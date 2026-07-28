<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

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

        $filtro = in_array($this->getStr('filtro'), ['publicados', 'borradores'], true)
            ? $this->getStr('filtro') : 'todos';

        $this->render('cursos/lista', [
            'titulo'  => 'Cursos',
            'listado' => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $filtro),
            'filtro'  => $filtro,
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('cursos.editar');

        $this->render('cursos/form', [
            'titulo'      => 'Nuevo curso',
            'curso'       => null,
            'sesiones'    => [],
            'instructores'=> (new PersonaModel())->paraSelector(),
            'pastorales'  => (new PastoralModel())->paraSelector(),
            'scriptExtra' => $this->scriptEditor(),
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

        $this->render('cursos/form', [
            'titulo'      => $curso['titulo'],
            'curso'       => $curso,
            'sesiones'    => $this->modelo->sesiones((int) $curso['id']),
            'instructores'=> (new PersonaModel())->paraSelector(),
            'pastorales'  => (new PastoralModel())->paraSelector(),
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('cursos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('cursos'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'El curso necesita un título.');
            $this->redirect($id ? url_admin('cursos', 'editar', ['id' => $id]) : url_admin('cursos', 'nuevo'));
            return;
        }

        $existente  = $id ? $this->modelo->porId($id) : null;
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

        $puedePublicar = Auth::tienePermiso('cursos.publicar');
        $publicado = $puedePublicar ? $this->postBool('publicado') : ($existente['publicado'] ?? 0);

        $datos = [
            'slug'                     => $slug,
            'titulo'                   => $titulo,
            'descripcion'              => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'objetivos'                => $this->postStr('objetivos') ?: null,
            'dirigido_a'               => $this->postStr('dirigido_a') ?: null,
            'imagen'                   => $imagen,
            'modalidad'                => isset(CursoModel::MODALIDADES[$this->postStr('modalidad')]) ? $this->postStr('modalidad') : 'presencial',
            'instructor_id'            => $this->postIntONull('instructor_id'),
            'pastoral_id'              => $this->postIntONull('pastoral_id'),
            'cupo'                     => $this->postIntONull('cupo'),
            'aportacion'               => $this->postStr('aportacion') ?: null,
            'fecha_inicio'             => $this->postStr('fecha_inicio') ?: null,
            'fecha_fin'                => $this->postStr('fecha_fin') ?: null,
            'horario'                  => $this->postStr('horario') ?: null,
            'lugar'                    => $this->postStr('lugar') ?: null,
            'inscripciones_abiertas'   => $this->postBool('inscripciones_abiertas'),
            'fecha_cierre_inscripcion' => $this->postStr('fecha_cierre_inscripcion') ?: null,
            'requiere_tutor'           => $this->postBool('requiere_tutor'),
            'publicado'                => $publicado,
            'orden'                    => $this->postInt('orden'),
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'cursos', $id, $titulo);
            if (!Session::hayFlash()) { Session::flash('success', 'Curso actualizado.'); }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'cursos', $id, $titulo);
            Session::flash('success', $puedePublicar ? 'Curso creado.' : 'Curso enviado como borrador para revisión.');
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
        if (!$this->modelo->porId($cursoId)) {
            Session::flash('error', 'No encontramos ese curso.');
            $this->redirect(url_admin('cursos'));
            return;
        }

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
