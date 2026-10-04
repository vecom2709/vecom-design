"""PNG aus gen.py → JPEG (q88) nach app/druckvorlagen/<format>/ und layout.json → layout.php."""
import json, os, sys
from PIL import Image
quelle, ziel = sys.argv[1], sys.argv[2]
for fmt in sorted(os.listdir(quelle)):
    q = os.path.join(quelle, fmt)
    if not os.path.isfile(os.path.join(q, 'layout.json')): continue
    z = os.path.join(ziel, fmt); os.makedirs(z, exist_ok=True)
    lay = json.load(open(os.path.join(q, 'layout.json')))
    for f in sorted(os.listdir(q)):
        if f.endswith('.png'):
            im = Image.open(os.path.join(q, f)).convert('RGB')
            im.save(os.path.join(z, f[:-4] + '.jpg'), quality=88, optimize=True, progressive=False)
            if lay.get('gross') and not f.endswith('-titelgrund.png'):   # Großformat: kleine Fassung für die Vorschau (PHP lädt das große Bild nie)
                im.resize((round(im.width * 1100 / im.height), 1100), Image.LANCZOS).save(os.path.join(z, f[:-4] + '-klein.jpg'), quality=85)
    def php(v, ein=''):
        if isinstance(v, dict):
            return '[\n' + ''.join(f"{ein}    '{k}' => {php(x, ein + '    ')},\n" for k, x in v.items()) + ein + ']'
        if isinstance(v, list): return '[' + ', '.join(php(x) for x in v) + ']'
        if isinstance(v, bool): return 'true' if v else 'false'
        if isinstance(v, str): return "'" + v.replace("'", "\\'") + "'"
        return repr(round(v, 1)) if isinstance(v, float) else str(v)
    open(os.path.join(z, 'layout.php'), 'w').write("<?php\ndeclare(strict_types=1);\n/* Automatisch erzeugt (tools/werbemittel/gen.py). Einheit 1/10 mm auf der Leinwand mit Beschnitt; Felder wie bei den Visitenkarten. */\nreturn " + php(lay) + ";\n")
    print(fmt, len(os.listdir(z)))
