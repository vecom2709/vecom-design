"""Intro „Gegossenes Gold" (Uwe, 24.09.2026: I1).

Im Dunkeln rinnt ein Faden flüssiges Gold in eine Form aus Graphit und
füllt das V. Es glüht, kühlt ab, erstarrt zu gebürstetem Gold, das V hebt
sich aus der Form, richtet sich auf und steht dort, wo auf der Startseite
das Echtzeit-V steht. 78 Bilder bei 24 B/s = 3,25 s.

Warum prozedural statt Flüssigkeitssimulation: Mantaflow braucht für eine
3-mm-Kante eine Auflösung, die in dieser Zeit nicht rechenbar ist, und sieht
bei dieser Kürze nicht besser aus als eine gezielt geführte Füllfront.
Echt ist hier, was das Auge prüft: Glühfarbe nach Planck (Schwarzkörper),
Abkühlen von der zuerst gefüllten Stelle her, flüssiger Glanz, der beim
Erstarren in Bürstung übergeht, Funken mit Bewegungsunschärfe, und das
Glühen beleuchtet die Form wirklich (Emission in Cycles ist Licht).

Aufruf: blender -b --factory-startup --python intro_gold.py -- <modus> [bilder]
  probe          960x540, 96 Samples, Bilder 3,15,30,44,60,78
  quer | hoch    1920x1080 bzw. 1080x1920, alle Bilder (oder die angegebenen)
"""
import bpy, sys, os, math, bmesh
from mathutils import Vector

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
MODUS = argv[0] if argv else 'probe'
BILDER = [int(x) for x in argv[1].split(',')] if len(argv) > 1 else None
BASIS = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL = os.path.join(BASIS, 'render', 'intro', MODUS)
os.makedirs(ZIEL, exist_ok=True)

GOLD = (1.0, 0.766, 0.336, 1.0)
F_START, F_VOLL, F_KALT, F_ENDE = 5, 44, 58, 78

sc = bpy.context.scene
for o in list(sc.objects): bpy.data.objects.remove(o, do_unlink=True)

def knoten(nt, typ, x, y, **werte):
    n = nt.nodes.new(typ); n.location = (x, y)
    for k, v in werte.items():
        if k in n.inputs: n.inputs[k].default_value = v
        else: setattr(n, k, v)
    return n

def schluessel(obj_or_socket, pfad, werte, interp='BEZIER'):
    for f, v in werte:
        setattr(obj_or_socket, pfad, v) if not hasattr(obj_or_socket, 'default_value') else None
        if hasattr(obj_or_socket, 'default_value'):
            obj_or_socket.default_value = v; obj_or_socket.keyframe_insert('default_value', frame=f)
        else:
            obj_or_socket.keyframe_insert(pfad, frame=f)

# ---------------------------------------------------------------- Marke
with bpy.data.libraries.load(os.path.join(BASIS, 'vecom-showroom_Farm1080c.blend'), link=False) as (q, z):
    z.objects = ['Marke_V']
v = z.objects[0]; sc.collection.objects.link(v)
v.data = v.data.copy()
v.rotation_euler = (0, 0, 0); v.location = (0, 0, -0.174 * 2.05)
me = v.data
# Flusskoordinate: 0 = Einlauf oben am linken Schenkel, 0,55 = Spitze,
# rechter Teil füllt von seinem unteren Ende nach oben (0,45 .. 1).
att = me.attributes.new('fluss', 'FLOAT', 'POINT')
for i, p in enumerate(me.vertices):
    x, y = p.co.x, p.co.y
    if x < 0.0 or (x < 0.34 and y < -0.43):
        u = 0.55 * (0.99 - y) / 2.0
    else:
        u = 0.45 + 0.55 * (y + 0.434) / (0.985 + 0.434)
    att.data[i].value = max(0.0, min(1.0, u))

