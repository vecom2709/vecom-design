# -*- coding: utf-8 -*-
"""
villa_web.py — Die Villa fuer den Browser.

WAS HIER ANDERS IST ALS IM STANDBILD
Im Blender-Bild duerfen Materialien Rauschknoten haben. Ein glTF kann die
nicht mitnehmen: es kennt nur Zahlen und Bilder. Also wird jedes
prozedurale Material auf EINEN Satz Zahlen zurueckgeschrieben (Grundfarbe,
Rauheit, Metall) und die Feinstruktur auf eine kleine Kachel gebacken, die
sich ueber die ganze Flaeche wiederholt.

KEIN DRACO. Die Seite laedt mit einem blanken GLTFLoader, ohne DRACOLoader
-- so wie tisch.glb auch. Ein komprimiertes Netz waere kleiner und wuerde
nicht laden.

WARUM DER URSPRUNG JEDES BAUTEILS UNTEN LIEGT
Damit die Seite das Haus aus dem Grundriss wachsen lassen kann: scale.y
(nach der Y-oben-Drehung des Exports) von 0 auf 1, und die Wand steigt aus
dem Boden statt in beide Richtungen zu wachsen. Das ist in villa.py
festgelegt und darf hier nicht verlorengehen -- deshalb wird NICHT
zusammengefasst und NICHT 'Origin to Geometry' gerufen.
"""

import bpy
import json
import os

ZIEL = 'C:/Users/manue/Desktop/Vecom Design/3d-produktion/web-export'

# Die Werte, mit denen der Browser rechnet. Sie stammen aus den
# prozeduralen Materialien -- gemittelt, nicht geraten.
NETZ_MATERIAL = {
    'Sichtbeton':    {'farbe': (0.437, 0.429, 0.413), 'rauheit': 0.68, 'metall': 0.0},
    'Putz_Weiss':    {'farbe': (0.731, 0.723, 0.709), 'rauheit': 0.76, 'metall': 0.0},
    'Eiche_Lamelle': {'farbe': (0.370, 0.232, 0.116), 'rauheit': 0.44, 'metall': 0.0},
    'Eiche_Diele':   {'farbe': (0.270, 0.156, 0.075), 'rauheit': 0.44, 'metall': 0.0},
    'Alu_Anthrazit': {'farbe': (0.048, 0.050, 0.054), 'rauheit': 0.32, 'metall': 0.85},
    'Glas':          {'farbe': (0.720, 0.800, 0.840), 'rauheit': 0.02, 'metall': 0.0},
    'Travertin':     {'farbe': (0.532, 0.493, 0.425), 'rauheit': 0.42, 'metall': 0.0},
    'Naturstein':    {'farbe': (0.287, 0.282, 0.274), 'rauheit': 0.38, 'metall': 0.0},
    'Poolstein':     {'farbe': (0.054, 0.105, 0.123), 'rauheit': 0.30, 'metall': 0.0},
    'Wasser':        {'farbe': (0.020, 0.115, 0.145), 'rauheit': 0.04, 'metall': 0.0},
    'Rasen':         {'farbe': (0.085, 0.152, 0.045), 'rauheit': 0.92, 'metall': 0.0},
    'Kies':          {'farbe': (0.181, 0.177, 0.169), 'rauheit': 0.88, 'metall': 0.0},
    'Estrich':       {'farbe': (0.502, 0.497, 0.486), 'rauheit': 0.80, 'metall': 0.0},
    'Zypresse':      {'farbe': (0.055, 0.105, 0.048), 'rauheit': 0.90, 'metall': 0.0},
    # Die Einrichtung. Diese Werte sind nicht geschaetzt, sondern am
    # 17.09.2026 aus den gebauten Materialien ausgelesen -- sonst laufen
    # Standbild und Browser mit der Zeit farblich auseinander, und genau
    # das faellt im Register "Foto oder Echtzeit" sofort auf.
    'Polsterstoff':  {'farbe': (0.148, 0.152, 0.148), 'rauheit': 0.88, 'metall': 0.0},
    'Teppich':       {'farbe': (0.230, 0.205, 0.178), 'rauheit': 0.94, 'metall': 0.0},
    'Bettwaesche':   {'farbe': (0.800, 0.790, 0.770), 'rauheit': 0.80, 'metall': 0.0},
    'Akzent':        {'farbe': (0.340, 0.150, 0.088), 'rauheit': 0.86, 'metall': 0.0},
    'Keramik':       {'farbe': (0.900, 0.898, 0.890), 'rauheit': 0.18, 'metall': 0.0},
    'Kunstwerk':     {'farbe': (0.132, 0.148, 0.178), 'rauheit': 0.55, 'metall': 0.0},
    'Leuchtenschirm': {'farbe': (0.920, 0.880, 0.800), 'rauheit': 0.42, 'metall': 0.0},
    'Blattgruen':    {'farbe': (0.048, 0.118, 0.038), 'rauheit': 0.72, 'metall': 0.0},
    # Der Spiegel ist im Browser ein Metall mit fast glatter Oberflaeche.
    # Eine echte Spiegelung kostet einen zweiten Renderdurchgang je Bild;
    # die Umgebungskarte reicht hier voellig.
    'Spiegel':       {'farbe': (0.920, 0.940, 0.950), 'rauheit': 0.03, 'metall': 1.0},
    # Die Gartenmoebel. Sie gehen mit ins Netz, weil das Register
    # "Foto oder Echtzeit" beide Fassungen nebeneinanderstellt: Ein
    # moebliertes Foto neben einem leeren Echtzeitbild vergleicht zwei
    # Szenen statt zweier Verfahren.
    'Leinen':        {'farbe': (0.640, 0.618, 0.575), 'rauheit': 0.88, 'metall': 0.0},
    'Teak':          {'farbe': (0.232, 0.148, 0.078), 'rauheit': 0.52, 'metall': 0.0},
    'Terrakotta':    {'farbe': (0.198, 0.092, 0.052), 'rauheit': 0.78, 'metall': 0.0},
    'Mulch':         {'farbe': (0.062, 0.044, 0.030), 'rauheit': 0.92, 'metall': 0.0},
}

