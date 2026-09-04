#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
extraer_proclamadores.py — Pasa Proclamadores.xlsx a JSON revisable.

    python herramientas/extraer_proclamadores.py                    # deja proclamadores.json y un resumen en pantalla
    python herramientas/extraer_proclamadores.py --xlsx otra.xlsx --json otra.json

Mismo reparto de trabajo que extraer_agenda.py: Python abre el xlsx y PHP
escribe en la base (el PHP de este XAMPP no trae la extensión zip, así que no
puede leer un xlsx). Lo que sale de aquí lo importa
herramientas/importar_proclamadores.php.

La hoja la levantó la propia pastoral con un formulario, y viene con tres
problemas que este script no arregla en silencio, sino que marca:

1. El año de nacimiento suele ser el año en curso. Quien llenó el formulario
   escribió solo el día y el mes, y la hoja de cálculo completó el año sola.
   Aquí eso se detecta (un año que no deja a la persona con edad para
   proclamar) y se sustituye por 1900, que es la marca convenida de "año
   desconocido": el día y el mes, que son los que se celebran, se conservan.
   Ver docs/BASE-DE-DATOS.md → personas.fecha_nacimiento.
2. Una fecha viene como texto suelto ("13/5/0054").
3. Hay un nombre repetido con dos fechas distintas. Con dos respuestas que se
   contradicen no se inventa una: la fila sale sin fecha y con el conflicto
   anotado, para que la parroquia lo confirme.

