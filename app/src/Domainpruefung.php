<?php
declare(strict_types=1);

/**
 * Ist diese Wunschadresse noch frei?
 *
 * WARUM ES DIESE PRUEFUNG GIBT
 *
 * Ein Kunde ohne Website schreibt einen Wunsch in den Fragebogen. Die
 * Adresse ist vergeben — was in neun von zehn Faellen so ist, wenn sie kurz
 * und naheliegend ist. Dann beginnt das Hin und Her: Ich schreibe ihm, er
 * antwortet in zwei Tagen mit dem naechsten Wunsch, der auch weg ist. Eine
 * Woche fuer eine Auskunft, die eine Sekunde dauert.
 *
 * Deshalb fragt der Fragebogen nach DREI Wuenschen und sagt sofort daneben,
 * was frei ist.
 *
 * WIE GEPRUEFT WIRD
 *
 * Ueber RDAP, den Nachfolger von Whois — und zwar direkt bei der
 * Registrierungsstelle. Welche das je Endung ist, steht in einer Liste der
 * IANA, die einmal geholt und eine Woche lang aufgehoben wird.
 *
 * Der naheliegende Weg waere rdap.org gewesen, ein Vermittler, der die
 * Anfrage selbst weiterleitet. Gemessen war er zweimal untauglich. Erstens
 * begrenzt er auf zehn Anfragen in zehn Sekunden, gezaehlt pro IP-Adresse
 * — und auf einem geteilten Server ist das nicht unsere Adresse allein.
 * Im Test kamen nach acht Abfragen nur noch "unklar" zurueck. Zweitens
 * antwortet er SELBST mit 404, wenn er fuer eine Endung keinen Server
 * kennt; wer das nicht von der Antwort der Registrierungsstelle
 * unterscheidet, meldet jede exotische Endung als frei.
 *
 * Ohne den Vermittler faellt beides weg: kein fremdes Limit, und wer
 * geantwortet hat, ist keine Frage mehr.
 *
 * WAS DIESE KLASSE NICHT TUT
 *
 * Sie verspricht nichts. "frei" heisst: Die Registrierungsstelle sagt, die
 * Adresse ist nicht vergeben. Zwischen dieser Auskunft und der Bestellung
 * koennen Minuten liegen, und in Minuten wird registriert. Und wo keine
 * verlaessliche Auskunft zu holen ist, steht "unklar" — nicht "frei". Ein
 * falsches "frei" ist teurer als ein ehrliches Achselzucken: Der Kunde
 * freut sich, und ich muss es ihm hinterher wegnehmen.
 */
final class Domainpruefung
{
    public const FREI    = 'frei';
    public const VERGEBEN = 'vergeben';
    public const UNKLAR  = 'unklar';
    public const UNGUELTIG = 'ungueltig';

    /** Wie lange eine Auskunft gilt. Laenger waere gelogen, kuerzer Laerm. */
    private const HALTBAR = 900;          // 15 Minuten

    /** Obergrenze je Sitzung. Wer mehr braucht, sucht keine Domain mehr. */
    private const HOECHSTENS = 80;

    private const ZEITGRENZE = 5;         // Sekunden fuer die ganze Abfrage

