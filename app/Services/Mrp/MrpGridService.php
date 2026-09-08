<?php

namespace App\Services\Mrp;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\PlanConsumo;
use App\Models\Stock\SemConsumo;
use App\Support\MaterialFamiliaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MrpGridService
{
    /**
     * @return Collection<int, SemConsumo>
     */
    public function semanas(): Collection
    {
        return SemConsumo::query()->activo()->orderBy('nroOrden')->get();
    }

    /**
     * @return array{
     *     semanas: Collection<int, SemConsumo>,
     *     plan: list<array<string, mixed>>,
     *     consumo_real: list<array<string, mixed>>,
     *     ingresos: list<array<string, mixed>>,
     *     total_materiales: int
     * }
     */
    public function datos(array $filtros = []): array
    {
        $semanas = $this->semanas();
        if ($semanas->isEmpty()) {
            return [
                'semanas' => $semanas,
                'plan' => [],
                'consumo_real' => [],
                'ingresos' => [],
                'total_materiales' => 0,
            ];
        }

        $materiales = $this->materialesPlan($filtros);
        $planPorMaterial = $this->planPorMaterialSemana();
        $consumoPorMaterial = $this->movimientosPorMaterialSemana(MovimientoStock::TIPO_EGRESO, $semanas);
        $ingresoPorMaterial = $this->movimientosPorMaterialSemana(MovimientoStock::TIPO_INGRESO, $semanas);
        $stockPorMaterial = $this->stockPorMaterial();

        $plan = [];
        $consumoReal = [];
        $ingresos = [];

        foreach ($materiales as $material) {
            $id = (int) $material->idMaterialPL;
            $codigo = (string) $material->CodigoPL;
            $base = [
                'idMaterialPL' => $id,
                'codigoPL' => $codigo,
                'descripcion' => (string) $material->nomMaterial,
            ];

            $filaPlan = $base;
            $filaConsumo = array_merge($base, [
                'stock_actual' => (float) ($stockPorMaterial[$id] ?? 0),
            ]);
            $filaIngreso = $base;
            $totalIngreso = 0.0;
            $saldoProyectado = (float) ($stockPorMaterial[$id] ?? 0);

            foreach ($semanas as $sem) {
                $idSem = (int) $sem->idSemConsumo;
                $clave = $id.'|'.$idSem;

                $planSem = (float) ($planPorMaterial[$clave] ?? 0);
                $consumoSem = (float) ($consumoPorMaterial[$clave] ?? 0);
                $ingSem = (float) ($ingresoPorMaterial[$clave] ?? 0);

                $filaPlan['sem_'.$idSem] = $planSem;

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
                    ];
                } elseif ($consumoSem > 0.0) {
                    $filaConsumo['sem_'.$idSem] = [
                        'valor' => $consumoSem,
                        'alerta' => false,
                    ];
                } else {
                    $filaConsumo['sem_'.$idSem] = [
                        'valor' => 0.0,
                        'alerta' => false,
                    ];
                }

                $saldoProyectado -= $pendiente;

                $filaIngreso['sem_'.$idSem] = $ingSem;
                $totalIngreso += $ingSem;
            }

            $filaIngreso['ingreso_total'] = $totalIngreso;

            $plan[] = $filaPlan;
            $consumoReal[] = $filaConsumo;
            $ingresos[] = $filaIngreso;
        }

        return [
            'semanas' => $semanas,
            'plan' => $plan,
            'consumo_real' => $consumoReal,
            'ingresos' => $ingresos,
            'total_materiales' => count($materiales),
        ];
    }

    /**
     * @return Collection<int, Familia>
     */
    public function familiasParaFiltro(): Collection
    {
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
    }

    /**
     * @param  array{familia?: mixed}  $filtros
     * @return list<MaterialProgramaPL>
     */
    private function materialesPlan(array $filtros): array
    {
        $ids = PlanConsumo::query()->distinct()->pluck('idMaterialPL');
        if ($ids->isEmpty()) {
            return [];
        }

        $query = MaterialProgramaPL::query()
            ->whereIn('idMaterialPL', $ids)
            ->orderBy('CodigoPL');

        $idFamilia = filled($filtros['familia'] ?? null) ? (int) $filtros['familia'] : 0;
        if ($idFamilia > 0) {
            $idsMaterialFamilia = MaterialFamiliaSupport::idsMaterialPorFamilia(
                $idFamilia,
                $ids->map(fn ($id) => (int) $id)->all()
            );

            $query->whereIn('idMaterialPL', $idsMaterialFamilia !== [] ? $idsMaterialFamilia : [-1]);
        }

        return $query->get()->all();
    }

    /**
     * @return array<string, float> clave idMaterial|idSem => cantidad
     */
    private function planPorMaterialSemana(): array
    {
        $mapa = [];
        PlanConsumo::query()
            ->select(['idMaterialPL', 'idSemConsumo', 'cantidad'])
            ->orderBy('idPlanConsumo')
            ->lazy(500)
            ->each(function ($fila) use (&$mapa) {
                $mapa[$fila->idMaterialPL.'|'.$fila->idSemConsumo] = (float) $fila->cantidad;
            });

        return $mapa;
    }

    /**
     * @return array<int, float> idMaterialPL => stock total
     */
    private function stockPorMaterial(): array
    {
        $mapa = [];
        DB::table('vw_StockAlmacenDetalle')
            ->selectRaw('idMaterialPL, COALESCE(SUM(stock), 0) AS stock_total')
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
     * @return array<string, float>
     */
    private function movimientosPorMaterialSemana(string $tipo, Collection $semanas): array
    {
        if ($semanas->isEmpty()) {
            return [];
        }

        $fechaMin = $semanas->min('fechaInicio')->format('Y-m-d');
        $fechaMax = $semanas->max('fechaInicio')->copy()->addDays(6)->format('Y-m-d');

        $movimientos = DB::table('MovimientoStock')
            ->select(['idMaterialPL', 'cantidad', 'fecha'])
            ->where('estado', 1)
            ->where('tipo', $tipo)
            ->whereNull('idTraslado')
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->get();

        $indiceSemana = [];
        foreach ($semanas as $sem) {
            $inicio = $sem->fechaInicio->copy()->startOfDay();
            $fin = $inicio->copy()->addDays(6)->endOfDay();
            $indiceSemana[(int) $sem->idSemConsumo] = [$inicio, $fin];
        }

        $mapa = [];
        foreach ($movimientos as $mov) {
            $fecha = \Carbon\Carbon::parse($mov->fecha)->startOfDay();
            $idMat = (int) $mov->idMaterialPL;

            foreach ($indiceSemana as $idSem => [$inicio, $fin]) {
                if ($fecha->between($inicio, $fin)) {
                    $clave = $idMat.'|'.$idSem;
                    $mapa[$clave] = ($mapa[$clave] ?? 0) + (float) $mov->cantidad;
                    break;
                }
            }
        }

        return $mapa;
    }
}
