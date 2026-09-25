# -*- coding: utf-8 -*-
"""kueche_modern.py -- Moderne Kueche 2026 fuer die Branchen-Demo (24.09.2026).

Uwe: „die kueche viel moderner, was zur heutigen Zeit passt, hyperrealistisch
fotorealistisch, mache mit Unreal Engine“ -- Weg K1: Blender baut das Aussehen,
Unreal rechnet mit dem Path Tracer, der Planer im Web bekommt dieselben
Fronten und Materialien.

Entwurf (warum so):
  * Grifflos. Waagrechte Griffmulden (Gola) in Champagner-Gold -- das ist das
    Gold der Marke, als Metall und nicht als Farbe.
  * Hochschrankwand bis zur Decke in Eiche furniert, Maserung laeuft ueber
    alle Tueren durch (fortlaufendes Furnierbild) -- woran man eine teure
    Kueche erkennt.
  * Insel und Unterschraenke supermatt schwarz (Fenix-artig), Keramikplatte
    3 cm in Calacatta Oro, an beiden Enden als Wange bis zum Boden
    (Wasserfall) mit durchlaufender Aderung; Rueckwand raumhoch aus zwei
    gespiegelten Platten (Bookmatch).
  * Induktion mit Muldenlueftung statt Haube: nichts haengt im Raum.
  * Licht: Tageslicht durch ein raumhohes Stahlfenster, dazu warmes LED unter
    dem Regal, in der Nische und in der Pendelleuchte (2700 K).

Masse (m, Z oben): Sockel 0,10, Arbeitshoehe 0,92, Plattenstaerke 0,03,
Korpustiefe 0,58 + Front 0,019, Durchgang Zeile--Insel 1,11, Raum 5,40 x 4,80
x 2,80.

Aufruf (Blender 5.x):
  blender -b --factory-startup --python kueche_modern.py -- MODUS KAMERAS [EV]
  MODUS    probe | voll | unreal | bauen
  KAMERAS  gesamt,insel,detail (kommagetrennt)
  EV       Belichtung in Blenden (Film), Standard 0
Pfade lassen sich fuer Probelaeufe ueber VD_TEX / VD_AUS umlenken.
"""
import bpy
import bmesh
import json
import math
import os
import random
import sys
from mathutils import Vector, Matrix

ARGS = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
MODUS = ARGS[0] if len(ARGS) > 0 else 'probe'
KAMS = (ARGS[1] if len(ARGS) > 1 else 'gesamt').split(',')
EV = float(ARGS[2]) if len(ARGS) > 2 else 0.0

BASIS = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
TEX = os.environ.get('VD_TEX') or os.path.join(BASIS, 'quelle', 'tex', 'modern')
AUS = os.environ.get('VD_AUS') or os.path.join(BASIS, 'render', 'kueche-modern')
UE_AUS = os.environ.get('VD_UE') or os.path.join(BASIS, 'render', 'arbeiten', 'kueche-modern', 'unreal')

random.seed(26)


def log(*a):
    print('[kueche]', *a, flush=True)


# ====================================================================== Masse
H_RAUM = 2.80
X_L, X_R = -2.70, 2.70          # Seitenwaende (rechts: Fenster)
Y_V, Y_H = -2.70, 2.10          # Wand hinter der Kamera / Rueckwand
SOCKEL = 0.10
ARBEIT = 0.92
PLATTE = 0.03
Z_KO = ARBEIT - PLATTE          # Oberkante Korpus 0,89
FRONT = 0.019
Y_FRONT = Y_H - 0.60            # Frontflaeche der Zeile 1,50
FUGE = 0.003
X_HOCH0, X_HOCH1 = X_L, -0.30   # Hochschrankwand
X_ZEILE0, X_ZEILE1 = -0.281, 2.10
INSEL_X, INSEL_Y0, INSEL_Y1 = 1.50, -0.75, 0.35   # halbe Laenge, vorn (Sitzseite), hinten
Z_GOLA_OBEN = (0.825, Z_KO)     # obere Griffmulde unter der Platte
Z_GOLA_MITTE = (0.545, 0.600)   # C-Profil zwischen den Schubkaesten


# ====================================================================== Grundlagen
def leeren():
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o, do_unlink=True)
    for coll in (bpy.data.meshes, bpy.data.materials, bpy.data.lights, bpy.data.cameras, bpy.data.images, bpy.data.curves):
        for b in list(coll):
            coll.remove(b)


_BILDER = {}


def bild(name, farbe=True):
    """Bild aus TEX; sRGB fuer Farbe, sonst Non-Color. Fehlt es, bricht der
    Lauf ab -- ein stilles Grau statt Marmor faellt sonst erst im Bild auf."""
    key = (name, farbe)
    if key in _BILDER:
        return _BILDER[key]
    pfad = os.path.join(TEX, name)
    if not os.path.exists(pfad):
        raise FileNotFoundError(pfad)
    im = bpy.data.images.load(pfad, check_existing=False)
    im.colorspace_settings.name = 'sRGB' if farbe else 'Non-Color'
    _BILDER[key] = im
    return im


def stoff(name, farbe=(0.8, 0.8, 0.8), rau=0.5, metall=0.0, karte=None, rau_karte=None, normal=None,
          nstaerke=1.0, kachel=1.0, spec=0.5, coat=0.0, coat_rau=0.05, trans=0.0, ior=1.5,
          aniso=0.0, emiss=None, sheen=0.0):
    """Principled BSDF, glTF-tauglich: Karten haengen direkt an den Eingaengen,
    eine Mapping-Stufe (Meter je Kachel) wird als KHR_texture_transform
    exportiert. Keine Mischknoten -- die kaemen in Unreal nicht an."""
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (*farbe, 1.0)
    b.inputs['Roughness'].default_value = rau
    b.inputs['Metallic'].default_value = metall
    b.inputs['Specular IOR Level'].default_value = spec
    b.inputs['IOR'].default_value = ior
    b.inputs['Coat Weight'].default_value = coat
    b.inputs['Coat Roughness'].default_value = coat_rau
    b.inputs['Transmission Weight'].default_value = trans
    if aniso:
        b.inputs['Anisotropic'].default_value = aniso
    if sheen:
        b.inputs['Sheen Weight'].default_value = sheen
    if emiss:
        b.inputs['Emission Color'].default_value = (*emiss[0], 1.0)
        b.inputs['Emission Strength'].default_value = emiss[1]
    vek = None
    if karte or rau_karte or normal:
        tk = nt.nodes.new('ShaderNodeTexCoord')
        mp = nt.nodes.new('ShaderNodeMapping')
        mp.inputs['Scale'].default_value = (1.0 / kachel, 1.0 / kachel, 1.0)
        nt.links.new(tk.outputs['UV'], mp.inputs['Vector'])
        vek = mp.outputs['Vector']

    def tex(n, f):
        t = nt.nodes.new('ShaderNodeTexImage')
        t.image = bild(n, f)
        t.interpolation = 'Cubic' if not f else 'Linear'
        nt.links.new(vek, t.inputs['Vector'])
        return t
    if karte:
        nt.links.new(tex(karte, True).outputs['Color'], b.inputs['Base Color'])
    if rau_karte:
        nt.links.new(tex(rau_karte, False).outputs['Color'], b.inputs['Roughness'])
    if normal:
        t = tex(normal, False)
        nm = nt.nodes.new('ShaderNodeNormalMap')
        nm.inputs['Strength'].default_value = nstaerke
        nt.links.new(t.outputs['Color'], nm.inputs['Color'])
        nt.links.new(nm.outputs['Normal'], b.inputs['Normal'])
    return m


