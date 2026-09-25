"""fahrzeug_bau.py -- zwei eigene Serienfahrzeuge fuer die Automotive-Demo.

Aufruf (headless, Windows):
  werkzeug\\blender-lauf.ps1 -Skript fahrzeug_bau.py -Log bau.log -Werte kleinwagen

Ergebnis: branchen\\quelle\\<was>.glb (mit drei Lackvarianten ueber
KHR_materials_variants) und branchen\\<was>-bau.blend. Das Studio
(branchen_studio.py) liest die GLB genau wie beim Konzeptauto.

WARUM EIGENE ENTWUERFE
Uwe will drei Autos zum Umschalten: Kleinwagen, Mittelklasse, Sportwagen.
Echte Modelle (Polo, 3er, 911 ...) nachzubauen hiesse, fremdes Design auf
einer gewerblichen Seite zu zeigen -- Formen von Serienautos sind als Design
geschuetzt, und die Seite verkauft genau das: Gestaltung. Deshalb eigene
Entwuerfe mit den MASSEN und der BAUWEISE ihrer Klasse (Quellen in
fz_karosserie.py). Der Sportwagen bleibt das Konzeptauto (Khronos, CC BY 4.0).

AUFBAU
fz_karosserie: Karosserie als Parameterflaeche, Konturen eingerastet
fz_raeder:     Reifen (ETRTO-Masse), Felgenstern, Bremse
hier:          Blender-Objekte, Materialien, Falze, Innenraum, Export
"""
import bpy, json, math, os, struct, sys, time
import numpy as np

HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HIER)
import fz_geom as FG
import fz_karosserie as FK
import fz_raeder as FR
import fz_anbau as FA
import fz_details as FD
import fz_technik as FT
import fz_innen as FI
import fz_tueren as FTU

P = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
WAS = argv[0] if argv else 'kleinwagen'
# 'web': Echtzeitfassung -- Karosserie mit 55 % der Netzdichte, Raeder mit
# halber Segmentzahl. Konturen bleiben scharf, weil sie eingerastet werden,
# nicht aus der Dichte kommen.
WEB = 'web' in argv[1:]
FK.aufloesung(0.55 if WEB else 1.0)
FR.SEGMENTE = 0.5 if WEB else 1.0
FI.skala(0.5 if WEB else 1.0)
t_start = time.time()


def log(*a):
    print(f'[{time.time() - t_start:6.1f}s]', *a, flush=True)


bpy.ops.wm.read_factory_settings(use_empty=True)
sc = bpy.context.scene

# ================================================================= Materialien
STOFF = {}


def principled(name, farbe, rau, metall=0.0, coat=0.0, coat_rau=0.03, trans=0.0, ior=1.5, emiss=None, spec=0.5):
    m = bpy.data.materials.new(name); m.use_nodes = True
    b = m.node_tree.nodes.get('Principled BSDF')
    b.inputs['Base Color'].default_value = (*farbe, 1)
    b.inputs['Roughness'].default_value = rau
    b.inputs['Metallic'].default_value = metall
    b.inputs['Coat Weight'].default_value = coat
    b.inputs['Coat Roughness'].default_value = coat_rau
    b.inputs['Transmission Weight'].default_value = trans
    b.inputs['IOR'].default_value = ior
    b.inputs['Specular IOR Level'].default_value = spec
    if emiss:
        b.inputs['Emission Color'].default_value = (*emiss[0], 1)
        b.inputs['Emission Strength'].default_value = emiss[1]
    return m


# Lacke, Farbwerte linear, Klarlack mit Rauheit 0,03 wie ein polierter
# Serienlack. Erster Versuch mit Metallic 0,6: Der nichtmetallische Anteil streute das
# Deckenlicht diffus, die Flanke wurde flaechig hellblau wie ein Kreidemodell.
# Echter Metallic-Lack verhaelt sich optisch fast wie Metall unter Klarlack:
# dunkel, wo er dunkle Umgebung spiegelt, farbig in den Lichtern -- so ist
# auch das Konzeptauto (Khronos) angelegt (metallic 1, Klarlack 1).
LACK = {
    'kleinwagen': [('Paint Azzurro', (0.040, 0.200, 0.520), 1.00, 0.32),
                   ('Paint Bianco', (0.80, 0.80, 0.78), 0.00, 0.28),
                   ('Paint Salvia', (0.190, 0.260, 0.215), 1.00, 0.34)],
    'mittelklasse': [('Paint Blu Notte', (0.018, 0.034, 0.100), 1.00, 0.30),
                     ('Paint Argento', (0.600, 0.610, 0.620), 1.00, 0.32),
                     ('Paint Rosso', (0.520, 0.018, 0.022), 1.00, 0.28)],
}[WAS]
lack_name, lack_farbe, lack_met, lack_rau = LACK[0]
STOFF['lack'] = principled(lack_name, lack_farbe, lack_rau, lack_met, coat=1.0)
STOFF['unterboden'] = principled('Underbody', (0.012, 0.012, 0.012), 0.85)
# Seiten- und Heckscheiben leicht getoent (Serien-Waermeschutzglas), die
# Frontscheibe fast klar (70 % Lichtdurchlass vorgeschrieben).
STOFF['glas'] = principled('Glass', (0.50, 0.53, 0.54), 0.0, trans=1.0, ior=1.52)
STOFF['frontglas'] = principled('Glass Clear', (0.80, 0.83, 0.82), 0.0, trans=1.0, ior=1.52)
STOFF['himmel'] = principled('Headliner', (0.30, 0.29, 0.27), 0.9)
STOFF['spiegelglas'] = principled('Mirror Glass', (0.90, 0.90, 0.90), 0.02, metall=1.0)
STOFF['zier'] = principled('Trim Satin', (0.62, 0.63, 0.64), 0.22, metall=1.0)
STOFF['leder'] = principled('Leather Dark', (0.025, 0.024, 0.024), 0.55)
STOFF['rahmen'] = principled('Trim Gloss Black', (0.006, 0.006, 0.007), 0.08, coat=0.6)
# Genarbter Kunststoff (Spiegelfuss, Wasserkasten, Schwellerleiste): Rauheit
# 0,78. Mit 0,62 spiegelte der Wasserkasten den Deckendiffusor cremeweiss
# (Nahprobe A-Saeule, 23.09.2026) -- echter Narbkunststoff streut das weg.
STOFF['kunststoff'] = principled('Plastic Black', (0.015, 0.015, 0.016), 0.78, spec=0.4)
# EPDM-Dichtung: Fensterrahmen Kleinwagen, Grund der Tuerfugen
STOFF['dichtung'] = principled('Rubber Seal', (0.010, 0.010, 0.011), 0.62, spec=0.4)
# Keramikfritte am Scheibenrand: schwarz eingebrannt, unter Glas -- glaenzt
# wie die Scheibe, laesst aber kein Licht durch.
# Fritte = schwarze Keramik HINTER der Glasoberflaeche: Sie spiegelt genau so
# viel wie die Scheibe selbst (eine Glasflaeche, ~4 %), nicht mehr. Mit
# Klarlack obendrauf spiegelte sie doppelt, und ihre dreieckige Kante stand
# an der A-Saeule als weisser, gezackter Keil im Bild (Strahlmessung
# 23.09.2026: erste Flaeche 'Glass Frit', Spiegelung Richtung Deckenlicht).
STOFF['fritte'] = principled('Glass Frit', (0.004, 0.004, 0.005), 0.0, spec=0.5, ior=1.52)
STOFF['grill'] = principled('Grille', (0.010, 0.010, 0.011), 0.28)
STOFF['rueckstrahler'] = principled('Reflector Red', (0.35, 0.01, 0.01), 0.15, coat=0.5)
STOFF['linse'] = principled('Lamp Lens', (0.95, 0.95, 0.95), 0.0, trans=1.0, ior=1.49)
STOFF['linse_rot'] = principled('Lamp Lens Red', (0.42, 0.012, 0.014), 0.02, trans=1.0, ior=1.49)
STOFF['lampengehaeuse'] = principled('Lamp Housing', (0.020, 0.020, 0.022), 0.30)
STOFF['grill_grund'] = principled('Grille Back', (0.006, 0.006, 0.006), 0.7)
STOFF['reflektor'] = principled('Lamp Reflector', (0.80, 0.80, 0.80), 0.12, metall=1.0)
STOFF['tagfahrlicht'] = principled('Lamp DRL', (1, 1, 1), 0.3, emiss=((1.0, 0.97, 0.92), 6.0))
STOFF['rueck_leucht'] = principled('Lamp Tail', (0.3, 0.0, 0.0), 0.4, emiss=((1.0, 0.02, 0.01), 3.0))
STOFF['innen'] = principled('Interior Dark', (0.022, 0.022, 0.024), 0.75)
STOFF['sitz'] = principled('Seat Fabric', (0.030, 0.030, 0.033), 0.85)
# Gummi: matt. Mit Rauheit 0,6 spiegelte die Flanke den Deckendiffusor
# grau wie Kunststoff (Nahaufnahme Rad, 23.09.2026).
STOFF['reifen'] = principled('Tiretread', (0.016, 0.016, 0.016), 0.90, spec=0.3)
STOFF['flanke'] = principled('Tire Sidewall', (0.018, 0.018, 0.018), 0.82, spec=0.35)
STOFF['felge'] = principled('Rim', (0.55, 0.56, 0.57) if WAS == 'mittelklasse' else (0.16, 0.165, 0.17), 0.26, metall=1.0, coat=0.5)
STOFF['felgenbett'] = principled('Rim Barrel', (0.30, 0.30, 0.31), 0.45, metall=1.0)
STOFF['scheibe'] = principled('Brake Disc', (0.42, 0.42, 0.42), 0.38, metall=1.0)
STOFF['sattel'] = principled('Brake Caliper', (0.05, 0.05, 0.055), 0.4)
STOFF['schraube'] = principled('Wheel Bolt', (0.7, 0.7, 0.72), 0.2, metall=1.0)
STOFF['motor'] = principled('Cast Aluminium', (0.52, 0.53, 0.54), 0.42, metall=1.0)
STOFF['stahl'] = principled('Steel Dark', (0.14, 0.14, 0.15), 0.50, metall=1.0)
STOFF['gummi'] = principled('Rubber', (0.02, 0.02, 0.02), 0.80)
STOFF['feder'] = principled('Spring Paint', (0.05, 0.06, 0.08), 0.35, coat=0.5)
STOFF['guss'] = principled('Cast Iron', (0.10, 0.10, 0.105), 0.62, metall=1.0)
STOFF['hitzeschild'] = principled('Heat Shield', (0.70, 0.70, 0.71), 0.38, metall=1.0)
STOFF['kuehler'] = principled('Radiator Core', (0.035, 0.035, 0.038), 0.55, metall=1.0)

