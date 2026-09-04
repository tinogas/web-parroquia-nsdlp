<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * AvisoModel — Boletín semanal y noticias parroquiales.
 *
 * Publicar tiene dos escalones, no uno: `publicado_interno` lo hace visible
 * dentro del panel a los miembros de su pastoral, y `publicado` lo saca al
 * sitio web. Todo entra con los dos en 0, como borrador, y el segundo exige el
 * primero (lo garantiza `chk_avi_escalon`, ver install.sql). Los tres estados
 * de ESTADOS son la lectura de esas dos columnas, no una tercera columna.
 */
class AvisoModel extends Model
{
    public const TIPOS = [
        'noticia'    => 'Noticia',
        'boletin'    => 'Boletín',
        'comunicado' => 'Comunicado',
    ];

    /**
     * El tipo que va dirigido a TODA la parroquia, sea de la pastoral que sea.
     *
     * Hasta ahora los tres tipos eran una etiqueta y nada más: se mostraba en
     * cuatro pantallas y lo único que cambiaba era que en `boletin` seguía
     * apareciendo el campo del PDF al editar. `comunicado` es el primero que
     * hace algo, y es esto: se salta el alcance por pastoral —el suyo dice
     * quién lo escribe, no a quién va— y aparece en el panel de todo el mundo.
     *
     * No lo confundas con dejar la pastoral vacía, que sigue existiendo y
     * significa otra cosa: aquello es un aviso de la parroquia, sin dueño;
     * esto es un comunicado DE una pastoral PARA todos, y el panel lo muestra
     * con su nombre.
     *
     * Solo cuenta ya publicado hacia dentro. Un comunicado en borrador es de
     * quien lo escribe y de nadie más, como cualquier otro borrador.
     */
    public const TIPO_PARA_TODOS = 'comunicado';

    /** Icono de cada tipo, para el panel y la campana de la barra. */
    public const ICONOS = [
        'noticia'    => 'bi-newspaper',
        'boletin'    => 'bi-journal-text',
        'comunicado' => 'bi-megaphone',
    ];

    /** El icono de un tipo, con el del aviso genérico como respaldo. */
    public static function icono(?string $tipo): string
    {
        return self::ICONOS[$tipo] ?? 'bi-megaphone';
    }

    /**
     * Añade los comunicados a una condición de alcance por pastoral.
     *
     * Se envuelve la condición en vez de tocar Model::condicionAlcance() ni
     * condicionVisibilidadPanel(): esos dos los comparte con cursos, que no
     * tiene tipos y donde un comunicado no significa nada.
     *
     * Con la condición vacía no hay nada que añadir —ya lo ve todo—. Y ojo con
     * el otro extremo: `1 = 0` es una cuenta de alcance limitado sin ninguna
     * pastoral asignada, y ahí el comunicado SÍ entra, porque va dirigido a
     * toda la parroquia y esa cuenta también es de la parroquia.
     */
    private function conComunicados(string $condicion): string
    {
        if ($condicion === '') {
            return '';
        }
        return '(' . $condicion . " OR (a.publicado_interno = 1 AND a.tipo = '" . self::TIPO_PARA_TODOS . "'))";
    }

    /**
     * Listado paginado para el panel. $filtro: 'todos' o una clave de ESTADOS.
     *
     * $audiencia y $propias van juntas y salen de Controller::audienciaInterna()
     * y Auth::pastoralesPermitidas(): la primera dice qué puede leer (incluye
     * sus Comisiones y el contenido general), la segunda de quién son los
     * borradores que además ve. Con alcance global, $audiencia es null y no se
     * recorta nada. Ver Model::condicionVisibilidadPanel().
     */
    public function listar(int $pagina, string $filtro = 'todos', ?array $audiencia = null, array $propias = []): array
    {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'publico') {
            $condiciones[] = 'a.publicado = 1';
        } elseif ($filtro === 'interno') {
            $condiciones[] = 'a.publicado_interno = 1 AND a.publicado = 0';
        } elseif ($filtro === 'borrador') {
            $condiciones[] = 'a.publicado_interno = 0';
        }

