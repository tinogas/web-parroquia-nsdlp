<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * UsuarioPerfilModel — perfiles adicionales de acceso de una cuenta.
 *
 * Una fila es "un rol distinto sobre UNA pastoral distinta" para la MISMA
 * cuenta (mismo correo, misma contraseña, misma ficha de Persona vinculada):
 * ver docs/ARQUITECTURA.md, "Perfiles adicionales: un rol distinto sobre otra
 * pastoral, sin segunda cuenta". El perfil PRINCIPAL de una cuenta —su
 * `usuarios.rol` de siempre, con `usuarios_pastorales`/`usuarios_centros`—
 * nunca vive aquí: esta tabla es solo lo adicional.
 */
class UsuarioPerfilModel extends Model
{
    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM usuarios_perfiles WHERE id = :id', [':id' => $id]);
    }

    /**
     * Perfiles activos de la cuenta, con el nombre de pastoral y de sede ya
     * resueltos — para la pantalla de elegir perfil al iniciar sesión y para
     * el resumen de "Perfiles adicionales" en el formulario de la cuenta.
     */
    public function activosDeUsuario(int $usuarioId): array
    {
        return $this->fetchAll(
            'SELECT up.*, p.nombre AS pastoral_nombre, c.nombre AS centro_nombre
               FROM usuarios_perfiles up
               JOIN pastorales p ON p.id = up.pastoral_id
               LEFT JOIN centros c ON c.id = up.centro_id
              WHERE up.usuario_id = :id AND up.activo = 1
              ORDER BY up.nombre',
            [':id' => $usuarioId]
        );
    }

    /** Incluye los inactivos: para administrarlos (activar/desactivar), no para el login. */
    public function todosDeUsuario(int $usuarioId): array
    {
        return $this->fetchAll(
            'SELECT up.*, p.nombre AS pastoral_nombre, c.nombre AS centro_nombre
               FROM usuarios_perfiles up
               JOIN pastorales p ON p.id = up.pastoral_id
               LEFT JOIN centros c ON c.id = up.centro_id
              WHERE up.usuario_id = :id
              ORDER BY up.activo DESC, up.nombre',
            [':id' => $usuarioId]
        );
    }

    /**
     * IDs de pastoral que algún perfil ACTIVO de esta cuenta ya cubre con su
     * propio rol. La herencia ficha→cuenta (PersonaModel::sincronizarCuentaAlcance(),
     * UsuarioController::guardar()) resta este conjunto antes de heredar de la
     * ficha: pertenecer a una pastoral ahí no debe pisar el rol que un perfil
     * adicional ya le dio a propósito (ver el caso de Zulema en MESC).
     */
    public function pastoralesReservadas(int $usuarioId): array
    {
        $filas = $this->fetchAll(
            'SELECT DISTINCT pastoral_id FROM usuarios_perfiles WHERE usuario_id = :id AND activo = 1',
            [':id' => $usuarioId]
        );
        return array_map(static fn (array $f): int => (int) $f['pastoral_id'], $filas);
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO usuarios_perfiles (usuario_id, nombre, rol, pastoral_id, centro_id, activo)
             VALUES (:usuario, :nombre, :rol, :pastoral, :centro, :activo)',
            $this->parametros($datos) + [':usuario' => $datos['usuario_id']]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE usuarios_perfiles
                SET nombre = :nombre, rol = :rol, pastoral_id = :pastoral,
                    centro_id = :centro, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM usuarios_perfiles WHERE id = :id', [':id' => $id]);
    }

    // Sin :usuario aquí: actualizar() no lo usa (un perfil no cambia de
    // dueño), y con PDO::ATTR_EMULATE_PREPARES en false (prepared statements
    // nativos, ver config/database.php) pasar un parámetro que la consulta
    // no referencia es un error fatal, no algo que simplemente se ignore.
    private function parametros(array $datos): array
    {
        return [
            ':nombre'   => $datos['nombre'],
            ':rol'      => $datos['rol'],
            ':pastoral' => $datos['pastoral_id'],
            ':centro'   => $datos['centro_id'],
            ':activo'   => $datos['activo'],
        ];
    }
}
