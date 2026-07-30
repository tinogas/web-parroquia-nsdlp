<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CursoModel — Catálogo de cursos y capacitaciones.
 *
 * A diferencia de avisos/eventos/galería, `pastoral_id` aquí es solo una
 * etiqueta organizativa: el rol coordinador no administra cursos, así que no
 * hace falta el mismo alcance por pastoral. Ver install.sql.
 *
 * Fase 1: catálogo con temario informativo e inscripción. `curso_sesiones` es
 * el ancla del aula virtual de fase 2. Ver docs/BASE-DE-DATOS.md
 */
class CursoModel extends Model
{
    public const MODALIDADES = [
        'presencial' => 'Presencial',
        'en_linea'   => 'En línea',
        'mixta'      => 'Mixta',
    ];

    public function listar(int $pagina, string $filtro = 'todos'): array
    {
        $where = match ($filtro) {
            'publicados' => 'WHERE c.publicado = 1',
            'borradores' => 'WHERE c.publicado = 0',
            default      => '',
        };
        return $this->paginar(
            "SELECT c.*, p.nombre AS instructor_nombre
               FROM cursos c
               LEFT JOIN personas p ON p.id = c.instructor_id
               {$where}
              ORDER BY c.fecha_inicio DESC, c.id DESC",
            [],
            $pagina,
            15
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM cursos WHERE id = :id', [':id' => $id]);
    }

    /** id => título, para el selector de filtro en la bandeja de inscripciones. */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, titulo FROM cursos ORDER BY titulo');
    }

    public function porSlugPublicado(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT c.*, p.nombre AS instructor_nombre, p.foto AS instructor_foto
               FROM cursos c
               LEFT JOIN personas p ON p.id = c.instructor_id
              WHERE c.slug = :slug AND c.publicado = 1',
            [':slug' => $slug]
        );
    }

    public function publicados(): array
    {
        return $this->fetchAll(
            "SELECT * FROM cursos WHERE publicado = 1 ORDER BY (fecha_inicio IS NULL), fecha_inicio, titulo"
        );
    }

    /**
     * Cursos que todavía tienen sentido anunciar, para la portada: los que no
     * han terminado y los que no tienen fechas puestas —un curso permanente o
     * uno cuyas fechas aún no se deciden sigue siendo información útil—.
     *
     * Un curso ya terminado deja de aparecer aquí, a diferencia de un evento,
     * que se conserva como registro histórico: al curso lo que le importa es
     * que alguien se pueda inscribir.
     */
    public function proximos(int $limite = 3): array
    {
        return $this->fetchAll(
            'SELECT * FROM cursos
              WHERE publicado = 1
                AND (COALESCE(fecha_fin, fecha_inicio) >= CURDATE()
                     OR (fecha_inicio IS NULL AND fecha_fin IS NULL))
              ORDER BY (fecha_inicio IS NULL), fecha_inicio, orden, titulo
              LIMIT ' . max(1, $limite)
        );
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO cursos
                (slug, titulo, descripcion, objetivos, dirigido_a, imagen, modalidad,
                 instructor_id, pastoral_id, cupo, aportacion, fecha_inicio, fecha_fin,
                 horario, lugar, inscripciones_abiertas, fecha_cierre_inscripcion,
                 requiere_tutor, publicado, orden)
             VALUES
                (:slug, :titulo, :descripcion, :objetivos, :dirigidoA, :imagen, :modalidad,
                 :instructor, :pastoral, :cupo, :aportacion, :inicio, :fin,
                 :horario, :lugar, :inscripcionesAbiertas, :cierre,
                 :tutor, :publicado, :orden)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE cursos
                SET slug = :slug, titulo = :titulo, descripcion = :descripcion,
                    objetivos = :objetivos, dirigido_a = :dirigidoA, imagen = :imagen,
                    modalidad = :modalidad, instructor_id = :instructor, pastoral_id = :pastoral,
                    cupo = :cupo, aportacion = :aportacion, fecha_inicio = :inicio, fecha_fin = :fin,
                    horario = :horario, lugar = :lugar,
                    inscripciones_abiertas = :inscripcionesAbiertas, fecha_cierre_inscripcion = :cierre,
                    requiere_tutor = :tutor, publicado = :publicado, orden = :orden
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM cursos WHERE id = :id', [':id' => $id]);
    }

    // ── Temario ─────────────────────────────────────────────────────────

    public function sesiones(int $cursoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM curso_sesiones WHERE curso_id = :id ORDER BY numero, orden',
            [':id' => $cursoId]
        );
    }

    public function sesionPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM curso_sesiones WHERE id = :id', [':id' => $id]);
    }

    public function crearSesion(array $datos): int
    {
        $this->execute(
            'INSERT INTO curso_sesiones (curso_id, numero, titulo, descripcion, fecha, orden)
             VALUES (:curso, :numero, :titulo, :descripcion, :fecha, :orden)',
            [
                ':curso'      => $datos['curso_id'],
                ':numero'     => $datos['numero'],
                ':titulo'     => $datos['titulo'],
                ':descripcion'=> $datos['descripcion'],
                ':fecha'      => $datos['fecha'],
                ':orden'      => $datos['orden'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarSesion(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE curso_sesiones SET numero = :numero, titulo = :titulo, descripcion = :descripcion,
                                       fecha = :fecha, orden = :orden
              WHERE id = :id',
            [
                ':numero'     => $datos['numero'],
                ':titulo'     => $datos['titulo'],
                ':descripcion'=> $datos['descripcion'],
                ':fecha'      => $datos['fecha'],
                ':orden'      => $datos['orden'],
                ':id'         => $id,
            ]
        );
    }

    public function eliminarSesion(int $id): int
    {
        return $this->execute('DELETE FROM curso_sesiones WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':slug'                  => $datos['slug'],
            ':titulo'                => $datos['titulo'],
            ':descripcion'           => $datos['descripcion'],
            ':objetivos'             => $datos['objetivos'],
            ':dirigidoA'             => $datos['dirigido_a'],
            ':imagen'                => $datos['imagen'],
            ':modalidad'             => $datos['modalidad'],
            ':instructor'            => $datos['instructor_id'],
            ':pastoral'              => $datos['pastoral_id'],
            ':cupo'                  => $datos['cupo'],
            ':aportacion'            => $datos['aportacion'],
            ':inicio'                => $datos['fecha_inicio'],
            ':fin'                   => $datos['fecha_fin'],
            ':horario'               => $datos['horario'],
            ':lugar'                 => $datos['lugar'],
            ':inscripcionesAbiertas' => $datos['inscripciones_abiertas'],
            ':cierre'                => $datos['fecha_cierre_inscripcion'],
            ':tutor'                 => $datos['requiere_tutor'],
            ':publicado'             => $datos['publicado'],
            ':orden'                 => $datos['orden'],
        ];
    }
}
