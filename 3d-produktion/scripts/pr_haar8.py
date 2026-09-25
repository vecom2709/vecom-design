"""pr_haar.py -- Friseur & Salon: Haarfarben am Frisierkopf.

Kunden eines Salons wollen vor dem Termin sehen, wie eine Farbe wirkt.
Gezeigt wird ein Uebungskopf mit langem, leicht gewelltem Haar
(Mittelscheitel, Stufen), gerechnet mit echten Haarkurven und dem
physikalischen Haarshader (Principled Hair, Melanin-Modell) -- Farben
entstehen wie im echten Haar aus Eumelanin, Phaeomelanin und Toenung.
Das Web bekommt je Farbe eine Drehung als Bildfolge (Haar in Echtzeit
waere nicht fotoecht).

Aufruf (Blender, Hintergrund):
  blender -b -P pr_haar.py -- <modus>[,farbe=...,prozent=..,bilder=..]
  modus: probe (ein Bild je Farbe, klein) | dreh (Bildfolge je Farbe) | poster

Masse: Kopf 156 x 190 x 220 mm (Erwachsene, Mittelwert), Haarlaenge im
Nacken 36 cm (schulterlang), ~52.000 Haare (Kopfhaut hat ~100.000; dichter gerechnet
wirken sie im Bild nicht dichter, nur langsamer).
"""
import math, os, sys, json, time
import numpy as np
import bpy
from mathutils import Vector

HIER = os.path.dirname(os.path.abspath(__file__))
P = os.path.normpath(os.path.join(HIER, '..', 'branchen'))
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
MODUS = argv[0] if argv else 'probe'
EXTRA = dict(t.split('=', 1) for t in argv[1:] if '=' in t)
AUSGABE = os.path.join(P, 'render', EXTRA.get('ausgabe', 'haar8'))  # eigener Ordner je Fassung (v8 = Oberkoerper)
os.makedirs(AUSGABE, exist_ok=True)
RNG = np.random.default_rng(20260924)

C = np.array([0.0, 0.0, 0.62])            # Kopfmitte (Bueste auf dem Stativ)
R = np.array([0.078, 0.095, 0.110])       # Halbachsen x (Breite), y (Tiefe), z (Hoehe)
# Oberkoerper unter dem Umhang als Profil: Hoehe z -> halbe Breite W, halbe
# Tiefe D (Mitte y = 0,01). Bis v7 war die Bueste ein halbes Ellipsoid: von
# hinten eine Glocke, mit der Haarsaeule darueber ein Pilz (Probe 24.09.2026).
# Jetzt Nacken -- Trapezmuskel -- Schulterdach -- Umhang faellt, nach
# Koerpermassen: Schulterbreite 42 cm, Trapez 5 cm Gefaelle auf 13 cm
# (~20 Grad). Mit 8,5 cm Gefaelle (v8a) wirkte der Umhang wie eine Thermoskanne.
KP = np.array([
    # z      W      D
    [0.080, 0.236, 0.146],
    [0.160, 0.216, 0.133],
    [0.260, 0.206, 0.125],
    [0.330, 0.208, 0.123],
    [0.370, 0.210, 0.122],
    [0.405, 0.205, 0.119],
    [0.425, 0.192, 0.113],
    [0.438, 0.170, 0.105],
    [0.448, 0.140, 0.095],
    [0.458, 0.105, 0.082],
    [0.468, 0.075, 0.068],
    [0.475, 0.057, 0.059],
])
K_OBEN = KP[-1, 0]
K_EXP = 2.2                               # Querschnitt: leicht eckig (2,5 wirkte wie eine Kiste, v8b)


def koerper_wd(z):
    return np.interp(z, KP[:, 0], KP[:, 1]), np.interp(z, KP[:, 0], KP[:, 2])


def koerper_hinaus(q, abstand):
    """Punkte im Oberkoerper auf die Flaeche (mal abstand) schieben -- nach
    vorn oder hinten, nicht zur Seite: Waagerecht-radial geschoben glitt das
    Haar am Trapezmuskel bis ueber die Schulterspitzen (Silhouettenprobe v8a).
    Nur wer schon am Rand steht, geht seitlich hinaus."""
    w, d = koerper_wd(q[:, 2]); w = w * abstand; d = d * abstand
    ax = np.abs(q[:, 0]) / w
    rand = ax > 0.97
    q = q.copy()
    yr = d * np.clip(1 - np.minimum(ax, 1) ** K_EXP, 0, 1) ** (1 / K_EXP)
    vz = np.where(q[:, 1] - 0.01 >= 0, 1.0, -1.0)
    q[~rand, 1] = 0.01 + vz[~rand] * yr[~rand]
    if rand.any():
        r_ = koerper_r(q[rand]) / abstand
        q[rand, 0] /= r_; q[rand, 1] = 0.01 + (q[rand, 1] - 0.01) / r_
    return q


