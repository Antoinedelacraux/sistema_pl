<?php

namespace App\Support;

use App\Models\Programas\MaterialProgramaPL;

final class MaterialProgramaLookup
{
    public static function porCodigo(string $codigo, bool $soloActivos = true): ?MaterialProgramaPL
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $query = MaterialProgramaPL::query()->where('CodigoPL', $codigo);

        if ($soloActivos) {
            $query->activo();
        }

        return $query->orderByDesc('idMaterialPL')->first();
    }
}
