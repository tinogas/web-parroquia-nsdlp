<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * GaleriaModel — Fotografías del sitio.
 *
 * La columna que importa es `autorizacion_imagen`: deja constancia de que
 * existe autorización de uso. La consulta pública exige `publicada = 1` **y**
 * `autorizacion_imagen = 1` a la vez, de modo que una foto sin esa
 * autorización registrada no puede llegar al sitio ni por descuido.
 * Ver docs/PRIVACIDAD.md
 */
class GaleriaModel extends Model
{
    public function listar(int $pagina, string $filtro = 'todas', ?array $pastoralesPermitidas = null): array
    {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'publicadas') {
            $condiciones[] = 'g.publicada = 1';
        } elseif ($filtro === 'ocultas') {
            $condiciones[] = 'g.publicada = 0';
        }

        [$condicionPastoral, $paramsPastoral] = $this->condicionAlcance($pastoralesPermitidas, 'g.pastoral_id');
        if ($condicionPastoral !== '') {
            $condiciones[] = $condicionPastoral;
            $params += $paramsPastoral;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT g.*, u.nombre AS autor, p.nombre AS pastoral_nombre
               FROM galeria_imagenes g
               LEFT JOIN usuarios u ON u.id = g.usuario_id
               LEFT JOIN pastorales p ON p.id = g.pastoral_id
               {$where}
              ORDER BY g.created_at DESC",
            $params,
            $pagina,
            24
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM galeria_imagenes WHERE id = :id', [':id' => $id]);
    }

    /** Para el sitio público: solo lo publicado y con autorización de uso. */
    public function publicadas(int $pagina, int $porPagina = 24): array
    {
        return $this->paginar(
            'SELECT * FROM galeria_imagenes WHERE publicada = 1 AND autorizacion_imagen = 1
              ORDER BY orden, created_at DESC',
            [],
            $pagina,
            $porPagina
        );
    }

    /**
     * Inserta varias fotos de un mismo lote, con la misma autorización para
     * todas. Nacen sin publicar a propósito: la autorización es sobre el uso
     * de la imagen, publicar es una decisión editorial aparte.
     */
    public function crearLote(array $rutas, bool $autorizacion, ?int $pastoralId, int $usuarioId): int
    {
        $creadas = 0;
        foreach ($rutas as $ruta) {
            $this->execute(
                'INSERT INTO galeria_imagenes (archivo, pastoral_id, autorizacion_imagen, publicada, usuario_id)
                 VALUES (:archivo, :pastoral, :autorizacion, 0, :usuario)',
                [
                    ':archivo'      => $ruta,
                    ':pastoral'     => $pastoralId,
                    ':autorizacion' => $autorizacion ? 1 : 0,
                    ':usuario'      => $usuarioId,
                ]
            );
            $creadas++;
        }
        return $creadas;
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE galeria_imagenes
                SET titulo = :titulo, alt_texto = :alt, autorizacion_imagen = :autorizacion,
                    publicada = :publicada, orden = :orden, pastoral_id = :pastoral
              WHERE id = :id',
            [
                ':titulo'       => $datos['titulo'],
                ':alt'          => $datos['alt_texto'],
                ':autorizacion' => $datos['autorizacion_imagen'],
                ':publicada'    => $datos['publicada'],
                ':orden'        => $datos['orden'],
                ':pastoral'     => $datos['pastoral_id'],
                ':id'           => $id,
            ]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM galeria_imagenes WHERE id = :id', [':id' => $id]);
    }
}