        [$condicionPastoral, $paramsPastoral] = $this->condicionVisibilidadPanel(
            $audiencia,
            $propias,
            'a.pastoral_id',
            'a.publicado_interno'
        );
        // Los comunicados de otras pastorales también salen en este listado: si
        // la campana anuncia dos avisos sin leer y al pulsar Avisos no
        // aparecen, el contador miente. Ver conComunicados().
        $condicionPastoral = $this->conComunicados($condicionPastoral);
        if ($condicionPastoral !== '') {
            $condiciones[] = $condicionPastoral;
            $params += $paramsPastoral;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT a.*, u.nombre AS autor, p.nombre AS pastoral_nombre
               FROM avisos a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
               LEFT JOIN pastorales p ON p.id = a.pastoral_id
               {$where}
              ORDER BY a.fecha_publicacion DESC, a.id DESC",
            $params,
            $pagina,
            15
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM avisos WHERE id = :id', [':id' => $id]);
    }

    /**
     * Condición SQL de vigencia, compartida por toda consulta pública: además
     * de publicado=1 y fecha_publicacion <= hoy (ya existían desde la etapa 5
     * y funcionan como "visible desde"), vigente_hasta es el "visible hasta"
     * del issue #3 — nulo significa sin fecha de baja. Con esto, un aviso se
     * publica y despublica solo, sin que nadie tenga que volver a tocarlo.
     *
     * Es solo del sitio web: el tablón interno del panel no la usa, porque
     * ahí lo que se enseña es lo publicado a la pastoral **este mes**, y una
     * ventana pensada para el escaparate público no tiene por qué esconderle
     * a un ministro lo que su coordinadora acaba de anunciarle. Ver
     * internosDelMes().
     */
    private const VIGENTE = "publicado = 1 AND fecha_publicacion <= CURDATE()
                             AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())";

    /** Detalle público: solo publicados y vigentes. */
    public function porSlugPublicado(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT a.*, u.nombre AS autor FROM avisos a LEFT JOIN usuarios u ON u.id = a.usuario_id
              WHERE a.slug = :slug AND ' . self::VIGENTE,
            [':slug' => $slug]
        );
    }

    /** Listado público paginado. */
    public function publicados(int $pagina, int $porPagina = 9): array
    {
        return $this->paginar(
            'SELECT * FROM avisos WHERE ' . self::VIGENTE . '
              ORDER BY fecha_publicacion DESC, id DESC',
            [],
            $pagina,
            $porPagina
        );
    }

    /** slug + fecha, de todos los visibles al público. Para sitemap.xml. */
    public function paraSitemap(): array
    {
        return $this->fetchAll(
            'SELECT slug, COALESCE(updated_at, created_at) AS modificado
               FROM avisos WHERE ' . self::VIGENTE
        );
    }

    /** Los más recientes, para destacar en la portada. */
    public function recientes(int $limite = 3): array
    {
        return $this->fetchAll(
            'SELECT * FROM avisos WHERE ' . self::VIGENTE . '
              ORDER BY fecha_publicacion DESC, id DESC LIMIT ' . max(1, $limite)
        );
    }

    /** Avisos vigentes de una pastoral, para su ficha pública (issue #3). */
    public function publicadosPorPastoral(int $pastoralId, int $limite = 6): array
    {
        return $this->fetchAll(
            'SELECT * FROM avisos WHERE pastoral_id = :pastoral AND ' . self::VIGENTE . '
              ORDER BY fecha_publicacion DESC, id DESC LIMIT ' . max(1, $limite),
            [':pastoral' => $pastoralId]
        );
    }

    public function incrementarVistas(int $id): void
    {
        $this->execute('UPDATE avisos SET vistas = vistas + 1 WHERE id = :id', [':id' => $id]);
    }

    /**
     * Todo lo publicado a la pastoral en el mes dado (el actual si no se pide
     * otro), para el tablón del panel. Sin límite: el mes ya acota cuánto es.
     *
     * De las dos fechas de la ventana pública solo pesa la de baja:
     *
     *  - `fecha_publicacion` se ignora, así que un aviso con fecha futura sí
     *    aparece. Dentro de casa interesa justamente saber lo que viene, y esa
     *    fecha es cuándo debe salir al escaparate, no cuándo enterarse.
     *  - `vigente_hasta` se respeta: lo caducado ya no le sirve a nadie y solo
     *    estorbaría en el tablón.
     *
     * Incluye lo que además ya es público: que un aviso haya salido al sitio
     * no lo hace menos novedad para los suyos.
     *
     * El mes se mide por `publicado_interno_at`, el momento real en que se
     * publicó hacia dentro, y como rango en vez de con DATE_FORMAT para que
     * pueda usarse el índice idx_avi_interno.
     *
     * @param ?array $audiencia Formato de Controller::audienciaInterna(); null = alcance global
     */
    public function internosDelMes(?array $audiencia, ?string $mes = null): array
    {
        [$condicion, $params] = $this->condicionAlcance($audiencia, 'a.pastoral_id');

        $mes = $mes ?? date('Y-m');
        $params += [
            ':desde' => $mes . '-01 00:00:00',
            ':hasta' => date('Y-m-01 00:00:00', strtotime($mes . '-01 +1 month')),
        ];

        $where = 'a.publicado_interno = 1
                  AND a.publicado_interno_at >= :desde AND a.publicado_interno_at < :hasta
                  AND (a.vigente_hasta IS NULL OR a.vigente_hasta >= CURDATE())';
        // Un comunicado entra aunque sea de otra pastoral — ver conComunicados().
        $condicion = $this->conComunicados($condicion);
        if ($condicion !== '') {
            $where .= ' AND ' . $condicion;
        }

        return $this->fetchAll(
            "SELECT a.*, p.nombre AS pastoral_nombre
               FROM avisos a
               LEFT JOIN pastorales p ON p.id = a.pastoral_id
              WHERE {$where}
              ORDER BY a.publicado_interno_at DESC, a.id DESC",
            $params
        );
    }

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO avisos
                (slug, titulo, resumen, contenido, imagen, tipo, archivo_pdf, pastoral_id,
                 fecha_publicacion, vigente_hasta, destacado,
                 publicado_interno, publicado_interno_at, publicado, usuario_id)
             VALUES
                (:slug, :titulo, :resumen, :contenido, :imagen, :tipo, :pdf, :pastoral,
                 :fecha, :vigenteHasta, :destacado,
                 :interno, :internoAt, :publicado, :usuario)',
            $this->parametros($datos) + [':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE avisos
                SET slug = :slug, titulo = :titulo, resumen = :resumen, contenido = :contenido,
                    imagen = :imagen, tipo = :tipo, archivo_pdf = :pdf, pastoral_id = :pastoral,
                    fecha_publicacion = :fecha, vigente_hasta = :vigenteHasta,
                    destacado = :destacado,
                    publicado_interno = :interno, publicado_interno_at = :internoAt,
                    publicado = :publicado, updated_at = NOW()
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM avisos WHERE id = :id', [':id' => $id]);
    }

    // ── Sin leer (aviso_lecturas) ────────────────────────────────────────
    // Lectura de verdad, por persona, y no «publicado desde tu último
    // ingreso»: el contador de la campana tiene que bajar al abrir el aviso y
    // no volver a subir, que es como ya se comporta el de los mensajes. Ver
    // docs/migraciones/2026-09-04-avisos-sin-leer.sql

    /**
     * Los avisos que esta persona tiene sin abrir, dentro de lo que le toca
     * leer: publicados hacia dentro, todavía vigentes, y sin su marca de
     * lectura. Los comunicados de otras pastorales entran igual.
     *
     * Sin recorte por mes, a diferencia de internosDelMes(): lo que se
     * anuncia no deja de estar sin leer porque cambie el mes. Lo que sí lo
     * cierra es `vigente_hasta`, que es la forma que ya tenía el sistema de
     * decir «esto ya pasó».
     *
     * @param ?array $audiencia Formato de Controller::audienciaInterna(); null = alcance global
     */
    public function sinLeer(?array $audiencia, int $usuarioId, int $limite = 0): array
    {
        [$where, $params] = $this->condicionSinLeer($audiencia, $usuarioId);

        // El límite se interpola porque LIMIT no admite parámetro con
        // PDO::ATTR_EMULATE_PREPARES en false; va casteado a entero.
        $limitSql = $limite > 0 ? ' LIMIT ' . (int) $limite : '';

        return $this->fetchAll(
            "SELECT a.id, a.titulo, a.resumen, a.tipo, a.publicado_interno_at,
                    p.nombre AS pastoral_nombre
               FROM avisos a
               LEFT JOIN pastorales p ON p.id = a.pastoral_id
              WHERE {$where}
              ORDER BY a.publicado_interno_at DESC, a.id DESC{$limitSql}",
            $params
        );
    }

    /** Cuántos tiene sin abrir. La misma condición que sinLeer(), contada. */
    public function contarSinLeer(?array $audiencia, int $usuarioId): int
    {
        [$where, $params] = $this->condicionSinLeer($audiencia, $usuarioId);

        return (int) $this->fetchColumn("SELECT COUNT(*) FROM avisos a WHERE {$where}", $params);
    }

    /**
     * La condición de «sin leer», en un solo sitio: la usan la lista de la
     * campana y su contador, y si cada una llevara su copia acabarían
     * diciendo números distintos — el globo con un número y la lista con
     * otro es justo el error que nadie perdona en un contador.
     *
     * @return array{0: string, 1: array} [condición SQL, parámetros]
     */
    private function condicionSinLeer(?array $audiencia, int $usuarioId): array
    {
        [$condicion, $params] = $this->condicionAlcance($audiencia, 'a.pastoral_id');
        $condicion = $this->conComunicados($condicion);

        $where = 'a.publicado_interno = 1
                  AND (a.vigente_hasta IS NULL OR a.vigente_hasta >= CURDATE())
                  AND NOT EXISTS (SELECT 1 FROM aviso_lecturas l
                                   WHERE l.aviso_id = a.id AND l.usuario_id = :usuario)';
        if ($condicion !== '') {
            $where .= ' AND ' . $condicion;
        }
        $params[':usuario'] = $usuarioId;

        return [$where, $params];
    }

    /**
     * Deja la marca de lectura. IGNORE porque la clave primaria compuesta ya
     * impide el duplicado: así no hay que preguntar antes si ya estaba.
     */
    public function marcarLeido(int $avisoId, int $usuarioId): void
    {
        $this->execute(
            'INSERT IGNORE INTO aviso_lecturas (aviso_id, usuario_id) VALUES (:aviso, :usuario)',
            [':aviso' => $avisoId, ':usuario' => $usuarioId]
        );
    }

    private function parametros(array $datos): array
    {
        return [
            ':slug'         => $datos['slug'],
            ':titulo'       => $datos['titulo'],
            ':resumen'      => $datos['resumen'],
            ':contenido'    => $datos['contenido'],
            ':imagen'       => $datos['imagen'],
            ':tipo'         => $datos['tipo'],
            ':pdf'          => $datos['archivo_pdf'],
            ':pastoral'     => $datos['pastoral_id'],
            ':fecha'        => $datos['fecha_publicacion'],
            ':vigenteHasta' => $datos['vigente_hasta'],
            ':destacado'    => $datos['destacado'],
            ':interno'      => $datos['publicado_interno'],
            ':internoAt'    => $datos['publicado_interno_at'],
            ':publicado'    => $datos['publicado'],
        ];
    }
}
