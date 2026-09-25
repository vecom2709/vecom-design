# -*- coding: utf-8 -*-
"""
villa.py — Ein modernes Haus der oberen Preisklasse, parametrisch.

WAS DER BESUCHER IM LABOR DAMIT TUN SOLL
  1. Grundriss   Der Plan liegt flach. Dann waechst das Haus daraus hoch.
  2. Bauablauf   Rohbau, Decke, Auskragung, Dach, Glas, Ausbau, Garten.
  3. Begehung    Kamera auf 1,65 m, durch die Tuer, durch die Raeume.

WARUM DIESES HAUS UND NICHT DAS ERSTE
Das erste war ein Satteldachhaus. Wer 1,2 Millionen ausgibt, erkennt sich
darin nicht wieder -- und ein Bauunternehmer, der diese Seite ansieht, auch
nicht. Also: zwei versetzte Koerper, das obere kragt 2,50 m ueber die
Terrasse, Flachdach, raumhohe Verglasung, Sichtbeton und Eichenlamellen.
Das ist die Bauaufgabe, bei der sich Visualisierung ueberhaupt lohnt.

AUFBAU DER MASSE
  Koerper A (Erdgeschoss)   18,00 x 11,00 m, lichte Hoehe 3,20 m
  Koerper B (Obergeschoss)  13,00 x 10,50 m, versetzt, kragt nach Sueden aus
  Aussenwand 0,40 m, Innenwand 0,15 m, Decke 0,40 m
  Nullpunkt: vordere linke Aussenecke. X nach rechts, Y in den Garten.

WARUM JEDES BAUTEIL SEINEN URSPRUNG UNTEN HAT
Damit der Browser das Haus aus dem Grundriss wachsen lassen kann, ohne die
Geometrie anzufassen: scale.y von 0 auf 1, und die Wand steigt aus dem
Boden. Bei einem Ursprung in der Mitte waechst sie in beide Richtungen und
das halbe Haus steht im Keller.

Lauf:  blender -b -P scripts/villa.py
"""

import bpy
import bmesh
import json
import math
import os

# ------------------------------------------------------------------ Masse

W_AUSSEN = 0.40
W_INNEN = 0.15
H_EG = 3.20
H_OG = 2.90
DECKE = 0.40
ATTIKA = 0.35          # Aufkantung ueber dem Flachdach

A_X0, A_X1 = 0.00, 18.00
A_Y0, A_Y1 = 0.00, 11.00
B_X0, B_X1 = 2.00, 15.00
B_Y0, B_Y1 = 3.00, 13.50      # ragt 2,50 m ueber A hinaus

Z_EG = 0.00
Z_DECKE = Z_EG + H_EG                 # Unterkante Geschossdecke
Z_OG = Z_DECKE + DECKE                # Fussboden OG  = 3.60
Z_DACH = Z_OG + H_OG                  # Unterkante Dachdecke = 6.50
Z_DACH_OK = Z_DACH + DECKE            # Oberkante Dach = 6.90

# Luftraum ueber der Halle (Loch in der Decke)
LUFT_X0, LUFT_X1 = 2.40, 5.20
LUFT_Y0, LUFT_Y1 = 3.40, 8.60


# -------------------------------------------------------------- Werkzeug

def leeren():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for c in list(bpy.data.collections):
        bpy.data.collections.remove(c)
    for block in (bpy.data.meshes, bpy.data.materials):
        for d in list(block):
            if d.users == 0:
                block.remove(d)


def gruppe(name):
    if name in bpy.data.collections:
        return bpy.data.collections[name]
    c = bpy.data.collections.new(name)
    bpy.context.scene.collection.children.link(c)
    return c


def quader(name, x0, y0, z0, dx, dy, dz, sammlung, mat=None):
    """Quader ueber Ecke und Kantenlaengen. Der Ursprung liegt UNTEN MITTIG
    (x-Mitte, y-Mitte, z0) -- siehe Kopf der Datei.

    DIE GROESSE STECKT IM NETZ, NICHT IN DER OBJEKTSKALIERUNG.
    Erste Fassung skalierte einen Einheitswuerfel auf (dx, dy, dz). Das ist
    kuerzer und macht jede Fase unbrauchbar: Ein Fasenmodifikator mit 6 mm
    Breite wird auf einer Wand mit Skalierung (5, 0.4, 3) zu 30 mm in X und
    2,4 mm in Y. Genau diese rasiermesserscharfen, ungleichen Kanten sind
    das, woran man ein Rendering als Rendering erkennt.

    Die Skalierung bleibt deshalb (1, 1, 1) -- und scale.y von 0 auf 1 im
    Browser funktioniert trotzdem, weil die lokale Z-Achse des Netzes bei 0
    beginnt und bei dz endet."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= dx
        v.co.y *= dy
        v.co.z = v.co.z * dz + dz / 2.0
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    ob.location = (x0 + dx / 2.0, y0 + dy / 2.0, z0)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob


def kegel(name, x, y, z0, radius, hoehe, sammlung, mat=None, seiten=12):
    """Ein Kegel mit Ursprung unten -- fuer die Zypressen. Zwoelf Seiten,
    nicht 32: Bei dieser Bildgroesse sieht man den Unterschied nicht, im
    Browser aber jede Ecke im Dreiecksbudget."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=seiten,
                          radius1=radius, radius2=0.0, depth=hoehe)
    bmesh.ops.translate(bm, verts=bm.verts, vec=(0.0, 0.0, hoehe / 2.0))
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    ob.location = (x, y, z0)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob


def material(name, farbe, rauheit=0.8, metall=0.0, alpha=1.0,
             durchlass=0.0, ior=1.45):
    if name in bpy.data.materials:
        return bpy.data.materials[name]
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes.get('Principled BSDF')
    b.inputs['Base Color'].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
    b.inputs['Roughness'].default_value = rauheit
    b.inputs['Metallic'].default_value = metall
    if 'IOR' in b.inputs:
        b.inputs['IOR'].default_value = ior
    if 'Transmission Weight' in b.inputs and durchlass > 0:
        b.inputs['Transmission Weight'].default_value = durchlass
    if alpha < 1.0:
        b.inputs['Alpha'].default_value = alpha
        m.blend_method = 'BLEND'
    return m


