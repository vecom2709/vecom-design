# -*- coding: utf-8 -*-
"""Headless: die fotorealen Ruhebilder der Villa fuer die Erlebnis-Seite.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript lauf_ruhebilder.py -Log ruhe.log -Werte garten:nachmittag,wohnen:abend,proben=512

WOZU
Auf vecom-design.it dreht der Besucher die Villa in Echtzeit. Sobald die
Kamera auf einem Standpunkt zur Ruhe kommt, blendet die Seite auf das
GERECHNETE Bild derselben Ansicht und Tageszeit ueber -- Echtzeit zum
Anfassen, Pfadverfolgung zum Anschauen. Die Kamerastandpunkte sind
dieselben wie in villa_szene.KAMERAS und in haus-manifest.json, deshalb
passen beide Bilder deckungsgleich aufeinander.

AUFBAU WIE DIE VORLAGE
Die Szene entsteht mit villa_exr_gleich.haupt() -- also genau so wie die
Vergleichsvorlage fuer Unreal (Gras, Veredelung, echtes Glas, Kaustiken,
keine Kappung). haupt() wird dafuer VOR dem Bildausgang angehalten; der
Rest (Belichtung, Sonnenstand, Ausgabe) steht hier. Obendrauf kommt
villa_fotoreal (Land, Ferne, Zypressenlaub) -- nur fuer Standbilder.

BELICHTUNG IST GEMESSEN
Nachmittag nimmt die kalibrierte Belichtung je Kamera aus villa_szene
(Sollwerte fuer Putz, Rasen, Decke). Jede andere Tageszeit wird daran
gemessen: ein kleines Vorbild mit 16 Proben, das 75-Prozent-Perzentil der
Anzeigeluminanz, und die Belichtung so, dass es zum Nachmittag im
gewuenschten Verhaeltnis steht (Abend etwas dunkler, blaue Stunde deutlich).
Kein Regler nach Augenmass.
"""
import bpy
import json
import math
import os
import sys
import time
import traceback

HIER = os.path.dirname(os.path.abspath(__file__))
if HIER not in sys.path:
    sys.path.append(HIER)
RENDER = os.path.join(os.path.dirname(HIER), 'render', 'ruhe')

# Hoehe, Azimut (Blender-Konvention wie villa_szene), Leuchtenstaerke,
# Helligkeit gegenueber dem Nachmittag (75-%-Perzentil).
ZEITEN = {
    'morgen':     (12.0, 100.0, 14.0, 0.95),
    'mittag':     (60.0, 185.0, 14.0, 1.00),
    'nachmittag': (27.0, 249.0, 14.0, 1.00),
    'abend':      (4.5, 282.0, 45.0, 0.70),
    'nacht':      (-4.0, 300.0, 70.0, 0.30),
}
STANDPUNKTE = ['garten', 'ankunft', 'terrasse', 'wohnen', 'kueche']
INNEN = ('wohnen', 'kueche', 'essen', 'master')
INNEN_ZIEL = 0.60
# Innen traegt am Abend und in der blauen Stunde das Kunstlicht den Raum.
# Mit den Aussenverhaeltnissen (0,70 / 0,30) versank der Wohnraum im
# Testbild in Orange und Schwarz -- ein Fotograf belichtet dann auf den
# beleuchteten Raum, nicht auf den Himmel vor dem Fenster.
INNEN_VERH = {'morgen': 0.95, 'mittag': 1.0, 'abend': 0.85, 'nacht': 0.66}
# Weissabgleich innen bei Kunstlicht: 4300 K wie eine Kamera im Mischlicht.
# Die Leuchten (2800 K) bleiben dann warm, aber der Raum nicht orange.
INNEN_WEISS = {'abend': 4600.0, 'nacht': 4300.0}
REIHENFOLGE = ['nachmittag', 'abend', 'nacht', 'morgen', 'mittag']


