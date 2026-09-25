"""pr_kueche.py -- Kuechenbau: Kochinsel 2,40 x 0,95 m mit Spuele und
Induktionskochfeld, Fronten, Arbeitsplatte und Griffe waehlbar; Tuer und
Auszuege oeffnen, die Arbeitsplatte hebt sich ab.

Masse nach Kuechennorm (DIN 68881 / gaengige Hersteller): Sockel 150 mm,
Korpus 720 mm, Platte 40 mm -> Arbeitshoehe 910 mm. Korpustiefe 560 mm,
Front 19 mm, Plattenueberstand vorn 20 mm, Sitzseite 332 mm. Module 600 mm:
Auszuege | Spuelenschrank | Schrank mit Boden | Topfauszuege unter dem
Kochfeld. Fugen 3 mm.

Varianten (KHR_materials_variants): Salbei matt / Eiche / Messing,
Weiss seidenmatt / Carrara / Edelstahl, Nussbaum / Keramik anthrazit /
Schwarz -- Front, Platte und Griffe (samt Armatur) wechseln zusammen.
"""
import math
import numpy as np
import bpy
from mathutils import Vector
import pr_basis as B

# ------------------------------------------------------------------ Masse
SOCKEL, KORPUS, PLATTE = 0.150, 0.720, 0.040
Z_K0, Z_K1 = SOCKEL, SOCKEL + KORPUS              # Korpus unten/oben
Y_VORN = -0.475                                    # Plattenvorderkante
FRONT = 0.019
Y_FRONT = Y_VORN + 0.020                           # Frontflaeche
Y_K0 = Y_FRONT + FRONT                             # Korpusvorderkante
TIEFE = 0.560
Y_K1 = Y_K0 + TIEFE                                # Korpushinterkante
WAND = 0.019
FUGE = 0.003
MODUL = 0.600
X0 = -1.200


def uv_welt(o, hoch=False):
    """UV in Metern aus Weltkoordinaten, je Flaeche nach der Hauptachse der
    Normalen -- ueber Stossfugen hinweg durchgehende Maserung (Platte nach
    dem Ausschnitt der Spuele)."""
    me = o.data; mw = o.matrix_world
    uvl = me.uv_layers[0] if me.uv_layers else me.uv_layers.new(name='UVMap')
    for p in me.polygons:
        n = p.normal; ax, ay, az = abs(n.x), abs(n.y), abs(n.z)
        for li in p.loop_indices:
            c = mw @ me.vertices[me.loops[li].vertex_index].co
            if az >= max(ax, ay):
                uv = (c.x, c.y)
            elif ay >= ax:
                uv = (c.x, c.z)
            else:
                uv = (c.y, c.z)
            uvl.data[li].uv = (uv[1], uv[0]) if hoch and az < max(ax, ay) else uv


def ausschneiden(ziel, schneider):
    """Boolesche Differenz, angewendet; der Schneider wird entfernt."""
    m = ziel.modifiers.new('aus', 'BOOLEAN'); m.operation = 'DIFFERENCE'; m.solver = 'EXACT'; m.object = schneider
    with bpy.context.temp_override(object=ziel, active_object=ziel, selected_objects=[ziel], selected_editable_objects=[ziel]):
        bpy.ops.object.modifier_apply(modifier=m.name)
    bpy.data.objects.remove(schneider, do_unlink=True)


def rund_rechteck(bx, by, r, n=6, mitte=(0.0, 0.0)):
    """Umriss eines Rechtecks bx x by mit Eckradius r (gegen den Uhrzeigersinn)."""
    pts = []
    for cx, cy, a0 in ((bx / 2 - r, by / 2 - r, 0), (-bx / 2 + r, by / 2 - r, 90), (-bx / 2 + r, -by / 2 + r, 180), (bx / 2 - r, -by / 2 + r, 270)):
        for k in range(n + 1):
            a = math.radians(a0 + 90 * k / n)
            pts.append((mitte[0] + cx + r * math.cos(a), mitte[1] + cy + r * math.sin(a)))
    return pts


