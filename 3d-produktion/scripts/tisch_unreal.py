# -*- coding: utf-8 -*-
"""Der Tisch auf dem Weg nach Unreal.

WARUM DAS ANDERS AUSSIEHT ALS DER WEB-EXPORT

web_export liefert ein GLB, das im Browser NEU beleuchtet wird -- Material
und Licht setzt dort three.js zur Laufzeit. Hier ist es umgekehrt: Unreal
soll nichts mehr setzen muessen.

Der Grund steht in der Messung vom 15.09.2026, dreifach belegt: Die
Unreal-MCP-Schnittstelle kann ERZEUGEN, VERBINDEN und LESEN, aber keine
Werte schreiben. ObjectTools.set_properties meldet Erfolg und aendert
nichts -- auf Komponenten im Level ueberhaupt nicht, neun von neun
Lichteigenschaften stumm. Wer darauf baut, baut auf Sand.

Daraus folgt die Regel fuer diesen Export:

    ALLES, WAS EINEN WERT TRAEGT, KOMMT DURCH DIE DATEI.

Also nicht nur Geometrie und Material, sondern auch das LICHT -- und zwar
als emissive Flaechen statt als Lampen. Eine Lampe traegt ihre Helligkeit
in einer Eigenschaft, die ich in Unreal nicht setzen kann. Eine leuchtende
Flaeche traegt sie im Material, und Material kommt durch das glTF. Lumen
rechnet mit emissiver Geometrie ohnehin als Lichtquelle; fuer ein
Studiobild ist eine grosse leuchtende Flaeche ohnehin richtiger als ein
Punktstrahler.

Das ist kein Notbehelf, sondern dieselbe Bauweise wie im Fotostudio: Die
Softbox IST die Flaeche, nicht die Birne darin.

WAS BEWUSST NICHT MITKOMMT
    Kamera      -- Unreal bekommt eine CineCameraActor, die dort hingehoert
    Animation   -- es gibt keine
    Modifikator -- angewendet, sonst kommt die Fase nicht mit
"""
import bpy, bmesh, os, sys, math, json
from mathutils import Vector

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.append(P)
import tisch_varianten as tv
import tisch_konfigurator as tk

AUS = os.path.join(os.path.dirname(P), 'unreal-export', 'tisch')
# EIGENES Projekt, nicht VecomShowroom. Der Showroom soll nicht angefasst
# werden, und ein Projekt kann nur das ueberschreiben, was es selbst anlegt.
UE_IMPORT = r'C:\Users\manue\Documents\Unreal Projects\VecomTisch\Import'
SAMMLUNG = 'Tisch_Unreal'


def srgb(r, g, b):
    """Gemessener sRGB-Ton in linear -- Blender rechnet linear."""
    def k(v):
        v = v / 255.0
        return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4
    return (k(r), k(g), k(b))


# ---------------------------------------------------------------- Material
def _leuchtmaterial(name, farbe, staerke):
    """Emissives Material, das den glTF-Weg ueberlebt.

    Wichtig: Base Color schwarz und Emission traegt alles. Eine Flaeche, die
    zugleich diffus und emissiv ist, kommt in Unreal als heller Klotz an,
    weil Lumen sie doppelt zaehlt -- einmal als Leuchte, einmal als
    Reflektor. Im Studio ist die Softbox aber nur Leuchte.

    staerke > 1 wandert ueber KHR_materials_emissive_strength ins glTF. Der
    Interchange-Importer von Unreal liest die Erweiterung; ohne sie wuerde
    jede Softbox bei 1.0 abgeschnitten und das Bild bliebe flau.
    """
    m = bpy.data.materials.get(name)
    if m is None:
        m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    b = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')
    b.inputs['Base Color'].default_value = (0, 0, 0, 1)
    b.inputs['Roughness'].default_value = 1.0
    b.inputs['Metallic'].default_value = 0.0
    b.inputs['Emission Color'].default_value = (*farbe, 1.0)
    b.inputs['Emission Strength'].default_value = staerke
    m.use_fake_user = True
    return m


def _hohlkehlmaterial():
    """Der Boden. Dunkles Anthrazit, matt, minimal glaenzend.

    Erst mittelgrau gebaut, nach Probebild 04 auf Anthrazit geaendert, und
    der Grund ist lehrreich: Messing hat einen Reflexionsgrad von 0,91 im
    Rot und 0,42 im Blau. Vor einer hellgrauen Hohlkehle spiegelt es
    hellgrau -- physikalisch richtig, und trotzdem sieht es aus wie
    eloxiertes Aluminium in Beige. Messing sieht man nicht an seiner
    Farbe, sondern am UNTERSCHIED zwischen dem, was es spiegelt, und dem,
    was es verschluckt. Ohne Dunkel im Raum gibt es diesen Unterschied
    nicht.
    Das deckt sich mit der Markenwelt (dunkel, cinematic) und mit der
    Browser-Vorschau, in der genau dieselbe Korrektur noetig war. Zwei
    Wege, dieselbe Lehre.
    """
    name = 'M_UE_Hohlkehle'
    m = bpy.data.materials.get(name)
    if m is None:
        m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = next(n for n in m.node_tree.nodes if n.type == 'BSDF_PRINCIPLED')
    b.inputs['Base Color'].default_value = (*srgb(31, 33, 38), 1.0)
    b.inputs['Roughness'].default_value = 0.44
    b.inputs['Metallic'].default_value = 0.0
    b.inputs['Specular IOR Level'].default_value = 0.4
    m.use_fake_user = True
    return m


# ---------------------------------------------------------------- Geometrie
def _hohlkehle(samm, mat, breite=14.0, tiefe=11.0, hoehe=6.0, radius=2.6):
    """Boden, der ohne sichtbare Kante in die Rueckwand laeuft.

    Gebaut als Gitter aus Segmenten statt als Bogen-Modifikator: Der Bogen
    muesste angewendet werden, und angewendete Modifikatoren auf einer
    Flaeche mit ungleichen Segmentlaengen geben ungleiche Texel. Hier ist
    die Rundung von Anfang an Geometrie.
    """
    me = bpy.data.meshes.new('Hohlkehle')
    bm = bmesh.new()
    schritte = 16
    profil = []
    y = -tiefe / 2
    profil.append((y, 0.0))
    y_bogen = tiefe / 2 - radius
    profil.append((y_bogen, 0.0))
    for i in range(1, schritte + 1):
        w = (math.pi / 2) * i / schritte
        profil.append((y_bogen + radius * math.sin(w), radius * (1 - math.cos(w))))
    profil.append((y_bogen + radius, hoehe))

    reihen = []
    for y, z in profil:
        reihen.append([bm.verts.new((-breite / 2, y, z)),
                       bm.verts.new(( breite / 2, y, z))])
    for a, b in zip(reihen, reihen[1:]):
        bm.faces.new((a[0], a[1], b[1], b[0]))
    bm.normal_update()
    bm.to_mesh(me); bm.free()
    for p in me.polygons:
        p.use_smooth = True
    me.materials.append(mat)
    o = bpy.data.objects.new('Hohlkehle', me)
    samm.objects.link(o)
    return o


