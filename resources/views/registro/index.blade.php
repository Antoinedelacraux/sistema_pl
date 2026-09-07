@extends('layouts.shell')

@section('title', 'Registro')

@section('heading', 'Registro')
@section('crumbs', 'Stock · ingresos, consumos y traslados')

@section('page')
@php
    $tabActiva = $tab ?? 'ingresos';
    $etiquetaContador = match ($tabActiva) {
        'consumos' => 'consumos',
        'traslados' => 'traslados',
        default => 'ingresos',
    };
    $paginaActiva = match ($tabActiva) {
        'consumos' => $consumos,
        'traslados' => $traslados,
        default => $ingresos,
    };
    $totalContador = method_exists($paginaActiva, 'total') ? $paginaActiva->total() : 0;
@endphp
<div class="registro-page">
@include('partials.flash')

<div class="offline-registro-banner" id="offline-registro-banner" hidden role="status">
    <div class="offline-registro-banner__body">
        <span id="offline-registro-status"></span>
        <ul class="offline-registro-banner__errors" id="offline-registro-errors" hidden></ul>
    </div>
    <div class="offline-registro-banner__actions">
        <button type="button" class="btn btn--outline btn--sm" id="offline-registro-sync" hidden>Sincronizar ahora</button>
        <button type="button" class="btn btn--outline btn--sm" id="offline-registro-clear" hidden>Descartar cola</button>
    </div>
</div>

