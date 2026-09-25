# Drei Korrekturen am Showroom - gerechnet, nicht geschaetzt.
# Ergebnis wird als Fotoreal6 gespeichert, die Fassung 5 bleibt liegen.
#
# GEMESSENE AUSGANGSLAGE
#   Kamera        (-3.30, -12.10, 2.00), 50 mm auf 36 mm Sensor
#   Blickpunkt    (-0.90,  4.20,  3.45), TRACK_TO
#   Marke_V       Mitte (-0.02, 4.01, 4.06), 5.09 breit, 4.10 hoch
#                 -> Unterkante z = 2.01
#   Podest        Mitte ( 0.00, 3.89, 0.90), Oberkante z = 1.04
#   -> zwischen Podest und Marke klaffen 0,97 m. Sie schwebt.
#
# 1. RECHTES DRITTEL
#    Bildbreite am Ort der Marke: Abstand 16,4 m, waagerechter
#    Bildwinkel 2*atan(36/100) = 39,6 Grad -> Breite 11,8 m.
#    Das rechte Drittel liegt 1/6 der Breite rechts der Achse: 1,97 m.
#    Die Blickachse laeuft bei y=4,01 durch x = -0,93.
#    Ziel also x = +1,04, heute -0,02  ->  verschieben um +1,06.
#
# 2. BODENKONTAKT
#    Marke um 1,10 senken: Unterkante 2,01 -> 0,91.
#    Podest aufdicken, Oberkante 1,04 -> 1,30.
#    Die Marke steckt dann 0,39 m im Sockel statt darueber zu schweben.
#
# 3. DECKENLINIEN AUS DEM BILD
#    Sie liegen bei x = +-6,20, die Bildkante bei +-5,90 - also genau
#    im Bild. Nach aussen auf +-9,40. Zusaetzlich das vorderste
#    Deckenfeld von y=9,17 nach y=12,60, damit es nicht hinter der
#    Markenspitze durchlaeuft.
import bpy, os
from mathutils import Vector

ORD = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL = os.path.join(ORD, 'vecom-showroom_Fotoreal6.blend')
BILD = os.path.join(ORD, 'render', 'fotoreal6-vorschau.png')
LOG  = os.path.join(ORD, 'render', 'korrektur6.txt')

z = []
def p(t):
    z.append(str(t)); print(t)

O = bpy.data.objects

def mitte(o):
    return sum((o.matrix_world @ Vector(c) for c in o.bound_box), Vector()) / 8.0

# --- 1. Gruppe aufs rechte Drittel -----------------------------------
GRUPPE = ['Marke_V', 'Markenkante', 'Podest',
          'Sockelfuge_0', 'Sockelfuge_1', 'Sockelfuge_2', 'Sockelfuge_3']
DX = 1.06
for n in GRUPPE:
    if n in O:
        O[n].location.x += DX
p('1. Marke, Podest und Fugen um %+.2f m nach rechts' % DX)

# --- 2. Bodenkontakt --------------------------------------------------
DZ = -1.10
for n in ['Marke_V', 'Markenkante']:
    if n in O:
        O[n].location.z += DZ
p('2. Marke um %+.2f m gesenkt' % DZ)

# Podest aufdicken: Unterkante halten, Oberkante auf 1,30 bringen.
pod = O['Podest']
alt_h = pod.dimensions.z
unten = mitte(pod).z - alt_h / 2.0
neu_h = 1.30 - unten
faktor = neu_h / alt_h
pod.scale.z *= faktor
bpy.context.view_layer.update()
pod.location.z += (1.30 - (mitte(pod).z + pod.dimensions.z / 2.0))
bpy.context.view_layer.update()
p('   Podest: Hoehe %.2f -> %.2f m, Oberkante jetzt %.2f' %
  (alt_h, pod.dimensions.z, mitte(pod).z + pod.dimensions.z / 2.0))

# --- 3. Decke aus dem Bild -------------------------------------------
for n, x in (('Deckenlinie_0', -9.40), ('Deckenlinie_1', 9.40)):
    if n in O:
        alt = O[n].location.x
        O[n].location.x += (x - mitte(O[n]).x)
        p('3. %s von x=%.2f nach x=%.2f' % (n, alt, O[n].location.x))
if 'Deckenfeld_0' in O:
    O['Deckenfeld_0'].location.y += 3.43
    p('   Deckenfeld_0 um +3.43 m nach hinten')

bpy.context.view_layer.update()

# --- Gegenprobe -------------------------------------------------------
p('')
p('-- Nachgemessen')
mv = mitte(O['Marke_V'])
p('   Marke_V      Mitte %6.2f /%6.2f /%6.2f   Unterkante %5.2f' %
  (mv.x, mv.y, mv.z, mv.z - O['Marke_V'].dimensions.z / 2.0))
mp = mitte(pod)
p('   Podest       Oberkante %5.2f' % (mp.z + pod.dimensions.z / 2.0))
ueberlapp = (mp.z + pod.dimensions.z / 2.0) - (mv.z - O['Marke_V'].dimensions.z / 2.0)
p('   Ueberlappung: %.2f m  (positiv = steckt im Sockel)' % ueberlapp)

bpy.ops.wm.save_as_mainfile(filepath=ZIEL)
p('')
p('Gespeichert: ' + ZIEL)

# --- Kontrollbild -----------------------------------------------------
sz = bpy.context.scene
try:
    pr = bpy.context.preferences.addons['cycles'].preferences
    pr.compute_device_type = 'OPTIX'
    pr.get_devices()
    for d in pr.devices:
        d.use = (d.type == 'OPTIX')
    sz.cycles.device = 'GPU'
except Exception as e:
    p('GPU: %s' % e)
sz.render.resolution_x = 960
sz.render.resolution_y = 540
sz.cycles.samples = 64
sz.render.filepath = BILD
bpy.ops.render.render(write_still=True)
p('Kontrollbild: ' + BILD)

open(LOG, 'w', encoding='utf-8').write('\n'.join(z))
