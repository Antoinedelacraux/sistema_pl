<?php

namespace App\Console\Commands;

use App\Models\Stock\MovimientoStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Antes: php database/57_vaciar_ingresos_consumos.php --confirm */
class VaciarIngresosConsumosCommand extends Command
{
    protected $signature = 'pl:vaciar-ingresos-consumos {--force : No pedir confirmación}';

    protected $description = 'DESTRUCTIVO: elimina todos los ingresos y consumos que no pertenecen a traslados (antes de cargar stock inicial).';

    public function handle(): int
    {
        $tipos = [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_EGRESO];

        $antes = MovimientoStock::query()->whereIn('tipo', $tipos)->whereNull('idTraslado')->count();

        if (! $this->option('force') && ! $this->confirm("Se eliminarán {$antes} ingreso(s)/consumo(s) (sin traslados). ¿Continuar?")) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $eliminados = 0;

        DB::transaction(function () use ($tipos, &$eliminados) {
            $eliminados = MovimientoStock::query()
                ->whereIn('tipo', $tipos)
                ->whereNull('idTraslado')
                ->delete();
        });

        $version = (int) Cache::get('registro.grid.cache_version', 0);
        Cache::forever('registro.grid.cache_version', $version + 1);
        Cache::forget('catalogo.almacenes.v2');
        Cache::forget('resumen.almacenes.v1');
        Cache::forget('resumen.variedades_mapa.v1');

        $this->info("Ingresos/consumos vaciados: {$eliminados} fila(s) eliminada(s) (había {$antes}).");
        $this->line('Traslados no modificados.');

        return self::SUCCESS;
    }
}
