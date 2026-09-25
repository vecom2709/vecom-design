"""pr_arbeiten.py -- „Umgesetzte Arbeiten" als Fotorender (Umbau 24.09.2026).

Uwe: „Mache umgesetzte Arbeiten viel hyperrealistischer" -- auf die
Vorschläge Ja zu R1 (echte Geräte statt gezeichneter Rahmen), R2 (jedes
Projekt in seiner Welt), R3 (live im Fotorender) und R4 (Kamerafahrt).

Je Projekt eine Szene: markenloser Laptop und Telefon mit der echten
Kundenseite auf dem Bildschirm, auf einem Tisch in einem Raum, der zur
Branche passt, Tageslicht durchs Fenster (oder Abendlampe), Schärfentiefe
wie mit einer 50-mm-Optik bei f/2,8.

Aufruf (Blender, Hintergrund):
  blender -b -P pr_arbeiten.py -- <projekt>,<modus>[,prozent=..,samples=..]
  projekt: cavaleri | jonika | mensaena | trendonix
  modus:   probe  -- ein kleines Bild zum Ansehen
           voll   -- Standbild 2400 x 1350 (an), dazu dasselbe mit dunklem
                     Bildschirm (aus, für den Glanz über dem Live-Bild) und
                     ecken.json (Bildschirmecken in Pixeln)
           fahrt  -- 3 s Kamerafahrt auf das Standbild zu (72 Bilder)

Maße nach realen Geräten (14"-Laptop 312 x 221 x 15,5 mm, 16:10-Anzeige;
Telefon 71,5 x 147 x 7,8 mm). Einheiten: Meter.
"""
import bpy, bmesh, math, os, sys, json


def ziel_v(v):
    return (float(v[0]), float(v[1]), float(v[2]))
import numpy as np
from mathutils import Vector, Matrix
from bpy_extras.object_utils import world_to_camera_view

HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HIER)
import pr_basis as B

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
PROJ = argv[0] if argv else 'cavaleri'
MODUS = argv[1] if len(argv) > 1 else 'probe'
EXTRA = dict(t.split('=', 1) for t in argv[2:] if '=' in t)
P = B.P
AUS = os.path.join(P, 'render', 'arbeiten', PROJ)
TEXA = os.path.join(P, 'quelle', 'tex', 'arbeiten')
os.makedirs(AUS, exist_ok=True)
RNG = np.random.default_rng(20260924)
TISCH_Z = 0.755
# Anteile des Browserrahmens im Bildschirmbild (arbeiten-texturen, Cloud):
# Laptop 76 von 900 CSS-Pixeln oben; Telefon 47 oben (Statusleiste), 70 unten
LAPTOP_CHROME = 76 / 900
TEL_OBEN, TEL_UNTEN = 47 / 844, 70 / 844


# ------------------------------------------------------------------ Hilfen
def rrect(w, h, r, n=10, cx=0.0, cy=0.0):
    """Abgerundetes Rechteck, gegen den Uhrzeigersinn."""
    pts = []
    for (mx, my, a0) in ((w / 2 - r, h / 2 - r, 0), (-w / 2 + r, h / 2 - r, 90), (-w / 2 + r, -h / 2 + r, 180), (w / 2 - r, -h / 2 + r, 270)):
        for i in range(n + 1):
            a = math.radians(a0 + 90 * i / n)
            pts.append((cx + mx + r * math.cos(a), cy + my + r * math.sin(a)))
    return pts


def uv_normieren(o, spiegel_u=False):
    """UV auf 0..1 über die Ausdehnung in x/y -- für Bildschirminhalte."""
    me = o.data
    xs = [v.co.x for v in me.vertices]; ys = [v.co.y for v in me.vertices]
    x0, x1, y0, y1 = min(xs), max(xs), min(ys), max(ys)
    uvl = me.uv_layers[0]
    for poly in me.polygons:
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co
            u = (co.x - x0) / (x1 - x0)
            uvl.data[li].uv = (1 - u if spiegel_u else u, (co.y - y0) / (y1 - y0))


def eltern(kind, vater):
    kind.parent = vater
    return kind


def bild_laden(pfad, farbe=True):
    im = bpy.data.images.load(pfad, check_existing=True)
    im.colorspace_settings.name = 'sRGB' if farbe else 'Non-Color'
    return im


def rausch_rauheit(m, grund, spanne, skala=180.0):
    """Feine Rauheitsschwankung (Fingerabdrücke, Eloxal) -- kein Material ist
    überall gleich glatt (Reality Check: Mikroebene)."""
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    n = nt.nodes.new('ShaderNodeTexNoise'); n.inputs['Scale'].default_value = skala
    n.inputs['Detail'].default_value = 6.0
    mr = nt.nodes.new('ShaderNodeMapRange')
    mr.inputs['To Min'].default_value = grund - spanne; mr.inputs['To Max'].default_value = grund + spanne
    nt.links.new(n.outputs['Fac'], mr.inputs['Value']); nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    return m