def fensterglas():
    """Duennes Glas: Sonne und Schattenstrahlen gehen ungebrochen durch --
    sonst waere das Tageslicht eine Kaustik und bliebe ohne Kaustiken
    draussen (gemessen: kein Sonnenfleck im Raum). Nur fuer Cycles; in den
    Unreal-Export geht das Glas nicht mit."""
    m = bpy.data.materials.new('Fensterglas')
    m.use_nodes = True
    nt = m.node_tree
    for n in list(nt.nodes):
        nt.nodes.remove(n)
    aus = nt.nodes.new('ShaderNodeOutputMaterial')
    glas = nt.nodes.new('ShaderNodeBsdfGlass')
    glas.inputs['IOR'].default_value = 1.0
    glas.inputs['Roughness'].default_value = 0.0
    glanz = nt.nodes.new('ShaderNodeBsdfGlossy')
    glanz.inputs['Roughness'].default_value = 0.0
    fres = nt.nodes.new('ShaderNodeFresnel')
    fres.inputs['IOR'].default_value = 1.52
    durch = nt.nodes.new('ShaderNodeBsdfTransparent')
    mix1 = nt.nodes.new('ShaderNodeMixShader')
    nt.links.new(fres.outputs['Fac'], mix1.inputs['Fac'])
    nt.links.new(durch.outputs['BSDF'], mix1.inputs[1])
    nt.links.new(glanz.outputs['BSDF'], mix1.inputs[2])
    lp = nt.nodes.new('ShaderNodeLightPath')
    mx = nt.nodes.new('ShaderNodeMath')
    mx.operation = 'MAXIMUM'
    nt.links.new(lp.outputs['Is Shadow Ray'], mx.inputs[0])
    nt.links.new(lp.outputs['Is Diffuse Ray'], mx.inputs[1])
    mix2 = nt.nodes.new('ShaderNodeMixShader')
    nt.links.new(mx.outputs['Value'], mix2.inputs['Fac'])
    nt.links.new(mix1.outputs['Shader'], mix2.inputs[1])
    nt.links.new(durch.outputs['BSDF'], mix2.inputs[2])
    nt.links.new(mix2.outputs['Shader'], aus.inputs['Surface'])
    return m


def objekt(name, me):
    o = bpy.data.objects.new(name, me)
    bpy.context.scene.collection.objects.link(o)
    return o


def glatt_nach_winkel(o, winkel=50):
    """Fasen weich, 90-Grad-Kanten hart (ohne Modifikator, exportfest)."""
    me = o.data
    for p in me.polygons:
        p.use_smooth = True
    try:
        with bpy.context.temp_override(object=o, active_object=o, selected_objects=[o], selected_editable_objects=[o]):
            bpy.ops.object.shade_smooth_by_angle(angle=math.radians(winkel), keep_sharp_edges=True)
    except Exception as f:
        log('smooth_by_angle nicht moeglich:', f)


def uv_welt(o, versatz=(0.0, 0.0), spiegel_x=None, funktion=None):
    """UV in Metern aus Weltkoordinaten nach Hauptachse der Normalen.
    Senkrechte Flaechen: (waagrecht, z) -- Maserung der Eiche laeuft damit
    senkrecht. spiegel_x: Achse fuer Bookmatch. funktion(co, n) ersetzt die
    Projektion ganz (Wasserfall-Wangen)."""
    me = o.data
    mw = o.matrix_world
    uvl = me.uv_layers[0] if me.uv_layers else me.uv_layers.new(name='UVMap')
    for p in me.polygons:
        n = p.normal
        for li in p.loop_indices:
            c = mw @ me.vertices[me.loops[li].vertex_index].co
            if funktion:
                u, v = funktion(c, n)
            else:
                ax, ay, az = abs(n.x), abs(n.y), abs(n.z)
                x = c.x if spiegel_x is None else (2 * spiegel_x - c.x)
                if az >= max(ax, ay):
                    u, v = x, c.y
                elif ay >= ax:
                    u, v = x, c.z
                else:
                    u, v = c.y, c.z
            uvl.data[li].uv = (u + versatz[0], v + versatz[1])


def kasten(name, x0, x1, y0, y1, z0, z1, mat, fase=0.0012, versatz=None, spiegel_x=None, funktion=None):
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x = x0 + (v.co.x + 0.5) * (x1 - x0)
        v.co.y = y0 + (v.co.y + 0.5) * (y1 - y0)
        v.co.z = z0 + (v.co.z + 0.5) * (z1 - z0)
    if fase > 0:
        f = min(fase, 0.45 * min(x1 - x0, y1 - y0, z1 - z0))
        bmesh.ops.bevel(bm, geom=list(bm.edges), offset=f, segments=2, affect='EDGES', profile=0.5)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    for mm in (mat if isinstance(mat, (list, tuple)) else [mat]):
        me.materials.append(mm)
    o = objekt(name, me)
    if versatz is None:
        versatz = (random.uniform(0, 3), random.uniform(0, 3))
    uv_welt(o, versatz, spiegel_x, funktion)
    glatt_nach_winkel(o)
    return o


def netz(name, V, F, mat, glatt=True):
    me = bpy.data.meshes.new(name)
    me.from_pydata(V, [], F)
    me.update()
    for mm in (mat if isinstance(mat, (list, tuple)) else [mat]):
        me.materials.append(mm)
    o = objekt(name, me)
    for p in me.polygons:
        p.use_smooth = glatt
    return o


def drehkoerper(name, profil, mat, n=64, versatz=(0, 0, 0), uv_hoehe=1.0):
    """Profil [(r, z)] um Z gedreht. UV: u = Umfang (0..1), v = z/uv_hoehe."""
    V, F, UV = [], [], []
    m = len(profil)
    for i in range(n + 1):
        a = 2 * math.pi * i / n
        for r, z in profil:
            V.append((versatz[0] + r * math.cos(a), versatz[1] + r * math.sin(a), versatz[2] + z))
    for i in range(n):
        for k in range(m - 1):
            a0 = i * m + k
            b0 = (i + 1) * m + k
            F.append((a0, b0, b0 + 1, a0 + 1))
    o = netz(name, V, F, mat)
    me = o.data
    uvl = me.uv_layers.new(name='UVMap')
    for p in me.polygons:
        for li in p.loop_indices:
            vi = me.loops[li].vertex_index
            i, k = divmod(vi, m)
            uvl.data[li].uv = (i / n, profil[k][1] / uv_hoehe)
    return o


def rohr(name, punkte, radius, mat, rund=4):
    cu = bpy.data.curves.new(name + '_k', 'CURVE')
    cu.dimensions = '3D'
    cu.bevel_depth = radius
    cu.bevel_resolution = rund
    cu.use_fill_caps = True
    sp = cu.splines.new('POLY')
    sp.points.add(len(punkte) - 1)
    for i, p in enumerate(punkte):
        sp.points[i].co = (*p, 1.0)
    ok = bpy.data.objects.new(name + '_k', cu)
    bpy.context.scene.collection.objects.link(ok)
    bpy.context.view_layer.update()
    me = bpy.data.meshes.new_from_object(ok.evaluated_get(bpy.context.evaluated_depsgraph_get()))
    bpy.data.objects.remove(ok, do_unlink=True)
    me.name = name
    me.materials.clear()
    me.materials.append(mat)
    o = objekt(name, me)
    for p in me.polygons:
        p.use_smooth = True
    if not me.uv_layers:
        me.uv_layers.new(name='UVMap')
    return o


def bogen(mitte, r, a0, a1, n=24, ebene='yz'):
    out = []
    for k in range(n + 1):
        a = math.radians(a0 + (a1 - a0) * k / n)
        if ebene == 'yz':
            out.append((mitte[0], mitte[1] + r * math.cos(a), mitte[2] + r * math.sin(a)))
        else:
            out.append((mitte[0] + r * math.cos(a), mitte[1], mitte[2] + r * math.sin(a)))
    return out


def rund_umriss(bx, by, r, n=8, mitte=(0.0, 0.0)):
    pts = []
    for cx, cy, a0 in ((bx / 2 - r, by / 2 - r, 0), (-bx / 2 + r, by / 2 - r, 90),
                       (-bx / 2 + r, -by / 2 + r, 180), (bx / 2 - r, -by / 2 + r, 270)):
        for k in range(n + 1):
            a = math.radians(a0 + 90 * k / n)
            pts.append((mitte[0] + cx + r * math.cos(a), mitte[1] + cy + r * math.sin(a)))
    return pts


