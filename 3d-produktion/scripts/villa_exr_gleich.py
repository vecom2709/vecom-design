# -*- coding: utf-8 -*-
"""Die Vorlage, GENAUSO GEBAUT wie der Unreal-Export.

DER BEFUND, 21.09.2026

Beim ersten Blick auf die beiden ganzen Bilder nebeneinander
(blick/ganz-beide.png) faellt etwas auf, das keine Messung je haette
zeigen koennen, weil Messungen nur Zahlen aus Rechtecken holen: In
Unreal steht eine Wiese aus 1,2 Millionen Buescheln, dazu kleine
Zypressen am linken Rand, ein Kuebelbaum auf der Terrasse und
Aussenmoebel. In der Cycles-Vorlage ist der Boden eine glatte gruene
Flaeche, und nichts davon ist da.

Der Grund steht in den Aufbauwegen, nicht im Bild:

  lauf_unreal_export.py   villa.haupt + villa_material
                          + villa_garten.aufwerten(mit_gras=False)
                          + villa_aussen.moeblieren
                          OHNE villa_echt (die Feinschicht wird in
                          Unreal mit v05 neu gebaut)

  villa_lauf.aufbauen     villa.haupt + villa_material + villa_echt
                          OHNE villa_garten, OHNE villa_aussen

Gemessen wurden also zwei verschiedene Szenen. Fuer Putz und Himmel
macht das wenig aus -- eine verputzte Wand bleibt eine verputzte Wand.
Fuer "Rasen vorn" (+0,14 Blenden), "Rasen links" (+0,48) und
"Terrasse Boden" (+1,91) macht es alles aus: Dort standen in den beiden
Bildern schlicht verschiedene Dinge. Diese drei Zahlen waren nie
Unreal-Fehler, und die Gras-Energiebilanz aus v97 wurde gegen eine
glatte gruene Flaeche gerechnet.

WAS DIESES SKRIPT ANDERS MACHT

Es baut die Szene in derselben Reihenfolge wie der Export, mit Gras
(die Partikelhaare sind das, was Unreals gestreute Bueschel abbilden
sollen), und legt villa_echt obendrauf -- denn diese Ebene gibt es in
Unreal ja auch, nur dort in v05 neu gebaut. Dazu echtes Glas wie in
villa_exr_glas.py, damit die Scheibe physikalisch stimmt und nicht der
EEVEE-Alphatrick als Massstab dient.

Ergebnis: ausgabe/cycles-gleich-garten.exr -- die erste Vorlage, gegen
die ein Vergleich ueberhaupt zulaessig ist.

Aufruf:
  werkzeug\\blender-lauf.ps1 -Skript villa_exr_gleich.py -Log villa-gleich.log
"""
import bpy
import os
import sys
import time
import traceback

HIER = os.path.dirname(os.path.abspath(__file__))
if HIER not in sys.path:
    sys.path.append(HIER)

AUS = os.path.join(os.path.dirname(HIER), 'ausgabe')
KAMERAS = ['garten']
BREITE, HOEHE = 960, 540
# Glas und Gras rauschen beide mehr als Putz.
PROBEN = 192
# 21.09.2026: mit Kaustiken (siehe haupt) rauscht Licht durch Glas und
# Wasser deutlich mehr. Ein Bild dauerte mit 192 Proben 21 s -- es ist
# also Luft fuer das Vierfache.
PROBEN = 768

