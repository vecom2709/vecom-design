# -*- coding: utf-8 -*-
"""villa_fotoreal.py -- die Schicht, die nur die gerechneten Ruhebilder bekommen.

ANLASS (22.09.2026, Phase 2 "Vecom Experience", Uwe: "Umsetzen, aber
fotorealistisch")

Der Reality Check der Cycles-Vorlage vom selben Tag nannte die Stellen, an
denen das Bild sich als gerechnet verraet -- nicht das Haus, sondern alles
drumherum:

  1. Das Land. Eine Wiese in einem Gruen bis zum Horizont. Im September
     ist Sizilien ausserhalb bewaesserter Gaerten goldbraun: trockenes
     Gras, dunkle Macchia-Inseln, rote Erde, Olivenhaine in Reihen. Ein
     gruener Horizont sagt "Rechner" oder "Irland", nicht "Aragona".
  2. Leere Ferne. Ein Huegelland ohne einen einzigen Baum gibt es nicht.
  3. Die Zypressen. Vier glatte, verbeulte Spindeln haben eine glatte
     Silhouette. Eine echte Zypresse franst an jeder Kante aus.

Diese Schicht fasst weder das Unreal-Material noch das Netz fuer den
Browser an -- sie laeuft nur in lauf_ruhebilder.py, NACH dem Aufbau.
Dadurch bleibt die Kalibrierung Cycles/Unreal (#103) unberuehrt.
"""
import bpy
import math
import random
from mathutils import Matrix, Vector, noise

MITTE = Vector((9.0, 5.5, 0.0))     # Mitte des Erdgeschosses (villa.py)


def _knoten(nt, art, x=0, y=0):
    n = nt.nodes.new(art)
    n.location = (x, y)
    return n


# Die Trockenmauer (23.09.2026). Uwe sah im Garten- und im Wohnraumbild eine
# harte, gerade Kante zwischen gruenem Rasen und goldbraunem Land. Gemessen
# (villa_mess_rand.py): Das Gelaende ist auf 130 m fast eben (+-1 m), die
# Kante ist also keine Kuppe. Der Uebergang von 36 auf 92 m war zwar weich --
# aber aus 2 m Augenhoehe liegen 36 m und 92 m nur 1,9 und 0,8 Grad unter
# dem Horizont. Ein 56 m breiter Verlauf schrumpft so auf 1,1 Grad, und das
# liest sich als Strich mit dem Lineal. Auf Sizilien endet ein bewaesserter
# Garten an einer Grenze, die man sieht: am Muretto a secco. Also dieselbe
# Grenze fuer Mauer UND Rasen -- ein abgerundetes Rechteck um das Grundstueck.
MAUER_AN = False
MAUER_MITTE = (12.5, 9.0)       # Mitte des Grundstuecks
MAUER_HALB = (25.5, 29.0)       # halbe Ausdehnung: x -13..38, y -20..38
MAUER_RUND = 7.0                # Eckradius
MAUER_TOR = (0.8, 5.6)          # Einfahrt in der Suedmauer (x von .. bis)


def _maske_mauer(nt, x=-900, y=300, start=0.65, ende=3.4):
    """Vorzeichenbehafteter Abstand zur Mauerlinie (innen negativ), daraus
    0 = Garten, 1 = trockenes Land. Die Grenze beginnt erst 0,65 m ausserhalb
    der Mauermitte (Aussenseite der Mauer 0,31 m) und franst 3 m weit aus --
    direkt hinter einer Mauer sieht man von innen ohnehin nichts."""
    cx, cy = MAUER_MITTE
    bx, by = MAUER_HALB
    r = MAUER_RUND
    geo = _knoten(nt, 'ShaderNodeNewGeometry', x, y)
    flach = _knoten(nt, 'ShaderNodeVectorMath', x + 160, y)
    flach.operation = 'MULTIPLY'
    flach.inputs[1].default_value = (1.0, 1.0, 0.0)
    nt.links.new(geo.outputs['Position'], flach.inputs[0])
    rel = _knoten(nt, 'ShaderNodeVectorMath', x + 320, y)
    rel.operation = 'SUBTRACT'
    rel.inputs[1].default_value = (cx, cy, 0.0)
    nt.links.new(flach.outputs[0], rel.inputs[0])
    ab = _knoten(nt, 'ShaderNodeVectorMath', x + 480, y)
    ab.operation = 'ABSOLUTE'
    nt.links.new(rel.outputs[0], ab.inputs[0])
    q = _knoten(nt, 'ShaderNodeVectorMath', x + 640, y)
    q.operation = 'SUBTRACT'
    q.inputs[1].default_value = (bx - r, by - r, 0.0)
    nt.links.new(ab.outputs[0], q.inputs[0])
    mx = _knoten(nt, 'ShaderNodeVectorMath', x + 800, y + 120)
    mx.operation = 'MAXIMUM'
    mx.inputs[1].default_value = (0.0, 0.0, 0.0)
    nt.links.new(q.outputs[0], mx.inputs[0])
    laenge = _knoten(nt, 'ShaderNodeVectorMath', x + 960, y + 120)
    laenge.operation = 'LENGTH'
    nt.links.new(mx.outputs[0], laenge.inputs[0])
    sep = _knoten(nt, 'ShaderNodeSeparateXYZ', x + 800, y - 120)
    nt.links.new(q.outputs[0], sep.inputs[0])
    gr = _knoten(nt, 'ShaderNodeMath', x + 960, y - 120)
    gr.operation = 'MAXIMUM'
    nt.links.new(sep.outputs[0], gr.inputs[0])
    nt.links.new(sep.outputs[1], gr.inputs[1])
    kl = _knoten(nt, 'ShaderNodeMath', x + 1120, y - 120)
    kl.operation = 'MINIMUM'
    kl.inputs[1].default_value = 0.0
    nt.links.new(gr.outputs[0], kl.inputs[0])
    summe = _knoten(nt, 'ShaderNodeMath', x + 1280, y)
    summe.operation = 'ADD'
    nt.links.new(laenge.outputs['Value'], summe.inputs[0])
    nt.links.new(kl.outputs[0], summe.inputs[1])
    rausch = _knoten(nt, 'ShaderNodeTexNoise', x + 960, y - 320)
    rausch.inputs['Scale'].default_value = 0.22
    rausch.inputs['Detail'].default_value = 3.0
    nt.links.new(geo.outputs['Position'], rausch.inputs['Vector'])
    zack = _knoten(nt, 'ShaderNodeMath', x + 1120, y - 320)
    zack.operation = 'MULTIPLY_ADD'
    zack.inputs[1].default_value = -1.6
    zack.inputs[2].default_value = 0.8 - r
    nt.links.new(rausch.outputs['Fac'], zack.inputs[0])
    sdf = _knoten(nt, 'ShaderNodeMath', x + 1440, y)
    sdf.operation = 'ADD'
    nt.links.new(summe.outputs[0], sdf.inputs[0])
    nt.links.new(zack.outputs[0], sdf.inputs[1])
    karte = _knoten(nt, 'ShaderNodeMapRange', x + 1600, y)
    karte.interpolation_type = 'SMOOTHSTEP'
    karte.inputs['From Min'].default_value = start
    karte.inputs['From Max'].default_value = ende
    nt.links.new(sdf.outputs[0], karte.inputs['Value'])
    return karte.outputs['Result'], geo


def _maske_garten(nt, innen=36.0, aussen=92.0, x=-900, y=300):
    """0 im bewaesserten Garten, 1 im trockenen Land -- mit ausgefranster
    Grenze. Weltkoordinaten, damit Gelaende UND Halme dieselbe Grenze haben.
    Mit Trockenmauer ist die Mauer die Grenze (siehe MAUER_AN)."""
    if MAUER_AN:
        return _maske_mauer(nt, x, y)
    geo = _knoten(nt, 'ShaderNodeNewGeometry', x, y)
    ab = _knoten(nt, 'ShaderNodeVectorMath', x + 180, y)
    ab.operation = 'DISTANCE'
    ab.inputs[1].default_value = (MITTE.x, MITTE.y, 0.0)
    nt.links.new(geo.outputs['Position'], ab.inputs[0])
    rausch = _knoten(nt, 'ShaderNodeTexNoise', x + 180, y - 180)
    rausch.inputs['Scale'].default_value = 0.035
    rausch.inputs['Detail'].default_value = 4.0
    nt.links.new(geo.outputs['Position'], rausch.inputs['Vector'])
    versatz = _knoten(nt, 'ShaderNodeMath', x + 360, y - 120)
    versatz.operation = 'MULTIPLY_ADD'
    versatz.inputs[1].default_value = 40.0
    versatz.inputs[2].default_value = -20.0
    nt.links.new(rausch.outputs['Fac'], versatz.inputs[0])
    summe = _knoten(nt, 'ShaderNodeMath', x + 520, y)
    summe.operation = 'ADD'
    nt.links.new(ab.outputs['Value'], summe.inputs[0])
    nt.links.new(versatz.outputs[0], summe.inputs[1])
    karte = _knoten(nt, 'ShaderNodeMapRange', x + 680, y)
    karte.interpolation_type = 'SMOOTHSTEP'
    karte.inputs['From Min'].default_value = innen
    karte.inputs['From Max'].default_value = aussen
    nt.links.new(summe.outputs[0], karte.inputs['Value'])
    return karte.outputs['Result'], geo


