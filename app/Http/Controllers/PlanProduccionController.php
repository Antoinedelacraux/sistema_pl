<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanProduccion\ImportPlanProduccionExcelRequest;
use App\Http\Requests\PlanProduccion\UpdatePlanProduccionRequest;
use App\Services\Auditoria\AuditoriaService;
use App\Services\PlanProduccion\PlanProduccionGridService;
use App\Services\PlanProduccion\PlanProduccionImportService;
use App\Services\PlanProduccion\PlanProduccionUpdateService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanProduccionController extends Controller
{
    public function __construct(
        private readonly PlanProduccionGridService $gridService,
        private readonly PlanProduccionImportService $importService,
        private readonly PlanProduccionUpdateService $updateService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $datos = $this->gridService->datos();

        return view('plan-produccion.index', [
            'semanas' => $datos['semanas'],
            'filas' => $datos['filas'],
            'planConsumo' => $datos['planConsumo'],
            'semanasPlanConsumo' => $datos['semanasPlanConsumo'],
            'totalProgramas' => $datos['total_programas'],
            'puedeImportar' => $request->user()?->puede('plan_produccion', 'crear') ?? false,
            'puedeEditar' => $request->user()?->puede('plan_produccion', 'editar') ?? false,
            'activeNav' => 'plan_produccion',
        ]);
    }

    public function importExcel(ImportPlanProduccionExcelRequest $request): RedirectResponse
    {
        try {
            $resultado = $this->importService->importar($request->file('archivo_excel'));
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('plan-produccion.index')
                ->with('error', $exception->getMessage());
        }

        $this->gridService->forgetCaches();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PLAN_PRODUCCION_IMPORTAR,
            entidad: 'PlanProduccion',
            idEntidad: null,
            descripcion: 'Importación Excel plan de producción',
            datosNuevos: $resultado,
            request: $request,
        );

        $mensaje = sprintf(
            'Plan importado: %d semana(s), %d programa(s), %d celda(s) con valor.',
            $resultado['semanas'],
            $resultado['programas'],
            $resultado['celdas']
        );

        if ($resultado['mrp_sync'] ?? null) {
            $mensaje .= sprintf(
                ' MRP sincronizado: %d material(es).',
                $resultado['mrp_sync']['materiales']
            );
        } elseif ($resultado['mrp_sync_error'] ?? null) {
            return redirect()
                ->route('plan-produccion.index')
                ->with('status', $mensaje.' Aviso MRP: '.$resultado['mrp_sync_error']);
        }

        return redirect()
            ->route('plan-produccion.index')
            ->with('status', $mensaje);
    }

    public function updatePlan(UpdatePlanProduccionRequest $request): RedirectResponse
    {
        $cambios = $this->updateService->actualizar(
            $request->validated('cantidades'),
            $request->validated('materiales') ?? []
        );
        $this->gridService->forgetCaches();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PLAN_PRODUCCION_ACTUALIZAR,
            entidad: 'PlanProduccion',
            idEntidad: null,
            descripcion: 'Plan de producción editado (sincronizado con MRP)',
            datosNuevos: ['cambios' => $cambios],
            request: $request,
        );

        return redirect()
            ->route('plan-produccion.index')
            ->with(
                'status',
                $cambios > 0
                    ? sprintf('Plan actualizado (%d cambio(s)). Plan de consumo MRP sincronizado.', $cambios)
                    : 'No hubo cambios en el plan.'
            );
    }

    public function vaciar(Request $request): RedirectResponse
    {
        $this->importService->vaciar();
        $this->gridService->forgetCaches();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PLAN_PRODUCCION_VACIAR,
            entidad: 'PlanProduccion',
            idEntidad: null,
            descripcion: 'Plan de producción vaciado',
            request: $request,
        );

        return redirect()
            ->route('plan-produccion.index')
            ->with('status', 'Plan de producción vaciado. Importe el Excel para cargar datos.');
    }
}
