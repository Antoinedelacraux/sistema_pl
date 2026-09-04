<nav class="receta-tabs" aria-label="Secciones de receta">
    <a href="{{ route('programas.index') }}" class="receta-tabs__link @if(($recetaTab ?? 'materiales') === 'materiales') is-active @endif">Materiales</a>
    <a href="{{ route('programas.presentacion-params.index') }}" class="receta-tabs__link @if(($recetaTab ?? '') === 'params') is-active @endif">Pallets y conformación</a>
</nav>
