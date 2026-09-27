<?php
declare(strict_types=1);

require_once __DIR__ . '/AkquiseBriefdienst.php';

/**
 * Brief-Serie (27.09.2026, Uwe: Ja): bis zu 20 freigegebene Briefe auf
 * einmal -- erst alle Vorschauen mit Gesamtpreis, dann EINE Rückfrage, dann
 * alle raus. Nichts Neues an Regeln: Jeder einzelne Brief läuft durch
 * dieselbe Vorschau und dasselbe Senden wie der Einzelbrief, samt Gate-
 * Prüfung unmittelbar vor dem Bestätigen. Die Serie spart nur Klicks.
 */
final class AkquiseBriefserie
{
    public const HOECHSTENS = 20;

    /**
     * Betriebe mit freigegebenem Brieftext, für die ein Brief erlaubt ist
     * und die noch keinen offenen oder verschickten Brief haben.
     * @return list<array{id:int, name:string, stadt:string, grund:?string}>
     */
    public static function kandidaten(int $max = self::HOECHSTENS): array
    {
        $aus = [];
        foreach (Db::all("SELECT f.* FROM akq_firmen f
                           WHERE f.gesperrt = 0 AND f.land = 'IT'
                             AND EXISTS (SELECT 1 FROM akq_vorlagen v WHERE v.firma_id = f.id AND v.kanal = 'brief' AND v.status = 'freigegeben')
                             AND NOT EXISTS (SELECT 1 FROM akq_briefe b WHERE b.firma_id = f.id AND b.status IN ('vorschau','verschickt'))
                        ORDER BY COALESCE(f.score, 0) DESC, f.id LIMIT 60") as $f) {
            $gate = AkquiseGate::pruefen($f, 'brief');
            $grund = null;
            if (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) { continue; }
            $an = AkquiseBriefdienst::empfaenger($f);
            if (!$an['ok']) { $grund = (string) $an['grund']; }
            $aus[] = ['id' => (int) $f['id'], 'name' => (string) $f['name'], 'stadt' => (string) ($f['stadt'] ?? ''), 'grund' => $grund];
            if (count($aus) >= $max) { break; }
        }
        return $aus;
    }

    /**
     * Vorschauen für die gewählten Betriebe. Fehler einzelner halten die
     * anderen nicht auf -- sie stehen danach mit Grund in der Liste.
     * @param list<int> $firmen
     * @return array{ok:int, fehler:array<int,string>}
     */
    public static function vorbereiten(array $firmen): array
    {
        $ok = 0; $fehler = [];
        foreach (array_slice(array_values(array_unique(array_map('intval', $firmen))), 0, self::HOECHSTENS) as $fid) {
            try { AkquiseBriefdienst::vorschau($fid); $ok++; }
            catch (Throwable $e) { $fehler[$fid] = $e->getMessage(); }
        }
        return ['ok' => $ok, 'fehler' => $fehler];
    }

    /** @return array{briefe:list<array<string,mixed>>, summe:int, test:bool} Alle offenen Vorschauen im aktuellen Modus. */
    public static function offen(): array
    {
        $test = AkquiseBriefdienst::test();
        $briefe = Db::all("SELECT b.*, f.name, f.stadt FROM akq_briefe b JOIN akq_firmen f ON f.id = b.firma_id
                            WHERE b.status = 'vorschau' AND b.test = ? ORDER BY b.id", [$test ? 1 : 0]);
        return ['briefe' => $briefe, 'summe' => array_sum(array_map(static fn($b) => (int) $b['kosten_cents'], $briefe)), 'test' => $test];
    }

    /**
     * Alle offenen Vorschauen bestätigen. Nur die, deren IDs der Rückfrage
     * zugrunde lagen -- kommt zwischendurch eine neue Vorschau dazu, geht sie
     * nicht ungesehen mit.
     * @param list<int> $briefIds
     * @return array{verschickt:int, fehler:array<int,string>, summe:int}
     */
    public static function senden(array $briefIds, string $begruendung): array
    {
        $n = 0; $fehler = []; $summe = 0;
        foreach (array_slice(array_values(array_unique(array_map('intval', $briefIds))), 0, self::HOECHSTENS) as $bid) {
            $kosten = (int) Db::wert("SELECT kosten_cents FROM akq_briefe WHERE id = ? AND status = 'vorschau'", [$bid], 0);
            try { AkquiseBriefdienst::senden($bid, $begruendung); $n++; $summe += $kosten; }
            catch (Throwable $e) { $fehler[$bid] = $e->getMessage(); }
        }
        return ['verschickt' => $n, 'fehler' => $fehler, 'summe' => $summe];
    }

    /** @param list<int> $briefIds */
    public static function verwerfen(array $briefIds): int
    {
        $n = 0;
        foreach (array_map('intval', $briefIds) as $bid) { AkquiseBriefdienst::verwerfen($bid); $n++; }
        return $n;
    }
}
