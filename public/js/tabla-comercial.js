(function () {
    var DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    function estadoEfectivo(idPg, idSem, planificado) {
        var k = key(idPg, idSem);
        if (estadoLocal[k] === 'despachado') return 'despachado';
        var consumido = egresos[k] || 0;
        if (planificado > 0 && consumido >= planificado) return 'realizado';
        if (consumido > 0) return 'proceso';
        return 'no_iniciado';
    }

    var ESTADO_LABEL = {
        despachado:  'Despachado',
        realizado:   'Realizado',
        proceso:     'Proceso',
        no_iniciado: 'No iniciado',
    };

    /* ── Config ──────────────────────────────────────────────── */
    var programas  = window.tcProgramas || [];
    var estados    = window.tcEstados   || {};   // {"idPg_idSem": "despachado"} — solo manual
    var egresos    = window.tcEgresos   || {};   // {"idPg_idSem": suma_cantidad}
    var nombres      = window.tcNombres      || {};
    var semanaUrl    = window.tcSemanaUrlTpl || '';
    var asignarUrl   = window.tcAsignarUrl   || '';
    var estadoUrl    = window.tcEstadoUrl    || '';
    var puedeEditar  = window.tcPuedeEditar  || false;
    var csrfToken    = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    /* ── Estado local (se actualiza al guardar) ──────────────── */
    var estadoLocal = Object.assign({}, estados);

    /* ── Helpers ─────────────────────────────────────────────── */
    function key(idPg, idSem) { return idPg + '_' + idSem; }

    function planDe(idPg, idSem) {
        var p = programas.find(function (x) { return x.id === idPg; });
        return p ? (p.sems[idSem] || 0) : 0;
    }

    function claseCelda(idPg, idSem) {
        var e = estadoEfectivo(idPg, idSem, planDe(idPg, idSem));
        if (e === 'despachado') return 'tc-cell--despachado';
        if (e === 'realizado')  return 'tc-cell--realizado';
        if (e === 'proceso')    return 'tc-cell--en-progreso';
        return '';
    }

    function claseCard(idPg, idSem) {
        var e = estadoEfectivo(idPg, idSem, planDe(idPg, idSem));
        if (e === 'despachado') return 'tc-card--despachado';
        if (e === 'realizado')  return 'tc-card--realizado';
        if (e === 'proceso')    return 'tc-card--en-progreso';
        return '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ════════════════════════════════════════════════════════════
       PLAN DE VENTAS — Colores + Popover
    ════════════════════════════════════════════════════════════ */
    var tabla = document.getElementById('tc-tabla-ventas');
    var popover    = document.getElementById('tc-popover');
    var popTitle   = document.getElementById('tc-pop-title');
    var popEstText = document.getElementById('tc-pop-estado-text');
    var popSelect  = document.getElementById('tc-pop-select');

    var popPlan      = document.getElementById('tc-pop-plan');
    var popConsumido = document.getElementById('tc-pop-consumido');
    var popPct       = document.getElementById('tc-pop-pct');
    var popActivePg  = null;
    var popActiveSem = null;
    var popHideTimer = null;

    /* Aplica colores a todas las celdas al cargar */
    function aplicarColoresCeldas() {
        if (!tabla) return;
        tabla.querySelectorAll('.tc-cell').forEach(function (td) {
            var pg  = parseInt(td.getAttribute('data-pg'), 10);
            var sem = parseInt(td.getAttribute('data-sem'), 10);
            actualizarColorCelda(td, pg, sem);
        });
    }

    function actualizarColorCelda(td, idPg, idSem) {
        td.classList.remove('tc-cell--en-progreso', 'tc-cell--realizado', 'tc-cell--despachado');
        var cls = claseCelda(idPg, idSem);
        if (cls) td.classList.add(cls);
    }

    /* Popover */
    function abrirPopover(td, idPg, idSem) {
        var plan = parseFloat(td.getAttribute('data-plan') || '0');
        if (plan <= 0) return;   // celda sin dato, no mostrar

        popActivePg  = idPg;
        popActiveSem = idSem;

        var nombre   = esc(nombres[idPg] || 'Programa ' + idPg);
        var semLabel = esc(td.getAttribute('data-sem-label') || '');
        var consumido = egresos[key(idPg, idSem)] || 0;
        var pct       = plan > 0 ? Math.min(100, Math.round(consumido / plan * 100)) : 0;
        var estadoEf  = estadoEfectivo(idPg, idSem, plan);

        popTitle.innerHTML = nombre + ' <span style="font-weight:400;color:#6b6384">· ' + semLabel + '</span>';
        if (popEstText)   popEstText.textContent   = ESTADO_LABEL[estadoEf] || estadoEf;
        if (popPlan)      popPlan.textContent      = Number(plan).toLocaleString() + ' cajas';
        if (popConsumido) popConsumido.textContent = Number(consumido).toLocaleString();
        if (popPct)       popPct.textContent       = pct + '%';

        if (popSelect) {
            popSelect.value = estadoLocal[key(idPg, idSem)] === 'despachado' ? 'despachado' : '';
        }

        // Posición junto al cursor / celda
        var rect = td.getBoundingClientRect();
        var top  = rect.bottom + window.scrollY + 4;
        var left = rect.left   + window.scrollX;

        popover.style.top  = top + 'px';
        popover.style.left = left + 'px';
        popover.classList.add('is-visible');
    }

    function cerrarPopover() {
        popover.classList.remove('is-visible');
        popActivePg  = null;
        popActiveSem = null;
    }

    if (tabla) {
        tabla.addEventListener('mouseenter', function (e) {
            var td = e.target.closest('.tc-cell');
            if (!td) return;
            clearTimeout(popHideTimer);
            var pg  = parseInt(td.getAttribute('data-pg'), 10);
            var sem = parseInt(td.getAttribute('data-sem'), 10);
            abrirPopover(td, pg, sem);
        }, true);

        tabla.addEventListener('mouseleave', function (e) {
            var td = e.target.closest('.tc-cell');
            if (!td) return;
            // Delay para que el cursor pueda entrar al popover
            popHideTimer = setTimeout(cerrarPopover, 200);
        }, true);
    }

    if (popover) {
        popover.addEventListener('mouseenter', function () { clearTimeout(popHideTimer); });
        popover.addEventListener('mouseleave', function () { popHideTimer = setTimeout(cerrarPopover, 150); });
    }

    // Guardar estado al cambiar el select
    if (popSelect) {
        popSelect.addEventListener('change', function () {
            if (!popActivePg || !popActiveSem) return;
            var val   = this.value;   // '' | 'despachado' | '_reset'
            var idPg  = popActivePg;
            var idSem = popActiveSem;
            var k     = key(idPg, idSem);

            if (val === '_reset' || val === '') {
                delete estadoLocal[k];
            } else {
                estadoLocal[k] = val;  // solo 'despachado'
            }
            if (val === '_reset') popSelect.value = '';

            // Actualizar popover, celda y tarjeta
            var plan2     = planDe(idPg, idSem);
            var estadoEf2 = estadoEfectivo(idPg, idSem, plan2);
            if (popEstText) popEstText.textContent = ESTADO_LABEL[estadoEf2] || estadoEf2;
            if (popPct)     popPct.textContent     = plan2 > 0 ? Math.min(100, Math.round((egresos[k] || 0) / plan2 * 100)) + '%' : '0%';

            var td2 = tabla && tabla.querySelector('.tc-cell[data-pg="' + idPg + '"][data-sem="' + idSem + '"]');
            if (td2) actualizarColorCelda(td2, idPg, idSem);
            actualizarColorCard(idPg, idSem);

            // Guardar en BD
            postJson(estadoUrl, {
                idProgramaGeneral: idPg,
                idSemProduccion: idSem,
                estado: (val === '' || val === '_reset') ? null : val,
            }).catch(function () { delete estadoLocal[k]; });
        });
    }

    // Cerrar popover al hacer click fuera
    document.addEventListener('click', function (e) {
        if (!popover.contains(e.target) && !e.target.closest('.tc-cell')) {
            cerrarPopover();
        }
    });

    /* ════════════════════════════════════════════════════════════
       KANBAN
    ════════════════════════════════════════════════════════════ */
    var semSelect  = document.getElementById('tc-sem-select');
    var kanbanWrap = document.getElementById('tc-kanban-wrap');
    var dragCard   = null;
    var idSemActual = null;

    if (semSelect) {
        semSelect.addEventListener('change', function () {
            var id = this.value ? parseInt(this.value, 10) : null;
            if (id) cargarYRenderizar(id);
            else kanbanWrap.innerHTML = '';
        });
    }

    function cargarYRenderizar(idSem) {
        idSemActual = idSem;
        kanbanWrap.innerHTML = '<p class="tc-kanban__empty">Cargando…</p>';

        fetch(semanaUrl.replace('__ID__', idSem), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderKanban(idSem, data); })
            .catch(function () {
                kanbanWrap.innerHTML = '<p class="tc-kanban__empty">Error al cargar.</p>';
            });
    }

    function renderKanban(idSem, data) {
        // data: {idPg: {dia, estado}}
        var items = programas.filter(function (p) {
            return (p.sems[idSem] || 0) > 0;
        });

        if (!items.length) {
            kanbanWrap.innerHTML = '<p class="tc-kanban__empty">Sin programas con cantidad en esta semana.</p>';
            return;
        }

        // Agrupar por día
        var porDia = { 0: [] };
        for (var d = 1; d <= 7; d++) porDia[d] = [];

        items.forEach(function (p) {
            var info = data[p.id] || data[String(p.id)] || {};
            var dia  = info.dia != null ? parseInt(info.dia, 10) : 0;
            if (info.estado === 'despachado') estadoLocal[key(p.id, idSem)] = info.estado;
            if (!porDia[dia]) dia = 0;
            porDia[dia].push(p);
        });

        var html = '<div class="tc-kanban">';
        html += columnaHtml(0, 'Sin asignar', porDia[0], idSem);
        for (var d = 1; d <= 7; d++) {
            html += columnaHtml(d, DIAS[d - 1], porDia[d], idSem);
        }
        html += '</div>';

        kanbanWrap.innerHTML = html;
        if (puedeEditar) atarDrag();
    }

    function columnaHtml(dia, titulo, items, idSem) {
        var cards = items.map(function (p) { return cardHtml(p, idSem); }).join('');
        return '<div class="tc-kanban__col" data-dia="' + dia + '">'
            + '<div class="tc-kanban__col-head">' + esc(titulo) + '</div>'
            + '<div class="tc-kanban__col-body" data-drop-zone="' + dia + '">' + cards + '</div>'
            + '</div>';
    }

    function cardHtml(p, idSem) {
        var cls = 'tc-card ' + claseCard(p.id, idSem);
        var drag = puedeEditar ? ' draggable="true"' : '';
        return '<div class="' + cls.trim() + '"' + drag + ' data-id="' + p.id + '" data-sem="' + idSem + '">'
            + '<span class="tc-card__nombre">' + esc(p.nombre) + '</span>'
            + '</div>';
    }

    function actualizarColorCard(idPg, idSem) {
        if (!kanbanWrap) return;
        var card = kanbanWrap.querySelector('.tc-card[data-id="' + idPg + '"][data-sem="' + idSem + '"]');
        if (!card) return;
        card.classList.remove('tc-card--en-progreso', 'tc-card--realizado', 'tc-card--despachado');
        var cls = claseCard(idPg, idSem);
        if (cls) card.classList.add(cls);
    }

    /* Drag & drop */
    function atarDrag() {
        kanbanWrap.querySelectorAll('.tc-card[draggable]').forEach(function (card) {
            card.addEventListener('dragstart', onDragStart);
            card.addEventListener('dragend', onDragEnd);
        });
        kanbanWrap.querySelectorAll('[data-drop-zone]').forEach(function (zone) {
            zone.addEventListener('dragover', onDragOver);
            zone.addEventListener('dragleave', onDragLeave);
            zone.addEventListener('drop', onDrop);
        });
    }

    function onDragStart(e) {
        dragCard = this;
        this.classList.add('tc-card--dragging');
        e.dataTransfer.effectAllowed = 'move';
    }

    function onDragEnd() {
        if (dragCard) dragCard.classList.remove('tc-card--dragging');
        dragCard = null;
        kanbanWrap.querySelectorAll('.tc-kanban__col-body').forEach(function (z) {
            z.classList.remove('tc-kanban__col-body--over');
        });
    }

    function onDragOver(e) { e.preventDefault(); this.classList.add('tc-kanban__col-body--over'); }
    function onDragLeave() { this.classList.remove('tc-kanban__col-body--over'); }

    function onDrop(e) {
        e.preventDefault();
        this.classList.remove('tc-kanban__col-body--over');
        if (!dragCard) return;

        var idPg = parseInt(dragCard.getAttribute('data-id'), 10);
        var dia  = parseInt(this.getAttribute('data-drop-zone'), 10);
        this.appendChild(dragCard);

        postJson(asignarUrl, {
            idProgramaGeneral: idPg,
            idSemProduccion: idSemActual,
            dia: dia === 0 ? null : dia,
        }).catch(function () { cargarYRenderizar(idSemActual); });
    }

    /* ── Inicializar ─────────────────────────────────────────── */
    aplicarColoresCeldas();

    // Auto-seleccionar si solo hay una semana
    if (semSelect && programas.length && semSelect.options.length === 2) {
        semSelect.selectedIndex = 1;
        cargarYRenderizar(parseInt(semSelect.value, 10));
    }
})();
