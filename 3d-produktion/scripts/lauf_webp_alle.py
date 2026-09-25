# -*- coding: utf-8 -*-
"""Headless: alle drei Saetze nach WebP wandeln.

Aufruf:  blender.exe -b -P lauf_webp_alle.py

Die Werte je Satz sind nicht beliebig:
  tisch-serie   Heroansicht, kein Beschnitt.
  tisch-platte  oben 250 Zeilen ab -- bei kurzen Tischen bliebe dort sonst
                ein Drittel Schwarz, weil die Kamera fuer alle 72 gleich
                steht (und das ist Absicht).
  tisch-dreh    kleine Fassung nur 360 px: Beim Ziehen liegen alle 36
                gleichzeitig im Speicher.
"""
import bpy, os, sys, json, traceback

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.insert(0, P)
RENDER = os.path.join(os.path.dirname(P), 'render')

SAETZE = [
    ('tisch-serie',  dict(guete=82, klein=480, guete_klein=78, beschnitt_oben=0)),
    ('tisch-platte', dict(guete=82, klein=480, guete_klein=78, beschnitt_oben=250)),
    ('tisch-dreh',   dict(guete=82, klein=360, guete_klein=76, beschnitt_oben=0)),
]

try:
    import tisch_unreal as tu
    gesamt = {}
    for ordner, werte in SAETZE:
        quelle = os.path.join(RENDER, ordner)
        if not os.path.isdir(quelle):
            gesamt[ordner] = 'Ordner fehlt'
            continue
        gesamt[ordner] = tu.webp_serie(quelle=quelle, **werte)
        print('[WEBP]', ordner, json.dumps(gesamt[ordner], ensure_ascii=False))
    print('[WEBP] fertig')
    print(json.dumps(gesamt, indent=2, ensure_ascii=False))
except Exception:
    print('[WEBP] FEHLER')
    traceback.print_exc()
