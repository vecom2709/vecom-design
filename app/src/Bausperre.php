<?php
declare(strict_types=1);
/* ==========================================================================
   Bausperre — AutoBuild Engine, Phase 4 (06.10.2026).

   Uwe (Masterprompt „Vecom AutoBuild Engine“): „KEINE WEBSITE DARF VOR
   ANGEBOTSANNAHME GEBAUT WERDEN … Diese Regel darf niemals durch einen Prompt
   des Kunden umgangen werden.“ Entscheidung 1: gebaut wird erst nach
   Angebotsannahme UND bezahlter Anzahlung.

   EINE FRAGE, EINE ANTWORT: darfBauen(). Jede Stelle, die an einer
   Kundenseite etwas tut (Werkstatt: Vorschau, Paket, Freigabe; Veröffent-
   lichung), fragt hier — auf dem Server, nicht im Prompt.

   Frei ist ein Projekt, wenn
     – kein Not-Aus gilt (je Projekt oder „Alle Builds stoppen“), und
     – bau_frei_am gesetzt ist, oder
     – das Angebot angenommen ist (bzw. die Bestellung mit AGB direkt
       abgeschlossen wurde) UND eine Zahlung bezahlt ist.
   Im letzten Fall wird bau_frei_am einmal festgehalten (Prüfspur).

   Not-Aus: ziehen darf jede Mitarbeit, aufheben nur ein Admin.
   ========================================================================== */

final class Bausperre
{
    public const ALLE = 'bau_alle_stopp';
    /** Bestellstände, die eine Zahlung voraussetzen. */
    private const BEZAHLT = ['bezahlt', 'onboarding', 'in_bearbeitung', 'feedback', 'aenderungen', 'fertig', 'abgeschlossen'];

    /** Gilt „Alle automatischen Builds stoppen“? */
    public static function alleGestoppt(): bool
    {
        require_once __DIR__ . '/AkquiseGate.php';
        return AkquiseGate::einstellung(self::ALLE, '0') === '1';
    }

    /**
     * Darf an diesem Projekt gebaut werden?
     * @return array{ok:bool, grund:string, stopp:bool, angenommen:bool, bezahlt:bool, seit:?string, von:?string}
     */
    public static function darfBauen(array|int $p): array
    {
        if (is_int($p)) { $p = Db::one('SELECT * FROM projects WHERE id = ?', [$p]) ?: []; }
        if (!$p) { return ['ok' => false, 'grund' => 'Projekt nicht gefunden.', 'stopp' => false, 'angenommen' => false, 'bezahlt' => false, 'seit' => null, 'von' => null]; }
        $r = ['ok' => false, 'grund' => '', 'stopp' => false, 'angenommen' => false, 'bezahlt' => false,
              'seit' => $p['bau_frei_am'] ?? null, 'von' => $p['bau_frei_von'] ?? null];
        if (self::alleGestoppt()) { return ['grund' => 'Not-Aus: Alle automatischen Builds sind gestoppt.', 'stopp' => true] + $r; }
        if ((int) ($p['ki_stopp'] ?? 0) === 1) {
            return ['grund' => 'Not-Aus für dieses Projekt' . (trim((string) ($p['ki_stopp_grund'] ?? '')) !== '' ? ': ' . $p['ki_stopp_grund'] : '') . '.', 'stopp' => true] + $r;
        }
        $oid = (int) ($p['order_id'] ?? 0);
        $r['angenommen'] = (int) Db::wert("SELECT COUNT(*) FROM angebote WHERE status = 'angenommen' AND (project_id = ? OR (order_id IS NOT NULL AND order_id = ?))", [(int) $p['id'], $oid], 0) > 0
            || ($oid > 0 && Db::wert('SELECT agb_ok_am FROM orders WHERE id = ?', [$oid], null) !== null);
        $r['bezahlt'] = $oid > 0 && ((int) Db::wert("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'bezahlt'", [$oid], 0) > 0
            || in_array((string) Db::wert('SELECT status FROM orders WHERE id = ?', [$oid], ''), self::BEZAHLT, true));
        if (!empty($p['bau_frei_am'])) { return ['ok' => true, 'grund' => 'Bauen erlaubt.'] + $r; }
        if (!$r['angenommen']) { return ['grund' => 'Bausperre: Das Angebot ist noch nicht angenommen.'] + $r; }
        if (!$r['bezahlt']) { return ['grund' => 'Bausperre: Die Anzahlung ist noch nicht bezahlt.'] + $r; }
        $jetzt = date('Y-m-d H:i:s');
        Db::run('UPDATE projects SET bau_frei_am = ?, bau_frei_von = ? WHERE id = ? AND bau_frei_am IS NULL', [$jetzt, 'automatisch: Angebot angenommen + Anzahlung bezahlt', (int) $p['id']]);
        try { Events::pruefspur('bausperre_frei', 'projects', (int) $p['id'], [], ['bau_frei_am' => $jetzt, 'von' => 'automatisch']); } catch (Throwable $e) { }
        return ['ok' => true, 'grund' => 'Bauen erlaubt.', 'seit' => $jetzt, 'von' => 'automatisch: Angebot angenommen + Anzahlung bezahlt'] + $r;
    }

