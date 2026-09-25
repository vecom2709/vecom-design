# Showroom nach Unreal exportieren.
#
# Weg: glTF/GLB. Unreal liest darueber die PBR-Werte (Grundfarbe,
# Metallisch, Rauheit, Leuchten) direkt mit - das ist genau das, was
# ueber die MCP-Schnittstelle NICHT setzbar war.
#
# Bewusst OHNE Draco: Unreals Importeur mag es unkomprimiert lieber.
#
# WICHTIG - teuer gelernt am 15.09.:
# export_image_format='AUTO' laesst Blender die Erweiterung
# EXT_texture_webp als *erforderlich* eintragen. Unreals Interchange
# kennt sie nicht und verwirft daraufhin die GESAMTE Datei:
#   "Nicht unterstuetzte Erweiterungen: EXT_texture_webp"
#   "Es gab nichts ... zu importieren."  -> 0 Objekte, ohne Absturz.
# Die Szene ist prozedural, Bildtexturen braucht sie nicht. Also 'NONE'.
import bpy, os

ZIEL = r'C:\Users\manue\Documents\Unreal Projects\VecomShowroom\Import'
os.makedirs(ZIEL, exist_ok=True)
DATEI = os.path.join(ZIEL, 'showroom.glb')
BERICHT = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\render\unreal-export.txt'

zeilen = []
def z(t):
    zeilen.append(str(t))
    print(t)

# Nur die Geometrie mitnehmen - Lampen und Kamera baut Unreal selbst,
# und der Volumen-Dunst (M_Dunst) laesst sich ohnehin nicht uebertragen.
raus = [o for o in bpy.data.objects if o.type == 'MESH']
z('Netze zum Export: %d' % len(raus))

bpy.ops.object.select_all(action='DESELECT')
for o in raus:
    o.hide_set(False)
    o.select_set(True)
bpy.context.view_layer.objects.active = raus[0]

# Lagen einbacken. Danach sitzt jedes Netz bereits in Weltkoordinaten,
# und Unreal kann alle 85 Teile schlicht bei (0,0,0) einsetzen - ohne
# Achsen- und Quaternionen-Umrechnung, die erfahrungsgemaess schiefgeht.
# Gefahrlos: Blender laeuft hier im Hintergrund und speichert die .blend nie.
bpy.ops.object.make_single_user(object=True, obdata=True, material=False, animation=False)
bpy.ops.object.transform_apply(location=True, rotation=True, scale=True)
z('Lagen eingebacken')

kwargs = dict(
    filepath=DATEI,
    export_format='GLB',
    use_selection=True,
    export_apply=True,           # Modifikatoren anwenden (Fasen, Normalen)
    export_materials='EXPORT',
    export_image_format='NONE',  # -> kein EXT_texture_webp, siehe oben
    export_yup=True,
    export_cameras=False,
    export_lights=False,
    export_animations=False,
    export_draco_mesh_compression_enable=False,
)
# Je nach Blender-Fassung zusaetzlich vorhanden - falls ja, hart abschalten.
for name, wert in (('export_image_add_webp', False),
                   ('export_image_webp_fallback', False)):
    if name in bpy.ops.export_scene.gltf.get_rna_type().properties:
        kwargs[name] = wert

bpy.ops.export_scene.gltf(**kwargs)

if os.path.exists(DATEI):
    mb = os.path.getsize(DATEI) / (1024*1024)
    z('Geschrieben: %s  (%.2f MB)' % (DATEI, mb))
    # Gegenprobe: steht wirklich keine erforderliche Erweiterung drin?
    with open(DATEI, 'rb') as f:
        kopf = f.read(200000)
    for marke in (b'extensionsRequired', b'EXT_texture_webp', b'KHR_draco'):
        z('  %-20s : %s' % (marke.decode(),
                            'GEFUNDEN - Problem!' if marke in kopf else 'nicht enthalten'))
else:
    z('FEHLER: keine Datei entstanden')

with open(BERICHT, 'w', encoding='utf-8') as f:
    f.write('\n'.join(zeilen))
