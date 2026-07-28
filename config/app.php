<?php
// ============================================================
// Configuración general de la aplicación
// ============================================================

define('APP_NAME',   'Parroquia Nuestra Señora de la Paz');
define('APP_CORTO',  'Parroquia N. S. de la Paz');
define('APP_VERSION', '0.1.0');

// Subcarpeta donde vive el sitio: '/WebParroquia' en XAMPP, '' en la raíz de un
// dominio. Se deduce de dónde está index.php, que es lo que cambia entre un
// entorno y otro; así no hay que acordarse de editarla al desplegar, que es de
// donde salen las rutas rotas. Para fijarla a mano, sustituir el bloque por:
//     define('APP_URL', '/WebParroquia');
$directorioBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
define('APP_URL', ($directorioBase === '/' || $directorioBase === '.' || $directorioBase === '')
    ? ''
    : rtrim($directorioBase, '/'));
unset($directorioBase);

// URLs amigables (/pastorales/coro en vez de ?area=publico&modulo=pastorales…).
// Requiere mod_rewrite y el .htaccess incluido. Si el hosting no lo soporta,
// poner en false: los helpers url_publica()/url_admin() emiten query strings y
// el sitio funciona igual. Ninguna vista construye URLs a mano precisamente
// para que este interruptor baste.
define('URLS_AMIGABLES', true);

// Segmento de URL distinto al nombre interno del módulo, cuando conviene que
// la dirección sea más legible. Clave = módulo interno, valor = segmento.
define('ALIAS_URL', [
    'nosotros' => 'quienes-somos',
]);

// Zona horaria
date_default_timezone_set('America/Hermosillo');

// Mostrar errores solo en desarrollo
define('APP_DEBUG', true);           // En producción: false

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ------------------------------------------------------------
// Protección de datos personales
// ------------------------------------------------------------
// Versión del aviso de privacidad vigente. Cada solicitud, inscripción y
// mensaje guarda la versión que la persona aceptó; sin esto no se puede
// demostrar a qué dio su consentimiento. Al publicar un aviso nuevo hay que
// incrementar esta constante y la clave 'aviso_privacidad_version' de la
// tabla configuracion. Ver docs/PRIVACIDAD.md
define('AVISO_VERSION', '1.0');

// ------------------------------------------------------------
// Roles del sistema
// ------------------------------------------------------------
define('ROL_ADMIN',       'admin');        // Todo, incluidos usuarios y configuración
define('ROL_EDITOR',      'editor');       // Todo el contenido; publica y modera
define('ROL_COORDINADOR', 'coordinador');  // Contenido de su pastoral; no publica
define('ROL_SECRETARIA',  'secretaria');   // Trámites y mensajes; no edita el sitio

define('ROLES_NOMBRES', [
    ROL_ADMIN       => 'Administrador',
    ROL_EDITOR      => 'Editor',
    ROL_COORDINADOR => 'Coordinador de pastoral',
    ROL_SECRETARIA  => 'Secretaría',
]);

// ------------------------------------------------------------
// Permisos por rol — notación modulo.accion, comodín '*'
// ------------------------------------------------------------
// Esta matriz responde "¿qué acción puede hacer?". La pregunta "¿sobre qué
// registro?" se resuelve aparte, con Auth::puedeSobrePastoral(), para que la
// matriz no crezca con una entrada por pastoral. Ver docs/ARQUITECTURA.md
//
// Los permisos de módulos que aún no existen están comentados y se irán
// activando conforme avancen las etapas del plan.
define('PERMISOS', [

    ROL_ADMIN => ['*'],

    // usuarios.*, auditoria.* y configuracion.* no aparecen en ningún otro
    // rol de esta matriz, a propósito: el plan reserva la gestión de cuentas,
    // la configuración global y la bitácora completa exclusivamente al
    // administrador. Llegan solo por el comodín '*' de arriba.
    ROL_EDITOR => [
        'panel.ver',
        'bloques.ver', 'bloques.editar',
        'paginas.ver', 'paginas.editar',
        'horarios.ver', 'horarios.editar',
        'personas.ver', 'personas.editar',
        'organigrama.ver', 'organigrama.editar',
        // El editor no toca la configuración global: los datos de contacto, el
        // logo y las claves legales son responsabilidad del administrador.
        // El editor tampoco ve los mensajes de contacto: son datos personales
        // de quien escribe, y esa lectura queda para administración y secretaría.
        'avisos.ver', 'avisos.crear', 'avisos.editar', 'avisos.eliminar', 'avisos.publicar',
        'eventos.ver', 'eventos.crear', 'eventos.editar', 'eventos.eliminar', 'eventos.publicar',
        'galeria.ver', 'galeria.crear', 'galeria.editar', 'galeria.eliminar', 'galeria.publicar',
        'carrusel.ver', 'carrusel.editar',
        'pastorales.ver', 'pastorales.crear', 'pastorales.editar', 'pastorales.eliminar',
        'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
        'sacramentos.ver', 'sacramentos.editar',
        'cursos.ver', 'cursos.crear', 'cursos.editar', 'cursos.eliminar', 'cursos.publicar',
    ],

    // Sin los permisos *.publicar a propósito: lo que escribe un coordinador
    // queda en borrador hasta que un editor lo revisa. El alcance sobre CUÁL
    // pastoral puede tocar no vive aquí, sino en Auth::puedeSobrePastoral() y
    // Controller::requireAlcancePastoral(). Ver docs/ARQUITECTURA.md
    ROL_COORDINADOR => [
        'panel.ver',
        'avisos.ver', 'avisos.crear', 'avisos.editar',
        'eventos.ver', 'eventos.crear', 'eventos.editar',
        'galeria.ver', 'galeria.crear', 'galeria.eliminar',
        'pastorales.ver', 'pastorales.editar',
        'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
        'cursos.ver',
    ],

    // Único rol, junto con el administrador, que ve datos personales.
    ROL_SECRETARIA => [
        'panel.ver',
        'mensajes.ver', 'mensajes.editar',
        'solicitudes.ver', 'solicitudes.cambiar_estado', 'solicitudes.exportar',
        'inscripciones.ver', 'inscripciones.editar', 'inscripciones.exportar',
        // 'cursos.ver', 'avisos.ver', 'eventos.ver',
    ],
]);
