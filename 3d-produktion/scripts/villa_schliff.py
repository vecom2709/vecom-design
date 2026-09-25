# -*- coding: utf-8 -*-
"""
villa_schliff.py -- Feinschliff an den Stellen, die im Bild noch
                    "gerechnet" ausgesehen haben.

GEMESSEN, NICHT VERMUTET
Im Unreal-Render vom 19.09.2026 lag die Poolflaeche bei einer Helligkeit
von 0,4854 -- flaches Grau, gegen einen Himmel von 0,7655. Weder der
Brechungsmodus (RM_INDEX_OF_REFRACTION) noch two_sided haben daran etwas
geaendert. Der Grund war banal und lag nicht im Material, sondern in der
Geometrie aus villa.aussen_bauen:

    quader('pool_becken', 5.80, 14.80, -1.55, 10.40, 4.40, 1.50, ...)
    quader('pool_wasser', 6.00, 15.00, -1.45, 10.00,  4.00, 1.32, ...)

Das Becken war ein MASSIVER Quader von z -1,55 bis -0,05. Der Wasserquader
lag mit seiner Oberkante bei -0,13 vollstaendig DARIN. Sichtbar war also
nie das Wasser, sondern der Deckel des Steinquaders. Kein Material der
Welt repariert das.

WIE ES JETZT GEBAUT IST
Wie ein Pool gebaut wird: Wanne aus Sohle und vier Waenden, Sohle mit
Gefaelle von 1,20 m auf 1,70 m, Roemische Treppe am Westende, dunkles
Wasserlinienband -- und darueber eine EINZELNE, verdraengte Wasserflaeche.
Eine Flaeche, kein Koerper: Der Pfadverfolger rechnet dann genau eine
Grenzflaeche Luft/Wasser, und das ist genau das, was eine Kamera sieht.
Ein Wasser-KOERPER haette zwei Grenzflaechen und wuerde das Bild unter
Wasser doppelt brechen.

DAZU ZWEI PFLICHTDETAILS AUS buildings.md, DIE NOCH FEHLTEN
  - Der Anschlag in der Fensterlaibung: 25 mm Ruecksprung, 120 mm hinter
    der Fassadenflaeche. Er erzeugt die Schattenlinie, an der das Auge ein
    Fenster von einem dunklen Rechteck unterscheidet.
  - Die Tropfkante an den Fensterbaenken der Ost- und Westseite.
    villa_detail.laibungen_und_baenke hat sie nur fuer Nord und Sued
    gebaut -- im y-Zweig fehlt sie, siehe dort.
"""

import bmesh
import bpy
import math

from mathutils import Vector, noise

# --------------------------------------------------------------- Pool
# Die Lichte der Wanne. Sie entspricht GENAU der Oeffnung im Randstein-
# ring aus villa.aussen_bauen (pool_rand_n endet bei y 15,00, pool_rand_s
# beginnt bei 19,00) -- sonst steht das Wasser neben dem Rand.
PX0, PX1 = 5.80, 16.20
PY0, PY1 = 15.00, 19.00

WAND = 0.25               # Wandstaerke der Wanne
SPIEGEL = -0.12           # Wasserspiegel, 12 cm unter Randsteinoberkante
SOHLE_WEST = -1.32        # 1,20 m Wassertiefe am flachen Ende
SOHLE_OST = -1.82         # 1,70 m am tiefen Ende
UNTERKANTE = -2.10        # Unterkante Sohle und Waende
STUFEN_BIS = 7.15         # bis hierhin reicht die Treppe, dann Gefaelle


def _kasten(name, x0, y0, z0, dx, dy, dz, g, mat=None):
    """Wie villa.quader: Ursprung unten-vorne-links, echte Masse im NETZ,
    Objektskalierung bleibt (1, 1, 1). Nur so greift die Fase spaeter."""
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
    g.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob


def _keil(name, x0, x1, y0, y1, z_unten, z_west, z_ost, g, mat=None):
    """Ein geschlossener Koerper mit geneigter Oberseite -- die Poolsohle.

    Kein Quader mit gedrehtem Objekt: Die Fase in villa.kanten_brechen
    arbeitet nur bei Objektskalierung (1, 1, 1), und eine Drehung wuerde
    ausserdem die Wanne verziehen. Acht Punkte, sechs Flaechen, fertig.
    """
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    v = {}
    for ix, x in ((0, x0), (1, x1)):
        zo = z_west if ix == 0 else z_ost
        for iy, y in ((0, y0), (1, y1)):
            v[(ix, iy, 0)] = bm.verts.new((x, y, z_unten))
            v[(ix, iy, 1)] = bm.verts.new((x, y, zo))
    bm.verts.ensure_lookup_table()
    f = bm.faces.new
    f((v[(0, 0, 1)], v[(1, 0, 1)], v[(1, 1, 1)], v[(0, 1, 1)]))
    f((v[(0, 0, 0)], v[(0, 1, 0)], v[(1, 1, 0)], v[(1, 0, 0)]))
    f((v[(0, 0, 0)], v[(1, 0, 0)], v[(1, 0, 1)], v[(0, 0, 1)]))
    f((v[(0, 1, 0)], v[(0, 1, 1)], v[(1, 1, 1)], v[(1, 1, 0)]))
    f((v[(0, 0, 0)], v[(0, 0, 1)], v[(0, 1, 1)], v[(0, 1, 0)]))
    f((v[(1, 0, 0)], v[(1, 1, 0)], v[(1, 1, 1)], v[(1, 0, 1)]))
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    g.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob


def _saum(x, y, breite=0.14):
    """1.0 in der Beckenmitte, 0.0 am Rand.

    Ohne diesen Saum stuende die verdraengte Wasserflaeche am Rand mal
    3 mm ueber dem Randstein und mal 3 mm darunter -- eine sichtbar
    gezackte Wasserlinie. Am Rand MUSS das Wasser genau auf Spiegelhoehe
    liegen, sonst verraet sich die Verdraengung.
    """
    d = min(x - PX0, PX1 - x, y - PY0, PY1 - y)
    t = min(max(d / breite, 0.0), 1.0)
    return t * t * (3.0 - 2.0 * t)


def _welle(x, y):
    """Die Kraeuselung eines ruhigen Pools bei leichtem Zug.

    Drei Groessenordnungen, weil eine einzelne Frequenz wie eine Tapete
    aussieht: 55 cm Duenung (+-4,2 mm), 15 cm Kraeuselung (+-1,6 mm),
    6 cm Kapillarwellen (+-0,6 mm). Zusammen hoechstens 6,4 mm -- mehr
    waere kein Pool mehr, sondern ein See bei Wind.
    """
    a = noise.noise(Vector((x * 1.85, y * 1.85, 3.0))) * 0.0042
    b = noise.noise(Vector((x * 6.50, y * 6.50, 9.0))) * 0.0016
    c = noise.noise(Vector((x * 17.0, y * 17.0, 21.0))) * 0.0006
    return a + b + c


