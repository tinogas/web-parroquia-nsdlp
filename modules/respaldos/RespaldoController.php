<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/respaldos/RespaldoModel.php';

/**
 * RespaldoController — Generar, descargar y eliminar respaldos de la base de
 * datos. Exclusivo de administrador: ver docs/ARQUITECTURA.md, sección
 * "Usuarios, roles y auditoría" — respaldos.* no aparece en la matriz de
 * ningún otro rol, igual que usuarios.* y auditoria.*.
 */
class RespaldoController extends Controller
{
    private RespaldoModel $modelo;

    public function __construct()
    {
        $this->modelo = new RespaldoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('respaldos.ver');

        $this->render('respaldos/lista', [
            'titulo'    => 'Respaldos de la base de datos',
            'respaldos' => $this->modelo->listar(),
        ]);
    }

    public function crear(): void
    {
        $this->requirePermiso('respaldos.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('respaldos'));
            return;
        }
        $this->validarCsrf();

        try {
            $resultado = $this->modelo->crear((int) Auth::usuario()['id']);
            $this->auditoria('crear', 'respaldos_log', 0, 'Archivo: ' . $resultado['archivo']);
            Session::flash('success', 'Respaldo generado correctamente: ' . $resultado['archivo']);
        } catch (RuntimeException $e) {
            $this->auditoria('crear', 'respaldos_log', 0, 'Error: ' . $e->getMessage());
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(url_admin('respaldos'));
    }

    public function descargar(): void
    {
        $this->requirePermiso('respaldos.ver');

        $fila = $this->modelo->porId($this->getInt('id'));
        $ruta = $fila ? $this->modelo->rutaArchivo($fila['archivo']) : null;

        if (!$fila || !$ruta || !is_file($ruta)) {
            Session::flash('error', 'Ese respaldo ya no está disponible.');
            $this->redirect(url_admin('respaldos'));
            return;
        }

        $this->auditoria('exportar', 'respaldos_log', (int) $fila['id'], 'Descarga: ' . $fila['archivo']);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fila['archivo'] . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-store');
        readfile($ruta);
        exit;
    }

    public function eliminar(): void
    {
        $this->requirePermiso('respaldos.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('respaldos'));
            return;
        }
        $this->validarCsrf();

        $id   = $this->postInt('id');
        $fila = $this->modelo->porId($id);
        if ($fila && $this->modelo->eliminar($id)) {
            $this->auditoria('eliminar', 'respaldos_log', $id, 'Archivo: ' . $fila['archivo']);
            Session::flash('success', 'Respaldo eliminado.');
        }

        $this->redirect(url_admin('respaldos'));
    }
}
