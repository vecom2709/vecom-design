# -*- coding: utf-8 -*-
"""Bereitet die Szene fuer SheepIt vor -- und sagt hinterher, was wirklich
in der Datei steht.

WARUM ES DIESE DATEI GIBT. Zwei Laeufe sind an Dingen gescheitert, die man
nicht sieht, wenn man auf das Bild schaut:

  Lauf 1  kam in 960x540 zurueck. Ein Testaufruf mit EEVEE hatte Aufloesung
          und Ausgabepfad ueberschrieben; das finally, das beide
          zuruecksetzen sollte, lief nie, weil die Verbindung vorher
          abbrach. Hochgeladen wurde, ohne noch einmal hinzusehen.
  Lauf 2  wurde von der Farm gesperrt: 41m12s je Bild. SheepIt laesst
          hoechstens 20 Minuten zu, und mit Denoising laesst sich ein Bild
          nicht teilen -- die Zeit muss also direkt herunter.

Daraus die zwei Regeln, die dieses Skript erzwingt:
  1. Speichern, neu laden, NACHLESEN. Was im laufenden Blender steht, ist
     nicht, was in der Datei steht.
  2. Erst wegnehmen, was nichts beitraegt. Dann erst an die Samples, und an
     die auch nur mit einer Messung in der Hand.

DER WEG VON 41 AUF 17 MINUTEN, in drei Zahlen:

  41m12s  wie hochgeladen.
  29m59s  nach dem Aufraeumen der Einstellungen, die in diesem Saal nichts
          beitragen: Lichtspruenge 24 -> 8 (kein Glas im Raum, nichts
          Lichtdurchlaessiges), Volumenschrittweite 1,0 -> 6,0 (der Dunst
          ist ein Hauch, kein Rauch), reflektierende Kaustiken aus
          (Spiegelboden, sieht niemand).
  17m43s  nach 256 statt 384 Samples -- und das erst, nachdem vier
          Testbilder in echtem 1080p gezeigt hatten, dass dafuer Luft ist:
          Rauschen an der Decke 6,5 statt 10,5, an der Wand 2,9 statt 4,5,
          also sauberer als der erste Durchgang.

Aufruf im Blender-Python:
    exec(open(r'...\\farmvorbereitung.py').read())
    ergebnis = vorbereiten(r'C:\\...\\vecom-showroom_Farm.blend')
"""

import bpy
import os

# Was die Farm sehen soll. Jede Zeile ist eine Entscheidung, keine Vorgabe.
SOLL = {
    'engine':            'CYCLES',
    'device':            'GPU',          # die Farm entscheidet selbst neu
    'aufloesung_x':      1920,
    'aufloesung_y':      1080,
    'prozent':           100,
    'samples':           256,            # gemessen, siehe Kopf
    'adaptive_schwelle': 0.01,
    'denoise':           True,
    'lichtspruenge':     8,              # war 24
    'durchsicht':        4,
    'volumen':           2,
    'volumen_schritt':   6.0,            # war 1.0
    'kaustik_spiegel':   False,
    'kaustik_brechung':  False,
    'bild_start':        1,
    'bild_ende':         240,
    'bildrate':          24,
    'format':            'PNG',
    'farbtiefe':         '8',
    'film_transparent':  False,
}


def _setzen():
    s = bpy.context.scene
    r = s.render
    c = s.cycles

    r.engine                = SOLL['engine']
    c.device                = SOLL['device']
    r.resolution_x          = SOLL['aufloesung_x']
    r.resolution_y          = SOLL['aufloesung_y']
    r.resolution_percentage = SOLL['prozent']
    c.samples               = SOLL['samples']
    c.adaptive_threshold    = SOLL['adaptive_schwelle']
    c.use_denoising         = SOLL['denoise']
    c.max_bounces           = SOLL['lichtspruenge']
    c.transmission_bounces  = SOLL['durchsicht']
    c.volume_bounces        = SOLL['volumen']
    c.volume_step_rate      = SOLL['volumen_schritt']
    c.caustics_reflective   = SOLL['kaustik_spiegel']
    c.caustics_refractive   = SOLL['kaustik_brechung']
    s.frame_start           = SOLL['bild_start']
    s.frame_end             = SOLL['bild_ende']
    r.fps                   = SOLL['bildrate']
    r.image_settings.file_format = SOLL['format']
    r.image_settings.color_depth = SOLL['farbtiefe']
    r.film_transparent      = SOLL['film_transparent']


def _lesen():
    """Liest zurueck, was tatsaechlich gilt -- nicht, was gesetzt wurde."""
    s = bpy.context.scene
    r = s.render
    c = s.cycles
    return {
        'engine':            r.engine,
        'device':            c.device,
        'aufloesung_x':      r.resolution_x,
        'aufloesung_y':      r.resolution_y,
        'prozent':           r.resolution_percentage,
        'samples':           c.samples,
        'adaptive_schwelle': round(c.adaptive_threshold, 4),
        'denoise':           c.use_denoising,
        'lichtspruenge':     c.max_bounces,
        'durchsicht':        c.transmission_bounces,
        'volumen':           c.volume_bounces,
        'volumen_schritt':   round(c.volume_step_rate, 2),
        'kaustik_spiegel':   c.caustics_reflective,
        'kaustik_brechung':  c.caustics_refractive,
        'bild_start':        s.frame_start,
        'bild_ende':         s.frame_end,
        'bildrate':          r.fps,
        'format':            r.image_settings.file_format,
        'farbtiefe':         r.image_settings.color_depth,
        'film_transparent':  r.film_transparent,
    }


def vorbereiten(ziel, neu_laden=True):
    """Setzt, speichert, laedt neu und liest nach.

    Das Neuladen ist der ganze Punkt: Lauf 1 kam nur deshalb in halber
    Aufloesung zurueck, weil niemand nach dem Speichern noch einmal
    hingesehen hat. Gibt (in_ordnung, abweichungen) zurueck.
    """
    _setzen()
    bpy.ops.wm.save_as_mainfile(filepath=ziel)

    if neu_laden:
        bpy.ops.wm.open_mainfile(filepath=ziel)

    ist = _lesen()
    abweichung = {k: (SOLL[k], ist[k]) for k in SOLL if SOLL[k] != ist[k]}

    print('--- Farmvorbereitung:', os.path.basename(ziel))
    for k in sorted(ist):
        zeichen = ' ' if k not in abweichung else '!'
        print(f'  {zeichen} {k:20s} {ist[k]}')
    if abweichung:
        print('  ABWEICHUNGEN (soll, ist):')
        for k, v in abweichung.items():
            print(f'    {k}: {v[0]} -> {v[1]}')
        print('  NICHT HOCHLADEN.')
    else:
        print('  Alle', len(SOLL), 'Einstellungen stehen. Datei darf hoch.')
    return (not abweichung), abweichung
