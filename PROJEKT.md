# Vecom Design — Website und Agentur-Verwaltung
Stand: 31.08.2026 · Einsatz-Stufe: High-End (öffentlich, kundenberührend, Geld fließt)

## Ziel
Aus der eigenen Webdesign-Website eine Verwaltung machen, die den ganzen Weg trägt:
Kunde kauft → zahlt → Projekt entsteht → Fragebogen → Vorschau → Freigabe → online → Betreuung.
Uwe arbeitet allein. Alles, was die Verwaltung übernimmt, muss er nicht mehr über WhatsApp,
E-Mail und Zettel zusammenhalten.

## Zielgruppe
Zwei, und sie brauchen Gegensätzliches.

**Uwe** in der Verwaltung: will auf einen Blick sehen, was offen ist, und mit einem Klick
handeln. Kein Handbuch, keine Einarbeitung.

**Seine Kunden**: lokale Betriebe in und um Agrigent — Bäckerei, Friseursalon, Transportfirma,
Ferienvermietung. Meist nicht technikaffin, oft am Handy, oft ältere Menschen. Sie verwalten
kein weiteres Passwort. Deshalb bekommen sie einen Link, kein Konto. Dreisprachig:
Italienisch (Standard), Deutsch, Englisch.

## Stimme
Aus den Kundentexten, die tatsächlich rausgehen:

> „Hallo {name}, deine Anzahlung über {betrag} ist angekommen. Danke!
> Jetzt geht es los: Der nächste Schritt ist, uns dein Projekt zu beschreiben.
> Öffne diesen Link und fülle in Ruhe aus — du kannst zwischendurch speichern."

> „Wenn die Seite so passt, gib sie frei — dann veröffentlichen wir.
> Wenn etwas nicht stimmt, schreib es uns: wir ändern es."

Duzen. Kurze Sätze. Kein „bitte beachten Sie", kein Agenturton. Was der Kunde tun soll,
steht im ersten oder zweiten Satz.

## Constraints
- **Kein SSH, kein Composer, kein Framework.** Webspace bei All-Inkl. Reines PHP 8 + PDO +
  MariaDB. Jede fremde Bibliothek wäre ein Ordner voller Dateien, der per FTP gepflegt werden
  muss — deshalb sind PDF-Erzeugung und Stripe-Anbindung selbst geschrieben.
- **Veröffentlichung** per GitHub Actions über FTP (lftp). Push auf `main` genügt.
- **Gepusht wird nur aus dem Mac-Klon** unter Uwes GitHub-Konto. Die Cloud-Umgebung hängt an
  einem fremden Konto und würde unter falschem Namen veröffentlichen.
- **Kein Geheimnis ins Repository.** Zugangsdaten leben in `app/config.local.php` auf dem
  Webspace, ausgeschlossen über `.gitignore`. Das Repository ist öffentlich.
- **`cockpit/.htaccess` nie über den Deploy pflegen.** Am 30.08.2026 hat ein Deploy den
  Passwortschutz still überschrieben, weil eine Ausschlusszeile fehlte. Die Datei liegt gar
  nicht mehr im Repository; gesetzt wird sie aus der Verwaltung heraus.
- **Beträge immer als ganze Cent.** Nie Fließkomma für Geld.
- **Dreisprachig ist Pflicht**, nicht Kür. Kundentexte stehen gesammelt in `app/src/Texte.php`.

## Bestand
Was existiert und nicht gebrochen werden darf:

- Die öffentliche Website (dreisprachig, `/` italienisch, `/de/`, `/en/`), erzeugt beim Deploy
  aus `index.html` + `i18n-data.js` durch `build.mjs`.
- Drei Pakete: Starter 499 € + 39 €/Monat · Business 899 € + 69 €/Monat · Premium 1.499 € +
  99 €/Monat. Sie kommen aus der Datenbank auf die Website; die fest eingebauten Karten im HTML
  bleiben als Rückfall stehen.
- `formular.php` (Kontaktformular über Brevo) und der Brevo-Schlüssel in der `config.local.php`
  im Stammverzeichnis — die Verwaltung nutzt denselben.
- Laufende Kundenprojekte: Cavaleri Trasporti, Charme Color, Ristorante Boulevard.

## Erfolgskriterium
Ein Kunde kauft auf der Website, zahlt, füllt den Fragebogen aus, sieht die Vorschau, gibt frei
und bekommt seinen Beleg — **ohne dass Uwe etwas anfasst außer der eigentlichen Gestaltung.**
Alles andere ist Nebenwirkung.

## Rubrik
Jede Änderung wird an diesen fünf Punkten gemessen:

1. **Nichts Bestehendes bricht.** Die Website steht im Netz und bringt Anfragen.
2. **Das Dashboard bleibt eine Ansicht, nie eine Datenquelle.** Jede Zahl wird bei jedem Aufruf
   aus den echten Tabellen gerechnet. Nichts wird fest eingetragen, nichts zwischengespeichert.
3. **Geprüft, nicht angenommen.** Gegen dieselbe MariaDB-Fassung wie auf dem Server und im
   Browser. Auch die Angriffswege, nicht nur der gute Fall.
4. **Der Kunde versteht es ohne Erklärung, in seiner Sprache.** Keine leere Seite, keine
   Datenbankmeldung, kein englischer Fehlertext.
5. **Kein Geheimnis im Repository, kein Passwort im Klartext, keine fremden Daten auf der
   Kundenseite.**

## Zuständige Skills
- Öffentliche Website, Gestaltung, Landingpages → `web-design-studio`
- Tiefe, Schatten, „wirkt flach" → `schatteneffekte`
- Verwaltung, PHP, Datenbank, Abläufe → hier, kein Fach-Skill nötig

## Entscheidungen
- **31.08.2026 — Kunden bekommen keinen Login, sondern einen Link.** Ein 48-stelliger
  Zufallsschlüssel öffnet Fragebogen und Projektseite. Grund: Die Zielgruppe verwaltet kein
  weiteres Passwort. Weicht bewusst von der ursprünglichen Vorgabe „Kundenbereich mit Login" ab.
- **31.08.2026 — Aktualisierungen der Datenbank laufen von allein**, nicht auf Knopfdruck.
  Grund: Der Knopf war gut gemeint, aber er hat dreimal dafür gesorgt, dass fertiger Code
  tagelang halb arbeitet.
- **31.08.2026 — Ohne Partita IVA heißt das Dokument Zahlungsbeleg, nicht Rechnung**, mit
  eigenem Nummernkreis und entsprechendem Vermerk. Grund: Wer keine Umsatzsteuernummer hat,
  stellt keine Rechnung im steuerlichen Sinn aus. Den genauen steuerlichen Satz liefert der
  Commercialista, er steht als freier Text in den Einstellungen — nicht im Code.
- **31.08.2026 — Die Restzahlung wird bei der finalen Freigabe angefordert**, nicht beim
  Onlinegang. Grund: Danach hat man nichts mehr in der Hand.
- **31.08.2026 — Der Cockpit-Schutz wird aus der Verwaltung gesetzt**, nicht über KAS oder
  GitHub. Grund: Die Verwaltung liegt auf demselben Server, eine Ebene daneben, und kann die
  Dateien selbst schreiben. Der Umweg über einen Menschen ist dreimal gescheitert.
- **30.08.2026 — Zahlungsmodell 50 % bei Auftrag, 50 % bei Übergabe.** Von Uwe bestätigt.
- **30.08.2026 — Stripe ohne fremde Bibliothek**, nur REST und HMAC.

## Was funktioniert hat
- **Gegen echte Fälle prüfen statt gegen Attrappen.** Ein kleiner Testserver, der sich auf Zuruf
  anders verhält (200, 500, gar keine Antwort, Umleitung), hat Unterschiede sichtbar gemacht,
  die eine Attrappe verschluckt hätte — etwa dass „Server antwortet nicht" und „Server meldet
  einen Fehler" zwei verschiedene Zustände sind.
- **Erst die eigene Erwartung verdächtigen, dann den Code.** Bei rund einem Drittel der
  fehlgeschlagenen Prüfungen lag der Fehler in meiner Zusicherung, nicht in der Anwendung.
  Wer das umdreht, „repariert" funktionierenden Code kaputt.
- **Beispieldaten mit Kennzeichen statt Attrappen im Code.** Drei echte Vorgänge in drei
  Sprachen, jede Zeile markiert, restlos löschbar — dadurch bleibt das Dashboard ehrlich und
  die leere Verwaltung wird trotzdem vorführbar.
- **Nach dem Schreiben nachsehen, ob es wirkt.** Der Cockpit-Schutz ruft sich selbst auf und
  prüft auf 401. Eine Schutzmaßnahme, die man nicht nachprüft, ist keine — zweimal hat genau
  das gefehlt.
- **Fehler im Vorbeigehen mitnehmen.** Beim Bauen fielen Dinge auf, nach denen niemand gesucht
  hatte: das Menü markierte immer „Dashboard", „Beliebteste Pakete" zeigte das Doppelte,
  Bestellnummern hätten sich nach einer Löschung wiederholt, die Wortmarke fehlte auf dem Handy.

## Offen
- **Statistiken** ist die letzte Platzhalterseite.
- **Monatliche Betreuung als Stripe-Abo** — braucht ein freigeschaltetes Stripe-Konto.
- **Stripe ist nicht live**: offen ist das Ausweisdokument (`company.verification.document`).
- **Der Cronjob im KAS ist noch nicht angelegt.** Ohne ihn läuft weder Monitoring noch die
  Fragebogen-Erinnerung. Adresse steht in der Verwaltung unter Website-Monitoring.
- **Partita IVA und der steuerliche Hinweistext** fehlen — solange bleiben es Belege.
- **Firmendaten** (Straße, IBAN) sind in den Einstellungen noch nicht gefüllt; sie stehen auf
  jedem Beleg.

## Politurdurchgang 01.09.2026

Alles hier ist nachgemessen, nicht geschätzt — dieselben Messungen vorher und nachher.

**Behoben:**
- **Mobiles Menü ließ sich nicht schließen.** Die Überlagerung liegt im Header und ist sein
  Kind; `z-index: 100` am Header gilt gegenüber der Seite, nicht gegenüber den eigenen
  Kindern. Die Fläche (99) schlug Wortmarke und Werkzeuge (auto) und legte sich über den
  Knopf, der sie schließen soll. Dazu machte das `backdrop-filter` des gescrollten Headers
  ihn zum Bezugsrahmen für alles `position: fixed` darin — die Menüfläche schrumpfte auf
  Headerhöhe, die Einträge lagen ohne Grund über dem Seiteninhalt. Beides war schon vorher
  so; nachgeprüft am unveränderten Stand aus `git archive HEAD`.
- **Lavori verlinkt jetzt die drei erreichbaren Seiten** (mensaena.de, jonika-venturis.com,
  trendonix-buecher.de). Vorher: null `href` im ganzen Abschnitt, die Domains standen als
  `<span>` da. Nebenbei: die Partnerlinks zeigten auf `www.jonika-venturis.com`, das mit
  401 antwortet — jetzt auf die Apex-Domain, die lädt.
- **Trefferflächen:** 30 von 80 Zielen unter 44px → 3, und die drei sind nur in der Breite
  kurz („FAQ", „AGB"), 44px hoch. Keine überlappenden Flächen (geprüft).
- **Hero-Fließtext über der 3D-Marke:** Fläche unter 4.5:1 von 17,9 % (Handy) und 21,4 %
  (900px) auf 0,0–0,7 % über 390/768/900/1024/1199/1280/1920px. Ursache war doppelt: die
  Spaltentrennung `max-width` stand VOR den Grundregeln und wirkte nie, und unterhalb davon
  stand die Marke mitten hinter dem Text. Jetzt trennt die Komposition ab 900px, darunter
  tritt die Marke zurück (`HERO_GEDRAENGT` in site-beats.js).
