# Aus dem Buehnenbild eine echte Messehalle machen.
#
# Befund: der vorhandene Raum ist 28,2 x 49,2 m bei 8,7 m Hoehe - das
# ist bereits Hallenmass. Was fehlt, sind die Hallenmerkmale: Dach-
# tragwerk, Lueftung, Sprinkler, Hallenstrahler, Stuetzen, Nachbar-
# staende. Und Dinge bekannter Groesse, damit das Auge den Massstab
# ablesen kann (Pruefung 7 der Anti-CGI-Liste).
#
# Alles Neue kommt in die Sammlung 'Halle' und traegt eigene
# Materialien - der Stand selbst wird nicht angefasst.
import bpy, bmesh, math
from mathutils import Vector

# --- Hallenmasse (echte Werte aus dem Messebau) -----------------
X0, X1 = -14.0, 14.0        # Hallenbreite, wie vorhanden
Y0, Y1 = -14.8, 34.0        # Hallentiefe, wie vorhanden
Z_BODEN = 0.0
Z_BINDER_UK = 9.30          # Unterkante Fachwerkbinder
Z_BINDER_OK = 10.70         # Oberkante
Z_PFETTE = 10.95
Z_DACH = 11.30
BINDER_ABSTAND = 8.0        # Binderabstand in y
PFETTEN_ABSTAND = 2.4

sammlung = bpy.data.collections.get('Halle')
if sammlung:
    for o in list(sammlung.objects):
        bpy.data.objects.remove(o, do_unlink=True)
else:
    sammlung = bpy.data.collections.new('Halle')
    bpy.context.scene.collection.children.link(sammlung)
print('Sammlung Halle bereit')

# --- Materialien der Halle --------------------------------------
def material(name, farbe, metall, rauheit, leucht=None, staerke=0.0):
    m = bpy.data.materials.get(name)
    if m is None:
        m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes.get('Principled BSDF')
    if b is None:
        for k in m.node_tree.nodes:
            if k.type == 'BSDF_PRINCIPLED':
                b = k
    b.inputs['Base Color'].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
    b.inputs['Metallic'].default_value = metall
    b.inputs['Roughness'].default_value = rauheit
    if leucht:
        b.inputs['Emission Color'].default_value = (leucht[0], leucht[1], leucht[2], 1.0)
        b.inputs['Emission Strength'].default_value = staerke
    return m

# Werte aus dem Messebau: Binder sind RAL 7016 anthrazit lackiert,
# Lueftungskanaele verzinkt, Sprinklerrohre rot, Dach dunkel.
M_STAHL   = material('M_HalleStahl',   (0.052, 0.055, 0.060), 0.85, 0.42)
M_VERZINK = material('M_HalleVerzinkt',(0.310, 0.330, 0.345), 0.90, 0.36)
M_SPRINK  = material('M_HalleSprinkler',(0.190, 0.022, 0.020), 0.75, 0.40)
M_DACH    = material('M_HalleDach',    (0.031, 0.032, 0.034), 0.25, 0.85)
M_STUETZE = material('M_HalleStuetze', (0.070, 0.072, 0.076), 0.15, 0.60)
M_KABEL   = material('M_HalleKabel',   (0.260, 0.270, 0.280), 0.85, 0.45)
M_LEUCHTE = material('M_HalleLeuchte', (0.020, 0.020, 0.020), 0.0, 0.30,
                     (1.0, 0.94, 0.84), 9.5)     # 4000 K Hallenstrahler
M_NOTAUS  = material('M_Notausgang',   (0.010, 0.010, 0.010), 0.0, 0.50,
                     (0.10, 1.00, 0.28), 3.2)

# --- Bauhelfer ---------------------------------------------------
_teile = []

def kasten(x0, y0, z0, x1, y1, z1, mat):
    me = bpy.data.meshes.new('t')
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    bm.to_mesh(me)
    bm.free()
    o = bpy.data.objects.new('t', me)
    o.scale = (abs(x1-x0), abs(y1-y0), abs(z1-z0))
    o.location = ((x0+x1)/2.0, (y0+y1)/2.0, (z0+z1)/2.0)
    o.data.materials.append(mat)
    sammlung.objects.link(o)
    _teile.append(o)
    return o

