# -*- coding: utf-8 -*-
"""Headless: die Villa fuer Unreal exportieren.

Aufruf:  blender.exe -b -P lauf_unreal_export.py

Gebaut wird mit allem, was auch ins gerechnete Bild geht -- Gelaende,
Moebel, Aussenmoebel, Zypressen. Nur das Gras faellt weg: Partikelhaare
gehen nicht durch FBX, dafuer kommt ein Bueschel als eigenes Netz mit, aus
dem Unreal die Wiese selbst streut.

Die Veredelung (villa_echt) bleibt AUS: Rauheitsvariation, Kantenabrieb und
Staub haengen an prozeduralen Knoten, die FBX nicht kennt. Diese Ebene wird
in Unreal neu gebaut -- dort mit weltbezogenem Rauschen, das keine UV
braucht und in jeder Entfernung scharf bleibt.
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
    d = v['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()
    garten = lade('villa_garten.py')
    garten['aufwerten'](mit_gras=False)
    aussen = lade('villa_aussen.py')
    print('[ue] Aussen: %s' % aussen['moeblieren']())
    ue = lade('villa_unreal.py')
    bericht = ue['haupt']()
    print('[ue] Masse in Blender: %s' % bericht['masse_blender'])
    print('[ue] Teile: %d' % len(bericht['teile']))
    print('[ue] FERTIG')
except Exception:
    print('[ue] FEHLER')
    traceback.print_exc()
