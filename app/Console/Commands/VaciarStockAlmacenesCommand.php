<?php

namespace App\Console\Commands;

use App\Models\Stock\Almacen;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Traslado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Antes: php database/31_vaciar_stock_almacenes.php */
class VaciarStockAlmacenesCommand extends Command
{
    protected $signature = 'pl:vaciar-stock-almacenes {--force : No pedir confirmación}';

    protected $description = 'DESTRUCTIVO: borra movimientos y traslados de los almacenes SFC (SAFCO), SF y FC.';

    public function handle(): int
    {
        $ids = Almacen::query()->whereIn('codigo', ['SFC', 'SAFCO', 'SF', 'FC'])->pluck('idAlmacen')->all();

        if ($ids === []) {
            $this->error('No se encontraron almacenes SFC/SF/FC.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Se borrarán movimientos y traslados de SFC/SF/FC. ¿Continuar?')) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $movimientos = 0;
        $traslados = 0;

        DB::transaction(function () use ($ids, &$movimientos, &$traslados) {
            $idsTraslado = Traslado::query()
                ->where(function ($query) use ($ids) {
                    $query->whereIn('idAlmacenOrigen', $ids)
                        ->orWhereIn('idAlmacenDestino', $ids);
                })
                ->pluck('idTraslado')
                ->all();

            if ($idsTraslado !== []) {
                $movimientos += MovimientoStock::query()->whereIn('idTraslado', $idsTraslado)->delete();
                $traslados += Traslado::query()->whereIn('idTraslado', $idsTraslado)->delete();
            }

            $movimientos += MovimientoStock::query()
                ->whereIn('idAlmacen', $ids)
                ->whereNull('idTraslado')
                ->delete();
        });

        Cache::forget('catalogo.almacenes.v2');

        $this->info("Stock vaciado en SFC/SF/FC: {$movimientos} movimiento(s), {$traslados} traslado(s) eliminados.");

        return self::SUCCESS;
    }
}
