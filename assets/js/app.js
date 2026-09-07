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

    // ---- Buscar en el modal de "Usar como…" ----
    // data-texto ya trae nombre + correo en minúsculas, listo para comparar.
    var buscarUsarComo = document.getElementById('buscarUsarComo');
    if (buscarUsarComo) {
        var filasUsarComo = document.querySelectorAll('.fila-usar-como');
        buscarUsarComo.addEventListener('input', function () {
            var texto = buscarUsarComo.value.trim().toLowerCase();
            filasUsarComo.forEach(function (fila) {
                var coincide = texto === '' || fila.dataset.texto.indexOf(texto) !== -1;
                fila.classList.toggle('d-none', !coincide);
            });
        });
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

    // ---- Un mensaje de WhatsApp para todo el listado ----
    // Los enlaces los arma boton_whatsapp() en core/helpers.php, ya con el
    // número normalizado. Esto solo les pega el texto que se escribe una vez
    // arriba: con wa.me cada conversación se abre por separado, así que sin
    // esto habría que teclear el mismo aviso quince veces.
    //
    // Se activa con un <textarea id="whatsappMensaje" data-lista="clave">
    // en la misma página que los enlaces .js-whatsapp. Inerte donde no lo hay.
    var cuadro  = document.getElementById('whatsappMensaje');
    var enlaces = document.querySelectorAll('a.js-whatsapp');

    if (cuadro && enlaces.length) {
        var clave = 'wa:' + (cuadro.dataset.lista || location.pathname);

        // El borrador sobrevive a abrir un chat y volver, que es lo que se hace
        // quince veces seguidas. sessionStorage y no localStorage: el aviso de
        // hoy no tiene por qué seguir ahí la semana que entra.
        var recordar = function (valor) {
            try { sessionStorage.setItem(clave, valor); } catch (e) { /* modo privado */ }
        };
        try { cuadro.value = sessionStorage.getItem(clave) || cuadro.value; } catch (e) { /* idem */ }

        var pintar = function () {
            var texto = cuadro.value;
            enlaces.forEach(function (enlace) {
                if (!enlace.dataset.base) {
                    enlace.dataset.base = enlace.href.split('?')[0];
                }
                // El nombre de pila, que es como se le habla a alguien.
                var pila = (enlace.dataset.nombre || '').trim().split(/\s+/)[0] || '';
                var suyo = texto.replace(/\{nombre\}/g, pila).trim();
                enlace.href = enlace.dataset.base
                            + (suyo === '' ? '' : '?text=' + encodeURIComponent(suyo));
            });
        };

        cuadro.addEventListener('input', function () {
            recordar(cuadro.value);
            pintar();
        });
        pintar();

        // A quién ya se le escribió, para no perder la cuenta a media lista.
        enlaces.forEach(function (enlace) {
            enlace.addEventListener('click', function () {
                enlace.classList.remove('btn-outline-success');
                enlace.classList.add('btn-success');
                enlace.title = 'Ya se abrió esta conversación. ' + enlace.title;
            });
        });
    }
})();
