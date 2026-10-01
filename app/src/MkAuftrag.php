<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkKampagne.php';
require_once __DIR__ . '/MkZielgruppe.php';

/**
 * Recherche per Knopf (Marketing-Studio, 01.10.2026, Uwe: „Recherche soll
 * automatisch starten, wenn in der Verwaltung geklickt wird — im Moment muss
 * man Claude im Chat schreiben“).
 *
 * Die Verwaltung kann den PC nicht anstoßen (Router dazwischen) und ruft
 * selbst keine KI auf (Uwe: über sein Claude-Abo). Deshalb:
 *
 *   Knopf  → Auftrag „wartet“
 *   PC     → fragt alle 5 Minuten nach (steuern), holt ihn ab → „läuft“,
 *            startet Claude Code mit Uwes Anmeldung, nur Websuche und Webseiten
 *   Claude → liefert Zielgruppen und Funde als ENTWÜRFE über die Worker-Tür
 *   PC     → meldet „fertig“ oder „Fehler“ mit Zahlen
 *
 * Freigegeben wird weiterhin nur in der Verwaltung.
 */
final class MkAuftrag
{
    public const STATUS = ['wartet' => 'wartet auf deinen PC', 'laeuft' => 'Claude recherchiert', 'fertig' => 'fertig',
                           'fehler' => 'nicht geklappt', 'abgebrochen' => 'abgebrochen'];
    /** Schutz fürs Claude-Abo: so viele Aufträge am Tag. */
    public const PRO_TAG = 8;
    /** Meldet der PC sich so lange nicht zurück, gilt der Auftrag als gescheitert. */
    public const HOECHSTENS_MIN = 75;
    /** So viele fehlende Zielgruppen nimmt ein Lauf „alle Branchen“ mit. */
    public const FEHLENDE_JE_LAUF = 2;

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** Was der Auftrag tut — in einem Satz. */
    public static function beschreibung(array $a): string
    {
        $land = MkZielgruppe::LAENDER[$a['land']] ?? $a['land'];
        if ($a['branche'] === '') { return 'Alle Branchen · ' . $land . ' — neue Funde und fehlende Zielgruppen'; }
        return (MkKampagne::branchen()[$a['branche']] ?? $a['branche']) . ' · ' . $land . ' — Zielgruppe und Funde';
    }

