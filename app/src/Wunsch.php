<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Bausperre.php';

/**
 * AutoBuild Phase 8 — Kundenwünsche und Scope-Schutz (06.10.2026).
 *
 *   Kunde schreibt „Ich möchte etwas ändern“  → Wunsch „neu“ (erfassen)
 *   Claude (optional, über den PC)            → VORSCHLAG im Umfang / Zusatz / unklar
 *   Mensch ordnet ein                         → im Umfang | Zusatz | abgelehnt
 *   Zusatz                                    → erst Angebot, Kunde sagt Ja → „Zusatz angenommen“
 *   „Wünsche umsetzen lassen“                 → ein Bau-Auftrag mit genau diesen Wünschen
 *   neue Fassung                              → Wünsche „umgesetzt in Vn“
 *
 * Der Masterprompt: keine Erweiterung ohne Zusatzangebot. Deshalb baut der
 * Builder nur Wünsche „im Umfang“ oder „Zusatz angenommen“ — nie „neu“ oder „Zusatz“.
 */
final class Wunsch
{
    public const STATUS = [
        'neu'               => 'neu — noch nicht eingeordnet',
        'im_umfang'         => 'im Umfang — wird umgesetzt',
        'zusatz'            => 'Zusatz — erst Angebot',
        'zusatz_angenommen' => 'Zusatz angenommen — wird umgesetzt',
        'in_arbeit'         => 'Claude baut',
        'umgesetzt'         => 'umgesetzt',
        'abgelehnt'         => 'abgelehnt',
    ];
    /** Was der Kunde sieht — in seiner Sprache, ohne interne Wörter. */
    public const KUNDE = [
        'titel'             => ['it' => 'Le sue richieste di modifica', 'de' => 'Ihre Änderungswünsche', 'en' => 'Your change requests'],
        'neu'               => ['it' => 'Ricevuta — la sto valutando', 'de' => 'Eingegangen — ich schaue es mir an', 'en' => 'Received — I’m looking at it'],
        'im_umfang'         => ['it' => 'Compresa — la realizzo', 'de' => 'Im vereinbarten Umfang — wird umgesetzt', 'en' => 'Included — I’ll make the change'],
        'zusatz'            => ['it' => 'Va oltre quanto concordato — le mando prima un preventivo', 'de' => 'Geht über das Vereinbarte hinaus — Sie bekommen vorher ein Angebot', 'en' => 'Beyond what we agreed — you’ll get a quote first'],
        'zusatz_angenommen' => ['it' => 'Preventivo accettato — la realizzo', 'de' => 'Angebot angenommen — wird umgesetzt', 'en' => 'Quote accepted — I’ll make the change'],
        'in_arbeit'         => ['it' => 'In lavorazione', 'de' => 'Wird gerade umgesetzt', 'en' => 'Being worked on'],
        'umgesetzt'         => ['it' => 'Fatto — la vede nell’anteprima', 'de' => 'Erledigt — Sie sehen es in der Vorschau', 'en' => 'Done — you’ll see it in the preview'],
        'abgelehnt'         => ['it' => 'Non realizzabile così', 'de' => 'So nicht umsetzbar', 'en' => 'Not possible this way'],
    ];

    public static function kundeText(string $schluessel, string $sprache): string
    {
        $t = self::KUNDE[$schluessel] ?? [];
        return (string) ($t[$sprache] ?? $t['it'] ?? $schluessel);
    }

    /** Diese darf der Builder bauen. */
    public const BAUBAR = ['im_umfang', 'zusatz_angenommen'];
    /** Was ein Mensch setzen darf (in_arbeit/umgesetzt setzt das System). */
    public const EINORDNEN = ['im_umfang', 'zusatz', 'zusatz_angenommen', 'abgelehnt', 'neu'];

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    public static function erfassen(int $pid, string $text, string $quelle = 'kunde', ?int $kundeId = null): int
    {
        $text = mb_substr(trim(strip_tags($text)), 0, 2000);
        if ($text === '') { throw new RuntimeException('Der Wunsch ist leer.'); }
        $p = Db::one('SELECT id, name, customer_id FROM projects WHERE id = ?', [$pid]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        $id = (int) Db::insert('projekt_wuensche', ['project_id' => $pid, 'customer_id' => $kundeId ?? (int) $p['customer_id'],
            'text' => $text, 'quelle' => $quelle === 'vecom' ? 'vecom' : 'kunde']);
        Events::pruefspur('wunsch_neu', 'projekt_wuensche', $id, [], ['projekt' => $pid, 'quelle' => $quelle]);
        if ($quelle === 'kunde') {
            self::still(static fn() => Events::melden('wunsch_neu', 'Neuer Wunsch: ' . (string) $p['name'], 'info',
                mb_substr($text, 0, 300) . ' — einordnen: im Umfang, Zusatz oder abgelehnt.', 'projekte/' . $pid . '#wuensche'), null);
        }
        return $id;
    }

    public static function liste(int $pid): array
    {
        return self::still(static fn() => Db::all('SELECT * FROM projekt_wuensche WHERE project_id = ? ORDER BY id DESC LIMIT 100', [$pid]), []);
    }

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM projekt_wuensche WHERE id = ?', [$id]) ?: null;
    }

