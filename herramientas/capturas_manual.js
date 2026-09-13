/**
 * capturas_manual.js — Toma las capturas de pantalla del manual de usuario
 * (docs/manual/) contra el sitio corriendo en el XAMPP local.
 *
 * Se ejecuta desde la línea de órdenes, con Apache y MySQL arriba:
 *
 *     node herramientas/capturas_manual.js               // todas
 *     node herramientas/capturas_manual.js panel-inicio  // solo esa
 *     node herramientas/capturas_manual.js publico-*     // las que empiezan así
 *     node herramientas/capturas_manual.js --lista       // qué pantallas hay
 *
 * Las pantallas están declaradas aparte, en capturas_manual_pantallas.js: este
 * archivo es solo el motor —abrir el navegador, entrar al panel, tapar los
 * datos personales y disparar—, y ese otro es la lista de qué fotografiar. Se
 * parten así porque la lista va a crecer con cada capítulo del manual y el
 * motor no debería moverse por eso.
 *
 * POR QUÉ TAPA DATOS: el repositorio es público y la base local es la de la
 * parroquia, con teléfonos, correos personales, cumpleaños, los mensajes del
 * formulario de contacto y las inscripciones a cursos, algunas de menores.
 * Antes de cada disparo se sustituyen esos datos por ejemplos —ver
 * enmascarar()—, así que las capturas enseñan el sistema real con datos que no
 * son de nadie. Lo que NO se tapa es lo que la propia parroquia ya publica en
 * su sitio: los nombres de quienes coordinan cada pastoral y los correos
 * institucionales @parroquiansdlp.org.
 *
 * Al tapar se toca solo el DOM de la página ya cargada, nunca la base de
 * datos: el guion no escribe una sola línea en MySQL.
 *
 * La contraseña del administrador no se versiona ni se pasa por la línea de
 * órdenes: se lee de herramientas/.env (que .gitignore ya excluye, por la
 * regla `.env`) o de las variables de entorno MANUAL_EMAIL y MANUAL_PASSWORD.
 *
 *     # herramientas/.env
 *     MANUAL_EMAIL=admin@parroquiansdlp.org
 *     MANUAL_PASSWORD=la-que-sea
 *
 * Usa puppeteer-core (devDependency) con el Chrome que ya está instalado en la
 * máquina, no un navegador descargado aparte: son 200 MB que no hacen falta.
 */

const fs   = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const RAIZ    = path.dirname(__dirname);
const DESTINO = path.join(RAIZ, 'docs', 'manual', 'img');
const BASE    = process.env.MANUAL_BASE || 'http://localhost/WebParroquia';

// 1280 es el ancho al que el panel ya no colapsa el menú lateral y sigue
// cabiendo en el ancho de lectura de GitHub. El factor 2 es para que el texto
// de la captura se lea en una pantalla HiDPI; sale un PNG del doble de lado.
const VENTANA = { width: 1280, height: 800, deviceScaleFactor: 2 };

// WebP a calidad 88 pesa la quinta parte que el PNG equivalente y a este
// tamaño no se le nota la diferencia; con un manual de decenas de pantallas,
// esa quinta parte es lo que separa un repositorio manejable de uno que pesa
// más por sus capturas que por su código. GitHub y VS Code lo dibujan igual.
// Para volver a PNG: MANUAL_FORMATO=png.
const FORMATO = process.env.MANUAL_FORMATO || 'webp';

// ------------------------------------------------------------
// Credenciales
// ------------------------------------------------------------

