# Überarbeitung vecom-design.it — Konzept und Budget

Festgeschrieben vor dem ersten Commit, wie der Skill `realtime-web-masterstack`
es verlangt: *„Ein Budget, das erst am Ende geprüft wird, ist kein Budget."*

## Ausgangsmessung (05.09.2026, lokal, ungedrosselt)

| Größe | Wert | Bemerkung |
|---|---|---|
| LCP | 1.976 ms | auf localhost ohne Drosselung — auf Mobilfunk deutlich darüber |
| CLS | 0,042 | in Ordnung |
| Übertragen | ~3,1 MB | ohne die nachgeladene Welt; mit ihr ~3,5 MB |
| davon Bilder | 1.332 KB | WebP |
| davon Video | 1.158 KB | `auftakt.mp4` 716 KB + `auftakt.webm` 442 KB |
| davon JS | 341 KB | plus `three.module.min.js` 365 KB, verzögert |
| davon CSS | 138 KB | `app.css` |
| davon Schriften | 131 KB | woff2 |
| Skripte im DOM | 22 | 16 davon als eigene `<script>`-Tags |
| Seitenhöhe | 16.961 px | 11 Abschnitte |

**Was schon da ist und bleibt** (nach 9 s gemessen, `data-world="on"`, keine
Konsolenfehler): Three.js-Szene als Bühne hinter dem Inhalt, Beat-System
(`site-beats.js`), adaptive Qualität mit Geräteerkennung (`quality.js`),
Bruch-Effekt (`bruch.js`), Bloom (`finish-pass.js`), vier Rückfallwege
(kein WebGL, reduzierte Bewegung, Datensparmodus, schwaches Gerät).

## Budget

| Größe | Grenze | Herkunft |
|---|---|---|
| Erstes Bild, übertragen | < 900 KB | eigener Vecom-Standard: „deutlich unter 1 MB" |
| JS fürs Erstbild | ≤ 300 KB gzip | Referenz 11 des Skills |
| LCP | < 2,5 s auf gedrosseltem Mobil | Referenz 11, Vecom-Standard |
| CLS | < 0,05 | bleibt wie gemessen |
| Bildrate | stabil auf Mittelklasse-Mobilgerät | Framezeit gemessen, nicht geschätzt |
| Texturen | ≤ 2k | außer der Zoom fordert mehr |

Geprüft wird auf 360 / 768 / 1280 / 1920 px, im Reduced-Motion-Pfad und ohne
WebGL.

## Rendering-Weg

WebGL2 über Three.js — der vorhandene Pfad. **Kein WebGPU-Build**: Die Szene
läuft fehlerfrei, der WebGPU-Build wäre deutlich schwerer, und der Gewinn wäre
an einer Logo-Szene fachlich, nicht sichtbar. Entschieden am 05.09.2026;
nachzumessen, sobald die Szene inhaltlich wächst.

## Stufen — jede für sich lauffähig

1. **~~Tag und Nacht~~ → Die Sonne wandert durch die Nacht.**
   *Verworfen und ersetzt am 05.09.2026.* Ein heller Modus war gebaut, mit drei
   Zuständen, gespeicherter Wahl und mitschaltender Szene. Er ist wieder raus,
   und der Grund ist eine Rechnung, keine Geschmacksfrage: **Glanz ist Kontrast.**
   Ein helles Glanzlicht auf einem hellen Körper vor hellem Grund liest sich als
   nichts. Drei Anläufe — helles Studio, schwarze Karten, heller Leuchtkasten —
   endeten alle beim selben Milchglas, und der additive Versuch verschluckte die
   Marke ganz. Über Weiß gibt es keinen Spielraum nach oben.

   Geblieben ist die bessere Hälfte der Idee: **Die Tageszeit steuert das Licht
   im dunklen Studio.** `World.setTageszeit()` liest die Uhr des Besuchers und
   setzt Sonnenstand, Lichtfarbe und Kantenton — morgens tief und warm von links,
   mittags hoch und fast neutral, abends warm von rechts, nachts kühl und flach.
   Der Körper bleibt immer tief, es wandert nur, woher das Licht kommt.
   Nachgezogen alle fünf Minuten und beim Zurückkehren auf den Tab.
2. **Der Auftakt kommt aus der Szene.** ✔ *Erledigt am 05.09.2026.*
   `auftakt.mp4`/`.webm` und das Posterbild sind gelöscht, der inline-Block,
   der Vorhang, die Ton- und Überspringen-Knöpfe und der CSS-Abschnitt dazu
   ebenfalls. Die Szene hatte ihren Auftakt längst — Kameraflug von außerhalb
   des Nebels, Bruch, Zusammensetzen — er war als Rückfall gebaut und ist
   jetzt die Hauptsache.

   **Gemessen: 4.036 KB → 2.852 KB.** Ein blockierender Inline-Block, ein
   Videoelement und eine ganze Zustandsmaschine weniger. Null Konsolenfehler,
   kein fehlgeschlagener Request.
3. **Gewicht.** Sprachdaten aufteilen (137 KB → nur die aktive Sprache),
   Bilder gegen echte Anzeigegrößen prüfen, Skript-Tags zusammenlegen.
4. **Drehbuch durchsehen.** `site-beats.js` ist bereits eine Beat-Timeline.
   Geprüft wird gegen die Regel: jeder Beat muss als Standbild funktionieren.
   Ersetzt wird sie nicht.
5. **Stufen-Demonstrator.** Ein Abschnitt, der die Ambitionsstufen A bis D am
   selben Beispiel umschaltbar zeigt — dieselben Stufen, die der Bau-Prompt
   der Verwaltung vergibt.

## Regel für alle Stufen

Jede eingebaute Technik braucht eine Begründung, die hier steht. Was nur
beeindruckt, ohne die Aussage zu tragen, wird gestrichen.
