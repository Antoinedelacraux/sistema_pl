@extends('layouts.shell')

@section('title', 'Plan MRP')

@section('heading', 'Plan MRP')
@section('crumbs', 'Planeamiento · consumo planificado y real')

@push('head')
<link rel="stylesheet" href="{{ asset('css/mrp.css') }}?v=1">
@endpush

@section('page')
<div class="mrp-page">
@include('partials.flash')

<section class="panel panel--mrp">
    <div class="mrp-toolbar">
        <form
            method="GET"
            action="{{ route('mrp.index') }}"
            class="mrp-toolbar__filtros"
            data-grid-filters
            data-clear-url="{{ route('mrp.index') }}"
        >
            <label class="mrp-toolbar__field">
                <span class="mrp-toolbar__label">Material</span>
                <select name="familia" class="mrp-toolbar__select">
                    <option value="">Todas</option>
                    @foreach ($familiasFiltro as $familia)
                        <option value="{{ $familia->idFamilia }}" @selected((string) ($filtros['familia'] ?? '') === (string) $familia->idFamilia)>
                            {{ $familia->nomFamilia }}
                        </option>
                    @endforeach
                </select>
            </label>
            @if (filled($filtros['familia'] ?? ''))
                <button type="button" class="btn btn--ghost btn--sm" data-grid-filters-clear>Limpiar</button>
            @endif
        </form>

        <div class="mrp-toolbar__actions">
            @if ($semanas->isNotEmpty())
                <label class="mrp-ios-switch">
                    <span class="mrp-ios-switch__label">Sincronizar</span>
                    <button
                        type="button"
                        role="switch"
                        id="mrp-sync-toggle"
                        class="mrp-ios-switch__control"
                        aria-checked="false"
                        aria-label="Sincronizar scroll de tablas"
                    >
                        <span class="mrp-ios-switch__track" aria-hidden="true">
                            <span class="mrp-ios-switch__thumb"></span>
                        </span>
                    </button>
                </label>
            @endif
            @if ($puedeImportar)
                <form method="POST" action="{{ route('mrp.import') }}" enctype="multipart/form-data" class="mrp-import-form" id="mrp-import-form">
                    @csrf
                    <input type="file" name="archivo_excel" id="mrp-archivo-excel" class="mrp-import-form__file" accept=".xlsx,.xlsm,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                    <button type="button" class="btn btn--outline btn--sm" id="mrp-importar-excel">Importar Excel</button>
                </form>
            @endif
        </div>
    </div>

    @if ($semanas->isEmpty())
        <div class="mrp-empty">
            <p>No hay semanas cargadas. @if ($puedeImportar) Importe el archivo <strong>001 MRP plan de uvas.xlsx</strong>. @endif</p>
        </div>
    @else
        <p class="mrp-meta">{{ number_format($totalMateriales) }} material(es) · {{ $semanas->count() }} semana(s)</p>

        <div class="mrp-section mrp-section--plan" id="mrp-section-plan">
            <div class="mrp-section__head">
                <h2 class="mrp-section__title">Plan de consumo</h2>
                <div class="mrp-section__actions">
                    <span class="mrp-section__note">Sincronizado desde Plan de Producción</span>
                    <button
                        type="button"
                        class="btn btn--outline btn--sm mrp-section__expand"
                        data-mrp-expand
                        aria-expanded="false"
                    >
                        Expandir
                    </button>
                </div>
            </div>
            @include('mrp._tabla_matriz', [
                'semanas' => $semanas,
                'filas' => $plan,
                'scrollId' => 'mrp-scroll-plan',
                'layoutPlan' => true,
                'editablePlan' => false,
                'mensajeVacio' => 'Sin materiales en el plan.',
            ])
        </div>

        <div class="mrp-section">
            <div class="mrp-section__head">
                <h2 class="mrp-section__title">Consumo real</h2>
                <span class="mrp-section__note">Rojo: déficit proyectado · Número: días de stock según consumo registrado</span>
            </div>
            @include('mrp._tabla_matriz', [
                'semanas' => $semanas,
                'filas' => $consumoReal,
                'scrollId' => 'mrp-scroll-consumo',
                'resaltarNegativos' => true,
                'columnasExtra' => [
                    ['key' => 'stock_actual', 'label' => 'Stock actual'],
                ],
                'mensajeVacio' => 'Sin materiales en el plan.',
            ])
        </div>

        <div class="mrp-section">
            <div class="mrp-section__head">
                <h2 class="mrp-section__title">Ingresos</h2>
            </div>
            @include('mrp._tabla_matriz', [
                'semanas' => $semanas,
                'filas' => $ingresos,
                'scrollId' => 'mrp-scroll-ingresos',
                'columnasExtra' => [
                    ['key' => 'ingreso_total', 'label' => 'Ingreso total'],
                ],
                'mensajeVacio' => 'Sin materiales en el plan.',
            ])
        </div>

    @endif
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/mrp.js') }}" defer></script>
@endpush
