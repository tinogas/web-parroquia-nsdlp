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

    // La barra lleva sombra solo cuando ya hay contenido debajo de ella.
    var barra = document.querySelector('.barra-sitio');
    if (barra) {
        var alDesplazar = function () {
            barra.classList.toggle('con-sombra', window.scrollY > 8);
        };
        alDesplazar();
        window.addEventListener('scroll', alDesplazar, { passive: true });
    }

    // Horarios de la portada: las fichas de sede filtran los renglones de los
    // siete días sin recargar. Cada tarjeta recalcula su cuenta y, si se queda
    // vacía, dice "Sin misas este día en esta sede". Sin JavaScript las fichas
    // ni se dibujan (.solo-con-js) y se ve todo.
    document.querySelectorAll('[data-filtro-sedes]').forEach(function (fichas) {
        var seccion = fichas.closest('[data-horarios]');
        if (!seccion) { return; }

        var filtrar = function (elegida) {
            var sede = elegida.getAttribute('data-sede');

            fichas.querySelectorAll('[data-sede]').forEach(function (ficha) {
                var activa = ficha === elegida;
                ficha.classList.toggle('activa', activa);
                ficha.setAttribute('aria-pressed', activa ? 'true' : 'false');
            });

            seccion.querySelectorAll('.grupo-horario').forEach(function (tarjeta) {
                var visibles = [];
                tarjeta.querySelectorAll('.renglon-horario').forEach(function (renglon) {
                    var entra = sede === 'todas' || renglon.getAttribute('data-sede') === sede;
                    renglon.hidden = !entra;
                    renglon.classList.remove('ultimo-visible');
                    if (entra) { visibles.push(renglon); }
                });
                if (visibles.length) { visibles[visibles.length - 1].classList.add('ultimo-visible'); }

                var cuenta = tarjeta.querySelector('.cuenta');
                if (cuenta) { cuenta.textContent = visibles.length || ''; }

                var vacio = tarjeta.querySelector('.grupo-horario-vacio');
                if (vacio) {
                    vacio.hidden = visibles.length > 0;
                    vacio.textContent = 'Sin ' + tarjeta.getAttribute('data-nombre') + ' este día'
                        + (sede === 'todas' ? '' : ' en esta sede') + '.';
                }
            });
        };

        fichas.addEventListener('click', function (evento) {
            var elegida = evento.target.closest('[data-sede]');
            if (elegida) { filtrar(elegida); }
        });

        // La página de Horarios puede abrir con una sede ya elegida (?centro=).
        var inicial = fichas.querySelector('.ficha-sede.activa');
        if (inicial && inicial.getAttribute('data-sede') !== 'todas') { filtrar(inicial); }
    });

    // Evangelio y reflexión de la portada: plegados enseñan el principio y
    // "Leer completo" los abre. Si el texto cabe entero, se quita el pliegue
    // y el botón ni aparece.
    document.querySelectorAll('[data-plegable]').forEach(function (lectura) {
        var texto = lectura.querySelector('.lectura-texto');
        var boton = lectura.querySelector('.lectura-boton');
        if (!texto || !boton) { return; }

        if (texto.scrollHeight <= texto.clientHeight + 24) {
            lectura.classList.remove('plegada');
            return;
        }

        boton.hidden = false;
        boton.addEventListener('click', function () {
            var abierta = !lectura.classList.toggle('plegada');
            boton.setAttribute('aria-expanded', abierta ? 'true' : 'false');
            boton.querySelector('span').textContent = abierta ? 'Mostrar menos' : 'Leer completo';
            if (!abierta) { lectura.scrollIntoView({ block: 'nearest' }); }
        });
    });
})();
