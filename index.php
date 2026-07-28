<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

// Configuración
require_once BASE_PATH . '/config/app.php';
if (!is_file(BASE_PATH . '/config/database.php')) {
    exit('Falta config/database.php. Copia config/database.example.php y ajusta las credenciales.');
}
require_once BASE_PATH . '/config/database.php';

// Núcleo
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/Session.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/Config.php';
require_once BASE_PATH . '/core/Model.php';
require_once BASE_PATH . '/core/Upload.php';
require_once BASE_PATH . '/core/SanitizadorHtml.php';
require_once BASE_PATH . '/core/Slug.php';
require_once BASE_PATH . '/core/AntiSpam.php';
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/core/Router.php';

// La sesión se abre solo si hace falta: un visitante anónimo que consulta los
// horarios de misa no debe recibir cookie ni perder la caché por ello.
Session::iniciarSiNecesario(Router::area());

Router::dispatch();
