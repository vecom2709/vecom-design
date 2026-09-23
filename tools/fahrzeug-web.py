#!/usr/bin/env python3
"""Serienautos (Kleinwagen, Mittelklasse) fuer die Automotive-Demo ins Web bringen.

Aufruf:
  python3 tools/fahrzeug-web.py <was> <quelle>   # was = kleinwagen | mittelklasse
  <quelle> = 3d-produktion/branchen: quelle/<was>-web.glb und render/<was>/ aus branchen_studio.py
  (poster-*.png, boden.png, umgebung.exr, umgebung-boden.exr, kamera.json)

Kette: 3d-produktion/scripts/fahrzeug_bau.py baut in Blender (Standbild- und
Echtzeitfassung), branchen_studio.py rechnet Poster, Boden und Umgebung --
dasselbe Studio wie beim Konzeptauto. Hier entsteht daraus:

  assets/3d/branchen/<was>/<was>.glb      je Teil vereinfacht (fahrzeug-vereinfachen.mjs,
                                           Raender fest) + meshopt
  assets/3d/branchen/<was>/kamera.json     Kamera, Boden, Umgebung, Zerlegen
  assets/3d/branchen/<was>/boden-licht.webp  Bodenbeleuchtung (sRGB, normiert)
  assets/3d/branchen/<was>/umgebung*.hdr     Rundumbilder 1024 x 512, RGBE

WARUM JE TEIL VEREINFACHEN, MIT FESTEN RAENDERN
Die Echtzeitfassung aus Blender hat gut 620 000 Dreiecke. Einheitlich mit
0,025 % Abweichung vereinfacht, zeigte der vordere Kotfluegel im Browser helle
Dellen -- Blech verzeiht nichts, Sitze und Reifen sehr viel. Deshalb Blech mit
0,01 %, der Rest mit 0,04 %: rund 235 000 Dreiecke. Ohne feste Raender zog
die Vereinfachung die Fugenkanten auseinander.

BODEN: Blender backt 16 Bit, sRGB-kodiert (Ansicht "Standard"). Das Web
bekommt 8 Bit -- deshalb auf den hellsten Wert normiert (licht_skala), damit
der Kontaktschatten die volle Stufung behaelt; produkt-echtzeit.js rechnet
daraus die Staerke der lightMap (I = pi * Skala / Albedo).
"""
import json, os, shutil, subprocess, sys
import numpy as np
from PIL import Image

HIER = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HIER)
GT = os.environ.get('GLTF_TRANSFORM', '/home/claude/demos/npm/node_modules/.bin/gltf-transform')

# Web-Einstellungen wie beim Konzeptauto: gleiches Studio, gleicher Look.
WEB = ('look', 'web_belichtung', 'web_spiegel', 'web_boden', 'grund_srgb')

# Explosionsansicht: Achsen in Weltkoordinaten (three.js): seite = nach aussen,
# hoch = +Y, vor = Fahrtrichtung. start = Anteil der Gesamtzeit.
ZERLEGEN = {
    'dauer': 3.0, 'breite': 0.34,
    'regeln': [
        {'muster': '^tuer_[vh](_|$)', 'seite': 1.15, 'hoch': 0.1, 'start': 0.0, 'beschriftung': 'tueren'},
        {'muster': '^haube$', 'hoch': 0.8, 'vor': 0.6, 'start': 0.06, 'beschriftung': 'haube'},
        {'muster': '^(klappe|klappe_spoiler|rueckleuchte.*|stoss_h|kennzeichen_h)$', 'hoch': 0.55, 'vor': -0.9, 'start': 0.12, 'beschriftung': 'heck'},
        {'muster': '^(stoss_v|grill_.*|einlass.*|scheinwerfer.*|kennzeichen_v)$', 'vor': 0.75, 'start': 0.10},
        {'muster': '^(frontscheibe|wischer)$', 'hoch': 0.95, 'vor': 0.35, 'start': 0.28},
        {'muster': '^(reifen|felge|felgenbett|schrauben|nabendeckel)_[vh]_[lr]$', 'seite': 0.9, 'start': 0.42, 'beschriftung': 'raeder'},
        {'muster': '^(bremsscheibe|sattel)_[vh]_[lr]$', 'seite': 0.5, 'start': 0.5, 'beschriftung': 'bremse'},
        {'muster': '^antrieb_', 'hoch': 0.55, 'start': 0.58, 'beschriftung': 'antrieb'},
        {'muster': '^fahrwerk_', 'hoch': 0.30, 'start': 0.62, 'beschriftung': 'fahrwerk'},
        {'muster': '^innen_sitze$', 'hoch': 0.45, 'start': 0.66, 'beschriftung': 'sitze'},
        {'muster': '^innen_(lenkrad|armatur)$', 'hoch': 0.28, 'vor': 0.1, 'start': 0.70},
    ],
}


