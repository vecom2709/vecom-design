# -*- coding: utf-8 -*-
"""Werbefilm „Sichtbar werden" in Unreal (28.09.2026, Uwe: „Vergleich Blender
vs. Unreal"). Die Gasse kommt fertig aus Blender (film_unreal_export.py:
gasse.glb + gasse.json), hier werden Licht, Kamera und Renderliste gesetzt --
Grundgerüst aus ue-a01_szene.py (Fallstudien), dort gemessen und erprobt.

Nacht statt Tag: kein Sonnenlicht; Mond (gerichtet, kühl), Laternen und
Ladenlicht aus den Blender-Werten, Leuchtflächen (Fenster, Telefon, Laptop,
Glühlampen) über den Emissions-Parameter des importierten Materials.
Maßstab wie in a01: 1 W/m² (Blender) = 26 190 lx; 1 W Lampe = 683 lm.
Jede Kamera zweimal: Path Tracer und Lumen.
"""
import json
import math
import os
import unreal

WERK = r'C:\Users\manue\Desktop\Vecom Design\werkzeug'
AUFTRAG = os.path.join(WERK, 'ue-film-auftrag.json')
BERICHT = os.path.join(WERK, 'ue-film-bericht.json')
MANIFEST_ZEIGER = os.path.join(WERK, 'ue-film-manifest.txt')
IMPORT = r'C:\Users\manue\Documents\Unreal Projects\VecomArbeiten\Import'
LUX_JE_W = 110000.0 / 4.2

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
        print('[f01] Karte geladen, %d Actors geraeumt' % n)
    else:
        if not LES.new_level(pfad):
            raise RuntimeError('new_level(%s) fehlgeschlagen' % pfad)
        print('[f01] Karte neu: %s' % pfad)


def importieren(p, glb):
    """GLB als SZENE einlesen: Actors mit ihren Lagen, nicht nur Assets."""
    ziel = '/Game/Film/%s' % p
    quelle = unreal.InterchangeManager.create_source_data(glb)
    par = unreal.ImportAssetParameters()
    par.is_automated = True
    mgr = unreal.InterchangeManager.get_interchange_manager_scripted()
    ok = mgr.import_scene(ziel, quelle, par)
    print('[f01] import_scene -> %s' % ok)
    # GEMESSEN (24.09.2026, cavaleri schwarz): Der Import legt Netze,
    # Materialien und Texturen nur im Speicher an. Die Karte wurde gespeichert
    # und zeigte auf Pakete, die es auf der Platte nie gab -- der Renderlauf
    # (-game) meldete „dependent package ... was not available" und rechnete
    # einen leeren Himmel. Also alles unter dem Zielordner sofort speichern.
    try:
        unreal.EditorLoadingAndSavingUtils.save_dirty_packages(True, True)
    except Exception as f:
        print('[f01] save_dirty_packages: %s' % f)
    try:
        unreal.EditorAssetLibrary.save_directory(ziel, only_if_is_dirty=False, recursive=True)
    except Exception as f:
        print('[f01] save_directory: %s' % f)
    n = len(unreal.EditorAssetLibrary.list_assets(ziel, recursive=True))
    print('[f01] Assets gespeichert unter %s: %d' % (ziel, n))
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


def sequenz(p, kam, kname):
    seq_ordner = '/Game/Film/Sequences'
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



def richtung(rot):
    """Blender-Objekt (Euler XYZ, rad) strahlt/blickt entlang -Z: Richtung in Unreal."""
    rx, ry, rz = rot
    v = [0.0, 0.0, -1.0]
    v = [v[0], v[1] * math.cos(rx) - v[2] * math.sin(rx), v[1] * math.sin(rx) + v[2] * math.cos(rx)]
    v = [v[0] * math.cos(ry) + v[2] * math.sin(ry), v[1], -v[0] * math.sin(ry) + v[2] * math.cos(ry)]
    v = [v[0] * math.cos(rz) - v[1] * math.sin(rz), v[0] * math.sin(rz) + v[1] * math.cos(rz), v[2]]
    return unreal.Vector(v[0], -v[1], v[2])


def kelvin_aus_farbe(f):
    """Farbe (linear) -> ungefähre Farbtemperatur; genügt für warm/kühl."""
    r, g, b = [max(1e-4, x) for x in f[:3]]
    q = b / r
    return max(1800.0, min(9000.0, 1800.0 + 5200.0 * q ** 0.9))


