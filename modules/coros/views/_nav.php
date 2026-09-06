<?php
/**
 * Barra de navegación entre las pantallas del módulo.
 *
 * Un parcial desde el primer día: es la lección de Proclamadores, que llegó a
 * cinco copias del mismo bloque antes de sacarlo aquí, y con copias agregar una
 * pantalla obliga a acordarse de editar todos los archivos —el que se olvide
 * queda con una barra distinta—. Se notó enseguida: nació con dos pantallas y
 * a las pocas horas fueron cuatro, y agregarlas fue una línea aquí.
 *
 * Variables esperadas:
 *   $navActiva  string, la clave de la pantalla que se está viendo. Esa se
 *               omite: ya la nombra el título de la página, y un botón que
 *               lleva a donde ya estás no sirve de nada.
 */
$pantallasModulo = [
    ''            => ['Coros',          'bi-music-note-list'],
    'coristas'    => ['Integrantes',    'bi-people'],
    'actividades' => ['Actividades',    'bi-clipboard-check'],
    'documentos'  => ['Documentos',     'bi-file-earmark-pdf'],
];
?>
<?php foreach ($pantallasModulo as $accion => [$etiqueta, $icono]): ?>
<?php if ($accion === ($navActiva ?? '')) { continue; } ?>
<a href="<?= e(url_admin('coros', $accion)) ?>" class="btn btn-outline-secondary">
    <i class="bi <?= e($icono) ?> me-1"></i><?= e($etiqueta) ?>
</a>
<?php endforeach; ?>
