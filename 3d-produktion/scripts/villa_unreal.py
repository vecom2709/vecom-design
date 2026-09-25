# -*- coding: utf-8 -*-
"""
villa_unreal.py -- Die Villa auf dem Weg nach Unreal Engine.

WAS HIER ANDERS IST ALS BEIM WEB-EXPORT
Das GLB fuer den Browser ist ein Kompromiss: wenig Bytes, flache Werte,
kein Gelaende, kein Gras. Unreal bekommt das Gegenteil -- alles, was da
ist, in voller Dichte. Nanite kuemmert sich um die Dreiecke, und der Path
Tracer rechnet ohnehin auf der GPU.

DER MASSSTAB, EXPLIZIT STATT GERATEN
Blender rechnet in Metern, Unreal in Zentimetern. Beim FBX-Export gibt es
drei Stellen, an denen umgerechnet werden kann (Blender-Einheiten,
FBX-Header, Unreal-Importskalierung) -- und genau deshalb landet dabei so
oft ein Haus in Streichholzgroesse oder eines fuer Riesen im Level.
Hier wird EINMAL umgerechnet, sichtbar: global_scale=100. Ein Haus von
18,0 m Laenge muss danach 1800 Einheiten messen, und genau das wird auf
der Unreal-Seite nachgemessen, bevor irgendetwas weitergeht.

WARUM NACH MATERIAL GRUPPIERT
In Unreal haengt das Material am Mesh-Asset. Ein Netz je Material heisst:
ein Materialschacht, eine Instanz, keine Verwechslung. Das ist auch der
Weg, der laut Befund vom 15.09.2026 als einziger zuverlaessig traegt --
`StaticMeshTools.set_material` am ASSET, nicht am platzierten Actor.
Fuer den Path Tracer ist die Aufteilung ohnehin gleichgueltig.

WAS NICHT MITKOMMT
    Gras        -- Partikelhaare gehen nicht durch FBX. Ein Grasbueschel
                   kommt als eigenes Netz mit, Unreal streut es selbst.
    Lichter     -- Sonne und Himmel baut Unreal selbst, physikalisch in Lux.
    Kameras     -- als Zahlen im Manifest, nicht als FBX-Kameras.
"""

import bmesh
import bpy
import json
import math
import os
import sys

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.insert(0, P)

AUS = r'C:\Users\manue\Documents\Unreal Projects\VecomVilla\Import'

# GEMESSEN AM 19.09.2026, nicht angenommen.
# Erster Versuch mit global_scale=100: Das Haus kam in Unreal mit 268.410
# statt 2.679,7 Einheiten an -- exakt hundertmal zu gross. Der Grund: Der
# FBX-Importer von Unreal rechnet die Datei SELBST von Meter auf
# Zentimeter um. Wer in Blender schon umrechnet, tut es zweimal.
# Also: Blender schreibt echte Meter, Unreal macht Zentimeter daraus.
# Umgerechnet wird an genau einer Stelle -- und die liegt nicht hier.
MASSSTAB = 1.0

# Was nicht exportiert wird: das Gelaende ist 520 x 520 m und wuerde als
# ein Netz jede Sinnhaftigkeit sprengen. Es wird in Unreal als eigenes
# Stueck behandelt (eigene Datei, eigenes Material, eigene Entscheidung
# ueber Nanite).
# 20.09.2026: Zwei Gelaendenetze statt einem. Warum, steht ausfuehrlich
# in villa_garten.fernbereich -- kurz: Ein Netz von 44 km Ausdehnung
# bekommt von Nanite ein Ersatznetz mit 252 Dreiecken, und das begraebt
# das halbe Haus.
EIGEN = {'p08_gelaende': 'GELAENDE',
         'p08_gelaende_fern': 'GELAENDE_FERN'}

# Wie stark alle Halme eines Bueschels in dieselbe Richtung fallen.
# Siehe die ausfuehrliche Begruendung in grasbueschel(): Ohne eine
# gemeinsame Richtung gibt es keine Maehstreifen, und ohne Maehstreifen
# sieht kein Rasen gepflegt aus. Die Streifen selbst entstehen drueben
# in v06_gras.py, das benachbarte Bahnen um 180 Grad dreht.
#
# 20.09.2026, 05:38 Uhr: von 0,74 auf 0,38. Bei 0,74 klappten die Halme
# so weit um, dass die nahe Wiese im Bild von 0,12 auf 0,53 sprang --
# das Vierfache, und deutlich heller als dieselbe Flaeche in der Ferne
# (0,16). Dazu klafften die Bueschel auseinander, weil ein umgelegter
# Halm weniger Grundflaeche deckt als ein stehender. 0,38 reicht fuer
# die Bahnen und laesst den Bestand geschlossen.
GEMEINSAM = 0.38


def _sichtbar_mesh():
    return [o for o in bpy.data.objects
            if o.type == 'MESH' and not o.hide_render]


def _material_von(ob):
    if ob.data.materials and ob.data.materials[0] is not None:
        return ob.data.materials[0].name
    return 'Ohne'


