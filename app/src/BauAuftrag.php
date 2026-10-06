<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Bausperre.php';

/**
 * AutoBuild Phase 5 — die Bau-Warteschlange (06.10.2026, Uwe: „ja“).
 *
 *   Knopf an der Projektkarte „Bauen“ → Auftrag „wartet“
 *   PC      → fragt alle 5 Minuten nach (steuern: bau_wartet), holt ab → „läuft“
 *   Claude  → arbeitet mit Uwes Abo auf dem PC, liefert Markdown zurück
 *   PC      → meldet „fertig“ oder „Fehler“
 *   Mensch  → liest, übernimmt (oder nicht). Erst die übernommene Fassung gilt.
 *
 * Zuerst nur Arten, die an keiner Website etwas ändern: Analyse und
 * Pflichtenheft. Sie dürfen auch vor Angebotsannahme laufen (Planen ist
 * erlaubt, Bauen nicht) — nur der Not-Aus hält sie an. Bauen, Ändern und
 * Testen kommen in späteren Phasen und prüfen dann Bausperre::pruefen().
 */
final class BauAuftrag
{
    public const ARTEN = [
        'analyse'       => ['Analyse', 'Machbarkeit, Risiken, Aufwand und offene Fragen — nur intern.'],
        'pflichtenheft' => ['Pflichtenheft', 'Ziel, Seiten, Funktionen, Inhalte und Abnahmekriterien — Grundlage für den Bau.'],
    ];
    /** Arten, die bauen oder ändern — brauchen später Annahme + Anzahlung. Noch leer. */
    public const BAUEN = [];
    public const STATUS = ['wartet' => 'wartet auf deinen PC', 'laeuft' => 'Claude arbeitet', 'fertig' => 'fertig',
                           'fehler' => 'nicht geklappt', 'abgebrochen' => 'abgebrochen'];
    /** Schutz fürs Claude-Abo. */
    public const PRO_TAG = 12;
    /** Meldet der PC sich so lange nicht, gilt der Auftrag als gescheitert. */
    public const HOECHSTENS_MIN = 75;
    public const MAX_ERGEBNIS = 200_000;

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    public static function name(string $art): string
    {
        return self::ARTEN[$art][0] ?? $art;
    }

