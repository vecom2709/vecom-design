# -*- coding: utf-8 -*-
"""Der Ablauf-Film (Uwe, 26.09.2026): "viel professioneller, mit Blender und
Unreal Engine -- damit der Kunde gleich am Anfang weiss, welche Schritte er
macht, wie es ablaeuft und welche Moeglichkeiten wir anbieten, bis er seine
Website ausgeliefert bekommt. Alles im Vecom-Stil, Gold."

Entscheidungen (Uwe): Text IM Bild gerendert · Blender baut, Unreal rendert ·
ohne Stimme · etwa 90 Sekunden.

DAS BILD
    Ein dunkler Raum aus poliertem Graphit. Eine eingelegte Goldlinie fuehrt
    durch zehn Stationen; an jeder steht ein Objekt aus gebuerstetem Gold, das
    den Schritt zeigt, daneben der Text in Gold. Eine einzige, ruhige
    Kamerafahrt ohne Schnitt: Der Kunde geht den Weg einmal ab -- genau das,
    was er spaeter tut. Keine erfundenen Bildschirme (Grundsatz vom 02.09.:
    ein Film darf keine Oberflaechen erfinden), keine Zeitangaben (25.09.).

DER WEG NACH UNREAL -- WARUM SO
    Befund 15.09.2026 (tisch_unreal.py): Die Unreal-MCP-Schnittstelle kann
    keine Werte setzen. Deshalb kommt alles, was einen Wert traegt, durch
    Dateien: Geometrie, Material und LICHT (als leuchtende Flaechen) im GLB,
    Bewegung und Kamera als Zahlen in bewegung.json. Die Sequenz baut
    ue_ablauf_film.py mit Unreals EIGENEM Python (das Werte setzen kann),
    nicht ueber MCP.

    Ausgabe unter 3d-produktion/unreal-export/ablauf/:
        welt.glb            alles Stehende, in Weltkoordinaten eingebacken
        text_<spr>.glb      die Schrift einer Sprache, eingebacken
        teil_<name>.glb     jedes bewegte Teil, Ursprung = Drehpunkt
        bewegung.json       Kamera je Bild (Ort, Ziel, Brennweite, Fokus),
                            bewegte Teile je Schluessel, Referenzpunkt

Aufruf (Windows, RTX 5070):
    blender -b --factory-startup --python ablauf_film.py -- probe de
        Eevee, 1280x720, die Mitte jeder Station als Standbild -> render/ablauf/probe_de
        ZUERST ansehen (Reality Check), bevor irgendetwas exportiert wird.
    blender -b --factory-startup --python ablauf_film.py -- export
        Die Dateien fuer Unreal (alle drei Sprachen).
    blender -b --factory-startup --python ablauf_film.py -- cycles de [von-bis]
        Rueckfallweg ohne Unreal: Cycles/OptiX, 1920x1080, PNG-Sequenz.
"""
import bpy, bmesh, sys, os, math, json
from mathutils import Vector, Matrix

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
MODUS = argv[0] if argv else 'probe'
SPRACHE = argv[1] if len(argv) > 1 and argv[1] in ('it', 'de', 'en') else 'de'
BEREICH = argv[2] if len(argv) > 2 else None

HIER = os.path.dirname(os.path.abspath(__file__))
PROD = os.path.dirname(HIER)                                   # 3d-produktion
TEXTE = json.load(open(os.path.join(PROD, 'film-ablauf', 'texte.json'), encoding='utf-8'))['szenen']
SCHRIFT = os.path.join(HIER, 'archivo-film.ttf')
SHOWROOM = os.path.join(PROD, 'vecom-showroom_Farm1080c.blend')  # traegt Marke_V (wie intro_gold.py)
AUS = os.environ.get('ABLAUF_AUS') or os.path.join(PROD, 'unreal-export', 'ablauf')
RENDER = os.environ.get('ABLAUF_RENDER') or os.path.join(PROD, 'render', 'ablauf')

FPS = 24
SEK = 90
BILDER = FPS * SEK            # 2160
STATIONEN = len(TEXTE)        # 10
JE = BILDER / STATIONEN       # 216 Bilder = 9 s je Station

# ---------------------------------------------------------------- Farben
def srgb(r, g, b):
    def k(v):
        v /= 255.0
        return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4
    return (k(r), k(g), k(b), 1.0)

GOLD_F0 = (1.0, 0.766, 0.336, 1.0)          # Gold, F0 linear (materials.md)
CHAMPAGNER = srgb(241, 211, 139)            # --c-cyan der Website, als Schriftfarbe
GRAPHIT = srgb(22, 20, 18)                  # nicht Schwarz: Albedo nie 0

sc = bpy.context.scene
for o in list(sc.objects):
    bpy.data.objects.remove(o, do_unlink=True)
sc.unit_settings.system = 'METRIC'
sc.unit_settings.scale_length = 1.0
sc.render.fps = FPS
sc.frame_start, sc.frame_end = 1, BILDER

def knoten(nt, typ, x, y, **werte):
    n = nt.nodes.new(typ); n.location = (x, y)
    for k, v in werte.items():
        if k in n.inputs: n.inputs[k].default_value = v
        else: setattr(n, k, v)
    return n

