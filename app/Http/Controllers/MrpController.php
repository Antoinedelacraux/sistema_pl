<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mrp\ImportMrpExcelRequest;
use App\Http\Requests\Mrp\UpdateMrpPlanRequest;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Mrp\MrpGridService;
use App\Services\Mrp\MrpImportService;
use App\Services\Mrp\MrpPlanUpdateService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MrpController extends Controller
{
    public function __construct(
        private readonly MrpGridService $gridService,
        private readonly MrpImportService $importService,
        private readonly MrpPlanUpdateService $planUpdateService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $filtros = [
            'familia' => filled($request->query('familia')) ? (int) $request->query('familia') : null,
        ];
        $datos = $this->gridService->datos($filtros);

        return view('mrp.index', [
            'semanas' => $datos['semanas'],
            'plan' => $datos['plan'],
            'consumoReal' => $datos['consumo_real'],
            'ingresos' => $datos['ingresos'],
            'totalMateriales' => $datos['total_materiales'],
            'familiasFiltro' => $this->gridService->familiasParaFiltro(),
            'filtros' => $filtros,
            'puedeImportar' => $request->user()?->puede('mrp', 'crear') ?? false,
            'puedeEditar' => $request->user()?->puede('mrp', 'editar') ?? false,
            'activeNav' => 'mrp',
        ]);
    }

    public function importExcel(ImportMrpExcelRequest $request): RedirectResponse
    {
        try {
            $resultado = $this->importService->importar($request->file('archivo_excel'));
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('mrp.index')
                ->with('error', $exception->getMessage());
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::MRP_IMPORTAR,
            entidad: 'PlanConsumo',
            idEntidad: null,
            descripcion: 'Importación Excel plan MRP',
            datosNuevos: $resultado,
            request: $request,
        );

        return redirect()
            ->route('mrp.index')
            ->with(
                'status',
                sprintf(
                    'Plan MRP importado: %d semana(s), %d material(es).',
                    $resultado['semanas'],
                    $resultado['filas_plan']
                )
            );
    }

    public function vaciarPlan(Request $request): RedirectResponse
    {
        $this->importService->vaciarPlan();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::MRP_VACIAR,
            entidad: 'PlanConsumo',
            idEntidad: null,
            descripcion: 'Plan MRP vaciado desde la interfaz',
            request: $request,
        );

        return redirect()
            ->route('mrp.index')
            ->with('status', 'Plan MRP vaciado. Importe el Excel para cargar semanas y consumo planificado.');
    }

    public function updatePlan(UpdateMrpPlanRequest $request): RedirectResponse
    {
        $cantidades = $request->validated('cantidades');
        $cambios = $this->planUpdateService->actualizar($cantidades);

        $this->auditoria->registrar(
            accion: AuditoriaAccion::MRP_PLAN_ACTUALIZAR,
            entidad: 'PlanConsumo',
            idEntidad: null,
            descripcion: 'Plan de consumo editado desde la interfaz',
            datosNuevos: ['cambios' => $cambios],
            request: $request,
        );

        return redirect()
            ->route('mrp.index', array_filter([
                'familia' => $request->input('familia'),
            ]))
            ->with(
                'status',
                $cambios > 0
                    ? sprintf('Plan de consumo actualizado (%d cambio(s)).', $cambios)
                    : 'No hubo cambios en el plan de consumo.'
            );
    }
}
