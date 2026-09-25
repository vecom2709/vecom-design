<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Hosting.php';
require_once __DIR__ . '/Domainpruefung.php';

/**
 * Den Umzug einer Domain zu All-Inkl begleiten (Phase 5, 25.09.2026).
 *
 * WAS HIER NICHT PASSIERT
 *
 * Umgezogen wird nicht von hier aus: Fuer das Domainbestellsystem von
 * All-Inkl gibt es keine Schnittstelle, und ein Umzug ohne ausdruecklichen
 * Auftrag des Kunden ist ausgeschlossen (seine Zustimmung haengt als
 * 'domain_transfer' am Hosting-Auftrag). Hier wird vorbereitet, aufbewahrt
 * und nachgesehen -- den Antrag stellt Uwe.
 *
 * DIE VIER DINGE
 *
 *   1. Bestandsaufnahme: Welche DNS-Eintraege hat die Domain HEUTE? Stehen
 *      MX, SPF, DKIM und DMARC nach dem Umzug nicht im KAS, kommen beim
 *      Kunden keine Mails mehr an -- und keiner merkt es sofort.
 *   2. Sperre: Viele Anbieter setzen eine Transfersperre. Solange sie steht,
 *      scheitert der Antrag. Der Kunde muss sie bei seinem Anbieter loesen.
 *   3. Auth-Code: Der Kunde gibt ihn auf seiner Seite ein, nicht per Mail.
 *      Verschluesselt wie die Zugangsdaten, weg mit dem Antrag.
 *   4. Abschluss: Zeigen die Nameserver auf All-Inkl, ist der Umzug durch.
 */
final class Domainumzug
{
    /** Gaengige DKIM-Namen -- gesucht wird nur, was oeffentlich im DNS steht. */
    public const DKIM = ['default', 'google', 'selector1', 'selector2', 'k1', 'k2', 'mail', 'dkim', 's1', 's2', 'smtp'];

    /** Woran man All-Inkl-Nameserver erkennt. */
    public const ZIEL_NS = 'kasserver.com';

    /**
     * Den Umzug anlegen -- beim Anlegen des Hosting-Auftrags mit "umziehen".
     * Zweimal ist harmlos: Es gibt ihn je Auftrag nur einmal.
     */
    public static function anlegen(int $auftragId, ?callable $dns = null, ?callable $sperre = null): ?int
    {
        $a = Db::one("SELECT * FROM hosting_auftraege WHERE id = ? AND domain_aktion = 'transfer'", [$auftragId]);
        if (!$a) { return null; }
        $da = Db::wert('SELECT id FROM domain_umzuege WHERE auftrag_id = ?', [$auftragId], 0);
        if ((int) $da > 0) { return (int) $da; }
        Db::run('INSERT IGNORE INTO domain_umzuege (auftrag_id, customer_id, domain) VALUES (?, ?, ?)',
            [$auftragId, (int) $a['customer_id'], (string) $a['domain']]);
        $id = (int) Db::wert('SELECT id FROM domain_umzuege WHERE auftrag_id = ?', [$auftragId], 0);
        self::nachsehen($id, $dns, $sperre);
        return $id;
    }

    /** Bestandsaufnahme und Sperre neu lesen (auch Uwes Knopf "Neu prüfen"). */
    public static function nachsehen(int $id, ?callable $dns = null, ?callable $sperre = null): void
    {
        $u = Db::one('SELECT * FROM domain_umzuege WHERE id = ?', [$id]);
        if (!$u || in_array((string) $u['stand'], ['beantragt', 'fertig'], true)) { return; }
        $eintraege = self::bestandsaufnahme((string) $u['domain'], $dns);
        $sp = self::sperre((string) $u['domain'], $sperre);
        Db::run('UPDATE domain_umzuege SET dns_json = ?, dns_am = NOW(), sperre = ?, sperre_am = NOW() WHERE id = ?',
            [json_encode($eintraege, JSON_UNESCAPED_UNICODE), $sp, $id]);
    }

