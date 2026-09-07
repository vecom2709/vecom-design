<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * DER DRAHT ZUM RESELLER-KAS VON ALL-INKL
 * ===========================================================================
 *
 * Seit dem 7.9. ist Uwe Reseller bei All-Inkl. Damit bekommt jeder Kunde
 * einen EIGENEN KAS-Account unter seinem Vertrag: eigener Webspace, eigene
 * Domain, eigene Postfaecher — sauber getrennt von vecom-design.it. Endet
 * ein Projekt, wird der Account uebergeben oder abgeschaltet, ohne dass
 * etwas anderes daran haengt.
 *
 * Die KAS-API ist eine SOAP-Schnittstelle (kasapi.kasserver.com). Diese
 * Klasse spricht sie ohne fremde Bibliothek an — auf dem Webspace gibt es
 * keinen Composer, und eine Abhaengigkeit fuer einen einzigen Aufruftyp
 * waere keine gewesen.
 *
 * WAS DIESE STUFE KANN — UND WAS ABSICHTLICH NOCH NICHT
 *
 * Sie liest (Verbindung pruefen, Accounts auflisten) und legt seit Stufe 2
 * Kunden-Accounts an — auf Knopfdruck in den Einstellungen, nie von
 * allein. LOESCHEN kann sie ausdruecklich nicht: Ein Account, an dem eine
 * Kundenwebsite haengt, verschwindet nur von Hand im KAS, mit allen
 * Warnungen, die All-Inkl dort zeigt. Ein Kettentest wacht darueber, dass
 * diese Klasse keine loeschenden Methoden bekommt.
 *
 * DIE PASSWOERTER DES NEUEN ACCOUNTS
 *
 * add_account verlangt ein KAS- und ein FTP-Passwort. Beide erzeugt der
 * Server selbst (Zufall, 16 Zeichen), zeigt sie GENAU EINMAL in der
 * Verwaltung an und speichert sie nirgends — gemerkt wird nur der neue
 * Login. Wer sie verliert, setzt sie im KAS neu. So laufen sie weder
 * durch einen Chat noch durch eine E-Mail noch in eine Datenbank.
 *
 * DER ZUGANG
 *
 * KAS-Login und KAS-Passwort stehen ausschliesslich in app/config.local.php
 * unter 'kas' — derselbe Ort wie die Stripe-Schluessel, gesetzt ueber die
 * Einstellungen im Browser, nie ueber Chat oder E-Mail, nie im Repository
 * (das ist oeffentlich). Die API kennt laut Doku 'plain' und 'session';
 * wir senden 'plain' — die Datei liegt mit Rechten 600 auf demselben
 * Server, ein zusaetzlicher Hash wuerde nur so tun, als waere sie damit
 * geschuetzt.
 */
final class Kas
{
    /** Die SOAP-Beschreibung der API. */
    public const WSDL = 'https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl';

    /**
     * Laenger warten wir nicht auf die Flutbremse.
     *
     * Die API nennt nach jedem Aufruf eine Sperrzeit (KasFloodDelay).
     * Innerhalb derer antwortet sie mit einem Fehler. Kurze Sperren sitzen
     * wir aus; wer laenger gesperrt ist, bekommt das gesagt, statt dass
     * eine Verwaltungsseite zwanzig Sekunden haengt.
     */
    public const WARTEN_MAX = 6;

    /* ==================================================================== */
    /*  Zugang                                                              */
    /* ==================================================================== */

    /** Frisch gespeicherte Zugangsdaten, bevor die Konfiguration neu geladen ist. */
    private static ?array $frisch = null;

    /**
     * Fuer den Aufruf direkt nach dem Speichern.
     *
     * Die Konfigurationsdatei wird je Seitenaufruf einmal gelesen. Wer den
     * Zugang speichert und im selben Aufruf prueft, saehe sonst den alten
     * Stand — genau so kam am 7.9. die irrefuehrende Meldung „Kein
     * KAS-Zugang hinterlegt" direkt nach dem Eintragen zustande.
     */
    public static function zugangFrisch(string $login, string $passwort): void
    {
        self::$frisch = ['login' => trim($login), 'passwort' => $passwort];
    }

    /** @return array{login:string,passwort:string} */
    public static function zugang(): array
    {
        if (self::$frisch !== null) { return self::$frisch; }
        $k = (array) Config::get('kas', []);
        return ['login' => trim((string) ($k['login'] ?? '')),
                'passwort' => (string) ($k['passwort'] ?? '')];
    }

