# Erklärvideo „Wie deine Website entsteht"

**Datei:** `webseite-erstellen.mp4` — 1280 × 720, 62 Sekunden, 3,3 MB, ohne Ton.

## Was darin zu sehen ist

Kein KI-Video und keine Stockbilder: Jedes Bild ist die laufende Website
vecom-design.it. Über die Aufnahme sind Titelkarten gelegt, dazwischen wird
gescrollt und geklickt wie bei einem echten Besuch — inklusive Vorführung des
Anfrageformulars.

Ablauf: Intro → Startseite → 01 Gespräch (Formular wird ausgefüllt) →
02 Richtung → 03 Umsetzung (Referenzen) → 04 Launch → Preis → Abbinder.

## Neu aufnehmen

    node tools/record-video.mjs                      # Live-Seite
    node tools/record-video.mjs http://localhost:8181/de/
    node tools/record-video.mjs https://vecom-design.it/de/ --3d

Danach umwandeln:

    ffmpeg -i video/*.webm -c:v libx264 -preset slow -crf 22 \
           -pix_fmt yuv420p -movflags +faststart -r 30 \
           video/webseite-erstellen.mp4

**Wichtig:** Ohne `--3d` läuft die Aufnahme ohne die 3D-Bühne. Grund: Auf einem
Rechner ohne Grafikkarte rendert der Browser die Szene in Software mit etwa
einem Bild pro Sekunde — das Video würde ruckeln. Mit einer normalen Grafikkarte
`--3d` verwenden, dann ist die Bühne im Video zu sehen.

Das Drehbuch (Titelkarten, Reihenfolge, Haltezeiten) steht oben in
`tools/record-video.mjs` in einer einzigen Liste und ist dort änderbar.

## Einsatz

- Auf der Website: als `<video>` im Ablauf-Abschnitt, stumm und mit Vorschaubild
- Instagram/TikTok: hochkant neu aufnehmen (Viewport im Skript auf 720 × 1280)
- Angebots-E-Mail: als Link, nicht als Anhang (3,3 MB)
