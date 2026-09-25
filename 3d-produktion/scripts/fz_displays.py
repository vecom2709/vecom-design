"""fz_displays.py -- Bildschirminhalte fuer Kombiinstrument und Mitteldisplay (v2).

Laeuft mit Pillow/NumPy (nicht in Blender). Die Bilder liegen als leuchtende
Texturen auf den Displays (fahrzeug_bau.py: Emission mit Klarlack darueber).

v2 (23.09.2026, Uwe: "realistische Cockpit-, Navigations- und Tachoanzeigen,
modern und hyperfotografisch"). Was v1 flach wirken liess und hier anders ist:
  * Leuchten: Alles, was auf einem echten OLED leuchtet (Skalenbogen, Route,
    Fahrspur), bekommt einen weichen Lichthof in zwei Radien. Ohne ihn sieht
    ein Display im Rendering aus wie ein bedrucktes Blatt.
  * Karte: echte Zentralperspektive (Lochkamera ueber der Strasse) mit
    extrudierten Gebaeuden, Kontaktschatten, Dunst zum Horizont, leuchtender
    Route und Richtungspfeilen -- wie die 3D-Ansichten heutiger Systeme.
  * Fahrerassistenz: Fahrspur-Ansicht in derselben Perspektive, eigenes Auto
    als Cycles-Freisteller (render/<modell>/probe-<lack>-adas.png), voraus ein
    erkanntes Fahrzeug mit Abstandsbalken.
  * Mattglas-Tafeln statt Kaesten: Die Karte scheint unscharf durch.
  * Ueberall ein Hauch Rauschen (+-0,6/255) gegen Banding in den Verlaeufen.

Keine Marken, keine echten Kartendaten: Stadt und Strassen sind erzeugt, die
Route fuehrt nach "Agrigento" (Sitz von Vecom Design). Schrift Inter, alle
Texte gross und kontrastreich -- auf dem Poster ist ein Display nur wenige
hundert Pixel breit.

Aufruf:  python3 fz_displays.py <zielordner> [auto-klein.png] [auto-mittel.png]
"""
import math, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

SCHRIFT = os.environ.get('INTER_TTF', '/tmp/claude-0/inter-latin.ttf')
SS = 2                                    # Ueberabtastung gegen Treppen

# Farbwelt: tiefes OLED-Schwarz, ein Blau fuer "aktiv", Cyan nur als Spitze.
SCHWARZ = (3, 5, 9)
TEXT = (240, 244, 250)
TEXT2 = (146, 158, 182)
TEXT3 = (88, 98, 120)
BLAU = (38, 132, 255)
BLAU_HELL = (130, 196, 255)
CYAN = (70, 226, 255)
BERNSTEIN = (255, 176, 64)


# ---------------------------------------------------------------- Grundlagen
_schriften = {}


def schrift(px, gewicht=500):
    k = (int(px * SS), gewicht)
    if k not in _schriften:
        f = ImageFont.truetype(SCHRIFT, k[0])
        try:
            f.set_variation_by_axes([gewicht])
        except Exception:
            pass
        _schriften[k] = f
    return _schriften[k]


def mischen(a, b, t):
    return tuple(a[i] + (b[i] - a[i]) * t for i in range(3))


class Bild:
    """Gleitkomma-Leinwand in Ueberabtastung. Gezeichnet wird in RGBA-Ebenen,
    die mit optionalem Lichthof aufgetragen werden."""

    def __init__(s, b, h, grund=SCHWARZ):
        s.b, s.h = b, h
        s.a = np.empty((h * SS, b * SS, 3), np.float32)
        s.a[:] = np.array(grund, np.float32) / 255
        s.zuschnitt = None           # (x0, y0, x1, y1): Ebenen wirken nur darin

    def ebene(s):
        e = Image.new('RGBA', (s.b * SS, s.h * SS), (0, 0, 0, 0))
        return e, ImageDraw.Draw(e)

    def auf(s, e, leuchten=0.0, r=10, deckung=1.0):
        x = np.asarray(e).astype(np.float32) / 255
        if s.zuschnitt:
            x0, y0, x1, y1 = [int(v * SS) for v in s.zuschnitt]
            m = np.zeros(x.shape[:2], np.float32); m[y0:y1, x0:x1] = 1
            x[..., 3] *= m
        al = x[..., 3:4] * deckung
        s.a = s.a * (1 - al) + x[..., :3] * al
        if leuchten:
            s.leuchten(x[..., :3] * al, leuchten, r)

    def leuchten(s, prem, staerke, r):
        """Lichthof: vormultiplizierte Farbe, zweimal unscharf, addiert."""
        q = Image.fromarray(np.clip(prem * 255, 0, 255).astype(np.uint8))
        for rr, w in ((r, 0.65), (r * 3.2, 0.35)):
            g = np.asarray(q.filter(ImageFilter.GaussianBlur(rr * SS))).astype(np.float32) / 255
            s.a += g * staerke * w

    def glas(s, box, radius=22, dunkel=0.42, unschaerfe=18, rand=0.10):
        """Mattglas-Tafel: Hintergrund unscharf und abgedunkelt, feine Lichtkante."""
        x0, y0, x1, y1 = [int(v * SS) for v in box]
        teil = Image.fromarray(np.clip(s.a[y0:y1, x0:x1] * 255, 0, 255).astype(np.uint8))
        teil = np.asarray(teil.filter(ImageFilter.GaussianBlur(unschaerfe * SS))).astype(np.float32) / 255
        teil = teil * dunkel + np.array([10, 14, 24], np.float32) / 255 * 0.55
        maske = Image.new('L', (x1 - x0, y1 - y0), 0)
        ImageDraw.Draw(maske).rounded_rectangle([0, 0, x1 - x0 - 1, y1 - y0 - 1], radius=radius * SS, fill=255)
        m = np.asarray(maske).astype(np.float32)[..., None] / 255
        s.a[y0:y1, x0:x1] = s.a[y0:y1, x0:x1] * (1 - m) + teil * m
        e, d = s.ebene()
        d.rounded_rectangle([x0, y0, x1 - 1, y1 - 1], radius=radius * SS, outline=(255, 255, 255, int(255 * rand)), width=SS)
        s.auf(e)

    def verlauf(s, box, oben, unten, deckung_oben=1.0, deckung_unten=1.0):
        x0, y0, x1, y1 = [int(v * SS) for v in box]
        t = np.linspace(0, 1, y1 - y0, dtype=np.float32)[:, None, None]
        f = np.array(oben, np.float32) / 255 * (1 - t) + np.array(unten, np.float32) / 255 * t
        al = deckung_oben * (1 - t) + deckung_unten * t
        s.a[y0:y1, x0:x1] = s.a[y0:y1, x0:x1] * (1 - al) + f * al

    def speichern(s, pfad, rng=np.random.default_rng(11)):
        a = s.a + (rng.random(s.a.shape, np.float32) - 0.5) * (1.2 / 255)
        im = Image.fromarray(np.clip(a * 255 + 0.5, 0, 255).astype(np.uint8))
        im.resize((s.b, s.h), Image.LANCZOS).save(pfad)


