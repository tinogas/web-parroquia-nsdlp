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
     * Para acciones irreversibles o que cambian qué ve todo el mundo (activar
     * una pastoral en el menú, borrarla): exige además que quien la ejecuta
     * reescriba su propia contraseña en el momento, no solo tener el rol.
     * `Auth::esAdmin()` ya excluye por sí solo una sesión de impersonación
     * (durante ella `usuario_rol` es el del usuario suplantado), así que no
     * hace falta distinguir aparte al "admin real".
     */
    protected function requireAdminConPassword(): void
    {
        $this->requireAdmin();

        require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
        $usuario = (new UsuarioModel())->porId((int) Auth::usuario()['id']);
        if (!$usuario || !password_verify($this->postStr('confirmar_password'), $usuario['password_hash'])) {
            Session::flash('error', 'Contraseña incorrecta.');
            $this->redirectBack();
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
     * Lo mismo, para el contenido que además vive en una sede: eventos y
     * cursos. Las dos condiciones se exigen juntas —la pastoral dice qué equipo
     * lo organiza y la sede en qué comunidad—, y basta que falle una para
     * rechazar: la coordinadora de catequesis de Jesús el Señor no toca la
     * catequesis de la sede aunque sea la misma pastoral.
     *
     * Los dos identificadores se leen DE LA BASE al editar y borrar, nunca del
     * POST, por lo mismo que en requireAlcancePastoral().
     */
    protected function requireAlcanceContenido(?int $pastoralId, ?int $centroId): void
    {
        $this->requireAuth();
        if (!Auth::puedeSobrePastoral($pastoralId) || !Auth::puedeSobreCentro($centroId)) {
            Session::flash('error', 'Ese contenido pertenece a otra pastoral o a otra sede.');
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
     * Filtro de pastoral elegido en pantalla (?pastoral=), que no es lo mismo
     * que filtroPastoralSql(): aquel recorta el listado al alcance de quien
     * mira, y este solo acota lo que esa persona quiso ver. Los dos se cruzan
     * después con pastoralesVisibles(); la agenda es la única pantalla que usa
     * este filtro a solas, porque ahí sí se ven todas entre sí.
     *
     * Acepta '' (todas), 'mias' (las que administra) o el id de una pastoral.
     * Un id que no exista cae a «todas» en silencio: es un filtro, no una
     * búsqueda que deba fallar con un error. Cuando el listado ofrece el
     * selector ya acotado —pastoralesDelFiltro()—, el id de una pastoral ajena
     * tampoco está en la lista y cae igual, así que nadie se asoma a lo de otra
     * escribiendo la URL a mano.
     *
     * @param  array $pastorales Las del selector, tal como las da PastoralModel::paraSelector()
     * @return array{0: string, 1: ?array} [valor para las URLs, ids para el modelo]
     */
    protected function filtroPastoral(array $pastorales): array
    {
        $valor   = $this->getStr('pastoral');
        $propias = Auth::pastoralesPermitidas();

        if ($valor === 'mias' && $propias) {
            return ['mias', $propias];
        }

        $id = (int) $valor;
        foreach ($pastorales as $pastoral) {
            if ((int) $pastoral['id'] === $id) {
                return [(string) $id, [$id]];
            }
        }
        return ['', null];
    }

    /**
     * Las pastorales que ofrece el selector de un listado del panel: todas si
     * quien mira tiene alcance global, y si no, las suyas. Ofrecer una pastoral
     * ajena en un listado que no la va a mostrar es prometer algo que no pasa.
     *
     * $excluirComisiones lo usa el formulario de usuarios: una Comisión no
     * tiene contenido operativo propio y su alcance no se hereda a sus
     * hijas, así que no tiene sentido ofrecerla como "pastoral que
     * administra" un coordinador (ver PastoralModel::sinComisiones()).
     */
    protected function pastoralesDelFiltro(bool $excluirComisiones = false): array
    {
        require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
        $modelo = new PastoralModel();
        $todas  = $excluirComisiones ? $modelo->sinComisiones() : $modelo->paraSelector();

        if (Auth::tieneAlcanceGlobal()) {
            return $todas;
        }

        $propias = Auth::pastoralesPermitidas();
        return array_values(array_filter(
            $todas,
            static fn (array $p): bool => in_array((int) $p['id'], $propias, true)
        ));
    }

    /**
     * Qué pastorales ve de verdad un listado del panel: el filtro que la
     * persona eligió en pantalla, cruzado con su alcance real.
     *
     * Quien tiene alcance global ve lo que pidió, sin más. Quien no, ve lo suyo
     * **y lo general de la parroquia** —311 de los 467 eventos de la agenda son
     * generales; esconderlos dejaría a cada pastoral mirando su propio recorte
     * del calendario—, salvo que pida «solo las mías» o una pastoral concreta.
     * Los generales llegan al modelo como un null dentro de la lista; lo demás
     * lo hace Model::condicionAlcance().
     *
     * Esto es solo lectura. Escribir sigue acotado a lo propio, siempre, en
     * requireAlcanceContenido(): ver un evento general no da derecho a tocarlo.
     *
     * @param  ?array $filtroElegido El segundo valor de filtroPastoral()
     * @return ?array Ids para el modelo, null si no hay que filtrar nada
     */
    protected function pastoralesVisibles(?array $filtroElegido): ?array
    {
        if (Auth::tieneAlcanceGlobal()) {
            return $filtroElegido;
        }

        $propias = Auth::pastoralesPermitidas();
        if ($filtroElegido === null) {
            return array_merge([null], $propias);
        }
        return array_values(array_intersect($filtroElegido, $propias));
    }

    // ── La otra mitad del alcance: la sede ──────────────────────────────
    //
    // Misma mecánica que las tres funciones de arriba, sobre centro_id. Están
    // separadas y no unificadas en una sola con la columna por parámetro
    // porque las reglas no son iguales: no tener pastorales asignadas es no
    // poder con nada, y no tener sedes asignadas es poder en todas.

    /** Las sedes que ofrece el selector de un listado: todas, o solo las suyas. */
    protected function centrosDelFiltro(): array
    {
        require_once BASE_PATH . '/modules/centros/CentroModel.php';
        $todos = (new CentroModel())->activos();

        $propios = Auth::centrosPermitidos();
        if (Auth::tieneAlcanceGlobal() || !$propios) {
            return $todos;
        }
        return array_values(array_filter(
            $todos,
            static fn (array $c): bool => in_array((int) $c['id'], $propios, true)
        ));
    }

    /**
     * Filtro de sede elegido en pantalla (?centro=): '' para todas, o el id de
     * una. Como en filtroPastoral(), un id que no esté en el selector cae a
     * «todas» en silencio.
     *
     * @param  array $centros Los del selector, tal como los da centrosDelFiltro()
     * @return array{0: string, 1: ?array} [valor para las URLs, ids para el modelo]
     */
    protected function filtroCentro(array $centros): array
    {
        $id = (int) $this->getStr('centro');
        foreach ($centros as $centro) {
            if ((int) $centro['id'] === $id) {
                return [(string) $id, [$id]];
            }
        }
        return ['', null];
    }

    /**
     * Qué sedes ve de verdad un listado: el filtro de pantalla cruzado con las
     * sedes de quien mira. Sin sedes marcadas no se recorta nada —trabaja en
     * toda la parroquia—; con ellas se ven las suyas y, cuando no ha pedido una
     * concreta, también el contenido sin sede, que es de todos.
     *
     * @param  ?array $filtroElegido El segundo valor de filtroCentro()
     * @return ?array Ids para el modelo, null si no hay que filtrar nada
     */
    protected function centrosVisibles(?array $filtroElegido): ?array
    {
        $propios = Auth::centrosPermitidos();
        if (Auth::tieneAlcanceGlobal() || !$propios) {
            return $filtroElegido;
        }

        if ($filtroElegido === null) {
            return array_merge([null], $propios);
        }
        return array_values(array_intersect($filtroElegido, $propios));
    }

    /**
     * Datos para el selector de sede de los formularios de eventos y cursos,
     * con el prefijo sc_ que espera shared/views/parciales/selector_centro.php.
     * Mismo criterio que opcionesPastoral(): si solo puede una, va fija y no se
     * le enseña una lista de una sola opción.
     */
    protected function opcionesCentro(): array
    {
        $opciones = $this->centrosDelFiltro();
        $propios  = Auth::centrosPermitidos();

        if (Auth::tieneAlcanceGlobal() || !$propios) {
            return ['sc_opciones' => $opciones, 'sc_fija' => null, 'sc_permiteVacio' => true];
        }

        return [
            'sc_opciones'     => $opciones,
            'sc_fija'         => count($opciones) === 1 ? (int) $opciones[0]['id'] : null,
            'sc_permiteVacio' => false,
        ];
    }

    /**
     * Valida el centro_id del formulario contra las sedes de quien guarda, como
     * pastoralIdValidado() con las pastorales.
     *
     * @throws RuntimeException si el valor no es válido para este usuario
     */
    protected function centroIdValidado(): ?int
    {
        $enviado = $this->postIntONull('centro_id');
        $propios = Auth::centrosPermitidos();

        if (Auth::tieneAlcanceGlobal() || !$propios) {
            return $enviado;
        }
        if ($enviado !== null && in_array($enviado, $propios, true)) {
            return $enviado;
        }
        throw new RuntimeException('Elige una de tus sedes.');
    }

    /**
     * Datos para el selector de pastoral en los formularios de avisos,
     * eventos y galería: las opciones que puede elegir según su alcance, si
     * queda fija a una sola (coordinador con una única pastoral asignada,
     * que no debe elegir de una lista) y si puede dejarse en blanco
     * (alcance global = contenido parroquial general).
     *
     * Las claves llevan el prefijo sp_ porque son justo las que espera
     * shared/views/parciales/selector_pastoral.php: al pasarlas por
     * render(), extract() las deja listas para el partial sin que cada
     * vista tenga que renombrarlas una por una.
     */
    protected function opcionesPastoral(): array
    {
        $opciones = $this->pastoralesDelFiltro();

        if (Auth::tieneAlcanceGlobal()) {
            return ['sp_opciones' => $opciones, 'sp_fija' => null, 'sp_permiteVacio' => true];
        }

        return [
            'sp_opciones'     => $opciones,
            'sp_fija'         => count($opciones) === 1 ? (int) $opciones[0]['id'] : null,
            'sp_permiteVacio' => false,
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

    /**
     * pastoral_id sugerido por querystring (?pastoral_id=), para que "Nuevo
     * aviso/evento/curso" llegue con el selector ya puesto cuando se entra
     * desde el panel básico de una pastoral. A diferencia de
     * pastoralIdValidado(), esto es una sugerencia de UI, no un dato que se
     * vaya a guardar: un valor fuera de alcance se ignora en silencio en vez
     * de fallar, igual que el resto de selects opcionales del proyecto.
     */
    protected function pastoralIdPreseleccionado(): ?int
    {
        $id = $this->getInt('pastoral_id', 0) ?: null;
        if ($id === null || Auth::tieneAlcanceGlobal()) {
            return $id;
        }
        return in_array($id, Auth::pastoralesPermitidas(), true) ? $id : null;
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
     *
     * usuario_id queda con la identidad efectiva de la sesión (la impersonada,
     * si aplica); admin_real_id, con el administrador real detrás si la acción
     * ocurrió durante un "Usar como…". Así se distingue "lo hizo la
     * secretaria" de "lo hizo el admin actuando como la secretaria".
     */
    protected function auditoria(string $accion, string $tabla = '', int $id = 0, string $desc = ''): void
    {
        try {
            Database::getInstance()->prepare(
                'INSERT INTO auditoria (usuario_id, admin_real_id, accion, tabla_ref, registro_id, ip, descripcion)
                 VALUES (:uid, :adminReal, :accion, :tabla, :rid, :ip, :desc)'
            )->execute([
                ':uid'       => Auth::usuario()['id'],
                ':adminReal' => Auth::adminReal()['id'] ?? null,
                ':accion'    => $accion,
                ':tabla'     => $tabla ?: null,
                ':rid'       => $id ?: null,
                ':ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
                ':desc'      => $desc ?: null,
            ]);
        } catch (Exception $e) {
            // Una falla al auditar no debe interrumpir la operación del usuario.
        }
    }
}
