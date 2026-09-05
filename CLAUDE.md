# Arbeitsregeln für dieses Repository

Diese Datei sagt, **wie hier gearbeitet wird**. Was gebaut wird und warum, steht in
`PROJEKT.md` — Ziel, Zielgruppe, Stimme, Constraints, Bestand, Rubrik und ein
Entscheidungsprotokoll mit Datum. **`PROJEKT.md` gilt vor dieser Datei** und wird
gelesen, bevor irgendetwas angefasst wird; hier steht nur, was dort fehlt.

Am Ende jeder Arbeit: zwei Zeilen ins Entscheidungsprotokoll von `PROJEKT.md`,
wenn etwas entschieden wurde. Keine Protokolle — nur, was beim nächsten Mal
wieder gebraucht wird.

---

## Drei Regeln, die aus Fehlern entstanden sind

**1. Nachsehen, bevor gebaut wird.** Dieses Repository ist größer, als es aussieht,
und vieles ist schon da. Dreimal in einer Sitzung wurde etwas gebaut, das es
bereits gab — die Stufenleiste des Vorgangs stand längst an zwei Stellen. Ein
`grep` kostet zehn Sekunden, ein doppelt gebautes Bauteil kostet den Rest des Tages
und hinterlässt zwei Wahrheiten über dieselbe Sache.

**2. Messen, nicht behaupten.** „Ist jetzt schneller", „müsste gehen", „dürfte
reichen" sind keine Ergebnisse. Zahlen vorher und nachher, oder es wurde nichts
belegt. Drei Beispiele aus einer Sitzung, die ohne Messung alle anders ausgegangen
wären:

- Der Glanz der Hero-V hing nicht am Hauptlicht, sondern am `pointerLight` —
  gefunden, indem die Lichter einzeln ausgeschaltet wurden (hellstes Pixel fiel von
  254 auf 3).
- Die `.htaccess`-Cache-Regel war wirkungslos, weil Apache `<FilesMatch>`-Inhalte
  *nach* allem davor anwendet. Gefunden, indem Apache installiert und getestet wurde.
- Drei „Fehler" waren keine: Ein CLS-Sprung kam nur daher, dass im Container keine
  `system-ui`-Schrift existiert; ein 404 kam vom eingebauten PHP-Server, den Apache
  richtig routet. Beide wären fast „repariert" worden.

**3. Ein Ding je Bildschirm.** Wer zwei gleich laute Knöpfe sieht, vergleicht, statt
zu handeln. Blau heißt genau eine Sache: das, was die Führung gerade meint. Alles
andere bleibt sichtbar und anklickbar und sieht aus wie das, was es ist.

---

## Die Kette — vor jedem Deploy

`app/pruefung/kette.php` prüft den ganzen Weg von der Bestellung bis zum Abschluss.
Sie läuft in GitHub Actions vor jedem Deploy; scheitert sie, geht nichts raus.
Sie hat bisher vier echte Fehler gefunden, die im Alltag nie aufgefallen wären:
eine leere Datenbank, die sich nie einrichtete; ein Projekt, das ohne bestätigte
Zahlung entstehen konnte; eine übersprungene Zwischenstufe, die den Projektstand
blockierte; und eine Wiederholung, die bei Verklemmung nicht ansprang.
(Die hängende Migration und die Belege ohne Zahlungsdeckung fand nicht sie,
sondern ein Blick in den Browser und eine eigene Andrangsprobe — auch das gehört
zur Wahrheit über dieses Werkzeug.)

```bash
mysql -uroot -e "DROP DATABASE IF EXISTS vdkette;
                 CREATE DATABASE vdkette CHARACTER SET utf8mb4;
                 GRANT ALL ON vdkette.* TO 'vd'@'localhost';"
VD_TEST_DB_NAME=vdkette VD_TEST_DB_USER=vd VD_TEST_DB_PASS=... \
  php app/pruefung/kette.php
```

Sie **verweigert** den Start ohne `VD_TEST_DB_NAME` und bei nicht leerer Datenbank
(beides Abbruch mit Code 2). Sie läuft nie gegen die echte Datenbank, und
`config.local.php` wird dabei nie geladen.

**Wer etwas an der Kette ändert, erweitert die Prüfung.** Eine Regel ohne Prüfung
ist eine Absichtserklärung. Die Prüfung ist vom Web gesperrt — über die
Ausschlussliste im Deploy *und* über eine `.htaccess`, weil der Deploy nie löscht.

---

## Veröffentlichen

Push auf `main` genügt: GitHub Actions baut mit `build.mjs` und überträgt per lftp.
Live nach etwa vier Minuten.

**Gepusht wird nur aus dem Mac-Klon** unter Uwes Konto (siehe `PROJEKT.md`).
Arbeitet Claude aus der Cloud, ist der Weg: Dateien packen → auf den Mac schreiben →
dort auspacken → in den Arbeitsordner *und* in den Git-Klon kopieren → im Klon
committen und pushen. Auf dem rsync-Einhängepunkt kann `tar` nicht überschreiben;
deshalb `cat "$Q/$f" > "$Z/$f"` statt `tar -x` darüber. Nach dem Push die
`.git`-Objekte und `HEAD`/`refs/heads/main`/`index` zurückspiegeln, sonst weiß der
Arbeitsordner nichts vom Commit.

