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
    /**
     * Listado del panel. $anio, $mes y $dia acotan por la fecha de inicio del
     * evento: con un año cargado entero, 467 eventos son 32 páginas y encontrar
     * uno concreto pasando páginas no es viable.
     */
    public function listar(
        int $pagina,
        string $filtro = 'todos',
        ?array $pastoralesPermitidas = null,
        ?int $anio = null,
        ?int $mes = null,
        ?int $dia = null
    ): array {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'publicados') {
            $condiciones[] = 'e.publicado = 1';
        } elseif ($filtro === 'borradores') {
            $condiciones[] = 'e.publicado = 0';
        }

        [$condicionFecha, $paramsFecha] = $this->condicionFecha($anio, $mes, $dia);
        if ($condicionFecha !== '') {
            $condiciones[] = $condicionFecha;
            $params += $paramsFecha;
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

    /**
     * Condición por día, mes y año de `fecha_inicio`, como [sql, params]. Los
     * tres son opcionales y se combinan en cualquier orden: un año entero, un
     * mes de un año, un día concreto, o «los días 16 de cualquier mes».
     *
     * Cuando hay año se compara por rango y no con YEAR()/MONTH()/DAY(): una
     * función sobre la columna deja fuera el índice `idx_eve_fecha`. Sin año no
     * queda más remedio que usarlas.
     */
    private function condicionFecha(?int $anio, ?int $mes, ?int $dia = null): array
    {
        if ($anio === null) {
            $partes = [];
            $params = [];
            if ($mes !== null) {
                $partes[] = 'MONTH(e.fecha_inicio) = :mes';
                $params[':mes'] = $mes;
            }
            if ($dia !== null) {
                $partes[] = 'DAY(e.fecha_inicio) = :dia';
                $params[':dia'] = $dia;
            }
            return $partes ? [implode(' AND ', $partes), $params] : ['', []];
        }

        // Sin mes, el día suelto tampoco puede ir por rango: «los días 16 de 2026».
        if ($mes === null) {
            $extra = $dia !== null ? ' AND DAY(e.fecha_inicio) = :dia' : '';
            return [
                'e.fecha_inicio >= :desde AND e.fecha_inicio < :hasta' . $extra,
                [':desde' => sprintf('%04d-01-01 00:00:00', $anio),
                 ':hasta' => sprintf('%04d-01-01 00:00:00', $anio + 1)]
                    + ($dia !== null ? [':dia' => $dia] : []),
            ];
        }

        // Un día que no existe en ese mes no puede tener eventos. Sin esto, el
        // rango de un «29 de febrero de 2026» se normalizaría al 1 de marzo y
        // devolvería los eventos de otro día.
        if ($dia !== null && !checkdate($mes, $dia, $anio)) {
            return ['1 = 0', []];
        }

        $desde = $dia !== null
            ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia)
            : sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-d', (int) strtotime($desde . ($dia !== null ? ' +1 day' : ' +1 month')));

        return [
            'e.fecha_inicio >= :desde AND e.fecha_inicio < :hasta',
            [':desde' => $desde . ' 00:00:00', ':hasta' => $hasta . ' 00:00:00'],
        ];
    }

    /**
     * Años que de verdad tienen eventos, de más reciente a más antiguo, para no
     * ofrecer un selector lleno de años vacíos. Respeta el alcance por pastoral
     * de quien mira: si solo administra una, no debería deducir de este selector
     * en qué años hay eventos de las demás.
     */
    public function aniosConEventos(?array $pastoralesPermitidas = null): array
    {
        [$condicion, $params] = $this->condicionPastoral($pastoralesPermitidas, 'e.pastoral_id');
        $where = $condicion !== '' ? 'WHERE ' . $condicion : '';

        return array_map(
            'intval',
            array_column(
                $this->fetchAll(
                    "SELECT DISTINCT YEAR(e.fecha_inicio) AS anio FROM eventos e
                     {$where} ORDER BY anio DESC",
                    $params
                ),
                'anio'
            )
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

    /**
     * Próximos eventos a partir de ahora, para la portada y la página de
     * eventos. $pastoralId acota a "el calendario de esta pastoral" en su
     * ficha pública (issue #3).
     */
    public function proximos(int $limite = 6, ?int $pastoralId = null): array
    {
        $condicionPastoral = $pastoralId !== null ? ' AND pastoral_id = :pastoral' : '';
        return $this->fetchAll(
            'SELECT * FROM eventos
              WHERE publicado = 1
                AND ((fecha_fin IS NOT NULL AND fecha_fin >= NOW())
                  OR (fecha_fin IS NULL AND fecha_inicio >= NOW()))'
                . $condicionPastoral . '
              ORDER BY fecha_inicio ASC
              LIMIT ' . max(1, $limite),
            $pastoralId !== null ? [':pastoral' => $pastoralId] : []
        );
    }

    /**
     * Eventos publicados vigentes en algún día del rango [$desde, $hasta],
     * ambos inclusive y en formato 'Y-m-d' (issue de revisión de módulos:
     * antes solo traía los que INICIABAN dentro del mes, así que un evento de
     * varios días desaparecía del todo en cuanto el calendario cruzaba a un
     * mes siguiente mientras seguía en curso). Un evento entra si su rango
     * [fecha_inicio, fecha_fin] se traslapa con el pedido, aunque haya
     * empezado antes o termine después; `EventoPublicoController::diasDelEvento()`
     * recorta ese rango a los días que de verdad caen dentro.
     *
     * El rango es libre para que el calendario público pueda pedir un día, una
     * semana, un mes o un año con la misma consulta.
     * $pastoralId filtra al calendario propio de una pastoral (issue #3).
     */
    public function entreFechas(string $desde, string $hasta, ?int $pastoralId = null): array
    {
        $condicionPastoral = $pastoralId !== null ? ' AND pastoral_id = :pastoral' : '';
        return $this->fetchAll(
            'SELECT id, slug, titulo, fecha_inicio, fecha_fin, todo_el_dia, lugar, color
               FROM eventos
              WHERE publicado = 1 AND DATE(fecha_inicio) <= :hasta
                AND DATE(COALESCE(fecha_fin, fecha_inicio)) >= :desde'
                . $condicionPastoral . '
              ORDER BY fecha_inicio',
            [':desde' => $desde, ':hasta' => $hasta]
                + ($pastoralId !== null ? [':pastoral' => $pastoralId] : [])
        );
    }

    /** Atajo de entreFechas() para un mes entero. */
    public function delMes(int $anio, int $mes, ?int $pastoralId = null): array
    {
        $primero = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimo  = date('Y-m-t', (int) strtotime($primero));
        return $this->entreFechas($primero, $ultimo, $pastoralId);
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
