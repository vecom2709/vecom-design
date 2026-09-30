"""Branchen-Demos am echten Ort (29.09.2026, Uwe: Ja zu R1-R3).

Bisher standen die Demos im schwarzen Studio (branchen_studio.py). Ein Foto
hat aber immer einen Ort: Licht mit Herkunft, einen Boden, Raum dahinter.
Hier steht das Modell in einer echten Aufnahme (HDRI von Poly Haven, CC0):
  - die HDRI ist Licht UND sichtbarer Hintergrund (unscharf wie beim Foto),
  - ein Schattenfaenger-Boden legt Kontaktschatten auf den Boden der Aufnahme,
  - Kamera auf Aufnahmehoehe der HDRI, damit Boden und Horizont stimmen,
  - echte Brennweite, offene Blende, Cycles auf der GPU (OptiX), AgX.

Aufruf (headless):
  blender -b -P branchen_ort.py -- gastro probe dreh=120
  blender -b -P branchen_ort.py -- gastro reihe            (8 Drehungen, klein)
  blender -b -P branchen_ort.py -- gastro voll dreh=120
"""
import bpy, json, os, sys, math, time, random
from mathutils import Vector, Matrix

P = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
Q = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\quellen\polyhaven'
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
WAS = argv[0] if argv else 'gastro'
MODUS = argv[1] if len(argv) > 1 else 'probe'
EXTRA = {k: v for k, v in (t.split('=', 1) for t in argv[2:] if '=' in t)}
NUR = [t for t in argv[2:] if '=' not in t] or None

# Je Demo: Ort (HDRI), Aufnahmehoehe der HDRI, Kamera, Zubehoer
ORTE = {
    'gastro': dict(hdri='bush_restaurant_4k.exr', hdri_web='bush_restaurant_2k.hdr', dreh=120.0, staerke=1.0,
                   kamera=dict(winkel=-30.0, hoehe=1.45, lens=50.0, ziel_hoehe=0.78, fuellung=0.62, blende=2.8),
                   stuehle='dining_chair_02', stoff='rough_linen', belichtung=0.0),
    # Wein: im Gewoelbekeller auf einem alten Holztisch. Kamerahoehen gelten
    # ab der Tischplatte (Unterlage), nicht ab dem Boden.
    'wein': dict(hdri='castle_zavelstein_cellar_4k.hdr', dreh=0.0, staerke=1.0,
                 kamera=dict(winkel=-24.0, hoehe=0.40, lens=85.0, ziel_hoehe=0.17, fuellung=0.46, blende=2.8),
                 unterlage='WoodenTable_01', unterlage_versatz=(0.0, 0.10), unterlage_hoehe=0.76, behalten=('weinglas_wein',), flagge=dict(breite=5.0, hoehe=1.6, abstand=0.25), belichtung=0.0),
    # Schmuck: beim Juwelier auf einer polierten Marmorplatte, Tageslicht
    # durchs Fenster (cayley_interior, Drehung 315: Fenster links hinten,
    # Reihe 29.09.2026). Makro 100 mm, erhoehter Blick.
    'schmuck': dict(hdri='cayley_interior_4k.exr', dreh=315.0, staerke=1.0,
                    kamera=dict(winkel=-12.0, hoehe=0.30, lens=100.0, ziel_hoehe=0.008, fuellung=0.78, blende=5.6),
                    wb=(0.85, 1.056, 1.18),
                    unterlage='WoodenTable_01', unterlage_hoehe=0.76,
                    stein=dict(ordner=r'ambientcg\Marble012', farbe='Marble012_2K-JPG_Color.jpg', rauheit='Marble012_2K-JPG_Roughness.jpg',
                               normal='Marble012_2K-JPG_NormalGL.jpg', groesse=(0.42, 0.30, 0.022), kachel_m=0.5, rau=(0.04, 0.18)),
                    belichtung=0.6),
    # Kueche: die Kochinsel steht in einem hellen Wohnraum mit Holzboden,
    # Tageslicht (Auswahl per Reihe unter drei Aufnahmen).
    # Loft und Kueche mit Hintergrundraum liessen die Insel schweben; im
    # hellen, leeren Haus (lebombo) steht sie mit echtem Eichenboden.
    # Boden 16 m: Bei 10 m sah man links seine Kante vor der Wand (Probe
    # fb1), bei 40 m wurde er zur endlosen Flaeche vor einer Wand wie im
    # Leerraum (fb2) -- 16 m endet etwa dort, wo in der Aufnahme die Wand
    # auf den Boden trifft.
    # Autos (29.09.2026): Sportwagen auf der Bergstrasse (red_hill_curve,
    # Drehung 225), Mittelklasse in einer Altstadtstrasse Palermos
    # (quattro_canti, 270), Kleinwagen auf der Wohnstrasse
    # (suburban_parking_area, 240); alle CC0. Andere Drehungen der Reihe
    # verworfen: Hecken, Denkmaeler oder parkende Autos direkt vor der Kamera.
    'auto': dict(hdri='red_hill_curve_4k.exr', dreh=225.0, staerke=1.0, aufhellung=0.4, schatten_m=11.0,
                 kamera=dict(winkel=-35.0, hoehe=1.5, lens=85.0, ziel_hoehe=0.55, fuellung=0.62, blende=5.6),
                 belichtung=0.0),
    'mittelklasse': dict(hdri='quattro_canti_4k.exr', dreh=270.0, staerke=1.0,
                         kamera=dict(winkel=-35.0, hoehe=1.15, lens=70.0, ziel_hoehe=0.65, fuellung=0.62, blende=5.6),
                         belichtung=0.0),
    'kleinwagen': dict(hdri='suburban_parking_area_4k.exr', dreh=240.0, staerke=1.0,
                       kamera=dict(winkel=-35.0, hoehe=1.15, lens=70.0, ziel_hoehe=0.65, fuellung=0.62, blende=5.6),
                       belichtung=0.0),
    'kueche': dict(hdri='lebombo_4k.exr', dreh=45.0, staerke=1.0,
                   kamera=dict(winkel=-28.0, hoehe=1.55, lens=50.0, ziel_hoehe=0.55, fuellung=0.5, blende=4.0),
                   fussboden=dict(ordner=r'polyhaven\laminate_floor_02', farbe='laminate_floor_02_diff_2k.jpg',
                                  rauheit='laminate_floor_02_rough_2k.jpg', normal='laminate_floor_02_nor_gl_2k.jpg',
                                  groesse=16.0, kachel_m=2.0),
                   belichtung=0.3),
    # Schuh: auf einem Betonblock (Sitzbank aus Waschbeton) am Potsdamer
    # Platz, bedeckter Himmel -- weiches Licht wie eine riesige Softbox,
    # Strasse und Passanten in der Unschaerfe (Drehung 45, Reihe 29.09.2026).
    # Beton granular_concrete (CC0, 2,4 m je Kachel); smooth_concrete_floor
    # war rotbraun wie Granit.
    'schuh': dict(hdri='potsdamer_platz_4k.exr', dreh=45.0, staerke=1.0,
                  kamera=dict(winkel=-35.0, hoehe=0.16, lens=85.0, ziel_hoehe=0.06, fuellung=0.6, blende=5.6),
                  stein=dict(ordner=r'polyhaven\granular_concrete', farbe='granular_concrete_diff_2k.jpg',
                             rauheit='granular_concrete_rough_2k.jpg', normal='granular_concrete_nor_gl_2k.jpg',
                             groesse=(1.6, 0.5, 0.45), kachel_m=2.4, rau=(0.55, 0.9), fase=0.006),
                  belichtung=0.0),
    # Salon (30.09.2026): Friseurstuhl in einem hellen Laden statt im Studio.
    # Reihe decor_shop (dunkel, Ziegel, wirkte altmodisch) gegen comfy_cafe:
    # gewaehlt comfy_cafe, Drehung 315 -- weisse Ziegelwand, Fenster mit
    # Gruen. Boden echt (Marmor), damit der Stuhl steht statt schwebt.
    'salon': dict(hdri='comfy_cafe_4k.exr', dreh=315.0, staerke=1.0,
                  kamera=dict(winkel=-32.0, hoehe=1.25, lens=50.0, ziel_hoehe=0.55, fuellung=0.55, blende=4.0),
                  fussboden=dict(ordner=r'polyhaven\marble_01', farbe='marble_01_diff_2k.jpg',
                                 rauheit='marble_01_rough_2k.jpg', normal='marble_01_nor_gl_2k.jpg',
                                 groesse=12.0, kachel_m=1.2),
                  belichtung=0.0),
    # LKW: Sattelzug auf einem asphaltierten Hof mit Halle (driving_school,
    # Drehung 315; tank_farm und Landstrasse verworfen, Reihe 29.09.2026).
    'lkw': dict(hdri='driving_school_4k.exr', dreh=315.0, staerke=1.0, schmutz=dict(staerke=1.0, bis=1.25),
                kamera=dict(winkel=-26.0, hoehe=2.2, lens=50.0, ziel_hoehe=1.8, fuellung=0.9, blende=8.0),
                belichtung=0.0),
}
O = ORTE[WAS]
if 'hdri' in EXTRA:              # Ortsvergleich: andere Aufnahme probeweise
    O['hdri'] = EXTRA['hdri']
