# -*- coding: utf-8 -*-
"""Fallstudien in Unreal (24.09.2026): Szene aus Blender, Licht, Kamera, Renderliste.

Uwe: „Alles auch mit Unreal Engine fotorealistisch rendern“ -- Ja.
Reihenfolge nach seiner Regel vom 16.09.2026: Das Aussehen entsteht in
Blender (pr_arbeiten.py, Modus unreal: GLB + Szenendaten), Unreal ist die
Buehne und der Path Tracer. Gebaut wird hier nichts neu, sondern
uebernommen und dann nachgemessen.

Ein Lauf = ein Projekt. Welches, steht in werkzeug\\ue-arbeiten-auftrag.json
(geschrieben von ue-arbeiten.ps1), zusammen mit Belichtung und Proben.

Uebernommen aus VecomVilla (v01/v03/v04), weil dort teuer gelernt:
  - Werte ueber Python setzen UND zuruecklesen (_setz). Die MCP-Bruecke
    meldet Erfolg, ohne zu schreiben; Python nicht -- aber Namen wechseln
    zwischen Engine-Fassungen, und ein stiller Fehlschlag faellt sonst erst
    am Bild auf.
  - Vorhandene Karte laden und leerraeumen statt loeschen und neu anlegen.
  - Sensor 36 x 20,25 mm, sonst Balken und ein anderer Bildwinkel als Blender.
  - Proben ueber die Raumabtastung der Render Queue, nicht ueber das CVar.
  - Belichtung ueber die Kamera (ISO/Verschluss), gemessen gegen Cycles.
"""
import json
import math
import os
import unreal

WERK = r'C:\Users\manue\Desktop\Vecom Design\werkzeug'
AUFTRAG = os.path.join(WERK, 'ue-arbeiten-auftrag.json')
BERICHT = os.path.join(WERK, 'ue-arbeiten-bericht.json')
MANIFEST_ZEIGER = os.path.join(WERK, 'ue-arbeiten-manifest.txt')
IMPORT = r'C:\Users\manue\Documents\Unreal Projects\VecomArbeiten\Import'

NICHT_GESETZT = []
AUFTRAG_GLOBAL = {}
EAS = unreal.get_editor_subsystem(unreal.EditorActorSubsystem)
LES = unreal.get_editor_subsystem(unreal.LevelEditorSubsystem)


def _setz(obj, name, wert):
    try:
        obj.set_editor_property(name, wert)
    except Exception as f:
        NICHT_GESETZT.append('%s.%s: nicht setzbar (%s)' % (type(obj).__name__, name, str(f)[:140]))
        return None
    try:
        ist = obj.get_editor_property(name)
    except Exception:
        return None
    if isinstance(wert, (int, float)) and not isinstance(wert, bool) and isinstance(ist, (int, float)):
        if abs(float(ist) - float(wert)) > max(1e-4, abs(float(wert)) * 1e-3):
            NICHT_GESETZT.append('%s.%s: gesetzt %s, gelesen %s' % (type(obj).__name__, name, wert, ist))
    return ist


def _komp(actor, klasse):
    k = actor.get_component_by_class(klasse)
    if k is None:
        raise RuntimeError('Komponente %s fehlt an %s' % (klasse, actor.get_name()))
    return k


def _spawn(klasse, ort=None, drehung=None, name=None):
    a = EAS.spawn_actor_from_class(klasse, ort or unreal.Vector(0, 0, 0), drehung or unreal.Rotator(0, 0, 0))
    if name:
        a.set_actor_label(name)
    return a


def nach_unreal(p):
    """Blender-Meter -> Unreal-Zentimeter mit Y-Spiegelung (wie VecomVilla).
    Ob der glTF-Import dieselbe Spiegelung macht, wird unten GEMESSEN."""
    return unreal.Vector(p[0] * 100.0, -p[1] * 100.0, p[2] * 100.0)


