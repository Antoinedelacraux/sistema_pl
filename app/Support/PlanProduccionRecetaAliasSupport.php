<?php

namespace App\Support;

final class PlanProduccionRecetaAliasSupport
{
    /**
     * Plan de ventas (PG) → programas cliente en receta (col. Program del Excel).
     *
     * @var array<string, list<string>>
     */
    private const PROGRAMAS_CLIENTE_POR_PLAN = [
        'pouch sweet globe 8.2 kg ff' => ['WELLCOME (3J)', 'WELLCOME (4J, 5J)'],
    ];

    /**
     * @return list<string>
     */
    public static function programasClienteParaPlan(string $nombrePlan): array
    {
        $clave = mb_strtolower(trim($nombrePlan));

        return self::PROGRAMAS_CLIENTE_POR_PLAN[$clave] ?? [];
    }
}
