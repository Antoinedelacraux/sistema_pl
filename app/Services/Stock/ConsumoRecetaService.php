<?php

namespace App\Services\Stock;

use App\Models\Estado;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\ProgramaPresentacionParam;
use App\Models\Programas\Presentacion;
use App\Models\Stock\MovimientoStock;
use App\Support\CantidadCajaSupport;
use App\Support\FamiliaMaterialSupport;
use App\Support\UsoObservacionSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConsumoRecetaService
{
    /**
     * @return Collection<int, ProgramaGeneral>
     */
    public function programasGeneralesDisponibles(): Collection
    {
        return Cache::remember('catalogo.programas_generales_consumo', 60, function () {
            $idsGeneral = ProgramaFila::query()
                ->where('Estado', Estado::ACTIVO)
                ->whereNotNull('idProgramaGeneral')
                ->distinct()
                ->pluck('idProgramaGeneral');

            if ($idsGeneral->isEmpty()) {
                $idsGeneral = ProgramaCatalogo::query()
                    ->activo()
                    ->whereIn('idPrograma', ProgramaFila::query()
                        ->select('idPrograma')
                        ->where('Estado', Estado::ACTIVO)
                        ->distinct())
                    ->whereNotNull('idProgramaGeneral')
                    ->distinct()
                    ->pluck('idProgramaGeneral');
            }

            return ProgramaGeneral::query()
                ->activo()
                ->whereIn('idProgramaGeneral', $idsGeneral)
                ->orderBy('nombre')
                ->get(['idProgramaGeneral', 'nombre', 'CodigoProGeneral']);
        });
    }

    /**
     * @return Collection<int, ProgramaCatalogo>
     */
    public function programasDisponibles(?int $idProgramaGeneral = null): Collection
    {
        $cacheKey = $idProgramaGeneral !== null && $idProgramaGeneral > 0
            ? 'catalogo.programas_consumo.pg.'.$idProgramaGeneral
            : 'catalogo.programas_consumo';

        return Cache::remember($cacheKey, 60, function () use ($idProgramaGeneral) {
            $filasQuery = ProgramaFila::query()
                ->select('idPrograma')
                ->where('Estado', Estado::ACTIVO)
                ->distinct();

            if ($idProgramaGeneral !== null && $idProgramaGeneral > 0) {
                $filasQuery->where(function ($query) use ($idProgramaGeneral) {
                    $query->where('idProgramaGeneral', $idProgramaGeneral)
                        ->orWhereIn('idPrograma', ProgramaCatalogo::query()
                            ->select('idPrograma')
                            ->activo()
                            ->where('idProgramaGeneral', $idProgramaGeneral));
                });
            }

            return ProgramaCatalogo::query()
                ->activo()
                ->whereIn('idPrograma', $filasQuery)
                ->orderBy('nomPrograma')
                ->get(['idPrograma', 'nomPrograma', 'idProgramaGeneral']);
        });
    }

    /**
     * Mapa idProgramaGeneral → programas (desde filas de receta, no solo catálogo).
     *
     * @return array<string, list<array{id_programa: int, nom_programa: string|null}>>
     */
    public function programasAgrupadosPorGeneral(): array
    {
        return Cache::remember('catalogo.programas_por_general_consumo', 60, function () {
            $pares = ProgramaFila::query()
                ->where('Estado', Estado::ACTIVO)
                ->whereNotNull('idProgramaGeneral')
                ->select(['idProgramaGeneral', 'idPrograma'])
                ->distinct()
                ->get();

            if ($pares->isEmpty()) {
                return [];
            }

            $programas = ProgramaCatalogo::query()
                ->activo()
                ->whereIn('idPrograma', $pares->pluck('idPrograma')->unique())
                ->get(['idPrograma', 'nomPrograma'])
                ->keyBy('idPrograma');

            $agrupado = [];
            foreach ($pares as $par) {
                $idPg = (string) $par->idProgramaGeneral;
                $prog = $programas->get($par->idPrograma);
                if ($prog === null) {
                    continue;
                }

                $idProg = (string) $prog->idPrograma;
                $agrupado[$idPg][$idProg] = [
                    'id_programa' => (int) $prog->idPrograma,
                    'nom_programa' => $prog->nomPrograma,
                ];
            }

            foreach ($agrupado as $idPg => $items) {
                $lista = array_values($items);
                usort($lista, static fn (array $a, array $b): int => strcmp(
                    (string) ($a['nom_programa'] ?? ''),
                    (string) ($b['nom_programa'] ?? '')
                ));
                $agrupado[$idPg] = $lista;
            }

            return $agrupado;
        });
    }

    /**
     * @return Collection<int, Presentacion>
     */
    public function presentacionesDePrograma(int $idPrograma): Collection
    {
        if ($idPrograma <= 0) {
            return collect();
        }

        $cacheKey = 'catalogo.presentaciones_consumo.pg.'.$idPrograma;

        return Cache::remember($cacheKey, 300, function () use ($idPrograma) {
            return Presentacion::query()
                ->activo()
                ->whereIn('idPresentacion', ProgramaFila::query()
                    ->select('idPresentacion')
                    ->where('idPrograma', $idPrograma)
                    ->where('Estado', Estado::ACTIVO)
                    ->distinct())
                ->orderBy('nomPresentacion')
                ->get(['idPresentacion', 'nomPresentacion']);
        });
    }

    /**
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   cantidad_por_caja: float,
     *   id_familia: int|null,
     *   nom_familia: string|null,
     *   nom_uso: string|null,
     *   nom_uso_clave: string,
     *   clase_css_observacion: string,
     *   es_caja_factor: bool,
     *   usa_pallets: bool,
     *   ya_sacado: float,
     *   faltante: float,
     *   stock: float|null,
     *   decimales_cantidad: int
     * }>
     */
    public function materialesDePresentacion(
        int $idPrograma,
        int $idPresentacion,
        ?array $excluirIdsMovimiento = null,
        ?int $idAlmacen = null
    ): array {
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return [];
        }

        $filas = ProgramaFila::query()
            ->with(['material', 'cantidadCaja', 'familia', 'uso'])
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('Estado', Estado::ACTIVO)
            ->whereHas('material', fn ($q) => $q->where('Estado', Estado::ACTIVO))
            ->get();

        $idsMaterial = $filas->pluck('idMaterialPL')->unique()->values()->all();
        $sacados = $this->cantidadesSacadasPorMaterial(
            $idPrograma,
            $idPresentacion,
            $idsMaterial,
            $excluirIdsMovimiento ?? []
        );

        $stocks = $this->stocksPorMaterial($idsMaterial, $idAlmacen);

        return $filas
            ->groupBy('idMaterialPL')
            ->map(function (Collection $grupo) use ($sacados, $stocks) {
                $fila = $grupo->first();
                $material = $fila->material;
                $familia = $fila->familia?->nomFamilia;
                $uso = $fila->uso?->nomUso;
                $necesario = (float) ($fila->cantidadCaja?->cantidad ?? 0);
                $idMaterial = (int) $fila->idMaterialPL;
                $yaSacado = (float) ($sacados[$idMaterial] ?? 0);

                return [
                    'id_material_pl' => $idMaterial,
                    'codigo_pl' => $material?->CodigoPL,
                    'nom_material' => $material?->nomMaterial,
                    'cantidad_por_caja' => $necesario,
                    'id_familia' => $fila->idFamilia ? (int) $fila->idFamilia : null,
                    'nom_familia' => $familia,
                    'nom_uso' => $uso,
                    'nom_uso_clave' => UsoObservacionSupport::clave($uso),
                    'clase_css_observacion' => UsoObservacionSupport::claseCss($uso),
                    'es_caja_factor' => FamiliaMaterialSupport::esCajas($familia),
                    'usa_pallets' => FamiliaMaterialSupport::usaPallets($familia),
                    'ya_sacado' => $yaSacado,
                    'faltante' => $this->faltantePorSacar($necesario, $yaSacado, $familia),
                    'stock' => $stocks[$idMaterial] ?? null,
                    'decimales_cantidad' => CantidadCajaSupport::decimales($familia),
                ];
            })
            ->sortBy(function (array $item) {
                $prioridad = \App\Support\RecetaFilaOrdenSupport::prioridadDesdeNombres(
                    $item['nom_familia'] ?? null,
                    $item['nom_uso'] ?? null
                );
                $material = mb_strtolower((string) ($item['nom_material'] ?? ''));

                return sprintf('%04d|%s', $prioridad, $material);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{pallets: float, conformacion: float}
     */
    public function parametrosPresentacion(int $idPrograma, int $idPresentacion): array
    {
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return ['pallets' => 0.0, 'conformacion' => 0.0];
        }

        $param = ProgramaPresentacionParam::query()
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('Estado', Estado::ACTIVO)
            ->first();

        return [
            'pallets' => (float) ($param?->pallets ?? 0),
            'conformacion' => (float) ($param?->conformacion ?? 0),
        ];
    }

    /**
     * @return array{pallets: float, conformacion: float}
     */
    public function resolverParametrosPresentacion(
        int $idPrograma,
        int $idPresentacion,
        mixed $palletsInput = null,
        mixed $conformacionInput = null
    ): array {
        $params = $this->parametrosPresentacion($idPrograma, $idPresentacion);

        if ($palletsInput !== null && $palletsInput !== '' && is_numeric($palletsInput)) {
            $params['pallets'] = (float) $palletsInput;
        }

        if ($conformacionInput !== null && $conformacionInput !== '' && is_numeric($conformacionInput)) {
            $params['conformacion'] = (float) $conformacionInput;
        }

        return $params;
    }

    public function cantidadPorCaja(int $idPrograma, int $idPresentacion, int $idMaterialPL): float
    {
        $fila = ProgramaFila::query()
            ->with('cantidadCaja')
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('Estado', Estado::ACTIVO)
            ->first();

        return (float) ($fila?->cantidadCaja?->cantidad ?? 0);
    }

    /**
     * @param  list<array{id_material_pl?: mixed, cantidad?: mixed, total?: mixed}>  $lineasInput
     * @return list<array{id_material_pl: int, cantidad: float, nom_material: string|null}>
     */
    public function resolverLineasConsumo(
        int $idPrograma,
        int $idPresentacion,
        array $lineasInput = [],
        mixed $palletsInput = null,
        mixed $conformacionInput = null
    ): array {
        $receta = $this->materialesDePresentacion($idPrograma, $idPresentacion);
        $inputPorId = collect($lineasInput)->keyBy(fn ($linea) => (int) ($linea['id_material_pl'] ?? 0));
        $factoresCajas = $this->factoresCajasDesdeLineas($receta, $inputPorId);
        $params = $this->resolverParametrosPresentacion($idPrograma, $idPresentacion, $palletsInput, $conformacionInput);

        $lineas = [];
        foreach ($receta as $item) {
            $idMaterial = (int) $item['id_material_pl'];
            $input = $inputPorId->get($idMaterial, []);
            $totalOverride = trim((string) ($input['total'] ?? ''));

            if ($totalOverride !== '' && is_numeric($totalOverride) && (float) $totalOverride > 0) {
                $cantidad = CantidadCajaSupport::redondear((float) $totalOverride, $item['nom_familia'] ?? null);
            } else {
                $cantidad = $this->calcularCantidadMaterial(
                    $item,
                    $factoresCajas,
                    $input,
                    $params['pallets'],
                    $params['conformacion']
                );
            }

            if ($cantidad <= 0) {
                continue;
            }

            $lineas[] = [
                'id_material_pl' => $idMaterial,
                'cantidad' => $cantidad,
                'nom_material' => $item['nom_material'] ?? null,
            ];
        }

        return $lineas;
    }

    /**
     * Calcula cantidades necesarias por material para N cajas y/o pallets (misma lógica que consumo).
     *
     * @return array<int, float> idMaterialPL => cantidad
     */
    public function proyectarNecesidadesPorCajas(
        int $idPrograma,
        int $idPresentacion,
        float $cajas,
        ?float $pallets = null
    ): array {
        if ($idPrograma <= 0 || $idPresentacion <= 0 || ($cajas <= 0 && ($pallets === null || $pallets < 0))) {
            return [];
        }

        $receta = $this->materialesDePresentacion($idPrograma, $idPresentacion);
        $lineasInput = [];

        if ($cajas > 0) {
            foreach ($receta as $item) {
                if (empty($item['es_caja_factor'])) {
                    continue;
                }

                $lineasInput[] = [
                    'id_material_pl' => (int) $item['id_material_pl'],
                    'cantidad' => $cajas,
                ];
            }
        }

        $lineas = $this->resolverLineasConsumo($idPrograma, $idPresentacion, $lineasInput, $pallets);
        $mapa = [];
        $soloPallets = $cajas <= 0;
        $idsPallets = [];

        if ($soloPallets) {
            foreach ($receta as $item) {
                if (! empty($item['usa_pallets'])) {
                    $idsPallets[(int) $item['id_material_pl']] = true;
                }
            }
        }

        foreach ($lineas as $linea) {
            $idMaterial = (int) $linea['id_material_pl'];
            if ($soloPallets && ! isset($idsPallets[$idMaterial])) {
                continue;
            }

            $mapa[$idMaterial] = (float) $linea['cantidad'];
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $input
     */
    private function calcularCantidadMaterial(
        array $item,
        array $factoresCajas,
        array $input,
        float $pallets,
        float $conformacion
    ): float {
        $nomFamilia = $item['nom_familia'] ?? null;

        if (FamiliaMaterialSupport::esCajas($nomFamilia)) {
            return CantidadCajaSupport::redondear($this->baseCantidad($item, $input), $nomFamilia);
        }

        $factorCaja = $this->factorCajaParaItem($item, $factoresCajas);

        if (! empty($item['usa_pallets'])) {
            $conf = max($conformacion, 0.00001);

            return CantidadCajaSupport::redondear(($pallets / $conf) * $factorCaja, $nomFamilia);
        }

        return CantidadCajaSupport::redondear($this->baseCantidad($item, $input) * $factorCaja, $nomFamilia);
    }

    /**
     * @param  array<string, float>  $factoresCajas
     */
    private function factorCajaParaItem(array $item, array $factoresCajas): float
    {
        $clave = UsoObservacionSupport::clave($item['nom_uso'] ?? null);

        if (isset($factoresCajas[$clave]) && $factoresCajas[$clave] > 0) {
            return $factoresCajas[$clave];
        }

        return 1.0;
    }

    private function baseCantidad(array $item, array $input): float
    {
        if (FamiliaMaterialSupport::esCajas($item['nom_familia'] ?? null)) {
            $override = trim((string) ($input['cantidad'] ?? ''));
            if ($override !== '' && is_numeric($override) && (float) $override > 0) {
                return (float) $override;
            }
        }

        return (float) $item['cantidad_por_caja'];
    }

    public function esUsoFactorCaja(?string $nomUso): bool
    {
        return UsoObservacionSupport::esFactorCaja($nomUso);
    }

    public function claveUsoConsumo(?string $nomUso): string
    {
        return UsoObservacionSupport::clave($nomUso);
    }

    public function claseCssObservacion(?string $nomUso): string
    {
        return UsoObservacionSupport::claseCss($nomUso);
    }

    public function esFamiliaCajas(?string $nomFamilia): bool
    {
        return FamiliaMaterialSupport::esCajas($nomFamilia);
    }

    public function familiaUsaPallets(?string $nomFamilia): bool
    {
        return FamiliaMaterialSupport::usaPallets($nomFamilia);
    }

    /**
     * @param  list<array<string, mixed>>  $receta
     * @param  Collection<int, mixed>  $inputPorId
     * @return array<string, float>
     */
    /**
     * Cajas indicadas en el formulario (campo «otra cantidad» en filas Box / alternativa).
     */
    public function cajasSolicitadasEnLineas(
        int $idPrograma,
        int $idPresentacion,
        array $lineasInput,
        mixed $palletsInput = null,
        mixed $conformacionInput = null
    ): float {
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return 0.0;
        }

        $inputPorId = collect($lineasInput)->keyBy(fn ($linea) => (int) ($linea['id_material_pl'] ?? 0));
        $receta = $this->materialesDePresentacion($idPrograma, $idPresentacion);
        $factores = $this->factoresCajasDesdeLineas($receta, $inputPorId);

        if ($factores !== []) {
            return max($factores);
        }

        $lineas = $this->resolverLineasConsumo(
            $idPrograma,
            $idPresentacion,
            $lineasInput,
            $palletsInput,
            $conformacionInput
        );

        $cajas = 0.0;
        foreach ($receta as $item) {
            if (empty($item['es_caja_factor'])) {
                continue;
            }
            $idMaterial = (int) $item['id_material_pl'];
            foreach ($lineas as $linea) {
                if ((int) $linea['id_material_pl'] === $idMaterial) {
                    $cajas = max($cajas, (float) $linea['cantidad']);
                }
            }
        }

        return $cajas;
    }

    private function factoresCajasDesdeLineas(array $receta, Collection $inputPorId): array
    {
        $factores = [];

        foreach ($receta as $item) {
            if (empty($item['es_caja_factor'])) {
                continue;
            }

            $input = $inputPorId->get((int) $item['id_material_pl'], []);
            $override = trim((string) ($input['cantidad'] ?? ''));
            if ($override === '' || ! is_numeric($override) || (float) $override <= 0) {
                continue;
            }

            $clave = UsoObservacionSupport::clave($item['nom_uso'] ?? null);
            $factores[$clave] = (float) $override;
        }

        return $factores;
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<int, float>
     */
    private function stocksPorMaterial(array $idsMaterial, ?int $idAlmacen): array
    {
        if ($idAlmacen === null || $idAlmacen <= 0 || $idsMaterial === []) {
            return [];
        }

        return app(MovimientoService::class)->stocksDisponiblesPorMaterial($idsMaterial, $idAlmacen);
    }

    public function materialPerteneceAPresentacion(int $idPrograma, int $idPresentacion, int $idMaterialPL): bool
    {
        return ProgramaFila::query()
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('Estado', Estado::ACTIVO)
            ->exists();
    }

    public function cantidadSacada(
        int $idPrograma,
        int $idPresentacion,
        int $idMaterialPL,
        array $excluirIdsMovimiento = []
    ): float {
        $mapa = $this->cantidadesSacadasPorMaterial(
            $idPrograma,
            $idPresentacion,
            [$idMaterialPL],
            $excluirIdsMovimiento
        );

        return (float) ($mapa[$idMaterialPL] ?? 0);
    }

    /**
     * @param  Collection<int, MovimientoStock>  $movimientos
     * @return list<array{id_movimiento: int, id_material_pl: int, cantidad: float}>
     */
    public function lineasEdicionDesdeMovimientos(int $idPrograma, int $idPresentacion, Collection $movimientos): array
    {
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return [];
        }

        return $movimientos
            ->map(fn (MovimientoStock $movimiento) => [
                'id_movimiento' => (int) $movimiento->idMovimiento,
                'id_material_pl' => (int) $movimiento->idMaterialPL,
                'cantidad' => (float) $movimiento->cantidad,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $idsMaterial
     * @param  list<int>  $excluirIdsMovimiento
     * @return array<int, float>
     */
    public function cantidadesSacadasPorMaterial(
        int $idPrograma,
        int $idPresentacion,
        array $idsMaterial,
        array $excluirIdsMovimiento = []
    ): array {
        if ($idsMaterial === []) {
            return [];
        }

        $query = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado')
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->whereIn('idMaterialPL', $idsMaterial)
            ->groupBy('idMaterialPL')
            ->selectRaw('idMaterialPL, COALESCE(SUM(cantidad), 0) AS total');

        if ($excluirIdsMovimiento !== []) {
            $query->whereNotIn('idMovimiento', $excluirIdsMovimiento);
        }

        return $query
            ->pluck('total', 'idMaterialPL')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    public function faltantePorSacar(float $necesario, float $yaSacado, ?string $nomFamilia = null): float
    {
        if ($necesario <= 0) {
            return 0.0;
        }

        $resto = fmod($yaSacado, $necesario);
        if ($resto < 0.00001) {
            return $yaSacado < 0.00001
                ? CantidadCajaSupport::redondear($necesario, $nomFamilia)
                : 0.0;
        }

        return CantidadCajaSupport::redondear($necesario - $resto, $nomFamilia);
    }

    /**
     * @return array{
     *   stock: float,
     *   cantidad_por_caja: float,
     *   ya_sacado: float,
     *   faltante: float
     * }
     */
    public function hintConsumo(
        int $idPrograma,
        int $idPresentacion,
        int $idMaterialPL,
        int $idAlmacen,
        array $excluirIdsMovimiento = []
    ): array {
        $necesario = $this->cantidadPorCaja($idPrograma, $idPresentacion, $idMaterialPL);
        $yaSacado = $this->cantidadSacada($idPrograma, $idPresentacion, $idMaterialPL, $excluirIdsMovimiento);
        $stock = app(MovimientoService::class)->stockDisponible($idMaterialPL, $idAlmacen);
        $fila = ProgramaFila::query()
            ->with('familia')
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('Estado', Estado::ACTIVO)
            ->first();

        return [
            'stock' => $stock,
            'cantidad_por_caja' => $necesario,
            'ya_sacado' => $yaSacado,
            'faltante' => $this->faltantePorSacar($necesario, $yaSacado, $fila?->familia?->nomFamilia),
        ];
    }
}
