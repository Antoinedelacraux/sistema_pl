(function () {
    var modal = document.getElementById('receta-param-modal');
    if (!modal) {
        return;
    }

    function abrirModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function cerrarModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-receta-param-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('receta-param-programa').value = btn.getAttribute('data-id-programa') || '';
            document.getElementById('receta-param-presentacion').value = btn.getAttribute('data-id-presentacion') || '';
            document.getElementById('receta-param-programa-nom').textContent = btn.getAttribute('data-nom-programa') || '—';
            document.getElementById('receta-param-presentacion-nom').textContent = btn.getAttribute('data-nom-presentacion') || '—';
            document.getElementById('receta-param-conformacion').value = btn.getAttribute('data-conformacion') || '';
            document.getElementById('receta-param-pallets').value = btn.getAttribute('data-pallets') || '';
            abrirModal();
        });
    });

    modal.querySelectorAll('[data-cerrar-receta-param]').forEach(function (node) {
        node.addEventListener('click', cerrarModal);
    });

    if (modal.querySelector('.modal__errors')) {
        abrirModal();
    }

    if (window.recetaParamOld) {
        var old = window.recetaParamOld;
        document.getElementById('receta-param-programa').value = old.id_programa || '';
        document.getElementById('receta-param-presentacion').value = old.id_presentacion || '';
        document.getElementById('receta-param-conformacion').value = old.conformacion ?? '';
        document.getElementById('receta-param-pallets').value = old.pallets ?? '';
        abrirModal();
    }
})();