    /** Ein Mensch ordnet ein. Ein Zusatz kann erst „angenommen“ werden, wenn er Zusatz war. */
    public static function einordnen(int $id, string $status, string $wer, string $grund = '', ?int $minuten = null): void
    {
        $w = self::laden($id);
        if (!$w) { throw new RuntimeException('Wunsch nicht gefunden.'); }
        if (!in_array($status, self::EINORDNEN, true)) { throw new RuntimeException('Unbekannte Einordnung.'); }
        if ($w['status'] === 'umgesetzt' || (int) ($w['bau_auftrag_id'] ?? 0) > 0) { throw new RuntimeException('Dieser Wunsch ist schon in Arbeit oder umgesetzt.'); }
        if ($status === 'zusatz_angenommen' && $w['status'] !== 'zusatz') { throw new RuntimeException('„Zusatz angenommen“ geht nur, wenn der Wunsch vorher als Zusatz eingeordnet war — und der Kunde dem Angebot zugestimmt hat.'); }
        $grund = mb_substr(trim(strip_tags($grund)), 0, 500);
        if (in_array($status, ['zusatz', 'abgelehnt'], true) && $grund === '') {
            $grund = $status === 'zusatz' ? 'Geht über das vereinbarte Angebot hinaus.' : 'Lässt sich so nicht umsetzen.';
        }
        /* Phase 10: Nach dem Livegang zählt „im Umfang“ gegen das Kontingent der Betreuung (Minuten aus dem Paket;
           kleine Inhaltsänderungen = 0 Min.). Ohne Vertrag und nach der Nachbesserung bleibt nur „Zusatz“. */
        $felder = [];
        if ($status === 'im_umfang') {
            require_once __DIR__ . '/Betrieb.php';
            $min = max(0, min(6000, (int) ($minuten ?? Betrieb::minutenAus((string) ($w['vorschlag_aufwand'] ?? '')))));
            $nein = Betrieb::einordnungPruefen((int) $w['project_id'], $min);
            if ($nein !== null) { throw new RuntimeException($nein); }
            $p = Db::one('SELECT * FROM projects WHERE id = ?', [(int) $w['project_id']]);
            if ($p && Betrieb::imBetrieb($p)) { $felder = ['aufwand_min' => $min, 'kontingent_monat' => date('Y-m')]; }
        } elseif ($minuten !== null && $status === 'zusatz') {
            $felder = ['aufwand_min' => max(0, min(6000, $minuten))];
        }
        Db::update('projekt_wuensche', $id, ['status' => $status, 'grund' => $grund !== '' ? $grund : null, 'eingeordnet_von' => mb_substr($wer, 0, 120), 'eingeordnet_am' => date('Y-m-d H:i:s')] + $felder);
        Events::pruefspur('wunsch_eingeordnet', 'projekt_wuensche', $id, ['status' => $w['status']], ['status' => $status, 'von' => $wer]);
    }

    /** Claudes Vorschläge speichern (Bau-Auftrag „wuensche“). Ordnet NICHT ein. */
    public static function vorschlaegeSpeichern(int $pid, array $vorschlaege): int
    {
        $n = 0;
        foreach ($vorschlaege as $v) {
            $id = (int) ($v['id'] ?? 0);
            $art = in_array($v['einordnung'] ?? '', ['im_umfang', 'zusatz', 'unklar'], true) ? (string) $v['einordnung'] : 'unklar';
            $n += Db::run("UPDATE projekt_wuensche SET vorschlag = ?, vorschlag_grund = ?, vorschlag_aufwand = ? WHERE id = ? AND project_id = ? AND status = 'neu'",
                [$art, mb_substr(trim(strip_tags((string) ($v['grund'] ?? ''))), 0, 500), mb_substr(trim(strip_tags((string) ($v['aufwand'] ?? ''))), 0, 120), $id, $pid])->rowCount();
        }
        return $n;
    }

