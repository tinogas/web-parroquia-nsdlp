<?php
/**
 * Menú del sitio.
 *
 * Inicio primero y Contacto al final, en el botón dorado, son fijos. Lo de en
 * medio —las secciones fijas y las páginas libres— sale de menu_del_sitio()
 * (core/helpers.php), que se administra en Contenido → Menú del sitio: qué
 * se muestra, en qué orden y con qué nombre. Esa función ya descarta las
 * secciones cuyo módulo no existe, Cursos si está apagado en Configuración
 * y las páginas sin publicar.
 */
$moduloActual = $_GET['modulo'] ?? 'inicio';
?>
<header>
<nav class="navbar navbar-expand-xl navbar-dark barra-sitio sticky-top">
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
            <ul class="navbar-nav ms-auto align-items-xl-center">
                <li class="nav-item">
                    <a class="nav-link <?= $moduloActual === 'inicio' ? 'active' : '' ?>"
                       href="<?= e(url_publica('inicio')) ?>">Inicio</a>
                </li>

                <?php foreach (menu_del_sitio() as $entrada): ?>
                    <?php if ($entrada['tipo'] === 'seccion'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $moduloActual === $entrada['modulo'] ? 'active' : '' ?>"
                           href="<?= e(url_publica($entrada['modulo'])) ?>"><?= e($entrada['etiqueta']) ?></a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <?php /* El router deja $_GET['slug'] tanto en /mi-pagina como en /pagina/mi-pagina. */ ?>
                        <a class="nav-link <?= $moduloActual === 'pagina' && ($_GET['slug'] ?? '') === $entrada['slug'] ? 'active' : '' ?>"
                           href="<?= e(url_publica('pagina', ['slug' => $entrada['slug']])) ?>"><?= e($entrada['etiqueta']) ?></a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (Router::existeRutaPublica('contacto')): ?>
                <li class="nav-item ms-xl-2 mt-2 mt-xl-0">
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
