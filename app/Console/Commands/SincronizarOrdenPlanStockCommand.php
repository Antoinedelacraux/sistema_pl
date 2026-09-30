<?php

namespace App\Console\Commands;

use App\Services\Mrp\MrpGridService;
use App\Support\PlanConsumoOrdenSupport;
use Illuminate\Console\Command;

/** Orden del plan MRP = filas de Stock.xlsx (sin tocar stock). */
class SincronizarOrdenPlanStockCommand extends Command
{
    protected $signature = 'pl:sincronizar-orden-plan-stock
        {archivo? : Ruta de Stock.xlsx (por defecto Stock.xlsx en la raíz)}';

    protected $description = 'Actualiza planConsumoOrden con el orden de filas de Stock.xlsx (Plan MRP).';

    public function handle(MrpGridService $mrpGrid): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('Stock.xlsx'));
        if (! is_file($ruta)) {
            $this->error("No existe: {$ruta}");

            return self::FAILURE;
        }

        $n = PlanConsumoOrdenSupport::sincronizarOrdenDesdeStockExcel($ruta);
        if ($n === 0) {
            $this->warn('No se encontraron materiales en el Excel.');

            return self::FAILURE;
        }

        $mrpGrid->forgetCaches();
        $this->info("Orden plan MRP actualizado desde ".basename($ruta)." ({$n} materiales).");

        return self::SUCCESS;
    }
}
