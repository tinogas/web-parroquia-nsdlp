<?php
// ============================================================
// Configuración general de la aplicación
// ============================================================

define('APP_NAME',   'Parroquia Nuestra Señora de la Paz');
define('APP_CORTO',  'Parroquia N. S. de la Paz');
define('APP_VERSION', '0.1.3');

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
 * Qué hace cada rol, en una frase, para quien da de alta una cuenta y no tiene
 * por qué conocer la matriz de PERMISOS que está más abajo. Vive aquí, a un
 * paso de esa matriz, para que quien cambie lo que un rol puede hacer vea el
 * texto que lo explica sin buscarlo en otro archivo — es la lección de los
 * nombres de las pastorales, que se escribían en tres sitios distintos.
 *
 * Lo usa el formulario de usuarios (modules/usuarios/views/form.php), debajo
 * del selector de rol y en el modal de perfiles adicionales, y el listado, como
 * texto al pasar el ratón sobre el rol de cada cuenta.
 *
 * Dicen QUÉ puede hacer, nunca sobre qué pastoral: eso lo deciden los dos
 * checklists de la propia cuenta, y por eso ningún rol lleva el nombre de una
 * pastoral. Ver docs/ARQUITECTURA.md, "El alcance por pastoral es ortogonal a
 * la matriz".
 */
define('ROLES_DESCRIPCION', [
    ROL_ADMIN =>
        'Todo, sin límite: cuentas, configuración del sitio, respaldos y la bitácora de '
        . 'quién hizo qué. Es el único que puede entrar como otra persona ("Usar como…"). '
        . 'Conviene que sean pocos.',
    ROL_EDITOR =>
        'Todo el contenido del sitio —textos, páginas, horarios, equipo pastoral, avisos, '
        . 'eventos, galería, cursos y las fichas de todas las pastorales—, sin límite de '
        . 'pastoral. No toca cuentas ni configuración, y no ve datos personales: ni los '
        . 'mensajes de contacto ni las inscripciones a cursos.',
    ROL_COORDINADOR_GENERAL =>
        'Lo mismo que Coordinador, pero sobre su pastoral en varias sedes o en toda la '
        . 'parroquia. Además edita la ficha de su pastoral —responsable, correo, horario de '
        . 'reunión, lo que se publica de ella— y da de alta y edita las cuentas de su propia '
        . 'pastoral, nunca de un rol igual o superior al suyo.',
    ROL_COORDINADOR =>
        'Su pastoral en una sede: publica sus avisos, sus eventos y sus cursos, sube fotos '
        . 'y documentos, y entra al módulo propio de su pastoral si lo tiene (MESC, '
        . 'Catequesis, Proclamadores o Coros). No edita la ficha de la pastoral ni administra '
        . 'cuentas: eso es de la coordinación general.',
    ROL_CONSULTA =>
        'Solo mira, sin poder cambiar nada: el calendario de la parroquia, su pastoral, sus '
        . 'documentos y los avisos que su pastoral publica hacia dentro. Es el rol de quien '
        . 'pertenece a una pastoral y entra a enterarse.',
    ROL_SECRETARIA =>
        'Solo trámites: los mensajes que llegan por el formulario de contacto y las '
        . 'inscripciones a cursos, que puede exportar. No edita el sitio. Es un rol aparte '
        . 'porque esos dos módulos son los que guardan datos personales, algunos de menores.',
]);

/**
 * Los tres escalones por los que pasa un aviso o un curso, en orden. La clave
 * es lo que viaja por el formulario y por la cadena de consulta del listado.
 *
 * No son una columna: se leen de `publicado_interno` y `publicado`, que es lo
 * que permitió partir la publicación en dos sin tocar una sola de las consultas
 * públicas, que siguen preguntando por `publicado = 1`. Ver install.sql y
 * estado_publicacion() en core/helpers.php.
 */
