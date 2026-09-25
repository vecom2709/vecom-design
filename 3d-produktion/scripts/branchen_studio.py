"""Fotostudio fuer die Branchen-Demos: Auto und Schuh.

Aufruf (headless):
  blender -b -P branchen_studio.py -- auto probe
  blender -b -P branchen_studio.py -- auto voll
  blender -b -P branchen_studio.py -- schuh voll

Was hier entsteht, sind die POSTER: gerechnete Fotos, die sofort auf der
Seite stehen (LCP, kein Skript noetig) und die das Echtzeitmodell spaeter
passgenau ueberlagert -- dasselbe Prinzip wie bei der Villa. Deshalb schreibt
das Skript neben jedes Bild die Kamera als JSON: Position, Ziel, Brennweite,
Sensor. Die Webseite rechnet daraus dieselbe Projektion.

Licht wie in der Autofotografie: ein grosser Deckendiffusor (die "Flaeche",
in der sich das Dach spiegelt), zwei Streiflichter seitlich fuer die Linie
auf der Flanke, ein Kantenlicht von hinten. Die HDRI (Poly Haven,
studio_small_03, CC0) gibt nur die Umgebung fuer Spiegelungen -- sie ist fuer
die Kamera unsichtbar, dort steht ein fast schwarzer Hintergrund in der
Farbe der Seite.
"""
import bpy, json, os, sys, math, time
from mathutils import Vector

P = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
# blender-lauf.ps1 reicht -Werte a,b als EIN Argument "a,b" durch.
argv = [t for a in argv for t in a.split(',') if t]
WAS = argv[0] if argv else 'auto'
# Die beiden Serienautos (fahrzeug_bau.py) fotografiert dasselbe Studio wie
# das Konzeptauto: gleiche Lichter, gleiche Kamera, gleiche Seite.
AUTO = WAS in ('auto', 'kleinwagen', 'mittelklasse')
# Produktdemos (wein, lkw, ...) nutzen das kleine Studio des Schuhs, auf
# ihre Groesse skaliert (SK, unten nach dem Einlesen): Abstaende linear,
# Leuchtenergien quadratisch -- dann bleibt die Beleuchtungsstaerke gleich.
ART = 'auto' if AUTO else 'schuh'
MODUS = argv[1] if len(argv) > 1 else 'probe'
# Zusatzwerte name=wert (winkel, hoehe, lens, prozent, name) fuer Pruefansichten
EXTRA = {k: v for k, v in (t.split('=', 1) for t in argv[2:] if '=' in t)}
NUR = [t for t in argv[2:] if '=' not in t] or None   # nur bestimmte Varianten

AUSGABE = os.path.join(P, 'render', WAS)
os.makedirs(AUSGABE, exist_ok=True)
STATUS = os.path.join(P, f'status-{WAS}.json')

def status(**k):
    k['zeit'] = time.strftime('%H:%M:%S')
    with open(STATUS, 'w', encoding='utf-8') as f:
        json.dump(k, f, ensure_ascii=False)

# ------------------------------------------------------------------ Szene
bpy.ops.wm.read_factory_settings(use_empty=True)
sc = bpy.context.scene
QUELLE = {'auto': 'auto-ohne-logos.glb', 'schuh': 'schuh.glb', 'kleinwagen': 'kleinwagen.glb', 'mittelklasse': 'mittelklasse.glb'}.get(WAS, f'{WAS}.glb')
bpy.ops.import_scene.gltf(filepath=os.path.join(P, 'quelle', QUELLE))
# Der Traeger der Ausstattungsstoffe (fahrzeug_bau.py) liegt 5 m unter dem
# Boden und ist im GLB aus der Szene genommen -- Blenders Import holt ihn
# trotzdem herein. Er machte das Modell 6,6 m hoch, und das Rundumbild fuer
# die Webseite wurde auf 2,95 statt 0,66 m Hoehe gebacken: Die Decken-
# softbox fuellte dort den halben Himmel (Umgebung 1,76x heller, 23.09.2026).
for _o in [o for o in sc.objects if o.name.startswith('varianten_traeger')]:
    bpy.data.objects.remove(_o, do_unlink=True)
# Nur fuers Web (Eigenschaft nur_web aus pr_<was>.py): Wein im Glas, Kisten
# fuer 2 und 3 Flaschen -- das Foto zeigt den Ausgangszustand (24.09.2026).
# Im Film steht Wein im Glas (eingeschenkt, 3,4 cm) und die Pasta auf dem
# Teller -- ein leeres Glas neben der Flasche wirkte im Probefilm wie ein
# unfertiger Tisch (24.09.2026).
FILM_BEHALTEN = ('weinglas_wein', 'gang1_') if MODUS == 'film' else ()
for _o in [o for o in sc.objects if o.get('nur_web') and not o.name.startswith(FILM_BEHALTEN or ('\0',))]:
    bpy.data.objects.remove(_o, do_unlink=True)
modell = [o for o in sc.objects if o.type == 'MESH']

def huelle(objs):
    lo = Vector((1e9,) * 3); hi = Vector((-1e9,) * 3)
    for o in objs:
        for c in o.bound_box:
            w = o.matrix_world @ Vector(c)
            lo = Vector(map(min, lo, w)); hi = Vector(map(max, hi, w))
    return lo, hi

lo, hi = huelle(modell)
# Aufstandspunkt: beim Auto die Reifen, nicht die Huelle -- unter dem Boden
# haengen Teile (Unterboden, Achsen), die tiefer reichen als die Laufflaeche.
def tiefster_punkt(objs):
    # Echte Eckpunkte statt Huellquader: Die Vorderraeder sind eingeschlagen,
    # ihr gedrehter Huellquader reicht tiefer als die Laufflaeche -- im ersten
    # Probebild stand das Auto dadurch 11 cm ueber dem Boden.
    z = 1e9
    for o in objs:
        mw = o.matrix_world
        for v in o.data.vertices:
            z = min(z, (mw @ v.co).z)
    return z
if AUTO:
    reifen = [o for o in modell if any(s.material and s.material.name == 'Tiretread' for s in o.material_slots)]
    boden_z = tiefster_punkt(reifen or modell)
else:
    boden_z = tiefster_punkt(modell)
print('BODEN', boden_z, 'HUELLE', lo.z)
mitte = (lo + hi) / 2
groesse = hi - lo
SK = 1.0 if (AUTO or WAS == 'schuh') else max(groesse.x, groesse.y, groesse.z) / 0.30
print('SKALA', round(SK, 3))

# ------------------------------------------------------------------ Welt
welt = bpy.data.worlds.new('Studio'); sc.world = welt
welt.use_nodes = True
nt = welt.node_tree; nt.nodes.clear()
aus = nt.nodes.new('ShaderNodeOutputWorld')
env = nt.nodes.new('ShaderNodeTexEnvironment')
# Weiche Fassung (23.09.2026): Schwelle 0,9 schon abgezogen und die Leuchten
# um ~2,5 Grad weichgezeichnet (Energie gleich). Der Schirm der HDRI hat acht
# Speichen und einen achteckigen Rand; in der gewoelbten Frontscheibe stand
# er als weisser, gezackter Keil an der A-Saeule (Strahlmessung: Spiegelung
# Richtung Schirm; glas=spiegel zeigte den Keil, glas=gerade nicht). Ein
# Fotograf haette den Schirm diffus abgehaengt -- das hier ist dieselbe Tat.
_weich = os.path.join(P, 'quelle', 'studio_small_03_2k_weich.hdr')
env.image = bpy.data.images.load(_weich if os.path.exists(_weich) and not EXTRA.get('hdri_hart')
                                 else os.path.join(P, 'quelle', 'studio_small_03_2k.hdr'))
