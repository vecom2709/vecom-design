# -*- coding: utf-8 -*-
"""Wie hell ist eine Fensterscheibe WIRKLICH? Der dritte Fall.

DER BEFUND, 20.09.2026

Das Glas in Unreal liegt 1,87 Blenden unter der Cycles-Vorlage. Der
naheliegende Schluss waere: Unreal ist zu dunkel. Ein Blick in
villa_material.glas() sagt etwas anderes:

    b.inputs['Alpha'].default_value = 0.14
    b.inputs['Transmission Weight'].default_value = 0.0
    use_raytrace_refraction = False
    Base Color (0.72, 0.80, 0.84), Rauheit 0.02

Das ist KEIN Glas, das ist eine helle Flaeche mit 86 Prozent
Durchsichtigkeit. Der Docstring sagt es selbst: "kein echtes
Transmission, sondern Alpha plus kraeftige Spiegelung ... im Browser
gibt es sie ohnehin nicht, also wird hier schon so gebaut, wie es dort
aussehen wird." Fuer EEVEE und fuer WebGL ist das die richtige
Entscheidung. Als Massstab fuer einen Pfadverfolger taugt sie nicht.

Unreal hat seit dem 20.09.2026 einen Master mit echtem Brechungsindex.
Physikalisch ist also womoeglich UNREAL richtig und die Vorlage der
Trick -- und dann waere "Unreal auf die Vorlage bringen" genau der
falsche Weg.

WAS HIER GERECHNET WIRD

Dieselbe Kameraeinstellung, dieselbe Szene, dasselbe Licht -- nur mit
physikalisch richtigem Glas: Transmission 1.0, Brechungsindex 1.5,
Rauheit 0.02, leicht gruenliche Tonung wie echtes Floatglas. Cycles ist
ein Pfadverfolger, Brechung kostet dort nichts Besonderes und ist
korrekt.

Damit gibt es drei Zahlen statt zwei:
    Vorlage (Alpha-Trick)  --  Physik (dieser Lauf)  --  Unreal
und erst der Vergleich sagt, wer nachgebessert werden muss.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript villa_exr_glas.py -Log villa-glas.log
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
PROBEN = 160     # Glas rauscht mehr als Putz, deshalb mehr als die 96

# Echtes Floatglas: die Scheibe laesst rund 0,88 durch und ist dabei
# leicht gruen (Eisenanteil). Zwei Scheiben Isolierglas kommen auf etwa
# 0,78 gesamt -- das ergibt sich hier von selbst, weil der Pfadverfolger
# jede Grenzflaeche einzeln rechnet.
GLASFARBE = (0.90, 0.95, 0.91, 1.0)


def lade(name):
    p = os.path.join(HIER, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def glas_echt():
    """Aus dem Alpha-Trick echtes Glas machen."""
    mat = bpy.data.materials.get('Glas')
    if mat is None:
        print('[glas] Material "Glas" nicht gefunden')
        return False
    b = None
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            b = n
            break
    if b is None:
        print('[glas] Kein Principled BSDF im Material "Glas"')
        return False
    b.inputs['Base Color'].default_value = GLASFARBE
    b.inputs['Roughness'].default_value = 0.02
    b.inputs['Metallic'].default_value = 0.0
    b.inputs['IOR'].default_value = 1.5
    b.inputs['Alpha'].default_value = 1.0
    if 'Transmission Weight' in b.inputs:
        b.inputs['Transmission Weight'].default_value = 1.0
    mat.blend_method = 'OPAQUE'
    if hasattr(mat, 'use_backface_culling'):
        mat.use_backface_culling = False
    if hasattr(mat, 'use_raytrace_refraction'):
        mat.use_raytrace_refraction = True
    print('[glas] Glas auf echte Transmission gestellt')
    return True


def haupt():
    t0 = time.time()
    lauf = lade('villa_lauf.py')
    d, kams, szene = lauf['aufbauen']()
    print('[glas] Aufbau fertig: %d Objekte, %.0f s'
          % (d['objekte'], time.time() - t0))

    if not glas_echt():
        return

    cyc = lade('villa_cycles.py')
    info = cyc['einstellen'](proben=PROBEN, breite=BREITE, hoehe=HOEHE,
                             rauschgrenze=0.015)
    print('[glas] Geraet: %s' % info['geraet'])

    sz = bpy.context.scene
    # Brechung braucht Durchgaenge, sonst ist die Scheibe schwarz.
    try:
        sz.cycles.transmission_bounces = 12
        sz.cycles.max_bounces = max(sz.cycles.max_bounces, 16)
        sz.cycles.transparent_max_bounces = 16
    except Exception as f:
        print('[glas] Durchgaenge nicht gesetzt: %s' % f)

    sz.render.image_settings.file_format = 'OPEN_EXR'
    sz.render.image_settings.color_depth = '32'
    sz.render.image_settings.color_mode = 'RGB'
    sz.view_settings.view_transform = 'Raw'
    sz.view_settings.look = 'None'
    sz.view_settings.exposure = 0.0

    for name in KAMERAS:
        if name not in kams:
            print('[glas] Kamera fehlt: %s' % name)
            continue
        sz.camera = kams[name]
        ziel = os.path.join(AUS, 'cycles-glasecht-%s.exr' % name)
        sz.render.filepath = ziel
        t = time.time()
        bpy.ops.render.render(write_still=True)
        print('[glas] %s -> %s  (%.0f s)' % (name, ziel, time.time() - t))

    print('[glas] FERTIG')


haupt()
