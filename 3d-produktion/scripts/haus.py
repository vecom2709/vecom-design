# -*- coding: utf-8 -*-
"""
haus.py — Ein Einfamilienhaus, parametrisch, in Bauabschnitten.

WOZU DAS GEBAUT WIRD
Im Labor auf vecom-design.it soll der Besucher drei Dinge an EINEM Haus
sehen, die im Netz sonst nur behauptet werden:

  1. Grundriss  Der Plan liegt flach da und richtet sich zum Haus auf.
  2. Bauablauf  Fundament, Waende, Decke, Dach, Fenster, Ausbau -- Schritt
                fuer Schritt, mit dem Regler nach vorn und zurueck.
  3. Begehung   Kamera auf 1,65 m, durch die Tuer, durch die Raeume.

Daraus folgt der ganze Aufbau dieser Datei: Jedes Bauteil gehoert zu genau
einem Abschnitt, und jeder Abschnitt ist eine eigene Collection mit einem
Namenspraefix. Die Webseite blendet dann Praefix fuer Praefix ein. Wer ein
Haus als EIN Netz exportiert, kann es nur zeigen oder verbergen.

MASSE
Aussenmass 11,00 x 9,00 m. Aussenwand 0,30 m, Innenwand 0,115 m.
Lichte Hoehe 2,60 m, Decke 0,22 m. Zwei Geschosse, Satteldach 30 Grad.
Alles in Metern, Z ist oben (Blender). Der Export dreht auf Y-oben.

WARUM KEINE BOOLESCHEN OPERATIONEN FUER DIE OEFFNUNGEN
Fenster- und Tueroeffnungen entstehen hier, indem die Wand in Stuecke
zerlegt wird (links, rechts, Bruestung, Sturz). Boolesche Schnitte auf
huderten Waenden erzeugen n-Gone, die beim Triangulieren im Browser
zerfallen -- und jede Naht sieht man im Streiflicht. Vier Quader je
Oeffnung sind mehr Objekte und weniger Aerger.

Lauf:  blender -b -P scripts/haus.py
       oder im offenen Blender:  exec(open(pfad).read())
"""

import bpy
import bmesh
import json
import math
import os

# ----------------------------------------------------------------- Masse

WAND_AUSSEN = 0.30
WAND_INNEN = 0.115
HOEHE = 2.60          # lichte Raumhoehe
DECKE = 0.22          # Geschossdecke
BODENPLATTE = 0.30
BREITE = 11.00        # X, aussen
TIEFE = 9.00          # Y, aussen
DACH_NEIGUNG = math.radians(30.0)
DACH_UEBERSTAND = 0.55

# Oberkanten der Geschosse ueber Gelaende (0.0 = Gelaende)
Z_EG = 0.0                       # Oberkante Bodenplatte / Fussboden EG
Z_DECKE = Z_EG + HOEHE           # Unterkante Geschossdecke
Z_OG = Z_DECKE + DECKE           # Fussboden OG
Z_TRAUFE = Z_OG + HOEHE          # Oberkante OG-Wand

TUER_B, TUER_H = 0.885, 2.010    # Normtuer
HAUSTUER_B, HAUSTUER_H = 1.10, 2.10
FENSTER_BRUESTUNG = 0.90
FENSTER_STURZ = 2.20             # Oberkante Fensteroeffnung


# ------------------------------------------------------------ Werkzeuge

def leeren():
    """Szene leeren -- auch Datenbloecke, sonst waechst die Datei bei jedem
    Lauf um einen kompletten Satz verwaister Meshes."""
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for sammlung in list(bpy.data.collections):
        bpy.data.collections.remove(sammlung)
    for block in (bpy.data.meshes, bpy.data.materials, bpy.data.images):
        for d in list(block):
            if d.users == 0:
                block.remove(d)


def gruppe(name):
    """Collection anlegen und in die Szene haengen."""
    if name in bpy.data.collections:
        return bpy.data.collections[name]
    c = bpy.data.collections.new(name)
    bpy.context.scene.collection.children.link(c)
    return c


def quader(name, mitte, mass, sammlung, material=None):
    """Ein achsparalleler Quader. mitte = (x, y, z) des Mittelpunkts,
    mass = (dx, dy, dz) der Kantenlaengen."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    ob.location = mitte
    ob.scale = mass
    sammlung.objects.link(ob)
    if material is not None:
        ob.data.materials.append(material)
    return ob


def keil(name, ecken, tiefe_y, y0, sammlung, material=None):
    """Ein Prisma: Polygon in der XZ-Ebene, in Y extrudiert. Fuer Giebel
    und Dachflaechen -- dort, wo ein Quader nicht reicht."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    unten = [bm.verts.new((x, y0, z)) for (x, z) in ecken]
    bm.faces.new(unten)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces[:])
    bmesh.ops.solidify(bm, geom=bm.faces[:], thickness=-tiefe_y)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    sammlung.objects.link(ob)
    if material is not None:
        ob.data.materials.append(material)
    return ob