    /** Für den Bau-Auftrag „wuensche“: die neuen Wünsche. */
    public static function neue(int $pid): array
    {
        return array_map(static fn($w) => ['id' => (int) $w['id'], 'text' => (string) $w['text'], 'datum' => substr((string) $w['created_at'], 0, 10)],
            self::still(static fn() => Db::all("SELECT id, text, created_at FROM projekt_wuensche WHERE project_id = ? AND status = 'neu' ORDER BY id LIMIT 40", [$pid]), []));
    }

    /** @return list<array{id:int,text:string}> */
    public static function baubar(int $pid): array
    {
        return array_map(static fn($w) => ['id' => (int) $w['id'], 'text' => (string) $w['text']],
            self::still(static fn() => Db::all("SELECT id, text FROM projekt_wuensche WHERE project_id = ? AND status IN ('im_umfang','zusatz_angenommen') AND bau_auftrag_id IS NULL ORDER BY id LIMIT 40", [$pid]), []));
    }

    /**
     * Alle baubaren Wünsche in EINEN Bau-Auftrag geben — auf der Live-Fassung,
     * sonst auf der neuesten. @return int|string Auftrags-Id oder Hinweis
     */
    public static function umsetzen(int $pid, string $wer): int|string
    {
        $liste = self::baubar($pid);
        if (!$liste) { return 'Es gibt keine Wünsche „im Umfang“ oder „Zusatz angenommen“.'; }
        require_once __DIR__ . '/Versionen.php';
        require_once __DIR__ . '/BauAuftrag.php';
        $basis = (int) Db::wert('SELECT live_version_id FROM projects WHERE id = ?', [$pid], 0);
        if ($basis <= 0) { $basis = (int) (Versionen::neueste($pid)['id'] ?? 0); }
        $param = ['wunsch_ids' => array_column($liste, 'id')] + ($basis > 0 ? ['version_id' => $basis] : []);
        $a = BauAuftrag::anlegen($pid, 'bauen', $wer, count($liste) . ' Kundenwünsche umsetzen', $param);
        if (!is_int($a)) { return $a; }
        Db::run('UPDATE projekt_wuensche SET bau_auftrag_id = ? WHERE project_id = ? AND id IN (' . implode(',', array_map('intval', $param['wunsch_ids'])) . ')', [$a, $pid]);
        Events::pruefspur('wuensche_umsetzen', 'projects', $pid, [], ['auftrag' => $a, 'wuensche' => $param['wunsch_ids'], 'von' => $wer]);
        return $a;
    }

    /** Nach einer neuen Fassung aus einem Wunsch-Auftrag (auch Nachbesser-Runden). */
    public static function fassungGebaut(array $wunschIds, int $versionId, int $pid): void
    {
        if (!$wunschIds) { return; }
        Db::run("UPDATE projekt_wuensche SET status = 'umgesetzt', version_id = ?, umgesetzt_am = NOW() WHERE project_id = ? AND (bau_auftrag_id IS NOT NULL OR status = 'umgesetzt') AND id IN ("
            . implode(',', array_map('intval', $wunschIds)) . ')', [$versionId, $pid]);
    }

    /** Scheitert ein Wunsch-Auftrag endgültig, gehen die Wünsche zurück in die Liste. */
    public static function zurueck(int $auftragId): void
    {
        self::still(static fn() => Db::run("UPDATE projekt_wuensche SET bau_auftrag_id = NULL WHERE bau_auftrag_id = ? AND status IN ('im_umfang','zusatz_angenommen')", [$auftragId]), null);
    }

    /** Anzeige-Status: baubar + Auftrag läuft = „Claude baut“. */
    public static function anzeige(array $w): string
    {
        return in_array($w['status'], self::BAUBAR, true) && (int) ($w['bau_auftrag_id'] ?? 0) > 0 ? 'in_arbeit' : (string) $w['status'];
    }

    /** Für die Kundenseite: Status in seinen Worten. */
    public static function fuerKunde(int $kundeId, ?int $pid): array
    {
        if (!$pid) { return []; }
        return self::still(static fn() => Db::all("SELECT id, text, status, grund, bau_auftrag_id, created_at, umgesetzt_am FROM projekt_wuensche WHERE project_id = ? AND quelle = 'kunde' ORDER BY id DESC LIMIT 30", [$pid]), []);
    }
}
