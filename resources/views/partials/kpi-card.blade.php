<article class="app-kpi app-kpi--{{ $variant ?? 'stock' }}">
    <div class="app-kpi__icon" aria-hidden="true">
        @include('partials.kpi-icon', ['variant' => $variant ?? 'stock'])
    </div>
    <div class="app-kpi__content">
        <p class="app-kpi__value">{{ is_numeric($value ?? null) ? number_format($value) : ($value ?? '0') }}</p>
        <p class="app-kpi__label">{{ $label ?? '' }}</p>
        @if (! empty($hint))
            <p class="app-kpi__hint">{{ $hint }}</p>
        @endif
    </div>
    <div class="app-kpi__wave" aria-hidden="true"></div>
</article>
