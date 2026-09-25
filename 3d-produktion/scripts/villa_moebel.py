# -*- coding: utf-8 -*-
"""
villa_moebel.py — Die Innenraeume.

WARUM MOEBEL KEIN SCHMUCK SIND
Ein leerer Raum hat keinen Massstab. Wer durch eine unmoeblierte Villa
laeuft, sieht weisse Flaechen und kann nicht sagen, ob der Raum 4 oder
14 Meter lang ist. Erst ein Sofa von 2,60 m und ein Stuhl mit 45 cm
Sitzhoehe geben dem Auge etwas, woran es messen kann -- und erst dann
wird aus einem Modell ein Haus, durch das man gehen will.

WIE SIE GEBAUT SIND
Aus Quadern und Zylindern, mit echten Massen, ohne ein einziges gekauftes
Modell. Ein Designersofa als 400-KB-Netz waere schoener und wuerde das
Datenbudget der Seite in einem Raum verbrauchen. Die Wirkung entsteht hier
aus Proportion, Material und Licht -- nicht aus Dreiecken.

Alle Masse in Metern, Nullpunkt und Achsen wie in villa.py.
"""

import bpy
import bmesh
import math


def _mesh(name, sammlung, mat):
    me = bpy.data.meshes.new(name)
    ob = bpy.data.objects.new(name, me)
    sammlung.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob, me


def kasten(name, x0, y0, z0, dx, dy, dz, sammlung, mat=None):
    """Quader in echter Groesse, Ursprung unten mittig. Gleiche Regel wie in
    villa.py: die Groesse steckt im Netz, damit Fasen gleichmaessig werden."""
    ob, me = _mesh(name, sammlung, mat)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= dx
        v.co.y *= dy
        v.co.z = v.co.z * dz + dz / 2.0
    bm.to_mesh(me)
    bm.free()
    ob.location = (x0 + dx / 2.0, y0 + dy / 2.0, z0)
    return ob


def polster(name, x0, y0, z0, dx, dy, dz, sammlung, mat=None,
            rundung=0.06, unruhe=0.014, samen=1, fuss=True):
    """Ein WEICHES Teil -- Sitzkissen, Rueckenkissen, Bettdecke, Matratze.

    WARUM ES DAS BRAUCHT
    Der 100-Prozent-Ausschnitt vom 19.09.2026 zeigte das Sofa als Stapel
    Quader mit 6 mm Fase. Ein Polster hat aber keine Kante, die man fasen
    koennte: Der Bezug legt sich mit mehreren Zentimetern Radius um den
    Schaum, und die Flaeche dazwischen ist nie eben. Genau diese zwei
    Dinge liest das Auge als "weich" -- alles andere ist Nebensache.

    WARUM KEINE STOFFSIMULATION
    Sie braucht Kollisionskoerper, Rechenzeit und einen Endzustand, den
    ein Kopflos-Lauf nicht reproduzierbar haelt. Grosse Rundung plus
    ruhiges Rauschen auf der Oberflaeche liefert denselben Eindruck und
    kommt bei jedem Lauf gleich heraus.

    fuss=True haelt die Unterseite flach -- ein Kissen, das auf einer
    Platte liegt, woelbt sich dort nicht.
    """
    from mathutils import Vector
    from mathutils import noise as bnoise
    ob, me = _mesh(name, sammlung, mat)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= dx
        v.co.y *= dy
        v.co.z = v.co.z * dz + dz / 2.0
    r = min(rundung, dx * 0.40, dy * 0.40, dz * 0.44)
    bmesh.ops.bevel(bm, geom=list(bm.verts) + list(bm.edges) + list(bm.faces),
                    offset=r, segments=4, profile=0.62, affect='EDGES')
    bmesh.ops.subdivide_edges(bm, edges=bm.edges[:], cuts=2,
                              use_grid_fill=True)
    bm.normal_update()
    for v in bm.verts:
        # Unten flach lassen, wo das Polster aufliegt.
        anteil = 1.0
        if fuss and v.co.z < dz * 0.16:
            anteil = 0.18
        d = bnoise.noise(Vector((v.co.x * 3.3 + samen * 7.0,
                                 v.co.y * 3.3 + samen * 3.0,
                                 v.co.z * 3.3)))
        v.co += v.normal * (d * unruhe * anteil)
    bm.normal_update()
    bm.to_mesh(me)
    bm.free()
    for p in me.polygons:
        p.use_smooth = True
    ob.location = (x0 + dx / 2.0, y0 + dy / 2.0, z0)
    return ob


