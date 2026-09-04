<?php

namespace App\Support;

final class UsoObservacionSupport
{
    public static function clave(?string $nomUso): string
    {
        $uso = mb_strtolower(trim((string) $nomUso));
        $uso = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $uso);

        if (str_starts_with($uso, 'comun')) {
            return 'comun';
        }

        if (preg_match('/^alternativo(?:\s*(\d+))?/', $uso, $matches)) {
            return ! empty($matches[1]) ? 'alternativo '.$matches[1] : 'alternativo';
        }

        return $uso;
    }

    public static function claseCss(?string $nomUso): string
    {
        return match (self::clave($nomUso)) {
            'oficial' => 'obsr--oficial',
            'alternativo' => 'obsr--alternativo',
            'alternativo 2' => 'obsr--alternativo-2',
            'alternativo 3' => 'obsr--alternativo-3',
            'comun' => 'obsr--comun',
            default => '',
        };
    }

    public static function esFactorCaja(?string $nomUso): bool
    {
        $clave = self::clave($nomUso);

        return in_array($clave, ['comun', 'oficial'], true) || str_starts_with($clave, 'alternativo');
    }
}
