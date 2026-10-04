"""Branchen-Flyer (DE/IT/EN) ins Programm übernehmen (04.10.2026).

    python3 tools/werbemittel/branchen_uebernehmen.py <ausgabe von branchen_gen.py>

Kopiert slug.de|it|en.jpg nach app/flyer/pro-slug.*.jpg und schreibt deren Einträge
oben in app/flyer/liste.php (zwischen den Markierungen; der Rest bleibt, wie er ist).
Italienisch steht vorn: Ohne gewählte Sprache bekommt der Kunde in Italien seine.
"""
import json, os, re, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from branchen_texte import B

QUELLE = sys.argv[1]
ZIEL = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'app', 'flyer')
LISTE = os.path.join(ZIEL, 'liste.php')
AN, AUS = '    // --- Branchen-Flyer DE/IT/EN, erzeugt von tools/werbemittel/branchen_uebernehmen.py ---', '    // --- Ende Branchen-Flyer DE/IT/EN ---'
SP = ('it', 'de', 'en')

def name(s):
    # „BAU & HANDWERK“ → „Bau & Handwerk“; kleine Bindewörter bleiben klein.
    w = [x.capitalize() if x.lower() not in ('e', 'und', 'and', 'per', 'for') else x.lower() for x in s.split(' ')]
    return ' '.join(w)

def php(v):
    return "'" + v.replace('\\', '\\\\').replace("'", "\\'") + "'"

masse = json.load(open(os.path.join(QUELLE, 'liste.json')))
zeilen = [AN]
for slug, (gruppe, titel, *_rest) in B.items():
    if slug not in masse or not all(os.path.exists(f'{QUELLE}/{slug}.{l}.jpg') for l in SP):
        print('übersprungen', slug); continue
    for l in SP:
        shutil.copyfile(f'{QUELLE}/{slug}.{l}.jpg', os.path.join(ZIEL, f'pro-{slug}.{l}.jpg'))
    m = masse[slug]
    n = ', '.join(f"{php(l)} => {php(name(titel[l]))}" for l in ('de', 'it', 'en'))
    q = ', '.join(str(v) for v in m['q'])
    zeilen.append(f"    'pro-{slug}' => ['g' => {php(gruppe)}, 'n' => [{n}], 'b' => {m['b']}, 'h' => {m['h']}, 'q' => [{q}], "
                  f"'beschnitt' => {m['beschnitt']}, 'ag' => 34, 'sp' => ['it', 'de', 'en']],")
zeilen.append(AUS)
block = '\n'.join(zeilen)

s = open(LISTE, encoding='utf-8').read()
if AN in s:
    s = re.sub(re.escape(AN) + r'.*?' + re.escape(AUS), lambda _: block, s, flags=re.S)
else:
    s = s.replace('return [\n', 'return [\n' + block + '\n', 1)
open(LISTE, 'w', encoding='utf-8').write(s)
print(len(zeilen) - 2, 'Flyer in liste.php')
