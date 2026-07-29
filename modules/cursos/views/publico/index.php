<h1 class="titulo-pagina mb-2">Cursos y capacitaciones</h1>

<?php if (!empty($bloques['cursos_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['cursos_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (!$cursos): ?>
<p class="text-muted fst-italic">No hay cursos publicados por el momento.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($cursos as $curso): ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 tarjeta-aviso">
                <img src="<?= e(imagen_o_placeholder($curso['imagen'], $curso['titulo'], 400, 200)) ?>"
                     class="card-img-top" alt="" style="height:150px;object-fit:cover">
                <div class="card-body p-3">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">
                        <?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?>
                    </span>
                    <h2 class="h6 fw-bold mb-1 text-body"><?= e($curso['titulo']) ?></h2>
                    <?php if ($curso['fecha_inicio']): ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i>Inicia el <?= e(fecha_larga($curso['fecha_inicio'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
