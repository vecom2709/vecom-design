#!/usr/bin/env python3
"""Haarfarben-Demo: gerechnete Drehbilder (pr_haar.py dreh) -> WebP fuer die Seite.

Aufruf:
  python3 tools/haar-web.py <ordner render/haar>

Erwartet <ordner>/<farbe>/dreh-00.png .. dreh-15.png (1600 x 900) und schreibt
  assets/img/erlebnis/haar/<farbe>/gross/dreh-XX.webp   1280 x 720
  assets/img/erlebnis/haar/<farbe>/klein/dreh-XX.webp    640 x 360
  assets/img/erlebnis/haar/poster.{webp,avif}           1600 x 900 (Kastanie, Mitte)
  assets/img/erlebnis/haar/kachel-800.{webp,avif}        800 x 450 (Ausschnitt Kopf)

WARUM 1280 UND NICHT 1600
Haar ist hochfrequent: Jede Straehne kostet Bytes. 16 Bilder je Farbe in 1600
waeren pro Farbe mehrere Megabyte; die Buehne ist auf dem Rechner selten
breiter als 1000 CSS-Pixel. Klein laedt zuerst (schnelles erstes Bild), gross
wird erst uebernommen, wenn der ganze Satz da ist.
"""
import os
import sys

from PIL import Image

FARBEN = ['kastanie', 'schwarz', 'kupfer', 'balayage', 'aschblond', 'rosegold']
N = 16
ZIEL = 'assets/img/erlebnis/haar'
Q_GROSS, Q_KLEIN = 78, 72
AVIF_Q = 60


def speichern(bild, pfad, q, avif=False):
    os.makedirs(os.path.dirname(pfad), exist_ok=True)
    bild.save(pfad + '.webp', 'WEBP', quality=q, method=6)
    if avif:
        bild.save(pfad + '.avif', 'AVIF', quality=AVIF_Q)


def main(quelle):
    summe = {}
    for f in FARBEN:
        for i in range(N):
            png = os.path.join(quelle, f, f'dreh-{i:02d}.png')
            if not os.path.exists(png):
                print('FEHLT', png); continue
            b = Image.open(png).convert('RGB')
            g = b.resize((1280, 720), Image.LANCZOS); k = b.resize((640, 360), Image.LANCZOS)
            speichern(g, os.path.join(ZIEL, f, 'gross', f'dreh-{i:02d}'), Q_GROSS)
            speichern(k, os.path.join(ZIEL, f, 'klein', f'dreh-{i:02d}'), Q_KLEIN)
            summe[f] = summe.get(f, 0) + os.path.getsize(os.path.join(ZIEL, f, 'gross', f'dreh-{i:02d}.webp'))
    mitte = os.path.join(quelle, 'kastanie', f'dreh-{N // 2:02d}.png')
    if os.path.exists(mitte):
        b = Image.open(mitte).convert('RGB')
        speichern(b, os.path.join(ZIEL, 'poster'), 80, avif=True)
        w, h = b.size   # Kachel: Kopf und Schultern, 16:9
        kw = int(w * 0.46); kh = int(kw * 9 / 16); x0 = (w - kw) // 2; y0 = int(h * 0.16)
        speichern(b.crop((x0, y0, x0 + kw, y0 + kh)).resize((800, 450), Image.LANCZOS), os.path.join(ZIEL, 'kachel-800'), 80, avif=True)
    for f, s in summe.items():
        print(f'{f}: {s / 1024:.0f} KB gross (16 Bilder)')


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'render/haar')
