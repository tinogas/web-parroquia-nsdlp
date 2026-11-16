<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CambiosTurno — Permutas, coberturas y refuerzos registrados sobre el rol de
 * turnos ya armado. Lo comparten MESC y Proclamadores: sus tablas de turnos
 * son calcadas una de la otra, así que el mismo código atiende a las dos y
 * solo cambian los nombres de tabla (ver las constantes MESC y PROCLAMADORES).
 *
 * El rol ORIGINAL nunca se toca: sigue en el pivote de cada módulo
 * (mesc_turno_ministros / proclamadores_turno_proclamadores). Quién sirve
 * realmente en un turno se calcula partiendo de ese rol y aplicando encima,
 * en el orden en que se registraron, los movimientos de `*_cambio_movimientos`
 * —cada uno «sale X, entra Y», solo «entra Y» si es un refuerzo, o solo
 * «sale X» en una permuta de grupos dispares, dos por uno—. Así la consulta
 * puede mostrar las dos cosas a la vez, y deshacer un cambio es borrar su
 * fila: el rol original reaparece solo.
 *
 * Una permuta es de grupo a grupo: uno o varios de un turno por uno o varios
 * del otro. Marcando a todos en los dos lados se intercambian los turnos
 * completos. Cómo se emparejan las filas por dentro (quién «entra por» quién)
 * no significa nada: el cálculo solo mira quiénes salen y quiénes entran.
 *
 * Aplicar en orden es lo que permite encadenar cambios: si Zulema le pasa el
 * domingo 5 a Sandra y luego Sandra se lo pasa a Hiram, el segundo «sale
 * Sandra» encuentra a Sandra porque el primero ya la había puesto ahí. Un
 * movimiento que ya no encuentra a quien sale (porque se editó el rol
 * original, o se borró un cambio anterior de la cadena) simplemente no quita
 * a nadie: el cálculo nunca truena, a lo más muestra a alguien de más.
 *
 * A la persona del turno se le llama aquí «persona» a secas —ministro en MESC,
 * proclamador en Proclamadores—; las vistas ponen la palabra de cada módulo.
 */
class CambiosTurno extends Model
{
    public const TIPOS = [
        'permuta'     => 'Permuta',
        'sustitucion' => 'Cubre a otro',
        'agregado'    => 'Se agrega',
    ];

    public const MESC = [
        'cambios'     => 'mesc_cambios',
        'movimientos' => 'mesc_cambio_movimientos',
        'turnos'      => 'mesc_turnos',
        'pivote'      => 'mesc_turno_ministros',
        'pivote_col'  => 'ministro_id',
        'personas'    => 'mesc_ministros',
    ];

    public const PROCLAMADORES = [
        'cambios'     => 'proclamadores_cambios',
        'movimientos' => 'proclamadores_cambio_movimientos',
        'turnos'      => 'proclamadores_turnos',
        'pivote'      => 'proclamadores_turno_proclamadores',
        'pivote_col'  => 'proclamador_id',
        'personas'    => 'proclamadores',
    ];

    /** @param array $t una de las constantes MESC / PROCLAMADORES: los nombres son fijos, nunca vienen del usuario. */
    public function __construct(private array $t)
    {
        parent::__construct();
    }

