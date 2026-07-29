<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * PastoralModel — Coro, catequesis, caridad, jóvenes, ministros MESC...
 *
 * Es la entidad sobre la que gira el alcance del rol coordinador: ver
 * docs/ARQUITECTURA.md, sección "El alcance por pastoral es ortogonal a la
 * matriz". Este modelo no aplica ningún filtro de alcance por sí mismo —
 * cualquiera puede LEER el catálogo de pastorales— son avisos, eventos,
 * galería y actividades quienes sí lo hacen.
 */
class PastoralModel extends Model
{
    public function todas(): array
    {
        return $this->fetchAll('SELECT * FROM pastorales ORDER BY orden, nombre');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastorales WHERE id = :id', [':id' => $id]);
    }

    public function porSlugActiva(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM pastorales WHERE slug = :slug AND activa = 1', [':slug' => $slug]);
    }

    /** Para el sitio público. */
    public function activas(): array
    {
        return $this->fetchAll('SELECT * FROM pastorales WHERE activa = 1 ORDER BY orden, nombre');
    }

    /** id => nombre, para selectores (formularios de avisos, eventos, galería). */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, nombre FROM pastorales WHERE activa = 1 ORDER BY nombre');
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO pastorales
                (centro_id, slug, nombre, descripcion_corta, descripcion, imagen, icono, responsable_nombre,
                 contacto_email, contacto_telefono, dia_reunion, hora_reunion, lugar_reunion,
                 acepta_voluntarios, orden, activa)
             VALUES
                (:centro, :slug, :nombre, :descCorta, :desc, :imagen, :icono, :responsable,
                 :email, :telefono, :diaReunion, :horaReunion, :lugarReunion,
                 :voluntarios, :orden, :activa)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE pastorales
                SET centro_id = :centro, slug = :slug, nombre = :nombre, descripcion_corta = :descCorta,
                    descripcion = :desc, imagen = :imagen, icono = :icono,
                    responsable_nombre = :responsable, contacto_email = :email,
                    contacto_telefono = :telefono, dia_reunion = :diaReunion,
                    hora_reunion = :horaReunion, lugar_reunion = :lugarReunion,
                    acepta_voluntarios = :voluntarios, orden = :orden, activa = :activa
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    /** Borrado lógico preferido: desactivarla conserva su historial de avisos/eventos. */
    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM pastorales WHERE id = :id', [':id' => $id]);
    }

    // ── Actividades ─────────────────────────────────────────────────────

    public const TIPOS_ACTIVIDAD = [
        'comunitaria'  => 'Comunitaria',
        'apoyo_social' => 'Apoyo social',
        'formacion'    => 'Formación',
        'liturgica'    => 'Litúrgica',
    ];

    public function actividades(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_actividades WHERE pastoral_id = :id ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function actividadesActivas(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_actividades WHERE pastoral_id = :id AND activa = 1 ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function actividadPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_actividades WHERE id = :id', [':id' => $id]);
    }

    public function crearActividad(array $datos): int
    {
        $this->execute(
            'INSERT INTO pastoral_actividades (pastoral_id, titulo, descripcion, tipo, orden, activa)
             VALUES (:pastoral, :titulo, :descripcion, :tipo, :orden, :activa)',
            [
                ':pastoral'    => $datos['pastoral_id'],
                ':titulo'      => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':tipo'        => $datos['tipo'],
                ':orden'       => $datos['orden'],
                ':activa'      => $datos['activa'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarActividad(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE pastoral_actividades
                SET titulo = :titulo, descripcion = :descripcion, tipo = :tipo,
                    orden = :orden, activa = :activa
              WHERE id = :id',
            [
                ':titulo'      => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':tipo'        => $datos['tipo'],
                ':orden'       => $datos['orden'],
                ':activa'      => $datos['activa'],
                ':id'          => $id,
            ]
        );
    }

    public function eliminarActividad(int $id): int
    {
        return $this->execute('DELETE FROM pastoral_actividades WHERE id = :id', [':id' => $id]);
    }

    // ── Documentos descargables (issue #3) ──────────────────────────────

    public function documentos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_documentos WHERE pastoral_id = :id ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function documentosActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_documentos WHERE pastoral_id = :id AND activo = 1 ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function documentoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_documentos WHERE id = :id', [':id' => $id]);
    }

    public function crearDocumento(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO pastoral_documentos (pastoral_id, titulo, archivo, orden, activo, usuario_id)
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
        return $this->execute('DELETE FROM pastoral_documentos WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':centro'      => $datos['centro_id'],
            ':slug'        => $datos['slug'],
            ':nombre'      => $datos['nombre'],
            ':descCorta'   => $datos['descripcion_corta'],
            ':desc'        => $datos['descripcion'],
            ':imagen'      => $datos['imagen'],
            ':icono'       => $datos['icono'],
            ':responsable' => $datos['responsable_nombre'],
            ':email'       => $datos['contacto_email'],
            ':telefono'    => $datos['contacto_telefono'],
            ':diaReunion'  => $datos['dia_reunion'],
            ':horaReunion' => $datos['hora_reunion'],
            ':lugarReunion'=> $datos['lugar_reunion'],
            ':voluntarios' => $datos['acepta_voluntarios'],
            ':orden'       => $datos['orden'],
            ':activa'      => $datos['activa'],
        ];
    }
}
