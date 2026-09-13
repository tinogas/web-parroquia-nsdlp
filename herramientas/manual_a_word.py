#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
manual_a_word.py — Arma el manual de usuario en Word a partir de los capítulos
en Markdown de docs/manual/.

    python herramientas/manual_a_word.py

Deja docs/manual/Manual-de-usuario.docx con portada, índice, encabezado, pie
numerado y todos los capítulos, cada uno empezando en página nueva.

POR QUÉ NO SE ESCRIBE EL WORD A MANO: el manual se corrige seguido —cambia una
pantalla, cambia el texto que la explica— y un .docx no se puede revisar en un
diff ni fusionar entre dos ramas. La fuente es el Markdown, que sí; el Word es
lo que se entrega. Editar el .docx directamente funciona una vez, hasta que
alguien vuelva a correr este guion y lo pise: las correcciones van al .md.

Qué entiende del Markdown, que es el subconjunto que usan los capítulos:
encabezados de tres niveles, párrafos, listas con viñeta y numeradas, citas
(«>»), tablas, reglas horizontales, bloques de código, y dentro del texto
negritas, cursivas, `código` y enlaces.

Las capturas están en WebP —pesan la quinta parte que un PNG y el Markdown se
ve igual—, pero Word no las lee, así que aquí se convierten a PNG al vuelo y de
paso se reducen a un ancho razonable: un .docx guarda la imagen entera dentro
de sí, y a tamaño original el manual acabaría pesando más de lo que ningún
correo deja mandar. El original en WebP no se toca.

Necesita python-docx y Pillow, que ya están instalados en la máquina de
desarrollo:

    pip install python-docx Pillow
