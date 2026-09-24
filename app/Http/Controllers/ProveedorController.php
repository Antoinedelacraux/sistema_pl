<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proveedores\ImportProveedorConfirmRequest;
use App\Http\Requests\Proveedores\ImportProveedorPreviewRequest;
use App\Http\Requests\Proveedores\StoreProveedorRequest;
use App\Http\Requests\Proveedores\UpdateProveedorRequest;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Proveedores\ProveedorExportService;
use App\Services\Proveedores\ProveedorGridService;
use App\Services\Proveedores\ProveedorImportService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorController extends Controller
{
    public function __construct(
        private readonly ProveedorGridService $gridService,
        private readonly ProveedorExportService $exportService,
        private readonly ProveedorImportService $importService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $catalogos = $this->gridService->catalogos();
        $filtros = $this->filtrosDesdeRequest($request);
        $filas = $this->gridService->filasGrillaPaginadas($filtros, $request);
        $columnas = $this->gridService->metadatosColumnas();
        $user = $request->user();

        return view('proveedores.index', array_merge($catalogos, [
            'filas' => $filas,
            'filtros' => $filtros,
            'totalFilas' => $filas->total(),
            'columnasProveedor' => $columnas['columnasProveedor'],
            'columnasConfig' => $columnas['columnasConfig'],
            'puedeCrear' => $user?->puede('proveedor', 'crear') ?? false,
            'puedeEditar' => $user?->puede('proveedor', 'editar') ?? false,
            'puedeEliminar' => $user?->puede('proveedor', 'eliminar') ?? false,
            'activeNav' => 'proveedor',
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

    public function importPreview(ImportProveedorPreviewRequest $request): JsonResponse
    {
        try {
            $resultado = $this->importService->preview($request->file('archivo_excel'));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    public function importConfirm(ImportProveedorConfirmRequest $request): RedirectResponse
    {
        $validado = $request->validated();
        $resultado = $this->importService->confirmar(
            $validado['nom_proveedor'],
            $validado['filas'],
            $this->gridService
        );

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROVEEDOR_CREAR,
            entidad: 'Proveedor',
            idEntidad: null,
            descripcion: 'Importación masiva de códigos proveedor',
            datosNuevos: array_merge($resultado, ['nom_proveedor' => $validado['nom_proveedor']]),
            request: $request,
        );

        return redirect()
            ->route('proveedor.index')
            ->with(
                'status',
                sprintf(
                    'Importación: %d guardada(s), %d omitida(s).',
                    $resultado['guardadas'],
                    $resultado['omitidas']
                )
            );
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        $proveedor = $this->gridService->crear($request->validated());

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROVEEDOR_CREAR,
            entidad: 'Proveedor',
            idEntidad: $proveedor->idProveedor ?? null,
            descripcion: 'Registro de proveedor creado',
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('proveedor.index')
            ->with('status', 'Registro agregado.');
    }

    public function materialPorCodigo(Request $request): JsonResponse
    {
        $material = $this->gridService->materialPorCodigo((string) $request->query('codigo', ''));

        if (! $material) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json([
            'encontrado' => true,
            'id_material_pl' => $material->idMaterialPL,
            'codigo_pl' => $material->CodigoPL,
            'nom_material' => $material->nomMaterial,
        ]);
    }

    public function materialPorNombre(Request $request): JsonResponse
    {
        $material = $this->gridService->materialPorNombre((string) $request->query('q', ''));

        if (! $material) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json([
            'encontrado' => true,
            'id_material_pl' => $material->idMaterialPL,
            'codigo_pl' => $material->CodigoPL,
            'nom_material' => $material->nomMaterial,
        ]);
    }

    public function update(UpdateProveedorRequest $request, int $nroFila): RedirectResponse
    {
        $this->gridService->actualizarGrupo($nroFila, $request->validated());

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROVEEDOR_ACTUALIZAR,
            entidad: 'Proveedor',
            idEntidad: $nroFila,
            descripcion: 'Registro de proveedor actualizado (fila '.$nroFila.')',
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('proveedor.index')
            ->with('status', 'Registro actualizado.');
    }

    public function destroy(Request $request, int $nroFila): RedirectResponse
    {
        if (! $request->user()?->puede('proveedor', 'eliminar')) {
            abort(403);
        }

        $this->gridService->eliminarGrupo($nroFila);

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROVEEDOR_ELIMINAR,
            entidad: 'Proveedor',
            idEntidad: $nroFila,
            descripcion: 'Registro de proveedor eliminado (fila '.$nroFila.')',
            request: $request,
        );

        return redirect()
            ->route('proveedor.index')
            ->with('status', 'Registro eliminado.');
    }

    /**
     * @return array{familia: mixed, buscar: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'familia' => $request->query('familia'),
            'buscar'  => trim((string) $request->query('buscar', '')),
        ];
    }
}
