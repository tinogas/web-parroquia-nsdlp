<?php
require_once BASE_PATH . '/core/CambiosTurno.php';

/**
 * Pantalla «Cambios de turno» de MESC y de Proclamadores: ver, registrar y
 * deshacer las permutas, coberturas y refuerzos que se acordaron después de
 * armar el rol, y el circuito de solicitudes de los laicos. Las acciones son
 * las mismas en los dos módulos; lo único que cambia es el vocabulario, los
 * permisos y las tablas, que cada controlador declara en cambiosConfig().
 *
 * Quién hace qué:
 *   <modulo>.editar     (administración y coordinación de la pastoral)
 *                       registra el cambio directo, y aprueba o rechaza las
 *                       solicitudes; la campanita le avisa de las pendientes.
 *   <modulo>.solicitar  (el laico, rol Consulta) llena el mismo formulario,
 *                       pero lo que envía queda como solicitud pendiente, debe
 *                       incluirlo a él, y no toca el calendario hasta que se
 *                       aprueba. La campanita le avisa de la respuesta.
 *
 * El controlador que lo usa debe tener además pastoralIdOFallar() y
 * nombreMes() —ya los tienen los dos— y personasDelModulo(), que devuelve
 * todas las personas de la pastoral (activas e inactivas) con id, nombre y
 * activo.
 *
 * Rutas: <modulo>/cambios, cambios_turnos (JSON), cambio_guardar,
 * cambio_eliminar, solicitud_aprobar, solicitud_rechazar, solicitud_cancelar.
 */
trait CambiosTurnoAcciones
{
    /**
     * @return array{modulo: string, tablas: array, singular: string, plural: string,
     *               titulo: string}
     */
    abstract protected function cambiosConfig(): array;

    abstract protected function personasDelModulo(int $pastoralId): array;