def zylinder(name, x, y, z0, radius, hoehe, sammlung, mat=None, seiten=16):
    ob, me = _mesh(name, sammlung, mat)
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=seiten,
                          radius1=radius, radius2=radius, depth=hoehe)
    bmesh.ops.translate(bm, verts=bm.verts, vec=(0.0, 0.0, hoehe / 2.0))
    bm.to_mesh(me)
    bm.free()
    ob.location = (x, y, z0)
    return ob


def kugel(name, x, y, z, radius, sammlung, mat=None, segmente=12):
    ob, me = _mesh(name, sammlung, mat)
    bm = bmesh.new()
    bmesh.ops.create_uvsphere(bm, u_segments=segmente * 2,
                              v_segments=segmente, radius=radius)
    bm.to_mesh(me)
    bm.free()
    ob.location = (x, y, z)
    return ob


# ------------------------------------------------------------- Bausteine

def stuhl(name, x, y, g, mat_sitz, mat_bein, dreh=0.0):
    """Ein moderner Freischwinger-artiger Stuhl: Sitz 45 cm, Lehne bis 82 cm,
    vier schlanke Beine. Sechs Teile -- mehr braucht das Auge auf drei Meter
    Abstand nicht, und weniger sieht nach Baukasten aus."""
    teile = []
    sb, st, sh = 0.46, 0.46, 0.045      # Sitz
    teile.append(kasten(name + '_sitz', -sb / 2, -st / 2, 0.435, sb, st, sh,
                        g, mat_sitz))
    teile.append(kasten(name + '_lehne', -sb / 2, st / 2 - 0.05, 0.46,
                        sb, 0.045, 0.40, g, mat_sitz))
    for i, (bx, by) in enumerate(((-1, -1), (1, -1), (-1, 1), (1, 1))):
        teile.append(kasten(name + '_bein%d' % i,
                            bx * (sb / 2 - 0.045) - 0.014,
                            by * (st / 2 - 0.045) - 0.014,
                            0.0, 0.028, 0.028, 0.435, g, mat_bein))
    for t in teile:
        t.location.x += x
        t.location.y += y
        if dreh:
            t.rotation_euler = (0.0, 0.0, dreh)
    # Um den Stuhlmittelpunkt drehen, nicht um den Weltnullpunkt
    if dreh:
        s, c = math.sin(dreh), math.cos(dreh)
        for t in teile:
            dx = t.location.x - x
            dy = t.location.y - y
            t.location.x = x + dx * c - dy * s
            t.location.y = y + dx * s + dy * c
    return teile


def leuchte_haenge(name, x, y, z_decke, g, mat_schirm, mat_kabel,
                   laenge=1.05, radius=0.15):
    zylinder(name + '_kabel', x, y, z_decke - laenge, 0.006, laenge, g, mat_kabel, 8)
    ob = zylinder(name + '_schirm', x, y, z_decke - laenge - 0.16,
                  radius, 0.16, g, mat_schirm, 20)
    return ob


