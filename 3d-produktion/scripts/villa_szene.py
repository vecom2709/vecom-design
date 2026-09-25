# -*- coding: utf-8 -*-
"""
villa_szene.py — Licht, Himmel, Kameras, Renderwerte.

DIE STUNDE ENTSCHEIDET
Architektur wird nicht mittags fotografiert. Bei hoher Sonne steht alles
flach und weiss da -- genau das Bild, das beim ersten Versuch herauskam.
Hier steht die Sonne auf 22 Grad aus Suedwest: lange Schatten ueber die
Terrasse, Streiflicht auf die Auskragung, und die Nordseite liegt im
kuehlen Himmelslicht. Der Kontrast zwischen beiden ist der Grund, warum
man Tiefe sieht.

BELICHTUNG
AgX entsaettigt Lichter kraeftig. Wer die Belichtung auf 0 laesst, bekommt
creme statt Eiche und weiss statt Beton -- auch das war beim ersten Versuch
so. Eine Blende runter, dafuer mehr Kontrast im Look: die Farben kommen
zurueck, ohne dass die Fassade absaeuft.
"""

import bpy
import math
import mathutils

# DIE WICHTIGSTE MESSUNG DIESES PROJEKTS (17.09.2026)
#
# Dasselbe Bild dreimal, jedes Mal nur eine Lichtquelle:
#     Rasen nur Himmel      0,132
#     Rasen nur Sonnenlampe 0,045   (Sonnenlampe auf 4,9 W/m2)
#     Fassade nur Sonne     0,000
# Der Himmel gab dem Boden DREIMAL so viel Licht wie die Sonne. In der
# Wirklichkeit ist es umgekehrt -- direktes Sonnenlicht liefert auf eine
# waagerechte Flaeche das Fuenf- bis Achtfache des Himmelslichts.
#
# Damit war klar, warum kein einziges Bild einen Schlagschatten hatte: Die
# Sonne war praktisch nicht vorhanden. Vier Durchgaenge mit verschiedenen
# Azimuten hatten das nicht aufgedeckt, weil sie alle dieselbe zu schwache
# Sonne drehten -- man kann die Richtung einer Lichtquelle nicht messen,
# die nicht leuchtet.
#
# DIE LOESUNG: EIN MODELL STATT ZWEIER ZAHLEN
# Zwei Regler, die physikalisch zusammengehoeren, von Hand aufeinander
# abzustimmen, geht schief. Der Himmel von Blender (MULTIPLE_SCATTERING)
# rechnet Sonnenscheibe UND Streulicht aus derselben Atmosphaere. Das
# Verhaeltnis stimmt dann von selbst, und es bleibt eine einzige Zahl zu
# waehlen: die Belichtung.
#
# AZIMUT  249 Grad, gewaehlt aus 205, 225, 249 und 270 im selben Lauf --
#         und zwar erst, NACHDEM die Sonne wirklich schien. Der Vergleich
#         davor war wertlos: Man kann die Richtung einer Lichtquelle nicht
#         beurteilen, die nicht leuchtet.
#         Bei 249 steht die Sonne im Westnordwesten. Der obere Baukoerper
#         liegt voll im Licht, das zurueckspringende Erdgeschoss unter der
#         Auskragung im Schatten, der Pool wird tief blau statt blass, und
#         die Zypressenreihe wirft quer ueber den Rasen. Der Unterschied
#         ZWISCHEN zwei Flaechen erzeugt Tiefe, nicht die Helligkeit einer
#         einzelnen.
# HOEHE   21 Grad war zu flach: die Auskragung verschluckte die ganze
#         Terrasse. 27 laesst Sonne bis an die Glasfront und wirft
#         Schatten von ueber 14 m Laenge.
SONNE_HOEHE = 27.0
SONNE_AZIMUT = 249.0
HIMMEL_STAERKE = 1.0
BELICHTUNG = -2.0

# Die Sonnenlampe ist AUS und bleibt es. Sie steht nur noch als Objekt in
# der Szene, damit Werkzeuge, die nach ihr suchen, sie finden -- das Licht
# kommt vollstaendig aus dem Himmelsmodell. Wer sie wieder einschaltet,
# verdoppelt die Sonne und bekommt zwei Schatten.
SONNE_STAERKE = 0.0


