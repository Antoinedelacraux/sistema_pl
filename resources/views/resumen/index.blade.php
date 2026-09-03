@extends('layouts.shell')

@section('title', 'Resumen')

@section('heading', 'Resumen de stock')
@section('crumbs', 'Stock · por almacén y material')

@section('page')
@php
    $exportParams = array_filter([
        'almacen' => request('almacen', $filtros['almacen'] ?? null),
        'estado' => request('estado', $filtros['estado'] ?? null),
        'buscar' => request('buscar', $filtros['buscar'] ?? null),
    ], fn ($valor) => filled($valor));
@endphp
<div class="resumen-page">
@include('partials.flash')

@include('partials.kpis-resumen')

<section class="panel panel--resumen">
    <div class="resumen-toolbar">
        <form
            method="GET"
            action="{{ route('resumen.index') }}"
            class="resumen-filters"
            data-grid-filters
            data-clear-url="{{ route('resumen.index') }}"
        >
            <div class="resumen-filters__main">
                <label class="resumen-filters__field">
                    <span class="resumen-filters__label">Almacén</span>
                    <select id="resumen-filtro-almacen" name="almacen" class="resumen-filters__input">
                        <option value="">Todos</option>
                        @foreach ($almacenes as $almacen)
                            <option value="{{ $almacen->codigo }}" @selected(($filtros['almacen'] ?? '') === $almacen->codigo)>
                                {{ $almacen->codigo }} — {{ $almacen->nomAlmacen }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="resumen-filters__field">
                    <span class="resumen-filters__label">Estado</span>
                    <select id="resumen-filtro-estado" name="estado" class="resumen-filters__input">
                        <option value="">Todos</option>
                        <option value="critico" @selected(($filtros['estado'] ?? '') === 'critico')>Crítico</option>
                        <option value="alerta" @selected(($filtros['estado'] ?? '') === 'alerta')>Alerta</option>
                        <option value="saludable" @selected(($filtros['estado'] ?? '') === 'saludable')>Saludable</option>
                    </select>
                </label>

                <label class="resumen-filters__field resumen-filters__field--search">
                    <span class="resumen-filters__label">Buscar</span>
                    <input
                        type="search"
                        id="resumen-buscar"
                        name="buscar"
                        class="resumen-filters__input resumen-filters__input--search"
                        placeholder="Código PL o material"
                        autocomplete="off"
                        value="{{ $filtros['buscar'] ?? '' }}"
                    >
                </label>

                <div class="resumen-filters__clear-wrap">
                    <button type="button" class="btn btn--outline btn--sm" id="resumen-limpiar" data-grid-filters-clear>Limpiar</button>
                </div>
            </div>

            @include('partials.export-actions', [
                'pdfUrl' => route('resumen.export.pdf', $exportParams),
                'excelUrl' => route('resumen.export.excel', $exportParams),
            ])
        </form>
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count" id="resumen-contador">{{ number_format($filas->total()) }} materiales en resumen</p>
        </div>

        <div class="programas-grid-wrap resumen-grid-wrap">
            <table class="programas-grid resumen-grid" id="resumen-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th programas-grid__th--stack resumen-grid__th--code">Cód.<br>planeamiento</th>
                        <th class="programas-grid__th programas-grid__th--stack resumen-grid__th--material">Material planeamiento</th>
                        @foreach ($almacenes as $almacen)
                            <th class="programas-grid__th programas-grid__th--num programas-grid__th--stack">Stock<br>{{ $almacen->codigo }}</th>
                        @endforeach
                        <th class="programas-grid__th programas-grid__th--num">Total</th>
                        <th class="programas-grid__th programas-grid__th--num">Mínimo</th>
                        <th class="programas-grid__th programas-grid__th--num resumen-grid__th--estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr class="resumen-fila">
                            <td class="programas-grid__code">{{ $fila->material->CodigoPL }}</td>
                            <td class="programas-grid__material">{{ $fila->material->nomMaterial }}</td>
                            @foreach ($almacenes as $almacen)
                                @php $stockAlmacen = $fila->stocks[$almacen->codigo] ?? 0; @endphp
                                <td class="programas-grid__qty resumen-stock-cell resumen-stock-cell--{{ $stockAlmacen <= 0 ? 'critico' : 'saludable' }}">{{ number_format($stockAlmacen, 2, '.', '') }}</td>
                            @endforeach
                            <td class="programas-grid__qty resumen-total-cell resumen-stock-cell resumen-stock-cell--{{ $fila->estadoStock }}">{{ number_format($fila->total, 2, '.', '') }}</td>
                            <td class="programas-grid__qty">{{ number_format($fila->stockMinimo, 2, '.', '') }}</td>
                            <td>
                                @if ($fila->estadoStock === 'critico')
                                    <span class="stock-badge stock-badge--critico">Crítico</span>
                                @elseif ($fila->estadoStock === 'alerta')
                                    <span class="stock-badge stock-badge--alerta">Alerta</span>
                                @elseif ($fila->estadoStock === 'saludable')
                                    <span class="stock-badge stock-badge--ok">OK</span>
                                @else
                                    <span class="stock-badge stock-badge--muted">Sin mín.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $almacenes->count() }}" class="data-table__empty">No hay materiales con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $filas, 'itemLabel' => 'materiales'])
    </div>
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
@endpush