def lade(name):
    p = os.path.join(HIER, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def vorlage_bauen(breite, hoehe, proben):
    """villa_exr_gleich laden, haupt() vor dem Bildausgang anhalten."""
    p = os.path.join(HIER, 'villa_exr_gleich.py')
    q = open(p, encoding='utf-8').read()
    halt = "    sz.render.image_settings.file_format = 'OPEN_EXR'"
    if halt not in q:
        raise RuntimeError('Haltepunkt in villa_exr_gleich.py nicht gefunden')
    q = q.replace(halt, '    return kams\n' + halt, 1)
    q = q.rstrip()
    if q.endswith('haupt()'):
        q = q[:-len('haupt()')]
    ns = {'__name__': 'villa_exr_gleich_modul', '__file__': p}
    exec(compile(q, p, 'exec'), ns)
    ns['BREITE'], ns['HOEHE'], ns['PROBEN'] = breite, hoehe, proben
    return ns['haupt']()


def leuchten(staerke, kelvin=2800.0):
    """Leuchtenschirme auf Staerke und Farbtemperatur.

    2800 K statt der 3500 K aus villa_cycles: Am Abend-Testbild wirkte
    der Innenraum gegen den blauen Himmel neutral grau -- AgX nimmt hellen
    Flaechen Saettigung, und 3500 K kommen hinter weissen Waenden als Weiss
    an. Wohnraum-LEDs liegen bei 2700 bis 3000 K; genau der warme Kern
    gegen das kalte Aussenlicht ist das, was ein Abendfoto traegt."""
    mat = bpy.data.materials.get('Leuchtenschirm')
    if not mat:
        return
    nt = mat.node_tree
    for k in nt.nodes:
        if k.type != 'EMISSION':
            continue
        k.inputs['Strength'].default_value = staerke
        farbe = k.inputs['Color']
        bb = farbe.links[0].from_node if farbe.is_linked else None
        if bb is None or bb.type != 'BLACKBODY':
            bb = nt.nodes.new('ShaderNodeBlackbody')
            nt.links.new(bb.outputs[0], farbe)
        bb.inputs['Temperature'].default_value = kelvin


def weiss_setzen(kelvin):
    """Weissabgleich der Ansicht (Blender 4.3+). None = aus (6500 K)."""
    vs = bpy.context.scene.view_settings
    try:
        vs.use_white_balance = kelvin is not None
        if kelvin is not None:
            vs.white_balance_temperature = kelvin
            vs.white_balance_tint = 0.0
    except AttributeError:
        pass


def perzentil_messen(pfad_tmp, anteil=0.75):
    """Helligkeits-Perzentil ohne Himmel.

    22.09.2026: Der erste Lauf mass ueber das ganze Bild. Am Abend ist
    der Himmel noch hell und fuellt ein Drittel des Gartenblicks -- das
    p75 blieb deshalb beim Nachmittagswert, die Belichtung auch (-3,11
    statt -3,10), und Rasen und Fassade versanken schwarz. Gemessen wird
    jetzt nur, was die Kamera wirklich trifft: Film transparent, und nur
    Bildpunkte mit Deckung zaehlen. So belichtet man auch am Set --
    auf die Fassade, nicht auf den Himmel."""
    sz = bpy.context.scene
    im = sz.render.image_settings
    alt = (sz.render.resolution_percentage, sz.cycles.samples,
           sz.cycles.use_adaptive_sampling, sz.render.film_transparent,
           im.color_mode)
    sz.render.resolution_percentage = 25
    sz.cycles.samples = 16
    sz.cycles.use_adaptive_sampling = False
    sz.render.film_transparent = True
    im.color_mode = 'RGBA'
    sz.render.filepath = pfad_tmp
    bpy.ops.render.render(write_still=True)
    (sz.render.resolution_percentage, sz.cycles.samples,
     sz.cycles.use_adaptive_sampling, sz.render.film_transparent,
     im.color_mode) = alt
    bild = bpy.data.images.load(pfad_tmp, check_existing=False)
    px = list(bild.pixels)
    bpy.data.images.remove(bild)
    lum = sorted(0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2]
                 for i in range(0, len(px), 4) if px[i + 3] > 0.9)
    if len(lum) < 50:
        return 0.0
    return lum[int(len(lum) * anteil)]


