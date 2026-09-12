# Vecom-Standard — wie eine Kundenseite entsteht

Dieser Standard gilt für **jede Seite, die Vecom für einen Kunden baut**. Nicht für
vecom-design.it selbst: dafür gelten `PROJEKT.md` und `CLAUDE.md` in diesem
Repository. Hier steht, was für Cavaleri gilt, für Boulevard und für jeden Kunden,
der danach kommt.

Er steht bewusst **in diesem Repository und nicht im Kundenordner**, weil er allen
Kundenordnern gemeinsam ist. Ein Standard, der in jedem Projekt neu abgeschrieben
wird, ist nach dem dritten Projekt drei verschiedene Standards.

> **Herkunft und Stand.** Grundlage ist Uwes Master-Standard vom 12.09.2026. Was
> hier steht, ist die Arbeitsfassung davon — festgehalten in der Form, in der sie
> beim Bauen wirklich benutzt wird. Vier Abschnitte des Originals sind hier nur als
> Überschrift vorhanden, weil ihr Wortlaut nicht vorliegt; sie stehen am Ende unter
> „Was noch fehlt". Erfundene Zwischenfassungen stehen dort ausdrücklich **nicht** —
> das wäre genau der Fehler, den Abschnitt 2 verbietet.

---

## 1 — Die Reihenfolge

```
KUNDENBEDARF → GESCHÄFTSZIEL → NUTZER → INHALT → GESCHICHTE
             → EMOTION → ERLEBNIS → TECHNIK
```

Technik steht am Ende, und zwar immer. Jede Entscheidung über Framework, Animation,
3D, Video oder Effekt fällt **nachdem** feststeht, wem die Seite wobei hilft, was
darauf steht und welchen Eindruck sie hinterlassen soll — nie davor.

Der häufigste Weg, wie eine Seite austauschbar wird, ist der umgekehrte: Erst wird
eine Technik gewählt, weil sie beeindruckt, dann wird Inhalt gesucht, der zu ihr
passt. Das Ergebnis sieht teuer aus und sagt nichts.

Praktische Probe vor der ersten Zeile Code: **Wenn die Antwort auf „Warum so?" eine
Technik nennt, ist die Reihenfolge verletzt.** Eine gültige Antwort nennt den
Kunden, sein Geschäft oder seinen Besucher.

---

## 2 — Erfinde niemals Unternehmensinformationen

Der wichtigste Satz des ganzen Standards. Gearbeitet wird **ausschließlich** aus:

- Fragebogen
- Gespräch mit dem Kunden
- hochgeladenen Dateien
- bestehender Website
- Marke und vorhandenen Assets
- vereinbartem Umfang
- Projektklasse und gebuchten Modulen
- freigegebenen Änderungen

Was dort nicht steht, steht nicht auf der Seite.

### Die Vertrauensstufen

Jede Information im Projekt trägt genau eine davon:

| Stufe | Bedeutung | Was damit passiert |
|---|---|---|
| `CONFIRMED` | Der Kunde hat es gesagt oder geliefert | Kommt so auf die Seite |
| `INFERRED` | Aus Vorhandenem geschlossen | Darf verwendet werden, muss aber als Annahme markiert und beim nächsten Kontakt bestätigt werden |
| `MISSING` | Fehlt, wird gebraucht | Wird gefragt. Bis zur Antwort: sichtbarer Platzhalter, nie stille Erfindung |
| `FORBIDDEN_TO_INVENT` | Darf unter keinen Umständen entstehen | Fehlt es, bleibt die Stelle leer oder die Sektion entfällt |

### Was niemals erfunden wird

Telefonnummern · Adressen · Preise · Bewertungen · Zertifizierungen · Auszeichnungen ·
Referenzen · Mitarbeiter · Kundenlogos · Firmengeschichte · Rechtsangaben ·
Produktversprechen · Garantien · technische Leistungswerte.