"""

import io
import re
import sys
from datetime import date
from pathlib import Path

try:
    from docx import Document
    from docx.enum.section import WD_SECTION
    from docx.enum.table import WD_TABLE_ALIGNMENT
    from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from docx.shared import Cm, Pt, RGBColor
    from PIL import Image
except ImportError as falta:
    sys.exit(f'Falta una librería: {falta.name}. Instálala con:\n'
             f'    pip install python-docx Pillow')

RAIZ    = Path(__file__).resolve().parent.parent
MANUAL  = RAIZ / 'docs' / 'manual'
DESTINO = MANUAL / 'Manual-de-usuario.docx'

# Los mismos tres colores del sitio (assets/css/app.css), para que el manual
# impreso se vea de la misma casa que la pantalla que explica.
AZUL   = RGBColor(0x1E, 0x4D, 0x8B)
DORADO = RGBColor(0xC9, 0xA2, 0x27)
OSCURO = RGBColor(0x16, 0x23, 0x3A)
GRIS   = RGBColor(0x6C, 0x75, 0x7D)

ANCHO_UTIL_CM  = 16.0   # Carta menos los márgenes de 2.5 cm
ALTO_MAXIMO_CM = 20.0   # para que una captura larga no ocupe dos páginas
ANCHO_MAXIMO_PX = 1600  # a 16 cm son unos 254 ppp: de sobra para imprimir


# ------------------------------------------------------------
# Utilidades de Word que python-docx no trae
# ------------------------------------------------------------

def _campo(parrafo, instruccion: str, dirty: bool = False):
    """Inserta un campo de Word (PAGE, TOC…), que python-docx no sabe crear."""
    campo = OxmlElement('w:fldSimple')
    campo.set(qn('w:instr'), instruccion)
    if dirty:
        campo.set(qn('w:dirty'), 'true')
    # Un run vacío dentro: es donde Word escribe el resultado al actualizar.
    run = OxmlElement('w:r')
    texto = OxmlElement('w:t')
    texto.text = ''
    run.append(texto)
    campo.append(run)
    parrafo._p.append(campo)


def _hipervinculo(parrafo, texto: str, url: str):
    """Enlace de verdad, azul y subrayado. python-docx no tiene API para esto."""
    relacion = parrafo.part.relate_to(
        url,
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
        is_external=True,
    )
    enlace = OxmlElement('w:hyperlink')
    enlace.set(qn('r:id'), relacion)

    run  = OxmlElement('w:r')
    prop = OxmlElement('w:rPr')
    color = OxmlElement('w:color')
    color.set(qn('w:val'), '1E4D8B')
    subrayado = OxmlElement('w:u')
    subrayado.set(qn('w:val'), 'single')
    prop.append(color)
    prop.append(subrayado)
    run.append(prop)

    nodo = OxmlElement('w:t')
    nodo.text = texto
    run.append(nodo)
    enlace.append(run)
    parrafo._p.append(enlace)


def _recuadro(parrafo, relleno: str, borde: str):
    """Fondo y filete de color a la izquierda, para las notas «Se llena desde…»."""
    prop = parrafo._p.get_or_add_pPr()

    sombreado = OxmlElement('w:shd')
    sombreado.set(qn('w:val'), 'clear')
    sombreado.set(qn('w:fill'), relleno)
    prop.append(sombreado)

    bordes = OxmlElement('w:pBdr')
    izquierdo = OxmlElement('w:left')
    izquierdo.set(qn('w:val'), 'single')
    izquierdo.set(qn('w:sz'), '18')
    izquierdo.set(qn('w:space'), '8')
    izquierdo.set(qn('w:color'), borde)
    bordes.append(izquierdo)
    prop.append(bordes)


def _idioma_espanol(documento):
    """
    Marca el documento entero como español de México. Sin esto Word revisa la
    ortografía en inglés y subraya en rojo el manual completo, que es la
    primera impresión que se lleva quien lo abre.
    """
    for estilo in documento.styles:
        if not hasattr(estilo, 'element'):
            continue
        prop = estilo.element.find(qn('w:rPr'))
        if prop is None:
            prop = OxmlElement('w:rPr')
            estilo.element.append(prop)
        idioma = OxmlElement('w:lang')
        idioma.set(qn('w:val'), 'es-MX')
        prop.append(idioma)


# ------------------------------------------------------------
# Aspecto del documento
# ------------------------------------------------------------

def preparar(documento):
    seccion = documento.sections[0]
    seccion.page_width    = Cm(21.59)   # Carta
    seccion.page_height   = Cm(27.94)
    for lado in ('left_margin', 'right_margin'):
        setattr(seccion, lado, Cm(2.5))
    seccion.top_margin    = Cm(2.5)
    seccion.bottom_margin = Cm(2.0)

    normal = documento.styles['Normal']
    normal.font.name = 'Calibri'
    normal.font.size = Pt(11)
    normal.paragraph_format.space_after = Pt(8)
    normal.paragraph_format.line_spacing = 1.15

    for nivel, (tamano, color, espacio) in {
        'Heading 1': (Pt(20), AZUL,   Pt(18)),
        'Heading 2': (Pt(15), AZUL,   Pt(14)),
        'Heading 3': (Pt(12), OSCURO, Pt(10)),
    }.items():
        estilo = documento.styles[nivel]
        estilo.font.name = 'Calibri'
        estilo.font.size = tamano
        estilo.font.color.rgb = color
        estilo.font.bold = True
        estilo.paragraph_format.space_before = espacio
        estilo.paragraph_format.space_after  = Pt(6)
        estilo.paragraph_format.keep_with_next = True

    cita = documento.styles['Quote']
    cita.font.color.rgb = OSCURO
    cita.font.italic = False
    cita.font.size = Pt(10)
    cita.paragraph_format.left_indent = Cm(0.4)
    cita.paragraph_format.space_before = Pt(4)
    cita.paragraph_format.space_after  = Pt(10)

    _idioma_espanol(documento)


def pie_de_pagina(documento, texto_izquierda: str):
    """
    Nombre de la parroquia a la izquierda, número de página a la derecha. La
    portada va sin pie: lleva su propio cierre y un «1» ahí abajo la afea.
    """
    seccion = documento.sections[0]
    seccion.different_first_page_header_footer = True

    pie = seccion.footer
    parrafo = pie.paragraphs[0]
    parrafo.text = ''
    parrafo.paragraph_format.tab_stops.add_tab_stop(Cm(ANCHO_UTIL_CM))

    run = parrafo.add_run(texto_izquierda + '\t')
    run.font.size = Pt(8)
    run.font.color.rgb = GRIS
    _campo(parrafo, ' PAGE ')
    for run in parrafo.runs:
        run.font.size = Pt(8)
        run.font.color.rgb = GRIS


def escudo() -> Path | None:
    """
    El logo que tenga cargado la parroquia en Configuración. Vive en uploads/,
    que no se versiona, así que puede no estar: en ese caso la portada sale sin
    él y no pasa nada.
    """
    from subprocess import run as ejecutar
    consulta = ("SELECT valor FROM configuracion WHERE clave = 'logo'")
    mysql = Path('C:/xampp/mysql/bin/mysql.exe')
    if not mysql.exists():
        return None
    try:
        salida = ejecutar([str(mysql), '-u', 'root', '-N', '-B', 'parroquia_nsdlp',
                           '-e', consulta], capture_output=True, text=True, timeout=15)
    except Exception:
        return None
    ruta = RAIZ / salida.stdout.strip()
    return ruta if salida.stdout.strip() and ruta.exists() else None


def logo_para_papel(ruta: Path) -> io.BytesIO:
    """
    El escudo del sitio es blanco: está hecho para la barra azul oscuro del
    encabezado, y sobre el papel blanco de la portada no se vería. Si en
    efecto viene casi blanco, se repinta del azul oscuro de la casa, conservando
    su silueta; si viniera oscuro —otro escudo, otro día— se deja como está.
    """
    with Image.open(ruta) as original:
        imagen = original.convert('RGBA')
        pixeles = list(imagen.getdata())

    opacos = [p for p in pixeles if p[3] > 40]
    claro = opacos and (sum(sum(p[:3]) for p in opacos) / (3 * len(opacos))) > 200

    fondo = Image.new('RGBA', imagen.size, (255, 255, 255, 255))
    tinta = Image.new('RGBA', imagen.size, (0x16, 0x23, 0x3A, 255)) if claro else imagen
    fondo.paste(tinta, mask=imagen.split()[3])

    memoria = io.BytesIO()
    fondo.convert('RGB').save(memoria, format='PNG', optimize=True)
    memoria.seek(0)
    return memoria


def portada(documento, version: str):
    for _ in range(2):
        documento.add_paragraph()

    logo = escudo()
    if logo:
        parrafo = documento.add_paragraph()
        parrafo.alignment = WD_ALIGN_PARAGRAPH.CENTER
        parrafo.add_run().add_picture(logo_para_papel(logo), height=Cm(3.2))
        documento.add_paragraph()

    titulo = documento.add_paragraph()
    titulo.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = titulo.add_run('Manual de usuario')
    run.font.size = Pt(36)
    run.font.bold = True
    run.font.color.rgb = AZUL

    for texto, tamano, color in [
        ('Parroquia Nuestra Señora de la Paz', Pt(18), OSCURO),
        ('Sitio web y panel de administración', Pt(13), GRIS),
    ]:
        parrafo = documento.add_paragraph()
        parrafo.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = parrafo.add_run(texto)
        run.font.size = tamano
        run.font.color.rgb = color

    for _ in range(8):
        documento.add_paragraph()

    meses = ('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
             'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre')
    hoy = date.today()
    pie = documento.add_paragraph()
    pie.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = pie.add_run(f'Versión {version} · {meses[hoy.month - 1]} de {hoy.year}')
    run.font.size = Pt(10)
    run.font.color.rgb = GRIS

    documento.add_paragraph().add_run().add_break(WD_BREAK.PAGE)


def indice(documento):
    # A mano y no con el estilo Título 1: si lo llevara, el índice se listaría
    # a sí mismo como su primera entrada.
    encabezado = documento.add_paragraph()
    encabezado.paragraph_format.space_after = Pt(14)
    run = encabezado.add_run('Contenido')
    run.font.size = Pt(20)
    run.font.bold = True
    run.font.color.rgb = AZUL

    _campo(documento.add_paragraph(), r' TOC \o "1-2" \h \z \u ', dirty=True)

    nota = documento.add_paragraph()
    run = nota.add_run('Si el índice aparece vacío, haz clic sobre él y pulsa F9 '
                       'para que Word lo arme con los números de página.')
    run.font.size = Pt(8)
    run.font.italic = True
    run.font.color.rgb = GRIS

    documento.add_paragraph().add_run().add_break(WD_BREAK.PAGE)


# ------------------------------------------------------------
# Markdown → Word
# ------------------------------------------------------------

# Negrita, cursiva, código y enlaces, en una sola pasada para no pisarse entre sí.
TROZOS = re.compile(
    r'(\*\*.+?\*\*)'      # **negrita**
    r'|(\*[^*]+?\*)'      # *cursiva*
    r'|(`[^`]+?`)'        # `código`
    r'|(\[[^\]]+\]\([^)]+\))'  # [texto](destino)
)


def escribir_texto(parrafo, texto: str):
    """Vuelca una línea de Markdown en un párrafo ya creado, con su formato."""
    for trozo in TROZOS.split(texto):
        if not trozo:
            continue
        if trozo.startswith('**') and trozo.endswith('**'):
            parrafo.add_run(trozo[2:-2]).bold = True
        elif trozo.startswith('*') and trozo.endswith('*'):
            parrafo.add_run(trozo[1:-1]).italic = True
        elif trozo.startswith('`') and trozo.endswith('`'):
            run = parrafo.add_run(trozo[1:-1])
            run.font.name = 'Consolas'
            run.font.size = Pt(9.5)
        elif trozo.startswith('['):
            etiqueta, destino = re.match(r'\[([^\]]+)\]\(([^)]+)\)', trozo).groups()
            if destino.startswith(('http://', 'https://')):
                _hipervinculo(parrafo, etiqueta, destino)
            else:
                # Un enlace a otro capítulo no lleva a ninguna parte dentro del
                # Word: se queda el texto, que es lo que el lector necesita.
                parrafo.add_run(etiqueta)
        else:
            parrafo.add_run(trozo)


def insertar_imagen(documento, ruta: Path, pie_texto: str):
    if not ruta.exists():
        print(f'  [falta] {ruta.name}')
        return

    with Image.open(ruta) as imagen:
        imagen = imagen.convert('RGB')
        if imagen.width > ANCHO_MAXIMO_PX:
            alto = round(imagen.height * ANCHO_MAXIMO_PX / imagen.width)
            imagen = imagen.resize((ANCHO_MAXIMO_PX, alto), Image.LANCZOS)
        proporcion = imagen.height / imagen.width
        memoria = io.BytesIO()
        # Una pantalla del panel son cuatro grises, un azul y texto: cabe en una
        # paleta de 256 colores sin que se le note, y así pesa una cuarta parte.
        # Las que llevan fotografías —la galería, la portada de un curso— no
        # caben, y esas van en JPEG: al revés, en paleta se verían sucias.
        plana = imagen.getcolors(maxcolors=4096) is not None
        if plana:
            imagen.convert('P', palette=Image.ADAPTIVE, colors=256).save(
                memoria, format='PNG', optimize=True)
        else:
            imagen.save(memoria, format='JPEG', quality=88, optimize=True)
    memoria.seek(0)

    ancho = ANCHO_UTIL_CM
    if ancho * proporcion > ALTO_MAXIMO_CM:
        ancho = ALTO_MAXIMO_CM / proporcion

    parrafo = documento.add_paragraph()
    parrafo.alignment = WD_ALIGN_PARAGRAPH.CENTER
    parrafo.add_run().add_picture(memoria, width=Cm(ancho))

    if pie_texto:
        leyenda = documento.add_paragraph()
        leyenda.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = leyenda.add_run(pie_texto)
        run.font.size = Pt(8.5)
        run.font.italic = True
        run.font.color.rgb = GRIS


def insertar_tabla(documento, filas):
    """`filas` son las líneas del Markdown ya partidas por «|», sin la de guiones."""
    tabla = documento.add_table(rows=len(filas), cols=len(filas[0]))
    tabla.style = 'Light Grid Accent 1'
    tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, fila in enumerate(filas):
        for j, celda in enumerate(fila):
            parrafo = tabla.cell(i, j).paragraphs[0]
            escribir_texto(parrafo, celda)
            for run in parrafo.runs:
                run.font.size = Pt(10)
                if i == 0:
                    run.bold = True
    documento.add_paragraph()


def convertir(documento, texto: str, carpeta: Path):
    """Recorre el Markdown de un capítulo y lo va escribiendo en el documento."""
    lineas = texto.split('\n')
    i = 0
    # Las líneas de un mismo párrafo se juntan ANTES de escribirlas, no una a
    # una: en el Markdown una negrita puede empezar en un renglón y cerrar en
    # el siguiente, y escribiendo por renglones los dos asteriscos salían
    # impresos en el Word.
    pendiente = []

    def cerrar():
        if not pendiente:
            return
        parrafo = documento.add_paragraph()
        parrafo.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        escribir_texto(parrafo, ' '.join(pendiente))
        pendiente.clear()

    while i < len(lineas):
        linea = lineas[i]
        desnuda = linea.strip()

        # --- Línea en blanco: cierra el párrafo que viniera ---
        if not desnuda:
            cerrar()
            i += 1
            continue

        # --- Bloque de código ---
        if desnuda.startswith('```'):
            cerrar()
            i += 1
            codigo = []
            while i < len(lineas) and not lineas[i].strip().startswith('```'):
                codigo.append(lineas[i])
                i += 1
            i += 1
            parrafo = documento.add_paragraph()
            parrafo.paragraph_format.left_indent = Cm(0.5)
            run = parrafo.add_run('\n'.join(codigo))
            run.font.name = 'Consolas'
            run.font.size = Pt(9)
            continue

        # --- Encabezados ---
        if desnuda.startswith('#'):
            cerrar()
            nivel = len(desnuda) - len(desnuda.lstrip('#'))
            documento.add_paragraph(
                desnuda[nivel:].strip(), style=f'Heading {min(nivel, 3)}')
            i += 1
            continue

        # --- Regla horizontal ---
        if re.fullmatch(r'-{3,}|\*{3,}', desnuda):
            cerrar()
            i += 1
            continue

        # --- Imagen (siempre sola en su línea) ---
        imagen = re.fullmatch(r'!\[([^\]]*)\]\(([^)]+)\)', desnuda)
        if imagen:
            cerrar()
            insertar_imagen(documento, carpeta / imagen.group(2), imagen.group(1))
            i += 1
            continue

        # --- Tabla ---
        if desnuda.startswith('|'):
            cerrar()
            filas = []
            while i < len(lineas) and lineas[i].strip().startswith('|'):
                celdas = [c.strip() for c in lineas[i].strip().strip('|').split('|')]
                if not all(re.fullmatch(r':?-{2,}:?', c) for c in celdas):
                    filas.append(celdas)
                i += 1
            if filas:
                insertar_tabla(documento, filas)
            continue

        # --- Cita ---
        if desnuda.startswith('>'):
            cerrar()
            partes = []
            while i < len(lineas) and lineas[i].strip().startswith('>'):
                partes.append(lineas[i].strip().lstrip('>').strip())
                i += 1
            parrafo = documento.add_paragraph(style='Quote')
            escribir_texto(parrafo, ' '.join(partes))
            _recuadro(parrafo, relleno='F4F6F9', borde='C9A227')
            continue

        # --- Listas ---
        vineta   = re.match(r'^(\s*)[-*]\s+(.*)$', linea)
        numerada = re.match(r'^(\s*)\d+\.\s+(.*)$', linea)
        if vineta or numerada:
            cerrar()
            sangria, contenido = (vineta or numerada).groups()
            estilo = 'List Bullet' if vineta else 'List Number'
            if len(sangria) >= 2:
                estilo += ' 2'
            # Una entrada puede seguir en las líneas de abajo, sangradas.
            i += 1
            while (i < len(lineas) and lineas[i].strip()
                   and lineas[i].startswith(' ')
                   and not re.match(r'^\s*([-*]|\d+\.)\s', lineas[i])):
                contenido += ' ' + lineas[i].strip()
                i += 1
            parrafo = documento.add_paragraph(style=estilo)
            escribir_texto(parrafo, contenido)
            continue

        # --- Párrafo normal ---
        pendiente.append(desnuda)
        i += 1

    cerrar()


# ------------------------------------------------------------

def version_de_la_app() -> str:
    """La misma APP_VERSION de config/app.php, para no llevar dos cuentas."""
    texto = (RAIZ / 'config' / 'app.php').read_text(encoding='utf-8')
    encontrado = re.search(r"APP_VERSION'?\s*,\s*'([^']+)'", texto)
    return encontrado.group(1) if encontrado else 's/n'


def rematar_con_word(docx: Path, exportar_pdf: bool) -> None:
    """
    Abre el documento con el Word instalado para dos cosas que python-docx no
    puede hacer sola: rellenar el índice —que hasta aquí es un campo vacío, y
    quien reciba el manual no tiene por qué saber que se actualiza con F9— y,
    si se pidió, dejar al lado un PDF para repartir.

    Si no hay Word en la máquina, se avisa y no pasa nada: el .docx ya está
    completo y el índice se arma al abrirlo.
    """
    try:
        import win32com.client as com
    except ImportError:
        print('  (sin pywin32: el índice se armará al abrir el documento)')
        return

    try:
        word = com.Dispatch('Word.Application')
    except Exception:
        print('  (sin Word instalado: el índice se armará al abrir el documento)')
        return

    word.Visible = False
    word.DisplayAlerts = 0
    documento = word.Documents.Open(str(docx), False, False)
    try:
        for tabla in documento.TablesOfContents:
            tabla.Update()
        documento.Save()
        if exportar_pdf:
            pdf = docx.with_suffix('.pdf')
            documento.ExportAsFixedFormat(str(pdf), 17)   # 17 = PDF
            print(f'  {pdf.relative_to(RAIZ)}')
    finally:
        documento.Close(0)
        word.Quit()


def main():
    capitulos = sorted(MANUAL.glob('[0-9][0-9]-*.md'))
    if not capitulos:
        sys.exit(f'No hay capítulos en {MANUAL}. Los archivos se llaman NN-titulo.md')

    documento = Document()
    preparar(documento)
    pie_de_pagina(documento, 'Parroquia Nuestra Señora de la Paz')
    portada(documento, version_de_la_app())
    indice(documento)

    for numero, capitulo in enumerate(capitulos):
        if numero:
            documento.add_paragraph().add_run().add_break(WD_BREAK.PAGE)
        print(f'  {capitulo.name}')
        convertir(documento, capitulo.read_text(encoding='utf-8'), MANUAL)

    documento.save(DESTINO)
    rematar_con_word(DESTINO, exportar_pdf='--pdf' in sys.argv)

    kb = round(DESTINO.stat().st_size / 1024)
    print(f'\n{DESTINO.relative_to(RAIZ)} ({kb} KB, {len(capitulos)} capítulos)')


if __name__ == '__main__':
    main()
