#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
videos_manual.py — Arma los videos del manual de usuario: uno por capítulo,
narrados y con subtítulos, a partir de los guiones de docs/videos/guiones/.

    python herramientas/videos_manual.py                 # todos
    python herramientas/videos_manual.py 05 13           # los que empiezan así
    python herramientas/videos_manual.py --lista         # qué guiones hay
    python herramientas/videos_manual.py --procesos 4    # cuántos a la vez

Deja en docs/videos/ un .mp4 (1920×1080, subtítulos quemados) y, al lado, los
mismos subtítulos en .srt y .vtt, para subirlos aparte a YouTube o a donde se
publique el video y que el espectador pueda apagarlos o traducirlos.

QUÉ SE VERSIONA Y QUÉ NO: igual que con el Word del manual, la fuente son los
guiones —JSON, que se revisan en un diff— y el video es lo que se entrega. Los
.mp4 no se versionan: pesan decenas de megas cada uno y se rehacen con este
comando. Las correcciones van al guion, nunca al video.

DE DÓNDE SALEN LAS IMÁGENES: de las capturas del manual (docs/manual/img/),
nunca de una grabación de pantalla aparte. Esas capturas las toma
capturas_manual.js con los teléfonos, correos y cumpleaños ya tapados; un video
grabado a mano se llevaría todo eso a internet. Si un video necesita una
pantalla que no está, se agrega al catálogo de capturas, no se graba suelta.

LA VOZ: es la voz neuronal es-MX-DaliaNeural de Microsoft, por medio del
paquete edge-tts, que necesita internet. Solo se le manda el texto de la
narración, que es el mismo del manual público. Cada frase se pide una sola vez
y se guarda en docs/videos/.voz/ (no versionado) bajo la huella de su texto:
volver a armar un video después de corregir una escena solo pide esa escena.

Los subtítulos salen de los tiempos que la propia voz reporta por oración, así
que van sincronizados sin ajustar nada a mano.

Formato de un guion (docs/videos/guiones/05-avisos.json):

    {
      "capitulo": 5,
      "titulo": "Avisos",
      "subtitulo": "Publicar no es una sola cosa",
      "intro": "Capítulo cinco: avisos. ...",          (opcional)
      "escenas": [
        {"imagen": "avisos-form",
         "texto": "Lo que dice la voz y el subtítulo.",
         "foco": [0.05, 0.10, 0.60, 0.55]}              (opcional)
      ]
    }

«imagen» es el nombre de una captura de docs/manual/img/ sin la extensión.
«foco» es el rectángulo al que la cámara se acerca durante la escena, en
fracciones del ancho y del alto de la captura: [izquierda, arriba, derecha,
abajo]. Sin foco, la cámara recorre la pantalla despacio de arriba abajo.

Necesita Pillow, edge-tts, imageio-ffmpeg (que trae su propio ffmpeg) y, para
mover la cámara a buena velocidad, OpenCV y NumPy —con Pillow solo, cada cuadro
tardaba veinte veces más—:

    pip install Pillow edge-tts imageio-ffmpeg opencv-python-headless numpy
