<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CentroModel — La sede parroquial y los centros que dependen de ella, en un
 * solo catálogo: "sede" y "centro" son el mismo tipo de dato (nombre,
 * dirección, teléfono), distinguidos por la columna tipo. Sin tabla aparte
 * para la sede: hoy hay una sola, pero forzar esa cardinalidad en el esquema
 * sería una regla que nadie pidió y que estorbaría el día que la parroquia
 * tenga una segunda.
 */
class CentroModel extends Model
{
    public const TIPOS = [
        'sede'   => 'Sede',
        'centro' => 'Centro',
    ];

    public function todos(): array
    {
        return $this->fetchAll(
            "SELECT * FROM centros ORDER BY FIELD(tipo, 'sede','centro'), orden, nombre"
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM centros WHERE id = :id', [':id' => $id]);
    }

    /** Activos, para selectores de otros módulos (pastorales, visitas MESC). */
    public function activos(): array
    {
        return $this->fetchAll(
            "SELECT id, tipo, nombre FROM centros WHERE activo = 1
             ORDER BY FIELD(tipo, 'sede','centro'), orden, nombre"
        );
    }

    /** Activos con todos sus datos (dirección incluida), para el sitio público. */
    public function activosCompletos(): array
    {
        return $this->fetchAll(
            "SELECT * FROM centros WHERE activo = 1
             ORDER BY FIELD(tipo, 'sede','centro'), orden, nombre"
        );
    }

    /**
     * Nombre corto de cada sede, para las fichas del filtro de horarios y el
     * lema de la portada: "Villa Bonita", "San Pío", "Jesús el Señor". La sede
     * parroquial se conoce por su colonia, que es el lugar que más se repite
     * en sus horarios; un centro, por su nombre hasta el primer " de " ("San
     * Pío de Pietrelcina" → "San Pío"). Así no hay que capturar un nombre más.
     *
     * @return array<int, string> id del centro => nombre corto
     */
    public static function nombresCortos(array $centros, array $horarios): array
    {
        $cortos = [];
        foreach ($centros as $centro) {
            $id = (int) $centro['id'];
            if ($centro['tipo'] === 'sede') {
                $lugares = [];
                foreach ($horarios as $h) {
                    if ((int) $h['centro_id'] === $id && $h['lugar']) {
                        $lugares[$h['lugar']] = ($lugares[$h['lugar']] ?? 0) + 1;
                    }
                }
                arsort($lugares);
                $cortos[$id] = (string) (array_key_first($lugares) ?? $centro['nombre']);
            } else {
                $cortos[$id] = explode(' de ', $centro['nombre'], 2)[0];
            }
        }
        return $cortos;
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO centros (tipo, nombre, direccion, telefono, descripcion, imagen, orden, activo)
             VALUES (:tipo, :nombre, :direccion, :telefono, :descripcion, :imagen, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE centros
                SET tipo = :tipo, nombre = :nombre, direccion = :direccion, telefono = :telefono,
                    descripcion = :descripcion, imagen = :imagen, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM centros WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':tipo'        => $datos['tipo'],
            ':nombre'      => $datos['nombre'],
            ':direccion'   => $datos['direccion'],
            ':telefono'    => $datos['telefono'],
            ':descripcion' => $datos['descripcion'],
            ':imagen'      => $datos['imagen'],
            ':orden'       => $datos['orden'],
            ':activo'      => $datos['activo'],
        ];
    }
}
