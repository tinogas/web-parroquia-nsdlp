<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * UsuarioModel — Cuentas del panel y su asignación a pastorales.
 *
 * La tabla usuarios_pastorales solo importa en la práctica para el rol
 * coordinador (Auth::tieneAlcanceGlobal() da por hecho acceso total a admin y
 * editor sin mirarla). Aun así este modelo sincroniza lo que llegue del
 * formulario tal cual, sin condicionarlo al rol: es el controlador quien
 * decide, según el rol enviado, qué conjunto de pastorales tiene sentido
 * guardar. Ver docs/ARQUITECTURA.md, "El alcance por pastoral es ortogonal
 * a la matriz".
 */
class UsuarioModel extends Model
{
    public function todos(): array
    {
        return $this->fetchAll(
            "SELECT u.*,
                    (SELECT GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ')
                       FROM usuarios_pastorales up JOIN pastorales p ON p.id = up.pastoral_id
                      WHERE up.usuario_id = u.id) AS pastorales_nombres,
                    (SELECT GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ')
                       FROM usuarios_centros uc JOIN centros c ON c.id = uc.centro_id
                      WHERE uc.usuario_id = u.id) AS centros_nombres
               FROM usuarios u
              ORDER BY u.nombre"
        );
    }

    /** Cuentas activas y no administradoras, para el selector de "Usar como…". */
    public function paraImpersonar(): array
    {
        return $this->fetchAll(
            "SELECT id, nombre, email, rol FROM usuarios
              WHERE activo = 1 AND rol != :admin
              ORDER BY nombre",
            [':admin' => ROL_ADMIN]
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM usuarios WHERE id = :id', [':id' => $id]);
    }

    /** IDs de pastoral asignadas, para marcar las casillas del formulario. */
    public function pastoralesDe(int $id): array
    {
        $filas = $this->fetchAll(
            'SELECT pastoral_id FROM usuarios_pastorales WHERE usuario_id = :id',
            [':id' => $id]
        );
        return array_map(static fn (array $f): int => (int) $f['pastoral_id'], $filas);
    }

    /** IDs de centro/sede asignados completos (issue #3, "usuarios por centro/sede"). */
    public function centrosDe(int $id): array
    {
        $filas = $this->fetchAll(
            'SELECT centro_id FROM usuarios_centros WHERE usuario_id = :id',
            [':id' => $id]
        );
        return array_map(static fn (array $f): int => (int) $f['centro_id'], $filas);
    }

    public function emailExiste(string $email, ?int $excluirId = null): bool
    {
        if ($excluirId !== null) {
            return (int) $this->fetchColumn(
                'SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id',
                [':email' => $email, ':id' => $excluirId]
            ) > 0;
        }
        return (int) $this->fetchColumn(
            'SELECT COUNT(*) FROM usuarios WHERE email = :email',
            [':email' => $email]
        ) > 0;
    }

    public function crear(array $datos): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono, foto, activo)
                 VALUES (:nombre, :email, :hash, :rol, :telefono, :foto, :activo)',
                [
                    ':nombre'   => $datos['nombre'],
                    ':email'    => $datos['email'],
                    ':hash'     => password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                    ':rol'      => $datos['rol'],
                    ':telefono' => $datos['telefono'],
                    ':foto'     => $datos['foto'],
                    ':activo'   => $datos['activo'],
                ]
            );
            $id = $this->lastInsertId();
            $this->sincronizarPastorales($id, $datos['pastorales']);
            $this->sincronizarCentros($id, $datos['centros']);
            $this->commit();
            return $id;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Si $datos['password'] llega vacío, la contraseña no cambia. */
    public function actualizar(int $id, array $datos): void
    {
        $this->beginTransaction();
        try {
            $sql = $datos['password'] !== ''
                ? 'UPDATE usuarios SET nombre=:nombre, email=:email, password_hash=:hash, rol=:rol,
                       telefono=:telefono, foto=:foto, activo=:activo WHERE id=:id'
                : 'UPDATE usuarios SET nombre=:nombre, email=:email, rol=:rol,
                       telefono=:telefono, foto=:foto, activo=:activo WHERE id=:id';

            $params = [
                ':nombre' => $datos['nombre'], ':email' => $datos['email'], ':rol' => $datos['rol'],
                ':telefono' => $datos['telefono'], ':foto' => $datos['foto'], ':activo' => $datos['activo'],
                ':id' => $id,
            ];
            if ($datos['password'] !== '') {
                $params[':hash'] = password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $this->execute($sql, $params);
            $this->sincronizarPastorales($id, $datos['pastorales']);
            $this->sincronizarCentros($id, $datos['centros']);
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Baja lógica. La contraseña, el rol y las pastorales asignadas se conservan por si se reactiva. */
    public function desactivar(int $id): void
    {
        $this->execute('UPDATE usuarios SET activo = 0 WHERE id = :id', [':id' => $id]);
    }

    private function sincronizarPastorales(int $usuarioId, array $pastoralIds): void
    {
        $this->execute('DELETE FROM usuarios_pastorales WHERE usuario_id = :id', [':id' => $usuarioId]);
        foreach (array_unique($pastoralIds) as $pid) {
            $this->execute(
                'INSERT INTO usuarios_pastorales (usuario_id, pastoral_id) VALUES (:uid, :pid)',
                [':uid' => $usuarioId, ':pid' => $pid]
            );
        }
    }

    private function sincronizarCentros(int $usuarioId, array $centroIds): void
    {
        $this->execute('DELETE FROM usuarios_centros WHERE usuario_id = :id', [':id' => $usuarioId]);
        foreach (array_unique($centroIds) as $cid) {
            $this->execute(
                'INSERT INTO usuarios_centros (usuario_id, centro_id) VALUES (:uid, :cid)',
                [':uid' => $usuarioId, ':cid' => $cid]
            );
        }
    }
}
