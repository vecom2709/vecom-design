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
   sieht diese Fassung vor der Freigabe. Die Bildgröße VORLAGE ist 3,75 ×
   2,25 Zoll bei 300 dpi (Endformat plus 1/8 Zoll Beschnitt). Das ist eine
   Annahme — deshalb prüft auftragSenden() vorher das Seitenverhältnis gegen
   die Druckfläche, die Printful selbst meldet, und sendet bei Abweichung
   NICHTS.

   SICHERHEIT WIE BEI DEN ANDEREN: Modus „entwurf“, bis in config.local.php
   'modus' => 'auftrag' steht. Senden genau einmal, Fehler bleiben stehen.
   ========================================================================== */
final class Printful implements DruckereiAnbieter, DruckereiPreise
{
    public const NAME = 'Printful';
    public const BASIS = 'https://api.printful.com';
    public const PRODUKT = 724;                  // „Set of Business Cards“
    public const VORLAGE = [1125, 675];          // px, siehe Kopf
    public const LAENDER = ['IT', 'DE'];

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
        $items = [];
        foreach (Db::all('SELECT * FROM wm_positionen WHERE bestellung_id = ? ORDER BY id', [$bestellungId]) as $i => $x) {
            $a = Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'printful'", [(int) $x['variante_id']]);
            if (!$a || !ctype_digit((string) $a['artikel'])) { return ['ok' => false, 'grund' => 'Für „' . $x['variante'] . '“ ist keine Printful-Variante eingetragen.']; }
            $e = Db::one('SELECT id FROM wm_entwuerfe WHERE id = ? AND datei_pf_vorn IS NOT NULL AND datei_pf_hinten IS NOT NULL', [(int) $x['entwurf_id']]);
            if (!$e) { return ['ok' => false, 'grund' => 'Zu „' . $x['variante'] . '“ fehlt die eingepasste Fassung (90 × 50 mm), die der Partner gesehen hat. Ältere Freigaben haben sie nicht — der Partner muss neu freigeben.']; }
            $items[] = [
                'external_id' => $b['nummer'] . '-' . ($i + 1),
                'variant_id' => (int) $a['artikel'],
                'quantity' => (int) $a['menge'] * (int) $x['menge'],
                'files' => [
                    ['type' => 'default', 'url' => Druckerei::dateiLink((int) $x['entwurf_id'], 'pf_vorn')],
                    ['type' => 'back', 'url' => Druckerei::dateiLink((int) $x['entwurf_id'], 'pf_hinten')],
                ],
            ];
        }
        if (!$items) { return ['ok' => false, 'grund' => 'Die Bestellung hat keine Position.']; }
        // Vor dem Senden: passt unser Bild zur Druckfläche, die Printful selbst meldet? Sonst nichts senden.
        $passt = self::flaechePruefen(array_column($items, 'variant_id'));
        if ($passt !== '') { return ['ok' => false, 'grund' => $passt]; }
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
        $a = Db::one("SELECT artikel FROM wm_anbieter_produkte WHERE anbieter = 'printful' ORDER BY menge, id LIMIT 1");
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
        foreach (Db::all("SELECT variante_id, artikel, menge FROM wm_anbieter_produkte WHERE anbieter = 'printful'") as $z) {
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
                        'papier' => 'Munken Lynx 300 g, 90 × 50 mm (eingepasst), Variante ' . $z['artikel'] . ' × ' . $z['menge'],
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
    public static function flaechePruefen(array $variantenIds): string
    {
        try {
            $r = self::rufen('GET', '/mockup-generator/printfiles/' . self::PRODUKT, null);
        } catch (Throwable $e) { return 'Druckfläche bei Printful nicht abrufbar: ' . $e->getMessage(); }
        $d = (array) (json_decode($r['body'], true)['result'] ?? []);
        if ($r['code'] !== 200 || !$d) { return 'Druckfläche bei Printful nicht abrufbar (' . self::grund($r) . ').'; }
        $flaechen = [];
        foreach ((array) ($d['printfiles'] ?? []) as $f) { $flaechen[(int) ($f['printfile_id'] ?? 0)] = $f; }
        $soll = self::VORLAGE[0] / self::VORLAGE[1];
        foreach ((array) ($d['variant_printfiles'] ?? []) as $vp) {
            if (!in_array((int) ($vp['variant_id'] ?? 0), $variantenIds, true)) { continue; }
            foreach (['default', 'back'] as $platz) {
                $f = $flaechen[(int) ($vp['placements'][$platz] ?? 0)] ?? null;
                if (!$f || (int) ($f['height'] ?? 0) <= 0) { return 'Printful meldet für Variante ' . $vp['variant_id'] . ' keine Druckfläche „' . $platz . '“.'; }
                $ist = (int) $f['width'] / (int) $f['height'];
                $istHoch = (int) $f['height'] / (int) $f['width'];
                if (abs($ist - $soll) / $soll > 0.01 && !(!empty($f['can_rotate']) && abs($istHoch - $soll) / $soll <= 0.01)) {
                    return 'Printful-Druckfläche ' . $f['width'] . ' × ' . $f['height'] . ' px passt nicht zu unserem Bild ' . self::VORLAGE[0] . ' × ' . self::VORLAGE[1] . ' px — nichts gesendet.';
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
        $e = Db::one('SELECT id FROM wm_entwuerfe WHERE id = ? AND datei_pf_vorn IS NOT NULL AND datei_pf_hinten IS NOT NULL AND mockup_status IS NULL', [$entwurfId]);
        if (!$e) { return 'fehlt'; }
        $koerper = ['variant_ids' => [self::MOCKUP_VARIANTE], 'format' => 'jpg', 'width' => 1000, 'files' => [
            ['placement' => 'default', 'image_url' => Druckerei::dateiLink($entwurfId, 'pf_vorn', 3)],
            ['placement' => 'back', 'image_url' => Druckerei::dateiLink($entwurfId, 'pf_hinten', 3)],
        ]];
        try { $r = self::rufen('POST', '/mockup-generator/create-task/' . self::PRODUKT, $koerper); }
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
