(function () {
    var materialUrl = window.registroMaterialUrl || '';
    var stockUrl = window.registroStockUrl || '';
    var consumoPresentacionesUrl = window.registroConsumoPresentacionesUrl || '';
    var consumoMaterialesUrl = window.registroConsumoMaterialesUrl || '';
    var consumoHintUrl = window.registroConsumoHintUrl || '';
    var tabActiva = window.registroTabActiva || 'ingresos';
    var lookupTimer = null;
    var consumoEditId = null;
    var consumoEditMaterialId = null;
    var consumoEditCantidad = null;
    var consumoOverrides = {};

    var modales = {
        ingreso: document.getElementById('registro-ingreso-modal'),
        egreso: document.getElementById('registro-egreso-modal'),
        traslado: document.getElementById('registro-traslado-modal'),
        detalle: document.getElementById('registro-detalle-modal'),
    };

    function abrirModal(tipo) {
        var modal = modales[tipo];
        if (!modal) {
            return;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModal(tipo) {
        var modal = modales[tipo];
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (tipo !== 'detalle') {
            resetModalCreacion(tipo);
        }

        var algunoAbierto = Object.keys(modales).some(function (key) {
            return modales[key] && modales[key].classList.contains('is-open');
        });

        if (!algunoAbierto) {
            document.body.classList.remove('modal-open');
        }
    }

    function resetModalCreacion(tipo) {
        var form = document.getElementById('registro-' + tipo + '-form');
        if (!form) {
            return;
        }

        var methodInput = document.getElementById('registro-' + tipo + '-method');
        var title = document.getElementById('registro-' + tipo + '-title');
        var submitBtn = document.getElementById('registro-' + tipo + '-guardar');

        if (form.dataset.storeAction) {
            form.action = form.dataset.storeAction;
        }

        if (methodInput) {
            methodInput.value = 'POST';
            methodInput.disabled = true;
        }

        if (title) {
            title.textContent = tipo === 'ingreso' ? 'Agregar ingreso' : tipo === 'egreso' ? 'Agregar consumo' : 'Agregar traslado';
        }

        if (submitBtn) {
            submitBtn.textContent = tipo === 'ingreso' ? 'Guardar ingreso' : 'Guardar';
        }

        if (tipo === 'ingreso' && typeof ingresoLineas !== 'undefined') {
            ingresoLineas = [];
            renderIngresoPreview();
            limpiarLineaIngreso();
            ocultarEstadoXmlIngreso();
        }

        if (tipo === 'egreso') {
            consumoEditId = null;
            consumoEditMaterialId = null;
            consumoEditCantidad = null;
            consumoOverrides = {};
            resetConsumoCascada();
        }
    }

    function formatearNumero(valor) {
        return Number(valor || 0).toLocaleString('es-PE', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        });
    }

    function resetConsumoCascada() {
        var presentacion = document.getElementById('egreso-id-presentacion');
        var materialHidden = document.getElementById('egreso-id-material');
        var programa = document.getElementById('egreso-id-programa');

        if (programa) {
            programa.value = '';
        }

        if (presentacion) {
            presentacion.innerHTML = '<option value="">Seleccione un programa</option>';
            presentacion.disabled = true;
            presentacion.value = '';
        }

        if (materialHidden) {
            materialHidden.value = '';
        }

        vaciarTablaMaterialesConsumo('Seleccione una presentación para cargar los materiales.');
    }

    function vaciarTablaMaterialesConsumo(mensaje) {
        var body = document.getElementById('egreso-materiales-body');
        var wrap = document.getElementById('egreso-materiales-tabla-wrap');
        var hint = document.getElementById('egreso-materiales-hint');

        if (body) {
            body.innerHTML = '';
        }
        if (wrap) {
            wrap.hidden = true;
        }
        if (hint) {
            hint.hidden = false;
            hint.textContent = mensaje || 'Seleccione una presentación para cargar los materiales.';
        }
    }

    function overrideDeMaterial(idMaterial) {
        var clave = String(idMaterial);
        if (Object.prototype.hasOwnProperty.call(consumoOverrides, clave)) {
            return consumoOverrides[clave];
        }

        if (consumoEditMaterialId && String(consumoEditMaterialId) === clave && consumoEditCantidad != null) {
            return null;
        }

        return '';
    }

    function factorConsumo(inputId) {
        var input = document.getElementById(inputId);
        if (!input) {
            return 1;
        }
        var texto = String(input.value || '').trim();
        if (texto === '' || Number(texto) <= 0) {
            return 1;
        }
        return Number(texto);
    }

    function factorCajasDesdeTabla(body) {
        var comun = body.querySelector('[data-caja-factor][data-uso="comun"]');
        var alternativo = body.querySelector('[data-caja-factor][data-uso="alternativo"]');
        var valorComun = comun && String(comun.value || '').trim() !== '' && Number(comun.value) > 0
            ? Number(comun.value)
            : 0;
        var valorAlternativo = alternativo && String(alternativo.value || '').trim() !== '' && Number(alternativo.value) > 0
            ? Number(alternativo.value)
            : 0;

        if (valorComun > 0) {
            return valorComun;
        }
        if (valorAlternativo > 0) {
            return valorAlternativo;
        }
        return 1;
    }

    function recalcularTotalesConsumo() {
        var body = document.getElementById('egreso-materiales-body');
        if (!body) {
            return;
        }

        var factorCajas = factorCajasDesdeTabla(body);
        var factorPallets = factorConsumo('egreso-pallets-fuera');

        body.querySelectorAll('tr[data-consumo-row]').forEach(function (tr) {
            var totalEl = tr.querySelector('[data-consumo-total]');
            var cantCaja = Number(tr.getAttribute('data-cantidad-caja') || 0);
            var usaPallets = tr.getAttribute('data-usa-pallets') === '1';
            var factor = usaPallets ? factorPallets : factorCajas;
            var total = cantCaja * factor;

            if (totalEl) {
                totalEl.textContent = formatearNumero(total);
            }
        });
    }

    function renderTablaMaterialesConsumo(items) {
        var body = document.getElementById('egreso-materiales-body');
        var wrap = document.getElementById('egreso-materiales-tabla-wrap');
        var hint = document.getElementById('egreso-materiales-hint');

        if (!body || !wrap) {
            return;
        }

        body.innerHTML = '';

        if (!items.length) {
            wrap.hidden = true;
            if (hint) {
                hint.hidden = false;
                hint.textContent = 'Esta presentación no tiene materiales activos en Receta.';
            }
            return;
        }

        items.forEach(function (item, index) {
            var idMaterial = String(item.id_material_pl || '');
            var override = overrideDeMaterial(idMaterial);
            if (override === null) {
                var defaultQty = Number(item.cantidad_por_caja || 0);
                var savedQty = Number(consumoEditCantidad);
                override = Math.abs(savedQty - defaultQty) > 0.001 ? savedQty.toFixed(2) : '';
            }
            var stock = item.stock == null ? null : Number(item.stock);
            var usaPallets = !!item.usa_pallets;
            var esCajaEditable = !!item.es_caja_editable;
            var usoNorm = String(item.nom_uso || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
            if (usoNorm.indexOf('alternativ') === 0) {
                usoNorm = 'alternativo';
            } else if (usoNorm.indexOf('comun') === 0) {
                usoNorm = 'comun';
            }
            var tr = document.createElement('tr');
            tr.setAttribute('data-consumo-row', '1');
            tr.setAttribute('data-cantidad-caja', String(item.cantidad_por_caja || 0));
            tr.setAttribute('data-usa-pallets', usaPallets ? '1' : '0');
            if (consumoEditMaterialId && String(consumoEditMaterialId) === idMaterial) {
                tr.className = (tr.className ? tr.className + ' ' : '') + 'is-edit-row';
            }
            if (esCajaEditable) {
                tr.classList.add('is-caja-editable');
            }

            var tdMaterial = document.createElement('td');
            tdMaterial.className = 'registro-consumo-tabla__material';
            tdMaterial.textContent = item.nom_material || '—';

            var tdCodigo = document.createElement('td');
            tdCodigo.className = 'registro-consumo-tabla__code';
            tdCodigo.textContent = item.codigo_pl || '—';

            var tdCaja = document.createElement('td');
            tdCaja.className = 'registro-consumo-tabla__qty';
            tdCaja.textContent = formatearNumero(item.cantidad_por_caja || 0);

            var tdOtra = document.createElement('td');
            var hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'lineas[' + index + '][id_material_pl]';
            hiddenId.value = idMaterial;
            var input = document.createElement('input');
            input.type = 'number';
            input.className = 'field__input';
            input.min = '0';
            input.step = '0.01';
            input.placeholder = '';
            input.value = esCajaEditable ? override : '';
            if (esCajaEditable) {
                input.name = 'lineas[' + index + '][cantidad]';
                input.setAttribute('data-consumo-override', idMaterial);
                input.setAttribute('data-caja-factor', '1');
                input.setAttribute('data-uso', usoNorm);
                input.addEventListener('input', function () {
                    consumoOverrides[idMaterial] = input.value;
                    recalcularTotalesConsumo();
                });
            } else {
                input.disabled = true;
                input.readOnly = true;
                input.tabIndex = -1;
                input.setAttribute('aria-hidden', 'true');
            }
            tdOtra.appendChild(hiddenId);
            tdOtra.appendChild(input);

            var tdTotal = document.createElement('td');
            tdTotal.className = 'registro-consumo-tabla__qty registro-consumo-tabla__total';
            tdTotal.setAttribute('data-consumo-total', '1');
            tdTotal.textContent = '0';

            var tdStock = document.createElement('td');
            tdStock.className = 'registro-consumo-tabla__qty registro-consumo-tabla__stock';
            if (stock == null) {
                tdStock.textContent = '—';
            } else {
                tdStock.textContent = formatearNumero(stock);
                if (stock <= 0) {
                    tdStock.classList.add('is-critico');
                }
            }

            tr.appendChild(tdMaterial);
            tr.appendChild(tdCodigo);
            tr.appendChild(tdCaja);
            tr.appendChild(tdOtra);
            tr.appendChild(tdTotal);
            tr.appendChild(tdStock);
            body.appendChild(tr);
        });

        wrap.hidden = false;
        if (hint) {
            hint.hidden = true;
            hint.textContent = '';
        }

        recalcularTotalesConsumo();
    }

    function cargarPresentacionesConsumo(idPrograma, idPresentacionSeleccionada) {
        var presentacion = document.getElementById('egreso-id-presentacion');

        if (!presentacion) {
            return Promise.resolve();
        }

        vaciarTablaMaterialesConsumo('Seleccione una presentación para cargar los materiales.');

        if (!idPrograma || !consumoPresentacionesUrl) {
            presentacion.innerHTML = '<option value="">Seleccione un programa</option>';
            presentacion.disabled = true;
            return Promise.resolve();
        }

        presentacion.disabled = true;
        presentacion.innerHTML = '<option value="">Cargando...</option>';

        return fetch(consumoPresentacionesUrl + '?id_programa=' + encodeURIComponent(idPrograma), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var items = Array.isArray(data.presentaciones) ? data.presentaciones : [];
                presentacion.innerHTML = '<option value="">Seleccionar presentación</option>';
                items.forEach(function (item) {
                    var opt = document.createElement('option');
                    opt.value = String(item.id_presentacion);
                    opt.textContent = item.nom_presentacion || ('#' + item.id_presentacion);
                    presentacion.appendChild(opt);
                });
                presentacion.disabled = items.length === 0;
                if (idPresentacionSeleccionada) {
                    presentacion.value = String(idPresentacionSeleccionada);
                }
            })
            .catch(function () {
                presentacion.innerHTML = '<option value="">Error al cargar</option>';
                presentacion.disabled = true;
            });
    }

    function cargarMaterialesConsumo(idPrograma, idPresentacion) {
        if (!idPrograma || !idPresentacion || !consumoMaterialesUrl) {
            vaciarTablaMaterialesConsumo('Seleccione una presentación para cargar los materiales.');
            return Promise.resolve();
        }

        var wrap = document.getElementById('egreso-materiales-tabla-wrap');
        var hint = document.getElementById('egreso-materiales-hint');
        if (hint) {
            hint.hidden = false;
            hint.textContent = 'Cargando materiales...';
        }
        if (wrap) {
            wrap.hidden = true;
        }

        var almacen = document.getElementById('egreso-id-almacen');
        var url = consumoMaterialesUrl
            + '?id_programa=' + encodeURIComponent(idPrograma)
            + '&id_presentacion=' + encodeURIComponent(idPresentacion);

        if (almacen && almacen.value) {
            url += '&id_almacen=' + encodeURIComponent(almacen.value);
        }

        if (consumoEditId) {
            url += '&excluir_id_movimiento=' + encodeURIComponent(consumoEditId);
        }

        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                renderTablaMaterialesConsumo(Array.isArray(data.materiales) ? data.materiales : []);
            })
            .catch(function () {
                vaciarTablaMaterialesConsumo('No se pudieron cargar los materiales.');
            });
    }

    function recargarMaterialesConsumoActuales() {
        var programa = document.getElementById('egreso-id-programa');
        var presentacion = document.getElementById('egreso-id-presentacion');
        if (!programa || !presentacion || !presentacion.value) {
            return;
        }
        cargarMaterialesConsumo(programa.value, presentacion.value);
    }

    function syncMaterial(prefix) {
        var select = document.querySelector('[data-material-select="' + prefix + '"]');
        var nomInput = document.querySelector('[data-material-nom="' + prefix + '"]');
        var searchInput = document.querySelector('[data-material-search="' + prefix + '"]');

        if (!select || !select.value) {
            if (nomInput) {
                nomInput.value = '';
            }
            return;
        }

        var option = select.options[select.selectedIndex];
        if (nomInput) {
            nomInput.value = option.getAttribute('data-nom-material-pl') || '';
        }
        if (searchInput) {
            searchInput.value = option.getAttribute('data-codigo-pl') || '';
        }

        actualizarStockHint(prefix);
    }

    function buscarMaterialPorCodigo(prefix) {
        var searchInput = document.querySelector('[data-material-search="' + prefix + '"]');
        var select = document.querySelector('[data-material-select="' + prefix + '"]');

        if (!searchInput || !select || !materialUrl) {
            return;
        }

        var codigo = searchInput.value.trim();
        if (codigo.length < 4) {
            return;
        }

        fetch(materialUrl + '?codigo=' + encodeURIComponent(codigo), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.encontrado) {
                    select.value = String(data.id_material_pl);
                    syncMaterial(prefix);
                }
            })
            .catch(function () {});
    }

    function actualizarStockHint(prefix) {
        if (prefix === 'egreso') {
            recargarMaterialesConsumoActuales();
            return;
        }

        if (prefix !== 'traslado') {
            return;
        }

        var selectMaterial = document.querySelector('[data-material-select="' + prefix + '"]');
        var almacenSelect = document.getElementById('traslado-id-almacen-origen');
        var hint = document.getElementById(prefix + '-stock-hint');

        if (!selectMaterial || !almacenSelect || !hint || !stockUrl) {
            return;
        }

        var idMaterial = selectMaterial.value;
        var idAlmacen = almacenSelect.value;

        if (!idMaterial || !idAlmacen) {
            hint.hidden = true;
            return;
        }

        fetch(stockUrl + '?id_material_pl=' + encodeURIComponent(idMaterial) + '&id_almacen=' + encodeURIComponent(idAlmacen), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var stock = Number(data.stock || 0);
                hint.querySelector('strong').textContent = stock.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                var badge = hint.querySelector('[data-stock-badge]');
                if (badge) {
                    badge.className = 'stock-badge ' + (stock <= 0 ? 'stock-badge--critico' : 'stock-badge--ok');
                    badge.textContent = stock <= 0 ? 'Crítico' : 'OK';
                }
                hint.hidden = false;
            })
            .catch(function () {
                hint.hidden = true;
            });
    }

    document.querySelectorAll('[data-material-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            var prefix = select.getAttribute('data-material-select');
            syncMaterial(prefix);
        });
    });

    document.querySelectorAll('[data-material-search]').forEach(function (input) {
        var prefix = input.getAttribute('data-material-search');
        input.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(function () {
                buscarMaterialPorCodigo(prefix);
            }, 350);
        });
        input.addEventListener('blur', function () {
            buscarMaterialPorCodigo(prefix);
        });
    });

    var egresoPrograma = document.getElementById('egreso-id-programa');
    var egresoPresentacion = document.getElementById('egreso-id-presentacion');
    var egresoAlmacen = document.getElementById('egreso-id-almacen');

    if (egresoPrograma) {
        egresoPrograma.addEventListener('change', function () {
            cargarPresentacionesConsumo(egresoPrograma.value);
        });
    }

    if (egresoPresentacion) {
        egresoPresentacion.addEventListener('change', function () {
            var programa = document.getElementById('egreso-id-programa');
            cargarMaterialesConsumo(
                programa ? programa.value : '',
                egresoPresentacion.value
            );
        });
    }

    if (egresoAlmacen) {
        egresoAlmacen.addEventListener('change', function () {
            recargarMaterialesConsumoActuales();
        });
    }

    ['egreso-pallets-fuera'].forEach(function (id) {
        var input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', recalcularTotalesConsumo);
            input.addEventListener('change', recalcularTotalesConsumo);
        }
    });

    var trasladoAlmacen = document.getElementById('traslado-id-almacen-origen');
    if (trasladoAlmacen) {
        trasladoAlmacen.addEventListener('change', function () {
            actualizarStockHint('traslado');
        });
    }

    var abrirIngreso = document.getElementById('registro-abrir-ingreso');
    var abrirEgreso = document.getElementById('registro-abrir-egreso');
    var abrirTraslado = document.getElementById('registro-abrir-traslado');

    if (abrirIngreso) abrirIngreso.addEventListener('click', function () {
        resetModalCreacion('ingreso');
        abrirModal('ingreso');
    });
    if (abrirEgreso) abrirEgreso.addEventListener('click', function () {
        resetModalCreacion('egreso');
        abrirModal('egreso');
    });
    if (abrirTraslado) abrirTraslado.addEventListener('click', function () {
        resetModalCreacion('traslado');
        abrirModal('traslado');
    });

    document.querySelectorAll('[data-cerrar-modal-registro]').forEach(function (node) {
        node.addEventListener('click', function () {
            cerrarModal(node.getAttribute('data-cerrar-modal-registro'));
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            Object.keys(modales).forEach(cerrarModal);
        }
    });

    ['ingreso', 'egreso', 'traslado'].forEach(function (tipo) {
        var modal = modales[tipo];
        if (modal && modal.querySelector('.modal__errors')) {
            abrirModal(tipo);
        }
    });

    if (tabActiva === 'traslados') {
        syncMaterial('traslado');
    } else if (tabActiva === 'ingresos') {
        syncMaterial('ingreso');
    }

    var consumoOld = window.registroConsumoOld || null;
    if (consumoOld && consumoOld.id_programa) {
        var programaSelectOld = document.getElementById('egreso-id-programa');
        consumoOverrides = {};
        (Array.isArray(consumoOld.lineas) ? consumoOld.lineas : []).forEach(function (linea) {
            if (linea && linea.id_material_pl) {
                consumoOverrides[String(linea.id_material_pl)] = linea.cantidad != null ? String(linea.cantidad) : '';
            }
        });
        if (programaSelectOld) {
            programaSelectOld.value = String(consumoOld.id_programa);
            cargarPresentacionesConsumo(consumoOld.id_programa, consumoOld.id_presentacion || '')
                .then(function () {
                    return cargarMaterialesConsumo(
                        consumoOld.id_programa,
                        consumoOld.id_presentacion || ''
                    );
                });
        }
    }

    /* ── Ingreso: carrito de materiales ── */
    var ingresoForm = document.getElementById('registro-ingreso-form');
    var ingresoLineas = [];
    var materialesMap = window.registroMaterialesMap || {};

    function almacenCodigoIngreso() {
        var select = document.getElementById('ingreso-id-almacen');
        if (!select || !select.value) {
            return '';
        }
        var option = select.options[select.selectedIndex];
        return option.getAttribute('data-codigo') || option.textContent.split('—')[0].trim();
    }

    function mostrarHintIngreso(mensaje, esError) {
        var hint = document.getElementById('ingreso-linea-hint');
        if (!hint) {
            return;
        }
        hint.textContent = mensaje;
        hint.hidden = false;
        hint.classList.toggle('is-error', !!esError);
    }

    function limpiarHintIngreso() {
        var hint = document.getElementById('ingreso-linea-hint');
        if (hint) {
            hint.hidden = true;
            hint.textContent = '';
            hint.classList.remove('is-error');
        }
    }

    function limpiarLineaIngreso() {
        var select = document.querySelector('[data-material-select="ingreso"]');
        var search = document.querySelector('[data-material-search="ingreso"]');
        var nom = document.querySelector('[data-material-nom="ingreso"]');
        var cantidad = document.getElementById('ingreso-linea-cantidad');

        if (select) {
            select.value = '';
        }
        if (search) {
            search.value = '';
        }
        if (nom) {
            nom.value = '';
        }
        if (cantidad) {
            cantidad.value = '';
        }
        limpiarHintIngreso();
    }

    function renderIngresoPreview() {
        var preview = document.getElementById('registro-ingreso-preview');
        var body = document.getElementById('registro-ingreso-preview-body');
        var count = document.getElementById('registro-ingreso-preview-count');
        var hidden = document.getElementById('registro-ingreso-lineas-hidden');

        if (!preview || !body || !hidden) {
            return;
        }

        if (count) {
            count.textContent = '(' + ingresoLineas.length + ')';
        }

        if (ingresoLineas.length === 0) {
            preview.hidden = true;
            body.innerHTML = '';
            hidden.innerHTML = '';
            return;
        }

        preview.hidden = false;
        body.innerHTML = ingresoLineas.map(function (linea, index) {
            return '<tr>' +
                '<td>' + escapeHtml(linea.almacenCodigo) + '</td>' +
                '<td class="registro-ingreso-preview__material">' + escapeHtml(linea.nomMaterial) + '</td>' +
                '<td class="registro-ingreso-preview__code">' + escapeHtml(linea.codigoPl) + '</td>' +
                '<td class="registro-ingreso-preview__qty">' + escapeHtml(linea.cantidadTexto) + '</td>' +
                '<td class="registro-ingreso-preview__actions">' +
                    '<button type="button" class="icon-action icon-action--delete" data-quitar-ingreso-linea="' + index + '" title="Quitar">&times;</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        hidden.innerHTML = ingresoLineas.map(function (linea, index) {
            var hiddenLinea = '<input type="hidden" name="lineas[' + index + '][id_material_pl]" value="' + linea.idMaterialPL + '">' +
                '<input type="hidden" name="lineas[' + index + '][cantidad]" value="' + linea.cantidad + '">';

            if (linea.idMovimiento) {
                hiddenLinea += '<input type="hidden" name="lineas[' + index + '][id_movimiento]" value="' + linea.idMovimiento + '">';
            }

            return hiddenLinea;
        }).join('');

        body.querySelectorAll('[data-quitar-ingreso-linea]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-quitar-ingreso-linea'), 10);
                ingresoLineas.splice(idx, 1);
                renderIngresoPreview();
            });
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function anadirLineaIngreso() {
        var select = document.querySelector('[data-material-select="ingreso"]');
        var cantidadInput = document.getElementById('ingreso-linea-cantidad');
        var almacenSelect = document.getElementById('ingreso-id-almacen');

        if (!select || !cantidadInput || !almacenSelect) {
            return;
        }

        if (!almacenSelect.value) {
            mostrarHintIngreso('Seleccione un almacén primero.', true);
            almacenSelect.focus();
            return;
        }

        if (!select.value) {
            mostrarHintIngreso('Seleccione un material.', true);
            select.focus();
            return;
        }

        var cantidad = parseFloat(String(cantidadInput.value).replace(',', '.'));
        if (!cantidad || cantidad <= 0) {
            mostrarHintIngreso('Ingrese una cantidad mayor a cero.', true);
            cantidadInput.focus();
            return;
        }

        var material = materialesMap[select.value] || {};
        var option = select.options[select.selectedIndex];

        ingresoLineas.push({
            idMaterialPL: select.value,
            cantidad: cantidad.toFixed(2),
            cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            codigoPl: material.codigo || option.getAttribute('data-codigo-pl') || '',
            nomMaterial: material.nombre || option.getAttribute('data-nom-material-pl') || '',
            almacenCodigo: almacenCodigoIngreso(),
        });

        limpiarLineaIngreso();
        renderIngresoPreview();
    }

    function restaurarLineasIngresoOld() {
        var oldLineas = window.registroIngresoLineasOld || [];
        if (!Array.isArray(oldLineas) || oldLineas.length === 0) {
            return;
        }

        ingresoLineas = oldLineas.map(function (linea) {
            var id = String(linea.id_material_pl || '');
            var material = materialesMap[id] || {};
            var cantidad = parseFloat(linea.cantidad);
            return {
                idMaterialPL: id,
                cantidad: cantidad.toFixed(2),
                cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                codigoPl: material.codigo || '',
                nomMaterial: material.nombre || '',
                almacenCodigo: almacenCodigoIngreso(),
            };
        });

        renderIngresoPreview();
    }

    var btnAnadirIngreso = document.getElementById('ingreso-linea-anadir');
    if (btnAnadirIngreso) {
        btnAnadirIngreso.addEventListener('click', anadirLineaIngreso);
    }

    var almacenIngreso = document.getElementById('ingreso-id-almacen');
    if (almacenIngreso) {
        almacenIngreso.addEventListener('change', function () {
            var codigo = almacenCodigoIngreso();
            ingresoLineas.forEach(function (linea) {
                linea.almacenCodigo = codigo;
            });
            renderIngresoPreview();
        });
    }

    var cantidadIngreso = document.getElementById('ingreso-linea-cantidad');
    if (cantidadIngreso) {
        cantidadIngreso.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                anadirLineaIngreso();
            }
        });
    }

    if (ingresoForm) {
        ingresoForm.addEventListener('submit', function (event) {
            if (ingresoLineas.length === 0) {
                event.preventDefault();
                mostrarHintIngreso('Agregue al menos un material con Añadir o cargue el XML de la guía.', true);
            }
        });
    }

    function mostrarEstadoXmlIngreso(mensaje, tipo) {
        var status = document.getElementById('ingreso-xml-lectura-status');
        if (!status) {
            return;
        }

        status.hidden = false;
        status.textContent = mensaje;
        status.className = 'field__hint registro-xml-lectura registro-ingreso-archivos__status' + (tipo ? ' is-' + tipo : '');
    }

    function ocultarEstadoXmlIngreso() {
        var status = document.getElementById('ingreso-xml-lectura-status');
        if (status) {
            status.hidden = true;
            status.textContent = '';
            status.className = 'field__hint registro-xml-lectura registro-ingreso-archivos__status';
        }
    }

    function aplicarLineasDesdeXml(lineas) {
        var codigoAlmacen = almacenCodigoIngreso();

        ingresoLineas = lineas.map(function (linea) {
            var cantidad = parseFloat(linea.cantidad);
            return {
                idMaterialPL: String(linea.id_material_pl),
                cantidad: cantidad.toFixed(2),
                cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                codigoPl: linea.codigo_pl || '',
                nomMaterial: linea.nom_material || '',
                almacenCodigo: codigoAlmacen || '—',
            };
        });

        renderIngresoPreview();
        limpiarLineaIngreso();
    }

    function leerXmlIngreso(archivo) {
        var url = window.registroIngresoLeerXmlUrl || '';
        var csrf = document.querySelector('meta[name="csrf-token"]');

        if (!url || !archivo) {
            return;
        }

        mostrarEstadoXmlIngreso('Leyendo XML...', 'loading');

        var formData = new FormData();
        formData.append('archivo_xml', archivo);

        fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
            },
            body: formData,
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                var data = result.data || {};

                if (!result.ok || data.ok === false) {
                    mostrarEstadoXmlIngreso(data.error || data.message || 'No se pudo leer el XML.', 'error');
                    return;
                }

                if (data.guia) {
                    var guiaInput = document.getElementById('ingreso-guia');
                    if (guiaInput) {
                        guiaInput.value = data.guia;
                    }
                }

                if (Array.isArray(data.lineas) && data.lineas.length > 0) {
                    var aplicarXml = function () {
                        aplicarLineasDesdeXml(data.lineas);

                        var sinCatalogoAvisos = Array.isArray(data.advertencias)
                            ? data.advertencias.filter(function (a) {
                                return a.indexOf('sin relación') !== -1;
                            })
                            : [];

                        if (sinCatalogoAvisos.length > 0) {
                            var codigosSinCatalogo = sinCatalogoAvisos.map(function (a) {
                                var match = a.match(/«([^»]+)»/);
                                return match ? match[1] : null;
                            }).filter(Boolean);

                            var mensajeSinCatalogo = codigosSinCatalogo.length > 0
                                ? codigosSinCatalogo.length + ' código(s) sin catálogo: ' + codigosSinCatalogo.join(', ')
                                : sinCatalogoAvisos.length + ' código(s) del XML no están en catálogo Proveedor.';

                            mostrarEstadoXmlIngreso(mensajeSinCatalogo, 'warn');
                        } else {
                            ocultarEstadoXmlIngreso();
                        }
                    };

                    if (ingresoLineas.length > 0 && window.TalsaConfirm) {
                        window.TalsaConfirm.show({
                            title: 'Reemplazar materiales',
                            message: 'Ya hay materiales agregados. ¿Deseas reemplazarlos por los del XML?',
                            confirmLabel: 'Sí, reemplazar',
                            variant: 'warning',
                        }).then(function (approved) {
                            if (approved) {
                                aplicarXml();
                            } else {
                                mostrarEstadoXmlIngreso('Guía leída. Materiales no reemplazados.', 'warn');
                            }
                        });
                        return;
                    }

                    aplicarXml();
                }
            })
            .catch(function () {
                mostrarEstadoXmlIngreso('Error de conexión al leer el XML.', 'error');
            });
    }

    var xmlIngresoInput = document.getElementById('ingreso-archivo-xml');
    if (xmlIngresoInput) {
        xmlIngresoInput.addEventListener('change', function () {
            var archivo = xmlIngresoInput.files && xmlIngresoInput.files[0];
            if (!archivo) {
                ocultarEstadoXmlIngreso();
                return;
            }

            leerXmlIngreso(archivo);
        });
    }

    restaurarLineasIngresoOld();

    /* ── Detalle de operación ── */
    var detalleMovimientoUrl = window.registroDetalleMovimientoUrl || '';
    var detalleTrasladoUrl = window.registroDetalleTrasladoUrl || '';
    var ingresoUpdateUrl = window.registroIngresoUpdateUrl || '';
    var egresoUpdateUrl = window.registroEgresoUpdateUrl || '';
    var trasladoUpdateUrl = window.registroTrasladoUpdateUrl || '';
    var detalleModal = modales.detalle;

    function escaparHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mostrarCampoDetalle(wrapId, valueId, valor) {
        var wrap = document.getElementById(wrapId);
        var value = document.getElementById(valueId);
        if (!wrap || !value) {
            return;
        }

        if (valor) {
            wrap.hidden = false;
            value.textContent = valor;
        } else {
            wrap.hidden = true;
            value.textContent = '—';
        }
    }

    function limpiarDetalleModal() {
        var estado = document.getElementById('registro-detalle-estado');
        var contenido = document.getElementById('registro-detalle-contenido');
        if (estado) {
            estado.hidden = false;
            estado.textContent = 'Cargando detalle...';
        }
        if (contenido) {
            contenido.hidden = true;
        }
    }

    function renderDetalle(data) {
        var estado = document.getElementById('registro-detalle-estado');
        var contenido = document.getElementById('registro-detalle-contenido');
        var titulo = document.getElementById('registro-detalle-titulo');
        var tipoBadge = document.getElementById('registro-detalle-tipo');
        var lineasBody = document.getElementById('registro-detalle-lineas');
        var vinculadosBody = document.getElementById('registro-detalle-vinculados');
        var vinculadosWrap = document.getElementById('registro-detalle-vinculados-wrap');
        var colAlmacen = document.getElementById('registro-detalle-col-almacen');

        if (estado) {
            estado.hidden = true;
        }
        if (contenido) {
            contenido.hidden = false;
        }
        if (titulo) {
            titulo.textContent = data.titulo || 'Detalle de operación';
        }
        if (tipoBadge) {
            tipoBadge.hidden = false;
            tipoBadge.textContent = data.tipo === 'ingreso'
                ? 'Ingreso'
                : data.tipo === 'egreso'
                    ? 'Consumo'
                    : 'Traslado';
            tipoBadge.className = 'registro-detalle-tipo registro-detalle-tipo--' + (data.tipo || 'ingreso');
        }

        var usuarioTexto = data.usuario || '—';
        if (data.usuario_email && data.usuario_email !== data.usuario) {
            usuarioTexto += ' (' + data.usuario_email + ')';
        }

        var usuarioEl = document.getElementById('registro-detalle-usuario');
        if (usuarioEl) {
            if (data.id_usuario) {
                usuarioEl.innerHTML = '<button type="button" class="usuario-nombre-clic" data-usuario-tarjeta="' +
                    escaparHtml(String(data.id_usuario)) + '">' + escaparHtml(usuarioTexto) + '</button>';
            } else {
                usuarioEl.textContent = usuarioTexto;
            }
        }

        document.getElementById('registro-detalle-fecha-operacion').textContent = data.fecha_operacion || '—';
        document.getElementById('registro-detalle-fecha-registro').textContent = data.fecha_registro || '—';

        var esTraslado = data.tipo === 'traslado';
        document.getElementById('registro-detalle-almacen-wrap').hidden = esTraslado;
        document.getElementById('registro-detalle-origen-wrap').hidden = !esTraslado;
        document.getElementById('registro-detalle-destino-wrap').hidden = !esTraslado;

        if (!esTraslado) {
            document.getElementById('registro-detalle-almacen').textContent = data.almacen || '—';
        } else {
            document.getElementById('registro-detalle-origen').textContent = data.almacen_origen || '—';
            document.getElementById('registro-detalle-destino').textContent = data.almacen_destino || '—';
        }

        mostrarCampoDetalle('registro-detalle-factura-wrap', 'registro-detalle-factura', data.factura);
        mostrarCampoDetalle('registro-detalle-guia-wrap', 'registro-detalle-guia', data.guia);
        mostrarCampoDetalle('registro-detalle-motivo-wrap', 'registro-detalle-motivo', data.motivo);
        mostrarCampoDetalle('registro-detalle-programa-wrap', 'registro-detalle-programa', data.programa);
        mostrarCampoDetalle('registro-detalle-presentacion-wrap', 'registro-detalle-presentacion', data.presentacion);
        mostrarCampoDetalle('registro-detalle-observacion-wrap', 'registro-detalle-observacion', data.observacion);

        var pdfWrap = document.getElementById('registro-detalle-pdf-wrap');
        var pdfValue = document.getElementById('registro-detalle-pdf');
        if (pdfWrap && pdfValue) {
            if (data.pdf_url) {
                pdfWrap.hidden = false;
                pdfValue.innerHTML = '<a href="' + escaparHtml(data.pdf_url) + '" class="registro-pdf-link" target="_blank" rel="noopener noreferrer">' +
                    escaparHtml(data.pdf_nombre || 'Ver') + '</a>';
            } else {
                pdfWrap.hidden = true;
                pdfValue.textContent = '—';
            }
        }

        if (lineasBody) {
            var lineas = Array.isArray(data.lineas) ? data.lineas : [];
            if (colAlmacen) {
                colAlmacen.hidden = esTraslado;
            }

            lineasBody.innerHTML = lineas.map(function (linea) {
                var almacenCell = esTraslado
                    ? ''
                    : '<td>' + escaparHtml(linea.almacen || '—') + '</td>';

                return '<tr>' +
                    '<td class="programas-grid__code">' + escaparHtml(linea.codigo_pl || '—') + '</td>' +
                    '<td class="programas-grid__material">' + escaparHtml(linea.material || '—') + '</td>' +
                    almacenCell +
                    '<td class="programas-grid__qty">' + escaparHtml(linea.cantidad || '0.00') + '</td>' +
                    '</tr>';
            }).join('');

            if (lineas.length === 0) {
                var colspan = esTraslado ? 3 : 4;
                lineasBody.innerHTML = '<tr><td colspan="' + colspan + '" class="data-table__empty">Sin materiales.</td></tr>';
            }
        }

        if (vinculadosWrap && vinculadosBody) {
            var vinculados = Array.isArray(data.movimientos_vinculados) ? data.movimientos_vinculados : [];
            if (vinculados.length > 0) {
                vinculadosWrap.hidden = false;
                vinculadosBody.innerHTML = vinculados.map(function (mov) {
                    return '<tr>' +
                        '<td>' + escaparHtml(mov.tipo || '—') + '</td>' +
                        '<td>' + escaparHtml(mov.almacen || '—') + '</td>' +
                        '<td class="programas-grid__qty">' + escaparHtml(mov.cantidad || '0.00') + '</td>' +
                        '</tr>';
                }).join('');
            } else {
                vinculadosWrap.hidden = true;
                vinculadosBody.innerHTML = '';
            }
        }
    }

    function abrirDetalle(tipo, id) {
        if (!detalleModal) {
            return;
        }

        var url = tipo === 'traslado'
            ? detalleTrasladoUrl.replace('__ID__', String(id))
            : detalleMovimientoUrl.replace('__ID__', String(id));

        limpiarDetalleModal();
        abrirModal('detalle');

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el detalle.');
                }
                return response.json();
            })
            .then(renderDetalle)
            .catch(function (error) {
                var estado = document.getElementById('registro-detalle-estado');
                if (estado) {
                    estado.hidden = false;
                    estado.textContent = error.message || 'Error al cargar el detalle.';
                }
            });
    }

    document.querySelectorAll('[data-registro-detalle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var tipo = button.getAttribute('data-registro-detalle');
            var id = button.getAttribute('data-registro-detalle-id');
            if (!tipo || !id) {
                return;
            }
            abrirDetalle(tipo, id);
        });
    });

    function setFieldValue(form, name, value) {
        if (!form) {
            return;
        }

        var field = form.querySelector('[name="' + name + '"]');
        if (field) {
            field.value = value == null ? '' : value;
        }
    }

    function abrirEdicion(tipo, id) {
        var url = tipo === 'traslado'
            ? detalleTrasladoUrl.replace('__ID__', String(id))
            : detalleMovimientoUrl.replace('__ID__', String(id));

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el registro.');
                }
                return response.json();
            })
            .then(function (data) {
                var edit = data.datos_edicion || {};
                var modalTipo = tipo === 'traslado' ? 'traslado' : (data.tipo === 'egreso' ? 'egreso' : 'ingreso');
                var form = document.getElementById('registro-' + modalTipo + '-form');
                var methodInput = document.getElementById('registro-' + modalTipo + '-method');
                var title = document.getElementById('registro-' + modalTipo + '-title');
                var submitBtn = document.getElementById('registro-' + modalTipo + '-guardar');
                var updateUrl = modalTipo === 'ingreso'
                    ? ingresoUpdateUrl.replace('__ID__', String(id))
                    : modalTipo === 'egreso'
                        ? egresoUpdateUrl.replace('__ID__', String(id))
                        : trasladoUpdateUrl.replace('__ID__', String(id));

                if (!form) {
                    return;
                }

                form.action = updateUrl;

                if (methodInput) {
                    methodInput.value = 'PUT';
                    methodInput.disabled = false;
                }

                if (title) {
                    title.textContent = modalTipo === 'ingreso' ? 'Editar ingreso' : modalTipo === 'egreso' ? 'Editar consumo' : 'Editar traslado';
                }

                if (submitBtn) {
                    submitBtn.textContent = 'Guardar cambios';
                }

                if (modalTipo === 'ingreso') {
                    setFieldValue(form, 'fecha', edit.fecha || '');
                    setFieldValue(form, 'id_almacen', edit.id_almacen || '');
                    setFieldValue(form, 'factura', edit.factura || '');
                    setFieldValue(form, 'guia', edit.guia || '');
                    setFieldValue(form, 'observacion', edit.observacion || '');

                    ingresoLineas = (Array.isArray(edit.lineas) ? edit.lineas : []).map(function (linea) {
                        var idMaterial = String(linea.id_material_pl || '');
                        var material = materialesMap[idMaterial] || {};
                        var cantidad = parseFloat(linea.cantidad);

                        return {
                            idMovimiento: linea.id_movimiento ? String(linea.id_movimiento) : null,
                            idMaterialPL: idMaterial,
                            cantidad: cantidad.toFixed(2),
                            cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                            codigoPl: material.codigo || '',
                            nomMaterial: material.nombre || '',
                            almacenCodigo: almacenCodigoIngreso(),
                        };
                    });

                    renderIngresoPreview();
                    limpiarLineaIngreso();
                    abrirModal('ingreso');
                    return;
                }

                if (modalTipo === 'egreso') {
                    consumoEditId = id;
                    consumoEditMaterialId = edit.id_material_pl || null;
                    consumoEditCantidad = edit.cantidad != null ? Number(edit.cantidad) : null;
                    consumoOverrides = {};
                    setFieldValue(form, 'fecha', edit.fecha || '');
                    setFieldValue(form, 'id_almacen', edit.id_almacen || '');
                    setFieldValue(form, 'id_material_pl', edit.id_material_pl || '');
                    setFieldValue(form, 'factura', edit.factura || '');
                    setFieldValue(form, 'guia', edit.guia || '');
                    setFieldValue(form, 'observacion', edit.observacion || '');

                    var selectMotivo = form.querySelector('[name="id_tipo_consumo"]');
                    if (selectMotivo && edit.id_tipo_consumo) {
                        var motivoValue = String(edit.id_tipo_consumo);
                        if (!selectMotivo.querySelector('option[value="' + motivoValue + '"]')) {
                            var optMotivo = document.createElement('option');
                            optMotivo.value = motivoValue;
                            optMotivo.textContent = edit.motivo_nombre || ('Motivo #' + motivoValue);
                            selectMotivo.appendChild(optMotivo);
                        }
                        selectMotivo.value = motivoValue;
                    }

                    var programaSelect = document.getElementById('egreso-id-programa');
                    if (programaSelect && edit.id_programa) {
                        programaSelect.value = String(edit.id_programa);
                    }

                    cargarPresentacionesConsumo(edit.id_programa || '', edit.id_presentacion || '')
                        .then(function () {
                            return cargarMaterialesConsumo(
                                edit.id_programa || '',
                                edit.id_presentacion || ''
                            );
                        })
                        .then(function () {
                            abrirModal('egreso');
                        });
                    return;
                }

                setFieldValue(form, 'fecha', edit.fecha || '');
                setFieldValue(form, 'id_almacen_origen', edit.id_almacen_origen || '');
                setFieldValue(form, 'id_almacen_destino', edit.id_almacen_destino || '');
                setFieldValue(form, 'cantidad', edit.cantidad != null ? Number(edit.cantidad).toFixed(2) : '');
                setFieldValue(form, 'factura', edit.factura || '');
                setFieldValue(form, 'guia', edit.guia || '');
                setFieldValue(form, 'observacion', edit.observacion || '');

                var selectTraslado = form.querySelector('[data-material-select="traslado"]');
                if (selectTraslado && edit.id_material_pl) {
                    selectTraslado.value = String(edit.id_material_pl);
                    syncMaterial('traslado');
                }

                abrirModal('traslado');
            })
            .catch(function (error) {
                window.alert(error.message || 'Error al cargar el registro.');
            });
    }

    document.querySelectorAll('[data-registro-editar]').forEach(function (button) {
        button.addEventListener('click', function () {
            var tipo = button.getAttribute('data-registro-editar');
            var id = button.getAttribute('data-registro-editar-id');
            if (!tipo || !id) {
                return;
            }
            abrirEdicion(tipo, id);
        });
    });
})();
