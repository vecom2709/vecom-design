"""fz_innen.py -- Innenraum der beiden Serienautos (nur numpy).

Ersetzt den groben Innenraum aus fz_anbau.innenraum (Superellipsoide, "durch
getoentes Glas liest man Silhouetten"). Seit der Innenraum waehlbar ist und
die Kamera durch die Fahrertuer hineinfaehrt, muss er aus der Naehe tragen:
Naehte, Keder, Polsterkanaele, Schalter, Displays.

BAUWEISE
Polster, Armaturentafel, Konsole und Verkleidungen sind LOFTS: geschlossene
2D-Profile (Querschnitte) werden entlang einer Achse aufgereiht und zu einem
Gitter verbunden. Das hat drei Vorteile gegenueber Grundkoerpern:
  1. Gleichmaessige Punktdichte (5 mm) auch auf flachen Seiten -- ein
     Superellipsoid haeuft die Punkte an den Kanten und laesst Flaechen leer.
  2. Naehte und Kanaele entstehen im Profil selbst (Rille = Delle im
     Querschnitt), die Normalen kommen aus dem Gitter -- eine echte Rille
     statt einer aufgemalten.
  3. UV = Bogenlaenge x Weglaenge in Metern: Stoff und Leder liegen ohne
     Verzerrung und in der richtigen Richtung.
Steppnaehte sind eigene Kleinstteile (Stiche 5 mm lang, 0,8 mm breit, 0,4 mm
erhaben), so wie echtes Garn auf dem Bezug liegt.

MASSE (Linkslenker, Fahrer bei +x). Quellen: Innenraummasse SAE J1100 der
Klassen (H-Punkt-Hoehe ueber Boden, Kniefreiheit, Lenkradmasse 370-380 mm),
Sitzmasse aus Herstellerzeichnungen gerundet. Koordinaten wie
fz_karosserie: x quer, d laengs ab Vorderkante, z hoch.

MATERIALSCHLUESSEL (fahrzeug_bau.py legt die Stoffe und die drei
Ausstattungen an): sitz_haupt, sitz_einlage, naht, keder, innen_oben,
innen_unten, innen_hart, dekor, klavierlack, chrom_innen, teppich, matte,
himmel, saeule, lenkrad, display, display_glas, gurt, lautsprecher
"""
import numpy as np
from fz_geom import catmull, glatt, normalen_gitter
from fz_anbau import gitter_netz, zusammen, superellipsoid, torus, drehen


# Netzdichte: 1,0 fuer Standbilder, 0,5 fuer die Echtzeitfassung (doppelter
# Punktabstand, keine einzelnen Stiche -- die Naht bleibt als Rille).
SKALA = 1.0


def skala(s):
    global SKALA
    SKALA = s


# ============================================================ Grundbausteine
class Teil:
    """Ein Netz mit Material je Dreieck und UV je Punkt (Meter)."""

    def __init__(self, V, N, F, stoffe, fidx=None, uv=None):
        self.V = np.asarray(V, float); self.N = np.asarray(N, float); self.F = np.asarray(F, int)
        self.stoffe = list(stoffe)
        self.fidx = np.zeros(len(self.F), int) if fidx is None else np.asarray(fidx, int)
        self.uv = uv if uv is not None else kasten_uv(self.V, self.N)

    def dazu(self, *andere):
        V = [self.V]; N = [self.N]; F = [self.F]; fi = [self.fidx]; uv = [self.uv]
        stoffe = list(self.stoffe); o = len(self.V)
        for t in andere:
            abb = []
            for s in t.stoffe:
                if s not in stoffe:
                    stoffe.append(s)
                abb.append(stoffe.index(s))
            V.append(t.V); N.append(t.N); F.append(t.F + o); fi.append(np.array(abb)[t.fidx]); uv.append(t.uv)
            o += len(t.V)
        return Teil(np.concatenate(V), np.concatenate(N), np.concatenate(F), stoffe, np.concatenate(fi), np.concatenate(uv))

    def bewegt(self, dreh=None, um=(0, 0, 0), versatz=(0, 0, 0), spiegel_x=False):
        V, N, F = self.V.copy(), self.N.copy(), self.F.copy()
        if dreh is not None:
            for achse, w in dreh:
                V, N = drehen(V, N, w, achse, um)
        V = V + np.asarray(versatz, float)
        if spiegel_x:
            V[:, 0] *= -1; N[:, 0] *= -1; F = F[:, ::-1].copy()
        return Teil(V, N, F, self.stoffe, self.fidx, self.uv)


def kasten_uv(V, N):
    """UV per Kastenprojektion (Meter) nach der staerksten Normalenachse."""
    a = np.argmax(np.abs(N), 1)
    uv = np.zeros((len(V), 2))
    uv[a == 0] = V[a == 0][:, [1, 2]]
    uv[a == 1] = V[a == 1][:, [0, 2]]
    uv[a == 2] = V[a == 2][:, [0, 1]]
    return uv


def netz(V, N, F, stoff, uv=None):
    return Teil(V, N, F, [stoff], None, uv)


def gleichmaessig(poly, n, geschlossen=True):
    """Polygon nach Bogenlaenge auf n Punkte verteilen (Start = erster Punkt)."""
    p = np.vstack([poly, poly[:1]]) if geschlossen else poly
    s = np.r_[0, np.cumsum(np.linalg.norm(np.diff(p, axis=0), axis=1))]
    z = np.linspace(0, s[-1], n + (0 if not geschlossen else 1))[: n]
    return np.stack([np.interp(z, s, p[:, k]) for k in range(p.shape[1])], 1)


def verrunden(pts, r, n=8):
    """Polygon mit Eckradien (wie fz_karosserie.verrunden, hier ohne Import-
    Kreis)."""
    pts = [np.asarray(p, float) for p in pts]
    k = len(pts)
    rr = r if isinstance(r, (list, tuple, np.ndarray)) else [r] * k
    out = []
    for i in range(k):
        a, b, c = pts[i - 1], pts[i], pts[(i + 1) % k]
        u = a - b; v = c - b
        lu, lv = np.linalg.norm(u), np.linalg.norm(v)
        if lu < 1e-9 or lv < 1e-9:
            out.append(b); continue
        u = u / lu; v = v / lv
        w = np.arccos(np.clip(u @ v, -1, 1))
        if rr[i] <= 0 or w < 1e-3 or w > np.pi - 1e-3:
            out.append(b); continue
        t = min(rr[i] / np.tan(w / 2), 0.49 * lu, 0.49 * lv)
        r_eff = t * np.tan(w / 2)
        mitte = b + (u + v) / np.linalg.norm(u + v) * (r_eff / np.sin(w / 2))
        p1 = b + u * t; p2 = b + v * t
        a1 = np.arctan2(*(p1 - mitte)[::-1]); a2 = np.arctan2(*(p2 - mitte)[::-1])
        da = (a2 - a1 + np.pi) % (2 * np.pi) - np.pi
        for s in np.linspace(0, 1, n):
            out.append(mitte + r_eff * np.array([np.cos(a1 + da * s), np.sin(a1 + da * s)]))
    return np.array(out)


def ring_normalen(r):
    """Aussennormalen eines geschlossenen 2D-Rings (gegen den Uhrzeigersinn
    oder mit): zeigt vom Schwerpunkt weg."""
    t = np.roll(r, -1, 0) - np.roll(r, 1, 0)
    n = np.c_[t[:, 1], -t[:, 0]]
    n /= np.maximum(np.linalg.norm(n, axis=1, keepdims=True), 1e-12)
    if ((r - r.mean(0)) * n).sum() < 0:
        n = -n
    return n


def delle(r, maske_fn, tiefe, lage_fn, breite):
    """Rille in ein Profil druecken: Punkte nahe lage_fn (Abstand in m,
    Gauss mit `breite`) werden `tiefe` nach innen versetzt, nur wo maske_fn."""
    n = ring_normalen(r)
    g = np.exp(-(lage_fn(r) / breite) ** 2) * maske_fn(r)
    return r - n * (tiefe * g)[:, None]