    /** @return int|string  Auftragsnummer oder Hinweis */
    public static function anlegen(string $branche, string $land): int|string
    {
        $land = strtoupper($land);
        if ($branche !== '' && !isset(MkKampagne::branchen()[$branche])) { return 'Unbekannte Branche.'; }
        if (!isset(MkZielgruppe::LAENDER[$land])) { return 'Land muss Italien oder Deutschland sein.'; }
        self::aufraeumen();
        $offen = Db::one("SELECT id, status FROM mk_auftraege WHERE branche = ? AND land = ? AND status IN ('wartet','laeuft') LIMIT 1", [$branche, $land]);
        if ($offen) { return $offen['status'] === 'laeuft' ? 'Diese Recherche läuft gerade schon.' : 'Diese Recherche wartet schon auf deinen PC.'; }
        $heute = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE created_at >= CURDATE() AND status <> 'abgebrochen'", [], 0);
        if ($heute >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Recherchen gelaufen — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $id = (int) Db::insert('mk_auftraege', ['branche' => $branche, 'land' => $land]);
        Events::protokoll('recherche_auftrag', 'Recherche angestoßen: ' . self::beschreibung(['branche' => $branche, 'land' => $land]), null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    public static function abbrechen(int $id): ?string
    {
        $n = Db::run("UPDATE mk_auftraege SET status = 'abgebrochen', fertig_am = NOW() WHERE id = ? AND status = 'wartet'", [$id])->rowCount();
        return $n > 0 ? null : 'Nur wartende Aufträge lassen sich abbrechen.';
    }

    /** Hängengebliebene Läufe beenden (PC aus, Claude abgestürzt …). */
    public static function aufraeumen(): void
    {
        Db::run("UPDATE mk_auftraege SET status = 'fehler', fertig_am = NOW(),
                        ergebnis = 'Keine Rückmeldung vom PC — vermutlich ausgeschaltet oder abgebrochen. Einfach neu starten.'
                  WHERE status = 'laeuft' AND gestartet_am < NOW() - INTERVAL " . self::HOECHSTENS_MIN . " MINUTE");
    }

    /** Für befehl_holen: wartet etwas? (vor der Migration still: nein) */
    public static function wartet(): bool
    {
        return (bool) self::still(static fn() => (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE status = 'wartet'", [], 0) > 0, false);
    }

    public static function liste(int $max = 6): array
    {
        self::aufraeumen();
        return Db::all('SELECT * FROM mk_auftraege ORDER BY id DESC LIMIT ' . max(1, min(50, $max)));
    }

    public static function offen(): bool
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE status IN ('wartet','laeuft')", [], 0) > 0;
    }

    /**
     * Für den PC: den ältesten wartenden Auftrag übernehmen, samt allem, was
     * Claude dafür braucht (ohne Personen).
     * @return array{ok:bool, auftrag:?array}
     */
    public static function holen(): array
    {
        self::aufraeumen();
        for ($versuch = 0; $versuch < 3; $versuch++) {
            $a = Db::one("SELECT * FROM mk_auftraege WHERE status = 'wartet' ORDER BY id LIMIT 1");
            if (!$a) { return ['ok' => true, 'auftrag' => null]; }
            $n = Db::run("UPDATE mk_auftraege SET status = 'laeuft', gestartet_am = NOW() WHERE id = ? AND status = 'wartet'", [(int) $a['id']])->rowCount();
            if ($n === 0) { continue; }   // ein anderer Abruf war schneller
            $branche = (string) $a['branche'];
            $land = (string) $a['land'];
            $daten = MkZielgruppe::datenFuerClaude($branche !== '' ? $branche : null, $land);
            $ziele = [];
            if ($branche !== '') {
                $ziele[] = $branche;
            } else {
                foreach (MkZielgruppe::fehlend(20) as $f) {
                    if ($f['land'] === $land) { $ziele[] = $f['branche']; }
                    if (count($ziele) >= self::FEHLENDE_JE_LAUF) { break; }
                }
            }
            $vorhanden = [];
            foreach ($ziele as $b) {
                $p = Db::one('SELECT profil FROM mk_zielgruppen WHERE branche = ? AND land = ?', [$b, $land]);
                if ($p) { $vorhanden[$b] = json_decode((string) $p['profil'], true) ?: null; }
            }
            return ['ok' => true, 'auftrag' => [
                'id' => (int) $a['id'], 'branche' => $branche, 'land' => $land,
                'beschreibung' => self::beschreibung($a),
                'zielgruppen_fuer' => array_map(static fn($b) => ['branche' => $b, 'name' => MkKampagne::branchen()[$b] ?? $b], $ziele),
                'vorhandene_profile' => $vorhanden,
                'daten' => $daten,
            ]];
        }
        return ['ok' => true, 'auftrag' => null];
    }

    /** Für den PC: fertig oder gescheitert. */
    public static function melden(array $d): array
    {
        $id = (int) ($d['id'] ?? 0);
        $ok = !empty($d['ok']);
        $text = mb_substr(trim(strip_tags((string) ($d['text'] ?? ''))), 0, 1000);
        $zg = max(0, min(999, (int) ($d['zielgruppen'] ?? 0)));
        $fu = max(0, min(999, (int) ($d['funde'] ?? 0)));
        $a = Db::one('SELECT * FROM mk_auftraege WHERE id = ?', [$id]);
        if (!$a) { return ['ok' => false, 'hinweis' => 'Auftrag unbekannt.']; }
        if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
        Db::update('mk_auftraege', $id, ['status' => $ok ? 'fertig' : 'fehler', 'ergebnis' => $text !== '' ? $text : null,
                                         'zielgruppen' => $zg, 'funde' => $fu, 'fertig_am' => date('Y-m-d H:i:s')]);
        self::still(static fn() => Events::melden('recherche_fertig',
            $ok ? 'Recherche fertig: ' . self::beschreibung($a) : 'Recherche nicht geklappt: ' . self::beschreibung($a),
            $ok ? 'gut' : 'info',   // kein „warnung“: das klingelte als Störung auf dem Handy
            $ok ? $zg . ' Zielgruppen-Entwürfe, ' . $fu . ' neue Funde — bitte prüfen und freigeben.' : $text,
            $zg > 0 && $fu === 0 ? 'zielgruppen' : 'recherche'), null);
        return ['ok' => true];
    }
}