def rohr(name, punkte, radius, stoffe_, n=20, deckel=True):
    """Rohr entlang eines Polygonzugs (Kurve mit Rundung), als Netz."""
    cu = bpy.data.curves.new(name + '_k', 'CURVE'); cu.dimensions = '3D'
    cu.bevel_depth = radius; cu.bevel_resolution = 4; cu.use_fill_caps = deckel
    sp = cu.splines.new('POLY'); sp.points.add(len(punkte) - 1)
    for i, p in enumerate(punkte):
        sp.points[i].co = (*p, 1.0)
    o = bpy.data.objects.new(name + '_k', cu); bpy.context.scene.collection.objects.link(o)
    bpy.context.view_layer.update()
    dg = bpy.context.evaluated_depsgraph_get()
    me = bpy.data.meshes.new_from_object(o.evaluated_get(dg))
    bpy.data.objects.remove(o, do_unlink=True)
    me.name = name
    me.materials.clear()
    for s in stoffe_:
        me.materials.append(s)
    for p in me.polygons:
        p.use_smooth = True
    ob = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(ob)
    return ob


def bogen(p0, mitte, r, a0, a1, ebene='xz', n=16):
    out = []
    for k in range(n + 1):
        a = math.radians(a0 + (a1 - a0) * k / n)
        if ebene == 'xz':
            out.append((mitte[0] + r * math.cos(a), mitte[1], mitte[2] + r * math.sin(a)))
        else:
            out.append((mitte[0], mitte[1] + r * math.cos(a), mitte[2] + r * math.sin(a)))
    return out


def leer(name, ort, kinder):
    e = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(e)
    e.location = ort
    bpy.context.view_layer.update()
    for o in kinder:
        o.parent = e; o.matrix_parent_inverse = e.matrix_world.inverted()
    return e


