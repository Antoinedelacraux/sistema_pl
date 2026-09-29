<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mrp\ImportMrpExcelRequest;
use App\Http\Requests\Mrp\UpdateMrpPlanRequest;
use App\Http\Requests\VaciarConfirmacionRequest;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Mrp\MrpGridService;
use App\Services\Mrp\MrpImportService;
use App\Services\Mrp\MrpPlanUpdateService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $filtros = $this->filtrosDesdeRequest($request);
        $datos = $this->gridService->datos($filtros, ['plan']);

        return view('mrp.index', [
            'semanas' => $datos['semanas'],
            'plan' => $datos['plan'],
            'totalMateriales' => $datos['total_materiales'],
            'familiasFiltro' => $this->gridService->familiasParaFiltro(),
            'filtros' => $filtros,
            'puedeImportar' => $request->user()?->puede('mrp', 'crear') ?? false,
            'puedeEditar' => $request->user()?->puede('mrp', 'editar') ?? false,
            'puedeVaciar' => $request->user()?->puede('mrp', 'eliminar') ?? false,
            'activeNav' => 'mrp',
        ]);
    }

    public function seccion(Request $request, string $tipo): View|Response
    {
        abort_unless(in_array($tipo, ['consumo', 'ingresos'], true), 404);

        $filtros = $this->filtrosDesdeRequest($request);
        $seccion = $tipo === 'consumo' ? 'consumo_real' : 'ingresos';
        $datos = $this->gridService->datos($filtros, [$seccion]);

        $viewData = [
            'semanas' => $datos['semanas'],
            'scrollId' => $tipo === 'consumo' ? 'mrp-scroll-consumo' : 'mrp-scroll-ingresos',
            'mensajeVacio' => 'Sin materiales en el plan.',
        ];

        if ($tipo === 'consumo') {
            $viewData['filas'] = $datos['consumo_real'];
            $viewData['resaltarNegativos'] = true;
            $viewData['columnasExtra'] = [
                ['key' => 'stock_actual', 'label' => 'Stock actual'],
            ];
        } else {
            $viewData['filas'] = $datos['ingresos'];
            $viewData['columnasExtra'] = [
                ['key' => 'ingreso_total', 'label' => 'Ingreso total'],
            ];
        }

        $html = view('mrp._tabla_matriz', $viewData)->render();

        if ($request->ajax() || $request->wantsJson()) {
            return response($html);
        }

        return view('mrp._tabla_matriz', $viewData);
    }

    public function seccionesExtra(Request $request): JsonResponse|Response
    {
        $filtros = $this->filtrosDesdeRequest($request);
        $datos = $this->gridService->datos($filtros, ['consumo_real', 'ingresos']);

        $consumoHtml = view('mrp._tabla_matriz', [
            'semanas' => $datos['semanas'],
            'filas' => $datos['consumo_real'],
            'scrollId' => 'mrp-scroll-consumo',
            'resaltarNegativos' => true,
            'columnasExtra' => [
                ['key' => 'stock_actual', 'label' => 'Stock actual'],
            ],
            'mensajeVacio' => 'Sin materiales en el plan.',
        ])->render();

        $ingresosHtml = view('mrp._tabla_matriz', [
            'semanas' => $datos['semanas'],
            'filas' => $datos['ingresos'],
            'scrollId' => 'mrp-scroll-ingresos',
            'columnasExtra' => [
                ['key' => 'ingreso_total', 'label' => 'Ingreso total'],
            ],
            'mensajeVacio' => 'Sin materiales en el plan.',
        ])->render();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'consumo' => $consumoHtml,
                'ingresos' => $ingresosHtml,
            ]);
        }

        return response($consumoHtml.$ingresosHtml);
    }

    public function importExcel(ImportMrpExcelRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $resultado = $this->importService->importar($request->file('archivo_excel'));
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('mrp.index')
                ->with('error', $exception->getMessage());
        }

        $this->gridService->forgetCaches();

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

    public function vaciarPlan(VaciarConfirmacionRequest $request): \Illuminate\Http\RedirectResponse
    {
        $this->importService->vaciarPlan();
        $this->gridService->forgetCaches();

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

    public function updatePlan(UpdateMrpPlanRequest $request): \Illuminate\Http\RedirectResponse
    {
        $cantidades = $request->validated('cantidades');
        $cambios = $this->planUpdateService->actualizar($cantidades);
        $this->gridService->forgetCaches();

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
            ], static fn ($v) => $v !== null && $v !== ''))
            ->with(
                'status',
                $cambios > 0
                    ? sprintf('Plan de consumo actualizado (%d cambio(s)).', $cambios)
                    : 'No hubo cambios en el plan de consumo.'
            );
    }

    /**
     * @return array{familia: int|null}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'familia' => filled($request->query('familia')) ? (int) $request->query('familia') : null,
        ];
    }
}
