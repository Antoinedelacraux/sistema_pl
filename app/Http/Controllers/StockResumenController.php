<?php

namespace App\Http\Controllers;

use App\Services\Stock\ResumenExportService;
use App\Services\Stock\StockResumenService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockResumenController extends Controller
{
    public function __construct(
        private readonly StockResumenService $resumenService,
        private readonly ResumenExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtrosDesdeRequest($request);

        return view('resumen.index', array_merge($this->resumenService->catalogos(), [
            'filas' => $this->resumenService->filasResumenPaginadas($filtros, $request),
            'filtros' => $filtros,
            'contadores' => $this->resumenService->contadores(),
            'activeNav' => 'resumen',
        ]));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        return $this->exportService->exportarExcel(
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    public function exportPdf(Request $request): Response
    {
        return $this->exportService->exportarPdf(
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    /**
     * @return array{almacen: mixed, estado: mixed, buscar: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'almacen' => $request->query('almacen'),
            'estado' => $request->query('estado'),
            'buscar' => trim((string) $request->query('buscar', '')),
        ];
    }
}
