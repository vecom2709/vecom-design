"""fz_geom.py -- Flaechenkern fuer die Fahrzeuge (nur numpy, ohne bpy).

Warum ein eigener Kern und keine Booleschen Schnitte in Blender:
Die Karosserie ist eine Parameterflaeche P(i, j) -- Laengsstation i,
Querschnittspunkt j. Jedes Merkmal (Radlauf, Tuerfuge, Leuchte, Fenster)
ist eine vorzeichenbehaftete Funktion f(Punkt): negativ innen, positiv
aussen. Statt Netze zu schneiden, werden die Gitterpunkte, die einer Kante
f = 0 am naechsten liegen, genau auf diese Kante geschoben -- entlang der
Flaeche, nicht quer dazu. Ergebnis: scharfe, glatte Konturen auf einem
Netz, das trotzdem ein sauberes Gitter bleibt, und Normalen, die aus der
Flaeche selbst stammen (keine Schattierungsdellen an Schnittkanten, wie sie
Boolesche Operationen hinterlassen).
Dieselbe Mechanik baut auch Felgen (Polargitter) und Reifen (Drehkoerper).
"""
import numpy as np


# ------------------------------------------------------------------ Kurven
def pchip(pts):
    """Monotone kubische Hermite-Interpolation (Fritsch-Carlson): keine
    Ueberschwinger zwischen den Stuetzstellen."""
    x = np.array([p[0] for p in pts], float); y = np.array([p[1] for p in pts], float)
    h = np.diff(x); dlt = np.diff(y) / h
    m = np.zeros_like(y)
    m[0], m[-1] = dlt[0], dlt[-1]
    for k in range(1, len(x) - 1):
        if dlt[k - 1] * dlt[k] <= 0:
            m[k] = 0
        else:
            w1, w2 = 2 * h[k] + h[k - 1], h[k] + 2 * h[k - 1]
            m[k] = (w1 + w2) / (w1 / dlt[k - 1] + w2 / dlt[k])

    def f(t):
        t = np.clip(np.asarray(t, float), x[0], x[-1])
        k = np.clip(np.searchsorted(x, t) - 1, 0, len(x) - 2)
        s = (t - x[k]) / h[k]
        h00 = 2 * s**3 - 3 * s**2 + 1; h10 = s**3 - 2 * s**2 + s
        h01 = -2 * s**3 + 3 * s**2; h11 = s**3 - s**2
        return h00 * y[k] + h10 * h[k] * m[k] + h01 * y[k + 1] + h11 * h[k] * m[k + 1]
    return f


def glaetten1d(y, sigma_pkt, ungerade=True):
    """Gauss-Glaettung einer abgetasteten Kurve (sigma in Abtastpunkten).
    Enden ungerade gespiegelt: Steigung am Rand bleibt erhalten."""
    y = np.asarray(y, float)
    k = int(np.ceil(3 * sigma_pkt))
    if k < 1:
        return y.copy()
    kern = np.exp(-0.5 * (np.arange(-k, k + 1) / sigma_pkt) ** 2); kern /= kern.sum()
    if y.ndim == 1:
        p = np.pad(y, k, mode='reflect', reflect_type='odd' if ungerade else 'even')
        return np.convolve(p, kern, mode='valid')
    return np.stack([glaetten1d(y[:, i], sigma_pkt, ungerade) for i in range(y.shape[1])], 1)


def glatte_kurve(pts, sigma=0.02, schritt=0.001, rand=0.12):
    """Wie pchip, aber G2-stetig: pchip hat an jedem Stuetzpunkt einen
    Kruemmungssprung, und genau den liest das Auge in Spiegelungen als
    Welle (erste Nahaufnahme der Haube, 23.09.2026). Dicht abtasten, mit
    sigma glaetten; an den Enden (Bug, Heck: bewusst enge Radien) laeuft
    die Glaettung ueber `rand` Meter aus."""
    f = pchip(pts)
    x0, x1 = pts[0][0], pts[-1][0]
    xs = np.arange(x0, x1 + schritt / 2, schritt)
    ys = f(xs)
    yg = glaetten1d(ys, sigma / schritt)
    w = glatt(np.minimum(xs - x0, x1 - xs) / rand)
    yy = ys * (1 - w) + yg * w

    def g(t):
        return np.interp(np.asarray(t, float), xs, yy)
    return g


