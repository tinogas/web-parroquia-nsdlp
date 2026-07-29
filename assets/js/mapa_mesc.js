/* ============================================================
   Mapa de la visita MESC — mejora progresiva, sin dependencias propias.

   Usa Leaflet + tiles de OpenStreetMap (CDN, sin llave de API): marcar el pin
   es enteramente opcional, el campo obligatorio sigue siendo la dirección de
   texto. Si Leaflet no carga por lo que sea, el formulario funciona igual con
   solo la dirección escrita a mano.
   ============================================================ */

(function () {
    'use strict';

    var contenedor = document.getElementById('mapa-visita');
    if (!contenedor || typeof L === 'undefined') {
        return;
    }

    var inputLat = document.getElementById('latitud');
    var inputLng = document.getElementById('longitud');

    var latVisita    = parseFloat(contenedor.dataset.lat);
    var lngVisita    = parseFloat(contenedor.dataset.lng);
    var latParroquia = parseFloat(contenedor.dataset.latParroquia);
    var lngParroquia = parseFloat(contenedor.dataset.lngParroquia);

    var tieneVisita    = !isNaN(latVisita) && !isNaN(lngVisita);
    var tieneParroquia = !isNaN(latParroquia) && !isNaN(lngParroquia);

    var centro, zoom;
    if (tieneVisita) {
        centro = [latVisita, lngVisita];
        zoom = 16;
    } else if (tieneParroquia) {
        centro = [latParroquia, lngParroquia];
        zoom = 13;
    } else {
        centro = [23.6345, -102.5528]; // centro geográfico de México, fallback sin ninguna referencia
        zoom = 5;
    }

    var mapa = L.map(contenedor).setView(centro, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; colaboradores de <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(mapa);

    var marcador = null;

    function actualizarInputs(lat, lng) {
        inputLat.value = lat.toFixed(7);
        inputLng.value = lng.toFixed(7);
    }

    function colocarMarcador(lat, lng) {
        if (marcador) {
            mapa.removeLayer(marcador);
        }
        marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
        marcador.on('dragend', function () {
            var posicion = marcador.getLatLng();
            actualizarInputs(posicion.lat, posicion.lng);
        });
        actualizarInputs(lat, lng);
    }

    if (tieneVisita) {
        colocarMarcador(latVisita, lngVisita);
    }

    mapa.on('click', function (evento) {
        colocarMarcador(evento.latlng.lat, evento.latlng.lng);
    });

    var botonQuitar = document.getElementById('quitar-ubicacion');
    if (botonQuitar) {
        botonQuitar.addEventListener('click', function () {
            if (marcador) {
                mapa.removeLayer(marcador);
                marcador = null;
            }
            inputLat.value = '';
            inputLng.value = '';
        });
    }
})();