def pool_wanne(g, m):
    """Die Wanne: Sohle mit Gefaelle, vier Waende, Roemische Treppe.

    Der alte Massivquader 'pool_becken' und der alte Wasserquader
    'pool_wasser' werden geloescht -- sie sind der Fehler, nicht die
    Grundlage.
    """
    entfernt = 0
    for n in ('pool_becken', 'pool_wasser', 'p08_pool_becken',
              'p08_pool_wasser'):
        ob = bpy.data.objects.get(n)
        if ob is not None:
            bpy.data.objects.remove(ob, do_unlink=True)
            entfernt += 1

    stein = m['poolstein']

    # Sohle mit Gefaelle, erst ab dem Ende der Treppe.
    _keil('pool_sohle', STUFEN_BIS, PX1, PY0, PY1,
          UNTERKANTE, SOHLE_WEST, SOHLE_OST, g, stein)

    # Roemische Treppe am Westende. Drei Stufen, jede 30 cm hoch --
    # das ist das Mass, das man ohne Handlauf noch bequem geht. Jede
    # Stufe reicht bis zur Unterkante durch: ueberlappende geschlossene
    # Koerper sind fuer den Pfadverfolger unproblematisch, offene nicht.
    for name, x1, oben in (('pool_stufe3', STUFEN_BIS, -1.02),
                           ('pool_stufe2', 6.70, -0.72),
                           ('pool_stufe1', 6.25, -0.42)):
        _kasten(name, PX0, PY0, UNTERKANTE, x1 - PX0, PY1 - PY0,
                oben - UNTERKANTE, g, stein)

    # Vier Waende. Oberkante -0,08: genau dort beginnt der Randstein
    # aus villa.aussen_bauen, der also auf der Wand aufliegt statt zu
    # schweben.
    ok = -0.08
    for name, x0, y0, dx, dy in (
            ('pool_wand_n', PX0 - WAND, PY0 - WAND,
             (PX1 - PX0) + 2 * WAND, WAND),
            ('pool_wand_s', PX0 - WAND, PY1,
             (PX1 - PX0) + 2 * WAND, WAND),
            ('pool_wand_w', PX0 - WAND, PY0, WAND, PY1 - PY0),
            ('pool_wand_o', PX1, PY0, WAND, PY1 - PY0)):
        _kasten(name, x0, y0, UNTERKANTE, dx, dy, ok - UNTERKANTE, g, stein)

    # Wasserlinienband. In jedem gebauten Pool sitzt an der Wasserlinie
    # eine dunklere Fliesenreihe -- sie verdeckt den Kalkrand. Ohne sie
    # laeuft die Wand ohne Zaesur ins Wasser, und genau daran erkennt
    # man ein gerechnetes Becken. 12,5 cm hoch, oben 1 cm ueber dem
    # Spiegel, damit keine Haarfuge entsteht.
    band = m['stein']
    bu, bh = SPIEGEL - 0.125, 0.135
    for name, x0, y0, dx, dy in (
            ('pool_band_n', PX0, PY0 - 0.005, PX1 - PX0, 0.020),
            ('pool_band_s', PX0, PY1 - 0.015, PX1 - PX0, 0.020),
            ('pool_band_w', PX0 - 0.005, PY0, 0.020, PY1 - PY0),
            ('pool_band_o', PX1 - 0.015, PY0, 0.020, PY1 - PY0)):
        _kasten(name, x0, y0, bu, dx, dy, bh, g, band)

    # Skimmer und zwei Einstroemduesen. Kleinteile, die niemand bewusst
    # sieht und deren Fehlen jeder bemerkt.
    alu = m['alu']
    _kasten('pool_skimmer', 14.60, PY0 - 0.02, SPIEGEL - 0.09,
            0.38, 0.045, 0.22, g, alu)
    for i, x in enumerate((8.20, 12.40)):
        _kasten('pool_duese%d' % i, x, PY1 - 0.045, SPIEGEL - 0.34,
                0.09, 0.050, 0.09, g, alu)
    return entfernt


def wasserflaeche(g, m, zelle=0.05):
    """EINE verdraengte Flaeche auf Spiegelhoehe -- kein Koerper.

    5 cm Rasterweite ergibt 208 x 80 Zellen, 33.280 Dreiecke. Das klingt
    viel fuer eine Pfuetze von 42 Quadratmetern und ist trotzdem richtig:
    Die Kraeuselung ist 1,6 mm hoch bei 15 cm Wellenlaenge. Wer sie ueber
    die Normalkarte statt ueber die Geometrie macht, bekommt ein Glanzlicht
    ohne Silhouette -- und die Silhouette am Beckenrand ist genau das, was
    Wasser von Glas unterscheidet.

    Glatt schattiert. Die Fase in villa.kanten_brechen muss dieses Objekt
    ueberspringen, sonst steht es flach schattiert und facettiert da; das
    besorgt die Namensregel dort ('wasser' im Namen).
    """
    me = bpy.data.meshes.new('pool_wasser')
    bm = bmesh.new()
    nx = int(round((PX1 - PX0) / zelle))
    ny = int(round((PY1 - PY0) / zelle))
    sx = (PX1 - PX0) / nx
    sy = (PY1 - PY0) / ny
    gitter = []
    for iy in range(ny + 1):
        y = PY0 + iy * sy
        zeile = []
        for ix in range(nx + 1):
            x = PX0 + ix * sx
            z = SPIEGEL + _welle(x, y) * _saum(x, y)
            zeile.append(bm.verts.new((x, y, z)))
        gitter.append(zeile)
    bm.verts.ensure_lookup_table()
    for iy in range(ny):
        for ix in range(nx):
            bm.faces.new((gitter[iy][ix], gitter[iy][ix + 1],
                          gitter[iy + 1][ix + 1], gitter[iy + 1][ix]))
    bm.normal_update()
    bm.to_mesh(me)
    bm.free()

    ob = bpy.data.objects.new('pool_wasser', me)
    g.objects.link(ob)
    ob.data.materials.append(m['wasser'])
    for p in me.polygons:
        p.use_smooth = True
    return len(me.polygons)


# ------------------------------------------------------------- Fenster

ANSCHLAG = 0.025          # Ruecksprung der Laibung
ANSCHLAG_TIEFE = 0.12     # so weit hinter der Fassadenflaeche
ANSCHLAG_BAND = 0.055     # Tiefe des Bandes, endet vor dem Rahmen


