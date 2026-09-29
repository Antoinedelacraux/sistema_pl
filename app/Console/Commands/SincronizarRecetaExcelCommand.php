<?php

namespace App\Console\Commands;

use App\Services\PlanProduccion\PlanProduccionGridService;
use App\Services\Programas\ProgramaGridService;
use App\Services\Programas\ProgramaImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/** Antes: php database/55_sincronizar_receta_desde_excel.php [ruta.xlsx] */
class SincronizarRecetaExcelCommand extends Command
{
    protected $signature = 'pl:sincronizar-receta-excel {archivo? : Ruta del Excel (por defecto "001 Receta v1 (3).xlsx" en la raíz)}';

    protected $description = 'Sincroniza la receta activa con el Excel (misma lógica que Importar en pantalla).';

    public function handle(ProgramaImportService $import, ProgramaGridService $programas, PlanProduccionGridService $plan): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('001 Receta v1 (3).xlsx'));
        if (! is_file($ruta)) {
            $this->error("Archivo no encontrado: {$ruta}");

            return self::FAILURE;
        }

        $resultado = $import->importar(new UploadedFile($ruta, basename($ruta), null, null, true));

        $programas->olvidarCaches();
        $plan->forgetCaches();

        $this->line((string) json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
