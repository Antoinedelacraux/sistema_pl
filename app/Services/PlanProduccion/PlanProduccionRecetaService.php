<?php

namespace App\Services\PlanProduccion;

use App\Models\Estado;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\ProgramaGeneral;
use App\Support\CantidadCajaSupport;
use App\Support\FamiliaMaterialSupport;
use App\Support\PlanProduccionRecetaAliasSupport;
use Illuminate\Support\Facades\Cache;

class PlanProduccionRecetaService
{
    /**
     * Variantes de receta: nombre exacto o "Nombre (3J)", "Nombre (4J, 5J)", etc.
     *
     * @return list<int>
     */
    public function idsRecetaRelacionados(string $nombrePlan): array
    {
        $nombre = trim($nombrePlan);
        if ($nombre === '') {
            return [];
        }

        $ids = $this->idsPorNombreProgramaGeneral($nombre);
        $ids = array_values(array_unique(array_merge(
            $ids,
            $this->idsPorFusionCalibre($nombre)
        )));

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function idsPorNombreProgramaGeneral(string $nombrePlan): array
    {
        $lower = mb_strtolower(trim($nombrePlan));
        if ($lower === '') {
            return [];
        }

        return ProgramaGeneral::query()
            ->activo()
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(TRIM(nombre)) = ?', [$lower])
                    ->orWhereRaw('LOWER(TRIM(nombre)) LIKE ?', [$lower.' (%']);
            })
            ->whereIn('idProgramaGeneral', function ($sub) {
                $sub->select('idProgramaGeneral')
                    ->from('ProgramaFila')
                    ->whereNotNull('idProgramaGeneral')
                    ->where('Estado', Estado::ACTIVO)
                    ->distinct();
            })
            ->orderBy('nombre')
            ->pluck('idProgramaGeneral')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * 3J + 4J/5J: siempre une recetas WELLCOME para planes Sweet Globe / Wellcome calibre.
     *
     * @return list<int>
     */
    private function idsPorFusionCalibre(string $nombrePlan): array
    {
        if (! PlanProduccionRecetaAliasSupport::planUsaFusionWellcomeCalibre($nombrePlan)) {
            return [];
        }

        $programas = PlanProduccionRecetaAliasSupport::programasClienteParaPlan($nombrePlan);
        if ($programas === []) {
            return [];
        }

        return $this->idsPorProgramasCliente($programas);
    }

