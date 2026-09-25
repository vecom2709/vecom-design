import bpy, sys
sys.argv = sys.argv[:sys.argv.index('--')] + ['--', 'keins'] if '--' in sys.argv else sys.argv + ['--', 'keins']
# Nur die Umfaerbung ausfuehren, nicht rendern: Funktionen aus dem Hauptskript holen.
src = open(r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\scripts\saal_goldsilber.py', encoding='utf-8').read()
src = src.split('marke(); lichter()')[0]
exec(compile(src, 'saal_goldsilber', 'exec'))
marke(); lichter(); metall_und_boden()

def blau(v):
    try:
        r, g, b = v[0], v[1], v[2]
    except (TypeError, IndexError):
        return False
    return b > 0.2 and b > r * 1.6

def baeume():
    for m in bpy.data.materials:
        if m.node_tree: yield 'MAT ' + m.name, m.node_tree
    for g in bpy.data.node_groups:
        yield 'GRP ' + g.name, g
    w = bpy.context.scene.world
    if w and w.node_tree: yield 'WELT', w.node_tree

for wo, nt in baeume():
    for n in nt.nodes:
        for i in n.inputs:
            if not i.is_linked and hasattr(i, 'default_value') and blau(getattr(i, 'default_value', None)):
                print('BLAU', wo, n.type, n.name, i.name, tuple(round(x, 3) for x in i.default_value))
        if n.type == 'VALTORGB':
            for e in n.color_ramp.elements:
                if blau(e.color): print('BLAU', wo, 'RAMPE', n.name, tuple(round(x, 3) for x in e.color))
for o in bpy.data.objects:
    if blau(o.color): print('BLAU OBJFARBE', o.name, tuple(o.color))
    if o.type == 'LIGHT' and blau(o.data.color): print('BLAU LICHT', o.name)
    for s in o.material_slots:
        if s.link == 'OBJECT': print('OBJ-SLOT', o.name, s.material.name if s.material else None)
for o in ('Sockelfuge_0', 'Fuge_1', 'Podest', 'Markenkante'):
    ob = bpy.data.objects[o]
    print('SLOTS', o, [(s.link, s.material.name if s.material else None) for s in ob.material_slots])
