<?php
/**
 * AntiSpam — Protección de los formularios públicos.
 *
 * Se descartó reCAPTCHA por dos razones: enviaría datos de cada visitante a
 * Google, lo que contradice el aviso de privacidad del propio sitio, y sería una
 * dependencia externa. En su lugar, tres medidas que no molestan a nadie:
 *
 *  1. Un campo señuelo, oculto por CSS. Una persona no lo ve; los programas
 *     automáticos rellenan todo lo que encuentran.
 *  2. Una marca de tiempo firmada. Nadie completa un formulario de inscripción
 *     en menos de cuatro segundos.
 *  3. Un límite por dirección IP, para que quien insista no pueda inundar la
 *     bandeja de la secretaría.
 *
 * El token CSRF sigue siendo obligatorio aparte; esto no lo sustituye.
 */
class AntiSpam
{
    private const NOMBRE_SENUELO = 'sitio_web';
    private const NOMBRE_TIEMPO  = '_t';
    private const SEGUNDOS_MINIMO = 4;
    private const SEGUNDOS_MAXIMO = 7200;      // 2 horas: un formulario más viejo se rehace
    private const INTENTOS_POR_HORA = 5;

    /** Campos ocultos que debe incluir todo formulario público. */
    public static function campos(): string
    {
        $ahora = time();
        $firma = $ahora . '.' . self::firmar($ahora);

        // El señuelo lleva un nombre creíble y etiqueta propia: los programas
        // que analizan el formulario buscan campos con aspecto real.
        return '<div class="campo-senuelo" aria-hidden="true">'
             . '<label for="' . self::NOMBRE_SENUELO . '">No escribas nada aquí</label>'
             . '<input type="text" name="' . self::NOMBRE_SENUELO . '" id="' . self::NOMBRE_SENUELO . '"'
             . ' value="" autocomplete="off" tabindex="-1">'
             . '</div>'
             . '<input type="hidden" name="' . self::NOMBRE_TIEMPO . '" value="' . e($firma) . '">';
    }

    /**
     * Comprueba el envío.
     *
     * @param  string $formulario  nombre para el registro de intentos
     * @throws RuntimeException con un mensaje presentable si algo no cuadra
     * @return bool  false si el envío parece automático y debe descartarse en
     *               silencio, sin decirle al remitente qué lo delató
     */
    public static function validar(string $formulario): bool
    {
        if (!empty($_POST[self::NOMBRE_SENUELO])) {
            return false;
        }

        [$momento, $firma] = array_pad(
            explode('.', (string) ($_POST[self::NOMBRE_TIEMPO] ?? ''), 2),
            2,
            ''
        );
        $momento = (int) $momento;

        if ($momento <= 0 || !hash_equals(self::firmar($momento), $firma)) {
            throw new RuntimeException(
                'No pudimos validar el formulario. Recarga la página e inténtalo de nuevo.'
            );
        }

        $transcurrido = time() - $momento;

        if ($transcurrido < self::SEGUNDOS_MINIMO) {
            return false;
        }

        if ($transcurrido > self::SEGUNDOS_MAXIMO) {
            throw new RuntimeException(
                'El formulario estuvo abierto demasiado tiempo. Recarga la página y vuelve a enviarlo.'
            );
        }

        if (self::demasiadosIntentos($formulario)) {
            throw new RuntimeException(
                'Recibimos varios envíos desde tu conexión. Espera unos minutos antes de intentarlo otra vez.'
            );
        }

        self::registrarIntento($formulario);

        return true;
    }

    // ── Interno ─────────────────────────────────────────────────────────

    private static function firmar(int $momento): string
    {
        return hash_hmac('sha256', (string) $momento, self::secreto());
    }

    /**
     * Clave de firma propia de esta instalación.
     *
     * Se genera la primera vez que se necesita y se guarda en la tabla de
     * configuración, en un grupo que el panel no muestra. No puede ir en
     * config/app.php porque ese archivo sí se versiona.
     */
    private static function secreto(): string
    {
        static $secreto = null;
        if ($secreto !== null) {
            return $secreto;
        }

        $db = Database::getInstance();

        $guardado = $db->query(
            "SELECT valor FROM configuracion WHERE clave = 'sistema_secreto'"
        )->fetchColumn();

        if (is_string($guardado) && $guardado !== '') {
            return $secreto = $guardado;
        }

        $nuevo = bin2hex(random_bytes(32));
        $db->prepare(
            "INSERT INTO configuracion (clave, valor, grupo) VALUES ('sistema_secreto', :v, 'sistema')
             ON DUPLICATE KEY UPDATE valor = :v2"
        )->execute([':v' => $nuevo, ':v2' => $nuevo]);

        return $secreto = $nuevo;
    }

    private static function demasiadosIntentos(string $formulario): bool
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM intentos_formulario
              WHERE ip = :ip AND formulario = :form
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute([':ip' => self::ip(), ':form' => $formulario]);

        return (int) $stmt->fetchColumn() >= self::INTENTOS_POR_HORA;
    }

    private static function registrarIntento(string $formulario): void
    {
        $db = Database::getInstance();

        $db->prepare(
            'INSERT INTO intentos_formulario (ip, formulario) VALUES (:ip, :form)'
        )->execute([':ip' => self::ip(), ':form' => $formulario]);

        // Esta tabla sí se purga de verdad: solo sirve para contar en la última
        // hora. Se limpia de vez en cuando para no depender de una tarea
        // programada, que en hosting compartido no siempre está disponible.
        if (random_int(1, 20) === 1) {
            $db->exec('DELETE FROM intentos_formulario WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    }

    private static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