def _mix_farbe(nt, a, b, fak, x, y):
    m = _knoten(nt, 'ShaderNodeMix', x, y)
    m.data_type = 'RGBA'
    if isinstance(a, tuple):
        m.inputs[6].default_value = a
    else:
        nt.links.new(a, m.inputs[6])
    if isinstance(b, tuple):
        m.inputs[7].default_value = b
    else:
        nt.links.new(b, m.inputs[7])
    nt.links.new(fak, m.inputs['Factor'])
    return m.outputs[2]


def _stufe(nt, wert, lage, breite, x, y):
    """Weiche Schwelle -- fuer Flecken, die als Flecken lesbar sind."""
    k = _knoten(nt, 'ShaderNodeMapRange', x, y)
    k.interpolation_type = 'SMOOTHSTEP'
    k.inputs['From Min'].default_value = lage - breite
    k.inputs['From Max'].default_value = lage + breite
    nt.links.new(wert, k.inputs['Value'])
    return k.outputs['Result']


def land_sizilien():
    """Rasen im Garten bleibt, draussen wird Land daraus.

    Albedo gemessen an Luftbildern statt geraten: trockenes Gras 0,25 bis
    0,32, Macchia 0,06 bis 0,09, Terra rossa 0,16 bis 0,22.
    """
    mats = set()
    for o in bpy.data.objects:
        if o.name.startswith('p08_gelaende') and o.type == 'MESH':
            for s in o.material_slots:
                if s.material:
                    mats.add(s.material)
    n = 0
    for mat in mats:
        nt = mat.node_tree
        aus = next((k for k in nt.nodes if k.type == 'OUTPUT_MATERIAL'), None)
        if aus is None or not aus.inputs['Surface'].is_linked:
            continue
        garten = aus.inputs['Surface'].links[0].from_socket
        maske, geo = _maske_garten(nt)
        pos = geo.outputs['Position']
        r_gross = _knoten(nt, 'ShaderNodeTexNoise', -900, -300)
        r_gross.inputs['Scale'].default_value = 0.012
        r_gross.inputs['Detail'].default_value = 5.0
        r_mittel = _knoten(nt, 'ShaderNodeTexNoise', -900, -500)
        r_mittel.inputs['Scale'].default_value = 0.09
        r_mittel.inputs['Detail'].default_value = 6.0
        r_macchia = _knoten(nt, 'ShaderNodeTexNoise', -900, -700)
        r_macchia.inputs['Scale'].default_value = 0.045
        r_macchia.inputs['Detail'].default_value = 7.0
        r_macchia.inputs['Roughness'].default_value = 0.68
        r_erde = _knoten(nt, 'ShaderNodeTexNoise', -900, -900)
        r_erde.inputs['Scale'].default_value = 0.03
        r_erde.inputs['Detail'].default_value = 3.0
        r_fein = _knoten(nt, 'ShaderNodeTexNoise', -900, -1100)
        r_fein.inputs['Scale'].default_value = 2.5
        r_fein.inputs['Detail'].default_value = 8.0
        for r in (r_gross, r_mittel, r_macchia, r_erde, r_fein):
            nt.links.new(pos, r.inputs['Vector'])
        stroh = _mix_farbe(nt, (0.205, 0.160, 0.082, 1.0), (0.330, 0.262, 0.142, 1.0), r_gross.outputs['Fac'], -500, -300)
        stroh = _mix_farbe(nt, stroh, (0.150, 0.132, 0.070, 1.0), _stufe(nt, r_mittel.outputs['Fac'], 0.60, 0.08, -700, -500), -300, -400)
        macchia = _stufe(nt, r_macchia.outputs['Fac'], 0.62, 0.035, -700, -700)
        farbe = _mix_farbe(nt, stroh, (0.050, 0.060, 0.030, 1.0), macchia, -100, -500)
        erde = _stufe(nt, r_erde.outputs['Fac'], 0.66, 0.03, -700, -900)
        farbe = _mix_farbe(nt, farbe, (0.215, 0.118, 0.066, 1.0), erde, 100, -600)
        land = _knoten(nt, 'ShaderNodeBsdfPrincipled', 300, -500)
        nt.links.new(farbe, land.inputs['Base Color'])
        land.inputs['Roughness'].default_value = 0.93
        bump = _knoten(nt, 'ShaderNodeBump', 100, -900)
        bump.inputs['Strength'].default_value = 0.6
        bump.inputs['Distance'].default_value = 0.05
        nt.links.new(r_fein.outputs['Fac'], bump.inputs['Height'])
        nt.links.new(bump.outputs['Normal'], land.inputs['Normal'])
        misch = _knoten(nt, 'ShaderNodeMixShader', 520, 0)
        nt.links.new(maske, misch.inputs['Fac'])
        nt.links.new(garten, misch.inputs[1])
        nt.links.new(land.outputs[0], misch.inputs[2])
        nt.links.new(misch.outputs[0], aus.inputs['Surface'])
        n += 1
    # Die Halme draussen vertrocknen mit: sonst stuende gruenes Haar auf
    # goldbraunem Boden.
    halm = bpy.data.materials.get('Grashalm')
    if halm is not None:
        nt = halm.node_tree
        ziele = [k for k in nt.nodes if k.type in ('BSDF_PRINCIPLED', 'BSDF_TRANSLUCENT')]
        quelle = None
        for k in ziele:
            e = k.inputs.get('Base Color') or k.inputs.get('Color')
            if e is not None and e.is_linked:
                quelle = e.links[0].from_socket
                break
        if quelle is not None:
            maske, geo = _maske_garten(nt, innen=34.0, aussen=80.0)
            zuf = _knoten(nt, 'ShaderNodeHairInfo', -900, -400)
            trocken = _mix_farbe(nt, (0.30, 0.24, 0.12, 1.0), (0.42, 0.34, 0.17, 1.0), zuf.outputs['Random'], -500, -400)
            # Auch im Garten ein paar trockene Halme: im Spaetsommer ist
            # kein Rasen gleichmaessig gruen. Zehn Prozent, zufaellig.
            ein = _stufe(nt, zuf.outputs['Random'], 0.93, 0.02, -700, -600)
            summe = _knoten(nt, 'ShaderNodeMath', -300, -500)
            summe.operation = 'MAXIMUM'
            nt.links.new(maske, summe.inputs[0])
            nt.links.new(ein, summe.inputs[1])
            neu = _mix_farbe(nt, quelle, trocken, summe.outputs[0], -100, -400)
            for k in ziele:
                e = k.inputs.get('Base Color') or k.inputs.get('Color')
                if e is not None:
                    nt.links.new(neu, e)
    return n


def _olivenblob(name, samen, mat_laub, mat_stamm):
    """Ein Olivenbaum fuer die Ferne, gebaut aus Laubballen.

    22.09.2026: Die erste Fassung war eine verbeulte Kugel auf einem
    Zylinder. Im Testbild standen daraus graugruene Pilze mit hellen
    Holzstielen -- glatt schattiert, ohne Luecken, ohne Eigenschatten.
    Eine Olive ist das Gegenteil: eine lockere, geteilte Krone, durch die
    man den Himmel sieht, und ein kurzer, dunkler, oft zwei- bis
    dreistaemmiger Stamm. Also jetzt rund 220 kleine, unregelmaessige
    Ballen in einem flachen Ellipsoid, bevorzugt aussen, mit Luecken dort,
    wo ein Rauschfeld unter null faellt -- und zwei bis drei schraege Aeste
    statt eines Pfahls. (Ein Zwischenstand mit 90 Ballen von 0,17 bis 0,30
    sah aus wie Popcorn: Olivenlaub ist feiner, die Ballen mussten halb so
    gross sein.) Fuenf Formen, von allen Baeumen geteilt: Cycles
    instanziert sie, das kostet fast nichts."""
    import bmesh
    from mathutils import Matrix
    rnd = random.Random(samen)
    bm = bmesh.new()
    mitte = Vector((0.0, 0.0, 1.30))
    ballen = 0
    versuche = 0
    while ballen < 220 and versuche < 9000:
        versuche += 1
        p = Vector((rnd.uniform(-1, 1), rnd.uniform(-1, 1), rnd.uniform(-1, 1)))
        r = p.length
        if r > 1.0 or r < 0.45:
            continue
        # Luecken: ein grobes Rauschfeld schneidet Loecher und Lappen in
        # die Krone. Ohne sie wird aus neunzig Ballen wieder eine Kugel.
        if noise.noise(p * 1.9 + Vector((samen * 0.13, 0.0, 0.0))) < -0.18:
            continue
        ort = mitte + Vector((p.x * 1.05, p.y * 1.05, p.z * 0.58))
        gr = rnd.uniform(0.09, 0.17)
        m = (Matrix.Translation(ort)
             @ Matrix.Rotation(rnd.uniform(0, 6.283), 4, 'Z')
             @ Matrix.Diagonal((gr * rnd.uniform(0.9, 1.25), gr * rnd.uniform(0.9, 1.25), gr * rnd.uniform(0.6, 0.85), 1.0)))
        erg = bmesh.ops.create_icosphere(bm, subdivisions=1, radius=1.0, matrix=m)
        for v in erg['verts']:
            v.co += (v.co - ort) * (noise.noise(v.co * 6.0) * 0.45)
        ballen += 1
    for f in bm.faces:
        f.material_index = 0
        f.smooth = True
    # Zwei bis drei Aeste vom Boden schraeg in die Krone.
    for k in range(rnd.choice((2, 3, 3))):
        w = rnd.uniform(0, 6.283)
        neig = rnd.uniform(0.20, 0.42)
        laenge = rnd.uniform(1.15, 1.45)
        r0 = rnd.uniform(0.09, 0.13)
        m = (Matrix.Rotation(w, 4, 'Z') @ Matrix.Rotation(neig, 4, 'X')
             @ Matrix.Translation((0.0, 0.0, laenge * 0.5)))
        erg = bmesh.ops.create_cone(bm, cap_ends=False, segments=6, radius1=r0,
                                    radius2=r0 * 0.55, depth=laenge, matrix=m)
        for v in erg['verts']:
            v.co.x += noise.noise(v.co * 3.0 + Vector((k, 0, 0))) * 0.04
            v.co.y += noise.noise(v.co * 3.0 + Vector((0, k, 0))) * 0.04
        for f in {f for v in erg['verts'] for f in v.link_faces}:
            f.material_index = 1
            f.smooth = True
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(mat_laub)
    me.materials.append(mat_stamm)
    return me


