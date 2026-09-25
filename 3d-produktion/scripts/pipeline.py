# -*- coding: utf-8 -*-
"""VECOM Blender-Pipeline — Struktur, LODs, Pruefung.

Ergaenzt die vorhandenen Skripte in 3d-produktion/, ersetzt sie nicht:
web_export.py bleibt der Weg ins Web (und bleibt ohne Draco, weil raum.js mit
blankem GLTFLoader laedt), bauabnahme.py bleibt die Masspruefung, fotoreal*.py
bleiben der Weg zum gerechneten Bild. Was hier dazukommt, fehlte bisher:
Sammlungsstruktur, LOD-Stufen, Impostor und eine Ausgangspruefung fuer beide
Exportwege.

Aufruf im offenen Blender:

    import sys; sys.path.append(r"C:\\Users\\manue\\Desktop\\Vecom Design\\3d-produktion\\scripts")
    import pipeline; pipeline.struktur_anlegen()

Oder im Hintergrund, was fuer alles Laengere der richtige Weg ist:

    blender -b datei.blend -P pipeline.py -- lods Marke Podest
"""
import bpy
import os
import sys

# ---------------------------------------------------------------- Struktur

# Vier Sammlungen, weil es vier Ausgangswege gibt. Ein Objekt kann in mehreren
# liegen — genau das ist der Sinn: derselbe Koerper geht als LOD0 ins Web und
# unverkleinert nach Unreal.
SAMMLUNGEN = ["MASTER", "WEB", "UNREAL", "SAFE"]
LOD_SAMMLUNGEN = ["LOD0", "LOD1", "LOD2", "LOD3", "IMPOSTOR"]

ORDNER = [
    "master", "assets", "models", "materials", "texturen", "animationen",
    "rigs", "kameras", "licht", "web-export", "unreal-export", "render",
    "safe-mode", "scripts",
]


def _hole_sammlung(name, elternteil=None):
    """Sammlung holen oder anlegen. Vorhandene werden nie neu erzeugt."""
    if name in bpy.data.collections:
        s = bpy.data.collections[name]
    else:
        s = bpy.data.collections.new(name)
    ziel = elternteil or bpy.context.scene.collection
    if s.name not in [k.name for k in ziel.children]:
        try:
            ziel.children.link(s)
        except RuntimeError:
            pass  # haengt schon woanders — dann bleibt sie dort
    return s


def struktur_anlegen(basis=None):
    """Sammlungen und Ordner herstellen. Mehrfach aufrufbar, ohne Schaden."""
    ergebnis = {"sammlungen": [], "ordner": []}
    for name in SAMMLUNGEN:
        s = _hole_sammlung(name)
        ergebnis["sammlungen"].append(s.name)
    web = bpy.data.collections["WEB"]
    for name in LOD_SAMMLUNGEN:
        s = _hole_sammlung(name, web)
        ergebnis["sammlungen"].append(s.name)

    if basis:
        for u in ORDNER:
            p = os.path.join(basis, u)
            if not os.path.isdir(p):
                os.makedirs(p, exist_ok=True)
                ergebnis["ordner"].append(u)
    return ergebnis


# -------------------------------------------------------------------- LODs

# Anteil der Flaechen je Stufe. Nicht linear: Der Sprung von LOD0 auf LOD1
# faellt am wenigsten auf, weil das Objekt dort noch nah ist und die Silhouette
# zaehlt — nicht die Dichte.
LOD_ANTEILE = {"LOD1": 0.55, "LOD2": 0.25, "LOD3": 0.10}

# Umschaltentfernungen in Metern. Der Kern (experience-core) skaliert sie
# ueber lodDistanceScale je Qualitaetsstufe.
LOD_ENTFERNUNGEN = {"LOD0": (0, 10), "LOD1": (10, 30), "LOD2": (30, 80), "LOD3": (80, 200)}


def _kopie(obj, neuer_name, sammlung):
    neu = obj.copy()
    neu.data = obj.data.copy()
    neu.name = neuer_name
    neu.data.name = neuer_name
    sammlung.objects.link(neu)
    return neu


