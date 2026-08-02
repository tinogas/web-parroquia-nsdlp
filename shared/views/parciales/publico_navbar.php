<?php
/**
 * Menú del sitio.
 *
 * Declara todas las secciones previstas, pero solo dibuja las que ya tienen
 * módulo publicado (Router::existeRutaPublica). Así el menú se completa solo
 * conforme avanzan las etapas, sin dejar enlaces rotos mientras tanto.
 *
 * 'cursos' además respeta configuracion.cursos_activo: a diferencia de
 * existeRutaPublica (un interruptor de código, permanente una vez conectado
 * el módulo), este es un interruptor del panel (Configuración → Secciones
 * del sitio) para ocultar la sección aunque el módulo siga funcionando.
 *
 * Después de las secciones fijas van las páginas libres marcadas con «Mostrar
 * en el menú» (paginas_del_menu()), en el orden que se les haya puesto.
 */
$secciones = [
    'nosotros'    => ['Quiénes somos', 'bi-people'],
    'horarios'    => ['Horarios',      'bi-clock'],
    'sacramentos' => ['Sacramentos',   'bi-droplet'],
    'pastorales'  => ['Pastorales',    'bi-hand-thumbs-up'],
    'cursos'      => ['Cursos',        'bi-mortarboard'],
    'avisos'      => ['Avisos',        'bi-megaphone'],
    'eventos'     => ['Eventos',       'bi-calendar-event'],
    'galeria'     => ['Galería',       'bi-images'],
];
$moduloActual = $_GET['modulo'] ?? 'inicio';
?>
<header>
<nav class="navbar navbar-expand-lg navbar-dark barra-sitio sticky-top">
    <div class="container">

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url_publica('inicio')) ?>">
            <?php if (Config::tiene('logo')): ?>
                <img src="<?= e(url_activo(Config::get('logo'))) ?>" alt="" height="38">
            <?php else: ?>
                <i class="bi bi-house-heart fs-4 text-dorado"></i>
            <?php endif; ?>
            <span class="marca-texto">
                <span class="marca-linea1">Parroquia</span>
                <span class="marca-linea2">Nuestra Señora de la Paz</span>
            </span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#menuSitio" aria-controls="menuSitio" aria-expanded="false"
                aria-label="Mostrar el menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuSitio">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?= $moduloActual === 'inicio' ? 'active' : '' ?>"
                       href="<?= e(url_publica('inicio')) ?>">Inicio</a>
                </li>

                <?php foreach ($secciones as $modulo => [$etiqueta, $icono]): ?>
                    <?php if (Router::existeRutaPublica($modulo) && ($modulo !== 'cursos' || Config::get('cursos_activo', '1') === '1')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $moduloActual === $modulo ? 'active' : '' ?>"
                           href="<?= e(url_publica($modulo)) ?>"><?= e($etiqueta) ?></a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (Router::existeRutaPublica('pagina')): ?>
                    <?php foreach (paginas_del_menu() as $paginaMenu): ?>
                    <li class="nav-item">
                        <?php /* El router deja $_GET['slug'] tanto en /mi-pagina como en /pagina/mi-pagina. */ ?>
                        <a class="nav-link <?= ($_GET['slug'] ?? '') === $paginaMenu['slug'] ? 'active' : '' ?>"
                           href="<?= e(url_publica('pagina', ['slug' => $paginaMenu['slug']])) ?>">
                            <?= e($paginaMenu['titulo']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (Router::existeRutaPublica('contacto')): ?>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-sm btn-dorado" href="<?= e(url_publica('contacto')) ?>">
                        <i class="bi bi-geo-alt me-1"></i>Contacto
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</nav>
</header>
