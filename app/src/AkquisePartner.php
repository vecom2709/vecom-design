<?php
declare(strict_types=1);

/**
 * Akquise-CRM Modul F (06.10.2026): Partner und ihre Reservierungen.
 *
 * Uwe:
 *   - Reservierung „Neue 30 Tage, alte bleiben“: neue Reservierungen gelten 30 Tage, in den letzten
 *     sieben Tagen kann der Partner einmal um 30 Tage verlängern. Bestehende behalten ihr Datum.
 *   - Warnungen „24h/48h Partner, 72h Sie“: Ein heißes Signal beim reservierten Betrieb (positive
 *     Antwort, Website-Check des Betriebs), auf das der Partner nicht reagiert: nach 24 h ein Punkt in
 *     „Heute zu tun“, nach 48 h ein Hinweis aufs Handy, nach 72 h eine Meldung an Uwe.
 *   - Provision bei Übernahme „Sie entscheiden je Fall“: Übernehmen, neu zuweisen und lösen fragen
 *     nach der Provision; „bleibt“ merkt den Betrieb dem bisherigen Partner vor (Partner::vormerken).
 *   - Auswertung „Funnel + Reaktionszeit“.
 *
 * Automatisch passiert nur, was niemanden außerhalb erreicht: Hinweise an Partner und an Uwe.
 * Reservierungen beendet oder verschiebt nur ein Klick in der Verwaltung (Ablauf::TRAGWEITE: schwer).
 */
final class AkquisePartner
{
    public const TAGE = 30;
    public const VERLAENGERN_TAGE = 30;
    /** Ab so vielen Tagen vor dem Ende darf verlängert werden. */
    public const VERLAENGERN_AB = 7;
    /** Stunden bis gelb, rot, Meldung an Uwe. */
    public const STUFEN = ['gelb' => 24, 'rot' => 48, 'admin' => 72];
    private const POSITIV = ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST'];
    /** Was als Reaktion des Partners zählt (partner_lead_verlauf.art). */
    private const REAKTION = ['anruf', 'whatsapp', 'email', 'notiz', 'stufe', 'uebergabe'];

    /** Darf diese Reservierung verlängert werden? */
    public static function kannVerlaengern(array $r): bool
    {
        if (!empty($r['verlaengert_am'])) { return false; }
        $bis = strtotime((string) ($r['bis'] ?? ''));
        return $bis !== false && $bis >= strtotime('today') && $bis <= strtotime('today +' . self::VERLAENGERN_AB . ' days');
    }

    /** Einmal um 30 Tage verlängern — nur die eigene, nur in den letzten sieben Tagen. @return string ok|nicht_deine|zu_frueh|schon */
    public static function verlaengern(int $partnerId, int $firmaId): string
    {
        $r = Db::one('SELECT * FROM partner_reservierungen WHERE firma_id = ? AND partner_id = ? AND bis >= CURDATE()', [$firmaId, $partnerId]);
        if (!$r) { return 'nicht_deine'; }
        if (!empty($r['verlaengert_am'])) { return 'schon'; }
        if (!self::kannVerlaengern($r)) { return 'zu_frueh'; }
        Db::run('UPDATE partner_reservierungen SET bis = DATE_ADD(bis, INTERVAL ' . self::VERLAENGERN_TAGE . ' DAY), verlaengert_am = NOW() WHERE firma_id = ? AND partner_id = ? AND verlaengert_am IS NULL',
            [$firmaId, $partnerId]);
        Akquise::protokoll($firmaId, 'partner', 'Reservierung vom Partner einmal verlängert (+' . self::VERLAENGERN_TAGE . ' Tage)', ['partner' => $partnerId]);
        return 'ok';
    }