# Kennzeichen: Haendlerschild wie beim Konzeptauto
kz = principled('Plate', (1, 1, 1), 0.35)
tex = kz.node_tree.nodes.new('ShaderNodeTexImage')
tex.image = bpy.data.images.load(os.path.join(P, 'quelle', 'tex', 'neu-kennzeichen.png'))
kz.node_tree.links.new(tex.outputs['Color'], kz.node_tree.nodes['Principled BSDF'].inputs['Base Color'])
STOFF['kennzeichen'] = kz

# ================================================================= Innenraum-Stoffe
# Texturen aus fz_texturen.py (kachelbar, Massstab je Kachel in Metern).
# UV der Innenraumteile ist in Metern, der Mapping-Knoten skaliert auf die
# Kachel -- der glTF-Exporter schreibt ihn als KHR_texture_transform.
TEX = os.path.join(P, 'quelle', 'tex')
KACHEL = {'stoff': 0.05, 'leder': 0.08, 'narbung': 0.06, 'lochung': 0.04, 'teppich': 0.10,
          'walnuss': 0.40, 'esche': 0.40, 'alu': 0.20, 'lautsprecher': 0.06, 'lederloch': 0.08}
_bilder = {}


def bild(name, farbe=False):
    if name not in _bilder:
        im = bpy.data.images.load(os.path.join(TEX, name + '.png'))
        im.colorspace_settings.name = 'sRGB' if farbe else 'Non-Color'
        _bilder[name] = im
    return _bilder[name]


def stoff_tex(name, farbe, rau, art=None, normal=0.6, metall=0.0, coat=0.0, coat_rau=0.05, farbkarte=False,
              raukarte=False, spec=0.5, sheen=0.0, lochung=False, emiss=None):
    """Principled mit Kachel-Texturen der Art `art` (stoff, leder, narbung,
    teppich, walnuss, esche, alu, lautsprecher)."""
    m = principled(name, farbe, rau, metall, coat=coat, coat_rau=coat_rau, spec=spec, emiss=emiss)
    if art is None:
        return m
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    koord = nt.nodes.new('ShaderNodeTexCoord'); abb = nt.nodes.new('ShaderNodeMapping')
    k = KACHEL[art]
    abb.inputs['Scale'].default_value = (1 / k, 1 / k, 1)
    nt.links.new(koord.outputs['UV'], abb.inputs['Vector'])
    def tex(n, farbig=False):
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = bild(n, farbig)
        nt.links.new(abb.outputs['Vector'], t.inputs['Vector'])
        return t
    tn = tex(f'innen-{art}-normal'); nm = nt.nodes.new('ShaderNodeNormalMap'); nm.inputs['Strength'].default_value = normal
    nt.links.new(tn.outputs['Color'], nm.inputs['Color']); nt.links.new(nm.outputs['Normal'], b.inputs['Normal'])
    if farbkarte:
        tf = tex(f'innen-{art}-farbe', art in ('walnuss', 'esche'))
        if art in ('walnuss', 'esche'):
            nt.links.new(tf.outputs['Color'], b.inputs['Base Color'])
        else:
            mix = nt.nodes.new('ShaderNodeMix'); mix.data_type = 'RGBA'; mix.blend_type = 'MULTIPLY'
            mix.inputs['Factor'].default_value = 1.0
            mix.inputs[7].default_value = (*farbe, 1)
            nt.links.new(tf.outputs['Color'], mix.inputs[6]); nt.links.new(mix.outputs[2], b.inputs['Base Color'])
    if raukarte:
        tr = tex(f'innen-{art}-rau')
        nt.links.new(tr.outputs['Color'], b.inputs['Roughness'])
    if sheen:
        b.inputs['Sheen Weight'].default_value = sheen
    return m



