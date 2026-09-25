# -*- coding: utf-8 -*-
"""
villa_echt.py — Die Ebene, an der sich Rendering und Foto unterscheiden.

Nach dem Reality Check des Skills "maximum-realism-visual-fidelity" fehlten
an der Villa genau die Dinge, die CGI verraten, ohne dass man sie benennen
kann. Dieses Modul traegt sie nach. Jede Massnahme hat eine Ursache aus der
Wirklichkeit -- das ist die Bedingung, unter der eine Unregelmaessigkeit
besser ist als keine.

WAS HIER PASSIERT UND WARUM

1. RAUHEITSVARIATION
   "Rauheit ist das staerkste Realismus-Werkzeug nach dem Licht. Eine
   Oberflaeche mit einheitlicher Roughness sieht aus wie Plastik, auch wenn
   die Farbe perfekt ist." Jedes Material bekommt deshalb ein Rauschen auf
   die Rauheit, in Objektkoordinaten, damit es ueber Kanten weiterlaeuft.

2. KANTENABRIEB
   Die Pointiness der Geometrie liefert eine Maske der konvexen Kanten.
   Dort ist jede reale Oberflaeche glatter (Abrieb) und heller (Material
   durchgescheuert). Gebrochen mit Rauschen, sonst sieht die Maske
   generiert aus.

3. SCHMUTZ IN VERTIEFUNGEN
   Umgebungsverdeckung als Maske: Wo wenig Licht hinkommt, kommt auch wenig
   Regen hin und viel Staub. Wirkt zuerst auf die Rauheit, dann erst auf
   die Farbe -- meistens reicht die Rauheit.

4. STAUB OBEN
   Die Z-Komponente der Normalen: Waagerechte Flaechen nach oben tragen
   Staub, senkrechte nicht, Unterseiten gar nicht. Richtung zaehlt.

5. SPRITZWASSER UNTEN
   Die untersten 35 cm jeder Aussenwand sind dunkler und matter. Ursache:
   Regen, der vom Boden zurueckspritzt. Ueber die Weltkoordinate Z.

Dosierung nach der Regel aus dem Skill: Ebene abschalten, und wenn das Bild
dann steril wirkt, war die Dosis richtig. Wirkt es MIT der Ebene schmutzig,
halbieren.
"""

import bpy


def _knoten(mat):
    nt = mat.node_tree
    bsdf = None
    for n in nt.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            bsdf = n
    return nt, bsdf


def _rauschen(nt, skala, detail=6.0, rauheit=0.6, koord='Object'):
    tex = nt.nodes.new('ShaderNodeTexCoord')
    n = nt.nodes.new('ShaderNodeTexNoise')
    n.inputs['Scale'].default_value = skala
    n.inputs['Detail'].default_value = detail
    n.inputs['Roughness'].default_value = rauheit
    nt.links.new(tex.outputs[koord], n.inputs['Vector'])
    return n


def _kurve(nt, quelle, a, b):
    """Eine Rampe, die den Bereich [a, b] auf [0, 1] spreizt."""
    r = nt.nodes.new('ShaderNodeValToRGB')
    r.color_ramp.elements[0].position = a
    r.color_ramp.elements[1].position = b
    nt.links.new(quelle, r.inputs['Fac'])
    return r


def _rechnen(nt, art, a, b, klemmen=False):
    m = nt.nodes.new('ShaderNodeMath')
    m.operation = art
    m.use_clamp = klemmen
    if not hasattr(a, 'bl_idname'):
        m.inputs[0].default_value = a
    else:
        nt.links.new(a, m.inputs[0])
    if not hasattr(b, 'bl_idname'):
        m.inputs[1].default_value = b
    else:
        nt.links.new(b, m.inputs[1])
    return m


