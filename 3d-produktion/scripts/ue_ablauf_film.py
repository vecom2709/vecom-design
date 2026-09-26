# -*- coding: utf-8 -*-
"""Der Ablauf-Film in Unreal Engine (Uwe, 26.09.2026: "Blender baut, Unreal rendert").

LAEUFT IN UNREALS EIGENEM PYTHON, NICHT UEBER MCP
    Befund 15.09.2026: Die MCP-Schnittstelle setzt keine Werte, obwohl sie
    "Erfolg" meldet. Unreals Editor-Python kann es. Deshalb wird dieses
    Skript im Editor ausgefuehrt:
        Output Log -> Cmd -> py "C:/Users/manue/Desktop/Vecom Design/website/3d-produktion/scripts/ue_ablauf_film.py"
    in einem EIGENEN Projekt (z. B. "VecomAblauf", leeres Level ohne World
    Partition -- umgebung-und-mcp.md: im Open-World-Level werden Actors
    weggestreamt). Plugins: Python Editor Script Plugin, Interchange/glTF,
    Movie Render Queue.

WAS ES TUT
    1. Importiert unreal-export/ablauf/*.glb (aus ablauf_film.py -- export).
    2. Setzt alles Stehende bei (0,0,0) ein (die Lagen sind eingebacken).
    3. MISST die Achsen am Referenzwuerfel, statt sie anzunehmen: Blender
       (5, 2, -0,5) m muss irgendwo bei (500, +-200, -50) cm landen. Daraus
       folgt das Vorzeichen fuer Y -- und stimmt der Massstab nicht, bricht
       das Skript ab, bevor es Kameras falsch setzt.
    4. Baut je Sprache eine Level Sequence: Kamera (CineCamera, 35 mm, f/4,
       Fokus auf das Ziel), die bewegten Teile, die Schrift NUR dieser Sprache
       (als Spawnables -- in den anderen Sequenzen gibt es sie nicht).
    5. Legt Nachbearbeitung fest (manuelle Belichtung, dezentes Bloom, kein
       Game-Look) und je Sprache einen Movie-Render-Queue-Auftrag.
    Gerendert wird ueber MRQ (Window -> Cinematics -> Movie Render Queue ->
    "Render (Local)"), Ausgabe PNG, 1920x1080, 24 B/s.

RENDERER
    PFADTRACER = True: Unreal Path Tracer (hoechste Qualitaet, auf der RTX 5070
    rund 5-20 s je Bild) -- sonst Lumen mit 16 zeitlichen Samples.
"""
import json, math, os
import unreal

PFADTRACER = True
HIER = os.path.dirname(os.path.abspath(__file__))
QUELLE = os.path.join(os.path.dirname(HIER), 'unreal-export', 'ablauf')
AUSGABE = os.path.join(os.path.dirname(HIER), 'render', 'ablauf')
ZIEL = '/Game/Ablauf'
SPRACHEN = ('it', 'de', 'en')

B = json.load(open(os.path.join(QUELLE, 'bewegung.json'), encoding='utf-8'))
FPS = int(B['fps'])
log = unreal.log

# ------------------------------------------------------------------ Import
def importieren(datei, ordner):
    t = unreal.AssetImportTask()
    t.filename = os.path.join(QUELLE, datei)
    t.destination_path = ZIEL + '/' + ordner
    t.automated = True; t.save = True; t.replace_existing = True
    unreal.AssetToolsHelpers.get_asset_tools().import_asset_tasks([t])
    netze = []
    for p in t.imported_object_paths:
        a = unreal.load_asset(p)
        if isinstance(a, unreal.StaticMesh):
            netze.append(a)
    if not netze:
        raise RuntimeError('Nichts importiert aus %s -- glTF-Plugin (Interchange) aktiv?' % datei)
    log('%s: %d Netze' % (datei, len(netze)))
    return netze

welt = importieren('welt.glb', 'Welt')
texte = {s: importieren('text_%s.glb' % s, 'Text_' + s) for s in SPRACHEN}
teile = {t['name']: importieren(t['datei'], 'Teile/' + t['name']) for t in B['teile']}

aktoren = unreal.get_editor_subsystem(unreal.EditorActorSubsystem)
def setzen(netz, ort=unreal.Vector(0, 0, 0), name=None):
    a = aktoren.spawn_actor_from_object(netz, ort)
    if name: a.set_actor_label(name)
    return a

stehend = [setzen(n) for n in welt]

# ------------------------------------------------------------------ Achsen messen
ref = [a for a in stehend if 'Referenz' in a.get_actor_label()]
if not ref:
    raise RuntimeError('SM_Referenz fehlt im Import -- Achsen lassen sich nicht pruefen.')
