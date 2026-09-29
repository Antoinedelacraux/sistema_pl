<?php

namespace App\Console\Commands;

use App\Services\PlanProduccion\PlanProduccionGridService;
use App\Services\Programas\ProgramaGridService;
use App\Services\Programas\ProgramaImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/** Antes: php database/47_reparar_pg_receta_excel.php [ruta.xlsx] */
class RepararProgramaGeneralRecetaCommand extends Command
{
    protected $signature = 'pl:reparar-programa-general-receta {archivo? : Ruta del Excel Materiales V2 (por defecto "001 Receta v1 (3).xlsx")}';

    protected $description = 'Repara idProgramaGeneral de la receta leyendo las columnas M/N del Excel.';

    public function handle(ProgramaImportService $import, ProgramaGridService $programas, PlanProduccionGridService $plan): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('001 Receta v1 (3).xlsx'));
        if (! is_file($ruta)) {
            $this->error("Archivo no encontrado: {$ruta}");

            return self::FAILURE;
        }

        $resultado = $import->repararProgramaGeneralDesdeExcel(new UploadedFile($ruta, basename($ruta), null, null, true));

        $programas->olvidarCaches();
        $plan->forgetCaches();

        $this->info(sprintf(
            'Reparación PG: %d fila(s) leídas en Excel, %d actualizada(s), %d sin match en BD.',
            $resultado['leidas'],
            $resultado['actualizadas'],
            $resultado['sin_match']
        ));

        return self::SUCCESS;
    }
}
