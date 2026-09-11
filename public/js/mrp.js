(function () {
    var syncBtn = document.getElementById('mrp-sync-toggle');
    var scrollPanes = [];
    var syncKey = 'mrp-scroll-sync';
    var syncing = false;
    var syncEnabled = false;
    var syncRaf = 0;
    var scrollEndTimers = new WeakMap();

    function collectScrollPanes() {
        scrollPanes = Array.prototype.slice.call(document.querySelectorAll('[data-mrp-scroll]'));
    }

    function setSyncState(active) {
        syncEnabled = active;
        if (!syncBtn) {
            return;
        }
        syncBtn.setAttribute('aria-checked', active ? 'true' : 'false');
        syncBtn.classList.toggle('is-active', active);
        try {
            localStorage.setItem(syncKey, active ? '1' : '0');
        } catch (e) {}
    }

    function marcarScrollActivo(pane) {
        pane.classList.add('is-scrolling');
        var prev = scrollEndTimers.get(pane);
        if (prev) {
            clearTimeout(prev);
        }
        scrollEndTimers.set(pane, setTimeout(function () {
            pane.classList.remove('is-scrolling');
        }, 120));
    }

    function syncScroll(source) {
        if (!syncEnabled || syncing) {
            return;
        }
        syncing = true;
        var maxTop = source.scrollHeight - source.clientHeight;
        var maxLeft = source.scrollWidth - source.clientWidth;
        var ratioTop = maxTop > 0 ? source.scrollTop / maxTop : 0;
        var ratioLeft = maxLeft > 0 ? source.scrollLeft / maxLeft : 0;

        scrollPanes.forEach(function (otro) {
            if (otro === source) {
                return;
            }
            var otroMaxTop = otro.scrollHeight - otro.clientHeight;
            var otroMaxLeft = otro.scrollWidth - otro.clientWidth;
            otro.scrollTop = otroMaxTop > 0 ? Math.round(ratioTop * otroMaxTop) : 0;
            otro.scrollLeft = otroMaxLeft > 0 ? Math.round(ratioLeft * otroMaxLeft) : 0;
        });
        syncing = false;
    }

    function onPaneScroll(pane) {
        marcarScrollActivo(pane);
        if (!syncEnabled || syncing) {
            return;
        }
        if (syncRaf) {
            cancelAnimationFrame(syncRaf);
        }
        var src = pane;
        syncRaf = requestAnimationFrame(function () {
            syncRaf = 0;
            syncScroll(src);
        });
    }

    function bindScrollSync() {
        collectScrollPanes();
        if (!syncBtn || scrollPanes.length < 2) {
            return;
        }

        try {
            setSyncState(localStorage.getItem(syncKey) === '1');
        } catch (e) {
            setSyncState(false);
        }

        if (!syncBtn.dataset.bound) {
            syncBtn.dataset.bound = '1';
            syncBtn.addEventListener('click', function () {
                setSyncState(!syncEnabled);
            });
        }

        scrollPanes.forEach(function (pane) {
            if (pane.dataset.syncBound === '1') {
                return;
            }
            pane.dataset.syncBound = '1';
            pane.addEventListener('scroll', function () {
                onPaneScroll(pane);
            }, { passive: true });
        });
    }

    document.querySelectorAll('[data-mrp-expand]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var section = btn.closest('.mrp-section');
            if (!section) {
                return;
            }

            var expanded = section.classList.toggle('is-expanded');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            btn.textContent = expanded ? 'Contraer' : 'Expandir';
        });
    });

    var importBtn = document.getElementById('mrp-importar-excel');
    var importInput = document.getElementById('mrp-archivo-excel');
    var importForm = document.getElementById('mrp-import-form');

    if (importBtn && importInput && importForm) {
        importBtn.addEventListener('click', function () {
            importInput.value = '';
            importInput.click();
        });

        importInput.addEventListener('change', function () {
            if (!importInput.files || !importInput.files.length) {
                return;
            }
            importBtn.disabled = true;
            importBtn.textContent = 'Importando…';
            importForm.submit();
        });
    }

    function whenIdle(fn) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: 2500 });
        } else {
            setTimeout(fn, 500);
        }
    }

    function cargarSeccionLazy(section) {
        var url = section.getAttribute('data-mrp-lazy-url');
        if (!url || section.dataset.mrpLazyLoaded === '1') {
            return Promise.resolve();
        }

        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var placeholder = section.querySelector('.mrp-section__lazy');
                if (placeholder) {
                    placeholder.remove();
                }
                section.insertAdjacentHTML('beforeend', html);
                section.dataset.mrpLazyLoaded = '1';
                bindScrollSync();
            })
            .catch(function () {
                var placeholder = section.querySelector('.mrp-section__lazy');
                if (placeholder) {
                    placeholder.textContent = 'No se pudo cargar esta sección.';
                }
            });
    }

    function cargarSeccionesLazy() {
        var secciones = Array.prototype.slice.call(document.querySelectorAll('[data-mrp-lazy-url]'));
        if (!secciones.length) {
            return;
        }

        var indice = 0;
        function cargarSiguiente() {
            if (indice >= secciones.length) {
                return;
            }
            var section = secciones[indice];
            indice += 1;
            cargarSeccionLazy(section).finally(function () {
                whenIdle(cargarSiguiente);
            });
        }

        whenIdle(cargarSiguiente);
    }

    bindScrollSync();
    cargarSeccionesLazy();
})();
