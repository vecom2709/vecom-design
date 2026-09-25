"""pr_lkw.py -- Logistik: Sattelzug (Zugmaschine 4x2 + Planenauflieger 13,6 m)
mit Ladung. Im Web oeffnet sich die Plane, die Kabine kippt, und die
Achsen gehen auseinander.

Masse nach EU-Grenzen und gaengigen Fahrzeugen (gerundet): Zuglaenge 16,50 m,
Breite 2,55 m, Hoehe 4,00 m. Zugmaschine: Ueberhang vorn 1,40 m, Radstand
3,60 m, Sattelkupplung 0,50 m vor der Hinterachse, Reifen 315/70 R22.5
(vorn einzeln, hinten zwillingsbereift). Auflieger: Laenge 13,60 m,
Ladeflaeche auf 1,20 m, Koenigszapfen 1,60 m hinter der Stirnwand,
3 Achsen im Abstand 1,31 m, Reifen 385/65 R22.5. Ladung: Europaletten
1200 x 800 mm, drei nebeneinander, Kartons foliert.

Laengsachse = y (Front nach -y, wie im Studio ueblich), Breite = x.

Varianten (KHR_materials_variants): Rot / Plane weiss, Weiss / Plane grau,
Blau / Plane blau.
"""
import math
import numpy as np
import bpy
import bmesh
from mathutils import Vector
import pr_basis as B

Y_FRONT = -8.25
Y_VA = Y_FRONT + 1.40            # Vorderachse
Y_HA = Y_VA + 3.60               # Hinterachse Zugmaschine
Y_SK = Y_HA - 0.50               # Sattelkupplung = Koenigszapfen
Y_A0 = Y_SK - 1.60               # Stirnwand Auflieger
Y_A1 = Y_A0 + 13.60              # Heck Auflieger
Y_ACHSEN = (Y_A1 - 1.48 - 2 * 1.31, Y_A1 - 1.48 - 1.31, Y_A1 - 1.48)
BREITE = 2.55
H_DECK = 1.20
H_DACH = 4.00


def rad_einheit(name, x, y, r_aus, breite, felge_r, stoffe, seite, n=72):
    """Rad als Gruppe unter einem Empty in der Radmitte: Das Web schiebt die
    Gruppe beim Zerlegen nach aussen."""
    reifen, felge, nabe = stoffe
    b2 = breite / 2
    rf = felge_r + 0.012
    prof = [(rf, -b2 + 0.02), (rf + 0.02, -b2), (r_aus - 0.10, -b2 - 0.004), (r_aus - 0.04, -b2 + 0.01), (r_aus - 0.012, -b2 + 0.035),
            (r_aus, -b2 + 0.06)]
    for zr in np.linspace(-b2 + 0.10, b2 - 0.10, 4):
        prof += [(r_aus, zr - 0.012), (r_aus - 0.014, zr - 0.008), (r_aus - 0.014, zr + 0.008), (r_aus, zr + 0.012)]
    prof += [(r_aus, b2 - 0.06), (r_aus - 0.012, b2 - 0.035), (r_aus - 0.04, b2 - 0.01), (r_aus - 0.10, b2 + 0.004), (rf + 0.02, b2),
             (rf, b2 - 0.02), (rf, -b2 + 0.02)]
    teile = [B.drehkoerper(name + '_reifen', prof, n, [reifen], kante_winkel=40)]
    teile.append(B.drehkoerper(name + '_felge', [(0.0, b2 - 0.05), (0.11, b2 - 0.05), (0.13, b2 - 0.08), (felge_r * 0.72, b2 - 0.085),
                                                 (felge_r * 0.80, b2 - 0.07), (felge_r - 0.01, b2 - 0.03), (felge_r + 0.012, b2 - 0.02),
                                                 (felge_r + 0.012, -b2 + 0.02), (felge_r - 0.004, -b2 + 0.03), (felge_r - 0.01, b2 - 0.04),
                                                 (felge_r * 0.78, b2 - 0.08), (0.12, b2 - 0.065), (0.0, b2 - 0.06)], n, [felge]))
    teile.append(B.drehkoerper(name + '_nabe', [(0.0, b2 - 0.06), (0.105, b2 - 0.06), (0.105, b2 - 0.03), (0.07, b2 + 0.005), (0.0, b2 + 0.01)], 32, [nabe]))
    for k in range(10):
        a = 2 * math.pi * k / 10
        m = B.drehkoerper(f'{name}_mutter_{k}', [(0.0, 0.0), (0.016, 0.0), (0.016, 0.028), (0.0, 0.03)], 6, [nabe])
        m.location = (0.0875 * math.cos(a), 0.0875 * math.sin(a), b2 - 0.05)
        teile.append(m)
    e = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(e)
    for o in teile:
        o.parent = e
    # Radachse = lokale z; nach aussen (seite +1 -> +x)
    e.rotation_euler = (0, math.radians(90 * seite), 0)
    e.location = (x, y, r_aus)
    return e


