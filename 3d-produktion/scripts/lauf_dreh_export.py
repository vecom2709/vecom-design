# -*- coding: utf-8 -*-
"""Headless: die Hero-Variante als Tisch und Buehne getrennt ausgeben.

Aufruf:  blender.exe -b -P lauf_dreh_export.py
"""
import bpy, os, sys, json, traceback

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.insert(0, P)

BERICHT = os.path.join(os.path.dirname(P), 'unreal-export', 'drehung',
                       'export-bericht.json')

try:
    # Leere Szene: die Bauskripte legen alles selbst an.
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o, do_unlink=True)

    import tisch_unreal as tu
    b = tu.exportieren_dreh(laenge='mittel', gestell='wange',
                            holz='Eiche', metall='Messing')
    os.makedirs(os.path.dirname(BERICHT), exist_ok=True)
    with open(BERICHT, 'w', encoding='utf-8') as f:
        json.dump(b, f, indent=2, ensure_ascii=False)
    print('[DREH] fertig')
    print(json.dumps(b, indent=2, ensure_ascii=False))
except Exception:
    print('[DREH] FEHLER')
    traceback.print_exc()
