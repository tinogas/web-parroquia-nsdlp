<h1 class="titulo-pagina mb-4">Galería</h1>

<?php if (!$listado['filas']): ?>
<p class="text-muted fst-italic">Todavía no hay fotografías publicadas.</p>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($listado['filas'] as $imagen): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <?php
        // El enlace apunta directo al archivo y funciona sin JavaScript —abre la
        // foto en una pestaña nueva—; lightbox_galeria.js lo intercepta para
        // mostrarla sin recortar, en la misma página. data-titulo va aparte de
        // title (el tooltip nativo) porque solo debe convertirse en pie de foto
        // cuando de verdad hay uno, no con el texto de ayuda por defecto.
        ?>
        <a href="<?= e(url_activo($imagen['archivo'])) ?>" target="_blank" rel="noopener"
           class="d-block tarjeta-galeria" title="<?= e($imagen['titulo'] ?: 'Ver en tamaño completo') ?>"
           <?php if ($imagen['titulo']): ?>data-titulo="<?= e($imagen['titulo']) ?>"<?php endif; ?>>
            <img src="<?= e(url_activo($imagen['archivo'])) ?>"
                 alt="<?= e($imagen['alt_texto'] ?: $imagen['titulo'] ?: 'Fotografía de la parroquia') ?>"
                 loading="lazy">
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php
$paginacion = $listado;
$paginaBase = url_publica('galeria');
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php if ($listado['filas']): ?>
<div class="lightbox-galeria" id="lightboxGaleria" role="dialog" aria-modal="true" aria-label="Visor de fotografías" hidden>
    <button type="button" class="lightbox-cerrar" aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
    </button>
    <button type="button" class="lightbox-nav lightbox-anterior" aria-label="Foto anterior">
        <i class="bi bi-chevron-left"></i>
    </button>
    <figure class="lightbox-contenido">
        <img src="" alt="">
        <figcaption hidden></figcaption>
    </figure>
    <button type="button" class="lightbox-nav lightbox-siguiente" aria-label="Foto siguiente">
        <i class="bi bi-chevron-right"></i>
    </button>
    <div class="lightbox-contador"></div>
</div>
<?php endif; ?>
