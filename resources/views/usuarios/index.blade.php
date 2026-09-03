@extends('layouts.shell')

@section('title', 'Usuarios')

@section('heading', 'Usuarios')
@section('crumbs', 'Administración · cuentas del sistema')

@section('page')
<div class="usuarios-page">
@include('partials.flash')

@include('partials.kpis-usuarios')

<section class="panel panel--usuarios">
    <div class="usuarios-hero">
        <div class="usuarios-hero__copy">
            <h2 class="usuarios-hero__title">Equipo del sistema</h2>
            <p class="usuarios-hero__text">Administra cuentas, roles y permisos de acceso a los módulos de planeamiento.</p>
        </div>
        <a href="{{ route('usuarios.create') }}" class="btn btn--purple usuarios-hero__cta">
            <span aria-hidden="true">+</span> Nuevo usuario
        </a>
    </div>

    <div class="usuarios-toolbar">
        <form
            method="GET"
            action="{{ route('usuarios.index') }}"
            class="usuarios-filters"
            data-grid-filters
            data-clear-url="{{ route('usuarios.index') }}"
        >
            <div class="usuarios-filters__main">
                <label class="usuarios-filters__field usuarios-filters__field--search">
                    <span class="usuarios-filters__label">Buscar</span>
                    <input
                        type="search"
                        id="usuarios-buscar"
                        name="buscar"
                        class="usuarios-filters__input usuarios-filters__input--search"
                        placeholder="Nombre, correo o usuario"
                        autocomplete="off"
                        value="{{ $filtros['buscar'] ?? '' }}"
                    >
                </label>

                <label class="usuarios-filters__field">
                    <span class="usuarios-filters__label">Estado</span>
                    <select id="usuarios-filtro-estado" name="estado" class="usuarios-filters__input">
                        <option value="">Todos</option>
                        <option value="activo" @selected(($filtros['estado'] ?? '') === 'activo')>Activos</option>
                        <option value="inactivo" @selected(($filtros['estado'] ?? '') === 'inactivo')>Inactivos</option>
                    </select>
                </label>

                <label class="usuarios-filters__field">
                    <span class="usuarios-filters__label">Rol</span>
                    <select id="usuarios-filtro-rol" name="rol" class="usuarios-filters__input">
                        <option value="">Todos</option>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->idRol }}" @selected((string) ($filtros['rol'] ?? '') === (string) $rol->idRol)>{{ $rol->Nombre }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="usuarios-filters__clear-wrap">
                    <button type="button" class="btn btn--outline btn--sm" id="usuarios-limpiar" data-grid-filters-clear>Limpiar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count">{{ number_format($usuarios->count()) }} usuarios en el listado</p>
        </div>

        <div class="programas-grid-wrap usuarios-grid-wrap">
            <table class="programas-grid usuarios-grid" id="usuarios-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th usuarios-grid__th--user">Usuario</th>
                        <th class="programas-grid__th usuarios-grid__th--rol">Rol</th>
                        <th class="programas-grid__th usuarios-grid__th--area">Área</th>
                        <th class="programas-grid__th usuarios-grid__th--cargo">Cargo</th>
                        <th class="programas-grid__th usuarios-grid__th--estado">Estado</th>
                        <th class="programas-grid__th usuarios-grid__th--actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $cuenta)
                        @php
                            $nombre = $cuenta->persona?->nombreCompleto() ?? $cuenta->Usuario;
                            $login = $cuenta->Usuario;
                            $correo = $cuenta->persona?->Correo;
                            $subtitulo = ($correo && mb_strtolower($correo) !== mb_strtolower($login))
                                ? $correo
                                : $login;
                            $iniciales = collect(explode(' ', $nombre))
                                ->filter()
                                ->take(2)
                                ->map(fn ($parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
                                ->implode('') ?: 'U';
                            $tonoAvatar = ($cuenta->idUsuario % 4) + 1;
                            $esAdmin = $cuenta->rol?->Nombre === \App\Models\Rol::ADMINISTRADOR;
                        @endphp
                        <tr class="usuarios-fila">
                            <td>
                                <div class="usuarios-user-cell">
                                    <span class="usuarios-avatar usuarios-avatar--tone-{{ $tonoAvatar }}" aria-hidden="true">{{ $iniciales }}</span>
                                    <div class="usuarios-user-cell__body">
                                        <span class="usuarios-user-cell__name">
                                            @include('partials.usuario-nombre-clic', [
                                                'usuarioId' => $cuenta->idUsuario,
                                                'nombre' => $nombre,
                                            ])
                                        </span>
                                        <span class="usuarios-user-cell__meta">{{ $subtitulo }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="usuarios-grid__cell-center">
                                <span class="usuario-rol {{ $esAdmin ? 'usuario-rol--admin' : 'usuario-rol--default' }}">
                                    {{ $cuenta->rol?->Nombre ?? '—' }}
                                </span>
                            </td>
                            <td class="usuarios-grid__area">{{ $cuenta->persona?->area?->nomArea ?? '—' }}</td>
                            <td class="usuarios-grid__cargo">{{ $cuenta->persona?->cargo?->nomCargo ?? '—' }}</td>
                            <td class="usuarios-grid__cell-center">
                                @if ($cuenta->estaActivo())
                                    <span class="stock-badge stock-badge--ok">Activo</span>
                                @else
                                    <span class="stock-badge stock-badge--muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="usuarios-grid__actions">
                                <div class="usuarios-grid__action-group" role="group" aria-label="Acciones de {{ $nombre }}">
                                    <span class="usuarios-grid__action-slot">
                                        <a
                                            href="{{ route('usuarios.edit', $cuenta) }}"
                                            class="icon-action icon-action--edit usuarios-grid__action-btn"
                                            title="Editar usuario"
                                            aria-label="Editar {{ $nombre }}"
                                        >
                                            @include('partials.icons.edit')
                                        </a>
                                    </span>
                                    <span class="usuarios-grid__action-slot">
                                        @if ($cuenta->estaActivo() && ! $cuenta->is(auth()->user()))
                                            <form
                                                method="POST"
                                                action="{{ route('usuarios.desactivar', $cuenta) }}"
                                                class="usuarios-grid__action-form"
                                                data-confirm="Se desactivará la cuenta de {{ $nombre }}. No podrá iniciar sesión hasta que un administrador la reactive."
                                                data-confirm-title="Desactivar cuenta"
                                                data-confirm-ok="Sí, desactivar"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="icon-action icon-action--disable usuarios-grid__action-btn"
                                                    title="Desactivar cuenta"
                                                    aria-label="Desactivar {{ $nombre }}"
                                                >
                                                    @include('partials.icons.user-off')
                                                </button>
                                            </form>
                                        @elseif (! $cuenta->estaActivo())
                                            <form
                                                method="POST"
                                                action="{{ route('usuarios.activar', $cuenta) }}"
                                                class="usuarios-grid__action-form"
                                                data-confirm="Se reactivará la cuenta de {{ $nombre }} y podrá volver a iniciar sesión."
                                                data-confirm-title="Activar cuenta"
                                                data-confirm-ok="Sí, activar"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="icon-action icon-action--enable usuarios-grid__action-btn"
                                                    title="Activar cuenta"
                                                    aria-label="Activar {{ $nombre }}"
                                                >
                                                    @include('partials.icons.user-check')
                                                </button>
                                            </form>
                                        @else
                                            <span class="usuarios-grid__action-placeholder" aria-hidden="true"></span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="data-table__empty">No hay usuarios con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
@endpush