for k in ('dreh', 'staerke', 'belichtung', 'aufhellung'):
    if k in EXTRA:
        O[k] = float(EXTRA[k])
K = dict(O['kamera'])
for k in ('winkel', 'hoehe', 'lens', 'ziel_hoehe', 'fuellung', 'blende'):
    if k in EXTRA:
        K[k] = float(EXTRA[k])

AUSGABE = os.path.join(P, 'render', WAS + '-ort')
os.makedirs(AUSGABE, exist_ok=True)
STATUS = os.path.join(P, f'status-{WAS}-ort.json')

def status(**k):
    k['zeit'] = time.strftime('%H:%M:%S')
    with open(STATUS, 'w', encoding='utf-8') as f:
        json.dump(k, f, ensure_ascii=False)

# ------------------------------------------------------------------ Modell
bpy.ops.wm.read_factory_settings(use_empty=True)
sc = bpy.context.scene
# Quelle wie im Studio (branchen_studio.py): der Sportwagen ohne Fremdlogos
QUELLE = {'auto': 'auto-ohne-logos.glb'}.get(WAS, f'{WAS}.glb')
AUTO = WAS in ('auto', 'kleinwagen', 'mittelklasse')
bpy.ops.import_scene.gltf(filepath=os.path.join(P, 'quelle', QUELLE))
for _o in [o for o in sc.objects if o.name.startswith('varianten_traeger')]:
    bpy.data.objects.remove(_o, do_unlink=True)
# Das Foto zeigt den gedeckten Tisch; Speisen kommen nur im Web (nur_web)
# Ausnahme je Ort: Wein im Glas bleibt -- ein leeres Glas vor der unscharfen
# Kellerwand las sich wie Milch im Glas (Probe 29.09.2026).
_behalten = tuple(ORTE.get(WAS, {}).get('behalten', ())) or ('\0',)
for _o in [o for o in sc.objects if o.get('nur_web') and not o.name.startswith(_behalten)]:
    bpy.data.objects.remove(_o, do_unlink=True)
# Nur zur Fehlersuche: verstecke=name1+name2 blendet Objekte fuer die Kamera aus
for _n in filter(None, EXTRA.get('verstecke', '').split('+')):
    for _o in sc.objects:
        if _o.name.startswith(_n):
            _o.visible_camera = False
modell = [o for o in sc.objects if o.type == 'MESH']
produkt_wurzeln = [o for o in sc.objects if o.parent is None]

def huelle(objs):
    lo = Vector((1e9,) * 3); hi = Vector((-1e9,) * 3)
    for o in objs:
        for c in o.bound_box:
            w = o.matrix_world @ Vector(c)
            lo = Vector(map(min, lo, w)); hi = Vector(map(max, hi, w))
    return lo, hi

def tiefster_punkt(objs):
    z = 1e9
    for o in objs:
        mw = o.matrix_world
        for v in o.data.vertices:
            z = min(z, (mw @ v.co).z)
    return z

lo, hi = huelle(modell)
# Auto: Aufstandspunkt sind die Reifen, nicht Unterboden oder Achsen
_reifen = [o for o in modell if any(sl.material and sl.material.name == 'Tiretread' for sl in o.material_slots)] if AUTO else []
boden_z = tiefster_punkt(_reifen or modell)
mitte = (lo + hi) / 2
groesse = hi - lo
print('BODEN', round(boden_z, 4), 'MASSE', [round(x, 3) for x in groesse])

# ------------------------------------------------------------------ Unterlage (Tisch, Poly Haven)
# Kleine Produkte stehen nicht auf dem Boden, sondern auf einem Tisch. Der
# Tisch kommt als echtes Modell (CC0) an seinen Platz, das Produkt wird auf
# die Platte gehoben. Kamerahoehen gelten dann ab der Platte (basis_z).
def gltf_laden(name):
    vorher = set(sc.objects)
    bpy.ops.import_scene.gltf(filepath=os.path.join(Q, name, f'{name}_2k.gltf'))
    neu = [o for o in sc.objects if o not in vorher]
    return neu, [o for o in neu if o.parent is None]

stuhl_objs = []            # Zubehoer: Stuehle, Tisch ... (im Web: zubehoer.glb)
basis_z = boden_z
unterlage_box = None
if O.get('unterlage'):
    neu, wurzel = gltf_laden(O['unterlage'])
    tm = [o for o in neu if o.type == 'MESH']
    halter_t = bpy.data.objects.new('unterlage', None); sc.collection.objects.link(halter_t)
    for w_ in wurzel:
        w_.parent = halter_t
    bpy.context.view_layer.update()
    lt, ht = huelle(tm)
    # Tischhoehe wie ein echter Esstisch: Das Modell ist ein niedriger Tisch
    # (55 cm); nur in der Hoehe gestreckt, die Platte bleibt wie sie ist.
    if O.get('unterlage_hoehe'):
        halter_t.scale.z = O['unterlage_hoehe'] / max(0.01, ht.z - lt.z)
        bpy.context.view_layer.update()
        lt, ht = huelle(tm)
    vx, vy = O.get('unterlage_versatz', (0.0, 0.0))
    halter_t.location = (mitte.x - (lt.x + ht.x) / 2 + vx, mitte.y - (lt.y + ht.y) / 2 + vy, boden_z - lt.z)
    bpy.context.view_layer.update()
    lt, ht = huelle(tm)
    platte = ht.z
    for w_ in produkt_wurzeln:
        w_.location.z += platte - boden_z
    bpy.context.view_layer.update()
    lo, hi = huelle(modell); mitte = (lo + hi) / 2; groesse = hi - lo
    basis_z = platte
    unterlage_box = (lt, ht)
    stuhl_objs += tm
    print('UNTERLAGE', O['unterlage'], 'Platte', round(platte, 3), 'Masse', [round(x, 3) for x in (ht - lt)])

# Kleine Stuecke (Schmuck) liegen beim Juwelier auf einer Steinplatte: eine
# echte Platte mit Fase und Marmortextur (Poly Haven, CC0) auf dem Tisch,
# das Produkt darauf. UV nach realer Groesse, damit die Aederung stimmt.
# (Erst marble_01 von Poly Haven: das sind Bodenfliesen mit Fugen -- auf
# einer Platte sah man das Fugenraster, Probe 29.09.2026. Jetzt ambientCG
# Marble012, durchgehender grauer Marmor, CC0.)
if O.get('stein'):
    import bmesh
    ST = O['stein']
    sw, sd, sh = ST['groesse']
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= sw; v.co.y *= sd; v.co.z = (v.co.z + 0.5) * sh
    bmesh.ops.bevel(bm, geom=list(bm.edges), offset=ST.get('fase', 0.0015), segments=3, affect='EDGES', profile=0.5)
    uvl = bm.loops.layers.uv.new('UVMap')
    kachel = ST.get('kachel_m', 0.6)
    for f in bm.faces:
        n = f.normal
        for l in f.loops:
            c = l.vert.co
            if abs(n.z) > 0.5:
                l[uvl].uv = (c.x / kachel + 0.37, c.y / kachel + 0.21)
            elif abs(n.x) > abs(n.y):
                l[uvl].uv = (c.y / kachel + 0.21, c.z / kachel)
            else:
                l[uvl].uv = (c.x / kachel + 0.37, c.z / kachel)
    me = bpy.data.meshes.new('stein'); bm.to_mesh(me); bm.free()
    for poly in me.polygons:
        poly.use_smooth = True
    stein = bpy.data.objects.new('stein', me); sc.collection.objects.link(stein)
    vx, vy = ST.get('versatz', (0.0, 0.0))
    stein.location = (mitte.x + vx, mitte.y + vy, basis_z)
    stein.rotation_euler.z = math.radians(ST.get('drehung', 0.0))
    ms = bpy.data.materials.new('Stein'); ms.use_nodes = True
    nts = ms.node_tree; bs = nts.nodes.get('Principled BSDF')
    def _bild(n, farbe=False):
        im = bpy.data.images.load(os.path.join(os.path.dirname(Q), ST['ordner'], n), check_existing=True)
        if not farbe:
            im.colorspace_settings.name = 'Non-Color'
        t = nts.nodes.new('ShaderNodeTexImage'); t.image = im
        return t
    t_d = _bild(ST['farbe'], True)
    t_r = _bild(ST['rauheit'])
    t_n = _bild(ST['normal'])
    nm = nts.nodes.new('ShaderNodeNormalMap'); nm.inputs['Strength'].default_value = 0.35
    nts.links.new(t_d.outputs['Color'], bs.inputs['Base Color'])
    # Polierter Stein: die Textur-Rauheit ist fuer den Boden gedacht --
    # hier poliert (0,06 .. 0,16), die Variation bleibt erhalten.
    kr = nts.nodes.new('ShaderNodeMapRange')
    kr.inputs['To Min'].default_value = ST.get('rau', (0.06, 0.16))[0]; kr.inputs['To Max'].default_value = ST.get('rau', (0.06, 0.16))[1]
    nts.links.new(t_r.outputs['Color'], kr.inputs['Value']); nts.links.new(kr.outputs['Result'], bs.inputs['Roughness'])
    nts.links.new(t_n.outputs['Color'], nm.inputs['Color']); nts.links.new(nm.outputs['Normal'], bs.inputs['Normal'])
    bs.inputs['Coat Weight'].default_value = 0.0
    stein.data.materials.append(ms)
    bpy.context.view_layer.update()
    lt, ht = huelle([stein])
    for w_ in produkt_wurzeln:
        w_.location.z += ht.z - basis_z
    bpy.context.view_layer.update()
    lo, hi = huelle(modell); mitte = (lo + hi) / 2; groesse = hi - lo
    basis_z = ht.z
    unterlage_box = (lt, ht)          # Kontaktschatten liegt auf dem Stein
    stuhl_objs.append(stein)
    print('STEIN', ST['ordner'], 'Oberkante', round(basis_z, 3))

