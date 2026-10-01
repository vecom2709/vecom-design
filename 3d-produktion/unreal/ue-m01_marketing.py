# -*- coding: utf-8 -*-
"""Marketing-Filme in Unreal (01.10.2026): Blender baut, Unreal rendert.

Uwe (01.10.2026, B3): „3D-Videos -- Blender baut die Szene, Unreal rendert
mit dem Path Tracer. Zuerst ein Probelauf, dann in Serie.“

Eingang (von ue-marketing.ps1): werkzeug\\ue-marketing-auftrag.json mit
  szene   JSON aus branchen_ort.py (Modus marketing_unreal): GLB, Kamerafahrt
          Bild fuer Bild, Brennweite, Blende, HDRI mit Drehung, Bodenhoehe
  ordner  wohin die Bildfolge (bild-0000.png ...) geschrieben wird
  ev, spp, raumproben, nur_bilder (Probe: nur die ersten n Bilder)

Aufbau:
  - Karte leeren, GLB als Szene importieren und SOFORT speichern (sonst
    rechnet -game einen leeren Himmel, gemessen 24.09.2026).
  - Ort: HDRIBackdrop (Plugin) mit der Aufnahme als Cubemap -- Licht,
    sichtbarer Hintergrund und ein Boden, der Schatten aufnimmt, wie der
    Schattenfaenger in Blender. Projektionsmitte auf Kamerahoehe.
  - CineCamera mit Vollformat-Sensor im Bildformat, Schaerfe folgt dem Ziel.
  - Level Sequence: Ort je Bild als Schluessel, Blick aufs Ziel.
  - Render Queue: Path Tracer, PNG, Manifest fuer den -game-Lauf.
"""
import json
import math
import os
import unreal

WERK = r'C:\Users\manue\Desktop\Vecom Design\werkzeug'
AUFTRAG = os.path.join(WERK, 'ue-marketing-auftrag.json')
BERICHT = os.path.join(WERK, 'ue-marketing-bericht.json')
MANIFEST_ZEIGER = os.path.join(WERK, 'ue-marketing-manifest.txt')
KARTE = '/Game/Marketing/Maps/L_Marketing'
NICHT_GESETZT = []
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
    if a is None:
        raise RuntimeError('Spawn %s fehlgeschlagen' % klasse)
    if name:
        a.set_actor_label(name)
    return a


def nach_unreal(p):
    """Blender-Meter -> Unreal-Zentimeter, Y gespiegelt. Gegengeprueft ueber
    die Grenzen der Geometrie (Blender) gegen die importierten Actors."""
    return unreal.Vector(p[0] * 100.0, -p[1] * 100.0, p[2] * 100.0)


def level():
    if unreal.EditorAssetLibrary.does_asset_exist(KARTE):
        LES.load_level(KARTE)
        for a in EAS.get_all_level_actors():
            if not isinstance(a, (unreal.WorldSettings, unreal.Brush)):
                EAS.destroy_actor(a)
    elif not LES.new_level(KARTE):
        raise RuntimeError('new_level fehlgeschlagen')


def speichern(ordner):
    try:
        unreal.EditorLoadingAndSavingUtils.save_dirty_packages(True, True)
    except Exception as f:
        print('[m01] save_dirty_packages: %s' % f)
    try:
        unreal.EditorAssetLibrary.save_directory(ordner, only_if_is_dirty=False, recursive=True)
    except Exception as f:
        print('[m01] save_directory: %s' % f)


def importieren(glb):
    ziel = '/Game/Marketing/Szene'
    if unreal.EditorAssetLibrary.does_directory_exist(ziel):
        unreal.EditorAssetLibrary.delete_directory(ziel)
    quelle = unreal.InterchangeManager.create_source_data(glb)
    par = unreal.ImportAssetParameters()
    par.is_automated = True
    ok = unreal.InterchangeManager.get_interchange_manager_scripted().import_scene(ziel, quelle, par)
    speichern(ziel)
    print('[m01] import_scene %s, Assets %d' % (ok, len(unreal.EditorAssetLibrary.list_assets(ziel, recursive=True))))


def hdri_importieren(pfad):
    """.hdr als TextureCube (Unreal macht aus Laengen-/Breiten-Bildern eine Cubemap)."""
    ziel = '/Game/Marketing/HDRI'
    name = os.path.splitext(os.path.basename(pfad))[0].replace('.', '_')
    asset = '%s/%s' % (ziel, name)
    if not unreal.EditorAssetLibrary.does_asset_exist(asset):
        t = unreal.AssetImportTask()
        t.set_editor_property('filename', pfad)
        t.set_editor_property('destination_path', ziel)
        t.set_editor_property('automated', True)
        t.set_editor_property('save', True)
        t.set_editor_property('replace_existing', True)
        unreal.AssetToolsHelpers.get_asset_tools().import_asset_tasks([t])
    tex = unreal.EditorAssetLibrary.load_asset(asset)
    if not isinstance(tex, unreal.TextureCube):
        raise RuntimeError('HDRI nicht als Cubemap importiert: %s (%s)' % (asset, type(tex).__name__))
    return tex


