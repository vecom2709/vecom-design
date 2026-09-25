# -*- coding: utf-8 -*-
"""NUR LESEN: Wie sind die Flaechen der Exportteile schattiert?

Anlass (21.09.2026): In Unreal ist die Scheibe oben links dreiecksweise
weiss/grau -- typisch fuer schiefe, gemittelte Schattierungsnormalen.
Unreal rechnet die Normalen beim Bau neu (recompute_normals = True) und
richtet sich dabei nach den Glaettungsgruppen der FBX. Der Export
schreibt mesh_smooth_type='FACE', also je Flaeche "glatt" oder "hart"
aus use_smooth. Sind die grossen Flaechen als GLATT markiert, mittelt
Unreal ueber jede Kante -- auch ueber die 90-Grad-Kanten einer 36 mm
dicken Scheibe.

Ausgabe je Teil: Flaechen gesamt, davon glatt; Modifikatoren (Namen,
Typ) der ersten Objekte; ob ein Objekt eigene Normalen traegt.
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
    v = lade('villa.py')
    v['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()
    lade('villa_garten.py')['aufwerten'](mit_gras=False)
    lade('villa_aussen.py')['moeblieren']()
    ue = lade('villa_unreal.py')
    g = ue['gruppen']()
    dg = bpy.context.evaluated_depsgraph_get()
    for t in ('Glas', 'Sichtbeton', 'Putz_Weiss', 'Alu_Anthrazit', 'Travertin', 'Wasser'):
        obs = g.get(t) or []
        ges = glatt = eigen = 0
        mods = {}
        for o in obs:
            e = o.evaluated_get(dg)
            me = e.to_mesh()
            ges += len(me.polygons)
            glatt += sum(1 for p in me.polygons if p.use_smooth)
            if getattr(me, 'has_custom_normals', False):
                eigen += 1
            e.to_mesh_clear()
            for m in o.modifiers:
                k = '%s:%s' % (m.type, m.name)
                mods[k] = mods.get(k, 0) + 1
        print('[glatt] %-14s Objekte %3d  Flaechen %6d  davon glatt %6d  eigene Normalen %d  Mods %s'
              % (t, len(obs), ges, glatt, eigen, mods))
    print('[glatt] FERTIG')
except Exception:
    print('[glatt] FEHLER')
    traceback.print_exc()
