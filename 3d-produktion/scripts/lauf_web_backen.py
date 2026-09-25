# -*- coding: utf-8 -*-
"""Headless: die Web-Texturen neu backen.

ANLASS (16.09.2026)
Die verfeinerte Maserung -- Ringe als Baender statt als Hoehenlinien,
Spiegel auf den Eichen -- steckte nur in den Unreal-Karten (18:32 Uhr).
Die Karten fuer das Live-Modell im Browser waren noch vom Vormittag
(03:43 Uhr). Live-Modell und gerechnetes Bild zeigten also verschiedenes
Holz, und beide sind auf derselben Seite zu sehen.

Aufruf:  blender.exe -b vecom-tisch-studio.blend -P lauf_web_backen.py
"""
import bpy, os, sys, json, traceback

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.insert(0, P)

def alle_einblenden(lc=None):
    """Jede Sammlung wieder in die Ansichtsebene holen.

    Die gespeicherte Datei stammt aus einem Renderlauf, in dem
    nur_studio() alles ausser der Buehne ausgeschlossen hat. Backen
    braucht aber ein AUSWAEHLBARES Objekt, und ausgeschlossen heisst
    nicht auswaehlbar -- derselbe Stolperstein wie beim Unreal-Backen.
    """
    lc = lc or bpy.context.view_layer.layer_collection
    for k in lc.children:
        k.exclude = False
        k.hide_viewport = False
        alle_einblenden(k)


try:
    import tisch_web as tw
    alle_einblenden()
    b = tw.backen()
    print('[WEBBAKE] fertig')
    print(json.dumps(b, indent=2, ensure_ascii=False, default=str))
except Exception:
    print('[WEBBAKE] FEHLER')
    traceback.print_exc()
