<?php
/**
 * helpers.php — Funciones de apoyo para vistas y controladores.
 *
 * Las funciones de URL son obligatorias: ninguna vista debe construir una
 * dirección a mano. Son las que permiten que la constante URLS_AMIGABLES
 * baste para cambiar entre /pastorales/coro y ?area=publico&modulo=…
 */

if (!function_exists('e')) {

    /**
     * Escapa texto para imprimirlo en HTML. Se usa en TODO eco de las vistas.
     * La única excepción son los campos de contenido enriquecido, que ya vienen
     * saneados desde el guardado; esos van con echo y llevan comentario.
     */
    function e(?string $texto): string
    {
        return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
    }

    // ── URLs ────────────────────────────────────────────────────────────

    /** Raíz del sitio, sin barra final. Cadena vacía si vive en la raíz del dominio. */
    function url_base(): string
    {
        return rtrim(APP_URL, '/');
    }

    /**
     * Antepone esquema y dominio a una URL ya construida por url_publica() o
     * url_activo(). Necesaria para sitemap.xml (el protocolo exige rutas
     * absolutas) y para og:image/og:url/canonical, que las redes sociales y
     * los buscadores no siempre resuelven bien si llegan relativas.
     *
     * Se deduce del propio request, igual que APP_URL: así no hace falta una
     * clave de configuración "dominio del sitio" que alguien pueda olvidar
     * actualizar el día que cambie de dominio.
     */
    function url_absoluta(string $rutaRelativa): string
    {
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $esquema . '://' . $host . $rutaRelativa;
    }

    /** URL de un archivo de assets o uploads: url_activo('assets/css/app.css') */
    function url_activo(string $ruta): string
    {
        return url_base() . '/' . ltrim($ruta, '/');
    }

    /**
     * URL de una página del sitio público.
     *
     *   url_publica('inicio')                            → /
     *   url_publica('nosotros')                          → /quienes-somos
     *   url_publica('pastorales', ['slug' => 'coro'])    → /pastorales/coro
     *   url_publica('avisos', ['pagina' => 2])           → /avisos?pagina=2
     *
     * La acción solo viaja como segmento si hay slug: la regla de reescritura
     * lee los segmentos en el orden módulo/slug/acción, y una acción suelta
     * en la segunda posición se interpretaría como slug.
     */
    function url_publica(string $modulo = 'inicio', array $params = []): string
    {
        if (!URLS_AMIGABLES) {
            $query = array_merge(['area' => 'publico', 'modulo' => $modulo], $params);
            return url_base() . '/index.php?' . http_build_query($query);
        }

        $slug   = $params['slug']   ?? null;
        $accion = $params['accion'] ?? null;
        unset($params['slug'], $params['accion']);

        if ($modulo === 'inicio' && $slug === null && $accion === null) {
            $ruta = url_base() . '/';
        } else {
            $segmentos = [ALIAS_URL[$modulo] ?? $modulo];
            if ($slug !== null) {
                $segmentos[] = $slug;
                if ($accion !== null) {
                    $segmentos[] = $accion;
                }
            } elseif ($accion !== null) {
                $params['accion'] = $accion;
            }
            $ruta = url_base() . '/' . implode('/', $segmentos);
        }

        return $params ? $ruta . '?' . http_build_query($params) : $ruta;
    }

    /**
     * URL de una pantalla del panel.
     *
     *   url_admin()                                  → /admin/panel
     *   url_admin('usuarios')                        → /admin/usuarios
     *   url_admin('usuarios', 'editar', ['id' => 3]) → /admin/usuarios/editar?id=3
     */
    function url_admin(string $modulo = 'panel', string $accion = '', array $params = []): string
    {
        if (!URLS_AMIGABLES) {
            $query = ['area' => 'admin', 'modulo' => $modulo];
            if ($accion !== '') {
                $query['accion'] = $accion;
            }
            return url_base() . '/index.php?' . http_build_query(array_merge($query, $params));
        }

        $ruta = url_base() . '/admin/' . $modulo . ($accion !== '' ? '/' . $accion : '');
        return $params ? $ruta . '?' . http_build_query($params) : $ruta;
    }

    /**
     * URL canónica para el atributo action de un formulario.
     *
     * Los POST nunca van a una URL amigable: la reescritura puede introducir
     * ambigüedades y complica el retorno con redirectBack(). Siempre query
     * string, en las dos áreas.
     */
    function url_post(string $area, string $modulo, string $accion, array $params = []): string
    {
        $query = array_merge(
            ['area' => $area, 'modulo' => $modulo, 'accion' => $accion],
            $params
        );
        return url_base() . '/index.php?' . http_build_query($query);
    }

    // ── Fechas ──────────────────────────────────────────────────────────

    /**
     * Fecha en español sin depender de setlocale, que en Windows y en hosting
     * compartido se comporta de forma distinta.  '2026-07-27' → '27 de julio de 2026'
     */
    function fecha_larga(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return '';
        }
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1]
             . ' de ' . date('Y', $ts);
    }

    /** '2026-07-27' → 'lunes 27 de julio' */
    function fecha_con_dia(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return '';
        }
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return nombre_dia((int) date('w', $ts)) . ' ' . (int) date('j', $ts)
             . ' de ' . $meses[(int) date('n', $ts) - 1];
    }

    /** 0 = domingo … 6 = sábado, tal como se guarda en la tabla horarios. */
    function nombre_dia(int $dia): string
    {
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        return $dias[$dia] ?? '';
    }

    /** '2026-08-15' → 'ago'. Para tarjetas de fecha destacada. */
    function mes_abreviado(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return '';
        }
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return $meses[(int) date('n', $ts) - 1];
    }

    /** '19:30:00' → '7:30 p. m.' */
    function hora_corta(?string $hora): string
    {
        if (empty($hora)) {
            return '';
        }
        $ts = strtotime($hora);
        if ($ts === false) {
            return '';
        }
        $sufijo = (int) date('G', $ts) < 12 ? 'a. m.' : 'p. m.';
        return ltrim(date('g:i', $ts), '0') . ' ' . $sufijo;
    }

    /** Recorta un texto a $largo caracteres respetando palabras. */
    function resumen(?string $texto, int $largo = 160): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', strip_tags($texto ?? '')));
        if (mb_strlen($texto) <= $largo) {
            return $texto;
        }
        $corte = mb_substr($texto, 0, $largo);
        $espacio = mb_strrpos($corte, ' ');
        return rtrim($espacio !== false ? mb_substr($corte, 0, $espacio) : $corte, ',.;:') . '…';
    }

    // ── Teléfonos ───────────────────────────────────────────────────────

    /**
     * Un teléfono escrito como se dice → los dígitos que exige un enlace de
     * WhatsApp: internacional, sin '+', sin espacios ni paréntesis.
     *
     * Los teléfonos de la parroquia conviven en tres formatos —'6622240453',
     * '662 220 7214', '(662) 220 7214'— y ninguno trae clave de país, así que
     * a los de diez dígitos se les antepone LADA_PAIS. No se toca el dato
     * guardado: la normalización es al dibujar, para que cada quien siga
     * leyendo el número como lo escribió.
     *
     * Devuelve '' cuando lo capturado no alcanza para escribirle a nadie, y
     * quien llama entonces no dibuja el botón. Nunca inventa un número: más
     * vale sin botón que un enlace a la conversación de un desconocido.
     */
    function telefono_internacional(?string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        // '00' es el prefijo de salida internacional, no parte del número.
        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }
        // Diez dígitos es un número nacional: le falta la clave de país.
        if (strlen($digitos) === 10) {
            $digitos = LADA_PAIS . $digitos;
        }
        // Once es el mínimo con clave de país; quince, el máximo de E.164. Los
        // '521…' de trece que dejó el celular mexicano de antes de 2019 caen
        // dentro y se respetan: WhatsApp los sigue resolviendo, y reescribir lo
        // que alguien tecleó a propósito arriesga más de lo que arregla.
        return (strlen($digitos) >= 11 && strlen($digitos) <= 15) ? $digitos : '';
    }

    /**
     * ¿Se puede capturar esto como teléfono? Es el control del lado del
     * servidor; el placeholder del campo es la cortesía.
     *
     * Vacío es válido: casi todos los campos de teléfono son opcionales, y
     * quien exige el dato lo comprueba por su cuenta —tutor_telefono cuando el
     * inscrito es menor, por ejemplo—.
     *
     * Es más estricta que telefono_internacional(), y a propósito. Esa función
     * tiene que arreglárselas con lo que ya está guardado desde antes de que
     * existiera esta regla, así que acepta cualquier cosa de once a quince
     * dígitos; aquí, con el dato todavía en la mano de quien lo escribe, se
     * puede pedir bien:
     *
     *   - Diez dígitos, con o sin espacios, guiones o paréntesis. Es el caso
     *     normal: '6622240453', '662 220 7214', '(662) 220 7214' — los tres
     *     formatos que la parroquia ya tiene guardados pasan sin tocarlos.
     *   - De otro país, solo si lo dice un '+' o un '00' delante. Así un número
     *     de once dígitos deja de ser ambiguo entre un extranjero y un local
     *     mal teclado.
     *   - Doce o trece dígitos que empiezan con LADA_PAIS, para quien escribe
     *     su propio número con el 52 por delante y sin '+'.
     *
     * Y cualquier letra lo invalida. Eso es lo que atrapa la extensión pegada
     * al número —'662 220 7214 ext 12'—, que da doce dígitos, pasaría por
     * largo y armaría un enlace de WhatsApp que no lleva a nadie.
     */
    function telefono_valido(?string $telefono): bool
    {
        $texto = trim((string) $telefono);
        if ($texto === '') {
            return true;
        }
        // Solo lo que se usa para escribir un teléfono.
        if (!preg_match('/^\+?[0-9 ()\-.]+$/', $texto)) {
            return false;
        }
        $digitos = preg_replace('/\D+/', '', $texto);
        $largo   = strlen($digitos);

        // Internacional declarado: el prefijo lo dice, no lo adivinamos.
        if (str_starts_with($texto, '+') || str_starts_with($digitos, '00')) {
            $sinSalida = str_starts_with($digitos, '00') ? substr($digitos, 2) : $digitos;
            return strlen($sinSalida) >= 11 && strlen($sinSalida) <= 15;
        }
        // Nacional con su clave de país por delante, sin '+'.
        if (($largo === 12 || $largo === 13) && str_starts_with($digitos, LADA_PAIS)) {
            return true;
        }
        return $largo === 10;
    }

    /**
     * El enlace "click to chat" de WhatsApp, con el mensaje ya escrito. No
     * manda nada: abre la conversación en WhatsApp Web o en la app, y quien
     * envía es la persona desde su propio WhatsApp. Ver docs/ARQUITECTURA.md
     *
     * '' si el teléfono no se pudo normalizar.
     */
    function whatsapp_enlace(?string $telefono, string $mensaje = ''): string
    {
        $numero = telefono_internacional($telefono);
        if ($numero === '') {
            return '';
        }
        // rawurlencode y no urlencode: WhatsApp quiere los espacios como %20;
        // con '+' llegan literales al chat.
        return 'https://wa.me/' . $numero
             . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
    }

    /** Href de `tel:` en formato internacional, para que marque también desde fuera. */
    function tel_enlace(?string $telefono): string
    {
        $numero = telefono_internacional($telefono);
        return $numero !== '' ? 'tel:+' . $numero : '';
    }

    /**
     * El botón de WhatsApp de un integrante de una pastoral, listo para pintar
     * —devuelve HTML, como icono_cruz() o badge_escalon()—.
     *
     * Cadena vacía, y no un botón deshabilitado, cuando no hay teléfono
     * utilizable: es la misma regla que rige los permisos, un botón que no
     * aplica no se dibuja. Hoy es el caso común, no el raro: coristas y
     * proclamadores tienen el teléfono en NULL casi todos.
     *
     * El nombre viaja en data-nombre y el número en el href; assets/js/whatsapp.js
     * los usa para meter el mensaje que se escribe una vez arriba del listado.
     */
    function boton_whatsapp(?string $telefono, string $nombre = '', string $clases = 'btn btn-sm btn-outline-success'): string
    {
        $url = whatsapp_enlace($telefono);
        if ($url === '') {
            return '';
        }
        $titulo = $nombre !== '' ? 'Escribir por WhatsApp a ' . $nombre : 'Escribir por WhatsApp';
        return '<a href="' . e($url) . '" target="_blank" rel="noopener"'
             . ' class="' . e($clases) . ' js-whatsapp" data-nombre="' . e($nombre) . '"'
             . ' title="' . e($titulo) . '"><i class="bi bi-whatsapp"></i></a>';
    }

    // ── Imágenes ────────────────────────────────────────────────────────

    /**
     * Avatar de iniciales como SVG en Data URI. Local, sin pedir nada a internet.
     */
    function avatar_iniciales(string $nombre, int $size = 64, string $bg = '1e4d8b', string $fg = 'ffffff'): string
    {
        $partes  = preg_split('/\s+/', trim($nombre)) ?: [];
        $primera = $partes[0] ?? '';
        $ultima  = count($partes) > 1 ? $partes[count($partes) - 1] : '';
        $ini     = mb_strtoupper(mb_substr($primera, 0, 1) . mb_substr($ultima, 0, 1));
        if ($ini === '') {
            $ini = '?';
        }
        $fuente = round($size / 2.3, 1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
             . '<rect width="' . $size . '" height="' . $size . '" rx="' . $size . '" fill="#' . $bg . '"/>'
             . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" '
             . 'font-family="Segoe UI, Arial, sans-serif" font-size="' . $fuente . '" fill="#' . $fg . '">'
             . e($ini) . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Foto de la persona, o su avatar de iniciales si no tiene. */
    function foto_o_avatar(?string $foto, string $nombre, int $size = 64): string
    {
        return !empty($foto) ? url_activo($foto) : avatar_iniciales($nombre, $size);
    }

    /**
     * En cuál de los ESTADOS_PUBLICACION está un aviso o un curso. Los dos
     * booleanos se leen siempre juntos y en este orden, así que vale la pena
     * preguntarlo en un solo sitio: `publicado` implica `publicado_interno`
     * (chk_avi_escalon / chk_cur_escalon), de modo que basta mirar el de fuera
     * primero.
     */
    function estado_publicacion(array $fila): string
    {
        if (!empty($fila['publicado'])) {
            return 'publico';
        }
        return !empty($fila['publicado_interno']) ? 'interno' : 'borrador';
    }

    /** La etiqueta de color del escalón, igual en el listado de avisos y en el de cursos. */
    function badge_escalon(array $fila): string
    {
        $estado = estado_publicacion($fila);
        $color  = ['borrador' => 'warning', 'interno' => 'info', 'publico' => 'success'][$estado];
        $corta  = ['borrador' => 'Borrador', 'interno' => 'De la pastoral', 'publico' => 'En la página'][$estado];

        return '<span class="badge bg-' . $color . '-subtle text-' . $color . '-emphasis" title="'
             . e(ESTADOS_PUBLICACION[$estado]) . '">' . e($corta) . '</span>';
    }

    /** Rectángulo gris con leyenda, para tarjetas sin imagen. */
    function placeholder_rect(string $texto = '', int $w = 400, int $h = 240): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
             . '<rect width="' . $w . '" height="' . $h . '" fill="#e9ecef"/>'
             . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" '
             . 'font-family="Segoe UI, Arial, sans-serif" font-size="' . max(12, (int) ($h / 10)) . '" fill="#8a939b">'
             . e($texto) . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Imagen del registro, o un placeholder con leyenda. */
    function imagen_o_placeholder(?string $imagen, string $leyenda = '', int $w = 400, int $h = 240): string
    {
        return !empty($imagen) ? url_activo($imagen) : placeholder_rect($leyenda, $w, $h);
    }

    // ── Páginas libres ──────────────────────────────────────────────────

    /**
     * ¿Está publicada esta página? La usa el pie del sitio para no enlazar al
     * aviso de privacidad mientras siga siendo un borrador. Vive aquí y no en
     * PaginaModel porque el pie se dibuja en TODAS las páginas públicas, y
     * cargar un modelo de módulo desde el arranque global rompería la regla de
     * que los módulos solo se cargan cuando su ruta se despacha.
     */
    function pagina_publicada(string $slug): bool
    {
        static $cache = [];

        if (!array_key_exists($slug, $cache)) {
            try {
                $stmt = Database::getInstance()->prepare(
                    'SELECT COUNT(*) FROM paginas WHERE slug = :slug AND publicada = 1'
                );
                $stmt->execute([':slug' => $slug]);
                $cache[$slug] = (int) $stmt->fetchColumn() > 0;
            } catch (PDOException $e) {
                $cache[$slug] = false;
            }
        }

        return $cache[$slug];
    }

    /**
     * Páginas libres marcadas con «Mostrar en el menú», ya publicadas y en su
     * orden. Vive aquí por lo mismo que pagina_publicada(): el menú se dibuja
     * en TODAS las páginas públicas, así que no puede depender de que el
     * módulo de páginas esté cargado.
     */
    function paginas_del_menu(): array
    {
        static $cache = null;

        if ($cache === null) {
            try {
                $cache = Database::getInstance()->query(
                    'SELECT slug, titulo FROM paginas
                      WHERE publicada = 1 AND en_menu = 1
                      ORDER BY orden, titulo'
                )->fetchAll();
            } catch (PDOException $e) {
                $cache = [];
            }
        }

        return $cache;
    }

    /**
     * Cruz latina (la de Cristo: travesaño en el tercio superior, no un "+"
     * centrado) como SVG inline. Bootstrap Icons 1.11 no trae ningún ícono
     * de temática religiosa —ni "cross" ni "church"—, así que en vez de
     * agregar una librería nueva solo por un ícono, se dibuja a mano con el
     * mismo criterio visual que el resto (hereda color con currentColor, se
     * ajusta como texto con 1em).
     */
    function icono_cruz(string $clases = ''): string
    {
        $clase = $clases !== '' ? ' class="' . e($clases) . '"' : '';
        return '<svg' . $clase . ' xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" '
             . 'viewBox="0 0 16 16" fill="currentColor" style="vertical-align:-0.125em">'
             . '<path d="M7 0h2v4h2v2H9v10H7V6H3V4h4z"/></svg>';
    }
}
