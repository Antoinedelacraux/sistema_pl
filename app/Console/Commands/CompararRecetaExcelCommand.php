<?php

namespace App\Console\Commands;

use App\Models\Programas\ProgramaFila;
use App\Services\Programas\ProgramaImportService;
use App\Support\CantidadCajaSupport;
use App\Support\MaterialProgramaLookup;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use ReflectionClass;

/** Antes: php database/54_comparar_receta_excel.php [ruta.xlsx] (solo lectura) */
class CompararRecetaExcelCommand extends Command
{
    protected $signature = 'pl:comparar-receta-excel {archivo? : Ruta del Excel (por defecto "001 Receta v1 (3).xlsx" en la raíz)}';

    protected $description = 'Compara la receta activa en BD con el Excel de referencia (no modifica datos). Código de salida 2 si hay diferencias de estructura.';

    public function handle(ProgramaImportService $import): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('001 Receta v1 (3).xlsx'));
        if (! is_file($ruta)) {
            $this->error("Archivo no encontrado: {$ruta}");

            return self::FAILURE;
        }

        $upload = new UploadedFile($ruta, basename($ruta), null, null, true);
        $ref = new ReflectionClass($import);
        $leer = $ref->getMethod('leerExcel');
        $leer->setAccessible(true);
        $keyExcel = $ref->getMethod('matchKeyDesdeExcel');
        $keyExcel->setAccessible(true);
        $keyFila = $ref->getMethod('matchKeyDesdeFila');
        $keyFila->setAccessible(true);
        $codigoValido = $ref->getMethod('codigoPlanificacionValido');
        $codigoValido->setAccessible(true);

        $filasExcel = $leer->invoke($import, $upload);

        $bd = ProgramaFila::query()
            ->activo()
            ->with([
                'mercado', 'cliente', 'programa', 'variedad', 'presentacion', 'material',
                'uso', 'familia', 'programaGeneral', 'cantidadCaja',
            ])
            ->get();

        $keysBd = [];
        foreach ($bd as $fila) {
            $k = $keyFila->invoke($import, $fila);
            $keysBd[$k][] = $fila;
        }

        $keysExcel = [];
        $dupExcel = 0;
        $omitExcel = 0;
        $cantidadDiff = [];
        $pgDiff = [];
        $sinMatchExcel = [];
        $sinMatchBd = array_fill_keys(array_keys($keysBd), true);

        foreach ($filasExcel as $row) {
            if (! $codigoValido->invoke($import, $row['Codigo Planificación'])) {
                $omitExcel++;

                continue;
            }
            $k = $keyExcel->invoke($import, $row);
            if (isset($keysExcel[$k])) {
                $dupExcel++;

                continue;
            }
            $keysExcel[$k] = $row;

            if (! isset($keysBd[$k])) {
                $sinMatchExcel[] = [
                    'codigo' => MaterialProgramaLookup::normalizarCodigo($row['Codigo Planificación']),
                    'programa' => $row['Programa'],
                    'producto' => $row['Presentación'],
                ];

                continue;
            }
            unset($sinMatchBd[$k]);

            $filaBd = $keysBd[$k][0];
            $nomFamilia = $filaBd->familia?->nomFamilia ?? '';
            $claveExcel = CantidadCajaSupport::claveCantidad($row['Cantidad x Caja'] ?? '', $nomFamilia);
            $claveBd = CantidadCajaSupport::claveCantidad(
                $filaBd->cantidadCaja?->cantidad !== null ? (float) $filaBd->cantidadCaja->cantidad : '',
                $nomFamilia
            );
            if ($claveExcel !== '' && $claveBd !== '' && $claveExcel !== $claveBd) {
                $cantidadDiff[] = [
                    'codigo' => $filaBd->material?->CodigoPL,
                    'excel' => $row['Cantidad x Caja'],
                    'bd' => CantidadCajaSupport::formatear(
                        $filaBd->cantidadCaja?->cantidad !== null ? (float) $filaBd->cantidadCaja->cantidad : null,
                        $nomFamilia
                    ),
                ];
            }

            $pgExcel = trim($row['Programa General'] ?? '');
            $pgBd = trim($filaBd->programaGeneral?->nombre ?? '');
            if ($pgExcel !== '' && mb_strtolower($pgExcel) !== mb_strtolower($pgBd)) {
                $pgDiff[] = [
                    'codigo' => $filaBd->material?->CodigoPL,
                    'excel' => $pgExcel,
                    'bd' => $pgBd !== '' ? $pgBd : '(vacío)',
                ];
            }
        }

        $extraBd = [];
        foreach (array_keys($sinMatchBd) as $k) {
            $f = $keysBd[$k][0];
            $extraBd[] = [
                'codigo' => $f->material?->CodigoPL,
                'programa' => $f->programa?->nomPrograma,
                'producto' => $f->presentacion?->nomPresentacion,
            ];
        }

        $this->line("Excel: {$ruta}");
        $this->line('Filas leídas Excel: '.count($filasExcel));
        $this->line("Filas BD activas: {$bd->count()}");
        $this->line("Claves únicas Excel (válidas): ".count($keysExcel));
        $this->line("Duplicadas en Excel: {$dupExcel}");
        $this->line("Omitidas Excel (código inválido): {$omitExcel}");
        $this->line('Solo en Excel (no en BD): '.count($sinMatchExcel));
        $this->line('Solo en BD (no en Excel): '.count($extraBd));
        $this->line('Cantidad x caja distinta: '.count($cantidadDiff));
        $this->line('Programa General distinto: '.count($pgDiff));
        $this->newLine();

        $mostrar = function (array $items, int $max = 15): void {
            foreach (array_slice($items, 0, $max) as $item) {
                $this->line('  - '.json_encode($item, JSON_UNESCAPED_UNICODE));
            }
            if (count($items) > $max) {
                $this->line('  ... y '.(count($items) - $max).' más');
            }
        };

        if ($sinMatchExcel !== []) {
            $this->line("Ejemplos solo en Excel:");
            $mostrar($sinMatchExcel);
            $this->newLine();
        }
        if ($extraBd !== []) {
            $this->line("Ejemplos solo en BD:");
            $mostrar($extraBd);
            $this->newLine();
        }
        if ($cantidadDiff !== []) {
            $this->line("Ejemplos cantidad distinta:");
            $mostrar($cantidadDiff);
            $this->newLine();
        }
        if ($pgDiff !== []) {
            $this->line("Ejemplos Programa General distinto:");
            $mostrar($pgDiff);
            $this->newLine();
        }

        $estructuraOk = count($sinMatchExcel) === 0
            && count($extraBd) === 0
            && count($pgDiff) === 0
            && $dupExcel === 0
            && $bd->count() === count($keysExcel);

        if ($estructuraOk && count($cantidadDiff) === 0) {
            $this->line("OK: Receta en BD coincide con el Excel (442 claves, cantidades y PG).");
            return self::SUCCESS;
        }

        if ($estructuraOk) {
            $this->line("OK estructura: {$bd->count()} filas activas = Excel (claves y PG).");
            $this->line("Cantidades x caja distintas al Excel (conservadas en BD): ".count($cantidadDiff));
            return self::SUCCESS;
        }

        return 2;
    }
}
