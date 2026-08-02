<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

/**
 * Los cursos se administran por pastoral, igual que los eventos: cada una
 * mantiene los suyos y en este listado ve los suyos y los generales, y el
 * administrador puede dejar uno sin pastoral —«general», de la parroquia— o
 * asignarlo a cualquiera. Lo que hay programado en toda la parroquia se
 * consulta en la agenda interna, que no recorta por pastoral.
 *
 * Antes del issue de filtrado por pastoral, `pastoral_id` se tomaba del POST
 * tal cual y ninguna acción comprobaba alcance, porque solo admin y editor
 * llegaban aquí. Al abrirle el módulo a coordinadores y administradores de
 * pastoral eso dejó de ser inocuo, y ahora rige la misma regla que en el resto
 * del sistema: el pastoral_id que decide se lee de la base al editar, nunca del
 * formulario.
 */
class CursoController extends Controller
{
    private CursoModel $modelo;

    public function __construct()
    {
        $this->modelo = new CursoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('cursos.ver');

        $filtro = isset(ESTADOS_PUBLICACION[$this->getStr('filtro')]) ? $this->getStr('filtro') : 'todos';

        // La sede se recorta como siempre; la pastoral ya no, porque leer lo
        // publicado hacia dentro alcanza también a las Comisiones que agrupan
        // las suyas y deja fuera los borradores ajenos. Ver acotarAudiencia().
        $pastorales = $this->pastoralesDelFiltro();
        $centros    = $this->centrosDelFiltro();
        [$filtroPastoral, $idsPastoral] = $this->filtroPastoral($pastorales);
        [$filtroCentro,   $idsCentro]   = $this->filtroCentro($centros);
        [$audiencia, $propias] = $this->acotarAudiencia($idsPastoral);
        $idsCentro   = $this->centrosVisibles($idsCentro);

        $this->render('cursos/lista', [
            'titulo'  => 'Cursos',
            'listado' => $this->modelo->listar(
                max(1, $this->getInt('pagina', 1)),
                $filtro,
                $audiencia,
                $propias,
                $idsCentro
            ),
            'filtro'  => $filtro,
            'pastorales'     => $pastorales,
            'filtroPastoral' => $filtroPastoral,
            'centros'        => $centros,
            'filtroCentro'   => $filtroCentro,
            'tieneAlcance'   => Auth::pastoralesPermitidas() !== [],
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('cursos.crear');

        $this->render('cursos/form', array_merge($this->opcionesPastoral(), $this->opcionesCentro(), [
            'titulo'                    => 'Nuevo curso',
            'curso'                     => null,
            'sesiones'                  => [],
            'instructores'              => (new PersonaModel())->paraSelector(),
            'pastoralIdPreseleccionado' => $this->pastoralIdPreseleccionado(),
            'scriptExtra'               => $this->scriptEditor(),
        ]));
    }

    /**
     * Lectura de solo lectura dentro del panel, hermana de
     * AvisoController::ver(): es lo que abre un miembro de la pastoral cuando
     * le llega un curso interno, y el destino de las miniaturas del panel de
     * inicio. Un curso que todavía no es público no tiene página que enseñar.
     */
    public function ver(): void
    {
        $this->requirePermiso('cursos.ver');

        $curso = $this->modelo->porId($this->getInt('id'));
        if (!$curso) {
            Session::flash('error', 'No encontramos ese curso.');
            $this->redirect(url_admin('cursos'));
            return;
        }

        $pastoralId = $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null;
        if (!$this->puedeLeerInterno($pastoralId, (bool) $curso['publicado_interno'])) {
            Session::flash('error', 'Ese curso no está publicado para tus pastorales.');
            $this->redirect(url_admin('cursos'));
            return;
        }

        $this->render('cursos/ver', [
            'titulo'      => $curso['titulo'],
            'curso'       => $curso,
            'pastoral'    => $pastoralId ? (new PastoralModel())->porId($pastoralId) : null,
            'sesiones'    => $this->modelo->sesiones((int) $curso['id']),
            'puedeEditar' => Auth::tienePermiso('cursos.editar')
                             && Auth::puedeSobrePastoral($pastoralId)
                             && Auth::puedeSobreCentro($curso['centro_id'] !== null ? (int) $curso['centro_id'] : null),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('cursos.editar');

        $curso = $this->modelo->porId($this->getInt('id'));
        if (!$curso) {
            Session::flash('error', 'No encontramos ese curso.');
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->requireAlcanceContenido(
            $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null,
            $curso['centro_id'] !== null ? (int) $curso['centro_id'] : null
        );

        $this->render('cursos/form', array_merge($this->opcionesPastoral(), $this->opcionesCentro(), [
            'titulo'      => $curso['titulo'],
            'curso'       => $curso,
            'sesiones'    => $this->modelo->sesiones((int) $curso['id']),
            'instructores'=> (new PersonaModel())->paraSelector(),
            'scriptExtra' => $this->scriptEditor(),
        ]));
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->porId($id) : null;
        $this->requirePermiso($existente ? 'cursos.editar' : 'cursos.crear');
        if ($existente) {
            $this->requireAlcanceContenido(
                $existente['pastoral_id'] !== null ? (int) $existente['pastoral_id'] : null,
                $existente['centro_id'] !== null ? (int) $existente['centro_id'] : null
            );
        }

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'El curso necesita un título.');
            $this->redirect($id ? url_admin('cursos', 'editar', ['id' => $id]) : url_admin('cursos', 'nuevo'));
            return;
        }

        try {
            $pastoralId = $this->pastoralIdValidado();
            $centroId   = $this->centroIdValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('cursos', 'editar', ['id' => $id]) : url_admin('cursos', 'nuevo'));
            return;
        }

        $slugPedido = $this->postStr('slug');
        $slug = $slugPedido !== ''
            ? Slug::unico($slugPedido, 'cursos', $id ?: null)
            : ($existente ? $existente['slug'] : Slug::unico($titulo, 'cursos'));

        $imagen = $existente['imagen'] ?? null;
        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'cursos', 'curso', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'El curso se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $datos = $this->escalonPublicacion('cursos.publicar', $existente) + [
            'slug'                     => $slug,
            'titulo'                   => $titulo,
            'descripcion'              => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'objetivos'                => $this->postStr('objetivos') ?: null,
            'dirigido_a'               => $this->postStr('dirigido_a') ?: null,
            'imagen'                   => $imagen,
            'modalidad'                => isset(CursoModel::MODALIDADES[$this->postStr('modalidad')]) ? $this->postStr('modalidad') : 'presencial',
            'instructor_id'            => $this->postIntONull('instructor_id'),
            'pastoral_id'              => $pastoralId,
            'centro_id'                => $centroId,
            'cupo'                     => $this->postIntONull('cupo'),
            'aportacion'               => $this->postStr('aportacion') ?: null,
            'fecha_inicio'             => $this->postStr('fecha_inicio') ?: null,
            'fecha_fin'                => $this->postStr('fecha_fin') ?: null,
            'horario'                  => $this->postStr('horario') ?: null,
            'lugar'                    => $this->postStr('lugar') ?: null,
            'inscripciones_abiertas'   => $this->postBool('inscripciones_abiertas'),
            'fecha_cierre_inscripcion' => $this->postStr('fecha_cierre_inscripcion') ?: null,
            'requiere_tutor'           => $this->postBool('requiere_tutor'),
            'orden'                    => $this->postInt('orden'),
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'cursos', $id, $titulo);
            if (!Session::hayFlash()) { Session::flash('success', 'Curso actualizado.'); }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'cursos', $id, $titulo);
            Session::flash('success', 'Curso creado como «'
                . ESTADOS_PUBLICACION[estado_publicacion($datos)] . '».');
        }

