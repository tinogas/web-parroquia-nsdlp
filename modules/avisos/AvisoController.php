<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';

class AvisoController extends Controller
{
    private AvisoModel $modelo;

    public function __construct()
    {
        $this->modelo = new AvisoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('avisos.ver');

        $filtro = in_array($this->getStr('filtro'), ['publicados', 'borradores'], true)
            ? $this->getStr('filtro') : 'todos';

        $this->render('avisos/lista', [
            'titulo'  => 'Avisos',
            'listado' => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $filtro),
            'filtro'  => $filtro,
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('avisos.crear');

        $this->render('avisos/form', [
            'titulo'      => 'Nuevo aviso',
            'aviso'       => null,
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('avisos.editar');

        $aviso = $this->modelo->porId($this->getInt('id'));
        if (!$aviso) {
            Session::flash('error', 'No encontramos ese aviso.');
            $this->redirect(url_admin('avisos'));
            return;
        }

        $this->render('avisos/form', [
            'titulo'      => $aviso['titulo'],
            'aviso'       => $aviso,
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('avisos'));
            return;
        }
        $this->validarCsrf();

        $id       = $this->postInt('id');
        $existente = $id ? $this->modelo->porId($id) : null;
        $this->requirePermiso($existente ? 'avisos.editar' : 'avisos.crear');

        $titulo = $this->postStr('titulo');
        if ($titulo === '' || !isset(AvisoModel::TIPOS[$this->postStr('tipo')])) {
            Session::flash('error', 'El aviso necesita título y tipo.');
            $this->redirect($id ? url_admin('avisos', 'editar', ['id' => $id]) : url_admin('avisos', 'nuevo'));
            return;
        }

        $slugPedido = $this->postStr('slug');
        $slug = $slugPedido !== ''
            ? Slug::unico($slugPedido, 'avisos', $id ?: null)
            : ($existente ? $existente['slug'] : Slug::unico($titulo, 'avisos'));

        $imagen = $existente['imagen'] ?? null;
        $pdf    = $existente['archivo_pdf'] ?? null;
        try {
            $imagen = $this->procesarImagen($imagen);
            $pdf    = $this->procesarPdf($pdf);
        } catch (RuntimeException $e) {
            Session::flash('warning', 'El aviso se guardó, pero hubo un problema con un archivo: ' . $e->getMessage());
        }

        $puedePublicar = Auth::tienePermiso('avisos.publicar');
        $publicado = $puedePublicar ? $this->postBool('publicado') : ($existente['publicado'] ?? 0);

        $datos = [
            'slug'              => $slug,
            'titulo'            => $titulo,
            'resumen'           => $this->postStr('resumen') ?: null,
            'contenido'         => SanitizadorHtml::limpiar($this->postHtml('contenido')) ?: null,
            'imagen'            => $imagen,
            'tipo'              => $this->postStr('tipo'),
            'archivo_pdf'       => $pdf,
            'fecha_publicacion' => $this->postStr('fecha_publicacion') ?: date('Y-m-d'),
            'destacado'         => $this->postBool('destacado'),
            'publicado'         => $publicado,
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'avisos', $id, $titulo);
            if (!Session::hayFlash()) { Session::flash('success', 'Aviso actualizado.'); }
        } else {
            $id = $this->modelo->crear($datos, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'avisos', $id, $titulo);
            Session::flash('success', $puedePublicar ? 'Aviso creado.' : 'Aviso enviado como borrador para revisión.');
        }

        $this->redirect(url_admin('avisos'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('avisos.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('avisos'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $aviso = $this->modelo->porId($id);
        if ($aviso) {
            Upload::borrar($aviso['imagen']);
            Upload::borrar($aviso['archivo_pdf']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'avisos', $id, $aviso['titulo']);
            Session::flash('success', 'Aviso eliminado.');
        }

        $this->redirect(url_admin('avisos'));
    }

    private function procesarImagen(?string $actual): ?string
    {
        if (!empty($_POST['imagen_quitar'])) {
            Upload::borrar($actual);
            return null;
        }
        return Upload::imagen('imagen', 'avisos', 'aviso', $actual);
    }

    private function procesarPdf(?string $actual): ?string
    {
        if (!empty($_POST['archivo_pdf_quitar'])) {
            Upload::borrar($actual);
            return null;
        }
        return Upload::documento('archivo_pdf', 'boletines', 'boletin', $actual);
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
