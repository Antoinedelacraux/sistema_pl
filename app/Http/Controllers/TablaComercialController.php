<?php

namespace App\Http\Controllers;

use App\Services\PlanProduccion\PlanProduccionGridService;
use App\Services\TablaComercial\TablaComercialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TablaComercialController extends Controller
{
    public function __construct(
        private readonly PlanProduccionGridService $gridService,
        private readonly TablaComercialService $tcService,
    ) {}

    public function index(Request $request): View
    {
        $datos = $this->gridService->datosPlanVentas();

        $semanas = $datos['semanas'];
        $filas   = $datos['filas'];

        $pgIds  = array_map(fn ($f) => (int) $f['idProgramaGeneral'], $filas);
        $semIds = $semanas->pluck('idSemProduccion')->map(fn ($id) => (int) $id)->all();

        $estados  = $this->tcService->estadosBulk($semIds, $pgIds);
        $egresos  = $this->tcService->egresosBulk($semanas, $pgIds); // "{idPg}_{idSem}" → suma

        return view('tabla-comercial.index', [
            'semanas'        => $semanas,
            'filas'          => $filas,
            'totalProgramas' => $datos['total_programas'],
            'tcEstados'      => $estados,
            'tcEgresos'      => $egresos,
            'puedeEditar'    => $request->user()?->puede('plan_produccion', 'editar') ?? false,
            'activeNav'      => 'tabla_comercial',
        ]);
    }

    /**
     * Asignaciones día + estados de una semana para el kanban.
     * GET /tabla-comercial/semana/{idSemProduccion}
     */
    public function semana(Request $request, int $idSemProduccion): JsonResponse
    {
        abort_unless($request->user()?->puede('plan_produccion', 'ver') ?? false, 403);

        return response()->json($this->tcService->semanaBulk($idSemProduccion));
    }

    /**
     * Guarda o actualiza el día asignado.
     * POST /tabla-comercial/asignar
     */
    public function asignar(Request $request): JsonResponse
    {
        abort_unless($request->user()?->puede('plan_produccion', 'editar') ?? false, 403);

        $v = $request->validate([
            'idProgramaGeneral' => 'required|integer|min:1',
            'idSemProduccion'   => 'required|integer|min:1',
            'dia'               => 'nullable|integer|min:1|max:7',
        ]);

        $this->tcService->asignarDia(
            (int) $v['idProgramaGeneral'],
            (int) $v['idSemProduccion'],
            $v['dia'] ?? null
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Guarda el estado de un programa en una semana.
     * POST /tabla-comercial/estado
     */
    public function updateEstado(Request $request): JsonResponse
    {
        abort_unless($request->user()?->puede('plan_produccion', 'editar') ?? false, 403);

        $v = $request->validate([
            'idProgramaGeneral' => 'required|integer|min:1',
            'idSemProduccion'   => 'required|integer|min:1',
            'estado'            => 'nullable|in:despachado',
        ]);

        $this->tcService->updateEstado(
            (int) $v['idProgramaGeneral'],
            (int) $v['idSemProduccion'],
            $v['estado'] ?? null
        );

        return response()->json(['ok' => true]);
    }
}
