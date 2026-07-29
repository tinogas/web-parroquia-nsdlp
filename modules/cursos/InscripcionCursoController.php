<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/cursos/InscripcionCursoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';

class InscripcionCursoController extends Controller
{
    private InscripcionCursoModel $modelo;

    public function __construct()
    {
        $this->modelo = new InscripcionCursoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('inscripciones.ver');

        $cursoId = $this->getInt('curso_id') ?: null;
        $estado  = $this->getStr('estado') ?: 'todos';

        $this->auditoria('consultar', 'inscripciones_curso', 0, "Listado, estado: {$estado}");

        $this->render('cursos/inscripciones_lista', [
            'titulo'   => 'Inscripciones a cursos',
            'listado'  => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $cursoId, $estado),
            'cursoId'  => $cursoId,
            'estado'   => $estado,
            'cursos'   => (new CursoModel())->paraSelector(),
        ]);
    }

    public function ver(): void
    {
        $this->requirePermiso('inscripciones.ver');

        $inscripcion = $this->modelo->porId($this->getInt('id'));
        if (!$inscripcion) {
            Session::flash('error', 'No encontramos esa inscripción.');
            $this->redirect(url_admin('inscripciones'));
            return;
        }
        $this->auditoria('consultar', 'inscripciones_curso', (int) $inscripcion['id'], $inscripcion['folio']);

        $this->render('cursos/inscripcion_ver', [
            'titulo'      => 'Inscripción ' . $inscripcion['folio'],
            'inscripcion' => $inscripcion,
        ]);
    }

    public function cambiarEstado(): void
    {
        $this->requirePermiso('inscripciones.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('inscripciones'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $estado = $this->postStr('estado');
        if (!$this->modelo->porId($id) || !isset(InscripcionCursoModel::ESTADOS[$estado])) {
            Session::flash('error', 'No se pudo actualizar la inscripción.');
            $this->redirect(url_admin('inscripciones'));
            return;
        }

        $this->modelo->cambiarEstado($id, $estado);
        $this->auditoria('editar', 'inscripciones_curso', $id, 'Estado → ' . $estado);

        Session::flash('success', 'Estado actualizado.');
        $this->redirect(url_admin('inscripciones', 'ver', ['id' => $id]));
    }

    /** CSV de la bandeja actual. */
    public function exportar(): void
    {
        $this->requirePermiso('inscripciones.exportar');

        $cursoId = $this->getInt('curso_id') ?: null;
        $estado  = $this->getStr('estado') ?: 'todos';

        $primera      = $this->modelo->listar(1, $cursoId, $estado);
        $filas        = $primera['filas'];
        $totalPaginas = $primera['total_paginas'];
        for ($p = 2; $p <= $totalPaginas; $p++) {
            $filas = array_merge($filas, $this->modelo->listar($p, $cursoId, $estado)['filas']);
        }

        $this->auditoria('exportar', 'inscripciones_curso', 0, "CSV, " . count($filas) . ' filas');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inscripciones_' . date('Y-m-d') . '.csv"');

        $salida = fopen('php://output', 'w');
        fputs($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Folio', 'Curso', 'Nombre', 'Es menor', 'Teléfono', 'Correo', 'Estado', 'Creada']);
        foreach ($filas as $fila) {
            fputcsv($salida, [
                $fila['folio'],
                $fila['curso_titulo'],
                $fila['nombre'],
                $fila['es_menor'] ? 'Sí' : 'No',
                $fila['telefono'],
                $fila['email'],
                InscripcionCursoModel::ESTADOS[$fila['estado']] ?? $fila['estado'],
                $fila['created_at'],
            ]);
        }
        fclose($salida);
        exit;
    }
}
