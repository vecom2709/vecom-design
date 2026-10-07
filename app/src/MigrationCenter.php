<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/DnsSchutz.php';

/**
 * Migration Center (AI Office Stufe 5, 07.10.2026, Uwe: „Eigene Seite + Pre-Flight“).
 *
 * WAS ES SCHON GAB
 * Domainumzug, Seitenumzug, Mailumzug — drei Klassen, drei Tabellen, drei eigene Stände,
 * jeweils einzeln in der Kundenakte. Ein Kunde, der mit Domain, Seite und Post zu Vecom
 * kommt, stand an drei Stellen, und keine sagte, wie weit „sein Umzug“ ist.
 *
 * WAS HIER DAZUKOMMT
 * Ein Vorgang je Kunde darüber — nichts davon ersetzt die drei. Der Vorgang hat einen
 * gemeinsamen Stand mit Verlauf und einen Pre-Flight-Bericht, der vor dem Start liest,
 * was schiefgehen kann. Gelesen wird nur; geschrieben wird nur in die eigenen Tabellen.
 *
 * STÄNDE
 *   neu → preflight → bereit → läuft ⇄ wartet (auf den Kunden) → abgeschlossen
 *   jederzeit → fehlgeschlagen → zurückgerollt (oder neuer Pre-Flight)
 * Uwe setzt Stände per Klick (nur erlaubte Übergänge). Den Rest leitet abgleich() aus
 * den drei Teilen ab: Läuft ein Teil, läuft der Vorgang; scheitert einer, ist er
 * fehlgeschlagen; sind alle durch, ist er abgeschlossen. Was Uwe zuletzt von Hand
 * gesetzt hat (fehlgeschlagen, zurückgerollt), überschreibt die Ableitung nicht.
 */
final class MigrationCenter
{
    public const STAENDE = [
        'neu' => 'Neu', 'preflight' => 'Pre-Flight', 'bereit' => 'Bereit', 'laeuft' => 'Läuft',
        'wartet' => 'Wartet auf Kunde', 'abgeschlossen' => 'Abgeschlossen',
        'fehlgeschlagen' => 'Fehlgeschlagen', 'zurueckgerollt' => 'Zurückgerollt',
    ];

    /** Von Hand erlaubte Übergänge. */
    public const UEBERGAENGE = [
        'neu'            => ['preflight', 'fehlgeschlagen'],
        'preflight'      => ['bereit', 'neu', 'fehlgeschlagen'],
        'bereit'         => ['laeuft', 'preflight', 'fehlgeschlagen'],
        'laeuft'         => ['wartet', 'abgeschlossen', 'fehlgeschlagen'],
        'wartet'         => ['laeuft', 'abgeschlossen', 'fehlgeschlagen'],
        'fehlgeschlagen' => ['zurueckgerollt', 'preflight'],
        'zurueckgerollt' => ['preflight'],
        'abgeschlossen'  => [],
    ];

    /** Fertig im Sinn von „nichts mehr zu tun“. */
    public const ENDE = ['abgeschlossen', 'zurueckgerollt'];

    public const ARTEN = ['domain' => 'Domain', 'seite' => 'Website', 'mail' => 'E-Mail'];

    /* ------------------------------------------------------------ Teile */

    /**
     * Der Stand eines Teils in einer gemeinsamen Sprache.
     * @return string offen | wartet_kunde | laeuft | fertig | fehler | abgebrochen
     */
    public static function teilStand(string $art, string $stand): string
    {
        return match ($art) {
            'domain' => ['code_fehlt' => 'wartet_kunde', 'code_da' => 'offen', 'beantragt' => 'laeuft', 'fertig' => 'fertig'][$stand] ?? 'offen',
            'seite'  => ['angefragt' => 'wartet_kunde', 'zugang_da' => 'laeuft', 'fertig' => 'fertig', 'abgebrochen' => 'abgebrochen'][$stand] ?? 'offen',
            'mail'   => ['angefragt' => 'wartet_kunde', 'zugang_da' => 'laeuft', 'laeuft' => 'laeuft', 'fertig' => 'fertig',
                         'fehler' => 'fehler', 'abgebrochen' => 'abgebrochen'][$stand] ?? 'offen',
            default  => 'offen',
        };
    }

