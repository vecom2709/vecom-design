<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';

/**
 * Ob die Absenderdomain so im DNS steht, dass Postfaecher unsere Mails
 * annehmen — SPF, DKIM, DMARC.
 *
 * WARUM DAS HIER STEHT UND NICHT IN EINER ANLEITUNG
 *
 * Diese drei Eintraege richtet man einmal ein und sieht sie nie wieder an.
 * Genau deshalb sind sie die Art von Sache, die ein Jahr spaeter still kaputt
 * ist: Jemand raeumt im DNS auf, ein Anbieterwechsel setzt die Zone neu, ein
 * Schluessel wird bei Brevo neu erzeugt. Nach aussen merkt man davon nichts —
 * Mails gehen weiterhin raus, sie landen nur zunehmend im Spam. Bis jemand
 * anruft und sagt, er habe nie eine Rechnung bekommen.
 *
 * Deshalb wird taeglich nachgefragt, nicht einmal geprueft.
 *
 * WAS DIE PRUEFUNG NICHT KANN
 *
 * Sie liest das DNS, nicht den Kopf einer wirklich zugestellten Mail. Dass die
 * Eintraege dastehen, heisst noch nicht, dass Brevo auch mit ihnen signiert.
 * Den Rest sagt nur eine echte Mail — siehe pruefadresse().
 */
final class Zustellbarkeit
{
    /** Brevo signiert ueber zwei Selektoren; frueher war es einer. */
    private const SELEKTOREN     = ['brevo1', 'brevo2'];
    private const ALT_SELEKTOREN = ['mail'];

    /**
     * Wohin die Probemail geht, wenn nichts anderes eingetragen ist.
     *
     * Warum nicht der bekannte Antwort-Dienst check-auth@verifier.port25.com:
     * Der antwortet an den Rueckweg der Mail, und den setzt Brevo auf eine
     * eigene Domain. Die Auswertung kaeme also bei Brevo an, nicht bei uns.
     * mail-tester zeigt das Ergebnis stattdessen auf einer Webseite — das
     * funktioniert unabhaengig davon, wer im Rueckweg steht.
     */
    public const PRUEFDIENST = 'https://www.mail-tester.com';

    /**
     * Die Domain, um die es geht: die des Absenders, nicht die der Website.
     * Beides ist hier dasselbe — aber wenn Uwe den Absender einmal aendert,
     * soll die Pruefung mitwandern und nicht die alte Domain weiterloben.
     */
    public static function domain(): string
    {
        require_once __DIR__ . '/Versand.php';
        $von = (string) self::still(fn() => Versand::absender(), '');
        if ($von !== '' && str_contains($von, '@')) {
            return strtolower(trim(explode('@', $von)[1]));
        }
        $seite = (string) Config::get('website', '');
        return strtolower((string) (parse_url($seite, PHP_URL_HOST) ?: 'vecom-design.it'));
    }

