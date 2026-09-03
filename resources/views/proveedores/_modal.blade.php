<div class="modal" id="proveedor-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-proveedor></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="proveedor-modal-title">
        <header class="modal__head">
            <h2 id="proveedor-modal-title" class="modal__title">Editar proveedor</h2>
            <button type="button" class="modal__close" data-cerrar-modal-proveedor aria-label="Cerrar">&times;</button>
        </header>

        <form method="POST" action="{{ old('_proveedor_action', '#') }}" class="modal__form" id="proveedor-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="_proveedor_nro_fila" id="proveedor-nro-fila" value="{{ old('_proveedor_nro_fila') }}">

            <div class="modal__grid">
                <label class="field">
                    <span class="field__label">Familia</span>
                    <select class="field__input @error('id_familia') is-invalid @enderror" name="id_familia" id="proveedor-id-familia">
                        <option value="">Sin familia</option>
                        @foreach ($familias as $item)
                            <option value="{{ $item->idFamilia }}" @selected((int) old('id_familia') === (int) $item->idFamilia)>{{ $item->nomFamilia }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Código planeamiento</span>
                    <input type="text" class="field__input" id="proveedor-codigo-pl" readonly>
                </label>

                <label class="field field--full">
                    <span class="field__label">Material planeamiento</span>
                    <input type="text" class="field__input" id="proveedor-nom-material-pl" readonly>
                </label>

                @foreach ($columnasProveedor as $proveedor)
                    @php
                        $key = $proveedor['key'];
                        $nombre = $proveedor['nombre'];
                        $oldProv = old('proveedores.'.$key, []);
                    @endphp
                    <div class="modal-proveedor-block field--full" data-proveedor-block data-nom-proveedor="{{ $nombre }}">
                        <div class="modal-proveedor-block__head">
                            <span class="modal-proveedor-block__title">{{ $nombre }}</span>
                            <label class="modal-proveedor-block__eliminar">
                                <input
                                    type="checkbox"
                                    name="proveedores[{{ $key }}][eliminar]"
                                    value="1"
                                    data-field="eliminar"
                                    @checked(! empty($oldProv['eliminar']))
                                >
                                Eliminar
                            </label>
                        </div>

                        <input type="hidden" name="proveedores[{{ $key }}][nombre]" value="{{ $nombre }}">

                        <div class="modal-proveedor-block__grid">
                            <label class="field">
                                <span class="field__label">Código {{ $nombre }}</span>
                                <input
                                    type="text"
                                    class="field__input @error('proveedores.'.$key.'.codigo') is-invalid @enderror"
                                    name="proveedores[{{ $key }}][codigo]"
                                    data-field="codigo"
                                    value="{{ $oldProv['codigo'] ?? '' }}"
                                    maxlength="30"
                                    autocomplete="off"
                                >
                            </label>

                            <label class="field">
                                <span class="field__label">Material {{ $nombre }}</span>
                                <input
                                    type="text"
                                    class="field__input @error('proveedores.'.$key.'.nom_material') is-invalid @enderror"
                                    name="proveedores[{{ $key }}][nom_material]"
                                    data-field="nom_material"
                                    value="{{ $oldProv['nom_material'] ?? '' }}"
                                    maxlength="255"
                                >
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($errors->any() && old('_proveedor_modal') !== 'agregar')
                <div class="alert alert--error modal__errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal-proveedor>Cancelar</button>
                <button type="submit" class="btn btn--purple">Guardar</button>
            </footer>
        </form>
    </div>
</div>
