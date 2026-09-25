# Messen vor dem Anfassen: Wo steht was, wie gross ist es, wohin
# schaut die Kamera. Ohne diese Zahlen waeren die drei Korrekturen
# geraten - und geraten hat heute schon genug gekostet.
import bpy, os, math
from mathutils import Vector

AUS = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\render\messung.txt'
z = []
def p(t):
    z.append(str(t)); print(t)

sz = bpy.context.scene
kam = sz.camera

def welt_mitte(o):
    return sum((o.matrix_world @ Vector(c) for c in o.bound_box), Vector()) / 8.0

p('== Kamera')
p('   Position:  %.2f / %.2f / %.2f' % tuple(kam.location))
p('   Drehung:   %.1f / %.1f / %.1f Grad' % tuple(math.degrees(a) for a in kam.rotation_euler))
p('   Brennweite: %.1f mm auf %.1f mm Sensor' % (kam.data.lens, kam.data.sensor_width))
for c in kam.constraints:
    p('   Constraint: %s -> %s' % (c.type, getattr(c, 'target', None).name if getattr(c, 'target', None) else '-'))
if 'Blickpunkt' in bpy.data.objects:
    p('   Blickpunkt: %.2f / %.2f / %.2f' % tuple(bpy.data.objects['Blickpunkt'].location))

p('')
p('== Marke, Podest und was darunter liegt')
for n in bpy.data.objects.keys():
    if any(s in n.lower() for s in ['marke', 'podest', 'sockel', 'kragen', 'platte']):
        o = bpy.data.objects[n]
        m = welt_mitte(o)
        p('   %-24s Mitte %7.2f /%7.2f /%7.2f   Masse %6.2f x%6.2f x%6.2f' %
          (n, m.x, m.y, m.z, o.dimensions.x, o.dimensions.y, o.dimensions.z))
        p('%29s Unterkante z = %6.2f   Oberkante z = %6.2f' % ('', m.z - o.dimensions.z/2, m.z + o.dimensions.z/2))

p('')
p('== Alles an der Decke')
for n in sorted(bpy.data.objects.keys()):
    if any(s in n.lower() for s in ['decken', 'leiste', 'linie', 'softbox']):
        o = bpy.data.objects[n]
        m = welt_mitte(o)
        p('   %-24s Mitte %7.2f /%7.2f /%7.2f   Masse %6.2f x%6.2f x%6.2f' %
          (n, m.x, m.y, m.z, o.dimensions.x, o.dimensions.y, o.dimensions.z))

p('')
p('== Raumgrenzen (Boden, Decke, Waende)')
for n in sorted(bpy.data.objects.keys()):
    if any(s in n.lower() for s in ['boden', 'decke', 'wand']):
        o = bpy.data.objects[n]
        m = welt_mitte(o)
        p('   %-24s Mitte %7.2f /%7.2f /%7.2f   Masse %6.2f x%6.2f x%6.2f' %
          (n, m.x, m.y, m.z, o.dimensions.x, o.dimensions.y, o.dimensions.z))

p('')
p('== Alle Objektnamen')
p('   ' + ', '.join(sorted(bpy.data.objects.keys())))

os.makedirs(os.path.dirname(AUS), exist_ok=True)
open(AUS, 'w', encoding='utf-8').write('\n'.join(z))
