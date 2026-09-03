@extends('layouts.app')

@section('body-class', 'page-app')

@section('content')
<div class="app" id="app-shell">
<script>
(function () {
    try {
        var isMobile = window.matchMedia('(max-width: 920px)').matches;
        if (!isMobile && localStorage.getItem('talsa-sidebar-collapsed') === '1') {
            document.getElementById('app-shell').classList.add('is-sidebar-collapsed');
        }
    } catch (e) {}
})();
</script>
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    @include('partials.sidebar')

    <div class="app-main">
        @include('partials.topbar')

        <div class="app-content">
            @yield('page')
        </div>
    </div>
</div>

@include('partials.usuario-tarjeta-popover')
@endsection

@push('scripts')
<script>
    window.usuarioTarjetaUrl = @json(route('usuario.tarjeta', ['usuario' => '__ID__']));
</script>
<script src="{{ asset('js/usuario-tarjeta.js') }}" defer></script>
<script src="{{ asset('js/app-shell.js') }}" defer></script>
@endpush