# ------------------------------------------------------ Wand mit Oeffnung

def wand(name, achse, lage, von, bis, dicke, z0, z1, sammlung, mat,
         oeffnungen=None):
    """Eine gerade Wand.

    achse  'x' = laeuft in X-Richtung, 'y' = laeuft in Y-Richtung
    lage   die feste Koordinate (Mittellinie der Wand)
    von/bis  Anfang und Ende entlang der Laufrichtung
    oeffnungen  Liste (pos_absolut, breite, z_unten, z_oben)

    Die Wand wird an ihren Oeffnungen in Pfeiler, Bruestung und Sturz
    zerlegt. Keine Booleschen Schnitte: Die erzeugen n-Gone, die beim
    Triangulieren im Browser zerfallen, und jede Naht sieht man im
    Streiflicht.
    """
    teile = []
    oeffnungen = sorted(oeffnungen or [], key=lambda o: o[0])

    def stueck(a, b, zu, zo, kennung):
        if b - a < 0.004 or zo - zu < 0.004:
            return
        if achse == 'x':
            teile.append(quader('%s_%s' % (name, kennung),
                                a, lage - dicke / 2.0, zu,
                                b - a, dicke, zo - zu, sammlung, mat))
        else:
            teile.append(quader('%s_%s' % (name, kennung),
                                lage - dicke / 2.0, a, zu,
                                dicke, b - a, zo - zu, sammlung, mat))

    cursor = von
    for i, (pos, br, ou, oo) in enumerate(oeffnungen):
        stueck(cursor, pos, z0, z1, 'p%d' % i)
        stueck(pos, pos + br, z0, min(ou, z1), 'b%d' % i)
        stueck(pos, pos + br, max(oo, z0), z1, 's%d' % i)
        cursor = pos + br
    stueck(cursor, bis, z0, z1, 'e')
    return teile


def verglasung(name, achse, lage, pos, breite, zu, zo, sammlung,
               m_glas, m_rahmen, pfosten=2.40, stark=0.05):
    """Scheibe samt Pfosten-Riegel-Rahmen.

    Eine 7,80 m breite Glaswand ohne Pfosten liest sich wie eine Luecke in
    der Wand. Der Raster macht aus der Luecke ein Bauteil -- und genau das
    ist der Unterschied zwischen teuer und unfertig.
    """
    raus = []
    d_glas = 0.036
    if achse == 'x':
        raus.append(quader(name + '_glas', pos, lage - d_glas / 2.0, zu,
                           breite, d_glas, zo - zu, sammlung, m_glas))
    else:
        raus.append(quader(name + '_glas', lage - d_glas / 2.0, pos, zu,
                           d_glas, breite, zo - zu, sammlung, m_glas))

    def leiste(a, laenge, u, hoehe, kennung):
        if achse == 'x':
            raus.append(quader('%s_%s' % (name, kennung),
                               a, lage - stark / 2.0, u,
                               laenge, stark, hoehe, sammlung, m_rahmen))
        else:
            raus.append(quader('%s_%s' % (name, kennung),
                               lage - stark / 2.0, a, u,
                               stark, laenge, hoehe, sammlung, m_rahmen))

    leiste(pos, breite, zu, 0.07, 'riegel_u')
    leiste(pos, breite, zo - 0.07, 0.07, 'riegel_o')
    n = max(1, int(round(breite / pfosten)))
    for i in range(n + 1):
        x = pos + i * breite / n
        b = 0.09
        leiste(min(max(x - b / 2.0, pos), pos + breite - b), b,
               zu, zo - zu, 'pfosten_%d' % i)
    return raus


def lamellen(name, achse, lage, von, bis, z0, z1, sammlung, mat,
             teilung=0.14, breite=0.09, tiefe=0.05):
    """Eichenlamellen vor Beton. Der Eingangsbereich und die Nordwand
    bekommen dadurch Massstab und Tiefe -- eine glatte Betonscheibe von
    18 m Laenge wirkt im Rendering wie eine Textur, nicht wie ein Haus."""
    raus = []
    n = int((bis - von) / teilung)
    for i in range(n):
        a = von + i * teilung
        if achse == 'x':
            raus.append(quader('%s_%03d' % (name, i), a, lage, z0,
                               breite, tiefe, z1 - z0, sammlung, mat))
        else:
            raus.append(quader('%s_%03d' % (name, i), lage, a, z0,
                               tiefe, breite, z1 - z0, sammlung, mat))
    return raus


# ------------------------------------------------------------- Grundriss
# (schluessel, x0, y0, x1, y1) -- lichte Innenmasse. Diese Liste ist die
# einzige Quelle fuer den Plan im Browser UND fuer die Flaechen, auf denen
# sich die Kamera bei der Begehung bewegen darf.

RAEUME_EG = [
    ('entree',  0.40, 0.40,  5.20,  8.60),
    ('wc',      0.40, 8.75,  2.55, 10.60),
    ('technik', 2.70, 8.75,  5.20, 10.60),
    ('essen',   5.35, 0.40, 17.60,  3.85),
    ('kueche',  5.35, 4.00,  9.55, 10.60),
    ('wohnen',  9.70, 4.00, 17.60, 10.60),
]

RAEUME_OG = [
    ('galerie',  5.35, 3.40, 14.60,  5.20),
    ('galerie2', 5.35, 5.35,  7.20, 13.10),
    ('bad',      2.40, 8.75,  5.20, 13.10),
    ('kind1',    7.35, 5.35, 10.80,  9.20),
    ('kind2',    7.35, 9.35, 10.80, 13.10),
    ('master',  10.95, 5.35, 14.60, 13.10),
]