def bauen(web=False):
    VAR = ['Salbei', 'Weiss', 'Nussbaum']
    front = [B.stoff('Front Salbei', (0.150, 0.190, 0.150), 0.62, spec=0.4),
             B.stoff('Front Weiss', (0.760, 0.755, 0.735), 0.42, spec=0.45),
             B.stoff('Front Nussbaum', (1, 1, 1), 0.48, farbkarte='holz-kiste-farbe', normal='innen-walnuss-normal', normal_staerke=0.18, kachel=0.40)]
    platte = [B.stoff('Platte Eiche', (1, 1, 1), 0.50, farbkarte='kueche-eiche-farbe', normal='kueche-eiche-normal', normal_staerke=0.5, kachel=2.4),
              B.stoff('Platte Marmor', (1, 1, 1), 0.10, farbkarte='kueche-marmor-farbe', kachel=1.2),
              B.stoff('Platte Keramik', (1, 1, 1), 0.58, farbkarte='kueche-keramik-farbe', normal='kueche-keramik-normal', normal_staerke=0.12, kachel=1.2)]
    griff = [B.stoff('Griff Messing', (0.88, 0.70, 0.42), 0.30, metall=1.0),
             B.stoff('Griff Edelstahl', (0.63, 0.62, 0.60), 0.26, metall=1.0),
             B.stoff('Griff Schwarz', (0.020, 0.020, 0.021), 0.45, spec=0.4)]
    korpus = B.stoff('Korpus Weiss', (0.70, 0.70, 0.68), 0.45)
    kante = B.stoff('Korpus Kante', (0.62, 0.62, 0.60), 0.40)
    sockel_s = B.stoff('Sockel Schwarz', (0.018, 0.018, 0.019), 0.55)
    zarge = B.stoff('Zarge Anthrazit', (0.060, 0.062, 0.066), 0.35, metall=0.7)
    edelstahl = B.stoff('Edelstahl Becken', (0.62, 0.62, 0.60), 0.22, metall=1.0)
    glas = B.stoff('Kochfeld Glas', (1, 1, 1), 0.04, farbkarte='kueche-kochfeld-farbe', spec=0.5)
    kunst = B.stoff('Siphon', (0.55, 0.55, 0.56), 0.38)
    porzellan = B.stoff('Porzellan', (0.80, 0.80, 0.78), 0.08)
    gummi = B.stoff('Dichtung', (0.02, 0.02, 0.02), 0.7)

    # -------------------------------------------------------------- Sockel und Korpusse
    B.kasten('sockel', 2 * -X0 - 0.12, WAND, SOCKEL - 0.004, [sockel_s], 0.001, (0, Y_K0 + 0.060, 0.0))
    B.kasten('sockel_hinten', 2 * -X0 - 0.12, WAND, SOCKEL - 0.004, [sockel_s], 0.001, (0, Y_K1 - 0.030, 0.0))
    for s_ in (-1, 1):
        B.kasten(f'sockel_seite_{"l" if s_ < 0 else "r"}', WAND, Y_K1 - Y_K0 - 0.09, SOCKEL - 0.004, [sockel_s], 0.001,
                 (s_ * (-X0 - 0.06 - WAND / 2), (Y_K0 + 0.06 + Y_K1 - 0.03) / 2, 0.0))
    ym = (Y_K0 + Y_K1) / 2
    for m in range(4):
        xa = X0 + m * MODUL; xm = xa + MODUL / 2
        for s_, xs in (('l', xa + WAND / 2), ('r', xa + MODUL - WAND / 2)):
            B.kasten(f'korpus_{m}_seite_{s_}', WAND, TIEFE, KORPUS, [korpus], 0.0008, (xs, ym, Z_K0))
        B.kasten(f'korpus_{m}_boden', MODUL - 2 * WAND, TIEFE, WAND, [korpus], 0.0008, (xm, ym, Z_K0))
        B.kasten(f'korpus_{m}_rueck', MODUL - 2 * WAND, 0.008, KORPUS - WAND, [korpus], 0.0, (xm, Y_K1 - 0.004 - 0.010, Z_K0 + WAND))
        for yy in (Y_K0 + 0.05, Y_K1 - 0.05):                         # Traversen oben
            B.kasten(f'korpus_{m}_trav_{"v" if yy < ym else "h"}', MODUL - 2 * WAND, 0.100, WAND, [korpus], 0.0008, (xm, yy, Z_K1 - WAND))
    # Rueckwand der Insel zur Sitzseite (Front wie vorn) und Seitenwangen
    uv_welt(B.kasten('wange_hinten', 2 * -X0, FRONT, KORPUS + SOCKEL - 0.004, [front[0]], 0.0012, (0, Y_K1 + FRONT / 2, 0.004), True), hoch=True)
    for s_ in (-1, 1):
        uv_welt(B.kasten(f'wange_{"l" if s_ < 0 else "r"}', FRONT, Y_K1 + FRONT - Y_FRONT, KORPUS + SOCKEL - 0.004, [front[0]], 0.0012,
                         (s_ * (-X0 + FRONT / 2), (Y_FRONT + Y_K1 + FRONT) / 2, 0.004), True), hoch=True)

    # -------------------------------------------------------------- Griffe
    def stangengriff(name, cx, cz, laenge=0.192):
        teile = []
        stange = B.drehkoerper(name + '_stange', [(0.0, -laenge / 2 - 0.02), (0.0055, -laenge / 2 - 0.0195), (0.006, -laenge / 2 - 0.017),
                                                  (0.006, laenge / 2 + 0.017), (0.0055, laenge / 2 + 0.0195), (0.0, laenge / 2 + 0.02)], 24, [griff[0]])
        stange.rotation_euler = (0, math.radians(90), 0); stange.location = (cx, Y_FRONT - 0.032, cz)
        teile.append(stange)
        for sx in (-1, 1):
            f = B.drehkoerper(f'{name}_fuss_{"l" if sx < 0 else "r"}', [(0.0, 0.0), (0.0045, 0.0), (0.0045, 0.030), (0.0, 0.030)], 16, [griff[0]])
            f.rotation_euler = (math.radians(90), 0, 0); f.location = (cx + sx * laenge / 2, Y_FRONT, cz)
            teile.append(f)
        return teile

    # -------------------------------------------------------------- Auszuege
    def auszug(name, xm, z0, hoehe, zargen_h, breite=MODUL - FUGE):
        """Front + Kasten (Zargen, Boden, Rueckwand) + Griff an einem Empty,
        damit das Web den ganzen Auszug zieht."""
        teile = [B.kasten(name + '_front', breite, FRONT, hoehe, [front[0]], 0.0012, (xm, Y_FRONT + FRONT / 2, z0), True)]
        uv_welt(teile[0], hoch=True)
        ib = MODUL - 2 * WAND - 0.026                              # lichte Breite minus Fuehrungen
        zb = z0 + 0.030
        tiefe = 0.500; y0 = Y_K0 + 0.004; yk = y0 + tiefe / 2
        for sx in (-1, 1):
            teile.append(B.kasten(f'{name}_zarge_{"l" if sx < 0 else "r"}', 0.012, tiefe, zargen_h, [zarge], 0.001, (xm + sx * (ib / 2 - 0.006), yk, zb)))
        teile.append(B.kasten(name + '_boden', ib - 0.024, tiefe - 0.012, 0.016, [korpus], 0.0006, (xm, yk - 0.006, zb)))
        teile.append(B.kasten(name + '_rueck', ib - 0.024, 0.012, zargen_h - 0.02, [zarge], 0.0008, (xm, y0 + tiefe - 0.006, zb)))
        teile.append(B.kasten(name + '_innenfront', ib - 0.024, 0.012, zargen_h - 0.02, [zarge], 0.0008, (xm, y0 + 0.006, zb)))
        teile += stangengriff(name + '_griff', xm, z0 + hoehe - 0.045 if hoehe < 0.2 else z0 + hoehe - 0.06)
        return leer(name, (xm, Y_FRONT, z0), teile)

    fh = KORPUS - 2 * FUGE                                         # Fronthoehe ohne Kopffuge
    # Modul 0: drei Auszuege 180 / 2 x 264
    z = Z_K0 + FUGE
    hs = [0.264, 0.264, fh - 0.528 - 2 * FUGE]
    for i, h in enumerate(hs):
        auszug(f'lade_0_{i}', X0 + MODUL / 2, z, h, 0.09 if h < 0.2 else 0.18)
        z += h + FUGE
    # Modul 3: zwei Topfauszuege unter dem Kochfeld
    z = Z_K0 + FUGE
    for i, h in enumerate((0.400, fh - 0.400 - FUGE)):
        auszug(f'lade_3_{i}', X0 + 3.5 * MODUL, z, h, 0.22 if h > 0.3 else 0.14)
        z += h + FUGE

    # -------------------------------------------------------------- Tueren
    def tuer(name, xa, anschlag):
        """Tuer mit Topfscharnier-Seite anschlag ('l' oder 'r')."""
        breite = MODUL - FUGE
        xm = xa + MODUL / 2
        teile = [B.kasten(name, breite, FRONT, fh, [front[0]], 0.0012, (xm, Y_FRONT + FRONT / 2, Z_K0 + FUGE), True)]
        uv_welt(teile[0], hoch=True)
        # Topfscharniere (innen, zwei)
        for zz in (Z_K0 + 0.10, Z_K1 - 0.14):
            hx = xm - breite / 2 + 0.022 if anschlag == 'l' else xm + breite / 2 - 0.022
            teile.append(B.drehkoerper(f'{name}_topf_{int(zz * 100)}', [(0.0, 0.0), (0.0175, 0.0), (0.0175, 0.003), (0.0, 0.003)], 20, [zarge]))
            teile[-1].rotation_euler = (math.radians(-90), 0, 0); teile[-1].location = (hx, Y_FRONT + FRONT + 0.003, zz)
        gx = xm + breite / 2 - 0.05 if anschlag == 'l' else xm - breite / 2 + 0.05
        st = stangengriff(name + '_griff', gx, Z_K1 - 0.12, 0.128)
        # Stange senkrecht fuer Tueren
        for o in st:
            if o.name.endswith('_stange'):
                o.rotation_euler = (0, 0, 0); o.location = (gx, Y_FRONT - 0.032, Z_K1 - 0.12)
            else:
                dz = 0.064 if o.name.endswith('_r') else -0.064
                o.location = (gx, Y_FRONT, Z_K1 - 0.12 + dz)
        teile += st
        if anschlag == 'l':
            return B.angel(name + '_angel', (xa + FUGE / 2, Y_FRONT, 0), (0, 0, 1), 105.0, teile)
        return B.angel(name + '_angel', (xa + MODUL - FUGE / 2, Y_FRONT, 0), (0, 0, -1), 105.0, teile)

    tuer('tuer_1', X0 + MODUL, 'l')
    tuer('tuer_2', X0 + 2 * MODUL, 'r')
    # Einlegeboden mit Tellern im Schrank 2
    xm2 = X0 + 2.5 * MODUL
    B.kasten('korpus_2_einlegeboden', MODUL - 2 * WAND - 0.002, TIEFE - 0.03, WAND, [korpus], 0.0008, (xm2, ym - 0.01, Z_K0 + 0.36))
    for k in range(6):
        t = B.drehkoerper(f'teller_{k}', [(0.0, 0.0), (0.050, 0.0), (0.055, 0.004), (0.118, 0.012), (0.130, 0.020), (0.128, 0.022),
                                           (0.116, 0.016), (0.055, 0.0085), (0.0, 0.0085)], 48, [porzellan])
        t.location = (xm2 - 0.12, ym - 0.03, Z_K0 + 0.36 + WAND + k * 0.0145)
    for k in range(3):
        sch = B.drehkoerper(f'schale_{k}', [(0.0, 0.0), (0.035, 0.0), (0.040, 0.004), (0.070, 0.045), (0.074, 0.062), (0.071, 0.063),
                                            (0.066, 0.048), (0.036, 0.009), (0.0, 0.009)], 40, [porzellan])
        sch.location = (xm2 + 0.14, ym - 0.03, Z_K0 + 0.36 + WAND + k * 0.018)

    # -------------------------------------------------------------- Arbeitsplatte mit Spuelenausschnitt
    pl = B.kasten('platte', 2 * -X0 + 2 * FRONT, 2 * -Y_VORN, PLATTE, [platte[0]], 0.0015, (0, 0, Z_K1))
    xs = X0 + 1.5 * MODUL                                          # Spuele mittig ueber Modul 1
    ys = Y_VORN + 0.090 + 0.22                                     # Beckenmitte (Rand 90 mm vorn)
    BX, BY = 0.500, 0.400
    sch = B.prisma('schneider', rund_rechteck(BX, BY, 0.012, 5, (xs, ys)), Z_K1 - 0.01, Z_K1 + PLATTE + 0.01, [korpus])
    ausschneiden(pl, sch)
    uv_welt(pl)
    # Unterbaubecken: Mantel (Hohlkasten), Boden mit Gefaelle, Rand unter der Platte
    tief = 0.200
    aussen = rund_rechteck(BX, BY, 0.012, 5)
    V, F = [], []
    ringe = [(0.0, 0.0, 0.0), (0.0, 0.0, -0.005), (0.004, 0.004, -0.02), (0.010, 0.010, -tief + 0.02), (0.022, 0.022, -tief)]
    for dx, dy, dz in ringe:
        for (px, py) in rund_rechteck(BX - 2 * dx, BY - 2 * dy, max(0.004, 0.012 + (0.02 if dz < -tief + 0.03 else 0)), 5):
            V.append((xs + px, ys + py, Z_K1 + dz))
    nr = len(aussen)
    for r in range(len(ringe) - 1):
        for i in range(nr):
            a, b = r * nr + i, r * nr + (i + 1) % nr
            F.append((a, b, b + nr, a + nr))
    V.append((xs, ys, Z_K1 - tief - 0.004)); c = len(V) - 1
    for i in range(nr):
        F.append(((len(ringe) - 1) * nr + i, c, (len(ringe) - 1) * nr + (i + 1) % nr))
    becken = B.netz('becken', V, F, [edelstahl], None, None, True)
    becken.data.uv_layers.new(name='UVMap')
    import bmesh
    bm = bmesh.new(); bm.from_mesh(becken.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.normal_update()
    boden_f = [f for f in bm.faces if len(f.verts) == 3]
    if sum(f.normal.z for f in boden_f) < 0:                       # Innenseite (sichtbar) zeigt nach oben
        for f in bm.faces:
            f.normal_flip()
    bm.to_mesh(becken.data); bm.free()
    so = becken.modifiers.new('dicke', 'SOLIDIFY'); so.thickness = 0.0012; so.offset = -1.0   # Blech nach aussen
    with bpy.context.temp_override(object=becken, active_object=becken, selected_objects=[becken], selected_editable_objects=[becken]):
        bpy.ops.object.modifier_apply(modifier=so.name)
    ablauf = B.drehkoerper('becken_ablauf', [(0.0, -0.001), (0.042, -0.001), (0.044, 0.001), (0.036, 0.0025), (0.0, 0.0015)], 40, [edelstahl])
    ablauf.location = (xs + 0.13, ys + 0.10, Z_K1 - tief - 0.004 + 0.0016)
    # Siphon unter dem Becken
    rohr('siphon', [(xs + 0.13, ys + 0.10, Z_K1 - tief - 0.01), (xs + 0.13, ys + 0.10, Z_K1 - tief - 0.12), (xs + 0.13, ys + 0.16, Z_K1 - tief - 0.17),
                    (xs + 0.13, ys + 0.21, Z_K1 - tief - 0.12), (xs + 0.13, ys + 0.21, Z_K1 - tief - 0.09), (xs + 0.13, Y_K1 - 0.02, Z_K1 - tief - 0.09)],
         0.020, [kunst])

    # -------------------------------------------------------------- Armatur (hinter dem Becken)
    ax_, ay_ = xs, ys + BY / 2 + 0.055
    fuss = B.drehkoerper('armatur_fuss', [(0.0, 0.0), (0.028, 0.0), (0.028, 0.004), (0.024, 0.008), (0.0, 0.008)], 40, [griff[0]])
    fuss.location = (ax_, ay_, Z_K1 + PLATTE)
    koerper = B.drehkoerper('armatur_koerper', [(0.0, 0.0), (0.023, 0.0), (0.023, 0.150), (0.0205, 0.160), (0.0, 0.160)], 40, [griff[0]])
    koerper.location = (ax_, ay_, Z_K1 + PLATTE + 0.008)
    z0_ = Z_K1 + PLATTE + 0.16
    pfad = [(ax_, ay_, z0_ - 0.01), (ax_, ay_, z0_ + 0.12)] + [(p[0], p[1], p[2]) for p in bogen(None, (ax_, ay_ - 0.10, z0_ + 0.12), 0.10, 0, 180, 'yz', 18)[1:]]
    pfad += [(ax_, ay_ - 0.20, z0_ + 0.02)]
    rohr('armatur_auslauf', pfad, 0.0115, [griff[0]])
    luft = B.drehkoerper('armatur_strahlregler', [(0.0, 0.0), (0.0118, 0.0), (0.0118, 0.012), (0.0, 0.012)], 24, [zarge])
    luft.location = (ax_, ay_ - 0.20, z0_ + 0.008)
    hebel = B.kasten('armatur_hebel', 0.008, 0.012, 0.075, [griff[0]], 0.002, (0, 0, 0))
    hebel.rotation_euler = (0, math.radians(-75), 0); hebel.location = (ax_ + 0.022, ay_, z0_ - 0.06)

    # -------------------------------------------------------------- Kochfeld (aufliegend, 800 x 520, Facette)
    kx, ky = 0.72, Y_VORN + 0.050 + 0.26                          # 80 mm Abstand zur Wange
    V = []; F = []
    a_ = rund_rechteck(0.800, 0.520, 0.006, 3); i_ = rund_rechteck(0.788, 0.508, 0.004, 3)
    for (px, py) in a_:
        V.append((kx + px, ky + py, Z_K1 + PLATTE))
    for (px, py) in a_:
        V.append((kx + px, ky + py, Z_K1 + PLATTE + 0.002))
    for (px, py) in i_:
        V.append((kx + px, ky + py, Z_K1 + PLATTE + 0.004))
    n_ = len(a_)
    for r in range(2):
        for i in range(n_):
            a, b = r * n_ + i, r * n_ + (i + 1) % n_
            F.append((a, b, b + n_, a + n_))
    F.append(tuple(range(2 * n_, 3 * n_)))
    F.append(tuple(range(n_ - 1, -1, -1)))
    uv = []
    for f in F:
        uv.append([((V[k][0] - kx) / 0.800 + 0.5, (V[k][1] - ky) / 0.520 + 0.5) for k in f])
    kf = B.netz('kochfeld', V, F, [glas], None, uv, False)

    # -------------------------------------------------------------- Varianten, Export
    zuordnung = {front[0]: front, platte[0]: platte, griff[0]: griff}
    B.exportieren('kueche', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; Kochinsel 2,40 m nach Kuechennorm')
    if not web:
        B.speichern('kueche')
