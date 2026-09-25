# -*- coding: utf-8 -*-
"""
villa_garten.py -- Die Welt um das Haus. Nur fuer gerechnete Bilder.

WARUM DIESE DATEI UEBERHAUPT ENTSTANDEN IST
Der erste fertige Satz Cycles-Bilder sah aus wie eine Architektursoftware,
nicht wie eine Fotografie. Drei Dinge waren schuld, und alle drei liegen
NICHT am Haus:

  1. Der Rasen war eine 500 x 500 m grosse, absolut ebene Platte mit einem
     Farbrauschen darauf. Eine Ebene ohne Hoehe hat keine Streiflichter,
     keinen Horizontbruch und keinen Kontakt zum Gebaeude -- sie liest sich
     als Tischplatte, egal wie gut das Material ist.
  2. Es gab keinen einzigen sichtbaren Schlagschatten. Die Sonne stand auf
     Azimut 191 Grad, also fast genau hinter der Kamera; der Hausschatten
     fiel hinter das Haus und war im Bild nicht vorhanden. Ein Gebaeude
     ohne Schatten auf dem Boden schwebt.
  3. Die Zypressen waren zwoelfseitige Kegel. Ein Kegel bleibt ein Kegel,
     auch mit dem besten Material.

Die Doktrin dazu: Welt zuerst, dann Bild. Diese Datei baut die Welt.

WARUM SIE NICHT IN villa.py STEHT
villa.py liefert AUCH das Netz fuer den Browser. Gelaende mit 180.000
Dreiecken und 600.000 Grashalme gehoeren nicht in eine Datei, die ueber
eine Mobilfunkverbindung geladen wird. Deshalb: villa.py baut das Haus und
eine einfache Rasenplatte, villa_garten.py ersetzt die Platte fuer die
gerechneten Bilder durch eine Landschaft.
"""

import bmesh
import bpy
import math
import random

from mathutils import Vector, noise

# Der bebaute Bereich, der eben bleiben muss. Etwas grosszuegiger als das
# Haus selbst, damit Terrasse, Pool und Vorplatz nicht in einer Mulde
# liegen oder von einem Huegel geschnitten werden.
EBEN_X0, EBEN_X1 = -7.0, 22.0
EBEN_Y0, EBEN_Y1 = -11.0, 24.0
UEBERGANG = 14.0          # Meter, ueber die die Ebene in die Landschaft laeuft

RASEN_OBEN = -0.38        # Hoehe der Rasenoberkante aus villa.aussen_bauen


# --------------------------------------------------------------- Hilfen

def _hol(name):
    return bpy.data.objects.get(name)


def _sammlung_von(ob):
    return ob.users_collection[0] if ob.users_collection else bpy.context.scene.collection


def _ebenheit(x, y):
    """1.0 = voellig eben (bebaut), 0.0 = freie Landschaft.

    Kein harter Rand: ueber UEBERGANG Meter laeuft der Wert mit einer
    Glaettungskurve aus. Ein harter Rand waere im Streiflicht als Kante
    sichtbar -- und Streiflicht ist genau das, wofuer das Gelaende da ist.
    """
    dx = max(EBEN_X0 - x, 0.0, x - EBEN_X1)
    dy = max(EBEN_Y0 - y, 0.0, y - EBEN_Y1)
    d = math.hypot(dx, dy)
    if d <= 0.0:
        return 1.0
    if d >= UEBERGANG:
        return 0.0
    t = d / UEBERGANG
    return 1.0 - (t * t * (3.0 - 2.0 * t))


def _hoehe(x, y):
    """Die Landschaft in drei Groessenordnungen -- Makro, Mittel, Mikro.

    Das ist keine Spielerei mit Rauschzahlen, sondern die Stufung, die ein
    Gelaende ueberhaupt erst lesbar macht: Huegel am Horizont geben dem
    Bild Tiefe, die Mittelwellen geben dem Streiflicht etwas zu tun, und
    das feine Korn verhindert, dass grosse Flaechen in einem Ton liegen.
    """
    r = math.hypot(x, y)

    # Makro: Huegel. Erst ab 80 m, damit der Garten Garten bleibt.
    m_amp = 0.0
    if r > 80.0:
        m_amp = min((r - 80.0) / 130.0, 1.0) ** 1.6 * 16.0
    makro = noise.noise(Vector((x * 0.0062, y * 0.0062, 0.0))) * m_amp

    # Mittel: sanfte Wellen ueber das Grundstueck, plus/minus 40 cm.
    mittel = noise.noise(Vector((x * 0.031, y * 0.031, 11.0))) * 0.42

    # Mikro: Unebenheit einer gemaehten Wiese, plus/minus 4 cm. Sie traegt
    # im Bild fast nichts -- ausser im Streiflicht, und dort alles.
    mikro = noise.noise(Vector((x * 0.62, y * 0.62, 23.0))) * 0.045

    return makro + mittel + mikro


