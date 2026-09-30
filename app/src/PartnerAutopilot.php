<?php
declare(strict_types=1);

require_once __DIR__ . '/PartnerRecherche.php';

/**
 * Partner-Autopilot (29.09.2026, Uwe: Ja).
 *
 * Jeder aktive Partner bekommt jeden Morgen fünf passende Betriebe in seinem
 * Ort: mit Flyer zur Branche (eigener QR-Code), Gesprächsleitfaden und Route
 * für alle fünf. Er muss nur noch vorbeigehen -- ein persönlicher Besuch,
 * keine Mail. Die Betriebe sagen dann selbst Ja (QR-Code → Analyse) und
 * bekommen ihr Dashboard.
 *
 * Auswahl wie im Firmen-Finder (gleiche Sperren: nicht reserviert von anderen,
 * nicht von Vecom angeschrieben, keine Absage, kein Kunde), zuerst Betriebe
 * ohne Website, dann mit den größten Schwächen. Ein Betrieb kommt beim selben
 * Partner höchstens alle 30 Tage wieder.
 */
final class PartnerAutopilot
{
    public const JE_TAG = 5;
    public const PAUSE_TAGE = 30;

    public static function ort(array $p): string
    {
        return trim((string) ($p['heimatort'] ?? ''));
    }

    public static function ortSetzen(int $partnerId, string $ort): bool
    {
        $ort = trim(mb_substr(strip_tags($ort), 0, 80));
        if (mb_strlen($ort) < 2) { return false; }
        Db::run('UPDATE partner SET heimatort = ? WHERE id = ?', [$ort, $partnerId]);
        return true;
    }

    /** Aus der ersten echten Suche im Firmen-Finder, wenn noch kein Ort da ist. */
    public static function ortMerken(int $partnerId, string $ort): void
    {
        $ort = trim(mb_substr($ort, 0, 80));
        if (mb_strlen($ort) < 2) { return; }
        Db::run("UPDATE partner SET heimatort = ? WHERE id = ? AND (heimatort IS NULL OR heimatort = '')", [$ort, $partnerId]);
    }

