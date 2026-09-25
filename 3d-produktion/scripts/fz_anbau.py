"""fz_anbau.py -- Anbauteile und Innenraum (nur numpy).

Spiegel, Tuergriffe, Wischer, Antenne, Armaturentafel, Lenkrad, Sitze.
Alles aus Superellipsoiden und Tori mit analytischen Normalen: Die Pole
eines Kugelgitters bekommen so keine Schattierungsflecken, und abgerundete
Kaesten bleiben ein einziges glattes Netz ohne Boolesche Naht.

Innere Koordinaten wie in fz_karosserie: x quer (links = +x, von vorn
gesehen rechts), d laengs ab Vorderkante, z hoch. Die Fahrerseite ist links
(Linkslenker, Markt Italien/Deutschland), also +x.
"""
import numpy as np
from fz_geom import pchip


def _sp(t, e):
    return np.sign(t) * np.abs(t) ** e


def superellipsoid(mitte, halb_plus, halb_minus=None, e1=0.3, e2=0.3, nu=24, nv=48, achsen=(0, 1, 2)):
    """Abgerundeter Koerper. halb_plus/halb_minus: Halbachsen in +/- Richtung
    (x, d, z) -- so werden Spiegelgehaeuse vorn rund und hinten flach.
    achsen: welche Weltachse die Polachse (dritte) ist."""
    hp = np.asarray(halb_plus, float)
    hm = hp if halb_minus is None else np.asarray(halb_minus, float)
    u = np.linspace(-np.pi / 2, np.pi / 2, nu)
    v = np.linspace(-np.pi, np.pi, nv, endpoint=False)
    U, W = np.meshgrid(u, v, indexing='ij')
    cu, su, cv, sv = np.cos(U), np.sin(U), np.cos(W), np.sin(W)
    a = _sp(cu, e1) * _sp(cv, e2)
    b = _sp(cu, e1) * _sp(sv, e2)
    c = _sp(su, e1)
    lok = np.stack([a, b, c], -1)
    na = _sp(cu, 2 - e1) * _sp(cv, 2 - e2)
    nb = _sp(cu, 2 - e1) * _sp(sv, 2 - e2)
    nc = _sp(su, 2 - e1)
    nlok = np.stack([na, nb, nc], -1)
    # Achsen umsortieren: lokale (a, b, c) -> Welt
    P = np.zeros_like(lok); Nn = np.zeros_like(nlok)
    for k, w in enumerate(achsen):
        P[..., w] = lok[..., k]; Nn[..., w] = nlok[..., k]
    halb = np.where(P >= 0, hp, hm)
    P = P * halb + np.asarray(mitte, float)
    Nn = Nn / np.where(Nn >= 0, hp, hm)
    Nn /= np.maximum(np.linalg.norm(Nn, axis=-1, keepdims=True), 1e-12)
    return gitter_netz(P, Nn, wrap=True)


def gitter_netz(P, N, wrap=False):
    m, n = P.shape[:2]
    idx = np.arange(m * n).reshape(m, n)
    jn = n if wrap else n - 1
    a = idx[:-1, :jn]; b = idx[1:, :jn]
    nx = np.roll(idx, -1, 1) if wrap else idx[:, 1:]
    c = nx[1:, :jn]; d = nx[:-1, :jn]
    F = np.r_[np.stack([a, b, c], -1).reshape(-1, 3), np.stack([a, c, d], -1).reshape(-1, 3)]
    V = P.reshape(-1, 3); Nn = N.reshape(-1, 3)
    e1 = V[F[:, 1]] - V[F[:, 0]]; e2 = V[F[:, 2]] - V[F[:, 0]]
    fl = np.linalg.norm(np.cross(e1, e2), axis=1)
    F = F[fl > 1e-12]
    # Umlaufsinn nach den Normalen
    n_ = np.cross(V[F[:, 1]] - V[F[:, 0]], V[F[:, 2]] - V[F[:, 0]])
    if (n_ * Nn[F].mean(1)).sum(1).mean() < 0:
        F = F[:, ::-1]
    return V, Nn, F


