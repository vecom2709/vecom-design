<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/TelegramWachstum.php';
require_once __DIR__ . '/MkKennzahlen.php';
require_once __DIR__ . '/Fmt.php';

/* ==========================================================================
   TelegramZahlen.php — Telegram Growth Engine, Schritt T2: das Dashboard
   (01.10.2026, Uwe: „Ja mach T2“). Reiter „Telegram“ unter Marketing.

   NUR LESEN, NUR GEMESSENES

   Hier wird nichts gezählt und nichts gespeichert — das tun der Bot, die
   Mini-App und der Webhook (TelegramWachstum). Diese Klasse setzt drei
   Quellen zusammen:

     tg_tage         Tageszahlen je Quelle: neue Nutzer, Starts, Beitritte,
                     Stufen des Funnels (je Chat einmal gezählt)
     tg_herkunft     welcher Kunde über Telegram kam → Kunden und Umsatz
     mk_kampagnen / mk_kosten / partner   Namen und Kosten der Quellen

   Was Telegram nicht hergibt (Aufrufe einzelner Beiträge, welche Gruppe
   jemanden geschickt hat, solange es dafür keinen eigenen Link gab), steht
   als „noch nicht messbar“ da — nie als 0.

   GROWTH SCORE

   Eine Zahl je Quelle, damit man Quellen vergleichen kann, deren Stärken
   verschieden sind (die eine bringt Mitglieder, die andere Anfragen). Die
   Gewichte stehen offen in GEWICHTE und auf der Seite; sie bewerten, wie
   nah eine Handlung am Umsatz ist. Mit Kosten zusätzlich „Punkte je 10 €“.
   Keine KI, keine versteckte Formel.
   ========================================================================== */
final class TelegramZahlen
{
    /** Punkte je Ereignis — je näher am Umsatz, desto mehr. Umsatz: 1 Punkt je 100 €. */
    public const GEWICHTE = [
        'bot_neu' => 1, 'kanal_bei' => 1, 'rechner_fertig' => 3, 'beratung' => 5, 'lead' => 10, 'kunden' => 30,
    ];
    public const PUNKTE_JE_UMSATZ_CENTS = 10000;

    /** Eine Kampagne mit Werbemittel (m_code_wm) zählt zu ihrer Kampagne (m_code). */
    public static function familie(string $q): string
    {
        if (preg_match('/^m_([a-z0-9][a-z0-9-]{2,23})_[a-z0-9-]+$/', $q, $m)) { return 'm_' . $m[1]; }
        return $q;
    }

    /** @return array{name:string, art:string, id:?int} */
    public static function beschreiben(string $q, array $kampagnen, array $partner): array
    {
        if (str_starts_with($q, 'm_')) {
            $k = $kampagnen[substr($q, 2)] ?? null;
            return ['name' => $k ? 'Kampagne „' . $k['name'] . '“' : 'Kampagne ' . substr($q, 2), 'art' => 'kampagne', 'id' => $k ? (int) $k['id'] : null];
        }
        if (str_starts_with($q, 'p_')) {
            $p = $partner[strtoupper(substr($q, 2))] ?? null;
            return ['name' => $p ? 'Partner ' . $p['name'] : 'Partner ' . substr($q, 2), 'art' => 'partner', 'id' => $p ? (int) $p['id'] : null];
        }
        if (str_starts_with($q, 'e_')) { return ['name' => 'Empfehlung eines Kunden', 'art' => 'empfehlung', 'id' => null]; }
        return match ($q) {
            ''          => ['name' => 'ohne Quelle (direkt, Suche, öffentlicher Name)', 'art' => 'ohne', 'id' => null],
            'kanal'     => ['name' => 'Kanal-Beiträge (Knöpfe im Kanal)', 'art' => 'kanal', 'id' => null],
            'web'       => ['name' => 'Website (Telegram-Knopf)', 'art' => 'website', 'id' => null],
            'telegram'  => ['name' => 'Mini-App ohne Angabe', 'art' => 'sonst', 'id' => null],
            'einladung' => ['name' => 'Einladungslink ohne Kampagne', 'art' => 'sonst', 'id' => null],
            default     => ['name' => ucfirst($q), 'art' => 'sonst', 'id' => null],
        };
    }