def koerper_r(q):
    """Normierter Abstand zur Oberkoerperflaeche (1 = auf der Flaeche)."""
    w, d = koerper_wd(q[:, 2])
    return (np.abs(q[:, 0] / w) ** K_EXP + np.abs((q[:, 1] - 0.01) / d) ** K_EXP) ** (1 / K_EXP)


# Farben: Melanin (0..1), Rotanteil (Phaeomelanin), Spitzen (Balayage), Toenung
FARBEN = {
    'schwarz':   dict(mel=0.92, rot=0.05, spitze=None, toen=None, name='Naturschwarz'),
    # Erste Vollprobe: Kastanie und Kupfer kaum unterscheidbar, Rosé wirkte blond,
    # Asch warm -- Kastanie dunkler, Kupfer heller und roter, Toenungen staerker
    'kastanie':  dict(mel=0.72, rot=0.38, spitze=None, toen=None, name='Kastanie'),
    'kupfer':    dict(mel=0.46, rot=1.00, spitze=None, toen=None, name='Kupfer'),
    'balayage':  dict(mel=0.58, rot=0.30, spitze=(0.16, 0.32), toen=None, name='Karamell-Balayage'),
    # Gefaerbte Toene (Asch, Rosé) direkt ueber die Faserfarbe: Melanin plus
    # Toenung blieb in Probe v2 bei beiden goldblond -- Pheomelanin ueberwiegt.
    # Die Faserfarbe erscheint durch Mehrfachstreuung viel heller und blasser
    # (v4 gemessen: 0,46/0,42/0,37 -> 0,27/0,22/0,19 linear) -- daher gesaettigter.
    'aschblond': dict(mel=0.18, rot=0.0, spitze=None, toen=None, direkt=(0.58, 0.46, 0.29), name='Aschblond'),
    'rosegold':  dict(mel=0.08, rot=0.20, spitze=None, toen=None, direkt=(0.95, 0.28, 0.20), name='Rosé-Gold'),
}


def leeren():
    bpy.ops.wm.read_factory_settings(use_empty=True)


def stoff(name, farbe, rau, sss=0.0, metall=0.0):
    m = bpy.data.materials.new(name); m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (*farbe, 1); b.inputs['Roughness'].default_value = rau
    b.inputs['Metallic'].default_value = metall
    if sss:
        b.inputs['Subsurface Weight'].default_value = sss
        b.inputs['Subsurface Radius'].default_value = (0.02, 0.012, 0.008)
    return m