env.interpolation = 'Cubic'
kopp = nt.nodes.new('ShaderNodeTexCoord'); dreh = nt.nodes.new('ShaderNodeMapping')
DREH_HDRI = float(EXTRA.get('hdri_dreh', {'auto': 205.0, 'schuh': 150.0}[ART]))
SCHWELLE = 0.0 if env.image.filepath.endswith('_weich.hdr') else 0.9
GLANZ_MAX = {'auto': 0.4, 'schuh': 0.12}[ART]
# Schuh: Kamera fast auf Bodenhoehe -- unter so flachem Winkel spiegelt
# selbst ein dunkler Boden die Softbox der HDRI als helle Flaeche (Probe 2).
RAUHEIT = {'auto': (0.26, 0.40), 'schuh': (0.45, 0.6)}[ART]
BODENLICHT = {'auto': 260.0, 'schuh': 1.2}[ART] * SK * SK
dreh.inputs['Rotation'].default_value[2] = math.radians(DREH_HDRI)
nt.links.new(kopp.outputs['Generated'], dreh.inputs['Vector'])
nt.links.new(dreh.outputs['Vector'], env.inputs['Vector'])
hell = nt.nodes.new('ShaderNodeBackground'); hell.inputs['Strength'].default_value = float(EXTRA.get('welt', 0.55))
# Die HDRI ist ein WEISSES Hohlkehlenstudio. Waende und Boden (Median 0,012,
# 90 % unter 0,6) spiegelten sich unter flachem Winkel im Studioboden und
# machten ihn milchig (drittes Probebild). Alles unter 0,9 faellt weg -- das
# sind 97,6 % der Flaeche, aber nur 8 % der Energie. Uebrig bleiben die
# Softboxen und Lichtleisten: ein schwarzes Autostudio mit echten Leuchten.
# Die Webseite bekommt dieselbe Rechnung, vorab auf die HDR-Datei angewendet.
schwelle = nt.nodes.new('ShaderNodeVectorMath'); schwelle.operation = 'SUBTRACT'
schwelle.inputs[1].default_value = (SCHWELLE, SCHWELLE, SCHWELLE)
null = nt.nodes.new('ShaderNodeVectorMath'); null.operation = 'MAXIMUM'
null.inputs[1].default_value = (0.0, 0.0, 0.0)
nt.links.new(env.outputs['Color'], schwelle.inputs[0])
nt.links.new(schwelle.outputs['Vector'], null.inputs[0])
nt.links.new(null.outputs['Vector'], hell.inputs['Color'])
# Fuer die Kamera: Grund der Seite (#05070d) -- linear ~ (0.0015, 0.0021, 0.0040)
grund = nt.nodes.new('ShaderNodeBackground')
grund.inputs['Color'].default_value = (0.0016, 0.0022, 0.0042, 1)
weg = nt.nodes.new('ShaderNodeLightPath'); mix = nt.nodes.new('ShaderNodeMixShader')
nt.links.new(weg.outputs['Is Camera Ray'], mix.inputs['Fac'])
nt.links.new(hell.outputs['Background'], mix.inputs[1])
nt.links.new(grund.outputs['Background'], mix.inputs[2])
nt.links.new(mix.outputs['Shader'], aus.inputs['Surface'])

# ------------------------------------------------------------------ Boden
# Epoxid-Studioboden: fast schwarz, seidenmatt, mit leichter Rauheitsstreuung
# (ein spiegelglatter Boden verraet CGI sofort). Weit genug, dass kein Rand
# ins Bild kommt; der Uebergang zum Hintergrund verschwindet im Dunkel.
bpy.ops.mesh.primitive_plane_add(size=80 if AUTO else 8 * max(SK, 1.0), location=(mitte.x, mitte.y, boden_z))
boden = sc.objects[-1] if sc.objects[-1].type == 'MESH' else bpy.context.active_object
boden = bpy.context.active_object; boden.name = 'Studioboden'
mb = bpy.data.materials.new('Studioboden'); mb.use_nodes = True
bn = mb.node_tree.nodes; bsdf = bn.get('Principled BSDF')
bsdf.inputs['Base Color'].default_value = (0.006, 0.007, 0.009, 1)
bsdf.inputs['Roughness'].default_value = 0.22
# Lichtinsel: Der Boden glaenzt nur um das Objekt herum und laeuft nach aussen
# ins Dunkel. Ohne das spiegelte er im ersten Probebild den weissen Studio-
# boden der HDRI und zog quer durchs Bild eine helle Horizontlinie.
koord = bn.new('ShaderNodeTexCoord'); abst = bn.new('ShaderNodeVectorMath'); abst.operation = 'LENGTH'
mb.node_tree.links.new(koord.outputs['Object'], abst.inputs[0])
insel = bn.new('ShaderNodeMapRange')
R_INSEL = tuple(v * SK for v in {'auto': (2.6, 9.0), 'schuh': (0.25, 0.9)}[ART])
insel.inputs['From Min'].default_value = R_INSEL[0]; insel.inputs['From Max'].default_value = R_INSEL[1]
insel.inputs['To Min'].default_value = 1.0; insel.inputs['To Max'].default_value = 0.0
mb.node_tree.links.new(abst.outputs['Value'], insel.inputs['Value'])
# Glanz hoechstens 0,4: Bei voller Staerke spiegelte der Boden den Decken-
# diffusor als milchige Flaeche (zweites Probebild) -- ein Autostudio mit
# weissem Boden, nicht der dunkle Raum der Seite.
glanz = bn.new('ShaderNodeMath'); glanz.operation = 'MULTIPLY'; glanz.inputs[1].default_value = GLANZ_MAX
mb.node_tree.links.new(insel.outputs['Result'], glanz.inputs[0])
mb.node_tree.links.new(glanz.outputs['Value'], bsdf.inputs['Specular IOR Level'])
# Ganz aussen wird der Boden durchsichtig: Dort sieht die Kamera den Grund
# der Seite, und es gibt keine Kante zwischen Boden und Hintergrund.
durch = bn.new('ShaderNodeBsdfTransparent'); misch = bn.new('ShaderNodeMixShader')
aussen = bn.new('ShaderNodeMapRange')
R_AUS = tuple(v * SK for v in {'auto': (4.0, 14.0), 'schuh': (0.35, 1.4)}[ART])
aussen.inputs['From Min'].default_value = R_AUS[0]; aussen.inputs['From Max'].default_value = R_AUS[1]
aussen.inputs['To Min'].default_value = 0.0; aussen.inputs['To Max'].default_value = 1.0
mb.node_tree.links.new(abst.outputs['Value'], aussen.inputs['Value'])
mb.node_tree.links.new(aussen.outputs['Result'], misch.inputs['Fac'])
mb.node_tree.links.new(bsdf.outputs['BSDF'], misch.inputs[1])
mb.node_tree.links.new(durch.outputs['BSDF'], misch.inputs[2])
ausg = bn.get('Material Output')
mb.node_tree.links.new(misch.outputs['Shader'], ausg.inputs['Surface'])
rau = bn.new('ShaderNodeTexNoise'); rau.inputs['Scale'].default_value = 3.0
rau.inputs['Detail'].default_value = 6
rampe = bn.new('ShaderNodeMapRange')
rampe.inputs['To Min'].default_value = RAUHEIT[0]; rampe.inputs['To Max'].default_value = RAUHEIT[1]
mb.node_tree.links.new(rau.outputs['Fac'], rampe.inputs['Value'])
mb.node_tree.links.new(rampe.outputs['Result'], bsdf.inputs['Roughness'])
boden.data.materials.append(mb)

# ------------------------------------------------------------------ Licht
def flaeche(name, ort, ziel, gr, staerke, kelvin=5600, form='RECTANGLE'):
    l = bpy.data.lights.new(name, 'AREA'); l.shape = form
    l.size = gr[0]; l.size_y = gr[1]; l.energy = staerke
    # Farbtemperatur ueber Blackbody im Lichtknoten
    l.use_nodes = True
    bb = l.node_tree.nodes.new('ShaderNodeBlackbody'); bb.inputs['Temperature'].default_value = kelvin
    em = l.node_tree.nodes.get('Emission')
    l.node_tree.links.new(bb.outputs['Color'], em.inputs['Color'])
    o = bpy.data.objects.new(name, l); sc.collection.objects.link(o)
    o.location = ort
    richtung = Vector(ziel) - Vector(ort)
    o.rotation_euler = richtung.to_track_quat('-Z', 'Y').to_euler()
    return o

# Lichtverknuepfung: Die Modelllichter beleuchten NUR das Modell. Im vierten
# Probebild war der Boden trotz fast schwarzer Farbe milchig hell -- er
# spiegelte unter flachem Winkel das Kantenlicht und die Streiflichter, die
# tief und direkt gegenueber der Kamera stehen. Ein Fotograf haengt dafuer
# schwarze Flaggen; hier bekommt der Boden sein eigenes, schwaches Licht.
nur_modell = bpy.data.collections.new('NurModell')
for o in modell:
    nur_modell.objects.link(o)