    /** Was beim Teil gerade zu tun ist, in Uwes Worten. */
    public static function teilWort(string $art, string $stand): string
    {
        return match ($art) {
            'domain' => ['code_fehlt' => 'wartet auf den Auth-Code des Kunden', 'code_da' => 'Code da — KK-Antrag stellen',
                         'beantragt' => 'beantragt — wartet auf die Nameserver', 'fertig' => 'umgezogen'][$stand] ?? $stand,
            'seite'  => ['angefragt' => 'wartet auf den FTP-Zugang des Kunden', 'zugang_da' => 'Zugang da — kopieren',
                         'fertig' => 'umgezogen', 'abgebrochen' => 'abgebrochen'][$stand] ?? $stand,
            'mail'   => ['angefragt' => 'wartet auf den Postfach-Zugang', 'zugang_da' => 'Zugang da — Abgleich startet',
                         'laeuft' => 'Post wird kopiert', 'fertig' => 'kopiert (holt 14 Tage nach)', 'fehler' => 'Fehler beim Kopieren',
                         'abgebrochen' => 'angehalten'][$stand] ?? $stand,
            default  => $stand,
        };
    }

    /** @return list<array{art:string, ref_id:int, stand:string, gemeinsam:string, wort:string, titel:string, kunde:int}> */
    public static function teile(int $migrationId): array
    {
        $aus = [];
        foreach (Db::all('SELECT art, ref_id FROM migration_teile WHERE migration_id = ? ORDER BY id', [$migrationId]) as $t) {
            $z = self::teilZeile((string) $t['art'], (int) $t['ref_id']);
            if ($z !== null) { $aus[] = $z; }
        }
        return $aus;
    }

    private static function teilZeile(string $art, int $id): ?array
    {
        $r = match ($art) {
            'domain' => Db::one('SELECT id, customer_id, domain AS titel, stand, NULL AS fehler_text FROM domain_umzuege WHERE id = ?', [$id]),
            'seite'  => Db::one('SELECT id, customer_id, adresse AS titel, stand, NULL AS fehler_text FROM seitenumzuege WHERE id = ?', [$id]),
            'mail'   => Db::one('SELECT id, customer_id, adresse AS titel, stand, fehler AS fehler_text FROM mailumzuege WHERE id = ?', [$id]),
            default  => null,
        };
        if (!$r) { return null; }
        $stand = (string) $r['stand'];
        return ['art' => $art, 'ref_id' => $id, 'stand' => $stand, 'gemeinsam' => self::teilStand($art, $stand),
                'wort' => self::teilWort($art, $stand), 'titel' => (string) $r['titel'], 'kunde' => (int) $r['customer_id'],
                'fehler' => (string) ($r['fehler_text'] ?? '')];
    }

    /**
     * Was die Teile zusammen sagen — oder null, wenn sie nichts Neues sagen.
     * @param list<string> $gemeinsam
     */
    public static function abgeleitet(array $gemeinsam): ?string
    {
        if (!$gemeinsam) { return null; }
        if (in_array('fehler', $gemeinsam, true)) { return 'fehlgeschlagen'; }
        $rest = array_values(array_diff($gemeinsam, ['abgebrochen']));
        if (!$rest) { return null; }
        if (!array_diff($rest, ['fertig'])) { return 'abgeschlossen'; }
        if (in_array('laeuft', $rest, true)) { return 'laeuft'; }
        if (in_array('wartet_kunde', $rest, true)) { return 'wartet'; }
        return null;
    }

    /* ------------------------------------------------------------ Abgleich */