def _olivenrinde():
    """Olivenrinde: dunkles Graubraun, rau. Vorher stand hier das helle
    Lamellenholz der Terrasse -- aus 100 m Entfernung wurden daraus
    leuchtende Stiele unter jedem Baum."""
    mat = bpy.data.materials.get('Olive_rinde') or bpy.data.materials.new('Olive_rinde')
    mat.use_nodes = True
    b = mat.node_tree.nodes.get('Principled BSDF')
    if b is not None:
        b.inputs['Base Color'].default_value = (0.062, 0.052, 0.043, 1.0)
        b.inputs['Roughness'].default_value = 0.92
    return mat


def ferne_baeume(anzahl_einzeln=420, haine=14, samen=909):
    """Olivenhaine in Reihen (6 x 6 m, wie gepflanzt) und einzelne Baeume,
    auf das Gelaende gesetzt per Strahl von oben."""
    rnd = random.Random(samen)
    laub = bpy.data.materials.get('Olive_fern') or bpy.data.materials.new('Olive_fern')
    laub.use_nodes = True
    nt = laub.node_tree
    for k in list(nt.nodes):
        nt.nodes.remove(k)
    aus = _knoten(nt, 'ShaderNodeOutputMaterial', 400, 0)
    b = _knoten(nt, 'ShaderNodeBsdfPrincipled', 150, 0)
    info = _knoten(nt, 'ShaderNodeObjectInfo', -400, 0)
    # Olivenlaub ist graugruen, die Blattunterseite silbrig. Zwei Streuungen:
    # je Baum (Object Info) und je Ballen (Rauschen in Objektkoordinaten,
    # grob genug, dass ein Ballen eine Farbe hat). Der Schimmer (Sheen)
    # ist die Unterseite, die der Wind nach oben dreht.
    f = _mix_farbe(nt, (0.046, 0.058, 0.034, 1.0), (0.100, 0.112, 0.074, 1.0), info.outputs['Random'], -150, 0)
    koord = _knoten(nt, 'ShaderNodeTexCoord', -700, 200)
    streu = _knoten(nt, 'ShaderNodeTexNoise', -500, 200)
    streu.inputs['Scale'].default_value = 2.2
    streu.inputs['Detail'].default_value = 1.0
    nt.links.new(koord.outputs['Object'], streu.inputs['Vector'])
    f2 = _mix_farbe(nt, (0.040, 0.050, 0.030, 1.0), (0.118, 0.128, 0.092, 1.0), streu.outputs['Fac'], -150, 200)
    misch = _knoten(nt, 'ShaderNodeMix', 0, 100)
    misch.data_type = 'RGBA'
    misch.inputs['Factor'].default_value = 0.5
    nt.links.new(f, misch.inputs[6])
    nt.links.new(f2, misch.inputs[7])
    nt.links.new(misch.outputs[2], b.inputs['Base Color'])
    b.inputs['Roughness'].default_value = 0.78
    try:
        b.inputs['Sheen Weight'].default_value = 0.35
        b.inputs['Sheen Tint'].default_value = (0.62, 0.66, 0.60, 1.0)
        b.inputs['Sheen Roughness'].default_value = 0.45
    except KeyError:
        pass
    r = _knoten(nt, 'ShaderNodeTexNoise', -400, -250)
    r.inputs['Scale'].default_value = 14.0
    r.inputs['Detail'].default_value = 6.0
    bump = _knoten(nt, 'ShaderNodeBump', -150, -250)
    bump.inputs['Strength'].default_value = 0.8
    nt.links.new(r.outputs['Fac'], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])
    nt.links.new(b.outputs[0], aus.inputs[0])
    stamm = _olivenrinde()
    formen = [_olivenblob('olive_fern_%d' % i, 31 + i * 7, laub, stamm) for i in range(5)]
    sammlung = bpy.data.collections.new('fotoreal_ferne')
    bpy.context.scene.collection.children.link(sammlung)
    dg = bpy.context.evaluated_depsgraph_get()
    sz = bpy.context.scene
    boden = {o.name for o in bpy.data.objects if o.name.startswith('p08_gelaende')}

    def setzen(x, y, groesse):
        if (Vector((x, y, 0)) - MITTE).length < 62.0:
            return False
        treffer, ort, _n, _i, obj, _m = sz.ray_cast(dg, Vector((x, y, 800.0)), Vector((0, 0, -1)), distance=2000.0)
        if not treffer or obj is None or obj.name not in boden:
            return False
        ob = bpy.data.objects.new('olive_fern', rnd.choice(formen))
        ob.location = (x, y, ort.z - 0.15)
        s = groesse * rnd.uniform(0.85, 1.15)
        ob.scale = (s * rnd.uniform(0.9, 1.1), s * rnd.uniform(0.9, 1.1), s * rnd.uniform(0.85, 1.05))
        ob.rotation_euler = (0, 0, rnd.uniform(0, 6.283))
        sammlung.objects.link(ob)
        return True

    n = 0
    for h in range(haine):
        r_ = rnd.uniform(90.0, 700.0)
        w = rnd.uniform(0, 6.283)
        cx, cy = MITTE.x + math.cos(w) * r_, MITTE.y + math.sin(w) * r_
        dreh = rnd.uniform(0, math.pi)
        breite, tiefe = rnd.uniform(40, 110), rnd.uniform(40, 90)
        ca, sa = math.cos(dreh), math.sin(dreh)
        for i in range(int(breite // 6)):
            for j in range(int(tiefe // 6)):
                if rnd.random() < 0.08:
                    continue
                lx, ly = i * 6 - breite / 2 + rnd.uniform(-0.6, 0.6), j * 6 - tiefe / 2 + rnd.uniform(-0.6, 0.6)
                if setzen(cx + lx * ca - ly * sa, cy + lx * sa + ly * ca, rnd.uniform(1.7, 2.3)):
                    n += 1
    for _ in range(anzahl_einzeln):
        r_ = 70.0 + (rnd.random() ** 1.6) * 1600.0
        w = rnd.uniform(0, 6.283)
        if setzen(MITTE.x + math.cos(w) * r_, MITTE.y + math.sin(w) * r_, rnd.uniform(1.8, 3.0)):
            n += 1
    return n


def _laubbuschel(name, samen):
    """Ein Zweigbueschel als unregelmaessiger, flacher Klumpen, Radius 1.

    Warum Instanzen statt Haare: Der erste Versuch am 22.09.2026 lief mit
    Haarpartikeln (0,20 m). Im Bild standen daraus meterlange, dunkle
    Pinselhaare um jede Krone -- die Kinder-Rauheit und die Interpolation
    rechnen nicht in Metern, und eine Zypresse ist ohnehin kein Fell,
    sondern eine Masse aus faustgrossen Zweigbuescheln. Genau die bilden
    wir nach: Tausende kleine Klumpen auf der Spindel machen die Kante
    knotig und lassen Licht in die Luecken fallen."""
    import bmesh
    rnd = random.Random(samen)
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_icosphere(bm, subdivisions=2, radius=1.0)
    for v in bm.verts:
        p = v.co * 2.3 + Vector((samen * 0.37, 0.0, 0.0))
        d = noise.noise(p) * 0.28 + rnd.uniform(-0.06, 0.06)
        v.co *= (1.0 + d)
        v.co.z *= 0.62
    bm.to_mesh(me)
    bm.free()
    for poly in me.polygons:
        poly.use_smooth = True
    mat = bpy.data.materials.get('Zypresse')
    if mat is not None:
        me.materials.append(mat)
    ob = bpy.data.objects.new(name, me)
    return ob


def zypressen_laub(dichte=140.0, groesse=0.13):
    """Zweigbueschel auf jede Spindel: die Silhouette wird knotig.

    Dichte je Quadratmeter Spindelflaeche. Die Spindel wird dafuer quer
    um 14 % eingezogen, weil die Bueschel mit ihrem halben Durchmesser
    nach aussen stehen -- sonst waechst die Zypresse um 25 cm in die
    Breite, und genau das Verhaeltnis Hoehe zu Breite ist der Baum."""
    sz = bpy.context.scene
    quellen = bpy.data.collections.get('fotoreal_quellen')
    if quellen is None:
        quellen = bpy.data.collections.new('fotoreal_quellen')
        sz.collection.children.link(quellen)
    # Die Vorlage liegt 500 m unter dem Gelaende und ist fuer den Render
    # ausgeblendet: doppelt, weil ein sichtbarer Klumpen im Bild schlimmer
    # waere als keiner. Die Partikel lesen nur Drehung und Groesse.
    quellen.hide_render = True
    vorlage = _laubbuschel('Zypressenbueschel', 11)
    quellen.objects.link(vorlage)
    vorlage.location = (0.0, 0.0, -500.0)
    n = 0
    for o in list(bpy.data.objects):
        if o.type != 'MESH' or 'zypresse' not in o.name or '_b' not in o.name:
            continue
        for v in o.data.vertices:
            v.co.x *= 0.86
            v.co.y *= 0.86
        o.data.update()
        sx, sy, szz = o.scale
        flaeche = sum(p.area for p in o.data.polygons) * abs(sx * sy)
        mod = o.modifiers.new('Laub', 'PARTICLE_SYSTEM')
        ps = o.particle_systems[-1]
        ps.seed = 17 + n * 31
        s = ps.settings
        s.name = 'Zypressenlaub_' + o.name
        s.type = 'EMITTER'
        s.count = max(200, int(flaeche * dichte))
        s.frame_start = float(sz.frame_current)
        s.frame_end = float(sz.frame_current)
        s.lifetime = 100000.0
        s.emit_from = 'FACE'
        s.distribution = 'RAND'
        s.use_even_distribution = True
        s.physics_type = 'NO'
        s.normal_factor = 0.0
        s.show_unborn = True
        s.use_dead = True
        s.render_type = 'OBJECT'
        s.instance_object = vorlage
        s.particle_size = groesse
        s.size_random = 0.55
        s.use_rotations = True
        s.rotation_mode = 'NOR'
        s.phase_factor_random = 2.0
        s.rotation_factor_random = 0.30
        mod.show_viewport = False
        n += 1
    return n


def _kieselstein(name):
    """Ein Kiesel: flach, rund geschliffen. Unterteilung 2 und glatt
    schattiert -- die 420 Steine aus villa_aussen sind Ikosaeder der Stufe 1
    mit gezerrten Ecken und lasen sich im Ankunftsbild als kleine Pyramiden."""
    import bmesh
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_icosphere(bm, subdivisions=2, radius=1.0)
    for v in bm.verts:
        v.co.z *= 0.52
        v.co *= 1.0 + noise.noise(v.co * 2.2) * 0.16
    bm.to_mesh(me)
    bm.free()
    for p in me.polygons:
        p.use_smooth = True
    return me


def kies_vorplatz(dichte=1800.0):
    """Der Vorplatz als echte Kiesflaeche.

    Vorher: eine glatte Platte mit dem Material 'Kies' und 420 verstreuten
    Einzelsteinen -- sechs je Quadratmeter. Im Ankunftsbild war das kein
    Kies, sondern eine Betonplatte mit Kruemeln. Kies ist eine geschlossene
    Schicht aus Steinen, die sich gegenseitig beschatten. Also: die
    Oberseite der Platte als eigener Streuer, darauf rund 1.800 Steine je
    Quadratmeter (5 bis 14 mm Radius), jeder mit eigener Farbe; die Platte
    darunter wird dunkler und koernig, damit die Luecken nach feinerem Kies
    aussehen und nicht nach Beton. Die alten Einzelsteine gehen aus dem Bild."""
    import bmesh
    kies = bpy.data.materials.get('Kies')
    if kies is None:
        return 0
    for o in bpy.data.objects:
        if o.name.startswith('kiesel_'):
            o.hide_render = True
    # Grundschicht: dunkler, koernig
    kies.use_nodes = True
    nt = kies.node_tree
    b = next((k for k in nt.nodes if k.type == 'BSDF_PRINCIPLED'), None)
    if b is not None:
        vor = _knoten(nt, 'ShaderNodeTexVoronoi', -500, 0)
        vor.inputs['Scale'].default_value = 260.0
        koord = _knoten(nt, 'ShaderNodeTexCoord', -700, 0)
        nt.links.new(koord.outputs['Object'], vor.inputs['Vector'])
        f = _mix_farbe(nt, (0.115, 0.108, 0.097, 1.0), (0.215, 0.203, 0.184, 1.0), vor.outputs['Color'], -250, 0)
        nt.links.new(f, b.inputs['Base Color'])
        b.inputs['Roughness'].default_value = 0.9
        bump = _knoten(nt, 'ShaderNodeBump', -250, -250)
        bump.inputs['Strength'].default_value = 0.7
        bump.inputs['Distance'].default_value = 0.004
        nt.links.new(vor.outputs['Distance'], bump.inputs['Height'])
        nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])
    # Steinmaterial: je Stein eine Farbe aus Kalkstein-Grau bis Sand
    stein = bpy.data.materials.get('Kies_Stein') or bpy.data.materials.new('Kies_Stein')
    stein.use_nodes = True
    snt = stein.node_tree
    sb = next((k for k in snt.nodes if k.type == 'BSDF_PRINCIPLED'), None)
    info = _knoten(snt, 'ShaderNodeObjectInfo', -600, 0)
    rampe = _knoten(snt, 'ShaderNodeValToRGB', -350, 0)
    el = rampe.color_ramp.elements
    el[0].position = 0.0; el[0].color = (0.17, 0.165, 0.155, 1.0)
    el[1].position = 1.0; el[1].color = (0.46, 0.43, 0.38, 1.0)
    mitte = el.new(0.55); mitte.color = (0.31, 0.295, 0.27, 1.0)
    snt.links.new(info.outputs['Random'], rampe.inputs['Fac'])
    snt.links.new(rampe.outputs['Color'], sb.inputs['Base Color'])
    sb.inputs['Roughness'].default_value = 0.72
    vorlage_me = _kieselstein('kieselstein')
    vorlage_me.materials.append(stein)
    quellen = bpy.data.collections.get('fotoreal_quellen')
    if quellen is None:
        quellen = bpy.data.collections.new('fotoreal_quellen')
        bpy.context.scene.collection.children.link(quellen)
    quellen.hide_render = True
    vorlage = bpy.data.objects.new('Kieselstein', vorlage_me)
    quellen.objects.link(vorlage)
    vorlage.location = (0.0, 0.0, -500.0)
    n = 0
    for o in list(bpy.data.objects):
        if o.type != 'MESH' or o.name.startswith(('kiesel', 'Kiesel', 'kies_streuer')):
            continue
        if not any(m is not None and m.name == 'Kies' for m in o.data.materials):
            continue
        # Nur die Oberseite streut: eine Kopie ohne Seiten und Unterseite,
        # 3 mm angehoben, selbst unsichtbar.
        bm = bmesh.new()
        bm.from_mesh(o.data)
        bm.transform(o.matrix_world)
        weg = [f for f in bm.faces if f.normal.z < 0.9]
        bmesh.ops.delete(bm, geom=weg, context='FACES')
        if not bm.faces:
            bm.free()
            continue
        for v in bm.verts:
            v.co.z += 0.003
        me = bpy.data.meshes.new('kies_streuer_%d' % n)
        bm.to_mesh(me)
        bm.free()
        flaeche = sum(p.area for p in me.polygons)
        st = bpy.data.objects.new('kies_streuer_%d' % n, me)
        bpy.context.scene.collection.objects.link(st)
        st.show_instancer_for_render = False
        mod = st.modifiers.new('Kies', 'PARTICLE_SYSTEM')
        ps = st.particle_systems[-1]
        ps.seed = 71 + n
        s = ps.settings
        s.type = 'EMITTER'
        s.count = int(flaeche * dichte)
        s.frame_start = s.frame_end = float(bpy.context.scene.frame_current)
        s.lifetime = 100000.0
        s.emit_from = 'FACE'
        s.distribution = 'RAND'
        s.use_even_distribution = True
        s.physics_type = 'NO'
        s.normal_factor = 0.0
        s.show_unborn = True
        s.use_dead = True
        s.render_type = 'OBJECT'
        s.instance_object = vorlage
        s.particle_size = 0.0095
        s.size_random = 0.6
        s.use_rotations = True
        s.rotation_mode = 'NOR'
        s.phase_factor_random = 2.0
        s.rotation_factor_random = 0.12
        mod.show_viewport = False
        n += 1
    return n


# --------------------------------------------------------------------------
# TROCKENMAUER
# --------------------------------------------------------------------------
def _mauerlinie(schritt=0.05):
    """Mittellinie der Mauer als Polylinie: Punkte, Tangenten, Laufmeter.
    Gegen den Uhrzeigersinn, Start an der Suedwestecke der Suedmauer."""
    cx, cy = MAUER_MITTE
    bx, by = MAUER_HALB
    r = MAUER_RUND
    teile = [
        ('g', (cx - bx + r, cy - by), (cx + bx - r, cy - by)),
        ('b', (cx + bx - r, cy - by + r), -90.0, 0.0),
        ('g', (cx + bx, cy - by + r), (cx + bx, cy + by - r)),
        ('b', (cx + bx - r, cy + by - r), 0.0, 90.0),
        ('g', (cx + bx - r, cy + by), (cx - bx + r, cy + by)),
        ('b', (cx - bx + r, cy + by - r), 90.0, 180.0),
        ('g', (cx - bx, cy + by - r), (cx - bx, cy - by + r)),
        ('b', (cx - bx + r, cy - by + r), 180.0, 270.0),
    ]
    pts = []
    for t in teile:
        if t[0] == 'g':
            a, b = Vector((*t[1], 0.0)), Vector((*t[2], 0.0))
            n = max(2, int((b - a).length / schritt))
            pts += [a.lerp(b, i / n) for i in range(n)]
        else:
            m = Vector((*t[1], 0.0))
            n = max(2, int(math.radians(t[3] - t[2]) * r / schritt))
            for i in range(n):
                w = math.radians(t[2] + (t[3] - t[2]) * i / n)
                pts.append(m + Vector((math.cos(w), math.sin(w), 0.0)) * r)
    pts.append(pts[0].copy())
    s = [0.0]
    for i in range(1, len(pts)):
        s.append(s[-1] + (pts[i] - pts[i - 1]).length)
    return pts, s


def _stein_varianten(anzahl=28, samen=5150):
    """Bruchsteine: ein abgerundeter Quader, verbeult, oben und unten leicht
    abgeflacht (Lagerflaechen -- so werden sie geschichtet). Einheitsgroesse
    -1..1, skaliert wird beim Setzen."""
    import bmesh
    rnd = random.Random(samen)
    varianten = []
    for k in range(anzahl):
        bm = bmesh.new()
        bmesh.ops.create_cube(bm, size=2.0)
        bmesh.ops.subdivide_edges(bm, edges=bm.edges[:], cuts=3, use_grid_fill=True)
        ver = Vector((rnd.uniform(-50, 50), rnd.uniform(-50, 50), rnd.uniform(-50, 50)))
        rund = rnd.uniform(0.28, 0.45)
        kante = 1.0 - rund
        for v in bm.verts:
            p = v.co.copy()
            q = Vector((max(-kante, min(kante, p.x)), max(-kante, min(kante, p.y)), max(-kante, min(kante, p.z))))
            d = p - q
            if d.length > 1e-6:
                p = q + d.normalized() * rund
            nrm = p.normalized()
            grob = noise.noise(p * 0.9 + ver) * 0.16
            fein = noise.noise(p * 2.7 + ver * 1.3) * 0.05
            p += nrm * (grob + fein)
            # Lagerflaechen: oben und unten flach, aber nicht spiegelglatt
            p.z = max(-0.9, min(0.9, p.z)) + noise.noise(p * 3.3 + ver) * 0.02
            v.co = p
        bm.verts.ensure_lookup_table()
        co = [tuple(v.co) for v in bm.verts]
        fl = [tuple(vv.index for vv in f.verts) for f in bm.faces]
        bm.free()
        varianten.append((co, fl))
    return varianten


def _trockenstein_material():
    mat = bpy.data.materials.get('Trockenstein') or bpy.data.materials.new('Trockenstein')
    mat.use_nodes = True
    nt = mat.node_tree
    for k in list(nt.nodes):
        nt.nodes.remove(k)
    aus = _knoten(nt, 'ShaderNodeOutputMaterial', 900, 0)
    b = _knoten(nt, 'ShaderNodeBsdfPrincipled', 650, 0)
    nt.links.new(b.outputs[0], aus.inputs[0])
    geo = _knoten(nt, 'ShaderNodeNewGeometry', -1200, 0)
    stein = _knoten(nt, 'ShaderNodeAttribute', -1200, 300)
    stein.attribute_name = 'stein'
    boden = _knoten(nt, 'ShaderNodeAttribute', -1200, -500)
    boden.attribute_name = 'boden'
    # Sizilianischer Kalkstein und Kalkarenit, verwittert: grau bis ocker.
    # Albedo 0,28 bis 0,50 -- gemessen an Fotos von Muretti bei Agrigent,
    # nicht heller: frischer Kalk waere 0,6 und stuende wie Zucker im Bild.
    rampe = _knoten(nt, 'ShaderNodeValToRGB', -950, 300)
    el = rampe.color_ramp.elements
    # 23.09., nach der ersten Probe: zu hell und zu kalt -- die Mauer stand
    # grau-blaeulich vor dem goldenen Land. Waermer und mehr dunkle Steine.
    el[0].position = 0.0; el[0].color = (0.170, 0.155, 0.130, 1.0)
    el[1].position = 1.0; el[1].color = (0.430, 0.350, 0.225, 1.0)
    m1 = el.new(0.3); m1.color = (0.280, 0.255, 0.210, 1.0)
    m2 = el.new(0.65); m2.color = (0.360, 0.320, 0.250, 1.0)
    nt.links.new(stein.outputs['Fac'], rampe.inputs['Fac'])
    fleck = _knoten(nt, 'ShaderNodeTexNoise', -950, 60)
    fleck.inputs['Scale'].default_value = 2.8
    fleck.inputs['Detail'].default_value = 6.0
    nt.links.new(geo.outputs['Position'], fleck.inputs['Vector'])
    farbe = _mix_farbe(nt, rampe.outputs['Color'], (0.17, 0.16, 0.14, 1.0), _stufe(nt, fleck.outputs['Fac'], 0.66, 0.08, -750, 60), -550, 200)
    # Flechten: helle Krusten und dunkle Punkte, vor allem auf der Oberseite
    oben = _knoten(nt, 'ShaderNodeSeparateXYZ', -950, -150)
    nt.links.new(geo.outputs['Normal'], oben.inputs[0])
    obenw = _knoten(nt, 'ShaderNodeMapRange', -750, -150)
    obenw.inputs['From Min'].default_value = -0.2
    obenw.inputs['From Max'].default_value = 0.9
    nt.links.new(oben.outputs[2], obenw.inputs['Value'])
    fl1 = _knoten(nt, 'ShaderNodeTexNoise', -950, -300)
    fl1.inputs['Scale'].default_value = 11.0
    fl1.inputs['Detail'].default_value = 8.0
    fl1.inputs['Roughness'].default_value = 0.65
    nt.links.new(geo.outputs['Position'], fl1.inputs['Vector'])
    hell = _stufe(nt, fl1.outputs['Fac'], 0.64, 0.025, -750, -300)
    hellw = _knoten(nt, 'ShaderNodeMath', -550, -250)
    hellw.operation = 'MULTIPLY'
    nt.links.new(hell, hellw.inputs[0])
    nt.links.new(obenw.outputs['Result'], hellw.inputs[1])
    farbe = _mix_farbe(nt, farbe, (0.56, 0.55, 0.48, 1.0), hellw.outputs[0], -350, 150)
    fl2 = _knoten(nt, 'ShaderNodeTexNoise', -950, -450)
    fl2.inputs['Scale'].default_value = 23.0
    fl2.inputs['Detail'].default_value = 4.0
    nt.links.new(geo.outputs['Position'], fl2.inputs['Vector'])
    farbe = _mix_farbe(nt, farbe, (0.055, 0.055, 0.045, 1.0), _stufe(nt, fl2.outputs['Fac'], 0.70, 0.02, -750, -450), -200, 100)
    # Unten: Erde, Spritzwasser, ein Hauch Moos auf der Nordseite waere zu viel
    unten = _knoten(nt, 'ShaderNodeMapRange', -750, -600)
    unten.inputs['From Min'].default_value = 0.45
    unten.inputs['From Max'].default_value = 0.0
    nt.links.new(boden.outputs['Fac'], unten.inputs['Value'])
    unten2 = _knoten(nt, 'ShaderNodeMath', -550, -600)
    unten2.operation = 'MULTIPLY'
    unten2.inputs[1].default_value = 0.55
    nt.links.new(unten.outputs['Result'], unten2.inputs[0])
    farbe = _mix_farbe(nt, farbe, (0.13, 0.11, 0.08, 1.0), unten2.outputs[0], -50, 50)
    nt.links.new(farbe, b.inputs['Base Color'])
    rau = _knoten(nt, 'ShaderNodeMapRange', 300, -200)
    rau.inputs['To Min'].default_value = 0.82
    rau.inputs['To Max'].default_value = 0.96
    nt.links.new(fleck.outputs['Fac'], rau.inputs['Value'])
    nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])
    for n_, w in (('Specular IOR Level', 0.45),):
        if b.inputs.get(n_) is not None:
            b.inputs[n_].default_value = w
    rb = _knoten(nt, 'ShaderNodeTexNoise', 100, -500)
    rb.inputs['Scale'].default_value = 21.0
    rb.inputs['Detail'].default_value = 10.0
    rb.inputs['Roughness'].default_value = 0.62
    nt.links.new(geo.outputs['Position'], rb.inputs['Vector'])
    vor = _knoten(nt, 'ShaderNodeTexVoronoi', 100, -700)
    vor.inputs['Scale'].default_value = 38.0
    nt.links.new(geo.outputs['Position'], vor.inputs['Vector'])
    hoehe = _knoten(nt, 'ShaderNodeMath', 300, -600)
    hoehe.operation = 'MULTIPLY_ADD'
    hoehe.inputs[1].default_value = 0.35
    nt.links.new(vor.outputs['Distance'], hoehe.inputs[0])
    nt.links.new(rb.outputs['Fac'], hoehe.inputs[2])
    bump = _knoten(nt, 'ShaderNodeBump', 450, -500)
    bump.inputs['Strength'].default_value = 0.55
    bump.inputs['Distance'].default_value = 0.012
    nt.links.new(hoehe.outputs[0], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])
    kern = bpy.data.materials.get('Mauerkern') or bpy.data.materials.new('Mauerkern')
    kern.use_nodes = True
    kb = next((k for k in kern.node_tree.nodes if k.type == 'BSDF_PRINCIPLED'), None)
    if kb is not None:
        kb.inputs['Base Color'].default_value = (0.07, 0.065, 0.058, 1.0)
        kb.inputs['Roughness'].default_value = 0.95
    return mat, kern


