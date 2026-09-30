<?php

namespace App\Support;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\ProgramaFila;
use App\Models\Stock\PlanConsumo;
use App\Models\Stock\PlanConsumoOrden;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PlanConsumoOrdenSupport
{
    /** @var array<int, int>|null */
    private static ?array $mapaCache = null;

    /**
     * @return array<int, int> idMaterialPL => nroOrden
     */
    public static function mapaOrden(): array
    {
        if (self::$mapaCache !== null) {
            return self::$mapaCache;
        }

        $mapa = [];
        PlanConsumoOrden::query()
            ->orderBy('nroOrden')
            ->lazy(500)
            ->each(function (PlanConsumoOrden $fila) use (&$mapa) {
                $mapa[(int) $fila->idMaterialPL] = (int) $fila->nroOrden;
            });

        self::$mapaCache = $mapa;

        return $mapa;
    }

    public static function olvidarCache(): void
    {
        self::$mapaCache = null;
    }

    /**
     * Reemplaza el orden completo (p. ej. desde Stock.xlsx).
     *
     * @param  list<int>  $idsMaterialEnOrden
     */
    public static function reemplazarOrden(array $idsMaterialEnOrden): void
    {
        DB::transaction(function () use ($idsMaterialEnOrden) {
            PlanConsumoOrden::query()->delete();

            $filas = [];
            foreach ($idsMaterialEnOrden as $i => $idMaterial) {
                $idMaterial = (int) $idMaterial;
                if ($idMaterial <= 0) {
                    continue;
                }

                $filas[] = [
                    'idMaterialPL' => $idMaterial,
                    'nroOrden' => $i + 1,
                ];
            }

            foreach (array_chunk($filas, 500) as $lote) {
                PlanConsumoOrden::query()->insert($lote);
            }
        });

        self::olvidarCache();
    }

    /**
     * Orden de filas leídas de Stock.xlsx (misma secuencia que el Excel).
     *
     * @param  list<array{codigo: string, descripcion: string, familia?: string, cantidad?: float}>  $filasStock
     * @return list<int>
     */
    public static function idsOrdenDesdeFilasStock(array $filasStock): array
    {
        $ids = [];
        $vistos = [];

        foreach ($filasStock as $fila) {
            $codigo = (string) ($fila['codigo'] ?? '-');
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));

            if ($codigo === '-' && $descripcion === '') {
                continue;
            }

            $material = MaterialProgramaLookup::resolverPorCodigo(
                $codigo,
                $descripcion !== '' ? $descripcion : $codigo,
                reactivar: false,
                actualizarNombre: false
            );

            $id = (int) $material->idMaterialPL;
            if (isset($vistos[$id])) {
                continue;
            }

            $vistos[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Lee Stock.xlsx una vez y persiste el orden en planConsumoOrden (uso CLI / import).
     */
    public static function sincronizarOrdenDesdeStockExcel(?string $ruta = null): int
    {
        $ruta = $ruta ?? base_path('Stock.xlsx');
        if (! is_file($ruta)) {
            return 0;
        }

        $filas = StockExcelFilas::leer(IOFactory::load($ruta)->getActiveSheet());
        $idsStock = self::idsOrdenDesdeFilasStock($filas);
        if ($idsStock === []) {
            return 0;
        }

        $idsPlan = PlanConsumo::query()->distinct()->pluck('idMaterialPL')->map(fn ($id) => (int) $id)->all();
        $idsPlan = array_values(array_unique(array_merge($idsStock, $idsPlan)));

        self::reemplazarOrden(self::ordenarIdsPlanDesdeStock($idsPlan, $idsStock));

        return count($idsStock);
    }

    /**
     * Orden al importar Stock: secuencia del Excel + extras del plan en su sector.
     *
     * @param  list<int>  $idsMaterial  Materiales del plan a incluir
     * @param  list<int>  $idsStock     Orden de filas del Excel (sin leer disco)
     * @return list<int>
     */
    public static function ordenarIdsPlanDesdeStock(array $idsMaterial, array $idsStock): array
    {
        $idsMaterial = array_values(array_unique(array_filter(array_map('intval', $idsMaterial), fn (int $id) => $id > 0)));
        if ($idsMaterial === []) {
            return [];
        }

        $idsStock = array_values(array_unique(array_filter(array_map('intval', $idsStock), fn (int $id) => $id > 0)));

        $planSet = array_fill_keys($idsMaterial, true);
        [$prioridad, $codigoDe] = self::reglasPrioridadCodigo($idsMaterial);

        $extrasPorSector = [];
        $setStock = array_fill_keys($idsStock, true);
        foreach ($idsMaterial as $id) {
            if (isset($setStock[$id])) {
                continue;
            }
            $p = $prioridad($id);
            $extrasPorSector[$p][] = $id;
        }

        foreach ($extrasPorSector as $p => $lista) {
            usort($lista, static fn (int $a, int $b): int => strcmp($codigoDe($a), $codigoDe($b)));
            $extrasPorSector[$p] = $lista;
        }

        $injected = [];
        $output = [];
        $lastP = null;

        foreach ($idsStock as $id) {
            $p = $prioridad($id);

            if ($lastP !== null && $p !== $lastP && ! isset($injected[$lastP])) {
                foreach ($extrasPorSector[$lastP] ?? [] as $extraId) {
                    if (isset($planSet[$extraId])) {
                        $output[] = $extraId;
                    }
                }
                $injected[$lastP] = true;
            }

            if (isset($planSet[$id])) {
                $output[] = $id;
            }

            $lastP = $p;
        }

        if ($lastP !== null && ! isset($injected[$lastP])) {
            foreach ($extrasPorSector[$lastP] ?? [] as $extraId) {
                if (isset($planSet[$extraId])) {
                    $output[] = $extraId;
                }
            }
            $injected[$lastP] = true;
        }

        $orphanSectors = array_keys($extrasPorSector);
        usort($orphanSectors, static fn (int $a, int $b): int => $a <=> $b);

        foreach ($orphanSectors as $p) {
            if (isset($injected[$p])) {
                continue;
            }

            $lista = array_values(array_filter(
                $extrasPorSector[$p] ?? [],
                static fn (int $id): bool => isset($planSet[$id])
            ));

            if ($lista === []) {
                continue;
            }

            $pos = count($output);
            foreach ($output as $i => $id) {
                if ($prioridad($id) > $p) {
                    $pos = $i;
                    break;
                }
            }

            array_splice($output, $pos, 0, $lista);
            $injected[$p] = true;
        }

        return $output;
    }

    /**
     * Inserta materiales nuevos en planConsumoOrden por sector (sin leer Excel).
     *
     * @param  list<int>  $idsMaterial  Universo de ids que deben quedar ordenados
     */
    public static function incorporarEnOrdenPersistido(array $idsMaterial): void
    {
        $idsMaterial = array_values(array_unique(array_filter(array_map('intval', $idsMaterial), fn (int $id) => $id > 0)));
        if ($idsMaterial === []) {
            return;
        }

        $mapa = self::mapaOrden();
        $lista = array_keys($mapa);
        usort($lista, static fn (int $a, int $b): int => ($mapa[$a] ?? PHP_INT_MAX) <=> ($mapa[$b] ?? PHP_INT_MAX));
        $lista = array_values(array_intersect($lista, $idsMaterial));

        $faltantes = array_values(array_diff($idsMaterial, $lista));
        if ($faltantes === []) {
            return;
        }

        [$prioridad, $codigoDe] = self::reglasPrioridadCodigo(array_merge($lista, $faltantes));

        usort($faltantes, static function (int $a, int $b) use ($prioridad, $codigoDe): int {
            $pa = $prioridad($a);
            $pb = $prioridad($b);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($codigoDe($a), $codigoDe($b));
        });

        foreach ($faltantes as $id) {
            $p = $prioridad($id);
            $pos = count($lista);

            for ($i = 0; $i < count($lista); $i++) {
                $pe = $prioridad($lista[$i]);
                if ($pe > $p) {
                    $pos = $i;
                    break;
                }
            }

            while ($pos < count($lista) && $prioridad($lista[$pos]) < $p) {
                $pos++;
            }

            while ($pos < count($lista) && $prioridad($lista[$pos]) === $p && strcmp($codigoDe($lista[$pos]), $codigoDe($id)) < 0) {
                $pos++;
            }

            array_splice($lista, $pos, 0, [$id]);
        }

        self::reemplazarOrden($lista);
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array{0: callable(int): int, 1: callable(int): string}
     */
    private static function reglasPrioridadCodigo(array $idsMaterial): array
    {
        $meta = MaterialProgramaPL::query()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->get(['idMaterialPL', 'CodigoPL', 'nomMaterial'])
            ->keyBy('idMaterialPL');

        $mapaPrioridad = self::mapaPrioridadSectores($idsMaterial);

        $prioridad = static function (int $id) use ($mapaPrioridad, $meta): int {
            if (isset($mapaPrioridad[$id])) {
                return $mapaPrioridad[$id];
            }

            return self::prioridadInferidaDesdeNombre((string) ($meta->get($id)?->nomMaterial ?? ''));
        };

        $codigoDe = static function (int $id) use ($meta): string {
            return MaterialProgramaLookup::normalizarCodigo((string) ($meta->get($id)?->CodigoPL ?? ''));
        };

        return [$prioridad, $codigoDe];
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<int, int> idMaterialPL => prioridad sector
     */
    public static function mapaPrioridadSectores(array $idsMaterial): array
    {
        $idsMaterial = array_values(array_unique(array_filter(array_map('intval', $idsMaterial), fn (int $id) => $id > 0)));
        if ($idsMaterial === []) {
            return [];
        }

        $mapa = [];
        ProgramaFila::query()
            ->activo()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->select(['idMaterialPL', 'idFamilia', 'idUso'])
            ->with([
                'familia:idFamilia,nomFamilia',
                'uso:idUso,nomUso',
            ])
            ->lazy(500)
            ->each(function (ProgramaFila $fila) use (&$mapa) {
                $id = (int) $fila->idMaterialPL;
                $prioridad = RecetaFilaOrdenSupport::prioridadDesdeNombres(
                    $fila->familia?->nomFamilia,
                    $fila->uso?->nomUso
                );

                if (! isset($mapa[$id]) || $prioridad < $mapa[$id]) {
                    $mapa[$id] = $prioridad;
                }
            });

        return $mapa;
    }

    private static function prioridadInferidaDesdeNombre(string $nomMaterial): int
    {
        $u = mb_strtoupper(trim($nomMaterial));

        if ($u === '') {
            return 500;
        }

        if (str_starts_with($u, 'BOLSA')) {
            return RecetaFilaOrdenSupport::prioridadDesdeNombres('bags', null);
        }

        if (str_contains($u, 'TERMOGRAFO')) {
            return RecetaFilaOrdenSupport::prioridadDesdeNombres('temperature recorder', null);
        }

        if (str_starts_with($u, 'CAJA')) {
            return RecetaFilaOrdenSupport::prioridadDesdeNombres('cartons', null);
        }

        if (str_starts_with($u, 'ETIQUETA') || str_starts_with($u, 'FAJILLA') || str_starts_with($u, 'FAJILLON')) {
            return RecetaFilaOrdenSupport::prioridadDesdeNombres('sticker', null);
        }

        return 500;
    }

    /**
     * Garantiza fila en planConsumoOrden; nuevos ids se ubican por sector (sin Excel).
     *
     * @param  list<int>  $idsMaterial
     */
    public static function asegurarOrden(array $idsMaterial): void
    {
        $idsMaterial = array_values(array_unique(array_filter(array_map('intval', $idsMaterial), fn (int $id) => $id > 0)));
        if ($idsMaterial === []) {
            return;
        }

        $mapa = self::mapaOrden();
        $faltantes = array_values(array_diff($idsMaterial, array_keys($mapa)));
        if ($faltantes === []) {
            return;
        }

        $universo = array_values(array_unique(array_merge(array_keys($mapa), $idsMaterial)));
        self::incorporarEnOrdenPersistido($universo);
    }

    /**
     * @param  array{idMaterialPL: int, codigoPL?: string}|object{idMaterialPL: int, codigoPL?: string}  $a
     * @param  array{idMaterialPL: int, codigoPL?: string}|object{idMaterialPL: int, codigoPL?: string}  $b
     */
    public static function compararFilas(array|object $a, array|object $b, ?array $mapa = null): int
    {
        $mapa ??= self::mapaOrden();

        $idA = (int) (is_array($a) ? $a['idMaterialPL'] : $a->idMaterialPL);
        $idB = (int) (is_array($b) ? $b['idMaterialPL'] : $b->idMaterialPL);

        $ordenA = $mapa[$idA] ?? PHP_INT_MAX;
        $ordenB = $mapa[$idB] ?? PHP_INT_MAX;

        if ($ordenA !== $ordenB) {
            return $ordenA <=> $ordenB;
        }

        $codigoA = MaterialProgramaLookup::normalizarCodigo(
            is_array($a) ? (string) ($a['codigoPL'] ?? '') : (string) ($a->CodigoPL ?? $a->codigoPL ?? '')
        );
        $codigoB = MaterialProgramaLookup::normalizarCodigo(
            is_array($b) ? (string) ($b['codigoPL'] ?? '') : (string) ($b->CodigoPL ?? $b->codigoPL ?? '')
        );

        if ($codigoA !== $codigoB) {
            return strcmp($codigoA, $codigoB);
        }

        return $idA <=> $idB;
    }
}