Los nombres se normalizan a Mayúsculas Iniciales porque en la hoja hay de todo
—ALL CAPS, minúsculas— y en el equipo pastoral se ven juntos.
"""

import argparse
import datetime
import json
import re
import sys
import unicodedata
from collections import OrderedDict

try:
    import openpyxl
except ImportError:
    sys.exit("Falta openpyxl:  python -m pip install openpyxl")

# La consola de Windows no viene en UTF-8, y este resumen está lleno de
# acentos y de eñes: sin esto sale ilegible justo donde hay que leerlo.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# Año con el que se guardan los cumpleaños cuyo año real no se sabe. Tiene que
# ser el mismo que espera importar_proclamadores.php.
ANIO_DESCONOCIDO = 1900

# Nadie proclama antes de los diez años: un año de nacimiento posterior a esto
# es la hoja de cálculo autocompletando, no un dato.
EDAD_MINIMA = 10

# Partículas que van en minúscula dentro de un nombre propio.
PARTICULAS = {"de", "del", "la", "las", "los", "y", "e", "da", "dos"}

# Columna "Preferencias al proclamar" → claves de proclamadores.preferencias.
PREFERENCIAS = OrderedDict([
    ("monitor", "monitor"),
    ("lectura", "lectura"),
    ("salmo",   "salmo"),
])


def sin_acentos(texto):
    """'María' → 'maria', para comparar nombres escritos de formas distintas."""
    descompuesto = unicodedata.normalize("NFD", texto)
    return "".join(c for c in descompuesto if unicodedata.category(c) != "Mn")


def clave_nombre(nombre):
    """Forma canónica de un nombre, para detectar repetidos: sin acentos, minúsculas, un solo espacio."""
    return re.sub(r"\s+", " ", sin_acentos(nombre).lower()).strip()


def nombre_presentable(bruto):
    """'VANESSA PATRICIA BLANCARTE LIMON' y 'noyra luz silva lopez' → Mayúsculas Iniciales."""
    limpio = re.sub(r"\s+", " ", (bruto or "").strip())
    if not limpio:
        return ""

    palabras = []
    for i, palabra in enumerate(limpio.split(" ")):
        minuscula = palabra.lower()
        # La partícula solo va en minúscula si no abre el nombre.
        if i > 0 and minuscula in PARTICULAS:
            palabras.append(minuscula)
        else:
            palabras.append(minuscula[:1].upper() + minuscula[1:])
    return " ".join(palabras)


def anio_creible(anio):
    return ANIO_DESCONOCIDO < anio <= datetime.date.today().year - EDAD_MINIMA


def leer_fecha(celda):
    """
    Devuelve (fecha ISO o None, año_conocido, nota). Acepta la fecha real de la
    hoja y también el texto suelto de quien la escribió a mano.
    """
    if celda is None or (isinstance(celda, str) and celda.strip() == ""):
        return None, False, "sin fecha en la hoja"

    if isinstance(celda, (datetime.datetime, datetime.date)):
        dia, mes, anio = celda.day, celda.month, celda.year
    else:
        texto = str(celda).strip()
        partes = re.split(r"[/\-.]", texto)
        if len(partes) != 3 or not all(p.strip().isdigit() for p in partes):
            return None, False, "fecha ilegible en la hoja: %r" % texto
        dia, mes, anio = (int(p) for p in partes)

    if not (1 <= mes <= 12 and 1 <= dia <= 31):
        return None, False, "día o mes fuera de rango: %s/%s" % (dia, mes)

    if anio_creible(anio):
        return "%04d-%02d-%02d" % (anio, mes, dia), True, ""

    return ("%04d-%02d-%02d" % (ANIO_DESCONOCIDO, mes, dia), False,
            "el año de la hoja (%s) no es creíble; se guarda solo el día y el mes" % anio)


def leer_preferencias(celda):
    """'Monitor, Lectura, Salmo (cantado)' → ['monitor', 'lectura', 'salmo']."""
    texto = sin_acentos(str(celda or "")).lower()
    return [clave for palabra, clave in PREFERENCIAS.items() if palabra in texto]


def extraer(ruta_xlsx):
    libro = openpyxl.load_workbook(ruta_xlsx, data_only=True)
    hoja = libro.worksheets[0]

    filas = []
    for numero, fila in enumerate(hoja.iter_rows(min_row=2, values_only=True), start=2):
        nombre_bruto = fila[0] if len(fila) > 0 else None
        if not nombre_bruto or not str(nombre_bruto).strip():
            continue

        nombre = nombre_presentable(str(nombre_bruto))
        fecha, anio_conocido, nota = leer_fecha(fila[1] if len(fila) > 1 else None)

        filas.append({
            "hoja_fila": numero,
            "nombre": nombre,
            "clave": clave_nombre(nombre),
            "fecha_nacimiento": fecha,
            "anio_conocido": anio_conocido,
            "preferencias": leer_preferencias(fila[2] if len(fila) > 2 else None),
            "notas": [nota] if nota else [],
        })

    return fundir_repetidos(filas)


def fundir_repetidos(filas):
    """
    Un mismo nombre dos veces es la misma persona que llenó el formulario dos
    veces. Se funde en una sola fila; si las dos fechas no coinciden, la fila
    queda sin fecha y con el conflicto anotado —no se elige una al azar—.
    """
    por_clave = OrderedDict()

    for fila in filas:
        previa = por_clave.get(fila["clave"])
        if previa is None:
            por_clave[fila["clave"]] = fila
            continue

        previa["notas"].append(
            "repetida en la hoja (filas %s y %s)" % (previa["hoja_fila"], fila["hoja_fila"])
        )
        previa["preferencias"] = sorted(set(previa["preferencias"]) | set(fila["preferencias"]))

        if previa["fecha_nacimiento"] != fila["fecha_nacimiento"]:
            previa["notas"].append(
                "las dos filas dan cumpleaños distintos (%s y %s): queda sin fecha hasta que la parroquia confirme"
                % (previa["fecha_nacimiento"] or "sin fecha", fila["fecha_nacimiento"] or "sin fecha")
            )
            previa["fecha_nacimiento"] = None
            previa["anio_conocido"] = False

    return list(por_clave.values())


def main():
    parser = argparse.ArgumentParser(description="Pasa Proclamadores.xlsx a JSON revisable.")
    parser.add_argument("--xlsx", default="Proclamadores.xlsx", help="hoja de origen")
    parser.add_argument("--json", default="proclamadores.json", help="archivo de salida")
    argumentos = parser.parse_args()

    filas = extraer(argumentos.xlsx)

    with open(argumentos.json, "w", encoding="utf-8") as salida:
        json.dump(filas, salida, ensure_ascii=False, indent=2)

    con_anio  = sum(1 for f in filas if f["anio_conocido"])
    sin_fecha = sum(1 for f in filas if not f["fecha_nacimiento"])

    print("%d personas -> %s" % (len(filas), argumentos.json))
    print("   %d con año de nacimiento real" % con_anio)
    print("   %d con solo día y mes (año %d)" % (len(filas) - con_anio - sin_fecha, ANIO_DESCONOCIDO))
    print("   %d sin fecha" % sin_fecha)

    marcadas = [f for f in filas if f["notas"]]
    if marcadas:
        print("\nRevisar %d:" % len(marcadas))
        for fila in marcadas:
            print("  - %s" % fila["nombre"])
            for nota in fila["notas"]:
                print("      %s" % nota)


if __name__ == "__main__":
    main()
