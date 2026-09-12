<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';

/**
 * Der Vecom-Standard: wie eine Vecom-Seite gebaut ist.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Jede Kundenseite entstand bisher in einem frischen Gespraech, das nichts
 * von den vorigen wusste. Was bei Boulevard gelernt wurde — dass die
 * Telefonnummer auf dem Handy anklickbar sein muss, dass Oeffnungszeiten
 * ueber alles gehen, dass niemand ein Kontaktformular ausfuellt, wenn
 * daneben WhatsApp steht — musste beim naechsten Kunden neu einfallen. So
 * wird Seite 12 nicht besser als Seite 1, sondern nur anders.
 *
 * Dieses Dokument ist die Gegenmassnahme, und es ist bewusst EIN Text und
 * keine Sammlung von Einstellungen: Wer eine Regel aendern will, schreibt
 * einen Satz um, statt ein Formular zu suchen. Es haengt an jedem Briefing
 * und gilt damit ab dem naechsten Kunden fuer alle.
 *
 * WARUM ES SICH ABSCHALTEN LAESST
 *
 * Liegt der Standard in der Wissensablage eines Claude-Projekts, kennt ihn
 * jedes Gespraech dort ohnehin. Ihn dann noch einmal mitzuschicken, waere
 * dieselbe Seite zweimal. Deshalb der Schalter: anhaengen ja oder nein.
 */
final class Standard
{
    private const SCHLUESSEL = 'werkstatt_standard';
    private const SCHALTER   = 'werkstatt_standard_anhaengen';
    private const GESEHEN    = 'werkstatt_standard_gesehen';

