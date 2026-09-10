<?php

namespace App\Services\Mrp;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\PlanConsumo;
use App\Models\Stock\SemConsumo;
use App\Support\MaterialFamiliaSupport;
use App\Support\PlanConsumoOrdenSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MrpGridService
{
    private const CACHE_TTL_FAMILIAS = 300;

    /**
     * @return Collection<int, SemConsumo>
     */
    public function semanas(): Collection
    {
        return SemConsumo::query()
            ->activo()
            ->orderBy('nroOrden')
            ->get(['idSemConsumo', 'etiqueta', 'codigoSemana', 'fechaInicio', 'nroOrden', 'Estado']);
    }

    /**
     * @param  array{familia?: mixed}  $filtros
     * @param  list<'plan'|'consumo_real'|'ingresos'>  $secciones
     * @return array{
     *     semanas: Collection<int, SemConsumo>,
     *     plan: list<array<string, mixed>>,
     *     consumo_real: list<array<string, mixed>>,
     *     ingresos: list<array<string, mixed>>,
     *     total_materiales: int
     * }
     */
    public function datos(array $filtros, array $secciones = ['plan', 'consumo_real', 'ingresos']): array
    {
        $semanas = $this->semanas();
        $vacío = [
            'semanas' => $semanas,
            'plan' => [],
            'consumo_real' => [],
            'ingresos' => [],
            'total_materiales' => 0,
        ];

        if ($semanas->isEmpty()) {
            return $vacío;
        }

        $materiales = $this->materialesPlan($filtros);
        $idsMaterial = $materiales
            ->map(static fn (MaterialProgramaPL $m): int => (int) $m->idMaterialPL)
            ->all();

        if ($idsMaterial === []) {
            return $vacío;
        }

        $necesitaPlan = array_intersect(['plan', 'consumo_real'], $secciones) !== [];
        $necesitaConsumo = in_array('consumo_real', $secciones, true);
        $necesitaIngresos = in_array('ingresos', $secciones, true);

        $planPorMaterial = $necesitaPlan ? $this->planPorMaterialSemana($idsMaterial) : [];
        $consumoPorMaterial = $necesitaConsumo
            ? $this->movimientosPorMaterialSemana(MovimientoStock::TIPO_EGRESO, $semanas, $idsMaterial)
            : [];
        $ingresoPorMaterial = ($necesitaConsumo || $necesitaIngresos)
            ? $this->movimientosPorMaterialSemana(MovimientoStock::TIPO_INGRESO, $semanas, $idsMaterial)
            : [];
        $stockPorMaterial = $necesitaConsumo ? $this->stockPorMaterial($idsMaterial) : [];

        $plan = [];
        $consumoReal = [];
        $ingresos = [];

        foreach ($materiales as $material) {
            $id = (int) $material->idMaterialPL;
            $base = [
                'idMaterialPL' => $id,
                'codigoPL' => (string) $material->CodigoPL,
                'descripcion' => (string) $material->nomMaterial,
            ];

            if (in_array('plan', $secciones, true)) {
                $filaPlan = $base;
                foreach ($semanas as $sem) {
                    $clave = $id.'|'.(int) $sem->idSemConsumo;
                    $filaPlan['sem_'.$sem->idSemConsumo] = (float) ($planPorMaterial[$clave] ?? 0);
                }
                $plan[] = $filaPlan;
            }

            if ($necesitaConsumo) {
                $filaConsumo = array_merge($base, [
                    'stock_actual' => (float) ($stockPorMaterial[$id] ?? 0),
                ]);
                $saldoProyectado = (float) ($stockPorMaterial[$id] ?? 0);

                foreach ($semanas as $sem) {
                    $idSem = (int) $sem->idSemConsumo;
                    $clave = $id.'|'.$idSem;

                    $planSem = (float) ($planPorMaterial[$clave] ?? 0);
                    $consumoSem = (float) ($consumoPorMaterial[$clave] ?? 0);
                    $ingSem = (float) ($ingresoPorMaterial[$clave] ?? 0);

                    $pendiente = max(0.0, $planSem - $consumoSem);
                    $saldoProyectado += $ingSem;
                    $saldoProyectado -= $consumoSem;

                    if ($pendiente > 0.0 && $saldoProyectado < $pendiente) {
                        $valorProyectado = ($ingSem > 0.0 && $saldoProyectado < 0.0)
                            ? $saldoProyectado
                            : ($saldoProyectado - $pendiente);

                        $filaConsumo['sem_'.$idSem] = [
                            'valor' => $valorProyectado,
                            'alerta' => true,
                            'negativo' => true,
                        ];
                    } elseif ($consumoSem > 0.0) {
                        $stockParaDias = $saldoProyectado + $consumoSem;
                        $consumoDiario = $consumoSem / 7.0;
                        $diasStock = $consumoDiario > 0.0 ? round($stockParaDias / $consumoDiario, 2) : 0.0;

                        $filaConsumo['sem_'.$idSem] = [
                            'valor' => $diasStock,
                            'alerta' => $diasStock < 7.0,
                            'negativo' => false,
                        ];
                    } else {
                        $filaConsumo['sem_'.$idSem] = [
                            'valor' => 0.0,
                            'alerta' => false,
                            'negativo' => false,
                        ];
                    }

                    $saldoProyectado -= $pendiente;
                }

                $consumoReal[] = $filaConsumo;
            }

            if ($necesitaIngresos) {
                $filaIngreso = $base;
                $totalIngreso = 0.0;

                foreach ($semanas as $sem) {
                    $clave = $id.'|'.(int) $sem->idSemConsumo;
                    $ingSem = (float) ($ingresoPorMaterial[$clave] ?? 0);
                    $filaIngreso['sem_'.$sem->idSemConsumo] = $ingSem;
                    $totalIngreso += $ingSem;
                }

                $filaIngreso['ingreso_total'] = $totalIngreso;
                $ingresos[] = $filaIngreso;
            }
        }

        return [
            'semanas' => $semanas,
            'plan' => $plan,
            'consumo_real' => $consumoReal,
            'ingresos' => $ingresos,
            'total_materiales' => $materiales->count(),
        ];
    }

    /**
     * @return Collection<int, Familia>
     */
    public function familiasParaFiltro(): Collection
    {
        return Cache::remember('mrp.familias_filtro.v1', self::CACHE_TTL_FAMILIAS, function () {
            $idsMaterial = PlanConsumo::query()->distinct()->pluck('idMaterialPL');
            if ($idsMaterial->isEmpty()) {
                return collect();
            }

            $idsFamilia = MaterialFamiliaSupport::idsFamiliaDeMateriales($idsMaterial);
            if ($idsFamilia === []) {
                return collect();
            }

            return Familia::query()
                ->activo()
                ->whereIn('idFamilia', $idsFamilia)
                ->orderBy('nomFamilia')
                ->get(['idFamilia', 'nomFamilia']);
        });
    }

    public function forgetCaches(): void
    {
        Cache::forget('mrp.familias_filtro.v1');
    }

    /**
     * @param  array{familia?: mixed, materiales?: list<int>}  $filtros
     * @return Collection<int, MaterialProgramaPL>
     */
    private function materialesPlan(array $filtros): Collection
    {
        if (! empty($filtros['materiales']) && is_array($filtros['materiales'])) {
            $ids = collect($filtros['materiales'])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        } else {
            $ids = PlanConsumo::query()->distinct()->pluck('idMaterialPL');
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = MaterialProgramaPL::query()
            ->whereIn('idMaterialPL', $ids);

        $idFamilia = filled($filtros['familia'] ?? null) ? (int) $filtros['familia'] : 0;
        if ($idFamilia > 0) {
            $idsMaterialFamilia = MaterialFamiliaSupport::idsMaterialPorFamilia(
                $idFamilia,
                $ids->map(fn ($id) => (int) $id)->all()
            );

            $query->whereIn('idMaterialPL', $idsMaterialFamilia !== [] ? $idsMaterialFamilia : [-1]);
        }

        $materiales = $query->get(['idMaterialPL', 'CodigoPL', 'nomMaterial']);
        $mapaOrden = PlanConsumoOrdenSupport::mapaOrden();

        return $materiales
            ->sort(fn (MaterialProgramaPL $a, MaterialProgramaPL $b): int => PlanConsumoOrdenSupport::compararFilas($a, $b, $mapaOrden))
            ->values();
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<string, float> clave idMaterial|idSem => cantidad
     */
    private function planPorMaterialSemana(array $idsMaterial): array
    {
        if ($idsMaterial === []) {
            return [];
        }

        $mapa = [];
        PlanConsumo::query()
            ->select(['idMaterialPL', 'idSemConsumo', 'cantidad'])
            ->whereIn('idMaterialPL', $idsMaterial)
            ->orderBy('idPlanConsumo')
            ->lazy(500)
            ->each(function ($fila) use (&$mapa) {
                $mapa[$fila->idMaterialPL.'|'.$fila->idSemConsumo] = (float) $fila->cantidad;
            });

        return $mapa;
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<int, float> idMaterialPL => stock total
     */
    private function stockPorMaterial(array $idsMaterial): array
    {
        if ($idsMaterial === []) {
            return [];
        }

        $mapa = [];
        DB::table('vw_StockAlmacenDetalle')
            ->selectRaw('idMaterialPL, COALESCE(SUM(stock), 0) AS stock_total')
            ->whereIn('idMaterialPL', $idsMaterial)
            ->groupBy('idMaterialPL')
            ->orderBy('idMaterialPL')
            ->lazy(500)
            ->each(function ($fila) use (&$mapa) {
                $mapa[(int) $fila->idMaterialPL] = (float) $fila->stock_total;
            });

        return $mapa;
    }

    /**
     * @param  Collection<int, SemConsumo>  $semanas
     * @param  list<int>  $idsMaterial
     * @return array<string, float>
     */
    private function movimientosPorMaterialSemana(string $tipo, Collection $semanas, array $idsMaterial): array
    {
        if ($semanas->isEmpty() || $idsMaterial === []) {
            return [];
        }

        $fechaMin = $semanas->min('fechaInicio')->format('Y-m-d');
        $fechaMax = $semanas->max('fechaInicio')->copy()->addDays(6)->format('Y-m-d');

        $caseParts = [];
        foreach ($semanas as $sem) {
            $idSem = (int) $sem->idSemConsumo;
            $ini = $sem->fechaInicio->format('Y-m-d');
            $fin = $sem->fechaInicio->copy()->addDays(6)->format('Y-m-d');
            $caseParts[] = "WHEN fecha BETWEEN '{$ini}' AND '{$fin}' THEN {$idSem}";
        }

        $caseExpr = 'CASE '.implode(' ', $caseParts).' END';

        $mapa = [];
        DB::query()
            ->fromSub(function ($query) use ($tipo, $idsMaterial, $fechaMin, $fechaMax, $caseExpr) {
                $query->from('MovimientoStock')
                    ->selectRaw("idMaterialPL, cantidad, ({$caseExpr}) AS idSemConsumo")
                    ->where('estado', 1)
                    ->where('tipo', $tipo)
                    ->whereNull('idTraslado')
                    ->whereIn('idMaterialPL', $idsMaterial)
                    ->whereBetween('fecha', [$fechaMin, $fechaMax]);
            }, 'mov')
            ->selectRaw('idMaterialPL, idSemConsumo, SUM(cantidad) AS total')
            ->whereNotNull('idSemConsumo')
            ->groupBy('idMaterialPL', 'idSemConsumo')
            ->orderBy('idMaterialPL')
            ->lazy(500)
            ->each(function ($fila) use (&$mapa) {
                $mapa[((int) $fila->idMaterialPL).'|'.((int) $fila->idSemConsumo)] = (float) $fila->total;
            });

        return $mapa;
    }
}
