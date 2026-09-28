(function () {
    function buildQueryUrl(form) {
        var pageInput = form.querySelector('input[name="page"]');
        if (pageInput) {
            pageInput.remove();
        }
        var params = new URLSearchParams(new FormData(form));
        params.delete('page');
        var qs = params.toString();

        return qs ? form.action + '?' + qs : form.action;
    }

    function submitFormFull(form) {
        window.location.href = buildQueryUrl(form);
    }

    function replaceSyncBlocks(doc) {
        document.querySelectorAll('[data-grid-sync-id]').forEach(function (current) {
            var syncId = current.getAttribute('data-grid-sync-id');
            if (!syncId) {
                return;
            }
            var next = doc.querySelector('[data-grid-sync-id="' + syncId + '"]');
            if (next) {
                current.innerHTML = next.innerHTML;
            }
        });
    }

    function replaceRegion(html, regionId) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var next = doc.getElementById(regionId);
        var current = document.getElementById(regionId);

        if (!next || !current) {
            return false;
        }

        replaceSyncBlocks(doc);
        current.innerHTML = next.innerHTML;

        var sync = current.querySelector('[data-grid-sync-stat]');
        if (sync) {
            var selector = sync.getAttribute('data-grid-sync-stat');
            var target = selector ? document.querySelector(selector) : null;
            if (target) {
                target.textContent = sync.textContent;
            }
        }

        return true;
    }

    function submitFormAjax(form) {
        var regionId = form.getAttribute('data-grid-filters-region');
        if (!regionId) {
            submitFormFull(form);
            return;
        }

        var url = buildQueryUrl(form);
        var current = document.getElementById(regionId);
        if (current) {
            current.setAttribute('aria-busy', 'true');
            current.classList.add('is-loading');
        }

        fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('fetch');
                }
                return response.text();
            })
            .then(function (html) {
                if (!replaceRegion(html, regionId)) {
                    window.location.href = url;
                    return;
                }
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', url);
                }
                document.dispatchEvent(new CustomEvent('grid-filters:updated', {
                    detail: { regionId: regionId, url: url },
                }));
            })
            .catch(function () {
                window.location.href = url;
            })
            .finally(function () {
                if (current) {
                    current.removeAttribute('aria-busy');
                    current.classList.remove('is-loading');
                }
            });
    }

    function submitForm(form) {
        if (form.getAttribute('data-grid-filters-ajax') === '1') {
            submitFormAjax(form);
            return;
        }
        submitFormFull(form);
    }

    document.querySelectorAll('[data-grid-filters]').forEach(function (form) {
        var timer = null;

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !target.matches('select, input[type="date"]')) {
                return;
            }
            submitForm(form);
        });

        form.addEventListener('input', function (event) {
            if (!event.target.matches('input[type="search"]')) {
                return;
            }
            clearTimeout(timer);
            timer = setTimeout(function () {
                submitForm(form);
            }, 400);
        });

        form.addEventListener('search', function (event) {
            if (!event.target.matches('input[type="search"]')) {
                return;
            }
            clearTimeout(timer);
            submitForm(form);
        });
    });

    document.addEventListener('click', function (event) {
        var clearBtn = event.target.closest('[data-grid-filters-clear]');
        if (clearBtn) {
            var clearForm = clearBtn.closest('[data-grid-filters]');
            if (clearForm) {
                event.preventDefault();
                window.location.href = clearForm.getAttribute('data-clear-url') || clearForm.action;
                return;
            }
        }

        var link = event.target.closest('[data-grid-ajax-page]');
        if (!link) {
            return;
        }
        var regionId = link.getAttribute('data-grid-region');
        var form = document.querySelector('[data-grid-filters][data-grid-filters-region="' + regionId + '"]');
        if (!form || form.getAttribute('data-grid-filters-ajax') !== '1') {
            return;
        }
        event.preventDefault();
        var url = link.getAttribute('href');
        if (!url) {
            return;
        }
        fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                if (!replaceRegion(html, regionId)) {
                    window.location.href = url;
                    return;
                }
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', url);
                }
                document.dispatchEvent(new CustomEvent('grid-filters:updated', {
                    detail: { regionId: regionId, url: url },
                }));
            })
            .catch(function () {
                window.location.href = url;
            });
    });
})();