def level(pfad):
    if unreal.EditorAssetLibrary.does_asset_exist(pfad):
        LES.load_level(pfad)
        n = 0
        for a in EAS.get_all_level_actors():
            if isinstance(a, (unreal.WorldSettings, unreal.Brush)):
                continue
            EAS.destroy_actor(a)
            n += 1
        print('[a01] Karte geladen, %d Actors geraeumt' % n)
    else:
        if not LES.new_level(pfad):
            raise RuntimeError('new_level(%s) fehlgeschlagen' % pfad)
        print('[a01] Karte neu: %s' % pfad)


def importieren(p, glb):
    """GLB als SZENE einlesen: Actors mit ihren Lagen, nicht nur Assets."""
    ziel = '/Game/Arbeiten/%s' % p
    quelle = unreal.InterchangeManager.create_source_data(glb)
    par = unreal.ImportAssetParameters()
    par.is_automated = True
    mgr = unreal.InterchangeManager.get_interchange_manager_scripted()
    ok = mgr.import_scene(ziel, quelle, par)
    print('[a01] import_scene -> %s' % ok)
    # GEMESSEN (24.09.2026, cavaleri schwarz): Der Import legt Netze,
    # Materialien und Texturen nur im Speicher an. Die Karte wurde gespeichert
    # und zeigte auf Pakete, die es auf der Platte nie gab -- der Renderlauf
    # (-game) meldete „dependent package ... was not available" und rechnete
    # einen leeren Himmel. Also alles unter dem Zielordner sofort speichern.
    try:
        unreal.EditorLoadingAndSavingUtils.save_dirty_packages(True, True)
    except Exception as f:
        print('[a01] save_dirty_packages: %s' % f)
    try:
        unreal.EditorAssetLibrary.save_directory(ziel, only_if_is_dirty=False, recursive=True)
    except Exception as f:
        print('[a01] save_directory: %s' % f)
    n = len(unreal.EditorAssetLibrary.list_assets(ziel, recursive=True))
    print('[a01] Assets gespeichert unter %s: %d' % (ziel, n))
    return ok


def ausdehnung():
    lo = [1e9] * 3
    hi = [-1e9] * 3
    teile = {}
    for a in EAS.get_all_level_actors():
        if not isinstance(a, unreal.StaticMeshActor):
            continue
        ursprung, halb = a.get_actor_bounds(False)
        name = a.get_actor_label()
        teile[name] = [round(ursprung.x, 1), round(ursprung.y, 1), round(ursprung.z, 1)]
        for i, k in enumerate('xyz'):
            lo[i] = min(lo[i], getattr(ursprung, k) - getattr(halb, k))
            hi[i] = max(hi[i], getattr(ursprung, k) + getattr(halb, k))
    return lo, hi, teile


