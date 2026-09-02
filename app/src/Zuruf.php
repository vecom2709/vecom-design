<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Der Zuruf aufs Handy — eine WhatsApp-Nachricht, wenn etwas passiert.
 *
 * WOFUER DAS DA IST UND WOFUER NICHT
 *
 * Die E-Mail und der Eintrag in der Verwaltung sind der Nachweis. Der Zuruf
 * ist nur das Klingeln: "Schau rein." Er darf deshalb ausfallen, ohne dass
 * irgendetwas verloren geht — und genau darum ruft ihn niemand so auf, dass
 * ein Fehlschlag einen Vorgang aufhalten koennte.
 *
 * Sein eigentlicher Wert liegt woanders: Er laeuft an Brevo vorbei. Als
 * dieser Weg gebaut wurde, antwortete Brevo gerade mit 500 — die
 * Benachrichtigung ueber eine neue Anfrage kam also gar nicht an. Ein
 * zweiter Kanal ist nur dann etwas wert, wenn er nicht an derselben Sache
 * haengt wie der erste.
 *
 * KEINE PERSONENBEZOGENEN DATEN
 *
 * Verschickt wird, DASS etwas ist, und wo es steht. Nie ein Kundenname, nie
 * eine Adresse, nie der Text einer Anfrage. Der Weg laeuft ueber einen
 * fremden Dienst; was dort nicht ankommt, kann dort auch nicht liegen
 * bleiben. Bei Stoerungen geht ausschliesslich der TITEL der Meldung raus —
 * die Titel sind durchweg allgemein ("Website nicht erreichbar"), waehrend
 * Domain und Einzelheiten im Text stehen, der hier nicht mitkommt.
 *
 * DER DIENST
 *
 * CallMeBot: kostenlos, ausdruecklich fuer den eigenen Gebrauch, keine
 * Vorlagen und keine Verifizierung. Dafuer inoffiziell — er kann ohne
 * Ankuendigung verschwinden. Deshalb liegt der Aufruf hinter dieser einen
 * Klasse: Faellt er weg oder soll spaeter der offizielle Weg ueber Meta her,
 * wird hier das Innere getauscht und sonst nichts.
 */
final class Zuruf
{
    private const NUMMER   = 'zuruf_nummer';
    private const KEY      = 'zuruf_key';
    private const AN       = 'zuruf_an';
    private const ZULETZT  = 'zuruf_zuletzt';

    /** Laenger darf ein Zuruf den Server nie aufhalten. */
    private const ZEITGRENZE = 6;

    /** Hoechstens so viele je Lauf — ein Cronlauf soll nicht festhaengen. */
    private const JE_LAUF = 5;

    /** Nach so vielen Fehlversuchen wird ein Zuruf aufgegeben. */
    private const VERSUCHE = 3;

    private static bool $angemeldet = false;

    /* ================================================================== */
    /*  Einstellungen                                                     */
    /* ================================================================== */

    public static function moeglich(): bool
    {
        return self::wert(self::AN) === '1'
            && self::wert(self::NUMMER) !== ''
            && self::wert(self::KEY) !== '';
    }

    public static function an(): bool          { return self::wert(self::AN) === '1'; }
    public static function nummer(): string    { return self::wert(self::NUMMER); }
    public static function hatSchluessel(): bool { return self::wert(self::KEY) !== ''; }

    /** Was beim letzten Versuch herauskam — fuer die Anzeige. */
    public static function zuletzt(): string   { return self::wert(self::ZULETZT); }

    /**
     * @return list<string> Fehler; leer heisst gespeichert
     */
    public static function speichern(string $nummer, string $key, bool $an): array
    {
        // Was eingegeben WURDE, und was daraus wird. Die Unterscheidung ist
        // wichtig: "hallo" schrumpft beim Normalisieren auf nichts zusammen.
        // Wuerde das als "nichts eingegeben" durchgehen, bliebe stillschweigend
        // die alte Nummer stehen — und Uwe glaubte, er haette sie geaendert.
        $eingegeben = trim($nummer) !== '';
        $nummer     = self::nummerNormalisieren($nummer);
        $key        = trim($key);
        $fehler     = [];

        if ($eingegeben && !preg_match('/^\+[1-9][0-9]{7,17}$/', $nummer)) {
            $fehler[] = 'Das ist keine brauchbare Nummer. Sie muss mit der Landesvorwahl beginnen,'
                . ' zum Beispiel +39 320 1234567 oder +49 172 1234567.';
        }
        // Der Schluessel von CallMeBot ist eine Zahlenfolge. Mehr Pruefung
        // waere geraten — wenn er falsch ist, sagt es die Testnachricht.
        if ($key !== '' && !preg_match('/^[0-9]{4,12}$/', $key)) {
            $fehler[] = 'Der Schlüssel von CallMeBot besteht nur aus Ziffern.';
        }
        if ($an && ($nummer === '' && self::nummer() === '')) {
            $fehler[] = 'Ohne Nummer kann nichts verschickt werden.';
        }
        if ($fehler) { return $fehler; }

        if ($nummer !== '') { self::merken(self::NUMMER, $nummer); }
        if ($key !== '')    { self::merken(self::KEY, $key); }
        self::merken(self::AN, $an ? '1' : '0');
        return [];
    }