def catmull(pts, n=None, geschlossen=False, dicht=24, abstand=None):
    """Catmull-Rom (zentripetal) durch die Kontrollpunkte, nach Bogenlaenge
    gleichmaessig auf n Punkte (oder im Abstand `abstand`) verteilt.
    Zentripetal, weil die uniforme Form bei ungleichen Abstaenden Schlaufen
    wirft -- genau das passiert an engen Radien neben langen Geraden."""
    pts = [np.asarray(p, float) for p in pts]
    if geschlossen:
        P_ = [pts[-1]] + pts + [pts[0], pts[1]]
    else:
        P_ = [2 * pts[0] - pts[1]] + pts + [2 * pts[-1] - pts[-2]]
    dichte = []
    for i in range(1, len(P_) - 2):
        p0, p1, p2, p3 = P_[i - 1], P_[i], P_[i + 1], P_[i + 2]
        t0 = 0.0
        t1 = t0 + max(np.linalg.norm(p1 - p0), 1e-9) ** 0.5
        t2 = t1 + max(np.linalg.norm(p2 - p1), 1e-9) ** 0.5
        t3 = t2 + max(np.linalg.norm(p3 - p2), 1e-9) ** 0.5
        for t in np.linspace(t1, t2, dicht, endpoint=False):
            a1 = (t1 - t) / (t1 - t0) * p0 + (t - t0) / (t1 - t0) * p1
            a2 = (t2 - t) / (t2 - t1) * p1 + (t - t1) / (t2 - t1) * p2
            a3 = (t3 - t) / (t3 - t2) * p2 + (t - t2) / (t3 - t2) * p3
            b1 = (t2 - t) / (t2 - t0) * a1 + (t - t0) / (t2 - t0) * a2
            b2 = (t3 - t) / (t3 - t1) * a2 + (t - t1) / (t3 - t1) * a3
            dichte.append((t2 - t) / (t2 - t1) * b1 + (t - t1) / (t2 - t1) * b2)
    dichte.append(pts[0] if geschlossen else pts[-1])
    dichte = np.array(dichte)
    seg = np.r_[0, np.cumsum(np.linalg.norm(np.diff(dichte, axis=0), axis=1))]
    if n is None:
        n = max(8, int(round(seg[-1] / abstand)) + 1)
    ziel = np.linspace(0, seg[-1], n)
    out = np.stack([np.interp(ziel, seg, dichte[:, k]) for k in range(dichte.shape[1])], axis=1)
    return out[:-1] if geschlossen else out


def glatt(t):
    t = np.clip(t, 0.0, 1.0)
    return t * t * (3 - 2 * t)


