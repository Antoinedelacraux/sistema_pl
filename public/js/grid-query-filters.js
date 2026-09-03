(function () {
    function submitForm(form) {
        var pageInput = form.querySelector('input[name="page"]');
        if (pageInput) {
            pageInput.remove();
        }
        form.submit();
    }

    document.querySelectorAll('[data-grid-filters]').forEach(function (form) {
        var timer = null;

        form.querySelectorAll('select, input[type="date"]').forEach(function (input) {
            input.addEventListener('change', function () {
                submitForm(form);
            });
        });

        var searchInput = form.querySelector('input[type="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    submitForm(form);
                }, 400);
            });
            searchInput.addEventListener('search', function () {
                clearTimeout(timer);
                submitForm(form);
            });
        }

        var clearBtn = form.querySelector('[data-grid-filters-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var url = form.getAttribute('data-clear-url') || form.action;
                window.location.href = url;
            });
        }
    });
})();
