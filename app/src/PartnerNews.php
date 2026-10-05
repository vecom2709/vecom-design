<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Partner.php';
require_once __DIR__ . '/PartnerPost.php';

/* ==========================================================================
   PartnerNews.php — Meldungen an Partner mit Zielgruppe (Phase 7b-2, 05.10.2026).

   Uwe: „Dashboard + Handy-Hinweis“ — keine Mail; die bleibt für Wichtiges.
   Eine Meldung steht oben auf der Startseite des Command Centers, bis der
   Partner sie wegklickt oder sie abläuft, und geht beim Senden EINMAL als
   Push an die passenden Partner mit App (PartnerPost::push achtet auf den
   Urlaubsmodus). Der Wochenimpuls bleibt, wie er ist.

   ZIELGRUPPE: alle · ein Level (starter/silber/gold/platin) · ein Land ·
   neue Partner (Vereinbarung in den letzten 30 Tagen). Immer nur aktive
   Partner mit unterschriebener Vereinbarung und ohne Sperre.
   Die Empfänger werden beim Senden festgehalten (partner_news_an): Wer
   später Gold wird, bekommt keine alte Gold-Meldung nachgereicht.
   ========================================================================== */
final class PartnerNews
{
    public const ZIELE = ['alle', 'level', 'land', 'neu'];
    public const LEVEL = ['starter', 'silber', 'gold', 'platin'];
    public const NEU_TAGE = 30;
    public const TITEL_MAX = 120;
    public const TEXT_MAX = 600;
    /** Ohne Ablaufdatum bleibt eine Meldung so lange auf der Startseite. */
    public const STANDARD_TAGE = 21;

    /** Prüfnaht für die Kette: fn(int $partnerId, string $titel, string $text, string $link): int — ersetzt PartnerPost::push. */
    public static $push = null;

    /** Wer gehört zur Zielgruppe? @return list<array<string,mixed>> Partnerzeilen */
    public static function empfaenger(string $ziel, string $wert = ''): array
    {
        if (!in_array($ziel, self::ZIELE, true)) { return []; }
        require_once __DIR__ . '/PartnerSchutz.php';
        $wo = "p.status = 'aktiv' AND p.vereinbarung_am IS NOT NULL AND " . PartnerSchutz::sqlFrei('p');
        $par = [];
        if ($ziel === 'land') {
            if (!preg_match('~^[A-Z]{2}$~', $wert)) { return []; }
            $wo .= ' AND UPPER(COALESCE(p.land, \'IT\')) = ?'; $par[] = $wert;
        }
        if ($ziel === 'neu') { $wo .= ' AND p.vereinbarung_am >= NOW() - INTERVAL ' . self::NEU_TAGE . ' DAY'; }
        $alle = Db::all("SELECT p.* FROM partner p WHERE $wo ORDER BY p.id", $par);
        if ($ziel !== 'level') { return $alle; }
        if (!in_array($wert, self::LEVEL, true)) { return []; }
        return array_values(array_filter($alle, static fn($p) => (Partner::satzFuer($p)['stufe'] ?? 'starter') === $wert));
    }

