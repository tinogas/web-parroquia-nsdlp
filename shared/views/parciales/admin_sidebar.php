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

        <?php /*
        <a href="<?= e(url_admin('avisos')) ?>"   class="sidebar-link <?= $activo('avisos') ?>"><i class="bi bi-megaphone"></i> Avisos</a>
        <a href="<?= e(url_admin('eventos')) ?>"  class="sidebar-link <?= $activo('eventos') ?>"><i class="bi bi-calendar-event"></i> Eventos</a>
        <a href="<?= e(url_admin('galeria')) ?>"  class="sidebar-link <?= $activo('galeria') ?>"><i class="bi bi-images"></i> Galería</a>
        */ ?>

        <?php if (Auth::tienePermiso('horarios.ver') || Auth::tienePermiso('personas.ver') || Auth::tienePermiso('organigrama.ver')): ?>
        <div class="sidebar-section mt-2">Parroquia</div>
        <?php endif; ?>
        <?php if (Auth::tienePermiso('horarios.ver')): ?>
        <a href="<?= e(url_admin('horarios')) ?>" class="sidebar-link <?= $activo('horarios') ?>">
            <i class="bi bi-clock"></i> Horarios
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
        <?php /*
        <a href="<?= e(url_admin('pastorales')) ?>"  class="sidebar-link <?= $activo('pastorales') ?>"><i class="bi bi-people"></i> Pastorales</a>

        <div class="sidebar-section mt-2">Trámites</div>
        <a href="<?= e(url_admin('solicitudes')) ?>"   class="sidebar-link <?= $activo('solicitudes') ?>"><i class="bi bi-file-earmark-text"></i> Solicitudes</a>
        <a href="<?= e(url_admin('inscripciones')) ?>" class="sidebar-link <?= $activo('inscripciones') ?>"><i class="bi bi-pencil-square"></i> Inscripciones</a>
        */ ?>

        <?php if (Auth::tienePermiso('mensajes.ver')): ?>
        <div class="sidebar-section mt-2">Comunicación</div>
        <a href="<?= e(url_admin('mensajes')) ?>" class="sidebar-link <?= $activo('mensajes') ?>">
            <i class="bi bi-envelope"></i> Mensajes
        </a>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('configuracion.ver')): ?>
        <div class="sidebar-section mt-2">Administración</div>
        <a href="<?= e(url_admin('configuracion')) ?>" class="sidebar-link <?= $activo('configuracion') ?>">
            <i class="bi bi-gear"></i> Configuración
        </a>
        <?php /*
        <a href="<?= e(url_admin('usuarios')) ?>"  class="sidebar-link <?= $activo('usuarios') ?>"><i class="bi bi-person-gear"></i> Usuarios</a>
        <a href="<?= e(url_admin('auditoria')) ?>" class="sidebar-link <?= $activo('auditoria') ?>"><i class="bi bi-journal-text"></i> Auditoría</a>
        */ ?>
        <?php endif; ?>

    </nav>
</div>
