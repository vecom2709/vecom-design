<?php
declare(strict_types=1);

/**
 * Partner steuern: Rangliste und Weckruf (27.09.2026, Uwe: Ja).
 *
 * RANGLISTE: „Lohnt es sich?“ zeigte nur Partner mit Klicks oder Kunden --
 * die stillen fielen heraus, und genau die sind die Frage. Jetzt alle aktiven
 * und pausierten, sortierbar, mit letzter Aktivität und einem Merker „still“
 * (30 Tage kein Klick). Aktivität ist, was wir ohnehin wissen: Klicks, neue
 * Kunden, Nachrichten des Partners, gestaltete Seite, Foto, Schnellchecks,
 * Reservierungen. Wann er sich sein Dashboard ansieht, zählen wir nicht.
 *
 * WECKRUF: Nach 30 Tagen ohne einen Klick ein Hinweis aufs Handy -- mit einem
 * konkreten Vorschlag, nicht mit „Wir vermissen dich“. Höchstens einmal im
 * Monat, nur an Partner mit eingeschalteten Hinweisen, nur tagsüber. Die
 * Mail nach 60 Tagen (Partner::ruhendeErinnern) bleibt, wie sie ist.
 */
final class PartnerSteuerung
{
    public const STILL_TAGE = 30;
    public const SORTIERUNG = ['umsatz', 'kunden', 'klicks', 'letzte', 'name'];

    /** @return list<array<string,mixed>> */
    public static function rangliste(string $sort = 'umsatz', int $monate = 12): array
    {
        $sort = in_array($sort, self::SORTIERUNG, true) ? $sort : 'umsatz';
        /* Nach „Klicks auf 0“ fehlen die alten Besuche -- „still“ hieße dann nur
           „seit dem Zurücksetzen nichts“, nicht „30 Tage nichts“. */
        $frisch = Partner::klicksFrisch(self::STILL_TAGE);
        $zeilen = array_values(array_filter(Partner::auswertung($monate), static fn($z) => in_array($z['status'], ['aktiv', 'pausiert'], true)));
        foreach ($zeilen as &$z) {
            $id = (int) $z['id'];
            $z['klicks30'] = (int) self::still(static fn() => Db::wert('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ? AND tag >= ?',
                [$id, date('Y-m-d', strtotime('-' . self::STILL_TAGE . ' days'))], 0), 0);
            $p = Db::one('SELECT created_at, seite_am, foto_am, weckruf_am FROM partner WHERE id = ?', [$id]) ?? [];
            $spuren = array_filter([
                self::still(static fn() => Db::wert('SELECT MAX(tag) FROM partner_klicks WHERE partner_id = ?', [$id], null)),
                self::still(static fn() => Db::wert('SELECT MAX(tag) FROM partner_klicks_archiv WHERE partner_id = ?', [$id], null)),
                self::still(static fn() => Db::wert('SELECT MAX(created_at) FROM partner_zuordnungen WHERE partner_id = ?', [$id], null)),
                self::still(static fn() => Db::wert("SELECT MAX(created_at) FROM partner_nachrichten WHERE partner_id = ? AND von = 'partner'", [$id], null)),
                self::still(static fn() => Db::wert('SELECT MAX(created_at) FROM partner_checks WHERE partner_id = ?', [$id], null)),
                self::still(static fn() => Db::wert('SELECT MAX(created_at) FROM partner_reservierungen WHERE partner_id = ?', [$id], null)),
                $p['seite_am'] ?? null, $p['foto_am'] ?? null,
            ]);
            $z['letzte'] = $spuren ? (string) max(array_map('strval', $spuren)) : null;
            $alt = !empty($p['created_at']) && strtotime((string) $p['created_at']) < strtotime('-' . self::STILL_TAGE . ' days');
            $z['still'] = $z['status'] === 'aktiv' && $z['klicks30'] === 0 && $alt && !$frisch;
            $z['weckruf_am'] = $p['weckruf_am'] ?? null;
            $z['push'] = (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_push WHERE partner_id = ?', [$id], 0), 0) > 0;
        }
        unset($z);
        usort($zeilen, static fn($a, $b) => match ($sort) {
            'kunden' => [(int) $b['kunden'], (int) $b['umsatz']] <=> [(int) $a['kunden'], (int) $a['umsatz']],
            'klicks' => [(int) $b['klicks'], (int) $b['kunden']] <=> [(int) $a['klicks'], (int) $a['kunden']],
            'letzte' => strcmp((string) ($b['letzte'] ?? ''), (string) ($a['letzte'] ?? '')),
            'name' => strcasecmp((string) $a['name'], (string) $b['name']),
            default => [(int) $b['umsatz'], (int) $b['kunden'], (int) $b['klicks']] <=> [(int) $a['umsatz'], (int) $a['kunden'], (int) $a['klicks']],
        });
        return $zeilen;
    }

    /**
     * Aus dem Cronlauf. @return int zugestellte Weckrufe
     */
    public static function weckruf(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $stunde = (int) date('G', $jetzt);
        if ($stunde < 9 || $stunde >= 20) { return 0; }
        if (Partner::klicksFrisch(self::STILL_TAGE)) { return 0; }   // nach „Klicks auf 0“ erst 30 Tage zählen
        require_once __DIR__ . '/Texte.php';
        $grenze = date('Y-m-d H:i:s', $jetzt - self::STILL_TAGE * 86400);
        $n = 0;
        require_once __DIR__ . '/PartnerSchutz.php';
        foreach (Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND p.vereinbarung_am IS NOT NULL AND " . PartnerSchutz::sqlFrei('p') . " AND p.created_at < ?
                            AND EXISTS (SELECT 1 FROM partner_push pp WHERE pp.partner_id = p.id)
                            AND (p.weckruf_am IS NULL OR p.weckruf_am < ?)
                            AND NOT EXISTS (SELECT 1 FROM partner_klicks k WHERE k.partner_id = p.id AND k.tag >= ?)",
                [$grenze, $grenze, date('Y-m-d', $jetzt - self::STILL_TAGE * 86400)]) as $p) {
            // Erst vermerken, dann schicken: Ein hängender Push-Dienst darf keine Serie auslösen.
            Db::run('UPDATE partner SET weckruf_am = ? WHERE id = ?', [date('Y-m-d H:i:s', $jetzt), (int) $p['id']]);
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            $impuls = Texte::PARTNER_IMPULSE[((int) date('n', $jetzt) + (int) $p['id']) % count(Texte::PARTNER_IMPULSE)];
            $titel = Texte::h(Texte::PARTNER['weck_titel'], $sp);
            if (PartnerPost::push((int) $p['id'], $titel, Texte::h($impuls['text'], $sp), Partner::portalLink($p) . '#' . $impuls['anker']) > 0) { $n++; }
        }
        return $n;
    }

    /** @template T @param callable():T $f @param T $sonst @return T */
    private static function still(callable $f, mixed $sonst = null): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }
}