def torus(mitte, R, r, achse_dreh=(0.0, 0.0), n=96, m=16, teil=1.0):
    """Ring um die lokale z-Achse, danach um x (neigung) gedreht."""
    u = np.linspace(0, 2 * np.pi * teil, n, endpoint=teil >= 1)
    v = np.linspace(0, 2 * np.pi, m, endpoint=False)
    U, W = np.meshgrid(u, v, indexing='ij')
    P = np.stack([(R + r * np.cos(W)) * np.cos(U), (R + r * np.cos(W)) * np.sin(U), r * np.sin(W)], -1)
    N = np.stack([np.cos(W) * np.cos(U), np.cos(W) * np.sin(U), np.sin(W)], -1)
    neig = achse_dreh[0]
    Rx = np.array([[1, 0, 0], [0, np.cos(neig), -np.sin(neig)], [0, np.sin(neig), np.cos(neig)]])
    P = P @ Rx.T + np.asarray(mitte, float); N = N @ Rx.T
    V, Nn, F = gitter_netz(P, N, wrap=teil >= 1)
    return V, Nn, F


def zusammen(*netze):
    V = []; N = []; F = []; o = 0
    for v, n, f in netze:
        V.append(v); N.append(n); F.append(f + o); o += len(v)
    return np.concatenate(V), np.concatenate(N), np.concatenate(F)


def drehen(V, N, winkel, achse, um):
    """Drehung um eine Weltachse (0 = x, 1 = d, 2 = z) durch Punkt `um`."""
    c, s = np.cos(winkel), np.sin(winkel)
    i, j = [k for k in range(3) if k != achse]
    M = np.eye(3); M[i, i] = c; M[i, j] = -s; M[j, i] = s; M[j, j] = c
    um = np.asarray(um, float)
    return (V - um) @ M.T + um, N @ M.T


# ================================================================= Masse
ANBAU = {
    'kleinwagen': dict(
        spiegel=dict(d=1.585, z=1.010, x_innen=0.78, x_aussen=0.990, vorn=0.105, hinten=0.032, hoch=0.074),
        griffe=[(2.05, 2.20), (2.82, 2.95)], griff_z=0.872,
        stirn=1.28, dach_vorn=2.10,
        lenkrad=dict(d=2.08, z=0.915, x=0.37, neig=np.radians(62)),
        sitz_v=2.52, sitz_h=3.22, kissen_z=0.50, lehne=1.18,
        antenne=3.38,
    ),
    'mittelklasse': dict(
        spiegel=dict(d=1.830, z=1.005, x_innen=0.82, x_aussen=1.040, vorn=0.125, hinten=0.034, hoch=0.080),
        griffe=[(2.28, 2.44), (3.10, 3.26)], griff_z=0.858,
        stirn=1.62, dach_vorn=2.50,
        lenkrad=dict(d=2.42, z=0.900, x=0.37, neig=np.radians(64)),
        sitz_v=2.86, sitz_h=3.62, kissen_z=0.48, lehne=1.15,
        antenne=3.75,
    ),
}


def spiegel(K):
    """Aussenspiegel: Gehaeuse (vorn rund, hinten flach), Spiegelglas,
    Fuss auf dem Tuerdreieck. Rueckgabe: {teil: (V, N, F)}."""
    A = ANBAU[K.was]['spiegel']
    xm = 0.5 * (A['x_innen'] + A['x_aussen']); hx = 0.5 * (A['x_aussen'] - A['x_innen'])
    geh = superellipsoid((xm, A['d'], A['z']), (hx, A['hinten'], A['hoch']), (hx, A['vorn'], A['hoch'] * 0.92),
                         e1=0.62, e2=0.55, nu=40, nv=72, achsen=(0, 1, 2))
    # Glas: flache, abgerundete Scheibe knapp vor der Hinterkante
    g = superellipsoid((xm + 0.004, A['d'] + A['hinten'] - 0.002, A['z']), (hx - 0.012, 0.0015, A['hoch'] - 0.010),
                       None, e1=0.35, e2=0.35, nu=20, nv=48, achsen=(0, 1, 2))
    # Fuss: schlanker Arm vom Gehaeuse zur Tuer
    fuss = superellipsoid((A['x_innen'] + 0.015, A['d'] - 0.01, A['z'] - A['hoch'] * 0.75), (0.045, 0.045, 0.022), None,
                          e1=0.5, e2=0.5, nu=16, nv=32)
    return {'spiegel_gehaeuse': geh, 'spiegel_glas': g, 'spiegel_fuss': fuss}


