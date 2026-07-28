/* ============================================================
   Editor de texto con formato — sin dependencias

   Un área editable sincronizada con un textarea oculto. Se descartaron TinyMCE
   y CKEditor por dos razones: son dependencia externa, contra la regla del
   proyecto, y amplían la superficie de ataque de un sitio que va a tener varias
   personas escribiendo contenido.

   Usa document.execCommand, que está marcado como obsoleto pero sigue
   funcionando en todos los navegadores actuales y no tiene sustituto nativo. Si
   algún día deja de responder, el textarea sigue ahí y se puede escribir el
   texto a mano; nada se pierde.

   La seguridad no depende de este archivo: lo que se guarde pasa por
   core/SanitizadorHtml.php en el servidor.
   ============================================================ */

(function () {
    'use strict';

    var editores = document.querySelectorAll('[data-editor]');
    if (!editores.length) {
        return;
    }

    editores.forEach(function (caja) {
        var area     = caja.querySelector('.editor-area');
        var textarea = caja.parentElement.querySelector('textarea');

        if (!area || !textarea) {
            return;
        }

        // Con JavaScript disponible manda el área editable; el textarea queda
        // oculto y solo transporta el valor.
        textarea.classList.add('d-none');
        caja.classList.add('activo');

        var sincronizar = function () {
            textarea.value = area.innerHTML.trim();
        };

        // Barra de herramientas
        caja.querySelectorAll('.editor-boton').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var comando = boton.dataset.comando;
                area.focus();

                if (comando === 'h2' || comando === 'h3' || comando === 'p') {
                    document.execCommand('formatBlock', false, comando);
                } else if (comando === 'createLink') {
                    insertarEnlace(area);
                } else {
                    document.execCommand(comando, false, null);
                }

                sincronizar();
                actualizarEstado(caja);
            });
        });

        area.addEventListener('input', sincronizar);
        area.addEventListener('blur', sincronizar);
        area.addEventListener('keyup', function () { actualizarEstado(caja); });
        area.addEventListener('mouseup', function () { actualizarEstado(caja); });

        // Al pegar, quedarse solo con el texto: evita arrastrar el formato de
        // Word o de otra página, que el servidor descartaría de todas formas.
        area.addEventListener('paste', function (evento) {
            evento.preventDefault();
            var texto = (evento.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, texto);
            sincronizar();
        });

        // Red de seguridad: sincronizar antes de enviar, por si el usuario dio
        // clic en Guardar sin que el área perdiera el foco.
        var formulario = caja.closest('form');
        if (formulario) {
            formulario.addEventListener('submit', sincronizar);
        }

        sincronizar();
    });

    function insertarEnlace(area) {
        var seleccion = window.getSelection();
        if (!seleccion || seleccion.isCollapsed) {
            alert('Selecciona primero el texto que quieres convertir en enlace.');
            return;
        }

        var url = window.prompt('Dirección del enlace:', 'https://');
        if (!url) {
            return;
        }

        // El servidor rechaza cualquier esquema que no sea seguro; esto solo
        // evita el viaje de ida y vuelta.
        if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) {
            alert('Usa una dirección que empiece por https://, mailto: o tel:');
            return;
        }

        document.execCommand('createLink', false, url);
    }

    /** Resalta los botones cuyo formato está activo donde está el cursor. */
    function actualizarEstado(caja) {
        var estados = {
            bold:                'bold',
            italic:              'italic',
            underline:           'underline',
            insertUnorderedList: 'insertUnorderedList',
            insertOrderedList:   'insertOrderedList'
        };

        caja.querySelectorAll('.editor-boton').forEach(function (boton) {
            var comando = estados[boton.dataset.comando];
            if (!comando) {
                return;
            }
            try {
                boton.classList.toggle('activo', document.queryCommandState(comando));
            } catch (e) {
                // queryCommandState puede lanzar excepción sin selección válida.
            }
        });
    }
})();
