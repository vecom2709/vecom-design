# -*- coding: utf-8 -*-
"""Esstisch als erstes Produktobjekt fuer den Abschnitt "Echtzeit statt
Standbild" — Varianz zum Anfassen statt Varianz behauptet.

WARUM EIN TISCH UND KEIN SESSEL

Der Kartentext spricht von einem Sessel in sechs Stoffen. Ein gepolsterter
Sessel ist aus einem Skript heraus nicht fotorealistisch zu bauen: Polster
braucht Subdivision mit sauberer Topologie, Stoffsimulation fuer den
Faltenwurf und Mikrostruktur im Gewebe. Was dabei herauskaeme, saehe nach
Skript aus — genau das Gegenteil dessen, was die Seite belegen soll.

Hartware dagegen wird aus dem Skript fotorealistisch, und sie traegt dasselbe
Argument: 4 Hoelzer x 3 Metalle x 2 Gestelle x 3 Maesse = 72 Varianten aus
einem Modell. Nachrechenbar, und jede weitere kostet einen Parameter.

MASSE, DIE JEDER IM GEFUEHL HAT (derselbe Grundsatz wie in stand_moebel.py)
    Hoehe Oberkante   0,75 m   — Esstischhoehe, weltweit
    Plattenstaerke    0,04 m   — Massivholz, nicht furniert
    Breite            0,95 m   — zwei Gedecke gegenueber
    Laenge      1,80 / 2,00 / 2,40 m

BLENDER-5-FALLEN, die hier schon eingearbeitet sind:
  * mesh.use_auto_smooth gibt es nicht mehr — geglaettet wird ueber den
    Modifikator "Smooth by Angle" bzw. shade_smooth mit Winkel.
  * Knoten werden ueber n.type gesucht, nie ueber den Namen: Die Oberflaeche
    ist deutsch, die Namen sind es auch.
  * object.dimensions ist die LOKALE Huellbox mal Skalierung. Gemessen wird
    ueber matrix_world @ bound_box.

Aufruf (Hintergrund, ohne Datei, factory-startup wegen BlenderKit):
    blender -b --factory-startup -P tisch.py
"""
import bpy, bmesh, math, os
from mathutils import Vector

WURZEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL_BLEND = os.path.join(WURZEL, 'vecom-tisch.blend')
ZIEL_BILD = os.path.join(WURZEL, 'render', 'tisch-kontrolle.png')

LAENGE, BREITE, HOEHE, STAERKE = 2.00, 0.95, 0.75, 0.040

# --------------------------------------------------------------- Szene leeren
for d in (bpy.data.objects, bpy.data.meshes, bpy.data.materials,
          bpy.data.lights, bpy.data.cameras, bpy.data.images):
    for x in list(d):
        try: d.remove(x, do_unlink=True)
        except Exception: pass

szene = bpy.context.scene
szene.render.engine = 'CYCLES'


def knoten(mat):
    """Den Principled-Knoten holen. Ueber type, nie ueber den Namen."""
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            return n
    return None


def neu_material(name):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    return m, m.node_tree, knoten(m)


# ----------------------------------------------------------------- Hoelzer
# Maserung entsteht aus gestrecktem Rauschen, das von einer Welle gebrochen
# wird: Die Welle gibt die Jahresringe, das Rauschen verzieht sie. Ohne die
# Verzerrung sieht es aus wie Sperrholz aus dem Katalog.
HOLZ = {
    'Eiche':        ((0.262, 0.157, 0.078), (0.395, 0.258, 0.132), 5.2, 0.30),
    'Nussbaum':     ((0.091, 0.048, 0.026), (0.178, 0.104, 0.058), 4.4, 0.26),
    'Esche':        ((0.451, 0.337, 0.208), (0.585, 0.465, 0.310), 6.1, 0.34),
    'Raeuchereiche':((0.043, 0.029, 0.021), (0.098, 0.068, 0.048), 5.2, 0.22),
}