def bauen():
    leeren()

    m = {
        'beton':    material('Sichtbeton',   (0.60, 0.59, 0.57), 0.62),
        'putz':     material('Putz_Weiss',   (0.86, 0.85, 0.83), 0.70),
        'eiche':    material('Eiche_Lamelle', (0.46, 0.31, 0.17), 0.52),
        'alu':      material('Alu_Anthrazit', (0.085, 0.088, 0.092), 0.34, 0.70),
        'glas':     material('Glas', (0.86, 0.90, 0.92), 0.02, 0.0, 0.16, 0.92, 1.5),
        'travertin': material('Travertin',   (0.74, 0.70, 0.63), 0.58),
        'diele':    material('Eiche_Diele',  (0.50, 0.35, 0.21), 0.45),
        'stein':    material('Naturstein',   (0.30, 0.295, 0.285), 0.40),
        # Eigener Stein fuers Becken. Er war bis zum 20.09.2026 fast
        # schwarz (0,055 / 0,105 / 0,128) -- eine Notloesung aus der Zeit,
        # als das "Wasser" ein Quader im Stein war und man das Becken nur
        # ueber seine Farbe ueberhaupt vom Wasser unterscheiden konnte.
        # Mit einer echten Wasserflaeche (villa_schliff.py) ist genau das
        # falsch: Das Blau eines Pools kommt NICHT aus dem Wasser, sondern
        # aus der hellen Wanne darunter plus der Absorption ueber 1,5 m
        # Weg. Ein dunkles Becken ergibt ein schwarzes Loch. Also helles
        # graublaues Poolputz-Finish, poliert.
        'poolstein': material('Poolstein',   (0.415, 0.492, 0.512), 0.28),
        # 20.09.2026: Alpha 0,85 UND Transmission 0,85 gleichzeitig -- das
        # war der Grund, warum der Pool in der Cycles-Probe schwarz blieb.
        # material() schaltet bei Alpha < 1 auf blend_method 'BLEND', und
        # eine Alpha-Mischung legt einen Transparent-BSDF ueber den
        # Brechungsanteil. Ergebnis: keine echte Brechung, dafuer eine
        # Flaeche, die nur noch das Dunkle der Umgebung spiegelt.
        # Jetzt: voll durchlaessig, keine Alpha-Mischung, IOR 1,333.
        'wasser':   material('Wasser', (0.72, 0.90, 0.92), 0.02, 0.0, 1.0, 1.0, 1.333),
        'rasen':    material('Rasen',        (0.17, 0.27, 0.11), 0.94),
        # --- Bepflanzung (villa_pflanzen.py). Graubraune, tief zerfurchte
        #     Olivenrinde; Olivenlaub ist silbriggruen und deutlich
        #     heller als Zypressennadeln -- der Unterschied zwischen den
        #     beiden Gruen ist das, was einen sizilianischen Garten
        #     ueberhaupt als solchen lesbar macht. Agavenblatt ist
        #     blaeulich bereift, nicht gruen.
        'olivenrinde': material('Olivenrinde',  (0.235, 0.215, 0.188), 0.88),
        'olivenlaub':  material('Olivenlaub',   (0.094, 0.112, 0.062), 0.76),
        'agave':       material('Agavenblatt',  (0.148, 0.192, 0.132), 0.62),
        'kies':     material('Kies',         (0.44, 0.43, 0.41), 0.90),
        'estrich':  material('Estrich',      (0.55, 0.55, 0.54), 0.85),
        # --- Einrichtung. Sie steht in villa_moebel.py, aber die Materialien
        #     gehoeren hierher: ein Verzeichnis, eine Quelle, ein Export.
        'stoff':       material('Polsterstoff', (0.148, 0.152, 0.148), 0.88),
        'teppich':     material('Teppich',      (0.230, 0.205, 0.178), 0.94),
        'leuchte':     material('Leuchtenschirm', (0.92, 0.88, 0.80), 0.42),
        'pflanze':     material('Blattgruen',   (0.048, 0.118, 0.038), 0.72),
        'kunst':       material('Kunstwerk',    (0.132, 0.148, 0.178), 0.55),
        'keramik':     material('Keramik',      (0.900, 0.898, 0.890), 0.18),
        'spiegel':     material('Spiegel',      (0.92, 0.94, 0.95), 0.03, 1.0),
        'bettwaesche': material('Bettwaesche',  (0.800, 0.790, 0.770), 0.80),
        # Eine einzige Akzentfarbe im ganzen Haus -- Kissen, Tischlaeufer.
        # Mehr als eine, und der Raum sieht dekoriert aus statt bewohnt.
        'akzent':      material('Akzent',       (0.340, 0.150, 0.088), 0.86),
    }

    g = {
        1: gruppe('01_Bodenplatte'),
        2: gruppe('02_Rohbau_EG'),
        3: gruppe('03_Geschossdecke'),
        4: gruppe('04_Rohbau_OG'),
        5: gruppe('05_Dach_Attika'),
        6: gruppe('06_Glas_Fassade'),
        7: gruppe('07_Innenausbau'),
        8: gruppe('08_Aussenanlage'),
    }

    # ------------------------------------------------ 01 Bodenplatte
    quader('platte', A_X0 - 0.30, A_Y0 - 0.30, -0.45,
           (A_X1 - A_X0) + 0.60, (A_Y1 - A_Y0) + 0.60, 0.45, g[1], m['beton'])

    # ------------------------------------------------ 02 Rohbau EG
    nord, sued = A_Y0 + W_AUSSEN / 2.0, A_Y1 - W_AUSSEN / 2.0
    west, ost = A_X0 + W_AUSSEN / 2.0, A_X1 - W_AUSSEN / 2.0

    O_NORD = [(2.20, 1.80, Z_EG, 3.00), (7.00, 9.00, 1.00, 2.80)]
    O_SUED = [(5.60, 3.60, 0.05, 3.15), (9.80, 7.60, 0.05, 3.15)]
    O_WEST = [(1.20, 1.60, 0.05, 3.15), (9.20, 0.90, 1.80, 2.90)]
    O_OST = [(4.20, 6.20, 0.05, 3.15)]

    wand('eg_nord', 'x', nord, A_X0, A_X1, W_AUSSEN, Z_EG, Z_DECKE, g[2],
         m['beton'], O_NORD)
    wand('eg_sued', 'x', sued, A_X0, A_X1, W_AUSSEN, Z_EG, Z_DECKE, g[2],
         m['beton'], O_SUED)
    wand('eg_west', 'y', west, A_Y0 + W_AUSSEN, A_Y1 - W_AUSSEN, W_AUSSEN,
         Z_EG, Z_DECKE, g[2], m['beton'], O_WEST)
    wand('eg_ost', 'y', ost, A_Y0 + W_AUSSEN, A_Y1 - W_AUSSEN, W_AUSSEN,
         Z_EG, Z_DECKE, g[2], m['beton'], O_OST)

    # Innenwaende EG
    wand('eg_iv1', 'y', 5.275, 0.40, 10.60, W_INNEN, Z_EG, Z_DECKE, g[2],
         m['putz'], [(1.40, 3.00, Z_EG, 2.80)])
    wand('eg_ih1', 'x', 8.675, 0.40, 5.20, W_INNEN, Z_EG, Z_DECKE, g[2],
         m['putz'], [(1.00, 0.95, Z_EG, 2.30), (3.40, 0.95, Z_EG, 2.30)])
    wand('eg_iv2', 'y', 2.625, 8.75, 10.60, W_INNEN, Z_EG, Z_DECKE, g[2],
         m['putz'], [])
    wand('eg_ih2', 'x', 3.925, 5.35, 9.63, W_INNEN, Z_EG, Z_DECKE, g[2],
         m['putz'], [(6.20, 2.20, Z_EG, 2.80)])
    wand('eg_iv3', 'y', 9.625, 4.00, 10.60, W_INNEN, Z_EG, Z_DECKE, g[2],
         m['putz'], [(5.20, 2.40, Z_EG, 2.80)])

    # ------------------------------------------- 03 Geschossdecke
    # Vier Streifen um den Luftraum, dazu die Auskragung nach Sueden.
    z0 = Z_DECKE
    quader('decke_w', A_X0, A_Y0, z0, LUFT_X0 - A_X0, A_Y1 - A_Y0, DECKE,
           g[3], m['beton'])
    quader('decke_n', LUFT_X0, A_Y0, z0, LUFT_X1 - LUFT_X0, LUFT_Y0 - A_Y0,
           DECKE, g[3], m['beton'])
    quader('decke_s', LUFT_X0, LUFT_Y1, z0, LUFT_X1 - LUFT_X0,
           A_Y1 - LUFT_Y1, DECKE, g[3], m['beton'])
    quader('decke_o', LUFT_X1, A_Y0, z0, A_X1 - LUFT_X1, A_Y1 - A_Y0, DECKE,
           g[3], m['beton'])
    quader('decke_auskragung', B_X0, A_Y1, z0, B_X1 - B_X0, B_Y1 - A_Y1,
           DECKE, g[3], m['beton'])

    # Attika auf dem freien Teil des EG-Dachs (Dachterrasse)
    for name, x0, y0, dx, dy in (
            ('attika_eg_n', A_X0, A_Y0, A_X1 - A_X0, 0.25),
            ('attika_eg_w', A_X0, A_Y0, 0.25, A_Y1 - A_Y0),
            ('attika_eg_o', A_X1 - 0.25, A_Y0, 0.25, A_Y1 - A_Y0),
            ('attika_eg_s1', A_X0, A_Y1 - 0.25, B_X0 - A_X0, 0.25),
            ('attika_eg_s2', B_X1, A_Y1 - 0.25, A_X1 - B_X1, 0.25)):
        quader(name, x0, y0, Z_OG, dx, dy, ATTIKA, g[3], m['beton'])

    return g, m


