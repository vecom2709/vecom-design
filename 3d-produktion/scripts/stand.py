# Bestandsaufnahme der aktuellen Showroom-Datei plus Kontrollbild.
# Laeuft im Hintergrund:  blender.exe -b <datei> -P stand.py
#
# Erst schauen, was da ist - dann erst anfassen. Die Fehler der letzten
# Stunden kamen alle daher, dass ich Erfolgsmeldungen geglaubt habe
# statt zurueckzulesen.
import bpy, os, sys

ORD = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
AUS = os.path.join(ORD, 'render', 'stand-bericht.txt')
BILD = os.path.join(ORD, 'render', 'stand-vorschau.png')

zeilen = []
def z(t):
    zeilen.append(str(t))
    print(t)

sz = bpy.context.scene
z('== Datei: ' + bpy.data.filepath)
z('Objekte gesamt: %d' % len(bpy.data.objects))
z('Materialien:    %d' % len(bpy.data.materials))
z('Bilder:         %d' % len(bpy.data.images))
z('Kameras:        %s' % ', '.join(o.name for o in bpy.data.objects if o.type == 'CAMERA'))
z('Lampen:         %s' % ', '.join('%s(%s)' % (o.name, o.data.type) for o in bpy.data.objects if o.type == 'LIGHT'))
z('Aktive Kamera:  %s' % (sz.camera.name if sz.camera else 'KEINE'))
z('Renderer:       %s' % sz.render.engine)
if sz.render.engine == 'CYCLES':
    z('Samples:        %d' % sz.cycles.samples)
z('Aufloesung:     %dx%d bei %d%%' % (sz.render.resolution_x, sz.render.resolution_y, sz.render.resolution_percentage))
z('Bildbereich:    %d-%d' % (sz.frame_start, sz.frame_end))

z('')
z('-- Netze mit Flaechenzahl (die groessten zuerst)')
netze = [(o.name, len(o.data.polygons)) for o in bpy.data.objects if o.type == 'MESH']
netze.sort(key=lambda t: -t[1])
z('Netze: %d, Flaechen gesamt: %d' % (len(netze), sum(n[1] for n in netze)))
for n, f in netze[:12]:
    z('   %-34s %6d' % (n, f))

z('')
z('-- Materialien')
for m in bpy.data.materials:
    z('   ' + m.name)

# --- GPU einschalten (OPTIX), sonst rechnet Cycles auf der CPU -------
try:
    pr = bpy.context.preferences.addons['cycles'].preferences
    pr.compute_device_type = 'OPTIX'
    pr.get_devices()
    an = []
    for d in pr.devices:
        d.use = (d.type == 'OPTIX')
        if d.use:
            an.append(d.name)
    sz.cycles.device = 'GPU'
    z('')
    z('GPU aktiv: ' + (', '.join(an) if an else 'KEINE - faellt auf CPU zurueck'))
except Exception as e:
    z('GPU-Umstellung fehlgeschlagen: %s' % e)

# --- Kontrollbild, klein und schnell ---------------------------------
sz.render.resolution_x = 960
sz.render.resolution_y = 540
sz.render.resolution_percentage = 100
if sz.render.engine == 'CYCLES':
    sz.cycles.samples = 64
    sz.cycles.use_denoising = True
sz.render.filepath = BILD
sz.render.image_settings.file_format = 'PNG'
bpy.ops.render.render(write_still=True)
z('')
z('Kontrollbild: ' + BILD)

with open(AUS, 'w', encoding='utf-8') as f:
    f.write('\n'.join(zeilen))
