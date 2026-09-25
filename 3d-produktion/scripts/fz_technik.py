"""fz_technik.py -- Antrieb und Fahrwerk (nur numpy).

Im Poster unsichtbar, in der Explosionsansicht das, was die Bauweise
erklaert -- deshalb nach den echten Konzepten der Klasse angeordnet und mit
den Baugruppen, die man in einer Werkstatt sieht:

Kleinwagen (MQB A0 / CMF-B): Reihendreizylinder-Turbo QUER vor der
  Vorderachse (Block, Oelwanne, Zylinderkopf, Ventildeckel mit drei
  Zuendspulen, Saugrohr mit drei Kanaelen, Turbolader auf der Auslassseite,
  Riementrieb mit Lichtmaschine und Klimakompressor), Getriebe links
  angeflanscht, Antriebswellen mit Faltenbaelgen. Kuehler mit Luefter,
  Batterie, Katalysator mit Hitzeschild, Mittel- und Endtopf. Vorn
  McPherson (Daempfer, Feder, Stuetzlager, Dreieckslenker, Stabilisator,
  Lenkgetriebe), hinten Verbundlenkerachse mit getrennten Federn und
  Daempfern.
Mittelklasse (BMW G20 als Referenz): Reihenvierzylinder-Turbo LAENGS hinter
  der Vorderachse, Achtgang-Automatik mit Oelwanne, Kardanwelle mit
  Mittellager und Hardyscheiben, Hinterachsdifferenzial mit Kuehlrippen,
  Doppelrohr-Abgasanlage. Vorn Zweigelenk-Federbein, hinten Fuenflenker auf
  Hilfsrahmen.

Masse der Aggregate aus Herstellerangaben gerundet (Dreizylinder-Block ca.
45 x 25 cm; Reihenvierer ca. 65 cm lang), Lage aus Schnittzeichnungen der
Klasse. Innere Koordinaten wie fz_karosserie (x quer, d laengs, z hoch).
Rueckgabe {name: ((V, N, F), stoff)}; die Namen beginnen mit antrieb_ oder
fahrwerk_, danach richtet sich die Explosionsansicht.
"""
import numpy as np
from fz_anbau import superellipsoid, gitter_netz, zusammen, torus
from fz_details import rohr, zylinder


def zylinder_zu(a, b, r, m=20):
    """Geschlossener Zylinder von a nach b (Kappen als Kugelscheiben)."""
    a = np.asarray(a, float); b = np.asarray(b, float)
    achse = b - a; L = np.linalg.norm(achse); achse = achse / L
    V, N, F = zylinder(a, achse, r, L, m)
    k1 = superellipsoid(a, (r, r, r), None, e1=1.0, e2=1.0, nu=8, nv=m)
    k2 = superellipsoid(b, (r, r, r), None, e1=1.0, e2=1.0, nu=8, nv=m)
    return zusammen((V, N, F), k1, k2)


stab = zylinder_zu


def ring_um(mitte, R, r, achse, n=48, m=10):
    """Ring (Torus) um eine beliebige Achse."""
    V, N, F = torus((0, 0, 0), R, r, (0.0, 0.0), n=n, m=m)
    a = np.asarray(achse, float); a = a / np.linalg.norm(a)
    e = np.cross(a, [0, 0, 1.0])
    if np.linalg.norm(e) < 1e-3:
        e = np.cross(a, [1.0, 0, 0])
    e /= np.linalg.norm(e); q = np.cross(a, e)
    M = np.stack([e, q, a], 1)
    return V @ M.T + np.asarray(mitte, float), N @ M.T, F



