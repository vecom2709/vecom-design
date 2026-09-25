"""Vermessung fuer die Villa-Nachbesserung (23.09.2026).

Uwe sah in Garten- und Wohnraumbild eine harte, gerade Kante zwischen
gruenem Rasen und goldbraunem Land. Bevor dort eine Trockenmauer hinkommt,
wird gemessen, WO diese Kante in der Welt liegt: je Kamera ein Strahlenfaecher
von unten nach oben; wo der Abstand zum getroffenen Gelaende springt, liegt
eine Kuppe. Dazu Hoehenprofile rund um das Haus, die Grasverteilung und der
Aufbau der Polsterstoffe. Rendert nichts, schreibt JSON."""
import bpy, os, sys, json, math
from mathutils import Vector
HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.append(HIER)
ns = {'__name__': 'lauf', '__file__': os.path.join(HIER, 'lauf_ruhebilder.py')}
q = open(os.path.join(HIER, 'lauf_ruhebilder.py'), encoding='utf-8').read()
q = q[:q.rindex('\ntry:')]
exec(compile(q, 'lauf_ruhebilder.py', 'exec'), ns)
kams = ns['vorlage_bauen'](400, 225, 8)
fr = ns['lade']('villa_fotoreal.py'); fr['anwenden'](('land',))
sz = bpy.context.scene
dg = bpy.context.evaluated_depsgraph_get()
erg = {'kameras': {}, 'kuppen': {}, 'profile': {}, 'gras': [], 'gelaende_mods': [], 'stoffe': {}}
R = lambda v: [round(c, 3) for c in v]

for name, kam in kams.items():
    sz.camera = kam
    mw = kam.matrix_world
    rahmen = [mw @ v for v in kam.data.view_frame(scene=sz)]  # oben-rechts, unten-rechts, unten-links, oben-links
    ursprung = mw.translation.copy()
    erg['kameras'][name] = {'ort': R(ursprung), 'blick': R((mw.to_quaternion() @ Vector((0, 0, -1)))), 'brennweite': round(kam.data.lens, 1)}
    liste = []
    for i in range(17):
        u = i / 16
        unten = rahmen[2].lerp(rahmen[1], u); oben = rahmen[3].lerp(rahmen[0], u)
        spalte = []
        for j in range(161):
            v = j / 160
            p = unten.lerp(oben, v)
            d = (p - ursprung).normalized()
            ok, ort, nor, idx, obj, _ = sz.ray_cast(dg, ursprung, d)
            spalte.append((v, (ort - ursprung).length if ok else None, obj.name if ok else None, R(ort) if ok else None))
        # Kuppe: letzter Gelaendetreffer vor einem Sprung (Abstand x1,6 oder Himmel/Ferne)
        for k in range(len(spalte) - 1):
            a, b = spalte[k], spalte[k + 1]
            if a[2] == 'p08_gelaende' and a[1] and a[1] > 8 and (b[1] is None or b[2] != 'p08_gelaende' or b[1] > a[1] * 1.6):
                liste.append({'u': round(u, 3), 'v': round(a[0], 3), 'abstand': round(a[1], 1), 'ort': a[3], 'danach': b[2], 'abstand_danach': round(b[1], 1) if b[1] else None})
                break
    erg['kuppen'][name] = liste

gel = bpy.data.objects.get('p08_gelaende')
ge = gel.evaluated_get(dg)
inv = ge.matrix_world.inverted()
for w in range(0, 360, 15):
    prof = []
    for r in range(0, 132, 4):
        x = 9.0 + r * math.cos(math.radians(w)); y = 5.5 + r * math.sin(math.radians(w))
        o = inv @ Vector((x, y, 200.0)); d = (inv.to_3x3() @ Vector((0, 0, -1))).normalized()
        ok, ort, nor, idx = ge.ray_cast(o, d)
        prof.append(round((ge.matrix_world @ ort).z, 2) if ok else None)
    erg['profile'][str(w)] = prof
erg['gelaende_mods'] = [(m.name, m.type) for m in gel.modifiers]
for p in gel.particle_systems:
    s = p.settings
    erg['gras'].append({'name': s.name, 'anzahl': s.count, 'dichtegruppe': p.vertex_group_density, 'laengengruppe': p.vertex_group_length,
                        'texturen': [ts.texture.name for ts in s.texture_slots if ts and ts.texture] if hasattr(s, 'texture_slots') else [],
                        'haarlaenge': round(s.hair_length, 3), 'kinder': s.child_type, 'kinder_n': s.rendered_child_count})
vg = {g.index: g.name for g in gel.vertex_groups}
erg['gruppen'] = list(vg.values())
if erg['gras'] and erg['gras'][0]['dichtegruppe']:
    gi = gel.vertex_groups[erg['gras'][0]['dichtegruppe']].index
    pts = []
    for v in gel.data.vertices:
        for g in v.groups:
            if g.group == gi and g.weight > 0.5:
                pts.append((v.co.x, v.co.y))
    if pts:
        mw = gel.matrix_world
        ws = [mw @ Vector((x, y, 0)) for x, y in pts]
        erg['grasflaeche'] = {'min': R((min(p.x for p in ws), min(p.y for p in ws))), 'max': R((max(p.x for p in ws), max(p.y for p in ws))),
                              'radius_max': round(max(math.hypot(p.x - 9, p.y - 5.5) for p in ws), 1)}
for mn in ('Polsterstoff', 'Akzent', 'Leinen', 'Rasen', 'Sichtbeton'):
    m = bpy.data.materials.get(mn)
    if not m or not m.use_nodes:
        continue
    kn = []
    for k in m.node_tree.nodes:
        e = {'typ': k.type, 'name': k.name}
        if k.type == 'BSDF_PRINCIPLED':
            for s in ('Base Color', 'Roughness', 'Sheen Weight', 'Sheen Roughness', 'Coat Weight'):
                i = k.inputs.get(s)
                if i is not None:
                    e[s] = 'verlinkt' if i.is_linked else (R(i.default_value) if hasattr(i.default_value, '__len__') else round(i.default_value, 3))
            e['Normal'] = 'verlinkt' if k.inputs['Normal'].is_linked else '-'
        kn.append(e)
    erg['stoffe'][mn] = kn
for on in ('p07_sofa_lang_kissen_l', 'p07_sofa_lang_lehne', 'p07_sofa_kurz_kissen'):
    o = bpy.data.objects.get(on)
    if o:
        erg.setdefault('kissen_netz', {})[on] = {'polys': len(o.data.polygons), 'glatt': o.data.polygons[0].use_smooth, 'mods': [(m.type, getattr(m, 'levels', None)) for m in o.modifiers], 'eltern': o.parent.name if o.parent else None, 'drehung': R(o.rotation_euler), 'mass': R(o.dimensions)}
json.dump(erg, open(os.path.join(os.path.dirname(HIER), 'render', 'villa-rand.json'), 'w', encoding='utf-8'), ensure_ascii=False)
print('RAND FERTIG')