    /**
     * @param  list<string>  $nombresPrograma
     * @return list<int>
     */
    private function idsPorProgramasCliente(array $nombresPrograma): array
    {
        if ($nombresPrograma === []) {
            return [];
        }

        return ProgramaFila::query()
            ->activo()
            ->whereNotNull('idProgramaGeneral')
            ->whereHas('programa', fn ($q) => $q->whereIn('nomPrograma', $nombresPrograma))
            ->distinct()
            ->pluck('idProgramaGeneral')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Materiales unificados del plan (suma cantidad/caja si el material se repite).
     *
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>
     */
    public function materialesUnificadosParaPlan(string $nombrePlan): array
    {
        $ids = $this->idsRecetaRelacionados($nombrePlan);
        if ($ids === []) {
            return [];
        }

        $porPg = $this->materialesPorProgramaGeneral($ids);

        return $this->unificarListasMateriales(array_values($porPg));
    }

    /**
     * Desglose Plan producción (un solo PG): ids relacionados en caché + una pasada de materiales.
     *
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>
     */
    public function materialesUnificadosParaDesglose(int $idProgramaGeneral, string $nombrePlan): array
    {
        if ($idProgramaGeneral <= 0) {
            return [];
        }

        $ids = $this->idsRecetaRelacionadosParaDesglose($idProgramaGeneral, $nombrePlan);
        if ($ids === []) {
            return [];
        }

        $porPgRaw = $this->materialesPorProgramaGeneral($ids);
        $listas = [];
        foreach ($ids as $idReceta) {
            if (isset($porPgRaw[$idReceta])) {
                $listas[] = $porPgRaw[$idReceta];
            }
        }

        return $this->unificarListasMateriales($listas);
    }

    /**
     * @return list<int>
     */
    public function idsRecetaRelacionadosParaDesglose(int $idProgramaGeneral, string $nombrePlan): array
    {
        $version = (int) Cache::get('plan_produccion.cache_version', 0);
        $cacheKey = 'plan_receta.ids_desglose.v'.$version.'.pg.'.$idProgramaGeneral;

        return Cache::remember($cacheKey, 600, function () use ($idProgramaGeneral, $nombrePlan) {
            return array_values(array_unique(array_merge(
                [$idProgramaGeneral],
                $this->idsPorNombreProgramaGeneral($nombrePlan),
                $this->idsPorFusionCalibre($nombrePlan)
            )));
        });
    }

    /**
     * @param  array<int, string>  $nombresPorIdPg
     * @return array<int, list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>>
     */
    public function materialesUnificadosPorProgramas(array $nombresPorIdPg): array
    {
        if ($nombresPorIdPg === []) {
            return [];
        }

        $idsPorPg = $this->idsRecetaRelacionadosPorProgramas($nombresPorIdPg);
        $allIds = [];
        foreach ($idsPorPg as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if ($allIds === []) {
            return array_fill_keys(array_keys($nombresPorIdPg), []);
        }

        $porPgRaw = $this->materialesPorProgramaGeneral($allIds);
        $resultado = [];

        foreach ($nombresPorIdPg as $idPg => $nombre) {
            $ids = $idsPorPg[$idPg] ?? [];
            $listas = [];
            foreach ($ids as $idReceta) {
                if (isset($porPgRaw[$idReceta])) {
                    $listas[] = $porPgRaw[$idReceta];
                }
            }
            $resultado[$idPg] = $this->unificarListasMateriales($listas);
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $idsProgramaGeneral
     * @return array<int, list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>>
     */
    public function materialesPorProgramaGeneral(array $idsProgramaGeneral): array
    {
        if ($idsProgramaGeneral === []) {
            return [];
        }

        $filas = ProgramaFila::query()
            ->activo()
            ->whereIn('idProgramaGeneral', $idsProgramaGeneral)
            ->select([
                'idProgramaFila',
                'idProgramaGeneral',
                'idPrograma',
                'idMaterialPL',
                'idCantidadCaja',
                'idFamilia',
            ])
            ->with([
                'material:idMaterialPL,CodigoPL,nomMaterial',
                'cantidadCaja:idCantidadCaja,cantidad',
                'familia:idFamilia,nomFamilia',
            ])
            ->get();

        if ($filas->isEmpty()) {
            $filas = ProgramaFila::query()
                ->activo()
                ->whereHas('programa', function ($q) use ($idsProgramaGeneral) {
                    $q->whereIn('idProgramaGeneral', $idsProgramaGeneral);
                })
                ->select(['idProgramaFila', 'idProgramaGeneral', 'idPrograma', 'idMaterialPL', 'idCantidadCaja', 'idFamilia'])
                ->with([
                    'material:idMaterialPL,CodigoPL,nomMaterial',
                    'cantidadCaja:idCantidadCaja,cantidad',
                    'familia:idFamilia,nomFamilia',
                    'programa:idPrograma,idProgramaGeneral',
                ])
                ->get();
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $idPg = (int) ($fila->idProgramaGeneral ?? $fila->programa?->idProgramaGeneral ?? 0);
            if ($idPg <= 0 || ! in_array($idPg, $idsProgramaGeneral, true) || ! $fila->material) {
                continue;
            }

            $idMat = (int) $fila->idMaterialPL;
            $qty = CantidadCajaSupport::normalizarValor(
                $fila->cantidadCaja?->cantidad ?? 0,
                $fila->familia?->nomFamilia
            );

            if (! isset($resultado[$idPg][$idMat])) {
                $nomFamilia = $fila->familia?->nomFamilia;
                $resultado[$idPg][$idMat] = [
                    'id_material_pl' => $idMat,
                    'codigo_pl' => $fila->material->CodigoPL,
                    'nom_material' => $fila->material->nomMaterial,
                    'nom_familia' => $nomFamilia,
                    'cantidad_por_caja' => $qty,
                    'es_caja' => FamiliaMaterialSupport::esCajas($nomFamilia),
                    'decimales_cantidad' => CantidadCajaSupport::decimalesVisualizacionCelda($nomFamilia),
                ];
            } else {
                $resultado[$idPg][$idMat]['cantidad_por_caja'] = max(
                    (float) $resultado[$idPg][$idMat]['cantidad_por_caja'],
                    $qty
                );
            }
        }

        $ordenado = [];
        foreach ($resultado as $idPg => $materiales) {
            $lista = array_values($materiales);
            usort($lista, static fn ($a, $b) => strcmp($a['nom_material'] ?? '', $b['nom_material'] ?? ''));
            $ordenado[$idPg] = $lista;
        }

        return $ordenado;
    }

    /**
     * @param  array<int, string>  $nombresPorIdPg
     * @return array<int, list<int>>
     */
    private function idsRecetaRelacionadosPorProgramas(array $nombresPorIdPg): array
    {
        // Una sola consulta de candidatos para todos los planes; el match se hace en PHP.
        $candidatos = $nombresPorIdPg === [] ? [] : $this->candidatosProgramaGeneralConReceta();
        $matchEnPhp = $candidatos !== null;
        $fusionPorProgramas = [];

        $resultado = [];
        foreach ($nombresPorIdPg as $idPg => $nombre) {
            $ids = $matchEnPhp
                ? $this->idsPorNombreEnCandidatos($candidatos, (string) $nombre)
                : null;
            $ids ??= $this->idsPorNombreProgramaGeneral($nombre);

            $fusion = [];
            if (PlanProduccionRecetaAliasSupport::planUsaFusionWellcomeCalibre($nombre)) {
                $programas = PlanProduccionRecetaAliasSupport::programasClienteParaPlan($nombre);
                if ($programas !== []) {
                    $clave = json_encode($programas);
                    $fusion = $fusionPorProgramas[$clave] ??= $this->idsPorProgramasCliente($programas);
                }
            }

            $resultado[$idPg] = array_values(array_unique(array_merge($ids, $fusion)));
        }

        return $resultado;
    }

    /**
     * Mismos ProgramaGeneral (y en el mismo orden) que filtra idsPorNombreProgramaGeneral(), sin el filtro de nombre.
     * Devuelve null si algún nombre no es ASCII imprimible: ahí la collation (acentos, etc.) se deja a MySQL.
     *
     * @return list<array{id: int, clave: string}>|null
     */
    private function candidatosProgramaGeneralConReceta(): ?array
    {
        $filas = ProgramaGeneral::query()
            ->activo()
            ->whereIn('idProgramaGeneral', function ($sub) {
                $sub->select('idProgramaGeneral')
                    ->from('ProgramaFila')
                    ->whereNotNull('idProgramaGeneral')
                    ->where('Estado', Estado::ACTIVO)
                    ->distinct();
            })
            ->orderBy('nombre')
            ->get(['idProgramaGeneral', 'nombre']);

        $candidatos = [];
        foreach ($filas as $fila) {
            $nombre = (string) $fila->nombre;
            if (! $this->esAsciiImprimible($nombre)) {
                return null;
            }

            // Equivale a LOWER(TRIM(nombre)) de MySQL: TRIM solo quita espacios.
            $candidatos[] = ['id' => (int) $fila->idProgramaGeneral, 'clave' => strtolower(trim($nombre, ' '))];
        }

        return $candidatos;
    }

    /**
     * Réplica en PHP de: LOWER(TRIM(nombre)) = :lower OR LOWER(TRIM(nombre)) LIKE ':lower (%'.
     * Devuelve null (usar la consulta SQL) si el nombre trae caracteres no ASCII o comodines de LIKE.
     *
     * @param  list<array{id: int, clave: string}>  $candidatos
     * @return list<int>|null
     */
    private function idsPorNombreEnCandidatos(array $candidatos, string $nombrePlan): ?array
    {
        $lower = mb_strtolower(trim($nombrePlan));
        if ($lower === '') {
            return [];
        }

        if (! $this->esAsciiImprimible($lower) || strpbrk($lower, '%_\\') !== false) {
            return null;
        }

        $prefijo = $lower.' (';
        $ids = [];
        foreach ($candidatos as $candidato) {
            if ($candidato['clave'] === $lower || str_starts_with($candidato['clave'], $prefijo)) {
                $ids[] = $candidato['id'];
            }
        }

        return $ids;
    }

    private function esAsciiImprimible(string $texto): bool
    {
        return preg_match('/^[\x20-\x7E]*$/', $texto) === 1;
    }

    /**
     * @param  list<list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>>  $listas
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>
     */
    private function unificarListasMateriales(array $listas): array
    {
        $merged = [];

        foreach ($listas as $lista) {
            foreach ($lista as $mat) {
                $idMat = (int) $mat['id_material_pl'];
                if (! isset($merged[$idMat])) {
                    $merged[$idMat] = $mat;

                    continue;
                }

                $nomFamilia = $mat['nom_familia'] ?? $merged[$idMat]['nom_familia'];
                $merged[$idMat]['cantidad_por_caja'] = CantidadCajaSupport::redondear(
                    (float) $merged[$idMat]['cantidad_por_caja'] + (float) $mat['cantidad_por_caja'],
                    $nomFamilia
                );
                $merged[$idMat]['es_caja'] = ($merged[$idMat]['es_caja'] ?? false) || ($mat['es_caja'] ?? false);
            }
        }

        $resultado = array_values($merged);
        usort($resultado, static fn ($a, $b) => strcmp($a['nom_material'] ?? '', $b['nom_material'] ?? ''));

        return $resultado;
    }
}