# ---------------------------------------------------------------- Materialien
def m_gold(name='M_Gold_Gebuerstet', rau=(0.16, 0.30)):
    """Gebuerstetes Gold: Metallic 1, F0 gemessen, Rauheit mit Makro-,
    Medium- und Mikro-Anteil (Rauschen grob/fein + Buerstrichtung)."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = GOLD_F0
    b.inputs['Metallic'].default_value = 1.0
    grob = knoten(nt, 'ShaderNodeTexNoise', -800, 200, **{'Scale': 3.0, 'Detail': 2.0})
    fein = knoten(nt, 'ShaderNodeTexNoise', -800, -50, **{'Scale': 180.0, 'Detail': 6.0})
    koord = knoten(nt, 'ShaderNodeTexCoord', -1200, 0)
    streck = knoten(nt, 'ShaderNodeMapping', -1000, -50)
    streck.inputs['Scale'].default_value = (1.0, 60.0, 1.0)      # Buerstrichtung: lange Riefen
    nt.links.new(koord.outputs['Object'], streck.inputs['Vector'])
    nt.links.new(streck.outputs['Vector'], fein.inputs['Vector'])
    mix = knoten(nt, 'ShaderNodeMix', -550, 100); mix.data_type = 'FLOAT'
    mix.inputs['Factor'].default_value = 0.35
    nt.links.new(grob.outputs['Fac'], mix.inputs['A'])
    nt.links.new(fein.outputs['Fac'], mix.inputs['B'])
    mr = knoten(nt, 'ShaderNodeMapRange', -300, 100)
    mr.inputs['To Min'].default_value, mr.inputs['To Max'].default_value = rau
    nt.links.new(mix.outputs['Result'], mr.inputs['Value'])
    nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    return m

def m_graphit():
    """Polierter Graphitstein: dielektrisch, Rauheit 0,12-0,32 in Wolken
    (Wischspuren, Laufweg), damit die Spiegelung lebt statt zu kleben."""
    m = bpy.data.materials.new('M_Graphit_Poliert'); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = GRAPHIT
    b.inputs['IOR'].default_value = 1.52
    n = knoten(nt, 'ShaderNodeTexNoise', -700, 0, **{'Scale': 0.6, 'Detail': 8.0, 'Roughness': 0.55})
    mr = knoten(nt, 'ShaderNodeMapRange', -400, 0)
    mr.inputs['To Min'].default_value, mr.inputs['To Max'].default_value = 0.12, 0.32
    nt.links.new(n.outputs['Fac'], mr.inputs['Value'])
    nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    return m

def m_schrift_matt():
    m = bpy.data.materials.new('M_Champagner_Matt'); m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = CHAMPAGNER
    b.inputs['Roughness'].default_value = 0.55
    return m

def m_glas():
    m = bpy.data.materials.new('M_Glas_Rauch'); m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (0.92, 0.88, 0.80, 1)
    b.inputs['Roughness'].default_value = 0.08
    b.inputs['Transmission Weight'].default_value = 1.0
    b.inputs['IOR'].default_value = 1.5
    return m

def m_licht(name, staerke, farbe=(1.0, 0.86, 0.68, 1)):
    """Licht als leuchtende Flaeche -- traegt seine Helligkeit im Material
    und kommt so durch das GLB (Befund 15.09., tisch_unreal.py)."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (0, 0, 0, 1)
    b.inputs['Emission Color'].default_value = farbe
    b.inputs['Emission Strength'].default_value = staerke
    return m

def m_bildschirm():
    """Der Bildschirm der fertigen Seite: warmes Leuchten mit Verlauf, kein
    erfundener Inhalt -- nur Licht, das 'da ist etwas' sagt."""
    m = bpy.data.materials.new('M_Bildschirm'); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (0.01, 0.01, 0.01, 1)
    b.inputs['Roughness'].default_value = 0.08
    gr = knoten(nt, 'ShaderNodeTexGradient', -600, 0)
    cr = knoten(nt, 'ShaderNodeValToRGB', -350, 0)
    cr.color_ramp.elements[0].color = srgb(40, 30, 16)
    cr.color_ramp.elements[1].color = srgb(245, 214, 150)
    nt.links.new(gr.outputs['Fac'], cr.inputs['Fac'])
    nt.links.new(cr.outputs['Color'], b.inputs['Emission Color'])
    b.inputs['Emission Strength'].default_value = 0.45     # 1,6 brannte zur flachen Flaeche aus (Probe 26.09.)
    return m

GOLD = m_gold()
GOLD_POLIERT = m_gold('M_Gold_Poliert', (0.05, 0.12))
STEIN = m_graphit()
MATT = m_schrift_matt()
GLAS = m_glas()
SCHIRM = m_bildschirm()

# ---------------------------------------------------------------- Werkzeuge
def fase(o, breite=0.004, seg=3):
    """Jede reale Kante ist gefast -- ohne Fase glaenzt keine Kante."""
    mod = o.modifiers.new('Fase', 'BEVEL'); mod.width = breite; mod.segments = seg
    mod.limit_method = 'ANGLE'; mod.harden_normals = True
    return o

def box(name, groesse, ort, mat, fas=0.004):
    bpy.ops.mesh.primitive_cube_add(size=1, location=ort)
    o = bpy.context.object; o.name = name
    o.scale = groesse; bpy.ops.object.transform_apply(scale=True)
    o.data.materials.append(mat); return fase(o, fas)

def zylinder(name, r, h, ort, mat, n=64):
    bpy.ops.mesh.primitive_cylinder_add(vertices=n, radius=r, depth=h, location=ort)
    o = bpy.context.object; o.name = name; o.data.materials.append(mat)
    bpy.ops.object.shade_smooth(); return fase(o, min(0.002, h / 4))