def flankenschrift(m, name, staerke=1.0):
    """Normalkarte der Reifenflanke (Schrift, Rippen) ueber die UV aus
    reifen_uv(). Ohne sie ist die Flanke ein glattes schwarzes Band -- das
    verraet ein CGI-Rad auf den ersten Blick."""
    if not os.path.exists(os.path.join(TEX, name + '.png')):
        log('  (keine', name, '-- Flanke ohne Schrift)'); return
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    t = nt.nodes.new('ShaderNodeTexImage'); t.image = bild(name)
    nm = nt.nodes.new('ShaderNodeNormalMap'); nm.inputs['Strength'].default_value = staerke
    nt.links.new(t.outputs['Color'], nm.inputs['Color']); nt.links.new(nm.outputs['Normal'], b.inputs['Normal'])


flankenschrift(STOFF['flanke'], f'reifen-{WAS}-normal')


def innen_grundstoffe():
    S = {}
    S['innen_oben'] = stoff_tex('Dash Soft', (0.030, 0.030, 0.032), 0.62, 'narbung', 0.35, raukarte=True, spec=0.4)
    S['innen_unten'] = stoff_tex('Dash Lower', (0.024, 0.024, 0.026), 0.66, 'narbung', 0.45, spec=0.4)
    S['innen_hart'] = stoff_tex('Trim Hard', (0.020, 0.020, 0.022), 0.55, 'narbung', 0.5, spec=0.45)
    S['klavierlack'] = principled('Piano Black', (0.004, 0.004, 0.005), 0.06, coat=1.0, coat_rau=0.01)
    S['chrom_innen'] = principled('Satin Chrome', (0.78, 0.78, 0.80), 0.16, metall=1.0)
    S['stahl_innen'] = principled('Seat Steel', (0.10, 0.10, 0.11), 0.45, metall=1.0)
    S['teppich'] = stoff_tex('Carpet', (0.028, 0.028, 0.030), 0.95, 'teppich', 0.8, farbkarte=True, spec=0.3, sheen=0.4)
    S['matte'] = stoff_tex('Floor Mat', (0.022, 0.022, 0.024), 0.92, 'teppich', 1.0, farbkarte=True, spec=0.3, sheen=0.5)
    S['himmel'] = stoff_tex('Headliner', (0.30, 0.295, 0.28), 0.92, 'stoff', 0.35, spec=0.3, sheen=0.3)
    S['saeule'] = stoff_tex('Pillar Trim', (0.085, 0.084, 0.082), 0.8, 'narbung', 0.3, spec=0.35)
    S['lenkrad'] = stoff_tex('Wheel Leather', (0.028, 0.027, 0.027), 0.45, 'leder', 0.7, raukarte=True)
    S['gurt'] = stoff_tex('Seat Belt', (0.018, 0.018, 0.019), 0.78, 'stoff', 0.9, spec=0.35, sheen=0.3)
    S['lautsprecher'] = stoff_tex('Speaker Grille', (0.05, 0.05, 0.055), 0.4, 'lautsprecher', 1.0, metall=1.0, farbkarte=True)
    S['display_glas'] = principled('Display Glass', (0.002, 0.002, 0.003), 0.02, coat=1.0, coat_rau=0.0)
    S['spiegelglas_innen'] = principled('Mirror Glass Inner', (0.85, 0.85, 0.86), 0.02, metall=1.0)
    S['rueck_innen'] = principled('Hazard Switch', (0.40, 0.012, 0.012), 0.3, coat=0.8)
    # Displays: Bild als Leuchtdichte. Staerke 3 bei Belichtung -2: das Weiss
    # der Schrift liegt knapp unter dem Weisspunkt, die dunkle Flaeche bleibt
    # dunkel -- ein Bildschirm im Studio, kein Leuchtkasten.
    for key in ('kombi', 'mitte', 'breit'):
        pfad = os.path.join(TEX, f'display-{key}.png')
        if not os.path.exists(pfad):
            continue
        # Entspiegeltes Deckglas: Serienbildschirme haben eine Antireflex-
        # Schicht (~1 % statt 4 % Reflexion) mit leichter Mattierung. Mit
        # Klarlack 1,0 / 0,01 spiegelten sich am 23.09.2026 Himmel und Sitz als
        # harte weisse Flaechen ueber die Karte (Innenraumfoto Kleinwagen).
        m = principled(f'Display {key}', (0.0, 0.0, 0.0), 0.3, spec=0.3, coat=0.3, coat_rau=0.09)
        nt = m.node_tree; b = nt.nodes['Principled BSDF']
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = bpy.data.images.load(pfad)
        nt.links.new(t.outputs['Color'], b.inputs['Emission Color'])
        b.inputs['Emission Strength'].default_value = 3.0
        S['display_' + key] = m
    return S


# Drei Ausstattungen je Auto. Werte linear; Leder nach gemessenen Albedos
# (Cognac ~0,12-0,30, Elfenbein ~0,55-0,62), Stoffe dunkel mit Melange.
AUSSTATTUNG = {
    'kleinwagen': [
        ('Stoff Anthrazit', dict(sitz_haupt=('stoff', (0.030, 0.030, 0.033), 0.95), sitz_einlage=('stoff', (0.050, 0.051, 0.055), 0.95),
                                 naht=(0.09, 0.09, 0.095), dekor=('klavierlack',), keder=(0.03, 0.03, 0.033))),
        ('Stoff Grau-Blau', dict(sitz_haupt=('leder', (0.026, 0.028, 0.034), 0.5), sitz_einlage=('stoff', (0.060, 0.078, 0.115), 0.95),
                                 naht=(0.10, 0.22, 0.52), dekor=('lack', (0.035, 0.070, 0.115), 0.3), keder=(0.10, 0.22, 0.52))),
        ('Kunstleder Hell', dict(sitz_haupt=('leder', (0.40, 0.40, 0.39), 0.48), sitz_einlage=('leder_loch', (0.37, 0.37, 0.36), 0.5),
                                 naht=(0.045, 0.045, 0.05), dekor=('alu',), keder=(0.03, 0.03, 0.033))),
    ],
    'mittelklasse': [
        ('Stoff Anthrazit', dict(sitz_haupt=('leder', (0.027, 0.027, 0.029), 0.5), sitz_einlage=('stoff', (0.040, 0.040, 0.044), 0.95),
                                 naht=(0.11, 0.11, 0.115), dekor=('alu',), keder=(0.03, 0.03, 0.033))),
        ('Leder Cognac', dict(sitz_haupt=('leder', (0.30, 0.115, 0.045), 0.42), sitz_einlage=('leder_loch', (0.27, 0.10, 0.04), 0.45),
                              naht=(0.52, 0.47, 0.38), dekor=('walnuss',), keder=(0.12, 0.045, 0.02))),
        ('Leder Elfenbein', dict(sitz_haupt=('leder', (0.62, 0.58, 0.49), 0.42), sitz_einlage=('leder_loch', (0.58, 0.54, 0.46), 0.45),
                                 naht=(0.06, 0.06, 0.065), dekor=('esche',), keder=(0.06, 0.06, 0.065))),
    ],
}[WAS]


