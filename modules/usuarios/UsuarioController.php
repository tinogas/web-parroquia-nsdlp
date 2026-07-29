<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/Upload.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
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

        $this->render('usuarios/lista', [
            'titulo'   => 'Usuarios',
            'usuarios' => $this->modelo->todos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('usuarios.editar');

        $this->render('usuarios/form', [
            'titulo'           => 'Nuevo usuario',
            'cuenta'           => null,
            'pastorales'       => (new PastoralModel())->paraSelector(),
            'asignadas'        => [],
            'centros'          => (new CentroModel())->activos(),
            'centrosAsignados' => [],
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

        $this->render('usuarios/form', [
            'titulo'           => $cuenta['nombre'],
            // OJO: 'usuario' es una clave reservada de Controller::render() —
            // guarda ahí SIEMPRE al administrador con sesión activa (la usa el
            // navbar). Usarla para la cuenta que se está editando la pisa en
            // silencio con Auth::usuario(), y el formulario termina mostrando
            // los datos de quien tiene la sesión abierta en vez de los de la
            // cuenta editada. De ahí el nombre 'cuenta' en vez de 'usuario'.
            'cuenta'           => $cuenta,
            'pastorales'       => (new PastoralModel())->paraSelector(),
            'asignadas'        => $this->modelo->pastoralesDe((int) $cuenta['id']),
            'centros'          => (new CentroModel())->activos(),
            'centrosAsignados' => $this->modelo->centrosDe((int) $cuenta['id']),
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
        $esPropio = $actual !== null && (int) $actual['id'] === (int) Auth::usuario()['id'];

        $nombre     = $this->postStr('nombre');
        $email      = strtolower($this->postStr('email'));
        $rol        = $this->postStr('rol');
        $password   = (string) ($_POST['password'] ?? '');
        $pastorales = $rol === ROL_COORDINADOR
            ? array_values(array_unique(array_map('intval', array_filter((array) ($_POST['pastorales'] ?? []), 'is_numeric'))))
            : [];
        $centros = $rol === ROL_COORDINADOR
            ? array_values(array_unique(array_map('intval', array_filter((array) ($_POST['centros'] ?? []), 'is_numeric'))))
            : [];

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Escribe el nombre completo.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Escribe un correo electrónico válido.';
        } elseif ($this->modelo->emailExiste($email, $actual ? $id : null)) {
            $errores[] = 'Ese correo ya está registrado por otro usuario.';
        }
        if (!isset(ROLES_NOMBRES[$rol])) {
            $errores[] = 'Elige un rol válido.';
        }
        if ($rol === ROL_COORDINADOR && !$pastorales && !$centros) {
            $errores[] = 'Un coordinador debe tener asignada al menos una pastoral o un centro/sede.';
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

        $foto = $actual['foto'] ?? null;
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

        $datos = [
            'nombre'     => $nombre,
            'email'      => $email,
            'password'   => $password,
            'rol'        => $rol,
            'telefono'   => $this->postStr('telefono') ?: null,
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
            $this->modelo->desactivar($id);
            $this->auditoria('eliminar', 'usuarios', $id, "Usuario: {$usuario['email']}");
            Session::flash('success', 'Usuario dado de baja.');
        }

        $this->redirect(url_admin('usuarios'));
    }
}