# ------------------------------------------------------------------ Kopf
def kopf(parent):
    """Uebungskopf: Schaedel als Ellipsoid, weich modelliertes Gesicht (Stirn,
    Nasenruecken, Kinn), Hals, darunter Klemme und Stativ."""
    haut = stoff('Kopf Vinyl', (0.60, 0.45, 0.36), 0.42, sss=0.15)
    bpy.ops.mesh.primitive_uv_sphere_add(segments=96, ring_count=64, radius=1.0, location=tuple(C))
    k = bpy.context.object; k.name = 'kopf'; k.scale = tuple(R)
    bpy.ops.object.transform_apply(scale=True)
    me = k.data
    for v in me.vertices:
        p = np.array(v.co) - C
        d = p / R
        # Gesicht (vorn = -y): Kiefer schmaler, Kinn nach vorn, Nase angedeutet
        if d[2] < 0.1:
            kiefer = min(1.0, (0.1 - d[2]) / 1.1)
            p[0] *= 1 - 0.26 * kiefer
            if d[1] < 0:
                p[1] *= 1 - 0.10 * kiefer
        nase = math.exp(-((d[0] / 0.13) ** 2) - ((d[2] + 0.12) / 0.26) ** 2) * max(0.0, -d[1]) ** 6
        p[1] -= 0.016 * nase
        augen = sum(math.exp(-(((d[0] - s * 0.36) / 0.16) ** 2) - ((d[2] - 0.08) / 0.12) ** 2) for s in (-1, 1)) * max(0.0, -d[1]) ** 4
        p[1] += 0.006 * augen
        v.co = Vector(C + p)
    for poly in me.polygons:
        poly.use_smooth = True
    me.materials.append(haut)
    bpy.ops.mesh.primitive_cylinder_add(vertices=64, radius=0.050, depth=0.20, location=(0, 0.01, C[2] - 0.17))
    hals = bpy.context.object; hals.name = 'hals'; hals.data.materials.append(haut)
    for poly in hals.data.polygons:
        poly.use_smooth = True
    # Schwarzer Friseurumhang statt nackter Vinylschultern: so sitzt man im
    # Salon, und die Haarfarbe steht vor dunklem Grund (Probe v3: Spitzen
    # auf hellem Vinyl wirkten wie Schmutz).
    umhang = bpy.data.materials.new('Umhang'); umhang.use_nodes = True
    ub = umhang.node_tree.nodes['Principled BSDF']
    # Stoff, nicht Kunststoff: Mit Klarlack und Rauheit 0,42 glaenzte der
    # Umhang wie eine Thermoskanne (Probe v8b). Friseurumhaenge sind matt
    # beschichtetes Polyester -- nur der Stoffglanz (Sheen) an den Kanten.
    ub.inputs['Base Color'].default_value = (0.014, 0.014, 0.016, 1); ub.inputs['Roughness'].default_value = 0.78
    ub.inputs['Sheen Weight'].default_value = 0.3; ub.inputs['Sheen Tint'].default_value = (0.30, 0.31, 0.34, 1)
    ub.inputs['Sheen Roughness'].default_value = 0.45
    # Oberkoerper im Umhang als ein Koerper aus dem Profil KP; oben legt sich
    # der Kragen eng um den Hals. Falten nur dort, wo der Stoff frei faellt.
    zs = np.concatenate([np.linspace(KP[0, 0], 0.33, 12), np.linspace(0.335, K_OBEN, 24)])
    seg = 128
    vs, fs = [], []
    for z in zs:
        w, d = koerper_wd(z)
        t = float(np.clip((0.33 - z) / 0.25, 0, 1))
        for i in range(seg):
            a = 2 * math.pi * i / seg
            c, s_ = math.cos(a), math.sin(a)
            # Superellipse: |x/w|^p + |y/d|^p = 1
            kf = (abs(c) ** K_EXP + abs(s_) ** K_EXP) ** (-1 / K_EXP)
            falte = 1 + 0.028 * t * math.sin(a * 9 + 1.3) + 0.016 * t * math.sin(a * 17)
            vs.append((w * kf * c * falte, 0.01 + d * kf * s_ * falte, z))
    vs.append((0.0, 0.01, KP[0, 0]))
    for j in range(len(zs) - 1):
        for i in range(seg):
            a0 = j * seg + i; a1 = j * seg + (i + 1) % seg
            fs.append((a0, a1, a1 + seg, a0 + seg))
    boden_i = len(vs) - 1
    for i in range(seg):
        fs.append((boden_i, (i + 1) % seg, i))
    me = bpy.data.meshes.new('umhang'); me.from_pydata(vs, [], fs)
    for poly in me.polygons:
        poly.use_smooth = True
    uh = bpy.data.objects.new('umhang', me); bpy.context.scene.collection.objects.link(uh)
    me.materials.append(umhang)
    uh.parent = parent
    sub = uh.modifiers.new('glatt', 'SUBSURF'); sub.levels = 1; sub.render_levels = 1
    metall = stoff('Stativ Schwarz', (0.02, 0.02, 0.022), 0.35, metall=0.6)
    bpy.ops.mesh.primitive_cylinder_add(vertices=32, radius=0.012, depth=KP[0, 0], location=(0, 0.01, KP[0, 0] / 2))
    st = bpy.context.object; st.name = 'stativ'; st.data.materials.append(metall)
    bpy.ops.mesh.primitive_cylinder_add(vertices=64, radius=0.12, depth=0.012, location=(0, 0.01, 0.006))
    fu = bpy.context.object; fu.name = 'fuss'; fu.data.materials.append(metall)
    for o in (k, hals, st, fu):
        o.parent = parent
    return k


