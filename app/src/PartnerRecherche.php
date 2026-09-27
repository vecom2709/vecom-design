<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Kunden-Recherche für Partner (26.09.2026, Uwe: Ja zu Firmen-Finder,
 * Schnellcheck, Vorstellung und Gesprächsleitfaden).
 *
 * FIRMEN-FINDER: Der Partner sieht Betriebe aus unserer Akquise-Liste in
 * seinem Ort -- Name, Ort, Branche, Adresse und wie groß die Chance ist.
 * NICHT: Telefon, E-Mail, Ansprechpartner. Der Partner soll hingehen oder
 * jemanden fragen, der den Betrieb kennt; eine Anrufliste in fremden Händen
 * wäre eine Weitergabe von Daten, für die wir geradestehen müssten.
 *
 * RESERVIERUNG: Wer eine Firma reserviert, hat sie 60 Tage für sich -- kein
 * anderer Partner sieht sie als frei, und Vecoms eigene Akquise fasst sie
 * nicht an (AkquiseGate::pruefen). Was Vecom schon angeschrieben hat, ist
 * nicht reservierbar: Die Firma hat dann schon Post von uns.
 */
final class PartnerRecherche
{
    public const TAGE = 60;
    public const MAX_AKTIV = 25;
    public const SUCHEN_JE_TAG = 30;
    public const TREFFER = 30;

    /** Zählt eine Nutzung und sagt, ob sie noch erlaubt ist. */
    public static function zaehlen(int $partnerId, string $art, int $grenze): bool
    {
        $n = (int) Db::wert('SELECT anzahl FROM partner_zaehler WHERE partner_id = ? AND art = ? AND tag = CURDATE()', [$partnerId, $art], 0);
        if ($n >= $grenze) { return false; }
        Db::run('INSERT INTO partner_zaehler (partner_id, art, tag, anzahl) VALUES (?, ?, CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE anzahl = anzahl + 1', [$partnerId, $art]);
        return true;
    }