nur_boden = bpy.data.collections.new('NurBoden')
nur_boden.objects.link(boden)

def nur(o, coll):
    o.light_linking.receiver_collection = coll
    return o

L = max(groesse.x, groesse.y)          # Laengsausdehnung
H = groesse.z
if AUTO:
    nur(flaeche('Decke', (mitte.x, mitte.y, boden_z + 4.6), (mitte.x, mitte.y, boden_z), (3.2, 6.0), 2600, 5600), nur_modell)
    nur(flaeche('StreifLinks', (mitte.x - 4.2, mitte.y, boden_z + 1.6), (mitte.x, mitte.y, boden_z + 0.5), (0.35, 5.0), 700, 5200), nur_modell)
    nur(flaeche('StreifRechts', (mitte.x + 4.2, mitte.y, boden_z + 1.6), (mitte.x, mitte.y, boden_z + 0.5), (0.35, 5.0), 500, 5200), nur_modell)
    nur(flaeche('Kante', (mitte.x, mitte.y + 5.5, boden_z + 2.2), (mitte.x, mitte.y, boden_z + 0.6), (4.0, 1.2), 900, 6500), nur_modell)
    nur(flaeche('Bodenlicht', (mitte.x, mitte.y, boden_z + 5.0), (mitte.x, mitte.y, boden_z), (5.0, 7.0), BODENLICHT, 5600), nur_boden)
elif WAS == 'schmuck':
    # Schmucklicht: Flache Teile (Zifferblatt, Band) spiegeln bei ~35 Grad
    # Kamerahoehe genau die Richtung 35 Grad hinter dem Objekt. Das Kantenlicht
    # der Schuhbuehne stand dort und legte einen Schleier ueber Blatt und Band
    # (Probe 23.09.2026). Hier bleibt diese Richtung dunkel; Licht kommt von
    # oben (Softbox), hoch hinten (Kante) und als zwei schmale Streifen von der
    # Seite -- die klassischen Hell-Dunkel-Baender auf poliertem Metall.
    s = L
    nur(flaeche('Decke', (mitte.x, mitte.y, boden_z + 6 * s), (mitte.x, mitte.y, boden_z), (3 * s, 2.5 * s), 22 * SK * SK, 5600), nur_modell)
    nur(flaeche('StreifLinks', (mitte.x - 2.2 * s, mitte.y + 0.4 * s, boden_z + 0.9 * s), tuple(mitte), (0.22 * s, 2.2 * s), 20 * SK * SK, 5400), nur_modell)
    nur(flaeche('StreifRechts', (mitte.x + 2.2 * s, mitte.y + 0.4 * s, boden_z + 0.9 * s), tuple(mitte), (0.22 * s, 2.2 * s), 15 * SK * SK, 5600), nur_modell)
    nur(flaeche('Kante', (mitte.x, mitte.y + 1.6 * s, boden_z + 4.4 * s), tuple(mitte), (2.4 * s, 0.35 * s), 12 * SK * SK, 6200), nur_modell)
    nur(flaeche('Fuehrung', (mitte.x - 2.0 * s, mitte.y - 3.0 * s, boden_z + 2.6 * s), tuple(mitte), (1.4 * s, 1.4 * s), 14 * SK * SK, 5200), nur_modell)
    nur(flaeche('Bodenlicht', (mitte.x, mitte.y, boden_z + 6 * s), (mitte.x, mitte.y, boden_z), (5 * s, 5 * s), BODENLICHT, 5600), nur_boden)
elif WAS == 'kueche':
    # Kueche: Fronten stehen senkrecht. Unter der Deckensoftbox der
    # Produktbuehne bekam die Platte achtmal so viel Licht wie die Fronten
    # (Probe 23.09.2026: Keramik 5 % Albedo heller als weisse Fronten im
    # Verhaeltnis) -- wie im Kuechenstudio kommt das Hauptlicht deshalb von
    # vorn links, oben nur noch wenig, dazu Aufheller und Kante.
    s = L
    nur(flaeche('Decke', (mitte.x, mitte.y, boden_z + 3.0 * s), (mitte.x, mitte.y, boden_z), (1.6 * s, 1.2 * s), 14 * SK * SK, 5600), nur_modell)
    nur(flaeche('Fuehrung', (mitte.x - 1.2 * s, mitte.y - 2.6 * s, boden_z + 1.1 * s), tuple(mitte), (1.4 * s, 1.0 * s), 36 * SK * SK, 5400), nur_modell)
    nur(flaeche('Aufheller', (mitte.x + 2.0 * s, mitte.y - 1.6 * s, boden_z + 0.7 * s), tuple(mitte), (1.0 * s, 1.0 * s), 10 * SK * SK, 5800), nur_modell)
    nur(flaeche('Kante', (mitte.x - 1.5 * s, mitte.y + 2.5 * s, boden_z + 1.5 * s), tuple(mitte), (2.0 * s, 0.5 * s), 16 * SK * SK, 6500), nur_modell)
    nur(flaeche('Bodenlicht', (mitte.x, mitte.y, boden_z + 6 * s), (mitte.x, mitte.y, boden_z), (5 * s, 5 * s), BODENLICHT, 5600), nur_boden)
else:
    s = L
    nur(flaeche('Decke', (mitte.x, mitte.y, boden_z + 6 * s), (mitte.x, mitte.y, boden_z), (4 * s, 3 * s), 60 * SK * SK, 5600), nur_modell)
    nur(flaeche('Fuehrung', (mitte.x - 3 * s, mitte.y - 3 * s, boden_z + 2 * s), tuple(mitte), (2 * s, 2 * s), 22 * SK * SK, 5400), nur_modell)
    nur(flaeche('Kante', (mitte.x + 3 * s, mitte.y + 3.5 * s, boden_z + 2.5 * s), tuple(mitte), (2.5 * s, 0.8 * s), 30 * SK * SK, 6500), nur_modell)
    nur(flaeche('Bodenlicht', (mitte.x, mitte.y, boden_z + 6 * s), (mitte.x, mitte.y, boden_z), (5 * s, 5 * s), BODENLICHT, 5600), nur_boden)

# Salon: Der Spiegel zeigte die Deckensoftbox als weisses Viereck (Probe
# 23.09.2026). Am Set haengt man dafuer eine schwarze Flagge -- hier nimmt
# die Lichtverknuepfung den Spiegel als Empfaenger der Studioleuchten aus.
# Er spiegelt weiter den Stuhl, der von denselben Leuchten beleuchtet ist.
if WAS == 'salon':
    ohne_spiegel = bpy.data.collections.new('OhneSpiegel')
    for o in modell:
        if not any(sl.material and sl.material.name.startswith('Spiegel') and not sl.material.name.startswith('Spiegel Licht')
                   for sl in o.material_slots):
            ohne_spiegel.objects.link(o)
    for l in [o for o in sc.objects if o.type == 'LIGHT' and o.name != 'Bodenlicht']:
        l.light_linking.receiver_collection = ohne_spiegel

# ------------------------------------------------------------------ Kamera
KAMERA = {
    # Blickwinkel (Grad, 0 = von vorn, Front zeigt nach -Y), Hoehe, Brennweite
    'auto': dict(winkel=36.0, hoehe=1.05, lens=85.0, ziel_hoehe=0.55, fuellung=0.74),
    # Erste Probe (58 Grad) zeigte die Ferse -- die Spitze liegt bei +X.
    'schuh': dict(winkel=-32.0, hoehe=0.16, lens=100.0, ziel_hoehe=0.06, fuellung=0.60),
}[ART]
# Produktdemos: eigene Standpunkte (Hoehen in Metern ueber dem Boden)
KAMERA = {
    'wein': dict(winkel=-24.0, hoehe=0.26, lens=85.0, ziel_hoehe=0.17, fuellung=0.36),
    # Makro von schraeg oben (~35 Grad): Zifferblatt lesbar, Band laeuft aus dem Bild
    'schmuck': dict(winkel=-15.0, hoehe=0.28, lens=100.0, ziel_hoehe=0.008, fuellung=1.7),
    # Kochinsel: Augenhoehe eines Stehenden (1,55 m), leicht von rechts vorn
    'kueche': dict(winkel=-28.0, hoehe=1.55, lens=50.0, ziel_hoehe=0.50, fuellung=0.70),
    # Gedeckter Tisch: Blick eines Stehenden am Tisch, schraeg von vorn
    'gastro': dict(winkel=-30.0, hoehe=1.40, lens=50.0, ziel_hoehe=0.55, fuellung=0.50),
    # Salonplatz: Stuhl vorn, Spiegel dahinter -- schraeg von rechts vorn
    'salon': dict(winkel=-36.0, hoehe=1.30, lens=35.0, ziel_hoehe=0.95, fuellung=0.34),
    # Sattelzug: von links vorn wie beim Auto, Augenhoehe eines Stehenden
    'lkw': dict(winkel=26.0, hoehe=2.20, lens=50.0, ziel_hoehe=1.80, fuellung=0.95),
}.get(WAS, KAMERA)
for _k in ('winkel', 'hoehe', 'lens', 'ziel_hoehe', 'fuellung'):
    if _k in EXTRA:
        KAMERA[_k] = float(EXTRA[_k])
