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

    function atarSimulacion(input) {
        if (!input || !form) {
            return;
        }

        var timer = null;
        input.addEventListener('change', submitFiltros);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitFiltros();
            }
        });
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(submitFiltros, 600);
        });
    }

    programa.addEventListener('change', function () {
        producto.value = '';
        if (cajas) {
            cajas.value = '';
        }
    });

    atarSimulacion(cajas);
})();
