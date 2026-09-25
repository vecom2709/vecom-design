# -*- coding: utf-8 -*-
"""Die Cycles-Vorlage als LINEARES Bild -- der Massstab zum Vergleichen.

WARUM

Bis heute wurde Unreal gegen ein PNG der Cycles-Vorlage gemessen. Beide
Bilder haben aber eine Tonwertkurve hinter sich, und zwar nicht
dieselbe: Blender AgX, Unreal seinen filmischen Tonemapper. Ein
Verhaeltnis "Schattenseite zu Sonnenseite" aus solchen Zahlen ist damit
kein physikalisches Verhaeltnis mehr, sondern eine Aussage ueber zwei
Kurven.

Fuer die Frage, die gerade ansteht -- wie stark ist das Himmelslicht im
Verhaeltnis zur Sonne -- braucht es lineare Werte. Dieses Skript rendert
dieselbe Kameraeinstellung ohne jede Kurve und ohne Belichtung als
32-Bit-EXR. Damit sind Unreal-EXR und Cycles-EXR direkt vergleichbar.

Klein und mit wenigen Proben: gemessen werden Mittelwerte ueber Flaechen,
und dafuer reicht das. Es geht nicht um ein Ansichtsbild.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript villa_exr.py -Log villa-exr.log
"""
import bpy
import os
import sys
import time

HIER = os.path.dirname(os.path.abspath(__file__))
if HIER not in sys.path:
    sys.path.append(HIER)

AUS = os.path.join(os.path.dirname(HIER), 'ausgabe')
KAMERAS = ['garten']
BREITE, HOEHE = 960, 540
PROBEN = 96


def lade(name):
    p = os.path.join(HIER, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def haupt():
    t0 = time.time()
    lauf = lade('villa_lauf.py')
    d, kams, szene = lauf['aufbauen']()
    print('[exr] Aufbau fertig: %d Objekte, %d Dreiecke, %.0f s'
          % (d['objekte'], d['dreiecke'], time.time() - t0))

    cyc = lade('villa_cycles.py')
    info = cyc['einstellen'](proben=PROBEN, breite=BREITE, hoehe=HOEHE,
                             rauschgrenze=0.02)
    print('[exr] Geraet: %s' % info['geraet'])

    sz = bpy.context.scene
    sz.render.image_settings.file_format = 'OPEN_EXR'
    sz.render.image_settings.color_depth = '32'
    sz.render.image_settings.color_mode = 'RGB'
    # KEINE Kurve und KEINE Belichtung -- genau darum geht es hier.
    sz.view_settings.view_transform = 'Raw'
    sz.view_settings.look = 'None'
    sz.view_settings.exposure = 0.0

    for name in KAMERAS:
        if name not in kams:
            print('[exr] Kamera fehlt: %s' % name)
            continue
        sz.camera = kams[name]
        ziel = os.path.join(AUS, 'cycles-linear-%s.exr' % name)
        sz.render.filepath = ziel
        t = time.time()
        bpy.ops.render.render(write_still=True)
        print('[exr] %s -> %s  (%.0f s)' % (name, ziel, time.time() - t))

    print('[exr] FERTIG')


haupt()
