#!/usr/bin/env python3
"""Kartenfotos fuer das Branchen-Karussell -> assets/img/erlebnis/karten/<demo>.{avif,webp}

Aufruf: python3 tools/karten-web.py <ordner mit <demo>.png>

Quellen (04.10.2026, Abendlicht-Look der Vorlage: schwarz, warmes Gold):
  auto schuh wein schmuck lkw gastro kueche  3d-produktion/scripts/karten_nacht.py (Cycles, Nachtstudio)
  salon                                      3d-produktion/metahuman/mh-karte-a.ps1 (Unreal, MetaHuman)
  villa                                      Ruhebild garten-abend (villa), Ausschnitt 5:4
  tisch                                      Ansicht VD-T-V180-EIE (Tisch-Konfigurator), auf 5:4 erweitert
Format 5:4 wie die Bildflaeche der Karte, 960 x 768 (vordere Karte bis ~400 CSS-px
breit, auf 2x-Bildschirmen also knapp 800 Pixel -- 960 laesst Luft fuer 1,2x Zoom).
"""
import os, sys
from PIL import Image, ImageChops, ImageDraw, ImageFilter

ZIEL = 'assets/img/erlebnis/karten'
DEMOS = ['villa', 'auto', 'schuh', 'tisch', 'wein', 'schmuck', 'kueche', 'gastro', 'salon', 'lkw']
# Belichtung im Nachhinein (Faktor auf die Anzeigewerte), gemessen am Mittelwert der Motive
NACH = {'schmuck': 1.2}


def vignette(b, staerke=0.35):
    w, h = b.size
    m = Image.new('L', (w, h), 0); d = ImageDraw.Draw(m)
    d.ellipse((-w * 0.15, -h * 0.2, w * 1.15, h * 1.2), fill=255)
    m = m.filter(ImageFilter.GaussianBlur(w * 0.12))
    dunkel = Image.eval(b, lambda v: int(v * (1 - staerke)))
    return Image.composite(b, dunkel, m)


def main(q):
    os.makedirs(ZIEL, exist_ok=True)
    for d in DEMOS:
        p = os.path.join(q, d + '.png')
        if not os.path.exists(p):
            print('FEHLT', d); continue
        b = Image.open(p).convert('RGB')
        if b.size[0] * 4 != b.size[1] * 5:   # auf 5:4 zuschneiden (mittig)
            w, h = b.size; cw = min(w, int(h * 5 / 4)); ch = int(cw * 4 / 5)
            b = b.crop(((w - cw) // 2, (h - ch) // 2, (w - cw) // 2 + cw, (h - ch) // 2 + ch))
        if d in NACH:
            f = NACH[d]; b = Image.eval(b, lambda v: min(255, int(v * f)))
        if d == 'salon':
            b = vignette(b, 0.45)
        b = b.resize((960, 768), Image.LANCZOS)
        b.save(os.path.join(ZIEL, d + '.webp'), 'WEBP', quality=80, method=6)
        b.save(os.path.join(ZIEL, d + '.avif'), 'AVIF', quality=62)
        print(d, os.path.getsize(os.path.join(ZIEL, d + '.webp')) // 1024, 'KB webp,', os.path.getsize(os.path.join(ZIEL, d + '.avif')) // 1024, 'KB avif')


if __name__ == '__main__':
    main(sys.argv[1])