    public static function entfernen(): void
    {
        foreach ([self::NUMMER, self::KEY, self::ZULETZT] as $s) { self::merken($s, ''); }
        self::merken(self::AN, '0');
    }

    /* ================================================================== */
    /*  Senden                                                            */
    /* ================================================================== */

    /**
     * Einen Zuruf in die Warteschlange legen.
     *
     * Hier wird nur geschrieben — nie verschickt. Der Grund steht in
     * Migration 014: Ein fremder Dienst, der langsam antwortet, darf niemals
     * den Besucher des Kontaktformulars warten lassen.
     *
     * @param string $anlass         Kurzname, zugleich der Schluessel fuer die Sperre
     * @param string $text           Was auf dem Handy steht. Ohne Personendaten.
     * @param int    $sperreMinuten  So lange kein zweiter Zuruf desselben Anlasses
     */
    public static function vormerken(string $anlass, string $text, int $sperreMinuten = 0): void
    {
        try {
            if (!self::moeglich()) { return; }
            if ($sperreMinuten > 0 && self::gesperrt($anlass, $sperreMinuten)) { return; }

            Db::insert('zurufe', [
                'anlass' => mb_substr($anlass, 0, 64),
                'text'   => mb_substr($text, 0, 900),
            ]);
            // Die Sperre greift ab dem Einreihen, nicht erst ab dem Versand.
            // Sonst reihen sich zehn gleiche Zurufe ein, waehrend der erste
            // noch unterwegs ist.
            if ($sperreMinuten > 0) { self::sperren($anlass); }
        } catch (Throwable $e) { return; }   // der Zuruf ist Beiwerk

        // Kann der Server die Antwort vorher abschliessen, geht es sofort
        // raus. Kann er es nicht, holt es der naechste Cronlauf — dann eben
        // ein paar Minuten spaeter, aber ohne dass jemand darauf wartet.
        if (!self::$angemeldet && function_exists('fastcgi_finish_request')) {
            self::$angemeldet = true;
            register_shutdown_function([self::class, 'nachDerAntwort']);
        }
    }

    /** Wird am Ende der Anfrage gerufen, nie von Hand. */
    public static function nachDerAntwort(): void
    {
        @fastcgi_finish_request();
        try { self::abarbeiten(); } catch (Throwable $e) { /* Beiwerk */ }
    }

    /**
     * Die Warteschlange leeren. Ruft der Cronjob, und — wo der Server es
     * hergibt — auch das Ende der Anfrage selbst.
     *
     * @return array{verschickt:int,offen:int,aufgegeben:int}
     */
    public static function abarbeiten(): array
    {
        $bilanz = ['verschickt' => 0, 'offen' => 0, 'aufgegeben' => 0];
        try {
            if (!self::moeglich()) { return $bilanz; }
            $offen = Db::all("SELECT * FROM zurufe WHERE status = 'offen' ORDER BY id LIMIT " . self::JE_LAUF);
        } catch (Throwable $e) {
            return $bilanz;   // Tabelle noch nicht da: naechster Lauf
        }

        foreach ($offen as $z) {
            $id = (int) $z['id'];
            [$ok, $grund] = self::hinschicken((string) $z['text']);
            $versuche = (int) $z['versuche'] + 1;

            if ($ok) {
                Db::run("UPDATE zurufe SET status='gesendet', versuche=?, fehler=NULL, gesendet_am=NOW() WHERE id=?",
                    [$versuche, $id]);
                $bilanz['verschickt']++;
                continue;
            }
            // Nach drei Versuchen ist Schluss. Ein Zuruf, den niemand mehr
            // braucht, soll nicht bis in alle Ewigkeit weiterprobiert werden.
            $aufgeben = $versuche >= self::VERSUCHE;
            Db::run("UPDATE zurufe SET status=?, versuche=?, fehler=? WHERE id=?",
                [$aufgeben ? 'aufgegeben' : 'offen', $versuche, mb_substr($grund, 0, 250), $id]);
            $aufgeben ? $bilanz['aufgegeben']++ : $bilanz['offen']++;
        }

        // Was erledigt ist, muss nicht ewig herumliegen.
        try {
            Db::run("DELETE FROM zurufe
                      WHERE status <> 'offen' AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)");
        } catch (Throwable $e) { }

        return $bilanz;
    }