def og_bauen(g, m):
    """04 Rohbau OG und 05 Dach. Koerper B steht versetzt auf A und kragt
    2,50 m nach Sueden aus -- die Auskragung ist der Grund, warum dieses
    Haus teuer aussieht, und sie kostet hier nur eine Zahl."""
    nord, sued = B_Y0 + W_AUSSEN / 2.0, B_Y1 - W_AUSSEN / 2.0
    west, ost = B_X0 + W_AUSSEN / 2.0, B_X1 - W_AUSSEN / 2.0

    O_N = [(5.60, 8.60, Z_OG + 0.30, Z_OG + 2.00)]
    O_S = [(7.50, 3.10, Z_OG + 0.05, Z_OG + 2.75),
           (11.10, 3.40, Z_OG + 0.05, Z_OG + 2.75)]
    O_W = [(9.60, 1.20, Z_OG + 1.30, Z_OG + 2.50)]
    O_O = [(6.00, 6.80, Z_OG + 0.05, Z_OG + 2.75)]

    wand('og_nord', 'x', nord, B_X0, B_X1, W_AUSSEN, Z_OG, Z_DACH, g[4],
         m['beton'], O_N)
    wand('og_sued', 'x', sued, B_X0, B_X1, W_AUSSEN, Z_OG, Z_DACH, g[4],
         m['beton'], O_S)
    wand('og_west', 'y', west, B_Y0 + W_AUSSEN, B_Y1 - W_AUSSEN, W_AUSSEN,
         Z_OG, Z_DACH, g[4], m['beton'], O_W)
    wand('og_ost', 'y', ost, B_Y0 + W_AUSSEN, B_Y1 - W_AUSSEN, W_AUSSEN,
         Z_OG, Z_DACH, g[4], m['beton'], O_O)

    # Innenwaende OG
    wand('og_iv1', 'y', 5.275, 8.60, 13.10, W_INNEN, Z_OG, Z_DACH, g[4],
         m['putz'], [(9.60, 0.95, Z_OG, Z_OG + 2.30)])
    wand('og_ih_bad', 'x', 8.675, 2.40, 5.20, W_INNEN, Z_OG, Z_DACH, g[4],
         m['putz'], [])
    wand('og_ih_gal', 'x', 5.275, 7.275, B_X1 - W_AUSSEN, W_INNEN, Z_OG,
         Z_DACH, g[4], m['putz'], [(12.00, 0.95, Z_OG, Z_OG + 2.30)])
    wand('og_iv2', 'y', 7.275, 5.35, 13.10, W_INNEN, Z_OG, Z_DACH, g[4],
         m['putz'], [(6.60, 0.95, Z_OG, Z_OG + 2.30),
                     (10.60, 0.95, Z_OG, Z_OG + 2.30)])
    wand('og_ih_k', 'x', 9.275, 7.35, 10.80, W_INNEN, Z_OG, Z_DACH, g[4],
         m['putz'], [])
    wand('og_iv3', 'y', 10.875, 5.35, 13.10, W_INNEN, Z_OG, Z_DACH, g[4],
         m['putz'], [])

    # Glasbruestung am Luftraum -- kein Mauerwerk. Eine Galerie, die man
    # nicht durchsieht, macht aus dem Luftraum einen Schacht.
    quader('og_bruestung_glas', 5.275 - 0.01, LUFT_Y0, Z_OG,
           0.02, LUFT_Y1 - LUFT_Y0, 1.05, g[4], m['glas'])
    quader('og_bruestung_holm', 5.275 - 0.03, LUFT_Y0, Z_OG + 1.05,
           0.06, LUFT_Y1 - LUFT_Y0, 0.05, g[4], m['alu'])

    # ---------------------------------------------- 05 Dach und Attika
    quader('dach_platte', B_X0, B_Y0, Z_DACH, B_X1 - B_X0, B_Y1 - B_Y0,
           DECKE, g[5], m['beton'])
    for name, x0, y0, dx, dy in (
            ('attika_og_n', B_X0, B_Y0, B_X1 - B_X0, 0.25),
            ('attika_og_s', B_X0, B_Y1 - 0.25, B_X1 - B_X0, 0.25),
            ('attika_og_w', B_X0, B_Y0, 0.25, B_Y1 - B_Y0),
            ('attika_og_o', B_X1 - 0.25, B_Y0, 0.25, B_Y1 - B_Y0)):
        quader(name, x0, y0, Z_DACH_OK, dx, dy, ATTIKA, g[5], m['beton'])