    /**
     * Offene heiße Signale bei reservierten Betrieben, auf die der Partner noch nicht reagiert hat.
     * @return list<array{partner_id:int,partner:string,firma_id:int,firma:string,art:string,wort:string,signal_am:string,stunden:int,stufe:string,bis:string}>
     */
    public static function signale(?int $partnerId = null, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $w = 'r.bis >= CURDATE() AND (r.herkunft IS NULL OR r.herkunft <> \'vecom\' OR r.anruf_status = \'zugestimmt\') AND COALESCE(p.test, 0) = 0';
        $par = [];
        if ($partnerId !== null) { $w .= ' AND r.partner_id = ?'; $par[] = $partnerId; }
        $res = Db::all("SELECT r.firma_id, r.partner_id, r.bis, r.created_at AS res_am, r.anruf_am, p.name AS partner, f.name AS firma
                          FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id JOIN akq_firmen f ON f.id = r.firma_id WHERE $w", $par);
        if (!$res) { return []; }
        $ids = implode(',', array_map(static fn($r) => (int) $r['firma_id'], $res));
        $pos = "'" . implode("','", self::POSITIV) . "'";
        $antw = []; $chk = []; $reakt = [];
        foreach (Db::all("SELECT firma_id, MIN(eingang_am) AS am FROM akq_antworten WHERE erledigt = 0 AND klasse IN ($pos) AND firma_id IN ($ids) GROUP BY firma_id") as $a) { $antw[(int) $a['firma_id']] = (string) $a['am']; }
        try { foreach (Db::all("SELECT firma_id, MAX(created_at) AS am FROM akq_checks WHERE firma_id IN ($ids) GROUP BY firma_id") as $c) { $chk[(int) $c['firma_id']] = (string) $c['am']; } } catch (Throwable $e) { }
        $art = "'" . implode("','", self::REAKTION) . "'";
        try {
            foreach (Db::all("SELECT l.partner_id, l.firma_id, GREATEST(COALESCE(l.kontakt_am, '1970-01-01'), COALESCE(MAX(v.created_at), '1970-01-01')) AS am
                                FROM partner_leads l LEFT JOIN partner_lead_verlauf v ON v.lead_id = l.id AND v.art IN ($art)
                               WHERE l.firma_id IN ($ids) GROUP BY l.id") as $x) { $reakt[(int) $x['partner_id'] . ':' . (int) $x['firma_id']] = (string) $x['am']; }
        } catch (Throwable $e) { }
        $aus = [];
        foreach ($res as $r) {
            $fid = (int) $r['firma_id']; $pid = (int) $r['partner_id'];
            $kandidaten = [];
            if (isset($antw[$fid])) { $kandidaten[] = ['antwort', 'Positive Antwort', max($antw[$fid], (string) $r['res_am'])]; }
            if (isset($chk[$fid]) && $chk[$fid] >= (string) $r['res_am']) { $kandidaten[] = ['check', 'Website-Check gemacht', $chk[$fid]]; }
            $reaktion = max((string) ($reakt[$pid . ':' . $fid] ?? ''), (string) ($r['anruf_am'] ?? ''));
            foreach ($kandidaten as [$k, $wort, $am]) {
                if ($reaktion !== '' && $reaktion >= $am) { continue; }   // schon reagiert
                $h = (int) floor(($jetzt - strtotime($am)) / 3600);
                $stufe = $h >= self::STUFEN['admin'] ? 'admin' : ($h >= self::STUFEN['rot'] ? 'rot' : ($h >= self::STUFEN['gelb'] ? 'gelb' : ''));
                $aus[] = ['partner_id' => $pid, 'partner' => (string) $r['partner'], 'firma_id' => $fid, 'firma' => (string) $r['firma'], 'art' => $k, 'wort' => $wort,
                          'signal_am' => $am, 'stunden' => max(0, $h), 'stufe' => $stufe, 'bis' => (string) $r['bis']];
            }
        }
        usort($aus, static fn($a, $b) => $b['stunden'] <=> $a['stunden']);
        return $aus;
    }

    /**
     * Cron (Regel partner_warnungen): ab 48 h ein Hinweis an den Partner, ab 72 h eine Meldung an Uwe — je Signal einmal.
     * @return array{push:int,uwe:int}
     */
    public static function warnen(?int $jetzt = null): array
    {
        require_once __DIR__ . '/PartnerPost.php';
        $n = ['push' => 0, 'uwe' => 0];
        foreach (self::signale(null, $jetzt) as $s) {
            if ($s['stufe'] !== 'rot' && $s['stufe'] !== 'admin') { continue; }
            Db::run('INSERT IGNORE INTO partner_warnungen (partner_id, firma_id, art, signal_am) VALUES (?, ?, ?, ?)', [$s['partner_id'], $s['firma_id'], $s['art'], $s['signal_am']]);
            $w = Db::one('SELECT * FROM partner_warnungen WHERE partner_id = ? AND firma_id = ? AND art = ? AND signal_am = ?', [$s['partner_id'], $s['firma_id'], $s['art'], $s['signal_am']]);
            if (!$w) { continue; }
            if ($w['push_am'] === null) {
                PartnerPost::push($s['partner_id'], self::pushTitel($s), self::pushText($s), '/partner.php#recherche');
                Db::run('UPDATE partner_warnungen SET push_am = NOW() WHERE id = ?', [(int) $w['id']]);
                $n['push']++;
            }
            if ($s['stufe'] === 'admin' && $w['uwe_am'] === null) {
                try { Events::melden('partner_warnung', 'Partner reagiert nicht: ' . $s['partner'] . ' · ' . $s['firma'], 'warnung',
                    $s['wort'] . ' vor ' . $s['stunden'] . ' Stunden — übernehmen, erinnern, neu zuweisen oder lösen.', 'partner-reservierungen'); } catch (Throwable $e) { }
                Db::run('UPDATE partner_warnungen SET uwe_am = NOW() WHERE id = ?', [(int) $w['id']]);
                $n['uwe']++;
            }
        }
        return $n;
    }

    private static function pushTitel(array $s): string { return 'Ein Betrieb wartet auf dich'; }

    private static function pushText(array $s): string
    {
        return $s['firma'] . ': ' . ($s['art'] === 'antwort' ? 'hat positiv geantwortet' : 'hat den Website-Check gemacht') . ' — melde dich heute, sonst übernimmt Vecom.';
    }

    /** Von Hand erinnern (Knopf in der Verwaltung). */
    public static function erinnern(int $firmaId): bool
    {
        require_once __DIR__ . '/PartnerPost.php';
        $r = Db::one('SELECT r.partner_id, f.name FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id WHERE r.firma_id = ? AND r.bis >= CURDATE()', [$firmaId]);
        if (!$r) { return false; }
        PartnerPost::push((int) $r['partner_id'], 'Vecom erinnert dich', (string) $r['name'] . ': bitte melde dich heute bei diesem Betrieb.', '/partner.php#recherche', true);
        Db::run('UPDATE partner_warnungen SET erinnert_am = NOW() WHERE partner_id = ? AND firma_id = ?', [(int) $r['partner_id'], $firmaId]);
        Akquise::protokoll($firmaId, 'partner', 'Partner von Hand erinnert', ['partner' => (int) $r['partner_id']]);
        return true;
    }

    /**
     * Übernehmen, neu zuweisen oder lösen — immer mit einer Entscheidung zur Provision.
     * @return array{ok:bool,fehler?:string}
     */
    public static function entscheiden(int $firmaId, string $aktion, string $provision, string $grund, string $von, ?int $neuPartnerId = null): array
    {
        if (!in_array($aktion, ['uebernommen', 'neu_zugewiesen', 'geloest'], true)) { return ['ok' => false, 'fehler' => 'Unbekannte Aktion.']; }
        if (!in_array($provision, ['bleibt', 'entfaellt'], true)) { return ['ok' => false, 'fehler' => 'Bitte zur Provision entscheiden: bleibt oder entfällt.']; }
        $r = Db::one('SELECT r.*, f.email, f.telefon, f.name AS firma, p.name AS partner FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                       JOIN partner p ON p.id = r.partner_id WHERE r.firma_id = ? AND r.bis >= CURDATE()', [$firmaId]);
        if (!$r) { return ['ok' => false, 'fehler' => 'Dieser Betrieb ist gerade bei keinem Partner reserviert.']; }
        $alt = (int) $r['partner_id'];
        if ($aktion === 'neu_zugewiesen') {
            $neu = $neuPartnerId ? Db::one("SELECT id, name FROM partner WHERE id = ? AND status = 'aktiv' AND COALESCE(test, 0) = 0", [$neuPartnerId]) : null;
            if (!$neu || (int) $neu['id'] === $alt) { return ['ok' => false, 'fehler' => 'Bitte einen anderen aktiven Partner wählen.']; }
        }
        Db::transaktion(static function () use ($r, $firmaId, $aktion, $provision, $grund, $von, $alt, $neuPartnerId): void {
            if ($aktion === 'neu_zugewiesen') {
                Db::run('UPDATE partner_reservierungen SET partner_id = ?, bis = DATE_ADD(CURDATE(), INTERVAL ' . self::TAGE . ' DAY), verlaengert_am = NULL, created_at = NOW(),
                                herkunft = NULL, anruf_status = NULL WHERE firma_id = ?', [$neuPartnerId, $firmaId]);
            } else {
                Db::run('DELETE FROM partner_reservierungen WHERE firma_id = ?', [$firmaId]);
            }
            Db::insert('partner_entscheide', ['firma_id' => $firmaId, 'partner_id' => $alt, 'aktion' => $aktion, 'provision' => $provision,
                'neu_partner_id' => $aktion === 'neu_zugewiesen' ? $neuPartnerId : null, 'grund' => mb_substr(trim(strip_tags($grund)), 0, 255) ?: null, 'von' => mb_substr($von !== '' ? $von : 'Verwaltung', 0, 80)]);
        }, 3);
        /* „Provision bleibt“: Wird der Betrieb später Kunde, gehört er dem bisherigen Partner — über denselben Weg wie jede andere Vormerkung. */
        if ($provision === 'bleibt') {
            require_once __DIR__ . '/Partner.php';
            /* quelle ist VARCHAR(8) — „reserv“, nicht „reservierung“ (die Kette fand es: sonst still „fehler“, nichts vorgemerkt). */
            Partner::vormerken($alt, (string) ($r['email'] ?? ''), (string) ($r['telefon'] ?? ''), 'reservierung', 'reserv');
        }
        $wort = ['uebernommen' => 'Vecom hat übernommen', 'neu_zugewiesen' => 'Neu zugewiesen', 'geloest' => 'Reservierung gelöst'][$aktion];
        Akquise::protokoll($firmaId, 'partner', $wort . ' (Partner ' . $r['partner'] . ', Provision ' . ($provision === 'bleibt' ? 'bleibt' : 'entfällt') . ')' . (trim($grund) !== '' ? ': ' . trim($grund) : ''),
            ['partner' => $alt, 'neu' => $neuPartnerId]);
        Events::pruefspur('partner_reservierung_' . $aktion, 'akq_firmen', $firmaId, ['partner_id' => $alt], ['provision' => $provision, 'neu_partner_id' => $neuPartnerId]);
        return ['ok' => true];
    }

    /**
     * Funnel je Partner + Reaktionszeit (Median, Stunden) + offene Warnungen.
     * @return list<array<string,mixed>>
     */
    public static function auswertung(): array
    {
        $aus = [];
        $warn = [];
        foreach (self::signale() as $s) { if ($s['stufe'] !== '') { $warn[$s['partner_id']] = ($warn[$s['partner_id']] ?? 0) + 1; } }
        foreach (Db::all("SELECT id, name FROM partner WHERE status = 'aktiv' AND COALESCE(test, 0) = 0 ORDER BY name") as $p) {
            $pid = (int) $p['id'];
            $res = (int) Db::wert('SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ? AND bis >= CURDATE()', [$pid], 0);
            $st = [];
            try { foreach (Db::all('SELECT stufe, COUNT(*) AS n FROM partner_leads WHERE partner_id = ? AND archiviert_am IS NULL GROUP BY stufe', [$pid]) as $x) { $st[(string) $x['stufe']] = (int) $x['n']; } }
            catch (Throwable $e) { }
            /* Funnel kumuliert: wer im Angebot steht, war auch kontaktiert und interessiert. */
            $ab = static fn(array $stufen): int => array_sum(array_map(static fn($k) => $st[$k] ?? 0, $stufen));
            $aus[] = [
                'id' => $pid, 'name' => (string) $p['name'], 'reserviert' => $res,
                'kontaktiert' => $ab(['kontaktiert', 'interesse', 'termin', 'angebot', 'auftrag']),
                'interesse' => $ab(['interesse', 'termin', 'angebot', 'auftrag']),
                'angebot' => $ab(['angebot', 'auftrag']), 'kunde' => $st['auftrag'] ?? 0,
                'reaktion_h' => self::reaktionszeit($pid), 'warnungen' => $warn[$pid] ?? 0,
            ];
        }
        return $aus;
    }

    /** Median der Stunden zwischen Signal (positive Antwort) und erster Partner-Aktivität danach, letzte 90 Tage. */
    public static function reaktionszeit(int $partnerId): ?int
    {
        $pos = "'" . implode("','", self::POSITIV) . "'";
        $art = "'" . implode("','", self::REAKTION) . "'";
        try {
            $werte = array_map('intval', array_column(Db::all("SELECT TIMESTAMPDIFF(HOUR, a.eingang_am, MIN(v.created_at)) AS h
                FROM partner_leads l JOIN akq_antworten a ON a.firma_id = l.firma_id AND a.klasse IN ($pos) AND a.eingang_am >= NOW() - INTERVAL 90 DAY
                JOIN partner_lead_verlauf v ON v.lead_id = l.id AND v.art IN ($art) AND v.created_at >= a.eingang_am
                WHERE l.partner_id = ? GROUP BY a.id", [$partnerId]), 'h'));
        } catch (Throwable $e) { return null; }
        if (!$werte) { return null; }
        sort($werte);
        $m = intdiv(count($werte), 2);
        return count($werte) % 2 ? $werte[$m] : intdiv($werte[$m - 1] + $werte[$m], 2);
    }

    /** Alle aktiven Reservierungen für die Verwaltung, mit Ampel. @return list<array<string,mixed>> */
    public static function reservierungen(): array
    {
        $sig = [];
        foreach (self::signale() as $s) { $k = $s['firma_id']; if (!isset($sig[$k]) || $s['stunden'] > $sig[$k]['stunden']) { $sig[$k] = $s; } }
        $aus = [];
        foreach (Db::all("SELECT r.firma_id, r.partner_id, r.bis, r.created_at, r.verlaengert_am, r.herkunft, p.name AS partner, f.name AS firma, f.stadt
                            FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id JOIN akq_firmen f ON f.id = r.firma_id
                           WHERE r.bis >= CURDATE() AND COALESCE(p.test, 0) = 0 ORDER BY r.bis") as $r) {
            $s = $sig[(int) $r['firma_id']] ?? null;
            $aus[] = $r + ['signal' => $s];
        }
        usort($aus, static fn($a, $b) => (int) (($b['signal']['stunden'] ?? -1) <=> ($a['signal']['stunden'] ?? -1)));
        return $aus;
    }
}