    /**
     * Eine Testnachricht, sofort und mit Rueckmeldung — fuer den Knopf in
     * den Einstellungen.
     *
     * @return array{ok:bool,text:string}
     */
    public static function pruefen(): array
    {
        if (self::nummer() === '' || !self::hatSchluessel()) {
            return ['ok' => false, 'text' => 'Es fehlt die Nummer oder der Schlüssel.'];
        }
        [$ok, $grund] = self::hinschicken('Vecom Design: Test. Wenn du das liest, funktioniert der Zuruf.');
        return $ok
            ? ['ok' => true,  'text' => 'Die Testnachricht ist raus. Schau auf dein Handy.']
            : ['ok' => false, 'text' => 'Es hat nicht geklappt: ' . $grund];
    }

    /**
     * Der eigentliche Aufruf an den Dienst.
     *
     * MELDET NIEMALS UEBER Events::melden(). Der Zuruf haengt selbst an
     * jeder Stoerungsmeldung — ein Fehlschlag, der wieder eine Meldung
     * ausloest, waere eine Schleife. Was schiefging, steht in der
     * Warteschlange und in den Einstellungen unter "zuletzt".
     *
     * @return array{0:bool,1:string} gelungen, und wenn nicht: warum
     */
    private static function hinschicken(string $text): array
    {
        // Die Adresse ist nur zum Durchtesten umstellbar — genauso wie beim
        // Mailversand. Fehlt der Eintrag, und das ist der Normalfall, geht
        // alles an CallMeBot.
        $basis = self::wert('zuruf_api') ?: 'https://api.callmebot.com/whatsapp.php';

        $adresse = $basis
            . '?phone=' . rawurlencode(self::nummer())
            . '&apikey=' . rawurlencode(self::wert(self::KEY))
            . '&text=' . rawurlencode(mb_substr($text, 0, 900));

        $ch = curl_init($adresse);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => self::ZEITGRENZE,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $antwort = (string) curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz    = curl_error($ch);
        curl_close($ch);

        $ok    = $code >= 200 && $code < 300;
        $grund = $ok ? '' : 'HTTP ' . $code . ' '
            . mb_substr(strip_tags($netz !== '' ? $netz : $antwort), 0, 160);

        self::merken(self::ZULETZT, date('d.m.Y H:i') . ($ok ? ' — verschickt' : ' — ' . $grund));
        return [$ok, $grund];
    }

    /* ================================================================== */
    /*  Sperre gegen Dauerklingeln                                        */
    /* ================================================================== */

    private static function gesperrt(string $anlass, int $minuten): bool
    {
        $zuletzt = self::wert('zuruf_sperre_' . $anlass);
        if ($zuletzt === '') { return false; }
        return (time() - (int) strtotime($zuletzt)) < ($minuten * 60);
    }

    private static function sperren(string $anlass): void
    {
        self::merken('zuruf_sperre_' . $anlass, date('Y-m-d H:i:s'));
    }

    /* ================================================================== */
    /*  Kleinkram                                                         */
    /* ================================================================== */

    /** "+39 320 123 45 67" und "0039320…" werden beide zu "+39320…". */
    private static function nummerNormalisieren(string $roh): string
    {
        $n = preg_replace('/[^0-9+]/', '', trim($roh)) ?? '';
        if (str_starts_with($n, '00')) { $n = '+' . substr($n, 2); }
        if ($n !== '' && !str_starts_with($n, '+')) { $n = '+' . $n; }
        return $n === '+' ? '' : $n;
    }

    private static function wert(string $name): string
    {
        try {
            return trim((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$name], ''));
        } catch (Throwable $e) {
            return '';   // vor der ersten Einrichtung gibt es die Tabelle nicht
        }
    }

    private static function merken(string $name, string $wert): void
    {
        try {
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$name, $wert]);
        } catch (Throwable $e) { /* Beiwerk */ }
    }
}