def plane_gitter(name, x, y0, y1, z0, z1, stoff_, seite, gurte, u0=0.0, u1=1.0, nz=10, dy=0.15):
    """Planenfeld mit leichtem Bauch zwischen den Spanngurten (x nach aussen).
    UV: u ueber die ganze Plane (Druck), v ueber die Hoehe."""
    ny = max(2, int(round((y1 - y0) / dy)))
    V, F, UV = [], [], []
    ys = np.linspace(y0, y1, ny + 1); zs = np.linspace(z0, z1, nz + 1)
    for j, z in enumerate(zs):
        for i, y in enumerate(ys):
            # Abstand zum naechsten Gurt -> Bauch; oben und unten gespannt
            d = min(abs(y - g) for g in gurte) if gurte else 0.3
            bauch = 0.018 * min(1.0, d / 0.30) * max(0.0, math.sin(math.pi * (z - z0) / (z1 - z0))) ** 0.7
            V.append((x + seite * bauch, y, z))
            u = u0 + (u1 - u0) * (i / ny)
            UV.append((1 - u if seite < 0 else u, (z - z0) / (z1 - z0)))   # liest sich von aussen
    m = ny + 1
    for j in range(nz):
        for i in range(ny):
            a = j * m + i
            q = (a, a + 1, a + m + 1, a + m)
            F.append(q[::-1] if seite < 0 else q)                          # Normale nach aussen
    uvs = [[UV[k] for k in f] for f in F]
    return B.netz(name, V, F, [stoff_], None, uvs, True)


