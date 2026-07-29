<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';
require_once BASE_PATH . '/modules/sacramentos/SolicitudModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class SacramentoPublicoController extends ControllerPublico
{
    /** Solo la pantalla con el formulario necesita sesión (CSRF + antispam). */
    protected bool $requiereSesion = true;

    public function index(): void
    {
        $this->render('sacramentos/publico/index', [
            'metaTitulo'      => 'Sacramentos',
            'metaDescripcion' => 'Requisitos y solicitud en línea de bautizo, primera comunión, confirmación, matrimonio y más.',
            'urlCanonica'     => url_publica('sacramentos'),
            'sacramentos'     => (new SacramentoModel())->activos(),
            'bloques'         => (new BloqueModel())->porZona('sacramentos'),
        ]);
    }

    public function ver(): void
    {
        $sacramento = $this->sacramentoDelSlug();
        if (!$sacramento) {
            $this->noEncontrado();
            return;
        }

        $this->render('sacramentos/publico/detalle', [
            'metaTitulo'      => $sacramento['nombre'],
            'metaDescripcion' => resumen($sacramento['descripcion'] ?: $sacramento['requisitos']),
            'ogImagen'        => $sacramento['imagen'] ?: null,
            'urlCanonica'     => url_publica('sacramentos', ['slug' => $sacramento['slug']]),
            'sacramento'      => $sacramento,
        ]);
    }

    public function solicitar(): void
    {
        $sacramento = $this->sacramentoDelSlug();
        if (!$sacramento || !$sacramento['acepta_solicitudes']) {
            $this->noEncontrado();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->mostrarFormulario($sacramento, [], []);
            return;
        }

        $this->validarCsrf();

        try {
            $esHumano = AntiSpam::validar('solicitud_' . $sacramento['slug']);
        } catch (RuntimeException $e) {
            $this->mostrarFormulario($sacramento, $_POST, [$e->getMessage()]);
            return;
        }
        if (!$esHumano) {
            // Se descarta en silencio, como en el formulario de contacto: no
            // hay forma de que un script distinga "se envió" de "se ignoró".
            $this->mostrarConfirmacion($sacramento, 'ENV-' . bin2hex(random_bytes(3)));
            return;
        }

        [$datos, $errores] = $this->validarSolicitud($sacramento);
        if ($errores) {
            $this->mostrarFormulario($sacramento, $_POST, $errores);
            return;
        }

        $folio = (new SolicitudModel())->crear($datos);

        $this->auditoria('crear', 'solicitudes_sacramento', 0, 'Folio ' . $folio);
        $this->mostrarConfirmacion($sacramento, $folio);
    }

    // ── Internos ────────────────────────────────────────────────────────

    private function validarSolicitud(array $sacramento): array
    {
        $errores = [];

        $nombre  = $this->postStr('nombre_solicitante');
        $fechaNac = $this->postStr('fecha_nacimiento');

        if ($nombre === '') {
            $errores[] = 'Escribe el nombre completo de quien solicita el sacramento.';
        }

        $esMenor = false;
        if ($fechaNac !== '') {
            $ts = strtotime($fechaNac);
            if ($ts === false || $ts > time()) {
                $errores[] = 'La fecha de nacimiento no es válida.';
            } else {
                $esMenor = (new DateTimeImmutable($fechaNac))
                    ->diff(new DateTimeImmutable())->y < 18;
            }
        }

        $telefono = $this->postStr('telefono');
        $email    = $this->postStr('email');
        if ($telefono === '' && $email === '') {
            $errores[] = 'Déjanos un teléfono o un correo para poder contactarte.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no tiene un formato válido.';
        }

        $tutorNombre     = $this->postStr('tutor_nombre');
        $tutorParentesco = $this->postStr('tutor_parentesco');
        $tutorTelefono   = $this->postStr('tutor_telefono');
        if ($esMenor && ($tutorNombre === '' || $tutorParentesco === '' || $tutorTelefono === '')) {
            $errores[] = 'Como el solicitante es menor de edad, el nombre, parentesco y teléfono del padre, madre o tutor son obligatorios.';
        }

        if (!$this->postBool('consentimiento')) {
            $errores[] = 'Debes aceptar el aviso de privacidad para enviar la solicitud.';
        }

        // Campos configurables del sacramento.
        $datosExtra = [];
        $modeloSac  = new SacramentoModel();
        foreach ($modeloSac->camposActivos((int) $sacramento['id']) as $campo) {
            $clave = 'extra_' . $campo['nombre_campo'];
            $valor = $campo['tipo'] === 'checkbox'
                ? ($this->postBool($clave) ? '1' : '0')
                : $this->postStr($clave);

            if ($campo['requerido'] && $valor === '') {
                $errores[] = 'El campo «' . $campo['etiqueta'] . '» es obligatorio.';
            }
            if ($valor !== '') {
                $datosExtra[$campo['nombre_campo']] = $valor;
            }
        }

        if ($errores) {
            return [[], $errores];
        }

        return [[
            'sacramento_id'     => (int) $sacramento['id'],
            'nombre_solicitante'=> $nombre,
            'fecha_nacimiento'  => $fechaNac ?: null,
            'es_menor'          => $esMenor ? 1 : 0,
            'telefono'          => $telefono,
            'email'             => $email,
            'direccion'         => $this->postStr('direccion'),
            'tutor_nombre'      => $esMenor ? $tutorNombre : '',
            'tutor_parentesco'  => $esMenor ? $tutorParentesco : '',
            'tutor_telefono'    => $esMenor ? $tutorTelefono : '',
            'fecha_preferida'   => $this->postStr('fecha_preferida'),
            'notas'             => $this->postStr('notas'),
            'datos_extra'       => $datosExtra,
            'prefijo_folio'     => SacramentoModel::prefijoFolio($sacramento['slug']),
            'ip'                => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'origen'            => 'web',
        ], []];
    }

    private function mostrarFormulario(array $sacramento, array $valores, array $errores): void
    {
        $this->render('sacramentos/publico/solicitar', [
            'metaTitulo'      => 'Solicitud de ' . $sacramento['nombre'],
            'sinIndexar'      => true,
            'urlCanonica'     => url_publica('sacramentos', ['slug' => $sacramento['slug'], 'accion' => 'solicitar']),
            'sacramento'      => $sacramento,
            'campos'          => (new SacramentoModel())->camposActivos((int) $sacramento['id']),
            'valores'         => $valores,
            'errores'         => $errores,
        ]);
    }

    private function mostrarConfirmacion(array $sacramento, string $folio): void
    {
        $this->render('sacramentos/publico/confirmacion', [
            'metaTitulo'  => 'Solicitud enviada',
            'sinIndexar'  => true,
            'sacramento'  => $sacramento,
            'folio'       => $folio,
        ]);
    }

    private function sacramentoDelSlug(): ?array
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9\-]{1,60}$/', $slug)) {
            return null;
        }
        return (new SacramentoModel())->porSlugActivo($slug);
    }
}