# Der Fernbereich. Ringe ausserhalb der Gelaendekante, jeder 21,7 Prozent
# weiter draussen als der vorige, bis 6 km.
FERN_RINGE = 16
# 20.09.2026, 03:25 Uhr: von 6 km auf 22 km.
#
# GEMESSEN, IN EINEM STREIFEN AUS NUR LAND UND HIMMEL:
#   Stufe zwischen zwei benachbarten Bildzeilen  0,2245
#   Gesamtspanne Land gegen Himmel               0,4881
# Die HAELFTE des gesamten Unterschieds passiert also in einer
# einzigen Pixelzeile. Das ist keine Luftperspektive, das ist ein
# Schnitt.
#
# Der Grund ist Geometrie, nicht Material: Bei einer Kamerahoehe von
# 4,4 m liegt der geometrische Horizont bei rund 7,5 km. Ein Gelaende,
# das bei 6 km aufhoert, endet also DIESSEITS des Horizonts -- dahinter
# steht Himmel, wo in Wirklichkeit noch Land waere. Deshalb half auch
# der Hoehennebel nichts: Er kann keine Flaeche aufhellen, die es nicht
# gibt.
# Bei 22 km liegt die Kante 0,011 Grad unter der Augenhoehe, also
# innerhalb einer Pixelzeile der Fluchtlinie -- und das Land davor hat
# den vollen Weg durch die Atmosphaere hinter sich.
# Kostet keinen einzigen Punkt mehr: Es sind dieselben 16 Ringe, nur
# mit groesseren Schritten.
FERN_WEITE = 22000.0


def _hoehe_fern(x, y, t):
    """Hoehe im Fernbereich. t = 0 an der Gelaendekante, 1 am Horizont.

    WARUM ES DEN FERNBEREICH GIBT
    Das Gelaende war 520 x 520 m gross und hoerte dann auf. Im Bild vom
    19.09.2026 lag bei rund 260 m eine waagerechte Linie quer durchs Bild:
    die Kante, dahinter Himmel. Ein Betrachter kann nicht sagen, was er
    da sieht, aber er sieht es sofort.

    WARUM NICHT EINFACH GROESSER
    Ein gleichmaessiges Raster bis 6 km haette 44 Millionen Dreiecke. Die
    Aufloesung, die im Garten noetig ist, ist in 4 km Entfernung
    Verschwendung: Dort ist ein 100-m-Huegel 60 Pixel hoch. Also Ringe mit
    wachsendem Abstand -- 16 Ringe kosten 13.800 Dreiecke.

    ZWEI GROESSENORDNUNGEN, ABSICHTLICH ANDERE ALS IM GARTEN
    Das feine Rauschen aus _hoehe hat 160 m Wellenlaenge. Wenn die Ringe
    100 m auseinanderliegen, wird daraus Zacken statt Landschaft. Es wird
    deshalb ueber die ersten beiden Ringe ausgeblendet, und dafuer kommen
    Huegelzuege mit 1.200 m Wellenlaenge -- das ist die Groesse, in der ein
    Huegel am Horizont als Huegel gelesen wird.
    """
    nah = _hoehe(x, y) * max(1.0 - t / 0.22, 0.0)
    auf = min(t / 0.35, 1.0)
    weit = (noise.noise(Vector((x * 0.00082, y * 0.00082, 5.0))) * 52.0
            + noise.noise(Vector((x * 0.0029, y * 0.0029, 17.0))) * 11.0) * auf
    # Die letzten vier Ringe laufen auf Null aus. Sonst zackt die
    # Silhouette genau dort, wo sie am ruhigsten sein muesste.
    ab = max((t - 0.72) / 0.28, 0.0)
    return (nah + weit) * (1.0 - ab * ab * (3.0 - 2.0 * ab))


