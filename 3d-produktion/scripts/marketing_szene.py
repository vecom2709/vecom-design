"""marketing_szene.py -- Claude baut eine Szene fuer ein Marketing-Bild
(Marketing-Studio 11, 01.10.2026, Uwe: Ja zu B2 -- "fuer andere Branchen
baut Claude die Szene selbst").

Fuer Branchen ohne fertige Szene (branchen_ort.py) schreibt Claude Code auf
Uwes PC ein kurzes Blender-Skript aus der Bildidee. Dieses Geruest
  - prueft den Code (keine Dateien, keine Prozesse, kein Netz),
  - stellt Bausteine bereit: echte Aufnahmen (HDRI) und gescannte Modelle
    von Poly Haven (CC0) aus 3d-produktion/quellen/polyhaven, PBR-Stoffe,
    Kamera mit echter Brennweite, Fasen, Schattenfaenger-Boden,
  - rechnet wie die Branchen-Szenen: Cycles auf der GPU, AgX, Belichtung an
    einer kleinen Probe gemessen und hoechstens eine Blende nachgefuehrt,
  - schreibt das Bild und einen Bericht (JSON) daneben.

Aufruf (headless, vom Worker):
  blender -b -P marketing_szene.py -- claude marketing auftrag=<json>
  blender -b -P marketing_szene.py -- claude marketing_film auftrag=<json>
    (Film, 01.10.2026 W2: langsame Fahrt um das Ziel, Titel und Abspann
     aus marketing_schnitt.py)
  Auftrag: {"px": "1080x1350", "seed": 7, "aus": "...png", "szene": "...py"}
"""
import bpy, bmesh, json, math, os, re, sys, time, random
from mathutils import Vector

Q = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\quellen\polyhaven'
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
EXTRA = {k: v for k, v in (t.split('=', 1) for t in argv[2:] if '=' in t)}
MODUS = argv[1] if len(argv) > 1 else 'marketing'
with open(EXTRA['auftrag'], encoding='utf-8') as _f:
    MK = json.load(_f)
PX = tuple(int(v) for v in str(MK.get('px', '1080x1350')).lower().split('x'))
AUS = MK['aus']
BERICHT = {'was': 'claude', 'modus': 'marketing', 'px': list(PX)}

# ------------------------------------------------------------------ Pruefung
# Claudes Code darf nur bauen: keine Dateien, keine Prozesse, kein Netz,
# keine Umgehung ueber eval/exec/__import__. Gelesen wird nur, was dieses
# Geruest selbst bereitstellt.
VERBOTEN = re.compile(
    r'(\bimport\s+(os|sys|subprocess|socket|shutil|urllib|requests|http|ctypes|pathlib|importlib|pickle|io|builtins)\b'
    r'|\bfrom\s+(os|sys|subprocess|socket|shutil|urllib|requests|http|ctypes|pathlib|importlib|pickle|io|builtins)\b'
    r'|__import__|__builtins__|__class__|__subclasses__|\bexec\s*\(|\beval\s*\(|\bopen\s*\(|\bcompile\s*\('
    r'|\bglobals\s*\(|\blocals\s*\(|bpy\.ops\.wm\.|bpy\.ops\.script|bpy\.app\.handlers|bpy\.utils\.|\.filepath\s*=|render\.render)')
with open(MK['szene'], encoding='utf-8') as _f:
    CODE = _f.read()
_treffer = VERBOTEN.search(CODE)
if _treffer:
    raise SystemExit('SZENE ABGELEHNT: unerlaubter Ausdruck ' + _treffer.group(0)[:40])

# ------------------------------------------------------------------ Grundlage
bpy.ops.wm.read_factory_settings(use_empty=True)
sc = bpy.context.scene
sc.unit_settings.system = 'METRIC'; sc.unit_settings.scale_length = 1.0