def torus(name, R, r, ort, rot, mat):
    bpy.ops.mesh.primitive_torus_add(major_radius=R, minor_radius=r, location=ort, rotation=rot,
                                     major_segments=96, minor_segments=32)
    o = bpy.context.object; o.name = name; o.data.materials.append(mat)
    bpy.ops.object.shade_smooth(); return o

def text(name, inhalt, ort, groesse, mat, breite=2.6, tiefe=0.012, ausr='LEFT'):
    """Schrift als Koerper: Archivo (die Filmschrift der Seite), leicht
    extrudiert und gefast, damit das Gold an der Kante Licht faengt."""
    cu = bpy.data.curves.new(name, 'FONT')
    cu.body = inhalt
    try:
        cu.font = bpy.data.fonts.load(SCHRIFT, check_existing=True)
    except Exception:
        pass
    # Fase klein halten: bei 0,35 x Tiefe quollen die Buchstaben auf (Probe 26.09.).
    cu.size = groesse; cu.extrude = tiefe; cu.bevel_depth = min(0.0012, tiefe * 0.2); cu.bevel_resolution = 2
    cu.align_x = ausr; cu.space_line = 1.08
    cu.text_boxes[0].width = breite
    o = bpy.data.objects.new(name, cu); sc.collection.objects.link(o)
    o.location = ort; o.rotation_euler = (math.radians(90), 0, 0)   # steht, schaut nach -Y (zur Kamera)
    o.data.materials.append(mat)
    return o

# ---------------------------------------------------------------- Weg und Stationen
def station_ort(i):
    """Die Stationen auf einer ruhigen S-Linie: 7 m Abstand, 1,4 m Schwung --
    genug Kurve fuer Parallaxe, zu wenig, um den Blick zu verlieren."""
    return Vector((i * 7.0, 1.4 * math.sin(i * 0.9), 0.0))

def station_bild(i):
    return 1 + i * JE + JE * 0.55                  # Mitte der Verweildauer

BEWEGT = []    # (objekt, [(bild, ort, drehung_z_grad, skalierung)])

def bewegt(o, schluessel):
    """Ein bewegtes Teil: Keys in Blender (fuer Probe/Cycles) UND in der Liste
    fuer Unreal. Nur Ort, Drehung um Z und gleichmaessige Skalierung -- das
    uebersetzt sich zwischen den Achsensystemen eindeutig."""
    for f, ort, rz, s in schluessel:
        o.location = ort; o.rotation_euler = (0, 0, math.radians(rz)); o.scale = (s, s, s)
        o.keyframe_insert('location', frame=f); o.keyframe_insert('rotation_euler', frame=f)
        o.keyframe_insert('scale', frame=f)
    BEWEGT.append((o, schluessel))

# Boden: 90 x 30 m polierter Graphit, der Raum verliert sich im Dunkel.
bpy.ops.mesh.primitive_plane_add(size=1, location=(31.5, 0, 0))
boden = bpy.context.object; boden.name = 'SM_Boden'; boden.scale = (90, 30, 1)
bpy.ops.object.transform_apply(scale=True); boden.data.materials.append(STEIN)

# Die Goldlinie: 2 cm breit, 1 mm erhaben, eingelegt -- der Weg des Kunden.
# Als Netz gebaut, nicht als Kurve: "extrude" einer Kurve stellt das Band
# hochkant, und im GLB kaeme ohnehin nur das Netz an.
def weg(u):
    # Hinter den Sockeln: Die Kamera sieht den Boden erst ab rund 3 m --
    # vor den Sockeln lag die Linie ausserhalb des Bildes (Probe 26.09.).
    return station_ort(u) + Vector((0, 0.8, 0))
bm = bmesh.new()
schritte = [(-0.6 + k * 0.01) for k in range(int((STATIONEN - 1 + 1.2) / 0.01) + 1)]
paare = []
for u in schritte:
    p = weg(u); t = (weg(u + 0.005) - weg(u - 0.005)).normalized()
    n = Vector((-t.y, t.x, 0)) * 0.01
    paare.append((bm.verts.new(p + n), bm.verts.new(p - n)))
for (a1, b1), (a2, b2) in zip(paare, paare[1:]):
    bm.faces.new((a1, b1, b2, a2))
me = bpy.data.meshes.new('Goldlinie'); bm.to_mesh(me); bm.free()
linie = bpy.data.objects.new('SM_Goldlinie', me); sc.collection.objects.link(linie)
linie.location.z = 0.0002
sol = linie.modifiers.new('Dicke', 'SOLIDIFY'); sol.thickness = 0.001; sol.offset = 1.0
linie.data.materials.append(GOLD_POLIERT)

