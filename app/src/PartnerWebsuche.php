<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Firmen-Finder sucht im Web nach (27.09.2026, Uwe: „Betriebe in der Nähe:
 * keine Ergebnisse — sollte entsprechend im Web suchen“).
 *
 * WARUM ES LEER BLIEB: Der Finder las nur unsere Akquise-Liste -- und die
 * enthält nur die Gemeinden, die der Worker auf Uwes Rechner schon
 * abgesucht hat. Ein Partner in Favara sah deshalb nichts, obwohl dort
 * Betriebe sind.
 *
 * WAS JETZT PASSIERT: Kennt die Liste den Ort (für diese Branche) noch nicht
 * oder ist die letzte Suche älter als zwei Wochen, fragt der Server
 * OpenStreetMap -- dieselbe Quelle und dieselben Branchen-Selektoren wie der
 * Worker (app/src/akquise_branchen.json, eine Wahrheit, zwei Leser):
 *
 *   1. Nominatim: Wo ist der Ort? (Gemeindegrenze, sonst Punkt mit Umkreis)
 *   2. Overpass: Betriebe dieser Branche(n) in der Gemeinde
 *   3. Akquise::firmaMelden: in die Liste -- mit Dublettenprüfung, Sperrliste
 *      und Quellenangabe (osm:node/…, ODbL). Danach findet die normale Suche
 *      sie, und Reservierung wie Vecom-Akquise sehen dieselbe Firma.
 *
 * Nur Name, Adresse, Branche, Website, Koordinaten gehen an den Partner --
 * Telefon und E-Mail bleiben in der Verwaltung (siehe PartnerRecherche).
 *
 * FAIR BLEIBEN: Beide Dienste sind frei und verlangen Zurückhaltung. Deshalb
 * je Ort und Branche höchstens eine Abfrage in 14 Tagen (nach einem Fehler:
 * eine Stunde Pause), eindeutiger Absender, kurze Zeitgrenzen, mehrere
 * Overpass-Server der Reihe nach wie im Worker.
 */
final class PartnerWebsuche
{
    public const FRISCH_TAGE = 14;
    public const FEHLER_PAUSE_MIN = 60;
    public const HOECHSTENS = 400;
    public const UMKREIS_M = 3000;
    public const ABSENDER = 'VecomAudit/1.0 (+https://vecom-design.it; Firmen-Finder, kontakt@vecom-design.it)';
    public const NOMINATIM = 'https://nominatim.openstreetmap.org';
    /* Gemessen am 27.09.2026 (Favara, alle Branchen): overpass-api.de 504,
       private.coffee Zeitüberschreitung, kumi.systems Verbindung abgebrochen,
       maps.mail.ru 200 nach 16 s mit 56 Treffern. Öffentliche Server
       schwanken stark -- deshalb mehrere, der Reihe nach, mit Zeitbudget. */
    public const OVERPASS = ['https://overpass-api.de/api/interpreter', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
                             'https://overpass.kumi.systems/api/interpreter', 'https://overpass.private.coffee/api/interpreter'];
    /** Sekunden für alle Overpass-Versuche zusammen -- der Partner wartet auf die Seite. */
    public const BUDGET_S = 30;
    public const LIZENZ = 'ODbL (© OpenStreetMap-Mitwirkende)';

    /** Für die Kette: fn(string $methode, string $url, ?string $body): array{status:int, json:mixed} */
    public static $netz = null;

    private const ORTSTYPEN = ['city', 'town', 'village', 'municipality', 'hamlet', 'suburb', 'quarter', 'neighbourhood'];
    private const KEIN_BETRIEB = '/\b(chiuso|chiusa|closed|geschlossen|comunale|comune di|municipio|patronato|caf|acli|parrocchia|chiesa|scuola|istituto comprensivo|asp|asl|gemeinde|stadtverwaltung|rathaus|kirche|schule|kita)\b/iu';

