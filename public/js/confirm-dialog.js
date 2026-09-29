(function () {
    var root = null;
    var panel = null;
    var titleEl = null;
    var messageEl = null;
    var iconEl = null;
    var cancelBtn = null;
    var confirmBtn = null;
    var textoWrap = null;
    var textoLabel = null;
    var textoInput = null;
    var textoRequerido = '';
    var resolver = null;

    function textoValido() {
        return textoRequerido === '' || (textoInput && textoInput.value.trim() === textoRequerido);
    }

    function actualizarBotonConfirmar() {
        if (confirmBtn) {
            confirmBtn.disabled = !textoValido();
        }
    }

    var icons = {
        danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    function ensureDialog() {
        if (root) {
            return;
        }

        root = document.createElement('div');
        root.className = 'confirm-dialog';
        root.id = 'confirm-dialog';
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML =
            '<div class="confirm-dialog__backdrop" data-confirm-dismiss></div>' +
            '<div class="confirm-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">' +
                '<div class="confirm-dialog__icon" data-confirm-icon></div>' +
                '<h2 class="confirm-dialog__title" id="confirm-dialog-title"></h2>' +
                '<p class="confirm-dialog__message" id="confirm-dialog-message"></p>' +
                '<div class="confirm-dialog__texto" data-confirm-texto-wrap hidden>' +
                    '<label class="confirm-dialog__texto-label" for="confirm-dialog-texto"></label>' +
                    '<input type="text" class="confirm-dialog__texto-input" id="confirm-dialog-texto" autocomplete="off" spellcheck="false">' +
                '</div>' +
                '<div class="confirm-dialog__actions">' +
                    '<button type="button" class="btn btn--outline btn--sm" data-confirm-cancel>Cancelar</button>' +
                    '<button type="button" class="btn btn--sm" data-confirm-ok>Confirmar</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(root);

        panel = root.querySelector('.confirm-dialog__panel');
        titleEl = root.querySelector('.confirm-dialog__title');
        messageEl = root.querySelector('.confirm-dialog__message');
        iconEl = root.querySelector('[data-confirm-icon]');
        cancelBtn = root.querySelector('[data-confirm-cancel]');
        confirmBtn = root.querySelector('[data-confirm-ok]');
        textoWrap = root.querySelector('[data-confirm-texto-wrap]');
        textoLabel = root.querySelector('.confirm-dialog__texto-label');
        textoInput = root.querySelector('.confirm-dialog__texto-input');

        textoInput.addEventListener('input', actualizarBotonConfirmar);

        cancelBtn.addEventListener('click', function () {
            close(false);
        });

        confirmBtn.addEventListener('click', function () {
            if (!textoValido()) {
                return;
            }
            close(true);
        });

        root.querySelector('[data-confirm-dismiss]').addEventListener('click', function () {
            close(false);
        });

        document.addEventListener('keydown', function (event) {
            if (!root.classList.contains('is-open')) {
                return;
            }
            if (event.key === 'Escape') {
                close(false);
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                if (textoValido()) {
                    close(true);
                }
            }
        });
    }

    function close(result) {
        if (!root || !resolver) {
            return;
        }

        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('confirm-dialog-open');

        var resolve = resolver;
        var textoIngresado = textoInput ? textoInput.value.trim() : '';
        resolver = null;
        window.setTimeout(function () {
            resolve(result ? (textoRequerido !== '' ? { approved: true, texto: textoIngresado } : true) : false);
        }, 200);
    }

    function show(options) {
        ensureDialog();

        var config = options || {};
        var variant = config.variant === 'warning' || config.variant === 'info' ? config.variant : 'danger';
        var title = config.title || (variant === 'danger' ? '¿Confirmar acción?' : 'Confirmar');
        var message = config.message || '¿Deseas continuar?';

        titleEl.textContent = title;
        messageEl.textContent = message;
        cancelBtn.textContent = config.cancelLabel || 'Cancelar';
        confirmBtn.textContent = config.confirmLabel || (variant === 'danger' ? 'Sí, continuar' : 'Confirmar');

        iconEl.className = 'confirm-dialog__icon confirm-dialog__icon--' + variant;
        iconEl.innerHTML = icons[variant] || icons.danger;

        confirmBtn.className = 'btn btn--sm ' + (variant === 'danger' ? 'btn--confirm-danger' : 'btn--purple');

        // Confirmación escrita opcional (p.ej. escribir VACIAR).
        textoRequerido = typeof config.requireText === 'string' ? config.requireText.trim() : '';
        textoInput.value = '';
        textoWrap.hidden = textoRequerido === '';
        textoLabel.textContent = textoRequerido !== '' ? 'Escriba ' + textoRequerido + ' para confirmar:' : '';
        textoInput.setAttribute('placeholder', textoRequerido);
        actualizarBotonConfirmar();

        return new Promise(function (resolve) {
            resolver = resolve;
            root.classList.add('is-open');
            root.setAttribute('aria-hidden', 'false');
            document.body.classList.add('confirm-dialog-open');
            window.requestAnimationFrame(function () {
                if (textoRequerido !== '') {
                    textoInput.focus();
                } else {
                    confirmBtn.focus();
                }
            });
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmApproved === '1') {
            delete form.dataset.confirmApproved;
            return;
        }

        event.preventDefault();

        var message = form.getAttribute('data-confirm') || '¿Deseas continuar?';
        var title = form.getAttribute('data-confirm-title') || 'Confirmar acción';
        var variant = form.getAttribute('data-confirm-variant') || 'danger';
        var confirmLabel = form.getAttribute('data-confirm-ok') || 'Sí, continuar';
        var requireText = form.getAttribute('data-confirm-texto') || '';

        show({
            title: title,
            message: message,
            variant: variant,
            confirmLabel: confirmLabel,
            requireText: requireText,
        }).then(function (approved) {
            if (!approved) {
                return;
            }
            if (requireText !== '') {
                var campo = form.querySelector('input[name="confirmacion"]');
                if (!campo) {
                    campo = document.createElement('input');
                    campo.type = 'hidden';
                    campo.name = 'confirmacion';
                    form.appendChild(campo);
                }
                campo.value = approved && approved.texto ? approved.texto : '';
            }
            form.dataset.confirmApproved = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    window.TalsaConfirm = { show: show };
})();
