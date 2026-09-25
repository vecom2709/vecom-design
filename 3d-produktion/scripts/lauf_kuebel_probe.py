# -*- coding: utf-8 -*-
"""Headless: den neuen Kuebelbaum allein ansehen, bevor er in die Szene geht.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript lauf_kuebel_probe.py -Log kuebel-probe.log

Zwei Bilder: 'nah' aus 3 m (Form, Topfrand, Stamm, Blaetter) und 'fern'
aus rund 30 m mit 35 mm -- das ist der Abstand, aus dem der Blick
'garten' den Kuebel sieht. Dazu Dreieckszahlen je Teil. Kein Teil der
Produktion; nur die Probe, ob die Form traegt.
"""
import math
import os
import random
import sys
import traceback

S = os.path.dirname(os.path.abspath(__file__))
if S not in sys.path:
    sys.path.insert(0, S)
AUS = os.path.join(os.path.dirname(S), 'ausgabe', 'probe-kuebel')


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def gpu(sc):
    try:
        pr = bpy.context.preferences.addons['cycles'].preferences
        for t in ('OPTIX', 'CUDA', 'HIP', 'ONEAPI'):
            try:
                pr.compute_device_type = t
                pr.get_devices()
                if any(d.type == t for d in pr.devices):
                    break
            except Exception:
                continue
        for d in pr.devices:
            d.use = True
        sc.cycles.device = 'GPU'
        print('[probe] Rechner: %s' % pr.compute_device_type)
    except Exception as f:
        print('[probe] GPU nicht gesetzt: %s' % f)


def kamera(sc, name, ort, ziel, brennweite):
    from mathutils import Vector
    cd = bpy.data.cameras.new(name)
    cd.lens = brennweite
    cd.sensor_width = 36.0
    ob = bpy.data.objects.new(name, cd)
    sc.collection.objects.link(ob)
    ob.location = ort
    richtung = Vector(ziel) - Vector(ort)
    ob.rotation_euler = richtung.to_track_quat('-Z', 'Y').to_euler()
    return ob


try:
    import bpy
    os.makedirs(AUS, exist_ok=True)
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    A = lade('villa_aussen.py')
    samml = bpy.data.collections.new('08_Aussenanlage')
    sc.collection.children.link(samml)
    m = A['materialien']()
    A['quader']('boden', -4.0, -4.0, -0.10, 8.0, 8.0, 0.10, samml,
                A['_mat']('Travertin', (0.52, 0.48, 0.41), 0.62))
    A['quader']('wand', -4.0, 1.1, 0.0, 8.0, 0.30, 3.2, samml,
                A['_mat']('Putz_Weiss', (0.70, 0.69, 0.66), 0.85))
    try:
        lade('villa_echt.py')['blatt_durchleuchten'](m['olive'])
    except Exception as f:
        print('[probe] Durchlicht nicht gesetzt: %s' % f)
    rnd = random.Random(8123)
    teile = A['kuebel']('kuebel1', 0.0, 0.0, 0.0, samml, m, rnd, True)
    gesamt = 0
    for o in teile:
        o.data.calc_loop_triangles()
        n = len(o.data.loop_triangles)
        gesamt += n
        lo = [o.matrix_world @ v.co for v in o.data.vertices]
        zs = [p.z for p in lo]
        print('[probe] %-22s %7d Dreiecke  z %.2f .. %.2f  Material %s'
              % (o.name, n, min(zs), max(zs), o.data.materials[0].name))
    print('[probe] zusammen %d Dreiecke' % gesamt)

    # Licht: Sonne wie im Blick 'garten' (Nachmittag, 27 Grad hoch) und
    # ein blauer Himmel als Welt. Kein Nishita -- es geht um die Form.
    sd = bpy.data.lights.new('Sonne', 'SUN')
    sd.energy = 4.5
    sd.angle = math.radians(0.53)
    so = bpy.data.objects.new('Sonne', sd)
    sc.collection.objects.link(so)
    # Licht von der Kameraseite: bei 215 Grad stand die Sonne in der
    # ersten Probe hinter der Wand, und alles lag im Schatten.
    so.rotation_euler = (math.radians(63.0), 0.0, math.radians(25.0))
    w = bpy.data.worlds.new('Welt')
    w.use_nodes = True
    bg = w.node_tree.nodes.get('Background')
    bg.inputs['Color'].default_value = (0.36, 0.52, 0.85, 1.0)
    bg.inputs['Strength'].default_value = 0.9
    sc.world = w

    sc.render.engine = 'CYCLES'
    gpu(sc)
    sc.cycles.samples = 96
    sc.cycles.use_denoising = True
    try:
        sc.view_settings.view_transform = 'AgX'
    except Exception:
        pass

    for name, ort, ziel, bw, br, ho in (
            ('nah', (2.3, -2.3, 1.55), (0.0, 0.0, 1.30), 50.0, 1000, 1250),
            ('fern', (21.0, -21.5, 4.4), (0.0, 0.0, 1.2), 35.0, 1600, 900)):
        cam = kamera(sc, 'Kam_' + name, ort, ziel, bw)
        sc.camera = cam
        sc.render.resolution_x = br
        sc.render.resolution_y = ho
        sc.render.resolution_percentage = 100
        sc.render.filepath = os.path.join(AUS, 'kuebel-%s.png' % name)
        bpy.ops.render.render(write_still=True)
        print('[probe] %s -> %s' % (name, sc.render.filepath))
    print('[probe] FERTIG')
except Exception:
    print('[probe] FEHLER')
    traceback.print_exc()