Diese Liste ist nicht als Beispiel gemeint, sondern als Sperre. Ein erfundenes
Kundenlogo ist eine Urheberrechtsverletzung, eine erfundene Zertifizierung ist eine
Falschangabe im Geschäftsverkehr, ein erfundener Leistungswert ist eine Zusage, für
die der Kunde haftet — nicht Vecom. Deshalb gilt sie auch dann, wenn eine Sektion
dadurch leer bleibt und die Seite „unfertig" aussieht.

Fehlt etwas aus dieser Liste, gibt es genau zwei erlaubte Wege: **fragen** oder
**die Sektion weglassen**. Ein dritter Weg wird nicht gesucht.

---

## 3 — Das Manifest ist die einzige Wahrheit

Jedes Kundenprojekt führt ein internes `VECOM_PROJECT_MANIFEST`. Darin steht, was
gilt: Klasse, Module, Umfang, Inhalte mit ihrer Vertrauensstufe, Marke, Entscheidungen,
offene Fragen, Freigaben.

Wenn Seite und Manifest sich widersprechen, hat das Manifest recht und die Seite ist
falsch — nicht umgekehrt. Wer etwas auf der Seite ändert, das im Manifest steht,
ändert zuerst das Manifest.

Im Kundenordner liegt es neben `BRIEFING.md`. Das Briefing ist, was ankam; das
Manifest ist, was daraus gilt — die beiden werden nicht vermischt.

---

## 4 — Umfangssperre

**Eine Leistung, die nicht im vereinbarten Umfang steht, wird nicht still
dazugebaut.** Sie ist eine Änderungsanfrage: benennen, Preis nennen, Freigabe
abwarten.

Das gilt in beide Richtungen und schützt beide Seiten. Der Kunde bekommt keine
Rechnung über etwas, das er nicht bestellt hat. Und Vecom baut nicht drei Stunden
umsonst, weil es „ja schnell ging". Was geschenkt werden soll, wird ausdrücklich
geschenkt und im Manifest vermerkt — nicht verschwiegen.

Der typische Fall ist harmlos und deshalb gefährlich: ein Reservierungsformular, das
niemand bestellt hat, weil es „bei einem Restaurant halt dazugehört". Dahinter hängen
E-Mail-Zustellung, Datenschutz, Pflege und eine Erwartung beim Gast, die jemand
erfüllen muss.

---

## 5 — Projektklassen

| Klasse | Name | Wofür |
|---|---|---|
| **A** | ESSENTIAL | Präsenz: da sein, gefunden werden, erreichbar sein |
| **B** | BUSINESS | Arbeitende Seite: Anfragen, Termine, Verkauf |
| **C** | PREMIUM | Gestalteter Auftritt mit eigener Handschrift |
| **D** | CGI | Gerenderte Bildwelten statt Fotos |
| **E** | CGI REALITY | Gerendert und fotorealistisch, nicht als Grafik erkennbar |
| **F** | EXPERIENCE | Erlebnis: Bewegung, Ablauf, Inszenierung tragen mit |
| **G** | SIGNATURE | Einzelstück mit Anspruch auf Auszeichnung |
| **X** | CUSTOM | Passt in keine der Klassen — Umfang wird einzeln festgelegt |

Die Klasse wird **vor dem Bauen** festgelegt und steht im Manifest. Sie entscheidet
über Aufwand, Preis, Qualitätsanforderung und darüber, was überhaupt geprüft wird.

**Eine Klasse höher als nötig ist ein Fehler, nicht Ehrgeiz.** Ein sauberes B schlägt
ein wackliges F jedes Mal — beim Kunden, bei der Ladezeit und bei der Pflege.

---

## 6 — Module

`WEB` · `BRAND` · `MARKETING` · `MOTION` · `CGI` · `CONTENT` · `VISUAL` ·
`PACKAGING` · `BUSINESS` · `TECH` · `CARE`

Module sind das, was gebucht wird. Die Klasse sagt, auf welchem Niveau gearbeitet
wird; die Module sagen, woran. Beides steht im Manifest, beides unterliegt der
Umfangssperre aus Abschnitt 4.