def prisma(name, umriss, z0, z1, mat, fase=0.0, versatz=(0, 0)):
    """Umriss (xy, gegen den Uhrzeigersinn) von z0 bis z1 extrudiert, mit Fase."""
    bm = bmesh.new()
    unten = [bm.verts.new((x, y, z0)) for x, y in umriss]
    oben = [bm.verts.new((x, y, z1)) for x, y in umriss]
    bm.faces.new(list(reversed(unten)))
    bm.faces.new(oben)
    n = len(umriss)
    for i in range(n):
        j = (i + 1) % n
        bm.faces.new((unten[i], unten[j], oben[j], oben[i]))
    if fase > 0:
        kanten = [e for e in bm.edges if abs(e.verts[0].co.z - e.verts[1].co.z) < 1e-6]
        bmesh.ops.bevel(bm, geom=kanten, offset=fase, segments=2, affect='EDGES', profile=0.5)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(mat)
    o = objekt(name, me)
    uv_welt(o, versatz)
    glatt_nach_winkel(o, 40)
    return o


def ausschneiden(ziel, schneider):
    m = ziel.modifiers.new('aus', 'BOOLEAN')
    m.operation = 'DIFFERENCE'
    m.solver = 'EXACT'
    m.object = schneider
    with bpy.context.temp_override(object=ziel, active_object=ziel, selected_objects=[ziel], selected_editable_objects=[ziel]):
        bpy.ops.object.modifier_apply(modifier=m.name)
    bpy.data.objects.remove(schneider, do_unlink=True)


def kelvin(k):
    """Grobe Schwarzkoerperfarbe (linear, normiert) fuer Leuchtflaechen."""
    t = k / 100.0
    r = 1.0 if t <= 66 else min(1.0, 1.292936 * ((t - 60) ** -0.1332047592))
    g = (0.39008157 * math.log(t) - 0.63184144) if t <= 66 else 1.129890861 * ((t - 60) ** -0.0755148492)
    b = 1.0 if t >= 66 else (0.0 if t <= 19 else 0.54320679 * math.log(t - 10) - 1.19625409)
    srgb = [max(0.0, min(1.0, c)) for c in (r, g, b)]
    return tuple(((c + 0.055) / 1.055) ** 2.4 if c > 0.04045 else c / 12.92 for c in srgb)


# ====================================================================== Materialien
def materialien():
    M = {}
    M['eiche'] = stoff('Eiche furniert', (1, 1, 1), 0.45, karte='eiche-farbe.jpg', rau_karte='eiche-rau-matt.jpg',
                       normal='eiche-normal.jpg', nstaerke=0.35, kachel=1.83)
    M['matt'] = stoff('Supermatt Schwarz', (0.018, 0.018, 0.019), 0.52, rau_karte='matt-rau.jpg',
                      normal='matt-normal.jpg', nstaerke=0.12, kachel=1.0, spec=0.42)
    M['oro'] = stoff('Keramik Calacatta Oro', (1, 1, 1), 0.28, karte='oro-farbe.jpg', rau_karte='oro-rau-seide.jpg',
                     normal='oro-normal.jpg', nstaerke=0.10, kachel=1.60, spec=0.5)
    M['gold'] = stoff('Champagner gebuerstet', (0.80, 0.64, 0.40), 0.27, metall=1.0, aniso=0.6)
    M['gold_rau'] = stoff('Champagner Profil', (0.74, 0.60, 0.40), 0.36, metall=1.0, aniso=0.5)
    M['sockel'] = stoff('Sockel Schwarz', (0.012, 0.012, 0.012), 0.6)
    M['korpus'] = stoff('Korpus Anthrazit', (0.05, 0.05, 0.052), 0.5)
    M['putz'] = stoff('Kalkputz', (1, 1, 1), 0.9, karte='putz-farbe-warm.jpg', rau_karte='putz-rau.jpg',
                      normal='putz-normal.jpg', nstaerke=0.6, kachel=2.0)
    M['decke'] = stoff('Decke weiss', (0.78, 0.77, 0.75), 0.9)
    M['boden'] = stoff('Eichendiele', (1, 1, 1), 0.38, karte='boden-farbe.jpg', rau_karte='boden-rau.jpg',
                       normal='boden-normal.jpg', nstaerke=0.6, kachel=1.70)
    M['glas_schwarz'] = stoff('Glas Schwarz', (0.008, 0.008, 0.009), 0.03, spec=0.5, coat=0.0)
    M['kochfeld'] = stoff('Kochfeld', (1, 1, 1), 0.04, karte='kochfeld-modern.png', kachel=1.0, spec=0.5)
    M['edelstahl'] = stoff('Edelstahl gebuerstet', (0.60, 0.59, 0.57), 0.30, metall=1.0, aniso=0.5)
    M['stahl_schwarz'] = stoff('Stahl pulverbeschichtet', (0.022, 0.022, 0.022), 0.42, metall=0.0, spec=0.45)
    M['fensterglas'] = fensterglas()
    M['keramik_weiss'] = stoff('Steingut weiss matt', (0.74, 0.72, 0.68), 0.55)
    M['keramik_sand'] = stoff('Steingut Sand', (0.52, 0.44, 0.35), 0.6)
    M['keramik_dunkel'] = stoff('Steingut Graphit', (0.07, 0.068, 0.065), 0.5)
    M['leder'] = stoff('Leder Cognac', (1, 1, 1), 0.6, karte='leder-farbe.jpg', rau_karte='leder-rau.jpg',
                       normal='leder-normal.jpg', nstaerke=0.6, kachel=0.40, sheen=0.1)
    M['zitrone'] = stoff('Zitrone', (1, 1, 1), 0.38, karte='zitrone-farbe.jpg', normal='zitrone-normal.jpg',
                         nstaerke=0.45, kachel=1.0, spec=0.55, coat=0.25, coat_rau=0.2)
    M['blatt'] = stoff('Zitronenblatt', (0.05, 0.13, 0.03), 0.35, spec=0.5)
    M['buch1'] = stoff('Buch Leinen Sand', (0.46, 0.40, 0.32), 0.8)
    M['buch2'] = stoff('Buch Leinen Olive', (0.16, 0.17, 0.11), 0.8)
    M['buch3'] = stoff('Buch Papier', (0.70, 0.67, 0.60), 0.85)
    M['led'] = stoff('LED 2700K', (0, 0, 0), 0.5, emiss=(kelvin(2700), 22.0))
    M['led_pendel'] = stoff('LED Pendel 2700K', (0, 0, 0), 0.5, emiss=(kelvin(2700), 14.0))
    M['led_diffusor'] = stoff('Diffusor opal', (0.8, 0.8, 0.8), 0.4, emiss=(kelvin(2700), 6.0))
    M['anzeige'] = stoff('Anzeige', (0.0, 0.0, 0.0), 0.2, emiss=((1.0, 0.62, 0.30), 1.2))
    M['terrasse'] = stoff('Terrasse Stein', (1, 1, 1), 0.55, karte='oro-farbe.jpg', rau_karte='oro-rau-seide.jpg', kachel=1.2)
    M['aussenwand'] = stoff('Aussenwand', (1, 1, 1), 0.9, karte='putz-farbe-warm.jpg', kachel=3.0)
    M['meer'] = stoff('Meer', (0.010, 0.035, 0.050), 0.06, spec=0.5, normal='putz-normal.jpg', nstaerke=0.3, kachel=40.0)
    return M


