<section class="app-kpis app-kpis--page" aria-label="Indicadores de stock">
    @include('partials.kpi-card', [
        'variant' => 'total',
        'value' => $contadores['total'],
        'label' => 'Materiales',
        'hint' => 'Total registrados',
    ])
    @include('partials.kpi-card', [
        'variant' => 'danger',
        'value' => $contadores['critico'],
        'label' => 'Stock crítico',
        'hint' => 'Requiere atención inmediata',
    ])
    @include('partials.kpi-card', [
        'variant' => 'warn',
        'value' => $contadores['alerta'],
        'label' => 'Stock en alerta',
        'hint' => 'Nivel bajo de stock',
    ])
    @include('partials.kpi-card', [
        'variant' => 'ok',
        'value' => $contadores['saludable'],
        'label' => 'Stock saludable',
        'hint' => 'Stock en buen nivel',
    ])
</section>