# ---------------------------------------------------------------- Material Guss
m = bpy.data.materials.new('M_Guss'); m.use_nodes = True
nt = m.node_tree; nt.nodes.clear()
aus = knoten(nt, 'ShaderNodeOutputMaterial', 900, 0)
mixs = knoten(nt, 'ShaderNodeMixShader', 700, 0)
transp = knoten(nt, 'ShaderNodeBsdfTransparent', 450, 150)
bsdf = knoten(nt, 'ShaderNodeBsdfPrincipled', 400, -150)
bsdf.inputs['Base Color'].default_value = GOLD
bsdf.inputs['Metallic'].default_value = 1.0
bsdf.inputs['Specular Tint'].default_value = (1.0, 0.93, 0.74, 1.0)
at = knoten(nt, 'ShaderNodeAttribute', -1400, 0, attribute_name='fluss')
fuell = knoten(nt, 'ShaderNodeValue', -1400, -200); fuell.name = fuell.label = 'fuellung'
glut = knoten(nt, 'ShaderNodeValue', -1400, -300); glut.name = glut.label = 'glut'
starr = knoten(nt, 'ShaderNodeValue', -1400, -400); starr.name = starr.label = 'erstarrt'
tc = knoten(nt, 'ShaderNodeTexCoord', -1600, 300)
rausch = knoten(nt, 'ShaderNodeTexNoise', -1400, 300); rausch.inputs['Scale'].default_value = 7.0
nt.links.new(tc.outputs['Object'], rausch.inputs['Vector'])
# alter = fuellung - fluss + kleine Welligkeit der Front
alter = knoten(nt, 'ShaderNodeMath', -1150, 0, operation='SUBTRACT')
nt.links.new(fuell.outputs[0], alter.inputs[0]); nt.links.new(at.outputs['Fac'], alter.inputs[1])
welle = knoten(nt, 'ShaderNodeMath', -1150, 250, operation='MULTIPLY_ADD')
welle.inputs[1].default_value = 0.035; welle.inputs[2].default_value = -0.0175
nt.links.new(rausch.outputs['Fac'], welle.inputs[0])
alter2 = knoten(nt, 'ShaderNodeMath', -950, 100, operation='ADD')
nt.links.new(alter.outputs[0], alter2.inputs[0]); nt.links.new(welle.outputs[0], alter2.inputs[1])
maske = knoten(nt, 'ShaderNodeMapRange', -750, 300, interpolation_type='SMOOTHSTEP')
maske.inputs['From Min'].default_value = -0.004; maske.inputs['From Max'].default_value = 0.006
nt.links.new(alter2.outputs[0], maske.inputs['Value'])
nt.links.new(maske.outputs['Result'], mixs.inputs['Fac'])
# Temperatur: frisch 1650 K (gelborange Glut), nach 0,35 Fluss-Einheiten 700 K
temp = knoten(nt, 'ShaderNodeMapRange', -750, 0)
temp.inputs['From Min'].default_value = 0.0; temp.inputs['From Max'].default_value = 0.5
temp.inputs['To Min'].default_value = 1650; temp.inputs['To Max'].default_value = 950
nt.links.new(alter2.outputs[0], temp.inputs['Value'])
# Glutfarbe: Die reine Planck-Farbe bei 1400 K kippte unter AgX ins Lachsrosa.
# Flüssiges Gold leuchtet gelborange -- seine eigene Reflexionsfarbe (Gold
# schluckt Blau) färbt die Glut mit. Also Planck mal Goldfarbe.
bb = knoten(nt, 'ShaderNodeBlackbody', -550, 0)
nt.links.new(temp.outputs['Result'], bb.inputs['Temperature'])
gm = knoten(nt, 'ShaderNodeMix', -350, 50, data_type='RGBA', blend_type='MULTIPLY')
gm.inputs['Factor'].default_value = 1.0; gm.inputs['B'].default_value = (1.0, 0.82, 0.42, 1.0)
nt.links.new(bb.outputs['Color'], gm.inputs['A'])
nt.links.new(gm.outputs['Result'], bsdf.inputs['Emission Color'])
staerke = knoten(nt, 'ShaderNodeMapRange', -750, -250, interpolation_type='SMOOTHSTEP')
staerke.inputs['From Min'].default_value = 0.0; staerke.inputs['From Max'].default_value = 0.45
staerke.inputs['To Min'].default_value = 0.38; staerke.inputs['To Max'].default_value = 0.0
nt.links.new(alter2.outputs[0], staerke.inputs['Value'])
st2 = knoten(nt, 'ShaderNodeMath', -550, -250, operation='MULTIPLY')
nt.links.new(staerke.outputs['Result'], st2.inputs[0]); nt.links.new(glut.outputs[0], st2.inputs[1])
nt.links.new(st2.outputs[0], bsdf.inputs['Emission Strength'])
# fest: aus dem Alter, am Ende für alle erzwungen
fest = knoten(nt, 'ShaderNodeMapRange', -750, -500, interpolation_type='SMOOTHSTEP')
fest.inputs['From Min'].default_value = 0.15; fest.inputs['From Max'].default_value = 0.55
nt.links.new(alter2.outputs[0], fest.inputs['Value'])
fest2 = knoten(nt, 'ShaderNodeMath', -550, -500, operation='MAXIMUM')
nt.links.new(fest.outputs['Result'], fest2.inputs[0]); nt.links.new(starr.outputs[0], fest2.inputs[1])
rau = knoten(nt, 'ShaderNodeMapRange', -350, -500)
rau.inputs['To Min'].default_value = 0.025; rau.inputs['To Max'].default_value = 0.21
nt.links.new(fest2.outputs[0], rau.inputs['Value'])
nt.links.new(rau.outputs['Result'], bsdf.inputs['Roughness'])
aniso = knoten(nt, 'ShaderNodeMath', -350, -650, operation='MULTIPLY'); aniso.inputs[1].default_value = 0.55
nt.links.new(fest2.outputs[0], aniso.inputs[0]); nt.links.new(aniso.outputs[0], bsdf.inputs['Anisotropic'])
tang = knoten(nt, 'ShaderNodeTangent', -350, -800, direction_type='RADIAL', axis='X')
nt.links.new(tang.outputs['Tangent'], bsdf.inputs['Tangent'])
# Flüssige Oberfläche: bewegte Wellung, verschwindet beim Erstarren
zeit = knoten(nt, 'ShaderNodeValue', -1400, -600); zeit.name = zeit.label = 'zeit'
flw = knoten(nt, 'ShaderNodeTexNoise', -1150, -700, noise_dimensions='4D')
flw.inputs['Scale'].default_value = 3.2; flw.inputs['Detail'].default_value = 4.0
nt.links.new(tc.outputs['Object'], flw.inputs['Vector']); nt.links.new(zeit.outputs[0], flw.inputs['W'])
bumpst = knoten(nt, 'ShaderNodeMapRange', -950, -800)
bumpst.inputs['To Min'].default_value = 0.45; bumpst.inputs['To Max'].default_value = 0.0
nt.links.new(fest2.outputs[0], bumpst.inputs['Value'])
bump = knoten(nt, 'ShaderNodeBump', -150, -700)
nt.links.new(flw.outputs['Fac'], bump.inputs['Height']); nt.links.new(bumpst.outputs['Result'], bump.inputs['Strength'])
# Schleifspuren im erstarrten Gold
tc2 = knoten(nt, 'ShaderNodeMapping', -1150, -950); tc2.inputs['Scale'].default_value = (60.0, 1.0, 1.0)
nt.links.new(tc.outputs['Object'], tc2.inputs['Vector'])
schl = knoten(nt, 'ShaderNodeTexNoise', -950, -1000); schl.inputs['Scale'].default_value = 40.0
nt.links.new(tc2.outputs['Vector'], schl.inputs['Vector'])
bumps = knoten(nt, 'ShaderNodeMath', -750, -1000, operation='MULTIPLY'); bumps.inputs[1].default_value = 0.035
nt.links.new(fest2.outputs[0], bumps.inputs[0])
bump2 = knoten(nt, 'ShaderNodeBump', 50, -900)
nt.links.new(schl.outputs['Fac'], bump2.inputs['Height']); nt.links.new(bumps.outputs[0], bump2.inputs['Strength'])
nt.links.new(bump.outputs['Normal'], bump2.inputs['Normal'])
nt.links.new(bump2.outputs['Normal'], bsdf.inputs['Normal'])
nt.links.new(transp.outputs[0], mixs.inputs[1]); nt.links.new(bsdf.outputs[0], mixs.inputs[2])
nt.links.new(mixs.outputs[0], aus.inputs['Surface'])
v.data.materials.clear(); v.data.materials.append(m)
schluessel(fuell.outputs[0], None, [(1, 0.0), (F_START, 0.0), (F_VOLL, 1.0)])
fuell.outputs[0].default_value = 0.0
for f, w in [(1, -0.06), (F_START, -0.06), (F_VOLL, 1.02)]:
    fuell.outputs[0].default_value = w; fuell.outputs[0].keyframe_insert('default_value', frame=f)