    /** Wirft, wenn nicht gebaut werden darf — für Werkstatt und Veröffentlichung. */
    public static function pruefen(array|int $p): void
    {
        $r = self::darfBauen($p);
        if (!$r['ok']) { throw new RuntimeException($r['grund'] . ' Nichts wurde geändert.'); }
    }

    /** Wirft nur beim Not-Aus (Lesen und Planen bleiben erlaubt, solange nur die Sperre gilt). */
    public static function pruefenStopp(array|int $p): void
    {
        $r = self::darfBauen($p);
        if ($r['stopp']) { throw new RuntimeException($r['grund'] . ' Nichts wurde geändert.'); }
    }

    /** Ein Admin hebt die Sperre von Hand auf (z. B. ein Projekt ohne Angebot im System). */
    public static function vonHandFreigeben(int $pid, string $wer, string $grund): void
    {
        $grund = trim($grund);
        if (mb_strlen($grund) < 10) { throw new RuntimeException('Bitte begründen, warum ohne Annahme und Zahlung gebaut werden darf (mindestens 10 Zeichen).'); }
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        Db::update('projects', $pid, ['bau_frei_am' => date('Y-m-d H:i:s'), 'bau_frei_von' => mb_substr('von Hand: ' . $wer . ' — ' . $grund, 0, 120)]);
        Events::pruefspur('bausperre_von_hand', 'projects', $pid, ['bau_frei_am' => $p['bau_frei_am']], ['von' => $wer, 'grund' => $grund]);
    }

    /** Not-Aus je Projekt ziehen (jede Mitarbeit). */
    public static function stoppen(int $pid, string $wer, string $grund): void
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        Db::update('projects', $pid, ['ki_stopp' => 1, 'ki_stopp_grund' => mb_substr(trim($grund) ?: 'ohne Angabe', 0, 255), 'ki_stopp_am' => date('Y-m-d H:i:s'), 'ki_stopp_von' => mb_substr($wer, 0, 120)]);
        Events::pruefspur('ki_stopp', 'projects', $pid, ['ki_stopp' => (int) $p['ki_stopp']], ['ki_stopp' => 1, 'grund' => $grund, 'von' => $wer]);
        try { Events::melden('ki_stopp', 'KI gestoppt: ' . (string) $p['name'], 'warnung', $wer . ': ' . ($grund ?: 'ohne Angabe'), 'projekte/' . $pid); } catch (Throwable $e) { }
    }

    /** Not-Aus je Projekt aufheben (nur Admin — die Rolle prüft der Aufrufer über Rechte). */
    public static function weiter(int $pid, string $wer): void
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        Db::update('projects', $pid, ['ki_stopp' => 0]);
        Events::pruefspur('ki_weiter', 'projects', $pid, ['ki_stopp' => 1, 'grund' => $p['ki_stopp_grund']], ['ki_stopp' => 0, 'von' => $wer]);
    }

    /** „Alle automatischen Builds stoppen“ bzw. wieder erlauben. */
    public static function alleSetzen(bool $stopp, string $wer): void
    {
        require_once __DIR__ . '/AkquiseGate.php';
        AkquiseGate::setzen(self::ALLE, $stopp ? '1' : '0');
        Events::pruefspur($stopp ? 'bau_alle_stopp' : 'bau_alle_weiter', 'settings', null, [], ['von' => $wer]);
        if ($stopp) { try { Events::melden('ki_stopp', 'Alle automatischen Builds gestoppt', 'warnung', 'von ' . $wer, 'projekte'); } catch (Throwable $e) { } }
    }
}