def pflanze(name, x, y, z, g, mat_topf, mat_blatt, hoehe=1.35):
    zylinder(name + '_topf', x, y, z, 0.24, 0.40, g, mat_topf, 18)
    stamm = zylinder(name + '_stamm', x, y, z + 0.38, 0.035, hoehe * 0.55, g, mat_blatt, 8)
    for i in range(7):
        a = i * math.pi * 2 / 7 + 0.4
        r = 0.30 + (i % 3) * 0.08
        b = kasten(name + '_blatt%d' % i, -r / 2, -0.055, 0.0, r, 0.11, 0.012,
                   g, mat_blatt)
        b.location = (x + math.cos(a) * r * 0.45,
                      y + math.sin(a) * r * 0.45,
                      z + 0.38 + hoehe * (0.42 + (i % 4) * 0.13))
        b.rotation_euler = (0.0, math.radians(-18 - (i % 3) * 12), a)
    return stamm


def teppich(name, x0, y0, z, dx, dy, g, mat):
    return kasten(name, x0, y0, z + 0.004, dx, dy, 0.012, g, mat)


# ------------------------------------------------------------ Die Raeume

def einrichten(g, m):
    """Alle Raeume moeblieren. g ist die Collection 07_Innenausbau, m das
    Materialverzeichnis aus villa.py.

    Die Stuecke sind bewusst grosszuegig bemessen -- 2,60-m-Sofa, 2,80-m-Tisch,
    Kingsize-Bett. In einem Haus dieser Preisklasse steht keine
    Zweisitzer-Garnitur, und ein zu kleines Moebel laesst den Raum nicht
    grosszuegig wirken, sondern billig."""

    raus = []

    # =============================================== WOHNEN  x 9.7..17.6, y 4.0..10.6
    # Sitzgruppe ueber Eck, zur Glasfront nach Sueden ausgerichtet
    # Der Korpus bleibt ein Quader -- er IST einer, ein Holzrahmen mit
    # Bezug. Alles, was Schaum enthaelt, ist Polster: grosse Rundung,
    # unruhige Oberflaeche, leicht unterschiedliche Hoehen. Und die
    # Sitzkissen liegen nicht bundig aneinander, sondern mit einem
    # Fingerbreit Fuge und ein paar Millimetern Versatz -- so wie sie
    # liegen, wenn jemand aufgestanden ist.
    kasten('sofa_lang', 11.60, 8.70, 0.0, 3.20, 0.98, 0.40, g, m['stoff'])
    polster('sofa_lang_lehne', 11.62, 9.46, 0.39, 3.16, 0.22, 0.32, g,
            m['stoff'], rundung=0.075, unruhe=0.020, samen=1)
    polster('sofa_lang_kissen_l', 11.67, 8.77, 0.395, 1.50, 0.84, 0.17, g,
            m['stoff'], rundung=0.065, unruhe=0.016, samen=2)
    polster('sofa_lang_kissen_r', 13.26, 8.775, 0.393, 1.43, 0.83, 0.175, g,
            m['stoff'], rundung=0.065, unruhe=0.016, samen=3)
    kasten('sofa_arm_l', 11.44, 8.70, 0.0, 0.16, 0.98, 0.62, g, m['stoff'])
    kasten('sofa_kurz', 14.90, 6.60, 0.0, 0.98, 2.30, 0.40, g, m['stoff'])
    polster('sofa_kurz_lehne', 15.66, 6.62, 0.39, 0.22, 2.26, 0.32, g,
            m['stoff'], rundung=0.075, unruhe=0.020, samen=4)
    polster('sofa_kurz_kissen', 14.97, 6.67, 0.394, 0.84, 2.12, 0.17, g,
            m['stoff'], rundung=0.065, unruhe=0.016, samen=5)
    # Zierkissen -- unsymmetrisch, eines angelehnt. Ein Sofa ohne sie
    # sieht aus wie im Katalog, nicht wie bewohnt.
    # Der Testrender vom 20.09.2026 zeigte die Zierkissen noch als
    # Scheiben: 8 cm Rundung auf 17 cm Dicke ist zu wenig, und 0,022
    # Unruhe verschwindet im Bild. Ein Kissen mit Federfuellung ist an den
    # Ecken praktisch rund und hat eine deutlich bewegte Flaeche.
    zk = polster('sofa_zier1', 11.85, 9.16, 0.545, 0.46, 0.18, 0.44, g,
                 m['akzent'], rundung=0.115, unruhe=0.042, samen=6, fuss=False)
    zk.rotation_euler = (math.radians(-13.0), 0.0, math.radians(6.0))
    zk2 = polster('sofa_zier2', 12.38, 9.19, 0.55, 0.42, 0.17, 0.42, g,
                  m['stoff'], rundung=0.110, unruhe=0.040, samen=7, fuss=False)
    zk2.rotation_euler = (math.radians(-9.0), 0.0, math.radians(-11.0))
    zk3 = polster('sofa_zier3', 15.30, 7.40, 0.55, 0.17, 0.44, 0.42, g,
                  m['akzent'], rundung=0.110, unruhe=0.040, samen=8, fuss=False)
    zk3.rotation_euler = (0.0, math.radians(11.0), math.radians(4.0))
    # Eine Decke, ueber die Armlehne geworfen. Duenn, stark gerundet,
    # kraeftig unruhig -- das ist der Unterschied zu einem Brett.
    dk = polster('sofa_decke', 11.42, 8.86, 0.585, 0.30, 0.62, 0.055, g,
                 m['leinen'] if 'leinen' in m else m['stoff'],
                 rundung=0.026, unruhe=0.030, samen=9, fuss=False)
    dk.rotation_euler = (0.0, math.radians(-7.0), 0.0)

    teppich('teppich_wohnen', 11.20, 6.10, 0.02, 4.40, 3.10, g, m['teppich'])
    kasten('couchtisch', 12.60, 7.10, 0.0, 1.30, 0.70, 0.32, g, m['eiche'])
    kasten('couchtisch_platte', 12.52, 7.02, 0.32, 1.46, 0.86, 0.035, g, m['stein'])

    # Sideboard mit Fernseher an der Innenwand
    kasten('sideboard', 9.90, 5.10, 0.0, 0.46, 2.60, 0.52, g, m['eiche'])
    kasten('tv', 9.94, 5.60, 0.62, 0.05, 1.60, 0.92, g, m['alu'])
    kasten('tv_fuss', 10.00, 6.20, 0.52, 0.22, 0.40, 0.10, g, m['alu'])

    # Sessel und Stehleuchte an der Ostfront
    kasten('sessel', 15.90, 4.60, 0.0, 0.92, 0.88, 0.38, g, m['stoff'])
    kasten('sessel_lehne', 15.90, 5.30, 0.38, 0.92, 0.18, 0.44, g, m['stoff'])
    zylinder('stehleuchte_fuss', 16.80, 4.30, 0.0, 0.16, 0.025, g, m['alu'], 16)
    zylinder('stehleuchte_rohr', 16.80, 4.30, 0.02, 0.016, 1.68, g, m['alu'], 10)
    zylinder('stehleuchte_schirm', 16.80, 4.30, 1.70, 0.17, 0.22, g, m['leuchte'], 20)

    pflanze('pflanze_wohnen', 16.90, 9.90, 0.02, g, m['stein'], m['pflanze'], 1.55)

    # =============================================== ESSEN  x 5.35..17.6, y 0.4..3.85
    kasten('esstisch_platte', 8.40, 1.40, 0.735, 2.80, 1.05, 0.045, g, m['eiche'])
    for i, (bx, by) in enumerate(((8.55, 1.52), (11.05, 1.52),
                                  (8.55, 2.33), (11.05, 2.33))):
        kasten('esstisch_bein%d' % i, bx, by, 0.0, 0.07, 0.07, 0.735, g, m['alu'])
    for i in range(3):
        stuhl('esstuhl_n%d' % i, 8.95 + i * 0.80, 1.02, g, m['stoff'], m['alu'], math.pi)
        stuhl('esstuhl_s%d' % i, 8.95 + i * 0.80, 2.83, g, m['stoff'], m['alu'], 0.0)
    stuhl('esstuhl_w', 8.14, 1.92, g, m['stoff'], m['alu'], math.pi / 2)
    stuhl('esstuhl_o', 11.46, 1.92, g, m['stoff'], m['alu'], -math.pi / 2)
    for i in range(3):
        leuchte_haenge('pendel_essen%d' % i, 9.10 + i * 0.85, 1.92, 3.18,
                       g, m['leuchte'], m['alu'], 0.95, 0.13)

    # Konsole und Bild an der Nordwand
    kasten('konsole_essen', 13.60, 0.62, 0.0, 1.80, 0.38, 0.78, g, m['eiche'])
    kasten('bild_essen', 13.90, 0.58, 1.15, 1.30, 0.04, 0.90, g, m['kunst'])
    pflanze('pflanze_essen', 16.90, 1.20, 0.02, g, m['stein'], m['pflanze'], 1.20)

    # =============================================== KUECHE  x 5.35..9.55, y 4.0..10.6
    kasten('kueche_zeile', 5.50, 9.72, 0.0, 3.90, 0.66, 0.90, g, m['alu'])
    kasten('kueche_zeile_platte', 5.46, 9.66, 0.90, 3.98, 0.78, 0.04, g, m['stein'])
    kasten('kueche_ruecken', 5.50, 10.40, 0.94, 3.90, 0.02, 0.56, g, m['stein'])
    kasten('kueche_hoch', 5.50, 4.12, 0.0, 0.64, 2.60, 2.40, g, m['eiche'])
    kasten('kueche_backofen', 5.46, 4.80, 0.90, 0.04, 0.60, 0.60, g, m['alu'])
    kasten('kueche_insel', 6.70, 6.70, 0.0, 2.60, 1.05, 0.90, g, m['eiche'])
    kasten('kueche_insel_platte', 6.62, 6.62, 0.90, 2.76, 1.21, 0.045, g, m['stein'])
    kasten('kueche_spuele', 7.35, 6.95, 0.855, 0.62, 0.42, 0.05, g, m['alu'])
    zylinder('kueche_armatur', 7.35, 7.28, 0.945, 0.016, 0.34, g, m['alu'], 10)
    # Barhocker an der Insel
    for i in range(3):
        x = 6.95 + i * 0.75
        zylinder('hocker%d_sitz' % i, x, 5.95, 0.64, 0.18, 0.05, g, m['stoff'], 18)
        zylinder('hocker%d_rohr' % i, x, 5.95, 0.0, 0.025, 0.64, g, m['alu'], 10)
        zylinder('hocker%d_fuss' % i, x, 5.95, 0.0, 0.18, 0.02, g, m['alu'], 18)
    for i in range(2):
        leuchte_haenge('pendel_kueche%d' % i, 6.95 + i * 1.10, 7.20, 3.18,
                       g, m['leuchte'], m['alu'], 1.15, 0.11)

    # =============================================== ENTREE  x 0.4..5.2, y 0.4..8.6
    kasten('garderobe', 0.52, 0.60, 0.0, 0.42, 2.20, 2.05, g, m['eiche'])
    kasten('bank_entree', 1.20, 0.60, 0.0, 1.30, 0.42, 0.44, g, m['eiche'])
    kasten('spiegel_entree', 0.46, 3.20, 0.90, 0.03, 1.10, 1.70, g, m['spiegel'])
    pflanze('pflanze_entree', 4.60, 1.10, 0.02, g, m['stein'], m['pflanze'], 1.70)

    # =============================================== GAESTE-WC  x 0.4..2.55, y 8.75..10.6
    kasten('wc_becken', 0.55, 9.10, 0.82, 0.62, 0.42, 0.14, g, m['keramik'])
    kasten('wc_unterbau', 0.55, 9.12, 0.0, 0.62, 0.38, 0.82, g, m['eiche'])
    zylinder('wc_armatur', 0.72, 9.44, 0.96, 0.014, 0.22, g, m['alu'], 10)
    kasten('wc_schuessel', 1.75, 10.15, 0.0, 0.38, 0.56, 0.42, g, m['keramik'])
    kasten('wc_spiegel', 0.44, 9.02, 1.05, 0.03, 0.60, 0.90, g, m['spiegel'])

    # =============================================== TECHNIK  x 2.7..5.2, y 8.75..10.6
    kasten('technik_regal', 4.70, 8.90, 0.0, 0.44, 1.60, 2.00, g, m['alu'])
    kasten('technik_kessel', 2.85, 10.05, 0.0, 0.62, 0.42, 1.55, g, m['alu'])

    raus.append('eg')
    return raus


