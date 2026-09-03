<div class="app-kpis inventario-kpis" aria-label="Indicadores del almacén">
    @include('partials.kpi-card', [
        'variant' => 'stock',
        'value' => $contadores['con_stock'],
        'label' => 'Con stock',
        'hint' => 'Materiales disponibles',
    ])
    @include('partials.kpi-card', [
        'variant' => 'danger',
        'value' => $contadores['critico'],
        'label' => 'Crítico',
        'hint' => 'Requiere atención inmediata',
    ])
    @include('partials.kpi-card', [
        'variant' => 'warn',
        'value' => $contadores['alerta'],
        'label' => 'Alerta',
        'hint' => 'Nivel bajo de stock',
    ])
    @include('partials.kpi-card', [
        'variant' => 'ok',
        'value' => $contadores['saludable'],
        'label' => 'Saludable',
        'hint' => 'Stock en buen nivel',
    ])
</div>