def text(d, xy, s, px, farbe=TEXT, gewicht=500, anker='la', alpha=255):
    d.text((xy[0] * SS, xy[1] * SS), s, font=schrift(px, gewicht), fill=tuple(int(c) for c in farbe) + (alpha,), anchor=anker)


def P(*pts):
    return [(x * SS, y * SS) for x, y in pts]


# ------------------------------------------------------------- Rundinstrument
def ring(bild, mitte, r, breite, anteil, c0, c1, w0=135.0, spanne=270.0, leuchten=0.9):
    """Skalenbogen mit Farbverlauf entlang des Bogens, dunkle Spur dahinter."""
    cx, cy = mitte[0] * SS, mitte[1] * SS
    R, B = r * SS, breite * SS
    x0, x1 = int(cx - R - B), int(cx + R + B) + 1
    y0, y1 = int(cy - R - B), int(cy + R + B) + 1
    yy, xx = np.mgrid[y0:y1, x0:x1].astype(np.float32)
    dx, dy = xx - cx + 0.5, yy - cy + 0.5
    rad = np.hypot(dx, dy)
    w = (np.degrees(np.arctan2(dy, dx)) - w0) % 360.0
    t = w / spanne
    innen = np.clip(B / 2 - np.abs(rad - R) + 0.5, 0, 1)            # Kantenglaettung
    auf_skala = (t <= 1.0).astype(np.float32)
    spur = innen * auf_skala
    fuell = innen * (t <= anteil)
    # runde Kappe am Ende der Fuellung
    we = math.radians(w0 + spanne * anteil)
    ex, ey = cx + math.cos(we) * R, cy + math.sin(we) * R
    kappe = np.clip(B / 2 - np.hypot(xx + 0.5 - ex, yy + 0.5 - ey) + 0.5, 0, 1) * (anteil > 0)
    fuell = np.maximum(fuell, kappe)
    tt = np.clip(t / max(anteil, 1e-3), 0, 1)[..., None]
    farbe = (np.array(c0, np.float32) * (1 - tt) + np.array(c1, np.float32) * tt) / 255
    teil = bild.a[y0:y1, x0:x1]
    teil = teil * (1 - spur[..., None] * 0.85) + np.array([22, 28, 40], np.float32) / 255 * spur[..., None] * 0.85
    teil = teil * (1 - fuell[..., None]) + farbe * fuell[..., None]
    bild.a[y0:y1, x0:x1] = teil
    if leuchten:
        prem = np.zeros_like(bild.a)
        prem[y0:y1, x0:x1] = farbe * fuell[..., None]
        bild.leuchten(prem, leuchten, 9)
    return ex / SS, ey / SS


def skala(bild, mitte, r, werte, w0=135.0, spanne=270.0, px=22, zwischen=4, aktiv_bis=None):
    e, d = bild.ebene()
    n = len(werte) - 1
    for k in range(n * zwischen + 1):
        w = math.radians(w0 + spanne * k / (n * zwischen))
        gross = k % zwischen == 0
        a, b = r - (16 if gross else 10), r - 2
        c = TEXT if gross else TEXT3
        d.line(P((mitte[0] + math.cos(w) * a, mitte[1] + math.sin(w) * a),
                 (mitte[0] + math.cos(w) * b, mitte[1] + math.sin(w) * b)), fill=c + (230,), width=int((3 if gross else 2) * SS))
        if gross:
            i = k // zwischen
            rr = r - 40
            aktiv = aktiv_bis is not None and i * spanne / n <= aktiv_bis * spanne + 0.5
            text(d, (mitte[0] + math.cos(w) * rr, mitte[1] + math.sin(w) * rr), str(werte[i]), px,
                 TEXT if aktiv else TEXT2, 600 if aktiv else 500, 'mm')
    bild.auf(e)


def rundinstrument(bild, mitte, r, anteil, werte, gross, einheit, fuss, c0=BLAU, c1=CYAN):
    # Zifferblatt: ganz leichter Radialverlauf, damit das Instrument "sitzt"
    cx, cy = mitte
    yy, xx = np.mgrid[0:bild.h * SS, 0:bild.b * SS].astype(np.float32)
    rad = np.hypot(xx / SS - cx, yy / SS - cy) / r
    hof = np.clip(1 - rad, 0, 1) ** 1.6 * 0.07
    bild.a += hof[..., None] * np.array([0.35, 0.55, 1.0], np.float32)
    ex, ey = ring(bild, mitte, r, 7, anteil, c0, c1)
    skala(bild, mitte, r - 14, werte, aktiv_bis=anteil)
    e, d = bild.ebene()
    # Leuchtpunkt am Zeigerende
    d.ellipse([(ex - 9) * SS, (ey - 9) * SS, (ex + 9) * SS, (ey + 9) * SS], fill=(235, 250, 255, 255))
    bild.auf(e, leuchten=1.2, r=7)
    e, d = bild.ebene()
    text(d, (cx, cy + 6), gross, r * 0.60, TEXT, 600, 'mm')
    text(d, (cx, cy + r * 0.40), einheit, r * 0.15, TEXT2, 500, 'mm')
    text(d, (cx, cy + r * 0.80), fuss, r * 0.13, TEXT2, 500, 'mm')
    bild.auf(e, leuchten=0.10, r=6)