def licht(sz, auftrag):
    raus = {'lichter': 0}
    fak = float(auftrag.get('lampe_faktor', 1.0))
    for l in sz['lichter']:
        art = l['art']; ort = nach_unreal(l['ort'])
        if art == 'SUN':
            d = richtung(l['rot'])
            a = _spawn(unreal.DirectionalLight, unreal.Vector(0, 0, 3000), unreal.MathLibrary.make_rot_from_x(d), 'Mond')
            k = _komp(a, unreal.DirectionalLightComponent)
            _setz(k, 'intensity', float(l['energie_w']) * LUX_JE_W * float(auftrag.get('mond_faktor', 1.0)))
            srgb = [int(round(255 * ((x * 12.92) if x <= 0.0031308 else (1.055 * x ** (1 / 2.4) - 0.055)))) for x in l['farbe'][:3]]
            try:
                k.set_editor_property('light_color', unreal.Color(r=srgb[0], g=srgb[1], b=srgb[2], a=255))
            except Exception as f:
                NICHT_GESETZT.append('Mond.light_color: %s' % f)
            _setz(k, 'light_source_angle', max(0.3, math.degrees(float(l.get('winkel', 0.009)))))
            _setz(k, 'cast_shadows', True)
        elif art == 'POINT':
            a = _spawn(unreal.PointLight, ort, name=l['name'])
            k = _komp(a, unreal.PointLightComponent)
            try:
                k.set_editor_property('intensity_units', unreal.LightUnits.LUMENS)
            except Exception as f:
                NICHT_GESETZT.append('PointLight.intensity_units: %s' % f)
            _setz(k, 'intensity', float(l['energie_w']) * 683.0 * fak)
            _setz(k, 'use_temperature', True); _setz(k, 'temperature', kelvin_aus_farbe(l['farbe']))
            _setz(k, 'source_radius', max(0.5, float(l.get('radius') or 0.05) * 100.0))
            _setz(k, 'attenuation_radius', 2500.0)
            _setz(k, 'cast_shadows', True)
        elif art == 'AREA':
            d = richtung(l['rot'])
            a = _spawn(unreal.RectLight, ort, unreal.MathLibrary.make_rot_from_x(d), l['name'])
            k = _komp(a, unreal.RectLightComponent)
            try:
                k.set_editor_property('intensity_units', unreal.LightUnits.LUMENS)
            except Exception as f:
                NICHT_GESETZT.append('RectLight.intensity_units: %s' % f)
            _setz(k, 'intensity', float(l['energie_w']) * 683.0 * fak)
            _setz(k, 'use_temperature', True); _setz(k, 'temperature', kelvin_aus_farbe(l['farbe']))
            _setz(k, 'source_width', max(1.0, float(l['groesse']) * 100.0))
            _setz(k, 'source_height', max(1.0, float(l.get('groesse_y') or l['groesse']) * 100.0))
            _setz(k, 'attenuation_radius', 1500.0)
            _setz(k, 'cast_shadows', True)
        else:
            continue
        raus['lichter'] += 1
    # Nachthimmel: Himmelslicht aus der erfassten Szene, dazu eine Himmelskuppel
    sl = _spawn(unreal.SkyLight, unreal.Vector(0, 0, 500), name='Himmelslicht')
    c = _komp(sl, unreal.SkyLightComponent)
    c.set_editor_property('source_type', unreal.SkyLightSourceType.SLS_CAPTURED_SCENE)
    _setz(c, 'real_time_capture', True)
    _setz(c, 'intensity', float(auftrag.get('himmel', 1.0)))
    kuppel(float(auftrag.get('himmel_nits', 180.0)))
    return raus


def kuppel(nits):
    """Große Kugel mit unbeleuchtetem, tiefblauem Material als Nachthimmel
    (Leuchtdichte in cd/m², Farbe wie der eingefärbte Himmel in Blender)."""
    mel = unreal.MaterialEditingLibrary
    pfad = '/Game/Film/M_Nachthimmel'
    if unreal.EditorAssetLibrary.does_asset_exist(pfad):
        unreal.EditorAssetLibrary.delete_asset(pfad)
    m = unreal.AssetToolsHelpers.get_asset_tools().create_asset('M_Nachthimmel', '/Game/Film', unreal.Material, unreal.MaterialFactoryNew())
    m.set_editor_property('shading_model', unreal.MaterialShadingModel.MSM_UNLIT)
    m.set_editor_property('two_sided', True)
    farbe = mel.create_material_expression(m, unreal.MaterialExpressionConstant3Vector, -400, 0)
    farbe.set_editor_property('constant', unreal.LinearColor(0.44 * nits, 0.55 * nits, 1.0 * nits, 1.0))
    mel.connect_material_property(farbe, '', unreal.MaterialProperty.MP_EMISSIVE_COLOR)
    mel.recompile_material(m)
    unreal.EditorAssetLibrary.save_asset(pfad)
    kugel = _spawn(unreal.StaticMeshActor, unreal.Vector(0, -3000, 0), name='Himmelskuppel')
    smk = kugel.static_mesh_component
    smk.set_static_mesh(unreal.load_asset('/Engine/BasicShapes/Sphere.Sphere'))
    kugel.set_actor_scale3d(unreal.Vector(900, 900, 900))
    smk.set_material(0, m)
    _setz(smk, 'cast_shadow', False)


