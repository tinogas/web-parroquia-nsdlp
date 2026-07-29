/* ============================================================
   Calendario de eventos — mejora progresiva, sin dependencias

   El servidor ya entrega el mes solicitado completamente renderizado: los
   enlaces de mes anterior/siguiente son URLs normales que funcionan sin
   JavaScript, recargando la página. Este script solo intercepta esos clics
   para traer el mes por fetch y reconstruir la tabla sin recargar, contra el
   mismo endpoint JSON (?accion=datos) que serviría a cualquier otro cliente.

   Si el fetch falla por lo que sea, se cae al enlace normal: nunca deja al
   visitante sin poder cambiar de mes.
   ============================================================ */

(function () {
    'use strict';

    var contenedor = document.getElementById('calendario');
    if (!contenedor) {
        return;
    }

    var cuerpo = contenedor.querySelector('[data-calendario-cuerpo]');
    var titulo = contenedor.querySelector('[data-calendario-titulo]');

    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function escaparHtml(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function construirCuerpo(anio, mes, eventos) {
        var porDia = {};
        eventos.forEach(function (ev) {
            var dia = parseInt(ev.fecha.slice(8, 10), 10);
            (porDia[dia] = porDia[dia] || []).push(ev);
        });

        var primerDia    = new Date(anio, mes - 1, 1);
        var diasEnMes    = new Date(anio, mes, 0).getDate();
        var diaSemanaIni = primerDia.getDay();
        var hoy          = new Date();
        var esHoy = function (dia) {
            return anio === hoy.getFullYear() && mes === (hoy.getMonth() + 1) && dia === hoy.getDate();
        };

        var celdas = [];
        var i;
        for (i = 0; i < diaSemanaIni; i++) { celdas.push(null); }
        for (i = 1; i <= diasEnMes; i++) { celdas.push(i); }
        while (celdas.length % 7 !== 0) { celdas.push(null); }

        var html = '';
        for (var f = 0; f < celdas.length; f += 7) {
            html += '<tr>';
            celdas.slice(f, f + 7).forEach(function (dia) {
                if (dia === null) {
                    html += '<td></td>';
                    return;
                }
                html += '<td' + (esHoy(dia) ? ' class="dia-hoy"' : '') + '>'
                      + '<div class="numero-dia">' + dia + '</div>';
                (porDia[dia] || []).forEach(function (ev) {
                    html += '<a href="' + escaparHtml(ev.url) + '" class="evento-punto" '
                          + 'style="background:' + escaparHtml(ev.color) + '" title="' + escaparHtml(ev.titulo) + '">'
                          + escaparHtml(ev.titulo) + '</a>';
                });
                html += '</td>';
            });
            html += '</tr>';
        }
        return html;
    }

    function actualizarEnlacesNav(anio, mes) {
        var anterior  = mes === 1  ? { a: anio - 1, m: 12 } : { a: anio, m: mes - 1 };
        var siguiente = mes === 12 ? { a: anio + 1, m: 1 }  : { a: anio, m: mes + 1 };

        contenedor.querySelectorAll('[data-calendario-nav]').forEach(function (enlace) {
            var destino = enlace.dataset.calendarioNav === 'anterior' ? anterior : siguiente;
            var url = new URL(enlace.href);
            url.searchParams.set('anio', destino.a);
            url.searchParams.set('mes', destino.m);
            enlace.href = url.toString();
        });
    }

    function cargarMes(anio, mes, urlDestino) {
        var base = window.APP_URL || '';
        var query = 'anio=' + anio + '&mes=' + mes;
        if (contenedor.dataset.pastoral) {
            query += '&pastoral=' + encodeURIComponent(contenedor.dataset.pastoral);
        }
        fetch(base + '/index.php?area=publico&modulo=eventos&accion=datos&' + query)
            .then(function (respuesta) {
                return respuesta.ok ? respuesta.json() : Promise.reject(new Error('respuesta no válida'));
            })
            .then(function (datos) {
                cuerpo.innerHTML = construirCuerpo(datos.anio, datos.mes, datos.eventos);
                titulo.textContent = MESES[datos.mes - 1].charAt(0).toUpperCase()
                    + MESES[datos.mes - 1].slice(1) + ' ' + datos.anio;
                contenedor.dataset.anio = String(datos.anio);
                contenedor.dataset.mes  = String(datos.mes);
                actualizarEnlacesNav(datos.anio, datos.mes);
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', urlDestino);
                }
            })
            .catch(function () {
                window.location.href = urlDestino;
            });
    }

    contenedor.addEventListener('click', function (evento) {
        var enlace = evento.target.closest('[data-calendario-nav]');
        if (!enlace) {
            return;
        }
        var url  = new URL(enlace.href);
        var anio = parseInt(url.searchParams.get('anio'), 10);
        var mes  = parseInt(url.searchParams.get('mes'), 10);
        if (!anio || !mes) {
            return;   // Sin datos claros: se deja el enlace normal.
        }
        evento.preventDefault();
        cargarMes(anio, mes, enlace.href);
    });
})();