def licht(sz, auftrag):
    raus = {}
    abend = sz.get('sonne') is None
    # Die Sonne. Tagszenen: Richtung der Blender-Sonnenlampe (sie ist dort
    # an und traegt den Streifen auf dem Tisch). Abend: tiefe Sonne hinter
    # dem Fenster, warm -- die Blender-Szene hat dafuer den Himmel bei 2 Grad.
    if not abend:
        rx, ry, rz = sz['sonne']['rotation_euler_rad']
        # Blender-Lampe strahlt entlang -Z ihres Objekts: R = Rz*Ry*Rx
        v = [0.0, 0.0, -1.0]
        # Rx
        v = [v[0], v[1] * math.cos(rx) - v[2] * math.sin(rx), v[1] * math.sin(rx) + v[2] * math.cos(rx)]
        # Ry
        v = [v[0] * math.cos(ry) + v[2] * math.sin(ry), v[1], -v[0] * math.sin(ry) + v[2] * math.cos(ry)]
        # Rz
        v = [v[0] * math.cos(rz) - v[1] * math.sin(rz), v[0] * math.sin(rz) + v[1] * math.cos(rz), v[2]]
        d = unreal.Vector(v[0], -v[1], v[2])
    else:
        hoehe, azimut = math.radians(3.0), math.radians(float(auftrag.get('abend_azimut', 200.0)))
        # zur Sonne in Blender: hinter dem Fenster (+Y), leicht seitlich
        zur = [math.cos(hoehe) * math.sin(azimut - math.pi / 2), math.cos(hoehe) * math.cos(azimut - math.pi / 2), math.sin(hoehe)]
        d = unreal.Vector(-zur[0], zur[1], -zur[2])
    rot = unreal.MathLibrary.make_rot_from_x(d)
    sonne = _spawn(unreal.DirectionalLight, unreal.Vector(0, 0, 500), rot, 'Sonne')
    k = _komp(sonne, unreal.DirectionalLightComponent)
    _setz(k, 'intensity', float(auftrag.get('sonne_lux', 110000.0)))
    _setz(k, 'use_temperature', True)
    # Farbtemperatur aus dem Auftrag (27.09.2026): Mit atmosphere_sun_light
    # faerbt die SkyAtmosphere die Sonne ohnehin nach ihrem Stand -- 5400 K
    # obendrauf machten die Kueche doppelt gelb (R/B 1,65 gegen 1,28 in Cycles).
    _setz(k, 'temperature', float(auftrag.get('sonne_kelvin', 5400.0)) if not abend else 3000.0)
    _setz(k, 'light_source_angle', 0.545)
    _setz(k, 'cast_shadows', True)
    _setz(k, 'atmosphere_sun_light', True)
    raus['sonne_rot'] = [rot.pitch, rot.yaw, rot.roll]

    _spawn(unreal.SkyAtmosphere, name='Himmel')
    sl = _spawn(unreal.SkyLight, unreal.Vector(0, 0, 300), name='Himmelslicht')
    c = _komp(sl, unreal.SkyLightComponent)
    c.set_editor_property('source_type', unreal.SkyLightSourceType.SLS_CAPTURED_SCENE)
    _setz(c, 'real_time_capture', True)
    _setz(c, 'intensity', float(auftrag.get('himmel', 1.0)))

    # Lampen (Abendszenen): Blender-Flaechenleuchte -> Rechtecklicht nach unten.
    # 683 lm je Watt: dieselbe Umrechnung, die Blender fuer seine Watt annimmt.
    for i, l in enumerate(sz.get('lampen') or []):
        ort = nach_unreal(l['ort'])
        a = _spawn(unreal.RectLight, ort, unreal.Rotator(0, -90, 0), 'Lampe_%d' % i)
        rk = _komp(a, unreal.RectLightComponent)
        try:
            rk.set_editor_property('intensity_units', unreal.LightUnits.LUMENS)
        except Exception as f:
            NICHT_GESETZT.append('RectLight.intensity_units: %s' % f)
        _setz(rk, 'intensity', float(l['energie_w']) * 683.0 * float(auftrag.get('lampe_faktor', 1.0)))
        _setz(rk, 'use_temperature', True)
        _setz(rk, 'temperature', 2700.0)
        gr = max(1.0, float(l.get('groesse_m') or 0.12) * 100.0)
        _setz(rk, 'source_width', gr)
        _setz(rk, 'source_height', gr)
        _setz(rk, 'attenuation_radius', 800.0)
        _setz(rk, 'cast_shadows', True)

    # LEUCHTSTREIFEN (27.09.2026, Kueche): Emission kommt aus dem GLB nur als
    # Farbe 0..1 an und ist neben der Sonne schwarz (erstes Probebild: Pendel,
    # Nische, Regal dunkel). Jeder Streifen wird ein Rechtecklicht nach unten,
    # 3 mm unter seiner Unterseite. Leuchtdichte im selben Massstab wie die
    # Sonne: Blender-Staerke x (Sonne in lx / Sonne in Blender-W/m2) -- dann
    # stimmt das Verhaeltnis Streifen zu Tageslicht wie in Cycles.
    k_lum = float(auftrag.get('sonne_lux', 110000.0)) / max(0.1, float((sz.get('sonne') or {}).get('staerke_w_m2', 4.2)))
    raus['leuchten'] = []
    for i, l in enumerate(sz.get('leuchten') or []):
        ort = nach_unreal([l['mitte'][0], l['mitte'][1], float(l['unterseite_z']) - 0.003])
        a = _spawn(unreal.RectLight, ort, unreal.Rotator(0, -90, 0), 'Streifen_%s' % l['name'])
        rk = _komp(a, unreal.RectLightComponent)
        nits = float(l['staerke']) * k_lum * float(auftrag.get('streifen_faktor', 1.0))
        try:
            rk.set_editor_property('intensity_units', unreal.LightUnits.NITS)
            _setz(rk, 'intensity', nits)
            einheit = 'nits'
        except Exception as f:
            # Aeltere Fassung ohne Nits: Lichtstrom einer einseitigen Lambert-Flaeche.
            flaeche = max(1e-6, float(l['x_m']) * float(l['y_m']))
            rk.set_editor_property('intensity_units', unreal.LightUnits.LUMENS)
            _setz(rk, 'intensity', math.pi * nits * flaeche)
            einheit = 'lumen (%s)' % str(f)[:60]
        _setz(rk, 'use_temperature', True)
        _setz(rk, 'temperature', float(l.get('kelvin', 2700)))
        _setz(rk, 'source_width', max(0.2, float(l['y_m']) * 100.0))
        _setz(rk, 'source_height', max(0.2, float(l['x_m']) * 100.0))
        _setz(rk, 'barn_door_angle', 88.0)
        _setz(rk, 'attenuation_radius', 700.0)
        _setz(rk, 'cast_shadows', True)
        raus['leuchten'].append({'name': l['name'], 'nits': round(nits), 'einheit': einheit,
                                 'cm': [round(float(l['x_m']) * 100, 1), round(float(l['y_m']) * 100, 1)]})
    return raus


