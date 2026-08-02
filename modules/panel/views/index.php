<?php
/**
 * Panel — accesos rápidos según permiso, no una lista de etapas fija: así
 * nunca vuelve a quedar desactualizado según avance el proyecto (a
 * diferencia de la versión anterior, un texto "sitio en construcción" con
 * una lista de etapas hardcodeada desde la etapa 1, que nunca se actualizó).
 * Mismo cruce de permiso + pastoral que ya usa shared/views/parciales/admin_sidebar.php:
 * mesc.ver/catequesis.ver/lector.ver los llevan todos los coordinadores a
 * propósito (para que cada controlador decida el alcance real), así que la
 * tarjeta solo se ofrece si además se administra la pastoral de ese módulo.
 */
$secciones = [
    ['bloques',       'Textos del sitio',   'bi-file-richtext',       'bloques.ver'],
    ['paginas',       'Páginas',            'bi-file-earmark-text',  'paginas.ver'],
    ['horarios',      'Horarios',           'bi-clock',              'horarios.ver'],
    ['centros',       'Sede y centros',     'bi-buildings',          'centros.ver'],
    ['personas',      'Equipo pastoral',    'bi-person-badge',       'personas.ver'],
    ['organigrama',   'Organigrama',        'bi-diagram-3',          'organigrama.ver'],
    ['pastorales',    'Pastorales',         'bi-people',             'pastorales.ver'],
    ['mesc',          'MESC',               'bi-heart-pulse',        'mesc.ver'],
    ['catequesis',    'Catequesis',         'bi-book',               'catequesis.ver'],
    ['lector',        'Lectores',           'bi-mic',                'lector.ver'],
    ['sacramentos',   'Sacramentos',        null,                    'sacramentos.ver'],
    ['cursos',        'Cursos',             'bi-mortarboard',        'cursos.ver'],
    ['inscripciones', 'Inscripciones',      'bi-pencil-square',      'inscripciones.ver'],
    ['avisos',        'Avisos',             'bi-megaphone',          'avisos.ver'],
    ['eventos',       'Eventos',            'bi-calendar-event',     'eventos.ver'],
    ['galeria',       'Galería',            'bi-images',             'galeria.ver'],
    ['carrusel',      'Carrusel',           'bi-collection-play',    'carrusel.ver'],
    ['mensajes',      'Mensajes',           'bi-envelope',           'mensajes.ver'],
    ['configuracion', 'Configuración',      'bi-gear',               'configuracion.ver'],
    ['usuarios',      'Usuarios',           'bi-person-gear',        'usuarios.ver'],
    ['auditoria',     'Auditoría',          'bi-journal-text',       'auditoria.ver'],
    ['respaldos',     'Respaldos',          'bi-database-fill-gear', 'respaldos.ver'],
];
// Los tres módulos dedicados también exigen administrar la pastoral que les
// corresponde, no solo llevar el permiso — ver el comentario de arriba.
$pastoralPorModulo = [
    'mesc'       => PASTORAL_MESC,
    'catequesis' => PASTORAL_CATEQUESIS,
    'lector'     => PASTORAL_LECTOR,
];
$disponibles = array_values(array_filter(
    $secciones,
    static function (array $s) use ($pastoralPorModulo): bool {
        if (!Auth::tienePermiso($s[3])) {
            return false;
        }
        $slug = $pastoralPorModulo[$s[0]] ?? null;
        return $slug === null || Auth::administraPastoral($slug);
    }
));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Hola, <?= e($usuario['nombre']) ?></h1>
        <p class="text-muted mb-0 small"><?= e(Auth::nombreRol()) ?></p>
    </div>
    <a href="<?= e(url_publica('inicio')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-box-arrow-up-right me-1"></i>Ver el sitio
    </a>
</div>

<?php if ($cumpleanerosMes): ?>
<?php
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesActual = $meses[(int) date('n') - 1];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <h2 class="h6 fw-bold mb-3">
            <i class="bi bi-balloon-heart-fill text-dorado me-1"></i>Cumpleaños de <?= e($mesActual) ?>
        </h2>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($cumpleanerosMes as $persona): ?>
            <div class="d-flex align-items-center gap-2">
                <img src="<?= e(foto_o_avatar($persona['foto'], $persona['nombre'], 32)) ?>"
                     class="rounded-circle" style="width:28px;height:28px;object-fit:cover" alt="">
                <span class="small"><?= e($persona['nombre']) ?> <span class="text-muted">· día <?= (int) $persona['dia'] ?></span></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$disponibles): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">
            Tu cuenta todavía no tiene ninguna sección asignada. Pide a un administrador
            que te asigne una pastoral o revise tus permisos.
        </p>
    </div>
</div>
<?php else: ?>
<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3">
    <?php foreach ($disponibles as [$modulo, $etiqueta, $icono, ]): ?>
    <div class="col">
        <a href="<?= e(url_admin($modulo)) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body p-3 text-center">
                <div class="fs-3 text-dorado mb-2">
                    <?= $icono !== null ? '<i class="bi ' . e($icono) . '"></i>' : icono_cruz() ?>
                </div>
                <div class="small fw-semibold"><?= e($etiqueta) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($comisionesMenu || $sueltasMenu): ?>
<?php
/** Misma tarjeta que el grid de arriba, apuntando al panel básico de la pastoral. */
$dibujarTarjetaMenu = static function (array $pastoral): void {
    ?>
    <div class="col">
        <a href="<?= e(url_admin('pastorales', 'panel', ['id' => $pastoral['id']])) ?>"
           class="card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body p-3 text-center">
                <div class="fs-3 text-dorado mb-2"><i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?>"></i></div>
                <div class="small fw-semibold"><?= e($pastoral['nombre']) ?></div>
            </div>
        </a>
    </div>
    <?php
};
?>
<h2 class="h6 fw-bold text-uppercase text-muted mb-3 mt-4">Pastorales y comisiones</h2>

<?php foreach ($comisionesMenu as $grupo): ?>
<div class="mb-3">
    <div class="small fw-semibold text-muted mb-2">
        <i class="bi <?= e($grupo['padre']['icono'] ?: 'bi-people') ?> me-1"></i>
        <?php if ($grupo['padre']['visible_en_menu']): ?>
        <a href="<?= e(url_admin('pastorales', 'panel', ['id' => $grupo['padre']['id']])) ?>" class="text-decoration-none text-muted">
            <?= e($grupo['padre']['nombre']) ?>
        </a>
        <?php else: ?>
        <?= e($grupo['padre']['nombre']) ?>
        <?php endif; ?>
    </div>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3">
        <?php foreach ($grupo['hijas'] as $pastoral): ?>
        <?php $dibujarTarjetaMenu($pastoral); ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($sueltasMenu): ?>
<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3 mb-3">
    <?php foreach ($sueltasMenu as $pastoral): ?>
    <?php $dibujarTarjetaMenu($pastoral); ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
