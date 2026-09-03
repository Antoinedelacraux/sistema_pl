@extends('layouts.shell')

@section('title', 'Inicio')

@section('heading')
    {{ auth()->user()->persona?->nombreCompleto() ?? auth()->user()->Usuario }}
@endsection
@section('crumbs')
    {{ auth()->user()->persona?->area?->nomArea ?? '—' }}
    · {{ auth()->user()->persona?->cargo?->nomCargo ?? '—' }}
@endsection

@section('page')
<div class="inicio-page">
    @include('partials.kpis-inicio')

    <div class="inicio-dashboard">
        <section class="inicio-panel">
            <header class="inicio-panel__head">
                <h2 class="inicio-panel__title">Stock por almacén</h2>
                @if (auth()->user()->puedeVer('inventario'))
                    <a href="{{ route('inventario.index') }}" class="inicio-panel__link">Ver inventario</a>
                @endif
            </header>
            <div class="inicio-almacen-grid">
                @foreach ($stockPorAlmacen as $almacen)
                    <article class="inicio-almacen-card">
                        <div class="inicio-almacen-card__head">
                            <h3 class="inicio-almacen-card__code">{{ $almacen->codigo }}</h3>
                            <span class="inicio-almacen-card__name">{{ $almacen->nombre }}</span>
                        </div>
                        <dl class="inicio-almacen-card__stats">
                            <div>
                                <dt>Con stock</dt>
                                <dd>{{ number_format($almacen->con_stock) }}</dd>
                            </div>
                            <div class="inicio-almacen-card__stat--danger">
                                <dt>Crítico</dt>
                                <dd>{{ number_format($almacen->critico) }}</dd>
                            </div>
                            <div class="inicio-almacen-card__stat--warn">
                                <dt>Alerta</dt>
                                <dd>{{ number_format($almacen->alerta) }}</dd>
                            </div>
                        </dl>
                        @if (auth()->user()->puedeVer('inventario'))
                            <a href="{{ route('inventario.index', ['almacen' => $almacen->codigo]) }}" class="inicio-almacen-card__link">Abrir {{ $almacen->codigo }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @if ($accesosRapidos !== [])
            <section class="inicio-panel">
                <header class="inicio-panel__head">
                    <h2 class="inicio-panel__title">Accesos rápidos</h2>
                </header>
                <div class="inicio-quick-grid">
                    @foreach ($accesosRapidos as $acceso)
                        <a href="{{ route($acceso['route']) }}" class="inicio-quick-card">
                            <span class="inicio-quick-card__label">{{ $acceso['label'] }}</span>
                            <span class="inicio-quick-card__desc">{{ $acceso['descripcion'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <div class="inicio-dashboard inicio-dashboard--tables">
        <section class="inicio-panel">
            <header class="inicio-panel__head">
                <h2 class="inicio-panel__title">Materiales críticos y en alerta</h2>
                @if (auth()->user()->puedeVer('inventario'))
                    <a href="{{ route('inventario.index', ['almacen' => 'SFC']) }}" class="inicio-panel__link">Ver todos</a>
                @endif
            </header>
            <div class="grid-table-section">
                <div class="grid-table-head">
                    <p class="grid-table-count">{{ number_format($materialesCriticos->count()) }} materiales críticos y en alerta</p>
                </div>
                <div class="programas-grid-wrap inicio-table-wrap">
                    <table class="programas-grid inicio-table">
                    <thead>
                        <tr>
                            <th class="programas-grid__th">Almacén</th>
                            <th class="programas-grid__th">Código PL</th>
                            <th class="programas-grid__th">Material</th>
                            <th class="programas-grid__th programas-grid__th--num">Stock</th>
                            <th class="programas-grid__th programas-grid__th--num">Mínimo</th>
                            <th class="programas-grid__th">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($materialesCriticos as $fila)
                            <tr>
                                <td>{{ $fila->almacen }}</td>
                                <td class="programas-grid__code">{{ $fila->codigo_pl }}</td>
                                <td class="programas-grid__material">{{ $fila->material }}</td>
                                <td class="programas-grid__qty inicio-stock-cell inicio-stock-cell--{{ $fila->estado }}">{{ number_format($fila->stock, 2, '.', '') }}</td>
                                <td class="programas-grid__qty">{{ number_format($fila->stock_minimo, 2, '.', '') }}</td>
                                <td>
                                    <span class="stock-badge stock-badge--{{ $fila->estado === 'critico' ? 'critico' : 'alerta' }}">
                                        {{ $fila->estado === 'critico' ? 'Crítico' : 'Alerta' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="data-table__empty">No hay materiales críticos ni en alerta.</td></tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="inicio-panel">
            <header class="inicio-panel__head">
                <h2 class="inicio-panel__title">Últimos movimientos</h2>
                @if (auth()->user()->puedeVer('registro'))
                    <a href="{{ route('registro.index') }}" class="inicio-panel__link">Ver registro</a>
                @endif
            </header>
            <div class="grid-table-section">
                <div class="grid-table-head">
                    <p class="grid-table-count">{{ number_format($ultimosMovimientos->count()) }} movimientos recientes</p>
                </div>
                <div class="programas-grid-wrap inicio-table-wrap">
                    <table class="programas-grid inicio-table">
                    <thead>
                        <tr>
                            <th class="programas-grid__th">Tipo</th>
                            <th class="programas-grid__th">Fecha</th>
                            <th class="programas-grid__th">Almacén</th>
                            <th class="programas-grid__th">Código PL</th>
                            <th class="programas-grid__th">Material</th>
                            <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ultimosMovimientos as $mov)
                            <tr>
                                <td>
                                    <span class="registro-tipo-mark registro-tipo-mark--{{ $mov->tipo_clase }}">{{ $mov->tipo }}</span>
                                </td>
                                <td>{{ $mov->fecha?->format('d/m/Y') }}</td>
                                <td>{{ $mov->detalle }}</td>
                                <td class="programas-grid__code">{{ $mov->codigo_pl }}</td>
                                <td class="programas-grid__material">{{ $mov->material }}</td>
                                <td class="programas-grid__qty">{{ number_format($mov->cantidad, 2, '.', '') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="data-table__empty">No hay movimientos registrados.</td></tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