        $this->redirect(url_admin('cursos', 'editar', ['id' => $id]));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('cursos.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $curso = $this->modelo->porId($id);
        if ($curso) {
            $this->requireAlcanceContenido(
                $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null,
                $curso['centro_id'] !== null ? (int) $curso['centro_id'] : null
            );
            Upload::borrar($curso['imagen']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'cursos', $id, $curso['titulo']);
            Session::flash('success', 'Curso eliminado, junto con su temario e inscripciones.');
        }

        $this->redirect(url_admin('cursos'));
    }

    // ── Temario ─────────────────────────────────────────────────────────

    public function sesionGuardar(): void
    {
        $this->requirePermiso('cursos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->validarCsrf();

        $cursoId = $this->postInt('curso_id');
        $curso   = $this->modelo->porId($cursoId);
        if (!$curso) {
            Session::flash('error', 'No encontramos ese curso.');
            $this->redirect(url_admin('cursos'));
            return;
        }
        // El temario hereda el alcance de su curso: no es una entidad aparte.
        $this->requireAlcanceContenido(
            $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null,
            $curso['centro_id'] !== null ? (int) $curso['centro_id'] : null
        );

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'La sesión necesita un título.');
            $this->redirect(url_admin('cursos', 'editar', ['id' => $cursoId]));
            return;
        }

        $datos = [
            'curso_id'    => $cursoId,
            'numero'      => $this->postInt('numero') ?: 1,
            'titulo'      => $titulo,
            'descripcion' => $this->postStr('descripcion') ?: null,
            'fecha'       => $this->postStr('fecha') ?: null,
            'orden'       => $this->postInt('orden'),
        ];

        $id = $this->postInt('id');
        if ($id && $this->modelo->sesionPorId($id)) {
            $this->modelo->actualizarSesion($id, $datos);
            $this->auditoria('editar', 'curso_sesiones', $id, $titulo);
            Session::flash('success', 'Sesión actualizada.');
        } else {
            $id = $this->modelo->crearSesion($datos);
            $this->auditoria('crear', 'curso_sesiones', $id, $titulo);
            Session::flash('success', 'Sesión agregada.');
        }

        $this->redirect(url_admin('cursos', 'editar', ['id' => $cursoId]));
    }

    public function sesionEliminar(): void
    {
        $this->requirePermiso('cursos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $sesion  = $this->modelo->sesionPorId($id);
        if ($sesion) {
            $curso = $this->modelo->porId((int) $sesion['curso_id']);
            $this->requireAlcanceContenido(
                $curso && $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null,
                $curso && $curso['centro_id'] !== null ? (int) $curso['centro_id'] : null
            );
            $this->modelo->eliminarSesion($id);
            $this->auditoria('eliminar', 'curso_sesiones', $id);
            Session::flash('success', 'Sesión eliminada.');
            $this->redirect(url_admin('cursos', 'editar', ['id' => $sesion['curso_id']]));
            return;
        }

        $this->redirect(url_admin('cursos'));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