def ausdehnung():
    lo, hi = [1e9] * 3, [-1e9] * 3
    for a in EAS.get_all_level_actors():
        if isinstance(a, unreal.StaticMeshActor):
            u, h = a.get_actor_bounds(False)
            for i, k in enumerate('xyz'):
                lo[i] = min(lo[i], getattr(u, k) - getattr(h, k))
                hi[i] = max(hi[i], getattr(u, k) + getattr(h, k))
    return lo, hi


def ort(sz, auftrag, tex):
    """HDRIBackdrop: Aufnahme als Licht, Hintergrund und Boden mit Schatten."""
    klasse = unreal.EditorAssetLibrary.load_blueprint_class('/HDRIBackdrop/Blueprints/HDRIBackdrop')
    if klasse is None:
        raise RuntimeError('HDRIBackdrop-Plugin fehlt im Projekt')
    z = sz['ziel']
    # Drehung GEMESSEN (01.10.2026, Restaurant, vier Proben 60/120/240/300):
    # Gierwinkel = Blender-Drehung. auftrag['hdri_yaw'] uebersteuert.
    yaw = float(auftrag['hdri_yaw']) if auftrag.get('hdri_yaw') not in (None, '') else float(sz['hdri_drehung_grad'])
    boden = nach_unreal([z[0], z[1], sz['boden_z']])
    a = _spawn(klasse, boden, unreal.Rotator(roll=0.0, pitch=0.0, yaw=yaw), 'Ort')
    # Projektionsmitte zuerst auf Aufnahmehoehe (dann stehen Boden und Horizont
    # wie in Blender); die Eigenschaften danach -- jedes set_editor_property
    # laesst das Konstruktionsskript neu laufen, das Ergebnis wird gespeichert.
    hoehe = float(sz.get('kamera_hoehe_m', 1.5)) * 100.0
    for k in a.get_components_by_class(unreal.SceneComponent):
        if 'projection' in k.get_name().lower():
            k.set_editor_property('relative_location', unreal.Vector(0.0, 0.0, hoehe))
    _setz(a, 'Size', float(auftrag.get('hdri_groesse', 150.0)))
    _setz(a, 'UseCameraProjection', False)
    _setz(a, 'Intensity', float(sz.get('hdri_staerke', 1.0)) * float(auftrag.get('hdri_faktor', 1.0)))
    _setz(a, 'Cubemap', tex)
    eigen = {}
    for name in ('Cubemap', 'Intensity', 'Size', 'LightingDistanceFactor', 'UseCameraProjection'):
        try:
            v = a.get_editor_property(name)
            eigen[name] = v.get_name() if hasattr(v, 'get_name') else v
        except Exception as f:
            eigen[name] = 'fehlt'
    print('[m01] HDRIBackdrop: %s' % eigen)
    return {'hdri_yaw': yaw, 'projektion_cm': hoehe, 'eigenschaften': {k: str(v) for k, v in eigen.items()}}


def nachbearbeitung():
    ppv = _spawn(unreal.PostProcessVolume, name='Nachbearbeitung')
    _setz(ppv, 'unbound', True)
    e = ppv.settings
    _setz(e, 'override_auto_exposure_method', True)
    e.set_editor_property('auto_exposure_method', unreal.AutoExposureMethod.AEM_MANUAL)
    _setz(e, 'override_auto_exposure_apply_physical_camera_exposure', True)
    _setz(e, 'auto_exposure_apply_physical_camera_exposure', True)
    # Kein Game-Look: kaum Bloom, keine Vignette, kein Farbsaum, kein Korn.
    for feld, wert in (('bloom_intensity', 0.06), ('vignette_intensity', 0.0), ('scene_fringe_intensity', 0.0),
                       ('film_grain_intensity', 0.0), ('motion_blur_amount', 0.0)):
        _setz(e, 'override_' + feld, True)
        _setz(e, feld, wert)
    _setz(ppv, 'settings', e)


