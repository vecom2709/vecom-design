"""fz_raeder.py -- Rad, Reifen, Bremse (nur numpy).

Lokales System: Achse = x (aussen positiv), Rad dreht in der y-z-Ebene.
Masse nach ETRTO: Reifenbreite b, Querschnitt q (Hoehe/Breite), Felge in
Zoll. 205/45 R17 -> Flanke 92 mm, Felgenhorn 431,8 mm, Aussendurchmesser
616 mm. Felge 7J x 17 (Kleinwagen) bzw. 8J x 18 (Mittelklasse).
"""
import numpy as np
from fz_geom import Gitter, catmull, sd_polygon, glatt
from fz_karosserie import verrunden

SEGMENTE = 1.0          # Anteil der Umfangsteilung (Echtzeitfassung: 0,5)


def drehkoerper(profil, n=192, nprofil=None):
    """Profil [(r, a), ...] um die x-Achse drehen. Gibt V, N, F zurueck.
    Normalen aus der Flaeche (Gitter), daher glatt ueber die Naht."""
    pr = np.asarray(profil, float)
    th = np.linspace(0, 2 * np.pi, n, endpoint=False)
    r, a = pr[:, 0], pr[:, 1]
    P = np.stack([np.repeat(a[:, None], n, 1), r[:, None] * np.cos(th), r[:, None] * np.sin(th)], -1)
    G = Gitter(P, wrap_j=True)
    return G


def netz_aus_gitter(G, T=None, ist=None):
    if T is None:
        T, _ = G.dreiecke(lambda S, IJ, N: np.zeros(len(S), int))
        ist = np.ones(len(T), bool)
    V, N, F, alt = G.teilnetz(T, ist)
    return V, N, F, alt


def reifen_profil(K):
    """Halber Reifenquerschnitt (r, a) von der Laufflaechenmitte ueber
    Schulter und Flanke bis ins Felgenhorn, mit vier Laengsrillen."""
    b, q, zoll = K.E['reifen']
    R = K.R_REIFEN; Rf = K.R_FELGE
    h = R - Rf
    halb = b / 2
    lauf = halb * 0.80                  # Laufflaechen-Halbbreite
    pts = []
    # Laufflaeche mit Rillen (Mitte -> Schulter), leicht gewoelbt (2 mm)
    rillen = [(0.21, 0.30), (0.62, 0.72)]         # Anteile der Laufbreite
    tiefe = 0.0075
    xs = np.linspace(0, 1, int(40 * SEGMENTE) + 1)
    for s in xs:
        a = s * lauf
        r = R - 0.002 * s * s
        for r0, r1 in rillen:
            if r0 < s < r1:
                # Rille mit schraegen Waenden
                w = min(s - r0, r1 - s) / (r1 - r0) * 2
                r -= tiefe * min(1, w * 6)
        pts.append((r, a))
    # Schulter (Radius ~ 16 mm) und Flanke mit groesster Breite bei ~45 % h
    schulter = catmull([(R - 0.002, lauf), (R - 0.006, lauf + 0.010), (R - 0.018, halb - 0.004),
                        (R - 0.035, halb), (Rf + h * 0.45, halb + 0.004), (Rf + 0.024, halb - 0.004),
                        (Rf + 0.012, halb - 0.012), (Rf + 0.004, halb - 0.016)], int(60 * SEGMENTE))
    pts += [tuple(p) for p in schulter[1:]]
    # gespiegelt auf die Innenseite
    innen = [(r, -a) for r, a in reversed(pts[1:])]
    return np.array(innen + pts)


def felge_gitter(K, typ):
    """Felgenstern als Polargitter: Ringe (r) x Winkel. Axiale Lage a(r)
    gibt die Schuesselung -- Nabe 40 mm tiefer als das Horn."""
    Rf = K.R_FELGE
    r_horn = Rf + 0.010
    ringe = np.r_[np.linspace(0.028, Rf - 0.020, int(60 * SEGMENTE)), np.linspace(Rf - 0.017, r_horn, 8)]
    breite = {'kleinwagen': 0.178, 'mittelklasse': 0.203}[K.was]      # 7J / 8J
    a_horn = breite / 2 + 0.006

    def axial(r):
        s = np.clip((r - 0.06) / (Rf - 0.035 - 0.06), 0, 1)
        a = 0.050 + (a_horn - 0.012 - 0.050) * (1 - (1 - s) ** 2.2)
        # Horn: kleiner Wulst nach aussen
        horn = np.clip((r - (Rf - 0.02)) / 0.03, 0, 1)
        a = a + 0.012 * np.sin(horn * np.pi * 0.9)
        # Nabenkappe leicht gewoelbt
        a = np.where(r < 0.034, 0.050 + 0.004 * (1 - (r / 0.034) ** 2), a)
        return a
    n = int(480 * SEGMENTE)
    th = np.linspace(0, 2 * np.pi, n, endpoint=False)
    P = np.stack([np.repeat(axial(ringe)[:, None], n, 1), ringe[:, None] * np.cos(th), ringe[:, None] * np.sin(th)], -1)
    G = Gitter(P, wrap_j=True)
    # Normalen nach aussen (+x) drehen
    if G.N[..., 0].mean() < 0:
        G.N = -G.N
    return G, axial, a_horn


