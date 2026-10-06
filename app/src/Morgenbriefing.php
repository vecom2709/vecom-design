<?php
declare(strict_types=1);

/**
 * Das Morgenbriefing (AI Office Stufe 2, V2, 07.10.2026).
 *
 * Uwe: um 07:30 an sein Telegram, mit allem, was er angekreuzt hat —
 * „Wartet auf Sie“, „Geld“, „Technik“, „Akquise und Termine“.
 *
 * KEINE ZWEITE RECHNUNG
 * Was hier gezählt wird, kommt aus denselben Quellen wie „Heute“, die
 * Chef-Zentrale und Manuelas Tageslage: Freigabe::anzahlOffen,
 * Ausgang::anzahlOffen, Chef::lage (die nach Dringlichkeit geordneten
 * Punkte), TelegramAdmin::lage. Neu sind nur die Blicke, die es so noch
 * nicht gab: Geld von gestern, gestörte Seiten, Termine von heute.
 *
 * NIE EINE ERFUNDENE NULL
 * Lässt sich eine Zahl nicht lesen, steht dort null und im Text „–“. Eine
 * Null hieße „nichts los“ — und das wäre eine Behauptung.
 *
 * Kein Sprachmodell schreibt hier mit (Masterprompt: keine Claude-API).
 * Der Text entsteht aus festen Sätzen. Die Daten dahinter liest Claude über
 * das Werkzeug „lage_heute“ (ClaudeWerkzeuge) — dieselbe Funktion daten().
 */
final class Morgenbriefing
{
    /** Ab wann gesendet wird — und bis wann noch, falls der Cron gehangen hat. */
    public const AB = '07:30';
    public const BIS = '11:00';

    /** Für die Prüfung: statt Telegram hier ablegen. */
    public static ?array $abgefangen = null;

