<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Partner.php';

/* ==========================================================================
   PartnerTest.php — der Testpartner (Phase 9b, 06.10.2026).

   Uwe: „Ja, ein Testpartner“ — er zählt nirgends: keine Provision, keine Mail,
   keine Auszahlung, keine Statistik. Ein Knopf setzt ihn zurück.

   WOFÜR
   „Als Partner ansehen“ zeigt nur die Vorschau des Marketing Centers. Was ein
   Partner wirklich erlebt — Leads anlegen, Support schreiben, Mails aus dem
   Mail-Center, Academy, Kampagnen — ließ sich bisher nur an einem echten
   Partner ausprobieren. Dafür gibt es genau EINEN Testpartner (partner.test = 1).

   WIE ER NIRGENDS ZÄHLT — an den Stellen, durch die alles muss:
     Partner::klick, Partner::zuordnen, Spur (Besuch ohne Partner),
     Partner::provisionAnlegen (keine Provision), PartnerWege::auszahlen und
     Partner::lauf (kein Geld), Partner::schreiben, PartnerPost::push und
     PartnerMail::senden (keine Mail, kein Push), Partner::kundeMelden
     (Übergabe wird nur gespielt — keine echte Anfrage), PartnerNews::empfaenger,
     Rangliste und Auswertung.
   Seine Adresse endet auf .invalid: Selbst ein vergessener Weg zu Mail::senden
   scheitert dort (RFC 2606) — die zweite Linie, nicht die erste.
   Gerätecodes gehen deshalb nie per Mail raus, sondern stehen als Meldung bei Uwe.
   ========================================================================== */
final class PartnerTest
{
    public const CODE = 'TESTVECOM';
    public const EMAIL = 'testpartner@vecom-design.invalid';

    public static function ist(?array $p): bool
    {
        return $p !== null && !empty($p['test']);
    }

    public static function istId(int $partnerId): bool
    {
        return (int) Db::wert('SELECT COUNT(*) FROM partner WHERE id = ? AND test = 1', [$partnerId], 0) === 1;
    }

    public static function laden(): ?array
    {
        $p = Db::one('SELECT * FROM partner WHERE test = 1 ORDER BY id LIMIT 1');
        return $p ?: null;
    }

    /** Anlegen, falls es ihn noch nicht gibt — aktiv, Vereinbarung bestätigt, freigeschaltet. */
    public static function anlegen(): array
    {
        $p = self::laden();
        if ($p !== null) { return $p; }
        $id = Partner::anlegen(['name' => 'Testpartner (Vecom)', 'email' => self::EMAIL, 'code' => self::CODE, 'sprache' => 'it', 'status' => 'aktiv']);
        Db::run('UPDATE partner SET test = 1, customer_id = NULL, vereinbarung_am = NOW(), vereinbarung_klauseln_am = NOW(), vereinbarung_version = ?, freigeschaltet_am = NOW()
                  WHERE id = ?', [Partner::VEREINBARUNG_VERSION, $id]);
        self::spur('partner_test_anlegen', $id);
        return (array) Partner::laden($id);
    }

    /**
     * Zurücksetzen: alles, was beim Ausprobieren entstand, ist weg — der Partner selbst bleibt (Link und Code gelten weiter).
     * @return array<string,int> gelöschte Zeilen je Tabelle
     */
    public static function zuruecksetzen(): array
    {
        $p = self::laden();
        if ($p === null) { return []; }
        $id = (int) $p['id'];
        $aus = [];
        $tabellen = ['partner_lead_verlauf', 'partner_leads', 'partner_nachrichten', 'partner_tickets', 'partner_news_an', 'partner_mails', 'partner_provisionen',
                     'partner_klicks', 'partner_kanal_klicks', 'partner_zaehler', 'academy_fortschritt', 'academy_merkliste', 'academy_notizen', 'academy_abschluss',
                     'academy_zertifikate', 'partner_push', 'partner_geraete'];
        foreach ($tabellen as $t) {
            try { $aus[$t] = Db::run("DELETE FROM `$t` WHERE partner_id = ?", [$id])->rowCount(); }
            catch (Throwable $e) { /* Tabelle gibt es (noch) nicht — dann gibt es dort auch nichts */ }
        }
        Db::run('UPDATE partner SET name = ? WHERE id = ?', ['Testpartner (Vecom)', $id]);
        self::spur('partner_test_zuruecksetzen', $id, $aus);
        return array_filter($aus);
    }

    private static function spur(string $tat, int $id, array $nachher = []): void
    {
        try { require_once __DIR__ . '/Events.php'; Events::pruefspur($tat, 'partner', $id, [], $nachher); } catch (Throwable $e) { }
    }
}
