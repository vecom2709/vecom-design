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
  aus `index.html` + `i18n-it.js` / `i18n-de.js` / `i18n-en.js` durch `build.mjs`.
- Kein Festpreis-Paket mehr (seit 12.09.2026). Der Preis entsteht im Konfigurator aus dem
  Umfang; auf der Startseite stehen vier Beispielspannen, die live aus der Datenbank kommen
  (`preise-daten.php`), und im HTML dieselben Zahlen als Rückfall. Verkauft werden zusätzlich
  zwei Monatsverträge: Betreuung 39 €/Monat und Domain & Hosting 9,90 €/Monat.
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
- **05.09.2026 — Kein Tagmodus. Stattdessen wandert die Sonne durch die Nacht.** Der helle Modus
  ist wieder entfernt (`theme.css`, `thema.js`, Umschalter, `THEMEN.light`). Grund: Glanz ist
  Kontrast, und über Weiß gibt es keinen — jeder Versuch, dasselbe hyperreale Metall auf hellem
  Grund zu halten, endete bei Milchglas. Geblieben ist `World.setTageszeit()`: Es liest die Uhr
  des Besuchers und setzt Sonnenstand, Lichtfarbe und Kantenton im dunklen Studio. Morgens tief
  und warm von links, mittags hoch und neutral, abends warm von rechts, nachts kühl und flach.
  Der Körper bleibt tief, es wandert nur das Licht.
- **05.09.2026 — Jede Kundenmail läuft auf dem Briefbogen von Vecom Design.** Farbstreifen in den
  Markenfarben, Wortmarke als **Schrift** (nicht als Grafik — entfernte Bilder lädt jedes zweite
  Programm nicht, und eine Marke, die als leerer Rahmen ankommt, ist keine), Knopf im Markenblau,
  Fuß mit Anschrift, Kontakt und Steuernummern aus `Firma`, eigener Dunkelmodus, Vorschauzeile für
  den Posteingang. Bewusst **nicht**: Kopfbild, Logografik, Farbflächen, Spalten, Symbole — solche
  Mails werden als Werbung eingestuft, und die Zustellung ist hier ein offener Punkt. Bank und IBAN
  stehen nur auf dem Beleg, nie unter einer Mail mit Zahlungsknopf. Der reine Text geht unverändert
  mit.
- **05.09.2026 — Ansehen und Abnehmen sind zwei Schalter, nicht einer.** `vorschau_frei_am`
  erlaubt dem Kunden, den Entwurf anzusehen und Änderungen zu wünschen; `abnahme_frei_am` erlaubt
  ihm, „Passt so" zu sagen. Grund: Bisher stand der Abnahmeknopf neben jedem freigeschalteten
  Entwurf — und weil er auch ohne eingetragene Adresse dastand, konnte jemand eine Seite abnehmen,
  die er nie gesehen hatte. An der Abnahme hängen Restzahlung und Veröffentlichung. Geprüft wird
  serverseitig, nicht durch das Verstecken des Knopfes. Die zweite Freigabe schickt die Nachricht
  „die Seite ist fertig" und nennt die Kostenregel: Änderungen im vereinbarten Umfang sind
  enthalten, alles darüber bekommt der Kunde vorher als Angebot mit Preis.
- **05.09.2026 — Die Ambitionsstufe kommt aus Branche, Preis und Kundenwunsch, in dieser
  Reihenfolge.** Die Branche (`Technik::GRUNDSTUFE`) sagt, wo ein Gewerbe von Haus aus liegt;
  der Preis setzt eine Decke und darf höchstens eine Stufe anheben; was der Kunde ausdrücklich
  will, schlägt beides. Grund: Der Preis sagt, wie *viel* Arbeit bezahlt ist, nicht *welche* —
  ein Schlosser mit 3.000 € Budget bekommt trotzdem kein Scroll-Kino, weil bei ihm nichts
  zwischen Besucher und Telefonnummer gehört. Widersprechen sich Wunsch und Branche, wird das
  im Auftrag und auf der Werkstatt-Kachel benannt statt still aufgelöst.
- **05.09.2026 — Die Kreativdoktrin steht in `Haltung.php`, nicht im Prompt.** Aus dem langen
  Grundsatztext („Creative Tech OS", 77 Abschnitte) wurde übernommen: kreative These in fünf
  Sätzen, der eine Moment, der emotionale Bogen, Szenen statt Abschnitte, die Abstufung nach
  Gerät, gestaltete Fehlerfälle und ein Selbstzeugnis vor dem Zeigen. Jeder Block erscheint erst
  auf der Stufe, auf der er trägt. Grund: derselbe wie bei `Technik.php` — wer alles fordert,
  fordert nichts, und „baue ein Erlebnis" ohne Gegengewicht erzeugt genau die Seite, auf der die
  Öffnungszeiten hinter einer Animation liegen.
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

## Offen (Stand 06.09.2026)
- **Statistiken** ist die letzte Platzhalterseite.
- **Monatliche Betreuung als Stripe-Abo** — braucht ein freigeschaltetes Stripe-Konto.
- **Stripe ist nicht live**: offen ist das Ausweisdokument (`company.verification.document`).
  Ebenso der `whsec_`-Schlüssel in den Integrationen.
- **Partita IVA und der steuerliche Hinweistext** fehlen — solange bleiben es Belege.
  Gehört zum commercialista, nicht hierher.
- **Firmendaten** (Straße, IBAN) sind in den Einstellungen noch nicht gefüllt; sie stehen auf
  jedem Beleg.
- **1,73 MB tote Dateien im KAS** liegen noch da.
- **Der Telefonassistent hat noch nie geklingelt.** Die Endpunkte sind live und geprüft, aber
  von STRATO wurde noch keiner gerufen. Drei Testanrufe (it/de/en) stehen aus — und erst ein
  echter Anruf zeigt, welches Format der Post-Call-Webhook schickt.

**Erledigt und hier korrigiert:** Der Cronjob im KAS stand bis 05.09. als offen. Er ist
angelegt und läuft — nachgesehen, nicht angenommen: zwei Läufe um 22:10 und 22:20, ohne dass
jemand ihn angestoßen hat. Ein einzelner Job genügt, `cron.php` verteilt intern.

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

## Erstellung und Betreuung sind zwei Produkte (02.09.2026)

Uwes Vorgabe: Website-Preise und Betreuungspreise getrennt halten, damit der
Kunde die Betreuung zusätzlich **oder allein** kaufen kann. Dazu die Frage, was
der Kunde eigentlich bekommt und was in der Branche üblich ist.

**Beim Nachsehen zwei Widersprüche im eigenen Bestand gefunden:**

1. Die Paketlisten mischten genau das, was getrennt werden sollte. Im
   Starter-Paket für 499 € standen „Monatliche Backups und Updates", „Kleine
   Änderungen inklusive" und „Direkte Betreuung ohne Ticketsystem" — alles
   Leistungen, die zusätzlich mit 39 € im Monat berechnet werden. Wer genau
   liest, fragt zu Recht, wofür er monatlich zahlt.
2. Auf der Website stand „zzgl. MwSt.", auf den Belegen 0 %. Ohne P. IVA gibt es
   keine ausgewiesene IVA — der Satz versprach eine Rechnung, die es nicht gibt,
   und ließ den Preis niedriger aussehen, als er ist.

**Was gebaut wurde** (Migration `018`, Spalte `packages.art`):

- Drei Produktarten: `website` (einmalig), `betreuung` (monatlich), `zusatz`
  (einmalig). `monthly_cents` bleibt beim Website-Paket stehen — als *empfohlene*
  Betreuung zur Größe, die das Angebotsschreiben nennt, aber nicht mitverkauft.
- **Drei Betreuungspakete** als eigene Produkte: Basis 39 €, Plus 69 €,
  Premium 99 € im Monat, mit vollständigen, aufsummierten Listen.
- **Bestandsaufnahme, 99 € einmalig**, für Seiten, die Uwe nicht gebaut hat:
  Prüfung von Bau, Aktualisierungen, Sicherungen, Tempo, Domain und Zertifikat,
  schriftlicher Befund — und wird auf die Betreuung angerechnet. Der Grund steht
  in der Vorlage: eine Seite zu betreuen, die man nie angesehen hat, hieße für
  fremde Arbeit geradezustehen.
- **`Einrichtung::paketeTrennen()`** legt die neuen Pakete an und entwirrt die
  Listen der Website-Pakete — aber nur dort, wo noch die ausgelieferte Liste
  steht. Was Uwe selbst eingetragen hat, bleibt seins. Läuft einmal, nach der
  Migration, vom Cronjob angestoßen.

**Ein Fallstrick, der beinahe live gegangen wäre:** Die Betreuungspakete stehen
auf `öffentlich`, und `pakete-live.js` **ersetzt** die Preiskarten der Startseite
aus genau dieser Liste. Ohne Filter wären sofort drei zusätzliche Karten mit
„0 €" erschienen. `pakete-daten.php` liefert die Arten deshalb getrennt aus:
`pakete` bleibt exakt das, was es war, `betreuung` und `zusatz` stehen daneben
und werden nur angezeigt, wer sie ausdrücklich holt. Eine neue Produktart kann
die Preiskarten so nicht kapern.

**Zwei neue Vorlagen-Bausteine:** `{alle_betreuung}` (alle Betreuungspakete mit
Preis und Inhalt) und `{bestandsaufnahme}`. Die Antwort „Was kostet eine
Website?" hat jetzt zwei getrennte Blöcke und sagt ausdrücklich, dass beides
einzeln geht. Dazu die 37. Vorlage: **„Betreuung für bestehende Seite"** — mit
dem ehrlichen Satz, dass Uwe für eine fremd gehostete Seite zwar überwachen und
warnen, aber nicht für die Erreichbarkeit geradestehen kann.

**MwSt.-Angaben korrigiert** in `i18n-data.js` und in den drei `index.html`
(die tragen den Text vor dem Übersetzen und für Suchmaschinen): aus „einmalig ·
zzgl. MwSt." wird „einmalig", aus „Alle Preise zzgl. MwSt." wird „Die genannten
Preise sind die, die du zahlst. Die monatliche Betreuung ist freiwillig und auch
allein erhältlich." In allen drei Sprachen.

Geprüft: Migration und Trennung liefen sauber durch (drei Pakete entwirrt, vier
angelegt, keins unberührt übergangen); `pakete-daten.php` gibt drei Website-,
drei Betreuungs- und ein Zusatzpaket in getrennten Listen; die Startseite zeigt
in allen drei Sprachen weiterhin genau drei Karten, jetzt mit „einmalig" statt
„zzgl. MwSt."; alle Verwaltungsseiten und alle Kundenseiten ohne Fehler.

**Offen und bewusst nicht mitgemacht:** Die Preis-Sektion der Website zeigt die
Betreuung weiterhin nur als Zusatzzeile an der Karte, nicht als eigenen Block.
Die Daten dafür liegen bereit (`betreuung`, `zusatz` in `pakete-daten.php`) — die
Gestaltung ist ein eigener Durchgang. Und: **Betreuung allein lässt sich noch
nicht bezahlen.** Dafür braucht es Stripe-Abonnements, und das steht seit Längerem
als offener Punkt. Bis dahin ist die Betreuung allein ein Angebot per Nachricht,
kein Kaufknopf.

## Betreuungsverträge und der Ordner fürs Finanzamt (02.09.2026)

Uwes Auftrag: Ein monatliches Paket muss vollständig funktionieren — Zahlart,
zwölf Monate Mindestlaufzeit, danach monatlich, automatisch geprüfte Kündigung
mit Bestätigung, und zum Kündigungsdatum wird nicht mehr abgebucht. Dazu: nur
abbuchen, kein Überweisen. Und ein Ordner mit allem, was das Finanzamt braucht.

**Zwei Dinge musste ich vorweg sagen, bevor irgendetwas gebaut wurde:**

1. **Der Stripe-Webhook stimmt nicht** — sieben fehlgeschlagene Ereignisse.
   Bei einer Einmalzahlung ärgerlich, bei einem Abo tödlich: Wiederkehrende
   Abbuchungen laufen vollständig über Webhooks. Ohne sie erfährt das System
   nie, dass eine Zahlung kam, nie, dass eine scheiterte.
2. **Es gibt noch keine P. IVA.** Wiederkehrende monatliche Einnahmen sind der
   Kern dessen, wofür sie da ist. Das gehört vor das erste echte Abo, und zwar
   zum Commercialista, nicht zu mir.

Deshalb in zwei Stufen. **Gebaut wurde Stufe 1** — alles, was ohne Stripe
richtig ist:

### Der Vertrag (Migration `019`, Tabelle `abos`)

Eine Bestellung hat einen Endpreis, eine Anzahlung und eine Restzahlung. Ein
Vertrag, der monatlich weiterläuft, hat nichts davon — er hat einen Monatspreis,
eine Mindestlaufzeit und ein Datum, an dem er endet. Deshalb eine eigene Tabelle
und keine weitere Spalte an `orders`: Wer beides hat, hat **zwei Verträge, zwei
Laufzeiten, zwei Kündigungen** — genau wie gewünscht. `invoices.abo_id` sagt,
zu welchem Vertrag ein Beleg gehört; die Nummernreihe bleibt eine einzige, weil
lückenlos im Jahr so am sichersten ist.

### Die Kündigung, die sich selbst prüft

`Abo::kuendigungsvorschau()` rechnet das Ende aus, ohne etwas zu ändern:
während der Mindestlaufzeit zu deren Ende (auf das Monatsende gerundet), danach
zum Ende des laufenden Monats.

**Der Kunde sieht dieses Datum, bevor er klickt.** Eine Kündigung, deren Wirkung
man erst hinterher erfährt, ist eine Zumutung — und der Grund für genau die
Rückfrage, die man sich sparen wollte. Auf seiner Seite steht: *„Wenn du jetzt
kündigst, läuft die Betreuung noch bis zum 30.09.2027 — bis dahin zahlst du,
danach nicht mehr."*

Nach dem Klick: Status und Enddatum gesetzt, **Bestätigung automatisch raus** in
seiner Sprache, Meldung an Uwe. Die Bestätigung nennt das Datum, sagt, dass die
Website online bleibt und ihm gehört, dass Aktualisierungen aufhören, und dass
er auf Wunsch alle Zugänge und eine Sicherung bekommt. Zweimal kündigen ändert
nichts und wirft keinen Fehler — der Kunde hat nur nicht gesehen, dass es schon
erledigt ist.

Der Cronjob setzt abgelaufene Verträge täglich auf „beendet". **Das ist die
Stelle, an der später der Zahlungsanbieter abbestellt wird** — deshalb steht sie
jetzt schon da.

Kündigen kann auch Uwe, aus der Kundenakte, mit demselben ausgerechneten Datum
und derselben Bestätigung.

### Der Ordner fürs Finanzamt

Ein Jahr, ein Klick, eine Datei: `belege/` mit jedem Beleg als PDF,
`verzeichnis.csv` mit einer Zeile je Beleg (Nummer, Datum, Kunde, Bezug, Netto,
Steuersatz, Steuer, Brutto, Status, Zahldatum, Art) und `uebersicht.txt` mit
Summen je Monat und fürs Jahr.

Zwei Dinge, die die Übersicht **prüft statt nur zu addieren**:

- **Lücken in der Nummernreihe.** Eine italienische Belegnummerierung muss im
  Jahr lückenlos sein. Fällt eine Nummer aus, will man das hier sehen und nicht
  beim Steuerberater. Die Seite zeigt es rot.
- **Entwürfe ohne Nummer** stehen ausdrücklich *nicht* im Paket, und die
  Übersicht sagt, wie viele es sind — sie sind keine Belege.

Ein Beleg, dessen PDF sich nicht bauen lässt, verhindert das Paket nicht, landet
aber in `FEHLENDE-BELEGE.txt`. Sonst fiele er niemandem auf.

Nicht drin: die elektronische Rechnung über das SdI. Das war nie Teil dieser
Anwendung und bleibt Sache des Commercialista.

**Beim Prüfen einen Fehler gefunden:** Die Lückenerkennung nahm die Breite der
Nummer aus dem größten Wert statt aus den führenden Nullen — zwischen `0004` und
`0011` meldete sie brav „BE-2026-05". Behoben; jetzt kommt die Breite aus den
echten Nummern.

Geprüft: Vertrag angelegt (Mindestlaufzeit korrekt auf den Tag), Kündigung durch
den Kunden über die echte Seite — Datum ausgerechnet, Bestätigungsmail im
Mailfänger mit Kennung im Betreff, Meldung an Uwe; zweite Kündigung ändert
nichts; Kündigung nach abgelaufener Mindestlaufzeit endet zum Monatsende;
abgelaufener Vertrag wird vom Cronlauf auf „beendet" gesetzt; ZIP über die
Verwaltung heruntergeladen (283 KB, 9 PDFs, Verzeichnis, Übersicht), CSV mit BOM
und Semikolon öffnet in Excel; alle Verwaltungsseiten ohne Fehler.

### Offen, für Stufe 2

- **Stripe-Webhook in Ordnung bringen** — das ist die Bedingung, nicht ein
  Detail. Solange er nicht stimmt, steht bei einem Vertrag „von Hand abrechnen",
  und das steht auch so in der Rückmeldung, wenn man einen anlegt.
- Abo bei Stripe anlegen, **nur Karte und SEPA-Lastschrift**, Überweisung wird
  gar nicht erst angeboten. Automatischer Stopp exakt zum Kündigungsdatum
  (`cancel_at`), Meldung bei fehlgeschlagener Abbuchung.
- Monatlicher Beleg je Abrechnung, mit `abo_id`, in derselben Nummernreihe.

## Die Betreuung steht jetzt auch auf der Website getrennt (02.09.2026)

Uwes Hinweis, und er hatte recht: Die Datenbank war getrennt, die Website nicht.
Die Betreuung hing weiter als Zusatzzeile an der Paketkarte — dann liest sie sich
wie ein Aufschlag, den man nicht abwählen kann.

**Drei Stellen waren betroffen, nicht eine:**

1. Die Karten auf der Startseite kommen aus der Datenbank und waren sauber. Die
   **eingebauten Ersatzkarten** im HTML und die **Übersetzungsdaten** trugen aber
   noch die alten, vermischten Listen. Wer die Seite mit blockiertem JavaScript
   öffnet — und jede Suchmaschine, die den Quelltext liest — sah die alte Fassung.
2. Die Monatszeile stand ohne Einordnung da. Jetzt: „+ 39 € Betreuung im Monat —
   freiwillig".
3. Es gab keinen Betreuungsblock.

**Neu: ein eigener Abschnitt unter den Preiskarten**, abgesetzt durch eine Linie —
sichtbar getrennt, ohne einen zweiten Preisbereich zu erfinden. Drei Karten mit
dem Monatspreis als Preis, die vollständige Leistungsliste, und darunter der Satz
zur Bestandsaufnahme für fremde Seiten. Gespeist aus **derselben Quelle** wie die
Paketkarten (`pakete-daten.php`), damit die Preise nicht an zwei Orten leben.

**Zwei Dinge dabei bewusst nicht gemacht:**

- Kein „Alle Details"-Knopf auf den Betreuungskarten. Es gibt keine Detailseite
  dafür, und ein Link ins Leere ist schlimmer als kein Link.
- **Das Abzeichen „Am häufigsten gewählt" wieder entfernt**, das ich der mittleren
  Betreuung zuerst gegeben hatte. Es hat noch nie jemand eine Betreuung gewählt —
  eine unverdiente Auszeichnung ist genau die Art Kleinigkeit, die Vertrauen
  kostet, wenn sie auffällt.

Die alte Preisnotiz wurde gekürzt: Was sie über Laufzeit und Freiwilligkeit sagte,
steht jetzt im Betreuungsblock. Zweimal dasselbe untereinander liest niemand.

Geprüft: drei Betreuungskarten in allen drei Sprachen, kein JS-Fehler, kein
waagerechter Überlauf bei 390 und 1400 Pixeln, die alten vermischten Listen
kommen im Quelltext nicht mehr vor.

## Die Steuerakte legt sich selbst an (02.09.2026)

Uwe: „Prüfe nun meine gesamte Seite einfach alles und recherchiere was das
Finanzamt alles braucht — entsprechend lege automatisch immer alles an für
Finanzamt zum download."

Vorher gab es ein ZIP auf Knopfdruck: Belege als PDF, ein Verzeichnis, eine
Übersicht. Das war die halbe Miete, und die falsche Hälfte.

### Was die Recherche ergeben hat — und was daran wehtut

Drei Dinge waren neu und haben den Bau verändert:

**1. Es zählt der Zahlungseingang, nicht das Rechnungsdatum.** In Italien wird
beim Einzelunternehmer nach dem *principio di cassa* besteuert (Art. 1 comma 64
L. 190/2014). Eine Rechnung vom 20. Dezember, bezahlt am 15. Januar, gehört ins
Folgejahr. Das Verzeichnis sortierte nach Belegdatum — es zeigte also die
falsche Zahl. Schlimmer: Die Agenzia delle Entrate füllt das *Quadro LM*
inzwischen selbst vor und **unterstellt dabei, jede Rechnung sei am
Ausstellungstag bezahlt worden**. Bei jedem Zahlungsziel ist die Vorbefüllung
falsch, und widersprechen kann nur, wer die echten Eingänge belegen kann. Genau
das ist jetzt die Hauptliste: `einnahmen-nach-zahlung.csv`, dazu
`abgrenzung.csv` mit den drei Fällen, die über den Jahreswechsel fallen.

**2. Eingangsrechnungen sind Pflicht, auch im Forfettario.** Comma 59 nimmt den
Forfettario von fast allem aus — ausdrücklich *nicht* von der „numerazione e
conservazione delle fatture di acquisto". Kosten werden nicht abgezogen,
aufbewahrt und nummeriert werden müssen sie trotzdem.

**3. Reverse Charge ist die Falle, die niemand kommen sieht.** Stripe (Irland),
Google, Hosting im Ausland: Auf solche Leistungen fällt für den Forfettario
italienische IVA an, die tatsächlich zu zahlen und **nicht** abziehbar ist. Wer
das nicht mitschreibt, erfährt es im März vom Commercialista. Deshalb ist das
ein eigenes Feld und eine eigene Liste, keine Notiz.

Und der Punkt, der ehrlich gesagt werden musste: **Ein selbstgebautes ZIP ist
keine *conservazione a norma*.** Die verlangt Zeitstempel und Signatur auf dem
Archivpaket, einen Index nach UNI 11386, Pflichtmetadaten, einen benannten
Verantwortlichen und ein Handbuch (DM 17.06.2014, Linee Guida AgID). Die
Agenzia formuliert es selbst: das ist nicht „einfach auf dem Rechner speichern".
Der richtige Weg ist ihr **kostenloser Dienst im Portal „Fatture e
Corrispettivi"** — er muss aber aktiv eingeschaltet werden, sonst passiert
nichts, und der Opt-in „Consultazione e acquisizione" ist noch einmal ein
eigener Klick. Das steht jetzt sowohl auf der Seite als auch in der `LIESMICH.txt`
im Paket, weil es die Frage ist, die man sich sonst falsch beantwortet.

### Neu gebaut

- **`app/src/Ausgabe.php` + Migration `021`, Tabelle `ausgaben`.** Eigene
  Nummernreihe `EA-Jahr-lfd` (das ist die Nummerierung nach comma 59), Datum und
  Zahlungsdatum getrennt, Land, Reverse-Charge-Merkmal, Beleg als Datei in
  `app/eingang/` hinter einer `.htaccess`-Sperre. Die Betragsfelder verstehen
  `12,50`, `12.50` und `1.234,56` — wer tippt, denkt nicht an Trennzeichen; fehlt
  eins der drei Felder Netto/Steuer/Brutto, wird es ausgerechnet.
- **`payments.gebuehr_cents`.** Stripe zahlt netto aus. Vereinnahmt sind trotzdem
  die vollen 499 €, die Gebühr ist eine Ausgabe. Wer den Kontoeingang bucht,
  weist zu wenig aus — deshalb steht die Differenz daneben und wird nicht
  abgezogen.
- **`Steuerakte::taeglich()` im Cronlauf.** Baut jede Nacht das laufende Jahr und
  das Vorjahr neu, in `app/steuerakte/`. Erst in eine Nebendatei, dann umbenennen
  — bricht der Lauf ab, steht das vollständige Paket von gestern da und keine
  halbe Datei. Ältere Jahre bleiben liegen, wie sie waren.
- **Grenzwertwächter.** 68.000 / 85.000 / 100.000 €, gerechnet nach
  Zahlungseingang. Über 85.000 endet das Regime zum Folgejahr, über 100.000
  sofort — das ist nichts, was man im Nachhinein erfahren will.
- **Fristen auf der Seite:** Marca da bollo je Quartal, Archivierungsfrist,
  Termin der Steuererklärung. Jede mit dem Satz, warum sie dasteht.

Das Paket enthält jetzt: `belege/`, `eingang/`, `verzeichnis.csv`,
`einnahmen-nach-zahlung.csv`, `abgrenzung.csv`, `ausgaben.csv`,
`reverse-charge.csv`, `uebersicht.txt`, `pruefsummen.txt` (SHA-256 je Datei),
`LIESMICH.txt` und — wenn etwas fehlt — `FEHLENDE-BELEGE.txt`. Ein Eingangsbeleg
ohne hinterlegte Datei steht dort namentlich; sonst sucht im März jemand nach
einer Rechnung, die es nie als Datei gab.

**Der Cronlauf baut das Paket ganz zuletzt**, nach der Sicherung. Es dauert am
längsten und wird mit jedem Jahr länger — steigt der Server mittendrin aus, gilt
der Tag als erledigt, und die Sicherung wäre still ausgefallen.

### Kann das direkt ans Finanzamt gehen?

Nein, und zwar nicht aus Bequemlichkeit: **Es gibt keinen Kanal dafür.** Belege
werden aufbewahrt und auf Anforderung vorgelegt. Nur Rechnungen haben einen
eigenen Weg, das SdI — das setzt eine Partita IVA voraus und läuft über das
Portal, eine PEC oder den Commercialista. Eine offene Schnittstelle für Dritte
gibt es nicht; der Webservice des SdI ist ein Akkreditierungsverfahren für
Anbieter mit sehr hohem Volumen. Steht in der Übersicht auf der Seite, damit die
Frage nicht wiederkommt.

## Kunde von Hand angelegt: Link und freier Preis (02.09.2026)

Zweite Frage von Uwe, und sie deckte zwei echte Lücken auf.

**Der Link.** Er existierte für jeden Kunden, auch für den handangelegten — nur
musste man ihn kopieren und selbst eine Mail schreiben. Jetzt steht neben
„Ansehen" ein **„Link schicken"**: ein Klick, und unten im Schreibfeld steht der
fertige Brief mit dem Link darin, in der Sprache des Kunden. Dafür gibt es die
**38. Vorlage `zugang`** („Deine eigene Seite bei mir") und in `nachrichtfeld.php`
eine Vorauswahl über `?vorlage=…` — beim Laden feuert kein `change`, also setzt
das Skript den Text einmal selbst ein.

**Der Preis.** `bestellungAnlegen()` nahm ausschließlich den Paketpreis. Wer am
Telefon 750 € statt 899 € zugesagt hatte, konnte das nirgends eintragen — und
dann stimmten Bestellung, Zahlungen und Beleg von Anfang an nicht. Jetzt gibt es
im Bestellformular einen zugeklappten Bereich **„Preis abweichend vereinbart"**:
Gesamtpreis, Anzahlung in Prozent, abweichende Bezeichnung. Leer heißt weiterhin
Paketpreis. Geprüft: 750 € bei 30 % ergibt 225 € und 525 €.

Dasselbe für die Betreuung — `Abo::anlegen()` konnte einen eigenen Monatsbetrag
schon, es fehlte nur das Feld in der Kundenakte.

**Nebenbei aufgefallen:** Im Bestellformular standen die drei Betreuungspakete
als Nullzeilen mit — eine Bestellung darüber wäre eine Bestellung über null Euro
gewesen. Sie sind jetzt raus; Betreuung wird in der Kundenakte als Vertrag
gebucht, nicht als Bestellung.

## Die Verwaltung lief auf dem Telefon aus dem Rand (02.09.2026)

Beim Durchmessen aller 23 Verwaltungsseiten bei 390 Pixeln: **drei Seiten
schoben die ganze Seite nach rechts.** Man wischte, und die Kopfzeile wanderte
mit — es sah aus, als wäre die Verwaltung kaputt.

Drei verschiedene Ursachen, und die zweite war die eigentliche:

1. Tabellen ohne scrollenden Rahmen (`rechnungen`, und meine neue
   `ausgaben`). Auf dem Telefon scrollen Tabellen in `.block` jetzt in sich selbst.
2. **Eine Rasterspalte ist von sich aus so breit wie ihr breitester Inhalt** —
   auch wenn dieser Inhalt selbst scrollen könnte. Deshalb schob eine Tabelle die
   Seite auseinander, obwohl sie längst in einem scrollenden Rahmen saß. Ein
   `min-width:0` auf die Spalten, und es war weg. Das erklärt auch, warum die
   Rahmen bisher nur manchmal geholfen haben.
3. Ein Formular in den Einstellungen mit festen Feldbreiten und ohne Umbruch.

Danach: **23 von 23 Seiten ohne waagerechten Überlauf**, bei 390 und bei 1280
Pixeln, keine Konsolenfehler. Am Rechner sieht alles aus wie vorher.

## Ein Bereich für Projekte, die in kein Paket passen (02.09.2026)

Uwe wollte einen eigenen Bereich für individuelle Vorhaben — Preis nach Aufwand
und Vorstellung, besprochen statt ausgeschildert. Auf die Frage, wie der Preis
dort auftreten soll, hat er sich für **kein Preis, nur Gespräch** entschieden.

**Die eine Gestaltungsentscheidung, auf die es ankam:** Es ist ausdrücklich
*keine vierte Preiskarte*. Neben 499, 899 und 1499 € liest sich eine Karte ohne
Zahl als die teuerste — die Leerstelle wird gefüllt, und zwar mit dem
schlimmsten Wert, den der Leser sich vorstellen kann. In eigener Form, als
breites zweigeteiltes Feld unter den Paketen, steht der Bereich nicht im
Vergleich, sondern daneben: ein anderer Weg, kein teureres Paket.

Links die Begründung und der Knopf, rechts sechs typische Fälle — die
beantworten „bin ich damit gemeint?" schneller als jeder Fließtext. Der Satz,
der die Preisfrage trägt: *„Am Ende weißt du, was es kostet und warum — auch
wenn du dann nein sagst."*

Die Anfrage reist über `?paket=individuell` mit; dafür sucht `qform.js` jetzt
nach dem Merkmal `data-paket` statt nach der Preiskarte, weil dieser Bereich
bewusst keinen Preis hat.

## Der Ablauf klappt auf (02.09.2026)

Vier ausgeschriebene Absätze untereinander sind viel Text an einer Stelle, an
der der Leser nur wissen will, wie es läuft. Jetzt bleiben Nummer, Titel und
Zeitangabe sichtbar, der Rest kommt auf Klick.

Gebaut mit `<details>` — Tastatur, Vorleseprogramm und die Suchfunktion des
Browsers können das von Haus aus, ohne eine Zeile JavaScript. Der **erste
Schritt steht offen**, sonst sähe der Abschnitt wie eine leere Liste aus. Das
Zeichen ist ein Plus, das sich zum Minus dreht, kein Pfeil: ein Pfeil verspricht
einen Sprung woandershin. Weiches Auf- und Zuklappen über `::details-content`,
wo der Browser es kann — wo nicht, springt es auf, und das ist kein Fehler.

## Das Foto lebt (02.09.2026)

Ein Portrait neben drei Absätzen Text ist die statischste Stelle der Seite — und
ausgerechnet die, an der jemand entscheidet, ob er es mit einem Menschen zu tun
hat. Drei Bewegungen, alle so klein, dass man sie nicht als Animation wahrnimmt:
ein Atmen über 24 Sekunden (`scale`), ein Mitlaufen beim Scrollen (`translate`
über `animation-timeline: view()`), und alle 14 Sekunden ein schräger
Lichtstreifen. Schräg, weil senkrecht nach Scanner aussieht und waagerecht nach
Ladebalken.

Möglich ist das ohne verschachtelte Hüllen, weil `scale` und `translate` in CSS
eigene Eigenschaften sind — zwei Animationen können sie gleichzeitig steuern,
ohne sich zu überschreiben. Bei `prefers-reduced-motion` steht alles still.

## Das neue Erklärvideo: der ganze Ablauf, in drei Sprachen (02.09.2026)

Der Rundgang „Die Seite in Bewegung" ist raus. An seiner Stelle steht **ein**
Video von 1:31, das den kompletten Weg des Kunden zeigt: erste Nachricht,
Bestätigungsmail, eigene Seite, Fragebogen, Angebot und Anzahlung, Bau,
Entwurf und Freigabe, online, Belege, Betreuung.

**Zur Frage, womit es gemacht wird.** Uwe wollte es über kie.ai und „so wie es
tatsächlich aus Kundensicht aussieht, mit allen E-Mails, Seiten, jedem Klick".
Das zweite schließt das erste aus: Ein Videogenerator erfindet Oberflächen, er
kann seine Seite nicht kennen. Was im Video zu sehen ist, sind deshalb
**echte Aufnahmen der laufenden Kundenseite** (`video/quellen/<sprache>/`),
gesetzt in eine Fensterattrappe, dazu Titelkarten in der Schrift und den Farben
der Website. Kein Stockmaterial, keine erfundenen Bildschirme.

**Drei Sprachfassungen, und zwar vollständig.** Nicht nur der Text ist
italienisch, deutsch oder englisch — auch die abgebildeten Bildschirme. Eine
italienische Fassung mit deutschen Screenshots wäre genau die Art
Nachlässigkeit, die ein Kunde bemerkt. Jede Sprachseite bindet ihr eigenes
Video und ihr eigenes Vorschaubild ein.

**Werkzeuge:** `explainer-ablauf.html` ist die Bühne (elf Szenen, Sprache über
`?lang=`), `tools/record-ablauf.mjs` filmt sie ab, ffmpeg macht das mp4. Texte
ändert man in `assets/js/i18n-data.js` unter `abl`, nicht im HTML.

**Zum Ton:** Uwe wollte eine KI-Stimme. Das Guthaben beim einzigen dafür
verbundenen Dienst liegt bei 0,15 Einheiten — das reicht für keine einzige
Aufnahme, geschweige denn drei. Das Video ist deshalb stumm, wie die bisherigen
auch; auf dem Handy, wo die meisten es sehen, läuft es ohnehin ohne Ton. Der
Sprechertext steht fertig in den Sprachdaten und lässt sich jederzeit
darunterlegen.

## Sprachprüfung: was dabei herauskam (02.09.2026)

Vor der Aufnahme haben je ein italienischer und ein englischer Muttersprachler
die neuen Texte gelesen. Ergebnis: **55 Korrekturen**, fast alle Germanismen.

Die lehrreichsten:

- *„un gestionale che ti toglie lavoro dalle mani"* — die wörtliche Übersetzung
  von „jemandem Arbeit abnehmen". Gibt es im Italienischen nicht.
- *„niente indovinelli"* für „kein Rätselraten" — `indovinelli` sind
  Scherzrätsel für Kinder.
- *„Prezzo fisso, scritto"* — im Geschäftskontext heißt es `per iscritto`.
- *„An admin that takes work off your hands"* — im Englischen ist ein `admin`
  eine Person, kein System.
- *„Bookings that genuinely calculate"* — inhaltsleer; `really do the maths`.
- *„Care, if you want it."* — `care` trägt allein im britischen Englisch keinen
  Wartungsvertrag. Als Produktname bleibt es (er heißt auf der ganzen Seite so),
  die alleinstehende Überschrift heißt jetzt `Ongoing care`.

Dabei fielen zwei echte Fehler in den **bestehenden** Kundentexten auf:
`„Tienila d'occhio noi."` (grammatikalisch kaputt) und `„Two sentences is
enough."`

**Und der eigentliche Fund:** Die Kundenseite sprach auf Italienisch und
Englisch noch im Firmen-Wir („Ci pensiamo noi", „We're building your site"),
während die Website längst „ich" sagt. Im Video hätte „Io costruisco" direkt
neben „Ci pensiamo noi" gestanden. Beide Sprachen sind jetzt nachgezogen — 32
italienische und 31 englische Stellen, jede einzeln umformuliert statt ersetzt.
Wo „wir" wirklich Uwe **und** den Kunden meint („quando siamo d'accordo",
„once we agree"), steht es weiter.

## Zwei Fehlersuchen: eine echte, eine eingebildete (02.09.2026)

**Der eingebildete.** Im deutschen Quelltext stand *„Mehrsprachig, von Ihnen
pflegbar"* — Siezen auf einer Seite, die durchgehend duzt. Ich hielt das für
einen Fehler, der monatelang live stand, baute ein Werkzeug dagegen und schrieb
es hier als Fund auf. Dann habe ich die laufende Seite abgefragt: dort stand
längst *„von dir pflegbar"*.

Der Grund: **`build.mjs` erzeugt alle drei Sprachseiten beim Deploy neu**, aus
`index.html` als Quelle plus den Sprachdaten. Was im Repository an eingebauten
Texten steht, ist ein Zwischenstand, der beim nächsten Deploy überschrieben
wird — Besucher und Suchmaschinen bekommen immer die Fassung aus den
Sprachdaten. Das Werkzeug ist wieder raus; ein zweites, das dasselbe anders
macht, hätte nur dazu verleitet, erzeugte Dateien von Hand zu „reparieren".

Merksatz für das nächste Mal: erst die laufende Seite fragen, dann den Befund
aufschreiben.

**Der echte — und der stand wirklich live.** `build.mjs` benannte das Video je
Sprache um, kannte aber nur die alten Dateinamen (`erklaervideo-`, `rundgang-`).
Das neue `ablauf-de.mp4` fiel durch das Raster: `/de/` und `/en/` bekamen das
**italienische** Video ausgeliefert. Auf dem Prüfstand war davon nichts zu
sehen, weil dort die drei Sprachdateien einzeln und richtig danebenliegen —
erst der Build baut sie auseinander. Aufgefallen ist es nur, weil ich nach dem
Deploy die echte Seite abgefragt habe statt die lokale.

Die Regel ist jetzt allgemein, und darunter steht eine **Prüfung, die den Build
abbricht**: Zeigt eine Sprachseite auf eine Datei einer anderen Sprache, auf die
falsche Ebene oder auf eine, die es nicht gibt, wird gar nichts hochgeladen.
Gegenprobe mit einem absichtlich falschen Pfad: bricht ab, wie gewollt.

**Ebenfalls behoben:** Im Videobereich fehlte anfangs das `../`. Aufgefallen
erst beim Prüfen von `naturalWidth` — die Anfrage schlug wegen `loading="lazy"`
erst nach dem Netzwerk-Leerlauf fehl und tauchte in der ersten Kontrolle gar
nicht auf. Bei Bildern also nicht das Attribut prüfen, sondern ob Pixel
angekommen sind.

## Das Video sitzt jetzt da, wo die Frage gestellt wird (02.09.2026)

Es stand als eigener Abschnitt zwischen „Über mich" und den Prinzipien —
mitten in der Strecke, in der sich der Leser ein Bild von der Person macht,
und weit weg von der Frage, die es beantwortet.

Jetzt schließt es den **Ablauf** ab: oben die vier Schritte in je einem Satz,
darunter dieselbe Sache in Bewegung. Das spart einen Abschnitt und einen
Navigationspunkt — die Seite wirkt weniger voll, ohne dass etwas fehlt. Die
Sprungmarke `#video` bleibt bestehen, damit Verweise aus alten E-Mails und
Suchergebnissen weiter dort landen, wo sie sollen.

Zwei Kleinigkeiten, die beim Ansehen auffielen: Der Abspielknopf lag genau auf
der Überschrift des Vorschaubilds — das Vorschaubild ist die Titelkarte des
Videos, und die trägt oben Logo und Titel. Er sitzt jetzt tief, wo die Fläche
frei ist. Und der Titel stand zweimal: einmal auf der Titelkarte, drei
Zentimeter darunter noch einmal als Bildunterschrift. Die Wiederholung ist
raus, geblieben ist die Zeile, die etwas sagt: „Der ganze Ablauf · 1:31 ·
ohne Ton".

## „Klingt wie eine Prototyp-Webseite" (02.09.2026)

Uwes Kritik, ausgelöst an einem einzigen Satz: *„Ein Video, anderthalb
Minuten: … Ohne Ton, du kannst es überall ansehen."* Der Satz beschreibt den
Abspieler statt die Sache — und daran hing mehr.

Ein Werbetexter hat die ganze deutsche Seite gelesen. Sein Befund in einem
Satz: **Der Ton ist gut, die Bindeglieder sind es nicht.** Genau dort, wo Uwe
aufhört zu sprechen und anfängt, die Seite zu beschriften, klingt es nach
Baukasten. Und das sind ausgerechnet die meistgesehenen Stellen.

**Was geändert wurde — und warum:**

- **„Webdesign-Agentur"** in der allerersten Zeile und im Abbinder, dazu
  „Gestaltet und entwickelt im eigenen Haus". Er ist einer. Das ganze
  Verkaufsargument der Seite lautet „kein Vertrieb, keine Weitergabe,
  dieselbe Person" — und Zeile eins sagte „Agentur". Jetzt: „Webdesign aus
  Sizilien" und „Gestaltet und gebaut in Aragona. Von mir." Der Suchbegriff
  bleibt in Title und Beschreibung, wo er hingehört.
- **Achtmal „kostenlos und unverbindlich".** Wer achtmal beteuert, dass es
  nichts kostet, klingt, als rechne er damit, dass man ihm nicht glaubt. Jetzt
  steht es an zwei bis drei Stellen — dort, wo die Frage tatsächlich gestellt
  wird.
- **„Kostenloses Angebot anfordern"** auf dem wichtigsten Knopf. „Anfordern"
  ist Behördensprache und bricht das Du der ganzen Seite. Jetzt „Angebot
  holen".
- **„dein Vertriebsmitarbeiter, der nachts arbeitet"** — der abgegriffenste
  Satz der Branche, und `inter.a` sagt dasselbe schon in gut („Sie ist das
  erste Verkaufsgespräch — und es findet ohne dich statt"). Von zwei Bildern
  für einen Gedanken musste das schwächere weg.
- **„Individuelles Premium-Design", „Professionelle Farb- und
  Schriftgestaltung"** — die drei Wörter, die jeder Wettbewerber in dieselbe
  Preisbox schreibt, genau in der Zeile, in der jemand 899 € gegen 1499 €
  abwägt. Jetzt steht dort, was tatsächlich geliefert wird.
- **„Die Aufnahmen zeigen die tatsächlich laufenden Seiten"** stellte die
  Frage erst, die es beantwortet. Jetzt: „Alle diese Seiten sind online. Die
  Links führen direkt hin." Ein Link beweist es, ohne sie zu stellen.
- **Der Formularhinweis** erklärte den Knopf. Jetzt sagt er, was der Vorteil
  ist: „Kein Formular, keine gespeicherten Daten."

**Was ausdrücklich nicht angefasst wurde:** `about.p1` („Ich kenne den Moment,
in dem eine Seite online ist und trotzdem niemand anruft"), `inter.a`/`inter.b`,
`faq.a5` („wer dir Platz eins in einem Monat verspricht, verkauft dir Luft"),
der Satz „auch wenn ich daran weniger verdiene" und der ganze `masz.`-Block.
Das sind die Stellen, die die Seite tragen — sie waren nie das Problem.

### Italienisch und Englisch: dasselbe, plus das Firmen-Wir

Beide Sprachen sagten auf den Verkaufsseiten noch „noi" und „we" — nachdem die
Kundenseite schon umgestellt war. Das ist derselbe Widerspruch wie dort, nur an
der sichtbareren Stelle. Beide Fassungen sind von Muttersprachlern überarbeitet
worden: **68 Änderungen**, Personalform und Redaktion in einem Zug.

**Dabei kam ein echter Sachfehler heraus**, der live stand: Die italienische und
die englische FAQ verkauften die Betreuung noch als Pflichtaufschlag — *„499 €
una tantum più 39 € al mese"*, *„€499 one-off plus €39 per month"*. Das
widerspricht der Trennung von Erstellung und Betreuung, die im Deutschen und in
der Verwaltung längst gilt, und es widersprach der eigenen Antwort zwei Fragen
weiter unten. Beide Sprachen sagen jetzt dasselbe wie die deutsche.

## Der Ton für das Video steht bereit (02.09.2026)

Uwe will eine gesprochene Fassung über kie.ai. Zwei Dinge dazu, beide
unverhandelbar auf der einen und beide gelöst auf der anderen Seite:

**Ich arbeite nicht mit seinem Zugangsschlüssel.** Nicht aus Förmlichkeit —
Schlüssel eintragen und sich damit in fremdem Namen anmelden ist etwas, das ich
grundsätzlich nicht tue, auch auf ausdrückliche Erlaubnis hin. Der Schlüssel,
den er in den Chat gestellt hat, gehört getauscht.

**Deshalb läuft es umgekehrt:** Er führt einen Befehl aus, der Schlüssel bleibt
bei ihm.

    export KIE_API_KEY="…"
    bash tools/ton-kie.sh de     # dann it, dann en
    node tools/ton-einbauen.mjs de

`tools/ton-kie.sh` fragt **immer zuerst das Guthaben ab** (wie gewünscht),
rechnet vor, was ansteht, und hält nach der ersten Szene an, um den
tatsächlichen Verbrauch zu zeigen, bevor der Rest läuft — kie.ai nennt die
Kosten vorher nicht, erst hinterher je Aufgabe im Feld `creditsConsumed`.
Modell ist `elevenlabs/text-to-speech-turbo-2-5`, weil nur Turbo v2.5 eine
Sprache erzwingen kann; ohne das spricht ein mehrsprachiges Modell den
deutschen Text gern mit englischem Einschlag.

Der Sprechertext wird **nicht eigens geschrieben**, sondern von
`tools/sprechertext.mjs` aus genau den Sätzen erzeugt, die im Video stehen.
Zwei Gründe: Die Sätze sind von Muttersprachlern geprüft, und ein zweiter
Sprechertext wäre ein zweiter Ort, an dem dieselbe Aussage veralten kann.

**Und das Wichtigste am Ablauf:** `tools/ton-einbauen.mjs` schiebt den Ton nicht
unter ein fertiges Bild. Es misst jede Aufnahme, leitet daraus die Standzeit
jeder Szene ab, **nimmt das Video mit diesen Zeiten neu auf** und legt den Ton
darunter. So endet jede Szene, wenn der Satz zu Ende ist — und nicht zwei
Sekunden davor. Der Recorder nimmt die Zeiten als vierten Aufrufparameter
entgegen; ohne ihn bleibt es bei den festen Standzeiten.

Geprüft mit erzeugten Testaufnahmen: elf Stück, Längen 3,4 bis 7,4 Sekunden,
daraus 85,1 Sekunden Video und eine Tonspur von 83,4 Sekunden — die Differenz
ist der Nachlauf der letzten Szene, also richtig.

## Das Video hat jetzt eine Stimme (02.09.2026)

Uwe hat bei kie.ai aufgeladen, den Schlüssel selbst in eine Datei auf seinem
Schreibtisch gelegt (`kie-schluessel.txt`, außerhalb des Repositorys) und die
Aufnahme lief über die Schnittstelle auf seinem Mac — der Schlüssel ist nie
durch den Chat gegangen und steht nirgends im Code.

**Modell:** `elevenlabs/text-to-speech-turbo-2-5`, Stimme „Benjamin"
(`LruHrtVF6PSyGItzMNHS`), `speed 0.96`, `language_code` je Sprache. Turbo, weil
nur dieses Modell eine Sprache erzwingen kann; ohne das spricht ein
mehrsprachiges Modell den deutschen Text mit englischem Einschlag.

**Was dabei zu lernen war — und was es gekostet hat, es zu lernen:**

Die Schnittstelle verträgt keine parallelen Aufträge. 32 Aufträge auf einmal:
4 gelungen, 28 mit „Internal Error". Acht auf einmal mit zwei Sekunden Abstand:
1 von 8. Vier auf einmal: 0 von 4. **Einer nach dem anderen, jeder abgewartet,
bevor der nächste losgeht: fast alles beim ersten oder zweiten Versuch.** Es
lag nicht am Text — dieselbe Szene, die fünfmal ausfiel, lief einzeln durch.

Der zweite Fehler war meiner: Mein Wartefenster war mit 24 Sekunden zu kurz,
also habe ich Aufträge als gescheitert verworfen, die noch liefen. Mit 44
Sekunden Fenster stieg die Ausbeute sofort.

**Fehlversuche kosten nichts** — nachgerechnet am Guthaben: 240 Credits für 40
gelungene Aufnahmen, also 6 je Stück, unabhängig von der Textlänge. Eine
Aufnahme über 30 Zeichen kostet genauso viel wie eine über 211.

**Die Folge fürs Video:** Gesprochen dauert der Ablauf deutlich länger als
gelesen. Aus 1:31 werden **2:54 (de), 2:48 (it), 2:33 (en)**. Die Standzeiten
kommen jetzt aus den Aufnahmen: `tools/ton-einbauen.mjs` misst jede Datei,
rechnet Vorlauf und Nachlauf dazu, nimmt das Video mit diesen Zeiten neu auf
und legt die Tonspur darunter. Jede Szene endet also, wenn der Satz zu Ende ist.

**Und eine Zeile, die mit dem Ton nötig wurde:** `assets/js/vids.js` startete
das Video über das `autoplay`-Attribut. Solange es stumm war, ging das überall.
Mit Tonspur lässt Safari das nicht mehr zu — Ton nur aus einer echten
Nutzeraktion heraus. Der Klick auf den Knopf ist eine, also wird jetzt dort
`play()` gerufen. Weigert sich ein Browser trotzdem, läuft es stumm weiter
statt gar nicht.

## Warum die neuen Videos nicht ankamen (02.09.2026)

Uwe: „Videos sind nicht auf der Seite aktualisiert." Auf dem Server lagen sie
längst — 5,4 MB mit Tonspur, geprüft. Im Browser lag die alte, stumme Fassung.

**Der Grund:** Der Server liefert `cache-control: public, max-age=2592000` —
dreißig Tage. Solange die Adresse gleich bleibt, holt kein wiederkehrender
Besucher die Datei neu. `build.mjs` hängt zwar einen Fingerabdruck an, aber
nur an **CSS und JavaScript**. Video und Vorschaubild bekamen keinen.

Das war kein Ausrutscher an einer Datei, sondern eine Lücke im System: Jedes
Bild, das er künftig austauscht — ein neues Projektfoto, ein anderes Portrait
— wäre für wiederkehrende Besucher einen Monat lang unsichtbar geblieben, und
niemand hätte den Zusammenhang erraten.

Der Fingerabdruck steht jetzt an **allem, was sich ändern kann**: css, js, img,
video, und auch am Attribut `data-src`, über das das Video geladen wird. In der
deutschen Fassung sind das 35 Verweise. Ändert sich eine Datei, ändert sich
ihre Adresse, und der Browser holt sie.

Zwei Dinge dabei, die leicht schiefgegangen wären:

- `stempel()` gab für eine fehlende Datei `'0'` zurück. Damit hätte eine
  vertippte Adresse ein munteres `?v=0` bekommen statt aufzufallen. Jetzt
  liefert sie einen leeren Wert, und der Verweis bleibt ungestempelt.
- Die Deploy-Prüfung, die falsche Sprachdateien abfängt, verlangte das
  Anführungszeichen direkt hinter der Dateiendung. Mit angehängtem `?v=` hätte
  sie **nichts mehr gefunden und stillschweigend „alles gut" gemeldet** — die
  gefährlichste Art, wie eine Prüfung kaputtgeht. Sie verträgt den Stempel
  jetzt; gegengeprüft mit einem absichtlich falschen Pfad: bricht ab.

## Wunschdomains und Domainprüfung (05.09.2026)

Der Fragebogen fragt bei „Domain haben wir nicht" nach **drei** Wunschadressen
in Rangfolge und prüft sie sofort, während der Kunde tippt. Mit einer einzigen
Zeile ging pro Kunde eine Woche mit Hin und Her drauf: Der naheliegende Name ist
fast immer vergeben.

**Geprüft wird über eine Leiter, weil keine Stufe allein reicht:**

1. RDAP direkt bei der Registrierungsstelle. Zuordnung aus der IANA-Liste
   (`data.iana.org/rdap/dns.json`), einmal geholt, eine Woche in
   `app/zwischenspeicher/` aufgehoben. Deckt `.com`, `.net`, `.org` und die
   allgemeinen Endungen ab — gemessen 120 ms.
2. Zusatzliste für Stellen, die es gibt, aber nicht in der IANA-Liste stehen:
   `de → rdap.denic.de` (gemessen, antwortet).
3. Whois auf Port 43 für `.it` — dort gibt es kein RDAP, `rdap.nic.it` löst
   nicht einmal auf. **Auf All-Inkl ist Port 43 offen, am 05.09.2026 auf der
   laufenden Seite geprüft.** Schlägt die Verbindung fehl, wird das gemerkt,
   damit nicht jede der drei Adressen in dieselbe Zeitgrenze läuft.
4. DNS zuletzt: Namensserver heißt vergeben. Der Umkehrschluss gilt nicht,
   also sagt diese Stufe nie „frei".

**Nicht über rdap.org.** Der Vermittler war zweimal untauglich: zehn Anfragen in
zehn Sekunden je IP (auf einem geteilten Server nicht unsere allein — nach acht
Abfragen kam nur noch „unklar"), und er antwortet selbst mit 404, wenn er für
eine Endung keinen Server kennt, was jede exotische Endung als frei melden würde.

**Die Regel dahinter:** Wo keine belastbare Auskunft zu holen ist, steht „kann
ich nicht sicher sagen" — nicht „frei". Ein falsches „frei" ist teurer als ein
ehrliches Achselzucken: Der Kunde freut sich, und man muss es ihm hinterher
wegnehmen.

Die Prüfung hängt am Fragebogen-Schlüssel (`domain-pruefung.php?t=…`), sonst
wäre sie eine offene Whois-Abfrage für jeden auf unsere Rechnung und unsere IP.

## Der Telefonassistent bekommt Augen und Ohren (06.09.2026)

Manuela (STRATO AI Voice Receptionist) konnte vier Dinge: nachschlagen, wer anruft; den
Konfigurator-Link schicken; ein Anliegen melden; eine Zusammenfassung senden. Sie kann jetzt
sieben. Die drei neuen haben denselben Grund: **ein Sprachmodell weiß Dinge nicht und sagt sie
trotzdem — überzeugend.**

**Preis.** Vorher hätte der Preis im Prompt gestanden, und beim nächsten Preisschritt hätte
Manuela drei Monate lang den alten genannt. Jetzt rechnet `preis_auskunft` mit demselben
`Baukasten::rechnen()`, das auch der Konfigurator benutzt, und holt Paket- und Betreuungspreis
aus der Datenbank. Immer eine Spanne, nie ein Festpreis. Sagt der Anrufer nichts, kommt die
Orientierung für den häufigsten Fall — mit der Angabe, worauf sie beruht.

Die Regel „keine Beträge am Telefon" musste dafür geschärft werden. Sie hieß: *keine Beträge,
nirgends*. Sie heißt jetzt: **keine Beträge zu einem Kunden** — kein offener Posten, keine
Rechnung, keine Restzahlung. Was öffentlich auf der Website steht, darf sie nennen, weil jeder
es ohne Anruf lesen kann. Die Trennlinie läuft nicht zwischen Zahl und keiner Zahl, sondern
zwischen öffentlich und persönlich. Eine Prüfung hält fest, dass eine mitgeschickte
Kundennummer an der Preisauskunft nichts ändert.

**Lage.** `lage` gibt Datum, Uhrzeit, Wochentag und Zeitzone vom Server, dazu den Modus, den
Uwe in der Verwaltung stellt: normal, Urlaub, ausgelastet. „Herr Vetter ruft Sie heute noch
zurück" während des Urlaubs ist ein Versprechen, das jemand anders bricht, und der Anrufer
merkt es erst, wenn niemand anruft. Im Urlaub und bei „ausgelastet" gibt es deshalb keine
Tageszusage. Ein Modus, den es nicht gibt — auch als Altbestand in der Datenbank — fällt auf
„normal" zurück.

**Zeitfenster.** `melde` nimmt jetzt auf, *wann* jemand erreichbar ist, nicht nur *dass* er
einen Rückruf will. Freitext mit Absicht: „ab 14 Uhr", „nur vormittags", „nicht Dienstag" —
so antworten Menschen, und ein Uhrzeitfeld hätte die Hälfte davon verworfen. Fehlt das
Fenster, kommt `nachfragen: true` zurück und Manuela fragt genau einmal nach. Bei einer
Beschwerde nicht: da zählt, dass es rausgeht.

**Wissenslücken.** Was sie nicht beantworten konnte, landet über `wissensluecke` auf einer
Liste in der Verwaltung, statt im Nichts. Das ist die wertvollste Liste der Seite — jede Zeile
ist eine Frage, die ein echter Anrufer gestellt hat.

### Der Trichter, der nach unten breiter wurde

Der interessanteste Fehler des Tages stand in der ersten eigenen Messung. Die Seite zeigte:
2 Anrufe, 0 Links, 3 Bedarfe, 5 Anfragen, 18 Bestellungen — „167 %", „360 %". Darüber stand
der Satz „Von links nach rechts wird es weniger". Der Trichter hat das Gegenteil dessen
behauptet, was daneben stand.

Zwei Fehler steckten drin, und beide sind lehrreich:

1. **Hinten wurde alles gezählt, was auf der Website passiert ist.** Keine dieser
   Bestellungen gehörte dem Telefon. Die Zahl war groß, richtig — und wertlos, weil sie
   niemandem gehörte. Behoben durch eine Kette: Der Anruf legt einen Bedarf an und schreibt
   dessen Nummer in die Spur; der abgesendete Bedarf trägt die Anfrage, die Anfrage die
   Bestellung. Gezählt wird nur, was daran hängt.
2. **Die Stufen hatten verschiedene Einheiten.** Oben Gespräche, darunter verschickte Links —
   und weil Manuela in einem Gespräch zweimal einen Link schicken kann, standen da 1 Anruf und
   2 Links. Jetzt zählt **jede Stufe Gespräche**: von so vielen Anrufen ging ein Link raus,
   aus so vielen wurde ein ausgefüllter Bedarf, daraus eine Anfrage, daraus eine Bestellung.
   Jede Stufe ist eine Teilmenge der vorherigen — der Trichter kann gar nicht mehr wachsen.

Damit das so bleibt, steht die Rechnung als `Telefon::trichter()` in der Klasse und nicht im
Verteiler: **eine Zahl ohne Prüfung ist eine Behauptung.** Abschnitt 17 der Prüfkette hält
fest, dass fremde Bedarfe nicht mitzählen, dass ein abgesendeter Telefon-Bedarf nachrückt und
dass der Trichter nach unten nie breiter wird — auch dann noch, wenn jemand später eine Stufe
dazwischenschiebt und die Herkunft dabei vergisst.

Ein Gespräch ist dabei eine Minute: Nachfragen innerhalb derselben Minute gehören zum selben
Anruf. Eine Näherung — und sie steht als Näherung auf der Seite, nicht als Tatsache.

**Nachgemessen:** Prüfkette von 233 auf 259 Prüfungen, alle grün. Die drei neuen Aktionen
zusätzlich über HTTP gegen den laufenden Server gerufen, nicht nur als Methoden. Zwei
Nebenbefunde dabei: eine Prüfung schrieb die Zahl der Aktionen fest („genau vier") und wäre
bei jeder neuen Fähigkeit nachgezogen statt gelesen worden — sie prüft jetzt, was gelten muss
(jeder Name eindeutig und ein schlichtes Wort). Und die Verwaltungsseite sprach an drei
Stellen von „den vier Konfigurationen"; die Zahl kommt jetzt aus der Liste selbst.

**Offen und Uwes Aufgabe:** drei Testanrufe auf Italienisch, Deutsch und Englisch. Bis dahin
ist alles hier geprüft, aber nichts davon je von STRATO gerufen worden. Der Post-Call-Webhook
wartet auf denselben ersten Anruf — ohne ihn ist das Format nicht bekannt, und geraten wird
hier nichts.

## Manuela hilft weiter — und ein Fehler, den zwei Zeichen ausgelöst haben (06.09.2026)

### Der Fehler zuerst, weil er das meiste gelehrt hat

STRATO meldete beim Speichern: *„Bitte beheben Sie die Validierungsfehler in den markierten
Feldern."* Kein Feld war rot. Alle sechs Abschnitte trugen einen grünen Haken. Der Knopf zum
Speichern war gesperrt — der ganze Assistent ließ sich nicht mehr ändern.

Die Ursache stand nirgends auf der Seite. Sie kam erst heraus, als das Formular selbst gefragt
wurde (react-hook-form hält seine Fehler im Zustand):

```
tools.5.parameters.properties: Invalid input: expected record, received array
```

Werkzeug Nummer 5 ist `lage` — die einzige Aktion ohne Parameter. **PHP kennt keinen
Unterschied zwischen einer leeren Liste und einem leeren Objekt.** Beides ist `[]`, und
`json_encode` macht daraus `[]`. STRATO erwartet dort ein Objekt. Zwei Zeichen.

Drei Dinge daran sind es wert, aufgeschrieben zu werden:

1. **Der Fehler war unsichtbar, wo er entstand.** In der Verwaltung sah die Konfiguration
   richtig aus; erst die fremde Seite hat ihn bemerkt, Stunden später, bei jemandem ohne
   Zugriff auf den Code. Deshalb wird der Block jetzt in `Telefon::konfigJson()` gebaut und
   nicht mehr in der Ansicht — dort kann die Prüfkette ihn nachrechnen. Prüfung 18 hält fest,
   dass ein Parametersatz ohne Felder als `{}` herauskommt.
2. **Die Fehlermeldung log nicht, sie war nur an der falschen Stelle.** „Markierte Felder" gibt
   es nicht, wenn der Fehler in einem eingeklappten Bereich sitzt. Wer so etwas sucht, sollte
   früher aufhören, im Bild zu suchen, und den Zustand fragen.
3. **Nicht der offensichtliche Verdächtige war schuld.** Der Verdacht lag zuerst auf den langen
   Beschreibungstexten und den deutschen Anführungszeichen. Beide waren unschuldig.

### Der Schlüssel wurde dabei getauscht

Beim Nachsehen stand der Telefonschlüssel auf einem Bildschirmfoto — und auf der
Verwaltungsseite steht ausdrücklich, dass er weder in eine E-Mail noch in einen Chat gehört.
Also: neuer Schlüssel erzeugt, alle acht Konfigurationen bei STRATO damit neu gesetzt, ohne
dass der neue Schlüssel je gelesen wurde (kopiert wird über die Zwischenablage, und dort, wo
das nicht ging, wurde er innerhalb der STRATO-Seite von einer Integration zur anderen
übernommen). Der alte ist damit tot.

### Neu: die achte Aktion

`hilfe` — wenn jemand nicht weiterkommt: Fragebogen, Bezahlung, Link weg, Entwurf, Zugang.

Der Unterschied zu einer FAQ ist der ganze Punkt: Manuela erklärt nicht allgemein, wie ein
Fragebogen funktioniert. Sie sieht nach, wo **dieser** Kunde steht, und liest ab da vor. „Dein
Fragebogen ist schon da, du wartest auf uns" und „ich schick ihn dir nochmal" sind zwei
verschiedene Gespräche — und das falsche davon ärgert jemanden, der seine Arbeit schon gemacht
hat.

Der Stand kommt aus `Kundenzugang::seite()`, derselben Rechnung, aus der auch seine eigene
Seite gebaut wird. Damit kann das Telefon gar nicht etwas anderes sagen als der Bildschirm.
Zwei Quellen für denselben Stand laufen irgendwann auseinander, und dann steht Aussage gegen
Aussage.

**Drei Grenzen, geprüft, nicht nur aufgeschrieben:**

- **Kein Betrag — und kein Satz darüber, ob etwas offen ist.** Auch „du hast noch etwas offen"
  ist eine Auskunft über Geld, und am anderen Ende sitzt kein Ausweis, sondern eine Stimme. Wer
  Geld meint, bekommt den Link zu seiner Kundenseite; dort ist der Link der Ausweis, und dort
  steht ohnehin mehr, als Manuela sagen dürfte.
- **Alles geht an die hinterlegte Adresse.** Nie an eine, die am Telefon genannt wurde — sonst
  wäre „schick mir den Link an meine neue Adresse" die Übernahme eines Kundenkontos.
- **Die erste Fragebogen-Einladung macht Uwe.** Sie hängt in der Verwaltung an einer Rückfrage,
  weil danach eine Uhr läuft. Erneut schicken darf der Assistent — das wiederholt nur, was
  schon entschieden war.

Dazu zwei Dinge, die aus dem Bauen selbst kamen: Klappt ein Versand nicht (kein Mailschlüssel,
Brevo down, Adresse tot), wird **nichts zugesagt** — es geht sofort eine dringende Meldung
raus, und Manuela sagt genau das. Vorher hätte der Anrufer „kommt gleich" gehört und drei Tage
gewartet. Und nach zwei vergeblichen Anläufen übernimmt ohnehin ein Mensch; eine Schleife, die
dreimal dieselbe Anleitung vorliest, ist keine Hilfe, sondern eine Warteschleife mit Text.

**„Woran es hakt"** zählt die Hilfe-Anrufe nach Problem. Zwanzig Anrufe zum Fragebogen sind
kein Support-Fall, sondern ein Produktfehler — dann ist nicht der Assistent zu verbessern,
sondern der Fragebogen.

**Nachgemessen:** Prüfkette 259 → 294, alle grün. Bei STRATO stehen jetzt alle acht
Konfigurationen, gespeichert und nach dem Neuladen nachgesehen.

**Weiter offen:** der erste echte Anruf. Bis dahin ist alles hier geprüft, aber nichts davon je
von STRATO gerufen worden.

## „Heute anrufen" — und warum kein Roboter zurückruft (06.09.2026)

### Die Frage, die zuerst beantwortet werden musste

Uwe hat eine Nummer bei Sonetel und wollte wissen, ob Manuela damit ausgehend
telefonieren kann. Nachgesehen statt geraten:

- **Manuela kann es nicht.** STRATOs Assistent ist ausdrücklich nur für eingehende Anrufe
  gebaut („übernimmt eingehende Anrufe auf Ihrer Geschäftsnummer"). Die Plattform ist zu; es
  gibt keine Schnittstelle, über die man sie wählen ließe.
- **Die Nummer könnte es** — über einen SIP-Trunk an eine andere Agenten-Plattform
  (ElevenLabs Agents und Retell unterstützen ausgehende Anrufe ausdrücklich). Das wäre aber
  ein **zweiter, neu aufgebauter Assistent**. Die acht Aktionen liefen weiter, die sind
  schlichte HTTP-Aufrufe; Stimme, Prompt und Wissen wären neu.
- **Kosten**, falls es je dazu kommt: zwei Zähler gleichzeitig. Telefonie bei Sonetel —
  Italien Festnetz 0,009 €/Min, Italien Mobil **von einer lokalen Sonetel-Nummer**
  0,031 €/Min, **ohne** lokale Nummer bis 0,716 €/Min (der Unterschied ist der ganze Punkt).
  Dazu der Agent: Retell 0,07–0,31 $/Min, keine Grundgebühr, SIP-Trunking kostenlos. Ein
  Rückruf von drei Minuten liegt bei etwa 50 Cent. Synthflow — einmal der Plan — gibt es
  inzwischen nur noch als Enterprise ab 30.000 $/Jahr und ist damit raus.
- **Offen und bei Sonetel zu erfragen:** ob deren SIP-Trunk ausgehende Anrufe von einer
  fremden Plattform überhaupt zulässt. Steht auf keiner ihrer öffentlichen Seiten.

### Was stattdessen gebaut wurde

Vor der Anschaffung steht eine Zahl, die niemand kennt: **wie viele Rückrufe es überhaupt
gibt.** Bei fünf im Monat lohnt keine zweite Plattform, bei fünfzig schon. Also erst die
Zahl, dann die Entscheidung.

„Heute anrufen" steht jetzt ganz oben auf der Telefonseite: wer wartet, seit wann, unter
welcher Nummer (als `tel:`-Link, ein Griff), in welchem Zeitfenster, worum es geht.
Beschwerden oben, danach das Älteste — wer lange wartet, hat am ehesten schon aufgegeben.
Rot ab einem Tag. Der Block erscheint nur, wenn wirklich jemand wartet.

**Zwei Entscheidungen, die erklärt gehören:**

1. **Erledigt löscht nichts.** Aktivitäten sind ein Protokoll: Man schreibt hinein, man
   ändert sie nicht. Ein erledigter Rückruf bekommt eine Zeile, die auf ihn zeigt. Damit
   bleibt lesbar, wann der Wunsch kam und wann er erledigt wurde — und es braucht keine
   Wanderung an der Datenbank.
2. **Die Nummer steht als Zeichenkette in der Spur.** Mit einer blanken Zahl hätte die Suche
   nach `12` auch auf `123` gepasst, und ein erledigter Rückruf hätte fremde mit weggeräumt.
   Der Fehler wäre nie aufgefallen — er hätte nur gelegentlich jemanden verschwinden lassen.
   Prüfung 20 hält genau das fest.

Dazu meldet der Cronjob einmal am Tag, was länger als einen Tag liegt — als **eine** Meldung,
nicht als eine je Rückruf. Eine Liste, die man vergisst zu öffnen, ist keine Liste.

Nebenbei korrigiert: `melde()` schrieb bisher ein Ja/Nein („Nummer war dabei") in die Spur
statt der Nummer. Die Liste hätte Leute angezeigt, die man nicht anrufen kann.

### Was von Vorschlag 5 schon lief

Vor dem Bauen nachgesehen — und das meiste war da: Fragebogen-Erinnerung, erste
Zahlungserinnerung mit frischem Link, Betreuungsmonate, abgelaufene Angebote, Abnahme-Prüfung.
Neu ist nur die Rückruf-Mahnung. Ein zweiter Erinnerungsmechanismus daneben wäre eine
Doppelung gewesen, die irgendwann zwei verschiedene Dinge behauptet.

**Nachgemessen:** Prüfkette 302 → 325, alle grün.

## Der Fragebogen am Telefon (07.09.2026)

Achtundvierzig Felder in sechs Abschnitten sind im Browser achtundvierzig Kästen — und
deshalb bleiben sie liegen. „Fragebogen" ist der häufigste Grund, warum ein Projekt
stehenbleibt, und der häufigste Punkt auf der Liste, woran es hakt. Am Telefon ist es eine
Viertelstunde Reden.

Manuela kann ihn jetzt gemeinsam mit dem Kunden ausfüllen. Neue Aktion `fragebogen`
(`app/src/Telefonfragebogen.php`, Abschnitt 15 in `Telefon.php`), sechs Schritte: `start`,
`antwort`, `weiter`, `spaeter`, `pruefen`, `absenden`.

### Was dabei anders ist als im Formular

**Niemand liest elf Optionen vor.** Bei „Branche" stehen elf zur Auswahl. Sie fragt offen —
„was für ein Betrieb ist das?" — und `Telefonfragebogen::zuordnen()` ordnet die Antwort den
Optionstexten aller drei Sprachen zu. Wer auf Deutsch „Ristorante" sagt, meint dasselbe.
Passt nichts, kommen zwei Vorschläge; passt immer noch nichts, wird „anders" vermerkt —
mit seinem Wortlaut in der freien Zeile.

**Was in der Akte steht, wird bestätigt statt gefragt.** Firmenname, Ort, Rufnummer,
E-Mail, Ansprechpartner werden vorbelegt und in einem Satz zurückgelesen. Vier Fragen
weniger, und er merkt, dass er bekannt ist.

**Gespeichert wird nach jeder einzelnen Antwort.** Nicht am Ende. Wer nach zwanzig Fragen
auflegt, hat zwanzig Antworten im Fragebogen.

**Nach jedem Abschnitt ein Ausgang.** Stand ansagen, fragen, ob weitergemacht wird. Sechs
Abschnitte am Stück machen einsilbig, und einsilbige Antworten sind der Grund, warum ein
Briefing später nichts hergibt.

**Abgeschickt wird nur ausdrücklich.** `absenden` verlangt alle fünf Pflichtangaben *und*
`bestaetigt: true` — und davor den Durchgang: jeden Abschnitt vorlesen, fragen, ob etwas
korrigiert oder ergänzt werden soll. Das Abschicken rückt das Projekt weiter und verschickt
Post; das darf keinem Missverständnis passieren.

### Drei Riegel, die im Code stehen und nicht im Leitfaden

Dasselbe Muster wie bei `kunde_id`, „sonstiges" und der Merkliste: *Was verlässlich sein
muss, gehört ins Werkzeug.*

1. **Es geht immer vorwärts.** `naechstes()` sucht ab dem zuletzt behandelten Feld, nicht
   von vorn. Der erste Entwurf hätte eine offen gebliebene Frage sofort wiederholt — beim
   dritten Mal legt jeder auf.
2. **Zweimal unklar ist genug.** Gezählt wird über die Aktivitätsspur. Danach „anders" mit
   Wortlaut, oder — wo es keine Auffangoption gibt — die Frage bleibt offen und steht in der
   Durchsicht. Besser eine Lücke, die man sieht, als eine Antwort, die niemand gesagt hat.
3. **Was gespeichert wird, muss es geben.** Zugeordnet wird gegen die Auswahl, und
   `Onboarding::saeubern()` prüft es danach noch einmal. Zwei Netze, weil am anderen Ende
   ein Sprachmodell sitzt.

### Sie bietet ihn von selbst an

`kunde_nachschlagen` gibt bei offenem Fragebogen einen Block `fragebogen` zurück — Stand,
Restzeit und den Satz zum Vorlesen. Wer über seine Kundenseite anruft, wird gleich nach der
Begrüßung gefragt (er sitzt ohnehin vor dem Portal); wer anruft, bekommt zuerst sein
Anliegen erledigt. Sagt er nein, wird nicht noch einmal gefragt.

Am Telefon sagt niemand „8" — er sagt acht, otto, eight. `Telefonfragebogen::zahl()`
versteht beides; der erste Entwurf hätte eine Website mit null Seiten eingetragen.

Geprüft: 754 Kettenprüfungen, Abschnitt 41 mit 60 davon. Fünfzehn Werkzeuge bei STRATO.

## Die Assistentin steht jetzt auch auf der Website (07.09.2026)

Zwei Stellen, zwei Absichten:

**Startseite (it/de/en):** Neue Sektion `#assistente` zwischen Ablauf und „Über mich" —
sechs Karten, was Manuela wirklich kann: Preise sofort, Blick auf die bestehende Website,
Angebots-Link, echter Termin, Projektstand für Kunden, Fragebogen gemeinsam ausfüllen.
Jeder Punkt entspricht einem Werkzeug, das existiert (der Kommentar im HTML sagt das
ausdrücklich — Werbung, die mehr verspricht als das Telefon hält, kostet genau das
Vertrauen, das sie aufbauen soll). Dazu FAQ-Eintrag „Wer geht ran, wenn ich anrufe?"
samt Ergänzung im FAQPage-JSON-LD (SEO), i18n-Schlüssel `ast.*` und `faq.q10/a10` in
allen drei Sprachdateien, CSS-Block `.ast` im Stil der Prinzipien-Zellen.

**Kundendashboard:** Unter dem Fragebogen-Knopf steht jetzt der Satz, dass Manuela ihn
gemeinsam am Telefon ausfüllt (`Texte::SEITE['fragebogenTelefon']`, dreisprachig) —
genau an der Stelle, an der jemand vor 48 Feldern steht und sie auf morgen verschieben
will.

Sichtprüfung lokal per Screenshot (it/de), Prüfkette weiter 754 grün.

## Die Schleife vom 7. September: dieselbe Frage, fünfmal (07.09.2026)

Manuel wollte den Fragebogen am Telefon ausfüllen und bekam die Zielgruppen-Frage
immer wieder — dreimal um 04:56, zweimal um 05:02. Er hat es der Verwaltung selbst
ins Protokoll gesagt: „das System wiederholt ständig die Frage zur Zielgruppe,
obwohl die Antwort 'Leser' gegeben wurde."

Drei Fehler, alle im Werkzeug behoben, keiner im Leitfaden:

1. **Eine Antwort, die in keine Auswahl passt, ist trotzdem eine Antwort.**
   „Leser" auf „wer sind eure Kunden?" traf keine der acht Optionen und die Frage
   blieb offen. Jetzt landet der Wortlaut in der freien Zeile des Feldes
   (`zielgruppe__frei`), das Feld gilt als beantwortet, und Uwe liest „Leser" —
   was mehr sagt als jeder Auswahlschlüssel. `beantwortet()` zählt die freie
   Zeile mit.

2. **Was offen blieb, darf „start" nicht wieder vorlegen.** „start" nahm immer
   die erste offene Frage von vorn — also genau die übersprungene.
   `fragebogenUebersprungen()` liest die offen gelassenen Felder aus der
   Aktivitätsspur (24 h) und lässt sie in der Reihe aus; sie stehen weiter in
   der Durchsicht vor dem Abschicken.

3. **Das Speicherformat passte nicht zur Formularprüfung.** `Onboarding::saeubern()`
   erwartet Mehrfachauswahl als Liste und die Materialliste als Zuordnung — so
   schickt es der Browser. Das Telefon übergab Zeichenketten („einheim,jung");
   saeubern verwarf sie lautlos, das Feld blieb leer, die Frage kam wieder.
   `speicherwert()` und `standwert()` liefern jetzt die Formularform, und die
   Kette prüft für jede Feldart die volle Rundreise speicherwert → saeubern → DB.

Dazu: „weiß nicht" auf eine Zahlenfrage wird nachgefragt statt als 0 gespeichert
(eine Website mit null Seiten wäre erst im Angebot aufgefallen), und die
Werkzeugbeschreibung sagt jetzt ausdrücklich: start nur EINMAL am Anfang, danach
immer „antwort" mit genau der Frage aus der Antwort.

Prüfkette 754 → 770. Der nachgespielte Anruf ist Abschnitt 41/9b.

## Der KAS-Reseller (07.09.2026)

Uwe ist jetzt Reseller bei All-Inkl. Damit bekommt künftig jeder Kunde einen eigenen
KAS-Account unter seinem Vertrag — eigener Webspace, eigene Domain, eigene Postfächer,
sauber getrennt von vecom-design.it. Domains für Kunden laufen über das
Domainbestellsystem; primärer Nameserver für im KAS angelegte Domains ist
ns5.kasserver.com. (Kundennummer und Zugänge stehen bewusst nicht hier — das
Repository ist öffentlich.)

Erste Stufe gebaut: `app/src/Kas.php` spricht die KAS-API (SOAP, ohne fremde
Bibliothek), Zugang unter Einstellungen → Zugänge & Schutz (landet nur in
app/config.local.php), Verbindungsprüfung mit Accountliste, Flutbremse
(KasFloodDelay) eingebaut. **Diese Stufe liest nur** — Accounts anlegen kommt als
eigene Stufe, sobald der Zugang steht und ein von Hand angelegter Account zum
Gegenprüfen da ist; ein Kettentest wacht darüber, dass die Leseklasse keine
anlegenden Methoden bekommt, ohne dass es auffällt.

Entschieden (07.09.): KAS-API an die Verwaltung anbinden; Preise/Angebot ums Hosting
ergänzen (Hosting auf eigenem Kunden-Account als Teil der Betreuung ausweisen —
Preis dafür ist noch Uwes Entscheidung). Wichtig fürs Einrichten: Die API nimmt das
KAS-Passwort (im KAS unter Einstellungen gesetzt), nicht das MembersArea-Passwort.

Prüfkette 770 → 774.

### Nachtrag KAS-API (07.09.2026, abends)

Die API ist verbunden und geprüft: „Der Zugang steht. 0 Unter-Accounts im
Reseller-Vertrag." Zwei Stolperer aus der Einrichtung, beide abgestellt:

1. Die Sofort-Prüfung nach dem Speichern sah den alten Konfigurationsstand
   (die Datei wird je Aufruf einmal gelesen) und meldete irreführend „Kein
   KAS-Zugang hinterlegt". Jetzt prüft `Kas::zugangFrisch()` mit den gerade
   gespeicherten Werten.
2. `kas_password_incorrect` heißt oft nur „noch nicht": Ein frisch im KAS
   gesetztes Passwort braucht ein paar Minuten, bis es bei All-Inkl greift.
   Die Fehlermeldung sagt das jetzt dazu.

## KAS Stufe 2: Accounts anlegen (07.09.2026, abends)

`Kas::accountAnlegen()` legt Kunden-Accounts unter dem Reseller an — auf Knopfdruck
unter Einstellungen → Zugänge & Schutz („Kunden-Account anlegen"), nie von allein.
Die beiden Pflicht-Passwörter (KAS + FTP) erzeugt der Server (16 Zeichen, regelfest),
zeigt sie GENAU EINMAL in verdeckten Feldern an und speichert sie nirgends; gemerkt
wird nur der von All-Inkl vergebene Login. Kontingente werden bewusst nicht gesetzt
(All-Inkl-Vorgaben statt geratener Zahlen mit falscher Einheit). Der Kommentar ist
Pflicht — er ist das Einzige, woran man in der Accountliste den Kunden erkennt.

Die Grenze, die bleibt: LÖSCHEN kann die Klasse nicht, und ein Kettentest wacht
darüber (bewusst umgebaut aus dem alten „legt nichts an"-Wächter). Ein Account, an
dem eine Kundenwebsite hängt, verschwindet nur von Hand im KAS.

Als Nächstes (Stufe 3, wenn gewünscht): Anlegen aus dem Projekt heraus + Login am
Projekt vermerken. Prüfkette 774 → 779.

## Wunschdomain-Automatik: vom Fragebogen bis zum Hosting-Vertrag (07.09.2026, nachts)

Wer im Fragebogen „keine Website, Domain neu" sagt und Wünsche nennt, bekommt ab
jetzt automatisch ein Angebot — und am Ende einen fertig eingerichteten Account.
Der Ablauf (`app/src/Hosting.php`, Tabelle `hosting_auftraege`, Migration 040):

1. **Fragebogen abgeschickt** → `Hosting::nachFragebogen()` (Haken in
   `Onboarding::absenden()`, still): erste freie Wunschdomain suchen, Vorschlag
   mit eingefrorenem Preis anlegen. Keine frei → Meldung an Uwe statt stillem
   Verzicht. Nie ein zweiter Vorschlag neben einem bestehenden.
2. **Kundenseite**: Kasten mit Domain, Preis (9,90 €/Monat aus Paket `hosting`,
   art `hosting`, active 0 — taucht in keiner Bestellliste auf) und ZWEI Knöpfen.
   Der Ja-Knopf IST die Zustimmung zu den Monatskosten; ohne ihn passiert nichts.
3. **Finale Freigabe** → `Hosting::beiStatuswechsel()` (Haken in
   `Events::projektStatus()`, still): KAS-Account + Domain + Postfach info@
   anlegen, jeder Schritt einzeln fehlertolerant; Monatsvertrag über
   `Abo::anlegen()` — außer der Kunde hat Betreuung Plus/Premium (dann
   inklusive). Meldung „Domain bestellen" an Uwe: Die Registrierung selbst geht
   nur übers Domainbestellsystem (kein API-Weg), Nameserver ns5.kasserver.com.
4. **Zugangsdaten**: AES-256-GCM-verschlüsselt in der DB (Schlüssel
   `hosting_geheim` in config.local.php, entsteht beim ersten Gebrauch),
   EINMALIGER Abruf durch den Kunden auf seiner Seite, danach gelöscht;
   spätestens nach 14 Tagen räumt der Cron ab.

Nebenbei repariert: `Abo::anlegen()` sperrte bisher JEDES zweite Abo — jetzt
vergleicht die Eindeutigkeitsprüfung die Paket-Art (Betreuung und Hosting laufen
nebeneinander, zwei gleicher Art bleiben gesperrt). Zeitvergleiche gegen die
PHP-Uhr statt DB-NOW() (Zeitzonenfalle). Texte dreisprachig in `Texte::SEITE`
(hosting*). Kettentest-Abschnitt 43 (Riegel, Preis eingefroren, Einmal-Abruf,
Krypto-Rundreise, Abo-Arten). Prüfkette 779 → 814.

Bewusste Grenze: Domainpruefung fragt echte Dienste — der Kettentest prüft die
Riegel drumherum, nicht das Netz. Und die Domainbestellung bleibt ein Handgriff
mit Ansage, weil All-Inkl dafür keine API anbietet.

## Solo-Paket: Domain & Hosting ohne Website (07.09.2026, nachts)

Das Hosting gibt es jetzt auch allein — für Kunden, die (noch) keine Website
wollen. Entscheidungen: 9,90 €/Monat wie im Projekt-Ablauf; angelegt wird
erst nach Eingang der ERSTEN Monatszahlung (eine Domain kostet uns echtes
Geld — nichts auf Verdacht); Bestellweg über Formular mit Wunschdomain;
10 GB Speicher je Kunden-Account (max_webspace 10240 MB — der WEB-L-Vertrag
hat 200 GB auf 25 Accounts, ohne Grenze könnte einer alles belegen).

Der Weg: Karte auf der Website (Sektion #hosting, dreisprachig, Preis live
aus der Verwaltung über pakete-daten.php-Schlüssel `hosting`) → hosting.php
(Name, E-Mail, Wunschdomain; wird eine ANFRAGE über Anfrage::annehmen —
bewusst KEINE Live-Domainprüfung ohne Token) → Uwe prüft im Kundenblatt
(„Domain & Hosting", tat=hosting_vorschlag: nur eine als FREI bestätigte
Domain wird angeboten) → Angebots-Mail `hosting_angebot` mit Kundenseiten-
Link → Ja-Knopf startet Vertrag + erste Rate + Mail `hosting_faellig`
(eigener Text — die Betreuungs-Mail verspricht Aktualisierungen, die es hier
nicht gibt) → Zahlungseingang bestätigt → Events::zahlungBestaetigen ruft
Hosting::nachZahlung() → KAS-Account (mit 10-GB-Grenze), Domain, Postfach,
Zugangsdaten-Blob. Website-Ablauf unverändert (finale Freigabe).

Dazu: Migration 041 (Paket aktiv+öffentlich mit Karten-Texten it/de/en),
Admin-Handknopf „Jetzt von Hand anlegen" (Riegel „nur aus zugestimmt"
bleibt im Werkzeug), Vertragskasten auf der Kundenseite heißt nach dem
Paket, Rate heißt „Domain & Hosting — Monat" statt „Betreuung …",
Bestellformular-Filter schließt art hosting aus. Kette: Abschnitt 2 wählt
nur Website-Pakete, Abschnitt 43 prüft den Solo-Ablauf. 814 → 825 grün.

Merkposten: SSL (Let's Encrypt, kostenlos) wird je Domain im KAS aktiviert —
steht jetzt als Handgriff mit in der „Domain bestellen"-Meldung; die
Domainbestellung selbst bleibt Handgriff (kein API-Weg). AGB/Widerruf fürs
Solo-Paket ggf. vom Anwalt gegenlesen lassen.

## Rechtlich sauber: Vertragsblatt für Monatsverträge (08.09.2026, nachts)

Uwe fragte, ob Monatsvertrags-Kunden Rechnung, Vertrag und Zugangsdaten
„wie es sich gehört" bekommen. Bestandsaufnahme: Belege je Rate liefen
schon (Rechnung::ausZahlung kennt abo_id seit 032, Beleg-Mail mit PDF über
Events::zahlungBestaetigen, Liste auf der Kundenseite; Rechnung vs.
Zahlungsbeleg schaltet mit der Partita IVA). Zugangsdaten: einmalige
verschlüsselte Anzeige — sauber. Was FEHLTE: ein Vertragsdokument für
Abos — beim Solo-Hosting kommt der Vertrag online zustande (Fernabsatz!),
und da gehört eine Bestätigung auf dauerhaftem Datenträger hin.

Gebaut: `app/src/Abovertrag.php` — einseitiges PDF je Monatsvertrag
(A-{id}): Anbieter-Briefkopf, Kunde, Paketname und Leistungen in
Kundensprache (aus packages.texte), Monatsbetrag, Beginn, Mindestlaufzeit,
Kündigungssatz, beim Solo-Hosting „zustande gekommen am {datum} per Klick",
Widerrufsbelehrung (Widerruf::t), AGB-Link. `Abovertrag::bestaetigen()`
hängt an `Abo::anlegen()` (jeder Abschluss, still): Mail `vertrag_monat`
mit dem Blatt im Anhang, Wiederholungsschutz übers Protokoll
(abo_vertragsblatt). Kundenseite: Knopf „Vertragsblatt (PDF)" im
Vertragskasten (?abovertrag=, Kundennummern-geprüft wie beim Beleg).

Dazu Button-Lösung nachgeschärft: Der Ja-Knopf heißt jetzt
„zahlungspflichtig bestellen / ordino con obbligo di pagare", und die
Angebotstexte nennen die 12 Monate Mindestlaufzeit VOR dem Knopf.
Kette 825 → 828 grün. Merkposten: AGB/Widerruf in legal.html sollte der
Anwalt einmal mit Blick auf die Monatsverträge gegenlesen.

### Nachtrag: Kunden-Mail beim Anlegen + Wunschdomains im Admin (08.09.2026)

Drei Punkte auf Uwes Zuruf. (1) Der Kunde erfährt jetzt per Mail
(`hosting_fertig`, dreisprachig), dass Domain und Account geschaltet sind —
die Zugangsdaten stehen NICHT in der Mail (unverschlüsselt, liegt ewig im
Postfach), sondern die Mail führt zur einmaligen Anzeige auf seiner Seite
und sagt ihm ausdrücklich, die Passwörter danach im KAS-Kundenmenü zu
ändern und dass die Anzeige nach 14 Tagen verfällt. (2) Derselbe
Passwort-Ändern-Hinweis steht jetzt auch direkt unter der einmaligen
Anzeige (hostingZugangAendern). (3) Im Verwaltungs-Kundenblatt zeigt der
Block „Domain & Hosting" die Wunschdomains aus dem Fragebogen: ohne
Auftrag als Ein-Klick-Knöpfe (prüft und bietet an — der Handler lässt
weiter nur als FREI bestätigte Domains durch), mit Auftrag als Infozeile
der übrigen Wünsche. Zur Klarstellung dokumentiert: Jeder Kunde hat mit
seinem Unter-Account ein EIGENES KAS-Kundenmenü (kas.all-inkl.com).
Kette weiter 828 grün.

### Nachtrag: Löschweg kennt die Hosting-Aufträge (08.09.2026)

Vor Uwes Test-Buchung entdeckt: Kunde::loeschen (die REIHE) und
Kunde::anonymisieren stammten von vor Migration 040 — ein gelöschter
Testkunde hätte einen Waisen-Hosting-Auftrag mit Domain, KAS-Login und
verschlüsseltem Zugangs-Blob hinterlassen. Jetzt: Löschen nimmt
hosting_auftraege mit; Anonymisieren lässt die Vertragszeile stehen,
leert aber zugang_blob und notiz. Kette 828 grün.

## Die Sprache reist mit — über alle Seiten (08.09.2026)

Der Riss: Die Umschaltung oben rechts ist auf den festen Sprachfassungen
ein LINK (/de/, /en/) — und nichts merkte sich den Klick. Gespeichert
wurde die Wahl nur auf den vier Umschalt-Seiten (legal, pakete, danke,
404). Wer DE wählte und später die Domain neu eintippte oder eine
PHP-Seite ohne ?lang öffnete, stand wieder auf Italienisch.

Jetzt (alles zentral in assets/js/app.js): (1) Wer auf einer festen
Fassung steht, hat sie gewählt — localStorage vecom-lang UND Cookie
vecomlang (1 Jahr) werden gesetzt. (2) Sprachweiche: Wer eine der
gemappten Seiten (Start, Preise, Betreuung — je it/de/en) VON AUSSEN
betritt (Lesezeichen, Google, getippt) und eine andere Fassung gemerkt
hat, wird per location.replace dorthin geleitet; Klicks INNERHALB der
Website (Referrer = eigene Domain) leiten nie um — sonst käme niemand
bewusst zurück auf IT. (3) Auf den Umschalt-Seiten werden nach jedem
Sprachwechsel die Links umgeschrieben: Heim-/Preis-/Betreuungs-Links
zeigen auf die gewählte Fassung, lang=-Parameter in PHP-Zielen werden
mitgedreht. (4) Die fünf PHP-Seiten (bedarf, buchen, hosting, fragebogen,
kunde) nehmen ohne ?lang das Cookie als Rückfall — beim Fragebogen und
der Kundenseite bleibt die im Datensatz hinterlegte Kundensprache davor.

### Nachtrag: build.mjs kennt hosting.php (08.09.2026)

Live gefunden: Der deutsche Hosting-Knopf zeigte auf ?lang=it. Ursache:
build.mjs baut /de/ und /en/ aus der italienischen Vorlage und dreht die
lang=-Parameter nur dort, wo eine Regel steht — bedarf.php hatte eine,
hosting.php nicht. Regel ergänzt, Fassungen neu gebaut. (Die von Hand
geschriebenen de/en-Hosting-Blöcke waren ohnehin Makulatur — der Build
überschreibt sie; die Übersetzung tragen die i18n-Schlüssel.)

### Nachtrag: Hosting ist Direktverkauf, kein Konfigurator-Vorgang (08.09.2026)

Uwe: Beim 9,90-Solo-Hosting zeigte die Verwaltung den Konfigurator-Weg —
falsch, das ist ein fester Direktverkauf ins Abo. Ursache: Eine
Hosting-Anfrage (paket_slug='hosting') landete in der normalen
Anfrage-Ansicht (app/views/anfrage.php), deren Hauptaktion für Anfragen
ohne Bestellung "Konfigurator schicken ›" ist. Jetzt eine Weiche: Bei
paket_slug='hosting' erscheint stattdessen der Block "Domain & Hosting —
Direktverkauf" mit der aus dem Formular genannten Wunschdomain und einem
Knopf "Prüfen und dem Kunden anbieten" (Handler hosting_vorschlag, führt
zurück ins Kundenblatt). Kein Konfigurator, keine Festpreis-Paketauswahl,
keine rohe Nachricht.

Zweite Stelle (nachgereicht): Der globale "Jetzt dran"-Aufmacher
(Vorgang.php, Stufe 'gespraech') zeigte fuer JEDE offene Anfrage
"Konfigurator schicken" — auch fuer den Hosting-Kunden, prominent oben
auf jeder Verwaltungsseite. Jetzt eine Weiche vor dem Bedarf/Konfigurator-
Block: Bei einem Solo-Hosting-Vorgang (hosting_auftrag mit project_id NULL
ODER Anfrage paket_slug='hosting') richtet sich "Jetzt dran" nach dem
Auftragsstand — kein Auftrag: "Domain & Hosting anbieten" (Du); angeboten:
"Wartet auf Zustimmung" (Kunde); zugestimmt: "Wartet auf die erste
Zahlung"; angelegt: "laeuft". Kette 828 gruen. Live geprueft: Detailansicht
zeigt den Direktverkauf-Block (Wunschdomain www.trendonix.de aus der
echten Anfrage #23).

### Direktkauf: Domain prüfen und sofort abschließen (08.09.2026)

Uwe: Wo eine Domain eingegeben wird, soll sofort geprüft werden; ist sie
frei, kauft der Kunde direkt und schließt ab — kein Anbieten durch Uwe,
die Domain steht direkt in der Verwaltung. Umgesetzt: hosting.php ist jetzt
ein zweistufiger Direktkauf statt einer Anfrage.

1. Kunde gibt Name, E-Mail, Wunschdomain ein → "Verfügbarkeit prüfen".
2. Domainpruefung::pruefen läuft (einmal je Absenden, nicht bei jedem
   Tastendruck — 15-Sek-IP-Bremse + Sitzungsgrenze schützen die fremden
   RDAP/Whois-Dienste). Vergeben/unklar → Meldung, anderer Name. Frei →
   Bestätigungsansicht: "✓ domain.it ist frei!", Preis, Widerruf- und
   AGB-Checkbox, Button "Zahlungspflichtig bestellen".
3. Klick = Hosting::direktKauf(): Domain wird ein zweites Mal geprüft
   (Manipulationsschutz), Kunde + Auftrag (project_id NULL) angelegt und
   über Hosting::antwort(true) verbindlich abgeschlossen — Vertrag, erste
   Rate, Zahlungsaufforderung, Vertragsblatt. Kein Anfrage-Umweg, kein
   Konfigurator, kein Anbieten. Angelegt (KAS) wird nach Zahlungseingang.

hosting.php nutzt jetzt Session + CSRF (vecomhosting). Der Admin-Weg
"anbieten" (hosting_vorschlag) bleibt für von Hand angelegte/telefonische
Kunden und Altfälle. Kettentest 828 → 830 (Direktkauf-Riegel). Merkposten
verstärkt: Der Widerrufsverzicht-Text (aus Widerruf.php, wie bei buchen.php)
ist für ein Dauerschuldverhältnis (Hosting-Abo) rechtlich zu prüfen — Anwalt.

### Überweisungs-Zahlweg + Kunden-Dateien löschen (08.09.2026)

Zwei Punkte nach dem ersten echten Hosting-Testkauf (Kunde 43):

1. Zahlweg ohne Stripe: Solange Stripe nicht freigeschaltet ist, führt ein
   erzeugter Zahlungslink ins Leere. Auf der Kundenseite (kunde.php) prüft
   der Raten-Block jetzt, ob Stripe wirklich kassieren kann (bereit +
   webhookBereit + Live — dieselbe lokale Prüfung wie der Direktkauf). Wenn
   nicht: Der ins Leere führende Karten-Knopf wird ausgeblendet und
   stattdessen ein Überweisungs-Kasten gezeigt (Empfänger, Bank, IBAN,
   Verwendungszweck = Kundennummer · Paketname). Nur wenn eine IBAN in
   Firma & Steuern hinterlegt ist — sonst wäre es eine Sackgasse. Texte
   dreisprachig (Texte::KUNDE ueberweisung*). Sobald Stripe live ist,
   verschwindet der Kasten von selbst und der Karten-Knopf kommt zurück.
   MERKPOSTEN: Uwe muss seine IBAN (Revolut) in Firma & Steuern eintragen,
   sonst erscheint der Überweisungsweg nicht.

2. Kunden-Dateien löschen: In der Kundenakte (app/views/kunde.php) hat jetzt
   jede Datei neben „Herunterladen" einen „Löschen"-Knopf (mit Rückfrage).
   Der bestehende datei_weg-Handler sprang hart nach projekte/{id} — bei
   einer Datei am Kunden ohne Projekt (project_id NULL) landete er auf
   projekte/0 (404). Handler nimmt jetzt ein zurueck-Ziel und fällt sonst
   auf Kundenakte/Projekt zurück.

### Testmodus-Kauf reparieren + Vormerk-Sicherheitsnetz (08.09.2026)

Nach dem echten Testkauf (Kunde 43): keine Zahlungsseite, keine Mail.
Befund in der Verwaltung: Stripe ist im TESTMODUS eingerichtet (sk_test,
Webhook, kürzlich grüne checkout.session.completed), der Test/Live-
Umschalter existiert schon (Einstellungen → Bezahlung), und der Schalter
„Kaufknopf auch im Testmodus zeigen" (direktkauf_test) ist AN. Brevo ist
verbunden (Verbindungstest grün).

Der eigentliche Fehler war mein eigener Code von zuvor: kunde.php und
hosting.php prüften `modus === 'live'` und blendeten dadurch im Testmodus
jeden Zahlungsknopf aus. Jetzt zählt der Testmodus mit, wenn direktkauf_test
an ist (dieselbe Regel wie buchen.php): live ODER (testmodus + Schalter).

Zusätzlich als Sicherheitsnetz: hosting.php verkauft nur verbindlich, wenn
ein Bezahlweg steht (Stripe kassiert ODER IBAN hinterlegt). Fehlt beides,
nimmt die Seite die Wunschdomain nur als VORMERKUNG an (Anfrage, kein
Vertrag, keine Kundenseite) — „wir melden uns, sobald du bezahlen kannst".
Sobald ein Weg da ist, wird von selbst wieder verkauft. Löst Uwes „das
sollte noch nicht möglich sein" strukturell. Kette 830.

Merkposten E-Mail: Der Testkauf ging an info@vecom-design.it (eigene
Domain) — für echte Tests eine EXTERNE Adresse nehmen. Firmendaten (inkl.
IBAN) sind noch komplett leer — eintragen für Belege + Überweisungs-Fallback.

### IP-Bremse entschärft + „erst Bezahlseite, dann Dashboard" (08.09.2026)

Zwei Befunde aus Uwes Test (Handy-Screenshot, Kunde „Manuel Brandner",
Domain trendonvix.com):

1. „Einen Moment — bitte in ein paar Sekunden noch einmal versuchen"
   mitten im Ablauf. Ursache: Die IP-Sperre (15 s) galt für JEDEN POST,
   also auch für den Kaufklick, der Sekunden nach dem Prüfen kommt.
   Jetzt bremst sie nur noch die reine Verfügbarkeitsprüfung (tat=pruefen),
   und das Fenster ist auf 6 s verkürzt. Kauf und Vormerkung laufen nie
   mehr an der Bremse auf.

2. Nach Kauf ging es direkt aufs Dashboard, ohne zu bezahlen. Jetzt:
   Nach „Zahlungspflichtig bestellen" leitet hosting.php direkt auf die
   Stripe-Bezahlseite der ersten Rate. Als success_url ist die persönliche
   Kundenseite gesetzt (Abo::anfordern nimmt jetzt ein Erfolgsziel,
   Stripe::bezahlseite eine optionale Erfolgs-URL) — der Kunde landet also
   ERST auf der Bezahlseite und DANACH auf seinem Dashboard; der Webhook
   legt in der Zwischenzeit Domain, Account und Postfach an. Steht kein
   Stripe-Link (nur Überweisung), bleibt die Danke-/Überweisungsansicht.

Kette 832 (zwei neue Prüfungen: anfordern nimmt ein Erfolgsziel; ohne
Stripe trägt die Rate keinen Link → Fallback greift).

Offen bei Uwe: Firmendaten inkl. IBAN eintragen (IBAN trägt er selbst ein —
Kontonummern gebe ich nicht in Felder ein). Echte Tests mit EXTERNER
E-Mail (nicht @vecom-design.it).

### E-Mail-Gesamtaudit + zwei Lücken geschlossen (08.09.2026)

Uwe: „einiges kommt nicht an" — quer über alle Flows, Test wie Live.

Gemessen (Postausgang live angesehen): Die mails-Tabelle war KOMPLETT LEER —
nichts gesendet, nichts versucht. Also NICHT Brevo, das ablehnt (das gäbe rote
fehler-Zeilen), sondern: Die Sende-Schritte wurden nie erreicht. Brevo ist
eingerichtet (Schlüssel endet Cqpb), Firmendaten sind gefüllt (Vecom Design,
Uwe Vetter, Via d Ascoli 25, Aragona, Bank Revolut), Partita IVA leer (Absicht).

E-Mail hängt NICHT am Stripe-Test/Live-Modus (Brevo, unabhängig). Kein Flow
lässt eine Kundenmail nur im Livemodus raus — Audit bestätigt.

Zwei Lücken geschlossen:
1. Angebot::senden hat nie eine Mail geschickt (nur Status gesendet). Jetzt
   geht die Angebots-Mail mit Link raus. (Vorlage Texte::MAILS['angebot'],
   dreisprachig.) — commit 11a3634.
2. Monatliche Folgeraten (ab Monat 2): Der Cron legte sie nur an, forderte sie
   aber nicht an — der Kunde bekam keine Rechnung, wenn Uwe das To-do übersah.
   Jetzt fordert Abo::abrechnungenAnlegen jede fällige Rate gleich an (Mail +
   Link, success_url = Kundenseite), gegen Doppelversand gesichert.

Offen/Hinweis an Uwe: In Brevo prüfen, dass kontakt@vecom-design.it als
Absender verifiziert ist (sonst scheitert die erste echte Mail — steht dann
sichtbar im Postausgang). Sauberer End-to-End-Test mit externer Adresse.
Struktureller Merkposten: Gebuchte Website-Pakete hängen an der Zahlung — die
Bestätigungsmails entstehen aus dem Stripe-Webhook; stimmt das Live-Webhook-
Geheimnis nicht, kommt nach der Zahlung nichts (auch kein Postausgang-Eintrag).

Kette 838.

### Chef-Modus für Manuela — Assistent nur für Uwe (08.09.2026)

Uwe will einen Assistenten NUR für die Verwaltung, der ihn unterstützt (Fragen,
nächste Schritte, Kunden anlegen, Nachrichten/Notizen, Vorschläge mit ja/nein),
während die kundenseitige Manuela unverändert weiterläuft. Entscheidung: den
VORHANDENEN STRATO-Assistenten nutzen (kein neues KI-Gehirn), Schutztür = nur
ein gesprochenes Codewort. Uwe ist bei STRATO eingeloggt und will die Konfig
von mir gemacht haben.

Server-Seite gebaut (Grundlage, damit die STRATO-Werkzeuge ein Ziel haben):
- app/src/Chef.php: codewort/eingerichtet/frei (zeitkonstant, tippfehler-tolerant),
  lage (Tagesüberblick), kunde (Stand), kundeAnlegen (mit ja-Bestätigung, kein
  Doppel), notiz (mit ja-Bestätigung, landet in Meldungen).
- chef.php: eigener Endpunkt, zwei Türen — STRATO-Schlüssel (wie telefon.php) UND
  Codewort. Ohne gesetztes Codewort ist der Modus aus.
- Verwaltung → Einstellungen → Telefonassistentin: Feld „Chef-Modus / Codewort"
  (Uwe setzt es selbst, steht nirgends im Repo/Chat). Handler chef_codewort(_weg).
- Kette 853 (15 neue Prüfungen: Schutztür, Lage, Anlegen mit/ohne ja, Notiz).

Offen: STRATO-Konfiguration (Verhalten: Chef-Modus-Zweig auf Codewort; 4 neue
API-Integrationen chef_lage/chef_kunde/chef_kunde_anlegen/chef_notiz auf
chef.php) — additiv, ohne die Kundenstrecke anzutasten. Uwe setzt danach das
Codewort in der Verwaltung.

### Kundenseiten mit Claude Code bauen — die Werkstatt bekommt eine Tür (08.09.2026)

Bisher lief der Weg zum Baumeister über die Zwischenablage: Briefing erzeugen,
kopieren, Claude öffnen, einfügen, bauen — und am Ende Vorschau-Adresse und
Stand von Hand zurück in die Verwaltung tippen. Das trägt, solange ein Mensch
dazwischensitzt. Claude Code sitzt nicht in einem Chatfenster, sondern auf
einem Rechner mit einem Ordner: Es braucht eine Tür zum Holen und eine zum
Melden. Uwe: beide Richtungen, gebaut wird auf dem Mac und in der Cloud,
veröffentlicht wird auf Netlify (wie Cavaleri).

- app/src/Werkstatt.php: eigener Schlüssel (Setting werkstatt_schluessel,
  getrennt vom Telefonschlüssel — der liegt bei STRATO im Klartext und darf
  nicht schreiben). Aktionen: liste, auftrag (Briefing entsteht dabei und
  bleibt am Projekt), weiter, vorschau (Vorschau- und Quelltext-Adresse),
  stand, notiz, freigeben.
- werkstatt.php: Endpunkt, Schlüssel nur im Kopf (X-Vecom-Werkstatt oder
  Bearer), nie in der Adresszeile. Drosselung 120/Minute. Erwartbare Fehler
  kommen als lesbarer Satz zurück, nicht als leere Seite.
- 042_werkstatt_api.sql: projects.repo_url — die Vorschau sagt, WO die Seite
  ist, nicht WORAUS sie gebaut ist. In Monat 14 ist das die eigentliche Frage.
- Verwaltung → Vecom-Standard: Block „Mit Claude Code bauen" (Schlüssel
  erzeugen/neu/entfernen, Adresse, Beispielaufruf). Am Projekt ein Feld für
  die Quelltext-Adresse und ein Knopf, der den Satz für Claude Code kopiert.
- Kette 885 (32 neue Prüfungen).

DIE EINE REGEL: Eintragen schaltet nicht frei. „freigeben" ist der einzige
Schritt hier draußen, der beim Kunden ankommt (E-Mail + offener Entwurf) — und
verlangt deshalb ein ausdrückliches bestaetigt=ja.

### Kein Festpreis-Paket mehr — der Preisbereich neu geordnet (12.09.2026)

Uwe: „mache es so das 499 paket nicht mehr da ist, denn grundsätzlich wird der
Preis anhand des Umfangs schon berechnet. Mache die Anordnung insgesamt
hübscher, so dass der Kunde eher kauft."

Die 499-Euro-Karte war das letzte Paket auf der Startseite und stand direkt
neben dem Weg, der den Preis aus dem tatsächlichen Umfang rechnet. Zwei
Antworten auf dieselbe Frage — und die feste Zahl gewinnt jedes Mal, weil sie
konkret ist und eine Spanne es nicht ist. Wer 275 gebraucht hätte, las 499 und
ging; wer 900 gebraucht hätte, las 499 und fühlte sich hinterher getäuscht.

- index.html: `.einstieg` samt Karte und Einmalig/12-Monate-Umschalter raus.
  Aus den strukturierten Daten das Offer mit `price: 499`; dafür steht die
  Betreuung jetzt dort (39 €, monatlich). FAQ-Antwort und FAQ-Schema nennen
  statt des Einstiegspakets den Anfangspunkt ab 275 € — dieselbe Zahl wie
  `minPrice` im verbliebenen Offer, damit es nur eine Stelle zum Nachziehen
  gibt.
- Der Bedarfsweg ist jetzt der Hauptakt: zweispaltig ab 920px, links Grund und
  Knopf, rechts drei nummerierte Schritte („was passiert nach dem Klick").
  Getrennt durch eine Linie statt durch eine zweite Karte — zwei Karten
  konkurrieren, eine Karte mit zwei Hälften führt.
- Neu darunter: vier Beispielpreise (`.orient`). Das eine, was die Preiskarte
  konnte, war eine Zahl nennen; ohne jede Zahl nimmt der Leser das Schlimmste
  an. Vier Beispiele können das besser, ohne jemanden in ein Paket zu stecken.
  Die Zahlen kommen über `data-fall` aus `preise-daten.php` — dieselbe Quelle
  wie das Angebot. Dafür steht `preise-live.js` jetzt im Startseiten-Bündel.
- `.monatlich`: Betreuung und Hosting stehen nebeneinander. Solange die
  Festpreis-Karte da war, fiel nicht auf, dass beide je allein in einem
  Dreispaltenraster standen — nach ihrem Wegfall waren es zweimal zwei Drittel
  Leere untereinander.
- 043_kein_festpreis.sql: `starter` auf `oeffentlich = 0`. Damit ist der
  Direktkauf zu (`buchen.php` verlangt `oeffentlich = 1`) und die Karte käme
  selbst dann nicht zurück, wenn jemand das HTML wiederherstellt. Nicht
  gelöscht: an dem Paket hängen Bestellungen und Belege.
- Beim Umbau gefunden: `.bedarfsweg` und `.care` benutzten `--blau`, `--cyan`,
  `--dim`, `--leise`, `--linie` und `--r-lg` — Token, die es im System gar
  nicht gibt. Beide Blöcke liefen seit jeher auf ihren Rückfallwerten und waren
  dadurch runder, anders blau und anders grau als alles daneben. Jetzt stehen
  die echten Namen da.

WAS ES NICHT MEHR GIBT: Eine Website lässt sich nicht mehr in einem Zug selbst
kaufen. Der Weg führt über Konfigurator und Angebot — bewusst, denn genau dort
entsteht der Preis. Direkt kaufbar bleiben die Monatsverträge: Betreuung und
Domain & Hosting.

Offen: `pakete.html` zeigt weiter Starter/Business/Premium mit 499/899/1.499 €.
Die Seite ist noindex, steht nicht in der Sitemap und nichts verlinkt sie mehr
— aber wer die Adresse kennt, liest dort drei Pakete, die es nicht gibt.
Ebenso der Schritt „Pro Kunde entscheiden: Starter (499 €) …" im Cockpit.

### Alte Paketseite weggeräumt, Preise +15 % (12.09.2026)

Uwe: „ja räume weg und bei den Preise von bis setze etwas höher."

**Weggeräumt.** `pakete.html` ist aus dem Repository. Weil der Deploy nur
spiegelt (`lftp mirror --reverse`, ohne `--delete`), liegt die alte Datei
weiter auf dem Webspace — deshalb steht die eigentliche Arbeit in der
`.htaccess`: `/pakete.html` geht per 301 auf die Preisseite, sprachrichtig
(`?lang=de` → `/de/preise.html`, `?lang=en` → `/en/pricing.html`, sonst
`/prezzi.html`). 301 und nicht 404, weil die Adresse in alten Angeboten und
E-Mails steht. Dieselbe Überlegung wie bei den `.md`-Dateien: Der Deploy lädt
sie nicht mehr hoch, die Sperre gilt der Kopie, die schon dort liegt.

Mitgegangen sind die toten Schlüssel, die nur diese Seite brauchte: `plans.n1`
bis `i3`, `badge`, `priceNote`, `more`, `cmp*` und die ganze Gruppe `det` — in
allen drei Sprachdateien, die jede Seite lädt. Im Cockpit stand als Schritt
noch „Pro Kunde entscheiden: Starter (499 €), Business (899 €) oder Premium
(1.499 €)"; das war Anweisung an sich selbst, drei Produkte zu verkaufen, die
es nicht mehr gibt.

**+15 % (044_preise_plus15.sql).** Jede Grenze auf volle fünf Euro gerundet,
die Monatsverträge unangetastet — an einem Monatsbetrag bleibt der Blick
hängen, und an Hosting hängen echte Fremdkosten. Die vier Beispiele:

| Fall | vorher | jetzt |
|---|---|---|
| eine Seite | 275 – 350 € | 325 – 400 € |
| fünf Seiten | 450 – 575 € | 525 – 650 € |
| drei Sprachen | 675 – 875 € | 800 – 1.000 € |
| Onlineshop | 1.000 – 1.350 € | 1.200 – 1.550 € |

MBclick, der einzige Mitbewerber in der Provinz mit offenen Preisen, beginnt
bei 690 (Vitrinenseite) und 1.590 (Shop). Vecom liegt danach weiter knapp
darunter — das Argument „offene Preise, und günstiger als der Einzige, der
seine zeigt" bleibt stehen.

Die Einführungsphase (`Einfuehrung::anwenden`, +20 % nach den ersten zehn
abgeschlossenen Kunden) ist unberührt: Ihre Sperre wird nicht gesetzt. Diese
Erhöhung verschiebt den Ausgangspunkt, sie nimmt den späteren Schritt nicht
vorweg.

NACHGEZOGEN, WEIL SONST ZWEI ZAHLEN IM UMLAUF WÄREN: die Rückfallwerte im
Listenblatt der Preisseite, `preise.f1p` bis `f4p`, die Kurzantwort und die
Meta-Beschreibung der Preisseite („fünf Seiten 525–650 €"), die FAQ-Antwort
und das FAQ-Schema („ab 325 €") sowie `minPrice` in den strukturierten Daten.
Die Live-Zahlen kommen aus der Datenbank; diese hier sind der Rückfall, wenn
`preise-daten.php` nicht antwortet — sie müssen bei jeder Preisrunde mit.

NACHTRAG 13.09.2026, weil diese Liste selbst unvollständig war: Es gibt zwei
öffentliche Seiten, die Preise nennen und **nicht** an `preise-daten.php`
hängen — `explainer.html` (Zeile über `expl.s8a`, in allen drei Wörterbüchern)
und `tiktok.html` (Clip 2, hart im HTML). Beide standen einen Tag lang auf
275 €, während überall sonst 325 € stand. Die vollständige Liste für jede
Preisrunde ist deshalb: Listenblatt, `f1p`–`f4p`, `preise.kurz`,
`preise.metaDesc`, FAQ-Antwort und FAQ-Schema, `minPrice`, **`expl.s8a` in
i18n-de/it/en, `tiktok.html`**. Die Probe danach ist ein Griff:
`grep -rn "275\|499\|899" --include="*.html" --include="*.js" .` — findet jede
Zahl, die zurückgeblieben ist.

### Der Vecom-Standard für Kundenseiten (12.09.2026)

Uwe hat den Master-Standard geschickt: die zentrale Produktions-, Einstufungs-
und Qualitätslogik für **alle** Kundenwebseiten. Kein Auftrag, sondern eine
Grundlage — also gehört sie dorthin, wo beim Bauen hineingesehen wird, und
nicht in einen Chatverlauf.

**`VECOM-STANDARD.md`** im Repository, mit einem Zeiger aus `CLAUDE.md`. Er
liegt hier und nicht im Kundenordner, weil er allen Kundenordnern gemeinsam
ist; ein Standard, der in jedem Projekt neu abgeschrieben wird, ist nach dem
dritten Projekt drei Standards. Was darin trägt:

- **Die Reihenfolge** Bedarf → Geschäftsziel → Nutzer → Inhalt → Geschichte →
  Emotion → Erlebnis → **Technik**. Technik zuletzt, immer. Probe vor der
  ersten Zeile Code: Nennt die Antwort auf „Warum so?" eine Technik, ist die
  Reihenfolge verletzt.
- **Erfinde niemals Unternehmensinformationen.** Jede Information trägt eine
  Stufe: `CONFIRMED`, `INFERRED`, `MISSING` oder `FORBIDDEN_TO_INVENT`. Die
  Sperrliste (Telefonnummern, Adressen, Preise, Bewertungen, Zertifikate,
  Auszeichnungen, Referenzen, Mitarbeiter, Kundenlogos, Firmengeschichte,
  Rechtsangaben, Produktversprechen, Garantien, Leistungswerte) kennt genau
  zwei erlaubte Antworten: fragen oder die Sektion weglassen.
- **Das Manifest** (`VECOM_PROJECT_MANIFEST`) ist die einzige Wahrheit.
  Widersprechen sich Seite und Manifest, ist die Seite falsch.
- **Umfangssperre.** Nicht Vereinbartes wird nie still dazugebaut — es ist
  eine Änderungsanfrage mit Preis und Freigabe.
- **Klassen A–G und X**, **Module WEB bis CARE**: vor dem Bauen festgelegt.
  Eine Klasse höher als nötig ist ein Fehler, nicht Ehrgeiz.
- **Stufe 0 ist der Vertrag.** Ohne WebGL, ohne Effekte, auf dem langsamsten
  Gerät trägt die Seite Inhalt, Navigation, Marke, die wichtigste Handlung und
  den Kontakt. Verliert Stufe 0 eines davon, ist die Seite kaputt, nicht
  reduziert.
- **„Es läuft" ist die erste Fassung, nicht die letzte** — und steht nicht auf
  der Fertig-Liste.

EHRLICH DAZU: Vier Abschnitte des Originals stehen dort nur als Überschrift —
die Schichten 01–20, die Fassungen 01–08, die Freigabetore 01–09 und der
45-Schritt-Startbefehl. Ihr Wortlaut lag beim Festhalten nicht mehr vor, und
erfundene Zwischenfassungen wären genau der Fehler, den Abschnitt 2 des
Standards verbietet. Sie stehen am Ende der Datei unter „Was noch fehlt" und
werden eingesetzt, sobald der Originaltext wieder vorliegt. Die Abschnitte
1–10 und 14 gelten vollständig und tragen den Standard allein schon.

MITGEGANGEN: Der Deploy schließt jetzt `.md` als Endung aus statt drei Dateinamen
einzeln (`ftp-deploy.yml`). `VECOM-STANDARD.md` wäre sonst auf dem Webspace
gelandet — gesperrt durch die `.htaccess`, aber oben. Was gesperrt ist, muss gar
nicht erst hochgeladen werden, und eine Liste aus Namen wird bei der nächsten
Notizdatei wieder vergessen.

### Aufgeräumt: ein Klon, eine Abrissliste, eine Textquelle (12.09.2026)

Uwe: „räume auf das anständig das neuste deployed wird" und „lösche alte
Klons soll nur ein sein wo immer aktualisiert."

**Ein Klon.** Auf dem Mac lagen zwei Arbeitskopien — eine vom 08.09. auf
735e075, eine frische. Die alte ist gelöscht, die frische heißt jetzt
`website/` und steht auf dem neuesten Commit. Mitgegangen sind 153 MB
Transportreste aus früheren Sitzungen (`_to_delete/`, `_transfer/`, `_alt/`
und acht `.tgz`). Der Hilfsklon in der Arbeitsumgebung, über den bisher
veröffentlicht wurde, ist nicht mehr nötig: Mit Löschrechten im verbundenen
Ordner räumt git seine eigenen Sperrdateien wieder weg, und `pull`, `commit`
und `push` laufen dort ohne Umweg.

**Die Abrissliste (`ftp-deploy.yml`).** `mirror --reverse` lädt hoch und
löscht nie — wer eine Datei aus dem Repository nimmt, nimmt sie nicht vom
Webspace. Zwölf solcher Leichen lagen dort, gut 2 MB: `auftakt.mp4` und
`.webm` (1,16 MB für einen Film, den `site-world.js` seit dem Umbau gar
nicht mehr abspielt — der Kameraflug hat ihn ersetzt), `theme.css`,
`thema.js`, `legal-i18n.js`, `world-i18n.js`, fünf Bilder und `pakete.html`.
Unter dem `mirror` steht jetzt eine Liste, die bei jedem Deploy entfernt,
was dort eingetragen ist. Wer künftig eine Datei aus dem Repository nimmt,
trägt sie dort ein — das ist der einzige Weg, auf dem sie wirklich
verschwindet.

**Eine Textquelle.** `tools/sprechertext.mjs` las bis heute
`assets/js/i18n-data.js` — eine Sammelfassung aller drei Sprachen, die die
Seite längst nicht mehr benutzt (sie lädt `i18n-it/de/en.js`) und die nicht
einmal im Repository lag: Aus einem frischen Klon lief das Werkzeug nicht.
Es war genau das, wovor sein eigener Kommentar warnt — ein zweiter Ort, an
dem derselbe Satz veralten kann. Jetzt liest es dieselbe Datei wie die
Seite. Die Sammelfassung ist weg, die vier veralteten README-Stellen sind
nachgezogen.

NEBENBEI GEFUNDEN: Der Abschnitt „Bestand" in dieser Datei nannte noch die
drei Pakete (499 / 899 / 1.499 €) als Bestand — Wochen nachdem sie
abgeschafft wurden, und in genau dem Abschnitt, der sagt, was nicht
gebrochen werden darf. Korrigiert.

### Die Hausregeln tragen jetzt den Standard (12.09.2026)

Uwe: „Passe entsprechend auch die Hausregel an."

`Standard::VORGABE` ist der Text, der an jedem Briefing hängt — das, was
Claude beim Bauen einer Kundenseite als Erstes liest. Er hatte gute,
teuer bezahlte Regeln (Sprachen, WhatsApp statt Formular, Öffnungszeiten
im Fuß, Ladezeit als Versprechen), aber nichts von dem, was der
Master-Standard vom 12.09.2026 verlangt. Sechs Abschnitte sind vorn
dazugekommen, zwei in der Mitte, zwei am Schluss:

- **Die Reihenfolge** — Bedarf bis Technik, Technik zuletzt, mit der Probe
  „Nennt die Antwort auf *Warum so?* eine Technik, ist sie verletzt."
- **Erfinde niemals Unternehmensinformationen** — vier Stufen (bestätigt,
  geschlossen, fehlt, gesperrt) und die Sperrliste mit genau zwei erlaubten
  Antworten: fragen oder die Sektion weglassen.
- **Manifest**, **Umfangssperre**, **Klasse und Module**.
- **Stufe 0 als Vertrag** und die Frage „was passiert, wenn es nicht läuft?"
- **Bewegung und Ton** — Ton startet stumm, Sensoren erst nach Zustimmung.
- **Kein KI-Aussehen** (in „Was nie vorkommt" eingearbeitet), **erster
  Bildschirm zuerst**, **fertig ist nicht „es läuft"** mit der Fertig-Liste.

Aus 5.400 werden 11.000 Zeichen; die Obergrenze liegt bei 40.000.

WICHTIG UND LEICHT ZU ÜBERSEHEN: `Standard::text()` liefert die Vorgabe nur,
solange in `settings.werkstatt_standard` nichts steht. Wer einmal eine eigene
Fassung gespeichert hat, bekommt weiter seine — eine geänderte Vorgabe
erreicht ihn nie. Deshalb ist der Text zusätzlich zum Einfügen in die
Verwaltung herausgegeben worden (Vecom-Standard → Hausregeln).

### Die Abrisskiste lag offen im Netz (12.09.2026)

Uwe: „gh repo clone vecom2709/vecom-design" — und beim Klonen fiel auf, dass
`_to_delete/` im Repository liegt, obwohl `cc760e5` es genau dort
herausgenommen haben wollte.

**Warum die Ignorierregel nichts genützt hat.** `.gitignore` wirkt nur auf
Dateien, die git noch nicht kennt. `_to_delete/integrationen.php.alt` und
`_to_delete/werkstatt.patch` waren zum Zeitpunkt von `cc760e5` bereits
getrackt — die Zeile wurde eingetragen, die beiden Dateien blieben
versioniert, und niemandem fiel etwas auf, weil `git status` schwieg.

**Sie waren live lesbar.** Nachgemessen, nicht vermutet:
`https://vecom-design.it/_to_delete/werkstatt.patch` lieferte den kompletten
Werkstatt-Umbau als Diff aus, `integrationen.php.alt` den Quelltext der
Verwaltungsseite — beide als Klartext, weil `.patch` und `.alt` kein PHP sind
und Apache sie nicht ausführt. Keine Zugangsdaten darin, nur Platzhalter
(`sk_test_…`, `whsec_…`); wohl aber die Bauart der Verwaltung: Tat-Namen wie
`stripe_speichern`, das CSRF-Feld, die Formularstruktur. Das ist der Stoff,
mit dem ein Angriff anfängt, nicht der, mit dem er endet.

**Drei Stellen, weil eine nicht reicht.** `git rm --cached` nimmt die Dateien
aus der Versionsverwaltung (auf der Platte bleiben sie). Die Abrissliste in
`ftp-deploy.yml` bekommt `rm -rf $DIR/_to_delete` — der Deploy löscht nie von
selbst, und die alten Kopien liegen bereits oben. Die `.htaccess` sperrt
zusätzlich, weil zwischen Commit und nächstem Deploy Zeit liegt: einmal der
Ordner (`RewriteRule ^_to_delete/ - [R=404,L]`), einmal die Endungen
`.alt .bak .orig .patch .diff .save .swp .sql .log` — die fangen auch die
nächste Datei dieser Art, die irgendwo anders liegt.

Mit einem echten Apache 2.4.58 geprüft, nicht mit `php -S`: die beiden
Dateien 403, `/_to_delete/` 404, eine `.bak`-Gegenprobe außerhalb des Ordners
403, `/index.html` und `/e/ANNA3CU` weiter 200.

WICHTIG UND LEICHT ZU ÜBERSEHEN: Dass die beiden Dateien 403 melden und nicht
404, ist die bekannte `<FilesMatch>`-Falle aus den Hausregeln — Apache wendet
den Block nach der RewriteRule an und gewinnt. Hier stört das nicht, beides
ist zu. Wer aber je einen 404 erzwingen will, kommt mit einer RewriteRule
gegen einen `<FilesMatch>`-Block nicht an.

NEBENBEI: `git rm --cached` allein hätte nichts gebracht. Ohne den Eintrag in
der Abrissliste wären die Dateien für immer auf dem Webspace geblieben — das
ist genau die Falle, für die die Liste am selben Tag gebaut wurde.

### Zwei Preise von gestern und eine zweite Abrisskiste (13.09.2026)

Uwe: „starte wo du gestern aufgehört hattest." Erster Griff war deshalb die
Nachlese der Preisrunde vom Vortag (+15 %, Migration 044) — und dabei fiel
gleich der nächste offene Ordner auf.

**Die Preisrunde war fast vollständig.** Nachgerechnet statt nachgelesen: Die
vier Beispielspannen im HTML (`preise.f1p`–`f4p`, 325–400 / 525–650 /
800–1.000 / 1.200–1.550 €) kommen genau heraus, wenn man die Bausteinpreise aus
Migration 044 durch `Baukasten::spanne()` schickt. Ebenso stimmen Listenblatt,
`preise.kurz`, `preise.metaDesc`, die FAQ-Antwort und `minPrice` in den
strukturierten Daten. Die Rückfallwerte und die Datenbank sagen also dasselbe.

**Zwei Stellen nannten weiter 275 €** — die alte Untergrenze, also 50 € zu
wenig, öffentlich und auf keiner der geprüften Seiten:

- `expl.s8a` in allen drei Wörterbüchern — die Preiszeile von `explainer.html`.
- `tiktok.html`, Clip 2, hart im HTML statt über das Wörterbuch.

Beide Seiten stehen nicht in der Sitemap, tragen aber auch kein `noindex`: Sie
sind erreichbar, verlinkbar und indexierbar. Der Merkposten „bei jeder
Preisrunde ziehen die Rückfallwerte mit" nennt sie bisher nicht; er zählt nur
Start- und Preisseite auf. Das ist die eigentliche Lücke, nicht die Zahl.

**Und _baukasten.html lag offen im Netz.** Dieselbe Sorte Fund wie am Vortag,
eine Woche älter: `_baukasten.html` und `_baukasten_ende.html`, gespeicherte
Ansichten von `/app/baukasten`, mit Commit `8f989a1` (08.09.2026) versehentlich
mitgenommen. Nachgemessen, nicht vermutet: `https://vecom-design.it/_baukasten.html`
liefert die Verwaltungsseite aus — Menü mit allen Arbeitszahlen, die Tat-Namen
`bausteine_speichern` und `preise_anheben`, das CSRF-Feld und die interne
Preistabelle samt geplanter Erhöhung. Kein Passwort darin, und `noindex` im
Kopf hält Google fern; wer die Adresse hat, liest trotzdem mit.

Warum die Sperren vom Vortag nicht gegriffen haben: Die Endungsliste
(`.alt .bak .patch …`) kann `.html` nicht fassen, das ist die Endung der Seite
selbst. Gefasst wird deshalb der Unterstrich am Anfang — so heißt eine
beiseitegelegte Ansicht, und so wird die nächste auch heißen. Wieder an drei
Stellen: aus der Versionsverwaltung heraus, `--exclude '^_[^/]*$'` plus zwei
`rm -f` in der Abrissliste, und `RewriteRule ^_[^/]*$ - [R=404,L]` in der
`.htaccess`. Dazu `/_*.html` in der `.gitignore`.

WICHTIG UND LEICHT ZU ÜBERSEHEN: Die Regel gilt nur für die Wurzel
(`^_[^/]*$`, kein Schrägstrich im Rest). `richtungen/_rahmen.css` ist ein
echtes Stilblatt — eine Sperre auf „Dateiname beginnt mit Unterstrich" hätte
die Richtungsseiten still ohne Gestaltung gelassen.

Mit echtem Apache 2.4.58 gemessen (nicht mit `php -S`, und über https, weil die
erste Regel der Datei sonst mit 301 antwortet): `/_baukasten.html`,
`/_baukasten_ende.html` und eine neue `/_probe.html` je 404;
`/richtungen/_rahmen.css` und eine Gegenprobe `/richtungen/_probe.css` 200;
`/_to_delete/x.patch` 403 und `/README.md` 403 wie bisher; `/index.html`,
`/e/ANNA3CU`, `/prezzi.html`, `/de/preise.html`, `/404.html` 200.

### Der Prüfstand fand, was die Migrationen nicht erreichen (13.09.2026)

Uwe wollte die beiden Ansichten `/app/anfragen` und `/app/bedarf` an echten Daten
gegengeprüft haben — sie standen seit dem 03.09. leer. Also erst ein Prüfstand:
PHP 8.4, MariaDB, leere Datenbank, `migrate.php`, Startdaten, Admin, und dann ein
kompletter Durchlauf durch `bedarf.php` als Kunde (Trattoria da Nino, vier Zwecke,
zwei Sprachen; danach Pasticceria Ingrao mit allen acht Fragen).

**Der schwerste Fund hat nichts mit den Ansichten zu tun: Startdaten werden NACH
den Migrationen gesät.** `Baukasten::sicherstellen()` hängt daran, dass die Tabelle
leer ist — leer ist sie erst, wenn die Migrationen sie angelegt haben. Migration 044
(+15 %) traf deshalb auf null Zeilen, und danach säte `standardbausteine.json` die
alten Preise ein. Gemessen: eine frisch eingerichtete Seite rechnete 299 statt
345 € Grundgerüst und hätte auf der Preisseite 275 – 350 statt 325 – 400 € gezeigt.

Dasselbe bei den Paketen, und dort sichtbarer: `Einrichtung::pakete()` schrieb für
jede Zeile `oeffentlich => 1`, die Migrationen 025 und 043 hatten die drei
Website-Pakete kurz zuvor unsichtbar gesetzt — auf einer leeren Tabelle. Gemessen:
`pakete-daten.php` lieferte nach der Einrichtung **Starter 499, Business 899 und
Premium 1.499** aus. Die drei am 12.09. abgeschafften Preiskarten wären auf einer
neu eingerichteten Seite von selbst zurückgekehrt.

Die bestehende Einrichtung war nie betroffen — dort liefen die Migrationen auf
gefüllte Tabellen. Aufgefallen wäre es also erst dem nächsten, der eine eigene
Einrichtung bekommt, und das ist ausgerechnet der Plan mit dem
Verwaltungssystem-Master-Prompt. Behoben, indem die Startdaten den heutigen Stand
tragen: Preise in `standardbausteine.json`, Sichtbarkeit als neues Feld
`oeffentlich` in `standardpakete.json`, gelesen mit `?? 1`.

REGEL DARAUS, für jede künftige Preis- oder Sichtbarkeitsrunde: Die Migration ist
für die bestehende Einrichtung, die Startdatei für die nächste neue. Wer nur eines
von beiden ändert, hat zwei Wahrheiten. Die Kette prüft das jetzt (Abschnitt
„Startdaten nach den Migrationen").

**In den Ansichten selbst drei Fehler, alle erst am Bildschirm sichtbar:**

1. `/app/anfragen/<id>` riet bei einer Anfrage AUS DEM KONFIGURATOR: „Der Kunde hat
   noch nicht gesagt, was er braucht — Konfigurator schicken." Direkt über den
   Antworten, die er dort gegeben hatte. Der Block erscheint jetzt nur ohne Bedarf.
2. Darunter stand „Oder direkt ein Festpreis-Paket" mit Starter 499 / Business 899 /
   Premium 1.499 zur Auswahl. Ein Klick hätte eine Bestellung über einen Preis
   angelegt, den es nicht mehr gibt — und der Kunde hätte ihn schriftlich. Die
   Auswahl verlangt jetzt `oeffentlich = 1`; damit ist der Block heute leer und
   verschwindet, kommt aber von selbst zurück, wenn wieder ein Festpreis-Paket auf
   der Seite steht.
3. Im Briefing zum Bauen standen „BESTAND" und „TERMIN" als nackte Überschriften,
   sobald die Fragen offen geblieben waren — und bei einem Bedarf ohne eine einzige
   Antwort stand das ganze Briefing da und zählte unter „das ist kalkuliert und
   bezahlt" Grundgerüst, Texte und Bilder auf. Jetzt dieselbe Schwelle wie beim
   Preis (`Baukasten::genugGesagt`), und offene Fragen werden ausdrücklich benannt
   („nicht annehmen, dass neu gebaut wird", „keine Eile erfinden").

Was gut war und hier stehen soll, damit es nicht aus Versehen geändert wird: Die
Zusammenfassung in der Verwaltung ist wirklich auf Deutsch, während dieselbe Angabe
auf der Kundenseite in seiner Sprache steht; der Vorschlagspreis (911 € im
Durchlauf) ist die Summe der Positionsmitten und stimmt mit dem Angebot; das
Briefing benennt bei vollständigen Antworten sogar, was NICHT gebaut werden darf.

Die Kette läuft mit 904 Prüfungen durch (19 neue).

### Der ganze Kundenweg, einmal wirklich gefahren (13.09.2026)

Uwe: „prüfe das alles sauber funktioniert gesamter kunden prozess die logik
dahinter und alles". Also nicht gelesen, sondern gefahren — über HTTP, über die
echten Seiten und Formulare, von der leeren Datenbank bis zum Onlinegang:

Konfigurator (Sprachtor, vier Schritte, Spanne) → Absenden → Anfrage und
Kundenakte → Preisvorschlag in der Verwaltung → Angebot aus dem Bedarf →
verschickt → Kundenseite mit PDF → Annahme **nur mit beiden Haken** → Bestellung
mit zwei Raten → Anzahlung gebucht → Projekt entsteht → Fragebogen-Einladung →
sechs Schritte ausgefüllt → Briefing über `werkstatt.php` (22.947 Zeichen) →
Vorschau eingetragen → freigegeben → Abnahme freigeschaltet → Kunde drückt
„Passt so" → Restzahlung angefordert → bezahlt → zwei Belege → online.

Damit der Versand mitprüfbar ist, lief ein **Brevo-Doppelgänger** auf
127.0.0.1: `Mail::zugang()` erlaubt dafür `brevo.api` in der Konfiguration
(„nur zum Durchtesten umstellbar" — genau dafür war es gedacht). Zehn Mails
gingen in der richtigen Reihenfolge hinaus, mit den richtigen Anhängen
(Auftragsbestätigung, Widerrufsformular, beide Zahlungsbelege), keine doppelt,
26 Einträge in der Prüfspur, kein einziger Fehlschlag.

**Ein echter Fehler, und ein teurer: Eine Zwischenmeldung stufte den Kunden
zurück.** Hatte der Kunde im Fragebogen einen Posten *abgewählt*, der im Angebot
steht, meldete `Vorgang` „Mehrbedarf klären" — richtig — und setzte dabei die
Stufe fest auf `arbeit`. Die Kundenseite liest dieselbe Stufe. Auf ihr stand
dann weiter *„Ich baue deine Seite"*, Schritt 4 von 7, obwohl Vorschau UND
Abnahme ausdrücklich freigeschaltet waren und die E-Mail „Deine Seite ist fertig
— schau sie dir an" schon beim Kunden lag. Der Knopf „Passt so" erschien nie.

Damit war die Abnahme nicht erreichbar — und an ihr hängt alles Weitere: keine
Abnahme, keine Restzahlungs-Anfrage, kein Onlinegang. Ein Haken, den ein Kunde
wegklickt, hätte den Vorgang stillgelegt, ohne dass irgendwo ein Fehler zu sehen
gewesen wäre. Die Meldung für Uwe war ja da; nur zeigte sie in die falsche
Richtung.

Behoben mit `Vorgang::nichtZurueck()`: Die Meldung bleibt, die Stufe nicht — ist
das Projekt bei Vorschau, Freigabe oder Online, gilt der Projektstand. Dasselbe
gilt für den unbezahlten Nachtrag, der die Stufe genauso festgesetzt hat.

WICHTIG UND LEICHT ZU ÜBERSEHEN, für jede künftige Meldung in `stufeBestimmen`:
Der zweite Parameter von `setzen()` ist nicht nur Kosmetik für Uwes Liste — er
ist auch die Stufe, die der Kunde sieht. Eine Meldung, die eine frühere Stufe
setzt, nimmt dem Kunden den Knopf weg, der an dieser Stufe hängt.

DAZU EINE LEHRE ÜBER PRÜFUNGEN: Die erste Fassung der neuen Kettenprüfung hielt
auch dann, wenn der Fehler wieder eingebaut wurde — `Umfang::mehrbedarf()`
braucht ein angenommenes Angebot am Projekt und einen gefüllten Baukasten, und
beides fehlte an dieser Stelle der Kette. Eine Prüfung, die nicht rot wird, wenn
der Fehler zurückkommt, ist keine. Deshalb steht jetzt eine eigene Prüfung davor
(„der Prüffall erzeugt wirklich einen Mehrbedarf"), und die Gegenprobe ist
dokumentiert: mit eingebautem Fehler reißen genau zwei Prüfungen, ohne ihn
halten alle 913.

### Bezahlt heißt jetzt auch in der Verwaltung bezahlt (13.09.2026)

Uwe: „sobald ein Kunde bezahlt hat soll auch automatisch in der Verwaltung
bezahlt stehen — teste den ganzen Ablauf komplett realistisch durch."

**Was passiert war.** Ein Kunde zahlte mit Karte, bekam nie eine
Bestätigung. Bei Stripe lag das Geld, hier stand die Rate offen. Dazwischen
liegt genau ein Aufruf — der Webhook —, und der kam nie an: Im Livemodus
war kein Endpunkt eingetragen. Ohne die Buchung passiert gar nichts: kein
Beleg, keine Auftragsbestätigung, kein Fragebogen, kein Projekt. Gemerkt
hat es niemand. Gemerkt hat es der Kunde.

**Der Rückweg.** Ein Webhook ist ein Anruf, den der *andere* macht. Er kann
ausfallen, falsch unterschrieben sein oder ins Leere gehen, und in allen
drei Fällen sieht es hier gleich aus: Stille. Also fragen wir selbst nach.

- `045_zahlungsabgleich.sql`: `payments.provider_sitzung` — die Nummer der
  Bezahlseite (`cs_…`). Ohne sie ist nicht zu sagen, welche Seite zu welcher
  Rate gehörte. Alle fünf Stellen, die eine Bezahlseite erzeugen, schreiben
  sie jetzt mit (`buchen.php`, `app/index.php`, `Nachricht`, `Abo`,
  `Mahnung`).
- `StripeAnbieter::sitzungLesen()` fragt eine Bezahlseite ab. `anfrage()`
  kann dafür jetzt auch GET — mit Feldern in der Adresse statt im Rumpf,
  weil Stripe ein GET mit Rumpf je nach Tageslaune ablehnt.
- `Cron::zahlungenAbgleichen()` geht die offenen Raten durch und bucht, was
  bei Stripe bezahlt ist. Drei Minuten Schonfrist, damit der Webhook seinen
  Vorrang behält; höchstens 25 Raten je Lauf; nach 35 Tagen ist die
  Bezahlseite bei Stripe ohnehin weg. Im Cron steht der Abgleich **vor**
  `zahllinks` — wer in der letzten Minute vor Ablauf zahlt, soll gebucht
  werden und nicht auf „ausstehend" zurückfallen.
- Gebucht wird durch dieselbe Tür wie beim Webhook,
  `Events::zahlungBestaetigen()`. Die steigt bei `status = 'bezahlt'` sofort
  wieder aus — doppelt buchen ist damit unmöglich, auch wenn Webhook und
  Abgleich sich überholen.
- **Und es meldet sich.** Wenn der Abgleich bucht, hat der Webhook versagt.
  Das ist der eigentliche Befund, nicht die Nebensache: Sonst fängt der
  Abgleich jeden Kunden auf, und niemand erfährt, warum jede Bestätigung
  Minuten zu spät kommt.

**Durchgespielt.** `kette.php` hat einen neuen Abschnitt 48 mit 32
Prüfungen, und der Abgleich nimmt dafür einen Anbieter entgegen — ein
Rückweg, der sich nur im Echtbetrieb prüfen lässt, wird nie geprüft, und
genau ungeprüft war der Webhook, als er ausfiel. Geprüft werden: der Fall,
der wirklich passiert ist (Rate wird gebucht, Projekt und Beleg entstehen,
Bestellung steht auf bezahlt); zweiter Lauf bucht nicht doppelt; offene
Seite bleibt offen; abgelaufene verliert ihre Nummer und wird nicht mehr
gefragt; ein Fehler bei einer Rate hält die anderen nicht auf; die
Schonfrist; keine Nummer heißt keine Abfrage; ohne Schlüssel passiert
nichts; und die Reihenfolge im Cron.

**945 von 945 Prüfungen halten** — die ganze Kette von der Bestellung bis
zum Abschluss, mit echter Datenbank und allen Migrationen von vorn.

DAZU EHRLICH: Eine echte Kartenzahlung wurde dabei nicht ausgelöst — eine
gebuchte Zahlung erzeugt einen Beleg mit Nummer, der zehn Jahre bleibt
(Art. 2220 Codice civile). Die Feldnamen, auf die der Abgleich zugreift
(`payment_status`, `status`, `payment_intent`, `amount_total`), wurden
stattdessen gegen echte Checkout-Sitzungen im Stripe-Testkonto geprüft und
stimmen.

OFFEN BLEIBT: Der Live-Webhook bei Stripe. Der Abgleich fängt den Kunden
auf, aber er ist der langsame Weg — die Bestätigung kommt Minuten später
statt sofort.
### Der Showroom geht ans Netz (13.09.2026)

Uwe: „Jetzt schau das der showroom der wir erstellt hatten live geht". Der
begehbare Raum lag bis heute nur in Blender und in einer lokalen Vorschau unter
`3d-produktion/vorschau/`. Jetzt liegt er als `showroom.html` in der Wurzel und
lädt `assets/3d/showroom.glb` (390 KB) plus drei Texturkarten aus
`assets/img/3d/`.

WARUM DIE UNKOMPRIMIERTE GLB UND NICHT DRACO: Die Draco-Fassung ist roh kleiner
(125 KB statt 390 KB), aber über die Leitung nur 4 KB besser — denn gezippt sind
von der einfachen Fassung 24 KB übrig, ein Faktor sechzehn, weil ein GLB im Kern
Zahlenfelder sind. Dafür bräuchte Draco einen Decoder von rund 200 KB im
Browser. Also macht der Server die Arbeit: `AddType model/gltf-binary` (sonst
liefert der Webspace `application/octet-stream` aus und die Kompression greift
nicht) und ein `mod_deflate`-Block in der `.htaccess`. `glb` steht jetzt auch in
der Regel für den langen Zwischenspeicher.

Gemessen mit einem echten Apache 2.4.58, nicht mit `php -S`: `/showroom.html`
200, `/assets/3d/showroom.glb` 200 mit `Content-Encoding: gzip`,
`Content-Type: model/gltf-binary`, `Content-Length: 26122` und
`Cache-Control: public, max-age=2592000`. Die bestehenden Regeln bleiben heil:
`/index.html` 200, `/e/ANNA3CU` 200, `/_baukasten.html` 404, `/README.md` 403,
`/de/preise.html` 200.

VIER FEHLER, DIE ERST DAS MESSEN ZEIGTE:

1. Die Importkarte wurde „von einem null blockiert" — ein bloßes
   `assets/vendor/three/...` ist in einer Importkarte ungültig. Nur absolute
   Pfade (`/assets/...`) zählen.
2. Im Hochformat stand das Modell halb außerhalb des Bildes. Behoben mit einer
   Rechnung, die aus dem Seitenverhältnis eine Weitung ableitet und sie auf
   Blickwinkel *und* Kameraabstand verteilt — nur Blickwinkel verzerrt, nur
   Abstand macht das Modell winzig.
3. Der Schleier unter dem Text lief im Hochformat noch waagerecht, also quer zur
   Textrichtung, und der Text kämpfte gegen das helle V dahinter. Unter 760 px
   läuft er jetzt senkrecht.
4. Die Überschrift saß hinter dem Zurück-Link. Darum `padding-top:17vh` und
   `align-items:flex-start` im Hochformat.

`3d-produktion/` steht jetzt in der `.gitignore`: über 50 MB Blender-Dateien,
Renderings und Rohtexturen, von denen nichts auf den Webspace gehört.

OFFEN UND ABSICHTLICH OFFEN: Die Seite ist einsprachig deutsch und von nirgends
verlinkt — sie steht nicht in `build.mjs`' Seitenliste und nicht in der Sitemap.
Erst soll Uwe sie auf einem echten Bildschirm mit echter Grafikkarte sehen;
hier ist sie nur unter SwiftShader gemessen, also ohne GPU. Danach zu
entscheiden: eigene Seite oder Ersatz für den 3D-Auftakt der Startseite, und ob
es sie auf Italienisch und Englisch gibt.

### Der Showroom ist live — und das Hochformat einmal richtig (13.09.2026)

Gepusht, ausgeliefert, nachgemessen. `https://vecom-design.it/showroom.html`
antwortet mit 200 und ist Byte für Byte die Datei aus dem Repository; die GLB
kommt als `model/gltf-binary` gezippt an, 26.122 Byte auf der Leitung statt
389.640 roh, und die heruntergeladene Datei hat dieselbe Prüfsumme wie die
lokale. Alle acht Three.js-Zusatzmodule und alle drei Texturkarten: 200, jede
mit der erwarteten Größe. Die alten Sperren halten unverändert
(`_baukasten.html` 404, `README.md` und `PROJEKT.md` 403, `/pakete.html` 301,
`/cockpit/` 401).

**Das Hochformat war noch nicht gut, und der erste Ansatz war der falsche.**
Text oben, Raum darunter — das klang richtig und ging nicht auf: Die Marke saß
genau hinter den Knöpfen, und unter dem Boden blieb ein schwarzes Drittel, weil
der Raum dort schlicht zu Ende ist. Drei Versuche mit Blickwinkel, Kameraabstand
und angehobenem Blickziel haben das Loch nur verschoben, nie geschlossen.

Umgedreht geht es auf: **Raum oben, Text unten.** Der Raum bekommt die Hälfte,
in der er etwas zu zeigen hat; der Text die, in der ohnehin nur dunkler Boden
liegt. Der Schleier läuft von unten nach oben, das schwarze Drittel ist kein
Loch mehr, sondern der Grund, auf dem die Zeilen stehen. Dazu sinkt im
Hochformat das Blickziel (`BLICK_SENKUNG`), damit die Marke in die obere Hälfte
steigt, und die Kamera geht weniger weit zurück (0,10 statt 0,25) — sie muss ja
nur noch die obere Hälfte füllen. Auf allen fünf Bildern der Seite geprüft.

DAZU EINE SCHWELLE, DIE ERST DAS MESSEN ZEIGTE: Die Umstellung hing an
`max-width:760px`. Ein Tablet im Hochformat ist 768 px breit und fiel damit um
acht Pixel daneben — auf 768x1024 stand die Schrift quer über der Marke, oben
und unten je ein leeres Viertel. Es geht hier nicht um Breite, sondern um
Hochkant. Die Regel greift jetzt bei `max-width:760px` **oder**
`(max-width:1100px) and (orientation:portrait)`, im Skript entsprechend über
`innerHeight > innerWidth * 1.05`.

Gemessen wurde auf 390x844, 768x1024, 1440x900, 1200x800 und 844x390 (Telefon
quer), jeweils mit echtem WebGL unter SwiftShader, plus vier Scrollstände auf
dem Telefon: keine einzige Fehlermeldung in der Konsole, kein Querlauf.

WAS DABEI NOCH AUFFIEL, UND ZWAR AUF DEM ÜBERTRAGUNGSWEG: Beim Kopieren der
Texturen auf den Windows-Rechner hängte die Werkzeugkette jeder `.webp` einen
`C2PA`-Block von exakt 5.768 Byte an — gültige Dateien mit korrigierter
RIFF-Länge, aber eben andere. `.glb` und `.html` kamen unverändert an. Ohne
Prüfsummenvergleich hätten die beiden Arbeitskopien ab diesem Punkt verschiedene
Bäume gehabt, und niemand hätte gewusst, warum. Die Regel steht jetzt in den
Hausregeln: nach jeder Übertragung beide Seiten `git write-tree` und die
Baum-Hashes vergleichen.

OFFEN: Die Seite ist weiterhin einsprachig deutsch, von nirgends verlinkt und
nicht in der Sitemap — das bleibt liegen, bis Uwe sie auf einem Bildschirm mit
echter Grafikkarte gesehen und entschieden hat, ob sie den 3D-Auftakt der
Startseite ersetzt oder als eigene Seite danebensteht.

### Das Material des Kunden und die Website zum Mitnehmen (13.09.2026)

Uwe: „wenn Kunden Dateien hochgeladen haben soll dies auch in das Briefing
mit rein, dass Claude auch eigenständig dann die Logos, Schriften, Bilder
usw. sich holt … aber baue auch in die Verwaltung einen Bereich ein, wo man
vom Kunden die gesamten Daten der Webseite als ZIP herunterladen kann, den
man dem Kunden bereitstellen kann, entweder als E-Mail oder in seinem
persönlichen Dashboard."

Zwei Richtungen, die beide nicht funktionierten. Der Kunde lud sein Logo
hoch, und im Briefing stand bestenfalls der Dateiname — der Baumeister
wusste, dass es ein Logo gibt, und kam nicht daran. Also baute er eines
nach. Und die fertige Seite lag auf Netlify und im Mac-Ordner, nirgends
aber dort, wo man sie dem Kunden in die Hand geben kann.

**Eine Tabelle, zwei Sorten Datei.** `046_website_paket.sql` gibt `files`
eine Spalte `rolle`: `material` ist alles Bisherige (daher der Vorgabewert —
bestehende Zeilen bleiben unangetastet), `paket` ist die fertige Seite. Ohne
diese Trennung stünde das ZIP sofort in der Dateiliste der Kundenseite,
zwischen seinen eigenen Uploads.

**Der Weg zum Material.** Die Werkstatt kann jetzt `dateien` (was liegt da?)
und `datei` (gib sie her). `datei` antwortet an der JSON-Kopfzeile vorbei —
es kommen Bytes, kein JSON. Das Briefing führt beides zusammen: Es zählt
auf, was hochgeladen wurde, mit Nummer und Größe, und legt den fertigen
`curl`-Aufruf daneben. Dazu die Regel, auf die es ankommt: Logo, Schriften
und Bilder werden **benutzt, nicht nachgebaut**. Liegt nichts vor, sagt das
Briefing genau das und verlangt, danach zu fragen, statt Platzhalter zu
erfinden.

**Der Weg nach draußen.** `paket` nimmt die fertige Seite entgegen — nur
`.zip`, höchstens 200 MB, und an der Mengengrenze für Kundenmaterial vorbei.
In der Verwaltung steht sie unter „Website-Paket": herunterladen, dem Kunden
freigeben, per E-Mail schicken, Freigabe zurücknehmen.

**Warum die Freigabe am Projekt hängt und nicht an der Datei.** Freigegeben
wird kein Dateiname, sondern ein Zustand: „Der Kunde darf seine Seite
mitnehmen." Kommt eine neue Fassung dazu, bleibt die Freigabe stehen und er
bekommt die neue — genau das ist gemeint. Hinge sie an der Datei, müsste sie
bei jedem Nachliefern neu gesetzt werden, und irgendwann steht beim Kunden
ein Paket von vorletzter Woche.

**Die Mail trägt einen Link, kein ZIP im Anhang.** Acht Megabyte im Anhang
landen im Spam oder werden vom Empfänger abgewiesen. Der Link führt auf
seine Projektseite, wo das Paket ohnehin liegt — dreisprachig, mit einem
Satz dazu, dass es ihm gehört und er dafür nichts kündigen muss.

**Geprüft.** Abschnitt 49 der Kette, 45 Prüfungen: die Spalten, die Liste,
das Briefing mit und ohne Material, die vier Ablehnungen beim Hochladen
(keine Datei, keine `.zip`, abgebrochen, zu groß), die Sperre vor der
Freigabe, der Mailtext in allen drei Sprachen, das Nachliefern, das
Zurücknehmen. Dateien werden dabei direkt eingetragen statt hochgeladen —
`is_uploaded_file()` ist im CLI immer falsch, ein echter Upload also nicht
nachstellbar. **992 Prüfungen, alle grün.**

Zwei Fallen unterwegs: `mails` hat weder `typ` noch `body` — die Tabelle
hält nur fest, *dass* etwas rausging, der Anlass heißt `anlass`. Der
Mailtext wird deshalb dort geprüft, wo er entsteht. Und ohne Brevo-Schlüssel
meldet im Prüfstand *jeder* Versand `false`; gezählt wird deshalb der
Versuch, nicht der Rückgabewert.

### Dateien mit Bild, und ein Weg zwischen den Kunden (13.09.2026)

Uwe: „in der Verwaltung Dateien sollen auch mit Vorschau sein und auch
löschen können … teilweise ist alles sehr unübersichtlich … wenn ein Kunde
gerade bearbeitet wird und ein neuer Kunde reinkommt soll die Führung so sein,
dass man zwischen den Kunden wechseln kann und nicht mittendrin nur den einen
zeigt."

**Die Dateiliste bestand aus Dateinamen.** Ein Kunde schickt `IMG_4711.jpg`,
`logo_final_v3.png` und `scan.pdf`. Daraus geht nicht hervor, welches das Logo
ist, ob das Foto etwas taugt und ob der Scan schief liegt — man musste jede
Datei einzeln herunterladen, um das zu sehen. Jetzt trägt jede Zeile ein
Quadrat: die gerechnete Miniatur, oder bei allem anderen das Kürzel der Art.
Klick öffnet die Großansicht, Pfeiltasten blättern, Escape schließt.

**Warum die Vorschau nie das Original zeigt.** Hochgeladenes wird bewusst nur
als Anhang ausgeliefert, mit `nosniff` und einer CSP, die alles verbietet — der
Browser soll nichts davon ausführen. Eine Vorschau braucht das Gegenteil: sie
muss *inline* erscheinen. Also geht inline nur, was wir selbst erzeugt haben.
GD liest die Bildpunkte und schreibt eine neue Datei; was sonst noch drinsteckte
— ein Kommentar im EXIF-Block, eine zweite Datei hinter dem Bildende — überlebt
das nicht. Die Kette prüft genau das: Ein JPEG mit angehängter Nutzlast bleibt
ein gültiges Bild, die Vorschau daraus trägt die Nutzlast nicht mehr.

Zwei feste Kantenlängen (320 und 1600), abgelegt in `app/uploads/vorschau/` mit
eigener `.htaccess`. Frei wählbare Größen könnten in der Schleife den Webspace
vollschreiben. Durchsichtige Logos bekommen weißen Grund statt schwarzem, kleine
Bilder werden nicht aufgeblasen, kaputte still abgelehnt. Ohne GD steht in der
Liste ehrlich, dass es keine Vorschaubilder gibt — kein leerer Kasten, der so tut.

**Löschen geht jetzt auch zentral**, mit Rückfrage: `datei_weg` stand bisher
ohne jede Nachfrage im Projekt. Es ist endgültig, also wiegt es schwer. Gelöscht
wird beides, die Bytes und die gerechneten Vorschauen — sonst wächst der Ordner
mit jedem gelöschten Bild weiter. Dazu ein Filter nach Kunde und Projekt, und
nach dem Löschen landet man wieder in derselben gefilterten Liste.

**Die Leiste der offenen Vorgänge.** Wer an einem Kunden arbeitete, sah nur
diesen einen. Kam währenddessen eine Anfrage herein, merkte er es erst beim
nächsten Gang auf „Heute" — und mitten in einem Angebot geht niemand von selbst
zurück. Jetzt steht neben dem Vorgang eine schmale Spalte mit allem Offenen:
Name, Stufe, und ein Punkt bei jedem, der noch kein Wort gehört hat. Ein Klick
wechselt, der aktuelle bleibt markiert. Die Reihenfolge kommt aus derselben
Quelle wie „Heute" (`arbeitsliste()`) — zwei Meinungen darüber, was dringend
ist, wären schlimmer als keine. Am Handy ein Kasten fester Höhe, der in sich
scrollt; ohne die feste Höhe hätte er bei neun Vorgängen den geöffneten Kunden
neun Einträge nach unten geschoben.

**„Heute" hatte fünf Kästen, alle offen.** Bei zwölf Vorgängen scrollte man an
zwanzig Zeilen vorbei, in denen nichts zu tun war, um an die zu kommen, in denen
etwas zu tun war. „Du bist dran" steht offen; „Der Kunde ist dran" und „Läuft"
klappen zu, „Demnächst fällig" auch — es sei denn, es eilt etwas. Die Zahl neben
der Überschrift bleibt sichtbar: zugeklappt ist nicht verschwunden. Die
Störungen bleiben offen, weil eine Störung rufen soll, zeigen aber höchstens
drei; der Rest klappt auf.

**Drei Fehler, die nur das Rendern gefunden hat** — nicht das Linten, nicht die
Kette:

- `.dgross{display:flex}` überstimmt das `hidden`-Attribut. Die zugeklappte
  Großansicht lag unsichtbar über der ganzen Seite und fing jeden Klick ab; die
  Dateiliste wäre unbedienbar gewesen.
- Die allgemeine Regel `input,select{width:100%}` machte aus jedem Filter eine
  eigene Zeile — der Kasten sah aus wie ein Formular, das ausgefüllt werden will.
- `text-overflow: ellipsis` greift nicht auf einem Flex-Kasten. Lange Firmennamen
  in der Leiste brachen hart ab, ohne Auslassungspunkte.

**Geprüft.** Abschnitt 50, 60 Prüfungen — **1052 insgesamt, alle grün** —, dazu
die Seiten wirklich gerendert: angemeldet, Screenshots über 1440 und 390 Punkte,
Großansicht geöffnet und durchgeblättert, kein Querscrollen, keine
Konsolenfehler, keine PHP-Meldungen.

### Einfache Ansicht, Schubladen, ein Bildschirm je Kunde (13.09.2026)

Uwe: „Gesamte Verwaltung soll viel einfacher werden, dass selbst ein völliger
Anfänger damit umgehen kann — aber keine Kette darf abreißen, nichts darf
unübersichtlich sein, alles im besten Fall mit klarer Führung, wo manuell
eingegriffen werden soll."

**Erst gezählt, dann geurteilt.** 31 Menüpunkte · 54 Seiten · 130 Handgriffe ·
die Vorgangsseite allein mit 13 Blöcken und 29 Knöpfen · für *einen* Kunden
vier Seiten (Vorgang, Kundenakte, Bestellung, Projekt), die sich überschneiden ·
nur 12 von 130 Handgriffen fragen vorher nach, was sie anrichten. Das ist die
Ursache: nicht zu wenig Funktion, sondern zu viele gleichwertige Wege zur
selben Sache.

Neun Vorschläge, alle angenommen. Umgesetzt sind die ersten drei.

#### Der Schalter (Vorschlag 4)

`app/src/Modus.php` und ein Schalter ganz oben in den Einstellungen. **Einfach
ist die Vorgabe** — wer die volle Ansicht braucht, findet den Schalter; wer sie
nicht braucht, würde davon überfahren, bevor er ihn sucht. Auch bei einem
unsinnigen Wert in der Datenbank bleibt es einfach: Eine Oberfläche, die bei
einer Störung in den vollen Modus fällt, tut genau das Falsche.

Die Mechanik sind zwei Funktionen, `mehr_auf()` und `mehr_zu()`. Im einfachen
Modus wird daraus eine Schublade, im vollen geben sie **nichts** aus — dann
steht der Inhalt offen da wie vorher. Dazwischen darf alles stehen, was auch
ohne sie dort stünde; eine zugeklappte `<details>` schickt ihre Formularfelder
mit. Der Titel sagt, was drinliegt („Geld — Angebot, Zahlungen, Belege"), nicht
dass es etwas gibt („Erweitert"). Angewandt zuerst auf die Telefonseite, wo
Zahlen und Feinschliff nie der Grund sind, warum man sie aufschlägt.

#### Die Vorgangsseite (Vorschlag 2)

Aus 13 Blöcken wurden: der Handgriff oben, der Mehrbedarf (er kostet Geld, wenn
man ihn übersieht), „Auf einen Blick" — und fünf Schubladen. Zwei Regeln halten
die Kette:

1. **Die Schublade mit dem nächsten Handgriff steht offen.** Eine Führung, die
   auf etwas Unsichtbares zeigt, ist keine. Welcher Handgriff in welche
   Schublade gehört, steht als eine Liste da (`$schubladen`) — ein neuer wird
   an einer Stelle eingetragen, nicht an vieren. Die Kette prüft, dass kein
   Handgriff der Seite ohne Schublade dasteht.
2. **Die Zahl daneben sagt, was drinliegt** — „499,00 € offen", „3 ungelesen",
   „5 Dateien". Zugeklappt ist nicht weg, und man sieht von außen, ob sich das
   Aufziehen lohnt. (Erste Fassung zeigte an „Geld" eine nackte `1` — das sagt,
   dass gezählt wurde, aber nicht was.)

**Die Leiste oben schweigt auf dieser Seite.** Sie zeigte „Jetzt dran: Kunde E,
Fragebogen verschicken", die Seite zeigte Kunde D mit „Zahlungslink senden", und
daneben stand die Leiste mit neun weiteren. Drei Antworten auf „was mache ich
jetzt" sind schlechter als eine. Überall sonst bleibt sie.

#### Ein Bildschirm je Kunde (Vorschlag 1)

Kundenakte und Projekt liegen jetzt als Schubladen auf der Vorgangsseite —
**dieselben Blöcke, nicht nachgebaute.** Zwei verschiedene Wege dorthin, beide
aus demselben Grund gewählt:

- **Die Kundenakte** weiß, ob sie eingebettet ist (`$eingebettet`), und lässt
  dann weg, was oben schon steht: Bestellungen, Projekte, Zahlungen, die
  Nachricht, die Dateien, seine Seite, den Verlauf. Übrig bleibt, was es nur
  dort gibt: Kontakt, Betreuung, Domain und Hosting, interne Notizen, das
  Entfernen. Genau vier Weichen, von der Kette gezählt.
- **Die Projektseite** ließ sich so nicht aufteilen: Ihre Blöcke hängen an PHP,
  das davor läuft — Abfragen, Variablen, Bedingungen. Sie trägt deshalb Marken
  (`<!--teil:werkstatt-->`), läuft ganz durch wie auf ihrer eigenen Seite, und
  `Teile::ausHtml()` schneidet sie danach auseinander. Die Vorgangsseite nimmt
  sich die sieben Stücke, die ihr fehlen. Eine Fassung, ein Ort zum Ändern.

Die **Endmarke** ist die, auf die es ankommt: ohne sie landen die schließenden
Kästen der zweispaltigen Seite im letzten Abschnitt — zwei überzählige `</div>`
mitten in der Vorgangsseite. Die Kette prüft, dass es so viele Marken gibt wie
Blöcke plus eine.

Die Datenaufbereitung läuft für beide Wege durch **eine** Funktion
(`datenKunde()`, `datenProjekt()`), von zwei Aufrufern benutzt. Stünden die
Abfragen zweimal da, liefe die Schublade irgendwann der Seite hinterher, und
niemand wüsste, welche der beiden stimmt.

**Kunden, Bestellungen und Projekte stehen nicht mehr im Menü** — ihre Seiten
gibt es weiter, unter ihrer Adresse, von der Vorgangsseite verlinkt, und die
Suche findet sie. Nur als eigener Menüpunkt gäben sie einen zweiten Weg zur
selben Sache vor, und genau das war das Problem. „Alles andere" ist von 28 auf
23 Punkte gefallen.

Die drei Knöpfe „Kundenakte · Bestellung · Projekt" oben rechts sind weg: Sie
führten dorthin, wo man seit dem Umbau schon steht. 29 Knöpfe wurden 26.

#### Geprüft

Abschnitt 51 der Kette, 51 Prüfungen — **1103 insgesamt, alle grün**. Sie prüft
nicht die Gestaltung, sondern dass nichts abgerissen ist: alle 13 Blöcke, alle
26 Knöpfe, jeder Handgriff mit seiner Schublade, jede Marke genau einmal, die
alten Seiten weiter erreichbar, eine Aufbereitung mit zwei Aufrufern.

Dazu **jede Hauptseite in beiden Modi wirklich gerendert** — fünfzehn Seiten,
zweimal, angemeldet: keine PHP-Meldung, kein Konsolenfehler, kein Querscrollen
bei 390 Punkten. Screenshots über 1440 und 390.

**Noch offen (Vorschläge 3, 5–9):** Menü auf fünf Punkte · jeder Knopf sagt
vorher, was passiert (12 von 130 tun es heute) · eine Zeile, die nie abreißt
(„Schritt 4 von 9 · jetzt X · danach Y") · eine ehrliche Liste „Was hängt
gerade?" · deutsche Wörter statt Fachbegriffe · eine Seite „Damit alles läuft".
### Der Showroom ist jetzt die Bühne der Startseite (13.09.2026)

Uwe: „der neue showroom sollte auf die seite www.vecom-design.it deployed
werden". Auf die Rückfrage, wie — eigene Seite, Ersatz für die Startseite oder
neue Bühne dahinter — die Antwort: **neue Welt als Bühne der Startseite.**

Damit bleibt alles, was verkauft: Hero, Arbeiten, Leistungen, Preise, Ablauf,
Über mich, Haltung, Partner, Fragen, Kontakt — in Italienisch, Deutsch und
Englisch, mit derselben Sitemap und denselben Adressen. Ausgetauscht ist nur,
was dahinter steht.

WAS VORHER DA WAR UND WARUM ES GEHT: `scene.js` baute die Welt im Code — ein
aus Konturpunkten extrudiertes V, ein Boden, Staub, ein Halo, dazu ein
Bruch-Shader für den Auftakt. Gut gemacht, aber gerechnet, und man sah es. Der
Blender-Raum hat Podest, Portale, Deckenfelder, fünf Displays mit echten
Arbeiten und eine Marke mit eigenen Kanten. Über die Leitung kostet er 26 KB
(die GLB gezippt) plus drei Texturkarten.

**Der Umbau hängt an zwei Zeilen.** `site-world.js` lädt jetzt `raum.js` statt
`scene.js` und `raum-beats.js` statt `site-beats.js`. Die alten Dateien bleiben
unangetastet liegen — wer zurück will, tauscht die beiden Importe zurück.
Alles andere ist unverändert: dieselbe Rückfallebene (kein WebGL, reduzierte
Bewegung, Sparmodus, schwaches Gerät → exakt die Seite von vorher), dieselbe
Qualitätsstufung, derselbe Tagesgang des Lichts, derselbe Schleier.

DIE KAMERAZAHLEN SIND GEMESSEN. Vor dem ersten Beat wurde das Modell
ausgelesen: Boden x −11,5 … +11,5, z −21 … +9 (Gang bis −34), Decke bei 7,4,
Marke 5,1 × 4,1 m mittig auf dem Podest bei z −4, Portale bei −9/−17/−25,
Displays bei −20 … −22, Lamellenwand bei −33,2. Wer eine Kamera setzt, bleibt
darin — ein Meter zu weit, und man steht in der Wand.

**EIN FEHLER, DER ALLES ANDERE UNSICHTBAR MACHTE:** Ein ScrollTrigger mit
`scrub` ruft `onUpdate` schon beim Aufbau der Seite auf, mit `progress` 0. Der
Abspann am Seitenfuß schrieb damit beim Laden seine eigene Kamera in `camGoal`
und `lookGoal` — und zwar nach dem Eröffnungsflug. Die Startseite zeigte
deshalb dauerhaft die Ruhelage des Abspanns, der Hero-Zustand kam nie an, und
drei nacheinander gemessene Kameravarianten ergaben exakt dasselbe Bild. Das
sah aus wie ein Fehler in den Beats und war einer im Trigger. `if
(!self.isActive) return;` — eine Zeile.

**DER CHROMBODEN GEGEN DEN FLIESSTEXT.** Gemessen auf 1440x900, Pixel für
Pixel unter jeder Textfläche: 35,6 % des Fließtextes im Hero lagen unter 4.5:1,
an der schlechtesten Stelle bei 1.00 — dort war die Zeile schlicht weg. Schuld
ist die Spiegelung des Podests: die hellste Fläche der ganzen Seite, und sie
liegt genau auf halber Höhe, wo der Lauftext steht.

Drei Versuche, das über die Kamera zu lösen, machten es schlechter: 58 %, 92 %,
100 %. Die Spiegelung wandert mit der Marke — wer die Marke ins Bild holt, holt
sie mit. Gelöst mit einem Verlauf auf der Textseite (`.hero::after`), der vor
der Marke ausläuft, plus einer Kamera, die die Marke ins rechte Drittel setzt.
Ergebnis: **0,0 % unter 4.5:1, schlechtester Wert 5,19.**

Auf dem Telefon geht derselbe Trick nicht, und auch das ist gemessen: Dort
belegt der Text alles zwischen 110 px und 830 px — Kicker, Überschrift,
Fließtext, zwei Knöpfe, Kennzahlen. Ein Verlauf, der irgendwo aufmacht, macht
über Text auf. Also ein fast gleichmäßiger Schleier, der nur ganz oben öffnet,
wo nur die Kopfzeile steht. 0,0 % unter 4.5:1, schlechtester Wert 4,90. Die
frühere starke Rücknahme der Bühne auf schmalen Schirmen (Nebel hoch, Licht
runter, Schleier 0,60) konnte dafür weg — zusammen war es ein schwarzes Bild
mit einer Ahnung von Blau, und ein Hintergrund, den niemand erkennt, ist
verschenkte Ladezeit.

Geprüft: alle drei Sprachen laden die Bühne (5.232 Dreiecke), keine
Fehlermeldung in der Konsole, kein 4xx, kein Querlauf; neun Abschnitte einzeln
angefahren und angesehen. Bei „Haltung" stand die Überschrift auf der hellen
Marke — Schleier dort von 0,48 auf 0,68.

### Die Zeile, die Rückfragen und fünf Türen (13.09.2026, abends)

Vorschläge 6, 5 und 3 aus derselben Liste wie der Eintrag davor. Uwe: „Ok mach."

#### Eine Zeile, die nie abreißt (6)

Über allem auf der Vorgangsseite: **„Schritt 5 von 9 · Fehlt noch: Abnahme ist
gelaufen · Danach: Kunde hat abgenommen — beim Kunden."**

Die Verwaltung konnte immer sagen, was *jetzt* dran ist. Was danach kommt,
stand nirgends — und genau daran merkt man, ob eine Kette hält: Wer den
nächsten Schritt tut, ohne den übernächsten zu kennen, weiß hinterher nicht,
ob er fertig ist oder etwas vergessen hat.

**Das „Danach" wird nicht erfunden.** Eine Liste „nach A kommt B" hätte in dem
Augenblick gelogen, in dem ein Schritt übersprungen wird — und übersprungen
wird ständig, weil Tatsachen keine Reihenfolge kennen. `Ablauf::danach()`
nimmt deshalb denselben Weg wie das „Jetzt": den nächsten offenen Punkt der
Checkliste. Ist die Stufe durch, wird in der nächsten weitergesucht, bis etwas
offen ist oder wirklich nichts mehr kommt — dann steht das auch da.

Die Wörter mussten zweimal gesetzt werden: „Jetzt: Kunde hat den Link" liest
sich wie eine Tatsache, nicht wie etwas Offenes. Die Punkte der Checkliste
*sind* Zustände, keine Befehle. Also „Fehlt noch:". Der Befehl steht ohnehin
darunter auf dem blauen Knopf.

#### Jeder Knopf, der das Haus verlässt, fragt vorher (5)

Vorher: **12 von 130**. Jetzt: **51 von 131** (39 %). Dazugekommen ist nur, was
eine von vier Wirkungen hat — eine E-Mail an den *Kunden*, etwas in den
Büchern, etwas für Fremde Sichtbares, oder etwas endgültig Weges. Alles andere
schweigt weiter; sonst wird die Rückfrage zur Gewohnheit, und eine Gewohnheit
hält niemanden auf.

Dass es nur zwölf waren, hieß nie „wir fragen sparsam", sondern „wir haben nie
zu Ende gezählt". Ein Unteragent hat alle 131 Handgriffe einzeln gelesen und
nach Wirkung sortiert; daraus wurde die Liste. Neu mit Rückfrage sind unter
anderem `nachricht_senden`, `mahnung_schicken`, `angebot_senden`,
`zahlung_bestaetigen`, `cron_jetzt` (stößt Erinnerungen und Mahnungen an alle
Fälligen an), `cockpit_frei`, `kundenlink_neu` und `versand_schluessel_weg`
(„Ohne diesen Schlüssel geht keine einzige E-Mail mehr raus — an niemanden").

Die Kette prüft jetzt vier Dinge daran: dass jeder Eintrag einen Handgriff
trifft, den es gibt; dass jede Frage ein ganzer Satz ist; dass keine „Sind Sie
sicher?" fragt (wer das liest, antwortet Ja, ohne gelesen zu haben); und dass
jeder Ja-Knopf sagt, wozu man Ja sagt.

#### Fünf Türen (3)

`Heute · Kunden · Geld · Bauen · Einstellungen`. Die Tür, in der man steht,
klappt auf und zeigt ihre Nachbarn — sonst nichts. Die Zahl an einer
zugeklappten Tür ist die **Summe dahinter**: Zugeklappt heißt nicht, dass dort
nichts wartet.

Fünfundzwanzig flache Einträge, dann achtundzwanzig in sechs Gruppen, dann
sieben plus vierundzwanzig unter „Alles andere" — jeder Schritt war eine
Verbesserung, keiner löste das Problem. Die Ursache war nie die Zahl, sondern
die Ordnung: sortiert wurde nach Tabellen, und sechs dieser Tabellen sind sechs
Blicke auf denselben Kunden zu verschiedenen Momenten.

„Was nicht läuft" und „Was passiert ist" hängen unter **Heute**, nicht unter
Einstellungen: Achtzehn offene Warnungen neben dem Wort „Einstellungen" heißen
für jeden Leser, dass mit den Einstellungen etwas nicht stimmt.

#### Vier Seiten, die es gab und die niemand erreichte

Beim Durchrendern aller Menüpunkte gefunden: **„Ausgaben", „Betreuung",
„Kundenstimmen" und „Fürs Finanzamt" standen seit jeher im Menü und
antworteten mit 404.** Die Ansichten lagen fertig unter `app/views/`, die
Klassen dahinter auch — nur der Weg dorthin fehlte. Aufgefallen ist es nie,
weil niemand sie anklickte; und niemand klickte sie an, weil sie in der
zweiten Hälfte einer Liste von vierundzwanzig standen. Genau das meint
„nichts darf unübersichtlich sein": In einem Menü aus fünf Wörtern fällt ein
toter Punkt am ersten Tag auf.

Alle vier haben jetzt ihre Route, die Steuerseite samt ihrer sieben Downloads.

**Und ein Fehler, den ich fast ausgeliefert hätte:** Die Steuerseite hieß
`steuerakte` — genau wie der Ordner `app/steuerakte/`, in dem die fertigen
Jahrespakete liegen. Die Umleitung in `app/.htaccess` lässt echte Verzeichnisse
ausdrücklich in Ruhe (`RewriteCond !-d`), und der Ordner sperrt sich selbst mit
`Require all denied`. Der Aufruf wäre also nicht die Seite gewesen, sondern ein
403 — und zwar erst, **sobald das erste Jahrespaket geschrieben ist**. Vorher
hätte es monatelang funktioniert. Die Seite heißt jetzt `finanzamt`, wie ihr
Menüpunkt. Die Kette prüft seither zwei Dinge: dass jeder Menüpunkt einen Fall
im Verteiler hat, und dass keiner heißt wie ein Ordner unter `app/`.

#### Geprüft

Abschnitt 52, 76 Prüfungen — **1179 insgesamt, alle grün**. Dazu **33 Seiten in
beiden Modi wirklich gerendert**: keine PHP-Meldung, kein Konsolenfehler, kein
Querscrollen bei 390 Punkten. Eine Rückfrage im Browser ausgelöst und geprüft,
dass sie aufhält und nach „Abbrechen" nichts abgeschickt wurde.

**Noch offen (Vorschläge 7, 8, 9):** eine ehrliche Liste „Was hängt gerade?" ·
deutsche Wörter statt Fachbegriffe · eine Seite „Damit alles läuft".
*Nachtrag vom selben Tag: alle drei stehen, siehe unten. Damit sind die neun
Vorschläge vollständig umgesetzt.*
### „Weniger Bewegung" heißt weniger Bewegung, nicht weniger Inhalt (13.09.2026)

Aufgefallen beim Nachsehen in Uwes eigenem Chrome: Auf vecom-design.it stand
`data-world="reduced-motion"`, die Bühne war aus. Nicht wegen eines Fehlers —
in seinem Windows sind die Animationseffekte abgeschaltet, Chrome meldet das
als `prefers-reduced-motion`, und die Seite nahm ihn beim Wort. WebGL, gsap,
ScrollTrigger: alles vorhanden und in Ordnung.

**Er hat seine eigene neue Startseite also nie gesehen.** Und er ist damit
nicht allein: Die Einstellung wird auch gesetzt, um Akku zu sparen, von
Administratoren verteilt, oder sie bleibt nach einem einmaligen Anlass stehen.

Die alte Antwort — Bühne komplett aus — war zu grob. Die Einstellung gibt es
für Menschen, denen von bewegten Flächen schwindelig wird; ein **stehendes
Bild** tut ihnen nichts. Deshalb jetzt: Der Raum wird gebaut und genau einmal
gezeichnet. Kein Eröffnungsflug, keine Kamerafahrt zwischen den Abschnitten,
kein Driften, kein Atmen, kein Wiegen der Marke, keine Bildschleife — nach dem
einen Bild steht der Stromverbrauch bei null. Ein neues Fenstermaß löst ein
neues Bild aus; das ist eine Antwort, keine Bewegung.

Umgesetzt als eigener kurzer Weg (`standbild()` in site-world.js) statt als
Schalter in `start()`: Dort gibt es kein gsap, kein ScrollTrigger, kein Lenis
und keine Schleife — was fehlt, kann auch nicht versehentlich anspringen. In
`raum.js` springt die Kamera dabei hart auf den Sollwert statt gedämpft, denn
Dämpfung ist Bewegung. In `app.css` stand `display: none` für Bühne und
Schleier; geblieben ist davon nur `transition: none` — das Einblenden war der
Teil, der wirklich Bewegung war.

Gemessen mit `reducedMotion: 'reduce'`: `data-world="on"`, 5.232 Dreiecke,
keine Fehlermeldung, Schleier wie im bewegten Fall.

### 13.09.2026 — Was hängt, deutsche Wörter, und eine Seite „Damit alles läuft"

Die letzten drei der neun Vorschläge. Sie hängen zusammen: Alle drei nehmen
etwas weg, das nur im Kopf dessen existierte, der es gebaut hat.

#### 7 — Eine Liste statt dreier Kästen

Auf „Heute" standen zwei Kästen nebeneinander, die dasselbe meinten und es
verschieden nannten: **„Das läuft nicht"** zeigte, was gemeldet wurde;
**„Demnächst fällig"** zeigte, was eine Frist hat. Wer sie las, musste selbst
entscheiden, welcher der dringendere ist — und beide Male dieselbe Frage
beantworten: *Muss ich da ran?*

Dazwischen fehlte der gefährlichste Fall. **Stille löst nichts aus.** Ein
Vorgang, bei dem seit drei Wochen niemand etwas getan hat, erzeugt keine
Meldung und hat keine Frist. Er steht in der Arbeitsliste zwischen den
anderen, als wäre er von gestern. Genau deshalb fällt er niemandem auf.

Jetzt gibt es eine Liste: **„Was gerade hängt"**. Drei Quellen, ein Kasten,
jede Zeile mit *was*, *seit wann* und einem Knopf. Stille ist dort ein
Eintrag wie jeder andere — ab sieben Tagen, wenn es bei mir liegt, ab
vierzehn, wenn der Kunde schweigt. Der Unterschied ist Absicht: Was ich
liegen lasse, lässt jemanden warten; wer noch überlegt, soll nicht nach
einer Woche angemahnt werden.

**Zwei Dinge haben beim ersten Blick nicht funktioniert.** Achtzehn
Störungen nahmen alle zwölf Plätze — die ablaufenden Angebote und die stillen
Vorgänge kamen gar nicht mehr vor. Eine Liste, die nur noch eine Art zeigt,
ist wieder der Kasten, den sie ersetzen sollte. Seither bekommt jede Art
zuerst drei feste Plätze, dann füllen die Übriggebliebenen auf. Und zwölf
Zeilen schoben „Du bist dran" aus dem Bild — also sechs, und darunter die
ehrliche Zahl: *6 von 20*. Ein Deckel ohne diese Zahl wäre eine Lüge.

#### 8 — Deutsche Wörter

„Onboarding", „Kundenfeedback", „Finale Freigabe", „Mehrbedarf klären",
„Vorgänge". Alles Wörter, die jemand versteht, der das System kennt.

Geändert wurden die **Beschriftungen**, nicht die Schlüssel: In der Datenbank
steht weiter `onboarding`, `kundenfeedback`, `finale_freigabe`. Die Kette
prüft beides getrennt — dass draußen das deutsche Wort steht und dass drinnen
der gespeicherte Wert unberührt blieb. Wer Beschriftung und Schlüssel
zusammen ändert, schreibt eine Migration für ein Wort und riskiert, dass
alte Zeilen in kein Fach mehr passen.

#### 9 — „Damit alles läuft"

Zehn Dinge, die eingerichtet sein müssen, damit die Verwaltung von allein
arbeitet: Cronjob, Mailversand, Bezahlung, Webhook, Cockpit, Firmendaten,
Ablage, Datenbank, Beispieldaten, Werkstatt-Schlüssel.

**Jede Zeile misst etwas, das wirklich in den Daten steht** — kein Haken
bedeutet hier nur „ist eingetragen". Der Webhook ist der Grund für diese
Regel: Er stellt zwei Fragen. *Ist ein `whsec_` hinterlegt?* und *kam je
einer an?* Ein eingetragener Schlüssel, bei dem noch nie ein Ereignis
eintraf, sieht in jeder Konfigurationsansicht richtig aus und ist trotzdem
tot. Genau dieser Fall steht seit Wochen offen und wäre auf einer Seite mit
Häkchen für Eingetragenes grün gewesen.

Oben steht **ein Satz**, nicht eine Tabelle. Wer diese Seite aufmacht, will
eine Antwort: *Steht alles?* Steht alles, kann man die Seite nach zwanzig
Sekunden wieder zumachen. Am Menüpunkt hängt eine Zahl — und die zählt nur
echte Fehler, keine Warnungen. Eine Zahl, die auch bei Kleinigkeiten
aufleuchtet, wird nach einer Woche ignoriert.

Die Seite ändert nichts von selbst. Sie sieht nur nach, jedes Mal neu.

#### Geprüft

Abschnitt 53, 58 Prüfungen — **1237 insgesamt, alle grün**. Die
Stille-Prüfung brauchte einen zweiten Anlauf: Ich hatte nur
`orders.updated_at` und `projects.updated_at` gealtert, aber „zuletzt bewegt"
ist der jüngste von **fünf** Zeitpunkten — Fragebogen, Zahlungen und
Nachrichten zählen mit. Die Prüfung fand deshalb nichts und war grün, ohne
etwas zu prüfen. Jetzt bekommt `Haengt::alles()` eine gebaute Arbeitsliste
übergeben; genau dafür hat die Funktion diesen Parameter.

Dazu beide Modi im Browser, 1440 und 390 Punkte. Dabei zwei Dinge gefunden,
die kein Linter sieht: Die Untermenüs brachen auf dem Handy die Zeile
(518 Punkte Breite statt 390), und in der Zeile eines Vorgangs stand eine
120 Zeichen lange Adresse ohne Trennstelle — **das gab es schon vorher**,
es fiel nur nie auf, weil der Kasten daneben breiter war.
### Die fünf Displays zeigten nie, was auf ihnen liegt (13.09.2026)

Beim Fotoreal-Durchgang in Blender aufgefallen und dann auch auf der Website
gefunden: **Die Tafeln im Showroom sind Quader mit Würfel-UVs.** Acht Ecken,
sechs Flächen, eine UV-Insel von 0,12 bis 0,88 — das Standard-Auswickeln eines
Quaders, bei dem sich alle sechs Seiten dieselbe Fläche teilen. Ein Bild darauf
zeigt auf der Vorderseite einen Streifen und auf den Kanten den Rest.

Das erklärt einen ganzen Abend Fehlersuche in die falsche Richtung: In Blender
sahen die Bildschirme erst milchig-grau aus (Emission 3,2), dann fast schwarz
(1,9), dann wieder weiß (4,0 mit angehobenem Schwarzpunkt). Keine dieser
Schrauben war die richtige — es war immer dieselbe Randfarbe, über die ganze
Tafel gezogen.

Gelöst ohne das Modell anzufassen, in beiden Fassungen auf demselben Weg: Die
Koordinaten kommen aus dem eigenen Hüllquader statt aus der UV-Karte. In
Blender über `Generated` (x = Breite, z = Höhe), in three.js über neu
gerechnete UVs beim Laden (x und y aus der Bounding Box). Für eine flache
Tafel ist das genau ein Bildschirm.

DAZU ZWEI SACHEN, DIE ERST DAS MESSEN ZEIGTE:

**Die Arbeiten sind unterschiedlich hell.** Mittlere Helligkeit des gezeigten
Ausschnitts, linear gemessen: Cavaleri 0,30 — Vecom-Shop 0,14 — Mensaena 0,19
— Trendonix 0,075 — Jonika 0,055. Ein Faktor fünf zwischen hellster und
dunkelster. Mit einer gemeinsamen Leuchtstärke blendet die eine Hälfte aus und
die andere verschwindet. Jede Tafel wird deshalb auf dieselbe Ziel-Leuchtdichte
gerechnet — so wie fünf Monitore im selben Raum auch gleich hell eingestellt
wären.

**Die Aufnahmen sind Vollseiten.** `cavaleri-desktop.webp` ist 600 × 4184
Pixel. Ungeschnitten auf einer Tafel im Format 1,5:1 wird daraus ein Strich.
Gezeigt wird jetzt der obere Teil — der, den ein Besucher auch zuerst sieht.

### Der Raum wird fotografiert, nicht gerechnet (13.09.2026)

Uwe: „jetzt macht das showroom das maximum was geht fotorealistisch". Sechs
Änderungen an `vecom-showroom_Fotoreal3.blend`, daraus wurde `_Fotoreal4`:

1. **512 → 1024 Samples**, adaptiv 0,006 → 0,004. Die fleckigen, wie mit dem
   Daumen verwischten Wände im letzten Render waren keine Textur, sondern der
   Entrauscher, der aus zu wenig Information zu viel machen sollte.
2. **Klemme auf dem indirekten Licht von 12 auf 20.** Sie kappte genau die
   hellen Spitzen, die eine Spiegelung zur Spiegelung machen.
3. **Die Marke war nicht gebürstet, nur metallisch** — Anisotropie 0,0 bei
   Metallic 1,0. Das ist der Unterschied zwischen gebürstetem Aluminium und
   lackiertem Kunststoff. Jetzt 0,38 plus Schleifspuren aus einem in einer
   Achse 60:1 gestreckten Rauschen.
4. **Softboxen.** Der wichtigste Punkt und der, den man am wenigsten erwartet:
   Eine polierte Fläche zeigt nicht sich selbst, sondern ihre Umgebung — und
   in diesem Raum gab es nichts zu spiegeln außer dünnen Leisten weit oben.
   Drei Flächen (zwei hohe seitlich, eine liegende darüber), kameraunsichtbar
   und ohne Schattenwurf, ändern die Beleuchtung kaum und das Aussehen des
   Metalls vollständig. Genau so arbeitet jedes Produktfoto seit hundert
   Jahren.
5. **Polierbahnen im Boden** statt nur feinem Rauschen. Feines Rauschen ist
   Staub; was einen polierten Boden ausmacht, sind die großen Spuren.
6. **Der Dunst streut jetzt nach vorn** (Anisotropie 0,62). Ohne Vorzugsrichtung
   ergibt Streuung Milchglas; mit ihr entstehen Lichtbahnen dort, wo man gegen
   eine Lampe schaut.

ZWEI FUNDE AM RANDE, BEIDE TEUER GEWESEN, WENN SIE UNBEMERKT GEBLIEBEN WÄREN:
Drei Texturpfade zeigten noch auf `C:\Users\manue\Desktop\Vecom design\...` —
den Ordner **vor** dem OneDrive-Umzug. Die Dateien waren tot. Und für SheepIt
muss alles in die Datei gepackt sein, sonst wären die Displays auf der Farm
schwarz geblieben; `vecom-showroom_Fotoreal4.blend` ist mit 0,83 MB gepackt.

**Auf diesem Rechner gibt es kein GPU-Rendering** — Cycles findet nur den
i7-4600U. Ein Vorschaubild in 960 × 540 mit 128 Samples dauert dort neun bis
sechzehn Minuten; ein volles Bild in 1920 × 1080 mit 1024 Samples wären
Stunden. Die Farm ist hier keine Bequemlichkeit, sondern der einzige Weg.

### 13.09.2026 — Eine Sprache kostet je Seite

Uwe: „Die Preisspanne auf der Hauptseite ist sehr unrealistisch. Schau in den
Baukasten. Wenn eine Seite 325–400 kostet, können 5 Seiten mit 3 Sprachen keine
800–1.000 kosten."

Er hatte recht, und darunter lag mehr als eine schiefe Zeile.

#### Was auffiel: die Beschriftung

„Eine einzige Seite — 325–400 €" liest jeder als Preis **pro Seite**. Er war es
nie: `Grundgerüst` ist die ganze Website — Gestaltung, Handy, Kontaktformular,
Veröffentlichung — und die erste Seite steckt darin. Wer die Liste las,
rechnete 5 × 400 = 2.000 und sah daneben 650 stehen. Dann glaubt man entweder
die eine Zahl nicht oder die andere. Der erste Fall heißt jetzt **„Komplette
Website, eine Seite"**, und unter den vier Fällen steht, was im ersten Preis
steckt und was jede weitere Seite kostet.

#### Was darunter lag: die Sprache skalierte nicht

`Weitere Sprache` war eine **Pauschale**, unabhängig von der Seitenzahl. Auf
die übersetzte Seite gerechnet:

| Seiten | Aufschlag für 2 weitere Sprachen | je übersetzter Seite |
|---|---|---|
| 1 | 280–360 € | 140–180 € |
| 5 | 280–360 € | 28–36 € |
| 15 | 280–360 € | 7–9 € |

Dieselbe Arbeit zu zwanzigfach verschiedenen Preisen. Am Ende stand der
Konfigurator dafür gerade, dass **15 Seiten in drei Sprachen — 45
Seitenfassungen — 1.255 bis 1.600 Euro** kosten. Das ist Wochenarbeit für 28
Euro die Fassung, und es war kein Grenzfall: „viele Seiten" und „drei Sprachen"
stehen beide zur Auswahl.

Seit Migration 047 ist `sprache` ein Preis **je Seite**; die Menge ist
Seitenzahl mal zusätzliche Sprachen. Die weitere Seite steigt mit, von 45–60
auf 65–85 — bei 45 Euro bewegte die Seitenzahl den Preis kaum, das Grundgerüst
trug fast alles, obwohl es nur einmal anfällt.

|  | 1 Sprache | 2 Sprachen | 3 Sprachen |
|---|---|---|---|
| 1 Seite | 325–400 | 375–475 | 425–525 |
| 5 Seiten | 600–750 | 800–1.025 | 1.000–1.300 |
| 9 Seiten | 850–1.100 | 1.200–1.600 | 1.550–2.100 |
| 15 Seiten | 1.250–1.600 | 1.850–2.450 | 2.450–3.250 |

Nicht gestiegen sind Grundgerüst, Shop, die Zusatzfunktionen und beide
Monatsverträge. Diese Runde repariert eine Rechenregel, sie ist keine
Preiserhöhung über die Breite. Gegen den einzigen Mitbewerber in der Provinz,
der Preise nennt (690 Vitrine, 1.590 Shop), liegt Vecom weiter darunter.

#### Eine Spalte für die Einheit

Die Preisseite schrieb hinter jeden Baustein mit `je_einheit` das Wort „je
Stück". Bei einer weiteren Seite stimmt das; bei einer Sprache, die je Seite
gerechnet wird, wäre es falsch — und zwar genau an der Stelle, an der der Kunde
nachrechnet. Deshalb `bausteine.einheit` statt eines fest verdrahteten
Sonderfalls für diesen einen Slug, der beim nächsten Baustein wieder vergessen
worden wäre. Die Verwaltung liest dieselbe Spalte: In der Bausteinliste, die
Uwe bei einer Preisrunde vor sich hat, steht jetzt „je Seite" statt „je Stück".

#### Geprüft

Abschnitt 54, 38 Prüfungen — **1275 insgesamt, alle grün**. Der Abschnitt prüft
nicht die Zahlen, sondern die Regel: Mehr Seiten müssen mehr Übersetzung
kosten, und der Aufschlag bei fünfzehn Seiten muss das Fünfzehnfache des
Aufschlags bei einer sein. Dazu eine Untergrenze — keine Seitenfassung unter
dem halben Seitenpreis —, denn genau dort war der Fehler sichtbar geworden.

**Zwei Sabotageproben**, weil eine grüne Prüfung, die nichts prüft, schlimmer
ist als keine: Mit der alten Regel (`$sprachen - 1`) fallen acht Prüfungen um,
die letzte mit dem Satz „2,67 € je Fassung". Mit einer verstellten Zahl im
HTML-Rückfall fällt genau die Datei auf, in der sie steht.

Der Rückfall im HTML wird seither **gegen die Rechnung geprüft**, nicht gegen
eine zweite Konstante im Test: Antwortet `preise-daten.php` nicht, ist er die
einzige Zahl auf der Seite — und eine stille Störung sieht man nicht, weil die
Seite dann trotzdem vollständig aussieht.

Dazu alle drei Sprachfassungen von Start- und Preisseite bei 1440 und 390
Punkten gerendert, mit laufender Verwaltung (also mit den echten Zahlen, nicht
dem Rückfall): vier Fälle, Bausteintabelle, kein Querscrollen, kein Klemmen in
den Karten trotz der längeren Überschrift. Und ein Angebot durchgerechnet —
dort steht „Weitere Sprache, je Seite · 10 × · 400 – 550 €".

#### Nachtrag, eine Stunde später: das Fenster zwischen Deploy und Migration

Der Deploy war durch und die Startseite zeigte **1.900 – 2.450 €** für fünf
Seiten in drei Sprachen. Richtig wären in diesem Moment 800 – 1.000 gewesen.

Die Ursache ist keine Zahl, sondern eine Reihenfolge: Der Deploy bringt den
neuen Code sofort, die Migration läuft erst beim nächsten Cronlauf. Drei
Minuten lang traf die **neue Menge** — zehn übersetzte Seiten, im Code — auf
die **alten Preise** in der Datenbank. Der Konfigurator daneben rechnete
dieselbe Mischung; die Zahl war also nicht einmal widersprüchlich, sondern
überall gleich falsch.

Der erste Reflex war, die Preisseite den Konfigurator fragen zu lassen statt
selbst zu rechnen. Das war richtig und nötig — hier standen vier von Hand
gepflegte Rezepte neben der eigentlichen Rechnung, also zwei Wege für dieselbe
Frage — aber es hätte das Fenster nicht geschlossen: Die Menge kam weiterhin
aus dem Code, der Preis aus der Datenbank.

**Also entscheidet jetzt die Spalte.** `bausteine.einheit` sagt, ob ein
Baustein je Stück oder je Seite gerechnet wird, und `rechnen()` liest sie:

```php
if ((string) ($b['einheit'] ?? 'stueck') === 'seite') {
    $mengen[$slug] = $menge * $seitenGesamt;
}
```

Vor der Migration gibt es die Spalte nicht, der Rückfall ist `'stueck'`, und
gerechnet wird wie vorher — alte Menge, alte Preise, die alte richtige Zahl.
Nach der Migration steht `'seite'` da, und **Menge und Preis wechseln in
derselben Sekunde**, weil sie in derselben Zeile stehen. Nachgemessen: derselbe
Code gibt gegen den Katalog von vor der Migration 800 – 1.000 € aus und gegen
den danach 1.000 – 1.300 €.

Nebenbei braucht der nächste Baustein, der je Seite anfällt, keine Zeile Code
mehr, sondern einen Eintrag.

Die Preisseite fragt trotzdem ab sofort den Konfigurator: Die vier Fälle sind
keine Postenlisten mehr, sondern **Antworten**, wie ein Kunde sie gäbe. Damit
kann die Website nicht mehr etwas anderes behaupten als das Angebot — sie
rechnet es nicht nach, sie fragt.

**Geprüft**, und zwar an genau diesem Fall: Ein Katalog mit den alten Preisen
und ohne die Spalte muss die alte Zahl ergeben, nicht die Mischung; und das
Setzen der Spalte allein muss die Rechnung umschalten. **1282 Prüfungen, alle
grün.** Auch die Prüfung der HTML-Rückfälle geht jetzt über `rechnen()` statt
über eine abgeschriebene Postenliste — sonst hätte sie ihre eigene Abschrift
geprüft.

### 13.09.2026 — Was mit dem Auftrag wächst, wächst im Preis mit

Uwe nach der Sprachrunde: „Schau nochmal, ob die anderen Bausteine auch
realistisch sind."

Der Fehler bei der Sprache war kein Einzelfall, sondern ein **Muster**: eine
Pauschale für Arbeit, die mit jeder Seite mitwächst. Vier Bausteine hatten ihn
noch.

|  | pauschal | 1 Seite | 5 Seiten | 15 Seiten |
|---|---|---|---|---|
| Texte schreiben | 140–185 € | 140 €/Seite | 28 €/Seite | **9,30 €/Seite** |
| Bilder | 105–140 € | 105 €/Seite | 21 €/Seite | **7,00 €/Seite** |
| Inhalte übernehmen | 105–140 € | 105 €/Seite | 21 €/Seite | **7,00 €/Seite** |

Bei den Texten wog er schwerer als bei der Sprache, weil **Texte und Bilder von
allein anfallen**: Sie werden berechnet, sobald der Kunde sie unter „Was hast du
schon fertig?" nicht ankreuzt — und das ist der Normalfall. Ein Hotel mit
fünfzehn Seiten in drei Sprachen bekam eine Zeile über 140 Euro, hinter der
45 Seitenfassungen Text stehen.

`express` hatte denselben Fehler andersherum: 170–230 Euro fest sind beim
Einseiter dreißig Prozent Aufschlag und beim Hotel fünf — dabei ist Vorrang bei
einem großen Auftrag viel mehr Verschiebung.

#### Der Satz ist der heutige Preis geteilt durch fünf

„Wenige Seiten (3–5)" ist der häufigste Fall. Wer ihn bestellt, zahlt nach
dieser Runde **auf den Cent dasselbe** wie vorher. Das ist Absicht: Es wird eine
Rechenregel repariert, nicht der Preis erhöht. Bewegen tun sich nur die Enden.

| | vorher | nachher |
|---|---|---|
| Handwerker, 1 Seite, 1 Sprache | 575–725 | 375–475 |
| Trattoria, 5 Seiten, 3 Sprachen | 1.350–1.800 | 1.400–1.850 |
| Laden, 9 Seiten, Shop | 2.250–2.950 | 2.500–3.350 |
| Hotel, 15 Seiten, eilig | 3.400–4.600 | 4.600–6.200 |

Die vier Beispiele auf der Startseite ändern sich **nicht**: Sie rechnen mit
vollständigem Material und ohne Express, also kommt keiner der geänderten
Bausteine darin vor.

#### Was pauschal bleiben muss

Speisekarte, Termine, Buchung, Shop, Logo. Die baut man einmal, unabhängig von
der Seitenzahl — eine Buchung für eine Ferienwohnung wird nicht billiger, weil
die Seite klein ist. Die Kette prüft das ausdrücklich **in beide Richtungen**:
Was je Seite gerechnet werden muss, wird es; und was pauschal bleiben muss,
darf es nicht werden. Wer einen Fehler repariert und dabei über das Ziel
hinausschießt, macht ihn nur andersherum.

Zwei Funktionen standen im Vergleich zu niedrig und stehen jetzt richtig:
**Buchung 450–600 → 600–800** (Verfügbarkeit, Zeiträume, Saisonpreise und eine
Bestätigung, die von allein rausgeht, stehen dem Shop an Arbeit nicht nach) und
**Speisekarte 105–140 → 150–200** (nach Gruppen geordnet und ohne Code änderbar
heißt: es gibt eine Bearbeitungsfläche).

#### Ein Versprechen, das der Konfigurator nicht halten konnte

Auf der Preisseite stand beim Shop: „Was es am Ende kostet, hängt vor allem an
der Zahl der Artikel." Eine Frage nach der Artikelzahl gibt es nicht — acht
Fragen sind die Grenze, und eine neunte wäre der falsche Preis für diesen Satz.
Also geht der Satz: „Der Preis deckt den Aufbau — wie viele Artikel eingepflegt
werden, steht im Angebot."

#### Der Fund, der teurer geworden wäre als alles andere

Migration 048 lief nicht. Der Grund stand in keiner Zeile, die nach einem
Fehler aussah:

```
text_en = 'I write the text for every page; you read it before it goes live.'
```

Der Migrationslauf zerlegte die Datei mit `explode(';', $sql)`. Das ging Jahre
gut, weil nie ein Semikolon **innerhalb einer Zeichenkette** stand. Hier stand
eines — in einem englischen Satz —, der Teiler schnitt mitten hinein, und die
Datenbank bekam zwei Hälften zu sehen, von denen keine eine Anweisung ist.

Das Semikolon aus dem Satz zu nehmen wäre der schnelle Weg gewesen und hätte
die Falle stehen lassen — für den nächsten, der einen Satz für Kunden einträgt,
und Migrationen bestehen oft genau daraus. Der Teiler zählt jetzt mit, ob er in
einer Zeichenkette steht: die drei Begrenzer von MariaDB (`'`, `"`, Backtick),
die Verdopplung (`'L''Aquila'`) und der Backslash.

Nebenbei aufgefallen: Die **englische Preistabelle** schrieb „345 – 400 €"
statt „€345 – 400" — anders als die vier Fälle auf derselben Seite und anders
als das, was `preise-daten.php` liefert. Die Seite hätte beim Laden ihr
Aussehen gewechselt. Jetzt prüft die Kette auch die Preistabelle Zeile für
Zeile gegen den Baukasten, nicht nur die vier Fälle.

#### Geprüft

**1.318 Prüfungen, alle grün.** Drei Sabotageproben:

* Texte wieder pauschal → vier Prüfungen fallen, darunter „keine geschriebene
  Seite unter zwanzig Euro" mit dem Wert **1,87 € je Seite**.
* Der Shop je Seite gerechnet → der Fehler in die Gegenrichtung fällt in allen
  sechs HTML-Dateien auf.
* Der alte Teiler zurück → Migration 048 läuft nicht mehr, mit genau der
  Fehlermeldung, die den Fund ausgelöst hat.

Dazu alle drei Sprachfassungen der Preisseite bei 1440 und 390 Punkten mit
laufender Verwaltung gerendert, und ein Angebot durchgerechnet: Dort steht
jetzt „Texte schreiben, je Seite · 15 × · 420 – 555 €" statt einer Zeile über
140 Euro.

**Beobachtung, nicht geändert:** Die monatliche Betreuung ist 39 € — für einen
Einseiter wie für fünfzehn Seiten in drei Sprachen mit Buchungssystem. Das ist
derselbe Fehler wie oben, nur im Abo. Uwe hat in Migration 044 ausdrücklich
entschieden, dass der Monatsbetrag nicht steigt; hier steht es nur, damit es
beim nächsten Mal nicht wieder gefunden werden muss.
### Glas über dem Raum, und eine Gravur, die lesbar bleibt (14.09.2026)

Uwe: „Die gesamte Webseite soll auch sehr hyperrealistisch mit Glass Effekte
bestehen" — und kurz darauf: „Lass die Schrift im Glas wie eingraviert
aussehen aber ultra photorealistisch."

**Warum Glas erst jetzt Sinn ergibt.** Glasmorphismus auf flachem Grund ist
eine Scheibe vor einer Farbe — Dekoration. Seit der Blender-Raum hinter der
ganzen Seite steht, ist es das Gegenteil: Jede Fläche zeigt etwas, und die
Karten bekommen Tiefe geschenkt, statt sie mit Schatten zu behaupten. Der
ganze Block hängt deshalb an `[data-world="on"]`. Läuft die Bühne nicht,
bleibt die Seite exakt die alte, und der teure `backdrop-filter` wird gar
nicht erst gerechnet.

DREI DINGE UNTERSCHEIDEN ECHTES GLAS VON MILCHGLAS, und keins davon ist die
Unschärfe: die **Kante**, die Licht fängt (oben links hell, unten rechts
kühl); die **Dicke**, sichtbar als heller Strich knapp innen an der
Oberkante — die angeleuchtete Stirnfläche; und die **Sättigung**, die steigt,
weil Glas Licht bricht (`saturate(150%)`, keine Aufhellung).

Die Unschärfe ist der teure Teil, nicht der schöne: `backdrop-filter` rechnet
pro Element die Fläche dahinter neu, über einer laufenden 3D-Bühne. Der
Radius hängt deshalb an der Qualitätsstufe, die die Bühne ohnehin ermittelt —
22 px, 14, 8. Gleiche Optik, andere Rechenarbeit. `site-world.js` schreibt
die Stufe als `data-stufe` an die Wurzel.

**DIE GRAVUR — und warum der übliche Weg falsch ist.** „Eingraviert" und
„lesbar" widersprechen sich, wenn man Gravur als Vertiefung denkt: Eine Rille
in dunklem Glas ist dunkler als das Glas, und jede Zeile wäre weg. Genau
daran scheitert der Letterpress-Effekt aus der Werkzeugkiste.

Es gibt aber eine zweite Art, Glas zu beschriften, und sie ist die häufigere:
**sandgestrahlt**. Die aufgeraute Fläche streut das Licht, statt es
durchzulassen — geätzte Schrift auf dunklem Glas ist deshalb HELLER als ihre
Umgebung. Jede Duschkabine zeigt es. Drei Lagen: die dunkle Schnittkante
oben, die helle Gegenkante unten, und zwei Streuradien (eng und weit, weil
eine einzelne Unschärfe wie Leuchtreklame aussieht).

**Nur Auszeichnungsschrift.** Bei 16-px-Zeilen sitzen die drei Lagen enger
beieinander als die Strichstärke; was bei einer Überschrift Tiefe ist, wird
dort Matsch. Gravuren gibt es auf Türschildern, nicht in Büchern.

ZWEI SACHEN, DIE ERST DAS MESSEN ZEIGTE:

1. **`text-shadow` war auf den wichtigsten Überschriften wirkungslos.** Ein
   guter Teil der Auszeichnungsschrift ist Verlaufsschrift
   (`background-clip:text` mit `color:transparent`). Dort malt `text-shadow`
   hinter eine durchsichtige Schrift. `drop-shadow` arbeitet dagegen auf dem
   fertig gezeichneten Ergebnis und nimmt den Verlauf mit.
2. **Der erste Glaston war zu durchsichtig.** Mit `.46` lag die Scheibe im
   FAQ-Abschnitt fast auf der Helligkeit der Schrift darauf. Der Grund war
   die Bühne: Dort stand die Kamera dicht an der Marke, das ganze Bild war
   mittelblau. Zwei Korrekturen — Ton auf `.68`, und der FAQ-Beat schaut
   jetzt von weiter hinten an der Marke vorbei in den Gang.

Dazu bekommen die Überschriften einen eigenen weichen Grund: Der Schleier der
Bühne ist ein runder Verlauf, der die Ränder abdunkelt und die Mitte offen
lässt — und in der Mitte steht die Marke. Ein zweiter globaler Schleier wäre
die falsche Antwort; stattdessen bringt jede Überschrift ihren eigenen mit,
weit über den Textblock hinaus auslaufend.

Gemessen nach dem Umbau (Text ausgeblendet, reiner Grund, 1440x900):
Überschrift 0,0 % unter 4.5:1 (schlechtester 8,54), Einleitung 0,0 % (6,25),
Kartentext 0,1 % (4,43).

### Die schwebenden Balken schweben nicht (14.09.2026)

Uwe: „Es schweben komische Balken im Showroom, auch an der Decke sind komische
Balken." Nachgemessen, Objekt für Objekt: **Geometrisch schwebt nichts.** Die
Deckenfelder, die Portalbalken und die Lichtleisten sitzen alle dort, wo sie
hingehören.

Der Fehler ist ein Beleuchtungsfehler: **Die Decke ist fast schwarz** —
Grundfarbe 0,006 bei Rauheit 0,95, also schwarzer Samt. Man sieht sie nicht.
Und was unter einer unsichtbaren Decke hängt, hängt an nichts.

`raum_decke.py` setzt deshalb an drei Stellen an: die Decke bekommt ein
eigenes Material (Grundton verdreifacht, ein Hauch Glanz, eine große ruhige
Struktur) — nicht hell, nur vorhanden; die vier Deckenfelder bekommen je vier
schlanke Abhängungen von 3 cm nach oben in die Decke; und die Portalpfosten,
die im Dunkeln verschwanden, bekommen ein etwas helleres Metall, damit der
9 m lange Querbalken sichtbar auf etwas steht.

### Widerruf: sie schwebten doch (14.09.2026)

Der Abschnitt darüber ist falsch. „Geometrisch schwebt nichts" stand dort,
weil ich die Objekte nach ihren Positionen gefragt hatte und nicht nach ihren
Ausdehnungen. Die Positionen waren plausibel. Die Ausdehnungen waren es nicht.

Nachgemessen mit den Weltkoordinaten aller Eckpunkte statt mit `location`:

| Bauteil | gemessen | hätte sein müssen |
|---|---|---|
| Boden | Y −9,11 … 20,87 | bis Y 34 — der Saal geht bis zur Rückwand |
| Decke | Y −8,82 … 21,16 | ebenso |
| Seitenwände | Z 1,76 … 6,74 | Z 1,19 (Boden) … 7,38 (Decke) |
| Rückwand | Z 1,24 … 5,05 | ebenso |
| Portalbalken | X −4,50 … 4,50 | bis zu den Pfosten bei X ±8,6 |
| Portalpfosten | Z 2,28 … 5,78 | vom Boden bis unter den Balken |
| Deckenfelder | Z 7,84 … 7,90 | die Decke liegt bei 7,38 … 8,71 |
| Podest | Z 0,75 … 1,06 | der Boden endet bei 1,19 |

Die hinteren dreizehn Meter des Saals — genau dort, wo die Displays hängen —
hatten weder Boden noch Decke. Die Seitenwände schwebten 57 cm über dem Boden
und hörten 64 cm unter der Decke auf; sie waren selbst schwebende Platten. Die
Portalbalken spannten neun Meter zwischen Pfosten, die 17,2 m auseinander
stehen, und berührten ihre eigenen Pfosten nicht — **das sind die Balken, die
Uwe an der Decke gesehen hat.** Die Lichtpaneele steckten im Deckenstein, die
Abhängungen darüber: Sie waren in der Vorschau vom 13.09. nicht zu dunkel,
sondern eingemauert. Die Korrektur „helleres Metall, dickerer Durchmesser"
ging am Problem vorbei.

**Die Lehre ist nicht „sorgfältiger hinsehen".** `location` ist der
Objektursprung, `dimensions` der Huellquader im Objektmass, und `bound_box`
ist nach einer Punktänderung noch der alte Wert. Keine der drei Angaben sagt,
wo ein Bauteil in der Welt anfängt und aufhört. Nur die Eckpunkte durch
`matrix_world` sagen das. Wer Architektur prüft, prüft Fugen — also Kanten
gegen Kanten, nicht Mittelpunkte gegen Erwartungen. `bauabnahme.py` macht
genau das und druckt jede Kante mit.

DAZU DER GRUND, WARUM DER SAAL EIN LOCH WAR: **die Wände hatten Albedo
0,008.** Kein reales Material ist so dunkel — schwarze Wandfarbe liegt bei
0,04, dunkler Putz bei 0,06, und selbst Ruß kommt nicht unter 0,02. Bei 0,008
schluckt die Wand 99 % des Lichts und kann nichts zurückwerfen. Es gab keine
Fläche, auf der das Auge aufsetzen kann; was keine Fläche hat, sieht aus, als
schwebte es. Die Dunkelheit eines Raums kommt aus dem Licht, nicht aus der
Farbe — Wand 0,042, Boden 0,030, Decke 0,058, und die Decke ist jetzt wie in
jedem realen Innenraum die hellste Fläche.

Die Deckenfelder sind bündig in die Decke eingelassen, statt an Stangen zu
hängen: Was bündig sitzt, kann nicht schweben. Die 16 Abhängungen sind damit
gegenstandslos und entfernt. Zwei durchgehende Längsfugen geben der Decke eine
Richtung und liefern das Grundlicht, das der geschlossene Raum braucht —
vorher fiel Weltlicht durch die Lücken ein, die es nicht mehr gibt.

**Dasselbe kaputte Modell lag live.** Die ausgelieferte `showroom.glb`
nachgemessen: neun Meter breite Portalbalken, dreißig Meter Boden im
vierundvierzig Meter langen Saal. Was auf vecom-design.it zu sehen war, hatte
exakt denselben Fehler — Blender zu reparieren und die Seite zu vergessen
hätte gar nichts geheilt. `web_export.py` exportiert neu; ohne Draco, weil
`raum.js` mit blankem GLTFLoader lädt, und ohne Softboxen und Dunstvolumen,
weil das in three.js drei weiße Rechtecke und ein grauer Klotz wären.

### Der Saal ist ein Keil (14.09.2026)

Die Bauabnahme von heute Morgen hat die Balken zum Stehen gebracht und dabei
neue erzeugt. Der Grund stand von Anfang an im Modell, ich hatte nur nach
Kanten gesucht und nicht nach **Ebenen**:

**Boden und Decke sind beide um −0,024 m/m geneigt.** Vorn, an der Schwelle,
liegt der Boden auf 1,19 m und die Decke auf 8,56 m; hinten an der Rückwand
auf 0,01 m und 7,38 m. Die lichte Höhe bleibt über die ganzen 49 Meter bei
7,37 m — der ganze Saal kippt. Das ist erzwungene Perspektive, derselbe Trick
wie bei den Portalen, die nach hinten niedriger werden: Der Raum wirkt tiefer,
als er ist.

Nur wusste das keiner der Einbauten. Alles saß auf absoluten Höhen:

| Bauteil | nach der ersten Bauabnahme | Boden/Decke dort |
|---|---|---|
| Podest | Z 1,19 … 1,52 | Boden 0,78 — **41 cm in der Luft** |
| Deckenfugen | Z 7,33 … 7,39 | Decke vorn 8,56 — **1,2 m darunter** |
| Bodenlichtfugen | Z 1,17 … 1,21 | Boden fällt auf 0,01 — halb vergraben |
| Portalpfosten | Z 1,17 … | Boden 0,59 / 0,40 / 0,21 |
| Lamellen | Z 1,17 … | Boden 0,01 |

**Wer in einem geneigten Raum mit absoluten Höhen arbeitet, baut
zwangsläufig in die Luft.** `bauabnahme.py` rechnet jetzt gegen zwei Ebenen —
`boden(y)` und `decke(y)` — statt gegen zwei Zahlen. Jeder Einbau bekommt
seinen Sitz relativ zu der Fläche, auf der er steht oder in der er hängt.

DIE BÜHNE IM BROWSER MUSSTE EIGENE WERTE BEKOMMEN. Cycles und three.js meinen
dieselben Zahlen verschieden, und zwar systematisch:

* **Albedo.** Wand 0,008 → 0,042 ist in Cycles fast unsichtbar, der Gewinn
  steckt im indirekten Licht. three.js kennt kein indirektes Licht; dort
  multipliziert dieselbe Zahl die direkt beleuchtete Wand mit sieben.
* **Lampen.** Der Spot saß auf y 7,5 — mitten in der Deckenplatte, deren
  Unterkante bei 7,38 liegt. Er hat die Decke von innen angestrahlt. Das
  Wandlicht stand unter einem Deckenstück, das es vorher nicht gab.
* **Bloom.** Für eine schwarze Halle gerechnet. Sobald es Flächen gibt, die
  ihn tragen, wird aus Glanz ein Schleier. Alle dreizehn Beat-Werte auf 45 %.
* **Boden.** Er spiegelt nicht den Saal, sondern die Umgebungskarte — ein
  helles Studio. Solange die Halle schwarz war, fiel das nicht auf.

Gemessen im kopflosen Browser (1440 × 900, SwiftShader, Stufe „medium",
Bühne ohne Text und Schleier): live heute Mittel 103, neu 123. Heller, aber
nicht mehr ausgebrannt (0,9 % statt 4,7 %) — und der Unterschied ist, dass
über dem Saal jetzt eine Decke ist statt Schwärze.

**Das Werkzeug dazu ist der Strahl, nicht das Auge.** Dreimal habe ich die
falsche Ursache vermutet und geändert, ohne dass sich etwas bewegte. Erst ein
Raycast durch die Bildschirmkoordinate der Marke hat es entschieden: Marke_V,
19,3 m, unverdeckt, sichtbar — sie war da, nur übertönt. Wer Licht vermutet,
soll messen, was der Strahl trifft.

---

## 14.09.2026 — Der Ersatz bekam das Bild, aber nicht den Schutz

**Wer einen Ersatz baut, muss ihm alles mitgeben, was das Original hatte —
auch das Unsichtbare.** Das Standbild der Bühne stand als `position: fixed`
hinter der ganzen Startseite. Der laufende Saal legt aber je Abschnitt einen
Schleier darüber (`--world-scrim`, 0,48 bis 0,86), und diese Variable setzen
nur die Weltmodule. Läuft die Welt nicht, bleibt sie auf 0 — und genau dann
ist das Standbild sichtbar. Gemessen im Zustand „device": 11,98 % der
Textfläche unter 4.5:1 auf 1440 × 900, 10,77 % auf 390 × 844. Das Standbild
steht jetzt nur noch im Hero und rollt mit ihm weg; danach 0,17 bzw. 0,16 %.
Ein pauschaler Schleier wäre das Pflaster gewesen: 3,30 % bei 0,85, und die
Bühne dabei fast schwarz.

**Eine falsche Messung ist schlimmer als keine, weil sie beruhigt.** Dieselbe
Seite meldete über ein `fullPage`-Bild 0,40 %. Playwright malt ein Element mit
`position: fixed` darin nur einmal, ganz oben; alles darunter wird gegen
Schwarz gemessen. Kontrast über eine lange Seite wird ab jetzt bildschirmweise
gerollt gemessen — `kontrast-rollen.mjs`-Muster —, nie über ein fullPage-Bild.
Zweiter Fehler im selben Werkzeug: Ein pauschales `background-image: none`
gegen Verlaufsschrift nimmt `.btn--primary` sein weißes Kissen (das kommt aus
einem Verlauf, nicht aus `background-color`) und meldete 96,6 % unlesbar für
einen Knopf mit über 16:1. Nur wer `background-clip: text` hat, verliert sein
Bild.

**`defer` macht ein fremdes Skript nicht unschädlich.** Das Widget des
Telefonassistenten hielt `DOMContentLoaded` 12.713 ms auf, solange STRATO
schwieg — und „schweigt" ist der Normalfall für jeden mit Werbeblocker, denn
die Listen kennen `voicereceptionist`. Jetzt hängt es sich nach dem
`load`-Ereignis ein: 271 ms. Fremde Skripte gehören grundsätzlich hinter
`load`, nicht hinter `defer`.

**Ein Zustand, den niemand absichtlich herbeiführt, ist der, den niemand
nachmisst.** `init-error` und `context-lost` fehlten in der Schleierliste für
schmale Schirme und bekamen dort den Verlauf für breite: 3,5 % der Überschrift
unter 4.5:1, schlechteste Stelle 1,59:1. Wer eine Zustandsliste anlegt, prüft
sie gegen die Liste der Zustände, die es wirklich gibt.

**Der Film aus der Farm läuft einmal, nicht im Kreis.** Gemessen: Bild 239→240
unterscheidet sich um 0,91, Bild 1→2 um 0,44, Bild 240→1 um 48,38. Die Fahrt
blendet an beiden Enden weich ein und aus und endet woanders, als sie beginnt.
`autoplay muted playsinline`, kein `loop`.

---

## 14.09.2026, abends — Jede Bühne braucht denselben Notausgang

**Ein Rückfall, den niemand absichtlich auslöst, ist der, den niemand nachmisst
— also muss man ihn herbeiführen.** Vier Ausfälle, echt erzwungen statt
simuliert: `getContext` liefert `null`, das Kontextverlust-Ereignis wird
ausgelöst, der Import von three.js hängt, die Grafik ist zu schwach. Die
Startseite hielt alle vier. Die Showroom-Seite keinen: Bei Kontextverlust und
hängendem Aufbau blieb der Zustand überhaupt leer, und „aus" hieß nur, die
Leinwand zu verstecken — gemessene mittlere Helligkeit 17,8 von 255, fünf
Prozent der Fläche sichtbar. Jetzt 42,5, mit Standbild und lesbarem Text.

**Vier Stücke gehören zu jeder 3D-Seite**, und zwar zusammen: ein Standbild
(nur im ersten Bildschirm, es rollt mit ihm weg); ein Netz **außerhalb** des
Modulgraphen (ein gewöhnliches Skript ohne Abhängigkeit — ein Netz im Modul
greift bei hängendem Import nie, es wird selbst nie geladen); **dieselbe**
Weiche wie die Hauptseite, importiert statt kopiert (zwei Kopien sind zwei
Wahrheiten — hier urteilten sie verschieden: dieselbe Haswell-Karte bekam auf
der Startseite das Standbild und versuchte hier den vollen Saal mit Bloom);
und ein **Ende der Leiter** — wer auch auf der untersten Stufe unter 24
Bildern/s bleibt, bekommt das Bild, statt dort ewig weiterzuruckeln.

**Unsichtbares kostet trotzdem.** `backdrop-filter` auf Kopfleiste, Fuß und
Kontaktkasten fällt in den sechs Rückfallzuständen weg: Ohne Bühne liegt dort
einfarbiger Grund, der Weichzeichner verwischt eine Fläche, die überall gleich
aussieht. Einzeln gemessen, größter Pixelunterschied **1 von 255** bei jedem
der drei. Dieselbe Regel wie beim Schleier am Morgen: Eine Ebene mit
Deckkraft 0 ist immer noch eine Ebene, und ein Weichzeichner ohne Motiv ist
immer noch ein Weichzeichner.

**Ein Farbverlauf muss an seinem dunkelsten Punkt tragen, nicht im Mittel.**
Der Hauptknopf der Showroom-Seite: Schrift `#03060c` gegen `#0648e8` nur
2,99:1, gegen `#1fe8ff` 13,57:1 — gemessen 36,8 % der Knopffläche unter 4.5:1,
schlechteste Stelle 1,01:1. Der Verlauf beginnt jetzt bei `#0d85f7` (5,52:1).
Seite insgesamt 2,10 % → 0,15 %.

### 14.09.2026 — Die Tür hieß „Kunden" und dahinter standen keine

Uwe: „In der Verwaltung werden die schon hinterlegten Kunden nicht mehr
angezeigt wie Cavaleri."

Er hatte recht, und es war mein Fehler von gestern.

#### Was passiert war

Beim Umbau auf fünf Menüpunkte fiel „Kunden" als eigener Punkt weg — die
Vorgangsliste sollte ihn ersetzen und heißt seither so. Sie ersetzt ihn aber
nur für Kunden **mit** Vorgang: `Vorgang::alle()` baut die Liste aus
`orders` und `anfragen`. Ein Kunde, der von Hand angelegt wurde und weder
Bestellung noch Anfrage hat — Cavaleri —, kommt darin nicht vor.

Er stand also hinter einer Tür mit seinem Namen und war trotzdem nicht da. Die
Kundenliste gab es weiter, nur führte **kein einziger Klick** mehr hin: Alle
Verweise auf `kunden` standen in Seiten, die man erst über einen Kunden
erreicht. Auffindbar war er nur über die Suche — also nur, wenn man seinen
Namen schon kennt.

Nachgestellt mit zwei Kunden ohne Vorgang: Die Seite zeigte „Noch kein
Vorgang", während zwei Kunden in der Tabelle standen.

#### Warum die Kette es nicht gemerkt hat

An der Stelle stand:

```php
pruefe('die Suche findet Kunden weiterhin', str_contains($sbIndex, "case 'suche':"));
```

Das prüft, dass irgendwo im Verteiler das Wort „suche" vorkommt. Sonst nichts.
Sie war grün, während der Weg zur Kundenliste weg war — **eine Prüfung, die
nichts prüft, ist schlimmer als keine**, weil sie die Stelle als geprüft
markiert.

Das ist derselbe Fehler wie bei der Stille-Prüfung gestern, nur eine Stufe
gefährlicher: Dort war die Prüfung grün, ohne zu messen; hier war sie grün,
während der Fehler schon live stand.

#### Was jetzt gilt

`Alle Kunden` steht als erster Unterpunkt unter der Tür „Kunden". Die
Vorgangsliste trägt einen Knopf dorthin, nennt im Kopf die Gesamtzahl und sagt
in einem eigenen Kasten, **wie viele Kunden gerade keinen laufenden Vorgang
haben** — mit einem Knopf daneben. Ist die Liste leer, steht dort nicht mehr
„Noch kein Vorgang", sondern: „Gerade läuft kein Vorgang. Deine 2 Kunden stehen
weiter in der Kundenliste."

Gezählt wird nicht mit einer eigenen Abfrage, sondern gegen genau die Liste,
die gleich angezeigt wird: alle Kunden minus die, die darin vorkommen. Eine
zweite Abfrage könnte anders zählen als die Liste zeigt, und dann stünde auf
der Seite eine Zahl, die sich nicht nachzählen lässt.

Die Kundenliste heißt jetzt „Alle Kunden" statt „Kunden" — zwei Seiten mit
derselben Überschrift, und man weiß beim Blick nach oben nicht, auf welcher man
steht.

#### Geprüft

Die hohle Prüfung ist weg. An ihrer Stelle steht der **Weg**: Die Tür heißt
„Kunden", also muss dahinter etwas liegen, das alle Kunden zeigt. Dazu legt die
Kette einen Kunden ohne Bestellung und ohne Anfrage an und prüft dreierlei —
dass er keinen Vorgang hat (richtig), dass die Vorgangsliste ihn trotzdem
zählt, und dass die Kundenliste ihn zeigt.

**1.325 Prüfungen, alle grün.** Sabotageprobe: Nimmt man `Alle Kunden` wieder
aus dem Menü — also den Zustand von gestern —, fällt „unter der Tür „Kunden"
liegt auch die vollständige Kundenliste". Der Fehler, der gestern durchkam,
käme heute nicht mehr durch.

## Aufräumen: was ausgeliefert wurde, ohne dass es sollte (15.09.2026)

Anlass war eine einfache Frage — ob man Unnützes aus dem Repository nehmen
kann, damit es ohne Nachfragen läuft. Gemessen wurde zuerst, entfernt danach.

**Die Pages-Vorschau lieferte den Quelltext aus.** `pages.yml` lud mit
`path: .` das ganze Repository hoch. `https://vecom2709.github.io/vecom-design/kunde.php`
kam mit **Status 200 und 60.513 Byte PHP** zurück, `app/pruefung/kette.php`
mit **353.195 Byte**. Auf All-Inkl ist die eine per Deploy-Ausschluss draußen
und die andere per `.htaccess` gesperrt — auf Pages gibt es weder Apache noch
PHP, also greift keine der beiden Sperren. **Merksatz: Eine Sperre wirkt dort,
wo sie gelesen wird. Ein zweiter Auslieferungsweg erbt sie nicht.** Die
Vorschau sammelt jetzt ein, statt auszuschließen — eine neue Datei unter
`app/` ist damit von sich aus draußen —, und jede Seite bekommt ein `noindex`,
damit die Kopie der Hauptdomain keine Sichtbarkeit wegnimmt.

**`/de/` und `/en/` lagen im Repository, obwohl `build.mjs` sie erzeugt.**
Beide Abläufe bauen sie vor dem Hochladen neu. Beleg: gelöscht, `node
build.mjs`, alle sechs Dateien byte-identisch wieder da. Sie sind jetzt in
`.gitignore`. **Die drei italienischen Seiten bleiben versioniert — sie sind
Quelle UND Ziel** (`build.mjs`, Zeile 103). Der erste Versuch nahm
`prezzi.html` mit; der Lauf brach mit ENOENT ab, und zwar erst *nach*
`de/index.html`, also mitten in der Arbeit. Ein halb gebautes Verzeichnis
sieht wie ein erfolgreicher Lauf aus, wenn man nur hinsieht, ob Dateien da
sind.

**Sechs Megabyte toter Film.** `erklaervideo-de/-en/-it.mp4` sind die
Vorgänger des Ablauf-Films, seit dem Umbau nirgends verlinkt. Dazu die tote
Kette der alten Unterseite: `assets/js/world/main.js` wird von keiner Datei
geladen, `story.js` nur von `main.js`, `world.css` von niemandem. `scene.js`
bleibt — die lädt auch `site-world.js`. Alle sechs stehen in der Abrissliste;
ohne sie wären sie aus dem Repository verschwunden und auf dem Webspace
liegengeblieben.

**Benutzer und Server standen im Klartext in `ftp-deploy.yml`.** Das Passwort
lag richtig als Secret — aber ein öffentliches Repository, das Benutzer und
Server nennt, hat die Anmeldung zur Hälfte verraten. Beide kommen jetzt aus
Repository-Variablen, vorerst mit Rückfall auf die alten Werte, damit nichts
hängenbleibt.

#### Was dabei *nicht* angefasst wurde

- **`origin/glas-gravur`** ist kein toter Zweig: 204 Zeilen Glas- und
  Gravur-CSS, von denen kein Selektor in `main` steckt. Liegengeblieben, nicht
  verworfen — der Zweig hängt 23 Commits zurück und will vor dem Zusammenführen
  neu aufgesetzt werden.
- **`assets/img/geraete/buehne-*.webp`** erscheinen in keiner Seite, sind aber
  in ihrer README mit Herkunft, Maßstab (31,35 px/cm) und Lizenz dokumentiert.
  Zusammen 36 KB. Dokumentiertes Ausgangsmaterial wirft man nicht weg, um 36 KB
  zu sparen.
- **Die History.** 58 MB `.git`, davon rund 20 MB alte Videofassungen. Ein
  `filter-repo` würde alle 342 Commit-Nummern ändern — bei zwei Arbeitskopien
  und automatischem Deploy ist das teurer als die 20 MB.

#### Geprüft

154 PHP-Dateien ohne Syntaxfehler. Alle vier Workflows gültiges YAML. Sieben
Seiten örtlich im Browser, **keine einzige 404** durch die Entfernungen.

Und eine Falle, die beinahe als Fehler gemeldet worden wäre: Beim örtlichen
Lauf landeten `/de/` und `/en/` auf `/` mit `lang=it` — das sah nach einer
kaputten Sprachauslieferung aus. Gegen die Live-Seite geprüft: `/de/` liefert
`lang=de`, `/en/` liefert `lang=en`, beide mit dem richtigen Titel. Es war der
eingebaute PHP-Server, der Verzeichnisse anders routet. **Die Tabelle in
CLAUDE.md warnt genau davor, und sie hatte wieder recht.**

### Nachtrag am selben Tag: die Variablen stehen, der Name ist trotzdem draußen

`FTP_USER` und `FTP_HOST` sind bei GitHub als Repository-Variablen angelegt.
Damit konnte der Rückfall aus `ftp-deploy.yml` raus — und bei der Suche danach
kamen drei weitere Stellen zum Vorschein, an denen derselbe Benutzername stand:
zweimal in `cockpit-schutz.yml` (eigener lftp-Aufruf, derselbe Zugang) und
einmal als Anleitung in `cockpit/index.html`. Alle drei sind umgestellt; im
Arbeitsbaum steht der Name jetzt nirgends mehr.

**Das macht ihn nicht ungeschehen.** `git log -S` findet ihn in **zehn
Commits**, und die sind veröffentlicht. Ein öffentliches Repository kann man
nicht zurückrufen: Forks, Klone und Caches haben den Stand. Der wirksame
Schritt steht deshalb nicht hier, sondern im KAS — **neuen FTP-Zugang anlegen,
den alten löschen, `FTP_USER` und das Secret `FTP_PASSWORD` auf den neuen
setzen.** Danach ist der alte Name ein Name ohne Tür. Das Cockpit führt diese
Aufgabe ohnehin schon, mit demselben Grund und aus demselben Anlass.

### 17.09.2026 — Zwei Dinge hießen `.plan`, und die Bestellknöpfe waren tot

Uwe: „Das Fenster von dem Paket 9,90 Euro ist zu groß, passe es an den anderen
großen an."

Die Karte war zu groß — aber nicht, weil jemand eine Breite falsch gesetzt
hatte. Gemessen bei 1600 Punkten: **1600 × 1967 statt 420 × 727**, auf
`position: absolute`, außerhalb ihres Rasterfeldes. Dasselbe bei der
Betreuungskarte daneben und bei den drei Stufen auf der Betreuungsseite.

#### Die Ursache

Die neue Grundriss-Grafik aus „Die Villa im Labor" trägt die Klasse `.plan`:

```css
.plan { position: absolute; inset: 0; width: 100%; height: 100%; }
.plan { pointer-events: none; z-index: 2; }
```

`.plan` war seit Langem die **Preiskarte** (`.plans > .plan`). Die neue Regel
steht später in der Datei und gewinnt gegen die ältere. Jede Preiskarte wurde
damit zum Grundriss-Overlay.

#### Was wirklich kaputt war

Nicht die Größe. **`pointer-events: none`.** An der Mitte beider Bestellknöpfe
lieferte `elementFromPoint` **nichts** zurück — „Dieses Paket anfragen" und
„Deine Domain anfragen" waren nicht anklickbar. Wer Betreuung oder Domain &
Hosting bestellen wollte, konnte es nicht. Auf der Betreuungsseite ebenso, alle
drei Stufen.

Aufgefallen ist nur die Größe. Ein toter Knopf sieht aus wie ein Knopf — das
ist der Grund, warum diese Art Fehler lange steht.

#### Die Reparatur

Der Grundriss heißt jetzt `.grundriss`, samt seiner acht Kindklassen
(`grundriss__grund`, `__raum`, `__luft`, `__tuer`, `__name`, `__mass`,
`__kette`, `__kettentext`) — in `app.css`, in `haus.js` und an dem einen `<svg>`
in `index.html`. Umbenannt wurde der **Neuling**: Die Preiskarte steht an fünf
Stellen im HTML und wird von `pakete-live.js` als Vorlage gesucht.

Die Kindklassen kollidierten heute noch nicht — `plan__price` und
`plan__grund` sind verschiedene Wörter. Sie sind trotzdem mit umbenannt: Zwei
Dinge mit demselben Namensstamm sind keine Stilfrage, sondern eine Falle, die
beim nächsten `plan__name` wieder zuschnappt.

#### Nachgemessen

| | vorher | nachher |
|---|---|---|
| Karte bei 1600 px | 1600 × 1967, absolut | 420 × 727, im Raster |
| `pointer-events` | `none` | `auto` |
| Bestellknöpfe anklickbar | 0 von 5 | **5 von 5** |
| Grundriss | zeichnet | zeichnet (6 Räume, Maße, Türen, Maßkette) |

Kette **1325 Prüfungen, alle grün**. Kein Querscrollen bei 1600, 900 und 390
Punkten.

**Nebenbefund, nicht behoben:** `node build.mjs` schreibt schon auf dem
unveränderten Hauptzweig vier HTML-Seiten um — die eingecheckten Cache-Stempel
für `fonts.css`, `app.js`, `i18n-it.js`, `sprachhinweis.js` und
`pakete-live.js` stimmen nicht mehr mit dem Inhalt überein. Der Deploy baut
ohnehin, die ausgelieferte Seite ist also richtig; das Repository und der
Bauschritt sind nur verschiedener Meinung darüber, was drinsteht.

---

### 21.09.2026 — Durchsicht des Zahlungssystems: der Bezahllink stirbt nicht mehr

**Eine Stripe-Bezahlseite lebt höchstens 24 Stunden** (expires_at 30 min – 24 h,
nicht verlängerbar). Trotzdem stand genau diese Adresse in jeder Zahlungsmail
und auf den Knöpfen der Kundenseite, die Monatsmail mit sieben Tagen Frist,
die Verwaltung mit „gültig bis" +14 Tage. Nach außen geht jetzt nur noch
`/bezahlen.php?t=<Kundenschlüssel>&z=<Rate>` (`Bezahllink.php`): fragt beim
Klick die letzte Seite ab, bucht sie, wenn bezahlt, führt auf sie, wenn sie
läuft, und legt sonst eine frische mit dem jetzt geltenden Betrag an.
**Regel:** Eine Stripe-URL gehört nie in eine Mail, nur `Bezahllink::fuer()`.

Außerdem: Wiederholungen von Stripe nach einem Webhook-Fehler wurden als
„bereits verarbeitet" verworfen (`Webhook::annehmen`); gebucht wird nur noch,
wenn Betrag und Währung zur Rate passen (`Events::zahlungVonStripe`), sonst
laute Meldung; Webhook ohne Stripe-Kopfzeile schreibt nichts mehr; `FOR UPDATE`
beim Buchen; Fehlschlag einer Monatsrate ohne Zugriff auf eine nicht
vorhandene Bestellung. Kette **1368 Prüfungen** (Abschnitt 55), jeder der vier
alten Fehler in der Gegenprobe gefangen.

### 21.09.2026 — „Die stille Rechnung" ist raus

Auf Uwes Wunsch komplett entfernt: Abschnitt `#luecke` („Rechne es dir aus.
Mit deinen Zahlen."), Navigationspunkt, `luecke.js` (steht in der
Abrissliste), `.rechnung`-Stile, Texte in drei Sprachen und der Kamerazustand
in `site-beats.js`. Der zweite Hero-Knopf führt jetzt auf die Preise
(„Was es kostet" → `#plans`) statt ins Leere.

### 21.09.2026 — Erst der große Fragebogen, dann der Preis
Der Fragebogen hing an `project_id NOT NULL`, ein Projekt entsteht erst mit der Anzahlung — also kamen Preis und Zahlungslink zwangsläufig vor dem Fragebogen. Jetzt gehört er dem Kunden (Migration 049), liegt ab der Anfrage auf der Kundenseite, und Angebot senden, Zahlungslink erzeugen/senden und Direktbuchung einer Website sind hart gesperrt, bis er abgeschickt ist; bei der Zahlung wandert er ins Projekt, ohne zweite Einladung.
Die Führung zeigt dafür „Fragebogen verschicken“ (Einladung bleibt Uwes Klick); Kette Abschnitt 56, Gegenprobe mit vier Sabotagen, alle gefangen.

### 22.09.2026 — Die Erlebnisseite („Was könnte deine Website?")
Neue dreisprachige Seite (`esperienza.html`, `de/erlebnis.html`, `en/experience.html`) auf dem Zweig `vecom-experience`, **nicht** auf `main`: Zusammenführen erst, wenn Uwe die örtliche Vorschau gesehen hat (`node tools/vorschau.mjs`, dann `http://127.0.0.1:8090/de/erlebnis.html` — PHP läuft dort nicht, die Knöpfe zum Konfigurator gehen erst live). Sie zeigt fünf Standpunkte × fünf Tageszeiten als gerechnete Bilder aus Blender Cycles; auf Wunsch legt sich das Echtzeitmodell passgenau darüber (gleiche Kamera, Brennweite, Objektivversatz, gleicher Beschnitt) und weicht beim Loslassen wieder dem Foto. Dazu: Wegweiser Ziel → Branche, die Tischdrehung aus 36 gerechneten Bildern, die Stufen aus `experience-core`, Technik in zwei Ebenen.
Gemessen statt geschätzt, und deshalb festgehalten: Die Sonne der Fotos kommt aus der Sonnenscheibe des Nishita-Himmels, nicht aus der Lampe — in Three.js ist ihre Richtung `(-sin az·cos h, sin h, -cos az·cos h)` (vier Kandidaten gerechnet, Korrelation 0,73 gegen 0,39 für die naheliegende Umrechnung). `haus.glb` hat **32.280** Dreiecke, nicht 9.576 wie im Manifest. Die Innenraum-Belichtungen aus `villa_szene.KAMERAS` (19.09.) gelten nur ohne echtes Glas; mit Glas und Kaustik liegt der Wohnraum rund sechs Blenden darunter — `lauf_ruhebilder.py` misst innen jetzt selbst. **Wer einen Standpunkt in `villa_szene.KAMERAS` verschiebt, muss `STAENDE` in `assets/js/erlebnis/villa-echtzeit.js` nachziehen**, sonst liegt das Modell beim Überblenden neben dem Foto.

### 22.09.2026 — Der Erlebnisteil ist die Startseite
Zwei Stunden nach dem Livegang als eigene Seite auf Uwes Ansage zusammengelegt: Was unter `erlebnis.html` stand, ist jetzt der Hauptbereich der Startseite (`#erlebnis`, gleich hinter der Vorstellung). Dafür sind drei Abschnitte gewichen, deren Inhalt er ersetzt: die Werkbank „Zum Ausprobieren", das Haus-Labor und „Echtzeit statt Standbild". Die alten Adressen leiten per `.htaccess` dauerhaft auf die Startseite der jeweiligen Sprache um — der Deploy löscht nie, ohne die Regel lägen sie doppelt indexiert weiter oben.
`assets/css/erlebnis.css` gilt seitdem **nur innerhalb von `.erlebnis`** (Farbwerte auf `.erlebnis` statt `:root`), damit es sich mit `app.css` nicht ins Gehege kommt; `erlebnis.js` prüft jedes Bauteil einzeln und überspringt, was auf einer Seite fehlt. Gemessen vorher/nachher unter vierfacher Drosselung: LCP 7,32 s → 7,36 s am Telefon, Seitengewicht 1.206 KB → 1.167 KB (werkbank.js und haus.js fallen weg). **Was dabei verloren ging und noch fehlt:** Grundriss, Bauablauf und Begehung aus `haus.js` — die Datei liegt unbenutzt im Repository und wartet auf einen Platz in der neuen Bühne.

### 22.09.2026 — Das Angebot stand nur in der Mail
Uwes Stufenleiste kennt während der ganzen Angebotsphase nur „Gespräch“; übersetzt stand beim Kunden „Deine Anfrage ist da“ — und der einzige Knopf dieser Stufe war der Zahlknopf, den es erst nach der Annahme gibt. Wer die Mail nicht mehr fand, kam nicht weiter.
Jetzt entscheidet auf der Kundenseite die Tatsache: Liegt ein gesendetes Angebot beim Kunden, steht seine Seite auf „Angebot“, mit Knopf, Summe und Gültigkeit. Kette: vier Prüfungen im Abschnitt 56, Gegenprobe gefangen.
### 22.09.2026 — Grundriss, Bauablauf und Begehung sind zurück
Die drei Sachen aus dem alten Haus-Labor stehen jetzt in derselben Bühne wie das Foto, als vierte Chip-Reihe „Ansicht" (Standbild · Grundriss · Bauablauf · Hineingehen). Grundriss: senkrecht von oben, Decke und Obergeschoss abgenommen (`bbox.min.y > 3,2 m` fällt weg), **26 m** über dem Boden bei 26 mm — bei 21 m stieß das Haus oben und rechts an, am Bild nachgemessen. Bauablauf: die acht Abschnitte `p01_`–`p08_` aus `haus-manifest.json`, einzeln anklickbar. Hineingehen: Augenhöhe 1,65 m, ziehen zum Umsehen, W A S D. **Begehbar ist, was im Manifest ein Raum oder ein Türdurchgang ist** — dieselbe Quelle wie der Grundriss, damit nicht zwei Beschreibungen desselben Hauses auseinanderlaufen. Die Achsen werden einzeln geprüft, sonst bleibt man an jeder Wand kleben, statt an ihr entlangzugehen.
Damit ist `haus.js` endgültig ohne Aufgabe; sie liegt samt `werkbank.js` und den i18n-Zweigen `probieren.*`, `haus.*`, `beweis.*` unbenutzt im Repository und kann beim nächsten Aufräumen weg.

### 22.09.2026 — Der Weg ins Cockpit steht auch oben
Seit der Erlebnisteil auf der Startseite liegt, ist sie rund **25.000 Pixel** lang; das Zahnrad in der Fußzeile steht bei y = 24.803. Es war nie weg, nur unerreichbar. Zweites Zahnrad in den Kopfwerkzeugen, gleiche Gestaltung, nur kleiner (34 px statt 44) und zurückhaltender (Deckkraft 0,32 statt 0,5); unter 860 px Breite fällt es weg, weil der Kopf dort den Platz für Sprache, Angebot und Menü braucht.
Nebenbei berichtigt: Auf dem Windows-Rechner liegt seit heute ein eigenes **Git for Windows** (`C:\Program Files\Git\cmd\git.exe`, im Suchpfad). Der in `CLAUDE.md` notierte Umweg über das Git in Visual Studio existiert nicht mehr. Und PowerShell-Befehle gehen als `.ps1`-Datei an den Rechner, nie als `-Command "…"`: Variablen werden auf dem Weg ersetzt, der Befehl läuft ohne sie weiter, und der erste Fehler zeigt dann in die falsche Richtung.

### 22.09.2026 — Keine zweite Einladung zu einer Seite, die der Kunde schon hat
Uwe: „Wenn der Fragebogen im Dashboard ausgefüllt werden soll, macht es keinen Sinn, dass die Führung ‚Fragebogen verschicken‘ anzeigt.“ Stimmt: Die Adresse der Kundenseite steht schon in der Eingangsbestätigung, die nach dem Konfigurator automatisch rausgeht — der Schritt hätte dieselbe Seite ein zweites Mal geschickt.
Jetzt ist in dieser Phase der Kunde dran („Fragebogen ausfüllen“, ohne Knopf); „Fragebogen verschicken“ bleibt nur für Kunden ohne Link, nach fünf stillen Tagen heißt der Schritt „Nachfassen“. Punkteliste vor dem Preis ohne Anzahlung und ohne Pflichtmail. Kette 1422, Gegenprobe gefangen.

### 22.09.2026 — Das Angebot nur noch als Hinweis in der Mail, und eine Frage an Stripe
Die Angebots-Mail trug Betrag und Angebotslink — der Preis stand damit im Vorschautext jedes Postfachs. Jetzt sagt sie nur, dass das Angebot bereitliegt, und führt auf die Kundenseite; dort steht es vollständig und wird angenommen oder abgelehnt.
Zweitens: Hängt eine Rate länger als eine Stunde auf „in Bearbeitung“, fragt die Führung „Bei Stripe nachfragen“ statt zu erinnern — ein Klick bucht, was Stripe als bezahlt kennt. Nebenbefund: Alter von Datenbankzeiten nie mit PHPs Uhr rechnen (UTC/Rom), sondern mit TIMESTAMPDIFF; im Webhook ebenso korrigiert. Kette 1434, vier Gegenproben gefangen.
### 23.09.2026 — Automotive und Shop: die fehlenden Branchen-Techniken
Auf Uwes Hinweis („es fehlen die anderen Techniken, unter anderem Autos, E-Commerce") steht im Erlebnisteil ein neuer Abschnitt `#branchen-demo` zwischen Wegweiser und Tisch: zwei Reiter, **Automotive** (Lack in drei Varianten, Explosionsansicht) und **Onlineshop** (Farbe, Größe, Warenkorb als Demo), der Tisch darunter ist die dritte Technik. Der Wegweiser hat eine neue Branche „Automotive"; Automotive und Industrie führen auf `#bd-auto`, Shop auf `#bd-shop`.
Vorlagen: „Car Concept" (Eric Chadwick / DGG) und „Materials Variants Shoe" (Shopify) aus den Khronos glTF-Sample-Assets, beide **CC BY 4.0** — der Nachweis steht unter dem Abschnitt und muss dort bleiben. Die Khronos- und 3D-Commerce-Logos (Kennzeichen, Reifenflanke als Farbe UND Relief, Lenkrademblem) sind vor dem ersten Import entfernt (`repack.py`), das Kennzeichen trägt jetzt VECOM. Lackflocken 4× feiner und ein Drittel so stark, Perlmutt ohne Schlierentextur — beides im ersten Poster als Sand bzw. Schmutz sichtbar. Die Armbanduhr aus derselben Sammlung ist bewusst nicht dabei: Sie ist erkennbar eine Casio G-Shock Mudmaster.
Kette wie bei der Villa: Blender (`3d-produktion/scripts/branchen_studio.py`, Modi probe/voll/boden/umgebung, auf der RTX 5070 rund 11 s je Poster) → Poster als Sofortbild → Echtzeit erst auf Griff (`produkt-echtzeit.js`, 3,4 MB bzw. 0,9 MB, meshopt + WebP). Drei Dinge, die gemessen und nicht geraten sind: **(1)** Die HDRI (Poly Haven `studio_small_03`) ist ein weißes Hohlkehlenstudio; alles unter 0,9 abgeschnitten, bleiben 2,4 % der Fläche mit 92 % der Energie — ein schwarzes Studio mit echten Leuchten. **(2)** Der Boden hängt per Lichtverknüpfung an einem eigenen Licht; three.js kann das nicht, deshalb kommt seine Beleuchtung als gebackene lightMap (`I = π·Skala/Albedo`, gerechnet) und das ganze Studio samt Flächenlichtern als Rundumbild aus der Modellmitte (`umgebung.hdr`) — ein Deckendiffusor von 3,2 × 6 m bleibt so ein Band auf dem Lack statt eines Punkts. **(3)** Blenders Look „AgX – Medium High Contrast" fehlt in three.js; nachgebaut als CustomToneMapping (Kontrast 1,3 im AgX-Log-Raum, Sättigung 1,25, Belichtung 0,6), am Poster über ein Gitter von 36 Kombinationen ausgewählt — Prüfstand `tools/produkt-probe.html`.
Anfangsgewicht der Startseite: +15 KB (branchen.js); Bilder laden verzögert, Modelle erst beim ersten Griff. Eine Nachtansicht (Studio aus, nur die Leuchten) war gebaut und ist wieder raus: Ohne Bloom glühte nichts, das Auto wurde nur braun.
**Nachtrag 23.09.2026 — Zerlegen wie eine Explosionszeichnung.** Auf Uwes Ansage („beim Zerlegen noch realistischer und detaillierter") fährt nicht mehr jedes Teil strahlenförmig von der Mitte weg, sondern jede Baugruppe auf ihrer konstruktiven Achse und nacheinander: Türen seitlich, Haube vor/hoch, Heck zurück/hoch, Dach senkrecht, Scheibe und Säulen, dann Räder auf der Achse mit Bremsscheibe und Sattel gestaffelt dahinter, zuletzt Antrieb, Sitze, Cockpit — 41 Teile, 3 s. Die Regeln stehen als Daten in `assets/3d/branchen/auto/kamera.json` („zerlegen"), nicht im Code. Neu: Etiketten mit Führungslinien (acht Baugruppen, dreisprachig, im DOM), Echtzeitschatten nur für bewegte Teile (der Kontaktschatten unter dem Auto ist gebacken, ein zweiter hätte doppelt abgedunkelt), Kamera passt sich an die Hülle des zerlegten Modells an — über alle Blickwinkel, weil es sich danach 25 s langsam dreht. Die Kugel-Einpassung der ersten Fassung ließ das Auto als Spielzeug in der Bildmitte stehen.

### 22.09.2026 — Kein Zahlungslink, solange das Angebot beim Kunden liegt
Eine Bestellung kann auch von Hand aus einer Anfrage entstehen; ab da fragte niemand mehr, ob noch ein Angebot auf die Zusage wartet. Der Kunde hätte eine Zahlungsaufforderung über einen Betrag bekommen, dem er nie zugestimmt hat.
`Angebot::wartetAufZusage()` sperrt jetzt „Zahlungslink erzeugen“ und „senden“, solange ein Angebot im Entwurf oder verschickt ist; angenommen, abgelehnt und abgelaufen blockieren nicht, und ohne Angebot (Telefon, Betreuung, Hosting) bleibt alles wie bisher. Führung und Bestellseite zeigen statt des Knopfs den Weg zum Angebot. Kette 1447, drei Gegenproben gefangen.

### 23.09.2026 — Acht Nachbesserungen am Erlebnisteil, ohne ihn schwerer zu machen
Auf Uwes „alles" zu acht Vorschlägen. **Übersicht:** eine schmale Kapitelleiste (Villa · Wegweiser · Auto & Shop · Stufen) bleibt oben stehen und sitzt unter der Kopfleiste, solange die sichtbar ist; Technik, Vergleich und Streaming liegen zugeklappt in einem Block — `#technik`/`#vergleich`/`#streaming` öffnen ihn. Gemessen: Der Erlebnisteil ist am Rechner (1440 × 900) von 7,1 auf 5,4 Bildschirme geschrumpft, am Telefon (390 × 844) von 13,0 auf 8,8. **Ehrlichkeit vor dem Klick:** „Selbst drehen · 2,9 MB / 1,2 MB" ist die gemessene Übertragung (gzip, samt three.js), nicht die Dateigröße. **Auswahl geht mit:** Die Knöpfe unter den Demos rufen `bedarf.php?demo=auto-karmin` bzw. `demo=schuh-rose-42`; nur bekannte Schlüssel kommen durch, die Auswahl belegt den Zweck vor und steht als erste Zeile in der Anfrage (Kette prüft es). **Schuh:** Ansicht „Details" mit drei Namen an gemessenen Punkten (Strahl auf das Netz, in `kamera.json` → `punkte`), blendet aus, wenn sich ein Punkt von der Kamera wegdreht.
**Villa:** Die harte Kante zwischen Rasen und Land war keine Kuppe — das Gelände ist auf 130 m auf ±1 m eben. Aus 2 m Augenhöhe schrumpfte der 56 m breite Verlauf auf 1,1 Grad und las sich als Linealstrich. Jetzt endet der Garten an einem Muretto a secco (`villa_fotoreal.py`, Teil `mauer`): rund 7.800 Bruchsteine in zwei Schalen, dunkler Kern, Krone aus quer gestellten Steinen, 1,0 m hoch, verjüngt von 0,62 auf 0,46 m, Einfahrt im Süden. Mauer und Rasengrenze teilen dieselbe Form (abgerundetes Rechteck, im Shader als Abstandsfeld). Sofa und Sessel mit Kastenkissen samt Keder, Sitzmulden und Eckfalten, losen Rückenkissen und vernähten Zierkissen statt oranger Klötze; Polsterstoffe mit Schimmer (Sheen) und Gewebe. Alle 25 Standbilder neu gerechnet.
**AVIF** zusätzlich zu WebP, immer aus dem PNG (`tools/bilder-avif.py`): am Karminrot-Poster 24 statt 45 KB bei kleinerer Abweichung vom Original. JavaScript entscheidet beim Bildwechsel über ein 1-px-AVIF. **Aufgeräumt:** `haus.js`, `werkbank.js` und die i18n-Zweige `beweis`, `haus`, `probieren` sind aus dem Repository raus. Vom Webspace löscht sie die Abrissliste in `ftp-deploy.yml`; dafür brauchte das gh-Token auf dem Windows-Rechner erst das `workflow`-Recht (am 23.09.2026 per `gh auth refresh -s workflow` ergänzt — der Befehl muss in derselben Windows-Sitzung laufen, in der gh angemeldet ist; eine Administrator-PowerShell meldet „not logged in“). Bis dahin antwortete der Server mit 410.
Werkzeugfalle: Die Dateiübertragung zum Windows-Rechner hat bei gleichem Quellpfad eine **ältere Fassung** geschrieben — die zweite Probe lief mit dem alten Skript. Seitdem je Übertragung ein neuer Dateiname und danach die Prüfsumme vergleichen (wie in `CLAUDE.md` für Bilder schon verlangt).

### 23.09.2026 — Die gewählte Sprache trägt jetzt durch alle Seiten
Jede Seite löste die Sprachfrage für sich, und keine PHP-Seite hat die Wahl je gemerkt: Wer auf der Kundenseite DE wählte, bekam auf legal.html wieder Italienisch — ausgerechnet bei AGB und Datenschutz. Das Angebot hatte gar keinen Umschalter.
`app/src/Sprache.php` beantwortet die Frage jetzt an einer Stelle (Adresse → Keks → Sache → Italienisch), jede öffentliche Seite merkt die Wahl im Keks `vecomlang`, alle Rechtsverweise tragen `?lang=`, Kundenlinks in Mails tragen die Sprache des Kunden, und das Angebot hat einen Umschalter. app.js und sprachhinweis.js lesen den Keks vor dem localStorage. Kette 1478, vier Gegenproben gefangen.

### 23.09.2026 — Der Tisch flackerte beim Drehen
Uwe: „der Tisch flackert beim Drehen". Gemessen, zwei Ursachen: Die Drehung mischte zwei Bildsätze (360 px vorab, 1600 px nachgeladen) — scharf, weich, scharf; und die Messingwangen springen bei 10°-Schritten von dunkel auf voll beleuchtet (mittlere Helligkeit bis zu 15 Stufen von einem Bild zum nächsten). Jetzt zeichnet ein `<canvas>` nur aus vollständig dekodierten Bildern *eines* Satzes und blendet zwischen Nachbarbildern über; beim Loslassen rastet die Stellung auf ein ganzes Bild. Im Browser beim Ziehen gemessen: größter Helligkeitssprung von einem Bild zum nächsten 12,9 → 4,2.

### 23.09.2026 — Nachschliff nach dem ersten Durchgang
Auf Uwes „ok alles" zu acht weiteren Vorschlägen:
- **Schuh:** Auf der Zwischensohle stand eine Prägung „…FOAM" in der Normal-Textur der Shopify-Vorlage — der Satz „Logos entfernt" stimmte damit nicht. Weggenommen, indem das Schaumgewebe von weiter oben auf derselben UV-Insel darübergelegt wurde (Quelle `3d-produktion/branchen/quelle/schuh.glb`, Original daneben als `schuh-original-khronos.glb`), Poster neu gerechnet, Web-GLB mit derselben Textur.
- **Villa in Echtzeit:** Trockenmauer (dieselbe Linie wie in Blender, als Band mit gemalter Steintextur, rund 5.000 Dreiecke, nichts nachzuladen) und trockenes Land außerhalb per Shader — vorher lief der Rasen bis an eine harte Kante am Horizont.
- **Auto zerlegt auf dem Telefon:** Unter 520 px Bühnenbreite nur Türen, Haube, Räder, Antrieb; acht Schilder deckten auf 390 px das halbe Auto zu.
- **Zählen ohne Personendaten:** `d.php` (wie `z.php`: keine IP, kein Cookie; Datum, Stunde, Ereignis, Gerät) für Drehen, Zerlegen, Details, Warenkorb und die beiden „So etwas für …"-Knöpfe. `d.php?summe=1` gibt die Zählung der letzten 30 Tage als JSON. Ob daraus eine Anfrage wird, steht in der Anfrage selbst („Ausgangspunkt: …").
- **Gemessen statt vermutet:** Telefon (4× CPU, langsames 4G): Layout-Sprünge 0, LCP 1,9–4,1 s je nach Serverantwort (TTFB 0,2–1,4 s), Hauptfaden blockiert 1,5–1,9 s — größte Posten ScrollTrigger, start.js, app.js. `content-visibility` auf den Abschnitten brachte in vier Läufen keinen belastbaren Gewinn und bleibt deshalb weg. Gefunden und behoben: `branchen.js` legte beim Start bei jeder WebGL-Prüfung einen neuen Kontext an, ohne ihn freizugeben (148 → 89 ms). RTX 5070: Auto und Schuh bei 2560 × 1440 an der 60-Hz-Grenze, also mit Luft.
- **Villa-Knopf:** „Selbst drehen · 0,4 MB" wie bei Auto und Schuh.
- **Bewusst nicht gemacht:** Das Auto umgestalten. Beim genauen Hinsehen ist es der Konzeptentwurf der Khronos-Vorlage ohne Markenmerkmale; eine bestimmte Marke ließ sich nicht festmachen, und Umbauen auf Verdacht hätte nur die Qualität gekostet. Und keine Preisangabe an den Demos, solange Uwe keinen Einstiegspreis nennt — im Baukasten gibt es dafür noch keinen Baustein.

### 23.09.2026 — Drei Autos in der Automotive-Demo: Kleinwagen, Mittelklasse, Sportwagen
- **Eigene Entwürfe mit echten Klassenmaßen statt Nachbauten:** Kleinwagen 4,07 × 1,76 × 1,45 m (Radstand 2,57, Dreizylinder quer, Verbundlenker), Mittelklasse 4,76 × 1,83 × 1,44 m (2,85, Vierzylinder längs, Hinterradantrieb, Mehrlenker) — Maße und Bauweise aus den Datenblättern von Polo, Clio, 3er G20, Passat B9 und C-Klasse, die Form bewusst keiner Marke nachgebaut (Formschutz). Nach Uwes „zu kantig" komplett auf heutige Proportionen umgebaut: runde Schnauze, schräge Scheibe, abfallendes Dach. Gebaut parametrisch in Blender (`3d-produktion/scripts/fahrzeug_bau.py` + `fz_*.py`), Poster aus demselben Studio wie der Sportwagen.
- **Web je Teil vereinfacht, Blech feiner als der Rest** (`tools/fahrzeug-web.py`, `fahrzeug-vereinfachen.mjs`): einheitliche Vereinfachung gab helle Dellen im Kotflügel. Jetzt je rund 240 000 Dreiecke, 1,1 MB Übertragung samt three.js; der Sportwagen bleibt unverändert.

### 23.09.2026 — Keine Fertigungszeit mehr im Schaufenster
Uwe: „Keine Fertigungszeit angeben, sondern ausbessern je nach Aufwand.“ Auf der Seite standen „In zwei Wochen online“, „Online in 2–6 Wochen“, „2 Wochen bis zur fertigen Seite“, in den FAQ „ein bis zwei Wochen … ein Shop vier bis sechs“ und in den Textbausteinen „meist zwei bis vier Wochen“.
Jetzt steht überall, wovon es abhängt: Kennzahl „Zeitrahmen — nach Aufwand, nicht nach Schema“, Ablaufschritte ohne Tage/Wochen, FAQ und Vorlagen sagen zu, dass die Dauer genannt wird, sobald der Umfang feststeht. Kettenabschnitt 60 sucht in den Texten nach Wochenzahlen und reißt, wenn eine zurückkommt.

### 23.09.2026 — Serienautos: Fahrerplatz, Ausstattung, Zerlegen in Stufen, echte Anzeigen
- **Türen:** eine gemeinsame Fuge statt 3,3-cm-Blechstreifen, Türen öffnen an Scharnieren (Empties `tuer_*_angel` mit Achse/Winkel als extras), Innenseite mit Verkleidung, Dichtung, Schloss. Der Dichtungsschlauch schloss offene Kantenzüge quer durchs Fenster (schwarze Diagonale) — jetzt je Zug verfolgt. Das Türausschnitt-Band nur noch unter der Gürtellinie.
- **Weißer Keil an der A-Säule** — dreimal falsch vermutet (Himmel, Fritte, Schirm der HDRI), dann mit neuen Messwerkzeugen in `branchen_studio.py` gefunden: `strahl=u:v` (Objekte/Material hinter einem Pixel samt Spiegelrichtung), `glas=spiegel|durch|gerade`, `nur=frontscheibe`. Ursache: Die Frontscheibe spiegelt die Deckensoftbox unter flachem Winkel, eine Welligkeit der Krümmung machte daraus eine Wolke mit Beulen. Schattierungsnormalen der Scheibe geglättet, Glas im Studio als dünne Scheibe mit Polfilter (0,3), wie am Set.
- **Anzeigen (`fz_displays.py` v2):** 3D-Navigation mit Gebäuden, Dunst und leuchtender Route; Rundinstrumente mit Verlaufsbogen und Lichthof; Fahrspuransicht mit dem Auto als Cycles-Freisteller; Deckglas entspiegelt (Klarlack 0,3/0,09 statt 1,0/0,01 — vorher standen Himmel und Sitz als weiße Flächen auf der Karte).
- **Lenkrad:** Die Radachse zeigte zum Fahrer nach unten, das Rad „hing" (Uwe). Umgedreht, dann per Sichtlinie gerechnet: Rad 3–4 cm tiefer, Kombi 3 cm höher → 93/94 % des Kombis frei statt 32/55 %.
- **Reifen:** Flankenschrift als Normalkarte (Größe, Tragfähigkeit, keine Marke), UV je Dreiecksecke gegen die Umfangsnaht. Web: Texturgrößen positiv benennen — das Ausschlussmuster traf über die leere URI jede Textur, Displays und Reifen landeten bei 512 px.
- **Rundumbild auf 0,66 m statt 2,95 m gebacken:** Der Stoffträger der Ausstattungen (5 m unter dem Boden) kam beim Import mit und machte das Modell 6,6 m hoch; das Web-Auto wirkte milchig (Umgebung 1,76× heller). Spiegelungsumgebung jetzt 2048 px. Übertragung je Auto 3,2 MB samt three.js (vorher 1,1 — ohne Innenraum).


### 23.09.2026 — Demo-Galerie und neue Branchen: Wein, Schmuck & Uhren
Uwe: „auto, haus, tisch usw klein nebeneinander … erst mit Klick wie gehabt", dazu weitere Branchen (auf „Alles" zu sieben Vorschlägen).
- **Galerie:** Kacheln nebeneinander, die Bühne öffnet sich erst auf Klick darunter (`erlebnis.js`, `data-demo-buehne`); eine gemeinsame Produktbühne (`#produkt-demo`, `branchen.js` PRODUKTE/PRODUKT_TEXTE) wechselt das Modell je Kachel.
- **Pipeline je Produkt:** Blender-Bauskript `3d-produktion/scripts/pr_<was>.py` → Studio des Schuhs, auf die Produktgröße skaliert (`branchen_studio.py`) → `tools/produkt-web.py` (Zerlegeregeln und Texturgrößen als Daten) → `tools/bilder-avif.py`.
- **Wein & Naturprodukte:** Bordeaux-Flasche mit drei Etiketten, Holzkiste mit Tür; 1,6 MB.
- **Schmuck & Uhren:** Automatikuhr 40 mm (Saphirglas, Werk mit Rotor, Unruh und Lagersteinen, Lederband mit Naht und Schlaufen) und Solitärring; Varianten Edelstahl/Saphir, Gelbgold/Diamant, Roségold/Rubin; zerlegt in Glas & Lünette, Zeiger & Zifferblatt, Werk. 1,2 MB. Beim Zerlegen passt die Kamera nur den Uhrkopf ein (`zerlegen.fokus`) — mit dem ganzen Stillleben war die Uhr ein Punkt.
- **Gemessen statt vermutet (Schmuck):** Das gewölbte Glas wirkte wie eine Lupe — Ursache waren gemittelte Normalen über die 90-Grad-Kante, nicht die Wölbung (harte Kanten ab 30° in `pr_basis.drehkoerper`). Das Band war hell, weil nur die Umrissecken verformt wurden: Ober- und Unterseite blieben ebene Vielecke, das Band lag als schräge Platte über dem Tisch und verschluckte die Naht (Strahlmessung). Das Zifferblatt lag im Schatten des eigenen Glases (Cycles lässt direktes Licht nicht durch massives Glas) — im Studio jetzt dünne Scheibe, entspiegelt, nur die Oberseite spiegelt voll. Neue Prüfoptionen: `diffus=`, `welt=`, `hdri_dreh=`, `blende=`.
- **Küchenbau:** Kochinsel 2,40 m nach Küchennorm (Arbeitshöhe 910 mm, Korpus 560 mm, Fugen 3 mm) mit Auszügen, zwei Türen an Topfscharnieren, Unterbaubecken, Armatur und Induktionskochfeld; Varianten Salbei/Eiche/Messing, Weiß/Carrara/Edelstahl, Nussbaum/Keramik/Schwarz. Öffnen in zwei Schritten (Türen und Auszüge, dann Platte abheben). 1,6 MB. Eigenes Licht im Studio: Unter der Deckensoftbox der Produktbühne bekam die Platte achtmal so viel Licht wie die Fronten — Hauptlicht jetzt von vorn links wie im Küchenstudio. Furnier läuft über alle Fronten durch (UV aus Weltkoordinaten), sonst trugen beide Türen dasselbe Maserbild.
- **Gastronomie:** Tisch für zwei nach der Grundregel des Service (Speiseteller 2 cm von der Kante, Gabel links, Messer und Löffel rechts, Brotteller mit Buttermesser, Wein- und Wasserglas), Leinendecke als verformtes Gitter mit Kantenrundung, Seitenwellen, Eckfalten und Bügelfalten; Varianten weiß/Porzellan, Terrakotta/Steingut sand, Anthrazit/Steingut schwarz. Schritt „Abend“: das Studio geht aus, die Kerze trägt ein Punktlicht (`kerzenlicht` im Modell). 1,2 MB. Gemessen: Die dunklen Decken lagen mittelgrau im Bild — Ursache war der Sheen-Anteil (weiß, auch bei 0,2), nicht die Belichtung. Die Decke bleibt im Web unvereinfacht (`FEIN`): vereinfacht verschoben sich ihre zwei Lagen, unter dem Kerzenlicht zeigten sich Sägezähne.
- **Friseur & Salon:** Bedienplatz mit hydraulischem Friseurstuhl (Polster als gerundete Körper, Säule, Pumphebel, Fußstütze), Spiegel 30 mm vor der Wand mit LED dahinter, Ablage mit Produkten; Varianten Cognac/Messing/Salbei, Schwarz/Chrom/Kalk, Samt Petrol/Schwarz/Anthrazit. Schritte: Stuhl dreht zum Gast und fährt 80 mm hoch; Abendlicht (nur der Spiegel leuchtet, Punktlicht `spiegellicht`). 1,2 MB. Gemessen: Der Spiegel zeigte die Deckensoftbox als weißes Viereck — im Foto nimmt die Lichtverknüpfung den Spiegel als Empfänger der Studioleuchten aus (wie eine Flagge am Set), im Web wird er zu dunklem Glas (`web_material`), weil three.js ein Flächenlicht nicht je Material ausnehmen kann. Samt: weißer Sheen machte Petrol hellgrau, Glanz jetzt in der eigenen Farbe.
- **Logistik:** Sattelzug nach EU-Maßen (16,50 m, Zugmaschine 4x2 mit Kippkabine, Zwillingsbereifung, Planenauflieger 13,6 m mit drei Achsen, 23 Europaletten foliert), Planendruck als eigene Marke („VECOM Logistik“, keine fremde); Varianten Rot/weiß, Weiß/grau, Blau/blau. Schritte: Plane fährt in acht Feldern nach hinten zusammen, Kabine kippt, Räder gehen auseinander. 1,1 MB. Gemessen: Nach dem Zusammenfügen lag der Ursprung der Palette am ersten Klotz, gedreht standen die Paletten seitlich aus dem Auflieger — Ursprung jetzt in der Palettenmitte. Grenze: Die Kabine ist eine vereinfachte, markenlose Form (gerundete Grundkörper); für Nahaufnahmen wäre ein eigenes Kabinenmodell der nächste Schritt.

### 24.09.2026 — Jede Branche spricht ihre Kunden an: Planer, Haarfarben, Einschenken, Logo, AR, Film
Uwe: Küche „wie bei Ikea mit Maßen zusammenbauen", Friseur „passendes statt Stuhl der sich dreht", und „jede Branche möchte seine Kunden ansprechen". Auf die Vorschlagsliste: alles Ja (U1–U4, A1–A5, B1, B3, B5, B6+B7).
- **Küchenplaner** statt Kochinsel zum Drehen: Form (Zeile, L, U, Insel), Wandmaße in cm, automatische Planung mit Passblenden 1–15 cm, Prüfhinweise (Kühlschrank fehlt, Laufweg), Code der Planung geht mit der Anfrage mit (`Bedarf::planPruefen`). 3D in `kuechenplaner-3d.js`, Kamera je Form begrenzt, damit sie nie hinter einer Wand steht.
- **Haarfarben** statt Friseurstuhl: Cycles-Drehung eines Kopfes (110 000 Strähnen, Principled Hair, Chiang) in sechs Farben, Überblenden zwischen Farben. Gemessen: der Pinsel-Look kam vom Entrauscher, nicht vom Haar — Entrauschen aus. Aschblond und Roségold über direkte Farbe statt Melanin (Melanin kann kein Asch). Zweite Fassung (v8) nach eigener Durchsicht: Von hinten las sich Kopf plus halbe Ellipsoid-Büste als Glocke — jetzt Oberkörper aus einem Profil (Nacken, Trapez mit 20° Gefälle, Schulterdach 42 cm) unter mattem Umhang, Haar mit Volumen (2–3 cm am Oberkopf), fällt hinter und vor die Schultern statt als Säule; Rauschen je Haar statt je Punkt (vorher wie Stroh geknickt).
- **Uhr (U1–U4):** Explosionsansicht in zwei Säulen, Uhrmacher-Tablett, Gravur live auf dem Boden, Ring mit Karatwahl (Stein skaliert mit der Kubikwurzel). Ring im Lichtzelt (RoomEnvironment) — im Studiobild spiegelte poliertes Metall nur Schwarz.
- **Wein (B1):** Kiste für 1/2/3 Flaschen, Glas füllt sich beim Einschenken. **Gastro (B3):** Pasta und Dessert angerichtet, Menükarte mit Restaurantname, Knopf „Tisch reservieren". **Logistik (B5):** Ladeplaner 1–33 Paletten mit Auslastung und Lademetern; Fahranimation und genauere Kabine nicht gebaut — die Fahrt zeigt der Film. **Auto/Tisch (B6/B7):** Probefahrt mit gewählter Farbe, Tisch mit Holz und Maßen.
- **Alle Demos (A1–A4):** Kundenlogo aufs Produkt (auch Normal-/Rauheitskarten ersetzt, sonst blieb das alte Relief sichtbar), AR (Quick Look per USDZ im Browser, WebXR auf Android), Nutzen-Schilder statt Teilenamen, „So sieht es Ihre Kundschaft" als Ablauf-Dialog (`kundenablauf.js`).
- **Gemessen im Browser:** Die Haar-Drehung reagierte nicht aufs Ziehen — das Posterbild unter der Leinwand startete den Bild-Drag des Browsers (pointercancel). Die Dessert-Ansicht stand doppelt so weit weg wie die Pasta, weil ihr Fokus den eigenen Teller (21 cm) mitnimmt — eigene Luft je Ansicht.
- **Film (A5):** `branchen_studio.py … film` rechnet 10 s hochkant (1080 × 1920, 24 B/s) auf Uwes Rechner und setzt im Videoschnitt von Blender je Sprache ein MP4 mit Titel und Abspann in Archivo (Schrift der Seite). Probe gemessen: ohne `fit_method` stand eine Viertel-Probe als Kästchen in der Mitte.

### 24.09.2026 — Umbau „edel, exklusiv, sofort verständlich": Entscheidungen
Uwe: „Gesamte Vecom Seite soll edel, exclusive aber sehr ansprechend wirken, der Kunde soll direkt verstehen worum es geht." Kapitelleiste rechts entfernt (sein Wunsch). Auf die Vorschläge **Ja:** K1 Seite straffen (26 → ~10 Bildschirme, Technik auf eigene Unterseite), K3 „Mein Betrieb ist …", K4 Doppeltes zusammenlegen, E1 Schwarz & Champagner statt Cyan, E2 Serifenschrift, E3 mehr Ruhe, E4 große Bildbänder, V1 „Sie"/„Lei", V2 Referenzen nach oben, V4 Foto + WhatsApp-Knopf. **Nein:** K2 (Demo statt der V-Welt im Aufmacher — die Welt bleibt), V3 („ab"-Preise).
- Vorgehen: erst drei Richtungen lokal (`website/vorschau/`, nicht im Repository): A Maison (Marcellus, zentriert), B Magazin (Cormorant mit Kursive, asymmetrisch), C Atelier (Bodoni, „Mein Betrieb ist …" im Aufmacher). Marcellus der Seite war ohne Umlaute beschnitten — für den Umbau volle Schnitte (OFL, fontsource).

### 24.09.2026 — Umbau in Richtung B „Magazin", in Blau
Uwe wählte aus drei lokalen Richtungen B — „aber im Blau und das V bewegend wie es vorher war". Der Aufmacher behält die Echtzeit-Bühne mit dem V; das gerahmte Bild der Vorschau wanderte nach „Mein Betrieb ist …", weil es rechts das V verdeckt hätte.
- **Schrift:** Überschriften in Cormorant Garamond (OFL, nur lateinische Grundzeichen, 4 × 23 KB), die Kursive im Markenverlauf ist das einzige Akzentmittel. Alles in `assets/css/edel.css` über app.css — rückbaubar mit einer Zeile.
- **Gestrafft (K1, K4):** Laufband, „Stellen Sie sich vor", „Was wäre, wenn", Wegweiser, Zwischenrufe, Individuelle Projekte, Assistentin, Prinzipien, Partner und Signatur sind von der Startseite weg; Qualitätsstufen, Technik, Vergleich, Streaming stehen auf der neuen Technikseite (`tecnica.html` / `de/technik.html` / `en/technology.html`). Gemessen bei 1440 px: 23.836 → rund 15.800 px (26,5 → 17,6 Bildschirme).
- **Neu:** „Mein Betrieb ist …" (`betrieb.js`, zehn Branchen, öffnet die passende Demo), zwei Bildbänder, WhatsApp-Knopf mit Foto (erscheint nach dem Aufmacher). Ansprache überall „Sie"/„Lei", auch in Mails und Vorlagen.
- **Gemessen, nicht vermutet:** Fest getrennte Titelzeilen brachen auf 1440 in vier — jetzt eine Maske, Umbruch per `text-wrap: balance`. Auf dem Telefon wechselte das Bild außer Sicht, weil die Liste darunter stand — dort jetzt Chips über dem Bild. erlebnis.js setzte die Villa voraus und brach auf der Technikseite ab — Villa-Zugriffe abgesichert.

### 24.09.2026 — „Umgesetzte Arbeiten" als Fallstudien
Uwe: „viel hyperrealistischer … ich sage ja oder nein" — alles Ja (R1–R4, F1–F4). Statt gezeichneter Geräte-Karussells je Projekt ein Cycles-Foto aus Blender (`3d-produktion/scripts/pr_arbeiten.py`, markenloser 14"-Laptop und Telefon auf Acrylständer, Welt je Branche), darin die echte Kundenseite live: Blender schreibt die Bildschirmecken mit (`ecken.json`), `arbeiten.js` setzt die Seite per Homographie (`matrix3d`) hinein, der Glanz-Durchgang (`aus.webp`, `mix-blend-mode: screen`) liegt darüber. Beim ersten Zeigen 3 s Kamerafahrt (`fahrt.mp4`).
- **Texte:** Branche · Aufgabe · Lösung · Ergebnis und Kennzahlen nur aus Gemessenem oder auf der Kundenseite Stehendem (Jonika: 2 Bände ab 9 + 2 Malbücher 4–8, am 24.09. auf der Seite nachgelesen). Kundenstimme nur aus `stimmen-daten.php`. **Vorher/Nachher nur mit echtem altem Bildschirmfoto** (`vorher` im Projekteintrag) — ein nachgestelltes Vorher wäre eine erfundene Aussage über den Kunden.
- **Gemessen:** Flach liegende Bücher sieht eine Kamera 15° über dem Tisch streifend; der Umschlag spiegelt dann die helle Wand und erscheint cremefarben statt dunkel (Trendonix, Probe 2/3). Deshalb steht ein Band auf einem Acrylständer.

### 24.09.2026 — Der Einstieg ist eine E-Mail-Adresse
Uwe: „Statt dem Fragebogen ist es besser, dass der Kunde seine E-Mail-Adresse einträgt, mit dem klaren Hinweis, dass sein persönliches Dashboard ihm zugeschickt wird und darüber alles Weitere bis zur Auslieferung läuft … Die Kette darf nicht unterbrochen werden.“ **Ja:** E1 ein Feld, ein Knopf (Aufmacher, Kontakt, `zugang.php`) · E2 Link nur per Mail, derselbe Satz für jede Adresse, Bestandskunden bekommen ihren Link noch einmal · E4 Kunde erst beim Öffnen, ungeöffnet nach 7 Tagen gelöscht · D1 die acht Fragen als erster Schritt „Vorhaben“ im Dashboard (derselbe Bedarf, `Bedarf::absenden` unverändert) · D2 Name/Telefon erst dort · D3 Erinnerungen 1 Tag / 2 und 7 Tage · S1–S4. **Nein:** E3 (Fangfeld, Mailbremse), D4 (Demo-Auswahl mitnehmen).
- Bau: Migration 050 `zugaenge`, `app/src/Zugang.php`, `zugang.php`, Stufe `vorhaben` in `Kundenzugang::seite`, Dashboard-Modus in `bedarf.php` (Adresse fest, Rückweg, danach ins Dashboard), `bedarf.php` ohne Schlüssel → Einstieg, Telefonassistent verschickt Dashboard-Links (`Zugang::linkNachAnruf`), Trichter neu/alt auf /app/bedarf, Cron `zugaenge`. Kette Abschnitt 61: 1489 → 1562 Prüfungen.
- **Gemessen, nicht vermutet:** Die Kette war grün, der Browser nicht — `zugang.php` lud `Auth` nicht, das Öffnen brach nach dem Anlegen des Kunden ab. Erst der Durchlauf von der Startseite bis zum abgesendeten Vorhaben hat es gezeigt. `Anfrage::annehmen` ergänzt jetzt leere Felder der Akte (nie überschreiben) — sonst blieb der Kunde aus dem E-Mail-Einstieg namenlos.
- Offen/Risiko: Ohne E3 kann jemand fremde Adressen eintragen; jede bekommt höchstens die Willkommensmail und eine Erinnerung, danach nichts. Beobachten über Mails/Brevo; E3 lässt sich jederzeit nachrüsten.

### 24.09.2026 — Gold-Silber statt Blau
Uwe: „kann man das gesamte Blau ersetzen mit einem echten Gold", dann „hyperrealistischer Gold-Silber-Verlauf, sehr edel, professionell". Auf Ja/Nein: **„Ja, aber erst nachschärfen"** — das Standbild neu in Blender statt umgefärbt. Die Verwaltung (`app/views`) bleibt bewusst blau.
- **Farben:** Tokens `--gold-*`/`--silber-*`, `--grad-metall` für Hauptknöpfe (dunkle Schrift, auch beim Überfahren — Weiß auf Champagner wäre kaum lesbar), `--grad-text` für die Kursive. Kundenseiten (`kunde.css`) mit demselben Knopf. Logos und OG-Bild pixelweise über Gold-/Silber-Leuchtdichterampen umgefärbt.
- **3D-Marke (Echtzeit):** Albedo per `onBeforeCompile` von Gold `#d99a2b` nach Silber `#c9ccd1`, Übergang −2 % … +34 % der Breite um die Mitte. **Standbild (Cycles, `3d-produktion/scripts/saal_goldsilber.py`):** dieselbe Grenze, physikalische F0-Farben, Klarlack 0,6 → 0,1, alle blauen Lichtfugen, der Dunst und die Hallenmetalle warm. Gemessen: Gold verlor unter AgX in hellen Tönen die Farbe (V 0,9 → Sättigung 0,15); eine Reflexionsfläche hinter der Kamera mit halber Stärke brachte es in die Mitteltöne. Drei Lichtfarben saßen in Mischknoten vor dem Shader — gefunden mit `saal_blausuche.py`.

### 24.09.2026 — Vollgold statt Gold-Silber
Uwe: „Hintergrund in Silber und sonst, was Gold-Silber-Verlauf ist, echtes glänzendes Gold“. Zwei Vorschauen gebaut (A dunkel mit reinem Gold, B gebürstetes Silber als Seitengrund mit silbernem 3D-Studio), dazu eine Blender-Probe mit Silbersaal. **Gewählt: A, Saal im Standbild dunkel.** B liegt auf dem Zweig `vorschau-gold` (silber.css, `THEMEN.silber`), falls es später wieder gefragt ist.
- Verläufe reines Gold mit zwei Glanzkanten; Echtzeit-V beide Seiten Gold (Mischshader bleibt als Rückweg); Logos aus den blauen Originalen über die Goldrampe. Standbild: `saal_goldsilber.py voll vollgold` (Marke ganz Gold, Rauheit 0,04–0,09).
- **Leicht gebürstet statt Spiegel** (Uwe: „leicht gebürstetes elegantes Gold, die Reflexion nicht zu stark“): Cycles Rauheit 0,17–0,27, Anisotropie 0,55, kein Klarlack, Reflexionsfläche 1,5 → 1,0; Echtzeit-V Klarlack 1,0 → 0,15, Rauheit 0,30 → 0,36, Umgebung 1,9 → 1,5, Anisotropie 0,75, kein Schillern (Iridescence auf Gold wirkt wie Ölfilm); Showroom-Marke Rauheit 0,155 → 0,26 ohne Lack.
- **Showroom-Echtzeit** jetzt ebenfalls warm und gold. Auf der RTX 5070 nachgesehen, nicht im Container (SwiftShader zeigt dort nur das Standbild): Gold spiegelt rund fünfzehnmal mehr als das alte dunkle Blau, Marke und Portallicht liefen im Bloom zu Weiß aus. Ausgleich: Emission auf gleiche Leuchtdichte wie vorher gerechnet, Spitzlicht 180 → 50, Umgebungsanteil der Marke 2,0 → 1,0, Bloom-Schwelle 0,92 → 1,05. Die Reflexionsfläche aus Cycles ist in der Echtzeit bewusst nicht in der Umgebung.

### 24.09.2026 — Graue Kästen, Lade-V, Intro, Neuerungen N1/N2/N4/N6/N7
Uwe: „Bei Cavaleri, Mensaena, Trendonix und Jonika keine Bilder, sondern graue Kästen“, „das blaue V am Anfang soll Gold sein“, „phänomenales Intro, kurz und knackig“, „Mauszeiger dezenter, aber edel“, dazu Vorschläge zum Ja/Nein. **Ja:** Intro I1 „Gegossenes Gold“, N1 „Ihre Seite in 30 Sekunden“, N2 Vertrauensleiste, N4 Kundenlogos, N6 Seitenübergänge, N7 Handy-Leiste. **Nein:** I2, I3, N3 Messwerte, N5 Kennenlern-Termin, N8 Vorher/Nachher (braucht alte Adressen).
- **Graue Kästen** waren die Bildschirm-Bahnen: Aufnahmen direkt nach dem Laden, Nachlader noch leer. `tools/kundenseiten-aufnehmen.py` nimmt jetzt wie ein Besucher auf (erstes Bild sofort, damit es zum Fotorender passt, dann vorscrollen, auf Bilder warten, Abschnitte per Mausrad). Mensaena legt den Inhalt in einen festen Behälter: nur kleine feste Elemente ausblenden, sonst ist alles schwarz.
- **N1** (`assets/js/vorschau.js`): Skizze als HTML in den Bildschirmen des Cavaleri-Fotos, Branchenbilder aus den Demos, eigene Browserleiste mit abgeleiteter Adresse (das Foto zeigt sonst die Adresse der Kundenseite). Weiter über dasselbe E-Mail-Feld, Quelle `vorschau` (nur `Zugang::QUELLEN`, Kette +4 Prüfungen → 1566). Ausdrücklich als automatische Skizze bezeichnet, nicht als Entwurf.
- **N4**: echte Marken einfarbig Champagner aus Aufnahmen der Seiten, Jonika ohne Logo als Wortmarke; Klick öffnet die Fallstudie. Einverständnis der Kunden liegt bei Uwe.
- **N7**: Der STRATO-Assistent sitzt im geschlossenen Schattenbaum; ein fester, transformierter Wirt macht ihn zum Bezugsrahmen, so steigt er mit der Leiste (am Live-Widget geprüft).
- **Intro** (`3d-produktion/scripts/intro_gold.py`, Cycles, 78 Bilder à 24 B/s): Füllfront statt Flüssigkeitssimulation, Glut = Planck × Goldfarbe (reine Planck-Farbe kippte unter AgX ins Lachsrosa, gemessen 254/173/135), Emission 0,38 statt 7 — erst dann sieht man flüssiges Metall statt einer leuchtenden Fläche. Vorhang nur beim ersten Besuch in 30 Tagen, nie bei reduzierter Bewegung, Datensparen, langsamer Leitung, Robotern oder Sprung zu einem Abschnitt; spielt der Film nach 1,5 s nicht, gibt es keinen.

### 24.09.2026 — Moderne Küche (K1), Dashboard-Einblick (V1), Zeitleiste (V2), Logo in der Vorschau (V4)
Uwe: „die Küche viel moderner … hyperrealistisch mit Unreal Engine“, dazu V1–V4 mit Ja. **Weg K1:** Aussehen in Blender (`3d-produktion/scripts/kueche_modern.py`), Unreal rechnet mit dem Path Tracer, der Planer bekommt dieselben Oberflächen.
- **Entwurf:** grifflos mit Gola-Mulde in Champagner (das Gold der Marke als Metall), Hochschrankwand bis zur Decke in Eiche mit durchlaufendem Furnierbild, Insel und Zeile supermatt schwarz, Keramik Calacatta Oro 3 cm als Wasserfall mit durchlaufender Aderung, Rückwand raumhoch gespiegelt (Bookmatch), Induktion mit Muldenlüfter statt Haube. Texturen CC0 (ambientCG, Poly Haven), Quellen in `quelle/tex/modern/QUELLEN.txt`.
- Gemessen und behoben: Sonne kam nicht durch das Glas (Kaustik) → dünnes Glas lässt Schattenstrahlen durch; schräge Helligkeitskeile auf der Platte kamen von weich gemittelten Randnormalen an Boolean-Flächen → große Flächen flach.
- **Planer:** Fronten Eiche furniert / Kaschmir / Tiefschwarz / Weiß, Platten Calacatta Oro / Nero / Keramik Beton / Eiche, Standard grifflos mit Champagner-Mulde, Dielen und Kalkputz statt gezeichneter Fliesen; Steinkarten laden erst bei Wahl. Alte Plan-Codes (salbei, nussbaum, graphit, marmor) bleiben gültig, im Planer und in `Bedarf::PLAN_RE`.
- **Unreal:** `ue-a01_szene.py` speicherte die importierten Netze nie — die Karte zeigte auf Pakete, die es nicht gab, daher Cavaleris schwarzes Bild. Jetzt `save_directory` nach dem Import; Objektivverschiebung über `sensor_vertical_offset`.
- **V1** zeigt echte Dashboard-Seiten, lokal mit Beispieldaten aufgenommen (Preise lokal und live byte-gleich, `preise-daten.php` verglichen); das „noch X von 10 Plätzen“ ist ausgeblendet, weil es veraltet. **V2** ohne Tageszahlen — die FAQ sagt bewusst, dass der Zeitrahmen erst mit dem Umfang feststeht; nur belegte Zusagen (Werktag, 50/50, Freigabe). **V4**: Logo nur im Browser (Object-URL), Akzentfarbe aus dem Logo, dunkle Wortmarken hell, Logos mit weißem Grund auf Schild.
- Beim Durchklicken gefunden: Der Fragebogen duzte auf Deutsch und Italienisch an sechs Stellen (Kette prüft jetzt die Anrede, 1567), Englisch zeigte „€1.500“ statt „€1,500“.

### 25.09.2026 — Ein fertiger Commit lag nur auf dem Windows-Rechner
`068049b` (Moderne Küche, Dashboard-Einblick, Zeitleiste) war committet, aber nie gepusht — die Sitzung stand am Wochenlimit. Das Repository liegt dort in `Desktop\Vecom Design\website`, **nicht** im Ordner darüber; unter `_archiv-2026-09-16` liegen alte Klone mit demselben origin, eine Suche nach `.git` findet sie zuerst. Übernommen über einen Push auf den Arbeitszweig, Baumhash gleich (`30926fd`), Build ohne Abweichung, Kette 1567 grün, Einblick in DE/EN im Browser angesehen.
- Kein Fehler, fast „repariert“: Wer zuerst `/` öffnet und dann `/de/` direkt aufruft, landet wieder auf `/` — das ist die Sprachweiche vom 23.09., kein Fehler des Einblicks.

### 25.09.2026 — Phase 0 aus dem Master-Prompt: keine Website-Pakete, nirgends
Uwe: „keine Pakete, alles individuell je nachdem, was der Kunde im Fragebogen auswählt, berechnet durch den Konfigurator.“ Auf der Seite stimmte das seit dem 12.09.; dahinter nicht: Starter/Business/Premium waren nur `oeffentlich = 0`, aber `active = 1` — das Telefon nannte „Starter, 499 €“, zwei Vorlagen setzten den Starter-Preis ein, `clip2-preis.mp4` („ab 499 € · Festpreis“) lag öffentlich. Migration 051 schaltet sie aus (nicht löschen: Bestellungen hängen daran), Kette prüft das dauerhaft. Außerdem Anmeldebremse (5 Fehlversuche/15 min) und AR-Zählung. Entschieden: Stripe live; 12 Monate Mindestlaufzeit, Ende zum Monatsende, danach monatlich zum Monatsende kündbar (keine weitere Frist — so rechnet `Abo::kuendigungsvorschau` bereits); Hosting 9,90 €, inklusive bei Plus/Premium; noch keine KAS-Hostingkunden.
- Postfach des Hosting-Kunden heißt `kontakt@` statt `info@` (`Hosting::POSTFACH`).
- `3d-produktion/` im Repository trägt jetzt alle Blender-Skripte (`scripts/`, 103 Stück) und die 14 Branchen-Filme A5 (`film/`, je DE/IT). Der Deploy schließt den Ordner aus. Einzelbilder, Logs und `.blend` bleiben auf dem Windows-Rechner; das Repository `vecom-3d-produktion` gibt es nicht.
- Keine Dauer im Schaufenster, auch nicht in Clips (Uwe: „ja“): `clip3-ablauf.mp4` entfernt, `tiktok.html` Clip 3 ohne Zeitangabe. `tiktok.html`, `video/clip*` und `richtungen/` (Entwürfe mit „Online in due settimane, a un prezzo fisso“) lagen öffentlich, obwohl nichts sie verlinkte — jetzt vom Deploy ausgeschlossen und auf dem Server gelöscht.
- Offen: Preisseite sagt „Webspace in der Betreuung enthalten“, laut Code nur bei Plus/Premium.

### 25.09.2026 — Phase 1: Vertragsregeln in der Verwaltung, Domain/Hosting/E-Mail getrennt, Zustimmungen mit Wortlaut
- **1a Vertragsregeln** (Migration 052): Mindestlaufzeit, Kündigungsfrist vor Monatsende, Inklusivzeit stehen am Produkt (Formular „Paket“), werden beim Vertragsschluss in den Vertrag kopiert (`abos.kuendigung_tage`). Werte: 12 Monate, Frist 0 (zum Monatsende), Plus 60 / Premium 120 Minuten — auch in den Startdaten. Kette hält fest: keine Frist, solange die Vertragstexte „zum Monatsende“ sagen.
- **1c Getrennt** (Migration 053, Fragebogen Schritt 6): neue Fragen ohne Vorauswahl — „Welche Domain?“, „Was soll mit der Domain passieren?“ (bleibt / zieht um / beraten), „Wo soll die Website laufen?“ (Vecom / bisheriger Anbieter / offen), „E-Mail mit Ihrer Domain?“ (bleibt / Postfach über Vecom / keine). Hosting nur bei „Vecom“, Umzug nur bei „zieht um“, Postfach nur bei „Vecom“. `hosting_auftraege.domain_aktion` (neu/transfer/behalten/offen) und `mail`. Ältere Fragebögen behalten den alten Weg. Aufgabe für Uwe je Fall (bei „bleibt“: nur A/AAAA beim alten Anbieter, MX/SPF/DKIM/DMARC/TXT nicht anfassen; bei Umzug: DNS vorher ablesen und übernehmen).
- **Erst zahlen, dann anlegen**: Bei der finalen Freigabe entstehen Hosting-Vertrag und erste Rate; KAS-Account & Co. erst nach bezahlter Rate (`nachZahlung`). Ausnahme: in Betreuung Plus/Premium enthalten → gleich bei Freigabe.
- **1b Zustimmungen** (Tabelle `zustimmungen`, `Zustimmung.php`): Wortlaut des Kastens + Knopf, Fassung, Sprache — keine IP. Ein Domain-Umzug bekommt eine eigene Zeile. Kasten und gespeicherte Zustimmung kommen aus derselben Funktion (`Hosting::angebotText`). Kette 1629.
- Falle beim Testen: Fragebogen-Links müssen hexadezimal sein (`Onboarding::laden`), sonst „Dieser Link gilt nicht mehr“ — kein Fehler im Code.

### 25.09.2026 — Fragebogen: mehr erfahren, weniger fragen (Uwe: „alles außer B3“)
Ausgangslage gemessen: 8 Fragen (Vorhaben) + großer Fragebogen mit 6 Schritten, 52 Feldern, 13 langen Texten; nur 7 Antworten übernommen; Angebot erst nach dem ganzen Fragebogen. **Ja:** A1 alte Website auslesen, A2 Domain aus E-Mail, A3 Firmendaten aus P. IVA (VIES), A4 nichts doppelt fragen, A5 Google-Eintrag, B1 Kern/Kür, B2 Chips statt Text, B4 Diktieren, B5 Hochladen an der Frage, B6 Minuten statt Schritte, C1 Angebot nach dem Kern, C2 Telefon-Weg sichtbar, C3 zwei Erinnerungen, C4 Vorher/Nachher. **Nein:** B3 (KI-Vorschläge, laufende Kosten).
- **Erste Runde gebaut:** A2 (`Bedarf::domainAusMail`, Freemail/PEC ausgenommen), A4 (Termin, Betreuung, Telefon, E-Mail aus Vorhaben/Akte; Seiten/Sprachen/Funktionen als Ausgangswert über `Umfang::ausVorhaben` — nie als „beauftragt“), B1 (`Fragen::KERN`, 16 Fragen, kein langer Text; der Rest zugeklappt „Wenn Sie mögen“), C1 (Absenden, sobald der Kern steht — dann darf das Angebot raus; freiwillige Angaben danach über `Onboarding::nachtragen`, der Kern bleibt unverändert), B6 („Noch etwa X Minuten“ aus den offenen Kernfragen), C3 (Erinnerung nach 1 Tag Stille, zweite nach 3 — auch für den Fragebogen vor dem Preis; Migration 054). Kette 1655.
- Gemessen, nicht vermutet: Zähler und Hakenliste zeigen immer einen Wert — als „unbeantwortet“ gezählt, schickte der Fragebogen den Kunden zu einem Feld mit sichtbarer „5“ zurück. Jetzt gilt der angezeigte Wert. `updated_at` ändert sich auch beim Vermerk der Erinnerung — die zweite zählt deshalb nur Bewegung nach dem Vermerk.
- **A5 zurückgestellt:** Adresse/Öffnungszeiten aus Google gehen nur über die Google Places API (Konto mit Zahlungsdaten, Schlüssel); Google Maps auslesen verstößt gegen die Nutzungsbedingungen. Uwes Entscheidung offen.
- **Zweite Runde gebaut:** B2 (`Texte::CHIPS` — Bausteine zum Antippen an sechs Textfeldern, landen als Text im Feld, zweiter Tipp nimmt sie heraus; ein Chip darf kein Komma enthalten, sonst zerfällt er beim Abwählen — die Kette hat genau einen gefunden), B4 (Diktieren über die Spracherkennung des Browsers, Knopf nur, wo es sie gibt; der Hinweis sagt, dass Google/Apple erkennt), B5 (Hochladen direkt bei Material und Logo, dieselbe `Ablage` wie die Kundenseite), C2 (Manuela-Hinweis mit Kundennummer auf Schritt 1, Widget nach `load`). Kette 1659.
- **Dritte Runde, A1 + A3 gebaut:** `Vorwissen` liest die alte Website (`Seitenblick::abrufen` + `Seiteninhalt::lesen`: JSON-LD zuerst, dann tel/mailto/Profile, P. IVA nur mit gültiger Prüfziffer) und fragt die P. IVA bei VIES (`Vies`, amtliche REST-Schnittstelle, ohne Schlüssel). Eingetragen wird nur in **leere** Felder offener Fragebögen, markiert „von Ihrer Website übernommen — bitte prüfen“ (`questionnaires.seite_felder`, Migration 055). Gelesen wird nach dem Ausliefern (`fastcgi_finish_request`, Sitzung vorher frei) oder vom Cron, nie während jemand wartet; `updated_at` bleibt stehen, damit die Erinnerungen nicht getäuscht werden. Gegen vecom-design.it gemessen: Name, E-Mail, Anschrift in 1,5 s. Kette 1678.
- **C4 gebaut:** Solange es keinen Entwurf gibt, zeigt das Dashboard „Wie es heute ist — und wie es werden könnte“: bis zu drei gemessene Befunde der alten Seite (`seite_befunde`, aus demselben Abruf wie A1, keine zweite Anfrage) und die 30-Sekunden-Skizze mit Name, Branche, Ort und **seinem** Logo (Rolle `logo` beim Hochladen im Fragebogen; ausgeliefert nur neu gerechnet über `Ablage::vorschauAusliefern`). Die Bühne ist dafür aus `vorschau.js` herausgelöst (`buehneAn`), die Regeln stehen jetzt in `assets/css/vorschau.css` statt in `edel.css` — eine Quelle für Startseite und Dashboard. Neue Branche „Unterkunft/Ospitalità“ (Villa-Terrasse). Branchen ohne passendes Bild (Handwerk, Praxis, Dienstleistung, Sonstiges) bekommen keinen Kasten statt eines falschen. Kette 1683.
- **A5 ausgelassen (25.09.2026, Uwe):** kein Google-Unternehmenseintrag im Fragebogen — kein Google-Konto mit Zahlungsdaten. Erst wieder anfassen, wenn Uwe es ausdrücklich will.
- **Phase 2 gebaut (Branch, 25.09.2026): automatisch abbuchen.** Bewusst **kein** Stripe-Abonnement — Laufzeit, Kündigung und Raten bleiben in `Abo.php`, Stripe bucht nur ab, was dort als Rate steht (`Abbuchung.php`). Der Kunde hinterlegt im Dashboard Karte oder Lastschrift (Checkout „setup“, Zustimmung mit Wortlaut in `zustimmungen`, Art `abbuchung`). Jede Rate wird 2 Tage vorher angekündigt; **nur wenn die Ankündigung rausging**, wird abgebucht — sonst der gewohnte Zahlungslink (gefunden, weil die Kette ohne Mailversand läuft: vorher hätte der Cron ohne Vorabinformation abgebucht). Abgelehnt → Meldung an Uwe + Zahlungslink. Laufende Lastschrift wird nicht gemahnt (`Mahnung` schließt `method='abbuchung'` aus) und vom Abgleich über `pi_…` nachgebucht, auch ohne Webhook. Migration 056, Kette 1706.
- **Hinterlegen nur Karte + SEPA (25.09.2026, Uwe):** Die Einrichtungsseite nennt `payment_method_types` ausdrücklich; die übrigen Zahlarten im Konto gelten nur für Einmalzahlungen.
- **Phase 3 gebaut (Branch, 25.09.2026): Hosting in Schritten.** `Hosting::anlegen` beansprucht den Auftrag zuerst (`zugestimmt → in_arbeit`, ein Befehl) — vorher blieb er nach einem Abbruch „zugestimmt“, und die nächste bezahlte Rate hätte einen **zweiten KAS-Account** angelegt. Jeder Schritt hat eine Zeile (`hosting_schritte`, Migration 057): Account, Domain, Postfach, Vertrag, Kunden-Mail, Aufgabe. Domain/Postfach werden bis 3× wiederholt (Cron, 20 Min Pause), „gibt es schon“ zählt als erledigt; ein Account, der mitten im Aufruf abbrach, wird **nie** blind wiederholt, sondern Handarbeit. Das Passwort des Unter-Accounts liegt weiter nur verschlüsselt in der einmaligen Kundenanzeige; die gibt es deshalb erst, wenn alles Automatische durch ist. Verwaltung zeigt die Schritte und „Offene Schritte wiederholen“ (Rückfrage aus `Ablauf::TRAGWEITE`). Kette 1720.
- **Kunden ohne Namen waren unanklickbar (25.09.2026):** Suche und 16 Listen verlinkten nur den Namen — leer bei E-Mail-Einstieg, also unsichtbar. Uwe kam nicht an seinen Probekunden. Jetzt `Fmt::name()` (Firma, Name, E-Mail, Nummer, sonst „(ohne Namen)“); Kette 71 sucht solche Links in allen Ansichten.
- **Phase 4 (Verwaltung) gebaut, 25.09.2026:** „Betreuung“ heißt jetzt **„Verträge“** und zeigt Monatssumme, wie viel automatisch abgebucht wird, Überfälliges, Hosting-Stand und „Wartet auf dich“ (Hosting-Handarbeit, fehlgeschlagene/überfällige Raten, Verträge die in 30 Tagen auslaufen) — nur lesend (`Leistungen.php`), die Taten bleiben in der Kundenakte. Eine laufende Lastschrift zählt nie als überfällig. Kette 1729.
- **Phase 5 gebaut (Branch, 25.09.2026): Domain-Umzug begleiten.** Umgezogen wird weiter nur von Uwe im Domainbestellsystem (keine API). Neu (`Domainumzug.php`, Migration 058): DNS-Bestandsaufnahme vor dem Umzug (A/AAAA/CNAME, MX, SPF/TXT, DMARC, gängige DKIM-Namen) als Liste „so im KAS eintragen“; Transfersperre aus RDAP bzw. WHOIS (.it), vom Cron alle 6 h neu gelesen; Auth-Code gibt der Kunde im Dashboard ein — verschlüsselt, nie in Mail oder Meldung, gelöscht mit „KK-Antrag gestellt“; der Cron erkennt den Abschluss an den Nameservern (alle `*.kasserver.com`) und sagt Uwe (SSL einschalten) und Kunde Bescheid. Kette 1746.
- **Phase 6 — Uwes Wahl (25.09.2026):** E-Mail-Umzug, alte Website als Vorlage **und** 1:1-Umzug. Reihenfolge: Vorlage zuerst.
- **Phase 6a gebaut (Branch): alte Website als Vorlage sichern.** Kundenakte → Dateien → „Alte Seite als Vorlage sichern“ (`Altseite.php`, Migration 059). Der Cron liest portionsweise (max. 30 Seiten, 80 Bilder/PDFs, nur derselbe Host, keine Weiterleitung auf IP/localhost) und legt **eine** ZIP in die Ablage: `texte.html` in Seitenreihenfolge, `bilder/`, `dokumente/`. Gefunden durch die Kette: Die Unterseiten-Links stehen fast nur in der Navigation — erst Links sammeln, dann Navigation entfernen. An vecom-design.it: 0,7 s, 162 Textblöcke, 34 Bilder. `php-imap` fehlt in PHP 8.4 — für den E-Mail-Umzug (6b) nötig abzuklären. Kette 1756.
- **Phase 6c gebaut (Branch, 25.09.2026): 1:1-Umzug begleitet.** Uwe fragt in der Kundenakte an (Mail an den Kunden, Rückfrage RAUS); der Kunde stimmt im Dashboard mit Wortlaut zu (`zustimmungen`, Art `migration`) und gibt FTP/DB-Zugang ein — verschlüsselt, nur öffentliche Server (private IPs abgewiesen), gelöscht mit Abschluss/Abbruch oder nach 30 Tagen (Cron). Der Cron testet die Verbindung (nur anmelden, ein Verzeichnis lesen, WordPress erkennen). Kopieren tut Uwe; die Checkliste geht nur in Reihenfolge, **Sicherung zuerst** (`Seitenumzug.php`, Migration 060). Kette 1770.
- **SSH am Reseller-Zugang (25.09.2026):** bleibt aus. Die Automatik (PHP per Cron-Adresse) kann SSH nicht nutzen; nützlich wäre es nur Uwe beim ersten echten 1:1-Umzug — dann nur mit Schlüssel.
- **Phase 6b gebaut (Branch): E-Mail-Umzug, vollautomatisch.** Eigener IMAP-Client (`Imap.php`) ohne PHP-Erweiterung `imap` (seit PHP 8.4 nicht eingebaut). Uwe fragt an, der Kunde stimmt zu und gibt beide Passwörter ein (verschlüsselt, Art `mailumzug`). Der Cron kopiert portionsweise alle Ordner mit Gelesen-Markierung und Datum, legt Unterordner mit dem Trennzeichen des neuen Servers an, Gesendet/Entwürfe/Papierkorb in dessen Sonderordner; beim alten Anbieter nur EXAMINE + BODY.PEEK. Danach 14 Tage Nachlauf alle 6 h (bis die MX umgestellt sind), dann Passwörter weg. Gegen einen echten Dovecot getestet (6 Mails, Nachzügler, keine Doppelten, alte Mails bleiben ungelesen); in der Kette mit nachgebautem Server. Migration 061, Kette 1784.
- **Mehr aus der KAS-Schnittstelle (25.09.2026, Uwe: ja zu 1–3):** (1) Neuer Hosting-Schritt **„DNS vom alten Anbieter“** beim Umzug: die alten Einträge kommen per `add_dns_settings` in die KAS-Zone, bevor die Nameserver umziehen — bleibt die Mail beim alten Anbieter, MX/SPF/DKIM/DMARC mit, und die KAS-MX werden per `update_dns_settings` **umgeschrieben, nie gelöscht** (die Kette verbietet löschende Methoden in `Kas`; bleibt ein KAS-MX übrig → Handarbeit mit klarem Satz). Web (A/AAAA/www) und NS nie. (2) Speicher aller Kunden-Accounts einmal täglich (`get_space`, show_subaccounts), in „Verträge“ sichtbar, Meldung ab 90 % einmal je Monat. (3) Cronjob per Knopf im KAS eintragen (`add_cronjob`, prüft vorher `get_cronjobs`). Antwortfelder der KAS-Doku sind nicht dokumentiert → tolerant gelesen, beim ersten echten Lauf ansehen. Kette 1792.
- **Weiterleitungen + Sperre (25.09.2026, Uwe: ja):** Fragebogen-Feld `mail_weiter` (nur bei Postfach über Vecom) → neuer Hosting-Schritt „Weiterleitungen“ legt z. B. info@/buchung@ → kontakt@ per `add_mailforward` an. Nach Vertragsende (eigener Hosting-Vertrag bzw. bei „in Betreuung enthalten“ keine Betreuung mehr) steht der Kunde in „Wartet auf dich“; „Zugang sperren“ in der Kundenakte setzt `kas_access_forbidden` — gesperrt, nicht gelöscht, wieder zu öffnen, mit Rückfrage. Migration 062, Kette 1803.
- **25.09.2026 — KAS-Speicher: „no_statistic_data" ist kein Fehler.** Am echten Reseller-Zugang nur lesend gemessen: `get_space` antwortet ohne (oder mit frischen) Unterkonten mit diesem Fehler statt einer leeren Liste. `Kas::speicherAusAntwort` wertet ihn als „noch keine Zahlen"; andere Fehler bleiben Fehler (Kette geprüft). Ebenfalls gemessen: `get_accountresources` liefert je Ressource `{max, used, free}`; der Vertrag hat derzeit 0 Unterkonten und eine Domain.
- **Masterprompt KAS/Stripe (26.09.2026): nur die Lücken gebaut, nichts ersetzt.** (1) **Speicher je Vertrag** `hosting_auftraege.speicher_mb` — Vecom ist die Quelle, der KAS folgt; Bestand bekam genau den bisherigen Wert 10 GB (keine neue Paketgröße), neue Aufträge tragen ihren Wert selbst; ändern nur in der Kundenakte, mit alt → neu im Protokoll. (2) **Abgleich**: täglicher Lauf liest `max_webspace` (get_accounts), Abweichung → Meldung „Speicher weicht ab“; in den KAS geschrieben wird nur per Knopf und nur der Vecom-Wert. (3) **KAS-Probelauf** (Schalter unter „Was von allein läuft“, oder `KAS_DRY_RUN`): ohne Schalter **an** — add_/update_ erreichen den KAS nicht, Aufträge bleiben „zugestimmt“ und starten, sobald er aus ist. **Nach dem Deploy legt die Verwaltung also nichts an, bis Uwe ihn ausschaltet.** (4) **HTTPS**: Prüfung über `Monitoring::abrufen` + http→https-Umleitung, Cron 6 h/täglich; „Online“ ist gesperrt, solange HTTPS einer Domain bei uns nicht „ok“ ist. (5) **Datenbank/FTP** nur angekreuzt, idempotent am Kommentar `vecom-<Auftrag>-db/ftp`, Passwort verschlüsselt (`technik_blob`), Anzeige nur für Uwe, protokolliert. (6) „Ihr Hosting“ auf der Kundenseite (7,4 GB / 15 GB, HTTPS, E-Mail, nächste Abbuchung). (7) `KAS_LOGIN`/`KAS_PASSWORD` aus der Server-Umgebung gehen vor. Bewusst **nicht**: Stripe-Abos/`invoice.*` (Laufzeiten bestimmt Vecom, nicht Stripe), 50/50 unverändert, bei Zahlungsausfall wird nie gelöscht. Migration 063, Kette 1831.
- **Reseller-Übersicht (26.09.2026, Uwe: „lese meinen Reseller aus“):** Einstellungen → Reseller (KAS). Auf Knopfdruck nur lesende Aufrufe (get_accountresources, get_accounts, get_space, get_domains/subdomains/mailaccounts); Stand ohne Passwort gemerkt. Zeigt Kontingente, Kunden-Accounts und je Kunde Speicher Vecom ↔ KAS ↔ belegt (passt / weicht ab / bei Vecom unbekannt), Vecom-Aufträge ohne KAS-Account, und ob alle Vereinbarungen zusammen in den Vertrag passen. Das KAS-Passwort aus dem Chat wurde bewusst nicht behalten — Uwe trägt es selbst ein. Seitenprüfung: überall 10 GB, keine alten Pakete; **offen: Website verspricht info@, System legt kontakt@ an** (Uwe entscheidet). Kette 1837.
- **Postfach info@ statt kontakt@ (26.09.2026, Uwe: „ändere auf info@“):** `Hosting::POSTFACH = 'info'`, Fragebogen, Zustimmungstext, Verwaltung. Die Weiterleitungs-Frage schlägt jetzt „kontakt, buchung“ vor. Schon gespeicherte Zustimmungen bleiben im Wortlaut, wie sie gegeben wurden.
- **Gerecht geteilt statt pauschal 10 GB (26.09.2026, Uwe):** Jeder Kunde bekommt den Reseller-Vertrag **geteilt durch die Kunden-Plätze (`max_account`)**, abgerundet — Speicher (ganze GB), Domains, Subdomains, Postfächer, Weiterleitungen, Mailinglisten, Datenbanken, FTP, Cronjobs. Durch die Plätze, nicht durch die Kunden von heute: sonst schrumpfte jedem sein Speicher, sobald einer dazukommt. „Unbegrenzt“ beim Reseller → 10 je Kunde; Mindestwerte, damit das Einrichten nie an 0 scheitert. **Anlass und Fund:** `add_account` setzt laut Doku JEDE nicht übergebene Grenze auf 0 — bisher ging nur `max_webspace` mit, der erste echte Kunde hätte weder Domain noch Postfach anlegen dürfen. Die Vorgabe kommt aus dem letzten „Auslesen“ (Einstellungen → Reseller); ohne Auslesen bleibt es bei 10 GB. Neue Aufträge halten Speicher + Kontingente fest (`kontingente`, Migration 064); nach dem Auslesen bekommen nur noch nicht zugestimmte Angebote die neue Aufteilung. `hosting.php`, Zustimmungstexte und `pakete-daten.php` (`hosting_gb`) rechnen live. Nebenbei: `pakete-live.js` brach ohne Website-Pakete ab (live der Fall) — der Hosting-Preis kam nie aus der Verwaltung; `Events::protokoll` kürzt jetzt Titel > 255 Zeichen, statt den Vorgang abzubrechen. Kette 1850.
- **Durchsicht der ganzen Seite (26.09.2026), Uwe: „Alles“ außer 7 (Registrar — erst Kosten klären).** Gefunden und behoben: (1) Steuerakte bekam die Liste statt der Summe der Ausgaben → Zeile „Ausgaben“ und „Reverse Charge“ fehlten (vorher/nachher mit einem Beleg gemessen). (2) Lange Wörter in Nachrichten machten die Kundenseite 526 statt 390 px breit → `overflow-wrap:anywhere`, auch in der Verwaltung. (3) www lieferte doppelt aus → 301 auf ohne www, vor der https-Regel (ein Sprung). (4) Sicherheits-Header (HSTS ein Jahr ohne Subdomains, X-Frame-Options SAMEORIGIN, nosniff, Referrer-Policy) — **mit echtem Apache gemessen:** Skript-Header landen in der „always“-Tabelle, ein einfaches `setifempty` erzeugte zwei Referrer-Policy; darum `always setifempty`. (5) Reseller täglich von selbst auslesen (nur lesen); ein gescheitertes Lesen ersetzt keinen guten Stand. (6) „Heute“ warnt rot, wenn der Cron 30 Min. nicht lief. (8) Monatsbericht an Hosting-Kunden ab dem Ersten — nur Gemessenes, ohne Messung keine Mail, frühestens 20 Tage nach dem Einrichten, abschaltbar. (9) Ab 90 % Speicher bekommt auch der Kunde eine Mail mit Tipps. Öffentliche Seite sonst sauber: keine kaputten Links, keine Skriptfehler, gesperrte Pfade 403. Kette 1864.
- **Neue Domains: Weg B (26.09.2026, Uwe: „B“ — kostenlos statt Registrar-Konto).** Uwe bestellt weiter im Domainbestellsystem von All-Inkl (keine Schnittstelle, keine Zusatzkosten). Neu: Kasten „Domain bestellen“ in der Kundenakte mit allen Inhaberdaten und beiden Nameservern (gemessen: ns5/ns6.kasserver.com) und „Kopieren und Bestellsystem öffnen“ (das vorhandene data-kopieren/data-oeffnen des Layouts). Der Cron („registriert“) erkennt an den Nameservern, dass die Domain da ist, trägt `domain_registriert_am` ein (Migration 065), prüft sofort HTTPS, meldet sich bei Uwe und schreibt dem Kunden (`domain_aktiv`, dreisprachig) — genau einmal. Die Kette fand dabei: die längere Aufgabe sprengte die 500 Zeichen einer Meldung und hätte den letzten Einrichtungsschritt abgebrochen → `Events::melden` kürzt jetzt, und der Aufgabentext ist kürzer (Details stehen im Kasten). Live nachgemessen: www-Weiterleitung und Header greifen, hosting.php trägt genau eine Referrer-Policy. Kette 1875.
- **Seite veröffentlichen (26.09.2026, Uwe: ja).** Kunde ohne Domain → Hosting bei uns → die mit Claude Code gebaute Seite muss auf die neue Domain. Neu: Knopf auf der Projektseite (`Veroeffentlichung`): das letzte Werkstatt-Paket per **FTPS** in `/web` des Kunden-Accounts, mit dem „FTP-Zugang für Vecom“ aus `technik_blob` (bei Website-Aufträgen jetzt automatisch angekreuzt — er bleibt, auch wenn der Kunde seine Zugangsdaten abgerufen hat). Regeln: nur nach Abnahme und auf Uwes Klick (TRAGWEITE `veroeffentlichen`); **vorher sichern** (Web-Ordner als ZIP, Rolle `sicherung`, nie auf der Kundenseite — darin können alte Zugangsdaten stehen), scheitert die Sicherung → nichts hochgeladen (Mutationsprobe: die Kette schlägt an); **nie löschen**, nur überschreiben; ZIP-Prüfung (kein `..`, index.html oben, Oberordner fällt weg, __MACOSX raus). Danach `websites`-Eintrag (Monitoring, Monatsbericht, Kundenseite zeigt die echte Domain) und HTTPS-Prüfung → „Online“. Im Probelauf nur gezählt. Netlify: nach 28 Tagen online EINE Aufgabe „Vorschau kann weg“ mit 301-Hinweis — gelöscht wird dort nur von Hand. **Offen/ungemessen:** Ob FTPS vom Webspace zu `w0….kasserver.com` mit einem Zusatz-FTP-Nutzer durchgeht — beim ersten echten Kunden-Account messen. Migration 066, Kette 1892.
- **Uwes Fund vom 26.09.2026 (Kunde Manuel/brandy.com).** (1) Ein **E-Mail-Umzug ließ sich ohne zugestimmtes Hosting anfragen** — der Kunde sah „Zustimmen und Umzug starten nach info@brandy.com“ für einen Vertrag, den er nie geschlossen hatte. Jetzt nur mit Hosting im Stand zugestimmt/eingerichtet und Postfach bei uns (Verwaltung zeigt sonst den Grund). (2) **Die rote Meldung „Es braucht die Passwörter beider Postfächer“** kam, obwohl beide dastanden: Es fehlte der Zielserver (kein KAS-Account). Solange das neue Postfach nicht existiert, sieht der Kunde einen Satz statt der Abfrage; kennt Vecom das neue Passwort noch, wird nicht danach gefragt. (3+4) **Domain anbieten scheiterte bei .it**: .it hat keinen RDAP-Dienst (`rdap.nic.it` existiert nicht, gemessen), WHOIS auf Port 43 ist vom Server aus unklar → jede freie .it blieb „unklar“, und angeboten wurde nur „frei“. Jetzt: vergeben nie; unklar mit Haken „Selbst geprüft“ (Link web-whois.nic.it); ein unbeantwortetes Angebot lässt sich ersetzen statt „hat schon einen Hosting-Vorgang“. Knopf „Domainprüfung testen“ misst auf dem Server, ob WHOIS durchkommt. Hosting in Angebot/Rechnung ohne Zustimmung: im Code kein Weg gefunden (Katalog kennt keine Domain/Hosting-Zeile, Vertrag und Rate nur ab „zugestimmt“) — **offen, Uwe um Screenshot gebeten**. Kette 1900.
- **Ablauf-Film neu, 3D (26.09.2026, Uwe: Text im Bild, Blender baut/Unreal rendert, ohne Stimme, ~90 s; „fertig in 3 Sprachen → live“).** Ein goldener Weg durch eine dunkle Galerie, zehn Stationen (Anfrage, Seite, Fragebogen, Angebot 50/50, Bau, Abnahme, **Domain & E-Mail als Wahl**, online, Betreuung), eine Fahrt ohne Schnitt, keine erfundenen Bildschirme, keine Zeitangaben. `3d-produktion/scripts/ablauf_film.py` (Szene, Probe, Export, Cycles-Rückfall), `ue_ablauf_film.py` (Unreal-Sequenzen + MRQ, **noch nie gelaufen**), `film-ablauf/einbauen.py` (MP4, Plakate, Texte „1:30“, Build, Push — getestet), Anleitung `film-ablauf/LIESMICH.md`. In der Cloud mit Blender 5.2 als Python-Modul gebaut und per Cycles/CPU probegerendert: Proben fanden zu dunkles Gold (→ Reflexwand hinter der Kamera), aufgeblähte Schrift, abgeschnittenen Text (Bildausschnitt nachgerechnet), Lichter, die in Unreal im Bild stünden, schwarzen Hintergrund (→ Rückwand mit Deckenflutern), 90 MB Schrift je Sprache (→ 319k Flächen). **Rendern nur auf dem Windows-Rechner**; Texte s6–s9 vorher von Muttersprachlern lesen lassen.
- **Manuela: Chef-Modus nach dem Masterprompt (26.09.2026).** Bestehendes blieb (15 Kundenwerkzeuge, Termine nur aus `freiePlaetze`, Preise nur aus dem Baukasten, alle Chef-Aktionen und ihre Antwortfelder). Neu: Codewort nur als **Hash** (Klartext-Altbestand wird beim ersten Lesen umgeschrieben), mind. 8 Zeichen, **Sperre** nach 5 Fehlversuchen in 15 Min. für 60 Min. (auch fürs richtige Wort, eine Meldung), optionale **PIN**; jeder Aufruf steht für sich (STRATO hat keine Sitzung). **Freigabestufen 1–4** (`Chef::STUFEN`); **Stufe 4 wird am Telefon nie ausgeführt**, nur vorbereitet (alt/neu/Objekt/Folgen, Wert wiederholen) und in Einstellungen → Telefon freigegeben — Grund: Das Codewort steht im STRATO-Mitschnitt, und eine Rufnummernprüfung geht nicht (Werkzeuge bekommen keine Anrufernummer). Beim Freigeben wird nichts überschrieben, wenn sich der alte Wert inzwischen geändert hat. Tageslage nach KRITISCH/HOCH/NORMAL/NIEDRIG, gleiche Meldungen gebündelt, höchstens 5 vorgelesen; 360°-Akte mit Hosting (VECOM-Wert zählt, KAS nur Verbrauch); Gedächtnis `chef_gedaechtnis` (Migration 067) mit Widerspruch („wartet auf Logo“, Logo ist da) und Konflikt bei zweiter Entscheidung; Änderungssuche über activities + audit_log. Chef-Werkzeuge gehen mit der Übertragung zu STRATO (an chef.php, nur wenn ein Codewort gesetzt ist). **Verhaltenstext** `Telefonverhalten` v1 (Sprachsperre, nichts erfinden, Präzisionsmodus, Lead-Status, Übergabe, Barge-in, Abschluss, Beschwerde, Chef-Regeln): Kopierknopf + nur lesender Vergleich — **Uwe muss ihn einmal bei STRATO einfügen**; automatisch geschrieben wird er nicht (Stimme/Persönlichkeit stehen im selben Feld). Sprache, Unterbrechen und Tonfall macht das Modell bei STRATO — prüfbar ist nur, dass die Regel dasteht. Die Kette fand: Zählerstart aus PHP-Zeit (Rom) gegen DB-`created_at` → Sperre griff nie; jetzt setzt die Datenbank die Zeit.
- **STRATO meldet sich selbst neu an (26.09.2026, Uwe: „ja mach“ nach „Session Expired“).** STRATO beendet Sitzungen von sich aus; ein kopierter Token kann das nicht überleben. Uwe kann jetzt E-Mail + Passwort hinterlegen (versiegelt wie die Hosting-Zugänge, sofort geprüft, falsch → nicht gespeichert). Nur wenn der Token abgelehnt wird, holt sich der Server per `grant_type=password` eine eigene Sitzung (nicht die des Browsers). Scheitert auch das, genau eine Meldung je 24 h, das Passwort steht in keiner Meldung. Geht nicht mit Google-Login/Code. Kette 1969.
- **Partnerprogramm (26.09.2026, Uwe: „Alles und b“, Auszahlung „voll automatisch über Stripe“, 10 % / ab 50 €).** Neben den Kundenempfehlungen (Rabatt), nicht an ihrer Stelle: Partner bekommen Geld. Link `/p/CODE` (`p.php`) zählt Klicks je Tag ohne IP und legt nur einen **Sitzungs-Keks** mit dem Code an — die Website verspricht „keine Tracking-Cookies, kein Banner“, deshalb kein 30-Tage-Keks; zugeordnet wird beim ersten Kontakt **am Kunden** (Konfigurator, Direktbuchung, eingetippter Code, von Hand), der erste gewinnt, nie selbst, nie zusätzlich zu einer Kundenempfehlung (Pro Verkauf zahlt Vecom einmal). Provision nur aus **bezahltem Netto**, genau einmal je Zahlung (uq_pp_zahlung), wartet mind. 14 Tage; Erstattung vorher → entfällt/anteilig, nachher → Stripe-Rückholung, sonst „zurückfordern“ an Uwe. **Automatische Auszahlung ist eine bewusste Ausnahme** von „was das Haus verlässt, bleibt am Klick“ (Uwes Entscheidung): nur mit bestätigter Vereinbarung, von Stripe geprüftem Konto (Connect, recipient, nur `transfers`), Zahlung in dem Moment noch bezahlt, ab Mindestbetrag, **Tageslimit** (Standard 1.000 €), `source_transaction` + Idempotenz je Provision; Schalter aus = nur von Hand. Einstellbar global und je Partner: % oder fester Betrag, Arten (Website/Betreuung/Hosting), Laufzeit der Zuordnung, Monate bei Monatsverträgen, Freigabe jeder Provision, Steuereinbehalt (Standard 0 %). Öffentliche Bewerbung + Partnerseite `partner.php` (3 Sprachen, keine Kundennamen, Stripe-Einrichtung nur dort, Beleg-PDF), Verwaltung „Kunden → Partner“, Monatsmail nur bei Bewegung, Datenschutz p9 in 3 Sprachen. Migration 068. **Offen bei Uwe:** Stripe Connect im Dashboard aktivieren (Plattform-Profil), Commercialista zur Ritenuta, Vereinbarung rechtlich lesen lassen. Beim Ansehen gefunden: `partner.php` lud `Auth` nicht → erste Bewerbung wäre gestorben (jetzt in der Kette). Kette 2020, Gegenprobe Tageslimit schlägt an.
- **Auszahlungswege für Partner (26.09.2026, Uwe: „Ja mach“).** Neben Stripe: **SEPA** (IBAN versiegelt, nur die letzten 4 lesbar, nie in der Prüfspur; die Verwaltung baut eine pain.001-Datei fürs Online-Banking, danach „ausgeführt“/„abbrechen“ — nie automatisch), **PayPal** (Payouts-API, automatisch, feste Stapelkennung gegen Doppelzahlung), **Wise** (automatisch angelegt; verlangt Wise die Bestätigung in der App, bleibt die Auszahlung „offen“, Provisionen „unterwegs“), **Verrechnung** (nur wenn der Partner selbst Kunde ist; die offene Rate wird geteilt, der verrechnete Teil gilt als bezahlt mit Anbieter „verrechnung“ — Umsatz bleibt voll, Provision ist Ausgabe). Partner wählt auf seiner Seite, Uwe schaltet Wege ein; ein Weg erscheint nur, wenn er technisch da ist (PayPal/Wise: Schlüssel in config.local.php unter `paypal`/`wise`). Migration 069. Die Kette fand zwei zu schmale Spalten (`status` für „abgebrochen“, `weg` für „gutschrift“).
- **STRATO-Zugang (26.09.2026).** Uwe meldet sich mit Kundennummer an → die E-Mail/Passwort-Neuanmeldung kann bei ihm nicht gehen; das Feld ist aus der Ansicht genommen (Technik bleibt für E-Mail-Konten). STRATOs Login von hier zu bedienen wäre Screen Scraping — abgelehnt. Stattdessen: echte Absage (400/401/403, nicht 5xx) → eine Meldung + Zuruf aufs Handy, höchstens täglich; jede Sitzung wird mit Anfang/Ende/Grund gemerkt, die Ansicht zeigt, ob STRATO nach fester Zeit abschaltet. Kette 2050.
- **Partner löschen (26.09.2026, Uwe).** Nur ohne offenes Geld (bereit/unterwegs/zurückzufordern, offene Auszahlung) — die Vereinbarung verspricht, Verdientes auszuzahlen. Ohne je eine Auszahlung verschwindet er ganz; mit Auszahlungen bleiben Name, Steuernummer und Belege (Aufbewahrungspflicht), E-Mail/IBAN/PayPal/Notizen/Zugang werden gelöscht, Status „geloescht“, Link tot, Kunden frei, wartende Provisionen entfallen. Stripe-Konto des Partners bleibt bei Stripe (gehört ihm).
- **Verwaltung in Gold (26.09.2026, Uwe: „ganze Verwaltung gold“).** `app/assets/admin.css` und `cockpit/index.html` nehmen die Werte von Website und Kunden-Dashboard (kunde.css): Grund #0a0908, Akzent #c8963e/#f1d38b, Hauptknopf im Metallverlauf mit dunkler Schrift (Weiß auf Gold wäre ~2:1). Die Variablennamen `--blau/--cyan` blieben (≈30 Regeln, viele Ansichten) und heißen jetzt „Akzent dunkel/hell“. Warnungen wurden von Gelb auf Orange (#ff9f5a) gelegt — Gelb neben Gold war nicht mehr zu unterscheiden. CLAUDE.md-Regel 3 gilt für die Akzentfarbe, wortgleich angepasst.
- **Empfehlungsrabatt kommt jetzt an (26.09.2026).** Befund: `Empfehlung::neuBerechnen` schrieb `rabatt_prozent/_bis`, aber keine Rate las es — Betreuung ging immer zum vollen Preis raus, trotz „zwölf Monate zu fünfzehn Prozent“ in den Kundentexten. Jetzt in `Abo::abrechnen`: nur Betreuung (nie Hosting), es zählt der Monat der Rate (gilt der Rabatt am Monatsersten, gilt er für den Monat), ganze Cent; festgehalten in `payments.detail` (Prozent, Vollpreis, Abzug), damit alte Raten nie umgeschrieben werden.
- Der Beleg zeigt Vollpreis und „Empfehlungsrabatt (15 %)“ / „Sconto per raccomandazione“ / „Referral discount“ als eigene Zeile; Netto/Steuer so geteilt, dass beide Zeilen genau den Beleg ergeben. Kette 2063, Gegenprobe (Rabatt aus) schlägt an.
- **Partnercode selbst wählen (26.09.2026, Uwe: „selbst schreiben oder geben lassen“).** Beim Einladen optionales Feld „Code“ (leer = vergeben lassen), in der Partnerakte „Code ändern“ (mit Rückfrage: der alte Link stirbt). Regeln: 5–16 A–Z/0–9, Leerzeichen/Striche fallen weg, groß/klein egal, eindeutig auch gegenüber Kunden-Empfehlungscodes; Änderung in der Prüfspur. Kette 2071.
- **Partner-Tracking wirklich durchgespielt (26.09.2026, Uwe: „prüfe, ob wirklich ein Kauf getrackt wird“).** Befund vor dem Test: Der **Hauptweg** der Startseite (E-Mail → Link → Dashboard, `zugang.php`) und das Anfrageformular (`formular.php`) ordneten den Partner **nicht** zu — nur Konfigurator und Direktbuchung taten es. Behoben: Der Code reist jetzt am Zugang mit (Migration 070, `zugaenge.partner_code`) und wird beim Öffnen zugeordnet, auch auf einem anderen Gerät ohne Keks; das Formular ordnet über den Besuch zu. Neu nach Vereinbarung Punkt 1: Wer vorher schon gekauft hat, wird über den Link nicht mehr zugeordnet (von Hand schon). Im Browser gemessen: /p/TESTPARTNER → Startseite (gewollt, der Link führt auf die normale Seite), Klick gezählt, E-Mail eingetragen, Link am „Laptop“ geöffnet → Kunde dem Partner zugeordnet; Kauf 1.500 € (50/50) → 2 × 75 € Provision, wartend bis zur Widerrufsfrist; Gegenprobe ohne Link → keine Zuordnung. Kette 2078.
- **Partnerseite in der Sprache des Partners (26.09.2026, Uwe: „ständig auf Englisch“).** Ursache: `Sprache::ausAnfrage` nimmt den Website-Sprachkeks vor der Sprache des Partners — wer die Website einmal auf Englisch gesehen hatte, sah jede Partnerseite englisch; zusätzlich trug jede Formularadresse `lang=` und hielt das fest. Jetzt gilt mit Schlüssel die Sprache am Partner, nur die Sprachwahl unten ändert sie (und speichert sie, auch für Mails); Uwe kann sie in der Partnerakte setzen. Im Browser gemessen: Keks „en“, Partner „it“ → italienisch; Klick Deutsch → bleibt deutsch. Kette 2080.
- **„Konto bei Stripe einrichten“ gab nur „Etwas hat nicht geklappt“ (26.09.2026, Uwe mit Screenshot).** Wahrscheinlichste Ursache: Stripe **Connect** ist im Konto nicht aktiviert (ohne Connect legt Stripe keine Partnerkonten an); zweite mögliche: Stripe lehnt „recipient“-Konten im selben Land ab. Jetzt: recipient abgelehnt → automatisch zweiter Versuch mit normalem Konto; Connect fehlt → Setting `partner_stripe_connect=fehlt`, Stripe wird Partnern nicht mehr angeboten, Meldung an Uwe mit Anleitung (Partner → Auszahlungswege, Knopf „Stripe Connect prüfen“ gibt ihn wieder frei). Idempotenz-Schlüssel tragen Variante + Stunde (Stripe merkt sich manche Fehlerantworten). Partner sieht einen klaren Satz statt „Panne“ und eine Schritt-für-Schritt-Anleitung (Stripe/SEPA/PayPal, 3 Sprachen). Die genaue Stripe-Antwort steht in Uwes Meldungen — nicht von hier einsehbar. Kette 2088.
- **Partnerprogramm verständlicher (26.09.2026, Uwe).** Bewerbungs- und Partnerseite: „So funktioniert's“ in drei Schritten (Link teilen → Kunde kauft → Geld kommt, mit den echten Zahlen), „Häufige Fragen“, auf der Partnerseite oben der **eine nächste Schritt** (Vereinbarung → Auszahlungsweg → Link teilen → läuft), Zahlen mit Erklärung darunter, „auszahlbar ab“-Datum an jeder wartenden Provision, „Per WhatsApp senden“ mit fertigem Text. Verwaltung: „So läuft es“ in einer Zeile oben. Alles dreisprachig. Kette 2088.
- **Partner-Ausbau, Vorschläge 1 und 3–13 (26.09.2026, Uwe: „Ja alles ausser 2“ — kein Willkommensrabatt für Kunden).** Landeseite „Empfohlen von …“ statt nackter Startseite (`p.php`, E-Mail-Einstieg oben); Kanal-Links `/p/CODE/kanal` mit Klicks je Kanal (Migration 071); Werbemittel im Portal: drei Texte, Druckkarte A6 mit QR, QR-PNG, Story-Bild 1080×1920 — alles im Browser erzeugt (`assets/js/qrcode.js`, MIT), beide QR mit OpenCV zur richtigen Adresse zurückgelesen; „Ich habe einen Kunden für euch“ (nur mit Einverständnis, 10/Tag, als Anfrage mit Quelle „partner“); Manuela-Werkzeug `empfohlen` (Verhaltenstext v2 — **muss neu zu STRATO**, ebenso die Werkzeuge, jetzt 16); Stufen Bronze/Silber/Gold (5/10 Verkäufe in 12 Monaten → 12/15 %, nur Prozent und nur ohne eigene Bedingungen); Sofort-Mails bei neuem Kontakt/verdienter Provision (abschaltbar); Erinnerung an Ruhende, Missbrauchsprüfung, Jahresübersicht als PDF (Mail im Januar); Verwaltung „Lohnt es sich?“ je Partner und Kanal. Kette 2125.
- **Sichtprüfung fand zwei Fehler.** `Partner::anlegen` nahm einen Code, den `ausCode` nie findet („ROSA“, 4 Zeichen → Link führt still auf die Startseite); jetzt abgewiesen, mit Kettenprüfung. Die Kanal-Zeilen schoben das Portal am Handy auf 519 px: Grid-Eintrag ohne `minmax(0,1fr)` ist so breit wie sein Inhalt — jetzt 390 px gemessen.
- **„Stripe Connect prüfen“ sagte immer „nicht aktiviert“ (26.09.2026, Uwe).** Jeder Fehler galt als „Connect fehlt“ — auch ein eingeschränkter Schlüssel (`rk_…`), dessen Absage „rak_**connect**ed_account“ enthält, und jeder Netzwackler. Stripe selbst (über das Dashboard-Konto gelesen) beantwortet `GET /v1/accounts` ohne Fehler. Jetzt ordnet `Partner::connectGrund` ein (connect / rechte / schluessel / netz / stripe), Schlüssel in Stripes Text werden maskiert, ein Netzfehler ändert den Stand nicht, und Grund + Schlüsselart + Zeitpunkt stehen **beim Schild** — vorher nur als Meldung oben, außerhalb des Bildes nach dem Sprung zu #wege. Kette 2131.
- **Anruf-Knopf auf allen Sprachen, deutsche Nummer (26.09.2026, Uwe).** `build.mjs` TELEFON it/en/de = +49 30 4397926082, bis die italienische Sonetel-Nummer geht. Knopf 44 px, still (Hauptaktion bleibt der E-Mail-Einstieg), Klicks zählen als `anruf` in d.php. JSON-LD: `telephone` ergänzt, doppelte `founder`/`address` bereinigt.
- **Build fraß den Kontaktblock.** Die Entfernregel der Telefonzeile („…</div>\s*</div>“) lief über die Zeile hinaus und löschte auf der italienischen Vorlage Formular und „Che cosa succede dopo“ — unsichtbar, solange Italien keine Nummer hatte; der übernächste Deploy hätte es live gelöscht. Regel auf genau eine Zeile begrenzt; `build.mjs` bricht jetzt ab, wenn eine Seite weniger Formulare/Abschnitte/Überschriften hat als ihre Quelle (Gegenprobe mit der alten Regel: 4→3 Formulare, Abbruch).
- **Verwaltung einfacher, Teil 1 (26.09.2026, Uwe: „Mach“).** „Heute“: höchstens fünf Zeilen vorne, nur die erste golden (Regel 3), der Rest unter „Später“ mit Zahl — Seite von 6.419 auf 1.756 px. Jede Menüseite trägt oben einen Satz „Hier …“ aus `Hilfe::SAETZE` (Kette prüft: keine Menüseite ohne Satz). Einführung beim ersten Anmelden: 5 Schritte im Dialog, gemerkt je Benutzer beim Schließen wie beim Fertig, zurückholbar über „Einführung ansehen“.
- **Vorschlag 8 („ungenutzte Seiten unter ‚Mehr‘“) nicht gebaut.** Widerspricht der Entscheidung vom 13.09.2026 (zweite Menüliste entfernt: offene Posten verschwanden in der Schublade), die die Kette schützt. Menü wird stattdessen über Reiter gestrafft.
- **Menü mit Reitern (26.09.2026, Vorschlag 3).** „Posteingang“ = Nachrichten | Anfragen | Bedarf, „Weiterempfehlung“ = Empfehlungen | Partner | Kundenstimmen, „Preise“ = Pakete | Preisbausteine. Menüzeilen 28 → 23 sichtbare Einträge; jede Seite bleibt als Reiter erreichbar, die Menüzeile trägt die Summe ihrer Reiter. Kette erweitert: erreichbar = Menü ODER Reiter einer vorhandenen Zeile (Gegenprobe: Reiter „Bedarf“ entfernt → „verschwunden: bedarf“).
- **Meldungen erledigen sich selbst — nur mit Beleg (26.09.2026, Vorschlag 5).** `Meldungen::aufraeumen()` (bei „Heute“ und im Cronlauf) markiert als gelesen, wenn die Datenbank den Anlass als vorbei belegt: Connect ok, neue STRATO-Sitzung nach dem Alarm, Anfrage nicht mehr „neu“, Bewerbung entschieden, nichts zur Freigabe, Wise bestätigt, alle Kundennachrichten gelesen. Gegenstand aus dem Link; ohne Gegenstand bleibt sie stehen. Nie nach Zeit, nie gelöscht. Kette 2145.
- **Cockpit-Aufgaben unter „Heute“ (26.09.2026, Vorschlag 6).** `Einmalig`: vier noch mögliche Aufgaben aus dem Cockpit (Stand 29.08.) eingeklappt unter „Einmal zu erledigen“; „Erste Kundenstimme“ und „Instagram/TikTok“ haken sich selbst ab, sobald Datenbank bzw. `social.js` es belegen, der Rest per Klick. Betreuungslaufzeit weggelassen („[bitte ergänzen]“ steht nirgends mehr). Das Cockpit bleibt, trägt oben den Verweis.
- **„Heute“ war nach dem Reiter-Commit kaputt (500).** `layout.php` und die Ansicht teilen einen Gültigkeitsbereich; `foreach ($reiter as … => $liste)` überschrieb `$liste` von „Heute“. Die Kette liest Ansichten nur als Text — gefunden erst im Browser. Umbenannt; Absicherung folgt in der Kette.
- **Gerüst überschrieb Seitenwerte — seit 13.09. (gefunden 26.09.2026).** Statische Suche nach Namen, die `index.php` an Ansichten übergibt und `layout.php` vorher setzt: `offen`, `summe` (Menüschleife). Folgen: „Meldungen“ zeigte „0 ungelesen“ bei 126 offenen (im Browser bestätigt), „Rechnungen“ die Menüzahl statt des offenen Betrags, „Ausgaben“/„Empfehlungen“/„Telefon“ verloren Listen. Ursache behoben statt Namen gejagt: `extract($daten)` direkt vor `require $inhaltsdatei`. Kette prüft das; alle 31 Verwaltungsseiten lokal ohne PHP-Warnung.
