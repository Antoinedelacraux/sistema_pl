<?php

namespace App\Services\PlanProduccion;

use App\Models\Estado;
use App\Models\Programas\PlanProduccion;
use App\Models\Programas\PlanProduccionMaterial;
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\SemProduccion;
use App\Support\SemProduccionSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanProduccionImportService
{
    public function __construct(
        private readonly PlanProduccionMrpSyncService $syncService,
    ) {}

    private const COL_PROGRAMA = 2;

    private const COL_CAL = 3;

    private const COL_INICIO_SEM = 4;

    private const FILA_SEMANAS = 7;

    /** @var array<string, int> */
    private array $cacheProgramaGeneral = [];

    /**
     * @return array{semanas: int, programas: int, celdas: int}
     */
    public function importar(UploadedFile $archivo): array
    {
        @ini_set('max_execution_time', '300');
        set_time_limit(300);

        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            throw new InvalidArgumentException('No se pudo leer el archivo Excel.');
        }

        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($ruta);
        $hoja = $spreadsheet->getSheetByName('Plan Ventas') ?? $spreadsheet->getActiveSheet();

        $anio = $this->leerAnio($hoja);
        $mapaSemanas = $this->leerSemanas($hoja, $anio);
        if ($mapaSemanas === []) {
            throw new InvalidArgumentException('No se encontraron semanas de producción en el Excel (fila Sem. Produccion).');
        }

        $filasPrograma = $this->leerFilasPrograma($hoja, $mapaSemanas);
        if ($filasPrograma === []) {
            throw new InvalidArgumentException('No se encontraron programas generales (filas blancas) en el Excel.');
        }

        DB::transaction(function () use ($mapaSemanas, $filasPrograma) {
            SemProduccion::query()->delete();
            PlanProduccion::query()->delete();

            foreach ($mapaSemanas as $orden => $semana) {
                SemProduccion::query()->create([
                    'etiqueta' => $semana['etiqueta'],
                    'codigoSemana' => $semana['codigoSemana'],
                    'fechaInicio' => $semana['fechaInicio'],
                    'nroOrden' => $orden + 1,
                    'Estado' => Estado::ACTIVO,
                ]);
            }

            $semanasPorOrden = SemProduccion::query()
                ->orderBy('nroOrden')
                ->get()
                ->keyBy('nroOrden');

            foreach ($filasPrograma as $fila) {
                $programaGeneral = $this->resolverProgramaGeneral($fila['nombre']);

                foreach ($fila['cantidades'] as $nroOrden => $cantidad) {
                    if ($cantidad == 0.0) {
                        continue;
                    }

                    $sem = $semanasPorOrden->get($nroOrden);
                    if (! $sem) {
                        continue;
                    }

                    PlanProduccion::query()->create([
                        'idProgramaGeneral' => $programaGeneral->idProgramaGeneral,
                        'idSemProduccion' => $sem->idSemProduccion,
                        'cantidad' => $cantidad,
                        'calificacion' => $fila['calificacion'],
                        'nroOrden' => $fila['nroOrden'],
                        'Estado' => Estado::ACTIVO,
                    ]);
                }
            }
        });

        $celdas = array_sum(array_map(
            static fn (array $fila) => count(array_filter($fila['cantidades'], static fn ($c) => $c != 0.0)),
            $filasPrograma
        ));

        $resultado = [
            'semanas' => count($mapaSemanas),
            'programas' => count($filasPrograma),
            'celdas' => $celdas,
            'mrp_sync' => null,
            'mrp_sync_error' => null,
        ];

        try {
            $resultado['mrp_sync'] = $this->syncService->sincronizarPlanConsumo();
        } catch (InvalidArgumentException $exception) {
            $resultado['mrp_sync_error'] = $exception->getMessage();
        }

        return $resultado;
    }

    public function vaciar(): void
    {
        DB::transaction(function () {
            PlanProduccionMaterial::query()->delete();
            PlanProduccion::query()->delete();
            SemProduccion::query()->delete();
        });
    }

    private function leerAnio(Worksheet $hoja): int
    {
        $titulo = trim((string) $hoja->getCell([self::COL_PROGRAMA, 2])->getCalculatedValue());
        if (preg_match('/(20\d{2})/', $titulo, $m)) {
            return (int) $m[1];
        }

        return (int) date('Y');
    }

    /**
     * @return list<array{col: int, codigoSemana: string, fechaInicio: string, etiqueta: string}>
     */
    private function leerSemanas(Worksheet $hoja, int $anio): array
    {
        $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
        $semanas = [];
        $orden = 0;

        for ($col = self::COL_INICIO_SEM; $col <= $ultimaCol; $col++) {
            $codigo = trim((string) $hoja->getCell([$col, self::FILA_SEMANAS])->getCalculatedValue());
            if ($codigo === '' || ! preg_match('/^S\d+$/i', $codigo)) {
                continue;
            }

            $codigo = strtoupper($codigo);
            $fechaInicio = SemProduccionSupport::fechaInicioDesdeCodigo($anio, $codigo);
            $orden++;

            $semanas[] = [
                'col' => $col,
                'codigoSemana' => $codigo,
                'fechaInicio' => $fechaInicio,
                'etiqueta' => SemProduccionSupport::etiqueta($fechaInicio, $codigo),
            ];
        }

        return $semanas;
    }

    /**
     * @param  list<array{col: int, codigoSemana: string, fechaInicio: string, etiqueta: string}>  $mapaSemanas
     * @return list<array{nombre: string, calificacion: string|null, nroOrden: int, cantidades: array<int, float>}>
     */
    private function leerFilasPrograma(Worksheet $hoja, array $mapaSemanas): array
    {
        $columnasPorOrden = [];
        foreach ($mapaSemanas as $i => $semana) {
            $columnasPorOrden[$i + 1] = $semana['col'];
        }

        $ultimaFila = (int) $hoja->getHighestRow();
        $filas = [];
        $nroOrden = 0;

        for ($fila = self::FILA_SEMANAS + 1; $fila <= $ultimaFila; $fila++) {
            $celdaNombre = $hoja->getCell([self::COL_PROGRAMA, $fila]);
            $nombre = trim((string) $celdaNombre->getCalculatedValue());

            if ($nombre === '' || ! $this->esFilaProgramaGeneral($celdaNombre, $nombre)) {
                continue;
            }

            $cal = trim((string) $hoja->getCell([self::COL_CAL, $fila])->getCalculatedValue());
            $calificacion = $cal !== '' ? $cal : null;

            $cantidades = [];
            foreach ($columnasPorOrden as $ordenSem => $col) {
                $raw = $hoja->getCell([$col, $fila])->getCalculatedValue();
                $cantidades[$ordenSem] = is_numeric($raw) ? (float) $raw : 0.0;
            }

            $nroOrden++;
            $filas[] = [
                'nombre' => $nombre,
                'calificacion' => $calificacion,
                'nroOrden' => $nroOrden,
                'cantidades' => $cantidades,
            ];
        }

        return $filas;
    }

    private function esFilaProgramaGeneral(Cell $celda, string $nombre): bool
    {
        if (! $this->esCeldaBlanca($celda)) {
            return false;
        }

        $normalizado = mb_strtolower($nombre);
        $excluir = [
            'cliente', 'sem. produccion', 'semana etd', 'plan ventas',
            '(en cajas)', 'eu', 'cal',
        ];

        foreach ($excluir as $texto) {
            if ($normalizado === $texto || str_starts_with($normalizado, 'plan ventas')) {
                return false;
            }
        }

        if (preg_match('/^\d+([.,]\d+)?$/', $nombre)) {
            return false;
        }

        return true;
    }

    private function esCeldaBlanca(Cell $celda): bool
    {
        $fill = $celda->getStyle()->getFill();
        if ($fill->getFillType() === Fill::FILL_NONE) {
            return true;
        }

        $rgb = strtoupper($fill->getStartColor()->getRGB());

        return in_array($rgb, ['FFFFFF', 'FFFFFFFF', '000000'], true);
    }

    private function resolverProgramaGeneral(string $nombre): ProgramaGeneral
    {
        $clave = mb_strtolower(trim($nombre));
        if (isset($this->cacheProgramaGeneral[$clave])) {
            return ProgramaGeneral::query()->findOrFail($this->cacheProgramaGeneral[$clave]);
        }

        $existente = ProgramaGeneral::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [$clave])
            ->first();

        if ($existente) {
            $this->cacheProgramaGeneral[$clave] = (int) $existente->idProgramaGeneral;

            return $existente;
        }

        $codigo = $this->generarCodigo($nombre);
        $creado = ProgramaGeneral::query()->create([
            'CodigoProGeneral' => $codigo,
            'nombre' => trim($nombre),
            'fecha' => now()->toDateString(),
            'Estado' => Estado::ACTIVO,
        ]);

        $this->cacheProgramaGeneral[$clave] = (int) $creado->idProgramaGeneral;

        return $creado;
    }

    private function generarCodigo(string $nombre): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $nombre) ?: 'PG', 0, 12));
        $codigo = $base;
        $n = 1;

        while (ProgramaGeneral::query()->where('CodigoProGeneral', $codigo)->exists()) {
            $codigo = $base.$n;
            $n++;
        }

        return $codigo;
    }
}
