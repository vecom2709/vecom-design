"""ph_ansehen.py -- Poly-Haven-Modelle prüfen, bevor sie in die Szene kommen:
Objekte, Maße (m), Materialien, Achsen; dazu ein Kontrollbild je Modell.
Aufruf: blender -b --factory-startup -P ph_ansehen.py -- name1,name2,..."""
import bpy, sys, os, math
from mathutils import Vector

Q = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\film-sichtbar\quelle\modelle'
ZIEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\film-sichtbar\render\ansehen'
os.makedirs(ZIEL, exist_ok=True)
namen = sys.argv[sys.argv.index('--') + 1].split(',')
for name in namen:
    bpy.ops.wm.read_factory_settings(use_empty=True)
    d = os.path.join(Q, name)
    datei = next(os.path.join(d, f) for f in os.listdir(d) if f.endswith('.blend'))
    with bpy.data.libraries.load(datei, link=False) as (a, b):
        b.objects = list(a.objects)
    sc = bpy.context.scene
    for o in b.objects:
        if o is not None:
            sc.collection.objects.link(o)
    bpy.context.view_layer.update()
    lo = Vector((1e9,) * 3); hi = Vector((-1e9,) * 3)
    print('MODELL', name, len(b.objects), 'Objekte')
    for o in b.objects:
        if o is None or o.type != 'MESH':
            continue
        pts = [o.matrix_world @ Vector(c) for c in o.bound_box]
        l = Vector((min(p.x for p in pts), min(p.y for p in pts), min(p.z for p in pts)))
        h = Vector((max(p.x for p in pts), max(p.y for p in pts), max(p.z for p in pts)))
        lo = Vector((min(lo.x, l.x), min(lo.y, l.y), min(lo.z, l.z))); hi = Vector((max(hi.x, h.x), max(hi.y, h.y), max(hi.z, h.z)))
        print('  OBJ %-40s dim %.3f %.3f %.3f  ort %.3f %.3f %.3f  rot %s  mat %s' % (
            o.name, *(h - l), *((h + l) / 2), tuple(round(math.degrees(r)) for r in o.rotation_euler),
            [m.name for m in o.data.materials if m]))
    print('  GESAMT', tuple(round(x, 3) for x in (hi - lo)))
    # Kontrollbild: Kamera schräg von vorn (-y), Studiolicht
    c = (lo + hi) / 2; gr = (hi - lo).length
    cd = bpy.data.cameras.new('k'); cam = bpy.data.objects.new('k', cd); sc.collection.objects.link(cam)
    cam.location = c + Vector((gr * 0.6, -gr * 1.3, gr * 0.5))
    cam.rotation_euler = (c - cam.location).to_track_quat('-Z', 'Y').to_euler(); sc.camera = cam
    for ort, e in (((gr, -gr, gr * 1.5), 300.0), ((-gr, -gr * 0.5, gr), 120.0)):
        ld = bpy.data.lights.new('l', 'AREA'); ld.energy = e * gr * gr; ld.size = gr
        lo_ = bpy.data.objects.new('l', ld); sc.collection.objects.link(lo_); lo_.location = c + Vector(ort)
        lo_.rotation_euler = (c - lo_.location).to_track_quat('-Z', 'Y').to_euler()
    w = bpy.data.worlds.new('w'); sc.world = w; w.use_nodes = True
    w.node_tree.nodes['Background'].inputs['Strength'].default_value = 0.3
    sc.render.engine = 'CYCLES'; sc.cycles.samples = 32; sc.cycles.device = 'GPU'
    try:
        prefs = bpy.context.preferences.addons['cycles'].preferences; prefs.compute_device_type = 'OPTIX'; prefs.get_devices()
        for dv in prefs.devices: dv.use = True
    except Exception:
        pass
    sc.render.resolution_x = 640; sc.render.resolution_y = 480
    sc.render.filepath = os.path.join(ZIEL, name + '.png')
    bpy.ops.render.render(write_still=True)
print('ANSEHEN FERTIG', flush=True)
