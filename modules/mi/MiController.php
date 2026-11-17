<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/core/EnlacePersonal.php';
require_once BASE_PATH . '/core/CambiosTurno.php';
require_once BASE_PATH . '/core/CambiosTurnoValidacion.php';
require_once BASE_PATH . '/modules/mi/MiModel.php';

/**
 * MiController — La página personal de cada integrante, sin cuenta ni
 * contraseña: se entra con el enlace que la coordinación le manda por WhatsApp
 * (core/EnlacePersonal.php).
 *
 * Una pestaña por cada pastoral en la que está marcada en su ficha. En todas
 * ve quién coordina y cuándo se reúnen, los avisos internos, los próximos
 * eventos, el tablero y los documentos de su pastoral (MiModel), lo mismo que
 * vería con una cuenta de Consulta. En MESC y Proclamadores, si está activa en
 * la lista, además sus próximos turnos y el rol del mes, y pide cambios de
 * turno: quedan como solicitud pendiente, exactamente igual que si los pidiera
 * con una cuenta, y los aprueba o rechaza la coordinación desde el panel. No
 * administra nada.
 *
 *   /mi/<token>  abre el enlace: el teléfono lo recuerda (cookie) y pasa a /mi,
 *                para que el token no se quede a la vista en la barra de
 *                direcciones —ni en una captura de pantalla—
 *   /mi          la página; ?pastoral=<slug> elige la pestaña
 *   /mi?accion=turnos&pastoral=…&fecha=…   (JSON) los turnos de un día, para el formulario
 *   POST cambio_guardar, solicitud_retirar, olvidar
 *
 * No abre sesión para mostrar la página: la sesión del sitio usa una cookie
 * SameSite=Strict que no viaja cuando se toca un enlace desde WhatsApp, y abrir
 * una nueva ahí le cerraría la suya a quien tenga el panel abierto en el mismo
 * teléfono. Los formularios llevan una firma derivada del token en vez del
 * token CSRF de la sesión; la sesión solo se usa, si ya existe o al enviar un
 * formulario, para el mensaje de «listo» después de redirigir.
 *
 * Vista previa: si quien abre el enlace tiene sesión en el panel y no es la
 * dueña del enlace (la coordinación revisando qué le llegó a alguien), se
 * muestra la página sin efectos: no se recuerda en ese navegador, no se marcan
 * las respuestas como vistas, no cuenta como «lo abrió» y no se puede pedir
 * nada a nombre de esa persona.
 */
class MiController extends ControllerPublico
{
    use CambiosTurnoValidacion;

    /** Cuántos días hacia adelante se listan en «Tus próximos turnos». */
    private const DIAS_PROXIMOS = 60;

    /**
     * Quien abre el enlace sin ser una persona: WhatsApp y las demás apps lo
     * visitan solas para armar la vista previa del mensaje. Esa visita no debe
     * contar como «lo abrió» ni recibir la página de nadie.
     */
    private const BOTS = '/WhatsApp|facebookexternalhit|Facebot|TelegramBot|Twitterbot|Slackbot|Discordbot|LinkedInBot|SkypeUriPreview|bot\b|crawler|spider|preview/i';

    private EnlacePersonal $enlaces;

    public function __construct()
    {
        parent::__construct();
        $this->enlaces = new EnlacePersonal();
    }

    /** /mi/<token>: lo que abre el enlace. */
    public function ver(): void
    {
        $token = strtolower(trim((string) ($_GET['slug'] ?? '')));

        if (preg_match(self::BOTS, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            $this->renderMi('mi/sin_enlace', ['motivo' => 'vista_previa']);
            return;
        }

        $persona = $this->enlaces->personaPorToken($token);
        if (!$persona) {
            http_response_code(404);
            $this->renderMi('mi/sin_enlace', ['motivo' => 'no_vale']);
            return;
        }

        if ($this->esVistaPrevia($persona)) {
            $this->pagina($persona, $token, true);
            return;
        }

        $this->recordar($token);
        $this->redirect(url_publica('mi'));
    }

    /** /mi: la página, con el enlace que recuerda este teléfono. */
    public function index(): void
    {
        [$persona, $token] = $this->personaDeEsteTelefono();
        if (!$persona) {
            $motivo = $token !== null ? 'no_vale' : ($this->getInt('olvidado') ? 'olvidado' : 'sin_enlace');
            $this->renderMi('mi/sin_enlace', ['motivo' => $motivo]);
            return;
        }
        $vistaPrevia = $this->esVistaPrevia($persona);
        if (!$vistaPrevia) {
            // Un año más cada vez que entra: el enlace se olvida solo si no se usa.
            $this->recordar($token);
        }
        $this->pagina($persona, $token, $vistaPrevia);
    }

    /** JSON: los turnos de un día, con quiénes están, para el formulario de cambio. */
    public function turnos(): void
    {
        [$persona] = $this->personaDeEsteTelefono();
        $seccion = $persona ? ($this->secciones($persona)[$this->getStr('pastoral')]['turnos'] ?? null) : null;
        if (!$seccion) {
            $this->json(['turnos' => []], 403);
        }

        $fecha = $this->getStr('fecha');
        $dt    = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            $this->json(['turnos' => []], 400);
        }
        if ($fecha < date('Y-m-d')) {
            $this->json(['turnos' => [], 'aviso' => 'Esa fecha ya pasó']);
        }

        $this->json(['turnos' => CambiosTurno::paraFormulario(
            $seccion['modelo']->turnosEntre($seccion['pastoral_id'], $fecha, $fecha)
        )]);
    }

