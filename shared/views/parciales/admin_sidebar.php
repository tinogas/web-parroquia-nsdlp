<?php
/**
 * Menú lateral del panel. Cada entrada se muestra solo si el usuario tiene el
 * permiso correspondiente, así que un coordinador ve un menú corto y un
 * administrador lo ve completo.
 *
 * Las secciones comentadas se activan conforme avanzan las etapas del plan.
 */
$moduloActual = $_GET['modulo'] ?? 'panel';

/** Marca el enlace activo. */
$activo = static fn (string $modulo): string => $moduloActual === $modulo ? 'active' : '';
?>
<div id="sidebar" class="sidebar">
    <nav class="sidebar-nav">

        <a href="<?= e(url_admin('panel')) ?>" class="sidebar-link <?= $activo('panel') ?>">
            <i class="bi bi-speedometer2"></i> Panel
        </a>

        <?php /* La agenda va suelta arriba, sin sección: es transversal —eventos y
                 cursos de todas las pastorales— y para los roles de consulta es lo
                 único que verán además de su propio módulo. */ ?>
        <?php if (Auth::tienePermiso('agenda.ver')): ?>
        <a href="<?= e(url_admin('agenda')) ?>" class="sidebar-link <?= $activo('agenda') ?>">
            <i class="bi bi-calendar3"></i> Agenda
        </a>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('bloques.ver') || Auth::tienePermiso('paginas.ver')): ?>
        <div class="sidebar-section mt-2">Contenido</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('bloques.ver')): ?>
        <a href="<?= e(url_admin('bloques')) ?>" class="sidebar-link <?= $activo('bloques') ?>">
            <i class="bi bi-file-richtext"></i> Textos del sitio
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('paginas.ver')): ?>
        <a href="<?= e(url_admin('paginas')) ?>" class="sidebar-link <?= $activo('paginas') ?>">
            <i class="bi bi-file-earmark-text"></i> Páginas
        </a>
        <?php endif; ?>

        <?php
        /* Las pastorales con módulo propio, en su propia sección más abajo. La
           lista crece por aquí y por ningún otro lado: agregar una entrada es
           todo lo que hace falta para que el módulo nuevo aparezca en el menú.

           Cada una se ofrece a quien administra ESA pastoral, no a quien lleva
           el permiso: `mesc.*` lo tienen todos los coordinadores desde que el
           rol dejó de nombrar la pastoral, y un enlace que acaba en «no
           administras la pastoral de MESC» es peor que no tener enlace. */
        $modulosDePastoral = array_values(array_filter([
            ['mesc',          'MESC',          'bi-heart-pulse', 'mesc.ver',          PASTORAL_MESC],
            ['catequesis',    'Catequesis',    'bi-book',        'catequesis.ver',    PASTORAL_CATEQUESIS],
            ['proclamadores', 'Proclamadores', 'bi-mic',         'proclamadores.ver', PASTORAL_PROCLAMADORES],
        ], static fn (array $m): bool => Auth::tienePermiso($m[3]) && Auth::administraPastoral($m[4])));
        ?>
        <?php if (Auth::tienePermiso('horarios.ver') || Auth::tienePermiso('centros.ver') || Auth::tienePermiso('personas.ver')
                || Auth::tienePermiso('organigrama.ver') || Auth::tienePermiso('pastorales.ver')): ?>
        <div class="sidebar-section mt-2">Parroquia</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('horarios.ver')): ?>
        <a href="<?= e(url_admin('horarios')) ?>" class="sidebar-link <?= $activo('horarios') ?>">
            <i class="bi bi-clock"></i> Horarios
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('centros.ver')): ?>
        <a href="<?= e(url_admin('centros')) ?>" class="sidebar-link <?= $activo('centros') ?>">
            <i class="bi bi-buildings"></i> Sede y centros
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('personas.ver')): ?>
        <a href="<?= e(url_admin('personas')) ?>" class="sidebar-link <?= $activo('personas') ?>">
            <i class="bi bi-person-badge"></i> Equipo pastoral
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('organigrama.ver')): ?>
        <a href="<?= e(url_admin('organigrama')) ?>" class="sidebar-link <?= $activo('organigrama') ?>">
            <i class="bi bi-diagram-3"></i> Organigrama
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('pastorales.ver')): ?>
        <a href="<?= e(url_admin('pastorales')) ?>" class="sidebar-link <?= $activo('pastorales') ?>">
            <i class="bi bi-people"></i> Pastorales
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('sacramentos.ver')): ?>
        <a href="<?= e(url_admin('sacramentos')) ?>" class="sidebar-link <?= $activo('sacramentos') ?>">
            <i><?= icono_cruz() ?></i> Sacramentos
        </a>
        <?php endif; ?>

        <?php /* Sección aparte y no mezcladas con el resto de "Parroquia": estos
                 módulos no administran la parroquia entera sino una pastoral
                 concreta, y van a ser varios. Quien no administre ninguna de
                 esas pastorales no ve ni la sección. El enlace general a
                 "Pastorales" —el catálogo de todas— se queda arriba, con
                 Equipo pastoral y Organigrama, porque eso sí es de la
                 parroquia entera. */ ?>
        <?php if ($modulosDePastoral): ?>
        <div class="sidebar-section mt-2">Pastorales</div>
        <?php foreach ($modulosDePastoral as [$modulo, $etiqueta, $icono]): ?>
        <a href="<?= e(url_admin($modulo)) ?>" class="sidebar-link <?= $activo($modulo) ?>">
            <i class="bi <?= e($icono) ?>"></i> <?= e($etiqueta) ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('inscripciones.ver')): ?>
        <div class="sidebar-section mt-2">Trámites</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('inscripciones.ver')): ?>
        <a href="<?= e(url_admin('inscripciones')) ?>" class="sidebar-link <?= $activo('inscripciones') ?>">
            <i class="bi bi-pencil-square"></i> Inscripciones
        </a>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('avisos.ver') || Auth::tienePermiso('eventos.ver')
                || Auth::tienePermiso('cursos.ver') || Auth::tienePermiso('galeria.ver')
                || Auth::tienePermiso('carrusel.ver') || Auth::tienePermiso('mensajes.ver')): ?>
        <div class="sidebar-section mt-2">Comunicación</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('avisos.ver')): ?>
        <a href="<?= e(url_admin('avisos')) ?>" class="sidebar-link <?= $activo('avisos') ?>">
            <i class="bi bi-megaphone"></i> Avisos
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('eventos.ver')): ?>
        <a href="<?= e(url_admin('eventos')) ?>" class="sidebar-link <?= $activo('eventos') ?>">
            <i class="bi bi-calendar-event"></i> Eventos
        </a>
        <?php endif; ?>
        <?php /* Cursos va aquí y no en "Parroquia": es contenido que se publica y
                 se anuncia, del mismo grupo que los avisos y los eventos —los tres
                 se administran por pastoral y se publican en dos escalones—, y no
                 un catálogo de la parroquia como los horarios o las sedes. Las
                 inscripciones que llegan a un curso sí son un trámite y siguen en
                 su propia sección. */ ?>
        <?php if (Auth::tienePermiso('cursos.ver')): ?>
        <a href="<?= e(url_admin('cursos')) ?>" class="sidebar-link <?= $activo('cursos') ?>">
            <i class="bi bi-mortarboard"></i> Cursos
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('galeria.ver')): ?>
        <a href="<?= e(url_admin('galeria')) ?>" class="sidebar-link <?= $activo('galeria') ?>">
            <i class="bi bi-images"></i> Galería
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('carrusel.ver')): ?>
        <a href="<?= e(url_admin('carrusel')) ?>" class="sidebar-link <?= $activo('carrusel') ?>">
            <i class="bi bi-collection-play"></i> Carrusel
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('mensajes.ver')): ?>
        <a href="<?= e(url_admin('mensajes')) ?>" class="sidebar-link <?= $activo('mensajes') ?>">
            <i class="bi bi-envelope"></i> Mensajes
            <?php /* Mismo contador que la campana de la barra de arriba, que ya lo
                     calculó: layout_admin.php deja $mensajesSinLeer antes de
                     incluir este archivo, así que aquí no se vuelve a consultar.
                     Con empty() y no isset() a propósito: si alguien incluye este
                     menú desde otro sitio, sin la variable, la insignia
                     simplemente no se dibuja. */ ?>
            <?php if (!empty($mensajesSinLeer)): ?>
            <span class="badge rounded-pill bg-danger ms-auto">
                <?= $mensajesSinLeer > 99 ? '99+' : (int) $mensajesSinLeer ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('configuracion.ver') || Auth::tienePermiso('usuarios.ver')
                || Auth::tienePermiso('auditoria.ver') || Auth::tienePermiso('respaldos.ver')): ?>
        <div class="sidebar-section mt-2">Administración</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('configuracion.ver')): ?>
        <a href="<?= e(url_admin('configuracion')) ?>" class="sidebar-link <?= $activo('configuracion') ?>">
            <i class="bi bi-gear"></i> Configuración
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('usuarios.ver')): ?>
        <a href="<?= e(url_admin('usuarios')) ?>" class="sidebar-link <?= $activo('usuarios') ?>">
            <i class="bi bi-person-gear"></i> Usuarios
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('auditoria.ver')): ?>
        <a href="<?= e(url_admin('auditoria')) ?>" class="sidebar-link <?= $activo('auditoria') ?>">
            <i class="bi bi-journal-text"></i> Auditoría
        </a>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('respaldos.ver')): ?>
        <a href="<?= e(url_admin('respaldos')) ?>" class="sidebar-link <?= $activo('respaldos') ?>">
            <i class="bi bi-database-fill-gear"></i> Respaldos
        </a>
        <?php endif; ?>

    </nav>
</div>
