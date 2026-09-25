"""fz_karosserie.py -- Karosserie der beiden Serienfahrzeuge (nur numpy).

Innere Koordinaten: x quer (rechte Haelfte x >= 0), d laengs ab Vorderkante,
z hoch ab Boden. Blender bekommt (x, d - L/2, z): Front bei -Y, wie das
Konzeptauto, damit dasselbe Studio und dieselbe Kamera passen.

Eckmasse aus Datenblaettern (Stand 09/2026), jeweils Mitte der Klasse:
  Kleinwagen   VW Polo VI 4053/1751/1446, Rs 2564, Spur 1525/1505;
               Renault Clio (2026) 4116/1768/1451, Rs 2591, Ueberhang v 833 h 637
  Mittelklasse BMW 3er G20 4709/1827/1435, Rs 2851, cw 0,23-0,26;
               VW Passat B9 ~4917, Rs 2841; Mercedes C-Klasse W206 4893
Bauweise: selbsttragende Stahlkarosserie, Front quer eingebauter Motor
(Kleinwagen, MQB A0 / CMF-B) bzw. laengs (Mittelklasse, G20: MacPherson
vorn, Fuenflenker hinten). Daraus die Proportionen: kurzer Vorderwagen und
steile Front beim Kleinwagen, lange Haube und weit hinten stehende
Frontscheibe ("dash-to-axle") bei der Mittelklasse.
"""
import numpy as np
from fz_geom import pchip, catmull, glatt, sd_polygon, d_linie, Gitter, glatte_kurve, glaetten1d


def verrunden(pts, r, n=10):
    """Polygon mit Eckradien. r: Zahl oder Liste je Ecke."""
    pts = [np.asarray(p, float) for p in pts]
    k = len(pts)
    rr = r if isinstance(r, (list, tuple)) else [r] * k
    out = []
    for i in range(k):
        a, b, c = pts[i - 1], pts[i], pts[(i + 1) % k]
        u = a - b; v = c - b
        lu, lv = np.linalg.norm(u), np.linalg.norm(v)
        u /= lu; v /= lv
        w = np.arccos(np.clip(u @ v, -1, 1))
        if rr[i] <= 0 or w < 1e-3 or w > np.pi - 1e-3:
            out.append(b); continue
        t = min(rr[i] / np.tan(w / 2), 0.49 * lu, 0.49 * lv)
        r_eff = t * np.tan(w / 2)
        p1 = b + u * t; p2 = b + v * t
        mitte = b + (u + v) / np.linalg.norm(u + v) * (r_eff / np.sin(w / 2))
        a1 = np.arctan2(*(p1 - mitte)[::-1]); a2 = np.arctan2(*(p2 - mitte)[::-1])
        da = (a2 - a1 + np.pi) % (2 * np.pi) - np.pi
        for s in np.linspace(0, 1, n):
            out.append(mitte + r_eff * np.array([np.cos(a1 + da * s), np.sin(a1 + da * s)]))
    return np.array(out)


def fein(poly, schritt=0.004):
    """Polygon auf Kantenlaenge <= schritt nachverdichten."""
    out = []
    for i in range(len(poly)):
        a, b = poly[i], poly[(i + 1) % len(poly)]
        k = max(1, int(np.ceil(np.linalg.norm(b - a) / schritt)))
        for s in range(k):
            out.append(a + (b - a) * s / k)
    return np.array(out)


