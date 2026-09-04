<div class="modal" id="receta-param-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-receta-param></div>
    <div class="modal__panel" role="dialog" aria-modal="true">
        <header class="modal__head">
            <h2 class="modal__title" id="receta-param-title">Pallets y conformación</h2>
            <button type="button" class="modal__close" data-cerrar-receta-param>&times;</button>
        </header>
        <form method="POST" action="{{ route('programas.presentacion-params.store') }}" class="modal__form" id="receta-param-form">
            @csrf
            <input type="hidden" name="id_programa" id="receta-param-programa">
            <input type="hidden" name="id_presentacion" id="receta-param-presentacion">
            <div class="modal__grid">
                <div class="field field--full">
                    <span class="field__label">Programa</span>
                    <p class="field__static" id="receta-param-programa-nom">—</p>
                </div>
                <div class="field field--full">
                    <span class="field__label">Producto</span>
                    <p class="field__static" id="receta-param-presentacion-nom">—</p>
                </div>
                <label class="field">
                    <span class="field__label">Conformación (Dentro)</span>
                    <input type="number" class="field__input @error('conformacion') is-invalid @enderror" name="conformacion" id="receta-param-conformacion" min="0" step="0.01" value="{{ old('conformacion') }}">
                </label>
                <label class="field">
                    <span class="field__label">Pallets (Fuera)</span>
                    <input type="number" class="field__input @error('pallets') is-invalid @enderror" name="pallets" id="receta-param-pallets" min="0" step="0.01" value="{{ old('pallets') }}">
                </label>
            </div>
            @if ($errors->any())
                <div class="alert alert--error modal__errors">{{ $errors->first() }}</div>
            @endif
            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-receta-param>Cancelar</button>
                <button type="submit" class="btn btn--purple">Guardar</button>
            </footer>
        </form>
    </div>
</div>
