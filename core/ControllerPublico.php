<?php
/**
 * ControllerPublico — Base de los controladores del sitio.
 *
 * Se diferencia del controlador de administración en tres cosas: usa el layout
 * público, permite que el navegador guarde la página en caché, y solo abre
 * sesión si la pantalla tiene formulario.
 */
class ControllerPublico extends Controller
{
    protected string $layout = 'layout_publico';

    /**
     * Ponlo en true en los controladores con formulario: necesitan token CSRF,
     * y el token necesita sesión. El resto de las páginas públicas no le pone
     * cookie a nadie.
     */
    protected bool $requiereSesion = false;

    public function __construct()
    {
        if ($this->requiereSesion) {
            Session::iniciar();
        }
    }

    /**
     * Cabeceras de caché en vez de las de "no guardar" del panel.
     *
     * Solo para visitantes anónimos y sin mensaje flash pendiente: a alguien
     * con sesión abierta o que acaba de enviar un formulario hay que mostrarle
     * siempre la versión fresca.
     */
    protected function noCache(): void
    {
        if (headers_sent()) {
            return;
        }

        if (Auth::estaAutenticado() || Session::hayFlash() || $_SERVER['REQUEST_METHOD'] !== 'GET') {
            parent::noCache();
            return;
        }

        header('Cache-Control: public, max-age=300');
    }

    /**
     * Responde 404 con el layout público, para que un enlace roto siga
     * mostrando la navegación del sitio en vez de una página desnuda.
     */
    protected function noEncontrado(): void
    {
        http_response_code(404);
        $flash   = Session::activa() ? Session::getFlash() : [];
        $usuario = Auth::usuario();
        $csrf    = '';
        $appName = APP_NAME;
        $appUrl  = url_base();
        $config  = Config::todo();

        $metaTitulo  = 'Página no encontrada';
        $sinIndexar  = true;
        $vistaPath   = BASE_PATH . '/shared/views/error404.php';

        require BASE_PATH . '/shared/views/layout_publico.php';
        exit;
    }
}
