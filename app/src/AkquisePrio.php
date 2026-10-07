<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   AkquisePrio.php — Akquise-Priorität je Betrieb (Akquise-CRM Modul B, 06.10.2026).

   ZWEI ZAHLEN, ZWEI FRAGEN
     Digital-Chance (akq_firmen.score, AkquiseScore): Wie schwach ist der
       Webauftritt — wie viel könnte Vecom verbessern? Bleibt, wie er ist.
     Priorität (prio_score, hier): Wen sollte man JETZT ansprechen? Dafür
       zählt mehr als die Website: ein belegter Kontaktweg, eine dokumentierte
       Freigabe, gezeigtes Interesse, eine bekannte Ansprechperson — und was
       gegen ein Ansprechen spricht (Sperre, lange keine Reaktion).

   NUR BELEGTES
   Jeder Grund steht nur da, wenn ihn ein Datenfeld trägt. Gute Bewertungen,
   „aktiv auf Social Media“, Wachstum oder Stellenanzeigen werden NICHT
   behauptet, solange sie niemand gemessen hat — ein hinterlegtes Profil heißt
   „Instagram-Profil vorhanden“, nicht „aktiv“. Die Gründe tragen die Texte,
   die Uwe im CRM liest; sie gehen nie ungeprüft in eine Nachricht.

   STUFEN
     jetzt   🔥 Jetzt kontaktieren   ≥ 75, oder offenes Interesse (Antwort, Termin, Check)
     gut     🟢 Gute Chance          ≥ 55
     spaeter 🟡 Später prüfen        ≥ 35
     niedrig ⚪ Niedrige Priorität    < 35
     nie     🔴 Nicht kontaktieren   gesperrt, abgelehnt, Kunde, Sperrart gesetzt

   Gerechnet wird gebündelt im Cron (Regel „akquise_prio“ im Automation Center)
   und sofort nach Änderungen am Betrieb — nie beim Seitenaufruf für eine Liste.
   ========================================================================== */
final class AkquisePrio
{
    public const STUFEN = [
        'jetzt'   => ['🔥', 'Jetzt kontaktieren'],
        'gut'     => ['🟢', 'Gute Chance'],
        'spaeter' => ['🟡', 'Später prüfen'],
        'niedrig' => ['⚪', 'Niedrige Priorität'],
        'nie'     => ['🔴', 'Nicht kontaktieren'],
    ];
    public const POSITIV = ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST'];
    /** So viele Betriebe je Cron-Lauf. */
    public const JE_LAUF = 300;