def fernbereich(randpunkte, sammlung, mat, kante=520.0):
    """Der Fernbereich als EIGENES Netz -- und das ist kein Schoenheits-
    fehler, sondern die Lehre aus dem Lauf vom 20.09.2026, 04:01 Uhr.

    WAS PASSIERT IST
    Nah- und Fernbereich lagen in EINEM Netz. Beim Zug auf 22 km hatte
    dieses Netz eine Ausdehnung von 44 km. Nach dem Import meldete
    Unreal dafuer 252 Dreiecke statt 202.752: Nanite baut sein
    Ersatznetz mit einem Fehlermass, das an der GROESSE des Netzes
    haengt, und bei 44 km ist selbst ein Haus innerhalb der Toleranz.
    Im gerechneten Bild lag deshalb eine glatte Ebene ueber Terrasse,
    Pool und Erdgeschoss -- das halbe Haus war unter dem eigenen
    Gelaende begraben.

    Zwei Netze loesen das an der Wurzel:
      p08_gelaende       520 x 520 m, 165.888 Dreiecke, Nanite an.
      p08_gelaende_fern  bis 22 km,    36.864 Dreiecke, Nanite AUS.
    Das Fernnetz braucht kein Nanite: 36.864 Dreiecke sind nichts, und
    eine Vereinfachung waere dort ohnehin sinnlos, weil jedes Dreieck
    schon kilometergross ist.

    Die Naht kann nicht aufgehen, weil die erste Ringreihe GENAU die
    Randpunkte des Nahnetzes sind -- dieselben Zahlen, nicht dieselben
    Punkte. Eine doppelte Kante ohne Spalt.
    """
    me = bpy.data.meshes.new('p08_gelaende_fern')
    bm = bmesh.new()
    innen = [bm.verts.new(p) for p in randpunkte]
    cx, cy = 7.5, 7.0                       # Mitte des Rasters
    halb = kante / 2.0
    faktor = (FERN_WEITE / halb) ** (1.0 / FERN_RINGE)
    for k in range(1, FERN_RINGE + 1):
        s = faktor ** k
        t = float(k) / FERN_RINGE
        aussen = []
        for p in randpunkte:
            x = cx + (p.x - cx) * s
            y = cy + (p.y - cy) * s
            aussen.append(bm.verts.new((x, y, RASEN_OBEN + _hoehe_fern(x, y, t))))
        bm.verts.ensure_lookup_table()
        for i in range(len(randpunkte)):
            j = (i + 1) % len(randpunkte)
            # UMLAUFSINN, 21.09.2026. Hier stand (innen[i], innen[j],
            # aussen[j], aussen[i]). Der Randumlauf laeuft gegen den
            # Uhrzeigersinn; von innen[i] nach innen[j] und dann nach
            # AUSSEN ist, von oben gesehen, der Uhrzeigersinn -- jede
            # Flaeche zeigte nach UNTEN. Cycles schattiert eine
            # Rueckseite wie eine Vorderseite, deshalb sah man es hier
            # nie. Unreals Pfadverfolger bei einseitigem Material nicht:
            # Dort lag das Fernnetz als schwarzes Band am Horizont.
            bm.faces.new((innen[i], aussen[i], aussen[j], innen[j]))
        innen = aussen
    bm.normal_update()
    oben = sum(1 for f in bm.faces if f.normal.z > 0.0)
    print('[garten] Fernbereich: %d von %d Flaechen zeigen nach oben'
          % (oben, len(bm.faces)))
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new('p08_gelaende_fern', me)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    for poly in me.polygons:
        poly.use_smooth = True
    print('[garten] Fernbereich: %d Dreiecke bis %.0f km'
          % (len(me.polygons) * 2, FERN_WEITE / 1000.0))
    return ob


