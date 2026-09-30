<?php

namespace App\Services\PlanProduccion;

use App\Models\Programas\PlanProduccion;
use App\Models\Programas\PlanProduccionMaterial;
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\SemProduccion;
use App\Models\Stock\SemConsumo;
use App\Services\Mrp\MrpGridService;
use App\Services\Mrp\MrpPlanUpdateService;
use App\Support\CantidadCajaSupport;
use App\Support\PlanConsumoOrdenSupport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlanProduccionMrpSyncService
{
    public function __construct(
        private readonly PlanProduccionRecetaService $recetaService,
        private readonly PlanProduccionCantidadService $cantidadService,
        private readonly MrpPlanUpdateService $planUpdateService,
        private readonly MrpGridService $mrpGridService,
    ) {}

    /**
     * @return array{materiales: int, celdas: int}
     */
    public function sincronizarPlanConsumo(): array
    {
        $calculo = $this->calcularTotalesConsumo();
        if ($calculo === null) {
            return ['materiales' => 0, 'celdas' => 0];
        }

        ['totales' => $totales, 'materiales' => $materiales] = $calculo;

        $cantidades = [];
        $celdas = 0;

        foreach (array_keys($materiales) as $idMat) {
            foreach ($totales[$idMat] ?? [] as $idSem => $qtyRaw) {
                $qty = CantidadCajaSupport::redondearVisualizacion((float) $qtyRaw, $materiales[$idMat]['nom_familia'] ?? null);
                if ($qty == 0.0) {
                    continue;
                }

                $cantidades[$idMat][$idSem] = $qty;
                $celdas++;
            }
        }

        $this->planUpdateService->reemplazarMateriales($cantidades);
        $this->mrpGridService->forgetCaches();

        return [
            'materiales' => count($cantidades),
            'celdas' => $celdas,
        ];
    }

    /**
     * Plan de consumo agregado por material (misma lógica que sincroniza MRP).
     *
     * @return array{
     *   semanas: Collection<int, SemConsumo>,
     *   filas: list<array<string, mixed>>
     * }
     */
    /**
     * @param  array<int, list<array<string, mixed>>>|null  $materialesPorPg
     */
    public function planConsumoAlineado(Collection $semanasProd, ?array $materialesPorPg = null): array
    {
        $semanasMrp = SemConsumo::query()->activo()->orderBy('nroOrden')->get();
        $codigosProd = $semanasProd->pluck('codigoSemana')->all();

        $semanasVisibles = $semanasMrp->filter(
            fn (SemConsumo $sem) => in_array($sem->codigoSemana, $codigosProd, true)
        )->values();

        if ($semanasVisibles->isEmpty()) {
            return ['semanas' => collect(), 'filas' => []];
        }

        $calculo = $this->calcularTotalesConsumo($materialesPorPg);
        if ($calculo === null) {
            return ['semanas' => $semanasVisibles, 'filas' => []];
        }

        ['totales' => $totales, 'materiales' => $materiales] = $calculo;

        $filas = [];
        foreach ($materiales as $idMat => $meta) {
            $fila = [
                'idMaterialPL' => $idMat,
                'codigoPL' => $meta['codigoPL'],
                'descripcion' => $meta['descripcion'],
                'nom_familia' => $meta['nom_familia'] ?? '',
            ];

            foreach ($semanasVisibles as $sem) {
                $idSem = (int) $sem->idSemConsumo;
                $fila['sem_'.$idSem] = CantidadCajaSupport::redondearVisualizacion(
                    (float) ($totales[$idMat][$idSem] ?? 0),
                    $meta['nom_familia'] ?? null
                );
            }

            $filas[] = $fila;
        }

        $idsFilas = array_map(fn (array $f): int => (int) $f['idMaterialPL'], $filas);
        PlanConsumoOrdenSupport::asegurarOrden($idsFilas);
        $mapaOrden = PlanConsumoOrdenSupport::mapaOrden();
        usort($filas, static fn (array $a, array $b): int => PlanConsumoOrdenSupport::compararFilas($a, $b, $mapaOrden));

        return [
            'semanas' => $semanasVisibles,
            'filas' => $filas,
        ];
    }

    /**
     * @return array{
     *   totales: array<int, array<int, float>>,
     *   semProdAMrp: array<int, int>,
     *   materiales: array<int, array{codigoPL: string, descripcion: string}>
     * }|null
     */
    /**
     * @param  array<int, list<array<string, mixed>>>|null  $materialesPorPg
     */
    private function calcularTotalesConsumo(?array $materialesPorPg = null): ?array
    {
        $semanasProd = SemProduccion::query()->activo()->orderBy('nroOrden')->get();
        if ($semanasProd->isEmpty()) {
            return null;
        }

        $semanasMrp = SemConsumo::query()->activo()->get()->keyBy('codigoSemana');
        /** @var array<int, int> $semProdAMrp */
        $semProdAMrp = [];
        foreach ($semanasProd as $semProd) {
            $semMrp = $semanasMrp->get($semProd->codigoSemana);
            if ($semMrp) {
                $semProdAMrp[(int) $semProd->idSemProduccion] = (int) $semMrp->idSemConsumo;
            }
        }

        if ($semProdAMrp === []) {
            throw new InvalidArgumentException(
                'No hay semanas en común entre Plan de Producción y Plan MRP. Importe semanas compatibles en MRP.'
            );
        }

        $idsPg = PlanProduccion::query()->activo()->distinct()->pluck('idProgramaGeneral')->map(fn ($id) => (int) $id)->all();
        $idsPgOverride = PlanProduccionMaterial::query()->activo()->distinct()->pluck('idProgramaGeneral')->map(fn ($id) => (int) $id)->all();
        $idsPg = array_values(array_unique(array_merge($idsPg, $idsPgOverride)));

        $nombresPg = ProgramaGeneral::query()
            ->whereIn('idProgramaGeneral', $idsPg)
            ->pluck('nombre', 'idProgramaGeneral');
        $cajasPorPgSem = $this->cajasPorProgramaSemana();
        $overrides = $this->overridesPorPrograma();

        /** @var array<int, array<int, float>> $totales */
        $totales = [];
        /** @var array<int, array{codigoPL: string, descripcion: string}> $materiales */
        $materiales = [];

        foreach ($idsPg as $idPg) {
            $materialesPlan = $materialesPorPg[$idPg]
                ?? $this->recetaService->materialesUnificadosParaPlan((string) ($nombresPg[$idPg] ?? ''));

            foreach ($semanasProd as $semProd) {
                $idSemProd = (int) $semProd->idSemProduccion;
                $idSemMrp = $semProdAMrp[$idSemProd] ?? null;
                if (! $idSemMrp) {
                    continue;
                }

                $cajas = (float) ($cajasPorPgSem[$idPg][$idSemProd] ?? 0);

                foreach ($materialesPlan as $mat) {
                    $idMat = (int) $mat['id_material_pl'];
                    $override = $overrides[$idPg][$idMat][$idSemProd] ?? null;

                    if ($override !== null) {
                        $qty = CantidadCajaSupport::redondearVisualizacion((float) $override, $mat['nom_familia'] ?? null);
                    } else {
                        $qty = $this->cantidadService->calcularDesdeCajas($cajas, $mat);
                    }

                    if ($qty == 0.0) {
                        continue;
                    }

                    if (! isset($totales[$idMat][$idSemMrp])) {
                        $totales[$idMat][$idSemMrp] = 0.0;
                    }
                    $totales[$idMat][$idSemMrp] += $qty;

                    $materiales[$idMat] = [
                        'codigoPL' => (string) $mat['codigo_pl'],
                        'descripcion' => (string) $mat['nom_material'],
                        'nom_familia' => (string) ($mat['nom_familia'] ?? ''),
                    ];
                }
            }
        }

        return [
            'totales' => $totales,
            'semProdAMrp' => $semProdAMrp,
            'materiales' => $materiales,
        ];
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function cajasPorProgramaSemana(): array
    {
        $resultado = [];
        foreach (PlanProduccion::query()->activo()->get() as $plan) {
            $idPg = (int) $plan->idProgramaGeneral;
            $idSem = (int) $plan->idSemProduccion;
            $resultado[$idPg][$idSem] = (float) $plan->cantidad;
        }

        return $resultado;
    }

    /**
     * @return array<int, array<int, array<int, float>>>
     */
    private function overridesPorPrograma(): array
    {
        $resultado = [];
        foreach (PlanProduccionMaterial::query()->activo()->get() as $row) {
            $resultado[(int) $row->idProgramaGeneral][(int) $row->idMaterialPL][(int) $row->idSemProduccion] = (float) $row->cantidad;
        }

        return $resultado;
    }

}