# ------------------------------------------------------------------ Stoffe
def stoffe():
    S = {}
    S['alu'] = rausch_rauheit(B.stoff('Alu Space Grey', (0.27, 0.28, 0.30), rau=0.34, metall=1.0), 0.34, 0.05)
    S['alu_kante'] = B.stoff('Alu Diamantschliff', (0.62, 0.63, 0.65), rau=0.12, metall=1.0)
    S['tasten'] = rausch_rauheit(B.stoff('Tasten', (0.012, 0.012, 0.013), rau=0.55), 0.55, 0.08, 400)
    S['wanne'] = B.stoff('Tastenwanne', (0.02, 0.02, 0.022), rau=0.7)
    S['rand'] = rausch_rauheit(B.stoff('Glas Rahmen', (0.004, 0.004, 0.005), rau=0.05, spec=0.5, coat=1.0, coat_rau=0.02), 0.05, 0.03, 60)
    S['gummi'] = B.stoff('Gummi', (0.01, 0.01, 0.01), rau=0.8)
    return S


def bildschirm_stoff(name, pfad, staerke):
    """Anzeige: schwarzes Deckglas mit Spiegelung, darunter das Bild als
    Emission. staerke=0 ergibt den dunklen Bildschirm für den Glanz-Durchgang."""
    m = B.stoff(name, (0.003, 0.003, 0.004), rau=0.04, spec=0.5, coat=1.0, coat_rau=0.015)
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    t = nt.nodes.new('ShaderNodeTexImage'); t.image = bild_laden(pfad, True)
    t.interpolation = 'Cubic'
    nt.links.new(t.outputs['Color'], b.inputs['Emission Color'])
    b.inputs['Emission Strength'].default_value = staerke
    rausch_rauheit(m, 0.04, 0.02, 90)
    return m


# ------------------------------------------------------------------ Geräte
def laptop(name, ort, dreh_z, bild, S, oeffnung=112.0, hell=1.0):
    """14"-Laptop. ort = Mitte der Unterseite auf dem Tisch, Scharnier hinten
    (+y). Gibt (Wurzel, Ecken der Anzeige in Weltkoordinaten) zurück."""
    w0 = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(w0)
    w0.location = ort; w0.rotation_euler = (0, 0, dreh_z)
    W, T, H = 0.312, 0.221, 0.0155
    unterteil = B.prisma(name + '_unterteil', rrect(W, T, 0.011), 0.0, H, [S['alu']], fase=0.0014, segmente=3)
    eltern(unterteil, w0)
    # Füße
    for fx in (-0.12, 0.12):
        for fy in (-0.085, 0.085):
            f = B.prisma(name + '_fuss', B.kreis(0.006, 24, (fx, fy)), -0.0012, 0.0002, [S['gummi']], fase=0.0004)
            eltern(f, w0)
    # Tastaturwanne und Tasten (kurzhubig, schwarz, 16,2 mm Kappe auf 19 mm Raster)
    wanne = B.prisma(name + '_wanne', rrect(0.281, 0.112, 0.004, cy=0.035), H - 0.0006, H + 0.00005, [S['wanne']])
    eltern(wanne, w0)
    kappe = 0.0162; raster = 0.0190
    zeilen = [(14, 0.75), (14, 1.0), (14, 1.0), (13, 1.0), (12, 1.0), (10, 1.0)]
    y = 0.035 + 0.112 / 2 - 0.0085
    for r_i, (n, hoehe) in enumerate(zeilen):
        hk = kappe * hoehe * (0.62 if r_i == 0 else 1.0)
        breiten = [1.0] * n
        if r_i == 1: breiten[-1] = 1.5
        if r_i == 2: breiten[0] = 1.5
        if r_i == 3: breiten[0] = 1.8; breiten[-1] = 1.8
        if r_i == 4: breiten[0] = 2.3; breiten[-1] = 2.3
        if r_i == 5: breiten = [1, 1, 1, 1.25, 5.2, 1.25, 1, 1, 1, 1]
        gesamt = sum(breiten) * raster - (raster - kappe)
        x = -gesamt / 2
        for bw in breiten:
            kb = bw * raster - (raster - kappe)
            k = B.prisma(name + '_taste', rrect(kb, hk, 0.0018, 4, cx=x + kb / 2, cy=y - hk / 2), H - 0.0004, H + 0.0006, [S['tasten']], fase=0.00025, segmente=2)
            eltern(k, w0)
            x += bw * raster
        y -= hk + (raster - kappe) * (0.9 if r_i == 0 else 1.0)
    # Trackpad: eigenes, etwas stumpferes Glas in Gehäusefarbe
    tp = B.prisma(name + '_trackpad', rrect(0.132, 0.080, 0.006, cy=-0.063), H - 0.0002, H + 0.00003,
                  [rausch_rauheit(B.stoff('Trackpad', (0.44, 0.45, 0.47), rau=0.35, metall=0.9), 0.36, 0.04, 300)])
    eltern(tp, w0)
    # Deckel: geschlossen gebaut (Anzeige nach unten), dann am Scharnier geöffnet
    angel_ = bpy.data.objects.new(name + '_angel', None); bpy.context.scene.collection.objects.link(angel_)
    eltern(angel_, w0); angel_.location = (0, T / 2 - 0.006, H)
    D = 0.0058; LT = 0.215
    deckel = B.prisma(name + '_deckel', rrect(W, LT, 0.011, cy=-LT / 2), 0.0, D, [S['alu']], fase=0.0012, segmente=3)
    eltern(deckel, angel_)
    rahmen = B.prisma(name + '_rahmen', rrect(W - 0.0035, LT - 0.0035, 0.0095, cy=-LT / 2), -0.00025, 0.0001, [S['rand']])
    eltern(rahmen, angel_)
    AW, AH = 0.2960, 0.1850                      # sichtbare Fläche, 16:10
    ay0 = -0.0235                                 # Kinn am Scharnier
    anz = B.prisma(name + '_anzeige', rrect(AW, AH, 0.0012, 3, cy=ay0 - AH / 2), -0.00032, -0.00026,
                   [bildschirm_stoff(name + ' Anzeige', bild, 2.4 * hell)])
    eltern(anz, angel_)
    # Drehung um x spiegelt x nicht: von vorn liegt +x rechts
    uv_normieren(anz)
    # v: oben im Bild = Deckelkante (y klein) -> umdrehen
    uvl = anz.data.uv_layers[0]
    for d in uvl.data:
        d.uv = (d.uv[0], 1 - d.uv[1])
    scharnier = B.drehkoerper(name + '_scharnier', [(0.0, -0.13), (0.0042, -0.13), (0.0042, 0.13), (0.0, 0.13)], 32, [S['alu']])
    eltern(scharnier, w0); scharnier.rotation_euler = (0, math.radians(90), 0); scharnier.location = (0, T / 2 - 0.006, H - 0.001)
    angel_.rotation_euler = (math.radians(-oeffnung), 0, 0)
    bpy.context.view_layer.update()
    ecken_lokal = [(-AW / 2, ay0 - AH, -0.00032), (AW / 2, ay0 - AH, -0.00032), (AW / 2, ay0, -0.00032), (-AW / 2, ay0, -0.00032)]
    # Reihenfolge fürs Web: oben links, oben rechts, unten rechts, unten links
    M = angel_.matrix_world
    ol, orr, ur, ul = [M @ Vector(e) for e in ecken_lokal]
    return w0, [ol, orr, ur, ul]


