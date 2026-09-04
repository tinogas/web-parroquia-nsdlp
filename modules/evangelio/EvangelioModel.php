<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * EvangelioModel — Evangelio del día y reflexión del párroco, uno por fecha.
 *
 * Calca PaginaModel: contenido editorial de toda la parroquia, sin alcance por
 * pastoral, un solo booleano de publicación. La diferencia es que aquí el
 * identificador natural es `fecha`, no un `slug` con URL propia.
 */
class EvangelioModel extends Model
{
    public function porFecha(string $fecha): ?array
    {
        return $this->fetchOne('SELECT * FROM evangelios_dia WHERE fecha = :fecha', [':fecha' => $fecha]);
    }

    /** Para la portada del sitio: el de hoy, solo si ya está publicado. */
    public function deHoy(): ?array
    {
        return $this->fetchOne('SELECT * FROM evangelios_dia WHERE fecha = CURDATE() AND publicado = 1');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM evangelios_dia WHERE id = :id', [':id' => $id]);
    }

    /** Listado paginado del panel, más recientes primero — crece sin techo, a diferencia de paginas. */
    public function listar(int $pagina, int $porPagina = 20): array
    {
        return $this->paginar(
            'SELECT e.*, u.nombre AS editor
               FROM evangelios_dia e
               LEFT JOIN usuarios u ON u.id = e.usuario_id
              ORDER BY e.fecha DESC',
            [],
            $pagina,
            $porPagina
        );
    }

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO evangelios_dia (fecha, evangelio, reflexion, publicado, usuario_id, updated_at)
             VALUES (:fecha, :evangelio, :reflexion, :publicado, :usuario, NOW())',
            $this->parametros($datos) + [':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos, int $usuarioId): int
    {
        return $this->execute(
            'UPDATE evangelios_dia
                SET fecha = :fecha, evangelio = :evangelio, reflexion = :reflexion,
                    publicado = :publicado, usuario_id = :usuario, updated_at = NOW()
              WHERE id = :id',
            $this->parametros($datos) + [':usuario' => $usuarioId, ':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM evangelios_dia WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':fecha'     => $datos['fecha'],
            ':evangelio' => $datos['evangelio'],
            ':reflexion' => $datos['reflexion'],
            ':publicado' => $datos['publicado'],
        ];
    }
}
