<?php
/**
 * Barra de navegación entre las pantallas del módulo.
 *
 * Un parcial desde el primer día, aunque hoy sean solo dos pantallas: es la
 * lección de Proclamadores, que llegó a cinco copias del mismo bloque antes de
 * sacarlo aquí, y con copias agregar una pantalla obliga a acordarse de editar
 * todos los archivos —el que se olvide queda con una barra distinta—.
 *
 * Variables esperadas:
 *   $navActiva  string, la clave de la pantalla que se está viendo. Esa se
 *               omite: ya la nombra el título de la página, y un botón que
 *               lleva a donde ya estás no sirve de nada.
 */
$pantallasModulo = [
    ''         => ['Coros',          'bi-music-note-list'],
    'coristas' => ['Quiénes cantan', 'bi-people'],
];
?>
<?php foreach ($pantallasModulo as $accion => [$etiqueta, $icono]): ?>
<?php if ($accion === ($navActiva ?? '')) { continue; } ?>
<a href="<?= e(url_admin('coros', $accion)) ?>" class="btn btn-outline-secondary">
    <i class="bi <?= e($icono) ?> me-1"></i><?= e($etiqueta) ?>
</a>
<?php endforeach; ?>
