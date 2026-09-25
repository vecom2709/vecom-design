# -*- coding: utf-8 -*-
"""Warum traegt die Sonne nichts bei? Ein Lauf, der es beantwortet.

Gemessen am 17.09.2026: Ein Bild mit Sonne 4,9 W/m2 und eines mit 0,02
waren an der Fassade auf drei Nachkommastellen identisch. Entweder erreicht
das Sonnenlicht die Geometrie nicht, oder es wird von etwas anderem
uebertoent. Dieser Lauf schaltet beide Quellen einzeln ab.
"""
import os
import sys
import traceback

import bpy

S = os.path.dirname(os.path.abspath(__file__))
R = os.path.join(os.path.dirname(S), 'render')
if S not in sys.path:
    sys.path.insert(0, S)


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def zustand():
    so = bpy.data.objects.get('Sonne')
    print('[licht] Sonne-Objekt: %s' % so)
    if so:
        print('[licht]   energie=%.3f  farbe=%s' % (so.data.energy,
                                                    tuple(round(c, 3) for c in so.data.color)))
        print('[licht]   hide_render=%s  hide_viewport=%s  visible_get=%s'
              % (so.hide_render, so.hide_viewport, so.visible_get()))
        print('[licht]   Sammlungen: %s' % [c.name for c in so.users_collection])
        for at in ('visible_diffuse', 'visible_glossy', 'visible_transmission',
                   'visible_volume_scatter', 'visible_shadow'):
            print('[licht]   %s=%s' % (at, getattr(so, at, '-')))
        print('[licht]   Licht use_shadow=%s' % getattr(so.data, 'use_shadow', '-'))
        cy = getattr(so.data, 'cycles', None)
        if cy:
            print('[licht]   cycles: %s' % {a: getattr(cy, a) for a in dir(cy)
                                            if not a.startswith('_') and
                                            isinstance(getattr(cy, a), (bool, int, float))})
    vl = bpy.context.view_layer
    print('[licht] View-Layer: %s  use=%s' % (vl.name, vl.use))
    for lc in vl.layer_collection.children:
        print('[licht]   Sammlung %-22s ausgeschlossen=%s ausgeblendet=%s'
              % (lc.name, lc.exclude, lc.hide_viewport))
    sz = bpy.context.scene
    print('[licht] Welt: %s  Staerke=%s' % (sz.world.name if sz.world else None,
          sz.world.node_tree.nodes['Background'].inputs[1].default_value
          if sz.world and sz.world.use_nodes and 'Background' in sz.world.node_tree.nodes
          else '-'))
    print('[licht] cycles.use_light_tree=%s  max_bounces=%s'
          % (getattr(sz.cycles, 'use_light_tree', '-'), sz.cycles.max_bounces))


def bild(name, proben=72):
    sz = bpy.context.scene
    sz.render.filepath = os.path.join(R, name + '.png')
    bpy.ops.render.render(write_still=True)
    print('[licht] geschrieben: %s' % name)


try:
    lauf = lade('villa_lauf.py')
    d, kams, szene = lauf['aufbauen'](moebel=True, fasen=True, echt=True)
    garten = lade('villa_garten.py')
    garten['aufwerten'](mit_gras=False)          # Gras stoert die Frage nicht
    cyc = lade('villa_cycles.py')
    cyc['einstellen'](proben=72, breite=960, hoehe=540, rauschgrenze=0.02)
    sz = bpy.context.scene
    sz.camera = kams['garten']
    sz.view_settings.exposure = -2.2
    zustand()

    bg = sz.world.node_tree.nodes['Background']
    so = bpy.data.objects.get('Sonne')

    # 1 nur Himmel
    so.data.energy = 0.0
    bg.inputs[1].default_value = 0.35
    bild('l-nurhimmel')
    # 2 nur Sonne
    so.data.energy = 4.9
    bg.inputs[1].default_value = 0.0
    bild('l-nursonne')
    # 3 nur Sonne, kraeftig
    so.data.energy = 20.0
    bild('l-nursonne20')
    # 4 beides
    so.data.energy = 4.9
    bg.inputs[1].default_value = 0.35
    bild('l-beides')
    print('[licht] FERTIG')
except Exception:
    print('[licht] FEHLER')
    traceback.print_exc()
