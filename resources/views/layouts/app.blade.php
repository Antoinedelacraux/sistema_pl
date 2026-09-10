<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ingresar') · Talsa Grape Farms</title>
    <link rel="icon" href="{{ asset('images/logo-talsa-login.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-talsa-login.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap"></noscript>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=32">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v=9">
    @stack('head')
</head>
<body class="@yield('body-class', 'page-guest')">
<script>
(function () {
    try {
        if (document.body.classList.contains('page-app') && localStorage.getItem('talsa-theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
        }
    } catch (e) {}
})();
</script>
    @yield('content')
    <div id="toast-root" class="toast-root" aria-live="polite" aria-atomic="true"></div>
    <script src="{{ asset('js/toasts.js') }}" defer></script>
    <script src="{{ asset('js/confirm-dialog.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