def _kamera(name, pos, ziel, lens=32.0, senkrecht=True, blende=8.0):
    """Eine Kamera mit echten Werten.

    SENSOR: Vollformat 36 x 24 mm. Ohne gesetzte Sensorbreite rechnet
    Blender mit seinem Standard, und jede Brennweitenangabe ist dann eine
    andere als die, die auf einem Objektiv steht.

    SENKRECHT: Bei Architektur bleibt die Kamera waagerecht, und der
    Ausschnitt wandert ueber den Shift. Eine gekippte Kamera erzeugt
    stuerzende Linien -- das ist Urlaubsschnappschuss, nicht Architektur.

    TIEFENSCHAERFE: Mit echter Blende, echter Brennweite und echtem
    Fokusabstand. Blende 8 aussen, 5,6 innen. Das ist so wenig Unschaerfe,
    dass man sie erst beim Abschalten vermisst -- und genau so viel, dass
    das Bild aufhoert, wie eine Konstruktionszeichnung zu wirken. Zu viel
    davon erzeugt den Miniatur-Effekt und ist das sicherste Zeichen fuer
    CGI."""
    alt = bpy.data.objects.get(name)
    if alt is not None:
        bpy.data.objects.remove(alt, do_unlink=True)
    cd = bpy.data.cameras.new(name)
    cd.sensor_fit = 'HORIZONTAL'
    cd.sensor_width = 36.0
    cd.sensor_height = 24.0
    cd.lens = lens
    ob = bpy.data.objects.new(name, cd)
    bpy.context.scene.collection.objects.link(ob)
    ob.location = pos
    d = mathutils.Vector(ziel) - mathutils.Vector(pos)
    if senkrecht:
        waag = math.hypot(d.x, d.y)
        ob.rotation_euler = (math.radians(90.0), 0.0,
                             math.atan2(d.y, d.x) - math.radians(90.0))
        cd.shift_y = (d.z / waag) * (lens / cd.sensor_width) * 1.0
    else:
        ob.rotation_euler = d.to_track_quat('-Z', 'Y').to_euler()

    cd.dof.use_dof = True
    cd.dof.focus_distance = max(0.8, d.length)
    cd.dof.aperture_fstop = blende
    cd.dof.aperture_blades = 9
    cd.dof.aperture_rotation = math.radians(11.0)
    return ob


def licht():
    for name in ('Sonne', 'Sonne2'):
        alt = bpy.data.objects.get(name)
        if alt is not None:
            bpy.data.objects.remove(alt, do_unlink=True)
    ld = bpy.data.lights.new('Sonne', type='SUN')
    so = bpy.data.objects.new('Sonne', ld)
    bpy.context.scene.collection.objects.link(so)
    ld.energy = SONNE_STAERKE
    ld.angle = math.radians(0.55)      # scharfe Schatten, spaeter Nachmittag
    ld.color = (1.0, 0.905, 0.775)
    if hasattr(ld, 'use_shadow'):
        ld.use_shadow = True
    so.rotation_euler = (math.radians(90.0 - SONNE_HOEHE), 0.0,
                         math.radians(SONNE_AZIMUT))
    return so


def himmel(staerke=HIMMEL_STAERKE):
    sz = bpy.context.scene
    w = bpy.data.worlds.get('World') or bpy.data.worlds.new('World')
    sz.world = w
    w.use_nodes = True
    nt = w.node_tree
    for n in list(nt.nodes):
        if n.type not in ('OUTPUT_WORLD', 'BACKGROUND'):
            nt.nodes.remove(n)
    bg = nt.nodes.get('Background') or nt.nodes.new('ShaderNodeBackground')
    aus = nt.nodes.get('World Output') or nt.nodes.new('ShaderNodeOutputWorld')
    nt.links.new(bg.outputs[0], aus.inputs[0])
    sky = nt.nodes.new('ShaderNodeTexSky')
    sky.sky_type = 'MULTIPLE_SCATTERING'
    sky.sun_elevation = math.radians(SONNE_HOEHE)
    # Blender zaehlt die Himmelsdrehung ab -Y, die Sonnenlampe dreht um Z
    # und zeigt bei 0 nach unten. Die 90 Grad Versatz halten beide auf
    # demselben Stand -- sonst steht die Scheibe im Bild woanders als der
    # Schatten auf dem Boden, und das faellt sofort auf.
    sky.sun_rotation = math.radians(SONNE_AZIMUT - 90.0)
    # DIE SONNENSCHEIBE IST DIE LICHTQUELLE. Vorher stand sie auf 0,05 und
    # eine getrennte Lampe sollte die Arbeit tun -- siehe die Messung oben.
    sky.sun_disc = True
    sky.sun_intensity = 1.0
    sky.sun_size = math.radians(0.545)   # der wahre Winkeldurchmesser
    # Welche Regler es gibt, haengt am Modell: MULTIPLE_SCATTERING in
    # Blender 5.2 kennt kein dust_density. Deshalb setzen statt annehmen.
    for a, v in (('altitude', 120.0), ('air_density', 1.0),
                 ('dust_density', 2.2), ('ozone_density', 1.4)):
        if hasattr(sky, a):
            setattr(sky, a, v)
    nt.links.new(sky.outputs[0], bg.inputs[0])
    bg.inputs[1].default_value = staerke
    return sky


