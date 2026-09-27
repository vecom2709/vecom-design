<?php
declare(strict_types=1);

/**
 * „Ihre Empfehlung ist online“ -- Hinweis und fertiger Beitrag für Partner
 * (27.09.2026, Uwe: Ja zu „Erfolgs-Hinweis + Post“).
 *
 * Der stärkste Beitrag, den ein Partner teilen kann, ist keine Werbung,
 * sondern ein Ergebnis: „Seht, was für Pizzeria Rossi entstanden ist.“
 * Dafür braucht er Name und Adresse des Kunden -- und die bekommt er in der
 * Vereinbarung ausdrücklich NICHT. Deshalb zwei getrennte Schritte:
 *
 *   1. Projekt geht online  → Partner bekommt einen Hinweis OHNE Namen
 *      („Eine Ihrer Empfehlungen ist jetzt online“), einmal je Kunde.
 *   2. Der KUNDE stimmt auf seiner Seite zu („Mein Empfehler darf meine neue
 *      Website zeigen“) → erst dann sieht der Partner Namen, Adresse und den
 *      fertigen Beitrag. Widerruf jederzeit, dann ist beides wieder weg.
 *
 * Nichts davon verlässt das Haus ohne den Partner: Wir schlagen den Beitrag
 * vor, teilen tut er selbst.
 */
final class PartnerErfolg
{
    /** Aus Events::projektStatus beim Wechsel auf „online“. Still: darf den Statuswechsel nie aufhalten. */
    public static function beiOnline(int $projektId): bool
    {
        return (bool) self::still(static function () use ($projektId) {
            $pr = Db::one('SELECT customer_id FROM projects WHERE id = ?', [$projektId]);
            if (!$pr) { return false; }
            $z = Db::one('SELECT * FROM partner_zuordnungen WHERE customer_id = ?', [(int) $pr['customer_id']]);
            if (!$z || $z['online_gemeldet_am'] !== null) { return false; }
            $p = Partner::laden((int) $z['partner_id']);
            if (!$p || $p['status'] !== 'aktiv') { return false; }
            // Erst vermerken, dann schicken -- ein hängender Push-Dienst darf keine Serie auslösen.
            Db::run('UPDATE partner_zuordnungen SET online_gemeldet_am = NOW() WHERE customer_id = ? AND online_gemeldet_am IS NULL', [(int) $pr['customer_id']]);
            $sp = self::sprache($p);
            PartnerPost::push((int) $p['id'], self::t('push_online_t', $sp), self::t('push_online_x', $sp), Partner::portalLink($p) . '#erfolge');
            Events::protokoll('partner_erfolg', 'Partner-Empfehlung online: ' . $p['name'], (int) $pr['customer_id'], null, $projektId, ['partner_id' => (int) $p['id']]);
            return true;
        }, false);
    }

    /**
     * Der Kunde entscheidet. Beim ersten Ja erfährt der Partner es sofort --
     * das ist genau der Moment, in dem ein Beitrag noch frisch ist.
     */
    public static function zeigenSetzen(int $kundeId, bool $ja): void
    {
        $z = Db::one('SELECT * FROM partner_zuordnungen WHERE customer_id = ?', [$kundeId]);
        if (!$z) { return; }
        Db::run('UPDATE partner_zuordnungen SET zeigen_am = ' . ($ja ? 'COALESCE(zeigen_am, NOW())' : 'NULL') . ' WHERE customer_id = ?', [$kundeId]);
        Events::protokoll($ja ? 'partner_zeigen_ja' : 'partner_zeigen_nein',
            $ja ? 'Kunde erlaubt dem Empfehler, die Website zu zeigen' : 'Kunde nimmt die Erlaubnis für den Empfehler zurück', $kundeId, null, null,
            ['partner_id' => (int) $z['partner_id']]);
        if ($ja && $z['zeigen_am'] === null) {
            $p = Partner::laden((int) $z['partner_id']);
            if ($p && $p['status'] === 'aktiv') {
                $sp = self::sprache($p);
                self::still(static fn() => PartnerPost::push((int) $p['id'], self::t('push_zeigen_t', $sp), self::t('push_zeigen_x', $sp), Partner::portalLink($p) . '#erfolge'));
            }
        }
    }

