@if (session('status'))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        window.TalsaToast && window.TalsaToast.show(@json(session('status')), 'ok');
    });
    </script>
    @endpush
@endif

@if ($errors->any())
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.TalsaToast) return;
        @foreach ($errors->all() as $error)
        window.TalsaToast.show(@json($error), 'error');
        @endforeach
    });
    </script>
    @endpush
@endif
