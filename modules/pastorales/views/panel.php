<?php
/**
 * Panel básico de una pastoral: lo mínimo que necesita cualquier pastoral
 * para operar (avisos, calendario de eventos, cursos, documentos), sin
 * necesitar un módulo dedicado como MESC/Catequesis/Proclamadores. Avisos, Eventos
 * y Cursos son genéricos por pastoral_id —aquí solo se enlaza a ellos ya
 * filtrados, no se duplica su CRUD—; Documentos se gestiona en este mismo
 * Controller (PastoralController::documentoGuardar()/documentoEliminar()).
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('pastorales')) ?>" class="text-decoration-none">Pastorales</a>
                </li>
                <?php if ($comisionPadre): ?>
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('pastorales', 'panel', ['id' => $comisionPadre['id']])) ?>" class="text-decoration-none">
                        <?= e($comisionPadre['nombre']) ?>
                    </a>
                </li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($pastoral['nombre']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?> text-dorado me-1"></i><?= e($pastoral['nombre']) ?>
        </h1>
        <?php /* Quién coordina, aquí donde se mira. Es lo primero que se pregunta al entrar
                 a una pastoral y hasta ahora solo se veía abriendo "Editar pastoral", que es
                 donde se sigue cambiando: puede ser una persona o una pareja, y el nombre —de
                 las dos fichas, si son dos— ya viene escrito de ahí. */ ?>
        <?php if ($pastoral['responsable_nombre']): ?>
        <p class="text-muted small mb-0 mt-1">
            <i class="bi <?= $pastoral['responsable_pareja_id'] ? 'bi-heart-fill text-danger' : 'bi-person' ?> me-1"></i>
            Coordina <?= e($pastoral['responsable_nombre']) ?>
        </p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($puedeEditar): ?>
        <a href="<?= e(url_admin('pastorales', 'editar', ['id' => $pastoral['id']])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Editar pastoral
        </a>
        <?php endif; ?>
        <a href="<?= e(url_admin('pastorales')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if ($moduloDedicado): ?>
<div class="alert alert-light border d-flex align-items-center justify-content-between gap-2 mb-4">
    <span><i class="bi bi-info-circle me-1"></i>Esta pastoral opera además con su módulo propio de turnos y catálogo.</span>
    <a href="<?= e(url_admin($moduloDedicado)) ?>" class="btn btn-sm btn-primary">
        Ir a turnos y catálogo<i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>
<?php endif; ?>

