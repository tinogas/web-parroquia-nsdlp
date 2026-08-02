<?php
class Auth
{
    public static function intentarLogin(string $email, string $password): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, nombre, email, password_hash, rol, foto, ultimo_acceso
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
        // El acceso ANTERIOR, leído justo antes de pisarlo con NOW() arriba:
        // es lo que deja marcar como «Nuevo» en el panel lo publicado desde la
        // última vez que esta persona entró. Guardarlo en sesión evita una
        // columna más y evita también que la marca se apague sola a mitad de
        // la sesión. Null la primera vez que alguien entra: entonces todo lo
        // que vea es nuevo para esa persona, que es literalmente cierto.
        Session::set('usuario_acceso_anterior', $usuario['ultimo_acceso']);

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
     * IDs de las pastorales que administra el usuario: exactamente las que se
     * le marcaron en su cuenta (usuarios_pastorales), ni una más. Vacío si
     * tiene alcance global.
     */
    public static function pastoralesPermitidas(): array
    {
        return Session::get('usuario_pastorales', []);
    }

    /**
     * Las pastorales cuyo contenido interno puede LEER: las suyas más las
     * Comisiones que las agrupan, porque un aviso publicado en Litúrgica va
     * dirigido también a quien está en Lectores o en Coros.
     *
     * Deliberadamente aparte de pastoralesPermitidas(), no una ampliación
     * suya: aquella gobierna la ESCRITURA (puedeSobrePastoral(),
     * Controller::pastoralIdValidado()) y sigue sin heredar nada, que es la
     * decisión documentada en el comentario de cargarPastorales(). Estar en
     * Lectores te deja leer lo de Litúrgica; no te deja escribir en Litúrgica.
     *
     * Tampoco se cachea en sesión, a diferencia de las pastorales asignadas:
     * ahí el dato solo cambia cuando cambia la cuenta, pero esto depende de
     * pastoral_padre_id, y reasignar el padre de una pastoral no debería
     * esperar a que todo el mundo vuelva a entrar. Se resuelve una vez por
     * petición, como Auth::administraPastoral().
     */
    public static function pastoralesAudiencia(): array
    {
        static $audiencia = null;
        if ($audiencia !== null) {
            return $audiencia;
        }

        $propias = self::pastoralesPermitidas();
        if (!$propias) {
            return $audiencia = [];
        }

        require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
        return $audiencia = (new PastoralModel())->conAncestros($propias);
    }

    /**
     * IDs de las sedes/centros en los que trabaja, o vacío si no se le marcó
     * ninguno, que significa **todos**: así se representa a quien coordina su
     * pastoral en toda la parroquia. Es la otra mitad del alcance, y solo
     * acota: nunca añade pastorales (eso es lo que hacía la herencia por
     * centro que se retiró; ver docs/ARQUITECTURA.md).
     */
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
     * ¿Administra la pastoral de este slug? Es la pregunta de los tres módulos
     * dedicados —PASTORAL_MESC, PASTORAL_CATEQUESIS, PASTORAL_LECTOR—, que
     * trabajan sobre una pastoral fija y no sobre la que se elija en pantalla.
     *
     * Existe porque el permiso ya no distingue: `mesc.*` lo llevan todos los
     * coordinadores desde que se retiraron los roles con la pastoral en el
     * nombre, así que el menú dibujaría los tres módulos a cualquiera. El id se
     * resuelve una vez por petición; no se cachea en sesión a propósito, porque
     * una pastoral renombrada o dada de alta debe surtir efecto sin volver a
     * entrar.
     */
    public static function administraPastoral(string $slug): bool
    {
        if (self::tieneAlcanceGlobal()) {
            return true;
        }

        static $idPorSlug = [];
        if (!array_key_exists($slug, $idPorSlug)) {
            try {
                $stmt = Database::getInstance()->prepare('SELECT id FROM pastorales WHERE slug = :slug');
                $stmt->execute([':slug' => $slug]);
                $id = $stmt->fetchColumn();
                $idPorSlug[$slug] = $id !== false ? (int) $id : null;
            } catch (PDOException $e) {
                $idPorSlug[$slug] = null;
            }
        }

        return $idPorSlug[$slug] !== null && self::puedeSobrePastoral($idPorSlug[$slug]);
    }

    /**
     * ¿Puede tocar un registro de esta sede?
     *
     * Sin sedes marcadas trabaja en todas, así que esto no le quita nada: es
     * el caso de una coordinación general. Con sedes marcadas queda acotado a
     * ellas, y el contenido sin sede —de toda la parroquia— le queda fuera,
     * por lo mismo que un coordinador no toca el contenido sin pastoral.
     *
     * Se comprueba SIEMPRE junto a puedeSobrePastoral(), nunca sola: la
     * pastoral dice qué equipo organiza algo y la sede dónde, y hacen falta
     * las dos para que sea suyo. Ver Controller::requireAlcanceContenido().
     */
    public static function puedeSobreCentro(?int $centroId): bool
    {
        if (self::tieneAlcanceGlobal()) {
            return true;
        }
        $propios = self::centrosPermitidos();
        if (!$propios) {
            return true;
        }
        if ($centroId === null) {
            return false;
        }
        return in_array($centroId, $propios, true);
    }

    /**
     * Una sola fuente: las pastorales marcadas en la cuenta.
     *
     * Hasta la revisión de alcance esto hacía la UNIÓN con las pastorales de
     * cualquier centro/sede asignado (issue #3, "quien administra un centro
     * administra todas sus pastorales"). En la práctica esa herencia repartía
     * alcance que nadie había pedido: a la administradora de MESC, marcada en
     * los tres centros porque MESC opera en los tres, el centro le entregaba
     * también Catecismo, AMA, Raíces y JECSA, que son las otras pastorales
     * ligadas a esa misma sede. Quien deba administrar una sede completa lleva
     * marcadas sus pastorales, que además es lo que se ve en pantalla.
     */
    private static function cargarPastorales(int $usuarioId): array
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT pastoral_id FROM usuarios_pastorales WHERE usuario_id = :id'
            );
            $stmt->execute([':id' => $usuarioId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            // Las tablas aún no existen (etapas previas a la 6). No es un error.
            return [];
        }
    }

    /** Las sedes en las que trabaja. Vacío = todas; ver centrosPermitidos(). */
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
        // Sin marcas de «Nuevo» mientras se usa otra cuenta: la del
        // administrador no dice nada de lo que esa persona ha visto, y la de
        // ella tampoco es asunto de quien la está suplantando.
        Session::set('usuario_acceso_anterior', null);
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
        Session::set('usuario_acceso_anterior', null);

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
