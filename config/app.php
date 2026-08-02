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
// El rol dice QUÉ puede hacer una cuenta; la pastoral y la sede que se le
// marcan dicen SOBRE QUÉ. Por eso no hay un rol por pastoral: "coordinadora de
// catequesis en Jesús el Señor" es el rol Coordinador con Catecismo y esa sede,
// y su cargo real vive en su ficha del equipo pastoral. Antes existían seis
// roles con la pastoral en el nombre (admin_mesc, consulta_catequesis…); se
// retiraron al aparecer tres coordinadoras de catequesis, una por comunidad,
// que ese esquema no sabía distinguir. Ver docs/ARQUITECTURA.md
define('ROL_ADMIN',       'admin');        // Todo, incluidos usuarios y configuración
define('ROL_EDITOR',      'editor');       // Todo el contenido; publica y modera
define('ROL_SECRETARIA',  'secretaria');   // Trámites y mensajes; no edita el sitio

// Los tres roles acotados por pastoral y sede. Coordinador y Coordinador
// general comparten permisos y se distinguen por el alcance, que el formulario
// de usuarios exige distinto: el primero administra su pastoral en UNA sede
// —dejarlo sin sede lo convertiría en general sin querer—, y el segundo en
// varias, o en todas si no se le marca ninguna.
define('ROL_COORDINADOR',         'coordinador');
define('ROL_COORDINADOR_GENERAL', 'coordinador_general');
define('ROL_CONSULTA',            'consulta');

define('ROLES_NOMBRES', [
    ROL_ADMIN               => 'Administrador',
    ROL_EDITOR              => 'Editor',
    ROL_COORDINADOR_GENERAL => 'Coordinador general de pastoral',
    ROL_COORDINADOR         => 'Coordinador de pastoral',
    ROL_CONSULTA            => 'Consulta',
    ROL_SECRETARIA          => 'Secretaría',
]);

/**
 * Roles cuyo acceso queda acotado a lo que se les marque en la cuenta:
 * pastorales (usuarios_pastorales) y sedes (usuarios_centros), las dos mitades
 * del alcance — Auth::puedeSobrePastoral() y Auth::puedeSobreCentro(), ver
 * docs/ARQUITECTURA.md. Se usa en el formulario de usuarios, para mostrar los
 * dos checklists, y al guardar, para saber si hay que sincronizarlos.
 */
define('ROLES_CON_ALCANCE_PASTORAL', [
    ROL_COORDINADOR,
    ROL_COORDINADOR_GENERAL,
    ROL_CONSULTA,
]);

/**
 * Las tres pastorales que tienen módulo propio, por slug. El módulo resuelve
 * así cuál es la suya (MescModel::pastoralId() y sus gemelos) y el menú decide
 * con esto si dibuja el enlace: tener el permiso `mesc.*` no basta —lo llevan
 * todos los coordinadores—, hay que administrar esa pastoral en concreto.
 *
 * PASTORAL_LECTOR: la pastoral se llamaba "Lectores" (slug 'lectores') y se
 * renombró a "Liturgia"; el slug se alineó al mismo tiempo que este valor
 * —cambiar uno sin el otro rompe el módulo entero, porque
 * LectorModel::pastoralId() busca por slug, no por nombre—. El nombre de
 * esta constante no cambió: identifica al módulo Lector, no es una copia
 * del nombre visible de la pastoral.
 */
define('PASTORAL_MESC',       'ministro-extraordinario-de-la-sagrada-comunion');
define('PASTORAL_CATEQUESIS', 'catecismo');
define('PASTORAL_LECTOR',     'liturgia');

// ------------------------------------------------------------
// Permisos por rol — notación modulo.accion, comodín '*'
// ------------------------------------------------------------
// Esta matriz responde "¿qué acción puede hacer?". La pregunta "¿sobre qué
// registro?" se resuelve aparte, con Auth::puedeSobrePastoral(), para que la
// matriz no crezca con una entrada por pastoral. Ver docs/ARQUITECTURA.md
//
// Los permisos de módulos que aún no existen están comentados y se irán
// activando conforme avancen las etapas del plan.

