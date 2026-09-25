"""pr_schmuck.py -- Schmuck & Uhren: Automatikuhr 40 mm (liegend, Zifferblatt
oben) und ein Solitaerring daneben. Metall, Zifferblatt, Band und Stein
wechseln als Varianten zusammen; im Web geht die Uhr in drei Schritten
nach oben auseinander: Glas und Luenette, Zeiger und Zifferblatt, Werk.

Masse nach gaengigen Automatikuhren: Gehaeuse 40 mm, Hoehe 11 mm,
Bandanstoss 20 mm, Glas Saphir (n = 1,77). Ring: Innen 18 mm, Schiene
2,2 mm, Brillant 6,6 mm (~1 ct) in vier Krappen. Zeit 10:10:35 -- die
Stellung, in der Zeiger den Schriftzug frei lassen.
"""
import math
import numpy as np
import bpy
from mathutils import Vector, Matrix, Euler
import pr_basis as B


def ring_profil(r0, r1, z0, z1, fase=0.0004):
    """Geschlossenes Rechteckprofil (r, z) mit Fasen fuer hohle Drehkoerper."""
    f = fase
    return [(r0, z0 + f), (r0 + f, z0), (r1 - f, z0), (r1, z0 + f), (r1, z1 - f), (r1 - f, z1), (r0 + f, z1), (r0, z1 - f), (r0, z0 + f)]


def brillant(name, r, stoff_, ort):
    """Brillantschliff vereinfacht: Tafel, Krone (8 Haupt-, 16 Rundistfacetten),
    Rundiste, Pavillon (8 Haupt-, 16 untere Facetten), Kalette. Flach schattiert."""
    ct, pd = 0.162 * 2 * r, 0.431 * 2 * r
    V, F = [], []
    def ring_(n, rad, z, a0=0.0):
        s = len(V)
        for i in range(n):
            a = a0 + 2 * math.pi * i / n
            V.append((rad * math.cos(a), rad * math.sin(a), z))
        return list(range(s, s + n))
    tafel = ring_(8, 0.57 * r, ct)
    stern = ring_(8, 0.80 * r, 0.55 * ct, math.pi / 8)
    rund_o = ring_(16, r, 0.012 * r)
    rund_u = ring_(16, r, -0.012 * r)
    pav = ring_(8, 0.45 * r, -0.62 * pd, math.pi / 8)
    V.append((0, 0, -pd)); kal = len(V) - 1
    F.append(tuple(tafel))
    for i in range(8):
        a, b = tafel[i], tafel[(i + 1) % 8]
        F.append((a, stern[i], b))                                   # Sternfacette
        F.append((a, stern[i - 1], rund_o[2 * i]))                   # Hauptfacette (Drachen) in zwei Haelften
        F.append((a, rund_o[2 * i], stern[i]))
        F.append((stern[i], rund_o[2 * i], rund_o[2 * i + 1]))       # obere Rundistfacetten
        F.append((stern[i], rund_o[2 * i + 1], rund_o[(2 * i + 2) % 16]))
    for i in range(16):
        F.append((rund_o[i], rund_u[i], rund_u[(i + 1) % 16], rund_o[(i + 1) % 16]))
    for i in range(8):
        F.append((rund_u[2 * i], rund_u[2 * i + 1], pav[i]))
        F.append((rund_u[2 * i + 1], rund_u[(2 * i + 2) % 16], pav[i]))
        F.append((pav[i - 1 if i else 7], rund_u[2 * i], pav[i], kal))
    o = B.netz(name, V, [f[::-1] for f in F], [stoff_], None, None, False)
    import bmesh
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces); bm.to_mesh(o.data); bm.free()
    B.flach_schattieren(o); o.location = ort
    return o


