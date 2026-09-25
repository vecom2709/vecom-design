# -*- coding: utf-8 -*-
"""Headless: die 36 Drehbilder nach WebP wandeln, gross und klein.

Aufruf:  blender.exe -b -P lauf_dreh_webp.py
"""
import bpy, os, sys, json, traceback

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.insert(0, P)

QUELLE = os.path.join(os.path.dirname(P), 'render', 'tisch-dreh')

try:
    import tisch_unreal as tu
    # Kleiner als bei den Standbildern: Beim Ziehen liegen alle 36 kleinen
    # Fassungen zusammen im Speicher, und bei 480 px waeren das schon
    # ueber 150 KB fuer eine einzige Variante. 360 px reichen, solange das
    # grosse Bild sofort nachblendet, wenn die Hand stillsteht.
    b = tu.webp_serie(quelle=QUELLE, guete=82, klein=360, guete_klein=76)
    print('[WEBP] fertig')
    print(json.dumps(b, indent=2, ensure_ascii=False))
except Exception:
    print('[WEBP] FEHLER')
    traceback.print_exc()
