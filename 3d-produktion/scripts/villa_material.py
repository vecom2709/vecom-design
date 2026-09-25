# -*- coding: utf-8 -*-
"""
villa_material.py — Aus Farbflaechen werden Oberflaechen.

WARUM DAS EIN EIGENER SCHRITT IST
villa.py baut die Geometrie und gibt jedem Bauteil eine Farbe. Das reicht,
um Masse und Grundriss zu pruefen, und es reicht nicht fuer ein Bild, das
jemand fuer eine Fotografie haelt. Der Unterschied zwischen Ton-Modell und
Foto liegt fast nie in der Form -- er liegt darin, dass echte Oberflaechen
UNGLEICHMAESSIG sind: Sichtbeton hat Schalungsstoesse und Wolken, Travertin
hat Poren, Eiche hat Fladern, Rasen hat Flecken.

WARUM PROZEDURAL UND NICHT FOTOTEXTUREN
Weil die Bilder spaeter ins Netz muessen. Eine gekaufte 4K-Fototextur ist
im Browser 6 MB. Ein Rauschknoten kostet in Blender nichts und wird fuer
das Netz auf 1024 px gebacken -- 60 bis 90 KB als WebP. Derselbe Knoten
liefert also das Standbild UND die Echtzeitfassung.

Lauf nach villa.py:
    exec(open(pfad).read()); anwenden()
"""

import bpy


def _frisch(mat):
    """Materialbaum leeren bis auf Ausgang und Principled."""
    mat.use_nodes = True
    nt = mat.node_tree
    aus = None
    bsdf = None
    for n in list(nt.nodes):
        if n.type == 'OUTPUT_MATERIAL' and aus is None:
            aus = n
        elif n.type == 'BSDF_PRINCIPLED' and bsdf is None:
            bsdf = n
        else:
            nt.nodes.remove(n)
    if aus is None:
        aus = nt.nodes.new('ShaderNodeOutputMaterial')
    if bsdf is None:
        bsdf = nt.nodes.new('ShaderNodeBsdfPrincipled')
    nt.links.new(bsdf.outputs[0], aus.inputs[0])
    return nt, bsdf


def _koord(nt, skala=1.0, detail=6.0, rauheit=0.5, art='FBM'):
    """Objektkoordinaten -> Rauschen. Objektkoordinaten, nicht UV: Die
    Bauteile haben keine sinnvollen UV (es sind skalierte Wuerfel), und
    Generated-Koordinaten wuerden auf jedem Quader wieder bei 0 anfangen --
    dann haette jede Wandscheibe ihr eigenes Muster und man saehe jede
    Fuge."""
    tex = nt.nodes.new('ShaderNodeTexCoord')
    map_ = nt.nodes.new('ShaderNodeMapping')
    n = nt.nodes.new('ShaderNodeTexNoise')
    n.inputs['Scale'].default_value = skala
    n.inputs['Detail'].default_value = detail
    n.inputs['Roughness'].default_value = rauheit
    nt.links.new(tex.outputs['Object'], map_.inputs['Vector'])
    nt.links.new(map_.outputs['Vector'], n.inputs['Vector'])
    return tex, map_, n


def _mischen(nt, bsdf, rausch, farbe_a, farbe_b, staerke=0.35):
    misch = nt.nodes.new('ShaderNodeMix')
    misch.data_type = 'RGBA'
    misch.inputs['Factor'].default_value = staerke
    misch.inputs[6].default_value = (farbe_a[0], farbe_a[1], farbe_a[2], 1.0)
    misch.inputs[7].default_value = (farbe_b[0], farbe_b[1], farbe_b[2], 1.0)
    nt.links.new(rausch.outputs['Fac'], misch.inputs['Factor'])
    nt.links.new(misch.outputs[2], bsdf.inputs['Base Color'])
    return misch


def _bump(nt, bsdf, quelle, staerke=0.12, entfernung=0.02):
    b = nt.nodes.new('ShaderNodeBump')
    b.inputs['Strength'].default_value = staerke
    b.inputs['Distance'].default_value = entfernung
    nt.links.new(quelle, b.inputs['Height'])
    nt.links.new(b.outputs['Normal'], bsdf.inputs['Normal'])
    return b


