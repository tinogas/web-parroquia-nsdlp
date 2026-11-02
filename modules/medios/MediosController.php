<?php
require_once BASE_PATH . '/core/Controller.php';

/**
 * Imágenes dentro del texto con formato.
 *
 * El botón «Insertar imagen» del editor (assets/js/editor.js) sube aquí la foto
 * y recibe su dirección para colocarla donde está el cursor. Es la misma
 * subida que el resto del panel —Upload::imagen(): tipo real con finfo, máximo
 * 4 MB, redimensionada a 1600 px de ancho— y lo que se guarde con el texto
 * pasa después por SanitizadorHtml, que solo deja img con src, alt, width y
 * height.
 *
 * Responde JSON: {"url": "..."} o {"error": "..."}.
 */
class MediosController extends Controller
{
    /**
     * Quien puede escribir en alguno de los textos que llevan el editor. El
     * editor es uno solo para todos esos módulos, así que basta con uno.
     */
    private const PERMISOS_EDITOR = [
        'paginas.editar', 'bloques.editar', 'avisos.editar', 'avisos.crear',
        'eventos.editar', 'eventos.crear', 'cursos.editar', 'cursos.crear',
        'sacramentos.editar', 'evangelio.editar', 'pastorales.editar',
    ];

    public function imagen(): void
    {
        $this->requireAuth();

        $permitido = false;
        foreach (self::PERMISOS_EDITOR as $permiso) {
            if (Auth::tienePermiso($permiso)) {
                $permitido = true;
                break;
            }
        }
        if (!$permitido) {
            $this->json(['error' => 'Tu cuenta no puede subir imágenes.'], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método no permitido.'], 405);
        }
        if (!Session::validarCsrf($_POST['_csrf'] ?? '')) {
            $this->json(['error' => 'La sesión caducó. Recarga la página e inténtalo de nuevo.'], 403);
        }

        try {
            $ruta = Upload::imagen('imagen', 'contenido', 'img');
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }

        if (!$ruta) {
            $this->json(['error' => 'No llegó ninguna imagen.'], 422);
        }

        $this->auditoria('subir', 'contenido', 0, $ruta);
        $this->json(['url' => url_activo($ruta)]);
    }
}
