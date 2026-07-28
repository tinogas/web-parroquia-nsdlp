<?php
require_once BASE_PATH . '/core/Model.php';

/** CarruselModel — Diapositivas del carrusel de la portada. No van a ser muchas. */
class CarruselModel extends Model
{
    public function todos(): array
    {
        return $this->fetchAll('SELECT * FROM carrusel ORDER BY orden, id');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM carrusel WHERE id = :id', [':id' => $id]);
    }

    /** Para la portada: solo las activas. */
    public function activas(): array
    {
        return $this->fetchAll('SELECT * FROM carrusel WHERE activo = 1 ORDER BY orden, id');
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO carrusel (imagen, titulo, subtitulo, enlace, orden, activo)
             VALUES (:imagen, :titulo, :subtitulo, :enlace, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE carrusel SET imagen = :imagen, titulo = :titulo, subtitulo = :subtitulo,
                                 enlace = :enlace, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM carrusel WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':imagen'    => $datos['imagen'],
            ':titulo'    => $datos['titulo'],
            ':subtitulo' => $datos['subtitulo'],
            ':enlace'    => $datos['enlace'],
            ':orden'     => $datos['orden'],
            ':activo'    => $datos['activo'],
        ];
    }
}