def rauheit_variieren(mat, staerke=0.13, skala=9.0):
    """Punkt 1. Nimmt die vorhandene Rauheit als Mittelwert und laesst sie
    darum schwanken."""
    nt, b = _knoten(mat)
    if b is None:
        return
    grund = b.inputs['Roughness'].default_value
    if b.inputs['Roughness'].is_linked:
        return          # Material bringt schon eine eigene Rauheitskarte mit
    n = _rauschen(nt, skala, 7.0, 0.62)
    mitte = _rechnen(nt, 'SUBTRACT', n.outputs['Fac'], 0.5)
    breit = _rechnen(nt, 'MULTIPLY', mitte.outputs[0], staerke * 2.0)
    summe = _rechnen(nt, 'ADD', breit.outputs[0], grund, klemmen=True)
    nt.links.new(summe.outputs[0], b.inputs['Roughness'])
    return summe


def kanten_abrieb(mat, staerke=0.55, aufhellen=0.10):
    """Punkt 2. Pointiness liefert die konvexen Kanten."""
    nt, b = _knoten(mat)
    if b is None:
        return
    geo = nt.nodes.new('ShaderNodeNewGeometry')
    ramp = _kurve(nt, geo.outputs['Pointiness'], 0.52, 0.62)
    stoer = _rauschen(nt, 26.0, 6.0, 0.7)
    maske = _rechnen(nt, 'MULTIPLY', ramp.outputs['Color'], stoer.outputs['Fac'])
    maske = _rechnen(nt, 'MULTIPLY', maske.outputs[0], staerke, klemmen=True)

    # Kante glatter: Rauheit dort herunterziehen
    if b.inputs['Roughness'].is_linked:
        quelle = b.inputs['Roughness'].links[0].from_socket
        misch = nt.nodes.new('ShaderNodeMix')
        misch.data_type = 'FLOAT'
        misch.inputs[3].default_value = 0.14
        nt.links.new(maske.outputs[0], misch.inputs['Factor'])
        nt.links.new(quelle, misch.inputs[2])
        nt.links.new(misch.outputs[0], b.inputs['Roughness'])

    # Kante heller: das Material ist dort durchgescheuert
    if aufhellen > 0 and b.inputs['Base Color'].is_linked:
        farbe = b.inputs['Base Color'].links[0].from_socket
        hell = nt.nodes.new('ShaderNodeMix')
        hell.data_type = 'RGBA'
        hell.blend_type = 'SCREEN'
        hell.inputs[7].default_value = (aufhellen, aufhellen, aufhellen, 1.0)
        nt.links.new(maske.outputs[0], hell.inputs['Factor'])
        nt.links.new(farbe, hell.inputs[6])
        nt.links.new(hell.outputs[2], b.inputs['Base Color'])
    return maske


def staub_oben(mat, staerke=0.20):
    """Punkt 4. Nur nach oben zeigende Flaechen."""
    nt, b = _knoten(mat)
    if b is None:
        return
    geo = nt.nodes.new('ShaderNodeNewGeometry')
    trenn = nt.nodes.new('ShaderNodeSeparateXYZ')
    nt.links.new(geo.outputs['Normal'], trenn.inputs['Vector'])
    ramp = _kurve(nt, trenn.outputs['Z'], 0.55, 0.95)
    wolke = _rauschen(nt, 3.4, 5.0, 0.6, koord='Generated')
    maske = _rechnen(nt, 'MULTIPLY', ramp.outputs['Color'], wolke.outputs['Fac'])
    maske = _rechnen(nt, 'MULTIPLY', maske.outputs[0], staerke, klemmen=True)
    if b.inputs['Roughness'].is_linked:
        quelle = b.inputs['Roughness'].links[0].from_socket
        misch = nt.nodes.new('ShaderNodeMix')
        misch.data_type = 'FLOAT'
        misch.inputs[3].default_value = 0.95       # Staub ist stumpf
        nt.links.new(maske.outputs[0], misch.inputs['Factor'])
        nt.links.new(quelle, misch.inputs[2])
        nt.links.new(misch.outputs[0], b.inputs['Roughness'])
    return maske


