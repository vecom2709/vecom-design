"""Die Flyer im Originalstil ins Programm übernehmen (04.10.2026).

    python3 tools/werbemittel/original_uebernehmen.py <ausgabe von original_gen.py>

Kopiert <name>.it|de|en.jpg nach app/flyer/ und schreibt deren Einträge oben in
app/flyer/liste.php (zwischen den Markierungen). Ersetzt den Block der ersten
DE/IT/EN-Flyer (pro-*, Uwe: „im Originalstil ersetzen“) samt ihren Dateien.
Italienisch steht vorn: Ohne gewählte Sprache bekommt der Kunde in Italien seine.
"""
import glob, json, os, re, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from original_texte import F

QUELLE = sys.argv[1]
ZIEL = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'app', 'flyer')
LISTE = os.path.join(ZIEL, 'liste.php')
AN, AUS = '    // --- Branchen-Flyer DE/IT/EN, erzeugt von tools/werbemittel/original_uebernehmen.py ---', '    // --- Ende Branchen-Flyer DE/IT/EN ---'
ALT_AN = '    // --- Branchen-Flyer DE/IT/EN, erzeugt von tools/werbemittel/branchen_uebernehmen.py ---'
SP = ('it', 'de', 'en')
STIL = {'de': 'Stil', 'it': 'Stile', 'en': 'Style'}

def name(s):
    w = [x.capitalize() if x.lower() not in ('e', 'und', 'and', 'per', 'for', 'di') else x.lower() for x in s.split(' ')]
    return ' '.join(w)

def php(v):
    return "'" + v.replace('\\', '\\\\').replace("'", "\\'") + "'"

masse = json.load(open(os.path.join(QUELLE, 'liste.json')))
zeilen = [AN]
for slug, fl in F.items():
    if slug not in masse or not all(os.path.exists(f'{QUELLE}/{slug}.{l}.jpg') for l in SP):
        print('übersprungen', slug); continue
    for l in SP:
        shutil.copyfile(f'{QUELLE}/{slug}.{l}.jpg', os.path.join(ZIEL, f'{slug}.{l}.jpg'))
    m = masse[slug]
    n = ', '.join(f"{php(l)} => {php(name(fl['titel'][l]) + ' · ' + STIL[l] + ' ' + fl['f'].upper())}" for l in ('de', 'it', 'en'))
    q = ', '.join(str(v) for v in m['q'])
    u = m['u']
    zeilen.append(f"    {php(slug)} => ['g' => {php(fl['g'])}, 'n' => [{n}], 'b' => {m['b']}, 'h' => {m['h']}, 'q' => [{q}], "
                  f"'beschnitt' => {m['beschnitt']}, 'u' => ['x' => {u['x']}, 'y' => {u['y']}, 'gr' => {u['gr']}, 'anker' => {php(u['anker'])}, 'farbe' => {php(u['farbe'])}], "
                  f"'sp' => ['it', 'de', 'en']],")
zeilen.append(AUS)
block = '\n'.join(zeilen)

s = open(LISTE, encoding='utf-8').read()
for an in (AN, ALT_AN):
    if an in s:
        s = re.sub(re.escape(an) + r'.*?' + re.escape(AUS), lambda _: block, s, count=1, flags=re.S)
        break
else:
    s = s.replace('return [\n', 'return [\n' + block + '\n', 1)
open(LISTE, 'w', encoding='utf-8').write(s)
for alt in glob.glob(os.path.join(ZIEL, 'pro-*.jpg')):
    os.remove(alt)
print(len(zeilen) - 2, 'Flyer in liste.php')