# ------------------------------------------------------------------ Welt = der Ort
welt = bpy.data.worlds.new('Ort'); sc.world = welt
welt.use_nodes = True
nt = welt.node_tree; nt.nodes.clear()
aus = nt.nodes.new('ShaderNodeOutputWorld')
env = nt.nodes.new('ShaderNodeTexEnvironment')
env.image = bpy.data.images.load(os.path.join(Q, O['hdri']))
env.interpolation = 'Cubic'
kopp = nt.nodes.new('ShaderNodeTexCoord'); dreh = nt.nodes.new('ShaderNodeMapping')
dreh.inputs['Rotation'].default_value[2] = math.radians(O['dreh'])
nt.links.new(kopp.outputs['Generated'], dreh.inputs['Vector'])
nt.links.new(dreh.outputs['Vector'], env.inputs['Vector'])
hg = nt.nodes.new('ShaderNodeBackground'); hg.inputs['Strength'].default_value = O['staerke']
# Weissabgleich wie an der Kamera: Das Abendlicht in cayley_interior machte
# den grauen Marmor rosa-braun (Probe schmuck-b: R/B 1,17 in sRGB; c: noch
# leicht magenta, G 0,97). Die
# Korrektur sitzt am Licht selbst, damit Foto und Web-Rundumbild gleich sind.
if O.get('wb'):
    wbn = nt.nodes.new('ShaderNodeVectorMath'); wbn.operation = 'MULTIPLY'
    wbn.inputs[1].default_value = tuple(O['wb'])
    nt.links.new(env.outputs['Color'], wbn.inputs[0]); nt.links.new(wbn.outputs['Vector'], hg.inputs['Color'])
else:
    nt.links.new(env.outputs['Color'], hg.inputs['Color'])
nt.links.new(hg.outputs['Background'], aus.inputs['Surface'])

# ------------------------------------------------------------------ Boden (Schattenfaenger)
# Der Boden der Aufnahme bleibt sichtbar; der Faenger legt nur Schatten und
# Kontaktschatten darauf. Kein eigener Boden, der sich vom Foto abhebt.
bpy.ops.mesh.primitive_plane_add(size=30, location=(mitte.x, mitte.y, boden_z))
faenger = bpy.context.active_object; faenger.name = 'Schattenfaenger'
faenger.is_shadow_catcher = True
mf = bpy.data.materials.new('Faenger'); mf.use_nodes = True
mf.node_tree.nodes.get('Principled BSDF').inputs['Roughness'].default_value = 0.6
faenger.data.materials.append(mf)

# Aufhellung nur fuer den Schattenfaenger (Autos, 29.09.2026): Unter klarem
# Himmel ist die Sonne der Aufnahme so stark, dass der Faenger den Schatten
# vor dem Sportwagen fast schwarz machte (Probe auto-b: 8 gegen 88 in sRGB,
# gemessen; echter Asphalt im Schatten liegt bei 15-25 % des Sonnenlichts).
# Ein Sonnenlicht von oben, das per Lichtverknuepfung NUR den Faenger trifft
# und von nichts verdeckt wird, geht mit und ohne Auto gleich ein -- es hebt
# nur das Verhaeltnis, Auto und Hintergrund bleiben unberuehrt. Staerke als
# Anteil der gemessenen Bestrahlung der Aufnahme auf eine waagerechte Flaeche
# (auto: 0,4 ergibt 18 % im Schatten, gemessen in Probe auto-e).
if O.get('aufhellung'):
    import numpy as np
    im_ = env.image; bw_, bh_ = im_.size
    px_ = np.empty(bw_ * bh_ * 4, dtype=np.float32); im_.pixels.foreach_get(px_)
    px_ = px_.reshape(bh_, bw_, 4)[:, :, :3] @ np.array([0.2126, 0.7152, 0.0722], dtype=np.float32)
    # Blender-Bilder beginnen unten: Zeile 0 = Nadir. Obere Haelfte = Himmel.
    th_ = (np.arange(bh_) + 0.5) / bh_ * math.pi - math.pi / 2       # Hoehenwinkel
    oben_ = th_ > 0
    gew_ = np.sin(th_) * np.cos(th_) * (math.pi / bh_) * (2 * math.pi / bw_)
    E_ = float((px_[oben_].sum(axis=1) * gew_[oben_]).sum()) * O['staerke']
    ls_ = bpy.data.lights.new('FaengerAufhellung', 'SUN'); ls_.energy = E_ * float(O['aufhellung']); ls_.angle = math.radians(60)
    lo_ = bpy.data.objects.new('FaengerAufhellung', ls_); sc.collection.objects.link(lo_)
    kf_ = bpy.data.collections.new('NurFaenger'); kf_.objects.link(faenger)
    lo_.light_linking.receiver_collection = kf_
    lo_.light_linking.blocker_collection = kf_
    print('AUFHELLUNG Bestrahlung', round(E_, 3), 'Sonne', round(ls_.energy, 3))

# Grosse Stuecke (Kochinsel) brauchen einen echten Fussboden: Der Boden
# eines Rundumbilds liegt fuer die Kamera in unendlicher Ferne -- eine 2 m
# lange Insel schwebte davor (Proben kueche kia90/loft90, 29.09.2026).
# Ein echter Dielenboden (CC0) traegt Kontaktschatten und Spiegelung; die
# Aufnahme bleibt Licht und Hintergrund. Im Foto ersetzt er den Faenger.
fussboden = None
if O.get('fussboden'):
    FB = O['fussboden']
    bpy.ops.mesh.primitive_plane_add(size=1, location=(mitte.x, mitte.y, boden_z))
    fussboden = bpy.context.active_object; fussboden.name = 'fussboden'
    fussboden.scale = (FB['groesse'], FB['groesse'], 1)
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)
    k_ = FB['groesse'] / FB.get('kachel_m', 2.0)
    for lp in fussboden.data.uv_layers.active.data:
        lp.uv = (lp.uv[0] * k_, lp.uv[1] * k_)
    mfb = bpy.data.materials.new('Fussboden'); mfb.use_nodes = True
    nfb = mfb.node_tree; bfb = nfb.nodes.get('Principled BSDF')
    def _fb(n, farbe=False):
        im = bpy.data.images.load(os.path.join(os.path.dirname(Q), FB['ordner'], n), check_existing=True)
        if not farbe:
            im.colorspace_settings.name = 'Non-Color'
        t = nfb.nodes.new('ShaderNodeTexImage'); t.image = im
        return t
    t1 = _fb(FB['farbe'], True); t2 = _fb(FB['rauheit']); t3 = _fb(FB['normal'])
    nm_ = nfb.nodes.new('ShaderNodeNormalMap'); nm_.inputs['Strength'].default_value = 0.6
    mr_ = nfb.nodes.new('ShaderNodeMapRange')
    mr_.inputs['To Min'].default_value, mr_.inputs['To Max'].default_value = FB.get('rau', (0.28, 0.55))
    nfb.links.new(t1.outputs['Color'], bfb.inputs['Base Color'])
    nfb.links.new(t2.outputs['Color'], mr_.inputs['Value']); nfb.links.new(mr_.outputs['Result'], bfb.inputs['Roughness'])
    nfb.links.new(t3.outputs['Color'], nm_.inputs['Color']); nfb.links.new(nm_.outputs['Normal'], bfb.inputs['Normal'])
    fussboden.data.materials.append(mfb)
    stuhl_objs.append(fussboden)
    if MODUS != 'web':
        faenger.hide_render = True

