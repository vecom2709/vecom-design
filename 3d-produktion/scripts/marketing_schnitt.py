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
import math
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



# ------------------------------------------------------------------ Werbespot
# (01.10.2026, Uwe: hochprofessionelle 3D-Werbevideos). Harte Schnitte wie im
# Werbefilm, dezente Farbe (leichte S-Kurve, warme Lichter, kühle Schatten),
# Vignette, Einblendung aus Schwarz, Titel im ersten Bild, Abspann auf dem
# Heldenbild (Name groß in Gold, Satz, Adresse), Musik mit Ein- und Ausblenden.
# Für den Vecom-Spot hängt hinten das gegossene goldene V (assets/video/intro-*).

GOLD = (0.95, 0.77, 0.42, 1.0)
ABSPANN_S = 2.8                 # wie marketing_spot.ABSPANN_S


def _effekt(seq, name, typ, kanal, von, laenge):
    try:
        return seq.new_effect(name, typ, channel=kanal, frame_start=von, length=laenge)
    except TypeError:
        return seq.new_effect(name, typ, channel=kanal, frame_start=von, frame_end=von + laenge)


def _setzen(obj, **werte):
    for k, v in werte.items():
        try:
            setattr(obj, k, v)
        except Exception:
            pass


def _vignette(pfad, px):
    """Schwarz mit weichem Rand als PNG (Alpha), so groß wie der Film."""
    import numpy as np
    b, h = int(px[0]), int(px[1])
    y, x = np.mgrid[0:h, 0:b].astype(np.float32)
    rx, ry = (x - b / 2) / (b / 2), (y - h / 2) / (h / 2)
    r = np.sqrt(rx * rx + ry * ry) / math.sqrt(2.0)
    t = np.clip((r - 0.42) / 0.55, 0.0, 1.0)
    a = (t * t * (3 - 2 * t)) * 0.26              # Probe 01.10.2026: 0,34 dunkelte die Ecken sichtbar ab
    px4 = np.zeros((h, b, 4), np.float32); px4[..., 3] = a
    img = bpy.data.images.new('Vignette', b, h, alpha=True)
    img.pixels[:] = px4.ravel()
    img.filepath_raw = pfad; img.file_format = 'PNG'
    img.save()
    bpy.data.images.remove(img)