def einrichten_og(g, m, Z):
    """Obergeschoss. Z ist die Fussbodenhoehe (3,60)."""

    # =============================================== MASTER  x 10.95..14.6, y 5.35..13.1
    # Das Bett steht mit dem Kopf zur Innenwand und blickt nach Sueden auf
    # die Glasfront ueber der Auskragung -- der Grund, warum dieses Zimmer
    # dort liegt.
    kasten('bett_podest', 11.60, 9.60, Z, 2.10, 2.20, 0.22, g, m['eiche'])
    polster('bett_matratze', 11.68, 9.68, Z + 0.22, 1.94, 2.04, 0.30, g,
            m['stoff'], rundung=0.045, unruhe=0.010, samen=11)
    polster('bett_kopf', 11.50, 11.80, Z, 2.30, 0.12, 1.05, g, m['stoff'],
            rundung=0.055, unruhe=0.014, samen=12, fuss=False)
    # Die Decke: duenn, stark gerundet, kraeftig unruhig -- und am Fussende
    # zurueckgeschlagen. Eine glatte Platte auf dem Bett ist das Erste,
    # woran man ein Rechnerbild erkennt.
    polster('bett_decke', 11.70, 9.62, Z + 0.495, 1.90, 1.62, 0.115, g,
            m['bettwaesche'], rundung=0.048, unruhe=0.034, samen=13,
            fuss=False)
    umschlag = polster('bett_umschlag', 11.70, 11.16, Z + 0.545, 1.90, 0.30,
                       0.055, g, m['bettwaesche'], rundung=0.026,
                       unruhe=0.022, samen=14, fuss=False)
    umschlag.rotation_euler = (math.radians(6.0), 0.0, 0.0)
    # Zwei Kopfkissen, nicht gleich hoch und nicht parallel.
    k1 = polster('kissen0', 11.70, 11.38, Z + 0.50, 0.84, 0.36, 0.155, g,
                 m['bettwaesche'], rundung=0.075, unruhe=0.026, samen=15,
                 fuss=False)
    k1.rotation_euler = (math.radians(-5.0), 0.0, math.radians(3.0))
    k2 = polster('kissen1', 12.84, 11.41, Z + 0.498, 0.82, 0.35, 0.145, g,
                 m['bettwaesche'], rundung=0.075, unruhe=0.026, samen=16,
                 fuss=False)
    k2.rotation_euler = (math.radians(-6.5), 0.0, math.radians(-4.0))
    # Ein Ziersatz quer davor -- der Griff, der ein Bett bewohnt macht.
    kz = polster('bett_zier', 12.05, 11.06, Z + 0.505, 0.62, 0.20, 0.20, g,
                 m['akzent'], rundung=0.07, unruhe=0.024, samen=17,
                 fuss=False)
    kz.rotation_euler = (0.0, 0.0, math.radians(-7.0))
    for i, x in enumerate((11.00, 13.80)):
        kasten('nachttisch%d' % i, x, 11.30, Z, 0.52, 0.42, 0.42, g, m['eiche'])
        zylinder('nachtleuchte%d' % i, x + 0.26, 11.51, Z + 0.42, 0.055, 0.30,
                 g, m['alu'], 12)
        zylinder('nachtschirm%d' % i, x + 0.26, 11.51, Z + 0.72, 0.13, 0.17,
                 g, m['leuchte'], 18)
    teppich('teppich_master', 11.10, 8.40, Z + 0.02, 3.10, 2.20, g, m['teppich'])
    kasten('schrank_master', 14.12, 5.60, Z, 0.62, 3.20, 2.45, g, m['eiche'])
    kasten('bank_master', 11.60, 9.10, Z, 1.70, 0.44, 0.44, g, m['stoff'])
    kasten('bild_master', 10.98, 7.20, Z + 1.05, 0.04, 1.20, 0.85, g, m['kunst'])
    pflanze('pflanze_master', 13.90, 12.40, Z + 0.02, g, m['stein'], m['pflanze'], 1.30)

    # =============================================== KIND 1  x 7.35..10.8, y 5.35..9.2
    kasten('k1_bett', 9.40, 5.60, Z, 1.42, 2.05, 0.38, g, m['eiche'])
    kasten('k1_matratze', 9.48, 5.68, Z + 0.38, 1.26, 1.89, 0.22, g, m['stoff'])
    kasten('k1_decke', 9.52, 6.20, Z + 0.58, 1.18, 1.30, 0.08, g, m['bettwaesche'])
    kasten('k1_kissen', 9.62, 5.78, Z + 0.58, 0.72, 0.32, 0.13, g, m['bettwaesche'])
    kasten('k1_schreibtisch', 7.50, 8.20, Z, 1.40, 0.68, 0.74, g, m['eiche'])
    stuhl('k1_stuhl', 8.20, 7.70, g, m['stoff'], m['alu'], 0.0)
    kasten('k1_regal', 7.46, 5.60, Z, 0.36, 1.60, 1.85, g, m['eiche'])
    teppich('teppich_k1', 7.80, 6.20, Z + 0.02, 1.40, 1.90, g, m['teppich'])

    # =============================================== KIND 2  x 7.35..10.8, y 9.35..13.1
    kasten('k2_bett', 9.40, 11.10, Z, 1.42, 2.05, 0.38, g, m['eiche'])
    kasten('k2_matratze', 9.48, 11.18, Z + 0.38, 1.26, 1.89, 0.22, g, m['stoff'])
    kasten('k2_decke', 9.52, 11.70, Z + 0.58, 1.18, 1.30, 0.08, g, m['bettwaesche'])
    kasten('k2_kissen', 9.62, 11.28, Z + 0.58, 0.72, 0.32, 0.13, g, m['bettwaesche'])
    kasten('k2_schreibtisch', 7.50, 9.55, Z, 1.40, 0.68, 0.74, g, m['eiche'])
    kasten('k2_regal', 7.46, 11.20, Z, 0.36, 1.60, 1.85, g, m['eiche'])
    teppich('teppich_k2', 7.80, 10.80, Z + 0.02, 1.40, 1.90, g, m['teppich'])

    # =============================================== BAD  x 2.4..5.2, y 8.75..13.1
    # Freistehende Wanne vor dem Schlitzfenster
    kasten('wanne', 2.70, 11.50, Z, 0.82, 1.75, 0.58, g, m['keramik'])
    kasten('wanne_innen', 2.78, 11.58, Z + 0.10, 0.66, 1.59, 0.48, g, m['wasser'])
    kasten('waschtisch', 3.85, 8.95, Z, 1.30, 0.52, 0.86, g, m['eiche'])
    kasten('waschtisch_platte', 3.79, 8.89, Z + 0.86, 1.42, 0.64, 0.04, g, m['stein'])
    for i, x in enumerate((4.05, 4.75)):
        kasten('becken%d' % i, x, 9.02, Z + 0.90, 0.46, 0.32, 0.10, g, m['keramik'])
        zylinder('badarmatur%d' % i, x + 0.23, 9.30, Z + 0.90, 0.014, 0.24, g, m['alu'], 10)
    kasten('badspiegel', 3.79, 8.82, Z + 1.05, 1.42, 0.03, 0.95, g, m['spiegel'])
    kasten('dusche_glas', 4.55, 10.20, Z, 0.02, 1.40, 2.10, g, m['glas'])
    kasten('dusche_glas2', 3.20, 11.58, Z, 1.36, 0.02, 2.10, g, m['glas'])
    kasten('dusche_tasse', 3.20, 10.22, Z, 1.38, 1.38, 0.03, g, m['stein'])
    kasten('bad_wc', 4.68, 12.55, Z, 0.38, 0.56, 0.42, g, m['keramik'])

    # =============================================== GALERIE
    kasten('galerie_sideboard', 5.60, 3.60, Z, 1.60, 0.40, 0.62, g, m['eiche'])
    kasten('galerie_bild', 5.45, 7.60, Z + 1.10, 0.04, 1.40, 0.95, g, m['kunst'])
    pflanze('pflanze_galerie', 6.60, 12.50, Z + 0.02, g, m['stein'], m['pflanze'], 1.40)
    return ['og']


