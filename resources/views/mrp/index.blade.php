@extends('layouts.shell')

@section('title', 'Plan MRP')

@section('heading', 'Plan MRP')
@section('crumbs', 'Planeamiento · consumo planificado y real')

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
                    @if ($puedeEditar)
                        <button
                            type="button"
                            class="btn btn--outline btn--sm"
                            id="mrp-plan-edit"
                            data-mrp-edit
                        >
                            Editar
                        </button>
                        <button
                            type="submit"
                            class="btn btn--purple btn--sm"
                            form="mrp-plan-form"
                            id="mrp-plan-save"
                            data-mrp-save
                            hidden
                        >
                            Guardar
                        </button>
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            id="mrp-plan-cancel"
                            data-mrp-cancel
                            hidden
                        >
                            Cancelar
                        </button>
                    @endif
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
            <form
                id="mrp-plan-form"
                method="POST"
                action="{{ route('mrp.plan.update') }}"
                data-confirm="¿Está seguro de guardar estos cambios en el plan de consumo?"
                data-confirm-title="Guardar plan de consumo"
                data-confirm-ok="Sí, guardar"
                data-confirm-variant="warning"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="familia" value="{{ $filtros['familia'] ?? '' }}">
                @include('mrp._tabla_matriz', [
                    'semanas' => $semanas,
                    'filas' => $plan,
                    'scrollId' => 'mrp-scroll-plan',
                    'editablePlan' => true,
                    'mensajeVacio' => 'Sin materiales en el plan.',
                ])
            </form>
        </div>

        <div class="mrp-section">
            <div class="mrp-section__head">
                <h2 class="mrp-section__title">Consumo real</h2>
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
<script>
(function () {
    var importBtn = document.getElementById('mrp-importar-excel');
    var importInput = document.getElementById('mrp-archivo-excel');
    var importForm = document.getElementById('mrp-import-form');

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

    var syncBtn = document.getElementById('mrp-sync-toggle');
    var scrollPanes = Array.prototype.slice.call(document.querySelectorAll('[data-mrp-scroll]'));
    var syncKey = 'mrp-scroll-sync';
    var syncing = false;
    var syncEnabled = false;

    function setSyncState(active) {
        syncEnabled = active;
        if (!syncBtn) {
            return;
        }
        syncBtn.setAttribute('aria-checked', active ? 'true' : 'false');
        syncBtn.classList.toggle('is-active', active);
        try {
            localStorage.setItem(syncKey, active ? '1' : '0');
        } catch (e) {}
    }

    if (syncBtn && scrollPanes.length > 1) {
        try {
            setSyncState(localStorage.getItem(syncKey) === '1');
        } catch (e) {
            setSyncState(false);
        }

        syncBtn.addEventListener('click', function () {
            setSyncState(!syncEnabled);
        });

        scrollPanes.forEach(function (pane) {
            pane.addEventListener('scroll', function () {
                if (!syncEnabled || syncing) {
                    return;
                }
                syncing = true;
                scrollPanes.forEach(function (otro) {
                    if (otro === pane) {
                        return;
                    }
                    otro.scrollTop = pane.scrollTop;
                    otro.scrollLeft = pane.scrollLeft;
                });
                syncing = false;
            }, { passive: true });
        });
    }

    document.querySelectorAll('[data-mrp-expand]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var section = btn.closest('.mrp-section');
            if (!section) {
                return;
            }

            var expanded = section.classList.toggle('is-expanded');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            btn.textContent = expanded ? 'Contraer' : 'Expandir';
        });
    });

    function initMrpEditSection(config) {
        var section = document.getElementById(config.sectionId);
        var editBtn = document.getElementById(config.editBtnId);
        var saveBtn = document.getElementById(config.saveBtnId);
        var cancelBtn = document.getElementById(config.cancelBtnId);
        var form = document.getElementById(config.formId);

        function setEditMode(active) {
            if (!section) {
                return;
            }

            section.classList.toggle('is-editing', active);

            section.querySelectorAll('.mrp-grid__input').forEach(function (input) {
                input.hidden = !active;
                input.disabled = !active;
            });

            section.querySelectorAll('.mrp-grid__valor').forEach(function (span) {
                span.hidden = active;
            });

            if (editBtn) {
                editBtn.hidden = active;
            }
            if (saveBtn) {
                saveBtn.hidden = !active;
            }
            if (cancelBtn) {
                cancelBtn.hidden = !active;
            }
        }

        if (editBtn && section) {
            editBtn.addEventListener('click', function () {
                setEditMode(true);
            });
        }

        if (cancelBtn && section) {
            cancelBtn.addEventListener('click', function () {
                section.querySelectorAll('.mrp-grid__input').forEach(function (input) {
                    input.value = input.getAttribute('data-original') || '';
                });
                setEditMode(false);
            });
        }

        if (form && section) {
            form.addEventListener('submit', function () {
                section.querySelectorAll('.mrp-grid__input').forEach(function (input) {
                    input.disabled = false;
                });
            });
        }
    }

    initMrpEditSection({
        sectionId: 'mrp-section-plan',
        editBtnId: 'mrp-plan-edit',
        saveBtnId: 'mrp-plan-save',
        cancelBtnId: 'mrp-plan-cancel',
        formId: 'mrp-plan-form',
    });
})();
</script>
@endpush