def ausstattung_stoffe(i):
    """Die variablen Stoffe einer Ausstattung: sitz_haupt, sitz_einlage,
    naht, dekor, keder (Tuerverkleidung und Armlehnen nehmen sitz_haupt/
    sitz_einlage mit)."""
    name, A = AUSSTATTUNG[i]
    S = {}
    for k in ('sitz_haupt', 'sitz_einlage'):
        art, farbe, rau = A[k]
        if art == 'stoff':
            S[k] = stoff_tex(f'{name} {k}', farbe, rau, 'stoff', 0.9, farbkarte=True, spec=0.35, sheen=0.5)
        elif art == 'leder':
            S[k] = stoff_tex(f'{name} {k}', farbe, rau, 'leder', 0.65, raukarte=True)
        else:   # gelochtes Leder: Narbung plus Lochbild
            S[k] = stoff_tex(f'{name} {k}', farbe, rau, 'lederloch', 0.8, farbkarte=True, raukarte=True)
    S['naht'] = principled(f'{name} naht', A['naht'], 0.6)
    S['keder'] = stoff_tex(f'{name} keder', A['keder'], 0.5, 'leder', 0.5)
    d = A['dekor']
    if d[0] == 'klavierlack':
        S['dekor'] = principled(f'{name} dekor', (0.004, 0.004, 0.005), 0.06, coat=1.0, coat_rau=0.01)
    elif d[0] == 'lack':
        S['dekor'] = principled(f'{name} dekor', d[1], d[2], coat=0.8, coat_rau=0.02)
    elif d[0] == 'alu':
        S['dekor'] = stoff_tex(f'{name} dekor', (0.62, 0.62, 0.64), 0.28, 'alu', 0.25, metall=1.0, raukarte=True)
    else:
        S['dekor'] = stoff_tex(f'{name} dekor', (0.2, 0.1, 0.05), 0.35, d[0], 0.5, farbkarte=True, coat=1.0, coat_rau=0.04)
    return S


STOFF.update(innen_grundstoffe())
INNEN_VAR = [ausstattung_stoffe(i) for i in range(3)]
STOFF.update(INNEN_VAR[0])

# ================================================================= Netze
EW = FK.ENTWURF[WAS]
L_AUTO = EW['L']


def objekt(name, V, N, F, stoffe, fidx=None, uv=None):
    """Blender-Objekt aus numpy-Netz mit eigenen Normalen je Punkt.
    innen (x, d, z) -> Blender (x, d - L/2, z): Front bei -Y."""
    V = V + np.array([0.0, -L_AUTO / 2, 0.0])
    me = bpy.data.meshes.new(name)
    me.from_pydata(V.tolist(), [], np.asarray(F).tolist())
    for s in stoffe:
        me.materials.append(STOFF[s])
    if fidx is not None:
        me.polygons.foreach_set('material_index', np.asarray(fidx, np.int32))
    me.polygons.foreach_set('use_smooth', np.ones(len(F), bool))
    if uv is not None:
        lay = me.uv_layers.new(name='UVMap')
        # je Punkt (n, 2) oder je Dreiecksecke (nF, 3, 2) -- Letzteres fuer
        # Drehkoerper, deren Umfangsnaht sonst die ganze Karte rueckwaerts
        # ueber ein einziges Dreieck quetscht
        ecke = uv if uv.ndim == 3 else uv[np.asarray(F)]
        lay.data.foreach_set('uv', ecke.reshape(-1).astype(np.float32))
    me.update()
    n = N / np.maximum(np.linalg.norm(N, axis=1, keepdims=True), 1e-12)
    me.normals_split_custom_set_from_vertices(n.tolist())
    o = bpy.data.objects.new(name, me); sc.collection.objects.link(o)
    return o


def beide_seiten(name, V, N, F, stoffe, fidx=None, uv=None):
    """Rechte Haelfte als _r, gespiegelte als _l. Eine UV je Ecke wird mit
    gespiegelt (u -> -u), damit Schrift auf beiden Seiten lesbar bleibt."""
    objekt(name + '_r', V, N, F, stoffe, fidx, uv=uv)
    V2, N2, F2 = FG.spiegeln(V, N, F)
    uv2 = None
    if uv is not None:
        uv2 = uv[:, ::-1].copy(); uv2[..., 0] = -uv2[..., 0]
    objekt(name + '_l', V2, N2, F2, stoffe, fidx, uv=uv2)


def mit_spiegel(name, V, N, F, stoffe, fidx=None):
    """Ein Objekt aus rechter Haelfte und Spiegelbild (Haube, Dach, ...)."""
    V2, N2, F2 = FG.spiegeln(V, N, F)
    objekt(name, np.r_[V, V2], np.r_[N, N2], np.r_[F, F2 + len(V)], stoffe,
           None if fidx is None else np.r_[fidx, fidx])


def umlauf_richten(V, N, F):
    """Umlaufsinn an die Normalen anpassen (Drehkoerper haben je nach
    Profilrichtung den falschen, das verwirrt Rueckseiten-Culling im Web)."""
    n = np.cross(V[F[:, 1]] - V[F[:, 0]], V[F[:, 2]] - V[F[:, 0]])
    return F[:, ::-1].copy() if (n * N[F].mean(1)).sum(1).mean() < 0 else F


# ================================================================= Karosserie
log('Karosserie', WAS)
K, FORM, T, lab = FK.bauen(WAS, log=log)
G = K.G
ET = FK.ETIKETTEN


def maske(*namen):
    m = np.zeros(G.m * G.n, bool)
    for nme in namen:
        if nme in G.kante:
            m |= G.kante[nme].ravel()
    return m


M_BOGEN = maske('radlauf_v', 'radlauf_h')
M_LOCH = maske('scheinwerfer', 'rueckleuchte', 'grill_o', 'grill_u', 'einlass')
MITTIG = {'karosserie', 'haube', 'klappe', 'stoss_v', 'stoss_h', 'boden', 'frontscheibe'}
BLECH = {'karosserie', 'haube', 'klappe', 'stoss_v', 'stoss_h', 'tuer_v', 'tuer_h', 'boden'}
innenhaut = []


def teil_netz(teil):
    ids = [i for i, (t, s) in enumerate(ET) if t == teil]
    ist = np.isin(lab, ids)
    if not ist.any():
        return None
    V, N, F, alt = G.teilnetz(T, ist)
    namen = sorted({ET[i][1] for i in ids})
    fidx = np.array([namen.index(ET[l][1]) for l in lab[ist]])
    return V, N, F, alt, namen, fidx


def an_alt(alt, n, m):
    """Maske m (Gitterpunkte) auf die Punkte eines Teilnetzes uebertragen;
    Punkte, die erst durch Falze entstanden sind (alt = -1), sind nie dabei."""
    out = np.zeros(n, bool)
    ok = alt >= 0
    out[:len(alt)][ok] = m[alt[ok]]
    return out


def normalen_glaetten(V, N, F, n=40):
    """Schattierungsnormalen der Frontscheibe glaetten (Rand fest). Die
    Scheibe spiegelt die Deckensoftbox unter flachem Winkel; eine Welligkeit
    von Zehntelgrad in der Kruemmung reicht, dass deren Kante als Wolke mit
    Beulen an der A-Saeule erscheint (Probe 23.09.2026)."""
    e = np.r_[F[:, [0, 1]], F[:, [1, 2]], F[:, [2, 0]]]
    u, c = np.unique(np.sort(e, 1), axis=0, return_counts=True)
    rand = np.zeros(len(V), bool); rand[u[c == 1].ravel()] = True
    a, b = np.r_[e[:, 0], e[:, 1]], np.r_[e[:, 1], e[:, 0]]
    N = N / np.linalg.norm(N, axis=1, keepdims=True)
    for _ in range(n):
        S = N.copy(); np.add.at(S, a, N[b]); S /= np.linalg.norm(S, axis=1, keepdims=True)
        N = np.where(rand[:, None], N, S)
    return N


