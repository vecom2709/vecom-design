#!/usr/bin/env python3
"""Fallstudien „Umgesetzte Arbeiten": Blender-Renders -> Web (24.09.2026).

Aufruf:
  python3 tools/arbeiten-web.py <render/arbeiten> <screenshots> [projekt ...]

Erwartet je Projekt in <render/arbeiten>/<p>/:
  an.png, aus.png (2400 x 1350), ecken.json, fahrt/bild-000.png ... (optional)
und in <screenshots>/: <p>-laptop-ganz.png (2880 breit), <p>-handy-ganz.png.

Schreibt nach assets/img/arbeiten/<p>/:
  an.webp      Standbild mit Bildschirm (LCP-Kandidat -> q 80)
  aus.webp     dieselbe Aufnahme mit dunklem Bildschirm, nur über den Seiten (Glanz, q 72)
  ecken.json   Bildschirmecken in Renderpixeln
  laptop-bahn.webp, telefon-bahn.webp   die echte Seite zum Scrollen
  fahrt.mp4    3-s-Kamerafahrt (H.264, 1280 x 720, ohne Ton)

WARUM DIE BAHN KLEINER ALS DER BILDSCHIRM
Der Laptop-Bildschirm belegt im Foto rund 700 von 2400 Pixeln Breite; auf
einer 1440er Seite also ~420 CSS-Pixel. Eine 1440 breite Bahn wäre dreifach
überabgetastet und kostete das Dreifache. 960 Pixel reichen bis 2x-Displays.
"""
import json, os, shutil, subprocess, sys
from PIL import Image, ImageDraw

Image.MAX_IMAGE_PIXELS = None
ZIEL = 'assets/img/arbeiten'
BAHN = {'laptop': (960, 3.0), 'telefon': (520, 3.0)}     # Breite, Bildschirmhöhen
SEITE_H = {'laptop': 824 / 1440, 'telefon': 727 / 390}   # sichtbare Höhe / Breite


def glanz_maskiert(png, ecken):
    """Glanz-Durchgang nur über den beiden Seitenflächen, außen schwarz.

    Warum im Bild und nicht per clip-path: Chromium zeichnet bei clip-path
    zusammen mit mix-blend-mode eine 1-px-Linie an der linken Kante des
    Begrenzungsrechtecks (gemessen 24.09.: +8 bzw. +14 Grauwerte in genau
    einer Spalte, verschwindet ohne Glanz). Schwarz ist unter „screen"
    neutral — so braucht es keine Maske im Browser."""
    im = Image.open(png).convert('RGB')
    f = 4
    maske = Image.new('L', (im.width * f, im.height * f), 0)
    zeichne = ImageDraw.Draw(maske)
    for k in ('laptop_seite', 'telefon_seite'):
        if ecken.get(k):
            zeichne.polygon([(x * f, y * f) for x, y in ecken[k]], fill=255)
    maske = maske.resize(im.size, Image.LANCZOS)
    return Image.composite(im, Image.new('RGB', im.size, 0), maske)


def main(render, shots, projekte):
    for p in projekte:
        q = os.path.join(render, p); z = os.path.join(ZIEL, p)
        os.makedirs(z, exist_ok=True)
        png = os.path.join(q, 'an.png')
        if os.path.exists(png):
            Image.open(png).convert('RGB').save(os.path.join(z, 'an.webp'), 'WEBP', quality=80, method=6)
        png = os.path.join(q, 'aus.png'); ej = os.path.join(q, 'ecken.json')
        if os.path.exists(png) and os.path.exists(ej):
            glanz_maskiert(png, json.load(open(ej))).save(os.path.join(z, 'aus.webp'), 'WEBP', quality=72, method=6)
        if os.path.exists(os.path.join(q, 'ecken.json')):
            shutil.copy(os.path.join(q, 'ecken.json'), os.path.join(z, 'ecken.json'))
        for art, datei in (('laptop', f'{p}-laptop-ganz.png'), ('telefon', f'{p}-handy-ganz.png')):
            pfad = os.path.join(shots, datei)
            if not os.path.exists(pfad):
                print('FEHLT', pfad); continue
            im = Image.open(pfad).convert('RGB')
            breite, schirme = BAHN[art]
            h_quelle = int(im.width * SEITE_H[art] * schirme)
            teil = im.crop((0, 0, im.width, min(im.height, h_quelle)))
            teil = teil.resize((breite, round(teil.height * breite / teil.width)), Image.LANCZOS)
            teil.save(os.path.join(z, f'{art}-bahn.webp'), 'WEBP', quality=70, method=6)
        fahrt = os.path.join(q, 'fahrt')
        if os.path.isdir(fahrt) and any(f.endswith('.png') for f in os.listdir(fahrt)):
            subprocess.run(['ffmpeg', '-v', 'error', '-y', '-framerate', '24', '-i', os.path.join(fahrt, 'bild-%03d.png'),
                            '-vf', 'scale=1280:720:flags=lanczos', '-c:v', 'libx264', '-preset', 'slow', '-crf', '25',
                            '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', os.path.join(z, 'fahrt.mp4')], check=True)
        groesse = {f: os.path.getsize(os.path.join(z, f)) // 1024 for f in sorted(os.listdir(z))}
        print(p, groesse)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3:] or ['cavaleri', 'jonika', 'mensaena', 'trendonix'])