# =================================================================== Entwurf
ENTWURF = {
    # Zweite Fassung (23.09.2026, nach Uwes Rueckmeldung "zu kantig"): Formen
    # wie heutige Serienautos -- Bug in der Draufsicht stark verjuengt, Front-
    # scheibe flach (Kleinwagen 30, Mittelklasse 28 Grad zur Waagerechten),
    # Dachlinie nach hinten abfallend, Dach schmaler und staerker gewoelbt,
    # Flanken unten eingezogen, Schultern ueber den Raedern.
    # Frontscheibe (23.09.2026): Das Profil stieg vom Fuss weg erst steiler an
    # (Kleinwagen 22 -> 32 Grad) und hatte fuenf bis sieben Kruemmungswechsel.
    # Eine Scheibe mit Wendepunkten verzerrt jede Spiegelung -- der
    # Deckendiffusor lag im Poster als ausgefranste Wolke darin. Jetzt eine
    # Bezierkurve mit festen Tangenten an Wasserkasten und Dach.
    'kleinwagen': dict(
        name='Kleinwagen', L=4.070, B=1.760, H=1.450, rs=2.570, uv=0.830,
        spur=(1.520, 1.505), reifen=(0.205, 0.45, 17), backen=(0.008, 0.016),
        unten=[(0, .40), (.02, .35), (.07, .29), (.18, .235), (.40, .205), (.83, .185), (2.0, .175), (3.2, .185), (3.60, .215), (3.85, .26), (3.98, .31), (4.05, .37), (4.07, .42)],
        schulter=[(0, .52), (.05, .60), (.12, .665), (.30, .735), (.80, .815), (1.20, .878), (1.30, .895), (1.60, .925), (2.40, .955), (3.30, .985), (3.70, .975), (3.95, .93), (4.07, .84)],
        dach=[(0, .54), (.015, .60), (.05, .665), (.11, .715), (.25, .765), (.60, .832), (1.00, .888), (1.20, .915), (1.28, .930),
              # Frontscheibe: quadratische Bezierkurve, 34 -> 3 Grad, durchgehend konvex
              (1.458, 1.0463), (1.6251, 1.1477), (1.7813, 1.2343), (1.9267, 1.3061), (2.0613, 1.3631), (2.1851, 1.4052), (2.298, 1.4325),
              (2.40, 1.445), (2.65, 1.450), (3.00, 1.440), (3.30, 1.412), (3.55, 1.360), (3.70, 1.300), (3.80, 1.200), (3.92, 1.060), (4.00, .965), (4.05, .890), (4.07, .845)],
        breit=[(0, 0), (.004, .13), (.015, .28), (.035, .42), (.07, .55), (.12, .645), (.20, .725), (.32, .785), (.50, .830), (.75, .862), (.90, .870), (3.35, .870), (3.60, .858),
               (3.80, .825), (3.93, .765), (4.01, .68), (4.045, .56), (4.062, .38), (4.07, 0)],
        dachbreit=[(1.20, .76), (1.30, .745), (2.10, .620), (2.80, .640), (3.40, .620), (3.80, .600), (4.07, .55)],
        kabine=[(0, 0), (1.28, 0), (1.52, 1), (4.07, 1)],
    ),
    'mittelklasse': dict(
        name='Mittelklasse', L=4.760, B=1.830, H=1.440, rs=2.850, uv=0.860,
        spur=(1.590, 1.600), reifen=(0.225, 0.45, 18), backen=(0.006, 0.012),
        unten=[(0, .40), (.02, .35), (.07, .29), (.20, .23), (.45, .195), (.86, .170), (2.5, .160), (3.71, .170), (4.25, .205), (4.55, .27), (4.70, .34), (4.76, .42)],
        schulter=[(0, .52), (.05, .60), (.12, .66), (.45, .735), (.80, .785), (1.20, .84), (1.62, .905), (1.90, .925), (2.60, .94), (3.80, .975), (4.40, .985), (4.65, .965), (4.76, .90)],
        dach=[(0, .55), (.015, .61), (.06, .675), (.14, .72), (.35, .77), (.80, .838), (1.30, .905), (1.55, .935), (1.62, .948),
              # Frontscheibe: quadratische Bezierkurve, 33 -> 2 Grad, durchgehend konvex
              (1.7991, 1.0595), (1.9709, 1.1567), (2.1355, 1.2396), (2.2929, 1.3083), (2.443, 1.3626), (2.5859, 1.4027), (2.7216, 1.4285),
              (2.85, 1.440), (3.15, 1.435), (3.45, 1.405), (3.75, 1.33), (4.00, 1.21), (4.18, 1.12), (4.35, 1.085), (4.55, 1.068), (4.68, 1.055), (4.73, 1.02), (4.76, .97)],
        breit=[(0, 0), (.004, .14), (.015, .30), (.035, .45), (.07, .58), (.12, .67), (.20, .75), (.33, .815), (.52, .865), (.80, .895), (.95, .905), (3.65, .905), (3.95, .895),
               (4.25, .87), (4.48, .82), (4.62, .75), (4.70, .66), (4.735, .54), (4.752, .36), (4.76, 0)],
        dachbreit=[(1.50, .78), (1.62, .765), (2.45, .645), (3.20, .665), (3.70, .645), (4.20, .66), (4.76, .62)],
        kabine=[(0, 0), (1.62, 0), (1.90, 1), (4.05, 1), (4.35, 0), (4.76, 0)],
    ),
}

# Punkte je Zone im Querschnitt (ca. 10-12 mm)
SKALA = 1.0


def aufloesung(skala=1.0):
    """Netzdichte: 1,0 fuer Standbilder (Stationen 10 mm), kleiner fuer
    die Echtzeitfassung. Alle Index-Abstaende in den Merkmalen laufen ueber
    o(), damit sie physikalisch gleich bleiben."""
    global SKALA, N_BODEN, N_FLANKE, N_OBEN, N_DACH, N_ECKE, J_FLANKE, J_OBEN, J_DACH, J_ECKE, SCHRITT
    SKALA = skala
    N_BODEN, N_FLANKE, N_OBEN, N_DACH = max(8, round(20 * skala)), round(118 * skala), round(52 * skala), round(72 * skala)
    N_ECKE = max(5, round(10 * skala))           # Dachkante / Schulterradius des Deckels
    J_FLANKE = N_BODEN
    J_OBEN = N_BODEN + N_FLANKE
    J_DACH = J_OBEN + N_OBEN
    J_ECKE = J_DACH + N_ECKE
    SCHRITT = 0.010 / skala


def o(n):
    return int(round(n * SKALA))


aufloesung(1.0)
ANBAU_STIRN = {'kleinwagen': 1.28, 'mittelklasse': 1.62}


def weich_max(a, b, k):
    """Glattes Maximum (Uebergangsbreite ~k) statt Knick."""
    return 0.5 * (a + b + np.sqrt((a - b) ** 2 + k * k))


