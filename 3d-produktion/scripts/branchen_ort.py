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
}
O = ORTE[WAS]
for k in ('dreh', 'staerke', 'belichtung'):
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
bpy.ops.import_scene.gltf(filepath=os.path.join(P, 'quelle', f'{WAS}.glb'))
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
boden_z = tiefster_punkt(modell)
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
sc.view_settings.view_transform = 'AgX'
for _look in (EXTRA.get('look', 'AgX - Base Contrast'), 'None'):
    try:
        sc.view_settings.look = _look; break
    except TypeError:
        pass
sc.view_settings.exposure = O['belichtung']
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
    gr = 2 * max(1.2, max(abs(v) for o in stuhl_objs for c in o.bound_box
                          for v in ((o.matrix_world @ Vector(c)).x - mitte.x, (o.matrix_world @ Vector(c)).y - mitte.y)) + 0.25) if stuhl_objs else 2.4
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
        r.filepath = os.path.join(AUSGABE, f'reihe-{grad:03d}.png')
        t0 = time.time(); bpy.ops.render.render(write_still=True)
        fertig.append({'dreh': grad, 'sekunden': round(time.time() - t0, 1)})
        status(was=WAS, modus=MODUS, fertig=fertig)
else:
    liste = list(range(len(namen))) or [0]
    if NUR:
        liste = [i for i in liste if namen and namen[i] in NUR]
    for i in liste:
        if namen:
            variante_setzen(i)
        name = (namen[i] if namen else 'standard').lower().replace(' ', '-')
        pfad = os.path.join(AUSGABE, f"{'probe' if PROBE else 'poster'}-{name}{EXTRA.get('name', '')}.png")
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
