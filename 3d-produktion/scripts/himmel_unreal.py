# -*- coding: utf-8 -*-
"""Denselben Himmel fuer Unreal -- und die Zahl, die bisher gefehlt hat.

WARUM

In Cycles gibt es genau EINE Lichtquelle: den Nishita-Himmel mit
Sonnenscheibe (villa_szene.SONNE_STAERKE = 0.0 -- die Sonnenlampe ist
AUS). Sonne und Himmel stammen dort aus einem Modell und stehen damit
zwangslaeufig im richtigen Verhaeltnis zueinander.

In Unreal wurde daraus dreierlei nachgebaut: SkyAtmosphere, eine
DirectionalLight mit 110.000 lx bei 5200 K und ein SkyLight, das die
Szene selbst erfasst. Drei Naeherungen, deren Verhaeltnis niemand
gemessen hat. Genau dieses Verhaeltnis entscheidet aber ueber die drei
gemeldeten Abweichungen: zu dunkler Rasen, beige statt neutrale
Weisstoene, dunkles Glas.

WAS DIESES SKRIPT LIEFERT

  1. himmel-unreal.hdr -- der Himmel OHNE Sonnenscheibe, linear, als
     Kugelpanorama fuer das SkyLight (SLS_SPECIFIED_CUBEMAP).
     Ohne Scheibe, weil die Sonne in Unreal eine eigene gerichtete Lampe
     ist; stuende sie auch im Panorama, zaehlte sie doppelt.
     MIT unterer Halbkugel, so wie das Modell sie liefert -- anders als
     bei der Browserfassung, denn Unreal rechnet den Bodenanteil selbst
     und bekaeme ihn sonst zweimal.

  2. himmel-unreal.json -- die gemessene Beleuchtungsstaerke des Himmels
     und der Sonnenscheibe, je Farbkanal, cosinusgewichtet ueber die
     obere Halbkugel. Daraus folgt das Verhaeltnis Himmel zu Sonne und
     die Farbe beider -- die Zahlen, gegen die Unreal eingestellt wird.

Gemessen wird ueber zwei Panoramen (ohne und mit Scheibe) in linearem
EXR; die Differenz IST die Sonne. Kein Schaetzen.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript himmel_unreal.py -Log himmel-ue.log
"""
import bpy
import json
import math
import os
import sys

import numpy as np

HIER = os.path.dirname(os.path.abspath(__file__))
if HIER not in sys.path:
    sys.path.append(HIER)

import villa_szene

AUS = os.path.join(os.path.dirname(HIER), 'ausgabe')
WERK = r'C:\Users\manue\Desktop\Vecom Design\werkzeug'

# Messaufloesung: 2048x1024 gibt 0,176 Grad je Pixel. Die Sonnenscheibe
# hat 0,545 Grad, deckt also rund drei Pixel -- genug, dass ihr Fluss
# beim Filtern erhalten bleibt. Bei 1024x512 waere sie 1,6 Pixel breit
# und der gemessene Wert haette am Filterkern gehangen.
MESS_B, MESS_H = 2048, 1024
HDR_B, HDR_H = 2048, 1024


def leere_szene():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)


def kugelkamera():
    kd = bpy.data.cameras.new('Panorama')
    kd.type = 'PANO'
    for ziel in (kd, getattr(kd, 'cycles', None)):
        if ziel is not None and hasattr(ziel, 'panorama_type'):
            ziel.panorama_type = 'EQUIRECTANGULAR'
    ko = bpy.data.objects.new('Panorama', kd)
    bpy.context.collection.objects.link(ko)
    ko.rotation_euler = (math.radians(90.0), 0.0, 0.0)
    ko.location = (9.0, 5.5, 1.6)
    bpy.context.scene.camera = ko
    return ko


def himmel(scheibe):
    sky = villa_szene.himmel()
    villa_szene.sonnenstand(villa_szene.SONNE_HOEHE, villa_szene.SONNE_AZIMUT)
    sky.sun_disc = bool(scheibe)
    return sky


def panorama(pfad, breite, hoehe, format_, tiefe='32'):
    sz = bpy.context.scene
    sz.render.resolution_x, sz.render.resolution_y = breite, hoehe
    sz.render.resolution_percentage = 100
    sz.render.image_settings.file_format = format_
    sz.render.image_settings.color_mode = 'RGB'
    if format_ == 'OPEN_EXR':
        sz.render.image_settings.color_depth = tiefe
    # 'Raw' heisst: keine Anzeigekennlinie. Bei EXR ignoriert Blender sie
    # ohnehin, bei Radiance-HDR NICHT -- dort wuerde 'Standard' eine
    # sRGB-Kennlinie einbacken und die Datei waere nicht mehr linear.
    sz.view_settings.view_transform = 'Raw'
    sz.view_settings.look = 'None'
    sz.view_settings.exposure = 0.0
    sz.render.filepath = pfad
    bpy.ops.render.render(write_still=True)
    return pfad


def lies(pfad, breite, hoehe):
    """Bildinhalt als (hoehe, breite, 3), Zeile 0 = Zenit."""
    bild = bpy.data.images.load(pfad)
    n = breite * hoehe * 4
    roh = np.empty(n, dtype=np.float32)
    bild.pixels.foreach_get(roh)
    bpy.data.images.remove(bild)
    # Blender legt Pixel von UNTEN nach oben ab -- also umdrehen.
    return roh.reshape(hoehe, breite, 4)[::-1, :, :3].astype(np.float64)


