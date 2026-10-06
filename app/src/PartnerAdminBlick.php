<?php
declare(strict_types=1);
/* ==========================================================================
   PartnerAdminBlick — Admins schauen ins Partner-Dashboard, ohne Code
   (06.10.2026, Uwe: „Admins können ins Partner-Dashboard schauen ohne Code“).

   Ablauf: Knopf „Dashboard ansehen“ in der Partnerakte (nur Admin) →
   Einmal-Ticket, 2 Minuten gültig, nur als Hash gespeichert → partner.php
   löst es ein und merkt sich in SEINER Sitzung (Keks „vecompartnerseite“)
   für 2 Stunden: dieser Admin sieht diesen Partner. Der Partner-Link und
   der Code werden dafür nicht gebraucht und nicht angezeigt.

   NUR LESEN: In dieser Ansicht nimmt partner.php keine Formulare an — der
   Admin kann nichts im Namen des Partners ändern, senden oder freigeben.
   Eigene Aufrufe zählen nicht als Partner-Besuch, das Gerät wird nicht
   gemerkt, die Sprache des Partners bleibt. Jede Ansicht steht in der
   Prüfspur (wer, welcher Partner, wann).
   ========================================================================== */

final class PartnerAdminBlick
{
    public const TICKET_SEKUNDEN = 120;
    public const DAUER_SEKUNDEN = 7200;
    private const SITZUNG = 'admin_blick';

    /** Einmal-Ticket für einen Admin. @return string das Ticket (nur einmal sichtbar) */
    public static function ticket(int $partnerId, int $userId, string $adminName): string
    {
        if ($partnerId <= 0 || $userId <= 0) { throw new RuntimeException('Partner oder Admin fehlt.'); }
        if (!Db::one('SELECT id FROM partner WHERE id = ?', [$partnerId])) { throw new RuntimeException('Partner nicht gefunden.'); }
        $t = bin2hex(random_bytes(24));
        Db::insert('partner_admin_blick', ['partner_id' => $partnerId, 'user_id' => $userId, 'admin_name' => mb_substr($adminName !== '' ? $adminName : 'Admin', 0, 120),
            'ticket_hash' => hash('sha256', $t), 'gueltig_bis' => date('Y-m-d H:i:s', time() + self::TICKET_SEKUNDEN)]);
        return $t;
    }

    /** Ticket einlösen (genau einmal, nur solange gültig). @return array{partner_id:int,user_id:int,admin_name:string}|null */
    public static function einloesen(string $ticket): ?array
    {
        if (!preg_match('~^[0-9a-f]{48}$~', $ticket)) { return null; }
        $z = Db::one('SELECT * FROM partner_admin_blick WHERE ticket_hash = ? AND benutzt_am IS NULL AND gueltig_bis >= NOW()', [hash('sha256', $ticket)]);
        if (!$z) { return null; }
        $n = Db::run('UPDATE partner_admin_blick SET benutzt_am = NOW() WHERE id = ? AND benutzt_am IS NULL', [(int) $z['id']]);
        if (method_exists($n, 'rowCount') && $n->rowCount() !== 1) { return null; }
        try { Events::pruefspur('partner_admin_blick', 'partner', (int) $z['partner_id'], [], ['admin' => (string) $z['admin_name'], 'user_id' => (int) $z['user_id']]); } catch (Throwable $e) { }
        return ['partner_id' => (int) $z['partner_id'], 'user_id' => (int) $z['user_id'], 'admin_name' => (string) $z['admin_name']];
    }

    /** In der Partner-Sitzung merken. */
    public static function merken(array $z): void
    {
        $_SESSION[self::SITZUNG] = $z + ['bis' => time() + self::DAUER_SEKUNDEN];
    }

    /** Die laufende Admin-Ansicht, wenn es eine gibt. @return array{partner_id:int,user_id:int,admin_name:string,bis:int}|null */
    public static function aktiv(): ?array
    {
        $z = $_SESSION[self::SITZUNG] ?? null;
        if (!is_array($z) || (int) ($z['bis'] ?? 0) < time() || (int) ($z['partner_id'] ?? 0) <= 0) { unset($_SESSION[self::SITZUNG]); return null; }
        return $z;
    }

    public static function beenden(): void
    {
        unset($_SESSION[self::SITZUNG]);
    }

    /** Hinweisleiste oben auf jeder Seite der Admin-Ansicht. */
    public static function leiste(array $z, string $partnerName): string
    {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<div role="status" style="position:sticky;top:0;z-index:99999;background:#f2c94c;color:#111;font:600 15px/1.45 system-ui,sans-serif;padding:10px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:center;text-align:center">'
            . '<span>👁 Admin-Ansicht: Dashboard von <b>' . $h($partnerName) . '</b> — nur lesen, nichts wird geändert oder gesendet. (' . $h($z['admin_name']) . ', bis ' . date('H:i', (int) $z['bis']) . ' Uhr)</span>'
            . '<a href="/partner.php?admin_ende=1" style="color:#111;text-decoration:underline">Ansicht beenden</a></div>';
    }
}
