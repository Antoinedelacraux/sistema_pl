<div class="modal" id="registro-ingreso-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-registro="ingreso"></div>
    <div class="modal__panel modal__panel--wide" role="dialog" aria-modal="true">
        <header class="modal__head">
            <h2 class="modal__title" id="registro-ingreso-title">Agregar ingreso</h2>
            <button type="button" class="modal__close" data-cerrar-modal-registro="ingreso">&times;</button>
        </header>
        <form method="POST" action="{{ route('registro.ingreso.store') }}" class="modal__form" id="registro-ingreso-form" data-store-action="{{ route('registro.ingreso.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="registro-ingreso-method" value="POST" disabled>
            <div class="modal__grid">
                <label class="field">
                    <span class="field__label">Fecha</span>
                    <input type="date" class="field__input @error('fecha') is-invalid @enderror" name="fecha" id="ingreso-fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span class="field__label">Almacén</span>
                    <select class="field__input @error('id_almacen') is-invalid @enderror" name="id_almacen" id="ingreso-id-almacen" required>
                        <option value="">Seleccionar</option>
                        @foreach ($almacenes as $almacen)
                            <option value="{{ $almacen->idAlmacen }}" data-codigo="{{ $almacen->codigo }}" @selected((int) old('id_almacen') === (int) $almacen->idAlmacen)>{{ $almacen->codigo }} — {{ $almacen->nomAlmacen }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">Factura</span>
                    <input type="text" class="field__input @error('factura') is-invalid @enderror" name="factura" value="{{ old('factura') }}" maxlength="50" placeholder="Número de factura" autocomplete="off">
                </label>
                <label class="field">
                    <span class="field__label">Guía</span>
                    <input type="text" class="field__input @error('guia') is-invalid @enderror" name="guia" id="ingreso-guia" value="{{ old('guia') }}" maxlength="50" placeholder="Ej. T023 N° 0003027" autocomplete="off">
                </label>
                <div class="registro-ingreso-archivos">
                    <label class="field registro-ingreso-archivos__col">
                        <span class="field__label">Archivo PDF</span>
                        <input type="file" class="field__input field__input--file @error('archivo_pdf') is-invalid @enderror" name="archivo_pdf" id="ingreso-archivo-pdf" accept="application/pdf,.pdf">
                        <span class="field__hint registro-ingreso-archivos__hint">Solo para guardar en OneDrive al registrar.</span>
                    </label>
                    <label class="field registro-ingreso-archivos__col">
                        <span class="field__label">Archivo XML</span>
                        <input type="file" class="field__input field__input--file" id="ingreso-archivo-xml" accept=".xml,application/xml,text/xml">
                        <span class="field__hint registro-ingreso-archivos__hint">Lee guía, códigos proveedor y cantidades. Los mapea con la tabla Proveedor.</span>
                    </label>
                </div>
                <p class="field__hint registro-xml-lectura registro-ingreso-archivos__status" id="ingreso-xml-lectura-status" hidden></p>
                <label class="field field--full">
                    <span class="field__label">Observación</span>
                    <input type="text" class="field__input @error('observacion') is-invalid @enderror" name="observacion" value="{{ old('observacion') }}" maxlength="500">
                </label>
            </div>

            <div class="registro-ingreso-linea">
                <p class="registro-ingreso-linea__title">Agregar material al ingreso</p>
                <div class="modal__grid registro-ingreso-linea__grid">
                    @include('registro._material_fields', ['prefix' => 'ingreso', 'pickerOnly' => true])
                    <div class="field registro-ingreso-cantidad-wrap">
                        <span class="field__label">Cantidad</span>
                        <div class="registro-ingreso-cantidad-row">
                            <input type="number" class="field__input" id="ingreso-linea-cantidad" min="0.01" step="0.01" placeholder="0.00">
                            <button type="button" class="btn btn--purple btn--sm" id="ingreso-linea-anadir">Añadir</button>
                        </div>
                        <span class="field__hint registro-ingreso-linea-hint" id="ingreso-linea-hint" hidden></span>
                    </div>
                </div>
            </div>

            <div class="registro-ingreso-preview" id="registro-ingreso-preview" hidden>
                <p class="registro-ingreso-preview__title">Materiales a ingresar <span id="registro-ingreso-preview-count">0</span></p>
                <div class="registro-ingreso-preview__wrap">
                    <table class="registro-ingreso-preview__table">
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th>Material</th>
                                <th>Código PL</th>
                                <th class="registro-ingreso-preview__th--num">Cantidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="registro-ingreso-preview-body"></tbody>
                    </table>
                </div>
            </div>

            <div id="registro-ingreso-lineas-hidden"></div>

            @if ($errors->any() && ($tab ?? '') === 'ingresos')
                <div class="alert alert--error modal__errors">{{ $errors->first() }}</div>
            @endif
            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal-registro="ingreso">Cancelar</button>
                <button type="submit" class="btn btn--purple" id="registro-ingreso-guardar">Guardar ingreso</button>
            </footer>
        </form>
    </div>
</div>
