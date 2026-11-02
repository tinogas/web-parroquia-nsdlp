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
                } else if (comando === 'insertImage') {
                    elegirImagen(caja, area, sincronizar);
                    return;   // la subida es asíncrona: sincroniza al terminar
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

    /**
     * «Insertar imagen»: abre el selector de archivos, sube la foto a
     * modules/medios (la misma validación que el resto del panel) y la coloca
     * donde estaba el cursor, dentro de un <figure>. Se pide una descripción
     * corta para el texto alternativo, que es lo que oye quien usa lector de
     * pantalla. Si la subida falla, se avisa y no se toca el texto.
     */
    function elegirImagen(caja, area, sincronizar) {
        var destino = caja.dataset.subirImagen;
        var formulario = caja.closest('form');
        var token = formulario ? formulario.querySelector('input[name="_csrf"]') : null;
        if (!destino || !token) {
            alert('No se pueden subir imágenes desde esta pantalla.');
            return;
        }

        // Recordar dónde estaba el cursor: el selector de archivos se lo lleva.
        var seleccion = window.getSelection();
        var rango = seleccion && seleccion.rangeCount && area.contains(seleccion.anchorNode)
            ? seleccion.getRangeAt(0).cloneRange() : null;

        var selector = document.createElement('input');
        selector.type = 'file';
        selector.accept = 'image/jpeg,image/png,image/webp,image/gif';
        selector.addEventListener('change', function () {
            var archivo = selector.files && selector.files[0];
            if (!archivo) {
                return;
            }
            if (archivo.size > 4 * 1024 * 1024) {
                alert('La imagen pesa más de 4 MB. Redúcela e inténtalo de nuevo.');
                return;
            }
            var descripcion = window.prompt('Describe la imagen en pocas palabras (opcional):', '') || '';

            var datos = new FormData();
            datos.append('imagen', archivo);
            datos.append('_csrf', token.value);

            caja.classList.add('subiendo');
            fetch(destino, { method: 'POST', body: datos, credentials: 'same-origin' })
                .then(function (respuesta) {
                    return respuesta.json().catch(function () {
                        return { error: 'El servidor no respondió como se esperaba.' };
                    });
                })
                .then(function (resultado) {
                    if (!resultado || !resultado.url) {
                        alert('No se pudo subir la imagen: ' + ((resultado && resultado.error) || 'error desconocido.'));
                        return;
                    }
                    var figura = document.createElement('figure');
                    var imagen = document.createElement('img');
                    imagen.src = resultado.url;
                    imagen.alt = descripcion.trim();
                    figura.appendChild(imagen);
                    var despues = document.createElement('p');
                    despues.appendChild(document.createElement('br'));

                    if (rango) {
                        rango.collapse(false);
                        rango.insertNode(despues);
                        rango.insertNode(figura);
                    } else {
                        area.appendChild(figura);
                        area.appendChild(despues);
                    }
                    sincronizar();
                })
                .catch(function () {
                    alert('No se pudo subir la imagen: revisa tu conexión e inténtalo de nuevo.');
                })
                .then(function () {
                    caja.classList.remove('subiendo');
                });
        });
        selector.click();
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
