# Die Querfelder an der Decke laufen hinter der Markenspitze durch.
# Sie wegzunehmen wuerde das Bild seiner besten Eigenschaft berauben -
# das Raster traegt die ganze Tiefenwirkung.
#
# Loesung: eine LUECKE im Raster, genau ueber der Marke. Jedes Feld
# wird in eine linke und eine rechte Haelfte geteilt. Das sieht nicht
# nach Verlegenheit aus, sondern nach Absicht: ein Lichtraster, das
# dem Objekt Platz laesst.
import bpy, os
from mathutils import Vector

ORD = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL = os.path.join(ORD, 'vecom-showroom_Fotoreal6.blend')
BILD = os.path.join(ORD, 'render', 'fotoreal6-vorschau.png')
LOG  = os.path.join(ORD, 'render', 'deckenluecke.txt')
z = []
def p(t):
    z.append(str(t)); print(t)

O = bpy.data.objects
def box(o):
    e = [o.matrix_world @ Vector(c) for c in o.bound_box]
    return (min(v.x for v in e), max(v.x for v in e))

mu, mo = box(O['Marke_V'])
mitte = (mu + mo) / 2.0
HALB = (mo - mu) / 2.0 + 1.35      # Luecke etwas breiter als die Marke
p('Marke von x %.2f bis %.2f, Mitte %.2f' % (mu, mo, mitte))
p('Luecke von %.2f bis %.2f' % (mitte - HALB, mitte + HALB))

felder = [n for n in O.keys() if n.startswith('Deckenfeld_')]
p('Felder: ' + ', '.join(sorted(felder)))

for n in sorted(felder):
    o = O[n]
    lx, rx = box(o)
    breite = rx - lx
    links_bis = mitte - HALB
    rechts_ab = mitte + HALB

    # Rechte Haelfte als Kopie anlegen
    kopie = o.copy()
    kopie.data = o.data.copy()
    kopie.name = n + '_rechts'
    for s in o.users_collection:
        s.objects.link(kopie)

    # Linke Haelfte: von lx bis links_bis
    bl = max(0.2, links_bis - lx)
    o.scale.x *= bl / breite
    bpy.context.view_layer.update()
    nl, nr = box(o)
    o.location.x += (links_bis - nr)

    # Rechte Haelfte: von rechts_ab bis rx
    br = max(0.2, rx - rechts_ab)
    kopie.scale.x *= br / breite
    bpy.context.view_layer.update()
    kl, kr = box(kopie)
    kopie.location.x += (rechts_ab - kl)

    bpy.context.view_layer.update()
    a1, a2 = box(o)
    b1, b2 = box(kopie)
    p('   %-14s links %6.2f..%6.2f   rechts %6.2f..%6.2f' % (n, a1, a2, b1, b2))

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
sz.cycles.samples = 96
sz.render.filepath = BILD
bpy.ops.render.render(write_still=True)
p('Kontrollbild: ' + BILD)
open(LOG, 'w', encoding='utf-8').write('\n'.join(z))
