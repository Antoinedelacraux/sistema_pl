<?php

namespace App\Support;

final class PlanProduccionRecetaAliasSupport
{
    /** Programas cliente en receta (col. Program) que se fusionan en plan de ventas. */
    public const PROGRAMAS_WELLCOME_CALIBRE = [
        'WELLCOME (3J)',
        'WELLCOME (4J, 5J)',
    ];

    /**
     * @return list<string>
     */
    public static function programasClienteParaPlan(string $nombrePlan): array
    {
        if (self::planUsaFusionWellcomeCalibre($nombrePlan)) {
            return self::PROGRAMAS_WELLCOME_CALIBRE;
        }

        return [];
    }

    public static function planUsaFusionWellcomeCalibre(string $nombrePlan): bool
    {
        $clave = mb_strtolower(trim($nombrePlan));
        if ($clave === '') {
            return false;
        }

        if (str_contains($clave, 'pouch sweet globe') || str_contains($clave, 'sweet globe 8.2')) {
            return true;
        }

        if (str_contains($clave, 'wellcome') && (str_contains($clave, '3j') || str_contains($clave, '4j') || str_contains($clave, '5j'))) {
            return true;
        }

        return false;
    }
}
