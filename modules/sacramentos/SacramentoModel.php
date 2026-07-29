<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * SacramentoModel — Catálogo informativo de los sacramentos.
 *
 * Sin formulario de solicitud (issue #3: se eliminó junto con
 * solicitudes_sacramento, solicitudes_bitacora y sacramento_campos). La
 * sección es puramente informativa: requisitos, documentos y aportación,
 * para que quien busque el sacramento sepa cómo llevarlo a cabo acercándose
 * a la oficina parroquial.
 */
class SacramentoModel extends Model
{
    public function todos(): array
    {
        return $this->fetchAll('SELECT * FROM sacramentos ORDER BY orden, nombre');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM sacramentos WHERE id = :id', [':id' => $id]);
    }

    public function porSlugActivo(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM sacramentos WHERE slug = :slug AND activo = 1', [':slug' => $slug]);
    }

    public function activos(): array
    {
        return $this->fetchAll('SELECT * FROM sacramentos WHERE activo = 1 ORDER BY orden, nombre');
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO sacramentos (slug, nombre, descripcion, requisitos, documentos, aportacion, imagen, orden, activo)
             VALUES (:slug, :nombre, :descripcion, :requisitos, :documentos, :aportacion, :imagen, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE sacramentos
                SET slug = :slug, nombre = :nombre, descripcion = :descripcion,
                    requisitos = :requisitos, documentos = :documentos, aportacion = :aportacion,
                    imagen = :imagen, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM sacramentos WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':slug'        => $datos['slug'],
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'],
            ':requisitos'  => $datos['requisitos'],
            ':documentos'  => $datos['documentos'],
            ':aportacion'  => $datos['aportacion'],
            ':imagen'      => $datos['imagen'],
            ':orden'       => $datos['orden'],
            ':activo'      => $datos['activo'],
        ];
    }
}
