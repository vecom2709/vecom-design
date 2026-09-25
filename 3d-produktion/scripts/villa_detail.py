# -*- coding: utf-8 -*-
"""
villa_detail.py — Die Bauteile, die aus Quadern ein Gebaeude machen.

Aus der Pruefliste des Realismus-Skills, Abschnitt "Haeufige CAD-Merkmale,
die entfernt werden muessen":

  - Fenster als Glasflaeche buendig in der Wand   -> Laibung, Fensterbank,
                                                     Rahmenprofil, zwei Scheiben
  - fehlende Fugen zwischen Bauteilen             -> Dehnungsfuge, Silikonfuge
  - Boden, der ohne Uebergang an die Wand stoesst -> Kiesstreifen mit Gefaelle
  - keine Installationen                          -> Leuchten, Hausnummer,
                                                     Klingel, Rinne, Fallrohr,
                                                     Klimageraet

Jedes Stueck hier hat einen Grund aus der Wirklichkeit. Die Villa steht auf
Sizilien: Flachdach mit innenliegender Entwaesserung, Attika, Aussenleuchten
an der Zufahrt, ein Klimageraet an der Nordwand -- ohne das waere sie dort
nicht bewohnbar.
"""

import bpy
import bmesh
import math


def _kasten(name, x0, y0, z0, dx, dy, dz, g, mat=None):
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


def _rohr(name, x, y, z0, radius, hoehe, g, mat=None, seiten=14):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=seiten,
                          radius1=radius, radius2=radius, depth=hoehe)
    bmesh.ops.translate(bm, verts=bm.verts, vec=(0.0, 0.0, hoehe / 2.0))
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    ob.location = (x, y, z0)
    g.objects.link(ob)
    if mat is not None:
        ob.data.materials.append(mat)
    return ob


# Die Oeffnungen, wie sie in villa.py gesetzt sind. Hier noch einmal, weil
# Laibung, Bank und zweite Scheibe genau dort sitzen muessen.
# (achse, lage, pos, breite, z_unten, z_oben, aussen_richtung)
OEFFNUNGEN = [
    # --- Erdgeschoss, Aussenwand 0,40 m
    ('x', 0.20, 7.00, 9.00, 1.00, 2.80, -1),      # Nord, Bandfenster Essen
    ('x', 10.80, 5.60, 3.60, 0.05, 3.15, +1),     # Sued, Kueche
    ('x', 10.80, 9.80, 7.60, 0.05, 3.15, +1),     # Sued, Wohnen
    ('y', 0.20, 1.20, 1.60, 0.05, 3.15, -1),      # West, Entree
    ('y', 0.20, 9.20, 0.90, 1.80, 2.90, -1),      # West, WC
    ('y', 17.80, 4.20, 6.20, 0.05, 3.15, +1),     # Ost, Wohnen
    # --- Obergeschoss (Koerper B), Z 3,60
    ('x', 3.20, 5.60, 8.60, 3.90, 5.60, -1),      # Nord, Galerie
    ('x', 13.30, 7.50, 3.10, 3.65, 6.35, +1),     # Sued, Kind 2
    ('x', 13.30, 11.10, 3.40, 3.65, 6.35, +1),    # Sued, Master
    ('y', 2.20, 9.60, 1.20, 4.90, 6.10, -1),      # West, Bad
    ('y', 14.80, 6.00, 6.80, 3.65, 6.35, +1),     # Ost, Master
]


