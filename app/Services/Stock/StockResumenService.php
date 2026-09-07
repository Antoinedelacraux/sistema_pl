<?php

namespace App\Services\Stock;

use App\Models\Estado;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\ProgramaFila;
use App\Models\Stock\Almacen;
use App\Support\GridPagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockResumenService
{
    public function __construct(
        private readonly ConsumoRecetaService $consumoRecetaService
    ) {}

    /**
     * @param  array{programa?: int|null, producto?: int|null}  $filtros
     */
    public function catalogos(array $filtros = []): array
    {
        $idPrograma = (int) ($filtros['programa'] ?? 0);

        return [
            'almacenes' => Almacen::query()->activo()->orderBy('codigo')->get(),
            'programas' => $this->consumoRecetaService->programasDisponibles(),
            'productos' => $idPrograma > 0
                ? $this->consumoRecetaService->presentacionesDePrograma($idPrograma)
                : collect(),
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

        $variedadesPorMaterial = $this->mapaVariedadesPorMaterial($filtros);

        $idPrograma = (int) ($filtros['programa'] ?? 0);
        $idProducto = (int) ($filtros['producto'] ?? 0);
        $cajasSimulacion = (float) ($filtros['cajas'] ?? 0);
        $simulaCajas = $idPrograma > 0 && $idProducto > 0 && $cajasSimulacion > 0;

        $necesidades = $simulaCajas
            ? $this->consumoRecetaService->proyectarNecesidadesPorCajas($idPrograma, $idProducto, $cajasSimulacion)
            : [];

        $filas = $materiales->map(function (MaterialProgramaPL $material) use (
            $almacenes,
            $stocksPorMaterial,
            $minimos,
            $variedadesPorMaterial,
            $necesidades,
            $simulaCajas
        ) {
            $filasAlmacen = $stocksPorMaterial->get($material->idMaterialPL, collect());
            $stocks = [];
            $total = 0.0;

            foreach ($almacenes as $almacen) {
                $valor = (float) ($filasAlmacen->firstWhere('idAlmacen', $almacen->idAlmacen)?->stock ?? 0);
                $stocks[$almacen->codigo] = $valor;
                $total += $valor;
            }

            $stockMinimo = (float) ($minimos[$material->idMaterialPL] ?? 0);
            $necesario = (float) ($necesidades[$material->idMaterialPL] ?? 0);

            return (object) [
                'material' => $material,
                'variedad' => $variedadesPorMaterial[$material->idMaterialPL] ?? '',
                'stocks' => $stocks,
                'total' => $total,
                'stockMinimo' => $stockMinimo,
                'estadoStock' => $this->estadoStock($total, $stockMinimo),
                'simulaCajas' => $simulaCajas,
                'necesario' => $necesario,
                'alcanza' => ! $simulaCajas || $necesario <= 0 || $total >= $necesario,
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
                $variedad = mb_strtolower($fila->variedad ?? '');

                return str_contains($codigo, $termino)
                    || str_contains($material, $termino)
                    || str_contains($variedad, $termino);
            });
        }

        if (! empty($filtros['estado'])) {
            $filas = $filas->where('estadoStock', $filtros['estado']);
        }

        $idPrograma = (int) ($filtros['programa'] ?? 0);
        if ($idPrograma > 0) {
            $idProducto = (int) ($filtros['producto'] ?? 0);
            $idsMaterial = $this->idsMaterialesReceta(
                $idPrograma,
                $idProducto > 0 ? $idProducto : null
            );

            $filas = $filas->filter(
                fn ($fila) => in_array($fila->material->idMaterialPL, $idsMaterial, true)
            );
        }

        return $filas->values();
    }

    /**
     * @return list<int>
     */
    private function idsMaterialesReceta(int $idPrograma, ?int $idProducto = null): array
    {
        $query = ProgramaFila::query()
            ->where('idPrograma', $idPrograma)
            ->where('Estado', Estado::ACTIVO)
            ->whereNotNull('idMaterialPL');

        if ($idProducto !== null && $idProducto > 0) {
            $query->where('idPresentacion', $idProducto);
        }

        return $query->pluck('idMaterialPL')->unique()->values()->all();
    }

    /**
     * @param  array{programa?: int|null, producto?: int|null}  $filtros
     * @return array<int, string>
     */
    private function mapaVariedadesPorMaterial(array $filtros): array
    {
        $query = ProgramaFila::query()
            ->where('Estado', Estado::ACTIVO)
            ->whereNotNull('idMaterialPL')
            ->with('variedad:idVariedad,nomVariedad');

        $idPrograma = (int) ($filtros['programa'] ?? 0);
        if ($idPrograma > 0) {
            $query->where('idPrograma', $idPrograma);
        }

        $idProducto = (int) ($filtros['producto'] ?? 0);
        if ($idProducto > 0) {
            $query->where('idPresentacion', $idProducto);
        }

        $mapa = [];

        foreach ($query->get(['idMaterialPL', 'idVariedad']) as $fila) {
            $nombre = trim((string) ($fila->variedad?->nomVariedad ?? ''));
            if ($nombre === '') {
                continue;
            }

            $idMaterial = (int) $fila->idMaterialPL;
            $mapa[$idMaterial] ??= [];
            $mapa[$idMaterial][$nombre] = true;
        }

        foreach ($mapa as $idMaterial => $nombres) {
            $lista = array_keys($nombres);
            sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
            $mapa[$idMaterial] = implode(', ', $lista);
        }

        return $mapa;
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