def glb_lesen(p):
    import struct
    b = open(p, 'rb').read()
    n = struct.unpack('<I', b[12:16])[0]
    return json.loads(b[20:20 + n])


def huelle(glb):
    g = glb_lesen(glb)
    lo = np.full(3, 1e9); hi = np.full(3, -1e9)
    for m in g['meshes']:
        for p in m['primitives']:
            a = g['accessors'][p['attributes']['POSITION']]
            lo = np.minimum(lo, a['min']); hi = np.maximum(hi, a['max'])
    return lo, hi


def dreiecke(glb):
    g = glb_lesen(glb)
    return sum(g['accessors'][p['indices']]['count'] // 3 for m in g['meshes'] for p in m['primitives'])


def exr_lesen(p):
    import OpenEXR, Imath
    f = OpenEXR.InputFile(p)
    dw = f.header()['dataWindow']
    w, h = dw.max.x - dw.min.x + 1, dw.max.y - dw.min.y + 1
    kan = [np.frombuffer(f.channel(c, Imath.PixelType(Imath.PixelType.FLOAT)), np.float32).reshape(h, w) for c in 'RGB']
    return np.stack(kan, -1)


def hdr_schreiben(p, rgb):
    import cv2
    # 2048 x 1024 -> 1024 x 512 als Flaechenmittel (keine Aliasing-Funken)
    h, w = rgb.shape[:2]
    klein = rgb.reshape(h // 2, 2, w // 2, 2, 3).mean((1, 3)).astype(np.float32)
    cv2.imwrite(p, klein[..., ::-1])


def srgb_zu_linear(v):
    return np.where(v <= 0.04045, v / 12.92, ((v + 0.055) / 1.055) ** 2.4)


def linear_zu_srgb(l):
    return np.where(l <= 0.0031308, l * 12.92, 1.055 * np.power(np.maximum(l, 0), 1 / 2.4) - 0.055)


def main(was, quelle):
    ziel = os.path.join(REPO, 'assets', '3d', 'branchen', was)
    os.makedirs(ziel, exist_ok=True)
    render = os.path.join(quelle, 'render', was)
    # 1) Modell
    roh = os.path.join(quelle, 'quelle', f'{was}-web.glb')
    if not os.path.exists(roh):
        roh = os.path.join(quelle, f'{was}-web.glb')
    tmp = f'/tmp/{was}-einfach.glb'
    subprocess.run(['node', os.path.join(HIER, 'fahrzeug-vereinfachen.mjs'), roh, tmp], check=True)
    glb = os.path.join(ziel, f'{was}.glb')
    subprocess.run([GT, 'meshopt', tmp, glb, '--level', 'high'], check=True)
    n = dreiecke(tmp)
    print('Modell', glb, os.path.getsize(glb), 'Bytes', n, 'Dreiecke (roh', dreiecke(roh), ')')
    # 2) Umgebungen
    for name in ('umgebung', 'umgebung-boden'):
        hdr_schreiben(os.path.join(ziel, f'{name}.hdr'), exr_lesen(os.path.join(render, f'{name}.exr')))
    # 3) Boden
    b = np.asarray(Image.open(os.path.join(render, 'boden.png')), np.float64)
    tiefe = 65535.0 if b.max() > 255 else 255.0
    lin = srgb_zu_linear(b[..., :3] / tiefe)
    skala = float(lin.max())
    web = linear_zu_srgb(lin / skala)
    Image.fromarray(np.clip(web * 255 + 0.5, 0, 255).astype(np.uint8)).resize((512, 512), Image.LANCZOS).save(
        os.path.join(ziel, 'boden-licht.webp'), quality=90, method=6)
    # 4) Kamera
    k = json.load(open(os.path.join(render, 'kamera.json'), encoding='utf-8'))
    auto = json.load(open(os.path.join(REPO, 'assets', '3d', 'branchen', 'auto', 'kamera.json'), encoding='utf-8'))
    lo, hi = huelle(roh)
    mitte = [(lo[0] + hi[0]) / 2, k.get('boden_hoehe', 0.0), (lo[2] + hi[2]) / 2]
    k['boden'] = dict(auto['boden'], mitte=mitte, licht_skala=skala, datei='boden-licht.webp')
    k['umgebung'] = {'datei': 'umgebung.hdr', 'ort': k['umgebung']['ort']}
    k['umgebung_boden'] = {'datei': 'umgebung-boden.hdr'}
    for s in WEB:
        k[s] = auto[s]
    k['zerlegen'] = ZERLEGEN
    k['dreiecke'] = n
    with open(os.path.join(ziel, 'kamera.json'), 'w', encoding='utf-8') as f:
        json.dump(k, f, ensure_ascii=False, indent=1)
    print('fertig', ziel, 'licht_skala', round(skala, 4))


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
