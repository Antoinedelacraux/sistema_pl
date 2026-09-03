<section class="app-kpis app-kpis--page" aria-label="Indicadores generales">
    @include('partials.kpi-card', [
        'variant' => 'stock',
        'value' => $kpis['materiales_con_stock'],
        'label' => 'Materiales con stock',
        'hint' => 'de '.number_format($kpis['materiales_total']).' activos',
    ])
    @include('partials.kpi-card', [
        'variant' => 'danger',
        'value' => $kpis['stock_critico'],
        'label' => 'Stock crítico',
        'hint' => 'Sin stock en almacén',
    ])
    @include('partials.kpi-card', [
        'variant' => 'warn',
        'value' => $kpis['stock_alerta'],
        'label' => 'Stock en alerta',
        'hint' => 'Bajo el mínimo',
    ])
    @include('partials.kpi-card', [
        'variant' => 'activity',
        'value' => $kpis['movimientos_hoy'],
        'label' => 'Movimientos hoy',
        'hint' => now()->format('d/m/Y'),
    ])
</section>
