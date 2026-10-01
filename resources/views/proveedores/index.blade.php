@extends('layouts.shell')

@section('title', 'Proveedor')

@section('heading', 'Proveedor')
@section('crumbs', 'Planeamiento · códigos TGF y SAFCO')

@push('head')
<link rel="stylesheet" href="{{ asset('css/proveedor.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/receta.css') }}?v=3">
@endpush

@section('page')
@php
    $totalColumnas = count($columnasConfig);
    $exportParams = array_filter([
        'familia' => request('familia'),
        'buscar' => request('buscar'),
    ], fn ($valor) => filled($valor));
@endphp
<div class="proveedor-page">
@include('partials.flash')

<section class="panel panel--proveedor">
    <div class="receta-toolbar">
        <div class="receta-toolbar__shell">
            <form
                method="GET"
                action="{{ route('proveedor.index') }}"
                class="receta-filters"
                data-grid-filters
                data-grid-filters-ajax="1"
                data-grid-filters-region="proveedor-grid-region"
                data-clear-url="{{ route('proveedor.index') }}"
            >
                <div class="receta-filters__main">
                    <label class="receta-cmd-field receta-filters__field">
                        <span class="receta-cmd-label">Material</span>
                        <select id="proveedor-filtro-familia" name="familia" class="receta-cmd-input">
                            <option value="">Todas</option>
                            @foreach ($familias as $item)
                                <option value="{{ $item->idFamilia }}" @selected((string) ($filtros['familia'] ?? '') === (string) $item->idFamilia)>{{ $item->nomFamilia }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field receta-filters__field receta-filters__field--search">
                        <span class="receta-cmd-label">Buscar</span>
                        <input
                            type="search"
                            id="proveedor-buscar"
                            name="buscar"
                            class="receta-cmd-input receta-cmd-input--search"
                            placeholder="Código PL, proveedor o material"
                            autocomplete="off"
                            value="{{ $filtros['buscar'] ?? '' }}"
                        >
                    </label>

                    <div class="receta-filters__clear">
                        <button type="button" class="btn btn--outline btn--sm" id="proveedor-limpiar" data-grid-filters-clear>Limpiar</button>
                    </div>
                </div>

                @include('partials.export-actions', [
                    'pdfUrl' => route('proveedor.export.pdf', $exportParams),
                    'excelUrl' => route('proveedor.export.excel', $exportParams),
                    'class' => 'receta-cmd-export-inner receta-filters__export',
                ])
            </form>

            <div class="receta-toolbar__aside">
                <div class="receta-toolbar__stat" aria-live="polite">
                    <span class="receta-toolbar__stat-value">{{ number_format($totalFilas) }}</span>
                    <span class="receta-toolbar__stat-label">registros</span>
                </div>
                <div class="receta-toolbar__actions">
                    <button type="button" class="btn btn--outline btn--sm" id="proveedor-abrir-columnas">Columnas</button>
                    @if ($puedeCrear)
                        <button type="button" class="btn btn--purple btn--sm" id="proveedor-abrir-agregar">Agregar</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid-table-section" id="proveedor-grid-region">
        <span hidden data-grid-sync-stat=".receta-toolbar__stat-value">{{ number_format($totalFilas) }}</span>
        <div class="programas-grid-wrap proveedor-grid-wrap">
        <table class="programas-grid proveedor-grid" id="proveedor-grid">
            <colgroup>
                <col class="proveedor-col proveedor-col--num" data-col="num">
                <col class="proveedor-col proveedor-col--familia" data-col="familia">
                <col class="proveedor-col proveedor-col--code" data-col="codigo-pl">
                <col class="proveedor-col proveedor-col--material" data-col="material-pl">
                @foreach ($columnasProveedor as $proveedor)
                    <col class="proveedor-col proveedor-col--code" data-col="{{ $proveedor['key'] }}-codigo">
                    <col class="proveedor-col proveedor-col--material" data-col="{{ $proveedor['key'] }}-material">
                @endforeach
                <col class="proveedor-col proveedor-col--actions" data-col="acciones" width="40">
            </colgroup>
            <thead>
                <tr>
                    <th class="programas-grid__th programas-grid__th--num" data-col="num">#</th>
                    <th class="programas-grid__th programas-grid__th--stack" data-col="familia">Material</th>
                    <th class="programas-grid__th programas-grid__th--stack" data-col="codigo-pl">Código<br>Planeamiento</th>
                    <th class="programas-grid__th programas-grid__th--stack" data-col="material-pl">Material<br>Planeamiento</th>
                    @foreach ($columnasProveedor as $proveedor)
                        <th class="programas-grid__th programas-grid__th--stack" data-col="{{ $proveedor['key'] }}-codigo">Código<br>{{ $proveedor['nombre'] }}</th>
                        <th class="programas-grid__th programas-grid__th--stack" data-col="{{ $proveedor['key'] }}-material">Material<br>{{ $proveedor['nombre'] }}</th>
                    @endforeach
                    <th class="programas-grid__th programas-grid__th--stack proveedor-grid__th--actions" data-col="acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr
                        class="proveedor-fila"
                        data-nro-fila="{{ $fila->nroFila }}"
                        data-id-material-pl="{{ $fila->idMaterialPL }}"
                        data-id-familia="{{ $fila->idFamilia ?? '' }}"
                        data-codigo-pl="{{ $fila->materialPl?->CodigoPL }}"
                        data-nom-material-pl="{{ $fila->materialPl?->nomMaterial }}"
                        data-proveedores='@json($fila->proveedores)'
                    >
                        <td class="programas-grid__num" data-col="num">{{ $fila->nroFila }}</td>
                        <td class="programas-grid__cell programas-grid__cell--short proveedor-grid__familia" data-col="familia">{{ $fila->familia?->nomFamilia }}</td>
                        <td class="programas-grid__code proveedor-grid__codigo-pl" data-col="codigo-pl">{{ $fila->materialPl?->CodigoPL }}</td>
                        <td class="programas-grid__material proveedor-grid__material-pl" data-col="material-pl">{{ $fila->materialPl?->nomMaterial }}</td>
                        @foreach ($columnasProveedor as $proveedor)
                            @php
                                $datosProv = $fila->proveedores[$proveedor['nombre']] ?? ['codigo' => null, 'nomMaterial' => null];
                            @endphp
                            <td class="programas-grid__code proveedor-grid__codigo-proveedor" data-col="{{ $proveedor['key'] }}-codigo">{{ $datosProv['codigo'] }}</td>
                            <td class="programas-grid__material proveedor-grid__material-proveedor" data-col="{{ $proveedor['key'] }}-material">{{ $datosProv['nomMaterial'] }}</td>
                        @endforeach
                        <td class="proveedor-grid__actions" data-col="acciones">
                            <div class="proveedor-grid__action-group">
                                @if ($puedeEditar)
                                    <button
                                        type="button"
                                        class="icon-action icon-action--edit"
                                        data-editar-proveedor="{{ $fila->nroFila }}"
                                        aria-label="Editar"
                                        title="Editar"
                                    >
                                        @include('partials.icons.edit')
                                    </button>
                                @endif
                                @if ($puedeEliminar)
                                    <form
                                        method="POST"
                                        action="{{ route('proveedor.destroy', $fila->nroFila) }}"
                                        class="proveedor-grid__delete-form"
                                        data-confirm="Se eliminará este registro de proveedor. Esta acción no se puede deshacer."
                                        data-confirm-title="Eliminar registro"
                                        data-confirm-ok="Sí, eliminar"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="icon-action icon-action--delete"
                                            aria-label="Eliminar"
                                            title="Eliminar"
                                        >
                                            @include('partials.icons.trash')
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $totalColumnas }}" class="data-table__empty">No hay registros con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @include('partials.pagination', ['paginator' => $filas, 'itemLabel' => 'registros'])
    </div>
</section>
</div>

@include('proveedores._modal_columnas')

@if ($puedeEditar)
    @include('proveedores._modal')
@endif

@if ($puedeCrear)
    @include('proveedores._modal_agregar')
@endif

@endsection

@push('scripts')
<script>
    window.proveedorUpdateUrlTemplate = @json(route('proveedor.update', ['nroFila' => 0]));
    window.proveedorMaterialesBuscarUrl = @json(route('proveedor.materiales.buscar'));
    window.proveedorMaterialCodigoUrl = @json(route('proveedor.material-por-codigo'));
    window.proveedorMaterialNombreUrl = @json(route('proveedor.material-por-nombre'));
    window.proveedorImportPreviewUrl = @json(route('proveedor.import.preview'));
    window.proveedorColumnasKeys = @json(collect($columnasProveedor)->pluck('key', 'nombre'));
</script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/proveedor.js') }}?v=6" defer></script>
@endpush
