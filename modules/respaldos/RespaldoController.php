<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/respaldos/RespaldoModel.php';

/**
 * RespaldoController — Generar, descargar, restaurar y eliminar respaldos de
 * la base de datos. Exclusivo de administrador: ver docs/ARQUITECTURA.md,
 * sección "Usuarios, roles y auditoría" — respaldos.* no aparece en la
 * matriz de ningún otro rol, igual que usuarios.* y auditoria.*.
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

    public function restaurar(): void
    {
        $this->requirePermiso('respaldos.restaurar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('respaldos'));
            return;
        }
        $this->validarCsrf();

        // La casilla de confirmación ya deshabilita el botón en la vista,
        // pero un POST crudo podría saltársela: se revalida en el servidor.
        if (!$this->postBool('confirmo')) {
            Session::flash('error', 'Debes confirmar que entiendes que esto reemplaza todos los datos actuales.');
            $this->redirect(url_admin('respaldos'));
            return;
        }

        $id = $this->postInt('id');

        try {
            $resultado = $this->modelo->restaurar($id, (int) Auth::usuario()['id']);
            $this->auditoria(
                'restaurar',
                'respaldos_log',
                $id,
                "Restauración completa: {$resultado['sentencias']} sentencia(s). Respaldo de seguridad: {$resultado['seguridad']}"
            );
            Session::flash(
                'success',
                "Base de datos restaurada ({$resultado['sentencias']} sentencias). "
                    . "Se generó un respaldo de seguridad del estado anterior: {$resultado['seguridad']}."
            );
        } catch (RuntimeException $e) {
            $this->auditoria('restaurar', 'respaldos_log', $id, 'Error: ' . $e->getMessage());
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(url_admin('respaldos'));
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