def sichtbeton(mat):
    """Sichtbeton: grossflaechige Wolken, feine Poren, matte Oberflaeche.
    Die Schalungsstoesse sind absichtlich NICHT drin -- bei 0,40 m Wand und
    dieser Bildgroesse liest man sie als Schmutz, nicht als Handwerk."""
    nt, b = _frisch(mat)
    # Skala 1,4 war auf 18 m Fassade praktisch unsichtbar -- die Wand las
    # sich als eine Farbe. 0,45 macht die Wolken so gross, dass man sie auf
    # dem fertigen Bild wahrnimmt, ohne dass die Wand fleckig wirkt.
    _, _, wolke = _koord(nt, skala=0.45, detail=6.0, rauheit=0.66)
    _, _, pore = _koord(nt, skala=48.0, detail=8.0, rauheit=0.75)
    # Werte bewusst dunkel: Sichtbeton hat einen Grauwert um 0,45, nicht um
    # 0,65. Mit den helleren Startwerten sah die Villa unter AgX aus wie
    # gespritztes Weiss -- ein Baustoff, den man an diesem Haus nicht
    # verbaut haette.
    _mischen(nt, b, wolke, (0.395, 0.388, 0.374), (0.478, 0.470, 0.452), 1.0)
    _bump(nt, b, pore.outputs['Fac'], 0.16, 0.006)
    b.inputs['Roughness'].default_value = 0.68
    b.inputs['Specular IOR Level'].default_value = 0.42 if 'Specular IOR Level' in b.inputs else 0.5
    return mat


def putz(mat):
    nt, b = _frisch(mat)
    _, _, korn = _koord(nt, skala=90.0, detail=6.0, rauheit=0.6)
    _mischen(nt, b, korn, (0.700, 0.692, 0.678), (0.762, 0.755, 0.740), 1.0)
    _bump(nt, b, korn.outputs['Fac'], 0.10, 0.004)
    b.inputs['Roughness'].default_value = 0.76
    return mat


def eiche(mat, dunkel=False):
    """Eiche mit Fladern. Der gestreckte Rauschknoten laengs der Faser ist
    der ganze Trick: Holz ist in einer Achse fast gleichmaessig und quer
    dazu nicht."""
    nt, b = _frisch(mat)
    _, map_, faser = _koord(nt, skala=6.0, detail=9.0, rauheit=0.58)
    map_.inputs['Scale'].default_value = (0.06, 1.0, 1.0)
    welle = nt.nodes.new('ShaderNodeTexWave')
    welle.wave_type = 'BANDS'
    welle.bands_direction = 'X'
    welle.inputs['Scale'].default_value = 2.2
    welle.inputs['Distortion'].default_value = 11.0
    welle.inputs['Detail'].default_value = 3.0
    nt.links.new(map_.outputs['Vector'], welle.inputs['Vector'])
    misch = nt.nodes.new('ShaderNodeMix')
    misch.data_type = 'RGBA'
    hell = (0.34, 0.20, 0.098) if dunkel else (0.455, 0.295, 0.150)
    tief = (0.20, 0.112, 0.052) if dunkel else (0.285, 0.170, 0.082)
    misch.inputs[6].default_value = (tief[0], tief[1], tief[2], 1.0)
    misch.inputs[7].default_value = (hell[0], hell[1], hell[2], 1.0)
    nt.links.new(welle.outputs['Fac'], misch.inputs['Factor'])
    nt.links.new(misch.outputs[2], b.inputs['Base Color'])
    _bump(nt, b, faser.outputs['Fac'], 0.09, 0.003)
    b.inputs['Roughness'].default_value = 0.44
    return mat


def travertin(mat):
    """Travertin: Poren in Baendern, warmer Grundton. Der Bodenbelag, den
    man in dieser Preisklasse erwartet -- und der im Streiflicht der
    Nachmittagssonne den halben Eindruck traegt."""
    nt, b = _frisch(mat)
    _, map_, poren = _koord(nt, skala=26.0, detail=9.0, rauheit=0.72)
    map_.inputs['Scale'].default_value = (1.0, 0.22, 1.0)
    _, _, wolke = _koord(nt, skala=2.6, detail=5.0, rauheit=0.55)
    _mischen(nt, b, wolke, (0.480, 0.438, 0.372), (0.585, 0.548, 0.478), 1.0)
    _bump(nt, b, poren.outputs['Fac'], 0.22, 0.004)
    b.inputs['Roughness'].default_value = 0.42
    return mat


