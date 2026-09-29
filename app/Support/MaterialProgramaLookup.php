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
        $descripcion = trim($descripcion);

        if ($codigo === '' || $codigo === '-') {
            if ($descripcion === '') {
                throw new InvalidArgumentException('CodigoPL inválido.');
            }

            $porNombre = self::porNombreExacto($descripcion, false);
            if ($porNombre !== null) {
                return self::actualizarMaterialExistente($porNombre, $porNombre->CodigoPL, $descripcion, $reactivar, $actualizarNombre);
            }

            $codigoProvisional = self::codigoProvisionalPorDescripcion($descripcion);
            $existenteProvisional = MaterialProgramaPL::query()
                ->where('CodigoPL', $codigoProvisional)
                ->orderBy('idMaterialPL')
                ->first();

            if ($existenteProvisional !== null) {
                return self::actualizarMaterialExistente($existenteProvisional, $codigoProvisional, $descripcion, $reactivar, $actualizarNombre);
            }

            return MaterialProgramaPL::query()->create([
                'CodigoPL' => $codigoProvisional,
                'nomMaterial' => $descripcion,
                'fechaRegistro' => now()->toDateString(),
                'Estado' => Estado::ACTIVO,
            ]);
        }

        $material = MaterialProgramaPL::query()
            ->where('CodigoPL', $codigo)
            ->orderBy('idMaterialPL')
            ->first();

        $nomMaterial = $descripcion !== '' ? $descripcion : $codigo;

        if ($material) {
            return self::actualizarMaterialExistente($material, $codigo, $descripcion, $reactivar, $actualizarNombre);
        }

        return MaterialProgramaPL::query()->create([
            'CodigoPL' => $codigo,
            'nomMaterial' => $nomMaterial,
            'fechaRegistro' => now()->toDateString(),
            'Estado' => Estado::ACTIVO,
        ]);
    }

    public static function porNombreExacto(string $descripcion, bool $soloActivos = true): ?MaterialProgramaPL
    {
        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            return null;
        }

        $query = MaterialProgramaPL::query()
            ->whereRaw('LOWER(TRIM(nomMaterial)) = ?', [mb_strtolower($descripcion)]);

        if ($soloActivos) {
            $query->activo();
        }

        return $query->orderBy('idMaterialPL')->first();
    }

    /** CodigoPL único para materiales sin Idproducto en Excel (uk_material_codigo_pl). */
    public static function codigoProvisionalPorDescripcion(string $descripcion): string
    {
        $clave = mb_strtolower(trim($descripcion));

        return 'ND'.strtoupper(substr(hash('sha256', $clave), 0, 10));
    }

    private static function actualizarMaterialExistente(
        MaterialProgramaPL $material,
        string $codigo,
        string $descripcion,
        bool $reactivar,
        bool $actualizarNombre,
    ): MaterialProgramaPL {
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
}