    /**
     * Die oeffentlichen DNS-Eintraege, die im KAS stehen muessen.
     *
     * @param callable(string,int):array|null $dns wie dns_get_record -- austauschbar fuer die Pruefkette
     * @return list<array{name:string,typ:string,wert:string}>
     */
    public static function bestandsaufnahme(string $domain, ?callable $dns = null): array
    {
        $dns ??= static fn(string $h, int $t): array => (array) (@dns_get_record($h, $t) ?: []);
        $aus = [];
        $mit = static function (string $name, string $typ, string $wert) use (&$aus): void {
            $wert = trim($wert);
            if ($wert === '') { return; }
            foreach ($aus as $e) { if ($e['name'] === $name && $e['typ'] === $typ && $e['wert'] === $wert) { return; } }
            $aus[] = ['name' => $name, 'typ' => $typ, 'wert' => $wert];
        };
        $txt = static fn(array $r): string => (string) ($r['txt'] ?? implode('', (array) ($r['entries'] ?? [])));

        foreach ([$domain, 'www.' . $domain] as $host) {
            $kurz = $host === $domain ? '@' : 'www';
            foreach ($dns($host, DNS_A) as $r)     { $mit($kurz, 'A', (string) ($r['ip'] ?? '')); }
            foreach ($dns($host, DNS_AAAA) as $r)  { $mit($kurz, 'AAAA', (string) ($r['ipv6'] ?? '')); }
            foreach ($dns($host, DNS_CNAME) as $r) { $mit($kurz, 'CNAME', (string) ($r['target'] ?? '')); }
        }
        foreach ($dns($domain, DNS_MX) as $r) {
            $mit('@', 'MX', (int) ($r['pri'] ?? 10) . ' ' . (string) ($r['target'] ?? ''));
        }
        foreach ($dns($domain, DNS_TXT) as $r) { $mit('@', 'TXT', $txt($r)); }
        foreach ($dns('_dmarc.' . $domain, DNS_TXT) as $r) { $mit('_dmarc', 'TXT', $txt($r)); }
        foreach (self::DKIM as $sel) {
            foreach ($dns($sel . '._domainkey.' . $domain, DNS_TXT) as $r) {
                $wert = $txt($r);
                if (stripos($wert, 'v=DKIM1') !== false || stripos($wert, 'p=') !== false) { $mit($sel . '._domainkey', 'TXT', $wert); }
            }
        }
        foreach ($dns($domain, DNS_NS) as $r) { $mit('@', 'NS', (string) ($r['target'] ?? '')); }
        return $aus;
    }

    /**
     * Steht eine Transfersperre? Aus RDAP (status), sonst aus WHOIS (.it).
     *
     * @param callable(string):array{rdap:?array,whois:?string}|null $quelle austauschbar fuer die Pruefkette
     * @return string gesperrt | frei | unklar
     */
    public static function sperre(string $domain, ?callable $quelle = null): string
    {
        $q = $quelle !== null ? $quelle($domain)
            : ['rdap' => Domainpruefung::rdap($domain), 'whois' => null];
        $rdap = $q['rdap'] ?? null;
        if (is_array($rdap) && isset($rdap['status'])) {
            $st = mb_strtolower(implode(' ', (array) $rdap['status']));
            return str_contains($st, 'transfer prohibited') || str_contains($st, 'transferprohibited') ? 'gesperrt' : 'frei';
        }
        $whois = $quelle !== null ? ($q['whois'] ?? null) : Domainpruefung::whois($domain);
        if (is_string($whois) && preg_match_all('~^\s*(?:status|domain status)\s*:\s*(.+)$~mi', $whois, $m)) {
            $st = mb_strtolower(implode(' ', $m[1]));
            return str_contains($st, 'transferprohibited') || str_contains($st, 'transfer prohibited') ? 'gesperrt' : 'frei';
        }
        return 'unklar';
    }

    /* ================================================================== */
    /*  Auth-Code                                                         */
    /* ================================================================== */

    /**
     * Der Kunde gibt den Code ein -- nur fuer seinen eigenen Umzug.
     * @return string ok | falsch | nicht_dran
     */
    public static function codeSpeichern(int $id, int $kundeId, string $code): string
    {
        $u = Db::one('SELECT * FROM domain_umzuege WHERE id = ? AND customer_id = ?', [$id, $kundeId]);
        if (!$u || !in_array((string) $u['stand'], ['code_fehlt', 'code_da'], true)) { return 'nicht_dran'; }
        $code = trim($code);
        /* Auth-Codes sind 6 bis 64 druckbare Zeichen ohne Leerraum. Mehr
           pruefen koennen wir nicht -- ob er stimmt, weiss nur die Registry. */
        if (!preg_match('~^[\x21-\x7E]{6,64}$~', $code)) { return 'falsch'; }
        $blob = Hosting::versiegeln(['code' => $code]);
        if ($blob === null) { return 'nicht_dran'; }
        Db::run("UPDATE domain_umzuege SET code_blob = ?, code_am = NOW(), stand = 'code_da' WHERE id = ?", [$blob, $id]);
        Events::protokoll('domain_code', 'Auth-Code für ' . $u['domain'] . ' hinterlegt', $kundeId);
        Events::melden('domain_code', 'Auth-Code ist da: ' . $u['domain'], 'hinweis',
            'Der Kunde hat den Auth-Code hinterlegt. In der Kundenakte: DNS-Einträge im KAS eintragen, dann den '
            . 'Umzug im Domainbestellsystem beantragen und „KK-Antrag gestellt“ klicken — danach ist der Code gelöscht.',
            '/kunden/' . $kundeId);
        return 'ok';
    }

