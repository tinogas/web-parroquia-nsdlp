<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/configuracion/ConfiguracionModel.php';

class ConfiguracionController extends Controller
{
    private ConfiguracionModel $modelo;

    public function __construct()
    {
        $this->modelo = new ConfiguracionModel();
    }

    public function index(): void
    {
        $this->requirePermiso('configuracion.ver');

        $this->render('configuracion/form', [
            'titulo'  => 'Configuración',
            'valores' => $this->modelo->todas(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('configuracion.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('configuracion'));
            return;
        }
        $this->validarCsrf();

        $grupo   = $this->postStr('grupo');
        $campos  = ConfiguracionModel::camposDe($grupo);
        $errores = [];

        if (!$campos) {
            Session::flash('error', 'Sección desconocida.');
            $this->redirect(url_admin('configuracion'));
            return;
        }

        foreach ($campos as $clave => [$etiqueta, $tipo, , ]) {

            // Solo se toca lo que venga en el envío. Un formulario completo
            // manda todos sus campos, así que en el uso normal no cambia nada;
            // pero si llegara un envío incompleto, esto evita que se borren
            // datos que nadie pidió borrar.
            if ($tipo !== 'imagen' && !isset($_POST[$clave])) {
                continue;
            }

            try {
                $valor = $this->valorDelCampo($clave, $tipo, $etiqueta);
                if ($valor !== null) {
                    $this->modelo->guardar($clave, $valor);
                }
            } catch (RuntimeException $e) {
                $errores[] = $etiqueta . ': ' . $e->getMessage();
            }
        }

        // El pie y el encabezado del sitio leen de Config, que cachea en memoria.
        Config::limpiar();

        $this->auditoria('editar', 'configuracion', 0, 'Sección: ' . $grupo);

        if ($errores) {
            Session::flash('warning', 'Se guardaron los cambios, salvo: ' . implode(' · ', $errores));
        } else {
            Session::flash('success', 'Configuración guardada.');
        }

        $this->redirect(url_admin('configuracion') . '#' . $grupo);
    }

    /**
     * Devuelve el valor a guardar para un campo, o null si no hay que tocarlo
     * (caso de una imagen que no se reemplazó).
     */
    private function valorDelCampo(string $clave, string $tipo, string $etiqueta): ?string
    {
        return match ($tipo) {
            'solo_lectura' => null,

            'imagen' => $this->procesarImagen($clave),

            'mapa' => $this->normalizarMapa($this->postHtml($clave)),

            'numero' => (string) max(0, $this->postInt($clave)),

            'email' => $this->validarEmail($this->postStr($clave), $etiqueta),

            'url' => $this->validarUrl($this->postStr($clave), $etiqueta),

            // El horario de oficina y la descripción del sitio conservan los
            // saltos de línea, así que no se puede usar postStr (que recorta).
            'parrafo' => trim(strip_tags((string) ($_POST[$clave] ?? ''))),

            default => $this->postStr($clave),
        };
    }

    /**
     * Sube la imagen nueva, o borra la actual si se marcó la casilla de quitar.
     * Devuelve null cuando no hay nada que cambiar.
     */
    private function procesarImagen(string $clave): ?string
    {
        $actual = $this->modelo->valor($clave);

        if (!empty($_POST[$clave . '_quitar'])) {
            Upload::borrar($actual);
            return '';
        }

        return Upload::imagen($clave, 'sitio', $clave, $actual);
    }

    /**
     * Del código que Google Maps entrega para insertar solo se guarda la URL
     * del iframe, nunca el HTML.
     *
     * Guardar el fragmento completo obligaría a imprimir HTML de terceros en el
     * sitio; con la URL, la vista construye el iframe y basta comprobar que
     * apunte de verdad a Google Maps.
     */
    private function normalizarMapa(string $pegado): string
    {
        $pegado = trim($pegado);
        if ($pegado === '') {
            return '';
        }

        // Si pegaron el <iframe> completo, extraer su src.
        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $pegado, $coincidencia)) {
            $pegado = $coincidencia[1];
        }

        $pegado = html_entity_decode($pegado, ENT_QUOTES, 'UTF-8');

        if (!preg_match('#^https://(www\.)?google\.com/maps/embed#i', $pegado)) {
            throw new RuntimeException(
                'no reconocimos un mapa de Google. Usa Compartir → Insertar un mapa y pega el código completo.'
            );
        }

        return $pegado;
    }

    private function validarEmail(string $valor, string $etiqueta): string
    {
        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('el correo no tiene un formato válido.');
        }
        return $valor;
    }

    private function validarUrl(string $valor, string $etiqueta): string
    {
        if ($valor === '') {
            return '';
        }
        // Cortesía habitual: quien pega «facebook.com/algo» espera que funcione.
        if (!preg_match('#^https?://#i', $valor)) {
            $valor = 'https://' . $valor;
        }
        if (!filter_var($valor, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('la dirección no es válida.');
        }
        return $valor;
    }
}
