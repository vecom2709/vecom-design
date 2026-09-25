# -*- coding: utf-8 -*-
"""
villa_terrassenmoebel.py -- Tisch und Stuehle auf der Terrasse, gebaut
wie Tischlerei und nicht wie ein Drahtmodell.

WAS VORHER DASTAND, UND WARUM ES NICHT GETRAGEN HAT
Der Esstisch war eine Platte auf vier 6-cm-Pfosten, der Stuhl vier
3-cm-Staebe mit einer Sitzplatte und einer Rueckenplatte. Im Bild vom
20.09.2026 (Terrassenblick, die Gruppe steht mittig) liest sich das als
Gestell, nicht als Moebel -- und zwar aus drei Gruenden, die alle
nichts mit dem Material zu tun haben:

  1. KEINE FUGEN. Eine massive Platte von 2,20 x 0,95 m gibt es in Teak
     nicht; Holz arbeitet. Gebaut wird ein Lattenrost mit 8 mm Luft
     dazwischen, und genau diese acht Millimeter werfen im Streiflicht
     die Linien, an denen das Auge "Holz" erkennt.
  2. KEINE ZARGE. Eine Platte, die direkt auf Beinen sitzt, schwebt.
     Ein Tisch hat einen Rahmen darunter, und der wirft den Schatten,
     der die Platte erst auflegt.
  3. KEINE FASE. villa.kanten_brechen laeuft ueber villa.haupt und
     damit NICHT ueber die Aussenmoebel -- die entstehen erst danach in
     villa_aussen.moeblieren. Jede Kante hier war mathematisch scharf,
     und auf einer scharfen Kante gibt es kein Glanzlicht.

Dazu ein vierter Punkt, der ein echter Fehler war: villa_aussen.quader
dreht JEDE Kiste um ihre EIGENE Mitte. Der Stuhl wurde also nie
gedreht, sondern nur in sich verzogen. Hier traegt ein Moebelstueck
sein Netz in LOKALEN Koordinaten, und gedreht wird das Objekt.
"""

import bmesh
import bpy
import math


class Rohbau(object):
    """Sammelt Kaesten in EINEM Netz, in lokalen Koordinaten.

    Ein Stuhl ist ein Objekt und keine dreissig. Das spart drueben in
    Unreal dreissig Actors, und vor allem laesst es sich als Ganzes
    drehen -- was der bisherige Weg nicht konnte.
    """

    def __init__(self):
        self.bm = bmesh.new()

    def kasten(self, x0, y0, z0, dx, dy, dz):
        v = []
        for sz in (0.0, dz):
            for sy in (0.0, dy):
                for sx in (0.0, dx):
                    v.append(self.bm.verts.new((x0 + sx, y0 + sy, z0 + sz)))
        f = self.bm.faces.new
        f((v[0], v[1], v[3], v[2]))          # unten
        f((v[4], v[6], v[7], v[5]))          # oben
        f((v[0], v[4], v[5], v[1]))          # y0
        f((v[2], v[3], v[7], v[6]))          # y1
        f((v[0], v[2], v[6], v[4]))          # x0
        f((v[1], v[5], v[7], v[3]))          # x1

    def prisma(self, ecken_unten, ecken_oben):
        """Vier Punkte unten, vier oben -- fuer verjuengte Beine.

        Ein Tischbein, das oben und unten gleich dick ist, sieht aus wie
        ein Rohr. Echte Beine laufen nach unten schmaler zu; das ist
        nicht Zierde, sondern folgt der Beanspruchung.
        """
        vu = [self.bm.verts.new(p) for p in ecken_unten]
        vo = [self.bm.verts.new(p) for p in ecken_oben]
        f = self.bm.faces.new
        f((vu[0], vu[1], vu[2], vu[3]))
        f((vo[3], vo[2], vo[1], vo[0]))
        for i in range(4):
            j = (i + 1) % 4
            f((vu[i], vu[j], vo[j], vo[i]))

    def bein(self, x, y, z0, hoehe, b_oben, b_unten, neig_x=0.0, neig_y=0.0):
        """Ein verjuengtes Bein von (x, y) aus, oben breiter als unten."""
        ou, oo = b_unten / 2.0, b_oben / 2.0
        xo, yo = x + neig_x, y + neig_y
        unten = [(x - ou, y - ou, z0), (x + ou, y - ou, z0),
                 (x + ou, y + ou, z0), (x - ou, y + ou, z0)]
        oben = [(xo - oo, yo - oo, z0 + hoehe), (xo + oo, yo - oo, z0 + hoehe),
                (xo + oo, yo + oo, z0 + hoehe), (xo - oo, yo + oo, z0 + hoehe)]
        self.prisma(unten, oben)

    def fertig(self, name, sammlung, mat, fase=0.004, segmente=2,
               glatt=False):
        """Fase auf alles, dann ins Objekt.

        4 mm statt der 6 mm am Gebaeude: Ein Moebel wird gehobelt und
        geschliffen, eine Betonwand geschalt. Die Kante ist feiner, aber
        sie ist da -- und ohne sie gibt es auf keiner Kante ein
        Glanzlicht.
        """
        bmesh.ops.recalc_face_normals(self.bm, faces=self.bm.faces)
        if fase > 0.0:
            bmesh.ops.bevel(self.bm, geom=list(self.bm.verts)
                            + list(self.bm.edges) + list(self.bm.faces),
                            offset=fase, segments=segmente, profile=0.5,
                            affect='EDGES', clamp_overlap=True)
        me = bpy.data.meshes.new(name)
        self.bm.to_mesh(me)
        self.bm.free()
        ob = bpy.data.objects.new(name, me)
        sammlung.objects.link(ob)
        if mat is not None:
            me.materials.append(mat)
        for p in me.polygons:
            p.use_smooth = glatt
        return ob


