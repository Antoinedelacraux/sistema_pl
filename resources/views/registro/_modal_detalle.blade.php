<div class="modal" id="registro-detalle-modal" aria-hidden="true">
    <div class="modal__backdrop" data-cerrar-modal-registro="detalle"></div>
    <div class="modal__panel modal__panel--wide" role="dialog" aria-modal="true" aria-labelledby="registro-detalle-titulo">
        <header class="modal__head">
            <div class="registro-detalle-head">
                <span class="registro-detalle-tipo" id="registro-detalle-tipo" hidden></span>
                <h2 class="modal__title" id="registro-detalle-titulo">Detalle de operación</h2>
            </div>
            <button type="button" class="modal__close" data-cerrar-modal-registro="detalle" aria-label="Cerrar">&times;</button>
        </header>

        <div class="modal__body registro-detalle-body">
            <div class="registro-detalle-estado" id="registro-detalle-estado" hidden>Cargando detalle...</div>

            <div id="registro-detalle-contenido" hidden>
                <section class="registro-detalle-meta">
                    <dl class="registro-detalle-dl">
                        <div class="registro-detalle-dl__item">
                            <dt>Usuario</dt>
                            <dd id="registro-detalle-usuario">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item">
                            <dt>Fecha operación</dt>
                            <dd id="registro-detalle-fecha-operacion">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item">
                            <dt>Registrado</dt>
                            <dd id="registro-detalle-fecha-registro">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-almacen-wrap">
                            <dt>Almacén</dt>
                            <dd id="registro-detalle-almacen">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-origen-wrap" hidden>
                            <dt>Origen</dt>
                            <dd id="registro-detalle-origen">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-destino-wrap" hidden>
                            <dt>Destino</dt>
                            <dd id="registro-detalle-destino">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-motivo-wrap" hidden>
                            <dt>Motivo</dt>
                            <dd id="registro-detalle-motivo">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-programa-wrap" hidden>
                            <dt>Programa</dt>
                            <dd id="registro-detalle-programa">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-presentacion-wrap" hidden>
                            <dt>Producto</dt>
                            <dd id="registro-detalle-presentacion">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-factura-wrap" hidden>
                            <dt>Factura</dt>
                            <dd id="registro-detalle-factura">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-guia-wrap" hidden>
                            <dt>Guía</dt>
                            <dd id="registro-detalle-guia">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item" id="registro-detalle-pdf-wrap" hidden>
                            <dt>Documento</dt>
                            <dd id="registro-detalle-pdf">—</dd>
                        </div>
                        <div class="registro-detalle-dl__item registro-detalle-dl__item--full" id="registro-detalle-observacion-wrap" hidden>
                            <dt>Observación</dt>
                            <dd id="registro-detalle-observacion">—</dd>
                        </div>
                    </dl>
                </section>

                <section class="registro-detalle-section">
                    <h3 class="registro-detalle-section__title">Materiales</h3>
                    <div class="programas-grid-wrap">
                        <table class="programas-grid registro-detalle-grid">
                            <thead>
                                <tr>
                                    <th class="programas-grid__th">Código PL</th>
                                    <th class="programas-grid__th">Material</th>
                                    <th class="programas-grid__th registro-detalle-grid__th--almacen" id="registro-detalle-col-almacen">Almacén</th>
                                    <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody id="registro-detalle-lineas"></tbody>
                        </table>
                    </div>
                </section>

                <section class="registro-detalle-section" id="registro-detalle-vinculados-wrap" hidden>
                    <h3 class="registro-detalle-section__title">Movimientos de stock generados</h3>
                    <div class="programas-grid-wrap">
                        <table class="programas-grid registro-detalle-grid">
                            <thead>
                                <tr>
                                    <th class="programas-grid__th">Tipo</th>
                                    <th class="programas-grid__th">Almacén</th>
                                    <th class="programas-grid__th programas-grid__th--num">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody id="registro-detalle-vinculados"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <footer class="modal__actions">
            <button type="button" class="btn btn--outline" data-cerrar-modal-registro="detalle">Cerrar</button>
        </footer>
    </div>
</div>
