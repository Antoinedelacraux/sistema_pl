<?php

namespace App\Http\Controllers;

use App\Http\Requests\Programas\ImportProgramaExcelRequest;
use App\Http\Requests\Programas\StoreProgramaFilaRequest;
use App\Http\Requests\Programas\UpdateProgramaFilaImagenRequest;
use App\Models\Programas\ProgramaFila;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Programas\ProgramaExportService;
use App\Services\Programas\ProgramaGridService;
use App\Services\Programas\ProgramaImportService;
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
        private readonly ProgramaImportService $importService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->gridService->normalizarFiltros($this->filtrosDesdeRequest($request));
        $filas = $this->gridService->filasPaginadas($filtros, $request);

        return view('programas.index', array_merge($this->gridService->catalogos(), [
            'catalogosFiltro' => $this->gridService->catalogosFiltro($filtros),
            'filas' => $filas,
            'filtros' => $filtros,
            'totalFilas' => $filas->total(),
            'totalProgramas' => $this->gridService->totalProgramasDistintos($filtros),
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

    public function importExcel(ImportProgramaExcelRequest $request): RedirectResponse
    {
        try {
            $resultado = $this->importService->importar($request->file('archivo_excel'));
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('programas.index')
                ->with('error', $exception->getMessage());
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROGRAMA_CREAR,
            entidad: 'ProgramaFila',
            idEntidad: null,
            descripcion: 'Importación Excel de receta',
            datosNuevos: $resultado,
            request: $request,
        );

        return redirect()
            ->route('programas.index')
            ->with(
                'status',
                $this->mensajeImportacion($resultado)
            );
    }

    public function vaciarReceta(Request $request): RedirectResponse
    {
        $resultado = $this->gridService->vaciarReceta();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::PROGRAMA_VACIAR,
            entidad: 'ProgramaFila',
            idEntidad: null,
            descripcion: 'Receta vaciada desde la interfaz',
            datosAnteriores: $resultado,
            request: $request,
        );

        return redirect()
            ->route('programas.index')
            ->with(
                'status',
                sprintf(
                    'Receta vaciada: %d fila(s) y %d parámetro(s) de pallets/conformación eliminados. Importe el Excel para cargar la receta nuevamente.',
                    $resultado['filas'],
                    $resultado['params']
                )
            );
    }

    /**
     * @param  array{insertadas: int, duplicadas: int, omitidas: int, total: int}  $resultado
     */
    private function mensajeImportacion(array $resultado): string
    {
        return sprintf(
            'Importación: %d nueva(s), %d duplicada(s) en Excel, %d omitida(s). Total: %d.',
            $resultado['insertadas'],
            $resultado['duplicadas'],
            $resultado['omitidas'],
            $resultado['total']
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
                'programa' => $request->input('programa'),
                'presentacion' => $request->input('presentacion'),
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
     * @return array{mercado: mixed, cliente: mixed, programa_general: mixed, familia: mixed, programa: mixed, presentacion: mixed, q: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'mercado' => $request->query('mercado'),
            'cliente' => $request->query('cliente'),
            'programa_general' => $request->query('programa_general'),
            'familia' => $request->query('familia'),
            'programa' => $request->query('programa'),
            'presentacion' => $request->query('presentacion'),
            'q' => trim((string) $request->query('buscar', '')),
        ];
    }
}