def gruppen():
    """Objekte nach Material buendeln. Gelaende getrennt."""
    g = {}
    for ob in _sichtbar_mesh():
        schl = EIGEN.get(ob.name) or _material_von(ob)
        g.setdefault(schl, []).append(ob)
    return g


def _nur(objekte):
    bpy.ops.object.select_all(action='DESELECT')
    for o in objekte:
        o.select_set(True)
    bpy.context.view_layer.objects.active = objekte[0]


def fbx_schreiben(name, objekte, ordner=AUS):
    os.makedirs(ordner, exist_ok=True)
    pfad = os.path.join(ordner, name + '.fbx')
    _nur(objekte)
    bpy.ops.export_scene.fbx(
        filepath=pfad,
        use_selection=True,
        # Der Massstab steht an EINER Stelle -- siehe Kopf.
        global_scale=MASSSTAB,
        apply_scale_options='FBX_SCALE_NONE',
        apply_unit_scale=False,
        # Modifikatoren anwenden, sonst kommen die Fasen nicht mit. Ohne
        # Fasen hat jede Kante im Bild eine mathematisch perfekte Linie,
        # und das ist eines der sichersten Zeichen fuer ein Rechnerbild.
        use_mesh_modifiers=True,
        mesh_smooth_type='FACE',
        # Vertexfarben LINEAR, nicht in sRGB umgerechnet: Der
        # Wurzel-Spitze-Verlauf des Grases ist ein Faktor, keine Farbe.
        # Durch die sRGB-Kurve gedreht waere er schlicht die falsche Zahl.
        colors_type='LINEAR',
        use_tspace=True,
        add_leaf_bones=False,
        bake_anim=False,
        object_types={'MESH'},
        path_mode='STRIP',
    )
    return pfad, os.path.getsize(pfad)


def masse():
    """Die Kontrollmasse, gegen die Unreal spaeter geprueft wird."""
    xs, ys, zs = [], [], []
    for ob in _sichtbar_mesh():
        if ob.name in EIGEN:
            continue
        for ecke in ob.bound_box:
            p = ob.matrix_world @ bpy.app.driver_namespace.get(
                '_dummy', type(ob.location)(ecke)) if False else \
                ob.matrix_world @ type(ob.location)(ecke)
            xs.append(p.x); ys.append(p.y); zs.append(p.z)
    return {
        'x': [round(min(xs), 3), round(max(xs), 3)],
        'y': [round(min(ys), 3), round(max(ys), 3)],
        'z': [round(min(zs), 3), round(max(zs), 3)],
        'laenge_m': round(max(xs) - min(xs), 3),
        'hoehe_m': round(max(zs) - min(zs), 3),
    }