# ------------------------------------------------------------------ Haar
def haare(n_haare=52000, n_pkt=26):
    """Haarkurven: Wurzeln auf der Kopfhaut innerhalb des Haaransatzes,
    Wachstum vom Mittelscheitel weg und nach unten, Kollision mit dem Kopf
    in Lagen (hoehere Wurzeln liegen aussen), Wellen ab Kinnhoehe,
    Straehnen (Clumps) zu den Spitzen hin, leichter Frizz."""
    # Wurzeln: Richtungen gleichmaessig auf der Kugel, gefiltert nach Haaransatz
    wurzeln = []
    while len(wurzeln) < n_haare:
        d = RNG.standard_normal((n_haare, 3)); d /= np.linalg.norm(d, axis=1, keepdims=True)
        vorn = np.clip(-d[:, 1], 0, 1); seite = np.abs(d[:, 0])
        grenze = 0.30 * vorn ** 1.2 + (-0.10) * seite * (1 - vorn) + (-0.42) * np.clip(d[:, 1], 0, 1) * (1 - seite)
        # Ohren frei: seitlich unterhalb der Mitte kein Haar
        ohr = (seite > 0.75) & (d[:, 2] < 0.05) & (np.abs(d[:, 1]) < 0.35)
        ok = (d[:, 2] > grenze) & ~ohr
        wurzeln.extend(d[ok][: n_haare - len(wurzeln)])
    d = np.array(wurzeln)
    p0 = C + d * R
    n0 = d / R; n0 /= np.linalg.norm(n0, axis=1, keepdims=True)       # Normale der Ellipsoidflaeche
    seite = np.sign(d[:, 0] + RNG.normal(0, 0.02, len(d)))
    hoehe = (d[:, 2] + 1) / 2
    # Volumen: Haar steht am Oberkopf 2-3 cm vom Schaedel ab, seitlich knapp
    # 1 cm. Mit 1,012-1,067 (bis v7) lag es wie eine Badekappe an, und von
    # hinten las sich der Kopf als schmale Saeule (Probe 24.09.2026).
    lage = 1.035 + 0.17 * hoehe ** 1.3 + RNG.uniform(0, 0.008, len(d))
    # Gestufte Spitzen: eine einheitliche Laenge ergab eine gerade, verschmierte
    # Schnittkante auf den Schultern (Vollprobe 24.09.2026). Jede sechste
    # Straehne kuerzer, dazu eine Grundstreuung -- wie ein ausgefranster Schnitt.
    laenge = 0.36 * (1 - 0.10 * np.clip(-d[:, 1], 0, 1)) * RNG.normal(1.0, 0.07, len(d))
    laenge *= np.where(RNG.random(len(d)) < 0.18, RNG.uniform(0.72, 0.95, len(d)), 1.0)
    seg = laenge / (n_pkt - 1)
    # Anfangsrichtung: vom Scheitel weg (x), leicht nach hinten, nach unten
    # Scheitel nur oben/vorn: am Hinterkopf faellt das Haar gerade (sonst
    # klaffte hinten eine Luecke bis in die Spitzen, Skizze 23.09.2026)
    # Scheitel nur vorn: Laeuft er ueber den Wirbel, zeichnet er von hinten
    # eine Kerbe in den Umriss (v7, Kopf wie mit Spitze). Am Oberkopf faellt
    # das Haar deshalb nach hinten.
    scheitel = np.clip(0.3 - 2.0 * d[:, 1], 0, 1) * np.clip(d[:, 2] + 0.2, 0, 1)
    f = np.stack([seite * (0.9 * np.clip(d[:, 2], 0, 1) + 0.25) * scheitel, 0.28 * np.ones(len(d)) + 0.2 * d[:, 1], -0.55 * np.ones(len(d))], 1)
    f -= (np.sum(f * n0, 1, keepdims=True)) * n0                       # tangential
    v = f / np.linalg.norm(f, axis=1, keepdims=True) + 0.35 * n0
    v /= np.linalg.norm(v, axis=1, keepdims=True)
    pts = np.zeros((len(d), n_pkt, 3)); pts[:, 0] = p0
    q = p0.copy()
    for i in range(1, n_pkt):
        g = np.array([0.0, 0.0, -1.0])
        v = v + 0.45 * g
        # Unterhalb des Kopfes faellt langes Haar nach aussen und hinten
        # auseinander (A-Linie) und legt sich auf den Ruecken, statt als
        # Saeule gerade herunterzuhaengen.
        # Nach hinten staerker als nach aussen: Mit gleichem Zug lag das Haar
        # bis ueber die Schulterspitzen (Silhouettenprobe v8a).
        unten = np.clip((C[2] - 0.03 - q[:, 2]) / 0.12, 0, 1)[:, None]
        aus_h = q - np.array([0.0, 0.01, 0.0]); aus_h[:, 2] = 0
        aus_h /= np.maximum(np.linalg.norm(aus_h, axis=1, keepdims=True), 1e-6)
        v = v + unten * (0.025 * aus_h + np.array([0.0, 0.17, 0.0]))
        v /= np.linalg.norm(v, axis=1, keepdims=True)
        q = q + v * seg[:, None]
        # Kollision Kopf (Lagen)
        rel = (q - C) / R; r = np.linalg.norm(rel, axis=1)
        innen = r < lage
        if innen.any():
            q[innen] = C + rel[innen] / r[innen, None] * lage[innen, None] * R
            nrm = rel[innen] / R; nrm /= np.linalg.norm(nrm, axis=1, keepdims=True)
            vi = v[innen]; vi -= np.minimum(0, np.sum(vi * nrm, 1, keepdims=True)) * nrm
            v[innen] = vi / np.linalg.norm(vi, axis=1, keepdims=True)
        # Hals (Zylinder) und Schulterzone: nicht nach innen
        rh = np.hypot(q[:, 0], q[:, 1] - 0.01); unter = q[:, 2] < C[2] - 0.05
        zu = unter & (rh < 0.064 + 0.02 * hoehe)
        if zu.any():
            s_ = (0.064 + 0.02 * hoehe[zu]) / np.maximum(rh[zu], 1e-6)
            q[zu, 0] *= s_; q[zu, 1] = 0.01 + (q[zu, 1] - 0.01) * s_
        # Schultern und Ruecken (Umhang): Haar legt sich darauf und gleitet
        # am Trapezmuskel nach aussen ab -- waagerecht hinausschieben
        im = (q[:, 2] < K_OBEN + 0.005) & (q[:, 2] > KP[0, 0])
        if im.any():
            r_ = koerper_r(q[im])
            innen = r_ < 1.03
            if innen.any():
                idx = np.where(im)[0][innen]
                q[idx] = koerper_hinaus(q[idx], 1.03)
                nrm = q[idx] - np.array([0.0, 0.01, 0.0]); nrm[:, 2] = 0
                nrm /= np.maximum(np.linalg.norm(nrm, axis=1, keepdims=True), 1e-6)
                vi = v[idx]; vi -= np.minimum(0, np.sum(vi * nrm, 1, keepdims=True)) * nrm
                v[idx] = vi / np.linalg.norm(vi, axis=1, keepdims=True)
        pts[:, i] = q
    # Stirnhaare nicht ins Gesicht: vordere Straehnen seitlich hinters Gesicht lenken
    t = np.linspace(0, 1, n_pkt)[None, :, None]
    vorn_m = (pts[:, :, 1] < C[1] - 0.05)[:, :, None] & (np.abs(pts[:, :, 0])[:, :, None] < 0.07)
    pts[:, :, 0:1] += np.where(vorn_m, seite[:, None, None] * 0.03 * t, 0)
    # Straehnen: Wurzelnaehe -> Clump-Mitte, zu den Spitzen hin zusammen
    # 2400 statt 900 Straehnen, schwaecher zusammengezogen: Mit 900 wurden die
    # Spitzen zu Pinseln mit Luecken, durch die die Bueste schien (Probe v2).
    n_cl = 2400
    zent = p0[RNG.choice(len(p0), n_cl, replace=False)]
    zu_cl = np.zeros(len(p0), int)
    for a in range(0, len(p0), 4000):                                  # in Bloecken: sonst ~1 GB Abstandsmatrix
        zu_cl[a:a + 4000] = np.argmin(((p0[a:a + 4000, None, :] - zent[None, :, :]) ** 2).sum(-1), axis=1)
    summe = np.zeros((n_cl, n_pkt, 3)); anz = np.zeros(n_cl)
    np.add.at(summe, zu_cl, pts); np.add.at(anz, zu_cl, 1)
    mitte = summe / np.maximum(anz, 1)[:, None, None]
    zieh = 0.36 * t[..., 0] ** 2.0
    pts = pts + (mitte[zu_cl] - pts) * zieh[..., None]
    # Wellen ab Kinnhoehe (je Straehne eigene Phase), quer zur Wuchsrichtung
    bogen = np.concatenate([np.zeros((len(d), 1)), np.cumsum(np.linalg.norm(np.diff(pts, axis=1), axis=2), axis=1)], 1)
    phase = RNG.uniform(0, 2 * np.pi, n_cl)[zu_cl][:, None]
    amp = 0.007 * np.clip((bogen - 0.12) / 0.10, 0, 1)                   # weiche S-Welle, nicht kraus
    welle = amp * np.sin(2 * np.pi * bogen / 0.12 + phase)
    aussen = pts - np.array([0.0, 0.01, 0.0]); aussen[:, :, 2] = 0
    aussen /= np.maximum(np.linalg.norm(aussen, axis=2, keepdims=True), 1e-6)
    quer = np.cross(aussen, np.array([0.0, 0.0, 1.0]))
    pts = pts + (0.55 * aussen + 0.45 * quer) * welle[..., None]
    # Frizz: je Haar eine weiche Abweichung, die zu den Spitzen waechst, dazu
    # kaum Zittern. Unabhaengiges Rauschen je Punkt (bis v8a) knickte jedes
    # Haar alle 1,4 cm -- das Haar sah gekraeuselt und stumpf aus wie Stroh,
    # die Glanzlinie zerfiel (Probe v8a).
    pts += RNG.normal(0, 1, (len(d), 1, 3)) * (0.0016 * t ** 1.6)
    pts += RNG.normal(0, 1, pts.shape) * (0.00015 * t)
    # Nachkollision: Straehnen, Wellen und Frizz schieben Spitzen wieder in
    # Kopf und Schultern -- dort verschwammen sie als Schatten im Umhang
    # (Probe v3). Alles, was innen liegt, auf die Oberflaeche plus Abstand.
    flach = pts.reshape(-1, 3)
    rel = (flach - C) / R; r = np.linalg.norm(rel, axis=1)
    innen = r < 1.02
    flach[innen] = C + rel[innen] / r[innen, None] * 1.02 * R
    im = (flach[:, 2] < K_OBEN + 0.005) & (flach[:, 2] > KP[0, 0])
    r_ = koerper_r(flach[im]); innen = r_ < 1.035
    idx = np.where(im)[0][innen]
    flach[idx] = koerper_hinaus(flach[idx], 1.035)
    pts = flach.reshape(pts.shape)
    # Kurven anlegen
    kurven = bpy.data.hair_curves.new('Haar')
    kurven.add_curves([n_pkt] * len(d))
    kurven.position_data.foreach_set('vector', pts.astype(np.float32).ravel())
    rad = kurven.attributes.get('radius') or kurven.attributes.new('radius', 'FLOAT', 'POINT')
    r_ = (0.000060 * (1 - 0.72 * np.linspace(0, 1, n_pkt) ** 1.4))[None, :] * RNG.uniform(0.8, 1.2, (len(d), 1))
    rad.data.foreach_set('value', r_.astype(np.float32).ravel())
    o = bpy.data.objects.new('haar', kurven); bpy.context.scene.collection.objects.link(o)
    return o


