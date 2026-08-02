<?php
/**
 * Una entrada de la agenda dentro de una lista de día. Espera $it (ítem ya
 * normalizado por AgendaController) y lo dibuja como enlace a su edición o
 * como texto plano, según $it['url'].
 *
 * Que no haya enlace no es un descuido: si esta persona no puede editar ese
 * registro, el enlace la llevaría a un «no tienes permiso». Ver la nota de
 * AgendaController::urlDeEdicion().
 */
$etiqueta = $it['tipo'] === 'curso' ? 'Curso' : 'Evento';
$dura = !empty($it['fecha_fin'])
     && substr((string) $it['fecha_fin'], 0, 10) !== substr((string) $it['fecha_inicio'], 0, 10);
$marca = $it['url'] ? 'a' : 'span';
?>
<<?= $marca ?> <?= $it['url'] ? 'href="' . e($it['url']) . '"' : '' ?>
   class="evento-linea d-flex gap-2 text-decoration-none <?= $it['publicado'] ? '' : 'es-borrador' ?>"
   style="border-left-color:<?= e($it['color']) ?>">
    <span class="evento-linea-hora">
        <?= $it['todo_el_dia'] ? 'Todo el día' : e(hora_corta(substr((string) $it['fecha_inicio'], 11))) ?>
    </span>
    <span class="evento-linea-texto">
        <span class="evento-linea-titulo"><?= e($it['titulo']) ?></span>
        <span class="evento-linea-detalle">
            <span class="badge bg-light text-secondary border me-1"><?= e($etiqueta) ?></span>
            <span class="badge bg-light text-secondary border me-1"><?= e($it['pastoral_nombre'] ?? 'General') ?></span>
            <?php if (!empty($it['centro_nombre'])): ?>
            <span class="badge bg-light text-secondary border me-1"><?= e($it['centro_nombre']) ?></span>
            <?php endif; ?>
            <?php if (!$it['publicado']): ?>
            <span class="badge bg-warning-subtle text-warning-emphasis me-1">Borrador</span>
            <?php endif; ?>
            <?php if (!empty($it['horario'])): ?>
            <span class="me-1"><i class="bi bi-clock me-1"></i><?= e($it['horario']) ?></span>
            <?php endif; ?>
            <?php if ($it['lugar']): ?>
            <span class="me-1"><i class="bi bi-geo-alt me-1"></i><?= e($it['lugar']) ?></span>
            <?php endif; ?>
            <?php if ($dura): ?>
            <span><i class="bi bi-arrow-left-right me-1"></i>hasta el
                <?= e(fecha_larga(substr((string) $it['fecha_fin'], 0, 10))) ?></span>
            <?php endif; ?>
        </span>
    </span>
</<?= $marca ?>>
