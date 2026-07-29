<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/contacto/ContactoModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class ContactoPublicoController extends ControllerPublico
{
    /** Hay un formulario en esta página: necesita CSRF, y CSRF necesita sesión. */
    protected bool $requiereSesion = true;

    public function index(): void
    {
        $this->mostrarFormulario();
    }

    public function enviar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_publica('contacto'));
            return;
        }
        $this->validarCsrf();

        try {
            $esHumano = AntiSpam::validar('contacto');
        } catch (RuntimeException $e) {
            $this->mostrarFormulario([], [$e->getMessage()]);
            return;
        }

        $datos = [
            'nombre'   => $this->postStr('nombre'),
            'email'    => $this->postStr('email'),
            'telefono' => $this->postStr('telefono'),
            'asunto'   => $this->postStr('asunto'),
            'mensaje'  => $this->postStr('mensaje'),
        ];

        if (!$esHumano) {
            // Huele a envío automático: se descarta en silencio y se muestra
            // éxito, para no revelarle al robot qué lo delató.
            Session::flash('success', 'Gracias por escribirnos. Te responderemos pronto.');
            $this->redirect(url_publica('contacto'));
            return;
        }

        $errores = $this->validar($datos);
        if ($errores) {
            $this->mostrarFormulario($datos, $errores);
            return;
        }

        (new ContactoModel())->crear($datos + ['ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '')]);

        Session::flash('success', 'Gracias por escribirnos. Te responderemos pronto.');
        $this->redirect(url_publica('contacto'));
    }

    private function validar(array $datos): array
    {
        $errores = [];

        if ($datos['nombre'] === '') {
            $errores[] = 'Escribe tu nombre.';
        }
        if ($datos['mensaje'] === '') {
            $errores[] = 'Escribe tu mensaje.';
        }
        if ($datos['email'] === '' && $datos['telefono'] === '') {
            $errores[] = 'Déjanos un correo o un teléfono para poder responderte.';
        }
        if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no tiene un formato válido.';
        }
        if (!$this->postBool('consentimiento')) {
            $errores[] = 'Debes aceptar el aviso de privacidad para enviar el mensaje.';
        }

        return $errores;
    }

    private function mostrarFormulario(array $valores = [], array $errores = []): void
    {
        $this->render('contacto/publico/index', [
            'metaTitulo'      => 'Contacto',
            'metaDescripcion' => 'Dirección, teléfono, correo y formulario de contacto de la parroquia.',
            'urlCanonica'     => url_publica('contacto'),
            'valores'         => $valores,
            'errores'         => $errores,
            'bloques'         => (new BloqueModel())->porZona('contacto'),
        ]);
    }
}
