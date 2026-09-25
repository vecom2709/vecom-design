# -*- coding: utf-8 -*-
"""
villa_pflanzen.py -- Der Garten, der zu diesem Haus gehoert.

WAS IM BILD VOM 20.09.2026 NOCH FEHLTE
Rasen, acht Zypressen, ein paar Kuebel. Das ist kein sizilianischer
Garten, das ist eine Rasenflaeche mit Saeulen darauf. Und es ist der
Punkt, an dem ein Bild kippt: Haus und Pool koennen noch so gut
gerechnet sein -- wenn drumherum nichts steht, was dort wirklich
waechst, bleibt es ein Modell in einer Landschaft statt ein Haus an
einem Ort.

WARUM AUSGERECHNET OLIVEN
Weil eine alte Olive das Gegenteil von allem ist, was ein Rechner von
selbst produziert: kein gerader Stamm, kein runder Querschnitt, keine
gleichmaessige Krone, keine Symmetrie. Ein Baum mit geriffeltem,
gewundenem Stamm und einer offenen, unregelmaessigen Krone traegt in
einem Bild mehr Glaubwuerdigkeit als jede Textur an der Fassade.

WIE DAS LAUB ENTSTEHT
Nicht als Millionen Blattgeometrien. Die Krone besteht aus wenigen
verdraengten Blattballen mit dem Material 'Olivenlaub'; auf deren
Oberflaeche streut dieselbe Maschinerie wie bei den Zypressen
(lauf_villa.laub_ausgeben, v08_laub.py) kleine Blattbuendel. Der
Unterschied zwischen einer glatten Kugel und einer Krone ist die
ausgefranste Silhouette, und genau die liefern die Buendel.
"""

import bmesh
import bpy
import math
import random

from mathutils import Matrix, Vector, noise


# ------------------------------------------------------------- Hilfen

def _objekt(name, bm, sammlung, mat=None, glatt=False):
    me = bpy.data.meshes.new(name)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    if glatt:
        for p in me.polygons:
            p.use_smooth = True
    return ob


def _ring(bm, mitte, achse, radius, seiten, riffel, phase, drall):
    """Ein Querschnitt quer zur Achse -- mit Riffelung, nicht als Kreis.

    Die Riffelung ist der ganze Punkt. Ein Olivenstamm hat im
    Querschnitt keinen Kreis, sondern tiefe, senkrecht durchlaufende
    Rippen; deshalb haengt die Radiusaenderung nur am WINKEL und am
    Drall, nicht an der Hoehe. Ein Rauschen, das auch in der Hoehe
    schwankt, ergibt Beulen -- das sieht aus wie ein Kartoffelsack.
    """
    achse = achse.normalized()
    hilf = Vector((0.0, 0.0, 1.0))
    if abs(achse.dot(hilf)) > 0.95:
        hilf = Vector((1.0, 0.0, 0.0))
    u = achse.cross(hilf).normalized()
    v = achse.cross(u).normalized()
    punkte = []
    for i in range(seiten):
        a = 2.0 * math.pi * i / seiten
        aw = a + drall
        r = radius * (1.0
                      + riffel * math.sin(5.0 * aw + phase)
                      + riffel * 0.55 * math.sin(8.0 * aw + phase * 1.7)
                      + riffel * 0.30 * math.sin(13.0 * aw - phase * 0.6))
        p = mitte + u * (math.cos(a) * r) + v * (math.sin(a) * r)
        punkte.append(bm.verts.new(p))
    return punkte


def _zug(bm, punkte, radien, seiten=14, riffel=0.10, phase=0.0, drall=0.0):
    """Ein Rohr entlang eines Streckenzugs, mit Deckel an beiden Enden."""
    ringe = []
    for i, p in enumerate(punkte):
        if i == 0:
            achse = punkte[1] - punkte[0]
        elif i == len(punkte) - 1:
            achse = punkte[-1] - punkte[-2]
        else:
            achse = punkte[i + 1] - punkte[i - 1]
        ringe.append(_ring(bm, p, achse, radien[i], seiten, riffel,
                           phase, drall * i))
    bm.verts.ensure_lookup_table()
    for i in range(len(ringe) - 1):
        a, b = ringe[i], ringe[i + 1]
        for j in range(seiten):
            k = (j + 1) % seiten
            bm.faces.new((a[j], a[k], b[k], b[j]))
    bm.faces.new(list(reversed(ringe[0])))
    bm.faces.new(ringe[-1])
    return ringe