def gelaende(feinheit=1.8, kante=520.0):
    """Ersetzt die Rasenplatte durch ein Gelaende.

    feinheit: Kantenlaenge einer Zelle in Metern im INNEREN Bereich.
              1,8 m klingt grob, reicht aber: Die Mikrowelle ist 4 cm hoch,
              eine feinere Aufloesung kostet Dreiecke und zeigt nichts.
              Das Gras darauf ist die eigentliche Feinstruktur.
    """
    alt = _hol('p08_rasen')
    sammlung = _sammlung_von(alt) if alt else bpy.context.scene.collection
    mat = alt.data.materials[0] if (alt and alt.data.materials) else None
    if alt is not None:
        bpy.data.objects.remove(alt, do_unlink=True)

    n = int(kante / feinheit)
    if n % 2:
        n += 1
    schritt = kante / n

    me = bpy.data.meshes.new('p08_gelaende')
    bm = bmesh.new()
    gitter = []
    for iy in range(n + 1):
        zeile = []
        y = -kante / 2.0 + iy * schritt + 7.0
        for ix in range(n + 1):
            x = -kante / 2.0 + ix * schritt + 7.5
            e = _ebenheit(x, y)
            z = RASEN_OBEN + (1.0 - e) * _hoehe(x, y)
            zeile.append(bm.verts.new((x, y, z)))
        gitter.append(zeile)
    bm.verts.ensure_lookup_table()
    for iy in range(n):
        for ix in range(n):
            bm.faces.new((gitter[iy][ix], gitter[iy][ix + 1],
                          gitter[iy + 1][ix + 1], gitter[iy + 1][ix]))

    # Der Randumlauf des Rasters, gegen den Uhrzeigersinn und LUECKENLOS.
    # Er wird gleich als Anfang des Fernbereichs wiederverwendet.
    rand = ([gitter[0][ix] for ix in range(n + 1)]
            + [gitter[iy][n] for iy in range(1, n + 1)]
            + [gitter[n][ix] for ix in range(n - 1, -1, -1)]
            + [gitter[iy][0] for iy in range(n - 1, 0, -1)])
    randpunkte = [v.co.copy() for v in rand]
    bm.normal_update()
    bm.to_mesh(me)
    bm.free()

    ob = bpy.data.objects.new('p08_gelaende', me)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    for poly in me.polygons:
        poly.use_smooth = True

    fernbereich(randpunkte, sammlung, mat)

    # Dichtegruppe fuers Gras: voll im Vordergrund, aus ab 55 m. Weiter
    # draussen traegt der Shader allein -- ein Halm, der ein Zehntel Pixel
    # gross ist, kostet Speicher und zeigt nichts.
    # 40 m voll, dann ueber 130 m auslaufend.
    # Erst standen hier 34 und 58 m -- und man sah den Rand als dunkles
    # Band quer durchs Bild. Der Grund ist nicht die Grenze selbst: Gras
    # aus Geometrie beschattet sich gegenseitig und ist deshalb IMMER
    # dunkler als dieselbe Flaeche ohne Halme, egal wie gut die Farben
    # zusammenpassen. Ein Sprung faellt auf, ein Verlauf ueber 130 m nicht.
    # Dass die Halme draussen dabei duenn stehen, ist richtig so: Dort
    # brauchen sie nur noch die Flaeche aufzurauhen.
    gruppe = ob.vertex_groups.new(name='nah')
    mitte = Vector((8.0, 9.0, 0.0))
    for v in me.vertices:
        d = (Vector((v.co.x, v.co.y, 0.0)) - mitte).length
        if d < 40.0:
            w = 1.0
        elif d > 170.0:
            w = 0.0
        else:
            t = (d - 40.0) / 130.0
            w = 1.0 - (t * t * (3.0 - 2.0 * t))
        if w > 0.0:
            gruppe.add([v.index], w, 'REPLACE')
    return ob


