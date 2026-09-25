"""pr_salon.py -- Friseur & Salon: Bedienplatz mit Friseurstuhl, Spiegel mit
Hinterleuchtung, Ablage und Wandelement; Polster, Metall und Wand waehlbar.
Im Web dreht der Stuhl zum Gast und faehrt hoch; am Abend leuchtet nur der
Spiegel.

Masse nach gaengigen Salonmoebeln: Stuhlfuss 560 mm, Sitzhoehe 500 mm
(hydraulisch +80 mm), Sitz 500 x 460 mm, Lehne 480 mm; Spiegel 660 x 1100 mm,
30 mm vor der Wand, LED-Band dahinter; Ablage 1100 x 280 mm auf 860 mm.

Varianten (KHR_materials_variants): Cognac / Messing / Salbei,
Schwarz / Chrom / Kalk, Samt Petrol / Schwarz matt / Anthrazit.
"""
import math
import numpy as np
import bpy
import bmesh
from mathutils import Vector
import pr_basis as B


def rundkasten(name, gx, gy, gz, r, stoffe_, ort=(0, 0, 0), n=10):
    """Quader gx x gy x gz mit Rundung r an allen Kanten (Polster): Wuerfel-
    gitter, jeder Punkt auf den abgerundeten Koerper projiziert. Mitte unten
    bei ort. UV in Metern nach der Hauptrichtung der Normale."""
    hx, hy, hz = gx / 2, gy / 2, gz / 2
    r = min(r, hx, hy, hz)
    V, F = [], []
    idx = {}

    def punkt(p):
        key = tuple(round(c, 6) for c in p)
        if key not in idx:
            idx[key] = len(V); V.append(p)
        return idx[key]
    t = np.linspace(-1, 1, n + 1)
    for ax in range(3):
        for s in (-1, 1):
            for i in range(n):
                for j in range(n):
                    quad = []
                    for (a, b) in ((i, j), (i + 1, j), (i + 1, j + 1), (i, j + 1)):
                        p = [0.0, 0.0, 0.0]
                        p[ax] = s; p[(ax + 1) % 3] = t[a]; p[(ax + 2) % 3] = t[b]
                        quad.append(punkt(tuple(p)))
                    F.append(tuple(quad) if s > 0 else tuple(quad[::-1]))
    halb = np.array([hx, hy, hz]); innen = halb - r
    W = []
    for p in V:
        q = np.array(p) * halb
        k = np.clip(q, -innen, innen)
        d = q - k; ld = np.linalg.norm(d)
        W.append(tuple(k + (d / ld * r if ld > 1e-9 else d)))
    W = [(x, y, z + hz) for (x, y, z) in W]
    uv = []
    for f in F:
        ps = [np.array(W[k]) for k in f]
        nrm = np.cross(ps[1] - ps[0], ps[2] - ps[0]); a = np.abs(nrm)
        if a[2] >= max(a[0], a[1]):
            uv.append([(p[0], p[1]) for p in ps])
        elif a[1] >= a[0]:
            uv.append([(p[0], p[2]) for p in ps])
        else:
            uv.append([(p[1], p[2]) for p in ps])
    o = B.netz(name, W, F, stoffe_, None, uv, True)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(o.data); bm.free()
    o.location = ort
    return o


def rohr(name, punkte, radius, stoffe_):
    cu = bpy.data.curves.new(name + '_k', 'CURVE'); cu.dimensions = '3D'
    cu.bevel_depth = radius; cu.bevel_resolution = 4; cu.use_fill_caps = True
    sp = cu.splines.new('POLY'); sp.points.add(len(punkte) - 1)
    for i, p in enumerate(punkte):
        sp.points[i].co = (*p, 1.0)
    o = bpy.data.objects.new(name + '_k', cu); bpy.context.scene.collection.objects.link(o)
    bpy.context.view_layer.update()
    me = bpy.data.meshes.new_from_object(o.evaluated_get(bpy.context.evaluated_depsgraph_get()))
    bpy.data.objects.remove(o, do_unlink=True)
    me.name = name; me.materials.clear()
    for s in stoffe_:
        me.materials.append(s)
    for p in me.polygons:
        p.use_smooth = True
    ob = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(ob)
    return ob


