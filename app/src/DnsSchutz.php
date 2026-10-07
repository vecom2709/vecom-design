<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Domainumzug.php';

/**
 * DNS-Schutz (AI Office Stufe 5, 07.10.2026, Uwe: „Vorher + täglich, MX/NS-Alarm“).
 *
 * WARUM
 * Die Systemanalyse (Befund 6) fand: Der Hosting-Ablauf schrieb DNS-Einträge in die
 * KAS-Zone, ohne irgendwo festzuhalten, wie es vorher aussah. Geht dabei ein MX
 * verloren, kommt beim Kunden keine Post mehr an — und keiner merkt es sofort, weil
 * Absender ihre Fehlermeldung bekommen, nicht der Kunde.
 *
 * WAS ES TUT
 *  1. Vor jeder Änderung ein Schnappschuss (öffentliches DNS, dazu die KAS-Zone, wenn
 *     der Zugang des Unter-Accounts noch da ist) — ohne Vorher-Stand keine Änderung.
 *  2. Danach ein zweiter, mit der Liste, was umgeschrieben (alter Wert dabei) und was
 *     hinzugefügt wurde.
 *  3. Einmal am Tag das öffentliche DNS aller Kundendomains. Ändern sich MX, NS, SPF
 *     oder DMARC: Meldung und Zuruf an Uwe.
 *
 * ZURÜCKROLLEN
 * Umgeschriebene Einträge gehen per update_dns_settings auf den alten Wert zurück —
 * nur auf Uwes Klick. Hinzugefügte kann die Verwaltung nicht entfernen: delete_* steht
 * mit Absicht nie in Kas::SCHREIBEN_ERLAUBT. Die stehen als Liste da, zum Löschen im KAS.
 * Ehrlich statt bequem: Ein halber Rückweg, der als ganzer verkauft wird, ist schlimmer
 * als keiner.
 *
 * NUR LESEN, AUSSER BEIM ZURÜCKROLLEN. Der tägliche Lauf schreibt niemandem außer Uwe.
 */
final class DnsSchutz
{
    public const ANLAESSE = ['vorher', 'nachher', 'taeglich', 'hand', 'exit'];

    /** Höchstens so viele Domains je Tageslauf (je Domain rund 20 DNS-Abfragen). */
    public const JE_LAUF = 120;

    /** Ein unveränderter Stand wird höchstens so oft neu abgelegt (Tage) — damit die Liste zeigt, dass geprüft wurde. */
    public const RUHIG_TAGE = 7;

    /** Prüfnaht für die Kette: ersetzt den Zuruf an Uwe. */
    public static $zuruf = null;

    /* ------------------------------------------------------------ Schnappschuss */

