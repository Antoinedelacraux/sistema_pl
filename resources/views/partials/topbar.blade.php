<header class="topbar">
    <div class="topbar__lead">
        <button
            type="button"
            class="topbar__menu"
            id="sidebar-open"
            aria-label="Abrir menú"
            aria-controls="sidebar"
            aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
        </button>
        <div class="topbar__titles">
            <h1 class="topbar__title">@yield('heading', 'Inicio')</h1>
            @hasSection('crumbs')
                <p class="topbar__crumbs">@yield('crumbs')</p>
            @endif
        </div>
    </div>

    <div class="topbar__actions">
        <button
            type="button"
            class="topbar__notif"
            aria-label="Notificaciones"
            title="Notificaciones"
        >
            @include('partials.icons.notificaciones')
        </button>
        <button
            type="button"
            class="topbar__theme"
            id="theme-toggle"
            aria-label="Modo oscuro"
            title="Modo oscuro"
        >
            <svg class="topbar__theme-icon topbar__theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <svg class="topbar__theme-icon topbar__theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
        </button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--outline btn--sm">Salir</button>
        </form>
    </div>
</header>
