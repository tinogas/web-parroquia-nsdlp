<?php
/**
 * Catálogo de sacramentos: un renglón por sacramento, con el principio de su
 * descripción y la aportación, que lleva a su ficha completa. Al lado, la
 * oficina parroquial, que es donde se tramitan todos.
 */
$telefono = Config::get('telefono');
$whatsapp = Config::get('whatsapp');
?>
<div class="row g-4 g-lg-5">
    <div class="col-lg-7">
        <div class="encabezado-seccion">
            <span class="antetitulo">Sacramentos</span>
            <h1 class="titulo-pagina mb-3">Lo que necesitas para cada sacramento</h1>
            <?php if (!empty($bloques['sacramentos_intro']['contenido'])): ?>
            <div class="contenido-editorial text-tinta-suave">
                <?= $bloques['sacramentos_intro']['contenido'] ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$sacramentos): ?>
        <p class="text-muted fst-italic">Estamos actualizando esta sección.</p>
        <?php else: ?>
        <ul class="lista-sacramentos">
            <?php foreach ($sacramentos as $sacramento): ?>
            <?php $resumen = resumen($sacramento['descripcion'], 150); ?>
            <li>
                <a href="<?= e(url_publica('sacramentos', ['slug' => $sacramento['slug']])) ?>">
                    <span class="min-w-0">
                        <span class="nombre"><?= e($sacramento['nombre']) ?></span>
                        <?php if ($sacramento['aportacion']): ?>
                        <span class="aportacion">Aportación <?= e($sacramento['aportacion']) ?></span>
                        <?php endif; ?>
                        <?php if ($resumen !== ''): ?>
                        <span class="resumen"><?= e($resumen) ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <aside class="col-lg-4 offset-lg-1">
        <div class="tarjeta-oficina">
            <i class="bi bi-file-earmark-text icono" aria-hidden="true"></i>
            <h2>Oficina parroquial</h2>
            <?php if (Config::tiene('horario_oficina')): ?>
            <p class="horario-oficina"><?= nl2br(e(Config::get('horario_oficina'))) ?></p>
            <?php endif; ?>
            <p class="small text-muted">Todo trámite de sacramentos se asienta en persona, en la oficina.</p>
            <div class="d-grid gap-2">
                <?php if ($telEnlace = tel_enlace($telefono)): ?>
                <a href="<?= e($telEnlace) ?>" class="btn btn-primary">
                    <i class="bi bi-telephone me-1"></i>Llamar al <?= e($telefono) ?>
                </a>
                <?php endif; ?>
                <?php if ($waEnlace = whatsapp_enlace($whatsapp)): ?>
                <a href="<?= e($waEnlace) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-1"></i>Escribir por WhatsApp
                </a>
                <?php endif; ?>
                <?php if (Router::existeRutaPublica('contacto')): ?>
                <a href="<?= e(url_publica('contacto')) ?>" class="enlace-flecha justify-content-center mt-1">
                    Ubicación y contacto <i class="bi bi-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