def _setzen(ob, x, y, z, dreh):
    """Lage und Drehung ins NETZ rechnen, nicht ans Objekt haengen.

    TEUER BEZAHLT AM 20.09.2026, 06:00 UHR.
    Erst stand hier das Naheliegende:
        ob.location = (x, y, z)
        ob.rotation_euler = (0.0, 0.0, dreh)
    In Blender stand die Sitzgruppe damit richtig -- die Cycles-Probe
    zeigte Tisch und vier Stuehle aufrecht. In Unreal lagen sie flach
    auf der Terrasse wie hingeworfene Bretter.

    Dazwischen liegt der FBX-Weg. Der Exporter dreht die Szene von
    Z-oben auf Y-oben, der Importer dreht zurueck, und dazwischen
    steht `combine_meshes`, das mehrere Knoten zu EINEM Netz
    zusammenfasst. Die Verkettung dieser Drehungen mit einer eigenen
    Objektdrehung ist die Stelle, an der es schiefgeht.

    Warum das vorher NIE aufgefallen ist: Die alten Aussenmoebel waren
    symmetrische Kaesten. Ein Wuerfel, dessen Drehung verloren geht,
    sieht genauso aus wie einer mit Drehung. Erst ein Stuhl, der vorn
    und hinten unterscheidet, macht den Fehler sichtbar -- er war die
    ganze Zeit da.

    Die Loesung ist keine Einstellung, sondern eine Vermeidung: Das
    Netz traegt die Weltkoordinaten, das Objekt steht auf Identitaet.
    Dann gibt es keine Verkettung, die schiefgehen koennte. Genauso
    macht es villa.quader fuer alles andere im Haus.
    """
    from mathutils import Matrix
    ob.data.transform(Matrix.Translation((x, y, z))
                      @ Matrix.Rotation(dreh, 4, 'Z'))
    ob.location = (0.0, 0.0, 0.0)
    ob.rotation_euler = (0.0, 0.0, 0.0)
    return ob


# --------------------------------------------------------------- Tisch

TISCH_L, TISCH_B, TISCH_H = 2.20, 0.95, 0.755
LATTEN = 9
FUGE = 0.008