def hdri(name, dreh=0.0, staerke=1.0):
    """Echte Aufnahme als Licht UND Hintergrund (Poly Haven, CC0)."""
    pfad = os.path.join(Q, name)
    if not os.path.exists(pfad):
        raise ValueError('HDRI unbekannt: ' + name)
    w = bpy.data.worlds.new('Ort'); sc.world = w; w.use_nodes = True
    nt = w.node_tree; nt.nodes.clear()
    tk = nt.nodes.new('ShaderNodeTexCoord'); mp = nt.nodes.new('ShaderNodeMapping')
    mp.inputs['Rotation'].default_value[2] = math.radians(dreh)
    tx = nt.nodes.new('ShaderNodeTexEnvironment'); tx.image = bpy.data.images.load(pfad, check_existing=True)
    bg = nt.nodes.new('ShaderNodeBackground'); bg.inputs['Strength'].default_value = staerke
    aus = nt.nodes.new('ShaderNodeOutputWorld')
    nt.links.new(tk.outputs['Generated'], mp.inputs['Vector']); nt.links.new(mp.outputs['Vector'], tx.inputs['Vector'])
    nt.links.new(tx.outputs['Color'], bg.inputs['Color']); nt.links.new(bg.outputs['Background'], aus.inputs['Surface'])
    return w


def modell(name, ort=(0, 0, 0), dreh=0.0, skala=1.0):
    """Gescanntes Modell von Poly Haven (glTF, echte Masse in Metern). Gibt die Objekte zurueck."""
    pfad = os.path.join(Q, name, name + '_2k.gltf')
    if not os.path.exists(pfad):
        raise ValueError('Modell unbekannt: ' + name)
    vorher = set(bpy.data.objects)
    bpy.ops.import_scene.gltf(filepath=pfad)
    neu = [o for o in bpy.data.objects if o not in vorher]
    wurzeln = [o for o in neu if o.parent is None]
    for o in wurzeln:
        o.location = Vector(ort); o.rotation_euler[2] += math.radians(dreh); o.scale = (skala, skala, skala)
    return neu


def pbr(name, kachel=1.0):
    """PBR-Stoff aus einem Poly-Haven-Texturordner (Farbe, Rauheit, Normalen)."""
    ordner = os.path.join(Q, name)
    dateien = os.listdir(ordner) if os.path.isdir(ordner) else []
    finde = lambda teil: next((os.path.join(ordner, d) for d in dateien if teil in d.lower()), None)
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes.get('Principled BSDF')
    tk = nt.nodes.new('ShaderNodeTexCoord'); mp = nt.nodes.new('ShaderNodeMapping')
    mp.inputs['Scale'].default_value = (kachel, kachel, kachel); nt.links.new(tk.outputs['UV'], mp.inputs['Vector'])

    def bild(pfad, farbe):
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = bpy.data.images.load(pfad, check_existing=True)
        if not farbe:
            t.image.colorspace_settings.name = 'Non-Color'
        nt.links.new(mp.outputs['Vector'], t.inputs['Vector']); return t
    d = finde('diff') or finde('col')
    if d:
        nt.links.new(bild(d, True).outputs['Color'], b.inputs['Base Color'])
    r = finde('rough')
    if r:
        nt.links.new(bild(r, False).outputs['Color'], b.inputs['Roughness'])
    n = finde('nor_gl') or finde('normal')
    if n:
        nm = nt.nodes.new('ShaderNodeNormalMap'); nt.links.new(bild(n, False).outputs['Color'], nm.inputs['Color'])
        nt.links.new(nm.outputs['Normal'], b.inputs['Normal'])
    return m


