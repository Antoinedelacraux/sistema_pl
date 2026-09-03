@php
    $textoNombre = $nombre ?? '—';
    $claseExtra = $class ?? '';
@endphp

@if (! empty($usuarioId))
    <button
        type="button"
        class="usuario-nombre-clic {{ $claseExtra }}"
        data-usuario-tarjeta="{{ $usuarioId }}"
        aria-label="Ver perfil de {{ $textoNombre }}"
    >{{ $textoNombre }}</button>
@else
    <span class="{{ $claseExtra }}">{{ $textoNombre }}</span>
@endif
