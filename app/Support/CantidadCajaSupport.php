<?php

namespace App\Support;

final class CantidadCajaSupport
{
    public const DECIMALES_ALTA = 4;

    public const DECIMALES_TEMPERATURE_RECORDER = 7;

    public const DECIMALES_NORMAL = 2;

    public static function usaDecimalesAlta(?string $nomFamilia): bool
    {
        return in_array(FamiliaMaterialSupport::normalizar($nomFamilia), [
            'carton',
            'cartons',
            'cartones',
            'pallet',
            'pallets',
            'parihuela',
            'parihuelas',
        ], true);
    }

    /** @return list<string> */
    private static function familiasCantidadExacta(): array
    {
        return [
            'temperature recorder',
            'others',
        ];
    }

    public static function usaCantidadExacta(?string $nomFamilia): bool
    {
        return in_array(FamiliaMaterialSupport::normalizar($nomFamilia), self::familiasCantidadExacta(), true);
    }

    public static function usaDecimalesTemperatureRecorder(?string $nomFamilia): bool
    {
        return self::usaCantidadExacta($nomFamilia);
    }

    public static function redondear(float $valor, ?string $nomFamilia): float
    {
        return (float) self::formatear($valor, $nomFamilia);
    }

    public static function decimales(?string $nomFamilia): int
    {
        if (self::usaCantidadExacta($nomFamilia)) {
            return self::DECIMALES_TEMPERATURE_RECORDER;
        }

        return self::usaDecimalesAlta($nomFamilia) ? self::DECIMALES_ALTA : self::DECIMALES_NORMAL;
    }

    /** Decimales al mostrar el factor × cantidad x caja en plan. */
    public static function decimalesVisualizacionFactor(?string $nomFamilia): int
    {
        if (self::usaCantidadExacta($nomFamilia)) {
            return self::DECIMALES_ALTA;
        }

        return self::decimales($nomFamilia);
    }

    /** Celdas plan ventas/consumo: enteros sin decimales (solo visual). */
    public static function decimalesVisualizacionCelda(?string $nomFamilia): int
    {
        return 0;
    }

    public static function decimalesVisualizacion(?string $nomFamilia): int
    {
        return self::decimalesVisualizacionCelda($nomFamilia);
    }

    public static function redondearVisualizacion(float $valor, ?string $nomFamilia): float
    {
        return round($valor, 0);
    }

    public static function formatearVisualizacionFactor(?float $valor, ?string $nomFamilia): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format($valor, self::decimalesVisualizacionFactor($nomFamilia), '.', '');
    }

    public static function formatearVisualizacionCelda(?float $valor, ?string $nomFamilia): string
    {
        if ($valor === null || round((float) $valor, 0) == 0.0) {
            return '—';
        }

        return number_format(round((float) $valor, 0), 0, '.', ',');
    }

    public static function formatearVisualizacion(?float $valor, ?string $nomFamilia): string
    {
        return self::formatearVisualizacionCelda($valor, $nomFamilia);
    }

    /** Registro (solo visual): trunca a 2 dec.; BD guarda precisión de familia. */
    public static function truncarVisualRegistro(float $valor, int $decVisual = self::DECIMALES_NORMAL): float
    {
        if ($decVisual <= 0) {
            return (float) (int) $valor;
        }

        $factor = 10 ** $decVisual;

        if ($valor >= 0) {
            return floor($valor * $factor + 1e-12) / $factor;
        }

        return ceil($valor * $factor - 1e-12) / $factor;
    }

    public static function formatearRegistroVisual(?float $valor, int $decVisual = self::DECIMALES_NORMAL): string
    {
        if ($valor === null) {
            return '—';
        }

        return self::formatearCantidadFlexible(self::truncarVisualRegistro((float) $valor, $decVisual), $decVisual);
    }

    /** MRP consumo/ingreso/stock: enteros sin .00; decimales solo si aplica (p. ej. 205.21). */
    public static function formatearCantidadFlexible(?float $valor, int $maxDecimales = self::DECIMALES_NORMAL): string
    {
        if ($valor === null) {
            return '—';
        }

        $num = round((float) $valor, $maxDecimales);
        if ($num == 0.0) {
            return '—';
        }

        if (abs($num - round($num, 0)) < 1e-9) {
            return number_format((int) round($num), 0, '.', ',');
        }

        $formatted = number_format($num, $maxDecimales, '.', ',');
        $pos = strrpos($formatted, '.');
        if ($pos === false) {
            return $formatted;
        }

        $entero = substr($formatted, 0, $pos);
        $frac = rtrim(substr($formatted, $pos + 1), '0');

        return $frac === '' ? $entero : $entero.'.'.$frac;
    }

    public static function normalizarValor(string|float|null $valor, ?string $nomFamilia): float
    {
        return (float) self::claveCantidad($valor, $nomFamilia);
    }

    public static function clave(float $valor, ?string $nomFamilia): string
    {
        return self::claveCantidad((string) $valor, $nomFamilia);
    }

    /** Clave para guardar cantidad x caja en CantidadCaja. */
    public static function claveCantidad(string|float|null $valor, ?string $nomFamilia): string
    {
        if (self::usaCantidadExacta($nomFamilia)) {
            return self::claveCantidadExacta($valor);
        }

        $texto = str_replace(',', '.', trim((string) $valor));
        $decimales = self::decimales($nomFamilia);

        if ($texto === '' || ! is_numeric($texto)) {
            return number_format(0, $decimales, '.', '');
        }

        return number_format((float) $texto, $decimales, '.', '');
    }

    /** Conserva el valor ingresado (sin redondear), hasta 7 decimales. */
    private static function claveCantidadExacta(string|float|null $valor): string
    {
        $texto = str_replace(',', '.', trim((string) $valor));
        $decimales = self::DECIMALES_TEMPERATURE_RECORDER;

        if ($texto === '' || ! is_numeric($texto)) {
            return number_format(0, $decimales, '.', '');
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $texto)) {
            return number_format((float) $texto, $decimales, '.', '');
        }

        $sign = str_starts_with($texto, '-') ? '-' : '';
        $texto = ltrim($texto, '-');
        [$entero, $frac] = array_pad(explode('.', $texto, 2), 2, '');
        $frac = substr($frac, 0, $decimales);
        $frac = str_pad($frac, $decimales, '0');

        return $sign.$entero.'.'.$frac;
    }

    public static function formatear(?float $valor, ?string $nomFamilia): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format($valor, self::decimales($nomFamilia), '.', '');
    }
}