def staender(name, ort, dreh_z, neigung, S):
    """Tischständer aus Aluminium: Fuß, Lippe vorn, Rückenplatte in der
    Neigung des Telefons. Flach liegend spiegelte das Telefon das ganze
    Fenster und zeigte nur Weiß (Probe 24.09.2026)."""
    w0 = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(w0)
    w0.location = (ort[0], ort[1], TISCH_Z); w0.rotation_euler = (0, 0, dreh_z)
    fuss = B.prisma(name + '_fuss', rrect(0.078, 0.085, 0.008), 0.0, 0.006, [S['alu']], fase=0.0012); eltern(fuss, w0)
    lippe = B.kasten(name + '_lippe', 0.078, 0.008, 0.010, [S['alu']], fase=0.0015, ort=(0, -0.030, 0.006)); eltern(lippe, w0)
    ruecken = B.prisma(name + '_ruecken', rrect(0.060, 0.105, 0.006, cy=0.0525), 0.0, 0.005, [S['alu']], fase=0.001)
    eltern(ruecken, w0); ruecken.location = (0, -0.018, 0.006); ruecken.rotation_euler = (neigung, 0, 0)
    return w0


def telefon(name, ort, dreh_z, bild, S, hell=1.0, neigung=0.0):
    """Telefon, Anzeige oben. neigung (rad) kippt die Oberkante nach hinten:
    Das Telefon lehnt dann im Ständer und schaut zur Kamera."""
    w0 = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(w0)
    W, L, H = 0.0715, 0.1470, 0.0078
    if neigung:
        z = ort[2] + 0.009 + (L / 2) * math.sin(neigung) + 0.0005
        # Unterkante liegt hinter der Lippe des Ständers (y = -0,025)
        w0.location = (ort[0], ort[1] - 0.025 + (L / 2) * math.cos(neigung), z)
    else:
        w0.location = ort
    w0.rotation_euler = (neigung, 0, dreh_z)
    rahmen = B.prisma(name + '_rahmen', rrect(W, L, 0.0095), 0.0, H, [S['alu_kante']], fase=0.0007, segmente=3)
    eltern(rahmen, w0)
    glas = B.prisma(name + '_glas', rrect(W - 0.0012, L - 0.0012, 0.0089), H - 0.0003, H + 0.00012, [S['rand']], fase=0.00035, segmente=2)
    eltern(glas, w0)
    AW, AL = 0.0665, 0.1440
    anz = B.prisma(name + '_anzeige', rrect(AW, AL, 0.0078), H + 0.00013, H + 0.00016,
                   [bildschirm_stoff(name + ' Anzeige', bild, 2.0 * hell)])
    eltern(anz, w0); uv_normieren(anz)
    bpy.context.view_layer.update()
    M = w0.matrix_world; z = H + 0.00016
    e = [(-AW / 2, AL / 2, z), (AW / 2, AL / 2, z), (AW / 2, -AL / 2, z), (-AW / 2, -AL / 2, z)]
    return w0, [M @ Vector(p) for p in e]


