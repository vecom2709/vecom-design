"""Bildaufträge für die 51 Branchen-Flyer im Originalstil (04.10.2026).

Uwe: „im selben Stil wie die Original-Flyer, alle 51 einzeln, in DE/IT/EN, mit dem echten V,
Code und Link vom Partner“. Die Originale (4 Collagen, Downloads\\Flyer) haben eingebrannten
deutschen Text mit Fehlern und ein erfundenes Schnörkel-V. Darum: jedes Original geht als
Bild-Referenz an kie.ai (nano-banana-pro, image_input) mit dem Auftrag, Foto, Licht und Geräte
zu behalten und ALLES Grafische zu entfernen. Text, echtes Logo, Symbole, Knopf, QR und Link
setzt danach original_gen.py — exakt und in drei Sprachen.

Ausgabe: JSON-Liste [{name, bild, prompt}] für kie-ref.ps1.
    python3 tools/werbemittel/original_bilder.py [name …] > auftraege.json
"""
import json, os, sys

GRUND = (
    "Use the attached flyer only as a visual reference. Recreate it as a clean, high-resolution vertical background "
    "image for a premium print flyer: keep the same photographic scene, subject, lighting, colour mood and overall "
    "composition{geraete}. Remove EVERYTHING that is graphic design: all text and letters, the golden V emblem and the "
    "'VECOM DESIGN' wordmark, headings, bullet lists and their round icons, buttons, the QR code, the web address, "
    "handwritten script slogans, separator lines{leiste}. Where these elements were, continue the photograph and its "
    "dark shadows naturally, so those areas become calm, dark and empty, ready for text to be placed later. "
    "The top 18 percent of the image stays calm and fairly dark for a logo. "
    "No text, no letters, no numbers, no logos, no brand emblems, no watermarks anywhere in the image. "
    "Photorealistic, luxurious, sharp, high detail.")
GERAETE = (", including the laptop and smartphone mockups in the same place; their screens show a generic elegant "
           "website with photos and soft blurred lines instead of any readable text")
LEISTE = " and the dark icon bar at the bottom (keep a plain dark band there instead)"

def auftrag(name):
    fam = name[0]
    return GRUND.format(geraete=GERAETE if fam in 'bd' else '', leiste=LEISTE if fam == 'c' else '')

if __name__ == '__main__':
    ref = os.environ.get('REF', r'C:\Users\manue\Desktop\Vecom Design\_flyer_bilder\ref')
    alle = sorted(f[:-4] for f in os.listdir(os.path.join(os.path.dirname(__file__), 'original_ref')) if f.endswith('.jpg')) \
        if os.path.isdir(os.path.join(os.path.dirname(__file__), 'original_ref')) else []
    nur = sys.argv[1:] or alle
    print(json.dumps([{'name': 'o-' + n, 'bild': ref + '\\' + n + '.jpg', 'prompt': auftrag(n)} for n in nur],
                     ensure_ascii=False, indent=1))