/** Lee herramientas/.env sin dependencias: KEY=valor, una por línea, # comenta. */
function leerEnv() {
    const archivo = path.join(__dirname, '.env');
    if (!fs.existsSync(archivo)) { return {}; }
    const datos = {};
    for (const linea of fs.readFileSync(archivo, 'utf8').split(/\r?\n/)) {
        const m = linea.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i);
        if (m) { datos[m[1]] = m[2].trim().replace(/^["']|["']$/g, ''); }
    }
    return datos;
}

const env      = leerEnv();
const EMAIL    = process.env.MANUAL_EMAIL    || env.MANUAL_EMAIL    || 'admin@parroquiansdlp.org';
const PASSWORD = process.env.MANUAL_PASSWORD || env.MANUAL_PASSWORD || '';

// ------------------------------------------------------------
// Navegador
// ------------------------------------------------------------

/** El Chrome (o Edge) instalado en Windows, que es el que se usa en vez de bajar uno. */
function buscarNavegador() {
    const candidatos = [
        process.env.MANUAL_CHROME,
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ].filter(Boolean);
    const encontrado = candidatos.find((ruta) => fs.existsSync(ruta));
    if (!encontrado) {
        throw new Error('No se encontro Chrome ni Edge. Indica la ruta en MANUAL_CHROME.');
    }
    return encontrado;
}

// ------------------------------------------------------------
// Tapar los datos personales
// ------------------------------------------------------------

/**
 * Los teléfonos de la parroquia —el de la oficina y el de WhatsApp, tal como
 * están en Configuración— no se tapan: son los que el sitio publica en
 * Contacto, en el pie de cada página y en el ejemplo que acompaña a cada campo
 * de teléfono del panel. Se comparan por sus diez dígitos, sin espacios ni
 * clave de país, que es lo único estable entre un formato y otro.
 */
const TELEFONOS_PUBLICOS = ['6622207214', '6621487589'];

/**
 * Sustituye en la página ya cargada lo que no puede salir en un repositorio
 * público. Corre dentro del navegador, sobre los nodos de texto y sobre los
 * value de los formularios, justo antes de disparar.
 *
 * Las reglas generales van por expresión regular porque los datos aparecen en
 * sitios que no se pueden enumerar —una tabla, un tooltip, el href de un
 * WhatsApp—; las que dependen de la pantalla se declaran en cada entrada del
 * catálogo con `tapar`, como pares [selector, texto de ejemplo].
 */
async function enmascarar(page, tapar = []) {
    await page.evaluate((selectoresATapar, publicos) => {
        // --- Reglas generales -------------------------------------------
        const reglas = [
            // Teléfonos de diez dígitos, con o sin espacios, guiones o paréntesis,
            // y su versión internacional (+52…), que es la que va en los enlaces
            // de WhatsApp. Se deja siempre el mismo número de ejemplo, salvo los
            // de la propia parroquia, que el sitio ya publica en Contacto y en el
            // pie: taparlos no protegería a nadie y dejaría el manual enseñando
            // un teléfono que no existe.
            [/(\+?52\s?)?\(?\b[2-9]\d{2}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/g,
                (encontrado) => (publicos.includes(encontrado.replace(/\D/g, '').slice(-10))
                    ? encontrado
                    : '662 000 0000')],
            // Correos personales. Los institucionales de la parroquia se quedan:
            // son los que el propio sitio publica.
            [/\b[\w.+-]+@(?!parroquiansdlp\.org)[\w-]+\.[\w.]+\b/g, 'persona@ejemplo.com'],
        ];

        const aplicar = (texto) => reglas.reduce((t, [re, por]) => t.replace(re, por), texto);

        const it = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodos = [];
        while (it.nextNode()) { nodos.push(it.currentNode); }
        for (const nodo of nodos) {
            const nuevo = aplicar(nodo.nodeValue);
            if (nuevo !== nodo.nodeValue) { nodo.nodeValue = nuevo; }
        }
        for (const campo of document.querySelectorAll('input, textarea')) {
            if (campo.value) { campo.value = aplicar(campo.value); }
        }
        // Los enlaces tel: y wa.me llevan el número en el href, donde no se ve
        // pero viaja en el HTML de la página.
        const enlaces = 'a[href^="tel:"], a[href*="wa.me"], a[href^="mailto:"]';
        for (const a of document.querySelectorAll(enlaces)) {
            a.setAttribute('href', aplicar(a.getAttribute('href')));
        }

        // --- Reglas de esta pantalla ------------------------------------
        // Todo lo que case con el selector pasa a decir el texto de ejemplo:
        // columnas enteras de una tabla, quién escribe un mensaje, cumpleaños.
        // Con una lista de ejemplos en vez de uno solo, se van turnando: seis
        // renglones que dijeran el mismo nombre delatarían el retoque y harían
        // ilegible la pantalla que se quiere enseñar.
        // Se sustituye el TEXTO del elemento, no el elemento entero: muchos de
        // estos datos viven junto a un icono o dentro de un enlace, y borrarlos
        // con textContent se llevaría por delante media pantalla.
        const ponerTexto = (el, texto) => {
            const sueltos = [...el.childNodes]
                .filter((n) => n.nodeType === 3 && n.nodeValue.trim() !== '');
            if (!sueltos.length) { el.textContent = texto; return; }
            sueltos[0].nodeValue = texto;
            for (const nodo of sueltos.slice(1)) { nodo.nodeValue = ''; }
        };

        for (const [selector, ejemplo] of selectoresATapar) {
            const lista = Array.isArray(ejemplo) ? ejemplo : [ejemplo];
            document.querySelectorAll(selector).forEach((el, i) => {
                ponerTexto(el, lista[i % lista.length]);
            });
        }
    }, tapar, TELEFONOS_PUBLICOS);
}

/**
 * Cambia un elemento por un recuadro con una leyenda dentro. Es para lo que en
 * el sitio real se ve y en una captura local no: el muro de Facebook de la
 * portada, que el Page Plugin solo dibuja sobre el dominio de la parroquia y
 * que aquí saldría como un hueco en blanco. Enseñar el hueco sería mentir
 * sobre la pantalla; enseñar un recuadro que dice qué va ahí, no.
 */
async function sustituir(page, cambios = []) {
    if (!cambios.length) { return; }
    await page.evaluate((lista) => {
        for (const [selector, leyenda] of lista) {
            for (const el of document.querySelectorAll(selector)) {
                const caja = document.createElement('div');
                caja.textContent = leyenda;
                caja.style.cssText = 'display:flex; align-items:center; justify-content:center;'
                    + 'min-height:180px; padding:2rem; text-align:center;'
                    + 'border:2px dashed #adb5bd; border-radius:8px;'
                    + 'color:#6c757d; font-style:italic;';
                el.replaceWith(caja);
            }
        }
    }, cambios);
}

/**
 * Cambia fotos y avatares por una silueta neutra. Hace falta donde el nombre
 * ya se sustituyó pero la imagen seguiría delatando a la persona: el avatar
 * que dibuja el sistema cuando no hay foto lleva las iniciales de verdad, y
 * junto a su pastoral y su día de cumpleaños eso alcanza para reconocer a
 * alguien dentro de la parroquia.
 */
async function neutralizarFotos(page, selectores = []) {
    if (!selectores.length) { return; }
    await page.evaluate((lista) => {
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            + '<rect width="64" height="64" fill="#ced4da"/>'
            + '<circle cx="32" cy="24" r="11" fill="#f8f9fa"/>'
            + '<path d="M12 60c0-11 9-18 20-18s20 7 20 18z" fill="#f8f9fa"/></svg>';
        const dataUri = 'data:image/svg+xml;base64,' + btoa(svg);
        for (const selector of lista) {
            for (const img of document.querySelectorAll(selector)) {
                img.setAttribute('src', dataUri);
                img.removeAttribute('srcset');
            }
        }
    }, selectores);
}

/**
 * Quita la franja de «Actuando como…» y el desplazamiento que trae consigo.
 *
 * Se usa cuando la pantalla se fotografía desde la sesión de otra persona
 * —«Usar como…»— por una razón de contenido y no de permisos: la campana con
 * avisos sin leer, por ejemplo, que la cuenta del administrador ya leyó. Lo
 * que queda es exactamente lo que esa persona ve al entrar con su contraseña,
 * así que la captura no enseña nada que no ocurra; la franja, en cambio,
 * hablaría de un rodeo del guion que al lector no le sirve de nada.
 *
 * En la captura que SÍ explica la impersonación (capítulo 4), no se usa: ahí
 * la franja es justamente lo que hay que enseñar.
 */
async function disimularImpersonacion(page) {
    await page.evaluate(() => {
        document.querySelector('.banner-impersonando')?.remove();
        document.body.classList.remove('impersonando');
        // Y el aviso verde de «Ahora estás usando el panel como…», que sale
        // una sola vez, justo al entrar: es el otro rastro del rodeo.
        for (const alerta of document.querySelectorAll('.alert')) {
            if (/usando el panel como/i.test(alerta.textContent)) { alerta.remove(); }
        }
    });
}

/** Dibuja un recuadro sobre los elementos que el manual va a explicar. */
async function marcar(page, selectores = []) {
    if (!selectores.length) { return; }
    await page.evaluate((lista) => {
        const estilo = document.createElement('style');
        estilo.textContent = '.manual-marca {'
            + 'outline: 3px solid #d6336c !important;'
            + 'outline-offset: 2px !important;'
            + 'border-radius: 4px !important; }';
        document.head.appendChild(estilo);
        for (const selector of lista) {
            for (const el of document.querySelectorAll(selector)) {
                el.classList.add('manual-marca');
            }
        }
    }, selectores);
}

// ------------------------------------------------------------
// Entrar al panel
// ------------------------------------------------------------

async function entrar(page) {
    if (!PASSWORD) {
        throw new Error(
            'Falta la contraseña. Crea herramientas/.env con MANUAL_EMAIL y MANUAL_PASSWORD '
            + '(ese archivo no se versiona).'
        );
    }
    await page.goto(BASE + '/admin/auth/login', { waitUntil: 'networkidle2' });
    await page.type('#email', EMAIL);
    await page.type('#password', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('button[type="submit"]'),
    ]);

    // Quien tiene perfiles adicionales pasa por una pantalla intermedia; se
    // acepta el perfil principal, que viene marcado.
    if (await page.$('input[name="perfil_id"]')) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]'),
        ]);
    }

    if (await page.$('#password')) {
        throw new Error('El acceso no se completó: revisa MANUAL_EMAIL y MANUAL_PASSWORD.');
    }
}