DURCHSICHTIG = {'Glas': 0.16, 'Wasser': 0.78}


def materialien_vereinfachen():
    """Prozedurale Baeume durch einen Principled mit festen Werten ersetzen.

    Der Umweg ueber ein BACKEN waere schoener und kostet 14 Texturen. Bei
    diesem Haus traegt die Form, nicht die Maserung: Der Unterschied ist bei
    Aussenaufnahmen im Browser nicht zu sehen, die 900 KB dagegen schon.
    """
    getan = []
    for name, werte in NETZ_MATERIAL.items():
        mat = bpy.data.materials.get(name)
        if mat is None:
            continue
        mat.use_nodes = True
        nt = mat.node_tree
        for n in list(nt.nodes):
            nt.nodes.remove(n)
        b = nt.nodes.new('ShaderNodeBsdfPrincipled')
        aus = nt.nodes.new('ShaderNodeOutputMaterial')
        nt.links.new(b.outputs[0], aus.inputs[0])
        f = werte['farbe']
        b.inputs['Base Color'].default_value = (f[0], f[1], f[2], 1.0)
        b.inputs['Roughness'].default_value = werte['rauheit']
        b.inputs['Metallic'].default_value = werte['metall']
        if name in DURCHSICHTIG:
            b.inputs['Alpha'].default_value = DURCHSICHTIG[name]
            mat.blend_method = 'BLEND'
        getan.append(name)
    return getan


def fasen_fuers_netz(segmente=1, breite=0.005):
    """Fuer den Browser eine einfache Fase statt der zweisegmentigen.

    Gemessen am 17.09.2026: Mit zwei Segmenten und flacher Schattierung
    wuchs haus.glb von 362 KB auf 3,3 MB -- jede Kante verdreifacht die
    Flaechen, und flach schattierte Flaechen teilen sich keine Punkte. Ein
    Segment kostet ein Drittel davon und ist auf Bildschirmabstand nicht zu
    unterscheiden. Die gerechneten Bilder behalten ihre zwei Segmente; dort
    zaehlt jedes Pixel und keine Kilobyte."""
    n = 0
    for ob in bpy.data.objects:
        for mod in ob.modifiers:
            if mod.type == 'BEVEL':
                mod.segments = segmente
                mod.width = breite
                n += 1
    return n


