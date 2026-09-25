"""Bestandsaufnahme der Branchen-Vorlagen in Blender.

Misst, bevor irgendetwas gebaut wird: Masse in Metern, Ausrichtung,
Materialien, ob die Varianten beim Import ankommen. Schreibt alles nach
branchen/bestand.json -- nichts wird gespeichert oder veraendert.
"""
import bpy, json, os, sys
from mathutils import Vector

P = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
erg = {}

def leeren():
    bpy.ops.wm.read_factory_settings(use_empty=True)

def huelle(objs):
    lo = Vector((1e9, 1e9, 1e9)); hi = Vector((-1e9, -1e9, -1e9))
    for o in objs:
        if o.type != 'MESH':
            continue
        for c in o.bound_box:
            w = o.matrix_world @ Vector(c)
            lo = Vector(map(min, lo, w)); hi = Vector(map(max, hi, w))
    return [round(v, 4) for v in lo], [round(v, 4) for v in hi]

for name in ('auto-ohne-logos', 'schuh'):
    leeren()
    bpy.ops.import_scene.gltf(filepath=os.path.join(P, 'quelle', name + '.glb'))
    objs = list(bpy.context.scene.objects)
    lo, hi = huelle(objs)
    mats = sorted({s.material.name for o in objs if o.type == 'MESH' for s in o.material_slots if s.material})
    tris = 0
    for o in objs:
        if o.type == 'MESH':
            o.data.calc_loop_triangles(); tris += len(o.data.loop_triangles)
    varianten = []
    sc = bpy.context.scene
    if hasattr(sc, 'gltf2_KHR_materials_variants_variants'):
        varianten = [v.name for v in sc.gltf2_KHR_materials_variants_variants]
    erg[name] = {
        'objekte': len(objs), 'dreiecke': tris, 'min': lo, 'max': hi,
        'masse': [round(hi[i] - lo[i], 4) for i in range(3)],
        'materialien': mats, 'varianten': varianten,
        'namen': [o.name for o in objs][:140],
    }

# Exporter-Optionen, damit der spaetere Export nicht raten muss
import inspect
try:
    ops = bpy.ops.export_scene.gltf.get_rna_type().properties
    erg['export_optionen'] = [p.identifier for p in ops if 'variant' in p.identifier.lower() or 'draco' in p.identifier.lower() or 'image' in p.identifier.lower()]
except Exception as e:
    erg['export_optionen'] = str(e)
erg['blender'] = bpy.app.version_string
with open(os.path.join(P, 'bestand.json'), 'w', encoding='utf-8') as f:
    json.dump(erg, f, ensure_ascii=False, indent=1)
print('BESTAND FERTIG')
