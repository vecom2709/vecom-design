# VECOM Akquise-Worker

Findet Betriebe (OpenStreetMap), prüft ihre Websites (Playwright, Lighthouse, eigene
Prüfungen), lässt starke Leads von Claude deuten und schreibt Textvorschläge — und meldet
alles an die Verwaltung (`/app/akquise`). **Er versendet nie etwas.** Freigabe und Versand
passieren nur in der Verwaltung, von einem Menschen, hinter dem Compliance-Gate.

Läuft auf Uwes Windows-Rechner (Rechenarbeit lokal, Overpass ist von dort erreichbar).

## Einrichten (einmal)

```powershell
cd "C:\Users\manue\Desktop\Vecom Design\website\tools\akquise"
npm.cmd install
npx.cmd playwright install chromium
copy .env.beispiel .env      # dann ausfüllen
npm.cmd run pruefen
```

`.env`: `AKQUISE_SCHLUESSEL` aus der Verwaltung (Neue Kunden finden → Compliance & Versand →
Worker-Schlüssel), optional `ANTHROPIC_API_KEY` und `AKQUISE_PSI_SCHLUESSEL`.
Die `.env` und der Ordner `daten/` kommen nie ins Repository.

## Befehle

| Befehl | Was passiert |
|---|---|
| `npm run pruefen` | Verbindung zur Verwaltung, Notbremse, Schlüssel |
| `npm run recherche` | wartende Rechercheaufträge abarbeiten (Gemeinde für Gemeinde, fortsetzbar) |
| `npm run audit` | nächste Websites prüfen (Standard 20 je Lauf) |
| `npm run texte` | Claude: Deutung + Vorlage für Leads ab Score 51 (Token-Obergrenze je Lauf) |
| `npm run alles` | alle drei nacheinander |
| `npm run einzel -- https://beispiel.it restaurant IT Aragona` | eine Seite prüfen, nichts melden |
| `npm run osm -- IT stadt Aragona restaurant,hotel` | zeigen, was OSM liefern würde, nichts melden |
| `npm test` | Regeln gegen feste Rohdaten prüfen |

Nachts automatisch: `planen.ps1` registriert die Windows-Aufgabe „VECOM Akquise“ (täglich 02:30, `npm run alles`).

## Grundsätze

- **Zuerst maschinell, dann Claude.** Kein KI-Token für etwas, das sich messen lässt.
- **Keine Behauptung ohne Beleg.** Was nicht sicher erkannt ist, heißt `UNVERIFIED` und kommt in keinen Regeltext.
- **Höflich crawlen.** robots.txt wird befolgt, eine Anfrage je Domain alle 1,5 s, höchstens 8 Seiten und 25 Links je Website, offen als `VecomAudit/1.0`.
- **Gemessenes vor Nicht-Gefundenem.** Zwei Lehren aus dem ersten echten Lauf stehen als Regressionstests in `test/regeln.test.ts`: eine englische Fassung unter `/englisch/` und eine Partita IVA als „Steuernummer“ — beide wären sonst falsch als „fehlt“ gemeldet worden.