def naturstein(mat):
    nt, b = _frisch(mat)
    _, _, korn = _koord(nt, skala=14.0, detail=7.0, rauheit=0.6)
    _mischen(nt, b, korn, (0.245, 0.240, 0.232), (0.330, 0.325, 0.316), 1.0)
    _bump(nt, b, korn.outputs['Fac'], 0.14, 0.004)
    b.inputs['Roughness'].default_value = 0.38
    return mat


def poolstein(mat):
    """Beckenauskleidung. Tief und leicht gruenstichig -- siehe villa.py."""
    nt, b = _frisch(mat)
    _, _, korn = _koord(nt, skala=22.0, detail=6.0, rauheit=0.6)
    _mischen(nt, b, korn, (0.038, 0.082, 0.098), (0.070, 0.128, 0.148), 1.0)
    _bump(nt, b, korn.outputs['Fac'], 0.10, 0.003)
    b.inputs['Roughness'].default_value = 0.30
    return mat


def rasen(mat):
    """Kein Grasgenerator: 40.000 Halme kosten im Netz alles und hier fast
    nichts an Wirkung.

    Entscheidend ist nicht die Halmgeometrie, sondern dass die Flaeche
    UNGLEICHMAESSIG ist. Ein einheitliches Gruen liest sich als Teppich --
    genau so sah der erste Durchgang aus. Drei Ebenen: grosse Feuchte- und
    Schnittwolken, mittlere Flecken, feines Korn fuer den Bump. Die Toene
    bleiben gedaempft; ein saftiges Maigruen ist auf Sizilien ohnehin
    unglaubwuerdig."""
    nt, b = _frisch(mat)
    # Drei ECHTE Groessenordnungen. Vorher lagen sie bei 0,9 / 7,5 / 190 --
    # das sind auf dem 520 m grossen Gelaende Strukturen von 1,1 m, 13 cm
    # und 5 mm. Alles davon ist ab 30 m Entfernung ein einziger Ton. Es
    # fehlte die grosse Ebene: Maehbahnen, Trockenstellen, feuchte Senken,
    # 15 bis 20 m gross. Genau die sieht man aus der Entfernung, aus der
    # dieses Bild aufgenommen ist.
    _, _, gross = _koord(nt, skala=0.062, detail=6.0, rauheit=0.62)
    _, _, mittel = _koord(nt, skala=0.9, detail=8.0, rauheit=0.66)
    _, _, fein = _koord(nt, skala=190.0, detail=4.0, rauheit=0.5)

    # DUNKLER UND WENIGER GELB als im ersten Anlauf (0,052/0,086/0,030 bis
    # 0,104/0,146/0,048). Gemessen am 17.09.2026: Unter der tiefen Sonne
    # und mit AgX zog dieses Gruen ins Gelbgraue, und die Wiese sah aus wie
    # ein Golfplatz im August. Ein gepflegter Rasen hat ein Albedo um 0,05
    # bis 0,08 -- er ist dunkler, als man ihn in Erinnerung hat. Der
    # Blauanteil bleibt bewusst nicht bei null: Himmelslicht faellt auf
    # jeden Halm, und ein Gruen ganz ohne Blau wirkt giftig.
    m1 = nt.nodes.new('ShaderNodeMix')
    m1.data_type = 'RGBA'
    m1.inputs[6].default_value = (0.030, 0.054, 0.024, 1.0)
    m1.inputs[7].default_value = (0.066, 0.098, 0.040, 1.0)
    nt.links.new(gross.outputs['Fac'], m1.inputs['Factor'])

    m2 = nt.nodes.new('ShaderNodeMix')
    m2.data_type = 'RGBA'
    m2.inputs[7].default_value = (0.048, 0.072, 0.030, 1.0)
    nt.links.new(mittel.outputs['Fac'], m2.inputs['Factor'])
    nt.links.new(m1.outputs[2], m2.inputs[6])
    nt.links.new(m2.outputs[2], b.inputs['Base Color'])

    _bump(nt, b, fein.outputs['Fac'], 0.42, 0.012)
    b.inputs['Roughness'].default_value = 0.95

    # SPIEGELUNG FAST AUS -- und das ist der Grund, warum die Wiese in der
    # Ferne vorher aussah wie nasser Asphalt.
    # Ein Principled-BSDF hat Fresnel: Unter flachem Blickwinkel steigt die
    # Spiegelung jeder Oberflaeche gegen eins. Die Kamera steht auf 4,4 m
    # und sieht eine 500 m tiefe Ebene fast von der Kante -- also glaenzte
    # der halbe Rasen. Bei echtem Gras passiert das nicht, weil die Halme
    # jeden zusammenhaengenden Glanz zerschneiden. Wo Halme stehen, loest
    # sich das von selbst; fuer die Flaeche dahinter wird die Spiegelung
    # heruntergenommen.
    if 'Specular IOR Level' in b.inputs:
        b.inputs['Specular IOR Level'].default_value = 0.06
    elif 'Specular' in b.inputs:
        b.inputs['Specular'].default_value = 0.06
    return mat


