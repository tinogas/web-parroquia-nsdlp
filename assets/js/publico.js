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
})();
