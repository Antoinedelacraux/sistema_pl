<?php

namespace App\Services\Programas;

use App\Models\Programas\ProgramaFila;
use App\Support\CantidadCajaSupport;
use App\Support\ExportaGridDocumento;

class ProgramaExportService
{
    use ExportaGridDocumento;

    public function __construct(
        private readonly ProgramaGridService $gridService
    ) {}

    /**
     * @return array{
     *     tituloDocumento: string,
     *     tituloPdf: string,
     *     prefijo: string,
     *     tituloHoja: string,
     *     nombreTabla: string
     * }
     */
    protected function configuracionExport(): array
    {
        return [
            'tituloDocumento' => 'Receta',
            'tituloPdf' => 'Receta · Materiales Por Receta',
            'prefijo' => 'receta',
            'tituloHoja' => 'Receta',
            'nombreTabla' => 'TablaReceta',
        ];
    }

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        return [
            '#',
            'Mercado',
            'Cliente',
            'Prog. General',
            'Cód. PG',
            'Programa',
            'Variedad',
            'Producto',
            'Material',
            'Código PL',
            'Descripción material',
            'Observación',
            'Cantidad x caja',
        ];
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

    /**
     * @return list<string|int|float|null>
     */
    private function filaComoFila(ProgramaFila $fila): array
    {
        return [
            $fila->nroFila,
            $fila->mercado?->nomMercado,
            $fila->cliente?->nomCliente,
            $fila->programa?->programaGeneral?->nombre,
            $fila->programa?->programaGeneral?->CodigoProGeneral,
            $fila->programa?->nomPrograma,
            $fila->variedad?->nomVariedad,
            $fila->presentacion?->nomPresentacion,
            $fila->familia?->nomFamilia,
            $fila->material?->CodigoPL,
            $fila->material?->nomMaterial,
            $fila->uso?->nomUso,
            CantidadCajaSupport::formatear(
                $fila->cantidadCaja?->cantidad !== null ? (float) $fila->cantidadCaja->cantidad : null,
                $fila->familia?->nomFamilia
            ),
        ];
    }
}
