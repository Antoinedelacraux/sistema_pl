<?php

namespace App\Http\Requests\Stock;

use App\Support\Validation\LotePresenceVerifier;
use Illuminate\Validation\Validator;

/**
 * Evita una consulta COUNT(*) por línea en `lineas.*.id_material_pl => exists:MaterialProgramaPL,idMaterialPL`:
 * todos los ids se verifican con un solo whereIn. Mensajes y orden de errores idénticos.
 */
trait ValidaMaterialesEnLote
{
    protected function validarMaterialesEnLote(Validator $validator): void
    {
        $validator->setPresenceVerifier(new LotePresenceVerifier(
            $validator->getPresenceVerifier(),
            'MaterialProgramaPL',
            'idMaterialPL',
            function () use ($validator): array {
                $lineas = $validator->getData()['lineas'] ?? [];
                if (! is_array($lineas)) {
                    return [];
                }

                $valores = [];
                foreach ($lineas as $linea) {
                    if (is_array($linea) && array_key_exists('id_material_pl', $linea)) {
                        $valores[] = $linea['id_material_pl'];
                    }
                }

                return $valores;
            }
        ));
    }
}
