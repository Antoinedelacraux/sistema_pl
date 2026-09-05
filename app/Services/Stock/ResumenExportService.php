<?php

namespace App\Services\Stock;

use App\Models\Stock\Almacen;
use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use App\Support\ExportText;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumenExportService
{
    public function __construct(
        private readonly StockResumenService $resumenService
    ) {}

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        $columnas = ExportText::tituloPalabrasLista([
            'Código Planeamiento',
            'Material Planeamiento',
        ]);

        foreach ($this->almacenesActivos() as $almacen) {
            $columnas[] = ExportText::tituloPalabras('Stock '.$almacen->codigo);
        }

        return array_merge($columnas, ExportText::tituloPalabrasLista([
            'Total',
            'Mínimo',
            'Estado',
        ]));
    }

    /**
     * @return list<array<int, string|float|null>>
     */
    public function filasExportables(array $filtros): array
    {
        return $this->resumenService->filasResumen($filtros)
            ->map(fn (object $fila) => $this->filaComoFila($fila))
            ->all();
    }

    public function exportarExcel(array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        return DocumentoExportSupport::exportarExcel(
            $this->encabezados(),
            $this->filasExportables($filtros),
            $usuario,
            'Inicio',
            'inicio-stock',
            'TablaInicio'
        );
    }

    public function exportarPdf(array $filtros, ?Usuario $usuario = null): Response
    {
        $filas = $this->filasExportables($filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => 'Inicio',
            'titulo' => 'Inicio · stock por almacén',
            'filas' => $filas,
            'encabezados' => $this->encabezados(),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], 'inicio-stock');
    }

    /**
     * @return list<string|float|null>
     */
    private function filaComoFila(object $fila): array
    {
        $datos = [
            $fila->material->CodigoPL,
            $fila->material->nomMaterial,
        ];

        foreach ($this->almacenesActivos() as $almacen) {
            $datos[] = number_format($fila->stocks[$almacen->codigo] ?? 0, 2, '.', '');
        }

        $datos[] = number_format($fila->total, 2, '.', '');
        $datos[] = number_format($fila->stockMinimo, 2, '.', '');
        $datos[] = $this->etiquetaEstado($fila->estadoStock);

        return $datos;
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'critico' => 'Crítico',
            'alerta' => 'Alerta',
            'saludable' => 'OK',
            'sin_config' => 'Sin mín.',
            default => ExportText::tituloPalabras($estado),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, Almacen>
     */
    private function almacenesActivos()
    {
        return $this->resumenService->catalogos()['almacenes'];
    }
}
