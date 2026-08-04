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
                if (Auth::tieneLoginPendiente()) {
                    // Con perfiles adicionales, la contraseña ya se verificó
                    // pero falta elegir con cuál entrar: la auditoría del
                    // login se registra recién ahí, cuando la sesión quede
                    // completa.
                    $this->redirect(url_admin('auth', 'elegir_perfil'));
                }
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

    /**
     * Pantalla intermedia para cuentas con perfiles adicionales: la
     * contraseña ya se verificó en login() (Auth::intentarLogin()) pero la
     * sesión no se completa hasta elegir con cuál entrar. Ver
     * Auth::loginPendienteId().
     */
    public function elegirPerfil(): void
    {
        $usuarioId = Auth::loginPendienteId();
        if ($usuarioId === null) {
            $this->redirect(url_admin('auth', 'login'));
            return;
        }

        require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
        require_once BASE_PATH . '/modules/usuarios/UsuarioPerfilModel.php';
        $usuario = (new UsuarioModel())->porId($usuarioId);
        if (!$usuario || !$usuario['activo']) {
            Auth::cancelarLoginPendiente();
            $this->redirect(url_admin('auth', 'login'));
            return;
        }
        $perfiles = (new UsuarioPerfilModel())->activosDeUsuario($usuarioId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validarCsrf();

            $perfilId = $this->postIntONull('perfil_id');
            $perfil   = null;
            if ($perfilId !== null) {
                foreach ($perfiles as $candidato) {
                    if ((int) $candidato['id'] === $perfilId) {
                        $perfil = $candidato;
                        break;
                    }
                }
                if ($perfil === null) {
                    // El id no pertenece a esta cuenta o ya no está activo: no
                    // se confía en lo que venga del POST más allá de eso.
                    Session::flash('error', 'Elige una de las opciones de la lista.');
                    $this->redirect(url_admin('auth', 'elegir_perfil'));
                    return;
                }
            }

            Auth::completarLogin($usuario, $perfil);
            $this->auditoria('login', 'usuarios', (int) $usuario['id']);
            $this->redirect(url_admin('panel'));
        }

        $this->noCache();
        $flash = Session::getFlash();
        $csrf  = Session::getCsrfToken();
        // Si el rol principal no tiene ninguna pastoral asignada, no se
        // ofrece como opción: llegar aquí con eso y solo 1 perfil ya se
        // resolvió antes, en Auth::intentarLogin() (entra directo, sin esta
        // pantalla); aquí solo aplica cuando además hay 2+ perfiles entre
        // los que sí hay algo que elegir.
        $rolPrincipalUtil = Auth::rolPrincipalTieneAlcance($usuario);
        require BASE_PATH . '/modules/auth/views/elegir_perfil.php';
    }

    /**
     * Cambia el perfil activo de la MISMA cuenta ya autenticada, sin pedir
     * contraseña de nuevo: la sesión ya demostró quién es, igual que "Usar
     * como…" tampoco la vuelve a pedir. Distinto de elegirPerfil() (completa
     * un login recién verificado) y de iniciarImpersonacion() (cambia a OTRA
     * cuenta): aquí usuario_id no cambia, solo el rol y el alcance con el que
     * opera. No aplica mientras se impersona, para no mezclar dos cambios de
     * identidad a la vez —quien impersona entra siempre con el perfil
     * principal del objetivo, ver docs/ARQUITECTURA.md—.
     */
    public function cambiarPerfil(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('panel'));
            return;
        }
        $this->validarCsrf();

        if (Auth::estaImpersonando()) {
            Session::flash('error', 'No puedes cambiar de perfil mientras usas otra cuenta.');
            $this->redirect(url_admin('panel'));
            return;
        }

        require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
        require_once BASE_PATH . '/modules/usuarios/UsuarioPerfilModel.php';
        $usuarioId = (int) Auth::usuario()['id'];
        $usuario   = (new UsuarioModel())->porId($usuarioId);
        if (!$usuario || !$usuario['activo']) {
            // La cuenta se desactivó a mitad de su propia sesión: no tiene
            // sentido dejarla elegir un perfil de algo que ya no existe.
            Auth::logout();
            $this->redirect(url_admin('auth', 'login'));
            return;
        }

        $perfilId = $this->postIntONull('perfil_id');
        $perfil   = null;
        if ($perfilId !== null) {
            foreach ((new UsuarioPerfilModel())->activosDeUsuario($usuarioId) as $candidato) {
                if ((int) $candidato['id'] === $perfilId) {
                    $perfil = $candidato;
                    break;
                }
            }
            if ($perfil === null) {
                Session::flash('error', 'Ese perfil ya no está disponible.');
                $this->redirect(url_admin('panel'));
                return;
            }
        }

        Auth::completarLogin($usuario, $perfil);
        $this->auditoria(
            'cambiar_perfil',
            'usuarios',
            $usuarioId,
            $perfil ? "Cambió a: {$perfil['nombre']}" : 'Cambió a: perfil principal'
        );
        Session::flash('success', $perfil ? "Ahora estás en el perfil {$perfil['nombre']}." : 'Volviste a tu perfil principal.');
        $this->redirect(url_admin('panel'));
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
