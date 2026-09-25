"""fz_tueren.py -- Tueren zum Oeffnen: Innenseite, Falzflaechen, Tuerausschnitt
(nur numpy).

Bis 23.09.2026 waren die Tueren Blechschalen: In der Explosionsansicht
flogen sie weg und zeigten innen blanken Lack. Jetzt oeffnen sie an echten
Scharnierachsen (vorne angeschlagen, etwa 65 Grad), und was man dann sieht,
ist gebaut:

  Tuer:        Innenschale 10,5 cm hinter der Aussenhaut (unten) bzw. 3 cm
               (Fensterrahmen), Falzflaeche in Wagenfarbe rundum, Dichtung
               um die Scheibe; Tuerverkleidung mit Armlehne, Zuziehmulde,
               Fensterhebertasten (Fahrertuer), Tueroeffner in Chrom,
               Lautsprecher, Kartenfach und Dekorleiste.
  Karosserie:  Tuerausschnitt (Schweller, A-/B-/C-Saeule, Dachrahmen) in
               Wagenfarbe mit umlaufender Gummidichtung, Einstiegsleiste,
               B-Saeulen-Verkleidung mit Gurt.

Grundlage ist das Netz der Tueraussenhaut selbst (aus fz_karosserie): Die
Innenschale ist eine nach innen versetzte Kopie, die Falzflaeche verbindet
die Randkanten beider -- so passen Kontur, Fuge und Innenseite exakt, ohne
dass die Tuerform ein zweites Mal beschrieben wird.
"""
import numpy as np
from fz_geom import randkanten, glatt
from fz_anbau import gitter_netz, zusammen, superellipsoid, torus
from fz_innen import Teil, rahmen_platte, zylinder_voll, loft, gleichmaessig, verrunden, polster, lehne_profil

GUERTEL = {'kleinwagen': 0.955, 'mittelklasse': 0.960}      # Unterkante Seitenscheibe
T_UNTEN, T_RAHMEN = 0.105, 0.030


def haut_x(K, d, z):
    """x der Aussenhaut (rechte Seite) bei Laengsstelle d, Hoehe z."""
    import fz_karosserie as FK
    q = K.querschnitt(d)[FK.J_FLANKE:FK.J_DACH]
    o = np.argsort(q[:, 1])
    return float(np.interp(z, q[o, 1], q[o, 0]))


def dicke(z, guertel):
    """Tuerdicke (Innenschale hinter der Haut) nach Hoehe."""
    return T_UNTEN + (T_RAHMEN - T_UNTEN) * glatt((z - (guertel - 0.03)) / 0.05)


def _band(Va, Vb, kanten, stoff, innen_nach=None):
    """Band aus Vierecken zwischen Randkanten in zwei Punktlagen."""
    n = len(Va)
    V = np.r_[Va, Vb]
    F = []
    for a, b in kanten:
        F += [(a, b, n + b), (a, n + b, n + a)]
    F = np.array(F)
    # Normalen je Punkt aus den Flaechen
    fn = np.cross(V[F[:, 1]] - V[F[:, 0]], V[F[:, 2]] - V[F[:, 0]])
    N = np.zeros_like(V)
    for k in range(3):
        np.add.at(N, F[:, k], fn)
    N /= np.maximum(np.linalg.norm(N, axis=1, keepdims=True), 1e-12)
    if innen_nach is not None:
        # Normalen so drehen, dass sie von `innen_nach` (Punkt) weg zeigen
        mitte = V[F].mean(1)
        falsch = ((mitte - innen_nach) * fn).sum(1) < 0
        F[falsch] = F[falsch][:, ::-1]
        fn = np.cross(V[F[:, 1]] - V[F[:, 0]], V[F[:, 2]] - V[F[:, 0]])
        N = np.zeros_like(V)
        for k in range(3):
            np.add.at(N, F[:, k], fn)
        N /= np.maximum(np.linalg.norm(N, axis=1, keepdims=True), 1e-12)
    benutzt = np.unique(F)
    neu = -np.ones(len(V), int); neu[benutzt] = np.arange(len(benutzt))
    return Teil(V[benutzt], N[benutzt], neu[F], [stoff])


