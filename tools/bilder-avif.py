#!/usr/bin/env python3
"""Gerechnete Bilder fuer die Erlebnis-Seite: PNG -> WebP und AVIF, je 1600 und 800 px.

Aufruf:
  python3 tools/bilder-avif.py branchen <ordner-mit-poster-pngs>
  python3 tools/bilder-avif.py villa <ordner-mit-ruhe-pngs> [--webp]

WARUM AVIF ZUSAETZLICH (23.09.2026)
Gemessen am Karminrot-Poster gegen das PNG aus Cycles: WebP 45 KB bei einer
mittleren Abweichung von 1,02 Stufen; AVIF q60 24 KB bei 0,94 -- also
kleiner UND naeher am Original. WebP bleibt als Rueckfall fuer Browser ohne
AVIF (Safari vor 16, alte Android-WebViews).

IMMER AUS DEM PNG
Nie WebP in AVIF umrechnen: zwei verlustbehaftete Schritte hintereinander
addieren ihre Fehler, und das AVIF waere schlechter als das WebP, das es
ersetzen soll.
"""
import os
import sys

from PIL import Image

AVIF_Q = 60         # gemessen, siehe oben
# Bei 800 px lag q60 knapp UEBER dem Fehler des WebP (1,38 zu 1,31). q66 lag
# darunter (1,18) -- und besser als q68/q70, gemessen, nicht geschaetzt.
AVIF_Q_KLEIN = 66
WEBP_Q = 82
ZIEL_BR = 'assets/img/erlebnis/branchen'
ZIEL_VI = 'assets/img/erlebnis/villa'
BRANCHEN = {
    'auto/poster-carmine-candy.png': 'auto-karmin',
    'auto/poster-pearly-swirly.png': 'auto-perl',
    'auto/poster-torched-graphite.png': 'auto-graphit',
    'schuh/poster-midnight.png': 'schuh-blau',
    'schuh/poster-beach.png': 'schuh-rose',
    'schuh/poster-street.png': 'schuh-anthrazit',
    # Serienautos (fahrzeug_bau.py, 23.09.2026)
    'kleinwagen/poster-azzurro.png': 'kleinwagen-azzurro',
    'kleinwagen/poster-bianco.png': 'kleinwagen-bianco',
    'kleinwagen/poster-salvia.png': 'kleinwagen-salvia',
    'mittelklasse/poster-blu-notte.png': 'mittelklasse-blunotte',
    'mittelklasse/poster-argento.png': 'mittelklasse-argento',
    'mittelklasse/poster-rosso.png': 'mittelklasse-rosso',
    # Produktdemos der Galerie (24.09.2026)
    'wein/poster-rosso.png': 'wein-rosso', 'wein/poster-bianco.png': 'wein-bianco', 'wein/poster-olio.png': 'wein-olio',
    'schmuck/poster-stahl.png': 'schmuck-stahl', 'schmuck/poster-gelbgold.png': 'schmuck-gelbgold', 'schmuck/poster-rosegold.png': 'schmuck-rosegold',
    'kueche/poster-salbei.png': 'kueche-salbei', 'kueche/poster-weiss.png': 'kueche-weiss', 'kueche/poster-nussbaum.png': 'kueche-nussbaum',
    'gastro/poster-weiss.png': 'gastro-weiss', 'gastro/poster-terrakotta.png': 'gastro-terrakotta', 'gastro/poster-anthrazit.png': 'gastro-anthrazit',
    # Fahrerplatz je Ausstattung (branchen_studio.py, Modus innen) -- Standbild
    # am Ende der Kamerafahrt und Rueckfall ohne Echtzeit
    **{f'{m}/innen-{k}.png': f'{m}-innen-{k}' for m, ks in (
        ('kleinwagen', ('stoff-anthrazit', 'stoff-grau-blau', 'kunstleder-hell')),
        ('mittelklasse', ('stoff-anthrazit', 'leder-cognac', 'leder-elfenbein'))) for k in ks},
}


# Galeriekacheln mit eigenem Ausschnitt (links, oben, rechts, unten im
# 1600er-Poster): Die Uhr liegt im Poster unten rechts -- in der Kachel lag
# sie unter der Beschriftung, zu sehen war nur das Band (23.09.2026).
KACHEL = {
    'schmuck/poster-stahl.png': ('schmuck-kachel', (470, 348, 1450, 899)),
}


def kachel_schreiben(quelle, ziel_stamm, box, webp=False):
    bild = Image.open(quelle).convert('RGB').crop(box).resize((800, 450), Image.LANCZOS)
    bild.save(f'{ziel_stamm}-800.avif', 'AVIF', quality=AVIF_Q_KLEIN, speed=3)
    if webp:
        bild.save(f'{ziel_stamm}-800.webp', 'WEBP', quality=WEBP_Q, method=6)
    return ziel_stamm


def schreiben(quelle, ziel_stamm, webp=False):
    bild = Image.open(quelle).convert('RGB')
    if bild.size != (1600, 900):
        bild = bild.resize((1600, 900), Image.LANCZOS)
    klein = bild.resize((800, 450), Image.LANCZOS)
    for b, anhang, q in ((bild, '', AVIF_Q), (klein, '-800', AVIF_Q_KLEIN)):
        b.save(f'{ziel_stamm}{anhang}.avif', 'AVIF', quality=q, speed=3)
        if webp:
            b.save(f'{ziel_stamm}{anhang}.webp', 'WEBP', quality=WEBP_Q, method=6)
    return ziel_stamm


def main():
    art, ordner = sys.argv[1], sys.argv[2]
    webp = '--webp' in sys.argv
    if art == 'branchen':
        for q, z in BRANCHEN.items():
            # Nur was im Ordner liegt -- so laesst sich ein einzelnes Modell
            # nachrechnen, ohne die anderen Poster erneut zu kodieren.
            if os.path.exists(os.path.join(ordner, q)):
                print(schreiben(os.path.join(ordner, q), os.path.join(ZIEL_BR, z), webp))
        for q, (z, box) in KACHEL.items():
            if os.path.exists(os.path.join(ordner, q)):
                print(kachel_schreiben(os.path.join(ordner, q), os.path.join(ZIEL_BR, z), box, webp))
    elif art == 'villa':
        for f in sorted(os.listdir(ordner)):
            if f.startswith('ruhe-') and f.endswith('.png') and '-kueche-' not in f:
                print(schreiben(os.path.join(ordner, f), os.path.join(ZIEL_VI, f[:-4]), webp))


if __name__ == '__main__':
    main()
