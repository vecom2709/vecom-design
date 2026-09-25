"""pr_texturen.py -- Bildtexturen fuer die Produktdemos (Pillow, nicht Blender).

Etiketten der Weinflasche: je Produkt eine Farbkarte (Papier + Druck) und
eine Metall/Rauheit-Karte im glTF-Format (G = Rauheit, B = Metall). So ist
die Goldpraegung im Web und in Cycles wirklich Metall -- ein gelb
gedrucktes "Gold" erkennt jedes Auge sofort als Druck.

Keine fremden Marken: Die Kellerei ist Vecom Design selbst (Musteretikett),
Rebsorten und Herkunftsangaben sind Gattungsbegriffe.

Aufruf: python3 pr_texturen.py <zielordner>
"""
import math, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

LORA = '/usr/share/fonts/truetype/google-fonts/Lora-Variable.ttf'
LORA_I = '/usr/share/fonts/truetype/google-fonts/Lora-Italic-Variable.ttf'
RNG = np.random.default_rng(20260924)


def schrift(pfad, px, gewicht=None):
    f = ImageFont.truetype(pfad, int(px))
    if gewicht:
        try:
            f.set_variation_by_axes([gewicht])
        except Exception:
            pass
    return f


def rauschen(h, w, glatt, rng=RNG):
    a = rng.standard_normal((h, w))
    im = Image.fromarray(((a - a.min()) / (np.ptp(a) + 1e-9) * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(glatt))
    x = np.asarray(im).astype(np.float64) / 255
    return (x - x.mean()) / (x.std() + 1e-9)


def papier(h, w, farbe):
    """Buettenpapier: Grundton, Fasern (laengliches Rauschen), leichte Wolken."""
    fas = rauschen(h, w // 3, 1.2)
    fas = np.asarray(Image.fromarray(((fas - fas.min()) / np.ptp(fas) * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC)).astype(float) / 255 - 0.5
    wolke = rauschen(h // 8, w // 8, 3)
    wolke = np.asarray(Image.fromarray(((wolke - wolke.min()) / np.ptp(wolke) * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC)).astype(float) / 255 - 0.5
    korn = RNG.standard_normal((h, w)) * 0.012
    g = 1 + fas * 0.035 + wolke * 0.05 + korn
    return np.clip(np.array(farbe)[None, None, :] * g[..., None], 0, 1)


def etikett(ziel, name, grund, tinte, akzent, zeilen, B=2048, H=1770):
    """zeilen: [(text, schrift, px, farbe|'gold', y_anteil, sperren)]"""
    farbe = papier(H, B, grund)
    im = Image.fromarray((farbe * 255).astype(np.uint8))
    d = ImageDraw.Draw(im)
    gold = Image.new('L', (B, H), 0); dg = ImageDraw.Draw(gold)
    # Rahmen: doppelte Linie in Gold
    for off, br in ((70, 6), (92, 2)):
        dg.rectangle([off, off, B - off, H - off], outline=255, width=br)
    for text, sch, px, fb, y, sperr in zeilen:
        f = schrift(sch[0], px, sch[1]) if isinstance(sch, tuple) else schrift(sch, px)
        # Sperrsatz von Hand: Zeichen einzeln setzen
        breiten = [f.getlength(c) for c in text]
        gesamt = sum(breiten) + sperr * (len(text) - 1)
        x = (B - gesamt) / 2
        for c, bw in zip(text, breiten):
            if fb == 'gold':
                dg.text((x, y * H), c, font=f, fill=255, anchor='ls')
            else:
                d.text((x, y * H), c, font=f, fill=tuple(int(v * 255) for v in fb), anchor='ls')
            x += bw + sperr
    # Wappen-Ornament: Olivenzweig aus Blaettern (Gold), oben mittig
    cx, cy = B / 2, H * 0.19
    for s in (-1, 1):
        for k in range(7):
            t = k / 6
            px_ = cx + s * (40 + t * 230); py_ = cy + math.sin(t * math.pi) * -55 + t * 40
            ang = math.radians(s * (25 + t * 30))
            L, Wd = 58 - t * 14, 20 - t * 5
            pts = []
            for u in np.linspace(0, 2 * math.pi, 24):
                ex, ey = L * math.cos(u), Wd * math.sin(u)
                pts.append((px_ + ex * math.cos(ang) - ey * math.sin(ang), py_ + ex * math.sin(ang) + ey * math.cos(ang)))
            dg.polygon(pts, fill=255)
        dg.line([(cx + s * 30, cy + 40), (cx + s * 270, cy + 25)], fill=255, width=5)
    # Goldfolie: leicht abgerundete Kanten (Praegung), in die Farbkarte als Gold
    gold = gold.filter(ImageFilter.GaussianBlur(0.8))
    ga = np.asarray(gold).astype(float)[..., None] / 255
    goldton = np.array([0.80, 0.63, 0.30])
    farbe = np.asarray(im).astype(float) / 255
    farbe = farbe * (1 - ga) + goldton * ga
    Image.fromarray(np.clip(farbe * 255 + 0.5, 0, 255).astype(np.uint8)).save(os.path.join(ziel, f'{name}-farbe.png'))
    # glTF metallicRoughness: R ungenutzt, G Rauheit, B Metall
    rau = 0.72 - ga[..., 0] * 0.44
    mr = np.stack([np.zeros_like(rau), rau, ga[..., 0]], -1)
    Image.fromarray((mr * 255 + 0.5).astype(np.uint8)).save(os.path.join(ziel, f'{name}-mr.png'))
    # Normalkarte: Praegung der Goldfolie (0,15 mm) + Papierfaser
    h = ga[..., 0] * 1.0 + rauschen(H, B, 1.0) * 0.04
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 1.4; gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 1.4
    n = np.stack([-gx, gy, np.ones_like(h)], -1); n /= np.linalg.norm(n, axis=-1, keepdims=True)
    Image.fromarray(((n * 0.5 + 0.5) * 255 + 0.5).astype(np.uint8)).save(os.path.join(ziel, f'{name}-normal.png'))


def kork(ziel, n=512):
    """Naturkork: Zellen (helle Koerner, dunkle Poren), kachelbar."""
    a = np.zeros((n, n))
    rng = np.random.default_rng(7)
    for _ in range(2600):
        x, y = rng.integers(0, n, 2); r = rng.uniform(1.0, 4.5); v = rng.uniform(-1, 1)
        yy, xx = np.ogrid[-6:7, -6:7]
        m = np.exp(-(xx ** 2 + yy ** 2) / (r * r))
        a[np.ix_((y + np.arange(-6, 7)) % n, (x + np.arange(-6, 7)) % n)] += m * v
    a = (a - a.min()) / np.ptp(a)
    basis = np.array([0.62, 0.44, 0.27])
    farbe = basis[None, None, :] * (0.7 + 0.5 * a[..., None])
    Image.fromarray(np.clip(farbe * 255, 0, 255).astype(np.uint8)).save(os.path.join(ziel, 'kork-farbe.png'))


PRODUKTE = {
    # name, Papier, Tinte, Akzent, Zeilen
    'etikett-rosso': ((0.93, 0.90, 0.83), (0.12, 0.06, 0.07), (0.45, 0.05, 0.10)),
    'etikett-bianco': ((0.95, 0.94, 0.90), (0.14, 0.16, 0.14), (0.50, 0.55, 0.30)),
    'etikett-olio': ((0.90, 0.88, 0.78), (0.10, 0.14, 0.08), (0.30, 0.40, 0.12)),
}
TEXTE = {
    'etikett-rosso': ['NERO D’AVOLA', 'Sicilia · Rosso', '2022'],
    'etikett-bianco': ['GRILLO', 'Sicilia · Bianco', '2024'],
    'etikett-olio': ['OLIO EXTRA VERGINE', 'di oliva · Nocellara del Belice', 'raccolta 2024'],
}
FUSS = {'etikett-rosso': '750 ml · 13,5 % vol', 'etikett-bianco': '750 ml · 12,5 % vol', 'etikett-olio': '0,75 l · spremuto a freddo'}


def holz_kiste(ziel, quelle):
    """Nussbaum der Kiste: das Furnier der Innenraeume, dunkler und waermer
    geoelt. Das helle Furnier las sich im ersten Probebild wie Sperrholz."""
    p = os.path.join(quelle, 'innen-walnuss-farbe.png')
    if not os.path.exists(p):
        return
    a = np.asarray(Image.open(p).convert('RGB')).astype(float) / 255
    lin = a ** 2.2 * np.array([0.52, 0.40, 0.30])            # geoelt: dunkler, waermer
    Image.fromarray(np.clip(lin ** (1 / 2.2) * 255 + 0.5, 0, 255).astype(np.uint8)).save(os.path.join(ziel, 'holz-kiste-farbe.png'))


def alle(ziel, quelle='/tmp/claude-0/tex'):
    os.makedirs(ziel, exist_ok=True)
    holz_kiste(ziel, quelle)
    for name, (grund, tinte, akzent) in PRODUKTE.items():
        t = TEXTE[name]
        haupt_px = 210 if len(t[0]) < 12 else (150 if len(t[0]) < 16 else 112)
        zeilen = [
            ('VECOM', (LORA, 700), 120, 'gold', 0.40, 30),
            ('CANTINA DIMOSTRATIVA', (LORA, 500), 44, tinte, 0.47, 12),
            (t[0], (LORA, 600), haupt_px, akzent, 0.66, 8),
            (t[1], (LORA_I, 400), 78, tinte, 0.75, 2),
            (t[2], (LORA, 500), 74, 'gold', 0.85, 10),
            (FUSS[name], (LORA, 400), 46, tinte, 0.925, 3),
        ]
        etikett(ziel, name, grund, tinte, akzent, zeilen)
    kork(ziel)


if __name__ == '__main__':
    alle(sys.argv[1] if len(sys.argv) > 1 else 'tex')


# ------------------------------------------------------------ Uhr
def zifferblatt(ziel, name, grund, druck, B=2048, welle_staerke=0.16):
    """Zifferblatt 34 mm, UV 0..1 ueber den Durchmesser. Sonnenschliff als
    leichte Helligkeitswelle ueber den Winkel (ein echter Sonnenschliff ist
    anisotrop -- das kann glTF nicht; so bleibt die Wirkung im Foto und im
    Web gleich), Minuterie, Schriftzug."""
    yy, xx = np.mgrid[0:B, 0:B].astype(np.float64)
    cx = cy = (B - 1) / 2
    dx, dy = xx - cx, cy - yy
    r = np.hypot(dx, dy) / (B / 2)
    th = np.arctan2(dy, dx)
    welle = 1 + welle_staerke * np.cos(2 * (th - 0.6)) * np.clip(r * 1.4, 0, 1)
    rad = RNG.standard_normal((B, B)) * 0.015            # feine Schliffstruktur
    farbe = np.array(grund)[None, None, :] * (welle + rad)[..., None]
    farbe = np.clip(farbe, 0, 1)
    im = Image.fromarray((farbe * 255).astype(np.uint8)); d = ImageDraw.Draw(im)
    R = B / 2
    col = tuple(int(c * 255) for c in druck)
    # Minuterie: 60 Striche am Rand
    for k in range(60):
        a = math.radians(90 - k * 6)
        r0, r1 = R * (0.93 if k % 5 else 0.90), R * 0.975
        w = 7 if k % 5 == 0 else 4
        d.line([(cx + r0 * math.cos(a), cy - r0 * math.sin(a)), (cx + r1 * math.cos(a), cy - r1 * math.sin(a))], fill=col, width=w)
    f1 = schrift(LORA, 118, 700); f2 = schrift(LORA, 58, 500); f3 = schrift(LORA_I, 54, 400)
    d.text((cx, cy - R * 0.36), 'VECOM', font=f1, fill=col, anchor='mm')
    d.text((cx, cy - R * 0.24), 'SICILIA', font=f2, fill=col, anchor='mm')
    d.text((cx, cy + R * 0.36), 'Automatic', font=f3, fill=col, anchor='mm')
    d.text((cx, cy + R * 0.46), '100 M', font=f2, fill=col, anchor='mm')
    im.save(os.path.join(ziel, f'{name}-farbe.png'))


def werk(ziel, n=1024):
    """Werkteile: Perlage (Platine) und Genfer Streifen (Bruecken) als Normalkarten."""
    yy, xx = np.mgrid[0:n, 0:n].astype(np.float64)
    h = np.zeros((n, n))
    schritt = 26
    for oy in range(0, n + schritt, schritt):
        for ox in range(0, n + schritt, schritt):
            ox2 = ox + (schritt // 2 if (oy // schritt) % 2 else 0)
            rr = np.hypot(xx - ox2, yy - oy)
            h = np.maximum(h, np.clip(1 - rr / 18, 0, 1) * (0.5 + 0.5 * np.cos(rr * 0.9)))
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 2; gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 2
    nn = np.stack([-gx, gy, np.ones_like(h)], -1); nn /= np.linalg.norm(nn, axis=-1, keepdims=True)
    Image.fromarray(((nn * 0.5 + 0.5) * 255).astype(np.uint8)).save(os.path.join(ziel, 'werk-perlage-normal.png'))
    h = 0.5 + 0.5 * np.sin(yy / n * 2 * math.pi * 10) ** 7
    h = h + np.sin(xx * 0.8) * 0.02
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 3; gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 3
    nn = np.stack([-gx, gy, np.ones_like(h)], -1); nn /= np.linalg.norm(nn, axis=-1, keepdims=True)
    Image.fromarray(((nn * 0.5 + 0.5) * 255).astype(np.uint8)).save(os.path.join(ziel, 'werk-streifen-normal.png'))


def uhr(ziel):
    zifferblatt(ziel, 'zifferblatt-blau', (0.03, 0.10, 0.30), (0.93, 0.94, 0.96))
    zifferblatt(ziel, 'zifferblatt-schwarz', (0.018, 0.018, 0.02), (0.88, 0.76, 0.50))
    zifferblatt(ziel, 'zifferblatt-weiss', (0.82, 0.80, 0.76), (0.10, 0.10, 0.12), welle_staerke=0.05)
    werk(ziel)


# ------------------------------------------------------------ Kueche
# Nahtlos kachelbar (periodisches Rauschen ueber FFT). Farbkarten in sRGB,
# gerechnet linear. Masse je Kachel stehen in pr_kueche.py (kachel=).

def _prausch(h, w, gx, gy=None, rng=RNG):
    """Periodisches Gauss-Rauschen, Glaettung gx/gy in Pixeln (anisotrop)."""
    gy = gx if gy is None else gy
    a = rng.standard_normal((h, w))
    fy = np.fft.fftfreq(h)[:, None]; fx = np.fft.fftfreq(w)[None, :]
    r = np.real(np.fft.ifft2(np.fft.fft2(a) * np.exp(-2 * np.pi ** 2 * (fx ** 2 * gx ** 2 + fy ** 2 * gy ** 2))))
    return (r - r.mean()) / (r.std() + 1e-12)


def _srgb(lin):
    lin = np.clip(lin, 0, 1)
    return np.where(lin <= 0.0031308, lin * 12.92, 1.055 * np.power(lin, 1 / 2.4) - 0.055)


def _normal(h, staerke):
    gx = (np.roll(h, -1, 1) - np.roll(h, 1, 1)) * 0.5 * staerke
    gy = (np.roll(h, -1, 0) - np.roll(h, 1, 0)) * 0.5 * staerke
    n = np.stack([-gx, gy, np.ones_like(h)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    return np.clip((n * 0.5 + 0.5) * 255 + 0.5, 0, 255).astype(np.uint8)


def _bild(ziel, name, arr, farbe=True):
    a = (np.clip(_srgb(arr) if farbe else arr, 0, 1) * 255 + 0.5).astype(np.uint8) if arr.dtype != np.uint8 else arr
    Image.fromarray(a).save(os.path.join(ziel, name + '.png'), optimize=True)


def eiche_platte(ziel, n=2048, kachel=2.4):
    """Massivholzplatte Eiche, keilgezinkt: Lamellen 40 mm quer (y), Stuecke
    0,4-1,2 m lang (x) mit eigener Tonlage, gerade Maserung laengs, Poren als
    kurze dunkle Striche, vereinzelt Spiegel (Markstrahlen)."""
    px_m = n / kachel
    lam = int(round(0.040 * px_m))
    yy, xx = np.mgrid[0:n, 0:n].astype(float)
    ton = np.zeros((n, n)); fuge = np.zeros((n, n))
    rng = np.random.default_rng(7)
    for y0 in range(0, n, lam):
        x0 = int(rng.integers(0, n)); x = 0
        while x < n:
            l = int(rng.uniform(0.4, 1.2) * px_m)
            t = rng.normal(0, 0.10)
            sp = (x0 + np.arange(x, min(n, x + l))) % n
            ton[y0:y0 + lam, sp] = t
            fuge[y0:y0 + lam, (x0 + x) % n] = 1.0          # Keilzinkung als Stossfuge
            x += l
        fuge[y0 % n, :] = np.maximum(fuge[y0 % n, :], 0.6)   # Leimfuge zwischen den Lamellen
    # gerade Maserung: quer schnell, laengs sehr langsam veraenderlich
    faser = _prausch(n, n, 180, 1.2, rng)
    faser2 = _prausch(n, n, 60, 0.6, rng)
    poren = np.clip((_prausch(n, n, 5, 0.5, rng) - 2.0) * 1.2, 0, 1)
    spiegel = np.clip((_prausch(n, n, 3, 1.5, rng) - 2.6) * 2.0, 0, 1) * (_prausch(n, n, 80, 20, rng) > 1.0)
    wolke = _prausch(n, n, 90, 40, rng)
    hell, dunkel = np.array([0.46, 0.26, 0.105]), np.array([0.20, 0.10, 0.038])     # geoelte Eiche, honigfarben
    t = np.clip(0.55 + 0.16 * faser + 0.08 * faser2 + 0.06 * wolke, 0, 1)[..., None]
    farbe = (dunkel + (hell - dunkel) * t) * (1 + ton[..., None]) * (1 - 0.45 * poren[..., None]) * (1 + 0.18 * spiegel[..., None])
    farbe *= (1 - 0.35 * fuge[..., None])
    _bild(ziel, 'kueche-eiche-farbe', farbe)
    h = -poren * 1.0 + 0.2 * faser - 0.8 * fuge
    _bild(ziel, 'kueche-eiche-normal', _normal(h, 1.6))


def marmor(ziel, n=2048):
    """Carrara: warmweisser Grund, weiche graue Adern laengs der Platte, die
    sich verzweigen und auslaufen (Nulldurchgaenge von gestrecktem Rauschen),
    grauer Hof um die Hauptadern, feine Nebenadern. Die erste Fassung (Sinus
    ueber verzogener Phase) sah aus wie Hoehenlinien einer Landkarte."""
    rng = np.random.default_rng(11)
    n1 = _prausch(n, n, 150, 30, rng) + 0.12 * _prausch(n, n, 40, 10, rng)   # wenig Kleinanteil: sonst kleine Ringe
    n2 = _prausch(n, n, 60, 12, rng) + 0.3 * _prausch(n, n, 12, 4, rng)
    maske1 = np.clip(0.55 + 0.6 * _prausch(n, n, 200, 120, rng), 0, 1)
    maske2 = np.clip(0.4 + 0.6 * _prausch(n, n, 120, 80, rng), 0, 1)
    breite = 0.035 + 0.11 * np.clip(0.5 + 0.5 * _prausch(n, n, 90, 40, rng), 0, 1)   # Adern schwellen an und ab
    ader = np.exp(-(np.abs(n1) / breite) ** 1.4) * maske1 * 0.75
    hof = np.exp(-np.abs(n1) / 0.45) * maske1 * 0.28
    fein = np.exp(-np.abs(n2) / 0.02) * maske2 * 0.22
    wolke = _prausch(n, n, 160, rng=rng)
    grund = np.array([0.80, 0.79, 0.765]) * (1 + 0.02 * wolke[..., None])
    grau_ = np.array([0.33, 0.34, 0.36])
    m = np.clip(ader + hof + fein, 0, 0.8)
    m = np.asarray(Image.fromarray((m * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.6))).astype(float)[..., None] / 255
    farbe = grund * (1 - m) + grau_ * m
    _bild(ziel, 'kueche-marmor-farbe', farbe)


def keramik_dunkel(ziel, n=1024):
    """Keramikplatte in Beton-Optik, anthrazit: Wolken, feine Sprenkel,
    matte, leicht unruhige Oberflaeche."""
    rng = np.random.default_rng(13)
    wolke = _prausch(n, n, 70, rng=rng); wolke2 = _prausch(n, n, 14, rng=rng)
    spr = np.clip((_prausch(n, n, 0.8, rng=rng) - 2.2) * 1.5, 0, 1)
    farbe = np.array([0.050, 0.050, 0.052]) * (1 + 0.18 * wolke + 0.07 * wolke2)[..., None] * (1 + 0.8 * spr[..., None])
    _bild(ziel, 'kueche-keramik-farbe', farbe)
    _bild(ziel, 'kueche-keramik-normal', _normal(0.6 * wolke2 + 0.4 * _prausch(n, n, 1.2, rng=rng), 0.6))


def kochfeld(ziel, B=2048):
    """Induktionskochfeld 800 x 520 mm, schwarzes Glas mit Siebdruck: vier
    Kochzonen (zwei als Bruecke verbunden), Bedienleiste mit Schieber vorn.
    UV 0..1 ueber das ganze Feld (x = Breite, y = Tiefe, 0 = vorn)."""
    H = int(B * 0.52 / 0.80)
    im = Image.new('RGB', (B, H), (6, 6, 7)); d = ImageDraw.Draw(im)
    px = B / 0.80
    grau = (74, 74, 78); hell = (150, 150, 156)
    def kreis(cx, cy, r, w=3, f=grau):
        x, y = cx * px, H - cy * px
        d.ellipse([x - r * px, y - r * px, x + r * px, y + r * px], outline=f, width=w)
    kreis(0.19, 0.385, 0.105); kreis(0.19, 0.385, 0.098, 2)                 # hinten links
    kreis(0.61, 0.385, 0.085)                                               # hinten rechts
    kreis(0.61, 0.165, 0.100); kreis(0.61, 0.165, 0.064, 2)                 # vorn rechts, Zweikreis
    # Bruecke links vorn: Rechteckzone mit Ecken
    x0, x1, y0, y1 = 0.09 * px, 0.29 * px, H - 0.26 * px, H - 0.07 * px
    d.rounded_rectangle([x0, y0, x1, y1], radius=int(0.012 * px), outline=grau, width=3)
    # Bedienleiste vorn: Schieber (Punktreihe) und Symbole
    for i in range(19):
        x = (0.26 + i * 0.016) * px; y = H - 0.030 * px
        d.ellipse([x - 5, y - 5, x + 5, y + 5], fill=grau)
    f = schrift(LORA, int(0.016 * px), 500)
    for i, t in enumerate(('P', '−', '+', '0/I')):
        d.text(((0.07 + i * 0.045) * px, H - 0.030 * px), t, font=f, fill=hell, anchor='mm')
    d.text((0.73 * px, H - 0.030 * px), 'VECOM', font=f, fill=grau, anchor='mm')
    im.save(os.path.join(ziel, 'kueche-kochfeld-farbe.png'))


def kueche(ziel):
    os.makedirs(ziel, exist_ok=True)
    eiche_platte(ziel); marmor(ziel); keramik_dunkel(ziel); kochfeld(ziel)


# ------------------------------------------------------------ Sattelzug
def plane(ziel, B=4096):
    """Seitenplane 13,55 x 2,80 m mit Druck (eigene Marke, keine fremde):
    grosser Schriftzug, Zeile darunter, feine Kante oben und unten. UV 0..1
    ueber die ganze Plane (u = Laenge ab Stirnwand, v = Hoehe). Linke Seite
    liest sich von aussen richtig, die rechte bekommt dieselbe Karte
    gespiegelt in der UV (pr_lkw.py)."""
    H = int(B * 2.80 / 13.55)
    for name, grund, schrift_f, akzent in (('weiss', (238, 238, 234), (150, 18, 22), (150, 18, 22)),
                                           ('grau', (92, 96, 102), (242, 242, 240), (200, 30, 36)),
                                           ('blau', (18, 38, 82), (240, 240, 236), (240, 196, 60))):
        im = Image.new('RGB', (B, H), grund); d = ImageDraw.Draw(im)
        px = B / 13.55
        # Leichte Wolken im Gewebe (PVC-Plane ist nie ganz gleichmaessig)
        w = rauschen(H // 16, B // 16, 2)
        w = np.asarray(Image.fromarray(((w - w.min()) / np.ptp(w) * 255).astype(np.uint8)).resize((B, H), Image.BICUBIC)).astype(float) / 255 - 0.5
        a = np.asarray(im).astype(float) * (1 + 0.035 * w[..., None])
        im = Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)); d = ImageDraw.Draw(im)
        f1 = schrift(LORA, int(0.95 * px), 700); f2 = schrift(LORA, int(0.26 * px), 500)
        d.text((int(1.2 * px), int(1.55 * px)), 'VECOM', font=f1, fill=schrift_f, anchor='ls')
        d.text((int(1.25 * px), int(2.05 * px)), 'LOGISTIK  ·  SICILIA  ·  EUROPA', font=f2, fill=schrift_f, anchor='ls')
        d.rectangle([0, int(0.10 * px), B, int(0.16 * px)], fill=akzent)
        d.rectangle([0, H - int(0.16 * px), B, H - int(0.10 * px)], fill=akzent)
        im.save(os.path.join(ziel, f'lkw-plane-{name}-farbe.png'))


def lkw(ziel):
    os.makedirs(ziel, exist_ok=True)
    plane(ziel)