def mat(name, farbe, rauheit=0.5, metall=0.0, transmission=0.0, ior=1.45, variation=0.08):
    """Principled-Material mit leichter Rauheitsvariation (kein perfekter CG-Glanz)."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes.get('Principled BSDF')
    b.inputs['Base Color'].default_value = (*farbe[:3], 1.0)
    b.inputs['Metallic'].default_value = metall
    b.inputs['IOR'].default_value = ior
    b.inputs['Transmission Weight'].default_value = transmission
    rau = nt.nodes.new('ShaderNodeTexNoise'); rau.inputs['Scale'].default_value = 40.0
    mr = nt.nodes.new('ShaderNodeMapRange')
    mr.inputs['To Min'].default_value = max(0.0, rauheit - variation); mr.inputs['To Max'].default_value = min(1.0, rauheit + variation)
    nt.links.new(rau.outputs['Fac'], mr.inputs['Value']); nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    return m


def holz(name, farbe=(0.28, 0.16, 0.08), maserung=1.0, rauheit=0.42):
    """Prozedurales Holz: Jahresringe (Welle + Rauschen), helle und dunkle Toene, Rauheit und Relief folgen der Maserung.
    Probe 01.10.2026: einfarbiges Holz wirkte wie lackiertes Plastik."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes.get('Principled BSDF')
    tk = nt.nodes.new('ShaderNodeTexCoord'); mp = nt.nodes.new('ShaderNodeMapping')
    mp.inputs['Scale'].default_value = (1.0 * maserung, 8.0 * maserung, 1.0 * maserung)
    nt.links.new(tk.outputs['Object'], mp.inputs['Vector'])
    ws = nt.nodes.new('ShaderNodeTexWave'); ws.inputs['Scale'].default_value = 6.0; ws.inputs['Distortion'].default_value = 7.0
    ws.inputs['Detail'].default_value = 3.0; ws.inputs['Detail Scale'].default_value = 1.6
    nt.links.new(mp.outputs['Vector'], ws.inputs['Vector'])
    rampe = nt.nodes.new('ShaderNodeValToRGB')
    rampe.color_ramp.elements[0].color = (farbe[0] * 0.55, farbe[1] * 0.55, farbe[2] * 0.55, 1)
    rampe.color_ramp.elements[1].color = (min(1, farbe[0] * 1.35), min(1, farbe[1] * 1.35), min(1, farbe[2] * 1.35), 1)
    nt.links.new(ws.outputs['Fac'], rampe.inputs['Fac']); nt.links.new(rampe.outputs['Color'], b.inputs['Base Color'])
    mr = nt.nodes.new('ShaderNodeMapRange'); mr.inputs['To Min'].default_value = rauheit - 0.08; mr.inputs['To Max'].default_value = rauheit + 0.1
    nt.links.new(ws.outputs['Fac'], mr.inputs['Value']); nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    bu = nt.nodes.new('ShaderNodeBump'); bu.inputs['Strength'].default_value = 0.08; bu.inputs['Distance'].default_value = 0.0006
    nt.links.new(ws.outputs['Fac'], bu.inputs['Height']); nt.links.new(bu.outputs['Normal'], b.inputs['Normal'])
    return m


def fase(obj, breite=0.003, segmente=3):
    """Gefaste Kanten -- echte Dinge haben keine messerscharfen Kanten."""
    mo = obj.modifiers.new('Fase', 'BEVEL'); mo.width = breite; mo.segments = segmente; mo.limit_method = 'ANGLE'
    return mo


def boden(groesse=12.0, material=None):
    """Boden: mit Material sichtbar, ohne Material Schattenfaenger (Kontaktschatten auf dem HDRI-Boden)."""
    bpy.ops.mesh.primitive_plane_add(size=groesse, location=(0, 0, 0))
    o = bpy.context.active_object; o.name = 'Boden'
    if material is not None:
        o.data.materials.append(material)
    else:
        o.is_shadow_catcher = True
    return o


