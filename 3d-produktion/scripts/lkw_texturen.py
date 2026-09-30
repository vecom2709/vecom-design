"""lkw_texturen.py -- Bildtexturen fuer den neuen Sattelzug (lkw_hd.py).

Alles als Karten, die glTF mitnimmt: Das Poster rechnet Cycles aus dem
exportierten GLB, das Web zeigt dasselbe GLB -- was nur als Shader-Knoten
existierte, fehlte im Web.

  lkw-lack-normal        Orangenhaut der Lackierung (Kachel 0,30 m)
  lkw-kunststoff-normal  Narbung der Kunststoffteile (Kachel 0,20 m)
  lkw-riffel-normal      Alu-Riffelblech der Stufen (Kachel 0,20 m)
  lkw-gewebe-normal      PVC-beschichtetes Gewebe der Plane (Kachel 0,10 m)
  lkw-reifen-zug-normal / -auflieger-normal / -farbe
                         Profilbloecke, Lamellen und erhabene Flankenschrift;
                         u = Umfang, v = Querschnitt wie in lkw_profil.py

Keine fremden Marken: Reifen und LKW tragen nur VECOM bzw. Gattungsangaben.
Aufruf: python3 lkw_texturen.py <zielordner>
"""
import math, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from pr_texturen import _prausch, _normal, _bild
import lkw_profil as LP

RNG = np.random.default_rng(20260930)
FETT = '/usr/share/fonts/truetype/google-fonts/Poppins-Bold.ttf'
MITTEL = '/usr/share/fonts/truetype/google-fonts/Poppins-Medium.ttf'


def speichern(ziel, name, n8):
    Image.fromarray(n8).save(os.path.join(ziel, name + '.png'), optimize=True)


def lack(ziel, n=1024):
    # Orangenhaut: Wellen um 2-4 mm, sehr flach (sonst sieht es nach Hammerschlag aus)
    h = _prausch(n, n, 7.0, rng=RNG) * 1.0 + _prausch(n, n, 2.2, rng=RNG) * 0.25
    speichern(ziel, 'lkw-lack-normal', _normal(h, 0.9))


def kunststoff(ziel, n=1024):
    # Narbung: feine Koernung plus weiche Mulden (spritzgegossener PP)
    h = _prausch(n, n, 1.2, rng=RNG) * 0.6 + _prausch(n, n, 4.0, rng=RNG) * 0.8
    h = np.tanh(h * 0.9)
    speichern(ziel, 'lkw-kunststoff-normal', _normal(h, 2.2))


def riffel(ziel, n=1024, kachel=0.20):
    """Quintett-Riffelblech: Linsen 28 x 6 mm, abwechselnd 45/-45 Grad."""
    px = n / kachel
    y, x = np.mgrid[0:n, 0:n].astype(np.float32) / px          # Meter
    h = np.zeros((n, n), np.float32)
    teil = 0.025                                               # Raster 25 mm
    for gx in range(int(kachel / teil) + 1):
        for gy in range(int(kachel / teil) + 1):
            cx, cy = gx * teil, gy * teil
            winkel = math.radians(45 if (gx + gy) % 2 == 0 else -45)
            c, s = math.cos(winkel), math.sin(winkel)
            for ox in (-kachel, 0, kachel):
                for oy in (-kachel, 0, kachel):
                    dx = x - (cx + ox); dy = y - (cy + oy)
                    u = dx * c + dy * s; v = -dx * s + dy * c
                    d = (u / 0.014) ** 2 + (v / 0.003) ** 2
                    h = np.maximum(h, np.clip(1 - d, 0, 1) ** 0.5)
    h = np.asarray(Image.fromarray((h * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.2))).astype(np.float32) / 255
    h += _prausch(n, n, 1.0, rng=RNG) * 0.02
    speichern(ziel, 'lkw-riffel-normal', _normal(h, 9.0))