def ausgeben(datei='haus.glb'):
    os.makedirs(ZIEL, exist_ok=True)
    for ob in bpy.data.objects:
        ob.select_set(ob.type == 'MESH')
    mesh = [o for o in bpy.data.objects if o.type == 'MESH']
    if not mesh:
        raise RuntimeError('keine Meshes')
    bpy.context.view_layer.objects.active = mesh[0]
    pfad = os.path.join(ZIEL, datei)
    bpy.ops.export_scene.gltf(
        filepath=pfad, export_format='GLB',
        # export_apply=True, damit die Fasen mitkommen. Das war frueher
        # gefaehrlich, weil die Bauteile ihre Groesse in der
        # OBJEKTSKALIERUNG trugen; seit quader() echte Netze baut, ist die
        # Skalierung (1, 1, 1) und der Knoten behaelt seine Position.
        use_selection=True, export_apply=True,
        export_draco_mesh_compression_enable=False,
        export_yup=True, export_cameras=False, export_lights=False,
        export_texcoords=False, export_normals=True,
    )
    return pfad, os.path.getsize(pfad)


def manifest(grundriss, groesse, datei='haus.glb'):
    """Das Manifest beschreibt, was der Browser mit dem GLB tun soll.
    Es enthaelt bewusst KEINE Geometrie -- nur Regeln."""
    d = dict(grundriss)
    d['dateien'] = {'geometrie': datei, 'bytes': groesse}
    d['material'] = {k: {'farbe': list(v['farbe']), 'rauheit': v['rauheit'],
                         'metall': v['metall'],
                         'alpha': DURCHSICHTIG.get(k, 1.0)}
                     for k, v in NETZ_MATERIAL.items()}
    # DIE STANDPUNKTE DER GERECHNETEN BILDER.
    # Damit der Browser im Register "Foto oder Echtzeit" die Kamera genau
    # dorthin stellen kann, wo in Blender gerechnet wurde. Ohne diese Werte
    # vergleicht der Besucher zwei ANSICHTEN statt zwei VERFAHREN -- und
    # genau darum geht es in dem Register.
    try:
        import sys
        hier = os.path.dirname(os.path.abspath(__file__))
        if hier not in sys.path:
            sys.path.insert(0, hier)
        p = os.path.join(hier, 'villa_szene.py')
        ns = {'__name__': 'villa_szene_daten', '__file__': p}
        exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
        d['fotostandpunkte'] = {
            name: {'pos': list(k['pos']), 'ziel': list(k['ziel']),
                   'brennweite': k['lens'], 'innen': bool(k.get('innen')),
                   'bild': 'haus-%s.webp' % name}
            for name, k in ns['KAMERAS'].items()
        }
        d['sonne'] = {'hoehe': ns['SONNE_HOEHE'], 'azimut': ns['SONNE_AZIMUT']}
    except Exception as f:
        print('[web] Standpunkte nicht gelesen: %s' % f)

    d['hinweise'] = {
        'draco': 'nicht verwendet -- die Seite laedt mit blankem GLTFLoader',
        'ursprung': 'Jedes Bauteil hat seinen Ursprung UNTEN. Deshalb laesst '
                    'sich das Haus mit scale.y von 0 auf 1 aus dem Grundriss '
                    'hochwachsen, ohne die Geometrie anzufassen.',
        'praefix': 'Die Bauabschnitte stecken im Knotennamen (p01_ bis p08_). '
                   'glTF nimmt Collections nicht mit, Namen schon.',
        'begehung': 'raeume + durchgaenge sind die Flaechen, auf denen sich '
                    'die Kamera bewegen darf. Keine echte Kollision: Ein '
                    'Rechtecktest kostet nichts und faehrt nie durch eine Wand.',
    }
    with open(os.path.join(ZIEL, 'haus-manifest.json'), 'w',
              encoding='utf-8') as f:
        json.dump(d, f, ensure_ascii=False, indent=2, sort_keys=True)
    return d


def haupt(grundriss):
    getan = materialien_vereinfachen()
    fasen_fuers_netz()
    pfad, groesse = ausgeben()
    d = manifest(grundriss, groesse)
    print('[web] %s -> %d Bytes, %d Materialien vereinfacht'
          % (os.path.basename(pfad), groesse, len(getan)))
    return {'pfad': pfad, 'bytes': groesse, 'materialien': getan,
            'abschnitte': len(d['abschnitte'])}
