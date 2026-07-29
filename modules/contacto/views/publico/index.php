<h1 class="titulo-pagina mb-2">Contacto</h1>

<?php if (!empty($bloques['contacto_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['contacto_intro']['contenido'] ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Cómo encontrarnos</h2>
                <ul class="list-unstyled lista-contacto mb-0">
                    <?php if (Config::tiene('direccion')): ?>
                    <li>
                        <i class="bi bi-geo-alt text-primary"></i>
                        <span>
                            <?= e(Config::get('direccion')) ?>
                            <?php if (Config::tiene('ciudad')): ?><br><?= e(Config::get('ciudad')) ?><?php endif; ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('telefono')): ?>
                    <li>
                        <i class="bi bi-telephone text-primary"></i>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', Config::get('telefono'))) ?>">
                            <?= e(Config::get('telefono')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('whatsapp')): ?>
                    <li>
                        <i class="bi bi-whatsapp text-primary"></i>
                        <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', Config::get('whatsapp'))) ?>"
                           target="_blank" rel="noopener">Escribir por WhatsApp</a>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('email')): ?>
                    <li>
                        <i class="bi bi-envelope text-primary"></i>
                        <a href="mailto:<?= e(Config::get('email')) ?>"><?= e(Config::get('email')) ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('horario_oficina')): ?>
                    <li>
                        <i class="bi bi-clock text-primary"></i>
                        <span><?= nl2br(e(Config::get('horario_oficina'))) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if (Config::tiene('mapa_embed')): ?>
        <div class="ratio ratio-4x3 marco-mapa">
            <iframe src="<?= e(Config::get('mapa_embed')) ?>" loading="lazy"
                    title="Ubicación de la parroquia" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <?php endif; ?>

    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Escríbenos</h2>

                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errores as $error): ?>
                        <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('publico', 'contacto', 'enviar')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <?= AntiSpam::campos() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($valores['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="asunto" class="form-label fw-semibold">Asunto</label>
                            <input type="text" name="asunto" id="asunto" class="form-control"
                                   value="<?= e($valores['asunto'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?= e($valores['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control"
                                   value="<?= e($valores['telefono'] ?? '') ?>">
                        </div>
                    </div>
                    <p class="form-text mt-n2 mb-3">Déjanos al menos un correo o un teléfono para poder responderte.</p>

                    <div class="mb-3">
                        <label for="mensaje" class="form-label fw-semibold">Mensaje</label>
                        <textarea name="mensaje" id="mensaje" class="form-control" rows="5"
                                  required><?= e($valores['mensaje'] ?? '') ?></textarea>
                    </div>

                    <?php require BASE_PATH . '/shared/views/parciales/consentimiento_privacidad.php'; ?>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-send me-1"></i>Enviar mensaje
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