# --------------------------------------------------------------- Perspektive
class Kamera:
    def __init__(s, pos, ziel, fov_h, box, versatz=0.0):
        s.C = np.array(pos, float)
        f = np.array(ziel, float) - s.C; s.f = f / np.linalg.norm(f)
        r = np.cross(s.f, [0, 0, 1.0]); s.r = r / np.linalg.norm(r)
        s.u = np.cross(s.r, s.f)
        x0, y0, x1, y1 = box
        s.cx, s.cy = (x0 + x1) / 2, (y0 + y1) / 2 + versatz
        s.fpx = (x1 - x0) / 2 / math.tan(math.radians(fov_h / 2))

    def tiefe(s, p):
        return float(np.dot(np.asarray(p, float) - s.C, s.f))

    def bild(s, p):
        d = np.asarray(p, float) - s.C
        z = max(np.dot(d, s.f), 0.5)
        return (s.cx + s.fpx * np.dot(d, s.r) / z, s.cy - s.fpx * np.dot(d, s.u) / z)

    def poly(s, pts):
        return [s.bild(p) for p in pts]

    def horizont(s):
        """Bildzeile des Horizonts (Bildschirm-Pixel)."""
        h = np.array([s.f[0], s.f[1], 0.0]); h /= np.linalg.norm(h)
        return s.cy - s.fpx * np.dot(h, s.u) / np.dot(h, s.f)

    def boden_tiefe(s, zeilen):
        """Tiefe des Bodenpunkts je Bildzeile (inf ueber dem Horizont)."""
        vy = zeilen - s.cy
        dz = s.f[2] * s.fpx - s.u[2] * vy
        t = np.where(dz < -1e-6, s.C[2] / np.maximum(-dz, 1e-6), np.inf)
        return t * s.fpx


def boden_und_himmel(bild, kam, box, boden, dunst, himmel_oben, nebel):
    x0, y0, x1, y1 = box
    zeilen = (np.arange(y0 * SS, y1 * SS, dtype=np.float32) + 0.5) / SS
    tiefe = kam.boden_tiefe(zeilen)
    f = 1 - np.exp(-np.nan_to_num(tiefe, posinf=1e9) / nebel)
    boden_f = np.array(boden, np.float32)[None] * (1 - f[:, None]) + np.array(dunst, np.float32)[None] * f[:, None]
    hz = kam.horizont()
    t = np.clip((hz - zeilen) / max(hz - y0, 1), 0, 1)[:, None]
    himmel_f = np.array(dunst, np.float32)[None] * (1 - t ** 0.6) + np.array(himmel_oben, np.float32)[None] * t ** 0.6
    zeile = np.where(np.isfinite(tiefe)[:, None], boden_f, himmel_f) / 255
    bild.a[y0 * SS:y1 * SS, x0 * SS:x1 * SS] = zeile[:, None, :]


def nebelfarbe(c, tiefe, dunst, nebel):
    f = 1 - math.exp(-max(tiefe, 0) / nebel)
    return tuple(int(v) for v in mischen(c, dunst, f))


def quad_band(a, b, breite, z=0.0):
    """Rechteck-Streifen (Welt) zwischen zwei Bodenpunkten."""
    dx, dy = b[0] - a[0], b[1] - a[1]; L = math.hypot(dx, dy) or 1
    nx, ny = -dy / L * breite / 2, dx / L * breite / 2
    return [(a[0] + nx, a[1] + ny, z), (b[0] + nx, b[1] + ny, z), (b[0] - nx, b[1] - ny, z), (a[0] - nx, a[1] - ny, z)]


def zerteilen(a, b, schritt):
    L = math.hypot(b[0] - a[0], b[1] - a[1]); n = max(1, int(L / schritt))
    return [((a[0] + (b[0] - a[0]) * i / n, a[1] + (b[1] - a[1]) * i / n),
             (a[0] + (b[0] - a[0]) * (i + 1) / n, a[1] + (b[1] - a[1]) * (i + 1) / n)) for i in range(n)]


# ------------------------------------------------------------------ 3D-Karte
KARTE_BODEN = (15, 19, 28)
KARTE_DUNST = (30, 38, 56)
KARTE_HIMMEL = (6, 9, 16)
STRASSE_NEBEN = (40, 48, 66)
STRASSE_HAUPT = (62, 72, 94)
PARK = (16, 36, 32)
WASSER = (12, 30, 58)


def stadt(seed):
    rng = np.random.default_rng(seed)
    xs = [0.0]
    for sgn in (1, -1):
        x = 0.0
        while abs(x) < 1100:
            x += sgn * rng.uniform(80, 135); xs.append(x)
    xs.sort()
    ys = [300.0]
    y = 300.0
    while y < 3200: y += rng.uniform(70, 140); ys.append(y)
    y = 300.0
    while y > -400: y -= rng.uniform(70, 120); ys.append(y)
    ys.sort()
    wasser = (1640.0, 1730.0)                   # Fluss quer, Bruecken auf allen Strassen
    return rng, xs, ys, wasser


