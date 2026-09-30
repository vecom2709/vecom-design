"""pr_lkw2.py -- Sattelzug in High-End-Fassung (30.09.2026).

Uwe: "Wegen dem LKW nehme als Referenz einen modernen Scania ... hyperrealistisch
und fotorealistisch ... gehe auf Highend". Referenz ist die GATTUNG (moderne
europaeische Fernverkehrs-Zugmaschine mit Hochdach-Fahrerhaus und flachem
Boden, Massstab Scania S 500 A4x2): Proportionen, Detaildichte, Materialien.
Keine Marke, kein Logo, kein Schriftzug, keine markentypische Frontgestaltung
(Greif, Lichtband) -- der Zug traegt VECOM.

Masse (gerundet, EU): Zuglaenge 16,50 m, Breite 2,55 m, Hoehe 4,00 m.
Zugmaschine 4x2: Ueberhang vorn 1,40 m, Radstand 3,60 m, Fahrerhaus
2,49 x 2,28 m, Dach 3,95 m, Kabinenboden 1,45 m, Reifen 315/70 R22.5.
Auflieger: Planenauflieger 13,60 m, 3 Achsen, Reifen 385/65 R22.5.

Die Namen, an denen das Web haengt (kab_angel, rad_*, plane_l_i/plane_r_i,
palette_/ladung_/folie_), bleiben wie in pr_lkw.py -- Zerlegen, Plane und
Ladung laufen ohne Aenderung in produkt-echtzeit.js weiter.

Laengsachse = y (Front nach -y), Breite = x, Hoehe = z.
Aufruf: blender -b -P produkt_bau.py -- lkw2[,web]
"""
import math
import numpy as np
import bpy
import bmesh
from mathutils import Vector, Matrix
from mathutils.bvhtree import BVHTree
import pr_basis as B
import pr_lkw as ALT
import lkw_profil as LP

Y_FRONT = ALT.Y_FRONT            # -8.25
Y_VA, Y_HA, Y_SK = ALT.Y_VA, ALT.Y_HA, ALT.Y_SK
Y_A0, Y_A1, Y_ACHSEN = ALT.Y_A0, ALT.Y_A1, ALT.Y_ACHSEN
BREITE, H_DECK, H_DACH = ALT.BREITE, ALT.H_DECK, ALT.H_DACH

# Fahrerhaus
K0 = Y_FRONT + 0.06              # Front unten (Grill)
K1 = K0 + 2.28                   # Rueckwand
HX = 1.245                       # halbe Breite
ZK0, ZK1 = 1.12, 3.95            # Unterkante Front / Dach
Z_SCH_U, Z_SCH_O = 2.10, 3.16    # Frontscheibe unten / oben
NEIGUNG = math.radians(12.0)     # Scheibe und Stirn nach hinten geneigt (Probe hd: 8 Grad wirkte senkrecht)
Z_TUER_U = 1.30
TUER_HINTEN = K0 + 1.46


def y_stirn(z):
    """Vorderkante des Fahrerhauses in Hoehe z (nach der Neigung)."""
    return K0 + max(0.0, z - 2.05) * math.tan(NEIGUNG)


# ---------------------------------------------------------------- Karosserie
def achse(lo, hi, r_lo, r_hi, grob=0.05, fein_n=7):
    """Stuetzstellen einer Achse: fein in den Rundungen, grob dazwischen."""
    a = list(np.linspace(lo, lo + r_lo, fein_n))
    innen_lo, innen_hi = lo + r_lo, hi - r_hi
    n = max(1, int(math.ceil((innen_hi - innen_lo) / grob)))
    a += list(np.linspace(innen_lo, innen_hi, n + 1))[1:-1]
    a += list(np.linspace(hi - r_hi, hi, fein_n))
    return np.array(sorted(set(round(float(v), 6) for v in a)))


def rundbox(name, lo, hi, r_lo, r_hi, stoffe, form=None, grob=0.05, fein_n=7):
    """Kasten lo..hi mit elliptischen Rundungen je Achse und Seite
    (r_lo fuer die Minus-, r_hi fuer die Plus-Seite). form(x, y, z) verformt
    danach (Neigung, Einzug). UV in Metern nach der Hauptrichtung."""
    lo = np.array(lo, float); hi = np.array(hi, float)
    rl = np.array(r_lo, float); rh = np.array(r_hi, float)
    ax = [achse(lo[i], hi[i], rl[i], rh[i], grob, fein_n) for i in range(3)]
    V, F, idx = [], [], {}

    def pkt(p):
        k = (round(p[0], 6), round(p[1], 6), round(p[2], 6))
        if k not in idx:
            idx[k] = len(V); V.append(p)
        return idx[k]
    for a in range(3):
        b_, c_ = (a + 1) % 3, (a + 2) % 3
        for s, wert in ((-1, lo[a]), (1, hi[a])):
            A, Cc = ax[b_], ax[c_]
            for i in range(len(A) - 1):
                for j in range(len(Cc) - 1):
                    q = []
                    for (ii, jj) in ((i, j), (i + 1, j), (i + 1, j + 1), (i, j + 1)):
                        p = [0.0, 0.0, 0.0]; p[a] = wert; p[b_] = A[ii]; p[c_] = Cc[jj]
                        q.append(pkt(tuple(p)))
                    F.append(tuple(q) if s > 0 else tuple(q[::-1]))
    P = np.array(V)
    k = np.clip(P, lo + rl, hi - rh)
    d = P - k
    r = np.where(d < 0, rl, rh)
    n = d / np.maximum(r, 1e-9)
    ln = np.linalg.norm(n, axis=1, keepdims=True)
    W = np.where(ln > 1e-9, k + r * n / np.maximum(ln, 1e-9), P)
    if form is not None:
        W = np.array([form(*w) for w in W])
    uv = []
    for f in F:
        ps = W[list(f)]
        nrm = np.cross(ps[1] - ps[0], ps[2] - ps[0]); aa = np.abs(nrm)
        if aa[2] >= max(aa[0], aa[1]):
            uv.append([(p[0], p[1]) for p in ps])
        elif aa[1] >= aa[0]:
            uv.append([(p[0], p[2]) for p in ps])
        else:
            uv.append([(p[1], p[2]) for p in ps])
    o = B.netz(name, [tuple(w) for w in W], F, stoffe, None, uv, True)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(o.data); bm.free(); o.data.update()
    return o


def kab_form(x, y, z):
    t = min(1.0, max(0.0, (y - K0) / (K1 - K0)))
    if z > 2.05:                                      # Stirn und Scheibe geneigt
        y += (z - 2.05) * math.tan(NEIGUNG) * (1 - t) ** 1.5
    if z > 3.30:                                      # Dach leicht eingezogen
        x *= 1 - 0.030 * ((z - 3.30) / (ZK1 - 3.30)) ** 2
    if 1.15 < z < 2.05 and t < 0.08:                  # Grillpartie minimal vorgewoelbt
        w = math.sin(math.pi * (z - 1.15) / 0.90) * math.exp(-(x / 0.95) ** 4)
        y -= 0.012 * w * (1 - t / 0.08)
    return (x, y, z)


def flaechen(o, bedingung):
    """Indizes der Flaechen, deren Mitte/Normale die Bedingung erfuellt."""
    me = o.data; out = []
    for p in me.polygons:
        c = o.matrix_world @ p.center; n = (o.matrix_world.to_3x3() @ p.normal).normalized()
        if bedingung(c, n):
            out.append(p.index)
    return out


def stoff_setzen(o, idx, stoff_):
    me = o.data
    if stoff_.name not in [m.name for m in me.materials if m]:
        me.materials.append(stoff_)
    mi = [m.name for m in me.materials].index(stoff_.name)
    for i in idx:
        me.polygons[i].material_index = mi


def abloesen(o, idx, name, stoffe_, versatz):
    """Kopiert die Flaechen idx als eigenes Objekt, um versatz entlang der
    Normale verschoben (Scheiben, Dichtungen)."""
    bm = bmesh.new(); bm.from_mesh(o.data)
    bm.faces.ensure_lookup_table()
    keep = set(idx)
    weg = [f for f in bm.faces if f.index not in keep]
    bmesh.ops.delete(bm, geom=weg, context='FACES')
    bm.normal_update()
    for v in bm.verts:
        v.co += v.normal * versatz
    me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
    me.materials.clear()
    for s in stoffe_:
        me.materials.append(s)
    for p in me.polygons:
        p.material_index = 0; p.use_smooth = True
    ob = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(ob)
    ob.matrix_world = o.matrix_world
    return ob


class Oberflaeche:
    """Strahltest gegen die Karosserie: Punkt und Normale auf der Aussenhaut."""
    def __init__(self, o):
        dg = bpy.context.evaluated_depsgraph_get()
        self.bvh = BVHTree.FromObject(o, dg)
        self.mw = o.matrix_world

    def treffer(self, start, richtung):
        loc, nrm, _i, _d = self.bvh.ray_cast(Vector(start), Vector(richtung).normalized())
        if loc is None:
            return None, None
        return loc, nrm.normalized()