    /**
     * Für die Kundenseite: Kam dieser Kunde über einen Partner? Nur der
     * Anzeigename des Partners -- der Kunde kennt ihn ja, er hat ihn empfohlen.
     *
     * @return array{partner:string, zeigen:bool}|null
     */
    public static function fuerKunde(int $kundeId): ?array
    {
        return self::still(static function () use ($kundeId) {
            $z = Db::one("SELECT z.zeigen_am, p.* FROM partner_zuordnungen z JOIN partner p ON p.id = z.partner_id
                           WHERE z.customer_id = ? AND p.status = 'aktiv'", [$kundeId]);
            return $z ? ['partner' => Partner::anzeigeName($z), 'zeigen' => $z['zeigen_am'] !== null] : null;
        }, null);
    }

    /**
     * Für das Dashboard: jede Empfehlung, deren Projekt online ist. Name und
     * Adresse nur mit Zustimmung des Kunden.
     *
     * @return list<array{seit:string, zeigen:bool, firma:string, url:string}>
     */
    public static function liste(int $partnerId): array
    {
        $aus = [];
        foreach (self::still(static fn() => Db::all("SELECT z.customer_id, z.zeigen_am,
                    (SELECT MAX(pr.updated_at) FROM projects pr WHERE pr.customer_id = z.customer_id AND pr.status IN ('online','abgeschlossen')) AS seit,
                    c.company, c.name
                FROM partner_zuordnungen z JOIN customers c ON c.id = z.customer_id
               WHERE z.partner_id = ? AND EXISTS (SELECT 1 FROM projects pr WHERE pr.customer_id = z.customer_id AND pr.status IN ('online','abgeschlossen'))
            ORDER BY seit DESC", [$partnerId]), []) as $z) {
            $zeigen = $z['zeigen_am'] !== null;
            $url = '';
            if ($zeigen) {
                $w = self::still(static fn() => Db::one("SELECT url, domain FROM websites WHERE customer_id = ? AND status IN ('online','wird_geprueft') ORDER BY id DESC LIMIT 1", [(int) $z['customer_id']]), null);
                $url = $w ? (string) ($w['url'] ?: 'https://' . $w['domain']) : '';
                if (!preg_match('~^https?://~', $url)) { $url = ''; }
            }
            $aus[] = ['seit' => (string) ($z['seit'] ?? ''), 'zeigen' => $zeigen,
                      'firma' => $zeigen ? trim((string) ($z['company'] ?: $z['name'])) : '', 'url' => $url];
        }
        return $aus;
    }

    /** Der fertige Beitrag -- in der Sprache des Dashboards, mit Empfehlungslink des Partners (Kanal „erfolg“). */
    public static function beitrag(array $p, string $firma, string $url, string $sprache): string
    {
        $t = strtr(self::t('post_text', $sprache), ['{firma}' => $firma, '{url}' => $url !== '' ? $url : '']);
        $t = preg_replace("~\n{3,}~", "\n\n", $t);
        return trim($t) . "\n\n" . self::t('post_link', $sprache) . ' ' . PartnerWerbung::link($p, 'erfolg');
    }

    private static function sprache(array $p): string
    {
        return in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
    }

    private static function t(string $k, string $sp): string
    {
        require_once __DIR__ . '/Texte.php';
        return Texte::h(Texte::PARTNER_ERFOLG[$k] ?? [], $sp);
    }

    /** @template T @param callable():T $f @param T $sonst @return T */
    private static function still(callable $f, mixed $sonst = null): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }
}