for f, w in [(1, 1.0), (F_VOLL, 1.0), (F_KALT, 0.0)]:
    glut.outputs[0].default_value = w; glut.outputs[0].keyframe_insert('default_value', frame=f)
for f, w in [(1, 0.0), (F_VOLL - 2, 0.0), (F_KALT + 2, 1.0)]:
    starr.outputs[0].default_value = w; starr.outputs[0].keyframe_insert('default_value', frame=f)
for f, w in [(1, 0.0), (F_ENDE, 2.6)]:
    zeit.outputs[0].default_value = w; zeit.outputs[0].keyframe_insert('default_value', frame=f)

# Aufrichten: aus der Form heben, aufstellen (Rückseite der Marke zur Kamera = lokale +Z)
for f, loc, rot in [(1, (0, 0, -0.357), 0.0), (F_KALT, (0, 0, -0.357), 0.0), (F_ENDE, (0, 0.0, 2.25), math.radians(90))]:
    v.location = loc; v.rotation_euler = (rot, 0, 0)
    v.keyframe_insert('location', frame=f); v.keyframe_insert('rotation_euler', frame=f)

# ---------------------------------------------------------------- Form aus Graphit
bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0, -0.7)); form = bpy.context.active_object
form.name = 'Form'; form.scale = (16, 14, 1.4)
schn = v.copy(); schn.data = v.data.copy(); schn.animation_data_clear(); sc.collection.objects.link(schn)
schn.name = 'Formschnitt'; schn.scale = (2.05 * 1.012, 2.05 * 1.012, 2.05 * 1.1); schn.location = (0, 0, -0.30)
schn.rotation_euler = (0, 0, 0); schn.hide_render = True; schn.hide_viewport = True
bo = form.modifiers.new('Rinne', 'BOOLEAN'); bo.object = schn; bo.operation = 'DIFFERENCE'; bo.solver = 'EXACT'
fm = bpy.data.materials.new('M_Graphit'); fm.use_nodes = True
fnt = fm.node_tree; fb = fnt.nodes['Principled BSDF']
fb.inputs['Base Color'].default_value = (0.012, 0.0115, 0.011, 1)
fb.inputs['Specular IOR Level'].default_value = 0.25
fr = knoten(fnt, 'ShaderNodeTexNoise', -700, -200); fr.inputs['Scale'].default_value = 3.0
frm = knoten(fnt, 'ShaderNodeMapRange', -450, -200)
frm.inputs['To Min'].default_value = 0.55; frm.inputs['To Max'].default_value = 0.85
fnt.links.new(fr.outputs['Fac'], frm.inputs['Value']); fnt.links.new(frm.outputs['Result'], fb.inputs['Roughness'])
ff = knoten(fnt, 'ShaderNodeTexNoise', -700, -450); ff.inputs['Scale'].default_value = 160.0
fbm = knoten(fnt, 'ShaderNodeBump', -300, -450); fbm.inputs['Strength'].default_value = 0.12
fnt.links.new(ff.outputs['Fac'], fbm.inputs['Height']); fnt.links.new(fbm.outputs['Normal'], fb.inputs['Normal'])
form.data.materials.append(fm)

