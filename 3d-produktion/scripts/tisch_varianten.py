# -*- coding: utf-8 -*-
"""Der Tisch als Funktion, nicht als Datei.

WARUM EINE FUNKTION UND KEINE SECHS OBJEKTE

Zwei Gestellformen mal drei Maesse waeren sechs Modelle, die man sechsmal
pflegen muss. Aendert sich die Fase, aendert sie sich an sechs Stellen -- und
beim siebten Mal vergisst man eines. Der Konfigurator auf der Seite braucht
ohnehin eine Funktion: Er schickt (Laenge, Gestell, Holz, Metall) und bekommt
ein Modell. Also wird es gleich so gebaut.

DIE VIER ACHSEN
    Holz     Eiche | Esche | Nussbaum | Raeuchereiche      4
    Metall   Schwarzstahl | Edelstahl | Messing            3
    Gestell  Wange | Vierbein                              2
    Laenge   1,80 | 2,00 | 2,40 m                          3
                                                       = 72

MASSE, DIE NICHT VERHANDELBAR SIND
    Oberkante      0,750 m   Esstischhoehe
    Plattenstaerke 0,040 m   Massivholz
    Breite         0,950 m   zwei Gedecke gegenueber
    Beinfreiheit   mind. 0,640 m unter der Zarge

FALLE, die bei den Wangen schon zugeschlagen hat: o.location setzt nicht
sofort matrix_world. Wer direkt danach misst, misst die Werte von vorher.
Deshalb steht am Ende jeder Aufbaufunktion ein view_layer.update().
"""
import bpy, bmesh, math
from mathutils import Vector

HOEHE, STAERKE, BREITE = 0.750, 0.040, 0.950
LAENGEN = {'klein': 1.80, 'mittel': 2.00, 'gross': 2.40}
GESTELLE = ('wange', 'vierbein')
SAMMLUNG = 'Tisch'


def _sammlung(szene):
    s = bpy.data.collections.get(SAMMLUNG)
    if s is None:
        s = bpy.data.collections.new(SAMMLUNG)
    if s.name not in [k.name for k in szene.collection.children]:
        szene.collection.children.link(s)
    for o in list(s.objects):
        bpy.data.objects.remove(o, do_unlink=True)
    return s


def _koerper(name, mitte, groesse, mat, samm, fase=0.0035, verjuengung=None):
    """Quader, wahlweise nach unten verjuengt (fuer Beine).

    verjuengung = Faktor der unteren Flaeche, 1.0 = gerade.
    """
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    bmesh.ops.scale(bm, vec=Vector(groesse), verts=bm.verts)
    if verjuengung and verjuengung != 1.0:
        unten = min(v.co.z for v in bm.verts)
        for v in bm.verts:
            if abs(v.co.z - unten) < 1e-6:
                v.co.x *= verjuengung
                v.co.y *= verjuengung
    bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new(name, me)
    o.location = mitte
    me.materials.append(mat)
    if fase > 0:
        f = o.modifiers.new('Fase', 'BEVEL')
        f.width = fase; f.segments = 3
        f.limit_method = 'ANGLE'; f.angle_limit = math.radians(30)
        wn = o.modifiers.new('Normalen', 'WEIGHTED_NORMAL')
        wn.keep_sharp = True
    for p in me.polygons:
        p.use_smooth = True
    samm.objects.link(o)
    return o


