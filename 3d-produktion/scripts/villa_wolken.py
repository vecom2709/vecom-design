# -*- coding: utf-8 -*-
"""
villa_wolken.py -- Himmel mit Wolken und Luftperspektive, die nichts kostet.

ZWEI PUNKTE AUS DER PRUEFLISTE, DIE NOCH OFFEN WAREN

1. WOLKEN
   Ein Himmel ohne eine einzige Wolke ist moeglich, aber er ist auch das
   Erste, was an einem gerechneten Bild auffaellt: Der Verlauf ist zu
   sauber, und ueber der Dachkante steht eine makellose Flaeche. Die
   Wolken kommen hier NICHT aus einem Volumen. Ein Wolkenvolumen ueber
   einer 500-m-Szene kostet Minuten je Bild und bringt bei dieser
   Motivgroesse nichts, was man im fertigen Bild sehen wuerde.
   Statt dessen: eine prozedurale Wolkenschicht IM Welt-Shader, auf eine
   gedachte Ebene in 1800 m Hoehe projiziert. Sie kostet nichts, sie
   verdeckt die Sonne nicht (das waere ein anderer Lichtaufbau), und sie
   gibt dem Himmel etwas, woran das Auge Groesse ablesen kann.

2. LUFTPERSPEKTIVE -- DIESMAL RICHTIG
   Der erste Versuch war ein Volume Scatter in einem 520 m grossen
   Quader. Gemessen am 17.09.2026: Das Bild wurde rund anderthalb
   Blenden dunkler, der Beton beigegrau, die Schatten verschwanden. Der
   Grund ist einfach: Ein Kamerastrahl zum Himmel legt 520 m im Medium
   zurueck, waehrend die Szene selbst nur 30 m tief ist. Das Volumen
   daempft also vor allem das LICHT, nicht die FERNE.

   Hier laeuft die Luftperspektive statt dessen im Compositor ueber den
   Tiefenpass. Sie greift NACH dem Rendern, kann also per Definition kein
   Licht wegnehmen, und ihre Staerke haengt genau an dem, woran sie
   haengen soll: an der Entfernung. Die Huegel in 200 m bekommen Dunst,
   das Haus in 30 m fast keinen, und die Belichtung bleibt, wie sie
   gemessen wurde.

   Das ist kein Trick, sondern dieselbe Physik in der billigeren
   Reihenfolge: Streuung entlang der Sichtlinie ist ein Mischen zur
   Himmelsfarbe mit der Strecke als Faktor.
"""

import bpy


# Die Dunstfarbe ist NICHT geraten: Sie ist der gemessene Himmelston
# knapp ueber dem Horizont in dieser Szene (linear, vor der Tonwertkurve).
# Wer hier ein neutrales Grau nimmt, bekommt graue Huegel unter blauem
# Himmel -- und das sieht falscher aus als gar kein Dunst.
DUNST = (0.42, 0.55, 0.78)

# Ab 60 m faengt es an, bei 320 m ist der eingestellte Hoechstwert
# erreicht. Kein Dunst bis zum Anschlag: 0,45 heisst, dass auch der
# fernste Huegel noch als Form lesbar bleibt.
NAH = 60.0
FERN = 320.0
HOECHST = 0.45


