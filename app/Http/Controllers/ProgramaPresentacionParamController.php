<?php

namespace App\Http\Controllers;

use App\Http\Requests\Programas\StoreProgramaPresentacionParamRequest;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Programas\ProgramaGridService;
use App\Services\Programas\ProgramaPresentacionParamService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramaPresentacionParamController extends Controller
{
    public function __construct(
        private readonly ProgramaPresentacionParamService $paramService,
        private readonly ProgramaGridService $gridService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->gridService->normalizarFiltros([
            'programa' => $request->query('programa'),
            'presentacion' => $request->query('presentacion'),
            'q' => trim((string) $request->query('buscar', '')),
        ]);

        $combinaciones = $this->paramService->listarPaginado([
            'programa' => $filtros['programa'],
            'presentacion' => $filtros['presentacion'],
            'buscar' => $filtros['q'],
        ], $request);

        return view('programas.presentacion_params', array_merge($this->gridService->catalogos(), [
            'catalogosFiltro' => $this->gridService->catalogosFiltro($filtros),
            'combinaciones' => $combinaciones,
            'filtros' => [
                'programa' => $filtros['programa'],
                'presentacion' => $filtros['presentacion'],
                'buscar' => $filtros['q'],
            ],
            'puedeEditar' => $request->user()?->puede('programas', 'editar') ?? false,
            'activeNav' => 'programas',
            'recetaTab' => 'params',
        ]));
    }

    public function store(StoreProgramaPresentacionParamRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $param = $this->paramService->guardar(
            (int) $datos['id_programa'],
            (int) $datos['id_presentacion'],
            (float) ($datos['pallets'] ?? 0),
            (float) ($datos['conformacion'] ?? 0)
        );

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROGRAMA_PARAM_GUARDAR,
            entidad: 'ProgramaPresentacionParam',
            idEntidad: $param->idProgramaPresentacionParam,
            descripcion: 'Pallets y conformación actualizados',
            datosNuevos: $datos,
            request: $request,
        );

        return redirect()
            ->route('programas.presentacion-params.index')
            ->with('status', 'Pallets y conformación guardados.');
    }
}
