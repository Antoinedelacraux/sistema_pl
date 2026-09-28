@extends('layouts.shell')

@section('title', 'Inicio')

@section('heading', 'Inicio')
@section('crumbs', 'Stock · por almacén y material')

@section('page')
@php
    $exportParams = array_filter([
        'programa' => request('programa', $filtros['programa'] ?? null),
        'producto' => request('producto', $filtros['producto'] ?? null),
        'estado' => request('estado', $filtros['estado'] ?? null),
        'buscar' => request('buscar', $filtros['buscar'] ?? null),
        'cajas' => request('cajas', $filtros['cajas'] ?? null),
        'pallets' => request('pallets', $filtros['pallets'] ?? null),
    ], fn ($valor) => filled($valor));
    $programaSeleccionado = (int) ($filtros['programa'] ?? 0);
    $productoSeleccionado = (int) ($filtros['producto'] ?? 0);
    $tieneCajas = filled($filtros['cajas'] ?? null);
    $tienePallets = ($filtros['pallets'] ?? null) !== null && ($filtros['pallets'] ?? '') !== '';
    $simulaCajas = $productoSeleccionado > 0 && ($tieneCajas || $tienePallets);
@endphp
<div class="resumen-page">
@include('partials.flash')

{{-- @include('partials.kpis-resumen') --}}

<section class="panel panel--resumen">
    <div class="resumen-toolbar">
        <form
            method="GET"
            action="{{ route('inicio') }}"
            class="resumen-filters"
            data-grid-filters
            data-clear-url="{{ route('inicio') }}"
        >
            <div class="resumen-filters__main">
                <label class="resumen-filters__field">
                    <span class="resumen-filters__label">Programa</span>
                    <select id="inicio-filtro-programa" name="programa" class="resumen-filters__input">
                        <option value="">Todos</option>
                        @foreach ($programas as $programa)
                            <option value="{{ $programa->idPrograma }}" @selected($programaSeleccionado === (int) $programa->idPrograma)>
                                {{ $programa->nomPrograma }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="resumen-filters__field">
                    <span class="resumen-filters__label">Producto</span>
                    <select id="inicio-filtro-producto" name="producto" class="resumen-filters__input" @disabled($programaSeleccionado <= 0)>
                        @if ($programaSeleccionado <= 0)
                            <option value="">Seleccione un programa</option>
                        @else
                            <option value="">Todos</option>
                            @foreach ($productos as $producto)
                                <option value="{{ $producto->idPresentacion }}" @selected((int) ($filtros['producto'] ?? 0) === (int) $producto->idPresentacion)>
                                    {{ $producto->nomPresentacion }}
                                </option>
                            @endforeach
                        @endif
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
                    <label class="resumen-filters__field resumen-filters__field--cajas">
                        <span class="resumen-filters__label">Caj.</span>
                        <input
                            type="number"
                            id="inicio-filtro-cajas"
                            name="cajas"
                            class="resumen-filters__input resumen-filters__input--cajas"
                            min="0.01"
                            step="0.01"
                            placeholder="—"
                            value="{{ $filtros['cajas'] ?? '' }}"
                            title="Simular consumo para N cajas (requiere programa y producto específico)"
                        >
                    </label>
                    <label class="resumen-filters__field resumen-filters__field--cajas">
                        <span class="resumen-filters__label">Pall.</span>
                        <input
                            type="number"
                            id="inicio-filtro-pallets"
                            name="pallets"
                            class="resumen-filters__input resumen-filters__input--cajas"
                            min="0"
                            step="0.01"
                            placeholder="—"
                            value="{{ $filtros['pallets'] ?? '' }}"
                            title="Simular pallets para Temperature Recorder, parihuelas, tapas y pallets (requiere programa y producto)"
                        >
                    </label>
                    <button type="button" class="btn btn--outline btn--sm" id="resumen-limpiar" data-grid-filters-clear>Limpiar</button>
                </div>
            </div>

            @include('partials.export-actions', [
                'pdfUrl' => route('inicio.export.pdf', $exportParams),
                'excelUrl' => route('inicio.export.excel', $exportParams),
            ])
        </form>
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count" id="resumen-contador">{{ number_format($filas->total()) }} materiales</p>
        </div>

        <div class="programas-grid-wrap resumen-grid-wrap">
            <table class="programas-grid resumen-grid" id="resumen-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th programas-grid__th--stack resumen-grid__th--code">Cód.<br>planeamiento</th>
                        <th class="programas-grid__th programas-grid__th--stack resumen-grid__th--variedad">Variedad</th>
                        <th class="programas-grid__th programas-grid__th--stack resumen-grid__th--material">Material planeamiento</th>
                        @foreach ($almacenes as $almacen)
                            <th class="programas-grid__th programas-grid__th--num programas-grid__th--stack">Stock<br>{{ $almacen->codigo }}</th>
                        @endforeach
                        <th class="programas-grid__th programas-grid__th--num">Total</th>
                        @if ($simulaCajas)
                            <th class="programas-grid__th programas-grid__th--num programas-grid__th--stack" title="Cantidad necesaria para las cajas indicadas">Neces.<br>cajas</th>
                        @endif
                        <th class="programas-grid__th programas-grid__th--num">Mínimo</th>
                        <th class="programas-grid__th programas-grid__th--num resumen-grid__th--estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        @php
                            $clasesFila = ['resumen-fila'];
                            if ($fila->estadoStock === 'critico') {
                                $clasesFila[] = 'resumen-fila--stock-cero';
                            }
                        @endphp
                        <tr @class($clasesFila)>
                            <td class="programas-grid__code">{{ $fila->material->CodigoPL }}</td>
                            <td class="programas-grid__cell resumen-grid__variedad">{{ $fila->variedad !== '' ? $fila->variedad : '—' }}</td>
                            <td class="programas-grid__material">{{ $fila->material->nomMaterial }}</td>
                            @foreach ($almacenes as $almacen)
                                @php
                                    $stockAlmacen = $fila->stocks[$almacen->codigo] ?? 0;
                                    $claseStock = 'programas-grid__qty resumen-stock-cell';
                                    if ($simulaCajas && $fila->necesario > 0) {
                                        $claseStock .= $stockAlmacen >= $fila->necesario
                                            ? ' resumen-stock-cell--sim-ok'
                                            : ' resumen-stock-cell--sim-falta';
                                    } elseif ($stockAlmacen <= 0 && $fila->estadoStock === 'critico') {
                                        $claseStock .= ' resumen-stock-cell--critico';
                                    } elseif ($stockAlmacen > 0) {
                                        $claseStock .= ' resumen-stock-cell--saludable';
                                    }
                                @endphp
                                <td class="{{ $claseStock }}">{{ number_format($stockAlmacen, 2, '.', ',') }}</td>
                            @endforeach
                            <td class="programas-grid__qty resumen-total-cell resumen-stock-cell resumen-stock-cell--{{ $fila->estadoStock }}">{{ number_format($fila->total, 2, '.', ',') }}</td>
                            @if ($simulaCajas)
                                <td class="programas-grid__qty resumen-necesario-cell">
                                    {{ $fila->necesario > 0 ? number_format($fila->necesario, 2, '.', ',') : '—' }}
                                </td>
                            @endif
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
                            <td colspan="{{ 6 + $almacenes->count() + ($simulaCajas ? 1 : 0) }}" class="data-table__empty">No hay materiales con esos filtros.</td>
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
<script src="{{ asset('js/inicio-filters.js') }}?v=4" defer></script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
@endpush