def gras(ob, halme=90000, kinder=7, laenge=0.085):
    """Echte Halme im Vordergrund.

    WARUM UEBERHAUPT GEOMETRIE
    Frueher stand hier die Ueberlegung, ein gutes Rasenmaterial reiche.
    Das stimmt bei senkrechter Aufsicht. Diese Kamera steht auf 4,4 m und
    schaut flach ueber die Flaeche -- unter diesem Winkel sieht man die
    Halme von der SEITE, sie ueberdecken einander, und genau diese
    Ueberdeckung ist das, was ein Auge als Rasen erkennt. Ein Material
    kann das nicht nachbilden, weil es keine Silhouette hat.

    90.000 Bueschel mit sieben Kindern sind rund 630.000 Straehnen. Auf
    einer RTX 5070 kostet das in dieser Szene knapp eine Minute je Bild --
    gemessen, nicht geschaetzt.
    """
    mat = bpy.data.materials.get('Grashalm')
    if mat is None:
        mat = bpy.data.materials.new('Grashalm')
    if mat.name not in [m.name for m in ob.data.materials if m]:
        ob.data.materials.append(mat)
    index = list(ob.data.materials).index(mat)

    mod = ob.modifiers.new('Gras', 'PARTICLE_SYSTEM')
    ps = ob.particle_systems[-1]
    s = ps.settings
    s.name = 'Grashalme'
    s.type = 'HAIR'
    s.count = halme
    s.hair_length = laenge
    s.hair_step = 3
    s.use_advanced_hair = True
    s.material = index + 1
    # Die Dichtegruppe haengt am SYSTEM, nicht an den Einstellungen --
    # eine Einstellung kann von mehreren Objekten geteilt werden, die
    # Gruppe gehoert zu diesem einen Netz.
    if 'nah' in [g.name for g in ob.vertex_groups]:
        ps.vertex_group_density = 'nah'

    # Halme stehen nicht senkrecht und nicht alle gleich hoch. Ohne diese
    # drei Zahlen sieht der Rasen aus wie ein Nagelbrett.
    s.child_type = 'INTERPOLATED'
    s.rendered_child_count = kinder
    s.child_percent = max(1, kinder // 3)   # nur fuer die Oberflaeche
    s.child_size = 1.0
    s.child_size_random = 0.35
    s.child_radius = 0.10
    s.child_roundness = 0.4
    s.clump_factor = 0.22
    s.roughness_1 = 0.28
    s.roughness_1_size = 0.06
    s.roughness_endpoint = 0.18
    s.kink = 'CURL'
    s.kink_amplitude = 0.008
    s.kink_frequency = 1.4
    s.brownian_factor = 0.004

    s.root_radius = 0.0022
    s.tip_radius = 0.0002
    s.radius_scale = 1.0
    s.use_hair_bspline = True

    mod.show_viewport = False        # sonst wird die Oberflaeche unbedienbar
    return ps


def gras_material():
    """Halmmaterial: durchscheinend, an der Spitze heller.

    Ein Grashalm ist kein undurchsichtiger gruener Stab. Gegen die Sonne
    leuchtet er -- das ist der Unterschied zwischen einer Wiese und einem
    gruenen Teppich. Deshalb Translucent im Mix, nicht nur Diffus.
    """
    mat = bpy.data.materials.get('Grashalm') or bpy.data.materials.new('Grashalm')
    mat.use_nodes = True
    nt = mat.node_tree
    for n in list(nt.nodes):
        nt.nodes.remove(n)
    aus = nt.nodes.new('ShaderNodeOutputMaterial')
    misch = nt.nodes.new('ShaderNodeMixShader')
    b = nt.nodes.new('ShaderNodeBsdfPrincipled')
    tr = nt.nodes.new('ShaderNodeBsdfTranslucent')

    # Farbe laeuft ueber die Halmlaenge: unten feucht und dunkel, oben
    # trockener. Hair Info -> Intercept ist 0 an der Wurzel, 1 an der Spitze.
    info = nt.nodes.new('ShaderNodeHairInfo')
    ramp = nt.nodes.new('ShaderNodeValToRGB')
    ramp.color_ramp.elements[0].position = 0.0
    ramp.color_ramp.elements[0].color = (0.026, 0.052, 0.014, 1.0)
    ramp.color_ramp.elements[1].position = 1.0
    ramp.color_ramp.elements[1].color = (0.118, 0.165, 0.052, 1.0)
    nt.links.new(info.outputs['Intercept'], ramp.inputs['Fac'])

    # Und zusaetzlich von Bueschel zu Bueschel, sonst ist jeder Halm gleich.
    zuf = nt.nodes.new('ShaderNodeHairInfo')
    heller = nt.nodes.new('ShaderNodeMix')
    heller.data_type = 'RGBA'
    heller.inputs[7].default_value = (0.148, 0.186, 0.068, 1.0)
    nt.links.new(ramp.outputs['Color'], heller.inputs[6])
    nt.links.new(zuf.outputs['Random'], heller.inputs['Factor'])

    nt.links.new(heller.outputs[2], b.inputs['Base Color'])
    nt.links.new(heller.outputs[2], tr.inputs['Color'])
    b.inputs['Roughness'].default_value = 0.42
    nt.links.new(b.outputs[0], misch.inputs[1])
    nt.links.new(tr.outputs[0], misch.inputs[2])
    misch.inputs[0].default_value = 0.32
    nt.links.new(misch.outputs[0], aus.inputs[0])
    return mat


# ------------------------------------------------------------- Zypressen

def _zypresse(name, x, y, z, hoehe, breite, samen, sammlung, m_nadel, m_stamm):
    """Eine Zypresse aus drei uebereinanderliegenden, verformten Spindeln.

    Warum nicht ein Kegel mit Verschiebung: Ein Kegel hat eine gerade
    Mantellinie. Auch stark verrauscht bleibt die Silhouette eine Gerade,
    und das Auge erkennt sie. Drei Spindeln uebereinander geben der
    Silhouette Einschnuerungen -- das ist die Form, an der man eine
    Zypresse ueberhaupt erkennt.
    """
    rnd = random.Random(samen)
    teile = []
    # Vier Abschnitte statt drei, mit Ueberlappung. Der erste Versuch mit
    # drei dicken Spindeln sah aus wie Brokkoli: Die Einschnuerungen waren
    # zu tief und die Kugeln zu breit. Eine Mittelmeerzypresse ist ueber
    # 6 m Hoehe kaum 1,2 m breit -- das Verhaeltnis ist der ganze Baum.
    #
    # 21.09.2026: Der unterste Abschnitt beginnt jetzt 3 % der Hoehe UNTER
    # dem Rasen statt 6 % darueber. Vorher lief die untere Spindel zum
    # Boden hin spitz zu, und darunter stand ein halber Meter nackter,
    # heller Stamm -- ein Lutscher, wie der alte Kuebelbaum. Eine
    # Cupressus sempervirens ist bis zum Boden benadelt; an der
    # Rasenkante hat der Baum so noch gut die Haelfte seiner Breite.
    abschnitte = ((-0.03, 0.34, 1.00), (0.21, 0.34, 0.97),
                  (0.45, 0.32, 0.85), (0.67, 0.34, 0.55))
    for k, (u, h_rel, b_rel) in enumerate(abschnitte):
        bpy.ops.mesh.primitive_uv_sphere_add(segments=18, ring_count=12,
                                             radius=1.0,
                                             location=(0.0, 0.0, 0.0))
        ob = bpy.context.object
        for s in list(ob.users_collection):
            s.objects.unlink(ob)
        sammlung.objects.link(ob)
        ob.name = '%s_b%d' % (name, k)
        me = ob.data
        rb = breite * b_rel
        rh = hoehe * h_rel * 0.5
        for v in me.vertices:
            # Spindel: oben spitzer als unten
            f = 1.0 - max(0.0, v.co.z) * 0.45
            v.co.x *= rb * f
            v.co.y *= rb * f
            v.co.z *= rh
            # Buschige Unregelmaessigkeit. 0,17 klingt wenig und ist genau
            # der Punkt, an dem die Silhouette aufhoert, glatt zu sein,
            # ohne dass der Baum zerfranst.
            p = Vector((v.co.x * 3.4 + samen, v.co.y * 3.4, v.co.z * 2.2))
            d = noise.noise(p)
            richtung = v.co.normalized() if v.co.length > 1e-6 else Vector((0, 0, 1))
            v.co += richtung * d * rb * 0.20
        ob.location = (x + rnd.uniform(-0.09, 0.09),
                       y + rnd.uniform(-0.09, 0.09),
                       z + hoehe * (u + h_rel * 0.5))
        ob.rotation_euler = (rnd.uniform(-0.035, 0.035),
                             rnd.uniform(-0.035, 0.035),
                             rnd.uniform(0.0, 6.28))
        for poly in me.polygons:
            poly.use_smooth = True
        me.materials.append(m_nadel)
        teile.append(ob)

    bpy.ops.mesh.primitive_cylinder_add(vertices=10, radius=0.085,
                                        depth=hoehe * 0.28,
                                        location=(0.0, 0.0, 0.0))
    st = bpy.context.object
    for s in list(st.users_collection):
        s.objects.unlink(st)
    sammlung.objects.link(st)
    st.name = name + '_stamm'
    st.location = (x, y, z + hoehe * 0.14)
    st.data.materials.append(m_stamm)
    teile.append(st)
    return teile


def zypressen():
    """Ersetzt die Kegel durch gebaute Baeume, mit Streuung in Hoehe,
    Breite, Neigung und Standort. Gleich hohe Baeume in einer Reihe sind
    das zweitsicherste Zeichen fuer ein Rechnerbild -- nach dem fehlenden
    Schatten."""
    # 21.09.2026: AUCH 'p08_zypresse'. villa.haupt setzt vor jeden Namen
    # das Kuerzel seines Abschnitts -- aus 'zypresse_3' wird
    # 'p08_zypresse_3'. Die Suche nach 'zypresse...' fand deshalb nie
    # etwas, und die alte Kegelreihe aus villa.py stand die ganze Zeit
    # weiter nordlich am Pool, genau dort, wo sie laut der Begruendung
    # unten nicht stehen soll: zwischen Kamera und Haus. Im Blick
    # 'garten' waren das die glatten zwoelfseitigen Kegel rechts vorn --
    # Strahltest vom 21.09.: Bildpunkt 700:400 trifft 'p08_zypresse_7'.
    alt = [o for o in bpy.data.objects
           if o.name.startswith('zypresse') or o.name.startswith('p08_zypresse')]
    sammlung = _sammlung_von(alt[0]) if alt else bpy.context.scene.collection
    m_nadel = bpy.data.materials.get('Zypresse')
    m_stamm = bpy.data.materials.get('Eiche_Lamelle') or m_nadel
    for o in alt:
        bpy.data.objects.remove(o, do_unlink=True)

    rnd = random.Random(4711)
    gebaut = []
    # Die Reihe steht nicht mehr auf einer Linie: plus/minus 90 cm quer und
    # ungleiche Abstaende. Eine exakte Reihe liest sich als Zaun.
    #
    # BREITE: 0,42 bis 0,58 m Radius, also 0,9 bis 1,2 m Durchmesser bei
    # 6 bis 9 m Hoehe. Das ist das Verhaeltnis einer echten Cupressus
    # sempervirens 'Stricta'. Der erste Versuch stand bei 2,2 m Breite --
    # dreimal zu dick, und deshalb sah die Reihe aus wie Buchsbaumkugeln.
    # WO SIE STEHEN, IST WICHTIGER ALS WIE SIE AUSSEHEN.
    # Die Reihe stand auf y 21,6 -- also zwischen Kamera und Haus. Acht
    # neun Meter hohe Baeume standen damit genau im Hauptmotiv: Der Blick,
    # der das Haus verkaufen soll, zeigte eine Baumreihe mit Haus dahinter.
    # Jetzt laeuft die Reihe an der Westgrenze entlang, parallel zur
    # Gartenmauer, von der Kamera weg. Sie rahmt den rechten Bildrand,
    # gibt der Tiefe eine Staffel und verdeckt nichts.
    # ANFANG BEI y 0,0 statt -7,0. Die Ankunftskamera steht auf
    # (-7,5 / -13,0) und schaut nach Nordosten; ein Baum auf (-4,6 / -7)
    # liegt von dort 6,6 m entfernt und 17 Grad neben der Achse -- also
    # mitten im Bild. Zwei angeschnittene Blobs im linken Drittel sind
    # keine Rahmung, sondern eine Verdeckung. Ab y 0 bleibt die Reihe
    # ausserhalb dieses Blickfelds und rahmt den Gartenblick weiter.
    y = 0.0
    for i in range(7):
        h = rnd.uniform(6.0, 9.0)
        b = rnd.uniform(0.42, 0.58)
        x = -4.6 + rnd.uniform(-0.8, 0.8)
        gebaut += _zypresse('zypresse_%d' % i, x, y, RASEN_OBEN, h, b,
                            1000 + i * 37, sammlung, m_nadel, m_stamm)
        y += rnd.uniform(3.4, 4.6)

    # Drei als Hintergrund hinter dem Haus (Sueden). Sie geben der weissen
    # Fassade eine dunkle Folie -- ohne sie steht Weiss auf Himmel, und
    # die obere Haushaelfte verliert ihre Kante.
    #
    # ERST STANDEN SIE AUF y -9 BIS -11 und damit mitten im Ankunftsblick:
    # Die Kamera steht auf (-7,5 / -13,0) und schaut nach Nordosten, also
    # lagen zwei neun Meter hohe Baeume angeschnitten im linken Bilddrittel
    # und verdeckten den Eingang. 17 m weiter sued-suedoestlich sind sie
    # aus diesem Blickfeld heraus und stehen aus dem Garten gesehen immer
    # noch hinter dem Haus.
    for i, (bx, by) in enumerate(((6.0, -17.5), (13.0, -19.0), (20.0, -16.0))):
        h = rnd.uniform(6.4, 9.2)
        gebaut += _zypresse('zypresse_h%d' % i, bx, by, RASEN_OBEN, h,
                            rnd.uniform(0.44, 0.58), 2000 + i * 53,
                            sammlung, m_nadel, m_stamm)
    return gebaut


def nadel_material():
    """Nadelmasse: dunkel, matt, leicht durchscheinend an den Raendern."""
    mat = bpy.data.materials.get('Zypresse')
    if mat is None:
        return None
    mat.use_nodes = True
    nt = mat.node_tree
    for n in list(nt.nodes):
        nt.nodes.remove(n)
    aus = nt.nodes.new('ShaderNodeOutputMaterial')
    misch = nt.nodes.new('ShaderNodeMixShader')
    b = nt.nodes.new('ShaderNodeBsdfPrincipled')
    tr = nt.nodes.new('ShaderNodeBsdfTranslucent')

    tex = nt.nodes.new('ShaderNodeTexCoord')
    ra = nt.nodes.new('ShaderNodeTexNoise')
    ra.inputs['Scale'].default_value = 26.0
    ra.inputs['Detail'].default_value = 9.0
    ra.inputs['Roughness'].default_value = 0.74
    nt.links.new(tex.outputs['Object'], ra.inputs['Vector'])

    mix = nt.nodes.new('ShaderNodeMix')
    mix.data_type = 'RGBA'
    # Eine Zypresse ist fast schwarzgruen. Der helle Ton lag bei 0,072 --
    # im Bild wurden daraus blassgruene Kegel. 0,046 haelt sie dunkel
    # genug, dass die weisse Fassade eine Folie bekommt.
    mix.inputs[6].default_value = (0.014, 0.028, 0.013, 1.0)
    mix.inputs[7].default_value = (0.046, 0.076, 0.030, 1.0)
    nt.links.new(ra.outputs['Fac'], mix.inputs['Factor'])
    nt.links.new(mix.outputs[2], b.inputs['Base Color'])
    nt.links.new(mix.outputs[2], tr.inputs['Color'])
    b.inputs['Roughness'].default_value = 0.78

    # Die Oberflaeche ist keine Kugel, sondern Nadelwerk: ein kraeftiger
    # Bump macht aus der glatten Spindel eine Masse.
    bump = nt.nodes.new('ShaderNodeBump')
    bump.inputs['Strength'].default_value = 0.85
    bump.inputs['Distance'].default_value = 0.06
    nt.links.new(ra.outputs['Fac'], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])

    nt.links.new(b.outputs[0], misch.inputs[1])
    nt.links.new(tr.outputs[0], misch.inputs[2])
    # 0,22 war zu viel: Durchscheinen hellt die ganze Krone auf, auch da,
    # wo sie zwei Meter dick ist. 0,13 laesst nur die Raender leuchten --
    # und genau dort passiert es in der Wirklichkeit auch.
    misch.inputs[0].default_value = 0.13
    nt.links.new(misch.outputs[0], aus.inputs[0])
    return mat


