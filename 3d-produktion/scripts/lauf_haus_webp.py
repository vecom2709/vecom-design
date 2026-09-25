# -*- coding: utf-8 -*-
"""Headless: die Cycles-Finals der Villa nach WebP fuer die Website.

Aufruf:  blender.exe -b -P lauf_haus_webp.py -- final

WARUM ZWEI GROESSEN
Die Bildfassung des Labors zeigt eine Ansicht formatfuellend, auf einem
27-Zoll-Schirm also rund 1400 px breit. 1920 px waeren dort Ballast,
960 px auf einem Netzhautschirm zu weich. Also 1600 px fuer die Anzeige
und 800 px als erste Stufe fuer schmale Geraete -- beide aus demselben
gerechneten Bild, damit keine zweite Wahrheit entsteht.

WARUM GUETE 82
Bei diesen Motiven (weicher Beton, Glas, Himmelsverlauf) setzt unterhalb
von 80 die Bandenbildung im Himmel ueber dem Dach ein. 82 ist der
gemessene Punkt kurz darueber, nicht der uebliche Daumenwert.
"""
import bpy
import os
import sys

P = os.path.dirname(os.path.abspath(__file__))
WURZEL = os.path.dirname(os.path.dirname(P))
RENDER = os.path.join(os.path.dirname(P), 'render')
ZIEL = os.path.join(WURZEL, 'website', 'assets', 'img', '3d', 'haus')

BILDER = [
    ('garten', 'haus-garten'),
    ('ankunft', 'haus-ankunft'),
    ('terrasse', 'haus-terrasse'),
    ('wohnen', 'haus-wohnen'),
    ('essen', 'haus-essen'),
    ('kueche', 'haus-kueche'),
    ('master', 'haus-master'),
]

BREIT = 1600
SCHMAL = 800
GUETE = 82


def _speichern(bild, pfad, breite, guete):
    hoehe = int(round(breite * bild.size[1] / float(bild.size[0])))
    kopie = bild.copy()
    kopie.scale(breite, hoehe)
    ein = bpy.context.scene.render.image_settings
    ein.file_format = 'WEBP'
    ein.color_mode = 'RGB'
    ein.quality = guete
    kopie.save_render(filepath=pfad)
    bpy.data.images.remove(kopie)
    return os.path.getsize(pfad)


def main():
    argv = sys.argv
    vorsatz = argv[argv.index('--') + 1] if '--' in argv else 'final'
    if not os.path.isdir(ZIEL):
        os.makedirs(ZIEL)

    # Farbverwaltung aus: die PNGs sind bereits fertig entwickelt.
    # Ohne das legt Blender ein zweites Mal Filmic darueber.
    bpy.context.scene.view_settings.view_transform = 'Standard'
    bpy.context.scene.view_settings.look = 'None'
    bpy.context.scene.view_settings.exposure = 0.0
    bpy.context.scene.view_settings.gamma = 1.0

    summe = 0
    behalten = set()
    for kurz, name in BILDER:
        quelle = os.path.join(RENDER, vorsatz + '-' + kurz + '.png')
        if not os.path.isfile(quelle):
            print('[WEBP] FEHLT ' + quelle)
            continue
        bild = bpy.data.images.load(quelle)
        bild.colorspace_settings.name = 'sRGB'
        g = _speichern(bild, os.path.join(ZIEL, name + '.webp'), BREIT, GUETE)
        k = _speichern(bild, os.path.join(ZIEL, name + '-800.webp'), SCHMAL, GUETE)
        bpy.data.images.remove(bild)
        summe += g
        behalten.add(name + '.webp')
        behalten.add(name + '-800.webp')
        print('[WEBP] %-14s %6d KB  (schmal %4d KB)' % (name, g // 1024, k // 1024))

    print('[WEBP] Summe gross: %d KB' % (summe // 1024))

    # Aufraeumen: Ansichten, die es nicht mehr gibt (bad, galerie), duerfen
    # nicht als alte Datei liegen bleiben -- sonst zeigt ein Register
    # irgendwann ein Bild, das zur heutigen Villa nicht mehr passt.
    for f in sorted(os.listdir(ZIEL)):
        if f.endswith('.webp') and f not in behalten:
            os.remove(os.path.join(ZIEL, f))
            print('[WEBP] entfernt ' + f)
    print('[WEBP] fertig')


main()
