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
- **Kunden löschen** ist nicht vorgesehen — bearbeiten ja.
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
