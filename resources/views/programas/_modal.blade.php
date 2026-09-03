<div class="modal" id="receta-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="receta-modal-title">
        <header class="modal__head">
            <h2 id="receta-modal-title" class="modal__title">Agregar a receta</h2>
            <button type="button" class="modal__close" data-cerrar-modal aria-label="Cerrar">&times;</button>
        </header>

        <form method="POST" action="{{ route('programas.store') }}" class="modal__form" id="receta-form" enctype="multipart/form-data">
            @csrf

            <div class="modal__grid">
                <label class="field">
                    <span class="field__label">Mercado</span>
                    <select class="field__input @error('id_mercado') is-invalid @enderror" name="id_mercado" required>
                        <option value="">Seleccionar</option>
                        @foreach ($mercados as $item)
                            <option value="{{ $item->idMercado }}" @selected((int) old('id_mercado') === (int) $item->idMercado)>{{ $item->nomMercado }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Cliente</span>
                    <select class="field__input @error('id_cliente') is-invalid @enderror" name="id_cliente" required>
                        <option value="">Seleccionar</option>
                        @foreach ($clientes as $item)
                            <option value="{{ $item->idCliente }}" @selected((int) old('id_cliente') === (int) $item->idCliente)>{{ $item->nomCliente }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Variedad</span>
                    <select class="field__input @error('id_variedad') is-invalid @enderror" name="id_variedad" required>
                        <option value="">Seleccionar</option>
                        @foreach ($variedades as $item)
                            <option value="{{ $item->idVariedad }}" @selected((int) old('id_variedad') === (int) $item->idVariedad)>{{ $item->nomVariedad }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Programa</span>
                    <select class="field__input @error('id_programa') is-invalid @enderror" name="id_programa" required>
                        <option value="">Seleccionar</option>
                        @foreach ($programas as $item)
                            <option value="{{ $item->idPrograma }}" @selected((int) old('id_programa') === (int) $item->idPrograma)>{{ $item->nomPrograma }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Presentación</span>
                    <select class="field__input @error('id_presentacion') is-invalid @enderror" name="id_presentacion" required>
                        <option value="">Seleccionar</option>
                        @foreach ($presentaciones as $item)
                            <option value="{{ $item->idPresentacion }}" @selected((int) old('id_presentacion') === (int) $item->idPresentacion)>{{ $item->nomPresentacion }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Presentación Nisira</span>
                    <select class="field__input @error('id_pre_nisira') is-invalid @enderror" name="id_pre_nisira" required>
                        <option value="">Seleccionar</option>
                        @foreach ($preNisiras as $item)
                            <option value="{{ $item->idPreNisira }}" @selected((int) old('id_pre_nisira') === (int) $item->idPreNisira)>{{ $item->nomNisira }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Código de planificación</span>
                    <input
                        type="text"
                        class="field__input @error('codigo_pl') is-invalid @enderror"
                        name="codigo_pl"
                        id="receta-codigo-pl"
                        value="{{ old('codigo_pl') }}"
                        required
                        autocomplete="off"
                    >
                </label>

                <label class="field field--full">
                    <span class="field__label">Material</span>
                    <input
                        type="text"
                        class="field__input @error('nom_material') is-invalid @enderror"
                        name="nom_material"
                        id="receta-nom-material"
                        value="{{ old('nom_material') }}"
                        required
                    >
                </label>

                <label class="field">
                    <span class="field__label">Cantidad X Caja</span>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        class="field__input @error('cantidad') is-invalid @enderror"
                        name="cantidad"
                        value="{{ old('cantidad', '1') }}"
                        required
                    >
                </label>

                <label class="field">
                    <span class="field__label">Uso</span>
                    <select class="field__input @error('id_uso') is-invalid @enderror" name="id_uso" required>
                        <option value="">Seleccionar</option>
                        @foreach ($usos as $item)
                            <option value="{{ $item->idUso }}" @selected((int) old('id_uso') === (int) $item->idUso)>{{ $item->nomUso }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Situado</span>
                    <select class="field__input @error('id_situado') is-invalid @enderror" name="id_situado" required>
                        <option value="">Seleccionar</option>
                        @foreach ($situados as $item)
                            <option value="{{ $item->idSituado }}" @selected((int) old('id_situado') === (int) $item->idSituado)>{{ $item->nomSituado }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Familia</span>
                    <select class="field__input @error('id_familia') is-invalid @enderror" name="id_familia" required>
                        <option value="">Seleccionar</option>
                        @foreach ($familias as $item)
                            <option value="{{ $item->idFamilia }}" @selected((int) old('id_familia') === (int) $item->idFamilia)>{{ $item->nomFamilia }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field field--full">
                    <span class="field__label">Imagen</span>
                    <input
                        type="file"
                        class="field__input field__input--file @error('imagen') is-invalid @enderror"
                        name="imagen"
                        id="receta-imagen"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                    >
                    <span class="field__hint">Opcional. Se comprime automáticamente (WebP) para reducir peso.</span>
                    <div class="receta-imagen-preview" id="receta-imagen-preview" hidden>
                        <img src="" alt="Vista previa" id="receta-imagen-preview-img">
                    </div>
                </label>
            </div>

            @if ($errors->any())
                <div class="alert alert--error modal__errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal>Cancelar</button>
                <button type="submit" class="btn btn--purple">Guardar</button>
            </footer>
        </form>
    </div>
</div>
