<?php

namespace App\Services\PlanProduccion;

use App\Support\CantidadCajaSupport;

class PlanProduccionCantidadService
{
    /**
     * @param  array<string, mixed>  $material
     */
    public function calcularDesdeCajas(float $cajas, array $material): float
    {
        if ($cajas <= 0) {
            return 0.0;
        }

        $factor = (float) ($material['cantidad_por_caja'] ?? 0);

        return CantidadCajaSupport::redondear(
            $cajas * $factor,
            $material['nom_familia'] ?? null
        );
    }

    /**
     * @param  array<string, mixed>  $material
     */
    public function cajasDesdeMaterial(float $cantidadMaterial, array $material): float
    {
        if ($cantidadMaterial <= 0) {
            return 0.0;
        }

        $factor = (float) ($material['cantidad_por_caja'] ?? 0);
        if ($factor <= 0) {
            return 0.0;
        }

        return round($cantidadMaterial / $factor);
    }

    /**
     * @param  array<string, mixed>  $material
     */
    public function formatear(float $valor, array $material): string
    {
        if ($valor == 0.0) {
            return '—';
        }

        return CantidadCajaSupport::formatear($valor, $material['nom_familia'] ?? null);
    }
}
