/* ============================================================
   Sitio público — JavaScript sin dependencias
   El sitio debe funcionar completo sin JavaScript; lo de aquí solo mejora
   la experiencia.
   ============================================================ */

(function () {
    'use strict';

    // Al tocar un enlace del menú en móvil, plegarlo.
    var menu = document.getElementById('menuSitio');
    if (menu) {
        menu.querySelectorAll('a').forEach(function (enlace) {
            enlace.addEventListener('click', function () {
                if (menu.classList.contains('show')) {
                    var boton = document.querySelector('[data-bs-target="#menuSitio"]');
                    if (boton) { boton.click(); }
                }
            });
        });
    }

    // Inscripción a cursos: la sección "Padre, madre o tutor" arranca
    // deshabilitada; la casilla la habilita. Sin JavaScript los campos se
    // quedan como en el HTML (habilitados), así que el formulario sigue
    // funcionando completo.
    var casillaTutor = document.querySelector('[data-tutor-activar]');
    if (casillaTutor) {
        var camposTutor = document.querySelectorAll('[data-tutor-campo]');
        var actualizarCamposTutor = function () {
            camposTutor.forEach(function (campo) {
                campo.disabled = !casillaTutor.checked;
            });
        };
        casillaTutor.addEventListener('change', actualizarCamposTutor);
        // Si el formulario se volvió a mostrar tras un error con estos campos
        // ya escritos, se dejan visibles y editables en vez de esconderlos.
        var tutorConDatos = Array.prototype.some.call(camposTutor, function (campo) {
            return campo.value.trim() !== '';
        });
        if (tutorConDatos) {
            casillaTutor.checked = true;
        }
        actualizarCamposTutor();
    }
})();
