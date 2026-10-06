<?php
declare(strict_types=1);

require_once __DIR__ . '/Ablage.php';
require_once __DIR__ . '/Akquise.php';

/**
 * Dokumente je Betrieb (Akquise-CRM, 06.10.2026).
 *
 * Uwe: „Am Betrieb, später beim Kunden“ — die Liste hängt am Betrieb und
 * erscheint, sobald er Kunde ist (akq_firmen.customer_id), auch in der
 * Kundenakte. „Nur Verwaltung“ — Partner sehen nichts davon. „Art + wichtig“.
 * „Archivieren, Löschen nur Admin“ — Mitarbeit blendet aus, endgültig löscht
 * nur der Admin, mit Rückfrage und Prüfspur, und nur, was schon im Archiv liegt.
 */
final class AkquiseDokument
{
    public const ARTEN = ['einwilligung' => 'Einwilligung / Nachweis', 'vertrag' => 'Angebot / Vertrag', 'foto' => 'Foto',
                          'gespraech' => 'Gespräch', 'sonstiges' => 'Sonstiges'];
    public const MAX_JE_BETRIEB = 40;

    /** @return list<array<string,mixed>> */
    public static function liste(int $fid, bool $archiv = false): array
    {
        try {
            return Db::all('SELECT * FROM akq_dokumente WHERE firma_id = ? AND archiviert_am IS ' . ($archiv ? 'NOT ' : '') . 'NULL
                             ORDER BY wichtig DESC, id DESC', [$fid]);
        } catch (Throwable $e) { return []; }   // vor Migration 199
    }

    /** Für die Kundenakte: alle aktiven Dokumente der Betriebe, die zu diesem Kunden wurden. */
    public static function zuKunde(int $kid): array
    {
        try {
            return Db::all('SELECT d.*, f.name AS firma FROM akq_dokumente d JOIN akq_firmen f ON f.id = d.firma_id
                             WHERE f.customer_id = ? AND d.archiviert_am IS NULL ORDER BY d.wichtig DESC, d.id DESC', [$kid]);
        } catch (Throwable $e) { return []; }
    }

    /** Der jüngste Einwilligungsnachweis (für den Kommunikationsstatus). */
    public static function nachweis(int $fid): ?array
    {
        try {
            return Db::one("SELECT id, orig_name, created_at FROM akq_dokumente WHERE firma_id = ? AND art = 'einwilligung' AND archiviert_am IS NULL ORDER BY id DESC LIMIT 1", [$fid]) ?: null;
        } catch (Throwable $e) { return null; }
    }

    /**
     * @param array $abgelegt Ergebnis von Ablage::ablegen()/ablegenAus()
     * @return array{ok:bool, id?:int, fehler?:string}
     */
    public static function eintragen(int $fid, array $abgelegt, string $art, bool $wichtig, string $notiz, string $wer, ?int $userId): array
    {
        if (!Db::one('SELECT id FROM akq_firmen WHERE id = ?', [$fid])) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        $id = (int) Db::insert('akq_dokumente', $abgelegt + [
            'firma_id' => $fid, 'art' => isset(self::ARTEN[$art]) ? $art : 'sonstiges', 'wichtig' => $wichtig ? 1 : 0,
            'notiz' => mb_substr(trim(strip_tags($notiz)), 0, 255) ?: null, 'hochgeladen_von' => mb_substr($wer !== '' ? $wer : 'Verwaltung', 0, 80), 'user_id' => $userId]);
        Akquise::protokoll($fid, 'dokument', 'Dokument abgelegt: ' . $abgelegt['orig_name'] . ' (' . (self::ARTEN[$art] ?? 'Sonstiges') . ')');
        return ['ok' => true, 'id' => $id];
    }

    /** Hochladen aus dem Formular. @return array{ok:bool, id?:int, fehler?:string} */
    public static function hochladen(int $fid, array $datei, string $art, bool $wichtig, string $notiz, string $wer, ?int $userId): array
    {
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_dokumente WHERE firma_id = ?', [$fid], 0) >= self::MAX_JE_BETRIEB) {
            return ['ok' => false, 'fehler' => 'Bei diesem Betrieb liegen schon ' . self::MAX_JE_BETRIEB . ' Dokumente.'];
        }
        try { $abgelegt = Ablage::ablegen($datei); } catch (RuntimeException $e) { return ['ok' => false, 'fehler' => $e->getMessage()]; }
        return self::eintragen($fid, $abgelegt, $art, $wichtig, $notiz, $wer, $userId);
    }

    public static function laden(int $id, int $fid): ?array
    {
        try { return Db::one('SELECT * FROM akq_dokumente WHERE id = ? AND firma_id = ?', [$id, $fid]) ?: null; } catch (Throwable $e) { return null; }
    }

    public static function wichtig(int $id, int $fid): bool
    {
        $d = self::laden($id, $fid);
        if (!$d) { return false; }
        Db::run('UPDATE akq_dokumente SET wichtig = 1 - wichtig WHERE id = ?', [$id]);
        return true;
    }

    /** Archivieren oder zurückholen — nichts wird gelöscht. */
    public static function archivieren(int $id, int $fid, bool $zurueck, string $wer): bool
    {
        $d = self::laden($id, $fid);
        if (!$d) { return false; }
        Db::run('UPDATE akq_dokumente SET archiviert_am = ?, archiviert_von = ? WHERE id = ?',
            [$zurueck ? null : date('Y-m-d H:i:s'), $zurueck ? null : mb_substr($wer, 0, 80), $id]);
        Akquise::protokoll($fid, 'dokument', ($zurueck ? 'Dokument zurückgeholt: ' : 'Dokument archiviert: ') . $d['orig_name']);
        return true;
    }

    /**
     * Endgültig löschen: nur Admin, nur aus dem Archiv. Datei und Eintrag weg, die Prüfspur behält Name, Größe, Art und wer.
     * @return array{ok:bool, fehler?:string}
     */
    public static function loeschen(int $id, int $fid, bool $istAdmin, string $wer): array
    {
        if (!$istAdmin) { return ['ok' => false, 'fehler' => 'Endgültig löschen darf nur der Admin. Archivieren geht.']; }
        $d = self::laden($id, $fid);
        if (!$d) { return ['ok' => false, 'fehler' => 'Dokument nicht gefunden.']; }
        if ($d['archiviert_am'] === null) { return ['ok' => false, 'fehler' => 'Erst archivieren, dann löschen.']; }
        $pfad = Ablage::ordner() . '/' . basename((string) $d['stored_name']);
        Db::run('DELETE FROM akq_dokumente WHERE id = ?', [$id]);
        if (is_file($pfad)) { @unlink($pfad); }
        Events::pruefspur('akquise_dokument_geloescht', 'akq_dokumente', $id,
            ['firma_id' => $fid, 'name' => $d['orig_name'], 'art' => $d['art'], 'groesse' => (int) $d['size_bytes'], 'hochgeladen_von' => $d['hochgeladen_von']], ['von' => $wer]);
        Akquise::protokoll($fid, 'dokument', 'Dokument endgültig gelöscht: ' . $d['orig_name']);
        return ['ok' => true];
    }
}