def nachbearbeitung():
    ppv = _spawn(unreal.PostProcessVolume, name='Nachbearbeitung')
    _setz(ppv, 'unbound', True)
    e = ppv.settings
    _setz(e, 'override_auto_exposure_method', True)
    e.set_editor_property('auto_exposure_method', unreal.AutoExposureMethod.AEM_MANUAL)
    _setz(e, 'override_auto_exposure_apply_physical_camera_exposure', True)
    _setz(e, 'auto_exposure_apply_physical_camera_exposure', True)
    for feld, wert in (('bloom_intensity', 0.08), ('vignette_intensity', 0.0), ('scene_fringe_intensity', 0.0),
                       ('film_grain_intensity', 0.0), ('motion_blur_amount', 0.0)):
        _setz(e, 'override_' + feld, True)
        _setz(e, feld, wert)
    # Weissabgleich: GEMESSEN WIRKUNGSLOS (27.09.2026) -- 5500, 8000 und
    # 9500 K ergaben byte-gleiche Bilder (Path Tracer in der Render Queue).
    # Bleibt als Schalter stehen, die Farbe regelt farbgewinn unten.
    weiss = float(AUFTRAG_GLOBAL.get('weiss', 6500.0))
    if abs(weiss - 6500.0) > 1.0:
        _setz(e, 'override_white_temp', True)
        _setz(e, 'white_temp', weiss)
    # Saettigung (27.09.2026): Unreals Filmkurve saettigt Holz und warme Toene
    # staerker als AgX in Blender (Holz R/B 3,7 gegen 2,4). 1,0 = unveraendert.
    satt = float(AUFTRAG_GLOBAL.get('saettigung', 1.0))
    if abs(satt - 1.0) > 1e-3:
        _setz(e, 'override_color_saturation', True)
        e.set_editor_property('color_saturation', unreal.Vector4(satt, satt, satt, 1.0))
    # Farbgewinn r,g,b (27.09.2026): gleicht den Gelbstich gegen Cycles aus
    # (Rueckwand Cycles 0,74/0,70/0,67, Unreal 0,78/0,70/0,62). Wirkt -- anders
    # als white_temp -- auch im Path Tracer.
    gewinn = [float(x) for x in str(AUFTRAG_GLOBAL.get('farbgewinn') or '').split(',') if x.strip()]
    if len(gewinn) == 3:
        _setz(e, 'override_color_gain', True)
        e.set_editor_property('color_gain', unreal.Vector4(gewinn[0], gewinn[1], gewinn[2], 1.0))
    _setz(ppv, 'settings', e)


