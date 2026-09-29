<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Antes: php database/35_deduplicar_material_programa_pl.php */
class DeduplicarMaterialesCommand extends Command
{
    protected $signature = 'pl:deduplicar-materiales {--force : No pedir confirmación}';

    protected $description = 'Fusiona MaterialProgramaPL duplicados por CodigoPL (conserva el id más bajo) y asegura el índice único.';

    /** @var array<string, string> */
    private const REFERENCIAS = [
        'ProgramaFila' => 'idMaterialPL',
        'MovimientoStock' => 'idMaterialPL',
        'Traslado' => 'idMaterialPL',
        'Proveedor' => 'idMaterialPL',
    ];

    public function handle(): int
    {
        $grupos = DB::table('MaterialProgramaPL')
            ->select('CodigoPL', DB::raw('COUNT(*) as total'))
            ->groupBy('CodigoPL')
            ->having('total', '>', 1)
            ->orderBy('CodigoPL')
            ->get();

        if ($grupos->isEmpty()) {
            $this->info('Sin duplicados por CodigoPL.');
            $this->asegurarIndiceUnico();

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Se fusionarán {$grupos->count()} grupo(s) de materiales duplicados. ¿Continuar?")) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $fusionados = 0;
        $eliminados = 0;

        DB::transaction(function () use ($grupos, &$fusionados, &$eliminados) {
            foreach ($grupos as $grupo) {
                $ids = DB::table('MaterialProgramaPL')
                    ->where('CodigoPL', $grupo->CodigoPL)
                    ->orderBy('idMaterialPL')
                    ->pluck('idMaterialPL')
                    ->all();

                $keeper = (int) $ids[0];
                $duplicados = array_slice($ids, 1);

                if ($duplicados === []) {
                    continue;
                }

                foreach ($duplicados as $dupId) {
                    $dupId = (int) $dupId;

                    foreach (self::REFERENCIAS as $tabla => $columna) {
                        DB::table($tabla)->where($columna, $dupId)->update([$columna => $keeper]);
                    }

                    $cfgDup = DB::table('MaterialStockConfig')->where('idMaterialPL', $dupId)->first();
                    if ($cfgDup) {
                        $cfgKeeper = DB::table('MaterialStockConfig')->where('idMaterialPL', $keeper)->exists();
                        if ($cfgKeeper) {
                            DB::table('MaterialStockConfig')->where('idMaterialPL', $dupId)->delete();
                        } else {
                            DB::table('MaterialStockConfig')->where('idMaterialPL', $dupId)->update(['idMaterialPL' => $keeper]);
                        }
                    }

                    DB::table('MaterialProgramaPL')->where('idMaterialPL', $dupId)->delete();
                    $eliminados++;
                }

                $fusionados++;
            }
        });

        $restantes = DB::table('MaterialProgramaPL')
            ->select('CodigoPL', DB::raw('COUNT(*) as total'))
            ->groupBy('CodigoPL')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $this->line("Grupos fusionados: {$fusionados}");
        $this->line("Registros eliminados: {$eliminados}");
        $this->line("Grupos duplicados restantes: {$restantes}");
        $this->line('Total materiales: '.DB::table('MaterialProgramaPL')->count());

        $this->asegurarIndiceUnico();

        return self::SUCCESS;
    }

    private function asegurarIndiceUnico(): void
    {
        $indices = DB::select("SHOW INDEX FROM MaterialProgramaPL WHERE Key_name = 'uk_material_codigo_pl'");
        if ($indices === []) {
            DB::statement('ALTER TABLE MaterialProgramaPL ADD UNIQUE KEY uk_material_codigo_pl (CodigoPL)');
            $this->info('Índice UNIQUE uk_material_codigo_pl creado.');
        } else {
            $this->line('Índice UNIQUE uk_material_codigo_pl ya existía.');
        }
    }
}
