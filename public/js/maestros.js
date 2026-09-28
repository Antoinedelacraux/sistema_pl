(function () {
    var modal = document.getElementById('maestros-modal');
    var form = document.getElementById('maestros-form');
    var methodInput = document.getElementById('maestros-form-method');
    var title = document.getElementById('maestros-modal-title');
    var nombreInput = document.getElementById('maestros-nombre');
    var codigoInput = document.getElementById('maestros-codigo');
    var fechaInput = document.getElementById('maestros-fecha');
    var estadoWrap = document.getElementById('maestros-estado-wrap');
    var estadoSelect = document.getElementById('maestros-estado');
    var submitBtn = document.getElementById('maestros-submit');
    var abrirCrearBtn = document.getElementById('maestros-abrir-crear');
    var config = window.maestrosConfig || {};

    function cerrarModal() {
        if (!modal) {
            return;
        }

        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    }

    function abrirModal() {
        if (!modal) {
            return;
        }

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        if (codigoInput) {
            codigoInput.focus();
        } else if (nombreInput) {
            nombreInput.focus();
        }
    }

    function prepararCrear() {
        if (!form || !config.storeUrl) {
            return;
        }

        form.action = config.storeUrl;
        form.method = 'post';
        if (methodInput) {
            methodInput.disabled = true;
            methodInput.value = 'POST';
        }
        if (title) {
            title.textContent = 'Agregar ' + (config.label || 'registro');
        }
        if (codigoInput) {
            codigoInput.value = '';
        }
        if (nombreInput) {
            nombreInput.value = '';
        }
        if (fechaInput) {
            fechaInput.value = '';
        }
        if (estadoWrap) {
            estadoWrap.hidden = true;
        }
        if (estadoSelect) {
            estadoSelect.value = '1';
        }
        if (submitBtn) {
            submitBtn.textContent = 'Guardar';
        }
        abrirModal();
    }

    function prepararEditar(id, nombre, estado, codigo, fecha) {
        if (!form || !config.updateUrlTemplate) {
            return;
        }

        form.action = config.updateUrlTemplate.replace('__ID__', String(id));
        form.method = 'post';
        if (methodInput) {
            methodInput.disabled = false;
            methodInput.value = 'PUT';
        }
        if (title) {
            title.textContent = 'Editar ' + (config.label || 'registro');
        }
        if (codigoInput) {
            codigoInput.value = codigo || '';
        }
        if (nombreInput) {
            nombreInput.value = nombre || '';
        }
        if (fechaInput) {
            fechaInput.value = fecha || '';
        }
        if (estadoWrap) {
            estadoWrap.hidden = false;
        }
        if (estadoSelect) {
            estadoSelect.value = String(estado === 0 ? 0 : 1);
        }
        if (submitBtn) {
            submitBtn.textContent = 'Actualizar';
        }
        abrirModal();
    }

    if (abrirCrearBtn) {
        abrirCrearBtn.addEventListener('click', prepararCrear);
    }

    document.querySelectorAll('[data-maestros-editar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            prepararEditar(
                btn.getAttribute('data-id'),
                btn.getAttribute('data-nombre'),
                parseInt(btn.getAttribute('data-estado') || '1', 10),
                btn.getAttribute('data-codigo') || '',
                btn.getAttribute('data-fecha') || ''
            );
        });
    });

    document.querySelectorAll('[data-maestros-cerrar]').forEach(function (btn) {
        btn.addEventListener('click', cerrarModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            cerrarModal();
        }
    });

    var navSelect = document.querySelector('[data-maestros-nav]');
    if (navSelect) {
        navSelect.addEventListener('change', function () {
            if (navSelect.value) {
                window.location.href = navSelect.value;
            }
        });
    }
})();