def sonnenstand(hoehe, azimut):
    """Sonnenstand zur Laufzeit aendern -- Himmel UND Lampe zugleich.

    Es gibt genau eine Stelle, an der der Sonnenstand steht. Wer nur die
    Lampe dreht, dreht den Schatten und laesst die Sonnenscheibe stehen."""
    w = bpy.context.scene.world
    if w and w.node_tree:
        for n in w.node_tree.nodes:
            if n.type == 'TEX_SKY':
                n.sun_elevation = math.radians(hoehe)
                n.sun_rotation = math.radians(azimut - 90.0)
    so = bpy.data.objects.get('Sonne')
    if so is not None:
        so.rotation_euler = (math.radians(90.0 - hoehe), 0.0,
                             math.radians(azimut))
    return hoehe, azimut


def luftperspektive(dichte=0.00020):
    """Punkt 21 der Pruefliste: Tiefe.

    In echter Luft verliert alles mit der Entfernung Kontrast und nimmt die
    Farbe des Himmels an. Ohne das steht der Baum in 200 m Entfernung so
    knackig da wie die Hauswand in 20 m, und das Bild hat keine Tiefe --
    eines der zuverlaessigsten Zeichen fuer eine Kulisse.

    Umgesetzt als Volumenstreuung in einem Quader um die Szene.

    DIE DICHTE IST GEMESSEN, NICHT GESCHAETZT. Der erste Versuch stand auf
    0,0022 bei 180 m Hoehe -- das Sonnenlicht musste durch 180 m Nebel, und
    das ganze Bild wurde zwei Blenden dunkler, der Rasen olivgruen. Jetzt
    0,00042 bei 70 m: ueber die 70 m Hoehe geht knapp 3 Prozent Licht
    verloren (unmerklich), ueber 300 m Sichtweite gut 12 Prozent -- und
    genau das ist Luftperspektive."""
    alt = bpy.data.objects.get('Luft')
    if alt is not None:
        bpy.data.objects.remove(alt, do_unlink=True)
    me = bpy.data.meshes.new('Luft')
    import bmesh
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= 520.0
        v.co.y *= 520.0
        v.co.z = v.co.z * 62.0 + 29.0
    bmesh.ops.reverse_faces(bm, faces=bm.faces[:])
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new('Luft', me)
    bpy.context.scene.collection.objects.link(ob)

    mat = bpy.data.materials.get('Luft') or bpy.data.materials.new('Luft')
    mat.use_nodes = True
    nt = mat.node_tree
    for n in list(nt.nodes):
        if n.type != 'OUTPUT_MATERIAL':
            nt.nodes.remove(n)
    aus = nt.nodes.get('Material Output') or nt.nodes.new('ShaderNodeOutputMaterial')
    vol = nt.nodes.new('ShaderNodeVolumeScatter')
    vol.inputs['Color'].default_value = (0.62, 0.72, 0.86, 1.0)
    vol.inputs['Density'].default_value = dichte
    vol.inputs['Anisotropy'].default_value = 0.35
    nt.links.new(vol.outputs[0], aus.inputs['Volume'])
    ob.data.materials.append(mat)
    ob.visible_camera = False
    ob.visible_shadow = False
    return ob


