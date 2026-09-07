<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Integrantes</h1>
        <p class="text-muted mb-0 small">El catálogo de la pastoral, y en qué misas canta cada quien.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php $navActiva = 'coristas'; require __DIR__ . '/_nav.php'; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$coristas): ?>
        <p class="text-muted small mb-3">Todavía no hay nadie en el catálogo.</p>
        <?php else: ?>
        <?php
        $mw_lista = 'coristas';
        $mw_filas = $coristas;
        require BASE_PATH . '/shared/views/parciales/mensaje_whatsapp.php';
        ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Voz e instrumento</th>
                        <th>Canta en</th>
                        <th class="d-none d-lg-table-cell">Contacto</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($coristas as $corista): ?>
                <?php $sus = $susCoros[(int) $corista['id']] ?? []; ?>
                    <tr class="<?= $corista['activo'] ? '' : 'text-muted' ?>">
                        <td><?= e($corista['nombre']) ?><?= $corista['activo'] ? '' : ' (inactivo)' ?></td>
                        <td class="small">
                            <?php if (!$corista['voz'] && !$corista['instrumento']): ?>
                            <span class="text-muted">—</span>
                            <?php else: ?>
                            <?php if ($corista['voz']): ?>
                            <span class="badge bg-light text-dark border fw-normal">
                                <i class="bi bi-music-note me-1"></i><?= e($corista['voz']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($corista['instrumento']): ?>
                            <span class="badge bg-light text-dark border fw-normal">
                                <i class="bi bi-music-player me-1"></i><?= e($corista['instrumento']) ?>
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if (!$sus): ?>
                            <span class="text-muted">—</span>
                            <?php else: ?>
                            <?php foreach ($sus as $coroId): ?>
                            <?php if (!isset($coros[$coroId])) { continue; } ?>
                            <span class="badge bg-light text-dark border fw-normal">
                                <?= e($coros[$coroId]['etiqueta']) ?>
                            </span>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell small">
                            <?= e(trim(($corista['telefono'] ?? '')
                                . ($corista['telefono'] && $corista['email'] ? ' · ' : '')
                                . ($corista['email'] ?? ''))) ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <?php if (Auth::tienePermiso('personas.contactar')): ?>
                                <?= boton_whatsapp($corista['telefono'], $corista['nombre']) ?>
                                <?php endif; ?>
                                <?php if (Auth::tienePermiso('coros.editar')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#corista<?= (int) $corista['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('coros.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#coristaNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar
        </button>
        <?php endif; ?>

        <?php if (!$coros): ?>
        <p class="text-muted small mt-3 mb-0">
            Todavía no hay ningún coro creado, así que aquí no hay en qué misa marcar a nadie.
            <a href="<?= e(url_admin('coros')) ?>">Crea los coros</a> primero.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php
/*
 * Un modal por fila más el del alta, generados por la misma closure y fuera de
 * la tabla para no anidar formularios — igual que en Proclamadores.
 */
$dibujarModalCorista = static function (
    string $idModal,
    ?array $corista,
    string $csrf,
    array $personas,
    array $coros,
    array $sus
) {
    $vacio     = $corista === null;
    $personaId = $vacio ? 0 : (int) ($corista['persona_id'] ?? 0);
    $contacto  = $vacio ? '' : trim(
        (string) ($corista['telefono'] ?? '')
        . (($corista['telefono'] ?? '') && ($corista['email'] ?? '') ? ' · ' : '')
        . (string) ($corista['email'] ?? '')
    );
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'coros', 'corista_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $corista['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Agregar al catálogo' : 'Editar' ?></h2>
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
                               value="<?= e($vacio || $personaId ? '' : $corista['nombre']) ?>" maxlength="140">
                        <div class="form-text">Se ignora si arriba eliges a alguien del equipo.</div>
                    </div>
                    <?php if ($personaId): ?>
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
                               value="<?= e($vacio ? '' : (string) $corista['telefono']) ?>" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $corista['email']) ?>" maxlength="150">
                    </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold" for="voz<?= e($idModal) ?>">Voz</label>
                            <input type="text" name="voz" id="voz<?= e($idModal) ?>"
                                   class="form-control form-control-sm" maxlength="60"
                                   value="<?= e($vacio ? '' : (string) $corista['voz']) ?>"
                                   placeholder="Ej. soprano, tenor, segunda voz">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold" for="ins<?= e($idModal) ?>">Instrumento</label>
                            <input type="text" name="instrumento" id="ins<?= e($idModal) ?>"
                                   class="form-control form-control-sm" maxlength="60"
                                   value="<?= e($vacio ? '' : (string) $corista['instrumento']) ?>"
                                   placeholder="Ej. guitarra, teclado">
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                Las dos son opcionales y de texto libre: hay quien canta sin tocar y quien
                                toca sin cantar. A diferencia del nombre y el contacto, estas no vienen de
                                la ficha del equipo pastoral.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Canta en</label>
                        <?php if (!$coros): ?>
                        <p class="text-muted small mb-0">Todavía no hay ningún coro creado.</p>
                        <?php else: ?>
                        <?php foreach ($coros as $coroId => $coro): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="coros[]"
                                   value="<?= (int) $coroId ?>" id="co<?= e($idModal) ?>_<?= (int) $coroId ?>"
                                   <?= in_array((int) $coroId, $sus, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="co<?= e($idModal) ?>_<?= (int) $coroId ?>">
                                <?= e($coro['etiqueta']) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <div class="form-text">Puede cantar en más de una misa.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $corista['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio && Auth::tienePermiso('coros.eliminar')): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'coros', 'corista_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar del catálogo?');">
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

if (Auth::tienePermiso('coros.crear')) {
    $dibujarModalCorista('coristaNuevo', null, $csrf, $personas, $coros, []);
}
if (Auth::tienePermiso('coros.editar')) {
    foreach ($coristas as $corista) {
        $dibujarModalCorista(
            'corista' . (int) $corista['id'],
            $corista,
            $csrf,
            $personas,
            $coros,
            $susCoros[(int) $corista['id']] ?? []
        );
    }
}
?>
