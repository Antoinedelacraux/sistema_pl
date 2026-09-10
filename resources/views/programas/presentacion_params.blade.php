@extends('layouts.shell')

@section('title', 'Receta · Pallets y conformación')

@section('heading', 'Receta')
@section('crumbs', 'Planeamiento · pallets y conformación')

@push('head')
<link rel="stylesheet" href="{{ asset('css/receta.css') }}?v=1">
@endpush

@section('page')
@php
    $programaSeleccionado = (int) ($filtros['programa'] ?? 0);
@endphp
<div class="programas-page">
@include('partials.flash')
@include('programas._tabs')

<section class="panel panel--programas panel--receta-params">
    <div class="receta-command">
        <div class="receta-command__shell receta-command__shell--params">
            <form
                method="GET"
                action="{{ route('programas.presentacion-params.index') }}"
                class="receta-command__form"
                data-grid-filters
                data-clear-url="{{ route('programas.presentacion-params.index') }}"
            >
                <div class="receta-command__grid receta-command__grid--params">
                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Prog. General</span>
                        <select id="receta-filtro-programa-general" name="programa_general" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($catalogosFiltro['programasGenerales'] as $item)
                                <option value="{{ $item->idProgramaGeneral }}" @selected((string) ($filtros['programa_general'] ?? '') === (string) $item->idProgramaGeneral)>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Programa</span>
                        <select id="receta-filtro-programa" name="programa" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($catalogosFiltro['programas'] as $item)
                                <option value="{{ $item->idPrograma }}" @selected((string) ($filtros['programa'] ?? '') === (string) $item->idPrograma)>{{ $item->nomPrograma }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Producto</span>
                        <select id="receta-filtro-presentacion" name="presentacion" class="receta-cmd-input" @disabled($programaSeleccionado <= 0)>
                            @if ($programaSeleccionado <= 0)
                                <option value="">Seleccione un programa</option>
                            @else
                                <option value="">Todos</option>
                                @foreach ($catalogosFiltro['presentaciones'] as $item)
                                    <option value="{{ $item->idPresentacion }}" @selected((string) ($filtros['presentacion'] ?? '') === (string) $item->idPresentacion)>{{ $item->nomPresentacion }}</option>
                                @endforeach
                            @endif
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Buscar</span>
                        <input
                            type="search"
                            name="buscar"
                            class="receta-cmd-input receta-cmd-input--search"
                            placeholder="Prog. general, programa o producto"
                            value="{{ $filtros['buscar'] ?? '' }}"
                        >
                    </label>

                    <div class="receta-cmd-clear">
                        <button type="button" class="btn btn--outline btn--sm" data-grid-filters-clear>Limpiar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="grid-table-section">
        <div class="programas-grid-wrap">
            <table class="programas-grid receta-params-grid">
                <thead>
                    <tr>
                        <th class="programas-grid__th programas-grid__th--stack">Prog.<br>General</th>
                        <th class="programas-grid__th">Programa</th>
                        <th class="programas-grid__th">Producto</th>
                        <th class="programas-grid__th programas-grid__th--qty">Conformación<br>(Dentro)</th>
                        <th class="programas-grid__th programas-grid__th--qty">Pallets<br>(Fuera)</th>
                        @if ($puedeEditar)
                            <th class="programas-grid__th programas-grid__th--actions"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($combinaciones as $fila)
                        <tr>
                            <td class="programas-grid__cell">{{ $fila->nom_programa_general ?? '—' }}</td>
                            <td class="programas-grid__cell">{{ $fila->nom_programa }}</td>
                            <td class="programas-grid__cell">{{ $fila->nom_presentacion }}</td>
                            <td class="programas-grid__qty">{{ $fila->conformacion !== null ? number_format((float) $fila->conformacion, 2, '.', '') : '—' }}</td>
                            <td class="programas-grid__qty">{{ $fila->pallets !== null ? number_format((float) $fila->pallets, 2, '.', '') : '—' }}</td>
                            @if ($puedeEditar)
                                <td class="programas-grid__cell programas-grid__cell--actions">
                                    <button
                                        type="button"
                                        class="icon-action icon-action--edit"
                                        data-receta-param-edit
                                        data-id-programa="{{ $fila->id_programa }}"
                                        data-id-presentacion="{{ $fila->id_presentacion }}"
                                        data-nom-programa="{{ $fila->nom_programa }}"
                                        data-nom-presentacion="{{ $fila->nom_presentacion }}"
                                        data-conformacion="{{ $fila->conformacion ?? '' }}"
                                        data-pallets="{{ $fila->pallets ?? '' }}"
                                        title="Editar"
                                    >@include('partials.icons.edit')</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $puedeEditar ? 6 : 5 }}" class="data-table__empty">No hay combinaciones programa/producto en receta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $combinaciones, 'itemLabel' => 'combinaciones'])
    </div>
</section>
</div>

@if ($puedeEditar)
    @include('programas._modal_presentacion_param')
@endif
@endsection

@push('scripts')
@if ($errors->any())
<script>window.recetaParamOld = @json(old());</script>
@endif
<script src="{{ asset('js/receta-filters.js') }}?v=2" defer></script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/receta-params.js') }}" defer></script>
@endpush
