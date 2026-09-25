"""Fahrt-Bilder -> fahrt.mp4 mit dem ffmpeg, das in Blender steckt.

Auf dem Windows-Rechner gibt es kein eigenes ffmpeg; die Bilder (4 x 72 PNG)
in die Cloud zu schieben kostet mehr als hier zu kodieren. Werte wie in
tools/arbeiten-web.py: 1280 x 720, 24 B/s, H.264, ohne Ton.
Aufruf: blender -b --factory-startup --python fahrt_film.py -- <projekt> <quelle> <ziel.mp4>
"""
import bpy, sys, os
proj, quelle, ziel = sys.argv[sys.argv.index('--') + 1:][:3]
bilder = sorted(f for f in os.listdir(quelle) if f.endswith('.png'))
sc = bpy.context.scene
sc.sequence_editor_create()
seq = sc.sequence_editor
strip = seq.strips.new_image(name='fahrt', filepath=os.path.join(quelle, bilder[0]), channel=1, frame_start=1) \
    if hasattr(seq, 'strips') else seq.sequences.new_image(name='fahrt', filepath=os.path.join(quelle, bilder[0]), channel=1, frame_start=1)
for f in bilder[1:]:
    strip.elements.append(f)
sc.frame_start, sc.frame_end = 1, len(bilder)
sc.render.fps, sc.render.fps_base = 24, 1.0
r = sc.render
r.resolution_x, r.resolution_y, r.resolution_percentage = 1280, 720, 100
r.use_sequencer = True
# Blender 5: Video ist eine eigene Medienart, erst dann gibt es FFMPEG
if hasattr(r.image_settings, 'media_type'):
    r.image_settings.media_type = 'VIDEO'
r.image_settings.file_format = 'FFMPEG'
r.ffmpeg.format = 'MPEG4'
r.ffmpeg.codec = 'H264'
r.ffmpeg.constant_rate_factor = 'MEDIUM'      # entspricht etwa CRF 23-25
r.ffmpeg.ffmpeg_preset = 'BEST'
r.ffmpeg.audio_codec = 'NONE'
r.ffmpeg.gopsize = 12
r.filepath = ziel
sc.view_settings.view_transform = 'Standard'   # Bilder sind fertig belichtet: nichts nachbearbeiten
sc.view_settings.look = 'None'
bpy.ops.render.render(animation=True)
print('FERTIG', proj, len(bilder), ziel)
