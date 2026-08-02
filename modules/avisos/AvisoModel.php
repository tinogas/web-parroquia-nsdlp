<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * AvisoModel — Boletín semanal y noticias parroquiales.
 *
 * `publicado` arranca en 0: todo entra como borrador. `pastoral_id` existe en
 * el esquema desde ya, pero hasta la etapa 6 —cuando exista `pastorales` y el
 * rol coordinador— todo aviso se crea con `pastoral_id = NULL`, es decir,
 * como aviso parroquial global.
 */
class AvisoModel extends Model
{
    public const TIPOS = [
        'noticia'    => 'Noticia',
        'boletin'    => 'Boletín',
        'comunicado' => 'Comunicado',
    ];

    /**
     * Listado paginado para el panel. $filtro: 'todos', 'publicados' o 'borradores'.
     * $pastoralesPermitidas: null = ve todo (alcance global); array de IDs = solo esas.
     */
    public function listar(int $pagina, string $filtro = 'todos', ?array $pastoralesPermitidas = null): array
    {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'publicados') {
            $condiciones[] = 'a.publicado = 1';
        } elseif ($filtro === 'borradores') {
            $condiciones[] = 'a.publicado = 0';
        }

        [$condicionPastoral, $paramsPastoral] = $this->condicionAlcance($pastoralesPermitidas, 'a.pastoral_id');
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

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO avisos
                (slug, titulo, resumen, contenido, imagen, tipo, archivo_pdf, pastoral_id,
                 fecha_publicacion, vigente_hasta, destacado, publicado, usuario_id)
             VALUES
                (:slug, :titulo, :resumen, :contenido, :imagen, :tipo, :pdf, :pastoral,
                 :fecha, :vigenteHasta, :destacado, :publicado, :usuario)',
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
                    destacado = :destacado, publicado = :publicado, updated_at = NOW()
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
            ':publicado'    => $datos['publicado'],
        ];
    }
}
