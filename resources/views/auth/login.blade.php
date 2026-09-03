@extends('layouts.app')

@section('title', 'Ingresar')

@section('content')
<main class="login-page">
    <section class="login-visual">
        <div class="login-visual__bg"></div>
        <div class="login-visual__shade"></div>

        <div class="login-visual__copy">
            <p class="login-visual__tag">Grupo Rocío</p>
            <h1 class="login-visual__title">Materiales de packing,<br>bajo control.</h1>
            <p class="login-visual__lead">
                Planeamiento, stock y movimientos para las líneas de empaque en campo y planta.
            </p>
        </div>
    </section>

    <section class="login-panel" aria-labelledby="login-title">
        <div class="login-panel__box">
            <header class="login-panel__head">
                <img
                    src="{{ asset('images/logo-talsa-login.png') }}"
                    alt="Talsa Grape Farms"
                    class="login-panel__logo"
                    width="280"
                    height="120"
                    decoding="async"
                >
                <p class="login-panel__eyebrow">Acceso interno</p>
                <h2 id="login-title" class="login-panel__title">Ingresar al sistema</h2>
                <p class="login-panel__sub">Control de materiales · Planeamiento e Información</p>
            </header>

            <form method="POST" action="{{ route('login.store') }}" class="login-form" novalidate>
                @csrf

                @include('partials.flash')

                <label class="field">
                    <span class="field__label">Correo</span>
                    <input
                        class="field__input @error('usuario') is-invalid @enderror"
                        type="email"
                        name="usuario"
                        value="{{ old('usuario') }}"
                        placeholder="Ingresar correo"
                        autocomplete="username"
                        autofocus
                        required
                    >
                </label>

                <label class="field">
                    <span class="field__label">Contraseña</span>
                    <div class="field__password">
                        <input
                            class="field__input @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Ingresar contraseña"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="field__toggle"
                            data-toggle-password="password"
                            aria-label="Mostrar contraseña"
                        >Ver</button>
                    </div>
                </label>

                <button type="submit" class="btn btn--login btn--wide">Ingresar</button>
            </form>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.textContent = hidden ? 'Ocultar' : 'Ver';
        });
    });
</script>
@endpush
