<div class="modal" id="proveedor-columnas-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-proveedor-columnas></div>
    <div class="modal__panel modal__panel--sm" role="dialog" aria-modal="true" aria-labelledby="proveedor-columnas-modal-title">
        <header class="modal__head">
            <h2 id="proveedor-columnas-modal-title" class="modal__title">Columnas visibles</h2>
            <button type="button" class="modal__close" data-cerrar-modal-proveedor-columnas aria-label="Cerrar">&times;</button>
        </header>

        <div class="modal__form">
            <p class="modal__hint">Desmarca las columnas que quieres ocultar en la grilla.</p>

            <ul class="columnas-list" id="proveedor-columnas-list">
                @foreach ($columnasConfig as $columna)
                    <li class="columnas-list__item">
                        <label class="columnas-list__label">
                            <input
                                type="checkbox"
                                class="columnas-list__check"
                                value="{{ $columna['key'] }}"
                                data-col-toggle="{{ $columna['key'] }}"
                                checked
                                @disabled(!empty($columna['fija']))
                            >
                            <span>{{ $columna['label'] }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>

            <footer class="modal__actions">
                <button type="button" class="btn btn--outline btn--sm" id="proveedor-columnas-restaurar">Mostrar todas</button>
                <button type="button" class="btn btn--purple btn--sm" data-cerrar-modal-proveedor-columnas>Listo</button>
            </footer>
        </div>
    </div>
</div>
