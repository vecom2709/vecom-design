"""film_unreal_export.py -- die Gasse aus film-sichtbar.blend für den
Vergleich Blender/Unreal (Uwe, 28.09.2026: „Vergleich Blender vs. Unreal").

Blender baut, Unreal bekommt eine Kopie: Geometrie mit Texturen als GLB,
dazu Lichter und Kameras als JSON (Blender-Koordinaten, Meter; Umrechnung
macht die Unreal-Seite). Zustand der Szene bei Bild STAND (alle Rollläden
oben, alle Läden an).

Prozedurale Knoten (Pfützen, Feuchte, Regenspuren) kennt glTF nicht. Jedes
Material wird deshalb vor dem Export auf die Karten reduziert, die glTF trägt
(Farbe, Rauheit, Normal, Emission) -- der Unterschied ist Teil des Vergleichs
und wird in Unreal mit Material-Parametern nachgezogen (Nässe über Rauheit).

Aufruf: blender -b film-sichtbar.blend -P film_unreal_export.py -- [stand] [bilder]
"""
import bpy, os, sys, json, math
from mathutils import Vector

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
STAND = int(argv[0]) if argv else 420
BILDER = [int(x) for x in (argv[1] if len(argv) > 1 else '358,420,640').split(',')]
FILM = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\film-sichtbar'
ZIEL = os.path.join(FILM, 'unreal'); os.makedirs(ZIEL, exist_ok=True)
sc = bpy.context.scene
sc.frame_set(STAND)


def schwarzkoerper(k):
    """RGB (linear, 0..1) einer Farbtemperatur -- Näherung nach Tanner Helland."""
    t = k / 100.0
    r = 255.0 if t <= 66 else 329.698727446 * ((t - 60) ** -0.1332047592)
    g = 99.4708025861 * math.log(t) - 161.1195681661 if t <= 66 else 288.1221695283 * ((t - 60) ** -0.0755148492)
    b = 255.0 if t >= 66 else (0.0 if t <= 19 else 138.5177312231 * math.log(t - 10) - 305.0447927307)
    c = [max(0.0, min(255.0, x)) / 255.0 for x in (r, g, b)]
    return tuple((x / 12.92) if x <= 0.04045 else ((x + 0.055) / 1.055) ** 2.4 for x in c)


def bild_quelle(sock, tiefe=0):
    """Erstes Bild hinter einem Eingang (durch Mix/Mapping/NormalMap hindurch)."""
    if tiefe > 6 or not sock.is_linked:
        return None
    n = sock.links[0].from_node
    if n.type == 'TEX_IMAGE':
        return n.image
    for i in n.inputs:
        if i.is_linked:
            im = bild_quelle(i, tiefe + 1)
            if im is not None:
                return im
    return None


