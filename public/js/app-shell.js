(function () {
    var shell = document.getElementById('app-shell');
    var toggle = document.getElementById('sidebar-toggle');
    var openBtn = document.getElementById('sidebar-open');
    var backdrop = document.getElementById('sidebar-backdrop');
    var sidebar = document.getElementById('sidebar');
    if (!shell || !toggle) return;

    var storageKey = 'talsa-sidebar-collapsed';
    var mobileQuery = window.matchMedia('(max-width: 920px)');

    function isMobile() {
        return mobileQuery.matches;
    }

    function setCollapsed(collapsed) {
        shell.classList.toggle('is-sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
    }

    function setMobileOpen(open) {
        shell.classList.toggle('is-sidebar-open', open);
        document.body.classList.toggle('is-sidebar-locked', open);
        if (backdrop) {
            backdrop.hidden = !open;
        }
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            openBtn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        }
        toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function finishTransition() {
        shell.classList.remove('is-sidebar-animating');
    }

    function closeSubmenus() {
        document.querySelectorAll('[data-sidebar-submenu].is-open').forEach(function (submenu) {
            submenu.classList.remove('is-open');
            var btn = submenu.querySelector('[data-sidebar-submenu-toggle]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (!isMobile()) {
        setCollapsed(localStorage.getItem(storageKey) === '1');
    } else {
        setCollapsed(false);
        setMobileOpen(false);
    }

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            setMobileOpen(!shell.classList.contains('is-sidebar-open'));
            return;
        }

        var collapsed = !shell.classList.contains('is-sidebar-collapsed');

        if (collapsed) {
            closeSubmenus();
        }

        shell.classList.add('is-sidebar-animating');
        setCollapsed(collapsed);
        localStorage.setItem(storageKey, collapsed ? '1' : '0');

        if (!sidebar) {
            finishTransition();
            return;
        }

        var done = false;
        function onTransitionEnd(event) {
            if (done || event.target !== sidebar || event.propertyName !== 'width') {
                return;
            }
            done = true;
            sidebar.removeEventListener('transitionend', onTransitionEnd);
            finishTransition();
        }

        sidebar.addEventListener('transitionend', onTransitionEnd);
        window.setTimeout(function () {
            if (!done) {
                done = true;
                sidebar.removeEventListener('transitionend', onTransitionEnd);
                finishTransition();
            }
        }, 450);
    });

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (!isMobile()) {
                return;
            }
            setMobileOpen(true);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setMobileOpen(false);
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll('a.sidebar__link, a.sidebar__sublink').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    setMobileOpen(false);
                }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isMobile() && shell.classList.contains('is-sidebar-open')) {
            setMobileOpen(false);
        }
    });

    function onViewportChange() {
        if (isMobile()) {
            setCollapsed(false);
            setMobileOpen(false);
        } else {
            setMobileOpen(false);
            setCollapsed(localStorage.getItem(storageKey) === '1');
        }
    }

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', onViewportChange);
    } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(onViewportChange);
    }
})();

(function () {
    document.querySelectorAll('[data-sidebar-submenu-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var submenu = button.closest('[data-sidebar-submenu]');
            if (!submenu) {
                return;
            }

            var open = submenu.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();

(function () {
    var storageKey = 'talsa-theme';
    var toggle = document.getElementById('theme-toggle');
    var body = document.body;

    if (!body.classList.contains('page-app')) {
        return;
    }

    function applyTheme(theme) {
        if (theme === 'dark') {
            body.setAttribute('data-theme', 'dark');
        } else {
            body.removeAttribute('data-theme');
        }

        if (!toggle) {
            return;
        }

        var label = theme === 'dark' ? 'Modo claro' : 'Modo oscuro';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
    }

    function currentTheme() {
        return body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    try {
        var saved = localStorage.getItem(storageKey);
        if (saved === 'dark' || saved === 'light') {
            applyTheme(saved);
        }
    } catch (e) {}

    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        var next = currentTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(next);

        try {
            localStorage.setItem(storageKey, next);
        } catch (e) {}
    });
})();
