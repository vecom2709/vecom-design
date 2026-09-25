# Filme auf vecom-design.it

## Der Ablauf-Film — `ablauf-<sprache>.mp4`

Was die Startseite im Abschnitt „Ablauf" abspielt. Drei Fassungen, je eine
Sprache, eingebunden in `index.html`; `build.mjs` tauscht beim Bauen den
Dateinamen mit (`ablauf-[a-z]{2}\.mp4`), `/de/` und `/en/` bekommen so von
selbst die richtige.

Gemessen am 15.09.2026:

| Datei | Länge | Größe |
|---|---|---|
| `ablauf-de.mp4` | 2:55 | 5,2 MB |
| `ablauf-en.mp4` | 2:34 | 4,5 MB |
| `ablauf-it.mp4` | 2:48 | 4,9 MB |

1280 × 720, H.264, **mit Ton** (AAC). Kein KI-Video und keine Stockbilder:
aufgenommen wird `explainer-ablauf.html`, also die eigene laufende Seite.

### Neu aufnehmen

    node tools/record-ablauf.mjs http://localhost:8181 de   # → video/ablauf-de.webm
    node tools/ton-einbauen.mjs de                          # → video/ablauf-de.mp4

Das Drehbuch steht oben in `tools/record-ablauf.mjs`.

**Wichtig:** Ohne Grafikkarte rendert der Browser die 3D-Bühne in Software mit
etwa einem Bild pro Sekunde — das Video ruckelt dann. Auf einem Rechner ohne
GPU die Bühne abschalten, statt das Ergebnis hinzunehmen.

## Was sonst hier liegt

- `jonika-*.mp4`, `mensaena-*.mp4` — Referenzaufnahmen der Kundenseiten,
  eingebunden im Abschnitt „Arbeiten".
- `clip1-fehler.mp4`, `clip3-ablauf.mp4` — Hochkant-Clips (`clip2-preis.mp4` am 25.09.2026 entfernt: zeigte „ab 499 € · Festpreis“)
  für TikTok und Reels, 720 × 1280. Beschrieben in `tiktok.md`; auf der Website
  werden sie nicht abgespielt.
- `sprechertext.md`, `tiktok.md` — Drehbücher. Gehen nie auf den Webspace,
  der Deploy schließt `.md` aus.

## Warum hier keine `erklaervideo-*.mp4` mehr liegt

Bis zum 15.09.2026 lagen drei Dateien dieses Namens im Repository — die
Vorgänger des Ablauf-Films, zusammen 6,0 MB. Sie waren seit dem Umbau in
keiner Seite mehr verlinkt; `build.mjs` kannte nur noch `ablauf-`. Diese
README beschrieb bis dahin eine vierte Datei, `webseite-erstellen.mp4`, die
es überhaupt nicht mehr gab. Eine Doku, die auf eine fehlende Datei zeigt,
schickt beim nächsten Mal jemanden auf die Suche.
