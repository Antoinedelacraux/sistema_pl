<?php

namespace App\Http\Controllers;

use App\Http\Requests\Programas\StoreProgramaFilaRequest;
use App\Http\Requests\Programas\UpdateProgramaFilaImagenRequest;
use App\Models\Programas\ProgramaFila;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Programas\ProgramaExportService;
use App\Services\Programas\ProgramaGridService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramaController extends Controller
{
    public function __construct(
        private readonly ProgramaGridService $gridService,
        private readonly ProgramaExportService $exportService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $catalogos = $this->gridService->catalogos();
        $filtros = $this->filtrosDesdeRequest($request);
        $filas = $this->gridService->filasPaginadas($filtros, $request);

        return view('programas.index', array_merge($catalogos, [
            'filas' => $filas,
            'filtros' => $filtros,
            'totalFilas' => $filas->total(),
            'puedeCrear' => $request->user()?->puede('programas', 'crear') ?? false,
            'puedeEditar' => $request->user()?->puede('programas', 'editar') ?? false,
            'activeNav' => 'programas',
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

    public function store(StoreProgramaFilaRequest $request): RedirectResponse
    {
        $fila = $this->gridService->crearFila(
            $request->validated(),
            $request->file('imagen')
        );

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROGRAMA_CREAR,
            entidad: 'ProgramaFila',
            idEntidad: $fila->idProgramaFila ?? null,
            descripcion: 'Fila agregada a la receta',
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('programas.index')
            ->with('status', 'Registro agregado a la receta.');
    }

    public function updateImagen(UpdateProgramaFilaImagenRequest $request, int $idProgramaFila): RedirectResponse
    {
        $imagenAnterior = ProgramaFila::query()
            ->whereKey($idProgramaFila)
            ->value('imagen');

        $fila = $this->gridService->actualizarImagen(
            $idProgramaFila,
            $request->file('imagen'),
            $request->boolean('eliminar_imagen')
        );

        $mensaje = match (true) {
            $request->boolean('eliminar_imagen') => 'Imagen eliminada de la receta.',
            $request->file('imagen') !== null => 'Imagen actualizada en la receta.',
            default => 'No se realizaron cambios en la imagen.',
        };

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROGRAMA_IMAGEN_ACTUALIZAR,
            entidad: 'ProgramaFila',
            idEntidad: $fila->idProgramaFila,
            descripcion: $mensaje,
            datosAnteriores: ['imagen' => $imagenAnterior],
            datosNuevos: ['imagen' => $fila->imagen],
            request: $request,
        );

        return redirect()
            ->route('programas.index', array_filter([
                'mercado' => $request->input('mercado'),
                'cliente' => $request->input('cliente'),
                'familia' => $request->input('familia'),
                'buscar' => $request->input('buscar'),
            ], fn ($valor) => filled($valor)))
            ->with('status', $mensaje);
    }

    public function materialPorCodigo(Request $request): JsonResponse
    {
        $codigo = (string) $request->query('codigo', '');
        $material = $this->gridService->materialPorCodigo($codigo);

        if (! $material) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json([
            'encontrado' => true,
            'codigo_pl' => $material->CodigoPL,
            'nom_material' => $material->nomMaterial,
        ]);
    }

    /**
     * @return array{mercado: mixed, cliente: mixed, familia: mixed, q: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'mercado' => $request->query('mercado'),
            'cliente' => $request->query('cliente'),
            'familia' => $request->query('familia'),
            'q' => trim((string) $request->query('buscar', '')),
        ];
    }
}
