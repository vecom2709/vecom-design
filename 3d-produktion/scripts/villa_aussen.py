# -*- coding: utf-8 -*-
"""
villa_aussen.py -- Was aus dem Architekturmodell einen bewohnten Ort macht.

DER BEFUND AUS DEM REALITY CHECK
Die Aussenaufnahmen waren nach dem Lichtumbau technisch richtig und
trotzdem unbewohnt: ein Haus, ein Pool, eine Wiese, sonst nichts. Zwei
Punkte der Pruefliste waren offen.

MASSSTABSTRAEGER
Ein Betrachter liest Groesse an Dingen ab, deren Mass er kennt -- eine
Liege, ein Stuhl, ein Sonnenschirm, eine Fussmatte. Ohne sie kann derselbe
Baukoerper 8 m oder 30 m lang sein, und genau diese Unsicherheit liest das
Auge als "Modell". Eine Liege ist 1,98 m lang. Steht sie am Pool, ist
danach jedes andere Mass im Bild festgelegt.

UEBERGAENGE
Realismus entsteht dort, wo Objekt und Welt sich beruehren. Ein Baum, der
ohne Mulchring und ohne Laub aus einer geschnittenen Wiese ragt, steht
nicht im Boden, sondern auf ihm. Am Fuss der Mauer maeht niemand, also
steht das Gras dort hoeher. Auf dem Kiesplatz liegen viele kleine und
wenige grosse Steine, nie gleich grosse.

ALLES HIER IST ZURUECKHALTEND. Zwei Liegen, ein Tisch mit vier Stuehlen,
ein Schirm, drei Kuebel. Ein vollgestellter Garten waere der naechste
Fehler -- eine Villa dieser Preisklasse wird leer fotografiert.
"""

import bmesh
import bpy
import math
import random

from mathutils import Matrix, Vector, noise

RASEN_OBEN = -0.38
TERRASSE_OBEN = 0.0


# ------------------------------------------------------------- Bausteine

def _samml(name='08_Aussenanlage'):
    s = bpy.data.collections.get(name)
    if s is not None:
        return s
    for c in bpy.data.collections:
        if c.name.endswith('Aussenanlage'):
            return c
    return bpy.context.scene.collection


