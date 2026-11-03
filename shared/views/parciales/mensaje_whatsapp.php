<?php
/**
 * Cuadro "Mensaje para todos" que alimenta a los botones de WhatsApp del
 * listado. Se escribe una vez y assets/js/app.js lo pega al enlace de cada
 * quien: con wa.me cada conversación se abre por separado, así que sin esto
 * habría que teclear el mismo aviso una vez por persona.
 *
 * Si la pastoral tiene guardado el enlace de su grupo (pastorales.whatsapp_grupo),
 * aparece además "Copiar y abrir grupo". A un grupo WhatsApp no le acepta el
 * texto ya escrito —wa.me solo sirve para chats de una persona—, así que el
 * botón copia el mensaje al portapapeles y abre el grupo; ahí se pega.
 *
 * No se guarda en ningún lado —el aviso de la reunión de mañana no es un
 * ajuste del sitio—; solo el borrador de la sesión, en el navegador.
 *
 * Variables:
 *   $mw_lista    — clave corta del listado, para que el borrador de Coros no se
 *                  le aparezca a Catequesis. Ej.: 'ministros', 'coristas'.
 *   $mw_filas    — las filas que se van a listar, para no dibujar los botones
 *                  individuales cuando no hay un solo teléfono al que
 *                  escribirle. Hoy es el caso de Coros y Proclamadores, con el
 *                  teléfono en NULL casi todos.
 *   $mw_pastoral — opcional: la pastoral del listado, como fila de `pastorales`
 *                  o como slug (PASTORAL_COROS…). De ahí sale el grupo.
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

$mw_fila_pastoral = $mw_pastoral ?? null;
if (is_string($mw_fila_pastoral)) {
    require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
    $mw_fila_pastoral = (new PastoralModel())->porSlugActiva($mw_fila_pastoral);
}
$mw_grupo = whatsapp_grupo_enlace($mw_fila_pastoral['whatsapp_grupo'] ?? null);
?>
<?php if (($mw_hayTelefono || $mw_grupo !== '') && Auth::tienePermiso('personas.contactar')): ?>
<div class="mb-3">
    <label for="whatsappMensaje" class="form-label small text-muted mb-1">
        <i class="bi bi-whatsapp text-success"></i> Mensaje para todos
    </label>
    <textarea id="whatsappMensaje" class="form-control form-control-sm" rows="2"
              data-lista="<?= e($mw_lista ?? 'lista') ?>"
              placeholder="Escríbelo una vez y cada botón de WhatsApp lo lleva ya escrito. Usa {nombre} donde vaya el nombre de pila."></textarea>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <?php if ($mw_grupo !== ''): ?>
        <a href="<?= e($mw_grupo) ?>" id="whatsappGrupo" class="btn btn-sm btn-success"
           target="_blank" rel="noopener"
           title="Copia el mensaje y abre el grupo de WhatsApp de <?= e($mw_fila_pastoral['nombre'] ?? 'la pastoral') ?>">
            <i class="bi bi-people-fill me-1"></i>Copiar y abrir grupo
        </a>
        <?php endif; ?>
        <div class="form-text m-0" id="whatsappAviso" aria-live="polite">
            <?php if ($mw_hayTelefono): ?>
            Se abre la conversación con el texto puesto; enviarlo sigue siendo un clic tuyo, uno por persona.
            <?php endif; ?>
            <?php if ($mw_grupo !== ''): ?>
            En el grupo, el mensaje se pega (mantén presionado → Pegar, o Ctrl+V); {nombre} se quita.
            <?php endif; ?>
        </div>
    </div>
    <?php if ($mw_grupo === '' && $mw_fila_pastoral && Auth::tienePermiso('pastorales.editar')): ?>
    <div class="form-text">
        <a href="<?= e(url_admin('pastorales', 'editar', ['id' => (int) $mw_fila_pastoral['id']])) ?>">Agrega el enlace del grupo de WhatsApp</a>
        en la ficha de la pastoral para escribirle al grupo desde aquí.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
