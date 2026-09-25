"""pr_gastro.py -- Gastronomie: gedeckter Tisch fuer zwei, Tischdecke und
Geschirr waehlbar; im Web wird es Abend (die Kerze traegt das Licht).

Masse: Restauranttisch 80 x 80 cm, Hoehe 75 cm, Eiche. Tischdecke 124 x
124 cm (22 cm Ueberhang), Leinen. Gedeck nach Gastronomie-Grundregel:
Speiseteller 27 cm (2 cm von der Tischkante), darauf Vorspeisenteller 21 cm
mit Serviette, Brotteller 16 cm links oben mit Buttermesser; Gabel links,
Messer (Schneide innen) und Loeffel rechts, Dessertloeffel oben;
Weinglas ueber dem Messer, Wasserglas rechts daneben. Mitte: Kerze im
Messingleuchter, Salz- und Pfeffermuehle.

Varianten (KHR_materials_variants): Leinen weiss / Porzellan weiss,
Leinen terrakotta / Steingut sand, Leinen anthrazit / Steingut schwarz.
"""
import math
import numpy as np
import bpy
import bmesh
from mathutils import Vector
import pr_basis as B

A = 0.40                  # halbe Tischbreite
H_T = 0.750               # Tischoberkante
H = H_T + 0.0012          # Oberseite der Decke
C = 0.62                  # halbe Deckenbreite
R_K = 0.006               # Rundung ueber der Tischkante


def _kante(d):
    """Weg d von der Tischkante nach aussen -> (seitlicher Versatz, Absenkung)."""
    bogen = R_K * math.pi / 2
    if d <= bogen:
        t = d / R_K
        return R_K * math.sin(t), R_K * (1 - math.cos(t))
    return R_K, R_K + (d - bogen)


def decke(name, stoffe_, n=124):
    """Tischdecke als verformtes Gitter: flach auf dem Tisch, ueber die
    Kante gerundet, an den Seiten mit leichten Wellen, an den Ecken als
    Kegel mit Falten (dort ist mehr Stoff als Umfang)."""
    us = np.linspace(-C, C, n + 1)
    V, F, UV = [], [], []
    rng = np.random.default_rng(3)
    ph = rng.uniform(0, 2 * np.pi, 8)
    for j, v in enumerate(us):
        for i, u in enumerate(us):
            dx, dy = abs(u) - A, abs(v) - A
            sx, sy = (1 if u >= 0 else -1), (1 if v >= 0 else -1)
            if dx <= 0 and dy <= 0:
                # Buegelfalten: gefaltet gelagert -> feine Kanten im Kreuz und in den Vierteln
                falte = 0.0
                for f0, st in ((0.0, 0.0009), (C / 2, 0.0005), (-C / 2, 0.0005)):
                    falte += st * (math.exp(-((u - f0) / 0.004) ** 2) + math.exp(-((v - f0) / 0.004) ** 2))
                p = (u, v, H + falte)
            elif dy <= 0:
                q, s = _kante(dx)
                w = 0.005 * min(1.0, dx / 0.18) * math.sin(2 * math.pi * v / 0.21 + ph[0 if u > 0 else 1]) * min(1.0, (A - abs(v)) / 0.08)
                p = (sx * (A + q + w), v, H - s)
            elif dx <= 0:
                q, s = _kante(dy)
                w = 0.005 * min(1.0, dy / 0.18) * math.sin(2 * math.pi * u / 0.23 + ph[2 if v > 0 else 3]) * min(1.0, (A - abs(u)) / 0.08)
                p = (u, sy * (A + q + w), H - s)
            else:
                d = max(dx, dy)
                phi = math.atan2(dy, dx)
                q, s = _kante(d)
                ecke = 0.34 * d * math.sin(2 * phi) ** 2 * (1 + 0.30 * math.sin(8 * phi + ph[4]))
                r = q + ecke
                p = (sx * (A + r * math.cos(phi)), sy * (A + r * math.sin(phi)), H - s)
            V.append(p)
            UV.append((u, v))
    m = n + 1
    for j in range(n):
        for i in range(n):
            a = j * m + i
            F.append((a, a + 1, a + m + 1, a + m))
    uvs = [[UV[k] for k in f] for f in F]
    o = B.netz(name, V, F, stoffe_, None, uvs, True)
    so = o.modifiers.new('dicke', 'SOLIDIFY'); so.thickness = 0.0011; so.offset = -1.0
    with bpy.context.temp_override(object=o, active_object=o, selected_objects=[o], selected_editable_objects=[o]):
        bpy.ops.object.modifier_apply(modifier=so.name)
    return o