# ------------------------------------------------------------------ Raum
def raum(stil):
    """Tisch, Boden, Wand mit Fenster, Decke. stil: tag | abend."""
    S = {}
    holz = B.stoff('Tisch Eiche', (1, 1, 1), rau=0.42, farbkarte='kueche-eiche-farbe', normal='kueche-eiche-normal', normal_staerke=0.3, kachel=4.8)
    rausch_rauheit(holz, 0.44, 0.08, 30)
    tisch = B.kasten('tisch', 1.9, 0.95, 0.035, [holz], fase=0.003, ort=(0.1, 0.05, TISCH_Z - 0.035))
    wand_m = B.stoff('Putz', (0.72, 0.70, 0.66), rau=0.92)
    boden_m = B.stoff('Boden', (0.30, 0.27, 0.24), rau=0.6)
    B.kasten('boden', 8, 8, 0.02, [boden_m], fase=0, ort=(0, 0, -0.02))
    B.kasten('decke', 8, 8, 0.02, [wand_m], fase=0, ort=(0, 0, 2.8))
    # Rückwand mit Fenster (1,8 x 1,5 m, Brüstung 0,85 m)
    WY = 1.35; FX0, FX1, FZ0, FZ1 = -0.55, 1.25, 0.85, 2.35
    for (x0, x1, z0, z1) in ((-4, FX0, 0, 2.8), (FX1, 4, 0, 2.8), (FX0, FX1, 0, FZ0), (FX0, FX1, FZ1, 2.8)):
        B.kasten('wand', x1 - x0, 0.22, z1 - z0, [wand_m], fase=0.002, ort=((x0 + x1) / 2, WY + 0.11, z0))
    rahmen_m = B.stoff('Fensterrahmen', (0.16, 0.16, 0.17), rau=0.4, metall=0.6)
    for (cx, cz, gx, gz) in (((FX0 + FX1) / 2, FZ0, FX1 - FX0, 0.05), ((FX0 + FX1) / 2, FZ1 - 0.05, FX1 - FX0, 0.05),
                             (FX0 + 0.025, FZ0, 0.05, FZ1 - FZ0), (FX1 - 0.025, FZ0, 0.05, FZ1 - FZ0), ((FX0 + FX1) / 2, FZ0, 0.045, FZ1 - FZ0)):
        B.kasten('fensterrahmen', gx, 0.07, gz, [rahmen_m], fase=0.003, ort=(cx, WY + 0.02, cz))
    # Seitenwand links, damit das Licht im Raum bleibt
    B.kasten('wand_links', 0.2, 8, 2.8, [wand_m], fase=0, ort=(-2.2, 0, 0))
    # Draußen
    B.kasten('aussen', 60, 60, 0.02, [B.stoff('Aussen Boden', (0.16, 0.15, 0.13), rau=0.9)], fase=0, ort=(0, 32, -0.4))
    sc = bpy.context.scene
    welt = bpy.data.worlds.new('Himmel'); sc.world = welt; welt.use_nodes = True
    nt = welt.node_tree; bg = nt.nodes['Background']
    himmel = nt.nodes.new('ShaderNodeTexSky')
    try:
        himmel.sky_type = 'NISHITA'; himmel.sun_disc = False
        himmel.sun_elevation = math.radians(24 if stil == 'tag' else 2)
        himmel.sun_rotation = math.radians(200)
    except Exception:
        pass
    nt.links.new(himmel.outputs['Color'], bg.inputs['Color'])
    bg.inputs['Strength'].default_value = 0.30 if stil == 'tag' else 0.035
    if stil == 'tag':
        sd = bpy.data.lights.new('Sonne', 'SUN'); sd.energy = 3.2; sd.angle = math.radians(0.6)
        sd.color = (1.0, 0.95, 0.88)
        so = bpy.data.objects.new('Sonne', sd); sc.collection.objects.link(so)
        # durchs Fenster schräg von hinten links auf den Tisch
        so.rotation_euler = (math.radians(58), math.radians(0), math.radians(160))
    return S


# ------------------------------------------------------------------ Dinge
def tasse(name, ort, farbe=(0.93, 0.92, 0.89)):
    kera = B.stoff(name + ' Porzellan', farbe, rau=0.12, coat=0.6, coat_rau=0.05)
    prof = [(0.0, 0.0), (0.021, 0.0), (0.026, 0.004), (0.030, 0.040), (0.032, 0.058), (0.0295, 0.058), (0.0275, 0.042), (0.0235, 0.007), (0.0, 0.007)]
    t = B.drehkoerper(name, prof, 64, [kera]); t.location = (ort[0], ort[1], TISCH_Z + 0.006)
    kaffee = B.drehkoerper(name + '_kaffee', [(0.0, 0.0), (0.0262, 0.0), (0.0, 0.0)], 48, [B.stoff('Kaffee', (0.05, 0.022, 0.01), rau=0.05, spec=0.5)])
    kaffee.location = (ort[0], ort[1], TISCH_Z + 0.006 + 0.036)
    unter = B.drehkoerper(name + '_unter', [(0.0, 0.0), (0.050, 0.0), (0.060, 0.006), (0.062, 0.008), (0.058, 0.008), (0.048, 0.004), (0.0, 0.004)], 64, [kera])
    unter.location = (ort[0], ort[1], TISCH_Z)
    henkel = B.rohr(name + '_henkel', [(0.028, 0, 0.046), (0.040, 0, 0.044), (0.043, 0, 0.030), (0.036, 0, 0.018), (0.027, 0, 0.016)], 0.0032, [kera])
    henkel.location = (ort[0], ort[1], TISCH_Z + 0.006); henkel.rotation_euler = (0, 0, ort[2] if len(ort) > 2 else 0)


def papierstapel(name, ort, dreh, n=6, farbe=(0.86, 0.85, 0.82)):
    pap = rausch_rauheit(B.stoff(name + ' Papier', farbe, rau=0.8, sheen=0.2), 0.8, 0.06, 40)
    for i in range(n):
        k = B.kasten(name, 0.210, 0.297, 0.0006, [pap], fase=0.0001,
                     ort=(ort[0] + RNG.normal(0, 0.003), ort[1] + RNG.normal(0, 0.003), TISCH_Z + i * 0.0007))
        k.rotation_euler = (0, 0, dreh + RNG.normal(0, 0.02))


