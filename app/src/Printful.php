<?php
declare(strict_types=1);

require_once __DIR__ . '/DruckereiSchnittstelle.php';


/* ==========================================================================
   Printful.php — dritte angebundene Druckerei des Marketing Centers
   (04.10.2026, Uwe: „bringe trotzdem Printful zusätzlich mit rein“).

   QUELLE: die offizielle OpenAPI-Beschreibung
   developers.printful.com/docs/openapi.json (Version 1.0), gelesen am
   04.10.2026, und der öffentliche Katalog (GET /products/724).
     Zugang       privater Schlüssel aus dem Printful-Konto, Kopfzeile
                  „Authorization: Bearer …“; bei einem Konto-Schlüssel
                  zusätzlich „X-PF-Store-Id“ ('store' in der Einstellung)
     Bestellung   POST https://api.printful.com/orders — ohne ?confirm=true
                  ein ENTWURF (Uwe bestätigt im Printful-Dashboard), mit
                  confirm=true sofort in die Fertigung
                  external_id, recipient {name, company, address1, city,
                  zip, country_code, phone}, items [{variant_id, quantity,
                  files [{type: default|back, url}]}]
     Lesen        GET /orders/@{external_id} → result.status, result.shipments
                  [{tracking_number, tracking_url}]
     Preis        POST /orders/estimate-costs (gleicher Rumpf, ohne Dateien)
                  → result.costs {currency, subtotal, shipping, vat, tax, total}
     Druckfläche  GET /mockup-generator/printfiles/724 → printfiles [{width,
                  height, dpi}], variant_printfiles [{variant_id, placements}]
   Webhooks: keine Signatur in der Doku → nicht benutzt, der Cron liest nach.

   FORMAT: Printful druckt Visitenkarten nur 3,5 × 2 Zoll. Die Karte wird
   eingepasst (PartnerKarten::eingepasst, Uwes Entscheidung) und der Partner
   sieht diese Fassung vor der Freigabe. Die Bildgröße VORLAGE ist 4 × 2,5
   Zoll bei 300 dpi = 1200 × 750 px: so meldet Printful die Druckfläche selbst
   (GET /mockup-generator/printfiles/724, vom Server gelesen 05.10.2026).
   Endformat 3,5 × 2 Zoll = 1050 × 600 px → 150 px mehr in beiden Richtungen,
   also 1/4 Zoll (RAND = 75 px) Beschnitt je Seite. Die erste Fassung hatte
   1125 × 675 (1/8 Zoll) angenommen; die Flächenprüfung unten hat das vor dem
   ersten Auftrag abgefangen. auftragSenden() prüft weiter das
   Seitenverhältnis gegen Printfuls Meldung und sendet bei Abweichung NICHTS.

   SICHERHEIT WIE BEI DEN ANDEREN: Modus „entwurf“, bis in config.local.php
   'modus' => 'auftrag' steht. Senden genau einmal, Fehler bleiben stehen.
   ========================================================================== */
final class Printful implements DruckereiAnbieter, DruckereiPreise
{
    public const NAME = 'Printful';
    public const BASIS = 'https://api.printful.com';
    public const PRODUKT = 724;                  // „Set of Business Cards“
    public const VORLAGE = [1200, 750];          // px, siehe Kopf
    public const RAND = 75;                      // px Beschnitt je Seite (1/4 Zoll), siehe Kopf
    public const LAENDER = ['IT', 'DE'];

    /**
     * Was wir bei Printful drucken, je Vorlage (04.10.2026, Tasse dazu): Katalogprodukt, unsere Bildgröße
     * (Seitenverhältnis wird vor jedem Auftrag gegen Printfuls eigene Druckfläche geprüft), welche Datei an
     * welche Druckstelle geht, die Variante fürs Produktfoto und was beim Angebot als Material steht.
     * Material der Tasse aus Printfuls Katalogtext (GET /products/19): Keramik, spülmaschinen- und mikrowellenfest.
     */
    public const ARTEN = [
        'visitenkarte' => ['produkt' => 724, 'px' => [1200, 750], 'rand' => 75, 'plaetze' => ['default' => 'front', 'back' => 'back'], 'dateien' => ['default' => 'pf_vorn', 'back' => 'pf_hinten'], 'mockup' => 18554,
                           'material' => 'Munken Lynx 300 g, 90 × 50 mm (eingepasst)'],
        'tasse_11'     => ['produkt' => 19, 'px' => [2700, 1050], 'dateien' => ['default' => 'pf_vorn'], 'mockup' => 1320,
                           'material' => 'Keramiktasse weiß glänzend, 11 oz (325 ml), spülmaschinen- und mikrowellenfest'],
        // Geschenke (05.10.2026, Uwe: „ja“ zu den Vorschlägen). Bildgröße = Printfuls Druckfläche, vom Server abgefragt
        // (druckflaechenHolen); Material aus Printfuls Katalogtext (GET /products/{id}, öffentlich, 05.10.2026).
        'notizbuch'    => ['produkt' => 474, 'px' => [1725, 2625], 'plaetze' => ['default' => 'front', 'back' => 'back'], 'dateien' => ['default' => 'pf_vorn', 'back' => 'pf_hinten'], 'mockup' => 12141,
                           'material' => 'Spiralnotizbuch 14,5 × 21 cm, 140 Seiten gepunktet, Umschlag 352 g/m² soft-touch, Metallspirale'],
        'flasche'      => ['produkt' => 382, 'px' => [2557, 1582], 'dateien' => ['default' => 'pf_vorn'], 'mockup' => 10798,
                           'material' => 'Edelstahl-Thermosflasche 500 ml, doppelwandig, weiß glänzend, auslaufsicher, Handwäsche'],
        'untersetzer'  => ['produkt' => 611, 'px' => [1181, 1181], 'dateien' => ['default' => 'pf_vorn'], 'mockup' => 15662,
                           'material' => 'Untersetzer 95 × 95 mm, MDF mit Kork-Rücken, Hochglanz, runde Ecken, hitzebeständig'],
        'beutel'       => ['produkt' => 367, 'px' => [1500, 1500], 'plaetze' => ['default' => 'front'], 'dateien' => ['default' => 'pf_vorn'], 'mockup' => 10457,
                           'material' => 'Tragetasche schwarz, 100 % Bio-Baumwolle 272 g/m², 40,6 × 35,6 × 12,7 cm, Druck vorn'],
    ];