def glas_bauen(g, m):
    """06 Glas und Fassade. Erst jetzt wird aus dem Rohbau ein Haus."""
    nord, sued = A_Y0 + W_AUSSEN / 2.0, A_Y1 - W_AUSSEN / 2.0
    west, ost = A_X0 + W_AUSSEN / 2.0, A_X1 - W_AUSSEN / 2.0

    verglasung('gl_eg_n_band', 'x', nord, 7.00, 9.00, 1.00, 2.80, g[6],
               m['glas'], m['alu'], pfosten=2.25)
    verglasung('gl_eg_s_kueche', 'x', sued, 5.60, 3.60, 0.05, 3.15, g[6],
               m['glas'], m['alu'])
    verglasung('gl_eg_s_wohnen', 'x', sued, 9.80, 7.60, 0.05, 3.15, g[6],
               m['glas'], m['alu'], pfosten=2.53)
    verglasung('gl_eg_w_entree', 'y', west, 1.20, 1.60, 0.05, 3.15, g[6],
               m['glas'], m['alu'])
    verglasung('gl_eg_w_wc', 'y', west, 9.20, 0.90, 1.80, 2.90, g[6],
               m['glas'], m['alu'])
    verglasung('gl_eg_o_wohnen', 'y', ost, 4.20, 6.20, 0.05, 3.15, g[6],
               m['glas'], m['alu'], pfosten=2.07)

    b_nord, b_sued = B_Y0 + W_AUSSEN / 2.0, B_Y1 - W_AUSSEN / 2.0
    b_west, b_ost = B_X0 + W_AUSSEN / 2.0, B_X1 - W_AUSSEN / 2.0
    verglasung('gl_og_n_galerie', 'x', b_nord, 5.60, 8.60, Z_OG + 0.30,
               Z_OG + 2.00, g[6], m['glas'], m['alu'], pfosten=2.15)
    verglasung('gl_og_s_kind2', 'x', b_sued, 7.50, 3.10, Z_OG + 0.05,
               Z_OG + 2.75, g[6], m['glas'], m['alu'])
    verglasung('gl_og_s_master', 'x', b_sued, 11.10, 3.40, Z_OG + 0.05,
               Z_OG + 2.75, g[6], m['glas'], m['alu'])
    verglasung('gl_og_w_bad', 'y', b_west, 9.60, 1.20, Z_OG + 1.30,
               Z_OG + 2.50, g[6], m['glas'], m['alu'])
    verglasung('gl_og_o_master', 'y', b_ost, 6.00, 6.80, Z_OG + 0.05,
               Z_OG + 2.75, g[6], m['glas'], m['alu'], pfosten=2.27)

    # Haustuer: Eiche, raumhoch, drehbar gelagert
    quader('haustuer', 2.24, nord - 0.06, Z_EG, 1.72, 0.12, 2.98, g[6],
           m['eiche'])
    quader('haustuer_griff', 3.78, nord - 0.10, Z_EG + 1.00, 0.04, 0.04,
           1.20, g[6], m['alu'])

    # Eichenlamellen: links neben dem Eingang und als Blende vor dem
    # Bandfenster-Sturz. Sie geben der 18 m langen Nordwand einen Massstab.
    lamellen('lam_nord_links', 'x', A_Y0 - 0.06, 0.30, 2.10, Z_EG, 3.10,
             g[6], m['eiche'])
    lamellen('lam_nord_rechts', 'x', A_Y0 - 0.06, 4.20, 6.80, Z_EG, 3.10,
             g[6], m['eiche'])
    lamellen('lam_west', 'y', A_X0 - 0.06, 3.40, 8.60, Z_EG, 3.10,
             g[6], m['eiche'])