# ---------------------------------------------------------------- Gießstrahl
EIN = Vector((-2.25, 1.95, 0.0))
bpy.ops.mesh.primitive_cylinder_add(radius=0.03, depth=1, vertices=24, location=(EIN.x, EIN.y, 3.0))
strahl = bpy.context.active_object; strahl.name = 'Strahl'
for p in strahl.data.vertices: p.co.z -= 0.5          # Ursprung oben: wächst nach unten
strahl.location.z = 6.0
sm = bpy.data.materials.new('M_Strahl'); sm.use_nodes = True
sb = sm.node_tree.nodes['Principled BSDF']
sb.inputs['Base Color'].default_value = GOLD; sb.inputs['Metallic'].default_value = 1.0
sb.inputs['Roughness'].default_value = 0.05
sbb = knoten(sm.node_tree, 'ShaderNodeBlackbody', -300, -300); sbb.inputs['Temperature'].default_value = 1900
sgm = knoten(sm.node_tree, 'ShaderNodeMix', -100, -300, data_type='RGBA', blend_type='MULTIPLY')
sgm.inputs['Factor'].default_value = 1.0; sgm.inputs['B'].default_value = (1.0, 0.82, 0.42, 1.0)
sm.node_tree.links.new(sbb.outputs['Color'], sgm.inputs['A'])
sm.node_tree.links.new(sgm.outputs['Result'], sb.inputs['Emission Color'])
sb.inputs['Emission Strength'].default_value = 0.8
strahl.data.materials.append(sm)
for f, s in [(1, (1, 1, 0.0)), (F_START - 2, (1, 1, 0.0)), (F_START + 1, (1, 1, 6.0)), (F_VOLL - 8, (1, 1, 6.0)), (F_VOLL - 2, (0.0, 0.0, 6.0))]:
    strahl.scale = s; strahl.keyframe_insert('scale', frame=f)

