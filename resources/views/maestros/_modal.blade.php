<div class="modal" id="maestros-modal" aria-hidden="true">
    <div class="modal__backdrop" data-maestros-cerrar></div>
    <div class="modal__panel modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="maestros-modal-title">
        <header class="modal__head">
            <h2 id="maestros-modal-title" class="modal__title">Agregar {{ $definicion['label'] }}</h2>
            <button type="button" class="modal__close" data-maestros-cerrar aria-label="Cerrar">&times;</button>
        </header>

        <form
            method="POST"
            action="{{ route('maestros.store', $tab) }}"
            class="modal__form"
            id="maestros-form"
        >
            @csrf
            <input type="hidden" name="_method" id="maestros-form-method" value="POST" disabled>
            <input type="hidden" name="buscar" value="{{ $buscar }}">

            <label class="field">
                <span class="field__label">Nombre</span>
                <input
                    type="text"
                    class="field__input @error('nombre') is-invalid @enderror"
                    name="nombre"
                    id="maestros-nombre"
                    maxlength="{{ $definicion['maxLength'] }}"
                    required
                    autocomplete="off"
                    value="{{ old('nombre') }}"
                >
            </label>

            <label class="field maestros-modal__estado" id="maestros-estado-wrap" hidden>
                <span class="field__label">Estado</span>
                <select class="field__input @error('estado') is-invalid @enderror" name="estado" id="maestros-estado">
                    <option value="1" @selected((int) old('estado', 1) === 1)>Activo</option>
                    <option value="0" @selected((int) old('estado', 1) === 0)>Inactivo</option>
                </select>
            </label>

            <footer class="modal__foot">
                <button type="button" class="btn btn--outline" data-maestros-cerrar>Cancelar</button>
                <button type="submit" class="btn btn--purple" id="maestros-submit">Guardar</button>
            </footer>
        </form>
    </div>
</div>