def haarstoff(farbe):
    """Principled Hair (Chiang), Melanin-Parametrierung. Balayage: Melanin
    ueber die Laenge (Intercept) von der Ansatzfarbe zur Spitzenfarbe."""
    F = FARBEN[farbe]
    m = bpy.data.materials.new('Haar ' + F['name']); m.use_nodes = True
    nt = m.node_tree; nt.nodes.clear()
    aus = nt.nodes.new('ShaderNodeOutputMaterial')
    h = nt.nodes.new('ShaderNodeBsdfHairPrincipled')
    try:
        h.model = 'CHIANG'
    except Exception:
        pass
    h.parametrization = 'COLOR' if F.get('direkt') else 'MELANIN'
    if F.get('direkt'):
        h.inputs['Color'].default_value = (*F['direkt'], 1)
    # Bei direkter Farbe blendet Blender die Melanin-Eingaenge aus (KeyError, v4)
    if not F.get('direkt'):
        h.inputs['Melanin'].default_value = F['mel']
        h.inputs['Melanin Redness'].default_value = F['rot']
    h.inputs['Roughness'].default_value = 0.22
    h.inputs['Radial Roughness'].default_value = 0.32
    h.inputs['Coat'].default_value = 0.12
    if 'Random Color' in h.inputs:
        h.inputs['Random Color'].default_value = 0.08
    if 'Random Roughness' in h.inputs:
        h.inputs['Random Roughness'].default_value = 0.15
    if F['toen']:
        h.inputs['Tint'].default_value = (*F['toen'], 1)
    if F['spitze']:
        info = nt.nodes.new('ShaderNodeHairInfo')
        kurve = nt.nodes.new('ShaderNodeMapRange')
        kurve.inputs['From Min'].default_value = 0.28; kurve.inputs['From Max'].default_value = 0.85
        kurve.inputs['To Min'].default_value = F['mel']; kurve.inputs['To Max'].default_value = F['spitze'][0]
        nt.links.new(info.outputs['Intercept'], kurve.inputs['Value'])
        nt.links.new(kurve.outputs['Result'], h.inputs['Melanin'])
        k2 = nt.nodes.new('ShaderNodeMapRange')
        k2.inputs['From Min'].default_value = 0.28; k2.inputs['From Max'].default_value = 0.85
        k2.inputs['To Min'].default_value = F['rot']; k2.inputs['To Max'].default_value = F['spitze'][1]
        nt.links.new(info.outputs['Intercept'], k2.inputs['Value'])
        nt.links.new(k2.outputs['Result'], h.inputs['Melanin Redness'])
    nt.links.new(h.outputs[0], aus.inputs['Surface'])
    return m


