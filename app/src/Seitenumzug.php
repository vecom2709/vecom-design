<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Hosting.php';
require_once __DIR__ . '/Domainpruefung.php';

/**
 * Eine bestehende Website 1:1 zu uns umziehen -- begleitet (Phase 6c, 25.09.2026).
 *
 * DIE REGELN, DIE HIER GELTEN
 *
 *   - Nie ohne Auftrag: Der Kunde stimmt mit Wortlaut zu (Zustimmung
 *     'migration'), bevor er einen Zugang eingeben kann.
 *   - Die alte Seite wird nicht veraendert: Der Verbindungstest liest nur
 *     ein Verzeichnis. Kopieren tut Uwe -- erst die Sicherung, dann der Rest
 *     (die Checkliste steht in dieser Reihenfolge und laesst nichts aus).
 *   - Zugangsdaten nie im Klartext und nie laenger als noetig: verschluesselt
 *     wie die Hosting-Zugangsdaten, geloescht mit "fertig", "abgebrochen"
 *     oder nach LOESCHEN_TAGE.
 *   - Nur echte, oeffentliche Server: Ein Host, der auf eine private
 *     Adresse zeigt, wird nicht angesprochen -- sonst waere der Test ein
 *     Tuersteher ins eigene Netz.
 */
final class Seitenumzug
{
    public const LOESCHEN_TAGE = 30;
    public const FASSUNG = '2026-09-25';

    /** Uwes Checkliste -- in dieser Reihenfolge, die Sicherung zuerst. */
    public const SCHRITTE = [
        'sicherung'  => 'Vollständige Sicherung der alten Seite (Dateien + Datenbank) liegt bei uns',
        'dateien'    => 'Dateien in den KAS-Account kopiert',
        'datenbank'  => 'Datenbank im KAS angelegt und eingespielt, Zugangsdaten der Seite angepasst',
        'test'       => 'Unter der KAS-Vorschauadresse getestet: Seiten, Formulare, Anmeldung',
        'dns'        => 'Domain zeigt auf den KAS (Umzug oder A-Eintrag), SSL aktiv',
    ];

    public static function anfragen(int $kundeId, string $adresse): int
    {
        $name = Domainpruefung::normalisieren($adresse);
        if ($name === null) { throw new RuntimeException('Das ist keine Adresse: ' . $adresse); }
        $da = (int) Db::wert("SELECT id FROM seitenumzuege WHERE customer_id = ? AND stand IN ('angefragt','zugang_da')", [$kundeId], 0);
        if ($da > 0) { return $da; }
        $id = (int) Db::insert('seitenumzuege', ['customer_id' => $kundeId, 'adresse' => $name, 'schritte' => '{}']);
        Events::protokoll('seitenumzug_anfrage', 'Umzug der Website ' . $name . ' angefragt', $kundeId);
        return $id;
    }

    /** Der Wortlaut, dem der Kunde zustimmt -- genau der wird gespeichert. */
    public static function zustimmungsText(string $adresse, string $sprache): string
    {
        require_once __DIR__ . '/Texte.php';
        return strtr(Texte::h(Texte::KUNDE['seitenumzugZustimmung'] ?? [], $sprache),
            ['{adresse}' => $adresse, '{tage}' => (string) self::LOESCHEN_TAGE]);
    }

