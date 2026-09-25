# -*- coding: utf-8 -*-
"""Bildbereiche messen statt hinsehen.

Aufruf:  blender.exe -b -P messen_bild.py -- datei.png x0;y0;x1;y1;name ...

Koordinaten in Bruchteilen der Bildbreite/-hoehe, Ursprung OBEN LINKS --
so, wie man ein Bild ansieht. Blender legt den Ursprung unten links; das
wird hier umgerechnet, damit man beim Ablesen nicht umdenken muss.
"""
import os
import statistics
import sys

import bpy


def main():
    roh = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    if not roh:
        print('[mess] keine Datei')
        return
    # PowerShell reicht ein Array je nach Aufrufform als ein einziges
    # Argument durch. Deshalb wird hier zusaetzlich am senkrechten Strich
    # getrennt -- der kommt in keinem Feldnamen vor.
    zerlegt = []
    for a in roh:
        zerlegt.extend([x for x in a.split('|') if x])
    roh = zerlegt
    pfad = roh[0]
    if not os.path.isabs(pfad):
        pfad = os.path.join(os.path.dirname(os.path.dirname(
            os.path.abspath(__file__))), 'render', pfad)
    im = bpy.data.images.load(pfad)
    w, h = im.size
    px = list(im.pixels)
    print('[mess] %s  %dx%d' % (os.path.basename(pfad), w, h))
    for feld in roh[1:]:
        t = feld.split(';')
        if len(t) < 4:
            continue
        fx0, fy0, fx1, fy1 = (float(t[0]), float(t[1]), float(t[2]), float(t[3]))
        name = t[4] if len(t) > 4 else feld
        x0, x1 = int(fx0 * w), int(fx1 * w)
        # oben-links -> unten-links
        y0, y1 = int((1.0 - fy1) * h), int((1.0 - fy0) * h)
        werte = []
        for y in range(max(0, y0), min(h, y1), 2):
            for x in range(max(0, x0), min(w, x1), 2):
                i = (y * w + x) * 4
                werte.append(0.2126 * px[i] + 0.7152 * px[i + 1]
                             + 0.0722 * px[i + 2])
        if not werte:
            continue
        werte.sort()
        n = len(werte)
        ueber = sum(1 for v in werte if v > 0.97) / float(n)
        print('[mess] %-16s n=%6d  p05=%.3f med=%.3f p95=%.3f  '
              'std=%.3f  geklippt=%.1f%%'
              % (name, n, werte[n // 20], werte[n // 2], werte[19 * n // 20],
                 statistics.pstdev(werte), ueber * 100.0))
    bpy.data.images.remove(im)
    print('[mess] FERTIG')


main()
