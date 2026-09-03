<?php

namespace App\Services\Proveedores;

use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use App\Support\ExportText;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorExportService
{
    public function __construct(
        private readonly ProveedorGridService $gridService
    ) {}

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        $encabezados = ExportText::tituloPalabrasLista([
            '#',
            'Familia',
            'Código Planeamiento',
            'Material Planeamiento',
        ]);

        foreach ($this->gridService->columnasProveedor() as $proveedor) {
            $encabezados[] = ExportText::tituloPalabras('Código').' '.$proveedor['nombre'];
            $encabezados[] = ExportText::tituloPalabras('Material').' '.$proveedor['nombre'];
        }

        return $encabezados;
    }

    /**
     * @return list<array<int, string|int|float|null>>
     */
    public function filasExportables(array $filtros): array
    {
        return $this->gridService->filasGrilla($filtros)
            ->map(fn (object $fila) => $this->filaComoFila($fila))
            ->all();
    }

    public function exportarExcel(array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        return DocumentoExportSupport::exportarExcel(
            $this->encabezados(),
            $this->filasExportables($filtros),
            $usuario,
            'Proveedor',
            'proveedor',
            'TablaProveedor'
        );
    }

    public function exportarPdf(array $filtros, ?Usuario $usuario = null): Response
    {
        $filas = $this->filasExportables($filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => 'Proveedor',
            'titulo' => 'Proveedor · Códigos TGF Y SAFCO',
            'filas' => $filas,
            'encabezados' => $this->encabezados(),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], 'proveedor');
    }

    /**
     * @return list<string|int|float|null>
     */
    private function filaComoFila(object $fila): array
    {
        $datos = [
            $fila->nroFila,
            $fila->familia?->nomFamilia,
            $fila->materialPl?->CodigoPL,
            $fila->materialPl?->nomMaterial,
        ];

        foreach ($this->gridService->columnasProveedor() as $proveedor) {
            $datosProv = $fila->proveedores[$proveedor['nombre']] ?? ['codigo' => null, 'nomMaterial' => null];
            $datos[] = $datosProv['codigo'];
            $datos[] = $datosProv['nomMaterial'];
        }

        return $datos;
    }
}
