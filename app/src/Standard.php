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
- Der violett-blaue Verlauf. Drei gleiche Kaestchen nebeneinander.
- Erfundene Bewertungen, Zahlen oder Auszeichnungen. Nie, auch nicht als
  Platzhalter, ohne dass es danebensteht.

RECHTLICHES UND AUFFINDBARKEIT
- Impressum und Datenschutz auf jeder Seite im Fuss verlinkt, in jeder
  Sprache erreichbar, die die Seite fuehrt. Cookie-Hinweis nur, wenn wirklich gesetzt wird — und
  dann mit echter Ablehnmoeglichkeit.
- Je Seite und Sprache ein eigener Titel und eine eigene Beschreibung.
  OG-Bild, favicon, sitemap.xml, robots.txt.
- Keine Schriften und Karten von fremden Servern nachladen, ohne dass es in
  der Datenschutzerklaerung steht. Am einfachsten: selbst ausliefern.

BEVOR ES LIVE GEHT
- Der Abnahme-Check in der Verwaltung laeuft durch. Was er anmeckert, wird
  behoben oder bewusst abgehakt — nicht uebersehen.
- Auf einem echten Telefon angesehen, nicht nur im schmalen Fenster.
- Konsole ohne Fehler, keine fehlgeschlagenen Anfragen.

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

    public static function gesehen(): bool
    {
        return self::gesehenAm() !== null;
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
            'fertig' => $gesehen !== null,
            'stand'  => self::eigener()
                ? 'eigene Fassung, zuletzt ' . Fmt::seit((string) $gesehen)
                : 'gelesen und für richtig befunden, ' . Fmt::seit((string) $gesehen),
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
