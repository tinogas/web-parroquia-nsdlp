<?php
/**
 * Editor de texto con formato.
 *
 * Variables esperadas:
 *   $eh_nombre    nombre del textarea que se envía
 *   $eh_etiqueta  etiqueta visible
 *   $eh_valor     HTML guardado (ya saneado al guardarse)
 *   $eh_ayuda     texto de ayuda (opcional)
 *
 * El área editable y un textarea oculto van sincronizados: el navegador edita
 * la primera y lo que viaja en el formulario es el segundo. Si el visitante
 * tiene JavaScript desactivado, el textarea se muestra tal cual y se puede
 * escribir en él directamente.
 */
$eh_id    = 'ed_' . preg_replace('/[^a-z0-9_]/', '', strtolower($eh_nombre));
$eh_valor = $eh_valor ?? '';
$eh_ayuda = $eh_ayuda ?? '';

$eh_botones = [
    ['bold',                 'bi-type-bold',        'Negrita'],
    ['italic',               'bi-type-italic',      'Cursiva'],
    ['underline',            'bi-type-underline',   'Subrayado'],
    ['|',                    '',                    ''],
    ['h2',                   'bi-type-h2',          'Título'],
    ['h3',                   'bi-type-h3',          'Subtítulo'],
    ['p',                    'bi-paragraph',        'Párrafo normal'],
    ['|',                    '',                    ''],
    ['insertUnorderedList',  'bi-list-ul',          'Lista con puntos'],
    ['insertOrderedList',    'bi-list-ol',          'Lista numerada'],
    ['|',                    '',                    ''],
    ['createLink',           'bi-link-45deg',       'Insertar enlace'],
    ['unlink',               'bi-link-break',       'Quitar enlace'],
    ['removeFormat',         'bi-eraser',           'Quitar el formato'],
];
?>
<div class="mb-3">
    <label for="<?= e($eh_id) ?>" class="form-label fw-semibold"><?= e($eh_etiqueta) ?></label>

    <div class="editor-html" data-editor>
        <div class="editor-barra" role="toolbar" aria-label="Herramientas de formato">
            <?php foreach ($eh_botones as [$comando, $icono, $titulo]): ?>
                <?php if ($comando === '|'): ?>
                    <span class="editor-separador"></span>
                <?php else: ?>
                    <button type="button" class="editor-boton" data-comando="<?= e($comando) ?>"
                            title="<?= e($titulo) ?>" aria-label="<?= e($titulo) ?>">
                        <i class="bi <?= e($icono) ?>"></i>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php /* Contenido ya saneado con lista blanca al guardarse: se imprime sin escapar a propósito. */ ?>
        <div class="editor-area" contenteditable="true" role="textbox" aria-multiline="true"
             aria-labelledby="<?= e($eh_id) ?>_etiqueta"><?= $eh_valor ?></div>
    </div>

    <textarea name="<?= e($eh_nombre) ?>" id="<?= e($eh_id) ?>"
              class="form-control editor-fuente" rows="6"><?= e($eh_valor) ?></textarea>

    <div class="form-text">
        <?= $eh_ayuda !== '' ? e($eh_ayuda) . ' ' : '' ?>
        Se admiten negritas, cursivas, listas, títulos y enlaces. El resto del formato se descarta al guardar.
    </div>
</div>