def beleuchtungsstaerke(bild):
    """Cosinusgewichtete Beleuchtungsstaerke einer waagerechten Flaeche,
    je Farbkanal, aus der OBEREN Halbkugel.

        E = Integral L(w) cos(t) dw
          = Summe L * cos(t) * sin(t) * dt * dp

    t ist der Winkel zum Zenit. Zeile j deckt t = pi*(j+0.5)/H.
    """
    hoehe, breite = bild.shape[0], bild.shape[1]
    t = np.pi * (np.arange(hoehe) + 0.5) / hoehe
    gewicht = np.where(t < np.pi / 2.0, np.cos(t) * np.sin(t), 0.0)
    dt = np.pi / hoehe
    dp = 2.0 * np.pi / breite
    return (bild * gewicht[:, None, None]).sum(axis=(0, 1)) * dt * dp


def unten(bild):
    """Mittlere Radianz der unteren Halbkugel -- nur zur Einordnung."""
    hoehe = bild.shape[0]
    return bild[hoehe // 2:, :, :].mean(axis=(0, 1))


def haupt():
    sz = bpy.context.scene
    leere_szene()
    kugelkamera()

    sz.render.engine = 'CYCLES'
    sz.cycles.samples = 256
    sz.cycles.use_denoising = False   # Entrauschen verfaelscht die Messung
    sz.render.film_transparent = False

    ohne = os.path.join(AUS, '_ue-himmel-ohne.exr')
    mit = os.path.join(AUS, '_ue-himmel-mit.exr')

    himmel(scheibe=False)
    panorama(ohne, MESS_B, MESS_H, 'OPEN_EXR')
    a = lies(ohne, MESS_B, MESS_H)

    himmel(scheibe=True)
    panorama(mit, MESS_B, MESS_H, 'OPEN_EXR')
    b = lies(mit, MESS_B, MESS_H)

    e_himmel = beleuchtungsstaerke(a)
    e_gesamt = beleuchtungsstaerke(b)
    e_sonne_waag = np.maximum(e_gesamt - e_himmel, 0.0)
    sinh = math.sin(math.radians(villa_szene.SONNE_HOEHE))
    e_sonne_senk = e_sonne_waag / sinh

    def leuchtdichte(v):
        """Grobe photometrische Wichtung, nur fuer den Vergleich der
        Kanaele untereinander -- nicht als absolute Candela."""
        return float(0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2])

    y_h = leuchtdichte(e_himmel)
    y_s = leuchtdichte(e_sonne_senk)
    verhaeltnis = y_h / y_s if y_s > 0 else 0.0

    def weiss(v):
        s = float(v.sum())
        return [round(float(x) / s, 4) for x in v] if s > 0 else [0, 0, 0]

    bericht = {
        'sonne_hoehe': villa_szene.SONNE_HOEHE,
        'sonne_azimut': villa_szene.SONNE_AZIMUT,
        'e_himmel_rgb': [round(float(x), 5) for x in e_himmel],
        'e_gesamt_rgb': [round(float(x), 5) for x in e_gesamt],
        'e_sonne_waagerecht_rgb': [round(float(x), 5) for x in e_sonne_waag],
        'e_sonne_senkrecht_rgb': [round(float(x), 5) for x in e_sonne_senk],
        'y_himmel': round(y_h, 5),
        'y_sonne_senkrecht': round(y_s, 5),
        'verhaeltnis_himmel_zu_sonne': round(verhaeltnis, 5),
        'himmel_weisspunkt': weiss(e_himmel),
        'sonne_weisspunkt': weiss(e_sonne_senk),
        'untere_halbkugel_mittel': [round(float(x), 5) for x in unten(a)],
        'messaufloesung': [MESS_B, MESS_H],
    }

    print('[ue-himmel] Himmel  E = %s' % bericht['e_himmel_rgb'])
    print('[ue-himmel] Sonne   E senkrecht = %s'
          % bericht['e_sonne_senkrecht_rgb'])
    print('[ue-himmel] Verhaeltnis Himmel zu Sonne = %.4f' % verhaeltnis)
    print('[ue-himmel] Weisspunkt Himmel = %s' % bericht['himmel_weisspunkt'])
    print('[ue-himmel] Weisspunkt Sonne  = %s' % bericht['sonne_weisspunkt'])

    # Jetzt das Panorama ohne Scheibe als lineares Radiance-HDR.
    himmel(scheibe=False)
    hdr = os.path.join(AUS, 'himmel-unreal.hdr')
    panorama(hdr, HDR_B, HDR_H, 'HDR')
    bericht['hdr'] = hdr
    print('[ue-himmel] geschrieben: %s' % hdr)

    for ziel in (os.path.join(AUS, 'himmel-unreal.json'),
                 os.path.join(WERK, 'himmel-unreal.json')):
        with open(ziel, 'w', encoding='utf-8') as f:
            json.dump(bericht, f, indent=2, ensure_ascii=False)
        print('[ue-himmel] Bericht: %s' % ziel)

    for p in (ohne, mit):
        try:
            os.remove(p)
        except OSError:
            pass
    print('[ue-himmel] FERTIG')


haupt()
