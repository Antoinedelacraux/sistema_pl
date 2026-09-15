(function () {
    var modal = document.getElementById('proveedor-modal');
    var form = document.getElementById('proveedor-form');
    var updateUrlTemplate = window.proveedorUpdateUrlTemplate || '';

    var agregarModal = document.getElementById('proveedor-agregar-modal');
    var abrirAgregarBtn = document.getElementById('proveedor-abrir-agregar');
    var agregarIdMaterial = document.getElementById('proveedor-agregar-id-material');
    var agregarCodigoPlSelect = document.getElementById('proveedor-agregar-codigo-pl-select');
    var agregarMaterialPlSelect = document.getElementById('proveedor-agregar-material-pl-select');
    var agregarCodigoPlSearch = document.getElementById('proveedor-agregar-codigo-pl-search');
    var agregarMaterialPlSearch = document.getElementById('proveedor-agregar-material-pl-search');
    var materialCodigoUrl = window.proveedorMaterialCodigoUrl || '';
    var materialNombreUrl = window.proveedorMaterialNombreUrl || '';
    var importPreviewUrl = window.proveedorImportPreviewUrl || '';
    var agregarNomProveedor = document.getElementById('proveedor-agregar-nom-proveedor');
    var importArchivo = document.getElementById('proveedor-import-archivo');
    var importElegirBtn = document.getElementById('proveedor-import-elegir');
    var importNombreArchivo = document.getElementById('proveedor-import-nombre-archivo');
    var importError = document.getElementById('proveedor-import-error');
    var importResumen = document.getElementById('proveedor-import-resumen');
    var importPreviewWrap = document.getElementById('proveedor-import-preview-wrap');
    var importPreviewBody = document.getElementById('proveedor-import-preview-body');
    var importConfirmForm = document.getElementById('proveedor-import-confirm-form');
    var importConfirmBtn = document.getElementById('proveedor-import-confirmar');
    var importFilasHidden = document.getElementById('proveedor-import-filas-hidden');
    var importNomHidden = document.getElementById('proveedor-import-nom-proveedor-hidden');
    var importVolverBtn = document.getElementById('proveedor-import-volver');
    var importFilasOk = [];
    var lookupTimer = null;
    var columnasModal = document.getElementById('proveedor-columnas-modal');
    var abrirColumnasBtn = document.getElementById('proveedor-abrir-columnas');
    var columnasStorageKey = 'proveedor-columnas-ocultas';
    var proveedorColumnasKeys = window.proveedorColumnasKeys || {};

    function crearBloqueProveedor(nombre, key) {
        var bloque = document.createElement('div');
        bloque.className = 'modal-proveedor-block field--full';
        bloque.setAttribute('data-proveedor-block', '');
        bloque.setAttribute('data-nom-proveedor', nombre);
        bloque.setAttribute('data-proveedor-dinamico', '1');

        bloque.innerHTML =
            '<div class="modal-proveedor-block__head">' +
                '<span class="modal-proveedor-block__title"></span>' +
                '<label class="modal-proveedor-block__eliminar">' +
                    '<input type="checkbox" name="proveedores[' + key + '][eliminar]" value="1" data-field="eliminar">' +
                    'Eliminar' +
                '</label>' +
            '</div>' +
            '<input type="hidden" name="proveedores[' + key + '][nombre]" value="">' +
            '<div class="modal-proveedor-block__grid">' +
                '<label class="field">' +
                    '<span class="field__label"></span>' +
                    '<input type="text" class="field__input" name="proveedores[' + key + '][codigo]" data-field="codigo" maxlength="30" autocomplete="off">' +
                '</label>' +
                '<label class="field">' +
                    '<span class="field__label"></span>' +
                    '<input type="text" class="field__input" name="proveedores[' + key + '][nom_material]" data-field="nom_material" maxlength="255">' +
                '</label>' +
            '</div>';

        bloque.querySelector('.modal-proveedor-block__title').textContent = nombre;
        bloque.querySelector('input[type="hidden"]').value = nombre;
        var labels = bloque.querySelectorAll('.field__label');
        if (labels[0]) {
            labels[0].textContent = 'Código ' + nombre;
        }
        if (labels[1]) {
            labels[1].textContent = 'Material ' + nombre;
        }

        var eliminarInput = bloque.querySelector('[data-field="eliminar"]');
        if (eliminarInput) {
            eliminarInput.addEventListener('change', function () {
                bloque.classList.toggle('is-marked-delete', eliminarInput.checked);
            });
        }

        return bloque;
    }

    function claveProveedor(nombre) {
        if (proveedorColumnasKeys[nombre]) {
            return proveedorColumnasKeys[nombre];
        }

        return 'prov-' + nombre.toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 10);
    }

    function limpiarBloquesDinamicosProveedor() {
        if (!modal) {
            return;
        }

        modal.querySelectorAll('[data-proveedor-dinamico]').forEach(function (bloque) {
            bloque.remove();
        });
    }

    function asegurarBloquesProveedor(proveedores) {
        if (!modal) {
            return;
        }

        var grid = modal.querySelector('.modal__grid');
        if (!grid) {
            return;
        }

        Object.keys(proveedores).forEach(function (nombre) {
            var existe = Array.from(grid.querySelectorAll('[data-nom-proveedor]')).some(function (bloque) {
                return bloque.getAttribute('data-nom-proveedor') === nombre;
            });

            if (existe) {
                return;
            }

            var bloque = crearBloqueProveedor(nombre, claveProveedor(nombre));
            grid.appendChild(bloque);
        });
    }

    function abrirModalProveedor(fila) {
        if (!modal || !form || !fila) {
            return;
        }

        var nroFila = fila.dataset.nroFila;
        if (!nroFila || !updateUrlTemplate) {
            return;
        }

        form.action = updateUrlTemplate.replace(/\/0$/, '/' + nroFila);

        var proveedorNroFila = document.getElementById('proveedor-nro-fila');
        if (proveedorNroFila) {
            proveedorNroFila.value = nroFila;
        }

        var familia = document.getElementById('proveedor-id-familia');
        var codigoPl = document.getElementById('proveedor-codigo-pl');
        var nomMaterialPl = document.getElementById('proveedor-nom-material-pl');

        if (familia) {
            familia.value = fila.dataset.idFamilia || '';
        }
        if (codigoPl) {
            codigoPl.value = fila.dataset.codigoPl || '';
        }
        if (nomMaterialPl) {
            nomMaterialPl.value = fila.dataset.nomMaterialPl || '';
        }

        var proveedores = {};
        try {
            proveedores = JSON.parse(fila.getAttribute('data-proveedores') || '{}');
        } catch (error) {
            proveedores = {};
        }

        limpiarBloquesDinamicosProveedor();
        asegurarBloquesProveedor(proveedores);

        document.querySelectorAll('[data-proveedor-block]').forEach(function (bloque) {
            var nombre = bloque.getAttribute('data-nom-proveedor') || '';
            var datos = proveedores[nombre] || { codigo: '', nomMaterial: '' };
            var codigoInput = bloque.querySelector('[data-field="codigo"]');
            var materialInput = bloque.querySelector('[data-field="nom_material"]');
            var eliminarInput = bloque.querySelector('[data-field="eliminar"]');

            if (codigoInput) {
                codigoInput.value = datos.codigo || '';
            }
            if (materialInput) {
                materialInput.value = datos.nomMaterial || '';
            }
            if (eliminarInput) {
                eliminarInput.checked = false;
            }

            bloque.classList.toggle('is-marked-delete', false);
        });

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModalProveedor() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if ((!agregarModal || !agregarModal.classList.contains('is-open')) && (!columnasModal || !columnasModal.classList.contains('is-open'))) {
            document.body.classList.remove('modal-open');
        }
    }

    function abrirModalAgregar() {
        if (!agregarModal) {
            return;
        }

        agregarModal.classList.add('is-open');
        agregarModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModalAgregar() {
        if (!agregarModal) {
            return;
        }

        agregarModal.classList.remove('is-open');
        agregarModal.setAttribute('aria-hidden', 'true');
        resetImportPreview();
        activarTabAgregar('manual');
        if ((!modal || !modal.classList.contains('is-open')) && (!columnasModal || !columnasModal.classList.contains('is-open'))) {
            document.body.classList.remove('modal-open');
        }
    }

    function activarTabAgregar(tab) {
        document.querySelectorAll('[data-proveedor-tab]').forEach(function (btn) {
            var activo = btn.getAttribute('data-proveedor-tab') === tab;
            btn.classList.toggle('is-active', activo);
            btn.setAttribute('aria-selected', activo ? 'true' : 'false');
        });
        document.querySelectorAll('[data-proveedor-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-proveedor-panel') !== tab;
        });
    }

    function resetImportPreview() {
        importFilasOk = [];
        if (importArchivo) {
            importArchivo.value = '';
        }
        if (importNombreArchivo) {
            importNombreArchivo.textContent = '';
        }
        if (importError) {
            importError.hidden = true;
            importError.textContent = '';
        }
        if (importResumen) {
            importResumen.hidden = true;
            importResumen.textContent = '';
        }
        if (importPreviewWrap) {
            importPreviewWrap.hidden = true;
        }
        if (importPreviewBody) {
            importPreviewBody.innerHTML = '';
        }
        if (importFilasHidden) {
            importFilasHidden.innerHTML = '';
        }
        if (importConfirmBtn) {
            importConfirmBtn.disabled = true;
        }
    }

    function mostrarErrorImport(mensaje) {
        if (!importError) {
            return;
        }
        importError.textContent = mensaje;
        importError.hidden = false;
    }

    function etiquetaEstado(estado) {
        if (estado === 'ok') {
            return 'OK';
        }
        if (estado === 'sin_pl') {
            return 'PL no encontrado';
        }
        if (estado === 'duplicado') {
            return 'Duplicado';
        }

        return 'Inválido';
    }

    function renderPreview(data) {
        if (!importPreviewBody || !importPreviewWrap) {
            return;
        }

        importFilasOk = [];
        importPreviewBody.innerHTML = '';

        (data.filas || []).forEach(function (fila) {
            var tr = document.createElement('tr');
            var esOk = fila.estado === 'ok';
            tr.className = esOk ? 'is-ok' : 'is-error';

            if (esOk) {
                importFilasOk.push({
                    id_material_pl: fila.id_material_pl,
                    codigo: fila.codigo_proveedor,
                    nom_material: fila.nom_material,
                });
            }

            var estadoTexto = fila.mensaje ? (etiquetaEstado(fila.estado) + ': ' + fila.mensaje) : etiquetaEstado(fila.estado);

            tr.innerHTML =
                '<td>' + (fila.fila_excel || '') + '</td>' +
                '<td>' + (fila.codigo_pl || '') + '</td>' +
                '<td>' + (fila.nom_material_pl || '—') + '</td>' +
                '<td>' + (fila.nom_familia || '—') + '</td>' +
                '<td>' + (fila.codigo_proveedor || '') + '</td>' +
                '<td>' + (fila.nom_material || '') + '</td>' +
                '<td>' + estadoTexto + '</td>';

            importPreviewBody.appendChild(tr);
        });

        importPreviewWrap.hidden = false;

        var res = data.resumen || {};
        if (importResumen) {
            importResumen.hidden = false;
            importResumen.textContent =
                'Leídas: ' + (res.leidas || 0) +
                ' · Válidas: ' + (res.validas || 0) +
                ' · Sin PL: ' + (res.sin_pl || 0) +
                ' · Duplicadas: ' + (res.duplicadas || 0) +
                ' · Inválidas: ' + (res.invalidas || 0);
        }

        if (importConfirmBtn) {
            importConfirmBtn.disabled = importFilasOk.length === 0;
        }
    }

    function enviarPreviewImport(archivo) {
        if (!importPreviewUrl || !archivo) {
            return;
        }

        var nomProveedor = agregarNomProveedor ? agregarNomProveedor.value.trim() : '';
        if (nomProveedor === '') {
            mostrarErrorImport('Seleccione un proveedor antes de importar.');
            return;
        }

        if (importError) {
            importError.hidden = true;
            importError.textContent = '';
        }
        if (importPreviewBody) {
            importPreviewBody.innerHTML = '';
        }
        if (importPreviewWrap) {
            importPreviewWrap.hidden = true;
        }
        if (importResumen) {
            importResumen.hidden = true;
        }
        importFilasOk = [];
        if (importConfirmBtn) {
            importConfirmBtn.disabled = true;
        }
        if (importNombreArchivo) {
            importNombreArchivo.textContent = archivo.name;
        }

        var token = document.querySelector('meta[name="csrf-token"]');
        var formData = new FormData();
        formData.append('archivo_excel', archivo);
        formData.append('nom_proveedor', nomProveedor);
        if (token) {
            formData.append('_token', token.getAttribute('content'));
        }

        if (importElegirBtn) {
            importElegirBtn.disabled = true;
            importElegirBtn.textContent = 'Leyendo…';
        }

        fetch(importPreviewUrl, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' },
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    mostrarErrorImport(result.json.error || 'No se pudo leer el Excel.');
                    return;
                }
                renderPreview(result.json);
            })
            .catch(function () {
                mostrarErrorImport('Error de red al leer el Excel.');
            })
            .finally(function () {
                if (importElegirBtn) {
                    importElegirBtn.disabled = false;
                    importElegirBtn.textContent = 'Elegir Excel';
                }
            });
    }

    function prepararConfirmImport() {
        if (!importFilasHidden || !importNomHidden || !agregarNomProveedor) {
            return;
        }

        importNomHidden.value = agregarNomProveedor.value;
        importFilasHidden.innerHTML = '';

        importFilasOk.forEach(function (fila, index) {
            ['id_material_pl', 'codigo', 'nom_material'].forEach(function (campo) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'filas[' + index + '][' + campo + ']';
                input.value = fila[campo];
                importFilasHidden.appendChild(input);
            });
        });
    }

    function leerColumnasOcultas() {
        try {
            var raw = localStorage.getItem(columnasStorageKey);
            if (!raw) {
                return [];
            }
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function guardarColumnasOcultas(ocultas) {
        localStorage.setItem(columnasStorageKey, JSON.stringify(ocultas));
    }

    function aplicarVisibilidadColumnas(ocultas) {
        document.querySelectorAll('#proveedor-grid [data-col]').forEach(function (node) {
            var key = node.getAttribute('data-col');
            var oculta = ocultas.indexOf(key) !== -1;
            node.classList.toggle('is-col-hidden', oculta);
        });
    }

    function sincronizarChecksColumnas(ocultas) {
        document.querySelectorAll('[data-col-toggle]').forEach(function (input) {
            if (input.disabled) {
                return;
            }
            var key = input.getAttribute('data-col-toggle');
            input.checked = ocultas.indexOf(key) === -1;
        });
    }

    function abrirModalColumnas() {
        if (!columnasModal) {
            return;
        }

        sincronizarChecksColumnas(leerColumnasOcultas());
        columnasModal.classList.add('is-open');
        columnasModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModalColumnas() {
        if (!columnasModal) {
            return;
        }

        columnasModal.classList.remove('is-open');
        columnasModal.setAttribute('aria-hidden', 'true');
        if ((!modal || !modal.classList.contains('is-open')) && (!agregarModal || !agregarModal.classList.contains('is-open'))) {
            document.body.classList.remove('modal-open');
        }
    }

    function toggleColumna(key, visible) {
        var ocultas = leerColumnasOcultas().filter(function (item) {
            return item !== key;
        });

        if (!visible) {
            ocultas.push(key);
        }

        guardarColumnasOcultas(ocultas);
        aplicarVisibilidadColumnas(ocultas);
    }

    function restaurarColumnas() {
        guardarColumnasOcultas([]);
        sincronizarChecksColumnas([]);
        aplicarVisibilidadColumnas([]);
    }

    function ensureMaterialOption(select, idMaterial, codigoPl, nomMaterialPl) {
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
        option.textContent = codigoPl
            ? (codigoPl + (nomMaterialPl ? ' — ' + nomMaterialPl : ''))
            : (nomMaterialPl || value);
        select.value = value;
    }

    function setMaterialPlSeleccion(idMaterial, codigoPl, nomMaterialPl) {
        if (agregarIdMaterial) {
            agregarIdMaterial.value = idMaterial || '';
        }

        if (idMaterial) {
            ensureMaterialOption(agregarCodigoPlSelect, idMaterial, codigoPl, nomMaterialPl);
            ensureMaterialOption(agregarMaterialPlSelect, idMaterial, codigoPl, nomMaterialPl);
            if (agregarMaterialPlSelect) {
                var matOpt = agregarMaterialPlSelect.options[agregarMaterialPlSelect.selectedIndex];
                if (matOpt) {
                    matOpt.textContent = nomMaterialPl || codigoPl || String(idMaterial);
                }
            }
        } else {
            if (agregarCodigoPlSelect) {
                agregarCodigoPlSelect.value = '';
            }
            if (agregarMaterialPlSelect) {
                agregarMaterialPlSelect.value = '';
            }
        }

        if (agregarCodigoPlSearch) {
            agregarCodigoPlSearch.value = codigoPl || '';
        }

        if (agregarMaterialPlSearch) {
            agregarMaterialPlSearch.value = nomMaterialPl || '';
        }
    }

    function syncSelectMaterialPl(sourceSelect) {
        if (!sourceSelect || !sourceSelect.value) {
            setMaterialPlSeleccion('', '', '');
            return;
        }

        var option = sourceSelect.options[sourceSelect.selectedIndex];
        setMaterialPlSeleccion(
            sourceSelect.value,
            option.getAttribute('data-codigo-pl') || '',
            option.getAttribute('data-nom-material-pl') || ''
        );
    }

    function activarModoCampo(fieldMode, mode) {
        if (!fieldMode) {
            return;
        }

        fieldMode.querySelectorAll('.mode-switch__btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
        });

        fieldMode.querySelectorAll('.field-mode__panel').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== mode;
        });
    }

    function buscarMaterialPlPorCodigo() {
        if (!agregarCodigoPlSearch || !materialCodigoUrl) {
            return;
        }

        var codigo = agregarCodigoPlSearch.value.trim();
        if (codigo.length < 4) {
            if (agregarIdMaterial) {
                agregarIdMaterial.value = '';
            }
            return;
        }

        fetch(materialCodigoUrl + '?codigo=' + encodeURIComponent(codigo), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.encontrado) {
                    setMaterialPlSeleccion(data.id_material_pl, data.codigo_pl, data.nom_material);
                } else if (agregarIdMaterial) {
                    agregarIdMaterial.value = '';
                }
            })
            .catch(function () {});
    }

    function buscarMaterialPlPorNombre() {
        if (!agregarMaterialPlSearch || !materialNombreUrl) {
            return;
        }

        var nombre = agregarMaterialPlSearch.value.trim();
        if (nombre.length < 3) {
            if (agregarIdMaterial) {
                agregarIdMaterial.value = '';
            }
            return;
        }

        fetch(materialNombreUrl + '?q=' + encodeURIComponent(nombre), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.encontrado) {
                    setMaterialPlSeleccion(data.id_material_pl, data.codigo_pl, data.nom_material);
                } else if (agregarIdMaterial) {
                    agregarIdMaterial.value = '';
                }
            })
            .catch(function () {});
    }

    if (modal) {
        modal.querySelectorAll('[data-field="eliminar"]').forEach(function (input) {
            input.addEventListener('change', function () {
                var bloque = input.closest('[data-proveedor-block]');
                if (bloque) {
                    bloque.classList.toggle('is-marked-delete', input.checked);
                }
            });
        });
    }

    document.querySelectorAll('[data-editar-proveedor]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirModalProveedor(boton.closest('.proveedor-fila'));
        });
    });

    document.querySelectorAll('[data-cerrar-modal-proveedor]').forEach(function (node) {
        node.addEventListener('click', cerrarModalProveedor);
    });

    document.querySelectorAll('[data-cerrar-modal-proveedor-agregar]').forEach(function (node) {
        node.addEventListener('click', cerrarModalAgregar);
    });

    if (abrirAgregarBtn) {
        abrirAgregarBtn.addEventListener('click', function () {
            resetImportPreview();
            activarTabAgregar('manual');
            abrirModalAgregar();
        });
    }

    document.querySelectorAll('[data-proveedor-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activarTabAgregar(btn.getAttribute('data-proveedor-tab') || 'manual');
        });
    });

    if (importElegirBtn && importArchivo) {
        importElegirBtn.addEventListener('click', function () {
            importArchivo.click();
        });
        importArchivo.addEventListener('change', function () {
            if (!importArchivo.files || !importArchivo.files.length) {
                return;
            }
            enviarPreviewImport(importArchivo.files[0]);
        });
    }

    if (importConfirmForm) {
        importConfirmForm.addEventListener('submit', function () {
            prepararConfirmImport();
            if (importConfirmBtn) {
                importConfirmBtn.disabled = true;
                importConfirmBtn.textContent = 'Guardando…';
            }
        });
    }

    if (importVolverBtn) {
        importVolverBtn.addEventListener('click', function () {
            resetImportPreview();
            activarTabAgregar('manual');
        });
    }

    if (abrirColumnasBtn) {
        abrirColumnasBtn.addEventListener('click', abrirModalColumnas);
    }

    document.querySelectorAll('[data-cerrar-modal-proveedor-columnas]').forEach(function (node) {
        node.addEventListener('click', cerrarModalColumnas);
    });

    document.querySelectorAll('[data-col-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            toggleColumna(input.getAttribute('data-col-toggle'), input.checked);
        });
    });

    var restaurarColumnasBtn = document.getElementById('proveedor-columnas-restaurar');
    if (restaurarColumnasBtn) {
        restaurarColumnasBtn.addEventListener('click', restaurarColumnas);
    }

    aplicarVisibilidadColumnas(leerColumnasOcultas());
    sincronizarChecksColumnas(leerColumnasOcultas());

    document.querySelectorAll('.field-mode').forEach(function (fieldMode) {
        fieldMode.querySelectorAll('.mode-switch__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activarModoCampo(fieldMode, btn.getAttribute('data-mode'));
            });
        });
    });

    if (agregarCodigoPlSelect) {
        agregarCodigoPlSelect.addEventListener('change', function () {
            syncSelectMaterialPl(agregarCodigoPlSelect);
        });
    }

    if (agregarMaterialPlSelect) {
        agregarMaterialPlSelect.addEventListener('change', function () {
            syncSelectMaterialPl(agregarMaterialPlSelect);
        });
    }

    if (agregarCodigoPlSearch) {
        agregarCodigoPlSearch.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(buscarMaterialPlPorCodigo, 350);
        });
        agregarCodigoPlSearch.addEventListener('blur', buscarMaterialPlPorCodigo);
    }

    if (agregarMaterialPlSearch) {
        agregarMaterialPlSearch.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(buscarMaterialPlPorNombre, 350);
        });
        agregarMaterialPlSearch.addEventListener('blur', buscarMaterialPlPorNombre);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            cerrarModalProveedor();
            cerrarModalAgregar();
            cerrarModalColumnas();
        }
    });

    if (modal && modal.querySelector('.modal__errors')) {
        var proveedorNroFila = document.getElementById('proveedor-nro-fila');
        if (proveedorNroFila && proveedorNroFila.value && updateUrlTemplate) {
            form.action = updateUrlTemplate.replace(/\/0$/, '/' + proveedorNroFila.value);
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }
    }

    if (agregarModal && agregarModal.querySelector('.modal__errors')) {
        abrirModalAgregar();

        if (agregarIdMaterial && agregarIdMaterial.value) {
            syncSelectMaterialPl(agregarCodigoPlSelect);
        }
    }
})();
