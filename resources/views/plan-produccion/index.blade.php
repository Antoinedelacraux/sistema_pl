@extends('layouts.shell')

@section('title', 'Plan de Producción')

@section('heading', 'Plan de Producción')
@section('crumbs', 'Planeamiento · ventas en cajas · sincronizado con MRP')

@push('head')
<link rel="stylesheet" href="{{ asset('css/mrp.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/plan-produccion.css') }}?v=15">
@endpush

@section('page')
<div class="plan-prod-page">
@include('partials.flash')

<section class="panel panel--plan-prod">
    <div class="plan-prod-toolbar">
        @if ($semanas->isNotEmpty())
            <p class="plan-prod-meta plan-prod-meta--toolbar">{{ number_format($totalProgramas) }} programa(s) · {{ $semanas->count() }} semana(s) · valores en cajas</p>
        @endif
        <div class="plan-prod-toolbar__actions">
            @if ($puedeEditar && $semanas->isNotEmpty())
                <button type="button" class="btn btn--outline btn--sm" id="plan-prod-edit">Editar</button>
                <button type="submit" class="btn btn--purple btn--sm" form="plan-prod-form" id="plan-prod-save" hidden>Guardar</button>
                <button type="button" class="btn btn--ghost btn--sm" id="plan-prod-cancel" hidden>Cancelar</button>
            @endif
            @if ($puedeImportar)
                <form method="POST" action="{{ route('plan-produccion.import') }}" enctype="multipart/form-data" class="plan-prod-import-form" id="plan-prod-import-form">
                    @csrf
                    <input type="file" name="archivo_excel" id="plan-prod-archivo-excel" class="plan-prod-import-form__file" accept=".xlsx,.xlsm,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                    <button type="button" class="btn btn--outline btn--sm" id="plan-prod-importar-excel">Importar Excel</button>
                </form>
            @endif
            @if ($puedeVaciar ?? false)
                <form method="POST" action="{{ route('plan-produccion.vaciar') }}" data-confirm="Se eliminará todo el plan de producción. Esta acción no se puede deshacer." data-confirm-title="Vaciar plan" data-confirm-ok="Sí, vaciar" data-confirm-variant="danger" data-confirm-texto="VACIAR">
                    @csrf
                    <input type="hidden" name="confirmacion" value="">
                    <button type="submit" class="btn btn--ghost btn--sm">Vaciar</button>
                </form>
            @endif
        </div>
    </div>

    @if ($semanas->isEmpty())
        <div class="plan-prod-empty">
            <p>No hay datos cargados. @if ($puedeImportar) Importe <strong>2026.08.24 Plan de ventas resumido.xlsx</strong>. @endif</p>
        </div>
    @else
        @php
            $semIds = $semanas->pluck('idSemProduccion')->map(fn ($id) => (int) $id)->values()->all();
        @endphp
        <div class="plan-prod-section plan-prod-section--plan" id="plan-prod-section-plan" data-materiales-url="{{ route('plan-produccion.materiales', ['idProgramaGeneral' => '__PG__']) }}">
            <div class="plan-prod-section__head">
                <div class="plan-prod-section__head-left">
                    <h2 class="plan-prod-section__title">Plan de ventas</h2>
                </div>
                <div class="plan-prod-section__export-wrap">
                    <label class="plan-prod-export-desglose">
                        <input type="checkbox" id="plan-prod-export-desglose">
                        Desglosar materiales
                    </label>
                    @include('partials.export-actions', [
                        'pdfUrl' => route('plan-produccion.export.pdf', ['seccion' => 'ventas']),
                        'excelUrl' => route('plan-produccion.export.excel', ['seccion' => 'ventas']),
                        'class' => 'plan-prod-section__export inventario-export',
                    ])
                </div>
            </div>
            <form
                id="plan-prod-form"
                method="POST"
                action="{{ route('plan-produccion.update') }}"
                data-confirm="¿Guardar cambios? Se sincronizará el plan de consumo en MRP."
                data-confirm-title="Guardar plan de producción"
                data-confirm-ok="Sí, guardar"
                data-confirm-variant="warning"
            >
                @csrf
                @method('PUT')
                <div class="plan-prod-grid-wrap" id="plan-prod-scroll-plan">
                    <table class="programas-grid plan-prod-grid" id="plan-prod-table" data-sem-ids="{{ implode(',', $semIds) }}">
                        <thead>
                            <tr>
                                <th class="programas-grid__th plan-prod-grid__th plan-prod-grid__th--programa">Programa General</th>
                                @foreach ($semanas as $sem)
                                    <th class="programas-grid__th programas-grid__th--num plan-prod-grid__th plan-prod-grid__th--sem" data-sem="{{ $sem->idSemProduccion }}" title="{{ $sem->fechaInicio?->format('d/m/Y') }}">{{ $sem->codigoSemana }}</th>
                                @endforeach
                                <th class="programas-grid__th programas-grid__th--num plan-prod-grid__th plan-prod-grid__th--total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                @php
                                    $pgId = $fila['idProgramaGeneral'];
                                    $vals = [];
                                    foreach ($semanas as $sem) {
                                        $valor = (float) ($fila['sem_'.$sem->idSemProduccion] ?? 0);
                                        $vals[] = $valor == 0.0 ? '' : number_format($valor, 0, '.', '');
                                    }
                                @endphp
                                <tr class="plan-prod-grid__row plan-prod-grid__row--pg" data-plan-prod-pg="{{ $pgId }}" data-vals="{{ implode(',', $vals) }}">
                                    <td class="plan-prod-grid__cell plan-prod-grid__cell--programa"><button type="button" class="plan-prod-expand" data-plan-prod-toggle="{{ $pgId }}" aria-expanded="false" @disabled(! $fila['tiene_materiales'])><span class="plan-prod-expand__icon" aria-hidden="true"></span><span class="plan-prod-expand__label">{{ $fila['nombre'] }}@if ($fila['calificacion']) <span class="plan-prod-expand__cal">{{ $fila['calificacion'] }}</span>@endif</span></button></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + $semanas->count() }}" class="plan-prod-grid__empty">Sin programas en el plan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <script>
                    (function () {
                        var table = document.getElementById('plan-prod-table');
                        if (!table) return;
                        var semIds = String(table.getAttribute('data-sem-ids') || '').split(',').map(function (id) { return String(id).trim(); }).filter(Boolean);
                        table.querySelectorAll('tr.plan-prod-grid__row--pg[data-vals]').forEach(function (tr) {
                            if (tr.getAttribute('data-pg-painted') === '1') return;
                            var pgId = tr.getAttribute('data-plan-prod-pg') || '';
                            var vals = String(tr.getAttribute('data-vals') || '').split(',');
                            var total = 0;
                            semIds.forEach(function (semId, idx) {
                                var valor = vals[idx] != null ? String(vals[idx]) : '';
                                var num = valor ? Number(valor) : 0;
                                total += num;
                                var td = document.createElement('td');
                                td.className = 'programas-grid__qty plan-prod-grid__cell--qty plan-prod-grid__cell--pg-qty plan-prod-grid__qty--editable';
                                td.setAttribute('data-pg', pgId);
                                td.setAttribute('data-sem', semId);
                                td.setAttribute('data-val', valor);
                                var span = document.createElement('span');
                                span.className = 'plan-prod-grid__valor';
                                span.textContent = valor ? num.toLocaleString('en-US', { maximumFractionDigits: 0 }) : '—';
                                td.appendChild(span);
                                tr.appendChild(td);
                            });
                            var tdTotal = document.createElement('td');
                            tdTotal.className = 'programas-grid__qty plan-prod-grid__cell--qty plan-prod-grid__cell--pg-qty plan-prod-grid__cell--total';
                            var spanTotal = document.createElement('span');
                            spanTotal.className = 'plan-prod-grid__valor';
                            spanTotal.textContent = total > 0 ? total.toLocaleString('en-US', { maximumFractionDigits: 0 }) : '—';
                            tdTotal.appendChild(spanTotal);
                            tr.appendChild(tdTotal);
                            tr.setAttribute('data-pg-painted', '1');
                        });
                    })();
                    </script>
                </div>
            </form>
        </div>

        @if (session()->has('plan_prod_cells_highlight'))
            <script type="application/json" id="plan-prod-cells-highlight">@json(session('plan_prod_cells_highlight'))</script>
        @endif

        @if ($semanasPlanConsumo->isNotEmpty())
            <div class="plan-prod-section plan-prod-section--consumo">
                <div class="plan-prod-section__head">
                    <div class="plan-prod-section__head-left">
                        <h2 class="plan-prod-section__title">Plan de consumo</h2>
                        <span class="plan-prod-section__note">Suma por material · sincronizado con MRP</span>
                    </div>
                    @include('partials.export-actions', [
                        'pdfUrl' => route('plan-produccion.export.pdf', ['seccion' => 'consumo']),
                        'excelUrl' => route('plan-produccion.export.excel', ['seccion' => 'consumo']),
                        'class' => 'plan-prod-section__export inventario-export',
                    ])
                </div>
                @include('mrp._tabla_matriz', [
                    'semanas' => $semanasPlanConsumo,
                    'filas' => $planConsumo,
                    'scrollId' => 'plan-prod-scroll-consumo',
                    'layoutPlan' => true,
                    'cabeceraUnaLinea' => true,
                    'mostrarTotal' => true,
                    'mensajeVacio' => 'Sin materiales en el plan.',
                ])
            </div>
        @endif
    @endif
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/plan-produccion.js') }}?v=9"></script>
<script>
(function () {
    var importBtn = document.getElementById('plan-prod-importar-excel');
    var importInput = document.getElementById('plan-prod-archivo-excel');
    var importForm = document.getElementById('plan-prod-import-form');

    var desgloseCheck = document.getElementById('plan-prod-export-desglose');
    var exportWrap = document.querySelector('.plan-prod-section--plan .plan-prod-section__export');
    if (desgloseCheck && exportWrap) {
        var pdfLink = exportWrap.querySelector('.export-icon--pdf');
        var excelLink = exportWrap.querySelector('.export-icon--excel');
        if (pdfLink && excelLink) {
            pdfLink.setAttribute('data-base-href', pdfLink.getAttribute('href') || '');
            excelLink.setAttribute('data-base-href', excelLink.getAttribute('href') || '');

            function actualizarEnlacesExport() {
                var q = desgloseCheck.checked ? '?desglose=1' : '';
                pdfLink.href = pdfLink.getAttribute('data-base-href') + q;
                excelLink.href = excelLink.getAttribute('data-base-href') + q;
            }

            desgloseCheck.addEventListener('change', actualizarEnlacesExport);
            actualizarEnlacesExport();
        }
    }

    if (importBtn && importInput && importForm) {
        importBtn.addEventListener('click', function () {
            importInput.value = '';
            importInput.click();
        });

        importInput.addEventListener('change', function () {
            if (!importInput.files || !importInput.files.length) {
                return;
            }
            importBtn.disabled = true;
            importBtn.textContent = 'Importando…';
            importForm.submit();
        });
    }
})();
</script>
@endpush