GLASFARBE = (0.90, 0.95, 0.91, 1.0)
# 21.09.2026: DIE FARBE WIRKT JE GRENZFLAECHE, NICHT JE SCHEIBE.
# Cycles faerbt den durchgehenden Strahl bei JEDER Brechung mit der
# Grundfarbe. Die Isolierverglasung hat vier Grenzflaechen (36-mm-Quader
# plus zweite Scheibe) -- aus 0,90/0,95/0,91 wurde im Durchblick
# 0,66/0,81/0,69, dazu der Fresnel-Verlust. Das ist ein getoentes
# Sonnenschutzglas, keine Villenverglasung. Gemeint war die Farbe je
# Scheibe; echtes Floatglas schluckt in 6 mm rund 3 Prozent im Volumen,
# rot etwas mehr als gruen (Eisenoxid). Je Grenzflaeche also die Wurzel
# davon: 0,98/0,99/0,985. Zusammen mit Fresnel ergibt das rund 0,8
# Lichtdurchlass fuer die Einheit -- der uebliche Wert fuer klares
# Isolierglas. Unreal rechnet das Glas ohne Volumenabsorption
# (Fresnel allein, rund 0,85); das liegt jetzt dicht daneben statt
# 0,7 Blenden daneben.
GLASFARBE = (0.98, 0.99, 0.985, 1.0)


