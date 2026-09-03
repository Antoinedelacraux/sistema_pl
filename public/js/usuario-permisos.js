(function () {
    function init() {
        var rolSelect = document.getElementById('id_rol');
        var jsonNode = document.getElementById('permisos-por-rol');
        var marcarTodos = document.getElementById('permisos-marcar-todos');
        if (!rolSelect || !jsonNode) return;

        var porRol = {};
        try {
            porRol = JSON.parse(jsonNode.textContent || '{}');
        } catch (e) {
            porRol = {};
        }

        function inputs() {
            return document.querySelectorAll('.perm-check__input');
        }

        function aplicarMatriz(matriz) {
            inputs().forEach(function (input) {
                var modulo = input.getAttribute('data-modulo');
                var accion = input.getAttribute('data-accion');
                var fila = matriz && matriz[modulo] ? matriz[modulo] : {};
                input.checked = !!(fila && fila[accion]);
            });
        }

        function matrizCompleta() {
            var matriz = {};
            inputs().forEach(function (input) {
                var modulo = input.getAttribute('data-modulo');
                var accion = input.getAttribute('data-accion');
                if (!matriz[modulo]) matriz[modulo] = {};
                matriz[modulo][accion] = true;
            });
            return matriz;
        }

        function hayMarcados() {
            return !!document.querySelector('.perm-check__input:checked');
        }

        rolSelect.addEventListener('change', function () {
            var id = rolSelect.value;
            if (!id || !porRol[id]) return;
            aplicarMatriz(porRol[id]);
        });

        if (marcarTodos) {
            marcarTodos.addEventListener('click', function () {
                aplicarMatriz(matrizCompleta());
            });
        }

        if (rolSelect.value && porRol[rolSelect.value] && !hayMarcados()) {
            aplicarMatriz(porRol[rolSelect.value]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
