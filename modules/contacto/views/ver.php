<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('mensajes')) ?>" class="text-decoration-none">Mensajes</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">De <?= e($mensaje['nombre']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1"><?= e($mensaje['asunto'] ?: '(Sin asunto)') ?></h1>
        <p class="text-muted mb-0 small"><?= e(fecha_larga($mensaje['created_at'])) ?></p>
    </div>
    <a href="<?= e(url_admin('mensajes')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="mb-0" style="white-space:pre-wrap"><?= e($mensaje['mensaje']) ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Datos de contacto</h2>
                <ul class="list-unstyled small mb-0 lista-contacto">
                    <li><i class="bi bi-person"></i> <?= e($mensaje['nombre']) ?></li>
                    <?php if ($mensaje['email']): ?>
                    <li><i class="bi bi-envelope"></i> <a href="mailto:<?= e($mensaje['email']) ?>"><?= e($mensaje['email']) ?></a></li>
                    <?php endif; ?>
                    <?php if ($mensaje['telefono']): ?>
                    <li>
                        <i class="bi bi-telephone"></i>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $mensaje['telefono'])) ?>"><?= e($mensaje['telefono']) ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if ($mensaje['respondido']): ?>
        <div class="alert alert-success small">
            <i class="bi bi-check-circle me-1"></i>
            Marcado como atendido<?= $mensaje['atendido_por_nombre'] ? ' por ' . e($mensaje['atendido_por_nombre']) : '' ?>.
        </div>
        <?php else: ?>
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mensajes', 'marcarRespondido')) ?>" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $mensaje['id'] ?>">
            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-check-lg me-1"></i>Marcar como atendido
            </button>
        </form>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-2">Nota interna</h2>
                <p class="text-muted small mb-3">Solo la ve el equipo del panel; nunca aparece en el sitio.</p>
                <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mensajes', 'guardarNota')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $mensaje['id'] ?>">
                    <textarea name="nota_interna" class="form-control mb-2" rows="3"
                              placeholder="Ej. Se llamó el martes, sin respuesta."><?= e((string) $mensaje['nota_interna']) ?></textarea>
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-save me-1"></i>Guardar nota
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
