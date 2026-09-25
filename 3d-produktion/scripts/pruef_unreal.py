# -*- coding: utf-8 -*-
"""Der Wirklichkeitsabgleich fuer die Unreal-Bilder -- in Zahlen.

Aufruf:
    blender.exe -b -P pruef_unreal.py -- ordner=<pfad> [felder=<datei.json>]

WAS GEMESSEN WIRD UND WARUM
1. Belichtung je Kamera. Sollwert fuer den Median ist 0,50: Das ist der
   mittlere Grauwert, auf den eine Kamera eine durchschnittliche Szene
   belichtet. Dazu der Anteil, der unten (unter 0,02) und oben (ueber
   0,98) anliegt -- was dort liegt, ist Information, die es nicht mehr
   gibt.
2. Spaltenspannweite in einem Feld. Fuer den Spurenzweig: Wenn eine
   Fassade ueber ihre Breite immer denselben Wert hat, steht dort keine
   Struktur, egal was der Regler sagt. Am 19.09.2026 waren es 0,0085 --
   also nichts.
3. Zwei Felder im Verhaeltnis. Fuer den Pool gegen den Himmel: Wasser,
   das im Bild dieselbe Helligkeit hat wie Stein, ist kein Wasser.
4. Waagerechte Bruchkante. Sucht die groesste Helligkeitsstufe zwischen
   zwei benachbarten Bildzeilen im oberen Drittel. Eine Gelaendekante
   zeigt sich genau so -- und zwar bevor man sie bewusst sieht.

Felder kommen aus einer JSON-Datei, damit die Messung wiederholbar ist
und nicht jedes Mal neu geschaetzt wird:
    {"garten": {"fassade": [x0,y0,x1,y1], "pool": [...], "himmel": [...]}}
Koordinaten in Bruchteilen, Ursprung OBEN LINKS.
"""
import json
import os
import statistics
import sys

import bpy


def argumente():
    roh = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    teile = []
    for a in roh:
        teile.extend([x for x in a.replace(';', ',').split(',') if x])
    w = {}
    for t in teile:
        if '=' in t:
            k, v = t.split('=', 1)
            w[k.strip()] = v.strip()
    return w


def graustufen(px, w, h, feld):
    """Helligkeit als Liste, zeilenweise. Feld in Bruchteilen, oben links."""
    fx0, fy0, fx1, fy1 = feld
    x0, x1 = int(fx0 * w), int(fx1 * w)
    y0, y1 = int((1.0 - fy1) * h), int((1.0 - fy0) * h)
    zeilen = []
    for y in range(max(0, y0), min(h, y1)):
        zeile = []
        for x in range(max(0, x0), min(w, x1)):
            i = (y * w + x) * 4
            zeile.append(0.2126 * px[i] + 0.7152 * px[i + 1]
                         + 0.0722 * px[i + 2])
        if zeile:
            zeilen.append(zeile)
    return zeilen


def flach(zeilen):
    return [v for z in zeilen for v in z]


def spaltenspanne(zeilen):
    """Spannweite der Spaltenmittel. Das Mass fuer den Spurenzweig."""
    if not zeilen:
        return 0.0, 0.0
    breite = min(len(z) for z in zeilen)
    mittel = []
    for x in range(breite):
        mittel.append(sum(z[x] for z in zeilen) / float(len(zeilen)))
    return max(mittel) - min(mittel), statistics.pstdev(mittel)


def bruchkante(px, w, h):
    """Groesste Stufe zwischen zwei Zeilen im oberen Drittel.

    Gemittelt ueber die ganze Bildbreite -- eine Gelaendekante laeuft
    quer durchs Bild, ein Dachrand nicht.
    """
    mittel = []
    for y in range(0, h // 2):
        s = 0.0
        for x in range(0, w, 4):
            i = (y * w + x) * 4
            s += 0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2]
        mittel.append(s / float(len(range(0, w, 4))))
    groesste, wo = 0.0, 0
    for y in range(1, len(mittel)):
        d = abs(mittel[y] - mittel[y - 1])
        if d > groesste:
            groesste, wo = d, y
    return groesste, wo


