# Kamera und Objektlagen aus der Blender-Vorlage ausschreiben.
#
# Zweck: Die freigegebene Komposition (Fassung 6b) steckt in dieser
# Kamera. Statt in Unreal eine neue zu suchen, wird diese uebertragen.
#
# Die Achsenumrechnung wird NICHT geraten: zusaetzlich zu Kamera und
# Objektiv gehen die Mittelpunkte aller Netz-Huellkoerper mit raus.
# Unreal kann damit die Zuordnung selbst ausrechnen und nachpruefen.
import bpy, json
from mathutils import Vector

ZIEL = r'C:\Users\manue\Desktop\Vecom Design\_to_delete\kamera.json'

def welt_mitte(o):
    ecken = [o.matrix_world @ Vector(c) for c in o.bound_box]
    mi = Vector((min(e.x for e in ecken), min(e.y for e in ecken), min(e.z for e in ecken)))
    ma = Vector((max(e.x for e in ecken), max(e.y for e in ecken), max(e.z for e in ecken)))
    return [(mi.x + ma.x) / 2, (mi.y + ma.y) / 2, (mi.z + ma.z) / 2]

aus = {'objekte': {}}
for o in bpy.data.objects:
    if o.type == 'MESH':
        aus['objekte'][o.name] = welt_mitte(o)

kam = bpy.context.scene.camera or next((o for o in bpy.data.objects if o.type == 'CAMERA'), None)
if kam:
    m = kam.matrix_world
    aus['kamera'] = {
        'name': kam.name,
        'lage': [m.translation.x, m.translation.y, m.translation.z],
        # Blickrichtung: in Blender schaut die Kamera entlang -Z der
        # eigenen Achsen. Der Vektor laesst sich in Unreal direkt in
        # einen Rotator umrechnen - einfacher als Euler-Gefummel.
        'blick': list((m.to_3x3() @ Vector((0.0, 0.0, -1.0))).normalized()),
        'oben':  list((m.to_3x3() @ Vector((0.0, 1.0, 0.0))).normalized()),
        'brennweite': kam.data.lens,
        'sensor_breite': kam.data.sensor_width,
        'blende': getattr(kam.data.dof, 'aperture_fstop', None),
        'fokus': getattr(kam.data.dof, 'focus_distance', None),
    }

with open(ZIEL, 'w', encoding='utf-8') as f:
    json.dump(aus, f, indent=1)
print('Objekte: %d' % len(aus['objekte']))
if kam:
    k = aus['kamera']
    print('Kamera %s  Lage %.2f %.2f %.2f' % (k['name'], *k['lage']))
    print('  Blick %.3f %.3f %.3f' % tuple(k['blick']))
    print('  Brennweite %.1f mm  Sensor %.1f mm  Blende %s'
          % (k['brennweite'], k['sensor_breite'], k['blende']))
else:
    print('KEINE Kamera in der Datei')
