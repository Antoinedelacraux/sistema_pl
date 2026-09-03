<?php

namespace App\Services\Inicio;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Traslado;
use App\Services\Stock\InventarioService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InicioService
{
    public function __construct(
        private readonly InventarioService $inventarioService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'kpis' => $this->kpis(),
            'stockPorAlmacen' => $this->stockPorAlmacen(),
            'materialesCriticos' => $this->materialesCriticos(),
            'ultimosMovimientos' => $this->ultimosMovimientos(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function kpis(): array
    {
        $filas = DB::table('vw_StockAlmacenDetalle')->get();

        $critico = 0;
        $alerta = 0;
        $materialesConStock = $filas
            ->filter(fn ($fila) => (float) $fila->stock > 0)
            ->pluck('idMaterialPL')
            ->unique()
            ->count();

        foreach ($filas as $fila) {
            $estado = $this->estadoStock((float) $fila->stock, (float) $fila->stockMinimo);
            if ($estado === 'critico') {
                $critico++;
            } elseif ($estado === 'alerta') {
                $alerta++;
            }
        }

        $movimientosHoy = MovimientoStock::query()
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->whereDate('fecha', today())
            ->count()
            + Traslado::query()
                ->where('estado', 1)
                ->whereDate('fecha', today())
                ->count();

        return [
            'materiales_con_stock' => $materialesConStock,
            'materiales_total' => MaterialProgramaPL::query()->activo()->count(),
            'stock_critico' => $critico,
            'stock_alerta' => $alerta,
            'familias' => Familia::query()->activo()->count(),
            'movimientos_hoy' => $movimientosHoy,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function stockPorAlmacen(): Collection
    {
        return $this->inventarioService->almacenes()->map(function ($almacen) {
            $contadores = $this->inventarioService->contadoresPorAlmacen($almacen->codigo);

            return (object) [
                'codigo' => $almacen->codigo,
                'nombre' => $almacen->nomAlmacen,
                'con_stock' => $contadores['con_stock'],
                'critico' => $contadores['critico'],
                'alerta' => $contadores['alerta'],
                'saludable' => $contadores['saludable'],
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function materialesCriticos(int $limite = 8): Collection
    {
        return DB::table('vw_StockAlmacenDetalle')
            ->where('stockMinimo', '>', 0)
            ->orderBy('stock')
            ->get()
            ->map(function ($fila) {
                $stock = (float) $fila->stock;
                $stockMinimo = (float) $fila->stockMinimo;

                return (object) [
                    'codigo_pl' => $fila->CodigoPL,
                    'material' => $fila->nomMaterial,
                    'almacen' => $fila->codigoAlmacen,
                    'stock' => $stock,
                    'stock_minimo' => $stockMinimo,
                    'estado' => $this->estadoStock($stock, $stockMinimo),
                ];
            })
            ->filter(fn ($fila) => in_array($fila->estado, ['critico', 'alerta'], true))
            ->sortBy(fn ($fila) => ($fila->estado === 'critico' ? '0' : '1').sprintf('%015.2f', $fila->stock))
            ->take($limite)
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function ultimosMovimientos(int $limite = 8): Collection
    {
        $movimientos = MovimientoStock::query()
            ->with(['almacen', 'materialPl'])
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->orderByDesc('fechaRegistro')
            ->limit($limite)
            ->get()
            ->map(fn (MovimientoStock $mov) => (object) [
                'tipo' => $mov->tipo === MovimientoStock::TIPO_INGRESO ? 'Ingreso' : 'Consumo',
                'tipo_clase' => $mov->tipo,
                'fecha' => $mov->fecha,
                'fecha_registro' => $mov->fechaRegistro,
                'detalle' => $mov->almacen?->codigo,
                'codigo_pl' => $mov->materialPl?->CodigoPL,
                'material' => $mov->materialPl?->nomMaterial,
                'cantidad' => (float) $mov->cantidad,
                'url' => route('registro.index', ['tab' => $mov->tipo === MovimientoStock::TIPO_INGRESO ? 'ingresos' : 'consumos']),
            ]);

        $traslados = Traslado::query()
            ->with(['almacenOrigen', 'almacenDestino', 'materialPl'])
            ->where('estado', 1)
            ->orderByDesc('fechaRegistro')
            ->limit(4)
            ->get()
            ->map(fn (Traslado $traslado) => (object) [
                'tipo' => 'Traslado',
                'tipo_clase' => 'traslado',
                'fecha' => $traslado->fecha,
                'fecha_registro' => $traslado->fechaRegistro,
                'detalle' => ($traslado->almacenOrigen?->codigo ?? '—').' → '.($traslado->almacenDestino?->codigo ?? '—'),
                'codigo_pl' => $traslado->materialPl?->CodigoPL,
                'material' => $traslado->materialPl?->nomMaterial,
                'cantidad' => (float) $traslado->cantidad,
                'url' => route('registro.index', ['tab' => 'traslados']),
            ]);

        return $movimientos
            ->concat($traslados)
            ->sortByDesc(fn ($item) => $item->fecha_registro?->format('Y-m-d H:i:s') ?? '')
            ->take($limite)
            ->values();
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