# ------------------------------------------------------------------ Studio
def studio():
    sc = bpy.context.scene
    welt = bpy.data.worlds.new('Studio'); sc.world = welt; welt.use_nodes = True
    welt.node_tree.nodes['Background'].inputs['Color'].default_value = (0.004, 0.0045, 0.005, 1)
    welt.node_tree.nodes['Background'].inputs['Strength'].default_value = 1.0
    # Hohlkehle, dunkel
    bpy.ops.mesh.primitive_plane_add(size=6, location=(0, 0, 0))
    boden = bpy.context.object; boden.name = 'boden'
    boden.data.materials.append(stoff('Hohlkehle', (0.018, 0.019, 0.021), 0.6))
    bpy.ops.mesh.primitive_plane_add(size=6, location=(0, -1.4, 3.0), rotation=(math.radians(90), 0, 0))
    wand = bpy.context.object; wand.name = 'wand'
    wand.data.materials.append(boden.data.materials[0])

    def flaeche(name, ort, ziel, gr, staerke, kelvin):
        l = bpy.data.lights.new(name, 'AREA'); l.shape = 'RECTANGLE'; l.size = gr[0]; l.size_y = gr[1]; l.energy = staerke
        l.use_nodes = True
        bb = l.node_tree.nodes.new('ShaderNodeBlackbody'); bb.inputs['Temperature'].default_value = kelvin
        l.node_tree.links.new(bb.outputs['Color'], l.node_tree.nodes['Emission'].inputs['Color'])
        o = bpy.data.objects.new(name, l); sc.collection.objects.link(o); o.location = ort
        o.rotation_euler = (Vector(ziel) - Vector(ort)).to_track_quat('-Z', 'Y').to_euler()
        return o
    z = Vector(C)
    # Erste Probe mit zehnfacher Leistung: alles ueberstrahlt, Haar wie Haut
    flaeche('Haupt', (-1.1, 1.3, 1.20), z, (1.0, 1.2), 26, 5400)         # von links, auf der Kameraseite
    flaeche('Aufheller', (1.2, 1.6, 0.70), z, (1.0, 1.0), 6, 5600)
    flaeche('KanteL', (-0.9, -0.9, 1.05), z, (0.25, 1.4), 18, 6200)      # Gegenlicht fuer die Haarkanten
    flaeche('KanteR', (0.9, -0.9, 1.05), z, (0.25, 1.4), 15, 4800)
    flaeche('Oben', (0.0, 0.3, 2.0), z, (0.8, 0.8), 9, 5600)


