# Materialwerte aus der Blender-Vorlage ausschreiben.
#
# Der Umweg ueber glTF verliert Werte: dort steht nur, was vom
# glTF-Standardwert abweicht, und Unreals Importeur setzt fehlende
# Felder auf glTF-Vorgaben (metallic=1, roughness=1) - was aus einer
# matten Wand ein raues Metall macht. Die Vorlage ist die Wahrheit.
import bpy, json, os

ZIEL = r'C:\Users\manue\Desktop\Vecom Design\_to_delete\materialien.json'

def wert(knoten, feld, vorgabe=None):
    e = knoten.inputs.get(feld)
    if e is None:
        return vorgabe
    try:
        v = e.default_value
    except Exception:
        return vorgabe
    try:
        return [float(x) for x in v]
    except TypeError:
        return float(v)

aus = {}
for m in bpy.data.materials:
    if not m.use_nodes:
        continue
    bsdf = None
    for k in m.node_tree.nodes:
        if k.type == 'BSDF_PRINCIPLED':
            bsdf = k
            break
    if bsdf is None:
        # Die Leuchtflaechen haengen an einem reinen Emission-Knoten.
        # Ohne diesen Zweig fehlten zwoelf von einundzwanzig.
        em = None
        for k in m.node_tree.nodes:
            if k.type == 'EMISSION':
                em = k
                break
        if em is None:
            continue
        aus[m.name] = {
            'grundfarbe': [0.0, 0.0, 0.0, 1.0],
            'metallisch': 0.0, 'rauheit': 0.5, 'spiegelung': 0.5,
            'anisotropie': 0.0, 'klarlack': 0.0, 'klarlackrauheit': 0.03,
            'leuchtfarbe': wert(em, 'Color', [1.0, 1.0, 1.0, 1.0]),
            'leuchtstaerke': wert(em, 'Strength', 1.0),
            'art': 'emission',
        }
        continue
    aus[m.name] = {
        'grundfarbe':      wert(bsdf, 'Base Color', [0.18, 0.18, 0.18, 1.0]),
        'metallisch':      wert(bsdf, 'Metallic', 0.0),
        'rauheit':         wert(bsdf, 'Roughness', 0.5),
        'spiegelung':      wert(bsdf, 'Specular IOR Level', 0.5),
        'anisotropie':     wert(bsdf, 'Anisotropic', 0.0),
        'klarlack':        wert(bsdf, 'Coat Weight', 0.0),
        'klarlackrauheit': wert(bsdf, 'Coat Roughness', 0.03),
        'leuchtfarbe':     wert(bsdf, 'Emission Color', [0.0, 0.0, 0.0, 1.0]),
        'leuchtstaerke':   wert(bsdf, 'Emission Strength', 0.0),
    }

with open(ZIEL, 'w', encoding='utf-8') as f:
    json.dump(aus, f, indent=1, ensure_ascii=False)
print('Materialien ausgeschrieben: %d -> %s' % (len(aus), ZIEL))
for n in sorted(aus):
    d = aus[n]
    print('  %-22s metall %.2f  rauh %.2f  aniso %.2f  klar %.2f  leucht %.1f'
          % (n, d['metallisch'], d['rauheit'], d['anisotropie'],
             d['klarlack'], d['leuchtstaerke']))