def griffe(K):
    """Buegelgriffe (Liste je Tuer: vorn, hinten), 4 mm vor der Tuerhaut. Die Flaeche folgt der
    Karosserie, damit der Griff anliegt statt zu schweben."""
    A = ANBAU[K.was]
    netze = []
    for d0, d1 in A['griffe']:
        nd, nz = 28, 8
        dd = np.linspace(d0, d1, nd)
        zz = np.linspace(A['griff_z'] - 0.017, A['griff_z'] + 0.017, nz)
        P = np.zeros((nd, nz, 3))
        for i, d in enumerate(dd):
            q = K.querschnitt(d)
            fl = q[: len(q)]
            for k, z in enumerate(zz):
                import fz_karosserie as _K
                lo = np.argmin(np.abs(fl[_K.J_FLANKE:_K.J_OBEN, 1] - z)) + _K.J_FLANKE
                P[i, k] = (fl[lo, 0], d, z)
        # Buegel: aussen 7 mm abstehend, an den Enden auf 1 mm zurueck
        s = np.linspace(-1, 1, nd)[:, None]; t = np.linspace(-1, 1, nz)[None, :]
        wulst = 0.001 + 0.006 * np.sqrt(np.clip(1 - s ** 8, 0, 1)) * np.sqrt(np.clip(1 - t ** 6, 0, 1))
        P[..., 0] += wulst
        # Enden rund: z-Ausdehnung an den Enden verkleinern
        form = np.sqrt(np.clip(1 - np.abs(s) ** 6, 0.05, 1))
        P[..., 2] = A['griff_z'] + (P[..., 2] - A['griff_z']) * form
        from fz_geom import normalen_gitter
        N = normalen_gitter(P)
        if N[..., 0].mean() < 0:
            N = -N
        netze.append(gitter_netz(P, N))
    return netze


def antenne(K):
    A = ANBAU[K.was]
    d = A['antenne']
    z = float(K.zt(d)) + 0.02
    # Haifischflosse: lang und flach nach vorn, hinten steil (Halbachsen +d = hinten)
    return superellipsoid((0.0, d, z - 0.012), (0.028, 0.035, 0.058), (0.028, 0.12, 0.010), e1=0.55, e2=0.6, nu=24, nv=48)


def wischer(K):
    """Zwei Wischerarme in Parkstellung am Scheibenfuss."""
    A = ANBAU[K.was]
    d0 = A['stirn'] + 0.07
    netze = []
    for x0, laenge in ((0.52, 0.62), (-0.05, 0.52)):
        n = 30
        s = np.linspace(0, 1, n)
        x = x0 - s * laenge
        d = d0 - 0.01 + 0.035 * s
        z = np.array([float(K.zt(di)) for di in d]) + 0.012
        # Querschnitt: flaches Rechteck 22 x 10 mm, abgerundet
        th = np.linspace(0, 2 * np.pi, 12, endpoint=False)
        qx = 0.011 * _sp(np.cos(th), 0.4); qz = 0.005 * _sp(np.sin(th), 0.4)
        P = np.stack([np.repeat(x[:, None], 12, 1), d[:, None] + qx[None, :], z[:, None] + qz[None, :]], -1)
        N = np.stack([np.zeros((n, 12)), np.repeat(_sp(np.cos(th), 0.6)[None, :], n, 0), np.repeat(_sp(np.sin(th), 0.6)[None, :], n, 0)], -1)
        N /= np.linalg.norm(N, axis=-1, keepdims=True)
        netze.append(gitter_netz(P, N, wrap=True))
    return zusammen(*netze)