# ====================================================================== Raum
def raum(M):
    kasten('boden', X_L - 0.3, X_R, Y_V - 0.3, Y_H + 0.3, -0.05, 0.0, M['boden'], fase=0, versatz=(0.37, 0.11))
    kasten('decke', X_L - 0.3, X_R + 0.3, Y_V - 0.3, Y_H + 0.3, H_RAUM, H_RAUM + 0.05, M['decke'], fase=0)
    kasten('wand_hinten', X_L - 0.3, X_R + 0.3, Y_H, Y_H + 0.2, 0, H_RAUM, M['putz'], fase=0)
    kasten('wand_links', X_L - 0.2, X_L, Y_V - 0.3, Y_H + 0.3, 0, H_RAUM, M['putz'], fase=0)
    kasten('wand_vorn', X_L - 0.3, X_R + 0.3, Y_V - 0.2, Y_V, 0, H_RAUM, M['putz'], fase=0)
    # rechte Wand mit raumhoher Oeffnung y -1,80..1,40, z 0..2,55, Wandstaerke 0,30
    F0, F1, FZ = -1.80, 1.40, 2.55
    kasten('wand_rechts_a', X_R, X_R + 0.30, Y_V - 0.3, F0, 0, H_RAUM, M['putz'], fase=0)
    kasten('wand_rechts_b', X_R, X_R + 0.30, F1, Y_H + 0.3, 0, H_RAUM, M['putz'], fase=0)
    kasten('wand_rechts_sturz', X_R, X_R + 0.30, F0, F1, FZ, H_RAUM, M['putz'], fase=0)
    kasten('schwelle', X_R, X_R + 0.30, F0, F1, -0.05, 0.0, M['boden'], fase=0)
    # Stahlfenster: Rahmen 50 x 60 mm, zwei Pfosten, ein Kaempfer bei 2,15
    xr0, xr1 = X_R + 0.10, X_R + 0.16
    s = M['stahl_schwarz']
    kasten('fenster_rahmen_u', xr0, xr1, F0, F1, 0.0, 0.03, s, 0.002)
    kasten('fenster_rahmen_o', xr0, xr1, F0, F1, FZ - 0.05, FZ, s, 0.002)
    kasten('fenster_rahmen_l', xr0, xr1, F0, F0 + 0.05, 0.0, FZ, s, 0.002)
    kasten('fenster_rahmen_r', xr0, xr1, F1 - 0.05, F1, 0.0, FZ, s, 0.002)
    b = (F1 - F0 - 0.10) / 3
    for i in (1, 2):
        y = F0 + 0.05 + i * b
        kasten('fenster_pfosten_%d' % i, xr0, xr1, y - 0.02, y + 0.02, 0.0, FZ, s, 0.002)
    kasten('fenster_kaempfer', xr0, xr1, F0, F1, 2.13, 2.17, s, 0.002)
    for i in range(3):
        ya = F0 + 0.05 + i * b + (0.02 if i else 0.0)
        yb = F0 + 0.05 + (i + 1) * b - (0.02 if i < 2 else 0.0)
        for (za, zb, t) in ((0.03, 2.13, 'u'), (2.17, FZ - 0.05, 'o')):
            kasten('fensterglas_%d%s' % (i, t), xr0 + 0.026, xr0 + 0.034, ya, yb, za, zb, M['fensterglas'], fase=0)
    # draussen: Terrasse, Brüstungsmauer, Hecke -- nur als Licht- und Tiefenkulisse
    kasten('terrasse', X_R + 0.30, X_R + 14.0, -12.0, 12.0, -0.08, -0.02, M['terrasse'], fase=0)
    kasten('mauer', X_R + 6.0, X_R + 6.4, -12.0, 12.0, -0.02, 0.95, M['aussenwand'], fase=0.01)
    # Dahinter das Meer (Sizilien): grosse Wasserflaeche weit unten, spiegelt den Himmel
    kasten('meer', X_R + 40.0, X_R + 6000.0, -6000.0, 6000.0, -61.0, -60.0, M['meer'], fase=0)
    kasten('hang', X_R + 6.4, X_R + 40.0, -6000.0, 6000.0, -61.0, -1.0, M['aussenwand'], fase=0)

# ====================================================================== Kuechenzeile + Hochschraenke
def fronten_unterschrank(M, x0, x1, y_front, name, auszuege=2):
    """Zwei Auszuege mit Gola: oben Mulde unter der Platte, dazwischen C-Profil."""
    s = M['matt']
    zf = [(SOCKEL + 0.003, Z_GOLA_MITTE[0]), (Z_GOLA_MITTE[1], Z_GOLA_OBEN[0])]
    for i, (za, zb) in enumerate(zf):
        kasten('%s_front_%d' % (name, i), x0 + FUGE / 2, x1 - FUGE / 2, y_front - FRONT, y_front, za, zb, s, 0.0012)


def zeile(M):
    y0 = Y_FRONT
    # Korpus als Block (sichtbar nur durch die Fugen), Sockel zurueckgesetzt
    kasten('zeile_korpus', X_ZEILE0, X_ZEILE1, y0 + 0.035, Y_H - 0.01, SOCKEL, Z_KO, M['korpus'], fase=0)
    kasten('zeile_sockel', X_ZEILE0, X_ZEILE1 - 0.02, y0 + 0.06, y0 + 0.08, 0.0, SOCKEL, M['sockel'], fase=0.001)
    n = 4
    b = (X_ZEILE1 - X_ZEILE0) / n
    for i in range(n):
        fronten_unterschrank(M, X_ZEILE0 + i * b, X_ZEILE0 + (i + 1) * b, y0 + FRONT, 'zeile_%d' % i)
    # Griffmulden (Profil sichtbar als Rueckflaeche und Boden der Mulde)
    for nm, (za, zb) in (('gola_oben', (Z_GOLA_OBEN[0] + 0.002, Z_KO)), ('gola_mitte', (Z_GOLA_MITTE[0] + 0.002, Z_GOLA_MITTE[1] - 0.002))):
        kasten('zeile_%s' % nm, X_ZEILE0, X_ZEILE1, y0 + 0.030, y0 + 0.036, za, zb, M['gold_rau'], fase=0.0005)
        kasten('zeile_%s_boden' % nm, X_ZEILE0, X_ZEILE1, y0 + FRONT, y0 + 0.036, za, za + 0.002, M['gold_rau'], fase=0)
    # Arbeitsplatte 3 cm, 3 cm Ueberstand, Seitenkante rechts sichtbar
    kasten('zeile_platte', X_ZEILE0, X_ZEILE1, y0 - 0.03, Y_H, Z_KO, ARBEIT, M['oro'], fase=0.0015, versatz=(0.4, 0.2))
    # Rueckwand raumhoch, zwei Platten gespiegelt (Bookmatch) an der Stossfuge
    xm = (X_ZEILE0 + X_ZEILE1) / 2
    kasten('rueckwand_a', X_ZEILE0, xm - 0.0006, Y_H - 0.012, Y_H, ARBEIT, H_RAUM - 0.002, M['oro'], fase=0.0008, versatz=(0.9, 0.35))
    kasten('rueckwand_b', xm + 0.0006, X_ZEILE1, Y_H - 0.012, Y_H, ARBEIT, H_RAUM - 0.002, M['oro'], fase=0.0008,
           versatz=(0.9, 0.35), spiegel_x=xm)
    # Wange rechts (Stein) bis zum Boden: der Block endet sauber vor dem Fenster
    kasten('zeile_wange', X_ZEILE1, X_ZEILE1 + 0.03, y0 - 0.03, Y_H, 0.0, ARBEIT, M['oro'], fase=0.0015, versatz=(1.3, 0.8))
    # Schwebendes Regal Eiche 4 cm mit LED darunter
    ry0, ry1 = Y_H - 0.012 - 0.26, Y_H - 0.012
    kasten('regal', -0.05, 1.85, ry0, ry1, 1.52, 1.56, M['eiche'], fase=0.0015)
    kasten('regal_led', -0.02, 1.82, ry0 + 0.015, ry0 + 0.03, 1.516, 1.52, M['led'], fase=0)
    return xm


