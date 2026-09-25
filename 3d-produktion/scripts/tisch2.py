# -*- coding: utf-8 -*-
"""Esstisch, Durchgang 2 — die vier Befunde aus Durchgang 1 abgearbeitet.

BEFUND 1: MASERUNG WAR GESTREIFT, NICHT GEWACHSEN
Die Wave-Textur lief diagonal und mit zu wenig Verzerrung: regelmaessige
Baender, die wie Wellpappe lesen. Echtes Sageholz hat Jahresringe, die LAENGS
der Bohle laufen und quer dazu angeschnitten sind -- daraus entsteht die
Kathedralfigur. Drei Aenderungen:
  * Die Abbildung staucht X stark (0.09) und dehnt Y. Dadurch laufen alle
    Merkmale in Laengsrichtung, statt schraeg ueber die Platte.
  * Die Verzerrung der Welle kommt aus einem grossen, langsamen Rauschen mit
    Faktor 9 statt 7.5 -- erst ab dieser Staerke werden aus Baendern Figuren.
  * Die Farbrampe hat vier Stuetzstellen statt zwei. Ein Jahresring ist keine
    Ueberblendung von hell nach dunkel, sondern eine harte dunkle Linie in
    hellem Holz.

BEFUND 2: DIE FARBE WAR BUCHE, NICHT EICHE
Die Werte waren geraten. Jetzt aus sRGB-Messwerten realer Bohlen umgerechnet
(sRGB -> linear, weil Blender im linearen Raum rechnet). Die Umrechnung steht
unten als Funktion, damit die Zahlen nachvollziehbar bleiben.

BEFUND 3: DAS METALL SPIEGELTE SCHWARZ
Uwes eigene Lehre aus dem Showroom vom 12.09.2026: "Der Raum ist eine
geschlossene Kiste -- ohne emissive Flaechen im Raum spiegelt Chrom nur
Schwarz." Hier war es dasselbe: drei Flaechenleuchten, die die Kamera nicht
sieht, und eine fast schwarze Welt. Jetzt stehen drei emissive Tafeln im
Raum, fuer die Kamera unsichtbar, fuer Glanz und Diffus sichtbar. Sie sind
das, was im Stahl steht.

BEFUND 4: DER BODEN HATTE EINEN HARTEN HORIZONT
Platte plus dunkler Hintergrund ergibt eine Kante. Jetzt eine echte
Hohlkehle: Der Boden laeuft nach hinten in einem Viertelkreis hoch, ohne
Knick. Ein Studio hat keinen Horizont.

NEBENBEFUND aus Durchgang 1, hier behoben: Nach o.location = ... ist
matrix_world erst nach einem Depsgraph-Durchlauf gueltig. Ohne
view_layer.update() misst man die Werte VOR dem Setzen -- das Protokoll
meldete eine Tischplatte bei z -0.020..0.020.

    blender -b --factory-startup -P tisch2.py
"""
import bpy, bmesh, math, os
from mathutils import Vector

WURZEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL_BLEND = os.path.join(WURZEL, 'vecom-tisch2.blend')
ZIEL_BILD = os.path.join(WURZEL, 'render', 'tisch-kontrolle2.png')

LAENGE, BREITE, HOEHE, STAERKE = 2.00, 0.95, 0.75, 0.040


def srgb(r, g, b):
    """sRGB 0..255 in den linearen Raum, in dem Blender rechnet."""
    def k(v):
        v = v / 255.0
        return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4
    return (k(r), k(g), k(b))


for d in (bpy.data.objects, bpy.data.meshes, bpy.data.materials,
          bpy.data.lights, bpy.data.cameras, bpy.data.images, bpy.data.worlds):
    for x in list(d):
        try: d.remove(x, do_unlink=True)
        except Exception: pass

szene = bpy.context.scene
szene.render.engine = 'CYCLES'


def prinzipiell(mat):
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            return n
    return None


