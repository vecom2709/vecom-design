<?php
declare(strict_types=1);

/**
 * Ueberwachung der veroeffentlichten Websites.
 *
 * Einmal je Lauf wird jede Seite mit eingeschalteter Ueberwachung
 * aufgerufen: Antwortet sie, wie schnell, und wie lange gilt ihr
 * Zertifikat noch. Jede Pruefung wird festgehalten, damit sich hinterher
 * nachvollziehen laesst, wann etwas war.
 *
 * Zwei Regeln, damit das im Alltag brauchbar bleibt:
 *
 *   1. Gemeldet wird nur der Wechsel. Eine Seite, die seit drei Tagen
 *      offline ist, meldet sich einmal — nicht alle zehn Minuten. Sonst
 *      liest niemand mehr die Meldungen.
 *   2. Ein einzelner Fehlversuch macht noch keine Stoerung. Erst wenn zwei
 *      Pruefungen hintereinander fehlschlagen, gilt die Seite als offline.
 *      Ein kurzer Aussetzer beim Anbieter soll nicht sofort Alarm ausloesen.
 */
final class Monitoring
{
    /** So viele Fehlversuche hintereinander, bevor eine Seite als gestoert gilt. */
    public const FEHLVERSUCHE_BIS_ALARM = 2;

    /** Ab wie vielen Tagen Restlaufzeit vor dem Zertifikatsablauf gewarnt wird. */
    public const SSL_WARNUNG_TAGE = 14;

    /** Antwortzeit, ab der eine Seite als langsam gilt (Millisekunden). */
    public const LANGSAM_AB_MS = 3000;

    private const ZEITLIMIT = 15;

    /**
     * Prueft alle ueberwachten Websites.
     *
     * @return array{geprueft:int,ok:int,gestoert:int,gewechselt:int}
     */
    public static function alle(): array
    {
        // Beispieldaten bleiben aussen vor: Ihre Domains gibt es nicht, und
        // ein Fehlalarm fuer eine erfundene Seite ist schlimmer als gar keine
        // Meldung. Ihr Verlauf steht ja schon fertig in der Tabelle.
        $seiten = self::zuPruefen();
        $bilanz = ['geprueft' => 0, 'ok' => 0, 'gestoert' => 0, 'gewechselt' => 0];
        foreach ($seiten as $s) {
            $e = self::eine((int) $s['id']);
            if ($e === null) { continue; }
            $bilanz['geprueft']++;
            if ($e['ok']) { $bilanz['ok']++; } else { $bilanz['gestoert']++; }
            if ($e['gewechselt']) { $bilanz['gewechselt']++; }
        }
        return $bilanz;
    }

    /** Welche Seiten der Lauf anfasst — ohne Beispieldaten. */
    private static function zuPruefen(): array
    {
        $sql = "SELECT * FROM websites
                WHERE monitoring = 1 AND status NOT IN ('nicht_veroeffentlicht')%s
                ORDER BY id";
        try {
            return Db::all(sprintf($sql, ' AND demo = 0'));
        } catch (Throwable $e) {
            // Spalte noch nicht eingespielt — dann eben alle.
            return Db::all(sprintf($sql, ''));
        }
    }

    /**
     * Prueft eine einzelne Website und zieht ihren Zustand nach.
     *
     * @return array{ok:bool,status:string,gewechselt:bool,pruefung:array}|null
     */
    public static function eine(int $websiteId): ?array
    {
        $w = Db::one('SELECT * FROM websites WHERE id = ?', [$websiteId]);
        if (!$w) { return null; }

        $pruefung = self::abrufen((string) $w['url']);
        Db::insert('website_checks', [
            'website_id'     => $websiteId,
            'http_status'    => $pruefung['status'],
            'response_ms'    => $pruefung['ms'],
            'ssl_valid'      => $pruefung['ssl_gueltig'],
            'ssl_expires_at' => $pruefung['ssl_bis'],
            'ok'             => $pruefung['ok'] ? 1 : 0,
            'error'          => $pruefung['fehler'],
        ]);

        $alt = (string) $w['status'];
        $neu = self::zustandBestimmen($w, $pruefung);

        $felder = [
            'last_status' => $pruefung['status'],
            'last_ms'     => $pruefung['ms'],
        ];
        if ($pruefung['ssl_bis'] !== null) { $felder['ssl_expires_at'] = $pruefung['ssl_bis']; }
        if ($pruefung['ok']) { $felder['last_ok_at'] = date('Y-m-d H:i:s'); }
        else                 { $felder['last_fail_at'] = date('Y-m-d H:i:s'); }
        if ($neu !== $alt) { $felder['status'] = $neu; }
        Db::update('websites', $websiteId, $felder);

        $gewechselt = $neu !== $alt;
        if ($gewechselt) { self::melden($w, $alt, $neu, $pruefung); }

        return ['ok' => $pruefung['ok'], 'status' => $neu, 'gewechselt' => $gewechselt, 'pruefung' => $pruefung];
    }

