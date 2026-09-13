/**
 * capturas_manual_pantallas.js — Qué pantallas fotografía el manual.
 *
 * La lista que recorre herramientas/capturas_manual.js. Cada entrada es una
 * imagen de docs/manual/img/, y el `id` es el nombre del archivo sin .png:
 * así, desde el capítulo del manual, se sabe de un vistazo qué guion rehace
 * esa imagen y con qué nombre pedirla.
 *
 *     {
 *       id:       'panel-inicio',        // -> docs/manual/img/panel-inicio.png
 *       titulo:   'Panel de inicio',     // solo para el mensaje de la consola
 *       url:      '/admin/panel',        // colgando de MANUAL_BASE
 *       sesion:   'publico',             // 'publico' = sin entrar al panel
 *       espera:   '.sidebar-link',       // selector que confirma que ya cargó
 *       recorte:  '#contenido',          // captura solo ese elemento
 *       completa: true,                  // la página entera, no lo que cabe
 *       marcar:   ['#buscador'],         // recuadro sobre lo que se explica
 *       tapar:    [['td.correo', 'persona@ejemplo.com']],
 *       antes:    async (page) => {},    // clics previos al disparo
 *     }
 *
 * `tapar` es el cierre de lo que enmascarar() no puede adivinar sola: los
 * teléfonos y los correos los encuentra por su forma, pero el nombre de quien
 * escribió un mensaje o la fecha de un cumpleaños solo se distinguen por dónde
 * están. Ver el encabezado de capturas_manual.js.
 *
 * El orden de la lista es el del manual, no el del menú: primero el sitio que
 * ve cualquiera, luego la puerta de entrada al panel y después los módulos.
 */

/**
 * Nombres de relleno para las pantallas que listan personas. Se turnan, así
 * que una lista de seis renglones sale con seis nombres distintos y se sigue
 * leyendo como lo que es.
 */
const EJEMPLOS_NOMBRES = [
    'María Elena Ruiz Bernal',
    'Guadalupe Soto Ramírez',
    'Javier Navarro Peña',
    'Ana Rivas Domínguez',
    'Rosa María Gil Ochoa',
    'Carlos Medina Vázquez',
];

/** El nombre de cada quien dentro de la tarjeta «Cumpleaños de …» del panel. */
const NOMBRES_CUMPLEANEROS = '.card .d-flex.flex-wrap.gap-3 > div > div.small > div:first-child';

/**
 * Lo que hay que tapar en CUALQUIER captura de la pantalla de inicio: la
 * tarjeta de cumpleaños ata cada nombre a su día de nacimiento, que es un dato
 * de la ficha y no algo que la parroquia publique. Los días se quedan —un día
 * suelto, sin nombre, no es de nadie— y las fotos se cambian por una silueta,
 * porque el avatar que dibuja el sistema cuando no hay foto lleva las
 * iniciales de verdad.
 */
const TAPAR_INICIO = [[NOMBRES_CUMPLEANEROS, EJEMPLOS_NOMBRES]];
const FOTOS_INICIO = ['.card .d-flex.flex-wrap.gap-3 img'];

/** El nombre de quien tiene la sesión abierta, arriba a la derecha. */
const NOMBRE_EN_LA_BARRA = '.navbar .btn-outline-light span';

/** El «Hola, Fulano» con el que abre la pantalla de inicio. */
const SALUDO_DEL_PANEL = 'h1.h4.fw-bold';

/**
 * Entra al panel como otra cuenta, por «Usar como…», para fotografiar lo que
 * esa persona ve —un menú corto, una campana con avisos sin leer— sin conocer
 * su contraseña. `despues` deshace la impersonación, para que las capturas
 * siguientes no salgan con la sesión prestada.
 */
const usarComo = (usuarioId) => async (page) => {
    await page.click('.navbar .btn-outline-light.dropdown-toggle');
    await page.waitForSelector('.dropdown-menu.show');
    await page.click('[data-bs-target="#modalUsarComo"]');
    await page.waitForSelector('#modalUsarComo.show');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.evaluate((id) => {
            const campo = document.querySelector(
                'form.fila-usar-como input[name="usuario_id"][value="' + id + '"]');
            campo.closest('form').querySelector('button[type="submit"]').click();
        }, usuarioId),
    ]);
};

const volverAAdmin = async (page) => {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.evaluate(() => {
            const form = document.querySelector('form[action*="terminar_impersonacion"]');
            if (form) { form.querySelector('button[type="submit"]').click(); }
        }),
    ]);
};