cam_d = bpy.data.cameras.new('Kamera'); cam_d.sensor_fit = 'HORIZONTAL'
cam_d.sensor_width = 36.0; cam_d.lens = KAMERA['lens']
cam = bpy.data.objects.new('Kamera', cam_d); sc.collection.objects.link(cam); sc.camera = cam
ziel = Vector((mitte.x, mitte.y, boden_z + KAMERA['ziel_hoehe']))
# Abstand so, dass die Laengsausdehnung ~fuellung der Bildbreite belegt
hfov = 2 * math.atan(18.0 / KAMERA['lens'])
sichtbreite = L / KAMERA['fuellung']
abstand = (sichtbreite / 2) / math.tan(hfov / 2)
# Pruefansichten: Ziel (x:y:z, Blender) und Abstand frei waehlbar
if 'ziel' in EXTRA:
    ziel = Vector([float(v) for v in EXTRA['ziel'].split(':')])
if 'abstand' in EXTRA:
    abstand = float(EXTRA['abstand'])
w = math.radians(KAMERA['winkel'])
richt = Vector((-math.sin(w), -math.cos(w), 0))     # von vorn-links
ort = ziel + richt * abstand
ort.z = boden_z + KAMERA['hoehe']
cam.location = ort
cam.rotation_euler = (ziel - ort).to_track_quat('-Z', 'Y').to_euler()
# Tiefenschaerfe: auf die Front gestellt, Blende weit genug zu, dass das
# ganze Objekt scharf bleibt -- nur der Boden dahinter faellt weich ab.
cam_d.dof.use_dof = True
cam_d.dof.focus_distance = (ziel - ort).length
cam_d.dof.aperture_fstop = float(EXTRA.get('blende', 8.0 if AUTO else {'schmuck': 16.0}.get(WAS, 11.0)))

# ------------------------------------------------------------------ Render
r = sc.render
r.engine = 'CYCLES'
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
PROBE = MODUS == 'probe'
r.resolution_x, r.resolution_y = 1600, 900
r.resolution_percentage = int(EXTRA.get('prozent', 40 if PROBE else 100))
sc.cycles.samples = 48 if PROBE else 384
sc.cycles.adaptive_threshold = 0.02 if PROBE else 0.006
sc.cycles.use_denoising = True
sc.cycles.denoiser = 'OPENIMAGEDENOISE'
sc.cycles.max_bounces = 16; sc.cycles.glossy_bounces = 8; sc.cycles.transmission_bounces = 12
sc.cycles.blur_glossy = 0.4
sc.view_settings.view_transform = 'AgX'
sc.view_settings.look = 'AgX - Medium High Contrast'
# Belichtung: Die Serienautos stehen 2 Blenden dunkler als das Konzeptauto.
# Bei 0 war ein weisser Lack flaechig ueberstrahlt und schwarzer Kunststoff
# (Albedo 1,5 %) cremefarben -- gemessen: Stoff mit 3 % Albedo lag bei 0,6
# im Bild, ein weisses Blatt waere achtfach ueberbelichtet gewesen.
# Schmuck: Messung diffus=Band (23.09.2026) -- Navy-Leder mit 5 % Albedo lag
# bei 0 mittelblau im Bild; bei -1,5 Cognac noch hellbraun (183/124/84) -> -2,1.
BELICHTUNG = {'kleinwagen': -2.0, 'mittelklasse': -2.0, 'schmuck': -2.1, 'kueche': -2.3, 'gastro': -1.8, 'salon': -1.8, 'lkw': -1.8}.get(WAS, 0.0)
sc.view_settings.exposure = float(EXTRA.get('belichtung', BELICHTUNG))
r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'; r.image_settings.color_depth = '8'
r.film_transparent = False

# ------------------------------------------------------------------ Varianten
def varianten():
    v = getattr(sc, 'gltf2_KHR_materials_variants_variants', None)
    return [x.name for x in v] if v else []

def variante_setzen(idx):
    """Materialien je Slot nach den Variantendaten des Importers setzen."""
    gesetzt = 0
    for o in modell:
        me = o.data
        daten = getattr(me, 'gltf2_variant_mesh_data', None)
        if not daten:
            continue
        for eintrag in daten:
            for v in eintrag.variants:
                if v.variant.variant_idx == idx:
                    o.material_slots[eintrag.material_slot_index].material = eintrag.material
                    gesetzt += 1
    return gesetzt

# Pruefansicht ohne=Glass+Headliner: Materialien mit diesen Namensanfaengen
# werden voll durchsichtig (keine Spiegelung). So laesst sich messen, welche
# Flaeche einen Bildfehler traegt, statt es zu vermuten.
if 'ohne' in EXTRA:
    for m in bpy.data.materials:
        if any(m.name.startswith(p.replace('_', ' ')) for p in EXTRA['ohne'].split('+')) and m.use_nodes:
            nt_m = m.node_tree; aus_m = next(n for n in nt_m.nodes if n.type == 'OUTPUT_MATERIAL')
            nt_m.links.new(nt_m.nodes.new('ShaderNodeBsdfTransparent').outputs[0], aus_m.inputs['Surface'])

# Polfilter (Standard in der Autofotografie): Er loescht den groessten Teil
# der Spiegelung auf Glas. Ohne ihn stand die Deckensoftbox in der unteren
# Ecke der Frontscheibe als flacher, heller Keil an der A-Saeule -- physikalisch
# richtig, aber genau das, was ein Fotograf am Set wegdreht (Strahlmessung
# 23.09.2026: Spiegelung Richtung Decke; glas=spiegel zeigte den Keil).
# Emuliert ueber die Spiegelstaerke der Glasmaterialien; polfilter=1 = aus.
# Umsetzung als eigener Glas-Shader (duenne Scheibe): Durchsicht ohne
# Versatz + glatte Spiegelung mit Fresnel x POLFILTER. Der erste Versuch
# ueber 'Specular IOR Level' am Principled aenderte bei Transmission 1 nichts
# (Probe pol15 = pol40 = ohne).
POLFILTER = float(EXTRA.get('polfilter', 0.3 if AUTO else 1.0))
if POLFILTER < 1.0:
    for m in bpy.data.materials:
        if not (m.name.startswith('Glass') and m.use_nodes):
            continue
        nt_m = m.node_tree; b_ = nt_m.nodes.get('Principled BSDF')
        if not b_:
            continue
        aus_m = next(n for n in nt_m.nodes if n.type == 'OUTPUT_MATERIAL')
        fr = nt_m.nodes.new('ShaderNodeFresnel'); fr.inputs['IOR'].default_value = b_.inputs['IOR'].default_value
        mal = nt_m.nodes.new('ShaderNodeMath'); mal.operation = 'MULTIPLY'; mal.inputs[1].default_value = POLFILTER
        nt_m.links.new(fr.outputs['Fac'], mal.inputs[0])
        gl = nt_m.nodes.new('ShaderNodeBsdfGlossy'); gl.inputs['Roughness'].default_value = 0.0
        gl.inputs['Color'].default_value = (1, 1, 1, 1)
        if b_.inputs['Transmission Weight'].default_value > 0.5:
            unten = nt_m.nodes.new('ShaderNodeBsdfTransparent')
            unten.inputs['Color'].default_value = b_.inputs['Base Color'].default_value
        else:                                           # Fritte: schwarze Keramik
            unten = nt_m.nodes.new('ShaderNodeBsdfDiffuse')
            unten.inputs['Color'].default_value = b_.inputs['Base Color'].default_value
        mx = nt_m.nodes.new('ShaderNodeMixShader')
        nt_m.links.new(mal.outputs[0], mx.inputs['Fac'])
        nt_m.links.new(unten.outputs[0], mx.inputs[1]); nt_m.links.new(gl.outputs[0], mx.inputs[2])
        nt_m.links.new(mx.outputs[0], aus_m.inputs['Surface'])

