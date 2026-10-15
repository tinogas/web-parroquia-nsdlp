<?php
/**
 * Página de Horarios: la misma semana que la portada (semana.php), con el
 * texto de introducción que se captura en el panel.
 */
$intro = !empty($bloques['horarios_intro']['contenido'])
    ? '<div class="contenido-editorial text-tinta-suave">' . $bloques['horarios_intro']['contenido'] . '</div>'
    : '<p>Los horarios de las misas y confesiones en la sede parroquial y en los centros. Se abre en el día de hoy.</p>';
?>
<div data-horarios>
    <?php
    $semana = ['nivel' => 'h1', 'intro' => $intro, 'sede' => $centroId];
    require BASE_PATH . '/modules/horarios/views/publico/semana.php';
    ?>
</div>