def band(name, flaeche, punkte, richtung_fn, breite, stoffe_, abstand=0.0008, geschlossen=False):
    """Schmales Band auf der Karosserie entlang einer Punktfolge (Fugen,
    Wischerarme). richtung_fn(p) liefert Start und Strahlrichtung von aussen."""
    P, N = [], []
    for p in punkte:
        s, r = richtung_fn(p)
        loc, nrm = flaeche.treffer(s, r)
        if loc is None:
            continue
        P.append(loc + nrm * abstand); N.append(nrm)
    if len(P) < 2:
        return None
    V, F = [], []
    m = len(P)
    for i in range(m):
        a = P[(i + 1) % m] if (geschlossen or i < m - 1) else P[i]
        b = P[i - 1] if (geschlossen or i > 0) else P[i]
        t = (a - b).normalized()
        q = N[i].cross(t).normalized() * (breite / 2)
        V += [tuple(P[i] - q), tuple(P[i] + q)]
    for i in range(m - 1 + (1 if geschlossen else 0)):
        j = (i + 1) % m
        F.append((2 * i, 2 * j, 2 * j + 1, 2 * i + 1))
    uv = [[(0, 0), (1, 0), (1, 1), (0, 1)]] * len(F)
    o = B.netz(name, V, F, stoffe_, None, uv, True)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bm.normal_update()
    for f in bm.faces:                                # Normale nach aussen
        if f.normal.dot(Vector(N[min(len(N) - 1, f.verts[0].index // 2)])) < 0:
            f.normal_flip()
    bm.to_mesh(o.data); bm.free()
    return o


def trapez_rund(hw_oben, hw_unten, z_oben, z_unten, r, n=6, x_innen_oben=None, x_innen_unten=None, x_aussen=None):
    """Umriss (x, z) eines Trapezes mit gerundeten Ecken, gegen den
    Uhrzeigersinn. Standard: symmetrisch um x = 0 (hw = halbe Breite oben/
    unten). Mit x_innen_*/x_aussen: einseitiges Viereck rechts (Scheinwerfer)."""
    if x_innen_oben is None:
        ecken = [(-hw_unten, z_unten), (hw_unten, z_unten), (hw_oben, z_oben), (-hw_oben, z_oben)]
    else:
        ecken = [(x_innen_unten, z_unten), (x_aussen, z_unten), (x_aussen, z_oben), (x_innen_oben, z_oben)]
    P = []
    m = len(ecken)
    for i in range(m):
        a, b_, c = np.array(ecken[i - 1], float), np.array(ecken[i], float), np.array(ecken[(i + 1) % m], float)
        u1 = (a - b_) / np.linalg.norm(a - b_); u2 = (c - b_) / np.linalg.norm(c - b_)
        p1, p2 = b_ + u1 * r, b_ + u2 * r
        for k in range(n + 1):                       # quadratische Bezier als Eckrundung
            t = k / n
            P.append(tuple((1 - t) ** 2 * p1 + 2 * (1 - t) * t * b_ + t ** 2 * p2))
    return P


def poly_versatz(P, d):
    """Umriss (a, b) um d nach aussen versetzen (Gehrung, fuer sanfte Umrisse)."""
    P = np.array(P, float); n = len(P); out = []
    flaeche = 0.5 * sum(P[i - 1][0] * P[i][1] - P[i][0] * P[i - 1][1] for i in range(n))
    s_ = 1.0 if flaeche > 0 else -1.0
    for i in range(n):
        a, b_, c = P[i - 1], P[i], P[(i + 1) % n]
        e1 = b_ - a; e2 = c - b_
        n1 = np.array([e1[1], -e1[0]]) * s_; n1 /= max(1e-9, np.linalg.norm(n1))
        n2 = np.array([e2[1], -e2[0]]) * s_; n2 /= max(1e-9, np.linalg.norm(n2))
        m = n1 + n2; m /= max(1e-9, np.linalg.norm(m))
        out.append(tuple(b_ + m * d / max(0.35, float(np.dot(m, n1)))))
    return out


def ring_prisma(name, aussen, innen, y0, y1, stoffe_, fase=0.004, achse='y'):
    """Rahmen zwischen zwei Umrissen (gleiche Punktzahl) von y0 (vorn) bis y1
    (hinten), gefast. Deckt die Treppenkanten der Flaechenauswahl ab."""
    n = len(aussen); V, F = [], []
    pk = (lambda a, t, z: (a, t, z)) if achse == 'y' else (lambda a, t, z: (t, a, z))
    for (x, z) in aussen: V.append(pk(x, y0, z))
    for (x, z) in innen: V.append(pk(x, y0, z))
    for (x, z) in aussen: V.append(pk(x, y1, z))
    for (x, z) in innen: V.append(pk(x, y1, z))
    for i in range(n):
        j = (i + 1) % n
        F.append((i, j, n + j, n + i))                         # Front
        F.append((2 * n + i, 3 * n + i, 3 * n + j, 2 * n + j))  # Rueckseite
        F.append((i, 2 * n + i, 2 * n + j, j))                 # Aussenwand
        F.append((n + i, n + j, 3 * n + j, 3 * n + i))         # Innenwand
    uv = [[(0, 0), (1, 0), (1, 1), (0, 1)]] * len(F)
    o = B.netz(name, V, F, stoffe_, None, uv, True, kante_winkel=40)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(o.data); bm.free(); o.data.update()
    if fase:
        m = o.modifiers.new('fase', 'BEVEL'); m.width = fase; m.segments = 2; m.limit_method = 'ANGLE'
    return o


# ---------------------------------------------------------------- Stoffe
def stoffe():
    S = {}
    VAR = ['Rot', 'Weiss', 'Blau']
    lack = [('Lack Rot', (0.30, 0.008, 0.012)), ('Lack Weiss', (0.80, 0.80, 0.78)), ('Lack Blau', (0.010, 0.026, 0.085))]
    # Autolack: Grundschicht + Klarlack, Orangenhaut als flache Normalkarte
    S['lack'] = [B.stoff(n, c, 0.26, coat=1.0, coat_rau=0.018, normal='lkw-lack-normal', normal_staerke=0.12, kachel=0.30)
                 for n, c in lack]
    S['plane'] = [B.stoff('Plane Weiss', (1, 1, 1), 0.48, farbkarte='lkw-plane-weiss-farbe', spec=0.42),
                  B.stoff('Plane Grau', (1, 1, 1), 0.48, farbkarte='lkw-plane-grau-farbe', spec=0.42),
                  B.stoff('Plane Blau', (1, 1, 1), 0.48, farbkarte='lkw-plane-blau-farbe', spec=0.42)]
    S['dachplane'] = [B.stoff('Dachplane Weiss', (0.76, 0.76, 0.74), 0.55, spec=0.35),
                      B.stoff('Dachplane Grau', (0.28, 0.29, 0.31), 0.55, spec=0.35),
                      B.stoff('Dachplane Blau', (0.03, 0.06, 0.16), 0.55, spec=0.35)]
    S['kunststoff'] = B.stoff('Kunststoff genarbt', (0.030, 0.031, 0.033), 0.62, normal='lkw-kunststoff-normal', normal_staerke=0.45, kachel=0.20)
    S['glanz'] = B.stoff('Kunststoff Klavierlack', (0.006, 0.006, 0.007), 0.10, coat=0.8, coat_rau=0.02)
    S['grill'] = B.stoff('Grill Gitter', (1, 1, 1), 0.55, farbkarte='lkw-waben-farbe', normal='lkw-waben-normal', normal_staerke=1.0, kachel=0.12)
    S['rahmen'] = B.stoff('Rahmen Schwarz', (0.016, 0.016, 0.018), 0.42, coat=0.3, coat_rau=0.2)
    S['chrom'] = B.stoff('Chrom', (0.80, 0.80, 0.80), 0.04, metall=1.0)
    S['alu'] = B.stoff('Aluminium satiniert', (0.63, 0.64, 0.65), 0.30, metall=1.0)
    S['alu_pol'] = B.stoff('Felge Alu poliert', (0.91, 0.91, 0.92), 0.07, metall=1.0)
    S['riffel'] = B.stoff('Riffelblech', (0.62, 0.63, 0.64), 0.34, metall=1.0, normal='lkw-riffel-normal', normal_staerke=1.0, kachel=0.20)
    S['glas'] = B.stoff('Glas Scheibe', (0.86, 0.93, 0.90), 0.015, trans=1.0, ior=1.52)
    S['frit'] = B.stoff('Siebdruck Rand', (0.007, 0.007, 0.008), 0.22, coat=0.9, coat_rau=0.01)
    S['innen'] = B.stoff('Innenraum', (0.060, 0.058, 0.056), 0.75)
    S['dichtung'] = B.stoff('Dichtung', (0.012, 0.012, 0.013), 0.70)
    S['fuge'] = B.stoff('Fuge', (0.004, 0.004, 0.004), 0.75, spec=0.1)
    S['linse'] = B.stoff('Glas Leuchte', (0.95, 0.96, 0.97), 0.02, trans=1.0, ior=1.49)
    S['led'] = B.stoff('LED Tagfahrlicht', (0.9, 0.9, 0.9), 0.2, emiss=((1.0, 0.97, 0.92), 3.0))
    S['reflektor'] = B.stoff('Reflektor', (0.85, 0.85, 0.86), 0.10, metall=1.0)
    S['blinker'] = B.stoff('Blinker Glas', (0.95, 0.40, 0.02), 0.12, spec=0.6)
    S['rueck'] = B.stoff('Rueckleuchte', (0.45, 0.01, 0.012), 0.10, spec=0.6, emiss=((1.0, 0.04, 0.02), 0.25))
    S['reflex_gelb'] = B.stoff('Reflexfolie Gelb', (0.92, 0.66, 0.02), 0.30, spec=0.8)
    S['reflex_rot'] = B.stoff('Reflexfolie Rot', (0.60, 0.02, 0.02), 0.30, spec=0.8)
    S['reifen_zug'] = B.stoff('Reifen Zug', (1, 1, 1), 0.78, spec=0.35, farbkarte='lkw-reifen-zug-farbe', normal='lkw-reifen-zug-normal', normal_staerke=0.7)
    S['reifen_af'] = B.stoff('Reifen Auflieger', (1, 1, 1), 0.78, spec=0.35, farbkarte='lkw-reifen-auflieger-farbe', normal='lkw-reifen-auflieger-normal', normal_staerke=0.7)
    S['nabe'] = B.stoff('Nabe', (0.30, 0.31, 0.32), 0.35, metall=1.0)
    S['handloch'] = B.stoff('Felge Handloch', (0.010, 0.010, 0.011), 0.6)
    S['kennzeichen'] = B.stoff('Kennzeichen', (1, 1, 1), 0.35, farbkarte='lkw-kennzeichen-farbe')
    S['holz'] = B.stoff('Ladeboden', (1, 1, 1), 0.7, farbkarte='holz-kiste-farbe', kachel=0.60)
    S['palette'] = B.stoff('Palette Holz', (0.42, 0.31, 0.19), 0.85)
    S['karton'] = B.stoff('Karton', (0.40, 0.28, 0.15), 0.85)
    S['folie'] = B.stoff('Stretchfolie', (0.9, 0.9, 0.9), 0.15, trans=0.55, ior=1.45)
    S['gurt'] = B.stoff('Spanngurt', (0.035, 0.035, 0.04), 0.65)
    # Scania-Runde: Grillstaebe in dunklem Gunmetal, Innenverkleidung, Dachhimmel,
    # Scheinwerfer-Innenleben in dunklem Chrom (daran erkennt man echte Leuchten)
    S['leiste'] = B.stoff('Grill Leiste Gunmetal', (0.060, 0.062, 0.066), 0.30, metall=1.0, coat=0.5, coat_rau=0.05)
    S['verkleidung'] = B.stoff('Innenverkleidung', (0.13, 0.125, 0.12), 0.80)
    S['dachhimmel'] = B.stoff('Dachhimmel', (0.40, 0.38, 0.35), 0.92)
    S['sw_innen'] = B.stoff('Scheinwerfer Innen', (0.30, 0.30, 0.31), 0.22, metall=1.0)
    S['graphit'] = B.stoff('Kunststoff Graphit', (0.030, 0.032, 0.035), 0.42, coat=0.25, coat_rau=0.12, normal='lkw-kunststoff-normal', normal_staerke=0.15, kachel=0.20)
    return VAR, S


# ---------------------------------------------------------------- Raeder
def rad(name, x, y, art, seite, S, zwilling_innen=False):
    """Rad als Gruppe unter einem Empty in der Radmitte (lokale z = Achse,
    nach aussen). Reifen nach lkw_profil (mit Profil- und Flankenkarte),
    polierte Alu-Scheibenfelge 22.5 x 9.00 mit 10 Handloechern."""
    d = LP.REIFEN[art]
    P, _A = LP.reifen(**d)
    ra = max(p[0] for p in P)
    reifen = S['reifen_zug'] if art == 'zug' else S['reifen_af']
    teile = [B.drehkoerper(name + '_reifen', P, 96, [reifen], kante_winkel=50)]
    b2 = d['breite'] / 2
    rf = 0.5715 / 2
    # Felge: Horn, Bett, Schuessel (nach aussen gewoelbt), Nabenflansch
    felge = [(0.0, b2 - 0.02), (0.085, b2 - 0.02), (0.110, b2 - 0.035), (0.150, b2 - 0.040), (0.20, b2 - 0.030),
             (0.245, b2 - 0.012), (rf - 0.004, b2 - 0.004), (rf + 0.012, b2 - 0.012), (rf + 0.012, b2 - 0.030),
             (rf - 0.012, b2 - 0.040), (rf - 0.020, -b2 + 0.030), (rf + 0.012, -b2 + 0.020), (rf + 0.012, -b2 + 0.006),
             (rf - 0.010, -b2 + 0.010), (rf - 0.030, -b2 + 0.035), (0.22, b2 - 0.075), (0.12, b2 - 0.080), (0.0, b2 - 0.080)]
    teile.append(B.drehkoerper(name + '_felge', felge, 96, [S['alu_pol']], kante_winkel=40))
    # Handloecher (dunkle Durchbrueche in der Schuessel), 10 Stueck
    for k in range(10):
        a = 2 * math.pi * (k + 0.5) / 10
        h = B.drehkoerper(f'{name}_loch_{k}', [(0.0, 0.0), (0.030, 0.0), (0.030, 0.004), (0.0, 0.004)], 20, [S['handloch']])
        h.scale = (1.25, 0.80, 1.0)
        h.rotation_euler = (0, 0, a)
        h.location = (0.218 * math.cos(a), 0.218 * math.sin(a), b2 - 0.024)
        teile.append(h)
    # Nabe mit Kappe, 10 Radmuttern M22 (SW 32) mit Kappen
    teile.append(B.drehkoerper(name + '_nabe', [(0.0, b2 - 0.03), (0.098, b2 - 0.03), (0.098, b2 + 0.010), (0.080, b2 + 0.034),
                                               (0.0, b2 + 0.042)], 48, [S['chrom'] if not zwilling_innen else S['nabe']]))
    for k in range(10):
        a = 2 * math.pi * k / 10
        m = B.drehkoerper(f'{name}_mutter_{k}', [(0.0, 0.0), (0.019, 0.0), (0.019, 0.022), (0.016, 0.030), (0.0, 0.034)], 6, [S['chrom']])
        m.location = (0.1675 * math.cos(a), 0.1675 * math.sin(a), b2 - 0.037)   # Lochkreis 335 mm
        m.rotation_euler = (0, 0, a)
        teile.append(m)
    e = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(e)
    for o in teile:
        o.parent = e
    e.rotation_euler = (0, math.radians(90 * seite), 0)
    e.location = (x, y, ra)
    return e, ra


# ---------------------------------------------------------------- Fahrerhaus
def fahrerhaus(S):
    lack = S['lack'][0]
    kab = []
    k = rundbox('kab_koerper', (-HX, K0, ZK0), (HX, K1, ZK1), (0.28, 0.32, 0.04), (0.28, 0.07, 0.16),
                [lack], form=kab_form, grob=0.030, fein_n=8)
    kab.append(k)
    front = lambda c, n: n.y < -0.45

    # Frontscheibe: Glas 4 mm vor der Haut, darunter dunkler Innenraum,
    # ringsum schwarzer Siebdruckrand (wie echte Verbundglasscheiben)
    def in_scheibe(c, n, rand=0.0):
        # Scheibe laeuft in die Ecken hinein (Panorama), Normale bis 72 Grad seitlich
        return n.y < -0.30 and Z_SCH_U - rand < c.z < Z_SCH_O + rand and abs(c.x) < 1.17 + rand * 0.6
    scheibe = flaechen(k, lambda c, n: in_scheibe(c, n))
    ring = [i for i in flaechen(k, lambda c, n: in_scheibe(c, n, 0.075)) if i not in set(scheibe)]
    stoff_setzen(k, ring, S['frit'])
    glas_front = abloesen(k, scheibe, 'kab_frontscheibe', [S['glas']], 0.004)
    kab.append(glas_front)
    # Die Flaechen hinter dem Glas werden unten entfernt: Durch die Scheibe
    # sieht man ins Fahrerhaus (Probe hdd: eine dunkle Flaeche direkt hinter
    # dem Glas machte die Scheibe undurchsichtig schwarz)
    loch = set(scheibe)

    # Seitenfenster in der Tuer: Vorderkante parallel zur A-Saeule
    def in_fenster(c, n, rand=0.0):
        vorn = y_stirn(c.z) + 0.26 - rand
        hinten = TUER_HINTEN - 0.10 + rand
        if not (abs(n.x) > 0.8 and vorn < c.y < hinten):
            return False
        # Bruestungslinie faellt nach vorn ab (Sicht auf den Bordstein), wie
        # bei heutigen Fernverkehrskabinen
        t = (c.y - vorn) / max(0.01, hinten - vorn)
        z_u = 2.00 + 0.16 * min(1.0, t / 0.35)
        return z_u - rand < c.z < 3.02 + rand
    fenster = flaechen(k, lambda c, n: in_fenster(c, n))
    fring = [i for i in flaechen(k, lambda c, n: in_fenster(c, n, 0.035)) if i not in set(fenster)]
    stoff_setzen(k, fring, S['dichtung'])
    kab.append(abloesen(k, fenster, 'kab_seitenscheiben', [S['glas']], 0.003))
    loch |= set(fenster)
    # Gummirahmen ueber der Schnittkante (sonst Treppenstufen des Rasters)
    umriss = []
    vorn_u, hinten = y_stirn(2.00) + 0.26, TUER_HINTEN - 0.10
    for t in np.linspace(0, 1, 24):
        yy = vorn_u + (hinten - vorn_u) * t
        umriss.append((yy, 2.00 + 0.16 * min(1.0, t / 0.35)))
    umriss += [(hinten, z) for z in np.linspace(2.16, 3.02, 12)[1:]]
    umriss += [(y, 3.02) for y in np.linspace(hinten, y_stirn(3.02) + 0.26, 16)[1:]]
    umriss += [(y_stirn(z) + 0.26, z) for z in np.linspace(3.02, 2.00, 16)[1:-1]]
    for sx in (-1, 1):
        kab.append(ring_prisma(f'kab_fensterdichtung_{"l" if sx < 0 else "r"}', poly_versatz(umriss, 0.026), poly_versatz(umriss, -0.008),
                               sx * (HX + 0.006), sx * (HX - 0.03), [S['dichtung']], fase=0.003, achse='x'))

    # Eckleitbleche: die vorderen Kabinenecken in Klavierlack-Schwarz von der
    # Stossfaengerkante bis zur Dachkante -- so laeuft das dunkle Glasband um
    # die Ecke in die Tuer, wie bei heutigen Fernverkehrskabinen
    ecke = flaechen(k, lambda c, n: -0.93 < n.y < -0.12 and abs(n.x) > 0.25 and 1.16 < c.z < Z_SCH_O + 0.02)
    ecke = [i for i in ecke if i not in set(scheibe)]
    stoff_setzen(k, ecke, S['glanz'])
    # Unterer Rand der Seitenwand in genarbtem Kunststoff (Einstiegsschutz)
    sockel = flaechen(k, lambda c, n: abs(n.x) > 0.7 and c.z < 1.47)
    stoff_setzen(k, sockel, S['kunststoff'])

    # Grill (Scania-Runde): hohes Trapez, unten breiter, zurueckgesetztes
    # Wabengitter, davor vier kraeftige Querstaebe in Gunmetal, ringsum eine
    # schmale Klavierlack-Einfassung. Darueber die Kopfleiste in Wagenfarbe
    # mit Chromlinie und Schriftzug.
    def hw(z):
        return 0.80 + (1.96 - z) * 0.14
    grill = flaechen(k, lambda c, n: front(c, n) and 1.24 < c.z < 1.88 and abs(c.x) < hw(c.z))
    einfass = [i for i in flaechen(k, lambda c, n: front(c, n) and 1.20 < c.z < 1.915 and abs(c.x) < hw(c.z) + 0.04)
               if i not in set(grill)]
    stoff_setzen(k, grill, S['grill']); stoff_setzen(k, einfass, S['glanz'])
    kab.append(ring_prisma('kab_grill_einfassung', trapez_rund(hw(1.915) + 0.035, hw(1.205) + 0.035, 1.915, 1.205, 0.06),
                           trapez_rund(hw(1.885) - 0.012, hw(1.235) - 0.012, 1.885, 1.235, 0.04), K0 - 0.016, K0 + 0.05, [S['glanz']]))
    bm = bmesh.new(); bm.from_mesh(k.data); bm.faces.ensure_lookup_table()
    for v in {v for i in grill for v in bm.faces[i].verts}:
        v.co.y += 0.045
    for v in {v for i in einfass for v in bm.faces[i].verts} - {v for i in grill for v in bm.faces[i].verts}:
        v.co.y += 0.012
    bm.to_mesh(k.data); bm.free(); k.data.update()
    for i, z in enumerate((1.31, 1.455, 1.60, 1.745)):
        kab.append(B.rundkasten(f'kab_lamelle_{i}', 2 * hw(z + 0.035) - 0.05, 0.055, 0.072, 0.026, [S['leiste']],
                                (0, K0 + 0.012, z), n=5))
    kab.append(B.rundkasten('kab_chromlinie', 2 * hw(1.90) + 0.02, 0.012, 0.011, 0.004, [S['chrom']], (0, K0 - 0.006, 1.921), n=3))
    # Schriftzug VECOM (eigene Marke) in der Kopfleiste, weit gesperrt
    cu = bpy.data.curves.new('kab_schrift_k', 'FONT'); cu.body = 'VECOM'; cu.size = 0.095; cu.extrude = 0.004
    cu.align_x = 'CENTER'; cu.align_y = 'CENTER'; cu.space_character = 1.45
    try:
        cu.font = bpy.data.fonts.load(r'C:\Windows\Fonts\arialbd.ttf')
    except Exception:
        pass
    t = bpy.data.objects.new('kab_schrift_k', cu); bpy.context.scene.collection.objects.link(t)
    bpy.context.view_layer.update()
    me = bpy.data.meshes.new_from_object(t.evaluated_get(bpy.context.evaluated_depsgraph_get()))
    bpy.data.objects.remove(t, do_unlink=True)
    me.materials.clear(); me.materials.append(S['chrom'])
    so = bpy.data.objects.new('kab_schriftzug', me); bpy.context.scene.collection.objects.link(so)
    so.rotation_euler = (math.radians(90), 0, 0); so.location = (0, K0 - 0.009, 1.985)
    kab.append(so)

    # Loecher schneiden und Innenhaut einziehen: Verkleidung an den Waenden,
    # heller Dachhimmel oben -- das sieht man durch Front- und Seitenscheiben
    bm = bmesh.new(); bm.from_mesh(k.data); bm.faces.ensure_lookup_table()
    bmesh.ops.delete(bm, geom=[bm.faces[i] for i in sorted(loch)], context='FACES_ONLY')
    bm.to_mesh(k.data); bm.free(); k.data.update()
    innen = abloesen(k, list(range(len(k.data.polygons))), 'kab_innenhaut', [S['verkleidung'], S['dachhimmel']], -0.035)
    bm = bmesh.new(); bm.from_mesh(innen.data)
    for f in bm.faces:
        f.normal_flip()
    bm.normal_update()
    for f in bm.faces:
        f.material_index = 1 if f.normal.z < -0.6 else 0
    bm.to_mesh(innen.data); bm.free(); innen.data.update()
    kab.append(innen)

    # Sonnenblende ueber der Scheibe mit Positionsleuchten
    zb = Z_SCH_O + 0.10
    yb = y_stirn(zb)
    # Sonnenblende als ausgeformte Dachkante (nicht als aufgesetztes Brett)
    sb = rundbox('kab_sonnenblende', (-1.16, yb - 0.11, zb - 0.040), (1.16, yb + 0.12, zb + 0.045), (0.20, 0.07, 0.035), (0.20, 0.02, 0.03),
                 [lack], grob=0.06, fein_n=6)
    kab.append(sb)
    for i, x in enumerate(np.linspace(-0.80, 0.80, 5)):
        kab.append(B.rundkasten(f'kab_dachleuchte_{i}', 0.07, 0.018, 0.010, 0.004, [S['linse']], (x, yb - 0.090, zb + 0.040), n=3))

    # Tuerfuge und Klappenfugen als schmale dunkle Baender auf der Haut
    fl = Oberflaeche(k)
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        pts = []
        for z in np.linspace(Z_TUER_U, 3.10, 30):
            pts.append((sx, y_stirn(z) + 0.13, z))
        for y in np.linspace(y_stirn(3.10) + 0.13, TUER_HINTEN, 22)[1:]:
            pts.append((sx, y, 3.10))
        for z in np.linspace(3.10, Z_TUER_U, 30)[1:]:
            pts.append((sx, TUER_HINTEN, z))
        for y in np.linspace(TUER_HINTEN, y_stirn(Z_TUER_U) + 0.13, 22)[1:-1]:
            pts.append((sx, y, Z_TUER_U))
        f = band(f'kab_tuerfuge_{sl}', fl, pts, lambda p: ((p[0] * 3.0, p[1], p[2]), (-p[0], 0, 0)), 0.006, [S['fuge']], geschlossen=True)
        if f:
            kab.append(f)
        # Griffmulde und Griff
        kab.append(B.rundkasten(f'kab_griffmulde_{sl}', 0.012, 0.20, 0.07, 0.02, [S['glanz']], (sx * (HX + 0.001), TUER_HINTEN - 0.24, 2.02), n=4))
        kab.append(B.rundkasten(f'kab_griff_{sl}', 0.022, 0.16, 0.024, 0.008, [S['alu']], (sx * (HX + 0.010), TUER_HINTEN - 0.24, 2.04), n=4))
        # Haltegriff senkrecht an der hinteren Kante
        kab.append(B.rohr(f'kab_haltegriff_{sl}', [(sx * (HX - 0.01), K1 - 0.22, 1.55), (sx * (HX + 0.045), K1 - 0.20, 1.62),
                                                   (sx * (HX + 0.045), K1 - 0.20, 2.55), (sx * (HX - 0.01), K1 - 0.22, 2.62)], 0.017, [S['glanz']]))
        # Spiegel: Arm von der A-Saeule, Hauptspiegel + Weitwinkel darunter
        ya = y_stirn(3.00) + 0.10
        kab.append(B.rohr(f'kab_spiegelarm_{sl}', [(sx * (HX - 0.02), ya, 3.00), (sx * (HX + 0.18), ya - 0.10, 3.02),
                                                   (sx * (HX + 0.27), ya - 0.14, 2.92), (sx * (HX + 0.27), ya - 0.14, 2.40)], 0.016, [S['glanz']]))
        for nm, z0, hoch in (('haupt', 2.44, 0.44), ('weit', 2.22, 0.19)):
            gh = rundbox(f'kab_spiegel_{nm}_{sl}', (sx * (HX + 0.22) - 0.11, ya - 0.215, z0), (sx * (HX + 0.22) + 0.11, ya - 0.08, z0 + hoch),
                         (0.035, 0.09, 0.035), (0.035, 0.012, 0.035), [S['glanz']], grob=0.03, fein_n=6,
                         form=lambda x, y, z, y0=ya - 0.215, y1=ya - 0.08, xm=sx * (HX + 0.22): (xm + (x - xm) * (1 - 0.10 * max(0.0, (y1 - y) / (y1 - y0))), y, z))
            if nm == 'haupt':                          # Rueckseite in Wagenfarbe (Kappe)
                kappe = flaechen(gh, lambda c, n: n.y < -0.35)
                stoff_setzen(gh, kappe, lack)
            kab.append(gh)
            kab.append(B.rundkasten(f'kab_spiegelglas_{nm}_{sl}', 0.19, 0.004, hoch - 0.03, 0.01, [S['chrom']],
                                    (sx * (HX + 0.22), ya - 0.078, z0 + 0.015), n=3))
        # Seitenleitblech an der Rueckwand (schliesst die Luecke zum Auflieger)
        lb = rundbox(f'kab_leitblech_{sl}', (sx * HX - 0.018, K1 - 0.06, 1.45), (sx * HX + 0.018, K1 + 0.40, 3.72), (0.01, 0.02, 0.05), (0.01, 0.10, 0.10),
                     [lack], grob=0.08, fein_n=5)
        kab.append(lb)
    # Frontklappe (Wartung): Fugenlinie seitlich und unten, oben verschwindet
    # sie im Siebdruckrand der Scheibe
    pts = [(-0.975, 0, z) for z in np.linspace(2.03, 1.24, 30)] + \
          [(-0.975 + 0.06 * (1 - math.cos(a)), 0, 1.24 - 0.06 * math.sin(a)) for a in np.linspace(0, math.pi / 2, 8)[1:]] + \
          [(x, 0, 1.18) for x in np.linspace(-0.915, 0.915, 50)[1:-1]] + \
          [(0.915 + 0.06 * math.sin(a), 0, 1.18 + 0.06 * (1 - math.cos(a))) for a in np.linspace(0, math.pi / 2, 8)] + \
          [(0.975, 0, z) for z in np.linspace(1.25, 2.03, 30)]
    f = band('kab_klappenfuge', fl, pts, lambda p: ((p[0], K0 - 2.0, p[2]), (0, 1, 0)), 0.006, [S['fuge']], abstand=0.0006)
    if f:
        kab.append(f)
    # Hochdach: waagerechte Fuge ueber der Tuer (Dachaufsatz), gliedert die grosse Seitenflaeche
    for sx in (-1, 1):
        pts = [(sx, y, 3.34) for y in np.linspace(y_stirn(3.34) + 0.25, K1 - 0.10, 40)]
        f = band(f'kab_dachfuge_{"l" if sx < 0 else "r"}', fl, pts, lambda p: ((p[0] * 3.0, p[1], p[2]), (-p[0], 0, 0)), 0.005, [S['fuge']])
        if f:
            kab.append(f)
    # Wischerarme unten an der Scheibe
    for sx in (-1, 1):
        pts = [(x, 0, Z_SCH_U + 0.04 + 0.02 * abs(x)) for x in np.linspace(sx * 0.10, sx * 0.95, 16)]
        w = band(f'kab_wischer_{"l" if sx < 0 else "r"}', Oberflaeche(glas_front), pts, lambda p: ((p[0], K0 - 2.0, p[2]), (0, 1, 0)), 0.022, [S['glanz']], abstand=0.010)
        if w:
            kab.append(w)
    # Innenraum hinter den Scheiben: Armaturenbrett, Lenkrad, Sitzlehnen
    S.setdefault('armatur', B.stoff('Armatur Oberseite', (0.11, 0.11, 0.105), 0.7))
    S.setdefault('sitzbezug', B.stoff('Sitzbezug', (0.09, 0.085, 0.08), 0.85))
    kab.append(B.rundkasten('kab_armatur', 2.30, 0.55, 0.30, 0.08, [S['armatur']], (0, K0 + 0.55, 1.95), n=6))
    kab.append(B.rundkasten('kab_bildschirm', 0.32, 0.03, 0.20, 0.02, [S['glanz']], (-0.10, K0 + 0.80, 2.24), n=3))
    kab.append(B.rundkasten('kab_vorhang', 2.30, 0.04, 1.40, 0.05, [S['innen']], (0, K1 - 0.35, 1.50), n=4))
    lr = B.drehkoerper('kab_lenkrad', [(0.21, -0.02), (0.235, 0.0), (0.21, 0.02), (0.185, 0.0), (0.21, -0.02)], 48, [S['innen']])
    lr.rotation_euler = (math.radians(90 - 22), 0, 0); lr.location = (-0.62, K0 + 0.78, 2.22); kab.append(lr)
    for sx in (-1, 1):
        kab.append(B.rundkasten(f'kab_sitz_{sx}', 0.52, 0.16, 0.80, 0.08, [S['sitzbezug']], (sx * 0.62, K0 + 1.55, 1.95), n=6))
        kab.append(B.rundkasten(f'kab_kopfstuetze_{sx}', 0.30, 0.12, 0.22, 0.06, [S['sitzbezug']], (sx * 0.62, K0 + 1.57, 2.80), n=5))
    return kab, k


# ---------------------------------------------------------------- Front unten, Einstieg, Radlauf
def stossfaenger(S):
    lack = S['lack'][0]
    teile = []
    y_ende = Y_VA - 0.62                              # endet vor dem Vorderrad
    # oben in Wagenfarbe mit den Scheinwerfern, unten genarbter Kunststoff
    teile.append(rundbox('zm_stoss_oben', (-HX, K0 - 0.035, 0.80), (HX, y_ende, 1.105), (0.09, 0.14, 0.03), (0.09, 0.03, 0.03),
                         [S['graphit']], grob=0.025, fein_n=6))
    teile.append(rundbox('zm_stoss_unten', (-HX + 0.01, K0 - 0.045, 0.40), (HX - 0.01, y_ende, 0.795), (0.20, 0.20, 0.06), (0.20, 0.03, 0.02),
                         [S['kunststoff']], grob=0.06, fein_n=6))
    teile.append(B.rundkasten('zm_stoss_fuge', 2.40, 0.02, 0.008, 0.003, [S['fuge']], (0, K0 - 0.040, 0.793), n=3))
    # Scheinwerfer (Scania-Runde): grosse Einheiten in den Stossfaengerecken,
    # innere Kante schraeg. Unter einer buendigen Klarglasscheibe liegt ein
    # Gehaeuse in dunklem Chrom mit zwei Projektoren fuer Abblendlicht, einem
    # kleineren fuer Fernlicht, einem L-foermigen Tagfahrlicht (oben und
    # aussen) und dem Blinker unten innen.
    so = teile[0]
    def x_innen(z):
        return 0.58 + (z - 0.83) * 0.45
    mulde = flaechen(so, lambda c, n: n.y < -0.5 and 0.835 < c.z < 1.075 and x_innen(c.z) < abs(c.x) < 1.135)
    rand = [i for i in flaechen(so, lambda c, n: n.y < -0.4 and 0.815 < c.z < 1.092 and x_innen(c.z) - 0.02 < abs(c.x) < 1.16)
            if i not in set(mulde)]
    for sx in (-1, 1):
        au = trapez_rund(0, 0, 1.092, 0.815, 0.025, x_innen_oben=x_innen(1.092) - 0.022, x_innen_unten=x_innen(0.815) - 0.022, x_aussen=1.150)
        ii = trapez_rund(0, 0, 1.070, 0.838, 0.018, x_innen_oben=x_innen(1.070) + 0.004, x_innen_unten=x_innen(0.838) + 0.004, x_aussen=1.128)
        if sx < 0:
            au = [(-x, z) for (x, z) in au][::-1]; ii = [(-x, z) for (x, z) in ii][::-1]
        teile.append(ring_prisma(f'zm_sw_rahmen_{"l" if sx < 0 else "r"}', au, ii, K0 - 0.041, K0 + 0.02, [S['glanz']], fase=0.003))
    # mittlerer Lufteinlass zwischen den Scheinwerfern (Wabengitter, Rahmen)
    mitte_ein = flaechen(so, lambda c, n: n.y < -0.5 and 0.875 < c.z < 1.045 and abs(c.x) < 0.43)
    stoff_setzen(so, mitte_ein, S['grill'])
    teile.append(ring_prisma('zm_einlass_rahmen', trapez_rund(0.47, 0.44, 1.068, 0.852, 0.03), trapez_rund(0.435, 0.405, 1.040, 0.880, 0.02),
                             K0 - 0.041, K0 + 0.02, [S['glanz']], fase=0.003))
    deckglas = abloesen(so, mulde, 'zm_sw_deckglas', [S['linse']], 0.0015)
    mod = deckglas.modifiers.new('dicke', 'SOLIDIFY'); mod.thickness = 0.004; mod.offset = 1.0
    teile.append(deckglas)
    stoff_setzen(so, mulde, S['sw_innen']); stoff_setzen(so, rand, S['glanz'])
    bm = bmesh.new(); bm.from_mesh(so.data); bm.faces.ensure_lookup_table()
    for v in {v for i in mulde for v in bm.faces[i].verts}:
        v.co.y += 0.045
    for v in {v for i in mitte_ein for v in bm.faces[i].verts}:
        v.co.y += 0.035
    bm.to_mesh(so.data); bm.free(); so.data.update()
    yi = K0 - 0.035 + 0.045                           # Boden des Gehaeuses
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        for j, (xx, rr, zz) in enumerate(((0.80, 0.048, 0.945), (0.955, 0.048, 0.945), (1.075, 0.034, 0.925))):
            tief = 0.034 if rr > 0.04 else 0.026
            lin = B.drehkoerper(f'zm_sw_projektor_{sl}_{j}', [(0.0, 0.0), (rr * 0.82, 0.0), (rr * 0.92, tief * 0.25), (rr * 0.92, tief * 0.8), (0.0, tief)],
                                40, [S['linse']])
            lin.rotation_euler = (math.radians(90), 0, 0); lin.location = (sx * xx, yi - 0.004, zz)
            teile.append(lin)
            ring = B.drehkoerper(f'zm_sw_ring_{sl}_{j}', [(rr * 0.92, 0.0), (rr * 1.35, 0.0), (rr * 1.35, 0.010), (rr * 0.92, 0.014)], 40, [S['reflektor']])
            ring.rotation_euler = (math.radians(90), 0, 0); ring.location = (sx * xx, yi - 0.002, zz)
            teile.append(ring)
            blende = B.drehkoerper(f'zm_sw_blende_{sl}_{j}', [(0.0, 0.0), (rr * 0.8, 0.0), (rr * 0.8, 0.003), (0.0, 0.003)], 32, [S['glanz']])
            blende.rotation_euler = (math.radians(90), 0, 0); blende.location = (sx * xx, yi + 0.002, zz)
            teile.append(blende)
        # Tagfahrlicht als L: oben entlang der schraegen Oberkante, aussen senkrecht
        xa, xb = x_innen(1.045) + 0.03, 1.105
        teile.append(B.rundkasten(f'zm_sw_tfl_waag_{sl}', xb - xa, 0.012, 0.016, 0.006, [S['led']], (sx * (xa + xb) / 2, yi - 0.008, 1.037), n=3))
        teile.append(B.rundkasten(f'zm_sw_tfl_senk_{sl}', 0.016, 0.012, 0.18, 0.006, [S['led']], (sx * 1.112, yi - 0.008, 0.868), n=3))
        xa2 = x_innen(0.86) + 0.03
        teile.append(B.rundkasten(f'zm_sw_blinker_{sl}', 0.70 - xa2 + 0.12, 0.010, 0.020, 0.006, [S['blinker']], (sx * (xa2 + 0.12 + 0.70) / 2 * 0 + sx * (xa2 + (0.82 - xa2) / 2), yi - 0.006, 0.852), n=3))
        # Nebel-/Abbiegelicht unten
        nb = B.drehkoerper(f'zm_nebel_{sl}', [(0.0, 0.0), (0.038, 0.0), (0.042, 0.008), (0.0, 0.012)], 32, [S['linse']])
        nb.rotation_euler = (math.radians(90), 0, 0); nb.location = (sx * 0.95, K0 - 0.050, 0.60); teile.append(nb)
        teile.append(B.rundkasten(f'zm_nebel_rahmen_{sl}', 0.14, 0.012, 0.10, 0.02, [S['glanz']], (sx * 0.95, K0 - 0.050, 0.55), n=3))
    # Lufteinlass im unteren Stossfaenger (Wabengitter, zurueckgesetzt)
    su = teile[1]
    ein = flaechen(su, lambda c, n: n.y < -0.5 and 0.62 < c.z < 0.76 and abs(c.x) < 0.58)
    stoff_setzen(su, ein, S['grill'])
    bm = bmesh.new(); bm.from_mesh(su.data); bm.faces.ensure_lookup_table()
    for v in {v for i in ein for v in bm.faces[i].verts}:
        v.co.y += 0.028
    bm.to_mesh(su.data); bm.free(); su.data.update()
    # Mittelstufe (Riffelblech) zum Scheibenputzen, Kennzeichen
    teile.append(B.rundkasten('zm_frontstufe', 0.62, 0.20, 0.025, 0.008, [S['riffel']], (0, K0 + 0.04, 0.78), n=3))
    yk = K0 - 0.052
    V = [(-0.26, yk, 0.47), (0.26, yk, 0.47), (0.26, yk, 0.58), (-0.26, yk, 0.58)]
    teile.append(B.netz('zm_kennzeichen', V, [(0, 1, 2, 3)], [S['kennzeichen']], None, [[(0, 0), (1, 0), (1, 1), (0, 1)]], False))
    teile.append(B.kasten('zm_kennzeichen_halter', 0.54, 0.006, 0.13, [S['glanz']], 0.002, (0, K0 - 0.047, 0.46)))
    return teile


def einstieg(S):
    """Drei Stufen hinter dem Vorderrad, eingelassen in einen Kasten aus
    genarbtem Kunststoff; Trittflaechen aus Alu-Riffelblech."""
    teile = []
    y0, y1 = Y_VA + 0.60, K1 - 0.02
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        xa = sx * (HX - 0.005)
        teile.append(B.kasten(f'zm_einstieg_rueck_{sl}', 0.02, y1 - y0, 0.95, [S['kunststoff']], 0.004, (sx * (HX - 0.24), (y0 + y1) / 2, 0.36)))
        for zs in (0.36, 1.30):
            teile.append(B.kasten(f'zm_einstieg_deckel_{sl}_{int(zs*100)}', 0.24, y1 - y0, 0.02, [S['kunststoff']], 0.004, (sx * (HX - 0.12), (y0 + y1) / 2, zs - 0.02)))
        for yy in (y0, y1):
            teile.append(B.kasten(f'zm_einstieg_wange_{sl}_{int(yy*10)}', 0.24, 0.025, 0.95, [S['kunststoff']], 0.004, (sx * (HX - 0.12), yy, 0.36)))
        for j, zz in enumerate((0.58, 0.94)):
            teile.append(B.kasten(f'zm_stufe_{sl}_{j}', 0.23, y1 - y0 - 0.04, 0.035, [S['riffel']], 0.004, (sx * (HX - 0.12), (y0 + y1) / 2, zz)))
            teile.append(B.kasten(f'zm_stufe_kante_{sl}_{j}', 0.012, y1 - y0 - 0.04, 0.035, [S['blinker'] if False else S['alu']], 0.003,
                                  (xa - sx * 0.004, (y0 + y1) / 2, zz)))
    return teile


def radlauf(name, x, y, zc, r_in, r_aus, breite, a0, a1, stoffe_, n=40):
    """Radlaufschale als Bogenstueck (Querschnitt: Dach + Kante)."""
    V, F = [], []
    for i in range(n + 1):
        a = math.radians(a0 + (a1 - a0) * i / n)
        for (rr, xx) in ((r_in, -breite / 2), (r_aus, -breite / 2), (r_aus, breite / 2), (r_in, breite / 2)):
            V.append((x + xx, y + rr * math.cos(a), zc + rr * math.sin(a)))
    for i in range(n):
        for j in range(3):
            a = i * 4 + j; b_ = a + 1
            F.append((a, b_, b_ + 4, a + 4))
    uv = [[(0, 0), (1, 0), (1, 1), (0, 1)]] * len(F)
    o = B.netz(name, V, F, stoffe_, None, uv, True)
    return o


# ---------------------------------------------------------------- Fahrgestell Zugmaschine
def fahrgestell(S):
    lack = S['lack'][0]
    R = rad('_tmp', 0, 0, 'zug', 1, S)[1]
    for o in [o for o in bpy.data.objects if o.name.startswith('_tmp')]:
        bpy.data.objects.remove(o, do_unlink=True)
    teile = []
    # Rahmen: zwei C-Traeger 270 x 80 mm, Quertraeger
    for sx in (-1, 1):
        teile.append(B.kasten(f'zm_rahmen_{sx}', 0.08, (Y_HA + 1.05) - (K0 + 0.30), 0.27, [S['rahmen']], 0.004,
                              (sx * 0.43, ((Y_HA + 1.05) + (K0 + 0.30)) / 2, 0.73)))
    for yq in (Y_VA - 0.2, Y_VA + 1.4, Y_HA - 0.9, Y_HA + 0.9):
        teile.append(B.kasten(f'zm_quer_{int((yq - Y_FRONT) * 10)}', 0.80, 0.08, 0.20, [S['rahmen']], 0.004, (0, yq, 0.76)))
    # Seitenverkleidung zwischen Einstieg und Hinterrad (Aerodynamik-Paket),
    # verdeckt Tank, AdBlue und Batteriekasten
    y0 = K1 + 0.02; y1 = Y_HA - 0.66
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        teile.append(rundbox(f'zm_seitenverkleidung_{sl}', (sx * HX - 0.02, y0, 0.38), (sx * HX + 0.02, y1, 1.10),
                             (0.01, 0.04, 0.04), (0.01, 0.10, 0.04), [lack], grob=0.08, fein_n=5))
        teile.append(B.kasten(f'zm_verkleidung_leiste_{sl}', 0.025, y1 - y0 - 0.10, 0.06, [S['kunststoff']], 0.006,
                              (sx * (HX + 0.004), (y0 + y1) / 2, 0.38)))
    # Tank (Alu, D-Form) sichtbar zwischen Vorderrad und Verkleidung? -- liegt dahinter
    teile.append(B.rundkasten('zm_tank', 0.62, 1.60, 0.62, 0.20, [S['alu']], (0.90, (y0 + y1) / 2, 0.40), n=10))
    teile.append(B.rundkasten('zm_batterie', 0.50, 1.10, 0.55, 0.05, [S['rahmen']], (-0.88, (y0 + y1) / 2, 0.42), n=6))
    # Luftansaugung hinter dem Fahrerhaus
    teile.append(B.rundkasten('zm_ansaugung', 0.30, 0.30, 1.90, 0.10, [S['kunststoff']], (-0.98, K1 + 0.20, 1.95), n=6))
    # Sattelkupplung (Platte mit Einlauframpen), gefettet schwarz
    teile.append(B.drehkoerper('zm_sattelplatte', [(0.0, 1.07), (0.44, 1.07), (0.45, 1.09), (0.44, 1.16), (0.0, 1.16)], 64, [S['rahmen']]))
    teile[-1].location = (0, Y_SK, 0)
    # Kotfluegel hinten (Viertelschalen ueber den Zwillingsraedern) und Schmutzfaenger
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        teile.append(radlauf(f'zm_kotfluegel_{sl}', sx * 0.93, Y_HA, R, R + 0.08, R + 0.11, 0.70, 30, 150, [S['kunststoff']]))
        teile.append(B.kasten(f'zm_schmutzfaenger_{sl}', 0.62, 0.012, 0.52, [S['kunststoff']], 0.004, (sx * 0.95, Y_HA + 0.66, 0.22)))
        teile.append(B.rundkasten(f'zm_rueckleuchte_{sl}', 0.34, 0.07, 0.13, 0.02, [S['rueck']], (sx * 0.78, Y_HA + 1.06, 0.78), n=4))
        # Radlauf vorn: Schale ueber dem Vorderrad bis unter die Kabine
        teile.append(radlauf(f'zm_radlauf_vorn_{sl}', sx * 1.06, Y_VA, R, R + 0.07, R + 0.10, 0.40, 10, 170, [S['kunststoff']]))
    # Raeder
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        rad(f'rad_va_{sl}', sx * 1.035, Y_VA, 'zug', sx, S)
        rad(f'rad_ha_{sl}_aussen', sx * 1.10, Y_HA, 'zug', sx, S)
        rad(f'rad_ha_{sl}_innen', sx * 0.76, Y_HA, 'zug', -sx, S, zwilling_innen=True)
    for yy in (Y_VA, Y_HA):
        a = B.drehkoerper(f'zm_achse_{int(yy * 10)}', [(0.0, -0.95), (0.06, -0.95), (0.06, 0.95), (0.0, 0.95)], 16, [S['rahmen']])
        a.rotation_euler = (0, math.radians(90), 0); a.location = (0, yy, R)
    return teile


# ---------------------------------------------------------------- Auflieger
def auflieger(S):
    ALT_B = ALT.B
    YT = Y_A0 + 3.00
    alu, rahmen = S['alu'], S['rahmen']
    B.kasten('af_kupplungsplatte', 1.30, YT - Y_A0 - 0.05, 0.09, [rahmen], 0.004, (0, (Y_A0 + YT) / 2, 1.08))
    for sx in (-1, 1):
        B.kasten(f'af_traeger_vorn_{sx}', 0.14, YT - Y_A0 - 0.05, 0.10, [rahmen], 0.006, (sx * 0.55, (Y_A0 + YT) / 2, 1.07))
        B.kasten(f'af_traeger_{sx}', 0.14, Y_A1 - YT - 0.15, 0.45, [rahmen], 0.006, (sx * 0.55, (YT + Y_A1 - 0.15) / 2, 0.72))
        # Aussenrahmen (Alu-Profil) mit Zurrmulden
        B.rundkasten(f'af_aussenrahmen_{sx}', 0.07, Y_A1 - Y_A0, 0.17, 0.012, [alu], (sx * (BREITE / 2 - 0.035), (Y_A0 + Y_A1) / 2, 1.03), n=4)
        for yy in np.arange(Y_A0 + 0.45, Y_A1 - 0.3, 0.52):
            B.kasten(f'af_zurrmulde_{sx}_{int(yy*100)}', 0.012, 0.07, 0.06, [S['fuge']], 0.002, (sx * (BREITE / 2 + 0.001), yy, 1.10))
        # Konturmarkierung gelb (ECE 104), unterbrochen, am Unter- und Obergurt
        for yy in np.arange(Y_A0 + 0.20, Y_A1 - 0.20, 0.55):
            B.kasten(f'af_kontur_u_{sx}_{int(yy*100)}', 0.003, 0.36, 0.05, [S['reflex_gelb']], 0.0005, (sx * (BREITE / 2 + 0.0015), yy, 1.045))
            B.kasten(f'af_kontur_o_{sx}_{int(yy*100)}', 0.003, 0.36, 0.05, [S['reflex_gelb']], 0.0005, (sx * (BREITE / 2 + 0.0165), yy, H_DACH - 0.12))
        # Seitenmarkierungsleuchten (orange)
        for yy in np.linspace(Y_A0 + 1.0, Y_A1 - 1.0, 5):
            B.rundkasten(f'af_seitenleuchte_{sx}_{int(yy*100)}', 0.02, 0.10, 0.04, 0.008, [S['blinker']], (sx * (BREITE / 2 + 0.010), yy, 0.96), n=3)
    B.kasten('af_ladeboden', BREITE - 0.12, Y_A1 - Y_A0 - 0.02, 0.03, [S['holz']], 0.003, (0, (Y_A0 + Y_A1) / 2, H_DECK - 0.03))
    B.kasten('af_stirnwand', BREITE, 0.05, H_DACH - 1.00, [alu], 0.008, (0, Y_A0 + 0.025, 1.00))
    # Obergurt: gerundetes Alu-Profil mit leichtem Ueberstand
    for sx in (-1, 1):
        B.rundkasten(f'af_dachholm_{sx}', 0.08, Y_A1 - Y_A0, 0.13, 0.025, [alu], (sx * (BREITE / 2 - 0.025), (Y_A0 + Y_A1) / 2, H_DACH - 0.14), n=6)
    B.kasten('af_dach', BREITE - 0.02, Y_A1 - Y_A0 - 0.04, 0.02, [S['dachplane'][0]], 0.004, (0, (Y_A0 + Y_A1) / 2, H_DACH - 0.03))
    B.kasten('af_heck_oben', BREITE, 0.10, 0.20, [alu], 0.006, (0, Y_A1 - 0.05, H_DACH - 0.20))
    for sx in (-1, 1):
        B.kasten(f'af_heckpfosten_{sx}', 0.10, 0.10, H_DACH - H_DECK, [alu], 0.006, (sx * (BREITE / 2 - 0.05), Y_A1 - 0.05, H_DECK))
        B.kasten(f'af_hecktuer_{sx}', BREITE / 2 - 0.11, 0.04, H_DACH - H_DECK - 0.22, [alu], 0.004,
                 (sx * (BREITE / 4 - 0.005), Y_A1 + 0.01, H_DECK + 0.02))
        for k in (-1, 1):
            B.drehkoerper(f'af_verschluss_{sx}_{k}', [(0.0, H_DECK + 0.04), (0.018, H_DECK + 0.04), (0.018, H_DACH - 0.24), (0.0, H_DACH - 0.24)], 16,
                          [S['chrom']]).location = (sx * (BREITE / 4 + k * 0.22 - 0.005), Y_A1 + 0.05, 0)
        B.kasten(f'af_kontur_heck_{sx}', 0.36, 0.003, 0.05, [S['reflex_rot']], 0.0005, (sx * 0.95, Y_A1 + 0.032, 1.02))
    rungen_y = [Y_A0 + 3.40, Y_A0 + 6.80, Y_A0 + 10.20]
    for sx in (-1, 1):
        for k, yy in enumerate(rungen_y):
            B.kasten(f'af_runge_{sx}_{k}', 0.08, 0.10, H_DACH - 0.14 - H_DECK, [alu], 0.006, (sx * (BREITE / 2 - 0.06), yy, H_DECK))
    # Planen (Namen wie in pr_lkw.py: das Web schiebt sie beim Oeffnen)
    gurte = list(np.arange(Y_A0 + 0.30, Y_A1 - 0.1, 0.62))
    N = 8
    grenzen_ = np.linspace(Y_A0 + 0.06, Y_A1 - 0.10, N + 1)
    for sx in (-1, 1):
        sl = 'l' if sx < 0 else 'r'
        for i in range(N):
            ya, yb = grenzen_[i], grenzen_[i + 1]
            pf = ALT.plane_gitter(f'plane_{sl}_{i}', sx * (BREITE / 2 + 0.004 + 0.004 * i), ya, yb + 0.03, 1.02, H_DACH - 0.14, S['plane'][0], sx,
                                  [g for g in gurte if ya - 0.01 <= g <= yb + 0.04],
                                  (ya - Y_A0) / (Y_A1 - Y_A0), (yb + 0.03 - Y_A0) / (Y_A1 - Y_A0), nz=16, dy=0.08)
            for g in [g for g in gurte if ya <= g < yb]:
                B.kasten(f'gurt_{sl}_{i}_{int(g * 100)}', 0.004, 0.045, H_DACH - 0.14 - 1.00, [S['gurt']], 0.001,
                         (sx * (BREITE / 2 + 0.024 + 0.004 * i), g, 1.00)).parent = pf
                B.rundkasten(f'gurtschloss_{sl}_{i}_{int(g * 100)}', 0.018, 0.055, 0.11, 0.005, [S['alu']],
                             (sx * (BREITE / 2 + 0.03 + 0.004 * i), g, 1.03), n=3).parent = pf
    # Stuetzwinde mit Kurbel, Unterfahrschutz, Kotfluegel, Heck
    for sx in (-1, 1):
        B.kasten(f'af_stuetze_{sx}', 0.12, 0.12, 0.66, [rahmen], 0.006, (sx * 0.62, Y_A0 + 3.70, 0.30))
        B.kasten(f'af_stuetzfuss_{sx}', 0.26, 0.26, 0.04, [rahmen], 0.006, (sx * 0.62, Y_A0 + 3.70, 0.26))
        for zz in (0.46, 0.66):
            B.rundkasten(f'af_seitenschutz_{sx}_{int(zz * 100)}', 0.035, Y_ACHSEN[0] - 0.75 - (Y_A0 + 4.20), 0.10, 0.012, [alu],
                         (sx * (BREITE / 2 - 0.04), (Y_ACHSEN[0] - 0.75 + Y_A0 + 4.20) / 2, zz), n=4)
        B.rundkasten(f'af_rueckleuchte_{sx}', 0.46, 0.07, 0.16, 0.02, [S['rueck']], (sx * 0.95, Y_A1 + 0.02, 0.60), n=4)
    B.rohr('af_kurbel', [(0.66, Y_A0 + 3.70, 0.80), (0.95, Y_A0 + 3.70, 0.80), (0.95, Y_A0 + 3.70, 0.62)], 0.012, [rahmen])
    B.kasten('af_unterfahrschutz', 2.30, 0.12, 0.12, [alu], 0.006, (0, Y_A1 - 0.10, 0.44))
    B.kasten('af_heckleiste', BREITE, 0.10, 0.18, [rahmen], 0.006, (0, Y_A1 - 0.05, 1.02))
    R = None
    for k, yy in enumerate(Y_ACHSEN):
        for sx in (-1, 1):
            _e, R = rad(f'rad_af{k}_{"l" if sx < 0 else "r"}', sx * 1.04, yy, 'auflieger', sx, S)
        a = B.drehkoerper(f'af_achse_{k}', [(0.0, -0.95), (0.065, -0.95), (0.065, 0.95), (0.0, 0.95)], 16, [rahmen])
        a.rotation_euler = (0, math.radians(90), 0); a.location = (0, yy, R)
    for sx in (-1, 1):
        B.rundkasten(f'af_kotfluegel_{sx}', 0.56, Y_ACHSEN[2] - Y_ACHSEN[0] + 1.30, 0.05, 0.02, [S['kunststoff']],
                     (sx * 1.04, (Y_ACHSEN[0] + Y_ACHSEN[2]) / 2, R * 2 + 0.10), n=4)
        B.kasten(f'af_schmutzfaenger_{sx}', 0.56, 0.012, 0.50, [S['kunststoff']], 0.004, (sx * 1.04, Y_ACHSEN[2] + 0.68, 0.22))


def ladung(S):
    """Europaletten, foliert (wie pr_lkw.py; Namen fuer das Web)."""
    def palette_netz():
        t = []
        for ix in (-0.55, 0.0, 0.55):
            for iy in (-0.35, 0.0, 0.35):
                t.append(B.kasten('_pk', 0.145, 0.10, 0.078, [S['palette']], 0.003, (ix, iy, 0.022)))
        for iy in (-0.35, 0.0, 0.35):
            t.append(B.kasten('_pl', 1.20, 0.10, 0.022, [S['palette']], 0.002, (0, iy, 0.0)))
        for ix in (-0.55, 0.0, 0.55):
            t.append(B.kasten('_pq', 0.145, 0.80, 0.022, [S['palette']], 0.002, (ix, 0, 0.100)))
        for k in range(5):
            t.append(B.kasten('_pd', 1.20, 0.10 if k % 2 == 0 else 0.145, 0.022, [S['palette']], 0.002, (0, -0.35 + k * 0.175, 0.122)))
        with bpy.context.temp_override(active_object=t[0], selected_objects=t, selected_editable_objects=t, object=t[0]):
            bpy.ops.object.join()
        p = t[0]; p.name = 'palette_vorlage'; p.data.name = 'palette_netz'
        p.data.transform(p.matrix_world); p.location = (0, 0, 0)
        return p
    pv = palette_netz()
    la = B.rundkasten('ladung_vorlage', 1.18, 0.78, 1.30, 0.03, [S['karton']], (0, 0, 0.144), n=4)
    fi = B.rundkasten('folie_vorlage', 1.21, 0.81, 1.36, 0.05, [S['folie']], (0, 0, 0.12), n=6)
    k = 0
    for r in range(8):
        for c in (-1, 0, 1):
            if r == 7 and c == 1:
                continue
            x = c * 0.81; y = Y_A0 + 0.70 + r * 1.23
            for vorlage, nm in ((pv, 'palette'), (la, 'ladung'), (fi, 'folie')):
                o = bpy.data.objects.new(f'{nm}_{k}', vorlage.data); bpy.context.scene.collection.objects.link(o)
                o.location = (x + vorlage.location.x, y + vorlage.location.y, H_DECK + vorlage.location.z)
                o.rotation_euler = (0, 0, math.radians(90))
            k += 1
    for v in (pv, la, fi):
        bpy.data.objects.remove(v, do_unlink=True)


# ---------------------------------------------------------------- Aufbau
def bauen(web=False):
    VAR, S = stoffe()
    kab, _k = fahrerhaus(S)
    B.angel('kab_angel', (0, K0 + 0.10, 1.00), (-1, 0, 0), 48.0, kab)       # kippt nach vorn
    stossfaenger(S)
    einstieg(S)
    fahrgestell(S)
    auflieger(S)
    ladung(S)
    zuordnung = {S['lack'][0]: S['lack'], S['plane'][0]: S['plane'], S['dachplane'][0]: S['dachplane']}
    B.exportieren('lkw', web, VAR, zuordnung, 'Vecom Design, eigener Entwurf; moderne EU-Fernverkehrs-Zugmaschine (Gattung), keine Marke')
    if not web:
        B.speichern('lkw')