def kamera(ort, ziel, lens=50.0, blende=4.0):
    """Kamera mit echter Brennweite (Vollformat 36 mm) und Schaerfe auf das Ziel."""
    cd = bpy.data.cameras.new('Kamera'); cd.lens = lens; cd.sensor_width = 36.0; cd.sensor_fit = 'HORIZONTAL'
    c = bpy.data.objects.new('Kamera', cd); sc.collection.objects.link(c); sc.camera = c
    c.location = Vector(ort)
    c.rotation_euler = (Vector(ziel) - Vector(ort)).to_track_quat('-Z', 'Y').to_euler()
    cd.dof.use_dof = True; cd.dof.focus_distance = (Vector(ziel) - Vector(ort)).length
    cd.dof.aperture_fstop = blende; cd.dof.aperture_blades = 9
    return c


def flaechenlicht(ort, ziel, groesse=(1.0, 1.0), leistung=200.0, kelvin=5000):
    """Flaechenlicht mit Herkunft (Fenster, Softbox) -- fuer die Kamera unsichtbar."""
    ld = bpy.data.lights.new('Licht', 'AREA'); ld.shape = 'RECTANGLE'; ld.size, ld.size_y = groesse; ld.energy = leistung
    try:
        ld.use_temperature = True; ld.temperature = kelvin
    except Exception:
        pass
    o = bpy.data.objects.new('Licht', ld); sc.collection.objects.link(o)
    o.location = Vector(ort); o.rotation_euler = (Vector(ziel) - Vector(ort)).to_track_quat('-Z', 'Y').to_euler()
    o.visible_camera = False
    return o


# ------------------------------------------------------------------ Claudes Szene
_ns = {'bpy': bpy, 'bmesh': bmesh, 'math': math, 'Vector': Vector, 'zufall': random.Random(int(MK.get('seed', 0))),
       'hdri': hdri, 'modell': modell, 'pbr': pbr, 'mat': mat, 'holz': holz, 'fase': fase, 'boden': boden, 'kamera': kamera,
       'flaechenlicht': flaechenlicht, 'szene': sc}
t_bau = time.time()
exec(compile(CODE, 'claude_szene', 'exec'), _ns)
BERICHT['bau_sekunden'] = round(time.time() - t_bau, 1)
if sc.camera is None:
    raise SystemExit('SZENE OHNE KAMERA')
if sc.world is None:
    hdri('photo_studio_loft_hall_2k.exr')

# ------------------------------------------------------------------ Rendern
r = sc.render
r.engine = 'CYCLES'
prefs = bpy.context.preferences.addons['cycles'].preferences
for typ in ('OPTIX', 'CUDA'):
    try:
        prefs.compute_device_type = typ; prefs.get_devices()
        if any(d.type == typ for d in prefs.devices):
            for d in prefs.devices:
                d.use = d.type == typ
            break
    except Exception:
        pass
sc.cycles.device = 'GPU'
r.resolution_x, r.resolution_y, r.resolution_percentage = PX[0], PX[1], 100
sc.cycles.samples = int(MK.get('samples', 768)); sc.cycles.adaptive_threshold = 0.005
sc.cycles.use_denoising = True
sc.cycles.denoiser = 'OPTIX' if prefs.compute_device_type == 'OPTIX' else 'OPENIMAGEDENOISE'
sc.cycles.max_bounces = 24; sc.cycles.glossy_bounces = 12; sc.cycles.transmission_bounces = 24
sc.view_settings.view_transform = 'AgX'
for _look in ('AgX - Base Contrast', 'None'):
    try:
        sc.view_settings.look = _look; break
    except TypeError:
        pass
r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'; r.image_settings.color_depth = '8'
r.film_transparent = False


def _leuchtdichte(pfad):
    import numpy as np
    bild = bpy.data.images.load(pfad, check_existing=False)
    px = np.array(bild.pixels[:], dtype=np.float32).reshape(-1, 4)[:, :3]
    bpy.data.images.remove(bild)
    lum = px @ np.array([0.2126, 0.7152, 0.0722], dtype=np.float32)
    return float(lum.mean()), float((px.max(axis=1) > 0.985).mean())


