/* ============================================================
   Panel de administración — JavaScript sin dependencias
   ============================================================ */

(function () {
    'use strict';

    // ---- Mostrar u ocultar la barra lateral ----
    var boton    = document.getElementById('sidebarToggle');
    var sidebar  = document.getElementById('sidebar');
    var contenido = document.getElementById('main-content');

    if (boton && sidebar && contenido) {
        var escritorio = function () { return window.innerWidth >= 992; };

        boton.addEventListener('click', function () {
            if (escritorio()) {
                sidebar.classList.toggle('oculto');
                contenido.classList.toggle('ancho');
            } else {
                sidebar.classList.toggle('visible');
            }
        });

        // En móvil, tocar fuera del menú lo cierra.
        document.addEventListener('click', function (evento) {
            if (escritorio() || !sidebar.classList.contains('visible')) {
                return;
            }
            if (!sidebar.contains(evento.target) && !boton.contains(evento.target)) {
                sidebar.classList.remove('visible');
            }
        });
    }

    // ---- Abrir la pestaña indicada en la dirección ----
    // Tras guardar, el controlador redirige a …/configuracion#contacto para
    // devolver al usuario a la sección donde estaba.
    if (window.location.hash) {
        var destino = document.querySelector('[data-bs-target="#panel-' +
            window.location.hash.substring(1).replace(/[^a-z0-9_-]/gi, '') + '"]');
        if (destino && window.bootstrap) {
            window.bootstrap.Tab.getOrCreateInstance(destino).show();
        }
    }

    // ---- Vista previa de la imagen antes de subirla ----
    // Se activa con: <input type="file" data-preview="idDeLaImagen">
    document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var destino = document.getElementById(input.dataset.preview);
            if (destino && input.files && input.files[0]) {
                destino.src = URL.createObjectURL(input.files[0]);
            }
        });
    });
})();