def gewebe(ziel, n=1024, kachel=0.10):
    """Plane: Polyestergewebe 1100 dtex unter PVC -- Faeden kaum sichtbar,
    nur als feines Raster im Glanz; dazu weiche Wellen der Beschichtung."""
    px = n / kachel
    y, x = np.mgrid[0:n, 0:n].astype(np.float32) / px
    f = 1 / 0.0011                                             # ~0,9 Faeden je mm
    h = 0.5 * np.sin(2 * np.pi * x * f) * np.sin(2 * np.pi * y * f * 0.5) ** 2
    h += _prausch(n, n, 3.0, rng=RNG) * 0.35
    speichern(ziel, 'lkw-gewebe-normal', _normal(h, 1.4))


def reifen(ziel, art, B=4096, H=1024):
    d = LP.REIFEN[art]
    P, A = LP.reifen(**d)
    ber = LP.bereiche(P, A)
    ra = d['flanke'] + 0.5715 / 2
    umfang = 2 * math.pi * ra
    h = np.zeros((H, B), np.float32)
    farbe = np.full((H, B), 0.018, np.float32)
    v = 1 - (np.arange(H) + 0.5) / H                           # Bildzeile -> v (Blender: v=0 unten)
    # --- Laufflaeche: Querlamellen (Sipes) und Blockkanten, Stollen nach Umfang
    l0, l1 = ber['lauf']
    in_lauf = (v >= l0) & (v <= l1)
    u = (np.arange(B) + 0.5) / B
    s_mm = u * umfang * 1000                                   # Umfangsweg in mm
    teil = 38.0                                                # Blockteilung 38 mm
    phase = (s_mm % teil) / teil
    for zeile in np.where(in_lauf)[0]:
        vv = (v[zeile] - l0) / (l1 - l0)
        versatz = 0.5 * (int(vv * 4) % 2)                      # Bloecke je Rippe versetzt
        p = (phase + versatz) % 1
        lam = np.exp(-((p - 0.5) / 0.03) ** 2)                 # Lamelle quer
        h[zeile] -= 0.9 * lam
    # Abrieb: fein und leicht glaenzender auf der Lauffläche
    h += _prausch(H, B, 1.5, rng=RNG) * 0.12
    # --- Flanke aussen: erhabene Schrift, zweimal am Umfang
    f0, f1 = ber['flanke_a']
    vm = f0 + (f1 - f0) * 0.42                                 # Mitte der Flanke (etwas nach aussen)
    zeile_m = int((1 - vm) * H)
    # Pixel je Meter: quer (v) haengt an der Bogenlaenge des ganzen Profils
    pr = np.asarray(P); s = np.r_[0, np.cumsum(np.hypot(np.diff(pr[:, 0]), np.diff(pr[:, 1])))]
    px_v = H / s[-1]; px_u = B / umfang
    # Gesetzt wird auf einer Leinwand im Massstab px_v (quadratische Pixel),
    # danach in u auf B gestaucht -- so stimmt das Seitenverhaeltnis der Buchstaben.
    Wc = int(B * px_v / px_u)
    txt = Image.new('L', (Wc, H), 0); dr = ImageDraw.Draw(txt)
    gross = 'VECOM TRACTION' if art == 'zug' else 'VECOM TRAILER'
    zeile2 = '315/70 R22.5  154/150L  M+S  3PMSF' if art == 'zug' else '385/65 R22.5  164K  REGROOVABLE'
    f_gross = ImageFont.truetype(FETT, int(0.034 * px_v)); f_klein = ImageFont.truetype(MITTEL, int(0.014 * px_v))
    for k in range(2):
        x0 = int(Wc * (0.06 + 0.5 * k))
        dr.text((x0, zeile_m), gross, font=f_gross, fill=255, anchor='lm')
        x1 = x0 + int(0.50 * px_v)
        dr.text((x1, zeile_m - int(0.010 * px_v)), zeile2, font=f_klein, fill=255, anchor='lm')
        dr.text((x1, zeile_m + int(0.012 * px_v)), 'TUBELESS  ·  STEEL BELTED RADIAL', font=f_klein, fill=210, anchor='lm')
    # Von aussen gesehen laeuft u gegen die Leserichtung, und die Buchstaben
    # stehen mit dem Fuss zur Felge (v waechst zur Wulst): 180 Grad gedreht und
    # waagerecht gespiegelt = senkrecht gespiegelt.
    txt = txt.resize((B, H), Image.LANCZOS).transpose(Image.FLIP_TOP_BOTTOM).filter(ImageFilter.GaussianBlur(0.8))
    t = np.asarray(txt).astype(np.float32) / 255
    h += t * 1.6
    farbe = farbe * (1 + 0.25 * t)                            # erhabene Schrift etwas heller (Abrieb)
    # Flanke: feine Riefen (Formtrennung)
    fl = (v >= f0) & (v <= f1)
    h[fl] += 0.04 * np.sin(np.arange(B) * 2 * np.pi / 6)[None, :]
    speichern(ziel, f'lkw-reifen-{art}-normal', _normal(h, 2.4))
    farbe += _prausch(H, B, 6, rng=RNG) * 0.003
    _bild(ziel, f'lkw-reifen-{art}-farbe', np.repeat(np.clip(farbe, 0, 1)[..., None], 3, -1))