    /**
     * Sucht nach, wenn nötig. Wirft nie -- im schlimmsten Fall bleibt es bei
     * dem, was die Liste schon kennt.
     *
     * @return array{gefragt:bool, gefunden:int, neu:int, fehler:?string, gebiet:?string}
     */
    public static function ergaenzen(string $ort, string $branche): array
    {
        $ort = trim(mb_substr($ort, 0, 80));
        $schluessel = sha1(mb_strtolower(Akquise::ohneAkzente($ort)) . '|' . $branche);
        $alt = self::still(static fn() => Db::one('SELECT * FROM partner_websuche WHERE schluessel = ?', [$schluessel]), null);
        if ($alt) {
            $grenze = $alt['fehler'] !== null ? '-' . self::FEHLER_PAUSE_MIN . ' minutes' : '-' . self::FRISCH_TAGE . ' days';
            if (strtotime((string) $alt['am']) > strtotime($grenze)) {
                return ['gefragt' => false, 'gefunden' => (int) $alt['gefunden'], 'neu' => (int) $alt['neu'], 'fehler' => $alt['fehler'], 'gebiet' => $alt['gebiet']];
            }
        }
        @set_time_limit(75);   // Nominatim + Overpass-Budget; der Webspace bricht sonst nach 30 s mitten in der Übernahme ab
        // Erst vermerken, dann fragen: Zwei gleichzeitige Suchen desselben Orts fragen nicht doppelt.
        self::merken($schluessel, $ort, $branche, null, 0, 0, 'läuft');
        try {
            $gebiet = self::gebiet($ort);
            if ($gebiet === null) { self::merken($schluessel, $ort, $branche, null, 0, 0, null); return ['gefragt' => true, 'gefunden' => 0, 'neu' => 0, 'fehler' => null, 'gebiet' => null]; }
            $elemente = self::betriebe($gebiet, $branche);
            $gefunden = 0; $neu = 0;
            foreach (array_slice($elemente, 0, self::HOECHSTENS) as $e) {
                $f = self::alsFirma($e, $gebiet, $branche);
                if ($f === null) { continue; }
                $gefunden++;
                try { if (Akquise::firmaMelden($f)['neu']) { $neu++; } } catch (Throwable $x) { /* eine kaputte Zeile hält die anderen nicht auf */ }
            }
            self::merken($schluessel, $ort, $branche, $gebiet['name'], $gefunden, $neu, null);
            return ['gefragt' => true, 'gefunden' => $gefunden, 'neu' => $neu, 'fehler' => null, 'gebiet' => $gebiet['name']];
        } catch (Throwable $e) {
            $grund = mb_substr($e->getMessage(), 0, 200);
            self::merken($schluessel, $ort, $branche, null, 0, 0, $grund);
            return ['gefragt' => true, 'gefunden' => 0, 'neu' => 0, 'fehler' => $grund, 'gebiet' => null];
        }
    }

    /**
     * Wo ist der Ort? Bevorzugt die Gemeindegrenze (Suche innerhalb), sonst
     * ein Punkt mit Umkreis (PLZ, Ortsteil, Weiler).
     *
     * @return array{name:string, land:?string, kreis:?string, region:?string, plz:?string, rel:?int, lat:?float, lon:?float, nachName?:bool}|null
     */
    public static function gebiet(string $ort): ?array
    {
        $plz = preg_match('/^\d{5}$/', $ort) === 1;
        $q = ['format' => 'jsonv2', 'addressdetails' => '1', 'limit' => '10', 'countrycodes' => 'it,de'];
        if ($plz) { $q['postalcode'] = $ort; } else { $q['q'] = $ort; }
        /* Antwortet Nominatim nicht (am 27.09.2026 im Test: 429 „Too many
           requests“ über eine geteilte Adresse), sucht Overpass die Gemeinde
           selbst über ihren Namen -- langsamer, aber ohne zweiten Dienst. */
        try { $r = self::holen('GET', self::NOMINATIM . '/search?' . http_build_query($q), null); }
        catch (Throwable $e) { $r = ['status' => 0, 'json' => null]; }
        if ($r['status'] !== 200) {
            return $plz ? null : ['name' => $ort, 'land' => null, 'kreis' => null, 'region' => null, 'plz' => null, 'rel' => null, 'lat' => null, 'lon' => null, 'nachName' => true];
        }
        $liste = is_array($r['json']) ? $r['json'] : [];
        if (!$liste) { return null; }
        $wahl = null;
        foreach ($liste as $z) {
            if (!$plz && ($z['osm_type'] ?? '') === 'relation' && ($z['category'] ?? '') === 'boundary' && in_array($z['addresstype'] ?? '', self::ORTSTYPEN, true)) { $wahl = $z; break; }
        }
        $wahl ??= $liste[0];
        $a = (array) ($wahl['address'] ?? []);
        $land = strtoupper((string) ($a['country_code'] ?? ''));
        if (!in_array($land, ['IT', 'DE'], true)) { return null; }
        $name = (string) ($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['hamlet'] ?? $wahl['name'] ?? $ort);
        /* Große Städte (addresstype city: Palermo, München) nicht als ganze
           Fläche -- die Abfrage liefe auf den öffentlichen Servern in die
           Zeitgrenze. Dort der Umkreis um die Mitte. */
        $istGrenze = !$plz && ($wahl['osm_type'] ?? '') === 'relation' && ($wahl['category'] ?? '') === 'boundary' && ($wahl['addresstype'] ?? '') !== 'city';
        return [
            'name' => $name, 'land' => $land,
            'kreis' => isset($a['county']) || isset($a['province']) || isset($a['state_district']) ? (string) ($a['county'] ?? $a['province'] ?? $a['state_district']) : null,
            'region' => isset($a['state']) ? (string) $a['state'] : null,
            'plz' => $plz ? $ort : (isset($a['postcode']) && preg_match('/^\d{5}$/', (string) $a['postcode']) ? (string) $a['postcode'] : null),
            'rel' => $istGrenze ? (int) $wahl['osm_id'] : null,
            'lat' => (float) ($wahl['lat'] ?? 0), 'lon' => (float) ($wahl['lon'] ?? 0),
        ];
    }

