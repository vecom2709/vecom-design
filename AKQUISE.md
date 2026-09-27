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

## Der Alltag (seit 26.09.2026)

1. **Suchen:** Auf „Betriebe“ einen Ort eintippen → „Jetzt suchen“. Nachts sucht der Rechner die Betriebe und prüft ihre Websites.
2. **Auswählen:** Morgens die Liste — Chance, Hauptproblem, Ampel. Je Zeile ein Knopf „nächster Schritt“.
3. **Brief:** „Brief schreiben“ → Text lesen → „Text ist gut — freigeben“ (schaltet die Analyse-Seite ein) → „Brief drucken“ → einwerfen → „Als verschickt vermerken“.
4. **Anruf** (deutschsprachige Betriebe): Anrufzettel mit Einstieg aus den Befunden und Antworten auf Einwände. Ergebnis mit einem Klick; „bitte per Mail“ hält die Einwilligung fest — danach ist die E-Mail erlaubt.
5. **Antwort:** eintragen; „kein Interesse“ sperrt für immer. Montags kommt ein Wochenbericht aufs Handy.

Ampel: grün = E-Mail erlaubt (Einwilligung), gelb = Brief (oder Anruf), rot = nicht ansprechen, grau = schon kontaktiert oder unklar.

### Seit 26.09.2026 dazu

- **Einwilligung per Link:** Kasten auf der Analyse-Seite oder Link/QR aus der Firmenansicht → Bestätigungsmail → erst der Klick setzt die Einwilligung (Beleg mit Wortlaut, Zeit, Adresse; IP nur als Hash). Danach ist die E-Mail erlaubt.
- **Brief per Klick:** freigegebener Brief → „Preis und Blatt vom Briefdienst holen“ (ufficiopostale.com, kostet nichts) → „Jetzt verschicken“ (Rückfrage). Nur Italien. Testbetrieb (Sandbox) ist ab Werk an; Schlüssel und Umschalter unter Regeln & Versand → Briefdienst. Absender aus Einstellungen → Firma (Straße, PLZ, Ort mit Provinz).
- **Analyse-Seite** jetzt in Gold, mit Skizze der neuen Startseite (nur Branchen mit passendem Bild, `AkquiseAnalyse::SKIZZE`).
- **Signal-Wecker:** Website ausgefallen, Zertifikat ungültig oder bald fällig → Meldung und Kasten „Signale“ auf der Betriebe-Seite. Kein Versand.
- **Wiedervorlage:** 5 Tage nach dem Brief (3 nach der E-Mail) Meldung mit Stand (Analyse-Seite geöffnet? Antwort?). Keine zweite Ansprache — das Gate bleibt.
- **Karte** (Reiter) und **Auswertung** (Trichter nach Branche/Kanal/Text, Wochenziel).
- **Text A/B:** B = „Skizze zuerst“, nur Briefe an Branchen mit Skizze, hälftig nach Kennung; Vergleich unter Auswertung → nach Text A/B.
- **Partner:** Firmen, die ein Partner reserviert hat (Firmen-Finder, 60 Tage), sperrt das Gate für die eigene Akquise.

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
| 11 Antworttracking | fertig (von Hand + Postfach alle 10 Minuten, seit 27.09.2026) |
| 12 Audit-Landingpages | fertig (`analyse.php`) |
| 13 Optimierung/Skalierung | offen: PSI-Schlüssel, Überwachung der Recherche im Cockpit |

## Abgleich mit dem Gesamtauftrag vom 27.09.2026

Am 27.09.2026 kam der Auftrag für ein „weitgehend automatisiertes Akquise-,
Lead- und Follow-up-System“ mit 32 Punkten. Das Ergebnis der
Bestandsaufnahme: Etwa drei Viertel davon stehen oben schon, oft genauer als
verlangt. Die folgenden Punkte sind ebenfalls vorhanden:

- Dubletten (19)
- Abmeldung per Knopf und RFC 8058 (20)
- Sperrliste (21)
- Grenzen mit Stopp bei Bounces (22)
- Protokoll und Prüfspur (23)
- Notbremse (28)
- Briefdienst-Sandbox (29, für Briefe)
- Trichter nach Branche, Kanal und Text als lernender Teil (18)
- Angebote nur mit Freigabe (16)
- Rückruf von der Website

### Lücken

| # | Fehlt | Einschätzung |
|---|---|---|
| A | **Öffentlicher Website-Check** (7+8): Anfrage und Marketing-Einwilligung getrennt, Ergebnis sofort, Double-Opt-in | Der einzige Weg, auf dem Betriebe von selbst kommen und eine Einwilligung entsteht. **Zuerst gebaut.** |
| B | **Folgesequenz** Tag 0/3/7/14/30 für Betriebe mit bestätigter Einwilligung, pausiert bei Antwort, endet bei Abmeldung/Kunde (10) | Technisch klar. Offen: Dürfen Folgemails ohne Einzelfreigabe hinaus? Heute gibt Uwe jede Mail frei. **Entscheidung Uwe.** |
| C | **Terminbuchung** mit freien Zeiten, Sprache, Thema, Erinnerung (15) | Rückruf gibt es, ein Kalender fehlt. **Entscheidung Uwe:** eigener Kalender oder vorhandener Dienst. |
| D | **Assistent in der Verwaltung** (14) | Braucht einen Claude-Schlüssel auf dem Server (Kosten je Frage). Ohne Schlüssel: feste Fragen als Filter. **Entscheidung Uwe.** |
| E | **Einzelschalter** Recherche/Audit/KI/Follow-up/Automatik und allgemeiner Sandbox-Modus (28, 29) | Heute: E-Mail an/aus, Notbremse, Briefdienst-Sandbox. Klein, sobald B steht. |
| F | **Frist-Löschung** für Anfragen (24) | Kommt mit A: 180 Tage ohne Einwilligung oder Auftrag, danach anonymisiert. |
| G | **Pipeline-Stufen** Termin/Angebot/Verhandlung/Verloren in der Akquise-Anzeige (17) | Heute endet die Akquise bei „Kunde“ und geht an Vorgang/Angebot über. Wird nur als Anzeige ergänzt. |

Bewusst nicht gebaut werden: Scraping von Google Maps oder Indeed (Uwe: nie),
automatisch versandte rechtsverbindliche Angebote und Werbemails ohne
Rechtsgrundlage. Neue Technik kommt nicht dazu. Jeder Weg nach draußen läuft
weiter über `AkquiseGate::pruefen` und `versandSperre`.

### Reihenfolge

A (fertig, 27.09.2026) → B und E (fertig, 27.09.2026) → D (Assistent ohne KI-Kosten) → C (Terminbuchung) → G.
Uwes Entscheidungen vom 27.09.2026: Folge-Mails „Texte freigeben, dann automatisch“, Assistent ohne KI-Kosten, Terminbuchung mit eigenem Kalender.
Jede Stufe endet mit Kette, Prüfung im Browser, Uwes Ja und Live-Prüfung.

### Modul A: Website-Check (27.09.2026)

- `website-check.php`: öffentlich, dreisprachig, ohne Skript.
- Zwei getrennte Häkchen:
  1. Nur wer die **ausführliche Analyse** anfragt, bekommt eine Antwort von Uwe. Das ist eine Anfrage, keine Werbung.
  2. **Marketing-Einwilligung:** nie vorausgewählt, der Wortlaut steht daneben, danach folgt der Double-Opt-in über `AkquiseEinwilligung` (Quelle `check`).
- Das Ergebnis (die 6 Punkte aus `PartnerCheck`) erscheint sofort. Unter `website-check.php?t=…` bleibt es abrufbar, ohne Name und ohne E-Mail.
- Der Betrieb wird über die Dublettenprüfung angelegt oder ergänzt (Quelle `website-check:<domain>`). Das tiefe Audit merkt der Worker vor.
- Bremsen:
  - Honeypot und 20 Sekunden Sperre je Adresse
  - 5 Checks je Adresse und Tag, 3 je Domain, 200 insgesamt
  - Die Bestätigungsmails sind zusätzlich begrenzt.
