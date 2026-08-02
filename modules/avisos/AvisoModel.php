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
     * La ventana de fechas del aviso, sin decir nada de en qué escalón está:
     * fecha_publicacion funciona como "visible desde" (una fecha futura no se
     * muestra hasta llegar ese día) y vigente_hasta como "visible hasta" del
     * issue #3 —nulo significa sin fecha de baja—. Con las dos, un aviso se
     * publica y se retira solo, sin que nadie vuelva a tocarlo.
     *
     * Va aparte de VIGENTE porque el escalón interno necesita la misma ventana
     * sin el `publicado = 1`: un aviso que aún no ha llegado su fecha tampoco
     * debe salir en las novedades del panel, y uno caducado tampoco.
     */
    private const EN_FECHA = "fecha_publicacion <= CURDATE()
                              AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())";

    /** Condición de visibilidad pública, compartida por toda consulta del sitio web. */
    private const VIGENTE = 'publicado = 1 AND ' . self::EN_FECHA;

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
     * Lo último publicado hacia dentro que le toca leer a esta persona, para
     * las miniaturas del panel. Incluye lo que además ya es público: que un
     * aviso haya salido al sitio no lo hace menos novedad para su pastoral.
     *
     * Ordena por publicado_interno_at, que es el momento real de publicación
     * —no fecha_publicacion, que es una fecha que escribe la persona, ni
     * created_at, que es cuando se empezó el borrador—.
     *
     * @param ?array $audiencia Formato de Controller::audienciaInterna(); null = alcance global
     */
    public function internosPara(?array $audiencia, int $limite = 4): array
    {
        [$condicion, $params] = $this->condicionAlcance($audiencia, 'a.pastoral_id');

        $where = 'a.publicado_interno = 1 AND ' . str_replace(
            ['fecha_publicacion', 'vigente_hasta'],
            ['a.fecha_publicacion', 'a.vigente_hasta'],
            self::EN_FECHA
        );
        if ($condicion !== '') {
            $where .= ' AND ' . $condicion;
        }

        return $this->fetchAll(
            "SELECT a.*, p.nombre AS pastoral_nombre
               FROM avisos a
               LEFT JOIN pastorales p ON p.id = a.pastoral_id
              WHERE {$where}
              ORDER BY a.publicado_interno_at DESC, a.id DESC
              LIMIT " . max(1, $limite),
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