def kamera():
    sc = bpy.context.scene
    cd = bpy.data.cameras.new('Kamera'); cd.lens = 85; cd.sensor_width = 36; cd.sensor_fit = 'HORIZONTAL'
    cam = bpy.data.objects.new('Kamera', cd); sc.collection.objects.link(cam); sc.camera = cam
    # Enger gefasst: Kopf bis Mitte Schulter. Weiter unten sah man in v6 nur
    # noch Umhang und Stativ -- das erzaehlt nichts ueber die Farbe.
    ziel = Vector((0, 0, C[2] - 0.10))
    ort = Vector((0, 2.45, C[2] - 0.02))
    cam.location = ort; cam.rotation_euler = (ziel - ort).to_track_quat('-Z', 'Y').to_euler()
    cd.dof.use_dof = True; cd.dof.focus_distance = (ziel - ort).length; cd.dof.aperture_fstop = 8.0
    cd.clip_start = 0.05
    return cam


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
    r.resolution_x, r.resolution_y = 1600, 900
    r.resolution_percentage = int(EXTRA.get('prozent', 40 if probe else 100))
    sc.cycles.samples = int(EXTRA.get('samples', 64 if probe else 1024))
    # Entrauscher aus: Er verschmiert Einzelhaare zu einer gemalten Flaeche
    # (Vollprobe 24.09.2026, 256 Samples). Stattdessen mehr Samples.
    sc.cycles.use_denoising = EXTRA.get('entrauschen', '0') == '1'
    if 'ausschnitt' in EXTRA:
        x0, x1, y0, y1 = (float(a) for a in EXTRA['ausschnitt'].split(':'))
        r.use_border = True; r.use_crop_to_border = True
        r.border_min_x, r.border_max_x, r.border_min_y, r.border_max_y = x0, x1, y0, y1
    sc.cycles.max_bounces = 24; sc.cycles.transparent_max_bounces = 24
    try:
        sc.cycles_curves.shape = 'THICK'; sc.cycles_curves.subdivisions = 2
    except Exception:
        pass
    sc.view_settings.view_transform = 'AgX'
    sc.view_settings.exposure = float(EXTRA.get('belichtung', 0.35))
    r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'