def karte3d(bild, box, seed=5, h=340.0, zurueck=300.0, blick=260.0, fov=62.0, pfeil_y=70.0, versatz=0.0, ziel_y=1450.0):
    """Blick schraeg von oben (gut 30 Grad) wie in heutigen 3D-Navis: steil
    genug, dass die naechste Abbiegung nicht hinter Haeusern verschwindet."""
    x0, y0, x1, y1 = box
    rng, xs, ys, wasser = stadt(seed)
    kam = Kamera((0, pfeil_y - zurueck, h), (0, pfeil_y + blick, 0), fov, box, versatz)
    bild.zuschnitt = box
    nebel = 1500.0
    y_min = kam.C[1] + 20
    boden_und_himmel(bild, kam, box, KARTE_BODEN, KARTE_DUNST, KARTE_HIMMEL, nebel)

    def flaeche(pts_boden, farbe, d, z=0.0):
        t = sum(kam.tiefe((p[0], p[1], z)) for p in pts_boden) / len(pts_boden)
        d.polygon(P(*kam.poly([(p[0], p[1], z) for p in pts_boden])), fill=nebelfarbe(farbe, t, KARTE_DUNST, nebel) + (255,))

    # --- Flaechen: Fluss und Park
    e, d = bild.ebene()
    for xa in range(-1400, 1400, 50):
        flaeche([(xa, wasser[0]), (xa + 50, wasser[0] + 12), (xa + 50, wasser[1] + 12), (xa, wasser[1])], WASSER, d)
    ix = max(i for i, x in enumerate(xs) if x < -150)
    iy = max(i for i, y in enumerate(ys) if y < 900)
    park = (xs[ix - 1], ys[iy], xs[ix], ys[iy + 2])
    for a, b in zerteilen((park[0], park[1]), (park[0], park[3]), 40):
        flaeche([(park[0], a[1]), (park[2], a[1]), (park[2], b[1]), (park[0], b[1])], PARK, d)
    bild.auf(e)

    # --- Strassen (in Stuecken, damit der Dunst mit der Tiefe zunimmt)
    e, d = bild.ebene()
    y_ende = 3300
    for x in xs:
        breite = 16 if x == 0 else 9
        c = STRASSE_HAUPT if x == 0 else STRASSE_NEBEN
        for a, b in zerteilen((x, y_min), (x, y_ende), 35):
            flaeche([p[:2] for p in quad_band(a, b, breite)], c, d)
    for y in ys:
        if y < y_min: continue
        breite = 13 if y == 300 else 9
        c = STRASSE_HAUPT if y == 300 else STRASSE_NEBEN
        for a, b in zerteilen((-1300, y), (1300, y), 35):
            flaeche([p[:2] for p in quad_band(a, b, breite)], c, d)
    bild.auf(e)
    # Mittellinie der Hauptstrasse, fein gestrichelt
    e, d = bild.ebene()
    for yy in range(int(y_min), 1600, 18):
        flaeche([p[:2] for p in quad_band((0, yy), (0, yy + 8), 0.5)], (120, 132, 156), d)
    bild.auf(e, deckung=0.8)

    # --- Route: geradeaus, rechts in die Querstrasse, dann wieder voraus
    xr = [x for x in xs if x > 0][1]
    route = [(0, pfeil_y), (0, 300), (xr, 300), (xr, ziel_y)]
    e, d = bild.ebene()
    stuecke = []
    for i in range(len(route) - 1):
        stuecke += zerteilen(route[i], route[i + 1], 25)
    for a, b in stuecke:
        d.polygon(P(*kam.poly(quad_band(a, b, 11, 0.2))), fill=BLAU + (255,))
    # Knickpunkte runden
    for p in route[1:-1]:
        pts = [(p[0] + math.cos(w) * 5.5, p[1] + math.sin(w) * 5.5, 0.2) for w in np.linspace(0, 2 * math.pi, 24)]
        d.polygon(P(*kam.poly(pts)), fill=BLAU + (255,))
    bild.auf(e, leuchten=0.9, r=6)
    e, d = bild.ebene()
    for a, b in stuecke:
        d.polygon(P(*kam.poly(quad_band(a, b, 3.2, 0.25))), fill=BLAU_HELL + (190,))
    bild.auf(e)
    # Richtungspfeile auf der Route
    e, d = bild.ebene()
    for i in range(len(route) - 1):
        a, b = route[i], route[i + 1]
        L = math.hypot(b[0] - a[0], b[1] - a[1]); ux, uy = (b[0] - a[0]) / L, (b[1] - a[1]) / L
        s = 38.0
        while s < L - 20:
            m = (a[0] + ux * s, a[1] + uy * s)
            nx, ny = -uy, ux
            spitze = (m[0] + ux * 3.2, m[1] + uy * 3.2, 0.3)
            l = (m[0] - ux * 2.4 + nx * 3.4, m[1] - uy * 2.4 + ny * 3.4, 0.3)
            rr = (m[0] - ux * 2.4 - nx * 3.4, m[1] - uy * 2.4 - ny * 3.4, 0.3)
            k = (m[0] - ux * 0.6, m[1] - uy * 0.6, 0.3)
            d.polygon(P(*kam.poly([spitze, l, k, rr])), fill=(235, 245, 255, 235))
            s += 55.0
    bild.auf(e)

    # --- Gebaeude: Kontaktschatten, dann von hinten nach vorn
    gebaeude = []
    for i in range(len(xs) - 1):
        for j in range(len(ys) - 1):
            bx0, bx1 = xs[i] + 9, xs[i + 1] - 9
            by0, by1 = ys[j] + 9, ys[j + 1] - 9
            if by1 < y_min + 40 or bx1 - bx0 < 12 or by1 - by0 < 12: continue
            if by1 > wasser[0] - 10 and by0 < wasser[1] + 10: continue
            if (bx0 >= park[0] - 1 and bx1 <= park[2] + 1 and by0 >= park[1] - 1 and by1 <= park[3] + 1): continue
            nx_ = int(rng.integers(1, 4)); ny_ = int(rng.integers(1, 3))
            gx = np.sort(np.concatenate([[bx0, bx1], rng.uniform(bx0 + 10, bx1 - 10, nx_ - 1)]))
            gy = np.sort(np.concatenate([[by0, by1], rng.uniform(by0 + 10, by1 - 10, ny_ - 1)]))
            mitte_abst = abs((bx0 + bx1) / 2)
            for a in range(len(gx) - 1):
                for b in range(len(gy) - 1):
                    if rng.random() < 0.12: continue
                    fx0, fx1 = gx[a] + 1.5, gx[a + 1] - 1.5
                    fy0, fy1 = gy[b] + 1.5, gy[b + 1] - 1.5
                    if fx1 - fx0 < 6 or fy1 - fy0 < 6: continue
                    hoch = float(np.clip(rng.lognormal(math.log(13), 0.5), 5, 48))
                    if (fy0 + fy1) / 2 < 420: hoch = min(hoch, 12)       # Sicht auf Abbiegung
                    # Haeuser zwischen Kamera und Route verdecken sie mit ihren
                    # Daechern (gemessen: 190 px der Abbiegung weg) -> niedrig halten
                    if fy1 < 300 and -20 < fx1 and fx0 < xr + 20: hoch = min(hoch, 6)
                    if -10 < fx0 and fx1 < xr and fy0 > 250: hoch = min(hoch, 8)
                    gebaeude.append((fx0, fy0, fx1, fy1, hoch))
    e, d = bild.ebene()
    for fx0, fy0, fx1, fy1, hoch in gebaeude:
        g = 3.0
        d.polygon(P(*kam.poly([(fx0 - g, fy0 - g, 0), (fx1 + g, fy0 - g, 0), (fx1 + g, fy1 + g, 0), (fx0 - g, fy1 + g, 0)])), fill=(0, 0, 0, 150))
    e = e.filter(ImageFilter.GaussianBlur(3 * SS))
    bild.auf(e)

    C = kam.C
    gebaeude.sort(key=lambda g: -math.hypot((g[0] + g[2]) / 2 - C[0], (g[1] + g[3]) / 2 - C[1]))
    GRUND = np.array([44, 54, 74], float)
    e, d = bild.ebene()
    for fx0, fy0, fx1, fy1, hoch in gebaeude:
        t = kam.tiefe(((fx0 + fx1) / 2, (fy0 + fy1) / 2, hoch / 2))
        if t < 5: continue
        seiten = []
        if C[1] < fy0: seiten.append(([(fx0, fy0), (fx1, fy0)], 1.00))     # zur Kamera
        if C[0] < fx0: seiten.append(([(fx0, fy1), (fx0, fy0)], 0.74))
        if C[0] > fx1: seiten.append(([(fx1, fy0), (fx1, fy1)], 0.82))
        for (a, b), hell in seiten:
            # drei Baender: unten dunkler (Umgebungsverdeckung am Boden)
            for z0, z1, ao in ((0, 0.22, 0.72), (0.22, 0.55, 0.88), (0.55, 1.0, 1.0)):
                c = GRUND * hell * ao
                q = [(a[0], a[1], hoch * z0), (b[0], b[1], hoch * z0), (b[0], b[1], hoch * z1), (a[0], a[1], hoch * z1)]
                d.polygon(P(*kam.poly(q)), fill=nebelfarbe(c, t, KARTE_DUNST, nebel) + (255,))
        dach = [(fx0, fy0, hoch), (fx1, fy0, hoch), (fx1, fy1, hoch), (fx0, fy1, hoch)]
        d.polygon(P(*kam.poly(dach)), fill=nebelfarbe(GRUND * 1.32, t, KARTE_DUNST, nebel) + (255,))
        # Dachkante: feine Lichtkante zur Kamera hin
        kante = nebelfarbe((120, 138, 170), t, KARTE_DUNST, nebel * 0.8)
        d.line(P(*kam.poly([(fx0, fy0, hoch), (fx1, fy0, hoch)])), fill=kante + (200,), width=SS)
    bild.auf(e)
    # Route durch die Haeuser hindurch angedeutet, wie es Serien-Navis tun
    e, d = bild.ebene()
    for a, b in stuecke:
        d.polygon(P(*kam.poly(quad_band(a, b, 11, 0.2))), fill=BLAU + (255,))
    bild.auf(e, deckung=0.28)

    # --- eigene Position: Lichtfleck am Boden, Pfeil
    e, d = bild.ebene()
    kreis = [(math.cos(w) * 16, pfeil_y + math.sin(w) * 16, 0.4) for w in np.linspace(0, 2 * math.pi, 40)]
    d.polygon(P(*kam.poly(kreis)), fill=BLAU + (110,))
    bild.auf(e, leuchten=0.8, r=8)
    e, d = bild.ebene()
    pf = [(0, pfeil_y + 11, 1.0), (-7.5, pfeil_y - 7, 1.0), (0, pfeil_y - 2.5, 1.0), (7.5, pfeil_y - 7, 1.0)]
    sch = [(p[0] + 1.2, p[1] - 1.8, 0.2) for p in pf]
    d.polygon(P(*kam.poly(sch)), fill=(0, 0, 0, 140))
    d.polygon(P(*kam.poly(pf)), fill=(250, 252, 255, 255), outline=BLAU + (255,), width=2 * SS)
    bild.auf(e)

    # --- Ziel-Nadel am Routenende (Bildschirmraum), Strassenname
    zx, zy = kam.bild((xr, ziel_y, 0))
    e, d = bild.ebene()
    d.line(P((zx, zy), (zx, zy - 30)), fill=(230, 240, 255, 255), width=3 * SS)
    d.ellipse([(zx - 13) * SS, (zy - 52) * SS, (zx + 13) * SS, (zy - 26) * SS], fill=CYAN + (255,))
    d.ellipse([(zx - 5) * SS, (zy - 44) * SS, (zx + 5) * SS, (zy - 34) * SS], fill=(8, 20, 34, 255))
    bild.auf(e, leuchten=0.9, r=8)
    nx_, ny_ = kam.bild((xr * 0.55, 300, 0))
    bild.zuschnitt = None
    sichtbar = x0 + 40 < zx < x1 - 40 and y0 + 90 < zy < y1
    return kam, (nx_, ny_ - 34), ((zx, zy - 52) if sichtbar else None)


