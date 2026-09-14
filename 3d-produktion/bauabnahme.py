# -*- coding: utf-8 -*-
"""Bauabnahme: der Raum war kein Raum.

Uwe hat gemeldet, im Showroom schwebten Balken, auch an der Decke. Die erste
Vermutung war Licht — dunkles Metall vor dunkler Decke. Das war falsch
geraten. Am 14.09.2026 nachgemessen, Objekt fuer Objekt, in
vecom-showroom_Fotoreal4.blend:

  Boden      X -11,50..11,50  Y  -9,11..20,87  Z -0,09..1,19
  Decke      X -11,50..11,50  Y  -8,82..21,16  Z  7,38..8,71
  Wand_links                  Y  -9,04..21,08  Z  1,76..6,74
  Wand_hinten                 Y  33,83..34,17  Z  1,24..5,05
  Portal_0_O X  -4,50.. 4,50                   (Pfosten stehen bei X +-8,6)
  Portal_0_L                                   Z  2,28..5,78
  Deckenfeld_0                                 Z  7,84..7,90 (Decke: 7,38..8,71)
  Podest                                       Z  0,75..1,06 (Boden endet 1,19)

Daraus folgt der ganze Fehler:

  * Boden und Decke endeten bei Y 21, der Saal geht bis 34. Die hinteren
    dreizehn Meter — dort haengen die Displays — hatten weder Boden noch Decke.
  * Die Seitenwaende schwebten 57 cm ueber dem Boden und hoerten 64 cm unter
    der Decke auf. Sie waren selbst schwebende Platten.
  * Die Portalbalken waren 9 m breit und spannten zwischen Pfosten, die
    17,2 m auseinanderstehen. Sie beruehrten ihre eigenen Pfosten nicht.
    Das sind die Balken an der Decke.
  * Die Lichtpaneele steckten im Deckenstein, die Abhaengungen darueber.
    Deshalb waren die Abhaengungen in der Vorschau vom 13.09. unsichtbar:
    nicht zu dunkel, sondern eingemauert.
  * Das Podest stand 13 cm im Boden, die Bodenlichtfugen lagen 50 cm darunter
    begraben, die Wandlinien hingen 1,25 m vor der Rueckwand in der Luft.

Und der Grund, warum der Saal wie ein schwarzes Loch aussah: die Waende hatten
Albedo 0,008. Kein reales Material ist so dunkel — schwarze Wandfarbe liegt
bei 0,04, dunkler Putz bei 0,06. Bei 0,008 schluckt die Wand 99 % des Lichts
und kann nichts zurueckwerfen. Es gab keine Flaeche, auf der das Auge
aufsetzen kann; was keine Flaeche hat, sieht aus, als schwebte es. Die
Dunkelheit eines Raums kommt aus dem Licht, nicht aus der Farbe.

Dasselbe kaputte Modell liegt als showroom.glb auf vecom-design.it. Nach
dieser Abnahme muss neu exportiert werden, sonst ist nur Blender heil.
"""
import bpy
from mathutils import Vector

# Massgaben, auf die ab hier alles ausgerichtet ist
BODEN_OBEN  = 1.19    # Oberkante Bodenplatte
DECKE_UNTEN = 7.38    # Unterkante Deckenplatte
DECKE_OBEN  = 8.71
WAND_INNEN  = 13.90
WAND_AUSSEN = 14.10
Y_VORN      = -15.00  # hinter der Kamera (sie faehrt bei Y -12,8 .. -11,0)
Y_WAND      = 33.83   # Innenflaeche der Rueckwand
Y_HINTEN    = 34.20


def vbb(o):
    """Huellquader aus den Punkten, nicht aus o.bound_box — der ist nach einer
    Punktaenderung noch der alte und hat mich einmal glauben lassen, nichts
    sei angekommen."""
    mw = o.matrix_world
    p = [mw @ v.co for v in o.data.vertices]
    return [(min(q.x for q in p), max(q.x for q in p)),
            (min(q.y for q in p), max(q.y for q in p)),
            (min(q.z for q in p), max(q.z for q in p))]


