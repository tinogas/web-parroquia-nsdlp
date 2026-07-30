/* ============================================================
   Calendario de eventos — mejora progresiva, sin dependencias

   El servidor ya entrega el periodo solicitado completamente renderizado, y
   todos los enlaces del calendario —cambiar de periodo, cambiar de vista, abrir
   un día— son URLs normales que funcionan sin JavaScript recargando la página.
   Este script solo intercepta esos clics para traer el mismo bloque por fetch y
   sustituirlo sin recargar.

   Pide el HTML ya armado (?accion=fragmento) en vez de reconstruirlo aquí: son
   cuatro cuadrículas distintas (día, semana, mes, año) y duplicar en JavaScript
   lo que PHP ya sabe hacer garantizaría que las dos versiones se separaran.
   Para consumir los eventos como datos está ?accion=datos, que devuelve JSON.

   Si el fetch falla por lo que sea, se cae al enlace normal: nunca deja al
   visitante sin poder moverse por el calendario.
   ============================================================ */

(function () {
    'use strict';

    var contenedor = document.querySelector('[data-calendario-contenedor]');
    if (!contenedor || !window.fetch) {
        return;
    }

    // Los enlaces que maneja este script. El resto —un evento concreto— navega
    // como siempre.
    var SELECTOR = '[data-calendario-nav], [data-calendario-vista], [data-calendario-ir]';

    function cargar(url) {
        var destino = new URL(url, window.location.href);
        var interna = new URL(destino.toString());
        interna.searchParams.set('area', 'publico');
        interna.searchParams.set('modulo', 'eventos');
        interna.searchParams.set('accion', 'fragmento');

        contenedor.setAttribute('aria-busy', 'true');
        fetch(interna.toString(), { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (respuesta) {
                return respuesta.ok ? respuesta.text() : Promise.reject(new Error('respuesta no válida'));
            })
            .then(function (html) {
                contenedor.innerHTML = html;
                contenedor.removeAttribute('aria-busy');
                if (window.history && window.history.pushState) {
                    window.history.pushState({ calendario: true }, '', destino.toString());
                }
            })
            .catch(function () {
                window.location.href = destino.toString();
            });
    }

    contenedor.addEventListener('click', function (evento) {
        if (evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.button !== 0) {
            return;   // Clic con modificador: que el navegador haga lo suyo.
        }
        var enlace = evento.target.closest(SELECTOR);
        if (!enlace || !enlace.href) {
            return;
        }
        evento.preventDefault();
        cargar(enlace.href);
    });

    // Atrás y adelante del navegador: las URLs son de verdad, así que basta con
    // volver a pedir el fragmento que toque.
    window.addEventListener('popstate', function (evento) {
        if (evento.state && evento.state.calendario) {
            cargar(window.location.href);
        }
    });
})();