# ------------------------------------------------------------- Poolwasser

def wasser_bewegt():
    """Kraeuselung auf dem Pool.

    Spiegelglattes Wasser gibt es in einem Aussenpool nie. Die Kraeuselung
    ist winzig -- 4 mm -- aber sie zerlegt die Spiegelung, und eine
    zerlegte Spiegelung ist der Unterschied zwischen Wasser und blauem
    Lack.
    """
    mat = bpy.data.materials.get('Wasser')
    if mat is None or not mat.use_nodes:
        return None
    nt = mat.node_tree
    b = next((n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED'), None)
    if b is None:
        return None
    tex = nt.nodes.new('ShaderNodeTexCoord')
    w1 = nt.nodes.new('ShaderNodeTexWave')
    w1.wave_type = 'BANDS'
    w1.bands_direction = 'DIAGONAL'
    w1.inputs['Scale'].default_value = 2.1
    w1.inputs['Distortion'].default_value = 9.0
    w1.inputs['Detail'].default_value = 5.0
    nt.links.new(tex.outputs['Object'], w1.inputs['Vector'])

    w2 = nt.nodes.new('ShaderNodeTexNoise')
    w2.inputs['Scale'].default_value = 11.0
    w2.inputs['Detail'].default_value = 7.0
    nt.links.new(tex.outputs['Object'], w2.inputs['Vector'])

    add = nt.nodes.new('ShaderNodeMix')
    add.data_type = 'FLOAT'
    add.inputs[0].default_value = 0.45
    nt.links.new(w1.outputs['Fac'], add.inputs[2])
    nt.links.new(w2.outputs['Fac'], add.inputs[3])

    bump = nt.nodes.new('ShaderNodeBump')
    bump.inputs['Strength'].default_value = 0.16
    bump.inputs['Distance'].default_value = 0.004
    nt.links.new(add.outputs[0], bump.inputs['Height'])
    nt.links.new(bump.outputs['Normal'], b.inputs['Normal'])
    return mat


# ------------------------------------------------------------------ Ruf

def aufwerten(mit_gras=True, halme=90000):
    """Alles zusammen. Wird NUR vor gerechneten Bildern gerufen."""
    ob = gelaende()
    gras_material()
    if mit_gras:
        gras(ob, halme=halme)
    nadel_material()
    baeume = zypressen()
    wasser_bewegt()
    dreiecke = len(ob.data.polygons) * 2
    return {'gelaende_dreiecke': dreiecke, 'baumteile': len(baeume),
            'halme': halme if mit_gras else 0}