def buch(name, ort, dreh, gx, gy, gz, farbe, rau=0.55, titel_bild=None):
    """Gebundenes Buch: Deckel mit Stoffbezug, Seitenschnitt cremefarben."""
    bezug = rausch_rauheit(B.stoff(name + ' Bezug', farbe, rau=rau, sheen=0.3), rau, 0.08, 120)
    seiten = B.stoff(name + ' Schnitt', (0.80, 0.76, 0.66), rau=0.85)
    innen = B.kasten(name + '_block', gx - 0.006, gy - 0.004, gz - 0.004, [seiten], fase=0.0005, ort=(0.003, 0, 0.002))
    d_u = B.kasten(name + '_deckel_u', gx, gy, 0.0022, [bezug], fase=0.0006)
    d_o = B.kasten(name + '_deckel_o', gx, gy, 0.0022, [bezug], fase=0.0006, ort=(0, 0, gz - 0.0022))
    ruecken = B.kasten(name + '_ruecken', 0.003, gy, gz, [bezug], fase=0.0008, ort=(-gx / 2 + 0.0015, 0, 0))
    w0 = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(w0)
    for o in (innen, d_u, d_o, ruecken):
        o.parent = w0
    if titel_bild:
        m = B.stoff(name + ' Umschlag', (1, 1, 1), rau=0.5)
        nt = m.node_tree; t = nt.nodes.new('ShaderNodeTexImage'); t.image = bild_laden(titel_bild, True)
        nt.links.new(t.outputs['Color'], nt.nodes['Principled BSDF'].inputs['Base Color'])
        um = B.prisma(name + '_umschlag', rrect(gx - 0.001, gy - 0.001, 0.0008, 2), gz, gz + 0.0003, [m])
        uv_normieren(um); um.parent = w0
    w0.location = (ort[0], ort[1], ort[2] if len(ort) > 2 else TISCH_Z); w0.rotation_euler = (0, 0, dreh)
    return w0


def buchstaender(name, ort, dreh, buch_args, neig=72):
    """Aufgestelltes Buch auf einem Acrylständer.

    Warum: Flach liegende Bücher sieht die Kamera (15 Grad über dem Tisch)
    fast streifend. Dann spiegelt der Umschlag die helle Wand, und statt des
    dunklen Covers erscheint eine cremefarbene Fläche (Probe 2 und 3,
    24.09.2026, gemessen am Trendonix-Stapel). Aufgestellt zeigt es sein Cover.
    """
    gx, gy, gz = buch_args[3], buch_args[4], buch_args[5]
    k = math.radians(neig)
    # tiefster Punkt der Lippe in Buchkoordinaten -> Höhe des Ursprungs
    y_l, z_l = -gy / 2 - 0.006, -0.004
    unten = -(y_l * math.sin(k) + z_l * math.cos(k))
    oz = TISCH_Z + 0.004 + unten
    b = buch(name, (ort[0], ort[1], oz), 0.0, *buch_args[3:7], **buch_args[7])
    b.rotation_euler = (k, 0, dreh)
    acryl = B.stoff('Acryl', (1, 1, 1), rau=0.04, trans=1.0, ior=1.49)
    g = bpy.data.objects.new(name + '_staender', None); bpy.context.scene.collection.objects.link(g)
    ruecken = B.kasten(name + '_st_ruecken', gx * 0.7, 0.156, 0.004, [acryl], fase=0.0008, ort=(0, -gy / 2 + 0.072, -0.004))
    lippe = B.kasten(name + '_st_lippe', gx * 0.8, 0.006, gz + 0.012, [acryl], fase=0.0008, ort=(0, -gy / 2 - 0.003, -0.004))
    for o in (ruecken, lippe):
        o.parent = g
    g.location = (ort[0], ort[1], oz); g.rotation_euler = (k, 0, dreh)
    # Fuß: flach auf dem Tisch, von der Lippe nach hinten
    vorn = y_l * math.cos(k) - z_l * math.sin(k)
    mitte = Matrix.Rotation(dreh, 3, 'Z') @ Vector((0, vorn + 0.045, 0))
    fuss = B.kasten(name + '_st_fuss', gx * 0.8, 0.09, 0.004, [acryl], fase=0.0008,
                    ort=(ort[0] + mitte.x, ort[1] + mitte.y, TISCH_Z))
    fuss.rotation_euler = (0, 0, dreh)
    return b


def stift(name, ort, dreh, farbe=(0.03, 0.03, 0.035)):
    m = B.stoff(name, farbe, rau=0.25, metall=0.4)
    s = B.drehkoerper(name, [(0.0, 0.0), (0.0045, 0.004), (0.0048, 0.12), (0.004, 0.138), (0.0, 0.140)], 32, [m])
    s.rotation_euler = (0, math.radians(90), dreh); s.location = (ort[0], ort[1], TISCH_Z + 0.0048)