def material(name, farbe, rauheit=0.85, metall=0.0, alpha=1.0, ior=1.45):
    """Ein schlichtes PBR-Material. Absichtlich ohne Knotenwerk: Was ein
    glTF nicht mitnehmen kann, waere in Blender huebsch und im Browser weg."""
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
    if alpha < 1.0:
        b.inputs['Alpha'].default_value = alpha
        m.blend_method = 'BLEND'
    return m


# ------------------------------------------------------ Wand mit Oeffnung

def wand(name, p1, p2, dicke, z_unten, z_oben, sammlung, mat,
         oeffnungen=None):
    """Eine gerade Wand von p1 nach p2 (beides (x, y)), aufgeteilt an ihren
    Oeffnungen.

    oeffnungen: Liste von (abstand_von_p1, breite, z_unten, z_oben).

    Die Wand laeuft immer achsparallel -- das genuegt fuer ein Haus und
    spart die Drehmatrix. Laenge ist der Abstand, Querrichtung die andere
    Achse.
    """
    (x1, y1), (x2, y2) = p1, p2
    waagerecht = abs(x2 - x1) > abs(y2 - y1)
    laenge = abs(x2 - x1) if waagerecht else abs(y2 - y1)
    anfang = min(x1, x2) if waagerecht else min(y1, y2)
    quer = y1 if waagerecht else x1

    oeffnungen = sorted(oeffnungen or [], key=lambda o: o[0])
    teile = []

    def stueck(s0, s1, zu, zo, kennung):
        if s1 - s0 < 0.004 or zo - zu < 0.004:
            return
        mitte_l = anfang + (s0 + s1) / 2.0
        if waagerecht:
            mitte = (mitte_l, quer, (zu + zo) / 2.0)
            mass = (s1 - s0, dicke, zo - zu)
        else:
            mitte = (quer, mitte_l, (zu + zo) / 2.0)
            mass = (dicke, s1 - s0, zo - zu)
        teile.append(quader('%s_%s' % (name, kennung), mitte, mass,
                            sammlung, mat))

    cursor = 0.0
    for i, (ab, br, ou, oo) in enumerate(oeffnungen):
        stueck(cursor, ab, z_unten, z_oben, 'p%d' % i)          # Pfeiler
        stueck(ab, ab + br, z_unten, min(ou, z_oben), 'b%d' % i)  # Bruestung
        stueck(ab, ab + br, max(oo, z_unten), z_oben, 's%d' % i)  # Sturz
        cursor = ab + br
    stueck(cursor, laenge, z_unten, z_oben, 'e')

    return teile


def scheibe(name, p1, p2, ab, breite, zu, zo, dicke, sammlung, mat):
    """Die Verglasung in einer Oeffnung. Eigenes Objekt, eigenes Material --
    Glas muss im Browser getrennt schaltbar bleiben (Spiegelung, Tageszeit)."""
    (x1, y1), (x2, y2) = p1, p2
    waagerecht = abs(x2 - x1) > abs(y2 - y1)
    anfang = min(x1, x2) if waagerecht else min(y1, y2)
    quer = y1 if waagerecht else x1
    mitte_l = anfang + ab + breite / 2.0
    if waagerecht:
        mitte = (mitte_l, quer, (zu + zo) / 2.0)
        mass = (breite, dicke, zo - zu)
    else:
        mitte = (quer, mitte_l, (zu + zo) / 2.0)
        mass = (dicke, breite, zo - zu)
    return quader(name, mitte, mass, sammlung, mat)


def rahmen(name, p1, p2, ab, breite, zu, zo, dicke, sammlung, mat, stark=0.06):
    """Blendrahmen als vier schmale Leisten. Ohne ihn sitzt die Scheibe
    kantenlos im Mauerwerk und das Haus sieht aus wie ein Modell."""
    (x1, y1), (x2, y2) = p1, p2
    waagerecht = abs(x2 - x1) > abs(y2 - y1)
    anfang = min(x1, x2) if waagerecht else min(y1, y2)
    quer = y1 if waagerecht else x1
    l0 = anfang + ab
    raus = []

    def leiste(a, b, u, o, kennung):
        m_l = (a + b) / 2.0
        if waagerecht:
            mitte = (m_l, quer, (u + o) / 2.0)
            mass = (b - a, dicke, o - u)
        else:
            mitte = (quer, m_l, (u + o) / 2.0)
            mass = (dicke, b - a, o - u)
        raus.append(quader('%s_%s' % (name, kennung), mitte, mass,
                           sammlung, mat))

    leiste(l0, l0 + breite, zu, zu + stark, 'unten')
    leiste(l0, l0 + breite, zo - stark, zo, 'oben')
    leiste(l0, l0 + stark, zu + stark, zo - stark, 'links')
    leiste(l0 + breite - stark, l0 + breite, zu + stark, zo - stark, 'rechts')
    return raus