    /**
     * Den Stand einer Domain ablegen.
     *
     * @param callable(string,int):array|null $dns wie dns_get_record
     * @param callable(string):array|null $kasLesen liefert wie Kas::dnsLesen ['ok','text','eintraege']
     * @param list<array{name:string,typ:string,wert:string}>|null $oeffentlich schon gelesen (spart die Abfragen)
     * @return int ID des Schnappschusses
     */
    public static function schnappschuss(string $domain, string $anlass, ?int $kundeId = null, ?string $bezug = null,
                                         ?callable $dns = null, ?callable $kasLesen = null, ?array $oeffentlich = null,
                                         ?int $vorherId = null, ?array $aenderungen = null): int
    {
        $domain = self::domain($domain);
        if ($domain === '') { throw new InvalidArgumentException('Keine gültige Domain.'); }
        if (!in_array($anlass, self::ANLAESSE, true)) { throw new InvalidArgumentException('Unbekannter Anlass.'); }
        $oeffentlich ??= Domainumzug::bestandsaufnahme($domain, $dns);
        $kas = null;
        $kasOk = false;
        if ($kasLesen !== null) {
            try {
                $k = $kasLesen($domain);
                $kasOk = !empty($k['ok']);
                $kas = $kasOk ? array_map(static fn($e) => ['id' => (string) ($e['id'] ?? ''), 'name' => (string) ($e['name'] ?? ''),
                    'typ' => strtoupper((string) ($e['typ'] ?? '')), 'daten' => (string) ($e['daten'] ?? ''), 'aux' => (string) ($e['aux'] ?? ''),
                    'aenderbar' => !empty($e['aenderbar'])], (array) ($k['eintraege'] ?? [])) : ['fehler' => (string) ($k['text'] ?? '')];
            } catch (Throwable $e) {
                $kas = ['fehler' => $e->getMessage()];
            }
        }
        $finger = self::fingerabdruck($oeffentlich, $kasOk ? $kas : null);
        return Db::insert('dns_schnappschuesse', [
            'domain' => $domain, 'customer_id' => $kundeId, 'anlass' => $anlass, 'bezug' => $bezug !== null ? mb_substr($bezug, 0, 60) : null,
            'oeffentlich_json' => json_encode(array_values($oeffentlich), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'kas_json' => $kas !== null ? json_encode($kas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'kas_ok' => $kasOk ? 1 : 0, 'fingerabdruck' => $finger, 'vorher_id' => $vorherId,
            'aenderungen_json' => $aenderungen !== null ? json_encode($aenderungen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    /** Gleicher Inhalt = gleicher Abdruck, egal in welcher Reihenfolge der Resolver antwortet. */
    public static function fingerabdruck(array $oeffentlich, ?array $kas): string
    {
        $o = array_map(static fn($e) => strtoupper((string) $e['typ']) . ' ' . (string) $e['name'] . ' ' . self::wert((string) $e['wert']), $oeffentlich);
        sort($o);
        $k = [];
        foreach ((array) $kas as $e) {
            if (is_array($e)) { $k[] = $e['typ'] . ' ' . $e['name'] . ' ' . $e['daten'] . ' ' . $e['aux']; }
        }
        sort($k);
        return hash('sha256', implode("\n", $o) . "\n--\n" . implode("\n", $k));
    }

    /** Vergleichbare Schreibweise: ohne Punkt am Ende, klein bei Hostnamen. */
    private static function wert(string $w): string
    {
        $w = trim($w);
        return preg_match('~^(\d+\s+)?[a-z0-9.-]+\.?$~i', $w) ? rtrim(strtolower($w), '.') : $w;
    }

    public static function domain(string $roh): string
    {
        $d = strtolower(trim($roh));
        $d = preg_replace('~^https?://~', '', $d) ?? '';
        $d = rtrim(explode('/', $d)[0], '.');
        if (str_starts_with($d, 'www.')) { $d = substr($d, 4); }
        return preg_match('~^(?=.{3,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$~', $d) ? $d : '';
    }

    /* ------------------------------------------------------------ Lesen */

    public static function laden(int $id): ?array
    {
        $s = Db::one('SELECT * FROM dns_schnappschuesse WHERE id = ?', [$id]);
        return $s ? self::auspacken($s) : null;
    }

    /** @return list<array> neueste zuerst */
    public static function fuerDomain(string $domain, int $anzahl = 20): array
    {
        $domain = self::domain($domain);
        return array_map([self::class, 'auspacken'], Db::all('SELECT * FROM dns_schnappschuesse WHERE domain = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $anzahl)), [$domain]));
    }

    private static function auspacken(array $s): array
    {
        $s['oeffentlich'] = json_decode((string) ($s['oeffentlich_json'] ?? ''), true) ?: [];
        $s['kas'] = json_decode((string) ($s['kas_json'] ?? ''), true);
        $s['aenderungen'] = json_decode((string) ($s['aenderungen_json'] ?? ''), true);
        return $s;
    }

    /**
     * Die Einträge, an denen die Post und die Zuständigkeit hängen: MX, NS, SPF, DMARC.
     * @return list<string> „TYP name wert“
     */
    public static function kritisch(array $eintraege): array
    {
        $aus = [];
        foreach ($eintraege as $e) {
            $typ = strtoupper((string) ($e['typ'] ?? ''));
            $name = (string) ($e['name'] ?? '');
            $wert = self::wert((string) ($e['wert'] ?? ''));
            $spf = $typ === 'TXT' && stripos($wert, 'v=spf1') === 0;
            $dmarc = $typ === 'TXT' && str_starts_with($name, '_dmarc');
            if (in_array($typ, ['MX', 'NS'], true) || $spf || $dmarc) { $aus[] = ($spf ? 'SPF' : ($dmarc ? 'DMARC' : $typ)) . ' ' . $name . ' ' . $wert; }
        }
        $aus = array_values(array_unique($aus));
        sort($aus);
        return $aus;
    }

    /**
     * Was ist neu, was ist weg — Zeile für Zeile.
     * @return array{neu:list<string>, weg:list<string>}
     */
    public static function vergleich(array $alt, array $neu): array
    {
        $a = array_map(static fn($e) => strtoupper((string) $e['typ']) . ' ' . (string) $e['name'] . ' ' . self::wert((string) $e['wert']), $alt);
        $n = array_map(static fn($e) => strtoupper((string) $e['typ']) . ' ' . (string) $e['name'] . ' ' . self::wert((string) $e['wert']), $neu);
        return ['neu' => array_values(array_diff(array_unique($n), $a)), 'weg' => array_values(array_diff(array_unique($a), $n))];
    }

    /**
     * Je Kundendomain der letzte Stand und ob es eine offene Änderung gibt (Seite „Umzüge & DNS“).
     * @return list<array{domain:string, kunde:int, letzter:?array, nachher_offen:int}>
     */
    public static function uebersicht(): array
    {
        $aus = [];
        foreach (self::domains() as $d) {
            $l = Db::one('SELECT id, anlass, created_at FROM dns_schnappschuesse WHERE domain = ? ORDER BY id DESC LIMIT 1', [$d['domain']]);
            $offen = (int) Db::wert("SELECT COUNT(*) FROM dns_schnappschuesse WHERE domain = ? AND anlass = 'nachher' AND zurueck_am IS NULL", [$d['domain']], 0);
            $aus[] = $d + ['letzter' => $l, 'nachher_offen' => $offen];
        }
        return $aus;
    }

    /* ------------------------------------------------------------ Täglich */

    /**
     * Die Kundendomains: was bei uns gehostet ist und jede überwachte Website.
     * @return list<array{domain:string, kunde:int}>
     */
    public static function domains(): array
    {
        $aus = [];
        $rein = static function (string $roh, int $kunde) use (&$aus): void {
            $d = self::domain($roh);
            if ($d !== '' && !isset($aus[$d])) { $aus[$d] = ['domain' => $d, 'kunde' => $kunde]; }
        };
        foreach (Db::all("SELECT h.domain, h.customer_id FROM hosting_auftraege h JOIN customers c ON c.id = h.customer_id
                           WHERE h.status IN ('angelegt','aktiv','in_arbeit') AND h.demo = 0 AND c.demo = 0 AND h.gesperrt_am IS NULL ORDER BY h.id") as $r) {
            $rein((string) $r['domain'], (int) $r['customer_id']);
        }
        try {
            foreach (Db::all("SELECT w.domain, w.url, w.customer_id FROM websites w JOIN customers c ON c.id = w.customer_id
                               WHERE w.monitoring = 1 AND c.demo = 0 ORDER BY w.id") as $r) {
                $rein((string) ($r['domain'] ?: $r['url']), (int) $r['customer_id']);
            }
        } catch (Throwable $e) { }
        return array_values($aus);
    }

    /**
     * Einmal am Tag: öffentliches DNS aller Kundendomains, Alarm bei MX/NS/SPF/DMARC.
     *
     * Leer heißt nicht „alles gelöscht“: Liefert der Resolver gar nichts, ist der Stand
     * „nicht lesbar“ — sonst schlüge jeder Netzschluckauf als Alarm durch.
     *
     * @param callable(string,int):array|null $dns
     * @return array{geprueft:int, geaendert:int, unlesbar:int}
     */
    public static function taeglich(?callable $dns = null): array
    {
        $n = ['geprueft' => 0, 'geaendert' => 0, 'unlesbar' => 0];
        foreach (array_slice(self::domains(), 0, self::JE_LAUF) as $d) {
            $jetzt = Domainumzug::bestandsaufnahme($d['domain'], $dns);
            $n['geprueft']++;
            if (!$jetzt) { $n['unlesbar']++; continue; }
            $letzter = Db::one("SELECT * FROM dns_schnappschuesse WHERE domain = ? AND anlass IN ('taeglich','hand','nachher','exit')
                                 ORDER BY id DESC LIMIT 1", [$d['domain']]);
            $alt = $letzter ? (json_decode((string) $letzter['oeffentlich_json'], true) ?: []) : null;
            $abdruck = self::fingerabdruck($jetzt, null);
            $gleich = $letzter !== null && hash_equals(self::fingerabdruck($alt ?? [], null), $abdruck);
            if ($gleich && strtotime((string) $letzter['created_at']) > time() - self::RUHIG_TAGE * 86400) { continue; }
            self::schnappschuss($d['domain'], 'taeglich', $d['kunde'], 'taeglich', $dns, null, $jetzt);
            if ($alt === null || $gleich) { continue; }
            $v = self::vergleich(self::kritischeZeilen($alt), self::kritischeZeilen($jetzt));
            if (!$v['neu'] && !$v['weg']) { continue; }
            $n['geaendert']++;
            self::alarm($d['domain'], $d['kunde'], $v);
        }
        return $n;
    }

    /** Nur die Einträge, die für den Alarm zählen — in der Form, die vergleich() liest. */
    private static function kritischeZeilen(array $eintraege): array
    {
        return array_values(array_filter($eintraege, static function ($e) {
            $typ = strtoupper((string) ($e['typ'] ?? ''));
            $wert = (string) ($e['wert'] ?? '');
            return in_array($typ, ['MX', 'NS'], true) || ($typ === 'TXT' && (stripos($wert, 'v=spf1') === 0 || str_starts_with((string) ($e['name'] ?? ''), '_dmarc')));
        }));
    }

    /** Meldung mit allen Zeilen; Zuruf ohne Domain und ohne Namen (Regel des Zurufs). */
    private static function alarm(string $domain, int $kundeId, array $v): void
    {
        $mxWeg = (bool) array_filter($v['weg'], static fn($z) => str_starts_with($z, 'MX ') || str_starts_with($z, 'NS '));
        $text = ($v['weg'] ? 'Weg: ' . implode(' · ', $v['weg']) . '. ' : '') . ($v['neu'] ? 'Neu: ' . implode(' · ', $v['neu']) . '. ' : '')
              . 'War das ein geplanter Umzug? Sonst den Vorher-Stand unter Bauen → Umzüge & DNS ansehen.';
        Events::melden('dns_geaendert', 'DNS geändert: ' . $domain . ($mxWeg ? ' (MX/NS betroffen)' : ''), $mxWeg ? 'schlecht' : 'warnung', $text,
            '/umzuege?dns=' . rawurlencode($domain));
        Events::protokoll('dns_geaendert', 'DNS von ' . $domain . ' hat sich geändert', $kundeId > 0 ? $kundeId : null);
        $satz = 'Umzüge & DNS: Bei einer Kundendomain haben sich ' . ($mxWeg ? 'MX- oder Nameserver-Einträge' : 'Mail-Einträge (SPF/DMARC)')
              . ' geändert. Bitte in der Verwaltung ansehen.';
        if (self::$zuruf !== null) { (self::$zuruf)($satz); return; }
        try { require_once __DIR__ . '/Zuruf.php'; Zuruf::vormerken('dns_geaendert', $satz, 60); } catch (Throwable $e) { }
    }

    /* ------------------------------------------------------------ Zurück */

    /**
     * Was ein Zurückrollen tun würde — ohne es zu tun.
     * @return array{ok:bool, text:string, zurueck:list<array>, hand:list<string>}
     */
    public static function rueckweg(int $nachherId): array
    {
        $s = self::laden($nachherId);
        if (!$s || $s['anlass'] !== 'nachher' || !is_array($s['aenderungen'])) { return ['ok' => false, 'text' => 'Zu diesem Stand gibt es keine Änderung, die sich zurücknehmen ließe.', 'zurueck' => [], 'hand' => []]; }
        if ($s['zurueck_am'] !== null) { return ['ok' => false, 'text' => 'Schon zurückgenommen am ' . date('d.m.Y H:i', strtotime((string) $s['zurueck_am'])) . '.', 'zurueck' => [], 'hand' => []]; }
        $zurueck = array_values(array_filter((array) ($s['aenderungen']['umgeschrieben'] ?? []), static fn($u) => preg_match('~^\d+$~', (string) ($u['id'] ?? '')) === 1));
        $hand = array_map(static fn($h) => strtoupper((string) $h['typ']) . ' ' . (((string) $h['name']) !== '' ? $h['name'] : '@') . ' ' . (string) $h['daten'],
            (array) ($s['aenderungen']['hinzugefuegt'] ?? []));
        return ['ok' => true, 'text' => '', 'zurueck' => $zurueck, 'hand' => $hand];
    }

    /**
     * Umgeschriebene Einträge auf den alten Wert zurücksetzen (nur Uwes Klick).
     * @param callable(string,string,int):array|null $aendern wie Kas::dnsAendern ohne $als
     * @return array{ok:bool, text:string, hand:list<string>}
     */
    public static function zurueckrollen(int $nachherId, string $wer, ?callable $aendern = null): array
    {
        $r = self::rueckweg($nachherId);
        if (!$r['ok']) { return ['ok' => false, 'text' => $r['text'], 'hand' => []]; }
        $s = self::laden($nachherId);
        $hand = $r['hand'];
        if ($r['zurueck'] && $aendern === null) {
            $als = self::alsFuer((string) ($s['bezug'] ?? ''));
            if ($als === null) {
                foreach ($r['zurueck'] as $u) { $hand[] = 'zurücksetzen: ' . $u['typ'] . ' ' . ($u['name'] !== '' ? $u['name'] : '@') . ' auf „' . $u['alt_daten'] . '“' . ((int) $u['alt_aux'] ? ' (Priorität ' . (int) $u['alt_aux'] . ')' : ''); }
                $r['zurueck'] = [];
            } else {
                require_once __DIR__ . '/Kas.php';
                $aendern = static fn(string $id, string $daten, int $aux): array => Kas::dnsAendern($id, $daten, $aux, $als);
            }
        }
        $gut = 0;
        $fehler = [];
        foreach ($r['zurueck'] as $u) {
            $e = $aendern((string) $u['id'], (string) $u['alt_daten'], (int) $u['alt_aux']);
            if (!empty($e['ok'])) { $gut++; } else { $fehler[] = $u['typ'] . ' ' . ($u['name'] ?: '@') . ': ' . (string) ($e['text'] ?? 'Fehler'); }
        }
        $text = $gut . ' Eintrag/Einträge auf den alten Wert gesetzt.'
              . ($fehler ? ' Nicht: ' . implode(' · ', $fehler) . '.' : '')
              . ($hand ? ' Von Hand im KAS: ' . implode(' · ', $hand) . '.' : '');
        if (!$fehler) {
            Db::run('UPDATE dns_schnappschuesse SET zurueck_am = NOW(), zurueck_von = ?, zurueck_text = ? WHERE id = ? AND zurueck_am IS NULL',
                [mb_substr($wer, 0, 80), mb_substr($text, 0, 500), $nachherId]);
        }
        Events::protokoll('dns_zurueck', 'DNS ' . $s['domain'] . ' zurückgerollt: ' . mb_substr($text, 0, 200), $s['customer_id'] !== null ? (int) $s['customer_id'] : null);
        return ['ok' => !$fehler, 'text' => $text, 'hand' => $hand];
    }

    /** Der KAS-Login des Unter-Accounts — nur solange er noch verschlüsselt am Auftrag liegt. */
    private static function alsFuer(string $bezug): ?array
    {
        if (!preg_match('~^hosting:(\d+)$~', $bezug, $m)) { return null; }
        require_once __DIR__ . '/Hosting.php';
        return Hosting::kasAlsStill((int) $m[1]);
    }

    /**
     * Ein Stand von Hand (Knopf „Jetzt festhalten“): öffentliches DNS, und die KAS-Zone,
     * wenn der Zugang des Unter-Accounts noch da ist.
     */
    public static function vonHand(string $domain, ?callable $dns = null): int
    {
        $d = self::domain($domain);
        if ($d === '') { throw new InvalidArgumentException('Keine gültige Domain.'); }
        $a = Db::one("SELECT id, customer_id FROM hosting_auftraege WHERE domain = ? ORDER BY id DESC LIMIT 1", [$d]);
        $kunde = $a ? (int) $a['customer_id'] : ((int) Db::wert('SELECT customer_id FROM websites WHERE domain = ? OR url LIKE ? ORDER BY id DESC LIMIT 1', [$d, '%' . $d . '%'], 0) ?: null);
        $kasLesen = null;
        if ($a && $dns === null) {
            require_once __DIR__ . '/Hosting.php';
            $als = Hosting::kasAlsStill((int) $a['id']);
            if ($als !== null) { require_once __DIR__ . '/Kas.php'; $kasLesen = static fn(string $x): array => Kas::dnsLesen($x, $als); }
        }
        return self::schnappschuss($d, 'hand', $kunde, $a ? 'hosting:' . (int) $a['id'] : null, $dns, $kasLesen);
    }
}
