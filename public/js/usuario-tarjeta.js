(function () {
    var popover = document.getElementById('usuario-tarjeta');
    var estado = document.getElementById('usuario-tarjeta-estado');
    var contenido = document.getElementById('usuario-tarjeta-contenido');
    var urlTemplate = window.usuarioTarjetaUrl || '';
    var cache = {};
    var cargando = false;

    if (!popover || !urlTemplate) {
        return;
    }

    function cerrar() {
        popover.hidden = true;
        popover.setAttribute('aria-hidden', 'true');
        if (contenido) {
            contenido.hidden = true;
        }
        if (estado) {
            estado.hidden = false;
            estado.textContent = 'Cargando…';
        }
    }

    function posicionar(anchor) {
        var panel = popover.querySelector('.usuario-tarjeta__panel');
        if (!panel || !anchor) {
            return;
        }

        var rect = anchor.getBoundingClientRect();
        var panelRect = panel.getBoundingClientRect();
        var top = rect.bottom + 8;
        var left = rect.left;

        if (left + panelRect.width > window.innerWidth - 12) {
            left = window.innerWidth - panelRect.width - 12;
        }

        if (left < 12) {
            left = 12;
        }

        if (top + panelRect.height > window.innerHeight - 12) {
            top = rect.top - panelRect.height - 8;
        }

        panel.style.top = Math.max(12, top) + 'px';
        panel.style.left = left + 'px';
    }

    function render(data) {
        document.getElementById('usuario-tarjeta-avatar').textContent = data.iniciales || 'U';
        document.getElementById('usuario-tarjeta-nombre').textContent = data.nombre || '—';
        document.getElementById('usuario-tarjeta-login').textContent = data.usuario || '—';
        document.getElementById('usuario-tarjeta-area').textContent = data.area || '—';
        document.getElementById('usuario-tarjeta-cargo').textContent = data.cargo || '—';
        document.getElementById('usuario-tarjeta-rol').textContent = data.rol || '—';

        var correoWrap = document.getElementById('usuario-tarjeta-correo-wrap');
        var correo = document.getElementById('usuario-tarjeta-correo');

        if (correoWrap && correo) {
            if (data.correo) {
                correoWrap.hidden = false;
                correo.textContent = data.correo;
            } else {
                correoWrap.hidden = true;
                correo.textContent = '—';
            }
        }

        if (estado) {
            estado.hidden = true;
        }

        if (contenido) {
            contenido.hidden = false;
        }
    }

    function abrir(idUsuario, anchor) {
        if (!idUsuario || cargando) {
            return;
        }

        popover.hidden = false;
        popover.setAttribute('aria-hidden', 'false');

        if (cache[idUsuario]) {
            render(cache[idUsuario]);
            posicionar(anchor);
            return;
        }

        if (estado) {
            estado.hidden = false;
            estado.textContent = 'Cargando…';
        }

        if (contenido) {
            contenido.hidden = true;
        }

        posicionar(anchor);
        cargando = true;

        fetch(urlTemplate.replace('__ID__', String(idUsuario)), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el perfil.');
                }
                return response.json();
            })
            .then(function (data) {
                cache[idUsuario] = data;
                render(data);
                posicionar(anchor);
            })
            .catch(function (error) {
                if (estado) {
                    estado.hidden = false;
                    estado.textContent = error.message || 'Error al cargar.';
                }
            })
            .finally(function () {
                cargando = false;
            });
    }

    document.addEventListener('click', function (event) {
        var boton = event.target.closest('[data-usuario-tarjeta]');

        if (boton) {
            event.preventDefault();
            event.stopPropagation();
            abrir(boton.getAttribute('data-usuario-tarjeta'), boton);
            return;
        }

        if (!popover.hidden && !event.target.closest('.usuario-tarjeta__panel')) {
            cerrar();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !popover.hidden) {
            cerrar();
        }
    });

    document.querySelectorAll('[data-usuario-tarjeta-cerrar]').forEach(function (node) {
        node.addEventListener('click', cerrar);
    });
})();