def hochschraenke(M):
    y0 = Y_FRONT
    # Korpus als Block -- ausser dort, wo die Nische offen ist (x -0,90..-0,30)
    kasten('hoch_korpus', X_HOCH0, -0.90, y0 + 0.02, Y_H - 0.01, SOCKEL, H_RAUM - 0.003, M['korpus'], fase=0)
    kasten('hoch_korpus_nu', -0.90, X_HOCH1, y0 + 0.02, Y_H - 0.01, SOCKEL, 0.88, M['korpus'], fase=0)
    kasten('hoch_korpus_no', -0.90, X_HOCH1, y0 + 0.02, Y_H - 0.01, 2.195, H_RAUM - 0.003, M['korpus'], fase=0)
    kasten('hoch_sockel', X_HOCH0, X_HOCH1, y0 + 0.06, y0 + 0.08, 0.0, SOCKEL, M['sockel'], fase=0.001)
    # Seitenwange rechts (Eiche), sichtbar ueber der Arbeitsplatte
    kasten('hoch_wange', X_HOCH1, X_HOCH1 + FRONT, y0, Y_H - 0.002, 0.0, H_RAUM - 0.003, M['eiche'], fase=0.0012, versatz=(0.0, 0.0))
    E = M['eiche']
    z0 = SOCKEL + 0.003
    zt = 2.195          # Teilung Tuer / Aufsatz
    zo = H_RAUM - 0.003
    w = 0.60
    xs = [X_HOCH0 + i * w for i in range(5)]   # -2,70 -2,10 -1,50 -0,90 -0,30
    vf = y0 + FRONT
    def tuer(nm, xa, xb, za, zb):
        # Fortlaufendes Furnierbild: gleiche Weltprojektion fuer alle Tueren
        return kasten(nm, xa, xb, y0, vf, za, zb, E, fase=0.0012, versatz=(0.0, 0.0))
    # Kuehlen / Gefrieren: zwei hohe Tueren mit senkrechter Griffmulde dazwischen
    tuer('hoch_0_tuer', xs[0] + 0.003, xs[1] - 0.015, z0, zt)
    tuer('hoch_1_tuer', xs[1] + 0.015, xs[2] - FUGE / 2, z0, zt)
    kasten('hoch_gola_senkrecht', xs[1] - 0.015, xs[1] + 0.015, y0 + 0.028, y0 + 0.034, z0, zt, M['gold_rau'], fase=0.0005)
    for i in range(4):
        tuer('hoch_%d_aufsatz' % i, xs[i] + (0.003 if i == 0 else FUGE / 2), xs[i + 1] - FUGE / 2, zt + FUGE, zo)
    # Backofensaeule
    xa, xb = xs[2] + FUGE / 2, xs[3] - FUGE / 2
    tuer('hoch_2_auszug', xa, xb, z0, 0.52)
    kasten('waermeschublade', xa, xb, y0, vf, 0.523, 0.667, M['glas_schwarz'], fase=0.001)
    for nm, za, zb in (('backofen', 0.670, 1.265), ('dampfgarer', 1.268, 1.720)):
        kasten(nm, xa, xb, y0 - 0.002, vf, za, zb, M['glas_schwarz'], fase=0.0015)
        kasten(nm + '_leiste', xa + 0.004, xb - 0.004, y0 - 0.006, y0 - 0.002, zb - 0.030, zb - 0.012, M['gold'], fase=0.0008)
        kasten(nm + '_anzeige', (xa + xb) / 2 - 0.035, (xa + xb) / 2 + 0.035, y0 - 0.0022, y0 - 0.0018, zb - 0.070, zb - 0.052, M['anzeige'], fase=0)
    tuer('hoch_2_tuer_oben', xa, xb, 1.723, zt)
    # Nische mit Eiche innen, Regal und LED
    xa, xb = xs[3] + FUGE / 2, xs[4] - FUGE / 2
    tuer('hoch_3_auszug_u', xa, xb, z0, 0.52)
    tuer('hoch_3_auszug_o', xa, xb, 0.523, 0.88)
    t = 0.019
    ni0, ni1 = 0.88 + FUGE, zt - FUGE
    kasten('nische_boden', xa, xb, vf, Y_H - 0.01, ni0, ni0 + t, E, fase=0.001)
    kasten('nische_decke', xa, xb, vf, Y_H - 0.01, ni1 - t, ni1, E, fase=0.001)
    kasten('nische_seite_l', xa, xa + t, vf, Y_H - 0.01, ni0, ni1, E, fase=0.001)
    kasten('nische_seite_r', xb - t, xb, vf, Y_H - 0.01, ni0, ni1, E, fase=0.001)
    kasten('nische_rueck', xa + t, xb - t, Y_H - 0.03, Y_H - 0.01, ni0 + t, ni1 - t, M['oro'], fase=0, versatz=(2.1, 0.4))
    kasten('nische_regal', xa + t, xb - t, vf + 0.04, Y_H - 0.03, 1.52, 1.54, E, fase=0.001)
    kasten('nische_led', xa + t + 0.01, xb - t - 0.01, vf + 0.03, vf + 0.045, ni1 - t - 0.004, ni1 - t, M['led'], fase=0)
    return xs


# ====================================================================== Insel
def insel_uv(c, n):
    """Wasserfall: die Aderung der Platte laeuft ueber die Kante in die Wangen
    und ueber die Vorderkante nach unten -- wie ein gefalteter Stein."""
    ax, ay, az = abs(n.x), abs(n.y), abs(n.z)
    if az >= max(ax, ay):
        return c.x, c.y
    if ax >= ay:
        s = 1 if n.x > 0 else -1
        return s * INSEL_X + s * (ARBEIT - c.z), c.y
    s = 1 if n.y > 0 else -1
    y_kante = INSEL_Y1 if s > 0 else INSEL_Y0
    return c.x, y_kante + s * (ARBEIT - c.z)