class Karosserie:
    def __init__(self, was):
        E = ENTWURF[was]
        self.was = was; self.E = E
        self.L = E['L']
        rb, rq, rz = E['reifen']
        self.R_REIFEN = rz * 0.0254 / 2 + rb * rq
        self.R_FELGE = rz * 0.0254 / 2
        self.achse = (E['uv'], E['uv'] + E['rs'])
        self.z_achse = self.R_REIFEN - 0.012            # eingefedert, Latsch 12 mm
        self.R_BOGEN = self.R_REIFEN + 0.036
        self.zu = glatte_kurve(E['unten'], 0.03); self.zs = glatte_kurve(E['schulter'], 0.04)
        self.zt = glatte_kurve(E['dach'], 0.025)
        self.wm = glatte_kurve(E['breit'], 0.03); self.wt = glatte_kurve(E['dachbreit'], 0.05, rand=0.05)
        self.kab = glatte_kurve(E['kabine'], 0.04, rand=0.02)
        L = E['L']; av, ah = self.achse
        self.formung = pchip([(0, 0), (0.32, 0), (0.55, 1), (L - 0.40, 1), (L - 0.18, 0), (L, 0)])
        # Radhaus-Ausbuchtung: flache Glocke ueber jedem Rad (Schultern)
        bv, bh = E.get('backen', (0.006, 0.012))
        dv = np.linspace(0, L, 400)
        glocke = lambda c, w: np.exp(-((dv - c) / w) ** 4)
        self.backen = pchip(list(zip(dv, bv * glocke(av, 0.52) + bh * glocke(ah, 0.62))))
        self.haubensicke = pchip([(0, 0), (0.25, 0), (0.45, 1), (E['kabine'][1][0] - 0.1, 1), (E['kabine'][1][0] + 0.05, 0), (L, 0)])
        self._stationen()
        self._gitter()

    # ------------------------------------------------------------ Querschnitt
    def querschnitt(self, d):
        zu, zs, zt = float(self.zu(d)), float(self.zs(d)), float(self.zt(d))
        wm_echt = float(self.wm(d))
        W0 = 0.62
        wm = max(wm_echt, W0)
        # weiche Maxima: ein hartes max() knickt die Flaeche dort, wo die
        # Kurven sich kreuzen -- im Zebra-Test eine Querlinie ueber die Haube.
        zs = weich_max(zs, zu + 0.06, 0.02)
        zt = weich_max(zt, zs + 0.008, 0.02)
        # Schulter rollt deutlich ein (ws), Boden weit eingezogen (wb): Beides
        # zusammen nimmt der Flanke das Kastenfoermige der ersten Fassung.
        ws = wm - 0.048 - 0.016 * float(glatt((zs - 0.8) / 0.2))
        wb = wm - 0.13
        zw = min(zu + 0.30, zs - 0.12)
        zw = max(zw, zu + 0.05)
        boden = np.c_[np.linspace(0, wb, N_BODEN, endpoint=False), np.full(N_BODEN, zu)]
        # Flanke: Schweller eingezogen, Sickenlinie unten, groesste Breite auf
        # Radmitte, darueber die Schulterkante ("Tornadolinie") mit dem Knick,
        # an dem das Licht bricht -- sie traegt die ganze Seitenansicht.
        # s blendet die Formung an Bug und Heck aus, wo der Querschnitt auf
        # einen Strich zulaeuft. f: Radhaus-Ausbuchtung (Schultern hinten).
        s = float(self.formung(d))
        f = float(self.backen(d))
        zc = zs - self.E.get('kante_abstand', 0.105)
        if zc < zw + 0.05:
            s *= float(glatt((zc - zw) / 0.05 + 1))
            zc = max(zc, zw + 0.02)
        x_c = wm - 0.020
        glatt_pts = [(wb, zu), (wm - 0.055, zu + 0.012), (wm - 0.030, zu + 0.042), (wm - 0.016, zu + 0.090),
                     (wm - 0.006, zu + 0.160), (wm, zw), (wm - 0.008, zw + (zc - zw) * .55), (wm - 0.018, zc - 0.012),
                     (wm - 0.022, zc), (wm - 0.029, zc + 0.014), (ws + 0.014, zs - 0.032), (ws, zs)]
        form_pts = [(wb, zu), (wm - 0.062 + f * .3, zu + 0.012), (wm - 0.036 + f * .6, zu + 0.045), (wm - 0.030 + f, zu + 0.100),
                    (wm - 0.012 + f, zu + 0.170), (wm + f, zw), (wm - 0.008 + f * .6, zw + (zc - zw) * .55), (x_c + 0.0025, zc - 0.012),
                    (x_c, zc), (x_c - 0.009, zc + 0.013), (ws + 0.014, zs - 0.030), (ws, zs)]
        pts = [(a[0] * (1 - s) + b[0] * s, a[1] * (1 - s) + b[1] * s) for a, b in zip(glatt_pts, form_pts)]
        # Punkte unterhalb des Bodens / oberhalb der Schulter vermeiden
        pts = [(x, min(max(z, zu), zs)) for x, z in pts]
        flanke = catmull(pts, N_FLANKE + 1)[:-1]
        k = float(self.kab(d))
        h = zt - zs
        # ---- Kabine: Seitenscheibe (Tumblehome) bis zur Dachkante, dort ein
        # Radius, dann das Dach mit Querwoelbung. Die Mitte liegt genau auf
        # zt (Gesamthoehe laut Datenblatt), die Woelbung senkt die Seiten.
        # Radius und Woelbung wachsen mit der Fensterhoehe: Am Scheibenfuss
        # und am Heck laeuft die Glasflaeche auf null aus -- ein fester
        # Radius warf dort im ersten Versuch eine Treppe in den Querschnitt.
        r = min(0.095, max(0.012, h * 0.30))
        wolb = 0.042 * float(glatt(h / 0.25))
        wt = min(float(self.wt(d)), ws - 0.04)
        z_holm = zt - wolb - r
        ob_k = catmull([(ws, zs), (ws - 0.004, zs + min(0.010, h * .08)), (wt + (ws - wt) * .52 + 0.014 * min(1, h / 0.2), zs + (z_holm - zs) * .5),
                        (wt, z_holm)], N_OBEN + 1)[:-1]
        ecke_k = catmull([(wt, z_holm)] + [(wt - r + r * np.cos(a), z_holm + r * np.sin(a)) for a in np.radians([28, 55, 75])]
                         + [(wt - r, z_holm + r)], N_ECKE + 1)[:-1]
        wr = wt - r
        dach_k = catmull([(wr * f, zt - wolb * f * f) for f in (1.0, 0.75, 0.5, 0.25, 0.0)], N_DACH - N_ECKE)
        # ---- Deckel (Haube, Kofferraum): Schulterradius, dann flach gewoelbt
        # zur Mitte; bei 56 % der Breite eine Sicke mit leicht erhabenem Dom.
        rs = 0.075
        z_aus = zs + 0.02 * np.tanh(h * 0.6 / 0.02)
        ob_d = catmull([(ws, zs), (ws - 0.004, zs + 0.007 * min(1, h / 0.02 + 0.3)), (ws - rs * .5, zs + (z_aus - zs) * .85),
                        (ws - rs, z_aus)], N_OBEN + 1)[:-1]
        w0 = ws - rs
        hs = float(self.haubensicke(d)) * s
        ecke_d = catmull([(w0, z_aus), (w0 - 0.022, z_aus + (zt - z_aus) * 0.2), (w0 - 0.045, z_aus + (zt - z_aus) * 0.35)], N_ECKE + 1)[:-1]
        dach_d = catmull([(w0 - 0.045, z_aus + (zt - z_aus) * 0.35), (0.62 * w0, zt - 0.009 - 0.002 * hs), (0.565 * w0, zt - 0.006 + 0.003 * hs),
                          (0.50 * w0, zt - 0.004 + 0.003 * hs), (0.25 * w0, zt - 0.001 + 0.001 * hs), (0, zt)], N_DACH - N_ECKE)
        ob_d, ob_k = ob_d, ob_k
        dk_d = np.vstack([ecke_d, dach_d]); dk_k = np.vstack([ecke_k, dach_k])
        q = np.vstack([boden, flanke, ob_d * (1 - k) + ob_k * k, dk_d * (1 - k) + dk_k * k])
        # Bug und Heck: Dort laeuft der Querschnitt auf einen Strich zu, und alle
        # Merkmale (Schwellerkante, Schulter, Deckelkante) liefen im ersten
        # Stand als Strahlen aus der Mitte ueber die Frontschuerze -- sichtbare
        # V-Linien. Deshalb dort in eine glatte Superellipse ueberblenden, mit
        # denselben Zonenanteilen, damit j seine Bedeutung behaelt.
        v = max(1 - float(glatt((d - 0.02) / 0.30)), 1 - float(glatt((self.L - d - 0.02) / 0.20)))
        if v > 1e-4:
            q = q * (1 - v) + self._superellipse(q, zu, zt, wm) * v
        # Querschnitt entlang j leicht glaetten (sigma ~ 8 mm): Die Catmull-Kurve
        # hat an jedem Kontrollpunkt einen Kruemmungssprung -- in Spiegelungen
        # laengs des Autos eine feine Knicklinie. Die Schulterkante bleibt als
        # Radius von knapp 1 cm stehen, wie ein echter Tiefziehradius.
        j0 = J_FLANKE
        q[j0:] = glaetten1d(q[j0:], 1.0)
        q[-1, 0] = 0.0
        if wm_echt < W0:
            q[:, 0] *= wm_echt / W0
        return q

    def _superellipse(self, q, zu, zt, wm, n=2.6):
        """Glatter Ersatzquerschnitt mit gleicher Punktverteilung je Zone."""
        seg = np.r_[0, np.cumsum(np.linalg.norm(np.diff(q, axis=0), axis=1))]
        anteil = seg / max(seg[-1], 1e-9)
        t = np.linspace(-np.pi / 2, np.pi / 2, 2001)
        zm, b = 0.5 * (zu + zt), 0.5 * (zt - zu)
        x = wm * np.abs(np.cos(t)) ** (2 / n)
        z = zm + b * np.sign(np.sin(t)) * np.abs(np.sin(t)) ** (2 / n)
        s = np.r_[0, np.cumsum(np.hypot(np.diff(x), np.diff(z)))]
        s /= s[-1]
        return np.c_[np.interp(anteil, s, x), np.interp(anteil, s, z)]

    def _stationen(self):
        """Laengsstationen gleichmaessig nach der steilsten Profilkurve: An
        Bug und Heck steht die Flaeche quer zur Laengsachse, dort braucht es
        viel dichtere Stationen als an der Flanke."""
        d = np.linspace(0, self.L, 20001)
        st = np.max(np.abs(np.stack([np.gradient(f(d), d) for f in (self.zu, self.zt, self.wm)])), 0)
        s = np.r_[0, np.cumsum(np.sqrt(1 + st[1:] ** 2) * np.diff(d))]
        n = int(s[-1] / SCHRITT)
        self.d = np.interp(np.linspace(0, s[-1], n + 1), s, d)

    def _gitter(self):
        Q = np.array([self.querschnitt(d) for d in self.d])      # (m, n, 2)
        P = np.stack([Q[..., 0], np.repeat(self.d[:, None], Q.shape[1], 1), Q[..., 1]], -1)
        self.G = Gitter(P)
        # Normalen am Bug/Heck (entarteter Querschnitt) nach aussen richten
        N = self.G.N
        mitte = np.array([0, self.L / 2, 0.6])
        falsch = ((P - mitte) * N).sum(-1) < 0
        if falsch.mean() > 0.5:
            self.G.N = -N
        # Auf der Mittelebene muss die Normale quer null sein -- sonst bricht
        # das Licht an der Spiegelnaht (sichtbare Mittellinie auf der Klappe).
        N = self.G.N
        mitte_x = np.abs(P[..., 0]) < 1e-6
        N[mitte_x, 0] = 0.0
        N /= np.maximum(np.linalg.norm(N, axis=-1, keepdims=True), 1e-12)
        self.G.N = N


