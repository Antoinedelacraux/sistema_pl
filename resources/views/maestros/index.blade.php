@extends('layouts.shell')

@section('title', 'Crud')

@section('heading', 'Crud')
@section('crumbs', 'Catálogos · mercados, clientes, materiales y más')

@section('page')
@php
    $tabActiva = $tab;
    $columnaNombre = $definicion['nombre'];
    $pk = $definicion['pk'];
    $extraFields = $definicion['extraFields'] ?? [];
    $tieneExtras = count($extraFields) > 0;
@endphp
<div class="maestros-page">
@include('partials.flash')

<section class="panel panel--maestros">
    <nav class="maestros-nav" aria-label="Catálogos">
        @foreach ($grupos as $grupo)
            <div class="maestros-nav__group">
                <p class="maestros-nav__title">{{ $grupo['label'] }}</p>
                @foreach ($grupo['tabs'] as $clave)
                    @continue(! isset($definiciones[$clave]))
                    <a
                        href="{{ route('maestros.index', array_merge(request()->except(['tab', 'page']), ['tab' => $clave])) }}"
                        class="maestros-nav__link {{ $tabActiva === $clave ? 'is-active' : '' }}"
                        @if ($tabActiva === $clave) aria-current="page" @endif
                    >{{ $definiciones[$clave]['label'] }}</a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="maestros-main">
        <label class="maestros-nav-mobile">
            <span class="maestros-nav-mobile__label">Catálogo</span>
            <select class="field__input maestros-nav-mobile__select" data-maestros-nav aria-label="Catálogo">
                @foreach ($grupos as $grupo)
                    <optgroup label="{{ $grupo['label'] }}">
                        @foreach ($grupo['tabs'] as $clave)
                            @continue(! isset($definiciones[$clave]))
                            <option
                                value="{{ route('maestros.index', array_merge(request()->except(['tab', 'page']), ['tab' => $clave])) }}"
                                @selected($tabActiva === $clave)
                            >{{ $definiciones[$clave]['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>

    <div class="maestros-toolbar">
        <form
            method="GET"
            action="{{ route('maestros.index') }}"
            class="maestros-filters"
            data-grid-filters
            data-clear-url="{{ route('maestros.index', ['tab' => $tabActiva]) }}"
        >
            <input type="hidden" name="tab" value="{{ $tabActiva }}">

            <label class="maestros-filters__field maestros-filters__field--search">
                <span class="maestros-filters__label">Buscar</span>
                <input
                    type="search"
                    name="buscar"
                    class="maestros-filters__input"
                    placeholder="Nombre de {{ strtolower($definicion['label']) }}"
                    autocomplete="off"
                    value="{{ $buscar }}"
                >
            </label>

            <div class="maestros-filters__clear-wrap">
                <button type="button" class="btn btn--outline btn--sm" data-grid-filters-clear>Limpiar</button>
            </div>
        </form>

        @if ($puedeCrear)
            <button type="button" class="btn btn--purple btn--sm" id="maestros-abrir-crear">
                + Agregar {{ $definicion['label'] }}
            </button>
        @endif
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count">{{ number_format($registros->total()) }} {{ strtolower($definicion['labelPlural']) }}</p>
        </div>

        <div class="programas-grid-wrap maestros-grid-wrap">
            <table class="programas-grid maestros-grid">
                <thead>
                    <tr>
                        @if (in_array('CodigoProGeneral', $extraFields))
                            <th class="programas-grid__th" style="width:120px">Código</th>
                        @endif
                        <th class="programas-grid__th maestros-grid__th--nombre">Nombre</th>
                        @if (in_array('fecha', $extraFields))
                            <th class="programas-grid__th" style="width:120px">Fecha</th>
                        @endif
                        <th class="programas-grid__th maestros-grid__th--estado">Estado</th>
                        @if ($puedeEditar || $puedeEliminar)
                            <th class="programas-grid__th maestros-grid__th--actions">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        @php
                            $nombreRegistro = $registro->{$columnaNombre};
                            $estaActivo = $registro->estaActivo();
                        @endphp
                        <tr>
                            @if (in_array('CodigoProGeneral', $extraFields))
                                <td class="maestros-grid__nombre" style="font-variant-numeric:tabular-nums;font-size:11px">{{ $registro->CodigoProGeneral }}</td>
                            @endif
                            <td class="maestros-grid__nombre">{{ $nombreRegistro }}</td>
                            @if (in_array('fecha', $extraFields))
                                <td class="maestros-grid__cell-center" style="font-size:11px;white-space:nowrap">{{ $registro->fecha ? $registro->fecha->format('d/m/Y') : '—' }}</td>
                            @endif
                            <td class="maestros-grid__cell-center">
                                @if ($estaActivo)
                                    <span class="stock-badge stock-badge--ok">Activo</span>
                                @else
                                    <span class="stock-badge stock-badge--muted">Inactivo</span>
                                @endif
                            </td>
                            @if ($puedeEditar || $puedeEliminar)
                                <td class="maestros-grid__actions">
                                    <div class="maestros-grid__action-group">
                                        @if ($puedeEditar)
                                            <span class="maestros-grid__action-slot">
                                                <button
                                                    type="button"
                                                    class="icon-action icon-action--edit maestros-grid__action-btn"
                                                    data-maestros-editar
                                                    data-id="{{ $registro->{$pk} }}"
                                                    data-nombre="{{ $nombreRegistro }}"
                                                    data-estado="{{ $estaActivo ? 1 : 0 }}"
                                                    @if (in_array('CodigoProGeneral', $extraFields))
                                                        data-codigo="{{ $registro->CodigoProGeneral }}"
                                                    @endif
                                                    @if (in_array('fecha', $extraFields))
                                                        data-fecha="{{ $registro->fecha ? $registro->fecha->format('Y-m-d') : '' }}"
                                                    @endif
                                                    title="Editar"
                                                    aria-label="Editar {{ $nombreRegistro }}"
                                                >
                                                    @include('partials.icons.edit')
                                                </button>
                                            </span>
                                        @endif
                                        @if ($puedeEliminar || $puedeEditar)
                                            <span class="maestros-grid__action-slot">
                                                @if ($estaActivo && $puedeEliminar)
                                                    <form
                                                        method="POST"
                                                        action="{{ route('maestros.desactivar', ['tab' => $tabActiva, 'id' => $registro->{$pk}]) }}"
                                                        class="maestros-grid__action-form"
                                                        data-confirm="Se desactivará «{{ $nombreRegistro }}». Los registros existentes no se borran."
                                                        data-confirm-title="Desactivar {{ $definicion['label'] }}"
                                                        data-confirm-ok="Sí, desactivar"
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        @if ($buscar !== '')
                                                            <input type="hidden" name="buscar" value="{{ $buscar }}">
                                                        @endif
                                                        <button
                                                            type="submit"
                                                            class="icon-action icon-action--disable maestros-grid__action-btn"
                                                            title="Desactivar"
                                                            aria-label="Desactivar {{ $nombreRegistro }}"
                                                        >
                                                            @include('partials.icons.user-off')
                                                        </button>
                                                    </form>
                                                @elseif (! $estaActivo && $puedeEditar)
                                                    <form
                                                        method="POST"
                                                        action="{{ route('maestros.activar', ['tab' => $tabActiva, 'id' => $registro->{$pk}]) }}"
                                                        class="maestros-grid__action-form"
                                                        data-confirm="Se reactivará «{{ $nombreRegistro }}»."
                                                        data-confirm-title="Activar {{ $definicion['label'] }}"
                                                        data-confirm-ok="Sí, activar"
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        @if ($buscar !== '')
                                                            <input type="hidden" name="buscar" value="{{ $buscar }}">
                                                        @endif
                                                        <button
                                                            type="submit"
                                                            class="icon-action icon-action--enable maestros-grid__action-btn"
                                                            title="Activar"
                                                            aria-label="Activar {{ $nombreRegistro }}"
                                                        >
                                                            @include('partials.icons.user-check')
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="maestros-grid__action-placeholder" aria-hidden="true"></span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($puedeEditar || $puedeEliminar) ? 3 + count($extraFields) : 2 + count($extraFields) }}" class="data-table__empty">
                                No hay {{ strtolower($definicion['labelPlural']) }} con ese filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', [
            'paginator' => $registros,
            'itemLabel' => strtolower($definicion['labelPlural']),
        ])
    </div>
    </div>
</section>

@include('maestros._modal')
</div>
@endsection

@push('scripts')
<script>
    window.maestrosConfig = {
        tab: @json($tabActiva),
        label: @json($definicion['label']),
        storeUrl: @json(route('maestros.store', $tabActiva)),
        updateUrlTemplate: @json(route('maestros.update', ['tab' => $tabActiva, 'id' => '__ID__'])),
    };
</script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/maestros.js') }}?v=2" defer></script>
@endpush