    /**
     * Die Liste von heute (legt sie beim ersten Aufruf des Tages an).
     * @return list<array<string,mixed>>
     */
    public static function heute(array $p, string $sprache, ?string $datum = null): array
    {
        $datum ??= date('Y-m-d');
        $pid = (int) $p['id'];
        $ort = self::ort($p);
        if ($ort === '') { return []; }
        if ((int) Db::wert('SELECT COUNT(*) FROM partner_tagesliste WHERE partner_id = ? AND datum = ?', [$pid, $datum], 0) === 0) {
            self::anlegen($pid, $ort, $datum);
        }
        return array_map(static fn(array $z): array => [
            'id' => (int) $z['id'], 'name' => (string) $z['name'], 'adresse' => (string) ($z['adresse'] ?? ''),
            'ort' => trim(((string) ($z['plz'] ?? '')) . ' ' . ((string) ($z['stadt'] ?? ''))), 'stadt' => (string) ($z['stadt'] ?? ''),
            'plz' => (string) ($z['plz'] ?? ''), 'branche' => Akquise::branchenName($z['branche'], $sprache), 'branche_key' => (string) ($z['branche'] ?? ''),
            'chance' => PartnerRecherche::chance($z), 'domain' => (string) ($z['domain'] ?? ''), 'url' => (string) ($z['url'] ?? ''),
            'meine' => $z['res_partner'] !== null && (int) $z['res_partner'] === $pid,
        ], Db::all('SELECT f.id, f.name, f.adresse, f.plz, f.stadt, f.branche, f.domain, f.url, f.score, r.partner_id AS res_partner
                      FROM partner_tagesliste t JOIN akq_firmen f ON f.id = t.firma_id
                 LEFT JOIN partner_reservierungen r ON r.firma_id = f.id AND r.bis >= CURDATE()
                     WHERE t.partner_id = ? AND t.datum = ? AND f.gesperrt = 0
                  ORDER BY t.id', [$pid, $datum]));
    }

    private static function anlegen(int $pid, string $ort, string $datum): int
    {
        $wie = '%' . addcslashes($ort, '%_\\') . '%';
        $ids = Db::all("SELECT f.id
                          FROM akq_firmen f
                     LEFT JOIN partner_reservierungen r ON r.firma_id = f.id AND r.bis >= CURDATE()
                         WHERE f.gesperrt = 0 AND f.bestandskunde = 0
                           AND f.kontakt_status NOT IN ('abgelehnt','gesperrt','kunde','geantwortet')
                           AND (f.stadt LIKE ? OR f.plz = ? OR f.kreis LIKE ?)
                           AND (r.partner_id IS NULL OR r.partner_id = ?)
                           AND NOT EXISTS (SELECT 1 FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand'))
                           AND NOT EXISTS (SELECT 1 FROM partner_tagesliste t WHERE t.partner_id = ? AND t.firma_id = f.id
                                            AND t.datum > DATE_SUB(?, INTERVAL " . self::PAUSE_TAGE . " DAY))
                      ORDER BY (f.url IS NULL OR f.url = '') DESC, COALESCE(f.score, 0) DESC, MD5(CONCAT(f.id, ?, ?))
                         LIMIT " . self::JE_TAG, [$wie, $ort, $wie, $pid, $pid, $datum, $pid, $datum]);
        foreach ($ids as $z) {
            Db::run('INSERT IGNORE INTO partner_tagesliste (partner_id, firma_id, datum) VALUES (?, ?, ?)', [$pid, (int) $z['id'], $datum]);
        }
        return count($ids);
    }

    /** Route durch alle Adressen der Liste (Google Maps, ohne Abruf von uns). */
    public static function route(array $liste): string
    {
        $ziele = array_map(static fn(array $f): string => trim($f['name'] . ' ' . $f['adresse'] . ' ' . $f['ort']), $liste);
        if (!$ziele) { return ''; }
        $ziel = array_pop($ziele);
        return 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' . rawurlencode($ziel)
            . ($ziele ? '&waypoints=' . rawurlencode(implode('|', $ziele)) : '');
    }

    /**
     * Morgens (7–10 Uhr) einmal am Tag: Listen anlegen und Partnern mit
     * Handy-Benachrichtigung Bescheid geben. Gibt die Zahl der Meldungen zurück.
     */
    public static function morgen(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $stunde = (int) date('G', $jetzt);
        if ($stunde < 7 || $stunde > 10) { return 0; }
        $heute = date('Y-m-d', $jetzt);
        require_once __DIR__ . '/PartnerPost.php';
        $n = 0;
        require_once __DIR__ . '/PartnerSchutz.php';
        foreach (Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND " . PartnerSchutz::sqlFrei('p') . " AND p.heimatort IS NOT NULL AND p.heimatort <> ''
                            AND NOT EXISTS (SELECT 1 FROM partner_tagesliste t WHERE t.partner_id = p.id AND t.datum = ? AND t.gemeldet = 1)
                          LIMIT 200", [$heute]) as $p) {
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            $liste = self::heute($p, $sp, $heute);
            Db::run('UPDATE partner_tagesliste SET gemeldet = 1 WHERE partner_id = ? AND datum = ?', [(int) $p['id'], $heute]);
            if (!$liste) { continue; }
            $T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sp);
            try {
                $n += PartnerPost::push((int) $p['id'], $T('ap_push_titel'),
                    strtr($T('ap_push_text'), ['{n}' => (string) count($liste), '{ort}' => self::ort($p)]), Partner::portalLink($p) . '#heute') > 0 ? 1 : 0;
            } catch (Throwable $e) { /* ohne Handy-Meldung steht die Liste trotzdem im Dashboard */ }
        }
        return $n;
    }
}