    public static function bereit(): bool
    {
        $z = self::zugang();
        return $z['login'] !== '' && $z['passwort'] !== '';
    }

    /** Fehlt auf dem Server etwas, soll es beim Einrichten auffallen — nicht beim ersten Aufruf. */
    public static function voraussetzung(): ?string
    {
        if (!class_exists('SoapClient')) {
            return 'Auf diesem Server fehlt die PHP-Erweiterung „soap“. Im KAS unter '
                 . 'Tools → PHP-Einstellungen einschalten.';
        }
        return null;
    }

    /* ==================================================================== */
    /*  Der Aufruf                                                          */
    /* ==================================================================== */

    /**
     * Ein Aufruf der KAS-API.
     *
     * Die API will alle Angaben als EIN JSON-Text: Login, Passwort, Aktion
     * und die Parameter der Aktion. Zurueck kommt ein verschachteltes
     * Array; das Eigentliche steht unter Response → ReturnInfo.
     *
     * @param array<string,mixed> $params
     * @return array{ok:bool,daten:mixed,text:string}
     */
    public static function rufen(string $aktion, array $params = [], ?array $als = null): array
    {
        $fehlt = self::voraussetzung();
        if ($fehlt !== null) { return ['ok' => false, 'daten' => null, 'text' => $fehlt]; }
        if (!self::bereit()) {
            return ['ok' => false, 'daten' => null,
                    'text' => 'Kein KAS-Zugang hinterlegt. Unter Einstellungen → Zugänge & Schutz eintragen.'];
        }

        /* DIE FLUTBREMSE, BEVOR SIE ZUSCHNAPPT
           ------------------------------------------------------------------
           Nach jedem Aufruf nennt die API eine Sperrzeit. Wir merken sie uns
           und warten von allein — sonst schluege jeder zweite Klick auf
           „Prüfen" mit einem Flood-Fehler fehl, und der saehe aus wie ein
           kaputter Zugang. */
        $ab = (float) self::wert('kas_naechster_ab', '0');
        $warten = $ab - microtime(true);
        if ($warten > self::WARTEN_MAX) {
            return ['ok' => false, 'daten' => null,
                    'text' => 'Die KAS-API bremst gerade (noch ' . (int) ceil($warten)
                            . ' Sekunden). Gleich noch einmal.'];
        }
        if ($warten > 0) { usleep((int) ceil($warten * 1000000)); }

        /* „$als" ist der Unter-Account: Direkt nach dem Anlegen tragen
           Domain und Postfach seinen eigenen Login und sein frisches
           KAS-Passwort — nur in diesem Moment kennen wir es, gespeichert
           wird es nie. */
        $z = $als ?? self::zugang();
        try {
            $client = new SoapClient(self::WSDL, [
                'connection_timeout' => 15,
                'exceptions' => true,
                'cache_wsdl' => defined('WSDL_CACHE_DISK') ? WSDL_CACHE_DISK : 1,
            ]);
            $roh = $client->KasApi(json_encode([
                'kas_login'        => $z['login'],
                'kas_auth_type'    => 'plain',
                'kas_auth_data'    => $z['passwort'],
                'kas_action'       => $aktion,
                'KasRequestParams' => $params ? (object) $params : (object) [],
            ], JSON_UNESCAPED_UNICODE));
        } catch (SoapFault $e) {
            return ['ok' => false, 'daten' => null, 'text' => self::deutsch((string) $e->faultstring)];
        } catch (Throwable $e) {
            return ['ok' => false, 'daten' => null,
                    'text' => 'Die KAS-API war nicht erreichbar: ' . $e->getMessage()];
        }

        $antwort = json_decode(json_encode($roh), true);
        if (!is_array($antwort)) { $antwort = (array) $roh; }

        $delay = (int) ($antwort['Response']['KasFloodDelay'] ?? $antwort['KasFloodDelay'] ?? 0);
        if ($delay > 0) { self::merken('kas_naechster_ab', (string) (microtime(true) + $delay)); }

        return ['ok' => true,
                'daten' => $antwort['Response']['ReturnInfo'] ?? $antwort,
                'text' => ''];
    }

