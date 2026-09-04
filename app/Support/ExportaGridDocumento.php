<?php

namespace App\Support;

use App\Models\Usuario;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportaGridDocumento
{
    /**
     * @return array{
     *     tituloDocumento: string,
     *     tituloPdf: string,
     *     prefijo: string,
     *     tituloHoja: string,
     *     nombreTabla: string
     * }
     */
    abstract protected function configuracionExport(): array;

    /**
     * @return list<string>
     */
    abstract public function encabezados(): array;

    /**
     * @return list<array<int, string|int|float|null>>
     */
    abstract public function filasExportables(array $filtros): array;

    public function exportarExcel(array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        $config = $this->configuracionExport();

        return DocumentoExportSupport::exportarExcel(
            $this->encabezados(),
            $this->filasExportables($filtros),
            $usuario,
            $config['tituloHoja'],
            $config['prefijo'],
            $config['nombreTabla'],
        );
    }

    public function exportarPdf(array $filtros, ?Usuario $usuario = null): Response
    {
        $config = $this->configuracionExport();
        $filas = $this->filasExportables($filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => $config['tituloDocumento'],
            'titulo' => $config['tituloPdf'],
            'filas' => $filas,
            'encabezados' => $this->encabezados(),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], $config['prefijo']);
    }
}
