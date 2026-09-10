<?php

namespace App\Services\PlanProduccion;

use App\Models\Estado;
use App\Models\Programas\PlanProduccion;
use App\Models\Programas\PlanProduccionMaterial;
use App\Models\Programas\ProgramaGeneral;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanProduccionUpdateService
{
    public function __construct(
        private readonly PlanProduccionMrpSyncService $syncService,
        private readonly PlanProduccionRecetaService $recetaService,
        private readonly PlanProduccionCantidadService $cantidadService,
    ) {}

    /**
     * @param  array<int|string, array<int|string, mixed>>  $cantidades
     * @param  array<int|string, array<int|string, array<int|string, mixed>>>  $materiales
     */
    public function actualizar(array $cantidades, array $materiales = []): int
    {
        $cambios = 0;

        DB::transaction(function () use ($cantidades, $materiales, &$cambios) {
            $cajasPorPgSem = $this->cajasPorProgramaSemana();
            $cambios += $this->guardarCajas($cantidades, $cajasPorPgSem);
            $cambios += $this->guardarMateriales($materiales, $cajasPorPgSem);
        });

        $this->syncService->sincronizarPlanConsumo();

        return $cambios;
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function cajasPorProgramaSemana(): array
    {
        $resultado = [];
        foreach (PlanProduccion::query()->activo()->get(['idProgramaGeneral', 'idSemProduccion', 'cantidad']) as $plan) {
            $resultado[(int) $plan->idProgramaGeneral][(int) $plan->idSemProduccion] = (float) $plan->cantidad;
        }

        return $resultado;
    }

    /**
     * @param  array<int|string, array<int|string, mixed>>  $cantidades
     * @param  array<int, array<int, float>>  $cajasPorPgSem
     */
    private function guardarCajas(array $cantidades, array &$cajasPorPgSem): int
    {
        $cambios = 0;
        $existentes = PlanProduccion::query()
            ->activo()
            ->get(['idPlanProduccion', 'idProgramaGeneral', 'idSemProduccion', 'cantidad', 'nroOrden'])
            ->groupBy('idProgramaGeneral');

        $maxNroOrden = (int) PlanProduccion::query()->max('nroOrden');
        $nroOrdenPorPg = PlanProduccion::query()
            ->activo()
            ->selectRaw('idProgramaGeneral, MIN(nroOrden) AS nroOrden')
            ->groupBy('idProgramaGeneral')
            ->pluck('nroOrden', 'idProgramaGeneral');

        foreach ($cantidades as $idProgramaGeneral => $semanas) {
            if (! is_array($semanas)) {
                continue;
            }

            $idPg = (int) $idProgramaGeneral;
            if ($idPg <= 0) {
                continue;
            }

            /** @var Collection<int, PlanProduccion> $porSem */
            $porSem = ($existentes->get($idPg) ?? collect())->keyBy('idSemProduccion');

            foreach ($semanas as $idSemProduccion => $valor) {
                $idSem = (int) $idSemProduccion;
                if ($idSem <= 0) {
                    continue;
                }

                $cantidad = max(0, (float) $valor);
                $registro = $porSem->get($idSem);

                if ($cantidad == 0.0) {
                    if ($registro) {
                        $registro->delete();
                        unset($cajasPorPgSem[$idPg][$idSem]);
                        $cambios++;
                    }

                    continue;
                }

                $cajasPorPgSem[$idPg][$idSem] = $cantidad;

                if ($registro) {
                    if ((float) $registro->cantidad !== $cantidad) {
                        $registro->update(['cantidad' => $cantidad]);
                        $cambios++;
                    }

                    continue;
                }

                $nroOrden = (int) ($nroOrdenPorPg[$idPg] ?? ++$maxNroOrden);

                PlanProduccion::query()->create([
                    'idProgramaGeneral' => $idPg,
                    'idSemProduccion' => $idSem,
                    'cantidad' => $cantidad,
                    'nroOrden' => $nroOrden,
                    'Estado' => Estado::ACTIVO,
                ]);
                $cambios++;
            }
        }

        return $cambios;
    }

    /**
     * @param  array<int|string, array<int|string, array<int|string, mixed>>>  $materiales
     * @param  array<int, array<int, float>>  $cajasPorPgSem
     */
    private function guardarMateriales(array $materiales, array $cajasPorPgSem): int
    {
        if ($materiales === []) {
            return 0;
        }

        $cambios = 0;
        $idsPg = array_map('intval', array_keys($materiales));
        $nombresPg = ProgramaGeneral::query()
            ->whereIn('idProgramaGeneral', $idsPg)
            ->pluck('nombre', 'idProgramaGeneral');
        $catalogoPorPg = [];
        foreach ($idsPg as $idPg) {
            $lista = $this->recetaService->materialesUnificadosParaPlan((string) ($nombresPg[$idPg] ?? ''));
            $catalogoPorPg[$idPg] = collect($lista)->keyBy('id_material_pl');
        }

        $overrides = PlanProduccionMaterial::query()
            ->activo()
            ->whereIn('idProgramaGeneral', $idsPg)
            ->get()
            ->keyBy(static fn (PlanProduccionMaterial $row): string => $row->idProgramaGeneral.'|'.$row->idMaterialPL.'|'.$row->idSemProduccion);

        foreach ($materiales as $idPgRaw => $porMaterial) {
            if (! is_array($porMaterial)) {
                continue;
            }

            $idPg = (int) $idPgRaw;
            if ($idPg <= 0) {
                continue;
            }

            $matsPg = $catalogoPorPg[$idPg] ?? collect();

            foreach ($porMaterial as $idMatRaw => $semanas) {
                if (! is_array($semanas)) {
                    continue;
                }

                $idMat = (int) $idMatRaw;
                if ($idMat <= 0) {
                    continue;
                }

                $mat = $matsPg->get($idMat);
                if (! $mat) {
                    continue;
                }

                foreach ($semanas as $idSemRaw => $valor) {
                    $idSem = (int) $idSemRaw;
                    if ($idSem <= 0) {
                        continue;
                    }

                    $cantidad = max(0, (float) $valor);
                    $clave = $idPg.'|'.$idMat.'|'.$idSem;
                    $registro = $overrides->get($clave);

                    $cajas = (float) ($cajasPorPgSem[$idPg][$idSem] ?? 0);
                    $calculado = $this->cantidadService->calcularDesdeCajas($cajas, $mat);
                    $esOverride = abs($cantidad - $calculado) > 0.0001;

                    if (! $esOverride || $cantidad == 0.0) {
                        if ($registro) {
                            $registro->delete();
                            $overrides->forget($clave);
                            $cambios++;
                        }

                        continue;
                    }

                    if ($registro) {
                        if ((float) $registro->cantidad !== $cantidad) {
                            $registro->update(['cantidad' => $cantidad]);
                            $cambios++;
                        }

                        continue;
                    }

                    PlanProduccionMaterial::query()->create([
                        'idProgramaGeneral' => $idPg,
                        'idMaterialPL' => $idMat,
                        'idSemProduccion' => $idSem,
                        'cantidad' => $cantidad,
                        'Estado' => Estado::ACTIVO,
                    ]);
                    $cambios++;
                }
            }
        }

        return $cambios;
    }
}
