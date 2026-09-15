<?php

namespace App\Services\PlanProduccion;

use App\Models\Programas\PlanProduccion;
use App\Models\Programas\PlanProduccionMaterial;
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\SemProduccion;
use App\Support\CantidadCajaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlanProduccionGridService
{
    public function __construct(
        private readonly PlanProduccionRecetaService $recetaService,
        private readonly PlanProduccionCantidadService $cantidadService,
        private readonly PlanProduccionMrpSyncService $syncService,
    ) {}

    /**
     * @return array{
     *   semanas: Collection<int, SemProduccion>,
     *   filas: list<array<string, mixed>>,
     *   planConsumo: list<array<string, mixed>>,
     *   semanasPlanConsumo: Collection,
     *   total_programas: int
     * }
     */
    public function datos(): array
    {
        $version = (int) Cache::get('plan_produccion.cache_version', 0);
        $cacheKey = 'plan_produccion.datos.v'.$version;

        return Cache::remember($cacheKey, 120, fn () => $this->buildDatos(incluirMaterialesEnFilas: false));
    }

    /**
     * Materiales de un programa (desglose plan de ventas), bajo demanda.
     *
     * @return list<array{
     *   id: int,
     *   c: string|null,
     *   n: string|null,
     *   f: string,
     *   x: float,
     *   xf: string,
     *   caja: int,
     *   dec: int,
     *   o?: array<string, float>
     * }>
     */
    public function materialesPayload(int $idProgramaGeneral): array
    {
        if ($idProgramaGeneral <= 0) {
            return [];
        }

        $version = (int) Cache::get('plan_produccion.cache_version', 0);
        $cacheKey = 'plan_produccion.mats.v'.$version.'.pg.'.$idProgramaGeneral;

        return Cache::remember($cacheKey, 120, fn () => $this->buildMaterialesPayload($idProgramaGeneral));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materialesDetalle(int $idProgramaGeneral): array
    {
        return $this->mapMaterialesDetalle(
            $idProgramaGeneral,
            $this->materialesCrudos($idProgramaGeneral)
        );
    }

    /**
     * @return array{
     *   semanas: Collection<int, SemProduccion>,
     *   filas: list<array<string, mixed>>,
     *   planConsumo: list<array<string, mixed>>,
     *   semanasPlanConsumo: Collection,
     *   total_programas: int
     * }
     */
    private function buildDatos(bool $incluirMaterialesEnFilas = false): array
    {
        $semanas = SemProduccion::query()
            ->activo()
            ->orderBy('nroOrden')
            ->get();

        if ($semanas->isEmpty()) {
            return [
                'semanas' => $semanas,
                'filas' => [],
                'planConsumo' => [],
                'semanasPlanConsumo' => collect(),
                'total_programas' => 0,
            ];
        }

        $idsSem = $semanas->pluck('idSemProduccion')->all();
        $cantidades = PlanProduccion::query()
            ->activo()
            ->whereIn('idSemProduccion', $idsSem)
            ->get()
            ->groupBy('idProgramaGeneral');

        $overrides = PlanProduccionMaterial::query()
            ->activo()
            ->whereIn('idSemProduccion', $idsSem)
            ->get()
            ->groupBy('idProgramaGeneral');

        $idsPgTodos = $cantidades->keys()->merge($overrides->keys())->unique()->values();
        $programas = ProgramaGeneral::query()
            ->activo()
            ->whereIn('idProgramaGeneral', $idsPgTodos)
            ->get()
            ->keyBy('idProgramaGeneral');

        $ordenPg = PlanProduccion::query()
            ->selectRaw('idProgramaGeneral, MIN(nroOrden) AS nroOrden')
            ->whereIn('idSemProduccion', $idsSem)
            ->groupBy('idProgramaGeneral')
            ->orderBy('nroOrden')
            ->get();

        $nombresPorIdPg = [];
        foreach ($idsPgTodos as $idPgRaw) {
            $idPg = (int) $idPgRaw;
            $pg = $programas->get($idPg);
            if ($pg) {
                $nombresPorIdPg[$idPg] = $pg->nombre;
            }
        }

        $materialesPorPg = $this->recetaService->materialesUnificadosPorProgramas($nombresPorIdPg);

        $filas = [];
        foreach ($ordenPg as $item) {
            $idPg = (int) $item->idProgramaGeneral;
            $pg = $programas->get($idPg);
            if (! $pg) {
                continue;
            }

            $porSem = [];
            $calificacion = null;
            foreach ($cantidades->get($idPg, collect()) as $plan) {
                $clave = 'sem_'.$plan->idSemProduccion;
                $porSem[$clave] = (float) $plan->cantidad;
                $calificacion = $plan->calificacion ?? $calificacion;
            }

            $matsCrudos = $materialesPorPg[$idPg] ?? [];
            $materiales = $incluirMaterialesEnFilas
                ? $this->mapMaterialesDetalle($idPg, $matsCrudos, $overrides->get($idPg))
                : [];

            $filas[] = [
                'idProgramaGeneral' => $idPg,
                'nombre' => $pg->nombre,
                'calificacion' => $calificacion,
                'materiales' => $materiales,
                'tiene_materiales' => $matsCrudos !== [],
            ] + $porSem;
        }

        $planConsumo = $this->syncService->planConsumoAlineado($semanas, $materialesPorPg);

        return [
            'semanas' => $semanas,
            'filas' => $filas,
            'planConsumo' => $planConsumo['filas'],
            'semanasPlanConsumo' => $planConsumo['semanas'],
            'total_programas' => count($filas),
        ];
    }

    public function forgetCaches(): void
    {
        $version = (int) Cache::get('plan_produccion.cache_version', 0);
        Cache::forever('plan_produccion.cache_version', $version + 1);
    }

    /**
     * @param  list<array<string, mixed>>  $matsCrudos
     * @return list<array<string, mixed>>
     */
    private function mapMaterialesDetalle(int $idPg, array $matsCrudos, ?Collection $overrideRows = null): array
    {
        $overridePg = ($overrideRows ?? collect())->groupBy('idMaterialPL');
        $materiales = [];

        foreach ($matsCrudos as $mat) {
            $overrideMap = [];
            foreach ($overridePg->get($mat['id_material_pl']) ?? collect() as $overrideRow) {
                $overrideMap[(string) $overrideRow->idSemProduccion] = (float) $overrideRow->cantidad;
            }

            $materiales[] = [
                'id_material_pl' => $mat['id_material_pl'],
                'codigo_pl' => $mat['codigo_pl'],
                'descripcion' => $mat['nom_material'],
                'nom_familia' => $mat['nom_familia'],
                'es_caja' => (bool) ($mat['es_caja'] ?? false),
                'decimales_cantidad' => CantidadCajaSupport::decimalesVisualizacionCelda($mat['nom_familia'] ?? null),
                'cantidad_por_caja' => CantidadCajaSupport::formatearVisualizacionFactor(
                    (float) $mat['cantidad_por_caja'],
                    $mat['nom_familia']
                ),
                'cantidad_por_caja_num' => (float) $mat['cantidad_por_caja'],
                'overrides' => $overrideMap,
            ];
        }

        return $materiales;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function materialesCrudos(int $idProgramaGeneral): array
    {
        $pg = ProgramaGeneral::query()->activo()->find($idProgramaGeneral);
        if ($pg === null) {
            return [];
        }

        $materialesPorPg = $this->recetaService->materialesUnificadosPorProgramas([
            $idProgramaGeneral => $pg->nombre,
        ]);

        return $materialesPorPg[$idProgramaGeneral] ?? [];
    }

    /**
     * @return list<array{id: int, c: string|null, n: string|null, f: string, x: float, xf: string, caja: int, dec: int, o?: array<string, float>}>
     */
    private function buildMaterialesPayload(int $idProgramaGeneral): array
    {
        $idsSem = SemProduccion::query()->activo()->pluck('idSemProduccion')->all();
        $overrideRows = PlanProduccionMaterial::query()
            ->activo()
            ->where('idProgramaGeneral', $idProgramaGeneral)
            ->whereIn('idSemProduccion', $idsSem)
            ->get();

        $detalle = $this->mapMaterialesDetalle($idProgramaGeneral, $this->materialesCrudos($idProgramaGeneral), $overrideRows);
        $payload = [];

        foreach ($detalle as $mat) {
            $item = [
                'id' => (int) $mat['id_material_pl'],
                'c' => $mat['codigo_pl'],
                'n' => $mat['descripcion'],
                'f' => $mat['nom_familia'] ?? '',
                'x' => (float) $mat['cantidad_por_caja_num'],
                'xf' => $mat['cantidad_por_caja'],
                'caja' => ! empty($mat['es_caja']) ? 1 : 0,
                'dec' => (int) ($mat['decimales_cantidad'] ?? 2),
            ];
            if (! empty($mat['overrides'])) {
                $item['o'] = $mat['overrides'];
            }
            $payload[] = $item;
        }

        return $payload;
    }
}