def kies(mat):
    nt, b = _frisch(mat)
    _, _, korn = _koord(nt, skala=95.0, detail=8.0, rauheit=0.78)
    _mischen(nt, b, korn, (0.128, 0.124, 0.118), (0.235, 0.230, 0.220), 1.0)
    _bump(nt, b, korn.outputs['Fac'], 0.45, 0.012)
    b.inputs['Roughness'].default_value = 0.88
    return mat


def alu(mat):
    nt, b = _frisch(mat)
    b.inputs['Base Color'].default_value = (0.048, 0.050, 0.054, 1.0)
    b.inputs['Metallic'].default_value = 0.85
    b.inputs['Roughness'].default_value = 0.32
    return mat


def glas(mat):
    """Glas fuer EEVEE: kein echtes Transmission, sondern Alpha plus
    kraeftige Spiegelung. Echte Brechung kostet in EEVEE mehr, als sie an
    einer flachen Scheibe bringt -- und im Browser gibt es sie ohnehin
    nicht, also wird hier schon so gebaut, wie es dort aussehen wird."""
    nt, b = _frisch(mat)
    b.inputs['Base Color'].default_value = (0.72, 0.80, 0.84, 1.0)
    b.inputs['Roughness'].default_value = 0.02
    b.inputs['Metallic'].default_value = 0.0
    b.inputs['IOR'].default_value = 1.5
    if 'Transmission Weight' in b.inputs:
        b.inputs['Transmission Weight'].default_value = 0.0
    b.inputs['Alpha'].default_value = 0.14
    mat.blend_method = 'BLEND'
    if hasattr(mat, 'use_backface_culling'):
        mat.use_backface_culling = True
    if hasattr(mat, 'use_raytrace_refraction'):
        mat.use_raytrace_refraction = False
    return mat


def wasser(mat):
    nt, b = _frisch(mat)
    _, map_, welle = _koord(nt, skala=8.0, detail=6.0, rauheit=0.5)
    map_.inputs['Scale'].default_value = (1.0, 1.0, 0.05)
    b.inputs['Base Color'].default_value = (0.020, 0.115, 0.145, 1.0)
    b.inputs['Roughness'].default_value = 0.035
    b.inputs['IOR'].default_value = 1.33
    b.inputs['Alpha'].default_value = 0.72
    mat.blend_method = 'BLEND'
    _bump(nt, b, welle.outputs['Fac'], 0.06, 0.004)
    return mat


def estrich(mat):
    nt, b = _frisch(mat)
    _, _, korn = _koord(nt, skala=30.0, detail=6.0, rauheit=0.6)
    _mischen(nt, b, korn, (0.46, 0.455, 0.445), (0.545, 0.540, 0.528), 1.0)
    b.inputs['Roughness'].default_value = 0.80
    return mat


ZUORDNUNG = {
    'Sichtbeton': sichtbeton,
    'Putz_Weiss': putz,
    'Eiche_Lamelle': lambda m: eiche(m, dunkel=False),
    'Eiche_Diele': lambda m: eiche(m, dunkel=True),
    'Alu_Anthrazit': alu,
    'Glas': glas,
    'Travertin': travertin,
    'Naturstein': naturstein,
    'Poolstein': poolstein,
    'Wasser': wasser,
    'Rasen': rasen,
    'Kies': kies,
    'Estrich': estrich,
}


def anwenden():
    getan = []
    for name, f in ZUORDNUNG.items():
        mat = bpy.data.materials.get(name)
        if mat is None:
            continue
        f(mat)
        getan.append(name)
    return getan
