<?php
/**
 * Copia este archivo como database.php y ajusta las credenciales.
 * NUNCA subas database.php al repositorio (está en .gitignore).
 *
 * En cPanel el nombre de la base y el del usuario llevan el prefijo de la
 * cuenta, por ejemplo: micuenta_parroquia y micuenta_parroquia_usr.
 */
define('DB_HOST',    'localhost');
define('DB_NAME',    'parroquia_nsdlp');
define('DB_USER',    'usuario_bd');
define('DB_PASS',    'contraseña_bd');
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