# Uhrglas als duenne Scheibe: Ein massives Glas mit Transmission laesst in
# Cycles kein direktes Licht durch (Schattenstrahlen enden am Glas, Kaustik
# ist aus). Das Zifferblatt lag dadurch im Schatten des eigenen Glases --
# weiss diffus kam grau heraus, waehrend das Band daneben voll im Licht lag
# (Messung diffus=Zifferblatt, 23.09.2026). Bei 1,4 mm flachem Saphir ist der
# Versatz unsichtbar; die Spiegelung bleibt mit Fresnel, entspiegelt auf 35 %.
if WAS == 'schmuck' and not EXTRA.get('glas_massiv'):
    for m in bpy.data.materials:
        if not (m.name.startswith('Saphirglas') and m.use_nodes):
            continue
        nt_m = m.node_tree; b_ = nt_m.nodes.get('Principled BSDF')
        aus_m = next(n for n in nt_m.nodes if n.type == 'OUTPUT_MATERIAL')
        fr = nt_m.nodes.new('ShaderNodeFresnel'); fr.inputs['IOR'].default_value = b_.inputs['IOR'].default_value if b_ else 1.77
        mal = nt_m.nodes.new('ShaderNodeMath'); mal.operation = 'MULTIPLY'; mal.inputs[1].default_value = 0.35
        nt_m.links.new(fr.outputs['Fac'], mal.inputs[0])
        # Nur die Oberseite spiegelt voll: Die Unterseite warf den Schriftzug
        # als zweites, versetztes "VECOM" zurueck (Zifferblatt -> Glasunterseite
        # -> Zifferblatt, Poster 23.09.2026). Echte Glaeser sind innen
        # entspiegelt; dort bleibt ein Zehntel. Oben = Flaechennormale nach +Z.
        geo = nt_m.nodes.new('ShaderNodeNewGeometry')
        xyz = nt_m.nodes.new('ShaderNodeSeparateXYZ'); nt_m.links.new(geo.outputs['True Normal'], xyz.inputs[0])
        oben_ = nt_m.nodes.new('ShaderNodeMath'); oben_.operation = 'GREATER_THAN'; oben_.inputs[1].default_value = 0.2
        nt_m.links.new(xyz.outputs['Z'], oben_.inputs[0])
        innen = nt_m.nodes.new('ShaderNodeMath'); innen.operation = 'MULTIPLY_ADD'
        innen.inputs[1].default_value = 0.9; innen.inputs[2].default_value = 0.1       # 0,1 + 0,9 * oben
        nt_m.links.new(oben_.outputs[0], innen.inputs[0])
        mal2 = nt_m.nodes.new('ShaderNodeMath'); mal2.operation = 'MULTIPLY'
        nt_m.links.new(mal.outputs[0], mal2.inputs[0]); nt_m.links.new(innen.outputs[0], mal2.inputs[1])
        mal = mal2
        gl = nt_m.nodes.new('ShaderNodeBsdfGlossy'); gl.inputs['Roughness'].default_value = 0.0
        durch = nt_m.nodes.new('ShaderNodeBsdfTransparent')
        mx = nt_m.nodes.new('ShaderNodeMixShader')
        nt_m.links.new(mal.outputs[0], mx.inputs['Fac'])
        nt_m.links.new(durch.outputs[0], mx.inputs[1]); nt_m.links.new(gl.outputs[0], mx.inputs[2])
        nt_m.links.new(mx.outputs[0], aus_m.inputs['Surface'])

# Pruefansicht glas=spiegel|durch|gerade: Glasmaterialien nur als Spiegelung
# (8 % weiss, glatt), nur als Durchsicht mit Brechung, oder als Durchsicht
# ohne Brechung. Trennt, ob ein Fleck auf der Scheibe gespiegelt oder
# gebrochen hindurch gesehen wird.
if 'glas' in EXTRA:
    for m in bpy.data.materials:
        if m.name.startswith('Glass') and m.use_nodes:
            nt_m = m.node_tree; aus_m = next(n for n in nt_m.nodes if n.type == 'OUTPUT_MATERIAL')
            if EXTRA['glas'] == 'spiegel':
                g_ = nt_m.nodes.new('ShaderNodeBsdfGlossy'); g_.inputs['Roughness'].default_value = 0.0
                g_.inputs['Color'].default_value = (0.08, 0.08, 0.08, 1)
            elif EXTRA['glas'] == 'durch':
                g_ = nt_m.nodes.new('ShaderNodeBsdfRefraction'); g_.inputs['IOR'].default_value = 1.52
                g_.inputs['Roughness'].default_value = 0.0
            else:
                g_ = nt_m.nodes.new('ShaderNodeBsdfTransparent')
            nt_m.links.new(g_.outputs[0], aus_m.inputs['Surface'])

# Pruefansicht nur=innen_+tuer_: nur Objekte mit diesen Namensanfaengen rechnen
if 'nur' in EXTRA:
    for o in modell:
        o.hide_render = not any(o.name.startswith(p) for p in EXTRA['nur'].split('+'))

# Pruefansicht diffus=Band_Navy+...: Materialien mit diesen Namensanfaengen
# werden rein diffus in ihrer Grundfarbe (ohne Glanz, ohne Normalenkarte).
# Trennt, ob eine Flaeche durch Spiegelung oder durch ihre Farbe hell wirkt.
if 'diffus' in EXTRA:
    for m in bpy.data.materials:
        if any(m.name.startswith(p.replace('_', ' ')) for p in EXTRA['diffus'].split('+')) and m.use_nodes:
            nt_m = m.node_tree; aus_m = next(n for n in nt_m.nodes if n.type == 'OUTPUT_MATERIAL')
            b_ = nt_m.nodes.get('Principled BSDF')
            dif = nt_m.nodes.new('ShaderNodeBsdfDiffuse')
            if b_:
                dif.inputs['Color'].default_value = b_.inputs['Base Color'].default_value
                print('DIFFUS', m.name, tuple(round(c, 3) for c in b_.inputs['Base Color'].default_value),
                      'rau', round(b_.inputs['Roughness'].default_value, 3), 'trans', b_.inputs['Transmission Weight'].default_value)
            nt_m.links.new(dif.outputs[0], aus_m.inputs['Surface'])

# Messung strahl=u:v+u:v (Bildanteile, 0 links/oben): Welche Objekte und
# Materialien liegen hinter diesem Pixel, in Reihenfolge? Bis zu acht Treffer
# (durch Glas hindurch). Eingefuehrt 23.09.2026, weil ohne=/nur= den weissen
# Keil an der A-Saeule dreimal nicht eingrenzen konnten -- ein Strahl kann
# nicht raten.
if 'strahl' in EXTRA:
    bpy.context.view_layer.update()
    dg = bpy.context.evaluated_depsgraph_get()
    rahmen = cam.data.view_frame(scene=sc)          # oben-rechts, unten-rechts, unten-links, oben-links
    mw = cam.matrix_world
    for pkt in EXTRA['strahl'].split('+'):
        u, v = (float(x) for x in pkt.split(':'))
        orr, urr, ul, ol = rahmen
        lokal = ol + (orr - ol) * u + (ul - ol) * v
        o = mw.translation.copy(); d = (mw @ lokal - o).normalized()
        treffer = []; start = o.copy()
        for _ in range(8):
            ok, ort_t, nor, idx, ob, _m = sc.ray_cast(dg, start, d)
            if not ok:
                break
            ob_e = ob.evaluated_get(dg)
            try:
                mi = ob_e.data.polygons[idx].material_index
                stoff = ob_e.material_slots[mi].name if ob_e.material_slots else '-'
            except Exception:
                stoff = '?'
            eintrag = f'{ob.name}/{stoff}@{(ort_t - o).length:.3f}m'
            if stoff.startswith('Glass') and len(treffer) == 0:
                # Spiegelung an der ersten Scheibe: Flaechennormale, zur Kamera
                n_ = nor if nor.dot(d) < 0 else -nor
                rd = (d - 2 * d.dot(n_) * n_).normalized()
                ok2, ort2, _n2, idx2, ob2, _m2 = sc.ray_cast(dg, ort_t + n_ * 2e-4, rd)
                if ok2:
                    ob2e = ob2.evaluated_get(dg)
                    try:
                        st2 = ob2e.material_slots[ob2e.data.polygons[idx2].material_index].name
                    except Exception:
                        st2 = '?'
                    eintrag += f' [spiegelt {ob2.name}/{st2}@{(ort2 - ort_t).length:.3f}m]'
                else:
                    eintrag += f' [spiegelt Umgebung, Richtung {tuple(round(c, 2) for c in rd)}]'
            treffer.append(eintrag)
            start = ort_t + d * 2e-4
        print('STRAHL', pkt, ' -> '.join(treffer) or 'nichts')

