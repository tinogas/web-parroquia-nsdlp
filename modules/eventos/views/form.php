<?php
$esNuevo       = $evento === null;
$puedePublicar = Auth::tienePermiso('eventos.publicar');

// Al duplicar llega $original: el formulario es de evento nuevo (id 0), pero
// lleno con los datos del original para cambiar solo la fecha. $base es de
// donde salen los valores: el evento que se edita, el original, o nada.
$original = $original ?? null;
$esCopia  = $esNuevo && $original !== null;
$base     = $evento ?? $original;

/** El input datetime-local espera "YYYY-MM-DDTHH:MM"; la BD guarda "YYYY-MM-DD HH:MM:SS". */
$paraInput = static fn (?string $valor): string => $valor ? str_replace(' ', 'T', substr($valor, 0, 16)) : '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('eventos')) ?>" class="text-decoration-none">Eventos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esCopia ? 'Duplicar' : ($esNuevo ? 'Nuevo' : 'Editar') ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esCopia ? 'Duplicar evento' : ($esNuevo ? 'Nuevo evento' : e($evento['titulo'])) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$esNuevo && Auth::tienePermiso('eventos.crear')): ?>
        <a href="<?= e(url_admin('eventos', 'duplicar', ['id' => (int) $evento['id']])) ?>"
           class="btn btn-sm btn-outline-primary" title="El mismo evento en otra fecha">
            <i class="bi bi-copy me-1"></i>Duplicar
        </a>
        <?php endif; ?>
        <a href="<?= e(url_admin('eventos')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if ($esCopia): ?>
<div class="alert alert-info d-flex gap-2 align-items-start" role="status">
    <i class="bi bi-copy mt-1"></i>
    <div>
        Copia de <strong>«<?= e($original['titulo']) ?>»</strong> del
        <?= e(fecha_larga(substr($original['fecha_inicio'], 0, 10))) ?>.
        Pon la <strong>nueva fecha</strong>, cambia lo que haga falta y pulsa <strong>Guardar</strong>.
        El evento original no se modifica.
    </div>
</div>
<?php endif; ?>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'eventos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $evento['id'] ?>">
    <?php if ($esCopia): ?>
    <input type="hidden" name="duplicar_de" value="<?= (int) $original['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($base ? $base['titulo'] : '') ?>" maxlength="200" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fecha_inicio" class="form-label fw-semibold">Inicio</label>
                            <input type="datetime-local" name="fecha_inicio" id="fecha_inicio"
                                   class="form-control <?= $esCopia ? 'border-primary border-2' : '' ?>"
                                   value="<?= e($base ? $paraInput($base['fecha_inicio']) : '') ?>" required
                                   <?= $esCopia ? 'autofocus' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin" class="form-label fw-semibold">Fin</label>
                            <input type="datetime-local" name="fecha_fin" id="fecha_fin" class="form-control"
                                   value="<?= e($base ? $paraInput($base['fecha_fin']) : '') ?>">
                            <div class="form-text">Opcional. Déjalo en blanco si es de un solo momento.
                                Al mover el inicio, el fin se mueve con él.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="lugar" class="form-label fw-semibold">Lugar</label>
                            <input type="text" name="lugar" id="lugar" class="form-control"
                                   value="<?= e($base ? (string) $base['lugar'] : '') ?>" maxlength="160">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       name="todo_el_dia" id="todo_el_dia" value="1"
                                       <?= ($base && $base['todo_el_dia']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="todo_el_dia">Todo el día</label>
                            </div>
                        </div>
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción';
                    $eh_valor    = $base ? (string) $base['descripcion'] : '';
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = $base ? (string) $base['imagen'] : '';
                    $ci_ayuda    = ($esCopia && $ci_actual !== '')
                        ? 'La copia lleva su propio archivo de la imagen del original; sube otra si quieres cambiarla.'
                        : '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <?php
                    $sp_valorActual = $base ? $base['pastoral_id'] : ($pastoralIdPreseleccionado ?? null);
                    require BASE_PATH . '/shared/views/parciales/selector_pastoral.php';

                    $sc_valorActual = $base ? $base['centro_id'] : null;
                    require BASE_PATH . '/shared/views/parciales/selector_centro.php';
                    ?>

                    <div class="mb-3">
                        <label for="color" class="form-label fw-semibold">Color en el calendario</label>
                        <input type="color" name="color" id="color" class="form-control form-control-color"
                               value="<?= e($base ? ($base['color'] ?: '#1e4d8b') : '#1e4d8b') ?>">
                    </div>

                    <?php if ($puedePublicar): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicado" id="publicado" value="1"
                               <?= (!$base || $base['publicado']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicado</label>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Este evento se enviará como borrador para que un editor lo publique.
                    </p>
                    <?php endif; ?>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i><?= $esCopia ? 'Guardar la copia' : 'Guardar' ?>
                </button>
                <a href="<?= e(url_admin('eventos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<script>
// Al mover el inicio, el fin se mueve con él y conserva la duración, como en
// cualquier calendario. Es lo que hace cómodo duplicar: se cambia un solo
// campo. Si el fin estaba vacío, se queda vacío.
(function () {
    'use strict';
    var inicio = document.getElementById('fecha_inicio');
    var fin    = document.getElementById('fecha_fin');
    if (!inicio || !fin) { return; }

    // datetime-local sin zona: se lee y escribe como UTC para que un cambio de
    // horario de verano no mueva la hora.
    var leer = function (v) { return v ? Date.parse(v + ':00Z') : NaN; };
    var escribir = function (ms) { return new Date(ms).toISOString().slice(0, 16); };

    var previo = leer(inicio.value);
    inicio.addEventListener('change', function () {
        var nuevo = leer(inicio.value);
        var hasta = leer(fin.value);
        if (!isNaN(previo) && !isNaN(nuevo) && !isNaN(hasta) && hasta >= previo) {
            fin.value = escribir(nuevo + (hasta - previo));
        }
        previo = nuevo;
    });
})();
</script>
