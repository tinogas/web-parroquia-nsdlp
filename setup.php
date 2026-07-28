<?php
/**
 * setup.php — Crea la cuenta de administrador.
 *
 * Se ejecuta UNA sola vez, después de importar install.sql, y se BORRA del
 * servidor a continuación: mientras exista, cualquiera que dé con la dirección
 * puede crear un administrador si la tabla está vacía.
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/app.php';

if (!is_file(BASE_PATH . '/config/database.php')) {
    exit('Falta config/database.php. Copia config/database.example.php y ajusta las credenciales.');
}
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/core/helpers.php';

$error   = '';
$exito   = '';
$avisos  = [];

// Comprobaciones del entorno, para no descubrir los problemas más tarde.
if (version_compare(PHP_VERSION, '8.1', '<')) {
    $avisos[] = 'PHP ' . PHP_VERSION . ': el proyecto requiere 8.1 o superior.';
}
foreach (['pdo_mysql', 'fileinfo', 'dom', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        $avisos[] = 'Falta la extensión de PHP «' . $ext . '».';
    }
}
if (!extension_loaded('gd')) {
    $avisos[] = 'La extensión «gd» no está disponible: las imágenes se guardarán sin redimensionar.';
}

try {
    $version = Database::getInstance()->query('SELECT VERSION()')->fetchColumn();
    $avisos[] = 'Base de datos: ' . $version;
} catch (PDOException $e) {
    $error = 'No se pudo conectar con la base de datos: ' . $e->getMessage();
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirma = $_POST['confirma']      ?? '';

    if ($nombre === '' || $email === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirma) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $db = Database::getInstance();
            $yaHay = (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();

            if ($yaHay > 0) {
                $error = 'Ya existe un administrador. Por seguridad, borra este archivo (setup.php).';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare(
                    "INSERT INTO usuarios (nombre, email, password_hash, rol)
                     VALUES (:nombre, :email, :hash, 'admin')"
                )->execute([':nombre' => $nombre, ':email' => $email, ':hash' => $hash]);

                $exito = 'Administrador creado correctamente.';
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Instalación — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background:#16233a; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
        .setup-card { width:100%; max-width:500px; }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
                <div style="font-size:2.5rem;color:#c9a227"><i class="bi bi-house-heart"></i></div>
                <h4 class="fw-bold mt-2 mb-1">Instalación</h4>
                <p class="text-muted small mb-0">Crea la cuenta de administrador del sitio</p>
            </div>

            <?php foreach ($avisos as $aviso): ?>
                <div class="alert alert-light border small py-2 mb-2">
                    <i class="bi bi-info-circle me-1 text-secondary"></i><?= e($aviso) ?>
                </div>
            <?php endforeach; ?>

            <?php if ($exito): ?>
                <div class="alert alert-success">
                    <strong><?= e($exito) ?></strong>
                    <div class="mt-3">
                        <a href="<?= e(url_admin('auth', 'login')) ?>" class="btn btn-success">
                            Entrar al panel
                        </a>
                    </div>
                    <hr>
                    <div class="small">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                        <strong>Antes de nada:</strong> borra el archivo <code>setup.php</code> del servidor.
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$exito): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre</label>
                    <input type="text" name="nombre" class="form-control"
                           value="<?= e($_POST['nombre'] ?? 'Administrador') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Correo electrónico</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                    <div class="form-text">Con este correo se inicia sesión.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Contraseña</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Mínimo 8 caracteres" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirmar contraseña</label>
                    <input type="password" name="confirma" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    Crear administrador
                </button>
            </form>
            <?php endif; ?>

        </div>
    </div>
    <p class="text-center text-white-50 small mt-3 mb-0">
        <?= e(APP_NAME) ?> · versión <?= e(APP_VERSION) ?>
    </p>
</div>
</body>
</html>
