<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CatequesisModel — Catequistas, periodos de catecismo, tablero de
 * actividades y documentos. A diferencia de MESC (que sirve a cualquier
 * pastoral), este módulo es exclusivo de la pastoral de Catecismo: no hay
 * selector de pastoral en ningún formulario, pastoralId() la resuelve sola
 * por su slug. Sin controlador público: vive enteramente en el panel.
 */
class CatequesisModel extends Model
{
    /** grado => nombre visible, en el orden real de avance (issue de revisión de módulos) */
    public const GRADOS = [
        'kinder'                       => 'Kinder',
        'primero_primaria'             => 'Primero Primaria',
        'segundo_primaria'             => 'Segundo Primaria',
        'tercero_primaria'             => 'Tercero Primaria',
        'comunion'                     => 'Comunión',
        'quinto_misionero'             => 'Quinto Misionero',
        'sexto_misionero'              => 'Sexto Misionero',
        'primero_secundaria_misionero' => 'Primero Secundaria Misionero',
        'segundo_secundaria'           => 'Segundo Secundaria',
        'confirmacion'                 => 'Confirmación',
    ];

    /** La única pastoral que administra este módulo, resuelta por slug (no por id fijo: los id no se siembran en install.sql). */
    public function pastoralId(): ?int
    {
        return $this->fetchColumn("SELECT id FROM pastorales WHERE slug = 'catecismo'") ?: null;
    }

    public function pastoral(): ?array
    {
        return $this->fetchOne("SELECT * FROM pastorales WHERE slug = 'catecismo'");
    }

    // ── Catequistas ──────────────────────────────────────────────────────

    public function catequistas(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_catequistas WHERE pastoral_id = :id ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function catequistasActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_catequistas WHERE pastoral_id = :id AND activo = 1 ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function catequistaPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM catequesis_catequistas WHERE id = :id', [':id' => $id]);
    }

    public function crearCatequista(array $datos): int
    {
        $this->execute(
            'INSERT INTO catequesis_catequistas (pastoral_id, nombre, telefono, email, orden, activo)
             VALUES (:pastoral, :nombre, :telefono, :email, :orden, :activo)',
            $this->parametrosCatequista($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizarCatequista(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE catequesis_catequistas
                SET nombre = :nombre, telefono = :telefono, email = :email, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametrosCatequista($datos) + [':id' => $id]
        );
    }

    public function eliminarCatequista(int $id): int
    {
        return $this->execute('DELETE FROM catequesis_catequistas WHERE id = :id', [':id' => $id]);
    }

    private function parametrosCatequista(array $datos): array
    {
        return [
            ':pastoral' => $datos['pastoral_id'],
            ':nombre'   => $datos['nombre'],
            ':telefono' => $datos['telefono'],
            ':email'    => $datos['email'],
            ':orden'    => $datos['orden'],
            ':activo'   => $datos['activo'],
        ];
    }

    // ── Periodos ─────────────────────────────────────────────────────────

    public function periodos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM catequesis_periodos WHERE pastoral_id = :id ORDER BY fecha_inicio DESC',
            [':id' => $pastoralId]
        );
    }

    public function periodoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM catequesis_periodos WHERE id = :id', [':id' => $id]);
    }

    public function crearPeriodo(array $datos): int
    {
        $this->execute(
            'INSERT INTO catequesis_periodos (pastoral_id, nombre, fecha_inicio, fecha_fin, activo)
             VALUES (:pastoral, :nombre, :inicio, :fin, :activo)',
            $this->parametrosPeriodo($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizarPeriodo(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE catequesis_periodos
                SET nombre = :nombre, fecha_inicio = :inicio, fecha_fin = :fin, activo = :activo
              WHERE id = :id',
            $this->parametrosPeriodo($datos) + [':id' => $id]
        );
    }

    public function eliminarPeriodo(int $id): int
    {
        return $this->execute('DELETE FROM catequesis_periodos WHERE id = :id', [':id' => $id]);
    }

    private function parametrosPeriodo(array $datos): array
    {
        return [
            ':pastoral' => $datos['pastoral_id'],
            ':nombre'   => $datos['nombre'],
            ':inicio'   => $datos['fecha_inicio'],
            ':fin'      => $datos['fecha_fin'],
            ':activo'   => $datos['activo'],
        ];
    }

    // ── Asignación catequista ↔ periodo (con su grado de ese ciclo) ──────

    /** Catequistas asignados a un periodo, con su grado en ese periodo. */
    public function catequistasDePeriodo(int $periodoId): array
    {
        return $this->fetchAll(
            'SELECT c.*, pc.grado
               FROM catequesis_periodo_catequistas pc
               JOIN catequesis_catequistas c ON c.id = pc.catequista_id
              WHERE pc.periodo_id = :periodo
              ORDER BY c.nombre',
            [':periodo' => $periodoId]
        );
    }

    /** Historial de un catequista: en qué periodos dio clase y de qué grado en cada uno. */
    public function periodosDeCatequista(int $catequistaId): array
    {
        return $this->fetchAll(
            'SELECT p.*, pc.grado
               FROM catequesis_periodo_catequistas pc
               JOIN catequesis_periodos p ON p.id = pc.periodo_id
              WHERE pc.catequista_id = :catequista
              ORDER BY p.fecha_inicio DESC',
            [':catequista' => $catequistaId]
        );
    }

    /** IDs de catequistas ya asignados a un periodo, para no repetirlos en el selector de "agregar". */
    public function catequistaIdsDePeriodo(int $periodoId): array
    {
        return array_map('intval', array_column($this->fetchAll(
            'SELECT catequista_id FROM catequesis_periodo_catequistas WHERE periodo_id = :periodo',
            [':periodo' => $periodoId]
        ), 'catequista_id'));
    }

    /** Asigna (o cambia el grado de) un catequista en un periodo. */
    public function asignarCatequista(int $periodoId, int $catequistaId, string $grado): void
    {
        $this->execute(
            'INSERT INTO catequesis_periodo_catequistas (periodo_id, catequista_id, grado)
             VALUES (:periodo, :catequista, :grado)
             ON DUPLICATE KEY UPDATE grado = VALUES(grado)',
            [':periodo' => $periodoId, ':catequista' => $catequistaId, ':grado' => $grado]
        );
    }

    public function quitarCatequistaDePeriodo(int $periodoId, int $catequistaId): void
    {
        $this->execute(
            'DELETE FROM catequesis_periodo_catequistas WHERE periodo_id = :periodo AND catequista_id = :catequista',
            [':periodo' => $periodoId, ':catequista' => $catequistaId]
        );
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
