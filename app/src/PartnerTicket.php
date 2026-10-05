<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/PartnerPost.php';

/* ==========================================================================
   PartnerTicket.php — Support-Tickets für Partner (Phase 7b, 05.10.2026).

   Uwe: „5 Themen“ (Provision & Auszahlung · Kunde/Kontakt · Werbemittel &
   Bestellung · Zugang & Technik · Sonstiges), „Offen → In Arbeit → Erledigt“.

   Ein Ticket ist der Rahmen um die vorhandenen Nachrichten (PartnerPost,
   partner_nachrichten): Thema, Betreff, Stand und auf Wunsch ein Bezug auf
   einen EIGENEN Kontakt oder eine EIGENE Bestellung. Gesendet, gemeldet und
   gedrosselt wird weiter in PartnerPost — es gibt keinen zweiten Weg.

   STÄNDE
   Uwe setzt „In Arbeit“ und „Erledigt“. Schreibt der Partner in ein erledigtes
   Ticket, ist es wieder offen. Nichts schließt von selbst.
   ========================================================================== */
final class PartnerTicket
{
    public const THEMEN = ['geld', 'kunde', 'werbemittel', 'technik', 'sonstiges'];
    public const STAENDE = ['offen', 'in_arbeit', 'erledigt'];
    public const BETREFF_MIN = 3;
    public const BETREFF_MAX = 120;
    /** Höchstens so viele neue Tickets je Tag — die Nachrichten selbst drosselt PartnerPost (12/Stunde). */
    public const NEU_JE_TAG = 10;

    /**
     * Neues Anliegen. @return array{ok:bool, id?:int, grund?:string}  grund: thema|betreff|text|bezug|genug|zuviel
     */
    public static function eroeffnen(int $partnerId, string $thema, string $betreff, string $text, ?string $bezugArt = null, ?int $bezugId = null): array
    {
        if (!in_array($thema, self::THEMEN, true)) { return ['ok' => false, 'grund' => 'thema']; }
        $betreff = trim((string) preg_replace('~[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+~u', ' ', $betreff));
        if (mb_strlen($betreff) < self::BETREFF_MIN) { return ['ok' => false, 'grund' => 'betreff']; }
        $betreff = mb_substr($betreff, 0, self::BETREFF_MAX);
        if (trim($text) === '') { return ['ok' => false, 'grund' => 'text']; }
        if ($bezugArt !== null || $bezugId !== null) {
            if (!self::bezugGehoert($partnerId, (string) $bezugArt, (int) $bezugId)) { return ['ok' => false, 'grund' => 'bezug']; }
        }
        if ((int) Db::wert('SELECT COUNT(*) FROM partner_tickets WHERE partner_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR', [$partnerId], 0) >= self::NEU_JE_TAG) {
            return ['ok' => false, 'grund' => 'genug'];
        }
        $id = (int) Db::insert('partner_tickets', ['partner_id' => $partnerId, 'thema' => $thema, 'betreff' => $betreff,
            'bezug_art' => $bezugArt, 'bezug_id' => $bezugArt !== null ? $bezugId : null]);
        try {
            PartnerPost::schreiben($partnerId, $text, 'partner', null, $id);
        } catch (LengthException $e) {
            Db::run('DELETE FROM partner_tickets WHERE id = ?', [$id]);   // ohne Nachricht kein Ticket
            return ['ok' => false, 'grund' => 'zuviel'];
        } catch (InvalidArgumentException $e) {
            Db::run('DELETE FROM partner_tickets WHERE id = ?', [$id]);
            return ['ok' => false, 'grund' => 'text'];
        }
        return ['ok' => true, 'id' => $id];
    }

    /** Gehört der Bezug dem Partner? Fremde Kontakte und Bestellungen gibt es für ihn nicht. */
    public static function bezugGehoert(int $partnerId, string $art, int $id): bool
    {
        if ($id <= 0) { return false; }
        return match ($art) {
            'lead' => (int) Db::wert('SELECT COUNT(*) FROM partner_leads WHERE id = ? AND partner_id = ?', [$id, $partnerId], 0) === 1,
            'bestellung' => (int) Db::wert('SELECT COUNT(*) FROM wm_bestellungen WHERE id = ? AND partner_id = ?', [$id, $partnerId], 0) === 1,
            default => false,
        };
    }

    /**
     * Antwort in ein Ticket. Der Partner öffnet ein erledigtes Ticket damit wieder.
     * @return string ok|ticket|text|zuviel
     */
    public static function antworten(int $partnerId, int $ticketId, string $text, string $von, ?int $userId = null): string
    {
        $t = self::laden($partnerId, $ticketId);
        if (!$t) { return 'ticket'; }
        try {
            PartnerPost::schreiben($partnerId, $text, $von, $userId, $ticketId);
        } catch (LengthException $e) { return 'zuviel'; }
          catch (InvalidArgumentException $e) { return 'text'; }
        $neu = $von === 'partner' && $t['stand'] === 'erledigt' ? 'offen' : (string) $t['stand'];
        Db::run('UPDATE partner_tickets SET geaendert_am = NOW(), stand = ?, erledigt_am = IF(? = \'erledigt\', erledigt_am, NULL) WHERE id = ?', [$neu, $neu, $ticketId]);
        return 'ok';
    }