<?php
/** Tarjeta de acceso al panel básico: el cuerpo va al listado ya filtrado; el "+" a "nuevo" ya preseleccionado. */
$dibujarAccesoBasico = static function (string $icono, string $titulo, string $subtitulo, string $modulo, int $pastoralId, bool $puedeCrear): void {
    ?>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
                <a href="<?= e(url_admin($modulo, '', ['pastoral' => $pastoralId])) ?>"
                   class="text-decoration-none d-flex align-items-center gap-2 flex-grow-1">
                    <i class="bi <?= e($icono) ?> fs-3 text-dorado"></i>
                    <div>
                        <div class="fw-semibold text-body"><?= e($titulo) ?></div>
                        <div class="small text-muted"><?= e($subtitulo) ?></div>
                    </div>
                </a>
                <?php if ($puedeCrear): ?>
                <a href="<?= e(url_admin($modulo, 'nuevo', ['pastoral_id' => $pastoralId])) ?>"
                   class="btn btn-sm btn-outline-primary" title="Nuevo">
                    <i class="bi bi-plus-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};
?>

<div class="row g-3 mb-4">
    <?php $dibujarAccesoBasico('bi-megaphone', 'Avisos', 'Publicar y ver los suyos', 'avisos', (int) $pastoral['id'], Auth::tienePermiso('avisos.crear')); ?>
    <?php $dibujarAccesoBasico('bi-calendar-event', 'Eventos', 'Su calendario', 'eventos', (int) $pastoral['id'], Auth::tienePermiso('eventos.crear')); ?>
    <?php $dibujarAccesoBasico('bi-mortarboard', 'Cursos', 'Los suyos', 'cursos', (int) $pastoral['id'], Auth::tienePermiso('cursos.crear')); ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Documentos descargables</h2>

        <?php if (!$documentos): ?>
        <p class="text-muted small mb-3">Todavía no hay documentos.</p>
        <?php else: ?>
        <ul class="list-group list-group-flush mb-3">
            <?php foreach ($documentos as $documento): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <a href="<?= e(url_activo($documento['archivo'])) ?>" target="_blank" class="text-decoration-none">
                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i><?= e($documento['titulo']) ?>
                </a>
                <form method="POST" accept-charset="UTF-8"
                      action="<?= e(url_post('admin', 'pastorales', 'documentoEliminar')) ?>" class="m-0"
                      onsubmit="return confirm('¿Eliminar este documento?');">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $documento['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                data-bs-target="#documentoNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar documento
        </button>
    </div>
</div>

<?php
/* Quiénes están en la pastoral, al final: es la respuesta a "¿y quién es de
   aquí?", que antes había que ir a buscar al Equipo pastoral filtrando por
   pastoral. La pertenencia NO se edita aquí —vive en el checklist de cada
   ficha, que es su única fuente— y por eso lo que hay es un botón a la ficha
   y no un formulario: dos sitios para marcar lo mismo es como se acaba con
   una persona en dos pastorales por descuido.

   Se muestran también las inactivas, con su etiqueta, igual que en el módulo
   de Equipo pastoral: quien dejó el cargo sigue contando para el historial y
   esconderla haría pensar que se borró. */
?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0">
                Quiénes están en esta pastoral
                <?php if ($personas): ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal"><?= count($personas) ?></span>
                <?php endif; ?>
            </h2>
            <?php if ($puedeEditarPersonas): ?>
            <a href="<?= e(url_admin('personas', '', ['pastoral' => $pastoral['id']])) ?>"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-person-badge me-1"></i>Ver en Equipo pastoral
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$personas): ?>
        <p class="text-muted small mb-0">
            <?php if ($agrupaOtras): ?>
            Nadie está marcado en esta Comisión, y es lo normal: su gente suele estar marcada en las
            pastorales que agrupa, no en ella misma.
            <?php else: ?>
            Todavía nadie está marcado en esta pastoral. Se marca desde la ficha de cada persona,
            en <strong>Equipo pastoral</strong>.
            <?php endif; ?>
        </p>
        <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($personas as $persona): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0">
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e(foto_o_avatar($persona['foto'], $persona['nombre'], 40)) ?>"
                         class="rounded-circle" style="width:32px;height:32px;object-fit:cover" alt="">
                    <div>
                        <div class="fw-semibold <?= $persona['activo'] ? '' : 'text-muted' ?>">
                            <?= e($persona['nombre']) ?>
                            <?php if (!$persona['activo']): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($persona['cargo']): ?>
                        <div class="text-muted small"><?= e($persona['cargo']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($persona['pastorales_coordina'])): ?>
                        <div class="small text-dorado">
                            <i class="bi bi-star-fill me-1"></i>Coordina <?= e($persona['pastorales_coordina']) ?>
                        </div>
                        <?php endif; ?>
                        <?php /* Con quién está, si está con alguien. Es un dato de la
                                 parroquia —la pareja se marca en su ficha, no aquí— y por
                                 eso se ve en cualquier pastoral, no solo en las de familia.
                                 Quien no tenga a nadie no muestra nada: participar solo es
                                 normal, no un registro a medias. */ ?>
                        <?php if (!empty($persona['pareja_nombre'])): ?>
                        <div class="small text-muted">
                            <i class="bi bi-heart-fill text-danger me-1"></i>Con <?= e($persona['pareja_nombre']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($puedeEditarPersonas): ?>
                <a href="<?= e(url_admin('personas', 'editar', ['id' => $persona['id']])) ?>"
                   class="btn btn-sm btn-outline-primary" title="Editar su ficha">
                    <i class="bi bi-pencil"></i>
                </a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($puedeEditarPersonas): ?>
        <p class="form-text mb-0 mt-3">
            Quién pertenece a esta pastoral se marca en la ficha de cada persona, no aquí.
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="documentoNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" accept-charset="UTF-8" enctype="multipart/form-data"
              action="<?= e(url_post('admin', 'pastorales', 'documentoGuardar')) ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="pastoral_id" value="<?= (int) $pastoral['id'] ?>">

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
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Agregar</button>
            </div>
        </form>
    </div>
</div>