# ------------------------------------------------------------------ Stoff: echte Leinenstruktur
def textur(name, farbe=False):
    img = bpy.data.images.load(os.path.join(Q, name), check_existing=True)
    if not farbe:
        img.colorspace_settings.name = 'Non-Color'
    return img

def uv_je_meter(o):
    """UV-Einheiten je Meter Stoff -- aus Flaeche 3D gegen Flaeche UV."""
    me = o.data
    if not me.uv_layers:
        return None
    uv = me.uv_layers.active.data
    a3 = auv = 0.0
    mw = o.matrix_world
    for p in me.polygons[:4000]:
        a3 += p.area * mw.to_scale().x * mw.to_scale().y
        pts = [uv[i].uv for i in p.loop_indices]
        s = 0.0
        for i in range(len(pts)):
            x1, y1 = pts[i]; x2, y2 = pts[(i + 1) % len(pts)]
            s += x1 * y2 - x2 * y1
        auv += abs(s) / 2
    return math.sqrt(auv / a3) if a3 > 0 else None

stoff_kachel_m = 0.22       # rough_linen: eine Kachel ~22 cm Gewebe
leinen = [m for m in bpy.data.materials if m.name.startswith(('Leinen', 'Serviette'))]
decke = next((o for o in modell if o.name == 'decke'), None)
uvm = uv_je_meter(decke) if decke else None
print('UV je Meter (Decke)', uvm)
if O.get('stoff') and leinen:
    nor = textur('rough_linen_nor_gl_2k.jpg'); rau = textur('rough_linen_rough_2k.jpg')
    for m in leinen:
        ntm = m.node_tree; b = next(n for n in ntm.nodes if n.type == 'BSDF_PRINCIPLED')
        tc = ntm.nodes.new('ShaderNodeTexCoord'); mp = ntm.nodes.new('ShaderNodeMapping')
        s = (1.0 / stoff_kachel_m) / (uvm or 1.0)
        mp.inputs['Scale'].default_value = (s, s, 1)
        ntm.links.new(tc.outputs['UV'], mp.inputs['Vector'])
        tn = ntm.nodes.new('ShaderNodeTexImage'); tn.image = nor; tn.interpolation = 'Cubic'
        tr = ntm.nodes.new('ShaderNodeTexImage'); tr.image = rau
        ntm.links.new(mp.outputs['Vector'], tn.inputs['Vector']); ntm.links.new(mp.outputs['Vector'], tr.inputs['Vector'])
        nm = ntm.nodes.new('ShaderNodeNormalMap'); nm.inputs['Strength'].default_value = float(EXTRA.get('stoff_normal', 0.8))
        ntm.links.new(tn.outputs['Color'], nm.inputs['Color'])
        # vorhandene (feinere) Normalenkarte ersetzen: Gewebe traegt jetzt die Struktur
        # Knitter: grosse, weiche Wellen (Lagerung) ueber dem Gewebe
        ko = ntm.nodes.new('ShaderNodeTexCoord')
        rn = ntm.nodes.new('ShaderNodeTexNoise'); rn.inputs['Scale'].default_value = 5.5
        rn.inputs['Detail'].default_value = 3.0; rn.inputs['Roughness'].default_value = 0.45
        ntm.links.new(ko.outputs['Object'], rn.inputs['Vector'])
        bu = ntm.nodes.new('ShaderNodeBump'); bu.inputs['Strength'].default_value = float(EXTRA.get('knitter', 0.06))
        bu.inputs['Distance'].default_value = 0.002
        ntm.links.new(rn.outputs['Fac'], bu.inputs['Height'])
        ntm.links.new(nm.outputs['Normal'], bu.inputs['Normal'])
        ntm.links.new(bu.outputs['Normal'], b.inputs['Normal'])
        # Rauheit: Grundwert des Stoffs, mit der gescannten Streuung moduliert
        r0 = b.inputs['Roughness'].default_value
        mr = ntm.nodes.new('ShaderNodeMapRange')
        mr.inputs['To Min'].default_value = max(0.0, r0 - 0.12); mr.inputs['To Max'].default_value = min(1.0, r0 + 0.1)
        ntm.links.new(tr.outputs['Color'], mr.inputs['Value'])
        ntm.links.new(mr.outputs['Result'], b.inputs['Roughness'])
        # Stoff ist nie ganz glatt beleuchtet: etwas Flaum (Sheen) an der Kante
        if 'Sheen Weight' in b.inputs:
            b.inputs['Sheen Weight'].default_value = float(EXTRA.get('sheen', 0.15))
            b.inputs['Sheen Roughness'].default_value = 0.5
            # Flaum in der Farbe des Stoffs -- weisser Flaum machte Terrakotta rosa (Probe 29.09.2026)
            if 'Sheen Tint' in b.inputs:
                bc = b.inputs['Base Color'].default_value
                b.inputs['Sheen Tint'].default_value = (min(1, bc[0] * 1.6), min(1, bc[1] * 1.6), min(1, bc[2] * 1.6), 1)

# ------------------------------------------------------------------ Tischdecke: echter Fall (Stoffsimulation)
# Der gebaute Behang hat regelmaessige Sinuswellen -- das Auge liest das als
# gerechnet. Hier faellt der Ueberhang unter Schwerkraft: Die Oberseite ist
# festgesteckt (dort liegen Teller), nur der Behang ist frei. Ein wenig
# Zufall im Startzustand, sonst faltet nichts. Danach: Saum (1,4 mm Stoff),
# feine Knitter im Shader.
def decke_fallen_lassen(o, rahmen=int(EXTRA.get('stoff_bilder', 70))):
    me = o.data
    mw = o.matrix_world
    zs = [(mw @ v.co).z for v in me.vertices]
    oben = max(zs)
    # Oberseite ohne Buegelfalten (lokal): Median der festen Punkte
    oben_lokal = sorted(v.co.z for v, z in zip(me.vertices, zs) if z > oben - 0.0025)[0]
    vg = o.vertex_groups.new(name='fest')
    fest = [v.index for v, z in zip(me.vertices, zs) if z > oben - 0.0025]
    vg.add(fest, 1.0, 'REPLACE')
    random.seed(11)
    for v, z in zip(me.vertices, zs):
        if z <= oben - 0.0025:
            tiefe = min(1.0, (oben - z) / 0.2)
            v.co.x += random.uniform(-1, 1) * float(EXTRA.get('stoff_zufall', 0.0006)) * tiefe
            v.co.y += random.uniform(-1, 1) * float(EXTRA.get('stoff_zufall', 0.0006)) * tiefe
    tisch = [x for x in modell if any(s.material and s.material.name.startswith('Tisch') for s in x.material_slots)]
    for t_ in tisch:
        c = t_.modifiers.new('Kollision', 'COLLISION'); t_.collision.thickness_outer = 0.003
    tuch = o.modifiers.new('Stoff', 'CLOTH')
    s = tuch.settings
    s.quality = 10; s.mass = 0.2; s.air_damping = 1.0
    s.tension_stiffness = 20; s.compression_stiffness = 20; s.shear_stiffness = 12; s.bending_stiffness = float(EXTRA.get('stoff_biegung', 8.0))
    s.vertex_group_mass = 'fest'; s.pin_stiffness = 1.0
    tuch.collision_settings.use_collision = True; tuch.collision_settings.distance_min = 0.002
    tuch.point_cache.frame_start = 1; tuch.point_cache.frame_end = rahmen
    for f in range(1, rahmen + 1):
        sc.frame_set(f)
    dg = bpy.context.evaluated_depsgraph_get()
    neu_me = bpy.data.meshes.new_from_object(o.evaluated_get(dg))
    # Variantendaten haengen am alten Mesh (glTF-Import) -- mitnehmen
    for k in me.keys():
        try:
            neu_me[k] = me[k]
        except Exception:
            pass
    o.modifiers.remove(tuch)
    alt_daten = getattr(me, 'gltf2_variant_mesh_data', None)
    # Nur die Koordinaten uebernehmen: gleiches Netz, gleiche Topologie
    # Buegelfalten oben nur noch angedeutet (gebuegelt, nicht gesteppt)
    faktor = float(EXTRA.get('falten_oben', 0.2))
    fest_set = set(fest)
    for v, nv in zip(me.vertices, neu_me.vertices):
        v.co = nv.co
        if v.index in fest_set:
            v.co.z = oben_lokal + (v.co.z - oben_lokal) * faktor
    bpy.data.meshes.remove(neu_me)
    for t_ in tisch:
        for m_ in [m_ for m_ in t_.modifiers if m_.type == 'COLLISION']:
            t_.modifiers.remove(m_)
    sc.frame_set(1)
    sol = o.modifiers.new('Saum', 'SOLIDIFY'); sol.thickness = 0.0014; sol.offset = -1.0
    sub = o.modifiers.new('Glatt', 'SUBSURF'); sub.levels = 0; sub.render_levels = 1
    print('DECKE gefallen:', len(fest), 'fest,', len(me.vertices) - len(fest), 'frei')