def spot_film(ordner, schnitte, fps, aus, px, spot, titel=''):
    """Einstellungen (bild-NNNN.png, Bereiche aus schnitte) -> fertiger Spot als MP4. Liefert einen Bericht."""
    sn = bpy.data.scenes.new('Werbespot')
    sn.render.resolution_x, sn.render.resolution_y, sn.render.resolution_percentage = int(px[0]), int(px[1]), 100
    sn.render.fps = fps; sn.render.fps_base = 1.0
    sn.sequence_editor_create()
    seq = sn.sequence_editor.strips if hasattr(sn.sequence_editor, 'strips') else sn.sequence_editor.sequences
    ordner_je = spot.get('ordner_je') or {}
    pos = 1
    lagen = []
    for i, st in enumerate(schnitte):
        quelle = ordner_je.get(str(i), ordner) if isinstance(ordner_je, dict) else ordner
        namen = ['bild-%04d.png' % k for k in range(int(st['von']), int(st['bis']) + 1)]
        try:
            s = seq.new_image('E%d' % i, os.path.join(quelle, namen[0]), channel=1, frame_start=pos, fit_method='FIT')
        except TypeError:
            s = seq.new_image('E%d' % i, os.path.join(quelle, namen[0]), channel=1, frame_start=pos)
        for x in namen[1:]:
            s.elements.append(x)
        s.frame_final_duration = len(namen)
        _setzen(s.colorspace_settings, name='sRGB')
        try:   # Farbe dezent: leichte S-Kurve, warme Lichter, kühle Schatten
            k = s.modifiers.new('Kurve', 'CURVES')
            c = k.curve_mapping.curves[3]
            c.points.new(0.25, 0.225); c.points.new(0.75, 0.79)
            k.curve_mapping.update()
            f = s.modifiers.new('Farbe', 'COLOR_BALANCE')
            f.color_balance.lift = (0.985, 0.995, 1.02)
            f.color_balance.gain = (1.025, 1.0, 0.97)
        except Exception as x:
            print('FARBE UEBERSPRUNGEN', x)
        lagen.append((pos, len(namen)))
        pos += len(namen)
    ende_film = pos                                   # erstes Bild nach den Einstellungen
    kurz = min(int(px[0]), int(px[1]))
    hoch = int(px[1]) > int(px[0])
    schrift = None
    for kandidat in (os.path.join(os.path.dirname(os.path.abspath(__file__)), 'archivo-film.ttf'), r'C:\Windows\Fonts\segoeuib.ttf'):
        if os.path.exists(kandidat):
            schrift = bpy.data.fonts.load(kandidat, check_existing=True); break
    endclip = spot.get('endclip')
    if endclip and os.path.exists(endclip):
        try:
            m = seq.new_movie('Gold', endclip, channel=1, frame_start=pos, fit_method='FIT')
        except TypeError:
            m = seq.new_movie('Gold', endclip, channel=1, frame_start=pos)
        pos += m.frame_final_duration
    n = pos - 1
    sn.frame_start, sn.frame_end = 1, n

    def blende(strip, von, bis, ein=10, aus_=8, wert=1.0):
        strip.blend_alpha = 0.0; strip.keyframe_insert('blend_alpha', frame=von)
        strip.blend_alpha = wert; strip.keyframe_insert('blend_alpha', frame=von + ein)
        if aus_:
            strip.keyframe_insert('blend_alpha', frame=max(von + ein, bis - aus_))
            strip.blend_alpha = 0.0; strip.keyframe_insert('blend_alpha', frame=bis)

    kanal = [2]

    def text(inhalt, von, bis, groesse, y, farbe=(1, 1, 1, 1), kasten=False, aus_=8):
        inhalt = str(inhalt or '').strip()
        if not inhalt or bis - von < 6:
            return None
        kanal[0] += 1
        ts = _effekt(seq, 'T%d' % kanal[0], 'TEXT', kanal[0], von, bis - von)
        ts.text = inhalt; ts.font_size = int(kurz * groesse); ts.location = (0.5, y)
        if schrift:
            ts.font = schrift
        ts.color = farbe
        _setzen(ts, use_shadow=True, shadow_color=(0, 0, 0, 0.75), shadow_blur=0.35, shadow_offset=0.02,
                alignment_x='CENTER', anchor_x='CENTER', anchor_y='CENTER', wrap_width=0.86,
                use_box=kasten, box_color=(0.02, 0.03, 0.05, 0.38), box_margin=0.025)
        blende(ts, von, bis, 10, aus_)
        return ts

    # Vignette über allen Einstellungen, Einblendung aus Schwarz
    vg = os.path.join(ordner, 'vignette.png')
    _vignette(vg, px)
    try:
        v = seq.new_image('Vignette', vg, channel=2, frame_start=1, fit_method='FIT')
    except TypeError:
        v = seq.new_image('Vignette', vg, channel=2, frame_start=1)
    v.frame_final_duration = ende_film - 1
    _setzen(v, blend_type='ALPHA_OVER', alpha_mode='STRAIGHT')
    sw = _effekt(seq, 'Schwarz', 'COLOR', 20, 1, 14)
    sw.color = (0, 0, 0)
    _setzen(sw, blend_type='ALPHA_OVER')
    sw.blend_alpha = 1.0; sw.keyframe_insert('blend_alpha', frame=1)
    sw.blend_alpha = 0.0; sw.keyframe_insert('blend_alpha', frame=14)

    # Titel auf der ersten Einstellung
    # Kleine Zeile je Einstellung (Vecom-Spot: die Branche) -- mit Kasten: Gold auf hellem
    # Marmor war in der Probe 01.10.2026 kaum lesbar; der Titel rückt dann darüber.
    etik = spot.get('etiketten') or []
    mit_etik = len(etik) == len(lagen) and bool(lagen)
    if lagen:
        p0, n0 = lagen[0]
        text(titel, p0 + int(fps * 0.5), p0 + n0 - 4, 0.064, (0.36 if mit_etik else 0.30) if hoch else (0.26 if mit_etik else 0.17), kasten=True)
    if mit_etik:
        for (p_, n_), e in zip(lagen, etik):
            text(e, p_ + 6, p_ + n_ - 2, 0.05, 0.22 if hoch else 0.1, farbe=GOLD, kasten=True, aus_=6)
    marke, satz, url = str(spot.get('marke') or ''), str(spot.get('claim') or ''), str(spot.get('url') or '')
    if endclip and os.path.exists(endclip):
        # Vecom: das V entsteht, darunter Satz und Adresse
        text(satz, ende_film + int(fps * 1.4), n + 1, 0.05, 0.25 if hoch else 0.16, aus_=0)
        text(url, ende_film + int(fps * 1.9), n + 1, 0.042, 0.19 if hoch else 0.09, farbe=GOLD, aus_=0)
    elif lagen:
        # Abspann auf dem Heldenbild: abdunkeln, Name groß in Gold, Satz, Adresse
        a0 = max(1, n - int(fps * ABSPANN_S) + 1)
        dk = _effekt(seq, 'Abdunkeln', 'COLOR', 19, a0, n + 1 - a0)
        dk.color = (0.0, 0.0, 0.0)
        _setzen(dk, blend_type='ALPHA_OVER')
        blende(dk, a0, n + 1, 14, 0, 0.5)
        text(marke, a0 + 6, n + 1, 0.105, 0.56, farbe=GOLD, aus_=0)
        text(satz, a0 + 14, n + 1, 0.048, 0.47, aus_=0)
        text(url, a0 + 22, n + 1, 0.04, 0.41, farbe=(0.92, 0.92, 0.92, 1), aus_=0)
    musik = spot.get('musik')
    if musik and os.path.exists(musik):
        t = seq.new_sound('Musik', musik, channel=30, frame_start=1)
        t.frame_final_duration = min(t.frame_final_duration, n)
        t.volume = 0.0; t.keyframe_insert('volume', frame=1)
        t.volume = 0.85; t.keyframe_insert('volume', frame=int(fps * 0.6))
        t.keyframe_insert('volume', frame=max(int(fps * 0.6), n - int(fps * 1.6)))
        t.volume = 0.0; t.keyframe_insert('volume', frame=n)
    _setzen(sn.render.image_settings, media_type='VIDEO')
    sn.render.image_settings.file_format = 'FFMPEG'
    ff = sn.render.ffmpeg
    ff.format = 'MPEG4'; ff.codec = 'H264'
    ff.constant_rate_factor = 'HIGH'; ff.ffmpeg_preset = 'GOOD'; ff.gopsize = fps
    if musik and os.path.exists(musik):
        ff.audio_codec = 'AAC'; ff.audio_bitrate = 192; ff.audio_channels = 'STEREO'; ff.audio_mixrate = 48000
    sn.render.use_sequencer = True
    sn.render.filepath = aus
    sn.view_settings.view_transform = 'Standard'
    with bpy.context.temp_override(scene=sn):
        bpy.ops.render.render(animation=True, scene=sn.name)
    mitte = os.path.join(ordner_je.get(str(len(schnitte) // 2), ordner) if isinstance(ordner_je, dict) else ordner,
                         'bild-%04d.png' % ((int(schnitte[len(schnitte) // 2]['von']) + int(schnitte[len(schnitte) // 2]['bis'])) // 2)) if schnitte else None
    m_, h_ = leuchtdichte(mitte) if mitte and os.path.exists(mitte) else (None, None)
    return {'dauer_s': round(n / fps, 1), 'einstellungen_n': len(schnitte), 'musik': bool(musik and os.path.exists(musik)),
            'endclip': bool(endclip and os.path.exists(endclip)), 'mittel': round(m_, 3) if m_ is not None else None,
            'ausgebrannt': round(h_, 4) if h_ is not None else None}


if __name__ == '__main__':
    argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    extra = dict(t.split('=', 1) for t in argv if '=' in t)
    with open(extra['auftrag'], encoding='utf-8-sig') as f:
        a = json.load(f)
    if a.get('modus') == 'spot':
        # Vecom-Spot: Einstellungen aus mehreren Blender-Laeufen (je Branche ein Ordner)
        _sp = dict(a.get('spot') or {})
        _sch, _je = [], {}
        for _i, _o in enumerate(a['ordner_liste']):
            with open(os.path.join(_o, 'schnitte.json'), encoding='utf-8') as _f:
                for _st in json.load(_f):
                    _je[str(len(_sch))] = _o; _sch.append(_st)
        _sp['ordner_je'] = _je
        _b = spot_film(a['ordner_liste'][0], _sch, int(a.get('fps', 24)), a['aus'], a['px'], _sp, a.get('titel', ''))
        with open(a['aus'].rsplit('.', 1)[0] + '.json', 'w', encoding='utf-8') as f:
            json.dump(_b, f, ensure_ascii=False, indent=1)
        print('SPOT FERTIG', a['aus'], json.dumps(_b))
        raise SystemExit(0)
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
