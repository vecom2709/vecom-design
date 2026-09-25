# -*- coding: utf-8 -*-
"""Headless: die frisch gebackenen Web-Texturen nach WebP wandeln.

FALLE, die hier zweimal zugeschlagen hat
Blender ENTWICKELT ein Bild beim Speichern noch einmal -- mit dem
Ansichtstransfer der Szene. Steht der auf AgX oder Filmic, kommt eine
zweite Gradation obendrauf und die Karte ist verdorben. Deshalb eine
eigene Hilfsszene mit view_transform='Standard'.

Und die Rauheitskarte ist kein Bild, sondern eine Zahl je Bildpunkt:
Sie wird als Non-Color geladen, damit niemand eine Gammakurve darauf
legt.

Aufruf:  blender.exe -b -P lauf_web_webp.py
"""
import bpy, os, sys, json, traceback

P = os.path.dirname(os.path.abspath(__file__))
AUS = os.path.join(os.path.dirname(P), 'web-export')
GUETE = 88

try:
    hilfs = bpy.data.scenes.get('WebP_Hilfe') or bpy.data.scenes.new('WebP_Hilfe')
    hilfs.view_settings.view_transform = 'Standard'
    hilfs.view_settings.look = 'None'
    hilfs.render.image_settings.file_format = 'WEBP'
    hilfs.render.image_settings.color_mode = 'RGB'
    hilfs.render.image_settings.quality = GUETE

    bericht = {}
    for datei in sorted(f for f in os.listdir(AUS)
                        if f.startswith('holz-') and f.endswith('.png')):
        stamm = os.path.splitext(datei)[0]
        bild = bpy.data.images.load(os.path.join(AUS, datei), check_existing=False)
        bild.colorspace_settings.name = 'Non-Color' if stamm.endswith('rauheit') else 'sRGB'
        ziel = os.path.join(AUS, stamm + '.webp')
        bild.save_render(filepath=ziel, scene=hilfs)
        bericht[stamm] = dict(px=list(bild.size),
                              kb=round(os.path.getsize(ziel) / 1024.0, 1))
        bpy.data.images.remove(bild)
    print('[WEBP] fertig')
    print(json.dumps(bericht, indent=2, ensure_ascii=False))
except Exception:
    print('[WEBP] FEHLER')
    traceback.print_exc()
