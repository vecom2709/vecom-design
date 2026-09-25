"""fz_details.py -- Leuchteninnenleben und Grillwaben (nur numpy).

Scheinwerfer: Lichtleiter (Tagfahrlicht) entlang der Oberkante, zwei
Projektionsmodule mit Linse, Gehaeuse. Rueckleuchte: umlaufender
Lichtleiter. Grill: Wabengitter, auf die Flaeche gelegt.
Alles aus der Linsenflaeche abgeleitet, damit Lichtleiter und Waben genau
der Kontur folgen, die Karosserie und Linse vorgeben.
"""
import numpy as np
from fz_geom import randkanten, sd_polygon
from fz_anbau import gitter_netz, zusammen


def randschleife(V, N, F):
    """Laengste geschlossene Randschleife eines Netzes, in Laufrichtung
    (Inneres links), dazu die Einwaertsrichtung in der Flaeche."""
    rk, _ = randkanten(F)
    nach = {int(a): int(b) for a, b in rk}
    gesehen = set(); beste = []
    for s in list(nach):
        if s in gesehen:
            continue
        sch = [s]; gesehen.add(s); v = nach.get(s)
        while v is not None and v != s and v not in gesehen:
            sch.append(v); gesehen.add(v); v = nach.get(v)
        if len(sch) > len(beste):
            beste = sch
    idx = np.array(beste)
    p = V[idx]; n = N[idx]
    t = np.roll(p, -1, 0) - np.roll(p, 1, 0)
    ein = np.cross(n, t)
    ein /= np.maximum(np.linalg.norm(ein, axis=1, keepdims=True), 1e-12)
    return p, n, ein


def glatt_pfad(p, n_iter=6):
    q = p.copy()
    for _ in range(n_iter):
        q[1:-1] = 0.5 * q[1:-1] + 0.25 * (q[:-2] + q[2:])
    return q


def neu_abtasten(p, schritt, geschlossen=False):
    if geschlossen:
        p = np.vstack([p, p[:1]])
    s = np.r_[0, np.cumsum(np.linalg.norm(np.diff(p, axis=0), axis=1))]
    n = max(4, int(s[-1] / schritt))
    z = np.linspace(0, s[-1], n + 1)
    q = np.stack([np.interp(z, s, p[:, k]) for k in range(3)], 1)
    return q[:-1] if geschlossen else q


def rohr(pfad, r, m=10, geschlossen=False):
    """Rohr entlang eines Pfads, Rahmen per Paralleltransport (kein Verdrillen)."""
    p = pfad
    t = np.gradient(p, axis=0)
    if geschlossen:
        t = np.roll(p, -1, 0) - np.roll(p, 1, 0)
    t /= np.maximum(np.linalg.norm(t, axis=1, keepdims=True), 1e-12)
    a = np.cross(t[0], [0, 0, 1.0])
    if np.linalg.norm(a) < 0.1:
        a = np.cross(t[0], [0, 1.0, 0])
    a /= np.linalg.norm(a)
    rahmen = [a]
    for i in range(1, len(p)):
        v = rahmen[-1] - t[i] * (rahmen[-1] @ t[i])
        rahmen.append(v / max(np.linalg.norm(v), 1e-12))
    A = np.array(rahmen); B = np.cross(t, A)
    w = np.linspace(0, 2 * np.pi, m, endpoint=False)
    Nn = np.cos(w)[None, :, None] * A[:, None, :] + np.sin(w)[None, :, None] * B[:, None, :]
    P = p[:, None, :] + r * Nn
    if geschlossen:
        P = np.concatenate([P, P[:1]], 0); Nn = np.concatenate([Nn, Nn[:1]], 0)
    return gitter_netz(P, Nn, wrap=True)


def scheinwerfer_innen(V, N, F, was):
    """Innenleben eines Scheinwerfers aus seiner Linsenflaeche (rechte Seite).
    Rueckgabe {teil: (V, N, F)}."""
    p, n, ein = randschleife(V, N, F)
    teile = {}
    # Lichtleiter: Rand 9 mm nach innen, 14 mm hinter die Linse, obere Haelfte
    pfad = p + ein * 0.009 - n * 0.014
    zmitte = 0.5 * (p[:, 2].min() + p[:, 2].max())
    oben = pfad[:, 2] > zmitte + 0.004
    # zusammenhaengendes Stueck der Schleife waehlen (Schleife rotieren)
    k = np.argmax(~oben) if oben.any() else 0
    rot = np.roll(np.arange(len(pfad)), -k)
    stueck = [i for i in rot if oben[i]]
    if len(stueck) > 8:
        weg = glatt_pfad(neu_abtasten(pfad[stueck], 0.004), 8)
        teile['tagfahrlicht'] = rohr(weg, 0.0035, 10)
    # Gehaeuse: Linse 45 mm nach hinten versetzt
    Vh = V - N * 0.045
    teile['gehaeuse'] = (Vh, N.copy(), F.copy())
    # Zwei Projektionsmodule in der unteren Haelfte, Achse nach vorn (-d)
    unten = V[:, 2] < zmitte - 0.004
    if unten.sum() > 10:
        xs = V[unten, 0]
        module = []
        for f in (0.30, 0.66):
            xm = xs.min() + (xs.max() - xs.min()) * f
            nah = unten & (np.abs(V[:, 0] - xm) < 0.02)
            if not nah.any():
                continue
            c = V[nah].mean(0)
            nv = N[nah].mean(0); nv /= np.linalg.norm(nv)
            r = 0.024
            mitte = c - nv * 0.022
            module.append(zylinder(mitte, -nv, r, 0.035))
            module.append(linse(mitte + nv * 0.001, nv, r * 0.86))
        if module:
            teile['projektor'] = zusammen(*[m for m in module[0::2]])
            teile['projektor_linse'] = zusammen(*[m for m in module[1::2]])
    return teile


