<?php
/**
 * Lectura de un aviso dentro del panel, sin poder tocarlo. Es lo que abre un
 * miembro de la pastoral cuando le llega un aviso interno: mientras no esté
 * publicado en el sitio no hay página pública que enseñarle, y el formulario
 * de edición no es sitio para leer (ni lo puede abrir quien solo consulta).
 */
$estado = estado_publicacion($aviso);
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('avisos')) ?>" class="text-decoration-none">Avisos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Leer</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= e($aviso['titulo']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ($puedeEditar): ?>
        <a href="<?= e(url_admin('avisos', 'editar', ['id' => $aviso['id']])) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <?php endif; ?>
        <a href="<?= e(url_admin('avisos')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <?php if ($aviso['imagen']): ?>
            <img src="<?= e(url_activo($aviso['imagen'])) ?>" class="card-img-top" alt=""
                 style="max-height:320px;object-fit:cover">
            <?php endif; ?>
            <div class="card-body p-4">
                <?php if ($aviso['resumen']): ?>
                <p class="lead fs-6"><?= e($aviso['resumen']) ?></p>
                <?php endif; ?>

                <?php if ($aviso['contenido']): ?>
                <?= $aviso['contenido'] /* ya saneado con SanitizadorHtml al guardarse */ ?>
                <?php else: ?>
                <p class="text-muted fst-italic mb-0">Este aviso no tiene más texto que su resumen.</p>
                <?php endif; ?>

                <?php if ($aviso['archivo_pdf']): ?>
                <a href="<?= e(url_activo($aviso['archivo_pdf'])) ?>" target="_blank"
                   class="btn btn-sm btn-outline-danger mt-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Abrir el PDF
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="text-uppercase text-muted small fw-semibold mb-2">Ficha</p>

                <div class="mb-2"><?= badge_escalon($aviso) ?></div>

                <dl class="row small mb-0">
                    <dt class="col-5 fw-semibold">Pastoral</dt>
                    <dd class="col-7"><?= e($pastoral['nombre'] ?? 'Toda la parroquia') ?></dd>

                    <dt class="col-5 fw-semibold">Tipo</dt>
                    <dd class="col-7"><?= e(AvisoModel::TIPOS[$aviso['tipo']] ?? $aviso['tipo']) ?></dd>

                    <dt class="col-5 fw-semibold">Visible desde</dt>
                    <dd class="col-7"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></dd>

                    <?php if ($aviso['vigente_hasta']): ?>
                    <dt class="col-5 fw-semibold">Visible hasta</dt>
                    <dd class="col-7"><?= e(fecha_larga($aviso['vigente_hasta'])) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($estado === 'publico'): ?>
                <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary mt-3">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Verlo en el sitio
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