def mauer_trocken(samen=2309):
    """Ein Muretto a secco um das Grundstueck: zwei Schalen aus Bruchstein,
    innen Schotter (dunkler Kern), oben eine Krone aus quer gestellten
    Steinen. 1,0 m hoch (+-8 cm), unten 0,62 m, oben 0,46 m breit -- die
    Mauer verjuengt sich, sonst stuende sie nicht. Ein Netz aus rund
    sechseinhalbtausend Steinen, jeder mit eigener Farbe ('stein') und
    seiner Hoehe ueber dem Boden ('boden') fuer die Erdspritzer unten."""
    import numpy as np
    rnd = random.Random(samen)
    mat, kern = _trockenstein_material()
    pts, s = _mauerlinie()
    L = s[-1]
    S = np.array(s)
    P = np.array([(p.x, p.y) for p in pts])
    gel = bpy.data.objects.get('p08_gelaende')
    dg = bpy.context.evaluated_depsgraph_get()
    ge = gel.evaluated_get(dg)
    inv = ge.matrix_world.inverted()
    ab = (inv.to_3x3() @ Vector((0, 0, -1))).normalized()
    raster = np.arange(0.0, L + 0.5, 0.5)
    zg = []
    for sv in raster:
        x = np.interp(sv, S, P[:, 0]); y = np.interp(sv, S, P[:, 1])
        ok, ort, _n, _i = ge.ray_cast(inv @ Vector((x, y, 200.0)), ab)
        zg.append((ge.matrix_world @ ort).z if ok else -0.38)
    zg = np.array(zg)

    def lage(sv):
        sv = sv % L
        x = float(np.interp(sv, S, P[:, 0])); y = float(np.interp(sv, S, P[:, 1]))
        x2 = float(np.interp(min(L, sv + 0.05), S, P[:, 0])); y2 = float(np.interp(min(L, sv + 0.05), S, P[:, 1]))
        t = Vector((x2 - x, y2 - y, 0.0))
        if t.length < 1e-6:
            t = Vector((1, 0, 0))
        t.normalize()
        return x, y, t, float(np.interp(sv, raster, zg))

    tor_s = []
    cx, cy = MAUER_MITTE
    bx, by = MAUER_HALB
    x0 = cx - bx + MAUER_RUND
    tor_s = (MAUER_TOR[0] - x0, MAUER_TOR[1] - x0)     # Suedmauer beginnt bei s=0
    im_tor = lambda sv: tor_s[0] < sv < tor_s[1]
    hoehe = lambda sv: 1.0 + 0.08 * noise.noise(Vector((sv * 0.06, 3.1, 0.0)))
    W0, W1 = 0.62, 0.46
    KRONE = 0.15
    steine = []   # (x, y, z, gier, nick, roll, l, d, h, zboden)
    for seite in (1, -1):
        z = -0.08
        while True:
            h_lage = rnd.uniform(0.13, 0.22) * (1.18 if z < 0.2 else 1.0)
            sv = rnd.uniform(0.0, 0.4)
            while sv < L:
                l = rnd.uniform(0.16, 0.55) * (1.2 if z < 0.2 else 1.0)
                mitte = sv + l / 2
                sv += l
                if im_tor(mitte):
                    continue
                H = hoehe(mitte)
                oben = H - KRONE
                if z > oben - 0.05:
                    continue
                # Nicht jeder Stein fuellt die Lage: die Probe vom 23.09. las
                # sich mit 84 bis 100 % Lagenhoehe wie Ziegel in Reihen. Ein
                # Muretto hat kleine, grosse und Zwickelsteine in den Luecken.
                h = min(h_lage * rnd.uniform(0.6, 1.0), oben - z)
                if h < 0.06:
                    continue
                x, y, t, zb = lage(mitte)
                n = Vector((t.y, -t.x, 0.0)) * seite
                zc = z + h / 2
                W = W0 + (W1 - W0) * max(0.0, zc) / H
                d = rnd.uniform(0.2, 0.3)
                aus = W / 2 - d / 2 + rnd.uniform(-0.03, 0.035)
                gier = math.atan2(t.y, t.x) + rnd.uniform(-0.1, 0.1)
                roll = seite * math.atan((W0 - W1) / 2 / H) + rnd.uniform(-0.08, 0.08)
                nick = rnd.uniform(-0.1, 0.1)
                steine.append((x + n.x * aus, y + n.y * aus, zb + zc, gier, nick, roll,
                               l - rnd.uniform(0.012, 0.03), d, h - rnd.uniform(0.008, 0.02), zb))
                rest = h_lage - h
                if rest > 0.05 and oben - (z + h) > 0.05:
                    # Zwickel: ein kleiner Stein in die Luecke darueber
                    hz_ = min(rest - 0.01, oben - z - h)
                    lz = l * rnd.uniform(0.35, 0.8)
                    ver = rnd.uniform(-0.3, 0.3) * (l - lz)
                    steine.append((x + n.x * aus + t.x * ver, y + n.y * aus + t.y * ver, zb + z + h + hz_ / 2,
                                   gier + rnd.uniform(-0.15, 0.15), rnd.uniform(-0.12, 0.12), roll,
                                   lz - 0.012, d * 0.8, hz_ - 0.008, zb))
            z += h_lage
            if z > 1.08 - KRONE:
                break
    # Krone: quer gestellte Steine, leicht gekippt -- die gezackte Oberkante
    sv = 0.0
    while sv < L:
        l = rnd.uniform(0.14, 0.26)
        mitte = sv + l / 2
        sv += l
        if im_tor(mitte):
            continue
        H = hoehe(mitte)
        x, y, t, zb = lage(mitte)
        hk = rnd.uniform(0.13, 0.21)
        gier = math.atan2(t.y, t.x) + rnd.uniform(-0.1, 0.1)
        steine.append((x, y, zb + H - KRONE + hk / 2 - 0.02, gier, rnd.uniform(-0.22, 0.22), rnd.uniform(-0.06, 0.06),
                       l - 0.015, W1 + rnd.uniform(0.02, 0.08), hk, zb))
    # Torpfeiler: zwei Saeulen aus groesseren Steinen, 1,3 m
    for tx in MAUER_TOR:
        sv_p = tx - x0
        x, y, t, zb = lage(sv_p)
        gier = math.atan2(t.y, t.x)
        z = -0.06
        while z < 1.25:
            h = rnd.uniform(0.18, 0.26)
            for dx in (-0.19, 0.19):
                for dy in (-0.19, 0.19):
                    steine.append((x + t.x * dx - t.y * dy, y + t.y * dx + t.x * dy, zb + z + h / 2,
                                   gier + rnd.uniform(-0.05, 0.05), rnd.uniform(-0.04, 0.04), rnd.uniform(-0.04, 0.04),
                                   0.36, 0.36, h - 0.015, zb))
            z += h
        steine.append((x, y, zb + z + 0.07, gier, 0.0, 0.0, 0.84, 0.84, 0.14, zb))
    varianten = [(np.array(c), f) for c, f in _stein_varianten()]
    alle_v, alle_f, attr_st, attr_bo = [], [], [], []
    basis = 0
    for x, y, z, gier, nick, roll, l, d, h, zb in steine:
        vc, vf = varianten[rnd.randrange(len(varianten))]
        R = (Matrix.Rotation(gier, 3, 'Z') @ Matrix.Rotation(roll, 3, 'X') @ Matrix.Rotation(nick, 3, 'Y'))
        Rn = np.array(R)
        v = vc * np.array([l / 2, d / 2, h / 2]) @ Rn.T + np.array([x, y, z])
        alle_v.append(v)
        alle_f.extend([tuple(i + basis for i in f) for f in vf])
        zuf = rnd.random()
        attr_st.append(np.full(len(v), zuf))
        attr_bo.append(v[:, 2] - zb)
        basis += len(v)
    V = np.concatenate(alle_v)
    me = bpy.data.meshes.new('trockenmauer')
    me.from_pydata(V.tolist(), [], alle_f)
    me.update()
    for poly in me.polygons:
        poly.use_smooth = True
    for name, werte in (('stein', np.concatenate(attr_st)), ('boden', np.concatenate(attr_bo))):
        a = me.attributes.new(name, 'FLOAT', 'POINT')
        a.data.foreach_set('value', werte.astype(np.float32))
    me.materials.append(mat)
    ob = bpy.data.objects.new('trockenmauer', me)
    bpy.context.scene.collection.objects.link(ob)
    # Kern: dunkler Schotter zwischen den Schalen, damit durch die Fugen
    # kein Himmel scheint.
    kv, kf = [], []
    sv = 0.0
    reihe = []
    while sv <= L:
        if not im_tor(sv):
            x, y, t, zb = lage(sv)
            n = Vector((t.y, -t.x, 0.0))
            H = hoehe(sv) - KRONE
            w0, w1 = W0 / 2 - 0.13, W1 / 2 - 0.1
            i = len(kv)
            kv += [(x + n.x * w0, y + n.y * w0, zb - 0.1), (x + n.x * w1, y + n.y * w1, zb + H),
                   (x - n.x * w1, y - n.y * w1, zb + H), (x - n.x * w0, y - n.y * w0, zb - 0.1)]
            reihe.append(i)
        else:
            reihe.append(None)
        sv += 0.5
    for a, b in zip(reihe, reihe[1:]):
        if a is None or b is None:
            continue
        kf += [(a, a + 1, b + 1, b), (a + 1, a + 2, b + 2, b + 1), (a + 2, a + 3, b + 3, b + 2)]
    km = bpy.data.meshes.new('mauerkern')
    km.from_pydata(kv, [], kf)
    km.materials.append(kern)
    ko = bpy.data.objects.new('mauerkern', km)
    bpy.context.scene.collection.objects.link(ko)
    print('[fotoreal] Trockenmauer: %d Steine, %d Flaechen, %.0f m' % (len(steine), len(alle_f), L))
    return len(steine)


