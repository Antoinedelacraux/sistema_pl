<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class SemProduccionSupport
{
    public static function etiqueta(CarbonInterface|string $fechaInicio, string $codigoSemana): string
    {
        if (is_string($fechaInicio)) {
            $fechaInicio = \Carbon\Carbon::parse($fechaInicio);
        }

        return SemanaRegistroSupport::fechaCabecera($fechaInicio).' '.trim($codigoSemana);
    }

    public static function fechaInicioDesdeCodigo(int $anio, string $codigoSemana): string
    {
        if (! preg_match('/^S(\d+)$/i', trim($codigoSemana), $m)) {
            return sprintf('%d-01-01', $anio);
        }

        $semana = (int) $m[1];

        return \Carbon\Carbon::now()
            ->setISODate($anio, max(1, min(53, $semana)), 1)
            ->format('Y-m-d');
    }
}
