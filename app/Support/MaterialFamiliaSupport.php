<?php

namespace App\Support;

use App\Models\Programas\ProgramaFila;
use App\Models\Proveedores\Proveedor;
use Illuminate\Support\Collection;

class MaterialFamiliaSupport
{
    /**
     * Materiales vinculados a una familia vía Receta (ProgramaFila) o Proveedor.
     *
     * @param  list<int>|null  $limitarIdsMaterial
     * @return list<int>
     */
    public static function idsMaterialPorFamilia(int $idFamilia, ?array $limitarIdsMaterial = null): array
    {
        if ($idFamilia <= 0) {
            return [];
        }

        $desdeReceta = ProgramaFila::query()
            ->where('idFamilia', $idFamilia)
            ->distinct()
            ->pluck('idMaterialPL');

        $desdeProveedor = Proveedor::query()
            ->where('idFamilia', $idFamilia)
            ->where('estado', 1)
            ->distinct()
            ->pluck('idMaterialPL');

        $ids = $desdeReceta
            ->merge($desdeProveedor)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($limitarIdsMaterial !== null) {
            $limite = collect($limitarIdsMaterial)->map(fn ($id) => (int) $id);
            $ids = $ids->intersect($limite)->values();
        }

        return $ids->all();
    }

    /**
     * @param  Collection<int, int|string>|list<int>  $idsMaterial
     * @return list<int>
     */
    public static function idsFamiliaDeMateriales(Collection|array $idsMaterial): array
    {
        $idsMaterial = collect($idsMaterial)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($idsMaterial->isEmpty()) {
            return [];
        }

        $desdeReceta = ProgramaFila::query()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->whereNotNull('idFamilia')
            ->distinct()
            ->pluck('idFamilia');

        $desdeProveedor = Proveedor::query()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->where('estado', 1)
            ->whereNotNull('idFamilia')
            ->distinct()
            ->pluck('idFamilia');

        return $desdeReceta
            ->merge($desdeProveedor)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