def dekor(g, m, Z_OG):
    """Das Kleinzeug.

    WARUM DAS DEN GROESSTEN UNTERSCHIED MACHT
    Ein Wohnzimmer mit Sofa, Tisch und Teppich ist ein Moebelkatalog. Erst
    die Schale auf dem Tisch, die drei Buecher, die Vase und das Kissen in
    einer anderen Farbe machen daraus einen Raum, in dem jemand wohnt --
    und genau das ist der Unterschied, den ein Kaufinteressent spuert,
    ohne ihn benennen zu koennen. Zwoelf Objekte, kaum Dreiecke, grosse
    Wirkung.
    """
    # --- Wohnen: Schale, Buecher, Kissen in Akzentfarbe
    zylinder('deko_schale', 12.40, 7.30, 0.352, 0.19, 0.075, g, m['keramik'], 20)
    for i in range(3):
        kasten('deko_buch%d' % i, 13.00 + i * 0.015, 7.00, 0.352 + i * 0.035,
               0.24, 0.31, 0.033, g, m['kunst'])
    for i, (x, y) in enumerate(((11.80, 9.20), (13.10, 9.20), (15.20, 7.40))):
        kasten('deko_kissen%d' % i, x, y, 0.56, 0.44, 0.16, 0.42, g, m['akzent'])
    zylinder('deko_vase_wohnen', 9.98, 6.10, 0.52, 0.10, 0.34, g, m['keramik'], 18)

    # --- Essen: Laeufer, Schale, zwei Glaeser
    kasten('deko_laeufer', 8.90, 1.72, 0.780, 1.80, 0.42, 0.006, g, m['akzent'])
    zylinder('deko_essschale', 9.80, 1.92, 0.780, 0.16, 0.06, g, m['keramik'], 20)
    for i, x in enumerate((9.40, 10.30)):
        zylinder('deko_glas%d' % i, x, 1.74, 0.780, 0.035, 0.11, g, m['glas'], 14)

    # --- Kueche: Brett, Topf, Oelflasche
    kasten('deko_brett', 7.05, 6.85, 0.945, 0.46, 0.30, 0.025, g, m['eiche'])
    zylinder('deko_topf', 6.10, 9.95, 0.94, 0.12, 0.14, g, m['alu'], 20)
    zylinder('deko_flasche', 8.62, 9.92, 0.94, 0.038, 0.27, g, m['glas'], 14)

    # --- Bad: Handtuecher und eine Seifenschale
    for i, x in enumerate((3.98, 4.68)):
        kasten('deko_handtuch%d' % i, x, Z_OG * 0 + 8.92, Z_OG + 0.62,
               0.22, 0.05, 0.34, g, m['bettwaesche'])
    zylinder('deko_seife', 4.40, Z_OG * 0 + 9.10, Z_OG + 0.90, 0.055, 0.035,
             g, m['keramik'], 16)

    # --- Master: Buch und Tablett auf der Bank
    kasten('deko_buch_bett', 11.06, 11.36, Z_OG + 0.42, 0.15, 0.22, 0.028,
           g, m['kunst'])
    kasten('deko_tablett', 11.90, 9.16, Z_OG + 0.44, 0.42, 0.30, 0.022,
           g, m['eiche'])
    return ['dekor']
