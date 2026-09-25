# -*- coding: utf-8 -*-
"""Ein Himmel, der seine eigene Sonne mitbringt -- und die Belichtung dazu.

DIE MESSUNG, DIE DAZU GEFUEHRT HAT (17.09.2026)
Drei Bilder derselben Einstellung, einmal nur mit Himmel, einmal nur mit
der Sonnenlampe, einmal mit beidem:

    Rasen nur Himmel   0,132
    Rasen nur Sonne    0,045   (Sonnenlampe 4,9 W/m2)
    Fassade nur Sonne  0,000

Der Himmel gab dem Boden dreimal so viel Licht wie die Sonne. In der
Wirklichkeit ist es umgekehrt: Direktes Sonnenlicht liefert auf eine
waagerechte Flaeche das Fuenf- bis Achtfache des Himmelslichts. Genau
deshalb hatte das Bild keine Schatten -- nicht weil die Sonne falsch stand,
sondern weil sie praktisch nicht vorhanden war.

Zwei Zahlen von Hand aufeinander abzustimmen, die physikalisch
zusammengehoeren, ist der falsche Weg. Der Himmel von Blender rechnet
beides aus einem Modell: Sonnenscheibe und Streulicht stehen dann im
richtigen Verhaeltnis, weil sie aus derselben Atmosphaere kommen. Dieser
Lauf prueft, ob das traegt, und sucht die Belichtung dazu.
"""
import math
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


def himmel_physikalisch(hoehe, azimut, staerke=1.0):
    sz = bpy.context.scene
    w = sz.world
    nt = w.node_tree
    sky = next((n for n in nt.nodes if n.type == 'TEX_SKY'), None)
    bg = next((n for n in nt.nodes if n.type == 'BACKGROUND'), None)
    sky.sky_type = 'MULTIPLE_SCATTERING'
    sky.sun_elevation = math.radians(hoehe)
    # Blender zaehlt die Himmelsdrehung ab -Y. Die Sonnenlampe wird um Z
    # gedreht und zeigt bei 0 nach unten. Der Versatz von 90 Grad haelt
    # beide auf demselben Stand -- sonst steht die Scheibe im Bild woanders
    # als der Schatten auf dem Boden.
    sky.sun_rotation = math.radians(azimut - 90.0)
    sky.sun_disc = True
    sky.sun_intensity = 1.0
    sky.sun_size = math.radians(0.545)
    # Welche Regler es gibt, haengt am Himmelsmodell -- MULTIPLE_SCATTERING
    # in Blender 5.2 kennt kein dust_density. Deshalb setzen statt annehmen.
    for feld, wert in (('altitude', 120.0), ('air_density', 1.0),
                       ('dust_density', 2.2), ('ozone_density', 1.4)):
        if hasattr(sky, feld):
            setattr(sky, feld, wert)
    bg.inputs[1].default_value = staerke
    return sky


try:
    lauf = lade('villa_lauf.py')
    d, kams, szene = lauf['aufbauen'](moebel=True, fasen=True, echt=True)
    garten = lade('villa_garten.py')
    garten['aufwerten'](mit_gras=False)
    cyc = lade('villa_cycles.py')
    cyc['einstellen'](proben=140, breite=960, hoehe=540, rauschgrenze=0.015)
    sz = bpy.context.scene
    sz.camera = kams['garten']

    # Die eigene Sonnenlampe ganz aus: Sie wuerde das Modell verdoppeln.
    so = bpy.data.objects.get('Sonne')
    if so:
        so.data.energy = 0.0

    roh = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    teile = []
    for a in roh:
        teile.extend([x for x in a.split(',') if x])
    wahl = {}
    for t in teile:
        if '=' in t:
            k, v = t.split('=', 1)
            wahl[k] = v
    azimute = [float(x) for x in wahl.get('az', '249;325').split(';')]
    stufen = [float(x) for x in wahl.get('bel', '-3;-4;-5').split(';')]
    sonnenhoehe = float(wahl.get('sonnenhoehe', 27.0))

    for az in azimute:
        himmel_physikalisch(sonnenhoehe, az, 1.0)
        for bel in stufen:
            sz.view_settings.exposure = bel
            sz.render.filepath = os.path.join(
                R, 'h-az%03d-b%s.png' % (az, str(abs(bel)).replace('.', '')))
            bpy.ops.render.render(write_still=True)
            print('[himmel] az=%d bel=%.1f geschrieben' % (az, bel))
    print('[himmel] FERTIG')
except Exception:
    print('[himmel] FEHLER')
    traceback.print_exc()