# --------------------------------------------------------------------------
# POLSTER
# --------------------------------------------------------------------------
def _glatt(a, b, x):
    t = max(0.0, min(1.0, (x - a) / (b - a)))
    return t * t * (3 - 2 * t)


def _kissen_form(a, b, c, r, krone, seite, mulden, falten, samen):
    """Die Verformung eines Kastenkissens als Funktion -- dieselbe fuer das
    Netz und fuer den Keder, damit der Keder auf der Naht sitzt."""
    hx, hy, hz = a / 2, b / 2, c / 2
    ver = Vector((samen * 1.7, samen * 0.9, samen * 2.3))
    ecken = [(sx, sy, random.Random(samen * 10 + i).uniform(0, 6.28)) for i, (sx, sy) in enumerate(((1, 1), (1, -1), (-1, 1), (-1, -1)))]

    def form(p):
        inn = Vector((hx - r, hy - r, hz - r))
        q = Vector((max(-inn.x, min(inn.x, p.x)), max(-inn.y, min(inn.y, p.y)), max(-inn.z, min(inn.z, p.z))))
        d = p - q
        if d.length > 1e-9:
            p = q + d.normalized() * r
        u, w, z = p.x / hx, p.y / hy, p.z / hz
        fx, fy = max(0.0, 1 - u * u) ** 0.8, max(0.0, 1 - w * w) ** 0.8
        # Krone: Fuellung drueckt Ober- und Unterseite nach aussen
        p.z += krone * fx * fy * z * abs(z)
        # Seiten bauchig, zur Mitte der Hoehe am staerksten
        bz = max(0.0, 1 - z * z)
        p.x += seite * abs(u) ** 6 * math.copysign(1, u) * max(0.0, 1 - w * w) * bz
        p.y += seite * abs(w) ** 6 * math.copysign(1, w) * max(0.0, 1 - u * u) * bz
        # Mulden, wo gesessen wird (nur oben)
        if z > 0:
            for mx_, my_, sx_, sy_, tiefe in mulden:
                p.z -= tiefe * math.exp(-((p.x - mx_) / sx_) ** 2 - ((p.y - my_) / sy_) ** 2) * z
        # Falten: an jeder Ecke der Oberseite zieht der Stoff zur Naht --
        # Wellen quer zur Diagonale, die zur Mitte hin auslaufen
        if z > 0.2:
            for sx, sy, ph in ecken:
                dx, dy = p.x - sx * hx, p.y - sy * hy
                dist = math.hypot(dx, dy)
                if dist < 0.24:
                    ang = math.atan2(dy * sy, dx * sx)
                    p.z += falten * math.sin(ang * 7.0 + ph) * (1 - dist / 0.24) ** 2 * _glatt(0.2, 0.8, z)
        # Seitenfalten senkrecht nahe den Ecken
        for sx, sy, ph in ecken:
            ex, ey = abs(p.x - sx * hx), abs(p.y - sy * hy)
            if ex < 0.12 and abs(u) > 0.97:
                p.x += math.copysign(1, u) * falten * 0.5 * math.sin(z * 4.7 + ph) * (1 - ex / 0.12) ** 2 * (1 if abs(p.y - sy * hy) < 0.12 else 0)
            if ey < 0.12 and abs(w) > 0.97:
                p.y += math.copysign(1, w) * falten * 0.5 * math.sin(z * 4.7 + ph) * (1 - ey / 0.12) ** 2 * (1 if abs(p.x - sx * hx) < 0.12 else 0)
        # gebraucht, nicht neu: ein Hauch Unruhe im Stoff
        n = noise.noise(p * 7.0 + ver)
        p += Vector((0, 0, n * 0.0018 * (1 if z > 0 else 0.3)))
        return p
    return form


