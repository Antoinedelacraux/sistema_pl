<?php

namespace App\Console\Commands;

use App\Services\Programas\ProgramaGridService;
use Illuminate\Console\Command;

/** Antes: php database/30_vaciar_programa_fila.php */
class VaciarRecetaCommand extends Command
{
    protected $signature = 'pl:vaciar-receta {--force : No pedir confirmación}';

    protected $description = 'DESTRUCTIVO: vacía la grilla Receta (ProgramaFila + parámetros de pallets + imágenes). No toca catálogos ni proveedores.';

    public function handle(ProgramaGridService $programas): int
    {
        if (! $this->option('force') && ! $this->confirm('Se eliminarán TODAS las filas de la receta. ¿Continuar?')) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $resultado = $programas->vaciarReceta();

        $this->info("Receta vaciada: {$resultado['filas']} fila(s) y {$resultado['params']} param(s) pallets/conformación eliminados.");

        return self::SUCCESS;
    }
}