def setzen(name, x=None, y=None, z=None):
    """Zieht die Huelle eines Objekts auf Weltmasse, ohne die Transformation
    anzutasten. Alle Materialien hier rechnen mit Object-Koordinaten: wer die
    Objekttransformation in Ruhe laesst und nur die Punkte verschiebt, behaelt
    den Texturmassstab exakt. Deshalb Punkte statt Skalierung."""
    o = bpy.data.objects.get(name)
    if not o or o.type != 'MESH':
        print("  ! %s fehlt" % name); return
    ist, soll = vbb(o), [x, y, z]
    mw, mi = o.matrix_world, o.matrix_world.inverted()
    for v in o.data.vertices:
        w = mw @ v.co
        neu = [w.x, w.y, w.z]
        for a in range(3):
            if soll[a] is None:
                continue
            a0, a1 = ist[a]
            s0, s1 = soll[a]
            t = 0.5 if a1 - a0 < 1e-9 else (neu[a] - a0) / (a1 - a0)
            neu[a] = s0 + t * (s1 - s0)
        v.co = mi @ Vector(neu)
    o.data.update()
    b = vbb(o)
    print("  %-16s X %7.2f..%7.2f Y %7.2f..%7.2f Z %6.2f..%6.2f"
          % (name, b[0][0], b[0][1], b[1][0], b[1][1], b[2][0], b[2][1]))


def kasten(name, x0, x1, y0, y1, z0, z1, mat):
    """Quader ueber from_pydata. bpy.ops.mesh.primitive_* geht im MCP-Kontext
    nicht — dort fehlt das aktive Objekt."""
    alt = bpy.data.objects.get(name)
    if alt:
        bpy.data.objects.remove(alt, do_unlink=True)
    v = [(x0, y0, z0), (x1, y0, z0), (x1, y1, z0), (x0, y1, z0),
         (x0, y0, z1), (x1, y0, z1), (x1, y1, z1), (x0, y1, z1)]
    f = [(0, 1, 2, 3), (7, 6, 5, 4), (0, 4, 5, 1), (1, 5, 6, 2), (2, 6, 7, 3), (3, 7, 4, 0)]
    me = bpy.data.meshes.new(name)
    me.from_pydata(v, [], f)
    me.update()
    me.materials.append(mat)
    ob = bpy.data.objects.new(name, me)
    bpy.context.scene.collection.objects.link(ob)
    return ob


# ---------------------------------------------------------------- 1. Huelle
print("\n=== 1. Die Huelle schliessen ===")
# Sie reicht bis hinter die Kamera, damit im Vordergrund keine Kante ins
# Nichts steht und der Raum hinter dem Objektiv Licht zurueckwirft.
setzen('Boden',       x=(-WAND_AUSSEN, WAND_AUSSEN), y=(Y_VORN, Y_HINTEN), z=(-0.09, BODEN_OBEN))
setzen('Decke',       x=(-WAND_AUSSEN, WAND_AUSSEN), y=(Y_VORN, Y_HINTEN), z=(DECKE_UNTEN, DECKE_OBEN))
setzen('Wand_links',  x=(-WAND_AUSSEN, -WAND_INNEN), y=(Y_VORN, Y_HINTEN), z=(BODEN_OBEN - 0.07, DECKE_UNTEN + 0.02))
setzen('Wand_rechts', x=(WAND_INNEN, WAND_AUSSEN),   y=(Y_VORN, Y_HINTEN), z=(BODEN_OBEN - 0.07, DECKE_UNTEN + 0.02))
setzen('Wand_hinten', x=(-WAND_AUSSEN, WAND_AUSSEN), y=(Y_WAND, Y_HINTEN), z=(BODEN_OBEN - 0.07, DECKE_UNTEN + 0.02))
setzen('Dunst',       x=(-13.80, 13.80), y=(Y_VORN + 0.2, Y_HINTEN - 0.2), z=(BODEN_OBEN + 0.01, DECKE_UNTEN - 0.01))

# ---------------------------------------------------------------- 2. Portale
print("\n=== 2. Portale werden Rahmen ===")
# Der Balken muss auf seinen Pfosten landen, der Pfosten auf dem Boden stehen.
for nr in ('0', '1', '2'):
    bz = vbb(bpy.data.objects['Portal_%s_O' % nr])[2]
    setzen('Portal_%s_O' % nr,     x=(-8.78, 8.78))
    setzen('Portal_%s_Licht' % nr, x=(-8.60, 8.60))
    for s in ('L', 'R'):
        setzen('Portal_%s_%s' % (nr, s), z=(BODEN_OBEN - 0.02, bz[0] + 0.07))

# ---------------------------------------------------------------- 3. Decke
print("\n=== 3. Deckenlicht sitzt in der Decke ===")
# Eingelassene Linienleuchten statt haengender Platten: was buendig sitzt,
# kann nicht schweben. Damit werden die 16 Abhaengungen gegenstandslos.
Z0, Z1 = DECKE_UNTEN - 0.055, DECKE_UNTEN + 0.005
for i in range(4):
    setzen('Deckenfeld_%d' % i, x=(-11.60, 11.60), z=(Z0, Z1))