def rohr(x, y, z0, z1, r, mat, achse='Z', laenge=None):
    me = bpy.data.meshes.new('r')
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=16,
                          radius1=r, radius2=r, depth=1.0)
    bm.to_mesh(me)
    bm.free()
    o = bpy.data.objects.new('r', me)
    if achse == 'Z':
        o.scale = (1, 1, abs(z1-z0))
        o.location = (x, y, (z0+z1)/2.0)
    elif achse == 'Y':
        o.rotation_euler = (math.radians(90), 0, 0)
        o.scale = (1, 1, laenge)
        o.location = (x, y, z0)
    else:
        o.rotation_euler = (0, math.radians(90), 0)
        o.scale = (1, 1, laenge)
        o.location = (x, y, z0)
    o.data.materials.append(mat)
    sammlung.objects.link(o)
    _teile.append(o)
    return o

def zusammen(name):
    """Alles seit dem letzten Aufruf zu einem Objekt vereinen.
    Ein Binder besteht aus 60 Staeben - als 60 Objekte waere die
    Szene unbedienbar und der Export riesig."""
    global _teile
    if not _teile:
        return None
    bpy.ops.object.select_all(action='DESELECT')
    for o in _teile:
        o.select_set(True)
    bpy.context.view_layer.objects.active = _teile[0]
    if len(_teile) > 1:
        bpy.ops.object.join()
    ziel = bpy.context.view_layer.objects.active
    ziel.name = name
    ziel.data.name = name
    _teile = []
    return ziel

# --- 1. Fachwerkbinder ------------------------------------------
# Echter Hallenbinder: zwei Gurte, senkrechte Pfosten, Diagonalen.
# Gurt 180 mm, Streben 90 mm - das sind Masse aus dem Hallenbau.
def binder(y):
    g = 0.09
    kasten(X0, y - g, Z_BINDER_OK - 2*g, X1, y + g, Z_BINDER_OK, M_STAHL)
    kasten(X0, y - g, Z_BINDER_UK, X1, y + g, Z_BINDER_UK + 2*g, M_STAHL)
    s = 0.045
    schritt = 2.0
    n = int((X1 - X0) / schritt)
    for i in range(n + 1):
        x = X0 + i * schritt
        kasten(x - s, y - s, Z_BINDER_UK, x + s, y + s, Z_BINDER_OK, M_STAHL)
        if i < n:
            # Diagonale als schmaler, gedrehter Kasten
            me = bpy.data.meshes.new('d')
            bm = bmesh.new()
            bmesh.ops.create_cube(bm, size=1.0)
            bm.to_mesh(me); bm.free()
            o = bpy.data.objects.new('d', me)
            hoehe = Z_BINDER_OK - Z_BINDER_UK
            laenge = math.hypot(schritt, hoehe)
            o.scale = (laenge, 2*s, 2*s)
            o.rotation_euler = (0, -math.atan2(hoehe, schritt) * (1 if i % 2 == 0 else -1), 0)
            o.location = (x + schritt/2.0, y, (Z_BINDER_UK + Z_BINDER_OK)/2.0)
            o.data.materials.append(M_STAHL)
            sammlung.objects.link(o)
            _teile.append(o)

anzahl_binder = 0
y = Y0 + 2.0
while y <= Y1 - 2.0:
    binder(y)
    anzahl_binder += 1
    y += BINDER_ABSTAND
zusammen('Halle_Binder')
print('Fachwerkbinder: %d' % anzahl_binder)

# --- 2. Pfetten und Dachhaut ------------------------------------
y = Y0
n = 0
while y <= Y1:
    kasten(X0, y - 0.06, Z_PFETTE - 0.12, X1, y + 0.06, Z_PFETTE, M_STAHL)
    n += 1
    y += PFETTEN_ABSTAND
zusammen('Halle_Pfetten')
print('Pfetten: %d' % n)

kasten(X0 - 0.4, Y0 - 0.4, Z_DACH, X1 + 0.4, Y1 + 0.4, Z_DACH + 0.12, M_DACH)
zusammen('Halle_Dach')