    /**
     * Der zuletzt gespeicherte Befund — ohne eine einzige DNS-Abfrage.
     *
     * Die Ansicht liest immer das hier, nie pruefen(). Eine haengende
     * Namensaufloesung darf die Verwaltung nicht festhalten, und ein Befund
     * von heute Nacht ist fuer diese Frage frisch genug: DNS-Eintraege
     * aendern sich nicht stuendlich.
     *
     * @return array{domain:string,stand:string,punkte:list<array<string,string>>,geprueft:?string}
     */
    public static function stand(): array
    {
        $roh = (string) self::still(fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'zustellbarkeit_befund'", [], ''), '');
        $d = $roh !== '' ? json_decode($roh, true) : null;
        if (!is_array($d) || empty($d['punkte'])) {
            return ['domain' => self::domain(), 'stand' => 'unbekannt', 'punkte' => [], 'geprueft' => null];
        }
        return $d + ['geprueft' => null];
    }

    /**
     * Alle drei Eintraege nachschlagen und den Befund festhalten.
     *
     * @return array{domain:string,stand:string,punkte:list<array{name:string,stand:string,text:string,wert:string}>}
     */
    public static function pruefen(): array
    {
        $domain = self::domain();

        // Dem Aufloeser eine kurze Leine geben. dns_get_record kennt keine
        // Zeitgrenze; ohne das kann eine einzige haengende Abfrage die Seite
        // festhalten. Die Bibliothek liest das beim naechsten Aufruf.
        @putenv('RES_OPTIONS=timeout:3 attempts:1');

        if (!function_exists('dns_get_record')) {
            // Auf manchen Tarifen ist die Funktion abgeschaltet. Das ist kein
            // Fehler an der Domain — also auch keine rote Meldung.
            $befund = ['domain' => $domain, 'stand' => 'unbekannt', 'geprueft' => date('Y-m-d H:i:s'),
                'punkte' => [['name' => 'DNS', 'stand' => 'unbekannt', 'wert' => '',
                    'text' => 'Dieser Server darf keine DNS-Abfragen stellen — die Einträge lassen sich von hier aus nicht prüfen.']]];
            self::merken($befund);
            return $befund;
        }

        $punkte = [self::spf($domain), self::dkim($domain), self::dmarc($domain)];

        $stand = 'gut';
        foreach ($punkte as $p) {
            if ($p['stand'] === 'schlecht') { $stand = 'schlecht'; break; }
            if ($p['stand'] === 'warnung')  { $stand = 'warnung'; }
        }
        $befund = ['domain' => $domain, 'stand' => $stand, 'punkte' => $punkte,
                   'geprueft' => date('Y-m-d H:i:s')];
        self::merken($befund);
        return $befund;
    }

    private static function merken(array $befund): void
    {
        self::still(fn() => Db::run(
            "INSERT INTO settings (skey, svalue) VALUES ('zustellbarkeit_befund', ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode($befund, JSON_UNESCAPED_UNICODE)]), null);
    }

    /* ================================================================== */
    /*  Die einzelnen Eintraege                                           */
    /* ================================================================== */

    private static function spf(string $domain): array
    {
        $zeilen = self::txt($domain);
        $spf = '';
        foreach ($zeilen as $z) {
            if (stripos($z, 'v=spf1') === 0) { $spf = $z; break; }
        }
        if ($spf === '') {
            return ['name' => 'SPF', 'stand' => 'schlecht', 'wert' => '',
                'text' => 'Es gibt keinen SPF-Eintrag. Manche Postfächer werten das als Verdachtsmoment.'];
        }
        if (!str_contains($spf, 'spf.brevo.com')) {
            return ['name' => 'SPF', 'stand' => 'warnung', 'wert' => $spf,
                'text' => 'SPF steht, kennt aber Brevo nicht. Brevo verlangt es nicht (die Zustellung hängt an DKIM), '
                    . 'aber „include:spf.brevo.com" ergänzt schadet nicht.'];
        }
        return ['name' => 'SPF', 'stand' => 'gut', 'wert' => $spf, 'text' => 'Steht und kennt Brevo.'];
    }

    private static function dkim(string $domain): array
    {
        $gefunden = [];
        foreach (self::SELEKTOREN as $s) {
            $t = self::dkimSchluessel("$s._domainkey.$domain");
            if ($t !== '') { $gefunden[] = $s; }
        }
        if (count($gefunden) === count(self::SELEKTOREN)) {
            return ['name' => 'DKIM', 'stand' => 'gut', 'wert' => implode(', ', $gefunden),
                'text' => 'Beide Schlüssel von Brevo sind da und lösen auf. Das ist der Eintrag, an dem die Zustellung hängt.'];
        }
        if ($gefunden) {
            return ['name' => 'DKIM', 'stand' => 'warnung', 'wert' => implode(', ', $gefunden),
                'text' => 'Nur ein Schlüssel von zweien ist erreichbar. Brevo wechselt zwischen beiden — '
                    . 'fehlt einer, scheitert jede zweite Signatur.'];
        }
        foreach (self::ALT_SELEKTOREN as $s) {
            if (self::dkimSchluessel("$s._domainkey.$domain") !== '') {
                return ['name' => 'DKIM', 'stand' => 'warnung', 'wert' => $s,
                    'text' => 'Es gibt nur den alten einzelnen Schlüssel. Er funktioniert, '
                        . 'aber Brevo empfiehlt inzwischen die beiden CNAME-Einträge.'];
            }
        }
        return ['name' => 'DKIM', 'stand' => 'schlecht', 'wert' => '',
            'text' => 'Kein DKIM-Schlüssel gefunden. Ohne ihn ist keine Mail als von dieser Domain nachweisbar — '
                . 'das ist der wichtigste der drei Einträge.'];
    }

    private static function dmarc(string $domain): array
    {
        $zeilen = self::txt('_dmarc.' . $domain);
        $d = '';
        foreach ($zeilen as $z) {
            if (stripos($z, 'v=DMARC1') === 0) { $d = $z; break; }
        }
        if ($d === '') {
            return ['name' => 'DMARC', 'stand' => 'warnung', 'wert' => '',
                'text' => 'Kein DMARC-Eintrag. Große Anbieter erwarten ihn inzwischen, und ohne ihn '
                    . 'erfährst du nie, ob jemand in deinem Namen schreibt.'];
        }
        $politik = 'none';
        if (preg_match('~\bp\s*=\s*([a-z]+)~i', $d, $t)) { $politik = strtolower($t[1]); }

        if ($politik === 'none') {
            return ['name' => 'DMARC', 'stand' => 'gut', 'wert' => $d,
                'text' => 'Steht auf „none" — das beobachtet nur und weist nichts ab. Als Anfang richtig. '
                    . 'Wenn über Wochen alles sauber signiert ist, wäre „quarantine" der nächste Schritt.'];
        }
        return ['name' => 'DMARC', 'stand' => 'gut', 'wert' => $d,
            'text' => 'Steht auf „' . $politik . '". Fremde Absender in deinem Namen werden abgewiesen.'];
    }

    /* ================================================================== */
    /*  Taeglich, aber nicht taeglich meckernd                            */
    /* ================================================================== */

    /**
     * Meldet nur, wenn sich der Zustand verschlechtert — und einmal, wenn er
     * sich wieder erholt. Eine Meldung, die jeden Tag dasselbe sagt, liest
     * nach einer Woche niemand mehr.
     */
    public static function taeglich(): array
    {
        $e = self::pruefen();
        $vorher = (string) self::still(fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'zustellbarkeit_stand'", [], ''), '');

        $rang = ['gut' => 0, 'unbekannt' => 1, 'warnung' => 2, 'schlecht' => 3];
        $jetzt = $rang[$e['stand']] ?? 1;
        $alt   = $rang[$vorher] ?? 0;

        if ($jetzt > $alt) {
            $schlimm = array_values(array_filter($e['punkte'],
                static fn($p) => $p['stand'] === 'schlecht' || $p['stand'] === 'warnung'));
            $text = implode(' · ', array_map(static fn($p) => $p['name'] . ': ' . $p['text'], $schlimm));
            // "nicht mehr" nur, wenn es vorher schon einen Befund gab. Beim
            // allerersten Lauf war nie etwas in Ordnung, das jetzt kaputt sein
            // koennte — und eine Meldung, die das Gegenteil behauptet, schickt
            // Uwe auf die Suche nach einer Aenderung, die es nicht gab.
            $zuvor = $vorher !== '';
            self::still(fn() => Events::melden('zustellbarkeit',
                $e['stand'] === 'schlecht'
                    ? ('Die Absenderdomain ist nicht' . ($zuvor ? ' mehr' : '') . ' richtig eingetragen')
                    : ('An der Absenderdomain stimmt etwas nicht' . ($zuvor ? ' mehr' : '')),
                $e['stand'] === 'schlecht' ? 'schlecht' : 'warnung',
                mb_substr($text, 0, 400), '/monitoring'), null);
        } elseif ($jetzt < $alt && $jetzt === 0) {
            self::still(fn() => Events::melden('zustellbarkeit',
                'Die Absenderdomain ist wieder in Ordnung', 'gut',
                'SPF, DKIM und DMARC stehen wieder vollständig.', '/monitoring'), null);
        }

        self::still(fn() => Db::run(
            "INSERT INTO settings (skey, svalue) VALUES ('zustellbarkeit_stand', ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$e['stand']]), null);

        return ['stand' => $e['stand'], 'domain' => $e['domain']];
    }

    /* ================================================================== */
    /*  Kleinkram                                                         */
    /* ================================================================== */

    /** @return list<string> */
    private static function txt(string $name): array
    {
        $aus = [];
        try {
            $r = @dns_get_record($name, DNS_TXT);
            foreach (is_array($r) ? $r : [] as $z) {
                if (isset($z['txt']))       { $aus[] = (string) $z['txt']; }
                elseif (isset($z['entries'])) { $aus[] = implode('', (array) $z['entries']); }
            }
        } catch (Throwable $e) { /* dann eben leer */ }
        return $aus;
    }

    /**
     * Der oeffentliche Schluessel hinter einem Selektor. Brevo legt die
     * Eintraege als CNAME an, die erst bei Brevo auf den eigentlichen Text
     * zeigen. Manche Aufloeser folgen dem von allein, manche nicht — deshalb
     * beide Wege.
     */
    private static function dkimSchluessel(string $name): string
    {
        // Erst der CNAME, dann der Text beim Ziel — und nicht umgekehrt.
        // Der Grund ist gemessen: Eine TXT-Abfrage auf einen Namen, der ein
        // CNAME zu einem fremden Server ist, laeuft bei manchen Aufloesern in
        // eine Zeitueberschreitung, die PHP nicht abbrechen kann. Der Umweg
        // ueber zwei kurze Abfragen dauert Millisekunden.
        try {
            $r = @dns_get_record($name, DNS_CNAME);
            foreach (is_array($r) ? $r : [] as $z) {
                if (empty($z['target'])) { continue; }
                foreach (self::txt((string) $z['target']) as $t) {
                    if (str_contains($t, 'p=')) { return $t; }
                }
                // Ein CNAME ohne lesbaren Text: Das Ziel antwortet nicht, aber
                // der Eintrag steht. Kein Grund fuer Alarm, wohl aber fuer
                // "nicht bestaetigt".
                return '';
            }
        } catch (Throwable $e) { /* dann der direkte Weg */ }

        foreach (self::txt($name) as $t) {
            if (str_contains($t, 'p=')) { return $t; }
        }
        return '';
    }

    /**
     * Verschickt eine echte Mail auf dem echten Weg. Das ist der Teil, den
     * die DNS-Abfrage nicht kann: Ob Brevo auch wirklich mit unserem
     * Schluessel signiert, sieht man nur an einer zugestellten Nachricht.
     *
     * @return array{ok:bool,text:string}
     */
    public static function probemail(string $an): array
    {
        $an = trim($an);
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'text' => 'Das ist keine gültige E-Mail-Adresse.'];
        }
        require_once __DIR__ . '/Mail.php';

        $domain = self::domain();
        $text = "Das ist eine Probenachricht aus der Vecom-Design-Verwaltung.\n\n"
            . "Sie dient nur einem Zweck: nachzusehen, ob eine über den normalen Weg verschickte\n"
            . "Mail von $domain als echt erkannt wird — SPF, DKIM und DMARC.\n\n"
            . "Verschickt am " . date('d.m.Y H:i:s') . ".\n";

        // Bewusst ohne customer_id: Diese Mail gehoert zu keinem Kunden, und
        // im Betreff hat eine Kundennummer hier nichts verloren.
        $ok = Mail::senden('zustellbarkeit_probe', $an,
            'Probenachricht von ' . $domain, $text, []);

        return $ok
            ? ['ok' => true, 'text' => 'Probenachricht an ' . $an . ' ist raus.']
            : ['ok' => false, 'text' => 'Die Probenachricht ging nicht raus — siehe Einstellungen, E-Mail-Versand.'];
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