def esstisch(name, x, y, z, dreh, sammlung, m_holz, m_metall):
    """Teaktisch mit Lattenplatte auf Aluminiumgestell, 2,20 x 0,95 m.

    Die Lattenteilung ist nicht frei gewaehlt: Neun Latten und acht
    Fugen von 8 mm ergeben bei 95 cm Breite eine Lattenbreite von
    98,4 mm -- ein Mass, das es in Teak wirklich gibt. Fugen sind in
    Wirklichkeit kein Gestaltungsmittel, sondern Notwendigkeit: Holz
    quillt und schwindet, und eine fugenlose Platte von 95 cm Breite
    reisst im ersten Sommer.
    """
    latte = (TISCH_B - FUGE * (LATTEN - 1)) / LATTEN

    holz = Rohbau()
    for i in range(LATTEN):
        y0 = -TISCH_B / 2.0 + i * (latte + FUGE)
        holz.kasten(-TISCH_L / 2.0, y0, TISCH_H - 0.032, TISCH_L, latte, 0.032)
    ob_holz = holz.fertig(name + '_platte', sammlung, m_holz, fase=0.0035)

    metall = Rohbau()
    # Zarge: 9 cm hoch, 3,5 cm stark, 8 cm von der Plattenkante
    # eingerueckt. Sie ist der Grund, warum eine Platte aufliegt statt
    # zu schweben -- ihr Schatten zeichnet die Unterkante.
    zl, zb = TISCH_L - 0.16, TISCH_B - 0.16
    for sy in (-1, 1):
        metall.kasten(-zl / 2.0, sy * (zb / 2.0) - 0.0175,
                      TISCH_H - 0.032 - 0.09, zl, 0.035, 0.09)
    for sx in (-1, 1):
        metall.kasten(sx * (zl / 2.0) - 0.0175, -zb / 2.0 + 0.035,
                      TISCH_H - 0.032 - 0.09, 0.035, zb - 0.07, 0.09)
    # Beine, nach unten verjuengt und leicht nach aussen gestellt.
    for sx in (-1, 1):
        for sy in (-1, 1):
            metall.bein(sx * (zl / 2.0 - 0.02), sy * (zb / 2.0 - 0.02),
                        0.0, TISCH_H - 0.032 - 0.09, 0.062, 0.042,
                        neig_x=-sx * 0.018, neig_y=-sy * 0.014)
    # Gleiter unter den Beinen: 8 mm Kunststoff. Ein Tischbein, das
    # ohne Absatz auf dem Stein steht, sieht aus wie eingelassen.
    for sx in (-1, 1):
        for sy in (-1, 1):
            metall.kasten(sx * (zl / 2.0 - 0.02) - 0.024,
                          sy * (zb / 2.0 - 0.02) - 0.024, -0.002, 0.048,
                          0.048, 0.008)
    ob_metall = metall.fertig(name + '_gestell', sammlung, m_metall,
                              fase=0.003)

    return [_setzen(ob_holz, x, y, z, dreh),
            _setzen(ob_metall, x, y, z, dreh)]


# --------------------------------------------------------------- Stuhl

SITZ_H = 0.452
SITZ_B = 0.455
SITZ_T = 0.445
LEHNE_H = 0.455
LEHNE_NEIG = math.radians(13.0)