    /**
     * Overpass-Selektoren einer Branche (oder aller) als Filter, wie im Worker.
     *
     * Einfache Selektoren (ein „k=v“) werden je Schlüssel zu EINEM Regex
     * zusammengefasst: 95 Einzelabfragen für „alle Branchen“ liefen am
     * 27.09.2026 auf overpass-api.de in den 504, zusammengefasst sind es
     * gut ein Dutzend.
     *
     * @return list<string>
     */
    public static function filter(string $branche): array
    {
        $alle = Akquise::branchen();
        $liste = $branche !== '' && isset($alle[$branche]) ? [$branche => $alle[$branche]] : $alle;
        $einfach = []; $aus = [];
        foreach ($liste as $b) {
            foreach ((array) ($b['osm'] ?? []) as $sel) {
                $sel = (string) $sel;
                if (preg_match('/^([a-z_:]+)=([A-Za-z0-9_;:-]+)$/', $sel, $m)) { $einfach[$m[1]][$m[2]] = true; continue; }
                $teile = '';
                foreach (explode('&', $sel) as $t) {
                    if (preg_match('/^([a-z_:]+)(=|~)(.+)$/i', $t, $m)) {
                        $teile .= '["' . $m[1] . '"' . $m[2] . '"' . addcslashes($m[3], '"\\') . '"]';
                    } elseif (preg_match('/^[a-z_:]+$/i', $t)) {
                        $teile .= '["' . $t . '"]';
                    }
                }
                if ($teile !== '') { $aus[] = $teile . '["name"]'; }
            }
        }
        foreach ($einfach as $k => $werte) {
            $w = array_keys($werte);
            array_unshift($aus, count($w) === 1 ? '["' . $k . '"="' . $w[0] . '"]["name"]' : '["' . $k . '"~"^(' . implode('|', $w) . ')$"]["name"]');
        }
        return array_values(array_unique($aus));
    }