def kamera(sz, auftrag, k=None, name='bild'):
    k = k or sz['kamera']
    pos = nach_unreal(k['ort'])
    ziel = nach_unreal(k['ziel'])
    rot = unreal.MathLibrary.find_look_at_rotation(pos, ziel)
    if k.get('shift_y'):
        rot = unreal.Rotator(roll=0.0, pitch=0.0, yaw=rot.yaw)
    a = _spawn(unreal.CineCameraActor, pos, rot, 'Kam_' + name)
    c = _komp(a, unreal.CineCameraComponent)
    fb = c.get_editor_property('filmback')
    _setz(fb, 'sensor_width', 36.0)
    _setz(fb, 'sensor_height', 36.0 * float(sz['hoehe']) / float(sz['breite']))
    # Objektivverschiebung aus Blender (Architektur: Kamera waagrecht, Bild
    # nach oben verschoben). Blender misst shift in Sensorbreiten.
    if k.get('shift_y'):
        _setz(fb, 'sensor_vertical_offset', float(k['shift_y']) * 36.0)
    _setz(c, 'filmback', fb)
    _setz(c, 'current_focal_length', float(k['brennweite_mm']))
    # Zum Pruefen laesst sich die Tiefenschaerfe abschalten (dof_blende 22),
    # ohne die Belichtung zu aendern: die rechnet mit depth_of_field_fstop.
    _setz(c, 'current_aperture', float(auftrag.get('dof_blende') or k['blende']))
    fs = c.get_editor_property('focus_settings')
    fs.set_editor_property('focus_method', unreal.CameraFocusMethod.MANUAL)
    _setz(fs, 'manual_focus_distance', float(k['fokus_m']) * 100.0)
    _setz(c, 'focus_settings', fs)
    # Belichtung: ISO 100, f/2.8, Verschluss aus dem Auftrag (Blenden
    # gegenueber 1/60 s). Gemessen und nachgezogen wird ueber ue-arbeiten.ps1 -Ev.
    t = (1.0 / 60.0) * (2.0 ** float(auftrag.get('ev', 0.0)))
    # Blende gleicht der Verschluss aus (27.09.2026, gemessen: Detail mit f/2,8
    # 2,8 Blenden heller als Cycles, Insel mit f/5,6 0,6). Blender belichtet
    # unabhaengig von der Blende; Unreal rechnet sie ein. Bezug ist die Blende
    # der ersten Kamera, fuer die ev abgestimmt wurde.
    bezug = float(auftrag.get('blende_bezug') or (sz.get('kamera') or {}).get('blende') or k['blende'])
    t *= (float(k['blende']) / bezug) ** 2
    iso = 100.0
    if t > 1.0:
        iso *= t
        t = 1.0
    pp = c.get_editor_property('post_process_settings')
    _setz(pp, 'override_camera_iso', True)
    _setz(pp, 'camera_iso', iso)
    _setz(pp, 'override_camera_shutter_speed', True)
    _setz(pp, 'camera_shutter_speed', 1.0 / t)
    _setz(pp, 'override_depth_of_field_fstop', True)
    _setz(pp, 'depth_of_field_fstop', float(k['blende']))
    _setz(c, 'post_process_settings', pp)
    return a, {'pos': [pos.x, pos.y, pos.z], 'rot': [rot.pitch, rot.yaw, rot.roll], 'iso': iso, 'verschluss': 1.0 / t}


def sequenz(p, kam, kname):
    seq_ordner = '/Game/Arbeiten/Sequences'
    stamm = 'SQ_%s' % p + ('' if kname == 'bild' else '_' + kname)
    pfad = '%s/%s' % (seq_ordner, stamm)
    if unreal.EditorAssetLibrary.does_asset_exist(pfad):
        unreal.EditorAssetLibrary.delete_asset(pfad)
    seq = unreal.AssetToolsHelpers.get_asset_tools().create_asset(
        stamm, seq_ordner, unreal.LevelSequence, unreal.LevelSequenceFactoryNew())
    seq.set_display_rate(unreal.FrameRate(24, 1))
    seq.set_playback_start(0)
    seq.set_playback_end(1)
    b = seq.add_possessable(kam)
    spur = seq.add_track(unreal.MovieSceneCameraCutTrack)
    ab = spur.add_section()
    ab.set_start_frame(0)
    ab.set_end_frame(1)
    bid = unreal.MovieSceneObjectBindingID()
    for name in ('guid', 'binding_id'):
        try:
            bid.set_editor_property(name, b.get_id())
            break
        except Exception:
            continue
    ab.set_camera_binding_id(bid)
    unreal.EditorAssetLibrary.save_asset(pfad)
    return pfad


