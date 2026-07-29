<?php
/**
 * Upload — Subida de imágenes y documentos desde el panel.
 *
 * Tres decisiones que importan:
 *
 * 1. El tipo se determina con finfo, leyendo el contenido real del archivo, no
 *    la extensión ni el Content-Type que manda el navegador. La extensión final
 *    se deriva del tipo detectado, así que un script renombrado a .jpg no puede
 *    conservar su nombre original.
 * 2. Los archivos se guardan en subcarpetas por año y mes. En un hosting
 *    compartido, una carpeta con miles de archivos hace inutilizable el gestor
 *    de archivos de cPanel.
 * 3. El redimensionado con GD es opcional: si la extensión no está disponible se
 *    guarda el original en vez de fallar. Nunca se pierde una subida por una
 *    extensión ausente.
 *
 * La protección de fondo no está aquí sino en uploads/.htaccess, que impide que
 * Apache ejecute cualquier cosa dentro de esa carpeta.
 */
class Upload
{
    private const MAX_IMAGEN     = 4194304;   // 4 MB
    private const MAX_DOCUMENTO  = 8388608;   // 8 MB
    private const ANCHO_MAXIMO   = 1600;      // píxeles

    private const TIPOS_IMAGEN = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const TIPOS_DOCUMENTO = [
        'application/pdf' => 'pdf',
    ];

    /**
     * Sube una imagen y devuelve su ruta relativa, o $actual si no se envió
     * ninguna nueva. Reemplaza y borra la anterior cuando corresponde.
     *
     * @param string $campo   nombre del input file
     * @param string $subdir  carpeta bajo uploads/ (avisos, eventos, personas…)
     * @param string $prefijo prefijo del nombre del archivo
     * @throws RuntimeException con un mensaje presentable al usuario
     */
    public static function imagen(string $campo, string $subdir, string $prefijo, ?string $actual = null): ?string
    {
        $archivo = $_FILES[$campo] ?? null;

        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $actual;
        }

        $ruta = self::guardar($archivo, $subdir, $prefijo, self::TIPOS_IMAGEN, self::MAX_IMAGEN);
        self::borrar($actual);

        return $ruta;
    }

    /**
     * Sube varias imágenes de un input con multiple.
     * Devuelve las rutas de las que se guardaron correctamente y acumula los
     * errores en $errores, para poder avisar sin perder las que sí funcionaron.
     */
    public static function imagenes(string $campo, string $subdir, string $prefijo, array &$errores = []): array
    {
        $archivos = $_FILES[$campo] ?? null;
        if (!$archivos || !is_array($archivos['name'])) {
            return [];
        }

        $rutas = [];
        $total = count($archivos['name']);

        for ($i = 0; $i < $total; $i++) {
            if (($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $uno = [
                'name'     => $archivos['name'][$i],
                'type'     => $archivos['type'][$i],
                'tmp_name' => $archivos['tmp_name'][$i],
                'error'    => $archivos['error'][$i],
                'size'     => $archivos['size'][$i],
            ];

            try {
                $rutas[] = self::guardar($uno, $subdir, $prefijo, self::TIPOS_IMAGEN, self::MAX_IMAGEN);
            } catch (RuntimeException $e) {
                $errores[] = $archivos['name'][$i] . ': ' . $e->getMessage();
            }
        }

        return $rutas;
    }

    /** Documentos PDF: el boletín semanal, principalmente. */
    public static function documento(string $campo, string $subdir, string $prefijo, ?string $actual = null): ?string
    {
        $archivo = $_FILES[$campo] ?? null;

        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $actual;
        }

        $ruta = self::guardar($archivo, $subdir, $prefijo, self::TIPOS_DOCUMENTO, self::MAX_DOCUMENTO);
        self::borrar($actual);

        return $ruta;
    }

    /** Borra un archivo subido. Solo actúa dentro de uploads/. */
    public static function borrar(?string $ruta): void
    {
        if (empty($ruta) || !str_starts_with($ruta, 'uploads/')) {
            return;
        }
        if (str_contains($ruta, '..')) {
            return;
        }
        $absoluta = rtrim(BASE_PATH, '/\\') . '/' . $ruta;
        if (is_file($absoluta)) {
            @unlink($absoluta);
        }
    }

    // ── Interno ─────────────────────────────────────────────────────────

    private static function guardar(array $archivo, string $subdir, string $prefijo, array $tipos, int $maximo): string
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::mensajeError((int) $archivo['error']));
        }

        if ($archivo['size'] > $maximo) {
            throw new RuntimeException('El archivo supera el máximo permitido ('
                . round($maximo / 1048576) . ' MB).');
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            throw new RuntimeException('El archivo no se recibió correctamente.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset($tipos[$mime])) {
            throw new RuntimeException('Formato no permitido. Se aceptan: '
                . implode(', ', array_map('strtoupper', array_values($tipos))) . '.');
        }

        $relativa = 'uploads/' . self::subdirSeguro($subdir) . '/' . date('Y') . '/' . date('m') . '/';
        $absoluta = rtrim(BASE_PATH, '/\\') . '/' . $relativa;

        if (!is_dir($absoluta) && !@mkdir($absoluta, 0775, true) && !is_dir($absoluta)) {
            throw new RuntimeException('No se pudo crear la carpeta de destino.');
        }

        $nombre = preg_replace('/[^a-z0-9]/', '', strtolower($prefijo))
                . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4))
                . '.' . $tipos[$mime];

        if (!move_uploaded_file($archivo['tmp_name'], $absoluta . $nombre)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        if (isset(self::TIPOS_IMAGEN[$mime])) {
            self::redimensionar($absoluta . $nombre, $mime);
        }

        return $relativa . $nombre;
    }

    private static function subdirSeguro(string $subdir): string
    {
        $limpio = preg_replace('/[^a-z0-9_]/', '', strtolower($subdir)) ?? '';
        return $limpio !== '' ? $limpio : 'varios';
    }

    /**
     * Reduce la imagen si excede el ancho máximo. Sin GD no hace nada: la foto
     * queda con su tamaño original, que es preferible a rechazar la subida.
     */
    private static function redimensionar(string $archivo, string $mime): void
    {
        if (!function_exists('imagecreatetruecolor') || $mime === 'image/gif') {
            return;   // Los GIF pueden estar animados: mejor no tocarlos.
        }

        $tamano = @getimagesize($archivo);
        if (!$tamano || $tamano[0] <= self::ANCHO_MAXIMO) {
            return;
        }

        [$ancho, $alto] = $tamano;
        $nuevoAncho = self::ANCHO_MAXIMO;
        $nuevoAlto  = (int) round($alto * ($nuevoAncho / $ancho));

        $origen = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($archivo),
            'image/png'  => @imagecreatefrompng($archivo),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($archivo) : false,
            default      => false,
        };
        if (!$origen) {
            return;
        }

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // El PNG y el WEBP pueden tener transparencia; sin esto se vuelve negra.
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
        }

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        match ($mime) {
            'image/jpeg' => imagejpeg($destino, $archivo, 85),
            'image/png'  => imagepng($destino, $archivo, 8),
            'image/webp' => function_exists('imagewebp') ? imagewebp($destino, $archivo, 85) : null,
            default      => null,
        };

        imagedestroy($origen);
        imagedestroy($destino);
    }

    private static function mensajeError(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
            UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió antes de terminar.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal configurada.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida.',
            default               => 'No se pudo subir el archivo (código ' . $codigo . ').',
        };
    }
}
