<?php
class Controller
{
    /** Layout que envuelve la vista. El área pública lo cambia. */
    protected string $layout = 'layout_admin';

    // ── Guardas de acceso ───────────────────────────────────────────────

    protected function requireAuth(): void
    {
        if (!Auth::estaAutenticado()) {
            $this->redirect(url_admin('auth', 'login'));
        }
    }

    protected function requirePermiso(string $permiso): void
    {
        $this->requireAuth();
        if (!Auth::tienePermiso($permiso)) {
            Session::flash('error', 'No tienes permiso para realizar esa acción.');
            $this->redirect(url_admin('panel'));
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::esAdmin()) {
            Session::flash('error', 'Acceso restringido a administradores.');
            $this->redirect(url_admin('panel'));
        }
    }

    /**
     * Segunda comprobación, complementaria a requirePermiso(): el permiso dice
     * qué acción puede hacer, esto dice sobre qué registro.
     *
     * Al editar o borrar hay que pasarle el pastoral_id LEÍDO DE LA BASE, no el
     * que venga en el POST: de otro modo un coordinador podría reasignar un
     * registro ajeno enviando otro identificador.
     */
    protected function requireAlcancePastoral(?int $pastoralId): void
    {
        $this->requireAuth();
        if (!Auth::puedeSobrePastoral($pastoralId)) {
            Session::flash('error', 'Ese contenido pertenece a otra pastoral.');
            $this->redirect(url_admin('panel'));
        }
    }

    /**
     * IDs de pastoral por los que debe filtrar un listado del panel, o null si
     * el usuario ve todo. Los modelos lo traducen a "AND pastoral_id IN (…)".
     */
    protected function filtroPastoralSql(): ?array
    {
        return Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas();
    }

    /**
     * Datos para el selector de pastoral en los formularios de avisos,
     * eventos y galería: las opciones que puede elegir según su alcance, si
     * queda fija a una sola (coordinador con una única pastoral asignada,
     * que no debe elegir de una lista) y si puede dejarse en blanco
     * (alcance global = contenido parroquial general).
     */
    protected function opcionesPastoral(): array
    {
        require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
        $todas = (new PastoralModel())->paraSelector();

        if (Auth::tieneAlcanceGlobal()) {
            return ['opciones' => $todas, 'fija' => null, 'permiteVacio' => true];
        }

        $propias  = Auth::pastoralesPermitidas();
        $opciones = array_values(array_filter(
            $todas,
            static fn (array $p): bool => in_array((int) $p['id'], $propias, true)
        ));
        return [
            'opciones'     => $opciones,
            'fija'         => count($opciones) === 1 ? (int) $opciones[0]['id'] : null,
            'permiteVacio' => false,
        ];
    }

    /**
     * Valida el pastoral_id recibido del formulario contra el alcance real
     * del usuario. Nunca se confía en lo que traiga el select del navegador:
     * un coordinador manipulando el POST no puede asignar contenido a una
     * pastoral ajena, ni dejarlo como "general".
     *
     * @throws RuntimeException si el valor no es válido para este usuario
     */
    protected function pastoralIdValidado(): ?int
    {
        $enviado = $this->postIntONull('pastoral_id');

        if (Auth::tieneAlcanceGlobal()) {
            return $enviado;
        }

        $propias = Auth::pastoralesPermitidas();
        if ($enviado !== null && in_array($enviado, $propias, true)) {
            return $enviado;
        }
        throw new RuntimeException('Selecciona una de tus pastorales.');
    }