def emission(auftrag):
    """Leuchtflächen: Die Stärke kommt aus dem GLB nur als Farbe 0..1 an (a01,
    27.09.2026). Jedes importierte Material mit Emission bekommt den Faktor
    aus Blender x LUX_JE_W als Parameter -- die Parameternamen des Imports
    werden gelesen, nicht angenommen."""
    mel = unreal.MaterialEditingLibrary
    staerken = dict(auftrag.get('emission') or {})
    treffer = []
    for pfad in unreal.EditorAssetLibrary.list_assets('/Game/Film', recursive=True):
        a = unreal.load_asset(pfad)
        if not isinstance(a, unreal.MaterialInstanceConstant):
            continue
        name = a.get_name()
        s = next((v for k, v in staerken.items() if k.lower() in name.lower()), None)
        if s is None:
            continue
        namen = [str(n) for n in mel.get_scalar_parameter_names(a)]
        ziel = [n for n in namen if 'emiss' in n.lower() and ('strength' in n.lower() or 'factor' in n.lower() or 'intensity' in n.lower() or 'scale' in n.lower())]
        for n in ziel:
            mel.set_material_instance_scalar_parameter_value(a, n, float(s) * LUX_JE_W * float(auftrag.get('emission_faktor', 1.0)))
        unreal.EditorAssetLibrary.save_loaded_asset(a)
        treffer.append({'material': name, 'parameter': ziel, 'alle': namen[:12]})
    return treffer


def kamera(sz, auftrag, k, name):
    pos = nach_unreal(k['ort'])
    d = richtung(k['rot'])
    rot = unreal.MathLibrary.make_rot_from_x(d)
    a = _spawn(unreal.CineCameraActor, pos, rot, 'Kam_' + name)
    c = _komp(a, unreal.CineCameraComponent)
    fb = c.get_editor_property('filmback')
    _setz(fb, 'sensor_width', 36.0)
    _setz(fb, 'sensor_height', 36.0 * float(auftrag['hoehe']) / float(auftrag['breite']))
    _setz(c, 'filmback', fb)
    _setz(c, 'current_focal_length', float(k['brennweite']))
    _setz(c, 'current_aperture', float(k['blende']))
    fs = c.get_editor_property('focus_settings')
    fs.set_editor_property('focus_method', unreal.CameraFocusMethod.MANUAL)
    _setz(fs, 'manual_focus_distance', float(k['fokus']) * 100.0)
    _setz(c, 'focus_settings', fs)
    t = (1.0 / 60.0) * (2.0 ** float(auftrag.get('ev', 0.0)))
    t *= (float(k['blende']) / 2.8) ** 2
    iso = 100.0
    if t > 1.0 / 24.0:
        iso *= t * 24.0
        t = 1.0 / 24.0
    pp = c.get_editor_property('post_process_settings')
    _setz(pp, 'override_camera_iso', True); _setz(pp, 'camera_iso', iso)
    _setz(pp, 'override_camera_shutter_speed', True); _setz(pp, 'camera_shutter_speed', 1.0 / t)
    _setz(pp, 'override_depth_of_field_fstop', True); _setz(pp, 'depth_of_field_fstop', float(k['blende']))
    _setz(c, 'post_process_settings', pp)
    return a, {'pos': [pos.x, pos.y, pos.z], 'rot': [rot.pitch, rot.yaw, rot.roll], 'iso': iso, 'verschluss': 1.0 / t}


def renderliste(p, karte, kams, auftrag):
    sub = unreal.get_editor_subsystem(unreal.MoviePipelineQueueSubsystem)
    q = sub.get_queue()
    q.delete_all_jobs()
    for kname, kam in kams:
        pfad = sequenz(p, kam, kname)
        for verfahren in [v for v in str(auftrag.get('verfahren', 'pt,lumen')).split(',') if v]:
            j = q.allocate_new_job(unreal.MoviePipelineExecutorJob)
            j.job_name = 'gasse-%s-%s' % (kname, verfahren)
            j.set_editor_property('map', unreal.SoftObjectPath(karte))
            j.set_editor_property('sequence', unreal.SoftObjectPath(pfad))
            auftrag_einstellen(j.get_configuration(), auftrag, verfahren)
    return q


