<?php
declare(strict_types=1);

/* ==========================================================================
   PartnerKampagne.php — Kampagnen der Partner (Etappe 0c des Marketing
   Command Center, 05.10.2026). Grundlage für Campaign Builder, Startseite,
   QR-Center und Erfolge; diese Klasse ist nur das Datenmodell.

   EINE TABELLE: mk_kampagnen (Uwe: „Ja“). partner_id gesetzt = Partner-
   Kampagne. Solche Kampagnen sind NIE über /k/CODE erreichbar (MkKampagne::
   ausCode filtert sie aus) und tauchen in den Listen der Verwaltung nicht
   zwischen Uwes Kampagnen auf.

   KEINE ZWEITE TRACKING-WELT: Gezählt wird, was Spur und der Partnerlink
   ohnehin zählen — über die Kanäle des Partners: der Kampagnenlink
   /p/CODE/kampagne-N und die Materialien /p/CODE/wm-M (wm_entwuerfe mit
   kampagne_id = N). Besucher, Anfragen und Abschlüsse kommen aus spur_besuche
   und partner_zuordnungen (gleiche Rechnung wie Werbemittel::erfolg).

   NUR EIGENES: Jede Methode mit Partner-Bezug nimmt die Partner-ID und
   prüft sie in der Abfrage selbst — nie „erst laden, dann prüfen“.
   ========================================================================== */
final class PartnerKampagne
{
    /** Ziele (Uwes Liste „Was möchtest du erreichen?“). */
    public const ZIELE = ['neue_kunden', 'anfragen', 'bekanntheit', 'lokal', 'social', 'messe', 'eroeffnung', 'reaktivieren', 'check'];

    /** Branchen: die fünf mit fertigen Partner-Paketen (PartnerMarketing::BRANCHEN) und die ersten neuen Welten (Uwe: Ja zu Gastronomie, Handwerk, Beauty, Automotive, Einzelhandel). */
    public const BRANCHEN = ['gastro', 'unterkunft', 'handwerk', 'laden', 'praxis', 'beauty', 'automotive', 'sonstige'];

    /** Wohin ein Link/QR führt: '' = Partnerseite; sonst einer der Wege (PartnerSeite::wegZiel, WhatsApp). */
    public const WEGE = ['', 'preis', 'check', 'termin', 'wa'];

    public const STATUS = ['aktiv', 'pausiert', 'beendet'];

    /** VC-2026-00012 — wie die Marketing-ID der Materialien gebildet, nicht gespeichert. */
    public static function nummer(array $k): string
    {
        return sprintf('VC-%s-%05d', substr((string) $k['created_at'], 0, 4), (int) $k['id']);
    }

    /** VC-2026-00012 → 12 (das Jahr wird mitgeprüft). */
    public static function ausNummer(string $nr): ?int
    {
        if (!preg_match('~^VC-(\d{4})-(\d{5,8})$~', strtoupper(trim($nr)), $m)) { return null; }
        $jahr = Db::wert('SELECT YEAR(created_at) FROM mk_kampagnen WHERE id = ? AND partner_id IS NOT NULL', [(int) $m[2]], null);
        return $jahr !== null && (int) $jahr === (int) $m[1] ? (int) $m[2] : null;
    }

    /** Kanal des Kampagnenlinks: /p/CODE/kampagne-N. */
    public static function kanal(int $kampagneId): string { return 'kampagne-' . $kampagneId; }