def zone(IJ):
    j = IJ[:, 1]
    return np.where(j < J_FLANKE, 0, np.where(j < J_OBEN, 1, np.where(j < J_DACH, 2, 3)))


# =================================================================== Merkmale
class Form:
    """Vorzeichenbehaftete Region in einer Projektion.
    ansicht: 'seite' (d, z), 'vorn'/'hinten' (x, z), 'oben' (d, x).
    Beliebig mit & (Schnitt), | (Vereinigung), - (Differenz) verknuepfbar."""

    def __init__(self, fn):
        self.fn = fn

    @staticmethod
    def poly(ansicht, pts, r=0.0, spiegel=True):
        pts = np.asarray(pts, float)
        pl = verrunden(pts, r, n=8) if (np.any(np.asarray(r) > 0)) else pts
        def fn(S, IJ, N):
            x, d, z = np.abs(S[:, 0]) if spiegel else S[:, 0], S[:, 1], S[:, 2]
            q = {'seite': np.c_[d, z], 'vorn': np.c_[x, z], 'hinten': np.c_[x, z], 'oben': np.c_[d, x]}[ansicht]
            return sd_polygon(q, pl)
        f = Form(fn); f.umriss = (ansicht, pl)
        return f

    @staticmethod
    def kreis(ansicht, mitte, r):
        def fn(S, IJ, N):
            x, d, z = np.abs(S[:, 0]), S[:, 1], S[:, 2]
            q = {'seite': np.c_[d, z], 'vorn': np.c_[x, z], 'oben': np.c_[d, x]}[ansicht]
            return np.hypot(q[:, 0] - mitte[0], q[:, 1] - mitte[1]) - r
        return Form(fn)

    @staticmethod
    def halbraum(fn_wert):
        """Region fn_wert(S, IJ, N) < 0."""
        return Form(fn_wert)

    def __and__(self, o):
        return Form(lambda S, IJ, N: np.maximum(self.fn(S, IJ, N), o.fn(S, IJ, N)))

    def __or__(self, o):
        return Form(lambda S, IJ, N: np.minimum(self.fn(S, IJ, N), o.fn(S, IJ, N)))

    def __sub__(self, o):
        return Form(lambda S, IJ, N: np.maximum(self.fn(S, IJ, N), -o.fn(S, IJ, N)))

    def __call__(self, S, IJ, N=None):
        return self.fn(S, IJ, N)


