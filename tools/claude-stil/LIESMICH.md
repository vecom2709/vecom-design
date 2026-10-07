# Claude im Stil von Vecom Design

Fast-Schwarz `#0a0908`, Elfenbein `#f7f3ea`, **ein** Gold `#f1d38b` für das, was
gerade gemeint ist; Archivo, Inter, Cormorant; das V-Zeichen. Dieselben Werte
wie auf vecom-design.it – sie stammen aus `assets/css/` und
`3d-produktion/film-sichtbar/vecom-logo.svg`, nicht aus einer neuen Wahl.

`tools/` geht nie auf den Webspace (Ausschlussliste im Deploy).

## Was wohin wirkt

| Teil | Wirkt in | Datei |
|---|---|---|
| claude.ai-Stil | Chrome (oder ein anderer Browser) mit Stylus, angemeldet, jedes Konto | `claude-ai.user.css` |
| Farbthema | Claude Code im Terminal | `claude-code/vecom.json` |
| Statuszeile | Claude Code im Terminal | `claude-code/statusline.sh` |
| Mod: V-Zeichen beim Start, deutsche Wörter, Gold | Claude Code im Terminal **und** im Code-Tab der Desktop-App | `claude-code/vecom-stil/` |
| Terminal-Farben, bei Windows samt Logo im Hintergrund | Mac-Terminal, iTerm2, Windows Terminal | `terminal/` |

**Was nicht geht:** die Desktop-App von Claude selbst, also Fenster, Seitenleiste und Chat
außerhalb von Claude Code, und die Handy-App. Dafür hat Anthropic keine Schnittstelle.

## Einrichten

**Mac:** `bash tools/claude-stil/installieren-mac.sh`

**Windows:** `powershell -NoProfile -ExecutionPolicy Bypass -File tools\claude-stil\installieren-windows.ps1`

Danach in Claude Code `/theme` und **Vecom Design** wählen.

Beide Skripte sichern `~/.claude/settings.json` als `settings.json.vor-vecom`.
Der Mod wird direkt aus diesem Ordner gelesen: Ein `git pull` bringt jede
Änderung mit, ohne neue Installation.

## claude.ai in Chrome

1. Die Erweiterung **Stylus** aus dem Chrome Web Store installieren.
2. Diese Adresse öffnen. Stylus bietet dann **Installieren** an und hält den
   Stil künftig von selbst aktuell:
   `https://raw.githubusercontent.com/vecom2709/vecom-design/main/tools/claude-stil/claude-ai.user.css`
   (Die Adresse funktioniert erst, wenn der Stand auf `main` liegt. Vorher: Stylus →
   Verwalten → Neuen Stil schreiben und den Inhalt der Datei einfügen.)
3. claude.ai neu laden.

Der Stil wirkt für jedes Konto, mit dem Chrome auf claude.ai angemeldet ist, und
fasst weder Konto noch Daten an. Er färbt nur die Seite um.

**Wenn die Schriften fehlen:** claude.ai kann fremde Schriftquellen sperren.
Dann in Stylus unter **Optionen** „CSP patchen“ einschalten. Farben und V-Zeichen
hängen nicht davon ab.

**Ehrlich:** Der Stil setzt auf die Farbvariablen von claude.ai
(`--bg-100`, `--text-000`, `--accent-brand` …) und nicht auf Klassennamen. So
übersteht er Updates. Geprüft ist er an einem Nachbau, weil die echte Seite eine
Anmeldung braucht. Sieht nach einem Update von claude.ai etwas falsch aus, ist der
nächste Schritt ein Bildschirmfoto davon.

## Ändern

- Logo: `vecom-zeichen.svg` ist die Gruppe `marke` aus
  `3d-produktion/film-sichtbar/vecom-logo.svg`, `vecom-logo.svg` das ganze Logo,
  beide mit dem Goldverlauf der Seite (`#f4e0aa → #f1d38b → #b8912f`). Die
  Halbblock-Grafik in `claude-code/vecom-stil/hooks/zeichen.ts` ist aus
  `vecom-zeichen.png` gemessen: 18 × 16 Pixel, zwei je Terminalzelle. Sie wird
  nicht von Hand gesetzt.
- Mod prüfen: `claude plugin validate tools/claude-stil/claude-code/vecom-stil`
  und `claude plugin test tools/claude-stil/claude-code/vecom-stil` (4 Tests).
- Farben ändern: an allen vier Stellen zugleich, also `claude-ai.user.css`,
  `claude-code/vecom.json`, `register.tsx` und `terminal/`. Sonst gibt es zwei
  Golds.
