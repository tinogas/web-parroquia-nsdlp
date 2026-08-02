<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Catequesis — Catequistas</h1>
        <p class="text-muted mb-0 small">Catequistas de Catecismo. El grado que da cada uno se asigna por periodo.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url_admin('catequesis', 'periodos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar-range me-1"></i>Periodos
        </a>
        <a href="<?= e(url_admin('catequesis', 'actividades')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar3 me-1"></i>Actividades
        </a>
        <a href="<?= e(url_admin('catequesis', 'documentos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Documentos
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$catequistas): ?>
        <p class="text-muted small mb-3">Todavía no hay catequistas registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Nombre</th><th class="d-none d-md-table-cell">Contacto</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($catequistas as $catequista): ?>
                    <tr class="<?= $catequista['activo'] ? '' : 'text-muted' ?>">
                        <td><?= e($catequista['nombre']) ?><?= $catequista['activo'] ? '' : ' (inactivo)' ?></td>
                        <td class="d-none d-md-table-cell small">
                            <?= e(trim(($catequista['telefono'] ?? '') . ($catequista['telefono'] && $catequista['email'] ? ' · ' : '') . ($catequista['email'] ?? ''))) ?>
                        </td>
                        <td class="text-end">
                            <?php if (Auth::tienePermiso('catequesis.editar')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#catequista<?= (int) $catequista['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('catequesis.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#catequistaNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar catequista
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$dibujarModalCatequista = static function (string $idModal, ?array $catequista, int $pastoralId, string $csrf, array $personas) {
    $vacio     = $catequista === null;
    $personaId = $vacio ? 0 : (int) ($catequista['persona_id'] ?? 0);
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'catequesis', 'catequista_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $catequista['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo catequista' : 'Editar catequista' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">¿Quién es?</label>
                        <select name="persona_id" class="form-select form-select-sm">
                            <option value="">— Todavía no está en el equipo pastoral —</option>
                            <?php foreach ($personas as $persona): ?>
                            <option value="<?= (int) $persona['id'] ?>"
                                <?= $personaId === (int) $persona['id'] ? 'selected' : '' ?>>
                                <?= e($persona['nombre']) ?><?= $persona['cargo'] ? ' — ' . e($persona['cargo']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre, si no está en el equipo pastoral</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio || $personaId ? '' : $catequista['nombre']) ?>" maxlength="140">
                        <div class="form-text">Se ignora si arriba eliges a alguien del equipo.</div>
                    </div>
                    <?php if ($personaId): ?>
                    <?php
                    $contacto = trim(
                        (string) ($catequista['telefono'] ?? '')
                        . (($catequista['telefono'] ?? '') && ($catequista['email'] ?? '') ? ' · ' : '')
                        . (string) ($catequista['email'] ?? '')
                    );
                    ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Contacto</label>
                        <p class="form-control-plaintext form-control-sm py-1 mb-0 small">
                            <?= $contacto !== '' ? e($contacto) : '—' ?>
                        </p>
                        <div class="form-text">Viene de su ficha del equipo pastoral.</div>
                    </div>
                    <?php else: ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $catequista['telefono']) ?>" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $catequista['email']) ?>" maxlength="150">
                    </div>
                    <?php endif; ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $catequista['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio && Auth::tienePermiso('catequesis.eliminar')): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'catequesis', 'catequista_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este catequista? También se quitará de cualquier periodo donde estuviera asignado.');">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$dibujarModalCatequista('catequistaNuevo', null, $pastoralId, $csrf, $personas);
foreach ($catequistas as $catequista) {
    $dibujarModalCatequista('catequista' . (int) $catequista['id'], $catequista, $pastoralId, $csrf, $personas);
}
?>
