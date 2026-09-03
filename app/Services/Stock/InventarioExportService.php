<?php

namespace App\Services\Stock;

use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use App\Support\ExportText;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioExportService
{
    public function __construct(
        private readonly InventarioService $inventarioService
    ) {}

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        return ExportText::tituloPalabrasLista([
            'Código Planeamiento',
            'Material Planeamiento',
            'Stock',
            'Mínimo',
            'Estado',
        ]);
    }

    /**
     * @return list<array<int, string|int|float|null>>
     */
    public function filasExportables(string $codigoAlmacen, array $filtros): array
    {
        return $this->inventarioService->filas($codigoAlmacen, $filtros)
            ->map(fn (object $fila) => $this->filaComoFila($fila))
            ->all();
    }

    public function exportarExcel(string $codigoAlmacen, array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        return DocumentoExportSupport::exportarExcel(
            $this->encabezados(),
            $this->filasExportables($codigoAlmacen, $filtros),
            $usuario,
            'Inventario',
            'inventario-'.strtolower($codigoAlmacen),
            'TablaInventario'
        );
    }

    public function exportarPdf(string $codigoAlmacen, string $nombreAlmacen, array $filtros, ?Usuario $usuario = null): Response
    {
        $filas = $this->filasExportables($codigoAlmacen, $filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => 'Inventario',
            'titulo' => 'Inventario · '.$nombreAlmacen,
            'filas' => $filas,
            'encabezados' => $this->encabezados(),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], 'inventario-'.strtolower($codigoAlmacen));
    }

    /**
     * @return list<string|float|null>
     */
    private function filaComoFila(object $fila): array
    {
        return [
            $fila->CodigoPL,
            $fila->nomMaterial,
            number_format($fila->stock, 2, '.', ''),
            number_format($fila->stockMinimo, 2, '.', ''),
            $this->etiquetaEstado($fila->estadoStock),
        ];
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
}