# ---------------------------------------------------------------- Funken
bpy.ops.mesh.primitive_plane_add(size=0.12, location=EIN + Vector((0, 0, 0.02))); quelle = bpy.context.active_object
quelle.name = 'Funkenquelle'; quelle.hide_render = False
bpy.ops.mesh.primitive_ico_sphere_add(radius=1.0, subdivisions=1, location=(0, 0, -50)); funke = bpy.context.active_object
funke.name = 'Funke'
fk = bpy.data.materials.new('M_Funke'); fk.use_nodes = True
fnt2 = fk.node_tree; fnt2.nodes.clear()
fo = knoten(fnt2, 'ShaderNodeOutputMaterial', 300, 0); fe = knoten(fnt2, 'ShaderNodeEmission', 100, 0)
fbb = knoten(fnt2, 'ShaderNodeBlackbody', -100, 0); fbb.inputs['Temperature'].default_value = 1750
fnt2.links.new(fbb.outputs['Color'], fe.inputs['Color']); fe.inputs['Strength'].default_value = 28.0
fnt2.links.new(fe.outputs[0], fo.inputs['Surface']); funke.data.materials.append(fk)
ps = quelle.modifiers.new('Funken', 'PARTICLE_SYSTEM').particle_system.settings
ps.count = 420; ps.frame_start = F_START; ps.frame_end = F_VOLL - 6
ps.lifetime = 14; ps.lifetime_random = 0.6
ps.normal_factor = 2.4; ps.factor_random = 2.8
ps.render_type = 'OBJECT'; ps.instance_object = funke
ps.particle_size = 0.009; ps.size_random = 0.7
quelle.show_instancer_for_render = False
qm = bpy.data.materials.new('M_unsichtbar'); qm.use_nodes = True
qm.node_tree.nodes.clear(); qo = knoten(qm.node_tree, 'ShaderNodeOutputMaterial', 0, 0)
qt = knoten(qm.node_tree, 'ShaderNodeBsdfTransparent', -200, 0); qm.node_tree.links.new(qt.outputs[0], qo.inputs['Surface'])
quelle.data.materials.append(qm)