def kamera(sz, auftrag):
    b, h = sz['px']
    z = nach_unreal(sz['ziel'])
    p0 = nach_unreal(sz['bilder'][0]['ort'])
    a = _spawn(unreal.CineCameraActor, p0, unreal.MathLibrary.find_look_at_rotation(p0, z), 'Kamera')
    c = _komp(a, unreal.CineCameraComponent)
    fb = c.get_editor_property('filmback')
    _setz(fb, 'sensor_width', 36.0)
    _setz(fb, 'sensor_height', 36.0 * float(h) / float(b))
    _setz(c, 'filmback', fb)
    _setz(c, 'current_focal_length', float(sz['brennweite_mm']))
    _setz(c, 'current_aperture', float(sz['blende']))
    # Schaerfe folgt dem Ziel (die Fahrt aendert den Abstand um ~20 cm).
    punkt = _spawn(unreal.TargetPoint, z, None, 'Schaerfe')
    fs = c.get_editor_property('focus_settings')
    fs.set_editor_property('focus_method', unreal.CameraFocusMethod.TRACKING)
    tr = fs.get_editor_property('tracking_focus_settings')
    _setz(tr, 'actor_to_track', punkt)
    fs.set_editor_property('tracking_focus_settings', tr)
    _setz(c, 'focus_settings', fs)
    # Belichtung: ISO 100, Verschluss 1/60 s je EV. Blender belichtet
    # unabhaengig von der Blende, Unreal rechnet sie ein -> Bezug f/2,8.
    t = (1.0 / 60.0) * (2.0 ** (float(auftrag.get('ev', 0.0)) + float(sz.get('belichtung', 0.0))))
    t *= (float(sz['blende']) / 2.8) ** 2
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
    _setz(pp, 'depth_of_field_fstop', float(sz['blende']))
    _setz(c, 'post_process_settings', pp)
    return a, {'iso': iso, 'verschluss': round(1.0 / t, 2)}


def _kanaele(abschnitt):
    """Ort X/Y/Z und Drehung X/Y/Z (roll/pitch/yaw) eines Transform-Abschnitts."""
    alle = abschnitt.get_all_channels()
    namen = {}
    for k in alle:
        try:
            namen[str(k.get_name())] = k
        except Exception:
            pass
    def finde(*kand):
        for n in kand:
            for name, k in namen.items():
                # UE 5.8 haengt „_0“ an (Location.X_0) -- Vergleich ohne Endung.
                if name.replace(' ', '').lower().split('_')[0] == n.lower():
                    return k
        return None
    return [finde('Location.X'), finde('Location.Y'), finde('Location.Z'),
            finde('Rotation.X'), finde('Rotation.Y'), finde('Rotation.Z')], list(namen)


def sequenz(sz, kam, n):
    ordner = '/Game/Marketing/Sequences'
    pfad = ordner + '/SQ_Marketing'
    if unreal.EditorAssetLibrary.does_asset_exist(pfad):
        unreal.EditorAssetLibrary.delete_asset(pfad)
    seq = unreal.AssetToolsHelpers.get_asset_tools().create_asset('SQ_Marketing', ordner, unreal.LevelSequence, unreal.LevelSequenceFactoryNew())
    fps = int(sz.get('fps', 24))
    seq.set_display_rate(unreal.FrameRate(fps, 1))
    try:
        seq.set_tick_resolution(unreal.FrameRate(fps, 1))     # Schluessel genau auf den Bildern
    except Exception:
        pass
    seq.set_playback_start(0)
    seq.set_playback_end(n)
    b = seq.add_possessable(kam)
    spur = b.add_track(unreal.MovieScene3DTransformTrack)
    ab = spur.add_section()
    ab.set_range(0, n)
    kan, namen = _kanaele(ab)
    if any(k is None for k in kan):
        raise RuntimeError('Transform-Kanaele nicht gefunden: %s' % namen)
    z = nach_unreal(sz['ziel'])
    vorher_yaw = None
    for f in range(n):
        p = nach_unreal(sz['bilder'][f]['ort'])
        r = unreal.MathLibrary.find_look_at_rotation(p, z)
        yaw = r.yaw
        if vorher_yaw is not None:               # kein Sprung bei +-180
            while yaw - vorher_yaw > 180.0:
                yaw -= 360.0
            while yaw - vorher_yaw < -180.0:
                yaw += 360.0
        vorher_yaw = yaw
        rahmen = unreal.FrameNumber(f)
        for k, v in zip(kan, (p.x, p.y, p.z, r.roll, r.pitch, yaw)):
            k.add_key(rahmen, float(v), interpolation=unreal.MovieSceneKeyInterpolation.LINEAR)
    schnitt = seq.add_track(unreal.MovieSceneCameraCutTrack)
    cab = schnitt.add_section()
    cab.set_range(0, n)
    bid = unreal.MovieSceneObjectBindingID()
    for name in ('guid', 'binding_id'):
        try:
            bid.set_editor_property(name, b.get_id())
            break
        except Exception:
            continue
    cab.set_camera_binding_id(bid)
    unreal.EditorAssetLibrary.save_asset(pfad)
    return pfad


