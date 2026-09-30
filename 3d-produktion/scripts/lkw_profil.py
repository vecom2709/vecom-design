"""lkw_profil.py -- Reifenquerschnitte des Sattelzugs (nur numpy, ohne Blender).

Gemeinsam fuer lkw_hd.py (Geometrie) und lkw_texturen.py (Flankenschrift und
Profilbloecke): Beide muessen wissen, welcher Abschnitt der Profillinie
Flanke und welcher Lauffläche ist, damit die Schrift genau auf der Flanke
sitzt (v = Bogenlaenge / Gesamtlaenge wie in pr_basis.drehkoerper).

Masse nach ETRTO, gerundet:
  Zugmaschine 315/70 R22.5: Felge 571,5 mm, Breite 315 mm, Flanke 220 mm
  Auflieger   385/65 R22.5: Breite 385 mm, Flanke 250 mm
"""
import math
import numpy as np


def reifen(breite, flanke, felge_d=0.5715, n_rillen=4):
    """Profillinie (r, z) von der inneren zur aeusseren Wulst.
    z = Querrichtung (0 = Reifenmitte), r = Abstand zur Achse.
    Liefert (punkte, abschnitte) -- abschnitte: Name je Segment."""
    rf = felge_d / 2 + 0.012          # Wulst sitzt auf dem Felgenhorn
    ra = felge_d / 2 + flanke          # Aussendurchmesser / 2
    b2 = breite / 2
    P, A = [], []

    def add(p, name):
        if P:
            A.append(name)
        P.append(p)
    # innere Wulst -> innere Flanke (Rundung wie ein echter Reifen: breiteste
    # Stelle auf etwa 55 % der Flankenhoehe)
    for t in np.linspace(0, 1, 9):
        r = rf + (ra - 0.035 - rf) * t
        bul = math.sin(math.pi * min(1, t / 0.9)) * 0.018
        add((r, -b2 + 0.010 - bul * (1 if t < 0.9 else 0.4)), 'flanke_i')
    # Schulter innen
    add((ra - 0.012, -b2 + 0.030), 'schulter')
    add((ra, -b2 + 0.060), 'schulter')
    # Laufflaeche mit Laengsrillen (echte Geometrie, Tiefe 14 mm)
    zs = np.linspace(-b2 + 0.06, b2 - 0.06, n_rillen + 1)
    rille = 0.016
    for k in range(n_rillen):
        z0, z1 = zs[k], zs[k + 1]
        if k > 0:
            add((ra, z0 + rille / 2), 'lauf')
        zm = z1 - rille / 2
        add((ra, zm - 0.002), 'lauf')
        if k < n_rillen - 1:
            add((ra - 0.014, zm + 0.002), 'rille')
            add((ra - 0.014, z1 + rille / 2 - 0.002), 'rille')
    add((ra, b2 - 0.060), 'lauf')
    add((ra - 0.012, b2 - 0.030), 'schulter')
    for t in np.linspace(1, 0, 9):
        r = rf + (ra - 0.035 - rf) * t
        bul = math.sin(math.pi * min(1, t / 0.9)) * 0.018
        add((r, b2 - 0.010 + bul * (1 if t < 0.9 else 0.4)), 'flanke_a')
    return P, A


def bogen_v(P):
    pr = np.asarray(P, float)
    s = np.r_[0, np.cumsum(np.hypot(np.diff(pr[:, 0]), np.diff(pr[:, 1])))]
    return s / s[-1]


def bereiche(P, A):
    """v-Bereich je Abschnittsname (min, max)."""
    v = bogen_v(P); out = {}
    for i, n in enumerate(A):
        lo, hi = v[i], v[i + 1]
        a, b = out.get(n, (1, 0))
        out[n] = (min(a, lo), max(b, hi))
    return out


REIFEN = {
    'zug': dict(breite=0.315, flanke=0.2205),
    'auflieger': dict(breite=0.385, flanke=0.250),
}

if __name__ == '__main__':
    for k, d in REIFEN.items():
        P, A = reifen(**d)
        print(k, len(P), {n: tuple(round(x, 3) for x in r) for n, r in bereiche(P, A).items()}, 'r_aussen', round(max(p[0] for p in P), 4))
