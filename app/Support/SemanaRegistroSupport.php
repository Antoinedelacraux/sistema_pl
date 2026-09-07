<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class SemanaRegistroSupport
{
    /**
     * Semanas de 7 días desde la fecha ancla (día 1 = S1).
     *
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string, fecha_cabecera: string, anio: int}>
     */
    public static function semanasDesdeAncla(CarbonInterface $ancla, CarbonInterface $hasta): array
    {
        $inicio = $ancla->copy()->startOfDay();
        $finRango = $hasta->copy()->startOfDay();
        $semanas = [];
        $numero = 1;

        while ($inicio->lte($finRango)) {
            $finSemana = $inicio->copy()->addDays(6)->endOfDay();
            $dias = [];
            for ($i = 0; $i < 7; $i++) {
                $dias[] = $inicio->copy()->addDays($i);
            }

            $semanas[] = [
                'inicio' => $inicio->copy(),
                'fin' => $finSemana,
                'etiqueta' => 'S'.$numero,
                'fecha_cabecera' => self::fechaCabecera($inicio),
                'anio' => (int) $inicio->format('Y'),
                'dias' => $dias,
            ];
            $inicio->addDays(7);
            $numero++;
        }

        return $semanas;
    }

    /**
     * @param  list<array{inicio: Carbon, fin: Carbon, etiqueta: string, fecha_cabecera: string, anio: int}>  $semanas
     */
    public static function indiceSemana(CarbonInterface $fecha, array $semanas): ?int
    {
        $fecha = $fecha->copy()->startOfDay();

        foreach ($semanas as $indice => $semana) {
            if ($fecha->between(
                $semana['inicio']->copy()->startOfDay(),
                $semana['fin']->copy()->startOfDay()
            )) {
                return $indice;
            }
        }

        return null;
    }

    public static function fechaCabecera(CarbonInterface $fecha): string
    {
        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $mes = (int) $fecha->format('n');

        return $fecha->format('j').'-'.($meses[$mes] ?? $fecha->format('M'));
    }
}
