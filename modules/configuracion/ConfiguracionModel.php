<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * ConfiguracionModel — Datos globales de la parroquia en pares clave/valor.
 *
 * La constante CAMPOS es la fuente de verdad del formulario: declara etiqueta,
 * tipo, grupo y texto de ayuda de cada clave. Añadir un dato nuevo al panel es
 * agregar una línea aquí y su semilla en install.sql; no hay que tocar la vista.
 */
class ConfiguracionModel extends Model
{
    /** Secciones del formulario: clave => [título, icono, descripción]. */
    public const GRUPOS = [
        'general'    => ['Identidad',            'bi-house-heart',  'Nombre, logotipo e imágenes de la parroquia.'],
        'contacto'   => ['Contacto y ubicación', 'bi-geo-alt',      'Lo que aparece en el pie del sitio y en la página de contacto.'],
        'redes'      => ['Redes sociales',       'bi-share',        'Se muestran como iconos en el pie. Deja en blanco las que no se usen.'],
        'seo'        => ['Buscadores',           'bi-search',       'Cómo se presenta el sitio en Google y al compartirlo en redes.'],
        'legal'      => ['Legal',                'bi-shield-check', 'Control del aviso de privacidad y de la conservación de datos.'],
        'secciones'  => ['Secciones del sitio',  'bi-sliders',      'Enciende o apaga partes opcionales del menú público.'],
    ];

    /** clave => [etiqueta, tipo, grupo, ayuda] */
    public const CAMPOS = [
        'parroquia_nombre'   => ['Nombre de la parroquia', 'texto',  'general',
                                 'Aparece en el título del navegador, en el pie y al compartir el sitio.'],
        'parroquia_diocesis' => ['Diócesis o decanato',    'texto',  'general', ''],
        'logo'               => ['Logotipo',               'imagen', 'general',
                                 'Se muestra en la barra superior del sitio. Se recomienda PNG con fondo transparente, de unos 200 píxeles de ancho.'],
        'favicon'            => ['Icono del navegador',    'imagen', 'general',
                                 'El icono pequeño de la pestaña. Cuadrado, de 64 × 64 píxeles.'],
        'organigrama_imagen' => ['Organigrama en imagen',  'imagen', 'general',
                                 'Opcional. Si subes una imagen, se mostrará en lugar del organigrama construido desde el panel.'],

        'direccion'          => ['Dirección',              'texto',    'contacto', 'Calle y número.'],
        'ciudad'             => ['Ciudad y estado',        'texto',    'contacto', ''],
        'cp'                 => ['Código postal',          'texto',    'contacto', ''],
        'telefono'           => ['Teléfono',               'telefono', 'contacto', 'Se convierte en un enlace para llamar desde el celular.'],
        'whatsapp'           => ['WhatsApp',               'telefono', 'contacto', 'Solo números, con clave de país. Ejemplo: 526621234567'],
        'email'              => ['Correo electrónico',     'email',    'contacto', 'Correo de la oficina parroquial.'],
        'horario_oficina'    => ['Horario de oficina',     'parrafo',  'contacto', 'Una línea por cada bloque de horario.'],
        'mapa_embed'         => ['Mapa',                   'mapa',     'contacto',
                                 'En Google Maps: Compartir → Insertar un mapa → Copiar HTML. Pega aquí lo copiado.'],
        'latitud'            => ['Latitud',                'texto',    'contacto', 'Opcional.'],
        'longitud'           => ['Longitud',               'texto',    'contacto', 'Opcional.'],

        'facebook'           => ['Facebook',  'url', 'redes',
                                 'Además del icono del pie, la portada muestra un pequeño recuadro con las últimas publicaciones de esta página.'],
        'instagram'          => ['Instagram', 'url', 'redes', ''],
        'youtube'            => ['YouTube',   'url', 'redes', ''],

        'meta_descripcion'   => ['Descripción del sitio', 'parrafo', 'seo',
                                 'Dos líneas que resuman la parroquia. Es lo que Google muestra debajo del título.'],
        'og_imagen'          => ['Imagen al compartir',   'imagen',  'seo',
                                 'La que aparece cuando alguien comparte el sitio en redes. Horizontal, de 1200 × 630 píxeles.'],

        'aviso_privacidad_version'    => ['Versión del aviso de privacidad', 'solo_lectura', 'legal',
                                          'Se cambia en config/app.php al publicar un aviso nuevo. Cada inscripción y mensaje guarda la versión que aceptó la persona.'],

        'cursos_activo'      => ['Mostrar "Cursos" en el menú', 'booleano', 'secciones',
                                 'Si lo apagas, el enlace desaparece del menú público y la sección deja de estar disponible, aunque haya cursos publicados. No afecta el panel: ahí puedes seguir dando de alta cursos.'],
    ];

    /** Claves cuyo valor es una ruta de imagen subida. */
    public const CAMPOS_IMAGEN = ['logo', 'favicon', 'organigrama_imagen', 'og_imagen'];

    public function todas(): array
    {
        $valores = [];
        foreach ($this->fetchAll('SELECT clave, valor FROM configuracion') as $fila) {
            $valores[$fila['clave']] = (string) ($fila['valor'] ?? '');
        }
        return $valores;
    }

    public function valor(string $clave): string
    {
        return (string) ($this->fetchColumn(
            'SELECT valor FROM configuracion WHERE clave = :clave',
            [':clave' => $clave]
        ) ?: '');
    }

    /**
     * Guarda un valor. Solo acepta claves declaradas en CAMPOS: aunque el POST
     * traiga otras, no se escriben.
     */
    public function guardar(string $clave, ?string $valor): void
    {
        if (!isset(self::CAMPOS[$clave])) {
            return;
        }
        $this->execute(
            'UPDATE configuracion SET valor = :valor WHERE clave = :clave',
            [':valor' => $valor, ':clave' => $clave]
        );
    }

    /** Campos de un grupo, en el orden en que están declarados. */
    public static function camposDe(string $grupo): array
    {
        return array_filter(
            self::CAMPOS,
            static fn (array $campo): bool => $campo[2] === $grupo
        );
    }
}
