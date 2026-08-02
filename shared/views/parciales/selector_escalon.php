<?php
/**
 * Los tres escalones de publicación de un aviso o un curso, en el formulario.
 * Sustituye al interruptor «Publicado» de cuando solo había dos posiciones.
 *
 * Van como radios y no como dos interruptores sueltos porque los estados son
 * excluyentes y ordenados: no existe «público pero no interno», y con dos
 * casillas independientes sí se podría marcar. Aquí esa combinación no es
 * representable, que es la misma garantía que da el CHECK de la base.
 *
 * El escalón público solo se ofrece a quien puede publicar; a quien no, se le
 * dice qué va a pasar en vez de enseñarle una opción desactivada.
 *
 * Variables esperadas:
 *   $se_estadoActual   string  clave de ESTADOS_PUBLICACION ya seleccionada
 *   $se_puedePublicar  bool    si se ofrece el tercer escalón
 *   $se_quien          string  a quién alcanza el escalón interno, para la ayuda
 */
?>
<fieldset class="mb-0">
    <legend class="form-label fw-semibold fs-6">Publicación</legend>
    <?php foreach (ESTADOS_PUBLICACION as $clave => $etiqueta): ?>
    <?php if ($clave === 'publico' && !$se_puedePublicar) { continue; } ?>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="estado"
               value="<?= e($clave) ?>" id="estado_<?= e($clave) ?>"
               <?= $se_estadoActual === $clave ? 'checked' : '' ?>>
        <label class="form-check-label" for="estado_<?= e($clave) ?>">
            <?= e($etiqueta) ?>
            <span class="d-block text-muted small">
                <?php if ($clave === 'borrador'): ?>
                Solo lo ves tú y quien administre esta pastoral.
                <?php elseif ($clave === 'interno'): ?>
                Lo leen <?= e($se_quien) ?> desde su panel. No sale en el sitio web.
                <?php else: ?>
                Además de lo anterior, se publica en el sitio web.
                <?php endif; ?>
            </span>
        </label>
    </div>
    <?php endforeach; ?>

    <?php if (!$se_puedePublicar): ?>
    <p class="small text-muted mb-0 mt-2">
        <i class="bi bi-info-circle me-1"></i>Para que salga en el sitio web hace falta que lo publique un editor.
    </p>
    <?php endif; ?>
</fieldset>