    /**
     * Neue Umzüge einem Vorgang zuordnen und die Stände nachziehen. Läuft im Cron und beim
     * Öffnen der Seite; zweimal hintereinander ändert nichts.
     * @return array{neu:int, geaendert:int}
     */
    public static function abgleich(): array
    {
        $n = ['neu' => 0, 'geaendert' => 0];
        $quellen = [
            'domain' => "SELECT d.id, d.customer_id, d.domain AS adresse FROM domain_umzuege d JOIN customers c ON c.id = d.customer_id WHERE c.demo = 0",
            'seite'  => "SELECT s.id, s.customer_id, s.adresse FROM seitenumzuege s JOIN customers c ON c.id = s.customer_id WHERE c.demo = 0",
            'mail'   => "SELECT m.id, m.customer_id, m.adresse FROM mailumzuege m JOIN customers c ON c.id = m.customer_id WHERE c.demo = 0",
        ];
        foreach ($quellen as $art => $sql) {
            foreach (Db::all($sql . " AND NOT EXISTS (SELECT 1 FROM migration_teile t WHERE t.art = '$art' AND t.ref_id = " . ($art === 'domain' ? 'd' : ($art === 'seite' ? 's' : 'm')) . ".id) ORDER BY 1") as $r) {
                $kunde = (int) $r['customer_id'];
                $mid = (int) Db::wert("SELECT id FROM migrationen WHERE customer_id = ? AND stand NOT IN ('abgeschlossen','zurueckgerollt') ORDER BY id DESC LIMIT 1", [$kunde], 0);
                if ($mid === 0) {
                    $mid = Db::insert('migrationen', ['customer_id' => $kunde, 'domain' => self::domainAus((string) $r['adresse']) ?: null, 'stand' => 'neu']);
                    self::verlauf($mid, null, 'neu', 'Aus einem bestehenden Umzug übernommen (' . self::ARTEN[$art] . ').', 'System');
                    $n['neu']++;
                }
                Db::run('INSERT IGNORE INTO migration_teile (migration_id, art, ref_id) VALUES (?, ?, ?)', [$mid, $art, (int) $r['id']]);
                if ((string) Db::wert('SELECT COALESCE(domain, \'\') FROM migrationen WHERE id = ?', [$mid], '') === '') {
                    $dom = self::domainAus((string) $r['adresse']);
                    if ($dom !== '') { Db::run('UPDATE migrationen SET domain = ? WHERE id = ?', [$dom, $mid]); }
                }
            }
        }
        foreach (Db::all("SELECT id, stand FROM migrationen WHERE stand NOT IN ('abgeschlossen','zurueckgerollt')") as $m) {
            if (self::nachziehen((int) $m['id'])) { $n['geaendert']++; }
        }
        return $n;
    }

    /** Den Stand aus den Teilen ableiten — ohne Uwes letzte Hand-Entscheidung zu überstimmen. */
    public static function nachziehen(int $id): bool
    {
        $m = Db::one('SELECT * FROM migrationen WHERE id = ?', [$id]);
        if (!$m) { return false; }
        $alt = (string) $m['stand'];
        $teile = self::teile($id);
        $neu = self::abgeleitet(array_column($teile, 'gemeinsam'));
        if ($neu === null || $neu === $alt || in_array($alt, self::ENDE, true)) { return false; }
        // Fehlgeschlagen bleibt, bis Uwe entscheidet (Rückweg oder neuer Pre-Flight).
        if ($alt === 'fehlgeschlagen') { return false; }
        // Vor dem Pre-Flight zieht nur ein echtes Ereignis den Stand: Fehler, Abschluss, ein laufender Teil.
        if (in_array($alt, ['neu', 'preflight'], true) && $neu === 'wartet') { return false; }
        $grund = match ($neu) {
            'fehlgeschlagen' => 'Ein Teil ist gescheitert: ' . implode(' · ', array_map(static fn($t) => self::ARTEN[$t['art']] . ' ' . $t['titel'] . ($t['fehler'] !== '' ? ' (' . mb_substr($t['fehler'], 0, 120) . ')' : ''),
                                    array_filter($teile, static fn($t) => $t['gemeinsam'] === 'fehler'))),
            'abgeschlossen'  => 'Alle Teile sind durch.',
            'laeuft'         => 'Ein Teil läuft: ' . implode(' · ', array_map(static fn($t) => self::ARTEN[$t['art']] . ' — ' . $t['wort'], array_filter($teile, static fn($t) => $t['gemeinsam'] === 'laeuft'))),
            'wartet'         => 'Wartet auf den Kunden: ' . implode(' · ', array_map(static fn($t) => self::ARTEN[$t['art']] . ' — ' . $t['wort'], array_filter($teile, static fn($t) => $t['gemeinsam'] === 'wartet_kunde'))),
            default          => '',
        };
        return self::stand($id, $neu, $grund, 'System', true);
    }

