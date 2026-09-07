(function () {
    var form = document.querySelector('.resumen-filters[data-grid-filters]');
    var programa = document.getElementById('inicio-filtro-programa');
    var producto = document.getElementById('inicio-filtro-producto');
    var cajas = document.getElementById('inicio-filtro-cajas');

    if (!programa || !producto) {
        return;
    }

    function submitFiltros() {
        if (!form) {
            return;
        }

        var pageInput = form.querySelector('input[name="page"]');
        if (pageInput) {
            pageInput.remove();
        }
        form.submit();
    }

    programa.addEventListener('change', function () {
        producto.value = '';
        if (cajas) {
            cajas.value = '';
        }
    });

    if (cajas && form) {
        var timerCajas = null;
        cajas.addEventListener('change', submitFiltros);
        cajas.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitFiltros();
            }
        });
        cajas.addEventListener('input', function () {
            clearTimeout(timerCajas);
            timerCajas = setTimeout(submitFiltros, 600);
        });
    }
})();
