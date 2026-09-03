<section class="app-kpis app-kpis--page usuarios-kpis" aria-label="Indicadores de usuarios">
    @include('partials.kpi-card', [
        'variant' => 'total',
        'value' => $contadores['total'],
        'label' => 'Usuarios',
        'hint' => 'Cuentas registradas',
    ])
    @include('partials.kpi-card', [
        'variant' => 'ok',
        'value' => $contadores['activos'],
        'label' => 'Activos',
        'hint' => 'Con acceso al sistema',
    ])
    @include('partials.kpi-card', [
        'variant' => 'warn',
        'value' => $contadores['inactivos'],
        'label' => 'Inactivos',
        'hint' => 'Cuentas suspendidas',
    ])
    @include('partials.kpi-card', [
        'variant' => 'activity',
        'value' => $contadores['administradores'],
        'label' => 'Administradores',
        'hint' => 'Con control total',
    ])
</section>
