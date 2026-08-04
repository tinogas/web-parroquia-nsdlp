<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/Upload.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioPerfilModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class UsuarioController extends Controller
{
    private UsuarioModel $modelo;

    public function __construct()
    {
        $this->modelo = new UsuarioModel();
    }

    public function index(): void
    {
        $this->requirePermiso('usuarios.ver');

        $usuarios      = $this->modelo->todos(Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas());
        $pastoralModel = new PastoralModel();
        // Padre arriba, hijas debajo, igual que en el formulario: se agrupa
        // aquí (no en la vista) porque cruza contra el catálogo completo de
        // pastorales, no contra algo que ya venga en $usuarios.
        foreach ($usuarios as &$usuario) {
            $ids = $usuario['pastorales_ids'] !== null
                ? array_map('intval', explode(',', $usuario['pastorales_ids']))
                : [];
            $usuario['pastorales_agrupadas'] = $pastoralModel->agruparIds($ids);
        }
        unset($usuario);

        $this->render('usuarios/lista', [
            'titulo'          => 'Usuarios',
            'usuarios'        => $usuarios,
            'alcanceLimitado' => !Auth::tieneAlcanceGlobal(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('usuarios.editar');

        $this->render('usuarios/form', [
            'titulo'           => 'Nuevo usuario',
            'cuenta'           => null,
            'personas'         => $this->modelo->personasSinCuenta(),
            'fichaCuenta'      => null,
            // Ofrecer una pastoral o sede que quien crea la cuenta no
            // administra sería prometer un alcance que luego el guardado
            // rechaza; los mismos helpers que acotan el selector de eventos y
            // cursos sirven aquí tal cual.
            'pastorales'       => $this->pastoralesDelFiltro(true),
            // Mismas pastorales que arriba (sin Comisiones, acotadas al
            // alcance de quien crea la cuenta), pero agrupadas por Comisión
            // padre para el checklist: ver nota en views/form.php.
            'pastoralesAgrupadas' => (new PastoralModel())->agrupadoVisible(
                Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas()
            ),
            'pastoralesTodas'  => (new PastoralModel())->paraSelector(),
            'asignadas'        => [],
            'pastoralesFichaAgrupadas' => ['comisiones' => [], 'sueltas' => []],
            'centros'          => $this->centrosDelFiltro(),
            'centrosTodos'     => (new CentroModel())->activos(),
            'centrosAsignados' => [],
            'rolesDisponibles' => $this->rolesAsignables(),
            'perfiles'         => [],
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('usuarios.editar');

        $cuenta = $this->modelo->porId($this->getInt('id'));
        if (!$cuenta) {
            Session::flash('error', 'No encontramos a ese usuario.');
            $this->redirect(url_admin('usuarios'));
            return;
        }
        $this->requireAlcanceCuenta($cuenta);

        // Con persona vinculada, el resumen de solo lectura del formulario
        // muestra lo que dice SU FICHA (fuente de verdad nueva), no lo que ya
        // tenía guardado la cuenta (usuarios_pastorales/usuarios_centros):
        // son justo las dos fuentes que hoy pueden desincronizarse en
        // silencio, y esta pantalla debe reflejar siempre la primera.
        $fichaCuenta = $cuenta['persona_id'] !== null
            ? (new PersonaModel())->porId((int) $cuenta['persona_id'])
            : null;
        $asignadas = $fichaCuenta
            ? (new PersonaModel())->pastoralesDe((int) $fichaCuenta['id'])
            : $this->modelo->pastoralesDe((int) $cuenta['id']);
        $perfiles  = (new UsuarioPerfilModel())->todosDeUsuario((int) $cuenta['id']);

        $this->render('usuarios/form', [
            'titulo'           => $cuenta['nombre'],
            // OJO: 'usuario' es una clave reservada de Controller::render() —
            // guarda ahí SIEMPRE al administrador con sesión activa (la usa el
            // navbar). Usarla para la cuenta que se está editando la pisa en
            // silencio con Auth::usuario(), y el formulario termina mostrando
            // los datos de quien tiene la sesión abierta en vez de los de la
            // cuenta editada. De ahí el nombre 'cuenta' en vez de 'usuario'.
            'cuenta'           => $cuenta,
            // La propia persona de esta cuenta sigue en la lista; las de las
            // demás cuentas no, para no poder asignar a alguien dos veces.
            'personas'         => $this->modelo->personasSinCuenta((int) $cuenta['id']),
            'fichaCuenta'      => $fichaCuenta,
            'pastorales'       => $this->pastoralesDelFiltro(true),
            // Mismas pastorales que arriba (sin Comisiones, acotadas al
            // alcance de quien edita), pero agrupadas por Comisión padre para
            // el checklist: ver nota en views/form.php.
            'pastoralesAgrupadas' => (new PastoralModel())->agrupadoVisible(
                Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas()
            ),
            // Catálogo sin acotar por alcance ni por "sin Comisiones": para
            // resolver el NOMBRE de lo que diga la ficha aunque incluya una
            // Comisión, o una pastoral fuera de lo que administra quien edita
            // (ver nota del resumen de solo lectura en views/form.php).
            'pastoralesTodas'  => (new PastoralModel())->paraSelector(),
            'asignadas'        => $asignadas,
            // Las pastorales de la ficha (o de la cuenta, sin vínculo), pero
            // agrupadas por Comisión padre para el resumen de solo lectura, y
            // cada hija anotada con el nivel real de acceso que tiene esta
            // cuenta ahí —control total (rol principal), un perfil adicional
            // con su propio rol, o ninguno—: ver pastoralesFichaConNivel().
            'pastoralesFichaAgrupadas' => $this->pastoralesFichaConNivel($cuenta, $asignadas, $perfiles),
            'centros'          => $this->centrosDelFiltro(),
            'centrosTodos'     => (new CentroModel())->activos(),
            'centrosAsignados' => $fichaCuenta
                ? (new PersonaModel())->centrosDe((int) $fichaCuenta['id'])
                : $this->modelo->centrosDe((int) $cuenta['id']),
            'rolesDisponibles' => $this->rolesAsignables(),
            'perfiles'         => $perfiles,
        ]);
    }

    /**
     * agruparIds() ya junta cada Comisión con sus hijas marcadas en la
     * ficha (ver docs/ARQUITECTURA.md, "Perfiles adicionales"); esto además
     * anota cada una con el nivel REAL de acceso que la cuenta tiene ahí,
     * cruzando las 3 fuentes que hoy pueden parecer independientes:
     *
     * - 'principal': la pastoral está en usuarios_pastorales del rol
     *   principal —control total, con los permisos de ese rol—.
     * - 'perfil': ningún control desde el rol principal, pero un perfil
     *   adicional activo la cubre —el nivel es el de ESE perfil, no el de
     *   la cuenta—.
     * - 'sin_acceso': pertenece en la ficha, pero ni el rol principal ni
     *   ningún perfil la administra —normal para las Comisiones en sí, que
     *   no tienen contenido propio; llamativo si aparece en una hija—.
     *
     * Sin esto, entender "qué puede hacer esta cuenta en cada pastoral"
     * exigía reconstruir mentalmente la relación entre la ficha, el rol
     * principal y los perfiles por separado.
     */
    private function pastoralesFichaConNivel(array $cuenta, array $asignadas, array $perfiles): array
    {
        $agrupado = (new PastoralModel())->agruparIds($asignadas);
        if (!$agrupado['comisiones'] && !$agrupado['sueltas']) {
            return $agrupado;
        }

        $pastoralesRolPrincipal = $this->modelo->pastoralesDe((int) $cuenta['id']);
        $perfilesActivos        = array_values(array_filter($perfiles, static fn (array $p): bool => (bool) $p['activo']));
        $nombreRolPrincipal     = ROLES_NOMBRES[$cuenta['rol']] ?? $cuenta['rol'];

        $anotar = static function (array $pastoral) use ($pastoralesRolPrincipal, $perfilesActivos, $nombreRolPrincipal): array {
            $id = (int) $pastoral['id'];
            if (in_array($id, $pastoralesRolPrincipal, true)) {
                $pastoral['nivel']          = 'principal';
                $pastoral['nivel_etiqueta'] = $nombreRolPrincipal;
                return $pastoral;
            }
            foreach ($perfilesActivos as $perfil) {
                if ((int) $perfil['pastoral_id'] === $id) {
                    $pastoral['nivel']          = 'perfil';
                    $pastoral['nivel_etiqueta'] = (ROLES_NOMBRES[$perfil['rol']] ?? $perfil['rol']) . ' · ' . $perfil['nombre'];
                    return $pastoral;
                }
            }
            $pastoral['nivel']          = 'sin_acceso';
            $pastoral['nivel_etiqueta'] = 'Sin acceso';
            return $pastoral;
        };

        foreach ($agrupado['comisiones'] as &$grupo) {
            $grupo['hijas'] = array_map($anotar, $grupo['hijas']);
        }
        unset($grupo);
        $agrupado['sueltas'] = array_map($anotar, $agrupado['sueltas']);

        return $agrupado;
    }

    public function guardar(): void
    {
        $this->requirePermiso('usuarios.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('usuarios'));
            return;
        }
        $this->validarCsrf();

        $id       = $this->postInt('id');
        $actual   = $id ? $this->modelo->porId($id) : null;
        if ($actual) {
            $this->requireAlcanceCuenta($actual);
        }
        $esPropio = $actual !== null && (int) $actual['id'] === (int) Auth::usuario()['id'];

        $nombre     = $this->postStr('nombre');
        $email      = strtolower($this->postStr('email'));
        $rol        = $this->postStr('rol');
        $password   = (string) ($_POST['password'] ?? '');
        $rolConAlcance = in_array($rol, ROLES_CON_ALCANCE_PASTORAL, true);
        $pastorales = $rolConAlcance
            ? array_values(array_unique(array_map('intval', array_filter((array) ($_POST['pastorales'] ?? []), 'is_numeric'))))
            : [];
        // Sin sedes marcadas trabaja en todas: para un coordinador general es
        // legítimo, y para uno de sede es justo el descuido que hay que evitar.
        $centros = $rolConAlcance
            ? array_values(array_unique(array_map('intval', array_filter((array) ($_POST['centros'] ?? []), 'is_numeric'))))
            : [];

        // De quién es la cuenta. La ficha del equipo pastoral manda sobre la
        // identidad —nombre, teléfono y foto—; si no se marcó alcance, también
        // presta el suyo, que es lo que evita crear cuentas sin nada asignado.
        $persona = $this->personaDelPost($actual);
        if ($persona) {
            $nombre = $persona['nombre'];
            // Con persona vinculada, pastoral y sede ya no se marcan a mano
            // aquí: se heredan siempre de su ficha si el rol elegido usa ese
            // alcance —sin `?:` de por medio—; ese operador solo caía al
            // fallback si el checklist llegaba COMPLETAMENTE vacío, así que un
            // subconjunto marcado a mano podía pisar en silencio lo que decía
            // la ficha (así perdió Lectores la cuenta de Martha Aimée). Con un
            // rol sin alcance (Administrador, Editor, Secretaría) se deja
            // en [] igual que antes: esa cuenta ya tiene acceso global o
            // ninguno, y no necesita arrastrar filas de alcance sin uso solo
            // porque su ficha las tenga. El formulario ya no ofrece marcarlo
            // a mano cuando hay persona vinculada; esto además ignora
            // cualquier POST manipulado.
            if ($rolConAlcance) {
                $pastorales = (new PersonaModel())->pastoralesDe((int) $persona['id']);
                $centros    = (new PersonaModel())->centrosDe((int) $persona['id']);
                if ($actual) {
                    // Una pastoral cubierta por un perfil adicional ya tiene
                    // su propio rol (ver docs/ARQUITECTURA.md): pertenecer a
                    // ella en la ficha no debe pisar ese alcance con el rol
                    // principal de esta cuenta.
                    $reservadas = (new UsuarioPerfilModel())->pastoralesReservadas((int) $actual['id']);
                    $pastorales = array_values(array_diff($pastorales, $reservadas));
                }
            }
            $email = $email ?: strtolower((string) $persona['email']);
        }

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Escribe el nombre completo, o elige a alguien del equipo pastoral.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Escribe un correo electrónico válido.';
        } elseif ($this->modelo->emailExiste($email, $actual ? $id : null)) {
            $errores[] = 'Ese correo ya está registrado por otro usuario.';
        }
        if (!array_key_exists($rol, $this->rolesAsignables())) {
            $errores[] = 'Elige un rol válido.';
        }
        if ($rolConAlcance && !$pastorales) {
            $errores[] = 'Este rol debe tener asignada al menos una pastoral.';
        }
        // Un Coordinador general solo llega hasta aquí para dar de alta o
        // editar cuentas de su propia pastoral (ver dentroDeMiAlcance()); esto
        // es lo mismo aplicado a lo que intenta ASIGNAR, no a lo que ya existe
        // — sin esto podría, con un formulario manipulado, marcarle a una
        // cuenta nueva una pastoral o una sede que él mismo no administra.
        //
        // Con persona vinculada no aplica: el valor ya no se asigna en este
        // formulario, se hereda de la ficha (que pudo editar alguien con más
        // alcance, como un Administrador); validarlo aquí bloquearía guardar
        // cualquier otro cambio de esa cuenta —rol, contraseña— por un dato
        // que ni siquiera se ofrece marcar en pantalla.
        if (!$persona && !Auth::tieneAlcanceGlobal()) {
            if (array_diff($pastorales, Auth::pastoralesPermitidas())) {
                $errores[] = 'Solo puedes asignar pastorales que tú mismo administras.';
            }
            $misCentros = Auth::centrosPermitidos();
            if ($misCentros && array_diff($centros, $misCentros)) {
                $errores[] = 'Solo puedes asignar sedes en las que tú mismo trabajas.';
            }
        }
        // Un coordinador de sede sin sede mandaría en todas, que es el rol de al
        // lado. Si de verdad coordina la pastoral entera, ese es su rol.
        if ($rol === ROL_COORDINADOR && count($centros) !== 1) {
            $errores[] = 'Un coordinador administra una sola sede: marca exactamente una, '
                       . 'o usa el rol de coordinador general.';
        }
        if (!$actual && $password === '') {
            $errores[] = 'La contraseña es obligatoria para un usuario nuevo.';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        // Nadie puede desactivarse a sí mismo desde el formulario: a la baja
        // solo se llega por la acción eliminar(), que además bloquea el propio id.
        $activo = $esPropio ? 1 : $this->postBool('activo');

        if ($errores) {
            Session::flash('error', implode(' ', $errores));
            $this->redirect($id ? url_admin('usuarios', 'editar', ['id' => $id]) : url_admin('usuarios', 'nuevo'));
            return;
        }

        $foto     = $actual['foto'] ?? null;
        $telefono = $this->postStr('telefono') ?: null;

        if ($persona) {
            // Foto y teléfono se copian de la ficha en cada guardado, y también
            // cuando se guarda la ficha (PersonaController), para que no haya
            // dos versiones. No se borra del disco la que tuviera la cuenta: el
            // archivo puede ser el mismo que usa la ficha, y perder una foto por
            // ahorrar unos kilobytes es mal negocio.
            $foto     = $persona['foto'];
            $telefono = $persona['telefono'];
        } else {
            try {
                if (!empty($_POST['foto_quitar'])) {
                    Upload::borrar($foto);
                    $foto = null;
                } else {
                    $foto = Upload::imagen('foto', 'usuarios', 'usuario', $foto);
                }
            } catch (RuntimeException $e) {
                Session::flash('warning', 'Se guardó, pero la foto no: ' . $e->getMessage());
            }
        }

        $datos = [
            'persona_id' => $persona ? (int) $persona['id'] : null,
            'nombre'     => $nombre,
            'email'      => $email,
            'password'   => $password,
            'rol'        => $rol,
            'telefono'   => $telefono,
            'foto'       => $foto,
            'activo'     => $activo,
            'pastorales' => $pastorales,
            'centros'    => $centros,
        ];

        if ($actual) {
            $this->modelo->actualizar($id, $datos);
            if ($esPropio) {
                Session::set('usuario_foto', $foto);
            }
            $this->auditoria('editar', 'usuarios', $id, "Usuario: {$email}, rol: {$rol}");
            if (!Session::hayFlash()) {
                Session::flash('success', 'Usuario actualizado.');
            }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'usuarios', $id, "Usuario: {$email}, rol: {$rol}");
            Session::flash('success', 'Usuario creado.');
        }

        $this->redirect(url_admin('usuarios'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('usuarios.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('usuarios'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        if ($id === (int) Auth::usuario()['id']) {
            Session::flash('error', 'No puedes darte de baja a ti mismo.');
            $this->redirect(url_admin('usuarios'));
            return;
        }

        $usuario = $this->modelo->porId($id);
        if ($usuario) {
            $this->requireAlcanceCuenta($usuario);
            $this->modelo->desactivar($id);
            $this->auditoria('eliminar', 'usuarios', $id, "Usuario: {$usuario['email']}");
            Session::flash('success', 'Usuario dado de baja.');
        }

        $this->redirect(url_admin('usuarios'));
    }

    // ── Perfiles adicionales de acceso ───────────────────────────────────
    //
    // Restringidos a quien tiene alcance global (Administrador/Editor), no a
    // cualquiera con usuarios.editar (que también alcanza a Coordinador
    // general): casi siempre cruzan a una pastoral fuera del alcance de quien
    // edita. Ver docs/ARQUITECTURA.md, "Perfiles adicionales".

    public function perfilGuardar(): void
    {
        $this->requirePermiso('usuarios.editar');
        if (!Auth::tieneAlcanceGlobal()) {
            Session::flash('error', 'Solo Administrador o Editor pueden gestionar perfiles adicionales.');
            $this->redirect(url_admin('usuarios'));
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('usuarios'));
            return;
        }
        $this->validarCsrf();

        $usuarioId = $this->postInt('usuario_id');
        $cuenta    = $this->modelo->porId($usuarioId);
        if (!$cuenta) {
            Session::flash('error', 'No encontramos esa cuenta.');
            $this->redirect(url_admin('usuarios'));
            return;
        }

        $nombre     = $this->postStr('nombre');
        $rol        = $this->postStr('rol');
        $pastoralId = $this->postInt('pastoral_id');
        $centroId   = $this->postIntONull('centro_id');

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Escribe un nombre para el perfil.';
        }
        if (!in_array($rol, ROLES_CON_ALCANCE_PASTORAL, true)) {
            $errores[] = 'Elige un rol acotado por pastoral.';
        }
        if (!$pastoralId) {
            $errores[] = 'Elige una pastoral.';
        }
        if ($errores) {
            Session::flash('error', implode(' ', $errores));
            $this->redirect(url_admin('usuarios', 'editar', ['id' => $usuarioId]));
            return;
        }

        $datos = [
            'usuario_id'  => $usuarioId,
            'nombre'      => $nombre,
            'rol'         => $rol,
            'pastoral_id' => $pastoralId,
            'centro_id'   => $centroId,
            'activo'      => $this->postBool('activo'),
        ];

        $modeloPerfil = new UsuarioPerfilModel();
        $id           = $this->postInt('id');
        if ($id) {
            $modeloPerfil->actualizar($id, $datos);
            $this->auditoria('editar', 'usuarios_perfiles', $id, "Perfil: {$nombre}, cuenta: {$cuenta['email']}");
            Session::flash('success', 'Perfil actualizado.');
        } else {
            $id = $modeloPerfil->crear($datos);
            $this->auditoria('crear', 'usuarios_perfiles', $id, "Perfil: {$nombre}, cuenta: {$cuenta['email']}");
            Session::flash('success', 'Perfil creado.');
        }

        // Un perfil nuevo reserva su pastoral y se la resta al rol principal;
        // sin esto, el rol principal solo se habría actualizado si alguien
        // volvía a guardar la ficha o la cuenta por separado.
        if ($cuenta['persona_id'] !== null) {
            (new PersonaModel())->resincronizarCuenta((int) $cuenta['persona_id']);
        }

        $this->redirect(url_admin('usuarios', 'editar', ['id' => $usuarioId]));
    }

    public function perfilEliminar(): void
    {
        $this->requirePermiso('usuarios.editar');
        if (!Auth::tieneAlcanceGlobal()) {
            Session::flash('error', 'Solo Administrador o Editor pueden gestionar perfiles adicionales.');
            $this->redirect(url_admin('usuarios'));
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('usuarios'));
            return;
        }
        $this->validarCsrf();

        $id           = $this->postInt('id');
        $modeloPerfil = new UsuarioPerfilModel();
        $perfil       = $modeloPerfil->porId($id);
        if (!$perfil) {
            $this->redirect(url_admin('usuarios'));
            return;
        }

        $modeloPerfil->eliminar($id);
        $this->auditoria('eliminar', 'usuarios_perfiles', $id);
        Session::flash('success', 'Perfil eliminado.');

        // La pastoral que este perfil reservaba queda libre: si el rol
        // principal la tiene en su ficha, debe recuperarla de inmediato, sin
        // esperar a que alguien vuelva a guardar la ficha o la cuenta aparte
        // (bug reportado: "quité el perfil y la pastoral no reaparece").
        $cuenta = $this->modelo->porId((int) $perfil['usuario_id']);
        if ($cuenta && $cuenta['persona_id'] !== null) {
            (new PersonaModel())->resincronizarCuenta((int) $cuenta['persona_id']);
        }

        $this->redirect(url_admin('usuarios', 'editar', ['id' => (int) $perfil['usuario_id']]));
    }

    /**
     * La ficha del equipo pastoral que se eligió en el formulario, ya validada,
     * o null si la cuenta no lleva ficha (la del administrador, por ejemplo).
     *
     * No se confía en el id recibido: tiene que existir, estar activa y no ser
     * ya de otra cuenta. La unicidad la garantiza además `uq_usr_persona` en la
     * base; esto es para poder explicarlo con un mensaje en vez de un error 500.
     */
    private function personaDelPost(?array $actual): ?array
    {
        $personaId = $this->postIntONull('persona_id');
        if ($personaId === null) {
            return null;
        }

        $persona = (new PersonaModel())->porId($personaId);
        if (!$persona || !$persona['activo']) {
            return null;
        }

        $duenio = $this->modelo->porPersona($personaId);
        if ($duenio && (!$actual || (int) $duenio['id'] !== (int) $actual['id'])) {
            return null;
        }

        return $persona;
    }

    /**
     * Los roles que la cuenta en sesión puede asignar a otra cuenta —al crear
     * o al editar, y en ambos casos siempre revalidado aquí, nunca solo en el
     * `<select>` del formulario—. Con alcance global, todos. Un Coordinador
     * general solo llega hasta Coordinador y Consulta: los rangos que
     * administra, nunca el suyo propio ni uno por encima.
     */
    private function rolesAsignables(): array
    {
        if (Auth::tieneAlcanceGlobal()) {
            return ROLES_NOMBRES;
        }
        return [
            ROL_COORDINADOR => ROLES_NOMBRES[ROL_COORDINADOR],
            ROL_CONSULTA    => ROLES_NOMBRES[ROL_CONSULTA],
        ];
    }

    /**
     * ¿Puede la cuenta en sesión ver o tocar esta cuenta objetivo?
     *
     * Con alcance global (Administrador), siempre. Un Coordinador general
     * llega hasta las cuentas de rango que administra —Coordinador,
     * Consulta— que comparten alguna de sus propias pastorales: nunca otra
     * Coordinador general (ni siquiera de su misma pastoral: administrar
     * cuentas de rango igual o superior sigue siendo solo de Administrador),
     * nunca Secretaría, Editor ni Administrador, y nunca una cuenta de una
     * pastoral que no administra él mismo.
     *
     * Deliberadamente NO hay excepción para "es mi propia cuenta": el rol de
     * esta cuenta (coordinador_general) tampoco está en rolesAsignables(), así
     * que si se permitiera editarse a sí misma, el `<select>` de rol no
     * tendría su propio valor entre las opciones y guardar la degradaría sin
     * avisar. Un Coordinador general no puede editar su propia cuenta desde
     * esta pantalla —tampoco podía antes de que existiera esta pantalla para
     * él—; para eso sigue haciendo falta Administrador.
     */
    private function dentroDeMiAlcance(array $cuenta): bool
    {
        if (Auth::tieneAlcanceGlobal()) {
            return true;
        }
        if (!in_array($cuenta['rol'], [ROL_COORDINADOR, ROL_CONSULTA], true)) {
            return false;
        }
        return (bool) array_intersect(
            Auth::pastoralesPermitidas(),
            $this->modelo->pastoralesDe((int) $cuenta['id'])
        );
    }

    private function requireAlcanceCuenta(array $cuenta): void
    {
        if (!$this->dentroDeMiAlcance($cuenta)) {
            Session::flash('error', 'Esa cuenta no está dentro de lo que administras.');
            $this->redirect(url_admin('usuarios'));
        }
    }
}