def _kissen(name, mass, ort, drehung, mat, mulden=(), falten=0.0035, krone=0.016, seite=0.006, r=None, samen=1, schnitte=20):
    """Ein Kastenkissen mit Keder oben und unten."""
    import bmesh
    a, b, c = mass
    r = r if r is not None else min(0.04, c * 0.24)
    form = _kissen_form(a, b, c, r, krone, seite, mulden, falten, samen)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=2.0)
    bmesh.ops.subdivide_edges(bm, edges=bm.edges[:], cuts=schnitte, use_grid_fill=True)
    for v in bm.verts:
        v.co = form(Vector((v.co.x * a / 2, v.co.y * b / 2, v.co.z * c / 2)))
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    for p in me.polygons:
        p.use_smooth = True
    me.materials.append(mat)
    ob = bpy.data.objects.new(name, me)
    bpy.context.scene.collection.objects.link(ob)
    ob.location = ort
    ob.rotation_euler = drehung
    sub = ob.modifiers.new('Glatt', 'SUBSURF')
    sub.levels = 1
    sub.render_levels = 2
    # Keder: 9 mm Kordel auf beiden Naehten, auf der Form mitgefuehrt
    hx, hy, hz = a / 2, b / 2, c / 2
    k = 0.2929 * r
    for vz in (1, -1):
        cu = bpy.data.curves.new(name + '_keder', 'CURVE')
        cu.dimensions = '3D'
        cu.bevel_depth = 0.0045
        cu.bevel_resolution = 2
        sp = cu.splines.new('POLY')
        punkte = []
        n = 240
        umfang = [(-hx, -hy), (hx, -hy), (hx, hy), (-hx, hy), (-hx, -hy)]
        teil = [((umfang[i + 1][0] - umfang[i][0]) ** 2 + (umfang[i + 1][1] - umfang[i][1]) ** 2) ** 0.5 for i in range(4)]
        ges = sum(teil)
        for j in range(n):
            t = j / n * ges
            i = 0
            while t > teil[i]:
                t -= teil[i]; i += 1
            f = t / teil[i]
            x = umfang[i][0] + (umfang[i + 1][0] - umfang[i][0]) * f
            y = umfang[i][1] + (umfang[i + 1][1] - umfang[i][1]) * f
            # Kantenpunkt des Wuerfels auf 45 Grad der Rundung abbilden
            p = form(Vector((x, y, vz * hz)))
            ri = Vector((max(-(hx - r), min(hx - r, p.x)), max(-(hy - r), min(hy - r, p.y)), max(-(hz - r), min(hz - r, p.z))))
            nrm = (p - ri).normalized() if (p - ri).length > 1e-6 else Vector((0, 0, vz))
            punkte.append(p + nrm * 0.0025)
        sp.points.add(len(punkte) - 1)
        for pt, co in zip(sp.points, punkte):
            pt.co = (co.x, co.y, co.z, 1.0)
        sp.use_cyclic_u = True
        cu.materials.append(mat)
        ko = bpy.data.objects.new(name + ('_keder_o' if vz > 0 else '_keder_u'), cu)
        bpy.context.scene.collection.objects.link(ko)
        ko.parent = ob
    return ob


