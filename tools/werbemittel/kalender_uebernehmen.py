"""PNG aus kalender.py → JPEG nach app/druckvorlagen/kalender_a3/ (+ kleine Fassung für die Vorschau) und layout.json → layout.php.
Aufruf: python3 tools/werbemittel/kalender_uebernehmen.py <ausgabe von kalender.py> app/druckvorlagen/kalender_a3"""
import json, os, sys
from PIL import Image
Image.MAX_IMAGE_PIXELS = None
quelle, ziel = sys.argv[1], sys.argv[2]
os.makedirs(ziel, exist_ok=True)
for f in sorted(os.listdir(quelle)):
    if not f.endswith('.png'): continue
    im = Image.open(os.path.join(quelle, f)).convert('RGB')
    # q80 (PSNR 40 dB, bei 100 % nicht von q85 zu unterscheiden): 13 Seiten je Kalender müssen in eine Datenbankzeile passen (MEDIUMBLOB 16 MB) — gemessen, siehe PROJEKT.md.
    im.save(os.path.join(ziel, f[:-4] + '.jpg'), quality=int(os.environ.get('KAL_Q', '80')), optimize=True)
    im.resize((round(im.width * 1100 / im.height), 1100), Image.LANCZOS).save(os.path.join(ziel, f[:-4] + '-klein.jpg'), quality=85)
lay = json.load(open(os.path.join(quelle, 'layout.json')))
def php(v, ein=''):
    if isinstance(v, dict):
        return '[\n' + ''.join(f"{ein}    '{k}' => {php(x, ein + '    ')},\n" for k, x in v.items()) + ein + ']'
    if isinstance(v, list): return '[' + ', '.join(php(x) for x in v) + ']'
    if isinstance(v, str): return "'" + v.replace("'", "\\'") + "'"
    return repr(round(v, 1)) if isinstance(v, float) else str(v)
open(os.path.join(ziel, 'layout.php'), 'w').write("<?php\ndeclare(strict_types=1);\n/* Automatisch erzeugt (tools/werbemittel/kalender.py). Einheit 1/10 mm auf der Seite mit Beschnitt. */\nreturn " + php(lay) + ";\n")
print('ok', len(os.listdir(ziel)))