def main():
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    teile = []
    for a in argv:
        teile += [t for t in a.split(',') if t]
    opt = dict(t.split('=', 1) for t in teile if '=' in t)
    paare = [t for t in teile if ':' in t and '=' not in t]
    if not paare or 'alle' in teile:
        paare = ['%s:%s' % (s, z) for z in REIHENFOLGE for s in STANDPUNKTE]
    breite = int(opt.get('breite', 1600))
    hoehe = int(opt.get('hoehe', 900))
    proben = int(opt.get('proben', 512))
    ohne = opt.get('ohne', '')
    # ordner=probe: Probebilder landen daneben, nicht zwischen den fertigen
    RENDER = os.path.join(os.path.dirname(HIER), 'render', opt.get('ordner', 'ruhe'))
    os.makedirs(RENDER, exist_ok=True)
    t0 = time.time()
    kams = vorlage_bauen(breite, hoehe, proben)
    print('[ruhe] Aufbau fertig in %.0f s, Kameras: %s' % (time.time() - t0, sorted(kams)))
    fr = lade('villa_fotoreal.py')
    wahl = tuple(t for t in ('land', 'mauer', 'ferne', 'zypressen', 'kies', 'polster') if t not in ohne.split('+'))
    print('[ruhe] Fotoreal-Schicht: %s' % (fr['anwenden'](wahl),))
    szene = lade('villa_szene.py')
    sz = bpy.context.scene
    sz.view_settings.view_transform = 'AgX'
    try:
        sz.view_settings.look = 'AgX - High Contrast'
    except TypeError:
        sz.view_settings.look = 'None'
    sz.view_settings.gamma = 1.0
    im = sz.render.image_settings
    if hasattr(im, 'media_type'):
        try:
            im.media_type = 'IMAGE'
        except Exception:
            pass
    im.file_format = 'PNG'
    im.color_mode = 'RGB'
    im.color_depth = '8'
    sz.render.resolution_x, sz.render.resolution_y = breite, hoehe
    sz.render.resolution_percentage = 100
    sz.cycles.samples = proben
    tmp = os.path.join(RENDER, '_mess.png')
    ref = {}
    basis_von = {}
    status_pfad = os.path.join(RENDER, 'status.json')
    status = json.load(open(status_pfad)) if os.path.isfile(status_pfad) else {}
    for paar in paare:
        stand, zeit = paar.split(':')
        if stand not in kams or zeit not in ZEITEN:
            print('[ruhe] uebersprungen: %s' % paar)
            continue
        h, az, lampe, verh = ZEITEN[zeit]
        sz.camera = kams[stand]
        if stand not in basis_von:
            szene['sonnenstand'](*ZEITEN['nachmittag'][:2])
            leuchten(ZEITEN['nachmittag'][2])
            weiss_setzen(None)
            b = float(szene['KAMERAS'][stand]['bel'])
            if stand in INNEN:
                # 22.09.2026: Die kalibrierten Innenwerte (wohnen 4,00) stammen
                # aus der Szene vom 19.09. -- ohne echtes Glas. In dieser
                # Vorlage laesst das Glas mit Schattenstrahl-Durchlass und
                # Kaustik ein Vielfaches an Licht herein; das erste Wohnraumbild
                # war reines Weiss. Innen wird deshalb hier gemessen: Median
                # der Anzeigehelligkeit im Raum auf 0,45 (Decke 0,55 bis 0,72,
                # Boden 0,28 bis 0,45 -- die Sollwerte aus villa_szene).
                # Ziel 0,60 statt 0,45: Der erste Messlauf blieb nach fuenf
                # Schritten bei 0,675 haengen, und genau dieses Bild sah aus
                # wie ein Immobilienfoto -- hell, luftig, Fenster knapp
                # ausgebrannt. Verstaerkung 1,8, weil AgX die Anzeigewerte
                # stark staucht; mit 1,25 kroch die Regelung.
                for _ in range(8):
                    sz.view_settings.exposure = b
                    m = max(1e-4, perzentil_messen(tmp, 0.5))
                    schritt = math.log2(INNEN_ZIEL / m) * 1.8
                    b = max(b - 9.0, min(b + 3.0, b + schritt))
                    print('[ruhe]   %s: Median %.3f (Ziel %.2f) -> %.2f EV' % (stand, m, INNEN_ZIEL, b))
                    if abs(schritt) < 0.08:
                        break
            basis_von[stand] = b
            sz.view_settings.exposure = b
            ref[stand] = perzentil_messen(tmp)
            print('[ruhe] %s: Bezug Nachmittag p75 = %.3f bei %.2f EV' % (stand, ref[stand], b))
        basis = basis_von[stand]
        szene['sonnenstand'](h, az)
        leuchten(lampe)
        bel = basis
        weiss_setzen(INNEN_WEISS.get(zeit) if stand in INNEN else None)
        if stand in INNEN:
            verh = INNEN_VERH.get(zeit, verh)
        if zeit != 'nachmittag':
            ziel = ref[stand] * verh
            for _ in range(3):
                sz.view_settings.exposure = bel
                wert = max(1e-4, perzentil_messen(tmp))
                schritt = math.log2(ziel / wert) * 1.25
                bel = max(basis - 4.0, min(basis + 7.0, bel + schritt))
                print('[ruhe]   %s/%s: p75 %.3f (Ziel %.3f) -> %.2f EV' % (stand, zeit, wert, ziel, bel))
                if abs(schritt) < 0.08:
                    break
        sz.view_settings.exposure = bel
        ziel_pfad = os.path.join(RENDER, 'ruhe-%s-%s.png' % (stand, zeit))
        sz.render.filepath = ziel_pfad
        t = time.time()
        bpy.ops.render.render(write_still=True)
        dauer = time.time() - t
        print('[ruhe] %s/%s -> %s  (%.0f s, %.2f EV)' % (stand, zeit, ziel_pfad, dauer, bel))
        status['%s:%s' % (stand, zeit)] = {'datei': os.path.basename(ziel_pfad), 'sekunden': round(dauer), 'belichtung': round(bel, 2), 'proben': proben}
        json.dump(status, open(status_pfad, 'w'), indent=1)
    if os.path.isfile(tmp):
        os.remove(tmp)
    print('[ruhe] FERTIG nach %.0f s' % (time.time() - t0))


try:
    main()
except Exception:
    print('[ruhe] FEHLER')
    traceback.print_exc()