def insel(M):
    X = INSEL_X
    t = 0.03
    ky0, ky1 = INSEL_Y0 + 0.30, INSEL_Y1 - 0.03       # Korpus: Sitzseite 30 cm Ueberstand
    platte = kasten('insel_platte', -X, X, INSEL_Y0, INSEL_Y1, Z_KO, ARBEIT, M['oro'], fase=0.0015, funktion=insel_uv)
    for s in (-1, 1):
        xa, xb = (X - t, X) if s > 0 else (-X, -X + t)
        kasten('insel_wange_%s' % ('r' if s > 0 else 'l'), xa, xb, INSEL_Y0, INSEL_Y1, 0.0, Z_KO, M['oro'], fase=0.0015, funktion=insel_uv)
    kasten('insel_korpus', -X + t, X - t, ky0, ky1 - FRONT - 0.035, SOCKEL, Z_KO, M['korpus'], fase=0)
    kasten('insel_sockel', -X + t + 0.02, X - t - 0.02, ky0 + 0.05, ky1 - 0.08, 0.0, SOCKEL, M['sockel'], fase=0.001)
    # Sitzseite: fuenf glatte Paneele mit 3-mm-Fugen
    n = 5
    b = (2 * X - 2 * t) / n
    for i in range(n):
        kasten('insel_paneel_%d' % i, -X + t + i * b + FUGE / 2, -X + t + (i + 1) * b - FUGE / 2, ky0 - FRONT, ky0,
               SOCKEL + 0.003, Z_KO - 0.003, M['matt'], fase=0.0012)
    # Arbeitsseite: Auszuege mit Gola, Front buendig 3 cm unter der Plattenkante
    vf = ky1
    n = 5
    b = (2 * X - 2 * t) / n
    for i in range(n):
        xa, xb = -X + t + i * b, -X + t + (i + 1) * b
        for k, (za, zb) in enumerate([(SOCKEL + 0.003, Z_GOLA_MITTE[0]), (Z_GOLA_MITTE[1], Z_GOLA_OBEN[0])]):
            kasten('insel_front_%d_%d' % (i, k), xa + FUGE / 2, xb - FUGE / 2, vf - FRONT, vf, za, zb, M['matt'], fase=0.0012)
    for nm, (za, zb) in (('gola_oben', (Z_GOLA_OBEN[0] + 0.002, Z_KO)), ('gola_mitte', (Z_GOLA_MITTE[0] + 0.002, Z_GOLA_MITTE[1] - 0.002))):
        kasten('insel_%s' % nm, -X + t, X - t, vf - 0.036, vf - 0.030, za, zb, M['gold_rau'], fase=0.0005)
        kasten('insel_%s_boden' % nm, -X + t, X - t, vf - 0.036, vf - FRONT, za, za + 0.002, M['gold_rau'], fase=0)

    # Spuele (Unterbau) links, Armatur hinten
    sx, sy, sb, st = -0.72, 0.02, 0.70, 0.40
    schn = prisma('spuele_schnitt', rund_umriss(sb, st, 0.012, mitte=(sx, sy)), Z_KO - 0.01, ARBEIT + 0.01, M['oro'])
    ausschneiden(platte, schn)
    # Becken: Wanne aus Innenumriss, Boden 20 cm tiefer, Ablauf
    innen = rund_umriss(sb - 0.004, st - 0.004, 0.010, mitte=(sx, sy))
    V = [(x, y, Z_KO) for x, y in innen] + [(x, y, Z_KO - 0.20) for x, y in innen]
    m = len(innen)
    F = [(i, (i + 1) % m, m + (i + 1) % m, m + i) for i in range(m)]
    F.append(tuple(range(m, 2 * m)))          # Boden, Normale nach oben (ins Becken)
    becken = netz('spuele_becken', V, F, M['edelstahl'])
    uv_welt(becken)
    drehkoerper('spuele_ablauf', [(0.0, Z_KO - 0.199), (0.040, Z_KO - 0.199), (0.042, Z_KO - 0.197), (0.042, Z_KO - 0.196)],
                M['edelstahl'], n=48, versatz=(sx + 0.18, sy, 0))
    # Armatur: Sockel, senkrechtes Rohr, Bogen, Auslauf -- Champagner gebuerstet
    ax_, ay_ = sx, INSEL_Y1 - 0.075
    drehkoerper('armatur_rosette', [(0.0, ARBEIT), (0.029, ARBEIT), (0.030, ARBEIT + 0.002), (0.030, ARBEIT + 0.006),
                                    (0.026, ARBEIT + 0.010), (0.0, ARBEIT + 0.010)], M['gold'], n=64, versatz=(ax_, ay_, 0))
    r_bogen = 0.115
    z_top = ARBEIT + 0.32
    pfad = [(ax_, ay_, ARBEIT + 0.008), (ax_, ay_, z_top)]
    pfad += bogen((ax_, ay_ - r_bogen, z_top), r_bogen, 0, 180, n=28)[1:]
    pfad += [(ax_, ay_ - 2 * r_bogen, z_top - 0.07)]
    rohr('armatur_rohr', pfad, 0.0135, M['gold'])
    drehkoerper('armatur_auslauf', [(0.0, z_top - 0.085), (0.0105, z_top - 0.085), (0.0135, z_top - 0.080), (0.0135, z_top - 0.068)],
                M['gold'], n=48, versatz=(ax_, ay_ - 2 * r_bogen, 0))
    drehkoerper('armatur_strahlregler', [(0.0, z_top - 0.0852), (0.0095, z_top - 0.0852), (0.0095, z_top - 0.0845)],
                M['stahl_schwarz'], n=32, versatz=(ax_, ay_ - 2 * r_bogen, 0))
    rohr('armatur_hebel', [(ax_ + 0.017, ay_, ARBEIT + 0.16), (ax_ + 0.085, ay_ + 0.012, ARBEIT + 0.175)], 0.0055, M['gold'])

    # Kochfeld flaechenbuendig rechts, mit Muldenlueftung
    kx, ky, kb, kt = 0.62, -0.08, 0.80, 0.52
    schn = prisma('kochfeld_schnitt', rund_umriss(kb + 0.002, kt + 0.002, 0.004, mitte=(kx, ky)), Z_KO - 0.01, ARBEIT + 0.01, M['oro'])
    ausschneiden(platte, schn)
    kf = kasten('kochfeld', kx - kb / 2, kx + kb / 2, ky - kt / 2, ky + kt / 2, ARBEIT - 0.006, ARBEIT - 0.0002,
                [M['kochfeld']], fase=0.0015, versatz=(0, 0))
    # UV: Bild genau auf die Oberseite (vorn = -y = unten im Bild)
    me = kf.data
    uvl = me.uv_layers[0]
    for p in me.polygons:
        for li in p.loop_indices:
            c = me.vertices[me.loops[li].vertex_index].co
            uvl.data[li].uv = ((c.x - (kx - kb / 2)) / kb, (c.y - (ky - kt / 2)) / kt)
    kasten('kochfeld_fuge', kx - kb / 2 - 0.001, kx + kb / 2 + 0.001, ky - kt / 2 - 0.001, ky + kt / 2 + 0.001,
           ARBEIT - 0.02, ARBEIT - 0.0065, M['sockel'], fase=0)
    return platte


# ====================================================================== Leuchten
def pendel(M):
    # Lineare Pendelleuchte 2,20 m, 25 x 45 mm, 1,00 m ueber der Platte
    L, z = 2.20, ARBEIT + 1.00
    y = (INSEL_Y0 + INSEL_Y1) / 2
    kasten('pendel_profil', -L / 2, L / 2, y - 0.0125, y + 0.0125, z, z + 0.045, M['stahl_schwarz'], fase=0.002)
    kasten('pendel_diffusor', -L / 2 + 0.01, L / 2 - 0.01, y - 0.009, y + 0.009, z - 0.0015, z + 0.0005, M['led_pendel'], fase=0)
    for s in (-1, 1):
        x = s * (L / 2 - 0.25)
        rohr('pendel_seil_%s' % ('r' if s > 0 else 'l'), [(x, y, z + 0.045), (x, y, H_RAUM)], 0.0009, M['edelstahl'], rund=2)
        drehkoerper('pendel_baldachin_%s' % ('r' if s > 0 else 'l'),
                    [(0.0, H_RAUM - 0.022), (0.036, H_RAUM - 0.022), (0.038, H_RAUM - 0.020), (0.038, H_RAUM)],
                    M['stahl_schwarz'], n=48, versatz=(x, y, 0))


# ====================================================================== Hocker und Dinge
def hocker(M, x, y):
    zs = 0.64
    # Gestell: vier Beine aus Rundstahl, leicht gespreizt, Fussring
    for sx in (-1, 1):
        for sy in (-1, 1):
            rohr('hocker_bein', [(x + sx * 0.17, y + sy * 0.16, 0.0), (x + sx * 0.15, y + sy * 0.14, zs - 0.03)], 0.0095, M['stahl_schwarz'], rund=3)
    ring = [(x - 0.162, y - 0.152, 0.26), (x + 0.162, y - 0.152, 0.26), (x + 0.162, y + 0.152, 0.26), (x - 0.162, y + 0.152, 0.26), (x - 0.162, y - 0.152, 0.26)]
    rohr('hocker_ring', ring, 0.008, M['stahl_schwarz'], rund=3)
    kasten('hocker_traeger', x - 0.17, x + 0.17, y - 0.16, y + 0.16, zs - 0.035, zs - 0.02, M['stahl_schwarz'], fase=0.004)
    # Sitz: gepolstert, Leder Cognac, weiche Kanten
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= 0.42
        v.co.y *= 0.38
        v.co.z = v.co.z * 0.07 + 0.035
    bmesh.ops.bevel(bm, geom=list(bm.edges), offset=0.025, segments=5, affect='EDGES', profile=0.6)
    me = bpy.data.meshes.new('hocker_sitz')
    bm.to_mesh(me)
    bm.free()
    me.materials.append(M['leder'])
    o = objekt('hocker_sitz', me)
    o.location = (x, y, zs - 0.02)
    bpy.context.view_layer.update()
    uv_welt(o, (random.uniform(0, 1), random.uniform(0, 1)))
    for p in me.polygons:
        p.use_smooth = True
    # Rueckenlehne niedrig, zur Kamera
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= 0.40
        v.co.y *= 0.05
        v.co.z = v.co.z * 0.14 + 0.07
    bmesh.ops.bevel(bm, geom=list(bm.edges), offset=0.018, segments=4, affect='EDGES', profile=0.6)
    me = bpy.data.meshes.new('hocker_lehne')
    bm.to_mesh(me)
    bm.free()
    me.materials.append(M['leder'])
    o = objekt('hocker_lehne', me)
    o.location = (x, y - 0.20, zs + 0.12)
    o.rotation_euler = (math.radians(-8), 0, 0)
    bpy.context.view_layer.update()
    uv_welt(o, (random.uniform(0, 1), random.uniform(0, 1)))
    for p in me.polygons:
        p.use_smooth = True
    for sx in (-1, 1):
        rohr('hocker_lehnenstrebe', [(x + sx * 0.15, y - 0.15, zs - 0.03), (x + sx * 0.15, y - 0.205, zs + 0.13)], 0.007, M['stahl_schwarz'], rund=3)