def rueckleuchte_innen(V, N, F):
    p, n, ein = randschleife(V, N, F)
    teile = {}
    pfad = glatt_pfad(neu_abtasten(p + ein * 0.010 - n * 0.012, 0.004, geschlossen=True), 4)
    teile['leuchtband'] = rohr(pfad, 0.004, 10, geschlossen=True)
    pfad2 = glatt_pfad(neu_abtasten(p + ein * 0.024 - n * 0.016, 0.004, geschlossen=True), 6)
    teile['leuchtband_innen'] = rohr(pfad2, 0.003, 10, geschlossen=True)
    teile['gehaeuse'] = (V - N * 0.040, N.copy(), F.copy())
    return teile


def zylinder(mitte, achse, r, laenge, m=32):
    """Zylinder von `mitte` entlang `achse` (nach hinten), offen."""
    a = np.cross(achse, [0, 0, 1.0])
    if np.linalg.norm(a) < 1e-3:
        a = np.cross(achse, [1.0, 0, 0])
    a /= np.linalg.norm(a)
    b = np.cross(achse, a)
    w = np.linspace(0, 2 * np.pi, m, endpoint=False)
    ring = np.cos(w)[:, None] * a + np.sin(w)[:, None] * b
    s = np.array([0.0, laenge])
    P = mitte + s[:, None, None] * achse + r * ring[None]
    Nn = np.repeat(ring[None], 2, 0)
    return gitter_netz(P, Nn, wrap=True)


def linse(mitte, blick, r, m=32, n=8):
    """Leicht gewoelbte Linsenscheibe (Kugelkappe), Normale nach `blick`."""
    a = np.cross(blick, [0, 0, 1.0]); a /= np.linalg.norm(a)
    b = np.cross(blick, a)
    rr = np.linspace(0, r, n)
    w = np.linspace(0, 2 * np.pi, m, endpoint=False)
    R = r * 2.2
    h = np.sqrt(R * R - rr ** 2) - np.sqrt(R * R - r * r)
    P = mitte + h[:, None, None] * blick + rr[:, None, None] * (np.cos(w)[None, :, None] * a + np.sin(w)[None, :, None] * b)
    zent = mitte - (np.sqrt(R * R - r * r)) * blick
    Nn = P - zent
    Nn /= np.linalg.norm(Nn, axis=-1, keepdims=True)
    return gitter_netz(P, Nn, wrap=True)


def waben(umriss_xz, flaeche_d, zelle=0.022, steg=0.0026, tiefe=0.012, spiegeln=True):
    """Wabengitter in einem Umriss der Frontansicht (x, z; rechte Haelfte,
    x < 0 bis zur Mitte erlaubt). flaeche_d(x, z) -> d der Flaeche.
    Jede Wabe: Ring (Aussen- minus Innensechseck) plus Seitenwaende nach hinten."""
    s = zelle
    ro = s / np.sqrt(3)                          # Umkreis
    dx = s; dz = 1.5 * ro
    lo = umriss_xz.min(0); hi = umriss_xz.max(0)
    zellen = []
    zs = np.arange(lo[1] - dz, hi[1] + dz, dz)
    for k, z in enumerate(zs):
        off = 0.5 * dx if k % 2 else 0.0
        for x in np.arange(-hi[0] - dx + off, hi[0] + dx, dx):
            zellen.append((x, z))
    zellen = np.array(zellen)
    # ganze Breite in einem Zug (Umriss gilt fuer |x|): Beim Spiegeln lagen
    # die Waben auf der Mittellinie doppelt.
    innen = sd_polygon(np.c_[np.abs(zellen[:, 0]), zellen[:, 1]], umriss_xz) < -0.35 * s
    zellen = zellen[innen]
    w = np.radians(30 + 60 * np.arange(6))
    ri = ro - steg / np.cos(np.radians(30))
    netze = []
    for x, z in zellen:
        aus = np.c_[x + ro * np.cos(w), z + ro * np.sin(w)]
        inn = np.c_[x + ri * np.cos(w), z + ri * np.sin(w)]
        d = flaeche_d(np.r_[aus[:, 0], inn[:, 0]], np.r_[aus[:, 1], inn[:, 1]])
        da, di = d[:6], d[6:]
        Pa = np.c_[aus[:, 0], da, aus[:, 1]]; Pi = np.c_[inn[:, 0], di, inn[:, 1]]
        Pt = np.c_[inn[:, 0], di + tiefe, inn[:, 1]]
        Nf = np.tile([0, -1.0, 0], (12, 1))
        # Innenwand-Normalen zeigen zur Wabenmitte
        nw = np.c_[x - inn[:, 0], np.zeros(6), z - inn[:, 1]]
        nw /= np.linalg.norm(nw, axis=1, keepdims=True)
        Nn = np.r_[Nf, nw]
        netze.append((np.r_[Pa, Pi, Pi, Pt], np.r_[Nf[:6], Nf[6:], nw, nw], _waben_flaechen()))
    if not netze:
        return None
    return zusammen(*netze)


def _waben_flaechen():
    """Indizes fuer eine Wabe: 0-5 aussen, 6-11 innen (Stirn), 12-17 innen
    (Wand oben), 18-23 Wand hinten."""
    F = []
    for i in range(6):
        j = (i + 1) % 6
        F += [(i, 6 + j, j), (i, 6 + i, 6 + j)]
        F += [(12 + i, 18 + j, 12 + j), (12 + i, 18 + i, 18 + j)]
    return np.array(F)