def stuhl(name, x, y, z, dreh, sammlung, m_holz, m_metall):
    """Teakstuhl mit Lattensitz und geneigter Lattenlehne.

    DAS WICHTIGSTE MASS IST DIE NEIGUNG.
    Eine senkrechte Rueckenlehne gibt es an keinem Stuhl, auf dem man
    essen mag; 13 Grad ist das uebliche Mass fuer einen Esszimmerstuhl
    (ein Sessel geht auf 20 bis 25). Die Hinterbeine laufen dabei OBEN
    WEITER -- sie sind dieselben Hoelzer wie die Lehnenpfosten, in
    einem Stueck. Genau daran erkennt das Auge einen gebauten Stuhl und
    nicht vier Staebe mit Brettern dazwischen.
    """
    holz = Rohbau()
    # --- Sitz: fuenf Latten quer, 6 mm Fuge, zur Mitte hin 6 mm tiefer.
    n_l = 5
    fuge = 0.006
    breite = (SITZ_T - fuge * (n_l - 1)) / n_l
    for i in range(n_l):
        y0 = -SITZ_T / 2.0 + i * (breite + fuge)
        # Die Mulde: aussen hoch, innen tief. Ein ebener Sitz ist
        # unbequem und sieht auch so aus.
        mitte = abs((i - (n_l - 1) / 2.0) / ((n_l - 1) / 2.0))
        senke = (1.0 - mitte * mitte) * 0.008
        holz.kasten(-SITZ_B / 2.0, y0, SITZ_H - 0.020 - senke,
                    SITZ_B, breite, 0.020)

    # --- Lehne: vier senkrechte Latten zwischen den Pfosten, geneigt.
    tan = math.tan(LEHNE_NEIG)
    n_r = 4
    r_fuge = 0.016
    r_breite = (SITZ_B - 0.09 - r_fuge * (n_r - 1)) / n_r
    for i in range(n_r):
        x0 = -(SITZ_B - 0.09) / 2.0 + i * (r_breite + r_fuge)
        for s in range(6):
            t0 = s / 6.0
            t1 = (s + 1) / 6.0
            z0 = SITZ_H + 0.085 + t0 * (LEHNE_H - 0.085)
            z1 = SITZ_H + 0.085 + t1 * (LEHNE_H - 0.085)
            yv = SITZ_T / 2.0 - 0.030 + (z0 - SITZ_H) * tan
            holz.kasten(x0, yv, z0, r_breite, 0.014, z1 - z0)
    # Abschlussriegel oben
    holz.kasten(-(SITZ_B - 0.09) / 2.0 - 0.005,
                SITZ_T / 2.0 - 0.030 + LEHNE_H * tan,
                SITZ_H + LEHNE_H, SITZ_B - 0.08, 0.026, 0.030)
    ob_holz = holz.fertig(name + '_holz', sammlung, m_holz, fase=0.003)

    metall = Rohbau()
    # --- Vorderbeine, verjuengt
    for sx in (-1, 1):
        metall.bein(sx * (SITZ_B / 2.0 - 0.028), -SITZ_T / 2.0 + 0.030,
                    0.0, SITZ_H - 0.020, 0.030, 0.021,
                    neig_x=-sx * 0.010, neig_y=0.006)
    # --- Hinterbeine: EIN Holz, das oben zum Lehnenpfosten wird.
    for sx in (-1, 1):
        px = sx * (SITZ_B / 2.0 - 0.028)
        metall.bein(px, SITZ_T / 2.0 - 0.030, 0.0, SITZ_H - 0.020,
                    0.030, 0.021, neig_x=-sx * 0.008, neig_y=-0.010)
        # Pfosten darueber, mit der Lehne geneigt
        for s in range(5):
            t0, t1 = s / 5.0, (s + 1) / 5.0
            z0 = SITZ_H - 0.020 + t0 * (LEHNE_H + 0.050)
            z1 = SITZ_H - 0.020 + t1 * (LEHNE_H + 0.050)
            yv = SITZ_T / 2.0 - 0.030 + (z0 - SITZ_H) * tan
            metall.kasten(px - 0.015, yv - 0.014, z0, 0.030, 0.028, z1 - z0)
    # --- Zargen unter dem Sitz
    metall.kasten(-SITZ_B / 2.0 + 0.020, -SITZ_T / 2.0 + 0.022,
                  SITZ_H - 0.020 - 0.042, SITZ_B - 0.040, 0.022, 0.042)
    metall.kasten(-SITZ_B / 2.0 + 0.020, SITZ_T / 2.0 - 0.044,
                  SITZ_H - 0.020 - 0.042, SITZ_B - 0.040, 0.022, 0.042)
    for sx in (-1, 1):
        metall.kasten(sx * (SITZ_B / 2.0 - 0.042) - 0.011,
                      -SITZ_T / 2.0 + 0.044, SITZ_H - 0.020 - 0.042,
                      0.022, SITZ_T - 0.088, 0.042)
    # --- Fussgleiter
    for sx in (-1, 1):
        for sy in (-1, 1):
            metall.kasten(sx * (SITZ_B / 2.0 - 0.028) - 0.014,
                          sy * (SITZ_T / 2.0 - 0.030) - 0.014,
                          -0.002, 0.028, 0.028, 0.007)
    ob_metall = metall.fertig(name + '_gestell', sammlung, m_metall,
                              fase=0.0025)

    return [_setzen(ob_holz, x, y, z, dreh),
            _setzen(ob_metall, x, y, z, dreh)]


def gruppe(name, x, y, z, dreh, sammlung, m_holz, m_metall, rnd):
    """Tisch mit vier Stuehlen. Die Stuehle stehen NICHT symmetrisch.

    Vier exakt ausgerichtete Stuehle sind das sicherste Zeichen dafuer,
    dass an diesem Tisch nie jemand gesessen hat. Einer ist
    zurueckgeschoben, einer steht schraeg, einer dichter dran.
    """
    teile = esstisch(name, x, y, z, dreh, sammlung, m_holz, m_metall)
    plaetze = ((-0.60, -0.76, 0.0, 0.02), (0.61, -0.79, 0.0, 0.00),
               (-0.62, 0.77, math.pi, 0.05), (0.66, 0.88, math.pi * 0.83, 0.17))
    for i, (px, py, pd, zurueck) in enumerate(plaetze):
        ab = zurueck + rnd.uniform(0.0, 0.05)
        sx = x + px * math.cos(dreh) - (py + (ab if py > 0 else -ab)) * math.sin(dreh)
        sy = y + px * math.sin(dreh) + (py + (ab if py > 0 else -ab)) * math.cos(dreh)
        teile += stuhl(name + '_stuhl%d' % i, sx, sy, z,
                       dreh + pd + rnd.uniform(-0.10, 0.10),
                       sammlung, m_holz, m_metall)
    return teile