    /**
     * Agrega a cada turno su rol original y el real, ya con los cambios:
     *
     *   originales  [id => nombre]  quiénes estaban en el rol
     *   actuales    [id => nombre]  quiénes sirven, después de los cambios
     *   entraron    [id => nombre]  están en actuales y no estaban en el rol
     *   salieron    [id => nombre]  estaban en el rol y ya no sirven
     *   tiene_cambios bool          si algún cambio tocó este turno
     *   nombres_actuales / nombres_originales  string, «A, B», para el calendario
     *
     * Dos consultas para todo el mes, no dos por turno.
     */
    public function aplicar(array $turnos): array
    {
        if (!$turnos) {
            return [];
        }
        [$enLista, $params] = $this->enLista(array_column($turnos, 'id'));

        $originales = [];
        foreach ($this->fetchAll(
            "SELECT tp.turno_id, p.id, p.nombre
               FROM {$this->t['pivote']} tp
               JOIN {$this->t['personas']} p ON p.id = tp.{$this->t['pivote_col']}
              WHERE tp.turno_id IN ({$enLista})",
            $params
        ) as $fila) {
            $originales[(int) $fila['turno_id']][(int) $fila['id']] = $fila['nombre'];
        }

        $movimientos = [];
        foreach ($this->fetchAll(
            "SELECT m.turno_id, m.sale_id, m.entra_id, pe.nombre AS entra_nombre
               FROM {$this->t['movimientos']} m
               JOIN {$this->t['cambios']} c ON c.id = m.cambio_id AND c.estado = 'aprobado'
               LEFT JOIN {$this->t['personas']} pe ON pe.id = m.entra_id
              WHERE m.turno_id IN ({$enLista})
              ORDER BY m.cambio_id, m.id",
            $params
        ) as $fila) {
            $movimientos[(int) $fila['turno_id']][] = $fila;
        }

        foreach ($turnos as &$turno) {
            $id       = (int) $turno['id'];
            $original = $originales[$id] ?? [];
            $actual   = $original;
            foreach ($movimientos[$id] ?? [] as $mov) {
                if ($mov['sale_id'] !== null) {
                    unset($actual[(int) $mov['sale_id']]);
                }
                if ($mov['entra_id'] !== null) {
                    $actual[(int) $mov['entra_id']] = $mov['entra_nombre'];
                }
            }
            natcasesort($original);
            natcasesort($actual);

            $turno['originales']         = $original;
            $turno['actuales']           = $actual;
            $turno['entraron']           = array_diff_key($actual, $original);
            $turno['salieron']           = array_diff_key($original, $actual);
            $turno['tiene_cambios']      = !empty($movimientos[$id]);
            $turno['nombres_originales'] = implode(', ', $original);
            $turno['nombres_actuales']   = implode(', ', $actual);
        }
        unset($turno);

        return $turnos;
    }

    /**
     * Turnos de la pastoral entre dos fechas (ambas incluidas), ya con su rol
     * real: los que se ofrecen al registrar un cambio.
     */
    public function turnosEntre(int $pastoralId, string $desde, string $hasta): array
    {
        return $this->aplicar($this->fetchAll(
            "SELECT t.id, t.fecha, t.hora, t.descripcion
               FROM {$this->t['turnos']} t
              WHERE t.pastoral_id = :pastoral AND t.fecha BETWEEN :desde AND :hasta
              ORDER BY t.fecha, t.hora",
            [':pastoral' => $pastoralId, ':desde' => $desde, ':hasta' => $hasta]
        ));
    }

    /** Un turno con su rol real, o null si no es de esta pastoral. */
    public function turnoConRol(int $turnoId, int $pastoralId): ?array
    {
        $turno = $this->fetchOne(
            "SELECT id, fecha, hora, descripcion FROM {$this->t['turnos']} WHERE id = :id AND pastoral_id = :pastoral",
            [':id' => $turnoId, ':pastoral' => $pastoralId]
        );
        return $turno ? $this->aplicar([$turno])[0] : null;
    }

    /**
     * Qué puede mostrar el historial de cambios a quien coordina. clave =>
     * etiqueta. Una solicitud se reconoce por `resuelto_at` —se llena al
     * aprobarla o rechazarla—, no por `resuelto_por`, que queda en NULL si la
     * cuenta de quien la resolvió se borra.
     */
    public const HISTORIAL = [
        'todos'      => 'Todo',
        'directos'   => 'Registrados por la coordinación',
        'aprobadas'  => 'Solicitudes aprobadas',
        'rechazadas' => 'Solicitudes rechazadas',
        'deshechos'  => 'Deshechos',
        'retiradas'  => 'Retiradas',
    ];

