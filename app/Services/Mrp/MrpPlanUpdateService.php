<?php

namespace App\Services\Mrp;

use App\Models\Estado;
use App\Models\Stock\PlanConsumo;
use Illuminate\Support\Facades\DB;

class MrpPlanUpdateService
{
    /**
     * @param  array<int|string, array<int|string, mixed>>  $cantidades
     */
    public function actualizar(array $cantidades): int
    {
        $cambios = 0;

        DB::transaction(function () use ($cantidades, &$cambios) {
            foreach ($cantidades as $idMaterialPL => $semanas) {
                if (! is_array($semanas)) {
                    continue;
                }

                $idMaterialPL = (int) $idMaterialPL;
                if ($idMaterialPL <= 0) {
                    continue;
                }

                foreach ($semanas as $idSemConsumo => $valor) {
                    $idSemConsumo = (int) $idSemConsumo;
                    if ($idSemConsumo <= 0) {
                        continue;
                    }

                    $cantidad = round(max(0, (float) $valor), 2);
                    $registro = PlanConsumo::query()
                        ->where('idMaterialPL', $idMaterialPL)
                        ->where('idSemConsumo', $idSemConsumo)
                        ->first();

                    if ($cantidad == 0.0) {
                        if ($registro) {
                            $registro->delete();
                            $cambios++;
                        }

                        continue;
                    }

                    if ($registro) {
                        if ((float) $registro->cantidad === $cantidad) {
                            continue;
                        }

                        $registro->update(['cantidad' => $cantidad]);
                        $cambios++;

                        continue;
                    }

                    PlanConsumo::query()->create([
                        'idMaterialPL' => $idMaterialPL,
                        'idSemConsumo' => $idSemConsumo,
                        'cantidad' => $cantidad,
                        'Estado' => Estado::ACTIVO,
                    ]);
                    $cambios++;
                }
            }
        });

        return $cambios;
    }
}
