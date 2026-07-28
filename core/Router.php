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
        'inicio'   => ['clase' => 'InicioController',           'archivo' => 'modules/inicio/InicioController.php'],
        'nosotros' => ['clase' => 'NosotrosController',         'archivo' => 'modules/nosotros/NosotrosController.php'],
        'horarios' => ['clase' => 'HorarioPublicoController',   'archivo' => 'modules/horarios/HorarioPublicoController.php'],
        'contacto' => ['clase' => 'ContactoPublicoController',  'archivo' => 'modules/contacto/ContactoPublicoController.php'],
        'pagina'   => ['clase' => 'PaginaPublicoController',    'archivo' => 'modules/paginas/PaginaPublicoController.php'],
        'avisos'   => ['clase' => 'AvisoPublicoController',     'archivo' => 'modules/avisos/AvisoPublicoController.php'],
        'eventos'  => ['clase' => 'EventoPublicoController',    'archivo' => 'modules/eventos/EventoPublicoController.php'],
        'galeria'  => ['clase' => 'GaleriaPublicoController',   'archivo' => 'modules/galeria/GaleriaPublicoController.php'],
        'pastorales' => ['clase' => 'PastoralPublicoController', 'archivo' => 'modules/pastorales/PastoralPublicoController.php'],
        'sacramentos' => ['clase' => 'SacramentoPublicoController', 'archivo' => 'modules/sacramentos/SacramentoPublicoController.php'],
        'cursos'      => ['clase' => 'CursoPublicoController',      'archivo' => 'modules/cursos/CursoPublicoController.php'],
        'sitemap'     => ['clase' => 'SitemapController',           'archivo' => 'modules/sitemap/SitemapController.php'],
    ];

    private static array $rutasAdmin = [
        'auth'          => ['clase' => 'AuthController',          'archivo' => 'modules/auth/AuthController.php'],
        'panel'         => ['clase' => 'PanelController',         'archivo' => 'modules/panel/PanelController.php'],
        'configuracion' => ['clase' => 'ConfiguracionController', 'archivo' => 'modules/configuracion/ConfiguracionController.php'],
        'bloques'       => ['clase' => 'BloqueController',        'archivo' => 'modules/bloques/BloqueController.php'],
        'paginas'       => ['clase' => 'PaginaController',        'archivo' => 'modules/paginas/PaginaController.php'],
        'mensajes'      => ['clase' => 'MensajeController',       'archivo' => 'modules/contacto/MensajeController.php'],
        'horarios'      => ['clase' => 'HorarioController',       'archivo' => 'modules/horarios/HorarioController.php'],
        'personas'      => ['clase' => 'PersonaController',       'archivo' => 'modules/personas/PersonaController.php'],
        'organigrama'   => ['clase' => 'OrganigramaController',   'archivo' => 'modules/organigrama/OrganigramaController.php'],
        'avisos'        => ['clase' => 'AvisoController',         'archivo' => 'modules/avisos/AvisoController.php'],
        'eventos'       => ['clase' => 'EventoController',        'archivo' => 'modules/eventos/EventoController.php'],
        'galeria'       => ['clase' => 'GaleriaController',       'archivo' => 'modules/galeria/GaleriaController.php'],
        'carrusel'      => ['clase' => 'CarruselController',      'archivo' => 'modules/carrusel/CarruselController.php'],
        'pastorales'    => ['clase' => 'PastoralController',      'archivo' => 'modules/pastorales/PastoralController.php'],
        'sacramentos'   => ['clase' => 'SacramentoController',    'archivo' => 'modules/sacramentos/SacramentoController.php'],
        'solicitudes'   => ['clase' => 'SolicitudController',     'archivo' => 'modules/sacramentos/SolicitudController.php'],
        'cursos'        => ['clase' => 'CursoController',         'archivo' => 'modules/cursos/CursoController.php'],
        'inscripciones' => ['clase' => 'InscripcionCursoController', 'archivo' => 'modules/cursos/InscripcionCursoController.php'],
        'usuarios'      => ['clase' => 'UsuarioController',       'archivo' => 'modules/usuarios/UsuarioController.php'],
        'auditoria'     => ['clase' => 'AuditoriaController',     'archivo' => 'modules/auditoria/AuditoriaController.php'],
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
