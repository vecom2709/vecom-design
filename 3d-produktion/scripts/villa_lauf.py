# -*- coding: utf-8 -*-
"""
villa_lauf.py — Ein Aufruf baut, richtet ein und rechnet.

Damit nicht bei jedem Durchgang dieselben sechs Zeilen neu getippt werden
und sich dabei eine davon aendert.
"""

import bpy
import os
import time

S = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\scripts'
R = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\render'
BLEND = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\vecom-villa.blend'


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def aufbauen(moebel=True, fasen=True, echt=True, luft=False):
    """Reihenfolge nach der Doktrin: Geometrie, dann Licht, dann Material.
    villa_echt kommt NACH villa_material, weil es an dessen Knoten
    anknuepft -- Rauheitsvariation, Kantenabrieb, Staub, Spritzwasser."""
    v = lade('villa.py')
    d = v['haupt'](speichern=False, moebel=moebel, fasen=fasen)
    lade('villa_material.py')['anwenden']()
    if echt:
        d['veredelt'] = lade('villa_echt.py')['veredeln']()
    szene = lade('villa_szene.py')
    kams = szene['alles'](motor='BLENDER_EEVEE', breite=1600, hoehe=900,
                          luft=luft)
    return d, kams, szene


def rechnen(namen, proben=220, breite=1600, hoehe=900, vorsatz='villa',
            rauschgrenze=0.012, speichern=True):
    d, kams, szene = aufbauen()
    cyc = lade('villa_cycles.py')
    info = cyc['einstellen'](proben=proben, breite=breite, hoehe=hoehe,
                             rauschgrenze=rauschgrenze)
    sz = bpy.context.scene
    raus = []
    for name in namen:
        if name not in kams:
            continue
        sz.camera = kams[name]
        bel = szene['belichtung_nach_kamera']()
        sz.render.filepath = os.path.join(R, '%s-%s.png' % (vorsatz, name))
        t0 = time.time()
        bpy.ops.render.render(write_still=True)
        raus.append((name, round(time.time() - t0, 1), round(bel, 2)))
    if speichern:
        bpy.ops.wm.save_as_mainfile(filepath=BLEND)
    return {'geraet': info['geraet'], 'bilder': raus,
            'objekte': d['objekte'], 'dreiecke': d['dreiecke']}
