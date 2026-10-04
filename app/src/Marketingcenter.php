<?php
declare(strict_types=1);

require_once __DIR__ . '/Werbemittel.php';

/**
 * Aufbau des Partner-Marketingcenters (04.10.2026, Schritt 2 „Grundstruktur“).
 *
 * Die 13 Bereiche aus Uwes Vorgabe. Sieben davon sind Produktbereiche (dort
 * stehen bestellbare Werbemittel), die anderen sechs sind Sichten auf das, was
 * es schon gibt: Übersicht, Digital Marketing (die vorhandenen Beiträge,
 * Bilder und die Signatur im Reiter „Werben“), Meine Designs (wm_entwuerfe),
 * Meine Bestellungen (wm_bestellungen), Favoriten (wm_favoriten) und
 * Marketing-Erfolge (Scans, Besucher, Anfragen je Marketing-ID).
 *
 * Nichts wird erfunden: Ein Produktbereich ohne freigeschaltetes Produkt
 * sagt „in Vorbereitung“ — er zeigt keine Platzhalter-Produkte.
 *
 * Alles hier liest nur Zeilen des eigenen Partners (partner_id im WHERE).
 */
final class Marketingcenter
{
    /** Reihenfolge der Karten. */
    /* 'geschenke' seit 04.10.2026 (Uwe: „auch Produkte für Betriebe, die Partner als Geschenk oder Mitbringsel
       kaufen und nutzen können“) — die 14. Karte; die übrigen 13 sind die der Vorgabe. */
    public const BEREICHE = ['uebersicht', 'print', 'pos', 'textil', 'fahrzeug', 'event', 'digital', 'premium', 'geschenke', 'starter',
                             'designs', 'bestellungen', 'favoriten', 'erfolge'];

    /** Bereiche, in denen Produkte stehen können (Auswahl in der Verwaltung). */
    public const PRODUKT_BEREICHE = ['print', 'pos', 'textil', 'fahrzeug', 'event', 'premium', 'geschenke', 'starter'];

    /** Ohne eigene Angabe steht ein Produkt im Bereich seiner Kategorie. */
    public const KATEGORIE_BEREICH = ['visitenkarten' => 'print', 'flyer' => 'print', 'aufkleber' => 'pos',
                                      'aufsteller' => 'pos', 'textil' => 'textil', 'werbeartikel' => 'premium'];

    /** Bereich eines Produkts: eigene Angabe, sonst die der Kategorie, sonst Print. */
    public static function bereich(?string $eigen, string $kategorieSlug): string
    {
        if ($eigen !== null && in_array($eigen, self::PRODUKT_BEREICHE, true)) { return $eigen; }
        return self::KATEGORIE_BEREICH[$kategorieSlug] ?? 'print';
    }

    /**
     * Katalog (Werbemittel::katalog) nach Bereichen statt nach Kategorien.
     * @return array<string, list<array>> nur Produktbereiche, in der Reihenfolge von BEREICHE
     */
    public static function nachBereich(array $katalog): array
    {
        $aus = array_fill_keys(self::PRODUKT_BEREICHE, []);
        foreach ($katalog as $k) {
            foreach ($k['produkte'] as $x) {
                $b = self::bereich($x['bereich'] ?? null, (string) ($k['slug'] ?? ''));
                $aus[$b][] = $x + ['kategorie' => (string) $k['name']];
            }
        }
        return $aus;
    }

    // ---- Favoriten ------------------------------------------------------------

    /** @return list<int> Produkt-IDs, neueste zuerst */
    public static function favoriten(int $partnerId): array
    {
        return array_map('intval', array_column(Db::all('SELECT produkt_id FROM wm_favoriten WHERE partner_id = ? ORDER BY created_at DESC, produkt_id', [$partnerId]), 'produkt_id'));
    }