# ---------------------------------------------------------------- Licht
def flaeche(name, loc, ziel, groesse, leistung, farbe=(1, 1, 1)):
    d = bpy.data.lights.new(name, 'AREA'); d.shape = 'RECTANGLE'; d.size, d.size_y = groesse
    d.energy = leistung; d.color = farbe
    o = bpy.data.objects.new(name, d); sc.collection.objects.link(o); o.location = loc
    o.rotation_euler = (Vector(ziel) - Vector(loc)).to_track_quat('-Z', 'Y').to_euler()
    return o
flaeche('Oben', (-2.0, 2.5, 8.0), (0, 0, 0), (4, 2), 160, (1.0, 0.95, 0.88))
flaeche('Kante', (1.0, 7.5, 3.0), (0, 0, 1.5), (5, 1.2), 420, (1.0, 0.97, 0.92))
karte = flaeche('Fuehrung', (-4.5, -3.0, 5.0), (0, 0, 2.2), (3, 3), 90, (1.0, 0.93, 0.84))
# Reflexionsfläche vor der Marke (nur in Spiegelungen), wie im Standbild
# Hinter der Kamera auf Augenhöhe: Die stehende Marke spiegelt genau dorthin
bpy.ops.mesh.primitive_plane_add(size=1, location=(0.0, -26.0, 4.0)); rk = bpy.context.active_object
rk.name = 'Reflexkarte'; rk.scale = (22, 9, 1)
rk.rotation_euler = (Vector((0, 0, 2.25)) - rk.location).to_track_quat('Z', 'Y').to_euler()
rm = bpy.data.materials.new('M_Reflex'); rm.use_nodes = True; rnt = rm.node_tree; rnt.nodes.clear()
ro = knoten(rnt, 'ShaderNodeOutputMaterial', 300, 0); re = knoten(rnt, 'ShaderNodeEmission', 100, 0)
re.inputs['Color'].default_value = (1.0, 0.95, 0.86, 1)
rtc = knoten(rnt, 'ShaderNodeTexCoord', -500, 0); rsep = knoten(rnt, 'ShaderNodeSeparateXYZ', -300, 0)
rmr = knoten(rnt, 'ShaderNodeMapRange', -100, 0, interpolation_type='SMOOTHSTEP')
rmr.inputs['To Min'].default_value = 0.08; rmr.inputs['To Max'].default_value = 2.4
rnt.links.new(rtc.outputs['Generated'], rsep.inputs[0]); rnt.links.new(rsep.outputs['Y'], rmr.inputs['Value'])
rnt.links.new(rmr.outputs['Result'], re.inputs['Strength']); rnt.links.new(re.outputs[0], ro.inputs['Surface'])
rk.data.materials.append(rm)
rk.visible_camera = False; rk.visible_shadow = False; rk.visible_diffuse = False; rk.visible_volume_scatter = False
w = bpy.data.worlds.new('Nacht'); sc.world = w; w.use_nodes = True
w.node_tree.nodes['Background'].inputs['Color'].default_value = (0.0012, 0.0011, 0.001, 1)
w.node_tree.nodes['Background'].inputs['Strength'].default_value = 1.0

