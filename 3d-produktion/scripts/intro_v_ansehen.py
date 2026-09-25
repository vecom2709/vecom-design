import bpy
with bpy.data.libraries.load(r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\vecom-showroom_Farm1080c.blend', link=False) as (src, dst):
    dst.objects = ['Marke_V']
o = dst.objects[0]
bpy.context.scene.collection.objects.link(o)
me = o.data
print('SKALA', tuple(o.scale), 'ROT', tuple(o.rotation_euler), 'VERTS', len(me.vertices), 'POLYS', len(me.polygons))
xs = [v.co.x for v in me.vertices]; ys = [v.co.y for v in me.vertices]; zs = [v.co.z for v in me.vertices]
print('X', min(xs), max(xs), 'Y', min(ys), max(ys), 'Z', min(zs), max(zs))
import bmesh
bm = bmesh.new(); bm.from_mesh(me)
teile = []
bm.verts.ensure_lookup_table()
besucht = set()
for v in bm.verts:
    if v.index in besucht: continue
    stapel = [v]; teil = []
    while stapel:
        w = stapel.pop()
        if w.index in besucht: continue
        besucht.add(w.index); teil.append(w)
        for e in w.link_edges: stapel.append(e.other_vert(w))
    teile.append(teil)
for t in teile:
    print('TEIL', len(t), 'x', round(min(v.co.x for v in t), 3), round(max(v.co.x for v in t), 3), 'y', round(min(v.co.y for v in t), 3), round(max(v.co.y for v in t), 3))
tip = min(me.vertices, key=lambda v: v.co.y)
print('SPITZE', tuple(round(c, 3) for c in tip.co))
print('MODIFIER', [m.type for m in o.modifiers])
