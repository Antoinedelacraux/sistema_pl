<div class="modal" id="registro-traslado-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-registro="traslado"></div>
    <div class="modal__panel" role="dialog" aria-modal="true">
        <header class="modal__head">
            <h2 class="modal__title" id="registro-traslado-title">Agregar traslado</h2>
            <button type="button" class="modal__close" data-cerrar-modal-registro="traslado">&times;</button>
        </header>
        <form method="POST" action="{{ route('registro.traslado.store') }}" class="modal__form" id="registro-traslado-form" data-store-action="{{ route('registro.traslado.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="registro-traslado-method" value="POST" disabled>
            <div class="modal__grid">
                <label class="field">
                    <span class="field__label">Fecha</span>
                    <input type="date" class="field__input @error('fecha') is-invalid @enderror" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span class="field__label">Almacén origen</span>
                    <select class="field__input @error('id_almacen_origen') is-invalid @enderror" name="id_almacen_origen" id="traslado-id-almacen-origen" data-stock-almacen="traslado" required>
                        <option value="">Seleccionar</option>
                        @foreach ($almacenes as $almacen)
                            <option value="{{ $almacen->idAlmacen }}" @selected((int) old('id_almacen_origen') === (int) $almacen->idAlmacen)>{{ $almacen->codigo }} — {{ $almacen->nomAlmacen }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">Almacén destino</span>
                    <select class="field__input @error('id_almacen_destino') is-invalid @enderror" name="id_almacen_destino" required>
                        <option value="">Seleccionar</option>
                        @foreach ($almacenes as $almacen)
                            <option value="{{ $almacen->idAlmacen }}" @selected((int) old('id_almacen_destino') === (int) $almacen->idAlmacen)>{{ $almacen->codigo }} — {{ $almacen->nomAlmacen }}</option>
                        @endforeach
                    </select>
                </label>
                @include('registro._material_fields', ['prefix' => 'traslado'])
                <p class="field field--full registro-stock-hint" id="traslado-stock-hint" hidden>
                    <span>Stock en origen:</span>
                    <strong>0</strong>
                    <span class="stock-badge stock-badge--ok" data-stock-badge>OK</span>
                </p>
                <label class="field">
                    <span class="field__label">Cantidad</span>
                    <input type="number" class="field__input @error('cantidad') is-invalid @enderror" name="cantidad" value="{{ old('cantidad') }}" min="0.01" step="0.01" required>
                </label>
                @include('registro._documento_fields')
                <label class="field field--full">
                    <span class="field__label">Observación</span>
                    <input type="text" class="field__input @error('observacion') is-invalid @enderror" name="observacion" value="{{ old('observacion') }}" maxlength="500">
                </label>
            </div>
            @if ($errors->any() && ($tab ?? '') === 'traslados')
                <div class="alert alert--error modal__errors">{{ $errors->first() }}</div>
            @endif
            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal-registro="traslado">Cancelar</button>
                <button type="submit" class="btn btn--purple" id="registro-traslado-guardar">Guardar</button>
            </footer>
        </form>
    </div>
</div>
