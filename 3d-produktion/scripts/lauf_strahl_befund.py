# -*- coding: utf-8 -*-
"""Headless, nur lesen: Was liegt unter einem Bildpunkt der Vorlage?

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript lauf_strahl_befund.py -Log strahl.log -Werte garten,45:339,48:338

Baut die Szene wie villa_exr_gleich (ohne Gras), setzt die Kameras wie
dort (960 x 540) und schiesst durch jeden angegebenen Bildpunkt (x:y,
von oben links gezaehlt) einen Strahl. Ausgabe: getroffenes Objekt,
Material, Trefferpunkt, Normale. Anlass (21.09.2026): eine helle,
beige Scheibe unter der linken Zypresse, in Vorlage UND Unreal gleich
hell (Leuchtdichte 0,44) -- zu hell fuer Mulch mit Albedo 0,06.
"""
import os
import sys
import traceback

S = os.path.dirname(os.path.abspath(__file__))
if S not in sys.path:
    sys.path.insert(0, S)
BREITE, HOEHE = 960, 540


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


try:
    import bpy
    from mathutils import Vector
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    teile = []
    for a in argv:
        teile += [t for t in a.split(',') if t]
    kname = teile[0] if teile else 'garten'
    punkte = [tuple(float(z) for z in t.split(':')) for t in teile[1:]]

    lade('villa.py')['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()
    lade('villa_garten.py')['aufwerten'](mit_gras=False)
    lade('villa_aussen.py')['moeblieren']()
    szene = lade('villa_szene.py')
    szene['alles'](motor='BLENDER_EEVEE', breite=BREITE, hoehe=HOEHE, luft=False)
    sc = bpy.context.scene
    bpy.context.view_layer.update()
    kam = next((o for o in bpy.data.objects if o.type == 'CAMERA'
                and kname.lower() in o.name.lower()), None)
    if kam is None:
        raise RuntimeError('Kamera %s fehlt: %s' % (kname, [o.name for o in bpy.data.objects if o.type == 'CAMERA']))
    print('[strahl] Kamera %s bei %s' % (kam.name, tuple(round(c, 2) for c in kam.matrix_world.translation)))
    rahmen = kam.data.view_frame(scene=sc)   # oben rechts, unten rechts, unten links, oben links
    ore, ure, uli, oli = [kam.matrix_world @ v for v in rahmen]
    ort = kam.matrix_world.translation
    dg = bpy.context.evaluated_depsgraph_get()
    for (px, py) in punkte:
        u = (px + 0.5) / BREITE
        w = (py + 0.5) / HOEHE
        oben = oli.lerp(ore, u)
        unten = uli.lerp(ure, u)
        ziel = oben.lerp(unten, w)
        r = (ziel - ort).normalized()
        # Durch Glas hindurch weiterverfolgen (gerade, ohne Brechung --
        # es geht nur darum, WAS dahinter liegt).
        start = ort
        for stufe in range(6):
            treffer, p, n, idx, ob, mw = sc.ray_cast(dg, start, r)
            if not treffer:
                print('[strahl] %4d:%-4d %snichts getroffen' % (px, py, '  ' * stufe))
                break
            mat = ob.material_slots[0].material.name if ob.material_slots and ob.material_slots[0].material else '-'
            print('[strahl] %4d:%-4d %s%-24s Material %-14s Punkt %6.2f %6.2f %6.2f  Normale %5.2f %5.2f %5.2f  Abstand %.1f m'
                  % (px, py, '  ' * stufe, ob.name, mat, p.x, p.y, p.z, n.x, n.y, n.z, (p - ort).length))
            if mat != 'Glas':
                break
            start = p + r * 0.001
    print('[strahl] FERTIG')
except Exception:
    print('[strahl] FEHLER')
    traceback.print_exc()
