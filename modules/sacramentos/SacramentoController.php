<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';

class SacramentoController extends Controller
{
    private SacramentoModel $modelo;

    public function __construct()
    {
        $this->modelo = new SacramentoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('sacramentos.ver');

        $this->render('sacramentos/lista', [
            'titulo'      => 'Sacramentos',
            'sacramentos' => $this->modelo->todos(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        $sacramento = $this->modelo->porId($this->getInt('id'));
        if (!$sacramento) {
            Session::flash('error', 'No encontramos ese sacramento.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $this->render('sacramentos/form', [
            'titulo'      => $sacramento['nombre'],
            'sacramento'  => $sacramento,
            'campos'      => $this->modelo->campos((int) $sacramento['id']),
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('sacramentos'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $nombre = $this->postStr('nombre');
        if ($id === 0 || $nombre === '') {
            Session::flash('error', 'Falta el sacramento o el nombre.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $actual = $this->modelo->porId($id);
        if (!$actual) {
            Session::flash('error', 'No encontramos ese sacramento.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $imagen = $actual['imagen'];
        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'sacramentos', 'sacramento', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'Se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $this->modelo->actualizar($id, [
            'slug'               => $actual['slug'], // el slug del sacramento no se edita: define su prefijo de folio y su URL
            'nombre'             => $nombre,
            'descripcion'        => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'requisitos'         => SanitizadorHtml::limpiar($this->postHtml('requisitos')) ?: null,
            'documentos'         => SanitizadorHtml::limpiar($this->postHtml('documentos')) ?: null,
            'aportacion'         => $this->postStr('aportacion') ?: null,
            'imagen'             => $imagen,
            'acepta_solicitudes' => $this->postBool('acepta_solicitudes'),
            'requiere_tutor'     => $this->postBool('requiere_tutor'),
            'orden'              => $this->postInt('orden'),
            'activo'             => $this->postBool('activo'),
        ]);
        $this->auditoria('editar', 'sacramentos', $id, $nombre);

        Session::flash('success', 'Sacramento actualizado.');
        $this->redirect(url_admin('sacramentos', 'editar', ['id' => $id]));
    }

    // ── Campos configurables ────────────────────────────────────────────

    public function campoGuardar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('sacramentos'));
            return;
        }
        $this->validarCsrf();

        $sacramentoId = $this->postInt('sacramento_id');
        if (!$this->modelo->porId($sacramentoId)) {
            Session::flash('error', 'No encontramos ese sacramento.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $etiqueta = $this->postStr('etiqueta');
        if ($etiqueta === '') {
            Session::flash('error', 'El campo necesita una etiqueta.');
            $this->redirect(url_admin('sacramentos', 'editar', ['id' => $sacramentoId]));
            return;
        }

        $tipo = isset(SacramentoModel::TIPOS_CAMPO[$this->postStr('tipo')]) ? $this->postStr('tipo') : 'texto';
        $id   = $this->postInt('id');

        if ($id && $this->modelo->campoPorId($id)) {
            $this->modelo->actualizarCampo($id, [
                'etiqueta'      => $etiqueta,
                'tipo'          => $tipo,
                'opciones'      => $tipo === 'seleccion' ? ($this->postStr('opciones') ?: null) : null,
                'requerido'     => $this->postBool('requerido'),
                'dato_sensible' => $this->postBool('dato_sensible'),
                'orden'         => $this->postInt('orden'),
                'activo'        => $this->postBool('activo'),
            ]);
            $this->auditoria('editar', 'sacramento_campos', $id, $etiqueta);
            Session::flash('success', 'Campo actualizado.');
        } else {
            $this->modelo->crearCampo([
                'sacramento_id' => $sacramentoId,
                'nombre_campo'  => SacramentoModel::slugCampo($etiqueta),
                'etiqueta'      => $etiqueta,
                'tipo'          => $tipo,
                'opciones'      => $tipo === 'seleccion' ? ($this->postStr('opciones') ?: null) : null,
                'requerido'     => $this->postBool('requerido'),
                'dato_sensible' => $this->postBool('dato_sensible'),
                'orden'         => $this->postInt('orden'),
                'activo'        => $this->postBool('activo'),
            ]);
            $this->auditoria('crear', 'sacramento_campos', 0, $etiqueta);
            Session::flash('success', 'Campo agregado.');
        }

        $this->redirect(url_admin('sacramentos', 'editar', ['id' => $sacramentoId]));
    }

    public function campoEliminar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('sacramentos'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $campo = $this->modelo->campoPorId($id);
        if ($campo) {
            $this->modelo->eliminarCampo($id);
            $this->auditoria('eliminar', 'sacramento_campos', $id, $campo['etiqueta']);
            Session::flash('success', 'Campo eliminado.');
            $this->redirect(url_admin('sacramentos', 'editar', ['id' => $campo['sacramento_id']]));
            return;
        }

        $this->redirect(url_admin('sacramentos'));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