def scheibe(mitte, achse, r, dicke, m=40, fase=0.15):
    """Flacher Zylinder mit gefasten Kanten (Riemenscheibe, Deckel)."""
    achse = np.asarray(achse, float); achse = achse / np.linalg.norm(achse)
    mitte = np.asarray(mitte, float)
    a = mitte - achse * dicke / 2; b = mitte + achse * dicke / 2
    V, N, F = zylinder(a, achse, r, dicke, m)
    e = np.cross(achse, [0, 0, 1.0])
    if np.linalg.norm(e) < 1e-3:
        e = np.cross(achse, [1.0, 0, 0])
    e /= np.linalg.norm(e); q = np.cross(achse, e)
    w = np.linspace(0, 2 * np.pi, m, endpoint=False)
    deckel = []
    for p, s in ((a, -1), (b, 1)):
        rr = np.array([0.0, r * (1 - fase), r])
        hh = np.array([0.0, 0.0, -dicke * fase * 0.5])
        P = p[None, None, :] + (rr[:, None, None] * (np.cos(w)[None, :, None] * e + np.sin(w)[None, :, None] * q)) + (hh[:, None, None] * s) * achse
        Nn = np.broadcast_to(achse * s, P.shape).copy()
        deckel.append(gitter_netz(P, Nn, wrap=True))
    return zusammen((V, N, F), *deckel)


def feder(fuss, kopf, R, draht, windungen=6.5, m=8):
    """Schraubenfeder zwischen zwei Punkten, Enden angelegt (flach)."""
    fuss = np.asarray(fuss, float); kopf = np.asarray(kopf, float)
    achse = kopf - fuss; L = np.linalg.norm(achse); e = achse / L
    a = np.cross(e, [1.0, 0, 0])
    if np.linalg.norm(a) < 0.1:
        a = np.cross(e, [0, 1.0, 0])
    a /= np.linalg.norm(a); b = np.cross(e, a)
    t = np.linspace(0, 2 * np.pi * windungen, int(windungen * 40))
    s = t / t[-1]
    # angelegte Endwindungen: Steigung an den Enden nahe null
    hub = 0.04 + 0.92 * np.clip((s - 0.07) / 0.86, 0, 1)
    hub = np.where(s < 0.07, s / 0.07 * 0.04, hub)
    p = fuss + hub[:, None] * achse + R * (np.cos(t)[:, None] * a + np.sin(t)[:, None] * b)
    return rohr(p, draht, m)


def rohrzug(punkte, r, m=12, schritt=0.01):
    from fz_details import neu_abtasten, glatt_pfad
    p = glatt_pfad(neu_abtasten(np.asarray(punkte, float), schritt), 10)
    return rohr(p, r, m)


def balg(a, b, r_klein, r_gross, falten=7, m=24):
    """Faltenbalg (Antriebswelle, Lenkgetriebe): Drehkoerper mit Wellen."""
    a = np.asarray(a, float); b = np.asarray(b, float)
    ach = b - a; L = np.linalg.norm(ach); e = ach / L
    q = np.cross(e, [0, 0, 1.0])
    if np.linalg.norm(q) < 1e-3:
        q = np.cross(e, [1.0, 0, 0])
    q /= np.linalg.norm(q); p = np.cross(e, q)
    s = np.linspace(0, 1, falten * 8 + 1)
    r = r_klein + (r_gross - r_klein) * np.sin(np.pi * s) ** 0.8 * (0.82 + 0.18 * np.cos(2 * np.pi * falten * s))
    w = np.linspace(0, 2 * np.pi, m, endpoint=False)
    P = a[None, None, :] + (s * L)[:, None, None] * e + r[:, None, None] * (np.cos(w)[None, :, None] * q + np.sin(w)[None, :, None] * p)
    dr = np.gradient(r, s * L)
    N = (np.cos(w)[None, :, None] * q + np.sin(w)[None, :, None] * p) - dr[:, None, None] * e
    N /= np.linalg.norm(N, axis=-1, keepdims=True)
    return gitter_netz(P, N, wrap=True)


def rippen(mitte, halb, zahl, achse=0, dicke=0.004, tiefe=0.006):
    """Guss- oder Kuehlrippen: flache Leisten auf einer Flaeche."""
    teile = []
    for k in range(zahl):
        c = np.array(mitte, float)
        c[achse] += (k - (zahl - 1) / 2) * (2 * halb[achse] / max(zahl, 1))
        h = list(halb); h[achse] = dicke / 2
        teile.append(superellipsoid(c, h, None, e1=0.2, e2=0.2, nu=8, nv=16))
    return zusammen(*teile)


