<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/coros/CoroModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

/**
 * CoroController — Exclusivo de la pastoral "Coros": ninguna acción muestra ni
 * acepta otra pastoral, igual que MESC, Catequesis y Proclamadores.
 * pastoralIdOFallar() resuelve esa única pastoral y corta el flujo con un
 * mensaje claro si todavía no existe o si quien mira no la administra.
 *
 * Dos pantallas y nada más. No hay calendario de turnos porque aquí la
 * asignación no es un rol mensual sino permanente —el coro de las 12:00 canta
 * todos los domingos—, y por lo mismo tampoco colores litúrgicos ni hoja
 * imprimible; las actividades y los documentos ya se administran desde el
 * panel básico de la pastoral y duplicar su pantalla es justo lo que se
 * corrigió al sacarlas de Catequesis.
 *
 * La portada es el catálogo de coros, y se recorre por MISA, no por coro: lo
 * que se consulta es "quién canta en la de 12", y una misa dominical sin coro
 * es una respuesta tan válida como cualquier otra —además de la única forma de
 * ofrecer el botón de crearlo donde se echa en falta—.
 */
class CoroController extends Controller
{
    private CoroModel $modelo;

    /**
     * El tablero de actividades y los documentos no son propios de este
     * módulo: viven en `pastoral_tablero` y `pastoral_documentos`, tablas de
     * cualquier pastoral que administra PastoralModel, y son las mismas filas
     * que muestra el panel básico de la pastoral. Catequesis y Proclamadores
     * entran por aquí igual que este módulo: la pantalla se repite en cada uno
     * porque quien coordina Coros no administra Catequesis, pero la tabla es
     * una sola.
     */
    private PastoralModel $pastorales;

    public function __construct()
    {
        $this->modelo     = new CoroModel();
        $this->pastorales = new PastoralModel();
    }

    // ── Coros (portada del módulo) ───────────────────────────────────────