def neu_material(name):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    return m, m.node_tree, prinzipiell(m)


# ------------------------------------------------------------------- Hoelzer
# (Kernton, Rington, Ringdichte, Grundrauheit) -- Toene als sRGB gemessen
HOLZ = {
    'Eiche':         (srgb(154, 117, 72), srgb(107, 79, 46), 14.0, 0.30),
    'Nussbaum':      (srgb(138, 95, 58),  srgb(74, 46, 26),  11.5, 0.26),
    'Esche':         (srgb(194, 166, 127), srgb(154, 123, 84), 16.0, 0.33),
    'Raeuchereiche': (srgb(94, 69, 48),   srgb(48, 34, 24),  14.0, 0.22),
}


def holz_material(name, kern, ring, dichte, rauheit):
    m, nt, b = neu_material('M_Holz_' + name)
    koord = nt.nodes.new('ShaderNodeTexCoord')

    # X stauchen: alles laeuft laengs der Bohle. Das ist der eigentliche Fix.
    abb = nt.nodes.new('ShaderNodeMapping')
    abb.inputs['Scale'].default_value = (0.09, 2.6, 2.6)

    # Langsames, grosses Rauschen -- es verzieht die Ringe zur Kathedralfigur.
    gross = nt.nodes.new('ShaderNodeTexNoise')
    gross.inputs['Scale'].default_value = 1.4
    gross.inputs['Detail'].default_value = 6.0
    gross.inputs['Roughness'].default_value = 0.58

    welle = nt.nodes.new('ShaderNodeTexWave')
    welle.wave_type = 'BANDS'
    welle.bands_direction = 'Y'          # Ringe quer angeschnitten
    welle.wave_profile = 'SIN'
    welle.inputs['Scale'].default_value = dichte
    welle.inputs['Detail'].default_value = 4.0
    welle.inputs['Detail Scale'].default_value = 1.6

    verzerr = nt.nodes.new('ShaderNodeMath')
    verzerr.operation = 'MULTIPLY'
    verzerr.inputs[1].default_value = 9.0

    rampe = nt.nodes.new('ShaderNodeValToRGB')
    cr = rampe.color_ramp
    # Vier Stuetzstellen: heller Kern, harte dunkle Linie, wieder Kern.
    cr.elements[0].position = 0.00; cr.elements[0].color = (*kern, 1.0)
    cr.elements[1].position = 0.42; cr.elements[1].color = (*kern, 1.0)
    e2 = cr.elements.new(0.52); e2.color = (*ring, 1.0)
    e3 = cr.elements.new(0.60); e3.color = (*kern, 1.0)

    # Spiegelstrahlen der Eiche: helle Flecken quer zur Faser, sehr gestreckt.
    strahl = nt.nodes.new('ShaderNodeTexNoise')
    strahl.inputs['Scale'].default_value = 5.0
    strahl.inputs['Detail'].default_value = 3.0
    abb2 = nt.nodes.new('ShaderNodeMapping')
    abb2.inputs['Scale'].default_value = (0.30, 9.0, 9.0)
    mischen = nt.nodes.new('ShaderNodeMixRGB')
    mischen.blend_type = 'SOFT_LIGHT'
    mischen.inputs['Fac'].default_value = 0.18

    nt.links.new(koord.outputs['Object'], abb.inputs['Vector'])
    nt.links.new(abb.outputs['Vector'], gross.inputs['Vector'])
    nt.links.new(abb.outputs['Vector'], welle.inputs['Vector'])
    nt.links.new(gross.outputs['Fac'], verzerr.inputs[0])
    nt.links.new(verzerr.outputs['Value'], welle.inputs['Distortion'])
    nt.links.new(welle.outputs['Fac'], rampe.inputs['Fac'])
    nt.links.new(koord.outputs['Object'], abb2.inputs['Vector'])
    nt.links.new(abb2.outputs['Vector'], strahl.inputs['Vector'])
    nt.links.new(rampe.outputs['Color'], mischen.inputs['Color1'])
    nt.links.new(strahl.outputs['Fac'], mischen.inputs['Color2'])
    nt.links.new(mischen.outputs['Color'], b.inputs['Base Color'])

    # Poren: feine Rauheitsschwankung. Gleichmaessiger Lack sieht gedruckt aus.
    poren = nt.nodes.new('ShaderNodeTexNoise')
    poren.inputs['Scale'].default_value = 260.0
    poren.inputs['Detail'].default_value = 3.0
    rau = nt.nodes.new('ShaderNodeMapRange')
    rau.inputs['From Min'].default_value = 0.35
    rau.inputs['From Max'].default_value = 0.65
    rau.inputs['To Min'].default_value = max(0.04, rauheit - 0.06)
    rau.inputs['To Max'].default_value = rauheit + 0.06
    nt.links.new(abb.outputs['Vector'], poren.inputs['Vector'])
    nt.links.new(poren.outputs['Fac'], rau.inputs['Value'])
    nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])

    # Die Poren sind auch Geometrie, nicht nur Glanz -- flaches Bump.
    bump = nt.nodes.new('ShaderNodeBump')
    bump.inputs['Strength'].default_value = 0.10
    bump.inputs['Distance'].default_value = 0.0006
    nt.links.new(poren.outputs['Fac'], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])

    b.inputs['Coat Weight'].default_value = 0.42
    b.inputs['Coat Roughness'].default_value = 0.16
    b.inputs['IOR'].default_value = 1.51
    return m


