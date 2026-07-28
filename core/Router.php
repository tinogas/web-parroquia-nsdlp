<?php
/**
 * Router — Despacha la petición al controlador correspondiente.
 *
 * A diferencia de un panel interno, este sitio tiene dos caras. El parámetro
 * 'area' elige entre ellas y, sobre todo, decide si se exige sesión: el área
 * pública NUNCA la pide. Copiar el guard global de un sistema cerrado dejaría
 * el sitio entero detrás del formulario de acceso.
 *
 * Con dos tablas separadas un mismo nombre de módulo puede existir en las dos
 * áreas —'avisos' es el listado público y también el CRUD del panel— con
 * controladores distintos y un solo modelo compartido.
 */
class Router
{
    private static ?string $areaResuelta = null;

    private static array $rutasPublicas = [
        'inicio' => ['clase' => 'InicioController', 'archivo' => 'modules/inicio/InicioController.php'],
    ];

    private static array $rutasAdmin = [
        'auth'          => ['clase' => 'AuthController',          'archivo' => 'modules/auth/AuthController.php'],
        'panel'         => ['clase' => 'PanelController',         'archivo' => 'modules/panel/PanelController.php'],
        'configuracion' => ['clase' => 'ConfiguracionController', 'archivo' => 'modules/configuracion/ConfiguracionController.php'],
        'bloques'       => ['clase' => 'BloqueController',        'archivo' => 'modules/bloques/BloqueController.php'],
    ];

    /**
     * Área solicitada. index.php la consulta antes de despachar para saber si
     * hace falta abrir sesión.
     */
    public static function area(): string
    {
        if (self::$areaResuelta === null) {
            self::$areaResuelta = (($_GET['area'] ?? '') === 'admin') ? 'admin' : 'publico';
        }
        return self::$areaResuelta;
    }

    /**
     * ¿Está publicado ya este módulo del sitio?
     *
     * El menú público declara todas las secciones previstas y consulta esto
     * para mostrar solo las que existen. Así el menú crece solo conforme
     * avanzan las etapas, sin enlaces rotos por el camino.
     */
    public static function existeRutaPublica(string $modulo): bool
    {
        return isset(self::$rutasPublicas[$modulo]);
    }

    public static function dispatch(): void
    {
        $area   = self::area();
        $modulo = self::parametro('modulo');
        $accion = self::parametro('accion');

        // Segmento legible de la URL → nombre interno del módulo.
        $modulo = (array_flip(ALIAS_URL)[$modulo] ?? $modulo);

        if ($modulo === '') {
            $modulo = $area === 'admin' ? 'panel' : 'inicio';
        }

        $rutas = $area === 'admin' ? self::$rutasAdmin : self::$rutasPublicas;

        // Aquí, y solo aquí, se exige sesión.
        if ($area === 'admin' && $modulo !== 'auth' && !Auth::estaAutenticado()) {
            header('Location: ' . url_admin('auth', 'login'));
            exit;
        }

        if (!isset($rutas[$modulo])) {
            // Un primer segmento desconocido puede ser el slug de una página
            // libre: /aviso-de-privacidad en vez de /pagina/aviso-de-privacidad.
            if ($area === 'publico' && isset(self::$rutasPublicas['pagina'])
                && $accion === '' && empty($_GET['slug'])) {
                $_GET['slug'] = $modulo;
                $modulo       = 'pagina';
                $accion       = 'ver';
            } else {
                self::error404($area);
                return;
            }
        }

        // Una URL con slug y sin acción explícita muestra el detalle.
        if ($accion === '') {
            $accion = !empty($_GET['slug']) ? 'ver' : 'index';
        }

        $archivo = BASE_PATH . '/' . $rutas[$modulo]['archivo'];
        if (!is_file($archivo)) {
            self::error404($area);
            return;
        }

        require_once $archivo;
        $clase = $rutas[$modulo]['clase'];
        if (!class_exists($clase)) {
            self::error404($area);
            return;
        }

        $controlador = new $clase();
        $metodo      = self::sanitizarMetodo($accion);

        if ($metodo === '' || !method_exists($controlador, $metodo)
            || !is_callable([$controlador, $metodo])) {
            self::error404($area);
            return;
        }

        $controlador->$metodo();
    }

    /** Limpia un parámetro de la URL dejando solo caracteres de identificador. */
    private static function parametro(string $nombre): string
    {
        $valor = $_GET[$nombre] ?? '';
        if (!is_string($valor)) {
            return '';
        }
        return strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', $valor));
    }

    /** Convierte "cambiar_estado" en "cambiarEstado". */
    private static function sanitizarMetodo(string $accion): string
    {
        $partes = explode('_', preg_replace('/[^a-z0-9_]/', '', strtolower($accion)));
        $metodo = array_shift($partes);
        foreach ($partes as $parte) {
            $metodo .= ucfirst($parte);
        }
        return $metodo;
    }

    public static function error404(string $area = 'publico'): void
    {
        http_response_code(404);

        if ($area === 'admin') {
            exit('<h1>404</h1><p>La pantalla solicitada no existe.</p>');
        }

        $flash      = Session::activa() ? Session::getFlash() : [];
        $usuario    = Auth::usuario();
        $csrf       = '';
        $appName    = APP_NAME;
        $appUrl     = url_base();
        $config     = Config::todo();
        $metaTitulo = 'Página no encontrada';
        $sinIndexar = true;
        $vistaPath  = BASE_PATH . '/shared/views/error404.php';

        require BASE_PATH . '/shared/views/layout_publico.php';
        exit;
    }
}
