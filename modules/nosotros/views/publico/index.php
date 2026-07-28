<h1 class="titulo-pagina mb-4">Quiénes somos</h1>

<?php if (!empty($bloques['historia']['contenido'])): ?>
<section class="mb-5">
    <h2 class="h4 fw-bold mb-3"><?= e($bloques['historia']['titulo'] ?: 'Nuestra historia') ?></h2>
    <div class="contenido-editorial">
        <?= $bloques['historia']['contenido'] ?>
    </div>
</section>
<?php endif; ?>

<?php
$mvv = ['mision' => 'bi-compass', 'vision' => 'bi-binoculars', 'valores' => 'bi-heart'];
$hayMvv = false;
foreach (array_keys($mvv) as $clave) {
    if (!empty($bloques[$clave]['contenido'])) { $hayMvv = true; break; }
}
?>
<?php if ($hayMvv): ?>
<section class="mb-5">
    <div class="row g-4">
        <?php foreach ($mvv as $clave => $icono): ?>
            <?php if (empty($bloques[$clave]['contenido'])) { continue; } ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="fs-3 text-dorado mb-2"><i class="bi <?= e($icono) ?>"></i></div>
                        <h2 class="h6 fw-bold"><?= e($bloques[$clave]['titulo'] ?: ucfirst($clave)) ?></h2>
                        <div class="contenido-editorial small">
                            <?= $bloques[$clave]['contenido'] ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (empty($bloques['historia']['contenido']) && !$hayMvv): ?>
<p class="text-muted fst-italic">Esta sección se está preparando.</p>
<?php endif; ?>

<?php if (!empty($equipo)): ?>
<section class="mb-5">
    <h2 class="h4 fw-bold mb-4">Nuestro equipo pastoral</h2>
    <?php foreach (PersonaModel::TIPOS as $tipo => $nombreTipo): ?>
        <?php if (empty($equipo[$tipo])) { continue; } ?>
        <h3 class="h6 text-uppercase text-muted mb-3"><?= e($nombreTipo) ?></h3>
        <div class="row g-4 mb-4">
            <?php foreach ($equipo[$tipo] as $persona): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <img src="<?= e(foto_o_avatar($persona['foto'], $persona['nombre'], 96)) ?>"
                             class="rounded-circle mb-3" style="width:88px;height:88px;object-fit:cover" alt="">
                        <h4 class="h6 fw-bold mb-1"><?= e($persona['nombre']) ?></h4>
                        <?php if ($persona['cargo']): ?>
                        <p class="text-dorado small fw-semibold mb-2"><?= e($persona['cargo']) ?></p>
                        <?php endif; ?>
                        <?php if ($persona['semblanza']): ?>
                        <p class="small text-muted mb-0"><?= e($persona['semblanza']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($organigramaImagen !== '' || !empty($organigramaArbol)): ?>
<section class="mb-5">
    <h2 class="h4 fw-bold mb-4">Organigrama</h2>
    <?php if ($organigramaImagen !== ''): ?>
        <img src="<?= e(url_activo($organigramaImagen)) ?>" alt="Organigrama de la parroquia" class="img-fluid rounded shadow-sm">
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php $oa_nodos = $organigramaArbol; require BASE_PATH . '/shared/views/parciales/organigrama_arbol.php'; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
