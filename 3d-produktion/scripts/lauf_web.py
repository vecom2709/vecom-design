# -*- coding: utf-8 -*-
"""Headless: das Netz fuer den Browser bauen und ausgeben.

Aufruf:  blender.exe -b -P lauf_web.py

WAS MITKOMMT UND WAS NICHT
  mit     Haus, Innenausbau, Detailstuecke, Gartenmoebel.
  ohne    Gelaende (166.000 Dreiecke), Gras (450.000 Halme), die
          gebauten Zypressen, Kiesel, Mulch, Randgras -- alles, was nur
          im gerechneten Bild zaehlt und im Browser nur Bytes waere.

Die Moebel sind die Ausnahme von dieser Regel, und zwar aus einem
inhaltlichen Grund: Das Register "Foto oder Echtzeit" stellt beide
Fassungen an DEMSELBEN Standpunkt nebeneinander. Ein moebliertes Foto
neben einem leeren Echtzeitbild vergleicht zwei Szenen statt zweier
Verfahren.
"""
import os
import sys
import traceback

S = os.path.dirname(os.path.abspath(__file__))
if S not in sys.path:
    sys.path.insert(0, S)


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


try:
    v = lade('villa.py')
    # echt=False: Die Veredelung haengt an prozeduralen Knoten, die glTF
    # nicht mitnimmt. Fuer den Browser waere sie reine Rechenzeit.
    d = v['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()
    aussen = lade('villa_aussen.py')
    print('[web] Aussenmoebel: %s' % aussen['moeblieren'](leicht=True))
    web = lade('villa_web.py')
    erg = web['haupt'](d)
    print('[web] %d Bytes (%d KB), %d Materialien, %d Abschnitte'
          % (erg['bytes'], erg['bytes'] // 1024,
             len(erg['materialien']), erg['abschnitte']))
    print('[web] FERTIG')
except Exception:
    print('[web] FEHLER')
    traceback.print_exc()