- Aufbewahrung: Name, E-Mail, Telefon und IP-Hash werden nach 180 Tagen geleert, wenn keine Einwilligung und kein Auftrag daraus wurde (Cron `akquise_checks`).
- Schalter „Website-Check an/aus“ unter Regeln & Versand.

### Module B und E: Folge-Mails, Schalter, Testbetrieb (27.09.2026)

- **Schalter** unter Regeln & Versand: Automatik (Hauptschalter), Betriebe suchen, Websites prüfen, Texte von Claude, Folge-Mails. Der Worker-Endpunkt gibt bei ausgeschaltetem Teil keine Arbeit heraus. Die Notbremse steht über allem.
- **Testbetrieb** (ab Werk an): Jede Akquise-Mail durchläuft Gate, Freigabe und Grenzen wie echt, am Ende steht nur „simuliert“. Ein gemeinsamer Versandweg `AkquiseVersand::rausschicken` bedient Einzelmail und Folge-Mails.
- **Folge-Mails** (`AkquiseFolge`, Reiter „Folge-Mails“):
  - Fünf Schritte je Sprache (Tag 0/3/7/14/30), jeder Text wird einmal freigegeben. Jede Änderung macht ihn wieder zum Entwurf.
  - Start mit dem Klick in der Bestätigungsmail.
  - Vor jeder Mail wird neu geprüft: Gate „Ja, erlaubt“, `versandSperre` (ohne Domain-Abstand, weil der Betrieb eingewilligt hat) und Hindernisse (Antwort pausiert; Abmeldung, Sperre, Kunde oder fehlende Einwilligung beenden).
  - Der Testbetrieb schiebt die echte Folge nie weiter.

### Modul D: Assistent ohne KI-Kosten (27.09.2026)

- Reiter „Assistent“ (`AkquiseAssistent`): neun feste Fragen als Knöpfe, dazu ein Eingabefeld. Sätze wie „Zeig mir die besten Leads aus Sizilien“ werden über Schlüsselwörter, Branchen- und Ortsnamen einer Frage mit Filter zugeordnet, und die Seite zeigt, was verstanden wurde.
- Jede Zeile trägt die Ampel des Gates („Darf ich?“). E-Mail-Adressen zeigt nur „Wer darf per E-Mail?“, und nur bei bestätigter Einwilligung mit Gate „Ja, erlaubt“.

### Modul C: Terminbuchung (27.09.2026)

- `termin.php` (öffentlich, dreisprachig, ohne Skript) und der Reiter „Termine“ (`AkquiseTermin`). Freie Zeiten ergeben sich aus dem Wochenplan, der Dauer, dem Vorlauf, dem Horizont und den gesperrten Tagen.
- Eine Zeit lässt sich nicht doppelt vergeben: Der eindeutige Schlüssel `(beginn, belegt)` in der Datenbank verhindert es, eine Absage setzt `belegt` auf NULL.
- Nach dem Buchen gehen eine Bestätigung mit Absagelink und eine Kalenderdatei (.ics) an den Buchenden, Uwe bekommt eine Meldung. Am Vortag folgt eine Erinnerung (Cron `akquise_termine`, genau einmal). Sagt Vecom ab, bekommt der Kunde eine Mail mit dem Link zu einer neuen Zeit.
- Die Folge-Mail „Gespräch“ bekommt über `{termin}` den Buchungslink, sobald es freie Zeiten gibt.

### G: Pipeline an der Firma und günstigerer Brief (27.09.2026)

