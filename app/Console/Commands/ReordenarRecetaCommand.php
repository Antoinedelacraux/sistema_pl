<?php

namespace App\Console\Commands;

use App\Services\PlanProduccion\PlanProduccionGridService;
use App\Services\Programas\ProgramaGridService;
use App\Support\RecetaFilaOrdenSupport;
use Illuminate\Console\Command;

/** Antes: php database/56_reordenar_receta.php */
class ReordenarRecetaCommand extends Command
{
    protected $signature = 'pl:reordenar-receta';

    protected $description = 'Renumera nroFila de la receta activa (Box y alternativa primero por presentación, resto como Excel).';

    public function handle(ProgramaGridService $programas, PlanProduccionGridService $plan): int
    {
        $cambios = RecetaFilaOrdenSupport::renumerarFilasActivas();

        $programas->olvidarCaches();
        $plan->forgetCaches();

        $this->info("Filas con nroFila actualizado: {$cambios}");

        return self::SUCCESS;
    }
}