def luefter(mitte, r, n_blatt=7):
    """Kuehlerluefter: Nabe und geschwungene Blaetter (Achse = d)."""
    mitte = np.asarray(mitte, float)
    teile = [superellipsoid(mitte, (0.045, 0.03, 0.045), None, e1=1.0, e2=1.0, nu=12, nv=32)]
    for k in range(n_blatt):
        w0 = 2 * np.pi * k / n_blatt
        rr = np.linspace(0.045, r, 14); tt = np.linspace(-0.22, 0.22, 6)
        R_, T_ = np.meshgrid(rr, tt, indexing='ij')
        w = w0 + T_ * (0.6 + 0.4 * R_ / r) + 0.3 * R_ / r
        P = np.stack([R_ * np.cos(w), T_ * 0.03 + 0.004 * np.sin(np.pi * (R_ - 0.045) / r), R_ * np.sin(w)], -1) + mitte
        from fz_geom import normalen_gitter
        N = normalen_gitter(P)
        teile.append(gitter_netz(P, N))
        teile.append(gitter_netz(P - np.array([0, 0.0015, 0]), -N))
    return zusammen(*teile)


def riemen(scheiben, breite=0.018, dicke=0.004):
    """Riemen um Scheiben [(mitte_xz, r)], Achse = d (quer eingebauter Motor:
    Riementrieb an der Stirnseite, x-z-Ebene bei festem d)."""
    pts = []
    for (c, r) in scheiben:
        for w in np.linspace(0, 2 * np.pi, 48, endpoint=False):
            pts.append((c[0] + r * np.cos(w), c[1] + r * np.sin(w)))
    pts = np.array(pts)
    # konvexe Huelle (Gift-Wrapping)
    start = np.argmin(pts[:, 0]); huelle = [start]; akt = start
    while True:
        kand = (akt + 1) % len(pts)
        for i in range(len(pts)):
            v1 = pts[kand] - pts[akt]; v2 = pts[i] - pts[akt]
            if v1[0] * v2[1] - v1[1] * v2[0] < 0:
                kand = i
        akt = kand
        if akt == start or len(huelle) > 400:
            break
        huelle.append(akt)
    return pts[huelle]


