# -*- coding: utf-8 -*-
"""Web-Export des Tischkonfigurators: backen, zusammenstellen, ausgeben.

DIE ENTSCHEIDUNG, WIE VIELE DATEIEN

Naheliegend waere "ein GLB je Variante" -- das waeren 72. Oder "eines fuer
alles" -- das geht nicht, weil die Laengen echte Geometrie sind. Richtig ist
die Aufteilung nach dem, was sich wirklich unterscheidet:

  MATERIAL ist keine Geometrie. Vier Hoelzer mal drei Metalle sind zwoelf
  Kombinationen, die der Browser zur Laufzeit umhaengt. Kein eigenes GLB.

  GESTELL ist Geometrie, aber skalierbar. Stahl hat keine Richtung; ein in X
  gestrecktes Profil sieht man nicht. Ein Gestellsatz je Form genuegt, die
  Laenge macht die Laufzeit.

  DIE PLATTE ist Geometrie und NICHT skalierbar. Gestreckte Maserung faellt
  sofort auf -- das ist genau der Fehler, den die Seite behauptet zu
  vermeiden. Drei Platten, je Laenge eine.

  => 3 Platten + 2 Gestelle in EINEM GLB, plus vier Holztexturen.

WARUM GEBACKEN WIRD

Die Maserung ist ein Knotenbaum aus rund 25 Knoten mit Arcustangens,
Quadratwurzel und drei Rauschtexturen. Das rechnet Cycles gern, ein
Fragment-Shader im Browser auf einem Mittelklassehandy nicht. Gebacken wird
deshalb auf eine Textur -- und zwar EINE je Holzart fuer die laengste Platte;
die kuerzeren greifen einen Ausschnitt daraus ab. Vier Texturen statt zwoelf.

KEIN DRACO. Dieselbe Regel wie in web_export.py: Der Loader der Seite laedt
mit blankem GLTFLoader. Ein komprimiertes GLB zeigt dort still nichts an --
kein Fehler, kein Bild.
"""
import bpy, bmesh, json, os, sys, math
from mathutils import Vector

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.append(P)
import tisch_varianten as tv
import tisch_konfigurator as tk

AUS = os.path.join(os.path.dirname(P), 'web-export')
HOELZER = tk.HOELZER
METALLE = tk.METALLE

# Aufloesung: Die laengste Platte ist 2,40 x 0,95 m. Bei 2048 px auf 2,40 m
# ist ein Texel 1,17 mm. Die Ringe liegen bei 5,9 mm -- also fuenf Texel je
# Ring. Das ist die Untergrenze, unter der die Maserung zu Brei wird; mehr
# waere schoener, kostet aber je Holzart das Vierfache.
TEX_B, TEX_H = 2048, 816
LAENGE_MAX = 2.40


def _bake_bereit(szene):
    szene.render.engine = 'CYCLES'
    szene.cycles.bake_type = 'DIFFUSE'
    szene.render.bake.use_pass_direct = False
    szene.render.bake.use_pass_indirect = False
    szene.render.bake.use_pass_color = True
    szene.render.bake.margin = 8
    szene.cycles.samples = 24          # Farbe braucht keine Strahlen


def _uv_planar(o, laenge, breite):
    """Planare UV von oben, physikalisch massstaeblich.

    Nicht Smart UV Project: Das wuerde jede Flaeche woanders hinlegen und die
    Maserung an den Kanten brechen. Hier soll die Platte aussehen, als waere
    sie aus EINEM Brett -- also wird von oben projiziert, und zwar so, dass
    2,40 m genau die Texturbreite fuellen. Kuerzere Platten fuellen weniger,
    bekommen damit denselben Massstab und einen eigenen Ausschnitt.
    """
    me = o.data
    if not me.uv_layers:
        me.uv_layers.new(name='UVMap')
    uv = me.uv_layers.active.data
    for poly in me.polygons:
        for li in poly.loop_indices:
            v = me.vertices[me.loops[li].vertex_index].co
            uv[li].uv = ((v.x + laenge / 2) / LAENGE_MAX,
                         (v.y + breite / 2) / breite)
    return me.uv_layers.active.name


