<?php
/**
 * Eventos en calendario: la misma cuadrícula del sitio, con los borradores a
 * la vista (borde punteado) y filtros de estado, pastoral y sede. Las vistas
 * de día, semana, mes y año son las de la Agenda (modules/agenda/views/),
 * con $calRuta apuntando aquí. Ver EventoController::calendario().
 */
$pestana   = 'calendario';
$subtitulo = 'Todos los eventos de la parroquia, publicados y en borrador. Cada quien edita lo suyo; aquí se ve todo.';
require __DIR__ . '/_pestanas.php';

$ESTADOS = ['todos' => 'Todos', 'publicados' => 'Publicados', 'borradores' => 'Borradores'];
$sinEstado = array_diff_key($comunes, ['estado' => true]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por estado">
        <?php foreach ($ESTADOS as $clave => $etiqueta): ?>
        <a href="<?= e(url_admin('eventos', 'calendario',
                     ['vista' => $vista, 'fecha' => $fecha] + ($clave !== 'todos' ? ['estado' => $clave] : []) + $sinEstado)) ?>"
           class="btn <?= $estado === $clave ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e($etiqueta) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="<?= e(url_admin('eventos', 'calendario')) ?>" class="row g-2 align-items-end">
        <?php if (!URLS_AMIGABLES): ?>
        <input type="hidden" name="area" value="admin">
        <input type="hidden" name="modulo" value="eventos">
        <input type="hidden" name="accion" value="calendario">
        <?php endif; ?>
        <?php /* El filtro no debe mover el periodo que la persona tiene delante. */ ?>
        <input type="hidden" name="vista" value="<?= e($vista) ?>">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <?php if ($estado !== 'todos'): ?>
        <input type="hidden" name="estado" value="<?= e($estado) ?>">
        <?php endif; ?>
        <div class="col-auto">
            <label for="pastoral" class="form-label small fw-semibold mb-1">Pastoral</label>
            <select name="pastoral" id="pastoral" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todas las pastorales</option>
                <?php if ($tieneAlcance): ?>
                <option value="mias" <?= $filtroPastoral === 'mias' ? 'selected' : '' ?>>Solo las mías</option>
                <?php endif; ?>
                <?php foreach ($pastorales as $unaPastoral): ?>
                <option value="<?= (int) $unaPastoral['id'] ?>"
                        <?= $filtroPastoral === (string) $unaPastoral['id'] ? 'selected' : '' ?>>
                    <?= e($unaPastoral['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($centros) > 1): ?>
        <div class="col-auto">
            <label for="centro" class="form-label small fw-semibold mb-1">Sede</label>
            <select name="centro" id="centro" class="form-select form-select-sm" onchange="this.form.submit()">
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
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
        </div>
        <?php if ($filtroPastoral !== '' || $filtroCentro !== ''): ?>
        <div class="col-auto">
            <a href="<?= e(url_admin('eventos', 'calendario',
                         ['vista' => $vista, 'fecha' => $fecha] + ($estado !== 'todos' ? ['estado' => $estado] : []))) ?>"
               class="btn btn-sm btn-outline-secondary">Quitar</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<p class="small text-muted mb-3">
    <span class="evento-punto d-inline-block" style="background:#1e4d8b">Publicado</span>
    <span class="evento-punto es-borrador d-inline-block" style="background:#1e4d8b">Borrador</span>
    <span class="ms-1">Los borradores van con el borde punteado. Al pasar el ratón se ve la pastoral.</span>
</p>

<?php require BASE_PATH . '/modules/agenda/views/calendario.php'; ?>