def _ballen(bm, mitte, halb, samen, unruhe=0.26, unterteilung=2):
    """Ein verdraengter Blattballen.

    Eine glatte Kugel als Krone sieht aus wie ein Lutscher. Die
    Verdraengung arbeitet in DREI Groessenordnungen: grobe Lappen (das
    sind die Aeste, die die Krone in Ballen teilen), mittlere Buchten
    und feines Korn. Erst die grobe Stufe macht aus der Kugel eine
    Form, die etwas gewachsen sein koennte.
    """
    rng = random.Random(samen)
    v0 = rng.random() * 100.0
    ober = bmesh.ops.create_icosphere(
        bm, subdivisions=unterteilung, radius=1.0)['verts']
    for v in ober:
        n = v.co.copy()
        n.normalize()
        grob = noise.noise(Vector((n.x * 1.15 + v0, n.y * 1.15,
                                   n.z * 1.15))) * unruhe
        mittel = noise.noise(Vector((n.x * 3.30, n.y * 3.30 + v0,
                                     n.z * 3.30))) * unruhe * 0.45
        fein = noise.noise(Vector((n.x * 8.50, n.y * 8.50,
                                   n.z * 8.50 + v0))) * unruhe * 0.16
        f = 1.0 + grob + mittel + fein
        v.co = Vector((n.x * halb[0] * f, n.y * halb[1] * f,
                       n.z * halb[2] * f)) + mitte
    return ober