def grasbueschel(name='SM_Grasbueschel', halme=100, hoehe=0.095, feld=0.13):
    """Ein RASENSTUECK, kein einzelnes Bueschel.

    Warum nicht das Partikelsystem exportieren: FBX kennt keine Haare.
    Warum ueberhaupt Geometrie: Die Kamera steht auf 4,4 m und sieht die
    Flaeche fast von der Kante; unter diesem Winkel ueberdecken sich die
    Halme, und genau diese Ueberdeckung erkennt das Auge als Rasen. Ein
    Material kann das nicht, weil es keine Silhouette hat.

    WARUM JETZT 100 STATT 7 HALME -- IN ZWEI SCHRITTEN GEMESSEN
    Fassung 1: sieben Halme auf 7 cm Durchmesser, 180.000 Stueck ueber
    das ganze Gelaende. Macht 1,4 Stueck je Quadratmeter -- im Bild war
    davon NICHTS zu sehen.
    Fassung 2: 44 Halme auf 26 cm, 260.000 Stueck bis 70 m. Macht 17
    Stueck und damit 830 Halme je Quadratmeter. Im Bild vom 19.09.2026
    waren jetzt einzelne Bueschel im Vordergrund zu erkennen -- aber
    eben einzelne, wie eine abgebrannte Wiese, nicht wie Rasen.
    Fassung 3, diese: 100 Halme auf 26 cm, 700.000 Stueck bis 55 m.
    Macht 74 Stueck und rund 7.400 Halme je Quadratmeter. Ein englischer
    Rasen hat etwa 10.000 -- das ist die Groessenordnung, in der die
    Flaeche zugeht.

    Die Rechnung kostet fast nichts: Unreal baut den Beschleunigungsbaum
    EINMAL fuer 600 Dreiecke und stellt ihn 700.000 mal hin.
    """
    import random
    rnd = random.Random(20260919)
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    # HELLIGKEIT VON DER WURZEL ZUR SPITZE, als Vertexfarbe.
    # In Cycles macht das die Haarinfo "Intercept": unten dunkel, oben
    # hell. Das ist kein Effekt, sondern Physik -- unten im Bestand kommt
    # weniger Licht an, und die Spitze ist duenner und laesst mehr durch.
    # Ohne diesen Verlauf wird dichtes Gras im Bild ein schwarzer Filz;
    # genau das zeigte der Lauf vom 19.09.2026 mit 700.000 Stuecken.
    farbschicht = bm.loops.layers.color.new('Verlauf')
    hoehen = {}
    for i in range(halme):
        a = rnd.uniform(0.0, 6.283)
        r = feld * math.sqrt(rnd.random())
        x0, y0 = math.cos(a) * r, math.sin(a) * r
        h = hoehe * rnd.uniform(0.55, 1.30)
        neig = rnd.uniform(0.12, 0.55)
        # DIE HALME FALLEN NICHT BELIEBIG, SONDERN UEBERWIEGEND IN EINE
        # RICHTUNG -- und das ist der Grund, warum ein gemaehter Rasen
        # gestreift aussieht.
        #
        # Ein Maehwerk legt die Halme um. Zwei Bahnen nebeneinander
        # werden in entgegengesetzter Richtung gefahren, und deshalb
        # zeigen ihre Halme mit der Spitze voneinander weg. Die eine
        # Bahn spiegelt das Licht zum Betrachter, die andere von ihm
        # fort -- das ergibt die hellen und dunklen Streifen auf jedem
        # gepflegten Rasen. Kein Material kann das nachbilden, weil es
        # keine Textur ist, sondern eine Ausrichtung.
        # Bis zum 20.09.2026 war ri voellig zufaellig. Damit stand
        # jeder Halm irgendwie, jedes Bueschel sah aus wie jedes andere,
        # und aus 700.000 Stueck wurde ein Filz.
        # 0,74 gemeinsam, 0,26 Streuung: genug Ordnung fuer den Streifen,
        # genug Unordnung, dass es kein Kamm wird.
        ri = rnd.uniform(0.0, 6.283)
        ex = math.cos(ri) * (1.0 - GEMEINSAM) + GEMEINSAM
        ey = math.sin(ri) * (1.0 - GEMEINSAM)
        lg = math.hypot(ex, ey) or 1.0
        ex, ey = ex / lg, ey / lg
        breit = 0.0026 * rnd.uniform(0.8, 1.2)
        # Drei Segmente mit zunehmender Neigung: ein Halm steht unten
        # steil und legt sich oben. Gerade Halme sehen aus wie Borsten.
        knoten = []
        for s in range(4):
            t = s / 3.0
            # Die Kruemmung waechst quadratisch, die Breite nimmt ab.
            aus = neig * h * (t * t)
            knoten.append((x0 + ex * aus, y0 + ey * aus, h * t,
                           breit * (1.0 - 0.85 * t)))
        vor = None
        for s in range(4):
            px, py, pz, pb = knoten[s]
            t = s / 3.0
            # Die Breite quer zur Neigungsrichtung auftragen.
            qx, qy = -ey * pb, ex * pb
            if s < 3:
                vl = bm.verts.new((px - qx, py - qy, pz))
                vr = bm.verts.new((px + qx, py + qy, pz))
                hoehen[vl] = t
                hoehen[vr] = t
                if vor is not None:
                    bm.faces.new((vor[0], vor[1], vr, vl))
                vor = (vl, vr)
            else:
                vs = bm.verts.new((px, py, pz))
                hoehen[vs] = t
                bm.faces.new((vor[0], vor[1], vs))
    # Verlauf eintragen: unten 0,38, oben 1,0. Die Kurve ist bewusst
    # nicht linear -- der Uebergang von Schatten zu Licht passiert im
    # unteren Drittel des Halms.
    for f in bm.faces:
        for lp in f.loops:
            t = hoehen.get(lp.vert, 0.0)
            wert = 0.38 + 0.62 * (t ** 0.65)
            lp[farbschicht] = (wert, wert, wert, 1.0)
    bm.normal_update()
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.scene.collection.objects.link(ob)
    mat = bpy.data.materials.get('Grashalm')
    if mat is not None:
        me.materials.append(mat)
    return ob


def haupt():
    os.makedirs(AUS, exist_ok=True)
    bericht = {'massstab': MASSSTAB, 'masse_blender': masse(), 'teile': []}

    g = gruppen()
    for schl, objekte in sorted(g.items()):
        sicher = ''.join(c if c.isalnum() or c == '_' else '_' for c in schl)
        pfad, bytes_ = fbx_schreiben('SM_' + sicher, objekte)
        dreiecke = 0
        for o in objekte:
            o.data.calc_loop_triangles()
            dreiecke += len(o.data.loop_triangles)
        bericht['teile'].append({
            'material': schl, 'datei': os.path.basename(pfad),
            'objekte': len(objekte), 'dreiecke': dreiecke,
            'kb': bytes_ // 1024,
        })
        print('[ue] %-18s %3d Objekte %7d Dreiecke %6d KB'
              % (schl, len(objekte), dreiecke, bytes_ // 1024))

    bue = grasbueschel()
    pfad, bytes_ = fbx_schreiben('SM_Grasbueschel', [bue])
    bericht['gras'] = {'datei': os.path.basename(pfad), 'kb': bytes_ // 1024}
    print('[ue] Grasbueschel %d KB' % (bytes_ // 1024))

    with open(os.path.join(AUS, 'villa-unreal.json'), 'w', encoding='utf-8') as f:
        json.dump(bericht, f, ensure_ascii=False, indent=2, sort_keys=True)
    return bericht
