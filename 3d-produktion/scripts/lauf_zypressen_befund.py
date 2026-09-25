# -*- coding: utf-8 -*-
"""Headless, nur lesen: Welche Baeume stehen in der Szene, woraus, wo?

Anlass (21.09.2026): Im Blick 'garten' stehen rechts glatte, zwoelfseitige
Kegel -- in der Vorlage wie in Unreal --, obwohl villa_garten.zypressen()
die Kegel aus villa.py durch Spindelbaeume ersetzen soll. Dieses Skript
baut die Szene wie der Export und listet jedes Objekt mit dem Material
Zypresse oder einem Namen mit 'zypresse': Name, Ecken, Mitte, Hoehe.
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
    namen_vor = sorted(o.name for o in bpy.data.objects if 'zypresse' in o.name.lower())
    print('[befund] nach villa.haupt: %d Objekte mit zypresse im Namen' % len(namen_vor))
    lade('villa_material.py')['anwenden']()
    lade('villa_garten.py')['aufwerten'](mit_gras=False)
    lade('villa_aussen.py')['moeblieren']()
    for o in sorted(bpy.data.objects, key=lambda o: o.name):
        if o.type != 'MESH':
            continue
        mats = [m.name for m in o.data.materials if m]
        if 'zypresse' not in o.name.lower() and 'Zypresse' not in mats:
            continue
        ecken = [o.matrix_world @ c.co for c in o.data.vertices]
        xs = [p.x for p in ecken]; ys = [p.y for p in ecken]; zs = [p.z for p in ecken]
        print('[befund] %-22s %5d Ecken  Mitte %6.2f %6.2f  z %5.2f..%5.2f  breit %.2f  %s  %s'
              % (o.name, len(ecken), (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2,
                 min(zs), max(zs), max(max(xs) - min(xs), max(ys) - min(ys)),
                 ','.join(mats), 'VERSTECKT' if o.hide_render else ''))
    print('[befund] FERTIG')
except Exception:
    print('[befund] FEHLER')
    traceback.print_exc()
