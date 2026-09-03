<?php

namespace App\Http\Controllers;

use App\Services\Stock\InventarioExportService;
use App\Services\Stock\InventarioService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly InventarioExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $almacenes = $this->inventarioService->almacenes();
        $tabActiva = $this->codigoAlmacenDesdeRequest($request, $almacenes->pluck('codigo')->all());
        $filtros = $this->filtrosDesdeRequest($request);
        $filas = $this->inventarioService->filasPaginadas($tabActiva, $filtros, $request);

        return view('inventario.index', [
            'almacenes' => $almacenes,
            'tabActiva' => $tabActiva,
            'almacenActivo' => $almacenes->firstWhere('codigo', $tabActiva),
            'filas' => $filas,
            'filtros' => $filtros,
            'contadores' => $this->inventarioService->contadoresPorAlmacen($tabActiva),
            'activeNav' => 'inventario',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $almacenes = $this->inventarioService->almacenes();
        $tabActiva = $this->codigoAlmacenDesdeRequest($request, $almacenes->pluck('codigo')->all());

        return $this->exportService->exportarExcel(
            $tabActiva,
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    public function exportPdf(Request $request): Response
    {
        $almacenes = $this->inventarioService->almacenes();
        $tabActiva = $this->codigoAlmacenDesdeRequest($request, $almacenes->pluck('codigo')->all());
        $almacenActivo = $almacenes->firstWhere('codigo', $tabActiva);

        return $this->exportService->exportarPdf(
            $tabActiva,
            $almacenActivo?->nomAlmacen ?? $tabActiva,
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    /**
     * @param  list<string>  $codigosValidos
     */
    private function codigoAlmacenDesdeRequest(Request $request, array $codigosValidos): string
    {
        $tabActiva = strtoupper((string) $request->query('almacen', $codigosValidos[0] ?? 'SFC'));
        if ($tabActiva === 'SAFCO') {
            $tabActiva = 'SFC';
        }

        if (! in_array($tabActiva, $codigosValidos, true)) {
            return $codigosValidos[0] ?? 'SFC';
        }

        return $tabActiva;
    }

    /**
     * @return array{estado: string, buscar: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'estado' => (string) $request->query('estado', 'con_stock'),
            'buscar' => trim((string) $request->query('buscar', '')),
        ];
    }
}