METALL = {
    'Schwarzstahl': (srgb(58, 60, 64), 0.38),
    'Edelstahl':    (srgb(196, 199, 203), 0.22),
    'Messing':      (srgb(181, 141, 72), 0.26),
}


def metall_material(name, farbe, rauheit):
    m, nt, b = neu_material('M_Metall_' + name)
    b.inputs['Base Color'].default_value = (*farbe, 1.0)
    b.inputs['Metallic'].default_value = 1.0
    b.inputs['Anisotropic'].default_value = 0.55
    koord = nt.nodes.new('ShaderNodeTexCoord')
    streu = nt.nodes.new('ShaderNodeTexNoise')
    streu.inputs['Scale'].default_value = 70.0
    streu.inputs['Detail'].default_value = 2.0
    rau = nt.nodes.new('ShaderNodeMapRange')
    rau.inputs['From Min'].default_value = 0.4
    rau.inputs['From Max'].default_value = 0.6
    rau.inputs['To Min'].default_value = rauheit - 0.05
    rau.inputs['To Max'].default_value = rauheit + 0.05
    nt.links.new(koord.outputs['Object'], streu.inputs['Vector'])
    nt.links.new(streu.outputs['Fac'], rau.inputs['Value'])
    nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])
    return m


HOELZER = {k: holz_material(k, *v) for k, v in HOLZ.items()}
METALLE = {k: metall_material(k, *v) for k, v in METALL.items()}

m_boden, _, b_boden = neu_material('M_Hohlkehle')
b_boden.inputs['Base Color'].default_value = (*srgb(126, 128, 132), 1.0)
b_boden.inputs['Roughness'].default_value = 0.46


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
        f.width = fase; f.segments = 3
        f.limit_method = 'ANGLE'; f.angle_limit = math.radians(30)
        wn = o.modifiers.new('Normalen', 'WEIGHTED_NORMAL')
        wn.keep_sharp = True
    for p in me.polygons:
        p.use_smooth = True
    return o


teile = []
teile.append(kasten('Tischplatte', (0, 0, HOEHE - STAERKE / 2),
                    (LAENGE, BREITE, STAERKE), HOELZER['Eiche'], fase=0.004))

WANGE_T = 0.012
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


