# -*- coding: utf-8 -*-
"""Exportiert den reparierten Saal als showroom.glb fuer die Webseite.

Die Fassung, die bis heute auf vecom-design.it lag, trug exakt die Geometrie,
die in der Bauabnahme durchgefallen ist: neun Meter breite Portalbalken
zwischen 17,2 m entfernten Pfosten, ein dreissig Meter langer Boden in einem
vierundvierzig Meter langen Saal. Wer nur Blender repariert, repariert die
Seite nicht.

Drei Dinge, die hier nicht verhandelbar sind:

  KEIN DRACO. raum.js laedt mit einem blanken GLTFLoader, ohne DRACOLoader.
  Ein komprimiertes GLB wuerde dort still nichts anzeigen — kein Fehler, kein
  Bild.

  KEINE SOFTBOXEN, KEIN DUNST. Die drei Softboxen sind kameraunsichtbare
  Flaechenstrahler, das Dunstvolumen ist Cycles-Volumetrie. In three.js waeren
  das drei weisse Rechtecke und ein grauer Klotz mitten im Raum. Licht macht
  die Webfassung selbst, mit RoomEnvironment und den Emissiven.

  KEINE TEXTUREN, DIE DIE SEITE SCHON HAT. Der erste Export wog 1,80 MB gegen
  0,39 MB der alten Fassung. 730 KB davon waren eingebettete Bilder: die fuenf
  Arbeiten von den Displays und die drei Rauheits- und Normalkarten. raum.js
  laedt beide Gruppen ohnehin selbst aus /assets/img/ und ersetzt die
  Materialkarten beim Laden — eingebettet waeren sie ein zweites Mal im Netz,
  im selben Seitenaufruf. Bleibt nur marke-verlauf, 6 KB, aus dem die Marke
  ihren Farbverlauf nimmt; den ersetzt die Seite nicht. Der Vergleich laeuft
  ohne Dateiendung — im ersten Anlauf stand hier 'marke-verlauf' gegen
  'marke-verlauf.png', und die Marke kam ohne ihren Verlauf heraus, als
  flaches Blaugrau. Das faellt nicht auf, bis man das alte GLB daneben legt.

  KEINE FASEN AN LICHTLEISTEN. Der Fotoreal-Durchgang hat jedem Bauteil
  Bevel und Weighted Normal verpasst — richtig fuer Cycles, wo eine Kante von
  zwei Millimetern das Schluesserlicht faengt. Im Browser ist eine Wandlinie
  drei Zentimeter breit und keine zwei Pixel gross; ihre Fase ist unsichtbar
  und kostet das Zwoelffache an Punkten. Die Lichtleisten, Bodenfugen,
  Deckenfugen und Lamellen gehen deshalb ungefast in die Webfassung, alles
  Grosse — Marke, Rahmen, Portale, Podest, Waende — behaelt seine Kanten.

  FLACHE GRUNDFARBEN FUER PUTZ. Wand und Decke bekommen in Cycles eine
  grossflaechige Fleckung aus Rauschknoten — die kann ein glTF nicht
  transportieren, und ein verknuepfter Base-Color-Eingang laesst den
  Exporteur raten. Deshalb werden die beiden hier auf genau den Mittelton
  festgelegt, der in Blender gemessen wurde. Das ist kein Verlust: raum.js
  legt ueber beide ohnehin eigene Rauheits- und Normalkarten.

Laeuft im Hintergrund-Blender, nicht im offenen Fenster: das Loesen der
Bildknoten und der Modifikatoren veraendert die Datei, und das soll die
Arbeitsdatei nicht abbekommen.

    blender -b vecom-showroom_Fotoreal5.blend -P web_export.py
"""
import bpy, os

NICHT_MIT = {'Dunst', 'Softbox_L', 'Softbox_O', 'Softbox_R'}
BILD_BEHALTEN = {'marke-verlauf'}   # ohne Endung verglichen: heisst hier .png

WEB_BASIS = {                       # Mittelton statt Rauschknoten
    'M_Wand':  (0.042, 0.046, 0.055, 1.0),
    'M_Decke': (0.058, 0.061, 0.068, 1.0),
}

# ------------------------------------------------------------- Grundfarbe fest
for mname, farbe in WEB_BASIS.items():
    m = bpy.data.materials.get(mname)
    if not m or not m.node_tree:
        continue
    for n in m.node_tree.nodes:
        if n.type != 'BSDF_PRINCIPLED':
            continue
        e = n.inputs['Base Color']
        for l in list(e.links):
            m.node_tree.links.remove(l)
        e.default_value = farbe
        print('[export] %s: Grundfarbe fest auf %s' % (mname, tuple(round(v, 3) for v in farbe)))

# ---------------------------------------------------------------- Bilder loesen
geloest = []
for m in bpy.data.materials:
    if not m.use_nodes or not m.node_tree:
        continue
    for n in list(m.node_tree.nodes):
        if n.type != 'TEX_IMAGE' or not n.image:
            continue
        if os.path.splitext(n.image.name)[0] in BILD_BEHALTEN:
            continue
        geloest.append('%s/%s' % (m.name, n.image.name))
        m.node_tree.nodes.remove(n)
print('[export] %d Bildknoten geloest: %s' % (len(geloest), ', '.join(sorted(set(geloest))[:6])))

# ---------------------------------------------------------------- Fasen weg
# Nur an Bauteilen, deren Fase im Browser kein Pixel gross waere.
OHNE_FASE = ('Wandlinie_', 'Lamelle_', 'Sockelfuge_', 'Deckenfeld_',
             'Deckenlinie_', 'Seitenfuge_', 'Fuge_', 'Markenkante')
entfaste = 0
for o in bpy.data.objects:
    if o.type != 'MESH':
        continue
    if not (o.name.startswith(OHNE_FASE) or o.name.endswith('_Licht')):
        continue
    for m in list(o.modifiers):
        if m.type in ('BEVEL', 'WEIGHTED_NORMAL'):
            o.modifiers.remove(m)
            entfaste += 1
print('[export] %d Fasen-Modifikatoren entfernt' % entfaste)

# ---------------------------------------------------------------- Auswahl
for o in bpy.data.objects:
    try:
        o.select_set(False)
    except Exception:
        pass
mit = []
for o in bpy.data.objects:
    if o.type != 'MESH' or o.name in NICHT_MIT:
        continue
    o.select_set(True)
    mit.append(o.name)
bpy.context.view_layer.objects.active = bpy.data.objects[mit[0]]
print('[export] %d Meshes, draussen: %s' % (len(mit), sorted(NICHT_MIT)))

# ---------------------------------------------------------------- Export
ordner = os.path.dirname(bpy.data.filepath)
ziel = os.path.join(ordner, 'export', 'showroom.glb')
os.makedirs(os.path.dirname(ziel), exist_ok=True)
bpy.ops.export_scene.gltf(
    filepath=ziel, export_format='GLB',
    use_selection=True, export_apply=True,
    export_draco_mesh_compression_enable=False,
    export_yup=True, export_cameras=False, export_lights=False,
    export_texcoords=True, export_normals=True,
)
print('[export] showroom.glb -> %d Bytes' % os.path.getsize(ziel))
