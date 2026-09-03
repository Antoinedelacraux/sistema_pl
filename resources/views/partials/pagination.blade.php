@if ($paginator->total() > 0)
    @php
        $paginaActual = $paginator->currentPage();
        $totalPaginas = $paginator->lastPage();
        $inicio = max(1, $paginaActual - 2);
        $fin = min($totalPaginas, $paginaActual + 2);
        $etiqueta = $itemLabel ?? 'registros';
    @endphp
    <nav class="grid-pagination" aria-label="Paginación">
        <p class="grid-pagination__summary">
            Mostrando {{ number_format($paginator->firstItem()) }} a {{ number_format($paginator->lastItem()) }} de {{ number_format($paginator->total()) }} {{ $etiqueta }}
        </p>

        <div class="grid-pagination__controls">
            <span class="grid-pagination__per-page">50 por página</span>

            @if ($paginator->onFirstPage())
                <span class="grid-pagination__nav is-disabled" aria-disabled="true" aria-label="Página anterior">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="grid-pagination__nav" aria-label="Página anterior">‹</a>
            @endif

            @if ($inicio > 1)
                <a href="{{ $paginator->url(1) }}" class="grid-pagination__page">1</a>
                @if ($inicio > 2)
                    <span class="grid-pagination__ellipsis">…</span>
                @endif
            @endif

            @for ($page = $inicio; $page <= $fin; $page++)
                @if ($page === $paginaActual)
                    <span class="grid-pagination__page is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="grid-pagination__page">{{ $page }}</a>
                @endif
            @endfor

            @if ($fin < $totalPaginas)
                @if ($fin < $totalPaginas - 1)
                    <span class="grid-pagination__ellipsis">…</span>
                @endif
                <a href="{{ $paginator->url($totalPaginas) }}" class="grid-pagination__page">{{ $totalPaginas }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="grid-pagination__nav" aria-label="Página siguiente">›</a>
            @else
                <span class="grid-pagination__nav is-disabled" aria-disabled="true" aria-label="Página siguiente">›</span>
            @endif
        </div>
    </nav>
@endif
