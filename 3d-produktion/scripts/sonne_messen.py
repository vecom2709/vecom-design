# -*- coding: utf-8 -*-
"""WO STEHT DIE SONNE WIRKLICH? Gemessen am Schatten, nicht hergeleitet.

ANLASS, 20.09.2026
Der Vergleich Unreal gegen Cycles zeigt nicht nur andere Helligkeiten,
sondern andere SCHATTEN: In der Cycles-Vorlage liegt die vordere Fassade
im vollen Licht und die Zypressenschatten laufen nach rechts; im
Unreal-Bild liegt dieselbe Fassade im Schatten und die Schatten laufen
zum Betrachter. Daraus folgt: Die Sonne steht in Unreal woanders.

Solange die Richtung aus Konventionen hergeleitet wird (Blenders
`sun_rotation` gegen Unreals Yaw), streiten zwei Vermutungen. Deshalb
hier der eine Weg, der keine Konvention braucht: Ein senkrechter Stab
auf einer Ebene, Blick von oben mit bekannter Ausrichtung -- die
Richtung des Schattens IST die Antwort, und seine Laenge gibt die Hoehe.

Der Rest der Szene bleibt draussen: gemessen wird das Himmelsmodell mit
seinen Werten aus villa_szene, nichts sonst.
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

STAB_HOEHE = 10.0
FELD = 60.0        # Kantenlaenge des Blicks von oben, in Metern
N = 512            # Bildkante


def leeren():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)


def ebene():
    me = bpy.data.meshes.new('Ebene')
    ob = bpy.data.objects.new('Ebene', me)
    bpy.context.collection.objects.link(ob)
    r = FELD
    me.from_pydata([(-r, -r, 0), (r, -r, 0), (r, r, 0), (-r, r, 0)],
                   [], [(0, 1, 2, 3)])
    me.update()
    mat = bpy.data.materials.new('Weiss')
    mat.use_nodes = True
    b = mat.node_tree.nodes.get('Principled BSDF')
    if b:
        b.inputs['Base Color'].default_value = (0.8, 0.8, 0.8, 1.0)
        b.inputs['Roughness'].default_value = 1.0
    me.materials.append(mat)
    return ob


def stab():
    """Ein duenner senkrechter Zylinder im Ursprung."""
    bpy.ops.mesh.primitive_cylinder_add(radius=0.06, depth=STAB_HOEHE,
                                        location=(0.0, 0.0, STAB_HOEHE / 2.0))
    return bpy.context.active_object


def kamera_oben():
    """Orthografisch senkrecht nach unten, ohne jede Drehung.

    Damit gilt im Bild: nach RECHTS ist Welt +X, nach OBEN ist Welt +Y.
    Genau das macht die Messung konventionsfrei.
    """
    kd = bpy.data.cameras.new('Oben')
    kd.type = 'ORTHO'
    kd.ortho_scale = FELD
    ko = bpy.data.objects.new('Oben', kd)
    bpy.context.collection.objects.link(ko)
    ko.location = (0.0, 0.0, 120.0)
    ko.rotation_euler = (0.0, 0.0, 0.0)   # schaut entlang -Z
    bpy.context.scene.camera = ko
    return ko


def rendern(pfad):
    sz = bpy.context.scene
    sz.render.engine = 'CYCLES'
    sz.cycles.samples = 48
    sz.cycles.use_denoising = True
    sz.render.resolution_x = sz.render.resolution_y = N
    sz.render.resolution_percentage = 100
    sz.render.image_settings.file_format = 'OPEN_EXR'
    sz.render.image_settings.color_depth = '32'
    sz.render.image_settings.color_mode = 'RGB'
    sz.view_settings.view_transform = 'Raw'
    sz.view_settings.exposure = 0.0
    sz.render.filepath = pfad
    bpy.ops.render.render(write_still=True)
    bild = bpy.data.images.load(pfad)
    roh = np.empty(N * N * 4, dtype=np.float32)
    bild.pixels.foreach_get(roh)
    bpy.data.images.remove(bild)
    # Zeile 0 im Speicher ist UNTEN. Nicht umdrehen -- dann entspricht
    # ein wachsender Zeilenindex einem wachsenden Welt-Y.
    return roh.reshape(N, N, 4)[:, :, :3].astype(np.float64)


def haupt():
    leeren()
    ebene()
    stab()
    kamera_oben()
    villa_szene.himmel()
    villa_szene.sonnenstand(villa_szene.SONNE_HOEHE, villa_szene.SONNE_AZIMUT)

    pfad = os.path.join(AUS, '_sonnenprobe.exr')
    bild = rendern(pfad)
    hell = bild.mean(axis=2)

    # Pixel -> Weltkoordinaten. Spalte i deckt x, Zeile j deckt y.
    mitte = (N - 1) / 2.0
    schritt = FELD / N
    xs = (np.arange(N) - mitte) * schritt
    ys = (np.arange(N) - mitte) * schritt
    X, Y = np.meshgrid(xs, ys)

    # Der Stab selbst steht im Ursprung und ist auch dunkel -- er wird
    # ausgeblendet, sonst zoege er den Schwerpunkt zur Mitte.
    abstand = np.sqrt(X * X + Y * Y)
    frei = abstand > 0.5

    voll = np.median(hell[frei & (abstand > FELD * 0.4)])
    schatten = frei & (hell < voll * 0.55)
    anzahl = int(schatten.sum())
    if anzahl < 20:
        print('[sonne] KEIN SCHATTEN GEFUNDEN (%d Pixel) -- Abbruch' % anzahl)
        return

    sx = float(X[schatten].mean())
    sy = float(Y[schatten].mean())
    laenge = float(abstand[schatten].max())

    # Der Schatten zeigt VON der Sonne weg.
    zur_sonne = (-sx, -sy)
    betrag = math.hypot(*zur_sonne)
    ex, ey = zur_sonne[0] / betrag, zur_sonne[1] / betrag

    # Zwei gebraeuchliche Lesarten derselben Richtung, damit beim
    # Uebertragen nach Unreal nichts mehr zu raten ist.
    az_von_x = math.degrees(math.atan2(ey, ex)) % 360.0     # 0 = +X, gegen Uhrzeiger
    az_von_y = math.degrees(math.atan2(ex, ey)) % 360.0     # 0 = +Y, im Uhrzeigersinn
    hoehe = math.degrees(math.atan2(STAB_HOEHE, laenge))

    bericht = {
        'eingestellt_azimut': villa_szene.SONNE_AZIMUT,
        'eingestellt_hoehe': villa_szene.SONNE_HOEHE,
        'gemessen_richtung_zur_sonne_xy': [round(ex, 5), round(ey, 5)],
        'gemessen_azimut_ab_plusX_gegen_uhrzeiger': round(az_von_x, 2),
        'gemessen_azimut_ab_plusY_im_uhrzeigersinn': round(az_von_y, 2),
        'gemessen_hoehe_aus_schattenlaenge': round(hoehe, 2),
        'schattenpixel': anzahl,
        'schattenschwerpunkt_xy': [round(sx, 3), round(sy, 3)],
        'schattenlaenge_m': round(laenge, 3),
        'stabhoehe_m': STAB_HOEHE,
    }
    for k in sorted(bericht):
        print('[sonne] %-42s %s' % (k, bericht[k]))

    for ziel in (os.path.join(AUS, 'sonne-gemessen.json'),
                 os.path.join(WERK, 'sonne-gemessen.json')):
        with open(ziel, 'w', encoding='utf-8') as f:
            json.dump(bericht, f, indent=2, ensure_ascii=False)
    try:
        os.remove(pfad)
    except OSError:
        pass
    print('[sonne] FERTIG')


haupt()
