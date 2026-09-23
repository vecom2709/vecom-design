#!/usr/bin/env python3
"""Produktdemos (Wein, ...) fuer die Erlebnis-Seite ins Web bringen.

Aufruf:  python3 tools/produkt-web.py <was> <quelle>
  <quelle> = 3d-produktion/branchen (quelle/<was>-web.glb, render/<was>/)

Dieselbe Kette wie bei den Serienautos (tools/fahrzeug-web.py, von dort
eingebunden): je Teil vereinfachen, WebP-Texturen, meshopt; Rundumbilder,
Bodenlicht, Kamera. Web-Einstellungen (Look, Belichtung, Spiegel, Boden)
kommen vom Schuh -- dasselbe kleine Studio, auf die Produktgroesse skaliert.
Das Zerlegen/Oeffnen je Produkt steht unten in ZERLEGEN.
"""
import importlib.util, json, os, subprocess, sys
import numpy as np
from PIL import Image

HIER = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HIER)
_s = importlib.util.spec_from_file_location('fw', os.path.join(HIER, 'fahrzeug-web.py'))
FW = importlib.util.module_from_spec(_s); _s.loader.exec_module(FW)

# Texturen je Produkt: Muster -> Kantenlaenge (fahrzeug-vereinfachen.mjs liest TEXTUR_REGELN)
TEXTUREN = {
    'wein': [['^etikett', 1024], ['^(holz|innen|kork)', 512]],
}
ZERLEGEN = {
    'wein': {
        'dauer': 3.2, 'breite': 0.30, 'stufen': ['kiste', 'flasche'],
        'regeln': [
            {'muster': '^kiste_tuer_angel$', 'dreh': True, 'start': 0.00, 'stufe': 1, 'beschriftung': 'kiste'},
            {'muster': '^flasche_kapsel$', 'hoch': 0.075, 'start': 0.55, 'stufe': 2, 'beschriftung': 'kapsel'},
            {'muster': '^flasche_kork$', 'hoch': 0.045, 'start': 0.62, 'stufe': 2, 'beschriftung': 'kork'},
        ],
    },
}


def main(was, quelle):
    ziel = os.path.join(REPO, 'assets', '3d', 'branchen', was)
    os.makedirs(ziel, exist_ok=True)
    render = os.path.join(quelle, 'render', was)
    roh = os.path.join(quelle, 'quelle', f'{was}-web.glb')
    tmp = f'/tmp/{was}-einfach.glb'
    env = dict(os.environ, TEXTUR_REGELN=json.dumps(TEXTUREN.get(was, [])))
    subprocess.run(['node', os.path.join(HIER, 'fahrzeug-vereinfachen.mjs'), roh, tmp], check=True, env=env)
    glb = os.path.join(ziel, f'{was}.glb')
    subprocess.run([FW.GT, 'meshopt', tmp, glb, '--level', 'high'], check=True)
    n = FW.dreiecke(tmp)
    print('Modell', glb, os.path.getsize(glb), 'Bytes', n, 'Dreiecke')
    for name in ('umgebung', 'umgebung-boden'):
        FW.hdr_schreiben(os.path.join(ziel, f'{name}.hdr'), FW.exr_lesen(os.path.join(render, f'{name}.exr')), name != 'umgebung')
    b = np.asarray(Image.open(os.path.join(render, 'boden.png')), np.float64)
    tiefe = 65535.0 if b.max() > 255 else 255.0
    lin = FW.srgb_zu_linear(b[..., :3] / tiefe)
    skala = float(lin.max())
    web = FW.linear_zu_srgb(lin / skala)
    Image.fromarray(np.clip(web * 255 + 0.5, 0, 255).astype(np.uint8)).resize((512, 512), Image.LANCZOS).save(
        os.path.join(ziel, 'boden-licht.webp'), quality=90, method=6)
    k = json.load(open(os.path.join(render, 'kamera.json'), encoding='utf-8'))
    schuh = json.load(open(os.path.join(REPO, 'assets', '3d', 'branchen', 'schuh', 'kamera.json'), encoding='utf-8'))
    lo, hi = FW.huelle(tmp)
    mitte = [(lo[0] + hi[0]) / 2, k.get('boden_hoehe', 0.0), (lo[2] + hi[2]) / 2]
    # Groesse, Insel und Auslauf aus dem eigenen Studio (skaliert), der Rest wie beim Schuh
    k['boden'] = dict(schuh['boden'], **k.get('boden', {}))
    k['boden'].update(mitte=mitte, licht_skala=skala, datei='boden-licht.webp')
    k['umgebung'] = {'datei': 'umgebung.hdr', 'ort': k['umgebung']['ort']}
    k['umgebung_boden'] = {'datei': 'umgebung-boden.hdr'}
    for s in FW.WEB:
        if s in schuh:
            k[s] = schuh[s]
    if was in ZERLEGEN:
        k['zerlegen'] = ZERLEGEN[was]
    k['dreiecke'] = n
    with open(os.path.join(ziel, 'kamera.json'), 'w', encoding='utf-8') as f:
        json.dump(k, f, ensure_ascii=False, indent=1)
    print('fertig', ziel, 'licht_skala', round(skala, 4))


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
