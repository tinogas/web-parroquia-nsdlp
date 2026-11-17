<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * EnlacePersonal — El acceso sin cuenta de los integrantes de una pastoral.
 *
 * Cada persona del equipo pastoral puede tener un enlace propio, /mi/<token>,
 * que la coordinación le manda por WhatsApp. Con él ve lo que sus pastorales
 * publican para su equipo y, en MESC y Proclamadores, sus turnos y el rol del
 * mes, y pide cambios de turno que la coordinación aprueba
 * (modules/mi/MiController.php). No administra nada: no hay usuario, ni
 * contraseña, ni rol.
 *
 * La coordinación lo saca con el botón de enlace de su lista: Ministros,
 * Proclamadores, Integrantes de Coros, Catequistas
 * (core/EnlacePersonalAcciones.php) o, en cualquier pastoral, «Quiénes están
 * en esta pastoral» en su panel (PastoralController::enlacePersonal()).
 *
 * El token son 32 caracteres hexadecimales al azar (128 bits): nadie lo
 * adivina, y cabe en las URL del sitio, que solo aceptan minúsculas y dígitos
 * en cada segmento. Uno vigente por persona; regenerarlo lo cambia y el
 * anterior deja de servir. Se guarda tal cual, no su hash, para que la
 * coordinación pueda volver a copiarlo cuando alguien lo pierde (ver
 * docs/migraciones/2026-11-15-enlace-personal.sql).
 *
 * El enlace se apaga solo, sin que nadie tenga que acordarse: vale mientras la
 * ficha de la persona esté activa, y cada módulo se le muestra mientras siga
 * activa en esa lista (ministros, proclamadores) y marcada en esa pastoral.
 */
class EnlacePersonal extends Model
{
    /**
     * La cookie con la que el teléfono recuerda el enlace después de abrirlo
     * una vez. SameSite=Lax y no Strict, como la de la sesión: el enlace llega
     * por WhatsApp, y una cookie Strict no viaja cuando se toca un enlace desde
     * otra aplicación.
     */
    public const COOKIE = 'nsdlp_mi';

    /**
     * Las listas propias de un módulo con botón de enlace. Cada renglón es
     * alguien de la pastoral (id, pastoral_id, persona_id, nombre, telefono,
     * activo); el enlace sale de su ficha, persona_id. Lista cerrada: el
     * nombre de la tabla va dentro del SQL.
     */
    public const LISTAS = ['mesc_ministros', 'proclamadores', 'coristas', 'catequesis_catequistas'];

    /**
     * Lo que verá con el enlace, para el mensaje de WhatsApp: «Ahí ves …».
     *
     * @param string $pastoral  el nombre con el que se le conoce: «MESC», «Coros»
     * @param bool   $turnos    si la pastoral lleva turnos (MESC, Proclamadores)
     */
    public static function queVe(string $pastoral, bool $turnos): string
    {
        return $turnos
            ? "tus turnos de {$pastoral} y el rol del mes, y puedes pedir cambios de turno; también los avisos, documentos y eventos de tu pastoral"
            : "los avisos, documentos y eventos de {$pastoral}";
    }

    /** Lo mismo, dicho a la coordinación en el cuadro del botón. */
    public static function explicacion(string $pastoral, bool $turnos): string
    {
        return ($turnos
            ? 'Con este enlace ve sus turnos y el rol del mes, y pide cambios de turno que la coordinación aprueba; '
              . "también los avisos, eventos y documentos de {$pastoral}."
            : "Con este enlace ve los avisos, eventos, actividades y documentos que {$pastoral} publica para su equipo.")
            . ' No administra nada y no necesita contraseña.';
    }