    public function index(): void
    {
        $this->requirePermiso('coros.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('coros/coros_lista', [
            'titulo'     => 'Coros',
            'pastoralId' => $pastoralId,
            'horarios'   => $this->modelo->horariosDominicales(),
            'coros'      => $this->modelo->corosPorHorario($pastoralId),
            'coristas'   => $this->modelo->coristasActivos($pastoralId),
            'integrantes' => $this->integrantesPorCoro($pastoralId),
        ]);
    }

    public function coroGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->coroPorId($id) : null;
        $this->requirePermiso($existente ? 'coros.editar' : 'coros.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        // El horario es la identidad del coro y solo se fija al crearlo: al
        // editar se conserva el de la base, nunca el del POST.
        if ($existente) {
            $horarioId = (int) $existente['horario_id'];
        } else {
            $horarioId = $this->postInt('horario_id');
            $horario   = $horarioId ? $this->modelo->horarioDominicalPorId($horarioId) : null;
            if (!$horario) {
                Session::flash('error', 'Elige una misa de domingo para ese coro.');
                $this->redirect(url_admin('coros'));
                return;
            }
            if ($this->modelo->coroPorHorario($horarioId)) {
                Session::flash('error', 'Esa misa ya tiene coro. Edita el que existe en vez de crear otro.');
                $this->redirect(url_admin('coros'));
                return;
            }
        }

        $datos = [
            'pastoral_id'  => $pastoralId,
            'horario_id'   => $horarioId,
            // Un coro nuevo todavía no tiene integrantes, así que tampoco puede
            // tener encargado: se nombra al editarlo, cuando ya hay de quién.
            'encargado_id' => null,
            'nota'         => $this->postStr('nota') ?: null,
            'activo'       => $this->postBool('activo'),
        ];

        if ($existente) {
            $coristaIds = $this->coristasPedidos($pastoralId);
            $datos['encargado_id'] = $this->encargadoValidado($coristaIds);

            $this->modelo->actualizarCoro($id, $datos);
            $this->modelo->sincronizarCoristasDeCoro($id, $coristaIds);
            $this->auditoria('editar', 'coros', $id, 'Coro de la misa ' . $horarioId);
            Session::flash('success', 'Coro actualizado.');
        } else {
            $id = $this->modelo->crearCoro($datos);
            $this->auditoria('crear', 'coros', $id, 'Coro de la misa ' . $horarioId);
            Session::flash('success', 'Coro creado. Ahora marca sus integrantes.');
        }

        $this->redirect(url_admin('coros'));
    }

    public function coroEliminar(): void
    {
        $this->requirePermiso('coros.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros'));
            return;
        }
        $this->validarCsrf();

        $id   = $this->postInt('id');
        $coro = $this->modelo->coroPorId($id);
        if ($coro) {
            $this->requireAlcancePastoral((int) $coro['pastoral_id']);
            $this->modelo->eliminarCoro($id);
            $this->auditoria('eliminar', 'coros', $id, 'Coro de la misa ' . (int) $coro['horario_id']);
            Session::flash('success', 'Coro eliminado. Quienes cantaban en él siguen en el catálogo.');
        }

        $this->redirect(url_admin('coros'));
    }

    // ── Coristas ─────────────────────────────────────────────────────────

    public function coristas(): void
    {
        $this->requirePermiso('coros.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('coros/coristas_lista', [
            'titulo'      => 'Integrantes',
            'pastoralId'  => $pastoralId,
            'coristas'    => $this->modelo->coristas($pastoralId),
            'personas'    => (new PersonaModel())->paraSelector(),
            'coros'       => $this->corosConEtiqueta($pastoralId),
            'susCoros'    => $this->modelo->corosDeCadaCorista($pastoralId),
        ]);
    }

    public function coristaGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'coristas'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->coristaPorId($id) : null;
        $this->requirePermiso($existente ? 'coros.editar' : 'coros.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        // Quien canta se elige del equipo pastoral; si todavía no está ahí, los
        // campos libres son el respaldo. Mismo patrón que los proclamadores,
        // los catequistas y el responsable de una pastoral. Los datos de la
        // persona se releen de la base, nunca se toman del POST.
        $personaId = $this->postIntONull('persona_id');
        $persona   = $personaId ? (new PersonaModel())->porId($personaId) : null;
        if ($persona) {
            $nombre   = $persona['nombre'];
            $telefono = $persona['telefono'];
            $email    = $persona['email'];
        } else {
            $personaId = null;
            $nombre    = $this->postStr('nombre');
            $telefono  = $this->postStr('telefono') ?: null;
            $email     = $this->postStr('email') ?: null;
        }

        if ($nombre === '') {
            Session::flash('error', 'Quien canta necesita un nombre, o elige a alguien del equipo pastoral.');
            $this->redirect(url_admin('coros', 'coristas'));
            return;
        }
        // Con ficha vinculada el teléfono viene de ella, ya validado al
        // guardarla; aquí solo se revisa lo escrito a mano.
        if (!$persona && !telefono_valido($telefono)) {
            Session::flash('error', 'El teléfono no tiene un formato válido. ' . TELEFONO_FORMATO);
            $this->redirect(url_admin('coros', 'coristas'));
            return;
        }

        // La voz y el instrumento sí son del módulo, no de la ficha: se leen del
        // formulario incluso con persona vinculada, y sincronizarPersonal() no
        // los toca. Texto libre —ver install.sql—, con la cadena vacía
        // guardada como NULL.
        $datos = [
            'pastoral_id' => $pastoralId,
            'persona_id'  => $personaId,
            'nombre'      => $nombre,
            'telefono'    => $telefono,
            'email'       => $email,
            'voz'         => $this->postStr('voz') ?: null,
            'instrumento' => $this->postStr('instrumento') ?: null,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarCorista($id, $datos);
            $this->auditoria('editar', 'coristas', $id, $nombre);
            Session::flash('success', 'Datos actualizados.');
        } else {
            $id = $this->modelo->crearCorista($datos);
            $this->auditoria('crear', 'coristas', $id, $nombre);
            Session::flash('success', 'Agregado al catálogo.');
        }

        $this->modelo->sincronizarCorosDeCorista($id, $this->corosPedidos($pastoralId));

        $this->redirect(url_admin('coros', 'coristas'));
    }

    public function coristaEliminar(): void
    {
        $this->requirePermiso('coros.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'coristas'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $corista = $this->modelo->coristaPorId($id);
        if ($corista) {
            $this->requireAlcancePastoral((int) $corista['pastoral_id']);
            $this->modelo->eliminarCorista($id);
            $this->auditoria('eliminar', 'coristas', $id, $corista['nombre']);
            Session::flash('success', 'Eliminado del catálogo.');
        }

        $this->redirect(url_admin('coros', 'coristas'));
    }

    // ── Actividades (tablero con fechas) ─────────────────────────────────
    // Sobre `pastoral_tablero`, compartida: son las mismas actividades que ve
    // el panel básico de la pastoral, no una segunda lista.

    public function actividades(): void
    {
        $this->requirePermiso('coros.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('coros/actividades_lista', [
            'titulo'      => 'Actividades',
            'pastoralId'  => $pastoralId,
            'actividades' => $this->pastorales->tablero($pastoralId),
        ]);
    }

    public function actividadGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'actividades'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->pastorales->entradaTablero($id) : null;
        $this->requirePermiso($existente ? 'coros.editar' : 'coros.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $titulo      = $this->postStr('titulo');
        $fechaInicio = $this->postStr('fecha_inicio');
        $fechaFin    = $this->postStr('fecha_fin') ?: null;

        if ($titulo === '' || $fechaInicio === '') {
            Session::flash('error', 'La actividad necesita título y fecha de inicio.');
            $this->redirect(url_admin('coros', 'actividades'));
            return;
        }
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            Session::flash('error', 'La fecha de término no puede ser anterior a la de inicio.');
            $this->redirect(url_admin('coros', 'actividades'));
            return;
        }

        $datos = [
            'pastoral_id'  => $pastoralId,
            'titulo'       => $titulo,
            'descripcion'  => trim(strip_tags((string) ($_POST['descripcion'] ?? ''))) ?: null,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'publicado'    => $this->postBool('publicado'),
            'orden'        => $this->postInt('orden'),
        ];

        if ($existente) {
            $this->pastorales->actualizarEntradaTablero($id, $datos);
            $this->auditoria('editar', 'pastoral_tablero', $id, $titulo);
            Session::flash('success', 'Actividad actualizada.');
        } else {
            $id = $this->pastorales->crearEntradaTablero($datos, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'pastoral_tablero', $id, $titulo);
            Session::flash('success', 'Actividad agregada.');
        }

        $this->redirect(url_admin('coros', 'actividades'));
    }

    public function actividadEliminar(): void
    {
        $this->requirePermiso('coros.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'actividades'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $actividad = $this->pastorales->entradaTablero($id);
        if ($actividad) {
            $this->requireAlcancePastoral((int) $actividad['pastoral_id']);
            $this->pastorales->eliminarEntradaTablero($id);
            $this->auditoria('eliminar', 'pastoral_tablero', $id, $actividad['titulo']);
            Session::flash('success', 'Actividad eliminada.');
        }

        $this->redirect(url_admin('coros', 'actividades'));
    }

    // ── Documentos ───────────────────────────────────────────────────────
    // Sobre `pastoral_documentos`, compartida — ver el comentario de
    // $pastorales arriba. Solo alta y baja: para cambiar uno se sube otro.

    public function documentos(): void
    {
        $this->requirePermiso('coros.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('coros/documentos_lista', [
            'titulo'     => 'Documentos',
            'pastoralId' => $pastoralId,
            'documentos' => $this->pastorales->documentos($pastoralId),
        ]);
    }

    public function documentoGuardar(): void
    {
        $this->requirePermiso('coros.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'documentos'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'El documento necesita un título.');
            $this->redirect(url_admin('coros', 'documentos'));
            return;
        }

        try {
            $archivo = Upload::documento('archivo', 'coros', 'documento');
        } catch (RuntimeException $e) {
            Session::flash('error', 'No se pudo subir el documento: ' . $e->getMessage());
            $this->redirect(url_admin('coros', 'documentos'));
            return;
        }
        if (!$archivo) {
            Session::flash('error', 'Elige un archivo PDF para subir.');
            $this->redirect(url_admin('coros', 'documentos'));
            return;
        }

        $id = $this->pastorales->crearDocumento([
            'pastoral_id' => $pastoralId,
            'titulo'      => $titulo,
            'archivo'     => $archivo,
            'orden'       => $this->postInt('orden'),
            'activo'      => 1,
        ], (int) Auth::usuario()['id']);
        $this->auditoria('crear', 'pastoral_documentos', $id, $titulo);
        Session::flash('success', 'Documento agregado.');

        $this->redirect(url_admin('coros', 'documentos'));
    }

    public function documentoEliminar(): void
    {
        $this->requirePermiso('coros.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('coros', 'documentos'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $documento = $this->pastorales->documentoPorId($id);
        if ($documento) {
            $this->requireAlcancePastoral((int) $documento['pastoral_id']);
            Upload::borrar($documento['archivo']);
            $this->pastorales->eliminarDocumento($id);
            $this->auditoria('eliminar', 'pastoral_documentos', $id, $documento['titulo']);
            Session::flash('success', 'Documento eliminado.');
        }

        $this->redirect(url_admin('coros', 'documentos'));
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /**
     * Resuelve la pastoral de Coros, la única que administra este módulo. Si
     * todavía no existe (instalación nueva) o quien mira no tiene alcance
     * sobre ella, corta el flujo con un mensaje claro.
     */
    private function pastoralIdOFallar(): ?int
    {
        $pastoralId = $this->modelo->pastoralId();
        if ($pastoralId === null) {
            Session::flash('error', 'Todavía no existe la pastoral "Coros". Créala primero desde Pastorales.');
            $this->redirect(url_admin('pastorales'));
            return null;
        }
        if (!Auth::puedeSobrePastoral($pastoralId)) {
            Session::flash('error', 'No administras la pastoral de Coros.');
            $this->redirect(url_admin('panel'));
            return null;
        }
        return $pastoralId;
    }

    /**
     * Los coros de la pastoral con el nombre con el que se les llama —el de su
     * misa—, listos para pintar en un checklist o una etiqueta. Indexado por id
     * del coro.
     *
     * Un coro no guarda su nombre: se compone aquí a partir del horario, que es
     * su identidad. Si esa misa cambia de hora, el coro cambia de nombre solo.
     */
    private function corosConEtiqueta(int $pastoralId): array
    {
        $porHorario = $this->modelo->corosPorHorario($pastoralId);

        $etiquetados = [];
        foreach ($this->modelo->horariosDominicales() as $horario) {
            $coro = $porHorario[(int) $horario['id']] ?? null;
            if (!$coro) {
                continue;
            }
            $coro['etiqueta'] = self::etiquetaDeCoro($horario);
            $etiquetados[(int) $coro['id']] = $coro;
        }
        return $etiquetados;
    }

    /**
     * "Domingo 12:00 · Villa Bonita (Misa para Niños)". La nota del horario
     * entra porque es lo que distingue dos misas del mismo lugar en la misma
     * mañana, y sin ella dos coros distintos se leerían casi igual.
     */
    public static function etiquetaDeCoro(array $horario): string
    {
        $texto = hora_corta($horario['hora']);
        if (!empty($horario['centro_nombre'])) {
            $texto .= ' · ' . $horario['centro_nombre'];
        }
        if (!empty($horario['nota'])) {
            $texto .= ' (' . $horario['nota'] . ')';
        }
        return $texto;
    }

    /**
     * Los integrantes de cada coro, indexados por coro_id, para la portada del
     * módulo: la lista que despliega el "+" de cada misa y, de paso, las
     * casillas ya marcadas del formulario. Antes esto se armaba aquí dando la
     * vuelta a corosDeCadaCorista() y solo devolvía ids, que alcanzaban para
     * marcar casillas pero no para escribir una lista con nombres.
     */
    private function integrantesPorCoro(int $pastoralId): array
    {
        return $this->modelo->integrantesDeCadaCoro($pastoralId);
    }

    /**
     * Los coristas marcados en el formulario, cruzados contra los activos de
     * esta pastoral: un id manipulado en el POST no entra. Mismo cruce que
     * ProclamadoresController::turnoGuardar() hace con sus proclamadores.
     */
    private function coristasPedidos(int $pastoralId): array
    {
        $idsValidos = array_map(
            static fn (array $c): int => (int) $c['id'],
            $this->modelo->coristasActivos($pastoralId)
        );

        return array_values(array_intersect(
            array_map('intval', array_filter((array) ($_POST['coristas'] ?? []), 'is_numeric')),
            $idsValidos
        ));
    }

    /** Lo mismo en la otra dirección: los coros marcados en la ficha de un corista. */
    private function corosPedidos(int $pastoralId): array
    {
        $idsValidos = array_map(
            static fn (array $c): int => (int) $c['id'],
            array_values($this->modelo->corosPorHorario($pastoralId))
        );

        return array_values(array_intersect(
            array_map('intval', array_filter((array) ($_POST['coros'] ?? []), 'is_numeric')),
            $idsValidos
        ));
    }

    /**
     * El encargado tiene que ser uno de quienes cantan en ese coro. No lo puede
     * exigir una FK —apunta al catálogo entero, no a este coro—, así que se
     * comprueba aquí contra la lista que se está guardando: si no está en ella,
     * el coro se queda sin encargado en vez de con uno que no le pertenece.
     */
    private function encargadoValidado(array $coristaIds): ?int
    {
        $encargadoId = $this->postIntONull('encargado_id');
        if ($encargadoId === null) {
            return null;
        }
        if (!in_array($encargadoId, $coristaIds, true)) {
            Session::flash('warning', 'El encargado tiene que cantar en ese coro. Se guardó sin encargado.');
            return null;
        }
        return $encargadoId;
    }
}