    /**
     * @param array<string,mixed> $f   Zeile aus akq_firmen
     * @param array<string,mixed> $k   Kontext (kontext()): positiv, offen_positiv, checks, termine, gesendet, letzter
     * @return array{score:int, stufe:string, gruende:list<string>}
     */
    public static function berechnen(array $f, array $k = []): array
    {
        $nie = (int) ($f['gesperrt'] ?? 0) === 1 || (string) ($f['sperr_art'] ?? '') !== ''
            || in_array((string) ($f['kontakt_status'] ?? ''), ['abgelehnt', 'gesperrt', 'kunde'], true) || (int) ($f['bestandskunde'] ?? 0) === 1
            || (string) ($f['markierung'] ?? '') === 'kunde';
        if ($nie) {
            require_once __DIR__ . '/AkquiseCrm.php';
            $art = (string) ($f['sperr_art'] ?? '');
            $warum = match (true) {
                $art !== '' => 'Gesperrt: ' . (AkquiseCrm::SPERR_ARTEN[$art] ?? $art),
                (int) ($f['bestandskunde'] ?? 0) === 1 || ($f['kontakt_status'] ?? '') === 'kunde' || ($f['markierung'] ?? '') === 'kunde' => 'Schon Kunde',
                ($f['kontakt_status'] ?? '') === 'abgelehnt' => 'Kein Interesse geäußert',
                default => 'Gesperrt',
            };
            return ['score' => 0, 'stufe' => 'nie', 'gruende' => [$warum]];
        }
        $p = [];   // [punkte, grund]
        /* Interesse schlägt alles: wer geantwortet, gebucht oder selbst geprüft hat, wartet auf uns. */
        if ((int) ($k['offen_positiv'] ?? 0) > 0) { $p[] = [40, 'Positive Antwort wartet auf Bearbeitung']; }
        elseif ((int) ($k['positiv'] ?? 0) > 0) { $p[] = [20, 'Hat Interesse gezeigt']; }
        if ((int) ($k['termine'] ?? 0) > 0) { $p[] = [20, 'Termin gebucht']; }
        if ((int) ($k['checks'] ?? 0) > 0) { $p[] = [15, 'Hat den Website-Check selbst gemacht']; }

        /* Digital-Chance: Website fehlt oder ist schwach. */
        $ohneWeb = ($f['audit_status'] ?? '') === 'keine_website';
        if ($ohneWeb) { $p[] = [40, 'Keine eigene Website']; }
        elseif ($f['score'] !== null && ($f['audit_status'] ?? '') === 'fertig') {
            $top = json_decode((string) ($f['top_probleme'] ?? ''), true);
            $titel = is_array($top) && $top ? (string) $top[0] : '';
            $pk = (int) round((int) $f['score'] * 0.45);
            if ($pk > 0) { $p[] = [$pk, 'Digital-Chance ' . (int) $f['score'] . '/100' . ($titel !== '' ? ': ' . $titel : '')]; }
        }

        /* Kontaktweg und Freigabe */
        if (trim((string) ($f['einwilligung'] ?? '')) !== '' || (int) ($f['email_send_allowed'] ?? 0) === 1) { $p[] = [15, 'Kommunikationsfreigabe dokumentiert']; }
        if (trim((string) ($f['ansprechpartner'] ?? '')) !== '') { $p[] = [8, 'Ansprechperson bekannt' . (trim((string) ($f['position'] ?? '')) !== '' ? ' (' . trim((string) $f['position']) . ')' : '')]; }
        $wege = array_filter(['E-Mail' => $f['email'] ?? '', 'Telefon' => ($f['telefon'] ?? '') ?: ($f['mobil'] ?? ''), 'WhatsApp' => $f['whatsapp'] ?? '']);
        if ($wege) { $p[] = [min(12, 4 * count($wege)), 'Kontaktweg vorhanden: ' . implode(', ', array_keys($wege))]; }

        /* Sichtbarkeit — nur, dass etwas da ist, nicht wie gut. */
        if (trim((string) ($f['google_profil'] ?? '')) !== '') { $p[] = [4, 'Google-Unternehmensprofil vorhanden']; }
        $soz = array_keys(array_filter(['Facebook' => $f['facebook'] ?? '', 'Instagram' => $f['instagram'] ?? '', 'LinkedIn' => $f['linkedin'] ?? '', 'Xing' => $f['xing'] ?? ''], static fn($v) => trim((string) $v) !== ''));
        if ($soz) { $p[] = [min(6, 3 * count($soz)), implode(', ', $soz) . '-Profil vorhanden']; }
        if (trim((string) ($f['oeffnungszeiten'] ?? '')) !== '') { $p[] = [2, 'Öffnungszeiten auf der Website gepflegt']; }
        if ((int) ($f['filiale_von'] ?? 0) > 0) { $p[] = [3, 'Teil eines Betriebs mit mehreren Standorten']; }

        /* Was dagegen spricht */
        $abzug = 0; $gegen = [];
        $letzter = (string) ($k['letzter'] ?? ($f['letzter_kontakt_am'] ?? ''));
        if ((int) ($k['gesendet'] ?? 0) > 0 && (int) ($k['positiv'] ?? 0) === 0 && $letzter !== '' && strtotime($letzter) < strtotime('-30 days')) {
            $abzug += 15; $gegen[] = 'Seit über 30 Tagen keine Reaktion';
        } elseif ((int) ($k['gesendet'] ?? 0) > 0 && (int) ($k['positiv'] ?? 0) === 0 && $letzter !== '' && strtotime($letzter) > strtotime('-3 days')) {
            $abzug += 10; $gegen[] = 'Gerade erst kontaktiert — Antwort abwarten';
        }
        if (!$wege) { $abzug += 10; $gegen[] = 'Kein Kontaktweg bekannt'; }

        /* Kunden finden (07.10.2026): Neueröffnung, Saison, wie gewonnene Kunden, lernende Branchenzahlen, fremde Agentur. */
        try {
            require_once __DIR__ . '/KundenFinden.php';
            $zu = KundenFinden::prioZusatz($f);
            foreach ($zu['plus'] as $x) { $p[] = $x; }
            foreach ($zu['minus'] as [$pk, $g]) { $abzug += $pk; $gegen[] = $g; }
        } catch (Throwable $e) { /* Zusatz ist Beiwerk */ }

        usort($p, static fn($a, $b) => $b[0] <=> $a[0]);
        $score = max(0, min(100, array_sum(array_column($p, 0)) - $abzug));
        $heiss = (int) ($k['offen_positiv'] ?? 0) > 0 || (int) ($k['termine'] ?? 0) > 0 || (int) ($k['checks'] ?? 0) > 0;
        $stufe = $heiss || $score >= 75 ? 'jetzt' : ($score >= 55 ? 'gut' : ($score >= 35 ? 'spaeter' : 'niedrig'));
        return ['score' => $score, 'stufe' => $stufe, 'gruende' => array_merge(array_column($p, 1), array_map(static fn($g) => '− ' . $g, $gegen))];
    }

