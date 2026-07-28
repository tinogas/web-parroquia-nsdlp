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

        <?php /*
        <div class="sidebar-section mt-2">Contenido</div>
        <a href="<?= e(url_admin('avisos')) ?>"   class="sidebar-link <?= $activo('avisos') ?>"><i class="bi bi-megaphone"></i> Avisos</a>
        <a href="<?= e(url_admin('eventos')) ?>"  class="sidebar-link <?= $activo('eventos') ?>"><i class="bi bi-calendar-event"></i> Eventos</a>
        <a href="<?= e(url_admin('galeria')) ?>"  class="sidebar-link <?= $activo('galeria') ?>"><i class="bi bi-images"></i> Galería</a>

        <div class="sidebar-section mt-2">Parroquia</div>
        <a href="<?= e(url_admin('horarios')) ?>"    class="sidebar-link <?= $activo('horarios') ?>"><i class="bi bi-clock"></i> Horarios</a>
        <a href="<?= e(url_admin('pastorales')) ?>"  class="sidebar-link <?= $activo('pastorales') ?>"><i class="bi bi-people"></i> Pastorales</a>
        <a href="<?= e(url_admin('personas')) ?>"    class="sidebar-link <?= $activo('personas') ?>"><i class="bi bi-person-badge"></i> Equipo pastoral</a>

        <div class="sidebar-section mt-2">Trámites</div>
        <a href="<?= e(url_admin('solicitudes')) ?>"   class="sidebar-link <?= $activo('solicitudes') ?>"><i class="bi bi-file-earmark-text"></i> Solicitudes</a>
        <a href="<?= e(url_admin('inscripciones')) ?>" class="sidebar-link <?= $activo('inscripciones') ?>"><i class="bi bi-pencil-square"></i> Inscripciones</a>
        <a href="<?= e(url_admin('mensajes')) ?>"      class="sidebar-link <?= $activo('mensajes') ?>"><i class="bi bi-envelope"></i> Mensajes</a>
        */ ?>

        <?php if (Auth::esAdmin()): ?>
        <div class="sidebar-section mt-2">Administración</div>
        <?php /*
        <a href="<?= e(url_admin('usuarios')) ?>"      class="sidebar-link <?= $activo('usuarios') ?>"><i class="bi bi-person-gear"></i> Usuarios</a>
        <a href="<?= e(url_admin('configuracion')) ?>" class="sidebar-link <?= $activo('configuracion') ?>"><i class="bi bi-gear"></i> Configuración</a>
        <a href="<?= e(url_admin('auditoria')) ?>"     class="sidebar-link <?= $activo('auditoria') ?>"><i class="bi bi-journal-text"></i> Auditoría</a>
        */ ?>
        <span class="sidebar-link text-white-50 small fst-italic">
            <i class="bi bi-hourglass-split"></i> En construcción
        </span>
        <?php endif; ?>

    </nav>
</div>