def teller(name, r, h, spiegel, stoffe_, ort, n=64, t=0.0038):
    """Teller als geschlossenes Profil: Standring, Spiegel (flacher Boden),
    Fahne bis zum Rand, Wandstaerke t."""
    fr = r * 0.62            # Standring
    unten = [(0.0, 0.0012), (fr - 0.004, 0.0012), (fr - 0.002, 0.0), (fr + 0.001, 0.0), (fr + 0.003, 0.0022),
             (spiegel, 0.0030), (r * 0.94, h - 0.0045), (r, h)]
    oben = [(r - 0.0012, h + 0.0006), (r * 0.93, h - 0.0012), (spiegel - 0.004, 0.0064), (0.0, 0.0060)]
    pr_ = unten + oben
    o = B.drehkoerper(name, pr_, n, stoffe_)
    o.location = ort
    return o


def flach(name, umriss, dicke, stoffe_, ort, bogen=None, dreh=0.0):
    """Flaches Besteckteil (Umriss in x/y, Laenge laengs y), mit Fase; bogen =
    (y_mitte, breite, hoehe): Hals hebt sich vom Tisch."""
    o = B.prisma(name, umriss, 0.0, dicke, stoffe_, fase=min(0.0008, dicke * 0.35), segmente=2, glatt_winkel=40)
    if bogen:
        ym, br, hh = bogen
        for v in o.data.vertices:
            v.co.z += hh * math.exp(-((v.co.y - ym) / br) ** 2)
        o.data.update()
    o.rotation_euler = (0, 0, dreh); o.location = ort
    return o


def gabel_umriss():
    """Tafelgabel 205 mm: Griff, Hals, vier Zinken (von unten nach oben = +y)."""
    p = [(0.0105, 0.000), (0.0120, 0.020), (0.0115, 0.070), (0.0060, 0.110), (0.0045, 0.125), (0.0110, 0.150), (0.0125, 0.165)]
    zinken = []
    xs = np.linspace(0.0125, -0.0125, 8)
    for k in range(4):
        x0, x1 = xs[2 * k], xs[2 * k + 1]
        zinken += [(x0, 0.205 - 0.0 * k), ((x0 + x1) / 2, 0.206), (x1, 0.205)]
        if k < 3:
            zinken += [(x1 - 0.0005, 0.168), (xs[2 * k + 2] + 0.0005, 0.168)]
    links = [(-x, y) for (x, y) in p[::-1]]
    return [(x, y) for (x, y) in p] + zinken + links[1:]


def messer_umriss():
    """Tafelmesser 230 mm: Griff mit leichter Taille, Klinge mit Rundung."""
    return [(0.0090, 0.000), (0.0105, 0.020), (0.0095, 0.060), (0.0085, 0.100), (0.0060, 0.110), (0.0085, 0.125),
            (0.0100, 0.190), (0.0080, 0.222), (0.0020, 0.230), (-0.0060, 0.226), (-0.0080, 0.200), (-0.0065, 0.125),
            (-0.0055, 0.110), (-0.0080, 0.100), (-0.0090, 0.060), (-0.0098, 0.020), (-0.0085, 0.000)]


