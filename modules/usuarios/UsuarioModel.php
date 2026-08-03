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
    /**
     * Listado del panel de usuarios. `$pastorales` es el alcance de quien
     * mira, igual que en el resto del sistema: null = alcance global (ve
     * todas las cuentas), o la lista de pastorales que administra.
     *
     * Con alcance acotado se aplican dos filtros a la vez, no uno: además de
     * pastoral compartida, el rango de la cuenta objetivo tiene que ser
     * Coordinador o Consulta — un Coordinador general nunca ve en esta lista
     * a otro Coordinador general, a Secretaría, a Editor ni a Administrador,
     * aunque coincida la pastoral. Es la misma regla que aplica
     * UsuarioController::dentroDeMiAlcance() cuando se llega por id directo;
     * aquí se hace por si sola para no traer de la base cuentas que ese
     * acceso directo rechazaría de todos modos.
     */
    public function todos(?array $pastorales = null): array
    {
        $where  = '';
        $params = [];

        if ($pastorales !== null) {
            if (!$pastorales) {
                $where = 'WHERE 1 = 0';
            } else {
                $marcadores = [];
                foreach (array_values($pastorales) as $i => $pid) {
                    $clave          = ":pas{$i}";
                    $marcadores[]   = $clave;
                    $params[$clave] = (int) $pid;
                }
                $params[':rolCoord']    = ROL_COORDINADOR;
                $params[':rolConsulta'] = ROL_CONSULTA;
                $where = 'WHERE u.rol IN (:rolCoord, :rolConsulta)
                            AND EXISTS (
                                  SELECT 1 FROM usuarios_pastorales up2
                                   WHERE up2.usuario_id = u.id
                                     AND up2.pastoral_id IN (' . implode(',', $marcadores) . ')
                                )';
            }
        }

        return $this->fetchAll(
            "SELECT u.*,
                    pe.cargo,
                    (SELECT GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ')
                       FROM usuarios_pastorales up JOIN pastorales p ON p.id = up.pastoral_id
                      WHERE up.usuario_id = u.id) AS pastorales_nombres,
                    (SELECT GROUP_CONCAT(up.pastoral_id)
                       FROM usuarios_pastorales up WHERE up.usuario_id = u.id) AS pastorales_ids,
                    (SELECT GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ')
                       FROM usuarios_centros uc JOIN centros c ON c.id = uc.centro_id
                      WHERE uc.usuario_id = u.id) AS centros_nombres
               FROM usuarios u
               LEFT JOIN personas pe ON pe.id = u.persona_id
               {$where}
              ORDER BY u.nombre",
            $params
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

    /** La cuenta de esa ficha del equipo pastoral, si ya tiene una. */
    public function porPersona(int $personaId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM usuarios WHERE persona_id = :id',
            [':id' => $personaId]
        );
    }

    /**
     * Las fichas del equipo pastoral a las que se les puede dar cuenta: las
     * activas que todavía no tienen una, más la de la cuenta que se esté
     * editando —si no, al guardar cualquier otro campo se perdería el vínculo
     * porque su propia persona no estaría en la lista—.
     */
    public function personasSinCuenta(?int $exceptoUsuarioId = null): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.nombre, p.cargo
               FROM personas p
               LEFT JOIN usuarios u ON u.persona_id = p.id
              WHERE p.activo = 1
                AND (u.id IS NULL OR u.id = :usuario)
              ORDER BY p.nombre',
            [':usuario' => $exceptoUsuarioId ?? 0]
        );
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

    /** IDs de las sedes en las que trabaja. Vacío = todas. */
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
                'INSERT INTO usuarios (persona_id, nombre, email, password_hash, rol, telefono, foto, activo)
                 VALUES (:persona, :nombre, :email, :hash, :rol, :telefono, :foto, :activo)',
                [
                    ':persona'  => $datos['persona_id'],
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
            $this->sincronizarPastoralResponsable($datos['persona_id'], $datos['email']);
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
                ? 'UPDATE usuarios SET persona_id=:persona, nombre=:nombre, email=:email,
                       password_hash=:hash, rol=:rol,
                       telefono=:telefono, foto=:foto, activo=:activo WHERE id=:id'
                : 'UPDATE usuarios SET persona_id=:persona, nombre=:nombre, email=:email, rol=:rol,
                       telefono=:telefono, foto=:foto, activo=:activo WHERE id=:id';

            $params = [
                ':persona' => $datos['persona_id'],
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
            $this->sincronizarPastoralResponsable($datos['persona_id'], $datos['email']);
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

    /**
     * Sincroniza SOLO el alcance (pastorales y sedes) de una cuenta ya
     * existente, sin tocar nombre, rol, correo, contraseña, foto ni activo.
     * Para cuando el cambio se origina fuera del formulario de Usuarios: hoy
     * lo usa únicamente PersonaModel::actualizar(), al guardar la ficha de
     * alguien que ya tiene cuenta vinculada, para que el alcance de la cuenta
     * nunca quede desincronizado de lo que la ficha dice. Ver
     * docs/ARQUITECTURA.md.
     */
    public function sincronizarAlcance(int $usuarioId, array $pastoralIds, array $centroIds): void
    {
        $this->beginTransaction();
        try {
            $this->sincronizarPastorales($usuarioId, $pastoralIds);
            $this->sincronizarCentros($usuarioId, $centroIds);
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Qué administra. Estas filas son el alcance, sin herencias de por medio. */
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

    /**
     * Si esta cuenta es la de la persona responsable de alguna pastoral, su
     * correo de contacto público se toma de aquí —el correo de acceso, «el
     * del rol»— para que no queden dos direcciones distintas de la misma
     * cuenta. Sin esto, `pastorales.contacto_email` es un campo libre que
     * diverge en cuanto alguien cambia su correo de acceso sin acordarse de
     * ir a editar también la ficha de su pastoral (fue justo lo que le pasó
     * a la de MESC: contacto_email tenía un correo con una letra distinta al
     * de la cuenta real de la coordinadora). Ver
     * PastoralController::guardar(), que hace el mismo cálculo al elegir
     * responsable, para que no haya que esperar a que la cuenta se vuelva a
     * guardar.
     */
    private function sincronizarPastoralResponsable(?int $personaId, string $email): void
    {
        if ($personaId === null) {
            return;
        }
        $this->execute(
            'UPDATE pastorales SET contacto_email = :email WHERE responsable_persona_id = :persona',
            [':email' => $email, ':persona' => $personaId]
        );
    }

    /**
     * Dónde lo administra. Ninguna fila aquí significa «en toda la parroquia»,
     * no «en ninguna parte»: es lo contrario que las pastorales, y por eso son
     * dos tablas y no una columna más.
     *
     * Hasta la revisión de alcance esta tabla hacía algo muy distinto —quien
     * administraba un centro heredaba todas sus pastorales—; ver
     * docs/ARQUITECTURA.md.
     */
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
