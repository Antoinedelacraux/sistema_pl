<?php

namespace App\Services\Programas;

use App\Models\Programas\ProgramaFila;
use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use App\Support\ExportText;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramaExportService
{
    public function __construct(
        private readonly ProgramaGridService $gridService
    ) {}

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        return ExportText::tituloPalabrasLista([
            '#',
            'Mercado',
            'Cliente',
            'Variedad',
            'Programa',
            'Presentación',
            'Presentación Nisira',
            'Código PL',
            'Material',
            'Cantidad x caja',
            'Uso',
            'Situado',
            'Familia',
        ]);
    }

    /**
     * @return list<array<int, string|int|float|null>>
     */
    public function filasExportables(array $filtros): array
    {
        return $this->gridService->filas($filtros)
            ->map(fn (ProgramaFila $fila) => $this->filaComoFila($fila))
            ->all();
    }

    public function exportarExcel(array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        return DocumentoExportSupport::exportarExcel(
            $this->encabezados(),
            $this->filasExportables($filtros),
            $usuario,
            'Receta',
            'receta',
            'TablaReceta'
        );
    }

    public function exportarPdf(array $filtros, ?Usuario $usuario = null): Response
    {
        $filas = $this->filasExportables($filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => 'Receta',
            'titulo' => 'Receta · Materiales Por Receta',
            'filas' => $filas,
            'encabezados' => $this->encabezados(),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], 'receta');
    }

    /**
     * @return list<string|int|float|null>
     */
    private function filaComoFila(ProgramaFila $fila): array
    {
        return [
            $fila->nroFila,
            $fila->mercado?->nomMercado,
            $fila->cliente?->nomCliente,
            $fila->variedad?->nomVariedad,
            $fila->programa?->nomPrograma,
            $fila->presentacion?->nomPresentacion,
            $fila->preNisira?->nomNisira,
            $fila->material?->CodigoPL,
            $fila->material?->nomMaterial,
            number_format((float) $fila->cantidadCaja?->cantidad, 2, '.', ''),
            $fila->uso?->nomUso,
            $fila->situado?->nomSituado,
            $fila->familia?->nomFamilia,
        ];
    }
}