def tuer_innen(K, V, N, F, namen, fidx, tuer, fahrer_seite=True):
    """Innenseite einer Tuer (rechte Netzhaelfte, x > 0). V, N, F, namen, fidx:
    das Tuernetz aus fz_karosserie vor dem Falz. Rueckgabe {teil: Teil},
    ausserdem die Scharnierachse (Punkt, Richtung) in inneren Koordinaten."""
    g = GUERTEL[K.was]
    glas = np.array([namen[i] == 'glas' for i in fidx])
    Fo = F[~glas]
    t = dicke(V[:, 2], g)
    Vi = V - np.c_[t, np.zeros(len(V)), np.zeros(len(V))]
    # Innenschale: Material nach Hoehe
    S = Vi[Fo].mean(1)
    zs = S[:, 2]
    arm = {'tuer_v': 0.655, 'tuer_h': 0.665}[tuer]
    mat = np.where(zs > g - 0.015, 3,                      # Fensterrahmen innen
          np.where(zs > arm + 0.13, 0,                     # Oberteil, weich
          np.where(zs > arm - 0.07, 1, 2)))                # Einlage um die Armlehne, unten hart
    stoffe = ['innen_oben', 'sitz_einlage', 'innen_unten', 'innen_hart']
    # Innenschale nach innen gewendet
    Fi = Fo[:, ::-1]
    benutzt = np.unique(Fi)
    neu = -np.ones(len(V), int); neu[benutzt] = np.arange(len(benutzt))
    Ni = -N[benutzt].copy(); Ni[:, 0] = -np.abs(Ni[:, 0]) - 0.2
    Ni /= np.linalg.norm(Ni, axis=1, keepdims=True)
    uv = np.c_[Vi[benutzt][:, 1], Vi[benutzt][:, 2]]
    schale = Teil(Vi[benutzt], Ni, neu[Fi], stoffe, mat, uv)
    # Randkanten der Aussenhaut ohne Glas: aussen (zur Fuge) und am Glas
    rk, rt = randkanten(Fo)
    glas_punkte = np.zeros(len(V), bool); glas_punkte[np.unique(F[glas])] = True
    am_glas = glas_punkte[rk[:, 0]] & glas_punkte[rk[:, 1]]
    # Falzflaeche: 10 mm hinter der Haut beginnen (dort endet der Falz der
    # Fuge), bis zur Innenschale
    Va = V - N * 0.010
    falz = _band(Va, Vi, rk[~am_glas], 'lack', innen_nach=np.array([V[:, 0].mean() - 0.05, V[:, 1].mean(), V[:, 2].mean()]))
    dicht = _band(V - N * 0.002, Vi, rk[am_glas], 'dichtung')
    teile = {'innen': schale, 'falz': falz, 'dichtung_innen': dicht}
    # ---------------------------------------------------------- Verkleidung
    d_lo, d_hi = V[:, 1].min(), V[:, 1].max()
    z_lo = V[:, 2].min()
    def xin(d, z):
        return haut_x(K, d, z) - dicke(z, g)
    det = []
    # Armlehne: Loft laengs, ragt 6 cm in den Innenraum
    a0, a1 = d_lo + 0.10, d_hi - (0.12 if tuer == 'tuer_v' else 0.10)
    if tuer == 'tuer_h':
        a1 = min(a1, d_lo + 0.55)
    ds = np.arange(a0, a1, 0.006)
    ringe, orte = [], []
    for d in ds:
        x0 = xin(d, arm)
        s = glatt((d - a0) / 0.08) * glatt((a1 - d) / 0.08)
        tief = 0.012 + 0.050 * s
        pts = [(x0 + 0.01, arm + 0.030), (x0 - tief + 0.012, arm + 0.028), (x0 - tief, arm + 0.005), (x0 - tief + 0.02, arm - 0.050), (x0 + 0.01, arm - 0.060)]
        ringe.append(gleichmaessig(verrunden(pts, [0.005, 0.015, 0.012, 0.02, 0.005], n=8), 64)); orte.append((0, d, 0))
    det.append(loft(np.array(ringe), np.array(orte), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), kappe=(0.01, 0.01), stoffe=('sitz_haupt',)))
    # Zuziehmulde vor der Armlehne (dunkle Schale) und Tueroeffner in Chrom
    dm = a0 + 0.07
    det.append(rahmen_platte((xin(dm, arm + 0.075) - 0.012, dm, arm + 0.075), np.array([0, 1.0, 0]), np.array([0, 0, 1.0]), np.array([-1.0, 0, 0]),
                             0.11, 0.045, 0.012, r=0.018, stoff='innen_hart'))
    dg = d_lo + 0.14
    zg = g - 0.075
    det.append(rahmen_platte((xin(dg, zg) - 0.004, dg, zg), np.array([0, 1.0, 0]), np.array([0, 0, 1.0]), np.array([-1.0, 0, 0]),
                             0.12, 0.040, 0.006, r=0.015, stoff='innen_hart'))
    det.append(rahmen_platte((xin(dg, zg) - 0.012, dg + 0.005, zg), np.array([0, 1.0, 0]), np.array([0, 0, 1.0]), np.array([-1.0, 0, 0]),
                             0.085, 0.016, 0.008, r=0.007, stoff='chrom_innen'))
    # Dekorleiste ueber die Verkleidung
    ds2 = np.arange(d_lo + 0.05, d_hi - 0.06, 0.008)
    ringe, orte = [], []
    zd = arm + 0.115
    for d in ds2:
        x0 = xin(d, zd)
        ringe.append(gleichmaessig(verrunden([(x0 + 0.004, zd - 0.012), (x0 - 0.004, zd - 0.012), (x0 - 0.004, zd + 0.012), (x0 + 0.004, zd + 0.012)], 0.003, n=5), 32))
        orte.append((0, d, 0))
    det.append(loft(np.array(ringe), np.array(orte), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), kappe=(0.004, 0.004), stoffe=('dekor',)))
    # Lautsprecher unten vorn
    dl = d_lo + (0.20 if tuer == 'tuer_v' else 0.16); zl = z_lo + 0.20
    rl = 0.085 if tuer == 'tuer_v' else 0.070
    xl = xin(dl, zl) - 0.004
    w = np.linspace(0, 2 * np.pi, 72, endpoint=False)
    rr = np.linspace(0, rl, 14)
    P = np.stack([np.full((14, 72), xl), dl + rr[:, None] * np.cos(w)[None], zl + rr[:, None] * np.sin(w)[None]], -1)
    Nn = np.tile([-1.0, 0, 0], P.shape[:2] + (1,))
    Vs, Ns, Fs = gitter_netz(P, Nn, wrap=True)
    det.append(Teil(Vs, Ns, Fs, ['lautsprecher'], None, np.c_[Vs[:, 1], Vs[:, 2]]))
    Vr, Nr, Fr = torus((0, 0, 0), rl + 0.004, 0.004, n=96, m=10)
    Vr = Vr[:, [2, 0, 1]] + np.array([xl - 0.002, dl, zl]); Nr = Nr[:, [2, 0, 1]]
    det.append(Teil(Vr, Nr, Fr, ['chrom_innen']))
    # Kartenfach
    dk0, dk1 = d_lo + 0.32, d_hi - 0.14
    if dk1 - dk0 > 0.15:
        ds3 = np.arange(dk0, dk1, 0.008)
        ringe, orte = [], []
        for d in ds3:
            x0 = xin(d, z_lo + 0.14)
            ringe.append(gleichmaessig(verrunden([(x0 + 0.01, z_lo + 0.07), (x0 - 0.045, z_lo + 0.075), (x0 - 0.05, z_lo + 0.19), (x0 - 0.035, z_lo + 0.20),
                                                  (x0 - 0.03, z_lo + 0.10), (x0 + 0.01, z_lo + 0.10)], [0.005, 0.01, 0.005, 0.005, 0.01, 0.005], n=6), 64))
            orte.append((0, d, 0))
        det.append(loft(np.array(ringe), np.array(orte), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), kappe=(0.01, 0.01), stoffe=('innen_hart',)))
    # Fensterhebertasten (Fahrertuer vier, sonst eine)
    tasten = 4 if (tuer == 'tuer_v' and fahrer_seite) else 1
    dp = a0 + 0.20 if tuer == 'tuer_v' else a0 + 0.12
    xp = xin(dp, arm) - 0.055
    det.append(rahmen_platte((xp, dp, arm + 0.031), np.array([0, 1.0, 0]), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]),
                             0.030 * tasten + 0.02, 0.045, 0.004, r=0.008, stoff='klavierlack'))
    for k in range(tasten):
        det.append(rahmen_platte((xp, dp + (k - (tasten - 1) / 2) * 0.030, arm + 0.036), np.array([0, 1.0, 0]), np.array([1.0, 0, 0]),
                                 np.array([0, 0, 1.0]), 0.020, 0.028, 0.006, r=0.005, stoff='innen_hart', nr=4))
    out = det[0]
    for x in det[1:]:
        out = out.dazu(x)
    teile['verkleidung'] = out
    # Scharnierachse: vordere Tuerkante, 3 cm innen, leicht nach innen geneigt
    vorne = V[:, 1] < d_lo + 0.02
    zz = V[vorne, 2]
    z0, z1 = np.percentile(zz, 15), min(np.percentile(zz, 60), g - 0.05)
    p0 = np.array([haut_x(K, d_lo + 0.03, z0) - 0.03, d_lo + 0.03, z0])
    p1 = np.array([haut_x(K, d_lo + 0.03, z1) - 0.03, d_lo + 0.03, z1])
    achse = (p1 - p0) / np.linalg.norm(p1 - p0)
    return teile, (0.5 * (p0 + p1), achse)


