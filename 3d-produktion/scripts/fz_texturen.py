"""fz_texturen.py -- kachelbare Materialtexturen fuer Innenraum und Lack.

Laeuft in Python mit numpy und Pillow (nicht in Blender): Die Bilder werden
einmal erzeugt, nach 3d-produktion/branchen/quelle/tex/ gelegt und von
fahrzeug_bau.py als Bildtexturen eingebunden. Bildtexturen statt Blender-
Knoten, weil das Web dieselben Oberflaechen braucht -- glTF kennt keine
prozeduralen Knoten.

Alle Karten sind nahtlos kachelbar (periodisches Rauschen ueber FFT,
Muster mit ganzzahligen Perioden). Normalkarten im OpenGL-Format (+Y oben),
wie glTF und Blenders Normal-Map-Knoten sie erwarten.

MASSSTAB (eine Kachel = so viele Meter; steht in MASSSTAB unten)
  stoff    Webstoff, Koeper 2/2, Fadenabstand 0,6 mm      -> 0,05 m
  leder    Narbung Rindleder, Zellen 0,8-2 mm             -> 0,08 m
  narbung  Kunststoffnarbung (Armaturentafel), 0,3-0,6 mm -> 0,06 m
  lochung  Perforation 1,2 mm Loch, 5 mm Raster            -> 0,04 m
  teppich  Nadelfilz                                       -> 0,10 m
  holz     offenporige Walnuss, laengs                     -> 0,40 m
  alu      gebuerstet, laengs                              -> 0,20 m
  lautsprecher Lochblech 2 mm / 3,5 mm                     -> 0,06 m
"""
import os, sys
import numpy as np
from PIL import Image

MASSSTAB = {'stoff': 0.05, 'leder': 0.08, 'narbung': 0.06, 'lochung': 0.04, 'teppich': 0.10,
            'holz': 0.40, 'alu': 0.20, 'lautsprecher': 0.06, 'flocken': 0.02}
RNG = np.random.default_rng(20260923)


def rauschen(n, glatt_px, rng=RNG):
    """Periodisches Gauss-Rauschen (nahtlos), Standardabweichung 1."""
    w = rng.standard_normal((n, n))
    f = np.fft.fftfreq(n)
    k2 = f[:, None] ** 2 + f[None, :] ** 2
    F = np.fft.fft2(w) * np.exp(-2 * (np.pi * glatt_px) ** 2 * k2)
    r = np.real(np.fft.ifft2(F))
    return (r - r.mean()) / (r.std() + 1e-12)


def zellen(n, zahl, rng=RNG):
    """Periodische Voronoi-Abstandsfelder (F1, F2) fuer Ledernarbung."""
    p = rng.random((zahl, 2)) * n
    yy, xx = np.mgrid[0:n, 0:n].astype(np.float32)
    f1 = np.full((n, n), 1e9, np.float32); f2 = np.full((n, n), 1e9, np.float32)
    for (px, py) in p:
        for ox in (-n, 0, n):
            for oy in (-n, 0, n):
                d = (xx - px - ox) ** 2 + (yy - py - oy) ** 2
                neu1 = np.minimum(f1, d)
                f2 = np.where(d < f1, f1, np.minimum(f2, d))
                f1 = neu1
    return np.sqrt(f1), np.sqrt(f2)


