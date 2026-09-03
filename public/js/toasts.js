(function () {
    var root = document.getElementById('toast-root');
    if (!root) {
        return;
    }

    var icons = {
        ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    function show(message, type, duration) {
        if (!message) {
            return;
        }

        var variant = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'ok');
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + variant;
        toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
        toast.innerHTML =
            '<span class="toast__icon">' + (icons[variant] || icons.info) + '</span>' +
            '<span class="toast__text"></span>' +
            '<button type="button" class="toast__close" aria-label="Cerrar">&times;</button>';
        toast.querySelector('.toast__text').textContent = message;

        root.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        var ttl = typeof duration === 'number' ? duration : (variant === 'error' ? 7000 : 4500);
        var timer = window.setTimeout(function () {
            dismiss(toast);
        }, ttl);

        toast.querySelector('.toast__close').addEventListener('click', function () {
            window.clearTimeout(timer);
            dismiss(toast);
        });
    }

    function dismiss(toast) {
        if (!toast || toast.classList.contains('is-leaving')) {
            return;
        }

        toast.classList.add('is-leaving');
        toast.classList.remove('is-visible');

        window.setTimeout(function () {
            toast.remove();
        }, 280);
    }

    window.TalsaToast = { show: show };
})();