/** Lo que puede hacer quien coordina una pastoral, sea de una sede o de todas. */
define('PERMISOS_COORDINACION', [
    'panel.ver',
    'agenda.ver',
    'avisos.ver', 'avisos.crear', 'avisos.editar',
    'eventos.ver', 'eventos.crear', 'eventos.editar', 'eventos.publicar',
    'galeria.ver', 'galeria.crear', 'galeria.eliminar',
    'pastorales.ver', 'pastorales.editar',
    'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
    'documentos.ver', 'documentos.crear', 'documentos.eliminar',
    'mesc.ver', 'mesc.crear', 'mesc.editar', 'mesc.eliminar',
    'catequesis.ver', 'catequesis.crear', 'catequesis.editar', 'catequesis.eliminar',
    'lector.ver', 'lector.crear', 'lector.editar', 'lector.eliminar',
    'cursos.ver', 'cursos.crear', 'cursos.editar', 'cursos.publicar',
]);

define('PERMISOS', [

    ROL_ADMIN => ['*'],

    // auditoria.*, configuracion.* y respaldos.* no aparecen en ningún otro rol
    // de esta matriz, a propósito: la configuración global, la bitácora
    // completa y los respaldos de la base de datos quedan exclusivamente al
    // administrador. Llegan solo por el comodín '*' de arriba. usuarios.* es
    // la única excepción, y acotada: Coordinador general la tiene más abajo,
    // pero solo alcanza a las cuentas de su propia pastoral y nunca a un rol
    // igual o superior al suyo — ver UsuarioController::dentroDeMiAlcance().
    ROL_EDITOR => [
        'panel.ver',
        'agenda.ver',
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

    // Coordinador y Coordinador general parten de la MISMA lista base
    // (PERMISOS_COORDINACION), para que no puedan divergir por descuido al
    // tocar una y olvidar la otra; lo que los separa —además del alcance en
    // sedes— es que solo General administra cuentas, y solo las de su propia
    // pastoral: ver más abajo y docs/ARQUITECTURA.md.
    //
    // Publican sus eventos y sus cursos, pero no sus avisos ni su galería: lo
    // que se pone en el calendario es una fecha que la pastoral ya tiene
    // decidida y que las demás necesitan ver publicada sin esperar a que un
    // editor pase a revisarla; un aviso, en cambio, es un texto dirigido a toda
    // la parroquia y sigue entrando como borrador.
    //
    // Los permisos de los tres módulos dedicados —mesc.*, catequesis.*,
    // lector.*— los llevan todos los coordinadores, y quien entra de verdad a
    // cada uno lo decide la pastoral asignada: el controlador comprueba
    // Auth::puedeSobrePastoral() con la pastoral del módulo, y el menú no
    // dibuja el enlace a quien no la administre (Auth::administraPastoral()).
    // Por eso no hacen falta seis roles con la pastoral en el nombre. En
    // particular mesc.* no se le da a secretaría: es una actividad de la propia
    // pastoral, no un trámite, y es el primer dato sensible (estado de salud)
    // que maneja el sistema.
    ROL_COORDINADOR         => PERMISOS_COORDINACION,
    // usuarios.ver/crear/editar: administra las cuentas de gente que trabaja
    // en su propia pastoral —quien coordina en una sola sede no llega a
    // administrar cuentas, solo contenido—. El alcance real (qué pastoral,
    // qué rangos de rol) lo aplica UsuarioController, no esta matriz: aquí
    // solo se decide la acción, igual que con el resto de módulos.
    ROL_COORDINADOR_GENERAL => array_merge(PERMISOS_COORDINACION, [
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar',
    ]),

    // Solo mira. Para el ministro, catequista o lector de a pie que entra a ver
    // su propio calendario y el de la parroquia, sin nada que tocar. Su
    // pastoral y su sede acotan lo que ve, igual que a un coordinador.
    ROL_CONSULTA => [
        'panel.ver',
        'agenda.ver',
        'pastorales.ver',
        'actividades.ver',
        'documentos.ver',
        'eventos.ver',
        'cursos.ver',
        'mesc.ver',
        'catequesis.ver',
        'lector.ver',
    ],

    // Único rol, junto con el administrador, que ve datos personales.
    ROL_SECRETARIA => [
        'panel.ver',
        'mensajes.ver', 'mensajes.editar',
        'inscripciones.ver', 'inscripciones.editar', 'inscripciones.exportar',
        // 'cursos.ver', 'avisos.ver', 'eventos.ver',
    ],
]);