if decke is not None and WAS == 'gastro' and not EXTRA.get('ohne_stoffsim'):
    decke_fallen_lassen(decke)

# ------------------------------------------------------------------ Tisch: dunkles Holz
# Helle Kiefer neben dunklen Lederstuehlen wirkte zusammengewuerfelt (Probe
# 29.09.2026). Restauranttische sind meist gebeizt: Farbe x TISCH_FARBE.
TISCH_FARBE = [float(x) for x in EXTRA.get('tisch_farbe', '0.42,0.34,0.28').split(',')]
for m in bpy.data.materials:
    if m.name.startswith('Tisch') and m.use_nodes:
        b_ = next((n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None)
        if not b_:
            continue
        lk = b_.inputs['Base Color'].links
        if lk:
            mul = m.node_tree.nodes.new('ShaderNodeMix'); mul.data_type = 'RGBA'; mul.blend_type = 'MULTIPLY'
            mul.inputs['Factor'].default_value = 1.0
            mul.inputs[7].default_value = (*TISCH_FARBE, 1)
            m.node_tree.links.new(lk[0].from_socket, mul.inputs[6])
            m.node_tree.links.new(mul.outputs[2], b_.inputs['Base Color'])
        else:
            c = b_.inputs['Base Color'].default_value
            b_.inputs['Base Color'].default_value = (c[0] * TISCH_FARBE[0], c[1] * TISCH_FARBE[1], c[2] * TISCH_FARBE[2], 1)

# ------------------------------------------------------------------ Stuehle (Poly Haven, CC0)
def stuhl_laden():
    return gltf_laden(O['stuehle'])

if O.get('stuehle'):
    # Gedecke finden: die zwei grossen Teller (Speiseteller) zeigen die Plaetze
    teller = [o for o in modell if 'teller' in o.name and not o.name.startswith('gang') and 'brot' not in o.name]
    plaetze = []
    for t in teller:
        c = t.matrix_world.translation
        v = Vector((c.x - mitte.x, c.y - mitte.y, 0))
        if v.length > 0.05 and all((v.normalized() - p).length > 0.5 for p in plaetze):
            plaetze.append(v.normalized())
    if not plaetze:
        plaetze = [Vector((0, -1, 0)), Vector((0, 1, 0))]
    print('PLAETZE', [tuple(round(x, 2) for x in p) for p in plaetze])
    random.seed(7)
    for i, richt in enumerate(plaetze[:2]):
        neu, wurzel = stuhl_laden()
        meshes = [o for o in neu if o.type == 'MESH']
        # Lehne finden: Schwerpunkt der oberen Haelfte des Stuhls
        l2, h2 = huelle(meshes)
        oben = [o.matrix_world @ v.co for o in meshes for v in o.data.vertices if (o.matrix_world @ v.co).z > l2.z + 0.7 * (h2.z - l2.z)]
        mitte_s = (l2 + h2) / 2
        s_oben = sum(oben, Vector()) / max(1, len(oben))
        lehne = Vector((s_oben.x - mitte_s.x, s_oben.y - mitte_s.y, 0))
        # Drehen: Lehne zeigt vom Tisch weg (= in Richtung richt)
        w_ist = math.atan2(lehne.y, lehne.x); w_soll = math.atan2(richt.y, richt.x)
        drehw = w_soll - w_ist + math.radians(random.uniform(-6, 6))
        halter = bpy.data.objects.new(f'stuhl_{i}', None); sc.collection.objects.link(halter)
        for w in wurzel:
            w.parent = halter
        halter.rotation_euler = (0, 0, drehw)
        bpy.context.view_layer.update()
        l3, h3 = huelle(meshes)
        # Sitzvorderkante ~12 cm unter die Tischkante geschoben, leicht herausgezogen
        abstand = max(groesse.x, groesse.y) * 0.5 * 0.78 + (h3 - l3).length * 0.0 + 0.30 + random.uniform(0.0, 0.06)
        ziel = Vector((mitte.x, mitte.y, 0)) + richt * abstand
        mitte3 = (l3 + h3) / 2
        halter.location = (ziel.x - mitte3.x, ziel.y - mitte3.y, boden_z - l3.z)
        stuhl_objs += meshes
        print('STUHL', i, 'Hoehe', round(h3.z - l3.z, 3), 'Drehung', round(math.degrees(drehw), 1))

# ------------------------------------------------------------------ Kamera
cam_d = bpy.data.cameras.new('Kamera'); cam_d.sensor_fit = 'HORIZONTAL'
cam_d.sensor_width = 36.0; cam_d.lens = K['lens']
cam = bpy.data.objects.new('Kamera', cam_d); sc.collection.objects.link(cam); sc.camera = cam
ziel = Vector((mitte.x, mitte.y, basis_z + K['ziel_hoehe']))
L = max(groesse.x, groesse.y)
hfov = 2 * math.atan(18.0 / K['lens'])
abstand = (L / K['fuellung'] / 2) / math.tan(hfov / 2)
w = math.radians(K['winkel'])
# Nahansicht zur Pruefung (Probe): ziel_y verschiebt den Blickpunkt entlang
# der Laengsachse, abstand setzt die Entfernung fest (LKW-Front, 30.09.2026)
if 'ziel_y' in EXTRA:
    ziel.y = float(EXTRA['ziel_y'])
if 'abstand' in EXTRA:
    abstand = float(EXTRA['abstand'])
ort = ziel + Vector((-math.sin(w), -math.cos(w), 0)) * abstand
ort.z = basis_z + K['hoehe']
cam.location = ort
cam.rotation_euler = (ziel - ort).to_track_quat('-Z', 'Y').to_euler()
cam_d.dof.use_dof = True
cam_d.dof.focus_distance = (ziel - ort).length
cam_d.dof.aperture_fstop = K['blende']
cam_d.dof.aperture_blades = 9            # runde Unschaerfescheiben wie ein echtes Objektiv

# Schwarze Flagge hinter der Kamera, wie sie jeder Produktfotograf aufstellt:
# Dunkles Flaschenglas spiegelte den hellen Kellerteil hinter der Kamera als
# gleichmaessigen grauen Schleier -- die volle Flasche las sich wie leer
# (Probe d0e: ohne Inhalt sah sie genauso aus). Eine runde Flasche spiegelt
# fast den halben Raum -- deshalb eine breite Flagge (Probe d0f: 1,4 m
# dunkelte nur einen Streifen). Die Flagge ist nur fuer
# Spiegelungen sichtbar; Licht, Schatten und das Rundumbild bleiben unberuehrt.
FL = ORTE.get(WAS, {}).get('flagge')
if FL and 'flagge=aus' not in argv:
    bpy.ops.mesh.primitive_plane_add(size=1, location=ort + (ort - ziel).normalized() * float(FL.get('abstand', 0.25)))
    flagge = bpy.context.active_object; flagge.name = 'Flagge'
    flagge.scale = (float(FL['breite']), float(FL['hoehe']), 1)
    flagge.rotation_euler = (ziel - ort).to_track_quat('Z', 'Y').to_euler()
    mfl = bpy.data.materials.new('Flagge'); mfl.use_nodes = True
    bfl = mfl.node_tree.nodes.get('Principled BSDF')
    bfl.inputs['Base Color'].default_value = (0.004, 0.004, 0.004, 1); bfl.inputs['Roughness'].default_value = 1.0
    bfl.inputs['Specular IOR Level'].default_value = 0.0
    flagge.data.materials.append(mfl)
    flagge.visible_camera = False; flagge.visible_diffuse = False; flagge.visible_shadow = False
    flagge.visible_transmission = True; flagge.visible_glossy = True; flagge.visible_volume_scatter = False

# ------------------------------------------------------------------ Fahrerplatz
# Modus innen (29.09.2026): Die Ausstattungsbilder vom Fahrerplatz entstanden
# im dunklen Studio -- durch die Scheiben sah man Schwarz. Am Ort faellt das
# Tageslicht der Aufnahme durch die Scheiben, draussen liegt die Strasse.
# Dieselbe Kamera wie branchen_studio.py (Augpunkt nach SAE J941, 20 mm),
# damit Standbild und Kamerafahrt im Web (kamera-innen.json) sich decken.
KAMERA_INNEN = {
    'kleinwagen': dict(auge=(0.30, 2.42, 1.12), ziel=(0.08, 1.78, 0.86), lens=20.0),
    'mittelklasse': dict(auge=(0.31, 2.76, 1.10), ziel=(0.08, 2.15, 0.85), lens=20.0),
}
INNEN = MODUS == 'innen' or (MODUS == 'probe' and EXTRA.get('kamera') == 'innen')
if INNEN:
    KI = KAMERA_INNEN[WAS]
    lo_m, hi_m = huelle(modell)
    y0 = lo_m.y                   # Vorderkante in Blender (-Y), wie im Studio
    auge = Vector((KI['auge'][0], y0 + KI['auge'][1], boden_z + KI['auge'][2]))
    ziel_i = Vector((KI['ziel'][0], y0 + KI['ziel'][1], boden_z + KI['ziel'][2]))
    cam.location = auge
    cam.rotation_euler = (ziel_i - auge).to_track_quat('-Z', 'Y').to_euler()
    cam_d.lens = KI['lens']; cam_d.dof.use_dof = False

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
PROBE = MODUS in ('probe', 'reihe')
r.resolution_x, r.resolution_y = 3840, 2160
r.resolution_percentage = int(EXTRA.get('prozent', 12 if MODUS == 'reihe' else (25 if PROBE else 100)))
sc.cycles.samples = int(EXTRA.get('samples', 32 if MODUS == 'reihe' else (96 if PROBE else 1024)))
sc.cycles.adaptive_threshold = 0.02 if PROBE else 0.004
sc.cycles.use_denoising = True
sc.cycles.denoiser = 'OPTIX' if prefs.compute_device_type == 'OPTIX' else 'OPENIMAGEDENOISE'
sc.cycles.max_bounces = 32; sc.cycles.glossy_bounces = 16; sc.cycles.transmission_bounces = 32
# Abgebrochene Lichtwege im Glas werden schwarz -- das liess das Wasserglas
# wie poliertes Metall aussehen (Probe 29.09.2026). Mehr Wege + Eigenfarbe.
for m in bpy.data.materials:
    if m.name.startswith('Glas') and m.use_nodes:
        b_ = next((n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None)
        if b_ and b_.inputs['Transmission Weight'].default_value > 0.5:
            b_.inputs['Base Color'].default_value = (0.93, 0.975, 0.955, 1)
            b_.inputs['Roughness'].default_value = 0.004
# Fluessigkeit in der Flasche: Zwischen Glas (IOR 1,5) und Wein (1,33) wird
# kaum Licht gespiegelt. Das Modell hatte eine normale Oberflaeche -- die
# volle Rotweinflasche glaenzte dadurch grau wie leer (Probe d0d, Messung
# Flaschenmitte 41-55 neutralgrau statt fast schwarz).
for m in bpy.data.materials:
    if m.name.startswith('Inhalt') and m.use_nodes:
        b_ = next((n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None)
        if b_:
            b_.inputs['Specular IOR Level'].default_value = 0.04
            b_.inputs['Roughness'].default_value = 0.02
sc.cycles.caustics_reflective = False; sc.cycles.caustics_refractive = False
# Ohne Kaustik wirft Glas einen schwarzen Schatten -- unter dem Weinglas lag
# ein dunkler Fleck wie verschuettete Tinte (Probe d0b, 29.09.2026). Echtes
# Glas laesst fast alles Licht durch. Deshalb sehen Schattenstrahlen das Glas
# als getoente Durchsicht (Glas hell, Wein tiefrot); alle anderen Strahlen
# bleiben unveraendert physikalisch. Nur fuer helles Glas.
for m in bpy.data.materials:
    if not m.use_nodes:
        continue
    nt = m.node_tree
    b_ = next((n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED'), None)
    aus = next((n for n in nt.nodes if n.type == 'OUTPUT_MATERIAL' and n.is_active_output), None)
    if not b_ or not aus or b_.inputs['Transmission Weight'].default_value <= 0.5 or not aus.inputs['Surface'].links:
        continue
    # Dunkles Flaschenglas (Rotwein, Oel) laesst in echt kaum Licht auf den
    # Inhalt -- mit Durchsicht glaenzte der Wein darin grau (Probe d0c).
    if max(b_.inputs['Base Color'].default_value[:3]) < 0.6:
        continue
    quelle = aus.inputs['Surface'].links[0].from_socket
    lp = nt.nodes.new('ShaderNodeLightPath'); tr = nt.nodes.new('ShaderNodeBsdfTransparent'); mx = nt.nodes.new('ShaderNodeMixShader')
    f_ = b_.inputs['Base Color'].default_value
    tr.inputs['Color'].default_value = (f_[0] * 0.82, f_[1] * 0.82, f_[2] * 0.82, 1)
    nt.links.new(lp.outputs['Is Shadow Ray'], mx.inputs[0])
    nt.links.new(quelle, mx.inputs[1]); nt.links.new(tr.outputs[0], mx.inputs[2])
    nt.links.new(mx.outputs[0], aus.inputs['Surface'])
sc.cycles.blur_glossy = 0.3

# Strassenstaub (LKW, 30.09.2026): Nutzfahrzeuge sind nie steril sauber.
# Unten, wo Spritzwasser und Staub hinkommen, liegt ein matter Film, nach
# oben auslaufend, mit Wolken statt als gleichmaessiger Verlauf; Reifen
# stark, Lack und Alu schwach. Nur im Foto -- im Web bleibt das Material
# des GLB (der Unterschied liegt unter dem, was man beim Ueberblenden sieht).
SM = O.get('schmutz')
if SM:
    import re as _re
    for m in bpy.data.materials:
        if not m.use_nodes:
            continue
        stark = {'Reifen': 0.55, 'Rahmen': 0.45, 'Kunststoff genarbt': 0.40, 'Riffelblech': 0.35, 'Felge': 0.22,
                 'Aluminium': 0.25, 'Lack': 0.22, 'Plane': 0.12, 'Nabe': 0.3}
        f_st = next((v for k_, v in stark.items() if m.name.startswith(k_)), None)
        if f_st is None:
            continue
        nt = m.node_tree
        b_ = next((n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED'), None)
        if not b_:
            continue
        geo = nt.nodes.new('ShaderNodeNewGeometry'); sep = nt.nodes.new('ShaderNodeSeparateXYZ')
        nt.links.new(geo.outputs['Position'], sep.inputs[0])
        hoehe = nt.nodes.new('ShaderNodeMapRange'); hoehe.interpolation_type = 'SMOOTHSTEP'
        hoehe.inputs['From Min'].default_value = float(SM.get('bis', 1.25)); hoehe.inputs['From Max'].default_value = 0.10
        nt.links.new(sep.outputs['Z'], hoehe.inputs['Value'])
        rausch = nt.nodes.new('ShaderNodeTexNoise'); rausch.inputs['Scale'].default_value = 2.2; rausch.inputs['Detail'].default_value = 6.0
        nt.links.new(geo.outputs['Position'], rausch.inputs['Vector'])
        wolke = nt.nodes.new('ShaderNodeMapRange'); wolke.inputs['From Min'].default_value = 0.35; wolke.inputs['From Max'].default_value = 0.70
        nt.links.new(rausch.outputs['Fac'], wolke.inputs['Value'])
        mal = nt.nodes.new('ShaderNodeMath'); mal.operation = 'MULTIPLY'
        nt.links.new(hoehe.outputs['Result'], mal.inputs[0]); nt.links.new(wolke.outputs['Result'], mal.inputs[1])
        fak = nt.nodes.new('ShaderNodeMath'); fak.operation = 'MULTIPLY'; fak.inputs[1].default_value = f_st * float(SM.get('staerke', 1.0))
        nt.links.new(mal.outputs[0], fak.inputs[0])
        # Farbe: bisherige Farbe (Karte oder Wert) mit Staubton mischen
        mix = nt.nodes.new('ShaderNodeMix'); mix.data_type = 'RGBA'
        nt.links.new(fak.outputs[0], mix.inputs['Factor'])
        ein = b_.inputs['Base Color']
        if ein.links:
            nt.links.new(ein.links[0].from_socket, mix.inputs['A'])
        else:
            mix.inputs['A'].default_value = ein.default_value
        mix.inputs['B'].default_value = (0.30, 0.26, 0.21, 1.0)
        nt.links.new(mix.outputs['Result'], ein)
        # Rauheit Richtung matt
        rr = nt.nodes.new('ShaderNodeMix'); rr.data_type = 'FLOAT'
        nt.links.new(fak.outputs[0], rr.inputs['Factor'])
        re_ = b_.inputs['Roughness']
        if re_.links:
            nt.links.new(re_.links[0].from_socket, rr.inputs['A'])
        else:
            rr.inputs['A'].default_value = re_.default_value
        rr.inputs['B'].default_value = 0.85
        nt.links.new(rr.outputs['Result'], re_)
        # Klarlack wird unter Staub stumpf
        if b_.inputs['Coat Weight'].default_value > 0:
            cr = nt.nodes.new('ShaderNodeMix'); cr.data_type = 'FLOAT'
            nt.links.new(fak.outputs[0], cr.inputs['Factor'])
            cr.inputs['A'].default_value = b_.inputs['Coat Roughness'].default_value; cr.inputs['B'].default_value = 0.5
            nt.links.new(cr.outputs['Result'], b_.inputs['Coat Roughness'])
    print('SCHMUTZ gesetzt')
sc.view_settings.view_transform = 'AgX'
for _look in (EXTRA.get('look', 'AgX - Base Contrast'), 'None'):
    try:
        sc.view_settings.look = _look; break
    except TypeError:
        pass
sc.view_settings.exposure = O['belichtung']
if INNEN:
    # Im Wagen ist es dunkler als draussen -- wie ein Fotograf eine Blende
    # mehr (innen_belichtung=... zum Probieren)
    sc.view_settings.exposure = O['belichtung'] + float(EXTRA.get('innen_belichtung', O.get('innen_belichtung', 1.0)))
r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'; r.image_settings.color_depth = '8'
r.film_transparent = False

# ------------------------------------------------------------------ Varianten
def varianten():
    v = getattr(sc, 'gltf2_KHR_materials_variants_variants', None)
    return [x.name for x in v] if v else []

def variante_setzen(idx):
    for o in modell:
        daten = getattr(o.data, 'gltf2_variant_mesh_data', None)
        if not daten:
            continue
        for e in daten:
            for v in e.variants:
                if v.variant.variant_idx == idx:
                    o.material_slots[e.material_slot_index].material = e.material

namen = varianten()
fertig = []

# ------------------------------------------------------------------ Web (R3)
# Fuer die Echtzeit im Browser: dieselbe Welt wie im Poster.
#   ort-hintergrund.png  Rundumbild des Ortes von der Kamera aus, fertig
#                        belichtet (AgX wie das Poster) -> Hintergrund
#   ort-umgebung.exr     Rundumbild aus der Tischmitte, linear -> Licht und
#                        Spiegelungen (PMREM)
#   schatten.png         Kontaktschatten von oben (Schattenfaenger, Alpha)
#   decke-ort.glb        die gefallene Tischdecke (nur Geometrie)
#   stuehle.glb          die Stuehle an ihrem Platz
# Rundumbilder: Bildmitte nach +X, oben oben -- wie three.js' Equirect.
def pano(name, ort, breite, exr):
    pc = bpy.data.cameras.new('Rund_' + name); pc.type = 'PANO'
    pc.panorama_type = 'EQUIRECTANGULAR'
    po = bpy.data.objects.new('Rund_' + name, pc); sc.collection.objects.link(po)
    po.location = ort
    po.rotation_euler = (math.radians(90), 0, math.radians(-90))
    alt = (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.image_settings.file_format,
           r.image_settings.color_depth, sc.view_settings.view_transform, sc.view_settings.look, sc.cycles.samples, r.film_transparent)
    sc.camera = po; r.resolution_x, r.resolution_y, r.resolution_percentage = breite, breite // 2, 100
    if exr:
        r.image_settings.file_format = 'OPEN_EXR'; r.image_settings.color_depth = '32'
        sc.view_settings.view_transform = 'Standard'; sc.view_settings.look = 'None'
    else:
        r.image_settings.file_format = 'PNG'; r.image_settings.color_depth = '16'
    sc.cycles.samples = 64; r.film_transparent = False
    r.filepath = os.path.join(AUSGABE, name); bpy.ops.render.render(write_still=True)
    (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.image_settings.file_format,
     r.image_settings.color_depth, sc.view_settings.view_transform, sc.view_settings.look, sc.cycles.samples, r.film_transparent) = alt
    bpy.data.objects.remove(po)

def schatten_backen(gr):
    oc = bpy.data.cameras.new('Oben'); oc.type = 'ORTHO'; oc.ortho_scale = gr
    ob = bpy.data.objects.new('Oben', oc); sc.collection.objects.link(ob)
    ob.location = (mitte.x, mitte.y, boden_z + 20)
    for o in modell + stuhl_objs:
        o.visible_camera = False
    alt = (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.film_transparent, r.image_settings.color_mode, sc.cycles.samples)
    sc.camera = ob; r.resolution_x = r.resolution_y = 1024; r.resolution_percentage = 100
    r.film_transparent = True; r.image_settings.color_mode = 'RGBA'; r.image_settings.file_format = 'PNG'
    r.image_settings.color_depth = '16'; sc.cycles.samples = 256
    r.filepath = os.path.join(AUSGABE, 'schatten.png'); bpy.ops.render.render(write_still=True)
    (sc.camera, r.resolution_x, r.resolution_y, r.resolution_percentage, r.film_transparent, r.image_settings.color_mode, sc.cycles.samples) = alt
    for o in modell + stuhl_objs:
        o.visible_camera = True
    bpy.data.objects.remove(ob)
    return {'datei': 'schatten.webp', 'groesse_m': gr, 'mitte': [mitte.x, boden_z, -mitte.y]}

def nur_exportieren(objs, datei, materialien, farben=False):
    bpy.ops.object.select_all(action='DESELECT')
    for o in objs:
        o.select_set(True)
    bpy.context.view_layer.objects.active = objs[0]
    kw = dict(filepath=os.path.join(AUSGABE, datei), export_format='GLB', use_selection=True, export_yup=True,
              export_apply=True, export_texcoords=True, export_normals=True,
              export_materials='EXPORT' if materialien else 'NONE')
    if materialien:
        kw['export_image_format'] = 'WEBP'
    if farben:
        kw['export_vertex_color'] = 'ACTIVE'
    bpy.ops.export_scene.gltf(**kw)

if MODUS == 'web':
    status(was=WAS, modus='web', schritt='rundum')
    variante_setzen(0) if namen else None
    for o in modell + stuhl_objs:
        o.hide_render = True
    faenger.hide_render = True
    pano('ort-hintergrund.png', cam.location.copy(), 4096, False)
    # Licht dort einfangen, wo das Produkt steht -- auf dem Tisch, nicht am
    # Boden darunter (Wein: 0,28 m statt 1,04 m, Web-Lauf 29.09.2026)
    ort_env = Vector((mitte.x, mitte.y, basis_z + groesse.z * 0.8))
    pano('ort-umgebung.exr', ort_env, 1024, True)
    for o in modell + stuhl_objs:
        o.hide_render = False
    faenger.hide_render = False
    status(was=WAS, modus='web', schritt='schatten')
    # Der Fussboden zaehlt nicht mit -- sonst lag der Kontaktschatten mit
    # 1024 Pixeln auf 16 m (Kueche, 29.09.2026) und war nur noch ein Hauch.
    _mitmoebel = [o for o in stuhl_objs if o is not fussboden]
    gr = 2 * max(1.2, max(abs(v) for o in _mitmoebel for c in o.bound_box
                          for v in ((o.matrix_world @ Vector(c)).x - mitte.x, (o.matrix_world @ Vector(c)).y - mitte.y)) + 0.25) if _mitmoebel else 2 * max(1.2, max(groesse.x, groesse.y) / 2 + 0.6)
    # Tiefe Sonne wirft lange Schatten: Beim Sportwagen (klarer Himmel) lief
    # der Schatten ueber den Rand der 5,6-m-Flaeche hinaus und wurde im Web
    # weich ausgeblendet -- vorn fehlte er (Probe 29.09.2026). Je Ort groesser.
    gr = float(EXTRA.get('schatten_m', O.get('schatten_m', gr)))
    schatten = schatten_backen(gr)
    schatten_oben = None
    if unterlage_box:
        # Schatten des Produkts auf der Tischplatte: eigener Faenger knapp ueber
        # der Platte, der Tisch selbst ist fuer die Kamera unsichtbar.
        lt, ht = unterlage_box
        bpy.ops.mesh.primitive_plane_add(size=1, location=((lt.x + ht.x) / 2, (lt.y + ht.y) / 2, basis_z + 0.0004))
        fo = bpy.context.active_object; fo.name = 'FaengerOben'; fo.is_shadow_catcher = True
        fo.scale = (ht.x - lt.x, ht.y - lt.y, 1)
        faenger.hide_render = True
        gr_o = max(ht.x - lt.x, ht.y - lt.y)
        ob = bpy.data.objects.new('ObenT', bpy.data.cameras.new('ObenT')); ob.data.type = 'ORTHO'; ob.data.ortho_scale = gr_o
        sc.collection.objects.link(ob); ob.location = ((lt.x + ht.x) / 2, (lt.y + ht.y) / 2, basis_z + 5)
        for o in modell + stuhl_objs:
            o.visible_camera = False
        alt = (sc.camera, r.resolution_x, r.resolution_y, r.film_transparent, r.image_settings.color_mode, r.image_settings.color_depth, sc.cycles.samples)
        sc.camera = ob; r.resolution_x = r.resolution_y = 1024; r.film_transparent = True
        r.image_settings.color_mode = 'RGBA'; r.image_settings.file_format = 'PNG'; r.image_settings.color_depth = '16'; sc.cycles.samples = 256
        r.filepath = os.path.join(AUSGABE, 'schatten-oben.png'); bpy.ops.render.render(write_still=True)
        (sc.camera, r.resolution_x, r.resolution_y, r.film_transparent, r.image_settings.color_mode, r.image_settings.color_depth, sc.cycles.samples) = alt
        for o in modell + stuhl_objs:
            o.visible_camera = True
        faenger.hide_render = False
        bpy.data.objects.remove(fo); bpy.data.objects.remove(ob)
        schatten_oben = {'datei': 'schatten-oben.webp', 'groesse_m': gr_o,
                         'mitte': [(lt.x + ht.x) / 2, basis_z, -(lt.y + ht.y) / 2]}
    # Stuhltexturen auf 1024 fuers Web
    for o in stuhl_objs:
        for s in o.material_slots:
            if s.material and s.material.use_nodes:
                for n in s.material.node_tree.nodes:
                    if n.type == 'TEX_IMAGE' and n.image and max(n.image.size) > 1024:
                        n.image.scale(1024, 1024)
    if decke is not None:
        for mn in ('Glatt', 'Saum'):
            m_ = decke.modifiers.get(mn)
            if m_:
                decke.modifiers.remove(m_)
        # Verdeckung (AO) in die Punktfarben backen: Unter Tellern, Leuchter und
        # in den Falten wird der Stoff dunkler -- three.js kennt das sonst nicht,
        # die Decke wirkte in Echtzeit flach und zu hell (Probe 29.09.2026).
        ca = decke.data.color_attributes.new('Verdeckung', 'BYTE_COLOR', 'POINT')
        decke.data.color_attributes.active_color = ca
        sc.render.bake.target = 'VERTEX_COLORS'
        sc.world.light_settings.distance = float(EXTRA.get('ao_weite', 0.12))
        bpy.ops.object.select_all(action='DESELECT'); decke.select_set(True); bpy.context.view_layer.objects.active = decke
        alt_s = sc.cycles.samples; sc.cycles.samples = 256
        bpy.ops.object.bake(type='AO')
        sc.cycles.samples = alt_s
        boden_ao = float(EXTRA.get('ao_min', 0.45))
        for d_ in ca.data:
            v_ = d_.color[0]
            w_ = boden_ao + (1 - boden_ao) * v_
            d_.color = (w_, w_, w_, 1.0)
        nur_exportieren([decke], 'decke-ort.glb', False, farben=True)
    if stuhl_objs:
        nur_exportieren(stuhl_objs, 'zubehoer.glb', True)
    web = {
        'ort': {'hintergrund': 'ort-hintergrund.webp', 'hdri': O['hdri'], 'drehung_grad': O['dreh'],
                'kamera_ort': [cam.location.x, cam.location.z, -cam.location.y]},
        'umgebung': {'datei': 'ort-umgebung.hdr', 'ort': [ort_env.x, ort_env.z, -ort_env.y]},
        'umgebung_boden': {'datei': 'ort-umgebung.hdr'},
        'schatten': schatten,
        'decke_ort': 'decke-ort.glb' if decke is not None else None,
        'zubehoer': 'zubehoer.glb' if stuhl_objs else None,
        'basis_hoehe': basis_z - boden_z,
        'schatten_oben': schatten_oben,
        'tisch_farbe': TISCH_FARBE,
    }
    with open(os.path.join(AUSGABE, 'web.json'), 'w', encoding='utf-8') as f:
        json.dump(web, f, ensure_ascii=False, indent=1)
    status(was=WAS, modus='web', schritt='fertig')
    print('WEB FERTIG')
    MODUS = 'web_fertig'

if MODUS == 'web_fertig':
    pass
elif MODUS == 'reihe':
    # Welche Drehung der Aufnahme gibt den besten Hintergrund? Acht Kandidaten.
    variante_setzen(0) if namen else None
    for grad in range(0, 360, 45):
        dreh.inputs['Rotation'].default_value[2] = math.radians(grad)
        r.filepath = os.path.join(AUSGABE, f"reihe{EXTRA.get('name', '')}-{grad:03d}.png")
        t0 = time.time(); bpy.ops.render.render(write_still=True)
        fertig.append({'dreh': grad, 'sekunden': round(time.time() - t0, 1)})
        status(was=WAS, modus=MODUS, fertig=fertig)
else:
    liste = list(range(len(namen))) or [0]
    # Lacke fuer die Aussenbilder, Ausstattungen ("Innen: ...") nur vom
    # Fahrerplatz -- aussen saehen sie gleich aus (vorher 6 statt 3 Poster)
    liste = [i for i in liste if not namen or namen[i].startswith('Innen') == bool(INNEN)]
    if NUR:
        # auch als Kurzname (stoff-anthrazit): Namen mit Leerzeichen kamen
        # ueber Start-Process zerlegt an (Probe innen 29.09.2026)
        _kurz = lambda n: n.lower().replace(' ', '-').replace('innen:-', '')
        liste = [i for i in liste if namen and (namen[i] in NUR or _kurz(namen[i]) in NUR)]
    for i in liste:
        if namen:
            variante_setzen(i)
        name = (namen[i] if namen else 'standard').lower().replace(' ', '-').replace('innen:-', '')
        art = 'probe' if PROBE else ('innen' if INNEN else 'poster')
        pfad = os.path.join(AUSGABE, f"{art}-{name}{EXTRA.get('name', '')}.png")
        status(was=WAS, modus=MODUS, variante=name, schritt='rendert', fertig=fertig)
        t0 = time.time(); r.filepath = pfad
        bpy.ops.render.render(write_still=True)
        fertig.append({'variante': name, 'sekunden': round(time.time() - t0, 1)})
        print('GERECHNET', pfad, round(time.time() - t0, 1), 's')
        if PROBE and not NUR:
            break                           # Probe: nur die erste Variante

kam = {
    'objekt': WAS, 'ort': O['hdri'], 'ort_web': O.get('hdri_web'), 'hdri_drehung_grad': O['dreh'], 'hdri_staerke': O['staerke'],
    'sensor_breite_mm': 36.0, 'brennweite_mm': cam_d.lens,
    'position': [cam.location.x, cam.location.z, -cam.location.y], 'ziel': [ziel.x, ziel.z, -ziel.y],
    'blende': cam_d.dof.aperture_fstop, 'fokus_m': cam_d.dof.focus_distance,
    'boden_hoehe': boden_z, 'belichtung': sc.view_settings.exposure, 'varianten': namen,
}
with open(os.path.join(AUSGABE, 'kamera.json' if MODUS in ('voll', 'web_fertig') else 'kamera-probe.json'), 'w', encoding='utf-8') as f:
    json.dump(kam, f, ensure_ascii=False, indent=1)
status(was=WAS, modus=MODUS, schritt='fertig', fertig=fertig)
if MODUS == 'voll':
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(P, f'{WAS}-ort.blend'))
print('ORT FERTIG')
