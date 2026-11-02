/**
 * Contenido → Menú del sitio: las flechas suben o bajan el renglón dentro de
 * la tabla. Solo se mueven entre sí los renglones [data-movible]; Inicio y
 * Contacto quedan fijos arriba y abajo. El orden se guarda tal como quedan
 * los campos ocultos orden[] al enviar el formulario.
 */
(function () {
    'use strict';

    const tabla = document.getElementById('tablaMenu');
    if (!tabla) { return; }

    function actualizarFlechas() {
        const filas = tabla.querySelectorAll('tr[data-movible]');
        filas.forEach(function (fila, i) {
            const subir = fila.querySelector('[data-subir]');
            const bajar = fila.querySelector('[data-bajar]');
            if (subir) { subir.disabled = i === 0; }
            if (bajar) { bajar.disabled = i === filas.length - 1; }
        });
    }

    tabla.addEventListener('click', function (evento) {
        const boton = evento.target.closest('[data-subir], [data-bajar]');
        if (!boton || boton.disabled) { return; }

        const fila = boton.closest('tr[data-movible]');
        if (boton.hasAttribute('data-subir')) {
            const anterior = fila.previousElementSibling;
            if (anterior && anterior.hasAttribute('data-movible')) {
                fila.parentNode.insertBefore(fila, anterior);
            }
        } else {
            const siguiente = fila.nextElementSibling;
            if (siguiente && siguiente.hasAttribute('data-movible')) {
                fila.parentNode.insertBefore(siguiente, fila);
            }
        }

        fila.classList.add('table-warning');
        setTimeout(function () { fila.classList.remove('table-warning'); }, 600);
        actualizarFlechas();
        boton.focus();
    });

    // Sin permiso de edición los botones ya vienen deshabilitados del
    // servidor y se quedan así.
    if (!tabla.querySelector('[data-subir]:not([disabled])') && !tabla.querySelector('[data-bajar]:not([disabled])')) {
        return;
    }
    actualizarFlechas();
}());
