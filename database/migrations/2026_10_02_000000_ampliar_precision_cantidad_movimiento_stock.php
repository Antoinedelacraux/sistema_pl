<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MovimientoStock.cantidad: DECIMAL(12,2) -> DECIMAL(18,6).
 *
 * La receta puede dar cantidades menores a 0.01 (p. ej. 0.0012 de termógrafo por caja). Con 2 decimales
 * se guardaban como 0.00 y el CHECK chk_movimiento_cantidad_positiva rechazaba el INSERT.
 * En BD se guarda la cantidad exacta; el redondeo a 2 decimales es solo visual.
 *
 * - Parte entera: 12 dígitos (antes 10), no se reduce.
 * - Se conserva NOT NULL y el CHECK (cantidad > 0).
 * - Las vistas vw_StockAlmacen / vw_StockMaterial / vw_StockAlmacenDetalle usan SUM(cantidad) sin CAST,
 *   así que heredan la nueva escala sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE MovimientoStock MODIFY cantidad DECIMAL(18,6) NOT NULL');

        $this->asegurarCheckCantidadPositiva();
    }

    /**
     * Vuelve a DECIMAL(12,2). Ojo: redondea las cantidades con más de 2 decimales y fallará por el CHECK
     * si existen movimientos con cantidad < 0.005 (quedarían en 0.00); en ese caso hay que corregirlos antes.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE MovimientoStock MODIFY cantidad DECIMAL(12,2) NOT NULL');

        $this->asegurarCheckCantidadPositiva();
    }

    private function asegurarCheckCantidadPositiva(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $existe = DB::selectOne(
            "SELECT COUNT(*) AS total
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND LOWER(TABLE_NAME) = 'movimientostock'
                AND CONSTRAINT_NAME = 'chk_movimiento_cantidad_positiva'
                AND CONSTRAINT_TYPE = 'CHECK'"
        );

        if ((int) ($existe->total ?? 0) === 0) {
            DB::statement('ALTER TABLE MovimientoStock ADD CONSTRAINT chk_movimiento_cantidad_positiva CHECK (cantidad > 0)');
        }
    }
};