def fenster_anschlag(g, m, oeffnungen, wand=0.40):
    """Der Anschlag in der Laibung -- die Schattenlinie um jedes Fenster.

    Eine Laibung, die von der Fassade bis zum Rahmen glatt durchlaeuft,
    ist ein Trichter. Gebaut wird sie anders: 120 mm hinter der
    Fassadenflaeche springt sie 25 mm ein, und der Rahmen sitzt in diesem
    Ruecksprung. Im Streiflicht ergibt das eine harte, umlaufende Linie.
    Sie ist nur 25 mm breit, aber sie ist der Unterschied zwischen einem
    Fenster und einem dunklen Rechteck in einer Wand.

    Das Band endet 55 mm hinter dem Ruecksprung, also VOR dem 50 mm
    tiefen Rahmen aus villa.verglasung. Ohne diese Begrenzung wuerden
    Putz und Aluminium ineinanderstehen, und an der Durchdringung sieht
    man im Pfadverfolger Flecken.
    """
    n = 0
    putz = m['putz']
    t = ANSCHLAG_BAND
    for (achse, lage, pos, breite, zu, zo, ri) in oeffnungen:
        a = lage + ri * (wand / 2.0 - ANSCHLAG_TIEFE)
        q = a - (t if ri > 0 else 0.0)     # Bandanfang auf der Wandachse
        kennung = '%s_%.2f_%.2f' % (achse, lage, pos)

        def stab(nm, p0, laenge, z0, dz):
            if achse == 'x':
                _kasten('anschlag_%s_%s' % (nm, kennung), p0, q, z0,
                        laenge, t, dz, g, putz)
            else:
                _kasten('anschlag_%s_%s' % (nm, kennung), q, p0, z0,
                        t, laenge, dz, g, putz)

        stab('l', pos, ANSCHLAG, zu, zo - zu)
        stab('r', pos + breite - ANSCHLAG, ANSCHLAG, zu, zo - zu)
        stab('o', pos, breite, zo - ANSCHLAG, ANSCHLAG)
        # Unten nur, wo es eine Bruestung gibt. Eine bodentiefe Oeffnung
        # hat unten eine Schwelle, keinen Anschlag.
        if zu > 0.30:
            stab('u', pos, breite, zu, ANSCHLAG)
        n += 1
    return n


def tropfkanten_ost_west(g, m, oeffnungen, wand=0.40):
    """Die fehlende Haelfte aus villa_detail.laibungen_und_baenke.

    Dort bekommt der x-Zweig (Nord- und Suedfassade) Bank UND Tropfkante,
    der y-Zweig (Ost und West) nur die Bank -- die Wassernase fehlt. Eine
    Fensterbank ohne Tropfkante fuehrt das Wasser an der Fassade entlang;
    genau die Laufspuren, die das Material darunter zeichnet, haetten dann
    keinen Grund. Hier wird sie nachgereicht.
    """
    n = 0
    stein = m['stein']
    u = 0.04
    for (achse, lage, pos, breite, zu, zo, ri) in oeffnungen:
        if achse != 'y' or zu <= 0.30:
            continue
        x0 = lage + ri * (wand / 2.0) - (u if ri > 0 else 0.0)
        _kasten('tropf_y_%.2f_%.2f' % (lage, pos),
                x0 + (u - 0.012 if ri > 0 else 0.0), pos - 0.05, zu - 0.055,
                0.012, breite + 0.10, 0.022, g, stein)
        n += 1
    return n


def alles(g_fassade, g_aussen, m, oeffnungen):
    """Wird aus villa.haupt gerufen, NACH villa_detail.alles und VOR
    abschnitte_ordnen -- die neuen Teile brauchen ihren p06_/p08_-Praefix
    genauso wie alle anderen."""
    bericht = {}
    bericht['pool_alt_entfernt'] = pool_wanne(g_aussen, m)
    bericht['wasser_flaechen'] = wasserflaeche(g_aussen, m)
    bericht['anschlaege'] = fenster_anschlag(g_fassade, m, oeffnungen)
    bericht['tropfkanten_y'] = tropfkanten_ost_west(g_fassade, m, oeffnungen)
    print('[schliff] Pool neu gebaut (%d Altteile entfernt), '
          '%d Wasserflaechen, %d Laibungsanschlaege, %d Tropfkanten'
          % (bericht['pool_alt_entfernt'], bericht['wasser_flaechen'],
             bericht['anschlaege'], bericht['tropfkanten_y']))
    return bericht