module.exports = [

    // ------------------------------------------------------------
    // El sitio público
    // ------------------------------------------------------------

    {
        id: 'publico-inicio',
        titulo: 'Portada del sitio',
        url: '/',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-inicio-completa',
        titulo: 'Portada del sitio, de arriba abajo',
        url: '/',
        sesion: 'publico',
        espera: '.barra-sitio',
        completa: true,
        // Facebook solo dibuja su Page Plugin sobre el dominio de la parroquia;
        // en localhost el iframe queda en blanco.
        sustituir: [['iframe[title="Publicaciones recientes en Facebook"]',
            'Aquí aparecen las últimas publicaciones de la página de Facebook de la parroquia.']],
    },
    {
        id: 'publico-menu',
        titulo: 'Menú del sitio',
        url: '/',
        sesion: 'publico',
        espera: '.barra-sitio',
        recorte: '.barra-sitio',
    },
    {
        id: 'publico-nosotros',
        titulo: 'Quiénes somos',
        url: '/quienes-somos',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-horarios',
        titulo: 'Horarios de misas',
        url: '/horarios',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-pastorales',
        titulo: 'Pastorales, agrupadas por comisión',
        url: '/pastorales',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-sacramentos',
        titulo: 'Sacramentos',
        url: '/sacramentos',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-avisos',
        titulo: 'Avisos',
        url: '/avisos',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-eventos',
        titulo: 'Calendario de eventos',
        url: '/eventos',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-cursos',
        titulo: 'Cursos abiertos',
        url: '/cursos',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-galeria',
        titulo: 'Galería',
        url: '/galeria',
        sesion: 'publico',
        espera: '.barra-sitio',
    },
    {
        id: 'publico-contacto',
        titulo: 'Formulario de contacto',
        url: '/contacto',
        sesion: 'publico',
        espera: '.barra-sitio',
    },

    // ------------------------------------------------------------
    // Entrar al panel
    // ------------------------------------------------------------

    {
        id: 'acceso-login',
        titulo: 'Pantalla de acceso',
        url: '/admin/auth/login',
        sesion: 'publico',
        espera: '#email',
    },
    {
        id: 'acceso-error',
        titulo: 'El aviso cuando los datos no coinciden',
        url: '/admin/auth/login',
        sesion: 'publico',
        espera: '#email',
        // Un correo que no existe: el mensaje que sale es el mismo que con la
        // contraseña equivocada, que es justo lo que el capítulo explica.
        antes: async (page) => {
            await page.type('#email', 'quien.sea@ejemplo.com');
            await page.type('#password', 'no-es-la-buena');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle2' }),
                page.click('button[type="submit"]'),
            ]);
            await page.waitForSelector('.alert-danger');
        },
    },

    // ------------------------------------------------------------
    // El panel por dentro
    // ------------------------------------------------------------

    {
        id: 'panel-inicio',
        titulo: 'Panel de inicio',
        url: '/admin/panel',
        espera: '.sidebar-link',
        // La tarjeta de cumpleaños ata cada nombre a su día de nacimiento, que
        // es un dato de la ficha y no algo que la parroquia publique. Los
        // nombres se cambian por ejemplos; los días se quedan, porque un día
        // suelto, sin nombre, no es de nadie.
        tapar: TAPAR_INICIO,
        fotos: FOTOS_INICIO,
    },
    {
        id: 'panel-menu',
        titulo: 'Menú lateral del panel',
        url: '/admin/panel',
        espera: '.sidebar-link',
        recorte: '#sidebar',
    },
    {
        id: 'acceso-barra-superior',
        titulo: 'La barra de arriba',
        url: '/admin/panel',
        espera: '.navbar',
        recorte: '.navbar',
    },
    {
        id: 'acceso-menu-usuario',
        titulo: 'El menú de tu nombre, desplegado',
        url: '/admin/panel',
        espera: '.navbar .btn-outline-light',
        tapar: TAPAR_INICIO,
        fotos: FOTOS_INICIO,
        // El desplegable se dibuja fuera de la barra, así que la captura es de
        // la ventana entera y no un recorte.
        antes: async (page) => {
            await page.click('.navbar .btn-outline-light.dropdown-toggle');
            await page.waitForSelector('.dropdown-menu.show');
        },
    },
    {
        id: 'panel-campana',
        titulo: 'La campana, desplegada',
        url: '/admin/panel',
        espera: '.navbar .bi-bell, .navbar .bi-bell-fill',
        // Desde la sesión de una coordinación general, no desde la del
        // administrador: lo que hay que enseñar es la campana CON avisos sin
        // leer, y la cuenta del administrador ya los leyó todos. Es la misma
        // pantalla que esa persona ve al entrar con su contraseña, así que la
        // franja de «Actuando como…» se quita —ver disimularImpersonacion()— y
        // su nombre se cambia por uno de ejemplo.
        antes: async (page) => {
            await usarComo(6)(page);
            await page.click('.navbar .bi-bell, .navbar .bi-bell-fill');
            await page.waitForSelector('.dropdown-menu.show');
        },
        despues: volverAAdmin,
        disimularImpersonacion: true,
        // Su nombre sale en dos sitios —la barra y el saludo— y los dos se
        // cambian por el mismo ejemplo, para que la captura se lea como la de
        // una sola persona.
        tapar: TAPAR_INICIO.concat([
            [NOMBRE_EN_LA_BARRA, EJEMPLOS_NOMBRES[0]],
            [SALUDO_DEL_PANEL,   'Hola, ' + EJEMPLOS_NOMBRES[0]],
        ]),
        fotos: FOTOS_INICIO.concat(['.navbar img']),
    },
    {
        id: 'panel-menu-coordinador',
        titulo: 'El mismo menú, visto por una coordinación de pastoral',
        url: '/admin/panel',
        espera: '.sidebar-link',
        antes: usarComo(6),
        despues: volverAAdmin,
        disimularImpersonacion: true,
        recorte: '#sidebar',
    },
    {
        id: 'panel-accesos',
        titulo: 'Los accesos rápidos',
        url: '/admin/panel',
        espera: '.sidebar-link',
        // La rejilla de accesos rápidos no tiene id propio; es la primera de
        // las tres filas de tarjetas de esa forma en la pantalla de inicio.
        recorte: 'div.row.row-cols-2.row-cols-sm-3.row-cols-lg-4',
    },
    {
        id: 'panel-listado',
        titulo: 'Un listado del panel, con sus filtros y sus botones',
        url: '/admin/avisos',
        espera: 'table',
    },

    // ------------------------------------------------------------
    // Avisos
    // ------------------------------------------------------------

    {
        id: 'avisos-form',
        titulo: 'El formulario de un aviso',
        url: '/admin/avisos/nuevo',
        espera: '#titulo',
        completa: true,
    },
    {
        id: 'avisos-escalon',
        titulo: 'Los tres escalones de publicación',
        url: '/admin/avisos/nuevo',
        espera: 'fieldset legend',
        recorte: 'fieldset',
    },

    // ------------------------------------------------------------
    // Eventos
    // ------------------------------------------------------------

    {
        id: 'eventos-lista',
        titulo: 'El listado de eventos',
        url: '/admin/eventos',
        espera: 'table',
    },
    {
        id: 'eventos-form',
        titulo: 'El formulario de un evento',
        url: '/admin/eventos/nuevo',
        espera: '#titulo',
        completa: true,
    },

    // ------------------------------------------------------------
    // Cursos e inscripciones
    // ------------------------------------------------------------

    {
        id: 'cursos-lista',
        titulo: 'El listado de cursos',
        url: '/admin/cursos',
        espera: 'table',
    },
    {
        id: 'cursos-form',
        titulo: 'El formulario de un curso',
        url: '/admin/cursos/nuevo',
        espera: '#titulo',
        completa: true,
    },
    {
        id: 'inscripciones-lista',
        titulo: 'Las inscripciones que llegan por el sitio',
        url: '/admin/inscripciones',
        espera: 'table',
        // La segunda columna es el nombre de quien se inscribió. El teléfono y
        // el correo los tapa ya la regla general.
        tapar: [['table tbody td:nth-child(2)', EJEMPLOS_NOMBRES]],
    },
    {
        id: 'inscripciones-ver',
        titulo: 'Una inscripción por dentro',
        url: '/admin/inscripciones/ver?id=1',
        espera: 'dl.row',
        // De esta pantalla no puede salir nada: es el expediente de una
        // persona real, con su fecha de nacimiento, y a veces la de un menor.
        tapar: [
            ['dl.row dd:nth-of-type(1)', EJEMPLOS_NOMBRES[0]],
            ['dl.row dd:nth-of-type(2)', '3 de marzo de 1998'],
        ],
    },

    // ------------------------------------------------------------
    // Agenda
    // ------------------------------------------------------------

    {
        id: 'agenda-mes',
        titulo: 'La agenda interna, vista de mes',
        url: '/admin/agenda',
        espera: '.sidebar-link',
    },
    {
        id: 'agenda-semana',
        titulo: 'La agenda interna, vista de semana',
        url: '/admin/agenda?vista=semana',
        espera: '.sidebar-link',
    },

    // ------------------------------------------------------------
    // Quién puede hacer qué
    // ------------------------------------------------------------

    {
        id: 'roles-usuario-form',
        titulo: 'El formulario de una cuenta: rol, pastorales y sedes',
        url: '/admin/usuarios/nuevo',
        espera: 'select[name="rol"]',
        completa: true,
    },
    {
        id: 'roles-usar-como',
        titulo: 'Usar como…',
        url: '/admin/panel',
        espera: '.navbar .btn-outline-light',
        // La tarjeta de cumpleaños se ve de fondo, tras el modal.
        tapar: TAPAR_INICIO,
        fotos: FOTOS_INICIO,
        antes: async (page) => {
            await page.click('.navbar .btn-outline-light.dropdown-toggle');
            await page.waitForSelector('.dropdown-menu.show');
            await page.click('[data-bs-target="#modalUsarComo"]');
            await page.waitForSelector('#modalUsarComo.show');
        },
    },
];
