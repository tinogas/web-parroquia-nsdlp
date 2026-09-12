<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class PersonaController extends Controller
{
    private PersonaModel $modelo;

    public function __construct()
    {
        $this->modelo = new PersonaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('personas.ver');

        // Filtro de conveniencia, no de acceso: quien llega aquí ya ve a todo
        // el equipo (personas.ver solo lo tienen roles con alcance global).
        // Se reusan los helpers de Controller.php igual —recortan las
        // opciones del selector solas si el permiso se abre a un rol acotado
        // algún día—, pero sin pastoralesVisibles()/centrosVisibles(): esos
        // permiten ver además "lo general sin pastoral", un concepto que no
        // existe en personas.
        $pastorales = $this->pastoralesDelFiltro();
        $centros    = $this->centrosDelFiltro();
        [$filtroPastoral, $idsPastoral] = $this->filtroPastoral($pastorales);
        [$filtroCentro,   $idsCentro]   = $this->filtroCentro($centros);

        $this->render('personas/lista', [
            'titulo'         => 'Equipo pastoral',
            'personas'       => $this->modelo->todas($idsPastoral, $idsCentro),
            'pastorales'     => $pastorales,
            'filtroPastoral' => $filtroPastoral,
            'centros'        => $centros,
            'filtroCentro'   => $filtroCentro,
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('personas.editar');

        $this->render('personas/form', [
            'titulo'           => 'Nueva persona',
            'persona'          => null,
            'agrupado'         => (new PastoralModel())->activasAgrupadas(),
            'asignadas'        => [],
            'centros'          => (new CentroModel())->activos(),
            'centrosAsignados' => [],
            // La pareja se elige aquí, en la ficha, porque es un dato de la
            // persona y no de una pastoral: el mismo matrimonio vale en
            // Matrimonios, en AMA y en JECSA. Al dar de alta todavía no hay a
            // quién ligar —la ficha no existe—, así que el selector aparece
            // vacío y se usa al editar.
            'candidatosPareja' => [],
            'parejaActual'     => null,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('personas.editar');

        $persona = $this->modelo->porId($this->getInt('id'));
        if (!$persona) {
            Session::flash('error', 'No encontramos a esa persona.');
            $this->redirect(url_admin('personas'));
            return;
        }

        $this->render('personas/form', [
            'titulo'           => $persona['nombre'],
            'persona'          => $persona,
            'agrupado'         => (new PastoralModel())->activasAgrupadas(),
            'asignadas'        => $this->modelo->pastoralesDe((int) $persona['id']),
            'centros'          => (new CentroModel())->activos(),
            'centrosAsignados' => $this->modelo->centrosDe((int) $persona['id']),
            'candidatosPareja' => $this->modelo->paraSelector(),
            'parejaActual'     => $this->modelo->parejaDe((int) $persona['id']),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('personas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('personas'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $nombre = $this->postStr('nombre');

        if ($nombre === '' || !isset(PersonaModel::TIPOS[$this->postStr('tipo')])) {
            Session::flash('error', 'Escribe el nombre y elige un tipo válido.');
            $this->redirect($id ? url_admin('personas', 'editar', ['id' => $id]) : url_admin('personas', 'nueva'));
            return;
        }
        // El teléfono se propaga a las cuatro tablas de pastoral y alimenta los
        // enlaces de WhatsApp, así que un dedazo aquí se multiplica.
        if (!telefono_valido($this->postStr('telefono'))) {
            Session::flash('error', 'El teléfono no tiene un formato válido. ' . TELEFONO_FORMATO);
            $this->redirect($id ? url_admin('personas', 'editar', ['id' => $id]) : url_admin('personas', 'nueva'));
            return;
        }

        $actual = $id ? $this->modelo->porId($id) : null;
        $foto   = $actual['foto'] ?? null;

        try {
            if (!empty($_POST['foto_quitar'])) {
                Upload::borrar($foto);
                $foto = null;
            } else {
                $foto = Upload::imagen('foto', 'personas', 'persona', $foto);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'Se guardó, pero la foto no: ' . $e->getMessage());
        }

        $pastorales = array_values(array_unique(array_map(
            'intval',
            array_filter((array) ($_POST['pastorales'] ?? []), 'is_numeric')
        )));
        $centros = array_values(array_unique(array_map(
            'intval',
            array_filter((array) ($_POST['centros'] ?? []), 'is_numeric')
        )));

        $datos = [
            'nombre'     => $nombre,
            'cargo'      => $this->postStr('cargo') ?: null,
            'tipo'       => $this->postStr('tipo'),
            'semblanza'  => $this->postStr('semblanza') ?: null,
            'foto'       => $foto,
            'email'      => $this->postStr('email') ?: null,
            'telefono'   => $this->postStr('telefono') ?: null,
            'fecha_nacimiento' => $this->postStrONull('fecha_nacimiento'),
            'orden'      => $this->postInt('orden'),
            'activo'     => $this->postBool('activo'),
            'pastorales' => $pastorales,
            'centros'    => $centros,
        ];

        if ($actual) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'personas', $id, $nombre);
            if (!Session::hayFlash()) {
                Session::flash('success', 'Datos actualizados.');
            }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'personas', $id, $nombre);
            Session::flash('success', 'Persona agregada.');
        }

        // La pareja se aplica después de tener el id (una ficha nueva no lo
        // tiene antes de guardarse). Cambiar de pareja deshace la anterior de
        // los dos: nadie puede estar en dos a la vez, y elegir "sin pareja"
        // simplemente la deshace.
        if ($id && array_key_exists('pareja_persona_id', $_POST)) {
            $otroId = $this->postIntONull('pareja_persona_id');
            if ($otroId === $id) {
                Session::flash('warning', 'Se guardó, pero nadie puede ser su propia pareja.');
            } else {
                $anterior = $this->modelo->parejaDe($id);
                $este     = $anterior['otro_id'] ?? null;
                if ((int) $este !== (int) $otroId) {
                    $this->modelo->guardarPareja($id, $otroId);
                    $this->auditoria('editar', 'parejas', $id, $nombre);
                }
            }
        }

        $this->redirect(url_admin('personas'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('personas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('personas'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $persona = $this->modelo->porId($id);

        if ($persona) {
            Upload::borrar($persona['foto']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'personas', $id, $persona['nombre']);
            Session::flash('success', 'Persona eliminada. Si aparecía en el organigrama, ese lugar quedó sin asignar.');
        }

        $this->redirect(url_admin('personas'));
    }
}
