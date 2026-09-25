# -*- coding: utf-8 -*-
"""Headless: NUR das Fernnetz neu fuer Unreal exportieren.

Aufruf:  werkzeug\\blender-lauf.ps1 -Skript lauf_fern_export.py -Log fern-export.log

WARUM EIN EIGENER LAUF (21.09.2026)
In villa_garten.fernbereich zeigten alle Flaechen nach unten (Umlaufsinn,
siehe dort). Cycles merkt davon nichts, Unreals Pfadverfolger zeichnet
die Rueckseite eines einseitigen Materials schwarz -- als Band am
Horizont. Geaendert ist nur dieses eine Netz. Der ganze Export wuerde
alle 40 FBX neu schreiben und in Unreal alles neu einlesen lassen; das
ist hier nicht noetig und birgt nur Risiko. Also: Gelaende bauen, das
Fernnetz schreiben, sonst nichts.

Der Materialschacht bekommt denselben Namen wie beim grossen Export
("Rasen"), damit Unreal beim Ersetzen denselben Schacht wiederfindet.
Die Instanz MI_Rasen setzt v112_fern.py drueben trotzdem ausdruecklich.
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
    import bpy
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o, do_unlink=True)
    garten = lade('villa_garten.py')
    garten['gelaende']()
    ob = bpy.data.objects.get('p08_gelaende_fern')
    if ob is None:
        raise RuntimeError('p08_gelaende_fern wurde nicht gebaut')
    mat = bpy.data.materials.get('Rasen') or bpy.data.materials.new('Rasen')
    ob.data.materials.clear()
    ob.data.materials.append(mat)
    oben = sum(1 for p in ob.data.polygons if p.normal.z > 0.0)
    print('[fern] Flaechen nach oben: %d von %d' % (oben, len(ob.data.polygons)))
    if oben != len(ob.data.polygons):
        raise RuntimeError('Nicht alle Flaechen zeigen nach oben -- nicht exportiert')
    ue = lade('villa_unreal.py')
    pfad, groesse = ue['fbx_schreiben']('SM_GELAENDE_FERN', [ob])
    print('[fern] geschrieben: %s  %d KB' % (pfad, groesse // 1024))
    print('[fern] FERTIG')
except Exception:
    print('[fern] FEHLER')
    traceback.print_exc()
