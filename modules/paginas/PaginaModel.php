<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * PaginaModel — Páginas libres con dirección propia.
 *
 * A diferencia de los bloques de contenido, aquí el panel sí puede crear y
 * borrar. Es la válvula de escape del sistema: sirve para lo que no cabe en las
 * secciones fijas del sitio, y es donde vive el aviso de privacidad.
 */
class PaginaModel extends Model
{
    /** Páginas que el sistema necesita y por tanto no se pueden borrar. */
    public const PROTEGIDAS = ['aviso-de-privacidad'];

    public function todas(): array
    {
        return $this->fetchAll(
            'SELECT p.*, u.nombre AS editor
             FROM paginas p
             LEFT JOIN usuarios u ON u.id = p.actualizado_por
             ORDER BY p.orden, p.titulo'
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM paginas WHERE id = :id', [':id' => $id]);
    }

    /** Para el sitio público: solo devuelve páginas publicadas. */
    public function porSlugPublicada(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM paginas WHERE slug = :slug AND publicada = 1',
            [':slug' => $slug]
        );
    }

    // Las páginas del menú del sitio las lee paginas_del_menu() en
    // core/helpers.php, no este modelo: el menú se dibuja en todas las páginas
    // públicas y no puede depender de que el módulo de páginas esté cargado.
    // Ver el comentario de pagina_publicada(), que existe por lo mismo.

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO paginas (slug, titulo, contenido, meta_descripcion, en_menu, orden,
                                  publicada, actualizado_por, updated_at)
             VALUES (:slug, :titulo, :contenido, :meta, :menu, :orden, :publicada, :usuario, NOW())',
            [
                ':slug'      => $datos['slug'],
                ':titulo'    => $datos['titulo'],
                ':contenido' => $datos['contenido'],
                ':meta'      => $datos['meta_descripcion'],
                ':menu'      => $datos['en_menu'],
                ':orden'     => $datos['orden'],
                ':publicada' => $datos['publicada'],
                ':usuario'   => $usuarioId,
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos, int $usuarioId): int
    {
        return $this->execute(
            'UPDATE paginas
                SET slug = :slug, titulo = :titulo, contenido = :contenido,
                    meta_descripcion = :meta, en_menu = :menu, orden = :orden,
                    publicada = :publicada, actualizado_por = :usuario, updated_at = NOW()
              WHERE id = :id',
            [
                ':slug'      => $datos['slug'],
                ':titulo'    => $datos['titulo'],
                ':contenido' => $datos['contenido'],
                ':meta'      => $datos['meta_descripcion'],
                ':menu'      => $datos['en_menu'],
                ':orden'     => $datos['orden'],
                ':publicada' => $datos['publicada'],
                ':usuario'   => $usuarioId,
                ':id'        => $id,
            ]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM paginas WHERE id = :id', [':id' => $id]);
    }
}