weg = [o for o in bpy.data.objects if o.name.startswith('Abhaengung_')]
for o in weg:
    bpy.data.objects.remove(o, do_unlink=True)
print("  %d Abhaengungen entfernt" % len(weg))

# Zwei durchgehende Laengsfugen geben der Decke eine Richtung und liefern das
# Grundlicht, das der geschlossene Raum jetzt braucht: vorher fiel Weltlicht
# durch die Luecken ein, die es nicht mehr gibt.
mat = bpy.data.materials['M_Deckenfeld']
for i, s in enumerate((-1, 1)):
    x = s * 6.20
    kasten('Deckenlinie_%d' % i, x - 0.16, x + 0.16, -14.60, 33.70, Z0, Z1, mat)
for n in mat.node_tree.nodes:
    if n.type == 'EMISSION':
        n.inputs['Color'].default_value = (0.80, 0.87, 1.0, 1.0)   # weniger blaustichig
        n.inputs['Strength'].default_value = 5.5                    # vorher 3,2

# ---------------------------------------------------------------- 4. Flaechen
print("\n=== 4. Was in der Luft hing, kommt an seine Flaeche ===")
setzen('Podest', z=(BODEN_OBEN, BODEN_OBEN + 0.33))                 # stand 13 cm IM Boden
for s, xv in ((-1, -9.40), (1, 9.40)):                              # lagen 50 cm im Boden
    setzen('Fuge_%d' % s, x=(xv - 0.02, xv + 0.02), y=(-9.50, Y_WAND - 0.10),
           z=(BODEN_OBEN - 0.02, BODEN_OBEN + 0.02))
for s, xv in ((-1, -WAND_INNEN), (1, WAND_INNEN)):                  # hingen 18 cm vor der Wand
    innen = xv + (0.04 if s < 0 else -0.04)
    setzen('Seitenfuge_%d_0' % s, x=(min(xv, innen), max(xv, innen)), y=(-9.50, Y_WAND - 0.10))
for i in range(11):                                                 # hingen 1,25 m vor der Wand
    setzen('Wandlinie_%02d' % i, y=(Y_WAND - 0.07, Y_WAND - 0.01))
for i in range(22):                                                 # standen 7 cm ueber dem Boden
    o = bpy.data.objects.get('Lamelle_%02d' % i)
    if o:
        setzen('Lamelle_%02d' % i, z=(BODEN_OBEN - 0.02, vbb(o)[2][1]))

print("\n=== 5. Sockelfugen als Rechteck um das Podest ===")
# Sie lagen bei Y 0,6 und 7,2 verstreut und 25 cm im Boden. Das Podest steht
# bei Y 2,14..5,64.
PX, PY = 1.78, (2.11, 5.67)
ZF = (BODEN_OBEN, BODEN_OBEN + 0.02)
setzen('Sockelfuge_0', x=(-PX, PX), y=(PY[0], PY[0] + 0.02), z=ZF)
setzen('Sockelfuge_1', x=(-PX, PX), y=(PY[1] - 0.02, PY[1]), z=ZF)
setzen('Sockelfuge_2', x=(PX - 0.02, PX), y=PY, z=ZF)
setzen('Sockelfuge_3', x=(-PX, -PX + 0.02), y=PY, z=ZF)

# ---------------------------------------------------------------- 6. Albedo
print("\n=== 6. Echte Albedo statt Schwarz ===")
NEU = {
    'M_Wand':  (0.042, 0.046, 0.055, 1.0),   # dunkler Putz
    'M_Boden': (0.030, 0.033, 0.040, 1.0),   # polierter dunkler Stein
    'M_Decke': (0.058, 0.061, 0.068, 1.0),   # im realen Raum immer die hellste Flaeche
}
for mn, farbe in NEU.items():
    m = bpy.data.materials[mn]
    for n in m.node_tree.nodes:
        if n.type != 'BSDF_PRINCIPLED':
            continue
        alt = tuple(round(v, 3) for v in n.inputs['Base Color'].default_value)
        n.inputs['Base Color'].default_value = farbe
        print("  %-9s %s -> %s" % (mn, alt, tuple(round(v, 3) for v in farbe)))
        if mn == 'M_Decke':
            n.inputs['Metallic'].default_value = 0.0
            if not n.inputs['Roughness'].is_linked:
                n.inputs['Roughness'].default_value = 0.70   # matter Putz

print("\nBauabnahme fertig. Objekte:", len(bpy.data.objects))
