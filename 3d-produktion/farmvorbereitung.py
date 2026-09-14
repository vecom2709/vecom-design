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
          beitragen: Lichtspruenge herunter (kein Glas im Raum, nichts
          Lichtdurchlaessiges), Volumenschrittweite 1,0 -> 6,0 (der Dunst
          ist ein Hauch, kein Rauch), reflektierende Kaustiken aus
          (Spiegelboden, sieht niemand).
  17m43s  nach 256 statt 384 Samples -- und das erst, nachdem vier
          Testbilder in echtem 1080p gezeigt hatten, dass dafuer Luft ist:
          Rauschen an der Decke 6,5 statt 10,5, an der Wand 2,9 statt 4,5,
          also sauberer als der erste Durchgang.

WOHER DIE TABELLE STAMMT -- und das ist die Lehre, die diese Datei am
teuersten bezahlt hat: Ihre erste Fassung war aus der Erinnerung an den
Commit-Text geschrieben, nicht aus der Datei. Am 14.09.2026 gegen
vecom-showroom_Farm1080c.blend geprueft -- also gegen genau die Datei, die
den durchgelaufenen 240-Bilder-Lauf erzeugt hat -- waren vier Werte falsch:

  Rechengeraet        stand auf GPU,  in der Datei ist CPU
  Adaptive Schwelle   stand auf 0,01, in der Datei 0,02
  Lichtspruenge       stand auf 8,    in der Datei 6
  Volumenspruenge     stand auf 2,    in der Datei 1

Alle vier haetten den Lauf teurer gemacht, zwei davon deutlich -- und 6
Spruenge statt 8 sind kein Detail, wenn die Grenze bei 20 Minuten liegt.
Dazu fehlten Werte ganz, die genauso zaehlen: diffuse und glaenzende
Spruenge einzeln, die Mindestsamples, die drei Denoiser-Einstellungen und
die Obergrenze der Volumenschritte. Die Tabelle unten ist jetzt
abgelesen, nicht erinnert.
"""

import bpy
import os

# Abgelesen aus vecom-showroom_Farm1080c.blend am 14.09.2026 -- der Datei,
# aus der die 240 Bilder in 1920x1080 tatsaechlich herausgekommen sind.
SOLL = {
    'engine':              'CYCLES',
    'device':              'CPU',      # die Farm waehlt ohnehin selbst
    'aufloesung_x':        1920,
    'aufloesung_y':        1080,
    'prozent':             100,
    'samples':             256,
    'adaptiv':             True,
    'adaptive_schwelle':   0.02,
    'adaptive_min':        24,
    'denoise':             True,
    'denoiser':            'OPENIMAGEDENOISE',
    'denoise_pass':        'RGB_ALBEDO_NORMAL',
    'denoise_vorfilter':   'ACCURATE',
    'lichtspruenge':       6,
    'diffus':              3,
    'glanz':               4,
    'durchsicht':          4,
    'volumen':             1,
    'volumen_schritt':     6.0,
    'volumen_max':         256,
    'kaustik_spiegel':     False,
    'kaustik_brechung':    False,
    'zeitgrenze':          0,
    'bild_start':          1,
    'bild_ende':           240,
    'bildrate':            24,
    'format':              'PNG',
    'farbtiefe':           '8',
    'film_transparent':    False,
}


def _setzen(soll=None):
    soll = soll or SOLL
    s = bpy.context.scene
    r = s.render
    c = s.cycles

    r.engine                = soll['engine']
    c.device                = soll['device']
    r.resolution_x          = soll['aufloesung_x']
    r.resolution_y          = soll['aufloesung_y']
    r.resolution_percentage = soll['prozent']
    c.samples               = soll['samples']
    c.use_adaptive_sampling = soll['adaptiv']
    c.adaptive_threshold    = soll['adaptive_schwelle']
    c.adaptive_min_samples  = soll['adaptive_min']
    c.use_denoising         = soll['denoise']
    c.denoiser              = soll['denoiser']
    c.denoising_input_passes = soll['denoise_pass']
    c.denoising_prefilter   = soll['denoise_vorfilter']
    c.max_bounces           = soll['lichtspruenge']
    c.diffuse_bounces       = soll['diffus']
    c.glossy_bounces        = soll['glanz']
    c.transmission_bounces  = soll['durchsicht']
    c.volume_bounces        = soll['volumen']
    c.volume_step_rate      = soll['volumen_schritt']
    c.volume_max_steps      = soll['volumen_max']
    c.caustics_reflective   = soll['kaustik_spiegel']
    c.caustics_refractive   = soll['kaustik_brechung']
    c.time_limit            = soll['zeitgrenze']
    s.frame_start           = soll['bild_start']
    s.frame_end             = soll['bild_ende']
    r.fps                   = soll['bildrate']
    r.image_settings.file_format = soll['format']
    r.image_settings.color_depth = soll['farbtiefe']
    r.film_transparent      = soll['film_transparent']


def lesen():
    """Liest zurueck, was tatsaechlich gilt -- nicht, was gesetzt wurde."""
    s = bpy.context.scene
    r = s.render
    c = s.cycles
    return {
        'engine':              r.engine,
        'device':              c.device,
        'aufloesung_x':        r.resolution_x,
        'aufloesung_y':        r.resolution_y,
        'prozent':             r.resolution_percentage,
        'samples':             c.samples,
        'adaptiv':             c.use_adaptive_sampling,
        'adaptive_schwelle':   round(c.adaptive_threshold, 4),
        'adaptive_min':        c.adaptive_min_samples,
        'denoise':             c.use_denoising,
        'denoiser':            c.denoiser,
        'denoise_pass':        c.denoising_input_passes,
        'denoise_vorfilter':   c.denoising_prefilter,
        'lichtspruenge':       c.max_bounces,
        'diffus':              c.diffuse_bounces,
        'glanz':               c.glossy_bounces,
        'durchsicht':          c.transmission_bounces,
        'volumen':             c.volume_bounces,
        'volumen_schritt':     round(c.volume_step_rate, 2),
        'volumen_max':         c.volume_max_steps,
        'kaustik_spiegel':     c.caustics_reflective,
        'kaustik_brechung':    c.caustics_refractive,
        'zeitgrenze':          c.time_limit,
        'bild_start':          s.frame_start,
        'bild_ende':           s.frame_end,
        'bildrate':            r.fps,
        'format':              r.image_settings.file_format,
        'farbtiefe':           r.image_settings.color_depth,
        'film_transparent':    r.film_transparent,
    }


def pruefen(soll=None):
    """Vergleicht die Datei mit der Tabelle, ohne etwas zu aendern."""
    soll = soll or SOLL
    ist = lesen()
    return {k: (soll[k], ist[k]) for k in soll if soll[k] != ist[k]}


def vorbereiten(ziel, soll=None, neu_laden=True):
    """Setzt, speichert, laedt neu und liest nach.

    Das Neuladen ist der ganze Punkt: Lauf 1 kam nur deshalb in halber
    Aufloesung zurueck, weil niemand nach dem Speichern noch einmal
    hingesehen hat. Gibt (in_ordnung, abweichungen) zurueck.
    """
    soll = soll or SOLL
    _setzen(soll)
    bpy.ops.wm.save_as_mainfile(filepath=ziel)

    if neu_laden:
        bpy.ops.wm.open_mainfile(filepath=ziel)

    abweichung = pruefen(soll)
    ist = lesen()

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
        print('  Alle', len(soll), 'Einstellungen stehen. Datei darf hoch.')
    return (not abweichung), abweichung