# Licht: je Station eine grosse warme Flaeche 3,2 m ueber dem Objekt, nach
# unten gerichtet, ausserhalb des Bildes. Dazu ein schwacher kuehler Rand von
# hinten, damit die Goldkante sich vom Dunkel loest.
for i in range(STATIONEN):
    s = station_ort(i)
    # 3,6 m hoch: in Unreal gibt es kein "unsichtbar fuer die Kamera" --
    # die Flaeche muss AUSSERHALB des Bildes liegen, nicht nur verborgen sein.
    bpy.ops.mesh.primitive_plane_add(size=1, location=s + Vector((-0.2, -0.6, 3.4)))
    l = bpy.context.object; l.name = 'SM_Licht_%02d' % i; l.scale = (1.8, 1.2, 1)
    l.rotation_euler = (math.radians(160), 0, 0)      # leicht zur Kamera geneigt: Licht von vorn oben
    bpy.ops.object.transform_apply(scale=True, rotation=True)
    l.data.materials.append(m_licht('M_Licht_Warm_%02d' % i, 18.0))
    l.visible_camera = False
    bpy.ops.mesh.primitive_plane_add(size=1, location=s + Vector((0.0, 2.2, 3.6)))
    r = bpy.context.object; r.name = 'SM_Rand_%02d' % i; r.scale = (2.4, 1.0, 1)
    r.rotation_euler = (math.radians(135), 0, 0)     # schraeg nach vorn unten: Kante von hinten oben
    bpy.ops.object.transform_apply(scale=True, rotation=True)
    r.data.materials.append(m_licht('M_Licht_Kuehl_%02d' % i, 3.0, (0.78, 0.84, 1.0, 1)))
    r.visible_camera = False

# DIE REFLEXWAND. Poliertes Metall zeigt nur, was es spiegelt -- und vor
# der Schrift war nur Dunkel, deshalb las sich das Gold schwarz (Probe
# 26.09.). Wie im Fotostudio: eine grosse, schwach leuchtende Flaeche HINTER
# der Kamera, ueber die ganze Fahrt. Sie liegt ausserhalb jedes Bildes und
# hellt nebenbei die Vorderseiten weich auf.
bpy.ops.mesh.primitive_plane_add(size=1, location=(31.5, -5.5, 2.2))
wand = bpy.context.object; wand.name = 'SM_Reflexwand'; wand.scale = (80, 3.6, 1)
wand.rotation_euler = (math.radians(-90), 0, 0)       # Normale nach +Y, zur Szene
bpy.ops.object.transform_apply(scale=True, rotation=True)
wand.data.materials.append(m_licht('M_Reflexwand', 1.4, (1.0, 0.93, 0.82, 1)))
wand.visible_camera = False

# DIE RUECKWAND. Reines Schwarz dahinter wirkte wie "im Nichts schweben"
# (Probe 26.09.). Eine Galerie hat eine Wand, und die Deckenfluter zeichnen
# einen weichen Lichtverlauf von oben darauf -- das trennt Sockel und Schrift
# vom Grund, ohne dass eine Lampe im Bild steht (Fluter bei 4,2 m, die
# Bildoberkante liegt an der Wand bei rund 3,1 m).
def m_wand():
    m = bpy.data.materials.new('M_Wand_Putz'); m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = srgb(38, 34, 30)
    b.inputs['Roughness'].default_value = 0.75
    return m
bpy.ops.mesh.primitive_plane_add(size=1, location=(31.5, 6.0, 2.5))
rueck = bpy.context.object; rueck.name = 'SM_Rueckwand'; rueck.scale = (90, 5, 1)
rueck.rotation_euler = (math.radians(90), 0, 0)
bpy.ops.object.transform_apply(scale=True, rotation=True)
rueck.data.materials.append(m_wand())
for i in range(STATIONEN):
    s = station_ort(i)
    bpy.ops.mesh.primitive_plane_add(size=1, location=(s.x + 0.3, 5.55, 4.2))
    fl = bpy.context.object; fl.name = 'SM_Fluter_%02d' % i; fl.scale = (2.8, 0.18, 1)
    fl.rotation_euler = (math.radians(145), 0, 0)      # schraeg auf die Wand
    bpy.ops.object.transform_apply(scale=True, rotation=True)
    fl.data.materials.append(m_licht('M_Fluter_%02d' % i, 30.0))
    fl.visible_camera = False

SOCKEL_H = 0.92
def sockel(i):
    """Ein Museumssockel aus demselben Stein, 92 cm: Das Objekt steht auf
    Augenhoehe der Kamera und auf etwas (Kontaktschatten), statt klein am
    Boden zu liegen (Probe 26.09.: zu klein, zu tief)."""
    s = station_ort(i) + Vector((-0.55, 0, 0))
    return (box('SM_Sockel_%02d' % i, (0.6, 0.6, SOCKEL_H), s + Vector((0, 0, SOCKEL_H / 2)), STEIN, 0.006),
            s + Vector((0, 0, SOCKEL_H)))

# --- s1: die Marke steigt aus dem Sockel
def v_netz():
    """Rueckfall ohne Showroom-Datei: ein V aus einem Netz, zwei Schenkel
    als gefaste Prismen, Ursprung unten in der Mitte."""
    bm = bmesh.new()
    for seite in (-1, 1):
        # Schenkel: unten an der Spitze, oben 0,17 m nach aussen
        pkt = [Vector((0, 0, 0)), Vector((seite * 0.07, 0, 0)), Vector((seite * 0.24, 0, 0.5)), Vector((seite * 0.17, 0, 0.5))]
        vs = [bm.verts.new(p + Vector((0, -0.035, 0))) for p in pkt] + [bm.verts.new(p + Vector((0, 0.035, 0))) for p in pkt]
        bm.faces.new(vs[:4] if seite > 0 else vs[:4][::-1]); bm.faces.new(vs[4:][::-1] if seite > 0 else vs[4:])
        for a in range(4):
            b = (a + 1) % 4
            bm.faces.new((vs[a], vs[b], vs[4 + b], vs[4 + a]) if seite > 0 else (vs[b], vs[a], vs[4 + a], vs[4 + b]))
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    me = bpy.data.meshes.new('Marke_V'); bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new('Marke_V', me); sc.collection.objects.link(o)
    return fase(o, 0.003)