def ausbau_bauen(g, m):
    """07 Innenausbau: Boeden, die schwebende Treppe, Tuerblaetter, die
    Kuechenzeile und der Kamin. Ohne Moebel ist ein Rundgang ein Rohbau --
    und ein Rohbau verkauft keine 1,2 Millionen."""
    def boden(name, r, z, mat):
        quader(name, r[1], r[2], z, r[3] - r[1], r[4] - r[2], 0.02, g[7], mat)

    for r in RAEUME_EG:
        boden('boden_eg_' + r[0], r, Z_EG,
              m['travertin'] if r[0] in ('entree', 'wc', 'technik') else m['diele'])
    for r in RAEUME_OG:
        boden('boden_og_' + r[0], r, Z_OG,
              m['stein'] if r[0] == 'bad' else m['diele'])

    # ---- Schwebende Treppe im Luftraum: Kragstufen aus der Wand
    stufen = 18
    steig = (Z_OG - Z_EG) / stufen
    auftritt = 0.285
    for i in range(stufen):
        quader('treppe_stufe_%02d' % i, 2.55, LUFT_Y0 + 0.20 + i * auftritt,
               Z_EG + (i + 1) * steig - 0.05,
               1.30, auftritt - 0.02, 0.05, g[7], m['eiche'])
    quader('treppe_wange', 2.42, LUFT_Y0 + 0.10, Z_EG,
           0.13, stufen * auftritt + 0.20, Z_OG - Z_EG, g[7], m['beton'])
    quader('treppe_glas', 3.86, LUFT_Y0 + 0.20, Z_EG,
           0.02, stufen * auftritt, Z_OG + 1.05 - Z_EG, g[7], m['glas'])

    # ---- Kamin als Scheibe im Wohnraum. Er ist Architektur, kein Moebel --
    # deshalb steht er hier und nicht in villa_moebel.py.
    quader('kamin', 9.80, 4.20, Z_EG, 0.30, 2.60, 2.60, g[7], m['stein'])
    quader('kamin_oeffnung', 9.74, 4.90, Z_EG + 0.45, 0.10, 1.20, 0.70,
           g[7], m['alu'])

    # ---- Sockelleisten dort, wo man sie im Rundgang sieht. Ohne sie stossen
    # Wand und Boden auf Null zusammen, und genau diese Nullfuge ist eines
    # der zuverlaessigsten Erkennungszeichen eines Renderings.
    for name, x0, y0, dx, dy in (
            ('sockel_wohnen_n', 9.70, 4.00, 7.90, 0.018),
            ('sockel_wohnen_w', 9.70, 4.00, 0.018, 6.60),
            ('sockel_essen_n', 5.35, 0.40, 12.25, 0.018),
            ('sockel_kueche_w', 5.35, 4.00, 0.018, 6.60)):
        quader(name, x0, y0, Z_EG + 0.02, dx, dy, 0.075, g[7], m['putz'])

    # Tuerblaetter
    for name, achse, lage, pos, z in (
            ('tuer_wc', 'x', 8.675, 1.00, Z_EG),
            ('tuer_technik', 'x', 8.675, 3.40, Z_EG),
            ('tuer_bad', 'y', 5.275, 9.60, Z_OG),
            ('tuer_master', 'x', 5.275, 12.00, Z_OG),
            ('tuer_kind1', 'y', 7.275, 6.60, Z_OG),
            ('tuer_kind2', 'y', 7.275, 10.60, Z_OG)):
        if achse == 'x':
            quader(name, pos + 0.02, lage - 0.02, z, 0.91, 0.04, 2.28,
                   g[7], m['eiche'])
        else:
            quader(name, lage - 0.02, pos + 0.02, z, 0.04, 0.91, 2.28,
                   g[7], m['eiche'])


def aussen_bauen(g, m):
    """08 Aussenanlage: Terrasse, Pool, Rasen, Zufahrt. Ein Haus ohne
    Umgebung schwebt -- und der Pool ist hier kein Schmuck, sondern das,
    was die Preisklasse ueberhaupt erst zeigt."""
    # 78 x 70 m waren zu klein: Dahinter sah man den Himmel unter dem
    # Horizont als harte blaue Kante -- ein "Meer", das es nicht gibt, und
    # das erste, was an dem Bild falsch aussah. 500 m reichen bis hinter
    # den Bildrand jeder Kameraeinstellung.
    quader('rasen', -240.0, -230.0, -0.50, 500.0, 500.0, 0.12, g[8], m['rasen'])
    quader('terrasse', A_X0 - 1.20, A_Y1, -0.06, (A_X1 - A_X0) + 2.40,
           4.00, 0.06, g[8], m['travertin'])
    quader('vorplatz', A_X0 - 1.00, A_Y0 - 7.00, -0.06, 9.00, 7.00, 0.06,
           g[8], m['kies'])

    # Pool: Becken, Wasser, Randstein
    quader('pool_becken', 5.80, 14.80, -1.55, 10.40, 4.40, 1.50, g[8], m['poolstein'])
    quader('pool_wasser', 6.00, 15.00, -1.45, 10.00, 4.00, 1.32, g[8], m['wasser'])
    for name, x0, y0, dx, dy in (
            ('pool_rand_n', 5.80, 14.60, 10.40, 0.40),
            ('pool_rand_s', 5.80, 19.00, 10.40, 0.40),
            ('pool_rand_w', 5.40, 14.60, 0.40, 4.80),
            ('pool_rand_o', 16.20, 14.60, 0.40, 4.80)):
        quader(name, x0, y0, -0.08, dx, dy, 0.08, g[8], m['travertin'])

    # Sichtbetonmauer als Abschluss nach Westen -- gibt dem Garten eine
    # Kante und verdeckt die Nachbargrenze. Sie steht seitlich, nicht im
    # Blick auf die Terrasse.
    quader('gartenmauer', -2.40, -6.00, -0.10, 0.30, 26.00, 2.20, g[8],
           m['beton'])

    # Zypressen statt Stangen. Ein Kegel je Baum, dunkelgruen, unregelmaessig
    # hoch -- sieben gleich hohe Saeulen sehen aus wie ein Zaun, und genau so
    # sah der erste Versuch aus.
    m_zypresse = material('Zypresse', (0.055, 0.105, 0.048), 0.90)
    hoehen = (5.8, 4.9, 6.4, 5.2, 6.0, 4.6, 5.6, 6.2)
    for i, h in enumerate(hoehen):
        x = -1.10 + i * 2.55
        kegel('zypresse_%d' % i, x, 21.40 + (i % 3) * 0.55, 0.0,
              0.62, h, g[8], m_zypresse)
        quader('zypresse_stamm_%d' % i, x - 0.09, 21.31 + (i % 3) * 0.55, 0.0,
               0.18, 0.18, 0.55, g[8], m['eiche'])


# ------------------------------------------------- Abschnitte und Daten

ABSCHNITTE = [
    ('01_Bodenplatte',   'Bodenplatte'),
    ('02_Rohbau_EG',     'Rohbau Erdgeschoss'),
    ('03_Geschossdecke', 'Decke und Auskragung'),
    ('04_Rohbau_OG',     'Rohbau Obergeschoss'),
    ('05_Dach_Attika',   'Dach und Attika'),
    ('06_Glas_Fassade',  'Glas und Fassade'),
    ('07_Innenausbau',   'Innenausbau'),
    ('08_Aussenanlage',  'Aussenanlage'),
]