def pille(bild, mitte, s, px=22, farbe=TEXT, grund=(14, 20, 32), alpha=220):
    f = schrift(px, 600)
    l, t, r, b = f.getbbox(s)
    w, h = (r - l) / SS + 28, px + 16
    x, y = mitte
    e, d = bild.ebene()
    d.rounded_rectangle([(x - w / 2) * SS, (y - h / 2) * SS, (x + w / 2) * SS, (y + h / 2) * SS], radius=h / 2 * SS, fill=grund + (alpha,), outline=(255, 255, 255, 40), width=SS)
    text(d, (x, y), s, px, farbe, 600, 'mm')
    bild.auf(e)


def abbiegepfeil(d, x, y, g, farbe=TEXT):
    """Pfeil 'rechts abbiegen' in einem g x g Feld (Bildschirm)."""
    w = g * 0.13
    d.line(P((x + g * 0.34, y + g * 0.92), (x + g * 0.34, y + g * 0.46)), fill=farbe + (255,), width=int(w * SS))
    d.arc([(x + g * 0.34 - w / 2) * SS, (y + g * 0.30) * SS, (x + g * 0.34 + g * 0.40) * SS, (y + g * 0.30 + g * 0.40) * SS],
          180, 270, fill=farbe + (255,), width=int(w * SS))
    d.line(P((x + g * 0.53, y + g * 0.30 + w / 2 - 1), (x + g * 0.66, y + g * 0.30 + w / 2 - 1)), fill=farbe + (255,), width=int(w * SS))
    s = y + g * 0.30 + w / 2 - 1
    d.polygon(P((x + g * 0.62, s - g * 0.20), (x + g * 0.90, s), (x + g * 0.62, s + g * 0.20)), fill=farbe + (255,))