def holz_material(name, dunkel, hell, ringe, rauheit):
    m, nt, b = neu_material('M_Holz_' + name)
    koord = nt.nodes.new('ShaderNodeTexCoord')
    abb = nt.nodes.new('ShaderNodeMapping')
    abb.inputs['Scale'].default_value = (1.0, 0.16, 1.0)   # Maserung laeuft laengs
    rausch = nt.nodes.new('ShaderNodeTexNoise')
    rausch.inputs['Scale'].default_value = 2.6
    rausch.inputs['Detail'].default_value = 8.0
    rausch.inputs['Roughness'].default_value = 0.62
    welle = nt.nodes.new('ShaderNodeTexWave')
    welle.wave_type = 'BANDS'
    welle.bands_direction = 'X'
    welle.wave_profile = 'SIN'
    welle.inputs['Scale'].default_value = ringe
    welle.inputs['Distortion'].default_value = 7.5
    welle.inputs['Detail'].default_value = 3.0
    rampe = nt.nodes.new('ShaderNodeValToRGB')
    rampe.color_ramp.elements[0].color = (dunkel[0], dunkel[1], dunkel[2], 1.0)
    rampe.color_ramp.elements[1].color = (hell[0], hell[1], hell[2], 1.0)
    rampe.color_ramp.elements[0].position = 0.28
    rampe.color_ramp.elements[1].position = 0.78

    nt.links.new(koord.outputs['Object'], abb.inputs['Vector'])
    nt.links.new(abb.outputs['Vector'], rausch.inputs['Vector'])
    nt.links.new(abb.outputs['Vector'], welle.inputs['Vector'])
    nt.links.new(rausch.outputs['Fac'], welle.inputs['Distortion'])
    nt.links.new(welle.outputs['Fac'], rampe.inputs['Fac'])
    nt.links.new(rampe.outputs['Color'], b.inputs['Base Color'])

    # Poren als feine Rauheitsschwankung. Ein gleichmaessiger Lack sieht
    # gedruckt aus; echte Oberflaechen haben Stellen, die anders glaenzen.
    poren = nt.nodes.new('ShaderNodeTexNoise')
    poren.inputs['Scale'].default_value = 340.0
    poren.inputs['Detail'].default_value = 3.0
    rau = nt.nodes.new('ShaderNodeMapRange')
    rau.inputs['From Min'].default_value = 0.35
    rau.inputs['From Max'].default_value = 0.65
    rau.inputs['To Min'].default_value = rauheit - 0.05
    rau.inputs['To Max'].default_value = rauheit + 0.05
    nt.links.new(abb.outputs['Vector'], poren.inputs['Vector'])
    nt.links.new(poren.outputs['Fac'], rau.inputs['Value'])
    nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])

    b.inputs['Coat Weight'].default_value = 0.55     # seidenmatter Lack
    b.inputs['Coat Roughness'].default_value = 0.14
    b.inputs['IOR'].default_value = 1.51
    return m


# ----------------------------------------------------------------- Metalle
METALL = {
    'Schwarzstahl': ((0.036, 0.038, 0.042), 0.42),
    'Edelstahl':    ((0.552, 0.565, 0.580), 0.24),
    'Messing':      ((0.492, 0.365, 0.148), 0.28),
}

def metall_material(name, farbe, rauheit):
    m, nt, b = neu_material('M_Metall_' + name)
    b.inputs['Base Color'].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
    b.inputs['Metallic'].default_value = 1.0
    b.inputs['Anisotropic'].default_value = 0.62      # Buerstung ueber Glanz,
    b.inputs['Anisotropic Rotation'].default_value = 0.0   # nicht ueber Textur
    koord = nt.nodes.new('ShaderNodeTexCoord')
    streu = nt.nodes.new('ShaderNodeTexNoise')
    streu.inputs['Scale'].default_value = 90.0
    streu.inputs['Detail'].default_value = 2.0
    rau = nt.nodes.new('ShaderNodeMapRange')
    rau.inputs['From Min'].default_value = 0.4
    rau.inputs['From Max'].default_value = 0.6
    rau.inputs['To Min'].default_value = rauheit - 0.04
    rau.inputs['To Max'].default_value = rauheit + 0.04
    nt.links.new(koord.outputs['Object'], streu.inputs['Vector'])
    nt.links.new(streu.outputs['Fac'], rau.inputs['Value'])
    nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])
    return m