    /** @return int|string  Auftragsnummer oder Hinweis */
    public static function anlegen(int $pid, string $art, string $wer, string $hinweis = ''): int|string
    {
        if (!isset(self::ARTEN[$art])) { return 'Unbekannte Auftragsart.'; }
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { return 'Projekt nicht gefunden.'; }
        $bs = Bausperre::darfBauen($p);
        if ($bs['stopp']) { return $bs['grund'] . ' Es wird kein Auftrag angelegt.'; }
        if (in_array($art, self::BAUEN, true) && !$bs['ok']) { return $bs['grund']; }
        self::aufraeumen();
        $offen = Db::one("SELECT status FROM bau_auftraege WHERE project_id = ? AND art = ? AND status IN ('wartet','laeuft') LIMIT 1", [$pid, $art]);
        if ($offen) { return self::name($art) . ($offen['status'] === 'laeuft' ? ' läuft gerade schon.' : ' wartet schon auf deinen PC.'); }
        if (self::heute() >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Bau-Aufträge gelaufen — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $hinweis = mb_substr(trim(strip_tags($hinweis)), 0, 500);
        $id = (int) Db::insert('bau_auftraege', ['project_id' => $pid, 'art' => $art, 'hinweis' => $hinweis !== '' ? $hinweis : null, 'von' => mb_substr($wer, 0, 120)]);
        Events::pruefspur('bau_auftrag', 'bau_auftraege', $id, [], ['projekt' => $pid, 'art' => $art, 'von' => $wer]);
        Events::protokoll('bau_auftrag', self::name($art) . ' angestoßen: ' . (string) $p['name'], (int) $p['customer_id'] ?: null, null, $pid, ['auftrag_id' => $id]);
        return $id;
    }

    private static function heute(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM bau_auftraege WHERE created_at >= CURDATE() AND status <> 'abgebrochen'", [], 0);
    }

    public static function abbrechen(int $id, string $wer): ?string
    {
        $n = Db::run("UPDATE bau_auftraege SET status = 'abgebrochen', fertig_am = NOW() WHERE id = ? AND status = 'wartet'", [$id])->rowCount();
        if ($n === 0) { return 'Nur wartende Aufträge lassen sich abbrechen.'; }
        Events::pruefspur('bau_auftrag_abbruch', 'bau_auftraege', $id, ['status' => 'wartet'], ['status' => 'abgebrochen', 'von' => $wer]);
        return null;
    }

    /** Hängengebliebene Läufe beenden (PC aus, Claude abgestürzt …). */
    public static function aufraeumen(): void
    {
        self::still(static fn() => Db::run("UPDATE bau_auftraege SET status = 'fehler', fertig_am = NOW(),
                fehler = 'Keine Rückmeldung vom PC — vermutlich ausgeschaltet oder abgebrochen. Einfach neu starten.'
            WHERE status = 'laeuft' AND gestartet_am < NOW() - INTERVAL " . self::HOECHSTENS_MIN . " MINUTE"), null);
    }

    /** Für befehl_holen: wartet etwas? Vor der Migration und bei Not-Aus für alle: nein. */
    public static function wartet(): bool
    {
        return (bool) self::still(static fn() => !Bausperre::alleGestoppt()
            && (int) Db::wert("SELECT COUNT(*) FROM bau_auftraege WHERE status = 'wartet'", [], 0) > 0, false);
    }

    public static function fuerProjekt(int $pid, int $max = 10): array
    {
        self::aufraeumen();
        return self::still(static fn() => Db::all('SELECT * FROM bau_auftraege WHERE project_id = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $max)), [$pid]), []);
    }

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM bau_auftraege WHERE id = ?', [$id]) ?: null;
    }

    /**
     * Für den PC: den ältesten wartenden Auftrag übernehmen, samt allem,
     * was Claude dafür braucht. Der Not-Aus wird beim Abholen noch einmal
     * geprüft — er kann zwischen Knopf und Abholen gezogen worden sein.
     * @return array{ok:bool, auftrag:?array}
     */
    public static function holen(): array
    {
        self::aufraeumen();
        if (Bausperre::alleGestoppt()) { return ['ok' => true, 'auftrag' => null, 'hinweis' => 'Not-Aus: alle Builds gestoppt.']; }
        for ($versuch = 0; $versuch < 5; $versuch++) {
            $a = Db::one("SELECT * FROM bau_auftraege WHERE status = 'wartet' ORDER BY id LIMIT 1");
            if (!$a) { return ['ok' => true, 'auftrag' => null]; }
            $bs = Bausperre::darfBauen((int) $a['project_id']);
            if ($bs['stopp'] || (in_array($a['art'], self::BAUEN, true) && !$bs['ok'])) {
                Db::run("UPDATE bau_auftraege SET status = 'abgebrochen', fertig_am = NOW(), fehler = ? WHERE id = ? AND status = 'wartet'", [mb_substr($bs['grund'], 0, 1000), (int) $a['id']]);
                continue;
            }
            $n = Db::run("UPDATE bau_auftraege SET status = 'laeuft', gestartet_am = NOW() WHERE id = ? AND status = 'wartet'", [(int) $a['id']])->rowCount();
            if ($n === 0) { continue; }   // ein anderer Abruf war schneller
            $daten = self::fuerPc($a);
            if ($daten === null) {
                Db::update('bau_auftraege', (int) $a['id'], ['status' => 'abgebrochen', 'fehler' => 'Projekt nicht mehr da.', 'fertig_am' => date('Y-m-d H:i:s')]);
                continue;
            }
            return ['ok' => true, 'auftrag' => $daten];
        }
        return ['ok' => true, 'auftrag' => null];
    }

    /** Was Claude bekommt: Briefing, Leistungsumfang aus dem Angebot, Hausregeln, alte Website — keine E-Mail, kein Telefon. */
    public static function fuerPc(array $a): ?array
    {
        $pid = (int) $a['project_id'];
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { return null; }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]) ?: [];
        require_once __DIR__ . '/Briefing.php';
        $briefing = trim((string) ($p['briefing'] ?? ''));
        if ($briefing === '') { $briefing = (string) self::still(static fn() => Briefing::speichern($pid), ''); }
        require_once __DIR__ . '/Standard.php';
        $haus = (string) self::still(static fn() => Standard::text(), '');
        $bs = Bausperre::darfBauen($p);
        return [
            'id' => (int) $a['id'], 'art' => (string) $a['art'], 'projekt' => $pid,
            'beschreibung' => self::name((string) $a['art']) . ' · ' . (string) $p['name'],
            'titel' => (string) $p['name'],
            'kunde' => ['firma' => (string) (($k['company'] ?? '') ?: ($k['name'] ?? '')), 'branche' => (string) ($k['industry'] ?? ''),
                        'ort' => trim((string) ($k['city'] ?? '') . ' ' . (string) ($k['country'] ?? '')), 'sprache' => (string) ($k['sprache'] ?? 'de'),
                        'website' => self::alteWebsite((int) ($k['id'] ?? 0))],
            'hinweis' => (string) ($a['hinweis'] ?? ''),
            'briefing' => mb_substr(self::ohneKontakt($briefing), 0, 60000),
            'umfang' => self::umfang($p),
            'hausregeln' => mb_substr($haus, 0, 20000),
            'analyse' => (string) ($p['analyse'] ?? ''),
            'pflichtenheft' => (string) ($p['pflichtenheft'] ?? ''),
            'bau_erlaubt' => $bs['ok'],
            'regel' => 'Nur lesen, analysieren und planen. Nichts bauen, nichts an einer Website ändern, nichts veröffentlichen, niemanden kontaktieren.',
        ];
    }

    /** E-Mail-Adressen und Telefonnummern raus — Claude braucht sie zum Planen nicht. */
    public static function ohneKontakt(string $t): string
    {
        $t = preg_replace('~[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}~iu', '[E-Mail entfernt]', $t) ?? $t;
        return preg_replace_callback('~(?<![\w/:])\+?\d[\d ()/.\-]{7,}\d(?![\w/])~u', static function (array $m): string {
            $z = preg_replace('~\D~', '', $m[0]) ?? '';
            /* Datum (2026-10-06, 06.10.2026) und Zeiträume bleiben; erst ab 9 Ziffern ist es eine Nummer. */
            if (strlen($z) < 9 || preg_match('~^\d{4}-\d{2}-\d{2}|^\d{1,2}\.\d{1,2}\.\d{2,4}~', trim($m[0]))) { return $m[0]; }
            return '[Telefon entfernt]';
        }, $t) ?? $t;
    }

    /** Die bisherige Website des Kunden — aus dem verknüpften Akquise-Betrieb, sonst leer. */
    public static function alteWebsite(int $kundeId): string
    {
        if ($kundeId <= 0) { return ''; }
        $w = (string) self::still(static fn() => Db::wert("SELECT COALESCE(NULLIF(url, ''), domain) FROM akq_firmen WHERE customer_id = ? AND COALESCE(NULLIF(url, ''), domain) IS NOT NULL ORDER BY id DESC LIMIT 1", [$kundeId], ''), '');
        return preg_match('~^https?://~i', $w) ? mb_substr($w, 0, 300) : ($w !== '' ? 'https://' . mb_substr($w, 0, 290) : '');
    }

    /** Leistungsumfang aus dem Angebot (ohne Preise) — Grundlage für den Scope-Schutz. @return list<array{bezeichnung:string,beschreibung:string,menge:int,monatlich:bool}> */
    public static function umfang(array $p): array
    {
        return self::still(static function () use ($p): array {
            $oid = (int) ($p['order_id'] ?? 0);
            $an = Db::one("SELECT id FROM angebote WHERE (project_id = ? OR (order_id IS NOT NULL AND order_id = ?)) AND status IN ('angenommen','gesendet') ORDER BY status = 'angenommen' DESC, id DESC LIMIT 1", [(int) $p['id'], $oid])
               ?: Db::one("SELECT id FROM angebote WHERE customer_id = ? AND status IN ('angenommen','gesendet') ORDER BY status = 'angenommen' DESC, id DESC LIMIT 1", [(int) $p['customer_id']]);
            if (!$an) { return []; }
            return array_map(static fn($r) => ['bezeichnung' => (string) $r['bezeichnung'], 'beschreibung' => (string) $r['beschreibung'], 'menge' => (int) $r['menge'], 'monatlich' => (int) $r['monatlich'] === 1],
                Db::all('SELECT bezeichnung, beschreibung, menge, monatlich FROM angebot_positionen WHERE angebot_id = ? ORDER BY sortierung, id', [(int) $an['id']]));
        }, []);
    }

    /** Für den PC: fertig (mit Markdown) oder gescheitert. */
    public static function melden(array $d): array
    {
        $id = (int) ($d['id'] ?? 0);
        $a = Db::one('SELECT * FROM bau_auftraege WHERE id = ?', [$id]);
        if (!$a) { return ['ok' => false, 'hinweis' => 'Auftrag unbekannt.']; }
        if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
        $ok = !empty($d['ok']);
        $text = (string) ($d['text'] ?? '');
        $text = str_replace("\r\n", "\n", $text);
        $text = trim(preg_replace('~<\s*(script|iframe|style|object|embed)\b[^>]*>.*?<\s*/\s*\1\s*>~is', '', $text) ?? '');
        if ($ok && mb_strlen($text) < 200) { $ok = false; $d['fehler'] = 'Claude hat zu wenig geliefert (unter 200 Zeichen).'; }
        $p = Db::one('SELECT id, name, customer_id FROM projects WHERE id = ?', [(int) $a['project_id']]) ?: ['id' => 0, 'name' => '?', 'customer_id' => null];
        if ($ok) {
            Db::update('bau_auftraege', $id, ['status' => 'fertig', 'ergebnis' => mb_substr($text, 0, self::MAX_ERGEBNIS), 'fehler' => null, 'fertig_am' => date('Y-m-d H:i:s')]);
        } else {
            $f = mb_substr(trim(strip_tags((string) ($d['fehler'] ?? $d['text'] ?? ''))) ?: 'Nicht geklappt.', 0, 1000);
            Db::update('bau_auftraege', $id, ['status' => 'fehler', 'fehler' => $f, 'fertig_am' => date('Y-m-d H:i:s')]);
        }
        self::still(static fn() => Events::melden('bau_auftrag_fertig',
            ($ok ? self::name((string) $a['art']) . ' fertig: ' : self::name((string) $a['art']) . ' nicht geklappt: ') . (string) $p['name'],
            $ok ? 'gut' : 'info',
            $ok ? 'Entwurf von Claude — bitte lesen und übernehmen.' : (string) ($d['fehler'] ?? ''),
            'projekte/' . (int) $p['id'] . '#bauen'), null);
        return ['ok' => true];
    }

    /**
     * Markdown von Claude lesbar machen — bewusst klein: Überschriften,
     * Listen, Fett, Tabellenzeilen als Text. Alles wird zuerst maskiert;
     * es entsteht nur HTML, das hier steht.
     */
    public static function alsHtml(string $md): string
    {
        $h = static fn(string $t): string => preg_replace('~\*\*(.+?)\*\*~u', '<b>$1</b>', htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
        $raus = []; $liste = null;
        $zu = static function () use (&$raus, &$liste): void { if ($liste !== null) { $raus[] = '</' . $liste . '>'; $liste = null; } };
        foreach (preg_split('~\R~u', $md) ?: [] as $z) {
            $t = rtrim($z);
            if (preg_match('~^(#{1,4})\s+(.+)$~u', $t, $m)) { $zu(); $n = min(5, strlen($m[1]) + 2); $raus[] = '<h' . $n . ' style="margin:14px 0 4px">' . $h($m[2]) . '</h' . $n . '>'; continue; }
            if (preg_match('~^\s*[-*•]\s+(.+)$~u', $t, $m)) { if ($liste !== 'ul') { $zu(); $raus[] = '<ul style="margin:4px 0 4px 20px;padding:0">'; $liste = 'ul'; } $raus[] = '<li>' . $h($m[1]) . '</li>'; continue; }
            if (preg_match('~^\s*\d+[.)]\s+(.+)$~u', $t, $m)) { if ($liste !== 'ol') { $zu(); $raus[] = '<ol style="margin:4px 0 4px 22px;padding:0">'; $liste = 'ol'; } $raus[] = '<li>' . $h($m[1]) . '</li>'; continue; }
            $zu();
            if (trim($t) === '' || preg_match('~^\s*\|?\s*:?-{3,}~', $t)) { continue; }
            $raus[] = '<p style="margin:4px 0">' . $h($t) . '</p>';
        }
        $zu();
        return implode("\n", $raus);
    }

    /**
     * Ein Mensch hat das Ergebnis gelesen und übernimmt es ans Projekt.
     * Erst diese Fassung gilt (Phase 7: der Builder baut gegen das übernommene Pflichtenheft).
     */
    public static function uebernehmen(int $id, string $wer): void
    {
        $a = Db::one('SELECT * FROM bau_auftraege WHERE id = ?', [$id]);
        if (!$a || $a['status'] !== 'fertig' || trim((string) $a['ergebnis']) === '') { throw new RuntimeException('Nur fertige Ergebnisse lassen sich übernehmen.'); }
        $spalte = $a['art'] === 'pflichtenheft' ? 'pflichtenheft' : 'analyse';
        $pid = (int) $a['project_id'];
        $vorher = (string) Db::wert('SELECT ' . $spalte . ' FROM projects WHERE id = ?', [$pid], '');
        Db::update('projects', $pid, [$spalte => (string) $a['ergebnis'], $spalte . '_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('bau_' . $spalte . '_uebernommen', 'projects', $pid, ['zeichen' => mb_strlen($vorher)], ['zeichen' => mb_strlen((string) $a['ergebnis']), 'auftrag' => $id, 'von' => $wer]);
    }
}