    /**
     * Die Vorgabe. Sie steht hier und nicht in der Datenbank, damit eine
     * frische Installation nicht mit einem leeren Blatt anfaengt — und
     * damit man sehen kann, was Uwe daran geaendert hat.
     */
    public const VORGABE = <<<'TEXT'
VECOM-STANDARD — so ist eine Vecom-Seite gebaut

DIE REIHENFOLGE
- Kundenbedarf → Geschaeftsziel → Nutzer → Inhalt → Geschichte → Emotion →
  Erlebnis → TECHNIK. Technik steht am Ende, und zwar immer. Ueber Framework,
  Animation, 3D, Video und Effekt wird entschieden, NACHDEM feststeht, wem die
  Seite wobei hilft und was darauf steht.
- Probe vor der ersten Zeile Code: Nennt die Antwort auf „Warum so?" eine
  Technik, ist die Reihenfolge verletzt. Eine gueltige Antwort nennt den
  Kunden, sein Geschaeft oder seinen Besucher.

ERFINDE NIEMALS UNTERNEHMENSINFORMATIONEN
- Gearbeitet wird ausschliesslich aus: Fragebogen, Gespraech, hochgeladenen
  Dateien, bestehender Website, Marke und Assets, vereinbartem Umfang,
  Projektklasse und Modulen, freigegebenen Aenderungen. Was dort nicht steht,
  steht nicht auf der Seite.
- Jede Information traegt eine Stufe:
  BESTAETIGT  — der Kunde hat es gesagt oder geliefert; kommt so auf die Seite.
  GESCHLOSSEN — aus Vorhandenem abgeleitet; als Annahme markieren und
                bestaetigen lassen.
  FEHLT       — wird gefragt; bis zur Antwort ein sichtbarer Platzhalter,
                nie eine stille Erfindung.
  GESPERRT    — darf unter keinen Umstaenden entstehen.
- GESPERRT sind: Telefonnummern, Adressen, Preise, Bewertungen,
  Zertifizierungen, Auszeichnungen, Referenzen, Mitarbeiter, Kundenlogos,
  Firmengeschichte, Rechtsangaben, Produktversprechen, Garantien und
  technische Leistungswerte.
- Fehlt etwas Gesperrtes, gibt es genau zwei erlaubte Wege: fragen oder die
  Sektion weglassen. Ein dritter wird nicht gesucht — auch dann nicht, wenn
  die Seite dadurch unfertig aussieht. Ein erfundenes Kundenlogo ist eine
  Urheberrechtsverletzung, eine erfundene Zertifizierung eine Falschangabe im
  Geschaeftsverkehr, ein erfundener Leistungswert eine Zusage, fuer die der
  Kunde haftet — nicht ich.

DAS MANIFEST IST DIE EINZIGE WAHRHEIT
- Jedes Projekt fuehrt ein Manifest: Klasse, Module, Umfang, alle Inhalte mit
  ihrer Stufe, Marke, Entscheidungen, offene Fragen, Freigaben. Es liegt im
  Kundenordner neben dem Briefing.
- Briefing ist, was ankam. Manifest ist, was daraus gilt. Widersprechen sich
  Seite und Manifest, ist die Seite falsch — nicht das Manifest.

DER UMFANG IST GESPERRT
- Eine Leistung, die nicht im vereinbarten Umfang steht, wird nie still
  dazugebaut. Sie ist eine Aenderungsanfrage: benennen, Preis nennen,
  Freigabe abwarten.
- Der typische Fall ist harmlos und deshalb gefaehrlich: ein
  Reservierungsformular, „weil das bei einem Restaurant dazugehoert". Dahinter
  haengen Zustellung, Datenschutz, Pflege und eine Erwartung beim Gast, die
  jemand erfuellen muss.
- Was geschenkt werden soll, wird ausdruecklich geschenkt und im Manifest
  vermerkt — nicht verschwiegen.

KLASSE UND MODULE STEHEN VOR DEM BAUEN FEST
- Klassen: A ESSENTIAL (da sein, gefunden werden, erreichbar sein) ·
  B BUSINESS (Anfragen, Termine, Verkauf) · C PREMIUM (eigene Handschrift) ·
  D CGI · E CGI REALITY · F EXPERIENCE (Bewegung und Ablauf tragen mit) ·
  G SIGNATURE (Einzelstueck) · X CUSTOM.
- Module: WEB · BRAND · MARKETING · MOTION · CGI · CONTENT · VISUAL ·
  PACKAGING · BUSINESS · TECH · CARE.
- Die Klasse entscheidet ueber Aufwand, Preis und Qualitaetsanspruch. Eine
  Klasse hoeher als noetig ist ein Fehler, nicht Ehrgeiz: Ein sauberes B
  schlaegt ein wackliges F — beim Kunden, bei der Ladezeit und bei der Pflege.

SPRACHEN
- WELCHE Sprachen und welche fuehrt, steht im Briefing und nirgends sonst.
  Es haengt am Kunden und an seinen Gaesten, nicht an einer Hausregel: Ein
  Restaurant in Agrigent fuehrt italienisch, ein deutscher Handwerker in
  Sizilien mit deutscher Kundschaft fuehrt deutsch, ein Hotel mit
  franzoesischen Gruppen braucht Franzoesisch statt Englisch. Steht es im
  Briefing nicht eindeutig da, ist das eine Frage an mich, keine Annahme.
- Die Zahl der Sprachen ist die bezahlte. Mehr ist nicht grosszuegig,
  sondern unbezahlte Arbeit, die spaeter gepflegt werden muss.
- Die fuehrende Sprache ist die der Gaeste, nicht die des Inhabers. Wer
  seine Post von mir auf Deutsch bekommt, kann trotzdem eine italienische
  Startseite brauchen.
- Was in jeder Sprache gilt: eigene Adresse (/it/, /de/, /en/) und hreflang;
  Sprachwahl sichtbar oben, nicht im Fuss; keine automatische Umleitung nach
  Browsersprache — wer Italienisch liest, will nicht auf Deutsch landen, weil
  sein Telefon deutsch eingestellt ist.
- Halb uebersetzt ist schlechter als einsprachig. Entweder eine Sprache ist
  vollstaendig da — Menue, Formulare, Fehlermeldungen, Rechtsseiten — oder
  sie steht nicht zur Wahl.
- Uebersetzung heisst uebersetzt, nicht durchgeschoben: Ein deutscher Satz,
  der woertlich ins Italienische wandert, klingt nach Behoerde.

WAS DIE SEITE LEISTEN MUSS
- Eine Aufgabe pro Seite. Wer alles auf die Startseite legt, hat nichts gesagt.
- Die gewuenschte Handlung ist immer in Reichweite: anrufen, schreiben,
  reservieren, den Weg finden. Auf dem Handy im Daumenbereich.
- Telefonnummer als tel:-Link, Adresse als Karten-Link, WhatsApp wenn der
  Kunde WhatsApp nutzt. In Sizilien ruft man an oder schreibt bei WhatsApp;
  ein Kontaktformular allein ist eine geschlossene Tuer.
- Oeffnungszeiten, Adresse und Telefonnummer stimmen und stehen auf jeder
  Seite im Fuss. Das sind die drei Angaben, wegen derer die Leute kommen.

STUFE 0 — WAS IMMER TRAEGT
- Die Seite misst das Geraet und nimmt sich zurueck, wenn es schwach ist. Die
  Inszenierung faellt zuerst, der Inhalt nie.
- Stufe 0 ist der Vertrag: ohne WebGL, ohne Effekte, auf dem langsamsten
  Geraet traegt die Seite weiterhin den Inhalt, die Navigation, die Marke, die
  wichtigste Handlung und den Kontakt. Fehlt davon eines, ist die Seite
  kaputt — nicht „reduziert".
- Gefragt wird nicht „laeuft es?", sondern „was passiert, wenn es nicht
  laeuft?": Video laedt nicht, Schrift kommt nicht an, Formular antwortet
  nicht, Verbindung bricht ab. Fuer jeden dieser Faelle gibt es einen
  Zustand, der geplant ist. Ein Ladefehler, der als leerer Kasten endet, ist
  kein Randfall, sondern der Normalfall auf jedem zehnten Besuch.

BEWEGUNG UND TON
- Jede Animation braucht einen Grund: Orientierung, Rueckmeldung,
  Zusammenhang oder Dramaturgie. Sonst weglassen.
- prefers-reduced-motion zeigt den Endzustand sofort und laesst nichts
  verschwinden.
- Ton startet immer stumm und laeuft erst nach einer ausdruecklichen Handlung
  des Besuchers an — nie beim Laden, nie beim Scrollen. Ton traegt nie eine
  Information, die es ohne ihn nicht gibt, und ist jederzeit abschaltbar.
- Kamera, Mikrofon, Bewegung und Standort werden erst nach Zustimmung
  angefragt, mit einer Begruendung, die der Besucher versteht. Ohne
  Zustimmung funktioniert die Seite weiter.

AUFBAU UND TECHNIK
- Handy zuerst gestalten, Rechner als eigene Komposition — nicht als Beiwerk.
- Eine Datei pro Seite, kein Framework ohne Grund. Keine Bibliothek, fuer die
  es keine vier guten Antworten gibt (welches Problem, geht es nativ, wird sie
  gepflegt, was kostet sie an Ladezeit).
- Bilder als WebP oder AVIF, mit width und height, srcset wo es lohnt.
  Das grosse Bild oben mit fetchpriority="high", alle anderen loading="lazy".
- Schriften: eine Anzeigeschrift, eine Textschrift. font-display: swap,
  Zeichensatz beschnitten, die Anzeigeschrift vorgeladen.
- Ladezeit ist ein Versprechen, kein Zufall: LCP unter 2,5 Sekunden auf
  Mobilfunk, keine springenden Layouts, die ganze Seite deutlich unter 1 MB.

GESTALTUNG
- Farben als Tokens an einer Stelle, nie verstreute Hex-Werte. Ein Akzent
  reicht; Ampelfarben fuer Zustaende sind davon getrennt.
- Abstaende aus einer 4/8-Skala. Schriftgroessen aus einer festen Reihe,
  fluid ueber clamp(). Fliesstext 60 bis 75 Zeichen je Zeile.
- Kontrast mindestens 4,5:1 fuer Text und 3:1 fuer Bedienelemente. Tastatur
  vollstaendig bedienbar, Fokus sichtbar und schoen. Touchziele ab 44 px.
- Dunkler Modus nur, wenn er wirklich gepflegt wird. Halb gemacht ist er
  schlechter als gar nicht.

WAS NIE VORKOMMT
- Blindtext, auch nicht kurz. Fehlt ein Text, steht ein realistischer
  Platzhalter in der richtigen Laenge da, klar als solcher gekennzeichnet.
- Stockbilder, die nach Stockbild aussehen. Lieber Typografie und Farbe.
- Karussell fuer etwas Wichtiges. Was durchlaeuft, wird nicht gelesen.
- Text auf unruhigem Bild ohne Abdunklung. Emoji als Ersatz fuer Symbole.
- Das KI-Aussehen: violett-blaue Verlaeufe, organische Blobs, Glaskarten
  ueberall, Neon ohne Anlass, schwebende 3D-Kugeln, die nichts erzaehlen,
  drei gleiche Kaestchen nebeneinander, zentrierte Hero mit zwei Knoepfen.
  Die Gestaltung kommt aus der Welt des Kunden — seinem Material, seinem
  Werkzeug, seiner Sprache. Eine Schreinerei sieht nicht aus wie ein
  Start-up, ein Transportunternehmen nicht wie eine Modemarke.
- Erfundene Bewertungen, Zahlen oder Auszeichnungen. Nie, auch nicht als
  Platzhalter, ohne dass es danebensteht. Siehe die Sperrliste oben.

RECHTLICHES UND AUFFINDBARKEIT
- Impressum und Datenschutz auf jeder Seite im Fuss verlinkt, in jeder
  Sprache erreichbar, die die Seite fuehrt. Cookie-Hinweis nur, wenn wirklich gesetzt wird — und
  dann mit echter Ablehnmoeglichkeit.
- Je Seite und Sprache ein eigener Titel und eine eigene Beschreibung.
  OG-Bild, favicon, sitemap.xml, robots.txt.
- Keine Schriften und Karten von fremden Servern nachladen, ohne dass es in
  der Datenschutzerklaerung steht. Am einfachsten: selbst ausliefern.

DER ERSTE BILDSCHIRM WIRD ZUERST ENTSCHIEDEN
- Ueber den ersten Bildschirm wird entschieden, bevor der Rest gebaut wird.
  Er bestimmt, was der Besucher ueber die ganze Seite denkt, und er ist der
  teuerste Teil, um ihn spaet zu aendern.
- Zwischenstaende sehe ich oertlich, bevor irgendetwas veroeffentlicht wird.

BEVOR ES LIVE GEHT
- Der Abnahme-Check in der Verwaltung laeuft durch. Was er anmeckert, wird
  behoben oder bewusst abgehakt — nicht uebersehen.
- Auf einem echten Telefon angesehen, nicht nur im schmalen Fenster.
- Konsole ohne Fehler, keine fehlgeschlagenen Anfragen.

FERTIG IST NICHT „ES LAEUFT"
- Funktionierend ist der Anfang der Arbeit, nicht ihr Ende. Danach kommen
  Genauigkeit, Rhythmus, Typografie, Zustaende, Politur — die Dinge, an denen
  ein Besucher „teuer" erkennt, ohne sagen zu koennen, woran.
- Fertig heisst: Reihenfolge eingehalten · nichts erfunden, jede Information
  mit ihrer Stufe · Manifest und Seite stimmen ueberein · Umfang eingehalten,
  Aenderungen freigegeben · Stufe 0 traegt Inhalt, Navigation, Marke,
  Handlung, Kontakt · Kontrast und Tastatur geprueft, nicht vermutet ·
  Messwerte vorher und nachher, nicht „muesste reichen" · Konsole leer ·
  Rechtliches vollstaendig mit den Angaben des Kunden · die Seite wurde
  angesehen, auf Telefon und Rechner.
- „Es laeuft" steht nicht auf dieser Liste.

UEBERGABE
- Der Kunde bekommt: die Adressen, was er selbst aendern kann, was die
  Betreuung abdeckt und was extra kostet. Schriftlich, in seiner Sprache.
TEXT;

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** Der geltende Text — der eigene, sonst die Vorgabe. */
    public static function text(): string
    {
        try {
            $w = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::SCHLUESSEL], '');
        } catch (Throwable $e) {
            $w = '';
        }
        return trim($w) !== '' ? $w : self::VORGABE;
    }

    /** Steht ein eigener Text da, oder gilt noch die Vorgabe? */
    public static function eigener(): bool
    {
        try {
            return trim((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::SCHLUESSEL], '')) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Haengt der Standard an jedem Briefing? Vorgabe: ja. */
    public static function anhaengen(): bool
    {
        try {
            $w = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::SCHALTER], '');
        } catch (Throwable $e) {
            return true;
        }
        return $w === '' ? true : $w === '1';
    }

    /**
     * Wann Uwe die Hausregeln zuletzt durchgesehen hat.
     *
     * WARUM DAS NICHT AM GESPEICHERTEN TEXT HAENGT
     *
     * Erst stand hier "hat er einen eigenen Text?" — und damit mass der
     * Einrichtungsstreifen etwas anderes, als er behauptete. Wer die Regeln
     * liest und richtig findet, hat sie durchgesehen; er muesste sonst eine
     * Kleinigkeit aendern, nur damit ein Haken umspringt. Ein Werkzeug, das
     * dazu zwingt, erzieht zum Pfusch.
     */
    public static function gesehenAm(): ?string
    {
        try {
            $w = trim((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::GESEHEN], ''));
        } catch (Throwable $e) {
            return null;
        }
        return $w !== '' ? $w : null;
    }

    /**
     * Durchgesehen — mit oder ohne Datum.
     *
     * Wer eine eigene Fassung gespeichert hat, hat sie gelesen; anders
     * aendert man keinen Text. Fuer alle, die das vor der Einfuehrung des
     * Vermerks getan haben, gaebe es sonst eine Nachfrage nach etwas, das
     * laengst erledigt ist — und eine Nachfrage, die man wegklickt, ist der
     * Anfang vom Ende jeder Liste.
     */
    public static function gesehen(): bool
    {
        return self::gesehenAm() !== null || self::eigener();
    }

    /** "Passt so" — gelesen und für richtig befunden. */
    public static function alsGesehenMerken(): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [self::GESEHEN, date('Y-m-d H:i:s')]);
    }

    public static function speichern(string $text, ?bool $anhaengen = null): void
    {
        // Wer speichert, hat gelesen. Beides getrennt abzuhaken waere ein
        // Handgriff, den niemand versteht.
        self::still(static fn() => self::alsGesehenMerken());

        /* Ein leeres Feld heisst "zurueck zur Vorgabe", nicht "leerer
           Standard". Ein leerer Hausstandard waere ein Briefing ohne
           Hausregeln, und das faellt erst auf, wenn die Seite fertig ist. */
        $text = trim($text);
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [self::SCHLUESSEL, mb_substr($text, 0, 40000)]);

        if ($anhaengen !== null) {
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                [self::SCHALTER, $anhaengen ? '1' : '0']);
        }
    }

    /**
     * Die Adresse des Claude-Projekts, in dem die Kundenseiten entstehen.
     *
     * Uwe legt es einmal an ("Vecom — Kundenseiten") und traegt die Adresse
     * hier ein. Danach oeffnet jeder Briefing-Knopf genau dieses Projekt:
     * Kundenarbeit liegt beisammen und nicht zwischen den Buechern.
     */
    public static function claudeProjekt(): string
    {
        try {
            return trim((string) Db::wert(
                'SELECT svalue FROM settings WHERE skey = ?', ['werkstatt_claude_projekt'], ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function claudeProjektSpeichern(string $url): void
    {
        $url = trim($url);
        // Nur https und nur claude.ai. Eine Adresse, die von hier aus mit
        // einem Klick geoeffnet wird, soll nicht irgendwo hinfuehren koennen.
        if ($url !== '' && !preg_match('~^https://(www\.)?claude\.ai/~i', $url)) {
            throw new RuntimeException('Das muss eine Adresse bei claude.ai sein.');
        }
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            ['werkstatt_claude_projekt', mb_substr($url, 0, 255)]);
    }

    /**
     * Was zur Einrichtung noch fehlt — und was schon steht.
     *
     * Steht auf der Werkstatt und verschwindet, sobald alles erledigt ist.
     * Eine Anleitung, die man einmal liest und danach sucht, ist keine; ein
     * Stand, der dort steht, wo gearbeitet wird, braucht man nicht zu
     * suchen.
     *
     * @return array{punkte:list<array<string,mixed>>,offen:list<string>,gesamt:int}
     */
    public static function einrichtungsstand(): array
    {
        $punkte = [];

        /* 1. Der Cronjob. Er steht bewusst zuerst: Ohne ihn laeuft nachts
              gar nichts — keine Betreuungsmonate, keine erste
              Zahlungserinnerung, kein Monitoring, keine Abnahme. */
        $lauf = null;
        try {
            require_once __DIR__ . '/Cron.php';
            $lauf = Cron::zuletzt();
        } catch (Throwable $e) { }
        $laeuft = $lauf !== null && strtotime((string) $lauf) > time() - 3600;
        $punkte[] = [
            'schluessel' => 'cron',
            'was'    => 'Cronjob im KAS',
            'fertig' => $laeuft,
            'stand'  => $lauf !== null ? 'zuletzt gelaufen ' . Fmt::seit((string) $lauf) : '',
            'warum'  => $lauf === null
                ? 'Ohne ihn läuft nachts nichts von selbst: keine Betreuungsmonate, keine '
                  . 'erste Zahlungserinnerung, kein Monitoring, keine Abnahme.'
                : 'Der letzte Lauf ist über eine Stunde her — läuft der Cronjob noch?',
            'ziel'   => 'monitoring',
            'wohin'  => 'Adresse und Anleitung',
        ];

        /* 2. Das Claude-Projekt. */
        $projekt = self::claudeProjekt();
        $punkte[] = [
            'schluessel' => 'projekt',
            'was'    => 'Claude-Projekt eingetragen',
            'fertig' => $projekt !== '',
            'stand'  => $projekt,
            'warum'  => 'Ohne Adresse öffnen die Briefing-Knöpfe einen freien Chat — die '
                      . 'Kundenarbeit liegt dann zwischen allem anderen.',
            'ziel'   => 'standard',
            'wohin'  => 'eintragen',
        ];

        /* 3. Die Hausregeln. "Fertig" heisst hier: einmal angefasst. Ob der
              Text gut ist, kann niemand ausser Uwe beurteilen — aber ob er
              ihn je gelesen hat, sieht man daran, ob er ihn geaendert hat. */
        $gesehen = self::gesehenAm();
        $punkte[] = [
            'schluessel' => 'standard',
            'was'    => 'Hausregeln durchgesehen',
            'fertig' => self::gesehen(),
            'stand'  => self::eigener()
                ? 'eigene Fassung' . ($gesehen !== null ? ', zuletzt ' . Fmt::seit($gesehen) : '')
                : 'gelesen und für richtig befunden' . ($gesehen !== null ? ', ' . Fmt::seit($gesehen) : ''),
            'warum'  => 'Noch die Vorgabe von mir, und noch nicht abgehakt. Lies sie einmal '
                      . 'durch und ändere, was für deine Kunden nicht stimmt — sie hängt an '
                      . 'jedem Briefing.',
            'ziel'   => 'standard',
            'wohin'  => 'ansehen',
        ];

        $offen = [];
        foreach ($punkte as $p) {
            if (!$p['fertig']) { $offen[] = (string) $p['schluessel']; }
        }
        return ['punkte' => $punkte, 'offen' => $offen, 'gesamt' => count($punkte)];
    }

    /**
     * Wohin der Knopf fuehrt.
     *
     * Steht ein Projekt da, dorthin — dann liegt das Gespraech gleich am
     * richtigen Ort. Sonst auf einen frischen Chat. Das Briefing liegt in
     * beiden Faellen schon in der Zwischenablage, siehe werkstatt.js:
     * Verlaesst man sich auf das Vorbefuellen ueber die Adresse, steht man
     * ohne Text da, sobald claude.ai das nicht mehr unterstuetzt.
     */
    public static function claudeZiel(): string
    {
        $p = self::claudeProjekt();
        return $p !== '' ? $p : 'https://claude.ai/new';
    }
}