def tuerausschnitt(K, V, N, F, namen, fidx):
    """Tuerausschnitt der Karosserie entlang der Aussenkante einer Tuer:
    Band in Wagenfarbe nach innen (13 cm unten, 4 cm am Dachrahmen) und
    eine umlaufende Gummidichtung darauf."""
    g = GUERTEL[K.was]
    rk, rt = randkanten(F)
    glas = np.array([namen[i] == 'glas' for i in fidx])
    glas_punkte = np.zeros(len(V), bool); glas_punkte[np.unique(F[glas])] = True
    aussen = ~(glas_punkte[rk[:, 0]] & glas_punkte[rk[:, 1]])
    rk = rk[aussen]
    # Punkte 4 mm nach aussen (ueber die Fuge auf die Karosserieseite)
    mitte2 = np.array([V[:, 1].mean(), V[:, 2].mean()])
    rp = np.unique(rk.ravel())
    Vb = V.copy()
    r2 = V[rp][:, 1:] - mitte2
    r2 /= np.maximum(np.linalg.norm(r2, axis=1, keepdims=True), 1e-9)
    Vb[rp, 1:] += r2 * 0.004
    Vb[rp, 0] -= 0.012
    tb = T_UNTEN + 0.025 + (0.045 - T_UNTEN - 0.025) * glatt((V[:, 2] - (g - 0.03)) / 0.05)
    Vc = Vb - np.c_[tb, np.zeros(len(V)), np.zeros(len(V))]
    # Band nur unterhalb der Guertellinie. Darueber lief es waagerecht 4,5 cm
    # nach innen -- an der stark eingezogenen A-Saeule stach es als Zacken in
    # Wagenfarbe durch die Frontscheibe (gemessen 23.09.2026 mit nur=ausschnitt_,
    # auf allen drei Postern als weisser Keil sichtbar). Am Fensterrahmen
    # deckt ohnehin die Dichtung den Spalt.
    unten = (V[rk[:, 0], 2] < g - 0.015) & (V[rk[:, 1], 2] < g - 0.015)
    band = _band(Vb, Vc, rk[unten], 'lack', innen_nach=np.array([V[:, 0].mean(), V[:, 1].mean(), V[:, 2].mean()]))
    # Dichtung: Rohr entlang der Kante. Die Kanten zerfallen je nach Tuer in
    # mehrere Zuege (Glaskanten sind ausgenommen); jeder Zug wird einzeln
    # verfolgt und nur geschlossen, wenn er wirklich zum Start zurueckkehrt --
    # vorher schloss ein offener Zug quer durchs Fenster (schwarze Diagonale).
    from fz_details import rohr, neu_abtasten, glatt_pfad
    nach = {}
    for a, b in rk:
        nach.setdefault(int(a), set()).add(int(b)); nach.setdefault(int(b), set()).add(int(a))
    frei = {tuple(sorted((int(a), int(b)))) for a, b in rk}
    zuege = []
    while frei:
        enden = [k for k, v in nach.items() if sum(tuple(sorted((k, n))) in frei for n in v) == 1]
        start = enden[0] if enden else next(iter(frei))[0]
        kette = [start]; akt = start
        while True:
            weiter = [n for n in nach[akt] if tuple(sorted((akt, n))) in frei]
            if not weiter:
                break
            n = weiter[0]; frei.discard(tuple(sorted((akt, n)))); kette.append(n); akt = n
        if len(kette) > 3:
            zuege.append((kette[:-1] if kette[-1] == kette[0] else kette, kette[-1] == kette[0]))
    teile_d = []
    for kette, zu in zuege:
        pfad = 0.5 * (Vb[kette] + Vc[kette])
        pfad[:, 0] = Vb[kette, 0] - 0.35 * tb[kette]
        pfad = neu_abtasten(pfad, 0.008, geschlossen=zu)
        if len(pfad) < 4:
            continue
        pfad = glatt_pfad(pfad, 3)
        teile_d.append(rohr(pfad, 0.008, 10, geschlossen=zu))
    Vd = np.concatenate([t[0] for t in teile_d]); Nd = np.concatenate([t[1] for t in teile_d])
    versatz = np.cumsum([0] + [len(t[0]) for t in teile_d[:-1]])
    Fd = np.concatenate([t[2] + o for t, o in zip(teile_d, versatz)])
    dicht = Teil(Vd, Nd, Fd, ['dichtung'])
    return {'band': band, 'dichtung': dicht}