def loeffel(name, stoffe_, ort, laenge=0.200, dreh=0.0):
    """Loeffel: flacher Stiel + Laffe als Kugelkappe (elliptisch gestreckt)."""
    stiel = [(0.0100, 0.000), (0.0115, 0.020), (0.0100, 0.070), (0.0045, 0.115), (0.0035, 0.128), (-0.0035, 0.128),
             (-0.0045, 0.115), (-0.0100, 0.070), (-0.0115, 0.020), (-0.0100, 0.000)]
    s = laenge / 0.200
    st = B.prisma(name + '_stiel', [(x * s, y * s) for x, y in stiel], 0.0, 0.0025, stoffe_, fase=0.0007, segmente=2)
    for v in st.data.vertices:
        v.co.z += 0.009 * math.exp(-((v.co.y - 0.118 * s) / (0.03 * s)) ** 2)
    st.data.update()
    # Laffe: Schale als Rotationskoerper, danach in y gestreckt
    rr = 0.0205 * s
    prof = [(0.0, 0.0), (rr * 0.6, 0.0012), (rr * 0.92, 0.0045), (rr, 0.0085), (rr - 0.0012, 0.0090), (rr * 0.9, 0.0055),
            (rr * 0.55, 0.0028), (0.0, 0.0017)]
    la = B.drehkoerper(name + '_laffe', prof, 40, stoffe_)
    la.scale = (1.0, 1.45, 1.0); la.location = (0, 0.128 * s + rr * 1.45 - 0.002, 0.0)
    bpy.context.view_layer.update()
    for o in (st, la):
        o.select_set(True)
    teile = [st, la]
    e = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(e)
    for o in teile:
        o.parent = e
    e.rotation_euler = (0, 0, dreh); e.location = ort
    return e


def glas_wein(name, stoffe_, ort):
    aussen = [(0.0, 0.0), (0.038, 0.0), (0.0392, 0.0015), (0.037, 0.0035), (0.008, 0.0065), (0.0036, 0.012), (0.0035, 0.092),
              (0.009, 0.104), (0.029, 0.124), (0.041, 0.152), (0.0432, 0.174), (0.0405, 0.200), (0.0342, 0.2215), (0.0336, 0.2222)]
    innen = [(0.0328, 0.2216), (0.0395, 0.200), (0.0420, 0.174), (0.0398, 0.153), (0.0282, 0.126), (0.0085, 0.1075), (0.0, 0.1062)]
    o = B.drehkoerper(name, aussen + innen, 64, stoffe_)
    o.location = ort
    return o


def glas_wasser(name, stoffe_, ort):
    pr_ = [(0.0, 0.0), (0.0335, 0.0), (0.0345, 0.0025), (0.0372, 0.098), (0.0368, 0.0990), (0.0358, 0.0985), (0.0332, 0.0085), (0.0, 0.0075)]
    o = B.drehkoerper(name, pr_, 64, stoffe_)
    o.location = ort
    return o


def serviette_kissen(name, stoffe_, bx=0.090, by=0.140, h=0.009, n=18):
    """Gefaltete Stoffserviette: weiches Kissen mit runden Kanten (oben
    gewoelbt, unten flach), Stoffrichtung in UV (Meter)."""
    V, F, UV = [], [], []
    m = n + 1
    for lage, zs in ((0, 1), (1, 0)):
        for j in range(m):
            for i in range(m):
                x = (i / n - 0.5) * bx; y = (j / n - 0.5) * by
                ex = 1 - abs(2 * i / n - 1) ** 6; ey = 1 - abs(2 * j / n - 1) ** 6
                z = h * (ex * ey) ** 0.35 if zs else 0.0
                V.append((x, y, z)); UV.append((x, y))
    for lage in range(2):
        o_ = lage * m * m
        for j in range(n):
            for i in range(n):
                a = o_ + j * m + i
                q = (a, a + 1, a + m + 1, a + m)
                F.append(q if lage == 0 else q[::-1])
    uvs = [[UV[k] for k in f] for f in F]
    o = B.netz(name, V, F, stoffe_, None, uvs, True)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-6)       # Rand oben/unten verschmelzen
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(o.data); bm.free()
    return o


# ====================================================================== Speisen
# Wunsch B3 (24.09.2026): "Appetit verkauft" -- angerichtete Teller statt
# nur Gedeck. Nur fuers Web (nur_web): Im Foto bleibt der Tisch gedeckt, im
# Web kommt der Gang beim Servieren von oben auf den Teller.

def essen_stoff(name, farbe, rau, sss=0.0, radius=(0.004, 0.002, 0.001), coat=0.0, spec=0.5):
    m = B.stoff(name, farbe, rau, coat=coat, coat_rau=0.05, spec=spec)
    b = m.node_tree.nodes['Principled BSDF']
    if sss:
        b.inputs['Subsurface Weight'].default_value = sss
        b.inputs['Subsurface Radius'].default_value = radius
    return m