def alle(ziel):
    os.makedirs(ziel, exist_ok=True)
    lack(ziel); kunststoff(ziel); riffel(ziel); gewebe(ziel)
    for art in ('zug', 'auflieger'):
        reifen(ziel, art)
    kennzeichen(ziel); waben(ziel)


if __name__ == '__main__':
    alle(sys.argv[1] if len(sys.argv) > 1 else 'tex')


def kennzeichen(ziel, B=1040, H=220):
    """Italienisches Kennzeichen (Format ab 1999, erfundene Nummer): weiss,
    links blaues Feld mit Sternenkreis und I, rechts blaues Feld."""
    im = Image.new('RGB', (B, H), (246, 246, 244)); d = ImageDraw.Draw(im)
    blau = (0, 51, 153)
    d.rectangle([0, 0, 88, H], fill=blau); d.rectangle([B - 88, 0, B, H], fill=blau)
    for k in range(12):
        a = 2 * math.pi * k / 12
        x, y = 44 + 26 * math.cos(a), 70 + 26 * math.sin(a)
        d.regular_polygon((x, y, 5), 5, fill=(255, 204, 0))
    d.text((44, 170), 'I', font=ImageFont.truetype(FETT, 44), fill=(255, 255, 255), anchor='mm')
    d.text((B / 2, H / 2 + 6), 'VD 260 CM', font=ImageFont.truetype(MITTEL, 150), fill=(12, 12, 12), anchor='mm')
    d.rectangle([2, 2, B - 3, H - 3], outline=(20, 20, 20), width=4)
    im.save(os.path.join(ziel, 'lkw-kennzeichen-farbe.png'))


def waben(ziel, n=1024, kachel=0.12):
    """Wabengitter hinter den Grilllamellen (Zellen 12 mm, Stege 2 mm):
    Hoehe = Steg, Zellen tief -- als Normalkarte; dazu eine Farbkarte, in
    der die Zellen fast schwarz sind (Tiefe) und die Stege matt grau."""
    px = n / kachel
    y, x = np.mgrid[0:n, 0:n].astype(np.float32) / px
    a = 0.012                                          # Zellweite
    # Hexagonraster: Abstand zum naechsten Zellmittelpunkt
    s3 = math.sqrt(3)
    qx = x / (a); qy = y / (a * s3 / 2)
    best = np.full((n, n), 9.0, np.float32)
    for oy in range(-1, 2):
        for ox in range(-1, 2):
            ry = np.floor(qy) + oy
            cx = (np.floor(qx - (ry % 2) * 0.5) + ox + (ry % 2) * 0.5) * a
            cy = ry * a * s3 / 2
            dx = x - cx; dy = y - cy
            # Sechseck-Abstand
            d = np.maximum(np.abs(dx), np.abs(dx) / 2 + np.abs(dy) * s3 / 2)   # spitz oben, Nachbarn in x
            best = np.minimum(best, d)
    steg = np.clip((best - a * 0.445) / (a * 0.03), 0, 1)
    h = steg.astype(np.float32)
    h = np.asarray(Image.fromarray((h * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.0))).astype(np.float32) / 255
    speichern(ziel, 'lkw-waben-normal', _normal(h, 6.0))
    farbe = 0.006 + 0.030 * h
    _bild(ziel, 'lkw-waben-farbe', np.repeat(farbe[..., None], 3, -1))