def innenraum(K):
    """Armaturentafel, Lenkrad, Mittelkonsole, Sitze. Grob, aber in echten
    Massen: Durch das getoente Glas liest man Silhouetten, keine Naehte."""
    A = ANBAU[K.was]
    teile = {}
    st = A['stirn']
    # Armaturentafel
    dash = superellipsoid((0.0, st + 0.40, 0.84), (0.74, 0.22, 0.10), (0.74, 0.20, 0.12), e1=0.25, e2=0.18, nu=24, nv=64)
    # Instrumentenhutze vor dem Fahrer
    hutze = superellipsoid((A['lenkrad']['x'], A['lenkrad']['d'] - 0.20, 0.965), (0.17, 0.10, 0.045), None, e1=0.4, e2=0.3)
    konsole = superellipsoid((0.0, st + 0.95, 0.55), (0.10, 0.50, 0.14), None, e1=0.3, e2=0.25)
    teile['armatur'] = zusammen(dash, hutze, konsole)
    L_ = A['lenkrad']
    kranz = torus((0, 0, 0), 0.182, 0.016, (0.0, 0.0), n=96, m=14)
    nabe = superellipsoid((0, 0, 0.010), (0.075, 0.060, 0.030), None, e1=0.4, e2=0.4, nu=16, nv=32)
    sp1 = superellipsoid((0.10, 0.0, 0.004), (0.075, 0.022, 0.010), None, e1=0.5, e2=0.4, nu=12, nv=24)
    sp2 = superellipsoid((-0.10, 0.0, 0.004), (0.075, 0.022, 0.010), None, e1=0.5, e2=0.4, nu=12, nv=24)
    sp3 = superellipsoid((0.0, -0.10, 0.004), (0.022, 0.06, 0.010), None, e1=0.5, e2=0.4, nu=12, nv=24)
    V, N, F = zusammen(kranz, nabe, sp1, sp2, sp3)
    # Lenkrad liegt in der lokalen x-y-Ebene; aufrichten (um x) und an den Ort
    V, N = drehen(V, N, -L_['neig'], 0, (0, 0, 0))
    V = V + np.array([L_['x'], L_['d'], L_['z']])
    teile['lenkrad'] = (V, N, F)
    sitze = []
    for x in (0.37, -0.37):
        d = A['sitz_v']
        kissen = superellipsoid((x, d - 0.20, A['kissen_z']), (0.25, 0.26, 0.07), None, e1=0.35, e2=0.3)
        lehne_V, lehne_N, lehne_F = superellipsoid((x, d + 0.06, A['kissen_z'] + 0.34), (0.25, 0.07, 0.34), None, e1=0.3, e2=0.35)
        lehne_V, lehne_N = drehen(lehne_V, lehne_N, np.radians(-18), 0, (x, d + 0.06, A['kissen_z']))
        kopf_V, kopf_N, kopf_F = superellipsoid((x, d + 0.02, A['lehne'] + 0.10), (0.13, 0.05, 0.10), None, e1=0.4, e2=0.4)
        kopf_V, kopf_N = drehen(kopf_V, kopf_N, np.radians(-18), 0, (x, d + 0.06, A['kissen_z']))
        sitze += [kissen, (lehne_V, lehne_N, lehne_F), (kopf_V, kopf_N, kopf_F)]
    d = A['sitz_h']
    bank = superellipsoid((0.0, d - 0.18, A['kissen_z'] + 0.02), (0.64, 0.25, 0.08), None, e1=0.35, e2=0.25)
    bl_V, bl_N, bl_F = superellipsoid((0.0, d + 0.08, A['kissen_z'] + 0.33), (0.64, 0.07, 0.31), None, e1=0.3, e2=0.25)
    bl_V, bl_N = drehen(bl_V, bl_N, np.radians(-22), 0, (0, d + 0.08, A['kissen_z']))
    sitze += [bank, (bl_V, bl_N, bl_F)]
    for x in (0.42, 0.0, -0.42):
        kV, kN, kF = superellipsoid((x, d + 0.05, A['lehne'] + 0.06), (0.12, 0.045, 0.085), None, e1=0.4, e2=0.4)
        kV, kN = drehen(kV, kN, np.radians(-22), 0, (0, d + 0.08, A['kissen_z']))
        sitze.append((kV, kN, kF))
    teile['sitze'] = zusammen(*sitze)
    return teile


def spoiler(K, d0=3.585):
    """Dachspoiler am Heckklappen-Oberrand (Kleinwagen): Tragflaechen-
    Profil 15 cm tief, der Dachwoelbung folgend, an den Enden verjuengt."""
    from fz_geom import normalen_gitter
    q = K.querschnitt(d0)
    from fz_karosserie import J_ECKE
    dach = q[J_ECKE:]
    xmax = float(dach[:, 0].max()) - 0.01
    xs = np.linspace(-xmax, xmax, 90)
    z_dach = np.interp(np.abs(xs), dach[::-1, 0], dach[::-1, 1])
    # Profil (d, z) relativ zum Dach, geschlossen, im Uhrzeigersinn von vorn oben
    prof = np.array([(-0.030, 0.000), (0.000, 0.006), (0.050, 0.010), (0.100, 0.002), (0.128, -0.012), (0.132, -0.018),
                     (0.120, -0.022), (0.070, -0.020), (0.020, -0.014), (-0.020, -0.006)])
    from fz_geom import catmull
    prof = catmull(list(prof), 40, geschlossen=True)
    s = np.clip(1 - (np.abs(xs) / xmax) ** 10, 0.02, 1) ** 0.5
    P = np.zeros((len(xs), len(prof), 3))
    P[..., 0] = xs[:, None]
    P[..., 1] = d0 + prof[None, :, 0] * (0.6 + 0.4 * s[:, None])
    P[..., 2] = z_dach[:, None] + prof[None, :, 1] * s[:, None]
    N = normalen_gitter(P, wrap_j=True)
    mitte = P.mean((0, 1))
    if ((P - mitte) * N).sum(-1).mean() < 0:
        N = -N
    return gitter_netz(P, N, wrap=True)