def main():
    w = argumente()
    ordner = w.get('ordner', r'C:\Users\manue\Desktop\Vecom Design'
                             r'\3d-produktion\ausgabe\unreal')
    # Der Pfad steht als Vorgabe IM Skript und nicht im Aufruf: Er
    # enthaelt ein Leerzeichen ("Vecom Design"), und PowerShell reicht
    # ihn ueber -- an Blender als ZWEI Argumente weiter. Das hat beim
    # ersten Versuch genau dazu gefuehrt, dass die Felder still fehlten.
    felder = {}
    fpfad = w.get('felder') or (r'C:\Users\manue\Desktop\Vecom Design'
                                r'\werkzeug\ue-felder.json')
    if os.path.exists(fpfad):
        with open(fpfad, encoding='utf-8') as f:
            felder = json.load(f)
        print('[pruef] Felder aus %s' % os.path.basename(fpfad))
    else:
        print('[pruef] Keine Felderdatei: %s' % fpfad)

    dateien = sorted(f for f in os.listdir(ordner)
                     if f.lower().endswith(('.png', '.exr', '.jpg')))
    if not dateien:
        print('[pruef] Keine Bilder in %s' % ordner)
        return
    print('[pruef] %d Bilder in %s' % (len(dateien), ordner))
    print('[pruef] %-12s %6s %6s %7s %7s  %s'
          % ('Kamera', 'Mittel', 'Median', 'schwarz', 'weiss', 'Bruchkante'))

    for d in dateien:
        pfad = os.path.join(ordner, d)
        try:
            im = bpy.data.images.load(pfad)
        except Exception as f:
            print('[pruef] %s nicht lesbar: %s' % (d, f))
            continue
        bw, bh = im.size
        px = list(im.pixels)
        alle = flach(graustufen(px, bw, bh, (0.0, 0.0, 1.0, 1.0)))
        alle.sort()
        n = len(alle)
        schwarz = sum(1 for v in alle if v < 0.02) / float(n) * 100.0
        weiss = sum(1 for v in alle if v > 0.98) / float(n) * 100.0
        stufe, zeile = bruchkante(px, bw, bh)
        name = os.path.splitext(d)[0]
        print('[pruef] %-12s %6.3f %6.3f %6.1f%% %6.1f%%  %.4f in Zeile %d'
              % (name[:12], sum(alle) / n, alle[n // 2], schwarz, weiss,
                 stufe, zeile))

        # Felder, wenn fuer diese Kamera welche hinterlegt sind
        schl = None
        for k in felder:
            if k in name:
                schl = k
                break
        if schl:
            # EIN FELD HAT EINE SONDERBEHANDLUNG: 'horizont'.
            #
            # Die Bruchkante oben mittelt ueber die GANZE Bildbreite. In
            # Zeile 534 des Gartenblicks steht zum groessten Teil das
            # Haus, und deshalb blieb dieser Wert ueber vier Laeufe auf
            # drei Stellen gleich, egal was am Horizont geaendert wurde.
            # Er misst etwas Bauliches, keine Gelaendekante.
            # Ein Streifen, in dem NUR Land und Himmel stehen, misst das
            # Richtige: die groesste Stufe zwischen zwei benachbarten
            # Zeilen innerhalb dieses Streifens.
            if 'horizont' in felder[schl]:
                z = graustufen(px, bw, bh, felder[schl]['horizont'])
                if len(z) > 2:
                    mw = [sum(r) / float(len(r)) for r in z]
                    stufen = [(abs(mw[i] - mw[i - 1]), i)
                              for i in range(1, len(mw))]
                    groesste, wo = max(stufen)
                    spanne = max(mw) - min(mw)
                    print('[pruef]    %-12s Stufe=%.4f in Streifenzeile %d '
                          'von %d, Spanne Land-Himmel=%.4f'
                          % ('horizont', groesste, wo, len(mw), spanne))
            for fname, feld in sorted(felder[schl].items()):
                if fname == 'horizont':
                    continue
                z = graustufen(px, bw, bh, feld)
                v = flach(z)
                if not v:
                    continue
                v2 = sorted(v)
                spanne, streu = spaltenspanne(z)
                print('[pruef]    %-12s med=%.4f  Spaltenspanne=%.4f  '
                      'Streuung=%.4f' % (fname, v2[len(v2) // 2],
                                         spanne, streu))
        bpy.data.images.remove(im)
    print('[pruef] FERTIG')


main()