    /**
     * Cambios que tocan algún turno entre esas fechas, cada uno con sus
     * movimientos ya legibles, de la actividad más reciente a la más vieja
     * (para una solicitud cuenta cuándo se resolvió). Una permuta entre el 30
     * de noviembre y el 7 de diciembre sale en los dos meses.
     *
     * $mostrar: 'vigentes' (lo que está aplicado en el calendario: lo que ve
     * el laico) o una clave de HISTORIAL. Las pendientes nunca salen aquí:
     * tienen su propio recuadro, «Solicitudes por aprobar».
     */
    public function cambiosEntre(int $pastoralId, string $desde, string $hasta, string $mostrar = 'vigentes'): array
    {
        $condicion = match ($mostrar) {
            'todos'      => "c.estado <> 'pendiente'",
            'directos'   => "c.estado = 'aprobado' AND c.resuelto_at IS NULL",
            'aprobadas'  => "c.estado = 'aprobado' AND c.resuelto_at IS NOT NULL",
            'rechazadas' => "c.estado = 'rechazado'",
            'deshechos'  => "c.estado = 'deshecho'",
            'retiradas'  => "c.estado = 'retirado'",
            default      => "c.estado = 'aprobado'",
        };

        return $this->conMovimientos($this->fetchAll(
            "SELECT DISTINCT c.*, u.nombre AS autor, r.nombre AS resolvio, x.nombre AS cancelo
               FROM {$this->t['cambios']} c
               JOIN {$this->t['movimientos']} m ON m.cambio_id = c.id
               JOIN {$this->t['turnos']} t ON t.id = m.turno_id
               LEFT JOIN usuarios u ON u.id = c.usuario_id
               LEFT JOIN usuarios r ON r.id = c.resuelto_por
               LEFT JOIN usuarios x ON x.id = c.cancelado_por
              WHERE c.pastoral_id = :pastoral AND {$condicion}
                AND t.fecha BETWEEN :desde AND :hasta
              ORDER BY COALESCE(c.cancelado_at, c.resuelto_at, c.created_at) DESC, c.id DESC",
            [':pastoral' => $pastoralId, ':desde' => $desde, ':hasta' => $hasta]
        ));
    }

    /**
     * Cuántos hay de cada clase de HISTORIAL entre esas fechas, para las
     * pestañas del historial: así se ve de un vistazo cuántas solicitudes se
     * aprobaron y cuántas se rechazaron, sin abrir cada filtro.
     *
     * @return array{todos: int, directos: int, aprobadas: int, rechazadas: int, deshechos: int, retiradas: int}
     */
    public function conteoHistorial(int $pastoralId, string $desde, string $hasta): array
    {
        $fila = $this->fetchOne(
            "SELECT SUM(c.estado = 'aprobado' AND c.resuelto_at IS NULL)     AS directos,
                    SUM(c.estado = 'aprobado' AND c.resuelto_at IS NOT NULL) AS aprobadas,
                    SUM(c.estado = 'rechazado')                              AS rechazadas,
                    SUM(c.estado = 'deshecho')                               AS deshechos,
                    SUM(c.estado = 'retirado')                               AS retiradas
               FROM {$this->t['cambios']} c
              WHERE c.pastoral_id = :pastoral
                AND EXISTS (SELECT 1 FROM {$this->t['movimientos']} m
                              JOIN {$this->t['turnos']} t ON t.id = m.turno_id
                             WHERE m.cambio_id = c.id AND t.fecha BETWEEN :desde AND :hasta)",
            [':pastoral' => $pastoralId, ':desde' => $desde, ':hasta' => $hasta]
        ) ?? [];

        $conteo = [
            'directos'   => (int) ($fila['directos'] ?? 0),
            'aprobadas'  => (int) ($fila['aprobadas'] ?? 0),
            'rechazadas' => (int) ($fila['rechazadas'] ?? 0),
            'deshechos'  => (int) ($fila['deshechos'] ?? 0),
            'retiradas'  => (int) ($fila['retiradas'] ?? 0),
        ];
        return ['todos' => array_sum($conteo)] + $conteo;
    }