# ------------------------------------------------------------------ Abstaende
def sd_polygon(q, poly, stueck=40000, rand=0.03):
    """Vorzeichenbehafteter Abstand von Punkten q (K,2) zu einem geschlossenen
    Polygon (M,2): negativ innen. Punkte ausserhalb des Huellrechtecks (plus
    `rand`) bekommen nur den Abstand zum Rechteck -- das Vorzeichen stimmt,
    und genau ist der Wert nur nahe der Kante noetig. Spart bei kleinen
    Merkmalen (Leuchten, Grill) ueber 90 % der Rechnung."""
    q = np.asarray(q, float); poly = np.asarray(poly, float)
    lo = poly.min(0) - rand; hi = poly.max(0) + rand
    aussen_box = np.maximum(lo - q, q - hi).max(1)
    out = aussen_box + rand
    nah = np.where(aussen_box <= 0)[0]
    a = poly; b = np.roll(poly, -1, axis=0); ab = b - a
    ab2 = np.maximum((ab * ab).sum(1), 1e-18)
    dy = np.where(b[:, 1] - a[:, 1] == 0, 1e-18, b[:, 1] - a[:, 1])
    for s in range(0, len(nah), stueck):
        ix = nah[s:s + stueck]
        p = q[ix, None, :]
        t = np.clip(((p - a) * ab).sum(2) / ab2, 0, 1)
        d = np.sqrt((((a + t[..., None] * ab) - p) ** 2).sum(2)).min(1)
        y = p[..., 1]
        kreuz = ((a[:, 1] > y) != (b[:, 1] > y)) & (p[..., 0] < (b[:, 0] - a[:, 0]) * (y - a[:, 1]) / dy + a[:, 0])
        innen = kreuz.sum(1) % 2 == 1
        out[ix] = np.where(innen, -d, d)
    return out


def d_linie(q, linie, stueck=40000):
    """Unvorzeichenbehafteter Abstand zu einem offenen Linienzug."""
    q = np.asarray(q, float); a = np.asarray(linie[:-1], float); b = np.asarray(linie[1:], float)
    ab = b - a; ab2 = np.maximum((ab * ab).sum(1), 1e-18)
    out = np.empty(len(q))
    for s in range(0, len(q), stueck):
        p = q[s:s + stueck, None, :]
        t = np.clip(((p - a) * ab).sum(2) / ab2, 0, 1)
        out[s:s + stueck] = np.sqrt((((a + t[..., None] * ab) - p) ** 2).sum(2)).min(1)
    return out


def normalen_gitter(P, wrap_j=False):
    """Flaechennormalen aus zentralen Differenzen des Gitters."""
    Pi = np.gradient(P, axis=0)
    if wrap_j:
        Pj = 0.5 * (np.roll(P, -1, axis=1) - np.roll(P, 1, axis=1))
    else:
        Pj = np.gradient(P, axis=1)
    n = np.cross(Pi, Pj)
    ln = np.linalg.norm(n, axis=-1, keepdims=True)
    return n / np.maximum(ln, 1e-12)