    public function cambioGuardar(): void
    {
        [$persona, $seccion, $clave] = $this->envioValido();
        if ($seccion === null) {
            return;
        }
        $modelo     = $seccion['modelo'];
        $pastoralId = $seccion['pastoral_id'];
        $cfg        = $seccion['cfg'];

        [$error, $movimientos, $turnoA] = $this->cambioDelFormulario($modelo, $pastoralId, $cfg, $modelo->personasDe($pastoralId));
        $error ??= self::errorSiNoLoIncluye($movimientos, $seccion['yo']);

        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect(url_publica('mi', array_filter([
                'pastoral' => $clave,
                'turno'    => $turnoA && $turnoA['fecha'] >= date('Y-m-d') ? (int) $turnoA['id'] : null,
            ])) . '#pedir');
            return;
        }

        $notas = mb_substr($this->postStr('notas'), 0, 255) ?: null;
        $id    = $modelo->registrar($pastoralId, $this->postStr('tipo'), $movimientos, $notas, null, 'pendiente', (int) $persona['id']);

        $cambio = $modelo->conDetalle($id, $pastoralId);
        $this->auditoria('solicitar', $cfg['tablas']['cambios'], $id, mb_substr(
            'Enlace personal de ' . $persona['nombre'] . ' — ' . ($cambio ? CambiosTurno::resumen($cambio) : ''), 0, 255
        ));
        Session::flash('success', 'Listo: tu solicitud le llegó a la coordinación de ' . $cfg['titulo']
            . '. Mientras la revisan, el rol no cambia; la respuesta la verás aquí mismo.');
        $this->redirect(url_publica('mi', ['pastoral' => $clave]) . '#solicitudes');
    }

    /** Quien pidió retira su solicitud mientras nadie la ha resuelto. */
    public function solicitudRetirar(): void
    {
        [$persona, $seccion, $clave] = $this->envioValido();
        if ($seccion === null) {
            return;
        }
        $modelo = $seccion['modelo'];
        $quien  = $this->solicitante($persona);
        $cambio = $modelo->conDetalle($this->postInt('id'), $seccion['pastoral_id']);

        if (!$cambio || !CambiosTurno::esDe($cambio, $quien) || $cambio['estado'] !== 'pendiente') {
            Session::flash('error', 'Esa solicitud ya no se puede retirar: la coordinación ya la revisó.');
        } elseif (CambiosTurno::yaPaso($cambio)) {
            Session::flash('error', 'Esa solicitud ya no se puede retirar: el turno ya pasó. La coordinación la cerrará.');
        } elseif ($modelo->retirarSolicitud((int) $cambio['id'], $quien)) {
            $this->auditoria('retirar', $seccion['cfg']['tablas']['cambios'], (int) $cambio['id'], mb_substr(
                'Enlace personal de ' . $persona['nombre'] . ' — ' . CambiosTurno::resumen($cambio), 0, 255
            ));
            Session::flash('success', 'Retiraste tu solicitud. Queda en tu lista como retirada.');
        }
        $this->redirect(url_publica('mi', ['pastoral' => $clave]) . '#solicitudes');
    }

    /** «Olvidar este teléfono»: para quien abrió su enlace en un teléfono prestado. */
    public function olvidar(): void
    {
        [$persona, $token] = $this->personaDeEsteTelefono();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $persona
            && hash_equals(self::firma($token), (string) ($_POST['_mi'] ?? ''))) {
            $this->recordar(null);
        }
        $this->redirect(url_publica('mi', ['olvidado' => 1]));
    }

    // ── La página ────────────────────────────────────────────────────────

    private function pagina(array $persona, string $token, bool $vistaPrevia): void
    {
        $secciones = $this->secciones($persona);
        $quien     = $this->solicitante($persona);
        $hoy       = date('Y-m-d');
        $conTurnos = array_filter(array_column($secciones, 'turnos', 'slug'));

        // Las respuestas de la coordinación que todavía no ha visto, de todas
        // sus listas con turnos: van arriba, antes que nada.
        $novedades = [];
        foreach ($conTurnos as $slug => $t) {
            foreach ($t['modelo']->respuestasNoVistas($quien, $t['pastoral_id']) as $cambio) {
                $novedades[] = $cambio + ['seccion' => $slug];
            }
        }

        $clave = $this->getStr('pastoral');
        if (!isset($secciones[$clave])) {
            $clave = array_key_first($secciones);
        }

        // En la vista previa la dirección lleva el token (no hay cookie): los
        // enlaces de la página tienen que conservarlo.
        $urlPagina = static fn (array $params = []): string => url_publica('mi', $vistaPrevia ? ['slug' => $token] + $params : $params);

        $datos = [
            'persona'     => $persona,
            'secciones'   => $secciones,
            'seccion'     => $clave,
            'novedades'   => $novedades,
            'vistaPrevia' => $vistaPrevia,
            'hoy'         => $hoy,
            'urlPagina'   => $urlPagina,
            'firma'       => self::firma($token),
            'turnos'      => null,
        ];

        if ($clave !== null) {
            $pastoralId = (int) $secciones[$clave]['pastoral']['id'];
            $mi         = new MiModel();
            $datos = array_merge($datos, [
                'pastoral'   => $secciones[$clave]['pastoral'],
                'avisos'     => $mi->avisos([$pastoralId]),
                'eventos'    => $mi->eventos($pastoralId),
                'tablero'    => $mi->tablero($pastoralId),
                'documentos' => $mi->documentos($pastoralId),
            ]);
            if ($secciones[$clave]['turnos']) {
                $datos = array_merge($datos, $this->datosDeTurnos($secciones[$clave]['turnos'], $clave, $quien, $hoy));
            }
        }

        $this->renderMi('mi/pagina', $datos);

        if (!$vistaPrevia) {
            foreach ($conTurnos as $t) {
                $t['modelo']->marcarRespuestasVistas($quien, $t['pastoral_id']);
            }
            $this->enlaces->registrarUso((int) $persona['id']);
        }
    }

    /** Lo de la pestaña de MESC o Proclamadores: sus turnos, sus solicitudes, el rol del mes y el formulario. */
    private function datosDeTurnos(array $t, string $slug, array $quien, string $hoy): array
    {
        $modelo     = $t['modelo'];
        $pastoralId = $t['pastoral_id'];

        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            [$anio, $mes] = [(int) date('Y'), (int) date('n')];
        }
        $inicioMes = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));

        $turnoPedido = $this->getInt('turno') > 0 ? $modelo->turnoConRol($this->getInt('turno'), $pastoralId) : null;
        if ($turnoPedido && $turnoPedido['fecha'] < $hoy) {
            $turnoPedido = null;
        }

        return [
            'turnos'       => $t,
            'cfg'          => $t['cfg'],
            'yo'           => $t['yo'],
            'misTurnos'    => $modelo->turnosDePersona($pastoralId, (int) $t['yo']['id'], $hoy,
                                  date('Y-m-d', strtotime('+' . self::DIAS_PROXIMOS . ' days'))),
            'diasProximos' => self::DIAS_PROXIMOS,
            'solicitudes'  => $modelo->solicitudesDe($quien, $pastoralId, 10),
            'rol'          => $modelo->turnosEntre($pastoralId, $inicioMes->format('Y-m-d'), $inicioMes->format('Y-m-t')),
            'inicioMes'    => $inicioMes,
            'personas'     => $modelo->personasDe($pastoralId),
            'turnoPedido'  => $turnoPedido,
            'urlTurnosDia' => url_publica('mi', ['accion' => 'turnos', 'pastoral' => $slug]),
        ];
    }

    /**
     * Sus pastorales: las activas en las que está marcada en su ficha, una
     * pestaña cada una, por slug. Si la quitan de una, esa pestaña desaparece
     * sola.
     *
     * En MESC y Proclamadores, `turnos` trae además su ficha en la lista del
     * módulo, si está activa ahí: sin eso ve la pastoral, pero no turnos. Esas
     * van primero, que es lo que más se consulta; las demás, por nombre.
     *
     * @return array<string, array{slug: string, pastoral: array, titulo: string,
     *               turnos: ?array{clave: string, cfg: array, modelo: CambiosTurno, pastoral_id: int, yo: array}}>
     */
    private function secciones(array $persona): array
    {
        $secciones = [];
        foreach ((new MiModel())->pastoralesDe((int) $persona['id']) as $pastoral) {
            $seccion = ['slug' => $pastoral['slug'], 'pastoral' => $pastoral, 'titulo' => $pastoral['nombre'], 'turnos' => null];
            foreach (CambiosTurno::MODULOS as $clave => $cfg) {
                if ($cfg['slug'] !== $pastoral['slug']) {
                    continue;
                }
                $seccion['titulo'] = $cfg['titulo'];
                $modelo = new CambiosTurno($cfg['tablas']);
                $yo     = $modelo->personaDelModulo((int) $pastoral['id'], (int) $persona['id']);
                if ($yo && $yo['activo']) {
                    $seccion['turnos'] = [
                        'clave' => $clave, 'cfg' => $cfg, 'modelo' => $modelo,
                        'pastoral_id' => (int) $pastoral['id'], 'yo' => $yo,
                    ];
                }
            }
            $secciones[$pastoral['slug']] = $seccion;
        }
        // uasort es estable: entre las que empatan se queda el orden por nombre.
        uasort($secciones, static fn (array $a, array $b): int => ($b['turnos'] !== null) <=> ($a['turnos'] !== null));
        return $secciones;
    }

    // ── Apoyo ────────────────────────────────────────────────────────────

    /**
     * Comprobaciones de todo POST: el enlace de este teléfono, la firma del
     * formulario, que no sea una vista previa y que siga en esa lista.
     *
     * @return array{0: ?array, 1: ?array, 2: ?string} persona, los turnos de esa
     *         pastoral (ver secciones()) y su slug; turnos null = ya se respondió
     */
    private function envioValido(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_publica('mi'));
            return [null, null, null];
        }
        [$persona, $token] = $this->personaDeEsteTelefono();
        if (!$persona || !hash_equals(self::firma($token), (string) ($_POST['_mi'] ?? ''))) {
            http_response_code(403);
            exit('Tu enlace ya no es válido en este teléfono. Vuelve a abrir el enlace que te mandó la coordinación.');
        }
        if ($this->esVistaPrevia($persona)) {
            Session::flash('error', 'Es una vista previa: las solicitudes las envía cada quien desde su propio teléfono.');
            $this->redirect(url_publica('mi'));
            return [null, null, null];
        }
        $clave   = $this->postStr('pastoral');
        $seccion = $this->secciones($persona)[$clave]['turnos'] ?? null;
        if ($seccion === null) {
            Session::flash('error', 'Ya no apareces en esa lista. Si es un error, pregúntale a tu coordinación.');
            $this->redirect(url_publica('mi'));
            return [null, null, null];
        }
        return [$persona, $seccion, $clave];
    }

    /** @return array{0: ?array, 1: ?string} la persona y el token que recuerda este teléfono; token null si no recuerda ninguno */
    private function personaDeEsteTelefono(): array
    {
        $token = strtolower(trim((string) ($_COOKIE[EnlacePersonal::COOKIE] ?? '')));
        if ($token === '') {
            return [null, null];
        }
        return [$this->enlaces->personaPorToken($token), $token];
    }

    /** La persona y su cuenta, si tiene: lo que pidió con cualquiera de las dos es suyo. */
    private function solicitante(array $persona): array
    {
        return ['persona' => (int) $persona['id'], 'usuario' => $this->enlaces->cuentaDePersona((int) $persona['id'])];
    }

    /**
     * ¿Lo está viendo alguien del panel que no es la dueña del enlace? La
     * coordinación revisando qué le llegó a alguien, o «Usar como…».
     */
    private function esVistaPrevia(array $persona): bool
    {
        if (!Auth::estaAutenticado()) {
            return false;
        }
        return Auth::estaImpersonando()
            || $this->enlaces->cuentaDePersona((int) $persona['id']) !== (int) Auth::usuario()['id'];
    }

    /** Guarda el enlace en este teléfono, o lo olvida con null. */
    private function recordar(?string $token): void
    {
        setcookie(EnlacePersonal::COOKIE, $token ?? '', [
            'expires'  => $token === null ? time() - 3600 : time() + 365 * 86400,
            'path'     => rtrim(APP_URL, '/') . '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Lo que va en el campo oculto `_mi` de cada formulario: se deriva del
     * token, así que solo puede armarlo quien tiene el enlace. Hace el papel
     * del token CSRF del panel sin necesitar sesión.
     */
    private static function firma(string $token): string
    {
        return hash_hmac('sha256', 'formulario-mi', $token);
    }

    private function renderMi(string $vista, array $datos): void
    {
        $this->render($vista, $datos + [
            'metaTitulo' => 'Tu página',
            'sinIndexar' => true,
        ]);
    }

    /** Página personal: nunca en caché, ni del navegador ni de nadie en medio. */
    protected function noCache(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private, max-age=0');
            header('X-Robots-Tag: noindex, nofollow');
        }
    }
}
