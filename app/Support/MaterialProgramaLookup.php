<?php

namespace App\Support;

use App\Models\Estado;
use App\Models\Programas\MaterialProgramaPL;
use InvalidArgumentException;

final class MaterialProgramaLookup
{
    public static function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '—') {
            return '-';
        }

        if (preg_match('/^\d+(\.\d+)?([eE][+\-]?\d+)?$/', $codigo)) {
            return sprintf('%.0f', (float) $codigo);
        }

        return $codigo;
    }

    public static function porCodigo(string $codigo, bool $soloActivos = true): ?MaterialProgramaPL
    {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '' || $codigo === '-') {
            return null;
        }

        $query = MaterialProgramaPL::query()->where('CodigoPL', $codigo);

        if ($soloActivos) {
            $query->activo();
        }

        return $query->orderBy('idMaterialPL')->first();
    }

    /** Reutiliza material existente por CodigoPL; nunca crea duplicado. */
    public static function resolverPorCodigo(
        string $codigo,
        string $descripcion = '',
        bool $reactivar = true,
        bool $actualizarNombre = false
    ): MaterialProgramaPL {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '' || $codigo === '-') {
            throw new InvalidArgumentException('CodigoPL inválido.');
        }

        $material = MaterialProgramaPL::query()
            ->where('CodigoPL', $codigo)
            ->orderBy('idMaterialPL')
            ->first();

        $nomMaterial = trim($descripcion) !== '' ? trim($descripcion) : $codigo;

        if ($material) {
            $updates = [];
            $nomMaterial = trim($descripcion) !== '' ? trim($descripcion) : $codigo;

            if ($actualizarNombre && $nomMaterial !== $codigo && $nomMaterial !== $material->nomMaterial) {
                $updates['nomMaterial'] = $nomMaterial;
            } elseif (! $actualizarNombre && $nomMaterial !== $codigo && ($material->nomMaterial === '' || $material->nomMaterial === $material->CodigoPL)) {
                $updates['nomMaterial'] = $nomMaterial;
            }

            if ($reactivar && ! $material->estaActivo()) {
                $updates['Estado'] = Estado::ACTIVO;
            }

            if ($updates !== []) {
                $material->update($updates);
            }

            return $material->fresh();
        }

        return MaterialProgramaPL::query()->create([
            'CodigoPL' => $codigo,
            'nomMaterial' => $nomMaterial,
            'fechaRegistro' => now()->toDateString(),
            'Estado' => Estado::ACTIVO,
        ]);
    }
}
