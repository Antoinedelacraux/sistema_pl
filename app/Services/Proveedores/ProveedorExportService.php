<?php

namespace App\Services\Proveedores;

use App\Support\ExportaGridDocumento;
use App\Support\ExportText;

class ProveedorExportService
{
    use ExportaGridDocumento;

    public function __construct(
        private readonly ProveedorGridService $gridService
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
            'tituloDocumento' => 'Proveedor',
            'tituloPdf' => 'Proveedor · Códigos TGF Y SAFCO',
            'prefijo' => 'proveedor',
            'tituloHoja' => 'Proveedor',
            'nombreTabla' => 'TablaProveedor',
        ];
    }

    /**
     * @return list<string>
     */
    public function encabezados(): array
    {
        $encabezados = ExportText::tituloPalabrasLista([
            '#',
            'Material',
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
