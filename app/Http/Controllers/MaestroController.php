<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maestros\StoreMaestroRequest;
use App\Http\Requests\Maestros\UpdateMaestroRequest;
use App\Services\Maestros\MaestroCatalogoService;
use App\Support\MaestrosCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MaestroController extends Controller
{
    public function __construct(
        private readonly MaestroCatalogoService $catalogoService
    ) {}

    public function index(Request $request): View
    {
        $tab = MaestrosCatalogo::normalizarTab($request->query('tab'));
        $definicion = MaestrosCatalogo::definicion($tab);
        $buscar = trim((string) $request->query('buscar', ''));
        $user = $request->user();

        return view('maestros.index', [
            'tab' => $tab,
            'definicion' => $definicion,
            'definiciones' => MaestrosCatalogo::definiciones(),
            'registros' => $this->catalogoService->listar($tab, $buscar, $request),
            'buscar' => $buscar,
            'puedeCrear' => $user?->puede('maestros', 'crear') ?? false,
            'puedeEditar' => $user?->puede('maestros', 'editar') ?? false,
            'puedeEliminar' => $user?->puede('maestros', 'eliminar') ?? false,
            'activeNav' => 'maestros',
        ]);
    }

    public function store(StoreMaestroRequest $request, string $tab): RedirectResponse
    {
        $tab = MaestrosCatalogo::normalizarTab($tab);
        $definicion = MaestrosCatalogo::definicion($tab);
        $extras = $this->extrasValidados($request->validated(), $definicion['extraFields'] ?? []);

        try {
            $this->catalogoService->crear($tab, $request->validated('nombre'), $extras);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('maestros.index', ['tab' => $tab])
                ->withInput()
                ->withErrors(['nombre' => $exception->getMessage()]);
        }

        return redirect()
            ->route('maestros.index', ['tab' => $tab])
            ->with('status', $definicion['label'].' creado correctamente.');
    }

    public function update(UpdateMaestroRequest $request, string $tab, int $id): RedirectResponse
    {
        $tab = MaestrosCatalogo::normalizarTab($tab);
        $definicion = MaestrosCatalogo::definicion($tab);
        $datos = $request->validated();
        $extras = $this->extrasValidados($datos, $definicion['extraFields'] ?? []);

        if ((int) $datos['estado'] === 0 && ($mensaje = $this->catalogoService->mensajeEnUso($tab, $id)) !== null) {
            return redirect()
                ->route('maestros.index', ['tab' => $tab, 'buscar' => $request->input('buscar')])
                ->withErrors(['estado' => $mensaje]);
        }

        $this->catalogoService->actualizar($tab, $id, $datos['nombre'], (int) $datos['estado'], $extras);

        return redirect()
            ->route('maestros.index', ['tab' => $tab, 'buscar' => $request->input('buscar')])
            ->with('status', MaestrosCatalogo::definicion($tab)['label'].' actualizado.');
    }

    public function desactivar(Request $request, string $tab, int $id): RedirectResponse
    {
        if (! $request->user()?->puede('maestros', 'eliminar')) {
            abort(403);
        }

        $tab = MaestrosCatalogo::normalizarTab($tab);
        $buscar = $request->input('buscar');

        try {
            $this->catalogoService->desactivar($tab, $id);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('maestros.index', ['tab' => $tab, 'buscar' => $buscar])
                ->withErrors(['catalogo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('maestros.index', ['tab' => $tab, 'buscar' => $buscar])
            ->with('status', MaestrosCatalogo::definicion($tab)['label'].' desactivado.');
    }

    public function activar(Request $request, string $tab, int $id): RedirectResponse
    {
        if (! $request->user()?->puede('maestros', 'editar')) {
            abort(403);
        }

        $tab = MaestrosCatalogo::normalizarTab($tab);
        $buscar = $request->input('buscar');
        $this->catalogoService->activar($tab, $id);

        return redirect()
            ->route('maestros.index', ['tab' => $tab, 'buscar' => $buscar])
            ->with('status', MaestrosCatalogo::definicion($tab)['label'].' activado.');
    }

    /**
     * Extrae sólo los campos extra definidos en la definición de catálogo.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $claves
     * @return array<string, mixed>
     */
    private function extrasValidados(array $datos, array $claves): array
    {
        $extras = [];
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $datos)) {
                $extras[$clave] = $datos[$clave];
            }
        }

        return $extras;
    }
}
