<?php

namespace App\Services\PlanProduccion;

use App\Models\Estado;
use App\Models\Programas\PlanProduccion;
use App\Models\Programas\PlanProduccionMaterial;
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\SemProduccion;
use App\Models\Stock\SemConsumo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanProduccionUpdateService
{
    /** @var array<string, array{idMaterialPL: int, idSemConsumo: int}> */
    private array $celdasAfectadas = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $catalogoPorPg = [];

    /** @var array<int, int>|null */
    private ?array $semProdAMrp = null;

    public function __construct(
        private readonly PlanProduccionMrpSyncService $syncService,
        private readonly PlanProduccionRecetaService $recetaService,
        private readonly PlanProduccionCantidadService $cantidadService,
    ) {}

    /**
     * @param  array<int|string, array<int|string, mixed>>  $cantidades
     * @param  array<int|string, array<int|string, array<int|string, mixed>>>  $materiales
     * @return array{cambios: int, celdasAfectadas: list<array{idMaterialPL: int, idSemConsumo: int}>}
     */
    public function actualizar(array $cantidades, array $materiales = []): array
    {
        $cambios = 0;
        $this->celdasAfectadas = [];
        $this->catalogoPorPg = [];
        $this->semProdAMrp = null;

        DB::transaction(function () use ($cantidades, $materiales, &$cambios) {
            $cajasPorPgSem = $this->cajasPorProgramaSemana();
            $cambios += $this->guardarCajas($cantidades, $cajasPorPgSem);
            $cambios += $this->guardarMateriales($materiales, $cajasPorPgSem);
        });

        $this->syncService->sincronizarPlanConsumo();

        return [
            'cambios' => $cambios,
            'celdasAfectadas' => array_values($this->celdasAfectadas),
        ];
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
                        $this->marcarMaterialesProgramaSemana($idPg, $idSem);
                    }

                    continue;
                }

                $cajasPorPgSem[$idPg][$idSem] = $cantidad;

                if ($registro) {
                    if ((float) $registro->cantidad !== $cantidad) {
                        $registro->update(['cantidad' => $cantidad]);
                        $cambios++;
                        $this->marcarMaterialesProgramaSemana($idPg, $idSem);
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
                $this->marcarMaterialesProgramaSemana($idPg, $idSem);
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
            $catalogoPorPg[$idPg] = collect($this->catalogoPrograma($idPg, (string) ($nombresPg[$idPg] ?? '')))
                ->keyBy('id_material_pl');
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
                            $this->marcarCeldaMaterial($idMat, $idSem);
                        }

                        continue;
                    }

                    if ($registro) {
                        if ((float) $registro->cantidad !== $cantidad) {
                            $registro->update(['cantidad' => $cantidad]);
                            $cambios++;
                            $this->marcarCeldaMaterial($idMat, $idSem);
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
                    $this->marcarCeldaMaterial($idMat, $idSem);
                }
            }
        }

        return $cambios;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogoPrograma(int $idPg, string $nombrePg): array
    {
        if (! isset($this->catalogoPorPg[$idPg])) {
            $this->catalogoPorPg[$idPg] = $this->recetaService->materialesUnificadosParaPlan($nombrePg);
        }

        return $this->catalogoPorPg[$idPg];
    }

    /**
     * @return array<int, int> idSemProduccion => idSemConsumo
     */
    private function mapaSemProdAMrp(): array
    {
        if ($this->semProdAMrp !== null) {
            return $this->semProdAMrp;
        }

        $semanasMrp = SemConsumo::query()->activo()->get()->keyBy('codigoSemana');
        $mapa = [];

        foreach (SemProduccion::query()->activo()->get(['idSemProduccion', 'codigoSemana']) as $semProd) {
            $semMrp = $semanasMrp->get($semProd->codigoSemana);
            if ($semMrp) {
                $mapa[(int) $semProd->idSemProduccion] = (int) $semMrp->idSemConsumo;
            }
        }

        $this->semProdAMrp = $mapa;

        return $mapa;
    }

    private function marcarCeldaMaterial(int $idMaterial, int $idSemProduccion): void
    {
        if ($idMaterial <= 0 || $idSemProduccion <= 0) {
            return;
        }

        $idSemConsumo = $this->mapaSemProdAMrp()[$idSemProduccion] ?? null;
        if (! $idSemConsumo) {
            return;
        }

        $clave = $idMaterial.'|'.$idSemConsumo;
        $this->celdasAfectadas[$clave] = [
            'idMaterialPL' => $idMaterial,
            'idSemConsumo' => $idSemConsumo,
        ];
    }

    private function marcarMaterialesProgramaSemana(int $idPg, int $idSemProduccion): void
    {
        if ($idPg <= 0 || $idSemProduccion <= 0) {
            return;
        }

        $nombrePg = ProgramaGeneral::query()
            ->whereKey($idPg)
            ->value('nombre');

        foreach ($this->catalogoPrograma($idPg, (string) ($nombrePg ?? '')) as $mat) {
            $this->marcarCeldaMaterial((int) ($mat['id_material_pl'] ?? 0), $idSemProduccion);
        }
    }
}
