<?php
declare(strict_types=1);

/**
 * Daten für die Mappe zum Vorbeibringen (27.09.2026, Uwe: Ja).
 *
 * Nur für das, was dem Partner gehört: ein eigener Schnellcheck oder ein
 * Betrieb, den er gerade reserviert hat. Fremde Checks oder Betriebe geben
 * null -- eine geratene Nummer in der Adresse darf keine fremde Firma zeigen.
 */
require_once __DIR__ . '/Akquise.php';

final class PartnerMappe
{
    /**
     * @return ?array{art:string, titel:string, ort:string, token?:string, host?:string, punkte?:list<array>, datum?:string,
     *                id?:int, ohne_website?:bool, branche?:string, stadt?:string}
     */
    public static function laden(array $p, array $q, string $sprache): ?array
    {
        $ck = (string) ($q['ck'] ?? '');
        if (preg_match('/^[0-9a-f]{32}$/', $ck)) {
            $z = Db::one('SELECT token, host, ergebnis, created_at FROM partner_checks WHERE token = ? AND partner_id = ?', [$ck, (int) $p['id']]);
            if (!$z) { return null; }
            $e = json_decode((string) $z['ergebnis'], true) ?: [];
            return ['art' => 'check', 'titel' => (string) $z['host'], 'ort' => '', 'token' => (string) $z['token'], 'host' => (string) $z['host'],
                    'punkte' => array_values(array_filter((array) ($e['punkte'] ?? []), 'is_array')), 'datum' => date('d.m.Y', strtotime((string) $z['created_at'])),
                    'ohne_website' => false];
        }
        $id = (int) ($q['firma'] ?? 0);
        if ($id > 0) {
            $f = Db::one('SELECT f.id, f.name, f.stadt, f.plz, f.branche, f.url FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                           WHERE r.partner_id = ? AND r.firma_id = ? AND r.bis >= CURDATE()', [(int) $p['id'], $id]);
            if (!$f) { return null; }
            return ['art' => 'firma', 'id' => (int) $f['id'], 'titel' => (string) $f['name'], 'ort' => trim(((string) ($f['plz'] ?? '')) . ' ' . ((string) ($f['stadt'] ?? ''))),
                    'stadt' => (string) ($f['stadt'] ?? ''), 'branche' => $f['branche'] ? Akquise::branchenName((string) $f['branche'], $sprache) : '',
                    'ohne_website' => trim((string) ($f['url'] ?? '')) === ''];
        }
        return null;
    }

    public static function link(array $p, array $ziel): string
    {
        return '/partner.php?' . http_build_query(['t' => $p['token'], 'druck' => 'mappe'] + $ziel);
    }
}
