<?php
/**
 * Recuadro de avisos de la cabecera de la portada.
 *
 * Los avisos vigentes publicados para el sitio, cada uno con una miniatura de
 * su foto como referencia, su fecha y su título; el renglón entero abre el
 * aviso. Sin foto, la miniatura es el ícono del tipo de aviso sobre marfil.
 *
 * Lo usan las dos cabeceras: la editorial (a la derecha del texto de
 * presentación) y la del carrusel (debajo de las fotos). Espera $avisosPortada.
 */
$iconosTipo = [
    'noticia'    => 'bi-newspaper',
    'boletin'    => 'bi-file-earmark-text',
    'comunicado' => 'bi-megaphone',
];
?>
<aside class="avisos-portada" aria-labelledby="avisos-portada-titulo">
    <div class="avisos-portada-cabecera">
        <h2 id="avisos-portada-titulo" class="avisos-portada-titulo">
            <i class="bi bi-megaphone" aria-hidden="true"></i> Avisos
        </h2>
        <a href="<?= e(url_publica('avisos')) ?>" class="enlace-flecha small">Ver todos <i class="bi bi-arrow-right"></i></a>
    </div>
    <ul class="avisos-portada-lista">
        <?php foreach ($avisosPortada as $aviso): ?>
        <li>
            <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>" class="aviso-mini">
                <?php if (!empty($aviso['imagen'])): ?>
                <img src="<?= e(url_activo($aviso['imagen'])) ?>" alt="" class="aviso-mini-foto"
                     width="64" height="64" decoding="async">
                <?php else: ?>
                <span class="aviso-mini-foto aviso-mini-icono" aria-hidden="true">
                    <i class="bi <?= e($iconosTipo[$aviso['tipo']] ?? 'bi-megaphone') ?>"></i>
                </span>
                <?php endif; ?>
                <span class="aviso-mini-texto">
                    <span class="aviso-mini-fecha"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></span>
                    <span class="aviso-mini-titulo"><?= e($aviso['titulo']) ?></span>
                </span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</aside>