def olivenbaum(name, x, y, z0, sammlung, m_rinde, m_laub,
               hoehe=5.4, samen=1):
    """Eine alte Olive: geriffelter, gewundener Stamm, drei Hauptaeste,
    offene Krone aus mehreren Ballen.

    Die Masse sind nicht erfunden: Ein 60 bis 100 Jahre alter Baum ist
    4,5 bis 6 m hoch, hat am Fuss 35 bis 50 cm Durchmesser und teilt
    sich bei 1,2 bis 1,8 m in drei bis vier Starkaeste. Die Krone ist
    etwa so breit wie der Baum hoch und ausdruecklich NICHT geschlossen
    -- man sieht durch eine Olive hindurch.
    """
    rng = random.Random(samen * 977 + 13)
    gabel = hoehe * (0.26 + rng.random() * 0.07)
    r_fuss = hoehe * (0.042 + rng.random() * 0.012)
    phase = rng.random() * 6.28
    drall = (rng.random() - 0.5) * 0.12

    # --- Stamm
    bm = bmesh.new()
    n_st = 9
    punkte, radien = [], []
    schief = Vector(((rng.random() - 0.5) * 0.30,
                     (rng.random() - 0.5) * 0.30, 0.0))
    for i in range(n_st):
        t = i / float(n_st - 1)
        # Der Fuss laeuft breit aus (Wurzelanlauf), oben wird es duenn.
        r = r_fuss * (0.42 + 0.58 * (1.0 - t) ** 0.55
                      + 0.55 * math.exp(-t * 9.0))
        w = Vector((noise.noise(Vector((t * 2.4 + samen, 0.0, 0.0))),
                    noise.noise(Vector((0.0, t * 2.4 + samen, 0.0))),
                    0.0)) * (hoehe * 0.055)
        punkte.append(Vector((x, y, z0 + gabel * t)) + schief * t + w)
        radien.append(r)
    _zug(bm, punkte, radien, seiten=16, riffel=0.135, phase=phase,
         drall=drall)

    # --- Starkaeste, jeder mit einer Verzweigung
    kronen = []
    n_ast = 3 + (samen % 2)
    for a in range(n_ast):
        winkel = 2.0 * math.pi * a / n_ast + rng.random() * 0.5
        neig = 0.55 + rng.random() * 0.35        # von der Senkrechten weg
        lang = hoehe * (0.40 + rng.random() * 0.16)
        fuss = punkte[-1]
        r0 = radien[-1] * (0.62 - a * 0.05)
        ap, ar = [], []
        n_a = 6
        for i in range(n_a):
            t = i / float(n_a - 1)
            bogen = math.sin(t * 1.35) * neig
            ap.append(fuss + Vector((math.cos(winkel) * lang * bogen,
                                     math.sin(winkel) * lang * bogen,
                                     lang * t * (1.0 - 0.30 * t)))
                      + Vector((noise.noise(Vector((t * 3.1 + a, samen, 0.0))),
                                noise.noise(Vector((samen, t * 3.1 + a, 0.0))),
                                0.0)) * (lang * 0.10))
            ar.append(r0 * (1.0 - 0.72 * t))
        _zug(bm, ap, ar, seiten=10, riffel=0.09, phase=phase + a,
             drall=drall * 0.5)

        # Zwei Zweige aus der Mitte des Starkastes
        for z in range(2):
            ab = ap[3]
            w2 = winkel + (z - 0.5) * 1.5 + rng.random() * 0.4
            l2 = lang * (0.45 + rng.random() * 0.25)
            zp, zr = [], []
            for i in range(4):
                t = i / 3.0
                zp.append(ab + Vector((math.cos(w2) * l2 * t * 0.85,
                                       math.sin(w2) * l2 * t * 0.85,
                                       l2 * t * 0.75)))
                zr.append(ar[3] * (0.75 - 0.60 * t))
            _zug(bm, zp, zr, seiten=8, riffel=0.07, phase=phase + z)
            kronen.append(zp[-1])
        kronen.append(ap[-1])

    _objekt(name + '_stamm', bm, sammlung, m_rinde, glatt=False)

    # --- Krone: viele KLEINE Bueschel, nicht wenige grosse
    #
    # GEMESSEN AM BILD VOM 20.09.2026, 01:49 UHR
    # Erste Fassung: ein Ballen je Astende mit bis zu 1,47 m Halbachse,
    # also fast 3 m breit -- bei elf Ballen wurde daraus EINE Masse von
    # sieben Metern Durchmesser auf einem 5,6-m-Baum. Im Bild sah das
    # aus wie Brokkoli, und es verdeckte das halbe Haus.
    # Eine alte Olive ist etwa so breit wie hoch, und sie ist OFFEN:
    # Man sieht durch sie hindurch. Die Krone besteht aus vielen
    # Buescheln von 0,7 bis 1,1 m mit Luecken dazwischen -- und genau
    # diese Luecken sind das, woran man sie erkennt.
    bl = bmesh.new()
    kr = hoehe * 0.40                     # Kronenradius: 2,2 m bei 5,5 m
    for i, p in enumerate(kronen):
        # Zwei bis drei Bueschel je Astende, versetzt statt konzentrisch
        for j in range(2 + (i + samen) % 2):
            s = 0.62 + rng.random() * 0.50
            ab = Vector(((rng.random() - 0.5) * kr * 0.52,
                         (rng.random() - 0.5) * kr * 0.52,
                         (rng.random() - 0.22) * kr * 0.42))
            _ballen(bl, p + ab,
                    (kr * 0.24 * s, kr * 0.23 * s, kr * 0.19 * s),
                    samen * 131 + i * 7 + j, unruhe=0.40)
    _objekt(name + '_laub', bl, sammlung, m_laub, glatt=True)
    return kronen


def polster_strauch(name, x, y, z0, breite, hoehe, sammlung, mat, samen=1):
    """Ein Polsterstrauch -- Rosmarin, Lavendel, Santolina.

    Diese Pflanzen wachsen als Halbkugel mit ausgefransten Raendern. Im
    Bild tun sie etwas, das kein Material kann: Sie brechen die
    kilometerlange gerade Kante zwischen Terrasse und Rasen. Eine
    scharfe Linie zwischen zwei Flaechen ist eines der sichersten
    Merkmale eines Rechnerbildes -- in der Wirklichkeit waechst dort
    immer etwas darueber.
    """
    bm = bmesh.new()
    verts = _ballen(bm, Vector((x, y, z0)),
                    (breite * 0.5, breite * 0.5, hoehe),
                    samen, unruhe=0.34, unterteilung=2)
    # Untere Haelfte auf Bodenhoehe kappen: Ein Polster ist eine
    # Halbkugel, keine Kugel, die halb im Boden steckt.
    for v in verts:
        if v.co.z < z0:
            v.co.z = z0 + (v.co.z - z0) * 0.08
    _objekt(name, bm, sammlung, mat, glatt=True)


