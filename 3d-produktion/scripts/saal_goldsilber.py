"""Saal in Gold-Silber: aus vecom-showroom_Farm1080c.blend (Quelle des
Standbilds, Bild 122) wird vecom-showroom_GoldSilber.blend.

Aufruf:  blender -b vecom-showroom_Farm1080c.blend --python saal_goldsilber.py -- <modus>
  modus probe   960x540, 256 Samples  -> render/goldsilber/probe.png
  modus voll   1920x1080, 1024 Samples -> render/goldsilber/standbild.png

Warum so und nicht anders:
- Die Marke bekommt physikalische F0-Farben (Gold 1.0/0.766/0.336, Silber
  0.972/0.960/0.915, linear) statt einer gemalten Textur. Metall zeigt seine
  Farbe nur in der Spiegelung; eine gemalte Albedo sieht unter Klarlack wie
  lackierter Kunststoff aus.
- Der Uebergang sitzt wie in der Echtzeit-V (scene.js: uX0 = -2 %, uX1 = +34 %
  der Breite um die Mitte), damit Standbild und Echtzeitbild dieselbe Marke
  zeigen. Die Grenze ist mit feinem Rauschen verschoben: Eine lineal-gerade
  Farbgrenze auf gebuerstetem Metall verraet den Rechner.
- Klarlack von 0.6 auf 0.2: Der Lack spiegelt weiss und frisst bei flachem
  Blick genau die Goldfarbe, um die es geht (in der Echtzeitfassung gemessen).
- Alle blauen und tuerkisen Lichtfugen werden warm: tiefes Gold fuer die
  Fugen, Champagner fuer die Kanten. Kaltweisse Deckenfelder werden
  warmweiss, damit das Gold nicht gegen blaues Umgebungslicht grau wird.
"""
import bpy, sys, os, math

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
MODUS = argv[0] if argv else 'probe'
BASIS = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
ZIEL = os.path.join(BASIS, 'render', 'goldsilber')
os.makedirs(ZIEL, exist_ok=True)

# Zweites Argument 'silbersaal' (24.09.2026, Uwe: „Hintergrund in Silber und
# sonst, was Gold-Silber-Verlauf ist, echtes glaenzendes Gold"): Die Marke
# ist ganz poliertes Gold, der Saal gebuerstetes Silber.
SILBERSAAL = len(argv) > 1 and argv[1] == 'silbersaal'
# 'vollgold': dunkler Saal wie bisher, Marke ganz poliertes Gold (Uwes Wahl
# vom 24.09.2026 zwischen Silbersaal und dunklem Saal).
VOLLGOLD = SILBERSAAL or (len(argv) > 1 and argv[1] == 'vollgold')
ENDUNG = '-silbersaal' if SILBERSAAL else ('-vollgold' if VOLLGOLD else '')

GOLD = (1.0, 0.766, 0.336, 1.0)
SILBER = GOLD if VOLLGOLD else (0.972, 0.960, 0.915, 1.0)
GOLD_KANTE = (1.0, 0.93, 0.74, 1.0)     # F82-Kantentoenung, Gold wird zum Rand hin heller
SILBER_KANTE = GOLD_KANTE if VOLLGOLD else (1.0, 1.0, 1.0, 1.0)


def knoten(nt, typ, x, y, name=None):
    n = nt.nodes.new(typ); n.location = (x, y)
    if name: n.name = n.label = name
    return n