def spaghetti(name, ort, stoff_, web, n=150, seed=1):
    """Spaghetti-Nest, mit der Zange gedreht: alle Straenge laufen im selben
    Drehsinn um die Mitte, aussen flach, innen zu einer Kuppe (Hoehe 3 cm,
    Durchmesser 11 cm). 1,7 mm stark wie Spaghetti n. 5."""
    rng = np.random.default_rng(seed)
    cu = bpy.data.curves.new(name, 'CURVE'); cu.dimensions = '3D'
    cu.bevel_depth = 0.00085; cu.bevel_resolution = 1 if web else 3; cu.resolution_u = 2 if web else 4
    for k in range(n):
        sp = cu.splines.new('POLY'); m = 44
        sp.points.add(m - 1)
        th0 = rng.uniform(0, 2 * np.pi); dreh = rng.uniform(1.1, 2.1) * 2 * np.pi
        r0 = rng.uniform(0.004, 0.050); dr = rng.uniform(-0.012, 0.014); ph = rng.uniform(0, 2 * np.pi)
        lage = rng.uniform(0, 1)
        for i in range(m):
            t = i / (m - 1)
            r = min(0.054, max(0.002, r0 + dr * t + 0.004 * math.sin(3 * math.pi * t + ph)))
            th = th0 + dreh * t
            kuppe = 0.030 * max(0.0, 1 - (r / 0.057) ** 2) ** 0.8
            z = kuppe * (0.55 + 0.45 * lage) + 0.0018 * math.sin(7 * t + ph) + 0.0009
            sp.points[i].co = (r * math.cos(th), r * math.sin(th), max(0.0009, z), 1)
    o = bpy.data.objects.new(name, cu); bpy.context.scene.collection.objects.link(o)
    cu.materials.append(stoff_)
    o.location = ort
    bpy.context.view_layer.objects.active = o; o.select_set(True)
    with bpy.context.temp_override(active_object=o, selected_objects=[o], selected_editable_objects=[o]):
        bpy.ops.object.convert(target='MESH')
    o.select_set(False)
    return o