# ------------------------------------------------------------ Hohlkehle
# Boden laeuft nach hinten in einem Viertelkreis hoch. Kein Knick, kein
# Horizont -- ein Studio hat keinen.
def hohlkehle(breite=16.0, vorn=-7.0, kehle=2.2, radius=3.4, hoehe=6.0, n=28):
    bm = bmesh.new()
    profil = []
    profil.append((vorn, 0.0))
    profil.append((kehle, 0.0))
    for i in range(1, n + 1):
        a = (math.pi / 2) * (i / n)
        profil.append((kehle + radius * math.sin(a), radius * (1 - math.cos(a))))
    profil.append((kehle + radius, hoehe))

    reihen = []
    for y, z in profil:
        reihen.append([bm.verts.new((-breite / 2, y, z)),
                       bm.verts.new(( breite / 2, y, z))])
    for i in range(len(reihen) - 1):
        a, b_ = reihen[i]
        c, d = reihen[i + 1]
        bm.faces.new((a, b_, d, c))
    bm.normal_update()
    me = bpy.data.meshes.new('Hohlkehle')
    bm.to_mesh(me); bm.free()
    for p in me.polygons:
        p.use_smooth = True
    o = bpy.data.objects.new('Hohlkehle', me)
    me.materials.append(m_boden)
    bpy.context.collection.objects.link(o)
    return o


hohlkehle()


# ------------------------------------------------- Softboxen: das, was spiegelt
def softbox(name, ort, blick, groesse, farbe_k, staerke):
    """Emissive Tafel. Fuer die Kamera unsichtbar, fuer Glanz und Diffus da.

    Genau der Punkt aus dem Showroom: Eine Flaechenleuchte beleuchtet, aber
    sie steht nicht IM Bild des Metalls. Was im Stahl zu sehen ist, muss ein
    Koerper sein, den ein Glanzstrahl treffen kann.
    """
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_grid(bm, x_segments=1, y_segments=1, size=0.5)
    bmesh.ops.scale(bm, vec=Vector((groesse[0], groesse[1], 1.0)), verts=bm.verts)
    bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new(name, me)
    o.location = ort
    d = Vector(blick) - Vector(ort)
    o.rotation_euler = d.to_track_quat('-Z', 'Y').to_euler()
    m, nt, b = neu_material('M_' + name)
    for n in list(nt.nodes):
        if n.type == 'BSDF_PRINCIPLED':
            nt.nodes.remove(n)
    em = nt.nodes.new('ShaderNodeEmission')
    t = farbe_k
    if t >= 7000:   em.inputs['Color'].default_value = (0.86, 0.92, 1.00, 1.0)
    elif t >= 6000: em.inputs['Color'].default_value = (0.97, 0.98, 1.00, 1.0)
    else:           em.inputs['Color'].default_value = (1.00, 0.93, 0.84, 1.0)
    em.inputs['Strength'].default_value = staerke
    aus = None
    for n in nt.nodes:
        if n.type == 'OUTPUT_MATERIAL':
            aus = n
    nt.links.new(em.outputs['Emission'], aus.inputs['Surface'])
    me.materials.append(m)
    bpy.context.collection.objects.link(o)
    o.visible_camera = False      # nicht im Bild
    o.visible_glossy = True       # aber im Stahl
    o.visible_diffuse = True
    o.visible_shadow = False
    return o


softbox('Softbox_L', (-2.60, -1.40, 2.35), (0, 0, 0.60), (3.0, 2.0), 5600, 14.0)
softbox('Softbox_R', ( 2.95, -0.55, 1.95), (0, 0, 0.55), (2.2, 2.2), 6500, 5.0)
softbox('Softbox_O', ( 0.30,  0.90, 3.05), (0, 0, 0.72), (3.4, 1.6), 6000, 8.0)
# Streifen dicht ueber dem Boden: gibt der Traverse und den Fuessen eine Kante
softbox('Softbox_K', (-0.20, -2.30, 0.22), (0, 0, 0.16), (2.8, 0.35), 7200, 9.0)