def vereinfachen(m):
    """Material auf glTF-taugliche Karten reduzieren (Kopie, Original bleibt)."""
    if not m.use_nodes:
        return
    nt = m.node_tree
    b = next((n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED'), None)
    em = next((n for n in nt.nodes if n.type == 'EMISSION'), None)
    farbe = rau = nrm = e_bild = None; basis = (0.8, 0.8, 0.8, 1); r = 0.5; metall = 0.0; trans = 0.0; e_col = None; e_st = 0.0
    if b is not None:
        farbe = bild_quelle(b.inputs['Base Color']); rau = bild_quelle(b.inputs['Roughness'])
        nrm = bild_quelle(b.inputs['Normal'])
        if not b.inputs['Base Color'].is_linked:
            basis = tuple(b.inputs['Base Color'].default_value)
        if not b.inputs['Roughness'].is_linked:
            r = b.inputs['Roughness'].default_value
        metall = b.inputs['Metallic'].default_value; trans = b.inputs['Transmission Weight'].default_value
        e_st = b.inputs['Emission Strength'].default_value
        e_bild = bild_quelle(b.inputs['Emission Color'])      # Bildschirme: die Website selbst leuchtet
        if e_st > 0:
            bb = next((n for n in nt.nodes if n.type == 'BLACKBODY'), None)
            e_col = schwarzkoerper(bb.inputs['Temperature'].default_value) if bb else tuple(b.inputs['Emission Color'].default_value)[:3]
    elif em is not None:
        bb = next((n for n in nt.nodes if n.type == 'BLACKBODY'), None)
        e_col = schwarzkoerper(bb.inputs['Temperature'].default_value) if bb else (1, 0.8, 0.6)
        e_st = em.inputs['Strength'].default_value; basis = (0.02, 0.02, 0.02, 1)
    for n in list(nt.nodes):
        nt.nodes.remove(n)
    out = nt.nodes.new('ShaderNodeOutputMaterial'); p = nt.nodes.new('ShaderNodeBsdfPrincipled')
    nt.links.new(p.outputs[0], out.inputs['Surface'])
    p.inputs['Base Color'].default_value = basis; p.inputs['Roughness'].default_value = r
    p.inputs['Metallic'].default_value = metall; p.inputs['Transmission Weight'].default_value = trans
    uvn = None
    if farbe:
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = farbe; nt.links.new(t.outputs['Color'], p.inputs['Base Color'])
    if rau:
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = rau; nt.links.new(t.outputs['Color'], p.inputs['Roughness'])
    if nrm:
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = nrm; nm = nt.nodes.new('ShaderNodeNormalMap')
        nt.links.new(t.outputs['Color'], nm.inputs['Color']); nt.links.new(nm.outputs['Normal'], p.inputs['Normal'])
    if e_bild is not None:
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = e_bild; nt.links.new(t.outputs['Color'], p.inputs['Emission Color'])
        p.inputs['Emission Strength'].default_value = max(e_st, 1.0)
    elif e_col is not None and e_st > 0:
        p.inputs['Emission Color'].default_value = (*e_col, 1); p.inputs['Emission Strength'].default_value = e_st


# ---------------------------------------------------------------- Auswahl: nur die Gasse
def in_gasse(o):
    w = o.matrix_world.translation
    if o.name.startswith(('stadthaus', 'stadt_bauten', 'stadtlaternen', 'gelaende', 'meer', 'logo', 'teilchen', 'adresse')):
        return False
    return abs(w.x) < 14 and -14 < w.y < 72


for m in bpy.data.materials:
    try:
        vereinfachen(m)
    except Exception as e:
        print('MATERIAL', m.name, e)
bpy.ops.object.select_all(action='DESELECT')
auswahl = [o for o in sc.objects if o.type in ('MESH', 'CURVE', 'EMPTY') and not o.hide_render and in_gasse(o)]
for o in auswahl:
    o.select_set(True)
bpy.context.view_layer.objects.active = auswahl[0]
glb = os.path.join(ZIEL, 'gasse.glb')
bpy.ops.export_scene.gltf(filepath=glb, export_format='GLB', use_selection=True, export_apply=True,
                          export_cameras=False, export_lights=False, export_animations=False,
                          export_image_format='JPEG', export_jpeg_quality=92, export_yup=True)

# ---------------------------------------------------------------- Lichter und Kameras
def kelvin_farbe(ld):
    if ld.use_nodes and ld.node_tree:
        bb = next((n for n in ld.node_tree.nodes if n.type == 'BLACKBODY'), None)
        if bb:
            return schwarzkoerper(bb.inputs['Temperature'].default_value)
    return tuple(ld.color)


daten = {'stand': STAND, 'lichter': [], 'kameras': {}, 'welt': {}}
for o in sc.objects:
    if o.type == 'LIGHT' and in_gasse(o) and not o.name.startswith('logo_'):
        ld = o.data
        daten['lichter'].append({'name': o.name, 'art': ld.type, 'ort': list(o.matrix_world.translation),
                                 'rot': list(o.matrix_world.to_euler()), 'energie_w': float(ld.energy),
                                 'farbe': list(kelvin_farbe(ld)), 'groesse': float(getattr(ld, 'size', 0.0)),
                                 'groesse_y': float(getattr(ld, 'size_y', 0.0)), 'form': getattr(ld, 'shape', ''),
                                 'radius': float(getattr(ld, 'shadow_soft_size', 0.0)), 'winkel': float(getattr(ld, 'angle', 0.0))})
mond = bpy.data.objects.get('Mond')
if mond:
    daten['lichter'].append({'name': 'Mond', 'art': 'SUN', 'ort': [0, 0, 50], 'rot': list(mond.matrix_world.to_euler()),
                             'energie_w': float(mond.data.energy), 'farbe': list(mond.data.color), 'winkel': float(mond.data.angle)})
bg = sc.world.node_tree.nodes.get('Background')
daten['welt'] = {'staerke': float(bg.inputs['Strength'].default_value) if bg else 0.1, 'belichtung': sc.view_settings.exposure}
for f in BILDER:
    sc.frame_set(f)
    cam = sc.camera
    for mk in sorted(sc.timeline_markers, key=lambda m: m.frame):
        if mk.frame <= f and mk.camera:
            cam = mk.camera
    cd = cam.data
    daten['kameras'][str(f)] = {'name': cam.name, 'ort': list(cam.matrix_world.translation), 'rot': list(cam.matrix_world.to_euler()),
                                'brennweite': cd.lens, 'sensor': cd.sensor_width, 'blende': cd.dof.aperture_fstop,
                                'fokus': cd.dof.focus_distance}
with open(os.path.join(ZIEL, 'gasse.json'), 'w') as fh:
    json.dump(daten, fh, indent=1)
print('EXPORT FERTIG', len(auswahl), 'Objekte,', len(daten['lichter']), 'Lichter', flush=True)