    /**
     * Stand setzen. Von Hand nur über erlaubte Übergänge; der Abgleich darf jeden ableiten.
     */
    public static function stand(int $id, string $nach, string $grund, string $wer, bool $abgeleitet = false): bool
    {
        if (!isset(self::STAENDE[$nach])) { return false; }
        $m = Db::one('SELECT stand FROM migrationen WHERE id = ?', [$id]);
        if (!$m) { return false; }
        $von = (string) $m['stand'];
        if ($von === $nach) { return false; }
        if (!$abgeleitet && !in_array($nach, self::UEBERGAENGE[$von] ?? [], true)) { return false; }
        $n = Db::run('UPDATE migrationen SET stand = ?, abgeschlossen_am = IF(? = \'abgeschlossen\', NOW(), abgeschlossen_am) WHERE id = ? AND stand = ?',
            [$nach, $nach, $id, $von])->rowCount();
        if ($n === 0) { return false; }
        self::verlauf($id, $von, $nach, $grund, $wer);
        if ($nach === 'fehlgeschlagen') {
            $mm = Db::one('SELECT customer_id, domain FROM migrationen WHERE id = ?', [$id]);
            Events::melden('migration_fehlgeschlagen', 'Umzug fehlgeschlagen' . (!empty($mm['domain']) ? ': ' . $mm['domain'] : ''), 'schlecht',
                mb_substr($grund, 0, 400), '/umzuege?id=' . $id);
        }
        return true;
    }

    private static function verlauf(int $id, ?string $von, string $nach, string $grund, string $wer): void
    {
        Db::insert('migration_verlauf', ['migration_id' => $id, 'von' => $von, 'nach' => $nach,
            'grund' => mb_substr(trim($grund), 0, 500) ?: null, 'wer' => mb_substr($wer, 0, 80)]);
    }

    /** Ein Vorgang von Hand — für einen Umzug, der erst geplant ist. */
    public static function anlegen(int $kundeId, string $domain, string $wer, string $notiz = ''): int
    {
        if (!Db::one('SELECT id FROM customers WHERE id = ? AND demo = 0', [$kundeId])) { throw new InvalidArgumentException('Diesen Kunden gibt es nicht.'); }
        $d = DnsSchutz::domain($domain);
        if ($d === '') { throw new InvalidArgumentException('Bitte eine gültige Domain angeben (z. B. firma.it).'); }
        $offen = (int) Db::wert("SELECT id FROM migrationen WHERE customer_id = ? AND stand NOT IN ('abgeschlossen','zurueckgerollt') LIMIT 1", [$kundeId], 0);
        if ($offen > 0) { throw new InvalidArgumentException('Für diesen Kunden läuft schon ein Umzug (#' . $offen . ').'); }
        $id = Db::insert('migrationen', ['customer_id' => $kundeId, 'domain' => $d, 'stand' => 'neu', 'notiz' => trim($notiz) !== '' ? mb_substr(trim($notiz), 0, 500) : null]);
        self::verlauf($id, null, 'neu', 'Von Hand angelegt.', $wer);
        Events::protokoll('migration_angelegt', 'Umzug für ' . $d . ' angelegt', $kundeId);
        return $id;
    }

    public static function domainAus(string $adresse): string
    {
        $a = trim($adresse);
        if (str_contains($a, '@')) { $a = (string) substr((string) strrchr($a, '@'), 1); }
        return DnsSchutz::domain($a);
    }

    /* ------------------------------------------------------------ Lesen */

