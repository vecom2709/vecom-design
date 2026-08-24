# Vecom Design — Website-Übergabe

Stand: 24.08.2026 · Sprachen: Italienisch (Standard), Deutsch, Englisch

---

## 1. Was hier drin liegt

```
vecom-design/
├── index.html              Startseite (alle Sektionen)
├── legal.html              Impressum & Datenschutz (vorbereitet, Platzhalter)
├── robots.txt              Suchmaschinen-Freigabe
├── sitemap.xml             Seitenverzeichnis
└── assets/
    ├── css/fonts.css       Selbst gehostete Schriften
    ├── css/app.css         Design-Tokens + alle Bausteine
    ├── js/i18n-data.js     Alle Texte in IT / DE / EN
    ├── js/app.js           Sprachwahl, Bewegung, Formular
    ├── fonts/*.woff2       Sora + Inter, lokal
    └── img/*               Logo (freigestellt), Favicons, OG-Bild
```

Reines HTML/CSS/JS. Kein Build, kein Framework, keine Abhängigkeit zu fremden
Servern. Ordner hochladen — fertig.

---

## 2. Design-DNA

| | |
|---|---|
| **Haltung** | filmisch · präzise · kühl-elegant |
| **Prinzip** | Kinotrailer-Licht: eine Lichtquelle, ein Motiv groß, viel Ruhe drumherum |
| **Farbe** | Grund `#05070D` · Fläche `#0C1119` · Text `#E9EEF8` · ein Akzent: Logo-Verlauf `#0648E8 → #1FE8FF` |
| **Typografie** | Sora (Display) + Inter (Text), Skala 1.25, fluid über `clamp()` |
| **Raster** | max. 1320 px, Abstände aus 8-px-Skala |
| **Materialität** | filmisch: Korn, Vignette, volumetrisches Licht |
| **Motion** | träge einschwingend, nur `transform` + `opacity` |
| **Nicht** | kein zentrierter Standard-Hero · keine drei gleichen Kacheln · kein Glassmorphism |

**Motion-Contract** (in `app.css` als Tokens, nie pro Baustein neu erfinden):

```
snap 120ms · ui 180ms · move 240ms · reveal 520ms · scene 900ms
Standard-Easing cubic-bezier(.16,1,.3,1) · Exit cubic-bezier(.4,0,1,1)
Staffelung 60ms · Reveal-Weg max. 24px
```

---

## 3. Texte ändern

Alle Texte stehen in `assets/js/i18n-data.js` — dreimal derselbe Schlüsselbaum
(`it`, `de`, `en`). Ein Text wird an genau einer Stelle geändert.

Im HTML zeigen die Attribute auf diese Schlüssel:

| Attribut | Wirkung |
|---|---|
| `data-i18n="services.s1t"` | ersetzt den Text des Elements |
| `data-i18n-list="services.s1l"` | baut eine Liste, Einträge mit `\|` getrennt; ein Eintrag, der auf `:` endet, wird zur Zwischenüberschrift |
| `data-i18n-attr="placeholder:contact.phMsg"` | setzt ein Attribut |

**Wichtig:** Die italienischen Texte stehen zusätzlich fest im HTML. Das
verhindert Layout-Sprünge und sorgt dafür, dass Suchmaschinen Inhalt sehen.
Wer italienischen Text ändert, ändert ihn an **beiden** Stellen — oder lässt
das Hilfsskript neu laufen (siehe Punkt 8).

Sprachwahl: `?lang=de` in der Adresse erzwingt eine Sprache und ist teilbar.
Ohne Angabe entscheidet zuerst die letzte Wahl des Besuchers, dann seine
Browsersprache, sonst Italienisch.

---

## 4. Was noch eingetragen werden muss

Alle offenen Stellen stehen in `[eckigen Klammern]` — Suche nach `[` findet sie.