    /** @return list<array<string,mixed>> OSM-Elemente */
    public static function betriebe(array $gebiet, string $branche): array
    {
        $filter = self::filter($branche);
        if (!$filter) { return []; }
        if (!empty($gebiet['nachName'])) {
            // Gemeinde (admin_level 8) über den Namen, Groß-/Kleinschreibung egal.
            // Nur Buchstaben, Ziffern, Leerzeichen, Apostroph, Bindestrich -- alles andere wäre ein Regex-Zeichen.
            $name = '^' . preg_replace("/[^\\p{L}\\p{N} '\\-]/u", '', $gebiet['name']) . '$';
            $teile = implode('', array_map(static fn($f) => 'nwr' . $f . '(area.g);', $filter));
            return self::overpass('[out:json][timeout:25];area["boundary"="administrative"]["admin_level"="8"]["name"~"' . $name . '",i]->.g;(' . $teile . ');out center tags ' . self::HOECHSTENS . ';');
        }
        $umkreis = '(around:' . self::UMKREIS_M . ',' . round($gebiet['lat'], 6) . ',' . round($gebiet['lon'], 6) . ')';
        if ($gebiet['rel'] !== null) {
            $teile = implode('', array_map(static fn($f) => 'nwr' . $f . '(area.g);', $filter));
            return self::overpass('[out:json][timeout:25];rel(' . (int) $gebiet['rel'] . ');map_to_area->.g;(' . $teile . ');out center tags ' . self::HOECHSTENS . ';');
        }
        $teile = implode('', array_map(static fn($f) => 'nwr' . $f . $umkreis . ';', $filter));
        return self::overpass('[out:json][timeout:25];(' . $teile . ');out center tags ' . self::HOECHSTENS . ';');
    }

    /** @return list<array<string,mixed>> */
    private static function overpass(string $abfrage): array
    {
        $letzter = 'kein Server';
        $ende = microtime(true) + self::BUDGET_S;
        foreach (self::OVERPASS as $server) {
            $rest = (int) floor($ende - microtime(true));
            if ($rest < 5) { $letzter .= ', Zeit um'; break; }
            try {
                $r = self::holen('POST', $server, 'data=' . rawurlencode($abfrage), min(22, $rest));
            } catch (Throwable $e) { $letzter = $e->getMessage(); continue; }
            if ($r['status'] === 200 && is_array($r['json'])) {
                if (isset($r['json']['remark']) && preg_match('/runtime error|timed out/i', (string) $r['json']['remark'])) { $letzter = (string) $r['json']['remark']; continue; }
                return array_values((array) ($r['json']['elements'] ?? []));
            }
            $letzter = 'Overpass ' . $r['status'];
        }
        throw new RuntimeException('OpenStreetMap antwortet gerade nicht (' . $letzter . ').');
    }

    /** Ein OSM-Element als Firma für Akquise::firmaMelden -- oder null (kein Betrieb, Kette, geschlossen). */
    public static function alsFirma(array $e, array $gebiet, string $branche): ?array
    {
        $t = (array) ($e['tags'] ?? []);
        $name = trim((string) ($t['name'] ?? ''));
        if (mb_strlen($name) < 3 || preg_match(self::KEIN_BETRIEB, $name) || preg_match_all('/\d/', $name) >= 6) { return null; }
        if (!empty($t['disused:shop']) || !empty($t['disused:amenity']) || ($t['disused'] ?? '') === 'yes' || ($t['opening_hours'] ?? '') === 'closed') { return null; }
        if (!empty($t['brand:wikidata']) || !empty($t['operator:wikidata'])) { return null; }   // Kettenfiliale: die Website macht die Zentrale
        if (in_array($t['operator:type'] ?? '', ['public', 'government', 'religious', 'community'], true)) { return null; }
        $b = self::brancheFuer($t, $branche);
        if ($b === null) { return null; }
        $strasse = trim(implode(' ', array_filter([$t['addr:street'] ?? $t['addr:place'] ?? null, $t['addr:housenumber'] ?? null])));
        $url = (string) ($t['website'] ?? $t['contact:website'] ?? $t['url'] ?? '');
        if ($url !== '' && !preg_match('~^https?://~i', $url)) { $url = 'http://' . $url; }
        $art = '';
        foreach (['amenity', 'shop', 'tourism', 'craft', 'office', 'leisure', 'healthcare'] as $k) { if (isset($t[$k])) { $art = $k . '=' . $t[$k]; break; } }
        $lat = $e['lat'] ?? ($e['center']['lat'] ?? null); $lon = $e['lon'] ?? ($e['center']['lon'] ?? null);
        /* Ohne Nominatim kennen wir das Land nicht: Italien und Deutschland
           trennt die Breite (Italien endet bei 47,1° am Brenner, Deutschland
           beginnt bei 47,3°). Außerhalb beider: kein Treffer. */
        $land = $gebiet['land'] ?? ($lat === null ? null : ((float) $lat < 47.2 && (float) $lat > 35.4 && (float) $lon > 6.5 && (float) $lon < 18.6 ? 'IT'
              : ((float) $lat >= 47.2 && (float) $lat < 55.1 && (float) $lon > 5.8 && (float) $lon < 15.1 ? 'DE' : null)));
        if ($land === null) { return null; }
        return [
            'name' => $name, 'land' => $land, 'region' => $gebiet['region'], 'kreis' => $gebiet['kreis'],
            'stadt' => (string) ($t['addr:city'] ?? $gebiet['name']), 'plz' => $t['addr:postcode'] ?? $gebiet['plz'],
            'adresse' => $strasse !== '' ? $strasse : null,
            'lat' => $lat, 'lon' => $lon,
            'url' => $url !== '' ? $url : null,
            'telefon' => $t['phone'] ?? $t['contact:phone'] ?? $t['contact:mobile'] ?? null,
            'email' => $t['email'] ?? $t['contact:email'] ?? null,
            'branche' => $b, 'unternehmensart' => $art,
            'quelle' => 'osm:' . ($e['type'] ?? 'node') . '/' . (int) ($e['id'] ?? 0), 'quelle_lizenz' => self::LIZENZ,
        ];
    }

