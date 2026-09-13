# Arbeitsregeln für dieses Repository

Diese Datei sagt, **wie hier gearbeitet wird**. Was gebaut wird und warum, steht in
`PROJEKT.md` — Ziel, Zielgruppe, Stimme, Constraints, Bestand, Rubrik und ein
Entscheidungsprotokoll mit Datum. **`PROJEKT.md` gilt vor dieser Datei** und wird
gelesen, bevor irgendetwas angefasst wird; hier steht nur, was dort fehlt.

Am Ende jeder Arbeit: zwei Zeilen ins Entscheidungsprotokoll von `PROJEKT.md`,
wenn etwas entschieden wurde. Keine Protokolle — nur, was beim nächsten Mal
wieder gebraucht wird.

Für **Kundenseiten** — also alles, was Vecom für einen Kunden baut und was nicht auf
vecom-design.it liegt — gilt zusätzlich `VECOM-STANDARD.md`: Reihenfolge vom Bedarf
zur Technik, Vertrauensstufen für jede Information, Umfangssperre, Projektklassen,
Stufe 0 als Vertrag. Er steht hier und nicht im Kundenordner, weil er allen
Kundenordnern gemeinsam ist.

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

Gepusht wird von dem Rechner, an dem gearbeitet wird — Mac oder Windows. Aus der
Cloud-Umgebung einer Claude-Sitzung geht es **nicht**: Der Git-Proxy lässt dieses
Repository nicht durch (403, „not in this session's authorized repository set"),
unabhängig von Zugangsdaten. Der Weg nach draußen ist dann `git format-patch`,
die Datei auf den Rechner, dort `git am` und pushen.

Auf dem Mac braucht es dabei einen Umweg, weil `tar` auf dem rsync-Einhängepunkt
nicht überschreiben kann: `cat "$Q/$f" > "$Z/$f"` statt `tar -x` darüber, und nach
dem Push die `.git`-Objekte und `HEAD`/`refs/heads/main`/`index` zurückspiegeln,
sonst weiß der Arbeitsordner nichts vom Commit.

---

## Zwei Rechner, ein Repository

Am Projekt wird von zwei Seiten gearbeitet: Mac und Windows-Rechner, jeweils in
einer eigenen Claude-Sitzung. **Ein** Repository, **zwei Arbeitskopien** — nicht
zwei Repositories. Der Grund steht nicht im Zugang, sondern im Deploy: An
`vecom2709/vecom-design` hängen die FTP-Secrets für All-Inkl. Ein zweites
Repository mit denselben Secrets lädt auf denselben Webspace, und die Abrissliste
im Deploy löscht dort, was das andere gerade hochgeladen hat. Wer getrennte
Konten braucht, nimmt einen Fork und Pull Requests — nie zwei gleichberechtigte
Repositories.

Schreibrecht bekommt das zweite Konto über **Settings → Collaborators**. Die
Commit-Identität bleibt auf beiden Rechnern `Vecom Design
<kontakt@vecom-design.it>`, damit die Historie einheitlich bleibt; wer gepusht
hat, steht ohnehin nur in der Signatur.

Vier Regeln, die aus Schaden entstanden sind:

1. **`git pull --rebase` vor der Arbeit, nicht erst vor dem Push.** Am 30.08.2026
   hat ein paralleler Commit die Ausschlusszeile für `cockpit/.htaccess` entfernt —
   der nächste Deploy hat daraufhin den Passwortschutz still überschrieben.
2. **Klein committen, sofort pushen.** Zwei Stunden ungepusht sind zwei Stunden
   Konfliktmaterial.
3. **`PROJEKT.md` wird von beiden Seiten unten ergänzt** und ist deshalb die
   Datei, an der es am häufigsten knallt. Direkt vor dem Commit pullen — oder auf
   einem eigenen Zweig arbeiten und zusammenführen.
4. **Nach jedem Push nachsehen, ob der Deploy durchlief.** Zwei Pushes kurz
   hintereinander lösen zwei Deploys aus; der letzte gewinnt. Das ist in Ordnung,
   solange beide denselben Stand haben — nach einem Rebase also erst pullen.

### Dateien auf den Windows-Rechner bringen

`git` liegt dort nicht im Suchpfad, sondern in Visual Studio:
`C:\Program Files\Microsoft Visual Studio\2022\Community\Common7\IDE\CommonExtensions\Microsoft\TeamFoundation\Team Explorer\Git\cmd\git.exe`.
`where git` findet nichts — das heißt nicht, dass keins da ist.

**Nach jeder Dateiübertragung die Prüfsumme vergleichen.** Am 13.09.2026
gemessen: Beim Übertragen von Bildern hängt die Werkzeugkette jeder `.webp` einen
`C2PA`-Block an — bei allen drei Texturen exakt 5.768 Byte, mit korrigierter
RIFF-Länge, also eine gültige, aber andere Datei. Für den Browser harmlos, fürs
Repository nicht: Die beiden Arbeitskopien hätten ab da verschiedene Bäume
gehabt. `.glb` und `.html` kamen unverändert an — es trifft nur Bilder.

Der Gegentest, der das sicher zeigt, kostet einen Befehl: auf beiden Seiten
`git add -A && git write-tree` und die beiden Baum-Hashes vergleichen. Sind sie
gleich, ist jede Datei bis aufs Byte gleich.

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
