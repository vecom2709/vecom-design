# -*- coding: utf-8 -*-
"""Headless: EINZELNE Teile fuer Unreal neu exportieren -- trianguliert.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript lauf_teil_export.py -Log teil-export.log -Werte Putz_Weiss,Sichtbeton

WARUM (21.09.2026)
Im Unreal-Bild liegt ueber dem linken Obergeschossfenster ein schraeger
Keil, der in Blender nicht existiert; die rechte Laibung laeuft schief,
an der Attika-Ecke sitzt eine Beule. Die Nanite-Ersatznetze sind es
nicht -- mit vollem Ersatznetz (v113) blieb das Bild gleich. Verdacht:
N-Ecke. Die Fensteroeffnungen entstehen ueber Boolesche Modifikatoren,
und die hinterlassen grosse, oft nicht konvexe Vielecke. FBX gibt sie
als Vielecke weiter, Unreal trianguliert sie beim Einlesen selbst --
und ein Faecher ueber ein nicht konvexes Vieleck spannt Dreiecke
AUSSERHALB der Flaeche auf. Cycles trianguliert in Blender, richtig.

Deshalb hier: dieselbe Szene wie im Export, aber use_triangles=True --
Blender trianguliert, Unreal bekommt nur noch Dreiecke. Vorher zaehlt
das Skript je Teil die Vielecke mit mehr als vier Ecken und die davon
nicht konvexen, damit der Verdacht eine Zahl hat.
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


def vielecke(objekte):
    """(Vielecke > 4 Ecken, davon nicht konvex) nach Anwenden der Modifikatoren."""
    import bpy
    from mathutils import Vector
    dg = bpy.context.evaluated_depsgraph_get()
    gross = konkav = 0
    for o in objekte:
        e = o.evaluated_get(dg)
        me = e.to_mesh()
        for p in me.polygons:
            if len(p.vertices) <= 4:
                continue
            gross += 1
            n = p.normal
            pts = [me.vertices[i].co for i in p.vertices]
            vorz = 0
            for k in range(len(pts)):
                a, b, c = pts[k - 1], pts[k], pts[(k + 1) % len(pts)]
                s = (b - a).cross(c - b).dot(n)
                if abs(s) < 1e-9:
                    continue
                v = 1 if s > 0 else -1
                if vorz == 0:
                    vorz = v
                elif v != vorz:
                    konkav += 1
                    break
        e.to_mesh_clear()
    return gross, konkav


try:
    import bpy
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    teile = []
    for a in argv:
        teile += [t for t in a.split(',') if t]
    if not teile:
        raise RuntimeError('Keine Teile angegeben (-Werte Putz_Weiss,Sichtbeton)')

    v = lade('villa.py')
    v['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()
    lade('villa_garten.py')['aufwerten'](mit_gras=False)
    lade('villa_aussen.py')['moeblieren']()
    ue = lade('villa_unreal.py')
    g = ue['gruppen']()
    for t in teile:
        objekte = g.get(t)
        if not objekte:
            print('[teil] %s: keine Objekte' % t)
            continue
        gross, konkav = vielecke(objekte)
        print('[teil] %s: %d Objekte, Vielecke >4 Ecken: %d, davon nicht konvex: %d'
              % (t, len(objekte), gross, konkav))
        pfad = os.path.join(ue['AUS'], 'SM_%s.fbx' % t)
        ue['_nur'](objekte)
        bpy.ops.export_scene.fbx(
            filepath=pfad, use_selection=True, global_scale=ue['MASSSTAB'],
            apply_scale_options='FBX_SCALE_NONE', apply_unit_scale=False,
            use_mesh_modifiers=True, mesh_smooth_type='FACE',
            colors_type='LINEAR', use_tspace=True, add_leaf_bones=False,
            bake_anim=False, object_types={'MESH'}, path_mode='STRIP',
            use_triangles=True)
        print('[teil] %s -> %s  %d KB (trianguliert)'
              % (t, pfad, os.path.getsize(pfad) // 1024))
    print('[teil] FERTIG')
except Exception:
    print('[teil] FEHLER')
    traceback.print_exc()