    protected function validarCsrf(): void
    {
        if (!Session::validarCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            exit('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
        }
        Session::renovarCsrf();
    }

    // ── Salida ──────────────────────────────────────────────────────────

    /**
     * Renderiza una vista dentro del layout.
     *
     * El nombre se resuelve como modulo/ruta →  modules/<modulo>/views/<ruta>.php
     *   render('usuarios/lista')        → modules/usuarios/views/lista.php
     *   render('inicio/publico/index')  → modules/inicio/views/publico/index.php
     */
    protected function render(string $vista, array $datos = []): void
    {
        $this->noCache();
        $vistaPath = $this->rutaVista($vista);

        // Quitar las claves reservadas del layout para que $datos no las pise.
        unset(
            $datos['flash'], $datos['usuario'], $datos['csrf'],
            $datos['appName'], $datos['appUrl'], $datos['vistaPath'], $datos['config']
        );
        extract($datos);

        $flash   = Session::getFlash();
        $usuario = Auth::usuario();
        $csrf    = Session::activa() ? Session::getCsrfToken() : '';
        $appName = APP_NAME;
        $appUrl  = url_base();
        $config  = Config::todo();

        require BASE_PATH . '/shared/views/' . $this->layout . '.php';
    }

    /** Renderiza sin layout: fragmentos para peticiones asíncronas, vistas de impresión. */
    protected function renderSinLayout(string $vista, array $datos = []): void
    {
        $this->noCache();
        $vistaPath = $this->rutaVista($vista);
        extract($datos);
        require $vistaPath;
    }

    private function rutaVista(string $vista): string
    {
        if (!preg_match('#^[a-z0-9_]+(/[a-z0-9_]+)+$#', $vista)) {
            throw new InvalidArgumentException('Nombre de vista inválido: ' . $vista);
        }
        $partes = explode('/', $vista);
        $modulo = array_shift($partes);
        return BASE_PATH . '/modules/' . $modulo . '/views/' . implode('/', $partes) . '.php';
    }

    /**
     * Evita que el navegador guarde páginas del panel. Sin esto, al volver
     * atrás tras editar un registro puede verse la versión anterior.
     * El área pública lo sobrescribe: ahí sí conviene que la caché funcione.
     */
    protected function noCache(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Recibe una URL ya construida con url_publica(), url_admin() o url_post(). */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** Vuelve a la página anterior, siempre que sea de este mismo sitio. */
    protected function redirectBack(?string $fallback = null): void
    {
        $ref  = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $propio = $ref !== '' && $host !== ''
               && strcasecmp((string) parse_url($ref, PHP_URL_HOST), $host) === 0;

        $this->redirect($propio ? $ref : ($fallback ?? url_admin('panel')));
    }

    // ── Entrada ─────────────────────────────────────────────────────────

    protected function postInt(string $key, int $default = 0): int
    {
        return (int) ($_POST[$key] ?? $default);
    }

    /** Texto plano: recorta y quita etiquetas. No usar para contenido enriquecido. */
    protected function postStr(string $key, string $default = ''): string
    {
        return $this->utf8(trim(strip_tags((string) ($_POST[$key] ?? $default))));
    }

    /**
     * Texto sin filtrar, para los campos de contenido enriquecido.
     * Lo que se obtenga aquí DEBE pasar por SanitizadorHtml antes de guardarse.
     */
    protected function postHtml(string $key): string
    {
        return $this->utf8(trim((string) ($_POST[$key] ?? '')));
    }

    /**
     * Asegura que el texto sea UTF-8 antes de que llegue a la base de datos.
     *
     * Un navegador que abre una página con <meta charset="UTF-8"> envía UTF-8,
     * así que en el uso normal esto no hace nada. Pero si por cualquier motivo
     * llegara texto en otra codificación, MySQL sustituye cada acento por un
     * signo de interrogación y el dato se pierde sin remedio ni aviso. Vale más
     * reinterpretarlo que guardar «Se?ora».
     */
    private function utf8(string $valor): string
    {
        if ($valor === '' || mb_check_encoding($valor, 'UTF-8')) {
            return $valor;
        }
        return mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');
    }

    protected function postFloat(string $key, float $default = 0.0): float
    {
        return (float) str_replace(',', '.', (string) ($_POST[$key] ?? $default));
    }

    /** Devuelve null cuando el campo llega vacío, para columnas que aceptan NULL. */
    protected function postStrONull(string $key): ?string
    {
        $valor = $this->postStr($key);
        return $valor !== '' ? $valor : null;
    }

    protected function postIntONull(string $key): ?int
    {
        $valor = trim((string) ($_POST[$key] ?? ''));
        return $valor !== '' ? (int) $valor : null;
    }

    protected function postBool(string $key): int
    {
        return !empty($_POST[$key]) ? 1 : 0;
    }

    protected function getInt(string $key, int $default = 0): int
    {
        return (int) ($_GET[$key] ?? $default);
    }

    protected function getStr(string $key, string $default = ''): string
    {
        return $this->utf8(trim(strip_tags((string) ($_GET[$key] ?? $default))));
    }

    // ── Auditoría ───────────────────────────────────────────────────────

    /**
     * Deja constancia de una acción. Se llama también al CONSULTAR datos
     * personales, no solo al modificarlos: es lo que permite responder a una
     * solicitud de acceso. Ver docs/PRIVACIDAD.md
     */
    protected function auditoria(string $accion, string $tabla = '', int $id = 0, string $desc = ''): void
    {
        try {
            Database::getInstance()->prepare(
                'INSERT INTO auditoria (usuario_id, accion, tabla_ref, registro_id, ip, descripcion)
                 VALUES (:uid, :accion, :tabla, :rid, :ip, :desc)'
            )->execute([
                ':uid'    => Auth::usuario()['id'],
                ':accion' => $accion,
                ':tabla'  => $tabla ?: null,
                ':rid'    => $id ?: null,
                ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
                ':desc'   => $desc ?: null,
            ]);
        } catch (Exception $e) {
            // Una falla al auditar no debe interrumpir la operación del usuario.
        }
    }
}
