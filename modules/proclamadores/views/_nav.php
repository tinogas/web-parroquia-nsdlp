<?php
/**
 * Barra de navegación entre las pantallas del módulo.
 *
 * Un parcial y no el bloque repetido en cada vista —como sí lo está en
 * Catequesis, que tiene tres pantallas— porque aquí son cinco: repetido,
 * agregar una sexta obliga a acordarse de editar los cinco archivos, y el que
 * se olvide queda con una barra distinta a las demás. La lista de abajo es el
 * único sitio donde se declara.
 *
 * Variables esperadas:
 *   $navActiva  string, la clave de la pantalla que se está viendo. Esa se
 *               omite: ya la nombra el título de la página, y un botón que
 *               lleva a donde ya estás no sirve de nada. Es el mismo criterio
 *               que siguen las vistas de Catequesis.
 */
$pantallasModulo = [
    ''            => ['Proclamadores', 'bi-people'],
    'turnos'      => ['Turnos',        'bi-calendar3'],
    'actividades' => ['Actividades',   'bi-clipboard-check'],
    'documentos'  => ['Documentos',    'bi-file-earmark-pdf'],
    'colores'     => ['Colores',       'bi-palette'],
];
?>
<?php foreach ($pantallasModulo as $accion => [$etiqueta, $icono]): ?>
<?php if ($accion === ($navActiva ?? '')) { continue; } ?>
<a href="<?= e(url_admin('proclamadores', $accion)) ?>" class="btn btn-outline-secondary">
    <i class="bi <?= e($icono) ?> me-1"></i><?= e($etiqueta) ?>
</a>
<?php endforeach; ?>