def lampe(name, ort, farbe_k=2700, staerke=18.0):
    """Schreibtischlampe als warme Flächenleuchte mit Schirm (Abendszenen)."""
    messing = B.stoff('Messing', (0.78, 0.60, 0.34), rau=0.28, metall=1.0)
    fuss = B.drehkoerper(name + '_fuss', [(0.0, 0.0), (0.075, 0.0), (0.078, 0.006), (0.07, 0.014), (0.0, 0.014)], 64, [messing])
    fuss.location = (ort[0], ort[1], TISCH_Z)
    stange = B.rohr(name + '_stange', [(0, 0, 0.012), (0, 0, 0.38), (0.10, 0, 0.44)], 0.006, [messing])
    stange.location = (ort[0], ort[1], TISCH_Z)
    schirm = B.drehkoerper(name + '_schirm', [(0.0, 0.0), (0.03, 0.0), (0.075, -0.07), (0.072, -0.071), (0.028, -0.004), (0.0, -0.004)], 64, [messing])
    schirm.location = (ort[0] + 0.10, ort[1], TISCH_Z + 0.46)
    ld = bpy.data.lights.new(name, 'AREA'); ld.shape = 'DISK'; ld.size = 0.12; ld.energy = staerke
    ld.use_nodes = True
    bb = ld.node_tree.nodes.new('ShaderNodeBlackbody'); bb.inputs['Temperature'].default_value = farbe_k
    ld.node_tree.links.new(bb.outputs['Color'], ld.node_tree.nodes['Emission'].inputs['Color'])
    lo = bpy.data.objects.new(name, ld); bpy.context.scene.collection.objects.link(lo)
    lo.location = (ort[0] + 0.10, ort[1], TISCH_Z + 0.39)


# ------------------------------------------------------------------ Projekte
PROJEKTE = {
    # Spedition in Sizilien: Tag, Lieferscheine, Espresso, draußen der Sattelzug
    'cavaleri': dict(stil='tag', laptop=(0.0, 0.02, 0.0), telefon=(0.285, -0.01, -0.12)),
    # Kinderbücher: Abend, warme Lampe, bunte Bücher
    'jonika': dict(stil='abend', laptop=(0.0, 0.02, 0.05), telefon=(0.285, -0.01, -0.12)),
    # Nachbarschaft: Küchentisch am Morgen
    'mensaena': dict(stil='tag', laptop=(0.0, 0.02, -0.05), telefon=(0.285, -0.01, -0.12)),
    # Sachbuchreihe: Arbeitszimmer am Abend, Bücherstapel
    'trendonix': dict(stil='abend', laptop=(0.0, 0.02, 0.03), telefon=(0.285, -0.01, -0.12)),
}


def dinge(proj):
    if proj == 'cavaleri':
        tasse('espresso', (-0.31, -0.05))
        papierstapel('lieferschein', (-0.33, 0.20), math.radians(12))
        stift('stift', (-0.20, 0.17), math.radians(-30))
        lkw = os.path.join(P, 'quelle', 'lkw.glb')
        if os.path.exists(lkw):
            vorher = set(bpy.data.objects)
            bpy.ops.import_scene.gltf(filepath=lkw)
            neu = [o for o in bpy.data.objects if o not in vorher and o.parent is None]
            for o in neu:
                o.location = (1.2, 6.5, -0.4); o.rotation_euler = (0, 0, math.radians(-72))
    elif proj == 'jonika':
        # Die zwei Bände der Autorin, aufgefächert, Umschläge von ihrer Seite
        buch('band2', (-0.36, 0.16, TISCH_Z), math.radians(-4), 0.145, 0.215, 0.019, (0.08, 0.10, 0.16),
             titel_bild=os.path.join(TEXA, 'cover-jonika-band2.png'))
        buch('band1', (-0.33, 0.10, TISCH_Z + 0.019), math.radians(9), 0.145, 0.215, 0.019, (0.10, 0.12, 0.18),
             titel_bild=os.path.join(TEXA, 'cover-jonika-band1.png'))
        for i, f in enumerate(((0.8, 0.1, 0.1), (0.1, 0.5, 0.2), (0.95, 0.75, 0.1), (0.1, 0.3, 0.8))):
            stift(f'buntstift{i}', (-0.22 + i * 0.012, -0.12 + i * 0.006), math.radians(-20 + i * 4), f)
        tasse('tee', (0.36, 0.20), (0.35, 0.52, 0.62))
        lampe('lampe', (-0.55, 0.30))
    elif proj == 'mensaena':
        tasse('becher', (-0.30, -0.02), (0.82, 0.55, 0.36))
        brett = B.stoff('Brett', (1, 1, 1), rau=0.55, farbkarte='kueche-eiche-farbe', kachel=1.2)
        b = B.kasten('brett', 0.34, 0.22, 0.018, [brett], fase=0.004, ort=(-0.40, 0.25, TISCH_Z)); b.rotation_euler = (0, 0, math.radians(-10))
        glas = B.stoff('Wasserglas', (1, 1, 1), rau=0.0, trans=1.0, ior=1.5)
        g = B.drehkoerper('glas', [(0.0, 0.0), (0.032, 0.0), (0.034, 0.11), (0.032, 0.11), (0.030, 0.004), (0.0, 0.004)], 64, [glas])
        g.location = (0.34, 0.22, TISCH_Z)
    elif proj == 'trendonix':
        # Die drei Bände der Reihe, gefächert: jeder Umschlag bleibt zu sehen
        z = TISCH_Z
        # Zwei Bände liegen hinten, der dritte steht vorn auf einem Acrylständer
        # und zeigt sein Cover zur Kamera (siehe buchstaender)
        z = TISCH_Z
        for i, (dx, dy, dr) in enumerate(((-0.40, 0.30, -8), (-0.38, 0.26, 6))):
            buch(f'band{i + 2}', (dx, dy, z), math.radians(dr), 0.155, 0.225, 0.022, (0.06, 0.07, 0.10), rau=0.5,
                 titel_bild=os.path.join(TEXA, f'cover-trendonix-{i + 2}.png'))
            z += 0.022
        buchstaender('band1', (-0.265, 0.23), math.radians(float(EXTRA.get('buchdreh', 24))),
                     (None, None, None, 0.155, 0.225, 0.022, (0.06, 0.07, 0.10),
                      dict(rau=0.5, titel_bild=os.path.join(TEXA, 'cover-trendonix-1.png'))))
        tasse('tasse', (0.36, 0.22), (0.12, 0.12, 0.13))
        lampe('lampe', (-0.66, 0.44), 2600, 14.0)
        stift('fueller', (-0.18, -0.10), math.radians(25), (0.02, 0.02, 0.02))


