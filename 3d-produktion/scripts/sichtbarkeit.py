# Sichtbarkeitsflaggen und Kameras ausschreiben.
#
# Verdacht: Die Softbox-Flaechen sind in Blender Lichtwerkzeug, nicht
# Kulisse - in Cycles laesst man sie leuchten, aber fuer die Kamera
# unsichtbar schalten. In Unreal stehen sie jetzt als weisse Saeulen
# mitten im Bild. Und im Vergleichsbild fuellt die Marke das Bild,
# bei mir ist sie klein - also war es womoeglich eine andere Kamera.
import bpy, json

ZIEL = r'C:\Users\manue\Desktop\Vecom Design\_to_delete\sichtbarkeit.json'
aus = {'objekte': {}, 'kameras': {}, 'szene_kamera': None}

for o in bpy.data.objects:
    if o.type == 'MESH':
        aus['objekte'][o.name] = {
            'kamera':  bool(getattr(o, 'visible_camera', True)),
            'diffus':  bool(getattr(o, 'visible_diffuse', True)),
            'glanz':   bool(getattr(o, 'visible_glossy', True)),
            'schatten': bool(getattr(o, 'visible_shadow', True)),
            'render_aus': bool(o.hide_render),
        }
    elif o.type == 'CAMERA':
        m = o.matrix_world
        aus['kameras'][o.name] = {
            'lage': [m.translation.x, m.translation.y, m.translation.z],
            'brennweite': o.data.lens,
            'sensor': o.data.sensor_width,
        }

if bpy.context.scene.camera:
    aus['szene_kamera'] = bpy.context.scene.camera.name

with open(ZIEL, 'w', encoding='utf-8') as f:
    json.dump(aus, f, indent=1)

print('Szenenkamera: %s' % aus['szene_kamera'])
for n, k in aus['kameras'].items():
    print('  Kamera %-18s Lage %7.2f %7.2f %6.2f  %.0f mm'
          % (n, k['lage'][0], k['lage'][1], k['lage'][2], k['brennweite']))
print('--- Objekte, die fuer die Kamera unsichtbar sind ---')
treffer = 0
for n, d in aus['objekte'].items():
    if not d['kamera'] or d['render_aus']:
        treffer += 1
        print('  %-24s kamera=%s render_aus=%s diffus=%s'
              % (n, d['kamera'], d['render_aus'], d['diffus']))
print('Summe: %d von %d' % (treffer, len(aus['objekte'])))
