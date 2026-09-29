<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline del esquema de system_pl (tablas, índices, FKs y vistas vw_*).
 *
 * - El DDL vive en database/schema/esquema_base.sql (nombres exactos que usa el código).
 * - Es idempotente: si la tabla Usuario ya existe (BD local o producción actuales) no hace nada.
 *   En esas BD basta con registrar la migración como aplicada (ver produccion_registrar_baseline.sql).
 * - Datos mínimos (roles, módulos, admin): php artisan db:seed
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Usuario')) {
            return;
        }

        $ruta = database_path('schema/esquema_base.sql');

        if (! is_file($ruta)) {
            throw new RuntimeException('No se encontró '.$ruta);
        }

        DB::unprepared((string) file_get_contents($ruta));
    }

    /**
     * Baseline: no se revierte automáticamente (borraría todos los datos).
     */
    public function down(): void
    {
        //
    }
};
