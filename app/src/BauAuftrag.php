<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Bausperre.php';
require_once __DIR__ . '/BauPruefung.php';

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
        /* AutoBuild Phase 7 (06.10.2026): Builder und Reviewer. */
        'bauen'         => ['Website bauen', 'Claude baut aus dem übernommenen Pflichtenheft eine neue Fassung — danach Tests und Review automatisch.'],
        'review'        => ['Review', 'Ein zweiter Claude-Lauf prüft eine Fassung gegen Pflichtenheft und Tests.'],
        /* AutoBuild Phase 8 (06.10.2026): Vorschlag zur Einordnung der Kundenwünsche — entschieden wird von Hand. */
        'wuensche'      => ['Wünsche einordnen', 'Claude schlägt für jeden neuen Kundenwunsch vor: im Umfang, Zusatz oder unklar — entscheiden tust du.'],
    ];
    /** Was sich in der Verwaltung von Hand anstoßen lässt (Karte + Wunschliste). */
    public const KNOPF = ['analyse', 'pflichtenheft', 'bauen', 'wuensche'];
    /** Diese Arten stehen als Knopf auf der Karte; „review“ läuft nach jedem Bau von selbst (oder je Fassung). */
    public const STARTBAR = ['analyse', 'pflichtenheft', 'bauen'];
    /** Arten, die bauen oder ändern — brauchen Annahme + Anzahlung (Bausperre). */
    public const BAUEN = ['bauen'];
    /** Nachbesserungsrunden Builder → Reviewer, dann entscheidet ein Mensch. */
    public const MAX_VERSUCHE = 3;
    /** So viel Quelltext bekommt Claude von einer Fassung zu sehen. */
    public const MAX_QUELLTEXT = 300_000;
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
    public static function anlegen(int $pid, string $art, string $wer, string $hinweis = '', array $parameter = [], int $versuch = 1, bool $auto = false): int|string
    {
        if (!isset(self::ARTEN[$art])) { return 'Unbekannte Auftragsart.'; }
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { return 'Projekt nicht gefunden.'; }
        $bs = Bausperre::darfBauen($p);
        if ($bs['stopp']) { return $bs['grund'] . ' Es wird kein Auftrag angelegt.'; }
        if (in_array($art, self::BAUEN, true) && !$bs['ok']) { return $bs['grund']; }
        if ($art === 'bauen' && trim((string) ($p['pflichtenheft'] ?? '')) === '') { return 'Erst ein Pflichtenheft übernehmen — gebaut wird nur dagegen.'; }
        if (isset($parameter['version_id'])) {
            require_once __DIR__ . '/Versionen.php';
            $pv = Versionen::laden((int) $parameter['version_id']);
            if (!$pv || (int) $pv['project_id'] !== $pid) { return 'Diese Fassung gehört nicht zu diesem Projekt.'; }
        } elseif ($art === 'review') { return 'Welche Fassung soll geprüft werden?'; }
        if ($art === 'wuensche') {
            require_once __DIR__ . '/Wunsch.php';
            if (!Wunsch::neue($pid)) { return 'Es gibt keine neuen Wünsche zum Einordnen.'; }
        }
        self::aufraeumen();
        $offen = Db::one("SELECT status FROM bau_auftraege WHERE project_id = ? AND art = ? AND status IN ('wartet','laeuft') LIMIT 1", [$pid, $art]);
        if ($offen) { return self::name($art) . ($offen['status'] === 'laeuft' ? ' läuft gerade schon.' : ' wartet schon auf deinen PC.'); }
        if (!$auto && self::heute() >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Bau-Aufträge gelaufen — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $hinweis = mb_substr(trim(strip_tags($hinweis)), 0, 500);
        $hinweis = mb_substr($hinweis, 0, $auto ? 500 : 500);
        $id = (int) Db::insert('bau_auftraege', ['project_id' => $pid, 'art' => $art, 'hinweis' => $hinweis !== '' ? $hinweis : null, 'von' => mb_substr($wer, 0, 120),
            'parameter' => $parameter ? json_encode($parameter) : null, 'versuch' => max(1, min(9, $versuch))]);
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
        require_once __DIR__ . '/Wunsch.php';
        Wunsch::zurueck($id);   // Phase 8: Wünsche zurück in die Liste
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
                require_once __DIR__ . '/Wunsch.php';
                Wunsch::zurueck((int) $a['id']);
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
        $param = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
        $zusatz = [];
        if (in_array($a['art'], ['bauen', 'review'], true) && !empty($param['version_id'])) {
            require_once __DIR__ . '/Versionen.php';
            $v = Versionen::laden((int) $param['version_id']);
            if ($v) {
                $zusatz['fassung'] = ['nummer' => (int) $v['nummer'], 'dateien' => Versionen::quelltext((int) $v['id'], self::MAX_QUELLTEXT),
                    'tests' => json_decode((string) ($v['tests'] ?? ''), true) ?: [], 'review' => (string) ($v['review_text'] ?? '')];
            }
        }
        if ($a['art'] === 'wuensche' || !empty($param['wunsch_ids'])) {
            require_once __DIR__ . '/Wunsch.php';
            $zusatz['wuensche'] = $a['art'] === 'wuensche' ? Wunsch::neue($pid)
                : array_map(static fn($w) => ['id' => (int) $w['id'], 'text' => (string) $w['text']],
                    Db::all('SELECT id, text FROM projekt_wuensche WHERE project_id = ? AND id IN (' . implode(',', array_map('intval', (array) $param['wunsch_ids'])) . ')', [$pid]));
        }
        if ($a['art'] === 'bauen') {
            /* Die Website zeigt die Geschäftskontakte des Kunden — nur beim Bauen, nie beim Planen. */
            $zusatz['kontakt'] = ['telefon' => (string) ($k['phone'] ?? ''), 'email' => (string) ($k['email'] ?? ''),
                                  'adresse' => trim((string) ($k['street'] ?? '') . ', ' . (string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? ''), ', ')];
        }
        $regel = match ((string) $a['art']) {
            'bauen'  => 'Baue die Website als statische Dateien und liefere sie zurück. Nichts veröffentlichen, nichts hochladen, niemanden kontaktieren — die Verwaltung legt eine neue Fassung an, testet sie, und erst ein Mensch schaltet sie frei.',
            'review' => 'Nur lesen und beurteilen. Nichts ändern, nichts veröffentlichen.',
            'wuensche' => 'Nur vorschlagen. Du ordnest nichts verbindlich ein — das entscheidet ein Mensch, weil davon abhängt, ob der Kunde zahlt.',
            default  => 'Nur lesen, analysieren und planen. Nichts bauen, nichts an einer Website ändern, nichts veröffentlichen, niemanden kontaktieren.',
        };
        return $zusatz + [
            'versuch' => (int) ($a['versuch'] ?? 1), 'max_versuche' => self::MAX_VERSUCHE,
            'grenzen' => ['dateien' => BauPruefung::MAX_DATEIEN, 'bytes' => 1_500_000, 'endungen' => BauPruefung::TEXT],
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
            'regel' => $regel,
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
        $p = Db::one('SELECT id, name, customer_id FROM projects WHERE id = ?', [(int) $a['project_id']]) ?: ['id' => 0, 'name' => '?', 'customer_id' => null];
        $art = (string) $a['art'];
        $meldung = 'Entwurf von Claude — bitte lesen und übernehmen.';
        $link = 'projekte/' . (int) $p['id'] . '#bauen';
        $mindest = in_array($art, ['bauen', 'wuensche'], true) ? 20 : 200;
        $param = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
        if ($ok && mb_strlen($text) < $mindest) { $ok = false; $d['fehler'] = 'Claude hat zu wenig geliefert (unter ' . $mindest . ' Zeichen).'; }

        /* Phase 7: Der Builder liefert Dateien → neue Fassung, Tests, Review von selbst. */
        if ($ok && $art === 'bauen') {
            try { $vid = self::fassungAnlegen($a, is_array($d['dateien'] ?? null) ? $d['dateien'] : [], $text); }
            catch (RuntimeException $e) { $ok = false; $d['fehler'] = $e->getMessage(); }
            if ($ok) {
                require_once __DIR__ . '/Versionen.php';
                $v = Versionen::laden($vid);
                $text = 'V' . (int) $v['nummer'] . ' gebaut' . ((int) $v['tests_ok'] === 1 ? ', Tests bestanden' : ', Tests mit Mängeln') . ".\n\n" . $text;
                $meldung = 'V' . (int) $v['nummer'] . ' ist gebaut — das Review läuft jetzt von selbst.';
                $link = 'projekte/' . (int) $p['id'] . '#versionen';
                self::anlegen((int) $p['id'], 'review', 'Claude (automatisch)', '', ['version_id' => $vid], (int) $a['versuch'], true);
            }
        }
        /* Phase 8: Vorschläge zur Einordnung der Wünsche — nur Vorschläge. */
        if ($ok && $art === 'wuensche') {
            require_once __DIR__ . '/Wunsch.php';
            $nv = Wunsch::vorschlaegeSpeichern((int) $p['id'], is_array($d['vorschlaege'] ?? null) ? $d['vorschlaege'] : []);
            $meldung = $nv . ' Wünsche mit Vorschlag — bitte selbst einordnen.';
            $link = 'projekte/' . (int) $p['id'] . '#wuensche';
        }
        /* Phase 7: Der Reviewer urteilt — bei Mängeln baut Claude nach, höchstens MAX_VERSUCHE Runden. */
        if ($ok && $art === 'review') {
            require_once __DIR__ . '/Versionen.php';
            $v = Versionen::laden((int) ($param['version_id'] ?? 0));
            if (!$v) { $ok = false; $d['fehler'] = 'Fassung nicht mehr da.'; }
            else {
                $urteil = ($d['urteil'] ?? '') === 'bestanden' && (int) $v['tests_ok'] === 1 ? 'bestanden' : 'nachbessern';
                $maengel = array_values(array_filter(array_map(static fn($m) => mb_substr(trim(strip_tags((string) $m)), 0, 300), (array) ($d['maengel'] ?? [])), static fn($m) => $m !== ''));
                foreach (json_decode((string) ($v['tests'] ?? ''), true) ?: [] as $tt) { if (!empty($tt['schwer']) && empty($tt['ok'])) { $maengel[] = 'Test: ' . $tt['name'] . ($tt['detail'] !== '' ? ' — ' . $tt['detail'] : ''); } }
                Db::update('projekt_versionen', (int) $v['id'], ['review_urteil' => $urteil, 'review_text' => mb_substr($text, 0, self::MAX_ERGEBNIS), 'review_am' => date('Y-m-d H:i:s')]);
                Events::pruefspur('version_review', 'projekt_versionen', (int) $v['id'], [], ['urteil' => $urteil, 'versuch' => (int) $a['versuch'], 'maengel' => count($maengel)]);
                $link = 'projekte/' . (int) $p['id'] . '#versionen';
                if ($urteil === 'bestanden') {
                    $meldung = 'V' . (int) $v['nummer'] . ': Tests und Review bestanden — jetzt auf die Testfassung und selbst ansehen.';
                } elseif ((int) $a['versuch'] < self::MAX_VERSUCHE && (int) $v['auftrag_id'] > 0) {
                    /* Wünsche, die in dieser Runde gebaut wurden, laufen mit (Phase 8). */
                    $wIds = (array) ((json_decode((string) Db::wert('SELECT parameter FROM bau_auftraege WHERE id = ?', [(int) $v['auftrag_id']], ''), true) ?: [])['wunsch_ids'] ?? []);
                    $nach = self::anlegen((int) $p['id'], 'bauen', 'Claude (automatisch)', mb_substr("Nachbessern (Runde " . ((int) $a['versuch'] + 1) . "):\n- " . implode("\n- ", array_slice($maengel, 0, 12)), 0, 500),
                        ['version_id' => (int) $v['id']] + ($wIds ? ['wunsch_ids' => array_map('intval', $wIds)] : []), (int) $a['versuch'] + 1, true);
                    $meldung = 'V' . (int) $v['nummer'] . ': Review fand ' . count($maengel) . ' Mängel — Claude bessert nach' . (is_int($nach) ? ' (Runde ' . ((int) $a['versuch'] + 1) . ').' : ': ' . $nach);
                } else {
                    $meldung = 'V' . (int) $v['nummer'] . ': nach ' . (int) $a['versuch'] . ' Runde(n) noch Mängel — bitte selbst ansehen und entscheiden.';
                }
            }
        }

        if ($ok) {
            Db::update('bau_auftraege', $id, ['status' => 'fertig', 'ergebnis' => mb_substr($text, 0, self::MAX_ERGEBNIS), 'fehler' => null, 'fertig_am' => date('Y-m-d H:i:s')]);
        } else {
            $f = mb_substr(trim(strip_tags((string) ($d['fehler'] ?? $d['text'] ?? ''))) ?: 'Nicht geklappt.', 0, 1000);
            Db::update('bau_auftraege', $id, ['status' => 'fehler', 'fehler' => $f, 'fertig_am' => date('Y-m-d H:i:s')]);
            if ($art === 'bauen' && !empty($param['wunsch_ids'])) { require_once __DIR__ . '/Wunsch.php'; Wunsch::zurueck($id); }
        }
        self::still(static fn() => Events::melden('bau_auftrag_fertig',
            ($ok ? self::name($art) . ' fertig: ' : self::name($art) . ' nicht geklappt: ') . (string) $p['name'],
            $ok ? 'gut' : 'info',
            $ok ? $meldung : (string) ($d['fehler'] ?? ''),
            $link), null);
        return ['ok' => true];
    }

    /**
     * Die Lieferung des Builders zur Fassung machen: Pfade und Endungen
     * prüfen, Binärdateien der Ausgangsfassung (Bilder, Schriften) mitnehmen,
     * ZIP ablegen, V-Nummer vergeben, Tests laufen lassen.
     * @param list<array{pfad?:string,inhalt?:string}> $lieferung
     */
    public static function fassungAnlegen(array $a, array $lieferung, string $zusammenfassung): int
    {
        require_once __DIR__ . '/Versionen.php';
        require_once __DIR__ . '/Ablage.php';
        $pid = (int) $a['project_id'];
        Bausperre::pruefen($pid);
        $dateien = [];
        $summe = 0;
        foreach ($lieferung as $f) {
            $pfad = BauPruefung::pfadOk((string) ($f['pfad'] ?? ''));
            if ($pfad === null || !in_array(strtolower(pathinfo($pfad, PATHINFO_EXTENSION)), BauPruefung::TEXT, true)) {
                throw new RuntimeException('Unzulässige Datei in der Lieferung: ' . mb_substr((string) ($f['pfad'] ?? ''), 0, 80));
            }
            $inhalt = (string) ($f['inhalt'] ?? '');
            $summe += strlen($inhalt);
            $dateien[$pfad] = $inhalt;
        }
        if (!$dateien) { throw new RuntimeException('Claude hat keine Dateien geliefert.'); }
        if (count($dateien) > BauPruefung::MAX_DATEIEN || $summe > 1_500_000) { throw new RuntimeException('Die Lieferung ist zu groß (' . count($dateien) . ' Dateien, ' . round($summe / 1024) . ' KB).'); }
        $param = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
        if (!empty($param['version_id'])) {   // Nachbessern: Bilder und Schriften der Ausgangsfassung bleiben
            foreach (Versionen::dateien((int) $param['version_id'], false) as $pfad => $inhalt) {
                if (!isset($dateien[$pfad]) && !in_array(strtolower(pathinfo($pfad, PATHINFO_EXTENSION)), BauPruefung::TEXT, true)) { $dateien[$pfad] = $inhalt; }
            }
        }
        $tests = BauPruefung::pruefen($dateien);
        $p = Db::one('SELECT customer_id, name FROM projects WHERE id = ?', [$pid]);
        $zip = sys_get_temp_dir() . '/vecom-bau-' . bin2hex(random_bytes(6)) . '.zip';
        $z = new ZipArchive();
        if ($z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('ZIP nicht anlegbar.'); }
        ksort($dateien);
        foreach ($dateien as $pfad => $inhalt) { $z->addFromString($pfad, $inhalt); }
        $z->close();
        try {
            $nr = (int) Db::wert('SELECT COALESCE(MAX(nummer), 0) + 1 FROM projekt_versionen WHERE project_id = ?', [$pid], 1);
            $fid = Ablage::ausDatei($zip, 'claude-v' . $nr . '-' . date('Y-m-d-Hi') . '.zip', $pid, (int) $p['customer_id'], 'werkstatt', 50 * 1024 * 1024, 'paket');
        } finally { @unlink($zip); }
        $vid = Versionen::erfassen($pid, $fid, 'ki', mb_substr('Runde ' . (int) $a['versuch'] . ': ' . preg_replace('~\s+~', ' ', $zusammenfassung), 0, 300));
        Db::update('projekt_versionen', $vid, ['tests' => json_encode($tests, JSON_UNESCAPED_UNICODE), 'tests_ok' => BauPruefung::bestanden($tests) ? 1 : 0, 'auftrag_id' => (int) $a['id']]);
        if (!empty($param['wunsch_ids'])) { require_once __DIR__ . '/Wunsch.php'; Wunsch::fassungGebaut((array) $param['wunsch_ids'], $vid, $pid); }
        return $vid;
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
        if (!in_array($a['art'], ['analyse', 'pflichtenheft'], true)) { throw new RuntimeException('Übernehmen gibt es nur für Analyse und Pflichtenheft — Fassungen gehen über Testfassung und „geprüft“.'); }
        $spalte = $a['art'] === 'pflichtenheft' ? 'pflichtenheft' : 'analyse';
        $pid = (int) $a['project_id'];
        $vorher = (string) Db::wert('SELECT ' . $spalte . ' FROM projects WHERE id = ?', [$pid], '');
        Db::update('projects', $pid, [$spalte => (string) $a['ergebnis'], $spalte . '_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('bau_' . $spalte . '_uebernommen', 'projects', $pid, ['zeichen' => mb_strlen($vorher)], ['zeichen' => mb_strlen((string) $a['ergebnis']), 'auftrag' => $id, 'von' => $wer]);
    }
}