def marke():
    m = bpy.data.materials['M_Marke']
    nt = m.node_tree
    bsdf = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')
    for l in list(bsdf.inputs['Base Color'].links):
        nt.links.remove(l)
    alt = nt.nodes.get('Bildtexturen')
    if alt: nt.nodes.remove(alt)

    v = bpy.data.objects['Marke_V']
    xs = [p.co.x for p in v.data.vertices]
    mitte, breite = (min(xs) + max(xs)) / 2, max(xs) - min(xs)
    x0, x1 = mitte - 0.02 * breite, mitte + 0.34 * breite
    print('MARKE lokal breite %.3f  x0 %.3f  x1 %.3f' % (breite, x0, x1))

    tc = knoten(nt, 'ShaderNodeTexCoord', -1400, 500, 'GS_Koord')
    sep = knoten(nt, 'ShaderNodeSeparateXYZ', -1200, 500, 'GS_X')
    nt.links.new(tc.outputs['Object'], sep.inputs[0])
    # Grenze leicht wellig: grobes Rauschen, +-4 % der Breite
    rs = knoten(nt, 'ShaderNodeTexNoise', -1200, 300, 'GS_Welle')
    rs.inputs['Scale'].default_value = 0.9
    rs.inputs['Detail'].default_value = 3.0
    nt.links.new(tc.outputs['Object'], rs.inputs['Vector'])
    ab = knoten(nt, 'ShaderNodeMath', -1000, 300, 'GS_Wellenhub')
    ab.operation = 'MULTIPLY_ADD'
    ab.inputs[1].default_value = 0.08 * breite
    ab.inputs[2].default_value = -0.04 * breite
    nt.links.new(rs.outputs['Fac'], ab.inputs[0])
    plus = knoten(nt, 'ShaderNodeMath', -850, 450, 'GS_Xverschoben')
    plus.operation = 'ADD'
    nt.links.new(sep.outputs['X'], plus.inputs[0])
    nt.links.new(ab.outputs[0], plus.inputs[1])
    mr = knoten(nt, 'ShaderNodeMapRange', -700, 450, 'GS_Uebergang')
    mr.interpolation_type = 'SMOOTHSTEP'
    mr.inputs['From Min'].default_value = x0
    mr.inputs['From Max'].default_value = x1
    nt.links.new(plus.outputs[0], mr.inputs['Value'])

    mix = knoten(nt, 'ShaderNodeMix', -450, 500, 'GS_Farbe')
    mix.data_type = 'RGBA'
    mix.inputs['A'].default_value = GOLD
    mix.inputs['B'].default_value = SILBER
    nt.links.new(mr.outputs['Result'], mix.inputs['Factor'])
    nt.links.new(mix.outputs['Result'], bsdf.inputs['Base Color'])

    kante = knoten(nt, 'ShaderNodeMix', -450, 250, 'GS_Kante')
    kante.data_type = 'RGBA'
    kante.inputs['A'].default_value = GOLD_KANTE
    kante.inputs['B'].default_value = SILBER_KANTE
    nt.links.new(mr.outputs['Result'], kante.inputs['Factor'])
    nt.links.new(kante.outputs['Result'], bsdf.inputs['Specular Tint'])

    bsdf.inputs['Coat Weight'].default_value = 0.1


def farbe_setzen(matname, knotentyp, eingang, farbe):
    m = bpy.data.materials.get(matname)
    if not m: return
    for n in m.node_tree.nodes:
        if n.type == knotentyp and eingang in n.inputs and not n.inputs[eingang].is_linked:
            n.inputs[eingang].default_value = farbe
    # Die Lichtfarbe sitzt in drei Materialien nicht im Shader, sondern in
    # einem Mischknoten davor (gefunden mit saal_blausuche.py, die erste Probe
    # zeigte die Sockelfuge noch blau). Jede blaue Farbe dort mit umstellen.
    for n in m.node_tree.nodes:
        if n.type == 'MIX' and n.data_type == 'RGBA':
            for i in n.inputs:
                if i.name in ('A', 'B') and i.type == 'RGBA' and not i.is_linked:
                    r, g, b = i.default_value[:3]
                    if b > 0.2 and b > r * 1.6:
                        i.default_value = farbe


def lichter():
    TIEFGOLD = (1.0, 0.46, 0.10, 1.0)
    CHAMPAGNER = (1.0, 0.76, 0.40, 1.0)
    for name, f in [('M_LichtBlau', TIEFGOLD), ('M_Fuge', TIEFGOLD),
                    ('M_LichtCyan', CHAMPAGNER), ('M_Kantenlicht', CHAMPAGNER)]:
        farbe_setzen(name, 'BSDF_PRINCIPLED', 'Emission Color', f)
        farbe_setzen(name, 'HUE_SAT', 'Color', f)
        farbe_setzen(name, 'EMISSION', 'Color', f)
    farbe_setzen('M_Deckenfeld', 'EMISSION', 'Color', (1.0, 0.93, 0.83, 1.0))
    farbe_setzen('M_Softbox_L', 'EMISSION', 'Color', (1.0, 0.97, 0.93, 1.0))
    farbe_setzen('M_Softbox_O', 'EMISSION', 'Color', (1.0, 0.97, 0.93, 1.0))
    for l in bpy.data.lights:
        if l.name.startswith('Fill'):
            l.color = (0.96, 0.95, 0.94)