def loft(ringe, orte, ea, eb, kappe=(0.0, 0.0), n_kappe=8, stoffe=('sitz_haupt',), stoff_fn=None):
    """Ringe (m, n, 2) in den Ebenen (orte[k], ea[k], eb[k]) -> geschlossener
    Koerper. kappe = (Tiefe unten, Tiefe oben): gewoelbte Deckel an beiden
    Enden (0 = offen). stoff_fn(ring2d_punkte, k) -> Materialindex je Punkt."""
    ringe = np.asarray(ringe, float); orte = np.asarray(orte, float)
    ea = np.broadcast_to(np.asarray(ea, float), orte.shape); eb = np.broadcast_to(np.asarray(eb, float), orte.shape)
    m, n = ringe.shape[:2]
    achse = np.gradient(orte, axis=0) if m > 1 else np.zeros_like(orte)
    achse /= np.maximum(np.linalg.norm(achse, axis=1, keepdims=True), 1e-12)
    liste_r = list(ringe); liste_o = list(orte); liste_a = list(ea); liste_b = list(eb)
    for seite, tiefe in ((0, kappe[0]), (1, kappe[1])):
        if tiefe <= 0:
            continue
        k = 0 if seite == 0 else m - 1
        r0 = ringe[k]; c = r0.mean(0)
        richt = -achse[k] if seite == 0 else achse[k]
        neu_r, neu_o = [], []
        for th in np.linspace(0, np.pi / 2, n_kappe + 1)[1:]:
            s = np.cos(th) if th < np.pi / 2 - 1e-6 else 0.02
            neu_r.append(c + (r0 - c) * s)
            neu_o.append(orte[k] + richt * tiefe * np.sin(th))
        if seite == 0:
            liste_r = neu_r[::-1] + liste_r; liste_o = neu_o[::-1] + liste_o
            liste_a = [ea[0]] * len(neu_r) + liste_a; liste_b = [eb[0]] * len(neu_r) + liste_b
        else:
            liste_r += neu_r; liste_o += neu_o
            liste_a += [ea[-1]] * len(neu_r); liste_b += [eb[-1]] * len(neu_r)
    R = np.asarray(liste_r); O = np.asarray(liste_o); A = np.asarray(liste_a); B = np.asarray(liste_b)
    P = O[:, None, :] + R[..., :1] * A[:, None, :] + R[..., 1:2] * B[:, None, :]
    N = normalen_gitter(P, wrap_j=True)
    mitte = P.mean(1, keepdims=True)
    if ((P - mitte) * N).sum() < 0:
        N = -N
    V, Nn, F = gitter_netz(P, N, wrap=True)
    # UV: Bogenlaenge entlang des Rings, Weg entlang der Achse
    bogen = np.r_[0, np.cumsum(np.linalg.norm(np.diff(np.vstack([R[len(R) // 2], R[len(R) // 2][:1]]), axis=0), axis=1))][:n]
    weg = np.r_[0, np.cumsum(np.linalg.norm(np.diff(O, axis=0), axis=1))]
    uv = np.stack(np.meshgrid(weg, bogen, indexing='ij')[::-1], -1).reshape(-1, 2)
    fidx = None
    if stoff_fn is not None:
        mat = np.concatenate([stoff_fn(R[k], k) for k in range(len(R))])
        fidx = np.max(mat[F], 1)
    return Teil(V, Nn, F, list(stoffe), fidx, uv)


def stiche(P3, N3, laenge=0.005, luecke=0.0035, breite=0.0008, hoch=0.0004, stoff='naht'):
    """Steppnaht entlang einer Punktkette P3 (dicht) mit Flaechennormalen N3:
    einzelne, leicht gewoelbte Stiche."""
    s = np.r_[0, np.cumsum(np.linalg.norm(np.diff(P3, axis=0), axis=1))]
    starts = np.arange(0, s[-1] - laenge, laenge + luecke)
    V, N, F = [], [], []
    for s0 in starts:
        a = np.array([np.interp(s0, s, P3[:, k]) for k in range(3)])
        b = np.array([np.interp(s0 + laenge, s, P3[:, k]) for k in range(3)])
        na = np.array([np.interp(s0 + laenge / 2, s, N3[:, k]) for k in range(3)]); na /= np.linalg.norm(na)
        t = b - a; t /= max(np.linalg.norm(t), 1e-9)
        q = np.cross(na, t); q /= max(np.linalg.norm(q), 1e-9)
        o = len(V)
        m = 0.5 * (a + b) + na * hoch
        V += [a + na * hoch * 0.2, m - q * breite / 2, b + na * hoch * 0.2, m + q * breite / 2, m + na * hoch * 0.6]
        N += [na, na * 0.6 - q * 0.8, na, na * 0.6 + q * 0.8, na]
        F += [(o, o + 1, o + 4), (o + 1, o + 2, o + 4), (o + 2, o + 3, o + 4), (o + 3, o, o + 4)]
    if not V:
        return None
    V = np.array(V); N = np.array(N); N /= np.linalg.norm(N, axis=1, keepdims=True)
    return Teil(V, N, np.array(F), [stoff])


def punkt_auf_ring(r3, n3, r2, ziel_u, seite_w=None):
    """Punkt auf einem 3D-Ring, dessen 2D-Koordinate u = ziel_u (vorn)."""
    vorn = r2[:, 1] > (np.median(r2[:, 1]) if seite_w is None else seite_w)
    idx = np.where(vorn)[0]
    u = r2[idx, 0]
    o = np.argsort(u)
    k = np.interp(ziel_u, u[o], idx[o].astype(float))
    i0 = int(np.floor(k)); i1 = min(i0 + 1, len(r3) - 1); f = k - i0
    return r3[i0] * (1 - f) + r3[i1] * f, n3[i0] * (1 - f) + n3[i1] * f


# ================================================================= Masse
INNEN = {
    'kleinwagen': dict(
        fahrer_x=0.370, h_punkt=(2.455, 0.515), lehne_neig=23.0, kissen_neig=13.0,
        hinten_h=(3.225, 0.545), hinten_neig=26.0, bank_breite=1.27,
        stirn=1.28, boden_z=0.255, lenkrad=dict(d=2.080, z=0.855, neig=64.0, r=0.186),
        breite_innen=0.705,        # halbe Innenbreite auf Schulterhoehe
    ),
    'mittelklasse': dict(
        fahrer_x=0.380, h_punkt=(2.795, 0.495), lehne_neig=24.0, kissen_neig=12.0,
        hinten_h=(3.610, 0.530), hinten_neig=27.0, bank_breite=1.34,
        stirn=1.62, boden_z=0.240, lenkrad=dict(d=2.420, z=0.835, neig=65.0, r=0.187),
        breite_innen=0.740,
    ),
}


def _rahmen_lehne(x, d, z, neig_grad):
    """Achse der Lehne (nach oben, nach hinten geneigt) und die zwei
    Profilachsen: ea quer, eb nach vorn (zum Insassen)."""
    w = np.radians(neig_grad)
    achse = np.array([0.0, np.sin(w), np.cos(w)])
    ea = np.array([1.0, 0, 0])
    eb = np.array([0.0, -np.cos(w), np.sin(w)])
    return np.array([x, d, z], float), achse, ea, eb


# ================================================================= Sitze
def kopf_profil(W, T):
    """Kopfstuetze: vorn gewoelbt, hinten flacher, alle Kanten weich."""
    pts = [(0, -T * 0.55), (W - 0.03, -T * 0.5), (W, -T * 0.2), (W - 0.01, T * 0.3), (W - 0.04, T * 0.5), (0, T * 0.55),
           (-W + 0.04, T * 0.5), (-W + 0.01, T * 0.3), (-W, -T * 0.2), (-W + 0.03, -T * 0.5)]
    return verrunden(pts, [0, 0.03, 0.03, 0.03, 0.04, 0, 0.04, 0.03, 0.03, 0.03], n=12)


def lehne_profil(W, T, B, einlage=0.155, krone=0.006):
    """Querschnitt einer Rueckenlehne (u quer, w nach vorn): Seitenwangen
    kommen B vor die Einlage, Einlage mit leichter Woelbung, Rueckwand flach."""
    e = einlage
    pts = [(0, -T), (W - 0.02, -T), (W, -T + 0.03), (W, B - 0.03), (W - 0.028, B), (e + 0.05, B * 0.82),
           (e + 0.012, 0.012), (e, 0.0), (0.0, krone), (-e, 0.0), (-e - 0.012, 0.012), (-e - 0.05, B * 0.82),
           (-W + 0.028, B), (-W, B - 0.03), (-W, -T + 0.03), (-W + 0.02, -T)]
    r = [0, 0.02, 0.025, 0.02, 0.03, 0.035, 0.012, 0.004, 0, 0.004, 0.012, 0.035, 0.03, 0.02, 0.025, 0.02]
    return verrunden(pts, r, n=10)


def polster(profil_fn, laenge, n_ring, schritt=0.005, neig=None, basis=None, kanaele=(), kanal_tiefe=0.005,
            einlage=0.155, naht_tiefe=0.0035, kappe=(0.02, 0.04), naehte=True, naht_u=None, einlage_fn=None):
    """Ein Polsterteil (Lehne, Kissen, Kopfstuetze): Ringe profil_fn(v) im
    Abstand `schritt` entlang der Achse. kanaele: Hoehen der Quernaehte.
    naht_u: Querlagen der Laengsnaehte (Standard +-einlage), einlage_fn(u):
    wo die Einlage liegt (Standard |u| < einlage).
    Rueckgabe: Teil (Material 0 = Bezug, 1 = Einlage, 2 = Naht)."""
    o, achse, ea, eb = basis
    schritt = schritt / SKALA; n_ring = max(24, int(n_ring * SKALA))
    naehte = naehte and SKALA >= 1.0
    naht_u = [einlage, -einlage] if naht_u is None else list(naht_u)
    ein_fn = (lambda u: np.abs(u) < einlage) if einlage_fn is None else einlage_fn
    vs = np.arange(0.0, laenge + 1e-9, schritt)
    ringe, orte = [], []
    for v in vs:
        r = gleichmaessig(profil_fn(v), n_ring)
        vorn = lambda q: (q[:, 1] > -0.012).astype(float)
        # Laengsnaehte: Rille 3,5 mm tief an jeder Einlagenkante
        for nu in naht_u:
            r = delle(r, vorn, naht_tiefe, lambda q, nu=nu: q[:, 0] - nu, 0.0028)
        # Quernaehte: ganze Einlage 5 mm eingezogen, Gauss 4 mm
        g = sum(np.exp(-((v - k) / 0.004) ** 2) for k in kanaele) if kanaele else 0.0
        if np.any(g > 1e-4):
            ein = ein_fn(r[:, 0]) & (r[:, 1] > -0.012)
            r = r.copy(); r[ein, 1] -= kanal_tiefe * g
        ringe.append(r); orte.append(o + achse * v)
    ringe = np.array(ringe)

    def stoff_fn(q, k):
        return (ein_fn(q[:, 0]) & (q[:, 1] > -0.012)).astype(int)
    t = loft(ringe, orte, ea, eb, kappe=kappe, stoffe=('sitz_haupt', 'sitz_einlage'), stoff_fn=stoff_fn)
    if not naehte:
        return t
    # Steppnaehte: Doppelnaht beidseits jeder Laengsrille, Einzelnaht ueber
    # jedem Querkanal
    teile = [t]
    for nu in naht_u:
        for off in (0.0045, -0.0045):
            P3, N3 = [], []
            for k in range(2, len(vs) - 6):
                r2 = ringe[k]; n2 = ring_normalen(r2)
                r3 = orte[k] + r2[:, :1] * ea + r2[:, 1:2] * eb
                n3 = n2[:, :1] * ea + n2[:, 1:2] * eb
                p, nn = punkt_auf_ring(r3, n3, r2, nu + off, seite_w=-0.012)
                P3.append(p); N3.append(nn)
            st = stiche(np.array(P3), np.array(N3))
            if st is not None:
                teile.append(st)
    for kv in kanaele:
        k = int(round(kv / schritt)) + 1
        if k >= len(vs):
            continue
        r2 = ringe[k]; n2 = ring_normalen(r2)
        r3 = orte[k] + r2[:, :1] * ea + r2[:, 1:2] * eb; n3 = n2[:, :1] * ea + n2[:, 1:2] * eb
        vorn = ein_fn(r2[:, 0]) & (r2[:, 1] > -0.012)
        # zusammenhaengende Stuecke einzeln (die Bank hat drei Einlagen)
        idx = np.where(vorn)[0]; idx = idx[np.argsort(r2[idx, 0])]
        if len(idx) < 5:
            continue
        schnitt = np.where(np.diff(r2[idx, 0]) > 0.02)[0] + 1
        for stueck in np.split(idx, schnitt):
            stueck = stueck[2:-2]
            if len(stueck) > 4:
                st = stiche(r3[stueck], n3[stueck])
                if st is not None:
                    teile.append(st)
    out = teile[0]
    for x in teile[1:]:
        out = out.dazu(x)
    return out


def vordersitz(M, x):
    """Fahrer- oder Beifahrersitz bei Querlage x. Rueckgabe: Teil."""
    dH, zH = M['h_punkt']
    # --- Lehne: 0,62 m, Wangen im Lendenbereich am weitesten vorn
    def W(v): return 0.255 - 0.030 * glatt((v - 0.30) / 0.30)
    def B(v): return 0.030 + 0.028 * np.exp(-((v - 0.24) / 0.16) ** 2)
    def T(v): return 0.095 - 0.025 * glatt(v / 0.6)
    basis_l = _rahmen_lehne(x, dH + 0.03, zH - 0.02, M['lehne_neig'])
    lehne = polster(lambda v: lehne_profil(W(v), T(v), B(v)), 0.62, 220, basis=basis_l,
                    kanaele=(0.14, 0.26, 0.38, 0.50), kappe=(0.02, 0.045))
    # --- Kissen: Achse nach vorn und leicht ansteigend
    w = np.radians(M['kissen_neig'])
    achse = np.array([0.0, -np.cos(w), np.sin(w)])
    ea = np.array([1.0, 0, 0]); eb = np.array([0.0, np.sin(w), np.cos(w)])
    o = np.array([x, dH + 0.03, zH - 0.035])
    def Wk(s): return 0.250 - 0.012 * glatt((s - 0.30) / 0.17)
    # Wangen am Kissen 4,5 cm hoch (Oberschenkelfuehrung), vorn auslaufend
    def Bk(s): return 0.010 + 0.036 * np.exp(-((s - 0.18) / 0.13) ** 2)
    # Schenkelrolle: das Kissen steigt vorn um 1,6 cm an (Profil nach oben)
    def Ok(s): return 0.016 * np.exp(-((s - 0.40) / 0.08) ** 2)
    def prof_k(s):
        q = lehne_profil(Wk(s), 0.11, Bk(s), krone=0.005)
        q[:, 1] += Ok(s) * (q[:, 1] > -0.05)
        return q
    kissen = polster(prof_k, 0.47, 220, basis=(o, achse, ea, eb), kanaele=(0.14, 0.26, 0.38), kappe=(0.01, 0.06))
    # --- Kopfstuetze auf Chromstangen
    oben = basis_l[0] + basis_l[1] * 0.62
    ko = oben + basis_l[1] * 0.075 + basis_l[3] * 0.012
    def Wh(v): return 0.130 - 0.012 * glatt((v - 0.10) / 0.12)
    kopf = polster(lambda v: kopf_profil(Wh(v), 0.062 - 0.012 * glatt(v / 0.16)), 0.17, 180,
                   basis=(ko, basis_l[1], basis_l[2], basis_l[3]), kanaele=(), kappe=(0.035, 0.045), naehte=False)
    stangen = []
    for sx in (-0.075, 0.075):
        a = oben - basis_l[1] * 0.06 + np.array([sx, 0, 0]) - basis_l[3] * 0.02
        b = ko + basis_l[1] * 0.04 + np.array([sx, 0, 0]) - basis_l[3] * 0.02
        stangen.append(zylinder_voll(a, b, 0.006))
    chrom = Teil(*zusammen(*stangen), ['chrom_innen'])
    # --- Seitenblende (aussen) und Schienen
    s = 1 if x > 0 else -1
    blende = Teil(*superellipsoid((x + s * 0.262, dH - 0.17, zH - 0.12), (0.012, 0.25, 0.085), None, e1=0.25, e2=0.2, nu=24, nv=64),
                  ['innen_hart'])
    hebel = Teil(*superellipsoid((x + s * 0.278, dH - 0.30, zH - 0.105), (0.010, 0.07, 0.014), None, e1=0.4, e2=0.35, nu=12, nv=32),
                 ['innen_hart'])
    unterbau = Teil(*superellipsoid((x, dH - 0.18, (zH - 0.16 + M['boden_z']) / 2 + 0.02), (0.20, 0.22, (zH - 0.16 - M['boden_z']) / 2),
                                    None, e1=0.3, e2=0.2, nu=16, nv=48), ['innen_hart'])
    schienen = [zusammen(superellipsoid((x + sx, dH - 0.20, M['boden_z'] + 0.018), (0.018, 0.25, 0.018), None, e1=0.2, e2=0.2, nu=10, nv=32))
                for sx in (-0.19, 0.19)]
    schiene = Teil(*zusammen(*schienen), ['stahl_innen'])
    return lehne.dazu(kissen, kopf, chrom, blende, hebel, unterbau, schiene)


def zylinder_voll(a, b, r, m=16):
    from fz_details import zylinder
    a = np.asarray(a, float); b = np.asarray(b, float)
    ach = b - a; L = np.linalg.norm(ach); ach /= L
    V, N, F = zylinder(a, ach, r, L, m)
    k1 = superellipsoid(a, (r, r, r), None, e1=1.0, e2=1.0, nu=8, nv=m)
    k2 = superellipsoid(b, (r, r, r), None, e1=1.0, e2=1.0, nu=8, nv=m)
    return zusammen((V, N, F), k1, k2)


def bank_profil(Wg, T, B, xa, e=0.125):
    """Querschnitt der Ruecksitzbank ueber die ganze Breite: zwei Aussen-
    plaetze (Mitte bei +-xa, Einlage +-e) mit Wangen, Mittelplatz flacher."""
    pts = [(0, -T), (Wg - 0.03, -T), (Wg, -T + 0.03), (Wg, B * 0.6), (Wg - 0.03, B), (xa + e + 0.045, B * 0.85), (xa + e + 0.012, 0.010),
           (xa + e, 0.0), (xa - e, 0.0), (xa - e - 0.05, 0.008), (0.0, 0.004),
           (-xa + e + 0.05, 0.008), (-xa + e, 0.0), (-xa - e, 0.0), (-xa - e - 0.012, 0.010), (-xa - e - 0.045, B * 0.85), (-Wg + 0.03, B),
           (-Wg, B * 0.6), (-Wg, -T + 0.03), (-Wg + 0.03, -T)]
    r = [0, 0.03, 0.03, 0.02, 0.03, 0.03, 0.01, 0.004, 0.004, 0.04, 0, 0.04, 0.004, 0.004, 0.01, 0.03, 0.03, 0.02, 0.03, 0.03]
    return verrunden(pts, r, n=10)


def ruecksitzbank(M):
    dH, zH = M['hinten_h']
    Wg = M['bank_breite'] / 2
    xa = Wg - 0.25
    e = 0.125
    naht_u = [xa + e, xa - e, -xa + e, -xa - e]
    ein_fn = lambda u: (np.abs(np.abs(u) - xa) < e) | (np.abs(u) < 0.11)
    def prof_l(v):
        return bank_profil(Wg - 0.02 * glatt((v - 0.4) / 0.2), 0.10 - 0.02 * glatt(v / 0.55),
                           0.020 + 0.012 * np.exp(-((v - 0.22) / 0.15) ** 2), xa, e)
    basis_l = _rahmen_lehne(0.0, dH + 0.02, zH - 0.02, M['hinten_neig'])
    lehne = polster(prof_l, 0.56, 440, basis=basis_l, naht_u=naht_u, einlage_fn=ein_fn,
                    kanaele=(0.16, 0.30, 0.44), kappe=(0.02, 0.05))
    w = np.radians(M['kissen_neig'] + 3)
    achse = np.array([0.0, -np.cos(w), np.sin(w)]); ea = np.array([1.0, 0, 0]); eb = np.array([0.0, np.sin(w), np.cos(w)])
    kissen = polster(lambda s: bank_profil(Wg, 0.12, 0.016, xa, e), 0.46, 440, basis=(np.array([0.0, dH + 0.02, zH - 0.04]), achse, ea, eb),
                     naht_u=naht_u, einlage_fn=ein_fn, kanaele=(0.16, 0.30), kappe=(0.01, 0.05))
    teile = [lehne, kissen]
    oben = basis_l[0] + basis_l[1] * 0.56
    for sx in (-xa, 0.0, xa):
        ko = oben + basis_l[1] * 0.06 + np.array([sx, 0, 0])
        kopf = polster(lambda v: kopf_profil(0.115, 0.058 - 0.010 * glatt(v / 0.13)), 0.14, 160,
                       basis=(ko, basis_l[1], basis_l[2], basis_l[3]), kanaele=(), kappe=(0.03, 0.04), naehte=False)
        teile.append(kopf)
        st = [zylinder_voll(oben - basis_l[1] * 0.05 + np.array([sx + q, 0, 0]), ko + basis_l[1] * 0.03 + np.array([q, 0, 0]), 0.0055)
              for q in (-0.065, 0.065)]
        teile.append(Teil(*zusammen(*st), ['chrom_innen']))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


# ================================================================= Hilfen fuer Flaechen
def flaeche_hoehe(xs, ds, z_fn, stoff, uv_skala=1.0, unten=False):
    """Hoehenfeld z = z_fn(x, d) als Netz (Boden, Teppich). Normale nach oben
    (unten=True: nach unten)."""
    X, D = np.meshgrid(xs, ds, indexing='ij')
    Z = z_fn(X, D)
    P = np.stack([X, D, Z], -1)
    N = normalen_gitter(P)
    if (N[..., 2].mean() < 0) != unten:
        N = -N
    V, Nn, F = gitter_netz(P, N)
    uv = np.c_[P[..., 0].ravel(), P[..., 1].ravel()] * uv_skala
    return Teil(V, Nn, F, [stoff], None, uv)


def rahmen_platte(mitte, u, v, n, breite, hoehe, dicke, r=0.006, stoff='innen_hart', nr=10):
    """Flache, abgerundete Platte (Tasten, Displays, Blenden) in der Ebene
    (u, v) mit Normale n, Kanten rundum gerundet (Loft entlang n)."""
    u = np.asarray(u, float); v = np.asarray(v, float); n = np.asarray(n, float); mitte = np.asarray(mitte, float)
    b2, h2 = breite / 2, hoehe / 2
    ring = gleichmaessig(verrunden([(b2, h2), (-b2, h2), (-b2, -h2), (b2, -h2)], min(r, b2 * 0.99, h2 * 0.99), n=nr), 4 * nr + 16)
    ts = np.linspace(-dicke / 2, dicke / 2, 3)
    orte = [mitte + n * t for t in ts]
    rr = min(dicke / 2, r) * 0.9
    return loft(np.array([ring] * 3), orte, u, v, kappe=(rr, rr), n_kappe=5, stoffe=(stoff,))


def ring3(mitte, u, v, radien, n=48):
    """Hilfsfunktion: Punkte eines Ovals im Raum."""
    w = np.linspace(0, 2 * np.pi, n, endpoint=False)
    return mitte + np.outer(np.cos(w) * radien[0], u) + np.outer(np.sin(w) * radien[1], v)


# ================================================================= Armaturentafel
def armatur_masse(K, M):
    S = M['stirn']
    d_lip = S + {'kleinwagen': 0.575, 'mittelklasse': 0.62}[K.was]
    z_lip = {'kleinwagen': 0.955, 'mittelklasse': 0.945}[K.was]
    return S, d_lip, z_lip


def armatur(K, M):
    """Armaturentafel als Loft quer (x): Oberteil weich genarbt, Gesicht mit
    Knieteil, Mittelkonsole angesetzt, Enden laufen zu den Tueren."""
    S, d_lip, z_lip = armatur_masse(K, M)
    W = M['breite_innen']
    xf = M['fahrer_x']
    xs = np.r_[np.arange(-W + 0.004, W - 0.004, 0.006 / SKALA), W - 0.004]
    def ws(d):                       # Innenseite der Frontscheibe (Mitte)
        return float(K.zt(d)) - 0.018
    ringe, orte = [], []
    d0 = S + 0.04
    for x in xs:
        a = abs(x)
        ende = glatt((a - 0.52) / 0.16)          # Enden laufen nach hinten zur Tuer
        dl = d_lip + 0.08 * ende
        zl = z_lip - 0.012 * ende
        mitte = 1 - glatt((a - 0.10) / 0.08)      # Mittelkonsolen-Ansatz
        z_top0 = min(ws(d0) - 0.01, zl - 0.03)
        pts = [(d0, z_top0), (d0 + 0.18, zl - 0.018), (dl - 0.05, zl), (dl + 0.012, zl - 0.020),
               (dl + 0.006, zl - 0.075), (dl - 0.004, 0.845), (dl - 0.004, 0.800),
               (dl - 0.020 + 0.06 * mitte, 0.72 - 0.08 * mitte), (dl - 0.05 + 0.11 * mitte, 0.60 - 0.14 * mitte),
               (dl - 0.16 + 0.10 * mitte, 0.54 - 0.10 * mitte), (S + 0.28, 0.54), (S + 0.12, 0.66)]
        r = [0.01, 0.10, 0.03, 0.018, 0.02, 0.006, 0.006, 0.06, 0.05, 0.05, 0.05, 0.04]
        q = gleichmaessig(verrunden(pts, r, n=8), int(200 * SKALA))
        ringe.append(q); orte.append((x, 0.0, 0.0))
    ringe = np.array(ringe)

    def stoff_fn(q, k):
        return (q[:, 1] < 0.855).astype(int)
    t = loft(ringe, np.array(orte), np.array([0.0, 1.0, 0.0]), np.array([0.0, 0.0, 1.0]), kappe=(0.012, 0.012),
             stoffe=('innen_oben', 'innen_unten'), stoff_fn=stoff_fn)
    teile = [t]
    # Dekorleiste ueber die ganze Breite, 4 cm hoch, 3 mm vor dem Gesicht
    bx = np.arange(-W + 0.06, W - 0.06, 0.008)
    b_ringe, b_orte = [], []
    for x in bx:
        a = abs(x); ende = glatt((a - 0.52) / 0.16)
        dl = d_lip + 0.08 * ende
        q = gleichmaessig(verrunden([(dl - 0.004, 0.803), (dl + 0.004, 0.803), (dl + 0.004, 0.843), (dl - 0.004, 0.843)], 0.0035, n=6), 40)
        b_ringe.append(q); b_orte.append((x, 0, 0))
    teile.append(loft(np.array(b_ringe), np.array(b_orte), np.array([0.0, 1, 0]), np.array([0.0, 0, 1]), kappe=(0.003, 0.003),
                      stoffe=('dekor',)))
    # Instrumentenhutze: eine Blende UEBER dem Kombiinstrument, hinten offen.
    # Erste Fassung war ein geschlossener Koerper -- das Display lag darin
    # und man sah von hinten nur eine graue Wand.
    if K.was == 'kleinwagen':
        hx = np.arange(xf - 0.165, xf + 0.165, 0.005)
        h_ringe, h_orte = [], []
        for x in hx:
            s = max(1 - ((x - xf) / 0.165) ** 4, 0.0)
            h = 0.140 * s ** 0.5                     # ueber dem 3 cm hoeheren Kombi
            pts = [(d_lip - 0.20, z_lip - 0.002), (d_lip - 0.09, z_lip + h), (d_lip + 0.012, z_lip + h - 0.004),
                   (d_lip + 0.016, z_lip + h - 0.016), (d_lip - 0.02, z_lip + h - 0.011), (d_lip - 0.09, z_lip + h - 0.012),
                   (d_lip - 0.19, z_lip - 0.006)]
            q = gleichmaessig(verrunden(pts, [0.02, 0.07, 0.008, 0.004, 0.02, 0.05, 0.01], n=8), 90)
            h_ringe.append(q); h_orte.append((x, 0, 0))
        teile.append(loft(np.array(h_ringe), np.array(h_orte), np.array([0.0, 1, 0]), np.array([0.0, 0, 1]), kappe=(0.008, 0.008),
                          stoffe=('innen_oben',)))
    # Steppnaht entlang der Vorderkante des weichen Oberteils
    for dz in (-0.006,):
        P3, N3 = [], []
        for x in np.arange(-W + 0.08, W - 0.08, 0.004):
            a = abs(x); ende = glatt((a - 0.52) / 0.16)
            dl = d_lip + 0.08 * ende; zl = z_lip - 0.012 * ende
            P3.append((x, dl + 0.0135, zl - 0.016 + dz)); N3.append((0, 0.8, 0.6))
        st = stiche(np.array(P3), np.array(N3) / 1.0) if SKALA >= 1.0 else None
        if st is not None:
            teile.append(st)
    # Handschuhfach: Fuge als schmale dunkle Rille (Beifahrerseite)
    for (xa, xb, za, zb) in ((-0.62, -0.18, 0.725, 0.725), (-0.62, -0.18, 0.585, 0.585)):
        xs_ = np.arange(xa, xb, 0.004)
        rs, os_ = [], []
        for x in xs_:
            rs.append(gleichmaessig(verrunden([(-0.0015, -0.0015), (0.0015, -0.0015), (0.0015, 0.0015), (-0.0015, 0.0015)], 0.0006, n=3), 12))
            os_.append((x, d_lip - 0.020 + (0.6 - za) * 0.25 + 0.0005, za))
        teile.append(loft(np.array(rs), np.array(os_), np.array([0.0, 1, 0]), np.array([0.0, 0, 1]), stoffe=('klavierlack',)))
    return teile


def duese(mitte, breite, hoehe, n=np.array([0.0, 1.0, 0.08]), lamellen=4):
    """Luftduese: Chromrahmen, dunkler Schacht, waagerechte Lamellen."""
    n = n / np.linalg.norm(n)
    u = np.array([1.0, 0, 0]); v = np.cross(n, u); v /= np.linalg.norm(v)
    if v[2] < 0:
        v = -v
    mitte = np.asarray(mitte, float)
    teile = [rahmen_platte(mitte - n * 0.012, u, v, n, breite, hoehe, 0.028, r=0.012, stoff='innen_hart')]
    # Rahmen: duenner Ring vor dem Schacht
    ring_i = verrunden([(breite / 2 - 0.004, hoehe / 2 - 0.004), (-breite / 2 + 0.004, hoehe / 2 - 0.004), (-breite / 2 + 0.004, -hoehe / 2 + 0.004), (breite / 2 - 0.004, -hoehe / 2 + 0.004)], 0.010, n=8)
    ring_a = verrunden([(breite / 2 + 0.003, hoehe / 2 + 0.003), (-breite / 2 - 0.003, hoehe / 2 + 0.003), (-breite / 2 - 0.003, -hoehe / 2 - 0.003), (breite / 2 + 0.003, -hoehe / 2 - 0.003)], 0.014, n=8)
    ring_i = gleichmaessig(ring_i, 64); ring_a = gleichmaessig(ring_a, 64)
    # Rahmen als flaches Band zwischen innen und aussen, leicht gewoelbt
    P = []
    for t, r in ((0.0, ring_i), (0.35, ring_i * 0.7 + ring_a * 0.3), (0.7, ring_i * 0.3 + ring_a * 0.7), (1.0, ring_a)):
        hub = 0.0025 * np.sin(np.pi * t)
        P.append(mitte + n * (0.003 + hub) + r[:, :1] * u + r[:, 1:2] * v)
    P = np.array(P)
    Nn = normalen_gitter(P, wrap_j=True)
    if (Nn * n).sum(-1).mean() < 0:
        Nn = -Nn
    V, N, F = gitter_netz(P, Nn, wrap=True)
    teile.append(Teil(V, N, F, ['chrom_innen']))
    for k in range(lamellen):
        zz = -hoehe / 2 + hoehe * (k + 0.5) / lamellen
        teile.append(rahmen_platte(mitte - n * 0.004 + v * zz, u, v * 0.3 + n * 0.95, np.cross(u, v * 0.3 + n * 0.95), breite - 0.012, 0.012, 0.002,
                                   r=0.001, stoff='innen_hart', nr=3))
    teile.append(rahmen_platte(mitte + n * 0.001, u, v, n, 0.010, 0.006, 0.006, r=0.002, stoff='chrom_innen', nr=3))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


def duesen(K, M):
    S, d_lip, z_lip = armatur_masse(K, M)
    teile = []
    W = M['breite_innen']
    for sx in (1, -1):
        dl = d_lip + 0.08 * glatt((0.60 - 0.52) / 0.16)
        teile.append(duese((sx * 0.60, dl + 0.010, 0.885), 0.095, 0.050, np.array([0.0, 1.0, 0.15])))
        teile.append(duese((sx * 0.085, d_lip + 0.028 + (0.02 if K.was == 'mittelklasse' else 0), 0.755), 0.110, 0.040, np.array([0.0, 1.0, 0.25])))
    out = teiles = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


# ================================================================= Displays
DISPLAY = {
    # Mitte, Breite, Hoehe, Neigung nach hinten (Grad), Bilddatei
    # z der Mitte so, dass die Unterkante 5 mm ueber der Oberseite liegt --
    # vorher steckte die untere Haelfte in der Armaturentafel.
    # 23.09.2026: Lenkrad aufgerichtet (Achse zum Fahrer nach oben) -- sein
    # oberer Kranz verdeckte danach das Kombi. Sichtlinie vom Augpunkt der
    # Innenkamera gerechnet (/tmp sicht.py): Rad 3 bzw. 4 cm tiefer, Anzeigen
    # 3 cm hoeher -> 93 % bzw. 94 % des Kombis frei (vorher 32 % / 55 %).
    'kleinwagen': dict(kombi=((None, -0.045, 1.035), 0.235, 0.090, 22.0), mitte=((0.0, 0.020, 1.035), 0.232, 0.140, 14.0)),
    'mittelklasse': dict(breit=((0.23, -0.020, 1.042), 0.62, 0.118, 16.0)),
}


def display_teil(mitte, breite, hoehe, neig, bild, gebogen=0.0, dicke=0.010):
    """Display: Gehaeuse (Klavierlack) mit Glas davor, Bildflaeche mit UV 0-1.
    gebogen: Kruemmung um die senkrechte Achse (1/m), fuer das Breitdisplay."""
    w = np.radians(neig)
    n = np.array([0.0, np.cos(w), np.sin(w)])     # nach hinten-oben, zum Fahrer
    u = np.array([1.0, 0, 0]); v = np.cross(n, u); v = v / np.linalg.norm(v)
    if v[2] < 0:
        v = -v
    mitte = np.asarray(mitte, float)
    nu, nv = 80, 24
    us = np.linspace(-breite / 2, breite / 2, nu); vs = np.linspace(-hoehe / 2, hoehe / 2, nv)
    U, Vv = np.meshgrid(us, vs, indexing='ij')
    beuge = gebogen * U ** 2 / 2
    P = mitte + U[..., None] * u + Vv[..., None] * v - beuge[..., None] * n
    Nn = np.broadcast_to(n, P.shape).copy()
    if gebogen:
        Nn = n + (gebogen * U)[..., None] * u
        Nn /= np.linalg.norm(Nn, axis=-1, keepdims=True)
    V, N, F = gitter_netz(P + Nn * 0.0012, Nn)
    # Bild-links liegt bei +x: Der Fahrer schaut nach vorn (-d), seine linke
    # Hand ist +x. Mit u von -x aus stand die Karte in der ersten Probe spiegelverkehrt.
    uv = np.c_[(breite / 2 - U.ravel()) / breite, (Vv.ravel() + hoehe / 2) / hoehe]
    # Das Bild traegt selbst die Glasbeschichtung (Klarlack im Material).
    # Ein eigenes Deckglas davor war undurchsichtig schwarz und verdeckte
    # das Bild ganz (erste Probe).
    bildflaeche = Teil(V, N, F, ['display_' + bild], None, uv)
    # Gehaeuse: Loft entlang u, Rand 6 mm
    g_ringe, g_orte = [], []
    for x in np.linspace(-breite / 2 - 0.006, breite / 2 + 0.006, nu):
        q = gleichmaessig(verrunden([(hoehe / 2 + 0.006, 0.001), (-hoehe / 2 - 0.006, 0.001), (-hoehe / 2 - 0.004, -dicke), (hoehe / 2 + 0.004, -dicke)], 0.003, n=6), 40)
        b = gebogen * x ** 2 / 2
        g_ringe.append(q); g_orte.append(mitte + u * x - n * b)
    geh = loft(np.array(g_ringe), np.array(g_orte), v, n, kappe=(0.004, 0.004), stoffe=('klavierlack',))
    return geh.dazu(bildflaeche)


def displays(K, M):
    S, d_lip, z_lip = armatur_masse(K, M)
    D = DISPLAY[K.was]
    teile = []
    for name, (m, b, h, neig) in D.items():
        x = M['fahrer_x'] if m[0] is None else m[0]
        mitte = (x, d_lip + m[1], m[2])
        teile.append(display_teil(mitte, b, h, neig, name, gebogen=0.9 if name == 'breit' else 0.0))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


# ================================================================= Lenkrad
def lenkrad(K, M):
    """Lenkrad 372 mm, Kranz oval (32 x 38 mm) mit Daumenmulden, drei
    Speichen mit Tastenfeldern, Prallpolster, Lenksaeulenverkleidung und
    zwei Hebeln. Mittelklasse: unten abgeflacht (sportlich)."""
    L = M['lenkrad']
    R = L['r']
    flach = K.was == 'mittelklasse'
    th = np.linspace(0, 2 * np.pi, 241)[:-1]
    ringe, orte, ea_l, eb_l = [], [], [], []
    for t in th:
        c = np.array([R * np.cos(t), R * np.sin(t), 0.0])
        if flach and np.sin(t) < -0.80:
            c[1] = -R * 0.80 - (np.sin(t) + 0.80) * 0.15 * R * 0.0
            c[1] = max(c[1], -R * 0.80)
        er = np.array([np.cos(t), np.sin(t), 0.0])
        # Daumenmulden bei 9 und 3 Uhr etwas darueber: dicker
        daumen = np.exp(-((np.abs(np.cos(t)) - 0.95) / 0.05) ** 2) * (np.sin(t) > -0.1)
        ra, rb = 0.0155 + 0.0025 * daumen, 0.0185 + 0.002 * daumen
        w = np.linspace(0, 2 * np.pi, 40, endpoint=False)
        ringe.append(np.c_[ra * np.cos(w), rb * np.sin(w)])
        orte.append(c); ea_l.append(er); eb_l.append(np.array([0, 0, 1.0]))
    # geschlossener Ring: ersten Ring hinten anhaengen
    ringe.append(ringe[0]); orte.append(orte[0]); ea_l.append(ea_l[0]); eb_l.append(eb_l[0])
    R_ = np.array(ringe); O = np.array(orte); A = np.array(ea_l); B = np.array(eb_l)
    P = O[:, None, :] + R_[..., :1] * A[:, None, :] + R_[..., 1:2] * B[:, None, :]
    N = normalen_gitter(P, wrap_j=True)
    if ((P - O[:, None, :]) * N).sum() < 0:
        N = -N
    V, Nn, F = gitter_netz(P, N, wrap=True)
    kranz = Teil(V, Nn, F, ['lenkrad'])
    teile = [kranz]
    # Speichen: links, rechts (leicht unter der Mitte), unten
    for w_sp, breite, lang in ((np.radians(-8), 0.074, R - 0.06), (np.radians(188), 0.074, R - 0.06), (np.radians(270), 0.080, R - 0.07)):
        e = np.array([np.cos(w_sp), np.sin(w_sp), 0]); q = np.array([-np.sin(w_sp), np.cos(w_sp), 0])
        s_ringe, s_orte = [], []
        for s in np.linspace(0.055, R - 0.004, 30):
            b = breite * (1 - 0.45 * (s - 0.055) / (R - 0.055))
            dk = 0.024 - 0.010 * (s - 0.055) / R
            s_ringe.append(gleichmaessig(verrunden([(b / 2, dk / 2), (-b / 2, dk / 2), (-b / 2, -dk / 2), (b / 2, -dk / 2)], 0.007, n=6), 40))
            s_orte.append(e * s + np.array([0, 0, -0.004]))
        teile.append(loft(np.array(s_ringe), np.array(s_orte), q, np.array([0, 0, 1.0]), kappe=(0.0, 0.006), stoffe=('innen_hart',)))
        if lang and abs(np.sin(w_sp)) < 0.5:
            # Tastenfeld auf der Speiche (Klavierlack) mit vier Tasten
            m = e * 0.090 + np.array([0, 0, 0.0085])
            teile.append(rahmen_platte(m - np.array([0, 0, 0.0012]), e, q, np.array([0, 0, 1.0]), 0.057, 0.043, 0.003, r=0.008, stoff='chrom_innen'))
            teile.append(rahmen_platte(m, e, q, np.array([0, 0, 1.0]), 0.050, 0.036, 0.003, r=0.006, stoff='klavierlack'))
            for a in (-1, 1):
                for b in (-1, 1):
                    teile.append(rahmen_platte(m + e * a * 0.011 + q * b * 0.008 + np.array([0, 0, 0.0025]), e, q, np.array([0, 0, 1.0]),
                                               0.016, 0.011, 0.0015, r=0.003, stoff='innen_hart', nr=4))
    # Prallpolster
    teile.append(Teil(*superellipsoid((0, -0.006, 0.014), (0.090, 0.066, 0.032), (0.090, 0.078, 0.024), e1=0.30, e2=0.45, nu=40, nv=80), ['lenkrad']))
    teile.append(Teil(*superellipsoid((0, 0.004, 0.043), (0.020, 0.012, 0.0015), None, e1=0.5, e2=1.0, nu=10, nv=32), ['chrom_innen']))
    # Lenksaeulenverkleidung und Hebel (lokal -z = Richtung Armatur)
    # Lenksaeulenverkleidung: unter der Nabe, damit der Blick durch den
    # oberen Kranz auf das Kombiinstrument frei bleibt (in der ersten Probe
    # stand sie als Kasten genau davor).
    teile.append(Teil(*superellipsoid((0, -0.062, -0.085), (0.058, 0.036, 0.065), (0.055, 0.042, 0.065), e1=0.3, e2=0.35, nu=24, nv=48), ['innen_hart']))
    for s in (1, -1):
        # Lenkstockhebel: 12 cm, 15 Grad nach unten, hinter dem Kranz
        a = np.array([s * 0.050, -0.040, -0.070]); b = a + np.array([s * 0.100, -0.027, 0.004])
        V1, N1, F1 = zylinder_voll(a, b, 0.0055)
        teile.append(Teil(V1, N1, F1, ['innen_hart']))
        teile.append(Teil(*superellipsoid(b, (0.012, 0.009, 0.009), None, e1=0.6, e2=0.8, nu=12, nv=24), ['innen_hart']))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    # Lokal: Radebene x-y, Achse +z zum Fahrer. Die Lenksaeule faellt zur
    # Stirnwand hin ab; die Radachse zeigt also zum Fahrer nach hinten und
    # nach OBEN, der obere Kranz steht weiter vorn als der untere (neig =
    # Winkel der Radebene zur Waagerechten). Bis 23.09.2026 zeigte sie nach
    # unten: Der obere Kranz kippte zum Fahrer, das Rad "hing" (Uwe), und die
    # Saeulenverkleidung stieg vor das Kombiinstrument.
    w = np.radians(90 - L['neig'])
    achse = np.array([0.0, np.cos(w), np.sin(w)])        # zum Fahrer: nach hinten, etwas nach oben
    ex = np.array([1.0, 0, 0]); ey = np.cross(achse, ex); ey /= np.linalg.norm(ey)
    if ey[2] < 0:
        ey = -ey
    Mx = np.stack([ex, ey, achse], 1)
    V = out.V @ Mx.T + np.array([M['fahrer_x'], L['d'], L['z']])
    N = out.N @ Mx.T
    return Teil(V, N, out.F, out.stoffe, out.fidx, out.uv)


# ================================================================= Mittelkonsole
def konsole(K, M):
    S, d_lip, z_lip = armatur_masse(K, M)
    dH = M['h_punkt'][0]
    zb = M['boden_z']
    d0, d1 = d_lip + 0.02, dH + 0.14
    ds = np.arange(d0, d1, 0.006)
    ringe, orte = [], []
    for d in ds:
        t = (d - d0) / (d1 - d0)
        b = 0.105 - 0.012 * t
        z_top = 0.50 + 0.085 * glatt((t - 0.62) / 0.25)        # Armlehne hinten hoeher
        z_u = zb + 0.10
        pts = [(b, z_top - 0.01), (0, z_top + 0.004), (-b, z_top - 0.01), (-b - 0.004, z_u), (b + 0.004, z_u)]
        ringe.append(gleichmaessig(verrunden(pts, [0.02, 0.0, 0.02, 0.01, 0.01], n=8), 120))
        orte.append((0.0, d, 0.0))
    t = loft(np.array(ringe), np.array(orte), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), kappe=(0.02, 0.02), stoffe=('innen_hart',))
    teile = [t]
    # Armlehne (gepolstert, Bezug wie Sitz, mit Naht)
    al = polster(lambda v: lehne_profil(0.085, 0.030, 0.004, einlage=0.06, krone=0.008), 0.26, 120,
                 basis=(np.array([0.0, dH - 0.10, 0.60]), np.array([0.0, 1.0, 0.0]), np.array([1.0, 0, 0]), np.array([0, 0, 1.0])),
                 kanaele=(), kappe=(0.02, 0.02))
    teile.append(al)
    # Waehlhebel
    wh = np.array([0.0, d0 + 0.20, 0.515])
    if K.was == 'kleinwagen':
        teile.append(Teil(*superellipsoid(wh + np.array([0, 0, 0.012]), (0.042, 0.055, 0.020), None, e1=0.5, e2=0.4, nu=20, nv=48), ['sitz_haupt']))
        teile.append(Teil(*zylinder_voll(wh + np.array([0, 0, 0.02]), wh + np.array([0, 0.012, 0.085]), 0.008), ['innen_hart']))
        teile.append(Teil(*superellipsoid(wh + np.array([0, 0.016, 0.105]), (0.022, 0.030, 0.026), None, e1=0.55, e2=0.7, nu=24, nv=48), ['lenkrad']))
        teile.append(rahmen_platte(wh + np.array([0, 0.018, 0.1325]), np.array([1.0, 0, 0]), np.array([0, 1.0, 0]), np.array([0, 0, 1.0]),
                                   0.022, 0.028, 0.002, r=0.006, stoff='chrom_innen'))
    else:
        teile.append(Teil(*superellipsoid(wh + np.array([0, 0, 0.02]), (0.020, 0.045, 0.028), (0.020, 0.030, 0.012), e1=0.4, e2=0.45, nu=24, nv=48), ['klavierlack']))
        teile.append(Teil(*superellipsoid(wh + np.array([0, 0.005, 0.047]), (0.016, 0.032, 0.004), None, e1=0.4, e2=0.4, nu=12, nv=40), ['chrom_innen']))
    # Dekorflaeche um den Waehlhebel und Becherhalter
    teile.append(rahmen_platte((0, d0 + 0.20, 0.508), np.array([1.0, 0, 0]), np.array([0, 1.0, 0]), np.array([0, 0, 1.0]), 0.19, 0.16, 0.004, r=0.02, stoff='dekor'))
    for dx in (-0.045, 0.045):
        c = np.array([dx, d0 + 0.40, 0.505])
        teile.append(Teil(*zylinder_voll(c + np.array([0, 0, -0.06]), c + np.array([0, 0, 0.001]), 0.037, m=40), ['innen_hart']))
        V_, N_, F_ = torus(c + np.array([0, 0, 0.001]), 0.038, 0.003, n=64, m=10)
        teile.append(Teil(V_, N_, F_, ['chrom_innen']))
    # Ablage mit Gummimatte (induktives Laden)
    teile.append(rahmen_platte((0, d0 + 0.055, 0.515), np.array([1.0, 0, 0]), np.array([0, 0.3, 1.0]) / np.hypot(0.3, 1), np.array([0, 1.0, -0.3]) / np.hypot(0.3, 1),
                               0.15, 0.09, 0.003, r=0.012, stoff='innen_unten'))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


# ================================================================= Boden, Himmel, Kleinteile
def boden(K, M):
    S, d_lip, z_lip = armatur_masse(K, M)
    zb = M['boden_z']
    W = M['breite_innen'] + 0.02
    dh = M['hinten_h'][0]
    tunnel_h = {'kleinwagen': 0.10, 'mittelklasse': 0.17}[K.was]
    tunnel_b = {'kleinwagen': 0.14, 'mittelklasse': 0.17}[K.was]
    def z_fn(X, D):
        stirn = glatt((S + 0.52 - D) / 0.32) * 0.30                      # Fussraum-Stirnwand
        tunnel = tunnel_h * np.exp(-(X / tunnel_b) ** 4) * (1 - glatt((D - (dh - 0.15)) / 0.3) * 0.5)
        sitzstufe = glatt((D - (dh - 0.25)) / 0.08) * 0.13                # unter der Ruecksitzbank
        schweller = glatt((np.abs(X) - (W - 0.10)) / 0.08) * 0.12
        return zb + stirn + np.maximum(tunnel, 0) + sitzstufe + schweller
    xs = np.arange(-W, W + 1e-9, 0.01 / SKALA)
    ds = np.arange(S + 0.18, dh + 0.30, 0.01 / SKALA)
    teppich = flaeche_hoehe(xs, ds, z_fn, 'teppich', uv_skala=1.0)
    # Fussmatten vorn und hinten: 6 mm dick, gebundene Kante
    teile = [teppich]
    for x0, d0, d1, b in ((M['fahrer_x'], S + 0.46, S + 1.02, 0.44), (-M['fahrer_x'], S + 0.46, S + 1.02, 0.44),
                          (M['fahrer_x'] + 0.02, dh - 0.78, dh - 0.36, 0.42), (-M['fahrer_x'] - 0.02, dh - 0.78, dh - 0.36, 0.42)):
        xm = np.arange(x0 - b / 2, x0 + b / 2 + 1e-9, 0.01); dm = np.arange(d0, d1 + 1e-9, 0.01)
        mt = flaeche_hoehe(xm, dm, lambda X, D: z_fn(X, D) + 0.006, 'matte')
        teile.append(mt)
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


def himmel(K, M):
    """Dachhimmel: Innenseite des Dachs, 35 mm tiefer, an den Seiten nach
    unten eingerollt; Leuchte und Haltegriffe."""
    import fz_karosserie as FK
    S, d_lip, z_lip = armatur_masse(K, M)
    d0 = {'kleinwagen': 2.02, 'mittelklasse': 2.36}[K.was]
    d1 = {'kleinwagen': 3.62, 'mittelklasse': 3.78}[K.was]
    ds = np.arange(d0, d1, 0.01 / SKALA)
    reihen = []
    for d in ds:
        q = K.querschnitt(d)[FK.J_DACH:]
        q = q[q[:, 0] < q[:, 0].max() - 0.035]
        o = np.c_[q[:, 0] - 0.0, q[:, 1] - 0.035]
        o = o[::-1]                        # von der Mitte nach aussen
        xs = np.linspace(0, o[:, 0].max(), 60)
        zs = np.interp(xs, o[:, 0], o[:, 1])
        reihen.append(np.c_[xs, zs])
    R = np.array(reihen)
    # beidseitig: gespiegelt zusammensetzen
    voll = np.concatenate([np.c_[-R[:, ::-1, 0:1], R[:, ::-1, 1:2]], R[:, 1:]], 1)
    P = np.stack([voll[..., 0], np.repeat(ds[:, None], voll.shape[1], 1), voll[..., 1]], -1)
    # Rand nach unten rollen (letzte 2 cm)
    rand = np.abs(P[..., 0]) > np.abs(P[..., 0]).max(1, keepdims=True) - 0.02
    P[..., 2] -= rand * 0.012
    N = normalen_gitter(P)
    if N[..., 2].mean() > 0:
        N = -N
    V, Nn, F = gitter_netz(P, N)
    uv = np.c_[P[..., 0].ravel(), P[..., 1].ravel()]
    teile = [Teil(V, Nn, F, ['himmel'], None, uv)]
    # Innenleuchte vorn und Sonnenblenden
    zt0 = float(K.zt(d0 + 0.05)) - 0.035
    teile.append(rahmen_platte((0, d0 + 0.07, zt0 - 0.008), np.array([1.0, 0, 0]), np.array([0, 1.0, 0]), np.array([0, 0, -1.0]),
                               0.20, 0.09, 0.012, r=0.02, stoff='innen_hart'))
    for s in (1, -1):
        m = np.array([s * 0.36, d0 + 0.10, zt0 - 0.018])
        teile.append(rahmen_platte(m, np.array([1.0, 0, 0]), np.array([0, 1.0, 0]), np.array([0, 0, -1.0]), 0.34, 0.16, 0.016,
                                   r=0.03, stoff='himmel'))
    # Innenspiegel mit Kamerafuss
    ms = np.array([0.0, d0 + 0.02, zt0 - 0.075])
    teile.append(Teil(*superellipsoid(ms, (0.125, 0.018, 0.034), (0.125, 0.012, 0.034), e1=0.35, e2=0.25, nu=20, nv=64), ['innen_hart']))
    teile.append(rahmen_platte(ms + np.array([0, 0.0185, 0]), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), np.array([0, 1.0, 0]),
                               0.235, 0.058, 0.001, r=0.012, stoff='spiegelglas_innen', nr=6))
    teile.append(Teil(*superellipsoid((0, d0 - 0.03, zt0 + 0.005), (0.045, 0.05, 0.03), None, e1=0.35, e2=0.3, nu=16, nv=40), ['innen_hart']))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


def pedale(K, M):
    S, d_lip, z_lip = armatur_masse(K, M)
    xf = M['fahrer_x']
    teile = []
    for dx, b, h, dd, zz, st in ((0.02, 0.085, 0.060, S + 0.60, 0.42, 'innen_hart'), (-0.14, 0.050, 0.150, S + 0.62, 0.38, 'innen_hart'),
                                 (0.20, 0.07, 0.22, S + 0.55, 0.36, 'innen_hart')):
        n = np.array([0.0, 0.55, 0.83]); n /= np.linalg.norm(n)
        v = np.cross(n, [1.0, 0, 0]); v /= np.linalg.norm(v)
        if v[2] < 0:
            v = -v
        teile.append(rahmen_platte((xf + dx, dd, zz), np.array([1.0, 0, 0]), v, n, b, h, 0.012, r=0.01, stoff=st))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


def klima(K, M):
    """Klimabedienteil unter den Mittelduesen: Klavierlackleiste mit sechs
    Tasten und zwei Drehreglern (Chromring), Warnblinkschalter."""
    S, d_lip, z_lip = armatur_masse(K, M)
    zk = 0.690
    dk = d_lip + 0.050 + (0.02 if K.was == 'mittelklasse' else 0.0)
    n = np.array([0.0, 1.0, 0.45]); n /= np.linalg.norm(n)
    u = np.array([1.0, 0, 0]); v = np.cross(n, u); v /= np.linalg.norm(v)
    if v[2] < 0:
        v = -v
    m = np.array([0.0, dk, zk])
    teile = [rahmen_platte(m, u, v, n, 0.30, 0.060, 0.006, r=0.012, stoff='klavierlack')]
    for k in range(6):
        teile.append(rahmen_platte(m + n * 0.004 + u * (-0.065 + k * 0.026), u, v, n, 0.020, 0.018, 0.004, r=0.004, stoff='innen_hart', nr=4))
    for sx in (-0.115, 0.115):
        c = m + n * 0.004 + u * sx
        V_, N_, F_ = zylinder_voll(c, c + n * 0.018, 0.019, m=48)
        teiles = Teil(V_, N_, F_, ['innen_hart'])
        teile.append(teiles)
        Vt, Nt, Ft = torus((0, 0, 0), 0.0195, 0.0022, n=64, m=8)
        M3 = np.stack([u, v, n], 1)
        teile.append(Teil(Vt @ M3.T + c + n * 0.016, Nt @ M3.T, Ft, ['chrom_innen']))
    teile.append(rahmen_platte(m + n * 0.004 + v * 0.045, u, v, n, 0.030, 0.014, 0.004, r=0.004, stoff='rueck_innen', nr=4))
    out = teile[0]
    for t in teile[1:]:
        out = out.dazu(t)
    return out


def innenraum(K, M):
    """Alle Innenraumteile: {Objektname: Teil}. Die Namen beginnen mit
    innen_ -- danach richtet sich die Explosionsansicht (Stufe Innenraum)."""
    T = {}
    T['innen_sitz_fahrer'] = vordersitz(M, M['fahrer_x'])
    T['innen_sitz_beifahrer'] = vordersitz(M, -M['fahrer_x'])
    T['innen_ruecksitz'] = ruecksitzbank(M)
    a = armatur(K, M)
    arm = a[0]
    for t in a[1:]:
        arm = arm.dazu(t)
    T['innen_armatur'] = arm.dazu(duesen(K, M), displays(K, M), klima(K, M))
    T['innen_lenkrad'] = lenkrad(K, M)
    T['innen_konsole'] = konsole(K, M)
    T['innen_boden'] = boden(K, M)
    T['innen_himmel'] = himmel(K, M)
    T['innen_pedale'] = pedale(K, M)
    return T
