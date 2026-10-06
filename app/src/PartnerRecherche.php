<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Kunden-Recherche für Partner (26.09.2026, Uwe: Ja zu Firmen-Finder,
 * Schnellcheck, Vorstellung und Gesprächsleitfaden).
 *
 * FIRMEN-FINDER: Der Partner sieht Betriebe aus unserer Akquise-Liste in
 * seinem Ort -- Name, Ort, Branche, Adresse und wie groß die Chance ist.
 * NICHT in der Suche: Telefon, E-Mail, Ansprechpartner -- sonst entstünde
 * eine Anrufliste über ganze Orte. Seit dem 27.09.2026 (Uwe: Ja) stehen
 * Telefon und E-Mail aber bei den EIGENEN Reservierungen (meine()), mit
 * Vorlagen und Regeln daneben (PartnerAnschreiben).
 *
 * RESERVIERUNG: Wer eine Firma reserviert, hat sie 60 Tage für sich -- kein
 * anderer Partner sieht sie als frei, und Vecoms eigene Akquise fasst sie
 * nicht an (AkquiseGate::pruefen). Was Vecom schon angeschrieben hat, ist
 * nicht reservierbar: Die Firma hat dann schon Post von uns.
 */
final class PartnerRecherche
{
    /** Akquise-CRM F (06.10.2026, Uwe: „Neue 30 Tage, alte bleiben“) — vorher 60; einmal verlängerbar (AkquisePartner). */
    public const TAGE = 30;
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
        if ($zaehlen) {   // Partner-Autopilot (29.09.2026): die erste Suche verrät, wo er unterwegs ist
            try { require_once __DIR__ . '/PartnerAutopilot.php'; PartnerAutopilot::ortMerken($partnerId, $ort); } catch (Throwable $e) { }
        }
        $webErg = null; $gebietName = '';
        if ($web) {
            require_once __DIR__ . '/PartnerWebsuche.php';
            $webErg = PartnerWebsuche::ergaenzen($ort, $branche);
            $gebietName = (string) ($webErg['gebiet'] ?? '');
        }
        $wie = '%' . addcslashes($ort, '%_\\') . '%';
        $zeilen = Db::all("SELECT f.id, f.name, f.stadt, f.plz, f.adresse, f.branche, f.url, f.domain, f.score, f.land,
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
        /* Kontrolleintrag (30.09.2026, PartnerSchutz): ab drei echten Treffern
           steht einer dieses Partners mitten in der Liste. Die Vereinbarung
           sagt es (Nr. 9). Ohne eingestellte Kontroll-Domain: keiner. */
        if (count($treffer) >= 3) {
            try {
                require_once __DIR__ . '/PartnerSchutz.php';
                $falle = PartnerSchutz::falleFuer($partnerId, $ort, $branche, (string) ($zeilen[0]['land'] ?? 'IT'));
                if ($falle) {
                    $pos = 1 + ((int) $falle['id'] * 7) % (count($treffer) - 1);
                    array_splice($treffer, $pos, 0, [PartnerSchutz::falleAlsTreffer($falle, $partnerId, $sprache)]);
                }
            } catch (Throwable $e) { }
        }
        if ($zaehlen) {
            require_once __DIR__ . '/PartnerSchutz.php';
            PartnerSchutz::protokoll($partnerId, 'suche', null, mb_substr($ort . ($branche !== '' ? ' · ' . $branche : '') . ' · ' . count($treffer) . ' Treffer: '
                . implode(',', array_map(static fn($t) => (string) $t['id'], $treffer)), 0, 255));
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

    /** @return string ok|fi_weg|fi_vecom|fi_voll|fi_tag */
    public static function reservieren(int $partnerId, int $firmaId): string
    {
        if ($firmaId < 0) {   // Kontrolleintrag (PartnerSchutz) -- sieht für den Partner aus wie jeder andere
            require_once __DIR__ . '/PartnerSchutz.php';
            return PartnerSchutz::falleReservieren($partnerId, -$firmaId, self::TAGE);
        }
        /* Höchstens 15 neue Reservierungen am Tag (30.09.2026): Wer Daten
           absaugen will, braucht dafür Wochen -- und steht im Protokoll. */
        $schon = (int) Db::wert('SELECT COUNT(*) FROM partner_reservierungen WHERE firma_id = ? AND partner_id = ? AND bis >= CURDATE()', [$firmaId, $partnerId], 0) > 0;
        if (!$schon) {
            require_once __DIR__ . '/PartnerSchutz.php';
            if (!self::zaehlen($partnerId, 'reserv', PartnerSchutz::RESERVIERUNGEN_JE_TAG)) { return 'fi_tag'; }
        }
        return Db::transaktion(static function () use ($partnerId, $firmaId): string {
            $f = Db::one("SELECT id FROM akq_firmen WHERE id = ? AND gesperrt = 0 AND bestandskunde = 0
                            AND kontakt_status NOT IN ('abgelehnt','gesperrt','kunde','geantwortet') FOR UPDATE", [$firmaId]);
            if (!$f) { return 'fi_weg'; }
            if ((int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [$firmaId], 0) > 0) { return 'fi_vecom'; }
            $r = Db::one('SELECT partner_id, bis FROM partner_reservierungen WHERE firma_id = ? FOR UPDATE', [$firmaId]);
            if ($r && (int) $r['partner_id'] !== $partnerId && strtotime((string) $r['bis']) >= strtotime('today')) { return 'fi_weg'; }
            if (!$r || (int) $r['partner_id'] !== $partnerId) {
                $aktiv = (int) Db::wert("SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ? AND bis >= CURDATE() AND (herkunft IS NULL OR herkunft <> 'vecom')", [$partnerId], 0);
                if ($aktiv >= self::MAX_AKTIV) { return 'fi_voll'; }
            }
            Db::run('INSERT INTO partner_reservierungen (firma_id, partner_id, bis) VALUES (?, ?, DATE_ADD(CURDATE(), INTERVAL ' . self::TAGE . ' DAY))
                     ON DUPLICATE KEY UPDATE partner_id = VALUES(partner_id), bis = VALUES(bis), created_at = NOW()', [$firmaId, $partnerId]);
            return 'ok';
        }, 3);
    }

    public static function freigeben(int $partnerId, int $firmaId): void
    {
        if ($firmaId < 0) { require_once __DIR__ . '/PartnerSchutz.php'; PartnerSchutz::falleFreigeben($partnerId, -$firmaId); return; }
        Db::run('DELETE FROM partner_reservierungen WHERE firma_id = ? AND partner_id = ?', [$firmaId, $partnerId]);
    }

    /** @return list<array<string,mixed>> Die eigenen, noch gültigen Reservierungen. */
    public static function meine(int $partnerId, string $sprache): array
    {
        $fallen = [];
        try { require_once __DIR__ . '/PartnerSchutz.php'; $fallen = PartnerSchutz::fallenMeine($partnerId, $sprache); } catch (Throwable $e) { }
        return array_merge($fallen, array_map(static fn(array $z): array => [
            'id' => (int) $z['id'], 'name' => (string) $z['name'], 'ort' => trim(((string) ($z['plz'] ?? '')) . ' ' . ((string) ($z['stadt'] ?? ''))),
            'adresse' => (string) ($z['adresse'] ?? ''), 'branche' => Akquise::branchenName($z['branche'], $sprache),
            'chance' => self::chance($z), 'bis' => (string) $z['bis'], 'domain' => (string) ($z['domain'] ?? ''),
            // Kontakt nur hier, bei eigenen Reservierungen (Uwe, 27.09.2026) -- nie in suchen().
            'telefon' => trim((string) ($z['telefon'] ?? '')), 'email' => trim((string) ($z['email'] ?? '')),
            'url' => trim((string) ($z['url'] ?? '')), 'land' => (string) ($z['land'] ?? 'IT'), 'stadt' => (string) ($z['stadt'] ?? ''),
            'plz' => (string) ($z['plz'] ?? ''), 'branche_key' => (string) ($z['branche'] ?? ''),
            'verlaengerbar' => self::verlaengerbar($z),   // Akquise-CRM F: einmal +30 Tage in den letzten sieben
        ], Db::all('SELECT f.id, f.name, f.stadt, f.plz, f.adresse, f.branche, f.url, f.domain, f.score, f.telefon, f.email, f.land, r.bis, r.verlaengert_am
                      FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                     WHERE r.partner_id = ? AND r.bis >= CURDATE() AND (r.herkunft IS NULL OR r.herkunft <> \'vecom\' OR r.anruf_status = \'zugestimmt\')
                     ORDER BY r.bis', [$partnerId])));
    }

    private static function verlaengerbar(array $z): bool
    {
        require_once __DIR__ . '/AkquisePartner.php';
        return AkquisePartner::kannVerlaengern($z);
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

    /* ==================================================================
       Weitere Quellen (27.09.2026, Uwe: Ja zu Suchknöpfen und „Betrieb
       selbst eintragen“). Google Maps, Indeed & Co. dürfen wir nicht
       automatisch auslesen -- ihre Bedingungen verbieten es. Der Partner
       darf dort aber selbst suchen: Die Knöpfe öffnen die Suche mit
       Branche und Ort in SEINEM Browser. Was er findet, trägt er ein.
       ================================================================== */

    /** @return list<array{art:string, url:string}> */
    public static function suchlinks(string $ort, string $branche, string $sprache = 'it'): array
    {
        $ort = trim(mb_substr($ort, 0, 80));
        if (mb_strlen($ort) < 2) { return []; }
        /* Ein deutscher Partner sucht in Deutschland (02.10.2026): deutsche Branchenwörter und
           deutsche Verzeichnisse statt paginegialle.it und it.indeed.com. */
        $de = $sprache === 'de';
        $was = $branche !== '' && isset(Akquise::branchen()[$branche]) ? Akquise::branchenName($branche, $de ? 'de' : 'it') : ($de ? 'Betrieb' : 'attività');
        $q = static fn(string $s): string => rawurlencode($s);
        $gastro = in_array($branche, ['restaurant', 'bar_cafe', 'hotel', 'ferienwohnung', 'agriturismo', 'tourismus', ''], true);
        $aus = [
            ['art' => 'maps', 'url' => 'https://www.google.com/maps/search/?api=1&query=' . $q($was . ' ' . $ort)],
            // Google-Suche (27.09.2026, Uwe): findet auch Betriebe ohne Maps-Eintrag, etwa über Facebook- oder Branchenbuchseiten.
            ['art' => 'google', 'url' => 'https://www.google.com/search?q=' . $q($was . ' ' . $ort)],
            ['art' => 'pagine', 'url' => $de ? 'https://www.gelbeseiten.de/suche/' . $q($was) . '/' . $q($ort) : 'https://www.paginegialle.it/ricerca/' . $q($was) . '/' . $q($ort)],
            ['art' => 'facebook', 'url' => 'https://www.facebook.com/search/pages/?q=' . $q($was . ' ' . $ort)],
            ['art' => 'indeed', 'url' => $de ? 'https://de.indeed.com/jobs?q=' . $q($was) . '&l=' . $q($ort) : 'https://it.indeed.com/offerte-lavoro?q=' . $q($was) . '&l=' . $q($ort)],
        ];
        if ($gastro) { $aus[] = ['art' => 'tripadvisor', 'url' => 'https://www.tripadvisor.' . ($de ? 'de' : 'it') . '/Search?q=' . $q($was . ' ' . $ort)]; }
        return $aus;
    }

    public const EINTRAEGE_JE_TAG = 20;

    /**
     * Der Partner trägt einen Betrieb ein, den er selbst gefunden hat --
     * er landet in der Akquise-Liste (Quelle partner:ID) und ist sofort für
     * ihn reserviert. Gibt es ihn schon, gilt die normale Reservierung.
     *
     * @return array{ok:bool, grund?:string, firma?:int, neu?:bool}
     */
    public static function eintragen(int $partnerId, array $d): array
    {
        $name = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) ($d['name'] ?? ''))) ?? '', 0, 160));
        $ort = trim(mb_substr(strip_tags((string) ($d['ort'] ?? '')), 0, 80));
        $branche = (string) ($d['branche'] ?? '');
        $land = in_array($d['land'] ?? 'IT', ['IT', 'DE'], true) ? (string) ($d['land'] ?? 'IT') : 'IT';
        $url = trim((string) ($d['website'] ?? ''));
        if (mb_strlen($name) < 3) { return ['ok' => false, 'grund' => 'fe_name']; }
        if (mb_strlen($ort) < 2) { return ['ok' => false, 'grund' => 'fe_ort']; }
        if (!isset(Akquise::branchen()[$branche])) { return ['ok' => false, 'grund' => 'fe_branche']; }
        if ($url !== '') {
            if (!preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://[^/\s]+\.[a-z]{2,}~i', $url)) { return ['ok' => false, 'grund' => 'fe_website']; }
        }
        if (!self::zaehlen($partnerId, 'eintrag', self::EINTRAEGE_JE_TAG)) { return ['ok' => false, 'grund' => 'fi_genug'];  }
        $r = Akquise::firmaMelden(['name' => $name, 'land' => $land, 'stadt' => $ort, 'adresse' => trim(mb_substr(strip_tags((string) ($d['adresse'] ?? '')), 0, 200)) ?: null,
            'url' => $url !== '' ? $url : null, 'branche' => $branche, 'quelle' => 'partner:' . $partnerId . ':' . substr(sha1(mb_strtolower($name . '|' . $ort)), 0, 12)]);
        if (!empty($r['gesperrt'])) { return ['ok' => false, 'grund' => 'fe_gesperrt']; }
        $res = self::reservieren($partnerId, (int) $r['id']);
        if ($res !== 'ok') { return ['ok' => false, 'grund' => $res, 'firma' => (int) $r['id']]; }
        Akquise::protokoll((int) $r['id'], 'partner', 'Vom Partner eingetragen und reserviert', ['partner_id' => $partnerId]);
        return ['ok' => true, 'firma' => (int) $r['id'], 'neu' => (bool) $r['neu']];
    }
}
