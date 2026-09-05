(function () {
    var programa = document.getElementById('inicio-filtro-programa');
    var producto = document.getElementById('inicio-filtro-producto');

    if (!programa || !producto) {
        return;
    }

    programa.addEventListener('change', function () {
        producto.value = '';
    });
})();