    /**
     * Ruft eine Adresse ab. Gibt nie eine Ausnahme weiter — ein unerreichbarer
     * Server ist ein Ergebnis, kein Programmfehler.
     *
     * @return array{ok:bool,status:?int,ms:?int,ssl_gueltig:?int,ssl_bis:?string,fehler:?string}
     */
    public static function abrufen(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'status' => null, 'ms' => null, 'ssl_gueltig' => null,
                    'ssl_bis' => null, 'fehler' => 'Keine gültige Adresse hinterlegt.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::ZEITLIMIT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CERTINFO       => true,
            CURLOPT_USERAGENT      => 'Vecom-Design-Monitoring/1.0 (+https://vecom-design.it)',
            // Nur lesen, nichts veraendern — und der Kopf reicht meistens.
            // Manche Server mögen HEAD aber nicht, deshalb ein GET mit
            // begrenzter Menge statt CURLOPT_NOBODY.
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ]);
        $anfang = microtime(true);
        $inhalt = curl_exec($ch);
        $ms     = (int) round((microtime(true) - $anfang) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz   = curl_error($ch);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        $sslBis = null;
        $sslOk  = null;
        if (str_starts_with(strtolower($url), 'https://')) {
            $sslOk = $netz === '' ? 1 : 0;
            foreach (($info['certinfo'] ?? []) as $zert) {
                if (!empty($zert['Expire date'])) {
                    $t = strtotime((string) $zert['Expire date']);
                    if ($t !== false) { $sslBis = date('Y-m-d', $t); }
                    break;   // das erste ist das der Seite selbst
                }
            }
        }

        if ($netz !== '') {
            return ['ok' => false, 'status' => $status ?: null, 'ms' => $ms,
                    'ssl_gueltig' => $sslOk, 'ssl_bis' => $sslBis,
                    'fehler' => mb_substr(self::netzfehlerDeutsch($netz), 0, 255)];
        }

        $ok = $status >= 200 && $status < 400;
        return ['ok' => $ok, 'status' => $status, 'ms' => $ms,
                'ssl_gueltig' => $sslOk, 'ssl_bis' => $sslBis,
                'fehler' => $ok ? null : "Der Server antwortete mit $status."];
    }

    /** Die haeufigsten curl-Meldungen in verstaendlichen Worten. */
    private static function netzfehlerDeutsch(string $roh): string
    {
        $k = strtolower($roh);
        return match (true) {
            str_contains($k, 'could not resolve host') => 'Die Domain ist nicht auflösbar — steht der DNS-Eintrag noch?',
            str_contains($k, 'timed out')              => 'Der Server hat nicht rechtzeitig geantwortet.',
            str_contains($k, 'connection refused')     => 'Der Server nimmt keine Verbindungen an.',
            str_contains($k, 'certificate')            => 'Mit dem SSL-Zertifikat stimmt etwas nicht: ' . $roh,
            str_contains($k, 'ssl')                    => 'SSL-Fehler: ' . $roh,
            default                                    => $roh,
        };
    }

    /**
     * Welcher Zustand folgt aus dieser Pruefung? Ein einzelner Fehlversuch
     * aendert noch nichts — erst mehrere hintereinander.
     */
    private static function zustandBestimmen(array $w, array $pruefung): string
    {
        // Eine Domain, die sich nicht aufloesen laesst, ist kein Serverfehler.
        if ($pruefung['fehler'] !== null && str_contains($pruefung['fehler'], 'nicht auflösbar')) {
            return self::genugFehlversuche((int) $w['id']) ? 'domain_problem' : (string) $w['status'];
        }
        if ($pruefung['ssl_gueltig'] === 0) {
            return self::genugFehlversuche((int) $w['id']) ? 'ssl_problem' : (string) $w['status'];
        }
        if (!$pruefung['ok']) {
            if (!self::genugFehlversuche((int) $w['id'])) { return (string) $w['status']; }
            // Antwortet der Server ueberhaupt nicht, ist die Seite offline.
            // Antwortet er mit einem Fehlercode, laeuft er — er hat ein Problem.
            return $pruefung['status'] === null ? 'offline' : 'fehler';
        }

        // Erreichbar. Laeuft das Zertifikat bald ab, ist das eine Warnung wert,
        // aber die Seite bleibt online — sie funktioniert ja.
        return 'online';
    }

    /** Sind die letzten Pruefungen alle fehlgeschlagen? */
    private static function genugFehlversuche(int $websiteId): bool
    {
        $letzte = Db::all(
            'SELECT ok FROM website_checks WHERE website_id = ? ORDER BY id DESC LIMIT ?',
            [$websiteId, self::FEHLVERSUCHE_BIS_ALARM]
        );
        if (count($letzte) < self::FEHLVERSUCHE_BIS_ALARM) { return false; }
        foreach ($letzte as $l) { if ((int) $l['ok'] === 1) { return false; } }
        return true;
    }

    /** Meldung an Uwe — nur beim Wechsel, nie bei jedem Lauf. */
    private static function melden(array $w, string $alt, string $neu, array $pruefung): void
    {
        $domain = (string) $w['domain'];
        $link   = '/monitoring';
        $projekt = $w['project_id'] !== null ? (int) $w['project_id'] : null;

        if ($neu === 'online') {
            Events::protokoll('website_online', "Website wieder erreichbar: $domain",
                (int) $w['customer_id'], null, $projekt);
            Events::melden('website_online', 'Website wieder erreichbar', 'gut',
                $domain . ' antwortet wieder (' . (int) $pruefung['ms'] . ' ms).', $link);
            return;
        }

        [$titel, $text] = match ($neu) {
            'offline'        => ['Website nicht erreichbar', "$domain antwortet nicht. " . ($pruefung['fehler'] ?? '')],
            'fehler'         => ['Website meldet einen Fehler', "$domain antwortet mit " . (int) $pruefung['status'] . '.'],
            'ssl_problem'    => ['SSL-Problem', "Beim Zertifikat von $domain stimmt etwas nicht. " . ($pruefung['fehler'] ?? '')],
            'domain_problem' => ['Domain nicht auflösbar', "$domain ist im DNS nicht zu finden."],
            default          => ['Website-Status geändert', "$domain: $alt → $neu"],
        };

        Events::protokoll('website_stoerung', "$titel: $domain", (int) $w['customer_id'], null, $projekt);
        Events::melden('website_stoerung', $titel, 'schlecht', trim($text), $link);
    }

    /**
     * Zertifikate, die bald ablaufen. Einmal am Tag gemeldet, nicht bei
     * jedem Lauf — dafuer merkt sich die Einstellung den letzten Hinweis.
     *
     * @return int Anzahl gemeldeter Seiten
     */
    public static function sslWarnungen(): int
    {
        $grenze = date('Y-m-d', strtotime('+' . self::SSL_WARNUNG_TAGE . ' days'));
        $sql = "SELECT * FROM websites
                WHERE monitoring = 1 AND ssl_expires_at IS NOT NULL AND ssl_expires_at <= ?
                  AND status NOT IN ('nicht_veroeffentlicht')%s";
        try { $faellig = Db::all(sprintf($sql, ' AND demo = 0'), [$grenze]); }
        catch (Throwable $e) { $faellig = Db::all(sprintf($sql, ''), [$grenze]); }
        $gezaehlt = 0;
        foreach ($faellig as $w) {
            $merker = 'ssl_hinweis_' . (int) $w['id'];
            $zuletzt = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$merker], '');
            if ($zuletzt === date('Y-m-d')) { continue; }      // heute schon gesagt
            $tage = (int) floor((strtotime((string) $w['ssl_expires_at']) - time()) / 86400);
            Events::melden('ssl_laeuft_ab', 'SSL-Zertifikat läuft bald ab',
                $tage <= 3 ? 'schlecht' : 'warnung',
                $w['domain'] . ' — noch ' . max(0, $tage) . ' Tage (bis ' . Fmt::datum((string) $w['ssl_expires_at']) . ').',
                '/monitoring');
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$merker, date('Y-m-d')]);
            $gezaehlt++;
        }
        return $gezaehlt;
    }

    /** Alte Pruefungen wegraeumen, damit die Tabelle nicht endlos waechst. */
    public static function aufraeumen(int $tage = 90): int
    {
        return Db::run('DELETE FROM website_checks WHERE checked_at < ?',
            [date('Y-m-d H:i:s', strtotime("-$tage days"))])->rowCount();
    }

    /** Verfuegbarkeit einer Seite in Prozent, ueber die letzten Tage. */
    public static function verfuegbarkeit(int $websiteId, int $tage = 30): ?float
    {
        $seit = date('Y-m-d H:i:s', strtotime("-$tage days"));
        $gesamt = (int) Db::wert('SELECT COUNT(*) FROM website_checks WHERE website_id = ? AND checked_at >= ?',
            [$websiteId, $seit]);
        if ($gesamt === 0) { return null; }
        $gut = (int) Db::wert('SELECT COUNT(*) FROM website_checks WHERE website_id = ? AND checked_at >= ? AND ok = 1',
            [$websiteId, $seit]);
        return round($gut / $gesamt * 100, 2);
    }
}