def zone_form(j0, j1):
    """Region j0 <= j < j1 im Querschnittsindex (1 Index ~ 1 cm)."""
    return Form(lambda S, IJ, N: 0.01 * np.maximum(j0 - IJ[:, 1], IJ[:, 1] - j1))


def laengs(d0, d1):
    return Form(lambda S, IJ, N: np.maximum(d0 - S[:, 1], S[:, 1] - d1))


def hoehe(z0, z1):
    return Form(lambda S, IJ, N: np.maximum(z0 - S[:, 2], S[:, 2] - z1))


def quer(x0, x1):
    return Form(lambda S, IJ, N: np.maximum(x0 - np.abs(S[:, 0]), np.abs(S[:, 0]) - x1))


def merkmale(K):
    # Tueren: Vorder- und Hintertuer teilen sich EINE Kante (Fuge 4 mm).
    # Bis 23.09.2026 endete die Vordertuer 3,3 cm vor der Hintertuer; dazwischen
    # stand ein Streifen Karosserie mit zwei Fugen -- im Poster eine doppelte
    # weisse Linie. Bei echten Autos deckt die Tuer die B-Saeule ab.
    """Alle Konturen eines Autos. Reihenfolge = Einrast-Reihenfolge:
    zuerst die, deren Kante am meisten auffaellt (Radlauf, Fenster, Leuchten),
    dann die Fugen."""
    E = K.E; was = K.was
    av, ah = K.achse
    F = {}
    seite_zonen = zone_form(J_FLANKE - o(3), J_DACH)                 # Flanke + Fensterzone
    F['radlauf_v'] = Form.kreis('seite', (av, K.z_achse), K.R_BOGEN) & quer(0.42, 2)
    F['radlauf_h'] = Form.kreis('seite', (ah, K.z_achse), K.R_BOGEN) & quer(0.42, 2)
    # Fensteroberkante folgt dem Dachholm (Zone), die Polygone geben nur
    # Vorder-, Hinter- und Unterkante vor -- so passt sie zu jeder Dachlinie.
    fz = zone_form(J_OBEN + o(1), J_DACH - o(3))
    if was == 'kleinwagen':
        F['fenster'] = Form.poly('seite', [(1.62, .945), (2.26, .958), (2.98, .982), (3.28, 1.05), (3.02, 1.40), (3.0, 1.8), (2.55, 1.8), (2.20, 1.395), (1.52, 1.008)],
                                 r=[.02, 0, .03, .06, .06, 0, 0, .06, .03]) & fz
        F['b_saeule'] = laengs(2.285, 2.370) & zone_form(J_OBEN + o(2), J_DACH + o(1))
        F['teiler'] = laengs(2.985, 3.012) & zone_form(J_OBEN + o(2), J_DACH)
        F['frontscheibe'] = zone_form(J_ECKE - o(2), 999) & laengs(1.31, 2.08)
        F['heckscheibe'] = zone_form(J_ECKE + o(3), 999) & Form.poly('oben', [(3.66, -.1), (3.66, .58), (3.97, .58), (3.97, -.1)], r=[0, .05, .05, 0])
        F['scheinwerfer'] = Form.poly('vorn', [(.40, .620), (.62, .605), (.78, .625), (.87, .665), (.85, .715), (.62, .725), (.44, .705)], r=[.02, .08, .05, .02, .02, .06, .03]) \
            & Form.poly('seite', [(-.2, .50), (.36, .60), (.42, .66), (.40, .72), (-.2, .82)], r=[0, .04, .03, 0, 0]) & laengs(-1, .6)
        F['rueckleuchte'] = Form.poly('hinten', [(.44, .76), (.95, .76), (.95, .885), (.52, .885), (.42, .82)], r=[.02, 0, 0, .03, .03]) \
            & Form.poly('seite', [(3.82, .76), (4.3, .72), (4.3, .95), (3.86, .905), (3.78, .84)], r=[.03, 0, 0, .04, .03]) & laengs(3.5, 5)
        _u = Form.poly('vorn', [(-.1, .595), (.30, .60), (.36, .625), (.31, .665), (-.1, .672)], r=[0, .02, .02, .02, 0]); F['grill_o'] = _u & laengs(-1, .3); F['grill_o'].umriss = _u.umriss
        _u = Form.poly('vorn', [(-.1, .245), (.46, .245), (.54, .30), (.52, .412), (-.1, .412)], r=[0, .05, .05, .04, 0]); F['grill_u'] = _u & laengs(-1, .35); F['grill_u'].umriss = _u.umriss
        _u = Form.poly('vorn', [(.58, .26), (.68, .27), (.72, .39), (.64, .405), (.59, .36)], r=[.02, .02, .02, .02, .02]); F['einlass'] = _u & laengs(-1, .4); F['einlass'].umriss = _u.umriss
        F['lippe'] = hoehe(-1, .225) & laengs(-1, .60) & zone_form(J_FLANKE + o(2), 999)
        F['diffusor'] = hoehe(-1, .335) & laengs(K.L - 0.60, 9) & zone_form(J_FLANKE + o(2), 999)
        F['rueckstrahler'] = Form.poly('hinten', [(.56, .365), (.70, .365), (.70, .392), (.56, .392)], r=.008) & laengs(K.L - 0.3, 9)
        F['haube'] = Form.poly('oben', [(.12, -.1), (.13, .30), (.17, .50), (.26, .62), (.40, .72), (.60, .95), (1.27, .95), (1.27, -.1)], r=[0, 0, .08, .1, .1, 0, 0, 0]) & zone_form(J_OBEN + o(8), 999)
        F['tuer_v'] = Form.poly('seite', [(1.215, .262), (1.215, .89), (1.47, .965), (2.17, 1.40), (2.17, 1.8), (2.3215, 1.8), (2.3215, .262)], r=[.03, .03, .03, 0, 0, 0, .03]) & seite_zonen
        F['tuer_h'] = Form.poly('seite', [(2.3215, .262), (2.3215, 1.8), (3.018, 1.8), (3.018, .262)], r=[.03, 0, 0, .03]) & seite_zonen
        F['klappe'] = (Form.poly('hinten', [(-.1, .605), (.60, .605), (.65, .76), (.66, 1.5), (-.1, 1.5)], r=[0, .04, 0, 0, 0]) & laengs(3.95, 5)) \
            | (zone_form(J_DACH - o(3), 999) & laengs(3.58, 5))
        F['stoss_v'] = Form.poly('seite', [(-.3, -1), (.9, -1), (.9, .40), (.58, .52), (.42, .60), (.38, .64), (.30, .78), (-.3, .78)], r=[0, 0, 0, .05, .03, 0, 0, 0])
        F['stoss_h'] = Form.poly('seite', [(3.2, -1), (4.5, -1), (4.5, .605), (3.84, .605), (3.74, .56), (3.2, .40)], r=[0, 0, 0, .03, .05, 0])
    else:
        F['fenster'] = Form.poly('seite', [(1.86, .955), (2.53, .950), (3.40, .972), (4.05, 1.02), (3.70, 1.45), (3.5, 1.8), (2.9, 1.8), (2.52, 1.395), (1.77, 1.0)],
                                 r=[.02, 0, .05, .05, .10, 0, 0, .08, .03]) & fz
        F['b_saeule'] = laengs(2.500, 2.590) & zone_form(J_OBEN + o(2), J_DACH + o(1))
        F['teiler'] = laengs(3.43, 3.456) & zone_form(J_OBEN + o(2), J_DACH)
        F['frontscheibe'] = zone_form(J_ECKE - o(2), 999) & laengs(1.65, 2.48)
        F['heckscheibe'] = zone_form(J_ECKE + o(3), 999) & Form.poly('oben', [(3.80, -.1), (3.80, .58), (4.28, .60), (4.28, -.1)], r=[0, .06, .06, 0])
        F['scheinwerfer'] = Form.poly('vorn', [(.42, .600), (.64, .585), (.80, .600), (.89, .640), (.87, .690), (.64, .700), (.46, .685)], r=[.02, .08, .05, .02, .02, .06, .03]) \
            & Form.poly('seite', [(-.2, .50), (.38, .58), (.45, .64), (.43, .70), (-.2, .82)], r=[0, .04, .03, 0, 0]) & laengs(-1, .7)
        F['rueckleuchte'] = Form.poly('hinten', [(.40, .86), (.95, .85), (.95, .97), (.44, .985), (.37, .93)], r=[.02, 0, 0, .03, .03]) \
            & Form.poly('seite', [(4.46, .82), (5.2, .78), (5.2, 1.1), (4.52, 1.00), (4.42, .90)], r=[.03, 0, 0, .04, .03]) & laengs(4.2, 6)
        _u = Form.poly('vorn', [(-.1, .555), (.28, .555), (.34, .59), (.33, .66), (.28, .685), (-.1, .69)], r=[0, .03, .03, .03, .03, 0]); F['grill_o'] = _u & laengs(-1, .3); F['grill_o'].umriss = _u.umriss
        _u = Form.poly('vorn', [(-.1, .235), (.48, .235), (.58, .30), (.54, .412), (-.1, .412)], r=[0, .05, .05, .04, 0]); F['grill_u'] = _u & laengs(-1, .4); F['grill_u'].umriss = _u.umriss
        _u = Form.poly('vorn', [(.62, .25), (.72, .26), (.77, .38), (.68, .395), (.63, .35)], r=[.02, .02, .02, .02, .02]); F['einlass'] = _u & laengs(-1, .45); F['einlass'].umriss = _u.umriss
        F['lippe'] = hoehe(-1, .215) & laengs(-1, .65) & zone_form(J_FLANKE + o(2), 999)
        F['diffusor'] = hoehe(-1, .36) & laengs(K.L - 0.60, 9) & zone_form(J_FLANKE + o(2), 999)
        F['rueckstrahler'] = Form.poly('hinten', [(.58, .40), (.72, .40), (.72, .426), (.58, .426)], r=.008) & laengs(K.L - 0.3, 9)
        F['haube'] = Form.poly('oben', [(.13, -.1), (.14, .30), (.18, .50), (.28, .64), (.45, .74), (.65, .95), (1.60, .95), (1.60, -.1)], r=[0, 0, .08, .1, .1, 0, 0, 0]) & zone_form(J_OBEN + o(8), 999)
        F['tuer_v'] = Form.poly('seite', [(1.40, .255), (1.40, .87), (1.74, .99), (2.47, 1.40), (2.47, 1.8), (2.5465, 1.8), (2.5465, .255)], r=[.03, .03, .03, 0, 0, 0, .03]) & seite_zonen
        F['tuer_h'] = Form.poly('seite', [(2.5465, .255), (2.5465, 1.8), (3.445, 1.8), (3.445, .98), (3.50, .72), (3.40, .255)], r=[.03, 0, 0, .06, .12, .03]) & seite_zonen
        F['klappe'] = zone_form(J_DACH - o(3), 999) & Form.poly('oben', [(4.34, -.1), (4.34, .64), (4.48, .68), (4.90, .70), (4.90, -.1)], r=[0, .08, 0, 0, 0]) \
            | (Form.poly('hinten', [(-.1, .93), (.64, .93), (.68, 1.4), (-.1, 1.4)], r=[0, .04, 0, 0]) & laengs(4.62, 6))
        F['stoss_v'] = Form.poly('seite', [(-.3, -1), (.95, -1), (.95, .40), (.62, .52), (.48, .585), (.44, .63), (.35, .78), (-.3, .78)], r=[0, 0, 0, .05, .03, 0, 0, 0])
        # Stossfaenger hinten: Oberkante waagerecht auf 0,60 m vom Radlauf bis
        # zum Heck -- eine schraeg zur Leuchte laufende Fuge (erster Entwurf)
        # zerschnitt die Seitenwand und sah aus wie ein Unfallschaden.
        F['stoss_h'] = Form.poly('seite', [(4.02, -1), (5.3, -1), (5.3, .605), (4.28, .605), (4.12, .56), (4.04, .47)], r=[0, 0, 0, .04, .08, 0])
    # Fensterrahmen: 14 mm breites Band aussen um die Seitenscheiben --
    # Chromleiste (Mittelklasse) bzw. schwarze Dichtung (Kleinwagen).
    if was == 'kleinwagen':
        F['schweller'] = Form(lambda S, IJ, N: S[:, 2] - (K.zu(S[:, 1]) + 0.072)) & laengs(K.achse[0] + 0.33, K.achse[1] - 0.33) & zone_form(J_FLANKE, J_OBEN)
    fe = F['fenster']
    F['fensterrahmen'] = Form(lambda S, IJ, N, fe=fe: fe.fn(S, IJ, N) - 0.014) & zone_form(J_OBEN + o(1), J_DACH)
    return F