def abschnitte_ordnen():
    """Jedes Objekt bekommt den Abschnittspraefix in den NAMEN.

    glTF nimmt Collections nicht mit, Knotennamen schon. Die Webseite
    gruppiert spaeter nach 'p01_', 'p02_' -- ohne Praefix braeuchte sie eine
    Namensliste, die bei jeder Aenderung am Haus veraltet."""
    zahl = {}
    for i, (name, _) in enumerate(ABSCHNITTE, start=1):
        c = bpy.data.collections.get(name)
        if c is None:
            print('[villa] Abschnitt fehlt: %s' % name)
            continue
        for ob in c.objects:
            if not ob.name.startswith('p%02d_' % i):
                ob.name = 'p%02d_%s' % (i, ob.name)
        zahl[name] = len(c.objects)
    return zahl


def daten():
    def fl(r):
        return round((r[3] - r[1]) * (r[4] - r[2]), 2)

    def liste(rs, z):
        return [{'schluessel': r[0], 'x0': r[1], 'y0': r[2], 'x1': r[3],
                 'y1': r[4], 'flaeche': fl(r), 'z': z} for r in rs]

    return {
        'einheit': 'Meter',
        'koerper': {'eg': {'x0': A_X0, 'y0': A_Y0, 'x1': A_X1, 'y1': A_Y1},
                    'og': {'x0': B_X0, 'y0': B_Y0, 'x1': B_X1, 'y1': B_Y1}},
        'luftraum': {'x0': LUFT_X0, 'y0': LUFT_Y0, 'x1': LUFT_X1, 'y1': LUFT_Y1},
        'hoehen': {'eg': Z_EG, 'decke': Z_DECKE, 'og': Z_OG, 'dach': Z_DACH,
                   'dach_ok': Z_DACH_OK, 'attika': Z_DACH_OK + ATTIKA,
                   'raum_eg': H_EG, 'raum_og': H_OG, 'augen': 1.65},
        'geschosse': [
            {'schluessel': 'eg', 'z': Z_EG, 'raeume': liste(RAEUME_EG, Z_EG),
             'flaeche': round(sum(fl(r) for r in RAEUME_EG), 1)},
            {'schluessel': 'og', 'z': Z_OG, 'raeume': liste(RAEUME_OG, Z_OG),
             'flaeche': round(sum(fl(r) for r in RAEUME_OG), 1)},
        ],
        'abschnitte': [{'nummer': i, 'praefix': 'p%02d_' % i,
                        'sammlung': name, 'titel': titel}
                       for i, (name, titel) in enumerate(ABSCHNITTE, start=1)],
        # Tuerdurchgaenge: dort darf die Kamera zwischen zwei Raeumen wechseln
        'durchgaenge': [
            {'z': Z_EG, 'x0': 6.75, 'y0': 1.40, 'x1': 9.75, 'y1': 4.40},
            {'z': Z_EG, 'x0': 1.00, 'y0': 8.55, 'x1': 1.95, 'y1': 8.80},
            {'z': Z_EG, 'x0': 3.40, 'y0': 8.55, 'x1': 4.35, 'y1': 8.80},
            {'z': Z_EG, 'x0': 6.20, 'y0': 3.80, 'x1': 8.40, 'y1': 4.05},
            {'z': Z_EG, 'x0': 9.50, 'y0': 5.20, 'x1': 9.75, 'y1': 7.60},
            {'z': Z_EG, 'x0': 5.15, 'y0': 1.40, 'x1': 5.40, 'y1': 4.40},
            {'z': Z_OG, 'x0': 5.15, 'y0': 9.60, 'x1': 5.40, 'y1': 10.55},
            {'z': Z_OG, 'x0': 12.00, 'y0': 5.15, 'x1': 12.95, 'y1': 5.40},
            {'z': Z_OG, 'x0': 7.15, 'y0': 6.60, 'x1': 7.40, 'y1': 7.55},
            {'z': Z_OG, 'x0': 7.15, 'y0': 10.60, 'x1': 7.40, 'y1': 11.55},
        ],
        # Wegpunkte des gefuehrten Rundgangs (x, y, Blick in Grad, Geschoss)
        'rundgang': [
            {'raum': 'ankunft', 'x': 3.10, 'y': -5.40, 'blick': 0,   'z': Z_EG},
            {'raum': 'entree',  'x': 3.10, 'y':  2.00, 'blick': 10,  'z': Z_EG},
            {'raum': 'entree',  'x': 3.60, 'y':  6.20, 'blick': 95,  'z': Z_EG},
            {'raum': 'essen',   'x': 8.60, 'y':  2.10, 'blick': 75,  'z': Z_EG},
            {'raum': 'wohnen',  'x': 13.40, 'y': 6.20, 'blick': 165, 'z': Z_EG},
            {'raum': 'wohnen',  'x': 13.80, 'y': 9.40, 'blick': 190, 'z': Z_EG},
            {'raum': 'kueche',  'x': 7.40, 'y':  8.20, 'blick': 250, 'z': Z_EG},
            {'raum': 'galerie', 'x': 6.30, 'y':  4.30, 'blick': 265, 'z': Z_OG},
            {'raum': 'master',  'x': 12.70, 'y': 9.20, 'blick': 175, 'z': Z_OG},
            {'raum': 'master',  'x': 12.70, 'y': 12.20, 'blick': 185, 'z': Z_OG},
        ],
    }