def spritzwasser(mat, hoehe=0.36, dunkler=0.62):
    """Punkt 5. Die untersten Zentimeter jeder Aussenwand."""
    nt, b = _knoten(mat)
    if b is None or not b.inputs['Base Color'].is_linked:
        return
    tex = nt.nodes.new('ShaderNodeTexCoord')
    trenn = nt.nodes.new('ShaderNodeSeparateXYZ')
    nt.links.new(tex.outputs['Generated'], trenn.inputs['Vector'])
    # Generated laeuft 0..1 ueber die Bounding Box -- fuer Waende genuegt das
    ramp = nt.nodes.new('ShaderNodeValToRGB')
    ramp.color_ramp.elements[0].position = 0.0
    ramp.color_ramp.elements[1].position = 0.085
    ramp.color_ramp.elements[0].color = (1, 1, 1, 1)
    ramp.color_ramp.elements[1].color = (0, 0, 0, 1)
    nt.links.new(trenn.outputs['Z'], ramp.inputs['Fac'])
    stoer = _rauschen(nt, 5.5, 7.0, 0.72)
    maske = _rechnen(nt, 'MULTIPLY', ramp.outputs['Color'], stoer.outputs['Fac'])
    maske = _rechnen(nt, 'MULTIPLY', maske.outputs[0], 0.9, klemmen=True)

    farbe = b.inputs['Base Color'].links[0].from_socket
    dunkel = nt.nodes.new('ShaderNodeMix')
    dunkel.data_type = 'RGBA'
    dunkel.blend_type = 'MULTIPLY'
    dunkel.inputs[7].default_value = (dunkler, dunkler * 0.97, dunkler * 0.92, 1.0)
    nt.links.new(maske.outputs[0], dunkel.inputs['Factor'])
    nt.links.new(farbe, dunkel.inputs[6])
    nt.links.new(dunkel.outputs[2], b.inputs['Base Color'])
    return maske


def glas_echt(mat):
    """Punkt 8 der Pruefliste: Glas hat Dicke, Toenung und ist nie sauber.

    Die Dicke steckt in der Geometrie (zwei Scheiben, siehe villa.py). Hier
    kommt die Toenung -- Kalk-Natron-Glas ist an der Kante sichtbar gruen --
    und die Mikroverschmutzung: eine leichte Rauheitsvariation plus ein
    Staubsaum am unteren Rand. Perfekt sauberes Glas gibt es nur im
    Rendering."""
    nt, b = _knoten(mat)
    if b is None:
        return
    b.inputs['Base Color'].default_value = (0.86, 0.94, 0.90, 1.0)
    b.inputs['IOR'].default_value = 1.52
    n = _rauschen(nt, 42.0, 5.0, 0.55)
    mitte = _rechnen(nt, 'SUBTRACT', n.outputs['Fac'], 0.5)
    fein = _rechnen(nt, 'MULTIPLY', mitte.outputs[0], 0.035)
    grund = _rechnen(nt, 'ADD', fein.outputs[0], 0.014, klemmen=True)

    # Staubsaum unten: Regen und Sprueher treffen das untere Drittel
    tex = nt.nodes.new('ShaderNodeTexCoord')
    trenn = nt.nodes.new('ShaderNodeSeparateXYZ')
    nt.links.new(tex.outputs['Generated'], trenn.inputs['Vector'])
    ramp = nt.nodes.new('ShaderNodeValToRGB')
    ramp.color_ramp.elements[0].position = 0.0
    ramp.color_ramp.elements[1].position = 0.22
    ramp.color_ramp.elements[0].color = (1, 1, 1, 1)
    ramp.color_ramp.elements[1].color = (0, 0, 0, 1)
    nt.links.new(trenn.outputs['Z'], ramp.inputs['Fac'])
    schleier = _rauschen(nt, 16.0, 6.0, 0.7)
    saum = _rechnen(nt, 'MULTIPLY', ramp.outputs['Color'], schleier.outputs['Fac'])
    saum = _rechnen(nt, 'MULTIPLY', saum.outputs[0], 0.10, klemmen=True)
    gesamt = _rechnen(nt, 'ADD', grund.outputs[0], saum.outputs[0], klemmen=True)
    nt.links.new(gesamt.outputs[0], b.inputs['Roughness'])
    return gesamt