    /** Fuer Uwe: den Code lesen, um ihn ins Domainbestellsystem zu kopieren. */
    public static function codeLesen(int $id): ?string
    {
        $u = Db::one('SELECT * FROM domain_umzuege WHERE id = ?', [$id]);
        if (!$u || $u['code_blob'] === null) { return null; }
        $d = Hosting::entsiegeln((string) $u['code_blob']);
        if ($d === null) { return null; }
        Events::protokoll('domain_code_gelesen', 'Auth-Code für ' . $u['domain'] . ' angezeigt', (int) $u['customer_id']);
        return (string) ($d['code'] ?? '');
    }

    /** Uwe hat den Antrag gestellt: Code weg, ab jetzt sieht der Cron nach. */
    public static function beantragt(int $id): bool
    {
        $n = Db::run("UPDATE domain_umzuege SET stand = 'beantragt', beantragt_am = NOW(), code_blob = NULL
                       WHERE id = ? AND stand = 'code_da'", [$id])->rowCount();
        if ($n > 0) {
            $u = Db::one('SELECT * FROM domain_umzuege WHERE id = ?', [$id]);
            Events::protokoll('domain_beantragt', 'Umzug von ' . $u['domain'] . ' beantragt', (int) $u['customer_id']);
        }
        return $n > 0;
    }

    /**
     * Der Cron: Zeigen die Nameserver schon auf All-Inkl? Dann ist der Umzug
     * durch -- Uwe bekommt Bescheid (SSL einschalten), der Kunde eine Mail.
     *
     * @param callable(string,int):array|null $dns wie dns_get_record
     */
    public static function nachsehenAlle(?callable $dns = null, ?callable $senden = null, ?callable $sperre = null): int
    {
        $dns ??= static fn(string $h, int $t): array => (array) (@dns_get_record($h, $t) ?: []);

        /* Eine gesetzte Sperre alle sechs Stunden neu lesen: Hebt der Kunde
           sie bei seinem Anbieter auf, soll seine Seite das auch sagen --
           und nicht bis zu Uwes Knopfdruck "gesperrt" zeigen. */
        foreach (Db::all("SELECT id FROM domain_umzuege WHERE stand IN ('code_fehlt','code_da') AND sperre = 'gesperrt'
                           AND (sperre_am IS NULL OR sperre_am < NOW() - INTERVAL 6 HOUR) LIMIT 10") as $g) {
            try { self::nachsehen((int) $g['id'], $dns, $sperre); } catch (Throwable $e) { /* naechstes Mal */ }
        }

        $n = 0;
        foreach (Db::all("SELECT * FROM domain_umzuege WHERE stand = 'beantragt' ORDER BY id LIMIT 20") as $u) {
            $ns = array_map(static fn($r) => mb_strtolower(rtrim((string) ($r['target'] ?? ''), '.')), $dns((string) $u['domain'], DNS_NS));
            if (!$ns || array_filter($ns, static fn($x) => !str_ends_with($x, self::ZIEL_NS))) { continue; }
            $geaendert = Db::run("UPDATE domain_umzuege SET stand = 'fertig', fertig_am = NOW() WHERE id = ? AND stand = 'beantragt'",
                                 [(int) $u['id']])->rowCount();
            if ($geaendert === 0) { continue; }
            $n++;
            Events::melden('domain_fertig', 'Umgezogen: ' . $u['domain'], 'gut',
                'Die Nameserver zeigen auf All-Inkl. Jetzt im KAS den SSL-Schutz (Let\'s Encrypt) für die Domain einschalten '
                . 'und einmal eine Test-Mail an den Kunden schicken.', '/kunden/' . (int) $u['customer_id']);
            self::kundeInformieren($u, $senden);
        }
        return $n;
    }

    private static function kundeInformieren(array $u, ?callable $senden): void
    {
        try {
            require_once __DIR__ . '/Mail.php';
            require_once __DIR__ . '/Texte.php';
            require_once __DIR__ . '/Kundenzugang.php';
            $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $u['customer_id']]);
            if (!$k || trim((string) $k['email']) === '') { return; }
            $sp = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
            [$b, $t] = Texte::mail('domain_umgezogen', $sp, [
                'name' => (string) $k['name'], 'domain' => (string) $u['domain'],
                'seite' => (string) Kundenzugang::linkFuer((int) $k['id']),
            ]);
            $senden ??= [Mail::class, 'senden'];
            $senden('domain_umgezogen', (string) $k['email'], $b, $t,
                ['customer_id' => (int) $k['id'], 'antwortAn' => Mail::eigeneAdresse()]);
        } catch (Throwable $e) { /* Uwe weiss es trotzdem */ }
    }

    public static function fuerAuftrag(int $auftragId): ?array
    {
        try { return Db::one('SELECT * FROM domain_umzuege WHERE auftrag_id = ?', [$auftragId]) ?: null; }
        catch (Throwable $e) { return null; }
    }
}
