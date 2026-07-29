<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * EventoModel — Celebraciones y actividades con fecha concreta.
 *
 * A diferencia de horarios (que es recurrencia semanal), aquí cada fila es una
 * ocurrencia real: la boda del sábado, la posada del 16 de diciembre. Ver
 * docs/ARQUITECTURA.md
 */
class EventoModel extends Model
{
    public function listar(int $pagina, string $filtro = 'todos', ?array $pastoralesPermitidas = null): array
    {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'publicados') {
            $condiciones[] = 'e.publicado = 1';
        } elseif ($filtro === 'borradores') {
            $condiciones[] = 'e.publicado = 0';
        }

        [$condicionPastoral, $paramsPastoral] = $this->condicionPastoral($pastoralesPermitidas, 'e.pastoral_id');
        if ($condicionPastoral !== '') {
            $condiciones[] = $condicionPastoral;
            $params += $paramsPastoral;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT e.*, u.nombre AS autor, p.nombre AS pastoral_nombre
               FROM eventos e
               LEFT JOIN usuarios u ON u.id = e.usuario_id
               LEFT JOIN pastorales p ON p.id = e.pastoral_id
               {$where}
              ORDER BY e.fecha_inicio DESC, e.id DESC",
            $params,
            $pagina,
            15
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM eventos WHERE id = :id', [':id' => $id]);
    }

    public function porSlugPublicado(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM eventos WHERE slug = :slug AND publicado = 1', [':slug' => $slug]);
    }

    /** slug + fecha de todos los publicados. Para sitemap.xml. */
    public function paraSitemap(): array
    {
        return $this->fetchAll('SELECT slug, created_at AS modificado FROM eventos WHERE publicado = 1');
    }

    /** Próximos eventos a partir de ahora, para la portada y la página de eventos. */
    public function proximos(int $limite = 6): array
    {
        return $this->fetchAll(
            'SELECT * FROM eventos
              WHERE publicado = 1
                AND ((fecha_fin IS NOT NULL AND fecha_fin >= NOW())
                  OR (fecha_fin IS NULL AND fecha_inicio >= NOW()))
              ORDER BY fecha_inicio ASC
              LIMIT ' . max(1, $limite)
        );
    }

    /**
     * Eventos publicados que INICIAN dentro del mes indicado, para el
     * calendario. Simplificación deliberada: un evento de varios días solo se
     * marca en el día en que empieza, no en cada día que dura. La inmensa
     * mayoría de los eventos de una parroquia son de un solo día.
     */
    public function delMes(int $anio, int $mes): array
    {
        $inicio          = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
        $siguienteAnio   = $mes === 12 ? $anio + 1 : $anio;
        $siguienteMes    = $mes === 12 ? 1 : $mes + 1;
        $fin             = sprintf('%04d-%02d-01 00:00:00', $siguienteAnio, $siguienteMes);

        return $this->fetchAll(
            'SELECT id, slug, titulo, fecha_inicio, fecha_fin, todo_el_dia, lugar, color
               FROM eventos
              WHERE publicado = 1 AND fecha_inicio >= :inicio AND fecha_inicio < :fin
              ORDER BY fecha_inicio',
            [':inicio' => $inicio, ':fin' => $fin]
        );
    }

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO eventos
                (slug, titulo, descripcion, imagen, lugar, fecha_inicio, fecha_fin,
                 todo_el_dia, pastoral_id, color, publicado, usuario_id)
             VALUES
                (:slug, :titulo, :descripcion, :imagen, :lugar, :inicio, :fin,
                 :todoDia, :pastoral, :color, :publicado, :usuario)',
            $this->parametros($datos) + [':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE eventos
                SET slug = :slug, titulo = :titulo, descripcion = :descripcion, imagen = :imagen,
                    lugar = :lugar, fecha_inicio = :inicio, fecha_fin = :fin, todo_el_dia = :todoDia,
                    pastoral_id = :pastoral, color = :color, publicado = :publicado
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM eventos WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':slug'        => $datos['slug'],
            ':titulo'      => $datos['titulo'],
            ':descripcion' => $datos['descripcion'],
            ':imagen'      => $datos['imagen'],
            ':lugar'       => $datos['lugar'],
            ':inicio'      => $datos['fecha_inicio'],
            ':fin'         => $datos['fecha_fin'],
            ':todoDia'     => $datos['todo_el_dia'],
            ':pastoral'    => $datos['pastoral_id'],
            ':color'       => $datos['color'],
            ':publicado'   => $datos['publicado'],
        ];
    }
}