def lade(name):
    p = os.path.join(HIER, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def glas_echt():
    """Aus dem EEVEE-Alphatrick echtes Glas machen -- wie in
    villa_exr_glas.py, damit beide Vorlagen dieselbe Scheibe haben."""
    mat = bpy.data.materials.get('Glas')
    if mat is None:
        print('[gleich] Material "Glas" nicht gefunden')
        return False
    b = None
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            b = n
            break
    if b is None:
        print('[gleich] Kein Principled BSDF im Material "Glas"')
        return False
    b.inputs['Base Color'].default_value = GLASFARBE
    b.inputs['Roughness'].default_value = 0.02
    b.inputs['Metallic'].default_value = 0.0
    b.inputs['IOR'].default_value = 1.5
    b.inputs['Alpha'].default_value = 1.0
    if 'Transmission Weight' in b.inputs:
        b.inputs['Transmission Weight'].default_value = 1.0
    mat.blend_method = 'OPAQUE'
    if hasattr(mat, 'use_backface_culling'):
        mat.use_backface_culling = False
    if hasattr(mat, 'use_raytrace_refraction'):
        mat.use_raytrace_refraction = True

    # 21.09.2026: SONNE DURCH DIE SCHEIBE.
    # Auch mit Kaustiken an blieb der Innenraum der Vorlage 1,4 Blenden
    # dunkler als in Unreal. Grund: Ein Schattenstrahl von einer Flaeche
    # im Raum zur Sonne endet in Cycles an der Glasscheibe -- echtes Glas
    # ist fuer Schattenstrahlen undurchsichtig. Sonnenlicht im Raum
    # entsteht dann nur, wenn ein zufaellig gestreuter Strahl durch die
    # Scheibe genau die Sonnenscheibe trifft, und das passiert praktisch
    # nie. Die Sonnenflecken auf dem Boden fehlen, obwohl echte Fenster
    # sie haben. Unreal laesst die Sonne durch (ApproximateCaustics).
    # Bei planparallelen Scheiben hebt sich die Brechung beim Ein- und
    # Austritt auf -- die Sonne kommt geradlinig herein, nur versetzt um
    # Millimeter. Deshalb ist der uebliche Weg der Architekturvisualisierung
    # hier physikalisch sauber: Fuer Schattenstrahlen ist die Scheibe
    # durchsichtig, gefaerbt mit Glasfarbe mal Fresnel-Verlust je Flaeche
    # (4 %). Kamerastrahlen sehen weiter das echte Glas.
    nt = mat.node_tree
    aus = None
    for n in nt.nodes:
        if n.type == 'OUTPUT_MATERIAL':
            aus = n
            break
    if aus is not None:
        weg = nt.nodes.new('ShaderNodeLightPath')
        durch = nt.nodes.new('ShaderNodeBsdfTransparent')
        durch.inputs['Color'].default_value = (GLASFARBE[0] * 0.96,
                                               GLASFARBE[1] * 0.96,
                                               GLASFARBE[2] * 0.96, 1.0)
        misch = nt.nodes.new('ShaderNodeMixShader')
        nt.links.new(weg.outputs['Is Shadow Ray'], misch.inputs['Fac'])
        nt.links.new(b.outputs[0], misch.inputs[1])
        nt.links.new(durch.outputs[0], misch.inputs[2])
        nt.links.new(misch.outputs[0], aus.inputs['Surface'])
        print('[gleich] Glas fuer Schattenstrahlen durchsichtig (Sonne im Raum)')
    print('[gleich] Glas auf echte Transmission gestellt')
    return True


def pool_echt():
    """Becken und Wasser so, wie villa.py sie seit dem 20.09.2026 will.

    villa.py hat das Becken aufgehellt (0,415/0,492/0,512, "Das Blau eines
    Pools kommt NICHT aus dem Wasser, sondern aus der hellen Wanne") und
    das Wasser auf echte Transmission gestellt (IOR 1,333, keine
    Alpha-Mischung). villa_material.anwenden() laeuft DANACH und setzt
    beides fuer den Browser wieder zurueck: dunkles Becken, Wasser als
    Alphatrick. Unreal hat die villa.py-Werte (v05: Poolstein
    0,415/0,492/0,512, Wasser als Dielektrikum). Die Vorlage zeigte also
    einen schwarzen Pool, Unreal einen tuerkisen -- zwei verschiedene
    Becken, kein Renderunterschied. Hier wird die Vorlage auf den
    physikalischen Stand von villa.py gebracht, wie beim Glas.
    """
    n = 0
    stein = bpy.data.materials.get('Poolstein')
    if stein is not None:
        for k in stein.node_tree.nodes:
            if k.type == 'MIX' and getattr(k, 'data_type', '') == 'RGBA':
                k.inputs[6].default_value = (0.375, 0.452, 0.472, 1.0)
                k.inputs[7].default_value = (0.455, 0.532, 0.552, 1.0)
                n += 1
        for k in stein.node_tree.nodes:
            if k.type == 'BSDF_PRINCIPLED':
                k.inputs['Roughness'].default_value = 0.28
    wasser = bpy.data.materials.get('Wasser')
    if wasser is not None:
        for k in wasser.node_tree.nodes:
            if k.type != 'BSDF_PRINCIPLED':
                continue
            k.inputs['Base Color'].default_value = (0.72, 0.90, 0.92, 1.0)
            k.inputs['Roughness'].default_value = 0.02
            k.inputs['Metallic'].default_value = 0.0
            k.inputs['IOR'].default_value = 1.333
            k.inputs['Alpha'].default_value = 1.0
            if 'Transmission Weight' in k.inputs:
                k.inputs['Transmission Weight'].default_value = 1.0
        wasser.blend_method = 'OPAQUE'
    print('[gleich] Pool wie villa.py: Becken hell (%d Mischknoten), Wasser echt' % n)
    return n > 0


def glas_ohne_spiegelung():
    """Brechungsindex 1,0: die Scheibe ist optisch nicht mehr da.

    Kein Sprung im Brechungsindex heisst keine Grenzflaeche -- also
    weder Fresnel-Spiegelung noch Ablenkung. Uebrig bleibt genau der
    Durchblick, den die Farbe der Scheibe noch faerbt. Zusaetzlich geht
    die Glanzstaerke auf 0, damit auch kein Rest-Schimmer bleibt.
    """
    mat = bpy.data.materials.get('Glas')
    if mat is None:
        return False
    b = None
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            b = n
            break
    if b is None:
        return False
    b.inputs['IOR'].default_value = 1.0
    for name in ('Specular IOR Level', 'Specular'):
        if name in b.inputs:
            b.inputs[name].default_value = 0.0
    print('[gleich] Scheibe auf Brechungsindex 1,0 gestellt (nur Durchblick)')
    return True


def nur_himmel():
    """Sonnenscheibe aus -- es leuchtet nur noch der Himmel.

    Die Sonnenlampe steht in villa_szene ohnehin auf 0; alles Sonnenlicht
    kommt aus der Scheibe im Nishita-Himmel. Ohne sie bleibt reines
    Himmelslicht, und genau das laesst sich mit einem Unreal-Lauf mit
    ausgeschalteter Sonne vergleichen: Schattenfassade gegen sichtbaren
    Himmel, ohne dass die Sonne dazwischenfunkt.
    """
    w = bpy.context.scene.world
    n = 0
    for k in w.node_tree.nodes:
        if k.type == 'TEX_SKY' and hasattr(k, 'sun_disc'):
            k.sun_disc = False
            n += 1
    print('[gleich] Sonnenscheibe aus: %d Himmelsknoten' % n)
    return n > 0


def haupt():
    t0 = time.time()
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    nurhimmel = 'nurhimmel' in argv
    # 'albedo': zusaetzlich die Diffusfarbe je Pixel ausgeben (mehrlagiges
    # EXR). Damit laesst sich an jeder Messflaeche ablesen, welche
    # Grundfarbe Cycles dort WIRKLICH rechnet -- nach allen Mischungen,
    # Kantenaufhellungen und Spritzwasser aus villa_echt.
    albedo = 'albedo' in argv

    # --- Reihenfolge WIE IM EXPORT, damit dieselbe Welt entsteht.
    v = lade('villa.py')
    d = v['haupt'](speichern=False, moebel=True, fasen=True)
    lade('villa_material.py')['anwenden']()

    garten = lade('villa_garten.py')
    # mit_gras=True: Der Export laesst die Partikelhaare weg, weil FBX
    # sie nicht kennt, und schickt statt dessen EIN Bueschel mit, aus dem
    # Unreal 1,2 Millionen streut. Fuer die VORLAGE ist das Gras selbst
    # der Massstab, also gehoert es hier hinein.
    print('[gleich] Garten: %s' % (garten['aufwerten'](mit_gras=True),))

    aussen = lade('villa_aussen.py')
    print('[gleich] Aussen: %s' % (aussen['moeblieren'](),))

    # villa_echt liegt im Export nicht drin, weil prozedurale Knoten
    # nicht durch FBX gehen. In Unreal wird diese Ebene von v05 neu
    # gebaut -- also gehoert sie auf beiden Seiten dazu.
    try:
        print('[gleich] Veredelt: %s' % (lade('villa_echt.py')['veredeln'](),))
    except Exception:
        print('[gleich] villa_echt uebersprungen:')
        traceback.print_exc()

    szene = lade('villa_szene.py')
    kams = szene['alles'](motor='BLENDER_EEVEE', breite=BREITE, hoehe=HOEHE,
                          luft=False)
    print('[gleich] Aufbau fertig: %d Objekte, %d Dreiecke, %.0f s'
          % (d['objekte'], d['dreiecke'], time.time() - t0))

    glas_echt()
    pool_echt()
    if nurhimmel:
        nur_himmel()

    cyc = lade('villa_cycles.py')
    info = cyc['einstellen'](proben=PROBEN, breite=BREITE, hoehe=HOEHE,
                             rauschgrenze=0.015)
    print('[gleich] Geraet: %s' % info['geraet'])
    # 21.09.2026: villa_cycles.einstellen() ruft SEIN glas_echt() auf und
    # setzt dabei die Glasfarbe auf 0,93/0,96/0,97 und die Rauheit auf
    # 0,015 zurueck -- NACH dem glas_echt() hier oben. Die Glasfarbe von
    # hier kam deshalb nie im Bild an (die Aenderung auf 0,98/0,99/0,985
    # verschob das Glasfeld nur um 0,05 Blenden). Also hier noch einmal,
    # zuletzt. Die Schattenstrahl-Mischung bleibt dabei stehen, weil
    # villa_cycles nur die Eingaenge des Principled-Knotens anfasst.
    g = bpy.data.materials.get('Glas')
    if g is not None:
        for k in g.node_tree.nodes:
            if k.type == 'BSDF_PRINCIPLED':
                k.inputs['Base Color'].default_value = GLASFARBE
                k.inputs['Roughness'].default_value = 0.02
        print('[gleich] Glasfarbe nach villa_cycles erneut gesetzt: %s' % (GLASFARBE[:3],))

    sz = bpy.context.scene
    # 21.09.2026: KAUSTIKEN AN -- fuer die Vorlage sind sie Pflicht.
    # villa_cycles schaltet sie ab (Rauschen bei Vorschaubildern). Jedes
    # Licht, das durch eine ECHTE Scheibe oder durch die Wasserflaeche
    # geht, ist aber eine Kaustik: Ohne sie bekommt der Innenraum hinter
    # dem Glas kein Sonnen- und kein Himmelslicht, und die Poolwanne
    # unter dem Wasser bleibt schwarz -- genau so sah die Vorlage aus
    # (dunkle Innenraeume, schwarzer Pool). Unreals Pfadverfolger laesst
    # dieses Licht durch (r.PathTracing.ApproximateCaustics 1). Mit
    # abgeschalteten Kaustiken war die Vorlage an diesen Stellen also
    # nicht die Wirklichkeit, sondern eine Abkuerzung.
    # Filter Glossy 0,5 daempft die Feuerfunken, ohne die Kaustik
    # wegzunehmen; die Probenzahl steigt dafuer (siehe PROBEN).
    try:
        sz.cycles.caustics_refractive = True
        sz.cycles.caustics_reflective = True
        sz.cycles.blur_glossy = 0.5
        print('[gleich] Kaustiken an (Filter Glossy 0,5)')
    except Exception as f:
        print('[gleich] Kaustiken nicht gesetzt: %s' % f)
    try:
        sz.cycles.transmission_bounces = 12
        sz.cycles.max_bounces = max(sz.cycles.max_bounces, 16)
        sz.cycles.transparent_max_bounces = 16
        # 21.09.2026: villa_cycles kappt diffuse Spruenge bei 5. Draussen
        # ist das gleichgueltig, im Innenraum nicht: Licht kommt durchs
        # Fenster und wird von hellen Waenden (0,73) viele Male
        # weitergereicht. Die Probe "glasdurch" zeigte den Innenraum hinter
        # der Scheibe in Unreal 0,67 Blenden heller -- auch OHNE jede
        # Grenzflaeche. Unreal rechnet mit 32 Spruengen; die Vorlage jetzt
        # auch.
        sz.cycles.max_bounces = 32
        sz.cycles.diffuse_bounces = 32
        sz.cycles.glossy_bounces = 32
        sz.cycles.transmission_bounces = 32
    except Exception as f:
        print('[gleich] Durchgaenge nicht gesetzt: %s' % f)
    # 22.09.2026: KEINE KAPPUNG DES INDIREKTEN LICHTS.
    # Cycles kappt indirekte Beitraege je Probe standardmaessig bei 10
    # (Clamp Indirect). Hinter einer Scheibe ist ALLES indirekt -- der
    # Kamerastrahl hat die Glasflaeche schon hinter sich --, und ein
    # sonnenbeschienener Innenraum liefert Proben weit ueber 10. Der
    # Innenraum hinter dem Erdgeschossglas lag in der Vorlage an drei
    # Stellen (Innenwand, Kamin, Kueche) gleichmaessig um den Faktor 2
    # unter Unreal -- auch ohne Glas (nurdurch), also nicht die Scheibe.
    # Unreal kappt erst bei r.PathTracing.MaxPathIntensity 30.
    try:
        print('[gleich] Kappung vorher: direkt %.1f, indirekt %.1f'
              % (sz.cycles.sample_clamp_direct, sz.cycles.sample_clamp_indirect))
        if 'kappung' not in argv:
            sz.cycles.sample_clamp_direct = 0.0
            sz.cycles.sample_clamp_indirect = 0.0
        print('[gleich] Kappung jetzt: direkt %.1f, indirekt %.1f'
              % (sz.cycles.sample_clamp_direct, sz.cycles.sample_clamp_indirect))
    except Exception as f:
        print('[gleich] Kappung nicht gesetzt: %s' % f)

    sz.render.image_settings.file_format = 'OPEN_EXR'
    sz.render.image_settings.color_depth = '32'
    sz.render.image_settings.color_mode = 'RGB'
    # Keine Kurve, keine Belichtung -- linear messbar.
    sz.view_settings.view_transform = 'Raw'
    sz.view_settings.look = 'None'
    sz.view_settings.exposure = 0.0

    if albedo:
        vl = bpy.context.view_layer
        vl.use_pass_diffuse_color = True
        vl.use_pass_glossy_color = True
        vl.use_pass_normal = True
        # Blender 5.x kennt OPEN_EXR_MULTILAYER nicht mehr als Format;
        # mehrlagig ist dort eine Medienart des gewoehnlichen OpenEXR.
        im = sz.render.image_settings
        # Nach dem Umschalten der Medienart ist OPEN_EXR_MULTILAYER dort
        # das einzige zulaessige Format (5.2 meldet es so).
        if hasattr(im, 'media_type'):
            im.media_type = 'MULTI_LAYER_IMAGE'
        im.file_format = 'OPEN_EXR_MULTILAYER'
        im.color_depth = '32'
        for name in KAMERAS:
            if name not in kams:
                continue
            sz.camera = kams[name]
            ziel = os.path.join(AUS, 'cycles-gleich-albedo-%s.exr' % name)
            sz.render.filepath = ziel
            t = time.time()
            bpy.ops.render.render(write_still=True)
            print('[gleich] albedo %s -> %s  (%.0f s)' % (name, ziel, time.time() - t))
        print('[gleich] FERTIG (Albedo)')
        return

    for name in KAMERAS:
        if name not in kams:
            print('[gleich] Kamera fehlt: %s' % name)
            continue
        sz.camera = kams[name]
        vorsatz = 'cycles-gleich-nurhimmel' if nurhimmel else 'cycles-gleich'
        ziel = os.path.join(AUS, '%s-%s.exr' % (vorsatz, name))
        sz.render.filepath = ziel
        t = time.time()
        bpy.ops.render.render(write_still=True)
        print('[gleich] %s -> %s  (%.0f s)' % (name, ziel, time.time() - t))

    if nurhimmel:
        print('[gleich] FERTIG (nur Himmel)')
        return

    # --- ZWEITER DURCHGANG: dieselbe Szene, Scheibe OHNE Spiegelung.
    #
    # WOZU. In Unreal laesst sich das Glasfeld sauber zerlegen:
    #     Dielektrikum gesamt       0,7049
    #     dasselbe ohne Spiegelung  0,1307
    #     Spiegelanteil             0,5742
    # Fuer die Vorlage fehlt dieselbe Zerlegung, und ohne sie laesst sich
    # nicht sagen, ob Unreals Spiegelung zu stark ist oder Unreals
    # Durchblick zu hell. Ein zweiter Lauf mit Brechungsindex 1,0 (keine
    # Grenzflaeche, also weder Fresnel noch Ablenkung) liefert den reinen
    # Durchblick der Vorlage -- und damit beide Zahlen zum Vergleichen.
    if glas_ohne_spiegelung():
        for name in KAMERAS:
            if name not in kams:
                continue
            sz.camera = kams[name]
            ziel = os.path.join(AUS, 'cycles-gleich-nurdurch-%s.exr' % name)
            sz.render.filepath = ziel
            t = time.time()
            bpy.ops.render.render(write_still=True)
            print('[gleich] nurdurch %s -> %s  (%.0f s)'
                  % (name, ziel, time.time() - t))

    print('[gleich] FERTIG')


haupt()
