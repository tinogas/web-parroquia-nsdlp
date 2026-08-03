/* ============================================================
   Lightbox de la galería pública — sin dependencias
   Cada tarjeta ya es un <a> al archivo completo que funciona sin JS (abre la
   foto en pestaña nueva); esto solo intercepta el clic para mostrarla sin
   recortar, en la misma página, con navegación entre las fotos de esta
   página. Si algo no encaja (no hay tarjetas, o el visor no está en el DOM),
   no hace nada: el enlace de siempre sigue funcionando.
   ============================================================ */

(function () {
    'use strict';

    var tarjetas = Array.prototype.slice.call(document.querySelectorAll('.tarjeta-galeria'));
    var lightbox = document.getElementById('lightboxGaleria');
    if (!tarjetas.length || !lightbox) {
        return;
    }

    var img          = lightbox.querySelector('img');
    var pie          = lightbox.querySelector('figcaption');
    var contador     = lightbox.querySelector('.lightbox-contador');
    var botonCerrar  = lightbox.querySelector('.lightbox-cerrar');
    var disparador   = null; // qué tenía el foco antes de abrir, para devolvérselo al cerrar
    var actual       = 0;

    function mostrar() {
        var tarjeta = tarjetas[actual];
        img.src = tarjeta.getAttribute('href');
        img.alt = tarjeta.querySelector('img').alt;

        var titulo = tarjeta.getAttribute('data-titulo');
        pie.textContent = titulo || '';
        pie.hidden = !titulo;

        contador.textContent = (actual + 1) + ' / ' + tarjetas.length;
    }

    function abrir(indice) {
        actual = indice;
        disparador = document.activeElement;
        mostrar();
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        botonCerrar.focus();
    }

    function cerrar() {
        lightbox.hidden = true;
        document.body.style.overflow = '';
        img.src = ''; // no seguir reteniendo en memoria una foto que ya no se ve
        if (disparador) { disparador.focus(); }
    }

    function anterior() {
        actual = (actual - 1 + tarjetas.length) % tarjetas.length;
        mostrar();
    }

    function siguiente() {
        actual = (actual + 1) % tarjetas.length;
        mostrar();
    }

    tarjetas.forEach(function (tarjeta, indice) {
        tarjeta.addEventListener('click', function (evento) {
            evento.preventDefault();
            abrir(indice);
        });
    });

    botonCerrar.addEventListener('click', cerrar);
    lightbox.querySelector('.lightbox-anterior').addEventListener('click', anterior);
    lightbox.querySelector('.lightbox-siguiente').addEventListener('click', siguiente);

    // Clic en el fondo oscuro cierra; clic en la foto o los botones, no.
    lightbox.addEventListener('click', function (evento) {
        if (evento.target === lightbox) { cerrar(); }
    });

    document.addEventListener('keydown', function (evento) {
        if (lightbox.hidden) { return; }
        if (evento.key === 'Escape') { cerrar(); }
        else if (evento.key === 'ArrowLeft') { anterior(); }
        else if (evento.key === 'ArrowRight') { siguiente(); }
    });
})();