def bauen(web=False):
    VAR = ['Rot', 'Weiss', 'Blau']
    lack = [B.stoff('Lack Rot', (0.42, 0.018, 0.022), 0.30, coat=1.0, coat_rau=0.03),
            B.stoff('Lack Weiss', (0.78, 0.78, 0.76), 0.30, coat=1.0, coat_rau=0.03),
            B.stoff('Lack Blau', (0.012, 0.030, 0.095), 0.30, coat=1.0, coat_rau=0.03)]
    plane = [B.stoff('Plane Weiss', (1, 1, 1), 0.55, farbkarte='lkw-plane-weiss-farbe', spec=0.35),
             B.stoff('Plane Grau', (1, 1, 1), 0.55, farbkarte='lkw-plane-grau-farbe', spec=0.35),
             B.stoff('Plane Blau', (1, 1, 1), 0.55, farbkarte='lkw-plane-blau-farbe', spec=0.35)]
    dachplane = [B.stoff('Dachplane Weiss', (0.78, 0.78, 0.76), 0.6, spec=0.35),
                 B.stoff('Dachplane Grau', (0.30, 0.31, 0.33), 0.6, spec=0.35),
                 B.stoff('Dachplane Blau', (0.03, 0.06, 0.16), 0.6, spec=0.35)]
    schwarz = B.stoff('Kunststoff Schwarz', (0.025, 0.025, 0.027), 0.55)
    anthrazit = B.stoff('Rahmen Anthrazit', (0.035, 0.037, 0.040), 0.45, metall=0.3)
    alu = B.stoff('Aluminium', (0.60, 0.61, 0.62), 0.32, metall=1.0)
    chrom = B.stoff('Chrom', (0.74, 0.74, 0.74), 0.06, metall=1.0)
    scheibe = B.stoff('Scheibe getoent', (0.015, 0.018, 0.022), 0.03, spec=0.6)
    reifen = B.stoff('Reifen', (0.022, 0.022, 0.024), 0.78, spec=0.35)
    felge = B.stoff('Felge Silber', (0.56, 0.57, 0.58), 0.30, metall=0.9)
    nabe = B.stoff('Nabe', (0.40, 0.41, 0.42), 0.35, metall=1.0)
    scheinwerfer = B.stoff('Scheinwerfer Glas', (0.85, 0.87, 0.90), 0.05, coat=1.0, emiss=((1.0, 0.98, 0.94), 0.4))
    rueck = B.stoff('Rueckleuchte', (0.55, 0.02, 0.02), 0.1, emiss=((1.0, 0.05, 0.03), 0.8))
    blinker = B.stoff('Blinker', (0.9, 0.45, 0.05), 0.1)
    holz = B.stoff('Ladeboden', (1, 1, 1), 0.7, farbkarte='holz-kiste-farbe', kachel=0.60)
    palette = B.stoff('Palette Holz', (0.42, 0.31, 0.19), 0.85)
    karton = B.stoff('Karton', (0.40, 0.28, 0.15), 0.85)
    folie = B.stoff('Stretchfolie', (0.9, 0.9, 0.9), 0.15, trans=0.55, ior=1.45)
    gurt = B.stoff('Spanngurt', (0.03, 0.03, 0.035), 0.6)

    # ============================================================== Zugmaschine
    # Rahmen: zwei Laengstraeger
    for sx in (-1, 1):
        B.kasten(f'zm_rahmen_{sx}', 0.08, Y_HA + 1.05 - (Y_FRONT + 0.30), 0.27, [anthrazit], 0.004,
                 (sx * 0.43, (Y_HA + 1.05 + Y_FRONT + 0.30) / 2, 0.73))
    for yq in (Y_VA - 0.2, Y_VA + 1.4, Y_HA - 0.9, Y_HA + 0.9):
        B.kasten(f'zm_quer_{int((yq - Y_FRONT) * 10)}', 0.80, 0.08, 0.20, [anthrazit], 0.004, (0, yq, 0.76))
    # Kabine (kippt ueber die Vorderkante) -- alles, was mitkippt, in kabine[]
    K0, K1 = Y_FRONT + 0.05, Y_FRONT + 2.25        # Kabine vorn/hinten
    ZK0, ZK1 = 1.10, 3.80
    BK = 2.49
    kabine = []
    kabine.append(B.rundkasten('kab_koerper', BK, K1 - K0, ZK1 - ZK0, 0.16, [lack[0]], (0, (K0 + K1) / 2, ZK0), n=12))
    # Dachspoiler, zum Heck ansteigend
    sp = B.rundkasten('kab_spoiler', BK - 0.10, 1.30, 0.26, 0.10, [lack[0]], (0, K1 - 0.70, ZK1 - 0.06), n=8)
    sp.rotation_euler = (math.radians(-6), 0, 0); kabine.append(sp)
    # Frontscheibe mit schwarzem Rand
    kabine.append(B.rundkasten('kab_scheibenrand', 2.32, 0.03, 1.20, 0.07, [schwarz], (0, K0 - 0.005, 1.96), n=6))
    kabine.append(B.rundkasten('kab_scheibe', 2.22, 0.02, 1.10, 0.06, [scheibe], (0, K0 - 0.018, 2.01), n=6))
    # Sonnenblende mit Positionsleuchten
    kabine.append(B.rundkasten('kab_sonnenblende', 2.36, 0.26, 0.07, 0.025, [lack[0]], (0, K0 - 0.10, 3.20), n=4))
    for k in range(5):
        kabine.append(B.rundkasten(f'kab_dachleuchte_{k}', 0.07, 0.04, 0.035, 0.012, [blinker], (-0.60 + k * 0.30, K0 - 0.20, 3.27), n=3))
    # Kuehlergrill: schwarzer Einsatz mit Querlamellen
    kabine.append(B.rundkasten('kab_grill', 1.84, 0.04, 0.78, 0.05, [schwarz], (0, K0 - 0.012, 1.12), n=6))
    for k in range(8):
        kabine.append(B.kasten(f'kab_lamelle_{k}', 1.74, 0.03, 0.026, [chrom if k == 4 else anthrazit], 0.004, (0, K0 - 0.032, 1.17 + k * 0.088)))
    # Eckleitbleche vorn und Seitenverlaengerung zur Luecke vor dem Auflieger
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        kabine.append(B.rundkasten(f'kab_seitenverl_{sl}', 0.035, 0.55, 2.10, 0.015, [lack[0]], (sx * (BK / 2 - 0.02), K1 + 0.25, 1.55), n=4))
    # Tueren (Blech leicht abgesetzt, Fuge sichtbar) mit Fenster, Griff, Spiegel
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        kabine.append(B.rundkasten(f'kab_tuer_{sl}', 0.012, 1.10, 1.78, 0.03, [lack[0]], (sx * (BK / 2 + 0.002), K0 + 0.72, 1.30), n=6))
        kabine.append(B.rundkasten(f'kab_fenster_{sl}', 0.012, 0.92, 0.72, 0.04, [scheibe], (sx * (BK / 2 + 0.009), K0 + 0.74, 2.26), n=6))
        kabine.append(B.kasten(f'kab_griff_{sl}', 0.03, 0.22, 0.05, [schwarz], 0.01, (sx * (BK / 2 + 0.02), K0 + 1.05, 2.02)))
        kabine.append(B.rohr(f'kab_spiegelarm_{sl}', [(sx * (BK / 2), K0 + 0.28, 2.95), (sx * (BK / 2 + 0.22), K0 + 0.18, 2.95),
                                                      (sx * (BK / 2 + 0.24), K0 + 0.16, 2.60)], 0.018, [schwarz]))
        kabine.append(B.rundkasten(f'kab_spiegel_{sl}', 0.10, 0.28, 0.46, 0.04, [schwarz], (sx * (BK / 2 + 0.26), K0 + 0.15, 2.30), n=6))
        kabine.append(B.kasten(f'kab_spiegelglas_{sl}', 0.012, 0.24, 0.40, [chrom], 0.002, (sx * (BK / 2 + 0.26), K0 + 0.30, 2.33)))
        # Einstieg: zwei Stufen
        for k, zz in enumerate((0.48, 0.86)):
            kabine.append(B.kasten(f'kab_stufe_{sl}_{k}', 0.24, 0.52, 0.035, [anthrazit], 0.006, (sx * (BK / 2 - 0.14), K0 + 0.72, zz)))
        kabine.append(B.rundkasten(f'kab_stufenkasten_{sl}', 0.06, 0.60, 0.70, 0.02, [schwarz], (sx * (BK / 2 - 0.03), K0 + 0.72, 0.42), n=4))
        # Radlauf vorn
        kabine.append(B.rundkasten(f'kab_radlauf_{sl}', 0.34, 1.20, 0.12, 0.05, [schwarz], (sx * 1.03, Y_VA, 1.06), n=4))
    # Stossfaenger mit Scheinwerfern
    kabine.append(B.rundkasten('kab_stoss', BK - 0.02, 0.34, 0.54, 0.08, [anthrazit], (0, K0 + 0.12, 0.48), n=8))
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        kabine.append(B.rundkasten(f'kab_scheinwerfer_{sl}', 0.46, 0.05, 0.16, 0.04, [scheinwerfer], (sx * 0.92, K0 - 0.06, 0.82), n=4))
        kabine.append(B.rundkasten(f'kab_blinker_{sl}', 0.10, 0.04, 0.10, 0.03, [blinker], (sx * 1.15, K0 - 0.05, 0.84), n=4))
        kabine.append(B.rundkasten(f'kab_nebel_{sl}', 0.16, 0.04, 0.07, 0.03, [scheinwerfer], (sx * 0.95, K0 - 0.06, 0.60), n=4))
    B.angel('kab_angel', (0, K0 + 0.10, 1.00), (-1, 0, 0), 48.0, kabine)          # kippt nach vorn

    # Tank rechts, Batterie-/AdBlue-Kasten links, Luftansaugung hinter der Kabine
    tank = B.rundkasten('zm_tank', 0.62, 1.50, 0.60, 0.22, [alu], (0.92, (Y_VA + Y_HA) / 2 + 0.2, 0.36), n=10)
    B.rundkasten('zm_batterie', 0.50, 1.10, 0.55, 0.05, [anthrazit], (-0.88, (Y_VA + Y_HA) / 2 + 0.2, 0.40), n=6)
    for sx in (-1, 1):
        B.kasten(f'zm_tankhalter_{sx}_{0}', 0.66 if sx > 0 else 0.54, 0.05, 0.06, [anthrazit], 0.004, (sx * 0.90, (Y_VA + Y_HA) / 2 - 0.3, 0.33))
    B.rundkasten('zm_ansaugung', 0.34, 0.30, 1.60, 0.10, [anthrazit], (-1.00, K1 + 0.20, 2.10), n=6)
    # Sattelkupplung
    B.drehkoerper('zm_sattelplatte', [(0.0, 1.07), (0.44, 1.07), (0.45, 1.09), (0.44, 1.16), (0.0, 1.16)], 48, [anthrazit]).location = (0, Y_SK, 0)
    # Kotfluegel hinten (Halbschalen) und Schmutzfaenger
    for sx in (-1, 1):
        B.rundkasten(f'zm_kotfluegel_{sx}', 0.72, 1.20, 0.08, 0.03, [schwarz], (sx * 0.95, Y_HA, 1.10), n=4)
        B.kasten(f'zm_schmutzfaenger_{sx}', 0.62, 0.012, 0.50, [schwarz], 0.004, (sx * 0.95, Y_HA + 0.62, 0.25))
        B.rundkasten(f'zm_rueckleuchte_{sx}', 0.30, 0.06, 0.12, 0.02, [rueck], (sx * 0.80, Y_HA + 1.05, 0.80), n=4)

    # Raeder Zugmaschine
    stoffe_r = (reifen, felge, nabe)
    R_ZM = 0.507                                  # 315/70 R22.5
    for sx in (-1, 1):
        rad_einheit(f'rad_va_{"l" if sx < 0 else "r"}', sx * 1.02, Y_VA, R_ZM, 0.315, 0.286, stoffe_r, sx)
        rad_einheit(f'rad_ha_{"l" if sx < 0 else "r"}_aussen', sx * 1.10, Y_HA, R_ZM, 0.315, 0.286, stoffe_r, sx)
        rad_einheit(f'rad_ha_{"l" if sx < 0 else "r"}_innen', sx * 0.76, Y_HA, R_ZM, 0.315, 0.286, stoffe_r, -sx)
    for yy in (Y_VA, Y_HA):
        B.drehkoerper(f'zm_achse_{int(yy * 10)}', [(0.0, -0.95), (0.06, -0.95), (0.06, 0.95), (0.0, 0.95)], 16, [anthrazit]).rotation_euler = (0, math.radians(90), 0)
        bpy.context.scene.objects[f'zm_achse_{int(yy * 10)}'].location = (0, yy, R_ZM)

    # ============================================================== Auflieger
    # Chassis: Laengstraeger, Aussenrahmen, Querträger
    # vorn ueber der Sattelkupplung flach (Kupplungsplatte), dahinter tief
    YT = Y_A0 + 3.00
    B.kasten('af_kupplungsplatte', 1.30, YT - Y_A0 - 0.05, 0.09, [anthrazit], 0.004, (0, (Y_A0 + YT) / 2, 1.08))
    for sx in (-1, 1):
        B.kasten(f'af_traeger_vorn_{sx}', 0.14, YT - Y_A0 - 0.05, 0.10, [anthrazit], 0.006, (sx * 0.55, (Y_A0 + YT) / 2, 1.07))
        B.kasten(f'af_traeger_{sx}', 0.14, Y_A1 - YT - 0.15, 0.45, [anthrazit], 0.006, (sx * 0.55, (YT + Y_A1 - 0.15) / 2, 0.72))
        B.kasten(f'af_aussenrahmen_{sx}', 0.06, Y_A1 - Y_A0, 0.16, [anthrazit], 0.006, (sx * (BREITE / 2 - 0.03), (Y_A0 + Y_A1) / 2, 1.04))
    B.kasten('af_ladeboden', BREITE - 0.12, Y_A1 - Y_A0 - 0.02, 0.03, [holz], 0.003, (0, (Y_A0 + Y_A1) / 2, H_DECK - 0.03))
    # Stirnwand, Dach, Heckportal, Tueren
    B.kasten('af_stirnwand', BREITE, 0.05, H_DACH - 1.00, [alu], 0.008, (0, Y_A0 + 0.025, 1.00))
    for sx in (-1, 1):
        B.kasten(f'af_dachholm_{sx}', 0.06, Y_A1 - Y_A0, 0.10, [alu], 0.006, (sx * (BREITE / 2 - 0.03), (Y_A0 + Y_A1) / 2, H_DACH - 0.12))
    dach = B.kasten('af_dach', BREITE - 0.02, Y_A1 - Y_A0 - 0.04, 0.02, [dachplane[0]], 0.004, (0, (Y_A0 + Y_A1) / 2, H_DACH - 0.03))
    B.kasten('af_heck_oben', BREITE, 0.10, 0.20, [alu], 0.006, (0, Y_A1 - 0.05, H_DACH - 0.20))
    for sx in (-1, 1):
        B.kasten(f'af_heckpfosten_{sx}', 0.10, 0.10, H_DACH - H_DECK, [alu], 0.006, (sx * (BREITE / 2 - 0.05), Y_A1 - 0.05, H_DECK))
        tuer = B.kasten(f'af_hecktuer_{sx}', BREITE / 2 - 0.11, 0.04, H_DACH - H_DECK - 0.22, [alu], 0.004,
                        (sx * (BREITE / 4 - 0.005), Y_A1 + 0.01, H_DECK + 0.02))
        for k in (-1, 1):
            B.drehkoerper(f'af_verschluss_{sx}_{k}', [(0.0, H_DECK + 0.04), (0.018, H_DECK + 0.04), (0.018, H_DACH - 0.24), (0.0, H_DACH - 0.24)], 12,
                          [chrom]).location = (sx * (BREITE / 4 + k * 0.22 - 0.005), Y_A1 + 0.05, 0)
    # Rungen (Pfosten) je Seite
    rungen_y = [Y_A0 + 3.40, Y_A0 + 6.80, Y_A0 + 10.20]
    for sx in (-1, 1):
        for k, yy in enumerate(rungen_y):
            B.kasten(f'af_runge_{sx}_{k}', 0.08, 0.10, H_DACH - 0.12 - H_DECK, [alu], 0.006, (sx * (BREITE / 2 - 0.06), yy, H_DECK))
    # Planen: je Seite 8 Felder, die sich beim Oeffnen nach hinten stapeln
    gurte = list(np.arange(Y_A0 + 0.30, Y_A1 - 0.1, 0.62))
    N = 8
    grenzen_ = np.linspace(Y_A0 + 0.06, Y_A1 - 0.10, N + 1)
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        for i in range(N):
            ya, yb = grenzen_[i], grenzen_[i + 1]
            pf = plane_gitter(f'plane_{sl}_{i}', sx * (BREITE / 2 + 0.004 + 0.004 * i), ya, yb + 0.03, 1.02, H_DACH - 0.10, plane[0], sx,
                              [g for g in gurte if ya - 0.01 <= g <= yb + 0.04],
                              (ya - Y_A0) / (Y_A1 - Y_A0), (yb + 0.03 - Y_A0) / (Y_A1 - Y_A0))
            for g in [g for g in gurte if ya <= g < yb]:
                B.kasten(f'gurt_{sl}_{i}_{int(g * 100)}', 0.006, 0.05, H_DACH - 0.10 - 1.00, [gurt], 0.001,
                         (sx * (BREITE / 2 + 0.022 + 0.004 * i), g, 1.00)).parent = pf
                B.kasten(f'gurtschloss_{sl}_{i}_{int(g * 100)}', 0.02, 0.06, 0.10, [chrom], 0.003,
                         (sx * (BREITE / 2 + 0.03 + 0.004 * i), g, 1.02)).parent = pf
    # Stuetzwinde, Unterfahrschutz, Kotfluegel, Heck
    for sx in (-1, 1):
        B.kasten(f'af_stuetze_{sx}', 0.12, 0.12, 0.62, [anthrazit], 0.006, (sx * 0.62, Y_A0 + 3.70, 0.30))
        B.kasten(f'af_stuetzfuss_{sx}', 0.26, 0.26, 0.04, [anthrazit], 0.006, (sx * 0.62, Y_A0 + 3.70, 0.26))
        for zz in (0.46, 0.66):
            B.kasten(f'af_seitenschutz_{sx}_{int(zz * 100)}', 0.03, Y_ACHSEN[0] - 0.75 - (Y_A0 + 4.20), 0.10, [alu], 0.004,
                     (sx * (BREITE / 2 - 0.04), (Y_ACHSEN[0] - 0.75 + Y_A0 + 4.20) / 2, zz))
        B.rundkasten(f'af_kotfluegel_{sx}', 0.52, Y_ACHSEN[2] - Y_ACHSEN[0] + 1.30, 0.06, 0.02, [schwarz],
                     (sx * 1.02, (Y_ACHSEN[0] + Y_ACHSEN[2]) / 2, 1.16), n=4)
        B.rundkasten(f'af_rueckleuchte_{sx}', 0.42, 0.06, 0.14, 0.02, [rueck], (sx * 0.95, Y_A1 + 0.02, 0.62), n=4)
    B.kasten('af_unterfahrschutz', 2.30, 0.12, 0.12, [alu], 0.006, (0, Y_A1 - 0.10, 0.44))
    B.kasten('af_heckleiste', BREITE, 0.10, 0.18, [anthrazit], 0.006, (0, Y_A1 - 0.05, 1.02))
    R_AF = 0.536
    for k, yy in enumerate(Y_ACHSEN):
        for sx in (-1, 1):
            rad_einheit(f'rad_af{k}_{"l" if sx < 0 else "r"}', sx * 1.04, yy, R_AF, 0.385, 0.286, stoffe_r, sx)
        B.drehkoerper(f'af_achse_{k}', [(0.0, -0.95), (0.065, -0.95), (0.065, 0.95), (0.0, 0.95)], 16, [anthrazit]).rotation_euler = (0, math.radians(90), 0)
        bpy.context.scene.objects[f'af_achse_{k}'].location = (0, yy, R_AF)

    # ============================================================== Ladung: Europaletten, foliert
    pal = []
    # Palette als ein Netz: 3 Deckbretter-Lagen vereinfacht zu Klotzfuessen + Deckbrett
    def palette_netz():
        teile = []
        for ix in (-0.55, 0.0, 0.55):
            for iy in (-0.35, 0.0, 0.35):
                teile.append(B.kasten('_pk', 0.145, 0.10, 0.078, [palette], 0.003, (ix, iy, 0.022)))
        for iy in (-0.35, 0.0, 0.35):
            teile.append(B.kasten('_pl', 1.20, 0.10, 0.022, [palette], 0.002, (0, iy, 0.0)))
        for ix in (-0.55, 0.0, 0.55):
            teile.append(B.kasten('_pq', 0.145, 0.80, 0.022, [palette], 0.002, (ix, 0, 0.100)))
        for k in range(5):
            teile.append(B.kasten('_pd', 1.20, 0.10 if k % 2 == 0 else 0.145, 0.022, [palette], 0.002, (0, -0.35 + k * 0.175, 0.122)))
        with bpy.context.temp_override(active_object=teile[0], selected_objects=teile, selected_editable_objects=teile, object=teile[0]):
            bpy.ops.object.join()
        p = teile[0]; p.name = 'palette_vorlage'; p.data.name = 'palette_netz'
        # Ursprung in die Palettenmitte: Nach dem Zusammenfuegen lag er am
        # ersten Klotz, gedreht standen die Paletten dann seitlich heraus (Probe).
        p.data.transform(p.matrix_world); p.location = (0, 0, 0)
        return p
    pv = palette_netz()
    ladung = B.rundkasten('ladung_vorlage', 1.18, 0.78, 1.30, 0.03, [karton], (0, 0, 0.144), n=4)
    film = B.rundkasten('folie_vorlage', 1.21, 0.81, 1.36, 0.05, [folie], (0, 0, 0.12), n=6)
    reihen = 8
    k = 0
    for r in range(reihen):
        for c in (-1, 0, 1):
            if r == reihen - 1 and c == 1:
                continue
            x = c * 0.81; y = Y_A0 + 0.70 + r * 1.23
            for vorlage, nm in ((pv, 'palette'), (ladung, 'ladung'), (film, 'folie')):
                o = bpy.data.objects.new(f'{nm}_{k}', vorlage.data); bpy.context.scene.collection.objects.link(o)
                o.location = (x + vorlage.location.x, y + vorlage.location.y, H_DECK + vorlage.location.z)
                o.rotation_euler = (0, 0, math.radians(90))
            k += 1
    for v in (pv, ladung, film):
        bpy.data.objects.remove(v, do_unlink=True)

    zuordnung = {lack[0]: lack, plane[0]: plane, dachplane[0]: dachplane}
    B.exportieren('lkw', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; Sattelzug nach EU-Massen, keine Marke')
    if not web:
        B.speichern('lkw')
