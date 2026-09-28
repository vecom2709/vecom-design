"""film_schnitt.py -- Bildfolgen + Tonmischung zu MP4/WebM, mit Blenders eigenem FFmpeg
(auf dem Rechner ist kein ffmpeg installiert; 28.09.2026).

Aufruf:
  blender -b --factory-startup -P film_schnitt.py -- <plan.json>

plan.json:
{
  "breite": 1920, "hoehe": 1080, "fps": 24,
  "ton": "C:/.../trailer-de.wav",
  "ziel": "C:/.../sichtbar-werden-quer-de.mp4",
  "format": "mp4" | "webm",
  "qualitaet": "hoch" | "mittel",
  "stuecke": [ {"ordner": "C:/.../render/quer/voll", "von": 0, "bis": 551},
               {"ordner": "C:/.../render/quer/s3b", "von": 552, "bis": 695}, ... ]
}
Die Bilder heißen bild-NNNN.png. Stücke werden in der angegebenen Reihenfolge hart
aneinandergeschnitten (die Kurzfassung besteht aus Ausschnitten, der Trailer aus einem Stück
mit ersetzten Bereichen). Fehlt ein Bild, bricht das Skript mit einer Liste ab.
"""
import bpy, sys, os, json

argv = sys.argv[sys.argv.index('--') + 1:]
plan = json.load(open(argv[0], encoding='utf-8'))
sc = bpy.context.scene
sc.render.resolution_x = plan['breite']; sc.render.resolution_y = plan['hoehe']
sc.render.resolution_percentage = 100
sc.render.fps = plan.get('fps', 24); sc.render.fps_base = 1.0
if sc.sequence_editor is None:
    sc.sequence_editor_create()
se = sc.sequence_editor
strips = se.strips if hasattr(se, 'strips') else se.sequences

fehlt = []
pos = 1
for n, st in enumerate(plan['stuecke']):
    namen = ['bild-%04d.png' % f for f in range(st['von'], st['bis'] + 1)]
    fehlt += [os.path.join(st['ordner'], x) for x in namen if not os.path.exists(os.path.join(st['ordner'], x))]
    if fehlt:
        continue
    s = strips.new_image(name='stueck%d' % n, filepath=os.path.join(st['ordner'], namen[0]), channel=1, frame_start=pos)
    for x in namen[1:]:
        s.elements.append(x)
    s.frame_final_duration = len(namen)
    # Farbraum: gerenderte PNGs sind bereits Anzeige-Werte (AgX angewendet) -> keine erneute Umwandlung
    try:
        s.colorspace_settings.name = 'sRGB'
    except Exception:
        pass
    pos += len(namen)
if fehlt:
    print('FEHLENDE BILDER', len(fehlt)); [print(' ', x) for x in fehlt[:20]]
    sys.exit(3)
sc.frame_start = 1; sc.frame_end = pos - 1
if plan.get('ton'):
    t = strips.new_sound(name='ton', filepath=plan['ton'], channel=2, frame_start=1)
sc.view_settings.view_transform = 'Standard'; sc.view_settings.look = 'None'
sc.view_settings.exposure = 0.0; sc.view_settings.gamma = 1.0
sc.sequencer_colorspace_settings.name = 'sRGB'
r = sc.render
try:
    r.image_settings.media_type = 'VIDEO'       # Blender 5: Film-Ausgabe erst über den Medientyp
except (AttributeError, TypeError):
    pass
r.image_settings.file_format = 'FFMPEG'
ff = r.ffmpeg
if plan.get('format', 'mp4') == 'webm':
    ff.format = 'WEBM'; ff.codec = 'WEBM'; ff.audio_codec = 'OPUS'; ff.audio_bitrate = 128
else:
    ff.format = 'MPEG4'; ff.codec = 'H264'; ff.audio_codec = 'AAC'; ff.audio_bitrate = 192
    ff.use_max_b_frames = True; ff.max_b_frames = 2
ff.constant_rate_factor = 'HIGH' if plan.get('qualitaet', 'hoch') == 'hoch' else 'MEDIUM'
ff.ffmpeg_preset = 'GOOD'
ff.gopsize = sc.render.fps * 2
ff.audio_channels = 'STEREO'; ff.audio_mixrate = 48000
r.use_sequencer = True; r.use_compositing = False
# Blender hängt bei Filmen ohne '#' den Bildbereich an den Namen: in einen Zwischennamen
# rendern und danach auf den Zielnamen umbenennen.
ziel = plan['ziel']; ordner = os.path.dirname(ziel); os.makedirs(ordner, exist_ok=True)
stamm = os.path.join(ordner, '_schnitt_' + os.path.splitext(os.path.basename(ziel))[0] + '_')
r.use_file_extension = True
r.filepath = stamm
bpy.ops.render.render(animation=True)
kand = [os.path.join(ordner, x) for x in os.listdir(ordner) if os.path.join(ordner, x).startswith(stamm)]
kand.sort(key=os.path.getmtime)
if not kand:
    print('KEINE AUSGABE GEFUNDEN'); sys.exit(4)
os.replace(kand[-1], ziel)
print('SCHNITT FERTIG', ziel, sc.frame_end, 'Bilder', round(os.path.getsize(ziel) / 1e6, 1), 'MB', flush=True)
