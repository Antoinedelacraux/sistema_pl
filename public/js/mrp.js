(function () {
    var syncBtn = document.getElementById('mrp-sync-toggle');
    var scrollPanes = [];
    var syncKey = 'mrp-scroll-sync';
    var syncing = false;
    var syncEnabled = false;
    var syncRaf = 0;

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
                if (!syncEnabled) {
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

    bindScrollSync();
})();