def _softbox(samm, name, mitte, blick, groesse, farbe, staerke):
    """Leuchtende Flaeche, ausgerichtet auf einen Punkt."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    b, h = groesse
    bmesh.ops.create_grid(bm, x_segments=1, y_segments=1, size=0.5)
    bmesh.ops.scale(bm, vec=Vector((b, h, 1.0)), verts=bm.verts)
    bm.to_mesh(me); bm.free()
    me.materials.append(_leuchtmaterial('M_UE_Licht_' + name, farbe, staerke))
    o = bpy.data.objects.new(name, me)
    o.location = mitte
    # Blickrichtung ueber eine Ausrichtungsmatrix statt ueber Handrechnerei
    richtung = (Vector(blick) - Vector(mitte)).normalized()
    o.rotation_euler = richtung.to_track_quat('Z', 'Y').to_euler()
    # Die Softbox leuchtet und spiegelt sich, ist aber selbst nicht im Bild.
    # Im Probebild 02 stand die Hauptleuchte als dunkle Scheibe oben links --
    # von hinten gesehen, wo sie nur noch schwarzer Grundton ist. Ein echtes
    # Studio loest das mit der Standortwahl; hier ist der Schalter ehrlicher,
    # weil die Lampe naeher stehen darf, als das Bildfeld erlaubt.
    # ACHTUNG fuer den Unreal-Weg: Diesen Schalter gibt es im glTF nicht.
    # Dort muessen die Flaechen tatsaechlich ausserhalb des Bildfeldes stehen.
    o.visible_camera = False
    samm.objects.link(o)
    return o


# ------------------------------------------------------------------- Aufbau
def _sammlung(szene):
    s = bpy.data.collections.get(SAMMLUNG)
    if s is None:
        s = bpy.data.collections.new(SAMMLUNG)
    if s.name not in [k.name for k in szene.collection.children]:
        szene.collection.children.link(s)
    for o in list(s.objects):
        bpy.data.objects.remove(o, do_unlink=True)
    return s


def aufbauen(laenge='mittel', gestell='wange', holz='Eiche', metall='Messing',
             szene=None):
    """Tisch plus Studio in einer eigenen Sammlung. Gibt die Objekte zurueck.

    Die Lichtsetzung ist ein Dreipunkt-Studio in echten Massen:
      Hauptlicht  2,4 x 1,8 m, links vorn, halbhoch -- die Groesse macht die
                  weiche Kante an der Wange, nicht die Staerke
      Aufheller   3,0 x 2,2 m, rechts, schwach und kuehl -- er soll die
                  Schattenseite lesbar machen, nicht ein zweites Glanzlicht
                  setzen
      Oberlicht   4,0 x 2,4 m, knapp ueber Kopfhoehe -- es zeichnet die
                  Plattenoberflaeche und den langen Reflex auf der Traverse
    Alle drei stehen ausserhalb eines 50-mm-Bildfeldes. Wer sie naeher
    heranzieht, sieht sie im Bild, und wer sie weiter wegstellt, verliert
    die weiche Kante -- der Winkel ist das, was zaehlt, nicht der Abstand.
    """
    szene = szene or bpy.context.scene
    samm = _sammlung(szene)

    teile = tv.bauen(laenge=laenge, gestell=gestell, holz=holz,
                     metall=metall, szene=szene)
    for o in teile:
        for c in list(o.users_collection):
            c.objects.unlink(o)
        samm.objects.link(o)

    # Die Platte bekommt die TEXTURFASSUNG der Maserung, nicht den Knotenbaum.
    # Cycles koennte beides rechnen; Unreal bekommt nur das, was durch glTF
    # passt. Damit rechnet das Cycles-Bild mit exakt denselben Karten wie das
    # Unreal-Bild -- sonst waere der Vergleich wertlos.
    import tisch_web as tw
    platte = next((o for o in teile if o.name.startswith('Platte')), None)
    if platte is not None:
        mat = holz_material_unreal(holz)
        platte.data.materials.clear()
        platte.data.materials.append(mat)
        tw._uv_planar(platte, tv.LAENGEN[laenge] if isinstance(laenge, str) else float(laenge),
                      tv.BREITE)

    # Hohlkehle gross genug, dass die Kamera nicht an ihrer Kante vorbeisieht.
    o = [_hohlkehle(samm, _hohlkehlmaterial(),
                    breite=22.0, tiefe=16.0, hoehe=8.0, radius=3.4)]
    ziel = (0.0, 0.0, 0.62)
    # Staerken nach Probebild 03 zurueckgenommen. Dort war das Messing
    # cremefarben statt messing -- nicht weil die Farbe falsch war, sondern
    # weil drei Flaechen zusammen so viel Licht warfen, dass jede Spiegelung
    # ins Weiss lief. Derselbe Fehler wie im Showroom, Durchgang 2, und in
    # der Browser-Vorschau. Er sieht jedes Mal anders aus und ist jedes Mal
    # dasselbe: Nicht das Material ist zu hell, das Licht ist zu viel.
    o.append(_softbox(samm, 'Licht_Haupt',  (-2.35, -1.55, 1.95), ziel,
                      (2.4, 1.8), srgb(255, 244, 228), 8.0))
    o.append(_softbox(samm, 'Licht_Fuell',  ( 2.85,  0.35, 1.35), ziel,
                      (3.0, 2.2), srgb(226, 236, 255), 1.5))
    o.append(_softbox(samm, 'Licht_Ober',   ( 0.15,  0.60, 2.85), ziel,
                      (4.0, 2.4), srgb(248, 250, 255), 3.6))
    return teile + o


# ----------------------------------------------------------------- Ausgeben
def exportieren(laenge='mittel', gestell='wange', holz='Eiche',
                metall='Messing', szene=None, nach_unreal=True):
    """Baut die Variante auf und schreibt sie als GLB.

    KEIN Draco. Der Web-Export laesst es aus Ruecksicht auf den blanken
    GLTFLoader weg; hier aus einem anderen Grund: Der Interchange-Importer
    von Unreal soll nichts auspacken muessen, und 1-2 MB sind auf der
    Festplatte egal. Draco kostet hier nur Fehlerquellen.
    """
    szene = szene or bpy.context.scene
    objekte = aufbauen(laenge, gestell, holz, metall, szene)
    os.makedirs(AUS, exist_ok=True)

    name = 'tisch-%s-%s-%s-%s' % (laenge, gestell, holz.lower(), metall.lower())
    pfad = os.path.join(AUS, name + '.glb')

    # TRANSFORMATION IN DIE NETZDATEN EINRECHNEN
    # Der Importer von Unreal legt aus einem GLB Mesh-ASSETS an, keine Szene.
    # Die Knotentransformationen bleiben dabei auf der Strecke; jedes Netz
    # saesse in Unreal im Ursprung und der Tisch faellt auseinander. Wer die
    # Transformation vorher in die Netzdaten rechnet, bekommt Teile, die
    # einzeln an ihrem Platz stehen -- und braucht in Unreal keine einzige
    # Positionsangabe, also auch keine Fehlerquelle beim Umrechnen zwischen
    # zwei Koordinatensystemen.
    # bpy.ops.object.transform_apply braucht einen Kontext, den die MCP-
    # Bruecke nicht sicher hat. me.transform() braucht keinen.
    for o in objekte:
        if o.type != 'MESH':
            continue
        o.data.transform(o.matrix_world)
        o.matrix_world.identity()
    bpy.context.view_layer.update()

    # Nicht bpy.ops.object.select_all: Der Operator prueft einen Kontext, den
    # es beim Aufruf ueber die MCP-Bruecke nicht immer gibt ("poll() failed").
    # select_set am Objekt braucht keinen Kontext und tut dasselbe.
    for o in bpy.context.view_layer.objects:
        if o is not None:
            o.select_set(False)
    for o in objekte:
        o.select_set(True)
    bpy.context.view_layer.objects.active = objekte[0]

    bpy.ops.export_scene.gltf(
        filepath=pfad,
        export_format='GLB',
        use_selection=True,
        export_apply=True,
        export_image_format='AUTO',   # anders als im Web: Unreal SOLL die
                                      # Texturen mitbekommen, nicht die Seite
        export_texcoords=True,
        export_normals=True,
        export_tangents=True,
        export_draco_mesh_compression_enable=False,
        export_yup=True,
        export_cameras=False,
        export_lights=False,          # Licht ist hier Geometrie, siehe oben
    )

    bericht = dict(datei=os.path.basename(pfad),
                   mb=round(os.path.getsize(pfad) / 1024 / 1024, 2),
                   objekte=len(objekte),
                   variante=dict(laenge=laenge, gestell=gestell,
                                 holz=holz, metall=metall))

    if nach_unreal and os.path.isdir(UE_IMPORT):
        import shutil
        ziel = os.path.join(UE_IMPORT, os.path.basename(pfad))
        shutil.copy2(pfad, ziel)
        bericht['unreal'] = ziel
    elif nach_unreal:
        bericht['unreal'] = 'Ordner fehlt: ' + UE_IMPORT
    return bericht


# =========================================================================
#  DIESELBE SZENE, ZWEI AUSGAENGE
#
#  Was oben aufgebaut wird, ist nicht "die Unreal-Szene" -- es ist DAS
#  STUDIO. Cycles kann es direkt rechnen, Unreal bekommt es als GLB. Das
#  ist Absicht: Wenn beide Wege dieselbe Buehne benutzen, ist ein
#  Unterschied im Bild ein Unterschied der Engine und nicht der Szene.
#  Nur so laesst sich ueberhaupt beantworten, was Unreal hier besser kann.
#
#  EIN UNTERSCHIED BLEIBT UND MUSS BENANNT WERDEN: Die Maserung ist ein
#  Knotenbaum. Cycles rechnet ihn. Das glTF kann ihn nicht mitnehmen -- es
#  kennt nur Texturen. Der Unreal-Weg braucht deshalb zwingend die
#  gebackenen Karten, der Cycles-Weg nicht. Das erste Export-GLB war
#  0,02 MB gross; das war kein Erfolg, sondern der Beleg dafuer.
# =========================================================================

def kamera(szene=None, brennweite=0.058, aus=(2.30, -2.75, 1.22),
           ziel=(0.0, 0.0, 0.44)):
    """Produktkamera in echten Massen.

    58 mm, nicht 35: Bei kurzer Brennweite laufen die Tischkanten stark
    zusammen und die Platte wirkt trapezfoermig -- das ist der Blick, der
    Moebelbilder nach Katalog von 1998 aussehen laesst. Zwischen 55 und
    85 mm bleiben die Kanten ruhig. Weiter als 85 mm ginge auch, dann
    steht die Kamera aber so weit weg, dass die Softboxen ins Bild
    wandern.

    Blende f/5,6: Bei f/2,8 waere die hintere Tischkante unscharf, und
    Unschaerfe an einem Produkt, das der Kunde beurteilen soll, ist kein
    Stilmittel, sondern ein Mangel.
    """
    szene = szene or bpy.context.scene
    dat = bpy.data.cameras.get('Kam_Tisch') or bpy.data.cameras.new('Kam_Tisch')
    dat.sensor_width = 36.0
    dat.lens = brennweite * 1000.0
    dat.dof.use_dof = True
    dat.dof.aperture_fstop = 5.6

    o = bpy.data.objects.get('Kam_Tisch')
    if o is None:
        o = bpy.data.objects.new('Kam_Tisch', dat)
    if o.name not in [x.name for x in szene.collection.all_objects]:
        bpy.data.collections[SAMMLUNG].objects.link(o)
    o.location = aus
    richtung = Vector(ziel) - Vector(aus)
    o.rotation_euler = richtung.to_track_quat('-Z', 'Y').to_euler()
    dat.dof.focus_distance = richtung.length
    szene.camera = o
    return o


def rendern(szene=None, breite=1600, hoehe=1000, samples=256, datei=None):
    """Cycles auf der Grafikkarte. Gibt Pfad und gemessene Zeit zurueck.

    OPTIX wird hier gesetzt und nicht vorausgesetzt: Am 15.09.2026 stand
    compute_device_type auf NONE, und Cycles rechnete trotz RTX 5070 auf
    der CPU -- Faktor 9,4 verschenkt, ohne dass irgendwo eine Warnung
    stand. Einmal messen genuegt uebrigens nicht: Der erste OptiX-Lauf
    uebersetzt die Kernel und ist deshalb langsam.
    """
    import time
    szene = szene or bpy.context.scene
    szene.render.engine = 'CYCLES'

    vor = bpy.context.preferences.addons['cycles'].preferences
    vor.compute_device_type = 'OPTIX'
    vor.get_devices()
    for g in vor.devices:
        g.use = (g.type == 'OPTIX')
    szene.cycles.device = 'GPU'

    szene.cycles.samples = samples
    szene.cycles.use_adaptive_sampling = True
    szene.cycles.adaptive_threshold = 0.008
    szene.cycles.use_denoising = True
    szene.cycles.denoiser = 'OPENIMAGEDENOISE'
    szene.cycles.denoising_input_passes = 'RGB_ALBEDO_NORMAL'
    szene.cycles.denoising_prefilter = 'ACCURATE'
    # blur_glossy 1.0 verwischt glaenzende Strahlen absichtlich und kostet
    # genau die feinen Glanzdetails, um die es hier geht.
    szene.cycles.blur_glossy = 0.4
    szene.cycles.glossy_bounces = 8
    szene.cycles.transmission_bounces = 8

    szene.render.resolution_x = breite
    szene.render.resolution_y = hoehe
    szene.render.resolution_percentage = 100
    szene.render.film_transparent = False
    szene.view_settings.view_transform = 'AgX'
    szene.view_settings.look = 'AgX - Medium High Contrast'

    ordner = os.path.join(os.path.dirname(P), 'render', 'tisch')
    os.makedirs(ordner, exist_ok=True)
    pfad = os.path.join(ordner, datei or 'tisch-studio.png')
    szene.render.filepath = pfad
    szene.render.image_settings.file_format = 'PNG'

    t0 = time.time()
    bpy.ops.render.render(write_still=True)
    return dict(datei=pfad, sekunden=round(time.time() - t0, 1),
                geraet=[g.name for g in vor.devices if g.use],
                samples=samples, px=[breite, hoehe])


def nur_studio(szene=None):
    """Alles ausser dem Studio aus der Ansichtsebene nehmen.

    Die Blend-Datei traegt gewachsene Sammlungen -- Tisch, Tisch_Vergleich,
    Varianten_Tische, Tisch_Export. Die stehen alle am selben Ort im Raum.
    Das erste Probebild zeigte deshalb einen Wangentisch UND die Beine
    eines Vierbeintisches ineinander, und das Metall war schwarz statt
    messing: sichtbar war der Vergleichsaufbau, nicht der neue.

    Ausblenden reicht nicht -- Cycles rechnet versteckte Objekte nicht mit,
    aber `hide_viewport` und `hide_render` muessten je Objekt gesetzt
    werden. Die Ausschlussmarke an der Sammlung in der Ansichtsebene nimmt
    den ganzen Zweig auf einmal heraus.
    """
    szene = szene or bpy.context.scene
    ebene = bpy.context.view_layer
    behalten = {SAMMLUNG}
    aus = []

    def gehen(schicht):
        for k in schicht.children:
            if k.name in behalten:
                k.exclude = False
            else:
                k.exclude = True
                aus.append(k.name)
            gehen(k)

    gehen(ebene.layer_collection)
    # Objekte, die direkt in der Szenenwurzel haengen, hat keine Sammlung.
    for o in list(szene.collection.objects):
        if o.name not in [x.name for x in bpy.data.collections[SAMMLUNG].objects]:
            o.hide_render = True
            o.hide_viewport = True
            aus.append(o.name)
    return aus


# =========================================================================
#  EINE QUELLE FUER DIE METALLE
#
#  Befund vom 16.09.2026, im Probebild 03 aufgefallen: Das Messing im
#  Cycles-Bild war ein ANDERES Messing als im Browser.
#      Blender  M_Metall_Messing  srgb(181,141,72)  = linear 0,461/0,267/0,069
#      Manifest tisch-manifest     linear 0,910/0,778/0,423
#  Der zweite Satz ist der gemessene Reflexionsgrad von Messing bei
#  senkrechtem Einfall; der erste war geschaetzt. Bei einem Konfigurator
#  ist das kein Schoenheitsfehler: Der Kunde soll im Standbild und im
#  Live-Modell dasselbe Produkt sehen. Zwei Wahrheiten sind eine zu viel.
#
#  Deshalb liest Blender die Werte jetzt AUS DEM MANIFEST. Nicht umgekehrt
#  -- das Manifest ist die Datei, die ausgeliefert wird, und was
#  ausgeliefert wird, ist die Wahrheit.
# =========================================================================

MANIFEST = os.path.join(os.path.dirname(P), 'web-export', 'tisch-manifest.json')


def metalle_aus_manifest(szene=None):
    """Schreibt M_Metall_* aus dem Web-Manifest neu. Gibt einen Bericht.

    Zur Rauheitsstreuung: Die alte Fassung streute ueber ein Rauschen der
    Groesse 70 um plus/minus 0,05. Im Bild wurde daraus eine gehaemmerte
    Oberflaeche -- bei 4,7 m Abstand und 1200 px deckt ein Pixel 2,4 mm ab,
    das Rauschen liegt bei 14 mm, also sechs Pixel je Flecken. Genau die
    Falle vom 13.09.2026, nur an anderer Stelle: Ein Muster, das man sieht,
    ist kein Mikrodetail mehr, sondern eine Struktur.
    Gebuerstetes Metall ist NICHT gefleckt. Es ist gleichmaessig und hat
    eine Richtung. Die Richtung macht die Anisotropie, die Streuung darf
    nur so gross sein, dass die Flaeche nicht mathematisch glatt wirkt --
    hier plus/minus 0,012 bei Groesse 420, also unter der Pixelgroesse.
    """
    szene = szene or bpy.context.scene
    man = json.load(open(MANIFEST, encoding='utf-8'))
    bericht = {}

    for name, w in man['material']['metall'].items():
        m = bpy.data.materials.get('M_Metall_' + name)
        if m is None:
            m = bpy.data.materials.new('M_Metall_' + name)
            m.use_nodes = True
        nt = m.node_tree
        for n in list(nt.nodes):
            if n.type not in ('BSDF_PRINCIPLED', 'OUTPUT_MATERIAL'):
                nt.nodes.remove(n)
        b = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')
        f = w['grundfarbe']
        b.inputs['Base Color'].default_value = (f[0], f[1], f[2], 1.0)
        b.inputs['Metallic'].default_value = float(w['metallisch'])
        b.inputs['Roughness'].default_value = float(w['rauheit'])
        b.inputs['Anisotropic'].default_value = float(w.get('anisotropie', 0.0))
        b.inputs['Anisotropic Rotation'].default_value = 0.0
        b.inputs['Coat Weight'].default_value = float(w.get('klarlack', 0.0))
        if w.get('klarlack'):
            b.inputs['Coat Roughness'].default_value = 0.16

        koord = nt.nodes.new('ShaderNodeTexCoord')
        streu = nt.nodes.new('ShaderNodeTexNoise')
        streu.inputs['Scale'].default_value = 420.0
        streu.inputs['Detail'].default_value = 2.0
        rau = nt.nodes.new('ShaderNodeMapRange')
        rau.inputs['From Min'].default_value = 0.35
        rau.inputs['From Max'].default_value = 0.65
        rau.inputs['To Min'].default_value = float(w['rauheit']) - 0.012
        rau.inputs['To Max'].default_value = float(w['rauheit']) + 0.012
        nt.links.new(koord.outputs['Object'], streu.inputs['Vector'])
        nt.links.new(streu.outputs['Fac'], rau.inputs['Value'])
        nt.links.new(rau.outputs['Result'], b.inputs['Roughness'])
        m.use_fake_user = True
        bericht[name] = dict(farbe=[round(x, 3) for x in f],
                             metallisch=w['metallisch'],
                             rauheit=w['rauheit'],
                             anisotropie=w.get('anisotropie', 0.0))
    return bericht


# =========================================================================
#  MASERUNG FUER UNREAL BACKEN
#
#  Der erste Import hat es schwarz auf weiss gezeigt: M_Holz_Eiche kam in
#  Unreal OHNE Grundfarbe an. Messing, Hohlkehle und die drei Softboxen
#  kamen mit allen Werten durch -- das Holz nicht, weil es als einziges
#  kein Wert ist, sondern ein Knotenbaum. glTF kennt nur Texturen.
#
#  Hoehere Aufloesung als im Web, und zwar gerechnet statt geraten: Die
#  laengste Platte ist 2,40 m. Bei 4096 px ist ein Texel 0,586 mm, die
#  Ringe liegen bei 5,9 mm -- also zehn Texel je Ring. Im Web sind es
#  fuenf, was fuer eine Ansicht auf dem Handy reicht; ein Unreal-Bild soll
#  die Grossaufnahme aushalten.
#
#  Dazu eine Normalkarte. Die ist NICHT erfunden: Eiche ist ringporig, das
#  dunkle Fruehholzband besteht aus offenen Poren von 100 bis 200 Mikrometer
#  und liegt nach dem Schleifen tiefer als das dichte Spaetholz. Wo die
#  Grundfarbe dunkel ist, ist die Oberflaeche also wirklich tiefer -- der
#  Sobel ueber die Helligkeit trifft die Physik, nicht nur den Eindruck.
# =========================================================================

UE_TEX_B, UE_TEX_H = 4096, 1632


def _bild_speichern(bild, pfad, is_data=False):
    bild.filepath_raw = pfad
    bild.file_format = 'PNG'
    bild.save()
    return dict(datei=os.path.basename(pfad),
                kb=round(os.path.getsize(pfad) / 1024, 1))


def backen_unreal(hoelzer=None, szene=None):
    """Baeckt je Holzart Grundfarbe, Rauheit und Normale nach unreal-export.

    Schreibt NICHT in web-export. Die beiden Ausgaenge haben verschiedene
    Aufloesungen und verschiedene Zwecke; ein gemeinsamer Ordner waere die
    sichere Art, irgendwann die falsche Datei hochzuladen.
    """
    import tisch_web as tw
    szene = szene or bpy.context.scene
    hoelzer = hoelzer or list(tk.HOELZER)
    ordner = os.path.join(AUS, 'texturen')
    os.makedirs(ordner, exist_ok=True)
    tw._bake_bereit(szene)
    szene.cycles.samples = 32

    # Hilfsplatte in Maximallaenge, ohne Fase -- die Fase stoert die planare UV.
    teile = tv.bauen(laenge='gross', gestell='wange', holz=hoelzer[0],
                     metall='Schwarzstahl', szene=szene)
    platte = next(o for o in teile if o.name.startswith('Platte'))
    for o in teile:
        if o is not platte:
            bpy.data.objects.remove(o, do_unlink=True)
    # In eine Sammlung haengen, die in der Ansichtsebene DRIN ist. nur_studio()
    # nimmt 'Tisch' heraus, und ein Objekt ausserhalb der Ansichtsebene laesst
    # sich nicht auswaehlen -- ohne Auswahl kein Backen.
    samm = _sammlung(szene)
    for c in list(platte.users_collection):
        c.objects.unlink(platte)
    samm.objects.link(platte)
    for m in list(platte.modifiers):
        platte.modifiers.remove(m)
    tw._uv_planar(platte, tw.LAENGE_MAX, tv.BREITE)

    bericht = {}
    for holz in hoelzer:
        mat = bpy.data.materials['M_Holz_' + holz]
        platte.data.materials.clear()
        platte.data.materials.append(mat)
        nt = mat.node_tree
        bsdf = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')
        bericht[holz] = {}

        for art in ('grundfarbe', 'rauheit'):
            res = (UE_TEX_B, UE_TEX_H) if art == 'grundfarbe' else (UE_TEX_B // 2, UE_TEX_H // 2)
            name = 'ue_%s_%s' % (holz, art)
            alt = bpy.data.images.get(name)
            if alt:
                bpy.data.images.remove(alt)
            bild = bpy.data.images.new(name, res[0], res[1], alpha=False,
                                       float_buffer=False, is_data=(art == 'rauheit'))
            ziel = nt.nodes.new('ShaderNodeTexImage')
            ziel.image = bild
            ziel.select = True
            nt.nodes.active = ziel

            zurueck = None
            if art == 'rauheit':
                quelle = next((l.from_socket for l in nt.links
                               if l.to_node.name == bsdf.name
                               and l.to_socket.name == 'Roughness'), None)
                if quelle is None:
                    bpy.data.images.remove(bild); nt.nodes.remove(ziel); continue
                em = nt.nodes.new('ShaderNodeEmission')
                nt.links.new(quelle, em.inputs['Color'])
                aus_n = next(n for n in nt.nodes if n.type == 'OUTPUT_MATERIAL')
                zurueck = next(l.from_socket for l in nt.links if l.to_node.name == aus_n.name)
                nt.links.new(em.outputs['Emission'], aus_n.inputs['Surface'])
                szene.cycles.bake_type = 'EMIT'
            else:
                szene.cycles.bake_type = 'DIFFUSE'

            # Nach bpy.data.objects.remove() kann view_layer.objects fuer einen
            # Moment ungueltige Eintraege fuehren -- deshalb der Wachposten.
            for x in list(bpy.context.view_layer.objects):
                if x is not None:
                    x.select_set(False)
            platte.select_set(True)
            bpy.context.view_layer.objects.active = platte
            bpy.ops.object.bake(type=szene.cycles.bake_type)

            bericht[holz][art] = _bild_speichern(
                bild, os.path.join(ordner, 'ue-holz-%s-%s.png' % (holz.lower(), art)))

            if zurueck is not None:
                nt.links.new(zurueck, aus_n.inputs['Surface'])
                nt.nodes.remove(em)
                szene.cycles.bake_type = 'DIFFUSE'
            nt.nodes.remove(ziel)

    bpy.data.objects.remove(platte, do_unlink=True)
    return bericht


def normalkarte(holz, staerke=1.6):
    """Normalkarte aus der Grundfarbe ableiten -- und warum das erlaubt ist.

    Aus einer Farbe eine Hoehe zu machen ist normalerweise ein Kunstgriff,
    der auffliegt. Bei ringporigem Holz ist es keiner: Das dunkle Band ist
    dunkel, WEIL es aus offenen Poren von 100 bis 200 Mikrometer besteht,
    und dieselben Poren liegen nach dem Schleifen tiefer als das dichte
    Spaetholz. Dunkel und tief fallen hier physikalisch zusammen.

    Was NICHT mitkommt: die Markstrahlen. Die sind quer zu den Ringen und
    fast nur ueber den Glanz sichtbar, nicht ueber die Farbe -- die bleiben
    offen und stehen so in der Liste.
    """
    import numpy as np
    ordner = os.path.join(AUS, 'texturen')
    quelle = os.path.join(ordner, 'ue-holz-%s-grundfarbe.png' % holz.lower())
    bild = bpy.data.images.load(quelle, check_existing=True)
    b, h = bild.size
    px = np.array(bild.pixels[:], dtype=np.float32).reshape(h, b, 4)
    # Helligkeit nach Rec.709, nicht der Mittelwert der Kanaele
    hoehe = 0.2126 * px[:, :, 0] + 0.7152 * px[:, :, 1] + 0.0722 * px[:, :, 2]

    # Sobel. Die Texelbreite geht in die Steigung ein, sonst haengt die
    # Staerke der Karte an der Aufloesung statt an der Oberflaeche.
    gx = np.zeros_like(hoehe); gy = np.zeros_like(hoehe)
    gx[:, 1:-1] = (hoehe[:, 2:] - hoehe[:, :-2]) * 0.5
    gy[1:-1, :] = (hoehe[2:, :] - hoehe[:-2, :]) * 0.5
    nx = -gx * staerke
    ny = -gy * staerke
    nz = np.ones_like(hoehe)
    laenge = np.sqrt(nx * nx + ny * ny + nz * nz)
    aus = np.empty((h, b, 4), dtype=np.float32)
    aus[:, :, 0] = nx / laenge * 0.5 + 0.5
    aus[:, :, 1] = ny / laenge * 0.5 + 0.5
    aus[:, :, 2] = nz / laenge * 0.5 + 0.5
    aus[:, :, 3] = 1.0

    name = 'ue_%s_normale' % holz
    alt = bpy.data.images.get(name)
    if alt:
        bpy.data.images.remove(alt)
    neu = bpy.data.images.new(name, b, h, alpha=False, float_buffer=False, is_data=True)
    neu.pixels = aus.reshape(-1).tolist()
    pfad = os.path.join(ordner, 'ue-holz-%s-normale.png' % holz.lower())
    return _bild_speichern(neu, pfad)


def holz_material_unreal(holz):
    """Texturfassung der Maserung -- das, was durch glTF passt."""
    ordner = os.path.join(AUS, 'texturen')
    name = 'M_UE_Holz_' + holz
    m = bpy.data.materials.get(name)
    if m is None:
        m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    for n in list(nt.nodes):
        if n.type not in ('BSDF_PRINCIPLED', 'OUTPUT_MATERIAL'):
            nt.nodes.remove(n)
    b = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')

    def karte(art, farbraum):
        p = os.path.join(ordner, 'ue-holz-%s-%s.png' % (holz.lower(), art))
        if not os.path.exists(p):
            return None
        bild = bpy.data.images.load(p, check_existing=True)
        bild.colorspace_settings.name = farbraum
        k = nt.nodes.new('ShaderNodeTexImage')
        k.image = bild
        return k

    grund = karte('grundfarbe', 'sRGB')
    if grund:
        nt.links.new(grund.outputs['Color'], b.inputs['Base Color'])
    rau = karte('rauheit', 'Non-Color')
    if rau:
        nt.links.new(rau.outputs['Color'], b.inputs['Roughness'])
    nor = karte('normale', 'Non-Color')
    if nor:
        abb = nt.nodes.new('ShaderNodeNormalMap')
        abb.inputs['Strength'].default_value = 0.85
        nt.links.new(nor.outputs['Color'], abb.inputs['Color'])
        nt.links.new(abb.outputs['Normal'], b.inputs['Normal'])

    b.inputs['Metallic'].default_value = 0.0
    b.inputs['Coat Weight'].default_value = 0.42
    b.inputs['Coat Roughness'].default_value = 0.16
    b.inputs['IOR'].default_value = 1.51
    m.use_fake_user = True
    return m


# =========================================================================
#  EIN NETZ STATT ELF -- der Neuanfang vom 16.09.2026
#
#  Der erste Weg nach Unreal ging ueber elf einzelne Teile und ist daran
#  gescheitert, dass der Interchange-Importer das Pivot JEDES Netzes auf
#  dessen eigene Boxmitte setzt. Die Lage wandert in den Szenenknoten, und
#  wer nur Assets importiert, wirft ihn weg. Gemessenes Ergebnis:
#  Huellbox-Mitte (0, 0, 0) bei 200 x 95 x 71 cm -- alle elf Teile lagen
#  uebereinander im Ursprung. Kein Tisch, ein Klumpen.
#
#  Drei Versuche dagegen haben nichts gebracht: Transformationen in die
#  Netzdaten einrechnen (der Importer nimmt sie wieder heraus), Actors von
#  Hand stellen (dieselbe Pivot-Frage, nur spaeter), import_scene (gleiches
#  Bild). Gegen einen Importer anzubauen ist immer der falsche Weg.
#
#  Der richtige ist, ihm das Problem wegzunehmen: EIN verschmolzenes Netz
#  mit mehreren Materialschaechten. Dann gibt es kein Pivot, keine
#  Transformation und keine Achsenfrage -- die Geometrie ist von sich aus
#  zusammengebaut, und der Importer kann sie nur noch als Ganzes drehen.
#
#  Fuer den Unreal-Weg ist das ohnehin die richtige Form: Dort wird je
#  Variante EIN Bild gerechnet, nichts zur Laufzeit umgeschaltet. Das
#  Umschalten bleibt im Browser-Konfigurator, wo es hingehoert.
# =========================================================================

def verschmelzen(objekte, name='Tisch_Studio', szene=None):
    """Alle Objekte zu einem Netz mit mehreren Materialschaechten.

    Kein bpy.ops.object.join: Der Operator braucht einen Kontext mit Auswahl
    und aktivem Objekt, den die MCP-Bruecke nicht sicher hat, und er haengt
    an der Reihenfolge der Auswahl. bmesh braucht beides nicht.

    Die Modifikatoren werden ueber den ausgewerteten Graphen mitgenommen --
    ohne das fehlen Fase und gewichtete Normalen, und der Tisch bekommt die
    scharfen Kanten, die ihn nach CAD aussehen lassen.
    """
    szene = szene or bpy.context.scene
    graph = bpy.context.evaluated_depsgraph_get()
    gesamt = bmesh.new()
    mats = []

    for o in objekte:
        if o.type != 'MESH' or not o.data.materials:
            continue
        mat = o.data.materials[0]
        if mat.name not in [m.name for m in mats]:
            mats.append(mat)
        schacht = [m.name for m in mats].index(mat.name)

        ausgewertet = o.evaluated_get(graph)
        me = bpy.data.meshes.new_from_object(ausgewertet)
        me.transform(o.matrix_world)
        # Jedes Quellobjekt traegt genau EIN Material. Also bekommt jede
        # Flaeche den globalen Schachtindex -- ohne das zeigen alle Flaechen
        # auf Schacht 0 und der ganze Tisch waere aus einem Stueck Holz.
        for p in me.polygons:
            p.material_index = schacht
        if not me.uv_layers:
            me.uv_layers.new(name='UVMap')
        gesamt.from_mesh(me)
        bpy.data.meshes.remove(me)

    ziel = bpy.data.meshes.new(name)
    gesamt.to_mesh(ziel)
    gesamt.free()
    for m in mats:
        ziel.materials.append(m)
    for p in ziel.polygons:
        p.use_smooth = True

    alt = bpy.data.objects.get(name)
    if alt:
        bpy.data.objects.remove(alt, do_unlink=True)
    neu = bpy.data.objects.new(name, ziel)
    bpy.data.collections[SAMMLUNG].objects.link(neu)
    return neu, [m.name for m in mats]


def exportieren_eins(laenge='mittel', gestell='wange', holz='Eiche',
                     metall='Messing', szene=None, mit_leuchten=False):
    """Der Weg nach Unreal ab 16.09.2026: aufbauen, verschmelzen, ausgeben.

    mit_leuchten=False ist die Vorgabe, und das ist eine Entscheidung, keine
    Bequemlichkeit: Die drei leuchtenden Flaechen waren ein Notbehelf aus
    der Zeit, in der die Unreal-MCP-Schnittstelle keine Werte schreiben
    konnte -- Licht musste als Material durch die Datei geschmuggelt werden.
    Ueber Unreal-Python gibt es diese Grenze nicht. Dort sind es jetzt echte
    Rechteckleuchten, und das ist in jeder Hinsicht besser:

      - Sie leuchten AUCH im Editorfenster. Leuchtende Flaechen tun das im
        Echtzeitrenderer kaum; das Level sah beim Oeffnen schwarz aus,
        obwohl der Pfadverfolger daraus ein fertiges Bild rechnete.
      - Ihre Staerke steht in Lumen statt in einer Materialzahl.
      - Sie spiegeln sich trotzdem im Messing, wie eine echte Softbox.
    """
    szene = szene or bpy.context.scene
    teile = aufbauen(laenge, gestell, holz, metall, szene)
    if not mit_leuchten:
        fuer_export = [o for o in teile if not o.name.startswith('Licht_')]
    else:
        fuer_export = teile
    eins, schaechte = verschmelzen(fuer_export, szene=szene)

    # Die Quellobjekte haben ihren Zweck erfuellt; im Export soll genau ein
    # Netz liegen, sonst waere die ganze Uebung umsonst.
    for o in teile:
        bpy.data.objects.remove(o, do_unlink=True)

    os.makedirs(AUS, exist_ok=True)
    name = 'studio-%s-%s-%s-%s' % (laenge, gestell, holz.lower(), metall.lower())
    pfad = os.path.join(AUS, name + '.glb')

    for o in bpy.context.view_layer.objects:
        if o is not None:
            o.select_set(False)
    eins.select_set(True)
    bpy.context.view_layer.objects.active = eins

    bpy.ops.export_scene.gltf(
        filepath=pfad,
        export_format='GLB',
        use_selection=True,
        export_apply=False,          # Modifikatoren stecken schon im Netz
        export_image_format='AUTO',
        export_texcoords=True,
        export_normals=True,
        export_tangents=True,
        export_draco_mesh_compression_enable=False,
        export_yup=True,
        export_cameras=False,
        export_lights=False,
    )

    ecken = [eins.matrix_world @ Vector(k) for k in eins.bound_box]
    return dict(datei=os.path.basename(pfad),
                mb=round(os.path.getsize(pfad) / 1024 / 1024, 2),
                schaechte=schaechte,
                flaechen=len(eins.data.polygons),
                mass_m=[round(max(p[i] for p in ecken) - min(p[i] for p in ecken), 3)
                        for i in range(3)])


def alle_exportieren(szene=None, nur=None):
    """Alle 72 Varianten als je ein verschmolzenes GLB.

    Der Dateiname traegt die Artikelnummer, nicht die Zutatenliste -- so
    heisst das gerechnete Bild spaeter genauso wie der Katalogeintrag, und
    niemand muss beim Zuordnen raten.
    """
    import time
    szene = szene or bpy.context.scene
    os.makedirs(AUS, exist_ok=True)
    liste = nur or tk.alle()
    bericht = {'anzahl': len(liste), 'dateien': [], 'fehler': []}
    t0 = time.time()

    for i, v in enumerate(liste, 1):
        artikel = tk.artikelnummer(v)
        pfad = os.path.join(AUS, artikel + '.glb')
        try:
            teile = aufbauen(v['laenge'], v['gestell'], v['holz'], v['metall'], szene)
            ohne_licht = [o for o in teile if not o.name.startswith('Licht_')]
            eins, _ = verschmelzen(ohne_licht, name='T_' + artikel, szene=szene)
            for o in teile:
                bpy.data.objects.remove(o, do_unlink=True)

            for o in bpy.context.view_layer.objects:
                if o is not None:
                    o.select_set(False)
            eins.select_set(True)
            bpy.context.view_layer.objects.active = eins
            bpy.ops.export_scene.gltf(
                filepath=pfad, export_format='GLB', use_selection=True,
                export_apply=False, export_image_format='AUTO',
                export_texcoords=True, export_normals=True, export_tangents=True,
                export_draco_mesh_compression_enable=False, export_yup=True,
                export_cameras=False, export_lights=False)
            bpy.data.objects.remove(eins, do_unlink=True)
            bericht['dateien'].append(dict(artikel=artikel,
                                           mb=round(os.path.getsize(pfad) / 1048576, 2)))
        except Exception as e:
            bericht['fehler'].append(dict(artikel=artikel, grund=str(e)))
        if i % 12 == 0:
            print('[TISCH] %d/%d nach %.0f s' % (i, len(liste), time.time() - t0))

    bericht['sekunden'] = round(time.time() - t0, 1)
    bericht['mb_gesamt'] = round(sum(d['mb'] for d in bericht['dateien']), 1)
    with open(os.path.join(AUS, 'varianten.json'), 'w', encoding='utf-8') as f:
        json.dump(bericht, f, indent=2, ensure_ascii=False)
    return bericht


# =========================================================================
#  WEBP FUERS WEB
#
#  Die gerechneten PNG sind 1,2 MB je Bild. Fuer einen Konfigurator, der
#  bei jedem Klick ein anderes Bild zeigt, ist das unbrauchbar -- selbst
#  auf einer guten Leitung sieht der Besucher dann das Laden statt den
#  Tisch.
#
#  FALLE, die hier lauert: Blender rechnet beim Speichern die
#  Ansichtstransformation. Die PNG sind aber schon fertig entwickelt.
#  Wer sie mit AgX aktiv wieder speichert, entwickelt sie ein zweites Mal
#  und bekommt flaue, ausgewaschene Bilder -- und sucht den Fehler dann in
#  der Kompression. Deshalb 'Standard': durchreichen, nicht entwickeln.
# =========================================================================

def webp_serie(quelle=None, guete=82, klein=480, guete_klein=78, beschnitt_oben=0):
    """Wandelt die gerechneten PNG in WebP -- gross und als Vorschau.

    beschnitt_oben schneidet Zeilen oben ab. Grund fuer die Plattenansicht:
    Die Kamera steht bei allen 72 Varianten gleich -- sonst vergleicht man
    Bildausschnitte statt Hoelzer -- und bei den kurzen Tischen bleibt
    dadurch oben ein Drittel Schwarz. Gemessen faengt der Inhalt bei allen
    zwischen Zeile 262 und 315 an; 250 laesst ueberall Luft.
    """
    import time
    import numpy as np
    quelle = quelle or os.path.join(os.path.dirname(P), 'render', 'tisch-serie')
    gross_ordner = os.path.join(quelle, 'web')
    klein_ordner = os.path.join(quelle, 'web-klein')
    os.makedirs(gross_ordner, exist_ok=True)
    os.makedirs(klein_ordner, exist_ok=True)

    # Eigene Szene, damit die Einstellungen der Arbeitsszene unberuehrt
    # bleiben. Eine halb umgestellte Renderszene ist der Fehler, den man
    # erst drei Bilder spaeter bemerkt.
    hilfs = bpy.data.scenes.get('WebP_Hilfe') or bpy.data.scenes.new('WebP_Hilfe')
    hilfs.view_settings.view_transform = 'Standard'
    hilfs.view_settings.look = 'None'
    hilfs.render.image_settings.file_format = 'WEBP'
    hilfs.render.image_settings.color_mode = 'RGB'

    dateien = sorted(f for f in os.listdir(quelle) if f.lower().endswith('.png'))
    bericht = {'anzahl': len(dateien), 'gross_kb': 0.0, 'klein_kb': 0.0, 'fehler': []}
    t0 = time.time()

    for name in dateien:
        stamm = os.path.splitext(name)[0]
        try:
            bild = bpy.data.images.load(os.path.join(quelle, name), check_existing=False)
            bild.colorspace_settings.name = 'sRGB'

            if beschnitt_oben > 0:
                # Blender kann Bilder skalieren, aber nicht beschneiden.
                # Also die Pixel selbst umkopieren. ACHTUNG: Blenders
                # Pixelfeld faengt UNTEN an -- "oben abschneiden" heisst
                # hier, die letzten Zeilen wegzulassen.
                b, h = bild.size
                px = np.array(bild.pixels[:], dtype=np.float32).reshape(h, b, 4)
                neu_h = h - beschnitt_oben
                px = px[:neu_h]
                zuschnitt = bpy.data.images.new(name + '_schnitt', b, neu_h,
                                                alpha=False, float_buffer=False)
                zuschnitt.colorspace_settings.name = 'sRGB'
                zuschnitt.pixels = px.reshape(-1).tolist()
                bpy.data.images.remove(bild)
                bild = zuschnitt

            hilfs.render.image_settings.quality = guete
            zg = os.path.join(gross_ordner, stamm + '.webp')
            bild.save_render(filepath=zg, scene=hilfs)
            bericht['gross_kb'] += os.path.getsize(zg) / 1024.0

            # Vorschau: skaliert, damit ein Uebersichtsraster nicht 72 grosse
            # Bilder zieht.
            b, h = bild.size
            bild.scale(klein, int(round(h * klein / b)))
            hilfs.render.image_settings.quality = guete_klein
            zk = os.path.join(klein_ordner, stamm + '.webp')
            bild.save_render(filepath=zk, scene=hilfs)
            bericht['klein_kb'] += os.path.getsize(zk) / 1024.0

            bpy.data.images.remove(bild)
        except Exception as e:
            bericht['fehler'].append(dict(datei=name, grund=str(e)))

    bericht['gross_kb'] = round(bericht['gross_kb'], 1)
    bericht['klein_kb'] = round(bericht['klein_kb'], 1)
    bericht['je_bild_kb'] = round(bericht['gross_kb'] / max(len(dateien), 1), 1)
    bericht['sekunden'] = round(time.time() - t0, 1)
    return bericht


# =========================================================================
#  MASERUNG NACHZIEHEN -- was die Plattenansicht sichtbar gemacht hat
#
#  In der Hero-Ansicht sah die Maserung richtig aus. In der Plattenansicht
#  mit 1,55 m Bildbreite nicht mehr, und zwar aus zwei Gruenden:
#
#  1. DIE RINGE LASEN ALS HOEHENLINIEN. Der Farbverlauf hatte die dunkle
#     Zone auf 8,5 % der Ringbreite. Bei 170 Ringen je Meter sind das
#     0,5 mm -- und ein Pixel deckt in dieser Ansicht 0,97 mm ab. Ein
#     halber Pixel breites Band IST eine Linie, da hilft keine Aufloesung.
#     Echte Eiche hat das umgekehrte Verhaeltnis: Das Fruehholz mit den
#     offenen Poren nimmt ein Viertel bis ein Drittel des Ringes ein.
#
#  2. DIE MARKSTRAHLEN FEHLTEN GANZ. Sie sind das auffaelligste Merkmal
#     von Eiche -- die "Spiegel", an denen man sie ueber zwei Meter
#     erkennt. Sie laufen RADIAL, also quer zu den Ringen, und sie sind
#     fast nur ueber den GLANZ sichtbar, kaum ueber die Farbe. Deshalb
#     waren sie zurueckgestellt; in der Hero-Ansicht fehlt einem nichts.
#     Auf der Platte in Grossaufnahme schon.
#
#  Esche und Nussbaum bekommen keine: Esche hat keine sichtbaren Strahlen,
#  Nussbaum ist halbringporig und zeigt sie nicht. Raeuchereiche ist
#  anatomisch Eiche und bekommt sie.
# =========================================================================

MIT_STRAHLEN = ('Eiche', 'Raeuchereiche')


def maserung_verfeinern(hoelzer=None):
    """Baender verbreitern und Markstrahlen ergaenzen. Aendert in place."""
    hoelzer = hoelzer or list(tk.HOELZER)
    bericht = {}

    for holz in hoelzer:
        m = bpy.data.materials.get('M_Holz_' + holz)
        if m is None:
            bericht[holz] = 'fehlt'
            continue
        nt = m.node_tree
        bsdf = next(n for n in nt.nodes if n.type == 'BSDF_PRINCIPLED')

        # ---- 1. Baender verbreitern ------------------------------------
        rampe = next((n for n in nt.nodes if n.type == 'VALTORGB'), None)
        if rampe is not None:
            cr = rampe.color_ramp
            alt = [(k.position, tuple(k.color)) for k in sorted(cr.elements,
                                                                key=lambda x: x.position)]
            dunkel = alt[0][1]
            hell = alt[2][1] if len(alt) > 2 else alt[-1][1]
            mittel = alt[4][1] if len(alt) > 4 else hell
            # Neue Lage: ein Viertel des Ringes ist Fruehholz mit Poren,
            # der Uebergang dahin weich statt als Kante.
            neu = [(0.00, dunkel), (0.17, dunkel), (0.30, hell),
                   (0.58, hell), (0.72, mittel), (0.93, mittel), (1.00, dunkel)]
            while len(cr.elements) > 1:
                cr.elements.remove(cr.elements[-1])
            cr.elements[0].position = neu[0][0]
            cr.elements[0].color = neu[0][1]
            for p, f in neu[1:]:
                e = cr.elements.new(p)
                e.color = f
            bericht.setdefault(holz, {})['baender'] = 'dunkelzone 8,5 -> 24 %'

        # Alte Strahlenknoten weg, bevor neue kommen. Ohne das legt jeder
        # Aufruf einen weiteren Satz dazu, die Wirkung addiert sich, und man
        # sucht den Fehler in den Werten statt im Aufruf.
        for n in [x for x in nt.nodes if x.name.startswith('Strahlen_')]:
            if n.type == 'MIX' or n.type == 'MIX_RGB':
                quelle = next((l.from_socket for l in nt.links
                               if l.to_node.name == n.name
                               and l.to_socket.name in ('A', 'Color1')), None)
                ziel_name = 'Roughness' if n.type == 'MIX' else 'Base Color'
                if quelle is not None:
                    nt.links.new(quelle, bsdf.inputs[ziel_name])
            nt.nodes.remove(n)

        if holz not in MIT_STRAHLEN:
            bericht.setdefault(holz, {})['strahlen'] = 'keine (anatomisch richtig)'
            continue

        # ---- 2. Markstrahlen -------------------------------------------
        # Gestreckt LAENGS der Bohle: Auf der Flaeche erscheinen die Strahlen
        # als kurze, schmale Spiegel in Faserrichtung. Kleiner Massstab in X
        # heisst lang in X, grosser in Y/Z heisst schmal quer dazu.
        koord = next(n for n in nt.nodes if n.type == 'TEX_COORD')
        abb = nt.nodes.new('ShaderNodeMapping')
        abb.name = 'Strahlen_Abbildung'
        # Erster Versuch war (1,1 / 26 / 26) bei Rauschen 6,5 und Schwelle
        # 0,60. Ergebnis: die ganze Platte wirkte behaart und dadurch blass --
        # aus Spiegeln waren Haare geworden. Markstrahlen sind EINZELNE
        # Flecken von wenigen Zentimetern, keine Schraffur. Also kuerzer
        # (kleinerer Streckfaktor), groeber und deutlich seltener.
        abb.inputs['Scale'].default_value = (3.2, 8.5, 8.5)
        rauschen = nt.nodes.new('ShaderNodeTexNoise')
        rauschen.name = 'Strahlen_Rauschen'
        rauschen.inputs['Scale'].default_value = 11.0
        rauschen.inputs['Detail'].default_value = 2.0
        rauschen.inputs['Roughness'].default_value = 0.45
        # Nur die obersten Prozent werden zum Spiegel -- Strahlen sind
        # einzelne Flecken, kein Flimmern ueber die ganze Flaeche.
        schwelle = nt.nodes.new('ShaderNodeMapRange')
        schwelle.name = 'Strahlen_Schwelle'
        schwelle.inputs['From Min'].default_value = 0.68
        schwelle.inputs['From Max'].default_value = 0.82
        schwelle.inputs['To Min'].default_value = 0.0
        schwelle.inputs['To Max'].default_value = 1.0
        schwelle.clamp = True
        nt.links.new(koord.outputs['Object'], abb.inputs['Vector'])
        nt.links.new(abb.outputs['Vector'], rauschen.inputs['Vector'])
        nt.links.new(rauschen.outputs['Fac'], schwelle.inputs['Value'])
        bericht.setdefault(holz, {})['strahlen'] = 'gesetzt'

        # (a) GLANZ: Wo ein Strahl liegt, ist die Flaeche glatter. Das ist
        #     der eigentliche Effekt -- Markstrahlen sieht man, weil sie
        #     anders spiegeln, nicht weil sie anders gefaerbt sind.
        rau_quelle = next((l.from_socket for l in nt.links
                           if l.to_node.name == bsdf.name
                           and l.to_socket.name == 'Roughness'), None)
        if rau_quelle is not None:
            glatt = nt.nodes.new('ShaderNodeMix')
            glatt.name = 'Strahlen_Glanz'
            glatt.data_type = 'FLOAT'
            glatt.inputs['B'].default_value = 0.055   # Spiegelglatt
            nt.links.new(schwelle.outputs['Result'], glatt.inputs['Factor'])
            nt.links.new(rau_quelle, glatt.inputs['A'])
            nt.links.new(glatt.outputs['Result'], bsdf.inputs['Roughness'])

        # (b) FARBE: nur ein Hauch heller. Wer hier viel aufdreht, bekommt
        #     weisse Striche statt Spiegel.
        farb_quelle = next((l.from_socket for l in nt.links
                            if l.to_node.name == bsdf.name
                            and l.to_socket.name == 'Base Color'), None)
        if farb_quelle is not None:
            heller = nt.nodes.new('ShaderNodeMixRGB')
            heller.name = 'Strahlen_Farbe'
            heller.blend_type = 'SCREEN'
            # 0,09 war zu viel und hat die ganze Platte aufgehellt.
            heller.inputs['Color2'].default_value = (0.045, 0.037, 0.026, 1.0)
            nt.links.new(schwelle.outputs['Result'], heller.inputs['Fac'])
            nt.links.new(farb_quelle, heller.inputs['Color1'])
            nt.links.new(heller.outputs['Color'], bsdf.inputs['Base Color'])

    return bericht


# =========================================================================
#  DREHUNG -- zwei Netze statt einem
#
#  Fuer die 72 Standbilder steckt alles in EINEM Netz. Das war richtig und
#  ist oben begruendet. Fuer die Drehung ist es genau falsch, und zwar aus
#  einem Grund, der nichts mit Unreal zu tun hat: Die Hohlkehle ist nur
#  nach hinten geschlossen. Dreht man sie mit, dreht sich der Raum statt
#  des Tisches, und nach etwa 60 Grad sieht die Kamera an ihrer Kante
#  vorbei ins Schwarze. Dasselbe passiert, wenn man die Kamera um den
#  Tisch fuehrt -- das ist dieselbe Bewegung, nur anders herum gedacht.
#
#  Also: Der Tisch wird ein Netz, die Buehne ein zweites. Und jetzt hilft
#  ausgerechnet das Verhalten, das oben das Problem war: Interchange legt
#  das Pivot auf die Boxmitte -- und die Boxmitte des Tisches IST seine
#  Drehachse. Man muss in Unreal nichts ausrichten, nur drehen.
# =========================================================================
DREH_AUS = os.path.join(os.path.dirname(P), 'unreal-export', 'drehung')


def exportieren_dreh(laenge='mittel', gestell='wange', holz='Eiche',
                     metall='Messing', szene=None):
    """Hero-Variante als zwei GLB: dreh-tisch.glb und dreh-buehne.glb."""
    szene = szene or bpy.context.scene
    teile = aufbauen(laenge, gestell, holz, metall, szene)
    tisch = [o for o in teile
             if o.name != 'Hohlkehle' and not o.name.startswith('Licht_')]
    buehne = [o for o in teile if o.name == 'Hohlkehle']
    if not tisch or not buehne:
        raise RuntimeError('Aufteilung misslungen: %d Tisch, %d Buehne'
                           % (len(tisch), len(buehne)))

    eins_t, sch_t = verschmelzen(tisch, name='Dreh_Tisch', szene=szene)
    eins_b, sch_b = verschmelzen(buehne, name='Dreh_Buehne', szene=szene)
    for o in teile:
        bpy.data.objects.remove(o, do_unlink=True)

    os.makedirs(DREH_AUS, exist_ok=True)
    bericht = {}
    for obj, datei, schaechte in ((eins_t, 'dreh-tisch.glb', sch_t),
                                  (eins_b, 'dreh-buehne.glb', sch_b)):
        for o in bpy.context.view_layer.objects:
            if o is not None:
                o.select_set(False)
        obj.select_set(True)
        bpy.context.view_layer.objects.active = obj
        pfad = os.path.join(DREH_AUS, datei)
        bpy.ops.export_scene.gltf(
            filepath=pfad,
            export_format='GLB',
            use_selection=True,
            export_apply=False,
            export_image_format='AUTO',
            export_texcoords=True,
            export_normals=True,
            export_tangents=True,
            export_draco_mesh_compression_enable=False,
            export_yup=True,
            export_cameras=False,
            export_lights=False,
        )
        ecken = [obj.matrix_world @ Vector(k) for k in obj.bound_box]
        klein = [min(p[i] for p in ecken) for i in range(3)]
        gross = [max(p[i] for p in ecken) for i in range(3)]
        bericht[datei] = dict(
            mb=round(os.path.getsize(pfad) / 1024 / 1024, 2),
            schaechte=schaechte,
            flaechen=len(obj.data.polygons),
            mass_m=[round(gross[i] - klein[i], 3) for i in range(3)],
            mitte_m=[round((gross[i] + klein[i]) / 2, 3) for i in range(3)],
            unten_m=round(klein[2], 3))
    return bericht
