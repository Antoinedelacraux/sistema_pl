<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuarios\StoreUsuarioRequest;
use App\Http\Requests\Usuarios\UpdateUsuarioRequest;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Permisos\PermisoService;
use App\Services\Usuarios\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $usuarioService,
        private readonly PermisoService $permisoService
    ) {}

    public function index(Request $request): View
    {
        $todos = Usuario::query()
            ->with(['persona.area', 'persona.cargo', 'rol'])
            ->orderByDesc('idUsuario')
            ->get();

        $filtros = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estado' => (string) $request->query('estado', ''),
            'rol' => (string) $request->query('rol', ''),
        ];

        return view('usuarios.index', [
            'usuarios' => $this->filtrarUsuarios($todos, $filtros)->values(),
            'contadores' => $this->contadoresUsuarios($todos),
            'roles' => Rol::query()->activo()->orderBy('Nombre')->get(),
            'filtros' => $filtros,
            'activeNav' => 'usuarios',
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', array_merge(
            $this->catalogos(),
            $this->permisosForm(null),
            ['activeNav' => 'usuarios']
        ));
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $this->usuarioService->crear($request->validated(), $request);

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuario creado correctamente.');
    }

    public function edit(Usuario $usuario): View
    {
        $usuario->load(['persona.area', 'persona.cargo', 'rol', 'permisos.modulo']);

        return view('usuarios.edit', array_merge(
            $this->catalogos(),
            $this->permisosForm($usuario),
            [
                'cuenta' => $usuario,
                'activeNav' => 'usuarios',
            ]
        ));
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $this->usuarioService->actualizar($usuario, $request->validated(), $request);

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuario actualizado.');
    }

    public function desactivar(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return redirect()
                ->route('usuarios.index')
                ->withErrors(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }

        if (! $usuario->estaActivo()) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', 'La cuenta ya está inactiva.');
        }

        $this->usuarioService->desactivar($usuario, $request);

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Cuenta desactivada correctamente.');
    }

    public function activar(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->estaActivo() && $usuario->persona?->estaActivo()) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', 'La cuenta ya está activa.');
        }

        $this->usuarioService->activar($usuario, $request);

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Cuenta activada correctamente.');
    }

    /**
     * @param  Collection<int, Usuario>  $usuarios
     * @return array{total: int, activos: int, inactivos: int, administradores: int}
     */
    private function contadoresUsuarios(Collection $usuarios): array
    {
        return [
            'total' => $usuarios->count(),
            'activos' => $usuarios->filter(fn (Usuario $usuario) => $usuario->estaActivo())->count(),
            'inactivos' => $usuarios->reject(fn (Usuario $usuario) => $usuario->estaActivo())->count(),
            'administradores' => $usuarios
                ->filter(fn (Usuario $usuario) => $usuario->rol?->Nombre === Rol::ADMINISTRADOR)
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, Usuario>  $usuarios
     * @param  array{buscar: string, estado: string, rol: string}  $filtros
     * @return Collection<int, Usuario>
     */
    private function filtrarUsuarios(Collection $usuarios, array $filtros): Collection
    {
        if ($filtros['buscar'] !== '') {
            $termino = mb_strtolower($filtros['buscar']);

            $usuarios = $usuarios->filter(function (Usuario $usuario) use ($termino) {
                $nombre = mb_strtolower($usuario->persona?->nombreCompleto() ?? '');
                $correo = mb_strtolower($usuario->persona?->Correo ?? '');
                $login = mb_strtolower($usuario->Usuario ?? '');

                return str_contains($nombre, $termino)
                    || str_contains($correo, $termino)
                    || str_contains($login, $termino);
            });
        }

        if ($filtros['estado'] === 'activo') {
            $usuarios = $usuarios->filter(fn (Usuario $usuario) => $usuario->estaActivo());
        } elseif ($filtros['estado'] === 'inactivo') {
            $usuarios = $usuarios->reject(fn (Usuario $usuario) => $usuario->estaActivo());
        }

        if ($filtros['rol'] !== '') {
            $usuarios = $usuarios->where('idRol', (int) $filtros['rol']);
        }

        return $usuarios;
    }

    private function catalogos(): array
    {
        return [
            'areas' => Area::query()->activo()->orderBy('nomArea')->get(),
            'cargos' => Cargo::query()->activo()->orderBy('nomCargo')->get(),
            'roles' => Rol::query()->activo()->orderBy('Nombre')->get(),
        ];
    }

    private function permisosForm(?Usuario $cuenta): array
    {
        if (is_array(old('permisos'))) {
            $matriz = $this->permisoService->matrizDesdeRequest(old('permisos'));
        } elseif ($cuenta) {
            $matriz = $this->permisoService->matrizParaUsuario($cuenta);
        } else {
            $matriz = [];
        }

        return [
            'modulos' => $this->permisoService->modulosActivos(),
            'permisosMatriz' => $matriz,
            'permisosAcciones' => config('permisos.acciones'),
            'permisosPorRolJson' => $this->permisoService->defaultsPorRolJson(),
        ];
    }

    /**
     * Tarjeta de perfil (popover). Se usa en Usuarios, Auditoría y en el detalle de Registro,
     * así que se permite: la propia tarjeta, administradores y quien vea usuarios/auditoría/registro.
     */
    public function tarjeta(Request $request, Usuario $usuario): JsonResponse
    {
        $actual = $request->user();
        $permitido = $actual !== null && (
            (int) $actual->idUsuario === (int) $usuario->idUsuario
            || $actual->esAdministrador()
            || $actual->puede('usuarios', 'ver')
            || $actual->puede('auditoria', 'ver')
            || $actual->puede('registro', 'ver')
        );

        abort_unless($permitido, 403, 'No tienes permiso para ver este perfil.');

        $usuario->load(['persona.area', 'persona.cargo', 'rol']);

        $persona = $usuario->persona;
        $nombre = $persona?->nombreCompleto() ?: $usuario->Usuario;
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $iniciales = collect($partes)
            ->filter()
            ->take(2)
            ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('') ?: 'U';

        return response()->json([
            'id' => $usuario->idUsuario,
            'nombre' => $nombre,
            'usuario' => $usuario->Usuario,
            'correo' => $persona?->Correo,
            'area' => $persona?->area?->nomArea,
            'cargo' => $persona?->cargo?->nomCargo,
            'rol' => $usuario->rol?->Nombre,
            'iniciales' => $iniciales,
            'activo' => $usuario->estaActivo(),
        ]);
    }
}
