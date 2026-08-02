<?php
/**
 * Agenda interna: el calendario de toda la parroquia, eventos y cursos, con lo
 * publicado y lo que sigue en borrador.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Agenda</h1>
        <p class="text-muted mb-0 small">
            Eventos y cursos de todas las pastorales y todas las sedes. Cada quien edita lo suyo; aquí se ve todo.
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::tienePermiso('eventos.crear')): ?>
        <a href="<?= e(url_admin('eventos', 'nuevo')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo evento
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('cursos.crear')): ?>
        <a href="<?= e(url_admin('cursos', 'nuevo')) ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo curso
        </a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="<?= e(url_admin('agenda')) ?>" class="row g-2 align-items-end mb-3">
    <?php if (!URLS_AMIGABLES): ?>
    <?php /* Sin URLs amigables la ruta va en la cadena de consulta, y un GET
             descarta la del action: hay que repetirla como campos. */ ?>
    <input type="hidden" name="area" value="admin">
    <input type="hidden" name="modulo" value="agenda">
    <?php endif; ?>
    <?php /* El filtro no debe mover el periodo que la persona tiene delante. */ ?>
    <input type="hidden" name="vista" value="<?= e($vista) ?>">
    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">

    <div class="col-auto">
        <label for="pastoral" class="form-label small fw-semibold mb-1">Pastoral</label>
        <select name="pastoral" id="pastoral" class="form-select form-select-sm"
                onchange="this.form.submit()">
            <option value="">Todas las pastorales</option>
            <?php if ($tieneAlcance): ?>
            <option value="mias" <?= $filtro === 'mias' ? 'selected' : '' ?>>Solo las mías</option>
            <?php endif; ?>
            <?php foreach ($pastorales as $unaPastoral): ?>
            <option value="<?= (int) $unaPastoral['id'] ?>"
                    <?= $filtro === (string) $unaPastoral['id'] ? 'selected' : '' ?>>
                <?= e($unaPastoral['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (count($centros) > 1): ?>
    <div class="col-auto">
        <label for="centro" class="form-label small fw-semibold mb-1">Sede</label>
        <select name="centro" id="centro" class="form-select form-select-sm"
                onchange="this.form.submit()">
            <option value="">Todas las sedes</option>
            <?php foreach ($centros as $unCentro): ?>
            <option value="<?= (int) $unCentro['id'] ?>"
                    <?= $filtroCentro === (string) $unCentro['id'] ? 'selected' : '' ?>>
                <?= e($unCentro['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-auto">
        <?php /* El select se envía solo con JavaScript; sin él, este botón. */ ?>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
    </div>
    <?php if ($filtro !== '' || $filtroCentro !== ''): ?>
    <div class="col-auto">
        <a href="<?= e(url_admin('agenda', '', ['vista' => $vista, 'fecha' => $fecha])) ?>"
           class="btn btn-sm btn-outline-secondary">Quitar</a>
    </div>
    <?php endif; ?>

    <div class="col-auto ms-auto">
        <span class="badge bg-primary-subtle text-primary-emphasis me-1">
            <i class="bi bi-calendar-event me-1"></i>Eventos
        </span>
        <span class="badge bg-warning-subtle text-warning-emphasis">
            <i class="bi bi-mortarboard me-1"></i>Cursos
        </span>
    </div>
</form>

<?php if ($vista !== 'anio'): ?>
<?php /* Filtro visual, no de servidor: alterna qué se ve sin recargar, porque
         a diferencia de pastoral/sede no hace falta ir y volver al servidor
         para ocultar temporalmente los borradores mientras se revisa la
         semana. La vista de año no tiene items sueltos que ocultar —cada
         casilla es un punto agregado por día—, así que aquí no aplica. */ ?>
<div class="d-flex align-items-center gap-3 mb-3 small">
    <span class="fw-semibold text-muted">Mostrar:</span>
    <div class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" id="verPublicados" checked>
        <label class="form-check-label" for="verPublicados">
            <span class="badge bg-success-subtle text-success-emphasis">Publicados</span>
        </label>
    </div>
    <div class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" id="verBorradores" checked>
        <label class="form-check-label" for="verBorradores">
            <span class="badge bg-warning-subtle text-warning-emphasis">Borradores</span>
        </label>
    </div>
</div>
<script>
(function () {
    'use strict';
    var casillaPublicados = document.getElementById('verPublicados');
    var casillaBorradores = document.getElementById('verBorradores');
    if (!casillaPublicados || !casillaBorradores) { return; }

    function aplicar() {
        document.querySelectorAll('.evento-punto, .evento-linea').forEach(function (item) {
            var esBorrador = item.classList.contains('es-borrador');
            var visible = esBorrador ? casillaBorradores.checked : casillaPublicados.checked;
            item.classList.toggle('oculto-por-filtro', !visible);
        });
    }
    casillaPublicados.addEventListener('change', aplicar);
    casillaBorradores.addEventListener('change', aplicar);
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/modules/agenda/views/calendario.php'; ?>

<?php if ($sinFechas): ?>
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-2">
            <i class="bi bi-question-circle text-warning me-1"></i>Cursos sin fechas
        </h2>
        <p class="small text-muted mb-2">
            No aparecen en el calendario porque todavía no tienen fecha de inicio.
        </p>
        <ul class="list-unstyled mb-0 small">
            <?php foreach ($sinFechas as $curso): ?>
            <li class="mb-1">
                <?php $puedeEditar = Auth::tienePermiso('cursos.editar')
                    && Auth::puedeSobrePastoral($curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null)
                    && Auth::puedeSobreCentro($curso['centro_id'] !== null ? (int) $curso['centro_id'] : null); ?>
                <?php if ($puedeEditar): ?>
                <a href="<?= e(url_admin('cursos', 'editar', ['id' => $curso['id']])) ?>"><?= e($curso['titulo']) ?></a>
                <?php else: ?>
                <span class="fw-semibold"><?= e($curso['titulo']) ?></span>
                <?php endif; ?>
                <span class="badge bg-light text-secondary border"><?= e($curso['pastoral_nombre'] ?? 'General') ?></span>
                <?php if ($curso['centro_nombre']): ?>
                <span class="badge bg-light text-secondary border"><i class="bi bi-geo-alt me-1"></i><?= e($curso['centro_nombre']) ?></span>
                <?php endif; ?>
                <?php if (!$curso['publicado']): ?>
                <?= badge_escalon($curso) ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
