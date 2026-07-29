<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * BloqueModel — Textos editables anclados a zonas fijas del sitio.
 *
 * El panel puede cambiar el título, el contenido y la imagen de cada bloque,
 * pero no crear ni borrar claves: son las anclas que las vistas buscan. Es lo
 * que permite que el contenido sea totalmente editable sin que nadie pueda
 * dejar la portada sin su saludo de bienvenida. Ver docs/ARQUITECTURA.md
 */
class BloqueModel extends Model
{
    /** zona => [nombre visible, icono] */
    public const ZONAS = [
        'inicio'      => ['Inicio',              'bi-house'],
        'nosotros'    => ['Quiénes somos',       'bi-people'],
        'horarios'    => ['Horarios',            'bi-clock'],
        'sacramentos' => ['Sacramentos',         'bi-droplet'],
        'pastorales'  => ['Pastorales',          'bi-hand-thumbs-up'],
        'cursos'      => ['Cursos',              'bi-mortarboard'],
        'contacto'    => ['Contacto',            'bi-geo-alt'],
        'general'     => ['Otros',               'bi-file-text'],
    ];

    public function todos(): array
    {
        return $this->fetchAll(
            'SELECT b.*, u.nombre AS editor
             FROM bloques_contenido b
             LEFT JOIN usuarios u ON u.id = b.actualizado_por
             ORDER BY FIELD(b.zona, ' . $this->ordenZonas() . '), b.orden, b.id'
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM bloques_contenido WHERE id = :id', [':id' => $id]);
    }

    public function porClave(string $clave): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM bloques_contenido WHERE clave = :clave AND activo = 1',
            [':clave' => $clave]
        );
    }

    /**
     * Todos los bloques activos de una zona, indexados por clave. Las vistas
     * públicas cargan su zona completa de una vez en lugar de consultar bloque
     * por bloque.
     */
    public function porZona(string $zona): array
    {
        $bloques = [];
        $filas = $this->fetchAll(
            'SELECT * FROM bloques_contenido WHERE zona = :zona AND activo = 1 ORDER BY orden, id',
            [':zona' => $zona]
        );
        foreach ($filas as $fila) {
            $bloques[$fila['clave']] = $fila;
        }
        return $bloques;
    }

    public function actualizar(int $id, array $datos, int $usuarioId): int
    {
        return $this->execute(
            'UPDATE bloques_contenido
                SET titulo = :titulo, contenido = :contenido, imagen = :imagen,
                    activo = :activo, actualizado_por = :usuario, updated_at = NOW()
              WHERE id = :id',
            [
                ':titulo'    => $datos['titulo'],
                ':contenido' => $datos['contenido'],
                ':imagen'    => $datos['imagen'],
                ':activo'    => $datos['activo'],
                ':usuario'   => $usuarioId,
                ':id'        => $id,
            ]
        );
    }

    /** Lista SQL para ordenar las zonas como están declaradas en ZONAS. */
    private function ordenZonas(): string
    {
        $zonas = array_map(
            static fn (string $z): string => "'" . $z . "'",
            array_keys(self::ZONAS)
        );
        return implode(',', $zonas);
    }

    public static function nombreZona(string $zona): string
    {
        return self::ZONAS[$zona][0] ?? ucfirst($zona);
    }

    public static function iconoZona(string $zona): string
    {
        return self::ZONAS[$zona][1] ?? 'bi-file-text';
    }
}
