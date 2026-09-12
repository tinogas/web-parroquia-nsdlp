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
];