    /**
     * Meldung anlegen und verteilen. Italienisch ist Pflicht (die meisten Partner), Deutsch und Englisch
     * fallen auf Italienisch zurück. Link nur https oder ein Weg auf der eigenen Seite.
     * @return array{ok:bool, id?:int, an?:int, push?:int, grund?:string}  grund: titel|text|ziel|link|leer
     */
    public static function senden(array $d, ?int $userId = null): array
    {
        $t = static fn(string $k, int $max): string => mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) ($d[$k] ?? ''))), 0, $max);
        $x = static fn(string $k): string => mb_substr(trim(str_replace("\r\n", "\n", (string) ($d[$k] ?? ''))), 0, self::TEXT_MAX);
        $z = ['titel_it' => $t('titel_it', self::TITEL_MAX), 'titel_de' => $t('titel_de', self::TITEL_MAX), 'titel_en' => $t('titel_en', self::TITEL_MAX),
              'text_it' => $x('text_it'), 'text_de' => $x('text_de'), 'text_en' => $x('text_en')];
        if (mb_strlen($z['titel_it']) < 3) { return ['ok' => false, 'grund' => 'titel']; }
        if (mb_strlen($z['text_it']) < 10) { return ['ok' => false, 'grund' => 'text']; }
        $ziel = (string) ($d['ziel'] ?? 'alle');
        $wert = $ziel === 'land' ? strtoupper(trim((string) ($d['ziel_wert'] ?? ''))) : trim((string) ($d['ziel_wert'] ?? ''));
        if (!in_array($ziel, self::ZIELE, true) || ($ziel === 'level' && !in_array($wert, self::LEVEL, true)) || ($ziel === 'land' && !preg_match('~^[A-Z]{2}$~', $wert))) {
            return ['ok' => false, 'grund' => 'ziel'];
        }
        $link = trim((string) ($d['link'] ?? ''));
        if ($link !== '' && !preg_match('~^(https://[^\s"<>]{4,280}|/[^\s"<>]{0,280})$~', $link)) { return ['ok' => false, 'grund' => 'link']; }
        $bis = (string) ($d['bis'] ?? '');
        $bis = preg_match('~^\d{4}-\d{2}-\d{2}$~', $bis) && strtotime($bis) && strtotime($bis) >= strtotime('today') ? $bis : date('Y-m-d', strtotime('+' . self::STANDARD_TAGE . ' days'));
        $an = self::empfaenger($ziel, $wert);
        if (!$an) { return ['ok' => false, 'grund' => 'leer']; }
        $id = (int) Db::insert('partner_news', $z + ['link' => $link !== '' ? $link : null, 'ziel' => $ziel, 'ziel_wert' => $ziel === 'alle' || $ziel === 'neu' ? '' : $wert,
            'bis' => $bis, 'user_id' => $userId]);
        // Erst alle Empfänger festhalten, dann pushen: Ein hängender Push-Dienst darf niemanden aus der Liste fallen lassen.
        foreach ($an as $p) { Db::run('INSERT IGNORE INTO partner_news_an (news_id, partner_id) VALUES (?, ?)', [$id, (int) $p['id']]); }
        $push = 0;
        foreach ($an as $p) {
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            $titel = $z['titel_' . $sp] !== '' ? $z['titel_' . $sp] : $z['titel_it'];
            $text = $z['text_' . $sp] !== '' ? $z['text_' . $sp] : $z['text_it'];
            $wohin = Partner::portalLink($p) . '&cc=1';
            try {
                $push += self::$push !== null ? (int) (self::$push)((int) $p['id'], $titel, mb_substr($text, 0, 140), $wohin) : (PartnerPost::push((int) $p['id'], $titel, mb_substr($text, 0, 140), $wohin) > 0 ? 1 : 0);
            } catch (Throwable $e) { /* ein kaputtes Abo hält die anderen nicht auf */ }
        }
        Db::run('UPDATE partner_news SET push_an = ? WHERE id = ?', [$push, $id]);
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur('partner_news', 'partner_news', $id, [], ['ziel' => $ziel, 'an' => count($an), 'push' => $push]);
        } catch (Throwable $e) { /* Prüfspur ist Beiwerk */ }
        return ['ok' => true, 'id' => $id, 'an' => count($an), 'push' => $push];
    }

    /** Was der Partner jetzt sieht: eigene, nicht weggeklickte, nicht abgelaufene, nicht zurückgezogene — neueste zuerst. */
    public static function fuerPartner(array $p, int $max = 3): array
    {
        $sp = in_array((string) ($p['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $zeilen = Db::all('SELECT n.* FROM partner_news n JOIN partner_news_an a ON a.news_id = n.id
                            WHERE a.partner_id = ? AND a.gelesen_am IS NULL AND n.zurueck_am IS NULL AND (n.bis IS NULL OR n.bis >= CURDATE())
                            ORDER BY n.id DESC LIMIT ' . max(1, min(10, $max)), [(int) $p['id']]);
        return array_map(static fn($n) => ['id' => (int) $n['id'],
            'titel' => (string) ($n['titel_' . $sp] !== '' ? $n['titel_' . $sp] : $n['titel_it']),
            'text' => (string) ($n['text_' . $sp] !== '' ? $n['text_' . $sp] : $n['text_it']),
            'link' => $n['link'] !== null ? (string) $n['link'] : null, 'am' => (string) $n['created_at']], $zeilen);
    }

    /** Weggeklickt — nur die eigene. */
    public static function gelesen(int $partnerId, int $newsId): bool
    {
        return Db::run('UPDATE partner_news_an SET gelesen_am = NOW() WHERE news_id = ? AND partner_id = ? AND gelesen_am IS NULL', [$newsId, $partnerId])->rowCount() === 1;
    }

    /** Zurückziehen: verschwindet bei allen von der Startseite (ein schon gesendeter Push lässt sich nicht zurückholen). */
    public static function zurueckziehen(int $newsId): bool
    {
        return Db::run('UPDATE partner_news SET zurueck_am = NOW() WHERE id = ? AND zurueck_am IS NULL', [$newsId])->rowCount() === 1;
    }

    /** Für die Verwaltung: gesendete Meldungen mit Empfängern und Weggeklickten. */
    public static function liste(int $max = 30): array
    {
        return Db::all('SELECT n.*, (SELECT COUNT(*) FROM partner_news_an a WHERE a.news_id = n.id) AS an,
                               (SELECT COUNT(*) FROM partner_news_an a WHERE a.news_id = n.id AND a.gelesen_am IS NOT NULL) AS gelesen
                          FROM partner_news n ORDER BY n.id DESC LIMIT ' . max(1, min(100, $max)));
    }
}
