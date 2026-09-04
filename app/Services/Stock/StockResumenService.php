<?php



namespace App\Services\Stock;



use App\Models\Programas\MaterialProgramaPL;

use App\Models\Stock\Almacen;

use App\Support\GridPagination;

use Illuminate\Http\Request;

use Illuminate\Pagination\LengthAwarePaginator;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;



class StockResumenService

{

    public function catalogos(): array

    {

        return [

            'almacenes' => Almacen::query()->activo()->orderBy('codigo')->get(),

        ];

    }



    public function filasResumenPaginadas(array $filtros, Request $request): LengthAwarePaginator

    {

        return GridPagination::paginateCollection(

            $this->filasResumen($filtros),

            $request

        );

    }



    public function filasResumen(array $filtros = []): Collection

    {

        $almacenes = Almacen::query()->activo()->orderBy('codigo')->get();

        $materiales = MaterialProgramaPL::query()->activo()->orderBy('CodigoPL')->get();



        $stocksRaw = DB::table('vw_StockAlmacen')->get();

        $stocksPorMaterial = $stocksRaw->groupBy('idMaterialPL');



        $minimos = DB::table('MaterialStockConfig')

            ->where('estado', 1)

            ->pluck('stockMinimo', 'idMaterialPL');



        $filas = $materiales->map(function (MaterialProgramaPL $material) use ($almacenes, $stocksPorMaterial, $minimos) {

            $filasAlmacen = $stocksPorMaterial->get($material->idMaterialPL, collect());

            $stocks = [];

            $total = 0.0;



            foreach ($almacenes as $almacen) {

                $valor = (float) ($filasAlmacen->firstWhere('idAlmacen', $almacen->idAlmacen)?->stock ?? 0);

                $stocks[$almacen->codigo] = $valor;

                $total += $valor;

            }



            $stockMinimo = (float) ($minimos[$material->idMaterialPL] ?? 0);



            return (object) [

                'material' => $material,

                'stocks' => $stocks,

                'total' => $total,

                'stockMinimo' => $stockMinimo,

                'estadoStock' => $this->estadoStock($total, $stockMinimo),

            ];

        });



        return $this->aplicarFiltros($filas, $filtros);

    }



    public function contadores(): array

    {

        $filas = $this->filasResumen();



        return [

            'total' => $filas->count(),

            'critico' => $filas->where('estadoStock', 'critico')->count(),

            'alerta' => $filas->where('estadoStock', 'alerta')->count(),

            'saludable' => $filas->where('estadoStock', 'saludable')->count(),

        ];

    }



    /**

     * @param  Collection<int, object>  $filas

     * @return Collection<int, object>

     */

    private function aplicarFiltros(Collection $filas, array $filtros): Collection

    {

        if (! empty($filtros['buscar'])) {

            $termino = mb_strtolower(trim($filtros['buscar']));



            $filas = $filas->filter(function ($fila) use ($termino) {

                $codigo = mb_strtolower($fila->material->CodigoPL ?? '');

                $material = mb_strtolower($fila->material->nomMaterial ?? '');



                return str_contains($codigo, $termino) || str_contains($material, $termino);

            });

        }



        if (! empty($filtros['estado'])) {

            $filas = $filas->where('estadoStock', $filtros['estado']);

        }



        if (! empty($filtros['almacen'])) {

            $codigoAlmacen = strtoupper((string) $filtros['almacen']);



            $filas = $filas->filter(function ($fila) use ($codigoAlmacen) {

                return ($fila->stocks[$codigoAlmacen] ?? 0) > 0;

            });

        }



        return $filas->values();

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

}

