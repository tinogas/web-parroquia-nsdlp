<?php
class Auth
{
    public static function intentarLogin(string $email, string $password): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, nombre, email, password_hash, rol, foto
             FROM usuarios
             WHERE email = :email AND activo = 1
             LIMIT 1'
        );
        $stmt->execute([':email' => trim($email)]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            return false;
        }

        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id')
           ->execute([':id' => $usuario['id']]);

        Session::regenerar();
        Session::set('usuario_id',     $usuario['id']);
        Session::set('usuario_nombre', $usuario['nombre']);
        Session::set('usuario_email',  $usuario['email']);
        Session::set('usuario_rol',    $usuario['rol']);
        Session::set('usuario_foto',   $usuario['foto'] ?? null);

        // Las pastorales asignadas se cachean aquí para no consultarlas en cada
        // listado del panel. La tabla usuarios_pastorales llega en la etapa 6;
        // hasta entonces el arreglo queda vacío y solo los roles con alcance
        // global pueden escribir contenido.
        Session::set('usuario_pastorales', self::cargarPastorales((int) $usuario['id']));
        Session::set('usuario_centros',    self::cargarCentros((int) $usuario['id']));

        return true;
    }

    public static function logout(): void
    {
        Session::destruir();
    }

    public static function estaAutenticado(): bool
    {
        return Session::has('usuario_id');
    }

    public static function usuario(): array
    {
        return [
            'id'         => Session::get('usuario_id'),
            'nombre'     => Session::get('usuario_nombre'),
            'email'      => Session::get('usuario_email'),
            'rol'        => Session::get('usuario_rol'),
            'foto'       => Session::get('usuario_foto'),
            'pastorales' => Session::get('usuario_pastorales', []),
            'centros'    => Session::get('usuario_centros', []),
        ];
    }

    public static function tienePermiso(string $permiso): bool
    {
        $permisos = PERMISOS[Session::get('usuario_rol')] ?? [];
        return in_array('*', $permisos, true) || in_array($permiso, $permisos, true);
    }

    public static function esAdmin(): bool
    {
        return Session::get('usuario_rol') === ROL_ADMIN;
    }

    public static function nombreRol(): string
    {
        return ROLES_NOMBRES[Session::get('usuario_rol')] ?? '';
    }

    // ── Alcance por pastoral ────────────────────────────────────────────
    //
    // La matriz PERMISOS responde "¿qué acción?". Esto responde "¿sobre qué
    // registro?". Se mantienen separados a propósito: mezclarlos obligaría a
    // una entrada por pastoral en la matriz. Ver docs/ARQUITECTURA.md

    /**
     * IDs de las pastorales que administra el usuario, ya sea porque se le
     * asignó directo (usuarios_pastorales) o porque administra el centro/sede
     * completo (usuarios_centros) al que esa pastoral está ligada — issue #3,
     * "usuarios por centro/sede". Vacío si tiene alcance global.
     */
    public static function pastoralesPermitidas(): array
    {
        return Session::get('usuario_pastorales', []);
    }

    /** IDs de los centros/sedes que el usuario administra completos. Vacío si no tiene ninguno. */
    public static function centrosPermitidos(): array
    {
        return Session::get('usuario_centros', []);
    }

    /** El administrador y el editor pueden con todo el contenido del sitio. */
    public static function tieneAlcanceGlobal(): bool
    {
        $rol = Session::get('usuario_rol');
        return $rol === ROL_ADMIN || $rol === ROL_EDITOR;
    }

    /**
     * ¿Puede tocar un registro de esta pastoral?
     *
     * Un pastoral_id nulo significa contenido parroquial global: solo lo
     * manejan los roles con alcance global, nunca un coordinador.
     */
    public static function puedeSobrePastoral(?int $pastoralId): bool
    {
        if (self::tieneAlcanceGlobal()) {
            return true;
        }
        if ($pastoralId === null) {
            return false;
        }
        return in_array($pastoralId, self::pastoralesPermitidas(), true);
    }

    /**
     * Unión de dos fuentes: pastorales asignadas directo, más las de
     * cualquier centro/sede que el usuario administre completo (issue #3).
     * PDO con ATTR_EMULATE_PREPARES=false no admite repetir :id, de ahí :id2.
     */
    private static function cargarPastorales(int $usuarioId): array
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT pastoral_id FROM usuarios_pastorales WHERE usuario_id = :id
                 UNION
                 SELECT p.id FROM pastorales p
                   INNER JOIN usuarios_centros uc ON uc.centro_id = p.centro_id
                  WHERE uc.usuario_id = :id2'
            );
            $stmt->execute([':id' => $usuarioId, ':id2' => $usuarioId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            // Las tablas aún no existen (etapas previas a la 6). No es un error.
            return [];
        }
    }

    private static function cargarCentros(int $usuarioId): array
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT centro_id FROM usuarios_centros WHERE usuario_id = :id'
            );
            $stmt->execute([':id' => $usuarioId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Impersonación ("Usar como…") ────────────────────────────────────
    //
    // Permite al administrador operar temporalmente con la sesión de otro
    // usuario, sin conocer su contraseña: para ver el panel exactamente como
    // lo ve un coordinador o secretaría, o para resolver una duda de soporte
    // sin pedir la contraseña de nadie. No se anida —el controlador rechaza
    // impersonar si ya se está impersonando a alguien— y nunca se puede
    // impersonar a otro administrador. Ver docs/ARQUITECTURA.md.

    public static function estaImpersonando(): bool
    {
        return (bool) Session::get('_impersonando', false);
    }

    /**
     * Cambia la sesión a la identidad de $objetivo, conservando la del
     * administrador real en claves aparte para poder volver.
     */
    public static function iniciarImpersonacion(array $objetivo): void
    {
        $real = self::usuario();

        Session::set('_impersonando',      true);
        Session::set('_admin_real_id',     $real['id']);
        Session::set('_admin_real_nombre', $real['nombre']);
        Session::set('_admin_real_email',  $real['email']);

        Session::set('usuario_id',         $objetivo['id']);
        Session::set('usuario_nombre',     $objetivo['nombre']);
        Session::set('usuario_email',      $objetivo['email']);
        Session::set('usuario_rol',        $objetivo['rol']);
        Session::set('usuario_foto',       $objetivo['foto'] ?? null);
        Session::set('usuario_pastorales', self::cargarPastorales((int) $objetivo['id']));
        Session::set('usuario_centros',    self::cargarCentros((int) $objetivo['id']));
    }

    /** Restaura la sesión del administrador real y borra el rastro de la impersonación. */
    public static function terminarImpersonacion(): void
    {
        Session::set('usuario_id',         Session::get('_admin_real_id'));
        Session::set('usuario_nombre',     Session::get('_admin_real_nombre'));
        Session::set('usuario_email',      Session::get('_admin_real_email'));
        Session::set('usuario_rol',        ROL_ADMIN);
        Session::set('usuario_foto',       null);
        Session::set('usuario_pastorales', []);
        Session::set('usuario_centros',    []);

        Session::delete('_impersonando');
        Session::delete('_admin_real_id');
        Session::delete('_admin_real_nombre');
        Session::delete('_admin_real_email');
    }

    /** El administrador real detrás de la impersonación activa, o null si no aplica. */
    public static function adminReal(): ?array
    {
        if (!self::estaImpersonando()) {
            return null;
        }
        return [
            'id'     => Session::get('_admin_real_id'),
            'nombre' => Session::get('_admin_real_nombre'),
            'email'  => Session::get('_admin_real_email'),
        ];
    }
}