    /** Stand setzen — nur die Verwaltung. */
    public static function standSetzen(int $ticketId, string $stand): bool
    {
        if (!in_array($stand, self::STAENDE, true)) { return false; }
        return Db::run('UPDATE partner_tickets SET stand = ?, geaendert_am = NOW(), erledigt_am = IF(? = \'erledigt\', NOW(), NULL) WHERE id = ?',
            [$stand, $stand, $ticketId])->rowCount() > 0;
    }

    /** Ein Ticket des Partners — fremde gibt es nicht. */
    public static function laden(int $partnerId, int $ticketId): ?array
    {
        $t = Db::one('SELECT * FROM partner_tickets WHERE id = ? AND partner_id = ?', [$ticketId, $partnerId]);
        return $t ?: null;
    }

    /** Tickets des Partners, offene zuerst, mit Zahl der ungelesenen Antworten. */
    public static function liste(int $partnerId): array
    {
        return Db::all("SELECT t.*, (SELECT COUNT(*) FROM partner_nachrichten n WHERE n.ticket_id = t.id AND n.von = 'vecom' AND n.gelesen_am IS NULL) AS neu
                          FROM partner_tickets t WHERE t.partner_id = ?
                         ORDER BY t.stand = 'erledigt', t.geaendert_am DESC LIMIT 100", [$partnerId]);
    }

    /** Verlauf eines Tickets, älteste zuerst. */
    public static function verlauf(int $ticketId): array
    {
        return Db::all('SELECT id, von, text, gelesen_am, created_at FROM partner_nachrichten WHERE ticket_id = ? ORDER BY id', [$ticketId]);
    }

    /** Antworten von Vecom in diesem Ticket als gelesen markieren (der Partner hat es geöffnet). */
    public static function gelesen(int $partnerId, int $ticketId, string $leser): int
    {
        $von = $leser === 'partner' ? 'vecom' : 'partner';
        return Db::run('UPDATE partner_nachrichten SET gelesen_am = NOW() WHERE partner_id = ? AND ticket_id = ? AND von = ? AND gelesen_am IS NULL',
            [$partnerId, $ticketId, $von])->rowCount();
    }

    /** Für die Verwaltung: offene und in Arbeit zuerst, mit Partnername und Bezug in Klartext. */
    public static function fuerVerwaltung(?int $partnerId = null, int $max = 50): array
    {
        $wo = $partnerId !== null ? 'WHERE t.partner_id = ?' : "WHERE t.stand <> 'erledigt' OR t.geaendert_am >= NOW() - INTERVAL 7 DAY";
        return Db::all("SELECT t.*, p.name AS partner, p.code,
                               (SELECT COUNT(*) FROM partner_nachrichten n WHERE n.ticket_id = t.id AND n.von = 'partner' AND n.gelesen_am IS NULL) AS neu,
                               CASE t.bezug_art WHEN 'lead' THEN (SELECT l.name FROM partner_leads l WHERE l.id = t.bezug_id)
                                                WHEN 'bestellung' THEN (SELECT b.nummer FROM wm_bestellungen b WHERE b.id = t.bezug_id) END AS bezug_name
                          FROM partner_tickets t JOIN partner p ON p.id = t.partner_id $wo
                         ORDER BY FIELD(t.stand, 'offen', 'in_arbeit', 'erledigt'), t.geaendert_am DESC LIMIT " . max(1, min(200, $max)),
            $partnerId !== null ? [$partnerId] : []);
    }

    /** Partner › Support (Phase 9): '' = offen und in Arbeit, 'erledigt' = erledigt in 30 Tagen, 'alle'. */
    public static function verwaltungListe(string $filter = ''): array
    {
        $wo = match ($filter) { 'erledigt' => "WHERE t.stand = 'erledigt' AND t.geaendert_am >= NOW() - INTERVAL 30 DAY", 'alle' => '', default => "WHERE t.stand <> 'erledigt'" };
        return Db::all("SELECT t.*, p.name AS partner, p.code,
                               (SELECT COUNT(*) FROM partner_nachrichten n WHERE n.ticket_id = t.id AND n.von = 'partner' AND n.gelesen_am IS NULL) AS neu,
                               CASE t.bezug_art WHEN 'lead' THEN (SELECT l.name FROM partner_leads l WHERE l.id = t.bezug_id)
                                                WHEN 'bestellung' THEN (SELECT b.nummer FROM wm_bestellungen b WHERE b.id = t.bezug_id) END AS bezug_name
                          FROM partner_tickets t JOIN partner p ON p.id = t.partner_id $wo
                         ORDER BY FIELD(t.stand, 'offen', 'in_arbeit', 'erledigt'), t.geaendert_am DESC LIMIT 200");
    }

    /** Wie viele Tickets warten auf Vecom (offen)? */
    public static function offenZahl(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM partner_tickets WHERE stand = 'offen'", [], 0);
    }

    /** Was der Partner als Bezug wählen kann: seine letzten Kontakte und Bestellungen (nur eigene). */
    public static function bezugAuswahl(int $partnerId): array
    {
        return [
            'lead' => Db::all('SELECT id, name FROM partner_leads WHERE partner_id = ? AND archiviert_am IS NULL ORDER BY COALESCE(kontakt_am, created_at) DESC LIMIT 40', [$partnerId]),
            'bestellung' => Db::all('SELECT id, nummer AS name FROM wm_bestellungen WHERE partner_id = ? ORDER BY id DESC LIMIT 20', [$partnerId]),
        ];
    }
}