    /**
     * Name der Druckstelle im Mockup-Generator und in den Druckflächen. Aufträge nennen die Datei nach Printfuls
     * Produktdateien (GET /products/{id} → files: default, back), der Mockup-Generator nach den Druckflächen
     * (variant_printfiles → placements). Bei Karte, Notizbuch und Beutel heißt dieselbe Stelle dort „front“
     * statt „default“ — so in der Server-Abfrage vom 05.10.2026 gelesen. Darum: erst den eigenen Namen, sonst „front“.
     */
    public static function platzImGenerator(string $platz, array $placements): string
    {
        if (isset($placements[$platz])) { return $platz; }
        return $platz === 'default' && isset($placements['front']) ? 'front' : $platz;
    }

    /** Druckstellen-Namen des Mockup-Generators für eine Art: 'plaetze' (in der Abfrage vom 05.10.2026 gelesen), sonst die eigenen. */
    private static function generatorPlaetze(string $vorlage): array
    {
        $art = self::ARTEN[$vorlage];
        $aus = [];
        foreach (array_keys($art['dateien']) as $platz) { $aus[$platz] = (string) ($art['plaetze'][$platz] ?? $platz); }
        return $aus;
    }

    /**
     * Printful-Produkte, deren Druckfläche wir kennen wollen — die gebauten (ARTEN) und die nächsten
     * Geschenke (Uwe 04.10.2026: „ja“ zu allen Vorschlägen). [Katalogprodukt, Variante] aus GET /products/{id}
     * (öffentlich, in der EU hergestellt, geprüft 04.10.2026). Die Druckfläche selbst liefert nur die
     * Schnittstelle mit Schlüssel — darum fragt der Server, nicht wir.
     */
    public const KANDIDATEN = [
        'visitenkarte' => [724, 18554], 'tasse_11' => [19, 1320], 'notizbuch' => [474, 12141],
        'beutel' => [367, 10457], 'flasche' => [382, 10798], 'untersetzer' => [611, 15662],
    ];

    /**
     * Druckflächen aller KANDIDATEN bei Printful abfragen und in settings ('pf_druckflaechen') ablegen:
     * je Name und Druckstelle Breite, Höhe, dpi, fill_mode, can_rotate. Nur lesen. @return int Zahl der Produkte
     */
    public static function druckflaechenHolen(): int
    {
        if (!self::bereit()) { return 0; }
        $aus = [];
        foreach (self::KANDIDATEN as $name => [$produkt, $variante]) {
            try { $r = self::rufen('GET', '/mockup-generator/printfiles/' . $produkt, null); } catch (Throwable $e) { self::$letzterGrund = $e->getMessage(); continue; }
            $d = (array) (json_decode($r['body'], true)['result'] ?? []);
            if ($r['code'] !== 200 || !$d) { self::$letzterGrund = self::grund($r); continue; }
            $flaechen = [];
            foreach ((array) ($d['printfiles'] ?? []) as $f) { $flaechen[(int) ($f['printfile_id'] ?? 0)] = $f; }
            foreach ((array) ($d['variant_printfiles'] ?? []) as $vp) {
                if ((int) ($vp['variant_id'] ?? 0) !== $variante) { continue; }
                foreach ((array) ($vp['placements'] ?? []) as $platz => $fid) {
                    $f = $flaechen[(int) $fid] ?? null;
                    if (!$f) { continue; }
                    $aus[$name][(string) $platz] = ['b' => (int) ($f['width'] ?? 0), 'h' => (int) ($f['height'] ?? 0), 'dpi' => (int) ($f['dpi'] ?? 0),
                        'fill' => (string) ($f['fill_mode'] ?? ''), 'drehen' => !empty($f['can_rotate'])];
                }
            }
        }
        if ($aus) {
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('pf_druckflaechen', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                [json_encode(['am' => date('Y-m-d H:i'), 'flaechen' => $aus], JSON_UNESCAPED_UNICODE)]);
        }
        return count($aus);
    }

    /** Zuletzt abgefragte Druckflächen. @return array{am:string, flaechen:array} */
    public static function druckflaechen(): array
    {
        $d = (array) json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'pf_druckflaechen'", [], ''), true);
        return ['am' => (string) ($d['am'] ?? ''), 'flaechen' => (array) ($d['flaechen'] ?? [])];
    }

