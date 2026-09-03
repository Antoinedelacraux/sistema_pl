<label class="field">
    <span class="field__label">Factura</span>
    <input
        type="text"
        class="field__input @error('factura') is-invalid @enderror"
        name="factura"
        value="{{ old('factura') }}"
        maxlength="50"
        placeholder="Número de factura"
        autocomplete="off"
    >
</label>

<label class="field">
    <span class="field__label">Guía</span>
    <input
        type="text"
        class="field__input @error('guia') is-invalid @enderror"
        name="guia"
        value="{{ old('guia') }}"
        maxlength="50"
        placeholder="Ej. T023 N° 0003027"
        autocomplete="off"
    >
</label>

<label class="field field--full">
    <span class="field__label">Archivo PDF</span>
    <input
        type="file"
        class="field__input field__input--file @error('archivo_pdf') is-invalid @enderror"
        name="archivo_pdf"
        accept="application/pdf,.pdf"
    >
    <span class="field__hint">Opcional. Se envía a OneDrive mediante Power Automate (máx. {{ number_format(config('power_automate.max_upload_kb')) }} KB).</span>
</label>