# --- 3. Lueftung ------------------------------------------------
# Zwei verzinkte Kanaele, 800 mm, mit Abhaengungen alle 3 m.
for x in (-8.5, 8.5):
    rohr(x, (Y0 + Y1) / 2.0, Z_BINDER_UK - 0.75, 0, 0.40, M_VERZINK,
         achse='Y', laenge=(Y1 - Y0))
    yy = Y0 + 1.5
    while yy < Y1:
        kasten(x - 0.02, yy - 0.02, Z_BINDER_UK - 0.75, x + 0.02, yy + 0.02,
               Z_BINDER_UK, M_VERZINK)
        yy += 3.0
zusammen('Halle_Lueftung')

# --- 4. Sprinkler ------------------------------------------------
# Rotes Stahlrohr, Koepfe alle 3,5 m - jeder echte Hallenbesucher
# kennt das Bild, auch wenn er es nie bewusst angesehen hat.
rohr(0.0, (Y0 + Y1) / 2.0, Z_BINDER_UK - 0.25, 0, 0.055, M_SPRINK,
     achse='Y', laenge=(Y1 - Y0))
yy = Y0 + 1.75
kopf = 0
while yy < Y1:
    rohr(0.0, yy, Z_BINDER_UK - 0.42, Z_BINDER_UK - 0.25, 0.018, M_SPRINK)
    kasten(-0.05, yy - 0.05, Z_BINDER_UK - 0.47, 0.05, yy + 0.05,
           Z_BINDER_UK - 0.42, M_VERZINK)
    kopf += 1
    yy += 3.5
zusammen('Halle_Sprinkler')
print('Sprinklerkoepfe: %d' % kopf)

# --- 5. Hallenstrahler ------------------------------------------
# Raster 7 x 8 m, Abhaengung auf 8,4 m. Gehaeuse sichtbar, Linse
# leuchtend - eine Halle ohne sichtbare Leuchten wirkt nie echt.
leuchten = []
x = X0 + 3.5
while x <= X1 - 3.0:
    y = Y0 + 4.0
    while y <= Y1 - 3.0:
        kasten(x - 0.025, y - 0.025, 8.40, x + 0.025, y + 0.025,
               Z_BINDER_UK, M_VERZINK)
        kasten(x - 0.28, y - 0.28, 8.18, x + 0.28, y + 0.28, 8.40, M_STAHL)
        kasten(x - 0.24, y - 0.24, 8.14, x + 0.24, y + 0.24, 8.18, M_LEUCHTE)
        leuchten.append((x, y))
        y += 8.0
    x += 7.0
zusammen('Halle_Strahler')
print('Hallenstrahler: %d' % len(leuchten))

# --- 6. Kabeltrasse ---------------------------------------------
kasten(-6.2, Y0, Z_BINDER_UK - 0.55, -5.8, Y1, Z_BINDER_UK - 0.42, M_KABEL)
yy = Y0 + 2.0
while yy < Y1:
    kasten(-6.22, yy - 0.02, Z_BINDER_UK - 0.55, -5.78, yy + 0.02,
           Z_BINDER_UK, M_KABEL)
    yy += 4.0
zusammen('Halle_Kabeltrasse')

# --- 7. Hallenstuetzen ------------------------------------------
# 600 mm Quadrat, alle 16 m an den Laengsseiten. Sie geben dem Auge
# den Massstab und verdecken Teile - beides hilft.
stuetzen = 0
for x in (X0 + 1.2, X1 - 1.2):
    y = Y0 + 6.0
    while y <= Y1 - 4.0:
        kasten(x - 0.30, y - 0.30, Z_BODEN, x + 0.30, y + 0.30, Z_BINDER_UK, M_STUETZE)
        kasten(x - 0.42, y - 0.42, Z_BODEN, x + 0.42, y + 0.42, 0.12, M_STAHL)
        stuetzen += 1
        y += 16.0
zusammen('Halle_Stuetzen')
print('Stuetzen: %d' % stuetzen)