    /** Vorlage eines Entwurfs (über sein Produkt). */
    private static function vorlageVon(int $entwurfId): string
    {
        return (string) Db::wert('SELECT w.vorlage FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id WHERE e.id = ?', [$entwurfId], '');
    }

    /** Liegen alle Printful-Dateien dieses Entwurfs vor? */
    private static function dateienDa(int $entwurfId, array $art): bool
    {
        $spalten = array_map(static fn(string $f): string => 'datei_' . $f, array_values($art['dateien']));
        $e = Db::one('SELECT ' . implode(', ', $spalten) . ' FROM wm_entwuerfe WHERE id = ?', [$entwurfId]);
        if (!$e) { return false; }
        // Auch die Größe muss stimmen: Freigaben vor dem 05.10.2026 haben die Karte in 1125 × 675 px (falsch
        // angenommener Beschnitt). Die gelten als fehlend — der Partner gibt neu frei und sieht dabei die richtige.
        foreach ($spalten as $sp) {
            $g = is_string($e[$sp] ?? null) && $e[$sp] !== '' ? @getimagesizefromstring($e[$sp]) : false;
            if (!$g || $g[0] !== $art['px'][0] || $g[1] !== $art['px'][1]) { return false; }
        }
        return true;
    }

    /** Prüfnaht für die Kette: fn(string $methode, string $url, array $kopf, ?string $rumpf): array{code:int, body:string} */
    public static $netz = null;

    /** Grund der letzten Absage (HTTP-Status und Printfuls eigener Text), nie der Schlüssel. */
    public static string $letzterGrund = '';

    private static function cfg(): array
    {
        $c = Config::get('printful', []);
        return is_array($c) ? $c : [];
    }

    public static function bereit(?string $land = null): bool
    {
        if (trim((string) (self::cfg()['api'] ?? '')) === '') { return false; }
        return $land === null || in_array(strtoupper($land), self::LAENDER, true);
    }

    /** 'entwurf' (Voreinstellung) oder 'auftrag' (sofort in die Fertigung). */
    public static function modus(): string
    {
        return (string) (self::cfg()['modus'] ?? '') === 'auftrag' ? 'auftrag' : 'entwurf';
    }

