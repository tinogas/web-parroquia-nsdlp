<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * ProclamadoresModel — Calendario de turnos y catálogo de proclamadores.
 * Calcado de MescModel (turnos + ministros), sin rutas ni visitas: quien
 * proclama la Palabra lo hace en misa, no reparte comunión a domicilio; esa
 * es toda la diferencia entre los dos módulos.
 *
 * color_liturgico_id apunta a `colores_liturgicos`, catálogo compartido: el
 * significado litúrgico de cada color es el mismo para toda la parroquia, no
 * propio de un módulo. Los métodos que lo leen y lo escriben están repetidos
 * en MescModel, con las mismas cinco consultas —los dos módulos ofrecen la
 * misma pantalla sobre la misma tabla, cada uno con sus permisos—.
 */
class ProclamadoresModel extends Model
{
    /**
     * Qué prefiere hacer cada quien al proclamar. Los tres valores son los que
     * la propia pastoral usó al levantar su lista (Proclamadores.xlsx): el
     * monitor guía a la asamblea, la lectura es la Palabra proclamada, y el
     * salmo va cantado y no todos lo hacen. clave => nombre visible, y las
     * claves son los miembros de la columna SET `proclamadores.preferencias`.
     *
     * No limita nada: son la preferencia de la persona, no un permiso, y la
     * coordinación asigna el turno como haga falta.
     */
    public const PREFERENCIAS = [
        'monitor' => 'Monitor',
        'lectura' => 'Lectura',
        'salmo'   => 'Salmo (cantado)',
    ];

    /** La única pastoral que administra este módulo, resuelta por slug (no por id fijo: los id no se siembran en install.sql). */
    public function pastoralId(): ?int
    {
        return $this->fetchColumn(
            'SELECT id FROM pastorales WHERE slug = :slug',
            [':slug' => PASTORAL_PROCLAMADORES]
        ) ?: null;
    }

    // ── Proclamadores ────────────────────────────────────────────────────

    public function proclamadores(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM proclamadores WHERE pastoral_id = :id ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function proclamadoresActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM proclamadores WHERE pastoral_id = :id AND activo = 1 ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function proclamadorPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM proclamadores WHERE id = :id', [':id' => $id]);
    }

    public function crearProclamador(array $datos): int
    {
        $this->execute(
            'INSERT INTO proclamadores (pastoral_id, persona_id, nombre, telefono, email, preferencias, orden, activo)
             VALUES (:pastoral, :persona, :nombre, :telefono, :email, :preferencias, :orden, :activo)',
            $this->parametrosProclamador($datos) + [':pastoral' => $datos['pastoral_id']]
        );
        return $this->lastInsertId();
    }

