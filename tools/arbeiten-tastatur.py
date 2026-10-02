#!/usr/bin/env python3
"""Beschriftung der Laptop-Tastatur für pr_arbeiten.py (03.10.2026).

Aufruf:  python3 tools/arbeiten-tastatur.py <ziel.png>

Zeichnet aus derselben Anordnung wie Blender (3d-produktion/scripts/
pr_tastatur.py) ein RGBA-Bild über die ganze Tastaturwanne: weiße Zeichen
auf durchsichtigem Grund, 20 Pixel je Millimeter. Blender legt es über
Objektkoordinaten auf die Kappen -- die Zeichen sitzen damit genau dort,
wo die Kappen stehen, ohne eigene UVs je Taste.

Warum überhaupt Beschriftung: Ohne Zeichen liest sich das Tastenfeld als
gleichförmiges Raster aus schwarzen Plättchen -- im 100-%-Ausschnitt
(Kamera 70 cm, Tastatur rund 700 Pixel breit) das erste Zeichen von CGI.
"""
import os, sys
from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '3d-produktion', 'scripts'))
import pr_tastatur as T

PX = 20000                     # Pixel je Meter (20 je mm)
SCHRIFT = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
ZEICHEN = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
WORT = {'⌫': 'delete', '⇥': 'tab', '⇪': 'caps lock', '⏎': 'return', '⇧': 'shift', 'ctrl': 'control', 'opt': 'option'}


def main(ziel):
    w = T.WANNE
    x0, y1 = w['cx'] - w['b'] / 2, w['cy'] + w['h'] / 2
    im = Image.new('RGBA', (round(w['b'] * PX), round(w['h'] * PX)), (255, 255, 255, 0))
    d = ImageDraw.Draw(im)
    gross = ImageFont.truetype(SCHRIFT, 84)        # Versalhöhe ~3,0 mm
    klein = ImageFont.truetype(SCHRIFT, 50)        # ~1,8 mm
    sym = ImageFont.truetype(ZEICHEN, 46)
    farbe = (255, 255, 255, 235)
    for k in T.tasten():
        l = (k['x'] - k['b'] / 2 - x0) * PX; r = (k['x'] + k['b'] / 2 - x0) * PX
        o = (y1 - (k['y'] + k['h'] / 2)) * PX; u = (y1 - (k['y'] - k['h'] / 2)) * PX
        t = k['text']
        if not t:
            continue
        if t == '⏻':
            # Ein-Taste: Ring mit Strich, gezeichnet (kein Zeichensatz hat ihn sauber)
            mx, my, rr = (l + r) / 2, (o + u) / 2, 26
            d.arc((mx - rr, my - rr, mx + rr, my + rr), -60, 240, fill=farbe, width=6)
            d.line((mx, my - rr - 4, mx, my - 2), fill=farbe, width=6)
        elif t in WORT:
            # Sondertasten wie heute üblich: Wort unten links, Zeichen oben links
            d.text((l + 22, u - 20), WORT[t], font=klein, fill=farbe, anchor='ls')
            if len(t) == 1:
                d.text((l + 22, o + 22), t, font=sym, fill=farbe, anchor='lt')
        elif t in ('▲', '▼', '◀', '▶'):
            d.text(((l + r) / 2, (o + u) / 2), t, font=ImageFont.truetype(ZEICHEN, 30), fill=farbe, anchor='mm')
        elif k['klein']:
            # fn, control, option, alt, esc, F-Tasten: klein, unten links bzw. mittig
            if t.startswith('F') or t == 'esc':
                d.text(((l + r) / 2, (o + u) / 2 + 6), t, font=klein, fill=farbe, anchor='mm')
            else:
                d.text((l + 22, u - 20), WORT.get(t, t), font=klein, fill=farbe, anchor='ls')
        else:
            d.text(((l + r) / 2, (o + u) / 2), t, font=gross, fill=farbe, anchor='mm')
    im.save(ziel, optimize=True)
    print(ziel, im.size)


if __name__ == '__main__':
    main(sys.argv[1])
