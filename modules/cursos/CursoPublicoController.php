<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/cursos/InscripcionCursoModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class CursoPublicoController extends ControllerPublico
{
    /** Solo la pantalla de inscripción necesita sesión (CSRF + antispam). */
    protected bool $requiereSesion = true;

    public function index(): void
    {
        if (!$this->activo()) {
            $this->noEncontrado();
            return;
        }

        $this->render('cursos/publico/index', [
            'metaTitulo'      => 'Cursos y capacitaciones',
            'metaDescripcion' => 'Catálogo de cursos y capacitaciones de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('cursos'),
            'cursos'          => (new CursoModel())->publicados(),
            'bloques'         => (new BloqueModel())->porZona('cursos'),
        ]);
    }

    public function ver(): void
    {
        $curso = $this->activo() ? $this->cursoDelSlug() : null;
        if (!$curso) {
            $this->noEncontrado();
            return;
        }

        $modelo = new CursoModel();
        $inscripciones = new InscripcionCursoModel();

        $this->render('cursos/publico/detalle', [
            'metaTitulo'      => $curso['titulo'],
            'metaDescripcion' => resumen($curso['descripcion'] ?: $curso['objetivos']),
            'ogImagen'        => $curso['imagen'] ?: null,
            'urlCanonica'     => url_publica('cursos', ['slug' => $curso['slug']]),
            'curso'           => $curso,
            'sesiones'        => $modelo->sesiones((int) $curso['id']),
            'cupoLleno'       => $curso['cupo'] !== null && $inscripciones->contarActivas((int) $curso['id']) >= (int) $curso['cupo'],
        ]);
    }

    public function inscribirse(): void
    {
        $curso = $this->activo() ? $this->cursoDelSlug() : null;
        $cerrado = !$curso || !$curso['inscripciones_abiertas']
                || ($curso['fecha_cierre_inscripcion'] && $curso['fecha_cierre_inscripcion'] < date('Y-m-d'));

        if (!$curso || $cerrado) {
            $this->noEncontrado();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->mostrarFormulario($curso, [], []);
            return;
        }

        $this->validarCsrf();

        try {
            $esHumano = AntiSpam::validar('inscripcion_curso_' . $curso['slug']);
        } catch (RuntimeException $e) {
            $this->mostrarFormulario($curso, $_POST, [$e->getMessage()]);
            return;
        }
        if (!$esHumano) {
            $this->mostrarConfirmacion($curso, 'ENV-' . bin2hex(random_bytes(3)));
            return;
        }

        [$datos, $errores] = $this->validarInscripcion($curso);
        if ($errores) {
            $this->mostrarFormulario($curso, $_POST, $errores);
            return;
        }

        $folio = (new InscripcionCursoModel())->crear($datos, $curso['cupo'] !== null ? (int) $curso['cupo'] : null);
        $this->auditoria('crear', 'inscripciones_curso', 0, 'Folio ' . $folio);
        $this->mostrarConfirmacion($curso, $folio);
    }

    // ── Internos ────────────────────────────────────────────────────────

    private function validarInscripcion(array $curso): array
    {
        $errores = [];

        $nombre = $this->postStr('nombre');
        $email  = $this->postStr('email');
        if ($nombre === '') {
            $errores[] = 'Escribe el nombre completo de quien se inscribe.';
        }
        // El correo es obligatorio aquí (a diferencia de otros formularios):
        // es la clave que evita una doble inscripción al mismo curso.
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Escribe un correo electrónico válido: con él evitamos inscripciones duplicadas.';
        }

        $fechaNac = $this->postStr('fecha_nacimiento');
        $esMenor  = false;
        if ($fechaNac !== '') {
            $ts = strtotime($fechaNac);
            if ($ts === false || $ts > time()) {
                $errores[] = 'La fecha de nacimiento no es válida.';
            } else {
                $esMenor = (new DateTimeImmutable($fechaNac))->diff(new DateTimeImmutable())->y < 18;
            }
        }

        $tutorNombre     = $this->postStr('tutor_nombre');
        $tutorParentesco = $this->postStr('tutor_parentesco');
        $tutorTelefono   = $this->postStr('tutor_telefono');
        if ($esMenor && ($tutorNombre === '' || $tutorParentesco === '' || $tutorTelefono === '')) {
            $errores[] = 'Como quien se inscribe es menor de edad, el nombre, parentesco y teléfono del padre, madre o tutor son obligatorios.';
        }

        if (!$this->postBool('consentimiento')) {
            $errores[] = 'Debes aceptar el aviso de privacidad para inscribirte.';
        }

        if (!$errores && (new InscripcionCursoModel())->yaInscrito((int) $curso['id'], $email)) {
            $errores[] = 'Ese correo ya tiene una inscripción activa a este curso.';
        }

        if ($errores) {
            return [[], $errores];
        }

        return [[
            'curso_id'         => (int) $curso['id'],
            'nombre'           => $nombre,
            'fecha_nacimiento' => $fechaNac ?: null,
            'es_menor'         => $esMenor ? 1 : 0,
            'telefono'         => $this->postStr('telefono'),
            'email'            => $email,
            'tutor_nombre'     => $esMenor ? $tutorNombre : '',
            'tutor_parentesco' => $esMenor ? $tutorParentesco : '',
            'tutor_telefono'   => $esMenor ? $tutorTelefono : '',
            'notas'            => $this->postStr('notas'),
            'ip'               => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ], []];
    }

    private function mostrarFormulario(array $curso, array $valores, array $errores): void
    {
        $cupoLleno = $curso['cupo'] !== null
            && (new InscripcionCursoModel())->contarActivas((int) $curso['id']) >= (int) $curso['cupo'];

        $this->render('cursos/publico/inscribirse', [
            'metaTitulo'  => 'Inscripción a ' . $curso['titulo'],
            'sinIndexar'  => true,
            'urlCanonica' => url_publica('cursos', ['slug' => $curso['slug'], 'accion' => 'inscribirse']),
            'curso'       => $curso,
            'valores'     => $valores,
            'errores'     => $errores,
            'cupoLleno'   => $cupoLleno,
        ]);
    }

    private function mostrarConfirmacion(array $curso, string $folio): void
    {
        $this->render('cursos/publico/confirmacion', [
            'metaTitulo' => 'Inscripción enviada',
            'sinIndexar' => true,
            'curso'      => $curso,
            'folio'      => $folio,
        ]);
    }

    /** Configuración → Secciones del sitio: interruptor manual, independiente de si hay cursos publicados. */
    private function activo(): bool
    {
        return Config::get('cursos_activo', '1') === '1';
    }

    private function cursoDelSlug(): ?array
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9\-]{1,120}$/', $slug)) {
            return null;
        }
        return (new CursoModel())->porSlugPublicado($slug);
    }
}