    /** Sendet eine bezahlte Bestellung an Printful (genau einmal). @return array{ok:bool, grund:string, id?:string} */
    public static function auftragSenden(int $bestellungId): array
    {
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'Printful-Schlüssel fehlt in config.local.php.']; }
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        if (!$b || $b['status'] !== 'bezahlt') { return ['ok' => false, 'grund' => 'Nur bezahlte Bestellungen gehen an den Drucker.']; }
        $ad = (array) json_decode((string) $b['adresse'], true);
        $land = strtoupper((string) ($ad['land'] ?? ''));
        if (!in_array($land, self::LAENDER, true)) { return ['ok' => false, 'grund' => 'Printful wird nur für Italien und Deutschland benutzt.']; }
        $items = []; $jeVorlage = [];
        foreach (Db::all('SELECT * FROM wm_positionen WHERE bestellung_id = ? ORDER BY id', [$bestellungId]) as $i => $x) {
            $a = Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'printful'", [(int) $x['variante_id']]);
            if (!$a || !ctype_digit((string) $a['artikel'])) { return ['ok' => false, 'grund' => 'Für „' . $x['variante'] . '“ ist keine Printful-Variante eingetragen.']; }
            $vorlage = self::vorlageVon((int) $x['entwurf_id']);
            $art = self::ARTEN[$vorlage] ?? null;
            if (!$art) { return ['ok' => false, 'grund' => 'Printful druckt „' . $x['variante'] . '“ nicht (keine Printful-Art für diese Vorlage).']; }
            if (!self::dateienDa((int) $x['entwurf_id'], $art)) {
                return ['ok' => false, 'grund' => 'Zu „' . $x['variante'] . '“ fehlt ' . ($vorlage === 'visitenkarte' ? 'die eingepasste Fassung (90 × 50 mm)' : 'das Printful-Bild')
                    . ', die der Partner gesehen hat. Ältere Freigaben haben sie nicht — der Partner muss neu freigeben.'];
            }
            $files = [];
            foreach ($art['dateien'] as $platz => $fassung) { $files[] = ['type' => $platz, 'url' => Druckerei::dateiLink((int) $x['entwurf_id'], $fassung)]; }
            $items[] = ['external_id' => $b['nummer'] . '-' . ($i + 1), 'variant_id' => (int) $a['artikel'], 'quantity' => (int) $a['menge'] * (int) $x['menge'], 'files' => $files];
            $jeVorlage[$vorlage][] = (int) $a['artikel'];
        }
        if (!$items) { return ['ok' => false, 'grund' => 'Die Bestellung hat keine Position.']; }
        // Vor dem Senden: passt unser Bild zur Druckfläche, die Printful selbst meldet? Sonst nichts senden.
        foreach ($jeVorlage as $vorlage => $ids) {
            $passt = self::flaechePruefen($ids, $vorlage);
            if ($passt !== '') { return ['ok' => false, 'grund' => $passt]; }
        }
        $koerper = ['external_id' => (string) $b['nummer'], 'shipping' => 'STANDARD', 'recipient' => self::empfaenger($ad), 'items' => $items];
        if (!Druckerei::sperren($bestellungId, self::NAME)) { return ['ok' => false, 'grund' => 'Diese Bestellung wurde schon gesendet (oder es läuft gerade).']; }
        try {
            $r = self::rufen('POST', '/orders' . (self::modus() === 'auftrag' ? '?confirm=true' : ''), $koerper);
        } catch (Throwable $ex) {
            Druckerei::fehler($bestellungId, 'Keine Antwort von Printful: ' . $ex->getMessage() . ' — im Printful-Konto nachsehen, ob ' . $b['nummer'] . ' trotzdem angelegt wurde.');
            return ['ok' => false, 'grund' => 'Keine Antwort von Printful. Nichts wird wiederholt.'];
        }
        $d = json_decode($r['body'], true);
        if ($r['code'] < 200 || $r['code'] >= 300 || !is_array($d) || !isset($d['result']['id'])) {
            $grund = self::grund($r);
            Druckerei::fehler($bestellungId, 'Printful lehnte ab (' . $grund . ')');
            return ['ok' => false, 'grund' => 'Printful lehnte ab: ' . $grund];
        }
        Druckerei::erledigt($bestellungId, (string) $d['result']['id']);
        return ['ok' => true, 'grund' => '', 'id' => (string) $d['result']['id']];
    }

    /** Was die Bestellung bei Printful JETZT kostet (estimate-costs, Gesamtpreis in EUR) — null, wenn nicht abrufbar. */
    public static function preisJetzt(int $bestellungId): ?int
    {
        $b = Db::one('SELECT adresse FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        $ad = (array) json_decode((string) ($b['adresse'] ?? ''), true);
        $land = strtoupper((string) ($ad['land'] ?? ''));
        if (!in_array($land, self::LAENDER, true)) { return null; }
        $items = [];
        foreach (Db::all('SELECT variante_id, menge FROM wm_positionen WHERE bestellung_id = ?', [$bestellungId]) as $x) {
            $a = Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'printful'", [(int) $x['variante_id']]);
            if (!$a || !ctype_digit((string) $a['artikel'])) { return null; }
            $items[] = ['variant_id' => (int) $a['artikel'], 'quantity' => (int) $a['menge'] * (int) $x['menge']];
        }
        if (!$items) { return null; }
        try { $r = self::rufen('POST', '/orders/estimate-costs', ['recipient' => self::musterEmpfaenger($land), 'items' => $items]); } catch (Throwable $e) { return null; }
        $c = (array) (json_decode($r['body'], true)['result']['costs'] ?? []);
        if ($r['code'] !== 200 || !isset($c['total']) || strtoupper((string) ($c['currency'] ?? '')) !== 'EUR') { return null; }
        $t = (int) round((float) $c['total'] * 100);
        return $t > 0 ? $t : null;
    }

    /** Probe-Entwurf mit der Musterkarte (siehe Druckerei::probeSenden) — ein Pack, nie mit confirm. */
    public static function probeSenden(): array
    {
        require_once __DIR__ . '/Druckerei.php';
        $a = Db::one("SELECT a.artikel FROM wm_anbieter_produkte a JOIN wm_varianten v ON v.id = a.variante_id JOIN wm_produkte w ON w.id = v.produkt_id
                       WHERE a.anbieter = 'printful' AND w.vorlage = 'visitenkarte' ORDER BY a.menge, a.id LIMIT 1");   // Probe = Musterkarte
        if (!$a || !ctype_digit((string) $a['artikel'])) { return ['ok' => false, 'grund' => 'Keine Printful-Variante zugeordnet.']; }
        $passt = self::flaechePruefen([(int) $a['artikel']]);
        if ($passt !== '') { return ['ok' => false, 'grund' => $passt]; }
        $ref = 'PROBE-' . date('Ymd-His');
        try {
            $r = self::rufen('POST', '/orders', ['external_id' => $ref, 'shipping' => 'STANDARD', 'recipient' => self::empfaenger(Druckerei::MUSTER_ADRESSE),
                'items' => [['external_id' => $ref . '-1', 'variant_id' => (int) $a['artikel'], 'quantity' => 1, 'files' => [
                    ['type' => 'default', 'url' => Druckerei::dateiLink(0, 'probe_pf_vorn', 2)],
                    ['type' => 'back', 'url' => Druckerei::dateiLink(0, 'probe_pf_hinten', 2)],
                ]]]]);
        } catch (Throwable $e) { return ['ok' => false, 'grund' => 'Keine Antwort von Printful: ' . $e->getMessage()]; }
        $d = json_decode($r['body'], true);
        if ($r['code'] < 200 || $r['code'] >= 300 || !isset($d['result']['id'])) { return ['ok' => false, 'grund' => 'Printful lehnte ab (' . self::grund($r) . ')']; }
        return ['ok' => true, 'grund' => '', 'id' => (string) $d['result']['id'], 'ref' => $ref];
    }

    /** Cron: Stand nachlesen. Sendung da → WmBestellung::versendet (Mail an den Partner); abgelehnt → Meldung. */
    public static function nachsehen(): int
    {
        if (!self::bereit()) { return 0; }
        require_once __DIR__ . '/WmBestellung.php';
        $n = 0;
        foreach (Db::all("SELECT id, nummer FROM wm_bestellungen WHERE anbieter = ? AND status = 'beim_drucker' AND anbieter_status = 'auftrag' LIMIT 30", [self::NAME]) as $b) {
            try {
                $r = self::rufen('GET', '/orders/@' . rawurlencode((string) $b['nummer']), null);
                if ($r['code'] !== 200) { continue; }
                $o = (array) (json_decode($r['body'], true)['result'] ?? []);
                $status = (string) ($o['status'] ?? '');
                foreach ((array) ($o['shipments'] ?? []) as $s) {
                    $nr = trim((string) ($s['tracking_number'] ?? ''));
                    $url = (string) ($s['tracking_url'] ?? '');
                    if ($nr === '' && $url === '') { continue; }
                    WmBestellung::versendet((int) $b['id'], $nr !== '' ? mb_substr($nr, 0, 120) : 'Printful ' . $b['nummer'], str_starts_with($url, 'https://') ? mb_substr($url, 0, 390) : '');
                    $n++;
                    continue 2;
                }
                if (in_array($status, ['failed', 'canceled'], true)) {
                    Db::run("UPDATE wm_bestellungen SET anbieter_status = 'fehler', anbieter_fehler = ? WHERE id = ? AND anbieter_status = 'auftrag'",
                        ['Printful meldet „' . $status . '“ — im Printful-Dashboard nachsehen.', (int) $b['id']]);
                    Events::melden('wm_druckerei_fehler', 'Printful: ' . $b['nummer'] . ' ' . $status, 'schlecht', 'Printful meldet „' . $status . '“.', '/werbemittel/bestellungen');
                }
            } catch (Throwable $e) { error_log('Printful::nachsehen ' . $b['id'] . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    /**
     * Printful-Preise aller zugeordneten Auflagen für IT und DE holen und als
     * Angebot eintragen: was Vecom zahlt (Druck + Versand + Steuer laut
     * Printful). Nur in Euro — sonst wäre der Vergleich falsch.
     */
    public static function preiseAktualisieren(): int
    {
        if (!self::bereit()) { return 0; }
        require_once __DIR__ . '/Werbemittel.php';
        self::$letzterGrund = '';
        $n = 0;
        foreach (Db::all("SELECT a.variante_id, a.artikel, a.menge, w.vorlage FROM wm_anbieter_produkte a JOIN wm_varianten v ON v.id = a.variante_id
                           JOIN wm_produkte w ON w.id = v.produkt_id WHERE a.anbieter = 'printful'") as $z) {
            foreach (self::LAENDER as $land) {
                try {
                    $r = self::rufen('POST', '/orders/estimate-costs', ['recipient' => self::musterEmpfaenger($land),
                        'items' => [['variant_id' => (int) $z['artikel'], 'quantity' => (int) $z['menge']]]]);
                    $c = (array) (json_decode($r['body'], true)['result']['costs'] ?? []);
                    if ($r['code'] !== 200 || !isset($c['total'])) { self::$letzterGrund = self::grund($r); continue; }
                    if (strtoupper((string) ($c['currency'] ?? '')) !== 'EUR') { self::$letzterGrund = 'Printful rechnet in ' . ($c['currency'] ?? '?') . ' — im Printful-Konto die Währung auf EUR stellen.'; continue; }
                    $total = (int) round((float) $c['total'] * 100);
                    $netto = (int) round(((float) ($c['subtotal'] ?? 0) - (float) ($c['discount'] ?? 0) + (float) ($c['shipping'] ?? 0)) * 100);
                    if ($total <= 0) { self::$letzterGrund = 'kein Preis in der Antwort'; continue; }
                    Werbemittel::angebotSpeichern((int) $z['variante_id'], [
                        'anbieter' => self::NAME, 'land' => $land, 'preis_eur' => number_format($total / 100, 2, ',', ''),
                        'netto_eur' => $netto > 0 ? number_format($netto / 100, 2, ',', '') : '',
                        'papier' => (self::ARTEN[$z['vorlage']]['material'] ?? 'Printful') . ', Variante ' . $z['artikel'] . ' × ' . $z['menge'],
                        'lieferung' => 'Standardversand · automatisch', 'link' => 'https://www.printful.com/dashboard', 'geprueft_am' => date('Y-m-d'),
                    ]);
                    $n++;
                } catch (Throwable $e) { self::$letzterGrund = 'keine Antwort: ' . mb_substr($e->getMessage(), 0, 200); }
            }
        }
        return $n;
    }

    /**
     * Fragt die Druckfläche bei Printful ab und vergleicht das Seitenverhältnis
     * mit VORLAGE (1 % Spielraum). Leer = passt, sonst der Grund.
     */
    public static function flaechePruefen(array $variantenIds, string $vorlage = 'visitenkarte'): string
    {
        $art = self::ARTEN[$vorlage] ?? null;
        if (!$art) { return 'Printful druckt diese Vorlage nicht.'; }
        try {
            $r = self::rufen('GET', '/mockup-generator/printfiles/' . $art['produkt'], null);
        } catch (Throwable $e) { return 'Druckfläche bei Printful nicht abrufbar: ' . $e->getMessage(); }
        $d = (array) (json_decode($r['body'], true)['result'] ?? []);
        if ($r['code'] !== 200 || !$d) { return 'Druckfläche bei Printful nicht abrufbar (' . self::grund($r) . ').'; }
        $flaechen = [];
        foreach ((array) ($d['printfiles'] ?? []) as $f) { $flaechen[(int) ($f['printfile_id'] ?? 0)] = $f; }
        $soll = $art['px'][0] / $art['px'][1];
        foreach ((array) ($d['variant_printfiles'] ?? []) as $vp) {
            if (!in_array((int) ($vp['variant_id'] ?? 0), $variantenIds, true)) { continue; }
            foreach (array_keys($art['dateien']) as $platz) {
                $f = $flaechen[(int) ($vp['placements'][self::platzImGenerator($platz, (array) ($vp['placements'] ?? []))] ?? 0)] ?? null;
                if (!$f || (int) ($f['height'] ?? 0) <= 0) { return 'Printful meldet für Variante ' . $vp['variant_id'] . ' keine Druckfläche „' . $platz . '“.'; }
                $ist = (int) $f['width'] / (int) $f['height'];
                $istHoch = (int) $f['height'] / (int) $f['width'];
                if (abs($ist - $soll) / $soll > 0.01 && !(!empty($f['can_rotate']) && abs($istHoch - $soll) / $soll <= 0.01)) {
                    return 'Printful-Druckfläche ' . $f['width'] . ' × ' . $f['height'] . ' px passt nicht zu unserem Bild ' . $art['px'][0] . ' × ' . $art['px'][1] . ' px — nichts gesendet.';
                }
            }
        }
        return '';
    }

    // ---- Produktfoto (Mockup-Generator, 04.10.2026) ------------------------------------
    /*  Quelle: offizielle OpenAPI v1.0 (developers.printful.com/docs/openapi.json, geprüft 04.10.2026):
        POST /mockup-generator/create-task/{id} {variant_ids, format, width, files[{placement, image_url}]}
        → result.task_key; GET /mockup-generator/task?task_key= → result.status (pending|completed|failed),
        result.mockups[].mockup_url. Grenze: 2 (neuer Shop) bis 10 Aufträge je 60 s, sonst 60 s Sperre. */

    /** Variante, die fotografiert wird (50 Stück — Karte und Papier sind bei allen Auflagen gleich). */
    public const MOCKUP_VARIANTE = 18554;

    /**
     * Foto für einen Entwurf bei Printful bestellen — einmal; ohne eingepasste Fassung nicht.
     * @return string ok|aus|fehlt|grenze|fehler
     */
    public static function mockupAnstossen(int $entwurfId): string
    {
        if (!self::bereit()) { return 'aus'; }
        require_once __DIR__ . '/Druckerei.php';
        $art = self::ARTEN[self::vorlageVon($entwurfId)] ?? null;
        if (!$art || !self::dateienDa($entwurfId, $art) || !Db::one('SELECT id FROM wm_entwuerfe WHERE id = ? AND mockup_status IS NULL', [$entwurfId])) { return 'fehlt'; }
        $files = [];
        $gp = self::generatorPlaetze(self::vorlageVon($entwurfId));
        foreach ($art['dateien'] as $platz => $fassung) { $files[] = ['placement' => $gp[$platz], 'image_url' => Druckerei::dateiLink($entwurfId, $fassung, 3)]; }
        // 1600 px: groß genug zum Herunterladen für Beiträge und die eigene Seite (Printful erlaubt bis 2000).
        $koerper = ['variant_ids' => [$art['mockup']], 'format' => 'jpg', 'width' => 1600, 'files' => $files];
        try { $r = self::rufen('POST', '/mockup-generator/create-task/' . $art['produkt'], $koerper); }
        catch (Throwable $ex) { self::$letzterGrund = $ex->getMessage(); return 'fehler'; }
        if ($r['code'] === 429) { self::$letzterGrund = self::grund($r); return 'grenze'; }   // später wieder: Status bleibt leer
        $k = (string) (json_decode($r['body'], true)['result']['task_key'] ?? '');
        if ($r['code'] !== 200 || !preg_match('~^[A-Za-z0-9_.:-]{4,80}$~', $k)) {
            self::$letzterGrund = self::grund($r);
            Db::run("UPDATE wm_entwuerfe SET mockup_status = 'fehler', mockup_am = NOW() WHERE id = ?", [$entwurfId]);
            return 'fehler';
        }
        Db::run("UPDATE wm_entwuerfe SET mockup_task = ?, mockup_status = 'wartet', mockup_am = NOW() WHERE id = ? AND mockup_status IS NULL", [$k, $entwurfId]);
        return 'ok';
    }

    /** Cron: fertige Fotos abholen und speichern; Liegengebliebenes (älter als 1 Tag) gilt als Fehler. @return int abgeholt */
    public static function mockupsHolen(int $max = 10): int
    {
        if (!self::bereit()) { return 0; }
        $n = 0;
        foreach (Db::all("SELECT id, mockup_task, mockup_am FROM wm_entwuerfe WHERE mockup_status = 'wartet' ORDER BY id LIMIT " . max(1, min(50, $max))) as $e) {
            try { $r = self::rufen('GET', '/mockup-generator/task?task_key=' . rawurlencode((string) $e['mockup_task']), null); }
            catch (Throwable $ex) { continue; }
            $d = (array) (json_decode($r['body'], true)['result'] ?? []);
            $st = (string) ($d['status'] ?? '');
            if ($r['code'] === 200 && $st === 'completed') {
                $bild = self::fotoLaden((string) ($d['mockups'][0]['mockup_url'] ?? ''));
                if ($bild !== null) {
                    Db::run("UPDATE wm_entwuerfe SET mockup = ?, mockup_status = 'fertig', mockup_am = NOW() WHERE id = ?", [$bild, (int) $e['id']]);
                    // Weitere Ansichten (Rückseite, andere Winkel): bis zu drei, gleiche Herkunftsprüfung wie das Hauptfoto.
                    $weitere = [];
                    foreach ((array) ($d['mockups'] ?? []) as $i => $m) {
                        if ($i > 0 && !empty($m['mockup_url'])) { $weitere[] = [(string) $m['mockup_url'], (string) ($m['placement'] ?? '')]; }
                        foreach ((array) ($m['extra'] ?? []) as $x) { if (!empty($x['url'])) { $weitere[] = [(string) $x['url'], (string) ($x['title'] ?? $x['option'] ?? '')]; } }
                    }
                    $nr = 0;
                    foreach ($weitere as [$url, $titel]) {
                        if ($nr >= 3) { break; }
                        $w = self::fotoLaden($url);
                        if ($w === null) { continue; }
                        $nr++;
                        Db::run('INSERT INTO wm_produktfotos (entwurf_id, nr, titel, bild) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE titel = VALUES(titel), bild = VALUES(bild)',
                            [(int) $e['id'], $nr, mb_substr(trim($titel), 0, 80), $w]);
                    }
                    $n++;
                    continue;
                }
                Db::run("UPDATE wm_entwuerfe SET mockup_status = 'fehler' WHERE id = ?", [(int) $e['id']]);
            } elseif ($st === 'failed' || strtotime((string) $e['mockup_am']) < time() - 86400) {
                Db::run("UPDATE wm_entwuerfe SET mockup_status = 'fehler' WHERE id = ?", [(int) $e['id']]);
            }
        }
        return $n;
    }

    // ---- Produktfoto je Gestaltung (04.10.2026, Uwe: „das Original-Mockup inklusive des Bedruckten zeigen,
    //      dass der Partner weiß, was er bestellt — bei allen Produkten, wo es geht“) ----------------------

    /** Alle Kombinationen Vorlage × Stil × Sprache, für die es ein Vorlagenfoto geben soll. @return list<array{0:string,1:string,2:string}> */
    public static function vorlagenKombis(): array
    {
        require_once __DIR__ . '/Designlinie.php';
        require_once __DIR__ . '/Werbemittel.php';
        $aus = [];
        foreach (array_keys(self::ARTEN) as $vorlage) {
            foreach (array_keys(Designlinie::STILE[$vorlage] ?? []) as $stil) {
                if (!Werbemittel::stilDa($vorlage, (string) $stil)) { continue; }
                foreach (Werbemittel::SPRACHEN as $l) { $aus[] = [$vorlage, (string) $stil, $l]; }
            }
        }
        return $aus;
    }

    /**
     * Cron: wartende Vorlagenfotos abholen, dann höchstens $neu neue bei Printful anstoßen (Grenze 2–10 je
     * Minute). Fehlgeschlagene werden nach einem Tag erneut versucht. @return int abgeholt + angestoßen
     */
    public static function vorlagenfotosPflegen(int $neu = 2): int
    {
        if (!self::bereit()) { return 0; }
        require_once __DIR__ . '/Druckerei.php';
        $n = 0;
        foreach (Db::all("SELECT id, task, am FROM wm_vorlagenfotos WHERE status = 'wartet' AND task IS NOT NULL ORDER BY id LIMIT 10") as $z) {
            try { $r = self::rufen('GET', '/mockup-generator/task?task_key=' . rawurlencode((string) $z['task']), null); } catch (Throwable $e) { continue; }
            $d = (array) (json_decode($r['body'], true)['result'] ?? []);
            $st = (string) ($d['status'] ?? '');
            if ($r['code'] === 200 && $st === 'completed') {
                $bild = self::fotoLaden((string) ($d['mockups'][0]['mockup_url'] ?? ''));
                Db::run("UPDATE wm_vorlagenfotos SET bild = ?, status = ?, am = NOW() WHERE id = ?", [$bild, $bild !== null ? 'fertig' : 'fehler', (int) $z['id']]);
                $n++;
            } elseif ($st === 'failed' || strtotime((string) $z['am']) < time() - 86400) {
                Db::run("UPDATE wm_vorlagenfotos SET status = 'fehler', am = NOW() WHERE id = ?", [(int) $z['id']]);
            }
        }
        Db::run("DELETE FROM wm_vorlagenfotos WHERE status = 'fehler' AND am < NOW() - INTERVAL 1 DAY");
        $da = [];
        foreach (Db::all('SELECT vorlage, stil, sprache FROM wm_vorlagenfotos') as $z) { $da[$z['vorlage'] . '|' . $z['stil'] . '|' . $z['sprache']] = true; }
        foreach (self::vorlagenKombis() as [$vorlage, $stil, $l]) {
            if ($neu <= 0) { break; }
            if (isset($da[$vorlage . '|' . $stil . '|' . $l])) { continue; }
            $art = self::ARTEN[$vorlage];
            $files = [];
            $gp = self::generatorPlaetze($vorlage);
            foreach ($art['dateien'] as $platz => $fassung) {
                $files[] = ['placement' => $gp[$platz], 'image_url' => Druckerei::dateiLink(0, 'probe_vf_' . $vorlage . '_' . $stil . '_' . $l . '_' . ($fassung === 'pf_hinten' ? 'hinten' : 'vorn'), 3)];
            }
            try { $r = self::rufen('POST', '/mockup-generator/create-task/' . $art['produkt'], ['variant_ids' => [$art['mockup']], 'format' => 'jpg', 'width' => 1200, 'files' => $files]); }
            catch (Throwable $e) { self::$letzterGrund = $e->getMessage(); break; }
            if ($r['code'] === 429) { self::$letzterGrund = self::grund($r); break; }      // Grenze erreicht: nächster Lauf
            $k = (string) (json_decode($r['body'], true)['result']['task_key'] ?? '');
            $ok = $r['code'] === 200 && preg_match('~^[A-Za-z0-9_.:-]{4,80}$~', $k) === 1;
            Db::run('INSERT INTO wm_vorlagenfotos (vorlage, stil, sprache, task, status) VALUES (?, ?, ?, ?, ?)', [$vorlage, $stil, $l, $ok ? $k : null, $ok ? 'wartet' : 'fehler']);
            $neu--; $n++;
        }
        return $n;
    }

    /** Foto von Printfuls Server holen — nur https auf *.printful.com, nur JPEG/PNG, höchstens 8 MB. */
    private static function fotoLaden(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!str_starts_with($url, 'https://') || ($host !== 'printful.com' && !str_ends_with($host, '.printful.com'))) { return null; }
        if (self::$netz !== null) { $r = (self::$netz)('GET', $url, [], null); }
        else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 8 * 1024 * 1024]);
            $roh = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $r = ['code' => $code, 'body' => is_string($roh) ? $roh : ''];
        }
        $b = (string) $r['body'];
        $g = $r['code'] === 200 && strlen($b) <= 8 * 1024 * 1024 ? @getimagesizefromstring($b) : false;
        return $g && in_array($g[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) ? $b : null;
    }

    /** Lieferadresse nach Printful-Feldern (Längen sind in der Doku nicht begrenzt; wir kürzen trotzdem vernünftig). */
    private static function empfaenger(array $ad): array
    {
        $k = static fn(string $t, int $n): string => mb_substr(trim($t), 0, $n);
        return array_filter([
            'name' => $k((string) ($ad['name'] ?? ''), 100),
            'company' => $k((string) ($ad['firma'] ?? ''), 100),
            'address1' => $k((string) ($ad['strasse'] ?? ''), 100),
            'city' => $k((string) ($ad['ort'] ?? ''), 60),
            'zip' => $k((string) ($ad['plz'] ?? ''), 15),
            'country_code' => strtoupper((string) ($ad['land'] ?? '')),
            'phone' => $k((string) ($ad['telefon'] ?? ''), 30),
        ], static fn($v) => $v !== '');
    }

    private static function musterEmpfaenger(string $land): array
    {
        return $land === 'DE'
            ? ['name' => 'Vecom Design', 'address1' => 'Unter den Linden 1', 'city' => 'Berlin', 'zip' => '10117', 'country_code' => 'DE']
            : ['name' => 'Vecom Design', 'address1' => 'Via Atenea 1', 'city' => 'Agrigento', 'zip' => '92100', 'country_code' => 'IT'];
    }

    /** „HTTP 401: Unauthorized“ aus einer Antwort — Printfuls eigener Text, nie der Schlüssel. */
    private static function grund(array $r): string
    {
        $d = json_decode((string) $r['body'], true);
        $text = is_array($d) ? (string) ($d['error']['message'] ?? $d['result'] ?? '') : mb_substr((string) $r['body'], 0, 200);
        return 'HTTP ' . $r['code'] . ($text !== '' ? ': ' . mb_substr(trim($text), 0, 300) : '');
    }

    /** @return array{code:int, body:string} */
    private static function rufen(string $methode, string $weg, ?array $koerper): array
    {
        $url = self::BASIS . $weg;
        $c = self::cfg();
        $kopf = ['Authorization: Bearer ' . trim((string) ($c['api'] ?? '')), 'Content-Type: application/json', 'Accept: application/json'];
        if (trim((string) ($c['store'] ?? '')) !== '') { $kopf[] = 'X-PF-Store-Id: ' . preg_replace('~\D~', '', (string) $c['store']); }
        $rumpf = $koerper === null ? null : (string) json_encode($koerper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (self::$netz !== null) { return (self::$netz)($methode, $url, $kopf, $rumpf); }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_HTTPHEADER => $kopf, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        if ($rumpf !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $rumpf); }
        $roh = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $f = curl_error($ch);
        curl_close($ch);
        if ($roh === false) { throw new RuntimeException($f !== '' ? $f : 'Verbindung fehlgeschlagen'); }
        return ['code' => $code, 'body' => (string) $roh];
    }
}
