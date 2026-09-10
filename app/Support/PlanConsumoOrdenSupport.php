<?php

namespace App\Support;

use App\Models\Stock\PlanConsumoOrden;
use Illuminate\Support\Facades\DB;

class PlanConsumoOrdenSupport
{
    /** @var array<int, int>|null */
    private static ?array $mapaCache = null;

    /**
     * @return array<int, int> idMaterialPL => nroOrden
     */
    public static function mapaOrden(): array
    {
        if (self::$mapaCache !== null) {
            return self::$mapaCache;
        }

        $mapa = [];
        PlanConsumoOrden::query()
            ->orderBy('nroOrden')
            ->lazy(500)
            ->each(function (PlanConsumoOrden $fila) use (&$mapa) {
                $mapa[(int) $fila->idMaterialPL] = (int) $fila->nroOrden;
            });

        self::$mapaCache = $mapa;

        return $mapa;
    }

    public static function olvidarCache(): void
    {
        self::$mapaCache = null;
    }

    /**
     * Reemplaza el orden completo (importación Excel MRP).
     *
     * @param  list<int>  $idsMaterialEnOrden
     */
    public static function reemplazarOrden(array $idsMaterialEnOrden): void
    {
        DB::transaction(function () use ($idsMaterialEnOrden) {
            PlanConsumoOrden::query()->delete();

            $filas = [];
            foreach ($idsMaterialEnOrden as $i => $idMaterial) {
                $idMaterial = (int) $idMaterial;
                if ($idMaterial <= 0) {
                    continue;
                }

                $filas[] = [
                    'idMaterialPL' => $idMaterial,
                    'nroOrden' => $i + 1,
                ];
            }

            foreach (array_chunk($filas, 500) as $lote) {
                PlanConsumoOrden::query()->insert($lote);
            }
        });

        self::olvidarCache();
    }

    /**
     * Asigna nroOrden a materiales nuevos (al final, sin alterar los existentes).
     *
     * @param  list<int>  $idsMaterial
     */
    public static function asegurarOrden(array $idsMaterial): void
    {
        $idsMaterial = array_values(array_unique(array_filter(array_map('intval', $idsMaterial), fn (int $id) => $id > 0)));
        if ($idsMaterial === []) {
            return;
        }

        $existentes = PlanConsumoOrden::query()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->pluck('idMaterialPL')
            ->map(fn ($id) => (int) $id)
            ->all();

        $faltantes = array_values(array_diff($idsMaterial, $existentes));
        if ($faltantes === []) {
            return;
        }

        $maxOrden = (int) PlanConsumoOrden::query()->max('nroOrden');
        $filas = [];
        foreach ($faltantes as $idMaterial) {
            $maxOrden++;
            $filas[] = [
                'idMaterialPL' => $idMaterial,
                'nroOrden' => $maxOrden,
            ];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            PlanConsumoOrden::query()->insert($lote);
        }

        self::olvidarCache();
    }

    /**
     * @param  array{idMaterialPL: int, codigoPL?: string}|object{idMaterialPL: int, codigoPL?: string}  $a
     * @param  array{idMaterialPL: int, codigoPL?: string}|object{idMaterialPL: int, codigoPL?: string}  $b
     */
    public static function compararFilas(array|object $a, array|object $b, ?array $mapa = null): int
    {
        $mapa ??= self::mapaOrden();

        $idA = (int) (is_array($a) ? $a['idMaterialPL'] : $a->idMaterialPL);
        $idB = (int) (is_array($b) ? $b['idMaterialPL'] : $b->idMaterialPL);

        $ordenA = $mapa[$idA] ?? PHP_INT_MAX;
        $ordenB = $mapa[$idB] ?? PHP_INT_MAX;

        if ($ordenA !== $ordenB) {
            return $ordenA <=> $ordenB;
        }

        $codigoA = is_array($a) ? (string) ($a['codigoPL'] ?? '') : (string) ($a->CodigoPL ?? $a->codigoPL ?? '');
        $codigoB = is_array($b) ? (string) ($b['codigoPL'] ?? '') : (string) ($b->CodigoPL ?? $b->codigoPL ?? '');

        return strcmp($codigoA, $codigoB);
    }
}
