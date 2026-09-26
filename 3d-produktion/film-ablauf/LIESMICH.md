# Ablauf-Film — vom Skript zum Film auf der Website

Stand 26.09.2026. Uwe: „Blender baut, Unreal rendert, Text im Bild, ohne Stimme,
etwa 90 Sekunden. Wenn er in drei Sprachen fertig ist: live.“

Gerendert wird **auf dem Windows-Rechner (RTX 5070)**. In der Cloud-Sitzung gibt
es weder Grafikkarte noch Unreal; dort wurde die Szene in Blender 5.2 (als
Python-Modul) gebaut, mit Cycles auf der CPU zur Probe gerendert und der
Export geprüft. Der Unreal-Teil (`ue_ablauf_film.py`) ist **noch nie gelaufen**.

## Vorher: Texte prüfen lassen
`texte.json`, Szenen **s6–s9** sind neu und von keinem Muttersprachler gelesen.
Einmal IT und EN gegenlesen lassen — nach dem Rendern heißt jede Änderung:
neu rendern.

## 1. Blender (je Sprache ein Blick, dann Export)
    git pull --rebase
    blender -b --factory-startup --python 3d-produktion/scripts/ablauf_film.py -- probe de
Die zehn Standbilder in `3d-produktion/render/ablauf/probe_de/` ansehen
(Eevee). Dann:
    blender -b --factory-startup --python 3d-produktion/scripts/ablauf_film.py -- export

## 2. Unreal
1. Neues Projekt **VecomAblauf** (leer, *ohne* World Partition), Plugins: Python
   Editor Script, glTF/Interchange, Movie Render Queue.
2. Leeres Level anlegen und speichern.
3. Output Log → Cmd:
   `py "C:/Users/manue/Desktop/Vecom Design/website/3d-produktion/scripts/ue_ablauf_film.py"`
   Das Skript misst zuerst den Referenzwürfel und bricht ab, wenn der Maßstab
   nicht stimmt — dann die Meldung schicken.
4. Je Sprache EIN Bild ansehen (Sequenz öffnen, Bild ~120). Gold brennt aus
   oder ist zu dunkel → im Actor `PP_Ablauf` den Wert *Exposure Compensation*
   ändern (gemessen, nicht geschätzt).
5. Window → Cinematics → Movie Render Queue → **Render (Local)**. Drei Aufträge,
   je 2160 Bilder, nach `3d-produktion/render/ablauf/unreal_<sprache>/`.

**Rückfallweg ohne Unreal** (Cycles/OptiX auf der RTX):
    blender -b --factory-startup --python 3d-produktion/scripts/ablauf_film.py -- cycles de
(ebenso `it`, `en`; Ausgabe `render/ablauf/cycles_<sprache>/`).

## 3. Live
    python 3d-produktion/film-ablauf/einbauen.py 3d-produktion/render/ablauf --live
Prüft, dass jede Sprache vollständig ist (sonst ändert es nichts), macht die
MP4s und Plakatbilder, ersetzt die Texte unter dem Video („1:30“ statt „2:48 ·
mit Ton“), baut die Seiten und pusht. Danach den Deploy prüfen.