def normalkarte(h, staerke):
    """Hoehenfeld (in Pixeln gleich skaliert) -> Normalkarte RGB 8 Bit, OpenGL."""
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 0.5 * staerke
    gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 0.5 * staerke
    # Bildzeilen laufen nach unten, +Y der Normalkarte nach oben
    n = np.stack([-gx, gy, np.ones_like(h)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    return np.clip((n * 0.5 + 0.5) * 255 + 0.5, 0, 255).astype(np.uint8)


def grau(a):
    return np.clip(a * 255 + 0.5, 0, 255).astype(np.uint8)


def speichern(ziel, name, arr):
    Image.fromarray(arr).save(os.path.join(ziel, name + '.png'), optimize=True)


def stoff(n=1024):
    """Koeper 2/2: Kett- und Schussfaeden abwechselnd oben, diagonal versetzt.
    Periode 16 px = ein Faden (0,05 m / 1024 * 16 = 0,78 mm)."""
    yy, xx = np.mgrid[0:n, 0:n]
    p = 16
    fx = (xx % p) / p; fy = (yy % p) / p
    kx = xx // p; ky = yy // p
    oben_kette = ((kx + ky) // 2) % 2 == 0          # Koeper: Versatz um einen Faden je Reihe
    faden_kette = np.sin(np.pi * fx) ** 0.6          # runder Faden quer
    faden_schuss = np.sin(np.pi * fy) ** 0.6
    h = np.where(oben_kette, 0.55 + 0.45 * faden_kette, 0.55 + 0.45 * faden_schuss)
    # Fasern: feines, laengs gestrecktes Rauschen, und Garnunruhe
    h = h + 0.10 * rauschen(n, 1.2) + 0.08 * rauschen(n, 6)
    farbe = 0.84 + 0.10 * (h - h.mean()) / h.std() * 0.5 + 0.05 * rauschen(n, 24)   # Melange
    return normalkarte(h, 2.2), grau(np.clip(farbe, 0.6, 1.0))


def leder(n=1024):
    """Narbung (Rindleder, "pebble grain"): gewoelbte Zellen, weiche Furchen.
    Erste Fassung nahm die Voronoi-Kanten direkt -- das sah aus wie ein
    gesprungener Fliesenboden. Echte Narbung ist rund: Hoehe aus dem Abstand
    zur Zellmitte (Kuppe) und eine breite, weiche Furche, danach 1,5 px
    geglaettet."""
    f1, f2 = zellen(n, 1100)
    mittel = np.sqrt(n * n / 1100) * 0.5
    kuppe = 1 - np.clip(f1 / mittel, 0, 1.4) ** 2
    furche = np.exp(-((f2 - f1) / 3.5) ** 2)
    h = 0.7 * kuppe - 0.8 * furche + 0.12 * rauschen(n, 1.0)
    F = np.fft.fft2(h); fr = np.fft.fftfreq(n)
    h = np.real(np.fft.ifft2(F * np.exp(-2 * (np.pi * 1.5) ** 2 * (fr[:, None] ** 2 + fr[None, :] ** 2))))
    rau = 0.40 + 0.10 * furche + 0.03 * rauschen(n, 6)
    farbe = 0.95 + 0.025 * rauschen(n, 40) - 0.05 * furche
    return normalkarte(h, 1.1), grau(np.clip(rau, 0, 1)), grau(np.clip(farbe, 0, 1))


def lederloch(n=1024):
    """Gelochtes Leder: Narbung wie leder(), dazu Loecher 1,2 mm im 5-mm-
    Raster (16 x 16 je 0,08-m-Kachel, versetzt). Loecher dunkel (Farbkarte)
    und als Mulde (Normalkarte)."""
    f1, f2 = zellen(n, 1100)
    mittel = np.sqrt(n * n / 1100) * 0.5
    kuppe = 1 - np.clip(f1 / mittel, 0, 1.4) ** 2
    furche = np.exp(-((f2 - f1) / 3.5) ** 2)
    h = 0.7 * kuppe - 0.8 * furche
    yy, xx = np.mgrid[0:n, 0:n].astype(float)
    p = n / 16
    gy = np.round(yy / p); cy = gy * p
    cx = np.round((xx - (gy % 2) * p / 2) / p) * p + (gy % 2) * p / 2
    r = np.hypot(xx - cx, yy - cy)
    loch = np.clip(1 - r / (n / 0.08 * 0.0006), 0, 1)
    rand = np.exp(-((r - n / 0.08 * 0.0006) / 1.5) ** 2)
    h = h - 6 * loch ** 0.5 + 0.8 * rand
    F = np.fft.fft2(h); fr = np.fft.fftfreq(n)
    h = np.real(np.fft.ifft2(F * np.exp(-2 * (np.pi * 1.0) ** 2 * (fr[:, None] ** 2 + fr[None, :] ** 2))))
    farbe = (0.95 - 0.05 * furche) * (1 - 0.9 * (loch > 0.15))
    rau = 0.42 + 0.1 * furche + 0.3 * (loch > 0.15)
    return normalkarte(h, 1.0), grau(np.clip(farbe, 0, 1)), grau(np.clip(rau, 0, 1))


def narbung(n=1024):
    """Genarbte Kunststoffhaut (Armaturentafel): feiner als Leder, flacher."""
    f1, f2 = zellen(n, 2600)
    h = -np.exp(-((f2 - f1) / 1.6) ** 2) + 0.35 * rauschen(n, 1.5)
    rau = 0.62 + 0.06 * rauschen(n, 3)
    return normalkarte(h, 0.9), grau(np.clip(rau, 0, 1))


def lochung(n=512):
    """Perforation: Loecher 1,2 mm im 5-mm-Raster, versetzt (0,04 m Kachel,
    8 x 8 Loecher). Rueckgabe: Normalkarte und Hoehlen-Abdunklung."""
    yy, xx = np.mgrid[0:n, 0:n].astype(float)
    p = n / 8
    loch = np.zeros((n, n))
    for oy in range(9):
        for ox in range(9):
            cx = ox * p + (p / 2 if oy % 2 else 0); cy = oy * p
            for sx in (-n, 0, n):
                for sy in (-n, 0, n):
                    r = np.hypot(xx - cx - sx, yy - cy - sy)
                    loch = np.maximum(loch, np.clip(1 - r / (p * 0.12), 0, 1))
    h = -loch ** 0.5
    return normalkarte(h, 3.0), grau(1 - 0.85 * (loch > 0.05))


def teppich(n=1024):
    h = rauschen(n, 0.8) * 0.6 + rauschen(n, 3) * 0.3 + rauschen(n, 12) * 0.2
    farbe = 0.9 + 0.08 * rauschen(n, 2)
    return normalkarte(h, 1.5), grau(np.clip(farbe, 0, 1))


def holz(n=1024, art='walnuss'):
    """Offenporiges Furnier, Maserung laengs (x). Jahresringe mit weichem
    Fruehholz und scharfem Uebergang zum dunklen Spaetholz (Saegezahn statt
    Sinus -- der Sinus der ersten Fassung sah aus wie ein Farbverlauf),
    leicht wellig verzogen; Poren als kurze, laengs gestreckte Striche."""
    yy, xx = np.mgrid[0:n, 0:n].astype(float)
    # Verzug: laengs sehr langwellig (ganze Kachel), quer maessig -- die
    # erste Fassung war rundum wellig und sah aus wie Sandrippeln.
    wv = RNG.standard_normal((n, n)); Fv = np.fft.fft2(wv)
    fx_ = np.fft.fftfreq(n)[None, :]; fy_ = np.fft.fftfreq(n)[:, None]
    v = np.real(np.fft.ifft2(Fv * np.exp(-2 * (np.pi ** 2) * (fx_ ** 2 * 260 ** 2 + fy_ ** 2 * 40 ** 2))))
    verzug = 26 * v / v.std() + 1.0 * rauschen(n, 4)
    ringe = np.zeros((n, n))
    for periode, gew in ((n / 64, 0.25), (n / 23, 0.5), (n / 9, 0.25)):
        ph = ((yy + verzug) / periode) % 1.0
        ringe += gew * np.where(ph < 0.82, ph / 0.82, 1 - (ph - 0.82) / 0.18) ** 2.2
    w = RNG.standard_normal((n, n)); F = np.fft.fft2(w)
    fx = np.fft.fftfreq(n)[None, :]; fy = np.fft.fftfreq(n)[:, None]
    p = np.real(np.fft.ifft2(F * np.exp(-2 * (np.pi ** 2) * (fx ** 2 * 7 ** 2 + fy ** 2 * 0.5 ** 2))))
    p = (p - p.mean()) / p.std()
    poren = np.clip((p - 1.9) * 1.5, 0, 1)
    if art == 'walnuss':
        hell, dunkel = np.array([0.23, 0.12, 0.06]), np.array([0.075, 0.035, 0.017])
    else:  # Esche, grau gebeizt
        hell, dunkel = np.array([0.30, 0.29, 0.27]), np.array([0.11, 0.105, 0.10])
    t = np.clip(0.25 + 0.6 * ringe + 0.08 * rauschen(n, 3) + 0.1 * rauschen(n, 40), 0, 1)[..., None]
    farbe = dunkel + (hell - dunkel) * t
    farbe = farbe * (1 - 0.55 * poren[..., None])
    h = -poren + 0.15 * ringe
    srgb = np.where(farbe <= 0.0031308, farbe * 12.92, 1.055 * np.power(farbe, 1 / 2.4) - 0.055)
    return grau(np.clip(srgb, 0, 1)), normalkarte(h, 2.0)


def alu(n=1024):
    """Gebuerstet: Rauheit und Normalen als lange Striche entlang u."""
    w = RNG.standard_normal((n, n))
    F = np.fft.fft2(w)
    fx = np.fft.fftfreq(n)[None, :]; fy = np.fft.fftfreq(n)[:, None]
    Fp = F * np.exp(-2 * (np.pi ** 2) * (fx ** 2 * 80 ** 2 + fy ** 2 * 0.5 ** 2))
    s = np.real(np.fft.ifft2(Fp)); s = (s - s.mean()) / s.std()
    rau = 0.28 + 0.06 * s
    return grau(np.clip(rau, 0, 1)), normalkarte(s * 0.4, 1.0)


def lautsprecher(n=512):
    yy, xx = np.mgrid[0:n, 0:n].astype(float)
    p = n / 16
    loch = np.zeros((n, n))
    for oy in range(17):
        for ox in range(17):
            cx = ox * p + (p / 2 if oy % 2 else 0); cy = oy * p * 1.0
            for sx in (-n, 0, n):
                r = np.hypot(xx - cx - sx, yy - cy)
                loch = np.maximum(loch, np.clip(1 - r / (p * 0.30), 0, 1))
    loch = np.maximum(loch, np.roll(loch, n // 2, 0) * 0)
    h = -np.clip(loch * 3, 0, 1)
    return normalkarte(h, 4.0), grau(1 - 0.92 * (loch > 0.02))


def flocken(n=1024):
    """Metallic-Effektpigment: einzelne Aluminiumplaettchen (0,02 m Kachel,
    Plaettchen ~20 um -> hier 1 px), jedes mit eigener Neigung. Nur fuer
    Cycles-Nahaufnahmen; im Web zu fein, dort nicht verwendet."""
    nx = RNG.normal(0, 0.35, (n, n)); ny = RNG.normal(0, 0.35, (n, n))
    maske = RNG.random((n, n)) < 0.35
    nx *= maske; ny *= maske
    nn = np.stack([nx, ny, np.ones_like(nx)], -1)
    nn /= np.linalg.norm(nn, axis=-1, keepdims=True)
    return np.clip((nn * 0.5 + 0.5) * 255 + 0.5, 0, 255).astype(np.uint8)


# ------------------------------------------------------------ Reifenflanke
REIFEN = {'kleinwagen': (0.205, 0.45, 17, '91W'), 'mittelklasse': (0.225, 0.45, 18, '94Y')}


def reifen(was, breite=4096, hoehe=512, schrift_datei=None):
    """Normalkarte der Reifenflanke: erhabene Schrift (0,8 mm), feine
    Ringrippen am Felgenschutz. Kein Hersteller -- nur Groesse, Tragfaehigkeit
    und eine neutrale Profilbezeichnung, wie auf jedem Reifen.

    UV (fahrzeug_bau.py, rad_teile): u = Umfang (0..1 = eine Umdrehung),
    v = 0,5 * (r - R_Felge) / Flankenhoehe auf der Aussenflanke, auf der
    Innenflanke gespiegelt nach 0,5..1. Die Karte ist also nur in der unteren
    Haelfte beschrieben; Zeile 0 ist oben (v = 1)."""
    from PIL import ImageDraw, ImageFont
    b, q, zoll, index = REIFEN[was]
    Rf = zoll * 0.0254 / 2; flanke = b * q
    mm_v = hoehe / 2 / (flanke * 1000)                      # Pixel je mm radial
    def zeile(rho):                                         # rho 0 Felge .. 1 Lauf
        return (1 - 0.5 * rho) * hoehe
    h = np.zeros((hoehe, breite), np.float64)
    # Felgenschutz: konzentrische Rippen 1,2 mm Teilung, 0,12 mm hoch
    y = np.arange(hoehe)[:, None]
    rho = np.clip(2 * (1 - (y + 0.5) / hoehe), 0, 1)
    r_mm = rho * flanke * 1000
    rippen = (rho > 0.10) & (rho < 0.24)
    h += np.where(rippen, 0.12 * (0.5 + 0.5 * np.cos(2 * np.pi * r_mm / 1.2)), 0.0)
    # Schrift: isotrop in mm zeichnen, dann auf den Umfang stauchen
    datei = schrift_datei or os.environ.get('INTER_TTF', '/tmp/claude-0/inter-latin.ttf')
    def schreiben(txt, rho_mitte, hoehe_mm, u_mitte, gewicht=800, abstand=0.18):
        r = Rf + rho_mitte * flanke
        mm_u = breite / (2 * np.pi * r * 1000)              # Pixel je mm am Umfang
        px = int(hoehe_mm * mm_v * 1.38)
        f = ImageFont.truetype(datei, px)
        try:
            f.set_variation_by_axes([gewicht])
        except Exception:
            pass
        zeichen = []
        for c in txt:
            l, t, rr, bb = f.getbbox(c)
            zeichen.append((c, rr - l if c != ' ' else px * 0.35))
        w = int(sum(z[1] for z in zeichen) + abstand * px * len(txt)) + 8
        im = Image.new('L', (w, int(px * 1.5)), 0); d = ImageDraw.Draw(im)
        x = 4
        for c, cw in zeichen:
            d.text((x, im.height / 2), c, font=f, fill=255, anchor='lm'); x += cw + abstand * px
        im = im.resize((max(1, int(w * mm_u / mm_v)), im.height), Image.LANCZOS)
        a = np.asarray(im, np.float64) / 255
        y0 = int(zeile(rho_mitte) - a.shape[0] / 2); x0 = int(u_mitte * breite - a.shape[1] / 2)
        for k in range(a.shape[1]):
            xx = (x0 + k) % breite
            h[y0:y0 + a.shape[0], xx] = np.maximum(h[y0:y0 + a.shape[0], xx], a[:, k] * 0.8)
    groesse = f"{int(round(b * 1000))}/{int(round(q * 100))} R{zoll} {index}"
    for u0 in (0.0, 0.5):
        schreiben('TOURING SPORT', 0.56, 15, u0 + 0.12)
        schreiben(groesse, 0.56, 15, u0 + 0.36)
        schreiben('RADIAL  TUBELESS', 0.34, 6, u0 + 0.12, 600)
        schreiben('MADE IN EU', 0.34, 6, u0 + 0.36, 600)
    # Fase der Buchstaben (~0,5 mm) statt Stufenkante
    from PIL import ImageFilter
    hi = Image.fromarray(np.clip(h / 0.8 * 255, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.2))
    h = np.asarray(hi, np.float64) / 255 * 0.8
    mm_u_px = breite / (2 * np.pi * (Rf + 0.5 * flanke) * 1000)
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 0.5 * mm_u_px
    gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 0.5 * mm_v
    gy[0] = gy[-1] = 0
    n = np.stack([-gx, gy, np.ones_like(h)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    return np.clip((n * 0.5 + 0.5) * 255 + 0.5, 0, 255).astype(np.uint8)


def alle(ziel):
    os.makedirs(ziel, exist_ok=True)
    n, f = stoff(); speichern(ziel, 'innen-stoff-normal', n); speichern(ziel, 'innen-stoff-farbe', f)
    n, r, f = leder(); speichern(ziel, 'innen-leder-normal', n); speichern(ziel, 'innen-leder-rau', r); speichern(ziel, 'innen-leder-farbe', f)
    n, r = narbung(); speichern(ziel, 'innen-narbung-normal', n); speichern(ziel, 'innen-narbung-rau', r)
    n, a = lochung(); speichern(ziel, 'innen-lochung-normal', n); speichern(ziel, 'innen-lochung-farbe', a)
    n, f, r = lederloch(); speichern(ziel, 'innen-lederloch-normal', n); speichern(ziel, 'innen-lederloch-farbe', f); speichern(ziel, 'innen-lederloch-rau', r)
    n, f = teppich(); speichern(ziel, 'innen-teppich-normal', n); speichern(ziel, 'innen-teppich-farbe', f)
    f, n = holz(art='walnuss'); speichern(ziel, 'innen-walnuss-farbe', f); speichern(ziel, 'innen-walnuss-normal', n)
    f, n = holz(art='esche'); speichern(ziel, 'innen-esche-farbe', f); speichern(ziel, 'innen-esche-normal', n)
    r, n = alu(); speichern(ziel, 'innen-alu-rau', r); speichern(ziel, 'innen-alu-normal', n)
    n, a = lautsprecher(); speichern(ziel, 'innen-lautsprecher-normal', n); speichern(ziel, 'innen-lautsprecher-farbe', a)
    speichern(ziel, 'lack-flocken-normal', flocken())
    for was in REIFEN:
        speichern(ziel, f'reifen-{was}-normal', reifen(was))


if __name__ == '__main__':
    alle(sys.argv[1] if len(sys.argv) > 1 else 'tex')