for teil in sorted({t for t, s in ET}):
    r = teil_netz(teil)
    if r is None or teil in ('grill_o', 'grill_u', 'einlass', 'scheinwerfer', 'rueckleuchte'):
        continue
    V, N, F, alt, namen, fidx = r
    if teil in BLECH:
        # Innenhaut: alle undurchsichtigen Flaechen 22 mm nach innen versetzt,
        # dunkel und nach innen gewendet -- durch die Scheiben saehe man sonst
        # die Rueckseite des Lacks.
        S_ = V[F].mean(1)
        kabine = (S_[:, 1] > FK.ANBAU_STIRN[WAS] - 0.15) & (S_[:, 1] < K.L - 0.15) & (S_[:, 2] > 0.42)
        undurch = np.array([namen[i] not in ('glas', 'frontglas') for i in fidx]) & kabine
        if undurch.any():
            Vi, Ni, Fi = FG.versetzen(V, N, F[undurch], -0.022, umdrehen=True)
            # Himmel (hell) nur am Dach: An A-Saeule und Wasserkasten sah man
            # ihn durch die Frontscheibe als weisse Wolke (Poster 23.09.2026).
            Si = Vi[Fi].mean(1)
            dach = (Si[:, 2] > np.array([float(K.zt(x)) for x in Si[:, 1]]) - 0.12) & (Si[:, 2] > 1.20)
            innenhaut.append((Vi, Ni, Fi, dach))
        am_rand = np.abs(V[:, 0]) > 1e-5
        # 1) Fugen: Teilegrenzen ausser Mitte, Radlauf, Leuchten-/Grillloch.
        #    4 mm Spalt (2 mm je Seite), 1,2 mm Kantenradius, 12 mm tief; die
        #    Wand unter dem Radius ist Dichtung. Vorher 3,2 mm mit 1,8 mm
        #    Radius und lackierter Wand: Beide Radien spiegelten die Decke,
        #    die Fuge las sich im Poster als weisse statt dunkle Linie.
        wahl = am_rand & ~an_alt(alt, len(V), M_BOGEN) & ~an_alt(alt, len(V), M_LOCH)
        V, N, F, q = FG.falz(V, N, F, wahl, spalt=0.0020, radius=0.0012, tiefe=0.012)
        if 'dichtung' not in namen:
            namen.append('dichtung')
        neu_f = fidx[q].copy(); neu_f[np.arange(len(q)) >= 2 * len(q) // 3] = namen.index('dichtung')
        fidx = np.r_[fidx, neu_f]; alt = np.r_[alt, np.full(len(V) - len(alt), -1)]
        # 2) Radlauf: gebordelte Kante, 5 mm Radius, 30 mm nach innen
        V, N, F, q = FG.falz(V, N, F, an_alt(alt, len(V), M_BOGEN), spalt=0.0, radius=0.005, tiefe=0.030)
        fidx = np.r_[fidx, fidx[q]]; alt = np.r_[alt, np.full(len(V) - len(alt), -1)]
        # 3) Leuchten- und Grilloeffnungen: schwarze Laibung, 45 mm tief
        if 'kunststoff' not in namen:
            namen.append('kunststoff')
        V, N, F, q = FG.falz(V, N, F, an_alt(alt, len(V), M_LOCH), spalt=0.0, radius=0.0012, tiefe=0.045)
        fidx = np.r_[fidx, np.full(len(q), namen.index('kunststoff'))]
    if teil == 'frontscheibe':
        N = normalen_glaetten(V, N, F, 40)
    if teil in MITTIG:
        mit_spiegel(teil, V, N, F, namen, fidx)
    else:
        beide_seiten(teil, V, N, F, namen, fidx)
    log('  Teil', teil, len(F), 'Dreiecke', namen)

# ---------------------------------------------------------------- Leuchten
# Linse = die Flaeche selbst (klar bzw. rot, 4 mm Wandstaerke am Rand).
# Dahinter: Gehaeuse, Lichtleiter entlang der Kontur, beim Scheinwerfer zwei
# Projektionsmodule. Erster Versuch: ein leuchtendes Band nach Hoehe z --
# es franste an den Dreieckskanten aus und sah aus wie ein Fehler.
for teil, linse in (('scheinwerfer', 'linse'), ('rueckleuchte', 'linse_rot')):
    r = teil_netz(teil)
    if r is None:
        continue
    V, N, F, alt, namen, fidx = r
    Vl, Nl, Fl, q = FG.falz(V, N, F, np.ones(len(V), bool), spalt=0.0, radius=0.001, tiefe=0.004)
    beide_seiten(teil, Vl, Nl, Fl, [linse])
    innen = FD.scheinwerfer_innen(V, N, F, WAS) if teil == 'scheinwerfer' else FD.rueckleuchte_innen(V, N, F)
    stoffe = {'tagfahrlicht': 'tagfahrlicht', 'gehaeuse': 'lampengehaeuse', 'projektor': 'reflektor',
              'projektor_linse': 'frontglas', 'leuchtband': 'rueck_leucht', 'leuchtband_innen': 'rueck_leucht'}
    for name, (Vi, Ni, Fi) in innen.items():
        beide_seiten(f'{teil}_{name}', Vi, Ni, FG.umlauf_je_flaeche(Vi, Ni, Fi), [stoffe[name]])

# ---------------------------------------------------------------- Grill
# Grund 30 mm hinter der Oeffnung (mattschwarz), davor ein Wabengitter,
# das der Flaeche folgt.
for teil in ('grill_o', 'grill_u', 'einlass'):
    r = teil_netz(teil)
    if r is None:
        continue
    V, N, F, alt, namen, fidx = r
    Vg, Ng, Fg = FG.versetzen(V, N, F, -0.030)
    mit_spiegel(teil, Vg, Ng, Fg, ['grill_grund'])
    Vf = V - N * 0.010
    ref = np.c_[Vf[:, 0], Vf[:, 2]]

    def flaeche_d(x, z, ref=ref, Vf=Vf):
        q = np.c_[np.abs(x), z]
        i = np.argmin(((q[:, None, :] - ref[None]) ** 2).sum(-1), 1)
        return Vf[i, 1]
    umriss = getattr(FORM[teil], 'umriss', None)
    if umriss is None:
        continue
    zelle = 0.020 if teil == 'einlass' else 0.024
    w = FD.waben(umriss[1], flaeche_d, zelle=zelle)
    if w is not None:
        Vw, Nw, Fw = w
        objekt(teil + '_waben', Vw, Nw, FG.umlauf_je_flaeche(Vw, Nw, Fw), ['grill'])

# ---------------------------------------------------------------- Innenhaut
if innenhaut:
    V = np.concatenate([h[0] for h in innenhaut]); N = np.concatenate([h[1] for h in innenhaut])
    off = np.cumsum([0] + [len(h[0]) for h in innenhaut[:-1]])
    F = np.concatenate([h[2] + o for h, o in zip(innenhaut, off)])
    fx = np.concatenate([h[3] for h in innenhaut]).astype(int)
    mit_spiegel('innenhaut', V, N, F, ['innen', 'himmel'], fx)


# ================================================================= Radhaeuser
def radhaus(d_achse, name):
    """Radhausschale: Zylinderstueck mit dem Radius des Radlaufs, von innen
    (x = 0,36) bis knapp hinter die Aussenhaut."""
    R = K.R_BOGEN + 0.004
    th = np.linspace(np.radians(-20), np.radians(200), 140)
    d = d_achse + R * np.cos(th); z = K.z_achse + R * np.sin(th)
    ok = z > np.array([float(K.zu(x)) for x in d]) - 0.03
    d, z = d[ok], z[ok]
    x_aus = []
    for di, zi in zip(d, z):
        fl = K.querschnitt(di)[FK.J_FLANKE:FK.J_OBEN]
        x_aus.append(fl[np.argmin(np.abs(fl[:, 1] - zi)), 0] - 0.012)
    x_aus = np.array(x_aus)
    xs = np.linspace(0, 1, 12)
    Pn = np.stack([0.36 + (x_aus[:, None] - 0.36) * xs[None, :], np.repeat(d[:, None], 12, 1), np.repeat(z[:, None], 12, 1)], -1)
    Gr = FG.Gitter(Pn)
    mitte = np.array([0, d_achse, K.z_achse])
    if ((Pn - mitte)[..., 1:] * Gr.N[..., 1:]).sum(-1).mean() > 0:
        Gr.N = -Gr.N
    V, N, F, _ = FR.netz_aus_gitter(Gr)
    beide_seiten(name, V, N, umlauf_richten(V, N, F), ['kunststoff'])


radhaus(K.achse[0], 'radhaus_v'); radhaus(K.achse[1], 'radhaus_h')


# ================================================================= Raeder
def reifen_uv(V, F):
    """UV je Dreiecksecke fuer die Flankenschrift (fz_texturen.reifen):
    u laeuft im Uhrzeigersinn, von aussen gesehen -- so steht die Schrift
    mit dem Kopf nach aussen und liest sich von links nach rechts."""
    th = np.arctan2(V[:, 2], V[:, 1])
    u = (-th / (2 * np.pi)) % 1.0
    rho = np.clip((np.hypot(V[:, 1], V[:, 2]) - K.R_FELGE) / (K.R_REIFEN - K.R_FELGE), 0, 1)
    v = np.where(V[:, 0] >= 0, 0.5 * rho, 1 - 0.5 * rho)
    ue = u[F]
    sprung = ue.max(1) - ue.min(1) > 0.5
    ue = np.where(sprung[:, None] & (ue < 0.5), ue + 1.0, ue)
    return np.stack([ue, v[F]], -1)


def rad_teile(vorn):
    teile = []
    pr = FR.reifen_profil(K)
    Gt = FR.drehkoerper(pr, int(240 * FR.SEGMENTE))
    FR.profil_stollen(K, Gt)
    if (Gt.N[..., 1:] * Gt.P[..., 1:]).sum(-1).mean() < 0:
        Gt.N = -Gt.N
    Tt, _ = Gt.dreiecke(lambda S, IJ, N: np.zeros(len(S), int))
    S = Gt.P.reshape(-1, 3)[Tt].mean(1)
    lauf = np.hypot(S[:, 1], S[:, 2]) > K.R_REIFEN - 0.028
    V, N, F, _ = Gt.teilnetz(Tt, np.ones(len(Tt), bool))
    teile.append(('reifen', V, N, F, ['reifen', 'flanke'], np.where(lauf, 0, 1), reifen_uv(V, F)))
    Gf, Tf, lf, axial, a_horn = FR.felge(K, WAS)
    V, N, F, alt = Gf.teilnetz(Tf, lf >= 0)
    V, N, F, q = FG.falz(V, N, F, Gf.kante['fenster'].ravel()[alt], spalt=0.0, radius=0.0025, tiefe=0.028)
    teile.append(('felge', V, N, F, ['felge'], None))
    V, N, F, _ = FR.netz_aus_gitter(FR.felgenbett(K, a_horn))
    teile.append(('felgenbett', V, N, F, ['felgenbett'], None))
    V, N, F = FA.superellipsoid((float(axial(0.0)) + 0.002, 0, 0), (0.006, 0.031, 0.031), (0.004, 0.031, 0.031), e1=0.25, e2=1.0, nu=12, nv=48, achsen=(1, 2, 0))
    teile.append(('nabendeckel', V, N, F, ['rahmen'], None))
    # Radschrauben, Lochkreis 5x100 (Kleinwagen) bzw. 5x112 (Mittelklasse)
    lk = {'kleinwagen': 0.050, 'mittelklasse': 0.056}[WAS]
    alle = []
    for k in range(5):
        w = np.radians(90 + 72 * k + 36)
        a = float(axial(lk))
        Gs = FR.drehkoerper([(0.0005, a + 0.010), (0.0075, a + 0.010), (0.0086, a + 0.006), (0.0086, a - 0.004)], 24)
        if Gs.N[..., 0].mean() < 0:
            Gs.N = -Gs.N
        V, N, F, _ = FR.netz_aus_gitter(Gs)
        alle.append((V + np.array([0, lk * np.cos(w), lk * np.sin(w)]), N, F))
    n0 = len(alle[0][0])
    teile.append(('schrauben', np.concatenate([a[0] for a in alle]), np.concatenate([a[1] for a in alle]),
                  np.concatenate([a[2] + i * n0 for i, a in enumerate(alle)]), ['schraube'], None))
    Gs, Rs, a0, dd = FR.bremse(K, vorn)
    V, N, F, _ = FR.netz_aus_gitter(Gs)
    teile.append(('bremsscheibe', V, N, F, ['scheibe'], None))
    V, N, F, _ = FR.netz_aus_gitter(FR.sattel(K, Rs, a0, dd, np.radians(160)))
    teile.append(('sattel', V, N, F, ['sattel'], None))
    return teile


for vorn, d_achse, spur in ((True, K.achse[0], EW['spur'][0]), (False, K.achse[1], EW['spur'][1])):
    for teil, V, N, F, st, fidx, *rest in rad_teile(vorn):
        uv = rest[0] if rest else None
        F2 = umlauf_richten(V, N, F)
        if uv is not None and F2 is not F:
            uv = uv[:, ::-1]
        F = F2
        V = V + np.array([spur / 2, d_achse, K.z_achse])
        if teil == 'reifen':
            # Der Reifen federt 12 mm ein: Latsch flach auf dem Boden
            V[:, 2] = np.maximum(V[:, 2], 0.0)
        beide_seiten(f"{teil}_{'v' if vorn else 'h'}", V, N, F, st, fidx, uv)


# ================================================================= Anbauteile
# Namen mit tuer_v_/tuer_h_: Spiegel und Griffe sitzen an der Tuer und
# fliegen in der Explosionsansicht mit ihr.
for teil, (V, N, F) in FA.spiegel(K).items():
    st = {'spiegel_gehaeuse': 'lack', 'spiegel_glas': 'spiegelglas', 'spiegel_fuss': 'rahmen'}[teil]
    beide_seiten('tuer_v_' + teil, V, N, umlauf_richten(V, N, F), [st])
for tuer, (V, N, F) in zip(('tuer_v', 'tuer_h'), FA.griffe(K)):
    beide_seiten(tuer + '_griff', V, N, umlauf_richten(V, N, F), ['lack' if WAS == 'kleinwagen' else 'zier'])
for tuer, (d0, d1) in zip(('tuer_v', 'tuer_h'), FA.ANBAU[WAS]['griffe']):
    V, N, F = FA.griffmulde(K, d0, d1, FA.ANBAU[WAS]['griff_z'])
    beide_seiten(tuer + '_mulde', V, N, umlauf_richten(V, N, F), ['lack'])
for d0, d1, tuer in FA.BLENDEN[WAS]:
    V, N, F = FA.saeulenblende(K, d0, d1)
    beide_seiten(tuer + '_blende', V, N, FG.umlauf_je_flaeche(V, N, F), ['rahmen'])
for name, ((V, N, F), st) in FT.technik(K).items():
    objekt(name, V, N, FG.umlauf_je_flaeche(V, N, F), [st])
V, N, F = FA.wischer(K)
objekt('wischer', V, N, umlauf_richten(V, N, F), ['kunststoff'])
if WAS == 'kleinwagen':
    V, N, F = FA.spoiler(K)
    objekt('klappe_spoiler', V, N, umlauf_richten(V, N, F), ['lack'])
V, N, F = FA.antenne(K)
objekt('antenne', V, N, umlauf_richten(V, N, F), ['rahmen'])
def teil_objekt(name, t):
    """fz_innen.Teil -> Blender-Objekt (Material je Dreieck, UV in Metern)."""
    return objekt(name, t.V, t.N, FG.umlauf_je_flaeche(t.V, t.N, t.F), t.stoffe, t.fidx, uv=t.uv)


# Innenraum (fz_innen): Sitze, Armaturentafel mit Displays, Lenkrad,
# Konsole, Teppich, Himmel, Pedale -- ersetzt die Superellipsoid-Fassung.
MI = FI.INNEN[WAS]
for name, t in FI.innenraum(K, MI).items():
    teil_objekt(name, t)
    log('  Innenraum', name, len(t.F), 'Dreiecke')


def teil_beide(name, t_r, t_l=None):
    """Teil auf der rechten Netzhaelfte (x > 0, Fahrerseite) als _r, das
    Spiegelbild (oder ein eigenes Teil t_l, schon gespiegelt) als _l."""
    teil_objekt(name + '_r', t_r)
    teil_objekt(name + '_l', (t_l if t_l is not None else t_r).bewegt(spiegel_x=True))


# ================================================================= Tueren oeffnen
# Innenseite, Falzflaeche, Verkleidung je Tuer; Tuerausschnitt, B-Saeule,
# Einstiegsleisten an der Karosserie. Danach haengen alle Teile einer Tuer an
# einem Scharnier-Empty (Drehpunkt), das Web dreht nur dieses.
SCHARNIER = {}
for tuer in ('tuer_v', 'tuer_h'):
    V, N, F, alt, namen, fidx = teil_netz(tuer)
    teile_f, (p, a) = FTU.tuer_innen(K, V, N, F, namen, fidx, tuer, fahrer_seite=True)
    teile_b, _ = FTU.tuer_innen(K, V, N, F, namen, fidx, tuer, fahrer_seite=False)
    for k in teile_f:
        teil_beide(f'{tuer}_{k}', teile_f[k], teile_b[k])
    for k, t in FTU.tuerausschnitt(K, V, N, F, namen, fidx).items():
        teil_beide(f'ausschnitt_{tuer[-1]}_{k}', t)
    SCHARNIER[tuer] = (p, a)
    log('  Tuer', tuer, 'Scharnier', p.round(3), a.round(3))
bs = FA.BLENDEN[WAS]
teil_beide('innen_b_saeule', FTU.b_saeule(K, MI, bs[0][0] + 0.01, bs[1][1] - 0.01))
teil_beide('einstieg_v', FTU.einstieg(K, FK.ANBAU_STIRN[WAS] - 0.02, bs[0][1] - 0.02))
teil_beide('einstieg_h', FTU.einstieg(K, bs[1][0] + 0.02, bs[1][0] + 0.62))


def scharniere():
    """Empty je Tuer und Seite im Drehpunkt; alle Tuerteile als Kinder.
    Achse (glTF-Koordinaten) und Oeffnungswinkel als extras."""
    import re
    for tuer, (p, a) in SCHARNIER.items():
        for seite, sx in (('r', 1.0), ('l', -1.0)):
            e = bpy.data.objects.new(f'{tuer}_{seite}_angel', None)
            sc.collection.objects.link(e)
            e.location = (sx * p[0], p[1] - L_AUTO / 2, p[2])
            ab = (sx * a[0], a[1], a[2])
            # Blender (x, y, z) -> glTF (x, z, -y)
            e['achse'] = [ab[0], ab[2], -ab[1]]
            e['winkel'] = 65.0 if tuer == 'tuer_v' else 70.0
            e['seite'] = sx
            bpy.context.view_layer.update()
            muster = re.compile(rf'^{tuer}(_.*)?_{seite}$')
            for o in list(sc.objects):
                if o.type == 'MESH' and muster.match(o.name) and o.parent is None:
                    o.parent = e
                    o.matrix_parent_inverse = e.matrix_world.inverted()


scharniere()


# ================================================================= Kennzeichen
def aussenhaut_d(z, vorn):
    ds = np.linspace(0, 0.35, 700) if vorn else np.linspace(K.L, K.L - 0.45, 900)
    for d in ds:
        if float(K.zu(d)) <= z <= float(K.zt(d)):
            return d
    return ds[0]


def schild(name, z, vorn):
    """EU-Format 520 x 110 mm, hier 520 x 130 fuer das 4:1-Bild."""
    d = aussenhaut_d(z, vorn) + (-0.006 if vorn else 0.006)
    ys = np.array([-0.26, 0.26, 0.26, -0.26]); zs = np.array([-0.065, -0.065, 0.065, 0.065])
    V = np.stack([ys, np.full(4, d), z + zs], 1)
    N = np.tile([0, -1.0 if vorn else 1.0, 0], (4, 1))
    F = np.array([[0, 1, 2], [0, 2, 3]])
    uv = np.array([[0, 0], [1, 0], [1, 1], [0, 1]], float)
    if not vorn:
        V[:, 0] *= -1                # von hinten gesehen laeuft x andersherum
    F = umlauf_richten(V, N, F)
    objekt(name, V, N, F, ['kennzeichen'], uv=uv)


# vorn: deckt die Mittelnaht des Bugs (dort laeuft der Querschnitt auf
# einen Strich zu) zwischen 0,40 und 0,54 m ab
schild('kennzeichen_v', 0.482, True)
schild('kennzeichen_h', {'kleinwagen': 0.50, 'mittelklasse': 0.52}[WAS], False)

meshes = [o for o in sc.objects if o.type == 'MESH']
log('Objekte', len(meshes), 'Dreiecke', sum(len(o.data.polygons) for o in meshes))

# ================================================================= Umgebungsverdeckung (nur Web)
# three.js beleuchtet den Innenraum mit der vollen Studioumgebung -- ohne
# Dach, Tueren und Sitze, die in Cycles das Licht wegnehmen. Deshalb wird die
# Verdeckung (AO, 25 cm Reichweite) in Cycles je Punkt gebacken und als
# Punktfarbe mitgegeben; three.js multipliziert sie auf die Grundfarbe. Nur
# fuer Innenraum, Tuerinnenseiten und Tuerausschnitt -- der Lack bleibt frei.
AO_MUSTER = ('innen_', 'tuer_v_innen', 'tuer_h_innen', 'tuer_v_falz', 'tuer_h_falz', 'tuer_v_verkleidung', 'tuer_h_verkleidung',
             'ausschnitt_', 'einstieg_', 'innenhaut')
if WEB:
    welt = bpy.data.worlds.new('AO'); sc.world = welt
    welt.light_settings.distance = 0.25
    sc.render.engine = 'CYCLES'
    try:
        prefs = bpy.context.preferences.addons['cycles'].preferences
        for typ in ('OPTIX', 'CUDA'):
            try:
                prefs.compute_device_type = typ; prefs.get_devices()
                if any(d.type == typ for d in prefs.devices):
                    for d in prefs.devices: d.use = d.type == typ
                    break
            except Exception:
                pass
        sc.cycles.device = 'GPU'
    except Exception:
        pass
    sc.cycles.samples = 64
    ao_obj = [o for o in sc.objects if o.type == 'MESH' and o.name.startswith(AO_MUSTER)]
    bpy.ops.object.select_all(action='DESELECT')
    for o in ao_obj:
        attr = o.data.color_attributes.new('AO', 'BYTE_COLOR', 'POINT')
        o.data.color_attributes.active_color = attr
        o.select_set(True)
    if ao_obj:
        bpy.context.view_layer.objects.active = ao_obj[0]
        t0 = time.time()
        bpy.ops.object.bake(type='AO', target='VERTEX_COLORS')
        log('AO gebacken', len(ao_obj), 'Objekte', round(time.time() - t0, 1), 's')

# ================================================================= Export
# Traeger fuer die Stoffe der Ausstattungen 2 und 3: Der Exporter schreibt
# nur Materialien, die an einem Netz haengen. Je Stoff ein winziges Dreieck;
# der Knoten wird danach aus der Szene genommen (Mesh und Stoffe bleiben).
_tv, _tf, _tm = [], [], []
_stoffe_traeger = [m for i in (1, 2) for m in INNEN_VAR[i].values()]
for k, m in enumerate(_stoffe_traeger):
    o = len(_tv); _tv += [(0, 0, -5 - k * 0.01), (0.001, 0, -5 - k * 0.01), (0, 0.001, -5 - k * 0.01)]; _tf.append((o, o + 1, o + 2)); _tm.append(k)
_me = bpy.data.meshes.new('varianten_traeger'); _me.from_pydata(_tv, [], _tf)
for m in _stoffe_traeger:
    _me.materials.append(m)
_me.polygons.foreach_set('material_index', np.array(_tm, np.int32))
# UV noetig, damit Texturen der Stoffe mitgehen
_me.uv_layers.new(name='UVMap')
_ob = bpy.data.objects.new('varianten_traeger', _me); sc.collection.objects.link(_ob)

ziel = os.path.join(P, 'quelle', f"{WAS}{'-web' if WEB else ''}.glb")
_ex = dict(filepath=ziel, export_format='GLB', export_yup=True, export_apply=True,
           export_texcoords=True, export_normals=True, export_materials='EXPORT', export_extras=True)
if WEB:
    _ex['export_vertex_color'] = 'ACTIVE'
try:
    bpy.ops.export_scene.gltf(**_ex)
except TypeError:
    _ex.pop('export_vertex_color', None); _ex['export_colors'] = WEB
    bpy.ops.export_scene.gltf(**_ex)
log('GLB', ziel, os.path.getsize(ziel))


# Lackvarianten (KHR_materials_variants) direkt ins GLB: Der Exporter kennt
# Varianten nur ueber Szenendaten, die man sonst in der Oberflaeche anlegt.
# Die zwei weiteren Lacke sind Kopien des ersten mit anderer Farbe,
# Metallic- und Rauheitswert.
def glb_lesen(p):
    b = open(p, 'rb').read()
    n = struct.unpack('<I', b[12:16])[0]
    g = json.loads(b[20:20 + n])
    off = 20 + n
    bl = struct.unpack('<I', b[off:off + 4])[0]
    return g, b[off + 8: off + 8 + bl]


def glb_schreiben(p, g, binb):
    js = json.dumps(g, separators=(',', ':')).encode()
    js += b' ' * ((4 - len(js) % 4) % 4)
    binb += b'\0' * ((4 - len(binb) % 4) % 4)
    out = struct.pack('<III', 0x46546C67, 2, 12 + 8 + len(js) + 8 + len(binb))
    out += struct.pack('<II', len(js), 0x4E4F534A) + js
    out += struct.pack('<II', len(binb), 0x004E4942) + binb
    open(p, 'wb').write(out)


g, binb = glb_lesen(ziel)
basis = next(i for i, m in enumerate(g['materials']) if m.get('name') == lack_name)
idx = [basis]
for name, farbe, met, rau in LACK[1:]:
    m = json.loads(json.dumps(g['materials'][basis]))
    m['name'] = name
    pbr = m.setdefault('pbrMetallicRoughness', {})
    pbr['baseColorFactor'] = [*farbe, 1.0]
    pbr['metallicFactor'] = met; pbr['roughnessFactor'] = rau
    g['materials'].append(m); idx.append(len(g['materials']) - 1)
# Varianten: 0-2 Lacke, 3-5 Ausstattungen (Name mit "Innen: "). Das Web und
# das Studio trennen die beiden Gruppen am Namen.
namen_var = [n[0].replace('Paint ', '') for n in LACK] + ['Innen: ' + a[0] for a in AUSSTATTUNG]
g.setdefault('extensions', {})['KHR_materials_variants'] = {'variants': [{'name': n} for n in namen_var]}
if 'KHR_materials_variants' not in g.setdefault('extensionsUsed', []):
    g['extensionsUsed'].append('KHR_materials_variants')
mat_idx = {m.get('name'): i for i, m in enumerate(g['materials'])}
innen_abb = {}
for k in INNEN_VAR[0]:
    b0 = mat_idx.get(INNEN_VAR[0][k].name)
    if b0 is None:
        continue
    innen_abb[b0] = [mat_idx.get(INNEN_VAR[i][k].name, b0) for i in range(3)]
belegt = 0
for me in g['meshes']:
    for pr in me['primitives']:
        if pr.get('material') == basis:
            pr.setdefault('extensions', {})['KHR_materials_variants'] = {
                'mappings': [{'material': m, 'variants': [k]} for k, m in enumerate(idx)]}
            belegt += 1
        elif pr.get('material') in innen_abb:
            pr.setdefault('extensions', {})['KHR_materials_variants'] = {
                'mappings': [{'material': m, 'variants': [3 + k]} for k, m in enumerate(innen_abb[pr['material']])]}
            belegt += 1
# Traegerknoten aus der Szene nehmen
for i, nd in enumerate(g['nodes']):
    if nd.get('name') == 'varianten_traeger':
        for szn in g.get('scenes', []):
            if i in szn.get('nodes', []):
                szn['nodes'].remove(i)
g.setdefault('asset', {})['extras'] = {
    'herkunft': f"Vecom Design, eigener Entwurf ({EW['name']}); Masse aus dem Klassendurchschnitt der Herstellerdatenblaetter"}
glb_schreiben(ziel, g, binb)
log('Varianten', namen_var, 'Primitive', belegt)

if not WEB:
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(P, f'{WAS}-bau.blend'))
log('FERTIG')
