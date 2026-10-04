"""PNG aus gen.py → JPEG (q88) nach app/druckvorlagen/<format>/ und layout.json → layout.php."""
import json, os, sys
from PIL import Image
quelle, ziel = sys.argv[1], sys.argv[2]
for fmt in sorted(os.listdir(quelle)):
    q = os.path.join(quelle, fmt)
    if not os.path.isfile(os.path.join(q, 'layout.json')): continue
    z = os.path.join(ziel, fmt); os.makedirs(z, exist_ok=True)
    for f in sorted(os.listdir(q)):
        if f.endswith('.png'):
            Image.open(os.path.join(q, f)).convert('RGB').save(os.path.join(z, f[:-4] + '.jpg'), quality=88, optimize=True, progressive=False)
    lay = json.load(open(os.path.join(q, 'layout.json')))
    def php(v, ein=''):
        if isinstance(v, dict):
            return '[\n' + ''.join(f"{ein}    '{k}' => {php(x, ein + '    ')},\n" for k, x in v.items()) + ein + ']'
        if isinstance(v, list): return '[' + ', '.join(php(x) for x in v) + ']'
        if isinstance(v, bool): return 'true' if v else 'false'
        if isinstance(v, str): return "'" + v.replace("'", "\\'") + "'"
        return repr(round(v, 1)) if isinstance(v, float) else str(v)
    open(os.path.join(z, 'layout.php'), 'w').write("<?php\ndeclare(strict_types=1);\n/* Automatisch erzeugt (tools/werbemittel/gen.py). Einheit 1/10 mm auf der Leinwand mit Beschnitt; Felder wie bei den Visitenkarten. */\nreturn " + php(lay) + ";\n")
    print(fmt, len(os.listdir(z)))
