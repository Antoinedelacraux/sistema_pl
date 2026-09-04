<?php

namespace App\Services\Stock;

use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Traslado;
use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use App\Support\DocumentoGuiaSupport;
use App\Support\ExportText;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistroExportService
{
    public function __construct(
        private readonly MovimientoService $movimientoService
    ) {}

    /**
     * @return list<string>
     */
    public function encabezados(string $tab): array
    {
        return match ($tab) {
            'consumos' => ExportText::tituloPalabrasLista([
                'Fecha',
                'Almacén',
                'Programa',
                'Presentación',
                'Motivo',
                'Material Planeamiento',
                'Cantidad',
                'Observación',
            ]),
            'traslados' => ExportText::tituloPalabrasLista([
                'Fecha',
                'Origen',
                'Destino',
                'Código Planeamiento',
                'Material Planeamiento',
                'Cantidad',
                'Factura',
                'Guía',
                'Observación',
            ]),
            default => ExportText::tituloPalabrasLista([
                'Tipo',
                'Almacén',
                'Material Planeamiento',
                'Fecha',
                'Código Planeamiento',
                'Cantidad',
                'Factura',
                'Guía',
                'Observación',
            ]),
        };
    }

    /**
     * @return list<array<int, string|float|null>>
     */
    public function filasExportables(string $tab, array $filtros): array
    {
        return match ($tab) {
            'consumos' => $this->movimientoService->listarEgresos($filtros)
                ->map(fn (object $egreso) => $this->filaEgreso($egreso))
                ->all(),
            'traslados' => $this->movimientoService->listarTraslados($filtros)
                ->map(fn (Traslado $traslado) => $this->filaTraslado($traslado))
                ->all(),
            default => $this->movimientoService->listarIngresos($filtros)
                ->map(fn (object $ingreso) => $this->filaIngreso($ingreso))
                ->all(),
        };
    }

    public function exportarExcel(string $tab, array $filtros, ?Usuario $usuario = null): StreamedResponse
    {
        $tab = $this->tabValida($tab);

        return DocumentoExportSupport::exportarExcel(
            $this->encabezados($tab),
            $this->filasExportables($tab, $filtros),
            $usuario,
            'Registro',
            'registro-'.$tab,
            'TablaRegistro'
        );
    }

    public function exportarPdf(string $tab, array $filtros, ?Usuario $usuario = null): Response
    {
        $tab = $this->tabValida($tab);
        $filas = $this->filasExportables($tab, $filtros);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => 'Registro',
            'titulo' => 'Registro · '.$this->tituloTab($tab),
            'filas' => $filas,
            'encabezados' => $this->encabezados($tab),
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], 'registro-'.$tab);
    }

    private function tabValida(string $tab): string
    {
        return in_array($tab, ['ingresos', 'consumos', 'egresos', 'traslados'], true)
            ? ($tab === 'egresos' ? 'consumos' : $tab)
            : 'ingresos';
    }

    private function tituloTab(string $tab): string
    {
        return match ($tab) {
            'consumos' => 'Consumos',
            'traslados' => 'Traslados',
            default => 'Ingresos',
        };
    }

    /**
     * @return list<string|float|null>
     */
    private function filaIngreso(object $ingreso): array
    {
        return [
            'Ingreso',
            $ingreso->almacen?->codigo,
            $ingreso->material,
            $ingreso->fecha?->format('d/m/Y'),
            $ingreso->codigo_pl,
            number_format((float) $ingreso->cantidad, 2, '.', ''),
            $ingreso->factura ?: '—',
            $ingreso->guia ?: '—',
            $ingreso->observacion ?: '—',
        ];
    }

    /**
     * @return list<string|float|null>
     */
    private function filaEgreso(object $egreso): array
    {
        return [
            $egreso->fecha?->format('d/m/Y'),
            $egreso->almacen?->codigo,
            $egreso->programa?->nomPrograma ?: '—',
            $egreso->presentacion?->nomPresentacion ?: '—',
            $egreso->tipoConsumo?->nomTipoConsumo ?: '—',
            $egreso->material ?? ($egreso->materialPl?->nomMaterial),
            number_format((float) $egreso->cantidad, 2, '.', ''),
            $egreso->observacion ?: '—',
        ];
    }

    /**
     * @return list<string|float|null>
     */
    private function filaTraslado(Traslado $traslado): array
    {
        return [
            $traslado->fecha?->format('d/m/Y'),
            $traslado->almacenOrigen?->codigo,
            $traslado->almacenDestino?->codigo,
            $traslado->materialPl?->CodigoPL,
            $traslado->materialPl?->nomMaterial,
            number_format((float) $traslado->cantidad, 2, '.', ''),
            $traslado->factura ?: '—',
            $this->guiaTraslado($traslado) ?: '—',
            $traslado->observacion ?: '—',
        ];
    }

    private function guiaMovimiento(MovimientoStock $mov): ?string
    {
        return DocumentoGuiaSupport::resolver($mov->guia, $mov->factura, $mov->facturaGuia);
    }

    private function guiaTraslado(Traslado $traslado): ?string
    {
        return DocumentoGuiaSupport::resolver($traslado->guia, $traslado->factura, $traslado->facturaGuia);
    }
}