def blatt_durchleuchten(mat, staerke=0.22):
    """Gegenlicht ist das Merkmal echter Vegetation. Ein Blatt, das kein
    Licht durchlaesst, ist ein gruener Karton."""
    nt, b = _knoten(mat)
    if b is None:
        return
    for name in ('Subsurface Weight', 'Transmission Weight'):
        if name in b.inputs:
            b.inputs[name].default_value = staerke
            if name == 'Subsurface Weight' and 'Subsurface Radius' in b.inputs:
                b.inputs['Subsurface Radius'].default_value = (0.012, 0.022, 0.008)
            break
    return b


# Welches Material bekommt was. Die Zuordnung folgt der Ursachenliste:
# Spritzwasser nur an Aussenbauteilen, Staub nur dort, wo waagerechte
# Flaechen im Bild sind, Kantenabrieb ueberall, wo jemand hinfasst oder
# etwas anstoesst.
PLAN = {
    'Sichtbeton':     dict(rauheit=0.11, kante=0.45, staub=0.22, spritz=True),
    'Putz_Weiss':     dict(rauheit=0.10, kante=0.35, staub=0.18, spritz=True),
    'Eiche_Lamelle':  dict(rauheit=0.14, kante=0.70, staub=0.14),
    'Eiche_Diele':    dict(rauheit=0.16, kante=0.55, staub=0.10),
    'Travertin':      dict(rauheit=0.13, kante=0.40, staub=0.20, spritz=True),
    'Naturstein':     dict(rauheit=0.12, kante=0.45, staub=0.16),
    'Poolstein':      dict(rauheit=0.09, kante=0.20),
    'Alu_Anthrazit':  dict(rauheit=0.09, kante=0.35, staub=0.24),
    'Keramik':        dict(rauheit=0.07, kante=0.30),
    'Polsterstoff':   dict(rauheit=0.10, kante=0.25),
    'Teppich':        dict(rauheit=0.08),
    'Bettwaesche':    dict(rauheit=0.12, kante=0.20),
    'Kies':           dict(rauheit=0.10),
    'Estrich':        dict(rauheit=0.12, staub=0.20),
    'Kunstwerk':      dict(rauheit=0.06, kante=0.20),
    'Akzent':         dict(rauheit=0.10, kante=0.22),
}


def veredeln():
    """Alles anwenden. Nach villa_material.anwenden() aufrufen, weil die
    Funktionen hier an die vorhandenen Knoten anknuepfen."""
    getan = []
    for name, p in PLAN.items():
        mat = bpy.data.materials.get(name)
        if mat is None:
            continue
        if p.get('rauheit'):
            rauheit_variieren(mat, p['rauheit'])
        if p.get('kante'):
            kanten_abrieb(mat, p['kante'])
        if p.get('staub'):
            staub_oben(mat, p['staub'])
        if p.get('spritz'):
            spritzwasser(mat)
        getan.append(name)
    g = bpy.data.materials.get('Glas')
    if g:
        glas_echt(g)
        getan.append('Glas')
    # Olivenlaub seit dem 21.09.2026: die Kuebelbaeume (villa_aussen.kuebel).
    for n in ('Blattgruen', 'Zypresse', 'Olivenlaub'):
        b = bpy.data.materials.get(n)
        if b:
            rauheit_variieren(b, 0.14, 12.0)
            blatt_durchleuchten(b)
            getan.append(n)
    return getan