def quader(name, x0, y0, z0, dx, dy, dz, samml, mat=None, drehung=0.0):
    """Wie in villa.py: Die Groesse steckt im NETZ, nicht in der
    Objektskalierung. Nur so wirkt eine 6-mm-Fase ueberall gleich, und nur
    so ueberlebt der Export die Skalierung."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= dx
        v.co.y *= dy
        v.co.z = v.co.z * dz + dz / 2.0
    bm.to_mesh(me)
    bm.free()
    # DIE DREHUNG GEHT INS NETZ, NICHT ANS OBJEKT.
    # Am 20.09.2026 lag die neue Sitzgruppe in Unreal flach auf der
    # Terrasse, obwohl sie in Blender stand. Ursache ist der FBX-Weg:
    # Exporter dreht Z-oben auf Y-oben, Importer dreht zurueck, und
    # `combine_meshes` fasst mehrere Knoten zu einem Netz zusammen --
    # eine eigene Objektdrehung ueberlebt diese Verkettung nicht
    # zuverlaessig. Bei symmetrischen Kaesten faellt das nie auf, und
    # genau deshalb stand der Fehler hier lange unbemerkt.
    # Gebacken ist er weg: Das Netz traegt die Drehung, das Objekt
    # steht auf Identitaet.
    if abs(drehung) > 1e-9:
        me.transform(Matrix.Rotation(drehung, 4, 'Z'))
    ob = bpy.data.objects.new(name, me)
    ob.location = (x0 + dx / 2.0, y0 + dy / 2.0, z0)
    samml.objects.link(ob)
    if mat is not None:
        me.materials.append(mat)
    return ob


def zylinder(name, x, y, z, r, h, samml, mat=None, seiten=16, achse='Z'):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=seiten,
                          radius1=r, radius2=r, depth=h)
    for v in bm.verts:
        v.co.z += h / 2.0
    bm.to_mesh(me)
    bm.free()
    # Auch hier ins Netz statt ans Objekt -- siehe quader().
    if achse == 'Y':
        me.transform(Matrix.Rotation(math.radians(90.0), 4, 'X'))
    elif achse == 'X':
        me.transform(Matrix.Rotation(math.radians(90.0), 4, 'Y'))
    ob = bpy.data.objects.new(name, me)
    ob.location = (x, y, z)
    samml.objects.link(ob)
    if mat is not None:
        me.materials.append(mat)
    return ob


def _mat(name, farbe, rauheit, metall=0.0):
    m = bpy.data.materials.get(name)
    if m is not None:
        return m
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = next((n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None)
    if b is not None:
        b.inputs['Base Color'].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
        b.inputs['Roughness'].default_value = rauheit
        b.inputs['Metallic'].default_value = metall
    return m


def materialien():
    return {
        # Leinen ist heller als man denkt, aber nie weiss -- und es hat eine
        # hohe Rauheit, sonst sieht es aus wie Kunststoff.
        'leinen': _mat('Leinen', (0.640, 0.618, 0.575), 0.88),
        'teak': _mat('Teak', (0.232, 0.148, 0.078), 0.52),
        'stahl': bpy.data.materials.get('Alu_Anthrazit')
                 or _mat('Alu_Anthrazit', (0.048, 0.050, 0.054), 0.32, 0.85),
        'stoff': bpy.data.materials.get('Polsterstoff')
                 or _mat('Polsterstoff', (0.148, 0.152, 0.148), 0.88),
        'terra': _mat('Terrakotta', (0.198, 0.092, 0.052), 0.78),
        'mulch': _mat('Mulch', (0.062, 0.044, 0.030), 0.92),
        'stein': bpy.data.materials.get('Kies')
                 or _mat('Kies', (0.181, 0.177, 0.169), 0.88),
        'blatt': bpy.data.materials.get('Blattgruen')
                 or _mat('Blattgruen', (0.048, 0.118, 0.038), 0.72),
        # Die Kuebelbaeume sind Oliven -- dieselben Materialien wie die
        # grossen Oliven in villa_pflanzen, nicht ein eigenes Gruen.
        'rinde': bpy.data.materials.get('Olivenrinde')
                 or _mat('Olivenrinde', (0.235, 0.215, 0.188), 0.88),
        'olive': bpy.data.materials.get('Olivenlaub')
                 or _mat('Olivenlaub', (0.094, 0.112, 0.062), 0.76),
    }


# --------------------------------------------------------------- Moebel

def liege(name, x, y, z, dreh, samml, m, rnd):
    """Sonnenliege, 1,98 x 0,72 m, Sitzhoehe 0,36 m.

    Die Rueckenlehne steht auf 28 Grad und bei der zweiten Liege auf 34 --
    zwei identisch eingestellte Liegen sind ein Katalogfoto, kein Garten.
    """
    teile = []
    L, B = 1.98, 0.72
    rahmenhoehe = 0.30
    for sx in (-1, 1):
        teile.append(quader(name + '_kufe%d' % sx, x - L / 2.0,
                            y + sx * (B / 2.0 - 0.05) - 0.025, z,
                            L, 0.05, rahmenhoehe, samml, m['teak'],
                            drehung=dreh))
    liegeflaeche = quader(name + '_flaeche', x - L / 2.0, y - B / 2.0,
                          z + rahmenhoehe, L, B, 0.05, samml, m['teak'],
                          drehung=dreh)
    teile.append(liegeflaeche)
    polster = quader(name + '_polster', x - L / 2.0 + 0.04, y - B / 2.0 + 0.04,
                     z + rahmenhoehe + 0.05, L - 0.08, B - 0.08, 0.09,
                     samml, m['leinen'], drehung=dreh)
    teile.append(polster)

    winkel = math.radians(rnd.uniform(26.0, 35.0))
    lehne = quader(name + '_lehne', x + L / 2.0 - 0.70, y - B / 2.0 + 0.04,
                   z + rahmenhoehe + 0.05, 0.66, B - 0.08, 0.09,
                   samml, m['leinen'], drehung=dreh)
    # Die Neigung der Rueckenlehne ins NETZ, nicht ans Objekt -- sonst
    # liegt die Liege in Unreal flach da (siehe quader()). Gedreht wird
    # um die hintere Unterkante, damit die Lehne sich aufstellt und
    # nicht in die Matratze faehrt.
    achse = Matrix.Translation((-0.33, 0.0, -0.045)) \
        @ Matrix.Rotation(-winkel, 4, 'Y') \
        @ Matrix.Translation((0.33, 0.0, 0.045))
    lehne.data.transform(achse)
    teile.append(lehne)

    # Ein Handtuch, nachlaessig ueber das Fussende. Es kostet zwei Quader
    # und ist das einzige Stueck im Bild, das nicht ausgerichtet ist.
    if rnd.random() < 0.8:
        t = quader(name + '_tuch', x - L / 2.0 + 0.18, y - B / 2.0 + 0.10,
                   z + rahmenhoehe + 0.14, 0.62, B - 0.18, 0.02,
                   samml, m['leinen'], drehung=dreh + rnd.uniform(-0.10, 0.10))
        teile.append(t)
    return teile


def beistelltisch(name, x, y, z, samml, m):
    teile = [zylinder(name + '_fuss', x, y, z, 0.035, 0.40, samml, m['stahl'], 12)]
    teile.append(zylinder(name + '_platte', x, y, z + 0.40, 0.22, 0.03,
                          samml, m['teak'], 20))
    teile.append(zylinder(name + '_teller', x, y, z + 0.435, 0.085, 0.012,
                          samml, bpy.data.materials.get('Keramik') or m['leinen'], 18))
    return teile


def esstisch_aussen(name, x, y, z, dreh, samml, m, rnd):
    """2,20 x 0,95 m, Hoehe 0,755 m, vier Stuehle.

    SEIT DEM 20.09.2026 BAUT DAS villa_terrassenmoebel.py.
    Die alte Fassung steht unten noch als _esstisch_alt und wird nicht
    mehr gerufen. Sie war eine Platte auf vier Pfosten und ein Stuhl
    aus vier Staeben -- im Terrassenblick mittig im Bild und dort als
    Drahtmodell zu erkennen. Drei Gruende, alle ohne Materialbezug:
    keine Fugen in der Platte, keine Zarge unter ihr, keine einzige
    Fase (villa.kanten_brechen laeuft ueber die Aussenmoebel nicht, die
    entstehen erst danach). Dazu ein echter Fehler: quader() dreht jede
    Kiste um ihre EIGENE Mitte, der Stuhl wurde also nie gedreht,
    sondern in sich verzogen.
    """
    import os as _os
    import sys as _sys
    hier = _os.path.dirname(_os.path.abspath(__file__))
    if hier not in _sys.path:
        _sys.path.insert(0, hier)
    pfad = _os.path.join(hier, 'villa_terrassenmoebel.py')
    ns = {'__name__': 'villa_terrassenmoebel_modul', '__file__': pfad}
    exec(compile(open(pfad, encoding='utf-8').read(), pfad, 'exec'), ns)
    return ns['gruppe'](name, x, y, z, dreh, samml,
                        m['teak'], m['stahl'], rnd)


def _esstisch_alt(name, x, y, z, dreh, samml, m, rnd):
    """Nicht mehr in Gebrauch -- siehe esstisch_aussen."""
    teile = []
    L, B, H = 2.20, 0.95, 0.75
    teile.append(quader(name + '_platte', x - L / 2.0, y - B / 2.0, z + H - 0.045,
                        L, B, 0.045, samml, m['teak'], drehung=dreh))
    for sx in (-1, 1):
        for sy in (-1, 1):
            teile.append(quader(
                name + '_bein%d%d' % (sx, sy),
                x + sx * (L / 2.0 - 0.12) - 0.03,
                y + sy * (B / 2.0 - 0.10) - 0.03,
                z, 0.06, 0.06, H - 0.045, samml, m['stahl'], drehung=dreh))

    plaetze = ((-0.62, -0.78, 0.0), (0.62, -0.78, 0.0),
               (-0.62, 0.78, math.pi), (0.68, 0.86, math.pi * 0.86))
    for i, (px, py, pd) in enumerate(plaetze):
        zurueck = rnd.uniform(0.0, 0.16) if i == 3 else rnd.uniform(0.0, 0.05)
        teile += stuhl(name + '_stuhl%d' % i, x + px, y + py + (zurueck if py > 0 else -zurueck),
                       z, dreh + pd + rnd.uniform(-0.09, 0.09), samml, m)
    return teile


def stuhl(name, x, y, z, dreh, samml, m):
    teile = []
    S = 0.46
    for sx in (-1, 1):
        for sy in (-1, 1):
            teile.append(quader(name + '_b%d%d' % (sx, sy),
                                x + sx * 0.19 - 0.015, y + sy * 0.19 - 0.015,
                                z, 0.03, 0.03, S, samml, m['stahl'], drehung=dreh))
    teile.append(quader(name + '_sitz', x - 0.22, y - 0.22, z + S,
                        0.44, 0.44, 0.035, samml, m['teak'], drehung=dreh))
    lehne = quader(name + '_lehne', x - 0.21, y + 0.18, z + S + 0.035,
                   0.42, 0.035, 0.42, samml, m['teak'], drehung=dreh)
    lehne.rotation_euler = (math.radians(-9.0), 0.0, dreh)
    teile.append(lehne)
    return teile


def sonnenschirm(name, x, y, z, samml, m, offen=True):
    """Mast 2,45 m, Schirm 3,0 m Durchmesser. Ein geschlossener Schirm
    neben einem offenen ist glaubwuerdiger als zwei offene."""
    teile = [zylinder(name + '_mast', x, y, z, 0.026, 2.45, samml, m['stahl'], 12)]
    teile.append(zylinder(name + '_fuss', x, y, z, 0.26, 0.06, samml,
                          bpy.data.materials.get('Naturstein') or m['stein'], 22))
    me = bpy.data.meshes.new(name + '_dach')
    bm = bmesh.new()
    if offen:
        bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=8,
                              radius1=1.50, radius2=0.05, depth=0.34)
        hoehe = 2.30
    else:
        bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=8,
                              radius1=0.13, radius2=0.05, depth=1.90
                              )
        hoehe = 1.40
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name + '_dach', me)
    ob.location = (x, y, z + hoehe)
    samml.objects.link(ob)
    me.materials.append(m['leinen'])
    teile.append(ob)
    return teile


_PFL = [None]


def _pflanzen():
    """villa_pflanzen.py einmal laden. Dort stehen der geriffelte Stamm
    (_zug) und der verdraengte Blattballen (_ballen), gebaut und begruendet
    fuer die grossen Oliven. Der Kuebelbaum ist dieselbe Art, nur jung und
    geschnitten -- zwei Baukaesten fuer einen Baum waeren zwei Arten,
    denselben Fehler zu machen."""
    if _PFL[0] is None:
        import os
        try:
            hier = os.path.dirname(os.path.abspath(__file__))
        except NameError:
            hier = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\scripts'
        p = os.path.join(hier, 'villa_pflanzen.py')
        ns = {'__name__': 'villa_pflanzen_modul', '__file__': p}
        exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
        _PFL[0] = ns
    return _PFL[0]


def _kanten_hart(me, grad=40.0):
    """Glatt schattieren, aber Kanten ueber `grad` hart lassen. Sonst
    mittelt die Normale ueber den Knick am Topfrand, und der Wulst
    bekommt einen schmierigen dunklen Streifen."""
    for p in me.polygons:
        p.use_smooth = True
    try:
        me.set_sharp_from_angle(angle=math.radians(grad))
    except AttributeError:
        me.use_auto_smooth = True
        me.auto_smooth_angle = math.radians(grad)


def _topf(name, x, y, z, r, h, samml, mat, seiten=40):
    """Ein Terrakottatopf, wie er von der Scheibe kommt: konisch, mit
    gerolltem Rand und einer Innenwand.

    Der alte Topf war ein Zylinder mit scharfer Kante -- eine Dose. Was
    einen Topf zum Topf macht, ist der Wulst am Rand: Er wirft einen
    schmalen Schatten auf die Wand darunter und faengt oben Licht. Genau
    diese zwei Linien liest das Auge aus 30 m noch. Masse wie ein
    handelsueblicher Kuebel: Fuss 76 % des Randdurchmessers, Wulst
    2,2 cm vorstehend und 5,8 cm hoch, Wand 1,7 cm."""
    rb = r * 0.76
    lip, rh, wand = 0.022, 0.058, 0.017
    # (Radius, Hoehe) -- aussen von unten nach oben, ueber den Rand,
    # innen wieder hinunter bis unter die Erde.
    prof = [(0.0, 0.0), (rb - 0.010, 0.0), (rb, 0.008),
            (r, h - rh),
            (r + lip * 0.60, h - rh + 0.003), (r + lip, h - rh + 0.015),
            (r + lip, h - 0.010), (r + lip - 0.009, h),
            (r - wand + 0.005, h), (r - wand, h - 0.007),
            (r - wand - 0.004, h - 0.080)]
    bm = bmesh.new()
    ringe = []
    for rad, hz in prof:
        if rad <= 1e-6:
            ringe.append([bm.verts.new((0.0, 0.0, hz))])
            continue
        ringe.append([bm.verts.new((math.cos(2.0 * math.pi * j / seiten) * rad,
                                    math.sin(2.0 * math.pi * j / seiten) * rad,
                                    hz)) for j in range(seiten)])
    # Umlaufsinn: Ringe gegen den Uhrzeigersinn, Profil wie oben -- so
    # zeigt jede Normale vom Werkstoff weg (unten nach unten, aussen nach
    # aussen, oben nach oben, innen zur Topfmitte).
    for i in range(len(ringe) - 1):
        a, b = ringe[i], ringe[i + 1]
        for j in range(seiten):
            k = (j + 1) % seiten
            if len(a) == 1:
                bm.faces.new((a[0], b[k], b[j]))
            else:
                bm.faces.new((a[j], a[k], b[k], b[j]))
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    _kanten_hart(me)
    ob = bpy.data.objects.new(name, me)
    ob.location = (x, y, z)
    samml.objects.link(ob)
    me.materials.append(mat)
    return ob


def _erde(name, x, y, z, r, samml, mat, rnd, seiten=32):
    """Die Erde im Topf: leicht gewoelbt und uneben. Eine ebene Scheibe
    auf genau gleicher Hoehe ist Kunststoff, keine Erde."""
    bm = bmesh.new()
    ringe = [[bm.verts.new((0.0, 0.0, 0.012))]]
    for f in (0.35, 0.70, 1.0):
        ringe.append([bm.verts.new((
            math.cos(2.0 * math.pi * j / seiten) * r * f,
            math.sin(2.0 * math.pi * j / seiten) * r * f,
            0.012 * (1.0 - f * f) + rnd.uniform(-0.004, 0.004)))
            for j in range(seiten)])
    for i in range(len(ringe) - 1):
        a, b = ringe[i], ringe[i + 1]
        for j in range(seiten):
            k = (j + 1) % seiten
            if len(a) == 1:
                bm.faces.new((a[0], b[j], b[k]))
            else:
                bm.faces.new((a[j], b[j], b[k], a[k]))
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    ob.location = (x, y, z)
    samml.objects.link(ob)
    me.materials.append(mat)
    return ob


def _olivenblaetter(name, C, R, samml, mat, rnd, zweige=420, samen=0):
    """Olivenblaetter an kurzen Zweiglein, verteilt ueber die Kronenschale.

    Die Ballen allein sind Knoedel -- das steht mit zwei Cycles-Proben
    belegt ueber PFLANZEN_AN in villa_pflanzen. Was eine Krone von einer
    Kugel unterscheidet, ist der Saum: Blaetter, die ueber den Umriss
    hinausstehen, und Luecken, durch die Himmel und Wand scheinen.

    ERSTE PROBE (21.09.2026): Blaetter einzeln und strahlenfoermig auf
    jedem Ballen -- das ergab Seeigel. Die Ballen standen als dunkle
    Kugeln einzeln da, jede mit einem Nadelkranz, und die Krone sah nach
    Kiefer aus. Ein Olivenblatt steht nicht radial, sondern paarweise an
    einem Zweiglein: kreuzgegenstaendig, 30 bis 55 Grad von der
    Zweigrichtung weg, Oberseite zum Licht. Erst diese Buendel geben die
    weiche, geschuppte Oberflaeche, an der man eine Olive erkennt.

    Ein Blatt ist 4,5 bis 7 cm lang und ein Fuenftel so breit,
    lanzettlich, die Spitze leicht nach unten. Sechs Punkte, vier
    Dreiecke -- in 30 m Abstand sind das vier bis fuenf Bildpunkte.
    Unten wird ausgeduennt: Dort ist Schatten, dort waechst weniger."""
    bm = bmesh.new()
    oben = Vector((0.0, 0.0, 1.0))

    def kugel():
        while True:
            d = Vector((rnd.uniform(-1, 1), rnd.uniform(-1, 1),
                        rnd.uniform(-1, 1)))
            if 0.05 < d.length <= 1.0:
                return d.normalized()

    def blatt(p, achse, licht):
        quer = achse.cross(licht)
        if quer.length < 1e-4:
            return False
        quer.normalize()
        nrm = achse.cross(quer)
        L = rnd.uniform(0.045, 0.072)
        B = L * rnd.uniform(0.16, 0.22)
        vs = [bm.verts.new(p),
              bm.verts.new(p + achse * (0.34 * L) + quer * (0.50 * B)),
              bm.verts.new(p + achse * (0.34 * L) - quer * (0.50 * B)),
              bm.verts.new(p + achse * (0.70 * L) + quer * (0.38 * B)
                           - nrm * (0.04 * L)),
              bm.verts.new(p + achse * (0.70 * L) - quer * (0.38 * B)
                           - nrm * (0.04 * L)),
              bm.verts.new(p + achse * L - nrm * (0.10 * L))]
        bm.faces.new((vs[0], vs[2], vs[1]))
        bm.faces.new((vs[1], vs[2], vs[4], vs[3]))
        bm.faces.new((vs[3], vs[4], vs[5]))
        return True

    zahl = 0
    versatz = Vector((samen * 0.37, samen * 0.11, 0.0))
    for _ in range(zweige):
        d = kugel()
        if d.z < -0.45 and rnd.random() < 0.6:
            continue
        # Die Schale ist nicht rund geschnitten: ein grobes Rauschen ueber
        # die Richtung macht Buckel und Mulden von 16 % des Radius.
        form = 1.0 + 0.16 * noise.noise(d * 1.4 + versatz)
        # Von 0,3 R bis zum Rand, nach aussen dichter (Wurzel der
        # Gleichverteilung): Ein geschnittener Baum ist aussen dicht und
        # innen licht, aber nicht hohl.
        rr = R * form * (0.30 + 0.68 * math.sqrt(rnd.random()))
        basis = C + Vector((d.x * rr, d.y * rr, d.z * rr * 0.86))
        richt = (d * 0.75 + kugel() * 0.40 + oben * 0.30).normalized()
        lang = rnd.uniform(0.09, 0.18)
        h1 = richt.cross(kugel())
        if h1.length < 1e-4:
            continue
        h1.normalize()
        h2 = richt.cross(h1)
        paare = rnd.randint(4, 7)
        for i in range(paare):
            p = basis + richt * (lang * (i + 1.0) / (paare + 0.6))
            q = h1 if i % 2 == 0 else h2        # kreuzgegenstaendig
            for s in (-1.0, 1.0):
                a = math.radians(rnd.uniform(32.0, 55.0))
                achse = (richt * math.cos(a) + q * (s * math.sin(a))
                         - oben * 0.12).normalized()
                if blatt(p, achse, oben * 0.7 + d * 0.5 + kugel() * 0.45):
                    zahl += 1
        spitze = basis + richt * lang            # Endpaar, fast gerade
        for s in (-1.0, 1.0):
            achse = (richt * 0.93 + h1 * (s * 0.35)).normalized()
            if blatt(spitze, achse, oben * 0.7 + d * 0.5 + kugel() * 0.45):
                zahl += 1
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    samml.objects.link(ob)
    me.materials.append(mat)
    print('[aussen] %s: %d Blaetter' % (name, zahl))
    return ob


def kuebel(name, x, y, z, samml, m, rnd, blaetter=True):
    """Terrakottakuebel mit Olivenstaemmchen.

    WARUM NEU (21.09.2026)
    Die alte Pflanze war eine glatte gruene Kugel auf einem geraden
    Stab -- im Unreal-Bild vom 21.09. das Erste, woran man das
    Rechnerbild erkannte ("Lutscher"). Die Begruendung "steht nie naeher
    als 12 m" trug nicht: Im Blick 'garten' steht Kuebel 1 mitten vor der
    Wohnzimmerscheibe, hell gegen dunkles Glas.

    WAS EIN OLIVENSTAEMMCHEN IST
    So, wie es in jedem sizilianischen Kuebel steht: 0,95 bis 1,15 m
    freier, leicht gewundener Stamm mit Riffelung und verdicktem Fuss,
    oben fuenf bis sechs Starkaeste mit je zwei Zweigen, darauf eine
    geschnittene, rund einen Meter breite Krone. Sie ist dicht, aber
    nicht geschlossen -- und sie ist silbrig graugruen, nicht
    saftgruen: dieselben Materialien wie die grossen Oliven.

    blaetter=False fuer den Browser: Dort traegt die Krone nur die Ballen.
    """
    # Dieselben Zuege aus rnd wie die alte Fassung (4 + 3 je Ecke ihrer
    # Ikosphaere), damit Kiesel, Mulch und Randgras danach GENAU dort
    # liegen, wo sie lagen. Sonst aendert ein neuer Baum drei andere Netze
    # mit, und Vorlage und Unreal-Bild zeigen verschiedene Szenen. Die
    # Eckenzahl wird gezaehlt statt hingeschrieben: subdivisions=2 heisst
    # in Blender 42 Ecken, nicht 162 -- die Stufe 1 ist schon das
    # Ikosaeder. Mit der ersten Fassung (162) haetten die Kiesel woanders
    # gelegen.
    r = rnd.uniform(0.26, 0.34)
    h = rnd.uniform(0.46, 0.60)
    tmp = bmesh.new()
    n_alt = len(bmesh.ops.create_icosphere(tmp, subdivisions=2, radius=1.0)['verts'])
    tmp.free()
    for _ in range(2 + n_alt * 3):
        rnd.random()
    eigen = random.Random(int(r * 1e6) * 7919 + int(h * 1e6))
    P = _pflanzen()
    teile = [_topf(name + '_topf', x, y, z, r, h, samml, m['terra'])]
    boden = z + h - 0.045
    # Rand der Erde an die Innenwand (r - Wand - 2 mm auf dieser Hoehe)
    teile.append(_erde(name + '_erde', x, y, boden, r - 0.020, samml,
                       m['mulch'], eigen))
    samen = eigen.randint(1, 999)
    phase = eigen.uniform(0.0, 6.28)

    # --- Stamm: an Fuss und Kopf auf der Achse, dazwischen gewunden.
    # Erste Probe: 4,5 cm Auslenkung auf gut einen Meter und schwache
    # Riffelung -- das sah gedrechselt aus. Ein Olivenstamm windet sich
    # sichtbar, ist tief gerieft und hat unter der Krone einen Wulst, wo
    # die Aeste abgehen.
    bm = bmesh.new()
    frei = eigen.uniform(0.82, 0.98)
    r0 = eigen.uniform(0.040, 0.050)
    schief = Vector((eigen.uniform(-0.08, 0.08), eigen.uniform(-0.08, 0.08), 0.0))
    pts, rad = [], []
    for i in range(11):
        t = i / 10.0
        w = Vector((noise.noise(Vector((t * 1.6 + samen, 0.3, 0.0))),
                    noise.noise(Vector((0.7, t * 1.6 + samen, 0.0))),
                    0.0)) * 0.075
        pts.append(Vector((x, y, boden - 0.05 + (frei + 0.05) * t))
                   + schief * t + w * math.sin(math.pi * t))
        rad.append(r0 * (1.0 - 0.35 * t) + r0 * 0.32 * math.exp(-t * 12.0)
                   + r0 * 0.16 * math.exp(-(1.0 - t) * 14.0))
    P['_zug'](bm, pts, rad, seiten=14, riffel=0.16, phase=phase, drall=0.22)

    # --- Starkaeste mit je zwei Zweigen. Kuerzer als in der ersten Probe:
    # Dort standen sie wie die Speichen eines Schirms unter der Krone.
    enden = []
    n_ast = 5 + samen % 2
    for a in range(n_ast):
        wink = 2.0 * math.pi * a / n_ast + eigen.uniform(-0.4, 0.4)
        neig = eigen.uniform(0.45, 1.00)
        lang = eigen.uniform(0.24, 0.36)
        start = pts[-2].lerp(pts[-1], eigen.uniform(0.2, 1.0))
        d = Vector((math.sin(neig) * math.cos(wink),
                    math.sin(neig) * math.sin(wink), math.cos(neig)))
        ap, ar = [], []
        for i in range(5):
            t = i / 4.0
            p = start + d * (lang * t)
            p.z += lang * 0.18 * math.sin(math.pi * t * 0.5)
            p += Vector((noise.noise(Vector((t * 3.0 + a, samen, 0.0))),
                         noise.noise(Vector((samen, t * 3.0 + a, 0.0))),
                         0.0)) * (0.03 * t)
            ap.append(p)
            ar.append(rad[-1] * 0.62 * (1.0 - 0.72 * t))
        P['_zug'](bm, ap, ar, seiten=7, riffel=0.06, phase=phase + a)
        enden.append(ap[-1])
        for z2 in range(2):
            ab = ap[2 + z2]
            w2 = wink + (z2 - 0.5) * 1.3 + eigen.uniform(-0.3, 0.3)
            l2 = lang * eigen.uniform(0.40, 0.60)
            d2 = Vector((math.cos(w2) * 0.75, math.sin(w2) * 0.75, 0.66)).normalized()
            zp = [ab + d2 * (l2 * k / 2.0) for k in range(3)]
            zr = [ar[2 + z2] * f for f in (0.70, 0.45, 0.22)]
            P['_zug'](bm, zp, zr, seiten=5, riffel=0.04, phase=phase + z2)
            enden.append(zp[-1])
    teile.append(P['_objekt'](name + '_stamm', bm, samml, m['rinde'], glatt=False))

    # --- Krone: EINE geschnittene Masse, nicht einzelne Kugeln.
    # Ein geschnittenes Staemmchen ist eine zusammenhaengende, buckelige
    # Kugel, die tief ueber dem Kopf sitzt und die Aeste verschluckt.
    # Drei Proben (lauf_kuebel_probe.py, 21.09.2026):
    #   1. 24 Ballen an den Astenden, Blaetter radial darauf -- sechs
    #      dunkle Kugeln mit Nadelkranz nebeneinander, Seeigel.
    #   2. Kern 0,6 R plus 420 Zweiglein -- die Blaetter sahen nach Olive
    #      aus, aber der Kern stand als gruener Felsbrocken in der Krone.
    #      Eine glatte Flaeche zwischen Blaettern ist dieselbe Luege wie
    #      die alte Kugel.
    #   3. So wie jetzt: Der Kern ist nur noch ein kleiner Schattenkoerper
    #      tief innen (0,36 R); die Dichte kommt aus 1.100 Zweiglein, die
    #      die ganze Krone fuellen. Aus der Naehe und aus 30 m eine Olive.
    C = pts[-1] + Vector((0.0, 0.0, eigen.uniform(0.16, 0.22)))
    R = eigen.uniform(0.44, 0.52)
    bl = bmesh.new()
    kern = 0.36 if blaetter else 0.85
    P['_ballen'](bl, C, (R * kern, R * kern, R * kern * 0.84),
                 samen * 17, unruhe=0.30, unterteilung=3)
    if not blaetter:
        # Browserfassung ohne Blaetter: die Buckel aus Ballen an den Astenden
        for i, e in enumerate(enden):
            v = e - C
            if v.length > R * 0.80:
                v = v.normalized() * (R * 0.80)
            s = eigen.uniform(0.75, 1.15)
            P['_ballen'](bl, C + v, (0.12 * s, 0.115 * s, 0.095 * s),
                         samen * 31 + i, unruhe=0.40)
    teile.append(P['_objekt'](name + '_krone', bl, samml, m['olive'], glatt=True))
    if blaetter:
        teile.append(_olivenblaetter(name + '_blaetter', C, R, samml,
                                     m['olive'], eigen, zweige=1100,
                                     samen=samen))
    return teile


# -------------------------------------------------------- Uebergaenge

_DG = [None]


def _boden_z(gelaende, x, y, sonst=RASEN_OBEN):
    """Hoehe der Rasenoberkante an (x, y) -- per Strahl von oben aufs
    Gelaendenetz, weil das Gelaende aus villa_garten um einige Zentimeter
    wellt und keine Formel dafuer hier greifbar ist. Mit dem
    ausgewerteten Netz (Depsgraph), falls ein Modifikator die Wellen
    macht."""
    if gelaende is None:
        return sonst
    if _DG[0] is None:
        _DG[0] = bpy.context.evaluated_depsgraph_get()
    inv = gelaende.matrix_world.inverted()
    o = inv @ Vector((x, y, 50.0))
    d = (inv.to_3x3() @ Vector((0.0, 0.0, -1.0))).normalized()
    try:
        ok, p, n, idx = gelaende.ray_cast(o, d, depsgraph=_DG[0])
    except Exception:
        return sonst
    return (gelaende.matrix_world @ p).z if ok else sonst


def _baumscheibe(name, x, y, r, gelaende, samml, mat, samen, seiten=28):
    """Mulch am Stammfuss: eine flache, unregelmaessige Aufschuettung,
    die dem Gelaende folgt.

    WARUM NICHT MEHR DER ZYLINDER (21.09.2026)
    Die alte Scheibe war ein 3,5 cm hoher Zylinder auf fester Hoehe. Das
    Gelaende wellt aber: Unter der linken Zypresse lag der Rasen 4 bis
    10 cm TIEFER, die Scheibe schwebte also mit eigenem Schatten darunter.
    Und aus 52 m unter 5 Grad Blickwinkel spiegelte ihre glatte Oberseite
    den Himmel -- Leuchtdichte 0,44 bei einer Mulch-Albedo von 0,06, in
    Vorlage und Unreal gleich. Im Bild stand ein heller Teller unter dem
    Baum.

    Jetzt: Rand 1 cm UNTER der Rasenkante (das Gras deckt ihn), zur Mitte
    1,5 cm gewoelbt, darauf Klumpen von 8 bis 15 cm Groesse mit bis zu
    8 mm Hoehe, die die Spiegelung zerlegen, und ein Umriss, der um
    12 % schwankt -- Mulch wird geschuettet, nicht gezirkelt."""
    versatz = Vector((samen * 1.7, samen * 0.3, 0.0))
    bm = bmesh.new()
    z0 = _boden_z(gelaende, x, y)
    ringe = [[bm.verts.new((x, y, z0 + 0.015))]]
    for f in (0.30, 0.55, 0.78, 1.0):
        ring = []
        for j in range(seiten):
            a = 2.0 * math.pi * j / seiten
            d = Vector((math.cos(a), math.sin(a), 0.0))
            umriss = 1.0 + 0.12 * noise.noise(d * 1.3 + versatz)
            px = x + d.x * r * f * umriss
            py = y + d.y * r * f * umriss
            klumpen = 0.008 * noise.noise(Vector((px * 9.0, py * 9.0, samen)))
            hz = _boden_z(gelaende, px, py) + 0.015 * (1.0 - f * f) \
                - 0.010 * f ** 4 + klumpen * (1.0 - f)
            ring.append(bm.verts.new((px, py, hz)))
        ringe.append(ring)
    for i in range(len(ringe) - 1):
        a, b = ringe[i], ringe[i + 1]
        for j in range(seiten):
            k = (j + 1) % seiten
            if len(a) == 1:
                bm.faces.new((a[0], b[j], b[k]))
            else:
                bm.faces.new((a[j], b[j], b[k], a[k]))
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    for p in me.polygons:
        p.use_smooth = True
    ob = bpy.data.objects.new(name, me)
    samml.objects.link(ob)
    me.materials.append(mat)
    return ob


def mulch_und_laub(samml, m, rnd):
    """Jeder Baum bekommt eine Baumscheibe aus Mulch und Laub darunter.

    Ohne das steht der Baum wie ein Pfahl in der Wiese. Die Scheiben sind
    nicht gleich gross und nicht zentriert -- Mulch wird geschuettet,
    nicht gezirkelt. Seit dem 21.09.2026 kleiner (0,35 bis 0,59 m statt
    0,55 bis 0,85 m): Die Zypressen sind jetzt bis zum Boden benadelt,
    die Scheibe schaut nur noch als dunkler Saum darunter hervor.
    """
    teile = []
    staemme = [o for o in bpy.data.objects
               if o.name.startswith('zypresse') and o.name.endswith('_stamm')]
    blatt = bpy.data.materials.get('Zypresse') or m['blatt']
    gelaende = bpy.data.objects.get('p08_gelaende')
    for i, st in enumerate(staemme):
        x, y = st.location.x, st.location.y
        # Dieselben drei Zuege wie frueher (Radius, Versatz x, Versatz y),
        # damit das Laub danach an denselben Stellen liegt.
        r = rnd.uniform(0.55, 0.85)
        dx, dy = rnd.uniform(-0.08, 0.08), rnd.uniform(-0.08, 0.08)
        ring = _baumscheibe('mulch_%d' % i, x + dx, y + dy,
                            0.35 + (r - 0.55) * 0.8, gelaende, samml,
                            m['mulch'], i)
        teile.append(ring)
        for k in range(rnd.randint(5, 11)):
            a = rnd.uniform(0.0, 6.283)
            d = rnd.uniform(r * 0.4, r * 2.1)
            lx, ly = x + math.cos(a) * d, y + math.sin(a) * d
            # Auf dem Gelaende statt auf fester Hoehe (siehe _baumscheibe);
            # der Aufruf zieht nichts aus rnd, die Reihenfolge bleibt.
            teile.append(quader(
                'laub_%d_%d' % (i, k), lx, ly,
                _boden_z(gelaende, lx, ly) + 0.002, rnd.uniform(0.02, 0.05),
                rnd.uniform(0.01, 0.03), 0.004, samml, blatt,
                drehung=rnd.uniform(0.0, 6.283)))
    return teile


def kiesel(samml, m, rnd, anzahl=420):
    """Steine auf dem Vorplatz: viele kleine, wenige grosse.

    Eine Kiesflaeche allein aus einem Material mit Bump hat keine
    Silhouette. Erst einzelne Steine, die ueber die Kante ragen und eigene
    Schatten werfen, machen daraus Kies. Alle teilen sich EIN Netz, sind
    also fuer Cycles Instanzen und kosten fast nichts.
    """
    me = bpy.data.meshes.new('kiesel')
    bm = bmesh.new()
    bmesh.ops.create_icosphere(bm, subdivisions=1, radius=1.0)
    for v in bm.verts:
        v.co.x *= rnd.uniform(0.8, 1.25)
        v.co.y *= rnd.uniform(0.8, 1.25)
        v.co.z *= rnd.uniform(0.45, 0.75)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(m['stein'])

    # Der Vorplatz aus villa.aussen_bauen: x von A_X0-1,00 ueber 9,00 m,
    # y von A_Y0-7,00 ueber 7,00 m.
    x0, y0, dx, dy = -1.0, -7.0, 9.0, 7.0
    teile = []
    for i in range(anzahl):
        # Groessenverteilung mit Potenz: viele kleine, wenige grosse.
        gr = 0.012 + (rnd.random() ** 3.2) * 0.075
        ob = bpy.data.objects.new('kiesel_%d' % i, me)
        ob.location = (x0 + rnd.random() * dx, y0 + rnd.random() * dy,
                       -0.058 + gr * 0.25)
        ob.scale = (gr, gr, gr)
        # Bewusst NICHT ins Netz gebacken, anders als ueberall sonst in
        # dieser Datei: Alle 420 Kiesel teilen sich EIN Netz, und
        # Backen hiesse 420 Kopien. Ein Kiesel ist annaehernd rund --
        # geht seine Drehung auf dem FBX-Weg verloren, sieht man es
        # nicht. Bei einem Stuhl sieht man es sofort, deshalb dort.
        ob.rotation_euler = (rnd.uniform(0, 6.283), rnd.uniform(0, 6.283),
                             rnd.uniform(0, 6.283))
        samml.objects.link(ob)
        teile.append(ob)
    return teile


def randgras(gelaende, laenge=0.24, halme=60000):
    """Hoeheres Gras entlang der Gartenmauer und am Sockel des Hauses.

    Dort maeht niemand. Das ist einer der wenigen Punkte, an denen ein
    einziges Detail eine ganze Flaeche glaubwuerdig macht: Ein Rasen, der
    bis zur Wand exakt gleich hoch bleibt, ist gemalt.
    """
    if gelaende is None:
        return None
    me = gelaende.data
    name = 'kante'
    alt = gelaende.vertex_groups.get(name)
    if alt is not None:
        gelaende.vertex_groups.remove(alt)
    gruppe = gelaende.vertex_groups.new(name=name)

    # Linien, an denen nicht gemaeht wird: Gartenmauer (x = -2,40 bis -2,10,
    # y -6 bis 20) und der Hauskoerper (x 0..18, y 0..13,5).
    def naehe(x, y):
        d_mauer = abs(x + 2.25) if -6.5 <= y <= 20.5 else 99.0
        dx = max(-0.4 - x, 0.0, x - 18.4)
        dy = max(-0.4 - y, 0.0, y - 13.9)
        d_haus = math.hypot(dx, dy)
        return min(d_mauer, d_haus)

    n = 0
    for v in me.vertices:
        d = naehe(v.co.x, v.co.y)
        if d < 0.9:
            w = 1.0 - (d / 0.9) ** 1.5
            if w > 0.02:
                gruppe.add([v.index], w, 'REPLACE')
                n += 1
    if n == 0:
        return None

    mat = bpy.data.materials.get('Grashalm')
    if mat is not None and mat.name not in [x.name for x in me.materials if x]:
        me.materials.append(mat)
    index = ([x.name for x in me.materials].index(mat.name) + 1) if mat else 1

    mod = gelaende.modifiers.new('Randgras', 'PARTICLE_SYSTEM')
    ps = gelaende.particle_systems[-1]
    s = ps.settings
    s.name = 'Randgras'
    s.type = 'HAIR'
    s.count = halme
    s.hair_length = laenge
    s.hair_step = 4
    s.use_advanced_hair = True
    s.material = index
    ps.vertex_group_density = name
    s.child_type = 'INTERPOLATED'
    s.rendered_child_count = 5
    s.child_percent = 2
    s.child_radius = 0.06
    s.clump_factor = 0.34
    s.roughness_1 = 0.42
    s.roughness_1_size = 0.09
    s.roughness_endpoint = 0.30
    s.kink = 'CURL'
    s.kink_amplitude = 0.02
    s.kink_frequency = 1.1
    s.brownian_factor = 0.012
    s.root_radius = 0.0024
    s.tip_radius = 0.0002
    s.use_hair_bspline = True
    mod.show_viewport = False
    return ps


# ------------------------------------------------------------------ Ruf

def moeblieren(samml=None, samen=8123, leicht=False):
    """Alles zusammen. Jeder Teil einzeln abgesichert: Wenn ein Stueck
    scheitert, faellt nicht der ganze Garten aus.

    leicht=True laesst weg, was nur fuer gerechnete Bilder zaehlt:
    420 Kiesel, 96 Laubstuecke und das Randgras. Die MOEBEL bleiben auch
    im Browser drin, und das ist kein Luxus, sondern eine Frage der
    Ehrlichkeit: Das Register "Foto oder Echtzeit" stellt beide Fassungen
    nebeneinander. Ein moebliertes Foto neben einem leeren Echtzeitbild
    vergleicht nicht zwei Verfahren, sondern zwei Szenen -- und der
    Besucher haelt den Unterschied fuer eine Schwaeche der Echtzeit."""
    rnd = random.Random(samen)
    s = samml or _samml()
    m = materialien()
    bericht = {}

    def versuch(schluessel, fn):
        try:
            r = fn()
            bericht[schluessel] = len(r) if isinstance(r, list) else bool(r)
        except Exception as f:
            bericht[schluessel] = 'FEHLER: %s' % f

    # Zwei Liegen laengs des Pools (Pool: x 5,80..16,20, y 14,80..19,20).
    # Sie stehen auf dem Travertinrand noerdlich des Beckens.
    versuch('liege1', lambda: liege('liege1', 7.4, 20.35, TERRASSE_OBEN,
                                    math.radians(2.0), s, m, rnd))
    versuch('liege2', lambda: liege('liege2', 9.9, 20.45, TERRASSE_OBEN,
                                    math.radians(-3.5), s, m, rnd))
    versuch('tischchen', lambda: beistelltisch('bt1', 8.65, 20.9,
                                               TERRASSE_OBEN, s, m))
    versuch('schirm', lambda: sonnenschirm('schirm1', 12.4, 20.6,
                                           TERRASSE_OBEN, s, m, offen=True))
    versuch('schirm_zu', lambda: sonnenschirm('schirm2', 15.1, 20.5,
                                              TERRASSE_OBEN, s, m, offen=False))
    # Essplatz auf der Terrasse unter der Auskragung.
    versuch('esstisch', lambda: esstisch_aussen('aussen_tisch', 8.2, 12.6,
                                                TERRASSE_OBEN,
                                                math.radians(1.5), s, m, rnd))
    bl = not leicht
    versuch('kuebel', lambda: (kuebel('kuebel1', 1.2, 12.4, TERRASSE_OBEN, s, m, rnd, bl)
                               + kuebel('kuebel2', 17.4, 12.2, TERRASSE_OBEN, s, m, rnd, bl)
                               + kuebel('kuebel3', 0.4, -1.9, TERRASSE_OBEN, s, m, rnd, bl)))
    if not leicht:
        versuch('mulch', lambda: mulch_und_laub(s, m, rnd))
        versuch('kiesel', lambda: kiesel(s, m, rnd))
        versuch('randgras',
                lambda: randgras(bpy.data.objects.get('p08_gelaende')))
    return bericht


# Die Werte, mit denen der Browser die neuen Materialien rechnet. Sie sind
# aus den oben gebauten Materialien abgeschrieben, nicht neu erfunden --
# sonst haben Standbild und Echtzeit verschiedene Moebel.
NETZ_MATERIAL_AUSSEN = {
    'Leinen':      {'farbe': (0.640, 0.618, 0.575), 'rauheit': 0.88, 'metall': 0.0},
    'Teak':        {'farbe': (0.232, 0.148, 0.078), 'rauheit': 0.52, 'metall': 0.0},
    'Terrakotta':  {'farbe': (0.198, 0.092, 0.052), 'rauheit': 0.78, 'metall': 0.0},
    'Mulch':       {'farbe': (0.062, 0.044, 0.030), 'rauheit': 0.92, 'metall': 0.0},
    # Seit dem 21.09.2026 tragen die Kuebel Olivenstaemmchen (villa.py).
    'Olivenrinde': {'farbe': (0.235, 0.215, 0.188), 'rauheit': 0.88, 'metall': 0.0},
    'Olivenlaub':  {'farbe': (0.094, 0.112, 0.062), 'rauheit': 0.76, 'metall': 0.0},
}
