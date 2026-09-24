@extends('layouts.shell')

@section('title', 'Tabla Comercial')
@section('heading', 'Tabla Comercial')
@section('crumbs', 'Planeamiento · plan de ventas y asignación diaria')

@push('head')
<link rel="stylesheet" href="{{ asset('css/plan-produccion.css') }}?v=15">
<style>
/* ── Colores de estado en celdas ───────────────────────────────── */
.tc-cell { position: relative; cursor: pointer; transition: background .15s; }
.tc-cell:hover { filter: brightness(.93); }

.tc-cell--en-progreso { background: #fef9c3 !important; color: #713f12 !important; }
.tc-cell--realizado   { background: #dcfce7 !important; color: #14532d !important; }
.tc-cell--despachado  { background: #dbeafe !important; color: #1e3a8a !important; }

/* ── Tooltip / popover ─────────────────────────────────────────── */
.tc-popover {
    display: none;
    position: fixed;
    z-index: 1200;
    min-width: 220px;
    max-width: 280px;
    background: var(--app-surface, #fff);
    border: 1px solid var(--app-line, #e2e0e8);
    border-radius: var(--radius-md, 8px);
    box-shadow: 0 8px 24px rgba(50,42,68,.18);
    padding: 12px 14px;
    font-size: 12px;
    color: var(--ink, #1a1523);
    pointer-events: auto;
}
.tc-popover.is-visible { display: block; }

.tc-popover__title    { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: var(--purple-700, #5b3fa0); line-height: 1.3; }
.tc-popover__row      { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.tc-popover__label    { color: var(--ink-muted, #6b6384); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; font-size: 10px; }
.tc-popover__value    { font-weight: 700; }
.tc-popover__sep      { border: none; border-top: 1px solid var(--app-line, #e2e0e8); margin: 8px 0; }
.tc-popover__select   { width: 100%; height: 32px; padding: 0 8px; border: 1px solid var(--app-line, #e2e0e8); border-radius: var(--radius-sm, 4px); background: var(--input-bg, #f5f4f9); color: var(--ink, #1a1523); font: inherit; font-size: 12px; margin-top: 4px; }
.tc-popover__estado-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-muted, #6b6384); }
.tc-popover__readonly { font-style: italic; color: var(--ink-muted, #6b6384); font-size: 11px; margin-top: 6px; }

/* ── Kanban ────────────────────────────────────────────────────── */
.tc-kanban-section        { padding: 16px 14px; }
.tc-kanban-toolbar        { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.tc-kanban-toolbar__label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--purple-700,#5b3fa0); white-space: nowrap; }
.tc-kanban-toolbar__select{ height: 34px; padding: 0 10px; border: none; border-radius: var(--radius-sm,4px); background: var(--input-bg,#f5f4f9); box-shadow: inset 0 0 0 1px var(--app-line,#e2e0e8); color: var(--ink,#1a1523); font: inherit; font-size: 13px; }

.tc-kanban {
    display: grid;
    grid-template-columns: minmax(140px,1fr) repeat(7, minmax(110px,1fr));
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
}
.tc-kanban__col       { display: flex; flex-direction: column; }
.tc-kanban__col-head  { padding: 6px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; text-align: center; color: var(--purple-700,#5b3fa0); background: var(--app-surface,#fff); border: 1px solid var(--app-line,#e2e0e8); border-bottom: none; border-radius: var(--radius-sm,4px) var(--radius-sm,4px) 0 0; }
.tc-kanban__col:first-child .tc-kanban__col-head { color: var(--ink-muted,#6b6384); }

.tc-kanban__col-body  { flex: 1; min-height: 160px; padding: 6px; display: flex; flex-direction: column; gap: 6px; background: var(--input-bg,#f5f4f9); border: 1px solid var(--app-line,#e2e0e8); border-radius: 0 0 var(--radius-sm,4px) var(--radius-sm,4px); transition: background .12s, border-color .12s; }
.tc-kanban__col-body--over { background: rgba(124,92,180,.08); border-color: var(--purple-500,#7c5cb4); }

.tc-card { padding: 8px 10px; background: #fff; border: 1px solid var(--app-line,#e2e0e8); border-radius: var(--radius-sm,4px); font-size: 12px; cursor: grab; user-select: none; box-shadow: 0 1px 3px rgba(50,42,68,.07); transition: box-shadow .12s, opacity .12s; }
.tc-card:active        { cursor: grabbing; }
.tc-card--dragging     { opacity: .4; box-shadow: none; }
.tc-card__nombre       { display: block; font-weight: 600; color: var(--ink,#1a1523); line-height: 1.3; }

/* tarjeta con estado */
.tc-card--en-progreso  { background: #fef9c3; border-color: #fde047; }
.tc-card--realizado    { background: #dcfce7; border-color: #4ade80; }
.tc-card--despachado   { background: #dbeafe; border-color: #60a5fa; }

.tc-kanban__empty { padding: 20px; color: var(--ink-muted,#6b6384); font-size: 13px; text-align: center; }
</style>
@endpush

@section('page')
@php
    $semIds     = $semanas->pluck('idSemProduccion')->map(fn ($id) => (int) $id)->values()->all();
    $tcProgramas = collect($filas)->map(fn ($f) => [
        'id'     => $f['idProgramaGeneral'],
        'nombre' => $f['nombre'],
        'sems'   => collect($semanas)->mapWithKeys(fn ($s) => [
            (string) $s->idSemProduccion => (float) ($f['sem_'.$s->idSemProduccion] ?? 0),
        ])->all(),
    ])->values()->all();
@endphp

<div class="tabla-comercial-page">
@include('partials.flash')

{{-- ─── Popover global ─────────────────────────────────────────── --}}
<div id="tc-popover" class="tc-popover" role="tooltip">
    <div class="tc-popover__title" id="tc-pop-title"></div>
    <div class="tc-popover__row">
        <span class="tc-popover__label">Estado</span>
        <span class="tc-popover__value" id="tc-pop-estado-text"></span>
    </div>
    <div class="tc-popover__row">
        <span class="tc-popover__label">Planificado</span>
        <span class="tc-popover__value" id="tc-pop-plan"></span>
    </div>
    <div class="tc-popover__row">
        <span class="tc-popover__label">Consumido</span>
        <span class="tc-popover__value" id="tc-pop-consumido"></span>
    </div>
    <div class="tc-popover__row">
        <span class="tc-popover__label">Avance</span>
        <span class="tc-popover__value" id="tc-pop-pct"></span>
    </div>
    <hr class="tc-popover__sep" id="tc-pop-sep">
    @if ($puedeEditar)
        <div id="tc-pop-accion-wrap">
            <div class="tc-popover__estado-label">Acción manual</div>
            <select id="tc-pop-select" class="tc-popover__select">
                <option value="">— Sin cambio —</option>
                <option value="despachado">Marcar como Despachado</option>
                <option value="_reset">Resetear estado</option>
            </select>
        </div>
    @endif
</div>

{{-- ─── Tabla 1: Plan de Ventas ────────────────────────────────── --}}
<section class="panel panel--tabla-comercial">
    <div class="plan-prod-section plan-prod-section--plan">
        <div class="plan-prod-section__head">
            <div class="plan-prod-section__head-left">
                <h2 class="plan-prod-section__title">Plan de Ventas</h2>
                @if ($semanas->isNotEmpty())
                    <p class="plan-prod-meta">{{ number_format($totalProgramas) }} programa(s) · {{ $semanas->count() }} semana(s) · valores en cajas</p>
                @endif
            </div>
        </div>

        @if ($semanas->isEmpty())
            <div class="plan-prod-empty"><p>No hay datos de plan de ventas cargados.</p></div>
        @else
            <div class="plan-prod-grid-wrap">
                <table class="programas-grid plan-prod-grid" id="tc-tabla-ventas">
                    <thead>
                        <tr>
                            <th class="programas-grid__th plan-prod-grid__th plan-prod-grid__th--programa">Programa General</th>
                            @foreach ($semanas as $sem)
                                <th class="programas-grid__th programas-grid__th--num plan-prod-grid__th plan-prod-grid__th--sem"
                                    title="{{ $sem->fechaInicio?->format('d/m/Y') }}">{{ $sem->codigoSemana }}</th>
                            @endforeach
                            <th class="programas-grid__th programas-grid__th--num plan-prod-grid__th plan-prod-grid__th--total">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            @php
                                $total = 0;
                                $pgId  = $fila['idProgramaGeneral'];
                            @endphp
                            <tr class="plan-prod-grid__row plan-prod-grid__row--pg">
                                <td class="plan-prod-grid__cell plan-prod-grid__cell--programa">
                                    <span class="plan-prod-expand__label">
                                        {{ $fila['nombre'] }}
                                        @if ($fila['calificacion'])
                                            <span class="plan-prod-expand__cal">{{ $fila['calificacion'] }}</span>
                                        @endif
                                    </span>
                                </td>
                                @foreach ($semanas as $sem)
                                    @php
                                        $v   = (float) ($fila['sem_'.$sem->idSemProduccion] ?? 0);
                                        $total += $v;
                                    @endphp
                                    <td class="plan-prod-grid__cell plan-prod-grid__cell--num tc-cell"
                                        data-pg="{{ $pgId }}"
                                        data-sem="{{ $sem->idSemProduccion }}"
                                        data-plan="{{ $v }}"
                                        data-sem-label="{{ $sem->codigoSemana }}">
                                        {{ $v > 0 ? number_format($v, 0, '.', ',') : '—' }}
                                    </td>
                                @endforeach
                                <td class="plan-prod-grid__cell plan-prod-grid__cell--num plan-prod-grid__cell--total">
                                    {{ $total > 0 ? number_format($total, 0, '.', ',') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + $semanas->count() }}" class="plan-prod-grid__empty">Sin programas en el plan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

{{-- ─── Tabla 2: Kanban semanal ─────────────────────────────────── --}}
<section class="panel panel--tabla-comercial" style="margin-top:16px;">
    <div class="tc-kanban-section">
        <div class="plan-prod-section__head" style="padding:0 0 12px;">
            <div class="plan-prod-section__head-left">
                <h2 class="plan-prod-section__title">Asignación diaria</h2>
                <p class="plan-prod-meta">Arrastra los programas al día en que se realizarán</p>
            </div>
        </div>

        @if ($semanas->isNotEmpty())
            <div class="tc-kanban-toolbar">
                <span class="tc-kanban-toolbar__label">Semana</span>
                <select id="tc-sem-select" class="tc-kanban-toolbar__select">
                    <option value="">Seleccionar…</option>
                    @foreach ($semanas as $sem)
                        <option value="{{ $sem->idSemProduccion }}">
                            {{ $sem->codigoSemana }}@if ($sem->fechaInicio) · {{ $sem->fechaInicio->format('d/m/Y') }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div id="tc-kanban-wrap"></div>
        @else
            <div class="plan-prod-empty"><p>No hay semanas configuradas.</p></div>
        @endif
    </div>
</section>

</div>

<script>
window.tcProgramas    = @json($tcProgramas);
window.tcEstados      = @json($tcEstados);
window.tcEgresos      = @json($tcEgresos);   // "{idPg}_{idSem}" → suma cantidad
window.tcSemanaUrlTpl = "{{ route('tabla-comercial.semana', ['idSemProduccion' => '__ID__']) }}";
window.tcAsignarUrl   = "{{ route('tabla-comercial.asignar') }}";
window.tcEstadoUrl    = "{{ route('tabla-comercial.estado') }}";
window.tcPuedeEditar  = {{ $puedeEditar ? 'true' : 'false' }};
window.tcNombres      = @json(collect($filas)->mapWithKeys(fn($f) => [$f['idProgramaGeneral'] => $f['nombre']]));
</script>
<script src="{{ asset('js/tabla-comercial.js') }}?v=8"></script>
@endsection