def technik(K):
    E = K.E; was = K.was
    av, ah = K.achse; za = K.z_achse
    sv, sh = E['spur'][0] / 2, E['spur'][1] / 2
    T = {}
    if was == 'kleinwagen':
        m0 = np.array([-0.06, av - 0.12, 0.50])
        # --- Motorblock mit Oelwanne und Oelfilter
        block = superellipsoid(m0, (0.23, 0.12, 0.16), None, e1=0.25, e2=0.22)
        wanne = superellipsoid(m0 + np.array([0, 0.01, -0.20]), (0.22, 0.13, 0.05), (0.21, 0.11, 0.05), e1=0.25, e2=0.25)
        T['antrieb_block'] = (zusammen(block, rippen(m0 + np.array([0, -0.121, 0.0]), (0.20, 0.004, 0.12), 6, achse=0)), 'motor')
        T['antrieb_oelwanne'] = (wanne, 'guss')
        T['antrieb_oelfilter'] = (zylinder_zu(m0 + np.array([0.12, -0.14, -0.04]), m0 + np.array([0.12, -0.20, -0.07]), 0.038), 'feder')
        # --- Kopf, Ventildeckel, drei Zuendspulen
        T['antrieb_kopf'] = (superellipsoid(m0 + np.array([0, -0.03, 0.195]), (0.22, 0.10, 0.045), None, e1=0.3, e2=0.25), 'motor')
        spulen = [zylinder_zu(m0 + np.array([x, -0.03, 0.27]), m0 + np.array([x, -0.03, 0.33]), 0.017) for x in (-0.12, 0.0, 0.12)]
        stecker = [superellipsoid(m0 + np.array([x, -0.03, 0.345]), (0.022, 0.016, 0.012), None, e1=0.4, e2=0.4, nu=10, nv=24) for x in (-0.12, 0.0, 0.12)]
        T['antrieb_ventildeckel'] = (zusammen(superellipsoid(m0 + np.array([0, -0.03, 0.258]), (0.20, 0.085, 0.028), None, e1=0.35, e2=0.3),
                                              *spulen, *stecker), 'kunststoff')
        # --- Saugrohr vorne: Sammler, drei Kanaele, Drosselklappe
        samm = superellipsoid(m0 + np.array([0, -0.20, 0.17]), (0.19, 0.045, 0.05), None, e1=0.4, e2=0.4)
        kanaele = [rohrzug([m0 + np.array([x, -0.19, 0.14]), m0 + np.array([x, -0.16, 0.22]), m0 + np.array([x, -0.10, 0.22])], 0.020) for x in (-0.12, 0.0, 0.12)]
        drossel = zylinder_zu(m0 + np.array([0.20, -0.20, 0.17]), m0 + np.array([0.27, -0.20, 0.17]), 0.030)
        T['antrieb_saugrohr'] = (zusammen(samm, *kanaele, drossel), 'kunststoff')
        # --- Turbolader hinten (Auslass): Turbine (Stahlguss) und Verdichter (Alu)
        tz = m0 + np.array([-0.05, 0.16, 0.10])
        T['antrieb_turbo_turbine'] = (zusammen(ring_um(tz, 0.055, 0.030, (0, 1, 0), n=64, m=16), scheibe(tz, (0, 1, 0), 0.05, 0.05)), 'guss')
        vz = tz + np.array([0.13, 0.0, 0.0])
        T['antrieb_turbo_verdichter'] = (zusammen(ring_um(vz, 0.050, 0.026, (0, 1, 0), n=64, m=16), scheibe(vz, (0, 1, 0), 0.045, 0.045),
                                                  zylinder_zu(tz, vz, 0.024)), 'motor')
        T['antrieb_ladeluft'] = (zusammen(rohrzug([vz + np.array([0.04, 0, 0.03]), vz + np.array([0.12, 0.0, 0.05]), np.array([0.30, 0.30, 0.35]),
                                                   np.array([0.28, 0.26, 0.47])], 0.026)), 'gummi')
        # --- Riementrieb an der rechten Stirnseite (x < 0): Kurbelwelle,
        #     Lichtmaschine, Klimakompressor, Spanner
        xr = m0[0] - 0.245
        sch = [((m0[1] - 0.02, m0[2] - 0.10), 0.075), ((m0[1] - 0.14, m0[2] + 0.12), 0.032), ((m0[1] - 0.16, m0[2] - 0.06), 0.052),
               ((m0[1] + 0.04, m0[2] + 0.05), 0.030)]
        T['antrieb_riemenscheiben'] = (zusammen(*[scheibe((xr, c[0], c[1]), (1, 0, 0), r, 0.022) for c, r in sch]), 'stahl')
        huelle = riemen(sch)
        pfad = np.c_[np.full(len(huelle), xr), huelle]
        T['antrieb_riemen'] = (rohr(np.vstack([pfad, pfad[:1]]), 0.005, 8), 'gummi')
        lima = m0 + np.array([-0.20, -0.14, 0.12])
        T['antrieb_lichtmaschine'] = (zusammen(zylinder_zu(lima + np.array([0.02, 0, 0]), lima + np.array([0.15, 0, 0]), 0.058),
                                               *[ring_um(lima + np.array([0.03 + 0.012 * k, 0, 0]), 0.060, 0.003, (1, 0, 0), n=48, m=6) for k in range(10)]), 'motor')
        T['antrieb_klima'] = (zylinder_zu(m0 + np.array([-0.21, -0.16, -0.06]), m0 + np.array([-0.06, -0.16, -0.06]), 0.055), 'motor')
        # --- Getriebe links angeflanscht, mit Rippen
        T['antrieb_getriebe'] = (zusammen(superellipsoid((0.28, av - 0.03, 0.47), (0.13, 0.15, 0.16), (0.10, 0.15, 0.16), e1=0.5, e2=0.5),
                                          rippen((0.36, av - 0.03, 0.47), (0.004, 0.13, 0.12), 5, achse=1)), 'motor')
        # --- Kuehler, Luefter, Batterie
        T['antrieb_kuehler'] = (superellipsoid((0.0, 0.24, 0.47), (0.33, 0.022, 0.17), None, e1=0.15, e2=0.12), 'kuehler')
        T['antrieb_luefter'] = (zusammen(luefter((0.0, 0.28, 0.47), 0.15), superellipsoid((0.0, 0.29, 0.47), (0.30, 0.010, 0.16), None, e1=0.2, e2=0.2)), 'kunststoff')
        bat = np.array([0.42, av - 0.28, 0.66])
        T['antrieb_batterie'] = (zusammen(superellipsoid(bat, (0.087, 0.12, 0.095), None, e1=0.12, e2=0.12),
                                          zylinder_zu(bat + np.array([0.04, -0.09, 0.095]), bat + np.array([0.04, -0.09, 0.115]), 0.010),
                                          zylinder_zu(bat + np.array([0.04, 0.09, 0.095]), bat + np.array([0.04, 0.09, 0.115]), 0.010)), 'kunststoff')
        # --- Antriebswellen mit Faltenbaelgen
        wellen = [stab((-0.20, av - 0.02, za + 0.01), (-sv + 0.09, av, za), 0.014), stab((0.34, av - 0.02, za + 0.01), (sv - 0.09, av, za), 0.014)]
        baelge = [balg((s * (sv - 0.20), av - 0.005, za + 0.005), (s * (sv - 0.10), av, za), 0.016, 0.045) for s in (1, -1)]
        baelge += [balg((-0.13, av - 0.02, za + 0.012), (-0.22, av - 0.02, za + 0.011), 0.016, 0.042),
                   balg((0.40, av - 0.02, za + 0.012), (0.31, av - 0.02, za + 0.011), 0.016, 0.042)]
        T['antrieb_wellen'] = (zusammen(*wellen), 'stahl')
        T['antrieb_baelge'] = (zusammen(*baelge), 'gummi')
        # --- Abgas: Hosenrohr, Katalysator mit Hitzeschild, Mittel-, Endtopf
        T['antrieb_auspuff'] = (zusammen(rohrzug([tz + np.array([0, 0.06, -0.05]), (0.03, av + 0.10, 0.30), (0.05, av + 0.30, 0.20), (0.05, 2.9, 0.21),
                                                  (-0.20, ah + 0.20, 0.27), (-0.35, ah + 0.32, 0.28)], 0.024),
                                         superellipsoid((0.05, 2.05, 0.215), (0.07, 0.16, 0.05), None, e1=0.4, e2=0.5),
                                         superellipsoid((-0.36, ah + 0.40, 0.29), (0.20, 0.14, 0.075), None, e1=0.3, e2=0.35),
                                         zylinder_zu((-0.40, ah + 0.52, 0.29), (-0.40, ah + 0.66, 0.285), 0.030)), 'stahl')
        T['antrieb_katalysator'] = (zusammen(superellipsoid((0.04, av + 0.18, 0.26), (0.065, 0.12, 0.060), None, e1=0.5, e2=0.8),
                                             superellipsoid((0.04, av + 0.18, 0.268), (0.085, 0.15, 0.072), None, e1=0.35, e2=0.5)), 'hitzeschild')
        T['antrieb_tank'] = (superellipsoid((0.0, ah - 0.58, 0.30), (0.36, 0.22, 0.10), None, e1=0.3, e2=0.25), 'kunststoff')
        # --- Hinterachse: Verbundlenker (U-Profil), Laengslenker, Federn und Daempfer getrennt
        balken = superellipsoid((0.0, ah - 0.26, 0.29), (0.58, 0.045, 0.055), None, e1=0.4, e2=0.25)
        laengs = [stab((s * 0.58, ah - 0.46, 0.31), (s * (sh - 0.10), ah, za), 0.030) for s in (1, -1)]
        lager = [ring_um((s * 0.58, ah - 0.46, 0.31), 0.035, 0.012, (1, 0, 0), n=32, m=10) for s in (1, -1)]
        T['fahrwerk_hinterachse'] = (zusammen(balken, *laengs), 'stahl')
        T['fahrwerk_lager_h'] = (zusammen(*lager), 'gummi')
        T['fahrwerk_federn_h'] = (zusammen(*[feder((s * 0.50, ah - 0.10, 0.30), (s * 0.50, ah - 0.10, 0.54), 0.055, 0.0065, 5.5) for s in (1, -1)]), 'feder')
        T['fahrwerk_daempfer_h'] = (zusammen(*[stab((s * (sh - 0.16), ah + 0.05, 0.28), (s * (sh - 0.20), ah + 0.12, 0.62), 0.020) for s in (1, -1)]), 'stahl')
    else:
        m0 = np.array([0.0, av + 0.32, 0.50])
        # --- Reihenvierzylinder laengs
        T['antrieb_block'] = (zusammen(superellipsoid(m0, (0.16, 0.33, 0.18), None, e1=0.25, e2=0.2),
                                       rippen(m0 + np.array([0.161, 0, -0.02]), (0.004, 0.30, 0.13), 8, achse=1)), 'motor')
        T['antrieb_oelwanne'] = (superellipsoid(m0 + np.array([0, 0.02, -0.22]), (0.15, 0.30, 0.05), (0.13, 0.28, 0.05), e1=0.25, e2=0.25), 'guss')
        T['antrieb_kopf'] = (superellipsoid(m0 + np.array([0.02, -0.02, 0.225]), (0.14, 0.32, 0.055), None, e1=0.3, e2=0.22), 'motor')
        spulen = [zylinder_zu(m0 + np.array([0.02, y, 0.30]), m0 + np.array([0.02, y, 0.36]), 0.017) for y in (-0.21, -0.07, 0.07, 0.21)]
        T['antrieb_ventildeckel'] = (zusammen(superellipsoid(m0 + np.array([0.0, -0.04, 0.29]), (0.16, 0.28, 0.025), None, e1=0.35, e2=0.3), *spulen), 'kunststoff')
        # Saugrohr links (x > 0), Turbo rechts (x < 0)
        samm = superellipsoid(m0 + np.array([0.24, 0.0, 0.18]), (0.05, 0.26, 0.055), None, e1=0.4, e2=0.4)
        kanaele = [rohrzug([m0 + np.array([0.24, y, 0.15]), m0 + np.array([0.22, y, 0.26]), m0 + np.array([0.12, y, 0.25])], 0.021) for y in (-0.21, -0.07, 0.07, 0.21)]
        T['antrieb_saugrohr'] = (zusammen(samm, *kanaele), 'kunststoff')
        tz = m0 + np.array([-0.24, 0.05, 0.10])
        T['antrieb_turbo_turbine'] = (zusammen(ring_um(tz, 0.060, 0.032, (1, 0, 0), n=64, m=16), scheibe(tz, (1, 0, 0), 0.055, 0.05)), 'guss')
        vz = tz + np.array([0.0, -0.14, 0.0])
        T['antrieb_turbo_verdichter'] = (zusammen(ring_um(vz, 0.055, 0.028, (0, 1, 0), n=64, m=16), zylinder_zu(tz, vz, 0.025)), 'motor')
        T['antrieb_ladeluft'] = (rohrzug([vz + np.array([0.0, -0.05, 0.03]), (-0.20, 0.45, 0.45), (0.0, 0.32, 0.42)], 0.028), 'gummi')
        lima = m0 + np.array([0.14, -0.36, 0.05])
        T['antrieb_lichtmaschine'] = (zusammen(zylinder_zu(lima, lima + np.array([0, 0.14, 0]), 0.060),
                                               *[ring_um(lima + np.array([0, 0.015 + 0.012 * k, 0]), 0.062, 0.003, (0, 1, 0), n=48, m=6) for k in range(10)]), 'motor')
        T['antrieb_riemenscheiben'] = (zusammen(scheibe(m0 + np.array([0, -0.34, -0.10]), (0, 1, 0), 0.080, 0.024),
                                                scheibe(lima + np.array([0, -0.01, 0]), (0, 1, 0), 0.032, 0.022)), 'stahl')
        # Getriebe: kegelig nach hinten, mit Oelwanne und Rippen
        t = np.linspace(0, 1, 30)
        d = av + 0.66 + t * 0.62
        r = 0.165 - 0.07 * t ** 1.3
        w = np.linspace(0, 2 * np.pi, 40, endpoint=False)
        P = np.stack([r[:, None] * np.cos(w)[None], np.repeat(d[:, None], 40, 1), 0.46 + r[:, None] * np.sin(w)[None]], -1)
        Nn = np.stack([np.cos(w)[None].repeat(30, 0), np.full((30, 40), 0.1), np.sin(w)[None].repeat(30, 0)], -1)
        Nn /= np.linalg.norm(Nn, axis=-1, keepdims=True)
        T['antrieb_getriebe'] = (zusammen(gitter_netz(P, Nn, wrap=True), rippen((0, av + 0.95, 0.30), (0.10, 0.20, 0.004), 6, achse=1)), 'motor')
        T['antrieb_getriebewanne'] = (superellipsoid((0.0, av + 0.95, 0.31), (0.12, 0.26, 0.03), None, e1=0.2, e2=0.2), 'guss')
        # Kardanwelle mit Mittellager und Hardyscheiben
        k0 = np.array([0, av + 1.28, 0.42]); k1 = np.array([0, ah - 0.22, 0.34]); km = 0.5 * (k0 + k1)
        T['antrieb_kardanwelle'] = (zusammen(stab(k0, km, 0.034), stab(km, k1, 0.036)), 'stahl')
        T['antrieb_kardan_lager'] = (zusammen(superellipsoid(km, (0.07, 0.03, 0.05), None, e1=0.4, e2=0.4),
                                              scheibe(k0 + np.array([0, -0.01, 0]), (0, 1, 0), 0.06, 0.02), scheibe(k1 + np.array([0, 0.01, 0]), (0, 1, 0), 0.06, 0.02)), 'gummi')
        T['antrieb_differenzial'] = (zusammen(superellipsoid((0.0, ah - 0.10, 0.34), (0.14, 0.13, 0.12), None, e1=0.6, e2=0.6),
                                              rippen((0.0, ah - 0.02, 0.34), (0.11, 0.004, 0.09), 7, achse=0)), 'guss')
        wellen = [stab((s * 0.12, ah - 0.08, 0.33), (s * (sh - 0.09), ah, za), 0.018) for s in (1, -1)]
        baelge = [balg((s * (sh - 0.20), ah - 0.02, za + 0.004), (s * (sh - 0.10), ah, za), 0.018, 0.046) for s in (1, -1)]
        T['antrieb_wellen'] = (zusammen(*wellen), 'stahl')
        T['antrieb_baelge'] = (zusammen(*baelge), 'gummi')
        T['antrieb_kuehler'] = (superellipsoid((0.0, 0.26, 0.47), (0.36, 0.022, 0.18), None, e1=0.15, e2=0.12), 'kuehler')
        T['antrieb_luefter'] = (zusammen(luefter((0.0, 0.30, 0.47), 0.16), superellipsoid((0.0, 0.31, 0.47), (0.33, 0.010, 0.17), None, e1=0.2, e2=0.2)), 'kunststoff')
        bat = np.array([-0.15, ah + 0.45, 0.42])
        T['antrieb_batterie'] = (superellipsoid(bat, (0.09, 0.14, 0.095), None, e1=0.12, e2=0.12), 'kunststoff')
        rohre = [rohrzug([(s * 0.10, av + 0.20, 0.40), (s * 0.12, av + 0.55, 0.22), (s * 0.10, 2.4, 0.21), (s * 0.12, ah - 0.35, 0.24),
                          (s * 0.35, ah + 0.25, 0.29), (s * 0.40, K.L - 0.52, 0.30)], 0.024) for s in (1, -1)]
        toepfe = [superellipsoid((s * 0.42, K.L - 0.40, 0.30), (0.16, 0.13, 0.075), None, e1=0.3, e2=0.35) for s in (1, -1)]
        T['antrieb_auspuff'] = (zusammen(*rohre, *toepfe), 'stahl')
        T['antrieb_katalysator'] = (zusammen(superellipsoid((-0.12, av + 0.62, 0.24), (0.07, 0.14, 0.06), None, e1=0.5, e2=0.8),
                                             superellipsoid((-0.12, av + 0.62, 0.248), (0.09, 0.17, 0.074), None, e1=0.35, e2=0.5)), 'hitzeschild')
        T['antrieb_tank'] = (superellipsoid((0.0, ah - 0.62, 0.31), (0.40, 0.22, 0.11), None, e1=0.3, e2=0.25), 'kunststoff')
        rahmen = superellipsoid((0.0, ah - 0.05, 0.30), (0.52, 0.18, 0.04), None, e1=0.2, e2=0.18)
        lenker = []
        for s in (1, -1):
            for (dd, zz) in ((-0.20, 0.26), (-0.05, 0.40), (0.12, 0.27), (0.20, 0.36), (0.02, 0.22)):
                lenker.append(stab((s * 0.40, ah + dd * 0.6, zz), (s * (sh - 0.12), ah + dd * 0.25, za + (zz - 0.3) * 0.5), 0.013))
        T['fahrwerk_hinterachse'] = (zusammen(rahmen, *lenker), 'stahl')
        T['fahrwerk_federn_h'] = (zusammen(*[feder((s * 0.52, ah - 0.14, 0.29), (s * 0.52, ah - 0.14, 0.53), 0.058, 0.007, 5.5) for s in (1, -1)]), 'feder')
        T['fahrwerk_daempfer_h'] = (zusammen(*[stab((s * (sh - 0.17), ah + 0.08, 0.27), (s * (sh - 0.22), ah + 0.12, 0.64), 0.021) for s in (1, -1)]), 'stahl')
    # Vorderachse: McPherson-Federbeine, Dreieckslenker, Stabilisator, Lenkung
    beine = []; federn = []; lenker = []; lager = []
    for s in (1, -1):
        fuss = (s * (sv - 0.13), av + 0.01, za + 0.06)
        # Domlager unter der Haube: bei 0,84 m stachen die Federn im ersten
        # Poster als Chromringe durch das Blech.
        kopf = (s * (sv - 0.20), av + 0.05, 0.72)
        beine.append(stab(fuss, kopf, 0.024))
        beine.append(scheibe(kopf, np.asarray(kopf) - np.asarray(fuss), 0.085, 0.022))
        f0 = np.asarray(fuss) + (np.asarray(kopf) - fuss) * 0.35
        federn.append(feder(f0, kopf, 0.070, 0.0065, 4.5))
        # Dreieckslenker als flache Platte zwischen drei Punkten
        a = np.array([s * (sv - 0.12), av, za - 0.05]); b = np.array([s * 0.30, av - 0.05, za - 0.06]); c = np.array([s * 0.30, av + 0.28, za - 0.05])
        lenker += [stab(a, b, 0.018), stab(a, c, 0.018), stab(b, c, 0.012)]
        lager += [ring_um(b, 0.028, 0.010, (1, 0, 0), n=32, m=10), ring_um(c, 0.030, 0.011, (0, 1, 0), n=32, m=10)]
    T['fahrwerk_federbeine'] = (zusammen(*beine, *lenker), 'stahl')
    T['fahrwerk_federn_v'] = (zusammen(*federn), 'feder')
    T['fahrwerk_lager_v'] = (zusammen(*lager), 'gummi')
    stabi = rohrzug([(-(sv - 0.16), av + 0.10, za + 0.02), (-0.30, av + 0.16, za - 0.02), (0.30, av + 0.16, za - 0.02), ((sv - 0.16), av + 0.10, za + 0.02)], 0.011)
    T['fahrwerk_stabilisator'] = (stabi, 'feder')
    zahn = stab((-0.34, av + 0.08, za + 0.04), (0.34, av + 0.08, za + 0.04), 0.028)
    spur = [stab((s * 0.40, av + 0.08, za + 0.04), (s * (sv - 0.12), av + 0.12, za + 0.02), 0.010) for s in (1, -1)]
    lb = [balg((s * 0.34, av + 0.08, za + 0.04), (s * 0.44, av + 0.08, za + 0.04), 0.013, 0.030, falten=6) for s in (1, -1)]
    T['fahrwerk_lenkung'] = (zusammen(zahn, *spur), 'motor')
    T['fahrwerk_lenkbaelge'] = (zusammen(*lb), 'gummi')
    return T
