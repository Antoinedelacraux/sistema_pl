(function () {
    var modal = document.getElementById('receta-modal');
    var abrir = document.getElementById('receta-abrir-modal');
    var codigoInput = document.getElementById('receta-codigo-pl');
    var materialInput = document.getElementById('receta-nom-material');
    var imagenInput = document.getElementById('receta-imagen');
    var imagenPreview = document.getElementById('receta-imagen-preview');
    var imagenPreviewImg = document.getElementById('receta-imagen-preview-img');
    var imagenModal = document.getElementById('receta-imagen-modal');
    var imagenForm = document.getElementById('receta-imagen-form');
    var imagenMeta = document.getElementById('receta-imagen-meta');
    var imagenActualWrap = document.getElementById('receta-imagen-actual-wrap');
    var imagenActual = document.getElementById('receta-imagen-actual');
    var imagenNuevaInput = document.getElementById('receta-imagen-nueva');
    var imagenNuevaPreview = document.getElementById('receta-imagen-nueva-preview');
    var imagenNuevaPreviewImg = document.getElementById('receta-imagen-nueva-preview-img');
    var imagenQuitarWrap = document.getElementById('receta-imagen-quitar-wrap');
    var imagenQuitar = document.getElementById('receta-imagen-quitar');
    var lightbox = document.getElementById('receta-lightbox');
    var lightboxImg = lightbox ? lightbox.querySelector('.receta-lightbox__img') : null;
    var lookupUrl = window.recetaMaterialUrl || '';
    var storeUrl = window.recetaStoreUrl || '';
    var form = document.getElementById('receta-form');
    var formMethod = document.getElementById('receta-form-method');
    var modalTitle = document.getElementById('receta-modal-title');
    var formSubmit = document.getElementById('receta-form-submit');
    var lookupTimer = null;
    var previewUrl = null;
    var previewNuevaUrl = null;

    var MAX_ANCHO_IMAGEN = 1280;
    var CALIDAD_WEBP = 0.82;

    function comprimirImagen(archivo) {
        return new Promise(function (resolve) {
            if (!archivo || !archivo.type || archivo.type.indexOf('image/') !== 0) {
                resolve(archivo);
                return;
            }

            if (typeof HTMLCanvasElement === 'undefined' || !HTMLCanvasElement.prototype.toBlob) {
                resolve(archivo);
                return;
            }

            var reader = new FileReader();

            reader.onload = function (event) {
                var img = new Image();

                img.onload = function () {
                    var ancho = img.width;
                    var alto = img.height;
                    var nuevoAncho = ancho;
                    var nuevoAlto = alto;

                    if (ancho > MAX_ANCHO_IMAGEN) {
                        nuevoAncho = MAX_ANCHO_IMAGEN;
                        nuevoAlto = Math.max(1, Math.round(alto * (MAX_ANCHO_IMAGEN / ancho)));
                    }

                    var canvas = document.createElement('canvas');
                    canvas.width = nuevoAncho;
                    canvas.height = nuevoAlto;

                    var ctx = canvas.getContext('2d');

                    if (!ctx) {
                        resolve(archivo);
                        return;
                    }

                    ctx.drawImage(img, 0, 0, nuevoAncho, nuevoAlto);

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            resolve(archivo);
                            return;
                        }

                        var base = (archivo.name || 'imagen').replace(/\.[^.]+$/, '');
                        resolve(new File([blob], base + '.webp', {
                            type: 'image/webp',
                            lastModified: Date.now(),
                        }));
                    }, 'image/webp', CALIDAD_WEBP);
                };

                img.onerror = function () {
                    resolve(archivo);
                };

                img.src = event.target.result;
            };

            reader.onerror = function () {
                resolve(archivo);
            };

            reader.readAsDataURL(archivo);
        });
    }

    function reemplazarArchivoInput(input, archivo) {
        var transfer = new DataTransfer();
        transfer.items.add(archivo);
        input.files = transfer.files;
    }

    function enlazarCompresionAlEnviar(formulario) {
        if (!formulario) return;

        var enviandoComprimido = false;

        formulario.addEventListener('submit', function (event) {
            if (enviandoComprimido) {
                return;
            }

            var input = formulario.querySelector('input[type="file"][name="imagen"]');

            if (!input || !input.files || !input.files[0]) {
                return;
            }

            event.preventDefault();
            enviandoComprimido = true;

            var boton = formulario.querySelector('[type="submit"]');
            if (boton) {
                boton.disabled = true;
            }

            comprimirImagen(input.files[0])
                .then(function (archivoComprimido) {
                    reemplazarArchivoInput(input, archivoComprimido);
                    formulario.submit();
                })
                .catch(function () {
                    enviandoComprimido = false;
                    if (boton) {
                        boton.disabled = false;
                    }
                    formulario.submit();
                });
        });
    }

    enlazarCompresionAlEnviar(document.getElementById('receta-form'));
    enlazarCompresionAlEnviar(imagenForm);

    function setSelectValue(select, value) {
        if (!select) return;
        select.value = value || '';
    }

    function resetModalCreate() {
        if (!form) return;

        form.action = storeUrl || form.getAttribute('action') || '';
        if (formMethod) {
            formMethod.disabled = true;
        }
        if (modalTitle) {
            modalTitle.textContent = 'Agregar a receta';
        }
        if (formSubmit) {
            formSubmit.textContent = 'Guardar';
        }
        if (imagenInput) {
            imagenInput.value = '';
        }
        limpiarPreview();
    }

    function abrirModal() {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        resetModalCreate();
    }

    function abrirModalEditar(button) {
        if (!form || !button) return;

        resetModalCreate();

        form.action = button.getAttribute('data-action') || '';
        if (formMethod) {
            formMethod.disabled = false;
        }
        if (modalTitle) {
            modalTitle.textContent = 'Editar receta';
        }
        if (formSubmit) {
            formSubmit.textContent = 'Guardar cambios';
        }

        setSelectValue(form.querySelector('[name="id_mercado"]'), button.getAttribute('data-id-mercado'));
        setSelectValue(form.querySelector('[name="id_cliente"]'), button.getAttribute('data-id-cliente'));
        setSelectValue(form.querySelector('[name="id_programa"]'), button.getAttribute('data-id-programa'));
        setSelectValue(form.querySelector('[name="id_presentacion"]'), button.getAttribute('data-id-presentacion'));
        setSelectValue(form.querySelector('[name="id_variedad"]'), button.getAttribute('data-id-variedad'));
        setSelectValue(form.querySelector('[name="id_familia"]'), button.getAttribute('data-id-familia'));
        setSelectValue(form.querySelector('[name="id_uso"]'), button.getAttribute('data-id-uso'));
        setSelectValue(form.querySelector('[name="id_situado"]'), button.getAttribute('data-id-situado'));

        if (codigoInput) {
            codigoInput.value = button.getAttribute('data-codigo-pl') || '';
        }

        if (materialInput) {
            materialInput.value = button.getAttribute('data-nom-material') || '';
            materialInput.readOnly = true;
        }

        var cantidadInput = form.querySelector('[name="cantidad"]');
        if (cantidadInput) {
            cantidadInput.value = button.getAttribute('data-cantidad') || '';
        }

        abrirModal();
    }

    function limpiarPreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        if (imagenPreview) {
            imagenPreview.hidden = true;
        }

        if (imagenPreviewImg) {
            imagenPreviewImg.removeAttribute('src');
        }
    }

    function limpiarPreviewNueva() {
        if (previewNuevaUrl) {
            URL.revokeObjectURL(previewNuevaUrl);
            previewNuevaUrl = null;
        }

        if (imagenNuevaPreview) {
            imagenNuevaPreview.hidden = true;
        }

        if (imagenNuevaPreviewImg) {
            imagenNuevaPreviewImg.removeAttribute('src');
        }
    }

    function resetImagenModal() {
        limpiarPreviewNueva();

        if (imagenNuevaInput) {
            imagenNuevaInput.value = '';
        }

        if (imagenQuitar) {
            imagenQuitar.checked = false;
        }
    }

    function abrirImagenModal(button) {
        if (!imagenModal || !imagenForm || !button) return;

        resetImagenModal();

        var nroFila = button.getAttribute('data-nro-fila') || '';
        var material = button.getAttribute('data-material') || '';
        var urlActual = button.getAttribute('data-imagen-url') || '';
        var action = button.getAttribute('data-action') || '';

        imagenForm.action = action;

        if (imagenMeta) {
            imagenMeta.textContent = 'Fila #' + nroFila + (material ? ' · ' + material : '');
        }

        if (imagenActualWrap && imagenActual) {
            if (urlActual) {
                imagenActual.src = urlActual;
                imagenActualWrap.hidden = false;
            } else {
                imagenActual.removeAttribute('src');
                imagenActualWrap.hidden = true;
            }
        }

        if (imagenQuitarWrap) {
            imagenQuitarWrap.hidden = !urlActual;
        }

        imagenModal.classList.add('is-open');
        imagenModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarImagenModal() {
        if (!imagenModal) return;

        imagenModal.classList.remove('is-open');
        imagenModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        resetImagenModal();
    }

    function abrirLightbox(url) {
        if (!lightbox || !lightboxImg || !url) return;

        lightboxImg.src = url;
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('receta-lightbox-open');
    }

    function cerrarLightbox() {
        if (!lightbox || !lightboxImg) return;

        lightbox.hidden = true;
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxImg.removeAttribute('src');
        document.body.classList.remove('receta-lightbox-open');
    }

    if (abrir) {
        abrir.addEventListener('click', function () {
            resetModalCreate();
            abrirModal();
        });
    }

    document.querySelectorAll('[data-receta-editar]').forEach(function (node) {
        node.addEventListener('click', function () {
            abrirModalEditar(node);
        });
    });

    document.querySelectorAll('[data-cerrar-modal]').forEach(function (node) {
        node.addEventListener('click', cerrarModal);
    });

    document.querySelectorAll('[data-cerrar-imagen-modal]').forEach(function (node) {
        node.addEventListener('click', cerrarImagenModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (lightbox && !lightbox.hidden) {
                cerrarLightbox();
                return;
            }

            if (imagenModal && imagenModal.classList.contains('is-open')) {
                cerrarImagenModal();
                return;
            }

            cerrarModal();
        }
    });

    if (modal && modal.querySelector('.modal__errors')) {
        var editarFilaId = window.recetaEditarFilaId;
        var botonEditarFila = editarFilaId
            ? document.querySelector('[data-receta-editar][data-id="' + editarFilaId + '"]')
            : null;

        if (botonEditarFila && form) {
            form.action = botonEditarFila.getAttribute('data-action') || '';
            if (formMethod) {
                formMethod.disabled = false;
            }
            if (modalTitle) {
                modalTitle.textContent = 'Editar receta';
            }
            if (formSubmit) {
                formSubmit.textContent = 'Guardar cambios';
            }
            abrirModal();
        } else {
            resetModalCreate();
            abrirModal();
        }
    }

    if (imagenModal && imagenModal.querySelector('.modal__errors')) {
        var editarId = window.recetaEditarImagenId;
        var botonEditar = editarId
            ? document.querySelector('[data-editar-imagen="' + editarId + '"]')
            : null;

        if (botonEditar) {
            abrirImagenModal(botonEditar);
        } else {
            imagenModal.classList.add('is-open');
            imagenModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }
    }

    function buscarMaterial() {
        if (!codigoInput || !materialInput || !lookupUrl) return;

        var codigo = codigoInput.value.trim();
        if (codigo.length < 4) return;

        fetch(lookupUrl + '?codigo=' + encodeURIComponent(codigo), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.encontrado) {
                    materialInput.value = data.nom_material || '';
                    materialInput.readOnly = true;
                } else {
                    materialInput.readOnly = false;
                }
            })
            .catch(function () {});
    }

    if (codigoInput) {
        codigoInput.addEventListener('input', function () {
            if (materialInput) {
                materialInput.readOnly = false;
            }
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(buscarMaterial, 350);
        });

        codigoInput.addEventListener('blur', buscarMaterial);
    }

    if (imagenInput) {
        imagenInput.addEventListener('change', function () {
            limpiarPreview();

            var archivo = imagenInput.files && imagenInput.files[0];
            if (!archivo || !imagenPreview || !imagenPreviewImg) return;

            previewUrl = URL.createObjectURL(archivo);
            imagenPreviewImg.src = previewUrl;
            imagenPreview.hidden = false;
        });
    }

    if (imagenNuevaInput) {
        imagenNuevaInput.addEventListener('change', function () {
            limpiarPreviewNueva();

            var archivo = imagenNuevaInput.files && imagenNuevaInput.files[0];
            if (!archivo || !imagenNuevaPreview || !imagenNuevaPreviewImg) return;

            if (imagenQuitar) {
                imagenQuitar.checked = false;
            }

            previewNuevaUrl = URL.createObjectURL(archivo);
            imagenNuevaPreviewImg.src = previewNuevaUrl;
            imagenNuevaPreview.hidden = false;
        });
    }

    document.querySelectorAll('[data-receta-imagen]').forEach(function (node) {
        node.addEventListener('click', function () {
            abrirLightbox(node.getAttribute('data-receta-imagen'));
        });
    });

    document.querySelectorAll('[data-editar-imagen]').forEach(function (node) {
        node.addEventListener('click', function (event) {
            event.stopPropagation();
            abrirImagenModal(node);
        });
    });

    document.querySelectorAll('[data-receta-lightbox-close]').forEach(function (node) {
        node.addEventListener('click', cerrarLightbox);
    });
})();