    /**
     * Neue Kampagne eines Partners. $d: ziel, branche, region, designlinie, sprache, name (optional),
     * vorlage_id (optional, Vecom-Kampagne), ziel_weg (optional), budget_cents (optional). @return int id
     */
    public static function anlegen(array $p, array $d): int
    {
        $ziel = (string) ($d['ziel'] ?? '');
        if (!in_array($ziel, self::ZIELE, true)) { throw new InvalidArgumentException('Ziel unbekannt.'); }
        $branche = (string) ($d['branche'] ?? 'sonstige');
        if (!in_array($branche, self::BRANCHEN, true)) { throw new InvalidArgumentException('Branche unbekannt.'); }
        require_once __DIR__ . '/Designlinie.php';
        $linie = (string) ($d['designlinie'] ?? 'premium');
        if (!in_array($linie, Designlinie::LINIEN, true)) { throw new InvalidArgumentException('Designlinie unbekannt.'); }
        $sprache = in_array((string) ($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : (string) ($p['sprache'] ?? 'it');
        $weg = (string) ($d['ziel_weg'] ?? '');
        if (!in_array($weg, self::WEGE, true)) { throw new InvalidArgumentException('Ziel des Links unbekannt.'); }
        // Region: ein Ortsname, keine Liste von Orten (Uwe: „nur sinnvoll lokalisieren, kein Keyword-Spam“).
        $region = trim(preg_replace('~\s+~u', ' ', (string) ($d['region'] ?? '')) ?? '');
        if (mb_strlen($region) > 60 || preg_match('~[,;|/]~', $region)) { throw new InvalidArgumentException('Region: bitte ein Ort oder eine Gegend.'); }
        $vorlage = isset($d['vorlage_id']) ? (int) $d['vorlage_id'] : null;
        if ($vorlage !== null && !Db::one("SELECT id FROM mk_kampagnen WHERE id = ? AND partner_id IS NULL AND status = 'aktiv'", [$vorlage])) {
            throw new InvalidArgumentException('Vorlage nicht verfügbar.');
        }
        $budget = isset($d['budget_cents']) && (int) $d['budget_cents'] > 0 ? min((int) $d['budget_cents'], 10_000_000) : null;
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') { $name = self::nameVorschlag($ziel, $branche, $region, $sprache); }
        // Der Code muss in mk_kampagnen eindeutig sein (Spalte der Verwaltungs-Kampagnen) — für Partner nie als /k/ benutzt.
        do { $code = 'p' . (int) $p['id'] . '-' . bin2hex(random_bytes(4)); } while (Db::wert('SELECT id FROM mk_kampagnen WHERE code = ?', [$code], null) !== null);
        $id = (int) Db::insert('mk_kampagnen', [
            'partner_id' => (int) $p['id'], 'vorlage_id' => $vorlage, 'code' => $code, 'name' => mb_substr($name, 0, 120),
            'plattform' => 'partner', 'ziel' => '/', 'ziel_weg' => $weg, 'ziel_art' => $ziel, 'branche' => $branche,
            'land' => strtoupper((string) ($p['land'] ?? '')) === 'DE' ? 'DE' : 'IT', 'region' => $region, 'designlinie' => $linie,
            'sprache' => $sprache, 'budget_cents' => $budget, 'status' => 'aktiv', 'start_am' => date('Y-m-d'),
        ]);
        return $id;
    }

    /** „Neue Kunden · Gastronomie · Mainz“ — kurz, ohne Werbesprech. */
    public static function nameVorschlag(string $ziel, string $branche, string $region, string $sprache): string
    {
        require_once __DIR__ . '/Texte.php';
        $z = (string) (Texte::KAMPAGNE_ZIELE[$ziel][$sprache] ?? $ziel);
        $b = (string) (Texte::KAMPAGNE_BRANCHEN[$branche][$sprache] ?? $branche);
        return implode(' · ', array_filter([$z, $branche === 'sonstige' ? '' : $b, $region]));
    }

    /** Eine eigene Kampagne — oder null (fremde gibt es für den Partner nicht). */
    public static function laden(int $partnerId, int $id): ?array
    {
        return Db::one('SELECT * FROM mk_kampagnen WHERE id = ? AND partner_id = ?', [$id, $partnerId]) ?: null;
    }

    /** Alle eigenen Kampagnen, aktive zuerst, neueste oben. */
    public static function liste(int $partnerId): array
    {
        return Db::all("SELECT * FROM mk_kampagnen WHERE partner_id = ?
                        ORDER BY FIELD(status, 'aktiv', 'pausiert', 'beendet'), id DESC", [$partnerId]);
    }

    public static function statusSetzen(int $partnerId, int $id, string $status): bool
    {
        if (!in_array($status, self::STATUS, true)) { return false; }
        return Db::run('UPDATE mk_kampagnen SET status = ?, ende_am = IF(? = \'beendet\', CURDATE(), ende_am) WHERE id = ? AND partner_id = ?',
            [$status, $status, $id, $partnerId])->rowCount() === 1;
    }

    /** Ziel eines Links ändern (ohne Neudruck): je Kampagne oder je Material. Nur eigenes. */
    public static function zielSetzen(int $partnerId, string $art, int $id, string $weg): bool
    {
        if (!in_array($weg, self::WEGE, true)) { return false; }
        $tabelle = match ($art) { 'kampagne' => 'mk_kampagnen', 'material' => 'wm_entwuerfe', default => '' };
        if ($tabelle === '') { return false; }
        $st = Db::run("UPDATE $tabelle SET ziel_weg = ? WHERE id = ? AND partner_id = ?", [$weg, $id, $partnerId]);
        return $st->rowCount() === 1 || (bool) Db::one("SELECT id FROM $tabelle WHERE id = ? AND partner_id = ? AND ziel_weg = ?", [$id, $partnerId, $weg]);
    }

    /** Material einer eigenen Kampagne zuordnen (null = lösen). Beides muss dem Partner gehören. */
    public static function materialZuordnen(int $partnerId, int $entwurfId, ?int $kampagneId): bool
    {
        if ($kampagneId !== null && !self::laden($partnerId, $kampagneId)) { return false; }
        return Db::run('UPDATE wm_entwuerfe SET kampagne_id = ? WHERE id = ? AND partner_id = ?', [$kampagneId, $entwurfId, $partnerId])->rowCount() === 1
            || (bool) Db::one('SELECT id FROM wm_entwuerfe WHERE id = ? AND partner_id = ? AND kampagne_id <=> ?', [$entwurfId, $partnerId, $kampagneId]);
    }

    /**
     * Wohin führt ein Partnerkanal? Für /p/CODE/wm-M und /p/CODE/kampagne-N: das eingestellte Ziel —
     * beim Material sein eigenes, sonst das seiner Kampagne. '' = Partnerseite wie bisher.
     */
    public static function zielWeg(int $partnerId, ?string $kanal): string
    {
        if ($kanal === null || !preg_match('~^(wm|kampagne)-(\d{1,8})$~', $kanal, $m)) { return ''; }
        try {
            if ($m[1] === 'kampagne') {
                return (string) Db::wert("SELECT ziel_weg FROM mk_kampagnen WHERE id = ? AND partner_id = ? AND status = 'aktiv'", [(int) $m[2], $partnerId], '');
            }
            $e = Db::one('SELECT e.ziel_weg, k.ziel_weg AS k_weg, k.status AS k_status FROM wm_entwuerfe e
                           LEFT JOIN mk_kampagnen k ON k.id = e.kampagne_id AND k.partner_id = e.partner_id
                          WHERE e.id = ? AND e.partner_id = ?', [(int) $m[2], $partnerId]);
            if (!$e) { return ''; }
            if ((string) $e['ziel_weg'] !== '') { return (string) $e['ziel_weg']; }
            return ($e['k_status'] ?? '') === 'aktiv' ? (string) ($e['k_weg'] ?? '') : '';
        } catch (Throwable $e) { return ''; }
    }

    /** Kanäle einer Kampagne: ihr eigener Link und alle zugeordneten Materialien. @return list<string> */
    public static function kanaele(int $partnerId, int $id): array
    {
        $k = [self::kanal($id)];
        foreach (Db::all('SELECT id FROM wm_entwuerfe WHERE partner_id = ? AND kampagne_id = ?', [$partnerId, $id]) as $e) { $k[] = 'wm-' . (int) $e['id']; }
        return $k;
    }

    /**
     * Was die Kampagne gebracht hat — echte Zahlen, nichts geschätzt (Uwe: „keine erfundenen Daten“).
     * Scans = Summe der Material-Scans; Klicks = Aufrufe des Kampagnenlinks; Besucher, Anfragen,
     * Abschlüsse über alle Kanäle der Kampagne. Fremde Kampagne → null.
     */
    public static function zahlen(int $partnerId, int $id): ?array
    {
        if (!self::laden($partnerId, $id)) { return null; }
        $kanaele = self::kanaele($partnerId, $id);
        $in = implode(',', array_fill(0, count($kanaele), '?'));
        $scans = (int) Db::wert('SELECT COALESCE(SUM(scans), 0) FROM wm_entwuerfe WHERE partner_id = ? AND kampagne_id = ?', [$partnerId, $id], 0);
        $klicks = (int) Db::wert('SELECT (SELECT COALESCE(SUM(anzahl), 0) FROM partner_kanal_klicks WHERE partner_id = ? AND kanal = ?)
                                       + (SELECT COALESCE(SUM(anzahl), 0) FROM partner_kanal_klicks_archiv WHERE partner_id = ? AND kanal = ?)',
            [$partnerId, self::kanal($id), $partnerId, self::kanal($id)], 0);
        $besucher = (int) Db::wert("SELECT COUNT(DISTINCT visitor_id) FROM spur_besuche WHERE partner_id = ? AND kanal IN ($in) AND verdacht = 0", array_merge([$partnerId], $kanaele), 0);
        $anfragen = (int) Db::wert("SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ? AND kanal IN ($in)", array_merge([$partnerId], $kanaele), 0);
        $kunden = (int) Db::wert("SELECT COUNT(DISTINCT pp.customer_id) FROM partner_provisionen pp
                                    JOIN partner_zuordnungen z ON z.customer_id = pp.customer_id AND z.partner_id = pp.partner_id
                                   WHERE pp.partner_id = ? AND z.kanal IN ($in) AND pp.status NOT IN ('storniert','abgelehnt')", array_merge([$partnerId], $kanaele), 0);
        $provision = (int) Db::wert("SELECT COALESCE(SUM(pp.provision_cents), 0) FROM partner_provisionen pp
                                       JOIN partner_zuordnungen z ON z.customer_id = pp.customer_id AND z.partner_id = pp.partner_id
                                      WHERE pp.partner_id = ? AND z.kanal IN ($in) AND pp.status NOT IN ('storniert','abgelehnt','zurueckgeholt','rueckforderung')", array_merge([$partnerId], $kanaele), 0);
        return ['scans' => $scans, 'klicks' => $klicks, 'besucher' => $besucher, 'anfragen' => $anfragen, 'kunden' => $kunden, 'provision_cents' => $provision];
    }

    /* ======================================================================
       Kampagnen-Assistent (Etappe 2, 05.10.2026): aus Ziel, Branche und Ort
       eine Strategie in Sätzen und ein Paket aus dem, was es schon gibt —
       Kampagnenlink, Druckprodukte aus dem Katalog des Partners (mit echtem
       Endpreis), fertige Texte für WhatsApp, Beiträge, E-Mail, Story-Grafik.
       Nichts wird neu erfunden: Die Texte an Betriebe sind dieselben, die
       Uwe freigegeben hat (PARTNER_BRANCHEN, PARTNER_WERBUNG), nur mit dem
       Link dieser Kampagne — damit jeder Klick hier zählt.
       ====================================================================== */

    /** Höchstens so viele aktive oder pausierte Kampagnen je Partner. */
    public const HOECHSTENS_OFFEN = 20;

    /** Was ins Paket gehört — je Ziel, in dieser Reihenfolge. */
    public const PAKETE = [
        'neue_kunden'  => ['link', 'visitenkarte', 'flyer', 'whatsapp', 'post'],
        'anfragen'     => ['link', 'flyer', 'visitenkarte', 'whatsapp', 'post'],
        'bekanntheit'  => ['link', 'post', 'story', 'visitenkarte', 'aufkleber'],
        'lokal'        => ['link', 'flyer', 'aufkleber', 'visitenkarte', 'whatsapp'],
        'social'       => ['link', 'post', 'story', 'whatsapp'],
        'messe'        => ['link', 'rollup', 'flyer', 'visitenkarte', 'geschenk'],
        'eroeffnung'   => ['link', 'flyer', 'post', 'geschenk', 'aufkleber'],
        'reaktivieren' => ['link', 'whatsapp', 'email', 'visitenkarte'],
        'check'        => ['link', 'flyer', 'whatsapp', 'post'],
    ];

    /** Druckprodukte je Art: die erste Vorlage, die der Partner im Katalog hat, gilt. */
    public const DRUCK = [
        'visitenkarte' => ['visitenkarte'],
        'flyer'        => ['flyer_branche', 'flyer_a6', 'flyer_a5'],
        'aufkleber'    => ['aufkleber_50'],
        'rollup'       => ['rollup_85'],
        'geschenk'     => ['tasse_11', 'beutel', 'notizbuch', 'untersetzer', 'flasche'],
    ];

    /** Wohin der Link führt, wenn der Partner nichts wählt. */
    public const WEG_STANDARD = ['anfragen' => 'preis', 'check' => 'check'];

    /** Branchen der Kampagne → Gruppe der 51 Branchen-Flyer (PartnerFlyer::GRUPPEN). */
    public const FLYER_GRUPPE = ['gastro' => 'gast', 'unterkunft' => 'gast', 'handwerk' => 'bau', 'laden' => 'handel', 'automotive' => 'handel',
        'beauty' => 'gesundheit', 'praxis' => 'gesundheit', 'sonstige' => 'allgemein'];

    /** Branchen der Kampagne → fertige Branchen-Texte (PARTNER_BRANCHEN); ohne Eintrag die allgemeinen Vorlagen. */
    public const TEXT_BRANCHE = ['gastro' => 'gastro', 'unterkunft' => 'unterkunft', 'handwerk' => 'handwerk', 'laden' => 'laden',
        'beauty' => 'laden', 'praxis' => 'praxis'];

    /** Wie viele aktive oder pausierte Kampagnen der Partner hat. */
    public static function offen(int $partnerId): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_kampagnen WHERE partner_id = ? AND status IN ('aktiv','pausiert')", [$partnerId], 0);
    }

    /** Drei Sätze: wer, wie, woran man es sieht. $sprache = Sprache der Oberfläche. @return list<string> */
    public static function strategie(array $k, string $sprache): array
    {
        require_once __DIR__ . '/Texte.php';
        $K = Texte::PARTNER_KAMPAGNE;
        $t = static fn(array $x): string => Texte::h($x, $sprache);
        $ziel = (string) $k['ziel_art'];
        $region = trim((string) ($k['region'] ?? ''));
        return [
            strtr($t($K['s_wer']), ['{ziel}' => $t(Texte::KAMPAGNE_ZIELE[$ziel] ?? []), '{branche}' => $t(Texte::KAMPAGNE_BRANCHEN[(string) $k['branche']] ?? []),
                '{region}' => $region === '' ? '' : strtr($t($K['s_region']), ['{region}' => $region])]),
            $t($K['s_weg'][$ziel] ?? $K['s_weg']['neue_kunden']),
            $t($K['s_mess']),
        ];
    }

    /**
     * Das Paket einer eigenen Kampagne. $katalog = Werbemittel::katalog(…) des Partners (nur was er bestellen kann).
     * Texte an Betriebe in der Sprache der Kampagne; Beschriftungen in der Sprache der Oberfläche.
     * @return list<array{art:string, titel:string, satz:string, text?:string, betreff?:string, link?:string, produkt_id?:int, ab_cent?:int, teilen?:?string}>
     */
    public static function paket(array $p, array $k, string $sprache, array $katalog): array
    {
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/PartnerWerbung.php';
        require_once __DIR__ . '/PartnerVorlagen.php';
        require_once __DIR__ . '/Fmt.php';
        $K = Texte::PARTNER_KAMPAGNE;
        $t = static fn(array $x): string => Texte::h($x, $sprache);
        $ks = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : $sprache;   // Sprache der Werbemittel
        $link = PartnerWerbung::link($p, self::kanal((int) $k['id']));
        $ersatz = ['{link}' => $link, '{name}' => Partner::anzeigeName($p)];
        $tb = self::TEXT_BRANCHE[(string) $k['branche']] ?? null;
        $vorlage = static fn(string $kanal, string $id, string $teil) => strtr(PartnerVorlagen::text("werbung.$kanal.$id.$teil", $ks,
            Texte::h(Texte::PARTNER_WERBUNG['vorlagen'][$kanal][$id][$teil] ?? [], $ks)), $ersatz);
        $produkte = [];
        foreach ($katalog as $kat) { foreach ($kat['produkte'] as $pr) { $produkte[(string) $pr['vorlage']] = $pr; } }
        $aus = [];
        foreach (self::PAKETE[(string) $k['ziel_art']] ?? self::PAKETE['neue_kunden'] as $art) {
            switch ($art) {
                case 'link':
                    $aus[] = ['art' => 'link', 'titel' => $t($K['p']['link'][0]), 'satz' => strtr($t($K['p']['link'][1]), ['{weg}' => $t($K['wege'][(string) $k['ziel_weg']] ?? $K['wege'][''])]), 'link' => $link];
                    break;
                case 'whatsapp':
                    $text = $tb !== null ? strtr(Texte::h(Texte::PARTNER_BRANCHEN[$tb]['wa'], $ks), $ersatz) : $vorlage('whatsapp', 'persoenlich', 'text');
                    $aus[] = ['art' => 'whatsapp', 'titel' => $t($K['p']['whatsapp'][0]), 'satz' => $t($K['p']['whatsapp'][1]), 'text' => $text,
                              'teilen' => PartnerWerbung::teilen('whatsapp', $text, '', $link)];
                    break;
                case 'post':
                    $text = $tb !== null ? strtr(Texte::h(Texte::PARTNER_BRANCHEN[$tb]['post'], $ks), $ersatz) : $vorlage('instagram', 'post', 'text');
                    if (!str_contains($text, '#')) { $text .= "\n\n" . (['it' => '#ad', 'de' => '#Werbung', 'en' => '#ad'][$ks] ?? '#ad'); }   // Werbung kennzeichnen
                    $aus[] = ['art' => 'post', 'titel' => $t($K['p']['post'][0]), 'satz' => $t($K['p']['post'][1]), 'text' => $text];
                    break;
                case 'email':
                    $aus[] = ['art' => 'email', 'titel' => $t($K['p']['email'][0]), 'satz' => $t($K['p']['email'][1]),
                              'betreff' => $vorlage('email', 'kontakt', 'betreff'), 'text' => $vorlage('email', 'kontakt', 'text')];
                    break;
                case 'story':
                    $aus[] = ['art' => 'story', 'titel' => $t($K['p']['story'][0]), 'satz' => $t($K['p']['story'][1])];
                    break;
                default:   // Druck: nur, was im Katalog des Partners steht
                    foreach (self::DRUCK[$art] ?? [] as $v) {
                        if (!isset($produkte[$v])) { continue; }
                        $pr = $produkte[$v];
                        $aus[] = ['art' => $art, 'titel' => strtr($t($K['p']['druck'][0]), ['{produkt}' => (string) $pr['name']]),
                                  'satz' => strtr($t($K['p']['druck'][1]), ['{preis}' => Fmt::geld((int) $pr['ab_cent'])]),
                                  'produkt_id' => (int) $pr['id'], 'ab_cent' => (int) $pr['ab_cent'], 'vorlage' => $v];
                        break;
                    }
            }
        }
        return $aus;
    }

    /**
     * QR-Center (Etappe 3, 05.10.2026): alle eigenen Werbemittel mit eigenem QR-Code — Scans, Kampagne,
     * eingestelltes und tatsächlich wirksames Ziel (wie zielWeg: eigenes vor dem der aktiven Kampagne).
     * @return list<array{id:int, marketing_id:string, produkt:string, status:string, version:int, scans:int,
     *                    kampagne_id:?int, kampagne:string, kampagne_status:string, weg:string, wirksam:string}>
     */
    public static function qrListe(int $partnerId, string $sprache): array
    {
        require_once __DIR__ . '/Werbemittel.php';
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $z = Db::all("SELECT e.id, e.created_at, e.status, e.version, e.scans, e.ziel_weg, e.kampagne_id,
                             COALESCE(NULLIF(w.name_$sprache, ''), w.name_it) AS produkt, k.name AS k_name, k.status AS k_status, k.ziel_weg AS k_weg
                        FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id
                        LEFT JOIN mk_kampagnen k ON k.id = e.kampagne_id AND k.partner_id = e.partner_id
                       WHERE e.partner_id = ? AND e.status IN ('entwurf','freigegeben','ersetzt')
                       ORDER BY e.scans DESC, e.id DESC LIMIT 200", [$partnerId]);
        return array_map(static function (array $r): array {
            $eigen = (string) $r['ziel_weg'];
            $wirksam = $eigen !== '' ? $eigen : (($r['k_status'] ?? '') === 'aktiv' ? (string) ($r['k_weg'] ?? '') : '');
            return ['id' => (int) $r['id'], 'marketing_id' => Werbemittel::marketingId($r), 'produkt' => (string) $r['produkt'],
                    'status' => (string) $r['status'], 'version' => (int) $r['version'], 'scans' => (int) $r['scans'],
                    'kampagne_id' => $r['kampagne_id'] !== null && $r['k_name'] !== null ? (int) $r['kampagne_id'] : null,
                    'kampagne' => (string) ($r['k_name'] ?? ''), 'kampagne_status' => (string) ($r['k_status'] ?? ''),
                    'weg' => $eigen, 'wirksam' => $wirksam];
        }, $z);
    }

    /**
     * Was der Druck mindestens kostet und was ein Budget davon abdeckt (in der Reihenfolge des Pakets).
     * @return array{summe:int, n:int, budget:?int, reicht:bool, deckt:list<string>}
     */
    public static function budget(array $paket, ?int $budgetCents): array
    {
        $druck = array_values(array_filter($paket, static fn($x) => isset($x['ab_cent'])));
        $summe = array_sum(array_column($druck, 'ab_cent'));
        $deckt = []; $rest = (int) $budgetCents;
        foreach ($druck as $d) { if ($budgetCents !== null && $d['ab_cent'] <= $rest) { $deckt[] = $d['titel']; $rest -= $d['ab_cent']; } }
        return ['summe' => $summe, 'n' => count($druck), 'budget' => $budgetCents, 'reicht' => $budgetCents !== null && $budgetCents >= $summe, 'deckt' => $deckt];
    }
}
