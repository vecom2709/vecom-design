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
 * Sie liest: Verbindung pruefen, Accounts auflisten. Sie legt NICHTS an.
 * Ein Skript, das ungetestet Accounts, Domains oder Datenbanken bei einem
 * Hoster erzeugt, ist keine Automatisierung, sondern ein Risiko mit
 * Vertragsbindung. Das Anlegen kommt als eigene Stufe, sobald der Zugang
 * steht und ein erster Account von Hand danebengelegt wurde, an dem sich
 * die Parameter pruefen lassen.
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

    /** @return array{login:string,passwort:string} */
    public static function zugang(): array
    {
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
    public static function rufen(string $aktion, array $params = []): array
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

        $z = self::zugang();
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