define('ESTADOS_PUBLICACION', [
    'borrador' => 'Borrador',
    'interno'  => 'Publicado para la pastoral',
    'publico'  => 'Publicado en la página',
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
 * Clave de país para los enlaces de WhatsApp y `tel:`. Los teléfonos se
 * capturan como se dicen aquí —diez dígitos de Hermosillo, con o sin espacios
 * y paréntesis— y ninguno trae la clave; telefono_internacional() se la
 * antepone al dibujar el enlace, sin tocar el dato guardado.
 *
 * Es constante y no clave de `configuracion` a propósito: una parroquia no se
 * muda de país, y un dedazo en un campo del panel rompería en silencio todos
 * los botones de WhatsApp a la vez. Ver docs/ARQUITECTURA.md
 */
define('LADA_PAIS', '52');

/**
 * Las cuatro pastorales que tienen módulo propio, por slug. El módulo resuelve
 * así cuál es la suya (MescModel::pastoralId() y sus gemelos) y el menú decide
 * con esto si dibuja el enlace: tener el permiso `mesc.*` no basta —lo llevan
 * todos los coordinadores—, hay que administrar esa pastoral en concreto.
 *
 * PASTORAL_PROCLAMADORES: esta pastoral ya se llamó "Lectores" (slug
 * 'lectores'), luego "Liturgia" —y con ese renombre el slug pasó a
 * 'liturgia'—, y hoy se llama "Proclamadores". El slug se quedó en
 * 'liturgia' a propósito: la URL pública /pastorales/liturgia ya está en
 * uso, y cambiarla la rompería. De ahí que este valor no se parezca al
 * nombre de la constante; lo que no se puede es cambiar uno sin el otro,
 * porque ProclamadoresModel::pastoralId() busca por slug, no por nombre.
 */
define('PASTORAL_MESC',          'ministro-extraordinario-de-la-sagrada-comunion');
define('PASTORAL_CATEQUESIS',    'catecismo');
define('PASTORAL_PROCLAMADORES', 'liturgia');
define('PASTORAL_COROS',         'coros');

/**
 * El reverso del mapa de arriba: slug de pastoral → módulo dedicado.
 * PastoralController::panel() lo usa para ofrecer, desde el panel básico de
 * MESC/Catequesis/Proclamadores, un salto directo a su módulo de turnos y
 * catálogo — esas tres pastorales siguen operando con su módulo propio, el
 * panel básico no lo reemplaza.
 */
define('MODULO_POR_PASTORAL', [
    PASTORAL_MESC          => 'mesc',
    PASTORAL_CATEQUESIS    => 'catequesis',
    PASTORAL_PROCLAMADORES => 'proclamadores',
    PASTORAL_COROS         => 'coros',
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

/** Lo que puede hacer quien coordina una pastoral, sea de una sede o de todas. */
define('PERMISOS_COORDINACION', [
    'panel.ver',
    'agenda.ver',
    'avisos.ver', 'avisos.crear', 'avisos.editar', 'avisos.publicar',
    'eventos.ver', 'eventos.crear', 'eventos.editar', 'eventos.publicar',
    'galeria.ver', 'galeria.crear', 'galeria.eliminar',
    // `pastorales.editar` NO entra aquí: la ficha de la pastoral —su responsable,
    // su correo, su descripción pública, su Comisión— la tocan el administrador
    // y la coordinación general, no quien coordina en una sola sede. Es lo que
    // pidió la parroquia: esa ficha es de la pastoral entera, y quien coordina
    // una comunidad no tiene por qué poder cambiar lo que se publica de todas.
    // Coordinador general la recupera más abajo, con el mismo array_merge que
    // ya usa para las cuentas.
    'pastorales.ver',
    // Escribirle por WhatsApp a la gente de su pastoral, desde los listados de
    // integrantes. Es un permiso propio y no `personas.ver` porque no abre la
    // ficha —ni domicilio, ni fecha de nacimiento, ni las demás pastorales de
    // esa persona—, solo el botón que abre la conversación; y porque los
    // permisos que ya gobiernan esas pantallas (mesc.ver y sus gemelos) los
    // lleva también Consulta, que mira y no actúa. Ver docs/ARQUITECTURA.md
    'personas.contactar',
    'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.eliminar',
    'documentos.ver', 'documentos.crear', 'documentos.eliminar',
    'mesc.ver', 'mesc.crear', 'mesc.editar', 'mesc.eliminar',
    'catequesis.ver', 'catequesis.crear', 'catequesis.editar', 'catequesis.eliminar',
    'proclamadores.ver', 'proclamadores.crear', 'proclamadores.editar', 'proclamadores.eliminar',
    'coros.ver', 'coros.crear', 'coros.editar', 'coros.eliminar',
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
        'evangelio.ver', 'evangelio.editar',
        'horarios.ver', 'horarios.editar',
        'centros.ver', 'centros.editar',
        'personas.ver', 'personas.editar', 'personas.contactar',
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
        'proclamadores.ver', 'proclamadores.crear', 'proclamadores.editar', 'proclamadores.eliminar',
        'coros.ver', 'coros.crear', 'coros.editar', 'coros.eliminar',
        'sacramentos.ver', 'sacramentos.editar',
        'cursos.ver', 'cursos.crear', 'cursos.editar', 'cursos.eliminar', 'cursos.publicar',
    ],

    // Coordinador y Coordinador general parten de la MISMA lista base
    // (PERMISOS_COORDINACION), para que no puedan divergir por descuido al
    // tocar una y olvidar la otra; lo que los separa —además del alcance en
    // sedes— es que solo General administra cuentas, y solo las de su propia
    // pastoral: ver más abajo y docs/ARQUITECTURA.md.
    //
    // Publican sus eventos, sus cursos y sus avisos, pero no su galería.
    //
    // Avisos entraba antes siempre como borrador, porque un aviso es un texto
    // dirigido a toda la parroquia y publicarlo era saltar directo a la
    // portada. Eso dejó de ser cierto al partir la publicación en dos
    // escalones (avisos.publicado_interno / .publicado): ahora el primer paso
    // solo alcanza a los miembros de la propia pastoral, que es exactamente lo
    // que una coordinadora necesita poder hacer sin pedir permiso, y el salto
    // al sitio web sigue siendo un acto aparte y deliberado. Se les da también
    // ese segundo salto —igual que ya lo tenían en eventos y cursos— porque
    // quien responde de lo que su pastoral comunica es ella misma; lo que
    // gobierna el alcance no es este permiso sino la pastoral asignada.
    //
    // Los permisos de los cuatro módulos dedicados —mesc.*, catequesis.*,
    // proclamadores.*, coros.*— los llevan todos los coordinadores, y quien entra de verdad a
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
        'pastorales.editar',
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar',
    ]),

    // Solo mira. Para el ministro, catequista o proclamador de a pie que entra a ver
    // su propio calendario y el de la parroquia, sin nada que tocar. Su
    // pastoral y su sede acotan lo que ve, igual que a un coordinador.
    //
    // avisos.ver es lo que le permite leer lo que su pastoral publica hacia
    // dentro: sin él, el escalón "publicado para la pastoral" no tendría a
    // nadie a quien llegar, porque este es justamente el rol de sus miembros.
    // Ver los borradores ajenos sigue sin poder: eso lo recorta el listado,
    // no la matriz.
    ROL_CONSULTA => [
        'panel.ver',
        'agenda.ver',
        'pastorales.ver',
        'actividades.ver',
        'documentos.ver',
        'avisos.ver',
        'eventos.ver',
        'cursos.ver',
        'mesc.ver',
        'catequesis.ver',
        'proclamadores.ver',
        'coros.ver',
    ],

    // Único rol, junto con el administrador, que ve datos personales.
    ROL_SECRETARIA => [
        'panel.ver',
        'mensajes.ver', 'mensajes.editar',
        'inscripciones.ver', 'inscripciones.editar', 'inscripciones.exportar',
        // 'cursos.ver', 'avisos.ver', 'eventos.ver',
    ],
]);
