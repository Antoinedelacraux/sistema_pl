(function () {
    var form = document.querySelector('[data-grid-filters].receta-command__form, [data-grid-filters][action*="presentacion-params"]');
    if (!form) {
        return;
    }

    var mercado = document.getElementById('receta-filtro-mercado');
    var cliente = document.getElementById('receta-filtro-cliente');
    var programaGeneral = document.getElementById('receta-filtro-programa-general');
    var programa = document.getElementById('receta-filtro-programa');
    var presentacion = document.getElementById('receta-filtro-presentacion');
    var familia = document.getElementById('receta-filtro-familia');

    var cascada = [];

    if (mercado) {
        cascada.push({ el: mercado, reset: [cliente, programaGeneral, programa, presentacion, familia] });
    }

    if (cliente) {
        cascada.push({ el: cliente, reset: [programaGeneral, programa, presentacion, familia] });
    }

    if (programaGeneral) {
        cascada.push({ el: programaGeneral, reset: [programa, presentacion, familia] });
    }

    if (programa) {
        cascada.push({ el: programa, reset: [presentacion, familia] });
    }

    if (presentacion && familia) {
        cascada.push({ el: presentacion, reset: [familia] });
    } else if (presentacion && !familia) {
        cascada.push({ el: presentacion, reset: [] });
    }

    cascada.forEach(function (item) {
        item.el.addEventListener('change', function () {
            item.reset.forEach(function (child) {
                if (child) {
                    child.value = '';
                }
            });
        });
    });
})();