def rund_rechteck(bx, by, r, n=8):
    pts = []
    for cx, cy, a0 in ((bx / 2 - r, by / 2 - r, 0), (-bx / 2 + r, by / 2 - r, 90), (-bx / 2 + r, -by / 2 + r, 180), (bx / 2 - r, -by / 2 + r, 270)):
        for k in range(n + 1):
            a = math.radians(a0 + 90 * k / n)
            pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return pts


def bauen(web=False):
    VAR = ['Cognac', 'Schwarz', 'Petrol']
    polster = [B.stoff('Polster Cognac', (0.23, 0.095, 0.038), 0.46, normal='innen-leder-normal', normal_staerke=0.6, kachel=0.08),
               B.stoff('Polster Schwarz', (0.020, 0.020, 0.021), 0.40, normal='innen-leder-normal', normal_staerke=0.6, kachel=0.08),
               B.stoff('Polster Samt Petrol', (0.010, 0.048, 0.054), 0.85, sheen=0.3, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.3, kachel=0.03)]
    # Samt: Glanz nur in der eigenen Farbe (weisser Sheen machte Petrol hellgrau)
    _b = polster[2].node_tree.nodes['Principled BSDF']
    if 'Sheen Tint' in _b.inputs:
        _b.inputs['Sheen Tint'].default_value = (0.10, 0.38, 0.42, 1.0)
    metall = [B.stoff('Metall Messing', (0.86, 0.67, 0.39), 0.28, metall=1.0),
              B.stoff('Metall Chrom', (0.74, 0.74, 0.74), 0.05, metall=1.0),
              B.stoff('Metall Schwarz matt', (0.022, 0.022, 0.023), 0.48, spec=0.4)]
    wand = [B.stoff('Wand Salbei', (0.25, 0.30, 0.25), 0.92, normal='kueche-keramik-normal', normal_staerke=0.15, kachel=1.2),
            B.stoff('Wand Kalk', (0.58, 0.54, 0.48), 0.92, normal='kueche-keramik-normal', normal_staerke=0.15, kachel=1.2),
            B.stoff('Wand Anthrazit', (0.045, 0.046, 0.048), 0.90, normal='kueche-keramik-normal', normal_staerke=0.15, kachel=1.2)]
    spiegel = B.stoff('Spiegel', (0.92, 0.92, 0.92), 0.02, metall=1.0)
    spiegel_kante = B.stoff('Spiegel Kante', (0.30, 0.34, 0.32), 0.1, trans=0.6, ior=1.52)
    led = B.stoff('Spiegel Licht', (1.0, 0.95, 0.88), 0.5, emiss=((1.0, 0.93, 0.84), 60.0))   # sichtbarer Hof an der Wand
    nuss = B.stoff('Ablage Nussbaum', (1, 1, 1), 0.42, farbkarte='holz-kiste-farbe', kachel=0.40)
    gummi = B.stoff('Gummi', (0.018, 0.018, 0.018), 0.7)
    pet_braun = B.stoff('Flasche Braunglas', (0.35, 0.14, 0.03), 0.08, trans=0.9, ior=1.5)
    pet_weiss = B.stoff('Flasche Weiss matt', (0.74, 0.73, 0.70), 0.45)
    kappe = B.stoff('Kappe Schwarz', (0.02, 0.02, 0.02), 0.35)
    kunststoff = B.stoff('Kamm', (0.03, 0.03, 0.03), 0.3)

    # -------------------------------------------------------------- Wandelement, Spiegel, Ablage
    YW = 0.45
    B.kasten('wand', 1.40, 0.05, 2.40, [wand[0]], 0.002, (0, YW + 0.025, 0))
    B.kasten('wand_sockel', 1.40, 0.012, 0.08, [metall[0]], 0.001, (0, YW - 0.006, 0))
    sp = B.prisma('spiegel', rund_rechteck(0.66, 1.10, 0.05), 0, 0.006, [spiegel], fase=0.001)
    sp.rotation_euler = (math.radians(90), 0, 0); sp.location = (0, YW - 0.030, 1.52)
    # Glaskante (sichtbar gruenlich) als duenner Rahmen um den Belag
    B.kasten('spiegel_halter', 0.50, 0.024, 0.90, [kappe], 0.001, (0, YW - 0.012, 1.07))
    ring = B.prisma('spiegel_led', rund_rechteck(0.60, 1.04, 0.04), 0, 0.004, [led])
    ring.rotation_euler = (math.radians(90), 0, 0); ring.location = (0, YW - 0.004, 1.52)
    lp = bpy.data.objects.new('spiegellicht', None); bpy.context.scene.collection.objects.link(lp)
    lp.location = (0, YW - 0.010, 1.52)
    B.kasten('ablage', 1.10, 0.28, 0.040, [nuss], 0.003, (0, YW - 0.14, 0.82), False)
    for sx in (-1, 1):
        B.kasten(f'ablage_konsole_{"l" if sx < 0 else "r"}', 0.012, 0.20, 0.10, [metall[0]], 0.001, (sx * 0.45, YW - 0.10, 0.72))
    # Produkte auf der Ablage
    def flasche(name, x, h, r, stoff_, pumpe=False, spray=False):
        b = B.drehkoerper(name, [(0.0, 0.0), (r, 0.0), (r + 0.001, 0.003), (r + 0.001, h * 0.82), (r * 0.8, h * 0.93), (r * 0.42, h * 0.97),
                                 (r * 0.42, h), (0.0, h)], 40, [stoff_])
        b.location = (x, YW - 0.12, 0.86)
        k = B.drehkoerper(name + '_kappe', [(0.0, 0.0), (r * 0.48, 0.0), (r * 0.48, 0.022), (r * 0.30, 0.026), (0.0, 0.026)], 24, [kappe])
        k.location = (x, YW - 0.12, 0.86 + h)
        if pumpe:
            st = B.drehkoerper(name + '_pumpe', [(0.0, 0.0), (0.004, 0.0), (0.004, 0.035), (0.0, 0.035)], 12, [kappe])
            st.location = (x, YW - 0.12, 0.86 + h + 0.026)
            kopf = B.kasten(name + '_kopf', 0.014, 0.040, 0.012, [kappe], 0.003, (x, YW - 0.12 - 0.012, 0.86 + h + 0.058))
        if spray:
            kopf = B.kasten(name + '_kopf', 0.018, 0.022, 0.024, [kappe], 0.004, (x, YW - 0.12, 0.86 + h + 0.022))
    flasche('flasche_0', -0.40, 0.19, 0.030, pet_braun, pumpe=True)
    flasche('flasche_1', -0.32, 0.17, 0.028, pet_braun, pumpe=True)
    flasche('flasche_2', -0.245, 0.21, 0.024, pet_weiss, spray=True)
    # Kamm (flach, Zinken)
    zinken = [(0.0, 0.0)]
    for k in range(34):
        x0 = 0.004 + k * 0.0052
        zinken += [(x0, 0.0), (x0, -0.020), (x0 + 0.0026, -0.020), (x0 + 0.0026, 0.0)]
    zinken += [(0.184, 0.0), (0.184, 0.012), (0.0, 0.012)]
    km = B.prisma('kamm', zinken[::-1], 0.0, 0.004, [kunststoff], fase=0.0005, segmente=1)
    km.rotation_euler = (0, 0, math.radians(8)); km.location = (0.18, YW - 0.20, 0.86)
    # Rundbuerste: Stiel + Borstenkoerper
    rb = B.drehkoerper('buerste', [(0.0, 0.0), (0.012, 0.0), (0.014, 0.10), (0.022, 0.105), (0.024, 0.20), (0.022, 0.205), (0.0, 0.205)], 32, [kunststoff])
    rb.rotation_euler = (0, math.radians(90), math.radians(-12)); rb.location = (0.28, YW - 0.12, 0.86 + 0.024)

    # -------------------------------------------------------------- Friseurstuhl (Blick zum Spiegel: +y)
    YS = -0.62
    B.drehkoerper('stuhl_fuss', [(0.0, 0.0), (0.278, 0.0), (0.280, 0.004), (0.276, 0.016), (0.260, 0.022), (0.080, 0.030),
                                 (0.0, 0.030)], 96, [metall[0]]).location = (0, YS, 0)
    B.drehkoerper('stuhl_saeule', [(0.0, 0.030), (0.055, 0.030), (0.055, 0.300), (0.050, 0.305), (0.0, 0.305)], 48, [metall[0]]).location = (0, YS, 0)
    B.drehkoerper('stuhl_manschette', [(0.0, 0.0), (0.057, 0.0), (0.057, 0.012), (0.0, 0.012)], 48, [gummi]).location = (0, YS, 0.30)
    # Pumpenhebel seitlich
    rohr('stuhl_pumphebel', [(0.055, YS, 0.12), (0.14, YS, 0.10), (0.20, YS - 0.04, 0.07)], 0.009, [metall[0]])
    B.kasten('stuhl_pumppedal', 0.07, 0.05, 0.012, [gummi], 0.004, (0.225, YS - 0.05, 0.058))
    oben = []
    oben.append(B.drehkoerper('stuhl_kolben', [(0.0, 0.0), (0.032, 0.0), (0.032, 0.150), (0.0, 0.150)], 32, [metall[0]]))
    oben[-1].location = (0, YS, 0.30)
    oben.append(B.kasten('stuhl_sitzplatte', 0.42, 0.40, 0.020, [metall[0]], 0.004, (0, YS, 0.44)))
    oben.append(rundkasten('stuhl_sitz', 0.50, 0.46, 0.100, 0.045, [polster[0]], (0, YS, 0.46)))
    # Lehne: leicht nach hinten geneigt, am Sitz hinten (Richtung -y)
    le = rundkasten('stuhl_lehne', 0.48, 0.10, 0.50, 0.045, [polster[0]], (0, 0, 0))
    le.rotation_euler = (math.radians(10), 0, 0); le.location = (0, YS - 0.21, 0.56)          # Oberkante nach hinten
    oben.append(le)
    oben.append(B.kasten('stuhl_lehnenbuegel', 0.30, 0.020, 0.16, [metall[0]], 0.003, (0, YS - 0.235, 0.46)))
    # Armlehnen: Polster auf Buegeln
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        oben.append(rundkasten(f'stuhl_arm_{sl}', 0.075, 0.40, 0.055, 0.022, [polster[0]], (sx * 0.29, YS + 0.01, 0.66)))
        oben.append(rohr(f'stuhl_armbuegel_{sl}', [(sx * 0.20, YS + 0.12, 0.46), (sx * 0.29, YS + 0.14, 0.52), (sx * 0.29, YS + 0.14, 0.66)], 0.011, [metall[0]]))
        oben.append(rohr(f'stuhl_armbuegel_h_{sl}', [(sx * 0.20, YS - 0.14, 0.46), (sx * 0.29, YS - 0.16, 0.52), (sx * 0.29, YS - 0.16, 0.66)], 0.011, [metall[0]]))
    # Fussstuetze vorn (Richtung Spiegel)
    oben.append(rohr('stuhl_fussstuetze', [(-0.20, YS + 0.36, 0.20), (-0.16, YS + 0.40, 0.20), (0.16, YS + 0.40, 0.20), (0.20, YS + 0.36, 0.20)], 0.012, [metall[0]]))
    oben.append(rohr('stuhl_fussstuetze_arm', [(0.0, YS + 0.04, 0.44), (0.0, YS + 0.22, 0.34), (0.0, YS + 0.40, 0.20)], 0.014, [metall[0]]))
    B.angel('stuhl_angel', (0, YS, 0), (0, 0, 1), 150.0, oben)

    zuordnung = {polster[0]: polster, metall[0]: metall, wand[0]: wand}
    B.exportieren('salon', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; Bedienplatz mit Friseurstuhl')
    if not web:
        B.speichern('salon')