---

## 7 — Der Fragebogen darf den Kunden nicht erschlagen

Der Fragebogen ist dynamisch: Er fragt, was für **diese** Klasse und **diese** Module
gebraucht wird, und sonst nichts. Ein Kunde der Klasse A bekommt keine Fragen zu
Kameraführung.

Fragen kommen in der Reihenfolge aus Abschnitt 1 — erst Geschäft und Nutzer, dann
Inhalt, zuletzt Gestaltung. Wer mit „Welche Farbe hätten Sie gern?" anfängt, bekommt
eine Farbe und erfährt nichts.

Was der Kunde nicht beantwortet, wird `MISSING` — nicht geraten.

---

## 8 — Erlebnis: was immer gilt

**Ton.** Startet immer stumm. Ton läuft erst nach einer ausdrücklichen Handlung des
Besuchers an — nie beim Laden, nie beim Scrollen. Ton trägt nie Information, die es
nicht auch ohne ihn gibt, und lässt sich jederzeit abschalten.

**Bewegung.** Jede Animation braucht einen Grund: Orientierung, Rückmeldung,
Zusammenhang oder Dramaturgie. `prefers-reduced-motion: reduce` zeigt den Endzustand
sofort und lässt nichts verschwinden.

**Mobil ist eine eigene Komposition**, keine schmale Fassung der Desktop-Seite. Beide
werden gestaltet, beide werden angesehen.

**Qualitätsstufen 0–7.** Die Seite misst das Gerät und reduziert in fester
Reihenfolge. Die Inszenierung fällt zuletzt.

**Stufe 0 ist der Vertrag.** Auch ohne WebGL, ohne JavaScript-Effekte, auf dem
langsamsten Gerät trägt die Seite:

- den Inhalt
- die Navigation
- die Marke
- die wichtigste Handlung (Anruf, Anfrage, Kauf, Termin)
- die Kontaktmöglichkeit

Wenn Stufe 0 eines davon verliert, ist die Seite kaputt — nicht „reduziert".

**Barrierefreiheit: WCAG 2.2 AA.** Kontrast, Tastaturbedienung, sichtbarer Fokus,
Landmarks, sinnvolle Alt-Texte. Das ist Teil der Gestaltung, nicht eine Prüfung
danach.

**Datenschutz bei Sensoren.** Kamera, Mikrofon, Bewegung und Standort werden erst
nach ausdrücklicher Zustimmung angefragt, mit einer Begründung, die der Besucher
versteht. Ohne Zustimmung funktioniert die Seite weiter.

**Sicherheit.** CSP, SRI für alles Fremde, CORS bewusst gesetzt. Kein Fremdskript,
dessen Zweck sich nicht in einem Satz sagen lässt.

**SEO.** Titel, Beschreibung, strukturierte Daten, Sitemap, sprechende Adressen. Bei
einem Umbau: bestehende Adressen erhalten oder umleiten — Rankings sind Vermögen des
Kunden.

---

## 9 — Versagen wird geplant

Gefragt wird nicht „läuft es?", sondern **„was passiert, wenn es nicht läuft?"**:
Video lädt nicht, Schrift kommt nicht an, WebGL fehlt, Formular antwortet nicht,
Verbindung bricht mitten im Laden ab.

Für jeden dieser Fälle gibt es einen Zustand, der geplant ist und nicht nur zufällig
entsteht. Ein Ladefehler, der als leerer Kasten endet, ist kein Randfall, sondern der
Normalfall auf jedem zehnten Besuch.

---

## 10 — Kein KI-Aussehen

Verboten, weil es inzwischen jeder erkennt: violett-blaue Verläufe · organische
„Blobs" · Glaskarten überall · Neon-Akzente ohne Anlass · schwebende 3D-Kugeln, die
nichts erzählen · drei identische Feature-Karten · zentrierte Hero mit zwei Knöpfen ·
Emoji als Icon-Ersatz.

