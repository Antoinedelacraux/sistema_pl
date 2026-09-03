<div class="inventario-export {{ $class ?? 'inventario-export--toolbar' }}">
    <span class="inventario-export__label">Exportar</span>
    <div class="inventario-export__actions">
        <a
            href="{{ $pdfUrl }}"
            class="export-icon export-icon--pdf"
            title="Exportar PDF con filtros actuales"
            aria-label="Exportar PDF"
        >
            @include('partials.icons.file-pdf')
        </a>
        <a
            href="{{ $excelUrl }}"
            class="export-icon export-icon--excel"
            title="Exportar Excel con filtros actuales"
            aria-label="Exportar Excel"
        >
            @include('partials.icons.file-excel')
        </a>
    </div>
</div>
