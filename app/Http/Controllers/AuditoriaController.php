<?php

namespace App\Http\Controllers;

use App\Services\Auditoria\AuditoriaGridService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriaGridService $gridService
    ) {}

    public function index(Request $request): View
    {
        $filtros = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'accion' => (string) $request->query('accion', ''),
            'usuario' => (string) $request->query('usuario', ''),
            'fecha_desde' => (string) $request->query('fecha_desde', ''),
            'fecha_hasta' => (string) $request->query('fecha_hasta', ''),
        ];

        return view('auditoria.index', [
            'registros' => $this->gridService->listadoPaginado($filtros, $request),
            'contadores' => $this->gridService->contadores(),
            'acciones' => $this->gridService->accionesDisponibles(),
            'usuarios' => $this->gridService->usuariosConActividad(),
            'filtros' => $filtros,
            'activeNav' => 'auditoria',
        ]);
    }
}
