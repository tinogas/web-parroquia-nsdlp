<?php
// ============================================================
// Configuración general de la aplicación
// ============================================================

define('APP_NAME',   'Parroquia Nuestra Señora de la Paz');
define('APP_CORTO',  'Parroquia N. S. de la Paz');
define('APP_VERSION', '0.1.2');

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
// Versión del aviso de privacidad vigente. Cada inscripción y mensaje guarda
// la versión que la persona aceptó; sin esto no se puede
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

// Administrador y Consulta de una pastoral con módulo propio (issue de
// revisión de módulos): mismo mecanismo de alcance por pastoral que
// Coordinador (ver ROLES_CON_ALCANCE_PASTORAL más abajo), pero con nombre
// explícito en vez de "Coordinador + pastoral asignada", para que quede
// claro de un vistazo qué administra cada cuenta. "Administrador X" tiene
// el mismo alcance que Coordinador más control total de su módulo
// específico; "Consulta X" es de solo lectura (para que un ministro,
// catequista o lector de a pie entre solo a ver su propio calendario).
define('ROL_ADMIN_MESC',           'admin_mesc');
define('ROL_CONSULTA_MESC',        'consulta_mesc');
define('ROL_ADMIN_CATEQUESIS',     'admin_catequesis');
define('ROL_CONSULTA_CATEQUESIS',  'consulta_catequesis');
define('ROL_ADMIN_LECTOR',         'admin_lector');
define('ROL_CONSULTA_LECTOR',      'consulta_lector');

define('ROLES_NOMBRES', [
    ROL_ADMIN               => 'Administrador',
    ROL_EDITOR              => 'Editor',
    ROL_COORDINADOR         => 'Coordinador de pastoral',
    ROL_SECRETARIA          => 'Secretaría',
    ROL_ADMIN_MESC          => 'Administrador MESC',
    ROL_CONSULTA_MESC       => 'Consulta MESC',
    ROL_ADMIN_CATEQUESIS    => 'Administrador Catequesis',
    ROL_CONSULTA_CATEQUESIS => 'Consulta Catequesis',
    ROL_ADMIN_LECTOR        => 'Administrador Lector',
    ROL_CONSULTA_LECTOR     => 'Consulta Lector',
]);

/**
 * Roles cuyo acceso queda acotado a las pastorales/centros que se le asignen
 * al crear la cuenta (Auth::pastoralesPermitidas()/centrosPermitidos(), vía
 * la tabla usuarios_pastorales/usuarios_centros). Se usa en el formulario de
 * usuarios (para mostrar el checklist de pastorales/centros) y al guardar
 * (para saber si hay que sincronizarlas). Coordinador es genérico —sirve
 * para cualquier pastoral sin módulo propio—; los seis roles de Administrador
 * y Consulta son la versión con nombre explícito para las pastorales que sí
 * tienen un módulo dedicado (MESC, Catequesis, Lector).
 */