    /**
     * @return array{stand:string, name:string, weg:string}
     */
    public static function pruefen(string $roh): array
    {
        $name = self::normalisieren($roh);
        if ($name === null) { return ['stand' => self::UNGUELTIG, 'name' => trim($roh), 'weg' => '']; }

        /* Zwischenspeicher in der Sitzung. Der Kunde tippt, korrigiert und
           tippt zurueck — ohne das waere jede Rueckkehr zum alten Wort eine
           neue Abfrage bei einem fremden Dienst. */
        if (session_status() === PHP_SESSION_ACTIVE) {
            $merk = $_SESSION['domainpruefung'] ?? [];
            if (isset($merk[$name]) && ($merk[$name]['bis'] ?? 0) > time()) {
                return ['stand' => (string) $merk[$name]['stand'], 'name' => $name, 'weg' => 'merk'];
            }
            if ((int) ($_SESSION['domainpruefung_zahl'] ?? 0) >= self::HOECHSTENS) {
                return ['stand' => self::UNKLAR, 'name' => $name, 'weg' => 'grenze'];
            }
            $_SESSION['domainpruefung_zahl'] = (int) ($_SESSION['domainpruefung_zahl'] ?? 0) + 1;
        }

        /* DIE LEITER: JEDE STUFE DARF NUR SAGEN, WAS SIE WIRKLICH WEISS
           ------------------------------------------------------------------
           RDAP zuerst, weil es die klarste Auskunft gibt. Dann Whois, weil
           es fuer .it die einzige ist. Dann DNS, das nur "vergeben" sagen
           kann. Wer nichts weiss, sagt "unklar" und nicht "frei". */
        $weg   = 'rdap';
        $stand = self::ueberRdap($name);
        if ($stand === self::UNKLAR) { $weg = 'whois'; $stand = self::ueberWhois($name); }
        if ($stand === self::UNKLAR) { $weg = 'dns';   $stand = self::ueberDns($name); }
        if ($stand === self::UNKLAR) { $weg = 'nichts'; }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $merk = $_SESSION['domainpruefung'] ?? [];
            $merk[$name] = ['stand' => $stand, 'bis' => time() + self::HALTBAR];
            if (count($merk) > 120) { $merk = array_slice($merk, -60, null, true); }
            $_SESSION['domainpruefung'] = $merk;
        }
        return ['stand' => $stand, 'name' => $name, 'weg' => $weg];
    }

    /**
     * Klein schreiben, Beiwerk abschneiden, das Offensichtliche abweisen.
     *
     * Kunden schreiben "www.trattoria.it", "https://trattoria.it" und
     * "Trattoria Rossi .it". Das ist kein Fehler, das ist normal — die
     * Adresse steht auf ihrem Auto so drauf. Also wird geputzt, nicht
     * gemeckert.
     */
    public static function normalisieren(string $roh): ?string
    {
        $n = mb_strtolower(trim($roh));
        $n = preg_replace('~^[a-z]+://~', '', $n) ?? $n;
        $n = preg_replace('~^www\.~', '', $n) ?? $n;
        $n = explode('/', $n)[0];
        $n = trim($n, " \t\n\r\0\x0B.");
        $n = str_replace(' ', '', $n);

        /* Umlaute und Akzente gehen — als Punycode. Ohne diese Zeile faellt
           "cafécentrale.it" durch die Pruefung, obwohl es die Adresse
           wirklich geben kann. */
        if (preg_match('~[^\x20-\x7E]~', $n) && function_exists('idn_to_ascii')) {
            $p = idn_to_ascii($n, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($p) && $p !== '') { $n = $p; }
        }

        if (!preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$~', $n)) {
            return null;
        }
        if (mb_strlen($n) > 253) { return null; }
        return $n;
    }

    /** Die Auskunft der Registrierungsstelle, direkt gefragt. */
    private static function ueberRdap(string $name): string
    {
        $endung = substr($name, (int) strrpos($name, '.') + 1);
        $basis  = self::stelleFuer($endung);
        if ($basis === null) { return self::UNKLAR; }   // keine Stelle fuer diese Endung

        $ch = curl_init(rtrim($basis, '/') . '/domain/' . rawurlencode($name));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => self::ZEITGRENZE,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/rdap+json'],
            CURLOPT_USERAGENT      => 'vecom-design.it Domainpruefung',
        ]);
        $koerper = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler  = curl_errno($ch);
        curl_close($ch);

        if ($fehler !== 0) { return self::UNKLAR; }   // Zeitgrenze, Netz, DNS
        if ($code === 429 || $code === 403) { return self::UNKLAR; }
        if ($code === 404) { return self::FREI; }
        if ($code >= 200 && $code < 300) {
            /* Manche Stellen antworten mit 200 und einem Fehlerobjekt statt
               mit 404. Wer nur auf den Code sieht, meldet dann alles als
               vergeben. */
            $d = json_decode((string) $koerper, true);
            if (is_array($d) && isset($d['errorCode']) && (int) $d['errorCode'] === 404) {
                return self::FREI;
            }
            return self::VERGEBEN;
        }
        return self::UNKLAR;
    }

    /**
     * Welcher Server ist fuer diese Endung zustaendig?
     *
     * Die IANA fuehrt die Liste. Sie aendert sich selten — einmal die Woche
     * zu holen ist reichlich, und wenn sie sich nicht holen laesst, gilt die
     * alte weiter. Eine Domainpruefung ist kein Grund, eine Seite anzuhalten.
     */
    private static function stelleFuer(string $endung): ?string
    {
        if (isset(self::EXTRA[$endung])) { return self::EXTRA[$endung]; }
        static $liste = null;
        if ($liste === null) { $liste = self::liste(); }
        return $liste[$endung] ?? null;
    }

    /** @return array<string,string> Endung => Adresse der Registrierungsstelle */
    private static function liste(): array
    {
        $datei = self::ordner() . '/rdap-stellen.json';
        if (is_file($datei) && (time() - (int) @filemtime($datei)) < 7 * 86400) {
            $d = json_decode((string) @file_get_contents($datei), true);
            if (is_array($d) && $d) { return $d; }
        }

        $ch = curl_init('https://data.iana.org/rdap/dns.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'vecom-design.it Domainpruefung',
        ]);
        $roh  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $aus = [];
        if ($code === 200) {
            $d = json_decode((string) $roh, true);
            foreach ((array) ($d['services'] ?? []) as $dienst) {
                $endungen = (array) ($dienst[0] ?? []);
                $adressen = (array) ($dienst[1] ?? []);
                $adresse  = '';
                foreach ($adressen as $a) {
                    if (str_starts_with((string) $a, 'https://')) { $adresse = (string) $a; break; }
                }
                if ($adresse === '') { continue; }
                foreach ($endungen as $e) { $aus[mb_strtolower((string) $e)] = $adresse; }
            }
        }

        if ($aus) {
            @file_put_contents($datei, json_encode($aus, JSON_UNESCAPED_SLASHES));
            return $aus;
        }
        /* Nichts bekommen: lieber eine veraltete Liste als gar keine. */
        if (is_file($datei)) {
            $d = json_decode((string) @file_get_contents($datei), true);
            if (is_array($d) && $d) { return $d; }
        }
        return [];
    }

    /** Der Ordner fuer die Liste — gesperrt wie jeder andere Ablageordner. */
    private static function ordner(): string
    {
        $pfad = dirname(__DIR__) . '/zwischenspeicher';
        if (!is_dir($pfad)) { @mkdir($pfad, 0755, true); }
        if (!is_dir($pfad) || !is_writable($pfad)) { return sys_get_temp_dir(); }
        $sperre = $pfad . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        return $pfad;
    }

    /* STELLEN, DIE ES GIBT, ABER NICHT IN DER LISTE STEHEN
       --------------------------------------------------------------------
       Die IANA-Liste fuehrt alle allgemeinen Endungen und ein paar
       Laenderendungen. Deutschland gehoert nicht dazu, hat aber sehr wohl
       einen Server — gemessen: rdap.denic.de antwortet. Fuer .it gibt es
       keinen (rdap.nic.it loest nicht einmal auf); dort greift Whois. */
    private const EXTRA = [
        'de' => 'https://rdap.denic.de/',
    ];

    /* WHOIS AUF PORT 43 — FUER .IT DIE EINZIGE AUSKUNFT
       --------------------------------------------------------------------
       Alt, aber es funktioniert und ist fuer die Endung, um die es hier vor
       allem geht, alternativlos. Je Endung der Server und das Wort, an dem
       eine freie Adresse zu erkennen ist. Nur dieses Wort zaehlt als "frei"
       — der Umkehrschluss "kein Wort gefunden, also vergeben" waere falsch,
       weil auch eine Fehlermeldung kein Wort enthaelt.

       Ob Port 43 vom Server aus ueberhaupt offen ist, haengt am Hoster. Ist
       er es nicht, faellt die Stufe still durch auf DNS. */
    private const WHOIS = [
        'it' => ['whois.nic.it',   'available'],
        'eu' => ['whois.eu',       'available'],
        'ch' => ['whois.nic.ch',   'do not have an entry'],
        'li' => ['whois.nic.li',   'do not have an entry'],
        'at' => ['whois.nic.at',   'nothing found'],
        'fr' => ['whois.nic.fr',   'no entries found'],
        'es' => ['whois.nic.es',   'not found'],
    ];

    private static function ueberWhois(string $name): string
    {
        $endung = substr($name, (int) strrpos($name, '.') + 1);
        if (!isset(self::WHOIS[$endung])) { return self::UNKLAR; }
        [$server, $freiWort] = self::WHOIS[$endung];

        /* EINMAL VERGEBLICH IST GENUG
           Ist Port 43 vom Server aus zu, laeuft jede Anfrage in dieselbe
           Zeitgrenze. Bei drei Wunschadressen waeren das sechs Sekunden
           Warten fuer dreimal dasselbe Nichts. Also wird gemerkt, dass es
           nicht geht, und der Rest der Anfrage geht direkt weiter. */
        static $zu = false;
        if ($zu) { return self::UNKLAR; }

        $f = @fsockopen($server, 43, $nr, $txt, 2);
        if (!$f) { $zu = true; return self::UNKLAR; }
        stream_set_timeout($f, 4);
        @fwrite($f, $name . "\r\n");
        $antwort = '';
        $bis = microtime(true) + 5;
        while (!feof($f) && microtime(true) < $bis && mb_strlen($antwort) < 20000) {
            $stueck = fgets($f, 512);
            if ($stueck === false) { break; }
            $antwort .= $stueck;
        }
        fclose($f);

        $klein = mb_strtolower($antwort);
        if ($klein === '') { return self::UNKLAR; }
        if (str_contains($klein, mb_strtolower($freiWort))) { return self::FREI; }
        /* Eine Auskunft, die den Namen und ein Datum traegt, ist ein
           Datensatz — und ein Datensatz heisst vergeben. */
        if (str_contains($klein, mb_strtolower($name))
            && (str_contains($klein, 'created') || str_contains($klein, 'registrar')
                || str_contains($klein, 'status') || str_contains($klein, 'holder'))) {
            return self::VERGEBEN;
        }
        return self::UNKLAR;
    }

    /**
     * Der Notnagel, wenn es fuer eine Endung keine RDAP-Auskunft gibt.
     *
     * Namensserver heisst: Die Adresse ist vergeben und benutzt sie — das
     * ist eine sichere Aussage. Der Umkehrschluss ist keine: Eine
     * registrierte Adresse kann ohne Namensserver herumliegen, jahrelang.
     * Deshalb gibt es hier nur "vergeben" oder "unklar", nie "frei".
     */
    private static function ueberDns(string $name): string
    {
        $alt = ini_get('default_socket_timeout');
        @ini_set('default_socket_timeout', '3');
        $ns = @dns_get_record($name, DNS_NS);
        @ini_set('default_socket_timeout', (string) $alt);
        return (is_array($ns) && $ns) ? self::VERGEBEN : self::UNKLAR;
    }

    /** Der Satz, den der Kunde daneben liest. */
    public static function wort(string $stand, string $sprache): string
    {
        $w = [
            self::FREI => ['it' => 'Sembra libero.', 'de' => 'Sieht frei aus.', 'en' => 'Looks available.'],
            self::VERGEBEN => ['it' => 'Già occupato — prova un’altra.',
                               'de' => 'Schon vergeben — nimm eine andere.',
                               'en' => 'Already taken — try another.'],
            self::UNKLAR => ['it' => 'Non posso dirlo con certezza — controllo io.',
                             'de' => 'Kann ich nicht sicher sagen — ich sehe selbst nach.',
                             'en' => 'I can’t say for sure — I’ll check myself.'],
            self::UNGUELTIG => ['it' => 'Non sembra un indirizzo. Esempio: latuaazienda.it',
                                'de' => 'Das sieht nicht nach einer Adresse aus. Beispiel: deinefirma.it',
                                'en' => 'That doesn’t look like an address. Example: yourcompany.com'],
        ];
        return Texte::h($w[$stand] ?? [], $sprache, $stand);
    }
}
