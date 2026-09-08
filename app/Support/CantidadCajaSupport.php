<?php

namespace App\Support;

final class CantidadCajaSupport
{
    public const DECIMALES_ALTA = 4;

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
            'temperature recorder',
        ], true);
    }

    public static function redondear(float $valor, ?string $nomFamilia): float
    {
        return (float) self::formatear($valor, $nomFamilia);
    }

    public static function decimales(?string $nomFamilia): int
    {
        return self::usaDecimalesAlta($nomFamilia) ? self::DECIMALES_ALTA : self::DECIMALES_NORMAL;
    }

    public static function normalizarValor(string|float|null $valor, ?string $nomFamilia): float
    {
        $texto = str_replace(',', '.', trim((string) $valor));
        if ($texto === '' || ! is_numeric($texto)) {
            return 0.0;
        }

        return (float) number_format((float) $texto, self::decimales($nomFamilia), '.', '');
    }

    public static function clave(float $valor, ?string $nomFamilia): string
    {
        return number_format($valor, self::decimales($nomFamilia), '.', '');
    }

    public static function formatear(?float $valor, ?string $nomFamilia): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format($valor, self::decimales($nomFamilia), '.', '');
    }
}