# ------------------------------------------------------------------ Kamera
def kamera(proj):
    sc = bpy.context.scene
    cd = bpy.data.cameras.new('Kamera'); cd.lens = float(EXTRA.get('lens', 50)); cd.sensor_width = 36
    cam = bpy.data.objects.new('Kamera', cd); sc.collection.objects.link(cam); sc.camera = cam
    # Etwas tiefer als Augenhöhe am Tisch: so steht das Fenster (und bei
    # Cavaleri der Sattelzug draußen) im Hintergrund, nicht nur die Tischplatte
    ziel = Vector((0.07, 0.05, TISCH_Z + 0.12))
    ort = Vector((0.34, -0.84, TISCH_Z + 0.26))
    cam.location = ort; cam.rotation_euler = (ziel - ort).to_track_quat('-Z', 'Y').to_euler()
    cd.dof.use_dof = True; cd.dof.focus_distance = (Vector((0.0, 0.07, TISCH_Z + 0.11)) - ort).length
    cd.dof.aperture_fstop = float(EXTRA.get('blende', 2.8))
    return cam, ziel, ort


def render_einstellen(probe):
    sc = bpy.context.scene; r = sc.render
    r.engine = 'CYCLES'
    prefs = bpy.context.preferences.addons['cycles'].preferences
    for typ in ('OPTIX', 'CUDA'):
        try:
            prefs.compute_device_type = typ; prefs.get_devices()
            if any(dv.type == typ for dv in prefs.devices):
                for dv in prefs.devices: dv.use = dv.type == typ
                break
        except Exception:
            pass
    sc.cycles.device = 'GPU'
    r.resolution_x, r.resolution_y = 2400, 1350
    r.resolution_percentage = int(EXTRA.get('prozent', 35 if probe else 100))
    sc.cycles.samples = int(EXTRA.get('samples', 96 if probe else 768))
    sc.cycles.use_denoising = True
    sc.cycles.max_bounces = 12
    sc.view_settings.view_transform = 'AgX'
    sc.view_settings.exposure = float(EXTRA.get('belichtung', 0.0))
    r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'


def ecken_pixel(cam, punkte):
    sc = bpy.context.scene; r = sc.render
    w = r.resolution_x; h = r.resolution_y
    aus = []
    for p in punkte:
        c = world_to_camera_view(sc, cam, p)
        aus.append([round(c.x * w, 2), round((1 - c.y) * h, 2)])
    return aus