    /** @return list<array> offene zuerst, dann die letzten abgeschlossenen */
    public static function liste(int $anzahl = 60): array
    {
        $zeilen = Db::all("SELECT m.*, c.name AS kunde_name, c.company AS kunde_firma FROM migrationen m JOIN customers c ON c.id = m.customer_id
                            ORDER BY (m.stand IN ('abgeschlossen','zurueckgerollt')), m.updated_at DESC LIMIT " . max(1, min(200, $anzahl)));
        foreach ($zeilen as &$z) {
            $z['teile'] = self::teile((int) $z['id']);
            $z['kunde'] = trim((string) ($z['kunde_firma'] ?: $z['kunde_name']));
        }
        return $zeilen;
    }

    public static function vorgang(int $id): ?array
    {
        $m = Db::one('SELECT m.*, c.name AS kunde_name, c.company AS kunde_firma FROM migrationen m JOIN customers c ON c.id = m.customer_id WHERE m.id = ?', [$id]);
        if (!$m) { return null; }
        $m['kunde'] = trim((string) ($m['kunde_firma'] ?: $m['kunde_name']));
        $m['teile'] = self::teile($id);
        $m['preflight'] = json_decode((string) ($m['preflight_json'] ?? ''), true) ?: null;
        $m['verlauf'] = Db::all('SELECT * FROM migration_verlauf WHERE migration_id = ? ORDER BY id DESC LIMIT 50', [$id]);
        $m['erlaubt'] = self::UEBERGAENGE[(string) $m['stand']] ?? [];
        return $m;
    }

    /* ------------------------------------------------------------ Pre-Flight */

    /**
     * Der Bericht vor dem Start. Liest nur — und hält dabei den DNS-Stand fest.
     *
     * @param array $quellen austauschbar für die Prüfkette: dns (wie dns_get_record),
     *        rdap (fn(domain): ?array), web (fn(url): array{code:int, html:string, fehler:string})
     * @return array{ampel:string, punkte:list<array{punkt:string, ampel:string, text:string}>, am:string, schnappschuss:?int}
     */
    public static function preflight(int $id, string $wer = 'Verwaltung', array $quellen = []): array
    {
        $m = Db::one('SELECT * FROM migrationen WHERE id = ?', [$id]);
        if (!$m) { throw new InvalidArgumentException('Diesen Umzug gibt es nicht.'); }
        $dns = $quellen['dns'] ?? null;
        $domain = (string) ($m['domain'] ?? '');
        $kunde = (int) $m['customer_id'];
        $teile = self::teile($id);
        $arten = array_column($teile, 'art');
        $p = [];
        $punkt = static function (string $was, string $ampel, string $text) use (&$p): void { $p[] = ['punkt' => $was, 'ampel' => $ampel, 'text' => $text]; };

        if ($domain === '') {
            $punkt('Domain', 'rot', 'Am Umzug steht keine Domain — ohne sie lässt sich nichts prüfen.');
        } else {
            // 1. Domain beim Registrar
            $rdap = array_key_exists('rdap', $quellen) ? ($quellen['rdap'])($domain)
                : (static function () use ($domain) { require_once __DIR__ . '/Domainpruefung.php'; return Domainpruefung::rdap($domain); })();
            if (!is_array($rdap)) {
                $punkt('Domain', 'warn', 'Keine RDAP-Auskunft (bei .it üblich) — Inhaber, Sperre und Ablauf beim bisherigen Anbieter nachsehen.');
            } else {
                $status = mb_strtolower(implode(' ', (array) ($rdap['status'] ?? [])));
                $gesperrt = str_contains($status, 'transfer prohibited') || str_contains($status, 'transferprohibited');
                $ablauf = '';
                foreach ((array) ($rdap['events'] ?? []) as $e) {
                    if (in_array((string) ($e['eventAction'] ?? ''), ['expiration', 'expiry'], true)) { $ablauf = (string) ($e['eventDate'] ?? ''); }
                }
                $registrar = '';
                foreach ((array) ($rdap['entities'] ?? []) as $en) {
                    if (in_array('registrar', (array) ($en['roles'] ?? []), true)) {
                        foreach ((array) (($en['vcardArray'][1] ?? [])) as $v) { if (($v[0] ?? '') === 'fn') { $registrar = (string) ($v[3] ?? ''); } }
                    }
                }
                $tage = $ablauf !== '' ? (int) floor((strtotime($ablauf) - time()) / 86400) : null;
                $amp = ($gesperrt && in_array('domain', $arten, true)) ? 'rot' : (($tage !== null && $tage < 30) ? 'warn' : 'ok');
                $punkt('Domain', $amp, trim(($registrar !== '' ? 'Registrar: ' . $registrar . '. ' : '')
                    . ($gesperrt ? 'Transfersperre gesetzt' . (in_array('domain', $arten, true) ? ' — der Kunde muss sie lösen, sonst scheitert der Antrag. ' : '. ') : 'Keine Transfersperre. ')
                    . ($tage !== null ? 'Läuft ab am ' . date('d.m.Y', (int) strtotime($ablauf)) . ($tage < 30 ? ' — in ' . max(0, $tage) . ' Tagen, vor dem Umzug verlängern lassen.' : '.') : '')));
            }

            // 2. DNS festhalten — der Vorher-Stand des ganzen Umzugs
            $bestand = Domainumzug::bestandsaufnahme($domain, $dns);
            $snap = null;
            try {
                $snap = DnsSchutz::schnappschuss($domain, 'hand', $kunde, 'migration:' . $id, $dns, null, $bestand);
            } catch (Throwable $e) { }
            if (!$bestand) {
                $punkt('DNS', 'rot', 'Die Domain liefert keine DNS-Einträge — existiert sie, und ist der Name richtig?');
            } else {
                $ns = array_map(static fn($e) => rtrim(strtolower((string) $e['wert']), '.'), array_filter($bestand, static fn($e) => $e['typ'] === 'NS'));
                $beiUns = $ns && !array_filter($ns, static fn($x) => !str_ends_with($x, 'kasserver.com'));
                $punkt('DNS', 'ok', count($bestand) . ' Einträge festgehalten' . ($snap ? ' (Stand #' . $snap . ')' : '') . '. Nameserver: '
                    . ($ns ? implode(', ', $ns) : 'keine gelesen') . ($beiUns ? ' — schon bei All-Inkl.' : '.'));
            }

            // 3. Post: Wer nimmt heute die Mails an, und was passiert nach dem Umzug?
            $mx = array_values(array_filter($bestand, static fn($e) => $e['typ'] === 'MX'));
            $spf = (bool) array_filter($bestand, static fn($e) => $e['typ'] === 'TXT' && stripos((string) $e['wert'], 'v=spf1') === 0 && $e['name'] === '@');
            $dmarc = (bool) array_filter($bestand, static fn($e) => $e['name'] === '_dmarc');
            $dkim = count(array_filter($bestand, static fn($e) => str_contains((string) $e['name'], '._domainkey')));
            $auftrag = Db::one("SELECT * FROM hosting_auftraege WHERE customer_id = ? AND domain = ? ORDER BY id DESC LIMIT 1", [$kunde, $domain]);
            $mailZiel = $auftrag ? (string) ($auftrag['mail'] ?? 'vecom') : '';
            if (!$mx) {
                $punkt('E-Mail', 'warn', 'Kein MX-Eintrag — an diese Domain kommt heute keine Post an. Absichtlich?');
            } else {
                $ziele = implode(', ', array_map(static fn($e) => preg_replace('~^\d+\s+~', '', rtrim((string) $e['wert'], '.')), $mx));
                $extern = !str_contains(strtolower($ziele), 'kasserver.com');
                $amp = 'ok';
                $satz = 'Post läuft heute über ' . $ziele . '.';
                if ($mailZiel === 'vecom' && $extern) {
                    $amp = in_array('mail', $arten, true) ? 'ok' : 'warn';
                    $satz .= ' Nach dem Umzug nimmt Vecom die Post an — ' . (in_array('mail', $arten, true) ? 'der Mailumzug ist angelegt.' : 'vorhandene Postfächer vorher umziehen (Kundenakte → Mailumzug), sonst bleiben alte Mails beim alten Anbieter.');
                } elseif ($mailZiel !== '' && $mailZiel !== 'vecom') {
                    $satz .= ' Die Post bleibt dort — MX, SPF, DKIM und DMARC werden beim Umzug übernommen (mit Vorher-Stand).';
                }
                $punkt('E-Mail', $amp, $satz);
            }
            $punkt('Mail-Schutz', ($mx && (!$spf || !$dmarc)) ? 'warn' : 'ok',
                'SPF ' . ($spf ? 'ja' : 'fehlt') . ' · DMARC ' . ($dmarc ? 'ja' : 'fehlt') . ' · DKIM ' . ($dkim ? $dkim . ' gefunden' : 'keiner unter den üblichen Namen')
                . (($mx && (!$spf || !$dmarc)) ? ' — ohne SPF/DMARC landen Mails eher im Spam; nach dem Umzug nachtragen.' : '.'));

            // 4. Website
            $web = array_key_exists('web', $quellen) ? ($quellen['web'])('https://' . $domain) : self::webLesen('https://' . $domain);
            if ((int) $web['code'] >= 200 && (int) $web['code'] < 400) {
                $cms = self::cms((string) $web['html']);
                $punkt('Website', 'ok', 'Erreichbar über HTTPS (' . (int) $web['code'] . ')' . ($cms !== '' ? ', gebaut mit ' . $cms : '') . '.'
                    . (in_array('seite', $arten, true) ? '' : ' Soll die Seite mit umziehen, in der Kundenakte den Seitenumzug anlegen.'));
            } else {
                $punkt('Website', 'warn', 'Über HTTPS nicht erreichbar' . ((string) $web['fehler'] !== '' ? ' (' . mb_substr((string) $web['fehler'], 0, 120) . ')' : ' (' . (int) $web['code'] . ')')
                    . ' — Zertifikat oder Server prüfen, bevor Besucher den Umzug dafür halten.');
            }
        }

        // 5. Ziel bei Vecom
        $auftrag ??= Db::one("SELECT * FROM hosting_auftraege WHERE customer_id = ? ORDER BY id DESC LIMIT 1", [$kunde]);
        if (!$auftrag) {
            $punkt('Ziel bei Vecom', 'rot', 'Kein Hosting-Auftrag — es gibt noch keinen Platz, wohin umgezogen wird. In der Kundenakte unter „Domain & Hosting“ anbieten.');
        } else {
            $st = (string) $auftrag['status'];
            $amp = in_array($st, ['angelegt', 'aktiv'], true) ? 'ok' : (in_array($st, ['zugestimmt', 'in_arbeit'], true) ? 'warn' : 'rot');
            $punkt('Ziel bei Vecom', $amp, 'Hosting-Auftrag ' . (string) $auftrag['domain'] . ': ' . $st
                . ($amp === 'ok' ? ' — der KAS-Account steht.' : ($amp === 'warn' ? ' — wird gerade eingerichtet.' : ' — noch nicht zugestimmt oder nicht angelegt.')));
        }

        // 6. Zustimmung des Kunden je Teil
        $braucht = ['domain' => 'domain_transfer', 'seite' => 'migration', 'mail' => 'mailumzug'];
        $fehlt = [];
        foreach (array_unique($arten) as $art) {
            if (!Db::one('SELECT id FROM zustimmungen WHERE customer_id = ? AND art = ? LIMIT 1', [$kunde, $braucht[$art]])) { $fehlt[] = self::ARTEN[$art]; }
        }
        if (!$arten) {
            $punkt('Zustimmung', 'offen', 'Noch kein Umzugsteil angelegt — die Zustimmung gibt der Kunde mit dem jeweiligen Teil.');
        } else {
            $punkt('Zustimmung', $fehlt ? 'rot' : 'ok', $fehlt ? 'Es fehlt die Zustimmung des Kunden für: ' . implode(', ', $fehlt) . '. Ohne sie wird nichts umgezogen.'
                : 'Der Kunde hat jedem Teil zugestimmt (Wortlaut in der Kundenakte).');
        }

        $ampeln = array_column($p, 'ampel');
        $gesamt = in_array('rot', $ampeln, true) ? 'rot' : (in_array('warn', $ampeln, true) ? 'warn' : 'ok');
        $bericht = ['ampel' => $gesamt, 'punkte' => $p, 'am' => date('Y-m-d H:i:s'), 'schnappschuss' => $snap ?? null];
        Db::run('UPDATE migrationen SET preflight_json = ?, preflight_am = NOW(), preflight_ampel = ? WHERE id = ?',
            [json_encode($bericht, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $gesamt, $id]);
        $alt = (string) $m['stand'];
        if (in_array($alt, ['neu', 'fehlgeschlagen', 'zurueckgerollt', 'bereit'], true)) { self::stand($id, 'preflight', 'Pre-Flight gestartet.', $wer); }
        if ($gesamt !== 'rot') {
            self::stand($id, 'bereit', 'Pre-Flight ohne Blocker (' . ($gesamt === 'ok' ? 'alles grün' : 'mit Hinweisen') . ').', $wer);
        }
        Events::protokoll('migration_preflight', 'Pre-Flight ' . ($domain ?: '#' . $id) . ': ' . (['ok' => 'alles in Ordnung', 'warn' => 'mit Hinweisen', 'rot' => 'mit Blockern'][$gesamt] ?? $gesamt), $kunde);
        return $bericht;
    }

    /** @return array{code:int, html:string, fehler:string} */
    private static function webLesen(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_USERAGENT => 'vecom-design.it Pre-Flight',
            CURLOPT_RANGE => '0-200000']);
        $html = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'html' => is_string($html) ? $html : '', 'fehler' => $fehler];
    }

    /** Woran man das Baukastensystem erkennt — nur Hinweise aus dem HTML, keine Vermutung. */
    public static function cms(string $html): string
    {
        if (preg_match('~<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']{2,60})~i', $html, $m)) { return trim($m[1]); }
        foreach (['wp-content/' => 'WordPress', 'Joomla' => 'Joomla', 'static.wixstatic.com' => 'Wix', 'squarespace' => 'Squarespace',
                  'jimdo' => 'Jimdo', 'shopify' => 'Shopify', 'webflow' => 'Webflow'] as $spur => $name) {
            if (stripos($html, $spur) !== false) { return $name; }
        }
        return '';
    }
}
