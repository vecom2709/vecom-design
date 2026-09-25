# -*- coding: utf-8 -*-
"""Den Himmel der Villa als Kugelpanorama ausgeben -- als Umgebungslicht
fuer die Echtzeitfassung im Browser.

WARUM UEBERHAUPT

Die gerechneten Bilder der Villa entstehen unter Blenders Himmelsmodell
(MULTIPLE_SCATTERING) bei Belichtung -3,1. Das Bild lebt dort vom
Himmelslicht, nicht vom Schlaglicht: Am Standpunkt "Zufahrt" (Kamera im
Suedwesten) UND am Standpunkt "Garten" (Nordosten) sind die Fassaden
hell -- das kann keine einzelne gerichtete Sonne.

Die Echtzeitfassung im Browser hatte dagegen ein HemisphereLight mit 0,72
und einen 16x256-Verlauf als Umgebung. Alle von der Sonne abgewandten
Flaechen fielen damit ins Graue, und der Vergleich "Foto oder Echtzeit"
auf der Seite zeigte zwei verschiedene Haeuser.

WAS HIER ENTSTEHT

Ein Kugelpanorama DESSELBEN Himmels, aus derselben Szenendatei, mit
denselben Werten -- nicht ein zweiter, nachgebauter Himmel.

OHNE SONNENSCHEIBE. Zwei Gruende:
  1. Die Sonne ist im Browser eine eigene gerichtete Lampe. Stuende sie
     auch im Panorama, zaehlte sie doppelt.
  2. Eine 8-Bit-Datei kann den Helligkeitssprung zur Sonnenscheibe (Faktor
     mehrere Tausend) nicht tragen. Sie wuerde abgeschnitten und liefe als
     weisser Fleck mit falschem, weichem Schatten mit -- der bekannte
     Fehler bei HDRIs, deren Spitze gekappt ist.

Ohne Scheibe hat der Himmel selbst einen Umfang von etwa 20:1. Das passt
in 8 Bit, wenn die Belichtung sitzt. Deshalb misst dieses Skript zuerst
den hellsten Punkt und setzt die Belichtung danach -- statt sie zu raten.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript himmel_web.py -Log himmel.log
"""
import bpy
import math
import os
import sys

HIER = os.path.dirname(os.path.abspath(__file__))
if HIER not in sys.path:
    sys.path.append(HIER)

import villa_szene

BREITE = 1536
HOEHE = 768
ZIEL = os.path.join(os.path.dirname(HIER), 'ausgabe', 'himmel-equirect.png')


def leere_szene():
    """Alles raus ausser Welt und Kamera. Das Panorama zeigt den Himmel,
    nicht das Haus -- ein Haus darin wuerde sich spaeter in jeder
    Fensterscheibe des Hauses spiegeln."""
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)


def kugelkamera():
    kd = bpy.data.cameras.new('Panorama')
    kd.type = 'PANO'
    # Blender 4.x/5.x: panorama_type sitzt an der Kamera, nicht am Cycles-Block
    for ziel in (kd, getattr(kd, 'cycles', None)):
        if ziel is not None and hasattr(ziel, 'panorama_type'):
            ziel.panorama_type = 'EQUIRECTANGULAR'
    ko = bpy.data.objects.new('Panorama', kd)
    bpy.context.collection.objects.link(ko)
    # Waagerecht ausrichten: Blender schaut standardmaessig nach -Z.
    ko.rotation_euler = (math.radians(90.0), 0.0, 0.0)
    ko.location = (9.0, 5.5, 1.6)
    bpy.context.scene.camera = ko
    return ko


def himmel_ohne_scheibe():
    sky = villa_szene.himmel()
    villa_szene.sonnenstand(villa_szene.SONNE_HOEHE, villa_szene.SONNE_AZIMUT)
    sky.sun_disc = False
    return sky