    public function cambios(): void
    {
        $cfg = $this->cambiosConfig();
        $this->requirePermiso($cfg['modulo'] . '.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $modelo    = new CambiosTurno($cfg['tablas']);
        $usuarioId = (int) Auth::usuario()['id'];

        $puedeRegistrar = Auth::tienePermiso($cfg['modulo'] . '.editar');
        $puedeSolicitar = !$puedeRegistrar && Auth::tienePermiso($cfg['modulo'] . '.solicitar');

        // Un turno pedido (?turno=id, desde su ficha) queda preseleccionado en
        // el formulario y fija el filtro en su mes.
        $turnoPedido = $this->getInt('turno') > 0 ? $modelo->turnoConRol($this->getInt('turno'), $pastoralId) : null;
        if ($turnoPedido && $turnoPedido['fecha'] < date('Y-m-d')) {
            Session::flash('error', 'Ese turno ya pasó: lo que ya pasó no se mueve.');
            $turnoPedido = null;
        }
        $filtro = $turnoPedido
            ? $this->filtroFecha((int) substr($turnoPedido['fecha'], 0, 4), (int) substr($turnoPedido['fecha'], 5, 2), 0)
            : $this->filtroFecha($this->getInt('anio', (int) date('Y')), $this->getInt('mes', (int) date('n')), $this->getInt('dia'));

        // Quien coordina ve el historial completo —lo registrado directo y las
        // solicitudes aprobadas y rechazadas, con quién pidió y quién
        // resolvió—; el laico, solo lo vigente en el calendario (lo suyo lo ve
        // en «Mis solicitudes»).
        $filtro['mostrar'] = $puedeRegistrar ? $this->historialPedido($this->getStr('mostrar')) : 'vigentes';

        $miPersona     = null;
        $misSolicitudes = [];
        if ($puedeSolicitar) {
            $miPersona      = $modelo->personaDelModulo($pastoralId, $this->personaIdDeCuenta($usuarioId));
            $misSolicitudes = $modelo->solicitudesDe($usuarioId, $pastoralId);
            // Abrir la pantalla es enterarse: las respuestas salen de la
            // campanita. Solo con sesión de verdad, no durante «Usar como…»:
            // marcaría como vista una respuesta que esa persona no ha visto
            // —el mismo criterio que AvisoController::ver() con los avisos—.
            if (!Auth::estaImpersonando()) {
                $modelo->marcarRespuestasVistas($usuarioId, $pastoralId);
            }
        }

        $this->render($cfg['modulo'] . '/cambios', [
            'titulo'         => 'Cambios de turno',
            'cfg'            => $cfg,
            'filtro'         => $filtro,
            'aniosFiltro'    => range((int) date('Y') - 2, (int) date('Y') + 1),
            'cambios'        => $modelo->cambiosEntre($pastoralId, $filtro['desde'], $filtro['hasta'], $filtro['mostrar']),
            'conteo'         => $puedeRegistrar ? $modelo->conteoHistorial($pastoralId, $filtro['desde'], $filtro['hasta']) : null,
            'pendientes'     => $puedeRegistrar ? $modelo->pendientes($pastoralId) : [],
            'misSolicitudes' => $misSolicitudes,
            'miPersona'      => $miPersona,
            'puedeRegistrar' => $puedeRegistrar,
            'puedeSolicitar' => $puedeSolicitar,
            'personas'       => $this->personasDelModulo($pastoralId),
            'turnoPedido'    => $turnoPedido,
            'hoy'            => date('Y-m-d'),
            'urlTurnosDia'   => url_admin($cfg['modulo'], 'cambios_turnos'),
            'urlCalendario'  => url_admin($cfg['modulo'], 'turnos', [
                'anio' => $filtro['anio'], 'mes' => $filtro['mes'] ?: (int) date('n'),
            ]),
        ]);
    }

    /**
     * Turnos de un día, en JSON, para el formulario: se elige la fecha y solo
     * se ofrecen los turnos de ese día, en vez de una lista con meses enteros
     * que había que recorrer. Cada turno trae su rol real, para que el
     * formulario muestre a quién se puede marcar.
     */
    public function cambiosTurnos(): void
    {
        $cfg = $this->cambiosConfig();
        $this->requirePermiso($cfg['modulo'] . '.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $fecha = $this->getStr('fecha');
        $dt    = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            $this->json(['turnos' => []], 400);
        }
        // Lo que ya pasó no se mueve: ni se ofrecen sus turnos.
        if ($fecha < date('Y-m-d')) {
            $this->json(['turnos' => [], 'aviso' => 'Esa fecha ya pasó']);
        }

        $turnos = (new CambiosTurno($cfg['tablas']))->turnosEntre($pastoralId, $fecha, $fecha);
        $this->json(['turnos' => array_map(static fn (array $t): array => [
            'id'       => (int) $t['id'],
            'etiqueta' => trim(($t['hora'] ? substr((string) $t['hora'], 0, 5) . ' ' : '') . $t['descripcion'])
                        . ' — ' . ($t['nombres_actuales'] ?: 'sin asignar'),
            'rol'      => array_map('intval', array_keys($t['actuales'])),
        ], $turnos)]);
    }

    public function cambioGuardar(): void
    {
        $cfg = $this->cambiosConfig();
        $puedeRegistrar = Auth::tienePermiso($cfg['modulo'] . '.editar');
        if (!$puedeRegistrar) {
            $this->requirePermiso($cfg['modulo'] . '.solicitar');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin($cfg['modulo'], 'cambios'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $modelo    = new CambiosTurno($cfg['tablas']);
        $usuarioId = (int) Auth::usuario()['id'];
        $tipo      = $this->postStr('tipo');
        $turnoA    = $this->postInt('turno_a') > 0 ? $modelo->turnoConRol($this->postInt('turno_a'), $pastoralId) : null;

        $volver = $turnoA
            ? url_admin($cfg['modulo'], 'cambios', ['turno' => (int) $turnoA['id']])
            : url_admin($cfg['modulo'], 'cambios');

        $error = null;
        $movimientos = [];

        if (!isset(CambiosTurno::TIPOS[$tipo])) {
            $error = 'Elige qué tipo de cambio es.';
        } elseif (!$turnoA) {
            $error = 'Elige el turno.';
        } elseif ($turnoA['fecha'] < date('Y-m-d')) {
            $error = 'El turno del ' . CambiosTurno::turnoCorto($turnoA) . ' ya pasó: solo se registran cambios de hoy en adelante.';
        } elseif ($tipo === 'permuta') {
            [$error, $movimientos] = $this->validarPermuta($modelo, $pastoralId, $turnoA, $cfg);
        } else {
            [$error, $movimientos] = $this->validarEntrada($pastoralId, $turnoA, $tipo, $cfg);
        }

        // El laico solo pide cambios que lo incluyen: él deja un turno, cubre
        // a alguien o se suma. Pedir por otros es cosa de la coordinación.
        if ($error === null && !$puedeRegistrar) {
            $yo = $modelo->personaDelModulo($pastoralId, $this->personaIdDeCuenta($usuarioId));
            if (!$yo) {
                $error = "Tu cuenta no está ligada a tu ficha de {$cfg['singular']}. Pídele a la coordinación que la ligue.";
            } else {
                $involucra = false;
                foreach ($movimientos as $mov) {
                    $involucra = $involucra || (int) $mov['sale_id'] === (int) $yo['id'] || (int) $mov['entra_id'] === (int) $yo['id'];
                }
                if (!$involucra) {
                    $error = 'La solicitud debe incluirte a ti, ' . $yo['nombre'] . ': que dejes un turno, cubras a alguien o te sumes a uno.';
                }
            }
        }

        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect($volver);
            return;
        }

        $notas  = mb_substr($this->postStr('notas'), 0, 255) ?: null;
        $estado = $puedeRegistrar ? 'aprobado' : 'pendiente';
        $id     = $modelo->registrar($pastoralId, $tipo, $movimientos, $notas, $usuarioId, $estado);

        $cambio  = $modelo->conDetalle($id, $pastoralId);
        $resumen = $cambio ? CambiosTurno::resumen($cambio) : CambiosTurno::TIPOS[$tipo];

        if ($puedeRegistrar) {
            $this->auditoria('crear', $cfg['tablas']['cambios'], $id, $resumen);
            Session::flash('success', 'Cambio registrado. ' . $resumen);
        } else {
            $this->auditoria('solicitar', $cfg['tablas']['cambios'], $id, $resumen);
            Session::flash('success', 'Solicitud enviada a la coordinación de ' . $cfg['titulo']
                . '. Te avisaremos en la campanita cuando la revisen. ' . $resumen);
        }

        $this->redirect(url_admin($cfg['modulo'], 'cambios', [
            'anio' => (int) substr($turnoA['fecha'], 0, 4),
            'mes'  => (int) substr($turnoA['fecha'], 5, 2),
        ]));
    }

    public function cambioEliminar(): void
    {
        $cfg = $this->cambiosConfig();
        $this->requirePermiso($cfg['modulo'] . '.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin($cfg['modulo'], 'cambios'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $modelo = new CambiosTurno($cfg['tablas']);
        $id     = $this->postInt('id');
        $cambio = $modelo->conDetalle($id, $pastoralId);

        // No se borra: queda «deshecho» en la bitácora, con quién, cuándo y
        // por qué, y si fue una solicitud se le avisa a quien la pidió.
        if (!$cambio || $cambio['estado'] !== 'aprobado') {
            Session::flash('error', 'Ese cambio ya no está aplicado.');
        } elseif (CambiosTurno::yaPaso($cambio)) {
            Session::flash('error', 'Ese cambio ya no se puede deshacer: el turno ya pasó.');
        } elseif ($modelo->hayCambiosPosteriores($cambio)) {
            Session::flash('error', 'Hay un cambio registrado después de este en el mismo turno. Deshaz primero el más reciente.');
        } else {
            $motivo = mb_substr($this->postStr('motivo'), 0, 255) ?: null;
            $modelo->deshacer($id, (int) Auth::usuario()['id'], $motivo);
            $this->auditoria('deshacer', $cfg['tablas']['cambios'], $id,
                CambiosTurno::resumen($cambio) . ($motivo ? ' — ' . $motivo : ''));
            $aviso = $cambio['resuelto_at'] !== null
                ? ' ' . ($cambio['autor'] ?? 'Quien lo pidió') . ' lo verá en su campanita.'
                : '';
            Session::flash('success', 'Cambio deshecho: el turno vuelve a como estaba antes de él y queda en el historial.' . $aviso);
        }

        $this->redirect($this->urlVolverFiltro($cfg));
    }

    // ── Solicitudes ──────────────────────────────────────────────────────

    public function solicitudAprobar(): void
    {
        [$cfg, $modelo, $pastoralId, $cambio] = $this->solicitudPendientePedida();
        if ($cambio === null) {
            return;
        }

        // Con el rol de hoy, no el del día en que se pidió.
        $conflicto = $modelo->conflicto($cambio, $pastoralId);
        if ($conflicto !== null) {
            Session::flash('error', 'No se puede aprobar: ' . $conflicto
                . ' Recházala con ese motivo para que la vuelvan a pedir.');
            $this->redirect($this->urlVolverFiltro($cfg));
            return;
        }

        if ($modelo->resolver((int) $cambio['id'], 'aprobado', (int) Auth::usuario()['id'])) {
            $this->auditoria('aprobar', $cfg['tablas']['cambios'], (int) $cambio['id'], CambiosTurno::resumen($cambio));
            Session::flash('success', 'Solicitud aprobada: el cambio ya se ve en el calendario.');
        } else {
            Session::flash('error', 'Esa solicitud ya no estaba pendiente.');
        }
        $this->redirect($this->urlVolverFiltro($cfg));
    }

    public function solicitudRechazar(): void
    {
        [$cfg, $modelo, , $cambio] = $this->solicitudPendientePedida();
        if ($cambio === null) {
            return;
        }

        $motivo = mb_substr($this->postStr('motivo'), 0, 255) ?: null;
        if ($modelo->resolver((int) $cambio['id'], 'rechazado', (int) Auth::usuario()['id'], $motivo)) {
            $this->auditoria('rechazar', $cfg['tablas']['cambios'], (int) $cambio['id'], CambiosTurno::resumen($cambio));
            Session::flash('success', 'Solicitud rechazada. ' . ($cambio['autor'] ?? 'Quien la pidió') . ' lo verá en su campanita.');
        } else {
            Session::flash('error', 'Esa solicitud ya no estaba pendiente.');
        }
        $this->redirect($this->urlVolverFiltro($cfg));
    }

    /** Quien pidió retira su solicitud mientras nadie la ha resuelto. */
    public function solicitudCancelar(): void
    {
        $cfg = $this->cambiosConfig();
        $this->requirePermiso($cfg['modulo'] . '.solicitar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin($cfg['modulo'], 'cambios'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $modelo    = new CambiosTurno($cfg['tablas']);
        $usuarioId = (int) Auth::usuario()['id'];
        $cambio    = $modelo->conDetalle($this->postInt('id'), $pastoralId);

        if (!$cambio || (int) $cambio['usuario_id'] !== $usuarioId || $cambio['estado'] !== 'pendiente') {
            Session::flash('error', 'Esa solicitud ya no se puede retirar: la coordinación ya la revisó.');
        } elseif (CambiosTurno::yaPaso($cambio)) {
            Session::flash('error', 'Esa solicitud ya no se puede retirar: el turno ya pasó. La coordinación la cerrará.');
        } elseif ($modelo->retirarSolicitud((int) $cambio['id'], $usuarioId)) {
            $this->auditoria('retirar', $cfg['tablas']['cambios'], (int) $cambio['id'], CambiosTurno::resumen($cambio));
            Session::flash('success', 'Retiraste tu solicitud. Queda en tu historial como retirada.');
        }
        $this->redirect($this->urlVolverFiltro($cfg));
    }

    // ── Apoyo ────────────────────────────────────────────────────────────

    /**
     * Comprobaciones comunes de aprobar y rechazar: permiso de coordinación,
     * POST con CSRF, la pastoral del módulo y una solicitud suya pendiente.
     *
     * @return array{0: array, 1: ?CambiosTurno, 2: ?int, 3: ?array} cambio null = ya se redirigió
     */
    private function solicitudPendientePedida(): array
    {
        $cfg = $this->cambiosConfig();
        $this->requirePermiso($cfg['modulo'] . '.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin($cfg['modulo'], 'cambios'));
            return [$cfg, null, null, null];
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return [$cfg, null, null, null];
        }

        $modelo = new CambiosTurno($cfg['tablas']);
        $cambio = $modelo->conDetalle($this->postInt('id'), $pastoralId);
        if (!$cambio || $cambio['estado'] !== 'pendiente') {
            Session::flash('error', 'Esa solicitud ya no estaba pendiente.');
            $this->redirect($this->urlVolverFiltro($cfg));
            return [$cfg, null, null, null];
        }
        return [$cfg, $modelo, $pastoralId, $cambio];
    }

    /**
     * El filtro de la lista de cambios: un año entero, un mes o un solo día.
     * mes = 0 es «todo el año» y dia = 0 «todo el mes»; un día que el mes no
     * tiene (31 de noviembre) se toma como todo el mes.
     *
     * @return array{anio: int, mes: int, dia: int, desde: string, hasta: string, etiqueta: string}
     */
    private function filtroFecha(int $anio, int $mes, int $dia): array
    {
        if ($anio < 2000 || $anio > 2100) { $anio = (int) date('Y'); }
        if ($mes < 0 || $mes > 12)        { $mes  = (int) date('n'); }

        if ($mes === 0) {
            return ['anio' => $anio, 'mes' => 0, 'dia' => 0,
                    'desde' => "{$anio}-01-01", 'hasta' => "{$anio}-12-31", 'etiqueta' => "todo {$anio}"];
        }

        $inicio = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        if ($dia < 1 || $dia > (int) $inicio->format('t')) {
            return ['anio' => $anio, 'mes' => $mes, 'dia' => 0,
                    'desde' => $inicio->format('Y-m-d'), 'hasta' => $inicio->format('Y-m-t'),
                    'etiqueta' => mb_strtolower($this->nombreMes($mes)) . " de {$anio}"];
        }

        $fecha = $inicio->format('Y-m-') . sprintf('%02d', $dia);
        return ['anio' => $anio, 'mes' => $mes, 'dia' => $dia,
                'desde' => $fecha, 'hasta' => $fecha, 'etiqueta' => fecha_con_dia($fecha) . " de {$anio}"];
    }

    /** De vuelta a la lista con el filtro que traía el formulario. */
    private function urlVolverFiltro(array $cfg): string
    {
        $filtro = $this->filtroFecha(
            $this->postInt('anio') ?: (int) date('Y'),
            $this->postInt('mes', (int) date('n')),
            $this->postInt('dia')
        );
        $params = ['anio' => $filtro['anio'], 'mes' => $filtro['mes'], 'dia' => $filtro['dia']];
        $mostrar = $this->historialPedido($this->postStr('mostrar'));
        if ($mostrar !== 'todos') {
            $params['mostrar'] = $mostrar;
        }
        return url_admin($cfg['modulo'], 'cambios', $params);
    }

    /** Qué parte del historial se pidió (CambiosTurno::HISTORIAL); «todos» si no se pidió o no existe. */
    private function historialPedido(string $valor): string
    {
        return isset(CambiosTurno::HISTORIAL[$valor]) ? $valor : 'todos';
    }

    /** La ficha del equipo pastoral ligada a la cuenta (usuarios.persona_id), o null. */
    private function personaIdDeCuenta(int $usuarioId): ?int
    {
        require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
        $cuenta = (new UsuarioModel())->porId($usuarioId);
        return !empty($cuenta['persona_id']) ? (int) $cuenta['persona_id'] : null;
    }

    // ── Validación ───────────────────────────────────────────────────────
    // Todo contra el rol REAL del turno (el original más los cambios ya
    // registrados), nunca contra lo que el formulario dice que hay: el
    // filtrado de las listas en el navegador es solo comodidad.

    /**
     * Permuta de grupo a grupo: los X marcados dejan A y pasan a B; los Y
     * marcados dejan B y pasan a A. Uno por uno, dos por dos, o todos por
     * todos (intercambio de turnos completos); también dos por uno, si así se
     * acordó.
     */
    private function validarPermuta(CambiosTurno $modelo, int $pastoralId, array $turnoA, array $cfg): array
    {
        $turnoB = $this->postInt('turno_b') > 0 ? $modelo->turnoConRol($this->postInt('turno_b'), $pastoralId) : null;
        $xs = $this->idsPedidos('salen_a');
        $ys = $this->idsPedidos('salen_b');

        if (!$turnoB) {
            return ['Elige el segundo turno de la permuta.', []];
        }
        if ($turnoB['fecha'] < date('Y-m-d')) {
            return ['El turno del ' . CambiosTurno::turnoCorto($turnoB) . ' ya pasó: solo se registran cambios de hoy en adelante.', []];
        }
        if ((int) $turnoB['id'] === (int) $turnoA['id']) {
            return ['Una permuta es entre dos turnos distintos. Para cambiar a alguien dentro del mismo turno usa «Cubre a otro».', []];
        }
        if (!$xs) {
            return ["Marca al menos un {$cfg['singular']} del primer turno.", []];
        }
        if (!$ys) {
            return ["Marca al menos un {$cfg['singular']} del segundo turno.", []];
        }
        foreach ($xs as $x) {
            if (!isset($turnoA['actuales'][$x])) {
                return ["Alguno de los marcados ya no está en el primer turno. Vuelve a elegir.", []];
            }
            if (isset($turnoB['actuales'][$x])) {
                return [$turnoA['actuales'][$x] . ' ya está en el segundo turno; no puede pasar a él.', []];
            }
        }
        foreach ($ys as $y) {
            if (!isset($turnoB['actuales'][$y])) {
                return ["Alguno de los marcados ya no está en el segundo turno. Vuelve a elegir.", []];
            }
            if (isset($turnoA['actuales'][$y])) {
                return [$turnoB['actuales'][$y] . ' ya está en el primer turno; no puede pasar a él.', []];
            }
        }

        // Primero todo lo del turno A y luego todo lo del B. Si los grupos no
        // son del mismo tamaño, al que le sobra le toca una fila de solo
        // «sale» de un lado y de solo «entra» del otro.
        $movimientos = [];
        $n = max(count($xs), count($ys));
        for ($i = 0; $i < $n; $i++) {
            $movimientos[] = ['turno_id' => (int) $turnoA['id'], 'sale_id' => $xs[$i] ?? null, 'entra_id' => $ys[$i] ?? null];
        }
        for ($i = 0; $i < $n; $i++) {
            $movimientos[] = ['turno_id' => (int) $turnoB['id'], 'sale_id' => $ys[$i] ?? null, 'entra_id' => $xs[$i] ?? null];
        }
        return [null, $movimientos];
    }

    /** Ids marcados en una lista de casillas del formulario, sin repetir. */
    private function idsPedidos(string $campo): array
    {
        return array_values(array_unique(array_map('intval',
            array_filter((array) ($_POST[$campo] ?? []), 'is_numeric')
        )));
    }

    /** Sustitución (sale X, entra Y) o agregado (solo entra Y), en un solo turno. */
    private function validarEntrada(int $pastoralId, array $turnoA, string $tipo, array $cfg): array
    {
        $entra = $this->postInt('entra');
        $activas = [];
        foreach ($this->personasDelModulo($pastoralId) as $persona) {
            if ($persona['activo']) {
                $activas[(int) $persona['id']] = $persona['nombre'];
            }
        }

        if (!isset($activas[$entra])) {
            return ["Elige qué {$cfg['singular']} entra al turno.", []];
        }
        if (isset($turnoA['actuales'][$entra])) {
            return [$activas[$entra] . ' ya está en ese turno.', []];
        }

        $sale = null;
        if ($tipo === 'sustitucion') {
            $sale = $this->postInt('sale_a');
            if (!isset($turnoA['actuales'][$sale])) {
                return ["Elige a qué {$cfg['singular']} del turno cubre.", []];
            }
        }

        return [null, [['turno_id' => (int) $turnoA['id'], 'sale_id' => $sale, 'entra_id' => $entra]]];
    }
}