def laibungen_und_baenke(g, m, wand=0.40):
    """Punkt 2 und 8 der Pruefliste.

    LAIBUNG: Die Wand ist 40 cm dick, das Glas sitzt 12 cm hinter der
    Aussenflaeche. Das ergibt eine Leibungstiefe, die im Streiflicht einen
    Schatten wirft -- und dieser Schatten ist der Unterschied zwischen
    "Fenster" und "dunkles Rechteck in einer Wand".

    FENSTERBANK: aussen mit 4 cm Ueberstand und Tropfkante, damit das
    Wasser nicht an der Fassade herunterlaeuft. Genau deshalb sind die
    Laufspuren im Material unter den Baenken begruendet.

    ZWEITE SCHEIBE: Isolierglas hat zwei Scheiben mit Luftspalt. Eine
    einzelne Flaeche mit Transmission hat keine Brechung an der Kante.
    """
    n = 0
    for (achse, lage, pos, breite, zu, zo, ri) in OEFFNUNGEN:
        # --- zweite Scheibe, dahinter
        # 21.09.2026: 30 statt 24 mm. Die erste Scheibe (villa.verglasung)
        # ist 36 mm dick und reicht bis 18 mm hinter die Wandmitte; die
        # zweite ist 12 mm dick und lag bei 24 mm Versatz genau zwischen
        # 18 und 30 mm -- ihre Vorderseite lag AUF der Rueckseite der
        # ersten, deckungsgleich. Zwei Glaskoerper mit einer gemeinsamen
        # Flaeche: Der Strahl weiss nicht, welche er zuerst trifft. Cycles
        # verzeiht das weitgehend, Unreals Pfadverfolger nicht -- dort
        # war die Scheibe oben links dreiecksweise weiss (Probe
        # "glasdreieck"). Jetzt liegt ein 6-mm-Luftspalt dazwischen.
        versatz = -ri * 0.030
        if achse == 'x':
            _kasten('scheibe2_%s_%.2f_%.2f' % (achse, lage, pos),
                    pos + 0.06, lage + versatz - 0.006, zu + 0.06,
                    breite - 0.12, 0.012, zo - zu - 0.12, g, m['glas'])
        else:
            _kasten('scheibe2_%s_%.2f_%.2f' % (achse, lage, pos),
                    lage + versatz - 0.006, pos + 0.06, zu + 0.06,
                    0.012, breite - 0.12, zo - zu - 0.12, g, m['glas'])

        # --- Fensterbank aussen, nur wo eine Bruestung da ist
        if zu > 0.30:
            u = 0.04            # Ueberstand
            if achse == 'x':
                y0 = lage + ri * (wand / 2.0) - (u if ri > 0 else 0.0)
                _kasten('bank_%s_%.2f_%.2f' % (achse, lage, pos),
                        pos - 0.05, y0 - (0.0 if ri > 0 else u), zu - 0.035,
                        breite + 0.10, u + 0.02, 0.035, g, m['stein'])
                _kasten('tropf_%s_%.2f_%.2f' % (achse, lage, pos),
                        pos - 0.05, y0 + (u - 0.012 if ri > 0 else 0.0),
                        zu - 0.055, breite + 0.10, 0.012, 0.022, g, m['stein'])
            else:
                x0 = lage + ri * (wand / 2.0) - (u if ri > 0 else 0.0)
                _kasten('bank_%s_%.2f_%.2f' % (achse, lage, pos),
                        x0 - (0.0 if ri > 0 else u), pos - 0.05, zu - 0.035,
                        u + 0.02, breite + 0.10, 0.035, g, m['stein'])
        n += 1
    return n


def entwaesserung(g, m):
    """Ein Flachdach ohne Attika-Ueberlauf und ohne Fallrohr ist ein
    Rendering. Zwei Speier in der Attika, zwei Fallrohre an den Ecken."""
    for name, x, y in (('fallrohr_nw', 0.22, 0.22), ('fallrohr_no', 17.78, 0.22)):
        _rohr(name, x, y, -0.05, 0.045, 3.70, g, m['alu'], 12)
        _rohr(name + '_bogen', x, y, 3.62, 0.050, 0.14, g, m['alu'], 12)
        for i in range(3):
            _rohr(name + '_schelle%d' % i, x, y, 0.45 + i * 1.15, 0.058, 0.035,
                  g, m['alu'], 12)
    # Speier durch die Attika
    for name, x in (('speier_w', 1.20), ('speier_o', 16.40)):
        _kasten(name, x, -0.02, 3.66, 0.16, 0.30, 0.09, g, m['alu'])