- [ ] E-Mail-Adresse — `index.html`: `data-mailto="[E-MAIL]"` im Formular **und** `contact.dd1` in allen drei Sprachen
- [ ] Telefonnummer — `contact.dd2`
- [ ] Anschrift — `contact.dd3`
- [ ] P. IVA / USt-IdNr. — `contact.dd4`
- [ ] Ort im Hero — `hero.m3d`
- [x] Alle drei Pakete eingetragen: Starter 499 € + 39 €/Monat · Business 899 € + 69 €/Monat · Premium 1.499 € + 99 €/Monat
- [ ] Laufzeit und Kündigungsfrist der monatlichen Wartung (`plans.note`, alle drei Sprachen)
- [ ] Steuerlicher Hinweis prüfen: aktuell „zzgl. MwSt." (`plans.priceNote`)
- [ ] `legal.html`: Firmierung, Anschrift, Verantwortlicher, Hosting-Anbieter
- [ ] Projekt-Screenshots statt der farbigen Poster-Flächen (siehe Punkt 6)
- [ ] Rechtstexte vor dem Livegang prüfen lassen

---

## 5. Logo

Aus der gelieferten JPEG-Datei erzeugt:

| Datei | Verwendung |
|---|---|
| `logo-mark.webp/png` | nur das „V" — Kopfzeile, Hero-Bildmarke, Favicons |
| `logo-full-dark.webp` | vollständige Sperrmarke, **helle Wortmarke** für dunklen Grund |
| `logo-full-light.webp` | Originalfarben, freigestellt, für hellen Grund |
| `favicon-32.png`, `apple-touch-icon.png`, `icon-512.png` | Browser & Startbildschirm |
| `og-image.jpg` | Vorschaubild beim Teilen (1200 × 630) |

Der weiße Hintergrund wurde herausgerechnet, die dunkle Wortmarke für dunklen
Grund in helle Tinte umgesetzt, die blaue Bildmarke unverändert gelassen.

**Empfehlung:** Sobald eine Vektordatei (SVG/AI/EPS) des Logos vorliegt, diese
einsetzen. Die PNG-Freistellung ist gut, aber eine Vektorkante ist bei großer
Darstellung schärfer und kleiner in der Datei.

---

## 6. Projekt-Screenshots einsetzen

Die Karten in `#work` tragen aktuell prozedurale Farbflächen. Für ein echtes
Bild:

```html
<article class="card card--a" style="--p1: …; --p2: …;">
  <img class="card__poster" src="assets/img/work-mensaena.webp"
       alt="" width="1200" height="900" loading="lazy"
       style="object-fit:cover; width:100%; height:100%;">
  …
```

Damit alle Bilder zusammenpassen: gleiche Aufnahmebreite (1440 px Browser),
gleicher Ausschnitt, danach dieselbe Farbgradierung — sonst zerfällt die Reihe
optisch. WebP, max. 1200 px breit, unter 150 KB pro Bild.

---

## 7. Messwerte beim Launch

Playwright, CPU 4× gedrosselt, fünf Breakpoints (360 / 414 / 768 / 1280 / 1920),
jeweils zusätzlich mit `prefers-reduced-motion`:

| Wert | Ergebnis |
|---|---|
| Konsolenfehler | 0 |
| Fehlgeschlagene Requests | 0 |
| Requests gesamt | 7 |
| Übertragen | 168 KB |
| LCP | 0,53 – 1,63 s |
| CLS | 0 – 0,045 |
| Horizontaler Overflow | keiner |

Diese Zahlen sind der Vergleichsmaßstab. Wenn später etwas hinzukommt
(Schriften, Bilder, Skripte), erneut messen — Drift zeigt sich nur im Vergleich.

---

## 8. Hilfsskript: italienische Standardtexte neu ins HTML schreiben