def blob(name, radius, hoehe, stoff_, ort, seed, n=48, rauh=0.18):
    """Weicher, unregelmaessiger Klecks (Sugo, Coulis): flache Halbkugel,
    Rand verrauscht."""
    rng = np.random.default_rng(seed)
    bpy.ops.mesh.primitive_uv_sphere_add(segments=n, ring_count=n // 2, radius=1.0, location=(0, 0, 0))
    o = bpy.context.object; o.name = name
    ph = rng.uniform(0, 2 * np.pi, 6)
    for v in o.data.vertices:
        x, y, z = v.co
        a = math.atan2(y, x)
        w = 1 + rauh * (0.5 * math.sin(3 * a + ph[0]) + 0.3 * math.sin(5 * a + ph[1]) + 0.2 * math.sin(9 * a + ph[2]))
        v.co = (x * radius * w, y * radius * w, max(0.0, z) * hoehe * (1 + 0.15 * math.sin(4 * a + ph[3])))
    for poly in o.data.polygons:
        poly.use_smooth = True
    o.data.materials.append(stoff_)
    o.location = ort
    return o


def blatt(name, laenge, breite, stoff_, ort, dreh, neig=0.25):
    """Blatt (Basilikum, Minze): spitz zulaufend, Mittelrippe gefaltet, leicht gewoelbt."""
    nu, nv = 14, 6; V, F, UV = [], [], []
    for i in range(nu + 1):
        u = i / nu
        w = breite / 2 * math.sin(math.pi * min(1, u * 1.05)) ** 0.8
        for j in range(nv + 1):
            v = j / nv * 2 - 1
            x = u * laenge; y = v * w
            z = 0.25 * abs(y) * (1 - u) + 0.08 * laenge * math.sin(math.pi * u) - 0.35 * y * y / max(breite, 1e-4)
            V.append((x, y, z)); UV.append((u, (v + 1) / 2))
    for i in range(nu):
        for j in range(nv):
            a = i * (nv + 1) + j
            F.append((a, a + nv + 1, a + nv + 2, a + 1))
    o = B.netz(name, V, F, [stoff_])
    o.rotation_euler = (neig, 0, dreh); o.location = ort
    mod = o.modifiers.new('dicke', 'SOLIDIFY'); mod.thickness = 0.0004
    return o


def himbeere(name, stoff_, ort, seed):
    """Himbeere aus Steinfruechtchen: 44 Kuegelchen auf einem Kegelmantel."""
    rng = np.random.default_rng(seed)
    bm = bmesh.new()
    for k in range(44):
        t = (k + 0.5) / 44
        h = 0.017 * t ** 0.9
        rr = 0.0095 * math.sqrt(max(0.0, 1 - (h / 0.019) ** 2)) + 0.001
        a = k * 2.39996 + rng.uniform(-0.1, 0.1)
        m = bmesh.ops.create_uvsphere(bm, u_segments=10, v_segments=6, radius=0.0024 + rng.uniform(0, 0.0004))
        for v in m['verts']:
            v.co += Vector((rr * math.cos(a), rr * math.sin(a), 0.003 + h))
    me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
    for poly in me.polygons:
        poly.use_smooth = True
    o = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(o)
    me.materials.append(stoff_)
    o.location = ort; o.rotation_euler = (math.radians(rng.uniform(70, 100)), 0, rng.uniform(0, 6.28))
    return o


def bauen(web=False):
    VAR = ['Weiss', 'Terrakotta', 'Anthrazit']
    leinen = [B.stoff('Leinen Weiss', (0.74, 0.73, 0.70), 0.72, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03),
              B.stoff('Leinen Terrakotta', (0.30, 0.085, 0.042), 0.74, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03),
              B.stoff('Leinen Anthrazit', (0.026, 0.027, 0.029), 0.72, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03)]
    serviette = [B.stoff('Serviette Weiss', (0.76, 0.75, 0.72), 0.72, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03),
                 B.stoff('Serviette Sand', (0.62, 0.55, 0.44), 0.74, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03),
                 B.stoff('Serviette Terrakotta', (0.30, 0.085, 0.042), 0.74, spec=0.2, normal='innen-stoff-normal', normal_staerke=0.5, kachel=0.03)]
    geschirr = [B.stoff('Porzellan Weiss', (0.80, 0.80, 0.78), 0.05, coat=0.3, coat_rau=0.02),
                B.stoff('Steingut Sand', (0.56, 0.48, 0.37), 0.22, coat=0.2, coat_rau=0.10),
                B.stoff('Steingut Schwarz', (0.030, 0.030, 0.032), 0.28, coat=0.2, coat_rau=0.12)]
    besteck = B.stoff('Besteck Edelstahl', (0.63, 0.62, 0.60), 0.16, metall=1.0)
    glas = B.stoff('Glas Kristall', (1, 1, 1), 0.0, trans=1.0, ior=1.52)
    messing = B.stoff('Leuchter Messing', (0.86, 0.66, 0.38), 0.24, metall=1.0)
    wachs = B.stoff('Kerze Wachs', (0.82, 0.80, 0.74), 0.45)
    flamme = B.stoff('Kerze Flamme', (1.0, 0.62, 0.28), 0.5, emiss=((1.0, 0.55, 0.20), 12.0))
    docht = B.stoff('Kerze Docht', (0.02, 0.02, 0.02), 0.8)
    holz = B.stoff('Muehle Nussbaum', (1, 1, 1), 0.45, farbkarte='holz-kiste-farbe', kachel=0.40)
    eiche = B.stoff('Tisch Eiche', (1, 1, 1), 0.50, farbkarte='kueche-eiche-farbe', normal='kueche-eiche-normal', normal_staerke=0.4, kachel=2.4)

    # -------------------------------------------------------------- Tisch
    B.kasten('tisch_platte', 2 * A, 2 * A, 0.030, [eiche], 0.002, (0, 0, H_T - 0.030))
    for sx in (-1, 1):
        for sy in (-1, 1):
            bein = B.kasten(f'tisch_bein_{sx}_{sy}', 0.045, 0.045, H_T - 0.030, [eiche], 0.0015, (sx * (A - 0.055), sy * (A - 0.055), 0.0), True)
            for v in bein.data.vertices:                       # nach unten auf 32 mm verjuengt
                f = 1 - 0.29 * (1 - v.co.z / (H_T - 0.030))
                v.co.x *= f; v.co.y *= f
            bein.data.update()
    for s_ in (-1, 1):
        B.kasten(f'tisch_zarge_x_{s_}', 2 * A - 0.14, 0.022, 0.080, [eiche], 0.001, (0, s_ * (A - 0.065), H_T - 0.110), True)
        B.kasten(f'tisch_zarge_y_{s_}', 0.022, 2 * A - 0.14, 0.080, [eiche], 0.001, (s_ * (A - 0.065), 0, H_T - 0.110), True)

    decke('decke', [leinen[0]], 80 if web else 124)             # Web: 1,5-cm-Raster, bleibt unvereinfacht

    # -------------------------------------------------------------- Gedecke (vorn -y, hinten +y)
    for k, sy in enumerate((-1, 1)):
        rot = 0.0 if sy < 0 else math.pi
        def P(x, y, z=0.0):
            # lokale Gedeck-Koordinaten (Gast schaut nach +y) -> Tisch
            return (x if sy < 0 else -x, sy * (A - y) if True else 0, H + z)
        yk = 0.020 + 0.135                                    # Tellermitte: 2 cm von der Kante
        teller(f'teller_gross_{k}', 0.135, 0.022, 0.100, [geschirr[0]], P(0.0, yk))
        teller(f'teller_vorspeise_{k}', 0.105, 0.020, 0.075, [geschirr[0]], P(0.0, yk, 0.0060))
        # Serviette gefaltet auf dem Vorspeisenteller
        sv = serviette_kissen(f'serviette_{k}', [serviette[0]])
        sv.rotation_euler = (0, 0, rot + (0.25 if sy < 0 else -0.25)); sv.location = P(0.0, yk, 0.0120)
        teller(f'teller_brot_{k}', 0.080, 0.014, 0.058, [geschirr[0]], P(-0.240, yk + 0.130))
        # Besteck: Gabel links, Messer und Loeffel rechts, Dessertloeffel oben
        flach(f'gabel_{k}', gabel_umriss(), 0.0025, [besteck], P(-0.160, 0.030), (0.118, 0.03, 0.008), rot)
        flach(f'messer_{k}', messer_umriss(), 0.0030, [besteck], P(0.160, 0.030), (0.105, 0.03, 0.006), rot)
        loeffel(f'loeffel_{k}', [besteck], P(0.195, 0.030), 0.200, rot)
        # Dessertloeffel oben quer, Griff zur rechten Hand des Gastes
        loeffel(f'dessertloeffel_{k}', [besteck], P(0.085, yk + 0.155), 0.170, math.pi / 2 if sy < 0 else -math.pi / 2)
        # Buttermesser quer ueber dem Brotteller, liegt auf dem Rand auf
        flach(f'buttermesser_{k}', [(x * 0.75, y * 0.75) for x, y in messer_umriss()], 0.0025, [besteck],
              P(-0.240 - 0.086, yk + 0.130 + 0.030, 0.0146), None, -math.pi / 2 if sy < 0 else math.pi / 2)
        glas_wein(f'weinglas_{k}', [glas], P(0.150, yk + 0.140))
        glas_wasser(f'wasserglas_{k}', [glas], P(0.240, yk + 0.095))

    # -------------------------------------------------------------- Speisen (nur Web)
    pasta = essen_stoff('Pasta', (0.70, 0.47, 0.17), 0.42, sss=0.3, radius=(0.003, 0.002, 0.0008), coat=0.12)
    sugo = essen_stoff('Sugo', (0.28, 0.018, 0.008), 0.22, sss=0.25, radius=(0.004, 0.001, 0.0005), coat=0.12)
    tomate = essen_stoff('Tomate', (0.42, 0.03, 0.015), 0.25, sss=0.3, coat=0.15)
    basil = essen_stoff('Basilikum', (0.035, 0.16, 0.02), 0.42, sss=0.25, radius=(0.001, 0.003, 0.0005))
    parm = essen_stoff('Parmesan', (0.86, 0.78, 0.52), 0.62, sss=0.2)
    panna = essen_stoff('Panna cotta', (0.92, 0.89, 0.80), 0.24, sss=0.55, radius=(0.006, 0.005, 0.004), coat=0.3)
    coulis = essen_stoff('Coulis', (0.20, 0.004, 0.03), 0.10, sss=0.3, coat=0.3)
    beere = essen_stoff('Himbeere', (0.48, 0.02, 0.07), 0.30, sss=0.45, radius=(0.004, 0.001, 0.001))
    minze = essen_stoff('Minze', (0.05, 0.22, 0.04), 0.4, sss=0.2)
    neu = []
    for k, sy in enumerate((-1, 1)):
        x0, y0 = 0.0, sy * (A - 0.155)
        z0 = H + 0.0062
        # Hauptgang: Spaghetti al pomodoro
        neu.append(spaghetti(f'gang1_{k}_pasta', (x0, y0, z0), pasta, web, seed=11 + k))
        neu.append(blob(f'gang1_{k}_sugo', 0.024, 0.012, sugo, (x0 + 0.004, y0 - 0.003, z0 + 0.022), seed=21 + k))
        rng = np.random.default_rng(31 + k)
        for i in range(7):
            a = rng.uniform(0, 6.28); rr = rng.uniform(0.006, 0.020)
            neu.append(blob(f'gang1_{k}_tomate_{i}', 0.0045, 0.004, tomate, (x0 + 0.004 + rr * math.cos(a), y0 - 0.003 + rr * math.sin(a), z0 + 0.029), seed=40 + i + 10 * k, n=16, rauh=0.3))
        for i in range(2):
            neu.append(blatt(f'gang1_{k}_basilikum_{i}', 0.034, 0.019, basil, (x0 + 0.006 - 0.012 * i, y0 + 0.004 + 0.006 * i, z0 + 0.034), 0.8 + 2.2 * i, 0.3))
        for i in range(9):
            a = rng.uniform(0, 6.28); rr = rng.uniform(0.004, 0.030)
            f = blob(f'gang1_{k}_parmesan_{i}', 0.0035, 0.0008, parm, (x0 + rr * math.cos(a), y0 + rr * math.sin(a), z0 + 0.030 - rr * 0.35), seed=60 + i, n=10, rauh=0.45)
            f.rotation_euler = (rng.uniform(-0.6, 0.6), rng.uniform(-0.6, 0.6), a); neu.append(f)
        # Dessert: Panna cotta mit Himbeer-Coulis, Himbeeren, Minze -- auf eigenem Teller
        neu.append(teller(f'gang2_{k}_teller', 0.105, 0.020, 0.075, [geschirr[0]], (x0, y0, H + 0.0062)))
        zd = H + 0.0062 + 0.0060
        pc = B.drehkoerper(f'gang2_{k}_panna', [(0.0, 0.0), (0.031, 0.0), (0.0315, 0.0015), (0.0292, 0.036), (0.0270, 0.0405),
                                                 (0.0225, 0.0425), (0.0, 0.0430)], 64, [panna])
        pc.location = (x0 - 0.008, y0 + 0.004, zd); neu.append(pc)
        neu.append(blob(f'gang2_{k}_coulis', 0.026, 0.004, coulis, (x0 - 0.008, y0 + 0.004, zd + 0.0415), seed=80 + k))
        neu.append(blob(f'gang2_{k}_spiegel', 0.030, 0.0015, coulis, (x0 + 0.030, y0 - 0.020, zd + 0.0002), seed=90 + k, rauh=0.35))
        for i, (dx, dy) in enumerate(((0.036, 0.010), (0.044, -0.008), (0.030, -0.030))):
            neu.append(himbeere(f'gang2_{k}_himbeere_{i}', beere, (x0 + dx, y0 + dy, zd + 0.009), seed=100 + i + 7 * k))
        neu.append(blatt(f'gang2_{k}_minze', 0.024, 0.014, minze, (x0 - 0.012, y0 + 0.006, zd + 0.0445), 2.2, 0.25))
    for o in neu:
        o['nur_web'] = 1

    # -------------------------------------------------------------- Tischkarte (Menue)
    # Aufsteller 10 x 14 cm, Karton, schraeg zur Kamera. Vorderseite als eigene
    # Flaeche mit UV 0..1: Im Web zeichnet dort eine Leinwand Namen, Logo und
    # die Gerichte (Wunsch A1/B3); im Foto steht der Beispielname.
    papier = B.stoff('Karton', (0.86, 0.83, 0.76), 0.8, spec=0.3)
    tinte = B.stoff('Druckfarbe', (0.03, 0.03, 0.035), 0.6)
    kb, kh, ka = 0.100, 0.140, math.radians(18)
    kx, ky = 0.255, 0.030
    aufsteller = bpy.data.objects.new('menukarte', None); bpy.context.scene.collection.objects.link(aufsteller)
    aufsteller.location = (kx, ky, H); aufsteller.rotation_euler = (0, 0, math.radians(-38))
    for seite, sgn in (('vorn', -1), ('hinten', 1)):
        # Zelt: Fuesse auseinander, oben treffen sich beide Seiten
        V = [(-kb / 2, 0, 0), (kb / 2, 0, 0), (kb / 2, 0, kh), (-kb / 2, 0, kh)]
        o = B.netz(f'menukarte_{seite}', V, [(0, 1, 2, 3)], [papier], uv=[[(0, 0), (1, 0), (1, 1), (0, 1)]], glatt=False)
        o.parent = aufsteller
        o.location = (0, sgn * math.sin(ka) * kh, 0)
        o.rotation_euler = (sgn * ka, 0, 0)
        mod = o.modifiers.new('karton', 'SOLIDIFY'); mod.thickness = 0.0006
    # Beispieltext (nur im Foto; im Web ersetzt die Leinwand)
    import bpy as _b
    zeilen = [('Trattoria Aurora', 0.0105, 0.112), ('Menu', 0.0060, 0.094), ('Spaghetti al pomodoro', 0.0052, 0.074),
              ('basilico, parmigiano', 0.0040, 0.064), ('Panna cotta', 0.0052, 0.046), ('coulis di lamponi', 0.0040, 0.036)]
    for i, (t, gr, zz) in enumerate(zeilen):
        cu = _b.data.curves.new(f'menukarte_text_{i}', 'FONT'); cu.body = t; cu.size = gr; cu.align_x = 'CENTER'; cu.extrude = 0.00005
        to = _b.data.objects.new(f'menukarte_text_{i}', cu); _b.context.scene.collection.objects.link(to)
        cu.materials.append(tinte)
        to.parent = bpy.data.objects['menukarte_vorn']
        to.location = (0, -0.0008, zz); to.rotation_euler = (math.radians(90), 0, 0)
        to['nur_foto'] = 1

    # -------------------------------------------------------------- Mitte: Kerze, Muehlen
    fuss = B.drehkoerper('leuchter', [(0.0, 0.0), (0.045, 0.0), (0.046, 0.003), (0.030, 0.010), (0.012, 0.018), (0.010, 0.080),
                                     (0.016, 0.090), (0.016, 0.100), (0.0125, 0.102), (0.0125, 0.096), (0.0, 0.096)], 48, [messing])
    fuss.location = (0.0, 0.0, H)
    kerze = B.drehkoerper('kerze', [(0.0, 0.0), (0.0110, 0.0), (0.0110, 0.195), (0.0100, 0.199), (0.0030, 0.201), (0.0, 0.198)], 40, [wachs])
    kerze.location = (0.0, 0.0, H + 0.096)
    B.drehkoerper('kerze_docht', [(0.0, 0.0), (0.0006, 0.0), (0.0006, 0.010), (0.0, 0.011)], 8, [docht]).location = (0.0, 0.0, H + 0.096 + 0.197)
    fl = B.drehkoerper('kerze_flamme', [(0.0, 0.0), (0.0035, 0.004), (0.0045, 0.010), (0.0035, 0.020), (0.0015, 0.030), (0.0, 0.034)], 24, [flamme])
    fl.location = (0.0, 0.0, H + 0.096 + 0.203)
    licht = bpy.data.objects.new('kerzenlicht', None); bpy.context.scene.collection.objects.link(licht)
    licht.location = (0.0, 0.0, H + 0.096 + 0.215)
    for i, (mx, hoch) in enumerate(((-0.075, 0.105), (0.075, 0.090))):
        m = B.drehkoerper(f'muehle_{i}', [(0.0, 0.0), (0.021, 0.0), (0.022, 0.003), (0.019, hoch * 0.45), (0.022, hoch * 0.85),
                                          (0.020, hoch), (0.0, hoch)], 40, [holz])
        m.location = (mx, 0.02, H)
        kn = B.drehkoerper(f'muehle_{i}_knauf', [(0.0, 0.0), (0.008, 0.0), (0.008, 0.008), (0.005, 0.012), (0.0, 0.012)], 24, [besteck])
        kn.location = (mx, 0.02, H + hoch)

    zuordnung = {leinen[0]: leinen, serviette[0]: serviette, geschirr[0]: geschirr}
    B.exportieren('gastro', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; Tischgedeck fuer zwei nach Gastronomie-Grundregel')
    if not web:
        B.speichern('gastro')
