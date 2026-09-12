<?php
/**
 * Cuadro "Mensaje para todos" que alimenta a los botones de WhatsApp del
 * listado. Se escribe una vez y assets/js/app.js lo pega al enlace de cada
 * quien: con wa.me cada conversación se abre por separado, así que sin esto
 * habría que teclear el mismo aviso una vez por persona.
 *
 * No se guarda en ningún lado —el aviso de la reunión de mañana no es un
 * ajuste del sitio—; solo el borrador de la sesión, en el navegador.
 *
 * Variables:
 *   $mw_lista — clave corta del listado, para que el borrador de Coros no se
 *               le aparezca a Catequesis. Ej.: 'ministros', 'coristas'.
 *   $mw_filas — las filas que se van a listar, para no dibujar el cuadro
 *               cuando no hay un solo teléfono al que escribirle. Hoy es el
 *               caso de Coros y Proclamadores, con el teléfono en NULL casi
 *               todos.
 *
 * Se dibuja solo con el permiso `personas.contactar`.
 */
$mw_hayTelefono = false;
foreach (($mw_filas ?? []) as $mw_fila) {
    if (telefono_internacional($mw_fila['telefono'] ?? null) !== '') {
        $mw_hayTelefono = true;
        break;
    }
}
?>
<?php if ($mw_hayTelefono && Auth::tienePermiso('personas.contactar')): ?>
<div class="mb-3">
    <label for="whatsappMensaje" class="form-label small text-muted mb-1">
        <i class="bi bi-whatsapp text-success"></i> Mensaje para todos
    </label>
    <textarea id="whatsappMensaje" class="form-control form-control-sm" rows="2"
              data-lista="<?= e($mw_lista ?? 'lista') ?>"
              placeholder="Escríbelo una vez y cada botón de WhatsApp lo lleva ya escrito. Usa {nombre} donde vaya el nombre de pila."></textarea>
    <div class="form-text">
        Se abre la conversación con el texto puesto; enviarlo sigue siendo un clic tuyo, uno por persona.
    </div>
</div>
<?php endif; ?>