mitte, _ = ref[0].get_actor_bounds(False)
bx, by, bz = B['referenz']['blender_ort']
MASS = mitte.x / (bx * 100.0)
SY = 1.0 if mitte.y * by > 0 else -1.0
log('Referenz bei (%.1f, %.1f, %.1f) cm -> Massstab %.3f, Y-Vorzeichen %+d' % (mitte.x, mitte.y, mitte.z, MASS, SY))
if abs(MASS - 1.0) > 0.02 or abs(mitte.z - bz * 100.0) > 3.0:
    raise RuntimeError('Massstab stimmt nicht (%.3f, z %.1f). Import-Einstellungen pruefen, nichts weiter gebaut.' % (MASS, mitte.z))
ref[0].set_actor_hidden_in_game(True)

def ue(p):
    """Blender-Meter -> Unreal-Zentimeter, mit dem gemessenen Y-Vorzeichen."""
    return unreal.Vector(p[0] * 100.0, SY * p[1] * 100.0, p[2] * 100.0)

# ------------------------------------------------------------------ Nachbearbeitung
ppv = aktoren.spawn_actor_from_class(unreal.PostProcessVolume, unreal.Vector(0, 0, 0))
ppv.set_actor_label('PP_Ablauf'); ppv.unbound = True
st = ppv.settings
st.override_auto_exposure_method = True; st.auto_exposure_method = unreal.AutoExposureMethod.AEM_MANUAL
st.override_auto_exposure_bias = True; st.auto_exposure_bias = 0.0          # am Testbild nachmessen, nicht raten
st.override_bloom_intensity = True; st.bloom_intensity = 0.15               # kein Game-Look
st.override_vignette_intensity = True; st.vignette_intensity = 0.15
st.override_film_grain_intensity = True; st.film_grain_intensity = 0.0
st.override_scene_fringe_intensity = True; st.scene_fringe_intensity = 0.0  # keine chromatische Aberration
ppv.settings = st

# ------------------------------------------------------------------ Sequenzen
werk = unreal.AssetToolsHelpers.get_asset_tools()
EINS = FPS  # Tick-Aufloesung wird unten auf die Bildrate gesetzt

def schluessel(kanal, bild, wert):
    kanal.add_key(unreal.FrameNumber(int(bild)), float(wert), interpolation=unreal.MovieSceneKeyInterpolation.AUTO)

def sequenz(spr):
    name = 'LS_Ablauf_' + spr
    pfad = ZIEL + '/' + name
    if unreal.EditorAssetLibrary.does_asset_exist(pfad):
        unreal.EditorAssetLibrary.delete_asset(pfad)
    seq = werk.create_asset(name, ZIEL, unreal.LevelSequence, unreal.LevelSequenceFactoryNew())
    seq.set_display_rate(unreal.FrameRate(FPS, 1))
    seq.set_tick_resolution(unreal.FrameRate(FPS, 1))
    seq.set_playback_start(1); seq.set_playback_end(int(B['bilder']) + 1)

    # Kamera
    cam = aktoren.spawn_actor_from_class(unreal.CineCameraActor, ue(B['kamera'][0]['ort']))
    cam.set_actor_label('Kamera_' + spr)
    cc = cam.get_cine_camera_component()
    fb = cc.filmback; fb.sensor_width = float(B['sensor_mm']); fb.sensor_height = float(B['sensor_mm']) * 9 / 16; cc.filmback = fb
    cc.current_focal_length = float(B['brennweite_mm']); cc.current_aperture = float(B['blende'])
    fs = cc.focus_settings; fs.focus_method = unreal.CameraFocusMethod.MANUAL; cc.focus_settings = fs
    kb = seq.add_spawnable_from_instance(cam)
    # Die Komponente binden, SOLANGE die Vorlage noch existiert -- ueblicher
    # Weg fuer Brennweite/Fokus einer gespawnten Kamera.
    fbind = seq.add_possessable(cc)
    fbind.set_parent(kb)
    aktoren.destroy_actor(cam)
    tr = kb.add_track(unreal.MovieScene3DTransformTrack); sec = tr.add_section(); sec.set_range(1, int(B['bilder']) + 1)
    kan = sec.get_all_channels()          # 0-2 Ort, 3-5 Roll/Pitch/Yaw, 6-8 Skalierung
    for k in B['kamera'][::2] + [B['kamera'][-1]]:
        o = ue(k['ort']); z = ue(k['ziel'])
        r = unreal.MathLibrary.find_look_at_rotation(o, z)
        for i, v in enumerate((o.x, o.y, o.z)): schluessel(kan[i], k['bild'], v)
        schluessel(kan[3], k['bild'], 0.0); schluessel(kan[4], k['bild'], r.pitch); schluessel(kan[5], k['bild'], r.yaw)
    # Fokus: Abstand Kamera -> Ziel, je 4 Bilder (echte Schaerfe-Nachfuehrung)
    ftr = fbind.add_track(unreal.MovieSceneFloatTrack)
    ftr.set_property_name_and_path('ManualFocusDistance', 'FocusSettings.ManualFocusDistance')
    fsec = ftr.add_section(); fsec.set_range(1, int(B['bilder']) + 1)
    fk = fsec.get_all_channels()[0]
    for k in B['kamera'][::4]:
        o = ue(k['ort']); z = ue(k['ziel'])
        schluessel(fk, k['bild'], (z - o).length())
    cut = seq.add_track(unreal.MovieSceneCameraCutTrack); cs = cut.add_section(); cs.set_range(1, int(B['bilder']) + 1)
    try:
        bid = unreal.MovieSceneSequenceExtensions.get_binding_id(seq, kb)
    except Exception:
        bid = seq.make_binding_id(kb, unreal.MovieSceneObjectBindingSpace.LOCAL)
    cs.set_camera_binding_id(bid)

    # bewegte Teile
    for t in B['teile']:
        for n in teile[t['name']]:
            a = setzen(n, ue(t['schluessel'][0]['ort']), 'Teil_%s_%s' % (t['name'], spr))
            b = seq.add_spawnable_from_instance(a); aktoren.destroy_actor(a)
            tr = b.add_track(unreal.MovieScene3DTransformTrack); sec = tr.add_section(); sec.set_range(1, int(B['bilder']) + 1)
            kan = sec.get_all_channels()
            for s in t['schluessel']:
                o = ue(s['ort'])
                for i, v in enumerate((o.x, o.y, o.z)): schluessel(kan[i], s['bild'], v)
                # Gespiegelte Y-Achse kehrt den Drehsinn um Z um.
                schluessel(kan[5], s['bild'], -s['dreh_z'] if SY < 0 else s['dreh_z'])
                for i in (6, 7, 8): schluessel(kan[i], s['bild'], max(0.001, s['skal']))

    # die Schrift DIESER Sprache
    for n in texte[spr]:
        a = setzen(n, unreal.Vector(0, 0, 0), 'Text_' + spr)
        seq.add_spawnable_from_instance(a); aktoren.destroy_actor(a)
    unreal.EditorAssetLibrary.save_loaded_asset(seq)
    log('Sequenz %s fertig' % name)
    return seq