# Einrast-Reihenfolge: sichtbarste Kanten zuerst
REIHENFOLGE = ['radlauf_v', 'radlauf_h', 'scheinwerfer', 'rueckleuchte', 'fenster', 'fensterrahmen', 'frontscheibe', 'heckscheibe',
               'grill_o', 'grill_u', 'einlass', 'rueckstrahler', 'haube', 'klappe', 'tuer_v', 'tuer_h', 'b_saeule', 'teiler', 'stoss_v', 'stoss_h', 'lippe', 'diffusor', 'schweller']

# Fugen: Kanten, die als echter Spalt mit Falz gebaut werden
FUGEN = ['haube', 'klappe', 'tuer_v', 'tuer_h', 'stoss_v', 'stoss_h']

ETIKETTEN = []


def etikett(teil, stoff):
    k = (teil, stoff)
    if k not in ETIKETTEN:
        ETIKETTEN.append(k)
    return ETIKETTEN.index(k)


def klassifizieren(K, F, S, IJ, N):
    """Teil und Material je Dreieck, nach Vorrang."""
    w = {k: f(S, IJ, N) < 0 for k, f in F.items()}
    zn = zone(IJ)
    T = np.full(len(S), '', object); M = np.full(len(S), 'lack', object)
    T[:] = 'karosserie'
    T[w['stoss_v']] = 'stoss_v'; T[w['stoss_h']] = 'stoss_h'
    T[w['tuer_v']] = 'tuer_v'; T[w['tuer_h']] = 'tuer_h'
    T[w['haube']] = 'haube'; T[w['klappe']] = 'klappe'
    M[zn == 0] = 'unterboden'; T[zn == 0] = 'boden'
    # Fensterzone: alles, was nicht Glas ist, ist dort Rahmen (schwarz)
    glaszone = (zn == 2) & np.isin(T, ['tuer_v', 'tuer_h', 'karosserie'])
    # Kleinwagen: matte EPDM-Dichtung statt Klavierlack. Glanzschwarz spiegelte
    # den Deckendiffusor als weisses Band ueber der Scheibe (Nahprobe
    # 23.09.2026: in der Pruefansicht ohne Glas blieb der Fleck -- es war
    # nicht das Glas, sondern die Rahmenflaeche, die nach oben schaut).
    M[glaszone & w['fensterrahmen'] & ~w['fenster']] = 'dichtung' if K.was == 'kleinwagen' else 'zier'
    M[glaszone & w['fenster']] = 'glas'
    # Spiegeldreieck: das Feld zwischen Tuervorderkante und Scheibe traegt bei
    # jedem Serienauto den Spiegelfuss auf genarbtem Schwarz -- in Wagenfarbe
    # stand es als heller Fleck neben der A-Saeule.
    d_dreieck = {'kleinwagen': 1.74, 'mittelklasse': 2.00}[K.was]
    M[glaszone & (T == 'tuer_v') & ~w['fenster'] & ~w['fensterrahmen'] & (S[:, 1] < d_dreieck)] = 'kunststoff'
    M[w['b_saeule']] = 'rahmen'; M[w['teiler'] & w['fenster']] = 'rahmen'
    M[w['frontscheibe']] = 'frontglas'; T[w['frontscheibe']] = 'frontscheibe'
    # Siebdruckrand (Keramikfritte) der Frontscheibe: unten 70 mm, seitlich
    # und oben 35 mm -- verdeckt Klebenaht und Wasserkasten, bei jeder
    # geklebten Scheibe vorhanden.
    fs0, fs1 = {'kleinwagen': (1.31, 2.08), 'mittelklasse': (1.65, 2.48)}[K.was]
    rand_fs = (S[:, 1] < fs0 + 0.07) | (S[:, 1] > fs1 - 0.035) | (IJ[:, 1] < J_ECKE - o(2) + o(4))
    M[w['frontscheibe'] & rand_fs] = 'fritte'
    # Wasserkasten unter der Frontscheibe
    wk = (zn == 3) & (S[:, 1] > K.E['uv'] + 0.2) & (S[:, 1] < {'kleinwagen': 1.31, 'mittelklasse': 1.65}[K.was]) & ~w['haube']
    M[wk] = 'kunststoff'; T[wk] = 'karosserie'
    M[w['heckscheibe']] = 'glas'
    hs0, hs1 = {'kleinwagen': (3.66, 3.97), 'mittelklasse': (3.80, 4.28)}[K.was]
    rand_hs = (S[:, 1] < hs0 + 0.035) | (S[:, 1] > hs1 - 0.035) | (np.abs(S[:, 0]) > 0.545) | (IJ[:, 1] < J_ECKE + o(3) + o(3))
    M[w['heckscheibe'] & rand_hs] = 'fritte'
    M[w['grill_o']] = 'grill'; T[w['grill_o']] = 'grill_o'
    M[w['grill_u']] = 'grill'; T[w['grill_u']] = 'grill_u'
    M[w['einlass']] = 'grill'; T[w['einlass']] = 'einlass'
    unlack = (w['lippe'] | w['diffusor']) & np.isin(T, ['stoss_v', 'stoss_h'])
    # Kleinwagen: schwarze Schwellerleiste unter der unteren Sicke -- sie
    # nimmt dem hohen Seitenblech optisch Hoehe, wie bei den Vorbildern.
    if 'schweller' in w:
        M[w['schweller'] & (zn == 1) & np.isin(T, ['karosserie', 'tuer_v', 'tuer_h'])] = 'kunststoff'
    M[unlack] = 'kunststoff'
    M[w['rueckstrahler']] = 'rueckstrahler'
    M[w['scheinwerfer']] = 'linse'; T[w['scheinwerfer']] = 'scheinwerfer'
    M[w['rueckleuchte']] = 'linse_rot'; T[w['rueckleuchte']] = 'rueckleuchte'
    weg = w['radlauf_v'] | w['radlauf_h']
    lab = np.array([etikett(t, m) for t, m in zip(T, M)])
    lab[weg] = -1
    return lab


def bauen(was, log=print):
    K = Karosserie(was)
    F = merkmale(K)
    for name in REIHENFOLGE:
        if name not in F:
            continue
        n = K.G.einrasten(name, F[name].fn)
        log(f'  eingerastet {name}: {n}')
    T, lab = K.G.dreiecke(lambda S, IJ, N: klassifizieren(K, F, S, IJ, N))
    return K, F, T, lab