# --------------------------------------------------------------- Fahrspuren
def auto_laden(p):
    if not p or not os.path.exists(p):
        return None
    im = Image.open(p).convert('RGBA')
    return im.crop(im.getchannel('A').getbbox())


def fahrspur(bild, box, auto, voraus=True):
    """Fahrerassistenz-Ansicht: eigene Spur, Nachbarspuren, erkanntes Fahrzeug."""
    x0, y0, x1, y1 = box
    kam = Kamera((0, -6.2, 2.7), (0, 40, 0), 58, box)
    X0, Y0, X1, Y1 = [int(v * SS) for v in box]
    vorher = bild.a[Y0:Y1, X0:X1].copy()
    bild.zuschnitt = box
    nebel = 70.0
    dunst = (14, 20, 32)
    # Strasse als Verlauf: vorn dunkles Grau, zur Ferne in den Dunst
    e, d = bild.ebene()
    for a, b in zerteilen((0, -1.5), (0, 160), 2.0):
        t = kam.tiefe((0, (a[1] + b[1]) / 2, 0))
        c = nebelfarbe((26, 32, 44), t, dunst, nebel)
        d.polygon(P(*kam.poly([(-7.5, a[1], 0), (7.5, a[1], 0), (7.5, b[1], 0), (-7.5, b[1], 0)])), fill=c + (255,))
    bild.auf(e)
    # Spurlinien: Nachbarspuren grau gestrichelt, eigene Spur blau leuchtend
    e, d = bild.ebene()
    for x in (-5.25, 5.25):
        for a, b in zerteilen((x, -1.5), (x, 160), 9.0):
            if int(a[1] // 9.0) % 2: continue
            t = kam.tiefe((x, a[1], 0))
            d.polygon(P(*kam.poly(quad_band(a, (b[0], a[1] + 3.0), 0.16))), fill=nebelfarbe((120, 130, 150), t, dunst, nebel) + (255,))
    bild.auf(e)
    e, d = bild.ebene()
    for x in (-1.75, 1.75):
        for a, b in zerteilen((x, -1.0), (x, 120), 2.0):
            t = kam.tiefe((x, a[1], 0))
            f = math.exp(-max(t, 0) / 60)
            d.polygon(P(*kam.poly(quad_band(a, b, 0.20))), fill=BLAU_HELL + (int(255 * f),))
    bild.auf(e, leuchten=1.1, r=5)
    # Abstandsbalken zwischen eigenem Auto und Vorausfahrendem
    if voraus:
        e, d = bild.ebene()
        for k, yb in enumerate(np.arange(8.0, 28.0, 3.2)):
            al = int(200 * (1 - k / 7))
            d.polygon(P(*kam.poly([(-1.35, yb, 0.02), (1.35, yb, 0.02), (1.35, yb + 1.1, 0.02), (-1.35, yb + 1.1, 0.02)])), fill=BLAU + (al,))
        bild.auf(e, leuchten=0.6, r=5)
    # Fahrzeuge: erst das vorausfahrende, dann das eigene (Painter)
    if auto is not None:
        def setzen(y_heck, x_mitte=0.0, hell=1.0, breite_m=2.02):
            links = kam.bild((x_mitte - breite_m / 2, y_heck, 0)); rechts = kam.bild((x_mitte + breite_m / 2, y_heck, 0))
            w = max(4, int((rechts[0] - links[0]) * SS))
            h = max(3, int(w * auto.height / auto.width))
            im = auto.resize((w, h), Image.LANCZOS)
            if hell != 1.0:
                a = np.asarray(im).astype(np.float32)
                grau = a[..., :3].mean(axis=2, keepdims=True)
                a[..., :3] = (a[..., :3] * 0.35 + grau * 0.65) * hell * np.array([0.86, 0.92, 1.0])
                im = Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))
            boden_x, boden_y = kam.bild((x_mitte, y_heck, 0))
            # Kontaktschatten
            e, d = bild.ebene()
            sw = w / SS * 0.62
            d.ellipse([(boden_x - sw) * SS, (boden_y - sw * 0.10) * SS, (boden_x + sw) * SS, (boden_y + sw * 0.16) * SS], fill=(0, 0, 0, 200))
            bild.auf(e.filter(ImageFilter.GaussianBlur(max(1, w * 0.035))))
            e, d = bild.ebene()
            e.alpha_composite(im, (int(boden_x * SS - w / 2), int(boden_y * SS - h * 0.97)))
            bild.auf(e)
        if voraus:
            setzen(30.0, 0.0, hell=0.62)
            setzen(58.0, -3.5, hell=0.45)
        setzen(0.0)
    bild.zuschnitt = None
    # Raender weich in den Grund: seitlich breit, oben (Ferne) und unten schmal
    xx = np.linspace(0, 1, X1 - X0, dtype=np.float32)[None]
    yy = np.linspace(0, 1, Y1 - Y0, dtype=np.float32)[:, None]
    m = np.clip(np.minimum(xx, 1 - xx) / 0.22, 0, 1) * np.clip(yy / 0.25, 0, 1) * np.clip((1 - yy) / 0.08, 0, 1)
    m = (m * m * (3 - 2 * m))[..., None]
    bild.a[Y0:Y1, X0:X1] = bild.a[Y0:Y1, X0:X1] * m + vorher * (1 - m)
    return kam