Die Gestaltung kommt aus der Welt des Kunden: seinem Material, seinem Werkzeug, seiner
Sprache, seinen Farben. Eine Schreinerei sieht nicht aus wie ein SaaS-Start-up, und
ein Transportunternehmen nicht wie eine Modemarke.

Zur Design-DNA, zum Motion-Contract und zum Quality Gate gilt der Skill
**web-design-studio**. Dieser Standard steht darüber, wo beide etwas sagen.

---

## 11 — Gebaut wird in Schichten, nicht in einem Stück

Schichten 01–20. Jede Schicht wird fertig, bevor die nächste beginnt; eine halb
gebaute Schicht wird nicht übersprungen.

*(Die Benennung der zwanzig Schichten steht im Original — siehe „Was noch fehlt".)*

---

## 12 — Fassungen 01–08: „Es läuft" ist die erste, nicht die letzte

Eine Seite durchläuft acht Fassungen. Die erste ist die, bei der alles funktioniert.
Der Standard sagt dazu den Satz, der den ganzen Unterschied macht:

> **Never stop at: IT WORKS.**

Funktionierend ist der Anfang der Arbeit. Danach kommen Genauigkeit, Rhythmus,
Typografie, Zustände, Politur — die Dinge, an denen ein Besucher „teuer" erkennt,
ohne sagen zu können, woran.

*(Die Benennung der acht Fassungen steht im Original — siehe „Was noch fehlt".)*

---

## 13 — Freigabetore 01–09

Neun Tore zwischen Auftrag und Onlinegang. Vor jedem Tor wird gezeigt, nicht
behauptet.

**Die Erste-Bildschirm-Regel:** Über den ersten Bildschirm wird entschieden, bevor
der Rest gebaut wird. Er bestimmt, was der Besucher über die ganze Seite denkt — und
er ist der teuerste Teil, um ihn spät zu ändern.

*(Die Benennung der neun Tore steht im Original — siehe „Was noch fehlt".)*

Für Vecom kommt dazu, was ohnehin gilt: **Uwe sieht jeden Zwischenstand örtlich,
bevor irgendetwas veröffentlicht wird**, und `freigeben` wird nie ohne sein
ausdrückliches Ja aufgerufen.

---

## 14 — Fertig ist nicht „läuft"

Am Ende steht eine Bewertung, keine Meinung. Bewertet wird gegen die Klasse aus
Abschnitt 5 — ein A wird nicht an einem G gemessen.

**Fertig heißt:**

- Reihenfolge aus Abschnitt 1 eingehalten, nachvollziehbar
- nichts erfunden; jede Information trägt ihre Stufe
- Manifest und Seite stimmen überein
- Umfang eingehalten, Änderungen freigegeben
- Stufe 0 trägt Inhalt, Navigation, Marke, Handlung, Kontakt
- WCAG 2.2 AA geprüft, nicht vermutet
- Messwerte vorhanden — vorher und nachher, nicht „müsste reichen"
- Konsole leer, keine fehlgeschlagenen Anfragen
- Rechtliches vollständig, mit den Angaben des Kunden
- die Seite wurde **angesehen**, auf Telefon und Desktop

**„Es läuft" steht nicht auf dieser Liste.**

---

## Was noch fehlt

Vier Abschnitte aus Uwes Original liegen hier nur als Überschrift vor. Sie werden
nicht erfunden — sie werden eingesetzt, wenn der Wortlaut wieder vorliegt:

1. **Schichten 01–20** (Abschnitt 11) — welche zwanzig, in welcher Reihenfolge.
2. **Fassungen 01–08** (Abschnitt 12) — was jede Fassung zusätzlich verlangt.
3. **Freigabetore 01–09** (Abschnitt 13) — was an jedem Tor gezeigt und entschieden wird.
4. **Der 45-Schritt-Startbefehl** für jedes neue Kundenprojekt.

Bis dahin gelten die Abschnitte 1–10 und 14 vollständig; sie tragen den Standard
allein schon.