# ------------------------------------------------------------- Grundriss
#
# Nullpunkt ist die vordere linke Aussenecke. X laeuft nach rechts (11,00 m),
# Y nach hinten in den Garten (9,00 m). Vorn liegt die Strasse.
#
# Die Raumliste ist nicht nur Dokumentation: Aus ihr entstehen spaeter der
# Grundriss im Browser UND die Flaechen, auf denen sich die Kamera bei der
# Begehung bewegen darf. Ein Haus, dessen Plan und dessen Begehung aus zwei
# verschiedenen Quellen kommen, laeuft irgendwann auseinander.

# (schluessel, x0, y0, x1, y1)  -- lichte Innenmasse
RAEUME_EG = [
    ('diele',   0.30, 0.30,  4.90, 4.00),
    ('wc',      0.30, 4.12,  1.90, 6.00),
    ('hwr',     0.30, 6.12,  1.90, 8.70),
    ('kueche',  2.02, 4.12,  4.90, 8.70),
    ('wohnen',  5.02, 0.30, 10.70, 8.70),
]

RAEUME_OG = [
    ('flur',    0.30, 4.03, 10.70, 5.57),
    ('kind1',   0.30, 0.30,  3.60, 3.91),
    ('kind2',   3.72, 0.30,  7.00, 3.91),
    ('buero',   7.12, 0.30, 10.70, 3.91),
    ('bad',     0.30, 5.69,  3.60, 8.70),
    ('schlafen', 3.72, 5.69,  7.60, 8.70),
    ('ankleide', 7.72, 5.69, 10.70, 8.70),
]


