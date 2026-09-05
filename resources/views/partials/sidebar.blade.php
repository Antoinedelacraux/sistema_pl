@php

    $active = $activeNav ?? '';

    $user = auth()->user();

    $inventarioAlmacen = strtoupper((string) request()->query('almacen', 'SFC'));
    if ($inventarioAlmacen === 'SAFCO') {
        $inventarioAlmacen = 'SFC';
    }

    $almacenesInventario = collect([
        'FC' => 'FRUCHINCHA',
        'SF' => 'SUN FRUIT',
        'SFC' => 'SAFCO',
    ])->sortKeys()->all();



    $navItems = [

        ['key' => 'inicio', 'label' => 'Inicio', 'route' => 'inicio', 'modulo' => 'inicio'],

        ['key' => 'programas', 'label' => 'Receta', 'route' => 'programas.index', 'modulo' => 'programas'],

        ['key' => 'maestros', 'label' => 'Crud', 'route' => 'maestros.index', 'modulo' => 'maestros'],

        ['key' => 'proveedor', 'label' => 'Proveedor', 'route' => 'proveedor.index', 'modulo' => 'proveedor'],

        [

            'key' => 'inventario',

            'label' => 'Inventario',

            'modulo' => 'inventario',

            'submenu' => collect($almacenesInventario)->map(function ($nombre, $codigo) use ($inventarioAlmacen) {

                return [

                    'label' => 'Stock '.$codigo,

                    'route' => route('inventario.index', ['almacen' => $codigo]),

                    'active' => $inventarioAlmacen === $codigo,

                ];

            })->values()->all(),

        ],

        ['key' => 'registro', 'label' => 'Registro', 'route' => 'registro.index', 'modulo' => 'registro'],

        ['key' => 'usuarios', 'label' => 'Usuarios', 'route' => 'usuarios.index', 'modulo' => 'usuarios', 'admin' => true],

        ['key' => 'auditoria', 'label' => 'Auditoría', 'route' => 'auditoria.index', 'modulo' => 'auditoria', 'admin' => true],

    ];



    $inventarioAbierto = $active === 'inventario';

    $nombreSidebar = $user?->Usuario ?? '';
    $rolSidebar = null;

    if ($user) {
        $user->loadMissing(['persona', 'rol']);

        $nombres = trim((string) ($user->persona?->Nombres ?? ''));
        $apellidos = trim((string) ($user->persona?->Apellidos ?? ''));
        $primerNombre = $nombres !== '' ? explode(' ', $nombres)[0] : '';
        $primerApellido = $apellidos !== '' ? explode(' ', $apellidos)[0] : '';
        $nombreSidebar = trim("{$primerNombre} {$primerApellido}") ?: $user->Usuario;
        $rolSidebar = $user->rol?->Nombre;
    }

@endphp



<aside class="sidebar" id="sidebar" aria-label="Menú principal">

    <div class="sidebar__head">

        <a href="{{ route('inicio') }}" class="sidebar__brand" title="Talsa Grape Farms">

            <img

                src="{{ asset('images/logo-talsa-login.png') }}"

                alt="Talsa Grape Farms"

                class="sidebar__logo"

                width="212"

                height="56"

                decoding="async"

            >

        </a>

        <button type="button" class="sidebar__toggle" id="sidebar-toggle" aria-label="Contraer menú" aria-expanded="true">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                <path d="M15 6l-6 6 6 6"/>

            </svg>

        </button>

    </div>



    <nav class="sidebar__nav">

        @foreach ($navItems as $item)

            @if (! empty($item['admin']) && ! $user->esAdministrador())

                @continue

            @endif



            @if (! $user->puedeVer($item['modulo']))

                @continue

            @endif



            @if (! empty($item['submenu']))

                <div class="sidebar__submenu {{ $inventarioAbierto ? 'is-open' : '' }}" data-sidebar-submenu>

                    <button

                        type="button"

                        class="sidebar__link sidebar__link--parent {{ $inventarioAbierto ? 'is-active' : '' }}"

                        data-sidebar-submenu-toggle

                        aria-expanded="{{ $inventarioAbierto ? 'true' : 'false' }}"

                    >

                        <span class="sidebar__link-icon">

                            @include('partials.icons.'.$item['key'])

                        </span>

                        <span class="sidebar__link-label">{{ $item['label'] }}</span>

                        <svg class="sidebar__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                            <path d="M6 9l6 6 6-6"/>

                        </svg>

                    </button>

                    <div class="sidebar__submenu-panel">

                        @foreach ($item['submenu'] as $sub)

                            <a href="{{ $sub['route'] }}" class="sidebar__sublink {{ ! empty($sub['active']) ? 'is-active' : '' }}">

                                <span>{{ $sub['label'] }}</span>

                            </a>

                        @endforeach

                    </div>

                </div>

            @elseif ($item['route'])

                <a

                    href="{{ route($item['route']) }}"

                    class="sidebar__link {{ $active === $item['key'] ? 'is-active' : '' }}"

                    title="{{ $item['label'] }}"

                >

                    <span class="sidebar__link-icon">

                        @include('partials.icons.'.$item['key'])

                    </span>

                    <span class="sidebar__link-label">{{ $item['label'] }}</span>

                </a>

            @else

                <span class="sidebar__link is-disabled" aria-disabled="true" title="{{ $item['label'] }}">

                    <span class="sidebar__link-icon">

                        @include('partials.icons.'.$item['key'])

                    </span>

                    <span class="sidebar__link-label">{{ $item['label'] }}</span>

                </span>

            @endif

        @endforeach

    </nav>

    @if ($user)
        <div class="sidebar__foot">
            <div class="sidebar-foot sidebar-foot--perfil">
                <span class="sidebar-foot__badge sidebar-foot__badge--user" aria-hidden="true">
                    @include('partials.icons.usuario')
                </span>
                <div class="sidebar-foot__copy">
                    <p class="sidebar-foot__title">{{ $nombreSidebar }}</p>
                    @if ($rolSidebar)
                        <p class="sidebar-foot__text">{{ $rolSidebar }}</p>
                    @endif
                </div>
                <span class="sidebar-foot__badge sidebar-foot__badge--config" aria-hidden="true">
                    @include('partials.icons.configuracion')
                </span>
            </div>
        </div>
    @endif

</aside>