# ------------------------------------------------------------------ Netz
class Gitter:
    """Strukturiertes Gitter P (m, n, 3) mit Parameterkoordinaten IJ und
    Normalen N. `einrasten` schiebt Punkte auf Merkmalskanten, `dreiecke`
    zerlegt es in klassifizierte Dreiecke."""

    def __init__(self, P, N=None, wrap_j=False):
        self.P = np.array(P, float)
        self.m, self.n = self.P.shape[:2]
        self.wrap_j = wrap_j
        self.N = normalen_gitter(self.P, wrap_j) if N is None else np.array(N, float)
        ii, jj = np.meshgrid(np.arange(self.m), np.arange(self.n), indexing='ij')
        self.IJ = np.stack([ii, jj], -1).astype(float)
        self.fest = np.zeros((self.m, self.n), bool)
        self.kante = {}          # Merkmal -> Maske der eingerasteten Punkte

    def _kanten(self):
        """(A-Index, B-Index) aller Gitterkanten, flach."""
        idx = np.arange(self.m * self.n).reshape(self.m, self.n)
        ka = [(idx[:-1, :].ravel(), idx[1:, :].ravel())]
        if self.wrap_j:
            ka.append((idx.ravel(), np.roll(idx, -1, axis=1).ravel()))
        else:
            ka.append((idx[:, :-1].ravel(), idx[:, 1:].ravel()))
        a = np.concatenate([k[0] for k in ka]); b = np.concatenate([k[1] for k in ka])
        return a, b

    def einrasten(self, name, f, max_anteil=0.5):
        """Punkte auf die Nullstelle von f ziehen. f bekommt (K,3) Orte, (K,2)
        Parameter und (K,3) Normalen und liefert (K,) Werte.
        Pro Kante mit Vorzeichenwechsel wird der Kreuzungspunkt bestimmt
        (lineare Schaetzung + zwei Sekantenschritte auf der echten Funktion)
        und der naehere Endpunkt dorthin gezogen -- ausser er sitzt schon auf
        einem frueheren Merkmal. Pro Punkt gewinnt die kuerzeste Bewegung."""
        P = self.P.reshape(-1, 3); IJ = self.IJ.reshape(-1, 2); N = self.N.reshape(-1, 3)
        fest = self.fest.ravel()
        F = f(P, IJ, N)
        a, b = self._kanten()
        fa, fb = F[a], F[b]
        k = (fa * fb) < 0
        a, b, fa, fb = a[k], b[k], fa[k], fb[k]
        t = fa / (fa - fb)
        lo = np.zeros_like(t); hi = np.ones_like(t); flo = fa.copy(); fhi = fb.copy()
        for _ in range(3):
            pt = P[a] + t[:, None] * (P[b] - P[a]); it = IJ[a] + t[:, None] * (IJ[b] - IJ[a])
            nt = N[a] + t[:, None] * (N[b] - N[a])
            ft = f(pt, it, nt)
            links = (ft * flo) > 0
            lo = np.where(links, t, lo); flo = np.where(links, ft, flo)
            hi = np.where(links, hi, t); fhi = np.where(links, fhi, ft)
            t = lo + (hi - lo) * flo / np.where(flo - fhi == 0, 1e-18, flo - fhi)
        laenge = np.linalg.norm(P[b] - P[a], axis=1)
        # Kandidaten: (Punkt, Bewegung, t, a, b)
        kand_p = np.r_[a, b]
        kand_w = np.r_[t, 1 - t] * np.r_[laenge, laenge]
        kand_t = np.r_[t, t]; kand_a = np.r_[a, a]; kand_b = np.r_[b, b]
        erlaubt = (np.r_[t, 1 - t] <= max_anteil + 1e-9) | ((np.r_[t, 1 - t] <= 0.85) & np.r_[fest[b], fest[a]])
        erlaubt &= ~fest[kand_p]
        kand_p, kand_w, kand_t, kand_a, kand_b = (x[erlaubt] for x in (kand_p, kand_w, kand_t, kand_a, kand_b))
        ordnung = np.lexsort((kand_w, kand_p))
        kand_p, kand_w, kand_t, kand_a, kand_b = (x[ordnung] for x in (kand_p, kand_w, kand_t, kand_a, kand_b))
        erst = np.r_[True, kand_p[1:] != kand_p[:-1]]
        p, t, a, b = kand_p[erst], kand_t[erst], kand_a[erst], kand_b[erst]
        neuP = P[a] + t[:, None] * (P[b] - P[a])
        neuI = IJ[a] + t[:, None] * (IJ[b] - IJ[a])
        neuN = N[a] + t[:, None] * (N[b] - N[a])
        neuN /= np.maximum(np.linalg.norm(neuN, axis=1, keepdims=True), 1e-12)
        P[p] = neuP; IJ[p] = neuI; N[p] = neuN
        fest[p] = True
        self.P = P.reshape(self.m, self.n, 3); self.IJ = IJ.reshape(self.m, self.n, 2)
        self.N = N.reshape(self.m, self.n, 3); self.fest = fest.reshape(self.m, self.n)
        maske = np.zeros(self.m * self.n, bool); maske[p] = True
        self.kante[name] = maske.reshape(self.m, self.n)
        return len(p)

    def dreiecke(self, klasse):
        """Jedes Viereck in zwei Dreiecke; die Diagonale folgt eingerasteten
        Punkten, damit keine Kante quer ueber eine Kontur laeuft.
        klasse(Schwerpunkte (T,3), Parameter (T,2), Normalen (T,3)) liefert
        pro Dreieck eine Ganzzahl (-1 = weg)."""
        m, n = self.m, self.n
        idx = np.arange(m * n).reshape(m, n)
        jn = n if self.wrap_j else n - 1
        i0 = idx[:-1, :jn]; i1 = idx[1:, :jn]
        j1 = np.roll(idx, -1, axis=1) if self.wrap_j else idx[:, 1:]
        a = i0.ravel(); b = i1.ravel(); c = j1[1:, :jn].ravel(); d = j1[:-1, :jn].ravel()
        P = self.P.reshape(-1, 3); F = self.fest.ravel()
        ac = F[a] & F[c]; bd = F[b] & F[d]
        diag_ac = np.where(ac & ~bd, True, np.where(bd & ~ac, False,
                           np.linalg.norm(P[a] - P[c], axis=1) <= np.linalg.norm(P[b] - P[d], axis=1)))
        t1 = np.where(diag_ac[:, None], np.stack([a, b, c], 1), np.stack([a, b, d], 1))
        t2 = np.where(diag_ac[:, None], np.stack([a, c, d], 1), np.stack([b, c, d], 1))
        T = np.r_[t1, t2]
        # entartete Dreiecke (Nase, Heck: Querschnitt auf einen Strich) weg
        e1 = P[T[:, 1]] - P[T[:, 0]]; e2 = P[T[:, 2]] - P[T[:, 0]]
        flaeche = 0.5 * np.linalg.norm(np.cross(e1, e2), axis=1)
        T = T[flaeche > 1e-10]
        S = P[T].mean(1)
        SI = self.IJ.reshape(-1, 2)[T].mean(1)
        SN = self.N.reshape(-1, 3)[T].mean(1)
        return T, klasse(S, SI, SN)

    def teilnetz(self, T, ist):
        """Dreiecke T[ist] als eigenes Netz: (V, N, F, alte Indizes)."""
        Tt = T[ist]
        alt, neu = np.unique(Tt.ravel(), return_inverse=True)
        return self.P.reshape(-1, 3)[alt], self.N.reshape(-1, 3)[alt], neu.reshape(-1, 3), alt