def metall_und_boden():
    # Kuehles Blaugrau der Hallenmetalle -> warmes Neutral bzw. dunkles Champagner,
    # bei gleicher Helligkeit (Luminanz vorher 0.53 bzw. 0.30).
    farbe_setzen('M_Chrom', 'HUE_SAT', 'Color', (0.545, 0.535, 0.515, 1.0))
    for n in ('M_MetallGeb', 'M_MetallPfosten'):
        farbe_setzen(n, 'HUE_SAT', 'Color', (0.345, 0.29, 0.205, 1.0))
    farbe_setzen('M_Boden', 'BSDF_PRINCIPLED', 'Base Color', (0.036, 0.033, 0.030, 1.0))
    d = bpy.data.materials.get('M_Dunst')
    if d:
        for n in d.node_tree.nodes:
            for e in ('Color',):
                if n.type in ('PRINCIPLED_VOLUME', 'VOLUME_SCATTER') and e in n.inputs and not n.inputs[e].is_linked:
                    print('DUNST vorher', tuple(round(c, 3) for c in n.inputs[e].default_value))
                    n.inputs[e].default_value = (1.0, 0.97, 0.92, 1.0)


def frontlicht():
    """Eine grosse Lichtwand hinter der Kamera, fuer die Kamera unsichtbar.

    Erste Probe: Die Frontflaechen der V sahen khakigrau aus. Gemessen am
    Bild: Sie spiegeln, was hinter der Kamera liegt -- und dort war nur
    dunkler Saal. Metall hat keine eigene Farbe ohne etwas Helles, das es
    spiegeln kann. Genau so arbeitet jedes Schmuckfoto: eine grosse Flaeche
    mit Verlauf, oben hell, unten auslaufend, damit ueber das Metall ein
    Lichtband laeuft statt eines flachen Glanzes.
    """
    ziel = bpy.data.objects['Marke_V'].matrix_world.translation.copy()
    bpy.ops.mesh.primitive_plane_add(size=1.0, location=(0.6, -6.5, 6.2))
    o = bpy.context.active_object
    o.name = 'Softbox_Front'
    o.scale = (11.0, 4.5, 1.0)
    richtung = (ziel - o.location).normalized()
    o.rotation_euler = richtung.to_track_quat('Z', 'Y').to_euler()
    m = bpy.data.materials.new('M_Softbox_Front')
    m.use_nodes = True
    nt = m.node_tree
    for n in list(nt.nodes): nt.nodes.remove(n)
    aus = knoten(nt, 'ShaderNodeOutputMaterial', 400, 0)
    em = knoten(nt, 'ShaderNodeEmission', 200, 0)
    em.inputs['Color'].default_value = (1.0, 0.95, 0.86, 1.0)
    tc = knoten(nt, 'ShaderNodeTexCoord', -600, 0)
    sep = knoten(nt, 'ShaderNodeSeparateXYZ', -400, 0)
    mr = knoten(nt, 'ShaderNodeMapRange', -200, 0)
    mr.interpolation_type = 'SMOOTHSTEP'
    mr.inputs['From Min'].default_value = 0.05
    mr.inputs['From Max'].default_value = 0.95
    # Zweite Probe mit 0,4..7,0: Gold kam champagnerbleich heraus, der Saal
    # grau aufgehellt. AgX entsaettigt helle Toene stark -- Gold behaelt seine
    # Farbe nur in den Mitteltoenen. Also halb so hell, und die Flaeche nur
    # in Spiegelungen, nicht als Raumlicht (siehe visible_diffuse unten).
    # Dritte Probe mit 0,15..3,2 gemessen: Goldflaeche V 0,89-0,92 bei
    # Saettigung 0,14-0,18 -- zu hell, also blass. Erst bei V 0,64 kam
    # Saettigung 0,43. Ziel sind die Mitteltoene, daher gut halb so hell.
    mr.inputs['To Min'].default_value = 0.08
    mr.inputs['To Max'].default_value = 1.5
    nt.links.new(tc.outputs['Generated'], sep.inputs[0])
    nt.links.new(sep.outputs['Y'], mr.inputs['Value'])
    nt.links.new(mr.outputs['Result'], em.inputs['Strength'])
    nt.links.new(em.outputs[0], aus.inputs['Surface'])
    o.data.materials.append(m)
    o.visible_camera = False
    o.visible_shadow = False
    o.visible_volume_scatter = False
    o.visible_diffuse = False
    o.visible_transmission = False