def main():
    leeren()
    dreh = bpy.data.objects.new('drehteller', None); bpy.context.scene.collection.objects.link(dreh)
    kopf(dreh)
    t0 = time.time()
    haar = haare(int(EXTRA.get('haare', 110000)))
    haar.parent = dreh
    print('HAARE', len(haar.data.curves), round(time.time() - t0, 1), 's')
    studio(); kamera(); render_einstellen(MODUS == 'probe')
    farben = [EXTRA['farbe']] if 'farbe' in EXTRA else list(FARBEN)
    stoffe = {f: haarstoff(f) for f in farben}
    if MODUS == 'probe':
        winkel = [float(w) for w in EXTRA.get('winkel', '-35').split(':')]
    elif MODUS == 'dreh':
        n = int(EXTRA.get('bilder', 16))
        # -75..75: Im reinen Profil verdeckte die Haarsaeule das Gesicht (v7)
        winkel = list(np.linspace(-75, 75, n))
    else:
        winkel = [-35.0]
    status = {'modus': MODUS, 'farben': farben, 'winkel': [round(w, 1) for w in winkel], 'fertig': []}
    for f in farben:
        haar.data.materials.clear(); haar.data.materials.append(stoffe[f])
        ziel = os.path.join(AUSGABE, f) if MODUS == 'dreh' else AUSGABE
        os.makedirs(ziel, exist_ok=True)
        for i, w in enumerate(winkel):
            dreh.rotation_euler = (0, 0, math.radians(w))
            name = f'dreh-{i:02d}.png' if MODUS == 'dreh' else f'{MODUS}-{f}{EXTRA.get("name", "")}{"" if len(winkel) == 1 else f"-{int(w)}"}.png'
            bpy.context.scene.render.filepath = os.path.join(ziel, name)
            # Zwei Ketten koennen dieselbe Farbe rechnen: fertige Bilder bleiben
            if MODUS == 'dreh' and os.path.exists(os.path.join(ziel, name)) and EXTRA.get('neu') != '1':
                continue
            t1 = time.time(); bpy.ops.render.render(write_still=True)
            status['fertig'].append({'farbe': f, 'datei': name, 's': round(time.time() - t1, 1)})
            print('GERECHNET', f, name, round(time.time() - t1, 1), 's')
            with open(os.path.join(AUSGABE, 'status.json'), 'w', encoding='utf-8') as fh:
                json.dump(status, fh, ensure_ascii=False, indent=1)
    with open(os.path.join(AUSGABE, 'farben.json'), 'w', encoding='utf-8') as fh:
        json.dump({k: v['name'] for k, v in FARBEN.items()}, fh, ensure_ascii=False, indent=1)
    print('FERTIG HAAR')


main()
