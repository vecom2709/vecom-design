<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

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
        /* Server-Umgebung zuerst (KAS_LOGIN/KAS_PASSWORD), dann die
           config.local.php -- wer die Geheimnisse lieber als Variable am
           Webspace fuehrt, soll nicht zusaetzlich eine Datei pflegen muessen. */
        $envLogin = trim((string) getenv('KAS_LOGIN'));
        $envPw = (string) getenv('KAS_PASSWORD');
        if ($envLogin !== '' && $envPw !== '') { return ['login' => $envLogin, 'passwort' => $envPw]; }
        $k = (array) Config::get('kas', []);
        return ['login' => trim((string) ($k['login'] ?? '')),
                'passwort' => (string) ($k['passwort'] ?? '')];
    }

    /**
     * Probelauf an? Ohne ausdrueckliches Ausschalten: ja.
     *
     * Die Umgebungsvariable KAS_DRY_RUN gewinnt (true/1 = an, false/0 = aus),
     * sonst die Einstellung kas_probelauf. Fehlt beides, bleibt er an -- ein
     * vergessener Schalter darf nie dazu fuehren, dass beim Anbieter etwas
     * entsteht, das Geld kostet.
     */
    public static function probelauf(): bool
    {
        $env = getenv('KAS_DRY_RUN');
        if ($env !== false && $env !== '') { return !in_array(strtolower(trim($env)), ['0', 'false', 'nein', 'off'], true); }
        return self::wert('kas_probelauf', '1') !== '0';
    }

    /** Veraendert diese Aktion etwas beim Anbieter? */
    public static function schreibt(string $aktion): bool
    {
        return (bool) preg_match('~^(add|update|delete)_~', $aktion);
    }

    /** Parameter fuers Protokoll: jedes Passwort und jeder Schluessel als ***. */
    public static function ohneGeheimnis(array $params): string
    {
        $aus = [];
        foreach ($params as $k => $v) {
            $aus[] = $k . '=' . (preg_match('~pass|auth|key|secret|crt~i', (string) $k) ? '***'
                : (is_scalar($v) ? mb_substr((string) $v, 0, 60) : '…'));
        }
        return '(' . implode(', ', $aus) . ')';
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
        /* DER PROBELAUF -- vor allem anderen, auch vor Zugang und Flutbremse:
           Er soll ohne jede Verbindung zeigen, was geschehen WUERDE. Lesen
           geht durch (es veraendert nichts), jedes add_/update_ bleibt hier. */
        if (self::schreibt($aktion) && self::probelauf()) {
            $was = 'Probelauf: ' . $aktion . ' ' . self::ohneGeheimnis($params) . ' — nicht ausgeführt.';
            self::still(static fn() => Events::protokoll('kas_probelauf', mb_substr($was, 0, 480)), null);
            return ['ok' => false, 'daten' => null, 'probelauf' => true, 'text' => $was];
        }
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
    /**
     * @param array<string,int|string> $grenzen Zusaetzliche Begrenzungen fuer
     *        add_account (z. B. ['max_webspace' => 10240] — Megabyte). Ohne
     *        Angabe gelten die All-Inkl-Vorgaben, wie beim Knopf im Admin.
     */
    public static function accountAnlegen(string $kommentar, array $grenzen = []): array
    {
        $kommentar = mb_substr(trim($kommentar), 0, 80);
        if ($kommentar === '') {
            return ['ok' => false, 'login' => '', 'kas_passwort' => '', 'ftp_passwort' => '',
                    'text' => 'Ohne Kommentar kein Account — er ist das Einzige, woran man '
                            . 'später erkennt, welcher Kunde das ist.'];
        }

        // Nur echte Begrenzungs-Parameter durchlassen — nichts anderes darf
        // hier den Aufruf umbiegen (kein kas_login, kein Passwort von aussen).
        $grenzen = array_filter($grenzen,
            static fn($w, string $k): bool => str_starts_with($k, 'max_') && (int) $w > 0,
            ARRAY_FILTER_USE_BOTH);

        $kasPw = self::passwortNeu();
        $ftpPw = self::passwortNeu();
        $erg = self::rufen('add_account', $grenzen + [
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

    /* ==================================================================== */
    /*  DNS, Speicher, Cronjob (25.09.2026, aus der KAS-Doku)               */
    /*                                                                      */
    /*  Die Doku nennt die Parameter, aber nicht die Felder der Antworten.  */
    /*  Deshalb wird tolerant gelesen -- und was sich nicht sicher als      */
    /*  bestimmter Eintrag erkennen laesst, wird nie geloescht.             */
    /* ==================================================================== */

    /** zone_host verlangt den Punkt am Ende ("meinedomain.de." -- so im Beispiel der Doku). */
    public static function zone(string $domain): string
    {
        return rtrim(strtolower(trim($domain)), '.') . '.';
    }

    /**
     * Die Eintraege einer Zone.
     * @return array{ok:bool,text:string,eintraege:list<array{id:string,name:string,typ:string,daten:string,aux:string,aenderbar:bool}>}
     */
    public static function dnsLesen(string $domain, ?array $als = null): array
    {
        $erg = self::rufen('get_dns_settings', ['zone_host' => self::zone($domain)], $als);
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'eintraege' => []]; }
        $roh = $erg['daten'];
        if (is_array($roh) && isset($roh['record_id'])) { $roh = [$roh]; }
        $aus = [];
        foreach ((array) $roh as $r) {
            if (!is_array($r)) { continue; }
            $aus[] = [
                'id'   => (string) ($r['record_id'] ?? ''),
                'name' => (string) ($r['record_name'] ?? ''),
                'typ'  => strtoupper((string) ($r['record_type'] ?? '')),
                'daten'=> (string) ($r['record_data'] ?? ''),
                'aux'  => (string) ($r['record_aux'] ?? ''),
                /* Nur was ausdruecklich als aenderbar markiert ist, gilt als
                   aenderbar -- fehlt das Feld, fassen wir es nicht an. */
                'aenderbar' => in_array(strtoupper((string) ($r['record_changeable'] ?? 'N')), ['Y', '1', 'TRUE'], true),
            ];
        }
        return ['ok' => true, 'text' => '', 'eintraege' => $aus];
    }

    /** @return array{ok:bool,text:string} "gibt es schon" zaehlt als ok */
    public static function dnsHinzufuegen(string $domain, string $typ, string $name, string $daten, int $aux = 0, ?array $als = null): array
    {
        $erg = self::rufen('add_dns_settings', [
            'zone_host' => self::zone($domain), 'record_type' => strtoupper($typ),
            'record_name' => $name, 'record_data' => $daten, 'record_aux' => (string) $aux,
        ], $als);
        if (!$erg['ok'] && stripos($erg['text'], 'record_already_exists') !== false) {
            return ['ok' => true, 'text' => 'war schon da'];
        }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'eingetragen' : $erg['text']];
    }

    /**
     * Einen Eintrag umschreiben (update_dns_settings). Geloescht wird in
     * dieser Klasse nie -- auch keine DNS-Eintraege (siehe Pruefkette).
     * @return array{ok:bool,text:string}
     */
    public static function dnsAendern(string $id, string $daten, int $aux = 0, ?array $als = null): array
    {
        if (!preg_match('~^\d+$~', $id)) { return ['ok' => false, 'text' => 'Keine gültige Eintragsnummer.']; }
        $erg = self::rufen('update_dns_settings', ['record_id' => $id, 'record_data' => $daten, 'record_aux' => (string) $aux], $als);
        if (!$erg['ok'] && stripos($erg['text'], 'nothing_to_do') !== false) { return ['ok' => true, 'text' => 'unverändert']; }
        return ['ok' => $erg['ok'], 'text' => $erg['text']];
    }

    /**
     * Speicher aller Unter-Accounts in einem Aufruf (get_space, show_subaccounts=Y).
     * @return array{ok:bool,text:string,belegt:array<string,int>} Login => belegte MB
     */
    public static function speicherUnterkonten(): array
    {
        return self::speicherAusAntwort(self::rufen('get_space', ['show_subaccounts' => 'Y']));
    }

    /**
     * Die Antwort von get_space auslegen -- getrennt, damit die Pruefkette sie pruefen kann.
     *
     * Am 25.09.2026 am echten Reseller-Zugang gemessen: Ohne Unterkonten (und bei
     * frischen, noch nie ausgewerteten) antwortet KAS nicht mit einer leeren
     * Liste, sondern mit dem Fehler "no_statistic_data". Das ist kein Defekt,
     * sondern "noch nichts zu zaehlen" -- als Fehler gemeldet, stuende im
     * Serverfeld jeden Tag ein roter Punkt, der nichts bedeutet.
     */
    public static function speicherAusAntwort(array $erg): array
    {
        if (!$erg['ok']) {
            if (stripos((string) $erg['text'], 'no_statistic_data') !== false) {
                return ['ok' => true, 'text' => 'KAS hat noch keine Speicherzahlen (bei neuen Konten erst nach etwa einem Tag).', 'belegt' => []];
            }
            return ['ok' => false, 'text' => $erg['text'], 'belegt' => []];
        }
        $belegt = [];
        $sammeln = static function ($d) use (&$sammeln, &$belegt): void {
            if (!is_array($d)) { return; }
            $login = (string) ($d['account_login'] ?? $d['login'] ?? '');
            if ($login !== '' && preg_match('/^w[0-9a-f]{7}$/i', $login)) {
                /* Die Doku sagt kBytes. Genommen wird das erste Feld, dessen
                   Name nach "belegt" klingt -- sonst lieber gar nichts. */
                foreach ($d as $k => $v) {
                    if (is_numeric($v) && preg_match('~used|belegt|space_used|webspace_used~i', (string) $k)) {
                        $belegt[$login] = (int) round(((float) $v) / 1024);
                        break;
                    }
                }
            }
            foreach ($d as $v) { if (is_array($v)) { $sammeln($v); } }
        };
        $sammeln($erg['daten']);
        return ['ok' => true, 'text' => $belegt ? '' : 'Die Antwort enthielt keine lesbaren Werte.', 'belegt' => $belegt];
    }

    /**
     * Der eingerichtete Speicher je Unter-Account (max_webspace, MB) -- fuer
     * den Abgleich mit dem, was bei Vecom vereinbart ist. Nur lesen.
     * @return array{ok:bool,text:string,grenzen:array<string,int>}
     */
    public static function accountGrenzen(): array
    {
        $erg = self::accounts();
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'grenzen' => []]; }
        return ['ok' => true, 'text' => '', 'grenzen' => self::grenzenAus($erg['accounts'])];
    }

    /** @param list<array{login:string,roh:array}> $accounts */
    public static function grenzenAus(array $accounts): array
    {
        $aus = [];
        foreach ($accounts as $a) {
            $w = $a['roh']['max_webspace'] ?? null;
            /* -1 heisst bei All-Inkl "unbegrenzt" -- das ist keine Zahl, mit
               der man vergleichen koennte, und wird deshalb ausgelassen. */
            if ($a['login'] !== '' && is_numeric($w) && (int) $w > 0) { $aus[$a['login']] = (int) $w; }
        }
        return $aus;
    }

    /**
     * Den Speicher eines Unter-Accounts auf den Vecom-Wert setzen. Nur in
     * diese Richtung: Vecom sagt, was vereinbart ist; der KAS folgt.
     */
    public static function speicherSetzen(string $login, int $mb): array
    {
        if (!preg_match('/^w[0-9a-f]{7}$/i', $login) || $mb < 1) { return ['ok' => false, 'text' => 'Ungültiger Account oder Wert.']; }
        $erg = self::rufen('update_account', ['account_login' => $login, 'max_webspace' => $mb]);
        if (!$erg['ok'] && stripos($erg['text'], 'nothing_to_do') !== false) { return ['ok' => true, 'text' => 'Stand schon so.']; }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'Im KAS auf ' . $mb . ' MB gesetzt.' : $erg['text']];
    }

    /**
     * Die Datenbanken bzw. FTP-Nutzer eines Accounts -- nur die Kommentare,
     * denn an ihnen erkennen wir unsere eigenen wieder (Idempotenz).
     * @return array{ok:bool,text:string,kommentare:list<string>}
     */
    public static function kommentare(string $aktion, ?array $als = null): array
    {
        if (!in_array($aktion, ['get_databases', 'get_ftpusers'], true)) { return ['ok' => false, 'text' => 'Unbekannt.', 'kommentare' => []]; }
        $erg = self::rufen($aktion, [], $als);
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'kommentare' => []]; }
        $aus = [];
        array_walk_recursive($erg['daten'], static function ($v, $k) use (&$aus) {
            if (is_string($v) && in_array((string) $k, ['database_comment', 'ftp_comment'], true)) { $aus[] = $v; }
        });
        return ['ok' => true, 'text' => '', 'kommentare' => $aus];
    }

    /** @return array{ok:bool,text:string,name:string} */
    public static function datenbankAnlegen(string $kommentar, string $passwort, ?array $als = null): array
    {
        $erg = self::rufen('add_database', ['database_password' => $passwort, 'database_comment' => $kommentar], $als);
        $name = '';
        if ($erg['ok'] && is_array($erg['daten'])) {
            array_walk_recursive($erg['daten'], static function ($v) use (&$name) { if ($name === '' && is_string($v) && preg_match('/^d0[0-9a-f]+$/i', $v)) { $name = $v; } });
        } elseif ($erg['ok'] && is_string($erg['daten']) && preg_match('/^d0[0-9a-f]+$/i', $erg['daten'])) { $name = $erg['daten']; }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'Datenbank angelegt.' : $erg['text'], 'name' => $name];
    }

    /** @return array{ok:bool,text:string,login:string} */
    public static function ftpAnlegen(string $kommentar, string $passwort, ?array $als = null): array
    {
        $erg = self::rufen('add_ftpusers', ['ftp_password' => $passwort, 'ftp_comment' => $kommentar, 'ftp_path' => '/'], $als);
        $login = '';
        if ($erg['ok'] && is_array($erg['daten'])) {
            array_walk_recursive($erg['daten'], static function ($v) use (&$login) { if ($login === '' && is_string($v) && preg_match('/^f0[0-9a-f]+$/i', $v)) { $login = $v; } });
        } elseif ($erg['ok'] && is_string($erg['daten']) && preg_match('/^f0[0-9a-f]+$/i', $erg['daten'])) { $login = $erg['daten']; }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'FTP-Nutzer angelegt.' : $erg['text'], 'login' => $login];
    }

    /** @return array{ok:bool,text:string,urls:list<string>} */
    public static function cronjobs(): array
    {
        $erg = self::rufen('get_cronjobs');
        if (!$erg['ok']) { return ['ok' => false, 'text' => $erg['text'], 'urls' => []]; }
        $urls = [];
        array_walk_recursive($erg['daten'], static function ($v, $k) use (&$urls) {
            if (is_string($v) && in_array((string) $k, ['http_url', 'cronjob_url', 'url'], true)) { $urls[] = $v; }
        });
        return ['ok' => true, 'text' => '', 'urls' => $urls];
    }

    /** Eine Weiterleitung (add_mailforward) -- "gibt es schon" zaehlt als ok. */
    public static function weiterleitungAnlegen(string $lokal, string $domain, string $ziel, ?array $als = null): array
    {
        $erg = self::rufen('add_mailforward', ['local_part' => $lokal, 'domain_part' => strtolower($domain), 'target_0' => $ziel], $als);
        if (!$erg['ok'] && stripos($erg['text'], 'mail_forward_exists_as_forward') !== false) { return ['ok' => true, 'text' => 'war schon da']; }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'angelegt' : $erg['text']];
    }

    /**
     * Den KAS-Zugang eines Unter-Accounts sperren oder wieder oeffnen
     * (update_account, kas_access_forbidden). Gesperrt, nicht geloescht:
     * Account, Dateien und Domain bleiben.
     */
    public static function zugangSperren(string $login, bool $sperren = true): array
    {
        if (!preg_match('/^w[0-9a-f]{7}$/i', $login)) { return ['ok' => false, 'text' => 'Kein gültiges KAS-Login.']; }
        $erg = self::rufen('update_account', ['account_login' => $login, 'kas_access_forbidden' => $sperren ? 'Y' : 'N']);
        if (!$erg['ok'] && stripos($erg['text'], 'nothing_to_do') !== false) { return ['ok' => true, 'text' => 'war schon so']; }
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? ($sperren ? 'gesperrt' : 'geöffnet') : $erg['text']];
    }

    /** Den Cronjob der Verwaltung anlegen: alle zehn Minuten, per HTTPS. */
    public static function cronjobAnlegen(string $url, string $kommentar = 'Vecom Verwaltung'): array
    {
        $ohne = preg_replace('~^https?://~i', '', $url) ?? $url;
        $erg = self::rufen('add_cronjob', [
            'protocol' => 'https', 'http_url' => $ohne, 'cronjob_comment' => mb_substr($kommentar, 0, 60),
            'minute' => '*/10', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*',
            'is_active' => 'Y',
        ]);
        return ['ok' => $erg['ok'], 'text' => $erg['ok'] ? 'Der Cronjob ist im KAS eingetragen.' : $erg['text']];
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