def welt():
    """Die Umgebung (studio.webp) ist eine blau-tuerkise Buehne: Mittelwert
    RGB 7/37/54. Im ersten vollen Bild blieb davon ein blauer Schimmer auf dem
    Chrompodest und ein Band oben an der rechten Wand. Graustufen und ein
    warmer Hauch, gleiche Helligkeit -- die Lichtstrahlen darin bleiben."""
    w = bpy.context.scene.world
    nt = w.node_tree
    env = next(n for n in nt.nodes if n.type == 'TEX_ENVIRONMENT')
    bg = next(n for n in nt.nodes if n.type == 'BACKGROUND')
    hs = knoten(nt, 'ShaderNodeHueSaturation', env.location.x + 250, env.location.y - 150, 'GS_Welt_grau')
    hs.inputs['Saturation'].default_value = 0.0
    hs.inputs['Value'].default_value = 1.25   # Luminanz von Blau-Tuerkis ist niedrig; ausgleichen
    warm = knoten(nt, 'ShaderNodeMix', env.location.x + 450, env.location.y - 150, 'GS_Welt_warm')
    warm.data_type = 'RGBA'; warm.blend_type = 'MULTIPLY'
    warm.inputs['Factor'].default_value = 1.0
    warm.inputs['B'].default_value = (1.0, 0.93, 0.84, 1.0)
    nt.links.new(env.outputs['Color'], hs.inputs['Color'])
    nt.links.new(hs.outputs['Color'], warm.inputs['A'])
    nt.links.new(warm.outputs['Result'], bg.inputs['Color'])


def silbersaal():
    """Saal aus gebuerstetem Silber, Marke hochglaenzend.

    Waende und Decke werden Metallpaneele (Aluminium, F0 0,91/0,92/0,92),
    satiniert statt spiegelnd -- ein spiegelnder Saal zeigt nur noch sich
    selbst und das Gold verliert die Buehne. Portale und Lamellen hell
    gebuerstet, Boden bleibt dunkler polierter Stein: Ohne dunklen Grund
    steht das Gold auf nichts, und die Spiegelung darin traegt es."""
    ALU = (0.91, 0.92, 0.92, 1.0)
    for name, rau in (('M_Wand', 0.42), ('M_Decke', 0.5)):
        m = bpy.data.materials.get(name)
        if not m: continue
        nt = m.node_tree
        b = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')
        for l in list(b.inputs['Base Color'].links): nt.links.remove(l)
        for l in list(b.inputs['Roughness'].links): nt.links.remove(l)
        b.inputs['Base Color'].default_value = ALU
        b.inputs['Metallic'].default_value = 1.0
        # Rauheit mit leichter Wolke, damit die Paneele nicht wie Folie wirken
        rs = knoten(nt, 'ShaderNodeTexNoise', b.location.x - 500, b.location.y - 300, 'SS_Rauwolke')
        rs.inputs['Scale'].default_value = 2.5
        tc = knoten(nt, 'ShaderNodeTexCoord', b.location.x - 700, b.location.y - 300, 'SS_Koord')
        nt.links.new(tc.outputs['Object'], rs.inputs['Vector'])
        mr = knoten(nt, 'ShaderNodeMapRange', b.location.x - 280, b.location.y - 300, 'SS_Rau')
        mr.inputs['To Min'].default_value = rau - 0.06
        mr.inputs['To Max'].default_value = rau + 0.06
        nt.links.new(rs.outputs['Fac'], mr.inputs['Value'])
        nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
        try: b.inputs['Anisotropic'].default_value = 0.5
        except KeyError: pass
    farbe_setzen('M_Chrom', 'HUE_SAT', 'Color', (0.95, 0.95, 0.95, 1.0))
    for n in ('M_MetallGeb', 'M_MetallPfosten'):
        farbe_setzen(n, 'HUE_SAT', 'Color', (0.80, 0.80, 0.80, 1.0))