def lods_erzeugen(namen=None, anteile=None):
    """Erzeugt LOD1-LOD3 aus den genannten Objekten (oder allen Meshes in WEB).

    Der Decimate-Modifikator wird angewendet, nicht nur gesetzt: Ein Modifikator
    reist beim glTF-Export mit und wird dort ein zweites Mal gerechnet — das
    Ergebnis sieht anders aus als im Fenster.
    """
    anteile = anteile or LOD_ANTEILE
    struktur_anlegen()
    quelle = bpy.data.collections.get("WEB")
    if namen:
        objekte = [bpy.data.objects[n] for n in namen if n in bpy.data.objects]
    else:
        objekte = [o for o in quelle.objects if o.type == "MESH"] if quelle else []
    if not objekte:
        return {"fehler": "keine Meshes gefunden — Objekte erst in die Sammlung WEB legen"}

    lod0 = bpy.data.collections["LOD0"]
    bericht = {"quelle": [], "erzeugt": []}
    for obj in objekte:
        if obj.name not in [o.name for o in lod0.objects]:
            try:
                lod0.objects.link(obj)
            except RuntimeError:
                pass
        bericht["quelle"].append({"name": obj.name, "flaechen": len(obj.data.polygons)})

        for stufe, anteil in anteile.items():
            name = "%s_%s" % (obj.name, stufe)
            if name in bpy.data.objects:
                continue  # schon da — nicht doppelt bauen
            ziel = bpy.data.collections[stufe]
            neu = _kopie(obj, name, ziel)
            m = neu.modifiers.new("LOD", "DECIMATE")
            m.ratio = anteil
            # Anwenden ueber den Kontext des Objekts, nicht ueber die Auswahl:
            # die Auswahl des Benutzers soll unangetastet bleiben.
            with bpy.context.temp_override(object=neu, active_object=neu,
                                           selected_objects=[neu],
                                           selected_editable_objects=[neu]):
                bpy.ops.object.modifier_apply(modifier=m.name)
            bericht["erzeugt"].append({"name": name, "flaechen": len(neu.data.polygons),
                                       "anteil": anteil})
    return bericht


# ---------------------------------------------------------------- Pruefung

def pruefen(grenze_flaechen=250000):
    """Ausgangspruefung vor jedem Export. Findet, was im Browser teuer wird."""
    befunde = []
    gesamt = 0
    for obj in bpy.data.objects:
        if obj.type != "MESH":
            continue
        n = len(obj.data.polygons)
        gesamt += n
        if n > 50000:
            befunde.append("%s hat %d Flaechen — fuer das Web zu dicht" % (obj.name, n))
        if obj.modifiers:
            namen = ", ".join(m.type for m in obj.modifiers)
            befunde.append("%s traegt noch Modifikatoren (%s) — vor dem Export anwenden" % (obj.name, namen))
        if not obj.data.materials:
            befunde.append("%s hat kein Material" % obj.name)
        if obj.scale[:] != (1.0, 1.0, 1.0):
            befunde.append("%s ist skaliert %s — Skalierung vor dem Export anwenden" % (obj.name, tuple(round(s, 3) for s in obj.scale)))

    fehlend = [i.name for i in bpy.data.images
               if i.source == "FILE" and i.filepath and not os.path.exists(bpy.path.abspath(i.filepath))]
    for f in fehlend:
        befunde.append("Bild fehlt auf der Platte: %s" % f)

    if gesamt > grenze_flaechen:
        befunde.append("Szene hat %d Flaechen, Budget ist %d" % (gesamt, grenze_flaechen))

    return {"flaechen_gesamt": gesamt, "meshes": sum(1 for o in bpy.data.objects if o.type == "MESH"),
            "materialien": len(bpy.data.materials), "bilder": len(bpy.data.images),
            "befunde": befunde, "in_ordnung": not befunde}


# ------------------------------------------------------------ Rechenwerk

def gpu_einschalten(art="OPTIX"):
    """Cycles auf die Grafikkarte stellen.

    Gemessen am 15.09.2026 auf DESKTOP-9JVIIPU (RTX 5070, Ryzen 7 5700X):
    960x540 mit 256 Samples brauchte auf der CPU 4,80 s, mit OptiX 0,51 s —
    Faktor 9,4. Vorher stand compute_device_type auf NONE, es lief also alles
    auf der CPU. Der erste OptiX-Lauf uebersetzt die Kernel und ist deshalb
    langsamer; wer nur einmal misst, kommt zum falschen Schluss.
    """
    p = bpy.context.preferences.addons["cycles"].preferences
    p.compute_device_type = art
    p.get_devices()
    ein = []
    for d in p.devices:
        d.use = (d.type == art)
        if d.use:
            ein.append(d.name)
    bpy.ops.wm.save_userpref()
    for s in bpy.data.scenes:
        s.cycles.device = "GPU"
    return {"art": art, "eingeschaltet": ein}


if __name__ == "__main__":
    argumente = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else []
    befehl = argumente[0] if argumente else "pruefen"
    if befehl == "struktur":
        print(struktur_anlegen(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
    elif befehl == "lods":
        print(lods_erzeugen(argumente[1:] or None))
    elif befehl == "gpu":
        print(gpu_einschalten())
    else:
        print(pruefen())