- **404-Seite** lädt absolut; unter `/de/…` war sie unformatiert. Titel und `lang` stimmen überein.
- **`pakete.html`** aus der Sitemap genommen (stand dort und trug `noindex`).
- **Meta-Beschreibungen** 169–174 → 143–150 Zeichen.

**Inhaltlich:**
- **Über mich** SEO-geschärft: Aragona, Provinz Agrigent, Sizilien; Kunden in IT/DE/AT/CH;
  drei Sprachen. Der persönliche Ton bleibt.
- **Angebote sind unverbindlich.** „Ein Angebot ist verbindlich" stand im Über-mich-Text und
  ist ersetzt: Angebot und Erstgespräch kostenlos und unverbindlich, verbindlich erst mit dem
  Vertragsabschluss. Durchgezogen in Preisnotiz, FAQ 1 und 3, neuer FAQ 9 („Ist das Angebot
  verbindlich?") — in allen drei Sprachen und in den strukturierten Daten.
- **Neun sichtbare Platzhalter entfernt**: Laufzeit der Betreuung (von Uwe bestätigt: zwölf
  Monate Erstlaufzeit, danach jederzeit zum Monatsende kündbar) und der Hosting-Anbieter im
  Rechtstext (ALL-INKL, Angaben aus deren Impressum).
- **Strukturierte Daten**: Uwe Vetter als `founder`, Adresse Aragona/Agrigento/IT,
  `areaServed` um Provinz und Sizilien ergänzt.

**Korrigiert (mein Irrtum):**
- Ich hatte gemeldet, die Rechtsseite sei leer, weil in `i18n-data.js` nur 12 der 67
  Textschlüssel stehen. Falsch: `legal.html` lädt zusätzlich **`assets/js/legal-i18n.js`**,
  und dort sind alle 67 in drei Sprachen gefüllt, mit echter Anschrift und Codice Fiscale.
  Aufgefallen ist es erst, als ich die Seite im Browser angesehen habe — die Prüfung im
  Wörterbuch allein war zu kurz gesprungen. Merksatz für das nächste Mal: eine Seite gilt
  erst als geprüft, wenn sie gerendert vor einem lag.
- Dabei aber ein echter Fehler gefunden und behoben: Die Datenschutzerklärung nannte in
  allen drei Sprachen **GitHub Pages (GitHub Inc., USA)** als Hoster samt Hinweis auf die
  Übermittlung in die USA. Die Seite liegt seit dem Umzug bei All-Inkl auf Servern in
  Deutschland. Jetzt korrekt benannt, ohne Drittlandübermittlung.
- `t2` in den AGB sagte schon immer „Angebote sind kostenlos und für den Kunden
  unverbindlich" — die neue Formulierung auf der Startseite stimmt damit jetzt überein.

**Dringend offen:**
- **Partita IVA und REA** stehen weiterhin nicht im Impressum, weil sie noch nicht vorliegen.
- **Wenn Stripe live geht**, muss der Zahlungsdienstleister in die Datenschutzerklärung
  (`legal-i18n.js`, Abschnitt Kontaktformular/Empfänger) aufgenommen werden.
- **Kundenstimmen** gibt es keine. Nicht erfinden: bei Mensaena, TerraViva und Trendonix
  nach zwei Sätzen mit Namen fragen.

## Arbeiten: Cavaleri aufgenommen (01.09.2026)

- **Cavaleri Srl war nicht auf der Seite** — die einzige fertige Arbeit für ein fremdes
  Unternehmen fehlte, während drei eigene Projekte dort standen. Jetzt führt sie den
  Abschnitt an, als einzige Karte über die volle Breite (`card--voll`, span 6; die
  Mindesthöhe muss NACH `.card--live` stehen, sonst gewinnt dessen `min-height`).
- Das Kartenbild ist aus dem eigenen Repo `vecom2709/cavaleri-transporte` erzeugt: lokal
  ausgeliefert, mit Playwright aufgenommen, auf 1400×540 zugeschnitten. Kein Abruf von einer
  fremden Domain nötig.
- Verlinkt ist `cavaleri-trasporti.netlify.app`. **Sobald cavaleri.it live ist, umstellen.**

### Nebenbefund im Cavaleri-Projekt (nicht behoben, anderes Repo)
`assets/css/site.css` Zeile 1514/1515: `p, li, figcaption, .guida { hyphens:auto;
overflow-wrap:break-word; }` und darunter `h1, h2, h3, .frase, … { hyphens:none; }`.
Die zweite Zeile schaltet die Silbentrennung ab, lässt aber `overflow-wrap: break-word`
stehen. Dadurch bricht `p.frase` lange Wörter mitten durch — auf der Startseite steht
„UN SOLO INTERLOCUTOR / E." und „IL BARICENTRO / È…". Ein Wort genügt als Korrektur:
`overflow-wrap: normal` in dieselbe Regel.

### Kundenstimmen
Anzufragen ist **Cavaleri** — das einzige fremde, fertige Projekt. Mensaena, TerraViva und
Trendonix sind Uwes eigene Projekte und scheiden aus. Charme Color erst, wenn
charme-color.it live ist (steht derzeit leer). Anfragetext liegt im Chatverlauf vom 01.09.

## Der Kaufweg (01.09.2026)

Recherchiert, was bei Webdesign üblich ist, und vier Lücken geschlossen. Das Zahlungsmodell
selbst blieb: 50 % Anzahlung, 50 % bei Übergabe, Betreuung monatlich — genau der Standard.
Uwe hat die Dreiteilung für Premium ausdrücklich abgelehnt.

1. **Die Paketwahl reist mit.** Alle Knöpfe zeigten auf `#contact`; wer „Business anfragen"
   drückte, landete in einem Formular, das nichts davon wusste. Jetzt `?paket=<slug>#contact`,
   und über den Fragen steht „Gewähltes Paket · Business · 899 €" mit einem Link zum Ändern.
   Name und Preis liest `qform.js` aus der Karte selbst, damit es auch stimmt, wenn die Pakete
   aus der Verwaltung kommen. Die Wahl geht als erste Zeile mit der Anfrage raus.
2. **`pakete.html` ist jetzt die Verkaufsseite**: zusätzlich „Wie lange es dauert" je Paket
   und die vier Schritte vom Ja bis online. Der Knopf trägt das Paket weiter. Bleibt
   `noindex` — sonst konkurriert sie mit dem Preisabschnitt der Startseite.
3. **`buchen.php` zeigt den Ablauf** über dem Bezahlknopf: Anzahlung, sechs Fragen, Entwurf,
   Freigabe und Rest. Nichts davon ist neu gebaut — es war nur unsichtbar für den, der noch
   nicht bezahlt hatte.
4. **Widerrufsrecht im Kauf.** Zwei Pflichthaken: AGB und Datenschutz, und der ausdrückliche
   Wunsch nach sofortigem Beginn samt Kenntnis, dass das Widerrufsrecht mit vollständiger
   Leistung erlischt (Codice del Consumo). Dazu eine aufklappbare Belehrung. Serverseitig
   geprüft — ohne beide Haken entsteht keine Bestellung. Migration `008_widerruf.sql` legt
   `agb_ok_am`, `widerruf_ok_am`, `zustimmung_text` und `zustimmung_lang` an; gespeichert wird
   der **Wortlaut**, nicht nur der Haken, weil sich der Text der Seite später ändern kann.
   Getestet: ohne Haken kommt die Fehlermeldung und keine Bestellung, mit Haken stehen beide
   Zeitstempel und der Wortlaut in der Zeile.

**Noch von Uwe:** Den Belehrungstext (`buchen.php`, Schlüssel `widText`) juristisch gegenlesen
lassen, bevor Stripe live geht. Ich bin kein Anwalt.

## Anfragen landen in der Verwaltung (01.09.2026)

Weg A war ein Bruch: Die Anfrage kam nur als E-Mail an, Kunde und Bestellung wurden von Hand
abgetippt — obwohl der Kunde die Angaben gerade erst eingegeben hatte.

- **`Anfrage::annehmen()`** legt den Kunden an oder findet ihn über die E-Mail (kein Doppel bei
  Stammkunden) und hängt die Anfrage daran: Name, E-Mail, Telefon, bestehende Seite, Sprache,
  gewähltes Paket (über den Slug der `packages`-Tabelle zugeordnet) und der volle Fragebogentext.
- **Eigene Tabelle `anfragen`**, nicht `orders` mit Status „Anfrage". Eine Anfrage ist kein
  Auftrag; in `orders` würde sie jede Umsatzzahl und jede Abschlussquote verfälschen.
- **Die E-Mail hatte Vorrang** — `formular.php` rief die Klasse erst NACH dem Versand auf. Am
  01.09. umgedreht (siehe unten): Erst wird die Anfrage festgehalten, dann gemeldet. Der
  Datenbankeintrag überlebt einen Mailausfall, umgekehrt nicht.
- **Verwaltung → Kontakt → Anfragen**: Liste mit Stand, Detailseite mit allem Geschriebenen und
  einem Knopf **„Bestellung anlegen"** — Paket vorausgewählt, wenn eines mitkam. Daraus entsteht
  die Bestellung samt Anzahlung und Restzahlung; den Zahlungslink erzeugt man wie gewohnt in der
  Bestellung. Die Anfrage bleibt verlinkt stehen.
- Zähler in der Navigation für offene Anfragen, über `sicher()` abgesichert, damit die Seite auch
  vor der Migration lädt.
- **Datenschutzerklärung ergänzt** (drei Sprachen): Die Anfrage wird zusätzlich in der
  Projektverwaltung auf demselben Server in Deutschland gespeichert.

Getestet: Formular abgeschickt → Kunde #7 mit Telefonnummer angelegt, Anfrage mit Paket Business
verknüpft; ein Klick → Bestellung VD-2026-0005 über 899 € mit Anzahlung und Restzahlung je
449,50 €; Anfrage steht danach auf „bestellung" und verlinkt die Bestellung.

## Der Weg vor dem Auftrag (01.09.2026)

Erkenntnis vorweg: Es fehlte nichts, es ging nur alles erst nach der Zahlung auf. Die
Kundenseite konnte Nachrichten und Dateien längst — sie hing nur am Projekt, und ein Projekt
gibt es erst mit bezahlter Bestellung. `messages.project_id` und `files.project_id` durften
schon immer leer bleiben; genutzt hat es niemand.

- **`vorgang.php`** — die Seite, die der Kunde mit der Anfrage bekommt. Was er angefragt hat,
  Nachrichten in beide Richtungen, Unterlagen hochladen und herunterladen, dreisprachig. Wird
  daraus ein Auftrag, **leitet derselbe Link auf `projekt.php` weiter** — eine Adresse vom
  ersten Kontakt bis online.
- **Zugang**: eigener Schlüssel an der Anfrage, 90 Tage gültig (`Anfrage::GUELTIG_TAGE`), bei
  jeder Berührung aufgefrischt. Wird nichts daraus, schließt er sich von selbst.
- **Eingangsbestätigung** an den Kunden, sofort nach dem Absenden, in seiner Sprache: was er
  angefragt hat, ausdrücklich unverbindlich, Antwort innerhalb eines Werktags, sein Link.
  Läuft ganz am Ende von `Anfrage::annehmen()` in eigenem try/catch — die Anfrage steht da
  bereits, ein stummer Mailserver darf sie nicht mehr gefährden.
- **`Nachricht::vorab()`** schreibt am Kunden statt am Projekt, bewusst getrennt von
  `schreiben()`, wo Projektstand und Projektlink mit drinhängen. **`Ablage::annehmen()`** nimmt
  jetzt `?int $projektId`; ohne Projekt zählt die Dateigrenze je Kunde.
- **Kundenakte**: Nachrichtenverlauf, Schreibfeld mit vier Vorlagen (Nachfassen, Rückfrage,
  Angebot, Absage) und ein Dateien-Block mit Herunterladen und eigenem Hochladen. Die
  ausgehende Mail trägt den Link zur Kundenseite mit, wenn eine offene Anfrage existiert.
- **Bestellung**: Knopf „Link an den Kunden senden" verschickt den Zahlungslink direkt über
  Brevo, in der Sprache des Kunden, mit Betrag und Anlass. Migration `011` gibt `mails` eine
  `payment_id` — sonst hielte die Warnung vor doppeltem Versand die Restzahlung für eine
  Wiederholung der Anzahlung. Der zweite Klick fragt nach, statt doppelt zu schicken.
- **Datenschutz**: bereits im vorigen Schritt um die Speicherung ergänzt.

Getestet mit einem lokalen Mailfänger: Bestätigung auf Italienisch raus, Kundennachricht kam
bei Uwe an, Antwort aus der Akte ging mit Link zurück, Datei hochgeladen und als Anhang wieder
heruntergeladen (`Content-Disposition: attachment`, richtiger Typ), Zahlungslink verschickt und
der Knopf wechselte auf „Nochmal senden".

### Anleitung für den Kunden (01.09.2026)

Der Kunde soll nicht raten müssen, was diese Seite ist und was er tun soll.

- **In der Eingangsbestätigung** steht jetzt ein abgesetzter Block „So läuft es — alles über
  einen Link" mit vier nummerierten Schritten: Link ablegen, dort schreiben statt mailen,
  Unterlagen hochladen, und was danach kommt. Die Dateigrenze wird zur Laufzeit eingesetzt
  (`Fmt::bytes(Ablage::grenze())`) — auf manchen Tarifen ist sie kleiner als die 15 MB, die die
  Anwendung erlaubt.
- **Oben auf `vorgang.php`** dieselben vier Schritte, sichtbar beim Ankommen. Wer nach drei
  Wochen zurückkommt, weiß sonst nicht mehr, wofür die Seite da war.
- Dreisprachig, ohne Fachbegriffe, mit dem wichtigsten Satz zuerst: kein Konto, kein Passwort,
  der Link ist der Zugang.

**Dabei einen Fehler gefunden und behoben**, der jede Bestätigung stillgelegt hätte: `bestaetigen()`
benutzt seit der Dateigrenze `Fmt`, lud die Klasse aber nicht. Der Fatal wurde vom eigenen
try/catch geschluckt — die Anfrage stand da, die Mail ging nie raus, und in den Meldungen stand
nur „Eingangsbestätigung nicht verschickt". Aufgefallen beim Nachmessen, nicht beim Lesen.

### Handbuch als PDF (01.09.2026)

Ein Dokument, beide Sichten darin — nicht zwei. 40 Seiten A4, im Vecom-Design gesetzt
(Archivo/Inter als Base64 eingebettet, dunkle Deckel- und Teilerseiten, helle Inhaltsseiten):

- **Vorab** — Ablauf auf einen Blick als Drei-Bahnen-Diagramm (Kunde / Automatisch / Du,
  Schritte 1–15), die drei Beteiligten, was der Kunde ausdrücklich nicht sieht.
- **Teil 1 · Die Verwaltung** — 1.1 Anmelden/Cockpit/Menü, Bildschirmdarstellung der
  Verwaltung, Dashboard, Anfragen, Kundenakte, Bestellungen und Zahlungen, Projekte führen
  (inkl. Projektdarstellung und allen 13 Ständen), Fragebögen/Nachrichten/Dateien, Belege und
  Rechnungen, Pakete, Monitoring, Einstellungen und Integrationen, die tägliche Runde.
- **Teil 2 · Der Kunde** — Anfrage auf der Website, die Bestätigungsmail (als Mail-Darstellung),
  seine eigene Seite (als Bildschirmdarstellung), Angebot/Zahlungslink/Anzahlung, Fragebogen,
  Entwurf/Vorschau/Freigabe, Restzahlung/Veröffentlichung, neun häufige Fragen mit fertigen
  Antworten zum Weiterschicken.
- **Anhang A–E** — alle acht automatischen Mails mit Auslöser, alle vier Statuslisten, was der
  Cronjob je Lauf tut, eine Störungstabelle Symptom → Ursache → erster Griff, Begriffe.

Gebaut als HTML-Teile plus `stil.css`, gerendert über Playwright `page.pdf()` bei A4. Jede Seite
wurde nachgemessen (Sollhöhe 1123 px bei 96 dpi); sechs Seiten liefen über und wurden gekürzt
oder umgebaut, bis die 40 gedruckten Seitenzahlen genau den 40 PDF-Seiten entsprechen.

Zwei Dinge, die beim Rendern auffielen und im Stylesheet stehen bleiben müssen:

- **Verlaufstext auf Blockelementen** (`background-clip:text`) zeichnet der PDF-Export als feine
  Rahmenlinien um die Verlaufsfläche mit. Deckelmarke, Titelakzent und die Ziffern der
  Teilerseiten stehen deshalb in voller Farbe statt im Verlauf.
- **Das Inhaltsverzeichnis** passt einspaltig nicht auf eine Seite (1666 px). Zweispaltig mit
  `break-inside:avoid` je Zeile.

Zwei Angaben im Entwurf waren falsch und wurden gegen den Quelltext korrigiert: der Fragebogen
hat vier Abschnitte mit 22 Feldern (nicht „sechs Fragen"), und es lassen sich sehr wohl weitere
Zugänge anlegen. Merksatz bleibt: nur was gerendert vor mir stand, gilt als geprüft.

Liegt als `Vecom-Design-Handbuch.pdf` im Projektordner.

### KAS, Brevo und der E-Mail-Versand (01.09.2026)

Der Auftrag war „richte alles ein, was auf KAS gebraucht wird". Gefunden wurden dabei drei
Dinge, die seit Wochen still kaputt waren.

**Der Cronjob lief ins Leere.** Er war da, aktiv, alle zehn Minuten — nur endete die Adresse
mit `cron.php?` und ohne Schlüssel. Aufgerufen antwortete sie `Nicht gefunden.` Seit der
Einrichtung hatte der Lauf also kein einziges Mal etwas getan: keine Erinnerungen, keine
Überwachung, kein Zurücksetzen abgelaufener Zahlungslinks. Schlüssel eingetragen, geprüft,
läuft. Zusätzlich meldet der Job jetzt an kontakt@vecom-design.it, wenn ein Lauf schiefgeht —
der E-Mail-Filter steht auf `ok`, und weil der Tooltip sagt „Schlüsselwort, das vorkommen muss,
um KEINE E-Mail zu erhalten", schweigt er bei gelungenen Läufen und schreibt nur bei Fehlern.

**Der Brevo-Schlüssel war ein Platzhalter.** 18 Zeichen statt achtzig, `xkeysib-` plus Rest.
Gegen die API geprüft: HTTP 401, `Key not found`. Dazu passend: im Brevo-Konto existierte gar
kein API-Schlüssel, und die Transaktions-Logs waren leer — null Einträge. Es war also noch nie
eine einzige automatische Mail rausgegangen. Die lokalen Tests liefen gegen den Mailfänger und
sahen deshalb gut aus.

**Die Domain war bei Brevo nicht authentifiziert.** Alle Mails gehen über die Brevo-API mit
Absender kontakt@vecom-design.it, im DNS stand aber weder Brevo-Code noch Brevo-DKIM.
Nachgeholt: Brevo-Code (TXT), brevo1/brevo2._domainkey (CNAME), DMARC um
`rua=mailto:rua@dmarc.brevo.com` ergänzt. Brevo bestätigt alle vier. Einen SPF-Eintrag braucht
Brevo ausdrücklich nicht — nachgelesen statt geraten. Absender `Vecom Design
<kontakt@vecom-design.it>` angelegt; vorher gab es nur den GMX-Absender, den Brevo als
Freemail-Domain beanstandet.

Ebenfalls im KAS: SSL auf allen vier Domains aktiv, PHP 8.5, Webspace-Sicherung vorhanden —
**Datenbank-Sicherung dagegen: null**. Subdomain `vorschau.vecom-design.it` angelegt, Let's
Encrypt bezogen, SSL erzwungen. Cockpit-Schutz hat Uwe über KAS → Verzeichnisschutz gesetzt;
die Adresse antwortet jetzt mit 401, und der Cronlauf meldet `cockpit: ja`.

### Zwei neue Klassen (01.09.2026)

**`Versand.php` — Zugangsdaten in der Verwaltung statt in einer Datei.** Einstellungen bekommt
den Block „E-Mail-Versand": Schlüssel, Absenderadresse, Name, Meldungsadresse. Was dort steht,
hat Vorrang vor config.local.php; ist nichts hinterlegt, greift weiterhin die Datei. Der
Schlüssel wird nie wieder ausgegeben, nur seine letzten vier Zeichen — im Protokoll steht bloß,
DASS er geändert wurde. „Speichern und prüfen" fragt sofort bei Brevo nach, ob er gilt. Und ein
Schlüssel, der nicht mit `xkeysib-` beginnt oder unter 40 Zeichen hat, wird abgelehnt: genau
der Fehler, der monatelang unbemerkt blieb, läuft jetzt in eine Meldung.

**`Sicherung.php` — nächtlicher Datenbank-Auszug.** Kein mysqldump, kein exec: reines PDO,
ungepuffert gelesen und beim Schreiben gzip-komprimiert. Landet in `app/sicherungen/` hinter
einer eigenen `.htaccess` — absichtlich auf dem Webspace, denn die Webspace-Sicherung des
Hosters nimmt ihn dann mit. Vierzehn Tage Aufbewahrung. Hängt als Tagesaufgabe im Cronlauf.

Beim Testen gegen eine echte MariaDB fielen zwei eigene Fehler auf, die ohne Test durchgerutscht
wären: `array_column($zeilen, 0, 1)` benutzte die Tabellenart als Schlüssel und verschluckte
dadurch alle Tabellen bis auf eine; und eine Sicht wurde mit `DROP TABLE` abgeräumt und vor der
Tabelle angelegt, auf die sie zugreift. Nachher: 20.004 Zeilen gesichert, in eine leere Datenbank
zurückgespielt, Prüfsummen beider Stände identisch — inklusive Umlauten, Emoji, Zeilenumbrüchen
und NULL-Werten. Spitzenspeicher 2 MB.

Auf dem Server geprüft: `sicherung {"datei":"vecom-2026-09-01.sql.gz","bytes":11738,
"tabellen":22}`, der Ordner antwortet mit 403, alle vierzehn Verwaltungsseiten mit 200 und ohne
PHP-Fehler.

**Merksatz dazu — und eine Korrektur:** `app/` liegt sehr wohl im Repository (78 Dateien);
ausgenommen sind nur `app/config.local.php`, `app/uploads/` und seit heute `app/sicherungen/`
und `app/notfall/`. Der Deploy trägt die Verwaltung also mit. Wer sie per FTP hochlädt und
danach nicht committet, bekommt sie beim nächsten Push still wieder überschrieben — mit dem
alten Stand aus dem Repository. Genau das drohte hier; deshalb sind die sechs Dateien
nachträglich in einem eigenen Commit gelandet.

Der Irrtum kam vom Klon in der Cloud-Sitzung: Der hängt Commits hinterher, dort war `app/`
tatsächlich unbekannt. **Maßgeblich ist immer der Klon auf dem Mac** — dort wird auch
ausschließlich gepusht, weil die Cloud unter fremder GitHub-Kennung schriebe.

### Das Kontaktformular hat nie funktioniert (01.09.2026)

Der schwerste Fund dieses Projekts, und er lag fünf Monate offen. Zwei Fehler, die einander
gedeckt haben:

1. `formular.php` verlangte `config.local.php` im **Stammverzeichnis**. Die Datei lag dort nie —
   der Brevo-Zugang steht in `app/config.local.php`. Der Server antwortete auf jede Anfrage mit
   `500 {"ok":false,"error":"config"}`.
2. `qform.js` sah die Antwort überhaupt nicht an: `fetch(...).catch(() => {})` und direkt danach
   `done.hidden = false`. Der Besucher las „Danke — deine Anfrage ist unterwegs", ganz gleich was
   zurückkam.

Jede Anfrage seit dem Start ist so verschwunden, ohne Eintrag, ohne Mail, ohne Spur. Aufgefallen
ist es erst, weil eine Testanfrage in Brevo nicht auftauchte — und weil die Antwort direkt mit
`curl` geprüft wurde statt im Browser.

**Die Lehre daraus, allgemeiner als dieser Fall:** Eine Erfolgsmeldung, die nicht an eine
Serverantwort gebunden ist, ist keine Meldung, sondern eine Behauptung. Sie verhindert genau das
Feedback, aus dem man den Fehler bemerkt hätte. Wo etwas „still" scheitern kann, muss geprüft
werden, ob es je gelaufen ist — nicht, ob es laufen könnte.

Beides repariert:

- **`formular.php`** hält die Anfrage zuerst in der Verwaltung fest (Kunde, Anfrage, Zugangslink,
  Eingangsbestätigung an den Kunden) und meldet sie danach per E-Mail über `Mail::senden()` —
  damit steht der Versand auch im Nachrichtenprotokoll. Alter Weg über die Wurzel-Konfiguration
  und `mail()` bleiben als Rückfall. Trägt keiner der Wege, landet die Anfrage als JSON-Zeile in
  `app/notfall/anfragen.jsonl` (über das Web gesperrt) und die Antwort sagt ehrlich 502.
- **`qform.js`** zeigt die Bestätigung nur bei `ok:true`. Sonst erscheint ein Fehlerkasten —
  **die Eingaben und der Absendeknopf bleiben stehen**, ein zweiter Versuch kostet nichts, und
  ein vorbereiteter `mailto:` mit dem ganzen Text ist der Notausgang. Eine HTML-Seite statt JSON
  gilt als Fehler, nicht als Erfolg. Neue Texte in `i18n-data.js`: `sending`, `failHead`,
  `failText`, `failBtn` (it/de/en).

Geprüft, örtlich gegen eine echte MariaDB und eine Brevo-Attrappe: Vollprobe (Anfrage, Kunde,
Token, beide Mails), Pflichtfelder 422, Honigtopf still verworfen und nichts gespeichert, Bremse
429, GET 405, Totalausfall 502 mit Eintrag in der Notfalldatei; beide Bildschirme im Testbrowser.
Danach live: `{"ok":true,"gespeichert":true,"gemeldet":true}`, Anfrage steht in der Verwaltung,
und in Brevo unter Transaktionale → Logs stehen beide Mails auf **Zugestellt**.

**Offen:** Der Brevo-Schlüssel, der jetzt hinterlegt ist (endet auf `Cqpb`), stand einmal im
Klartext in einem Chat. In Brevo einen neuen erzeugen, hier eintragen, den alten löschen.

### Der Kundenweg, tief durchgegangen (01.09.2026)

Alles vor dem Auftrag und danach einmal wirklich gegangen — mit echten
Schlüsseln gegen eine echte Datenbank, in drei Sprachen, auf 390 und 820 Pixel
Breite, und live mit einer Testanfrage.

**Was steht und trägt.** Vorgangsseite, Fragebogen, Projektseite und
Buchungsseite antworten in allen drei Sprachen ohne eine einzige PHP-Meldung,
weisen einen falschen oder fehlenden Schlüssel freundlich ab statt
abzustürzen, und haben durchgehend CSRF, `no-store`, `noindex` und
`Referrer-Policy: no-referrer`. Dateien und Belege sind über Kunde **und**
Projekt geprüft, nicht über die geratene Nummer. Kein Überlauf auf keiner
Breite. Der Fragebogen speichert 22 Felder zwischen und erzwingt beim
endgültigen Absenden den Firmennamen. Eine gefälschte CSRF-Marke kommt
weder örtlich noch live durch.

**Sechs Stellen, an denen etwas still blieb** — dieselbe Fehlerklasse wie beim
Formular, siehe den Commit dazu: Kundennachricht vor dem Auftrag meldete sich
nirgends; der Zähler neben „Nachrichten" konnte für solche Nachrichten nie
wieder auf null gehen; die Nachrichtenliste zeigte einen Strich statt eines
Wegs zum Antworten; die private Adresse des Kunden stand in der Verwaltung
nirgends; der Fragebogen ersetzte beim Speichern statt zusammenzuführen; und
„Integrationen" sagte „Brevo — Nicht verbunden", während Brevo zustellte.
Alles behoben und live nachgeprüft.

**Neu als Dauerwache:** Der Cron fragt jetzt einmal täglich bei Brevo nach und
meldet sich, wenn der Versand nicht mehr antwortet. Diese eine Frage hätte den
toten Schlüssel am ersten Tag gezeigt statt nach Monaten.

**Was noch nicht offen ist, und warum.**

- **Direktbuchung ist zu.** `buchen.php` verlangt Stripe im Livemodus mit
  eingerichtetem Webhook. Stripe steht auf Testmodus mit `sk_test_`, und
  „Kaufknopf auch im Testmodus zeigen" ist aus. Das ist richtig so, solange
  nicht live geschaltet ist — aber es heißt: Der Kaufweg ist gebaut und
  ungenutzt. Zum Ausprobieren reicht der Schalter in den Integrationen.
- **Stripe-Webhook: fünf ungültige Unterschriften an einem Tag**, die nicht von
  eigenen Aufrufen stammen. Wenn das echte Stripe-Ereignisse waren, passt das
  hinterlegte Webhook-Geheimnis nicht zu dem, das Stripe für
  `https://vecom-design.it/stripe-webhook.php` anzeigt — dann würde keine
  Zahlung je von allein bestätigt. Vor dem Livegang prüfen.

### Der ganze Ablauf, einmal wirklich durchlaufen (01.09.2026)

Nicht gelesen, sondern gefahren: zweimal die volle Kette gegen eine echte
MariaDB, mit einer Stripe-Attrappe (gültige HMAC-Unterschrift, echter
Webhook-Weg) und einer Brevo-Attrappe.

**Jedes Glied hält.** Formular → Anfrage + Kunde + Zugangsschlüssel + zwei
Mails · Anfrage → Bestellung `VD-2026-0004`, Anzahlung und Restzahlung
entstehen sofort mit · Zahlungslink über Stripe · Webhook mit geprüfter
Unterschrift → Anzahlung bezahlt · daraus von allein: Projekt, Fragebogen,
Beleg, Zahlungs- und Einladungsmail · Fragebogen (21 Felder, Zwischenstand
und Absenden) → Projekt auf „Informationen erhalten" · Vorschau gesetzt →
Mail · Freigabe → finale Freigabe → **Restzahlung wird angefordert, Link
erzeugt, Mail raus** · Restzahlung bezahlt → offen 0,00 €, zweiter Beleg ·
online → Projekt online, Bestellung fertig, Mail an den Kunden.

**Drei Funde, alle behoben:**

1. **Der schwerste: die Sprache stand nur an der Anfrage, nie am Kunden.**
   Die Eingangsbestätigung kam deutsch, die vier folgenden Mails italienisch
   — Zahlung, Vorschau, Restzahlung, „ist online". Nur `buchen.php` merkte
   sich die Sprache; der übliche Weg über das Formular nicht.
2. Geht ein Projekt online, ohne dass eine Adresse im Monitoring steht,
   passierte nichts. Kein Eintrag, keine Prüfung — ein stiller Ausfall genau
   des Dienstes, mit dem geworben wird. Jetzt meldet es sich.
3. Die Sprache war in der Verwaltung weder sichtbar noch änderbar. Jetzt in
   der Kundenakte und im Formular.

**Was nur noch eingeschaltet werden muss** (kein Fehler, Entscheidungen):

- **Direktkauf ist zweifach zu**: Stripe steht auf Testmodus, und bei keinem
  Paket ist „direkt auf der Website buchbar" gesetzt. Beides muss an, sonst
  erscheint kein Kaufknopf.
- **Partita IVA fehlt, MwSt steht auf 22 %.** Die Belege rechnen damit
  449,50 € in 368,44 € netto + 81,06 € MwSt auf — und tragen zugleich die
  Fußzeile „kein Rechnung im steuerlichen Sinn", weil keine P. IVA
  hinterlegt ist. Entweder die Nummer fehlt nur in den Einstellungen, dann
  werden aus Belegen (BE-) Rechnungen (RE-); oder es gibt keine, dann darf
  der Satz nicht 22 % sein. Das gehört vor den Commercialista, nicht in
  meine Hand.
- Stripe-Webhook: fünf ungültige Unterschriften an einem Tag, nicht von
  eigenen Aufrufen. Vor dem Livegang das Webhook-Geheimnis mit dem
  abgleichen, das Stripe für den Endpunkt zeigt.
- Beispieldaten sind noch geladen; der erste echte Auftrag räumt sie
  automatisch weg.

### Rechnung mit dem echten Logo, Kundenseiten im Auftritt der Website (01.09.2026)

**Der PDF-Erzeuger kann jetzt Bilder.** Ein JPEG geht als DCTDecode-XObject
unverändert in die Datei — kein Umrechnen, keine fremde Bibliothek. Die Maße
liest `Pdf::jpegKopf()` aus der Markerkette des JPEG statt sie zu raten, und
ein fehlendes oder kaputtes Bild fällt still auf den gesetzten Schriftzug
zurück. Der Briefkopf liegt als `app/assets/briefkopf.jpg`, aus
`logo-full-light.png` ohne die Claim-Zeile (bei 98 pt Breite war sie nur noch
ein Grauschleier).

**Die steuerliche Widersprüchlichkeit ist im Code geschlossen, nicht im
Formular.** `Firma::mwst()` gibt ohne Partita IVA immer 0 zurück, und die
Rechnung druckt selbst dann keine IVA, wenn in einem alten Datensatz noch ein
Satz steht. Vorher standen 22 % im Feld, wurden herausgerechnet, und dasselbe
Dokument erklärte darunter, es sei keine Rechnung.

**Die P. IVA ist beantragt** — deshalb steht schon alles bereit, was der Tag
der Eintragung braucht: das Steuerregime als Einstellung (normal /
forfettario), der Pflichthinweis nach L. 190/2014 samt Marca-da-bollo-Vermerk
ab 77,47 €, und Codice fiscale, Partita IVA und Empfängerkode am Kunden
(Migration 012). Eintragen genügt: aus BE-Belegen werden RE-Rechnungen mit
eigener Nummernreihe ab 0001. Geprüft in allen drei Zuständen.

**Die Kundenseiten haben eine eigene `assets/css/kunde.css`** statt des
Verwaltungs-Stylesheets. Der Kunde kam von der Website und landete in einer
Oberfläche, die für den Betreiber gebaut ist. Jetzt: Logo, Blau-Cyan,
Archivo und Inter, ruhige Karten, Fingerziele ab 44 px. Ohne `font-stretch`
auf den kleinen Überschriften — Archivos Breitenachse reißt dort einzelne
Buchstabenpaare sichtbar auseinander.

### Wie Rechnungen versendet werden — und was rechtlich dazugehört (01.09.2026)

Die Frage war „wie werden Rechnungen versendet". Die ehrliche Antwort war:
gar nicht. `Mail::senden` kannte keine Anhänge, der Beleg lag nur zum
Herunterladen auf der Projektseite, und die Mail „Zahlung erhalten" erwähnte
ihn mit keinem Wort.

**Der schwerere Fund kam beim Nachlesen.** Art. 51 Abs. 7 Codice del Consumo
verlangt bei einem Fernabsatzvertrag mit einem Verbraucher die Bestätigung
des geschlossenen Vertrags auf einem **dauerhaften Datenträger**, spätestens
bevor die Leistung beginnt. Eine Webseite ist keiner, eine E-Mail mit Anhang
schon. Die beiden Haken auf `buchen.php` (AGB, ausdrückliches Verlangen nach
sofortigem Beginn nach Art. 51 Abs. 8) wurden korrekt eingeholt und wörtlich
gespeichert — nur nie zurückbestätigt.

**Jetzt geht bei bestätigter Anzahlung eine Auftragsbestätigung raus**, in
der Sprache des Kunden, mit dem was Art. 49 Abs. 1 verlangt: wer die Leistung
erbringt samt Anschrift, Telefon und Steuernummer; Bestellung, Gesamtpreis
und beide Raten mit Stand; Widerrufsrecht mit Frist und Verfahren; der
wörtliche Zustimmungstext mit Zeitpunkt; AGB und Datenschutz als Verweis. Im
Anhang das Muster-Widerrufsformular (Anhang I Teil B, vorbelegt) und der
Beleg.

`Widerruf.php` hält die Widerrufstexte an **einer** Stelle; `buchen.php` zieht
sie von dort. Was der Kunde beim Buchen liest, ist damit wörtlich das, was
ihm bestätigt wird.

**Was ausdrücklich NICHT hier passiert:** die elektronische Rechnung über das
SDI. Sie ist für Forfettari seit 2024 ohne Umsatzschwelle Pflicht, auch
gegenüber Privatpersonen (Empfängerkode `0000000`). Was diese Anwendung
verschickt, ist die *copia di cortesia* — gute Praxis, aber kein Ersatz. Der
SDI-Weg gehört zum Commercialista oder zu einem Rechnungsdienst.

**Offen und Uwes Entscheidung:** Auf dem Weg über eine Anfrage (Uwe legt die
Bestellung von Hand an) gibt es keine Haken — dort wird kein ausdrückliches
Verlangen nach sofortigem Beginn eingeholt. Die Auftragsbestätigung geht
trotzdem raus, nur ohne diesen Absatz. Ob das genügt, gehört vor einen
Anwalt.

### Kunden löschen — zwei Wege, weil es zwei Fälle sind (01.09.2026)

Es gab keine Löschfunktion. Ein schlichtes `DELETE` wäre auch nicht gegangen:
`orders`, `projects`, `invoices` und `websites` stehen auf `ON DELETE RESTRICT`,
die Datenbank hätte es verweigert. Und selbst wenn nicht — ein ausgestellter
Beleg muss zehn Jahre aufbewahrt werden (Art. 2220 Codice civile), auch dann,
wenn der Kunde seine Löschung verlangt. Die DSGVO nimmt genau diesen Fall in
Art. 17 Abs. 3 Buchst. b vom Löschrecht aus.

Deshalb `app/src/Kunde.php` mit zwei Wegen, beide in der Kundenakte unten:

**Löschen** — für Testkunden, Vertipper und Anfragen, aus denen nichts wurde.
`riegel()` prüft vorher auf ausgestellte Belege und eingegangene Zahlungen und
verweigert mit genau diesem Grund im Klartext, statt in einen Datenbankfehler zu
laufen. Sonst verschwindet der Kunde in einer Transaktion mit allem, was an ihm
hängt — Kindtabellen zuerst, hochgeladene Dateien erst nach dem Commit, weil ein
Rollback keine Bytes zurückholt.

**Anonymisieren** — für den echten Kunden, der sein Löschrecht ausübt. Weg sind
Name, Adresse, Steuernummern, Nachrichten, Dateien, Fragebogen, Anfragen, Zugang
und Verlauf; die Projektnamen werden neutralisiert, weil dort fast immer der
Kundenname steht. Bestellungen, Zahlungen und Belege bleiben.

Der entscheidende Punkt dabei: `Rechnung::pdf()` las die Anschrift bisher bei
jedem Aufruf frisch aus `customers` — nach einer Anonymisierung hätte jede alte
Rechnung ohne Empfänger dagestanden. Migration 013 gibt `invoices` deshalb die
Spalte `empfaenger`; sie wird beim Ausstellen gefüllt und vor dem Anonymisieren
für alle Altbelege nachgetragen. Gegen eine Prüfdatenbank ist das Beleg-PDF vor
und nach der Anonymisierung Byte für Byte dasselbe.

Dazu: `customers.anonym_am` kennzeichnet den Datensatz, die Kundenliste zeigt es,
die Akte sperrt Bearbeiten, Bestellung und Nachrichtenfeld — und `Mail::senden()`
schickt nichts mehr an eine Adresse unter `.invalid` (RFC 2606), sondern vermerkt
den Grund. Beide Wege verlangen ein getipptes Wort (`LÖSCHEN` bzw. `ANONYM`);
ein Klick allein ist zu wenig für etwas, das nicht rückgängig zu machen ist.

**Offen:** Eine noch eingetragene Website bleibt beim Anonymisieren stehen —
solange sie online ist, läuft der Vertrag, und dann ist der Zeitpunkt falsch.
Das steht als Hinweis über dem Knopf, ist aber nicht erzwungen.

## Umbau der Verwaltung, Schritt 1: Heute und Vorgänge (02.09.2026)

Uwes Auftrag: die Verwaltung leichter und verständlicher, aber ohne eine
Funktion zu verlieren — und der Ablauf soll von selbst weiterlaufen, bis
eine Entscheidung nötig ist. Vereinbart sind drei Schritte; das hier ist der
erste, und er ändert bewusst **kein** Verhalten, nur die Anordnung.

**Was das Problem war.** Derselbe Kunde stand in sechs Tabellen und auf
ebenso vielen Seiten: Anfrage, Bestellung, Projekt, Fragebogen, Zahlungen,
Nachrichten. Wer wissen wollte, wie weit einer ist, sah an vier Stellen nach.
Und das Dashboard zeigte Zahlen — Zahlen sagen aber nicht, was zu tun ist.

**`app/src/Vorgang.php`** legt über die Tabellen eine Sicht: einen Vorgang.
Das ist entweder eine Anfrage ohne Bestellung oder eine Bestellung samt
Projekt. Die Stufe wird **nicht gespeichert, sondern gerechnet** — aus
Tatsachen, nicht aus einem Statusfeld: Gibt es eine Bestellung? Ist die
Anzahlung da? Ist der Zahlungslink schon rausgegangen (`mails`)? Liegt der
Fragebogen ausgefüllt vor? Ein zweiter gespeicherter Fortschritt wäre ein
zweiter Ort für eine Wahrheit, die schon in den Daten steht, und zwei Orte
für eine Wahrheit driften auseinander. Der Projektstatus bleibt daneben — er
ist das, was der Kunde auf seiner Seite liest.

Daraus fällt die einzige Frage ab, die morgens zählt: **wer ist dran und was
ist der nächste Handgriff.** Acht Stufen: Gespräch · Angebot · Fragebogen ·
In Arbeit · Vorschau · Freigegeben · Online · Abgeschlossen.

**`/app/heute`** ist die Arbeitsliste: was auf dich wartet, was auf den
Kunden wartet (mit „seit X Tagen still"), und ganz oben, was klemmt — nur
Meldungen der Stufe Warnung und Fehler, damit ein echter Fehler nicht
zwischen Infomeldungen verschwindet. Jede Zeile trägt den Knopf, der den
Schritt macht.

**`/app/vorgaenge/<schlüssel>`** ist ein Kunde auf einer Seite: Gespräch,
Zahlungen, Fragebogen, Dateien, Belege, Website, Verlauf. Schlüssel sind
`a<id>` für eine reine Anfrage und `b<id>` für eine Bestellung; eine Anfrage,
aus der eine Bestellung wurde, leitet auf deren Vorgang um, damit ein Kunde
nie zwei Seiten hat.

**Was ausdrücklich nicht passiert ist.** Kein Knopf hat eine neue Logik. Alle
Formulare schicken an dieselben `tat`-Aktionen wie vorher. Geändert wurde nur,
dass diese Aktionen jetzt dorthin zurückkehren, wo der Knopf stand
(`zurueck()` in `app/index.php`, 27 Aktionen) — vorher sprang „Fragebogen
verschickt" immer ins Projekt, egal woher man kam. Für die alten Seiten
ändert das nichts: Sie schickten schon immer dasselbe Ziel mit.

Direkt aus der Liste abgeschickt wird nur, was eine Nachricht auslöst
(Zahlungslink, Einladung, Erinnerung, Restzahlung). Was den Projektstand
verschiebt, führt erst auf die Vorgangsseite — erkennbar am `›` am Knopf.

**Geprüft** gegen eine Prüfdatenbank mit zwölf Vorgängen über alle acht
Stufen, über den echten Router mit Anmeldung: jede Stufe landet in der
richtigen Gruppe mit dem richtigen Knopf, alle Seiten 200, keine PHP-Meldung,
Konsole sauber. Die Zahl im Menü neben „Heute" kostet rund 10 ms.

**Offen für Schritt 2:** „Angebot verbindlich machen" soll Bestellung,
Zahlungslink und Mail in einem Zug erledigen; „Vorschau ist fertig" und
„Seite ist online" sollen die Adresse gleich mitnehmen. **Schritt 3** ist das
Zusammenräumen des Menüs auf fünf Punkte. Das alte Menü steht bis dahin
vollständig.

### Meldungen: löschbar, weniger davon — und die wichtigen kommen wirklich an (02.09.2026)

Uwes Beobachtung war „die Liste wird immer länger". Beim Nachsehen war das
Problem ein anderes und schlimmeres: In seiner Liste standen zwanzig Zeilen,
und die beiden echten Störungen — *„Der E-Mail-Versand antwortet nicht mehr"*
und *„Fragebogen nicht erreichbar"* — lagen zwischen sechsmal „Neue
Bestellung" und sechsmal „Neue Anfrage" begraben. Eine Warnung, die niemand
findet, ist keine Warnung.

Drei Eingriffe, in dieser Reihenfolge wichtig:

**1. Fehlgeschlagene E-Mails melden sich jetzt überhaupt.** `Mail::senden()`
rief `Events::melden()` nur in dem Zweig, in dem Brevo geantwortet hatte.
Fehlt der Schlüssel ganz oder ist die Adresse ungültig, kehrt die Methode
vorher um — und dann scheiterte jede Mail still. Ein Probelauf zeigte es:
fünf gescheiterte Kundenmails, null Meldungen. Genau dieser Ausfall ist hier
schon einmal monatelang gelaufen. Die Meldung sitzt jetzt in `vermerken()`,
durch die **jeder** Fehlschlag läuft, und wird innerhalb einer Stunde
fortgeschrieben statt vervielfacht: eine Zeile, „5 E-Mails gingen nicht raus".

**2. Weniger Meldungen erzeugen.** Die Regel steht jetzt im Kopf von
`Events::melden()`: Gemeldet wird, was gestört ist oder von außen kam und eine
Reaktion braucht. Nicht gemeldet wird, was Uwe selbst ausgelöst hat — er hat
gerade geklickt. Entfernt: „Neuer Kunde", „Neue Bestellung" (die Direktbuchung
über die Website meldet sich weiterhin selbst), „Projektstatus geändert",
„Zahlungslink verschickt", „Beispieldaten entfernt". Alle fünf stehen
unverändert im Verlauf.

Gemessen an einem vollen Durchlauf (Anfrage → Bestellung → Zahlung → drei
Statuswechsel → Kundennachricht): vorher **acht** Meldungen, jetzt **drei** —
neue Anfrage, Geld da, Kunde hat geschrieben. Der Verlauf hat unverändert elf
Einträge.

**3. Löschen.** Jede Zeile hat ein `×`, ungelesene zusätzlich „Gelesen"; oben
„Gelesene löschen (N)". Ungelesenes räumt kein Knopf weg. Ungelesene stehen
oben. Auf „Heute" haben die Störungen ein „Erledigt". Der Cronjob entfernt
täglich gelesene Meldungen älter als 30 Tage und deckelt sie bei 300.

### Der Cronjob zieht die Datenbank nach (02.09.2026)

In den Meldungen stand vom 31.08.: *„Fragebogen nicht erreichbar — Unknown
column 'c.sprache'"*. Ein Kunde hatte seinen Link angeklickt und eine
Fehlerseite bekommen.

Die Ursache war strukturell: Migrationen spielte ausschließlich
`app/index.php` ein — also erst, wenn **Uwe** die Verwaltung öffnete. Nach
jedem Deploy mit einer neuen Spalte lief der neue Code bis dahin auf der alten
Datenbank, und zwar auch für `fragebogen.php`, `projekt.php`, `vorgang.php`,
`buchen.php`, `formular.php` und `stripe-webhook.php` — die Seiten, die dem
Kunden gehören und die niemanden fragen.

`cron.php` ruft jetzt `Einrichtung::selbsttaetig(false)` auf, direkt nach der
Schlüsselprüfung (vorher wäre es ein Weg für Fremde, Schreibvorgänge
anzustoßen). Der neue Schalter unterdrückt dabei die Beispieldaten — die
gehören an den ersten Blick eines Menschen, nicht an einen Lauf um drei Uhr
nachts. Da der KAS alle zehn Minuten aufruft, ist das Fenster nie größer als
zehn Minuten, und niemand muss daran denken.

Geprüft: ohne Schlüssel 404 und keine Migration, mit Schlüssel wird die
Testspalte angelegt und im Verlauf vermerkt, keine Beispieldaten, zweiter Lauf
tut nichts doppelt. Alle 19 Verwaltungsseiten weiterhin 200, keine PHP-Meldung.

### Zuruf aufs Handy: WhatsApp bei neuer Anfrage und bei Störungen (02.09.2026)

Uwe wollte zusätzlich per WhatsApp benachrichtigt werden. Der offizielle Weg
über Meta hätte Facebook-Business-Konto, Unternehmensverifizierung, eine eigene
Absendernummer, genehmigte Vorlagen und gekauftes Guthaben bedeutet — viel
Bürokratie dafür, sich selbst „neue Anfrage" zuzurufen. Gebaut ist deshalb der
Weg über CallMeBot: kostenlos, ausdrücklich für den eigenen Gebrauch, ein
HTTP-Aufruf.

**Der eigentliche Grund für diesen und keinen anderen Weg:** Er läuft an Brevo
vorbei. Während dieser Weg gebaut wurde, antwortete Brevo mit 500 — die
E-Mail über eine neue Anfrage kam gar nicht an. Ein zweiter Kanal ist nur dann
etwas wert, wenn er nicht an derselben Sache hängt wie der erste. Über Brevos
eigene WhatsApp-Schnittstelle wäre es derselbe Kanal gewesen.

**Keine personenbezogenen Daten.** Verschickt wird, *dass* etwas ist, und der
Link zur Verwaltung. Nie ein Kundenname, nie eine Adresse, nie der Text einer
Anfrage. Bei Störungen geht ausschließlich der TITEL der Meldung raus — die
Titel sind durchweg allgemein („Website nicht erreichbar"), Domain und
Einzelheiten stehen im Text, der nicht mitkommt. Damit ist die Frage nach einem
Auftragsverarbeiter gar nicht erst da.

**Warum eine Warteschlange und kein direkter Aufruf.** Der erste Bau rief den
Dienst am Ende der Anfrage über `register_shutdown_function` auf, mit
`fastcgi_finish_request()` davor. Beim Durchtesten mit einem absichtlich
langsamen Dienst wartete das Kontaktformular trotzdem **fünf Sekunden** — die
Funktion gibt es nur unter FastCGI. Darauf soll sich nichts verlassen müssen.

Jetzt legt `Zuruf::vormerken()` den Zuruf in `zurufe` ab (Migration 014) und
verschickt nichts. Abgearbeitet wird er sofort, wo der Server die Antwort
vorher abschließen kann, und sonst beim nächsten Cronlauf — dann ein paar
Minuten später, aber ohne dass jemand darauf wartet. Fehlschläge bleiben offen
und werden bis zu dreimal wiederholt, danach aufgegeben. Höchstens fünf je
Lauf. Mit dem langsamen Dienst antwortet das Formular jetzt in 14 ms.

**Sperre:** Bei Störungen höchstens eine Nachricht je Meldungsart alle 15
Minuten — sonst klingelt ein kaputter Mailversand das Handy leer. Neue Anfragen
haben keine Sperre; die sind selten genug.

`Zuruf::hinschicken()` meldet **niemals** über `Events::melden()` — der Zuruf
hängt selbst an jeder Störungsmeldung, das wäre eine Schleife. Was schiefging,
steht in der Warteschlange und in den Einstellungen unter „Zuletzt".

Eingerichtet wird in den Einstellungen direkt unter dem E-Mail-Versand: Nummer,
Schlüssel, Ein/Aus, Testnachricht. Der Schlüssel wird nie wieder angezeigt.
Die Dienstadresse ist wie beim Mailversand nur zum Durchtesten umstellbar
(`zuruf_api`).

**Der ehrliche Vorbehalt:** CallMeBot ist inoffiziell und kann ohne Ankündigung
verschwinden. Deshalb liegt der Aufruf hinter genau einer Klasse — fällt er
weg oder soll später der offizielle Weg her, wird dort das Innere getauscht und
sonst nichts. Und der Zuruf ist nie tragend: Die Anfrage steht in der
Datenbank und geht als E-Mail raus, egal was WhatsApp macht.

Geprüft mit einem eigenen Gegenstück statt des echten Dienstes: 28 Prüfungen —
Störung klingelt, zweite gleiche schweigt, andere Art klingelt, „info" und
„gut" nie, Anfrage ohne Namen und ohne Text, krumme Nummer wird abgelehnt ohne
die alte zu überschreiben, Dienst kaputt wird vermerkt ohne Meldungsschleife,
Warteschlange mit Wiederholung und Deckelung, Formular wartet nicht, Cronlauf
holt nach. Alle 19 Verwaltungsseiten 200, keine PHP-Meldung, Konsole sauber.

### Testdaten mit Belegen löschen (02.09.2026)

Nach dem Anonymisieren stand „Gelöschter Kunde #5" dauerhaft in der Liste und
ließ sich nicht entfernen: `Kunde::riegel()` sperrt das Löschen, sobald ein
Beleg ausgestellt oder eine Zahlung eingegangen ist. Das ist richtig — für
echte Vorgänge.

Für den Probelauf ist es falsch. Wer die eigene Verwaltung durchtestet, erzeugt
Bestellungen, Zahlungen und Belege für Vorgänge, die es nie gegeben hat. Diese
Belege sind keine Dokumente, die man aufbewahrt, sondern Fehleinträge — und sie
blockieren obendrein den Nummernkreis: Bleibt der Testbeleg BE-2026-0001
stehen, fängt der erste echte bei 0002 an, und eine italienische Nummerierung
muss im Jahr lückenlos sein. Das stand seit Tagen als offener Punkt.

`Kunde::loeschen($id, $auchBelege = true)` überspringt den Riegel und nimmt
Belege und Zahlungen mit. In der Kundenakte erscheint der Weg nur dort, wo das
normale Löschen gesperrt ist, mit einem eigenen Bestätigungswort (`ALLES
LÖSCHEN` statt `LÖSCHEN` — nicht damit es schwerer ist, sondern damit niemand
aus Gewohnheit das falsche tippt), einer Liste der betroffenen Belege mit
Nummer, Betrag und Datum, und dem Satz: *Hat der Kunde wirklich gezahlt, darfst
du das nicht.*

Was danach bleibt, ist die Prüfspur: `loeschen_mit_belegen` mit Nummer, Betrag
und Datum jedes vernichteten Belegs, plus ein Verlaufseintrag. Das ist das
Einzige, was noch bezeugt, dass es sie gab — und der Grund, warum dieser Weg
verantwortbar ist.

Geprüft: Riegel sperrt weiter, ohne Flag verweigert die Methode, falsches Wort
ändert nichts, mit richtigem Wort verschwinden Kunde, Bestellung, Projekt,
Zahlung und Beleg, Prüfspur und Verlauf nennen die Nummern — und
`Rechnung::naechsteNummer()` liefert danach wieder BE-2026-0001.

**Nebenbefund am selben Tag:** Das Brevo-Problem war vorübergehend. „Verbindung
prüfen" meldet wieder „Verbunden"; die tägliche Cron-Prüfung und die neue
Meldung bei jedem einzelnen Fehlschlag fangen den nächsten Ausfall ab.

## Eine Seite für den Kunden (02.09.2026)

Ausgangspunkt war Uwes Satz: *„für den kunde ist der ablauf etwas verwirrend."*
Er hatte recht, und der Grund stand im Code. Der Kunde bekam mehrere Adressen —
`vorgang.php` mit der Anfrage, `projekt.php` nach dem Auftrag, `fragebogen.php`
für die Angaben. Die erste leitete auf die zweite weiter, was gut gedacht war,
aber zwei Dinge nicht löste:

- **`projekt.php` hing am Fragebogen.** Es lud über `Onboarding::laden($token)`.
  Kein Fragebogen, keine Seite.
- **Nach dem Onlinegang gab es gar nichts mehr.** Wer ein halbes Jahr später
  eine Änderung wollte, musste eine alte E-Mail suchen.

### Der Schlüssel gehört an den Kunden

Migration `015_kundenlink.sql` gibt `customers` eine Spalte `token CHAR(48)`
(eindeutig) und `token_seit`. `Kundenzugang` verwaltet ihn: `token()` legt beim
ersten Mal einen an, `neu()` zieht den alten zurück, `ausToken()` findet den
Kunden, `linkFuer()` baut die Adresse. Damit ist es **eine** Adresse, vom ersten
Kontakt bis Jahre danach — und dieselbe, wenn der Kunde später eine zweite Seite
bestellt.

Er läuft **nicht ab**. Ein Zugang, der abläuft, ist genau dann kaputt, wenn man
ihn braucht: beim Kunden, der sich nach acht Monaten meldet. Die Seite zeigt
nichts, was Schaden anrichtet — kein Geld, keine fremden Daten, keine
Verwaltung. Gegen Weitergabe hilft kein Ablaufdatum, sondern ein Knopf: In der
Kundenakte und auf der Vorgangsseite steht *„Neuen Link erzeugen"*. Der nimmt
den alten Kundenschlüssel **und** die alten Anfrage- und Fragebogenschlüssel
zurück — sonst wäre das Zurückziehen eine Beruhigung ohne Wirkung, weil die
alten Links weiterhin auf die neue Seite geleitet hätten.

### `kunde.php`

Eine Seite, acht Stufen, immer genau ein Schritt hervorgehoben:

- **Fortschrittsleiste** mit sieben Marken. Auf dem Handy nur die Balken plus
  „Schritt 3 von 7" — sieben Beschriftungen bei 390 px sind sieben Wortanfänge
  mit Auslassungspunkten.
- **Ein Kasten** sagt, wer dran ist und was zu tun ist. Der Rest (Website,
  Gespräch, Belege, Material) liegt zugeklappt darunter.
- **Wer dran ist, wird aus SEINER Sicht bestimmt.** Uwes Arbeitsliste kennt
  Zwischenschritte, die den Kunden nichts angehen: Solange der Fragebogen nicht
  verschickt ist, wartet Uwe dort auf sich selbst. Auf der Kundenseite stünde
  dann „Wir sind dran" über einem Knopf, den nur der Kunde drücken kann.
  `Kundenzugang::seite()` nimmt deshalb `Texte::KUNDE_STUFEN[...]['wer']` und
  prüft nur nach, ob es den Knopf wirklich gibt.
- **Herunterladen** von Belegen und Dateien wird über die Kundennummer geprüft,
  nie über die Zahl im Link allein. Ein fremder Beleg gibt 404.
- **Alte Links sterben nicht**: `vorgang.php` und `projekt.php` schlagen den
  alten Schlüssel nach und leiten auf `kunde.php` um.

### Fragebogen in vier Schritten

Vorher standen einundzwanzig Felder auf einer Seite. Jetzt sind es vier
Abschnitte mit fünf bis sechs Feldern, mit POST → Redirect → GET dazwischen:
Neuladen wiederholt nichts, der Zurück-Knopf des Browsers tut, was er soll. Wer
zurückkommt, landet im ersten Abschnitt, in dem noch nichts steht.

**Dabei einen Fehler gefunden, den es vorher nicht geben konnte:**
`Onboarding::absenden()` ersetzte die Daten, statt sie zusammenzuführen — beim
alten Formular kamen ohnehin alle Felder mit. In Abschnitten hätte der letzte
Klick die ersten drei gelöscht. Jetzt führt `absenden()` zusammen wie
`speichern()`, und der Firmenname wird gegen das Gespeicherte geprüft, nicht
gegen das, was gerade im Formular steht.

### Kürzere E-Mails

Die Eingangsbestätigung war eine Wand: ein abgesetzter Block mit vier
nummerierten Schritten, der erklärte, wie die Seite funktioniert. Das gehört auf
die Seite, nicht in die E-Mail — auf der Seite steht es ohnehin. Die Mail ist
jetzt etwa ein Drittel so lang und behält, was zählt: unverbindlich, Antwort
innerhalb eines Werktags, der eine Link, die Dateigrenze.

Alle Kunden-Mails zeigen jetzt auf `kunde.php`: `Anfrage::link()`,
`Nachricht::link()` und `Onboarding::mailLink()` lösen über den Kunden auf und
fallen nur zurück, wenn keiner gefunden wird.

**Nebenbei:** „Deine Unterlagen" (Belege) und „Dateien" (sein Material) standen
untereinander und klangen gleich. Jetzt „Belege und Rechnungen" und „Dein
Material".

Geprüft am laufenden Stand mit MariaDB und echtem Server: alle acht Stufen in
drei Sprachen ohne Fehler, Fragebogen über vier Schritte ausgefüllt und
abgesendet (alle Abschnitte überlebten das Absenden), Nachricht, Freigabe,
Datei-Upload und beide Downloadwege, fremder Beleg und fremde Datei je 404, alte
Links leiten um, „Neuen Link erzeugen" entwertet den alten (die alte Adresse
zeigt danach die Meldung, nicht die Seite), Formular → Bestätigungsmail →
Kundenseite als durchgehende Kette, Handy- und Rechneransicht ohne Überlauf.

## Vorlagen und Betreffzeilen (02.09.2026)

Uwes Beobachtung: Jede Nachricht aus der Verwaltung ging mit demselben Betreff
raus — *„Eine Nachricht zu deinem Projekt"*. Zehn Nachrichten, zehn gleiche
Betreffzeilen. Der Kunde findet nichts wieder, und gleichlautende Serienbetreffe
sind ein Merkmal, auf das Spamfilter achten.

### Kennung im Betreff

`Vorlage::kennung()` liefert die Bestellnummer, wenn es eine gibt, sonst eine aus
der Kundennummer gebildete (`VD-K-0023`). Berechnet, nicht gespeichert — eine
weitere Spalte wäre eine weitere Stelle, die auseinanderlaufen kann.

`Vorlage::betreff()` setzt sie **genau einmal** davor: `[VD-2026-0005] Deine
Vorschau steht`. Eingebaut ist das nicht im Formular, sondern zentral in
`Mail::senden()`, sobald ein `customer_id` mitkommt — damit es keinen Weg nach
draußen gibt, der die Kennung vergisst. Auftragsbestätigung, Zahlungslink,
Vorschau, Rechnung: alle tragen sie jetzt.

**Ehrlich zur Wirkung:** Die Kennung hilft beim Wiederfinden und beim Threading.
Gegen Spamfilter wirken vor allem SPF/DKIM für die Domain und der Wegfall der
Brevo-Fußzeile — das bleibt offen.

### Vierzehn Vorlagen, dreisprachig

Vorher vier Vorlagen, nur deutsch. Der freie Text geht wörtlich raus: Wer
deutsch an eine italienische Kundin schreibt, schickt ihr deutsch. `Vorlage::ALLE`
hat jetzt vierzehn Vorlagen in drei Sprachen, nach Phase gruppiert:

- **Vor dem Auftrag** — Rückfrage vor dem Angebot · Angebot zum Festpreis ·
  Termin vorschlagen · Nachfassen · Absage
- **Während der Arbeit** — Material anfordern · Fragebogen-Erinnerung · Vorschau
  ist fertig · Änderungen umgesetzt · Zahlungserinnerung
- **Danach** — Seite ist online · Betreuung anbieten · Um Bewertung bitten ·
  Ruhendes Projekt

`Vorlage::fuer($kundeId)` setzt sie in der Sprache des Kunden zusammen und füllt
`{vorname} {firma} {paket} {betrag} {seite} {vorschau}`. Was unbekannt ist, wird
zu „…" — eine leere Stelle rutscht beim Lesen durch, drei Punkte nicht. Anrede
und Gruß hängen an der Sprache, nicht am Anlass, und stehen deshalb einmal in
der Klasse statt zweiundvierzig Mal in den Vorlagen.

### Was sonst nötig war

- **Migration `016`**: `messages.betreff`. Der Betreff gehört an die Nachricht,
  nicht nur an die E-Mail — sonst stünde im Verlauf, bei Uwe wie beim Kunden, ein
  Text ohne die Zeile, unter der er verschickt wurde. `Nachricht::spalten()` lässt
  die Spalte weg, solange die Migration noch nicht durch ist: Zwischen Deploy und
  nächstem Cronlauf liegen bis zu zehn Minuten, in denen keine Nachricht an einer
  fehlenden Spalte scheitern darf.
- **Kein doppelter Umschlag**: Eine Vorlage ist ein fertiger Brief mit Anrede und
  Gruß. Mit eigenem Betreff entfällt deshalb der alte Rahmen („wir haben dir zu
  deinem Projekt geschrieben:"). Ohne Betreff bleibt alles wie bisher.
- Der Link auf die Kundenseite wird nur angehängt, wenn er nicht ohnehin schon im
  Text steht — die Vorlagen bringen ihn oft selbst mit.
- **Ein gemeinsames Feld**: `app/views/nachrichtfeld.php`, eingebunden von
  Kundenakte und Vorgangsseite. Die Kennung steht fest davor und ist nicht
  editierbar: Sie ist kein Text, sondern ein Aktenzeichen. Ein Vorlagenwechsel
  über bereits Getipptem fragt nach, bevor er überschreibt.

Geprüft: vierzehn Vorlagen für einen italienischen und einen deutschen Kunden
gefüllt, Betreffzeilen und Platzhalter richtig; Nachricht mit Betreff über beide
Wege (Kundenakte ohne Projekt, Vorgangsseite mit Projekt) verschickt und im
Mailfänger nachgelesen — Kennung vorn, kein doppelter Rahmen, Link genau einmal;
ohne Betreff bleibt der alte Weg unverändert; Eingangsbestätigung trägt die
Kennung jetzt ebenfalls; Betreff steht im Verlauf der Verwaltung und auf der
Kundenseite.

## Zustellbarkeit: SPF, DKIM, DMARC (02.09.2026)

Uwe wollte SPF und DKIM eingerichtet haben. Erster Schritt war nachsehen statt
loslegen — und der Befund war ein anderer als erwartet:

| Eintrag | Zustand |
|---|---|
| `brevo1._domainkey` / `brevo2._domainkey` | **stand schon**, beide CNAME zeigen auf Brevo, beide Schlüssel lösen auf |
| `_dmarc` | **stand schon**: `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com;` |
| `brevo-code` TXT | steht — die Domain ist bei Brevo bestätigt |
| SPF | `v=spf1 a mx include:spf.kasserver.com ~all` — **kennt Brevo nicht** |

DKIM war also längst erledigt. Brevo verlangt SPF für die Domain-Authentifizierung
ausdrücklich nicht (die Zustellung hängt an DKIM, und den Rückweg setzt Brevo auf
eine eigene Domain), aber `include:spf.brevo.com` schadet nicht und manche Filter
sehen darauf.

### Was gebaut wurde: `app/src/Zustellbarkeit.php`

Wichtiger als die einmalige Korrektur ist, dass es jemand merkt, wenn einer der
Einträge verschwindet. Diese drei richtet man einmal ein und sieht sie nie wieder
an — genau die Art Sache, die ein Jahr später still kaputt ist. Nach außen merkt
man nichts: Die Mails gehen weiter raus, sie landen nur zunehmend im Spam, bis
jemand anruft und sagt, er habe nie eine Rechnung bekommen.

- `pruefen()` schlägt SPF, beide DKIM-Selektoren und DMARC nach und beurteilt sie
  im Klartext, nicht mit „OK/FAIL".
- **Der Cronjob fragt täglich**, meldet aber nur bei *Verschlechterung* — und
  einmal, wenn es sich erholt. Eine Meldung, die jeden Tag dasselbe sagt, liest
  nach einer Woche niemand mehr.
- **Die Ansicht liest nie live**, sondern den gespeicherten Befund. Grund ist
  gemessen: `dns_get_record` kennt keine Zeitgrenze, und eine hängende Abfrage
  hätte die Verwaltung festgehalten.
- Beim allerersten Lauf heißt die Meldung „stimmt etwas nicht" statt „nicht
  **mehr**" — vorher war nie etwas in Ordnung, das jetzt kaputt sein könnte, und
  die falsche Formulierung schickt einen auf die Suche nach einer Änderung, die
  es nicht gab.

**Ein gemessener Fallstrick:** `dns_get_record('brevo1._domainkey.…', DNS_TXT)`
läuft in eine Zeitüberschreitung, die PHP nicht abbrechen kann — der Name ist ein
CNAME auf einen fremden Server. Erst den CNAME holen und dann den Text beim Ziel
lesen dauert Millisekunden. Die Reihenfolge steht deshalb ausdrücklich so im Code,
mit dem Grund daneben.

### Und was DNS nicht beantwortet

Dass die Einträge dastehen, heißt nicht, dass Brevo auch mit ihnen signiert. Das
sagt nur eine zugestellte Mail. Deshalb sitzt unter dem Block ein Feld
„Probenachricht senden": Adresse von mail-tester.com eintragen, senden, dort
nachladen.

Bewusst **nicht** `check-auth@verifier.port25.com`, obwohl das der bekanntere
Dienst ist: Der antwortet an den Rückweg der Mail, und den setzt Brevo auf eine
eigene Domain — die Auswertung käme bei Brevo an, nicht bei Uwe.

Geprüft: Befund für vecom-design.it in 0,48 s (SPF Warnung, DKIM und DMARC gut),
Ansicht ohne DNS-Abfrage, „Jetzt nachschlagen" schreibt den Befund neu, tägliche
Meldung feuert einmal und dann nicht mehr, Probenachricht im Mailfänger
angekommen — ohne Kundennummer im Betreff, weil sie zu keinem Kunden gehört.

### Die SPF-Zeile ist geändert (02.09.2026)

Uwe hat sich im KAS angemeldet, ich habe den Eintrag über seinen Browser
geändert — Zugangsdaten habe ich keine eingegeben, er war bereits angemeldet.
Aus

    v=spf1 a mx include:spf.kasserver.com ~all

wurde

    v=spf1 a mx include:spf.kasserver.com include:spf.brevo.com ~all

Der autoritative Nameserver liefert es, die Prüfung in der Verwaltung steht auf
allen drei Punkten grün.

**Dabei ein Fehler in der eigenen Prüfung aufgefallen — durch Messen, nicht durch
Nachdenken.** Direkt nach der Änderung meldete der tägliche Lauf abwechselnd
„alles gut" und „stimmt etwas nicht mehr". Grund: Solange die alte Gültigkeitsdauer
läuft, antworten selbst die beiden Server desselben Anbieters verschieden — acht
Abfragen hintereinander ergaben sechsmal den alten und zweimal den neuen Stand.

Eine Prüfung, die daraus sofort eine Meldung macht, meldet einen Ausfall, den es
nie gab, und am nächsten Tag die Entwarnung dazu. Genau so gewöhnt man sich ab
hinzusehen — und dann fehlt sie an dem Tag, an dem wirklich etwas kaputt ist.

Deshalb muss eine schlechte Nachricht jetzt **zweimal kommen**: Der erste
schlechte Befund wird nur vorgemerkt (`zustellbarkeit_verdacht`), gemeldet wird
erst, wenn der nächste Lauf ihn bestätigt. Zwei Läufe liegen einen Tag
auseinander, und ein Tag ist hier kein Problem: Es brennt nichts, ein Fehlalarm
wäre teurer. Der **angezeigte** Befund bleibt davon unberührt — die Seite zeigt
immer die letzte Messung, nur die Meldung wartet.

Geprüft: einmal schlecht und dann wieder gut meldet nichts und löscht den
Verdacht; zweimal schlecht meldet genau einmal und danach nie wieder; die
Erholung meldet einmal.

**Offen:** Wenn über Wochen alles sauber signiert ist, DMARC von `p=none` auf
`p=quarantine` heben.

## Die Vorschau wird freigeschaltet, nicht gespeichert (02.09.2026)

Uwes Beobachtung: Der Vorschau-Link ist je Kunde ein anderer und muss von Hand
gesetzt werden — der Bereich beim Kunden sollte deshalb inaktiv sein und erst
aktiv werden, wenn der Link freigegeben ist. Beim Nachsehen zeigte sich, dass
das nicht nur fehlte, sondern in einer Richtung einen Kunden ins Leere schickte.

**Was vorher war:**

- Der Link wurde in dem Moment sichtbar, in dem er **gespeichert** wurde. Kein
  Vorbereiten, kein Zwischenstand für sich behalten.
- Schlimmer die andere Richtung: Die Stufe „Dein Entwurf ist fertig" hing am
  Projektstatus, der Knopf am Vorschau-Link — zwei Dinge, die nichts voneinander
  wussten. Status auf „Vorschau" ohne eingetragene Adresse hieß: Der Kunde bekam
  die E-Mail *„Deine Vorschau steht"*, klickte, las „Dein Entwurf ist fertig" und
  fand nichts zum Anklicken.
- Das Feld stand nur auf der alten Projektseite, nicht auf der Vorgangsseite, mit
  der seit dem Umbau gearbeitet wird.

**Was jetzt gilt** (Migration `017`, Spalte `projects.vorschau_frei_am`):

- **Eintragen und Freischalten sind zwei Dinge.** Die Adresse darf lange
  dastehen; der Kunde sieht sie erst nach einem ausdrücklichen Klick.
- **Beim Kunden steht der Bereich trotzdem** — gestrichelt, grau, mit einem Satz:
  *„Sobald dein Entwurf fertig ist, kannst du ihn hier ansehen — wir sagen dir
  Bescheid."* Versteckt wäre er eine Leerstelle, die Fragen erzeugt. Er erscheint
  ab der Stufe „Wir bauen"; vorher wäre er verfrüht.
- **Die E-Mail hängt an der Freigabe**, nicht mehr am Projektstatus.
  `Nachricht::vorschauBereit()` verweigert zusätzlich den Dienst, wenn keine
  Adresse eingetragen oder nichts freigegeben ist — die Mail kann also nicht mehr
  rausgehen, bevor es etwas zu sehen gibt.
- **Freischalten zieht den Projektstand mit** auf „Vorschau". Und wer den Stand
  von Hand auf „Vorschau" setzt, ohne dass eine Adresse eingetragen oder
  freigegeben ist, bekommt das gesagt — statt dass der Kunde es merkt.
- **Wieder sperren** geht mit einem Klick, mit Rückfrage. Gedacht für den Fall,
  dass beim Draufschauen doch noch etwas auffällt.
- **Adresse tauschen bei laufender Freigabe wechselt still**: Der Kunde klickt
  weiter denselben Knopf und sieht ab sofort die neue Adresse. Keine zweite
  E-Mail — er hat nichts Neues zu tun. Die Rückmeldung in der Verwaltung sagt
  das ausdrücklich, damit es keine Überraschung ist.

**Rückwirkend:** Wer seine Vorschau nach der alten Regel schon sehen konnte,
verliert sie nicht. Die Migration gibt allen Projekten mit Adresse, die im Ablauf
bei der Vorschau oder dahinter stehen, ein Freigabedatum. Früher stehende
Projekte bleiben gesperrt — dort war die Adresse ohnehin nur ein Zwischenstand.

**Zwischen Deploy und Cronlauf** fehlt die Spalte für bis zu zehn Minuten. Beide
Seiten behandeln das ausdrücklich: die Kundenseite fällt auf die alte Regel
zurück (sonst verschwände eine freigegebene Vorschau kurz), die Verwaltung zeigt
den Schalter so lange gar nicht (statt einen Knopf anzubieten, der auf einen
Fehler läuft).

Geprüft am laufenden Stand: ohne Adresse grauer Kasten und gesperrter Knopf mit
Begründung; Adresse gespeichert → Kunde sieht weiterhin nichts; freigeschaltet →
Stufe wechselt auf „Dein Entwurf ist fertig", Knopf da, E-Mail mit Kennung im
Betreff angekommen; Adresse getauscht → neue Adresse beim Kunden, keine zweite
Mail; wieder gesperrt → Knopf weg, grauer Kasten zurück; Freischalten ohne
Adresse abgelehnt; Status von Hand auf „Vorschau" ohne Freigabe warnt.

## Sechsunddreißig Vorlagen, alle fertig ausgefüllt (02.09.2026)

Uwes Wunsch: alle Vorlagen vollständig, eine Betreuungs-Vorlage mit Preisen und
Inhalt, und deutlich mehr Vorlagen, die den Alltag abdecken.

Aus vier wurden **36**, in drei Sprachen, in fünf Gruppen:

| Gruppe | Anzahl | Beispiele |
|---|---|---|
| Vor dem Auftrag | 11 | Rückfrage vor dem Angebot · Angebot Starter/Business/Premium · „Was kostet eine Website?" · „Wie lange dauert es?" · Kleineres Paket · Absage |
| Auftrag und Start | 6 | Auftrag bestätigt · Anzahlung · Fragebogen · Material · Logo in besserer Qualität · Domain und Zugänge |
| Während der Arbeit | 8 | Texte schreibe ich · Vorschau fertig · Schon reingeschaut? · Änderungen umgesetzt · Über den Umfang hinaus · Verzögerung |
| Abschluss und Zahlung | 5 | Bitte um Freigabe · Restzahlung · Zweite Erinnerung · Beleg erklärt · Seite ist online |
| Danach und Betreuung | 6 | Betreuung mit Preisen · Laufzeit endet · Bewertung · Nach Monaten melden · Störung / behoben |

**Keine Lücken mehr.** Vorher standen in mehreren Vorlagen leere Absätze, in die
Uwe selbst schreiben musste. Jetzt ist jede ein fertiger Brief. Geprüft wird das
maschinell: kein `\n\n\n`, kein unersetzter Platzhalter, jede Sprache gefüllt.

**Die Betreuung mit Zahlen.** `{betreuung}` und `{betreuunginhalt}` setzen den
Monatspreis des bestellten Pakets und die vollständige Leistungsliste ein, dazu
die Bedingungen (zwölf Monate Erstlaufzeit, danach zum Monatsende kündbar) und
ausdrücklich, was ohne Betreuung passiert.

**Zwei Dinge, die beim Lesen des Ergebnisses auffielen:**

1. *„Alles aus der Starter-Betreuung, plus:"* — so steht es auf der Website, und
   dort ist es richtig. In einem Brief ist es wertlos: Der Kunde hat die
   Starter-Liste nie gesehen. Die Listen werden deshalb aufsummiert.
2. Beim Aufsummieren der Paketmerkmale stand dann „Website bis 5 Seiten" und drei
   Zeilen später „bis zu 10 Seiten". Die aufgelösten, widerspruchsfreien Listen
   liegen deshalb fertig in `vorlagen.json`, statt zur Laufzeit zusammengeklebt
   zu werden.

**Angebote nageln ihr Paket selbst fest.** Die Preise kamen bisher aus der
Bestellung des Kunden — nur schreibt man ein Angebot, *bevor* es eine Bestellung
gibt. Genau die wichtigste Vorlage war also leer, wenn man sie brauchte. Deshalb
gibt es das Angebot dreimal, je Paket, jeweils mit echtem Preis, Anzahlung,
Restzahlung und vollständiger Leistungsliste. Für einen Kunden mit Bestellung
sind alle 36 Vorlagen vollständig gefüllt; ohne Bestellung bleiben vier mit „…",
und die betreffen alle einen offenen Betrag, den es noch nicht gibt.

**Wo sie liegen:** `app/src/vorlagen.json` — 36 mal drei Sprachen sind zu viel
für eine PHP-Konstante, und in JSON lassen sie sich ohne Escaping pflegen.
Dieselbe Lösung wie bei `standardpakete.json`.

**Ein Fehler, den ich selbst gebaut und selbst ausgelöst habe:** Ich hatte in das
Skript einen Kommentar geschrieben, warum `JSON_HEX_TAG` nötig ist, damit ein
schließendes Skript-Tag in einer Vorlage den Block nicht vorzeitig beendet — und
das Tag wörtlich in den Kommentar gesetzt. Der Kommentar über die Gefahr war die
Gefahr; die Seite warf „Unexpected end of input" und die Vorlagenauswahl tat
nichts. Aufgefallen im Browser, nicht beim Lesen.

**Kleinigkeit mit großer Wirkung:** Das Textfeld wächst jetzt mit dem Inhalt. Ein
fertiger Brief in einem sieben Zeilen hohen Fenster heißt, dass man scrollen muss,
um zu sehen, was man verschickt.

Geprüft: 36 Vorlagen mal drei Sprachen ohne unersetzte Platzhalter und ohne
Lücken; die aufsummierten Listen widerspruchsfrei; alle drei Angebotsvorlagen
auch ohne Bestellung vollständig; das Betreuungsschreiben über den echten
Versandweg verschickt und im Mailfänger Zeichen für Zeichen mit der Vorlage
verglichen — identisch, nichts abgeschnitten; beide Skriptblöcke der Seite mit
`node --check` geprüft.
