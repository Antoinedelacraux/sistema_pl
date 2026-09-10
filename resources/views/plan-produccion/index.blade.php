@extends('layouts.shell')

@section('title', 'Plan de Producción')

@section('heading', 'Plan de Producción')
@section('crumbs', 'Planeamiento · ventas en cajas · sincronizado con MRP')

@push('head')
<link rel="stylesheet" href="{{ asset('css/mrp.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/plan-produccion.css') }}?v=8">
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
                <form method="POST" action="{{ route('plan-produccion.vaciar') }}" data-confirm="¿Vaciar todo el plan de producción?" data-confirm-title="Vaciar plan" data-confirm-ok="Sí, vaciar" data-confirm-variant="danger">
                    @csrf
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
            $matsPayload = [];
            foreach ($filas as $fila) {
                $pgKey = (string) $fila['idProgramaGeneral'];
                $matsPayload[$pgKey] = [];
                foreach ($fila['materiales'] as $mat) {
                    $item = [
                        'id' => (int) $mat['id_material_pl'],
                        'c' => $mat['codigo_pl'],
                        'n' => $mat['descripcion'],
                        'f' => $mat['nom_familia'] ?? '',
                        'x' => (float) $mat['cantidad_por_caja_num'],
                        'xf' => $mat['cantidad_por_caja'],
                        'caja' => ! empty($mat['es_caja']) ? 1 : 0,
                        'dec' => (int) ($mat['decimales_cantidad'] ?? 2),
                    ];
                    if (! empty($mat['overrides'])) {
                        $item['o'] = $mat['overrides'];
                    }
                    $matsPayload[$pgKey][] = $item;
                }
            }
        @endphp
        <div class="plan-prod-section plan-prod-section--plan" id="plan-prod-section-plan">
            <div class="plan-prod-section__head">
                <h2 class="plan-prod-section__title">Plan de ventas</h2>
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
                                    <td colspan="{{ 1 + $semanas->count() }}" class="plan-prod-grid__empty">Sin programas en el plan.</td>
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
                            semIds.forEach(function (semId, idx) {
                                var valor = vals[idx] != null ? String(vals[idx]) : '';
                                var td = document.createElement('td');
                                td.className = 'programas-grid__qty plan-prod-grid__cell--qty plan-prod-grid__cell--pg-qty plan-prod-grid__qty--editable';
                                td.setAttribute('data-pg', pgId);
                                td.setAttribute('data-sem', semId);
                                td.setAttribute('data-val', valor);
                                var span = document.createElement('span');
                                span.className = 'plan-prod-grid__valor';
                                span.textContent = valor ? Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 0 }) : '—';
                                td.appendChild(span);
                                tr.appendChild(td);
                            });
                            tr.setAttribute('data-pg-painted', '1');
                        });
                    })();
                    </script>
                </div>
            </form>
            <script type="application/json" id="plan-prod-mats-data">@json($matsPayload)</script>
        </div>

        @if ($semanasPlanConsumo->isNotEmpty())
            <div class="plan-prod-section plan-prod-section--consumo">
                <div class="plan-prod-section__head">
                    <h2 class="plan-prod-section__title">Plan de consumo</h2>
                    <span class="plan-prod-section__note">Suma por material · sincronizado con MRP</span>
                </div>
                @include('mrp._tabla_matriz', [
                    'semanas' => $semanasPlanConsumo,
                    'filas' => $planConsumo,
                    'scrollId' => 'plan-prod-scroll-consumo',
                    'layoutPlan' => true,
                    'cabeceraUnaLinea' => true,
                    'mensajeVacio' => 'Sin materiales en el plan.',
                ])
            </div>
        @endif
    @endif
</section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/plan-produccion.js') }}"></script>
<script>
(function () {
    var importBtn = document.getElementById('plan-prod-importar-excel');
    var importInput = document.getElementById('plan-prod-archivo-excel');
    var importForm = document.getElementById('plan-prod-import-form');

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
