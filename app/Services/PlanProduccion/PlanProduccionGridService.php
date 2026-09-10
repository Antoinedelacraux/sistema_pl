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

        return Cache::remember($cacheKey, 120, fn () => $this->buildDatos());
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
    private function buildDatos(): array
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

        $programas = ProgramaGeneral::query()
            ->activo()
            ->whereIn('idProgramaGeneral', $cantidades->keys())
            ->get()
            ->keyBy('idProgramaGeneral');

        $ordenPg = PlanProduccion::query()
            ->selectRaw('idProgramaGeneral, MIN(nroOrden) AS nroOrden')
            ->whereIn('idSemProduccion', $idsSem)
            ->groupBy('idProgramaGeneral')
            ->orderBy('nroOrden')
            ->get();

        $overrides = PlanProduccionMaterial::query()
            ->activo()
            ->whereIn('idSemProduccion', $idsSem)
            ->get()
            ->groupBy('idProgramaGeneral');

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

            $materiales = [];
            $overridePg = ($overrides->get($idPg) ?? collect())->groupBy('idMaterialPL');

            foreach ($this->recetaService->materialesUnificadosParaPlan($pg->nombre) as $mat) {
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
                    'decimales_cantidad' => (int) ($mat['decimales_cantidad'] ?? 2),
                    'cantidad_por_caja' => CantidadCajaSupport::formatear(
                        (float) $mat['cantidad_por_caja'],
                        $mat['nom_familia']
                    ),
                    'cantidad_por_caja_num' => (float) $mat['cantidad_por_caja'],
                    'overrides' => $overrideMap,
                ];
            }

            $filas[] = [
                'idProgramaGeneral' => $idPg,
                'nombre' => $pg->nombre,
                'calificacion' => $calificacion,
                'materiales' => $materiales,
                'tiene_materiales' => $materiales !== [],
            ] + $porSem;
        }

        $planConsumo = $this->syncService->planConsumoAlineado($semanas);

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
}