    /**
     * Aus „kas_login_incorrect" wird ein Satz, mit dem jemand etwas anfangen kann.
     */
    private static function deutsch(string $fault): string
    {
        $bekannt = [
            'kas_login_incorrect'  => 'Login oder Passwort stimmen nicht. Wichtig: Es zählt das '
                                    . 'KAS-Passwort (im KAS unter Einstellungen gesetzt), nicht das '
                                    . 'der MembersArea.',
            'kas_password_incorrect' => 'Das Passwort stimmt nicht — oder noch nicht: Ein frisch im '
                                    . 'KAS gesetztes Passwort braucht ein paar Minuten, bis es auf '
                                    . 'den Servern von All-Inkl greift. Kurz warten und noch einmal '
                                    . 'prüfen.',
            'flood_protection'     => 'Zu viele Anfragen kurz hintereinander — die KAS-API bremst. '
                                    . 'Einen Moment warten und noch einmal.',
            'account_incorrect'    => 'Diesen Account kennt der Reseller-Vertrag nicht.',
            'nothing_to_do'        => 'Es gab nichts zu tun.',
        ];
        foreach ($bekannt as $schluessel => $satz) {
            if (stripos($fault, $schluessel) !== false) { return $satz; }
        }
        return 'Die KAS-API meldet: ' . $fault;
    }

    /* ==================================================================== */
    /*  Was wir damit tun                                                   */
    /* ==================================================================== */