    /** Branche eines OSM-Elements -- Branchen mit Namensmuster zuerst (Agriturismo vor Gästehaus), wie im Worker. */
    public static function brancheFuer(array $t, string $nur = ''): ?string
    {
        $alle = Akquise::branchen();
        if ($nur !== '' && isset($alle[$nur])) { $alle = [$nur => $alle[$nur]]; }
        $passt = static function (string $sel) use ($t): bool {
            foreach (explode('&', $sel) as $teil) {
                if (preg_match('/^([a-z_:]+)(=|~)(.+)$/i', $teil, $m)) {
                    $v = $t[$m[1]] ?? null;
                    if ($v === null) { return false; }
                    if ($m[2] === '=' && $v !== $m[3]) { return false; }
                    if ($m[2] === '~' && !@preg_match('/' . str_replace('/', '\/', $m[3]) . '/u', (string) $v)) { return false; }
                } elseif (!isset($t[$teil])) { return false; }
            }
            return true;
        };
        foreach ([true, false] as $mitMuster) {
            foreach ($alle as $k => $b) {
                if (!empty($b['name_muster']) !== $mitMuster) { continue; }
                foreach ((array) ($b['osm'] ?? []) as $sel) { if ($passt((string) $sel)) { return (string) $k; } }
            }
        }
        return null;
    }

    /** @return array{status:int, json:mixed} */
    private static function holen(string $methode, string $url, ?string $body, int $sekunden = 10): array
    {
        if (self::$netz) { return (self::$netz)($methode, $url, $body); }
        $ch = curl_init($url);
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $sekunden, CURLOPT_CONNECTTIMEOUT => min(6, $sekunden), CURLOPT_USERAGENT => self::ABSENDER,
              CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: it,de,en']];
        if ($methode === 'POST') { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = (string) $body; }
        curl_setopt_array($ch, $o);
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($roh === false) { throw new RuntimeException('Netz: ' . $fehler); }
        return ['status' => $status, 'json' => json_decode((string) $roh, true)];
    }

    private static function merken(string $schluessel, string $ort, string $branche, ?string $gebiet, int $gefunden, int $neu, ?string $fehler): void
    {
        self::still(static fn() => Db::run('INSERT INTO partner_websuche (schluessel, ort, branche, gebiet, gefunden, neu, fehler, am) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE ort = VALUES(ort), gebiet = VALUES(gebiet), gefunden = VALUES(gefunden), neu = VALUES(neu), fehler = VALUES(fehler), am = VALUES(am)',
            // Zeit aus PHP, nicht NOW(): Datenbank (UTC) und PHP (Europe/Rome) liegen zwei Stunden auseinander --
            // die Fehlerpause von einer Stunde war damit schon beim Schreiben abgelaufen (Kette, 27.09.2026).
            [$schluessel, $ort, $branche, $gebiet !== null ? mb_substr($gebiet, 0, 120) : null, $gefunden, $neu, $fehler, date('Y-m-d H:i:s')]));
    }

    /** @template T @param callable():T $f @param T $sonst @return T */
    private static function still(callable $f, mixed $sonst = null): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }
}