    /** „Warum ist dieser Betrieb interessant?“ — die drei stärksten belegten Gründe in einem Satz (07.10.2026: drei statt zwei). */
    public static function warum(array $f): string
    {
        $g = json_decode((string) ($f['prio_gruende'] ?? ''), true);
        if (!is_array($g) || !$g) { return ''; }
        $pos = array_values(array_filter($g, static fn($x) => !str_starts_with((string) $x, '− ')));
        return $pos ? implode(' · ', array_slice($pos, 0, 3)) . '.' : '';
    }

    /** Kontext für viele Betriebe in vier Abfragen statt vier je Betrieb. @return array<int,array<string,mixed>> */
    public static function kontext(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) { return []; }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $k = array_fill_keys($ids, ['positiv' => 0, 'offen_positiv' => 0, 'checks' => 0, 'termine' => 0, 'gesendet' => 0, 'letzter' => null]);
        $pos = "'" . implode("','", self::POSITIV) . "'";
        foreach (Db::all("SELECT firma_id, COUNT(*) n, SUM(erledigt = 0) o FROM akq_antworten WHERE klasse IN ($pos) AND firma_id IN ($in) GROUP BY firma_id", $ids) as $r) {
            $k[(int) $r['firma_id']]['positiv'] = (int) $r['n']; $k[(int) $r['firma_id']]['offen_positiv'] = (int) $r['o'];
        }
        foreach (Db::all("SELECT firma_id, COUNT(*) n, MAX(created_at) l FROM akq_versand WHERE status IN ('gesendet','von_hand') AND firma_id IN ($in) GROUP BY firma_id", $ids) as $r) {
            $k[(int) $r['firma_id']]['gesendet'] = (int) $r['n']; $k[(int) $r['firma_id']]['letzter'] = $r['l'];
        }
        try {
            foreach (Db::all("SELECT firma_id, COUNT(*) n FROM akq_checks WHERE firma_id IN ($in) GROUP BY firma_id", $ids) as $r) { $k[(int) $r['firma_id']]['checks'] = (int) $r['n']; }
            foreach (Db::all("SELECT firma_id, COUNT(*) n FROM akq_termine WHERE status = 'gebucht' AND beginn >= NOW() AND firma_id IN ($in) GROUP BY firma_id", $ids) as $r) { $k[(int) $r['firma_id']]['termine'] = (int) $r['n']; }
        } catch (Throwable $e) { /* ältere Datenbank ohne Checks/Termine */ }
        return $k;
    }

    /** Einen Betrieb sofort neu rechnen (nach Änderung). */
    public static function aktualisieren(int $id): ?array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return null; }
        $r = self::berechnen($f, self::kontext([$id])[$id] ?? []);
        self::speichern($id, $r, self::kontext([$id])[$id]['letzter'] ?? null);
        return $r;
    }

    /** Cron: die am längsten nicht gerechneten zuerst. @return array{gerechnet:int} */
    public static function lauf(int $max = self::JE_LAUF): array
    {
        $zeilen = Db::all('SELECT * FROM akq_firmen ORDER BY prio_am IS NOT NULL, prio_am LIMIT ' . max(1, min(2000, $max)));
        $kx = self::kontext(array_column($zeilen, 'id'));
        foreach ($zeilen as $f) {
            $kk = $kx[(int) $f['id']] ?? [];
            self::speichern((int) $f['id'], self::berechnen($f, $kk), $kk['letzter'] ?? null);
        }
        return ['gerechnet' => count($zeilen)];
    }

    private static function speichern(int $id, array $r, ?string $letzter): void
    {
        Db::run('UPDATE akq_firmen SET prio_score = ?, prio_stufe = ?, prio_gruende = ?, prio_am = NOW(), letzter_kontakt_am = COALESCE(?, letzter_kontakt_am), updated_at = updated_at WHERE id = ?',
            [$r['score'], $r['stufe'], json_encode($r['gruende'], JSON_UNESCAPED_UNICODE), $letzter, $id]);
    }
}