def bauen(web=False):
    n = 96 if web else 192
    sc = bpy.context.scene
    VAR = ['Stahl', 'Gelbgold', 'Rosegold']
    poliert = [B.stoff('Metall poliert Stahl', (0.66, 0.65, 0.63), 0.07, metall=1.0),
               B.stoff('Metall poliert Gelbgold', (1.00, 0.77, 0.40), 0.07, metall=1.0),
               B.stoff('Metall poliert Rosegold', (0.98, 0.66, 0.55), 0.07, metall=1.0)]
    gebuerstet = [B.stoff('Metall satiniert Stahl', (0.64, 0.63, 0.61), 0.24, metall=1.0),
                  B.stoff('Metall satiniert Gelbgold', (0.98, 0.75, 0.38), 0.24, metall=1.0),
                  B.stoff('Metall satiniert Rosegold', (0.96, 0.64, 0.53), 0.24, metall=1.0)]
    blatt = [B.stoff(f'Zifferblatt {k}', (1, 1, 1), 0.32, farbkarte=f'zifferblatt-{k.lower()}-farbe', coat=0.4, coat_rau=0.05)
             for k in ('Blau', 'Schwarz', 'Weiss')]
    band = [B.stoff('Band Navy', (0.030, 0.045, 0.090), 0.68, spec=0.35, normal='innen-leder-normal', normal_staerke=1.0, kachel=0.012),
            B.stoff('Band Schwarz', (0.018, 0.018, 0.020), 0.64, spec=0.35, normal='innen-leder-normal', normal_staerke=1.0, kachel=0.012),
            B.stoff('Band Cognac', (0.22, 0.09, 0.035), 0.66, spec=0.35, normal='innen-leder-normal', normal_staerke=1.0, kachel=0.012)]
    stein = [B.stoff('Stein Saphir', (0.18, 0.30, 0.95), 0.0, trans=1.0, ior=1.77, disp=0.014),
             B.stoff('Stein Diamant', (1.0, 1.0, 1.0), 0.0, trans=1.0, ior=2.42, disp=0.023),
             B.stoff('Stein Rubin', (0.95, 0.10, 0.18), 0.0, trans=1.0, ior=1.77, disp=0.014)]
    glas = B.stoff('Saphirglas', (1, 1, 1), 0.0, trans=1.0, ior=1.77, spec=0.25)   # entspiegelt
    leucht = B.stoff('Leuchtmasse', (0.86, 0.90, 0.80), 0.45)
    rhodium = B.stoff('Werk Rhodium', (0.74, 0.74, 0.76), 0.28, metall=1.0, normal='werk-perlage-normal', normal_staerke=0.35, kachel=0.004)
    bruecke = B.stoff('Werk Bruecken', (0.76, 0.76, 0.78), 0.22, metall=1.0, normal='werk-streifen-normal', normal_staerke=0.25, kachel=0.012)
    messing = B.stoff('Werk Gold', (0.93, 0.72, 0.38), 0.18, metall=1.0)
    rubin_lager = B.stoff('Lagerstein', (0.55, 0.02, 0.06), 0.02, trans=0.6, ior=1.77)
    loch = B.stoff('Bandloch', (0.004, 0.004, 0.005), 0.8)
    faden = [B.stoff('Faden Navy', (0.55, 0.50, 0.42), 0.7),        # Sattlergarn, sandfarben
             B.stoff('Faden Schwarz', (0.020, 0.020, 0.022), 0.6),    # Ton in Ton
             B.stoff('Faden Cognac', (0.60, 0.52, 0.38), 0.7)]

    teile = []
    # ------------------------------------------------ Gehaeuse (Mitte 0,0; 12 Uhr = +Y)
    boden = B.drehkoerper('uhr_boden', [(0.0, 0.0008), (0.010, 0.0008), (0.0125, 0.0002), (0.0160, 0.0), (0.0174, 0.0008),
                                       (0.0176, 0.0026), (0.0, 0.0026)], n, [gebuerstet[0]])
    mitte = B.drehkoerper('uhr_gehaeuse', ring_profil(0.0164, 0.0200, 0.0024, 0.0088, 0.0005), n, [poliert[0]])
    for sx in (-1, 1):
        for sy in (-1, 1):
            u = [(0.0100, 0.0150), (0.0126, 0.0150), (0.0126, 0.0238), (0.0118, 0.0246), (0.0100, 0.0246)]
            u = [(sx * x, sy * y) for x, y in u]
            if sx * sy < 0:
                u = u[::-1]
            B.prisma(f'uhr_anstoss_{"l" if sx < 0 else "r"}{"o" if sy > 0 else "u"}', u, 0.0030, 0.0078, [gebuerstet[0]], fase=0.0005)
    lue = B.drehkoerper('uhr_luenette', [(0.0166, 0.0088), (0.0198, 0.0088), (0.0200, 0.0092), (0.0197, 0.0099), (0.0180, 0.0106),
                                         (0.0171, 0.0108), (0.0166, 0.0105), (0.0166, 0.0088)], n, [poliert[0]])
    # Saphirglas flach mit kleiner Fase -- die erste Fassung war gewoelbt und
    # wirkte wie eine Lupe (Ring aus gebrochenem Blau am Rand, Probe 23.09.)
    gl = B.drehkoerper('uhr_glas', [(0.0, 0.0102), (0.0165, 0.0102), (0.0165, 0.0112), (0.0160, 0.0116), (0.0, 0.0116)], n, [glas])
    # ------------------------------------------------ Krone (3 Uhr)
    # Feine Raendelung (40 flache Rillen) statt grober Zaehne -- die erste
    # Fassung sah in der Nahprobe aus wie ein Zahnrad; dazu eine gewoelbte Kappe.
    kr = B.prisma('uhr_krone', B.zahnrad(40, 0.0031, 0.00296, 4), 0.0, 0.0028, [poliert[0]], fase=0.00015)
    kr.rotation_euler = (0, math.radians(90), 0); kr.location = (0.0198, 0, 0.0056)
    kap = B.drehkoerper('uhr_krone_kappe', [(0.0, 0.0034), (0.0024, 0.0033), (0.0029, 0.0030), (0.0030, 0.0027), (0.0030, 0.0026), (0.0, 0.0026)], 48, [poliert[0]])
    kap.rotation_euler = (0, math.radians(90), 0); kap.location = (0.0198, 0, 0.0056)
    B.drehkoerper('uhr_krone_tubus', [(0.0, 0.0), (0.0014, 0.0), (0.0014, 0.0008), (0.0, 0.0008)], 32, [gebuerstet[0]]).rotation_euler = (0, math.radians(90), 0)
    bpy.data.objects['uhr_krone_tubus'].location = (0.0194, 0, 0.0056)
    # ------------------------------------------------ Zifferblatt, Indizes, Zeiger
    R_B = 0.0160
    zb = B.prisma('blatt_scheibe', B.kreis(R_B, n), 0.0058, 0.0062, [blatt[0]], uv_skala=1 / (2 * R_B), uv_versatz=(0.5, 0.5))
    for k in range(12):
        a = math.radians(90 - k * 30)
        breite = 0.0011 if k else 0.0009
        for dx in ((-0.00075, 0.00075) if k == 0 else (0.0,)):
            idx = B.kasten(f'blatt_index_{k}{"ab"[int(dx > 0)] if dx else ""}', breite, 0.0036, 0.00045, [poliert[0]], 0.0001)
            idx.rotation_euler = (0, 0, a - math.pi / 2)
            rr = 0.0124
            idx.location = (rr * math.cos(a) + dx * math.sin(a) * -1 * 0 + dx * math.cos(a - math.pi / 2) * 0 + (dx if k == 0 else 0),
                            rr * math.sin(a), 0.0062)
            if k:
                lu = B.kasten(f'blatt_leucht_{k}', breite * 0.45, 0.0026, 0.0001, [leucht], 0.0)
                lu.rotation_euler = idx.rotation_euler; lu.location = (idx.location.x, idx.location.y, 0.00665)
    def zeiger(name, laenge, breite, winkel_deg, z, dicke=0.00022, schwanz=0.0, form='dauphine'):
        if form == 'dauphine':
            u = [(0.0, -schwanz), (breite / 2, 0.0), (breite * 0.36, laenge * 0.72), (0.0, laenge), (-breite * 0.36, laenge * 0.72), (-breite / 2, 0.0)]
        else:
            u = [(breite / 2, -schwanz), (breite / 2, laenge), (-breite / 2, laenge), (-breite / 2, -schwanz)]
        o = B.prisma(name, u, z, z + dicke, [poliert[0]], fase=0.00005, segmente=1)
        o.rotation_euler = (0, 0, math.radians(-winkel_deg))
        return o
    zeiger('zeiger_stunde', 0.0095, 0.0017, 305.0, 0.0066)
    zeiger('zeiger_minute', 0.0146, 0.0013, 60.0, 0.0069)
    zeiger('zeiger_sekunde', 0.0156, 0.00022, 210.0, 0.0072, dicke=0.00012, schwanz=0.0035, form='stab')
    kappe = B.drehkoerper('zeiger_kappe', [(0.0, 0.0071), (0.0007, 0.0071), (0.0007, 0.0074), (0.0005, 0.0075), (0.0, 0.0075)], 24, [poliert[0]])
    # ------------------------------------------------ Werk (unter dem Zifferblatt)
    B.prisma('werk_platine', B.kreis(0.0158, n), 0.0030, 0.0042, [rhodium], fase=0.0002)
    B.prisma('werk_bruecke_a', [(-0.0120, -0.0020), (0.0020, -0.0100), (0.0090, -0.0060), (-0.0040, 0.0040)], 0.0042, 0.0048, [bruecke], fase=0.0002)
    B.prisma('werk_bruecke_b', [(-0.0060, 0.0050), (0.0080, 0.0010), (0.0110, 0.0070), (-0.0020, 0.0115)], 0.0042, 0.0048, [bruecke], fase=0.0002)
    for i, (mx, my, rk, zz) in enumerate(((0.0040, 0.0060, 0.0042, 60), (-0.0070, 0.0065, 0.0030, 45), (0.0065, -0.0055, 0.0034, 50))):
        g = B.prisma(f'werk_rad_{i}', B.zahnrad(zz, rk, rk * 0.93, 4), 0.0048, 0.0050, [messing])
        g.location = (mx, my, 0)
    unruh = B.drehkoerper('werk_unruh', ring_profil(0.0036, 0.0042, 0.0048, 0.0051, 0.00008), 64, [messing])
    unruh.location = (-0.0080, -0.0060, 0)
    for i in range(6):
        v = B.drehkoerper(f'werk_lager_{i}', [(0.0, 0.00481), (0.00055, 0.00481), (0.00055, 0.00495), (0.0, 0.00497)], 16, [rubin_lager])
        a = i * 1.1
        v.location = (0.0085 * math.cos(a), 0.0085 * math.sin(a), 0)
    rotor = B.prisma('werk_rotor', [(0.0145 * math.cos(a), 0.0145 * math.sin(a)) for a in np.linspace(math.pi * 0.05, math.pi * 0.95, 40)] +
                     [(0.0022 * math.cos(a), 0.0022 * math.sin(a)) for a in np.linspace(math.pi * 0.95, math.pi * 0.05, 12)],
                     0.0051, 0.0056, [bruecke], fase=0.0002)
    rotor.rotation_euler = (0, 0, math.radians(200))
    # ------------------------------------------------ Armband (liegt flach, faellt vom Anstoss zum Tisch)
    import bmesh
    # Form des Bandes, gemeinsam fuer Band, Naht und Loecher: leicht gepolstert
    # (0,35 mm Woelbung quer), vom Anstoss in 18 mm auf den Tisch fallend,
    # zur Spitze duenner und von 20 auf 16 mm verjuengt. Die erste Fassung
    # verformte nur die Umrissecken -- Ober- und Unterseite blieben ebene
    # Vielecke, das Band lag als schraege Platte ueber dem Tisch und die Naht
    # verschwand darin (Strahlmessung 23.09.2026: Band vor Naht, 3 mm).
    def band_form(co):
        x, y, z = co.x, co.y, co.z
        if z > 0.0019:
            z += 0.00035 * max(0.0, 1 - (x / 0.0098) ** 2) * min(1.0, (z - 0.0019) / 0.0019)
        d = abs(y) - 0.0240                               # ab dem Anstoss nach aussen
        hub = 0.0030 * max(0.0, 1 - d / 0.018) ** 2 if d > 0 else 0.0030
        co.z = z * (1 - 0.1 * max(0.0, min(1.0, d / 0.05))) + hub
        co.x = x * (1 - 0.18 * max(0.0, min(1.0, d / 0.075)))

    def band_teil(name, y0, y1, spitze):
        pts = [(0.0098, y0), (0.0098, y1)]
        if spitze:
            s = 1 if y1 > y0 else -1
            for a in np.linspace(0, math.pi, 18)[1:-1]:
                pts.append((0.0098 * math.cos(a), y1 + s * 0.0060 * math.sin(a)))
        pts += [(-0.0098, y1), (-0.0098, y0)]
        if y1 < y0:
            pts = pts[::-1]
        o = B.prisma(name, pts, 0.0, 0.0038, [band[0]], fase=0.0008, uv_skala=1.0)
        me = o.data
        bm = bmesh.new(); bm.from_mesh(me)
        lo_, hi_ = sorted((y0, y1 + (0.006 if spitze and y1 > y0 else -0.006 if spitze else 0.0)))
        for yc in np.arange(lo_ + 0.0015, hi_, 0.0015):         # Laengsteilung 1,5 mm
            g = bm.verts[:] + bm.edges[:] + bm.faces[:]
            bmesh.ops.bisect_plane(bm, geom=g, plane_co=(0, yc, 0), plane_no=(0, 1, 0))
        for xc in (-0.0065, -0.0033, 0.0, 0.0033, 0.0065):        # Querteilung fuer die Woelbung
            g = bm.verts[:] + bm.edges[:] + bm.faces[:]
            bmesh.ops.bisect_plane(bm, geom=g, plane_co=(xc, 0, 0), plane_no=(1, 0, 0))
        for v in bm.verts:
            band_form(v.co)
        bm.to_mesh(me); bm.free(); me.update()
        return o
    band_teil('band_oben', 0.0200, 0.1050, True)
    band_teil('band_unten', -0.0200, -0.0800, False)
    # Naht: Stiche 0,9 mm lang im Abstand 1,25 mm, 1,5 mm vom Rand, leicht
    # erhaben -- dieselbe Form wie das Band, damit sie aufliegt.
    def naht(name, y_von, y_bis):
        bm = bmesh.new(); schritt = 0.00125
        n_ = int(abs(y_bis - y_von) / schritt); s_ = 1 if y_bis > y_von else -1
        for k in range(n_):
            yc = y_von + s_ * (k + 0.5) * schritt
            for xc in (-0.0083, 0.0083):
                erg = bmesh.ops.create_cube(bm, size=1.0)
                for v in erg['verts']:
                    v.co.x = xc + v.co.x * 0.00032; v.co.y = yc + v.co.y * 0.0009; v.co.z = 0.00372 + (v.co.z + 0.5) * 0.00022
        for v in bm.verts:
            band_form(v.co)
        me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
        me.materials.append(faden[0])
        o = bpy.data.objects.new(name, me); sc.collection.objects.link(o)
        return o
    naht('band_naht_oben', 0.0262, 0.1000)
    naht('band_naht_unten', -0.0262, -0.0760)
    # Schlaufen auf der Schnallenseite (fest und beweglich): Leder, 3 mm
    # breit, liegen oben und seitlich um das Band; unten liegt das Band auf.
    for k_, ys in enumerate((-0.0600, -0.0690)):
        tmp = Vector((0.0098, ys, 0.0038)); band_form(tmp)
        hw, oben = tmp.x + 0.0003, tmp.z + 0.0002
        teile_s = [B.kasten(f'band_schlaufe_{k_}_oben', 2 * hw + 0.0014, 0.0030, 0.0007, [band[0]], 0.0002, (0, ys, oben)),
                   B.kasten(f'band_schlaufe_{k_}_l', 0.0007, 0.0030, oben + 0.0007, [band[0]], 0.0002, (-hw - 0.00035, ys, 0.0)),
                   B.kasten(f'band_schlaufe_{k_}_r', 0.0007, 0.0030, oben + 0.0007, [band[0]], 0.0002, (hw + 0.00035, ys, 0.0))]
    # Loecher echt ausgestanzt (Boolesche Differenz). Vorher lagen duenne
    # dunkle Scheiben auf dem Band -- im Web verschwanden sie nach der
    # Quantisierung im Leder (Uwe, 23.09.2026: "Es fehlen die Loecher").
    bo = bpy.data.objects['band_oben']
    for k in range(5):
        st = B.prisma(f'_stanze_{k}', B.kreis(0.00085, 24, (0.0, 0.070 + k * 0.007)), -0.01, 0.02, [loch])
        m = bo.modifiers.new(f'loch_{k}', 'BOOLEAN'); m.operation = 'DIFFERENCE'; m.solver = 'EXACT'; m.object = st
        with bpy.context.temp_override(object=bo, active_object=bo, selected_objects=[bo], selected_editable_objects=[bo]):
            bpy.ops.object.modifier_apply(modifier=m.name)
        bpy.data.objects.remove(st, do_unlink=True)
    schnalle = B.prisma('band_schnalle', [(0.0102, -0.0790), (0.0102, -0.0960), (-0.0102, -0.0960), (-0.0102, -0.0790),
                                          (-0.0088, -0.0790), (-0.0088, -0.0944), (0.0088, -0.0944), (0.0088, -0.0790)],
                        0.0004, 0.0036, [poliert[0]], fase=0.0004)
    # ------------------------------------------------ Ring (steht, Stein oben)
    rx, ry = -0.048, -0.026
    ri = B.drehkoerper('ring_schiene', [(0.0090, -0.0010), (0.0094, -0.0011), (0.0104, -0.0010), (0.0108, 0.0), (0.0104, 0.0010),
                                        (0.0094, 0.0011), (0.0090, 0.0010), (0.0089, 0.0), (0.0090, -0.0010)], n, [poliert[0]])
    ri.rotation_euler = (math.radians(90), 0, math.radians(-20)); ri.location = (rx, ry, 0.0108)
    # Korb: Innenflaeche bleibt unter dem Pavillon, damit sich Stein und Metall nicht schneiden
    kopf = B.drehkoerper('ring_kopf', [(0.0, 0.0200), (0.0016, 0.0200), (0.0030, 0.0228), (0.0027, 0.0231), (0.0025, 0.0227),
                                       (0.0010, 0.0206), (0.0, 0.0206)], 48, [poliert[0]])
    kopf.location = (rx, ry, 0)
    for i in range(4):
        a = math.radians(45 + 90 * i)
        kr_ = B.drehkoerper(f'ring_krappe_{i}', [(0.0, 0.0196), (0.00045, 0.0196), (0.00045, 0.0252), (0.0003, 0.0257), (0.0, 0.0258)], 12, [poliert[0]])
        kr_.location = (rx + 0.0031 * math.cos(a), ry + 0.0031 * math.sin(a), 0)
    brillant('ring_stein', 0.0033, stein[0], (rx, ry, 0.0240))

    zuordnung = {poliert[0]: poliert, gebuerstet[0]: gebuerstet, blatt[0]: blatt, band[0]: band, stein[0]: stein, faden[0]: faden}
    B.exportieren('schmuck', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; Automatikuhr 40 mm und Solitaerring')
    if not web:
        B.speichern('schmuck')