sequenzen = {s: sequenz(s) for s in SPRACHEN}

# ------------------------------------------------------------------ Level speichern
welt_level = unreal.get_editor_subsystem(unreal.UnrealEditorSubsystem).get_editor_world()
unreal.get_editor_subsystem(unreal.LevelEditorSubsystem).save_current_level()
karte = welt_level.get_path_name().split('.')[0]

# ------------------------------------------------------------------ Render-Auftraege
mrq = unreal.get_editor_subsystem(unreal.MoviePipelineQueueSubsystem)
schlange = mrq.get_queue(); schlange.delete_all_jobs()
for s, seq in sequenzen.items():
    job = schlange.allocate_new_job(unreal.MoviePipelineExecutorJob)
    job.job_name = 'Ablauf_' + s
    job.sequence = unreal.SoftObjectPath(seq.get_path_name())
    job.map = unreal.SoftObjectPath(karte)
    cfg = job.get_configuration()
    aus = cfg.find_or_add_setting_by_class(unreal.MoviePipelineOutputSetting)
    aus.output_directory = unreal.DirectoryPath(os.path.join(AUSGABE, 'unreal_' + s))
    aus.file_name_format = 'bild_{frame_number}'
    aus.output_resolution = unreal.IntPoint(1920, 1080)
    aus.use_custom_frame_rate = True; aus.output_frame_rate = unreal.FrameRate(FPS, 1)
    cfg.find_or_add_setting_by_class(unreal.MoviePipelineImageSequenceOutput_PNG)
    if PFADTRACER:
        pt = cfg.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPass_PathTracer)
        aa = cfg.find_or_add_setting_by_class(unreal.MoviePipelineAntiAliasingSetting)
        aa.override_anti_aliasing = True; aa.spatial_sample_count = 256; aa.temporal_sample_count = 1
    else:
        cfg.find_or_add_setting_by_class(unreal.MoviePipelineDeferredPassBase)
        aa = cfg.find_or_add_setting_by_class(unreal.MoviePipelineAntiAliasingSetting)
        aa.override_anti_aliasing = True; aa.spatial_sample_count = 1; aa.temporal_sample_count = 16
        aa.engine_warm_up_count = 32; aa.render_warm_up_count = 32
    log('Render-Auftrag %s angelegt -> %s' % (s, aus.output_directory.path))

log('FERTIG. Jetzt: Movie Render Queue oeffnen -> "Render (Local)". Vorher EIN Bild je Sprache '
    'ansehen (Belichtung: auto_exposure_bias im PP_Ablauf nachstellen, bis Gold nicht ausbrennt).')