# freisteller=1: ohne Boden, transparenter Hintergrund (Fahrzeugbild fuer die
# Assistenzanzeige im Kombiinstrument)
if EXTRA.get('freisteller'):
    boden.hide_render = True
    r.film_transparent = True
    r.image_settings.color_mode = 'RGBA'

# Pruefansicht tueren=45: Tueren um so viele Grad an ihren Scharnieren oeffnen
if 'tueren' in EXTRA:
    for o in sc.objects:
        if 'winkel' in o.keys() and 'achse' in o.keys():
            ag = list(o['achse'])
            o.rotation_mode = 'AXIS_ANGLE'
            o.rotation_axis_angle = (-math.radians(float(EXTRA['tueren'])) * float(o['seite']), ag[0], -ag[2], ag[1])

# Pruefansicht lack_met=, lack_rau=: Lackwerte fuer Vergleichsbilder
for m in bpy.data.materials:
    if m.name.startswith('Paint') and m.use_nodes:
        bs = next((n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None)
        if bs and 'lack_met' in EXTRA: bs.inputs['Metallic'].default_value = float(EXTRA['lack_met'])
        if bs and 'lack_rau' in EXTRA: bs.inputs['Roughness'].default_value = float(EXTRA['lack_rau'])

namen = varianten()

# ------------------------------------------------------------------ Film (A5)
# Kurzfilm fuer Instagram/TikTok (Wunsch A5, 24.09.2026): hochkant 1080 x 1920,
# 24 Bilder/s, 10 s. Die Kamera schwenkt 50 Grad um das Produkt und faehrt
# dabei leicht heran; je Drittel eine Variante. Gerechnet mit Cycles auf Uwes
# Rechner (96 Abtastungen + OIDN), danach im Videoschnitt von Blender mit
# grossen, kontrastreichen Titeln zu MP4 gesetzt (Texte in Videos gut lesbar).
FILM_TITEL = {                               # IT in der Lei-Form wie die Seite (24.09.2026)
    'wein': ('Il suo vino in 3D', 'Ihr Wein in 3D'), 'schmuck': ('I suoi gioielli in 3D', 'Ihr Schmuck in 3D'),
    'gastro': ('Il suo ristorante in 3D', 'Ihr Restaurant in 3D'), 'lkw': ('La sua flotta in 3D', 'Ihre Flotte in 3D'),
    'kueche': ('La sua cucina in 3D', 'Ihre Küche in 3D'), 'kleinwagen': ('La sua concessionaria in 3D', 'Ihr Autohaus in 3D'),
    'mittelklasse': ('La sua concessionaria in 3D', 'Ihr Autohaus in 3D'), 'auto': ('La sua concessionaria in 3D', 'Ihr Autohaus in 3D'),
    'schuh': ('Il suo negozio in 3D', 'Ihr Shop in 3D'),
}
if MODUS == 'film':
    FPS, DAUER = 24, float(EXTRA.get('sekunden', 10))
    N = int(FPS * DAUER)
    ordner = os.path.join(P, 'film', WAS); os.makedirs(ordner, exist_ok=True)
    r.resolution_x, r.resolution_y, r.resolution_percentage = 1080, 1920, int(EXTRA.get('prozent', 100))
    sc.cycles.samples = int(EXTRA.get('samples', 96))
    r.use_persistent_data = True                 # Szene bleibt zwischen den Bildern im Speicher
    # Hochkant: dieselbe Brennweite, aber naeher -- das Produkt soll ~85 % der
    # Bildbreite fuellen statt wie im Querformat-Poster KAMERA['fuellung'].
    naeher = min(1.0, KAMERA['fuellung'] / 0.85) if KAMERA['fuellung'] < 0.85 else 1.0
    # Gastro: ganz nah an den Teller -- der ganze Tisch im Hochformat liess die
    # Pasta daumennagelgross (Probefilm 24.09.2026). (Abstand, Zielhoehe, Hoehe)
    nah = {'gastro': (0.50, 0.80, 0.75)}.get(WAS)
    if nah:
        naeher *= nah[0]; ziel = Vector((ziel.x, ziel.y, boden_z + nah[1]))
    radius0 = (Vector((ort.x, ort.y, 0)) - Vector((ziel.x, ziel.y, 0))).length * naeher
    hoehe0 = (ort.z - ziel.z) * (nah[2] if nah else 1.0)
    w0 = math.radians(KAMERA['winkel'])
    reihe = list(range(len(namen))) if namen and not NUR else (liste if namen else [0])
    # Olivenoel gehoert nicht neben ein Weinglas mit Wein (Probefilm 24.09.)
    FILM_OHNE = {'wein': ('olio',)}.get(WAS, ())
    reihe = [i for i in reihe if not (namen and (namen[i].startswith('Innen') or any(o in namen[i].lower() for o in FILM_OHNE)))] or [0]
    for f in range(N):
        t = f / (N - 1)
        e = t * t * (3 - 2 * t)                              # weich an- und auslaufen
        w = w0 + math.radians(-25 + 50 * e)
        rad = radius0 * (1.0 - 0.10 * e)
        pos = Vector((ziel.x - math.sin(w) * rad, ziel.y - math.cos(w) * rad, ziel.z + hoehe0 * (0.92 + 0.10 * e)))
        cam.location = pos
        cam.rotation_euler = (ziel - pos).to_track_quat('-Z', 'Y').to_euler()
        cam_d.dof.focus_distance = (ziel - pos).length
        if namen:
            v = reihe[min(len(reihe) - 1, int(t * len(reihe)))]
            variante_setzen(v)
        pfad = os.path.join(ordner, f'bild-{f:04d}.png')
        if os.path.exists(pfad) and EXTRA.get('neu') != '1':
            continue                                        # abgebrochenen Lauf fortsetzen
        r.filepath = pfad
        bpy.ops.render.render(write_still=True)
        status(was=WAS, modus='film', bild=f, von=N)
        print('FILM', f, '/', N)
    # --- Schnitt: Bildfolge + Titel + Abspann, je Sprache ein MP4
    for sprache, titel in (('it', FILM_TITEL.get(WAS, ('In 3D', 'In 3D'))[0]), ('de', FILM_TITEL.get(WAS, ('In 3D', 'In 3D'))[1])):
        sn = bpy.data.scenes.new(f'Schnitt-{sprache}')
        sn.render.resolution_x, sn.render.resolution_y, sn.render.resolution_percentage = 1080, 1920, 100
        sn.render.fps = FPS; sn.frame_start = 1; sn.frame_end = N
        sn.sequence_editor_create()
        seq = sn.sequence_editor.strips if hasattr(sn.sequence_editor, 'strips') else sn.sequence_editor.sequences
        dateien = sorted(x for x in os.listdir(ordner) if x.startswith('bild-') and x.endswith('.png'))
        # Bildfolge immer auf volle Breite ziehen -- sonst steht eine Probe mit
        # prozent=25 als kleines Kaestchen in der Mitte (gemessen am 24.09.)
        try:
            bild = seq.new_image('Film', os.path.join(ordner, dateien[0]), channel=1, frame_start=1, fit_method='FIT')
        except TypeError:
            bild = seq.new_image('Film', os.path.join(ordner, dateien[0]), channel=1, frame_start=1)
        for d in dateien[1:]:
            bild.elements.append(d)
        try:
            gross = bpy.data.images.load(os.path.join(ordner, dateien[0]), check_existing=True).size[0]
            if gross and gross != 1080:
                bild.transform.scale_x = bild.transform.scale_y = 1080 / gross
        except Exception:
            pass
        # Schrift der Webseite (Archivo 700, Breite 110 %) statt Blenders Monospace
        schrift = None
        for kandidat in (os.path.join(os.path.dirname(P), 'scripts', 'archivo-film.ttf'), r'C:\Windows\Fonts\segoeuib.ttf'):
            if os.path.exists(kandidat):
                schrift = bpy.data.fonts.load(kandidat, check_existing=True); break
        def text(name, inhalt, von, bis, groesse, y, kanal):
            von, bis = max(1, von), min(N + 1, bis)
            # Blender 4.x heisst der Parameter frame_end, ab 5.0 length -- erst das neue probieren
            try:
                ts = seq.new_effect(name, 'TEXT', channel=kanal, frame_start=von, length=bis - von)
            except TypeError:
                ts = seq.new_effect(name, 'TEXT', channel=kanal, frame_start=von, frame_end=bis)
            ts.text = inhalt; ts.font_size = groesse; ts.location = (0.5, y)
            if schrift:
                ts.font = schrift
            ts.color = (1, 1, 1, 1); ts.use_shadow = True; ts.shadow_color = (0, 0, 0, 0.85)
            for a, v in (('alignment_x', 'CENTER'), ('anchor_x', 'CENTER'), ('anchor_y', 'CENTER'), ('wrap_width', 0.94),
                         ('use_box', True), ('box_color', (0.02, 0.03, 0.05, 0.55)), ('box_margin', 0.02)):
                try:
                    setattr(ts, a, v)                       # nicht jede Version kennt jede Eigenschaft
                except Exception:
                    pass
            return ts
        text('Titel', titel, 1, int(FPS * 2.6), 84, 0.86, 2)   # 96 px brach „Ihr Restaurant in / 3D“ (Probe gastro)
        text('Web', 'vecom-design.it', N - int(FPS * 2.4), N + 1, 88, 0.14, 3)
        try:
            sn.render.image_settings.media_type = 'VIDEO'   # ab Blender 5.0 noetig
        except Exception:
            pass
        sn.render.image_settings.file_format = 'FFMPEG'
        sn.render.ffmpeg.format = 'MPEG4'; sn.render.ffmpeg.codec = 'H264'
        sn.render.ffmpeg.constant_rate_factor = 'HIGH'; sn.render.ffmpeg.ffmpeg_preset = 'GOOD'
        sn.render.ffmpeg.gopsize = FPS
        sn.render.filepath = os.path.join(P, 'film', f'{WAS}-{sprache}.mp4')
        sn.view_settings.view_transform = 'Standard'          # die Bilder sind schon fertig belichtet
        with bpy.context.temp_override(scene=sn):
            bpy.ops.render.render(animation=True, scene=sn.name)
        print('FILM FERTIG', sn.render.filepath)
    status(was=WAS, modus='film', schritt='fertig')
    raise SystemExit(0)
kam = {
    'objekt': WAS, 'sensor_breite_mm': 36.0, 'brennweite_mm': cam_d.lens,
    # Blender (Z oben) -> glTF/three (Y oben, Z = -Blender Y)
    'position': [cam.location.x, cam.location.z, -cam.location.y],
    'ziel': [ziel.x, ziel.z, -ziel.y],
    'blende': cam_d.dof.aperture_fstop, 'fokus_m': cam_d.dof.focus_distance,
    'hdri': 'studio_small_03', 'hdri_drehung_grad': DREH_HDRI, 'hdri_staerke': 0.55, 'hdri_schwelle': SCHWELLE,
    'boden_hoehe': boden_z, 'belichtung': sc.view_settings.exposure,
    'aufloesung': [r.resolution_x, r.resolution_y], 'varianten': namen,
}
if not EXTRA:
    with open(os.path.join(AUSGABE, 'kamera.json'), 'w', encoding='utf-8') as f:
        json.dump(kam, f, ensure_ascii=False, indent=1)

liste = list(range(len(namen))) or [0]
# Lacke und Ausstattungen: Aussenposter nur fuer die Lacke; die
# Ausstattungen ("Innen: ...") bekommen im Modus "innen" eigene Bilder.
LACKE = [i for i in liste if not (namen and namen[i].startswith('Innen'))]
INNEN = [i for i in liste if namen and namen[i].startswith('Innen')]
liste = LACKE if MODUS != 'innen' else INNEN
if NUR:
    liste = [i for i in liste if (namen[i] if namen else str(i)) in NUR or str(i) in NUR]
fertig = []
# ------------------------------------------------------------------ Innenraum
# Blick vom Fahrerplatz (Augpunkt nach SAE J941, 95. Perzentil gerundet) auf
# das Cockpit, Brennweite 20 mm. Dieselbe Kamera ist das Ziel der
# Kamerafahrt im Web (kamera-innen.json) -- Bild und Echtzeit decken sich.
KAMERA_INNEN = {
    'kleinwagen': dict(auge=(0.30, 2.42, 1.12), ziel=(0.08, 1.78, 0.86), lens=20.0),
    'mittelklasse': dict(auge=(0.31, 2.76, 1.10), ziel=(0.08, 2.15, 0.85), lens=20.0),
}
if (MODUS == 'innen' or EXTRA.get('kamera') == 'innen') and WAS in KAMERA_INNEN:
    KI = KAMERA_INNEN[WAS]
    halb = (hi.y - lo.y) / 2
    y0 = mitte.y - halb           # Vorderkante in Blender (-Y)
    auge = Vector((KI['auge'][0], y0 + KI['auge'][1], boden_z + KI['auge'][2]))
    ziel_i = Vector((KI['ziel'][0], y0 + KI['ziel'][1], boden_z + KI['ziel'][2]))
    cam.location = auge
    cam.rotation_euler = (ziel_i - auge).to_track_quat('-Z', 'Y').to_euler()
    cam_d.lens = KI['lens']; cam_d.dof.use_dof = False
    # Aufheller im Innenraum: weiche Flaeche unter dem Dach hinter dem Fahrer,
    # wie ein Fotograf sie einhaengt. Nur fuer dieses Bild.
    aufh = flaeche('Innenaufheller', (0.0, y0 + KI['auge'][1] + 0.55, boden_z + 1.28), tuple(ziel_i), (0.9, 0.5), 18.0, 5200)
    nur(aufh, nur_modell)
    with open(os.path.join(AUSGABE, 'kamera-innen.json'), 'w', encoding='utf-8') as f:
        json.dump({'position': [auge.x, auge.z, -auge.y], 'ziel': [ziel_i.x, ziel_i.z, -ziel_i.y], 'brennweite_mm': cam_d.lens,
                   'sensor_breite_mm': 36.0, 'varianten': [namen[i] for i in INNEN]}, f, ensure_ascii=False, indent=1)
for i in (liste if MODUS not in ('boden', 'umgebung') else []):
    n = variante_setzen(i) if namen else 0
    name = (namen[i] if namen else 'standard').lower().replace(' ', '-')
    art = 'innen' if MODUS == 'innen' else ('probe' if PROBE else 'poster')
    name = name.replace('innen:-', '')
    pfad = os.path.join(AUSGABE, f"{art}-{name}{EXTRA.get('name', '')}.png")
    status(was=WAS, modus=MODUS, variante=name, schritt='rendert', fertig=fertig)
    t0 = time.time()
    r.filepath = pfad
    bpy.ops.render.render(write_still=True)
    fertig.append({'variante': name, 'datei': os.path.basename(pfad), 'sekunden': round(time.time() - t0, 1), 'slots': n})
    print('GERECHNET', pfad, round(time.time() - t0, 1), 's')
# ------------------------------------------------------------------ Boden backen
# Die Webseite bekommt den Boden als Bild: von oben gerechnet, mit Lichtinsel,
# Kontaktschatten und dem Auslauf ins Transparente -- genau der Boden des
# Posters. In Echtzeit gaebe es dafuer kein Gegenstueck: three.js kennt keine
# Lichtverknuepfung, ein Bodenlicht wuerde dort auch das Modell aufhellen.
def boden_backen():
    gr = {'auto': 16.0, 'schuh': 1.6}[ART] * SK
    oc = bpy.data.cameras.new('Oben'); oc.type = 'ORTHO'; oc.ortho_scale = gr
    ob = bpy.data.objects.new('Oben', oc); sc.collection.objects.link(ob)
    ob.location = (mitte.x, mitte.y, boden_z + 30); ob.rotation_euler = (0, 0, 0)
    for o in modell:
        o.visible_camera = False           # wirft Schatten, ist aber nicht im Bild
    alt_cam, alt_x, alt_y, alt_p = sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage
    sc.camera = ob; r.resolution_x = r.resolution_y = 1024; r.resolution_percentage = 100
    r.film_transparent = True; r.image_settings.color_mode = 'RGBA'
    sc.cycles.samples = 256 if not PROBE else 48
    # Gebacken wird die BELEUCHTUNG des Bodens, nicht sein Aussehen: weisser,
    # glanzloser Boden, neutrale Ansicht (Standard statt AgX), 16 Bit. Der
    # erste Versuch backte das Aussehen -- von oben spiegelte der Boden dabei
    # das Bodenlicht direkt in die Kamera und wurde viermal zu hell. Den Glanz
    # rechnet die Webseite selbst, er haengt am Blickwinkel.
    alt_farbe = tuple(bsdf.inputs['Base Color'].default_value)
    bsdf.inputs['Base Color'].default_value = (0.08, 0.08, 0.08, 1)   # Albedo 0,8 lief ueber 1 und wurde abgeschnitten
    for l in list(glanz.outputs['Value'].links):
        mb.node_tree.links.remove(l)
    bsdf.inputs['Specular IOR Level'].default_value = 0.0
    alt_view, alt_look = sc.view_settings.view_transform, sc.view_settings.look
    sc.view_settings.view_transform = 'Standard'; sc.view_settings.look = 'None'
    r.image_settings.color_depth = '16'
    pfad = os.path.join(AUSGABE, 'boden.png'); r.filepath = pfad
    bpy.ops.render.render(write_still=True)
    r.image_settings.color_depth = '8'
    sc.view_settings.view_transform, sc.view_settings.look = alt_view, alt_look
    bsdf.inputs['Base Color'].default_value = alt_farbe
    mb.node_tree.links.new(glanz.outputs['Value'], bsdf.inputs['Specular IOR Level'])
    for o in modell:
        o.visible_camera = True
    sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage = alt_cam, alt_x, alt_y, alt_p
    r.film_transparent = False; r.image_settings.color_mode = 'RGB'
    # Blender (x, y) -> three (x, -z); die Bildoberkante zeigt nach Blender +y
    return {'datei': 'boden.png', 'groesse_m': gr, 'mitte': [mitte.x, boden_z, -mitte.y], 'albedo_gebacken': 0.08, 'albedo_echt': 0.006, 'ansicht': 'Standard', 'insel_m': list(R_INSEL), 'auslauf_m': list(R_AUS), 'glanz_max': GLANZ_MAX, 'rauheit': list(RAUHEIT)}

# ------------------------------------------------------------------ Umgebung backen
# Die Webseite hat keine Flaechenlichter: RectAreaLight kann keinen
# Deckendiffusor von 3,2 x 6 m als weiches Band auf dem Lack zeichnen, und
# gerichtete Lichter machen daraus einen Punkt. Deshalb wird das ganze
# Studio -- HDRI plus die vier Modelllichter -- aus der Mitte des Modells als
# Rundumbild gerechnet. Im Browser ist das die Umgebung des Modells: Glanz
# UND Beleuchtung kommen aus derselben Quelle wie im Poster.
# Der Boden bekommt dort eine eigene Umgebung ohne die Modelllichter -- er
# ist auch hier per Lichtverknuepfung von ihnen getrennt.
def umgebung_backen(mit_lichtern=True, name='umgebung.exr'):
    for o in modell:
        o.hide_render = True
    boden.hide_render = True
    # Welt fuer Kamerastrahlen: HDRI statt Seitengrund
    for l in list(mix.inputs['Fac'].links):
        nt.links.remove(l)
    mix.inputs['Fac'].default_value = 0.0
    ersatz = []
    for lo in [o for o in sc.objects if o.type == 'LIGHT']:
        l = lo.data
        if lo.name == 'Bodenlicht' or not mit_lichtern:
            lo.hide_render = True; continue
        # Flaechenlicht -> leuchtende Flaeche gleicher Groesse. Leuchtdichte
        # L = P / (pi * A), einseitig wie das Blender-Flaechenlicht.
        bpy.ops.mesh.primitive_plane_add(size=1)
        e = bpy.context.active_object; e.name = 'Ersatz_' + lo.name
        e.matrix_world = lo.matrix_world.copy()
        e.scale = (l.size, l.size_y, 1)
        me = bpy.data.materials.new('Ersatz_' + lo.name); me.use_nodes = True
        nn = me.node_tree.nodes; nn.clear()
        em = nn.new('ShaderNodeEmission'); bb = nn.new('ShaderNodeBlackbody'); ao = nn.new('ShaderNodeOutputMaterial')
        bb.inputs['Temperature'].default_value = l.node_tree.nodes['Blackbody'].inputs['Temperature'].default_value
        em.inputs['Strength'].default_value = l.energy / (math.pi * l.size * l.size_y)
        me.node_tree.links.new(bb.outputs['Color'], em.inputs['Color'])
        me.node_tree.links.new(em.outputs['Emission'], ao.inputs['Surface'])
        e.data.materials.append(me)
        # Flaechenlicht strahlt entlang -Z; die Ebene leuchtet beidseitig --
        # die Rueckseite schaut ohnehin vom Modell weg.
        lo.hide_render = True; ersatz.append(e)
    pc = bpy.data.cameras.new('Rund'); pc.type = 'PANO'
    pc.panorama_type = 'EQUIRECTANGULAR'
    po = bpy.data.objects.new('Rund', pc); sc.collection.objects.link(po)
    po.location = (mitte.x, mitte.y, boden_z + groesse.z * 0.45)
    # Bildmitte = Blickrichtung -Z der Kamera; so gedreht, dass sie nach +X
    # schaut und oben oben bleibt (Blender-Konvention fuer Welt-Rundumbilder)
    po.rotation_euler = (math.radians(90), 0, math.radians(-90))
    alt = (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.image_settings.file_format, r.image_settings.color_depth, sc.view_settings.view_transform, sc.view_settings.look, sc.cycles.samples)
    sc.camera = po; r.resolution_x, r.resolution_y, r.resolution_percentage = 2048, 1024, 100
    r.image_settings.file_format = 'OPEN_EXR'; r.image_settings.color_depth = '32'
    sc.view_settings.view_transform = 'Standard'; sc.view_settings.look = 'None'
    sc.cycles.samples = 128
    pfad = os.path.join(AUSGABE, name); r.filepath = pfad
    bpy.ops.render.render(write_still=True)
    (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.image_settings.file_format, r.image_settings.color_depth, sc.view_settings.view_transform, sc.view_settings.look, sc.cycles.samples) = alt
    for e in ersatz:
        bpy.data.objects.remove(e)
    for lo in [o for o in sc.objects if o.type == 'LIGHT']:
        lo.hide_render = False
    for o in modell:
        o.hide_render = False
    boden.hide_render = False
    nt.links.new(weg.outputs['Is Camera Ray'], mix.inputs['Fac'])
    return {'datei': name, 'ort': [po.location.x, po.location.z, -po.location.y]}

if MODUS == 'umgebung':
    kam['umgebung'] = umgebung_backen(True, 'umgebung.exr')
    kam['umgebung_boden'] = umgebung_backen(False, 'umgebung-boden.exr')
    with open(os.path.join(AUSGABE, 'kamera.json'), 'w', encoding='utf-8') as f:
        json.dump(kam, f, ensure_ascii=False, indent=1)

if MODUS in ('voll', 'boden'):
    kam['boden'] = boden_backen()
    with open(os.path.join(AUSGABE, 'kamera.json'), 'w', encoding='utf-8') as f:
        json.dump(kam, f, ensure_ascii=False, indent=1)
status(was=WAS, modus=MODUS, schritt='fertig', fertig=fertig, masse=[round(x, 3) for x in groesse], boden=boden_z)
if not PROBE:
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(P, f'{WAS}-studio.blend'))
print('STUDIO FERTIG')
