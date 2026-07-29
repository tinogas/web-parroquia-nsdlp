<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Hola, <?= e($usuario['nombre']) ?></h1>
        <p class="text-muted mb-0 small"><?= e(Auth::nombreRol()) ?></p>
    </div>
    <a href="<?= e(url_publica('inicio')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-box-arrow-up-right me-1"></i>Ver el sitio
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">
            <i class="bi bi-tools text-warning me-1"></i>Sitio en construcción
        </h2>
        <p class="text-muted mb-3">
            Los cimientos están listos: acceso al panel, enrutado del sitio público y
            base de datos instalada. Las secciones de administración se irán activando
            por etapas.
        </p>

        <ol class="lista-etapas small mb-0">
            <li class="hecha">Documentación del proyecto</li>
            <li class="hecha">Andamiaje: núcleo, enrutado e instalador</li>
            <li>Configuración de la parroquia y bloques de contenido</li>
            <li>Sitio público: inicio, quiénes somos y contacto</li>
            <li>Horarios, equipo pastoral y organigrama</li>
            <li>Avisos, eventos, galería y carrusel</li>
            <li>Pastorales</li>
            <li>Sacramentos</li>
            <li>Cursos e inscripciones</li>
            <li>Usuarios, roles y auditoría</li>
            <li>Posicionamiento y publicación</li>
        </ol>
    </div>
</div>
