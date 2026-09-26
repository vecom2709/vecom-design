# VECOM Lead Intelligence & Website Opportunity System

Stand: 26.09.2026 · Auftrag von Uwe vom 24.09.2026 · Ziel: **wie viele wirklich relevante
Unternehmen finden wir, denen VECOM anhand konkreter Beobachtungen einen nachvollziehbaren
Mehrwert anbieten kann** — nicht: wie viele E-Mails gehen raus.

## Architektur — eine Wahrheit, zwei Teile

```
 Uwes Windows-Rechner                              vecom-design.it (All-Inkl, PHP 8 + MariaDB)
 ┌──────────────────────────────┐   akquise.php   ┌───────────────────────────────────────────┐
 │ tools/akquise (Node/TS)      │ ─────────────▶ │ Verwaltung /app/akquise                   │
 │ research  · Overpass (OSM)   │  eigener        │ CRM · Detailansicht · Score (Hoheit)      │
 │ crawler   · robots, Netz     │  Schlüssel,     │ Compliance-Gate · Regeln als Daten        │
 │ audit     · Playwright, LH   │  nur melden     │ Freigabe · Versand (Brevo) · Grenzen      │
 │ ki        · Claude (Score≥51)│                 │ Sperrliste · Antworten · Protokoll        │
 └──────────────────────────────┘                 │ analyse.php · widerspruch.php (öffentlich)│
                                                  └───────────────────────────────────────────┘
```

**Warum nicht Next.js + Postgres:** Die Verwaltung hat Anmeldung, Rollen, CSRF, Prüfspur,
Brevo-Versand, Cron und 1.600+ Kettenprüfungen schon. Ein zweites System hätte eine zweite
Sperrliste — und eine Sperrliste, die es zweimal gibt, wird irgendwann übersehen. Der
bevorzugte Stack (TypeScript, Playwright, Lighthouse) steckt dort, wo er gebraucht wird: im
Worker. Der Webspace kann weder Node noch Chromium ausführen.

**Research und Versand sind getrennt:** An `akquise.php` gibt es keine Aktion zum Senden,
Freigeben, Sperren oder Löschen. Selbst ein gestohlener Worker-Schlüssel löst keine Mail aus.

## Module

| Vorgabe | Wo |
|---|---|
| research / company-discovery | `tools/akquise/src/recherche/` (Overpass, Land → Region → Kreis → Gemeinde → Branche, fortsetzbar) |
| crawler | `tools/akquise/src/crawler/netz.ts` (DNS, TLS, Weiterleitungen, TTFB, robots.txt, Linkcheck) |
| audit · performance · mobile · seo · ux · conversion · design · vertrauen · experience | `tools/akquise/src/audit/` + `regeln/*.ts` (reine Funktionen, getestet) |
| lead-scoring | `app/src/AkquiseScore.php` (Gewichte 20/15/20/15/10/10/10, Sättigung, Boden für fehlende Seiten) |
| KI (Deutung, Texte) | `tools/akquise/src/ki/` — nur ab Score 51, Cache, Token-Obergrenze |
| compliance · approval · blacklist · rate limiting | `app/src/AkquiseGate.php`, Tabelle `akq_regeln` |
| email-generator | `app/src/AkquiseText.php` (Regeltext DE/IT/EN + Textprüfung) |
| email-sender · response tracking | `app/src/AkquiseVersand.php` |
| crm · settings · logs | `app/akquise_route.php`, `app/views/akquise*.php`, `akq_protokoll` |
| Audit-Landingpage | `analyse.php` + `app/src/AkquiseAnalyse.php` (Schlüssel, noindex, aus bis eingeschaltet, 60 Tage) |

Datenmodell: `app/migrations/072_akquise.sql` (10 Tabellen `akq_*`).

## Das Gate

Reihenfolge: Sperrliste → keine Zweitansprache → Land → Regel aus `akq_regeln` → sonst
UNKNOWN. Ergebnisse: `CONTACT_ALLOWED`, `REVIEW_REQUIRED`, `DO_NOT_EMAIL`, `UNKNOWN`.

Ausgangsregeln (Recherche 24.09.2026, **keine Rechtsberatung**): E-Mail, WhatsApp/SMS und
Kontaktformular ohne Einwilligung → `DO_NOT_EMAIL` (DE § 7 Abs. 2 Nr. 3 UWG; IT Art. 130
Codice Privacy, Garante Provv. 149/2021). Brief und Telefon → `REVIEW_REQUIRED`. Mit
belegter Einwilligung → `CONTACT_ALLOWED`. Regeln, die über zwölf Monate nicht geprüft
wurden, erlauben nichts mehr von selbst. Alles in der Oberfläche änderbar, mit Prüfspur.

Automatisch versendet wird nur bei: freigegebenem Text **und** `CONTACT_ALLOWED` **und**
eingeschaltetem Versand **und** nicht gezogener Notbremse **und** eingehaltenen Grenzen
(Tag, Stunde, Pause, Domain, Fehler, Bounces). Jeder verhinderte Versuch wird festgehalten.

## Phasen

| Phase | Stand |
|---|---|
| 1 Analyse, Datenmodell, Architektur | fertig |
| 2 Firmenrecherche DE/IT | fertig (OSM/Overpass, Dubletten über Domain → Quelle → Name+PLZ → Adresse) |
| 3 Audit-Engine | fertig (7 echte Websites im Prüfstand geprüft) |
| 4 Opportunity Score | fertig |
| 5 Claude-Analyse | fertig, braucht `ANTHROPIC_API_KEY` |
| 6 CRM-Dashboard | fertig |
| 7 Kontakttexte | fertig (Regeltext sofort, Claude-Text mit Nachbesserung) |
| 8 Compliance-Gate | fertig |
| 9 Manuelle Freigabe | fertig |
| 10 E-Mail-Integration | fertig über Brevo, **ab Werk aus** |
| 11 Antworttracking | fertig (von Hand eintragen + Einordnung); Postfach-Anbindung offen |
| 12 Audit-Landingpages | fertig (`analyse.php`) |
| 13 Optimierung/Skalierung | offen: Postfach (IMAP), PSI-Schlüssel, Überwachung der Recherche im Cockpit |