    public static function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
    }

    /** La dirección completa del enlace, para copiarla o mandarla. */
    public static function url(string $token): string
    {
        return url_absoluta(url_publica('mi', ['slug' => $token]));
    }

    /**
     * La persona dueña del enlace, si sigue vigente (ficha activa), o null.
     * Trae `llamarle`, el nombre con que la saluda su página (comoLlamarle()).
     */
    public function personaPorToken(string $token): ?array
    {
        if (!self::tokenValido($token)) {
            return null;
        }
        $persona = $this->fetchOne(
            'SELECT p.id, p.nombre, p.foto, e.token, e.ultimo_uso_at, m.nombre AS nombre_corto
               FROM persona_enlaces e
               JOIN personas p ON p.id = e.persona_id
               LEFT JOIN mesc_ministros m ON m.persona_id = p.id
              WHERE e.token = :token AND p.activo = 1',
            [':token' => $token]
        );
        if ($persona) {
            $persona['llamarle'] = self::nombreParaHablarle($persona['nombre'], $persona['nombre_corto']);
        }
        return $persona;
    }

    /**
     * Cómo se le habla a alguien, igual en su página («Hola, Male») que en el
     * mensaje con su enlace: su nombre corto si tiene, y si no, su nombre de
     * pila. El nombre corto solo lo guarda MESC (la columna «Nombre corto» de
     * Ministros); las listas de Proclamadores, Coros y Catequesis guardan el
     * nombre completo, igual que la ficha.
     */
    public function comoLlamarle(int $personaId): string
    {
        $fila = $this->fetchOne(
            'SELECT p.nombre, m.nombre AS nombre_corto
               FROM personas p
               LEFT JOIN mesc_ministros m ON m.persona_id = p.id
              WHERE p.id = :id',
            [':id' => $personaId]
        );
        return $fila ? self::nombreParaHablarle($fila['nombre'], $fila['nombre_corto']) : '';
    }

    /**
     * El nombre corto, si lo hay y no es el nombre completo repetido (así lo
     * capturan a veces); si no, la primera palabra del nombre: «María Elena
     * Ruiz Soto» → «María», pero con nombre corto «Male».
     */
    public static function nombreParaHablarle(string $nombre, ?string $corto): string
    {
        $nombre = trim($nombre);
        $corto  = trim((string) $corto);
        if ($corto !== '' && mb_strtolower($corto) !== mb_strtolower($nombre)) {
            return $corto;
        }
        return strtok($nombre, ' ') ?: $nombre;
    }

    public function dePersona(int $personaId): ?array
    {
        return $this->fetchOne('SELECT * FROM persona_enlaces WHERE persona_id = :id', [':id' => $personaId]);
    }

    /**
     * El enlace de la persona, creándolo si todavía no tiene: no hay que dar
     * de alta a nadie, el enlace nace la primera vez que alguien lo pide.
     *
     * @return array{0: array, 1: bool} la fila y si se acaba de crear
     */
    public function obtenerOCrear(int $personaId, ?int $usuarioId): array
    {
        $fila = $this->dePersona($personaId);
        if ($fila) {
            return [$fila, false];
        }
        // INSERT IGNORE: si otra coordinadora lo creó en este mismo instante,
        // gana la suya y se devuelve esa.
        $this->execute(
            'INSERT IGNORE INTO persona_enlaces (persona_id, token, creado_por) VALUES (:persona, :token, :usuario)',
            [':persona' => $personaId, ':token' => self::tokenNuevo(), ':usuario' => $usuarioId]
        );
        return [$this->dePersona($personaId), true];
    }

    /** Cambia el token: el enlace anterior deja de servir en ese momento, en cualquier teléfono. */
    public function regenerar(int $personaId, ?int $usuarioId): array
    {
        $this->execute(
            'INSERT INTO persona_enlaces (persona_id, token, creado_por) VALUES (:persona, :token, :usuario)
             ON DUPLICATE KEY UPDATE token = VALUES(token), creado_por = VALUES(creado_por),
                                     created_at = NOW(), ultimo_uso_at = NULL',
            [':persona' => $personaId, ':token' => self::tokenNuevo(), ':usuario' => $usuarioId]
        );
        return $this->dePersona($personaId);
    }

    /** Lo abrió: así la coordinación sabe si ya le llegó. */
    public function registrarUso(int $personaId): void
    {
        $this->execute('UPDATE persona_enlaces SET ultimo_uso_at = NOW() WHERE persona_id = :id', [':id' => $personaId]);
    }

    /**
     * El estado del enlace de varias personas, para pintar el botón de cada
     * renglón de una lista sin una consulta por renglón.
     *
     * @return array<int, array{created_at: string, ultimo_uso_at: ?string}> por persona_id
     */
    public function estados(array $personaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $personaIds))));
        if (!$ids) {
            return [];
        }
        $params = [];
        foreach ($ids as $i => $id) {
            $params[":p{$i}"] = $id;
        }
        $estados = [];
        foreach ($this->fetchAll(
            'SELECT persona_id, created_at, ultimo_uso_at FROM persona_enlaces
              WHERE persona_id IN (' . implode(',', array_keys($params)) . ')',
            $params
        ) as $fila) {
            $estados[(int) $fila['persona_id']] = $fila;
        }
        return $estados;
    }

    /**
     * Un renglón de una de las LISTAS con lo que hace falta para su enlace: su
     * ficha, si las dos están activas y el teléfono (el de la lista, o el de
     * la ficha si en la lista no hay). Solo si es de esa pastoral.
     */
    public function fichaDeLista(string $tabla, int $id, int $pastoralId): ?array
    {
        if (!in_array($tabla, self::LISTAS, true)) {
            throw new InvalidArgumentException("La lista {$tabla} no tiene enlace personal.");
        }
        return $this->fetchOne(
            "SELECT l.id, l.nombre, l.activo, l.persona_id,
                    p.nombre AS nombre_completo, p.activo AS persona_activa,
                    COALESCE(NULLIF(l.telefono, ''), p.telefono) AS telefono
               FROM {$tabla} l
               LEFT JOIN personas p ON p.id = l.persona_id
              WHERE l.id = :id AND l.pastoral_id = :pastoral",
            [':id' => $id, ':pastoral' => $pastoralId]
        );
    }

    /** ¿Está marcada en esa pastoral, en su ficha del equipo pastoral? */
    public function perteneceA(int $personaId, int $pastoralId): bool
    {
        return (bool) $this->fetchColumn(
            'SELECT 1 FROM persona_pastorales WHERE persona_id = :persona AND pastoral_id = :pastoral',
            [':persona' => $personaId, ':pastoral' => $pastoralId]
        );
    }

    /** La cuenta del panel ligada a esta persona, si tiene: lo que pidió con ella también es suyo. */
    public function cuentaDePersona(int $personaId): ?int
    {
        return ((int) $this->fetchColumn(
            'SELECT id FROM usuarios WHERE persona_id = :persona AND activo = 1 LIMIT 1',
            [':persona' => $personaId]
        )) ?: null;
    }

    /** «Lo abrió por última vez el 12 de noviembre», «Todavía no lo abre»: para la coordinación. */
    public static function textoEstado(?array $fila): string
    {
        if (!$fila) {
            return 'Todavía no tiene enlace.';
        }
        if (!$fila['ultimo_uso_at']) {
            return 'Todavía no lo abre.';
        }
        return 'Lo abrió por última vez el ' . fecha_larga(substr((string) $fila['ultimo_uso_at'], 0, 10)) . '.';
    }

    private static function tokenNuevo(): string
    {
        return bin2hex(random_bytes(16));
    }
}
