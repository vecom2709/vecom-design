# Visitenkarten-Hintergründe

Erzeugt die Hintergründe in `app/karten/` (4 Stile × Vorderseite + Rückseite IT/DE/EN, 91 × 61 mm, 450 dpi)
und die Lage der dynamischen Felder (`layout.json` → `app/karten/layout.php`).

1. Schriften holen: `npm pack @fontsource/montserrat @fontsource/kaushan-script`, entpacken,
   woff2 (latin, 400/500/600/700 bzw. 400) mit fontTools nach `fonts/montserrat-<gewicht>.ttf` und `fonts/kaushan.ttf` wandeln.
2. `python3 gen.py final` (Playwright/Chromium), danach PNG → JPEG q90 nach `app/karten/` und `layout.json` in `layout.php` übertragen.

`paths.json`/`bbox.json` stammen aus `3d-produktion/film-sichtbar/vecom-logo.svg` (DESIGN mit fill-rule nonzero, sonst Löcher in E/G/N).