def _zierkissen(name, groesse, dicke, ort, drehung, mat, samen=1, n=28):
    """Ein Zierkissen: zwei Stoffbahnen, am Rand vernaeht. Deshalb laufen die
    Ecken spitz aus (Eselsohren), die Kanten ziehen sich zur Mitte hin ein,
    und von jeder Ecke gehen Falten aus. Die vorigen Zierkissen waren
    abgerundete Quader -- orange Kloetze, keine Kissen."""
    import bmesh
    rnd = random.Random(samen)
    hx = hy = groesse / 2
    ver = Vector((samen * 3.1, samen * 1.3, 0.7))
    bm = bmesh.new()
    oben, unten = {}, {}

    def lage(i, j, vz):
        u, w = -1 + 2 * i / n, -1 + 2 * j / n
        x = u * hx * (1 - 0.075 * (1 - w * w))
        y = w * hy * (1 - 0.075 * (1 - u * u))
        f = ((1 - u * u) * (1 - w * w)) ** 0.42
        z = vz * dicke / 2 * f
        # Eselsohren und Falten aus den Ecken
        for sx, sy in ((1, 1), (1, -1), (-1, 1), (-1, -1)):
            dist = math.hypot(u - sx, w - sy)
            if dist < 0.9:
                ang = math.atan2(w - sy, u - sx)
                z += vz * 0.004 * math.sin(ang * 9 + samen) * (1 - dist / 0.9) ** 2 * f ** 0.3
        # Knick in der Mitte: jemand hat es zurechtgeklopft
        z -= vz * 0.012 * math.exp(-(u / 0.12) ** 2) * (1 - w * w) * (0.6 if vz > 0 else 0.2)
        z += vz * noise.noise(Vector((u * 2.2, w * 2.2, vz)) + ver) * 0.004 * f
        return Vector((x, y, z))
    for i in range(n + 1):
        for j in range(n + 1):
            rand = i in (0, n) or j in (0, n)
            v = bm.verts.new(lage(i, j, 1))
            oben[i, j] = v
            unten[i, j] = v if rand else bm.verts.new(lage(i, j, -1))
    for i in range(n):
        for j in range(n):
            bm.faces.new((oben[i, j], oben[i + 1, j], oben[i + 1, j + 1], oben[i, j + 1]))
            bm.faces.new((unten[i, j + 1], unten[i + 1, j + 1], unten[i + 1, j], unten[i, j]))
    bmesh.ops.remove_doubles(bm, verts=bm.verts[:], dist=1e-6)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    for p in me.polygons:
        p.use_smooth = True
    me.materials.append(mat)
    ob = bpy.data.objects.new(name, me)
    bpy.context.scene.collection.objects.link(ob)
    ob.location = ort
    ob.rotation_euler = drehung
    sub = ob.modifiers.new('Glatt', 'SUBSURF')
    sub.levels = 1
    sub.render_levels = 1
    # Keder am Rand
    cu = bpy.data.curves.new(name + '_keder', 'CURVE')
    cu.dimensions = '3D'
    cu.bevel_depth = 0.004
    cu.bevel_resolution = 2
    sp = cu.splines.new('POLY')
    rand = [(i, 0) for i in range(n)] + [(n, j) for j in range(n)] + [(i, n) for i in range(n, 0, -1)] + [(0, j) for j in range(n, 0, -1)]
    sp.points.add(len(rand) - 1)
    for pt, (i, j) in zip(sp.points, rand):
        co = lage(i, j, 1)
        pt.co = (co.x, co.y, co.z, 1.0)
    sp.use_cyclic_u = True
    cu.materials.append(mat)
    ko = bpy.data.objects.new(name + '_keder', cu)
    bpy.context.scene.collection.objects.link(ko)
    ko.parent = ob
    return ob


