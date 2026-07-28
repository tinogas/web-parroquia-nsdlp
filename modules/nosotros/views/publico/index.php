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

<?php /* El equipo pastoral y el organigrama se agregan en la etapa 4. */ ?>
