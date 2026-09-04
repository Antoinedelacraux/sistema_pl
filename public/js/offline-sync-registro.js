(function () {
    if (!window.OfflineQueue) {
        return;
    }

    var csrfUrl = window.registroOfflineCsrfUrl || '';
    var syncing = false;

    function toast(msg, type) {
        if (window.TalsaToast) {
            window.TalsaToast.show(msg, type || 'info');
        }
    }

    function isOffline() {
        return !navigator.onLine;
    }

    function isEditForm(form) {
        var method = form.querySelector('[name="_method"]');
        return method && !method.disabled && String(method.value || '').toUpperCase() !== 'POST';
    }

    function habilitarCamposSerializables(form) {
        var restaurar = [];
        form.querySelectorAll('select[name], input[name], textarea[name]').forEach(function (campo) {
            if (!campo.disabled || !campo.name) {
                return;
            }
            if (campo.type === 'file') {
                return;
            }
            var valor = String(campo.value || '').trim();
            if (valor === '' && campo.tagName !== 'SELECT') {
                return;
            }
            campo.disabled = false;
            restaurar.push(campo);
        });
        return function () {
            restaurar.forEach(function (campo) {
                campo.disabled = true;
            });
        };
    }

    function prepararFormulario(form) {
        if (form.id === 'registro-egreso-form' && typeof window.registroPrepararEgresoSubmit === 'function') {
            window.registroPrepararEgresoSubmit();
        }
    }

    function formularioTieneLineasConsumo(form) {
        return !!form.querySelector('input[name^="lineas["][name*="id_material_pl"]');
    }

    function serializeForm(form) {
        prepararFormulario(form);
        var restaurar = habilitarCamposSerializables(form);
        var fd = new FormData(form);
        var entries = [];
        fd.forEach(function (value, key) {
            if (key === 'archivo_pdf' || key === '_method') {
                return;
            }
            if (value instanceof File) {
                return;
            }
            entries.push([key, String(value)]);
        });
        restaurar();
        return entries;
    }

    function entriesToFormData(entries, token) {
        var fd = new FormData();
        entries.forEach(function (pair) {
            if (pair[0] === '_token') {
                return;
            }
            fd.append(pair[0], pair[1]);
        });
        if (token) {
            fd.append('_token', token);
        }
        return fd;
    }

    function formTipo(form) {
        if (form.id === 'registro-ingreso-form') {
            return 'ingreso';
        }
        if (form.id === 'registro-egreso-form') {
            return 'egreso';
        }
        return '';
    }

    function etiquetaTipo(tipo) {
        return tipo === 'egreso' ? 'Consumo' : 'Ingreso';
    }

    function updateBanner(items, offline) {
        var banner = document.getElementById('offline-registro-banner');
        var status = document.getElementById('offline-registro-status');
        var errorsEl = document.getElementById('offline-registro-errors');
        var btn = document.getElementById('offline-registro-sync');
        var clearBtn = document.getElementById('offline-registro-clear');
        if (!banner || !status) {
            return;
        }

        var count = items.length;
        var conError = items.filter(function (item) { return item.status === 'error'; });

        banner.classList.toggle('has-errors', conError.length > 0);

        if (offline) {
            banner.hidden = false;
            banner.classList.add('is-offline');
            status.textContent = count > 0
                ? 'Sin conexión · ' + count + ' pendiente' + (count === 1 ? '' : 's') + ' de sincronizar'
                : 'Sin conexión · puede seguir registrando ingresos y consumos';
        } else if (count > 0) {
            banner.hidden = false;
            banner.classList.remove('is-offline');
            if (conError.length > 0) {
                status.textContent = count + ' en cola · ' + conError.length + ' con error (datos incompletos o rechazados por el servidor)';
            } else {
                status.textContent = count + ' registro' + (count === 1 ? '' : 's') + ' pendiente' + (count === 1 ? '' : 's') + ' de sincronizar';
            }
        } else {
            banner.hidden = true;
            banner.classList.remove('is-offline');
            status.textContent = '';
        }

        if (errorsEl) {
            if (conError.length === 0) {
                errorsEl.hidden = true;
                errorsEl.innerHTML = '';
            } else {
                errorsEl.hidden = false;
                errorsEl.innerHTML = conError.map(function (item, index) {
                    var msg = item.error || 'Error al sincronizar';
                    return '<li>' + etiquetaTipo(item.tipo) + ' #' + (index + 1) + ': ' + msg + '</li>';
                }).join('');
            }
        }

        if (btn) {
            btn.hidden = count === 0 || offline;
        }
        if (clearBtn) {
            clearBtn.hidden = count === 0;
        }
    }

    function refreshBanner() {
        return OfflineQueue.pending().then(function (items) {
            updateBanner(items, isOffline());
            return items;
        });
    }

    function fetchCsrf() {
        if (!csrfUrl) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return Promise.resolve(meta ? meta.getAttribute('content') : '');
        }
        return fetch(csrfUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var token = data.token || '';
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta && token) {
                    meta.setAttribute('content', token);
                }
                return token;
            });
    }

    function syncItem(item, token) {
        item.status = 'syncing';
        return OfflineQueue.update(item).then(function () {
            return fetch(item.url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: entriesToFormData(item.entries, token),
                credentials: 'same-origin',
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }).catch(function () {
                    return { ok: false, data: { message: 'Respuesta inválida del servidor.' } };
                });
            });
        });
    }

    function syncAll() {
        if (syncing || isOffline()) {
            return Promise.resolve();
        }
        syncing = true;

        return fetchCsrf().then(function (token) {
            return OfflineQueue.pending().then(function (items) {
                var chain = Promise.resolve();
                var ok = 0;
                var fail = 0;

                items.forEach(function (item) {
                    chain = chain.then(function () {
                        return syncItem(item, token).then(function (result) {
                            if (result.ok && result.data && result.data.ok) {
                                ok++;
                                return OfflineQueue.remove(item.id);
                            }
                            var msg = (result.data && result.data.message) || '';
                            if (!msg && result.data && result.data.errors) {
                                var vals = Object.values(result.data.errors)[0];
                                msg = Array.isArray(vals) ? vals[0] : String(vals || '');
                            }
                            item.status = 'error';
                            item.error = msg || 'Error al sincronizar';
                            fail++;
                            return OfflineQueue.update(item);
                        }).catch(function () {
                            item.status = 'error';
                            item.error = 'Error de red al sincronizar';
                            fail++;
                            return OfflineQueue.update(item);
                        });
                    });
                });

                return chain.then(function () {
                    if (ok > 0) {
                        toast(ok + ' registro' + (ok === 1 ? '' : 's') + ' sincronizado' + (ok === 1 ? '' : 's') + '. PDF opcional: edítelo después si falta.', 'ok');
                    }
                    if (fail > 0) {
                        var detalle = items.filter(function (item) { return item.status === 'error' && item.error; })
                            .map(function (item) { return etiquetaTipo(item.tipo) + ': ' + item.error; })[0];
                        toast(
                            fail + ' registro' + (fail === 1 ? '' : 's') + ' no se pudo sincronizar.'
                                + (detalle ? ' ' + detalle : ' Use Descartar cola y créelos de nuevo.'),
                            'error'
                        );
                    }
                    if (ok > 0) {
                        window.location.reload();
                    }
                });
            });
        }).finally(function () {
            syncing = false;
            refreshBanner();
        });
    }

    function queueForm(form) {
        var tipo = formTipo(form);
        var url = form.getAttribute('data-store-action') || form.getAttribute('action');
        if (!tipo || !url) {
            return Promise.reject();
        }

        return OfflineQueue.add({
            tipo: tipo,
            url: url,
            entries: serializeForm(form),
        }).then(function () {
            toast('Guardado localmente. Se sincronizará al volver internet. PDF puede cargarse después.', 'info');
            document.dispatchEvent(new CustomEvent('registro-offline-queued', { detail: { tipo: tipo } }));
            return refreshBanner();
        });
    }

    function bindForms() {
        ['registro-ingreso-form', 'registro-egreso-form'].forEach(function (id) {
            var form = document.getElementById(id);
            if (!form) {
                return;
            }
            form.addEventListener('submit', function (event) {
                if (!isOffline()) {
                    return;
                }
                if (isEditForm(form)) {
                    event.preventDefault();
                    toast('Sin conexión: solo puede crear registros nuevos offline.', 'error');
                    return;
                }
                if (id === 'registro-ingreso-form') {
                    var hidden = document.getElementById('registro-ingreso-lineas-hidden');
                    if (hidden && hidden.innerHTML.trim() === '') {
                        return;
                    }
                }
                if (id === 'registro-egreso-form') {
                    prepararFormulario(form);
                    if (!formularioTieneLineasConsumo(form)) {
                        event.preventDefault();
                        toast('Sin conexión: cargue el producto y sus materiales antes de guardar el consumo.', 'error');
                        return;
                    }
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                queueForm(form).catch(function () {
                    toast('No se pudo guardar localmente.', 'error');
                });
            }, true);
        });
    }

    function bindUi() {
        var btn = document.getElementById('offline-registro-sync');
        if (btn) {
            btn.addEventListener('click', function () {
                syncAll();
            });
        }
        var clearBtn = document.getElementById('offline-registro-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var confirmar = function () {
                    return OfflineQueue.clearAll().then(function () {
                        toast('Cola descartada.', 'info');
                        refreshBanner();
                    });
                };

                if (!window.TalsaConfirm) {
                    if (window.confirm('Se eliminarán los registros pendientes de la cola local. ¿Continuar?')) {
                        confirmar();
                    }
                    return;
                }

                window.TalsaConfirm.show({
                    title: 'Descartar cola offline',
                    message: 'Se eliminarán los registros pendientes guardados en este navegador. Tendrá que crearlos de nuevo en el sistema.',
                    confirmLabel: 'Descartar',
                    variant: 'danger',
                }).then(function (confirmed) {
                    if (confirmed) {
                        confirmar();
                    }
                });
            });
        }
        window.addEventListener('online', function () {
            refreshBanner().then(syncAll);
        });
        window.addEventListener('offline', refreshBanner);
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindForms();
        bindUi();
        refreshBanner();
        if (navigator.onLine) {
            syncAll();
        }
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    }
})();