    /**
     * $web: Kennt die Liste den Ort noch nicht (oder nur alt), erst bei
     * OpenStreetMap nachsuchen (PartnerWebsuche, 27.09.2026). Aus der
     * Partnerseite immer an; die Kette schaltet es gezielt.
     *
     * @return array{ok:bool, grund?:string, treffer:list<array<string,mixed>>, web?:array}
     */
    public static function suchen(int $partnerId, string $ort, string $branche, string $sprache, bool $zaehlen = true, bool $web = false): array
    {
        $ort = trim(mb_substr($ort, 0, 80));
        if (mb_strlen($ort) < 2) { return ['ok' => false, 'grund' => 'fi_ort', 'treffer' => []]; }
        if ($branche !== '' && !isset(Akquise::branchen()[$branche])) { $branche = ''; }
        if ($zaehlen && !self::zaehlen($partnerId, 'suche', self::SUCHEN_JE_TAG)) { return ['ok' => false, 'grund' => 'fi_genug', 'treffer' => []]; }
        $webErg = null; $gebietName = '';
        if ($web) {
            require_once __DIR__ . '/PartnerWebsuche.php';
            $webErg = PartnerWebsuche::ergaenzen($ort, $branche);
            $gebietName = (string) ($webErg['gebiet'] ?? '');
        }
        $wie = '%' . addcslashes($ort, '%_\\') . '%';
        $zeilen = Db::all("SELECT f.id, f.name, f.stadt, f.plz, f.adresse, f.branche, f.url, f.domain, f.score,
                                  r.partner_id AS res_partner, r.bis AS res_bis,
                                  (SELECT COUNT(*) FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand')) AS angeschrieben
                             FROM akq_firmen f
                        LEFT JOIN partner_reservierungen r ON r.firma_id = f.id AND r.bis >= CURDATE()
                            WHERE f.gesperrt = 0 AND f.bestandskunde = 0
                              AND f.kontakt_status NOT IN ('abgelehnt','gesperrt','kunde','geantwortet')
                              AND (f.stadt LIKE ? OR f.plz = ? OR f.kreis LIKE ? OR (? <> '' AND f.stadt = ?))
                              AND (? = '' OR f.branche = ?)
                         ORDER BY (f.url IS NULL OR f.url = '') DESC, COALESCE(f.score, 0) DESC, f.name
                            LIMIT " . self::TREFFER, [$wie, $ort, $wie, $gebietName, $gebietName, $branche, $branche]);
        $treffer = [];
        foreach ($zeilen as $z) {
            $stand = $z['res_partner'] !== null
                ? ((int) $z['res_partner'] === $partnerId ? 'meine' : 'vergeben')
                : ((int) $z['angeschrieben'] > 0 ? 'vecom' : 'frei');
            // Vergebene Firmen erscheinen gar nicht: Ein anderer Partner soll
            // nicht erfahren, wo gerade jemand unterwegs ist.
            if ($stand === 'vergeben') { continue; }
            $treffer[] = [
                'id' => (int) $z['id'], 'name' => (string) $z['name'],
                'ort' => trim(((string) ($z['plz'] ?? '')) . ' ' . ((string) ($z['stadt'] ?? ''))),
                'adresse' => (string) ($z['adresse'] ?? ''),
                'branche' => Akquise::branchenName($z['branche'], $sprache),
                'chance' => self::chance($z), 'domain' => (string) ($z['domain'] ?? ''),
                'stand' => $stand, 'bis' => $z['res_bis'],
            ];
        }
        return ['ok' => true, 'treffer' => $treffer, 'web' => $webErg];
    }

    /** hoch = keine Website, mittel = Website mit deutlichen Mängeln, gering = ordentliche Website. */
    public static function chance(array $f): string
    {
        if (trim((string) ($f['url'] ?? '')) === '') { return 'hoch'; }
        $s = $f['score'] === null ? null : (int) $f['score'];
        if ($s === null) { return 'mittel'; }
        return $s >= 51 ? 'hoch' : ($s >= 31 ? 'mittel' : 'gering');
    }

    /** @return string ok|fi_weg|fi_vecom|fi_voll */
    public static function reservieren(int $partnerId, int $firmaId): string
    {
        return Db::transaktion(static function () use ($partnerId, $firmaId): string {
            $f = Db::one("SELECT id FROM akq_firmen WHERE id = ? AND gesperrt = 0 AND bestandskunde = 0
                            AND kontakt_status NOT IN ('abgelehnt','gesperrt','kunde','geantwortet') FOR UPDATE", [$firmaId]);
            if (!$f) { return 'fi_weg'; }
            if ((int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [$firmaId], 0) > 0) { return 'fi_vecom'; }
            $r = Db::one('SELECT partner_id, bis FROM partner_reservierungen WHERE firma_id = ? FOR UPDATE', [$firmaId]);
            if ($r && (int) $r['partner_id'] !== $partnerId && strtotime((string) $r['bis']) >= strtotime('today')) { return 'fi_weg'; }
            if (!$r || (int) $r['partner_id'] !== $partnerId) {
                $aktiv = (int) Db::wert('SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ? AND bis >= CURDATE()', [$partnerId], 0);
                if ($aktiv >= self::MAX_AKTIV) { return 'fi_voll'; }
            }
            Db::run('INSERT INTO partner_reservierungen (firma_id, partner_id, bis) VALUES (?, ?, DATE_ADD(CURDATE(), INTERVAL ' . self::TAGE . ' DAY))
                     ON DUPLICATE KEY UPDATE partner_id = VALUES(partner_id), bis = VALUES(bis), created_at = NOW()', [$firmaId, $partnerId]);
            return 'ok';
        }, 3);
    }

    public static function freigeben(int $partnerId, int $firmaId): void
    {
        Db::run('DELETE FROM partner_reservierungen WHERE firma_id = ? AND partner_id = ?', [$firmaId, $partnerId]);
    }

    /** @return list<array<string,mixed>> Die eigenen, noch gültigen Reservierungen. */
    public static function meine(int $partnerId, string $sprache): array
    {
        return array_map(static fn(array $z): array => [
            'id' => (int) $z['id'], 'name' => (string) $z['name'], 'ort' => trim(((string) ($z['plz'] ?? '')) . ' ' . ((string) ($z['stadt'] ?? ''))),
            'adresse' => (string) ($z['adresse'] ?? ''), 'branche' => Akquise::branchenName($z['branche'], $sprache),
            'chance' => self::chance($z), 'bis' => (string) $z['bis'], 'domain' => (string) ($z['domain'] ?? ''),
        ], Db::all('SELECT f.id, f.name, f.stadt, f.plz, f.adresse, f.branche, f.url, f.domain, f.score, r.bis
                      FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                     WHERE r.partner_id = ? AND r.bis >= CURDATE() ORDER BY r.bis', [$partnerId]));
    }

    /** Für AkquiseGate: Reserviert gerade ein Partner diese Firma? Dann Name und Datum. */
    public static function reserviertVon(int $firmaId): ?array
    {
        try {
            $r = Db::one('SELECT r.bis, p.name FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id
                           WHERE r.firma_id = ? AND r.bis >= CURDATE()', [$firmaId]);
            return $r ?: null;
        } catch (Throwable $e) { return null; }   // Tabelle noch nicht da (Migration läuft gleich)
    }
}