def wolken(deckung=0.20, hoehe=1800.0, groesse=1500.0, schaerfe=0.34):
    """Prozedurale Wolkenschicht im Welt-Shader.

    deckung  0 = klar, 1 = geschlossen. 0,20 sind einzelne Wolken an einem
             sonst klaren Sommernachmittag.
    hoehe    Wolkenuntergrenze in Metern. 1800 m passt zu Cumulus humilis.
    groesse  Durchmesser einer typischen Wolke in Metern.

    DREI VERSUCHE, ZWEIMAL ZU WEIT GEGANGEN
    2600 m / Deckung 0,34: ueber dem Haus war nichts und am Horizont ein
    Streifen. Grund ist die Projektion -- senkrecht nach oben liegt der
    Ursprung der gedachten Wolkenebene, und bei 2600 m deckt EINE Wolke
    den halben sichtbaren Himmel ab; man steht mitten darin.
    900 m / Deckung 0,44: das Gegenteil, ein geschlossener, zum Horizont
    hin gestreifter Teppich. Das las sich als Textur, nicht als Himmel --
    und eine schlechte Wolkenschicht ist schlechter als gar keine.
    1500 m / Deckung 0,20: einzelne Wolken, die zum Horizont hin
    ausblenden. Dass sie dort verschwinden, ist kein Trick gegen das
    Streifenproblem, sondern richtig: Flach von unten gesehen verschmelzen
    Wolken mit dem Dunst, und genau dort setzt die Luftperspektive an.
    """
    w = bpy.context.scene.world
    if w is None or w.node_tree is None:
        return None
    nt = w.node_tree
    sky = next((n for n in nt.nodes if n.type == 'TEX_SKY'), None)
    bg = next((n for n in nt.nodes if n.type == 'BACKGROUND'), None)
    if sky is None or bg is None:
        return None

    # Bereits vorhandene Wolken entfernen, damit ein zweiter Aufruf nicht
    # zwei Schichten uebereinanderlegt.
    for n in list(nt.nodes):
        if n.label == 'Wolken':
            nt.nodes.remove(n)

    def neu(art):
        n = nt.nodes.new(art)
        n.label = 'Wolken'
        return n

    # Blickrichtung. Im Welt-Shader liefert Texture Coordinate > Generated
    # den normierten Sichtstrahl.
    koord = neu('ShaderNodeTexCoord')
    trenn = neu('ShaderNodeSeparateXYZ')
    nt.links.new(koord.outputs['Generated'], trenn.inputs['Vector'])

    # Projektion auf eine waagerechte Ebene in Wolkenhoehe:
    #   u = x / z * hoehe,  v = y / z * hoehe
    # Nahe am Horizont geht z gegen null und u,v gehen gegen unendlich --
    # genau das ist richtig, denn dort sieht man die Wolken von der Seite
    # und sie ruecken zusammen. Damit daraus kein Rauschen wird, wird z
    # nach unten begrenzt.
    zmin = neu('ShaderNodeMath')
    zmin.operation = 'MAXIMUM'
    zmin.inputs[1].default_value = 0.045
    nt.links.new(trenn.outputs['Z'], zmin.inputs[0])

    teil = neu('ShaderNodeVectorMath')
    teil.operation = 'DIVIDE'
    verb = neu('ShaderNodeCombineXYZ')
    nt.links.new(trenn.outputs['X'], verb.inputs['X'])
    nt.links.new(trenn.outputs['Y'], verb.inputs['Y'])
    verb.inputs['Z'].default_value = 1.0
    nt.links.new(verb.outputs['Vector'], teil.inputs[0])

    zvec = neu('ShaderNodeCombineXYZ')
    nt.links.new(zmin.outputs[0], zvec.inputs['X'])
    nt.links.new(zmin.outputs[0], zvec.inputs['Y'])
    zvec.inputs['Z'].default_value = 1.0
    nt.links.new(zvec.outputs['Vector'], teil.inputs[1])

    skal = neu('ShaderNodeVectorMath')
    skal.operation = 'SCALE'
    skal.inputs['Scale'].default_value = hoehe / groesse
    nt.links.new(teil.outputs['Vector'], skal.inputs[0])

    # Zwei Rauschebenen: die Form der Wolke und ihr ausgefranster Rand.
    gross = neu('ShaderNodeTexNoise')
    gross.inputs['Scale'].default_value = 1.0
    gross.inputs['Detail'].default_value = 8.0
    gross.inputs['Roughness'].default_value = 0.56
    nt.links.new(skal.outputs['Vector'], gross.inputs['Vector'])

    fein = neu('ShaderNodeTexNoise')
    fein.inputs['Scale'].default_value = 6.5
    fein.inputs['Detail'].default_value = 9.0
    fein.inputs['Roughness'].default_value = 0.62
    nt.links.new(skal.outputs['Vector'], fein.inputs['Vector'])

    misch = neu('ShaderNodeMix')
    misch.data_type = 'FLOAT'
    misch.inputs[0].default_value = 0.26
    nt.links.new(gross.outputs['Fac'], misch.inputs[2])
    nt.links.new(fein.outputs['Fac'], misch.inputs[3])

    # Schwelle: aus dem Rauschen wird eine Wolkenkante.
    kante = neu('ShaderNodeValToRGB')
    mitte = 1.0 - deckung
    kante.color_ramp.elements[0].position = max(0.0, mitte - schaerfe * 0.5)
    kante.color_ramp.elements[1].position = min(1.0, mitte + schaerfe * 0.5)
    nt.links.new(misch.outputs[0], kante.inputs['Fac'])

    # AUSBLENDEN ZUM HORIZONT. Unter dem Horizont gar keine Wolken (sonst
    # liegt eine zweite Schicht unter dem Gelaende und blitzt am Bildrand
    # durch), und in den untersten 20 Grad ein Verlauf auf null. Dort
    # sieht man Wolken flach von unten; sie verschmelzen mit dem Dunst,
    # und die Projektion zieht sie ohnehin zu Streifen.
    oben = neu('ShaderNodeMath')
    oben.operation = 'MAXIMUM'
    oben.inputs[1].default_value = 0.0
    nt.links.new(trenn.outputs['Z'], oben.inputs[0])

    weich = neu('ShaderNodeMath')
    weich.operation = 'MULTIPLY'
    # 3,0 hiess: voll erst ab 19 Grad Hoehe. Die Gartenkamera steht
    # waagerecht mit 35 mm, ihr oberer Bildrand liegt bei rund 19 Grad --
    # die Wolken lagen also genau ausserhalb des Bildes. Gemessen am
    # Bildrand, nicht geschaetzt. 5,5 bringt sie ab etwa 10 Grad voll.
    weich.inputs[1].default_value = 5.5
    nt.links.new(oben.outputs[0], weich.inputs[0])

    grenze = neu('ShaderNodeClamp')
    nt.links.new(weich.outputs[0], grenze.inputs['Value'])

    maske = neu('ShaderNodeMath')
    maske.operation = 'MULTIPLY'
    nt.links.new(kante.outputs['Color'], maske.inputs[0])
    nt.links.new(grenze.outputs[0], maske.inputs[1])

    # Die Wolke ist heller als der Himmel, aber nicht weiss: Sie wird von
    # unten vom Boden und von der Seite von der tiefen Sonne angestrahlt.
    # Ein reines Weiss ueber blauem Himmel ist der klassische Aufkleber.
    # 1,22 war zu hell: Die Wolken lagen als weisse Decke ueber dem Blau.
    # 1,04 ist knapp ueber dem Zenithimmel -- sichtbar, ohne das Bild
    # nach oben zu ziehen.
    wolkenfarbe = neu('ShaderNodeRGB')
    wolkenfarbe.outputs[0].default_value = (1.04, 1.01, 0.96, 1.0)

    ueber = neu('ShaderNodeMix')
    ueber.data_type = 'RGBA'
    ueber.blend_type = 'MIX'
    nt.links.new(maske.outputs[0], ueber.inputs['Factor'])
    nt.links.new(sky.outputs[0], ueber.inputs[6])
    nt.links.new(wolkenfarbe.outputs[0], ueber.inputs[7])
    nt.links.new(ueber.outputs[2], bg.inputs[0])
    return ueber