def hochglanz():
    """Leicht gebuerstetes, elegantes Gold (Uwe, 24.09.2026: „die Reflexion
    nicht zu stark"). Erst war hier Hochglanz (Rauheit 0,04..0,09) -- das
    spiegelt den Saal wie ein Spiegel und wirkt eher Messing-Pokal als
    Schmuck. Satiniert: Rauheit 0,17..0,27, Buerstung deutlicher (Anisotropie
    0,55, Schleifspuren-Relief 0,06 -> 0,10), kein Klarlack, Reflexions-
    flaeche schwaecher. So verteilt sich das Licht als weicher Schimmer
    entlang der Schleifrichtung statt als harter Spiegel."""
    m = bpy.data.materials['M_Marke']
    nt = m.node_tree
    for n in nt.nodes:
        if n.type == 'VALTORGB' and n.name == 'Farbverlauf':
            n.color_ramp.elements[0].color = (0.17, 0.17, 0.17, 1)
            n.color_ramp.elements[1].color = (0.27, 0.27, 0.27, 1)
        if n.type == 'BUMP' and n.name == 'Bump (Relief).001':
            n.inputs['Strength'].default_value = 0.10
        if n.type == 'BSDF_PRINCIPLED':
            n.inputs['Anisotropic'].default_value = 0.55
            n.inputs['Coat Weight'].default_value = 0.0
    fb = bpy.data.materials.get('M_Softbox_Front')
    if fb:
        for n in fb.node_tree.nodes:
            if n.type == 'MAP_RANGE':
                n.inputs['To Max'].default_value = 1.0


def gpu():
    pr = bpy.context.preferences.addons['cycles'].preferences
    for typ in ('OPTIX', 'CUDA'):
        try:
            pr.compute_device_type = typ
            pr.get_devices()
            gpus = [d for d in pr.devices if d.type == typ]
            if gpus:
                for d in pr.devices: d.use = d.type == typ
                bpy.context.scene.cycles.device = 'GPU'
                print('GERAET', typ, [d.name for d in gpus])
                return
        except TypeError:
            pass
    print('GERAET CPU (keine GPU gefunden)')


marke(); lichter(); metall_und_boden(); frontlicht(); welt()
if SILBERSAAL: silbersaal()
if VOLLGOLD: hochglanz()
gpu()
s = bpy.context.scene
s.frame_set(122)
c = s.cycles
c.use_denoising = True
try: c.denoiser = 'OPTIX'
except TypeError: pass
if MODUS == 'probe':
    s.render.resolution_x, s.render.resolution_y = 960, 540
    c.samples, c.adaptive_threshold = 256, 0.02
    ausgabe = os.path.join(ZIEL, 'probe%s.png' % ENDUNG)
else:
    s.render.resolution_x, s.render.resolution_y = 1920, 1080
    c.samples, c.adaptive_threshold = 1024, 0.008
    ausgabe = os.path.join(ZIEL, 'standbild%s.png' % ENDUNG)
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(
        BASIS, 'vecom-showroom_%s.blend' % ('SilberGold' if SILBERSAAL else ('Vollgold' if VOLLGOLD else 'GoldSilber'))), copy=True)
s.render.resolution_percentage = 100
s.render.image_settings.file_format = 'PNG'
s.render.image_settings.color_depth = '16'
s.render.filepath = ausgabe
bpy.ops.render.render(write_still=True)
print('FERTIG', ausgabe)
