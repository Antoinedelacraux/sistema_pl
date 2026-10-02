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

    /**
     * Igual que resolverPorCodigo() cuando el material con ese CodigoPL ya se cargó (p. ej. precarga en lote):
     * aplica las mismas actualizaciones (reactivar / completar nombre) sin volver a consultarlo.
     */
    public static function resolverPrecargado(
        MaterialProgramaPL $material,
        string $codigo,
        string $descripcion = '',
        bool $reactivar = true,
        bool $actualizarNombre = false
    ): MaterialProgramaPL {
        return self::actualizarMaterialExistente(
            $material,
            self::normalizarCodigo($codigo),
            trim($descripcion),
            $reactivar,
            $actualizarNombre,
            false
        );
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

    /**
     * @return array{materiales: list<array{id_material_pl: int, codigo_pl: string, nom_material: string}>, hay_mas: bool}
     */
    public static function buscar(string $termino, int $limite = 50, int $tope = 200): array
    {
        $termino = trim($termino);
        $limite = max(1, min($limite, max(1, $tope)));

        if ($termino !== '' && mb_strlen($termino) < 2) {
            return ['materiales' => [], 'hay_mas' => false];
        }

        $query = MaterialProgramaPL::query()
            ->activo()
            ->orderBy('CodigoPL');

        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $query->where(function ($q) use ($like) {
                $q->where('CodigoPL', 'like', $like)
                    ->orWhere('nomMaterial', 'like', $like);
            });
        }

        $filas = $query
            ->limit($limite + 1)
            ->get(['idMaterialPL', 'CodigoPL', 'nomMaterial']);

        return [
            'materiales' => $filas->take($limite)->map(fn (MaterialProgramaPL $material) => [
                'id_material_pl' => (int) $material->idMaterialPL,
                'codigo_pl' => (string) $material->CodigoPL,
                'nom_material' => (string) $material->nomMaterial,
            ])->all(),
            'hay_mas' => $filas->count() > $limite,
        ];
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
        bool $refrescar = true,
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

        return $refrescar ? $material->fresh() : $material;
    }
}
