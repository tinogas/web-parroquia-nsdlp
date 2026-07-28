<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Inscripciones a cursos</h1>
        <p class="text-muted mb-0 small">Contienen datos personales. Cada consulta queda registrada.</p>
    </div>
    <?php if (Auth::tienePermiso('inscripciones.exportar')): ?>
    <a href="<?= e(url_admin('inscripciones', 'exportar', array_filter([
            'curso_id' => $cursoId,
            'estado'   => $estado !== 'todos' ? $estado : null,
        ]))) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i>Exportar CSV
    </a>
    <?php endif; ?>
</div>

<div class="row g-2 mb-3">
    <div class="col-auto">
        <select class="form-select form-select-sm" onchange="location.href=this.value">
            <option value="<?= e(url_admin('inscripciones', '', ['estado' => $estado !== 'todos' ? $estado : ''])) ?>"
                <?= !$cursoId ? 'selected' : '' ?>>Todos los cursos</option>
            <?php foreach ($cursos as $curso): ?>
            <option value="<?= e(url_admin('inscripciones', '', array_filter(['curso_id' => $curso['id'], 'estado' => $estado !== 'todos' ? $estado : null]))) ?>"
                <?= $cursoId === (int) $curso['id'] ? 'selected' : '' ?>>
                <?= e($curso['titulo']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?= e(url_admin('inscripciones', '', array_filter(['curso_id' => $cursoId]))) ?>"
               class="btn <?= $estado === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todas</a>
            <?php foreach (InscripcionCursoModel::ESTADOS as $valor => $etiqueta): ?>
            <a href="<?= e(url_admin('inscripciones', '', array_filter(['curso_id' => $cursoId, 'estado' => $valor]))) ?>"
               class="btn <?= $estado === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e($etiqueta) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-pencil-square"></i></div>
            <p class="text-muted mb-0">No hay inscripciones que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Folio</th>
                    <th>Nombre</th>
                    <th class="d-none d-md-table-cell">Curso</th>
                    <th>Estado</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $inscripcion): ?>
                <tr>
                    <td class="font-monospace small"><?= e($inscripcion['folio']) ?></td>
                    <td>
                        <?= e($inscripcion['nombre']) ?>
                        <?php if ($inscripcion['es_menor']): ?>
                        <span class="badge bg-info-subtle text-info-emphasis">Menor</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e($inscripcion['curso_titulo']) ?></td>
                    <td>
                        <?php
                        $clase = match ($inscripcion['estado']) {
                            'confirmada'   => 'bg-success-subtle text-success-emphasis',
                            'cancelada'    => 'bg-secondary-subtle text-secondary-emphasis',
                            'lista_espera' => 'bg-warning-subtle text-warning-emphasis',
                            default        => 'bg-info-subtle text-info-emphasis',
                        };
                        ?>
                        <span class="badge <?= $clase ?>"><?= e(InscripcionCursoModel::ESTADOS[$inscripcion['estado']]) ?></span>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url_admin('inscripciones', 'ver', ['id' => $inscripcion['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$paginacion = $listado;
$paginaBase = url_admin('inscripciones', '', array_filter([
    'curso_id' => $cursoId,
    'estado'   => $estado !== 'todos' ? $estado : null,
]));
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
