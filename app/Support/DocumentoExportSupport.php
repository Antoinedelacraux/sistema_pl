<?php

namespace App\Support;

use App\Models\Usuario;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoExportSupport
{
    public const TABLA_INICIO_FILA = 2;

    public const TABLA_INICIO_COLUMNA = 2;

    /**
     * @param  list<string>  $encabezados
     * @param  list<array<int, string|int|float|null>>  $filas
     * @param  list<int>  $indicesColumnaTexto
     * @param  list<int>  $indicesFilaNegrita
     */
    public static function exportarExcel(
        array $encabezados,
        array $filas,
        ?Usuario $usuario,
        string $tituloHoja,
        string $prefijoArchivo,
        string $nombreTabla,
        array $indicesColumnaTexto = [],
        array $indicesFilaNegrita = []
    ): StreamedResponse {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $exportadoPor = self::nombreExportador($usuario);
        $generado = now()->format('d/m/Y H:i');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($tituloHoja);
        $sheet->setCellValue('A1', 'Exportado por: '.$exportadoPor.' · Generado: '.$generado);

        $filaEncabezados = self::TABLA_INICIO_FILA;
        foreach ($encabezados as $indice => $encabezado) {
            $columna = Coordinate::stringFromColumnIndex(self::TABLA_INICIO_COLUMNA + $indice);
            $sheet->setCellValue($columna.$filaEncabezados, $encabezado);
        }

        $filaDatos = $filaEncabezados + 1;
        $ultimaColumnaIndice = self::TABLA_INICIO_COLUMNA + count($encabezados) - 1;

        foreach ($filas as $indiceFila => $fila) {
            $esNegrita = in_array($indiceFila, $indicesFilaNegrita, true);

            foreach ($fila as $indice => $valor) {
                $columna = Coordinate::stringFromColumnIndex(self::TABLA_INICIO_COLUMNA + $indice);
                $celda = $columna.$filaDatos;

                if (in_array($indice, $indicesColumnaTexto, true)) {
                    $sheet->setCellValueExplicit($celda, (string) ($valor ?? ''), DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($celda, $valor);
                }
            }

            if ($esNegrita) {
                $columnaInicio = Coordinate::stringFromColumnIndex(self::TABLA_INICIO_COLUMNA);
                $columnaFin = Coordinate::stringFromColumnIndex($ultimaColumnaIndice);
                $sheet->getStyle($columnaInicio.$filaDatos.':'.$columnaFin.$filaDatos)
                    ->getFont()
                    ->setBold(true);
            }

            $filaDatos++;
        }

        $ultimaFila = max($filaEncabezados, $filaDatos - 1);
        $ultimaColumna = Coordinate::stringFromColumnIndex(self::TABLA_INICIO_COLUMNA + count($encabezados) - 1);
        $rangoTabla = Coordinate::stringFromColumnIndex(self::TABLA_INICIO_COLUMNA).$filaEncabezados.':'.$ultimaColumna.$ultimaFila;

        $tabla = new Table($rangoTabla, $nombreTabla);
        $estiloTabla = new TableStyle;
        $estiloTabla->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
        $estiloTabla->setShowRowStripes(true);
        $estiloTabla->setShowColumnStripes(false);
        $tabla->setStyle($estiloTabla);
        $sheet->addTable($tabla);

        foreach (range(self::TABLA_INICIO_COLUMNA, self::TABLA_INICIO_COLUMNA + count($encabezados) - 1) as $indiceColumna) {
            $columna = Coordinate::stringFromColumnIndex($indiceColumna);
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }

        $nombre = $prefijoArchivo.'-'.now()->format('Ymd-His').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function exportarPdf(string $vista, array $datos, string $prefijoArchivo): Response
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $html = view($vista, $datos)->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', self::directorioTemporalDompdf());
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $nombre = $prefijoArchivo.'-'.now()->format('Ymd-His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public static function nombreExportador(?Usuario $usuario): string
    {
        if (! $usuario) {
            return '—';
        }

        $usuario->loadMissing('persona');

        return $usuario->persona?->nombreCompleto() ?: $usuario->Usuario;
    }

    public static function directorioTemporalDompdf(): string
    {
        $directorio = storage_path('framework/cache/dompdf');

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        return $directorio;
    }
}