Nach Änderungen an den italienischen Texten in `i18n-data.js`:
Das Skript trägt sie erneut fest ins HTML ein (verhindert Layout-Sprünge und
leere Seiten für Suchmaschinen). Es wurde beim Bau verwendet und kann bei
Bedarf erneut bereitgestellt werden — alternativ die italienische Fassung von
Hand an beiden Stellen pflegen.

---

## 9. Rechtliches & Datenschutz

- Schriften liegen lokal — **keine** Verbindung zu Google Fonts
- keine Tracking-Cookies, keine Analyse-Skripte, keine externen Einbindungen
- das Kontaktformular öffnet das E-Mail-Programm des Besuchers; von dieser Seite
  werden keine Daten übertragen oder gespeichert

**Damit ist kein Cookie-Banner erforderlich.** Sobald Analyse, Karten-Einbindung
oder ein Formular-Dienst dazukommt, ändert sich das — dann sind Einwilligung und
angepasste Datenschutzerklärung nötig.

Schriftlizenzen: Sora und Inter stehen unter der SIL Open Font License 1.1,
kommerzielle Nutzung und Selbst-Hosting eingeschlossen.

---

## 10. Nächste sinnvolle Ausbaustufe

1. **Echte Screenshots** der sechs Projekte — der größte Qualitätssprung, noch vor jedem weiteren Effekt.
2. **Projekt-Detailseiten** (`/lavori/mensaena.html`) mit Ausgangslage, Vorgehen, Ergebnis. Referenzen überzeugen erst mit Geschichte.
3. **Echte Sprachpfade** `/it/`, `/de/`, `/en/` statt Umschaltung im Browser — bessere Indexierung, `hreflang` zeigt dann auf getrennte Adressen.
4. **Formular-Dienst** statt `mailto:` (z. B. Formspree, Basin), sobald Anfragen laufen — `mailto:` scheitert bei Besuchern ohne eingerichtetes Mailprogramm.
5. **Scroll-Choreografie mit GSAP + Lenis** für die Prozess-Sektion, falls die Seite noch filmischer werden soll. Bewusst nicht eingebaut: 168 KB ohne jede Abhängigkeit sind ein Wert für sich.


---

## 11. Paketstruktur

Jedes Paket besteht aus sechs Schlüsseln, dreimal übersetzt:

| Schlüssel | Inhalt | Beispiel (de) |
|---|---|---|
| `plans.n1` | Name | Starter (n2 Business, n3 Premium) |
| `plans.s1` | Untertitel | Der perfekte Einstieg |
| `plans.p1` | Einmalpreis | 499 € |
| `plans.m1` | Monatsbeitrag | + 39 € monatliche Wartung |
| `plans.f1` | Leistungen, mit `|` getrennt | Enthalten:\|Moderne Webseite bis 5 Seiten\|… |
| `plans.i1` | Zielgruppe | Ideal für Selbstständige … |