def render_werte(breite=1920, hoehe=1080, proben=256, motor='BLENDER_EEVEE'):
    sz = bpy.context.scene
    try:
        sz.render.engine = motor
    except TypeError:
        sz.render.engine = 'BLENDER_EEVEE'
    sz.render.resolution_x, sz.render.resolution_y = breite, hoehe
    sz.render.resolution_percentage = 100
    sz.render.film_transparent = False
    sz.view_settings.view_transform = 'AgX'
    try:
        sz.view_settings.look = 'AgX - High Contrast'
    except TypeError:
        sz.view_settings.look = 'None'
    sz.view_settings.exposure = BELICHTUNG
    sz.view_settings.gamma = 1.0
    if sz.render.engine == 'CYCLES':
        sz.cycles.samples = proben
        sz.cycles.use_denoising = True
        sz.cycles.max_bounces = 8
        sz.cycles.transparent_max_bounces = 12
        sz.cycles.caustics_reflective = False
    else:
        e = sz.eevee
        e.taa_render_samples = max(64, proben)
        for a, v in (('use_shadows', True), ('use_raytracing', True),
                     ('shadow_ray_count', 4), ('shadow_step_count', 12),
                     ('use_volumetric_shadows', True)):
            if hasattr(e, a):
                setattr(e, a, v)
        if hasattr(e, 'ray_tracing_options'):
            try:
                e.ray_tracing_options.resolution_scale = '1'
            except Exception:
                pass


# JEDE KAMERA HAT IHRE EIGENE BELICHTUNG.
# Das ist keine Bequemlichkeit, sondern wie fotografiert wird: Zwischen der
# besonnten Fassade und dem Wohnraum dahinter liegen gut drei Blenden. Eine
# einzige Belichtung fuer beide ergibt entweder eine weisse Fassade oder
# einen schwarzen Innenraum -- der erste Cycles-Innenraum am 17.09.2026 war
# genau das, weil er mit der Aussenbelichtung -1,3 gerechnet wurde.
KAMERAS = {
    # Der Blick, der das Haus verkauft: Auskragung ueber der Terrasse,
    # Pool im Vordergrund, Sonne von rechts hinten.
    # BELICHTUNGSLEITER, 19.09.2026, je Kamera drei bis vier Stufen
    # gerechnet und die Flaechen im Bild gemessen. Sollwerte:
    #   besonnter weisser Putz   0,83 bis 0,86 Anzeigeluminanz
    #   Rasen daneben            0,36 bis 0,42   (rund drei Blenden darunter,
    #                                             das entspricht Albedo 0,07
    #                                             gegen 0,73)
    #   beschatteter Putz        0,50 bis 0,62
    # Der Himmel darf DUNKLER sein als die besonnte Wand -- die Annahme,
    # er muesse heller sein, war falsch: Eine weisse Wand in der Sonne hat
    # rund 27.000 cd/m2, der klare Zenit 3.000 bis 6.000.
    #
    # Was die Messung aufgedeckt hat: Bei den alten Werten war der
    # Ankunft-Blick zu 56 Prozent an der Wand und zu 98 Prozent am Holz
    # GEKLIPPT. Ausgebrannte Lichter sind das sicherste Zeichen fuer ein
    # Bild, das niemand belichtet hat.
    'garten':   {'pos': (30.0, 30.5, 4.4), 'ziel': (8.5, 8.0, 3.6),
                 'lens': 35.0, 'bel': -3.10},
    'ankunft':  {'pos': (-7.5, -13.0, 3.2), 'ziel': (7.0, 3.0, 2.6),
                 'lens': 35.0, 'bel': -3.70},
    # Die Terrasse liegt bei Azimut 249 im Schatten des eigenen Hauses.
    # Sie bekommt deshalb knapp eine Blende mehr als der Garten -- nicht
    # aus Geschmack, sondern weil sie nur Himmelslicht sieht.
    'terrasse': {'pos': (17.5, 20.0, 2.6), 'ziel': (7.0, 8.0, 2.6),
                 'lens': 24.0, 'bel': -2.20},
    # Innenraeume: Blick von der Kueche durch den Wohnraum nach Sueden,
    # damit die Glasfront im Bild liegt und nicht im Ruecken.
    # Auch die vier Innenraeume sind gemessen, nicht geschaetzt: je zwei
    # Stufen gerechnet, Decke und Boden ausgewertet, dann auf den Sollwert
    # hochgerechnet (Decke 0,55 bis 0,72, Boden 0,28 bis 0,45 -- die Decke
    # ist die hellste grosse Flaeche eines Raums, der Boden liegt rund
    # anderthalb Blenden darunter).
    #
    # Nach Augenmass hatte derselbe Wohnraum bei 1,60 "richtig belichtet"
    # ausgesehen. Gemessen: Decke 0,247, Boden 0,143, Sofa 0,091 -- also
    # anderthalb Blenden zu dunkel. Genau dafuer wird gemessen.
    #
    # Dass die Fenster dabei ausbrennen, ist kein Fehler: Zwischen
    # Wohnraum und besonntem Garten liegen gut sechs Blenden, und jede
    # Architekturaufnahme entscheidet sich fuer den Raum. Wer das Fenster
    # halten will, braucht zwei Belichtungen -- das waere eine Montage,
    # kein Foto.
    'wohnen':   {'pos': (10.60, 4.90, 1.62), 'ziel': (15.6, 9.6, 1.35),
                 'lens': 20.0, 'bel': 4.00, 'innen': True},
    'essen':    {'pos': (16.40, 3.10, 1.60), 'ziel': (7.6, 1.6, 1.30),
                 'lens': 22.0, 'bel': 3.20, 'innen': True},
    'kueche':   {'pos': (9.10, 10.00, 1.62), 'ziel': (5.8, 5.4, 1.20),
                 'lens': 20.0, 'bel': 3.85, 'innen': True},
    'master':   {'pos': (14.10, 6.10, 5.20), 'ziel': (11.4, 12.4, 4.60),
                 'lens': 21.0, 'bel': 3.90, 'innen': True},
    # GALERIE IST RAUS, aus demselben gemessenen Grund wie das Bad.
    # Erst stand sie bei y 12,30 und blickte nach Norden in die fensterlose
    # Tiefe des Flurs, dann am Suedende ueber die Glasbruestung in den
    # Luftraum. Beide Male: Median der Anzeigeluminanz 0,000. Der Luftraum
    # bekommt sein Licht ueber die Suedverglasung des Erdgeschosses, und
    # bis unter die Decke des Obergeschosses tragen zwei Reflexionen
    # nichts mehr. Statt eine Lampe ohne Quelle zu setzen: Bild faellt weg.
    # BAD IST RAUS. Gemessener Median der Anzeigeluminanz: 0,000 -- das Bad
    # hat ein Schlitzfenster von 1,20 m nach Westen, und um 27 Grad
    # Sonnenhoehe aus Sued kommt dort kein Licht an. Ein Bild, das man nur
    # mit einer Lampe ohne Quelle retten koennte, wird nicht gerechnet.
}