"""

import asyncio
import hashlib
import json
import os
import math
import subprocess
import sys
from concurrent.futures import ProcessPoolExecutor
from pathlib import Path

try:
    import cv2
    import edge_tts
    import imageio_ffmpeg
    import numpy as np
    from PIL import Image, ImageDraw, ImageFilter, ImageFont
except ImportError as falta:
    sys.exit(f'Falta una librería: {falta.name}. Instálala con:\n'
             f'    pip install Pillow edge-tts imageio-ffmpeg opencv-python-headless numpy')

RAIZ     = Path(__file__).resolve().parent.parent
CAPTURAS = RAIZ / 'docs' / 'manual' / 'img'
VIDEOS   = RAIZ / 'docs' / 'videos'
GUIONES  = VIDEOS / 'guiones'
VOZ_DIR  = VIDEOS / '.voz'
FUENTES  = RAIZ / 'assets' / 'fonts'
FFMPEG   = imageio_ffmpeg.get_ffmpeg_exe()

# Otra voz para probar, sin tocar esta línea:
#     VIDEOS_VOZ=es-MX-JorgeNeural python herramientas/videos_manual.py 05
# El video sale con el nombre de la voz al final (05-avisos-jorge.mp4) y no
# pisa el de la voz de siempre. Va por variable de entorno, no por argumento,
# porque los procesos que arman cada video la heredan solos.
VOZ_DE_SIEMPRE = 'es-MX-DaliaNeural'
VOZ       = os.environ.get('VIDEOS_VOZ') or VOZ_DE_SIEMPRE
VELOCIDAD = '-4%'
MUESTREO  = 24000          # Hz: lo que entrega la voz, mono

ANCHO, ALTO = 1920, 1080
FPS         = 25

# Pausas, en segundos.
ANTES_DE_HABLAR   = 0.35
DESPUES_DE_HABLAR = 0.55
FUNDIDO           = 0.45   # entre una escena y la siguiente
CIERRE            = 3.5

# Los colores del sitio (assets/css/publico.css).
AZUL_NOCHE = (0x16, 0x23, 0x3A)
AZUL       = (0x1E, 0x4D, 0x8B)
DORADO     = (0xC9, 0xA2, 0x27)
MARFIL     = (0xF1, 0xEC, 0xE0)
BLANCO     = (0xFF, 0xFF, 0xFF)

# Palabras que la voz lee mal tal como se escriben. Solo cambian lo que se
# dice; el subtítulo conserva la ortografía del manual.
DICCION = {
    'MESC':     'Mesc',
    'WebP':     'web pe',
    'JECSA':    'Jecsa',
    'AMA':      'Ama',
    'OMPE':     'ompe',
    'Coord.':   'coordinación',
}


# ---------------------------------------------------------------- fuentes ---

def fuente(familia: str, tamano: int, peso: int) -> ImageFont.FreeTypeFont:
    archivo = {'inter': 'inter-latin-wght-normal.woff2',
               'fraunces': 'fraunces-latin-wght-normal.woff2'}[familia]
    f = ImageFont.truetype(str(FUENTES / archivo), tamano)
    try:
        f.set_variation_by_axes([peso])
    except Exception:
        pass   # sin soporte de fuentes variables: queda el peso por omisión
    return f


# -------------------------------------------------------------------- voz ---

def para_la_voz(texto: str) -> str:
    for escrito, dicho in DICCION.items():
        texto = texto.replace(escrito, dicho)
    return texto


async def _sintetizar(texto: str, destino_mp3: Path) -> list:
    comunicar = edge_tts.Communicate(para_la_voz(texto), VOZ, rate=VELOCIDAD,
                                     boundary='SentenceBoundary')
    audio, oraciones = bytearray(), []
    async for trozo in comunicar.stream():
        if trozo['type'] == 'audio':
            audio += trozo['data']
        elif trozo['type'] == 'SentenceBoundary':
            oraciones.append([trozo['offset'] / 1e7, trozo['duration'] / 1e7])
    destino_mp3.write_bytes(bytes(audio))
    return oraciones


def voz(texto: str) -> tuple[bytes, list]:
    """
    PCM de 16 bits mono y los tiempos de cada oración [inicio, duración].
    Se piden a la voz una sola vez por texto; después salen del caché.
    """
    VOZ_DIR.mkdir(parents=True, exist_ok=True)
    huella = hashlib.sha1(f'{VOZ}|{VELOCIDAD}|{para_la_voz(texto)}'.encode()).hexdigest()[:16]
    mp3, tiempos = VOZ_DIR / f'{huella}.mp3', VOZ_DIR / f'{huella}.json'

    if not (mp3.exists() and tiempos.exists()):
        for intento in range(4):
            try:
                oraciones = asyncio.run(_sintetizar(texto, mp3))
                break
            except Exception as error:
                if intento == 3:
                    raise RuntimeError(f'La voz no respondió: {error}') from error
        tiempos.write_text(json.dumps(oraciones), encoding='utf-8')

    pcm = subprocess.run([FFMPEG, '-v', 'error', '-i', str(mp3), '-f', 's16le',
                          '-ac', '1', '-ar', str(MUESTREO), '-'],
                         capture_output=True, check=True).stdout
    return pcm, json.loads(tiempos.read_text(encoding='utf-8'))


# ------------------------------------------------------------ subtítulos ---

def oraciones_de(texto: str) -> list[str]:
    """Parte el texto como lo parte la voz: por punto, signo de cierre o dos puntos al final."""
    partes, actual = [], ''
    for i, c in enumerate(texto):
        actual += c
        siguiente = texto[i + 1] if i + 1 < len(texto) else ' '
        if c in '.?!…' and siguiente == ' ':
            partes.append(actual.strip())
            actual = ''
    if actual.strip():
        partes.append(actual.strip())
    return partes


def renglones(texto: str, letra, ancho_max: int) -> list[str]:
    palabras, lineas, actual = texto.split(), [], ''
    for p in palabras:
        prueba = f'{actual} {p}'.strip()
        if letra.getlength(prueba) <= ancho_max or not actual:
            actual = prueba
        else:
            lineas.append(actual)
            actual = p
    if actual:
        lineas.append(actual)
    return lineas


def trocear(oracion: str, letra, ancho_max: int) -> list[str]:
    """Una oración larga se reparte en tarjetas de dos renglones, cortando de preferencia en una coma."""
    lineas = renglones(oracion, letra, ancho_max)
    if len(lineas) <= 2:
        return [oracion]
    # Cortar en la coma más cercana a la mitad, si la hay; si no, en la mitad.
    palabras = oracion.split()
    mitad = len(palabras) // 2
    cortes = [i for i, p in enumerate(palabras[:-1], 1) if p[-1] in ',;:—)']
    corte = min(cortes, key=lambda i: abs(i - mitad)) if cortes else mitad
    if not 2 <= corte <= len(palabras) - 2:
        corte = mitad
    return (trocear(' '.join(palabras[:corte]), letra, ancho_max)
            + trocear(' '.join(palabras[corte:]), letra, ancho_max))


def subtitulos_de(texto: str, tiempos: list, inicio: float, duracion_audio: float,
                  letra, ancho_max: int) -> list[tuple[float, float, str]]:
    oraciones = oraciones_de(texto)
    # Si la voz partió distinto que nosotros, se reparte el tiempo por longitud.
    if len(tiempos) != len(oraciones):
        total = sum(len(o) for o in oraciones)
        t, tiempos = 0.0, []
        for o in oraciones:
            d = duracion_audio * len(o) / total
            tiempos.append([t, d])
            t += d

    cues = []
    for (desde, dura), oracion in zip(tiempos, oraciones):
        trozos = trocear(oracion, letra, ancho_max)
        total = sum(len(x) for x in trozos)
        t = desde
        for trozo in trozos:
            d = dura * len(trozo) / total
            cues.append((inicio + t, inicio + t + d, trozo))
            t += d
    # Cada tarjeta se queda hasta que llega la siguiente (sin parpadeos entre frases).
    for i in range(len(cues) - 1):
        a, _, txt = cues[i]
        siguiente = cues[i + 1][0]
        if siguiente - cues[i][1] < 0.6:
            cues[i] = (a, siguiente, txt)
    return cues


def marca_srt(s: float, separador: str = ',') -> str:
    ms = int(round(s * 1000))
    h, ms = divmod(ms, 3600000)
    m, ms = divmod(ms, 60000)
    seg, ms = divmod(ms, 1000)
    return f'{h:02}:{m:02}:{seg:02}{separador}{ms:03}'


def escribir_subtitulos(cues, base: Path):
    srt = []
    for n, (a, b, t) in enumerate(cues, 1):
        srt.append(f'{n}\n{marca_srt(a)} --> {marca_srt(b)}\n{t}\n')
    base.with_suffix('.srt').write_text('\n'.join(srt), encoding='utf-8')
    vtt = ['WEBVTT\n']
    for a, b, t in cues:
        vtt.append(f'{marca_srt(a, ".")} --> {marca_srt(b, ".")}\n{t}\n')
    base.with_suffix('.vtt').write_text('\n'.join(vtt), encoding='utf-8')


# --------------------------------------------------------------- imágenes ---

def escudo() -> Image.Image | None:
    """El logo cargado en Configuración (blanco, para fondo oscuro). Puede no estar."""
    mysql = Path('C:/xampp/mysql/bin/mysql.exe')
    if not mysql.exists():
        return None
    try:
        salida = subprocess.run([str(mysql), '-u', 'root', '-N', '-B', 'parroquia_nsdlp', '-e',
                                 "SELECT valor FROM configuracion WHERE clave = 'logo'"],
                                capture_output=True, text=True, timeout=15).stdout.strip()
    except Exception:
        return None
    ruta = RAIZ / salida
    if not salida or not ruta.exists():
        return None
    return Image.open(ruta).convert('RGBA')


def fondo_cortinilla() -> Image.Image:
    """
    La ilustración de la cabecera del sitio —el templo al atardecer—, tomada de
    la captura de la portada y oscurecida hacia la izquierda, donde va el texto.
    """
    ruta = CAPTURAS / 'publico-inicio.webp'
    if ruta.exists():
        with Image.open(ruta) as cap:
            w, h = cap.size
            # La ilustración va de debajo del menú (8 %) a cerca del 76 % del alto.
            arriba, abajo = int(h * 0.08), int(h * 0.76)
            alto = abajo - arriba
            ancho = int(alto * ANCHO / ALTO)
            x = max(0, (w - ancho) // 2 + int(w * 0.08))
            x = min(x, w - ancho)
            fondo = cap.convert('RGB').crop((x, arriba, x + ancho, abajo)).resize((ANCHO, ALTO), Image.LANCZOS)
    else:
        fondo = Image.new('RGB', (ANCHO, ALTO), AZUL_NOCHE)

    velo = Image.new('L', (ANCHO, 1))
    for x in range(ANCHO):
        velo.putpixel((x, 0), int(235 * max(0.0, 1 - x / (ANCHO * 0.78)) ** 0.9))
    velo = velo.resize((ANCHO, ALTO))
    noche = Image.new('RGB', (ANCHO, ALTO), AZUL_NOCHE)
    return Image.composite(noche, fondo, velo)


def cortinilla(capitulo: int, titulo: str, subtitulo: str, logo, final: bool = False) -> Image.Image:
    lienzo = fondo_cortinilla()
    d = ImageDraw.Draw(lienzo)
    x = 150

    if logo is not None:
        marca = logo.copy()
        marca.thumbnail((260, 120), Image.LANCZOS)
        lienzo.paste(marca, (x, 150), marca)

    sobre = fuente('inter', 34, 600)
    if final:
        d.text((x, 360), 'MANUAL DE USUARIO', font=sobre, fill=DORADO)
        grande = fuente('fraunces', 92, 500)
        y = 420
        for linea in renglones('Parroquia Nuestra Señora de la Paz', grande, 1100):
            d.text((x, y), linea, font=grande, fill=BLANCO)
            y += 108
        d.text((x, y + 30), 'El manual completo, con todas las pantallas, acompaña a estos videos.',
               font=fuente('inter', 34, 400), fill=MARFIL)
        return lienzo

    rotulo = f'CAPÍTULO {capitulo}' if capitulo else 'PRESENTACIÓN'
    d.text((x, 360), f'MANUAL DE USUARIO  ·  {rotulo}', font=sobre, fill=DORADO)
    grande = fuente('fraunces', 120, 500)
    y = 430
    for linea in renglones(titulo, grande, 1150):
        d.text((x, y), linea, font=grande, fill=BLANCO)
        y += 138
    if subtitulo:
        letra = fuente('inter', 42, 400)
        for linea in renglones(subtitulo, letra, 1050):
            d.text((x, y + 30), linea, font=letra, fill=MARFIL)
            y += 58
    return lienzo


def tarjeta_subtitulo(texto: str, letra) -> Image.Image:
    lineas = renglones(texto, letra, ANCHO_SUBTITULO)
    alto_linea = 62
    ancho = int(max(letra.getlength(l) for l in lineas)) + 72
    alto = alto_linea * len(lineas) + 34
    tarjeta = Image.new('RGBA', (ancho, alto), (0, 0, 0, 0))
    d = ImageDraw.Draw(tarjeta)
    d.rounded_rectangle((0, 0, ancho - 1, alto - 1), radius=18, fill=AZUL_NOCHE + (228,))
    y = 14
    for l in lineas:
        w = letra.getlength(l)
        d.text(((ancho - w) / 2, y), l, font=letra, fill=BLANCO)
        y += alto_linea
    return tarjeta


ANCHO_SUBTITULO = 1480


# ----------------------------------------------------------------- cámara ---

def suave(t: float) -> float:
    return 0.5 - 0.5 * math.cos(math.pi * max(0.0, min(1.0, t)))


def a_proporcion(caja, w, h):
    """Agranda la caja lo justo para que tenga la forma del video y cabe en la imagen."""
    x0, y0, x1, y1 = caja
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    bw, bh = x1 - x0, y1 - y0
    razon = ANCHO / ALTO
    if bw / bh < razon:
        bw = bh * razon
    else:
        bh = bw / razon
    # No acercarse de más: nunca menos del 38 % del ancho de la captura.
    if bw < w * 0.38:
        bw, bh = w * 0.38, w * 0.38 / razon
    if bw > w:
        bw, bh = w, w / razon
    if bh > h:
        bh, bw = h, h * razon
    x0 = min(max(0, cx - bw / 2), w - bw)
    y0 = min(max(0, cy - bh / 2), h - bh)
    return (x0, y0, x0 + bw, y0 + bh)


class Escena:
    """Una captura con su movimiento de cámara."""

    def __init__(self, nombre: str, foco):
        ruta = CAPTURAS / f'{nombre}.webp'
        if not ruta.exists():
            raise FileNotFoundError(f'No existe la captura {ruta.name}; ¿falta tomarla con capturas_manual.js?')
        self.imagen = Image.open(ruta).convert('RGB')
        w, h = self.imagen.size
        self.suelta = w < 1300 or h / w < 0.2   # un recorte: menú, barra, tarjeta

        if self.suelta:
            self.lamina = np.asarray(self._lamina())
            return

        # Al alejarse, la cámara toma de una copia ya reducida al ancho del
        # video: reducir con interpolación en cada cuadro hace que el texto
        # tiemble; reducir una vez, bien, y luego solo acercarse, no.
        self.original = np.asarray(self.imagen)
        self.reduccion = ANCHO / w
        self.reducida = cv2.resize(self.original, (ANCHO, round(h * self.reduccion)),
                                   interpolation=cv2.INTER_AREA)

        razon = ANCHO / ALTO
        completa = (0, 0, w, min(h, w / razon))           # todo el ancho, desde arriba
        if foco:
            fx0, fy0, fx1, fy1 = foco
            self.desde = completa
            self.hasta = a_proporcion((fx0 * w, fy0 * h, fx1 * w, fy1 * h), w, h)
        elif h / w > 1 / razon + 0.02:
            # Más alta que la pantalla: se recorre de arriba abajo, a todo lo ancho.
            self.desde = completa
            self.hasta = (0, h - completa[3], w, h)
        else:
            # Un leve acercamiento hacia el centro.
            self.desde = completa
            mx, my = w * 0.035, completa[3] * 0.035
            self.hasta = (mx, my, w - mx, completa[3] - my)

    def _lamina(self) -> Image.Image:
        """Un recorte pequeño se presenta entero, sobre marfil, como una hoja con sombra."""
        lienzo = Image.new('RGB', (ANCHO, ALTO), MARFIL)
        img = self.imagen.copy()
        img.thumbnail((ANCHO - 240, ALTO - 260), Image.LANCZOS)
        if img.width < 600 and img.height < 600:
            escala = min(2.0, 700 / img.width)
            img = self.imagen.resize((int(self.imagen.width * escala / 2 * 2),
                                      int(self.imagen.height * escala / 2 * 2)), Image.LANCZOS)
            img.thumbnail((ANCHO - 240, ALTO - 260), Image.LANCZOS)
        x, y = (ANCHO - img.width) // 2, (ALTO - 200 - img.height) // 2 + 30
        sombra = Image.new('L', (ANCHO, ALTO), 0)
        ImageDraw.Draw(sombra).rounded_rectangle((x + 6, y + 14, x + img.width + 6, y + img.height + 14),
                                                 radius=14, fill=90)
        sombra = sombra.filter(ImageFilter.GaussianBlur(18))
        lienzo.paste(Image.new('RGB', (ANCHO, ALTO), (60, 60, 70)), (0, 0), sombra)
        lienzo.paste(img, (x, y))
        return lienzo

    def cuadro(self, t: float) -> np.ndarray:
        if self.suelta:
            return self.lamina.copy()
        k = suave(t)
        x0, y0, x1, _ = (a + (b - a) * k for a, b in zip(self.desde, self.hasta))
        escala = (x1 - x0) / ANCHO
        origen = self.original
        if escala > 1.0001:
            origen = self.reducida
            x0, y0, escala = x0 * self.reduccion, y0 * self.reduccion, escala * self.reduccion
        matriz = np.float32([[escala, 0, x0], [0, escala, y0]])
        return cv2.warpAffine(origen, matriz, (ANCHO, ALTO),
                              flags=cv2.INTER_CUBIC | cv2.WARP_INVERSE_MAP,
                              borderMode=cv2.BORDER_REPLICATE)


# ------------------------------------------------------------------ video ---

def armar(guion_ruta: Path) -> str:
    guion = json.loads(guion_ruta.read_text(encoding='utf-8'))
    base = VIDEOS / guion_ruta.stem
    if VOZ != VOZ_DE_SIEMPRE:
        base = VIDEOS / f'{guion_ruta.stem}-{VOZ.split("-")[2].removesuffix("Neural").lower()}'
    letra_sub = fuente('inter', 46, 550)
    logo = escudo()

    # 1. La voz de cada tramo, y con ella la línea de tiempo.
    tramos = []   # (tipo, datos, inicio, duración, pcm, tiempos, texto)
    t = 0.0
    intro = guion.get('intro') or f'Capítulo {guion["capitulo"]}: {guion["titulo"]}.'
    partes = [('cortinilla', None, intro)] + [('escena', e, e['texto']) for e in guion['escenas']]
    for tipo, escena, texto in partes:
        pcm, tiempos = voz(texto)
        habla = len(pcm) / 2 / MUESTREO
        dura = ANTES_DE_HABLAR + habla + DESPUES_DE_HABLAR
        tramos.append((tipo, escena, t, dura, pcm, tiempos, texto, habla))
        t += dura
    tramos.append(('cierre', None, t, CIERRE, b'', [], '', 0.0))
    total = t + CIERRE

    # 2. El audio completo, con cada tramo en su lugar.
    pista = bytearray(int(total * MUESTREO) * 2 + MUESTREO * 2)
    cues = []
    for tipo, escena, inicio, dura, pcm, tiempos, texto, habla in tramos:
        if not pcm:
            continue
        pos = int((inicio + ANTES_DE_HABLAR) * MUESTREO) * 2
        pista[pos:pos + len(pcm)] = pcm
        cues += subtitulos_de(texto, tiempos, inicio + ANTES_DE_HABLAR, habla, letra_sub, ANCHO_SUBTITULO - 72)
    wav = base.with_suffix('.wav')
    _escribir_wav(wav, bytes(pista))
    escribir_subtitulos(cues, base)
    tarjetas = [(a, b, *_capa(tarjeta_subtitulo(txt, letra_sub))) for a, b, txt in cues]

    # 3. Los cuadros, directo a ffmpeg.
    mp4 = base.with_suffix('.mp4')
    ffmpeg = subprocess.Popen(
        [FFMPEG, '-v', 'error', '-y',
         '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-s', f'{ANCHO}x{ALTO}', '-r', str(FPS), '-i', '-',
         '-i', str(wav),
         '-c:v', 'libx264', '-preset', 'medium', '-crf', '21', '-pix_fmt', 'yuv420p',
         '-c:a', 'aac', '-b:a', '128k', '-shortest', '-movflags', '+faststart',
         '-metadata', f'title=Manual de usuario — {guion["capitulo"]}. {guion["titulo"]}',
         str(mp4)],
        stdin=subprocess.PIPE)

    portada = np.asarray(cortinilla(guion['capitulo'], guion['titulo'], guion.get('subtitulo', ''), logo))
    cierre = np.asarray(cortinilla(0, '', '', logo, final=True))
    camaras = {}
    anterior = None
    n_total = int(total * FPS)
    for n in range(n_total):
        ahora = n / FPS
        i = next(i for i, tr in enumerate(tramos) if ahora < tr[2] + tr[3] or i == len(tramos) - 1)
        tipo, escena, inicio, dura = tramos[i][:4]
        local = (ahora - inicio) / dura

        if tipo == 'cortinilla':
            cuadro = portada.copy()
        elif tipo == 'cierre':
            cuadro = cierre.copy()
        else:
            if i not in camaras:
                camaras[i] = Escena(escena['imagen'], escena.get('foco'))
                camaras.pop(i - 2, None)
            cuadro = camaras[i].cuadro(local)

        # Fundido con el final del tramo anterior.
        desde_inicio = ahora - inicio
        if i > 0 and desde_inicio < FUNDIDO and anterior is not None:
            k = suave(desde_inicio / FUNDIDO)
            cuadro = cv2.addWeighted(anterior, 1 - k, cuadro, k, 0)
        if i + 1 < len(tramos) and ahora + 1 / FPS >= inicio + dura:
            anterior = cuadro.copy()

        # El subtítulo vigente.
        for a, b, color, alfa in tarjetas:
            if a <= ahora < b:
                h, w = alfa.shape[:2]
                x, y = (ANCHO - w) // 2, ALTO - 70 - h
                zona = cuadro[y:y + h, x:x + w].astype(np.float32)
                cuadro[y:y + h, x:x + w] = (zona * (1 - alfa) + color * alfa).astype(np.uint8)
                break

        # Una línea dorada al pie que dice cuánto lleva el video.
        cuadro[ALTO - 6:, :int(ANCHO * ahora / total)] = DORADO
        ffmpeg.stdin.write(cuadro.tobytes())

    ffmpeg.stdin.close()
    if ffmpeg.wait() != 0:
        raise RuntimeError(f'ffmpeg falló con {mp4.name}')
    wav.unlink(missing_ok=True)
    minutos, segundos = divmod(int(total), 60)
    return f'  [ok] {mp4.name} ({minutos}:{segundos:02}, {mp4.stat().st_size // 1048576} MB)'


def _capa(imagen: Image.Image) -> tuple[np.ndarray, np.ndarray]:
    """Una imagen RGBA, partida en color y transparencia, lista para mezclar."""
    rgba = np.asarray(imagen).astype(np.float32)
    return rgba[..., :3], rgba[..., 3:] / 255.0


def _escribir_wav(ruta: Path, pcm: bytes):
    import wave
    with wave.open(str(ruta), 'wb') as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(MUESTREO)
        w.writeframes(pcm)


def _armar_seguro(ruta: Path) -> str:
    try:
        return armar(ruta)
    except Exception as error:
        return f'  [falló] {ruta.stem}: {error}'


def indice():
    """
    docs/videos/index.html: todos los videos armados en una sola página, para
    verlos desde la carpeta —una memoria USB, la computadora de la oficina—
    sin subirlos a ningún lado. No se versiona: se rehace con los videos.
    """
    from html import escape
    piezas = []
    for g in sorted(GUIONES.glob('*.json')):
        mp4 = VIDEOS / f'{g.stem}.mp4'
        if not mp4.exists():
            continue
        datos = json.loads(g.read_text(encoding='utf-8'))
        rotulo = f'Capítulo {datos["capitulo"]}' if datos['capitulo'] else 'Presentación'
        piezas.append(f'''
  <article>
    <p class="rotulo">{rotulo}</p>
    <h2>{escape(datos["titulo"])}</h2>
    <p>{escape(datos.get("subtitulo", ""))}</p>
    <video controls preload="metadata" src="{g.stem}.mp4"></video>
  </article>''')
    (VIDEOS / 'index.html').write_text(f'''<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Videos del manual</title>
<style>
  body {{ margin: 0; background: #f1ece0; color: #16233a; font: 17px/1.5 system-ui, sans-serif; }}
  header {{ background: #16233a; color: #fff; padding: 40px 16px; }}
  header h1 {{ margin: 0 auto; max-width: 960px; font: 500 40px/1.15 Georgia, serif; }}
  header p {{ margin: 8px auto 0; max-width: 960px; color: #f1ece0; }}
  main {{ max-width: 960px; margin: 0 auto; padding: 24px 16px 64px; }}
  article {{ margin: 0 0 48px; }}
  .rotulo {{ margin: 0; color: #9a7a14; font-weight: 600; }}
  h2 {{ margin: 0; font: 500 28px/1.2 Georgia, serif; }}
  video {{ width: 100%; margin-top: 12px; border-radius: 10px; background: #000; }}
</style>
</head>
<body>
<header>
  <h1>Videos del manual de usuario</h1>
  <p>Parroquia Nuestra Señora de la Paz · un video por capítulo, narrado y con subtítulos.</p>
</header>
<main>{"".join(piezas)}
</main>
</body>
</html>
''', encoding='utf-8')


def cuadricula(nombres: list[str]):
    """
    Para escribir un guion: copia de cada captura con una rejilla roja cada
    décimo del ancho y del alto, rotulada, de donde se leen las fracciones del
    «foco». Van a docs/videos/.cuadricula/, que no se versiona.
    """
    destino = VIDEOS / '.cuadricula'
    destino.mkdir(parents=True, exist_ok=True)
    for nombre in nombres:
        with Image.open(CAPTURAS / f'{nombre}.webp') as original:
            img = original.convert('RGB')
        img.thumbnail((1000, 1600))
        d = ImageDraw.Draw(img)
        for k in range(1, 10):
            x, y = img.width * k / 10, img.height * k / 10
            d.line((x, 0, x, img.height), fill=(230, 0, 0))
            d.line((0, y, img.width, y), fill=(230, 0, 0))
            d.text((x + 3, 3), f'{k / 10:.1f}', fill=(230, 0, 0))
            d.text((3, y + 3), f'{k / 10:.1f}', fill=(230, 0, 0))
        img.save(destino / f'{nombre}.jpg', quality=82)
        print(f'  {destino / nombre}.jpg')


def main():
    args = sys.argv[1:]
    procesos = 3
    if '--procesos' in args:
        i = args.index('--procesos')
        procesos = int(args[i + 1])
        del args[i:i + 2]

    if args[:1] == ['--cuadricula']:
        cuadricula(args[1:])
        return

    guiones = sorted(GUIONES.glob('*.json'))
    if '--lista' in args:
        # De paso revisa cada guion: que sea JSON válido, que sus capturas
        # existan y que los focos estén dentro de la imagen.
        for g in guiones:
            try:
                datos = json.loads(g.read_text(encoding='utf-8'))
                problemas = []
                for n, e in enumerate(datos['escenas'], 1):
                    if not (CAPTURAS / f'{e["imagen"]}.webp').exists():
                        problemas.append(f'escena {n}: no existe la captura {e["imagen"]}')
                    f = e.get('foco')
                    if f and not (len(f) == 4 and 0 <= f[0] < f[2] <= 1 and 0 <= f[1] < f[3] <= 1):
                        problemas.append(f'escena {n}: foco fuera de [0, 1] o al revés')
                palabras = sum(len(e['texto'].split()) for e in datos['escenas'])
                print(f'  {g.stem:40} {len(datos["escenas"]):2} escenas, ~{palabras / 150:.1f} min  {datos["titulo"]}')
                for p in problemas:
                    print(f'      ¡{p}!')
            except Exception as error:
                print(f'  {g.stem:40} ¡no se puede leer: {error}!')
        return
    if args:
        guiones = [g for g in guiones if any(g.stem.startswith(a) for a in args)]
    if not guiones:
        sys.exit('No hay guiones que coincidan.')

    VIDEOS.mkdir(parents=True, exist_ok=True)
    print(f'Armando {len(guiones)} video(s) en docs/videos/ ({procesos} a la vez)…')
    with ProcessPoolExecutor(max_workers=procesos) as grupo:
        for linea in grupo.map(_armar_seguro, guiones):
            print(linea, flush=True)
    indice()
    print('Índice: docs/videos/index.html')


if __name__ == '__main__':
    main()
