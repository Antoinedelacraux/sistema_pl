<section class="app-kpis app-kpis--page" aria-label="Indicadores de auditoría">
    @include('partials.kpi-card', [
        'variant' => 'activity',
        'value' => $contadores['eventos_hoy'],
        'label' => 'Eventos hoy',
        'hint' => now()->format('d/m/Y'),
    ])
    @include('partials.kpi-card', [
        'variant' => 'stock',
        'value' => $contadores['logins_hoy'],
        'label' => 'Inicios de sesión',
        'hint' => 'Accesos exitosos hoy',
    ])
    @include('partials.kpi-card', [
        'variant' => 'danger',
        'value' => $contadores['fallos_7d'],
        'label' => 'Intentos fallidos',
        'hint' => 'Últimos 7 días',
    ])
    @include('partials.kpi-card', [
        'variant' => 'warn',
        'value' => $contadores['total'],
        'label' => 'Total registrado',
        'hint' => 'Historial completo',
    ])
</section>