def agave(name, x, y, z0, sammlung, mat, radius=0.85, samen=1):
    """Eine Agave: Rosette aus dicken, spitzen Blaettern.

    Sie steht hier nicht als Zierde, sondern weil sie eine Silhouette
    hat, die nichts anderes im Bild hat: harte, gerade Spitzen. Genau
    solche Gegensaetze machen eine Bepflanzung lesbar.
    """
    rng = random.Random(samen * 613 + 7)
    bm = bmesh.new()
    n = 15
    for i in range(n):
        a = 2.0 * math.pi * i / n + rng.random() * 0.12
        neig = 0.35 + (i % 3) * 0.20 + rng.random() * 0.18
        lang = radius * (0.80 + rng.random() * 0.40)
        br = radius * 0.19
        dick = radius * 0.075
        spitze = Vector((x + math.cos(a) * lang * neig,
                         y + math.sin(a) * lang * neig,
                         z0 + lang * (1.0 - neig * 0.75)))
        fuss = Vector((x + math.cos(a) * radius * 0.10,
                       y + math.sin(a) * radius * 0.10, z0))
        quer = Vector((-math.sin(a), math.cos(a), 0.0))
        mitte = (fuss + spitze) * 0.5 + Vector((0.0, 0.0, -lang * 0.10))
        vs = [bm.verts.new(fuss + quer * br * 0.45),
              bm.verts.new(fuss - quer * br * 0.45),
              bm.verts.new(mitte + quer * br + Vector((0, 0, dick))),
              bm.verts.new(mitte - quer * br + Vector((0, 0, dick))),
              bm.verts.new(mitte + quer * br * 0.9 - Vector((0, 0, dick))),
              bm.verts.new(mitte - quer * br * 0.9 - Vector((0, 0, dick))),
              bm.verts.new(spitze)]
        bm.faces.new((vs[0], vs[1], vs[3], vs[2]))
        bm.faces.new((vs[0], vs[4], vs[5], vs[1]))
        bm.faces.new((vs[2], vs[3], vs[6]))
        bm.faces.new((vs[5], vs[4], vs[6]))
        bm.faces.new((vs[2], vs[6], vs[4], vs[0]))
        bm.faces.new((vs[3], vs[1], vs[5], vs[6]))
    _objekt(name, bm, sammlung, mat, glatt=False)


# Wo was steht. Absichtlich als Liste und nicht im Code verstreut:
# Eine Bepflanzung aendert man beim Ansehen des Bildes, nicht beim
# Lesen einer Funktion.
OLIVEN = (
    # x, y, Hoehe, Samen
    # Die Standorte sind nach dem Blick 'garten' gesetzt (Kamera bei
    # 30 / 30,5, Blick auf 8,5 / 8): Kein Baum steht auf der Sichtachse,
    # sonst verdeckt er genau das Haus, fuer das das Bild gemacht wird.
    (25.20, 3.60, 5.4, 1),      # rechts vom Haus, ausserhalb der Achse
    (24.60, 21.20, 4.8, 2),     # weit hinter dem Pool, links im Bild
    (-6.40, 12.80, 5.2, 3),     # westlich, hinter der Mauer
    (24.80, -5.40, 4.5, 4),     # an der Zufahrt
    (-4.60, -7.20, 5.0, 5),
    (33.50, 12.00, 5.6, 6),     # vorn rechts, gibt dem Bild Tiefe
)