def b_saeule(K, M, d0, d1):
    """Innenverkleidung der B-Saeule mit Sicherheitsgurt (Fahrerseite x > 0)."""
    g = GUERTEL[K.was]
    zs = np.arange(0.36, 1.30, 0.01)
    ringe, orte = [], []
    dm = 0.5 * (d0 + d1); b = 0.5 * (d1 - d0) + 0.015
    for z in zs:
        x0 = haut_x(K, dm, min(z, 1.25)) - (T_UNTEN + 0.03 if z < g else 0.06)
        ringe.append(gleichmaessig(verrunden([(x0 + 0.02, -b), (x0 - 0.012, -b + 0.01), (x0 - 0.018, 0), (x0 - 0.012, b - 0.01), (x0 + 0.02, b)],
                                             [0.004, 0.01, 0.02, 0.01, 0.004], n=6), 48))
        orte.append((0, dm, z))
    ringe = np.array(ringe)
    P_or = np.array(orte)
    t = loft(np.c_[ringe[..., 0].reshape(-1), ringe[..., 1].reshape(-1)].reshape(ringe.shape), P_or, np.array([1.0, 0, 0]), np.array([0, 1.0, 0]),
             kappe=(0.01, 0.02), stoffe=('saeule',))
    # Gurt: Umlenkung oben, Band schraeg zum Sitz
    zo = 1.12
    xo = haut_x(K, dm, zo) - 0.085
    oben = np.array([xo, dm - 0.01, zo])
    unten = np.array([M['fahrer_x'] + 0.20, M['h_punkt'][0] + 0.05, M['h_punkt'][1] + 0.02])
    e = unten - oben; L = np.linalg.norm(e); e /= L
    q = np.cross(e, [1.0, 0, 0]); q /= np.linalg.norm(q)
    ss = np.linspace(0, L, 40)
    ring = gleichmaessig(verrunden([(0.024, 0.0012), (-0.024, 0.0012), (-0.024, -0.0012), (0.024, -0.0012)], 0.001, n=3), 24)
    gurt = loft(np.array([ring] * len(ss)), oben + ss[:, None] * e, q, np.cross(e, q), stoffe=('gurt',))
    umlenk = Teil(*superellipsoid(oben + np.array([-0.008, 0, 0.01]), (0.012, 0.035, 0.03), None, e1=0.4, e2=0.4, nu=16, nv=40), ['innen_hart'])
    return t.dazu(gurt, umlenk)


def einstieg(K, d0, d1):
    """Einstiegsleiste (Schwellerabdeckung) zwischen d0 und d1."""
    z = 0.305 if K.was == 'kleinwagen' else 0.290
    ds = np.arange(d0, d1, 0.01)
    ringe, orte = [], []
    for d in ds:
        xa = haut_x(K, d, z + 0.02) - 0.02
        ringe.append(gleichmaessig(verrunden([(xa, z + 0.012), (xa - 0.12, z + 0.012), (xa - 0.14, z - 0.05), (xa, z - 0.03)], [0.004, 0.01, 0.01, 0.004], n=5), 48))
        orte.append((0, d, 0))
    return loft(np.array(ringe), np.array(orte), np.array([1.0, 0, 0]), np.array([0, 0, 1.0]), kappe=(0.01, 0.01), stoffe=('innen_hart',))
