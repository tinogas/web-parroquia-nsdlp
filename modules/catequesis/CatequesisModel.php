<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CatequesisModel — Maestros catequistas, tablero de actividades y
 * documentos de la pastoral de Catecismo. Calcado del patrón de MESC
 * (módulo dedicado a una pastoral, sin controlador público), pero mucho más
 * pequeño: no hay rutas ni datos sensibles de salud aquí.
 */
class CatequesisModel extends Model
{
    /** sacramento => nombre visible */
    public const SACRAMENTOS = [
        'primera_comunion' => 'Primera Comunión',
        'confirmacion'     => 'Confirmación',
    ];

    // ── Maestros ─────────────────────────────────────────────────────────

    public function maestros(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_maestros WHERE pastoral_id = :id ORDER BY sacramento, nombre',
            [':id' => $pastoralId]
        );
    }

    public function maestrosActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_maestros WHERE pastoral_id = :id AND activo = 1 ORDER BY sacramento, nombre',
            [':id' => $pastoralId]
        );
    }

    public function maestroPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM catequesis_maestros WHERE id = :id', [':id' => $id]);
    }

    public function crearMaestro(array $datos): int
    {
        $this->execute(
            'INSERT INTO catequesis_maestros (pastoral_id, nombre, sacramento, telefono, email, orden, activo)
             VALUES (:pastoral, :nombre, :sacramento, :telefono, :email, :orden, :activo)',
            $this->parametrosMaestro($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizarMaestro(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE catequesis_maestros
                SET nombre = :nombre, sacramento = :sacramento, telefono = :telefono,
                    email = :email, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametrosMaestro($datos) + [':id' => $id]
        );
    }

    public function eliminarMaestro(int $id): int
    {
        return $this->execute('DELETE FROM catequesis_maestros WHERE id = :id', [':id' => $id]);
    }

    private function parametrosMaestro(array $datos): array
    {
        return [
            ':pastoral'   => $datos['pastoral_id'],
            ':nombre'     => $datos['nombre'],
            ':sacramento' => $datos['sacramento'],
            ':telefono'   => $datos['telefono'],
            ':email'      => $datos['email'],
            ':orden'      => $datos['orden'],
            ':activo'     => $datos['activo'],
        ];
    }

    // ── Actividades (tablero/calendario) ────────────────────────────────

    public function actividades(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_actividades WHERE pastoral_id = :id ORDER BY fecha_inicio DESC, orden',
            [':id' => $pastoralId]
        );
    }

    public function actividadPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM catequesis_actividades WHERE id = :id', [':id' => $id]);
    }

    public function crearActividad(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO catequesis_actividades
                (pastoral_id, titulo, descripcion, fecha_inicio, fecha_fin, publicado, orden, usuario_id)
             VALUES (:pastoral, :titulo, :descripcion, :inicio, :fin, :publicado, :orden, :usuario)',
            $this->parametrosActividad($datos) + [':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizarActividad(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE catequesis_actividades
                SET titulo = :titulo, descripcion = :descripcion, fecha_inicio = :inicio,
                    fecha_fin = :fin, publicado = :publicado, orden = :orden
              WHERE id = :id',
            $this->parametrosActividad($datos) + [':id' => $id]
        );
    }

    public function eliminarActividad(int $id): int
    {
        return $this->execute('DELETE FROM catequesis_actividades WHERE id = :id', [':id' => $id]);
    }

    private function parametrosActividad(array $datos): array
    {
        return [
            ':pastoral'    => $datos['pastoral_id'],
            ':titulo'      => $datos['titulo'],
            ':descripcion' => $datos['descripcion'],
            ':inicio'      => $datos['fecha_inicio'],
            ':fin'         => $datos['fecha_fin'],
            ':publicado'   => $datos['publicado'],
            ':orden'       => $datos['orden'],
        ];
    }

    // ── Documentos ───────────────────────────────────────────────────────

    public function documentos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_documentos WHERE pastoral_id = :id ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function documentoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM catequesis_documentos WHERE id = :id', [':id' => $id]);
    }

    public function crearDocumento(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO catequesis_documentos (pastoral_id, titulo, archivo, orden, activo, usuario_id)
             VALUES (:pastoral, :titulo, :archivo, :orden, :activo, :usuario)',
            [
                ':pastoral' => $datos['pastoral_id'],
                ':titulo'   => $datos['titulo'],
                ':archivo'  => $datos['archivo'],
                ':orden'    => $datos['orden'],
                ':activo'   => $datos['activo'],
                ':usuario'  => $usuarioId,
            ]
        );
        return $this->lastInsertId();
    }

    public function eliminarDocumento(int $id): int
    {
        return $this->execute('DELETE FROM catequesis_documentos WHERE id = :id', [':id' => $id]);
    }
}