def installationen(g, m):
    """Was ein Haus bewohnt aussehen laesst. Ohne diese Stuecke ist es ein
    Modell des Architekten, kein Haus, in dem jemand wohnt."""
    # Zwei Wandleuchten neben der Haustuer (Nordseite, y = 0)
    for i, x in enumerate((1.80, 4.30)):
        _kasten('aussenleuchte%d' % i, x, -0.09, 2.05, 0.09, 0.09, 0.30,
                g, m['alu'])
    # Hausnummer und Klingel
    _kasten('hausnummer', 4.62, -0.055, 1.62, 0.20, 0.02, 0.28, g, m['alu'])
    _kasten('klingel', 4.62, -0.055, 1.28, 0.08, 0.02, 0.12, g, m['alu'])
    _kasten('briefkasten', 5.10, -0.14, 1.05, 0.34, 0.14, 0.42, g, m['alu'])
    # Aussenwasserhahn
    _rohr('wasserhahn', 16.60, -0.08, 0.55, 0.014, 0.16, g, m['alu'], 10)
    # Klimaaussengeraet an der Nordwand -- auf Sizilien Pflicht, und genau
    # solche Dinge fehlen in jedem Architekturmodell
    _kasten('klima', 15.30, -0.34, 2.35, 0.86, 0.32, 0.58, g, m['alu'])
    for i in range(6):
        _kasten('klima_lamelle%d' % i, 15.34, -0.355, 2.44 + i * 0.07,
                0.78, 0.012, 0.03, g, m['alu'])
    _kasten('klima_konsole_l', 15.34, -0.30, 2.28, 0.05, 0.26, 0.07, g, m['alu'])
    _kasten('klima_konsole_r', 16.07, -0.30, 2.28, 0.05, 0.26, 0.07, g, m['alu'])
    # Bodeneinbauleuchten an der Zufahrt
    for i in range(4):
        _rohr('bodenleuchte%d' % i, 0.40 + i * 2.40, -3.60, -0.055, 0.055,
              0.02, g, m['alu'], 12)


def uebergang_zum_boden(g, m):
    """Punkt: "Boden, der ohne Uebergang an die Wand stoesst".
    Ein 30 cm breiter Kiesstreifen mit Randstein rundum. Er hat einen
    bautechnischen Grund (Spritzwasser, Drainage) und loest zugleich die
    haerteste Kante im Bild auf."""
    b, t = 18.00, 11.00
    for name, x0, y0, dx, dy in (
            ('kiesstreifen_n', -0.36, -0.36, b + 0.72, 0.36),
            ('kiesstreifen_s', -0.36, t, b + 0.72, 0.36),
            ('kiesstreifen_w', -0.36, 0.0, 0.36, t),
            ('kiesstreifen_o', b, 0.0, 0.36, t)):
        _kasten(name, x0, y0, -0.14, dx, dy, 0.14, g, m['kies'])
    for name, x0, y0, dx, dy in (
            ('randstein_n', -0.44, -0.44, b + 0.88, 0.08),
            ('randstein_s', -0.44, t + 0.36, b + 0.88, 0.08),
            ('randstein_w', -0.44, -0.36, 0.08, t + 0.72),
            ('randstein_o', b + 0.36, -0.36, 0.08, t + 0.72)):
        _kasten(name, x0, y0, -0.12, dx, dy, 0.16, g, m['stein'])


def alles(g_fassade, g_aussen, m):
    n = laibungen_und_baenke(g_fassade, m)
    entwaesserung(g_fassade, m)
    installationen(g_fassade, m)
    uebergang_zum_boden(g_aussen, m)
    return n
