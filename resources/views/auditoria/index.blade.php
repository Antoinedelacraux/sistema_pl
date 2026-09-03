@extends('layouts.shell')

@section('title', 'Auditoría')

@section('heading', 'Auditoría')
@section('crumbs', 'Seguridad · registro de acciones del sistema')

@section('page')
<div class="auditoria-page">
@include('partials.flash')

@include('partials.kpis-auditoria')

<section class="panel panel--auditoria">
    <div class="auditoria-hero">
        <div class="auditoria-hero__copy">
            <h2 class="auditoria-hero__title">Registro de actividad</h2>
            <p class="auditoria-hero__text">Consulta accesos, cambios y operaciones realizadas por los usuarios del sistema.</p>
        </div>
    </div>

    <div class="auditoria-toolbar">
        <form
            method="GET"
            action="{{ route('auditoria.index') }}"
            class="auditoria-filters"
            data-grid-filters
            data-clear-url="{{ route('auditoria.index') }}"
        >
            <div class="auditoria-filters__main">
                <label class="auditoria-filters__field auditoria-filters__field--search">
                    <span class="auditoria-filters__label">Buscar</span>
                    <input
                        type="search"
                        id="auditoria-buscar"
                        name="buscar"
                        class="auditoria-filters__input auditoria-filters__input--search"
                        placeholder="Usuario, IP, descripción…"
                        autocomplete="off"
                        value="{{ $filtros['buscar'] ?? '' }}"
                    >
                </label>

                <label class="auditoria-filters__field">
                    <span class="auditoria-filters__label">Acción</span>
                    <select id="auditoria-filtro-accion" name="accion" class="auditoria-filters__input">
                        <option value="">Todas</option>
                        @foreach ($acciones as $codigo => $etiqueta)
                            <option value="{{ $codigo }}" @selected(($filtros['accion'] ?? '') === $codigo)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="auditoria-filters__field">
                    <span class="auditoria-filters__label">Usuario</span>
                    <select id="auditoria-filtro-usuario" name="usuario" class="auditoria-filters__input">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $usuario)
                            @php
                                $nombreUsuario = $usuario->persona?->nombreCompleto() ?? $usuario->Usuario;
                            @endphp
                            <option value="{{ $usuario->idUsuario }}" @selected((string) ($filtros['usuario'] ?? '') === (string) $usuario->idUsuario)>
                                {{ $nombreUsuario }} ({{ $usuario->Usuario }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="auditoria-filters__field">
                    <span class="auditoria-filters__label">Desde</span>
                    <input
                        type="date"
                        id="auditoria-fecha-desde"
                        name="fecha_desde"
                        class="auditoria-filters__input"
                        value="{{ $filtros['fecha_desde'] ?? '' }}"
                    >
                </label>

                <label class="auditoria-filters__field">
                    <span class="auditoria-filters__label">Hasta</span>
                    <input
                        type="date"
                        id="auditoria-fecha-hasta"
                        name="fecha_hasta"
                        class="auditoria-filters__input"
                        value="{{ $filtros['fecha_hasta'] ?? '' }}"
                    >
                </label>

                <div class="auditoria-filters__clear-wrap">
                    <button type="button" class="btn btn--outline btn--sm" id="auditoria-limpiar" data-grid-filters-clear>Limpiar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count">{{ number_format($registros->total()) }} eventos registrados</p>
        </div>

        <div class="programas-grid-wrap auditoria-grid-wrap">
            <table class="programas-grid auditoria-grid" id="auditoria-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th auditoria-grid__th--fecha">Fecha y hora</th>
                        <th class="programas-grid__th auditoria-grid__th--usuario">Usuario</th>
                        <th class="programas-grid__th auditoria-grid__th--accion">Acción</th>
                        <th class="programas-grid__th auditoria-grid__th--detalle">Detalle</th>
                        <th class="programas-grid__th auditoria-grid__th--ip">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $log)
                        @php
                            $actor = $log->usuario;
                            $nombreActor = $actor?->persona?->nombreCompleto() ?? $actor?->Usuario ?? 'Sistema';
                            $accionClase = match (true) {
                                str_contains($log->accion, 'fallido') => 'auditoria-accion--danger',
                                in_array($log->accion, ['login', 'logout'], true) => 'auditoria-accion--session',
                                str_contains($log->accion, 'anular') || str_contains($log->accion, 'eliminar') || str_contains($log->accion, 'desactivar') => 'auditoria-accion--warn',
                                str_contains($log->accion, 'crear') => 'auditoria-accion--create',
                                default => 'auditoria-accion--default',
                            };
                        @endphp
                        <tr class="auditoria-fila">
                            <td class="auditoria-grid__fecha">
                                <span class="auditoria-grid__fecha-dia">{{ $log->fechaRegistro?->format('d/m/Y') }}</span>
                                <span class="auditoria-grid__fecha-hora">{{ $log->fechaRegistro?->format('H:i:s') }}</span>
                            </td>
                            <td>
                                <div class="auditoria-user-cell">
                                    <span class="auditoria-user-cell__name">
                                        @include('partials.usuario-nombre-clic', [
                                            'usuarioId' => $actor?->idUsuario,
                                            'nombre' => $nombreActor,
                                        ])
                                    </span>
                                    @if ($actor)
                                        <span class="auditoria-user-cell__login">{{ $actor->Usuario }}</span>
                                    @else
                                        <span class="auditoria-user-cell__login">Sin sesión</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="auditoria-accion {{ $accionClase }}">
                                    {{ \App\Support\AuditoriaAccion::etiqueta($log->accion) }}
                                </span>
                            </td>
                            <td class="auditoria-grid__detalle">
                                @if ($log->descripcion)
                                    <span class="auditoria-grid__descripcion">{{ $log->descripcion }}</span>
                                @endif
                                @if ($log->entidad)
                                    <span class="auditoria-grid__meta">{{ $log->entidad }}@if ($log->idEntidad) #{{ $log->idEntidad }}@endif</span>
                                @endif
                            </td>
                            <td class="auditoria-grid__ip">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="data-table__empty">No hay eventos con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $registros, 'itemLabel' => 'eventos'])
    </div>
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
@endpush
