<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Documentos</h1>
        <p class="text-muted mb-0 small">Material descargable: cancioneros, partituras y letras.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php $navActiva = 'documentos'; require __DIR__ . '/_nav.php'; ?>
    </div>
</div>

<?php /* Igual que el tablero: `pastoral_documentos` es una tabla por pastoral,
         no por módulo, y el panel básico de la pastoral muestra esta misma
         lista. Antes de la migración del 4 de septiembre de 2026 había dos
         listas ciegas la una a la otra. */ ?>
<div class="alert alert-light border small mb-4">
    <i class="bi bi-info-circle text-primary me-1"></i>
    Estos documentos son los mismos que aparecen en el <strong>panel básico de la pastoral</strong>
    y en su página pública: una sola lista, se abra desde donde se abra.
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$documentos): ?>
        <p class="text-muted small mb-3">Todavía no hay documentos.</p>
        <?php else: ?>
        <ul class="list-group list-group-flush mb-3">
            <?php foreach ($documentos as $documento): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <a href="<?= e(url_activo($documento['archivo'])) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i><?= e($documento['titulo']) ?>
                </a>
                <?php if (Auth::tienePermiso('coros.eliminar')): ?>
                <form method="POST" accept-charset="UTF-8"
                      action="<?= e(url_post('admin', 'coros', 'documento_eliminar')) ?>" class="m-0"
                      onsubmit="return confirm('¿Eliminar este documento?');">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $documento['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('coros.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#documentoNuevo">
            <i class="bi bi-plus-lg me-1"></i>Subir documento
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="documentoNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" accept-charset="UTF-8" enctype="multipart/form-data"
              action="<?= e(url_post('admin', 'coros', 'documento_guardar')) ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold">Nuevo documento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Título</label>
                    <input type="text" name="titulo" class="form-control form-control-sm" maxlength="160" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Archivo</label>
                    <input type="file" name="archivo" class="form-control form-control-sm" accept="application/pdf" required>
                    <div class="form-text">Solo PDF, hasta 8 MB.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Orden</label>
                    <input type="number" name="orden" class="form-control form-control-sm" value="0" min="0" max="999">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Subir
                </button>
            </div>
        </form>
    </div>
</div>