# ------------------------------------------------------------------ Kanten
def randkanten(F):
    """Kanten, die nur zu einem Dreieck gehoeren, in Laufrichtung des
    Dreiecks (links liegt das Innere), dazu der Index dieses Dreiecks."""
    e = np.r_[F[:, [0, 1]], F[:, [1, 2]], F[:, [2, 0]]]
    tri = np.r_[np.arange(len(F)), np.arange(len(F)), np.arange(len(F))]
    s = np.sort(e, 1)
    _, inv, cnt = np.unique(s, axis=0, return_inverse=True, return_counts=True)
    rand = cnt[inv.ravel()] == 1
    return e[rand], tri[rand]


def falz(V, N, F, auswahl, spalt=0.0015, radius=0.0015, tiefe=0.004):
    """Umgebogene Blechkante an Randkanten, deren beide Punkte `auswahl`
    erfuellen: Der Rand wird um `spalt` zurueckgenommen, laeuft ueber einen
    Radius nach unten und endet `tiefe` unter der Flaeche. So sieht eine
    echte Fuge aus: zwei gerundete Kanten mit Schatten dazwischen, keine
    aufgemalte Linie.
    Rueckgabe: V, N, F und fuer jede neue Flaeche das Ursprungsdreieck
    (fuer das Material)."""
    rk, rt = randkanten(F)
    wahl = auswahl[rk[:, 0]] & auswahl[rk[:, 1]]
    rk, rt = rk[wahl], rt[wahl]
    if not len(rk):
        return V, N, F, np.zeros(0, int)
    aus = np.zeros_like(V)
    t = V[rk[:, 1]] - V[rk[:, 0]]
    nm = 0.5 * (N[rk[:, 0]] + N[rk[:, 1]])
    o = np.cross(t, nm)                     # zeigt vom Dreieck weg
    o /= np.maximum(np.linalg.norm(o, axis=1, keepdims=True), 1e-12)
    np.add.at(aus, rk[:, 0], o); np.add.at(aus, rk[:, 1], o)
    pk = np.unique(rk.ravel())
    aus[pk] /= np.maximum(np.linalg.norm(aus[pk], axis=1, keepdims=True), 1e-12)
    n0 = N[pk].copy(); a0 = aus[pk]
    V = V.copy(); N = N.copy()
    rand = V[pk] - a0 * spalt               # zurueckgenommene Kante
    r = radius
    # Viertelkreis ueber die Kante, dann senkrecht nach unten
    stufen = []
    for w in (0.0, np.pi / 4, np.pi / 2):
        mitte = rand - a0 * r - n0 * r
        p = mitte + a0 * (r * np.sin(w)) + n0 * (r * np.cos(w))
        nn = a0 * np.sin(w) + n0 * np.cos(w)
        stufen.append((p, nn))
    stufen.append((rand - n0 * max(tiefe, 2 * r) - a0 * (r * 0.3), a0 * 0.9 - n0 * 0.44))
    V[pk], N[pk] = stufen[0]
    neuV = [V]; neuN = [N]; zahl = len(V); ringe = []
    for p, nn in stufen[1:]:
        nn = nn / np.linalg.norm(nn, axis=1, keepdims=True)
        neuV.append(p); neuN.append(nn)
        ringe.append(dict(zip(pk.tolist(), range(zahl, zahl + len(pk)))))
        zahl += len(pk)
    V = np.concatenate(neuV); N = np.concatenate(neuN)
    neuF = []; quelle = []
    vor = {p: p for p in pk.tolist()}
    for ring in ringe:
        for (a, b), tq in zip(rk.tolist(), rt.tolist()):
            a0_, b0_ = vor[a], vor[b]
            a1, b1 = ring[a], ring[b]
            neuF.append((a0_, a1, b1)); neuF.append((a0_, b1, b0_))
            quelle += [tq, tq]
        vor = ring
    alt = len(F)
    F = np.r_[F, np.array(neuF, int)]
    return V, N, F, np.array(quelle, int)