def bauen():
    leeren()

    # ---------------------------------------------------------- Material
    m_beton   = material('Beton',        (0.52, 0.52, 0.50), 0.92)
    m_mauer   = material('Mauerwerk',    (0.72, 0.69, 0.64), 0.95)
    m_estrich = material('Estrich',      (0.60, 0.59, 0.57), 0.88)
    m_holz    = material('Holz_Konstruktion', (0.58, 0.42, 0.26), 0.80)
    m_ziegel  = material('Dachziegel',   (0.20, 0.20, 0.22), 0.72)
    m_glas    = material('Glas',         (0.78, 0.86, 0.90), 0.05, 0.0, 0.22, 1.5)
    m_rahmen  = material('Fensterrahmen', (0.14, 0.15, 0.16), 0.40, 0.55)
    m_tuer    = material('Tuerblatt',    (0.90, 0.89, 0.87), 0.55)
    m_haustuer = material('Haustuer',    (0.16, 0.18, 0.20), 0.38, 0.30)
    m_parkett = material('Parkett',      (0.52, 0.36, 0.22), 0.58)
    m_fliese  = material('Fliesen',      (0.74, 0.74, 0.73), 0.30)
    m_treppe  = material('Treppe',       (0.56, 0.40, 0.25), 0.52)
    m_terrasse = material('Terrasse',    (0.58, 0.56, 0.53), 0.78)
    m_gelaende = material('Gelaende',    (0.30, 0.36, 0.24), 0.96)

    g1 = gruppe('01_Fundament')
    g2 = gruppe('02_Waende_EG')
    g3 = gruppe('03_Decke')
    g4 = gruppe('04_Waende_OG')
    g5 = gruppe('05_Dach')
    g6 = gruppe('06_Fenster_Tueren')
    g7 = gruppe('07_Ausbau')
    g8 = gruppe('08_Aussen')

    # --------------------------------------------- 01 Fundament, Bodenplatte
    quader('fundament_platte',
           (BREITE / 2.0, TIEFE / 2.0, -BODENPLATTE / 2.0),
           (BREITE + 0.24, TIEFE + 0.24, BODENPLATTE), g1, m_beton)
    quader('fundament_sauberkeit',
           (BREITE / 2.0, TIEFE / 2.0, -BODENPLATTE - 0.04),
           (BREITE + 0.80, TIEFE + 0.80, 0.08), g1, m_beton)

    # ------------------------------------------------------ 02 Waende EG
    # Aussenwaende: vorn und hinten ueber die volle Breite, links und rechts
    # dazwischen -- so stossen die Ecken auf Stoss statt sich zu ueberlagern.
    V, H = 0.15, TIEFE - 0.15          # Mittellinien vorn/hinten
    L, R = 0.15, BREITE - 0.15         # Mittellinien links/rechts

    fenster_eg = []   # (wandname, p1, p2, ab, breite, zu, zo) fuer Glas+Rahmen

    def aussen(name, p1, p2, sammlung, z0, z1, oeff, glas=()):
        wand(name, p1, p2, WAND_AUSSEN, z0, z1, sammlung, m_mauer, oeff)
        for (ab, br, zu, zo, art) in glas:
            if art == 'glas':
                scheibe('glas_' + name + '_%0.2f' % ab, p1, p2, ab, br - 0.12,
                        zu + 0.06, zo - 0.06, 0.03, g6, m_glas)
                rahmen('rahmen_' + name + '_%0.2f' % ab, p1, p2, ab, br,
                       zu, zo, 0.10, g6, m_rahmen)

    # vorn (Strasse): Haustuer, Fenster Diele, grosses Fenster Wohnen
    o_vorn = [
        (1.10, HAUSTUER_B, 0.0, HAUSTUER_H),
        (2.60, 1.00, FENSTER_BRUESTUNG, FENSTER_STURZ),
        (6.00, 3.80, FENSTER_BRUESTUNG, FENSTER_STURZ),
    ]
    aussen('wand_eg_vorn', (0.0, V), (BREITE, V), g2, Z_EG, Z_DECKE, o_vorn,
           glas=[(2.60, 1.00, FENSTER_BRUESTUNG, FENSTER_STURZ, 'glas'),
                 (6.00, 3.80, FENSTER_BRUESTUNG, FENSTER_STURZ, 'glas')])
    quader('haustuer', (0.0 + 1.10 + HAUSTUER_B / 2.0, V, HAUSTUER_H / 2.0),
           (HAUSTUER_B - 0.04, 0.09, HAUSTUER_H - 0.03), g6, m_haustuer)

    # hinten (Garten): Kuechenfenster, Terrassentuer bodentief
    o_hinten = [
        (2.40, 2.00, FENSTER_BRUESTUNG, FENSTER_STURZ),
        (6.20, 3.60, 0.02, 2.36),
    ]
    aussen('wand_eg_hinten', (0.0, H), (BREITE, H), g2, Z_EG, Z_DECKE, o_hinten,
           glas=[(2.40, 2.00, FENSTER_BRUESTUNG, FENSTER_STURZ, 'glas'),
                 (6.20, 3.60, 0.02, 2.36, 'glas')])

    # links: WC und HWR, hochliegende schmale Fenster
    o_links = [
        (4.50, 0.60, 1.40, 2.10),
        (6.90, 0.70, 1.40, 2.10),
    ]
    aussen('wand_eg_links', (L, WAND_AUSSEN), (L, TIEFE - WAND_AUSSEN), g2,
           Z_EG, Z_DECKE, o_links,
           glas=[(4.50, 0.60, 1.40, 2.10, 'glas'),
                 (6.90, 0.70, 1.40, 2.10, 'glas')])

    # rechts: Wohnen, grosses Seitenfenster
    o_rechts = [(2.70, 3.40, FENSTER_BRUESTUNG, FENSTER_STURZ)]
    aussen('wand_eg_rechts', (R, WAND_AUSSEN), (R, TIEFE - WAND_AUSSEN), g2,
           Z_EG, Z_DECKE, o_rechts,
           glas=[(2.70, 3.40, FENSTER_BRUESTUNG, FENSTER_STURZ, 'glas')])


    # Innenwaende EG
    wand('iw_eg_h1', (0.30, 4.06), (4.96, 4.06), WAND_INNEN, Z_EG, Z_DECKE,
         g2, m_mauer, [(0.40, TUER_B, 0.0, TUER_H),        # WC
                       (2.30, TUER_B, 0.0, TUER_H)])       # Kueche
    wand('iw_eg_v1', (1.96, 4.06), (1.96, 8.70), WAND_INNEN, Z_EG, Z_DECKE,
         g2, m_mauer, [(2.60, TUER_B, 0.0, TUER_H)])       # HWR
    wand('iw_eg_h2', (0.30, 6.06), (1.96, 6.06), WAND_INNEN, Z_EG, Z_DECKE,
         g2, m_mauer, [])
    wand('iw_eg_v2', (4.96, 0.30), (4.96, 8.70), WAND_INNEN, Z_EG, Z_DECKE,
         g2, m_mauer, [(0.90, 1.60, 0.0, 2.20)])           # Durchgang Wohnen

    # -------------------------------------------------------- 03 Decke
    # Mit Loch fuer die Treppe. Vier Streifen statt eines Booleschen
    # Schnitts -- siehe Kopf der Datei.
    ox0, oy0, ox1, oy1 = 0.30, 0.30, 1.60, 4.00
    z_m = (Z_DECKE + Z_OG) / 2.0
    quader('decke_links',  (ox0 / 2.0, TIEFE / 2.0, z_m),
           (ox0, TIEFE, DECKE), g3, m_beton)
    quader('decke_vorn',   ((ox0 + ox1) / 2.0, oy0 / 2.0, z_m),
           (ox1 - ox0, oy0, DECKE), g3, m_beton)
    quader('decke_hinten', ((ox0 + ox1) / 2.0, (oy1 + TIEFE) / 2.0, z_m),
           (ox1 - ox0, TIEFE - oy1, DECKE), g3, m_beton)
    quader('decke_rechts', ((ox1 + BREITE) / 2.0, TIEFE / 2.0, z_m),
           (BREITE - ox1, TIEFE, DECKE), g3, m_beton)

    # ------------------------------------------------------ 04 Waende OG
    def og(name, p1, p2, oeff):
        """Oeffnungen im OG in absoluten Z-Werten."""
        wand(name, p1, p2, WAND_AUSSEN, Z_OG, Z_TRAUFE, g4, m_mauer,
             [(ab, br, Z_OG + zu, Z_OG + zo) for (ab, br, zu, zo) in oeff])
        for (ab, br, zu, zo) in oeff:
            scheibe('glas_%s_%0.2f' % (name, ab), p1, p2, ab, br - 0.12,
                    Z_OG + zu + 0.06, Z_OG + zo - 0.06, 0.03, g6, m_glas)
            rahmen('rahmen_%s_%0.2f' % (name, ab), p1, p2, ab, br,
                   Z_OG + zu, Z_OG + zo, 0.10, g6, m_rahmen)

    og('wand_og_vorn', (0.0, V), (BREITE, V), [
        (1.20, 1.40, 0.90, 2.20),      # Kind 1
        (4.60, 1.40, 0.90, 2.20),      # Kind 2
        (8.00, 1.60, 0.90, 2.20),      # Buero
    ])
    og('wand_og_hinten', (0.0, H), (BREITE, H), [
        (1.40, 1.00, 1.30, 2.20),      # Bad
        (4.60, 2.00, 0.90, 2.20),      # Schlafen
        (8.60, 1.20, 0.90, 2.20),      # Ankleide
    ])
    og('wand_og_links', (L, WAND_AUSSEN), (L, TIEFE - WAND_AUSSEN), [
        (4.10, 0.80, 1.20, 2.10),      # Flur
    ])
    og('wand_og_rechts', (R, WAND_AUSSEN), (R, TIEFE - WAND_AUSSEN), [
        (1.30, 1.40, 0.90, 2.20),      # Buero
        (6.10, 1.40, 0.90, 2.20),      # Ankleide
    ])

    # Innenwaende OG -- ein Flur quer durchs Haus, sechs Tueren
    wand('iw_og_h1', (0.30, 3.97), (10.70, 3.97), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [(1.30, TUER_B, Z_OG, Z_OG + TUER_H),
                       (4.70, TUER_B, Z_OG, Z_OG + TUER_H),
                       (8.20, TUER_B, Z_OG, Z_OG + TUER_H)])
    wand('iw_og_h2', (0.30, 5.63), (10.70, 5.63), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [(1.30, TUER_B, Z_OG, Z_OG + TUER_H),
                       (4.90, TUER_B, Z_OG, Z_OG + TUER_H),
                       (8.50, TUER_B, Z_OG, Z_OG + TUER_H)])
    wand('iw_og_v1', (3.66, 0.30), (3.66, 3.97), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [])
    wand('iw_og_v2', (7.06, 0.30), (7.06, 3.97), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [])
    wand('iw_og_v3', (3.66, 5.63), (3.66, 8.70), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [])
    wand('iw_og_v4', (7.66, 5.63), (7.66, 8.70), WAND_INNEN, Z_OG, Z_TRAUFE,
         g4, m_mauer, [])

    return dict(g1=g1, g2=g2, g3=g3, g4=g4, g5=g5, g6=g6, g7=g7, g8=g8,
                mat=dict(beton=m_beton, mauer=m_mauer, estrich=m_estrich,
                         holz=m_holz, ziegel=m_ziegel, glas=m_glas,
                         rahmen=m_rahmen, tuer=m_tuer, parkett=m_parkett,
                         fliese=m_fliese, treppe=m_treppe,
                         terrasse=m_terrasse, gelaende=m_gelaende))