    /**
     * Der Kunde gibt den Zugang zum alten Webspace ein -- und stimmt damit zu.
     *
     * @param array<string,string> $e ftp_host, ftp_user, ftp_pass, [db_host, db_name, db_user, db_pass]
     * @return string ok | unvollstaendig | host | nicht_dran
     */
    public static function zugangSpeichern(int $id, int $kundeId, array $e, string $sprache): string
    {
        $u = Db::one("SELECT * FROM seitenumzuege WHERE id = ? AND customer_id = ? AND stand IN ('angefragt','zugang_da')", [$id, $kundeId]);
        if (!$u) { return 'nicht_dran'; }
        $z = [];
        foreach (['ftp_host', 'ftp_user', 'ftp_pass', 'db_host', 'db_name', 'db_user', 'db_pass'] as $f) {
            $z[$f] = mb_substr(trim((string) ($e[$f] ?? '')), 0, 190);
        }
        if ($z['ftp_host'] === '' || $z['ftp_user'] === '' || $z['ftp_pass'] === '') { return 'unvollstaendig'; }
        if (!self::hostErlaubt($z['ftp_host'])) { return 'host'; }
        $blob = Hosting::versiegeln($z);
        if ($blob === null) { return 'nicht_dran'; }

        require_once __DIR__ . '/Zustimmung.php';
        Zustimmung::festhalten('migration', $kundeId, self::zustimmungsText((string) $u['adresse'], $sprache), $sprache,
            self::FASSUNG, null, $id);
        Db::run("UPDATE seitenumzuege SET zugang_blob = ?, zugang_am = NOW(), stand = 'zugang_da', test_json = NULL,
                  loeschen_am = NOW() + INTERVAL " . self::LOESCHEN_TAGE . " DAY WHERE id = ?", [$blob, $id]);
        Events::melden('seitenumzug_zugang', 'Zugang zur alten Website ist da: ' . $u['adresse'], 'hinweis',
            'Der Kunde hat dem Umzug zugestimmt und den Zugang hinterlegt. Der Verbindungstest läuft im nächsten Cron; '
            . 'die Checkliste steht in der Kundenakte — die Sicherung zuerst.', '/kunden/' . $kundeId);
        return 'ok';
    }

    /**
     * Nur oeffentliche Server: ein Name, der auf keine private Adresse zeigt.
     * @param callable(string):list<string>|null $aufloesen
     */
    public static function hostErlaubt(string $host, ?callable $aufloesen = null): bool
    {
        $host = mb_strtolower(trim($host));
        $host = preg_replace('~^(s?ftps?://)~', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        if (filter_var($host, FILTER_VALIDATE_IP)) { $ips = [$host]; }
        else {
            if (Domainpruefung::normalisieren($host) !== $host && !str_starts_with($host, 'www.')) { return false; }
            $aufloesen ??= static fn(string $h): array => array_values(array_filter((array) (@gethostbynamel($h) ?: [])));
            $ips = $aufloesen($host);
        }
        if (!$ips) { return false; }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
        }
        return true;
    }

    /**
     * Die Verbindung pruefen -- ein Verzeichnis lesen, sonst nichts.
     *
     * @param callable(array):array|null $ftp austauschbar: bekommt den Zugang, liefert
     *        ['ok'=>bool,'text'=>string,'liste'=>list<string>,'wordpress'=>bool]
     */
    public static function testen(int $id, ?callable $ftp = null): ?array
    {
        $u = Db::one("SELECT * FROM seitenumzuege WHERE id = ? AND stand = 'zugang_da'", [$id]);
        if (!$u || $u['zugang_blob'] === null) { return null; }
        $z = Hosting::entsiegeln((string) $u['zugang_blob']);
        if ($z === null) { return null; }
        $ftp ??= [self::class, 'ftpPruefen'];
        $r = self::hostErlaubt((string) $z['ftp_host'])
            ? $ftp($z) : ['ok' => false, 'text' => 'Der Server zeigt auf keine öffentliche Adresse.', 'liste' => [], 'wordpress' => false];
        $r['sftp_noetig'] = !$r['ok'] && str_contains(mb_strtolower((string) $r['text']), 'sftp');
        Db::run('UPDATE seitenumzuege SET test_json = ?, test_am = NOW() WHERE id = ?',
            [json_encode($r, JSON_UNESCAPED_UNICODE), $id]);
        return $r;
    }

    /** FTP mit TLS, sonst ohne -- nur anmelden und ein Verzeichnis lesen. */
    public static function ftpPruefen(array $z): array
    {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'text' => 'Auf diesem Server fehlt die PHP-Erweiterung „ftp“.', 'liste' => [], 'wordpress' => false];
        }
        $host = preg_replace('~^(s?ftps?://)~i', '', trim((string) $z['ftp_host'])) ?? '';
        $port = 21;
        if (preg_match('~:(\d+)$~', $host, $m)) { $port = (int) $m[1]; $host = substr($host, 0, -strlen($m[0])); }
        if ($port === 22) {
            return ['ok' => false, 'text' => 'Port 22 ist SFTP — das prüfen wir nicht automatisch; Uwe verbindet sich von Hand.', 'liste' => [], 'wordpress' => false];
        }
        $v = function_exists('ftp_ssl_connect') ? @ftp_ssl_connect($host, $port, 8) : false;
        $tls = $v !== false;
        if ($v !== false && !@ftp_login($v, (string) $z['ftp_user'], (string) $z['ftp_pass'])) { @ftp_close($v); $v = false; $tls = false; }
        if ($v === false) {
            $v = @ftp_connect($host, $port, 8);
            if ($v === false) { return ['ok' => false, 'text' => 'Der Server antwortet nicht auf FTP (Port ' . $port . ').', 'liste' => [], 'wordpress' => false]; }
            if (!@ftp_login($v, (string) $z['ftp_user'], (string) $z['ftp_pass'])) {
                @ftp_close($v);
                return ['ok' => false, 'text' => 'Anmeldung abgelehnt — Benutzer oder Passwort stimmen nicht.', 'liste' => [], 'wordpress' => false];
            }
        }
        @ftp_pasv($v, true);
        $liste = array_map('basename', (array) (@ftp_nlist($v, '.') ?: []));
        $wp = in_array('wp-config.php', $liste, true);
        foreach (['public_html', 'httpdocs', 'htdocs', 'www', 'html', 'web'] as $o) {
            if ($wp || !in_array($o, $liste, true)) { continue; }
            $unter = array_map('basename', (array) (@ftp_nlist($v, $o) ?: []));
            if (in_array('wp-config.php', $unter, true)) { $wp = true; }
        }
        @ftp_close($v);
        return ['ok' => true, 'text' => 'Anmeldung klappt' . ($tls ? ' (verschlüsselt)' : ' (unverschlüsselt — Passwort danach ändern lassen)') . '.',
                'liste' => array_slice($liste, 0, 30), 'wordpress' => $wp];
    }

    /** Fuer Uwe: den Zugang einmal zeigen (wird protokolliert). */
    public static function zugangLesen(int $id): ?array
    {
        $u = Db::one('SELECT * FROM seitenumzuege WHERE id = ?', [$id]);
        if (!$u || $u['zugang_blob'] === null) { return null; }
        $z = Hosting::entsiegeln((string) $u['zugang_blob']);
        if ($z !== null) { Events::protokoll('seitenumzug_zugang_gelesen', 'Zugang zu ' . $u['adresse'] . ' angezeigt', (int) $u['customer_id']); }
        return $z;
    }

    /** Einen Schritt der Checkliste abhaken (oder zuruecknehmen). Nur in Reihenfolge. */
    public static function schritt(int $id, string $schritt, bool $erledigt): bool
    {
        if (!isset(self::SCHRITTE[$schritt])) { return false; }
        $u = Db::one("SELECT * FROM seitenumzuege WHERE id = ? AND stand = 'zugang_da'", [$id]);
        if (!$u) { return false; }
        $s = json_decode((string) ($u['schritte'] ?? ''), true) ?: [];
        if ($erledigt) {
            /* Keine Kopie ohne Sicherung: Ein Schritt geht nur, wenn alle
               davor erledigt sind. */
            foreach (array_keys(self::SCHRITTE) as $k) {
                if ($k === $schritt) { break; }
                if (empty($s[$k])) { return false; }
            }
            $s[$schritt] = date('Y-m-d H:i');
        } else {
            unset($s[$schritt]);
        }
        Db::run('UPDATE seitenumzuege SET schritte = ? WHERE id = ?', [json_encode($s), $id]);
        return true;
    }

    /** Abschliessen oder abbrechen -- in beiden Faellen ist der Zugang danach weg. */
    public static function beenden(int $id, bool $fertig): bool
    {
        $u = Db::one("SELECT * FROM seitenumzuege WHERE id = ? AND stand IN ('angefragt','zugang_da')", [$id]);
        if (!$u) { return false; }
        if ($fertig) {
            $s = json_decode((string) ($u['schritte'] ?? ''), true) ?: [];
            foreach (array_keys(self::SCHRITTE) as $k) { if (empty($s[$k])) { return false; } }
        }
        Db::run('UPDATE seitenumzuege SET stand = ?, zugang_blob = NULL, fertig_am = NOW() WHERE id = ?',
            [$fertig ? 'fertig' : 'abgebrochen', $id]);
        Events::protokoll($fertig ? 'seitenumzug_fertig' : 'seitenumzug_abbruch',
            'Website-Umzug ' . $u['adresse'] . ($fertig ? ' abgeschlossen' : ' abgebrochen') . ' — Zugang gelöscht', (int) $u['customer_id']);
        return true;
    }

    /** Der Cron: frisch hinterlegte Zugaenge pruefen, abgelaufene loeschen. */
    public static function cron(?callable $ftp = null): array
    {
        $geprueft = 0;
        foreach (Db::all("SELECT id FROM seitenumzuege WHERE stand = 'zugang_da' AND test_json IS NULL LIMIT 3") as $r) {
            try { if (self::testen((int) $r['id'], $ftp) !== null) { $geprueft++; } } catch (Throwable $e) { /* naechster Lauf */ }
        }
        $weg = Db::run("UPDATE seitenumzuege SET zugang_blob = NULL
                         WHERE zugang_blob IS NOT NULL AND loeschen_am IS NOT NULL AND loeschen_am < NOW()")->rowCount();
        return ['geprueft' => $geprueft, 'geloescht' => $weg];
    }

    public static function fuerKunde(int $kundeId): ?array
    {
        try { return Db::one('SELECT * FROM seitenumzuege WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]) ?: null; }
        catch (Throwable $e) { return null; }
    }
}