    /** @return array<string,mixed> */
    public static function daten(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $heute = date('Y-m-d', $jetzt);
        $gestern = date('Y-m-d', $jetzt - 86400);
        $z = static function (string $sql, array $p = []): ?int {
            try { return (int) Db::wert($sql, $p, 0); } catch (Throwable $e) { return null; }
        };
        $still = static function (callable $fn, mixed $ersatz = null): mixed { try { return $fn(); } catch (Throwable $e) { return $ersatz; } };

        /* ---------- Wartet auf dich ---------- */
        $lage = $still(static function () { require_once __DIR__ . '/TelegramAdmin.php'; return TelegramAdmin::lage(); }, []);
        $chef = $still(static function () { require_once __DIR__ . '/Chef.php'; return Chef::lage(); }, []);
        $wartet = [
            'freigaben' => $still(static function () { require_once __DIR__ . '/Freigabe.php'; return Freigabe::anzahlOffen(); }),
            'gehalten'  => $still(static function () { require_once __DIR__ . '/Ausgang.php'; return Ausgang::anzahlOffen(); }),
            'du'        => isset($lage['du']) ? (int) $lage['du'] : null,
            'kunde'     => isset($lage['kunde']) ? (int) $lage['kunde'] : null,
            'anfragen'  => isset($lage['anfragen']) ? (int) $lage['anfragen'] : null,
            'nachrichten' => isset($lage['nachrichten']) ? (int) $lage['nachrichten'] : null,
            'wichtig'   => array_slice(array_values(array_map(static fn($p) => ['stufe' => (string) $p['stufe'], 'text' => (string) $p['text']],
                              array_filter((array) ($chef['prioritaeten'] ?? []), static fn($p) => in_array($p['stufe'] ?? '', ['KRITISCH', 'HOCH'], true)))), 0, 6),
        ];

        /* ---------- Geld ---------- */
        $offenSql = "FROM payments p LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos a ON a.id = p.abo_id
                     WHERE p.status IN ('ausstehend','in_bearbeitung','fehlgeschlagen') AND p.demo = 0";
        $geld = [
            'gestern_cent'   => $z("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'bezahlt' AND demo = 0 AND DATE(paid_at) = ?", [$gestern]),
            'gestern_anzahl' => $z("SELECT COUNT(*) FROM payments WHERE status = 'bezahlt' AND demo = 0 AND DATE(paid_at) = ?", [$gestern]),
            'offen_cent'     => $z("SELECT COALESCE(SUM(p.amount_cents),0) $offenSql"),
            'offen_anzahl'   => $z("SELECT COUNT(*) $offenSql"),
            'ueberfaellig_cent'   => $z("SELECT COALESCE(SUM(p.amount_cents),0) $offenSql AND p.faellig_am < ?", [$heute]),
            'ueberfaellig_anzahl' => $z("SELECT COUNT(*) $offenSql AND p.faellig_am < ?", [$heute]),
        ];
        // Der Kundenname hängt an der Bestellung oder am Vertrag.
        $geld['ueberfaellig'] = $still(static fn() => array_map(static fn($r) => [
                'kunde' => (string) ($r['kunde'] ?? '–'), 'cent' => (int) $r['amount_cents'],
                'tage' => (int) round((strtotime($heute) - strtotime((string) $r['faellig_am'])) / 86400), 'was' => (string) ($r['bezeichnung'] ?? '')],
            Db::all("SELECT p.amount_cents, p.faellig_am, p.bezeichnung, COALESCE(NULLIF(c.company,''), c.name) AS kunde
                       FROM payments p LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos a ON a.id = p.abo_id
                       LEFT JOIN customers c ON c.id = COALESCE(o.customer_id, a.customer_id)
                      WHERE p.status IN ('ausstehend','in_bearbeitung','fehlgeschlagen') AND p.demo = 0 AND p.faellig_am < ?
                      ORDER BY p.faellig_am LIMIT 3", [$heute])), null);

        // Umsatz-Spürhund (Stufe 3): was offen ist und was seit gestern dazukam.
        $geld['chancen'] = $still(static function () use ($jetzt) {
            require_once __DIR__ . '/Spuerhund.php';
            return Spuerhund::stand(date('Y-m-d H:i:s', $jetzt - 86400));
        });

        /* ---------- Technik ---------- */
        $cron = $still(static function () { require_once __DIR__ . '/Cron.php'; return Cron::zuletzt(); });
        $sicherung = $still(static function () { require_once __DIR__ . '/SicherungAussen.php'; return SicherungAussen::stand(); }, null);
        $technik = [
            'seiten'    => $z("SELECT COUNT(*) FROM websites WHERE monitoring = 1 AND status <> 'nicht_veroeffentlicht' AND demo = 0"),
            'gestoert'  => $still(static fn() => Db::all("SELECT domain, status FROM websites WHERE monitoring = 1 AND demo = 0
                                AND status IN ('offline','fehler','ssl_problem','domain_problem') ORDER BY id LIMIT 5"), null),
            'zertifikate' => $still(static fn() => array_map(static fn($r) => ['domain' => (string) $r['domain'],
                                'tage' => (int) floor((strtotime((string) $r['ssl_expires_at']) - $jetzt) / 86400)],
                                Db::all("SELECT domain, ssl_expires_at FROM websites WHERE monitoring = 1 AND demo = 0
                                AND ssl_expires_at IS NOT NULL AND ssl_expires_at <= ? ORDER BY ssl_expires_at LIMIT 5",
                                [date('Y-m-d H:i:s', $jetzt + 14 * 86400)])), null),
            'cron_zuletzt' => $cron,
            'cron_alt'  => $cron === null ? null : strtotime((string) $cron) < $jetzt - 30 * 60,
            'sicherung_eingerichtet' => $sicherung['eingerichtet'] ?? null,
            'sicherung_abgeholt' => $sicherung['abgeholt']['am'] ?? null,
            'probe_am'  => $sicherung['probe']['am'] ?? null,
            'probe_ok'  => isset($sicherung['probe']['ok']) ? (bool) $sicherung['probe']['ok'] : null,
            'notaus'    => $still(static function () { require_once __DIR__ . '/Automation.php'; return Automation::notAus(); }),
        ];

        /* ---------- Akquise und Termine ---------- */
        $interesse = "'INTERESTED','CALL_REQUEST','PRICE_REQUEST','MORE_INFO'";
        $akquise = [
            'antworten_gestern' => $z('SELECT COUNT(*) FROM akq_antworten WHERE DATE(created_at) = ?', [$gestern]),
            'interesse_offen'   => $z("SELECT COUNT(*) FROM akq_antworten WHERE erledigt = 0 AND klasse IN ($interesse)"),
            'versendet_gestern' => $z("SELECT COUNT(*) FROM akq_versand WHERE status IN ('gesendet','von_hand') AND DATE(created_at) = ?", [$gestern]),
            'wiedervorlagen'    => $z('SELECT COUNT(*) FROM akq_firmen WHERE gesperrt = 0 AND wiedervorlage_am IS NOT NULL AND wiedervorlage_am <= ?', [$heute]),
            'anfragen_gestern'  => $z('SELECT COUNT(*) FROM anfragen WHERE demo = 0 AND DATE(created_at) = ?', [$gestern]),
        ];
        $termine = $still(static fn() => array_map(static fn($r) => [
                'zeit' => date('H:i', strtotime((string) $r['beginn'])),
                'wer' => trim((string) ($r['firma'] ?: $r['name'])) ?: '–', 'thema' => (string) ($r['thema'] ?? ''), 'art' => (string) ($r['art'] ?? '')],
            Db::all("SELECT beginn, name, firma, thema, art FROM akq_termine WHERE status = 'gebucht' AND DATE(beginn) = ? ORDER BY beginn", [$heute])), null);

        return ['datum' => $heute, 'stand' => date('d.m.Y H:i', $jetzt), 'wartet' => $wartet, 'geld' => $geld,
                'technik' => $technik, 'akquise' => $akquise, 'termine' => $termine];
    }

    /** Der Text fürs Telegram (HTML, wie es der Bot überall nutzt). */
    public static function text(array $d): string
    {
        require_once __DIR__ . '/Status.php';
        $h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n = static fn($v): string => $v === null ? '–' : (string) (int) $v;
        $eur = static fn($c): string => $c === null ? '–' : number_format(((int) $c) / 100, 2, ',', '.') . ' €';
        $tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $monate = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $t = strtotime((string) $d['datum']);
        $z = [];
        $z[] = '☀️ <b>Guten Morgen, Uwe</b> — ' . $tage[(int) date('w', $t)] . ', ' . (int) date('j', $t) . '. ' . $monate[(int) date('n', $t)];

        $w = $d['wartet'];
        $z[] = '';
        $z[] = '<b>Wartet auf dich</b>';
        $z[] = '• AI Freigaben: ' . $n($w['freigaben']) . ($w['gehalten'] ? ' · zurückgehaltene Mails: ' . $n($w['gehalten']) : '');
        $z[] = '• Vorgänge bei dir: ' . $n($w['du']) . ' · beim Kunden: ' . $n($w['kunde']);
        $z[] = '• Neue Anfragen: ' . $n($w['anfragen']) . ' · ungelesene Kundennachrichten: ' . $n($w['nachrichten']);
        foreach ($w['wichtig'] as $p) {
            $z[] = ($p['stufe'] === 'KRITISCH' ? '🔴 ' : '🟠 ') . $h(mb_substr($p['text'], 0, 160));
        }

        $g = $d['geld'];
        $z[] = '';
        $z[] = '<b>Geld</b>';
        $z[] = '• Gestern eingegangen: ' . $eur($g['gestern_cent']) . ($g['gestern_anzahl'] ? ' (' . $n($g['gestern_anzahl']) . ')' : '');
        $z[] = '• Offen: ' . $eur($g['offen_cent']) . ' in ' . $n($g['offen_anzahl']) . ' Zahlung' . ((int) $g['offen_anzahl'] === 1 ? '' : 'en');
        if ((int) $g['ueberfaellig_anzahl'] > 0) {
            $z[] = '• Davon überfällig: ' . $eur($g['ueberfaellig_cent']) . ' (' . $n($g['ueberfaellig_anzahl']) . ')';
            foreach ((array) $g['ueberfaellig'] as $u) {
                $z[] = '   – ' . $h($u['kunde']) . ': ' . $eur($u['cent']) . ', seit ' . (int) $u['tage'] . ' Tag' . ((int) $u['tage'] === 1 ? '' : 'en');
            }
        }

        if (!empty($g['chancen']['offen'])) {
            $c = $g['chancen'];
            $wert = [];
            if ($c['wert_monat'] > 0) { $wert[] = 'rund ' . $eur($c['wert_monat']) . ' im Monat'; }
            if ($c['wert_einmal'] > 0) { $wert[] = $eur($c['wert_einmal']) . ' in Angeboten'; }
            $z[] = '• Umsatz-Chancen: ' . (int) $c['offen'] . ' offen' . ($c['neu'] ? ', ' . (int) $c['neu'] . ' neu' : '') . ($wert ? ' (' . implode(', ', $wert) . ')' : '');
            foreach ($c['neue'] as $t) { $z[] = '   – neu: ' . $h(mb_substr($t, 0, 120)); }
        }

        $te = $d['technik'];
        $z[] = '';
        $z[] = '<b>Technik</b>';
        if ($te['notaus']) { $z[] = '🛑 Not-Aus ist gezogen — nichts geht automatisch nach draußen.'; }
        $gestoert = (array) ($te['gestoert'] ?? []);
        $z[] = '• Seiten überwacht: ' . $n($te['seiten']) . ($te['gestoert'] === null ? ' · Stand –' : ($gestoert ? ' · gestört: ' . count($gestoert) : ' · alle erreichbar'));
        foreach ($gestoert as $s) { $z[] = '   – ' . $h($s['domain']) . ' (' . $h(Status::WEBSITE[$s['status']] ?? $s['status']) . ')'; }
        foreach ((array) ($te['zertifikate'] ?? []) as $c) {
            $z[] = '• Zertifikat ' . $h($c['domain']) . ' läuft ' . ($c['tage'] < 0 ? 'seit ' . -$c['tage'] . ' Tagen nicht mehr' : 'in ' . $c['tage'] . ' Tag' . ($c['tage'] === 1 ? '' : 'en') . ' ab');
        }
        $z[] = '• Cronjob: ' . ($te['cron_zuletzt'] === null ? 'noch nie gelaufen' : 'zuletzt ' . date('H:i', strtotime((string) $te['cron_zuletzt']))
            . ($te['cron_alt'] ? ' ⚠️ seit über 30 Minuten still' : ''));
        if ($te['sicherung_eingerichtet']) {
            $z[] = '• Sicherung auf deinem PC: ' . ($te['sicherung_abgeholt'] ? 'abgeholt ' . date('d.m. H:i', strtotime((string) $te['sicherung_abgeholt'])) : 'noch nie abgeholt')
                . ($te['probe_am'] ? ' · Probe ' . date('d.m.', strtotime((string) $te['probe_am'])) . ($te['probe_ok'] === false ? ' ⚠️ mit Problem' : ' in Ordnung') : ' · noch keine Probe');
        }

        $a = $d['akquise'];
        $z[] = '';
        $z[] = '<b>Akquise und Termine</b>';
        $z[] = '• Gestern: ' . $n($a['antworten_gestern']) . ' Antworten, ' . $n($a['versendet_gestern']) . ' versendet, ' . $n($a['anfragen_gestern']) . ' Anfragen über die Website';
        $z[] = '• Interessenten offen: ' . $n($a['interesse_offen']) . ' · Wiedervorlagen fällig: ' . $n($a['wiedervorlagen']);
        if ($d['termine'] === null) {
            $z[] = '• Termine heute: –';
        } elseif ($d['termine'] === []) {
            $z[] = '• Heute keine Termine.';
        } else {
            foreach ($d['termine'] as $tm) {
                $z[] = '• ' . $h($tm['zeit']) . ' ' . $h($tm['wer']) . ($tm['thema'] !== '' ? ' — ' . $h(mb_substr($tm['thema'], 0, 80)) : '');
            }
        }
        return implode("\n", $z);
    }

    /**
     * An Uwes Telegram (jeder verbundene Admin-Chat). Klappt das nicht, geht es
     * wie der Wochenbericht über den Ersatzweg (Zuruf). Wirft nie.
     *
     * @return array{gesendet:int, weg:string}
     */
    public static function senden(?int $jetzt = null): array
    {
        $text = self::text(self::daten($jetzt));
        if (self::$abgefangen !== null) { self::$abgefangen[] = $text; return ['gesendet' => 1, 'weg' => 'abgefangen']; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis();
        $gesendet = 0;
        try {
            require_once __DIR__ . '/Telegram.php';
            require_once __DIR__ . '/TelegramAdmin.php';
            if (Telegram::bereit()) {
                foreach (Db::all('SELECT id, chat_id, admin_verbunden FROM telegram_chats WHERE admin_verbunden IS NOT NULL') as $c) {
                    if (!TelegramAdmin::darfChat($c)) { continue; }
                    $r = Telegram::rufen('sendMessage', ['chat_id' => (int) $c['chat_id'], 'text' => mb_substr($text, 0, 3900), 'parse_mode' => 'HTML',
                        'link_preview_options' => ['is_disabled' => true],
                        'reply_markup' => ['inline_keyboard' => [[['text' => '✅ AI Freigaben', 'url' => $basis . '/ai-freigaben'],
                                                                  ['text' => '🛠 Verwaltung', 'url' => $basis . '/heute']]]]]);
                    if (!empty($r['ok'])) { $gesendet++; }
                }
            }
        } catch (Throwable $e) { }
        if ($gesendet > 0) { return ['gesendet' => $gesendet, 'weg' => 'telegram']; }
        try {
            require_once __DIR__ . '/Zuruf.php';
            Zuruf::vormerken('morgenbriefing', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'), 20 * 60);
            return ['gesendet' => 0, 'weg' => 'zuruf'];
        } catch (Throwable $e) {
            return ['gesendet' => 0, 'weg' => 'keiner'];
        }
    }

    /** Ist jetzt die Zeit dafür? (Der Cron fragt alle zehn Minuten.) */
    public static function faellig(?int $jetzt = null): bool
    {
        $uhr = date('H:i', $jetzt ?? time());
        return $uhr >= self::AB && $uhr < self::BIS;
    }
}