# ---------------------------------------------------------------- Kamera
ziel = bpy.data.objects.new('Blickziel', None); sc.collection.objects.link(ziel)
cd = bpy.data.cameras.new('Kamera'); kam = bpy.data.objects.new('Kamera', cd); sc.collection.objects.link(kam)
sc.camera = kam
tr = kam.constraints.new('TRACK_TO'); tr.target = ziel; tr.track_axis = 'TRACK_NEGATIVE_Z'; tr.up_axis = 'UP_Y'
HOCH = MODUS == 'hoch'
cd.sensor_width = 36
cd.dof.use_dof = True; cd.dof.focus_object = ziel; cd.dof.aperture_blades = 7
wege = [
    (1,       (-3.9, -0.6, 2.2),  (-2.15, 1.55, 0.05), 70 if not HOCH else 50, 2.2),
    (F_START + 8, (-3.6, -1.2, 2.5), (-1.7, 0.9, 0.0), 62 if not HOCH else 44, 2.4),
    (F_VOLL,  (0.9, -8.6, 7.4),   (0.1, 0.0, 0.0),    42 if not HOCH else 30, 4.0),
    (F_ENDE,  (0.0, -16.5, 2.6),  (0.0, 0.0, 2.25),   50 if not HOCH else 30, 8.0),
]
for f, loc, zl, lens, blende in wege:
    kam.location = loc; kam.keyframe_insert('location', frame=f)
    ziel.location = zl; ziel.keyframe_insert('location', frame=f)
    cd.lens = lens; cd.keyframe_insert('lens', frame=f)
    cd.dof.aperture_fstop = blende; cd.dof.keyframe_insert('aperture_fstop', frame=f)
if not HOCH:
    # Am Ende rechts im Bild, wo auf der Startseite das Echtzeit-V steht
    for f, s in [(1, 0.0), (F_KALT, 0.0), (F_ENDE, -0.16)]:
        cd.shift_x = s; cd.keyframe_insert('shift_x', frame=f)

# ---------------------------------------------------------------- Rendern
sc.render.engine = 'CYCLES'
pr = bpy.context.preferences.addons['cycles'].preferences
pr.compute_device_type = 'OPTIX'; pr.get_devices()
for d in pr.devices: d.use = d.type == 'OPTIX'
sc.cycles.device = 'GPU'
sc.cycles.use_denoising = True; sc.cycles.denoiser = 'OPTIX'
sc.cycles.max_bounces = 10; sc.cycles.glossy_bounces = 6; sc.cycles.transparent_max_bounces = 12
sc.render.use_motion_blur = True; sc.render.motion_blur_shutter = 0.5
sc.render.fps = 24; sc.frame_start = 1; sc.frame_end = F_ENDE
sc.view_settings.view_transform = 'AgX'; sc.view_settings.look = 'AgX - Medium High Contrast'
sc.view_settings.exposure = 0.0
if MODUS == 'probe':
    sc.render.resolution_x, sc.render.resolution_y = 960, 540
    sc.cycles.samples = 96; sc.cycles.adaptive_threshold = 0.03
    liste = BILDER or [3, 15, 30, 44, 60, 78]
else:
    sc.render.resolution_x, sc.render.resolution_y = (1080, 1920) if HOCH else (1920, 1080)
    sc.cycles.samples = 256; sc.cycles.adaptive_threshold = 0.012
    liste = BILDER or list(range(1, F_ENDE + 1))
sc.render.resolution_percentage = 100
sc.render.image_settings.file_format = 'PNG'; sc.render.image_settings.color_depth = '8'
bpy.ops.wm.save_as_mainfile(filepath=os.path.join(BASIS, 'intro_gold.blend'), copy=True)
for f in liste:
    sc.frame_set(f)
    sc.render.filepath = os.path.join(ZIEL, f'{f:03d}.png')
    bpy.ops.render.render(write_still=True)
    print('BILD', f, flush=True)
print('FERTIG', MODUS, len(liste))