welt = bpy.data.worlds.new('Studio')
szene.world = welt
welt.use_nodes = True
for n in welt.node_tree.nodes:
    if n.type == 'BACKGROUND':
        n.inputs['Color'].default_value = (0.038, 0.042, 0.050, 1.0)
        n.inputs['Strength'].default_value = 1.0

# ------------------------------------------------------------------- Kamera
kd = bpy.data.cameras.new('Kamera')
kd.lens = 50.0
kd.sensor_width = 36.0
kd.dof.use_dof = True
kd.dof.aperture_fstop = 3.5      # war 2.8 -- das Gestell soll noch lesbar sein
kd.dof.aperture_blades = 9
kd.dof.aperture_rotation = math.radians(7)
kam = bpy.data.objects.new('Kamera', kd)
kam.location = (1.95, -2.75, 1.22)
ziel = Vector((0.0, 0.0, 0.64))
kam.rotation_euler = (ziel - Vector(kam.location)).to_track_quat('-Z', 'Y').to_euler()
bpy.context.collection.objects.link(kam)
szene.camera = kam
kd.dof.focus_distance = (ziel - Vector(kam.location)).length

# ------------------------------------------------------------------ Rendern
c = szene.cycles
c.samples = 160
c.use_adaptive_sampling = True
c.adaptive_threshold = 0.010
c.use_denoising = True
try:
    c.denoiser = 'OPENIMAGEDENOISE'
    c.denoising_input_passes = 'RGB_ALBEDO_NORMAL'
    c.denoising_prefilter = 'ACCURATE'
except Exception:
    pass
c.blur_glossy = 0.4
c.glossy_bounces = 8

try:
    vor = bpy.context.preferences.addons['cycles'].preferences
    gewaehlt = None
    for art in ('OPTIX', 'CUDA', 'HIP', 'ONEAPI'):
        try:
            vor.compute_device_type = art
            vor.get_devices()
            if any(d.type == art for d in vor.devices):
                gewaehlt = art; break
        except Exception:
            continue
    if gewaehlt:
        for d in vor.devices:
            d.use = (d.type == gewaehlt)
        c.device = 'GPU'
        print('[TISCH2] Rechenweg: ' + gewaehlt)
    else:
        c.device = 'CPU'; print('[TISCH2] Rechenweg: CPU')
except Exception as e:
    c.device = 'CPU'; print('[TISCH2] Rechenweg: CPU (' + str(e) + ')')

szene.render.resolution_x = 1600
szene.render.resolution_y = 1000
szene.render.resolution_percentage = 60
szene.view_settings.view_transform = 'AgX'
szene.view_settings.look = 'AgX - Medium High Contrast'
szene.render.filepath = ZIEL_BILD

# --------------------------------------------- Messen, diesmal nach dem Update
bpy.context.view_layer.update()      # <- der Fehler aus Durchgang 1


def welt_masse(o):
    ecken = [o.matrix_world @ Vector(k) for k in o.bound_box]
    xs = [p.x for p in ecken]; ys = [p.y for p in ecken]; zs = [p.z for p in ecken]
    return (max(xs) - min(xs), max(ys) - min(ys), max(zs) - min(zs), min(zs), max(zs))


print('[TISCH2] --- Masspruefung (nach view_layer.update) ---')
for o in teile:
    b_, t_, h_, z0, z1 = welt_masse(o)
    print('[TISCH2] %-14s  B %.3f  T %.3f  H %.3f   z %.3f..%.3f' % (o.name, b_, t_, h_, z0, z1))

bpy.ops.wm.save_as_mainfile(filepath=ZIEL_BLEND)
print('[TISCH2] gespeichert: ' + ZIEL_BLEND)
bpy.ops.render.render(write_still=True)
print('[TISCH2] Bild: ' + ZIEL_BILD)