def kanten_brechen(breite=0.006, segmente=2):
    """Eine Fase auf jedes Bauteil.

    DAS IST DER GROESSTE EINZELNE SCHRITT ZUM FOTOREALISMUS -- groesser als
    jede Textur. In der Wirklichkeit gibt es keine mathematisch scharfe
    Kante: Jede Betonkante hat einen Radius, jede Tuerzarge ist gebrochen,
    jede Tischplatte gefast. Auf einer scharfen Kante trifft das Licht auf
    genau null Flaeche und erzeugt kein Glanzlicht. Das Auge liest das
    sofort als "am Rechner gemacht", ohne sagen zu koennen, warum.

    6 mm, zwei Segmente. Mehr kostet Dreiecke, ohne dass man es sieht;
    weniger sieht man auf drei Meter Abstand nicht mehr.

    Die Fase funktioniert nur, weil die Bauteile ihre echte Groesse im NETZ
    tragen und die Objektskalierung (1, 1, 1) ist -- siehe quader().
    Winkel 35 Grad laesst die Kegel der Zypressen in Ruhe."""
    gefast = 0
    for ob in bpy.data.objects:
        if ob.type != 'MESH':
            continue
        if ob.name.startswith(('rasen', 'p09_rasen')):
            continue                      # eine 78-m-Flaeche braucht keine Fase
        # WAS SCHON GLATT SCHATTIERT IST, BLEIBT ES.
        #
        # Das hier war ein stiller Fehler, der lange im Bild stand: Die
        # Schleife weiter unten setzt use_smooth auf ALLEN Polygonen auf
        # False -- auch bei Bauteilen, die vorher ausdruecklich glatt
        # schattiert wurden. Betroffen waren die Polster aus
        # villa_moebel.polster() (Sofa, Kissen, Matratze, Decke): Sie
        # werden gefast, unterteilt und verdraengt, damit sie WEICH
        # aussehen -- und wurden anschliessend facettiert. Ein Kissen mit
        # hundert sichtbaren Facetten ist kein weiches Kissen.
        # Dazu kaeme jetzt die Wasserflaeche mit 33.280 Dreiecken als
        # Facettenspiegel und jede Baumkrone als Papierlaterne.
        #
        # Die Regel haengt bewusst nicht an Namen: Wer glatt schattiert
        # hat, hat sich dabei etwas gedacht, und die naechste Baugruppe
        # soll nicht in eine Namensliste eingetragen werden muessen.
        if any(p.use_smooth for p in ob.data.polygons):
            continue
        if 'wasser' in ob.name or 'gelaende' in ob.name:
            continue
        mod = ob.modifiers.new('Fase', 'BEVEL')
        mod.width = breite
        mod.segments = segmente
        mod.limit_method = 'ANGLE'
        mod.angle_limit = math.radians(35.0)
        mod.harden_normals = False
        gefast += 1
        # Bewusst FLACH schattiert. In Blender 4.1 ist use_auto_smooth
        # weggefallen, und der Ersatz ist ein Geometrieknoten-Modifikator,
        # den man nicht mit modifiers.new() anlegen kann. Braucht es hier
        # auch nicht: An einem Gebaeude ist die Kante eine FASE und kein
        # Radius -- zwei flache Segmente sind die richtige Antwort und
        # ueberstehen jeden Versionswechsel.
        for p in ob.data.polygons:
            p.use_smooth = False
    return gefast


def haupt(speichern=True, moebel=True, fasen=True):
    g, m = bauen()
    og_bauen(g, m)
    glas_bauen(g, m)
    ausbau_bauen(g, m)
    aussen_bauen(g, m)
    if moebel:
        import sys
        import os as _os
        hier = _os.path.dirname(_os.path.abspath(__file__))
        if hier not in sys.path:
            sys.path.insert(0, hier)
        pfad = _os.path.join(hier, 'villa_moebel.py')
        ns = {'__name__': 'villa_moebel_modul', '__file__': pfad}
        exec(compile(open(pfad, encoding='utf-8').read(), pfad, 'exec'), ns)
        ns['einrichten'](g[7], m)
        ns['einrichten_og'](g[7], m, Z_OG)
        ns['dekor'](g[7], m, Z_OG)

        # Laibungen, Fensterbaenke, zweite Scheibe, Entwaesserung,
        # Installationen, Kiesstreifen. Ohne diese Stuecke ist das Haus die
        # Summe seiner Quader -- siehe villa_detail.py.
        pfad2 = _os.path.join(hier, 'villa_detail.py')
        ns2 = {'__name__': 'villa_detail_modul', '__file__': pfad2}
        exec(compile(open(pfad2, encoding='utf-8').read(), pfad2, 'exec'), ns2)
        ns2['alles'](g[6], g[8], m)

        # Feinschliff: Pool als echte Wanne mit Wasserflaeche (der alte
        # Massivquader hat das Wasser vollstaendig verdeckt -- siehe
        # villa_schliff.py), Laibungsanschlaege, fehlende Tropfkanten.
        # Muss NACH villa_detail laufen: es benutzt dessen Oeffnungsliste
        # und loescht Bauteile, die vorher dastehen.
        pfad3 = _os.path.join(hier, 'villa_schliff.py')
        ns3 = {'__name__': 'villa_schliff_modul', '__file__': pfad3}
        exec(compile(open(pfad3, encoding='utf-8').read(), pfad3, 'exec'), ns3)
        ns3['alles'](g[6], g[8], m, ns2['OEFFNUNGEN'])

        # Die Bepflanzung. Rasen plus acht Zypressen ist kein
        # sizilianischer Garten -- siehe Kopf von villa_pflanzen.py.
        pfad4 = _os.path.join(hier, 'villa_pflanzen.py')
        ns4 = {'__name__': 'villa_pflanzen_modul', '__file__': pfad4}
        exec(compile(open(pfad4, encoding='utf-8').read(), pfad4, 'exec'), ns4)
        ns4['bepflanzen'](g[8], m)
    zahl = abschnitte_ordnen()
    if fasen:
        kanten_brechen()

    dreiecke = 0
    for ob in bpy.data.objects:
        if ob.type == 'MESH':
            ob.data.calc_loop_triangles()
            dreiecke += len(ob.data.loop_triangles)

    d = daten()
    d['objekte'] = len(bpy.data.objects)
    d['dreiecke'] = dreiecke
    d['je_abschnitt'] = zahl

    ordner = 'C:/Users/manue/Desktop/Vecom Design/3d-produktion/web-export'
    os.makedirs(ordner, exist_ok=True)
    with open(os.path.join(ordner, 'haus-grundriss.json'), 'w',
              encoding='utf-8') as f:
        json.dump(d, f, ensure_ascii=False, indent=2, sort_keys=True)

    print('[villa] %d Objekte, %d Dreiecke' % (d['objekte'], dreiecke))
    for name, n in sorted(zahl.items()):
        print('[villa]   %-20s %4d' % (name, n))

    if speichern:
        ziel = 'C:/Users/manue/Desktop/Vecom Design/3d-produktion/vecom-villa.blend'
        bpy.ops.wm.save_as_mainfile(filepath=ziel)
        print('[villa] gespeichert: %s' % ziel)
    return d


if __name__ == '__main__':
    haupt()
