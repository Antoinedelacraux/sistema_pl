<div class="modal" id="proveedor-agregar-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-proveedor-agregar></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="proveedor-agregar-modal-title">
        <header class="modal__head">
            <h2 id="proveedor-agregar-modal-title" class="modal__title">Agregar proveedor</h2>
            <button type="button" class="modal__close" data-cerrar-modal-proveedor-agregar aria-label="Cerrar">&times;</button>
        </header>

        <form method="POST" action="{{ route('proveedor.store') }}" class="modal__form" id="proveedor-agregar-form">
            @csrf
            <input type="hidden" name="_proveedor_modal" value="agregar">
            <input type="hidden" name="id_material_pl" id="proveedor-agregar-id-material" value="{{ old('id_material_pl') }}">

            <div class="modal__grid">
                <label class="field field--full">
                    <span class="field__label">Proveedor</span>
                    <input
                        type="text"
                        class="field__input @error('nom_proveedor') is-invalid @enderror"
                        name="nom_proveedor"
                        id="proveedor-agregar-nom-proveedor"
                        value="{{ old('nom_proveedor') }}"
                        list="proveedor-nombres-list"
                        placeholder="TGF, SAFCO u otro"
                        maxlength="50"
                        required
                    >
                </label>

                <label class="field">
                    <span class="field__label">Código</span>
                    <input
                        type="text"
                        class="field__input @error('codigo') is-invalid @enderror"
                        name="codigo"
                        id="proveedor-agregar-codigo"
                        value="{{ old('codigo') }}"
                        maxlength="30"
                        required
                        autocomplete="off"
                    >
                </label>

                <label class="field">
                    <span class="field__label">Material</span>
                    <input
                        type="text"
                        class="field__input @error('nom_material') is-invalid @enderror"
                        name="nom_material"
                        id="proveedor-agregar-nom-material"
                        value="{{ old('nom_material') }}"
                        maxlength="255"
                        required
                    >
                </label>

                <div class="field field--full field-mode" data-field-mode="codigo-pl">
                    <div class="field-mode__head">
                        <span class="field__label">Código planeamiento</span>
                        <div class="mode-switch" role="group" aria-label="Modo código planeamiento">
                            <button type="button" class="mode-switch__btn" data-mode="select">Seleccionar</button>
                            <button type="button" class="mode-switch__btn is-active" data-mode="search">Buscador</button>
                        </div>
                    </div>

                    <div class="field-mode__panel" data-panel="select" hidden>
                        <select class="field__input" id="proveedor-agregar-codigo-pl-select">
                            <option value="">Seleccionar código PL</option>
                        </select>
                    </div>

                    <div class="field-mode__panel" data-panel="search">
                        <input
                            type="text"
                            class="field__input"
                            id="proveedor-agregar-codigo-pl-search"
                            placeholder="Buscar por código PL (nuestro planeamiento)"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="field field--full field-mode" data-field-mode="material-pl">
                    <div class="field-mode__head">
                        <span class="field__label">Material planeamiento</span>
                        <div class="mode-switch" role="group" aria-label="Modo material planeamiento">
                            <button type="button" class="mode-switch__btn" data-mode="select">Seleccionar</button>
                            <button type="button" class="mode-switch__btn is-active" data-mode="search">Buscador</button>
                        </div>
                    </div>

                    <div class="field-mode__panel" data-panel="select" hidden>
                        <select class="field__input" id="proveedor-agregar-material-pl-select">
                            <option value="">Seleccionar material planeamiento</option>
                        </select>
                    </div>

                    <div class="field-mode__panel" data-panel="search">
                        <input
                            type="text"
                            class="field__input"
                            id="proveedor-agregar-material-pl-search"
                            placeholder="Buscar material de planeamiento"
                            autocomplete="off"
                        >
                    </div>
                </div>
            </div>

            @if ($errors->any() && old('_proveedor_modal') === 'agregar')
                <div class="alert alert--error modal__errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal-proveedor-agregar>Cancelar</button>
                <button type="submit" class="btn btn--purple">Guardar</button>
            </footer>
        </form>
    </div>
</div>