// ------------------------------------------------------------
// Disparar
// ------------------------------------------------------------

async function capturar(page, pantalla) {
    const destino = path.join(DESTINO, pantalla.id + '.' + FORMATO);

    await page.goto(BASE + pantalla.url, { waitUntil: 'networkidle2' });
    if (pantalla.espera) { await page.waitForSelector(pantalla.espera, { timeout: 15000 }); }
    if (pantalla.antes)  { await pantalla.antes(page); }

    // Las animaciones de Bootstrap dejan elementos a medio camino si se
    // dispara demasiado pronto.
    await new Promise((r) => setTimeout(r, 400));

    if (pantalla.disimularImpersonacion) { await disimularImpersonacion(page); }
    await enmascarar(page, pantalla.tapar);
    await neutralizarFotos(page, pantalla.fotos);
    await sustituir(page, pantalla.sustituir);
    await marcar(page, pantalla.marcar);

    const opciones = { path: destino, type: FORMATO };
    if (FORMATO === 'webp') { opciones.quality = 88; }
    if (pantalla.completa)  { opciones.fullPage = true; }

    if (pantalla.recorte) {
        const elemento = await page.$(pantalla.recorte);
        if (!elemento) { throw new Error('No se encontró el recorte ' + pantalla.recorte); }
        await elemento.screenshot(opciones);
    } else {
        await page.screenshot(opciones);
    }

    // Para deshacer lo que la pantalla haya dejado montado —una impersonación,
    // sobre todo— antes de seguir con la siguiente.
    if (pantalla.despues) { await pantalla.despues(page); }

    const kb = Math.round(fs.statSync(destino).size / 1024);
    console.log('  [ok] ' + path.basename(destino) + ' (' + kb + ' KB) ' + pantalla.titulo);
}