HOELZER = {k: holz_material(k, *v) for k, v in HOLZ.items()}
METALLE = {k: metall_material(k, *v) for k, v in METALL.items()}

# Boden: heller Studioboden, damit das Holz Kontaktschatten bekommt
m_boden, nt_b, b_boden = neu_material('M_Studioboden')
b_boden.inputs['Base Color'].default_value = (0.205, 0.205, 0.208, 1.0)
b_boden.inputs['Roughness'].default_value = 0.42


# ------------------------------------------------------------------ Geometrie
def kasten(name, mitte, groesse, mat, fase=0.0035):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    bmesh.ops.scale(bm, vec=Vector(groesse), verts=bm.verts)
    bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new(name, me)
    o.location = mitte
    me.materials.append(mat)
    bpy.context.collection.objects.link(o)
    if fase > 0:
        f = o.modifiers.new('Fase', 'BEVEL')
        f.width = fase
        f.segments = 3
        f.limit_method = 'ANGLE'
        f.angle_limit = math.radians(30)
        wn = o.modifiers.new('Normalen', 'WEIGHTED_NORMAL')
        wn.keep_sharp = True
    for p in me.polygons:
        p.use_smooth = True
    return o


teile = []

# Platte — 4 cm massiv, Oberkante auf 0,75
teile.append(kasten('Tischplatte', (0, 0, HOEHE - STAERKE / 2),
                    (LAENGE, BREITE, STAERKE), HOELZER['Eiche'], fase=0.004))

# Gestell A: zwei Wangen aus Flachstahl, 6 cm eingerueckt, plus Laengstraverse
WANGE_T, WANGE_B = 0.012, 0.070
x_w = LAENGE / 2 - 0.26
for s, x in (('L', -x_w), ('R', x_w)):
    teile.append(kasten('Wange_' + s, (x, 0, (HOEHE - STAERKE) / 2),
                        (WANGE_T, BREITE - 0.18, HOEHE - STAERKE),
                        METALLE['Schwarzstahl'], fase=0.002))
    teile.append(kasten('Fuss_' + s, (x, 0, 0.010),
                        (0.075, BREITE - 0.10, 0.020),
                        METALLE['Schwarzstahl'], fase=0.002))
teile.append(kasten('Traverse', (0, 0, 0.115),
                    (2 * x_w - WANGE_T, 0.055, 0.016),
                    METALLE['Schwarzstahl'], fase=0.002))

boden = kasten('Studioboden', (0, 0, -0.004), (14.0, 14.0, 0.008), m_boden, fase=0.0)

# ------------------------------------------------------------------- Licht
def flaeche(name, ort, blick, groesse, farbe_k, watt):
    ld = bpy.data.lights.new(name, 'AREA')
    ld.shape = 'RECTANGLE'
    ld.size, ld.size_y = groesse
    ld.energy = watt
    # Farbtemperatur von Hand: Blender 5 hat den Blackbody-Knoten, aber fuer
    # Lampen ist die direkte Farbe verlaesslicher ueber Versionen hinweg.
    t = farbe_k
    if t >= 6500: ld.color = (0.90, 0.94, 1.00)
    elif t >= 5600: ld.color = (1.00, 0.98, 0.95)
    else: ld.color = (1.00, 0.89, 0.76)
    o = bpy.data.objects.new(name, ld)
    o.location = ort
    d = Vector(blick) - Vector(ort)
    o.rotation_euler = d.to_track_quat('-Z', 'Y').to_euler()
    bpy.context.collection.objects.link(o)
    return o

flaeche('Key',  (-2.30, -1.90, 3.10), (0, 0, 0.62), (2.60, 1.80), 5600, 620)
flaeche('Fill', ( 2.80, -1.10, 1.90), (0, 0, 0.55), (2.20, 2.20), 6500, 150)
flaeche('Kante',( 0.60,  2.60, 2.10), (0, 0, 0.70), (2.40, 1.20), 7200, 240)