# ================================================================= Tuerdetails
# Nahprobe 23.09.2026: Die B-Saeule war nur umgefaerbte Aussenhaut. Zwei
# Konturen (Saeulenrand, Tuerfuge) lagen dort 2 cm auseinander, das Gitter
# rastete sie gegeneinander ein, und im Klavierlack standen Zickzacklinien.
# Echte Saeulenblenden sind aufgeklipste Kunststoffteile: eine Blende je
# Tuerrahmen, 1,5 mm vor dem Blech, mit gerundeter Kante und der Fuge
# dazwischen. So werden sie hier gebaut -- ueber die Aussenhaut gelegt statt
# in sie hineingerechnet.
BLENDEN = {
    # (d_von, d_bis, Tuer) -- Fuge 4 mm bei der Tuerteilung
    'kleinwagen': [(2.268, 2.3195, 'tuer_v'), (2.3235, 2.384, 'tuer_h')],
    'mittelklasse': [(2.482, 2.5445, 'tuer_v'), (2.5485, 2.606, 'tuer_h')],
}


def saeulenblende(K, d0, d1, vor=0.0015, nd=14):
    """Flaches Band auf der Aussenhaut zwischen d0 und d1 in der Fensterzone,
    `vor` Meter davor; Kante gerundet (Rueckgabe V, N, F)."""
    import fz_karosserie as _K
    from fz_geom import normalen_gitter, falz
    # oben buendig mit der Scheibenoberkante: bis J_DACH + 1 stand die Blende
    # als Lasche ueber dem Dachholm (Nahprobe d).
    j0, j1 = _K.J_OBEN + _K.o(2), _K.J_DACH - _K.o(3)
    dd = np.linspace(d0, d1, nd)
    P = np.stack([np.c_[K.querschnitt(d)[j0:j1 + 1, 0], np.full(j1 - j0 + 1, d), K.querschnitt(d)[j0:j1 + 1, 1]] for d in dd])
    N = normalen_gitter(P)
    if N[..., 0].mean() < 0:
        N = -N
    P = P + N * vor
    V, Nn, F = gitter_netz(P, N)
    V, Nn, F, _ = falz(V, Nn, F, np.ones(len(V), bool), spalt=0.0, radius=0.0012, tiefe=0.004)
    return V, Nn, F


def griffmulde(K, d0, d1, z, tiefe=0.006, vor=0.0006, nd=40, nz=20):
    """Griffmulde hinter dem Buegelgriff: flache Schale auf der Tuerhaut, die
    nur ueber ihre Normalen vertieft wirkt (Spiegelungen biegen ein wie bei
    einer gepressten Mulde). Profil (1 - r^2)^2: am Rand ohne Knick, damit
    die Mulde ohne sichtbare Kante in die Tuer uebergeht."""
    import fz_karosserie as _K
    from fz_geom import normalen_gitter
    dm, hd, hz = 0.5 * (d0 + d1), 0.5 * (d1 - d0) + 0.022, 0.034
    s = np.linspace(-1, 1, nd); t = np.linspace(-1, 1, nz)
    S_, T_ = np.meshgrid(s, t, indexing='ij')
    P = np.zeros((nd, nz, 3))
    for i, si in enumerate(s):
        d = dm + si * hd
        q = K.querschnitt(d)
        fl = q[_K.J_FLANKE:_K.J_OBEN]
        for k, tk in enumerate(t):
            zz = z + tk * hz
            P[i, k] = (np.interp(zz, fl[::-1, 1], fl[::-1, 0]) if fl[0, 1] > fl[-1, 1] else np.interp(zz, fl[:, 1], fl[:, 0]), d, zz)
    N = normalen_gitter(P)
    if N[..., 0].mean() < 0:
        N = -N
    r2 = np.clip(S_ ** 2 + T_ ** 2, 0, 1)
    # Tiefe h = -tiefe (1 - r2)^2; Normale kippt um -grad h
    dh_ds = tiefe * 2 * (1 - r2) * 2 * S_ / hd * (r2 < 1)
    dh_dt = tiefe * 2 * (1 - r2) * 2 * T_ / hz * (r2 < 1)
    N = N.copy()
    N[..., 1] -= dh_ds; N[..., 2] -= dh_dt
    N /= np.linalg.norm(N, axis=-1, keepdims=True)
    P = P + np.stack([np.full_like(S_, vor), np.zeros_like(S_), np.zeros_like(S_)], -1) * np.sign(P[..., :1])
    # Ellipse ausschneiden: nur Zellen innerhalb r < 1
    V, Nn, F = gitter_netz(P, N)
    innen = (r2.ravel()[F] < 1.0).all(1)
    return V, Nn, F[innen]