def auftrag_einstellen(k, auftrag, verfahren):
    if verfahren == 'pt':
        k.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPass_PathTracer)
    else:
        k.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPassBase)
    k.find_or_add_setting_by_class(unreal.MoviePipelineImageSequenceOutput_PNG)
    aus = k.find_or_add_setting_by_class(unreal.MoviePipelineOutputSetting)
    aus.set_editor_property('output_directory', unreal.DirectoryPath(auftrag['ordner']))
    aus.set_editor_property('output_resolution', unreal.IntPoint(int(auftrag['breite']), int(auftrag['hoehe'])))
    aus.set_editor_property('file_name_format', '{job_name}')
    aus.set_editor_property('override_existing_output', True)
    aa = k.find_or_add_setting_by_class(unreal.MoviePipelineAntiAliasingSetting)
    aa.set_editor_property('spatial_sample_count', int(auftrag.get('raumproben', 16)) if verfahren == 'pt' else 8)
    aa.set_editor_property('temporal_sample_count', 1)
    aa.set_editor_property('override_anti_aliasing', True)
    aa.set_editor_property('anti_aliasing_method', unreal.AntiAliasingMethod.AAM_NONE if verfahren == 'pt' else unreal.AntiAliasingMethod.AAM_TSR)
    for feld, wert in (('engine_warm_up_count', 64), ('render_warm_up_count', 32)):
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
    if verfahren == 'pt':
        cvars = {'r.PathTracing': 1, 'r.PathTracing.MaxBounces': 16, 'r.PathTracing.MaxPathIntensity': 50.0,
                 'r.PathTracing.Denoiser': 1, 'r.PathTracing.EnableEmissive': 1, 'r.PathTracing.VisibleLights': 0,
                 'r.PathTracing.SamplesPerPixel': int(auftrag.get('spp', 64))}
    else:
        cvars = {'r.Lumen.HardwareRayTracing': 1, 'r.Lumen.Reflections.HardwareRayTracing': 1,
                 'r.Lumen.ScreenProbeGather.DownsampleFactor': 8, 'r.Lumen.TraceMeshSDFs': 1,
                 'r.Lumen.Reflections.Allow': 1, 'r.Shadow.Virtual.Enable': 1,
                 'r.DynamicGlobalIlluminationMethod': 1, 'r.ReflectionMethod': 1}
    cvars.update({'r.RayTracing.Nanite.Mode': 0, 'r.Streaming.FullyLoadUsedTextures': 1, 'r.TextureStreaming': 0,
                  'r.MotionBlurQuality': 0, 'r.Tonemapper.Sharpen': 0})
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


def main():
    with open(AUFTRAG, 'r', encoding='utf-8-sig') as f:
        auftrag = json.load(f)
    AUFTRAG_GLOBAL.update(auftrag)
    with open(os.path.join(IMPORT, 'gasse.json'), 'r', encoding='utf-8') as f:
        sz = json.load(f)
    karte = '/Game/Film/Maps/L_Gasse'
    level(karte)
    importieren('gasse', os.path.join(IMPORT, 'gasse.glb'))
    lo, hi, teile = ausdehnung()
    print('[f01] Ausdehnung cm: x %.0f..%.0f  y %.0f..%.0f  z %.0f..%.0f' % (lo[0], hi[0], lo[1], hi[1], lo[2], hi[2]))
    bericht = {'ausdehnung': [lo, hi]}
    kirche = [v for n, v in teile.items() if 'kirche' in n.lower()]
    if kirche:
        bericht['kirche'] = kirche[0]
        print('[f01] Kirche bei %s (erwartet y um -6600)' % kirche[0])
    bericht['licht'] = licht(sz, auftrag)
    bericht['emission'] = emission(auftrag)
    nachbearbeitung()
    kams = []
    bericht['kamera'] = {}
    for bild, k in sorted(sz['kameras'].items(), key=lambda x: int(x[0])):
        a, bericht['kamera'][bild] = kamera(sz, auftrag, k, 'b' + bild)
        kams.append(('b' + bild, a))
    weg = LES.save_current_level()
    if not weg:
        welt = unreal.EditorLevelLibrary.get_editor_world()
        weg = unreal.EditorLoadingAndSavingUtils.save_map(welt, karte)
    print('[f01] Karte gespeichert: %s' % weg)
    bericht['manifest'] = manifest(renderliste('gasse', karte, kams, auftrag), auftrag)
    bericht['nicht_gesetzt'] = NICHT_GESETZT
    with open(BERICHT, 'w', encoding='utf-8') as f:
        json.dump(bericht, f, ensure_ascii=False, indent=1)
    print('[f01] nicht gesetzt: %d' % len(NICHT_GESETZT))
    for z in NICHT_GESETZT[:25]:
        print('   ' + z)
    print('[f01] FERTIG')


main()
