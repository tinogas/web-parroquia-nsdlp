<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * LectorModel — Calendario de turnos y catálogo de lectores. Calcado de
 * MescModel (turnos + ministros), sin rutas ni visitas: un lector proclama
 * la Palabra en misa, no reparte comunión a domicilio. color_liturgico_id
 * reutiliza el catálogo de MESC (mesc_colores_liturgicos): el significado
 * litúrgico de cada color es el mismo para toda la parroquia, no propio de
 * este módulo.
 */
class LectorModel extends Model
{
    /** La única pastoral que administra este módulo, resuelta por slug (no por id fijo: los id no se siembran en install.sql). */
    public function pastoralId(): ?int
    {
        return $this->fetchColumn(
            'SELECT id FROM pastorales WHERE slug = :slug',
            [':slug' => PASTORAL_LECTOR]
        ) ?: null;
    }

    // ── Lectores ─────────────────────────────────────────────────────────

    public function lectores(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM lector_lectores WHERE pastoral_id = :id ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function lectoresActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM lector_lectores WHERE pastoral_id = :id AND activo = 1 ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function lectorPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM lector_lectores WHERE id = :id', [':id' => $id]);
    }

    public function crearLector(array $datos): int
    {
        $this->execute(
            'INSERT INTO lector_lectores (pastoral_id, persona_id, nombre, telefono, email, orden, activo)
             VALUES (:pastoral, :persona, :nombre, :telefono, :email, :orden, :activo)',
            $this->parametrosLector($datos) + [':pastoral' => $datos['pastoral_id']]
        );
        return $this->lastInsertId();
    }

    public function actualizarLector(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE lector_lectores
                SET persona_id = :persona, nombre = :nombre, telefono = :telefono, email = :email,
                    orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametrosLector($datos) + [':id' => $id]
        );
    }

    public function eliminarLector(int $id): int
    {
        return $this->execute('DELETE FROM lector_lectores WHERE id = :id', [':id' => $id]);
    }

    /**
     * Sin :pastoral: pastoral_id se fija una sola vez al crear (este módulo
     * es exclusivo de una única pastoral) y actualizarLector() no lo toca —
     * incluirlo aquí rompía el UPDATE con PDO::ATTR_EMULATE_PREPARES en
     * false, que rechaza cualquier parámetro que el SQL no declare.
     */
    private function parametrosLector(array $datos): array
    {
        return [
            ':persona'  => $datos['persona_id'],
            ':nombre'   => $datos['nombre'],
            ':telefono' => $datos['telefono'],
            ':email'    => $datos['email'],
            ':orden'    => $datos['orden'],
            ':activo'   => $datos['activo'],
        ];
    }

    // ── Turnos ───────────────────────────────────────────────────────────

    /** Turnos del mes, con los nombres de sus lectores ya concatenados, para el calendario. */
    public function turnosDelMes(int $anio, int $mes, int $pastoralId): array
    {
        $inicio        = sprintf('%04d-%02d-01', $anio, $mes);
        $siguienteAnio = $mes === 12 ? $anio + 1 : $anio;
        $siguienteMes  = $mes === 12 ? 1 : $mes + 1;
        $fin           = sprintf('%04d-%02d-01', $siguienteAnio, $siguienteMes);

        return $this->fetchAll(
            "SELECT t.*, c.nombre AS color_nombre, c.color_hex,
                    (SELECT GROUP_CONCAT(l.nombre ORDER BY l.nombre SEPARATOR ', ')
                       FROM lector_turno_lectores tl JOIN lector_lectores l ON l.id = tl.lector_id
                      WHERE tl.turno_id = t.id) AS lectores_nombres
               FROM lector_turnos t
               LEFT JOIN mesc_colores_liturgicos c ON c.id = t.color_liturgico_id
              WHERE t.fecha >= :inicio AND t.fecha < :fin AND t.pastoral_id = :pastoral
              ORDER BY t.fecha, t.hora",
            [':inicio' => $inicio, ':fin' => $fin, ':pastoral' => $pastoralId]
        );
    }

    public function turnoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM lector_turnos WHERE id = :id', [':id' => $id]);
    }

    public function lectoresDeTurno(int $turnoId): array
    {
        return $this->fetchAll(
            'SELECT l.* FROM lector_turno_lectores tl
               JOIN lector_lectores l ON l.id = tl.lector_id
              WHERE tl.turno_id = :turno
              ORDER BY l.nombre',
            [':turno' => $turnoId]
        );
    }

    public function crearTurno(array $datos, array $lectorIds, int $usuarioId): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO lector_turnos (pastoral_id, fecha, hora, descripcion, color_liturgico_id, usuario_id)
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
            $this->sincronizarLectoresDeTurno($turnoId, $lectorIds);
            $this->commit();
            return $turnoId;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function actualizarTurno(int $id, array $datos, array $lectorIds): int
    {
        $this->beginTransaction();
        try {
            $filas = $this->execute(
                'UPDATE lector_turnos
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
            $this->sincronizarLectoresDeTurno($id, $lectorIds);
            $this->commit();
            return $filas;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function eliminarTurno(int $id): int
    {
        return $this->execute('DELETE FROM lector_turnos WHERE id = :id', [':id' => $id]);
    }

    /** Catálogo de colores litúrgicos, compartido con MESC — ver comentario de la clase. */
    public function coloresLiturgicos(): array
    {
        return $this->fetchAll('SELECT * FROM mesc_colores_liturgicos ORDER BY orden, nombre');
    }

    public function colorLiturgicoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_colores_liturgicos WHERE id = :id', [':id' => $id]);
    }

    private function sincronizarLectoresDeTurno(int $turnoId, array $lectorIds): void
    {
        $this->execute('DELETE FROM lector_turno_lectores WHERE turno_id = :id', [':id' => $turnoId]);
        foreach (array_unique($lectorIds) as $lectorId) {
            $this->execute(
                'INSERT INTO lector_turno_lectores (turno_id, lector_id) VALUES (:turno, :lector)',
                [':turno' => $turnoId, ':lector' => $lectorId]
            );
        }
    }
}
