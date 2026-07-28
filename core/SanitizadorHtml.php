<?php
/**
 * SanitizadorHtml — Limpia el HTML que llega de los editores del panel.
 *
 * Es la defensa principal contra XSS almacenado. Habrá varias personas con
 * cuenta —párroco, secretaría, coordinadores de pastoral— escribiendo contenido
 * con formato; no todas son expertas y ninguna debería poder inyectar un script
 * en el sitio, ni por descuido ni pegando HTML de otra página.
 *
 * Funciona con lista blanca: lo que no está permitido explícitamente se quita.
 * Usa DOMDocument, que viene en PHP y por tanto no rompe la regla de cero
 * dependencias; parsear con un analizador real evita los agujeros clásicos de
 * intentar limpiar HTML con expresiones regulares.
 *
 * IMPORTANTE: se aplica AL GUARDAR, no al mostrar. Los campos que pasan por
 * aquí se imprimen luego con echo en vez de e(); son la única excepción a la
 * regla de escapar todo, y están marcados con un comentario en cada vista.
 */
class SanitizadorHtml
{
    /** Etiqueta => atributos permitidos en ella. */
    private const PERMITIDAS = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'blockquote' => [],
        'hr'         => [],
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'width', 'height'],
        'figure'     => [],
        'figcaption' => [],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => [],
        'td'         => [],
    ];

    /**
     * Se eliminan con todo su contenido. El resto de las etiquetas no
     * permitidas solo pierden la etiqueta y conservan el texto; con estas no,
     * porque su contenido no es texto para leer.
     */
    private const ELIMINAR_COMPLETAS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math',
    ];

    public static function limpiar(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');

        // El HTML de un editor casi nunca es un documento completo ni válido.
        // Se envuelve en un contenedor propio para tener una raíz conocida y se
        // silencian los avisos del analizador, que aquí no aportan nada.
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="_raiz">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $doc->getElementById('_raiz');
        if (!$raiz instanceof DOMElement) {
            // Sin raíz utilizable no se puede garantizar la limpieza: se
            // devuelve solo el texto, que siempre es seguro.
            return trim(strip_tags($html));
        }

        self::limpiarHijos($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    /** Versión para campos que no admiten formato alguno. */
    public static function soloTexto(?string $html): string
    {
        return trim(strip_tags((string) $html));
    }

    private static function limpiarHijos(DOMElement $padre): void
    {
        // Copia del listado: se va a modificar el árbol mientras se recorre.
        foreach (iterator_to_array($padre->childNodes) as $nodo) {

            if ($nodo instanceof DOMText) {
                continue;
            }

            if (!$nodo instanceof DOMElement) {
                // Comentarios, instrucciones de proceso, secciones CDATA.
                $padre->removeChild($nodo);
                continue;
            }

            $etiqueta = strtolower($nodo->nodeName);

            if (in_array($etiqueta, self::ELIMINAR_COMPLETAS, true)) {
                $padre->removeChild($nodo);
                continue;
            }

            if (!isset(self::PERMITIDAS[$etiqueta])) {
                // Etiqueta no admitida: se conserva el texto y se pierde el
                // envoltorio. Así un <div> pegado desde otra página no borra
                // el párrafo que contiene.
                self::limpiarHijos($nodo);
                while ($nodo->firstChild) {
                    $padre->insertBefore($nodo->firstChild, $nodo);
                }
                $padre->removeChild($nodo);
                continue;
            }

            self::limpiarAtributos($nodo, self::PERMITIDAS[$etiqueta]);
            self::limpiarHijos($nodo);
        }
    }

    private static function limpiarAtributos(DOMElement $nodo, array $permitidos): void
    {
        foreach (iterator_to_array($nodo->attributes) as $atributo) {
            $nombre = strtolower($atributo->nodeName);

            if (!in_array($nombre, $permitidos, true)) {
                $nodo->removeAttribute($atributo->nodeName);
                continue;
            }

            if (($nombre === 'href' || $nombre === 'src')
                && !self::urlSegura((string) $atributo->nodeValue)) {
                $nodo->removeAttribute($atributo->nodeName);
            }
        }

        // Un enlace que abre en otra pestaña sin rel deja la página original
        // accesible al destino a través de window.opener.
        if (strtolower($nodo->nodeName) === 'a' && $nodo->getAttribute('target') === '_blank') {
            $nodo->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function urlSegura(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // Los espacios, tabuladores y caracteres de control se usan para
        // disimular esquemas peligrosos: "java\tscript:alert(1)".
        $normalizada = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $url) ?? '');

        foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $prohibido) {
            if (str_starts_with($normalizada, $prohibido)) {
                return false;
            }
        }

        // Absolutas admitidas
        if (preg_match('#^(https?://|mailto:|tel:|//)#i', $normalizada)) {
            return true;
        }

        // Relativas del propio sitio
        return (bool) preg_match('#^[a-z0-9/._\-~?=&%#+]+$#i', $url);
    }
}
