"""Bestand fuer die Villa-Nachbesserung: Sofas und Gartengrenze finden.
Baut die Szene wie lauf_ruhebilder.py, rendert nichts, schreibt JSON."""
import bpy, os, sys, json
from mathutils import Vector
HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.append(HIER)
ns = {'__name__': 'lauf', '__file__': os.path.join(HIER, 'lauf_ruhebilder.py')}
q = open(os.path.join(HIER, 'lauf_ruhebilder.py'), encoding='utf-8').read()
q = q[:q.rindex('\ntry:')]  # main() nicht ausfuehren, nur die Helfer laden
exec(compile(q, 'lauf_ruhebilder.py', 'exec'), ns)
ns['vorlage_bauen'](400, 225, 8)
fr = ns['lade']('villa_fotoreal.py'); fr['anwenden'](('land',))
def huelle(o):
    ps = [o.matrix_world @ Vector(c) for c in o.bound_box]
    return [round(min(p[i] for p in ps), 3) for i in range(3)] + [round(max(p[i] for p in ps), 3) for i in range(3)]
erg = {'moebel': [], 'garten': [], 'partikel': [], 'mauern': []}
for o in bpy.data.objects:
    n = o.name.lower()
    mats = [s.material.name for s in o.material_slots if s.material] if hasattr(o, 'material_slots') else []
    if any(w in n for w in ('sofa', 'couch', 'kissen', 'polster', 'sessel', 'lounge', 'liege', 'sitz')) or any('polster' in m.lower() or 'stoff' in m.lower() or 'kissen' in m.lower() for m in mats):
        erg['moebel'].append({'name': o.name, 'typ': o.type, 'huelle': huelle(o) if o.type == 'MESH' else None, 'mats': mats, 'verts': len(o.data.vertices) if o.type == 'MESH' else 0, 'mods': [m.type for m in o.modifiers]})
    if any(w in n for w in ('rasen', 'gras', 'gelaende', 'garten', 'wiese')):
        erg['garten'].append({'name': o.name, 'typ': o.type, 'huelle': huelle(o) if o.type == 'MESH' else None, 'mats': mats, 'psys': [p.settings.name for p in o.particle_systems] if o.type == 'MESH' else []})
    if 'mauer' in n or 'wand_aussen' in n or 'zaun' in n:
        erg['mauern'].append({'name': o.name, 'huelle': huelle(o) if o.type == 'MESH' else None, 'mats': mats})
    if o.type == 'MESH' and len(o.particle_systems):
        erg['partikel'].append({'name': o.name, 'huelle': huelle(o), 'psys': [(p.settings.name, p.settings.count, p.settings.type) for p in o.particle_systems]})
json.dump(erg, open(os.path.join(os.path.dirname(HIER), 'render', 'villa-bestand2.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('BESTAND2 FERTIG')