---

## Örtlich prüfen

```bash
php -S 127.0.0.1:8080 -t .          # PHP_CLI_SERVER_WORKERS=8 für Andrangsproben
```

MariaDB muss als Benutzer `mysql` starten. Angemeldet wird sich am Anmeldeformular
über das versteckte Feld `_csrf`; das Formular hat **keinen** `type=submit`, also
`form button` klicken.

Für alles Sichtbare: Playwright, und **hinsehen**. Code lesen ersetzt kein Sehen.
Bei Bewegung `reducedMotion: 'reduce'` setzen — sonst wartet Playwright ewig darauf,
dass ein Element „stabil" wird.

---

## Fallen, die schon Zeit gekostet haben

| Falle | Was passiert | Was zu tun ist |
|---|---|---|
| **`build.mjs` überschreibt** | Erzeugt `/de/`, `/en/`, `prezzi.html`, `assistenza.html` bei jedem Deploy neu aus `index.html`. Handänderungen an den erzeugten Dateien sind beim nächsten Deploy weg. | Immer die Quelle ändern, dann `node build.mjs`. |
| **Der Deploy löscht nie** | Entfernte Dateien bleiben auf dem Webspace liegen — auch alte, große und solche, die nicht mehr erreichbar sein sollen. | Löschen von Hand im KAS. Was gesperrt sein muss, zusätzlich per `.htaccess` sperren. |
| **Migrationen sind nicht wiederholbar** | Keine der 36 hat `IF NOT EXISTS`. Eine, die scheitert, brach früher den ganzen Lauf ab — alle späteren liefen nie. | `Einrichtung::migrieren()` überspringt jetzt nur „existiert schon" (1050/1060/1061/1091/1826) und merkt es sich. Neue Migrationen trotzdem wiederholbar schreiben. |
| **`<FilesMatch>` gewinnt immer** | Apache wendet die Inhalte *nach* allem außerhalb an. Eine Regel davor ist wirkungslos, ohne dass etwas meldet. | Beide `Header set` in denselben Block. Mit einem echten Apache testen, nicht mit `php -S`. |
| **Der eingebaute PHP-Server routet anders** | Existiert ein Ordner `app/steuerakte/`, liefert er den statt der Route. Sieht aus wie ein 404 im Programm. | Solche Befunde mit Apache gegenprüfen, bevor „repariert" wird. |
| **Lesen und Schreiben in zwei Schritten** | Nachsehen, ob es etwas gibt, und dann anlegen — dazwischen passt ein zweiter Besucher. Kostete gemessen 2 von 12 Anfragen, 4 von 12 Bestellungen, 2 von 8 Belegen. | `Db::andrang()` erkennt 1062/1213/1205; `Db::nochmal()` und `Db::transaktion($fn, 5)` wiederholen genau die. In einer Transaktion muss die Nummer **sperrend** gelesen werden (`FOR UPDATE`) — aus einem Schnappschuss kann man sich nicht herauswiederholen. |
| **Ein `catch`, das alles verschluckt** | „Gibt es schon" und „Nummer vergeben" sehen gleich aus und bedeuten das Gegenteil. Ein Beleg verschwand still, das Geld war da. | Am Schlüsselnamen unterscheiden (`Db::doppelt($e, 'uq_...')`). Was nicht gemeint war, wird geworfen, nicht geschluckt. |

---

## Code-Stil

- **Deutsch.** Klassen, Methoden, Variablen, Kommentare, Datenbankspalten, wo neu.
  Gewachsene englische Spalten bleiben, wie sie sind — nicht umbenennen.
- **Kommentare erklären das Warum, nicht das Was.** Was der Code tut, steht im Code.
  Warum er es so tut und was die Alternative gekostet hätte, steht nirgendwo sonst.
  Ein Kommentar, der einen früheren Fehler beschreibt, ist mehr wert als zehn, die
  eine Zeile nacherzählen.
- **Beträge als ganze Cent.** Nie Fließkomma für Geld.
- **Kundentexte dreisprachig**, gesammelt in `app/src/Texte.php`. Kein englischer
  Fehlertext, keine Datenbankmeldung auf einer Kundenseite.
- **Kein Geheimnis ins Repository.** Es ist öffentlich. Zugangsdaten leben nur in
  `app/config.local.php` auf dem Webspace.
- Keine `-1`-Dubletten, keine Testartefakte, kein `_to_delete/`, kein
  `app/steuerakte/` im Commit.

---

## Was ohne Rückfrage nicht passiert

Was den Kunden erreicht oder in den Büchern landet, gehört hinter eine Rückfrage —
und die Rückfrage sagt, *was* passiert und *wem*, nicht „Sind Sie sicher?". Welche
Tat wie schwer wiegt, steht an genau einer Stelle: `Ablauf::TRAGWEITE`. Jedes
Formular mit dieser Tat bekommt die Frage von dort, auch eins, das es heute noch
nicht gibt.

Automatisch laufen darf nur, was **nichts das Haus verlassen lässt** und dessen
Tatsache schon in der Datenbank steht. Alles ab Projektstand „Vorschau" bleibt am
Klick eines Menschen.