# ---------------------------------------------------------------- Displays
def kombi_klein(ziel, auto=None):
    """Kleinwagen, 1280 x 490: Tacho | Fahrspur | Drehzahl."""
    B = Bild(1280, 490)
    B.verlauf((0, 0, 1280, 490), (8, 11, 18), SCHWARZ)
    rundinstrument(B, (262, 258), 200, 48 / 220, list(range(0, 221, 20)), '48', 'km/h', 'Tempo')
    rundinstrument(B, (1018, 258), 200, 1.9 / 7, [0, 1, 2, 3, 4, 5, 6, 7], '1,9', '× 1000 1/min', 'Tank 72 %  ·  612 km',
                   c0=(40, 110, 220), c1=(120, 200, 255))
    fahrspur(B, (478, 96, 802, 372), auto)
    e, d = B.ebene()
    text(d, (640, 44), '10:24', 38, TEXT, 600, 'mm')
    text(d, (640, 80), '21 °C', 24, TEXT2, 500, 'mm')
    # Fahrstufe
    for i, g in enumerate('PRND'):
        x = 574 + i * 44
        akt = g == 'D'
        if akt:
            d.rounded_rectangle([(x - 19) * SS, 392 * SS, (x + 19) * SS, 436 * SS], radius=10 * SS, fill=(24, 34, 52, 255))
        text(d, (x, 414), g, 32 if akt else 26, TEXT if akt else TEXT3, 700 if akt else 500, 'mm')
    text(d, (640, 466), '48 213 km', 22, TEXT2, 500, 'mm')
    B.auf(e)
    # Tempolimit-Schild
    e, d = B.ebene()
    d.ellipse([518 * SS, 22 * SS, 578 * SS, 82 * SS], fill=(245, 245, 245, 255), outline=(214, 30, 36, 255), width=7 * SS)
    text(d, (548, 52), '50', 27, (20, 20, 24), 700, 'mm')
    B.auf(e)
    B.speichern(os.path.join(ziel, 'display-kombi.png'))


def mitte_klein(ziel):
    """Kleinwagen, 1280 x 772: vollflaechige 3D-Navigation."""
    B = Bild(1280, 772)
    kam, strasse, zielpunkt = karte3d(B, (0, 0, 1280, 772), seed=5, fov=62, pfeil_y=70, versatz=-110, ziel_y=2600)
    # Lesbarkeit oben: dunkler Verlauf unter der Statusleiste
    B.verlauf((0, 0, 1280, 120), (0, 0, 0), (0, 0, 0), 0.72, 0.0)
    B.verlauf((0, 600, 1280, 682), (0, 0, 0), (0, 0, 0), 0.0, 0.45)
    pille(B, strasse, 'Via Atenea', 24)
    if zielpunkt:
        pille(B, (zielpunkt[0], zielpunkt[1] - 30), 'Agrigento', 24, CYAN)
    e, d = B.ebene()
    text(d, (36, 40), '10:24', 30, TEXT, 600, 'lm')
    text(d, (1244, 40), '21 °C', 30, TEXT, 500, 'rm')
    B.auf(e)
    # Abbiegehinweis (Mattglas)
    B.glas((32, 88, 520, 290), 26)
    e, d = B.ebene()
    d.rounded_rectangle([56 * SS, 112 * SS, 206 * SS, 262 * SS], radius=20 * SS, fill=BLAU + (255,))
    abbiegepfeil(d, 66, 118, 132)
    text(d, (232, 158), '300 m', 60, TEXT, 700, 'lm')
    text(d, (232, 222), 'Via Atenea', 32, TEXT2, 500, 'lm')
    B.auf(e)
    # Ankunft (Mattglas)
    B.glas((32, 520, 520, 660), 26)
    e, d = B.ebene()
    text(d, (60, 560), 'Ankunft 10:36', 36, TEXT, 600, 'lm')
    text(d, (60, 618), '12 min  ·  8,4 km', 32, CYAN, 500, 'lm')
    B.auf(e)
    # Tempolimit und aktuelles Tempo
    B.glas((1098, 520, 1248, 660), 26)
    e, d = B.ebene()
    d.ellipse([1118 * SS, 548 * SS, 1200 * SS, 630 * SS], fill=(245, 245, 245, 255), outline=(214, 30, 36, 255), width=9 * SS)
    text(d, (1159, 589), '50', 34, (20, 20, 24), 700, 'mm')
    text(d, (1224, 570), '48', 30, TEXT, 600, 'mm')
    text(d, (1224, 604), 'km/h', 16, TEXT2, 500, 'mm')
    B.auf(e)
    # Leiste unten
    B.glas((0, 682, 1280, 772), 0, dunkel=0.30, unschaerfe=24, rand=0.0)
    e, d = B.ebene()
    d.line(P((0, 682), (1280, 682)), fill=(255, 255, 255, 30), width=SS)
    for i, s in enumerate(('Karte', 'Musik', 'Telefon', 'Fahrzeug', 'Klima 21,5°')):
        x = 128 + i * 256
        if i == 0:
            d.rounded_rectangle([(x - 96) * SS, 700 * SS, (x + 96) * SS, 754 * SS], radius=27 * SS, fill=(30, 58, 104, 255))
        text(d, (x, 727), s, 30, TEXT if i == 0 else TEXT2, 600 if i == 0 else 500, 'mm')
    B.auf(e)
    B.speichern(os.path.join(ziel, 'display-mitte.png'))


def cover(b, h, rng=np.random.default_rng(21)):
    """Plattenhuelle ohne fremdes Motiv: weiche Farbflecken, Korn."""
    yy, xx = np.mgrid[0:h, 0:b].astype(np.float32)
    a = np.zeros((h, b, 3), np.float32) + np.array([20, 16, 40], np.float32)
    for c, (mx, my, r) in zip(((230, 120, 60), (60, 90, 220), (200, 60, 120)), ((0.3, 0.35, 0.5), (0.75, 0.7, 0.55), (0.6, 0.2, 0.35))):
        g = np.exp(-(((xx / b - mx) ** 2 + (yy / h - my) ** 2) / (r * r * 0.35)))
        a += g[..., None] * np.array(c, np.float32) * 0.8
    a += (rng.random(a.shape) - 0.5) * 10
    return np.clip(a, 0, 255) / 255