def kameras_setzen():
    raus = {}
    for name, k in KAMERAS.items():
        innen = bool(k.get('innen'))
        ob = _kamera('Kam_' + name, k['pos'], k['ziel'], k['lens'],
                     senkrecht=not innen,
                     blende=k.get('blende', 5.6 if innen else 8.0))
        ob['belichtung'] = k.get('bel', BELICHTUNG)
        raus[name] = ob
    bpy.context.scene.camera = raus['garten']
    return raus


def belichtung_nach_kamera():
    """Vor jedem Bild aufrufen: nimmt die Belichtung, die an der aktiven
    Kamera haengt."""
    sz = bpy.context.scene
    k = sz.camera
    if k is not None and 'belichtung' in k:
        sz.view_settings.exposure = float(k['belichtung'])
    return sz.view_settings.exposure


def alles(motor='BLENDER_EEVEE', breite=1920, hoehe=1080, proben=160,
          luft=False):
    """luft ist AUS, und das ist gemessen, nicht geschaetzt.

    A/B am 17.09.2026, identische Szene, nur das Volumen an/aus: Mit
    Volumen war das Bild rund anderthalb Blenden dunkler, der Beton
    beigegrau statt weiss, der Pool stumpf und die Schatten verschwunden.
    Der Grund ist die Ausdehnung: Ein Kamerastrahl zum Himmel laeuft 520 m
    durch das Medium, waehrend die Szene selbst nur 30 m tief ist. Bei
    dieser Motivgroesse kostet Luftperspektive alles und bringt nichts --
    sie gehoert zu Landschaften, nicht zu einem Haus im Garten.
    luftperspektive() bleibt fuer den Fall, dass spaeter eine Weitsicht
    dazukommt."""
    licht()
    himmel()
    if luft:
        luftperspektive()
    render_werte(breite, hoehe, proben, motor)
    return kameras_setzen()