def messen():
    """Hellster linearer Wert des Himmels. Klein gerendert, in EXR, damit
    die Zahl linear ist und nicht schon durch eine Kennlinie gelaufen."""
    sz = bpy.context.scene
    sz.render.resolution_x, sz.render.resolution_y = 256, 128
    sz.render.image_settings.file_format = 'OPEN_EXR'
    sz.render.image_settings.color_depth = '32'
    sz.view_settings.view_transform = 'Standard'
    sz.view_settings.exposure = 0.0
    probe = os.path.join(os.path.dirname(ZIEL), '_himmel-probe.exr')
    sz.render.filepath = probe
    bpy.ops.render.render(write_still=True)
    bild = bpy.data.images.load(probe)
    px = list(bild.pixels)
    hoch = 0.0
    for i in range(0, len(px), 4):
        for k in range(3):
            if px[i + k] > hoch:
                hoch = px[i + k]
    bpy.data.images.remove(bild)
    try:
        os.remove(probe)
    except OSError:
        pass
    return hoch


def boden():
    """Eine Rasenflaeche in die untere Halbkugel.

    Der erste Lauf lieferte ein Panorama, dessen untere Haelfte SCHWARZ
    war -- ein Himmel ohne Boden. Als Umgebungslicht heisst das: Von unten
    kommt nichts. Alle Untersichten (Auskragung, Sturz, Laibung, Dachrand)
    waeren im Browser tiefschwarz gewesen, waehrend sie im gerechneten
    Bild vom Rasen her aufgehellt sind. Jedes echte HDRI eines Ortes hat
    seinen Boden dabei, und aus genau diesem Grund.

    Albedo: Gras liegt bei rund 8 Prozent Reflexion und ist deutlich
    gruener als es aussieht -- das Auge rechnet die Farbe heraus, die
    Rechnung nicht. Die Wand bekommt unten dadurch einen leichten
    Gruenstich, und der gehoert dorthin.
    """
    me = bpy.data.meshes.new('Boden')
    ob = bpy.data.objects.new('Boden', me)
    bpy.context.collection.objects.link(ob)
    R = 4000.0
    H = -0.38          # RASEN_OBEN aus villa_garten.py
    me.from_pydata([(-R, -R, H), (R, -R, H), (R, R, H), (-R, R, H)],
                   [], [(0, 1, 2, 3)])
    me.update()
    mat = bpy.data.materials.new('Rasen_Umgebung')
    mat.use_nodes = True
    bsdf = mat.node_tree.nodes.get('Principled BSDF')
    if bsdf:
        bsdf.inputs['Base Color'].default_value = (0.055, 0.095, 0.035, 1.0)
        bsdf.inputs['Roughness'].default_value = 1.0
        if 'Specular IOR Level' in bsdf.inputs:
            bsdf.inputs['Specular IOR Level'].default_value = 0.2
    me.materials.append(mat)
    return ob


def haupt():
    sz = bpy.context.scene
    leere_szene()
    boden()
    kugelkamera()
    himmel_ohne_scheibe()

    sz.render.engine = 'CYCLES'
    sz.cycles.samples = 64          # nur Himmel, kein Rauschen zu erwarten
    sz.cycles.use_denoising = True
    sz.render.film_transparent = False

    hoch = messen()
    print('[himmel] hellster linearer Wert ohne Sonnenscheibe: %.4f' % hoch)

    # Belichtung so, dass die hellste Stelle knapp unter dem Anschlag liegt.
    # 0,95 statt 1,0 -- ein Panorama, das oben anstoesst, verliert genau da
    # Zeichnung, wo das meiste Licht herkommt.
    if hoch <= 0.0:
        belichtung = 0.0
    else:
        belichtung = math.log2(0.95 / hoch)
    print('[himmel] Belichtung: %+.4f EV' % belichtung)
    print('[himmel] Ruecknahme im Browser: Faktor %.5f' % (2.0 ** -belichtung))

    sz.view_settings.view_transform = 'Standard'   # keine Kennlinie, nur sRGB
    sz.view_settings.look = 'None'
    sz.view_settings.exposure = belichtung
    sz.render.resolution_x, sz.render.resolution_y = BREITE, HOEHE
    sz.render.resolution_percentage = 100
    sz.render.image_settings.file_format = 'PNG'
    sz.render.image_settings.color_mode = 'RGB'
    sz.render.image_settings.color_depth = '8'
    sz.render.filepath = ZIEL
    bpy.ops.render.render(write_still=True)
    print('[himmel] geschrieben: %s' % ZIEL)
    print('[himmel] FERTIG belichtung=%.4f faktor=%.5f' % (belichtung, 2.0 ** -belichtung))


haupt()
