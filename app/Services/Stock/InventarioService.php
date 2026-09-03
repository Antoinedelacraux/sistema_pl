<?php



namespace App\Services\Stock;



use App\Models\Stock\Almacen;

use App\Support\GridPagination;

use Illuminate\Http\Request;

use Illuminate\Pagination\LengthAwarePaginator;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;



class InventarioService

{

    public function almacenes(): Collection

    {

        return Almacen::query()->activo()->orderBy('orden')->get();

    }



    public function filasPaginadas(string $codigoAlmacen, array $filtros, Request $request): LengthAwarePaginator
    {
        $paginator = $this->queryFilas($codigoAlmacen, $filtros)
            ->orderBy('CodigoPL')
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();

        return $paginator->through(fn ($fila) => $this->enriquecerFila($fila));
    }

    public function filas(string $codigoAlmacen, array $filtros): Collection
    {
        return $this->queryFilas($codigoAlmacen, $filtros)
            ->orderBy('CodigoPL')
            ->get()
            ->map(fn ($fila) => $this->enriquecerFila($fila));
    }

    /**
     * @return array{total: int, con_stock: int, critico: int, alerta: int, saludable: int}
     */
    public function contadoresPorAlmacen(string $codigoAlmacen): array
    {
        $fila = DB::table('vw_StockAlmacenDetalle')
            ->where('codigoAlmacen', $codigoAlmacen)
            ->selectRaw('
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END), 0) AS con_stock,
                COALESCE(SUM(CASE WHEN stockMinimo > 0 AND stock <= 0 THEN 1 ELSE 0 END), 0) AS critico,
                COALESCE(SUM(CASE WHEN stockMinimo > 0 AND stock > 0 AND stock < stockMinimo THEN 1 ELSE 0 END), 0) AS alerta,
                COALESCE(SUM(CASE WHEN stockMinimo > 0 AND stock >= stockMinimo THEN 1 ELSE 0 END), 0) AS saludable
            ')
            ->first();

        return [
            'total' => (int) ($fila->total ?? 0),
            'con_stock' => (int) ($fila->con_stock ?? 0),
            'critico' => (int) ($fila->critico ?? 0),
            'alerta' => (int) ($fila->alerta ?? 0),
            'saludable' => (int) ($fila->saludable ?? 0),
        ];
    }



    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function aplicarFiltroEstado($query, string $estado): void

    {

        match ($estado) {

            'critico' => $query->where('stockMinimo', '>', 0)->where('stock', '<=', 0),

            'alerta' => $query->where('stockMinimo', '>', 0)->where('stock', '>', 0)->whereColumn('stock', '<', 'stockMinimo'),

            'saludable' => $query->where('stockMinimo', '>', 0)->whereColumn('stock', '>=', 'stockMinimo'),

            'con_stock' => $query->where('stock', '>', 0),

            default => null,

        };

    }



    private function estadoStock(float $stock, float $stockMinimo): string

    {

        if ($stockMinimo <= 0) {

            return $stock > 0 ? 'saludable' : 'sin_config';

        }



        if ($stock <= 0) {

            return 'critico';

        }



        if ($stock < $stockMinimo) {

            return 'alerta';

        }



        return 'saludable';
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function queryFilas(string $codigoAlmacen, array $filtros)
    {
        $query = DB::table('vw_StockAlmacenDetalle')
            ->where('codigoAlmacen', $codigoAlmacen);

        if (! empty($filtros['buscar'])) {
            $termino = '%'.$filtros['buscar'].'%';
            $query->where(function ($consulta) use ($termino) {
                $consulta->where('CodigoPL', 'like', $termino)
                    ->orWhere('nomMaterial', 'like', $termino);
            });
        }

        $this->aplicarFiltroEstado($query, $filtros['estado'] ?? 'con_stock');

        return $query;
    }

    private function enriquecerFila(object $fila): object
    {
        $stock = (float) $fila->stock;
        $stockMinimo = (float) $fila->stockMinimo;

        $fila->stock = $stock;
        $fila->stockMinimo = $stockMinimo;
        $fila->estadoStock = $this->estadoStock($stock, $stockMinimo);

        return $fila;
    }
}

