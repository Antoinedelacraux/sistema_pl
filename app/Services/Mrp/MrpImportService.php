<?php

namespace App\Services\Mrp;

use App\Models\Estado;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\PlanConsumo;
use App\Models\Stock\PlanConsumoOrden;
use App\Models\Stock\SemConsumo;
use App\Support\MaterialProgramaLookup;
use App\Support\PlanConsumoOrdenSupport;
use App\Support\SemConsumoSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MrpImportService
{
    private const FILA_FECHAS = 4;

    private const FILA_CODIGOS_SEM = 5;

    private const FILA_DATOS = 6;

    private const COL_INICIO_SEM = 4;

    /**
     * @return array{semanas: int, filas_plan: int}
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
        $hoja = $spreadsheet->getSheetByName('MRP') ?? $spreadsheet->getActiveSheet();

        $mapaSemanas = $this->leerSemanas($hoja);
        if ($mapaSemanas === []) {
            throw new InvalidArgumentException('No se encontraron semanas en el Excel.');
        }

        $filasPlan = $this->leerFilasPlan($hoja, $mapaSemanas);

        DB::transaction(function () use ($mapaSemanas, $filasPlan) {
            SemConsumo::query()->delete();
            PlanConsumo::query()->delete();

            $idsOrden = [];

            foreach ($mapaSemanas as $orden => $semana) {
                SemConsumo::query()->create([
                    'etiqueta' => $semana['etiqueta'],
                    'codigoSemana' => $semana['codigoSemana'],
                    'fechaInicio' => $semana['fechaInicio'],
                    'nroOrden' => $orden + 1,
                    'Estado' => Estado::ACTIVO,
                ]);
            }

            $semanasPorColumna = SemConsumo::query()
                ->orderBy('nroOrden')
                ->get()
                ->keyBy('nroOrden');

            foreach ($filasPlan as $fila) {
                $material = MaterialProgramaLookup::resolverPorCodigo(
                    $fila['codigo'],
                    $fila['descripcion'],
                    reactivar: true,
                    actualizarNombre: true
                );
                $idsOrden[] = (int) $material->idMaterialPL;

                foreach ($fila['cantidades'] as $nroOrden => $cantidad) {
                    if ($cantidad == 0.0) {
                        continue;
                    }

                    $sem = $semanasPorColumna->get($nroOrden);
                    if (! $sem) {
                        continue;
                    }

                    PlanConsumo::query()->create([
                        'idMaterialPL' => $material->idMaterialPL,
                        'idSemConsumo' => $sem->idSemConsumo,
                        'cantidad' => $cantidad,
                        'Estado' => Estado::ACTIVO,
                    ]);
                }
            }

            PlanConsumoOrdenSupport::asegurarOrden(array_values(array_unique($idsOrden)));
        });

        return [
            'semanas' => count($mapaSemanas),
            'filas_plan' => count($filasPlan),
        ];
    }

    public function vaciarPlan(): void
    {
        DB::transaction(function () {
            PlanConsumo::query()->delete();
            PlanConsumoOrden::query()->delete();
            SemConsumo::query()->delete();
            PlanConsumoOrdenSupport::olvidarCache();
        });
    }

    /**
     * @return list<array{col: int, codigoSemana: string, fechaInicio: string, etiqueta: string}>
     */
    private function leerSemanas(Worksheet $hoja): array
    {
        $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
        $semanas = [];

        for ($indice = self::COL_INICIO_SEM; $indice <= $ultimaCol; $indice++) {
            $col = Coordinate::stringFromColumnIndex($indice);
            $codigoSemana = trim((string) $hoja->getCell($col.self::FILA_CODIGOS_SEM)->getCalculatedValue());
            if ($codigoSemana === '' || ! preg_match('/^S\d+$/i', $codigoSemana)) {
                continue;
            }

            $serial = $hoja->getCell($col.self::FILA_FECHAS)->getCalculatedValue();
            if (! is_numeric($serial)) {
                continue;
            }

            $fechaInicio = Date::excelToDateTimeObject((float) $serial)->format('Y-m-d');
            $codigoSemana = strtoupper($codigoSemana);

            $semanas[] = [
                'col' => $indice,
                'codigoSemana' => $codigoSemana,
                'fechaInicio' => $fechaInicio,
                'etiqueta' => SemConsumoSupport::etiqueta($fechaInicio, $codigoSemana),
            ];
        }

        return $semanas;
    }

    /**
     * @param  list<array{col: int, codigoSemana: string, fechaInicio: string, etiqueta: string}>  $mapaSemanas
     * @return list<array{codigo: string, descripcion: string, cantidades: array<int, float>}>
     */
    private function leerFilasPlan(Worksheet $hoja, array $mapaSemanas): array
    {
        $filas = [];
        $ultimaFila = $hoja->getHighestDataRow();
        $ordenPorCol = [];
        foreach ($mapaSemanas as $i => $sem) {
            $ordenPorCol[$sem['col']] = $i + 1;
        }

        for ($numeroFila = self::FILA_DATOS; $numeroFila <= $ultimaFila; $numeroFila++) {
            $codigo = MaterialProgramaLookup::normalizarCodigo(
                trim((string) $hoja->getCell('B'.$numeroFila)->getCalculatedValue())
            );
            if ($codigo === '' || $codigo === '-') {
                continue;
            }

            $descripcion = trim((string) $hoja->getCell('C'.$numeroFila)->getCalculatedValue());
            $cantidades = [];

            foreach ($mapaSemanas as $sem) {
                $col = Coordinate::stringFromColumnIndex($sem['col']);
                $valor = $hoja->getCell($col.$numeroFila)->getCalculatedValue();
                $cantidad = is_numeric($valor) ? (float) $valor : 0.0;
                $nroOrden = $ordenPorCol[$sem['col']] ?? null;
                if ($nroOrden !== null) {
                    $cantidades[$nroOrden] = $cantidad;
                }
            }

            $filas[] = [
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'cantidades' => $cantidades,
            ];
        }

        if ($filas === []) {
            throw new InvalidArgumentException('El Excel no tiene filas de plan de consumo.');
        }

        return $filas;
    }
}