def renderliste(seqpfad, sz, auftrag):
    sub = unreal.get_editor_subsystem(unreal.MoviePipelineQueueSubsystem)
    q = sub.get_queue()
    q.delete_all_jobs()
    j = q.allocate_new_job(unreal.MoviePipelineExecutorJob)
    j.job_name = 'marketing'
    j.set_editor_property('map', unreal.SoftObjectPath(KARTE))
    j.set_editor_property('sequence', unreal.SoftObjectPath(seqpfad))
    k = j.get_configuration()
    k.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPass_PathTracer)
    k.find_or_add_setting_by_class(unreal.MoviePipelineImageSequenceOutput_PNG)
    aus = k.find_or_add_setting_by_class(unreal.MoviePipelineOutputSetting)
    aus.set_editor_property('output_directory', unreal.DirectoryPath(auftrag['ordner']))
    aus.set_editor_property('output_resolution', unreal.IntPoint(int(sz['px'][0]), int(sz['px'][1])))
    aus.set_editor_property('file_name_format', 'bild-{frame_number}')
    aus.set_editor_property('override_existing_output', True)
    _setz(aus, 'zero_pad_frame_numbers', 4)
    if int(auftrag.get('nur_bilder') or 0) > 0:
        _setz(aus, 'use_custom_playback_range', True)
        _setz(aus, 'custom_start_frame', 0)
        _setz(aus, 'custom_end_frame', int(auftrag['nur_bilder']))
    aa = k.find_or_add_setting_by_class(unreal.MoviePipelineAntiAliasingSetting)
    aa.set_editor_property('spatial_sample_count', int(auftrag.get('raumproben', 8)))
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
    cvars = {'r.PathTracing': 1, 'r.PathTracing.MaxBounces': 24, 'r.PathTracing.MaxPathIntensity': 50.0,
             'r.PathTracing.Denoiser': 1, 'r.PathTracing.EnableEmissive': 1,
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
    return q


def manifest(q):
    erg = unreal.MoviePipelineEditorLibrary.save_queue_to_manifest_file(q)
    pfadm = None
    for teil in (erg if isinstance(erg, tuple) else (erg,)):
        if isinstance(teil, str) and teil:
            pfadm = teil
    if not pfadm:
        raise RuntimeError('Manifest nicht geschrieben: %r' % (erg,))
    if not os.path.isabs(pfadm):
        pfadm = os.path.abspath(os.path.join(unreal.Paths.project_dir(), pfadm))
    with open(MANIFEST_ZEIGER, 'w', encoding='utf-8') as f:
        f.write(os.path.normpath(pfadm))
    return os.path.normpath(pfadm)


def main():
    with open(AUFTRAG, 'r', encoding='utf-8-sig') as f:
        auftrag = json.load(f)
    with open(auftrag['szene'], 'r', encoding='utf-8') as f:
        sz = json.load(f)
    os.makedirs(auftrag['ordner'], exist_ok=True)
    bericht = {'szene': auftrag['szene']}
    level()
    importieren(sz['glb'])
    lo, hi = ausdehnung()
    bericht['ausdehnung_cm'] = [lo, hi]
    # Achsen gegenpruefen: Blender-Grenzen nach Unreal umgerechnet.
    g = sz.get('grenzen')
    if g:
        a, b = nach_unreal(g['min']), nach_unreal(g['max'])
        soll = [min(a.x, b.x), min(a.y, b.y), min(a.z, b.z), max(a.x, b.x), max(a.y, b.y), max(a.z, b.z)]
        ist = lo + hi
        bericht['achsen_abweichung_cm'] = round(max(abs(s - i) for s, i in zip(soll, ist)), 1)
        print('[m01] Achsen: Abweichung %.1f cm' % bericht['achsen_abweichung_cm'])
    tex = hdri_importieren(sz['hdri'])
    bericht['ort'] = ort(sz, auftrag, tex)
    nachbearbeitung()
    n = len(sz['bilder'])
    kam, bericht['kamera'] = kamera(sz, auftrag)
    seqpfad = sequenz(sz, kam, n)
    speichern('/Game/Marketing')
    weg = LES.save_current_level()
    if not weg:
        weg = unreal.EditorLoadingAndSavingUtils.save_map(unreal.EditorLevelLibrary.get_editor_world(), KARTE)
    bericht['manifest'] = manifest(renderliste(seqpfad, sz, auftrag))
    bericht['bilder'] = n
    bericht['nicht_gesetzt'] = NICHT_GESETZT
    with open(BERICHT, 'w', encoding='utf-8') as f:
        json.dump(bericht, f, ensure_ascii=False, indent=1)
    print('[m01] nicht gesetzt: %d' % len(NICHT_GESETZT))
    for z in NICHT_GESETZT[:20]:
        print('   ' + z)
    print('[m01] FERTIG')


main()
