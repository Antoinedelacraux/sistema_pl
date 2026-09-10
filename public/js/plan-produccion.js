(function () {
    function decimalesFamilia(nomFamilia, decimalesApi) {
        if (decimalesApi != null && decimalesApi !== '') {
            return Number(decimalesApi);
        }

        var familia = String(nomFamilia || '').toLowerCase();
        if (
            familia.indexOf('carton') !== -1 || familia.indexOf('pallet') !== -1
            || familia.indexOf('parihuela') !== -1 || familia.indexOf('temperature recorder') !== -1
        ) {
            return 4;
        }

        return 2;
    }

    function redondear(valor, nomFamilia, decimalesApi) {
        var dec = decimalesFamilia(nomFamilia, decimalesApi);
        var factor = Math.pow(10, dec);

        return Math.round(Number(valor || 0) * factor) / factor;
    }

    function formatear(valor, nomFamilia, decimalesApi) {
        var num = redondear(valor, nomFamilia, decimalesApi);
        if (num === 0) {
            return '—';
        }

        return num.toLocaleString('es-PE', {
            minimumFractionDigits: decimalesFamilia(nomFamilia, decimalesApi),
            maximumFractionDigits: decimalesFamilia(nomFamilia, decimalesApi),
        });
    }

    function parseMatsData() {
        var el = document.getElementById('plan-prod-mats-data');
        if (!el) {
            return {};
        }

        try {
            return JSON.parse(el.textContent || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function weekIds() {
        var table = document.getElementById('plan-prod-table');
        if (!table) {
            return [];
        }

        var attr = table.getAttribute('data-sem-ids') || '';
        if (!attr) {
            return [];
        }

        return attr.split(',').map(function (id) {
            return String(id).trim();
        }).filter(Boolean);
    }

    var matsByPg = parseMatsData();
    var semIds = weekIds();
    var section = document.getElementById('plan-prod-section-plan');
    var editBtn = document.getElementById('plan-prod-edit');
    var saveBtn = document.getElementById('plan-prod-save');
    var cancelBtn = document.getElementById('plan-prod-cancel');
    var form = document.getElementById('plan-prod-form');

    function paintPgQtyCells() {
        document.querySelectorAll('tr.plan-prod-grid__row--pg[data-vals]').forEach(function (tr) {
            if (tr.getAttribute('data-pg-painted') === '1') {
                return;
            }

            var pgId = tr.getAttribute('data-plan-prod-pg') || '';
            var vals = String(tr.getAttribute('data-vals') || '').split(',');

            semIds.forEach(function (semId, idx) {
                var valor = vals[idx] != null ? String(vals[idx]) : '';
                var td = document.createElement('td');
                td.className = 'programas-grid__qty plan-prod-grid__cell--qty plan-prod-grid__cell--pg-qty plan-prod-grid__qty--editable';
                td.setAttribute('data-pg', pgId);
                td.setAttribute('data-sem', semId);
                td.setAttribute('data-val', valor);

                var span = document.createElement('span');
                span.className = 'plan-prod-grid__valor';
                span.textContent = valor
                    ? Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 0 })
                    : '—';
                td.appendChild(span);
                tr.appendChild(td);
            });

            tr.setAttribute('data-pg-painted', '1');
        });
    }

    paintPgQtyCells();

    function pgRow(pgId) {
        return document.querySelector('tr.plan-prod-grid__row--pg[data-plan-prod-pg="' + pgId + '"]');
    }

    function pgInput(pgId, semId) {
        return document.querySelector(
            '.plan-prod-grid__qty--editable[data-pg="' + pgId + '"][data-sem="' + semId + '"]:not([data-mat])'
        );
    }

    function matInput(pgId, semId, matId) {
        return document.querySelector(
            '.plan-prod-grid__qty--mat[data-pg="' + pgId + '"][data-sem="' + semId + '"][data-mat="' + matId + '"]'
        );
    }

    function matRows(pgId) {
        return document.querySelectorAll('[data-plan-prod-mat="' + pgId + '"][data-mat-row]');
    }

    function getCajas(pgId, semId) {
        var td = pgInput(pgId, semId);
        if (!td) {
            return 0;
        }

        var input = td.querySelector('.plan-prod-grid__input');
        var raw = input && !input.hidden ? input.value : td.getAttribute('data-val');

        return Math.max(0, Number(String(raw || '').replace(/,/g, '')) || 0);
    }

    function setCajas(pgId, semId, cajas) {
        var td = pgInput(pgId, semId);
        if (!td) {
            return;
        }

        var valor = cajas > 0 ? String(Math.round(cajas)) : '';
        td.setAttribute('data-val', valor);

        var span = td.querySelector('.plan-prod-grid__valor');
        if (span) {
            span.textContent = cajas > 0 ? Number(cajas).toLocaleString('es-PE', { maximumFractionDigits: 0 }) : '—';
        }

        var input = td.querySelector('.plan-prod-grid__input');
        if (input) {
            input.value = valor;
        }
    }

    function setMaterialQty(td, qty) {
        if (!td) {
            return;
        }

        var tr = td.closest('[data-mat-row]');
        var nomFamilia = tr ? tr.getAttribute('data-nom-familia') : '';
        var decimales = tr ? tr.getAttribute('data-decimales') : '2';
        var num = redondear(qty, nomFamilia, decimales);
        var valor = num > 0 ? num.toFixed(decimalesFamilia(nomFamilia, decimales)) : '';

        td.setAttribute('data-val', valor);
        td.removeAttribute('data-manual');

        var span = td.querySelector('.plan-prod-grid__valor');
        if (span) {
            span.textContent = formatear(num, nomFamilia, decimales);
        }

        var input = td.querySelector('.plan-prod-grid__input');
        if (input) {
            input.value = valor;
            input.removeAttribute('data-manual');
        }
    }

    function qtyForMat(pgId, semId, mat) {
        var overrides = mat.o || {};
        if (Object.prototype.hasOwnProperty.call(overrides, String(semId))) {
            return {
                qty: Number(overrides[String(semId)]) || 0,
                manual: true,
            };
        }

        var cajas = getCajas(pgId, semId);
        var factor = Number(mat.x || 0);
        return {
            qty: redondear(cajas * factor, mat.f || '', mat.dec),
            manual: false,
        };
    }

    function buildMatRows(pgId) {
        var row = pgRow(pgId);
        if (!row || row.getAttribute('data-mats-built') === '1') {
            return;
        }

        var mats = matsByPg[String(pgId)] || [];
        if (!mats.length) {
            row.setAttribute('data-mats-built', '1');
            return;
        }

        var frag = document.createDocumentFragment();
        var editing = section && section.classList.contains('is-editing');

        mats.forEach(function (mat) {
            var tr = document.createElement('tr');
            tr.className = 'plan-prod-grid__row plan-prod-grid__row--mat';
            tr.setAttribute('data-plan-prod-mat', String(pgId));
            tr.setAttribute('data-mat-row', String(mat.id));
            tr.setAttribute('data-es-caja', mat.caja ? '1' : '0');
            tr.setAttribute('data-factor', String(mat.x || 0));
            tr.setAttribute('data-nom-familia', mat.f || '');
            tr.setAttribute('data-decimales', String(mat.dec != null ? mat.dec : 2));
            tr.hidden = true;

            var tdMat = document.createElement('td');
            tdMat.className = 'plan-prod-grid__cell plan-prod-grid__cell--material';
            tdMat.innerHTML =
                '<span class="plan-prod-grid__mat-head">' +
                    '<span class="plan-prod-grid__mat-code"></span>' +
                    '<span class="plan-prod-grid__mat-factor"></span>' +
                '</span>' +
                '<span class="plan-prod-grid__mat-name"></span>';
            tdMat.querySelector('.plan-prod-grid__mat-code').textContent = mat.c || '';
            tdMat.querySelector('.plan-prod-grid__mat-name').textContent = mat.n || '';
            tdMat.querySelector('.plan-prod-grid__mat-factor').textContent = '× ' + (mat.xf || mat.x || '');
            tr.appendChild(tdMat);

            semIds.forEach(function (semId) {
                var info = qtyForMat(pgId, semId, mat);
                var dec = decimalesFamilia(mat.f || '', mat.dec);
                var valor = info.qty > 0 ? info.qty.toFixed(dec) : '';
                var td = document.createElement('td');
                td.className = 'programas-grid__qty plan-prod-grid__cell--qty plan-prod-grid__cell--mat-qty plan-prod-grid__qty--editable plan-prod-grid__qty--mat';
                td.setAttribute('data-pg', String(pgId));
                td.setAttribute('data-sem', String(semId));
                td.setAttribute('data-mat', String(mat.id));
                td.setAttribute('data-val', valor);
                if (info.manual) {
                    td.setAttribute('data-manual', '1');
                }

                var span = document.createElement('span');
                span.className = 'plan-prod-grid__valor';
                span.textContent = formatear(info.qty, mat.f || '', mat.dec);
                if (editing) {
                    span.hidden = true;
                }
                td.appendChild(span);

                if (editing) {
                    var step = Math.pow(10, -dec).toFixed(dec);
                    var input = document.createElement('input');
                    input.type = 'number';
                    input.className = 'plan-prod-grid__input field__input';
                    input.value = valor;
                    input.step = step;
                    input.min = '0';
                    input.setAttribute('inputmode', 'decimal');
                    input.setAttribute('data-original', valor);
                    input.setAttribute('data-name-template', 'materiales[' + pgId + '][' + mat.id + '][' + semId + ']');
                    if (info.manual) {
                        input.setAttribute('data-manual', '1');
                    }
                    input.hidden = false;
                    input.disabled = false;
                    td.appendChild(input);
                }

                tr.appendChild(td);
            });

            frag.appendChild(tr);
        });

        row.parentNode.insertBefore(frag, row.nextSibling);
        row.setAttribute('data-mats-built', '1');

        if (editing) {
            bindRecalcEvents();
        }
    }

    function recalcularSemana(pgId, semId, origen) {
        buildMatRows(pgId);

        var cajas = getCajas(pgId, semId);
        var filas = matRows(pgId);
        var cajaTd = null;
        var cajaFactor = 1;

        filas.forEach(function (tr) {
            if (tr.getAttribute('data-es-caja') === '1') {
                cajaFactor = Number(tr.getAttribute('data-factor') || 1);
                cajaTd = matInput(pgId, semId, tr.getAttribute('data-mat-row'));
            }
        });

        if (origen === 'caja-mat' && cajaTd) {
            var inputCaja = cajaTd.querySelector('.plan-prod-grid__input');
            var rawCaja = inputCaja && !inputCaja.hidden ? inputCaja.value : cajaTd.getAttribute('data-val');
            var qtyCaja = Number(String(rawCaja || '').replace(/,/g, '')) || 0;

            if (cajaFactor > 0 && qtyCaja > 0) {
                cajas = Math.round(qtyCaja / cajaFactor);
            } else {
                cajas = 0;
            }

            setCajas(pgId, semId, cajas);
            origen = 'cajas';
        }

        filas.forEach(function (tr) {
            var matId = tr.getAttribute('data-mat-row');
            var td = matInput(pgId, semId, matId);
            if (!td) {
                return;
            }

            if (td.getAttribute('data-manual') === '1' && origen !== 'cajas' && origen !== 'caja-mat') {
                return;
            }

            var factor = Number(tr.getAttribute('data-factor') || 0);
            var nomFamilia = tr.getAttribute('data-nom-familia') || '';
            var decimales = tr.getAttribute('data-decimales') || '2';
            var qty = redondear(cajas * factor, nomFamilia, decimales);

            setMaterialQty(td, qty);
        });
    }

    document.querySelectorAll('[data-plan-prod-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pgId = btn.getAttribute('data-plan-prod-toggle');
            if (!pgId) {
                return;
            }

            buildMatRows(pgId);

            var filas = document.querySelectorAll('[data-plan-prod-mat="' + pgId + '"]');
            if (!filas.length) {
                return;
            }

            var expanded = btn.getAttribute('aria-expanded') === 'true';
            expanded = !expanded;
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            btn.classList.toggle('is-expanded', expanded);

            filas.forEach(function (tr) {
                tr.hidden = !expanded;
            });
        });
    });

    function ensureInputs() {
        if (!section) {
            return;
        }

        section.querySelectorAll('.plan-prod-grid__qty--editable').forEach(function (td) {
            if (td.querySelector('.plan-prod-grid__input')) {
                return;
            }

            var pg = td.getAttribute('data-pg') || '';
            var sem = td.getAttribute('data-sem') || '';
            var mat = td.getAttribute('data-mat') || '';
            var val = td.getAttribute('data-val') || '';
            var tr = td.closest('[data-mat-row]');
            var decimales = tr ? tr.getAttribute('data-decimales') : '0';
            var step = mat ? Math.pow(10, -decimalesFamilia(tr ? tr.getAttribute('data-nom-familia') : '', decimales)).toFixed(Number(decimales) || 2) : '1';
            var input = document.createElement('input');

            input.type = 'number';
            input.className = 'plan-prod-grid__input field__input';
            input.value = val;
            input.step = step;
            input.min = '0';
            input.setAttribute('inputmode', mat ? 'decimal' : 'numeric');
            input.setAttribute('data-original', val);

            if (mat) {
                input.setAttribute('data-name-template', 'materiales[' + pg + '][' + mat + '][' + sem + ']');
                if (td.getAttribute('data-manual') === '1') {
                    input.setAttribute('data-manual', '1');
                }
            } else {
                input.name = 'cantidades[' + pg + '][' + sem + ']';
            }

            input.hidden = true;
            input.disabled = true;
            td.appendChild(input);
        });
    }

    function bindRecalcEvents() {
        if (!section) {
            return;
        }

        section.querySelectorAll('.plan-prod-grid__qty--editable').forEach(function (td) {
            if (td.getAttribute('data-recalc-bound') === '1') {
                return;
            }

            td.setAttribute('data-recalc-bound', '1');
            td.addEventListener('input', function () {
                var pg = td.getAttribute('data-pg');
                var sem = td.getAttribute('data-sem');
                var mat = td.getAttribute('data-mat');
                if (!pg || !sem) {
                    return;
                }

                if (mat) {
                    var tr = td.closest('[data-mat-row]');
                    td.setAttribute('data-manual', '1');
                    var input = td.querySelector('.plan-prod-grid__input');
                    if (input) {
                        input.setAttribute('data-manual', '1');
                    }

                    if (tr && tr.getAttribute('data-es-caja') === '1') {
                        recalcularSemana(pg, sem, 'caja-mat');
                    }
                    return;
                }

                recalcularSemana(pg, sem, 'cajas');
            });
        });
    }

    function setEditMode(active) {
        if (!section) {
            return;
        }

        if (active) {
            ensureInputs();
            bindRecalcEvents();
        }

        section.classList.toggle('is-editing', active);

        section.querySelectorAll('.plan-prod-grid__input').forEach(function (input) {
            input.hidden = !active;
            input.disabled = !active;
        });

        section.querySelectorAll('.plan-prod-grid__valor').forEach(function (span) {
            span.hidden = active;
        });

        if (editBtn) {
            editBtn.hidden = active;
        }
        if (saveBtn) {
            saveBtn.hidden = !active;
        }
        if (cancelBtn) {
            cancelBtn.hidden = !active;
        }
    }

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            setEditMode(true);
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            section.querySelectorAll('.plan-prod-grid__qty--editable:not([data-mat])').forEach(function (td) {
                var input = td.querySelector('.plan-prod-grid__input');
                var original = input ? (input.getAttribute('data-original') || '') : (td.getAttribute('data-val') || '');
                if (input) {
                    input.value = original;
                    input.removeAttribute('data-manual');
                }
                td.setAttribute('data-val', original);
                td.removeAttribute('data-manual');
                var span = td.querySelector('.plan-prod-grid__valor');
                if (span) {
                    span.textContent = original
                        ? Number(original).toLocaleString('es-PE', { maximumFractionDigits: 0 })
                        : '—';
                }
            });

            document.querySelectorAll('.plan-prod-grid__row--mat').forEach(function (tr) {
                tr.remove();
            });
            document.querySelectorAll('tr.plan-prod-grid__row--pg[data-mats-built]').forEach(function (tr) {
                tr.removeAttribute('data-mats-built');
            });
            document.querySelectorAll('[data-plan-prod-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('is-expanded');
            });

            setEditMode(false);
        });
    }

    if (form && section) {
        form.addEventListener('submit', function () {
            ensureInputs();
            section.querySelectorAll('.plan-prod-grid__input').forEach(function (input) {
                var template = input.getAttribute('data-name-template');
                if (template) {
                    if (input.getAttribute('data-manual') === '1') {
                        input.name = template;
                        input.disabled = false;
                    } else {
                        input.removeAttribute('name');
                        input.disabled = true;
                    }
                    return;
                }

                input.disabled = false;
            });
        });
    }
})();
