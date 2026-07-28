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

    /** El acceso tiene pantalla propia: no lleva el sidebar del panel. */
    private function mostrarFormulario(): void
    {
        $this->noCache();
        $flash = Session::getFlash();
        $csrf  = Session::getCsrfToken();
        require BASE_PATH . '/modules/auth/views/login.php';
    }
}