# --- 8. Massstab: Notausgangsschilder ---------------------------
# Pruefung 7: "Wuesste man ohne Vorwissen, wie gross der Raum ist?"
# Ein Notausgangsschild ist 30 x 15 cm und weltweit gleich - damit
# ist die Frage ohne ein Wort beantwortet.
for x, y in ((X0 + 0.35, Y1 - 6.0), (X1 - 0.35, Y1 - 6.0), (X0 + 0.35, Y0 + 6.0)):
    kasten(x - 0.02, y - 0.15, 2.35, x + 0.02, y + 0.15, 2.50, M_NOTAUS)
zusammen('Halle_Notausgang')

# --- 9. Nachbarstaende ------------------------------------------
# Einfache Baukoerper mit eigener Lichtfarbe. Sie stehen hinter der
# Schaerfeebene und liefern das, was eine Halle von einer schwarzen
# Kiste unterscheidet: es geht weiter.
M_NACHBAR = material('M_Nachbarstand', (0.085, 0.086, 0.090), 0.0, 0.55)
M_NACHBAR_L = material('M_NachbarLicht', (0.0, 0.0, 0.0), 0.0, 0.5,
                       (1.0, 0.86, 0.66), 3.0)     # 2900 K, andere Lichtfarbe
M_NACHBAR_R = material('M_NachbarLicht2', (0.0, 0.0, 0.0), 0.0, 0.5,
                       (0.86, 0.93, 1.0), 2.4)     # 6500 K

import random
random.seed(7)
nachbarn = 0
for seite, x_innen in ((-1, X0 + 2.6), (1, X1 - 2.6)):
    y = Y0 + 4.0
    while y < Y1 - 8.0:
        tiefe = random.uniform(5.5, 9.0)
        hoehe = random.uniform(3.2, 4.6)
        breite = random.uniform(2.2, 3.4)
        x_a = x_innen
        x_b = x_innen + seite * breite
        kasten(min(x_a, x_b), y, Z_BODEN, max(x_a, x_b), y + tiefe, hoehe, M_NACHBAR)
        # Leuchtblende oben - jeder Stand hat eine
        mat = M_NACHBAR_L if nachbarn % 2 == 0 else M_NACHBAR_R
        kasten(min(x_a, x_b) - seite * 0.03, y + 0.25, hoehe - 0.42,
               max(x_a, x_b) - seite * 0.03, y + tiefe - 0.25, hoehe - 0.08, mat)
        nachbarn += 1
        y += tiefe + random.uniform(1.2, 2.6)
zusammen('Halle_Nachbarstaende')
print('Nachbarstaende: %d' % nachbarn)

# --- 10. Alte Flachdecke und Dunst entfernen --------------------
# Die vorhandene 'Decke' war eine geschlossene Platte auf 8,7 m.
# Darueber liegt jetzt das Tragwerk - beides zusammen ergaebe eine
# doppelte Decke, und die Halle waere wieder zu.
for name in ('Decke', 'Dunst'):
    o = bpy.data.objects.get(name)
    if o:
        bpy.data.objects.remove(o, do_unlink=True)
        print('entfernt: %s' % name)

# Waende bis unter das Tragwerk hochziehen, sonst klafft ein Spalt.
for name in ('Wand_links', 'Wand_rechts', 'Wand_hinten'):
    o = bpy.data.objects.get(name)
    if not o:
        continue
    ecken = [o.matrix_world @ Vector(c) for c in o.bound_box]
    oben = max(e.z for e in ecken)
    unten = min(e.z for e in ecken)
    hoehe = oben - unten
    if hoehe > 0.01:
        faktor = (Z_BINDER_UK - unten) / hoehe
        o.scale.z *= faktor
        o.location.z = unten + (o.location.z - unten) * faktor
        print('%s auf %.2f m gezogen' % (name, Z_BINDER_UK))

bpy.context.view_layer.update()
netze = [o for o in bpy.data.objects if o.type == 'MESH']
print('Objekte gesamt: %d' % len(netze))
ZIEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\vecom-showroom_Halle.blend'
bpy.ops.wm.save_as_mainfile(filepath=ZIEL)
print('gespeichert: %s' % ZIEL)