    /** Eine Abfrage, die scheitert (Migration noch offen), macht eine Zahl leer, nicht die Seite kaputt. */
    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /**
     * Neue Kunden (erste bezahlte Zahlung im Zeitraum) und Umsatz (alle
     * bezahlten Zahlungen im Zeitraum) der Kunden, die über Telegram kamen —
     * je Quelle. Umsatz schließt Raten aus Verträgen ein.
     *
     * @return array<string, array{kunden:int, umsatz:int}>
     */
    public static function kundenJeQuelle(string $von, string $bis): array
    {
        $a = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $aus = [];
        foreach ((array) self::still(static fn() => Db::all(
            "SELECT h.quelle, COUNT(*) AS n FROM tg_herkunft h
               JOIN (SELECT o.customer_id, MIN(p.paid_at) AS erst FROM payments p JOIN orders o ON o.id = p.order_id
                      WHERE p.status = 'bezahlt' AND p.demo = 0 GROUP BY o.customer_id) k ON k.customer_id = h.customer_id
              WHERE k.erst BETWEEN ? AND ? GROUP BY h.quelle", $a), []) as $r) {
            $aus[(string) $r['quelle']]['kunden'] = (int) $r['n'];
        }
        foreach ((array) self::still(static fn() => Db::all(
            "SELECT h.quelle, SUM(p.amount_cents) AS n FROM payments p
               LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos ab ON ab.id = p.abo_id
               JOIN tg_herkunft h ON h.customer_id = COALESCE(o.customer_id, ab.customer_id)
              WHERE p.status = 'bezahlt' AND p.demo = 0 AND p.paid_at BETWEEN ? AND ? GROUP BY h.quelle", $a), []) as $r) {
            $aus[(string) $r['quelle']]['umsatz'] = (int) $r['n'];
        }
        foreach ($aus as $q => $w) { $aus[$q] = $w + ['kunden' => 0, 'umsatz' => 0]; }
        return $aus;
    }

    /** Punkte einer Quelle nach GEWICHTE. */
    public static function score(array $z): int
    {
        $p = 0;
        foreach (self::GEWICHTE as $k => $g) { $p += (int) ($z[$k] ?? 0) * $g; }
        return $p + intdiv((int) ($z['umsatz'] ?? 0), self::PUNKTE_JE_UMSATZ_CENTS);
    }

    /**
     * Alles für den Reiter „Telegram“.
     *
     * @param array $z  MkKennzahlen::zeitraum()
     */
    public static function dashboard(array $z): array
    {
        [$von, $bis, , $vv, $vb] = $z;
        $heute = date('Y-m-d');
        $s = TelegramWachstum::summen($von, $bis);
        $sv = TelegramWachstum::summen($vv, $vb);
        $tage = max(1, (int) round((strtotime($bis) - strtotime($von)) / 86400) + 1);
        $neuIn = static fn(string $a, string $b): ?int => TelegramWachstum::summen($a, $b)['bot_neu'];

        /* Kanal: letzter Stand bis zum Ende, Stand vor dem Anfang (sonst die erste Messung im Zeitraum). */
        $kanalStand = static fn(string $sql, array $p): ?array => self::still(static fn() => Db::one($sql, $p) ?: null, null);
        $ende = $kanalStand("SELECT tag, zahl FROM tg_tage WHERE art = 'kanal_stand' AND tag <= ? ORDER BY tag DESC LIMIT 1", [$bis]);
        $anfang = $kanalStand("SELECT tag, zahl FROM tg_tage WHERE art = 'kanal_stand' AND tag < ? ORDER BY tag DESC LIMIT 1", [$von])
               ?? $kanalStand("SELECT tag, zahl FROM tg_tage WHERE art = 'kanal_stand' AND tag BETWEEN ? AND ? ORDER BY tag ASC LIMIT 1", [$von, $bis]);
        $kanal = [
            'stand' => $ende ? (int) $ende['zahl'] : null, 'stand_tag' => $ende['tag'] ?? null,
            'anfang' => $anfang ? (int) $anfang['zahl'] : null, 'anfang_tag' => $anfang['tag'] ?? null,
            'bei' => (int) ($s['kanal_bei'] ?? 0), 'aus' => (int) ($s['kanal_aus'] ?? 0),
        ];
        $kanal['netto'] = $kanal['bei'] - $kanal['aus'];
        $kanal['wachstum'] = $ende && $anfang && $anfang['tag'] !== $ende['tag'] ? (int) $ende['zahl'] - (int) $anfang['zahl'] : null;
        $kanal['wachstum_pct'] = $kanal['wachstum'] !== null && (int) $anfang['zahl'] > 0 ? round($kanal['wachstum'] / (int) $anfang['zahl'] * 100, 1) : null;

        /* Je Quelle: Zähler aus tg_tage (zur Kampagne zusammengefasst), Kunden/Umsatz aus tg_herkunft, Kosten der Kampagne. */
        $roh = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT quelle, art, SUM(zahl) AS n FROM tg_tage WHERE tag BETWEEN ? AND ?
                                                               AND art NOT IN ('kanal_stand','bot_aktiv','bot_wieder') GROUP BY quelle, art", [$von, $bis]), []) as $r) {
            $f = self::familie((string) $r['quelle']);
            $roh[$f][(string) $r['art']] = ($roh[$f][(string) $r['art']] ?? 0) + (int) $r['n'];
        }
        $kjq = self::kundenJeQuelle($von, $bis);
        foreach ($kjq as $q => $w) { $f = self::familie($q); foreach ($w as $k => $v) { $roh[$f][$k] = ($roh[$f][$k] ?? 0) + $v; } }
        $kampagnen = [];
        foreach ((array) self::still(static fn() => Db::all('SELECT id, code, name FROM mk_kampagnen'), []) as $k) { $kampagnen[(string) $k['code']] = $k; }
        $partner = [];
        foreach ((array) self::still(static fn() => Db::all('SELECT id, code, name FROM partner'), []) as $p) { $partner[strtoupper((string) $p['code'])] = $p; }
        $kosten = (array) self::still(static function () use ($von, $bis) { require_once __DIR__ . '/MkKampagne.php'; return MkKampagne::kostenJe($von, $bis); }, []);

        $felder = ['bot_start', 'bot_neu', 'kanal_bei', 'app_start', 'wegweiser', 'check', 'interesse', 'rechner', 'rechner_fertig', 'beratung', 'lead', 'kunden', 'umsatz'];
        $quellen = [];
        foreach ($roh as $q => $w) {
            $zeile = ['quelle' => $q] + self::beschreiben($q, $kampagnen, $partner);
            foreach ($felder as $f) { $zeile[$f] = (int) ($w[$f] ?? 0); }
            $zeile['kosten'] = $zeile['art'] === 'kampagne' && $zeile['id'] !== null ? (int) ($kosten[$zeile['id']] ?? 0) : 0;
            $zeile['score'] = self::score($zeile);
            $zeile['je10'] = $zeile['kosten'] > 0 ? round($zeile['score'] / ($zeile['kosten'] / 1000), 1) : null;
            if (array_sum(array_intersect_key($zeile, array_flip($felder))) === 0) { continue; }
            $quellen[] = $zeile;
        }
        usort($quellen, static fn($a, $b) => [$b['score'], $b['lead'], $b['bot_neu']] <=> [$a['score'], $a['lead'], $a['bot_neu']]);

        $sum = static fn(string $f): int => array_sum(array_column($quellen, $f));
        $kunden = $sum('kunden'); $umsatz = $sum('umsatz');
        $vorher = array_values(self::kundenJeQuelle($vv, $vb));

        $funnel = [
            ['Über einen Link gekommen', (int) ($s['bot_start'] ?? 0) + $kanal['bei'], 'Starts über Bot-Links und Kanal-Beitritte über Kanal-Links'],
            ['Neu in Telegram', (int) ($s['bot_neu'] ?? 0) + (int) ($s['app_start'] ?? 0), 'neue Bot-Nutzer und geöffnete Mini-Apps'],
            ['Wegweiser genutzt', (int) ($s['wegweiser'] ?? 0), 'einen Punkt im Menü des Bots gewählt'],
            ['Website-Check geöffnet', (int) ($s['check'] ?? 0), ((int) ($s['check_fertig'] ?? 0)) . ' mit Ergebnis im Chat'],
            ['Interesse an einem Thema', (int) ($s['interesse'] ?? 0) + (int) ($s['app_start'] ?? 0), 'Thema im Bot gewählt oder Mini-App geöffnet'],
            ['Preisrechner gestartet', (int) ($s['rechner'] ?? 0), ((int) ($s['rechner_fertig'] ?? 0)) . ' abgeschlossen'],
            ['Beratung gestartet', (int) ($s['beratung'] ?? 0), 'persönliche Beratung im Bot angefragt'],
            ['Lead (Anfrage abgeschickt)', (int) ($s['lead'] ?? 0), 'aus Bot und Mini-App'],
            ['Kunde (erste Zahlung)', $kunden, 'Kunden, die über Telegram kamen'],
        ];

        $nurArt = static function (string $art, string $f) use ($quellen): array {
            $l = [];
            foreach ($quellen as $q) { if ($q['art'] === $art && $q[$f] > 0) { $l[$q['name']] = $q[$f]; } }
            return $l;
        };
        $kpLeads = $nurArt('kampagne', 'lead');
        $paLeads = $nurArt('partner', 'lead');
        $beste = [
            'quelle' => $quellen && $quellen[0]['score'] > 0
                ? ['name' => $quellen[0]['name'], 'zahl' => $quellen[0]['score'], 'zweiter' => $quellen[1]['name'] ?? null,
                   'zweiter_zahl' => $quellen[1]['score'] ?? 0, 'summe' => $sum('score'), 'wenig' => $sum('lead') < 10] : null,
            'kampagne' => MkKennzahlen::erster($kpLeads ?: $nurArt('kampagne', 'bot_start'), 10),
            'kampagne_nach' => $kpLeads ? 'Leads' : 'Starts (noch kein Lead)',
            'partner' => MkKennzahlen::erster($paLeads ?: $nurArt('partner', 'bot_neu'), 5),
            'partner_nach' => $paLeads ? 'Leads' : 'neue Nutzer (noch kein Lead)',
        ];

        $d = [
            'summe' => $s, 'vorher' => $sv, 'tage' => $tage,
            'neu' => ['heute' => $neuIn($heute, $heute), '7' => $neuIn(date('Y-m-d', strtotime('-6 days')), $heute), '30' => $neuIn(date('Y-m-d', strtotime('-29 days')), $heute)],
            'heute' => TelegramWachstum::summen($heute, $heute),
            'kanal' => $kanal, 'quellen' => $quellen, 'funnel' => $funnel, 'beste' => $beste,
            'kunden' => $kunden, 'umsatz' => $umsatz,
            'kunden_vorher' => array_sum(array_column($vorher, 'kunden')), 'umsatz_vorher' => array_sum(array_column($vorher, 'umsatz')),
            'partner_leads' => array_sum($nurArt('partner', 'lead')),
            'kosten' => $sum('kosten'),
        ];
        $d['hinweise'] = self::hinweise($d);
        return $d;
    }

    /**
     * Was auffällt — nur Regeln, jede mit ihrer Zahl (wie MkKennzahlen::hinweise).
     * @return list<string>
     */
    public static function hinweise(array $d): array
    {
        $h = [];
        $s = $d['summe'];
        if ($d['kanal']['stand'] === null) { $h[] = 'Die Mitgliederzahl des Kanals ist noch nicht gemessen — der tägliche Lauf trägt sie ein.'; }
        if (!array_filter($d['quellen'], static fn($q) => $q['art'] === 'kampagne')) {
            $h[] = 'Noch keine Kampagne mit Telegram-Link genutzt. Jede Kampagne hat einen eigenen Bot- und Kanal-Link (Marketing → Kampagnen) — erst dann sieht man hier, welche Quelle wirkt.';
        }
        foreach ($d['quellen'] as $q) {
            if ($q['art'] === 'kampagne' && $q['bot_start'] + $q['kanal_bei'] >= 20 && $q['lead'] === 0) {
                $h[] = $q['name'] . ': ' . ($q['bot_start'] + $q['kanal_bei']) . ' Starts und Beitritte, noch keine Anfrage.';
            }
            if ($q['kosten'] > 0 && $q['lead'] === 0) {
                $h[] = $q['name'] . ': ' . Fmt::geld($q['kosten']) . ' Kosten im Zeitraum, noch kein Lead über Telegram.';
            }
        }
        $r = (int) ($s['rechner'] ?? 0); $rf = (int) ($s['rechner_fertig'] ?? 0);
        if ($r >= 10 && $rf / $r < 0.3) {
            $h[] = 'Preisrechner: ' . $r . ' gestartet, nur ' . $rf . ' abgeschlossen (' . number_format($rf / $r * 100, 0, ',', '.') . ' %) — viele brechen unterwegs ab.';
        }
        $neu = (int) ($s['bot_neu'] ?? 0);
        if ($neu >= 20 && (int) ($s['lead'] ?? 0) === 0) { $h[] = $neu . ' neue Bot-Nutzer im Zeitraum, aber keine Anfrage.'; }
        if ($d['kanal']['aus'] > 0 && $d['kanal']['aus'] >= $d['kanal']['bei'] && $d['kanal']['bei'] + $d['kanal']['aus'] >= 5) {
            $h[] = 'Der Kanal hat im Zeitraum mehr verloren (' . $d['kanal']['aus'] . ') als gewonnen (' . $d['kanal']['bei'] . ').';
        }
        return $h;
    }
}