def _stoff(mat, glanz=0.45):
    """Polsterstoff statt Kunststoff: Schimmer (Sheen) an den Kanten -- das,
    woran man Stoff aus drei Metern erkennt --, dazu Noppen im Gewebe und
    eine leichte Farbunruhe. Vorher: Sheen 0, keine Normale."""
    if mat is None:
        return
    nt = mat.node_tree
    b = next((k for k in nt.nodes if k.type == 'BSDF_PRINCIPLED'), None)
    if b is None:
        return
    for n_, w in (('Sheen Weight', glanz), ('Sheen Roughness', 0.38)):
        if b.inputs.get(n_) is not None:
            b.inputs[n_].default_value = w
    if b.inputs.get('Sheen Tint') is not None:
        b.inputs['Sheen Tint'].default_value = (1.0, 1.0, 1.0, 1.0)
    koord = _knoten(nt, 'ShaderNodeTexCoord', -1400, -700)
    noppe = _knoten(nt, 'ShaderNodeTexNoise', -1200, -700)
    noppe.inputs['Scale'].default_value = 160.0
    noppe.inputs['Detail'].default_value = 3.0
    nt.links.new(koord.outputs['Object'], noppe.inputs['Vector'])
    welle = _knoten(nt, 'ShaderNodeTexWave', -1200, -900)
    welle.inputs['Scale'].default_value = 240.0
    welle.inputs['Distortion'].default_value = 3.0
    nt.links.new(koord.outputs['Object'], welle.inputs['Vector'])
    h = _knoten(nt, 'ShaderNodeMath', -1000, -800)
    h.operation = 'MULTIPLY_ADD'
    h.inputs[1].default_value = 0.4
    nt.links.new(welle.outputs['Fac'], h.inputs[0])
    nt.links.new(noppe.outputs['Fac'], h.inputs[2])
    bump = _knoten(nt, 'ShaderNodeBump', -800, -800)
    bump.inputs['Strength'].default_value = 0.14
    bump.inputs['Distance'].default_value = 0.0015
    nt.links.new(h.outputs[0], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])
    if not b.inputs['Base Color'].is_linked:
        grund = tuple(b.inputs['Base Color'].default_value)
        unruhe = _knoten(nt, 'ShaderNodeTexNoise', -1200, -400)
        unruhe.inputs['Scale'].default_value = 6.0
        unruhe.inputs['Detail'].default_value = 4.0
        nt.links.new(koord.outputs['Object'], unruhe.inputs['Vector'])
        k = _knoten(nt, 'ShaderNodeMapRange', -1000, -400)
        k.inputs['From Min'].default_value = 0.3
        k.inputs['From Max'].default_value = 0.7
        k.inputs['To Min'].default_value = 0.92
        k.inputs['To Max'].default_value = 1.07
        nt.links.new(unruhe.outputs['Fac'], k.inputs['Value'])
        m = _knoten(nt, 'ShaderNodeMix', -800, -400)
        m.data_type = 'RGBA'
        m.blend_type = 'MULTIPLY'
        m.inputs['Factor'].default_value = 1.0
        m.inputs[6].default_value = grund
        nt.links.new(k.outputs['Result'], m.inputs[7])
        nt.links.new(m.outputs[2], b.inputs['Base Color'])


def polster(samen=77):
    """Sofa und Sessel mit echten Kissen: Sitzkissen mit Keder, Mulden, wo
    gesessen wird, und Falten an den Ecken; lose Rueckenkissen, leicht
    angelehnt; Zierkissen als vernaehte Stoffbahnen. Die alten Kissen
    (weiche Quader) und Zierkloetze gehen aus dem Bild; Sockel, Armlehne
    und Rueckenrahmen bleiben."""
    polster_m = bpy.data.materials.get('Polsterstoff')
    akzent = bpy.data.materials.get('Akzent')
    leinen = bpy.data.materials.get('Leinen')
    for m, g in ((polster_m, 0.45), (akzent, 0.4), (leinen, 0.3)):
        _stoff(m, g)
    weg = ['p07_sofa_lang_kissen_l', 'p07_sofa_lang_kissen_r', 'p07_sofa_kurz_kissen',
           'p07_deko_kissen0', 'p07_deko_kissen1', 'p07_deko_kissen2',
           'p07_sofa_zier1', 'p07_sofa_zier2', 'p07_sofa_zier3']
    for n_ in weg:
        o = bpy.data.objects.get(n_)
        if o is not None:
            o.hide_render = True
    if bpy.data.objects.get('p07_sofa_lang') is None or polster_m is None:
        return 0
    r10 = math.radians(-10.0)
    seitlich = math.radians(-90.0)
    mulde2 = lambda: ((-0.38, -0.07, 0.3, 0.26, 0.019), (0.38, -0.07, 0.3, 0.26, 0.016))
    teile = [
        _kissen('sitz_lang_l', (1.52, 0.85, 0.17), (12.42, 9.193, 0.485), (0, 0, 0), polster_m, mulde2(), samen=1),
        _kissen('sitz_lang_r', (1.52, 0.85, 0.17), (13.97, 9.193, 0.485), (0, 0, 0), polster_m, mulde2(), samen=2),
        _kissen('sitz_kurz_1', (1.05, 0.85, 0.17), (15.388, 7.19, 0.485), (0, 0, seitlich), polster_m, ((0, -0.07, 0.3, 0.26, 0.014),), samen=3),
        _kissen('sitz_kurz_2', (1.05, 0.85, 0.17), (15.388, 8.27, 0.485), (0, 0, seitlich), polster_m, ((0.05, -0.07, 0.3, 0.26, 0.017),), samen=4),
        _kissen('ruecken_lang_l', (1.50, 0.19, 0.30), (12.42, 9.34, 0.715), (r10, 0, 0), polster_m, falten=0.003, krone=0.02, samen=5),
        _kissen('ruecken_lang_r', (1.50, 0.19, 0.30), (13.97, 9.34, 0.715), (r10, 0, 0), polster_m, falten=0.003, krone=0.02, samen=6),
        _kissen('ruecken_kurz_1', (1.05, 0.19, 0.30), (15.54, 7.19, 0.715), (r10, 0, seitlich), polster_m, falten=0.003, krone=0.02, samen=7),
        _kissen('ruecken_kurz_2', (1.05, 0.19, 0.30), (15.54, 8.27, 0.715), (r10, 0, seitlich), polster_m, falten=0.003, krone=0.02, samen=8),
    ]
    if bpy.data.objects.get('p07_sessel') is not None:
        teile += [
            _kissen('sitz_sessel', (0.80, 0.66, 0.14), (16.36, 4.98, 0.45), (0, 0, 0), polster_m, ((0, -0.06, 0.22, 0.2, 0.015),), samen=9),
            _kissen('ruecken_sessel', (0.78, 0.17, 0.30), (16.36, 5.21, 0.64), (math.radians(-9), 0, 0), polster_m, falten=0.003, krone=0.018, samen=10),
        ]
    lehnen = math.radians(76.0)
    zier = [
        ('zier_1', 0.46, akzent, (11.99, 9.17, 0.775), (lehnen, 0, math.radians(7))),
        ('zier_2', 0.42, leinen or polster_m, (12.53, 9.18, 0.76), (math.radians(73), 0, math.radians(-5))),
        ('zier_3', 0.45, akzent, (14.33, 9.17, 0.77), (lehnen, 0, math.radians(-4))),
        ('zier_4', 0.45, akzent, (15.38, 7.62, 0.77), (lehnen, 0, seitlich + math.radians(5))),
        ('zier_5', 0.42, leinen or polster_m, (15.39, 8.24, 0.76), (math.radians(72), 0, seitlich - math.radians(6))),
    ]
    for i, (n_, g, m, ort, dreh) in enumerate(zier):
        teile.append(_zierkissen(n_, g, 0.15, ort, dreh, m or polster_m, samen=samen + i))
    print('[fotoreal] Polster: %d Kissen' % len(teile))
    return len(teile)


def anwenden(teile=('land', 'mauer', 'ferne', 'zypressen', 'kies', 'polster')):
    global MAUER_AN
    MAUER_AN = 'mauer' in teile    # vor 'land': die Rasengrenze haengt daran
    erg = {}
    for name, fn in (('land', land_sizilien), ('mauer', mauer_trocken), ('ferne', ferne_baeume), ('zypressen', zypressen_laub), ('kies', kies_vorplatz), ('polster', polster)):
        if name not in teile:
            continue
        try:
            erg[name] = fn()
        except Exception as f:
            import traceback
            traceback.print_exc()
            erg[name] = 'FEHLER %s' % f
    return erg
