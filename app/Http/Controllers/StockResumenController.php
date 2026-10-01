<?php

namespace App\Http\Controllers;

use App\Services\Stock\ConsumoRecetaService;
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
        private readonly ResumenExportService $exportService,
        private readonly ConsumoRecetaService $consumoRecetaService
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtrosDesdeRequest($request);
        $filasCompletas = $this->resumenService->filasResumen($filtros);

        return view('resumen.index', array_merge($this->resumenService->catalogos($filtros), [
            'filas' => \App\Support\GridPagination::paginateCollection($filasCompletas, $request),
            'filtros' => $filtros,
            'contadores' => $this->resumenService->contadoresDesdeFilas($filasCompletas),
            'activeNav' => 'inicio',
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
     * @return array{programa: int|null, producto: int|null, estado: mixed, buscar: string, cajas: float|null}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        $programa = (int) $request->query('programa', 0) ?: null;
        $producto = (int) $request->query('producto', 0) ?: null;

        if ($programa === null) {
            $producto = null;
        } elseif ($producto !== null) {
            $validos = $this->consumoRecetaService
                ->presentacionesDePrograma($programa)
                ->pluck('idPresentacion');

            if (! $validos->contains($producto)) {
                $producto = null;
            }
        }

        return [
            'programa' => $programa,
            'producto' => $producto,
            'estado' => $request->query('estado'),
            'buscar' => trim((string) $request->query('buscar', '')),
            'cajas' => $this->cajasDesdeRequest($request, $programa, $producto),
        ];
    }

    private function cajasDesdeRequest(Request $request, ?int $programa, ?int $producto): ?float
    {
        return $this->numeroSimulacion($request->query('cajas'), $programa, $producto);
    }

    private function numeroSimulacion(mixed $raw, ?int $programa, ?int $producto): ?float
    {
        if ($programa === null || $producto === null) {
            return null;
        }

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $valor = (float) $raw;

        return $valor > 0 ? $valor : null;
    }
}

