@extends('layouts.shell')

@section('title', 'Inventario')

@section('heading', 'Inventario · '.$tabActiva)
@section('crumbs', ($almacenActivo->nomAlmacen ?? $tabActiva).' · materiales con stock')

@push('head')
<link rel="stylesheet" href="{{ asset('css/inventario.css') }}?v=1">
@endpush

@section('page')
@php
    $exportParams = array_filter([
        'almacen' => $tabActiva,
        'estado' => request('estado', $filtros['estado'] ?? 'con_stock'),
        'buscar' => request('buscar'),
    ], fn ($valor) => filled($valor));
@endphp
<div class="inventario-page">
@include('partials.flash')

<section class="panel panel--inventario inventario-panel">
    <div class="inventario-toolbar">
        <form
            method="GET"
            action="{{ route('inventario.index') }}"
            class="inventario-filters"
            data-grid-filters
            data-clear-url="{{ route('inventario.index', ['almacen' => $tabActiva]) }}"
        >
            <input type="hidden" name="almacen" value="{{ $tabActiva }}">

            <div class="inventario-filters__main">
                <label class="inventario-filters__field">
                    <span class="inventario-filters__label">Estado</span>
                    <select id="inventario-filtro-estado" name="estado" class="inventario-filters__input">
                        <option value="con_stock" @selected(($filtros['estado'] ?? 'con_stock') === 'con_stock')>Con stock</option>
                        <option value="critico" @selected(($filtros['estado'] ?? '') === 'critico')>Crítico</option>
                        <option value="alerta" @selected(($filtros['estado'] ?? '') === 'alerta')>Alerta</option>
                        <option value="saludable" @selected(($filtros['estado'] ?? '') === 'saludable')>Saludable</option>
                    </select>
                </label>

                <label class="inventario-filters__field inventario-filters__field--search">
                    <span class="inventario-filters__label">Buscar</span>
                    <input
                        type="search"
                        id="inventario-buscar"
                        name="buscar"
                        class="inventario-filters__input inventario-filters__input--search"
                        placeholder="Código PL o material"
                        autocomplete="off"
                        value="{{ $filtros['buscar'] ?? '' }}"
                    >
                </label>

                <div class="inventario-filters__clear-wrap">
                    <button type="button" class="btn btn--outline btn--sm inventario-filters__clear" id="inventario-limpiar" data-grid-filters-clear>
                        Limpiar
                    </button>
                </div>
            </div>

            @include('partials.export-actions', [
                'pdfUrl' => route('inventario.export.pdf', $exportParams),
                'excelUrl' => route('inventario.export.excel', $exportParams),
            ])
        </form>
    </div>

    @include('partials.kpis-inventario')

    <div class="grid-table-section inventario-table-section">
        <div class="grid-table-head inventario-table-head">
            <p class="grid-table-count inventario-table-count" id="inventario-contador">
                {{ number_format($filas->total()) }} materiales en {{ $almacenActivo?->nomAlmacen ?? $tabActiva }}
            </p>
        </div>

        <div class="programas-grid-wrap inventario-grid-wrap">
            <table class="programas-grid inventario-grid" id="inventario-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th programas-grid__th--stack inventario-grid__th--code">Cód.<br>planeamiento</th>
                        <th class="programas-grid__th inventario-grid__th--material">Material planeamiento</th>
                        <th class="programas-grid__th programas-grid__th--num">Stock</th>
                        <th class="programas-grid__th programas-grid__th--num">Mínimo</th>
                        <th class="programas-grid__th programas-grid__th--num inventario-grid__th--estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr
                            class="inventario-fila"
                            data-estado="{{ $fila->estadoStock }}"
                            data-stock="{{ $fila->stock }}"
                            data-codigo="{{ $fila->CodigoPL }}"
                            data-material="{{ $fila->nomMaterial }}"
                        >
                            <td class="programas-grid__code">{{ $fila->CodigoPL }}</td>
                            <td class="programas-grid__material">{{ $fila->nomMaterial }}</td>
                            <td class="programas-grid__qty inventario-stock-cell inventario-stock-cell--{{ $fila->estadoStock }}">{{ number_format($fila->stock, 2, '.', '') }}</td>
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
                    @endforeach
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
