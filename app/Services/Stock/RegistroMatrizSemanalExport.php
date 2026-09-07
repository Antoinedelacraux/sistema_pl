<?php

namespace App\Services\Stock;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\MovimientoStock;
use App\Services\Proveedores\ProveedorGridService;
use App\Support\DocumentoExportSupport;
use App\Support\SemanaRegistroSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistroMatrizSemanalExport
{
    private const UNIDAD_DEFECTO = 'UND';

    private const COL_INICIO = 1;

    public function __construct(
        private readonly ProveedorGridService $proveedorGridService
    ) {}

    public function exportar(string $tipo, array $filtros, ?\App\Models\Usuario $usuario = null): StreamedResponse
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $ancla = $this->fechaAncla($tipo, $filtros);
        $hasta = $this->fechaHasta($tipo, $filtros, $ancla);
        $semanas = SemanaRegistroSupport::semanasDesdeAncla($ancla, $hasta);
        $totalesDia = $this->totalesPorCodigoYDia($tipo, $filtros, $semanas);
        $materiales = $this->materialesOrdenados($filtros);
        $gruposSemana = $this->construirGruposSemana($semanas, self::COL_INICIO + 3);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($tipo === MovimientoStock::TIPO_EGRESO ? 'Consumos' : 'Ingresos');
        $sheet->setShowSummaryRight(true);

        $titulo = $tipo === MovimientoStock::TIPO_EGRESO
            ? 'Consumo de Materiales'
            : 'Ingreso de Materiales';

        $exportadoPor = DocumentoExportSupport::nombreExportador($usuario);

        $filaTitulo = 1;
        $filaMeta = 2;
        $filaSemana = 3;
        $filaDias = 4;
        $filaDatos = 5;
        $colBase = self::COL_INICIO;

        $ultimaColumna = $gruposSemana === []
            ? $colBase + 2
            : $gruposSemana[array_key_last($gruposSemana)]['total_col'];

        $sheet->setCellValue($this->celda($colBase, $filaTitulo), $titulo);
        $sheet->mergeCells(
            $this->celda($colBase, $filaTitulo).':'.$this->celda($ultimaColumna, $filaTitulo)
        );
        $sheet->getStyle($this->celda($colBase, $filaTitulo))->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle($this->celda($colBase, $filaTitulo))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue(
            $this->celda($colBase, $filaMeta),
            'Exportado por: '.$exportadoPor.' · Generado: '.now()->format('d/m/Y H:i')
        );
        $sheet->mergeCells(
            $this->celda($colBase, $filaMeta).':'.$this->celda($ultimaColumna, $filaMeta)
        );

        $sheet->setCellValue($this->celda($colBase, $filaSemana), 'CodigoPl');
        $sheet->setCellValue($this->celda($colBase + 1, $filaSemana), 'Materiales PL');
        $sheet->setCellValue($this->celda($colBase + 2, $filaSemana), 'Unidad');
        $sheet->mergeCells($this->celda($colBase, $filaSemana).':'.$this->celda($colBase, $filaDias));
        $sheet->mergeCells($this->celda($colBase + 1, $filaSemana).':'.$this->celda($colBase + 1, $filaDias));
        $sheet->mergeCells($this->celda($colBase + 2, $filaSemana).':'.$this->celda($colBase + 2, $filaDias));

        foreach ($gruposSemana as $grupo) {
            $colPrimerDia = $grupo['dias'][0]['col'];
            $colTotal = $grupo['total_col'];

            $sheet->mergeCells(
                $this->celda($colPrimerDia, $filaSemana).':'.$this->celda($colTotal, $filaSemana)
            );
            $sheet->setCellValue($this->celda($colPrimerDia, $filaSemana), $grupo['etiqueta']);

            foreach ($grupo['dias'] as $diaCol) {
                $sheet->setCellValue($this->celda($diaCol['col'], $filaDias), $diaCol['fecha_cabecera']);
            }

            $sheet->setCellValue($this->celda($colTotal, $filaDias), 'TOTAL');
        }

        $filaActual = $filaDatos;
        $familiaAnterior = null;

        foreach ($materiales as $material) {
            $familiaActual = trim((string) ($material->familia?->nomFamilia ?? ''));

            if ($familiaActual !== '' && $familiaActual !== $familiaAnterior) {
                $sheet->setCellValue($this->celda($colBase + 1, $filaActual), $familiaActual);
                $sheet->getStyle($this->celda($colBase + 1, $filaActual))
                    ->getFont()->getColor()->setARGB('FFFF0000');
                $filaActual++;
                $familiaAnterior = $familiaActual;
            }

            $codigo = trim((string) ($material->materialPl?->CodigoPL ?? ''));
            $this->escribirCodigoPl($sheet, $colBase, $filaActual, $codigo);
            $sheet->setCellValue($this->celda($colBase + 1, $filaActual), $material->materialPl?->nomMaterial ?? '');
            $sheet->setCellValue($this->celda($colBase + 2, $filaActual), self::UNIDAD_DEFECTO);

            foreach ($gruposSemana as $grupo) {
                $sumaSemana = 0.0;

                foreach ($grupo['dias'] as $diaCol) {
                    $cantidad = (float) ($totalesDia[$codigo][$diaCol['fecha_clave']] ?? 0);
                    $sumaSemana += $cantidad;
                    $sheet->setCellValue(
                        $this->celda($diaCol['col'], $filaActual),
                        $cantidad > 0 ? $this->formatearCantidad($cantidad) : '-'
                    );
                }

                $sheet->setCellValue(
                    $this->celda($grupo['total_col'], $filaActual),
                    $sumaSemana > 0 ? $this->formatearCantidad($sumaSemana) : '-'
                );
            }

            $filaActual++;
        }

        $ultimaFila = $filaActual - 1;

        $this->aplicarEstilos($sheet, $filaSemana, $filaDias, $filaDatos, $ultimaFila, $colBase, $gruposSemana);
        $this->aplicarAgrupacionColumnas($sheet, $gruposSemana);
        $this->aplicarFormatoCodigoPl($sheet, $colBase, $filaDatos, $ultimaFila);
        $this->aplicarAutoFiltro($sheet, $colBase, $filaDias, $ultimaColumna, $ultimaFila);

        foreach (range($colBase, $ultimaColumna) as $columna) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columna))->setAutoSize(true);
        }

        $prefijo = $tipo === MovimientoStock::TIPO_EGRESO ? 'registro-consumos' : 'registro-ingresos';
        $nombre = $prefijo.'-'.now()->format('Ymd-His').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array{inicio: Carbon, fin: Carbon, etiqueta: string, fecha_cabecera: string, anio: int, dias: list<Carbon>}>  $semanas
     * @return list<array{etiqueta: string, total_col: int, dias: list<array{col: int, fecha_clave: string, fecha_cabecera: string}>}>
     */
    private function construirGruposSemana(array $semanas, int $colInicio): array
    {
        $grupos = [];
        $col = $colInicio;

        foreach ($semanas as $semana) {
            $dias = [];

            foreach ($semana['dias'] as $dia) {
                $dias[] = [
                    'col' => $col,
                    'fecha_clave' => $dia->format('Y-m-d'),
                    'fecha_cabecera' => SemanaRegistroSupport::fechaCabecera($dia),
                ];
                $col++;
            }

            $grupos[] = [
                'etiqueta' => $semana['etiqueta'],
                'total_col' => $col,
                'dias' => $dias,
            ];
            $col++;
        }

        return $grupos;
    }

    /**
     * @param  list<array{etiqueta: string, total_col: int, dias: list<array{col: int, fecha_clave: string, fecha_cabecera: string}>}>  $gruposSemana
     */
    private function aplicarAgrupacionColumnas(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $gruposSemana): void
    {
        foreach ($gruposSemana as $grupo) {
            foreach ($grupo['dias'] as $diaCol) {
                $dimension = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($diaCol['col']));
                $dimension->setOutlineLevel(1);
                $dimension->setVisible(false);
                $dimension->setCollapsed(true);
            }
        }
    }

    private function fechaAncla(string $tipo, array $filtros): Carbon
    {
        $min = $this->queryMovimientos($tipo, $filtros)->min('fecha');

        return $min
            ? Carbon::parse($min)->startOfDay()
            : now()->startOfDay();
    }

    private function fechaHasta(string $tipo, array $filtros, Carbon $ancla): Carbon
    {
        $max = $this->queryMovimientos($tipo, $filtros)->max('fecha');
        $hasta = $max ? Carbon::parse($max)->startOfDay() : $ancla->copy();
        $hoy = now()->startOfDay();

        return $hasta->lt($hoy) ? $hoy : $hasta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<MovimientoStock>
     */
    private function queryMovimientos(string $tipo, array $filtros)
    {
        $query = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', $tipo)
            ->whereNull('idTraslado');

        if (! empty($filtros['id_almacen'])) {
            $query->where('idAlmacen', (int) $filtros['id_almacen']);
        }

        if (! empty($filtros['buscar'])) {
            $termino = '%'.$filtros['buscar'].'%';
            $query->where(function ($consulta) use ($termino) {
                $consulta->where('factura', 'like', $termino)
                    ->orWhere('guia', 'like', $termino)
                    ->orWhere('facturaGuia', 'like', $termino)
                    ->orWhereHas('materialPl', fn ($material) => $material->where('CodigoPL', 'like', $termino));
            });
        }

        return $query;
    }

    /**
     * @param  list<array{inicio: Carbon, fin: Carbon, etiqueta: string, fecha_cabecera: string, anio: int, dias: list<Carbon>}>  $semanas
     * @return array<string, array<string, float>>
     */
    private function totalesPorCodigoYDia(string $tipo, array $filtros, array $semanas): array
    {
        $query = $this->queryMovimientos($tipo, $filtros)->with('materialPl');

        if ($semanas !== []) {
            $query->whereBetween('fecha', [
                $semanas[0]['inicio']->toDateString(),
                $semanas[array_key_last($semanas)]['fin']->toDateString(),
            ]);
        }

        $totales = [];

        foreach ($query->get() as $movimiento) {
            $codigo = trim((string) ($movimiento->materialPl?->CodigoPL ?? ''));
            if ($codigo === '' || ! $movimiento->fecha) {
                continue;
            }

            $claveDia = $movimiento->fecha->format('Y-m-d');
            $totales[$codigo][$claveDia] = ($totales[$codigo][$claveDia] ?? 0) + (float) $movimiento->cantidad;
        }

        return $totales;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function materialesOrdenados(array $filtros)
    {
        $materiales = $this->proveedorGridService->filasGrilla([]);

        if (! empty($filtros['buscar'])) {
            $termino = mb_strtolower(trim((string) $filtros['buscar']));
            $materiales = $materiales->filter(function (object $fila) use ($termino) {
                $codigo = mb_strtolower((string) ($fila->materialPl?->CodigoPL ?? ''));
                $nombre = mb_strtolower((string) ($fila->materialPl?->nomMaterial ?? ''));

                return str_contains($codigo, $termino) || str_contains($nombre, $termino);
            })->values();
        }

        if ($materiales->isNotEmpty()) {
            return $materiales;
        }

        return MaterialProgramaPL::query()
            ->activo()
            ->orderBy('CodigoPL')
            ->get()
            ->map(fn (MaterialProgramaPL $material) => (object) [
                'nroFila' => (int) $material->idMaterialPL,
                'materialPl' => $material,
                'familia' => null,
            ]);
    }

    /**
     * @param  list<array{etiqueta: string, total_col: int, dias: list<array{col: int, fecha_clave: string, fecha_cabecera: string}>}>  $gruposSemana
     */
    private function aplicarEstilos(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $filaSemana,
        int $filaDias,
        int $filaDatos,
        int $ultimaFila,
        int $colBase,
        array $gruposSemana
    ): void {
        $verdeOscuro = 'FF1F6B45';
        $verdeClaro = 'FFE8F5E9';
        $grisDias = 'FF595959';

        $rangoBase = Coordinate::stringFromColumnIndex($colBase).$filaSemana.':'
            .Coordinate::stringFromColumnIndex($colBase + 2).$filaDias;

        $sheet->getStyle($rangoBase)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($verdeOscuro);
        $sheet->getStyle($rangoBase)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($rangoBase)->getFont()->setBold(true);
        $sheet->getStyle($rangoBase)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($rangoBase)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($gruposSemana as $grupo) {
            $colPrimerDia = $grupo['dias'][0]['col'];
            $colTotal = $grupo['total_col'];
            $rangoSemana = $this->celda($colPrimerDia, $filaSemana).':'.$this->celda($colTotal, $filaSemana);

            $sheet->getStyle($rangoSemana)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($verdeOscuro);
            $sheet->getStyle($rangoSemana)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($rangoSemana)->getFont()->setBold(true);
            $sheet->getStyle($rangoSemana)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($rangoSemana)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            foreach ($grupo['dias'] as $diaCol) {
                $celda = $this->celda($diaCol['col'], $filaDias);
                $sheet->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($grisDias);
                $sheet->getStyle($celda)->getFont()->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($celda)->getFont()->setBold(true);
                $sheet->getStyle($celda)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $celdaTotal = $this->celda($colTotal, $filaDias);
            $sheet->getStyle($celdaTotal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($verdeOscuro);
            $sheet->getStyle($celdaTotal)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($celdaTotal)->getFont()->setBold(true);
            $sheet->getStyle($celdaTotal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($celdaTotal)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            if ($ultimaFila >= $filaDatos) {
                $colTotalLetra = Coordinate::stringFromColumnIndex($colTotal);
                $sheet->getStyle($colTotalLetra.$filaDatos.':'.$colTotalLetra.$ultimaFila)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($verdeClaro);
            }
        }

        if ($ultimaFila >= $filaDatos) {
            $rangoInfo = Coordinate::stringFromColumnIndex($colBase).$filaDatos.':'
                .Coordinate::stringFromColumnIndex($colBase + 2).$ultimaFila;
            $sheet->getStyle($rangoInfo)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($verdeClaro);
        }
    }

    private function escribirCodigoPl(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $colBase,
        int $fila,
        string $codigo
    ): void {
        $celda = $this->celda($colBase, $fila);

        if ($codigo === '') {
            $sheet->setCellValue($celda, '');

            return;
        }

        $sheet->setCellValueExplicit($celda, $codigo, DataType::TYPE_STRING);
    }

    private function aplicarFormatoCodigoPl(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $colBase,
        int $filaDatos,
        int $ultimaFila
    ): void {
        if ($ultimaFila < $filaDatos) {
            return;
        }

        $columna = Coordinate::stringFromColumnIndex($colBase);
        $sheet->getStyle($columna.$filaDatos.':'.$columna.$ultimaFila)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
    }

    private function aplicarAutoFiltro(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $colBase,
        int $filaEncabezado,
        int $ultimaColumna,
        int $ultimaFila
    ): void {
        if ($ultimaFila < $filaEncabezado) {
            return;
        }

        $sheet->setAutoFilter(
            $this->celda($colBase, $filaEncabezado).':'.$this->celda($ultimaColumna, $ultimaFila)
        );
    }

    private function formatearCantidad(float $cantidad): float|int
    {
        if (abs($cantidad - round($cantidad)) < 0.001) {
            return (int) round($cantidad);
        }

        return round($cantidad, 2);
    }

    private function celda(int $columna, int $fila): string
    {
        return Coordinate::stringFromColumnIndex($columna).$fila;
    }
}