def keil_x(name, ecken, tiefe_x, x0, sammlung, material_=None):
    """Wie keil(), nur in der anderen Achse: Polygon in der YZ-Ebene, in X
    extrudiert. Fuer Giebel und Dachflaechen eines Satteldachs, dessen First
    laengs laeuft."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    unten = [bm.verts.new((x0, y, z)) for (y, z) in ecken]
    bm.faces.new(unten)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces[:])
    bmesh.ops.solidify(bm, geom=bm.faces[:], thickness=-tiefe_x)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    sammlung.objects.link(ob)
    if material_ is not None:
        ob.data.materials.append(material_)
    return ob


def dach_bauen(t):
    """Dachstuhl (05) und Dachhaut (06).

    Der First laeuft in X, die Flaechen fallen nach vorn und hinten. Giebel
    stehen deshalb links und rechts -- dort, wo das Haus schmal ist.
    """
    g5, g6 = t['g5'], gruppe('06_Dach')
    m = t['mat']
    steig = math.tan(DACH_NEIGUNG)
    y_first = TIEFE / 2.0
    z_first = Z_TRAUFE + y_first * steig
    ue = DACH_UEBERSTAND

    # ---- Giebel: schliessen das Haus links und rechts bis zum First
    for (kennung, x0) in (('links', 0.0), ('rechts', BREITE - WAND_AUSSEN)):
        keil_x('giebel_' + kennung,
               [(0.0, Z_TRAUFE), (TIEFE, Z_TRAUFE), (y_first, z_first)],
               WAND_AUSSEN, x0, g5, m['mauer'])

    # ---- Pfetten: First oben, Fusspfetten auf den Aussenwaenden
    quader('pfette_first', (BREITE / 2.0, y_first, z_first - 0.10),
           (BREITE + 2 * ue, 0.16, 0.20), g5, m['holz'])
    for y in (WAND_AUSSEN / 2.0, TIEFE - WAND_AUSSEN / 2.0):
        quader('pfette_fuss_%0.2f' % y, (BREITE / 2.0, y, Z_TRAUFE + 0.06),
               (BREITE, 0.14, 0.12), g5, m['holz'])

    # ---- Sparren: alle 0,75 m, beide Seiten, mit Ueberstand
    n = int(BREITE / 0.75) + 1
    for i in range(n + 1):
        x = -ue + i * (BREITE + 2 * ue) / n
        for (kennung, y_traufe) in (('v', -ue), ('h', TIEFE + ue)):
            richtung = 1.0 if y_traufe < y_first else -1.0
            z_traufe = Z_TRAUFE - ue * steig
            laenge = math.hypot(abs(y_first - y_traufe), z_first - z_traufe)
            mitte_y = (y_traufe + y_first) / 2.0
            mitte_z = (z_traufe + z_first) / 2.0
            me = bpy.data.meshes.new('sparren_%s_%02d' % (kennung, i))
            bm = bmesh.new()
            bmesh.ops.create_cube(bm, size=1.0)
            bm.to_mesh(me)
            bm.free()
            ob = bpy.data.objects.new('sparren_%s_%02d' % (kennung, i), me)
            ob.location = (x, mitte_y, mitte_z - 0.10)
            ob.scale = (0.10, laenge, 0.18)
            ob.rotation_euler = (richtung * DACH_NEIGUNG, 0.0, 0.0)
            g5.objects.link(ob)
            ob.data.materials.append(m['holz'])

    # ---- Dachhaut: zwei Platten, 0,28 m dick senkrecht gemessen
    d = 0.28
    z_traufe = Z_TRAUFE - ue * steig
    vorn = [(-ue, z_traufe), (y_first, z_first),
            (y_first, z_first + d), (-ue, z_traufe + d)]
    hinten = [(TIEFE + ue, z_traufe), (y_first, z_first),
              (y_first, z_first + d), (TIEFE + ue, z_traufe + d)]
    keil_x('dach_vorn', vorn, BREITE + 2 * ue, -ue, g6, m['ziegel'])
    keil_x('dach_hinten', hinten, BREITE + 2 * ue, -ue, g6, m['ziegel'])
    t['g6_dach'] = g6


def ausbau_bauen(t):
    """Fenster/Tueren stehen schon in 06_Fenster_Tueren. Hier kommt der
    Innenausbau: Boeden, Treppe, Innentuerblaetter."""
    g7 = t['g7']
    m = t['mat']

    def boden(name, raum, hoehe, mat):
        _, x0, y0, x1, y1 = raum
        quader(name, ((x0 + x1) / 2.0, (y0 + y1) / 2.0, hoehe + 0.01),
               (x1 - x0, y1 - y0, 0.02), g7, mat)

    nass_eg = ('wc', 'hwr', 'kueche')
    for r in RAEUME_EG:
        boden('boden_eg_' + r[0], r, Z_EG,
              m['fliese'] if r[0] in nass_eg else m['parkett'])
    for r in RAEUME_OG:
        boden('boden_og_' + r[0], r, Z_OG,
              m['fliese'] if r[0] == 'bad' else m['parkett'])

    # ---- Treppe: gerade, an der linken Wand der Diele
    stufen = 15
    steigung = (HOEHE + DECKE) / stufen
    auftritt = 0.257
    for i in range(stufen):
        z = Z_EG + (i + 1) * steigung
        y = 0.35 + i * auftritt
        quader('treppe_stufe_%02d' % i, (0.85, y + auftritt / 2.0, z - 0.02),
               (1.00, auftritt, 0.04), g7, m['treppe'])
        quader('treppe_setz_%02d' % i, (0.85, y, z - steigung / 2.0),
               (1.00, 0.03, steigung), g7, m['treppe'])
    quader('treppe_wange', (1.37, 0.35 + stufen * auftritt / 2.0,
                            Z_EG + (HOEHE + DECKE) / 2.0),
           (0.05, math.hypot(stufen * auftritt, HOEHE + DECKE), 0.30),
           g7, m['treppe']).rotation_euler = (
        math.atan2(HOEHE + DECKE, stufen * auftritt), 0.0, 0.0)

    # ---- Innentuerblaetter, leicht offen stehend
    tueren = [
        ('tuer_wc',       (0.70 + TUER_B / 2.0, 4.06, Z_EG), True),
        ('tuer_kueche',   (2.60 + TUER_B / 2.0, 4.06, Z_EG), True),
        ('tuer_hwr',      (1.96, 6.66 + TUER_B / 2.0, Z_EG), False),
        ('tuer_kind1',    (1.60 + TUER_B / 2.0, 3.97, Z_OG), True),
        ('tuer_kind2',    (5.00 + TUER_B / 2.0, 3.97, Z_OG), True),
        ('tuer_buero',    (8.50 + TUER_B / 2.0, 3.97, Z_OG), True),
        ('tuer_bad',      (1.60 + TUER_B / 2.0, 5.63, Z_OG), True),
        ('tuer_schlafen', (5.20 + TUER_B / 2.0, 5.63, Z_OG), True),
        ('tuer_ankleide', (8.80 + TUER_B / 2.0, 5.63, Z_OG), True),
    ]
    for (name, (x, y, z), waagerecht) in tueren:
        mass = ((TUER_B - 0.02, 0.05, TUER_H - 0.02) if waagerecht
                else (0.05, TUER_B - 0.02, TUER_H - 0.02))
        quader(name, (x, y, z + TUER_H / 2.0), mass, g7, m['tuer'])


def aussen_bauen(t):
    """Gelaende, Terrasse, Vordach -- alles, was das Haus in eine Umgebung
    stellt. Ohne das steht ein Modell in der Luft."""
    g8 = t['g8']
    m = t['mat']
    quader('gelaende', (BREITE / 2.0, TIEFE / 2.0, -0.36),
           (60.0, 60.0, 0.12), g8, m['gelaende'])
    quader('terrasse', (7.75, TIEFE + 2.10, -0.03),
           (6.50, 4.20, 0.10), g8, m['terrasse'])
    quader('weg_haustuer', (1.65, -1.60, -0.03),
           (2.40, 3.20, 0.10), g8, m['terrasse'])
    # Vordach ueber dem Eingang
    quader('vordach', (1.65, -0.55, Z_EG + 2.62),
           (2.60, 1.50, 0.12), g8, m['rahmen'])
    for x in (0.55, 2.75):
        quader('vordach_stuetze_%0.2f' % x, (x, -1.15, Z_EG + 1.31),
               (0.10, 0.10, 2.62), g8, m['rahmen'])


# ------------------------------------------------- Abschnitte ordnen

UMBENENNEN = {
    '05_Dach': '05_Dachstuhl',
    '06_Fenster_Tueren': '07_Fenster_Tueren',
    '07_Ausbau': '08_Ausbau',
    '08_Aussen': '09_Aussen',
}

ABSCHNITTE = [
    ('01_Fundament',      'Bodenplatte'),
    ('02_Waende_EG',      'Waende Erdgeschoss'),
    ('03_Decke',          'Geschossdecke'),
    ('04_Waende_OG',      'Waende Obergeschoss'),
    ('05_Dachstuhl',      'Dachstuhl'),
    ('06_Dach',           'Dachhaut'),
    ('07_Fenster_Tueren', 'Fenster und Tueren'),
    ('08_Ausbau',         'Innenausbau'),
    ('09_Aussen',         'Aussenanlage'),
]


def abschnitte_ordnen():
    """Collections auf die endgueltigen Namen bringen und jedes Objekt mit
    dem Abschnittspraefix versehen.

    Der Praefix im OBJEKTNAMEN ist der Punkt: glTF nimmt Collections nicht
    mit, Knotennamen schon. Die Webseite gruppiert spaeter nach 'p01_',
    'p02_' und blendet Abschnitt fuer Abschnitt ein. Ohne den Praefix
    muesste eine Namensliste mitgeliefert werden, die bei jeder Aenderung
    am Haus veraltet."""
    for alt, neu in UMBENENNEN.items():
        if alt in bpy.data.collections and neu not in bpy.data.collections:
            bpy.data.collections[alt].name = neu

    zahl = {}
    for i, (name, _) in enumerate(ABSCHNITTE, start=1):
        c = bpy.data.collections.get(name)
        if c is None:
            print('[haus] Abschnitt fehlt: %s' % name)
            continue
        for ob in c.objects:
            if not ob.name.startswith('p%02d_' % i):
                ob.name = 'p%02d_%s' % (i, ob.name)
        zahl[name] = len(c.objects)
    return zahl


# ------------------------------------------------------------ Kennwerte

def grundriss_daten():
    """Alles, was der Browser ueber den Plan wissen muss -- und zwar aus
    derselben Quelle, aus der die Waende gebaut wurden."""
    def flaeche(r):
        return round((r[3] - r[1]) * (r[4] - r[2]), 2)

    def raumliste(rs, z):
        return [{'schluessel': r[0], 'x0': r[1], 'y0': r[2],
                 'x1': r[3], 'y1': r[4], 'flaeche': flaeche(r), 'z': z}
                for r in rs]

    return {
        'einheit': 'Meter',
        'aussen': {'breite': BREITE, 'tiefe': TIEFE},
        'hoehen': {'eg': Z_EG, 'decke': Z_DECKE, 'og': Z_OG,
                   'traufe': Z_TRAUFE,
                   'first': round(Z_TRAUFE + (TIEFE / 2.0) * math.tan(DACH_NEIGUNG), 3),
                   'raum': HOEHE, 'augen': 1.65},
        'geschosse': [
            {'schluessel': 'eg', 'z': Z_EG, 'raeume': raumliste(RAEUME_EG, Z_EG),
             'flaeche': round(sum(flaeche(r) for r in RAEUME_EG), 1)},
            {'schluessel': 'og', 'z': Z_OG, 'raeume': raumliste(RAEUME_OG, Z_OG),
             'flaeche': round(sum(flaeche(r) for r in RAEUME_OG), 1)},
        ],
        'abschnitte': [{'nummer': i, 'praefix': 'p%02d_' % i,
                        'sammlung': name, 'titel': titel}
                       for i, (name, titel) in enumerate(ABSCHNITTE, start=1)],
        # Wegpunkte fuer die Begehung: (x, y, blickrichtung in Grad)
        'rundgang': [
            {'raum': 'aussen',  'x': 1.65, 'y': -3.20, 'blick': 0,   'z': Z_EG},
            {'raum': 'diele',   'x': 1.65, 'y':  1.30, 'blick': 0,   'z': Z_EG},
            {'raum': 'wohnen',  'x': 7.60, 'y':  2.60, 'blick': 40,  'z': Z_EG},
            {'raum': 'wohnen',  'x': 8.00, 'y':  6.80, 'blick': 200, 'z': Z_EG},
            {'raum': 'kueche',  'x': 3.40, 'y':  6.40, 'blick': 270, 'z': Z_EG},
            {'raum': 'flur',    'x': 1.20, 'y':  4.80, 'blick': 90,  'z': Z_OG},
            {'raum': 'schlafen', 'x': 5.60, 'y': 7.20, 'blick': 180, 'z': Z_OG},
            {'raum': 'buero',   'x': 8.90, 'y':  2.10, 'blick': 340, 'z': Z_OG},
        ],
    }


def haupt(speichern=True):
    t = bauen()
    dach_bauen(t)
    ausbau_bauen(t)
    aussen_bauen(t)
    zahl = abschnitte_ordnen()

    gesamt = len(bpy.data.objects)
    dreiecke = 0
    for ob in bpy.data.objects:
        if ob.type == 'MESH':
            ob.data.calc_loop_triangles()
            dreiecke += len(ob.data.loop_triangles)

    daten = grundriss_daten()
    daten['objekte'] = gesamt
    daten['dreiecke'] = dreiecke
    daten['je_abschnitt'] = zahl

    ordner = os.path.join(os.path.dirname(os.path.dirname(
        os.path.abspath(__file__))), 'web-export')
    if not os.path.isdir(ordner):
        ordner = 'C:/Users/manue/Desktop/Vecom Design/3d-produktion/web-export'
    os.makedirs(ordner, exist_ok=True)
    with open(os.path.join(ordner, 'haus-grundriss.json'), 'w',
              encoding='utf-8') as f:
        json.dump(daten, f, ensure_ascii=False, indent=2, sort_keys=True)

    print('[haus] %d Objekte, %d Dreiecke' % (gesamt, dreiecke))
    for name, n in sorted(zahl.items()):
        print('[haus]   %-20s %4d' % (name, n))

    if speichern:
        ziel = 'C:/Users/manue/Desktop/Vecom Design/3d-produktion/vecom-haus.blend'
        bpy.ops.wm.save_as_mainfile(filepath=ziel)
        print('[haus] gespeichert: %s' % ziel)
    return daten


if __name__ == '__main__':
    haupt()
