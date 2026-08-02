<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CursoModel — Catálogo de cursos y capacitaciones.
 *
 * `pastoral_id` era al principio solo una etiqueta organizativa, porque el rol
 * coordinador no administraba cursos. Desde el issue de filtrado por pastoral
 * pesa lo mismo que en avisos y eventos: la pastoral dueña administra sus
 * cursos y nadie más los toca, y `centro_id` añade en qué sede se da. Quien lo
 * hace cumplir es CursoController con requireAlcanceContenido(); aquí solo se
 * ofrece por dónde filtrar.
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

    /**
     * Listado del panel. `$pastorales` y `$centros` ya vienen cruzados con el
     * alcance de quien mira, igual que en EventoModel::listar().
     */
    public function listar(
        int $pagina,
        string $filtro = 'todos',
        ?array $pastorales = null,
        ?array $centros = null
    ): array {
        $condiciones = match ($filtro) {
            'publicados' => ['c.publicado = 1'],
            'borradores' => ['c.publicado = 0'],
            default      => [],
        };

        $params = [];
        foreach ([[$pastorales, 'c.pastoral_id'], [$centros, 'c.centro_id']] as [$ids, $columna]) {
            [$condicion, $paramsAlcance] = $this->condicionAlcance($ids, $columna);
            if ($condicion !== '') {
                $condiciones[] = $condicion;
                $params += $paramsAlcance;
            }
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT c.*, i.nombre AS instructor_nombre, p.nombre AS pastoral_nombre,
                    ce.nombre AS centro_nombre
               FROM cursos c
               LEFT JOIN personas i   ON i.id = c.instructor_id
               LEFT JOIN pastorales p ON p.id = c.pastoral_id
               LEFT JOIN centros ce   ON ce.id = c.centro_id
               {$where}
              ORDER BY c.fecha_inicio DESC, c.id DESC",
            $params,
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

    // ── Agenda interna ──────────────────────────────────────────────────
    //
    // El calendario del panel mezcla eventos y cursos; el del sitio público
    // sigue siendo solo de eventos, y los cursos allí se ven como catálogo.
    // Aquí no se filtra por `publicado` —un curso en borrador también hay que
    // poder programarlo— ni por el alcance de quien mira: todas las pastorales
    // se ven entre sí, y lo que se acota es escribir, no leer.

    /**
     * Cursos cuyo periodo [fecha_inicio, fecha_fin] se traslapa con el rango
     * pedido. Las dos columnas son DATE, así que un curso ocupa días enteros:
     * el controlador los normaliza a la forma que espera `Calendario`.
     *
     * Un curso sin fecha_inicio no puede dibujarse en ninguna casilla; los
     * recoge sinFechas() para avisar aparte en vez de desaparecerlos.
     */
    public function agenda(
        string $desde,
        string $hasta,
        ?array $pastorales = null,
        ?array $centros = null
    ): array {
        [$filtro, $params] = $this->filtroAlcance($pastorales, $centros);

        return $this->fetchAll(
            'SELECT c.id, c.slug, c.titulo, c.fecha_inicio, c.fecha_fin, c.horario, c.lugar,
                    c.publicado, c.pastoral_id, c.centro_id,
                    p.nombre AS pastoral_nombre, ce.nombre AS centro_nombre
               FROM cursos c
               LEFT JOIN pastorales p ON p.id = c.pastoral_id
               LEFT JOIN centros ce   ON ce.id = c.centro_id
              WHERE c.fecha_inicio IS NOT NULL
                AND c.fecha_inicio <= :hasta
                AND COALESCE(c.fecha_fin, c.fecha_inicio) >= :desde'
                . $filtro . '
              ORDER BY c.fecha_inicio, c.titulo',
            [':desde' => $desde, ':hasta' => $hasta] + $params
        );
    }

    /** Cursos sin fecha de inicio: no caben en el calendario, pero existen. */
    public function sinFechas(?array $pastorales = null, ?array $centros = null): array
    {
        [$filtro, $params] = $this->filtroAlcance($pastorales, $centros);

        return $this->fetchAll(
            'SELECT c.id, c.titulo, c.publicado, c.pastoral_id, c.centro_id,
                    p.nombre AS pastoral_nombre, ce.nombre AS centro_nombre
               FROM cursos c
               LEFT JOIN pastorales p ON p.id = c.pastoral_id
               LEFT JOIN centros ce   ON ce.id = c.centro_id
              WHERE c.fecha_inicio IS NULL'
                . $filtro . '
              ORDER BY c.titulo',
            $params
        );
    }

    /**
     * Las dos condiciones de alcance —pastoral y sede— como un solo fragmento
     * « AND … AND …» listo para pegar detrás de un WHERE que ya tiene algo.
     *
     * @return array{0: string, 1: array}
     */
    private function filtroAlcance(?array $pastorales, ?array $centros): array
    {
        $filtro = '';
        $params = [];
        foreach ([[$pastorales, 'c.pastoral_id'], [$centros, 'c.centro_id']] as [$ids, $columna]) {
            [$condicion, $paramsAlcance] = $this->condicionAlcance($ids, $columna);
            if ($condicion !== '') {
                $filtro .= ' AND ' . $condicion;
                $params += $paramsAlcance;
            }
        }
        return [$filtro, $params];
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO cursos
                (slug, titulo, descripcion, objetivos, dirigido_a, imagen, modalidad,
                 instructor_id, pastoral_id, centro_id, cupo, aportacion, fecha_inicio, fecha_fin,
                 horario, lugar, inscripciones_abiertas, fecha_cierre_inscripcion,
                 requiere_tutor, publicado, orden)
             VALUES
                (:slug, :titulo, :descripcion, :objetivos, :dirigidoA, :imagen, :modalidad,
                 :instructor, :pastoral, :centro, :cupo, :aportacion, :inicio, :fin,
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
                    centro_id = :centro,
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
            ':centro'                => $datos['centro_id'],
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
