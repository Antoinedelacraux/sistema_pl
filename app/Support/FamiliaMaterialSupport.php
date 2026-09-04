<?php

namespace App\Support;

final class FamiliaMaterialSupport
{
    public static function normalizar(?string $nomFamilia): string
    {
        $nombre = mb_strtolower(trim((string) $nomFamilia));

        return str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nombre);
    }

    public static function esCajas(?string $nomFamilia): bool
    {
        return in_array(self::normalizar($nomFamilia), ['cajas', 'caja', 'box'], true);
    }

    public static function usaPallets(?string $nomFamilia): bool
    {
        return in_array(self::normalizar($nomFamilia), [
            'pallet',
            'pallets',
            'tapas',
            'parihuelas',
            'termografos',
            'temperature recorder',
        ], true);
    }
}
