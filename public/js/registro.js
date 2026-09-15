(function () {
    var materialUrl = window.registroMaterialUrl || '';
    var materialesBuscarUrl = window.registroMaterialesBuscarUrl || '';
    var materialesMap = window.registroMaterialesMap || {};
    var stockUrl = window.registroStockUrl || '';
    var consumoProgramasUrl = window.registroConsumoProgramasUrl || '';
    var consumoPresentacionesUrl = window.registroConsumoPresentacionesUrl || '';
    var consumoMaterialesUrl = window.registroConsumoMaterialesUrl || '';
    var consumoHintUrl = window.registroConsumoHintUrl || '';
    var tabActiva = window.registroTabActiva || 'ingresos';
    var lookupTimer = null;
    var consumoEditId = null;
    var consumoEditLineas = {};
    var consumoOverrides = {};
    var consumoTotalOverrides = {};
    var consumoPresentacionesCache = {};
    var registroIndexUrl = window.registroIndexUrl || '';
    var INGRESO_OBS_AJUSTE = 'AJUSTE TEMPORAL — revertir o anular este ingreso al corregir consumo/traslado erróneo.';

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

        if (tipo === 'ingreso' && typeof resetIngresoModalState === 'function') {
            resetIngresoModalState();
        }

        if (tipo === 'egreso') {
            consumoEditId = null;
            consumoEditLineas = {};
            consumoOverrides = {};
            consumoTotalOverrides = {};
            resetConsumoCascada();
        }
    }

    function decimalesFamiliaConsumo(nomFamilia, decimalesApi) {
        if (decimalesApi != null && decimalesApi !== '') {
            return Number(decimalesApi);
        }

        var familia = normalizarFamiliaConsumo(nomFamilia);
        if (familia === 'temperature recorder') {
            return 7;
        }

        if (
            familia === 'carton' || familia === 'cartons' || familia === 'cartones'
            || familia === 'pallet' || familia === 'pallets'
            || familia === 'parihuela' || familia === 'parihuelas'
        ) {
            return 4;
        }

        return 2;
    }

    function redondearConsumo(valor, nomFamilia, decimalesApi) {
        var dec = decimalesFamiliaConsumo(nomFamilia, decimalesApi);
        var factor = Math.pow(10, dec);

        return Math.round(Number(valor || 0) * factor) / factor;
    }

    function formatearNumero(valor, nomFamilia, decimalesApi) {
        var dec = decimalesFamiliaConsumo(nomFamilia, decimalesApi);

        return Number(valor || 0).toLocaleString('es-PE', {
            minimumFractionDigits: 0,
            maximumFractionDigits: dec,
        });
    }

    function stepCantidadConsumo(nomFamilia, decimalesApi) {
        var dec = decimalesFamiliaConsumo(nomFamilia, decimalesApi);

        if (dec <= 0) {
            return '1';
        }

        return (1 / Math.pow(10, dec)).toFixed(dec);
    }

    function resetConsumoCascada() {
        var presentacion = document.getElementById('egreso-id-presentacion');
        var materialHidden = document.getElementById('egreso-id-material');
        var programa = document.getElementById('egreso-id-programa');
        var programaGeneral = document.getElementById('egreso-id-programa-general');

        if (programaGeneral) {
            programaGeneral.value = '';
        }

        if (programa) {
            programa.innerHTML = '<option value="">Seleccione un programa general</option>';
            programa.disabled = true;
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

        vaciarTablaMaterialesConsumo('Seleccione un producto para cargar los materiales desde Receta.');
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
            hint.textContent = mensaje || 'Seleccione un producto para cargar los materiales desde Receta.';
        }
        limpiarParametrosPresentacionConsumo();
    }

    function claveUsoConsumo(nomUso) {
        var uso = String(nomUso || '')
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        if (uso.indexOf('comun') === 0) {
            return 'comun';
        }

        var altMatch = uso.match(/^alternativo(?:\s*(\d+))?/);
        if (altMatch) {
            return altMatch[1] ? 'alternativo ' + altMatch[1] : 'alternativo';
        }

        return uso;
    }

    function pintarBadgeObservacionConsumo(badge, claseObsr) {
        badge.className = 'registro-consumo-tabla__obsr-valor';
        if (claseObsr) {
            badge.classList.add(claseObsr);
        }
    }

    function esUsoFactorCaja(nomUso) {
        var clave = claveUsoConsumo(nomUso);

        return clave === 'comun' || clave === 'oficial' || clave.indexOf('alternativo') === 0;
    }

    function normalizarFamiliaConsumo(nomFamilia) {
        return String(nomFamilia || '')
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function esTruthy(val) {
        return val === true || val === 1 || val === '1';
    }

    function esFamiliaCajasConsumo(nomFamilia) {
        var familia = normalizarFamiliaConsumo(nomFamilia);

        return familia === 'cajas' || familia === 'caja' || familia === 'box';
    }

    function esCajaEditableItem(item) {
        if (!item) {
            return false;
        }

        if (esTruthy(item.es_caja_factor)) {
            return true;
        }

        return esFamiliaCajasConsumo(item.nom_familia);
    }

    function overrideDeMaterial(idMaterial) {
        var clave = String(idMaterial);
        if (Object.prototype.hasOwnProperty.call(consumoOverrides, clave)) {
            return consumoOverrides[clave];
        }

        return '';
    }

    function totalOverrideDeMaterial(idMaterial) {
        var clave = String(idMaterial);
        if (Object.prototype.hasOwnProperty.call(consumoTotalOverrides, clave)) {
            return consumoTotalOverrides[clave];
        }

        if (consumoEditLineas[clave] && consumoEditLineas[clave].cantidad != null) {
            return String(consumoEditLineas[clave].cantidad);
        }

        return null;
    }

    function limpiarParametrosPresentacionConsumo() {
        actualizarParametrosPresentacionConsumo(null);
    }

    function actualizarParametrosPresentacionConsumo(params) {
        var conformacion = document.getElementById('egreso-conformacion-dentro');
        var pallets = document.getElementById('egreso-pallets-fuera');

        if (!params) {
            if (conformacion) {
                conformacion.value = '';
            }
            if (pallets) {
                pallets.value = '';
            }
            return;
        }

        if (conformacion) {
            conformacion.value = params.conformacion != null && String(params.conformacion).trim() !== ''
                ? Number(params.conformacion).toFixed(2)
                : '';
        }
        if (pallets) {
            pallets.value = params.pallets != null && String(params.pallets).trim() !== ''
                ? Number(params.pallets).toFixed(2)
                : '';
        }
    }

    function parametrosPresentacionConsumo() {
        var conformacionEl = document.getElementById('egreso-conformacion-dentro');
        var palletsEl = document.getElementById('egreso-pallets-fuera');
        var palletsText = palletsEl ? String(palletsEl.value || '').trim() : '';
        var conformacionText = conformacionEl ? String(conformacionEl.value || '').trim() : '';

        return {
            pallets: palletsText !== '' && Number(palletsText) >= 0 ? Number(palletsText) : 0,
            conformacion: conformacionText !== '' && Number(conformacionText) > 0 ? Number(conformacionText) : 1,
        };
    }

    function parseFactorInput(input) {
        if (!input) {
            return 0;
        }
        var texto = String(input.value || '').trim();
        if (texto === '' || Number(texto) <= 0) {
            return 0;
        }
        return Number(texto);
    }

    function factoresCajasDesdeTabla(body) {
        var factores = {};

        body.querySelectorAll('[data-caja-factor]').forEach(function (input) {
            var clave = input.getAttribute('data-uso') || '';
            if (!clave) {
                return;
            }
            var val = parseFactorInput(input);
            if (val > 0) {
                factores[clave] = val;
            }
        });

        return factores;
    }

    function factorCajaParaFila(tr, factores) {
        var uso = tr.getAttribute('data-uso') || '';

        if (factores[uso] > 0) {
            return factores[uso];
        }

        return 1;
    }

    function baseCantidadFila(tr) {
        var cantCaja = Number(tr.getAttribute('data-cantidad-caja') || 0);
        if (tr.getAttribute('data-es-caja') !== '1') {
            return cantCaja;
        }

        var input = tr.querySelector('[data-consumo-override]');
        var val = input ? String(input.value || '').trim() : '';
        if (val !== '' && Number(val) > 0) {
            return Number(val);
        }

        return cantCaja;
    }

    function calcularTotalMaterial(tr, factores, params) {
        var esCaja = tr.getAttribute('data-es-caja') === '1';
        var usaPallets = tr.getAttribute('data-usa-pallets') === '1';
        var factorCaja = factorCajaParaFila(tr, factores);

        if (esCaja) {
            return baseCantidadFila(tr);
        }

        if (usaPallets) {
            var conf = Math.max(params.conformacion, 0.00001);
            return (params.pallets / conf) * factorCaja;
        }

        return baseCantidadFila(tr) * factorCaja;
    }

    function almacenConsumoSeleccionado() {
        var almacen = document.getElementById('egreso-id-almacen');

        return !!(almacen && String(almacen.value || '').trim() !== '');
    }

    function pintarCeldaStockConsumo(tdStock, stock) {
        var tr = tdStock.closest('tr');
        tdStock.innerHTML = '';
        tdStock.removeAttribute('title');

        if (stock == null) {
            tdStock.textContent = '—';
            if (tr) {
                tr.removeAttribute('data-stock');
            }
            if (!almacenConsumoSeleccionado()) {
                tdStock.title = 'Seleccione un almacén para ver el stock';
            }
            return;
        }

        var stockBadge = document.createElement('span');
        stockBadge.className = 'registro-consumo-tabla__stock-valor';
        stockBadge.textContent = formatearNumero(
            stock,
            tr ? tr.getAttribute('data-nom-familia') : null,
            tr ? tr.getAttribute('data-decimales-cantidad') : null
        );
        if (stock <= 0) {
            stockBadge.classList.add('is-critico');
        }
        tdStock.appendChild(stockBadge);

        if (tr) {
            tr.setAttribute('data-stock', String(stock));
        }
    }

    function totalConsumoFila(tr) {
        var totalInput = tr.querySelector('[data-consumo-total]');
        if (!totalInput) {
            return 0;
        }

        return parseFloat(String(totalInput.value || '').replace(',', '.')) || 0;
    }

    function urlRegistroMovimientos(codigoPl, idAlmacen, tab) {
        if (!registroIndexUrl) {
            return '#';
        }

        var params = new URLSearchParams();
        params.set('tab', tab || 'consumos');
        if (idAlmacen) {
            params.set('almacen', String(idAlmacen));
        }
        if (codigoPl) {
            params.set('buscar', String(codigoPl));
        }

        var sep = registroIndexUrl.indexOf('?') >= 0 ? '&' : '?';

        return registroIndexUrl + sep + params.toString();
    }

    function actualizarAlertaStockConsumo() {
        var alertBox = document.getElementById('egreso-stock-alert');
        var body = document.getElementById('egreso-materiales-body');
        if (!alertBox || !body) {
            return;
        }

        if (!almacenConsumoSeleccionado()) {
            alertBox.hidden = true;
            alertBox.innerHTML = '';
            return;
        }

        var idAlmacen = document.getElementById('egreso-id-almacen').value;
        var faltantes = [];

        body.querySelectorAll('tr[data-consumo-row]').forEach(function (tr) {
            tr.classList.remove('is-stock-insuficiente');
            var stockRaw = tr.getAttribute('data-stock');
            if (stockRaw === null || stockRaw === '') {
                return;
            }

            var stock = parseFloat(stockRaw);
            var total = totalConsumoFila(tr);
            if (total <= 0 || total <= stock + 0.0001) {
                return;
            }

            tr.classList.add('is-stock-insuficiente');
            var inputMat = tr.querySelector('input[name*="[id_material_pl]"]');
            faltantes.push({
                idMaterial: inputMat ? inputMat.value : '',
                codigoPl: tr.getAttribute('data-codigo-pl') || '',
                nom: (tr.querySelector('.registro-consumo-tabla__material') || {}).textContent || 'Material',
                total: total,
                stock: stock,
                falta: total - stock,
            });
        });

        if (faltantes.length === 0) {
            alertBox.hidden = true;
            alertBox.innerHTML = '';
            return;
        }

        var html = '<p class="registro-consumo-stock-alert__lead"><strong>Stock insuficiente</strong> en '
            + faltantes.length
            + ' material(es). Ingreso temporal → corregir consumo/traslado en Registro → revertir ese ingreso.</p><ul class="registro-consumo-stock-alert__list">';

        faltantes.forEach(function (fila, index) {
            var faltaTxt = fila.falta.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            var verUrl = urlRegistroMovimientos(fila.codigoPl, idAlmacen);
            html += '<li><span class="registro-consumo-stock-alert__item">'
                + escapeHtml(fila.nom) + ' · PL ' + escapeHtml(fila.codigoPl)
                + ' · faltan ' + faltaTxt
                + '</span> '
                + '<a class="registro-consumo-stock-alert__link" href="' + escapeHtml(verUrl) + '" target="_blank" rel="noopener">Ver consumos</a> '
                + '<a class="registro-consumo-stock-alert__link" href="' + escapeHtml(urlRegistroMovimientos(fila.codigoPl, idAlmacen, 'traslados')) + '" target="_blank" rel="noopener">Traslados</a> '
                + '<button type="button" class="btn btn--outline btn--sm registro-consumo-stock-alert__btn" data-ingreso-emergencia="' + index + '">Ingreso temporal</button>'
                + '</li>';
        });

        html += '</ul>';
        alertBox.hidden = false;
        alertBox.innerHTML = html;

        alertBox.querySelectorAll('[data-ingreso-emergencia]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-ingreso-emergencia'), 10);
                var fila = faltantes[idx];
                if (fila) {
                    abrirIngresoEmergenciaDesdeConsumo(fila);
                }
            });
        });
    }

    function actualizarHintStockConsumo() {
        var hint = document.getElementById('egreso-materiales-hint');
        var wrap = document.getElementById('egreso-materiales-tabla-wrap');

        if (!hint || !wrap || wrap.hidden) {
            return;
        }

        if (!almacenConsumoSeleccionado()) {
            hint.hidden = false;
            hint.textContent = 'Seleccione un almacén para ver el stock disponible.';
            hint.classList.remove('is-error');
            return;
        }

        hint.hidden = true;
        hint.textContent = '';
    }

    function actualizarStockEnTablaConsumo(materiales) {
        var body = document.getElementById('egreso-materiales-body');
        if (!body) {
            return;
        }

        var stockPorId = {};
        (Array.isArray(materiales) ? materiales : []).forEach(function (item) {
            if (!item || !item.id_material_pl) {
                return;
            }
            stockPorId[String(item.id_material_pl)] = item.stock;
        });

        body.querySelectorAll('tr[data-consumo-row]').forEach(function (tr) {
            var input = tr.querySelector('input[name*="[id_material_pl]"]');
            var tdStock = tr.querySelector('.registro-consumo-tabla__stock');
            if (!input || !tdStock) {
                return;
            }

            var idMaterial = String(input.value || '');
            var stock = Object.prototype.hasOwnProperty.call(stockPorId, idMaterial)
                ? stockPorId[idMaterial]
                : null;
            pintarCeldaStockConsumo(
                tdStock,
                stock == null ? null : Number(stock)
            );
        });

        actualizarHintStockConsumo();
        actualizarAlertaStockConsumo();
    }

    function sincronizarStockMaterialesConsumo() {
        var programa = document.getElementById('egreso-id-programa');
        var presentacion = document.getElementById('egreso-id-presentacion');
        if (!programa || !programa.value || !presentacion || !presentacion.value) {
            return;
        }

        if (!almacenConsumoSeleccionado()) {
            actualizarStockEnTablaConsumo([]);
            return;
        }

        return cargarMaterialesConsumo(programa.value, presentacion.value, { soloStock: true });
    }

    function recalcularTotalesConsumo() {
        var body = document.getElementById('egreso-materiales-body');
        if (!body) {
            return;
        }

        var factores = factoresCajasDesdeTabla(body);
        var params = parametrosPresentacionConsumo();

        body.querySelectorAll('tr[data-consumo-row]').forEach(function (tr) {
            if (tr.getAttribute('data-total-manual') === '1') {
                return;
            }

            var totalInput = tr.querySelector('[data-consumo-total]');
            if (!totalInput) {
                return;
            }

            var total = calcularTotalMaterial(tr, factores, params);
            var nomFamilia = tr.getAttribute('data-nom-familia') || '';
            var decimales = tr.getAttribute('data-decimales-cantidad');
            totalInput.value = total > 0
                ? redondearConsumo(total, nomFamilia, decimales).toFixed(decimalesFamiliaConsumo(nomFamilia, decimales))
                : '';
        });

        actualizarAlertaStockConsumo();
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
                hint.textContent = 'Este producto no tiene materiales activos en Receta.';
            }
            return;
        }

        items.forEach(function (item, index) {
            var idMaterial = String(item.id_material_pl || '');
            var override = overrideDeMaterial(idMaterial);
            var totalOverride = totalOverrideDeMaterial(idMaterial);
            var stock = item.stock == null ? null : Number(item.stock);
            var usaPallets = !!item.usa_pallets;
            var esCajaEditable = esCajaEditableItem(item);
            var esCajaFactor = esCajaEditable;
            var esFilaCaja = esCajaEditable;
            var claveUso = item.nom_uso_clave || claveUsoConsumo(item.nom_uso);
            var tr = document.createElement('tr');
            tr.setAttribute('data-consumo-row', '1');
            tr.setAttribute('data-cantidad-caja', String(item.cantidad_por_caja || 0));
            tr.setAttribute('data-nom-familia', item.nom_familia || '');
            tr.setAttribute('data-decimales-cantidad', String(item.decimales_cantidad != null ? item.decimales_cantidad : ''));
            tr.setAttribute('data-usa-pallets', usaPallets ? '1' : '0');
            tr.setAttribute('data-es-caja', esFilaCaja ? '1' : '0');
            tr.setAttribute('data-uso', claveUso);
            tr.setAttribute('data-codigo-pl', item.codigo_pl || '');
            if (consumoEditLineas[idMaterial]) {
                tr.classList.add('is-edit-row');
            }
            if (esCajaFactor) {
                tr.classList.add('is-caja-editable');
            }

            var tdMaterial = document.createElement('td');
            tdMaterial.className = 'registro-consumo-tabla__cell--material';
            var materialText = document.createElement('span');
            materialText.className = 'registro-consumo-tabla__material';
            materialText.textContent = item.nom_material || '—';
            materialText.title = item.nom_material || '';
            tdMaterial.appendChild(materialText);

            var tdCodigo = document.createElement('td');
            tdCodigo.className = 'registro-consumo-tabla__code';
            tdCodigo.textContent = item.codigo_pl || '—';
            tdCodigo.title = item.codigo_pl || '';

            var tdUso = document.createElement('td');
            tdUso.className = 'registro-consumo-tabla__uso';
            var badgeUso = document.createElement('span');
            pintarBadgeObservacionConsumo(badgeUso, item.clase_css_observacion || '');
            badgeUso.textContent = item.nom_uso || '—';
            badgeUso.title = item.nom_uso || '';
            tdUso.appendChild(badgeUso);

            var tdCaja = document.createElement('td');
            tdCaja.className = 'registro-consumo-tabla__qty registro-consumo-tabla__cant-receta';
            var hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'lineas[' + index + '][id_material_pl]';
            hiddenId.value = idMaterial;
            tdCaja.appendChild(hiddenId);

            var editLinea = consumoEditLineas[idMaterial];
            if (editLinea && editLinea.id_movimiento) {
                var hiddenMov = document.createElement('input');
                hiddenMov.type = 'hidden';
                hiddenMov.name = 'lineas[' + index + '][id_movimiento]';
                hiddenMov.value = String(editLinea.id_movimiento);
                tdCaja.appendChild(hiddenMov);
            }

            var qtyReceta = document.createElement('span');
            qtyReceta.textContent = formatearNumero(item.cantidad_por_caja || 0, item.nom_familia, item.decimales_cantidad);
            tdCaja.appendChild(qtyReceta);

            var tdPersonalizado = document.createElement('td');
            tdPersonalizado.className = 'registro-consumo-tabla__qty registro-consumo-tabla__personalizado';
            if (esCajaFactor) {
                var inputCaja = document.createElement('input');
                inputCaja.type = 'number';
                inputCaja.className = 'field__input';
                inputCaja.min = '0';
                inputCaja.step = stepCantidadConsumo(item.nom_familia, item.decimales_cantidad);
                inputCaja.placeholder = '';
                inputCaja.value = override;
                inputCaja.name = 'lineas[' + index + '][cantidad]';
                inputCaja.setAttribute('data-consumo-override', idMaterial);
                inputCaja.setAttribute('data-caja-factor', '1');
                inputCaja.setAttribute('data-uso', claveUso);
                inputCaja.addEventListener('input', function () {
                    consumoOverrides[idMaterial] = inputCaja.value;
                    tr.removeAttribute('data-total-manual');
                    recalcularTotalesConsumo();
                });
                tdPersonalizado.appendChild(inputCaja);
            } else {
                tdPersonalizado.classList.add('registro-consumo-tabla__personalizado--empty');
                var dashPersonalizado = document.createElement('span');
                dashPersonalizado.textContent = '—';
                tdPersonalizado.appendChild(dashPersonalizado);
            }

            var tdTotal = document.createElement('td');
            tdTotal.className = 'registro-consumo-tabla__qty registro-consumo-tabla__total';
            var totalInput = document.createElement('input');
            totalInput.type = 'number';
            totalInput.className = 'field__input';
            totalInput.min = '0';
            totalInput.step = stepCantidadConsumo(item.nom_familia, item.decimales_cantidad);
            totalInput.name = 'lineas[' + index + '][total]';
            totalInput.setAttribute('data-consumo-total', '1');
            totalInput.setAttribute('data-material-id', idMaterial);
            if (totalOverride != null && String(totalOverride).trim() !== '') {
                totalInput.value = redondearConsumo(totalOverride, item.nom_familia, item.decimales_cantidad)
                    .toFixed(decimalesFamiliaConsumo(item.nom_familia, item.decimales_cantidad));
                totalInput.setAttribute('data-total-manual', '1');
                tr.setAttribute('data-total-manual', '1');
            }
            totalInput.addEventListener('input', function () {
                var texto = String(totalInput.value || '').trim();
                if (texto === '') {
                    delete consumoTotalOverrides[idMaterial];
                    tr.removeAttribute('data-total-manual');
                    totalInput.removeAttribute('data-total-manual');
                    recalcularTotalesConsumo();
                    return;
                }
                consumoTotalOverrides[idMaterial] = totalInput.value;
                tr.setAttribute('data-total-manual', '1');
                totalInput.setAttribute('data-total-manual', '1');
                actualizarAlertaStockConsumo();
            });
            tdTotal.appendChild(totalInput);

            var tdStock = document.createElement('td');
            tdStock.className = 'registro-consumo-tabla__qty registro-consumo-tabla__stock';
            pintarCeldaStockConsumo(tdStock, stock);

            tr.appendChild(tdMaterial);
            tr.appendChild(tdCodigo);
            tr.appendChild(tdUso);
            tr.appendChild(tdCaja);
            tr.appendChild(tdPersonalizado);
            tr.appendChild(tdTotal);
            tr.appendChild(tdStock);
            body.appendChild(tr);
        });

        wrap.hidden = false;
        actualizarHintStockConsumo();

        recalcularTotalesConsumo();
    }

    function pintarProgramasConsumo(programa, items, idProgramaSeleccionado) {
        programa.innerHTML = '<option value="">Seleccionar programa</option>';
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = String(item.id_programa);
            opt.textContent = item.nom_programa || ('#' + item.id_programa);
            programa.appendChild(opt);
        });
        programa.disabled = items.length === 0;
        if (idProgramaSeleccionado) {
            programa.value = String(idProgramaSeleccionado);
        }
    }

    function programasOfflinePorGeneral(idProgramaGeneral) {
        var mapa = window.registroConsumoProgramasPorGeneral || {};

        return mapa[String(idProgramaGeneral || '')] || [];
    }

    function pintarPresentacionesConsumo(presentacion, items, idPresentacionSeleccionada) {
        presentacion.innerHTML = '<option value="">Seleccionar producto</option>';
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
    }

    function prepararEnvioConsumo() {
        recalcularTotalesConsumo();
        ['egreso-id-programa', 'egreso-id-presentacion'].forEach(function (id) {
            var campo = document.getElementById(id);
            if (campo && String(campo.value || '').trim() !== '') {
                campo.disabled = false;
            }
        });
    }

    window.registroPrepararEgresoSubmit = prepararEnvioConsumo;

    function programaEnLista(items, idProgramaSeleccionado) {
        if (!idProgramaSeleccionado) {
            return true;
        }

        return items.some(function (item) {
            return String(item.id_programa) === String(idProgramaSeleccionado);
        });
    }

    function cargarProgramasConsumo(idProgramaGeneral, idProgramaSeleccionado) {
        var programa = document.getElementById('egreso-id-programa');

        if (!programa) {
            return Promise.resolve();
        }

        if (!idProgramaGeneral) {
            programa.innerHTML = '<option value="">Seleccione un programa general</option>';
            programa.disabled = true;
            return Promise.resolve();
        }

        var offlineItems = programasOfflinePorGeneral(idProgramaGeneral);

        if (offlineItems.length > 0 && (!idProgramaSeleccionado || programaEnLista(offlineItems, idProgramaSeleccionado))) {
            pintarProgramasConsumo(programa, offlineItems, idProgramaSeleccionado);
            return Promise.resolve();
        }

        if (!consumoProgramasUrl || !navigator.onLine) {
            pintarProgramasConsumo(programa, offlineItems, idProgramaSeleccionado);
            return Promise.resolve();
        }

        programa.disabled = true;
        programa.innerHTML = '<option value="">Cargando programas...</option>';

        return fetch(consumoProgramasUrl + '?id_programa_general=' + encodeURIComponent(idProgramaGeneral), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var items = Array.isArray(data.programas) ? data.programas : [];
                pintarProgramasConsumo(programa, items, idProgramaSeleccionado);
            })
            .catch(function () {
                pintarProgramasConsumo(programa, offlineItems, idProgramaSeleccionado);
            });
    }

    function cargarPresentacionesConsumo(idPrograma, idPresentacionSeleccionada) {
        var presentacion = document.getElementById('egreso-id-presentacion');

        if (!presentacion) {
            return Promise.resolve();
        }

        vaciarTablaMaterialesConsumo('Seleccione un producto para cargar los materiales desde Receta.');

        if (!idPrograma || !consumoPresentacionesUrl) {
            presentacion.innerHTML = '<option value="">Seleccione un programa</option>';
            presentacion.disabled = true;
            return Promise.resolve();
        }

        if (!navigator.onLine && consumoPresentacionesCache[idPrograma]) {
            pintarPresentacionesConsumo(presentacion, consumoPresentacionesCache[idPrograma], idPresentacionSeleccionada);
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
                consumoPresentacionesCache[idPrograma] = items;
                pintarPresentacionesConsumo(presentacion, items, idPresentacionSeleccionada);
            })
            .catch(function () {
                if (consumoPresentacionesCache[idPrograma]) {
                    pintarPresentacionesConsumo(presentacion, consumoPresentacionesCache[idPrograma], idPresentacionSeleccionada);
                    return;
                }
                presentacion.innerHTML = '<option value="">Error al cargar</option>';
                presentacion.disabled = true;
            });
    }

    function cargarMaterialesConsumo(idPrograma, idPresentacion, opciones) {
        opciones = opciones || {};

        if (!idPrograma || !idPresentacion || !consumoMaterialesUrl) {
            vaciarTablaMaterialesConsumo('Seleccione un producto para cargar los materiales desde Receta.');
            return Promise.resolve();
        }

        var wrap = document.getElementById('egreso-materiales-tabla-wrap');
        var hint = document.getElementById('egreso-materiales-hint');
        if (!opciones.soloStock) {
            if (hint) {
                hint.hidden = false;
                hint.textContent = 'Cargando materiales...';
            }
            if (wrap) {
                wrap.hidden = true;
            }
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
                var materiales = Array.isArray(data.materiales) ? data.materiales : [];

                if (opciones.soloStock) {
                    actualizarStockEnTablaConsumo(materiales);
                    return;
                }

                var params = data.presentacion || null;
                if (consumoOld && (consumoOld.conformacion_dentro != null || consumoOld.pallets_fuera != null)) {
                    params = {
                        conformacion: consumoOld.conformacion_dentro,
                        pallets: consumoOld.pallets_fuera,
                    };
                }
                actualizarParametrosPresentacionConsumo(params);
                renderTablaMaterialesConsumo(materiales);
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

    function poblarSelectMateriales(select, items) {
        if (!select) {
            return;
        }

        var seleccionado = select.value;
        select.innerHTML = '<option value="">Seleccionar material</option>';

        (Array.isArray(items) ? items : []).forEach(function (item) {
            if (!item || !item.id_material_pl) {
                return;
            }
            ensureMaterialOption(
                select,
                item.id_material_pl,
                item.codigo_pl || '',
                item.nom_material || '',
                true
            );
        });

        if (seleccionado && select.querySelector('option[value="' + seleccionado + '"]')) {
            select.value = seleccionado;
        }
    }

    function buscarMaterialesPicker(prefix, termino) {
        var select = document.querySelector('[data-material-select="' + prefix + '"]');
        if (!select || !materialesBuscarUrl || prefix === 'egreso') {
            return Promise.resolve();
        }

        var q = String(termino || '').trim();
        if (q !== '' && q.length < 2) {
            return Promise.resolve();
        }

        var url = materialesBuscarUrl + '?q=' + encodeURIComponent(q) + '&limite=50';

        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                poblarSelectMateriales(select, data.materiales || []);
            })
            .catch(function () {});
    }

    function ensureMaterialOption(select, idMaterial, codigoPl, nomMaterialPl, soloAgregar) {
        if (!select || !idMaterial) {
            return;
        }

        var value = String(idMaterial);
        var option = Array.prototype.find.call(select.options, function (opt) {
            return opt.value === value;
        });

        if (!option) {
            option = document.createElement('option');
            option.value = value;
            select.appendChild(option);
        }

        option.setAttribute('data-codigo-pl', codigoPl || '');
        option.setAttribute('data-nom-material-pl', nomMaterialPl || '');
        option.textContent = (codigoPl || value) + (nomMaterialPl ? ' — ' + nomMaterialPl : '');
        if (!soloAgregar) {
            select.value = value;
        }

        materialesMap[value] = {
            codigo: codigoPl || (materialesMap[value] && materialesMap[value].codigo) || '',
            nombre: nomMaterialPl || (materialesMap[value] && materialesMap[value].nombre) || '',
        };
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

        if (!searchInput || !select) {
            return;
        }

        var codigo = searchInput.value.trim();
        if (codigo.length >= 2 && materialesBuscarUrl) {
            buscarMaterialesPicker(prefix, codigo).then(function () {
                if (codigo.length >= 4 && materialUrl) {
                    fetch(materialUrl + '?codigo=' + encodeURIComponent(codigo), {
                        headers: { Accept: 'application/json' },
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.encontrado) {
                                ensureMaterialOption(
                                    select,
                                    data.id_material_pl,
                                    data.codigo_pl || '',
                                    data.nom_material || ''
                                );
                                syncMaterial(prefix);
                            }
                        })
                        .catch(function () {});
                }
            });
            return;
        }

        if (!materialUrl || codigo.length < 4) {
            return;
        }

        fetch(materialUrl + '?codigo=' + encodeURIComponent(codigo), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.encontrado) {
                    ensureMaterialOption(
                        select,
                        data.id_material_pl,
                        data.codigo_pl || '',
                        data.nom_material || ''
                    );
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

        var prefix = select.getAttribute('data-material-select');
        if (prefix === 'ingreso' || prefix === 'traslado') {
            select.addEventListener('focus', function () {
                if (select.options.length <= 1) {
                    buscarMaterialesPicker(prefix, '');
                }
            });
        }
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

    var egresoProgramaGeneral = document.getElementById('egreso-id-programa-general');
    var egresoPrograma = document.getElementById('egreso-id-programa');
    var egresoPresentacion = document.getElementById('egreso-id-presentacion');
    var egresoAlmacen = document.getElementById('egreso-id-almacen');

    if (egresoProgramaGeneral) {
        egresoProgramaGeneral.addEventListener('change', function () {
            cargarPresentacionesConsumo('');
            cargarProgramasConsumo(egresoProgramaGeneral.value);
        });
    }

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
            var body = document.getElementById('egreso-materiales-body');
            if (body && body.querySelector('tr[data-consumo-row]')) {
                sincronizarStockMaterialesConsumo();
                return;
            }
            recargarMaterialesConsumoActuales();
        });
    }

    ['egreso-conformacion-dentro', 'egreso-pallets-fuera'].forEach(function (id) {
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

    if (abrirEgreso) abrirEgreso.addEventListener('click', function () {
        resetModalCreacion('egreso');
        abrirModal('egreso');
    });
    if (abrirTraslado) abrirTraslado.addEventListener('click', function () {
        resetModalCreacion('traslado');
        abrirModal('traslado');
        buscarMaterialesPicker('traslado', '');
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
    if (consumoOld && (consumoOld.id_programa || consumoOld.id_programa_general)) {
        var programaSelectOld = document.getElementById('egreso-id-programa');
        var programaGeneralSelectOld = document.getElementById('egreso-id-programa-general');
        consumoOverrides = {};
        consumoTotalOverrides = {};
        (Array.isArray(consumoOld.lineas) ? consumoOld.lineas : []).forEach(function (linea) {
            if (linea && linea.id_material_pl) {
                var id = String(linea.id_material_pl);
                if (linea.cantidad != null && linea.cantidad !== '') {
                    consumoOverrides[id] = String(linea.cantidad);
                }
                if (linea.total != null && linea.total !== '') {
                    consumoTotalOverrides[id] = String(linea.total);
                }
            }
        });
        if (programaGeneralSelectOld && consumoOld.id_programa_general) {
            programaGeneralSelectOld.value = String(consumoOld.id_programa_general);
        }
        if (programaSelectOld) {
            cargarProgramasConsumo(consumoOld.id_programa_general || '', consumoOld.id_programa || '')
                .then(function () {
                    return cargarPresentacionesConsumo(consumoOld.id_programa || '', consumoOld.id_presentacion || '');
                })
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
    // materialesMap declared earlier for lazy option injection

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
                '<td class="registro-ingreso-preview__qty">' +
                    '<input type="number" class="field__input registro-ingreso-preview__qty-input" ' +
                    'name="lineas[' + index + '][cantidad]" ' +
                    'value="' + escapeHtml(linea.cantidad) + '" ' +
                    'min="0.01" step="0.01" inputmode="decimal" ' +
                    'data-ingreso-cantidad-index="' + index + '">' +
                '</td>' +
                '<td class="registro-ingreso-preview__actions">' +
                    '<button type="button" class="icon-action icon-action--delete" data-quitar-ingreso-linea="' + index + '" title="Quitar">&times;</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        hidden.innerHTML = ingresoLineas.map(function (linea, index) {
            var hiddenLinea = '<input type="hidden" name="lineas[' + index + '][id_material_pl]" value="' + linea.idMaterialPL + '">';

            if (linea.idMovimiento) {
                hiddenLinea += '<input type="hidden" name="lineas[' + index + '][id_movimiento]" value="' + linea.idMovimiento + '">';
            }

            return hiddenLinea;
        }).join('');

        body.querySelectorAll('[data-ingreso-cantidad-index]').forEach(function (input) {
            input.addEventListener('change', function () {
                var idx = parseInt(input.getAttribute('data-ingreso-cantidad-index'), 10);
                if (!ingresoLineas[idx]) {
                    return;
                }

                var val = parseFloat(String(input.value).replace(',', '.'));
                if (!val || val <= 0) {
                    input.value = ingresoLineas[idx].cantidad;
                    mostrarHintIngreso('La cantidad debe ser mayor a cero.', true);
                    return;
                }

                var cantidad = val.toFixed(2);
                ingresoLineas[idx].cantidad = cantidad;
                ingresoLineas[idx].cantidadTexto = val.toLocaleString('es-PE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                input.value = cantidad;
            });
        });

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

    function abrirIngresoEmergenciaDesdeConsumo(fila) {
        var egresoAlmacen = document.getElementById('egreso-id-almacen');
        var ingresoAlmacen = document.getElementById('ingreso-id-almacen');
        var checkAjuste = document.getElementById('ingreso-ajuste-temporal');
        var obs = document.getElementById('ingreso-observacion');

        resetIngresoModalState();

        if (egresoAlmacen && ingresoAlmacen && egresoAlmacen.value) {
            ingresoAlmacen.value = egresoAlmacen.value;
        }

        if (checkAjuste) {
            checkAjuste.checked = true;
        }
        if (obs) {
            obs.value = INGRESO_OBS_AJUSTE;
        }

        var cantidad = Math.max(0.01, Math.round(fila.falta * 100) / 100);
        var material = materialesMap[String(fila.idMaterial)] || {};

        ingresoLineas.push({
            idMaterialPL: String(fila.idMaterial),
            cantidad: cantidad.toFixed(2),
            cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            codigoPl: fila.codigoPl || material.codigo || '',
            nomMaterial: fila.nom || material.nombre || '',
            almacenCodigo: almacenCodigoIngreso(),
        });

        renderIngresoPreview();
        abrirModal('ingreso');
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

    var egresoForm = document.getElementById('registro-egreso-form');
    if (egresoForm) {
        egresoForm.addEventListener('submit', function () {
            prepararEnvioConsumo();
        });
    }

    function mostrarEstadoXmlIngreso(mensaje, tipo) {
        var status = document.getElementById('ingreso-xml-lectura-status');
        if (!status) {
            return;
        }

        if (tipo === 'ok') {
            tipo = 'success';
        }

        status.hidden = false;
        status.textContent = mensaje;
        status.className = 'field__hint registro-xml-lectura registro-ingreso-archivos__status' + (tipo ? ' is-' + tipo : '');
    }

    function resetIngresoModalState() {
        ingresoLineas = [];
        renderIngresoPreview();
        limpiarLineaIngreso();
        ocultarEstadoXmlIngreso();
        var checkAjuste = document.getElementById('ingreso-ajuste-temporal');
        if (checkAjuste) {
            checkAjuste.checked = false;
        }
        ['ingreso-archivo-pdf', 'ingreso-archivo-xml', 'ingreso-archivo-excel'].forEach(function (id) {
            var input = document.getElementById(id);
            if (input) {
                input.value = '';
            }
        });
    }

    var checkAjusteIngreso = document.getElementById('ingreso-ajuste-temporal');
    if (checkAjusteIngreso) {
        checkAjusteIngreso.addEventListener('change', function () {
            var obs = document.getElementById('ingreso-observacion');
            if (!obs) {
                return;
            }
            if (checkAjusteIngreso.checked && obs.value.trim() === '') {
                obs.value = INGRESO_OBS_AJUSTE;
            }
        });
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

    function seleccionarAlmacenIngreso(idAlmacen) {
        var select = document.getElementById('ingreso-id-almacen');
        if (!select || !idAlmacen) {
            return;
        }
        select.value = String(idAlmacen);
    }

    function aplicarLineasDesdeExcel(data) {
        if (data.id_almacen) {
            seleccionarAlmacenIngreso(data.id_almacen);
        }
        aplicarLineasDesdeXml(data.lineas || []);
    }

    function leerExcelIngreso(archivo) {
        var url = window.registroIngresoLeerExcelUrl || '';
        var csrf = document.querySelector('meta[name="csrf-token"]');

        if (!url || !archivo) {
            return;
        }

        mostrarEstadoXmlIngreso('Leyendo Excel de stock (SAFCO)...', 'loading');

        var formData = new FormData();
        formData.append('archivo_excel', archivo);

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
                    mostrarEstadoXmlIngreso(data.error || data.message || 'No se pudo leer el Excel.', 'error');
                    return;
                }

                var aplicarExcel = function () {
                    aplicarLineasDesdeExcel(data);
                    var msg = (data.lineas || []).length + ' material(es) cargados para SAFCO';
                    if (data.codigo_almacen) {
                        msg += ' (' + data.codigo_almacen + ')';
                    }
                    if (Array.isArray(data.advertencias) && data.advertencias.length > 0) {
                        msg += '. ' + data.advertencias.length + ' fila(s) omitida(s).';
                    }
                    mostrarEstadoXmlIngreso(msg, 'success');
                };

                if (ingresoLineas.length > 0 && window.TalsaConfirm) {
                    window.TalsaConfirm.show({
                        title: 'Reemplazar materiales',
                        message: 'Ya hay materiales agregados. ¿Deseas reemplazarlos por los del Excel?',
                        confirmLabel: 'Sí, reemplazar',
                        variant: 'warning',
                    }).then(function (approved) {
                        if (approved) {
                            aplicarExcel();
                        } else {
                            mostrarEstadoXmlIngreso('Excel leído. Materiales no reemplazados.', 'warn');
                        }
                    });
                    return;
                }

                aplicarExcel();
            })
            .catch(function () {
                mostrarEstadoXmlIngreso('Error de conexión al leer el Excel.', 'error');
            });
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

    var excelIngresoInput = document.getElementById('ingreso-archivo-excel');
    if (excelIngresoInput) {
        excelIngresoInput.addEventListener('change', function () {
            var archivo = excelIngresoInput.files && excelIngresoInput.files[0];
            if (!archivo) {
                ocultarEstadoXmlIngreso();
                return;
            }

            leerExcelIngreso(archivo);
        });
    }

    restaurarLineasIngresoOld();

    if (abrirIngreso) {
        abrirIngreso.addEventListener('click', function () {
            resetModalCreacion('ingreso');
            abrirModal('ingreso');
            buscarMaterialesPicker('ingreso', '');
        });
    }

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

        mostrarCampoDetalle('registro-detalle-motivo-wrap', 'registro-detalle-motivo', data.motivo);
        mostrarCampoDetalle('registro-detalle-programa-wrap', 'registro-detalle-programa', data.programa);
        mostrarCampoDetalle('registro-detalle-presentacion-wrap', 'registro-detalle-presentacion', data.presentacion);
        mostrarCampoDetalle('registro-detalle-observacion-wrap', 'registro-detalle-observacion', data.observacion);

        var esEgreso = data.tipo === 'egreso';
        if (!esEgreso) {
            mostrarCampoDetalle('registro-detalle-factura-wrap', 'registro-detalle-factura', data.factura);
            mostrarCampoDetalle('registro-detalle-guia-wrap', 'registro-detalle-guia', data.guia);
        } else {
            ['registro-detalle-factura-wrap', 'registro-detalle-guia-wrap'].forEach(function (id) {
                var wrap = document.getElementById(id);
                if (wrap) {
                    wrap.hidden = true;
                }
            });
        }

        var pdfWrap = document.getElementById('registro-detalle-pdf-wrap');
        var pdfValue = document.getElementById('registro-detalle-pdf');
        if (pdfWrap && pdfValue) {
            if (!esEgreso && data.pdf_url) {
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

        if (tipo !== 'traslado') {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'contexto=edicion';
        }

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
                        var codigoPl = linea.codigo_pl || material.codigo || '';
                        var nomMaterial = linea.nom_material || material.nombre || '';

                        if (idMaterial) {
                            materialesMap[idMaterial] = { codigo: codigoPl, nombre: nomMaterial };
                        }

                        return {
                            idMovimiento: linea.id_movimiento ? String(linea.id_movimiento) : null,
                            idMaterialPL: idMaterial,
                            cantidad: cantidad.toFixed(2),
                            cantidadTexto: cantidad.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                            codigoPl: codigoPl,
                            nomMaterial: nomMaterial,
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
                    consumoEditLineas = {};
                    consumoOverrides = {};
                    consumoTotalOverrides = {};

                    (Array.isArray(edit.lineas) ? edit.lineas : []).forEach(function (linea) {
                        if (!linea || !linea.id_material_pl) {
                            return;
                        }

                        var clave = String(linea.id_material_pl);
                        consumoEditLineas[clave] = {
                            id_movimiento: linea.id_movimiento || null,
                            cantidad: linea.cantidad != null ? Number(linea.cantidad) : null,
                        };

                        if (linea.cantidad != null && linea.cantidad !== '') {
                            consumoTotalOverrides[clave] = String(linea.cantidad);
                        }
                    });

                    setFieldValue(form, 'fecha', edit.fecha || '');
                    setFieldValue(form, 'id_almacen', edit.id_almacen || '');
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

                    var programaGeneralSelect = document.getElementById('egreso-id-programa-general');
                    var programaSelect = document.getElementById('egreso-id-programa');

                    if (programaGeneralSelect && edit.id_programa_general) {
                        programaGeneralSelect.value = String(edit.id_programa_general);
                    }

                    abrirModal('egreso');

                    var ctx = data.contexto_edicion || {};
                    var presentacionSelect = document.getElementById('egreso-id-presentacion');

                    cargarProgramasConsumo(edit.id_programa_general || '', edit.id_programa || '')
                        .then(function () {
                            if (Array.isArray(ctx.presentaciones) && ctx.presentaciones.length && presentacionSelect) {
                                consumoPresentacionesCache[String(edit.id_programa || '')] = ctx.presentaciones;
                                pintarPresentacionesConsumo(
                                    presentacionSelect,
                                    ctx.presentaciones,
                                    edit.id_presentacion || ''
                                );
                                return;
                            }

                            return cargarPresentacionesConsumo(edit.id_programa || '', edit.id_presentacion || '');
                        })
                        .then(function () {
                            if (Array.isArray(ctx.materiales)) {
                                actualizarParametrosPresentacionConsumo(ctx.presentacion || null);
                                renderTablaMaterialesConsumo(ctx.materiales);
                                return;
                            }

                            return cargarMaterialesConsumo(
                                edit.id_programa || '',
                                edit.id_presentacion || ''
                            );
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
                    ensureMaterialOption(
                        selectTraslado,
                        edit.id_material_pl,
                        edit.codigo_pl || '',
                        edit.nom_material || ''
                    );
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

    document.addEventListener('registro-offline-queued', function (event) {
        var tipo = event.detail && event.detail.tipo;
        if (tipo === 'ingreso') {
            resetModalCreacion('ingreso');
            cerrarModal('ingreso');
        } else if (tipo === 'egreso') {
            resetModalCreacion('egreso');
            cerrarModal('egreso');
        }
    });
})();