def speichen_fenster(K, typ):
    """Fensterpolygone (y, z) zwischen den Speichen.
    kleinwagen: 5 Doppelspeichen, mittelklasse: 10 schlanke Speichen."""
    Rf = K.R_FELGE
    r_in, r_aus = 0.078, Rf - 0.030
    fenster = []

    def fenster_zwischen(phi_a, phi_b, wa, wb, ri, ra, rund=0.009):
        """Fenster zwischen zwei Speichenmittellinien (Winkel) mit halben
        Speichenbreiten wa, wb (Meter, konstant) von ri bis ra."""
        pts = []
        for r in np.linspace(ra, ri, 12):                  # Seite B nach innen
            t = phi_b - np.arcsin(min(0.99, wb / r))
            pts.append((r * np.cos(t), r * np.sin(t)))
        ta = phi_a + np.arcsin(min(0.99, wa / ri)); tb = phi_b - np.arcsin(min(0.99, wb / ri))
        for t in np.linspace(tb, ta, 10)[1:-1]:             # innerer Bogen
            pts.append((ri * np.cos(t), ri * np.sin(t)))
        for r in np.linspace(ri, ra, 12):                  # Seite A nach aussen
            t = phi_a + np.arcsin(min(0.99, wa / r))
            pts.append((r * np.cos(t), r * np.sin(t)))
        ta = phi_a + np.arcsin(min(0.99, wa / ra)); tb = phi_b - np.arcsin(min(0.99, wb / ra))
        for t in np.linspace(ta, tb, 24)[1:-1]:             # aeusserer Bogen
            pts.append((ra * np.cos(t), ra * np.sin(t)))
        pts = np.array(pts)
        # Ecken runden: nur die vier echten Ecken
        return rund_ecken(pts, rund)

    if typ == 'kleinwagen':
        for k in range(5):
            m = np.radians(90 + 72 * k)
            # grosses Fenster zwischen zwei Paaren
            fenster.append(fenster_zwischen(m + np.radians(9), m + np.radians(63), 0.0105, 0.0105, r_in, r_aus, 0.012))
            # schmaler Schlitz zwischen den Zwillingen, V-foermig
            fenster.append(fenster_zwischen(m - np.radians(9), m + np.radians(9), 0.0105, 0.0105, 0.128, r_aus, 0.006))
    else:
        for k in range(10):
            m = np.radians(90 + 36 * k)
            fenster.append(fenster_zwischen(m, m + np.radians(36), 0.0085, 0.0085, r_in + 0.004, r_aus, 0.010))
    return fenster


def rund_ecken(pts, r):
    """Scharfe Ecken eines dicht abgetasteten Polygons runden: Punkte nahe
    einer Ecke durch einen Viertelkreis ersetzen (Laplace-Glaettung nur dort)."""
    p = pts.copy()
    for _ in range(int(r / 0.0015)):
        q = 0.5 * p + 0.25 * (np.roll(p, 1, 0) + np.roll(p, -1, 0))
        # nur Punkte mit starkem Knick glaetten
        a = np.roll(p, 1, 0) - p; b = np.roll(p, -1, 0) - p
        c = (a * b).sum(1) / (np.linalg.norm(a, axis=1) * np.linalg.norm(b, axis=1) + 1e-12)
        knick = c > -0.985
        p[knick] = q[knick]
    return p


def felge(K, typ):
    """Felgenstern mit ausgeschnittenen Fenstern. Rueckgabe: Gitter,
    Dreiecke, Etiketten (0 = Stern, -1 = Fenster), Fensterfunktion."""
    G, axial, a_horn = felge_gitter(K, typ)
    fenster = speichen_fenster(K, typ)

    def f_fenster(S, IJ, N):
        q = S[:, 1:3]
        return np.min([sd_polygon(q, w, rand=0.01) for w in fenster], axis=0)
    n = G.einrasten('fenster', f_fenster)
    T, lab = G.dreiecke(lambda S, IJ, N: np.where(f_fenster(S, IJ, N) < 0, -1, 0))
    return G, T, lab, axial, a_horn