    /**
     * Verbindung pruefen — der Knopf unter Einstellungen.
     *
     * @return array{ok:bool,text:string,accounts:list<array<string,mixed>>}
     */
    public static function pruefen(): array
    {
        $erg = self::accounts();
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'accounts' => []]; }
        $n = count($erg['accounts']);
        return ['ok' => true, 'accounts' => $erg['accounts'],
                'text' => 'Der Zugang steht. ' . $n . ' Unter-Account' . ($n === 1 ? '' : 's')
                        . ' im Reseller-Vertrag.'];
    }

    /**
     * Die Unter-Accounts des Resellers.
     *
     * @return array{ok:bool,text:string,accounts:list<array<string,mixed>>}
     */
    public static function accounts(): array
    {
        $erg = self::rufen('get_accounts');
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'accounts' => []]; }

        /* Die API liefert je nach Bestand ein Objekt oder eine Liste. Beides
           wird zu einer Liste — eine Verwaltungsseite soll nicht daran
           scheitern, dass genau ein Account existiert. */
        $roh = $erg['daten'];
        if (is_array($roh) && isset($roh['account_login'])) { $roh = [$roh]; }
        $aus = [];
        foreach ((array) $roh as $a) {
            if (!is_array($a)) { continue; }
            $aus[] = ['login'   => (string) ($a['account_login'] ?? ''),
                      'kommentar' => (string) ($a['account_comment'] ?? ''),
                      'roh'     => $a];
        }
        return ['ok' => true, 'text' => '', 'accounts' => $aus];
    }

    /* ==================================================================== */
    /*  Anlegen (Stufe 2)                                                   */
    /* ==================================================================== */

    /**
     * Ein Zufallspasswort, das jede uebliche Regel besteht.
     *
     * Garantiert Gross, Klein, Ziffer und ein Sonderzeichen aus einem
     * kleinen, unverdaechtigen Satz — FTP-Clients und Formulare stolpern
     * sonst gern ueber exotische Zeichen.
     */
    public static function passwortNeu(): string
    {
        $gross = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $klein = 'abcdefghjkmnpqrstuvwxyz';
        $zahl  = '23456789';
        $sonder = '!-_';
        $alle = $gross . $klein . $zahl . $sonder;
        $aus = $gross[random_int(0, strlen($gross) - 1)]
             . $klein[random_int(0, strlen($klein) - 1)]
             . $zahl[random_int(0, strlen($zahl) - 1)]
             . $sonder[random_int(0, strlen($sonder) - 1)];
        for ($i = 0; $i < 12; $i++) { $aus .= $alle[random_int(0, strlen($alle) - 1)]; }
        return str_shuffle($aus);
    }

    /**
     * Einen Kunden-Account unter dem Reseller anlegen.
     *
     * Den Login (w0…) vergibt All-Inkl; wir geben nur die Passwoerter und
     * den Kommentar mit — der Kommentar ist spaeter das Einzige, woran man
     * in der Accountliste erkennt, welcher Kunde das ist. Kontingente
     * werden bewusst NICHT gesetzt: Die Vorgaben von All-Inkl sind fuer
     * eine Kundenwebsite richtig, und eine geratene Zahl mit falscher
     * Einheit waere schlimmer als keine.
     *
     * @return array{ok:bool,login:string,kas_passwort:string,ftp_passwort:string,text:string}
     */
    public static function accountAnlegen(string $kommentar): array
    {
        $kommentar = mb_substr(trim($kommentar), 0, 80);
        if ($kommentar === '') {
            return ['ok' => false, 'login' => '', 'kas_passwort' => '', 'ftp_passwort' => '',
                    'text' => 'Ohne Kommentar kein Account — er ist das Einzige, woran man '
                            . 'später erkennt, welcher Kunde das ist.'];
        }

        $kasPw = self::passwortNeu();
        $ftpPw = self::passwortNeu();
        $erg = self::rufen('add_account', [
            'account_kas_password' => $kasPw,
            'account_ftp_password' => $ftpPw,
            'account_comment'      => $kommentar,
        ]);
        if (!$erg['ok']) {
            return ['ok' => false, 'login' => '', 'kas_passwort' => '', 'ftp_passwort' => '',
                    'text' => $erg['text']];
        }

        /* Der neue Login steht in der Antwort — wo genau, sagt die Doku
           nicht verbindlich. Also suchen wir ihn, und finden wir ihn
           nicht, ist der Account trotzdem da: Die Accountliste zeigt ihn. */
        $login = self::loginAus($erg['daten']);
        return ['ok' => true, 'login' => $login,
                'kas_passwort' => $kasPw, 'ftp_passwort' => $ftpPw,
                'text' => $login !== ''
                    ? 'Account ' . $login . ' ist angelegt.'
                    : 'Der Account ist angelegt — der Login steht in der Accountliste (Verbindung prüfen).'];
    }

    /**
     * Eine Domain im (Unter-)Account anlegen.
     *
     * Das ist der KAS-Teil — die REGISTRIERUNG der Domain laeuft danach im
     * Domainbestellsystem, von Hand: All-Inkl will es genau in dieser
     * Reihenfolge („Neue Domains sind vor der Bestellung im KAS anzulegen"),
     * und fuer das Bestellsystem gibt es keine Schnittstelle.
     *
     * @param array{login:string,passwort:string}|null $als
     * @return array{ok:bool,text:string}
     */
    public static function domainAnlegen(string $domain, ?array $als = null): array
    {
        $domain = strtolower(trim($domain, " \t\n\r\0\x0B./"));
        $punkt = strpos($domain, '.');
        if ($punkt === false || $punkt < 1) {
            return ['ok' => false, 'text' => 'Das ist keine vollständige Domain: ' . $domain];
        }
        $erg = self::rufen('add_domain', [
            'domain_name' => substr($domain, 0, $punkt),
            'domain_tld'  => substr($domain, $punkt + 1),
            'domain_path' => '/web/',
        ], $als);
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'Domain ' . $domain . ' ist im KAS angelegt.' : $erg['text']];
    }

    /**
     * Ein Postfach im (Unter-)Account anlegen.
     *
     * @param array{login:string,passwort:string}|null $als
     * @return array{ok:bool,text:string}
     */
    public static function postfachAnlegen(string $lokal, string $domain, string $passwort, ?array $als = null): array
    {
        $erg = self::rufen('add_mailaccount', [
            'local_part'    => strtolower(trim($lokal)),
            'domain_part'   => strtolower(trim($domain)),
            'mail_password' => $passwort,
        ], $als);
        return ['ok' => $erg['ok'],
                'text' => $erg['ok'] ? 'Postfach ' . $lokal . '@' . $domain . ' ist angelegt.' : $erg['text']];
    }

    /** Sucht in der API-Antwort nach dem vergebenen Account-Login. */
    private static function loginAus(mixed $daten): string
    {
        if (is_string($daten) && preg_match('/^w[0-9a-f]{7}$/i', trim($daten))) {
            return trim($daten);
        }
        if (is_array($daten)) {
            foreach (['account_login', 'login'] as $k) {
                if (isset($daten[$k]) && is_string($daten[$k])) { return trim($daten[$k]); }
            }
            foreach ($daten as $wert) {
                $g = self::loginAus($wert);
                if ($g !== '') { return $g; }
            }
        }
        return '';
    }

    /* ==================================================================== */
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    private static function wert(string $k, string $ersatz): string
    {
        return (string) self::still(static fn() => Db::wert(
            'SELECT svalue FROM settings WHERE skey = ?', [$k], $ersatz), $ersatz);
    }

    private static function merken(string $k, string $wert): void
    {
        self::still(static fn() => Db::run(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $wert]), null);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