os.makedirs(os.path.dirname(AUS), exist_ok=True)
_probe = os.path.join(os.path.dirname(AUS), 'probe-' + os.path.basename(AUS).rsplit('.', 1)[0] + '.png')
r.resolution_percentage = 25; _s = sc.cycles.samples; sc.cycles.samples = 48; r.filepath = _probe
bpy.ops.render.render(write_still=True)
_mittel, _hell = _leuchtdichte(_probe)
_ev = 0.0
if _mittel < 0.16 or _mittel > 0.66 or _hell > 0.03:
    _ziel = 0.42 if _hell <= 0.03 else min(0.40, _mittel * 0.8)
    _ev = max(-1.5, min(1.5, math.log2(max(1e-4, _ziel) / max(1e-4, _mittel)) * 2.2))
    sc.view_settings.exposure += _ev
BERICHT.update(probe_mittel=round(_mittel, 3), probe_ausgebrannt=round(_hell, 4), korrektur_ev=round(_ev, 2))
try:
    os.remove(_probe)
except OSError:
    pass
r.resolution_percentage = 100; sc.cycles.samples = _s
t0 = time.time()
if MODUS == 'marketing_film':
    # Fahrt um den Schaerfepunkt: +-15 Grad, leicht heran, weich an- und auslaufen.
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    import marketing_schnitt
    cam = sc.camera; cd = cam.data
    vorne = cam.matrix_world.to_quaternion() @ Vector((0.0, 0.0, -1.0))
    ziel = cam.location + vorne * (cd.dof.focus_distance or 3.0)
    d0 = cam.location - ziel
    w0 = math.atan2(-d0.x, -d0.y); rad0 = math.hypot(d0.x, d0.y); h0 = d0.z
    FPS = 24; N = max(48, int(FPS * float(MK.get('sekunden', 8))))
    spanne = math.radians(float(MK.get('spanne', 30.0)))
    sc.cycles.samples = int(MK.get('samples', 160)); sc.cycles.adaptive_threshold = 0.012
    r.use_persistent_data = True
    ordner = os.path.join(os.path.dirname(AUS), 'bilder-' + os.path.basename(AUS).rsplit('.', 1)[0])
    os.makedirs(ordner, exist_ok=True)
    for f in range(N):
        t = f / (N - 1); e = t * t * (3 - 2 * t)
        w = w0 - spanne / 2 + spanne * e; rad = rad0 * (1.0 - 0.08 * e)
        pos = Vector((ziel.x - math.sin(w) * rad, ziel.y - math.cos(w) * rad, ziel.z + h0))
        cam.location = pos; cam.rotation_euler = (ziel - pos).to_track_quat('-Z', 'Y').to_euler()
        cd.dof.focus_distance = (ziel - pos).length
        pfad = os.path.join(ordner, 'bild-%04d.png' % f)
        if not os.path.exists(pfad):
            r.filepath = pfad; bpy.ops.render.render(write_still=True)
        print('FILM', f + 1, '/', N, flush=True)
    marketing_schnitt.titel_film(ordner, N, FPS, AUS, PX, MK.get('titel', ''), MK.get('abspann', ''))
    _m2, _h2 = _leuchtdichte(os.path.join(ordner, 'bild-%04d.png' % (N // 2)))
    BERICHT.update(modus='marketing_film', bilder=N)
else:
    r.filepath = AUS
    bpy.ops.render.render(write_still=True)
    _m2, _h2 = _leuchtdichte(AUS)
BERICHT.update(sekunden=round(time.time() - t0, 1), mittel=round(_m2, 3), ausgebrannt=round(_h2, 4))
with open(AUS.rsplit('.', 1)[0] + '.json', 'w', encoding='utf-8') as _f:
    json.dump(BERICHT, _f, ensure_ascii=False, indent=1)
print('MARKETING FERTIG', AUS, BERICHT['sekunden'], 's')