    public function actualizarProclamador(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE proclamadores
                SET persona_id = :persona, nombre = :nombre, telefono = :telefono, email = :email,
                    preferencias = :preferencias, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametrosProclamador($datos) + [':id' => $id]
        );
    }

    public function eliminarProclamador(int $id): int
    {
        return $this->execute('DELETE FROM proclamadores WHERE id = :id', [':id' => $id]);
    }

    /**
     * Sin :pastoral: pastoral_id se fija una sola vez al crear (este módulo
     * es exclusivo de una única pastoral) y actualizarProclamador() no lo
     * toca — incluirlo aquí rompía el UPDATE con PDO::ATTR_EMULATE_PREPARES
     * en false, que rechaza cualquier parámetro que el SQL no declare.
     *
     * `preferencias` es una columna SET: viaja como cadena separada por comas
     * ('monitor,lectura'), y vacía se guarda como NULL para distinguir "no
     * eligió ninguna" de "no lo hemos preguntado".
     */
    private function parametrosProclamador(array $datos): array
    {
        $preferencias = is_array($datos['preferencias'] ?? null)
            ? implode(',', $datos['preferencias'])
            : (string) ($datos['preferencias'] ?? '');

        return [
            ':persona'      => $datos['persona_id'],
            ':nombre'       => $datos['nombre'],
            ':telefono'     => $datos['telefono'],
            ':email'        => $datos['email'],
            ':preferencias' => $preferencias !== '' ? $preferencias : null,
            ':orden'        => $datos['orden'],
            ':activo'       => $datos['activo'],
        ];
    }

    // ── Turnos ───────────────────────────────────────────────────────────

    /** Turnos del mes, con los nombres de sus proclamadores ya concatenados, para el calendario. */
    public function turnosDelMes(int $anio, int $mes, int $pastoralId): array
    {
        $inicio        = sprintf('%04d-%02d-01', $anio, $mes);
        $siguienteAnio = $mes === 12 ? $anio + 1 : $anio;
        $siguienteMes  = $mes === 12 ? 1 : $mes + 1;
        $fin           = sprintf('%04d-%02d-01', $siguienteAnio, $siguienteMes);

        return $this->fetchAll(
            "SELECT t.*, c.nombre AS color_nombre, c.color_hex,
                    (SELECT GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ')
                       FROM proclamadores_turno_proclamadores tp
                       JOIN proclamadores p ON p.id = tp.proclamador_id
                      WHERE tp.turno_id = t.id) AS proclamadores_nombres
               FROM proclamadores_turnos t
               LEFT JOIN colores_liturgicos c ON c.id = t.color_liturgico_id
              WHERE t.fecha >= :inicio AND t.fecha < :fin AND t.pastoral_id = :pastoral
              ORDER BY t.fecha, t.hora",
            [':inicio' => $inicio, ':fin' => $fin, ':pastoral' => $pastoralId]
        );
    }

    public function turnoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM proclamadores_turnos WHERE id = :id', [':id' => $id]);
    }

    public function proclamadoresDeTurno(int $turnoId): array
    {
        return $this->fetchAll(
            'SELECT p.* FROM proclamadores_turno_proclamadores tp
               JOIN proclamadores p ON p.id = tp.proclamador_id
              WHERE tp.turno_id = :turno
              ORDER BY p.nombre',
            [':turno' => $turnoId]
        );
    }

    public function crearTurno(array $datos, array $proclamadorIds, int $usuarioId): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO proclamadores_turnos (pastoral_id, fecha, hora, descripcion, color_liturgico_id, usuario_id)
                 VALUES (:pastoral, :fecha, :hora, :descripcion, :color, :usuario)',
                [
                    ':pastoral'    => $datos['pastoral_id'],
                    ':fecha'       => $datos['fecha'],
                    ':hora'        => $datos['hora'],
                    ':descripcion' => $datos['descripcion'],
                    ':color'       => $datos['color_liturgico_id'] ?? null,
                    ':usuario'     => $usuarioId,
                ]
            );
            $turnoId = $this->lastInsertId();
            $this->sincronizarProclamadoresDeTurno($turnoId, $proclamadorIds);
            $this->commit();
            return $turnoId;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function actualizarTurno(int $id, array $datos, array $proclamadorIds): int
    {
        $this->beginTransaction();
        try {
            $filas = $this->execute(
                'UPDATE proclamadores_turnos
                    SET fecha = :fecha, hora = :hora, descripcion = :descripcion, color_liturgico_id = :color
                  WHERE id = :id',
                [
                    ':fecha'       => $datos['fecha'],
                    ':hora'        => $datos['hora'],
                    ':descripcion' => $datos['descripcion'],
                    ':color'       => $datos['color_liturgico_id'] ?? null,
                    ':id'          => $id,
                ]
            );
            $this->sincronizarProclamadoresDeTurno($id, $proclamadorIds);
            $this->commit();
            return $filas;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function eliminarTurno(int $id): int
    {
        return $this->execute('DELETE FROM proclamadores_turnos WHERE id = :id', [':id' => $id]);
    }

    // ── Colores litúrgicos ───────────────────────────────────────────────
    // Catálogo compartido con MESC (`colores_liturgicos`, sin prefijo de
    // módulo a propósito): las mismas cinco consultas están en MescModel.
    // No tiene alcance por pastoral — un color litúrgico no es de nadie.

    public function coloresLiturgicos(): array
    {
        return $this->fetchAll('SELECT * FROM colores_liturgicos ORDER BY orden, nombre');
    }

    public function colorLiturgicoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM colores_liturgicos WHERE id = :id', [':id' => $id]);
    }

    public function crearColorLiturgico(array $datos): int
    {
        $this->execute(
            'INSERT INTO colores_liturgicos (nombre, color_hex, significado, orden)
             VALUES (:nombre, :hex, :significado, :orden)',
            [
                ':nombre'      => $datos['nombre'],
                ':hex'         => $datos['color_hex'],
                ':significado' => $datos['significado'],
                ':orden'       => $datos['orden'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarColorLiturgico(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE colores_liturgicos SET nombre = :nombre, color_hex = :hex, significado = :significado, orden = :orden
              WHERE id = :id',
            [
                ':nombre'      => $datos['nombre'],
                ':hex'         => $datos['color_hex'],
                ':significado' => $datos['significado'],
                ':orden'       => $datos['orden'],
                ':id'          => $id,
            ]
        );
    }

    public function eliminarColorLiturgico(int $id): int
    {
        return $this->execute('DELETE FROM colores_liturgicos WHERE id = :id', [':id' => $id]);
    }

    private function sincronizarProclamadoresDeTurno(int $turnoId, array $proclamadorIds): void
    {
        $this->execute('DELETE FROM proclamadores_turno_proclamadores WHERE turno_id = :id', [':id' => $turnoId]);
        foreach (array_unique($proclamadorIds) as $proclamadorId) {
            $this->execute(
                'INSERT INTO proclamadores_turno_proclamadores (turno_id, proclamador_id) VALUES (:turno, :proclamador)',
                [':turno' => $turnoId, ':proclamador' => $proclamadorId]
            );
        }
    }
}
