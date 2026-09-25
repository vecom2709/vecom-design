# Bodenkontakt sauber herstellen.
#
# FEHLER im vorigen Lauf: Ich habe mit o.dimensions gerechnet. Das ist
# die LOKALE Huellbox mal Skalierung, nicht die weltachsenparallele.
# Bei einem gedrehten Objekt wie dem V kommt dabei Unsinn heraus
# (0,71 statt 4,10 Hoehe). Richtig ist die Welt-Huellbox aus
# matrix_world @ bound_box.
import bpy, os
from mathutils import Vector

ORD = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL = os.path.join(ORD, 'vecom-showroom_Fotoreal6.blend')
BILD = os.path.join(ORD, 'render', 'fotoreal6-vorschau.png')
LOG  = os.path.join(ORD, 'render', 'kontakt.txt')
z = []
def p(t):
    z.append(str(t)); print(t)

O = bpy.data.objects

def welt_box(o):
    ecken = [o.matrix_world @ Vector(c) for c in o.bound_box]
    return (Vector((min(e.x for e in ecken), min(e.y for e in ecken), min(e.z for e in ecken))),
            Vector((max(e.x for e in ecken), max(e.y for e in ecken), max(e.z for e in ecken))))

mu, mo = welt_box(O['Marke_V'])
pu, po = welt_box(O['Podest'])
p('Marke_V   x %.2f..%.2f   z %.2f..%.2f   (Breite %.2f, Hoehe %.2f)' %
  (mu.x, mo.x, mu.z, mo.z, mo.x - mu.x, mo.z - mu.z))
p('Podest    x %.2f..%.2f   z %.2f..%.2f' % (pu.x, po.x, pu.z, po.z))
p('Luft dazwischen: %.2f m' % (mu.z - po.z))

# Ziel: die Marke steckt 0,40 m im Sockel.
versatz = (po.z - 0.40) - mu.z
for n in ['Marke_V', 'Markenkante']:
    if n in O:
        O[n].location.z += versatz
bpy.context.view_layer.update()
p('Marke um %+.2f m verschoben' % versatz)

# Der Sockel muss die Marke auch in der Breite tragen - sonst steht
# ein 5,09 m breites V auf einem 3,50 m schmalen Podest.
mu2, mo2 = welt_box(O['Marke_V'])
pu2, po2 = welt_box(O['Podest'])
breite_marke = mo2.x - mu2.x
breite_podest = po2.x - pu2.x
p('Marke %.2f breit, Podest %.2f breit' % (breite_marke, breite_podest))
if breite_podest < breite_marke * 1.15:
    pod = O['Podest']
    f = (breite_marke * 1.25) / breite_podest
    pod.scale.x *= f
    pod.scale.y *= f
    bpy.context.view_layer.update()
    pu2, po2 = welt_box(pod)
    p('Podest verbreitert um Faktor %.2f -> %.2f m' % (f, po2.x - pu2.x))

# Podest unter die Marke zentrieren (sie sitzt jetzt weiter rechts)
mitte_marke_x = (mu2.x + mo2.x) / 2.0
mitte_podest_x = (pu2.x + po2.x) / 2.0
O['Podest'].location.x += (mitte_marke_x - mitte_podest_x)
for n in ['Sockelfuge_0','Sockelfuge_1','Sockelfuge_2','Sockelfuge_3']:
    if n in O:
        O[n].location.x += (mitte_marke_x - mitte_podest_x)
bpy.context.view_layer.update()

mu3, mo3 = welt_box(O['Marke_V'])
pu3, po3 = welt_box(O['Podest'])
p('')
p('-- Endstand')
p('   Marke  x %.2f..%.2f  z %.2f..%.2f' % (mu3.x, mo3.x, mu3.z, mo3.z))
p('   Podest x %.2f..%.2f  z %.2f..%.2f' % (pu3.x, po3.x, pu3.z, po3.z))
p('   Ueberlappung: %.2f m (positiv = steckt im Sockel)' % (po3.z - mu3.z))

bpy.ops.wm.save_as_mainfile(filepath=ZIEL)
p('Gespeichert: ' + ZIEL)

sz = bpy.context.scene
try:
    pr = bpy.context.preferences.addons['cycles'].preferences
    pr.compute_device_type = 'OPTIX'; pr.get_devices()
    for d in pr.devices: d.use = (d.type == 'OPTIX')
    sz.cycles.device = 'GPU'
except Exception as e:
    p('GPU: %s' % e)
sz.render.resolution_x = 960; sz.render.resolution_y = 540
sz.cycles.samples = 64
sz.render.filepath = BILD
bpy.ops.render.render(write_still=True)
p('Kontrollbild: ' + BILD)
open(LOG, 'w', encoding='utf-8').write('\n'.join(z))