def renderliste(p, karte, kams, auftrag, sz):
    """kams: Liste (Kameraname, Actor). Ein Auftrag je Kamera, alle in einer Liste."""
    sub = unreal.get_editor_subsystem(unreal.MoviePipelineQueueSubsystem)
    q = sub.get_queue()
    q.delete_all_jobs()
    for kname, kam in kams:
        pfad = sequenz(p, kam, kname)
        j = q.allocate_new_job(unreal.MoviePipelineExecutorJob)
        j.job_name = '%s-ue%s' % (p, auftrag.get('marke', '')) if kname == 'bild' else '%s-%s-ue%s' % (p, kname, auftrag.get('marke', ''))
        j.set_editor_property('map', unreal.SoftObjectPath(karte))
        j.set_editor_property('sequence', unreal.SoftObjectPath(pfad))
        auftrag_einstellen(j.get_configuration(), auftrag)
    return q


def auftrag_einstellen(k, auftrag):
    k.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPass_PathTracer)
    k.find_or_add_setting_by_class(unreal.MoviePipelineImageSequenceOutput_PNG)
    aus = k.find_or_add_setting_by_class(unreal.MoviePipelineOutputSetting)
    aus.set_editor_property('output_directory', unreal.DirectoryPath(auftrag['ordner']))
    aus.set_editor_property('output_resolution', unreal.IntPoint(int(auftrag['breite']), int(auftrag['hoehe'])))
    aus.set_editor_property('file_name_format', '{job_name}')
    aus.set_editor_property('override_existing_output', True)
    aa = k.find_or_add_setting_by_class(unreal.MoviePipelineAntiAliasingSetting)
    aa.set_editor_property('spatial_sample_count', int(auftrag.get('raumproben', 16)))
    aa.set_editor_property('temporal_sample_count', 1)
    aa.set_editor_property('override_anti_aliasing', True)
    aa.set_editor_property('anti_aliasing_method', unreal.AntiAliasingMethod.AAM_NONE)
    for feld, wert in (('engine_warm_up_count', 32), ('render_warm_up_count', 8)):
        try:
            aa.set_editor_property(feld, wert)
        except Exception:
            pass
    spiel = k.find_or_add_setting_by_class(unreal.MoviePipelineGameOverrideSetting)
    for feld, wert in (('cinematic_quality_settings', True),
                       ('texture_streaming', unreal.MoviePipelineTextureStreamingMethod.FULLY_LOAD),
                       ('use_lod_zero', True), ('use_high_quality_shadows', True)):
        try:
            spiel.set_editor_property(feld, wert)
        except Exception:
            pass
    cvars = {'r.PathTracing': 1, 'r.PathTracing.MaxBounces': 32, 'r.PathTracing.MaxPathIntensity': 50.0,
             'r.PathTracing.Denoiser': 1, 'r.PathTracing.EnableEmissive': 1,
             'r.PathTracing.VisibleLights': int(auftrag.get('sichtbare_lichter', 0)),
             'r.PathTracing.SamplesPerPixel': int(auftrag.get('spp', 32)),
             'r.RayTracing.Nanite.Mode': 0, 'r.Streaming.FullyLoadUsedTextures': 1, 'r.TextureStreaming': 0,
             'r.MotionBlurQuality': 0, 'r.Tonemapper.Sharpen': 0}
    e = k.find_or_add_setting_by_class(unreal.MoviePipelineConsoleVariableSetting)
    eintraege = []
    for name, wert in sorted(cvars.items()):
        ein = unreal.MoviePipelineConsoleVariableEntry()
        ein.set_editor_property('name', name)
        ein.set_editor_property('value', float(wert))
        ein.set_editor_property('is_enabled', True)
        eintraege.append(ein)
    try:
        e.set_editor_property('cvars', eintraege)
    except Exception:
        e.set_editor_property('console_variables', dict((n, float(v)) for n, v in cvars.items()))