def luftperspektive_compositor(staerke=1.0, nah=NAH, fern=FERN,
                               hoechst=HOECHST, farbe=DUNST):
    """Dunst ueber den Tiefenpass -- nach dem Rendern, also ohne Lichtverlust.

    Siehe den Kopf dieser Datei: Der Volumenversuch kostete anderthalb
    Blenden und loeschte die Schatten. Diese Fassung kann das gar nicht,
    weil sie erst laeuft, wenn das Licht schon gerechnet ist.

    DER COMPOSITOR IN BLENDER 5.2
    'Scene.node_tree' gibt es nicht mehr; der Compositor ist eine
    Knotengruppe an 'Scene.compositing_node_group', und die alten Knoten
    Composite, MixRGB, MapRange und Math sind daraus verschwunden. An
    ihrer Stelle stehen der Gruppenausgang und die allgemeinen
    Shader-Knoten, die jetzt in jedem Baum laufen. Abgefragt statt
    geraten -- siehe scripts/pruef_compositor.py.
    """
    sz = bpy.context.scene
    vl = bpy.context.view_layer
    vl.use_pass_z = True

    alt = bpy.data.node_groups.get('VillaDunst')
    if alt is not None:
        sz.compositing_node_group = None
        bpy.data.node_groups.remove(alt)
    if staerke <= 0.0:
        return None

    ng = bpy.data.node_groups.new('VillaDunst', 'CompositorNodeTree')
    ng.interface.new_socket(name='Image', in_out='OUTPUT',
                            socket_type='NodeSocketColor')
    sz.compositing_node_group = ng

    ebenen = ng.nodes.new('CompositorNodeRLayers')
    ebenen.scene = sz
    aus = ng.nodes.new('NodeGroupOutput')

    tiefe = ebenen.outputs.get('Depth') or ebenen.outputs.get('Z')
    if tiefe is None:
        # Ohne Tiefenpass kein Dunst -- dann geht das Bild unveraendert
        # durch. Lieber kein Effekt als ein falscher.
        ng.links.new(ebenen.outputs['Image'], aus.inputs[0])
        return None

    def bereich_knoten(von, bis, nach_min, nach_max):
        n = ng.nodes.new('ShaderNodeMapRange')
        for feld in ('clamp', 'use_clamp'):
            if hasattr(n, feld):
                setattr(n, feld, True)
        n.inputs['From Min'].default_value = von
        n.inputs['From Max'].default_value = bis
        n.inputs['To Min'].default_value = nach_min
        n.inputs['To Max'].default_value = nach_max
        ng.links.new(tiefe, n.inputs['Value'])
        return n

    # Tiefe -> Mischfaktor.
    weite = bereich_knoten(nah, fern, 0.0, hoechst * staerke)

    # Der Himmel selbst bekommt KEINEN Dunst -- er IST der Dunst. Seine
    # Tiefe ist riesig (1e10), also wird er ueber eine zweite Schwelle
    # wieder ausmaskiert. Ohne das legt sich ein Grauschleier ueber den
    # ganzen Hintergrund und der Himmel verliert seine Farbe.
    himmel = bereich_knoten(fern * 2.0, fern * 3.0, 1.0, 0.0)

    fak = ng.nodes.new('ShaderNodeMath')
    fak.operation = 'MULTIPLY'
    ng.links.new(weite.outputs['Result'], fak.inputs[0])
    ng.links.new(himmel.outputs['Result'], fak.inputs[1])

    misch = ng.nodes.new('ShaderNodeMix')
    misch.data_type = 'RGBA'
    misch.blend_type = 'MIX'
    misch.inputs[7].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
    ng.links.new(fak.outputs[0], misch.inputs['Factor'])
    ng.links.new(ebenen.outputs['Image'], misch.inputs[6])
    ng.links.new(misch.outputs[2], aus.inputs[0])
    return misch


def alles(deckung=0.34, dunst=1.0):
    ergebnis = {}
    try:
        ergebnis['wolken'] = wolken(deckung=deckung) is not None
    except Exception as f:
        ergebnis['wolken'] = 'FEHLER: %s' % f
    try:
        ergebnis['dunst'] = luftperspektive_compositor(staerke=dunst) is not None
    except Exception as f:
        ergebnis['dunst'] = 'FEHLER: %s' % f
    return ergebnis
