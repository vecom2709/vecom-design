#!/usr/bin/env python3
"""Haarfarben-Demo: gerechnete Drehbilder -> WebP fuer die Seite.

SEIT 04.10.2026 kommen die Bilder aus Unreal (MetaHuman, Path Tracer,
3d-produktion/metahuman/mh-produktion-a.ps1) als <schnitt>/<farbe>/kopf.NNNN.png;
die alten Blender-Namen dreh-NN.png werden weiter erkannt.

Aufruf:
  python3 tools/haar-web.py <ordner render/haar_final>

Erwartet <ordner>/<schnitt>/<farbe>/dreh-00.png .. dreh-24.png (1280 x 720)
und schreibt
  assets/img/erlebnis/haar/<schnitt>/<farbe>/gross/dreh-XX.webp   1280 x 720
  assets/img/erlebnis/haar/<schnitt>/<farbe>/klein/dreh-XX.webp    640 x 360
  assets/img/erlebnis/haar/poster.{webp,avif}      (Stufenschnitt Kastanie, Gesicht frontal)
  assets/img/erlebnis/haar/kachel-800.{webp,avif}  800 x 450 (Ausschnitt Kopf)
  assets/img/erlebnis/haar/farben.json             {"stufen": [..], "bob": [..]}

SEIT 03.10.2026
Neuer Uebungskopf (Bueste mit Brauen und Wimpern), zwei Schnitte, 25 Ansichten
von -150 bis +150 Grad -- Bild 12 ist das Gesicht von vorn, dort beginnt die Demo.
Die alten Saetze (<farbe>/gross, 16 Bilder, Ansicht von hinten) sind entfernt.

WARUM 1280 UND NICHT 1600
Haar ist hochfrequent: Jede Straehne kostet Bytes. Die Buehne ist auf dem
Rechner selten breiter als 1000 CSS-Pixel. Klein laedt zuerst (schnelles erstes
Bild), gross wird erst uebernommen, wenn der ganze Satz da ist.
"""
import json
import os
import sys

from PIL import Image

SCHNITTE = ['stufen', 'bob']
FARBEN = ['kastanie', 'schwarz', 'kupfer', 'balayage', 'aschblond', 'rosegold']
N = 25
MITTE = N // 2
ZIEL = 'assets/img/erlebnis/haar'
Q_GROSS, Q_KLEIN = 76, 70
AVIF_Q = 60


def speichern(bild, pfad, q, avif=False):
    os.makedirs(os.path.dirname(pfad), exist_ok=True)
    bild.save(pfad + '.webp', 'WEBP', quality=q, method=6)
    if avif:
        bild.save(pfad + '.avif', 'AVIF', quality=AVIF_Q)


def bild(quelle, sn, f, i):
    for name in (f'kopf.{i:04d}.png', f'dreh-{i:02d}.png'):
        p = os.path.join(quelle, sn, f, name)
        if os.path.exists(p):
            return p
    return os.path.join(quelle, sn, f, f'kopf.{i:04d}.png')


def main(quelle):
    summe = {}
    for sn in SCHNITTE:
        for f in FARBEN:
            for i in range(N):
                png = bild(quelle, sn, f, i)
                if not os.path.exists(png):
                    print('FEHLT', png); continue
                b = Image.open(png).convert('RGB')
                g = b.resize((1280, 720), Image.LANCZOS) if b.size != (1280, 720) else b
                k = b.resize((640, 360), Image.LANCZOS)
                aus = os.path.join(ZIEL, sn, f)
                speichern(g, os.path.join(aus, 'gross', f'dreh-{i:02d}'), Q_GROSS)
                speichern(k, os.path.join(aus, 'klein', f'dreh-{i:02d}'), Q_KLEIN)
                summe[(sn, f)] = summe.get((sn, f), 0) + os.path.getsize(os.path.join(aus, 'gross', f'dreh-{i:02d}.webp'))
    mitte = bild(quelle, 'stufen', 'kastanie', MITTE)
    if os.path.exists(mitte):
        b = Image.open(mitte).convert('RGB')
        speichern(b, os.path.join(ZIEL, 'poster'), 80, avif=True)
    # Kachel: Dreiviertelansicht (Bild 14, +25 Grad) -- von vorn sieht man die Laenge nicht
    dv = bild(quelle, 'stufen', 'kastanie', MITTE + 2)
    if os.path.exists(dv):
        b = Image.open(dv).convert('RGB')
        w, h = b.size   # Kopf und Schultern, 16:9
        kw = int(w * 0.66); kh = int(kw * 9 / 16); x0 = (w - kw) // 2; y0 = int(h * 0.02)
        speichern(b.crop((x0, y0, x0 + kw, y0 + kh)).resize((800, 450), Image.LANCZOS), os.path.join(ZIEL, 'kachel-800'), 80, avif=True)
    # Welche Saetze vollstaendig da sind -- die Seite zeigt nur diese
    fertig = {sn: [f for f in FARBEN if all(os.path.exists(os.path.join(ZIEL, sn, f, g, f'dreh-{i:02d}.webp'))
                                            for g in ('gross', 'klein') for i in range(N))] for sn in SCHNITTE}
    with open(os.path.join(ZIEL, 'farben.json'), 'w', encoding='utf-8') as fh:
        json.dump(fertig, fh)
    print('fertig:', fertig)
    for (sn, f), s in summe.items():
        print(f'{sn}/{f}: {s / 1024:.0f} KB gross ({N} Bilder)')


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'render/haar_final')
