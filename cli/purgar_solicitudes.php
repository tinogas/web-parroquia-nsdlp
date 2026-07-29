<?php
/**
 * Purga programada de solicitudes de sacramentos vencidas.
 *
 * Anonimiza (nunca borra) las solicitudes ya cerradas —aprobada→completada,
 * rechazada, cancelada— más viejas que configuracion.retencion_meses_solicitudes.
 * Es la misma acción que el botón "Purgar vencidas" del panel
 * (SolicitudController::purgar()), pero pensada para correr sola desde el
 * programador de tareas de cPanel: sin esto, cumplir el plazo de retención de
 * docs/PRIVACIDAD.md depende de que alguien recuerde entrar y hacer clic.
 *
 * Uso en el cron de cPanel (una vez al día basta):
 *   php -f /home/usuario/public_html/cli/purgar_solicitudes.php
 *
 * No requiere sesión ni permisos de panel: si el hosting no da acceso a
 * "Cron Jobs", el botón del panel sigue siendo la única vía, y no pasa nada
 * si este script nunca se ejecuta.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por línea de comandos.\n");
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';
if (!is_file(BASE_PATH . '/config/database.php')) {
    fwrite(STDERR, "Falta config/database.php.\n");
    exit(1);
}
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/core/Config.php';
require_once BASE_PATH . '/core/Model.php';
require_once BASE_PATH . '/modules/sacramentos/SolicitudModel.php';

$meses = max(1, (int) Config::get('retencion_meses_solicitudes', '36'));
$n     = (new SolicitudModel())->purgarVencidas($meses);

try {
    Database::getInstance()->prepare(
        'INSERT INTO auditoria (usuario_id, accion, tabla_ref, descripcion)
         VALUES (NULL, :accion, :tabla, :desc)'
    )->execute([
        ':accion' => 'editar',
        ':tabla'  => 'solicitudes_sacramento',
        ':desc'   => "Purga automática (cron): {$n} registro(s) anonimizados, retención {$meses} meses",
    ]);
} catch (Throwable $e) {
    // No interrumpe la purga si la auditoría falla.
}

echo date('Y-m-d H:i:s') . " — Purga completada: {$n} solicitud(es) anonimizada(s) (retención: {$meses} meses).\n";