- An der Firma steht eine Leiste: Neu → Analysiert → Qualifiziert → Kontaktweg → Kontaktiert → Interesse → Termin → Angebot → Verhandlung → Gewonnen, dazu Verloren. Sie wird aus Audit, Score, Gate, Versand, Antworten, Website-Check und Terminen gerechnet (`Akquise::pipeline`). Übersprungene Stufen sind durchgestrichen, und jede Stufe nennt ihren Grund.
- Nur Angebot, Verhandlung, Gewonnen und Verloren setzt Uwe selbst (Spalte `pipeline`). „Gewonnen“ macht den Betrieb zum Kunden, „Verloren“ sperrt nicht. Beide beenden die Folge-Mails.
- Briefdienst mit zweiter Versandart: Posta Massiva über dieselbe Openapi-Schnittstelle (`/posta_massiva/`), laut Anbieter ab ca. 0,77 € + IVA statt ca. 1,38 € + IVA. Ab Werk bleibt es bei Posta Ordinaria. Jeder Auftrag merkt sich sein Produkt.

### Briefe ausgeschaltet (27.09.2026)

Uwe: „Briefversand komplett deaktivieren, bis ich entscheide – jetzt nur E-Mail, WhatsApp.“ Ab Werk gilt `akq_brief_an = 0`, und das Gate sagt für den Kanal Brief „Nein“. Damit sind Briefdienst, Brief-Serie, Druckblatt, „Brief schreiben“, „von Hand eingeworfen“ und „Vecom soll anschreiben“ an einer einzigen Stelle zu.

- Ampel und nächster Schritt schlagen keinen Brief mehr vor. Möglich sind nur noch E-Mail mit Einwilligung oder Anruf nach Prüfung.
- Claude schreibt nur noch Texte, wo eine E-Mail erlaubt ist.
- Schlüssel, Versandart und die Regeln für Briefe bleiben gespeichert. Wieder einschalten geht unter Regeln & Versand → Briefe per Post.

WhatsApp bleibt im Gate ohne Einwilligung auf „Nein“ (DE und IT). Bis zum 27.09.2026 galt jede Einwilligung nur für E-Mail; seitdem siehe unten.

### Einwilligung für E-Mail oder WhatsApp (27.09.2026)

Uwe: „ja, erweitere auf E-Mail oder WhatsApp“. Eine Einwilligung deckt nur die Wege, die in ihrem Wortlaut stehen (`AkquiseGate::einwilligungDeckt`).

- Website-Check und Einwilligungsseite haben ein zweites, freiwilliges Häkchen „Oder auch per WhatsApp“ mit Nummernfeld. Im Website-Check gilt: leer heißt die Telefonnummer von oben. Der Wortlaut hat eine eigene Fassung (`v2wa-2609`) und nennt die Nummer. Die Bestätigungsmail zeigt ihn, der Klick darin bestätigt beides.
- Eine unlesbare oder leere Nummer ist ein Fehler. Es gibt dann keine stille reine E-Mail-Einwilligung. Eine Nummer auf der Sperrliste führt zu keiner Mail.
- Nach dem Klick stehen an der Firma `einwilligung_kanaele = email,whatsapp` und `whatsapp`. Eine spätere reine E-Mail-Einwilligung nimmt WhatsApp nicht weg.
- Die Regeln (Migration 089) lauten: WhatsApp mit Einwilligung ist in DE und IT „Ja, erlaubt“. Für Brief, Anruf und Kontaktformular zählt die Einwilligung nicht; dort gilt die Regel „ohne“.
- An der Firma öffnet „WhatsApp-Nachricht öffnen“ WhatsApp mit einem vorgefüllten Text: Gruß, Analyse-Link falls vorhanden und der Hinweis „STOPP“. Das System schickt selbst nichts per WhatsApp; Uwe liest und sendet. „Als geschrieben vermerken“ trägt es ins Versandprotokoll ein.
- Beim Sperren kommt die WhatsApp-Nummer mit auf die Sperrliste.
- Der Assistent fragt jetzt „Wer darf per E-Mail oder WhatsApp?“, und die Spalte zeigt die Nummer oder „nur E-Mail“.
