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
        antes: async (page) => {
            await page.click('.navbar .bi-bell, .navbar .bi-bell-fill');
            await page.waitForSelector('.dropdown-menu.show');
        },
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
        antes: async (page) => {
            await page.click('.navbar .btn-outline-light.dropdown-toggle');
            await page.waitForSelector('.dropdown-menu.show');
            await page.click('[data-bs-target="#modalUsarComo"]');
            await page.waitForSelector('#modalUsarComo.show');
        },
    },
];