def backen(szene=None):
    """Baeckt je Holzart eine Grundfarben- und eine Rauheitskarte."""
    szene = szene or bpy.context.scene
    os.makedirs(AUS, exist_ok=True)
    _bake_bereit(szene)

    # Eine Hilfsplatte in Maximallaenge -- auf ihr wird gebacken.
    teile = tv.bauen(laenge='gross', gestell='wange', holz='Eiche',
                     metall='Schwarzstahl', szene=szene)
    platte = next(o for o in teile if o.name.startswith('Platte'))
    for m in list(platte.modifiers):
        platte.modifiers.remove(m)          # Fase stoert die planare UV
    _uv_planar(platte, LAENGE_MAX, tv.BREITE)

    bericht = {}
    for holz in HOELZER:
        mat = bpy.data.materials['M_Holz_' + holz]
        platte.data.materials.clear()
        platte.data.materials.append(mat)
        nt = mat.node_tree
        bsdf = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')

        for art, socket, res in (('grundfarbe', 'Base Color', (TEX_B, TEX_H)),
                                 ('rauheit', 'Roughness', (TEX_B // 2, TEX_H // 2))):
            name = 'bake_%s_%s' % (holz, art)
            bild = bpy.data.images.get(name)
            if bild:
                bpy.data.images.remove(bild)
            bild = bpy.data.images.new(name, res[0], res[1],
                                       alpha=False, float_buffer=False,
                                       is_data=(art == 'rauheit'))
            ziel = nt.nodes.new('ShaderNodeTexImage')
            ziel.image = bild
            ziel.select = True
            nt.nodes.active = ziel

            if art == 'rauheit':
                # Rauheit ist kein Licht. Statt zu backen wird der Wert direkt
                # ausgegeben: Emission an den Ausgang, dann DIFFUSE backen.
                quelle = next((l.from_socket for l in nt.links
                               if l.to_node.name == bsdf.name
                               and l.to_socket.name == 'Roughness'), None)
                if quelle is None:
                    bpy.data.images.remove(bild)
                    nt.nodes.remove(ziel)
                    continue
                em = nt.nodes.new('ShaderNodeEmission')
                nt.links.new(quelle, em.inputs['Color'])
                aus_n = next(n for n in nt.nodes if n.type == 'OUTPUT_MATERIAL')
                alt = next(l.from_socket for l in nt.links if l.to_node.name == aus_n.name)
                nt.links.new(em.outputs['Emission'], aus_n.inputs['Surface'])
                szene.cycles.bake_type = 'EMIT'
            else:
                szene.cycles.bake_type = 'DIFFUSE'

            bpy.ops.object.select_all(action='DESELECT')
            platte.select_set(True)
            bpy.context.view_layer.objects.active = platte
            bpy.ops.object.bake(type=szene.cycles.bake_type)

            pfad = os.path.join(AUS, 'holz-%s-%s.png' % (holz.lower(), art))
            bild.filepath_raw = pfad
            bild.file_format = 'PNG'
            bild.save()
            bericht.setdefault(holz, {})[art] = dict(
                datei=os.path.basename(pfad),
                kb=round(os.path.getsize(pfad) / 1024, 1),
                px=list(res))

            if art == 'rauheit':
                nt.links.new(alt, aus_n.inputs['Surface'])
                nt.nodes.remove(em)
                szene.cycles.bake_type = 'DIFFUSE'
            nt.nodes.remove(ziel)

    return bericht


# =========================================================================
#  GLB
#
#  16.09.2026 -- der Grund, warum dieser Teil jetzt im Skript steht und
#  nicht mehr von Hand in der Blender-Konsole passiert:
#
#  Das erste GLB trug auf den Metallteilen KEINE UV. Fuer gebackene Texturen
#  braucht Metall auch keine -- es bekommt konstante Werte aus dem Manifest.
#  Aber three.js baut den Tangentenrahmen fuer Anisotropie aus den
#  UV-Ableitungen. Ohne UV ist der Rahmen entartet, und die Wange stand als
#  weisse Flaeche im Bild. Ich habe das zwei Durchgaenge lang fuer ein
#  Lichtproblem gehalten und die Rauheit hochgesetzt -- behandelt wurde ein
#  Symptom, das es nicht gab.
#
#  Lehre, die in den Export gehoert und nicht in eine Erinnerung: Wer
#  Anisotropie im Browser will, exportiert UV und Tangenten. Das kostet hier
#  rund 20 KB und ist der Unterschied zwischen gebuerstetem Stahl und einem
#  Spiegel.
# =========================================================================

GLB = 'tisch.glb'
EXPORT_SAMMLUNG = 'Tisch_Export'


def _in_export(teile, exp):
    """Teile aus der Arbeitssammlung nehmen, bevor der naechste Aufbau sie
    loescht.

    tv._sammlung() raeumt bei JEDEM Aufruf auf. Wer das vergisst, exportiert
    nur das zuletzt gebaute Gestell -- derselbe Fehler wie beim ersten GLB.
    """
    for o in teile:
        for c in list(o.users_collection):
            c.objects.unlink(o)
        exp.objects.link(o)


def _uv_buerstung(o):
    """Box-Projektion mit EINER festgelegten Buerstrichtung je Teil.

    Gebuerstetes Metall hat eine Richtung. Sie ist nicht beliebig: Blech und
    Profil werden in der Laengsrichtung gebuerstet, weil das die Richtung
    ist, in der das Band durch die Maschine laeuft. Also gilt hier die
    laengste Kante des Teils als Buerstrichtung -- fuer die Wange quer
    (das Blech ist tiefer als hoch), fuer Traverse und Zarge laengs, fuer
    das Bein senkrecht. Das ist kein Kompromiss, sondern das, was an einem
    echten Tisch zu sehen waere.

    U laeuft in Buerstrichtung, V quer dazu, beides in Metern. Auf den
    Stirnflaechen, deren Normale selbst die Buerstachse ist, faellt U auf die
    naechste Achse zurueck; diese Flaechen sind wenige Quadratzentimeter
    gross und im Tisch verdeckt.
    """
    me = o.data
    if not me.uv_layers:
        me.uv_layers.new(name='UVMap')
    uv = me.uv_layers.active.data

    ko = [v.co for v in me.vertices]
    ausdehnung = [max(c[i] for c in ko) - min(c[i] for c in ko) for i in range(3)]
    buerste = ausdehnung.index(max(ausdehnung))

    for poly in me.polygons:
        n = poly.normal
        haupt = max(range(3), key=lambda i: abs(n[i]))
        u_achse = buerste if buerste != haupt else (haupt + 1) % 3
        v_achse = ({0, 1, 2} - {haupt, u_achse}).pop()
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co
            uv[li].uv = (co[u_achse], co[v_achse])
    return 'xyz'[buerste]


def glb_schreiben(szene=None):
    """Baut drei Platten und beide Gestelle in eine Sammlung und schreibt
    daraus EIN GLB. Gibt einen Bericht zurueck, keine Zusicherung."""
    szene = szene or bpy.context.scene
    os.makedirs(AUS, exist_ok=True)

    exp = bpy.data.collections.get(EXPORT_SAMMLUNG)
    if exp is None:
        exp = bpy.data.collections.new(EXPORT_SAMMLUNG)
    if exp.name not in [k.name for k in szene.collection.children]:
        szene.collection.children.link(exp)
    for o in list(exp.objects):
        bpy.data.objects.remove(o, do_unlink=True)

    bericht = {'platten': [], 'gestelle': {}}

    # ---- Platten: je Laenge ein eigenes Netz, planare UV auf 2,40 m --------
    for schluessel, L in tv.LAENGEN.items():
        teile = tv.bauen(laenge=schluessel, gestell='wange', holz='Eiche',
                         metall='Schwarzstahl', szene=szene)
        platte = next(o for o in teile if o.name.startswith('Platte'))
        rest = [o for o in teile if o is not platte]
        _in_export([platte], exp)
        for o in rest:
            bpy.data.objects.remove(o, do_unlink=True)
        platte.name = platte.data.name = 'platte_%d' % round(L * 100)
        _uv_planar(platte, L, tv.BREITE)
        bericht['platten'].append(platte.name)

    # ---- Gestelle: ein Satz je Form, in Mittellaenge gebaut ---------------
    #      Die Laenge macht spaeter die Laufzeit, siehe Manifest.
    for gestell in tv.GESTELLE:
        teile = tv.bauen(laenge='mittel', gestell=gestell, holz='Eiche',
                         metall='Schwarzstahl', szene=szene)
        platte = next(o for o in teile if o.name.startswith('Platte'))
        metallteile = [o for o in teile if o is not platte]
        _in_export(metallteile, exp)
        bpy.data.objects.remove(platte, do_unlink=True)
        for o in metallteile:
            o.name = o.data.name = gestell + '_' + o.name.lower()
            achse = _uv_buerstung(o)
            bericht['gestelle'].setdefault(gestell, {})[o.name] = achse

    # ---- Schreiben --------------------------------------------------------
    bpy.ops.object.select_all(action='DESELECT')
    for o in exp.objects:
        o.select_set(True)
    bpy.context.view_layer.objects.active = next(iter(exp.objects))

    pfad = os.path.join(AUS, GLB)
    bpy.ops.export_scene.gltf(
        filepath=pfad,
        export_format='GLB',
        use_selection=True,
        export_apply=True,             # Fase und gewichtete Normalen einrechnen
        export_image_format='NONE',    # 490 KB -> 50 KB: Blender packt sonst
                                       # die Rauheitskarte als PNG mit ein
        export_texcoords=True,
        export_normals=True,
        export_tangents=True,          # fuer die Anisotropie im Browser
        export_draco_mesh_compression_enable=False,
        export_yup=True,
    )
    bericht['datei'] = os.path.basename(pfad)
    bericht['kb'] = round(os.path.getsize(pfad) / 1024, 1)
    bericht['objekte'] = len(exp.objects)
    return bericht