`plans.priceNote` („einmalig · zzgl. MwSt.") gilt für alle drei. Wer nach
Kleinunternehmerregelung arbeitet, ersetzt den Zusatz in allen drei Sprachen.


---

## 12. Höhe, Schatten und Rahmen

Alle Flächen liegen sichtbar **auf** dem Grund. Dafür sorgen drei Dinge
zusammen — Schatten allein reicht auf dunklem Untergrund nie:

1. **Lichtkante oben** (`inset 0 1px 0 rgba(255,255,255,…)`) — simuliert die
   obere Kante einer erhöhten Fläche.
2. **Zwei Schatten** — ein kurzer, harter Kontaktschatten direkt unter der
   Kante und ein weiter, weicher Streuschatten.
3. **Fläche heller als Grund** — die Karten haben einen leichten Verlauf von
   `--c-surface-2` nach `--c-base-2`.

Tokens dafür: `--elev-1` (ruhige Flächen), `--elev-2` (Karten, Pakete),
`--elev-hover` (Zeiger darüber, mit blauem Lichtsaum).

**Schrift:** `--ink-drop` / `--ink-drop-s` als Textschatten für Überschriften,
`--ink-filter` für die Hero-Zeile. Wichtig: Bei Text mit Farbverlauf
(`background-clip: text`) **kein** `text-shadow` verwenden — der Schatten
schiene durch die transparenten Buchstaben. Dort wird `filter: drop-shadow()`
eingesetzt (`.hero h1`, `.step__no`, `.pillar strong`).

**Rahmen:** Die Klasse `.framed` legt eine 1px-Lichtkante als Verlauf um eine
Fläche (`mask-composite`, kein echter Rand — dadurch verläuft die Kante von
hell nach dunkel wie bei einem angeleuchteten Objekt).

Farbe der Kante über drei Tokens: `--frame-a`, `--frame-b`, `--frame-c`.

**Goldene Fassung** — eine Zeile genügt, nichts anderes muss geändert werden:

```html
<body data-frame="gold">
```

Standard ist die Markenkante (Blau → Cyan), weil sie mit dem Logo zusammenfällt.
Gold ist als Alternative eingebaut und über dasselbe Attribut umschaltbar.


---

## 13. Knopf-System

Ein Bauteil, zwei Gewichte — nie pro Sektion neu erfinden.

| | Aussehen | Einsatz |
|---|---|---|
| `.btn` | dunkler Körper, feine Lichtkante, 1px Innenrand | zweite Wahl, Nebenwege |
| `.btn.btn--primary` | heller Körper mit Verlauf, Lichtkante oben, Kontaktschatten unten | genau eine Zielhandlung je Bildschirm |

Beim Überfahren fährt bei beiden die Markenfüllung von unten ein, der Knopf hebt
sich 2 px, der Schatten bekommt einen blauen Saum, und beim hellen Knopf läuft
einmal ein Glanz quer darüber — dieselbe Bewegung wie der Lichtstreifen im Hero.
Beim Drücken sinkt er zurück auf die Grundlinie.

Eckenradius über `--btn-radius` (Standard 14 px, in den Paketkarten 12 px).
Der Pfeil ist eine SVG-Linie, kein Textzeichen — Textpfeile werden je nach
Schrift und Betriebssystem unterschiedlich hoch gesetzt.

**Ein Fallstrick, der beim Bauen auftrat:** Der Absendeknopf war 90 statt 50 px
hoch. Ursache war nicht der Knopf, sondern das Formularraster: Die Kontaktspalte
daneben ist höher, das Raster verteilte die Resthöhe auf seine Zeilen. Gelöst
mit `align-content: start` auf `.form`.


---

## 14. Die immersive Fassung (`world.html`)

Zweite, eigenständige Seite. Die klassische Startseite bleibt unberührt und ist
aus der Erfahrung heraus jederzeit erreichbar ("Klassische Fassung").

**Konzept:** Die Bildmarke ist kein Bild, sondern ein Körper. Die Silhouette
wurde per Konturerkennung aus der Logodatei ausgelesen (zwei Polygone: linker
Schenkel, rechter Schenkel mit Kerbe) und in `assets/js/world/logo-shape.js`
abgelegt. Daraus entsteht zur Laufzeit eine extrudierte Geometrie mit Fase —
kein nachgebautes „V", keine 3D-Datei, 0 KB Ladekosten.

**Drehbuch — sechs Beats über die Scrollstrecke:**

| Beat | Aussage | Kamera | Licht |
|---|---|---|---|
| 01 Ankunft | Jede Marke beginnt im Dunkeln | weit, tief, ruhig | nur Kantenlicht |
| 02 Das Licht | Zuerst kommt das Licht | Dolly seitlich heran | Hauptlicht fährt hoch |
| 03 Das Material | Dann das Material | Dreiviertel, mittlere Distanz | Klarlack, Reflexe |
| 04 Der Schnitt | Ein Zeichen entsteht durch das Weglassen | nah an der Kerbe | hartes Kantenlicht |
| 05 Der Raum | Und dann bekommt es einen Raum | Kranfahrt zurück | Boden, Kontaktlicht |
| 06 Ruhelage | Deine Marke, im Licht | frontal, Augenhöhe | ausgeglichen, CTA |

Alle Werte je Beat stehen in `assets/js/world/story.js` in einer einzigen
Tabelle — Kameraposition, Blickpunkt, Position und Drehung der Marke,
Nebeldichte, Lichtstärke und -position, Bloom, Rauheit, Lichtsaum. Wer die
Inszenierung ändern will, ändert Zahlen in dieser Tabelle, nichts anderes.

**Wichtig beim Bearbeiten:** Die Timeline-Startzeiten sind Sekunden (0,1,2,…),
nicht Anteile. Zusammen mit `end: story.offsetHeight - innerHeight` liegt Beat i
dadurch exakt auf Kapitel i. Beides zusammen ändern oder gar nicht.

**Stack:** Three.js 0.185 (lokal), GSAP + ScrollTrigger, Lenis. Ein Scrollsystem:
Lenis treibt ScrollTrigger. Kein CDN, keine Buildkette.

**Warum es teuer aussieht** (in dieser Reihenfolge gebaut, nicht andersherum):
1. *Licht vor Material.* Eigene Studioumgebung aus emissiven Flächen (Softbox
   oben, blaue Wand links, cyan Kante rechts). Metall kann nur spiegeln, was da
   ist — in einer schwarzen Umgebung wird jede Metallfläche schwarz.
2. *Struktur statt Farbe.* Gebürstete Rauheitskarte und anisotrope Reflexe.
   Ohne sie bleibt die Vorderfläche eine tote Farbe: der häufigste Grund, warum
   3D im Web nach Plastik aussieht.
3. *Kamera über ein Rig.* Nie direkte Positionen, immer gedämpft angefahren.
4. *Zurückhaltendes Bloom* (Schwelle 0.95). Bloom rettet kein schlechtes Licht.

**Adaptive Qualität:** vier Stufen (ultra/hoch/mittel/niedrig), automatisch nach
Gerät gewählt und über die Frame-Zeit nachgeregelt — reduziert wird in fester
Reihenfolge: Postprocessing → Partikel → Pixeldichte → Schatten. Manuell
umstellbar in der Kopfzeile. Es wird nie automatisch hochgestuft; ein Sprung hin
und her fällt mehr auf als eine dauerhaft niedrigere Stufe.

**Drei Fallback-Wege**, alle geprüft: kein WebGL, `prefers-reduced-motion`,
verlorener GL-Kontext. In jedem Fall wird die Seite zur ruhigen, vollständig
lesbaren Textfassung — es fehlt kein Inhalt, weil der Inhalt im DOM liegt und
die Inszenierung nur die Schicht darüber ist.

**Mobil** ist eine eigene Komposition, keine verkleinerte: Marke mittig statt
seitlich versetzt, Kamera weiter zurück, Kapitelleiste unten statt rechts,
Qualitätswahl ausgeblendet.

**Noch nicht geprüft:** die Bildrate auf echter Hardware. Der Testbrowser
rendert in Software; die dort gemessenen Werte sagen nichts aus. Vor dem
Livegang auf einem normalen Rechner und einem Mittelklasse-Telefon ansehen und
bei Bedarf die Standardstufe in `quality.js` senken.


---

## 15. Suchmaschinen: Sprachen als eigene Adressen

Die Umschaltung im Browser reicht Menschen, aber nicht Google: Indexiert wird
eine Adresse, kein Zustand. Deshalb gibt es jetzt drei Adressen:

| Adresse | Sprache | Rolle |
|---|---|---|
| `/` | Italienisch | Standard, `x-default` |
| `/de/` | Deutsch | eigene Seite, eigener Titel |
| `/en/` | Englisch | eigene Seite, eigener Titel |

Erzeugt werden `/de/` und `/en/` aus `index.html` und `i18n-data.js`:

```
node build.mjs
```

Das Skript setzt die Texte fest ein, tauscht `<html lang>`, Titel, Beschreibung,
`canonical`, `og:*` und `hreflang`, korrigiert die Pfade (`../assets/`) und macht
aus der Sprachwahl **echte Links** — Knöpfe folgt keine Suchmaschine.
`data-lang-fixed` auf `<html>` verhindert, dass die gespeicherte Sprachwahl eine
statische Seite überschreibt.

**Nach jeder Textänderung `node build.mjs` laufen lassen.** Der GitHub-Actions-
Workflow tut das bei jedem Push automatisch.

Weiter enthalten:
- `sitemap.xml` mit allen vier Adressen und wechselseitigen `hreflang`-Angaben
- strukturierte Daten (`ProfessionalService`) inklusive der drei Pakete als
  `Offer` mit Preis und Währung — damit kann Google Preise als Merkmal lesen
- `robots.txt` mit Verweis auf die Sitemap, Rechtsseite ausgenommen

**Was für lokale Suche noch fehlt:** Ort und Adresse. Sobald `[Città]` und die
Anschrift feststehen, gehören sie in `hero.m3d`, in `contact.dd3` und als
`address` plus `geo` in die strukturierten Daten. Ohne Ort ist eine lokale
Platzierung („webdesign + Stadt") praktisch nicht zu erreichen.

---

## 16. Schrift

Display ist **Archivo** (variabel, Breitenachse 62–125 %), Text bleibt **Inter**.

Die Breitenachse ist der eigentliche Grund für die Wahl: Überschriften laufen auf
110 % leicht breit und nehmen damit die weite Sperrung der Wortmarke
„VECOM DESIGN" auf; Labels und Marke laufen auf 92–96 % schmaler. Eine Schrift,
zwei Stimmungen, kein zweiter Font. Beide Schriften sind auf die benötigten
Zeichen verkleinert und liegen lokal (kein Google-Request).


---

## 17. Projektkarten: Vorführung echter Seiten

Die vier Karten mit Browserrahmen zeigen **echte Vollseiten-Aufnahmen** der
laufenden Projekte (mensaena.de, dragis-kitchen.de, Charme Color, Buchwebsite),
aufgenommen mit Playwright bei 1280 px Breite. Darüber läuft ein Drehbuch:
scrollen, Zeiger bewegen, klicken, Ladebalken, weiterscrollen. Es wirkt wie eine
Bildschirmaufnahme, ist aber **ein einziges Bild** — ein Video derselben Länge
wöge ungefähr das Zwanzigfache.

- Aufnahmen erneuern: `node capture.mjs` (Skript liegt im Projektordner unter
  `tools/`, falls die Seiten sich ändern) — Cookie-Schichten werden vor der
  Aufnahme nur **ausgeblendet**, nicht bestätigt.
- Steuerung: `assets/js/screens.js`. Das Drehbuch steht dort oben als Liste
  (`SCRIPT`) — scrollen, zielen, klicken, laden. Werte sind Anteile, keine Pixel,
  damit es in jeder Kartengröße stimmt.
- Läuft nur, wenn die Karte im Bild ist, hält im Hintergrund-Tab an, bei
  `prefers-reduced-motion` bleibt ein Standbild. Klick auf die Karte hält an
  oder setzt fort.
- In der Adresszeile steht die **tatsächliche** Adresse. Für Projekte ohne
  eigene Domain ist das die GitHub-Pages-Vorschau — keine erfundenen Adressen.

TerraViva Sicilia und Vecom Shop haben noch keine laufende Seite; sie behalten
ihr Motivbild, aber dieselbe Kartenstruktur (Medium oben, Text unten). Sobald
diese Seiten online sind, dieselbe Behandlung geben.