def zitrone(M, ort, drehung, groesse=1.0):
    bm = bmesh.new()
    bmesh.ops.create_uvsphere(bm, u_segments=40, v_segments=28, radius=1.0)
    for v in bm.verts:
        z = v.co.z
        # Zitronenform: laenglich, kleine Spitzen an den Polen
        r = 1.0 - 0.08 * abs(z) ** 6
        v.co.x *= 0.029 * r
        v.co.y *= 0.029 * r
        v.co.z = z * 0.040 + math.copysign(0.004, z) * max(0.0, abs(z) - 0.94) / 0.06
    me = bpy.data.meshes.new('zitrone')
    bm.to_mesh(me)
    bm.free()
    uvl = me.uv_layers.new(name='UVMap')
    for p in me.polygons:
        for li in p.loop_indices:
            c = me.vertices[me.loops[li].vertex_index].co
            uvl.data[li].uv = ((math.atan2(c.y, c.x) / (2 * math.pi)) % 1.0, 0.5 + c.z / 0.088)
    me.materials.append(M['zitrone'])
    o = objekt('zitrone', me)
    for p in me.polygons:
        p.use_smooth = True
    o.location = ort
    o.rotation_euler = drehung
    o.scale = (groesse,) * 3
    return o


def dinge(M, xm):
    # Schale mit Zitronen auf der Insel
    bx, by = -0.05, -0.42
    profil = [(0.0, 0.0), (0.060, 0.0), (0.068, 0.004), (0.120, 0.040), (0.150, 0.085), (0.152, 0.090),
              (0.146, 0.090), (0.143, 0.084), (0.114, 0.042), (0.060, 0.012), (0.0, 0.012)]
    drehkoerper('schale', profil, M['keramik_sand'], n=72, versatz=(bx, by, ARBEIT))
    lagen = [(0.00, 0.00, 0.042, (1.45, 0.2, 0.3)), (0.055, 0.03, 0.050, (1.35, -0.3, 1.9)),
             (-0.05, 0.045, 0.052, (1.7, 0.1, -0.8)), (0.025, -0.06, 0.050, (1.5, 0.4, 2.8)),
             (-0.035, -0.035, 0.085, (1.2, 0.8, 0.6))]
    for dx, dy, dz, rot in lagen:
        zitrone(M, (bx + dx, by + dy, ARBEIT + dz), rot, random.uniform(0.95, 1.07))
    # eine Zitrone liegt auf der Platte daneben
    zitrone(M, (bx + 0.22, by + 0.06, ARBEIT + 0.029), (math.radians(90), 0, math.radians(30)))
    # Schneidbrett Eiche mit Griffloch, schraeg auf der Insel
    b = prisma('brett', rund_umriss(0.48, 0.28, 0.03, mitte=(0.0, 0.0)), 0, 0.022, M['eiche'], fase=0.002)
    loch = prisma('brett_loch', rund_umriss(0.028, 0.028, 0.0139, n=12, mitte=(-0.205, 0.0)), -0.01, 0.03, M['eiche'])
    ausschneiden(b, loch)
    b.location = (0.62, -0.50, ARBEIT)
    b.rotation_euler = (0, 0, math.radians(-12))
    # Regal: Vasen und Buecher
    y = Y_H - 0.012 - 0.13
    drehkoerper('vase_hoch', [(0.0, 0.0), (0.045, 0.0), (0.060, 0.05), (0.062, 0.14), (0.040, 0.22), (0.022, 0.26), (0.024, 0.285),
                              (0.020, 0.285), (0.018, 0.262), (0.0, 0.262)], M['keramik_dunkel'], n=64, versatz=(0.25, y, 1.56))
    drehkoerper('vase_rund', [(0.0, 0.0), (0.050, 0.0), (0.085, 0.05), (0.090, 0.09), (0.070, 0.15), (0.045, 0.17), (0.046, 0.18),
                              (0.040, 0.18), (0.0, 0.17)], M['keramik_weiss'], n=64, versatz=(0.44, y + 0.02, 1.56))
    for i, (d, mat, h) in enumerate(((0.030, M['buch1'], 0.24), (0.022, M['buch2'], 0.22), (0.026, M['buch3'], 0.20))):
        z0 = 1.56 + sum(q[0] for q in ((0.030,), (0.022,), (0.026,))[:i])
        kasten('buch_%d' % i, 1.20, 1.20 + h, y - 0.09 + i * 0.006, y + 0.07, z0, z0 + d, mat, fase=0.002)
    drehkoerper('teller_stapel', [(0.0, 0.0), (0.11, 0.0), (0.135, 0.012), (0.135, 0.045), (0.11, 0.045), (0.0, 0.045)],
                M['keramik_weiss'], n=72, versatz=(1.62, y, 1.56))
    # Nische: Glasgefaesse gibt es nicht -- zwei Dosen und eine Schale
    xa = -0.90 + 0.30
    drehkoerper('dose_1', [(0.0, 0.0), (0.055, 0.0), (0.055, 0.16), (0.050, 0.165), (0.0, 0.165)], M['keramik_weiss'], n=48,
                versatz=(xa - 0.08, Y_H - 0.17, 0.88 + FUGE + 0.019))
    drehkoerper('dose_2', [(0.0, 0.0), (0.045, 0.0), (0.045, 0.12), (0.040, 0.125), (0.0, 0.125)], M['keramik_sand'], n=48,
                versatz=(xa + 0.07, Y_H - 0.16, 0.88 + FUGE + 0.019))
    drehkoerper('nische_schale', profil, M['keramik_dunkel'], n=64, versatz=(xa, Y_H - 0.17, 1.54))
    # Zeile: Olivenoelflasche und Holzbrett an der Rueckwand
    b2 = prisma('brett_2', rund_umriss(0.36, 0.52, 0.035, mitte=(0.0, 0.0)), 0, 0.02, M['eiche'], fase=0.002)
    b2.rotation_euler = (math.radians(90 - 8), 0, 0)
    b2.location = (1.55, Y_H - 0.045, ARBEIT + 0.005)
    drehkoerper('flasche', [(0.0, 0.0), (0.034, 0.0), (0.036, 0.004), (0.036, 0.19), (0.030, 0.215), (0.014, 0.245), (0.013, 0.285),
                            (0.0, 0.285)], M['keramik_dunkel'], n=48, versatz=(1.30, Y_H - 0.12, ARBEIT))


# ====================================================================== Licht, Kamera, Render
SONNE = {'hoehe': 24.0, 'azimut': -18.0}   # Grad; Azimut 0 = Sonne genau im Osten (+X)


def sonne_richtung():
    h = math.radians(SONNE['hoehe'])
    a = math.radians(SONNE['azimut'])
    zur_sonne = Vector((math.cos(h) * math.cos(a), math.cos(h) * math.sin(a), math.sin(h)))
    return zur_sonne


def licht():
    sc = bpy.context.scene
    zs = sonne_richtung()
    ld = bpy.data.lights.new('Sonne', 'SUN')
    ld.energy = 4.2
    ld.angle = math.radians(0.55)
    ld.color = (1.0, 0.95, 0.88)
    so = bpy.data.objects.new('Sonne', ld)
    sc.collection.objects.link(so)
    so.rotation_euler = (-zs).to_track_quat('-Z', 'Y').to_euler()
    w = bpy.data.worlds.new('Himmel')
    sc.world = w
    w.use_nodes = True
    nt = w.node_tree
    bg = nt.nodes['Background']
    sky = nt.nodes.new('ShaderNodeTexSky')
    for typ in ('MULTIPLE_SCATTERING', 'NISHITA'):
        try:
            sky.sky_type = typ
            break
        except Exception:
            continue
    try:
        sky.sun_disc = False
    except Exception:
        pass
    sky.sun_elevation = math.radians(SONNE['hoehe'])
    sky.sun_rotation = math.atan2(zs.y, zs.x) - math.pi / 2
    nt.links.new(sky.outputs['Color'], bg.inputs['Color'])
    bg.inputs['Strength'].default_value = float(os.environ.get('VD_HIMMEL', '0.35'))
    return so, sky


KAMERAS = {
    # ort, ziel, Brennweite, Blende, Fokus-Ziel
    'gesamt': ((-1.55, -2.45, 1.32), (0.35, 1.20, 1.02), 24.0, 8.0),
    'insel':  ((2.45, -1.10, 1.45), (-1.10, 1.25, 0.98), 35.0, 5.6),
    'detail': ((0.05, -0.80, 1.14), (-0.72, 0.22, 1.11), 50.0, 2.8),
}