    /**
     * Merken oder vergessen. Nur Produkte, die der Partner sehen kann (aktiv).
     * @return bool|null true = jetzt gemerkt, false = jetzt entfernt, null = Produkt gibt es nicht
     */
    public static function favoritUmschalten(int $partnerId, int $produktId): ?bool
    {
        if (Db::run('DELETE FROM wm_favoriten WHERE partner_id = ? AND produkt_id = ?', [$partnerId, $produktId])->rowCount() > 0) { return false; }
        if (!Db::wert('SELECT id FROM wm_produkte WHERE id = ? AND aktiv = 1', [$produktId])) { return null; }
        Db::run('INSERT IGNORE INTO wm_favoriten (partner_id, produkt_id) VALUES (?, ?)', [$partnerId, $produktId]);
        return true;
    }

    // ---- Meine Designs und Marketing-Erfolge ------------------------------------

    /** Status, die als „Design“ gelten: alles außer dem halbfertigen „entsteht“. */
    public const DESIGN_STATUS = ['entwurf', 'freigegeben', 'ersetzt'];

    /**
     * Alle Designs des Partners, neueste zuerst, ohne Dateien. Auch ersetzte:
     * Was gedruckt wurde, ist weiter unterwegs, und sein QR-Code zählt weiter.
     * @return list<array{id:int, produkt_id:int, produkt:string, vorlage:string, status:string, created_at:string,
     *                    freigegeben_am:?string, marketing_id:string, wahl:array, erfolg:array}>
     */
    public static function designs(int $partnerId, string $sprache, int $max = 60): array
    {
        $sprache = in_array($sprache, Werbemittel::SPRACHEN, true) ? $sprache : 'it';
        $zeilen = Db::all("SELECT e.id, e.produkt_id, e.status, e.created_at, e.freigegeben_am, e.wahl, e.mockup_status, w.vorlage,
                                  COALESCE(NULLIF(w.name_$sprache, ''), w.name_it) AS produkt
                             FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id
                            WHERE e.partner_id = ? AND e.status IN ('entwurf','freigegeben','ersetzt')
                            ORDER BY e.id DESC LIMIT " . max(1, min(200, $max)), [$partnerId]);
        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'], 'produkt_id' => (int) $r['produkt_id'], 'produkt' => (string) $r['produkt'],
            'vorlage' => (string) $r['vorlage'], 'status' => (string) $r['status'], 'created_at' => (string) $r['created_at'],
            'freigegeben_am' => $r['freigegeben_am'] !== null ? (string) $r['freigegeben_am'] : null,
            'marketing_id' => Werbemittel::marketingId($r), 'wahl' => (array) json_decode((string) $r['wahl'], true),
            'erfolg' => Werbemittel::erfolg($partnerId, (int) $r['id']),
            'foto' => $r['mockup_status'] === 'fertig',          // Produktfoto der Druckerei zum Herunterladen
        ], $zeilen);
    }

    /**
     * Summe über alle Designs und die besten (nach Anfragen, dann Scans).
     * @param list<array> $designs aus designs()
     * @return array{summe: array{scans:int, besucher:int, anfragen:int, abschluesse:int}, beste: list<array>, quote:?float}
     */
    public static function erfolge(array $designs, int $beste = 5): array
    {
        $s = ['scans' => 0, 'besucher' => 0, 'anfragen' => 0, 'abschluesse' => 0];
        foreach ($designs as $d) { foreach ($s as $k => $_) { $s[$k] += (int) $d['erfolg'][$k]; } }
        $mit = array_values(array_filter($designs, static fn($d) => $d['erfolg']['scans'] > 0 || $d['erfolg']['anfragen'] > 0));
        usort($mit, static fn($a, $b) => [$b['erfolg']['abschluesse'], $b['erfolg']['anfragen'], $b['erfolg']['scans']]
                                      <=> [$a['erfolg']['abschluesse'], $a['erfolg']['anfragen'], $a['erfolg']['scans']]);
        return ['summe' => $s, 'beste' => array_slice($mit, 0, $beste),
                'quote' => $s['scans'] > 0 ? round(100 * $s['anfragen'] / $s['scans'], 1) : null];
    }
}
