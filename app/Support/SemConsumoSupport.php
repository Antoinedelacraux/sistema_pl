<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class SemConsumoSupport
{
    /**
     * Etiqueta visible: «16-Nov S47»
     */
    public static function etiqueta(CarbonInterface|string $fechaInicio, string $codigoSemana): string
    {
        if (is_string($fechaInicio)) {
            $fechaInicio = \Carbon\Carbon::parse($fechaInicio);
        }

        return SemanaRegistroSupport::fechaCabecera($fechaInicio).' '.trim($codigoSemana);
    }
}