def breit_mittel(ziel, auto=None):
    """Mittelklasse, 2400 x 457: Fahrerbereich | Navigation | Medien."""
    B = Bild(2400, 457)
    B.verlauf((0, 0, 2400, 457), (9, 12, 20), SCHWARZ)
    # --- Fahrerbereich
    fahrspur(B, (300, 40, 860, 440), auto)
    e, d = B.ebene()
    text(d, (40, 44), '10:24', 30, TEXT2, 500, 'lm')
    text(d, (150, 212), '48', 170, TEXT, 600, 'mm')
    text(d, (150, 318), 'km/h', 34, TEXT2, 500, 'mm')
    for i, g in enumerate('PRND'):
        x = 64 + i * 58
        akt = g == 'D'
        if akt:
            d.rounded_rectangle([(x - 24) * SS, 372 * SS, (x + 24) * SS, 424 * SS], radius=12 * SS, fill=(24, 34, 52, 255))
        text(d, (x, 398), g, 36 if akt else 28, TEXT if akt else TEXT3, 700 if akt else 500, 'mm')
    B.auf(e, leuchten=0.08, r=6)
    # Reichweite als feiner Balken
    e, d = B.ebene()
    d.rounded_rectangle([40 * SS, 346 * SS, 262 * SS, 352 * SS], radius=3 * SS, fill=(34, 42, 58, 255))
    d.rounded_rectangle([40 * SS, 346 * SS, 200 * SS, 352 * SS], radius=3 * SS, fill=BLAU + (255,))
    text(d, (270, 349), '640 km', 22, TEXT2, 500, 'lm')
    B.auf(e)
    # Tempolimit
    e, d = B.ebene()
    d.ellipse([300 * SS, 34 * SS, 360 * SS, 94 * SS], fill=(245, 245, 245, 255), outline=(214, 30, 36, 255), width=7 * SS)
    text(d, (330, 64), '50', 27, (20, 20, 24), 700, 'mm')
    B.auf(e)
    # --- Navigation
    kam, strasse, zielpunkt = karte3d(B, (890, 0, 1890, 457), seed=9, fov=66, pfeil_y=60, versatz=-70, ziel_y=2600)
    pille(B, strasse, 'Via Atenea', 22)
    B.glas((910, 20, 1330, 170), 22)
    e, d = B.ebene()
    d.rounded_rectangle([926 * SS, 36 * SS, 1044 * SS, 154 * SS], radius=16 * SS, fill=BLAU + (255,))
    abbiegepfeil(d, 933, 40, 104)
    text(d, (1066, 76), '300 m', 50, TEXT, 700, 'lm')
    text(d, (1066, 132), 'Via Atenea', 28, TEXT2, 500, 'lm')
    B.auf(e)
    B.glas((1540, 350, 1872, 440), 22)
    e, d = B.ebene()
    text(d, (1562, 380), 'Agrigento  10:36', 28, TEXT, 600, 'lm')
    text(d, (1562, 418), '12 min  ·  8,4 km', 26, CYAN, 500, 'lm')
    B.auf(e)
    # weiche Kanten links/rechts der Karte ins Schwarz
    for xa, xb, links in ((890, 950, True), (1830, 1890, False)):
        t = np.linspace(0, 1, (xb - xa) * SS, dtype=np.float32)
        al = (1 - t) if links else t
        teil = B.a[:, xa * SS:xb * SS]
        B.a[:, xa * SS:xb * SS] = teil * (1 - al[None, :, None] * 0.9) + np.array(SCHWARZ, np.float32) / 255 * al[None, :, None] * 0.9
    # --- Medien
    x0 = 1930
    c = cover(170 * SS, 170 * SS)
    maske = Image.new('L', (170 * SS, 170 * SS), 0)
    ImageDraw.Draw(maske).rounded_rectangle([0, 0, 170 * SS - 1, 170 * SS - 1], radius=18 * SS, fill=255)
    m = np.asarray(maske).astype(np.float32)[..., None] / 255
    ys, xs_ = 40 * SS, x0 * SS
    B.a[ys:ys + 170 * SS, xs_:xs_ + 170 * SS] = B.a[ys:ys + 170 * SS, xs_:xs_ + 170 * SS] * (1 - m) + c * m
    e, d = B.ebene()
    text(d, (x0 + 196, 70), 'Radio', 26, TEXT2, 500, 'lm')
    text(d, (x0 + 196, 116), 'Jazz am Abend', 32, TEXT, 600, 'lm')
    text(d, (x0 + 196, 160), 'Live aus Palermo', 24, TEXT2, 500, 'lm')
    d.rounded_rectangle([x0 * SS, 244 * SS, (x0 + 430) * SS, 250 * SS], radius=3 * SS, fill=(34, 42, 58, 255))
    d.rounded_rectangle([x0 * SS, 244 * SS, (x0 + 250) * SS, 250 * SS], radius=3 * SS, fill=TEXT + (255,))
    d.ellipse([(x0 + 242) * SS, 239 * SS, (x0 + 258) * SS, 255 * SS], fill=TEXT + (255,))
    # Wiedergabe-Knoepfe
    for dx, kind in ((80, 'zurueck'), (215, 'pause'), (350, 'vor')):
        cx = x0 + dx; cy = 312
        if kind == 'pause':
            d.ellipse([(cx - 30) * SS, (cy - 30) * SS, (cx + 30) * SS, (cy + 30) * SS], fill=TEXT + (255,))
            d.rectangle([(cx - 10) * SS, (cy - 12) * SS, (cx - 4) * SS, (cy + 12) * SS], fill=SCHWARZ + (255,))
            d.rectangle([(cx + 4) * SS, (cy - 12) * SS, (cx + 10) * SS, (cy + 12) * SS], fill=SCHWARZ + (255,))
        else:
            s = -1 if kind == 'zurueck' else 1
            for o in (0, 14):
                d.polygon(P((cx - s * 12 + s * o, cy - 12), (cx + s * 2 + s * o, cy), (cx - s * 12 + s * o, cy + 12)), fill=TEXT + (255,))
    d.line(P((x0, 368), (x0 + 430, 368)), fill=(255, 255, 255, 26), width=SS)
    text(d, (x0, 410), 'Klima', 26, TEXT2, 500, 'lm')
    text(d, (x0 + 430, 410), '21,5 °C', 38, TEXT, 600, 'rm')
    B.auf(e)
    # feine Trenner
    e, d = B.ebene()
    for x in (876, 1908):
        for k in range(40):
            al = int(34 * math.sin(math.pi * k / 39))
            d.line(P((x, 30 + k * 10), (x, 40 + k * 10)), fill=(255, 255, 255, al), width=SS)
    B.auf(e)
    B.speichern(os.path.join(ziel, 'display-breit.png'))


if __name__ == '__main__':
    z = sys.argv[1] if len(sys.argv) > 1 else '.'
    os.makedirs(z, exist_ok=True)
    a_klein = auto_laden(sys.argv[2] if len(sys.argv) > 2 else None)
    a_mittel = auto_laden(sys.argv[3] if len(sys.argv) > 3 else None)
    kombi_klein(z, a_klein)
    mitte_klein(z)
    breit_mittel(z, a_mittel)