// ------------------------------------------------------------

async function main() {
    const pantallas = require('./capturas_manual_pantallas.js');
    const filtros   = process.argv.slice(2).filter((a) => !a.startsWith('--'));

    if (process.argv.includes('--lista')) {
        for (const p of pantallas) { console.log(p.id.padEnd(34) + p.url); }
        return;
    }

    const elegidas = filtros.length
        ? pantallas.filter((p) => filtros.some(
            (f) => new RegExp('^' + f.replace(/\*/g, '.*') + '$').test(p.id)))
        : pantallas;

    if (!elegidas.length) {
        console.error('Ningún id coincide. Prueba con --lista.');
        process.exitCode = 1;
        return;
    }

    fs.mkdirSync(DESTINO, { recursive: true });

    const navegador = await puppeteer.launch({
        executablePath: buscarNavegador(),
        headless: 'new',
        defaultViewport: VENTANA,
        args: ['--hide-scrollbars', '--lang=es-MX'],
    });

    try {
        const page = await navegador.newPage();
        await page.setViewport(VENTANA);

        // Primero todo lo que se ve sin entrar y después lo del panel, aunque
        // el catálogo los mezcle: la pantalla de acceso es de las que hay que
        // fotografiar ANTES de iniciar sesión, porque con la sesión abierta
        // esa dirección ya no enseña el formulario, redirige al panel.
        const publicas = elegidas.filter((p) => p.sesion === 'publico');
        const privadas = elegidas.filter((p) => p.sesion !== 'publico');

        console.log('Capturando ' + elegidas.length + ' pantalla(s) en docs/manual/img/');
        let fallos = 0;

        const disparar = async (pantalla) => {
            try {
                await capturar(page, pantalla);
            } catch (e) {
                fallos++;
                console.error('  [falla] ' + pantalla.id + ': ' + e.message);
            }
        };

        for (const pantalla of publicas) { await disparar(pantalla); }

        if (privadas.length) {
            console.log('Entrando al panel…');
            await entrar(page);
            for (const pantalla of privadas) { await disparar(pantalla); }
        }

        if (fallos) { process.exitCode = 1; }
    } finally {
        await navegador.close();
    }
}

main().catch((e) => {
    console.error(e.message);
    process.exitCode = 1;
});