POLSTER = (
    # x, y, Breite, Hoehe, Samen -- entlang Terrassen- und Poolkante
    (4.60, 15.30, 1.05, 0.42, 11), (4.55, 17.40, 0.85, 0.36, 12),
    (4.70, 19.10, 1.15, 0.46, 13), (17.30, 15.20, 0.95, 0.40, 14),
    (17.45, 17.60, 1.10, 0.44, 15), (17.20, 19.30, 0.80, 0.34, 16),
    (-1.40, 12.60, 1.30, 0.52, 17), (-1.30, 9.20, 1.05, 0.42, 18),
    (-1.55, 5.80, 1.20, 0.48, 19), (19.60, 11.40, 1.25, 0.50, 20),
    (19.40, 8.20, 0.95, 0.40, 21), (19.75, 4.90, 1.15, 0.46, 22),
    (10.40, -4.30, 1.10, 0.44, 23), (13.80, -4.10, 0.90, 0.38, 24),
)

AGAVEN = ((20.90, 2.20, 0.90, 31), (-2.90, 16.40, 0.78, 32),
          (22.40, 13.10, 0.85, 33))


# NOCH NICHT IM BILD -- und zwar aus einem gemessenen Grund.
#
# Zwei Fassungen, zwei Cycles-Proben (20.09.2026, 01:49 und 01:52 Uhr):
#   Fassung 1: Kronen mit 1,47 m Halbachse. Aus elf Ballen wurde EINE
#              Masse von sieben Metern auf einem 5,6-m-Baum. Brokkoli,
#              und sie verdeckte das halbe Haus.
#   Fassung 2: Ballen auf ein Drittel verkleinert, mehr davon, versetzt.
#              Besser proportioniert -- und immer noch Knoedel an
#              Stoecken. Die Aeste stehen nackt zwischen den Ballen, was
#              eine Olive in der aeusseren Krone nie tut.
#
# Warum das so nicht reicht: Eine Baumkrone aus glatten Koerpern LEBT
# von den Blattbuendeln darauf -- so, wie die Zypressen erst mit den
# 80.000 Nadelbuendeln aufgehoert haben, Kegel zu sein. Die Cycles-Probe
# zeigt die Ballen ohne diese Buendel, beurteilt also einen halben
# Zustand. Trotzdem ist die Anordnung darunter nachweislich falsch, und
# eine halbfertige Bepflanzung macht das Bild schlechter, nicht besser.
#
# Deshalb steht der Schalter auf False, bis die Krone gemessen besser
# ist als der Zustand ohne sie. Der Code bleibt -- weggeworfen wird
# nichts, was schon halb steht.
PFLANZEN_AN = False


def bepflanzen(sammlung, m, boden=-0.38):
    """Alles zusammen. Wird aus villa.haupt gerufen.

    boden: Hoehe der Rasenoberkante. Die Pflanzen stehen absichtlich
    2 cm darunter -- das Gelaende aus villa_garten.py schwankt um bis
    zu 4 cm, und ein Strauch, der schwebt, faellt sofort auf, waehrend
    einer, der zwei Zentimeter zu tief sitzt, niemandem auffaellt.
    """
    if not PFLANZEN_AN:
        print('[pflanzen] AUS -- siehe Begruendung ueber PFLANZEN_AN.')
        return {'oliven': 0, 'polster': 0, 'agaven': 0, 'aus': True}
    z = boden - 0.02
    bericht = {'oliven': 0, 'polster': 0, 'agaven': 0}
    for i, (x, y, h, s) in enumerate(OLIVEN):
        olivenbaum('olive%d' % i, x, y, z, sammlung,
                   m['olivenrinde'], m['olivenlaub'], hoehe=h, samen=s)
        bericht['oliven'] += 1
    for i, (x, y, b, h, s) in enumerate(POLSTER):
        polster_strauch('polster%d' % i, x, y, z, b, h, sammlung,
                        m['pflanze'], samen=s)
        bericht['polster'] += 1
    for i, (x, y, r, s) in enumerate(AGAVEN):
        agave('agave%d' % i, x, y, z, sammlung, m['agave'],
              radius=r, samen=s)
        bericht['agaven'] += 1
    print('[pflanzen] %d Oliven, %d Polster, %d Agaven'
          % (bericht['oliven'], bericht['polster'], bericht['agaven']))
    return bericht
