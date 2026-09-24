<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\ParseIngresoExcelRequest;
use App\Http\Requests\Stock\ParseIngresoXmlRequest;
use App\Http\Requests\Stock\StoreEgresoRequest;
use App\Http\Requests\Stock\StoreIngresoRequest;
use App\Http\Requests\Stock\StoreTrasladoRequest;
use App\Http\Requests\Stock\UpdateEgresoRequest;
use App\Http\Requests\Stock\UpdateIngresoRequest;
use App\Http\Requests\Stock\UpdateTrasladoRequest;
use App\Models\Stock\MovimientoStock;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Stock\ConsumoRecetaService;
use App\Services\Stock\GuiaXmlParserService;
use App\Services\Stock\IngresoNotificacionService;
use App\Services\Stock\IngresoStockExcelParserService;
use App\Services\Stock\MovimientoService;
use App\Services\Stock\RegistroExportService;
use App\Support\AuditoriaAccion;
use App\Support\DocumentoExportSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientoController extends Controller
{
    public function __construct(
        private readonly MovimientoService $movimientoService,
        private readonly ConsumoRecetaService $consumoRecetaService,
        private readonly GuiaXmlParserService $guiaXmlParserService,
        private readonly IngresoStockExcelParserService $ingresoStockExcelParserService,
        private readonly RegistroExportService $exportService,
        private readonly IngresoNotificacionService $ingresoNotificacionService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabRaw = (string) $request->query('tab', 'ingresos');
        if ($tabRaw === 'egresos') {
            $tabRaw = 'consumos';
        }
        $tab = in_array($tabRaw, ['ingresos', 'consumos', 'traslados'], true)
            ? $tabRaw
            : 'ingresos';

        $filtros = [
            'fecha' => $request->query('fecha'),
            'id_almacen' => $request->query('almacen'),
            'buscar' => trim((string) $request->query('buscar', '')),
        ];

        $puedeCrear = $user?->puede('registro', 'crear') ?? false;
        $puedeEditar = $user?->puede('registro', 'editar') ?? false;

        $viewData = array_merge(
            // Solo cargamos catálogos de consumo cuando el tab activo los necesita
            $this->movimientoService->catalogosRegistro($tab, $tab === 'consumos' && ($puedeCrear || $puedeEditar)),
            [
            'tab' => $tab,
            'filtros' => $filtros,
            'ingresos' => $tab === 'ingresos'
                ? $this->movimientoService->listarIngresosPaginados($filtros, $request)
                : collect(),
            'consumos' => $tab === 'consumos'
                ? $this->movimientoService->listarEgresosPaginados($filtros, $request)
                : collect(),
            'traslados' => $tab === 'traslados'
                ? $this->movimientoService->listarTrasladosPaginados($filtros, $request)
                : collect(),
            'puedeCrear' => $puedeCrear,
            'puedeEditar' => $puedeEditar,
            'puedeEliminar' => $user?->puede('registro', 'eliminar') ?? false,
            'activeNav' => 'registro',
        ]);

        // Solo materiales referenciados por old() (errores de validación), no el catálogo completo.
        $idsOld = collect(old('lineas', []))
            ->pluck('id_material_pl')
            ->push(old('id_material_pl'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $viewData['materiales'] = $this->movimientoService->materialesPorIds($idsOld);

        return view('registro.index', $viewData);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $tab = $this->tabDesdeRequest($request);

        return $this->exportService->exportarExcel(
            $tab,
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    public function exportPdf(Request $request): Response
    {
        $tab = $this->tabDesdeRequest($request);

        return $this->exportService->exportarPdf(
            $tab,
            $this->filtrosDesdeRequest($request),
            $request->user()
        );
    }

    /**
     * @return array{fecha: mixed, id_almacen: mixed, buscar: string}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'fecha' => $request->query('fecha'),
            'id_almacen' => $request->query('almacen'),
            'buscar' => trim((string) $request->query('buscar', '')),
        ];
    }

    private function tabDesdeRequest(Request $request): string
    {
        $tab = (string) $request->query('tab', 'ingresos');
        if ($tab === 'egresos') {
            $tab = 'consumos';
        }

        return in_array($tab, ['ingresos', 'consumos', 'traslados'], true) ? $tab : 'ingresos';
    }

    public function parseIngresoXml(ParseIngresoXmlRequest $request): JsonResponse
    {
        try {
            $resultado = $this->guiaXmlParserService->parse($request->file('archivo_xml'));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
                'guia' => null,
                'lineas' => [],
                'advertencias' => [],
            ], 422);
        }

        return response()->json($resultado);
    }

    public function parseIngresoExcel(ParseIngresoExcelRequest $request): JsonResponse
    {
        try {
            $resultado = $this->ingresoStockExcelParserService->parse($request->file('archivo_excel'));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
                'id_almacen' => null,
                'codigo_almacen' => null,
                'lineas' => [],
                'advertencias' => [],
            ], 422);
        }

        if (! ($resultado['ok'] ?? false)) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function storeIngreso(StoreIngresoRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $cantidad = $this->movimientoService->crearIngreso(
                $request->validated(),
                $request->user()?->idUsuario,
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            return redirect()
                ->route('registro.index', ['tab' => 'ingresos'])
                ->withErrors(['lineas' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            return redirect()
                ->route('registro.index', ['tab' => 'ingresos'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_INGRESO_CREAR,
            entidad: 'MovimientoStock',
            descripcion: 'Ingreso registrado ('.$cantidad.' '.($cantidad === 1 ? 'línea' : 'líneas').')',
            datosNuevos: $request->validated(),
            request: $request,
        );

        $usuario = $request->user();
        $this->ingresoNotificacionService->encolarSiCorresponde(
            $request->validated(),
            $usuario ? DocumentoExportSupport::nombreExportador($usuario) : null
        );

        $mensaje = $cantidad === 1 ? 'Ingreso registrado.' : "Se registraron {$cantidad} ingresos.";

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $mensaje, 'cantidad' => $cantidad]);
        }

        return redirect()
            ->route('registro.index', ['tab' => 'ingresos'])
            ->with('status', $mensaje);
    }

    public function storeEgreso(StoreEgresoRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $cantidad = $this->movimientoService->crearEgreso(
                $request->validated(),
                $request->user()?->idUsuario,
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            return redirect()
                ->route('registro.index', ['tab' => 'consumos'])
                ->withErrors(['lineas' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            return redirect()
                ->route('registro.index', ['tab' => 'consumos'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_EGRESO_CREAR,
            entidad: 'MovimientoStock',
            descripcion: 'Consumo registrado',
            datosNuevos: $request->validated(),
            request: $request,
        );

        $mensaje = $cantidad === 1 ? 'Consumo registrado.' : "Se registraron {$cantidad} consumos.";

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $mensaje, 'cantidad' => $cantidad]);
        }

        return redirect()
            ->route('registro.index', ['tab' => 'consumos'])
            ->with('status', $mensaje);
    }

    public function storeTraslado(StoreTrasladoRequest $request): RedirectResponse
    {
        try {
            $this->movimientoService->crearTraslado(
                $request->validated(),
                $request->user()?->idUsuario,
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'traslados'])
                ->withErrors(['cantidad' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'traslados'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_TRASLADO_CREAR,
            entidad: 'Traslado',
            descripcion: 'Traslado registrado',
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => 'traslados'])
            ->with('status', 'Traslado registrado.');
    }

    public function updateIngreso(UpdateIngresoRequest $request, int $idMovimiento): RedirectResponse
    {
        try {
            $this->movimientoService->actualizarIngreso(
                $idMovimiento,
                $request->validated(),
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'ingresos'])
                ->withErrors(['lineas' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'ingresos'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_INGRESO_ACTUALIZAR,
            entidad: 'MovimientoStock',
            idEntidad: $idMovimiento,
            descripcion: 'Ingreso actualizado #'.$idMovimiento,
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => 'ingresos'])
            ->with('status', 'Ingreso actualizado.');
    }

    public function updateEgreso(UpdateEgresoRequest $request, int $idMovimiento): RedirectResponse
    {
        try {
            $this->movimientoService->actualizarEgreso(
                $idMovimiento,
                $request->validated(),
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'consumos'])
                ->withErrors(['cantidad' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'consumos'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_EGRESO_ACTUALIZAR,
            entidad: 'MovimientoStock',
            idEntidad: $idMovimiento,
            descripcion: 'Consumo actualizado #'.$idMovimiento,
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => 'consumos'])
            ->with('status', 'Consumo actualizado.');
    }

    public function updateTraslado(UpdateTrasladoRequest $request, int $idTraslado): RedirectResponse
    {
        try {
            $this->movimientoService->actualizarTraslado(
                $idTraslado,
                $request->validated(),
                $request->file('archivo_pdf')
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'traslados'])
                ->withErrors(['cantidad' => $exception->getMessage()])
                ->withInput();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('registro.index', ['tab' => 'traslados'])
                ->withErrors(['archivo_pdf' => $exception->getMessage()])
                ->withInput();
        }

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_TRASLADO_ACTUALIZAR,
            entidad: 'Traslado',
            idEntidad: $idTraslado,
            descripcion: 'Traslado actualizado #'.$idTraslado,
            datosNuevos: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => 'traslados'])
            ->with('status', 'Traslado actualizado.');
    }

    public function destroyMovimiento(Request $request, int $idMovimiento): RedirectResponse
    {
        if (! $request->user()?->puede('registro', 'eliminar')) {
            abort(403);
        }

        $movimiento = MovimientoStock::query()
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $tab = $movimiento->tipo === MovimientoStock::TIPO_EGRESO ? 'consumos' : 'ingresos';

        $this->movimientoService->anularMovimiento($idMovimiento);

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_MOVIMIENTO_ANULAR,
            entidad: 'MovimientoStock',
            idEntidad: $idMovimiento,
            descripcion: 'Movimiento anulado #'.$idMovimiento,
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => $tab])
            ->with('status', $movimiento->tipo === MovimientoStock::TIPO_INGRESO
                ? 'Ingreso anulado.'
                : 'Consumo anulado.');
    }

    public function destroyTraslado(Request $request, int $idTraslado): RedirectResponse
    {
        if (! $request->user()?->puede('registro', 'eliminar')) {
            abort(403);
        }

        $this->movimientoService->anularTraslado($idTraslado);

        $this->auditoria->registrar(
            accion: AuditoriaAccion::REGISTRO_TRASLADO_ANULAR,
            entidad: 'Traslado',
            idEntidad: $idTraslado,
            descripcion: 'Traslado anulado #'.$idTraslado,
            request: $request,
        );

        return redirect()
            ->route('registro.index', ['tab' => 'traslados'])
            ->with('status', 'Traslado anulado.');
    }

    public function materialPorCodigo(Request $request): JsonResponse
    {
        $material = $this->movimientoService->materialPorCodigo((string) $request->query('codigo', ''));

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

    public function buscarMateriales(Request $request): JsonResponse
    {
        return response()->json([
            'materiales' => $this->movimientoService->buscarMateriales(
                (string) $request->query('q', ''),
                (int) $request->query('limite', 50)
            ),
        ]);
    }

    public function stockDisponible(Request $request): JsonResponse
    {
        $idMaterial = (int) $request->query('id_material_pl', 0);
        $idAlmacen = (int) $request->query('id_almacen', 0);

        if ($idMaterial <= 0 || $idAlmacen <= 0) {
            return response()->json(['stock' => 0]);
        }

        return response()->json([
            'stock' => $this->movimientoService->stockDisponible($idMaterial, $idAlmacen),
        ]);
    }

    public function programasConsumo(Request $request): JsonResponse
    {
        $idProgramaGeneral = (int) $request->query('id_programa_general', 0);

        return response()->json([
            'programas' => $this->consumoRecetaService
                ->programasDisponibles($idProgramaGeneral > 0 ? $idProgramaGeneral : null)
                ->map(fn ($item) => [
                    'id_programa' => $item->idPrograma,
                    'nom_programa' => $item->nomPrograma,
                    'id_programa_general' => $item->idProgramaGeneral,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function presentacionesConsumo(Request $request): JsonResponse
    {
        $idPrograma = (int) $request->query('id_programa', 0);

        return response()->json([
            'presentaciones' => $this->consumoRecetaService
                ->presentacionesDePrograma($idPrograma)
                ->map(fn ($item) => [
                    'id_presentacion' => $item->idPresentacion,
                    'nom_presentacion' => $item->nomPresentacion,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function materialesConsumo(Request $request): JsonResponse
    {
        $idPrograma = (int) $request->query('id_programa', 0);
        $idPresentacion = (int) $request->query('id_presentacion', 0);
        $excluir = (int) $request->query('excluir_id_movimiento', 0);
        $idAlmacen = (int) $request->query('id_almacen', 0);

        $excluirIds = $excluir > 0
            ? $this->movimientoService->idsMovimientosLoteEgreso($excluir)
            : [];

        return response()->json([
            'presentacion' => $this->consumoRecetaService->parametrosPresentacion(
                $idPrograma,
                $idPresentacion
            ),
            'materiales' => $this->consumoRecetaService->materialesDePresentacion(
                $idPrograma,
                $idPresentacion,
                $excluirIds !== [] ? $excluirIds : null,
                $idAlmacen > 0 ? $idAlmacen : null
            ),
        ]);
    }

    public function offlineCsrf(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function hintConsumo(Request $request): JsonResponse
    {
        $idPrograma = (int) $request->query('id_programa', 0);
        $idPresentacion = (int) $request->query('id_presentacion', 0);
        $idMaterial = (int) $request->query('id_material_pl', 0);
        $idAlmacen = (int) $request->query('id_almacen', 0);
        $excluir = (int) $request->query('excluir_id_movimiento', 0);

        if ($idPrograma <= 0 || $idPresentacion <= 0 || $idMaterial <= 0 || $idAlmacen <= 0) {
            return response()->json([
                'stock' => 0,
                'cantidad_por_caja' => 0,
                'ya_sacado' => 0,
                'faltante' => 0,
            ]);
        }

        $excluirIds = $excluir > 0
            ? $this->movimientoService->idsMovimientosLoteEgreso($excluir)
            : [];

        return response()->json(
            $this->consumoRecetaService->hintConsumo(
                $idPrograma,
                $idPresentacion,
                $idMaterial,
                $idAlmacen,
                $excluirIds
            )
        );
    }

    public function detalleMovimiento(Request $request, int $idMovimiento): JsonResponse
    {
        $contextoEdicion = $request->query('contexto') === 'edicion';

        return response()->json(
            $this->movimientoService->detalleMovimiento($idMovimiento, $contextoEdicion)
        );
    }

    public function detalleTraslado(int $idTraslado): JsonResponse
    {
        return response()->json(
            $this->movimientoService->detalleTraslado($idTraslado)
        );
    }
}