    /** Cambios que tocan ese turno, para mostrarlos en su ficha. */
    public function cambiosDeTurno(int $turnoId): array
    {
        return $this->conMovimientos($this->fetchAll(
            "SELECT DISTINCT c.*, u.nombre AS autor, r.nombre AS resolvio
               FROM {$this->t['cambios']} c
               JOIN {$this->t['movimientos']} m ON m.cambio_id = c.id
               LEFT JOIN usuarios u ON u.id = c.usuario_id
               LEFT JOIN usuarios r ON r.id = c.resuelto_por
              WHERE m.turno_id = :turno AND c.estado = 'aprobado'
              ORDER BY c.created_at, c.id",
            [':turno' => $turnoId]
        ));
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM {$this->t['cambios']} WHERE id = :id", [':id' => $id]);
    }

    /**
     * Guarda el cambio con sus movimientos, todo o nada. La validación (que
     * quien sale esté en el turno, que quien entra no esté ya) la hace el
     * controlador antes de llegar aquí, contra el rol real.
     *
     * @param array $movimientos lista de ['turno_id' => int, 'sale_id' => ?int, 'entra_id' => ?int],
     *                           nunca los dos en null
     * @param string $estado     'aprobado' si lo registra la coordinación; 'pendiente' si es la solicitud de un laico
     */
    public function registrar(int $pastoralId, string $tipo, array $movimientos, ?string $notas, int $usuarioId, string $estado = 'aprobado'): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                "INSERT INTO {$this->t['cambios']} (pastoral_id, tipo, notas, usuario_id, estado)
                 VALUES (:pastoral, :tipo, :notas, :usuario, :estado)",
                [':pastoral' => $pastoralId, ':tipo' => $tipo, ':notas' => $notas, ':usuario' => $usuarioId, ':estado' => $estado]
            );
            $cambioId = $this->lastInsertId();
            foreach ($movimientos as $mov) {
                $this->execute(
                    "INSERT INTO {$this->t['movimientos']} (cambio_id, turno_id, sale_id, entra_id)
                     VALUES (:cambio, :turno, :sale, :entra)",
                    [
                        ':cambio' => $cambioId,
                        ':turno'  => $mov['turno_id'],
                        ':sale'   => $mov['sale_id'],
                        ':entra'  => $mov['entra_id'],
                    ]
                );
            }
            $this->commit();
            return $cambioId;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Borra el cambio de verdad, con sus movimientos. Ninguna pantalla lo usa
     * —deshacer y retirar dejan rastro, ver deshacer() y retirarSolicitud()—;
     * queda para limpiar datos de prueba.
     */
    public function eliminar(int $id): int
    {
        return $this->execute("DELETE FROM {$this->t['cambios']} WHERE id = :id", [':id' => $id]);
    }

    /**
     * Deshace un cambio aplicado sin borrarlo: pasa a 'deshecho', deja de
     * contar para el calendario —el turno vuelve a como estaba— y queda en la
     * bitácora con quién lo deshizo, cuándo y por qué. Si venía de una
     * solicitud, la campanita se lo avisa a quien la pidió.
     */
    public function deshacer(int $id, int $usuarioId, ?string $motivo): int
    {
        return $this->execute(
            "UPDATE {$this->t['cambios']}
                SET estado = 'deshecho', cancelado_por = :usuario, cancelado_at = NOW(),
                    motivo_cancelacion = :motivo,
                    visto_solicitante = IF(resuelto_at IS NULL, 1, 0)
              WHERE id = :id AND estado = 'aprobado'",
            [':usuario' => $usuarioId, ':motivo' => $motivo, ':id' => $id]
        );
    }

    /**
     * Cambios aplicados registrados DESPUÉS de este en alguno de sus turnos. Si
     * los hay, deshacer este dejaría al otro a medias —«Sandra le pasa a Hiram
     * el turno que Zulema le había pasado a ella»—, así que primero se deshace
     * el más reciente.
     */
    public function hayCambiosPosteriores(array $cambio): bool
    {
        $turnos = array_unique(array_map(static fn (array $m): int => (int) $m['turno_id'], $cambio['movimientos']));
        if (!$turnos) {
            return false;
        }
        [$enLista, $params] = $this->enLista($turnos);
        return (bool) $this->fetchColumn(
            "SELECT 1 FROM {$this->t['movimientos']} m
               JOIN {$this->t['cambios']} c ON c.id = m.cambio_id
              WHERE m.turno_id IN ({$enLista}) AND c.estado = 'aprobado' AND c.id > :cambio
              LIMIT 1",
            $params + [':cambio' => (int) $cambio['id']]
        );
    }

    /**
     * ¿Toca algún turno de fecha anterior a hoy? Lo que ya pasó no se mueve:
     * no se registra, no se aprueba, no se deshace y no se retira. Cuenta la
     * fecha, no la hora: un turno de hoy todavía admite cambios.
     */
    public static function yaPaso(array $cambio): bool
    {
        $hoy = date('Y-m-d');
        foreach ($cambio['movimientos'] ?? [] as $mov) {
            if ((string) $mov['fecha'] < $hoy) {
                return true;
            }
        }
        return false;
    }

    // ── Solicitudes ──────────────────────────────────────────────────────
    // Lo que pide un laico (rol Consulta) nace 'pendiente' y no toca el
    // calendario —aplicar() solo mira los aprobados— hasta que una
    // coordinación de la pastoral lo resuelve.

    /** Solicitudes por resolver de la pastoral, de la más vieja a la más nueva: se atienden en el orden en que llegaron. */
    public function pendientes(int $pastoralId, ?int $limite = null): array
    {
        $limit = $limite ? ' LIMIT ' . (int) $limite : '';
        return $this->conMovimientos($this->fetchAll(
            "SELECT c.*, u.nombre AS autor
               FROM {$this->t['cambios']} c
               LEFT JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.pastoral_id = :pastoral AND c.estado = 'pendiente'
              ORDER BY c.created_at, c.id{$limit}",
            [':pastoral' => $pastoralId]
        ));
    }

    public function contarPendientes(int $pastoralId): int
    {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM {$this->t['cambios']} WHERE pastoral_id = :pastoral AND estado = 'pendiente'",
            [':pastoral' => $pastoralId]
        );
    }

    /**
     * Lo que pidió esta cuenta: las pendientes y las ya resueltas, de la más
     * nueva a la más vieja. Un cambio aprobado sin `resuelto_at` no es una
     * solicitud —lo registró directamente la coordinación— y no sale aquí,
     * aunque lo haya capturado la misma persona desde otro perfil.
     */
    public function solicitudesDe(int $usuarioId, int $pastoralId, int $limite = 20): array
    {
        return $this->conMovimientos($this->fetchAll(
            "SELECT c.*, r.nombre AS resolvio, x.nombre AS cancelo
               FROM {$this->t['cambios']} c
               LEFT JOIN usuarios r ON r.id = c.resuelto_por
               LEFT JOIN usuarios x ON x.id = c.cancelado_por
              WHERE c.usuario_id = :usuario AND c.pastoral_id = :pastoral
                AND (c.resuelto_at IS NOT NULL OR c.estado IN ('pendiente', 'retirado'))
              ORDER BY c.created_at DESC, c.id DESC
              LIMIT " . (int) $limite,
            [':usuario' => $usuarioId, ':pastoral' => $pastoralId]
        ));
    }

    /** Respuestas que el solicitante todavía no ha visto: lo que su campanita le anuncia. */
    public function respuestasNoVistas(int $usuarioId, int $pastoralId): array
    {
        return $this->conMovimientos($this->fetchAll(
            "SELECT c.*, r.nombre AS resolvio, x.nombre AS cancelo
               FROM {$this->t['cambios']} c
               LEFT JOIN usuarios r ON r.id = c.resuelto_por
               LEFT JOIN usuarios x ON x.id = c.cancelado_por
              WHERE c.usuario_id = :usuario AND c.pastoral_id = :pastoral
                AND c.estado <> 'pendiente' AND c.visto_solicitante = 0
              ORDER BY COALESCE(c.cancelado_at, c.resuelto_at) DESC",
            [':usuario' => $usuarioId, ':pastoral' => $pastoralId]
        ));
    }

    public function marcarRespuestasVistas(int $usuarioId, int $pastoralId): void
    {
        $this->execute(
            "UPDATE {$this->t['cambios']} SET visto_solicitante = 1
              WHERE usuario_id = :usuario AND pastoral_id = :pastoral AND visto_solicitante = 0",
            [':usuario' => $usuarioId, ':pastoral' => $pastoralId]
        );
    }

    /**
     * Aprueba o rechaza una solicitud pendiente. Devuelve 0 si ya no estaba
     * pendiente —otra coordinadora la resolvió primero, o la cancelaron—,
     * para no pisar esa respuesta.
     */
    public function resolver(int $id, string $estado, int $usuarioId, ?string $motivo = null): int
    {
        return $this->execute(
            "UPDATE {$this->t['cambios']}
                SET estado = :estado, resuelto_por = :usuario, resuelto_at = NOW(),
                    motivo_rechazo = :motivo, visto_solicitante = 0
              WHERE id = :id AND estado = 'pendiente'",
            [':estado' => $estado, ':usuario' => $usuarioId, ':motivo' => $motivo, ':id' => $id]
        );
    }

    /**
     * El solicitante retira su propia solicitud mientras sigue pendiente. No
     * se borra: queda 'retirado' en la bitácora, con cuándo se retiró.
     */
    public function retirarSolicitud(int $id, int $usuarioId): int
    {
        return $this->execute(
            "UPDATE {$this->t['cambios']}
                SET estado = 'retirado', cancelado_por = :quien, cancelado_at = NOW()
              WHERE id = :id AND usuario_id = :solicitante AND estado = 'pendiente'",
            [':id' => $id, ':quien' => $usuarioId, ':solicitante' => $usuarioId]
        );
    }

    /**
     * ¿Sigue siendo posible esta solicitud con el rol de HOY? Entre que se pide
     * y que se aprueba pueden pasar días, y en ese tiempo pudo registrarse otro
     * cambio o editarse el rol: quien sale ya no está, o quien entra ya entró.
     * Devuelve el motivo, o null si se puede aprobar tal cual.
     */
    public function conflicto(array $cambio, int $pastoralId): ?string
    {
        $grupos = [];
        foreach ($cambio['movimientos'] as $mov) {
            $grupos[(int) $mov['turno_id']][] = $mov;
        }
        if ($cambio['tipo'] === 'permuta' && count($grupos) !== 2) {
            return 'Uno de los dos turnos de la permuta ya no existe.';
        }
        if (!$grupos) {
            return 'El turno de esta solicitud ya no existe.';
        }
        if (self::yaPaso($cambio)) {
            return 'el turno ya pasó, y lo que ya pasó no se mueve.';
        }
        foreach ($grupos as $turnoId => $movs) {
            $turno = $this->turnoConRol($turnoId, $pastoralId);
            if (!$turno) {
                return 'Uno de los turnos ya no existe.';
            }
            $donde = self::turnoCorto($turno);
            foreach ($movs as $mov) {
                if ($mov['sale_id'] !== null && !isset($turno['actuales'][(int) $mov['sale_id']])) {
                    return "{$mov['sale_nombre']} ya no está en el turno del {$donde}.";
                }
                if ($mov['entra_id'] !== null && isset($turno['actuales'][(int) $mov['entra_id']])) {
                    return "{$mov['entra_nombre']} ya está en el turno del {$donde}.";
                }
            }
        }
        return null;
    }

    /** El cambio con sus movimientos, o null si no es de esta pastoral. */
    public function conDetalle(int $id, int $pastoralId): ?array
    {
        $cambio = $this->fetchOne(
            "SELECT c.*, u.nombre AS autor FROM {$this->t['cambios']} c
               LEFT JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.id = :id AND c.pastoral_id = :pastoral",
            [':id' => $id, ':pastoral' => $pastoralId]
        );
        return $cambio ? $this->conMovimientos([$cambio])[0] : null;
    }

    /**
     * La ficha del módulo (mesc_ministros / proclamadores) que corresponde a
     * una persona del equipo pastoral: así se sabe quién es «yo» cuando un
     * laico pide un cambio. Null si su cuenta no está ligada a una ficha, o si
     * esa ficha no está en este módulo.
     */
    public function personaDelModulo(int $pastoralId, ?int $personaId): ?array
    {
        if (!$personaId) {
            return null;
        }
        return $this->fetchOne(
            "SELECT id, nombre, activo FROM {$this->t['personas']} WHERE pastoral_id = :pastoral AND persona_id = :persona",
            [':pastoral' => $pastoralId, ':persona' => $personaId]
        );
    }

    /**
     * Lo que la campanita del panel anuncia sobre cambios de turno, en MESC y
     * en Proclamadores: a quien coordina, las solicitudes por resolver de su
     * pastoral; a quien pidió, las respuestas que todavía no ha visto. Se
     * calcula al vuelo, como el resto de la campanita: una solicitud deja de
     * anunciarse en cuanto alguien la resuelve, sin tabla de notificaciones
     * que mantener al día.
     *
     * @return array{total: int, grupos: array} cada grupo: titulo, total, url, items (texto, detalle, icono)
     */
    public static function paraCampana(int $usuarioId): array
    {
        $modulos = [
            'mesc'          => [self::MESC,          PASTORAL_MESC,          'MESC'],
            'proclamadores' => [self::PROCLAMADORES, PASTORAL_PROCLAMADORES, 'Proclamadores'],
        ];
        $total  = 0;
        $grupos = [];

        foreach ($modulos as $modulo => [$tablas, $slug, $nombre]) {
            if (!Auth::tienePermiso($modulo . '.ver') || !Auth::administraPastoral($slug)) {
                continue;
            }
            $modelo     = new self($tablas);
            $pastoralId = (int) $modelo->fetchColumn('SELECT id FROM pastorales WHERE slug = :slug', [':slug' => $slug]);
            if (!$pastoralId) {
                continue;
            }
            $url = url_admin($modulo, 'cambios');

            if (Auth::tienePermiso($modulo . '.editar')) {
                $n = $modelo->contarPendientes($pastoralId);
                if ($n) {
                    $items = [];
                    foreach ($modelo->pendientes($pastoralId, 5) as $c) {
                        $items[] = [
                            'texto'   => ($c['autor'] ?? 'Alguien') . ' pide un cambio',
                            'detalle' => self::resumen($c),
                            'icono'   => 'bi-arrow-left-right',
                        ];
                    }
                    $grupos[] = ['titulo' => "Solicitudes de cambio · {$nombre}", 'total' => $n, 'url' => $url, 'items' => $items];
                    $total += $n;
                }
            } elseif (Auth::tienePermiso($modulo . '.solicitar')) {
                $respuestas = $modelo->respuestasNoVistas($usuarioId, $pastoralId);
                if ($respuestas) {
                    $items = [];
                    foreach (array_slice($respuestas, 0, 5) as $c) {
                        [$texto, $motivo, $icono] = match ($c['estado']) {
                            'aprobado' => ['Tu solicitud fue aprobada', null, 'bi-check-circle'],
                            'deshecho' => ['Se deshizo tu cambio de turno', $c['motivo_cancelacion'], 'bi-arrow-counterclockwise'],
                            default    => ['Tu solicitud fue rechazada', $c['motivo_rechazo'], 'bi-x-circle'],
                        };
                        $items[] = [
                            'texto'   => $texto,
                            'detalle' => self::resumen($c) . ($motivo ? ' — ' . $motivo : ''),
                            'icono'   => $icono,
                        ];
                    }
                    $grupos[] = ['titulo' => "Tus solicitudes · {$nombre}", 'total' => count($respuestas), 'url' => $url, 'items' => $items];
                    $total += count($respuestas);
                }
            }
        }

        return ['total' => $total, 'grupos' => $grupos];
    }

    /**
     * Texto de una línea para la bitácora y los avisos: «Permuta: Zulema y
     * Tino (dom 5 nov, Misa 09:00) ⇄ Sandra e Hiram (dom 15 nov, Misa 09:00)».
     */
    public static function resumen(array $cambio): string
    {
        $grupos = self::porTurno($cambio['movimientos'] ?? []);
        $tipo   = self::TIPOS[$cambio['tipo']] ?? $cambio['tipo'];

        if ($cambio['tipo'] === 'permuta' && count($grupos) === 2) {
            return sprintf('%s: %s (%s) ⇄ %s (%s)', $tipo,
                self::unirNombres($grupos[0]['salen']), self::turnoCorto($grupos[0]['turno']),
                self::unirNombres($grupos[1]['salen']), self::turnoCorto($grupos[1]['turno']));
        }
        $partes = [];
        foreach ($grupos as $g) {
            $partes[] = self::unirNombres($g['entran'])
                . ($g['salen'] ? ' en lugar de ' . self::unirNombres($g['salen']) : '')
                . ' (' . self::turnoCorto($g['turno']) . ')';
        }
        return $tipo . ': ' . implode('; ', $partes);
    }

    /**
     * Los movimientos de un cambio agrupados por turno, en el orden en que se
     * registraron: [['turno' => fila con fecha/hora/descripcion, 'salen' =>
     * [nombres], 'entran' => [nombres]], …]. Así se muestran, porque en una
     * permuta de grupos lo que importa es quiénes dejan y quiénes toman cada
     * turno, no cómo quedaron emparejadas las filas.
     */
    public static function porTurno(array $movimientos): array
    {
        $grupos = [];
        foreach ($movimientos as $mov) {
            $id = (int) $mov['turno_id'];
            $grupos[$id] ??= ['turno' => $mov, 'salen' => [], 'entran' => []];
            if ($mov['sale_nombre'] !== null) {
                $grupos[$id]['salen'][] = $mov['sale_nombre'];
            }
            if ($mov['entra_nombre'] !== null) {
                $grupos[$id]['entran'][] = $mov['entra_nombre'];
            }
        }
        return array_values($grupos);
    }

    /** «Zulema», «Zulema y Tino», «Zulema, Tino e Hiram»: «e» delante de i/hi, como se escribe. */
    public static function unirNombres(array $nombres): string
    {
        $nombres = array_values($nombres);
        if (count($nombres) < 2) {
            return $nombres[0] ?? '';
        }
        $ultimo = array_pop($nombres);
        $conj   = preg_match('/^h?[iíIÍ](?![aeouáéóú])/iu', $ultimo) ? ' e ' : ' y ';
        return implode(', ', $nombres) . $conj . $ultimo;
    }

    /** «dom 5 nov, Misa 09:00»: lo justo para reconocer el turno en una lista. */
    public static function turnoCorto(array $turno): string
    {
        $dias  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $ts    = strtotime((string) $turno['fecha']);
        $texto = $dias[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1];
        $texto .= ', ' . trim(($turno['descripcion'] ?? '') . ' ' . ($turno['hora'] ? substr((string) $turno['hora'], 0, 5) : ''));
        return rtrim($texto, ', ');
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /** Anexa a cada cambio sus movimientos, con el turno y los nombres ya resueltos. */
    private function conMovimientos(array $cambios): array
    {
        if (!$cambios) {
            return [];
        }
        [$enLista, $params] = $this->enLista(array_column($cambios, 'id'));

        $porCambio = [];
        foreach ($this->fetchAll(
            "SELECT m.*, t.fecha, t.hora, t.descripcion,
                    ps.nombre AS sale_nombre, pe.nombre AS entra_nombre
               FROM {$this->t['movimientos']} m
               JOIN {$this->t['turnos']} t ON t.id = m.turno_id
               LEFT JOIN {$this->t['personas']} ps ON ps.id = m.sale_id
               LEFT JOIN {$this->t['personas']} pe ON pe.id = m.entra_id
              WHERE m.cambio_id IN ({$enLista})
              ORDER BY m.id",
            $params
        ) as $mov) {
            $porCambio[(int) $mov['cambio_id']][] = $mov;
        }

        foreach ($cambios as &$cambio) {
            $cambio['movimientos'] = $porCambio[(int) $cambio['id']] ?? [];
        }
        unset($cambio);
        return $cambios;
    }

    /** @return array{0: string, 1: array} marcadores «:i0,:i1» y sus valores */
    private function enLista(array $ids): array
    {
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $params[":i{$i}"] = (int) $id;
        }
        return [implode(',', array_keys($params)), $params];
    }
}