welt = bpy.data.worlds.new('Studio')
szene.world = welt
welt.use_nodes = True
for n in welt.node_tree.nodes:
    if n.type == 'BACKGROUND':
        n.inputs['Color'].default_value = (0.020, 0.023, 0.028, 1.0)
        n.inputs['Strength'].default_value = 1.0

# ------------------------------------------------------------------ Kamera
kd = bpy.data.cameras.new('Kamera')
kd.lens = 50.0
kd.sensor_width = 36.0
kd.dof.use_dof = True
kd.dof.aperture_fstop = 2.8
kd.dof.aperture_blades = 9
kd.dof.aperture_rotation = math.radians(7)
kam = bpy.data.objects.new('Kamera', kd)
kam.location = (2.05, -2.85, 1.28)
ziel = Vector((0.0, 0.0, 0.66))
kam.rotation_euler = (ziel - Vector(kam.location)).to_track_quat('-Z', 'Y').to_euler()
bpy.context.collection.objects.link(kam)
szene.camera = kam
kd.dof.focus_distance = (ziel - Vector(kam.location)).length

# ---------------------------------------------------------------- Rendern
c = szene.cycles
c.samples = 96
c.use_adaptive_sampling = True
c.adaptive_threshold = 0.012
c.use_denoising = True
try:
    c.denoiser = 'OPENIMAGEDENOISE'
    c.denoising_input_passes = 'RGB_ALBEDO_NORMAL'
    c.denoising_prefilter = 'ACCURATE'
except Exception:
    pass
c.blur_glossy = 0.4
c.glossy_bounces = 8
c.transmission_bounces = 8

# Grafikkarte einschalten — ohne das faellt Cycles still auf die CPU zurueck.
try:
    vor = bpy.context.preferences.addons['cycles'].preferences
    gewaehlt = None
    for art in ('OPTIX', 'CUDA', 'HIP', 'ONEAPI'):
        try:
            vor.compute_device_type = art
            vor.get_devices()
            if any(d.type == art for d in vor.devices):
                gewaehlt = art
                break
        except Exception:
            continue
    if gewaehlt:
        for d in vor.devices:
            d.use = (d.type == gewaehlt)
        c.device = 'GPU'
        print('[TISCH] Rechenweg: ' + gewaehlt)
    else:
        c.device = 'CPU'
        print('[TISCH] Rechenweg: CPU (keine nutzbare Grafikkarte gefunden)')
except Exception as e:
    c.device = 'CPU'
    print('[TISCH] Rechenweg: CPU (' + str(e) + ')')

szene.render.resolution_x = 1600
szene.render.resolution_y = 1000
szene.render.resolution_percentage = 55      # Kontrollbild zuerst, wie besprochen
szene.render.film_transparent = False
szene.view_settings.view_transform = 'AgX'
szene.view_settings.look = 'AgX - Medium High Contrast'
szene.render.filepath = ZIEL_BILD

# ---------------------------------------------------- Mass nachmessen, nicht raten
def welt_masse(o):
    ecken = [o.matrix_world @ Vector(k) for k in o.bound_box]
    xs = [p.x for p in ecken]; ys = [p.y for p in ecken]; zs = [p.z for p in ecken]
    return (max(xs) - min(xs), max(ys) - min(ys), max(zs) - min(zs), min(zs), max(zs))

print('[TISCH] --- Masspruefung ---')
for o in teile:
    b_, t_, h_, z0, z1 = welt_masse(o)
    print('[TISCH] %-14s  B %.3f  T %.3f  H %.3f   z %.3f..%.3f' % (o.name, b_, t_, h_, z0, z1))

bpy.ops.wm.save_as_mainfile(filepath=ZIEL_BLEND)
print('[TISCH] gespeichert: ' + ZIEL_BLEND)

bpy.ops.render.render(write_still=True)
print('[TISCH] Bild: ' + ZIEL_BILD)
