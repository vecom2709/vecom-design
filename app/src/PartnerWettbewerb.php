<?php
declare(strict_types=1);

/**
 * Monatswettbewerb der Partner (27.09.2026, Uwe: Ja zu „Stufen & Monatswettbewerb“).
 *
 * Wer diesen Monat die meisten Verkäufe bringt, bei Gleichstand die meisten
 * neuen Kunden. Kein Preis und kein Geld -- es wäre ein Versprechen, das Uwe
 * nicht gegeben hat. Die Rangliste macht nur sichtbar, dass andere es auch
 * tun und dass es geht.
 *
 * Namen: Nur wer zugestimmt hat (partner.wettbewerb_name), steht mit dem
 * Vornamen da, alle anderen als „Partner“. Die eigene Zeile heißt „Sie“.
 * Keine Beträge, keine Kundennamen, keine Orte.
 *
 * Gezählt wird wie bei den Stufen: je Bestellung bzw. Vertrag einmal, ohne
 * Stornos. Der Monatsanfang kommt aus PHP (Europe/Rome), nicht aus NOW() --
 * die Datenbank läuft in UTC.
 */
final class PartnerWettbewerb
{
    public const ZEIGEN = 5;

    /**
     * @return array{monat:string, liste:list<array{rang:int, name:?string, verkaeufe:int, kunden:int, ich:bool}>, ich:?array, teilnehmer:int}
     */
    public static function monat(int $partnerId, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $ab = date('Y-m-01 00:00:00', $jetzt);
        $bis = date('Y-m-01 00:00:00', strtotime('first day of next month', $jetzt));
        $zeilen = Db::all(
            "SELECT p.id, p.name, p.wettbewerb_name,
                    (SELECT COUNT(DISTINCT COALESCE(CONCAT('o', pp.order_id), CONCAT('a', z.abo_id)))
                       FROM partner_provisionen pp JOIN payments z ON z.id = pp.payment_id
                      WHERE pp.partner_id = p.id AND pp.status NOT IN ('storniert','zurueckgeholt','rueckforderung')
                        AND pp.created_at >= ? AND pp.created_at < ?) AS verkaeufe,
                    (SELECT COUNT(*) FROM partner_zuordnungen pz WHERE pz.partner_id = p.id AND pz.created_at >= ? AND pz.created_at < ?) AS kunden
               FROM partner p WHERE p.status = 'aktiv'", [$ab, $bis, $ab, $bis]);
        $zeilen = array_values(array_filter($zeilen, static fn($z) => (int) $z['verkaeufe'] + (int) $z['kunden'] > 0 || (int) $z['id'] === $partnerId));
        usort($zeilen, static fn($a, $b) => [(int) $b['verkaeufe'], (int) $b['kunden'], (int) $a['id']] <=> [(int) $a['verkaeufe'], (int) $a['kunden'], (int) $b['id']]);

        $liste = []; $ich = null; $rang = 0; $vorher = null; $teilnehmer = 0;
        foreach ($zeilen as $i => $z) {
            $v = (int) $z['verkaeufe']; $k = (int) $z['kunden'];
            $leer = $v + $k === 0;
            if (!$leer) { $teilnehmer++; }
            // Gleichstand = gleicher Platz (1, 2, 2, 4).
            if ($vorher !== [$v, $k]) { $rang = $i + 1; $vorher = [$v, $k]; }
            $eigen = (int) $z['id'] === $partnerId;
            $zeile = ['rang' => $leer ? 0 : $rang, 'name' => $eigen ? null : ((int) $z['wettbewerb_name'] === 1 ? self::vorname((string) $z['name']) : null),
                      'verkaeufe' => $v, 'kunden' => $k, 'ich' => $eigen];
            if ($eigen) { $ich = $zeile; }
            if (!$leer && count($liste) < self::ZEIGEN) { $liste[] = $zeile; }
        }
        return ['monat' => date('Y-m', $jetzt), 'liste' => $liste, 'ich' => $ich, 'teilnehmer' => $teilnehmer];
    }

    /** Nur der erste Vorname, nie mehr. „Maria Grazia Rossi“ → „Maria“. */
    public static function vorname(string $name): string
    {
        $w = preg_split('/\s+/u', trim($name)) ?: [''];
        return mb_substr((string) $w[0], 0, 20);
    }

    public static function nameErlauben(int $partnerId, bool $ja): void
    {
        Db::run('UPDATE partner SET wettbewerb_name = ? WHERE id = ?', [$ja ? 1 : 0, $partnerId]);
    }
}