def bauen(laenge='mittel', gestell='wange', holz='Eiche', metall='Schwarzstahl',
          szene=None, versatz=(0.0, 0.0)):
    """Baut eine Variante. Gibt die Objektliste zurueck."""
    szene = szene or bpy.context.scene
    L = LAENGEN[laenge] if isinstance(laenge, str) else float(laenge)
    samm = _sammlung(szene) if not versatz[0] and not versatz[1] else _sammlung_frei(szene)
    mh = bpy.data.materials['M_Holz_' + holz]
    mm = bpy.data.materials['M_Metall_' + metall]
    vx, vy = versatz
    teile = []

    # ---- Platte: bei allen Varianten gleich aufgebaut ----------------------
    teile.append(_koerper('Platte', (vx, vy, HOEHE - STAERKE / 2),
                          (L, BREITE, STAERKE), mh, samm, fase=0.004))

    if gestell == 'wange':
        # Zwei Flachstahlwangen, 26 cm eingerueckt, plus Laengstraverse.
        # Eingerueckt, damit an den Stirnseiten jemand sitzen kann.
        WT, EIN = 0.012, 0.26
        xw = L / 2 - EIN
        for s, x in (('L', -xw), ('R', xw)):
            teile.append(_koerper('Wange_' + s, (vx + x, vy, (HOEHE - STAERKE) / 2),
                                  (WT, BREITE - 0.18, HOEHE - STAERKE), mm, samm, fase=0.002))
            teile.append(_koerper('Fuss_' + s, (vx + x, vy, 0.010),
                                  (0.075, BREITE - 0.10, 0.020), mm, samm, fase=0.002))
        teile.append(_koerper('Traverse', (vx, vy, 0.115),
                              (2 * xw - WT, 0.055, 0.016), mm, samm, fase=0.002))

    elif gestell == 'vierbein':
        # Vier sich verjuengende Vierkantbeine plus schmale Zarge.
        # Die Zarge ist das, was Vierbein von "vier Stangen" unterscheidet --
        # und sie ist der Grund, warum die Beinfreiheit nachgerechnet wird.
        # 16.09.2026: von 50/36 auf 62/46 mm. 50 mm ist fuer Stahl statisch
        # richtig, trug die 2,40-m-Platte aber optisch nicht -- die Beine
        # lasen als Stangen. Ein Bein muss aussehen, als koennte es halten,
        # nicht nur rechnerisch halten. Zarge entsprechend von 48 auf 58 mm,
        # sonst verschwindet sie zwischen den kraeftigeren Beinen.
        BEIN_O, BEIN_U = 0.062, 0.046       # oben, unten
        EIN_X, EIN_Y = 0.115, 0.085
        # 16.09.2026, von der Pruefung gefunden: Die Zarge sass 64 mm
        # eingerueckt und stand damit WEITER AUSSEN als die Beine (115 mm).
        # Ueberstand war dadurch 56 mm statt 80 -- an den Stirnseiten haette
        # kein Stuhl darunter gepasst, und zwar bei allen 36 Vierbein-
        # Varianten. Im Render sieht man das nicht; die Zarge ist von oben
        # verdeckt. Jetzt sitzt sie hinter den Beinen, damit die Beine den
        # Ueberstand bestimmen und nicht das duenne Profil davor.
        ZARGE_H, ZARGE_T = 0.058, 0.016
        ZARGE_EIN_X = EIN_X + BEIN_O / 2 + 0.010      # hinter die Beine
        ZARGE_EIN_Y = EIN_Y + BEIN_O / 2 + 0.010
        bein_h = HOEHE - STAERKE
        for sx in (-1, 1):
            for sy in (-1, 1):
                x = sx * (L / 2 - EIN_X - BEIN_O / 2)
                y = sy * (BREITE / 2 - EIN_Y - BEIN_O / 2)
                teile.append(_koerper('Bein_%+d%+d' % (sx, sy),
                                      (vx + x, vy + y, bein_h / 2),
                                      (BEIN_O, BEIN_O, bein_h), mm, samm,
                                      fase=0.0025, verjuengung=BEIN_U / BEIN_O))
        zh = bein_h - ZARGE_H / 2 - 0.004
        for sy in (-1, 1):
            teile.append(_koerper('Zarge_laengs_%+d' % sy,
                                  (vx, vy + sy * (BREITE / 2 - ZARGE_EIN_Y), zh),
                                  (L - 2 * ZARGE_EIN_X, ZARGE_T, ZARGE_H), mm, samm, fase=0.002))
        for sx in (-1, 1):
            teile.append(_koerper('Zarge_quer_%+d' % sx,
                                  (vx + sx * (L / 2 - ZARGE_EIN_X), vy, zh),
                                  (ZARGE_T, BREITE - 2 * ZARGE_EIN_Y, ZARGE_H), mm, samm, fase=0.002))
        # Beinfreiheit nachrechnen statt behaupten
        frei = zh - ZARGE_H / 2
        if frei < 0.64:
            print('[TISCH] WARNUNG Beinfreiheit nur %.3f m (Minimum 0,64)' % frei)
    else:
        raise ValueError('unbekanntes Gestell: ' + gestell)

    bpy.context.view_layer.update()
    return teile


def _sammlung_frei(szene):
    """Eigene Sammlung fuer Vergleichsaufbauten, die nicht geleert wird."""
    s = bpy.data.collections.get('Tisch_Vergleich')
    if s is None:
        s = bpy.data.collections.new('Tisch_Vergleich')
    if s.name not in [k.name for k in szene.collection.children]:
        szene.collection.children.link(s)
    return s


def masse(teile):
    """Weltmasse jedes Teils. Nach view_layer.update, sonst wertlos."""
    aus = []
    for o in teile:
        e = [o.matrix_world @ Vector(k) for k in o.bound_box]
        xs = [p.x for p in e]; ys = [p.y for p in e]; zs = [p.z for p in e]
        aus.append((o.name, max(xs) - min(xs), max(ys) - min(ys),
                    max(zs) - min(zs), min(zs), max(zs)))
    return aus