<section class="panel panel--registro">
    <div class="registro-tabs" role="tablist" aria-label="Tipo de movimiento">
        <a href="{{ route('registro.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'ingresos'])) }}" class="registro-tabs__btn {{ $tabActiva === 'ingresos' ? 'is-active' : '' }}" role="tab">Ingresos</a>
        <a href="{{ route('registro.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'consumos'])) }}" class="registro-tabs__btn {{ $tabActiva === 'consumos' ? 'is-active' : '' }}" role="tab">Consumos</a>
        <a href="{{ route('registro.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'traslados'])) }}" class="registro-tabs__btn {{ $tabActiva === 'traslados' ? 'is-active' : '' }}" role="tab">Traslados</a>
    </div>

    @if ($tabActiva === 'ingresos')
        <div class="registro-toolbar-wrap">
            @include('registro._filtros')
            @if ($puedeCrear)
                <div class="registro-toolbar__actions">
                    <button type="button" class="btn btn--purple btn--sm" id="registro-abrir-ingreso">Agregar ingreso</button>
                </div>
            @endif
        </div>
        <div class="grid-table-section">
            <div class="grid-table-head">
                <p class="grid-table-count" id="registro-contador" data-total="{{ $totalContador }}" data-etiqueta="{{ $etiquetaContador }}">{{ number_format($totalContador) }} {{ $etiquetaContador }}</p>
            </div>
            <div class="programas-grid-wrap">
            <table class="programas-grid registro-grid" id="registro-grid-ingresos">
                <thead>
                    <tr>
                        <th class="programas-grid__th registro-grid__th--tipo" aria-label="Tipo">Tipo</th>
                        <th class="programas-grid__th registro-grid__th--almacen">Almacén</th>
                        <th class="programas-grid__th registro-grid__th--material">Material</th>
                        <th class="programas-grid__th registro-grid__th--fecha">Fecha</th>
                        <th class="programas-grid__th">Código PL</th>
                        <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                        <th class="programas-grid__th">Factura</th>
                        <th class="programas-grid__th">Guía</th>
                        <th class="programas-grid__th registro-grid__th--pdf">PDF</th>
                        <th class="programas-grid__th">Observación</th>
                        <th class="programas-grid__th proveedor-grid__th--actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingresos as $ingreso)
                        @php
                            $guiaIngreso = $ingreso->guia ?: '';
                            $facturaIngreso = $ingreso->factura ?: '';
                            $codigoIngreso = ($ingreso->codigo_pl ?? '') !== 'Varios' ? ($ingreso->codigo_pl ?? '') : '';
                        @endphp
                        <tr
                            class="registro-fila registro-fila--ingreso"
                            data-fecha="{{ $ingreso->fecha?->format('Y-m-d') }}"
                            data-almacen="{{ $ingreso->almacen?->idAlmacen }}"
                            data-codigo="{{ strtolower($codigoIngreso) }}"
                            data-factura="{{ strtolower($facturaIngreso) }}"
                            data-guia="{{ strtolower($guiaIngreso) }}"
                        >
                            <td class="registro-grid__td--tipo">
                                <span class="registro-tipo-mark registro-tipo-mark--ingreso" title="Ingreso">Ingreso</span>
                            </td>
                            <td class="registro-grid__td--almacen">{{ $ingreso->almacen?->codigo }}</td>
                            <td class="programas-grid__material registro-grid__td--material">{{ $ingreso->material }}</td>
                            <td class="registro-grid__td--fecha">{{ $ingreso->fecha?->format('d/m/Y') }}</td>
                            <td class="programas-grid__code">{{ $ingreso->codigo_pl }}</td>
                            <td class="programas-grid__qty">{{ number_format((float) $ingreso->cantidad, 2, '.', '') }}</td>
                            <td>{{ $ingreso->factura ?: '—' }}</td>
                            <td>{{ $ingreso->guia ?: '—' }}</td>
                            <td class="registro-grid__td--pdf">
                                @if ($ingreso->archivoOneDriveUrl)
                                    <a href="{{ $ingreso->archivoOneDriveUrl }}" class="registro-pdf-link" target="_blank" rel="noopener noreferrer">Ver</a>
                                @endif
                            </td>
                            <td>{{ $ingreso->observacion }}</td>
                            @include('registro._fila_acciones', [
                                'tipoDetalle' => 'movimiento',
                                'tipoEditar' => 'ingreso',
                                'id' => $ingreso->idMovimiento,
                                'puedeEditar' => $puedeEditar,
                                'puedeEliminar' => $puedeEliminar,
                                'destroyRoute' => route('registro.movimiento.destroy', $ingreso->idMovimiento),
                                'confirmMessage' => $ingreso->es_lote
                                    ? 'Se anulará este ingreso y todos sus materiales asociados. Esta acción no se puede deshacer.'
                                    : 'Se anulará este ingreso. Esta acción no se puede deshacer.',
                                'confirmTitle' => 'Anular ingreso',
                                'editLabel' => 'Editar ingreso',
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="11" class="data-table__empty">No hay ingresos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.pagination', ['paginator' => $ingresos, 'itemLabel' => 'ingresos'])
        </div>
    @endif

    @if ($tabActiva === 'consumos')
        <div class="registro-toolbar-wrap">
            @include('registro._filtros')
            @if ($puedeCrear)
                <div class="registro-toolbar__actions">
                    <button type="button" class="btn btn--purple btn--sm" id="registro-abrir-egreso">Agregar consumo</button>
                </div>
            @endif
        </div>
        <div class="grid-table-section">
            <div class="grid-table-head">
                <p class="grid-table-count" id="registro-contador" data-total="{{ $totalContador }}" data-etiqueta="{{ $etiquetaContador }}">{{ number_format($totalContador) }} {{ $etiquetaContador }}</p>
            </div>
            <div class="programas-grid-wrap">
            <table class="programas-grid registro-grid registro-grid--consumos" id="registro-grid-egresos">
                <thead>
                    <tr>
                        <th class="programas-grid__th">Fecha</th>
                        <th class="programas-grid__th">Almacén</th>
                        <th class="programas-grid__th">Programa</th>
                        <th class="programas-grid__th">Producto</th>
                        <th class="programas-grid__th">Motivo</th>
                        <th class="programas-grid__th registro-grid__th--material">Material</th>
                        <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                        <th class="programas-grid__th">Observación</th>
                        <th class="programas-grid__th proveedor-grid__th--actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consumos as $mov)
                        @php
                            $materialEgreso = $mov->material ?? ($mov->materialPl?->nomMaterial);
                            $codigoEgreso = $mov->codigo_pl ?? ($mov->materialPl?->CodigoPL);
                        @endphp
                        <tr
                            class="registro-fila"
                            data-fecha="{{ $mov->fecha?->format('Y-m-d') }}"
                            data-almacen="{{ $mov->almacen?->idAlmacen }}"
                            data-codigo="{{ strtolower($codigoEgreso ?? '') }}"
                        >
                            <td>{{ $mov->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $mov->almacen?->codigo }}</td>
                            <td>{{ $mov->programa?->nomPrograma ?: '—' }}</td>
                            <td>{{ $mov->presentacion?->nomPresentacion ?: '—' }}</td>
                            <td>{{ $mov->tipoConsumo?->nomTipoConsumo ?: '—' }}</td>
                            <td class="programas-grid__material registro-grid__td--material">{{ $materialEgreso }}</td>
                            <td class="programas-grid__qty">{{ number_format((float) $mov->cantidad, 2, '.', '') }}</td>
                            <td>{{ $mov->observacion ?: '—' }}</td>
                            @include('registro._fila_acciones', [
                                'tipoDetalle' => 'movimiento',
                                'tipoEditar' => 'egreso',
                                'id' => $mov->idMovimiento,
                                'puedeEditar' => $puedeEditar,
                                'puedeEliminar' => $puedeEliminar,
                                'destroyRoute' => route('registro.movimiento.destroy', $mov->idMovimiento),
                                'confirmMessage' => ! empty($mov->es_lote)
                                    ? 'Se anulará este consumo y todos sus materiales asociados. Esta acción no se puede deshacer.'
                                    : 'Se anulará este consumo. Esta acción no se puede deshacer.',
                                'confirmTitle' => 'Anular consumo',
                                'editLabel' => 'Editar consumo',
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="9" class="data-table__empty">No hay consumos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.pagination', ['paginator' => $consumos, 'itemLabel' => 'consumos'])
        </div>
    @endif

    @if ($tabActiva === 'traslados')
        <div class="registro-toolbar-wrap">
            @include('registro._filtros')
            @if ($puedeCrear)
                <div class="registro-toolbar__actions">
                    <button type="button" class="btn btn--purple btn--sm" id="registro-abrir-traslado">Agregar traslado</button>
                </div>
            @endif
        </div>
        <div class="grid-table-section">
            <div class="grid-table-head">
                <p class="grid-table-count" id="registro-contador" data-total="{{ $totalContador }}" data-etiqueta="{{ $etiquetaContador }}">{{ number_format($totalContador) }} {{ $etiquetaContador }}</p>
            </div>
            <div class="programas-grid-wrap">
            <table class="programas-grid registro-grid" id="registro-grid-traslados">
                <thead>
                    <tr>
                        <th class="programas-grid__th">Fecha</th>
                        <th class="programas-grid__th">Origen</th>
                        <th class="programas-grid__th">Destino</th>
                        <th class="programas-grid__th">Código PL</th>
                        <th class="programas-grid__th registro-grid__th--material">Material</th>
                        <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                        <th class="programas-grid__th">Factura</th>
                        <th class="programas-grid__th">Guía</th>
                        <th class="programas-grid__th registro-grid__th--pdf">PDF</th>
                        <th class="programas-grid__th">Observación</th>
                        <th class="programas-grid__th proveedor-grid__th--actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($traslados as $traslado)
                        @php
                            $guiaTraslado = $traslado->guia ?: ($traslado->factura ? '' : ($traslado->facturaGuia ?: ''));
                        @endphp
                        <tr
                            class="registro-fila"
                            data-fecha="{{ $traslado->fecha?->format('Y-m-d') }}"
                            data-almacen-origen="{{ $traslado->almacenOrigen?->idAlmacen }}"
                            data-almacen-destino="{{ $traslado->almacenDestino?->idAlmacen }}"
                            data-codigo="{{ strtolower($traslado->materialPl?->CodigoPL ?? '') }}"
                            data-factura="{{ strtolower($traslado->factura ?? '') }}"
                            data-guia="{{ strtolower($guiaTraslado) }}"
                        >
                            <td>{{ $traslado->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $traslado->almacenOrigen?->codigo }}</td>
                            <td>{{ $traslado->almacenDestino?->codigo }}</td>
                            <td class="programas-grid__code">{{ $traslado->materialPl?->CodigoPL }}</td>
                            <td class="programas-grid__material registro-grid__td--material">{{ $traslado->materialPl?->nomMaterial }}</td>
                            <td class="programas-grid__qty">{{ number_format((float) $traslado->cantidad, 2, '.', '') }}</td>
                            <td>{{ $traslado->factura ?: '—' }}</td>
                            <td>{{ $guiaTraslado ?: '—' }}</td>
                            <td class="registro-grid__td--pdf">
                                @if ($traslado->archivoOneDriveUrl)
                                    <a href="{{ $traslado->archivoOneDriveUrl }}" class="registro-pdf-link" target="_blank" rel="noopener noreferrer">Ver</a>
                                @endif
                            </td>
                            <td>{{ $traslado->observacion }}</td>
                            @include('registro._fila_acciones', [
                                'tipoDetalle' => 'traslado',
                                'tipoEditar' => 'traslado',
                                'id' => $traslado->idTraslado,
                                'puedeEditar' => $puedeEditar,
                                'puedeEliminar' => $puedeEliminar,
                                'destroyRoute' => route('registro.traslado.destroy', $traslado->idTraslado),
                                'confirmMessage' => 'Se anulará este traslado. Esta acción no se puede deshacer.',
                                'confirmTitle' => 'Anular traslado',
                                'editLabel' => 'Editar traslado',
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="11" class="data-table__empty">No hay traslados registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.pagination', ['paginator' => $traslados, 'itemLabel' => 'traslados'])
        </div>
    @endif
</section>
</div>

@if ($puedeCrear || $puedeEditar)
    @include('registro._modal_ingreso')
    @include('registro._modal_egreso')
    @include('registro._modal_traslado')
@endif

@include('registro._modal_detalle')
@endsection

@push('head')
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#6d4ba8">
@endpush

@push('scripts')
@php
    $registroConsumoOld = [
        'id_programa' => old('id_programa'),
        'id_presentacion' => old('id_presentacion'),
        'id_material_pl' => old('id_material_pl'),
        'id_almacen' => old('id_almacen'),
        'lineas' => old('lineas', []),
        'conformacion_dentro' => old('conformacion_dentro'),
        'pallets_fuera' => old('pallets_fuera'),
    ];

    $registroMaterialesMap = collect($materiales ?? [])->mapWithKeys(function ($m) {
        return [
            (string) $m->idMaterialPL => [
                'codigo' => $m->CodigoPL,
                'nombre' => $m->nomMaterial,
            ],
        ];
    });
@endphp
<script>
    window.registroMaterialUrl = @json(route('registro.material-por-codigo'));
    window.registroMaterialesBuscarUrl = @json(route('registro.materiales.buscar'));
    window.registroStockUrl = @json(route('registro.stock-disponible'));
    window.registroConsumoProgramasUrl = @json(route('registro.consumo.programas'));
    window.registroConsumoPresentacionesUrl = @json(route('registro.consumo.presentaciones'));
    window.registroConsumoMaterialesUrl = @json(route('registro.consumo.materiales'));
    window.registroConsumoHintUrl = @json(route('registro.consumo.hint'));
    window.registroIngresoLeerXmlUrl = @json(route('registro.ingreso.leer-xml'));
    window.registroIngresoLeerExcelUrl = @json(route('registro.ingreso.leer-excel'));
    window.registroDetalleMovimientoUrl = @json(route('registro.movimiento.detalle', ['idMovimiento' => '__ID__']));
    window.registroDetalleTrasladoUrl = @json(route('registro.traslado.detalle', ['idTraslado' => '__ID__']));
    window.registroIngresoUpdateUrl = @json(route('registro.ingreso.update', ['idMovimiento' => '__ID__']));
    window.registroEgresoUpdateUrl = @json(route('registro.egreso.update', ['idMovimiento' => '__ID__']));
    window.registroTrasladoUpdateUrl = @json(route('registro.traslado.update', ['idTraslado' => '__ID__']));
    window.registroTabActiva = @json($tabActiva);
    window.registroIngresoLineasOld = @json(old('lineas', []));
    window.registroConsumoOld = @json($registroConsumoOld);
    window.registroMaterialesMap = @json($registroMaterialesMap);
    window.registroOfflineCsrfUrl = @json(route('registro.offline.csrf'));
</script>
<script src="{{ asset('js/offline-queue.js') }}" defer></script>
<script src="{{ asset('js/offline-sync-registro.js') }}?v=3" defer></script>
<script src="{{ asset('js/grid-query-filters.js') }}" defer></script>
<script src="{{ asset('js/registro.js') }}?v=15" defer></script>
@endpush