def kamera(name):
    ort, ziel, f, blende = KAMERAS[name]
    cd = bpy.data.cameras.new('Kam_' + name)
    cd.lens = f
    cd.sensor_width = 36.0
    cd.sensor_fit = 'HORIZONTAL'
    o = bpy.data.objects.new('Kam_' + name, cd)
    bpy.context.scene.collection.objects.link(o)
    o.location = ort
    d = Vector(ziel) - Vector(ort)
    horiz = math.hypot(d.x, d.y)
    if name == 'detail':
        # Nah: echte Neigung (keine Architekturaufnahme)
        o.rotation_euler = d.to_track_quat('-Z', 'Y').to_euler()
    else:
        # Architektur: Kamera waagrecht, Senkrechte bleiben senkrecht; Bildausschnitt ueber Objektivverschiebung
        yaw = math.atan2(d.y, d.x) - math.pi / 2
        o.rotation_euler = (math.radians(90), 0.0, yaw)
        cd.shift_y = (f * d.z / horiz) / 36.0
    cd.dof.use_dof = True
    cd.dof.aperture_fstop = blende
    cd.dof.focus_distance = d.length
    return o


def rendern_einstellen(probe):
    sc = bpy.context.scene
    sc.render.engine = 'CYCLES'
    prefs = bpy.context.preferences.addons['cycles'].preferences
    geraet = 'CPU'
    for typ in ('OPTIX', 'CUDA'):
        try:
            prefs.compute_device_type = typ
            prefs.get_devices()
            gpus = [d for d in prefs.devices if d.type == typ]
            if gpus:
                for d in prefs.devices:
                    d.use = d.type == typ
                geraet = 'GPU'
                break
        except Exception:
            continue
    sc.cycles.device = geraet
    log('Geraet', geraet, prefs.compute_device_type)
    sc.cycles.samples = 96 if probe else 1024
    sc.cycles.use_adaptive_sampling = True
    sc.cycles.adaptive_threshold = 0.02 if probe else 0.006
    sc.cycles.use_denoising = True
    try:
        sc.cycles.denoiser = 'OPTIX' if geraet == 'GPU' else 'OPENIMAGEDENOISE'
    except Exception:
        pass
    sc.cycles.max_bounces = 14
    sc.cycles.diffuse_bounces = 6
    sc.cycles.glossy_bounces = 6
    sc.cycles.transmission_bounces = 10
    sc.cycles.caustics_reflective = False
    sc.cycles.caustics_refractive = False
    sc.cycles.sample_clamp_indirect = 8.0
    sc.cycles.blur_glossy = 0.5
    sc.render.resolution_x = 1280 if probe else 2560
    sc.render.resolution_y = 720 if probe else 1440
    sc.render.resolution_percentage = 100
    sc.render.use_motion_blur = False
    sc.view_settings.view_transform = 'AgX'
    try:
        sc.view_settings.look = 'AgX - Medium High Contrast'
    except Exception:
        pass
    sc.view_settings.exposure = EV
    if os.environ.get('VD_SCHNELL'):              # Probelauf ohne Grafikkarte: nur Bildaufbau pruefen
        sc.cycles.samples = int(os.environ['VD_SCHNELL'])
        sc.render.resolution_percentage = 50
    sc.render.image_settings.file_format = 'PNG'
    sc.render.image_settings.color_depth = '16' if not probe else '8'


def helligkeit(pfad):
    """Median und Mittel der Leuchtdichte (0..1, im Bild), zum Nachmessen."""
    try:
        im = bpy.data.images.load(pfad, check_existing=False)
        px = list(im.pixels[:])
        n = len(px) // 4
        schritt = max(1, n // 20000)
        lum = sorted(0.2126 * px[i * 4] + 0.7152 * px[i * 4 + 1] + 0.0722 * px[i * 4 + 2] for i in range(0, n, schritt))
        bpy.data.images.remove(im)
        return {'median': round(lum[len(lum) // 2], 4), 'p95': round(lum[int(len(lum) * 0.95)], 4),
                'p99': round(lum[int(len(lum) * 0.99)], 4), 'ueber_098': round(sum(1 for v in lum if v > 0.98) / len(lum), 4)}
    except Exception as f:
        return {'fehler': str(f)}


def unreal_export(kam_name, sonne_obj, sky):
    """GLB + Szenendaten im Format von ue-a01_szene.py (Fallstudien)."""
    os.makedirs(UE_AUS, exist_ok=True)
    for o in list(bpy.data.objects):
        if o.name.startswith('fensterglas') or o.type in ('LIGHT', 'CAMERA'):
            o.select_set(False)
        elif o.type == 'MESH':
            o.select_set(True)
    glb = os.path.join(UE_AUS, 'kueche-modern.glb')
    bpy.ops.export_scene.gltf(filepath=glb, export_format='GLB', use_selection=True, export_apply=True,
                              export_image_format='AUTO', export_materials='EXPORT', export_yup=True,
                              export_lights=False, export_cameras=False)
    ort, ziel, f, blende = KAMERAS[kam_name]
    k = bpy.data.objects['Kam_' + kam_name]
    sz = {
        'projekt': 'kueche-modern', 'einheit': 'Meter, Blender Z-oben', 'breite': 2560, 'hoehe': 1440,
        'kamera': {'ort': list(ort), 'ziel': list(ziel), 'rotation_euler_rad': list(k.rotation_euler),
                   'brennweite_mm': f, 'sensor_mm': 36.0, 'blende': blende,
                   'fokus_m': (Vector(ziel) - Vector(ort)).length, 'shift_y': k.data.shift_y},
        'sonne': {'rotation_euler_rad': list(sonne_obj.rotation_euler), 'staerke_w_m2': sonne_obj.data.energy,
                  'farbe': list(sonne_obj.data.color), 'winkel_grad': math.degrees(sonne_obj.data.angle)},
        'himmel': {'art': sky.sky_type, 'hoehe_grad': SONNE['hoehe'], 'drehung_grad': math.degrees(sky.sun_rotation),
                   'staerke': bpy.context.scene.world.node_tree.nodes['Background'].inputs['Strength'].default_value},
        'lampen': [], 'belichtung': EV, 'ansicht': 'AgX',
    }
    with open(os.path.join(UE_AUS, 'kueche-modern.json'), 'w', encoding='utf-8') as fh:
        json.dump(sz, fh, ensure_ascii=False, indent=1)
    log('Unreal-Export', glb, os.path.getsize(glb))


def main():
    leeren()
    sc = bpy.context.scene
    sc.unit_settings.system = 'METRIC'
    sc.unit_settings.scale_length = 1.0
    M = materialien()
    raum(M)
    xm = zeile(M)
    hochschraenke(M)
    insel(M)
    pendel(M)
    for x in (-0.85, 0.0, 0.85):
        hocker(M, x + random.uniform(-0.02, 0.02), INSEL_Y0 - 0.26 + random.uniform(-0.03, 0.02))
    dinge(M, xm)
    so, sky = licht()
    for name in KAMERAS:
        kamera(name)
    n_obj = len([o for o in bpy.data.objects if o.type == 'MESH'])
    n_tri = sum(len(o.data.polygons) for o in bpy.data.objects if o.type == 'MESH')
    log('Objekte', n_obj, 'Flaechen', n_tri)
    os.makedirs(AUS, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(AUS, 'kueche-modern.blend'), compress=True)
    if MODUS == 'bauen':
        return
    if MODUS == 'unreal':
        unreal_export(KAMS[0], so, sky)
        return
    probe = MODUS == 'probe'
    rendern_einstellen(probe)
    bericht = {}
    for name in KAMS:
        sc.camera = bpy.data.objects['Kam_' + name]
        pfad = os.path.join(AUS, '%s-%s.png' % (name, MODUS))
        sc.render.filepath = pfad
        bpy.ops.render.render(write_still=True)
        bericht[name] = helligkeit(pfad)
        log(name, bericht[name])
    with open(os.path.join(AUS, 'bericht-%s.json' % MODUS), 'w', encoding='utf-8') as fh:
        json.dump(bericht, fh, indent=1)
    log('FERTIG')


main()
