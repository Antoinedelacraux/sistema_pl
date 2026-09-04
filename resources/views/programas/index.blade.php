@extends('layouts.shell')

@section('title', 'Receta')

@section('heading', 'Receta')
@section('crumbs', 'Planeamiento · materiales por receta')

@section('page')
@php
    $exportParams = array_filter([
        'mercado' => request('mercado'),
        'cliente' => request('cliente'),
        'familia' => request('familia'),
        'programa' => request('programa'),
        'presentacion' => request('presentacion'),
        'buscar' => request('buscar'),
    ], fn ($valor) => filled($valor));
@endphp
<div class="programas-page">
@include('partials.flash')
@include('programas._tabs', ['recetaTab' => 'materiales'])

<section class="panel panel--programas">
    <div class="receta-command">
        <div class="receta-command__shell">
            <form
                method="GET"
                action="{{ route('programas.index') }}"
                class="receta-command__form"
                data-grid-filters
                data-clear-url="{{ route('programas.index') }}"
            >
                <div class="receta-command__grid">
                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Mercado</span>
                        <select id="receta-filtro-mercado" name="mercado" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($mercados as $item)
                                <option value="{{ $item->idMercado }}" @selected((string) ($filtros['mercado'] ?? '') === (string) $item->idMercado)>{{ $item->nomMercado }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Cliente</span>
                        <select id="receta-filtro-cliente" name="cliente" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($clientes as $item)
                                <option value="{{ $item->idCliente }}" @selected((string) ($filtros['cliente'] ?? '') === (string) $item->idCliente)>{{ $item->nomCliente }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Programa</span>
                        <select id="receta-filtro-programa" name="programa" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($programas as $item)
                                <option value="{{ $item->idPrograma }}" @selected((string) ($filtros['programa'] ?? '') === (string) $item->idPrograma)>{{ $item->nomPrograma }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Producto</span>
                        <select id="receta-filtro-presentacion" name="presentacion" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($presentaciones as $item)
                                <option value="{{ $item->idPresentacion }}" @selected((string) ($filtros['presentacion'] ?? '') === (string) $item->idPresentacion)>{{ $item->nomPresentacion }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field">
                        <span class="receta-cmd-label">Material</span>
                        <select id="receta-filtro-familia" name="familia" class="receta-cmd-input">
                            <option value="">Todos</option>
                            @foreach ($familias as $item)
                                <option value="{{ $item->idFamilia }}" @selected((string) ($filtros['familia'] ?? '') === (string) $item->idFamilia)>{{ $item->nomFamilia }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="receta-cmd-field receta-cmd-field--search">
                        <span class="receta-cmd-label">Buscar</span>
                        <input
                            type="search"
                            id="receta-buscar"
                            name="buscar"
                            class="receta-cmd-input receta-cmd-input--search"
                            placeholder="Código PL o material"
                            autocomplete="off"
                            value="{{ $filtros['q'] ?? '' }}"
                        >
                    </label>

                    <div class="receta-cmd-clear">
                        <button type="button" class="btn btn--outline btn--sm" id="receta-limpiar" data-grid-filters-clear>Limpiar</button>
                    </div>

                    <div class="receta-cmd-export">
                        @include('partials.export-actions', [
                            'pdfUrl' => route('programas.export.pdf', $exportParams),
                            'excelUrl' => route('programas.export.excel', $exportParams),
                            'class' => 'receta-cmd-export-inner',
                        ])
                    </div>
                </div>
            </form>

            <aside class="receta-command__aside">
                <div class="receta-command__stat" aria-live="polite">
                    <span class="receta-command__stat-value">{{ number_format($totalProgramas) }}</span>
                    <span class="receta-command__stat-label">programas</span>
                </div>
                @if ($puedeCrear)
                    <form method="POST" action="{{ route('programas.import') }}" enctype="multipart/form-data" class="receta-import-form" id="receta-import-form">
                        @csrf
                        <input type="file" name="archivo_excel" id="receta-archivo-excel" class="receta-import-form__file" accept=".xlsx,.xlsm,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                        <button type="button" class="btn btn--outline btn--sm" id="receta-importar-excel">Importar Excel</button>
                    </form>
                    <button type="button" class="btn btn--purple btn--sm" id="receta-abrir-modal">Agregar</button>
                @endif
            </aside>
        </div>
    </div>

    <div class="grid-table-section">
        <div class="grid-table-head">
            <p class="grid-table-count" id="receta-contador" data-total="{{ $totalFilas }}">{{ number_format($totalFilas) }} registros en receta</p>
        </div>

        <div class="programas-grid-wrap">
            <table class="programas-grid">
            <thead>
                <tr>
                    <th class="programas-grid__th programas-grid__th--num">#</th>
                    <th class="programas-grid__th programas-grid__th--stack">Mercado</th>
                    <th class="programas-grid__th programas-grid__th--stack">Cliente</th>
                    <th class="programas-grid__th programas-grid__th--stack">Programa</th>
                    <th class="programas-grid__th programas-grid__th--stack">Variedad</th>
                    <th class="programas-grid__th programas-grid__th--stack">Producto</th>
                    <th class="programas-grid__th programas-grid__th--stack">Material</th>
                    <th class="programas-grid__th programas-grid__th--stack">Código<br>PL</th>
                    <th class="programas-grid__th programas-grid__th--stack">Descripción<br>material</th>
                    <th class="programas-grid__th programas-grid__th--stack">Observación</th>
                    <th class="programas-grid__th programas-grid__th--stack programas-grid__th--qty">Cantidad x<br>caja</th>
                    <th class="programas-grid__th programas-grid__th--stack programas-grid__th--img">Imagen</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $claseObsr = \App\Support\UsoObservacionSupport::claseCss($fila->uso?->nomUso);
                    @endphp
                    <tr
                        class="receta-fila"
                        data-id-mercado="{{ $fila->idMercado }}"
                        data-id-cliente="{{ $fila->idCliente }}"
                        data-id-familia="{{ $fila->idFamilia }}"
                    >
                        <td class="programas-grid__num">{{ $fila->nroFila }}</td>
                        <td class="programas-grid__cell programas-grid__cell--short">{{ $fila->mercado?->nomMercado }}</td>
                        <td class="programas-grid__cell">{{ $fila->cliente?->nomCliente }}</td>
                        <td class="programas-grid__cell">{{ $fila->programa?->nomPrograma }}</td>
                        <td class="programas-grid__cell">{{ $fila->variedad?->nomVariedad }}</td>
                        <td class="programas-grid__cell">{{ $fila->presentacion?->nomPresentacion }}</td>
                        <td class="programas-grid__cell programas-grid__cell--short">{{ $fila->familia?->nomFamilia }}</td>
                        <td class="programas-grid__code">{{ $fila->material?->CodigoPL }}</td>
                        <td class="programas-grid__material">{{ $fila->material?->nomMaterial }}</td>
                        <td class="programas-grid__cell programas-grid__cell--obsr">
                            @if ($fila->uso?->nomUso)
                                <span class="programas-grid__obsr-valor {{ $claseObsr }}">{{ $fila->uso->nomUso }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="programas-grid__qty">{{ number_format((float) $fila->cantidadCaja?->cantidad, 2, '.', '') }}</td>
                        <td class="programas-grid__cell programas-grid__cell--img">
                            <div class="receta-imagen-cell">
                                @if ($urlImagen = $fila->imagenUrl())
                                    <button
                                        type="button"
                                        class="receta-imagen-thumb"
                                        data-receta-imagen="{{ $urlImagen }}"
                                        aria-label="Ver imagen ampliada"
                                    >
                                        <img src="{{ $urlImagen }}" alt="Imagen del material" loading="lazy" width="40" height="40">
                                    </button>
                                @else
                                    <span class="receta-imagen-empty">—</span>
                                @endif
                                @if ($puedeEditar)
                                    <button
                                        type="button"
                                        class="icon-action icon-action--edit receta-imagen-edit"
                                        data-editar-imagen="{{ $fila->idProgramaFila }}"
                                        data-imagen-url="{{ $urlImagen ?? '' }}"
                                        data-material="{{ $fila->material?->nomMaterial }}"
                                        data-nro-fila="{{ $fila->nroFila }}"
                                        data-action="{{ route('programas.imagen.update', $fila->idProgramaFila) }}"
                                        title="Cambiar imagen"
                                        aria-label="Cambiar imagen fila {{ $fila->nroFila }}"
                                    >
                                        @include('partials.icons.edit')
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="data-table__empty">No hay registros con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @include('partials.pagination', ['paginator' => $filas, 'itemLabel' => 'registros'])
    </div>
</section>
</div>

@if ($puedeCrear)
    @include('programas._modal')
@endif

@if ($puedeEditar)
    @include('programas._modal_imagen')
@endif

<div class="receta-lightbox" id="receta-lightbox" hidden aria-hidden="true">
    <button type="button" class="receta-lightbox__backdrop" data-receta-lightbox-close aria-label="Cerrar"></button>
    <div class="receta-lightbox__panel" role="dialog" aria-modal="true" aria-label="Imagen ampliada">
        <button type="button" class="receta-lightbox__close" data-receta-lightbox-close aria-label="Cerrar">&times;</button>
        <img class="receta-lightbox__img" src="" alt="Imagen ampliada">
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.recetaMaterialUrl = @json(route('programas.material-por-codigo'));
    window.recetaEditarImagenId = @json(session('receta_editar_imagen'));
</script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/receta.js') }}?v=5" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var importBtn = document.getElementById('receta-importar-excel');
        var importInput = document.getElementById('receta-archivo-excel');
        var importForm = document.getElementById('receta-import-form');

        if (!importBtn || !importInput || !importForm) {
            return;
        }

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
    });
</script>
@endpush
