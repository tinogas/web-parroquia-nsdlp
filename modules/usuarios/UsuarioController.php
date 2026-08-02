<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/Upload.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

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

        $this->render('usuarios/lista', [
            'titulo'          => 'Usuarios',
            'usuarios'        => $this->modelo->todos(Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas()),
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
            'asignadas'        => [],
            'centros'          => $this->centrosDelFiltro(),
            'centrosAsignados' => [],
            'rolesDisponibles' => $this->rolesAsignables(),
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
            'fichaCuenta'      => $cuenta['persona_id'] !== null
                ? (new PersonaModel())->porId((int) $cuenta['persona_id'])
                : null,
            'pastorales'       => $this->pastoralesDelFiltro(true),
            'asignadas'        => $this->modelo->pastoralesDe((int) $cuenta['id']),
            'centros'          => $this->centrosDelFiltro(),
            'centrosAsignados' => $this->modelo->centrosDe((int) $cuenta['id']),
            'rolesDisponibles' => $this->rolesAsignables(),
        ]);
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
            $nombre     = $persona['nombre'];
            $pastorales = $pastorales ?: (new PersonaModel())->pastoralesDe((int) $persona['id']);
            $centros    = $centros    ?: (new PersonaModel())->centrosDe((int) $persona['id']);
            $email      = $email ?: strtolower((string) $persona['email']);
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
        if (!Auth::tieneAlcanceGlobal()) {
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
