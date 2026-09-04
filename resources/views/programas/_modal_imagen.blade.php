<div class="modal" id="receta-imagen-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-imagen-modal></div>
    <div class="modal__panel modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="receta-imagen-modal-title">
        <header class="modal__head">
            <h2 id="receta-imagen-modal-title" class="modal__title">Cambiar imagen</h2>
            <button type="button" class="modal__close" data-cerrar-imagen-modal aria-label="Cerrar">&times;</button>
        </header>

        <form method="POST" action="" class="modal__form" id="receta-imagen-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <input type="hidden" name="mercado" value="{{ request('mercado') }}">
            <input type="hidden" name="cliente" value="{{ request('cliente') }}">
            <input type="hidden" name="familia" value="{{ request('familia') }}">
            <input type="hidden" name="programa" value="{{ request('programa') }}">
            <input type="hidden" name="presentacion" value="{{ request('presentacion') }}">
            <input type="hidden" name="buscar" value="{{ request('buscar') }}">

            <p class="receta-imagen-modal__meta" id="receta-imagen-meta"></p>

            <div class="receta-imagen-modal__actual" id="receta-imagen-actual-wrap" hidden>
                <span class="field__label">Imagen actual</span>
                <img src="" alt="Imagen actual" id="receta-imagen-actual" class="receta-imagen-modal__thumb">
            </div>

            <label class="field field--full">
                <span class="field__label">Nueva imagen</span>
                <input
                    type="file"
                    class="field__input field__input--file @error('imagen') is-invalid @enderror"
                    name="imagen"
                    id="receta-imagen-nueva"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                >
                <span class="field__hint">Opcional. Se comprime automáticamente (WebP).</span>
            </label>

            <div class="receta-imagen-preview" id="receta-imagen-nueva-preview" hidden>
                <span class="field__label">Vista previa</span>
                <img src="" alt="Vista previa" id="receta-imagen-nueva-preview-img">
            </div>

            <label class="field field--checkbox receta-imagen-modal__quitar" id="receta-imagen-quitar-wrap" hidden>
                <input type="checkbox" name="eliminar_imagen" value="1" id="receta-imagen-quitar" @checked(old('eliminar_imagen'))>
                <span>Quitar imagen actual</span>
            </label>

            @if ($errors->has('imagen') || $errors->has('eliminar_imagen'))
                <div class="alert alert--error modal__errors">
                    {{ $errors->first('imagen') ?: $errors->first('eliminar_imagen') }}
                </div>
            @endif

            <footer class="modal__foot">
                <button type="button" class="btn btn--outline" data-cerrar-imagen-modal>Cancelar</button>
                <button type="submit" class="btn btn--purple">Guardar imagen</button>
            </footer>
        </form>
    </div>
</div>