def manifest(q, auftrag):
    erg = unreal.MoviePipelineEditorLibrary.save_queue_to_manifest_file(q)
    pfadm = None
    for teil in (erg if isinstance(erg, tuple) else (erg,)):
        if isinstance(teil, str) and teil:
            pfadm = teil
    if not pfadm:
        raise RuntimeError('Manifest nicht geschrieben: %r' % (erg,))
    if not os.path.isabs(pfadm):
        pfadm = os.path.abspath(os.path.join(unreal.Paths.project_dir(), pfadm))
    os.makedirs(auftrag['ordner'], exist_ok=True)
    with open(MANIFEST_ZEIGER, 'w', encoding='utf-8') as f:
        f.write(os.path.normpath(pfadm))
    return os.path.normpath(pfadm)


def main():
    with open(AUFTRAG, 'r', encoding='utf-8-sig') as f:
        auftrag = json.load(f)
    p = auftrag['projekt']
    with open(os.path.join(IMPORT, p + '.json'), 'r', encoding='utf-8') as f:
        sz = json.load(f)
    karte = '/Game/Arbeiten/Maps/L_%s' % p
    level(karte)
    importieren(p, os.path.join(IMPORT, p + '.glb'))
    lo, hi, teile = ausdehnung()
    print('[a01] Ausdehnung cm: x %.0f..%.0f  y %.0f..%.0f  z %.0f..%.0f' % (lo[0], hi[0], lo[1], hi[1], lo[2], hi[2]))
    bericht = {'projekt': p, 'ausdehnung': [lo, hi]}
    # MASSSTAB UND SPIEGELUNG GEMESSEN: Die Tischplatte liegt in Blender bei
    # 0,755 m; das Fenster in +Y. Stehen hier z 0..280 und das Fenster in
    # -Y, stimmt nach_unreal(). Sonst steht es im Bericht, bevor jemand
    # ein Bild ansieht.
    fenster = [v for n, v in teile.items() if 'fensterrahmen' in n.lower()]
    if fenster:
        bericht['fenster_y'] = fenster[0][1]
        print('[a01] Fensterrahmen bei y = %.1f (erwartet etwa -137)' % fenster[0][1])
    AUFTRAG_GLOBAL.update(auftrag)
    bericht['licht'] = licht(sz, auftrag)
    nachbearbeitung()
    # Mehrere Kameras (27.09.2026): sz['kameras'] aus Blender, Auswahl ueber
    # auftrag['kameras']. Ohne beides wie bisher eine Kamera „bild“.
    kams = []
    wahl = [n for n in str(auftrag.get('kameras') or '').split(',') if n]
    if sz.get('kameras') and wahl:
        bericht['kamera'] = {}
        for n in wahl:
            if n not in sz['kameras']:
                NICHT_GESETZT.append('Kamera %s fehlt in der Szene' % n)
                continue
            a, bericht['kamera'][n] = kamera(sz, auftrag, sz['kameras'][n], n)
            kams.append((n, a))
    else:
        a, bericht['kamera'] = kamera(sz, auftrag)
        kams.append(('bild', a))
    weg = LES.save_current_level()
    if not weg:
        welt = unreal.EditorLevelLibrary.get_editor_world()
        weg = unreal.EditorLoadingAndSavingUtils.save_map(welt, karte)
    print('[a01] Karte gespeichert: %s' % weg)
    bericht['manifest'] = manifest(renderliste(p, karte, kams, auftrag, sz), auftrag)
    bericht['nicht_gesetzt'] = NICHT_GESETZT
    with open(BERICHT, 'w', encoding='utf-8') as f:
        json.dump(bericht, f, ensure_ascii=False, indent=1)
    print('[a01] nicht gesetzt: %d' % len(NICHT_GESETZT))
    for z in NICHT_GESETZT[:20]:
        print('   ' + z)
    print('[a01] FERTIG')


main()
