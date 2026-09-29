<div class="modal" id="registro-egreso-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-registro="egreso"></div>
    <div class="modal__panel modal__panel--wide registro-modal-panel" role="dialog" aria-modal="true">
        <header class="modal__head">
            <h2 class="modal__title" id="registro-egreso-title">Agregar consumo</h2>
            <button type="button" class="modal__close" data-cerrar-modal-registro="egreso">&times;</button>
        </header>
        <form method="POST" action="{{ route('registro.egreso.store') }}" class="modal__form" id="registro-egreso-form" data-store-action="{{ route('registro.egreso.store') }}">
            @csrf
            <input type="hidden" name="_method" id="registro-egreso-method" value="POST" disabled>
            <div class="modal__grid">
                <label class="field">
                    <span class="field__label">Fecha</span>
                    <input type="date" class="field__input @error('fecha') is-invalid @enderror" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span class="field__label">Almacén</span>
                    <select class="field__input @error('id_almacen') is-invalid @enderror" name="id_almacen" id="egreso-id-almacen" required>
                        <option value="">Seleccionar</option>
                        @foreach ($almacenes as $almacen)
                            <option value="{{ $almacen->idAlmacen }}" @selected((int) old('id_almacen') === (int) $almacen->idAlmacen)>{{ $almacen->codigo }} — {{ $almacen->nomAlmacen }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">Motivo</span>
                    <select class="field__input @error('id_tipo_consumo') is-invalid @enderror" name="id_tipo_consumo" id="egreso-id-tipo-consumo" required>
                        <option value="">Seleccionar</option>
                        @foreach ($tiposConsumo as $tipoConsumo)
                            <option value="{{ $tipoConsumo->idTipoConsumo }}" @selected((int) old('id_tipo_consumo') === (int) $tipoConsumo->idTipoConsumo)>{{ $tipoConsumo->nomTipoConsumo }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="field field-mode" data-field-mode="egreso-pg">
                    <div class="field-mode__head">
                        <span class="field__label">Programa General</span>
                        <div class="mode-switch" role="group" aria-label="Modo programa general">
                            <button type="button" class="mode-switch__btn is-active" data-mode="select">Seleccionar</button>
                            <button type="button" class="mode-switch__btn" data-mode="search">Buscador</button>
                        </div>
                    </div>
                    <div class="field-mode__panel" data-panel="select">
                        <select class="field__input" id="egreso-id-programa-general" required>
                            <option value="">Seleccionar programa general</option>
                            @foreach ($programasGenerales as $programaGeneral)
                                <option value="{{ $programaGeneral->idProgramaGeneral }}">{{ $programaGeneral->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-mode__panel" data-panel="search" hidden>
                        <div class="egreso-pg-combo" id="egreso-pg-combo">
                            <input
                                type="search"
                                class="field__input"
                                id="egreso-pg-buscar"
                                placeholder="Escriba para buscar…"
                                autocomplete="off"
                            >
                            <ul class="egreso-pg-combo__list" id="egreso-pg-list" hidden></ul>
                        </div>
                    </div>
                </div>
                <label class="field field--full">
                    <span class="field__label">Programa</span>
                    <select class="field__input @error('id_programa') is-invalid @enderror" name="id_programa" id="egreso-id-programa" required disabled>
                        <option value="">Seleccione un programa general</option>
                    </select>
                </label>
                <label class="field field--full">
                    <span class="field__label">Producto</span>
                    <select class="field__input @error('id_presentacion') is-invalid @enderror" name="id_presentacion" id="egreso-id-presentacion" required disabled>
                        <option value="">Seleccione un programa</option>
                    </select>
                </label>
                <div class="field field--full registro-consumo-materiales" id="egreso-materiales-wrap">
                    <div class="registro-consumo-materiales__head">
                        <span class="field__label">Materiales del producto</span>
                        <div class="registro-consumo-extra">
                            <label class="field registro-consumo-extra__field">
                                <span class="field__label">Conformación (Dentro)</span>
                                <input
                                    type="number"
                                    class="field__input @error('conformacion_dentro') is-invalid @enderror"
                                    name="conformacion_dentro"
                                    id="egreso-conformacion-dentro"
                                    value="{{ old('conformacion_dentro') }}"
                                    min="0"
                                    step="0.01"
                                    placeholder="—"
                                >
                            </label>
                            <label class="field registro-consumo-extra__field">
                                <span class="field__label">Pallets (Fuera)</span>
                                <input
                                    type="number"
                                    class="field__input @error('pallets_fuera') is-invalid @enderror"
                                    name="pallets_fuera"
                                    id="egreso-pallets-fuera"
                                    value="{{ old('pallets_fuera') }}"
                                    min="0"
                                    step="0.01"
                                    placeholder="—"
                                >
                            </label>
                        </div>
                    </div>
                    <p class="field__hint" id="egreso-materiales-hint">Seleccione un producto para cargar los materiales desde Receta.</p>
                    <p class="field__hint registro-consumo-plan-limite" id="egreso-plan-limite-hint" hidden></p>
                    <div class="registro-consumo-stock-alert" id="egreso-stock-alert" hidden role="alert"></div>
                    <div class="registro-consumo-tabla-wrap" id="egreso-materiales-tabla-wrap" hidden>
                        <table class="registro-consumo-tabla" id="egreso-materiales-tabla">
                            <colgroup>
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--material">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--code">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--obsr">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--cant">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--pers">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--total">
                                <col class="registro-consumo-tabla__col registro-consumo-tabla__col--stock">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="registro-consumo-tabla__th--material">Material</th>
                                    <th class="registro-consumo-tabla__th--code">Código PL</th>
                                    <th class="registro-consumo-tabla__th--obsr">Obsr.</th>
                                    <th class="registro-consumo-tabla__th--num registro-consumo-tabla__th--stack" title="Cantidad por caja">Cant.<br>/Ca.</th>
                                    <th class="registro-consumo-tabla__th--num" title="Personalizado">Pers.</th>
                                    <th class="registro-consumo-tabla__th--num">Total</th>
                                    <th class="registro-consumo-tabla__th--num">Stock</th>
                                </tr>
                            </thead>
                            <tbody id="egreso-materiales-body"></tbody>
                        </table>
                    </div>
                </div>
                <label class="field field--full">
                    <span class="field__label">Observación del movimiento</span>
                    <input type="text" class="field__input @error('observacion') is-invalid @enderror" name="observacion" value="{{ old('observacion') }}" maxlength="500">
                </label>
            </div>
            @if ($errors->any() && in_array(($tab ?? ''), ['consumos', 'egresos'], true))
                <div class="alert alert--error modal__errors">
                    <ul class="registro-modal-errors-list">
                        @foreach ($errors->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <footer class="modal__actions">
                <button type="button" class="btn btn--outline" data-cerrar-modal-registro="egreso">Cancelar</button>
                <button type="submit" class="btn btn--purple" id="registro-egreso-guardar">Guardar</button>
            </footer>
        </form>
    </div>
</div>
