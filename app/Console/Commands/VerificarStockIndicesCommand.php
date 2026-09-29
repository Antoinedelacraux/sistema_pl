<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Antes: php database/50_stock_vista_e_indices.php */
class VerificarStockIndicesCommand extends Command
{
    protected $signature = 'pl:verificar-stock-indices';

    protected $description = 'Verifica las vistas de stock (vw_*) y crea los índices faltantes en MovimientoStock.';

    /** @var array<string, string> */
    private const INDICES = [
        'idx_movimiento_stock_calc' => 'CREATE INDEX idx_movimiento_stock_calc ON MovimientoStock (idAlmacen, idMaterialPL, estado, tipo)',
        'idx_movimiento_tipo_estado_traslado_fecha' => 'CREATE INDEX idx_movimiento_tipo_estado_traslado_fecha ON MovimientoStock (tipo, estado, idTraslado, fecha, idMovimiento)',
        'idx_movimiento_consumo_receta' => 'CREATE INDEX idx_movimiento_consumo_receta ON MovimientoStock (tipo, idPrograma, idPresentacion, idMaterialPL, estado)',
    ];

    public function handle(): int
    {
        foreach (['vw_StockAlmacen', 'vw_StockAlmacenDetalle', 'vw_StockMaterial'] as $vista) {
            // Compara sin distinguir mayúsculas: en Windows (lower_case_table_names=1) se guardan en minúscula.
            $ok = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = LOWER(?)',
                [$vista]
            );
            $existe = ((int) ($ok->c ?? 0)) > 0;
            $existe ? $this->line("OK vista {$vista}") : $this->warn("FALTA vista {$vista}");
        }

        if (! Schema::hasTable('MovimientoStock')) {
            $this->error('FALTA tabla MovimientoStock');

            return self::FAILURE;
        }

        foreach (self::INDICES as $nombre => $sql) {
            if (DB::select('SHOW INDEX FROM MovimientoStock WHERE Key_name = ?', [$nombre]) !== []) {
                $this->line("OK índice {$nombre}");

                continue;
            }

            DB::statement($sql);
            $this->info("CREADO índice {$nombre}");
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