def marke(ort):
    try:
        with bpy.data.libraries.load(SHOWROOM, link=False) as (q, z):
            z.objects = ['Marke_V']
        v = z.objects[0]; sc.collection.objects.link(v); v.data = v.data.copy()
        v.rotation_euler = (0, 0, 0); v.location = (0, 0, 0)
    except Exception:
        v = v_netz()
    # Unabhaengig von den Massen der Quelle: 50 cm hoch, Ursprung unten mittig.
    xs = [c.co.x for c in v.data.vertices]; ys = [c.co.y for c in v.data.vertices]; zs = [c.co.z for c in v.data.vertices]
    mitte = Vector(((min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2, min(zs)))
    f = 0.5 / max(1e-6, (max(zs) - min(zs)))
    v.data.transform(Matrix.Scale(f, 4) @ Matrix.Translation(-mitte))
    v.data.materials.clear(); v.data.materials.append(GOLD_POLIERT)
    v.location = ort
    return v

_, o1 = sockel(0)
v = marke(o1); v.name = 'TEIL_marke'
f0 = 1
bewegt(v, [(f0, o1 + Vector((0, 0, -0.55)), -30, 1.0), (f0 + 72, o1, 0, 1.0), (f0 + 200, o1, 8, 1.0)])

# --- s2: der Brief
_, o2 = sockel(1)
brief = box('TEIL_brief', (0.46, 0.32, 0.018), Vector((0, 0, 0)), GOLD, 0.003)
f = int(station_bild(1) - 90)
bewegt(brief, [(f, o2 + Vector((-1.2, -0.6, 0.9)), -40, 1.0), (f + 60, o2 + Vector((0, 0, 0.35)), 10, 1.0),
               (f + 150, o2 + Vector((0, 0, 0.30)), 14, 1.0)])

# --- s3: ein Link -- zwei verschraenkte Ringe
_, o3 = sockel(2)
torus('SM_Ring_a', 0.17, 0.028, o3 + Vector((-0.1, 0, 0.3)), (math.radians(90), 0, 0), GOLD_POLIERT)
torus('SM_Ring_b', 0.17, 0.028, o3 + Vector((0.1, 0, 0.3)), (0, math.radians(90), 0), GOLD_POLIERT)

# --- s4: der Fragebogen -- Blaetter, das oberste mit Haken
_, o4 = sockel(3)
for k in range(6):
    b_ = box('SM_Blatt_%d' % k, (0.30, 0.42, 0.004), o4 + Vector((0.004 * k, 0.003 * k, 0.003 + 0.0045 * k)), GOLD if k < 5 else GOLD_POLIERT, 0.001)
    b_.rotation_euler = (0, 0, math.radians(-4 + 1.6 * k))
haken = bpy.data.curves.new('Haken', 'CURVE'); haken.dimensions = '3D'
hp = haken.splines.new('POLY'); hp.points.add(2)
for k, (x, y) in enumerate([(-0.07, 0.0), (-0.02, -0.05), (0.08, 0.07)]):
    hp.points[k].co = (x, y, 0, 1)
haken.bevel_depth = 0.008; haken.bevel_resolution = 3
ho = bpy.data.objects.new('TEIL_haken', haken); sc.collection.objects.link(ho); ho.data.materials.append(GOLD_POLIERT)
f = int(station_bild(3) - 30)
bewegt(ho, [(f, o4 + Vector((0, 0, 0.12)), 0, 0.001), (f + 18, o4 + Vector((0, 0, 0.036)), 0, 1.0)])

# --- s5: zwei gleich hohe Muenzstapel -- einer Gold (jetzt), einer Glas (bei Uebergabe)
_, o5 = sockel(4)
for k in range(12):
    zylinder('SM_Muenze_g_%02d' % k, 0.11, 0.012, o5 + Vector((-0.16, 0, 0.006 + 0.0124 * k)), GOLD)
    zylinder('SM_Muenze_l_%02d' % k, 0.11, 0.012, o5 + Vector((0.16, 0, 0.006 + 0.0124 * k)), GLAS)

# --- s6: der Bau -- Kacheln steigen nacheinander auf und bilden einen Rahmen
_, o6 = sockel(5)
f = int(station_bild(5) - 80)
for r in range(3):
    for c in range(4):
        t = box('TEIL_kachel_%d%d' % (r, c), (0.12, 0.03, 0.08), Vector((0, 0, 0)), GOLD, 0.003)
        ziel = o6 + Vector((-0.21 + 0.14 * c, 0, 0.05 + 0.095 * r))
        start = f + (r * 4 + c) * 7
        bewegt(t, [(1, ziel + Vector((0, 0, -0.5)), 0, 0.001), (start, ziel + Vector((0, 0, -0.5)), 0, 0.001),
                   (start + 20, ziel, 0, 1.0)])

# --- s7: die Freigabe -- ein Siegel wird aufgedrueckt
_, o7 = sockel(6)
zylinder('SM_Siegel_Grund', 0.2, 0.01, o7 + Vector((0, 0, 0.005)), MATT)
siegel = zylinder('TEIL_siegel', 0.13, 0.05, Vector((0, 0, 0)), GOLD_POLIERT)
f = int(station_bild(6) - 40)
bewegt(siegel, [(f, o7 + Vector((0, 0, 0.45)), 0, 1.0), (f + 14, o7 + Vector((0, 0, 0.035)), 0, 1.0),
                (f + 20, o7 + Vector((0, 0, 0.04)), 0, 1.0)])

# --- s8: Domain und E-Mail -- drei Wege fuehren von der Linie zu drei
# niedrigen Sockeln; die Wahl steht eingraviert auf der Vorderseite, oben
# liegt je ein Zeichen: Kugel = neu, Pfeil = Umzug, Ring = bleibt.
o8 = station_ort(7)
AESTE = []
for k, dx in enumerate((-0.95, -0.25, 0.45)):
    ende = o8 + Vector((dx, 1.9, 0))
    start = o8 + Vector((-0.25, 0.8, 0))
    d = ende - start
    arm = box('SM_Weg_%d' % k, (d.length, 0.03, 0.002), (start + ende) / 2 + Vector((0, 0, 0.001)), GOLD_POLIERT, 0.0005)
    arm.rotation_euler = (0, 0, math.atan2(d.y, d.x))
    box('SM_Wegsockel_%d' % k, (0.42, 0.42, 0.55), ende + Vector((0, 0, 0.275)), STEIN, 0.006)
    oben = ende + Vector((0, 0, 0.55))
    if k == 0:
        bpy.ops.mesh.primitive_uv_sphere_add(radius=0.07, location=oben + Vector((0, 0, 0.07)), segments=48, ring_count=24)
        z_ = bpy.context.object; z_.name = 'SM_Zeichen_neu'; z_.data.materials.append(GOLD_POLIERT); bpy.ops.object.shade_smooth()
    elif k == 1:
        box('SM_Zeichen_pfeil', (0.16, 0.04, 0.04), oben + Vector((-0.02, 0, 0.03)), GOLD_POLIERT, 0.003)
        spitze = box('SM_Zeichen_spitze', (0.07, 0.07, 0.04), oben + Vector((0.07, 0, 0.03)), GOLD_POLIERT, 0.003)
        spitze.rotation_euler = (0, 0, math.radians(45))
    else:
        torus('SM_Zeichen_ring', 0.07, 0.016, oben + Vector((0, 0, 0.016)), (0, 0, 0), GOLD_POLIERT)
    AESTE.append(ende)

# --- s9: online -- der Rahmen der fertigen Seite leuchtet
_, o9 = sockel(8)
rahmen = box('SM_Rahmen', (0.56, 0.025, 0.36), o9 + Vector((0, 0, 0.30)), GOLD_POLIERT, 0.004)
schirm = box('SM_Schirm', (0.51, 0.005, 0.31), o9 + Vector((0, -0.014, 0.30)), SCHIRM, 0.001)
box('SM_Fuss', (0.08, 0.08, 0.1), o9 + Vector((0, 0.02, 0.05)), GOLD, 0.003)

# --- s10: Betreuung -- ein Ring, um den ein Punkt kreist, dahinter die Marke
_, o10 = sockel(9)
torus('SM_Kreis', 0.24, 0.012, o10 + Vector((0, 0, 0.34)), (math.radians(90), 0, 0), GOLD_POLIERT)
bpy.ops.mesh.primitive_uv_sphere_add(radius=0.035, location=(0, 0, 0), segments=48, ring_count=24)
punkt = bpy.context.object; punkt.name = 'TEIL_punkt'; punkt.data.materials.append(GOLD_POLIERT)
bpy.ops.object.shade_smooth()
pk = []
f = int(station_bild(9) - 100)
for k in range(9):
    w = math.radians(k * 45)
    pk.append((f + k * 25, o10 + Vector((0.24 * math.cos(w), 0, 0.34 + 0.24 * math.sin(w))), 0, 1.0))
bewegt(punkt, pk)

# Referenzwuerfel: Unreal misst ihn nach und leitet daraus die Achsen ab.
REF = Vector((5.0, 2.0, 0.0))
box('SM_Referenz', (0.1, 0.1, 0.1), REF + Vector((0, 0, -0.5)), STEIN, 0.0)   # unter dem Boden, unsichtbar

# ---------------------------------------------------------------- Schrift je Sprache
def schrift(spr):
    teile = []
    for i, sz in enumerate(TEXTE):
        t = sz[spr]
        # Bildaufteilung nachgerechnet: 35 mm auf 3,5 m sehen rund 3,6 m Breite
        # (x -1,45 .. +2,15 um die Station). Objekt links, Text 1,7 m breit rechts.
        # Nachgerechnet fuer 35 mm auf 2,3 m: sichtbar rund x -0,95 .. +1,4 um
        # die Station. Sockel links (-0,8 .. -0,3), Text rechts, 1,15 m breit.
        s = station_ort(i) + Vector((-0.08, 0.1, 0))
        if t.get('kicker'):
            teile.append(text('TXT_%s_%02d_k' % (spr, i), t['kicker'].upper(), s + Vector((0, 0, 1.47)), 0.036, MATT, 1.15, 0.002))
        teile.append(text('TXT_%s_%02d_t' % (spr, i), t['titel'], s + Vector((0, 0, 1.33)), 0.095, GOLD_POLIERT, 1.15, 0.006))
        teile.append(text('TXT_%s_%02d_z' % (spr, i), t['zeile'], s + Vector((0, 0, 1.05)), 0.042, MATT, 1.15, 0.002))
        for k, ast in enumerate(t.get('rami', [])):
            # Auf der Vorderseite des Sockels, nicht in der Luft (Probe 26.09.)
            p = AESTE[k]
            teile.append(text('TXT_%s_ast_%d' % (spr, k), ast, p + Vector((0, -0.212, 0.36)), 0.055, GOLD_POLIERT, 0.4, 0.002, 'CENTER'))
    return teile

SCHRIFTEN = {s: schrift(s) for s in ('it', 'de', 'en')}
for s, teile in SCHRIFTEN.items():
    for o in teile:
        o.hide_render = (s != SPRACHE); o.hide_viewport = (s != SPRACHE)

# ---------------------------------------------------------------- Kamera
cam_data = bpy.data.cameras.new('Kamera')
cam_data.sensor_width = 36.0; cam_data.lens = 35.0
cam_data.dof.use_dof = True; cam_data.dof.aperture_fstop = 4.0
kamera = bpy.data.objects.new('Kamera', cam_data); sc.collection.objects.link(kamera); sc.camera = kamera
ziel_obj = bpy.data.objects.new('Kameraziel', None); sc.collection.objects.link(ziel_obj)
tc = kamera.constraints.new('TRACK_TO'); tc.target = ziel_obj; tc.track_axis = 'TRACK_NEGATIVE_Z'; tc.up_axis = 'UP_Y'
cam_data.dof.focus_object = ziel_obj

def glatt(t):
    return t * t * (3 - 2 * t)

def kamerapose(bild):
    """Eine einzige Fahrt. Je Station 9 s: rund 6 s fast Stillstand vor dem
    Objekt (lesen!), 3 s Uebergang mit weichem Anfahren und Abbremsen --
    nie ein harter Stopp (cinematics.md: natuerliche Beschleunigung)."""
    p = (bild - 1) / JE
    i = min(int(p), STATIONEN - 1); t = p - i
    halt = 0.66
    if t < halt or i == STATIONEN - 1:
        u = i + 0.08 * (t / halt if t < halt else 1.0)             # ganz leichtes Weitergleiten
    else:
        u = i + 0.08 + 0.92 * glatt((t - halt) / (1 - halt))
    a, b = int(math.floor(u)), min(int(math.floor(u)) + 1, STATIONEN - 1)
    w = u - math.floor(u)
    mitte = station_ort(a).lerp(station_ort(b), glatt(w))
    ort = mitte + Vector((0.08, -2.3, 1.22))      # Augenhoehe, leicht gesenkt
    ziel = mitte + Vector((0.08, 0.1, 1.08))
    return ort, ziel

KAMERA = []
for bild in range(1, BILDER + 1):
    ort, ziel = kamerapose(bild)
    kamera.location = ort; ziel_obj.location = ziel
    kamera.keyframe_insert('location', frame=bild); ziel_obj.keyframe_insert('location', frame=bild)
    KAMERA.append((bild, ort.copy(), ziel.copy()))

# ---------------------------------------------------------------- Welt, Farbe
welt = bpy.data.worlds.new('Dunkel'); welt.use_nodes = True
bg = welt.node_tree.nodes.get('Background') or welt.node_tree.nodes.new('ShaderNodeBackground')
out = welt.node_tree.nodes.get('World Output') or welt.node_tree.nodes.new('ShaderNodeOutputWorld')
welt.node_tree.links.new(bg.outputs['Background'], out.inputs['Surface'])
bg.inputs['Color'].default_value = (0.0022, 0.0019, 0.0016, 1); bg.inputs['Strength'].default_value = 1.0
sc.world = welt
sc.view_settings.view_transform = 'AgX'
for look in ('AgX - Base Contrast', 'AgX - Medium High Contrast', 'None'):
    try:
        sc.view_settings.look = look; break
    except TypeError:
        continue

# ---------------------------------------------------------------- Modi
def speichern_blend():
    os.makedirs(RENDER, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(RENDER, 'ablauf_film.blend'))

if MODUS == 'probe':
    ziel = os.path.join(RENDER, 'probe_' + SPRACHE); os.makedirs(ziel, exist_ok=True)
    sc.render.engine = 'BLENDER_EEVEE_NEXT' if 'BLENDER_EEVEE_NEXT' in [e.identifier for e in bpy.types.RenderSettings.bl_rna.properties['engine'].enum_items] else 'BLENDER_EEVEE'
    sc.render.resolution_x, sc.render.resolution_y = 1280, 720
    for i in range(STATIONEN):
        sc.frame_set(int(station_bild(i)))
        sc.render.filepath = os.path.join(ziel, 'station_%02d.png' % (i + 1))
        bpy.ops.render.render(write_still=True)
    speichern_blend()
    print('PROBE fertig:', ziel)

elif MODUS == 'probe-cpu':
    # Probe ohne Grafikkarte (z. B. in einer Cloud-Sitzung): Cycles auf der
    # CPU, wenige Samples + Entrauschen. Physikalisch dasselbe Licht wie der
    # Endrender -- nur rauschiger und kleiner. Stationen waehlbar: "1,5,8".
    ziel = os.path.join(RENDER, 'probe_' + SPRACHE); os.makedirs(ziel, exist_ok=True)
    sc.render.engine = 'CYCLES'; sc.cycles.device = 'CPU'
    sc.cycles.samples = int(os.environ.get('ABLAUF_SAMPLES', '48')); sc.cycles.use_denoising = True
    sc.render.resolution_x, sc.render.resolution_y = 960, 540
    welche = [int(x) - 1 for x in BEREICH.split(',')] if BEREICH else range(STATIONEN)
    for i in welche:
        sc.frame_set(int(station_bild(i)))
        sc.render.filepath = os.path.join(ziel, 'station_%02d.png' % (i + 1))
        bpy.ops.render.render(write_still=True)
    print('PROBE-CPU fertig:', ziel)

elif MODUS == 'cycles':
    sc.render.engine = 'CYCLES'
    prefs = bpy.context.preferences.addons['cycles'].preferences
    prefs.compute_device_type = 'OPTIX'; prefs.get_devices()
    for d in prefs.devices: d.use = (d.type == 'OPTIX')
    sc.cycles.device = 'GPU'
    print('Cycles-Geraete:', [(d.name, d.type, d.use) for d in prefs.devices])   # umgebung-und-mcp.md: pruefen, nicht annehmen
    sc.cycles.samples = 512; sc.cycles.use_denoising = True
    sc.render.use_motion_blur = True; sc.render.motion_blur_shutter = 0.5    # 180-Grad-Verschluss
    sc.render.resolution_x, sc.render.resolution_y = 1920, 1080
    sc.render.image_settings.file_format = 'PNG'; sc.render.image_settings.color_depth = '16'
    if BEREICH:
        a, b = BEREICH.split('-'); sc.frame_start, sc.frame_end = int(a), int(b)
    sc.render.filepath = os.path.join(RENDER, 'cycles_' + SPRACHE, 'bild_')
    bpy.ops.render.render(animation=True)

elif MODUS == 'export':
    os.makedirs(AUS, exist_ok=True)
    teile_namen = {o.name for o, _ in BEWEGT}

    def als_mesh(objs):
        """Kurven und Schrift zu Netzen, Modifikatoren (Fase!) angewendet."""
        aus = []
        for o in objs:
            bpy.ops.object.select_all(action='DESELECT')
            o.hide_viewport = False; o.hide_set(False); o.select_set(True)
            bpy.context.view_layer.objects.active = o
            if o.type in ('CURVE', 'FONT'):
                bpy.ops.object.convert(target='MESH')
                o = bpy.context.object
            for m in list(o.modifiers):
                bpy.ops.object.modifier_apply(modifier=m.name)
            aus.append(o)
        return aus

    def exportieren(objs, datei, lagen_einbacken=True):
        bpy.ops.object.select_all(action='DESELECT')
        for o in objs:
            o.select_set(True)
        bpy.context.view_layer.objects.active = objs[0]
        if lagen_einbacken:
            bpy.ops.object.make_single_user(object=True, obdata=True, material=False, animation=False)
            bpy.ops.object.transform_apply(location=True, rotation=True, scale=True)
        # 'NONE': sonst traegt Blender EXT_texture_webp als erforderlich ein, und
        # Unreals Interchange verwirft die GANZE Datei (unreal_export.py, 15.09.).
        bpy.ops.export_scene.gltf(filepath=os.path.join(AUS, datei), export_format='GLB', use_selection=True,
                                  export_apply=True, export_image_format='NONE', export_animations=False,
                                  export_cameras=False, export_lights=False, export_yup=True)

    # 1. bewegte Teile einzeln, Ursprung im Drehpunkt, ohne Bewegung
    teile_json = []
    for o, schl in BEWEGT:
        o.animation_data_clear()
        o.location = (0, 0, 0); o.rotation_euler = (0, 0, 0); o.scale = (1, 1, 1)
        [o2] = als_mesh([o])
        exportieren([o2], 'teil_%s.glb' % o2.name[5:], lagen_einbacken=False)
        teile_json.append({'name': o2.name[5:], 'datei': 'teil_%s.glb' % o2.name[5:],
                           'schluessel': [{'bild': int(f), 'ort': [p.x, p.y, p.z], 'dreh_z': rz, 'skal': s} for f, p, rz, s in schl]})
        bpy.data.objects.remove(o2, do_unlink=True)

    # 2. Schrift je Sprache
    for spr, teile in SCHRIFTEN.items():
        ms = als_mesh(teile)
        exportieren(ms, 'text_%s.glb' % spr)
        for m in ms: bpy.data.objects.remove(m, do_unlink=True)

    # 3. die stehende Welt (ohne Kamera, Ziel)
    rest = [o for o in sc.objects if o.type in ('MESH', 'CURVE', 'FONT') and o.name not in teile_namen]
    exportieren(als_mesh(rest), 'welt.glb')

    json.dump({
        'fps': FPS, 'bilder': BILDER, 'sensor_mm': 36.0, 'brennweite_mm': 35.0, 'blende': 4.0,
        'achsen': 'Blender-Meter; Unreal rechnet selbst um und prueft am Referenzwuerfel',
        'referenz': {'name': 'SM_Referenz', 'blender_ort': [REF.x, REF.y, REF.z - 0.5]},
        'stationen': [{'id': TEXTE[i]['id'], 'bild': int(station_bild(i))} for i in range(STATIONEN)],
        'kamera': [{'bild': b, 'ort': [o.x, o.y, o.z], 'ziel': [z.x, z.y, z.z]} for b, o, z in KAMERA],
        'teile': teile_json,
    }, open(os.path.join(AUS, 'bewegung.json'), 'w', encoding='utf-8'), ensure_ascii=False)
    print('EXPORT fertig:', AUS)
