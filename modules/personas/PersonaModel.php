<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * PersonaModel — Sacerdote, diáconos, religiosos, laicos y personal.
 *
 * Borrado lógico por defecto (`activo`): quien deja el cargo se desactiva, no
 * se borra, porque `organigrama_nodos.persona_id` puede seguir apuntando a su
 * historial. Un borrado real solo tiene sentido para corregir un alta hecha
 * por error, y la FK de organigrama ya lo deja en NULL sin romper nada
 * (`ON DELETE SET NULL`).
 */
class PersonaModel extends Model
{
    /** tipo => nombre visible, en el orden en que se muestran en "Quiénes somos". */
    public const TIPOS = [
        'parroco'   => 'Párroco',
        'vicario'   => 'Vicario',
        'diacono'   => 'Diácono',
        'religioso' => 'Religioso o religiosa',
        'laico'     => 'Laico',
        'staff'     => 'Personal de oficina',
    ];

    public function todas(): array
    {
        return $this->fetchAll(
            "SELECT * FROM personas ORDER BY FIELD(tipo, " . $this->ordenTipos() . "), orden, nombre"
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM personas WHERE id = :id', [':id' => $id]);
    }

    /** Para el sitio público: solo activos, agrupados por tipo. */
    public function activasPorTipo(): array
    {
        $filas = $this->fetchAll(
            "SELECT * FROM personas WHERE activo = 1
             ORDER BY FIELD(tipo, " . $this->ordenTipos() . "), orden, nombre"
        );
        $porTipo = [];
        foreach ($filas as $fila) {
            $porTipo[$fila['tipo']][] = $fila;
        }
        return $porTipo;
    }

    /** id => nombre de las personas activas, para el selector del organigrama. */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, nombre, cargo FROM personas WHERE activo = 1 ORDER BY nombre');
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO personas (nombre, cargo, tipo, semblanza, foto, email, telefono, orden, activo)
             VALUES (:nombre, :cargo, :tipo, :semblanza, :foto, :email, :telefono, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE personas
                SET nombre = :nombre, cargo = :cargo, tipo = :tipo, semblanza = :semblanza,
                    foto = :foto, email = :email, telefono = :telefono, orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM personas WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':nombre'    => $datos['nombre'],
            ':cargo'     => $datos['cargo'],
            ':tipo'      => $datos['tipo'],
            ':semblanza' => $datos['semblanza'],
            ':foto'      => $datos['foto'],
            ':email'     => $datos['email'],
            ':telefono'  => $datos['telefono'],
            ':orden'     => $datos['orden'],
            ':activo'    => $datos['activo'],
        ];
    }

    private function ordenTipos(): string
    {
        return implode(',', array_map(
            static fn (string $t): string => "'" . $t . "'",
            array_keys(self::TIPOS)
        ));
    }
}
