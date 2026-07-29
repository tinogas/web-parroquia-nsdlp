<?php
class Session
{
    /**
     * Arranca la sesión solo cuando hace falta.
     *
     * A diferencia de un panel interno, aquí la mayoría de las visitas son
     * anónimas: alguien que entra a consultar el horario de misa no necesita
     * una cookie PHPSESSID, y ponérsela anularía además cualquier caché.
     *
     * La sesión se abre si el visitante ya la tenía, si va al panel, si envía
     * un formulario, o si el controlador público la pide explícitamente
     * mediante $requiereSesion (páginas con formulario, que necesitan CSRF).
     */
    public static function iniciarSiNecesario(string $area = 'publico'): void
    {
        $hace_falta = $area === 'admin'
            || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            || isset($_COOKIE[session_name()]);

        if ($hace_falta) {
            self::iniciar();
        }
    }

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => rtrim(APP_URL, '/') . '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function activa(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function set(string $key, mixed $value): void
    {
        self::iniciar();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destruir(): void
    {
        if (!self::activa()) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function regenerar(): void
    {
        self::iniciar();
        session_regenerate_id(true);
    }

    // ── Mensajes flash: se guardan y se recuperan una sola vez ──────────

    public static function flash(string $tipo, string $mensaje): void
    {
        self::iniciar();
        $_SESSION['_flash'][$tipo][] = $mensaje;
    }

    public static function getFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public static function hayFlash(): bool
    {
        return !empty($_SESSION['_flash']);
    }

    // ── Token CSRF ──────────────────────────────────────────────────────

    public static function getCsrfToken(): string
    {
        self::iniciar();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validarCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf'] ?? '', $token);
    }

    public static function renovarCsrf(): void
    {
        self::iniciar();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
