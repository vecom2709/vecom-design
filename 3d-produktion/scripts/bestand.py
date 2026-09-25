# Bestandsaufnahme vor dem Hallenbau.
# Regel 7: erst messen, dann bauen. Ich muss wissen, wie gross der
# Stand wirklich ist, bevor ich eine Halle drumherum setze.
import bpy, json
from mathutils import Vector

ZIEL = r'C:\Users\manue\Desktop\Vecom Design\_to_delete\bestand.json'

def box(o):
    e = [o.matrix_world @ Vector(c) for c in o.bound_box]
    mi = [min(p[i] for p in e) for i in range(3)]
    ma = [max(p[i] for p in e) for i in range(3)]
    return mi, ma

netze = [o for o in bpy.data.objects if o.type == 'MESH']
aus = {}
gmi = [1e9] * 3
gma = [-1e9] * 3
for o in netze:
    mi, ma = box(o)
    aus[o.name] = {'min': mi, 'max': ma,
                   'material': o.material_slots[0].name if o.material_slots else None}
    for i in range(3):
        gmi[i] = min(gmi[i], mi[i])
        gma[i] = max(gma[i], ma[i])

with open(ZIEL, 'w', encoding='utf-8') as f:
    json.dump({'objekte': aus, 'gesamt': {'min': gmi, 'max': gma}}, f, indent=1)

print('Objekte: %d' % len(netze))
print('Gesamt  X %.2f..%.2f  Y %.2f..%.2f  Z %.2f..%.2f m'
      % (gmi[0], gma[0], gmi[1], gma[1], gmi[2], gma[2]))
print('--- Die grossen Teile ---')
gross = sorted(aus.items(),
               key=lambda kv: -((kv[1]['max'][0]-kv[1]['min'][0]) *
                                (kv[1]['max'][1]-kv[1]['min'][1])))
for n, d in gross[:14]:
    print('  %-22s %6.1f x %5.1f x %5.1f m   z %5.2f..%5.2f   %s'
          % (n, d['max'][0]-d['min'][0], d['max'][1]-d['min'][1],
             d['max'][2]-d['min'][2], d['min'][2], d['max'][2], d['material']))
print('--- Namensgruppen ---')
gruppen = {}
for n in aus:
    schluessel = n.split('_')[0].rstrip('0123456789')
    gruppen[schluessel] = gruppen.get(schluessel, 0) + 1
for k in sorted(gruppen):
    print('  %-18s %d' % (k, gruppen[k]))