def versetzen(V, N, F, abstand, umdrehen=False):
    """Kopie entlang der Normalen versetzt (Leuchtengehaeuse, Innenhaut)."""
    V2 = V + N * abstand
    F2 = F[:, ::-1].copy() if umdrehen else F.copy()
    N2 = -N if umdrehen else N.copy()
    return V2, N2, F2


def spiegeln(V, N, F):
    """An der Laengsmittelebene (x = 0) spiegeln, Umlaufsinn umkehren."""
    s = np.array([-1.0, 1.0, 1.0])
    return V * s, N * s, F[:, ::-1].copy()


def verschweissen(V, N, F, tol=1e-6):
    """Punkte auf der Mittelebene (x = 0), die doppelt vorkommen, vereinen."""
    mitte = np.abs(V[:, 0]) < tol
    idx = np.arange(len(V))
    if mitte.any():
        schluessel = np.round(V[mitte] / tol).astype(np.int64)
        _, erst, inv = np.unique(schluessel, axis=0, return_index=True, return_inverse=True)
        mi = np.where(mitte)[0]
        idx[mi] = mi[erst][inv.ravel()]
    F = idx[F]
    benutzt, neu = np.unique(F.ravel(), return_inverse=True)
    return V[benutzt], N[benutzt], neu.reshape(-1, 3)


def umlauf_je_flaeche(V, N, F):
    """Jedes Dreieck so drehen, dass seine Flaechennormale zu den
    Punktnormalen passt (fuer zusammengesetzte Kleinteile)."""
    n = np.cross(V[F[:, 1]] - V[F[:, 0]], V[F[:, 2]] - V[F[:, 0]])
    falsch = (n * N[F].mean(1)).sum(1) < 0
    F = F.copy()
    F[falsch] = F[falsch][:, ::-1]
    return F