def main():
    B.leeren()
    cfg = PROJEKTE[PROJ]
    S = stoffe()
    raum(cfg['stil'])
    hell = 1.0 if cfg['stil'] == 'tag' else 0.55     # abends sind Bildschirme dunkler gestellt
    lx, ly, ldz = cfg['laptop']
    _, ecken_l = laptop('laptop', (lx, ly, TISCH_Z + 0.0012), ldz, os.path.join(TEXA, f'{PROJ}-laptop.png'), S, hell=hell)
    tx, ty, tdz = cfg['telefon']
    neig = math.radians(64)
    staender('staender', (tx, ty), tdz, neig, S)
    _, ecken_t = telefon('telefon', (tx, ty, TISCH_Z), tdz, os.path.join(TEXA, f'{PROJ}-handy.png'), S, hell=hell, neigung=neig)
    dinge(PROJ)
    cam, ziel, ort = kamera(PROJ)
    render_einstellen(MODUS == 'probe')
    sc = bpy.context.scene
    if MODUS == 'probe':
        sc.render.filepath = os.path.join(AUS, f'probe{EXTRA.get("name", "")}.png')
        bpy.ops.render.render(write_still=True)
    elif MODUS == 'voll':
        sc.render.filepath = os.path.join(AUS, 'an.png'); bpy.ops.render.render(write_still=True)
        def teil(e, oben, unten):
            # Seitenbereich ohne Browserrahmen -- in 3D geteilt, damit die
            # Perspektive stimmt (im Bild linear zu teilen wäre schief)
            ol, orr, ur, ul = e
            return [ol.lerp(ul, oben), orr.lerp(ur, oben), orr.lerp(ur, 1 - unten), ol.lerp(ul, 1 - unten)]
        daten = {'breite': sc.render.resolution_x, 'hoehe': sc.render.resolution_y,
                 'laptop': ecken_pixel(cam, ecken_l), 'telefon': ecken_pixel(cam, ecken_t),
                 'laptop_seite': ecken_pixel(cam, teil(ecken_l, LAPTOP_CHROME, 0.0)),
                 'telefon_seite': ecken_pixel(cam, teil(ecken_t, TEL_OBEN, TEL_UNTEN))}
        with open(os.path.join(AUS, 'ecken.json'), 'w') as fh:
            json.dump(daten, fh, indent=1)
        # Glanz-Durchgang: dieselbe Aufnahme mit dunklen Anzeigen. Im Web liegt
        # er über dem Live-Bild (mix-blend-mode: screen) -- so spiegelt sich das
        # Fenster auch auf der laufenden Seite.
        for m in bpy.data.materials:
            if m.name.endswith('Anzeige'):
                m.node_tree.nodes['Principled BSDF'].inputs['Emission Strength'].default_value = 0.0
        sc.render.filepath = os.path.join(AUS, 'aus.png'); bpy.ops.render.render(write_still=True)
    elif MODUS == 'fahrt':
        # 3 s auf das Standbild zu: von weiter links und tiefer, weich auslaufend
        n = int(EXTRA.get('bilder', 72))
        start = ort + Vector((-0.32, -0.30, -0.07))
        # Direkt in Web-Größe: Das Video läuft in einer Bühne von höchstens
        # ~900 CSS-Pixeln Breite und wird ohnehin auf 1280 x 720 gebracht.
        # 1920er Bilder mit 256 Samples hätten bei belegter Grafikkarte rund
        # drei Stunden je Projekt gekostet, für Pixel, die niemand sieht.
        sc.render.resolution_x, sc.render.resolution_y = 1280, 720
        sc.render.resolution_percentage = int(EXTRA.get('prozent', 100))
        sc.cycles.samples = int(EXTRA.get('samples', 160))
        os.makedirs(os.path.join(AUS, 'fahrt'), exist_ok=True)
        for i in range(n):
            t = i / (n - 1); e = 1 - (1 - t) ** 3
            p = start.lerp(ort, e)
            cam.location = p; cam.rotation_euler = (ziel - p).to_track_quat('-Z', 'Y').to_euler()
            cam.data.dof.focus_distance = (Vector((0.0, 0.07, TISCH_Z + 0.11)) - p).length
            pfad = os.path.join(AUS, 'fahrt', f'bild-{i:03d}.png')
            if os.path.exists(pfad) and EXTRA.get('neu') != '1':
                continue
            sc.render.filepath = pfad; bpy.ops.render.render(write_still=True)
            print('FAHRT', i, '/', n, flush=True)
    elif MODUS == 'unreal':
        # Blender zuerst, dann Unreal (Uwe, 16.09.2026): Die Szene geht als
        # GLB mit allen Bildtexturen hinueber, dazu Kamera, Sonne und Himmel
        # als Zahlen. Prozedurale Knoten (Rauschen auf der Rauheit) kennt
        # glTF nicht -- das rekonstruiert die Unreal-Seite, nicht der Export.
        uziel = os.path.join(AUS, 'unreal'); os.makedirs(uziel, exist_ok=True)
        bpy.ops.object.select_all(action='DESELECT')
        bpy.ops.export_scene.gltf(filepath=os.path.join(uziel, PROJ + '.glb'), export_format='GLB',
                                  export_apply=True, export_cameras=False, export_lights=False,
                                  export_yup=True, export_texcoords=True, export_normals=True,
                                  export_image_format='AUTO')
        sonne = next((o for o in bpy.data.objects if o.type == 'LIGHT' and o.data.type == 'SUN'), None)
        himmel = next((n for n in (sc.world.node_tree.nodes if sc.world and sc.world.node_tree else [])
                       if n.bl_idname == 'ShaderNodeTexSky'), None)
        bg = sc.world.node_tree.nodes.get('Background') if sc.world and sc.world.node_tree else None
        lampen = [{'name': o.name, 'ort': list(o.matrix_world.translation), 'art': o.data.type,
                   'energie_w': float(o.data.energy), 'groesse_m': float(getattr(o.data, 'size', 0.0))}
                  for o in bpy.data.objects if o.type == 'LIGHT' and o.data.type != 'SUN']
        daten = {
            'projekt': PROJ, 'einheit': 'Meter, Blender Z-oben',
            'breite': sc.render.resolution_x, 'hoehe': sc.render.resolution_y,
            'kamera': {'ort': list(cam.matrix_world.translation), 'ziel': list(ziel_v(ziel)),
                       'rotation_euler_rad': list(cam.matrix_world.to_euler()),
                       'brennweite_mm': cam.data.lens, 'sensor_mm': cam.data.sensor_width,
                       'blende': cam.data.dof.aperture_fstop, 'fokus_m': cam.data.dof.focus_distance},
            'sonne': None if sonne is None else {
                'rotation_euler_rad': list(sonne.matrix_world.to_euler()), 'staerke_w_m2': sonne.data.energy,
                'farbe': list(sonne.data.color), 'winkel_grad': math.degrees(sonne.data.angle)},
            'himmel': None if himmel is None else {
                'art': himmel.sky_type, 'hoehe_grad': math.degrees(himmel.sun_elevation),
                'drehung_grad': math.degrees(himmel.sun_rotation),
                'staerke': float(bg.inputs['Strength'].default_value) if bg else 1.0},
            'lampen': lampen,
            'belichtung': sc.view_settings.exposure, 'ansicht': sc.view_settings.view_transform,
        }
        with open(os.path.join(uziel, PROJ + '.json'), 'w') as fh:
            json.dump(daten, fh, indent=1)
    print('FERTIG ARBEITEN', PROJ, MODUS)


main()
