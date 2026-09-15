<?php

namespace App\Services\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Lectura de stock agregado vía vistas SQL (vw_StockAlmacen / vw_StockAlmacenDetalle).
 */
class StockAlmacenService
{
    private const VISTA_ALMACEN = 'vw_StockAlmacen';

    private const VISTA_DETALLE = 'vw_StockAlmacenDetalle';

    /**
     * @param  list<int>  $idsMaterialPL
     */
    public function sumaStockEnAlmacen(array $idsMaterialPL, int $idAlmacen): float
    {
        if ($idsMaterialPL === [] || $idAlmacen <= 0) {
            return 0.0;
        }

        return (float) DB::table(self::VISTA_ALMACEN)
            ->where('idAlmacen', $idAlmacen)
            ->whereIn('idMaterialPL', $idsMaterialPL)
            ->sum('stock');
    }

    /**
     * @param  list<int>  $idsMaterialPL
     * @return array<int, float>
     */
    public function mapaStockEnAlmacen(array $idsMaterialPL, int $idAlmacen): array
    {
        if ($idsMaterialPL === [] || $idAlmacen <= 0) {
            return [];
        }

        $mapa = [];
        foreach (
            DB::table(self::VISTA_ALMACEN)
                ->where('idAlmacen', $idAlmacen)
                ->whereIn('idMaterialPL', $idsMaterialPL)
                ->get(['idMaterialPL', 'stock']) as $fila
        ) {
            $mapa[(int) $fila->idMaterialPL] = (float) $fila->stock;
        }

        return $mapa;
    }

    /**
     * Stock sumado en todos los almacenes (p. ej. MRP consumo real).
     *
     * @param  list<int>  $idsMaterialPL
     * @return array<int, float>
     */
    public function mapaStockTotalPorMaterial(array $idsMaterialPL): array
    {
        if ($idsMaterialPL === []) {
            return [];
        }

        $mapa = [];
        DB::table(self::VISTA_DETALLE)
            ->selectRaw('idMaterialPL, COALESCE(SUM(stock), 0) AS stock_total')
            ->whereIn('idMaterialPL', $idsMaterialPL)
            ->groupBy('idMaterialPL')
            ->orderBy('idMaterialPL')
            ->lazy(500)
            ->each(function ($fila) use (&$mapa) {
                $mapa[(int) $fila->idMaterialPL] = (float) $fila->stock_total;
            });

        return $mapa;
    }
}
