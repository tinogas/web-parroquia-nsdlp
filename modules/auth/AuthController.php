<?php
require_once BASE_PATH . '/core/Controller.php';

class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::estaAutenticado()) {
            $this->redirect(url_admin('panel'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validarCsrf();

            $email    = $this->postStr('email');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                Session::flash('error', 'Escribe tu correo y tu contraseña.');
                $this->mostrarFormulario();
                return;
            }

            if (Auth::intentarLogin($email, $password)) {
                $this->auditoria('login', 'usuarios', (int) Auth::usuario()['id']);
                $this->redirect(url_admin('panel'));
            }

            // Un mensaje genérico a propósito: decir cuál de los dos datos
            // falló le confirmaría a un atacante qué correos están dados de alta.
            Session::flash('error', 'Correo o contraseña incorrectos.');
            $this->mostrarFormulario();
            return;
        }

        $this->mostrarFormulario();
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('panel'));
            return;
        }
        $this->validarCsrf();
        Auth::logout();
        $this->redirect(url_admin('auth', 'login'));
    }

    /**
     * "Usar como…": el administrador opera temporalmente con la sesión de
     * otro usuario, sin conocer su contraseña. requirePermiso() ya bloquea
     * esto para cualquier sesión impersonada (ningún rol no-admin tiene
     * usuarios.impersonar), pero el chequeo de estaImpersonando() se deja
     * explícito: la impersonación nunca se anida.
     */
    public function impersonar(): void
    {
        $this->requirePermiso('usuarios.impersonar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('panel'));
            return;
        }
        $this->validarCsrf();

        if (Auth::estaImpersonando()) {
            Session::flash('error', 'Ya estás usando otra cuenta. Vuelve a tu sesión de administrador primero.');
            $this->redirect(url_admin('panel'));
            return;
        }

        require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
        $objetivo = (new UsuarioModel())->porId($this->postInt('usuario_id'));

        if (!$objetivo || !$objetivo['activo'] || $objetivo['rol'] === ROL_ADMIN) {
            Session::flash('error', 'No puedes usar esa cuenta.');
            $this->redirect(url_admin('panel'));
            return;
        }

        Auth::iniciarImpersonacion($objetivo);
        $this->auditoria('impersonar_iniciar', 'usuarios', (int) $objetivo['id'], 'Usando como: ' . $objetivo['email']);
        Session::flash('success', 'Ahora estás usando el panel como ' . $objetivo['nombre'] . '.');
        $this->redirect(url_admin('panel'));
    }

    /** Vuelve a la sesión real de administrador. Nunca se gatea con requirePermiso(): tiene que
     *  funcionar sin importar qué pueda o no hacer el rol de la cuenta que se estaba usando. */
    public function terminarImpersonacion(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('panel'));
            return;
        }
        $this->validarCsrf();

        if (!Auth::estaImpersonando()) {
            $this->redirect(url_admin('panel'));
            return;
        }

        $impersonado = Auth::usuario();
        Auth::terminarImpersonacion();
        $this->auditoria('impersonar_terminar', 'usuarios', (int) $impersonado['id'], 'Dejó de usar: ' . $impersonado['email']);
        Session::flash('success', 'Volviste a tu sesión de administrador.');
        $this->redirect(url_admin('panel'));
    }

    /** El acceso tiene pantalla propia: no lleva el sidebar del panel. */
    private function mostrarFormulario(): void
    {
        $this->noCache();
        $flash = Session::getFlash();
        $csrf  = Session::getCsrfToken();
        require BASE_PATH . '/modules/auth/views/login.php';
    }
}
