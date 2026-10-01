"""Messen und Schnitt fuer Marketing-Filme (01.10.2026).

Gemeinsam fuer beide Wege:
  - Blender rechnet die Bilder selbst (branchen_ort.py, marketing_film),
  - Unreal rechnet sie mit dem Path Tracer (ue-m01_marketing.py) -- dann
    ruft ue-marketing.ps1 dieses Skript direkt auf:

  blender -b -P marketing_schnitt.py -- auftrag=<json>
    JSON: ordner (Bildfolge bild-0000.png ...), aus (MP4), px [b, h], fps,
          titel, abspann, referenz (optional: Blender-Bild zum Vergleich)

Ergebnis: MP4 mit Titel und Abspann (gross, Kasten, Schatten -- Uwe,
03.09.2026) und ein Bericht <aus>.json mit der gemessenen Belichtung.
"""
import json
import os
import sys

import bpy


def leuchtdichte(pfad):
    """Mittlere Leuchtdichte (sRGB-Werte 0..1) und Anteil ausgebrannter Pixel."""
    import numpy as np
    bild = bpy.data.images.load(pfad, check_existing=False)
    px = np.array(bild.pixels[:], dtype=np.float32).reshape(-1, 4)[:, :3]
    bpy.data.images.remove(bild)
    lum = px @ np.array([0.2126, 0.7152, 0.0722], dtype=np.float32)
    return float(lum.mean()), float((px.max(axis=1) > 0.985).mean())


def titel_film(ordner, n, fps, aus, px, titel='', abspann=''):
    """Bildfolge + Titel + Abspann -> MP4 (gleiche Schrift und Kasten wie die Branchen-Filme)."""
    sn = bpy.data.scenes.new('Marketing-Schnitt')
    sn.render.resolution_x, sn.render.resolution_y, sn.render.resolution_percentage = int(px[0]), int(px[1]), 100
    sn.render.fps = fps; sn.frame_start = 1; sn.frame_end = n
    sn.sequence_editor_create()
    seq = sn.sequence_editor.strips if hasattr(sn.sequence_editor, 'strips') else sn.sequence_editor.sequences
    dateien = sorted(x for x in os.listdir(ordner) if x.startswith('bild-') and x.endswith('.png'))
    try:
        bild = seq.new_image('Film', os.path.join(ordner, dateien[0]), channel=1, frame_start=1, fit_method='FIT')
    except TypeError:
        bild = seq.new_image('Film', os.path.join(ordner, dateien[0]), channel=1, frame_start=1)
    for d in dateien[1:]:
        bild.elements.append(d)
    schrift = None
    for kandidat in (os.path.join(os.path.dirname(os.path.abspath(__file__)), 'archivo-film.ttf'), r'C:\Windows\Fonts\segoeuib.ttf'):
        if os.path.exists(kandidat):
            schrift = bpy.data.fonts.load(kandidat, check_existing=True); break
    # Gut lesbar (Uwe, 03.09.2026): gross, Kasten dahinter, Schatten. Groesse an der kurzen Bildkante.
    kurz = min(int(px[0]), int(px[1]))

    def text(name, inhalt, von, bis, faktor, y, kanal):
        von, bis = max(1, von), min(n + 1, bis)
        try:
            ts = seq.new_effect(name, 'TEXT', channel=kanal, frame_start=von, length=bis - von)
        except TypeError:
            ts = seq.new_effect(name, 'TEXT', channel=kanal, frame_start=von, frame_end=bis)
        ts.text = inhalt; ts.font_size = int(kurz * faktor); ts.location = (0.5, y)
        if schrift:
            ts.font = schrift
        ts.color = (1, 1, 1, 1); ts.use_shadow = True; ts.shadow_color = (0, 0, 0, 0.85)
        for a, v in (('alignment_x', 'CENTER'), ('anchor_x', 'CENTER'), ('anchor_y', 'CENTER'), ('wrap_width', 0.9),
                     ('use_box', True), ('box_color', (0.02, 0.03, 0.05, 0.55)), ('box_margin', 0.02)):
            try:
                setattr(ts, a, v)
            except Exception:
                pass
    if str(titel or '').strip():
        text('Titel', str(titel).strip()[:80], 1, int(fps * 2.8), 0.072, 0.86, 2)
    if str(abspann or '').strip():
        text('Abspann', str(abspann).strip()[:60], n - int(fps * 2.4), n + 1, 0.075, 0.14, 3)
    try:
        sn.render.image_settings.media_type = 'VIDEO'
    except Exception:
        pass
    sn.render.image_settings.file_format = 'FFMPEG'
    sn.render.ffmpeg.format = 'MPEG4'; sn.render.ffmpeg.codec = 'H264'
    sn.render.ffmpeg.constant_rate_factor = 'HIGH'; sn.render.ffmpeg.ffmpeg_preset = 'GOOD'
    sn.render.ffmpeg.gopsize = fps
    sn.render.filepath = aus
    sn.view_settings.view_transform = 'Standard'          # die Bilder sind schon fertig belichtet
    with bpy.context.temp_override(scene=sn):
        bpy.ops.render.render(animation=True, scene=sn.name)


if __name__ == '__main__':
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    extra = dict(t.split('=', 1) for t in argv if '=' in t)
    with open(extra['auftrag'], encoding='utf-8-sig') as f:
        a = json.load(f)
    ordner, aus = a['ordner'], a['aus']
    dateien = sorted(x for x in os.listdir(ordner) if x.startswith('bild-') and x.endswith('.png'))
    if not dateien:
        raise SystemExit('keine Bilder in ' + ordner)
    bericht = {'bilder': len(dateien)}
    # Belichtung am ersten und mittleren Bild; gegen Blenders Referenzbild,
    # wenn es eins gibt (gleiche Kamera, gleicher Ort).
    m0, h0 = leuchtdichte(os.path.join(ordner, dateien[0]))
    mm, hm = leuchtdichte(os.path.join(ordner, dateien[len(dateien) // 2]))
    bericht.update(mittel=round((m0 + mm) / 2, 3), ausgebrannt=round(max(h0, hm), 4))
    ref = a.get('referenz')
    if ref and os.path.exists(ref):
        mr, hr = leuchtdichte(ref)
        bericht.update(referenz_mittel=round(mr, 3), abweichung=round(m0 - mr, 3))
    titel_film(ordner, len(dateien), int(a.get('fps', 24)), aus, a['px'], a.get('titel', ''), a.get('abspann', ''))
    with open(aus.rsplit('.', 1)[0] + '.json', 'w', encoding='utf-8') as f:
        json.dump(bericht, f, ensure_ascii=False, indent=1)
    print('SCHNITT FERTIG', aus, json.dumps(bericht))