def felgenbett(K, a_horn):
    """Felgenbett (innen sichtbar durch die Fenster): Tiefbett-Profil."""
    Rf = K.R_FELGE
    pr = [(Rf - 0.004, a_horn - 0.010), (Rf - 0.012, a_horn - 0.016), (Rf - 0.030, a_horn - 0.030),
          (Rf - 0.030, -a_horn + 0.030), (Rf - 0.012, -a_horn + 0.016), (Rf + 0.006, -a_horn + 0.004), (Rf + 0.010, -a_horn - 0.004)]
    pr = catmull(pr, 40)
    G = drehkoerper(pr, int(160 * SEGMENTE))
    # Normalen nach innen (zur Achse): man sieht das Bett von innen
    if (G.N[..., 1:] * G.P[..., 1:]).sum(-1).mean() > 0:
        G.N = -G.N
    return G


def bremse(K, vorn=True):
    """Bremsscheibe (Ringscheibe mit Kanten) und Topf. Rueckgabe: Gitter."""
    Rs = {'kleinwagen': 0.144 if vorn else 0.136, 'mittelklasse': 0.165 if vorn else 0.150}[K.was]
    d = 0.022 if vorn else 0.012
    a0 = -0.015
    pr = [(0.040, a0 + 0.030), (0.080, a0 + 0.030), (0.085, a0 + 0.008), (0.090, a0 + 0.002), (Rs - 0.002, a0 + 0.002),
          (Rs, a0), (Rs, a0 - d), (Rs - 0.002, a0 - d - 0.002), (0.090, a0 - d - 0.002)]
    G = drehkoerper(np.array(pr), int(160 * SEGMENTE))
    return G, Rs, a0, d


def sattel(K, Rs, a0, d, winkel=np.radians(200)):
    """Bremssattel: gebogener Block ueber dem Scheibenrand."""
    r0, r1 = Rs - 0.050, Rs + 0.016
    th = np.linspace(winkel - 0.42, winkel + 0.42, 40)
    # Querschnitt (r, a) des Sattels, gerundet
    q = catmull([(r0, a0 + 0.020), (r1 - 0.006, a0 + 0.022), (r1, a0 + 0.012), (r1, a0 - d - 0.012),
                 (r1 - 0.006, a0 - d - 0.024), (r0, a0 - d - 0.026)], 30)
    P = np.stack([np.repeat(q[None, :, 1], len(th), 0), q[None, :, 0] * np.cos(th)[:, None], q[None, :, 0] * np.sin(th)[:, None]], -1)
    # Enden abrunden: Querschnitt zu den Enden hin schrumpfen
    s = np.abs(np.linspace(-1, 1, len(th)))[:, None, None]
    kern = np.array([a0 - d / 2, 0, 0])
    mitte_r = (r0 + r1) / 2
    zentrum = np.stack([np.full(len(th), a0 - d / 2), mitte_r * np.cos(th), mitte_r * np.sin(th)], -1)[:, None, :]
    f = 1 - 0.35 * np.clip((s - 0.8) / 0.2, 0, 1) ** 2
    P = zentrum + (P - zentrum) * f
    return Gitter(P)


def profil_stollen(K, G):
    """Querrillen in die Schulterbloecke (alle 48 mm, 5 mm tief) und feine
    Lamellen in die Mittelrippen: Ohne sie ist der Reifen im Nahbild ein
    glatter Ring mit Laengsrillen -- das liest man sofort als Modell."""
    from fz_geom import normalen_gitter
    P = G.P
    r = np.hypot(P[..., 1], P[..., 2]); a = P[..., 0]
    b, q, zoll = K.E['reifen']
    lauf = b / 2 * 0.80
    n = P.shape[1]
    k = np.arange(n)
    quer = (k % 6 == 0).astype(float)[None, :]
    lamelle = (k % 3 == 0).astype(float)[None, :] * (1 - quer)
    auf_lauf = r > K.R_REIFEN - 0.010
    schulter = auf_lauf & (np.abs(a) > lauf * 0.55)
    mitte = auf_lauf & (np.abs(a) < lauf * 0.50)
    tiefe = 0.0032 * quer * schulter + 0.0015 * lamelle * mitte
    f = np.where(r > 0, (r - tiefe) / np.maximum(r, 1e-9), 1)
    P[..., 1] *= f; P[..., 2] *= f
    G.P = P
    G.N = normalen_gitter(P, wrap_j=True)