define('ROLES_CON_ALCANCE_PASTORAL', [
    ROL_COORDINADOR,
    ROL_ADMIN_MESC, ROL_CONSULTA_MESC,
    ROL_ADMIN_CATEQUESIS, ROL_CONSULTA_CATEQUESIS,
    ROL_ADMIN_LECTOR, ROL_CONSULTA_LECTOR,
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

    // usuarios.*, auditoria.*, configuracion.* y respaldos.* no aparecen en
    // ningún otro rol de esta matriz, a propósito: el plan reserva la gestión
    // de cuentas, la configuración global, la bitácora completa y los
    // respaldos de la base de datos exclusivamente al administrador. Llegan
    // solo por el comodín '*' de arriba.
    ROL_EDITOR => [
        'panel.ver',
        'bloques.ver', 'bloques.editar',
        'paginas.ver', 'paginas.editar',
        'horarios.ver', 'horarios.editar',
        'centros.ver', 'centros.editar',
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
        'documentos.ver', 'documentos.crear', 'documentos.eliminar',
        'mesc.ver', 'mesc.crear', 'mesc.editar', 'mesc.eliminar',
        'catequesis.ver', 'catequesis.crear', 'catequesis.editar', 'catequesis.eliminar',
        'lector.ver', 'lector.crear', 'lector.editar', 'lector.eliminar',
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
        'documentos.ver', 'documentos.crear', 'documentos.eliminar',
        // mesc.* también respeta el alcance por pastoral: solo quien administra
        // la pastoral de Ministros Extraordinarios de la Comunión (directo o
        // por centro/sede) ve o toca estas visitas. No se le da a secretaría:
        // es una actividad de la propia pastoral, no un trámite administrativo,
        // y es el primer dato sensible (estado de salud) que maneja el sistema.
        'mesc.ver', 'mesc.crear', 'mesc.editar', 'mesc.eliminar',
        'catequesis.ver', 'catequesis.crear', 'catequesis.editar', 'catequesis.eliminar',
        'lector.ver', 'lector.crear', 'lector.editar', 'lector.eliminar',
        'cursos.ver',
    ],

    // Único rol, junto con el administrador, que ve datos personales.
    ROL_SECRETARIA => [
        'panel.ver',
        'mensajes.ver', 'mensajes.editar',
        'inscripciones.ver', 'inscripciones.editar', 'inscripciones.exportar',
        // 'cursos.ver', 'avisos.ver', 'eventos.ver',
    ],

    // Administrador/Consulta de una pastoral con módulo propio: mismo
    // alcance de contenido que Coordinador (avisos, eventos, galería,
    // pastorales, actividades, documentos), más control total (Admin) o
    // solo lectura (Consulta) del módulo específico de esa pastoral. El
    // alcance sobre CUÁL pastoral se resuelve igual que Coordinador, vía
    // Auth::puedeSobrePastoral() — ver ROLES_CON_ALCANCE_PASTORAL arriba.
    ROL_ADMIN_MESC => [
        'panel.ver',
        'avisos.ver', 'avisos.crear', 'avisos.editar',
        'eventos.ver', 'eventos.crear', 'eventos.editar',
        'galeria.ver', 'galeria.crear', 'galeria.eliminar',
        'pastorales.ver', 'pastorales.editar',
        'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
        'documentos.ver', 'documentos.crear', 'documentos.eliminar',
        'mesc.ver', 'mesc.crear', 'mesc.editar', 'mesc.eliminar',
        'cursos.ver',
    ],
    ROL_CONSULTA_MESC => ['panel.ver', 'mesc.ver'],

    ROL_ADMIN_CATEQUESIS => [
        'panel.ver',
        'avisos.ver', 'avisos.crear', 'avisos.editar',
        'eventos.ver', 'eventos.crear', 'eventos.editar',
        'galeria.ver', 'galeria.crear', 'galeria.eliminar',
        'pastorales.ver', 'pastorales.editar',
        'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
        'documentos.ver', 'documentos.crear', 'documentos.eliminar',
        'catequesis.ver', 'catequesis.crear', 'catequesis.editar', 'catequesis.eliminar',
        'cursos.ver',
    ],
    ROL_CONSULTA_CATEQUESIS => ['panel.ver', 'catequesis.ver'],

    ROL_ADMIN_LECTOR => [
        'panel.ver',
        'avisos.ver', 'avisos.crear', 'avisos.editar',
        'eventos.ver', 'eventos.crear', 'eventos.editar',
        'galeria.ver', 'galeria.crear', 'galeria.eliminar',
        'pastorales.ver', 'pastorales.editar',
        'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
        'documentos.ver', 'documentos.crear', 'documentos.eliminar',
        'lector.ver', 'lector.crear', 'lector.editar', 'lector.eliminar',
        'cursos.ver',
    ],
    ROL_CONSULTA_LECTOR => ['panel.ver', 'lector.ver'],
]);
