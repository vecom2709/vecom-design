<?php
declare(strict_types=1);

/* ==========================================================================
   HelloPrint.php — zweite angebundene Druckerei des Marketing Centers
   (04.10.2026, Uwe: „finde alle anderen Anbieter … per API“).

   QUELLE: developers.helloprint.com, im Browser gelesen am 04.10.2026
     Zugang      nur für Helloprint Connect, Schlüssel über api@helloprint.com
     Kopfzeile   x-api-key
     Bestellung  POST https://api.helloprint.com/rest/v1/orders
                 mode "test"|"prod" (Voreinstellung test), orderReferenceId,
                 shipping {firstName, lastName, addressLine1, postcode, city,
                 country, companyName?, addressLine2?, phone?},
                 orderItems[{itemReferenceId, variantKey, quantity,
                 serviceLevel, fileUrl}], callbackUrls[]
     Lesen       GET https://api.helloprint.com/rest/v1/orders/orderReferenceId={id}
     Adresse     Vorname/Nachname 32, Straße 128, PLZ 12 (nur A–Z 0–9 Leer -),
                 Ort 64, Telefon 32, Firma 64
     WICHTIG     „Delivery can only take place in the same country as the
                 customer.“ — ein Connect-Konto liefert nur in SEIN Land.
                 Deshalb trägt die Einstellung das Land des Kontos
                 ('helloprint' => ['api' => …, 'land' => 'IT']); für andere
                 Länder ist HelloPrint nicht „angebunden“.
   Nicht dokumentiert: die Antwortfelder von Bestellung, Lesen und Quote.
   Deshalb liest dieser Code tolerant (Status und trackingUrls irgendwo in
   der Antwort, wie im dokumentierten Callback-Beispiel) und holt KEINE
   Preise per API — die Angebote bleiben von Hand gepflegt.
   Callbacks sind laut Doku nicht unterschrieben → nicht benutzt; der Cron
   liest mit dem eigenen Schlüssel nach.

   SICHERHEIT WIE BEI GELATO
   Modus „test“, solange in config.local.php nicht ausdrücklich 'modus' =>
   'prod' steht. Senden genau einmal (Sperre), Fehler bleiben stehen, kein
   automatischer zweiter Versuch.
   ========================================================================== */
final class HelloPrint
{
    public const NAME = 'HelloPrint';
    public const BASIS = 'https://api.helloprint.com/rest/v1';

    /** Prüfnaht für die Kette: fn(string $methode, string $url, array $kopf, ?string $rumpf): array{code:int, body:string} */
    public static $netz = null;

    private static function cfg(): array
    {
        $c = Config::get('helloprint', []);
        return is_array($c) ? $c : [];
    }

    /** Angebunden — und, wenn $land angegeben, liefert dieses Konto dorthin? */
    public static function bereit(?string $land = null): bool
    {
        $c = self::cfg();
        if (trim((string) ($c['api'] ?? '')) === '') { return false; }
        return $land === null || strtoupper($land) === self::land();
    }

    /** Land des Connect-Kontos (nur dorthin wird geliefert). */
    public static function land(): string
    {
        $l = strtoupper(trim((string) (self::cfg()['land'] ?? 'IT')));
        return preg_match('~^[A-Z]{2}$~', $l) ? $l : 'IT';
    }

    public static function modus(): string
    {
        return (string) (self::cfg()['modus'] ?? 'test') === 'prod' ? 'prod' : 'test';
    }

    /** Sendet eine bezahlte Bestellung an HelloPrint (genau einmal). @return array{ok:bool, grund:string, id?:string} */
    public static function auftragSenden(int $bestellungId): array
    {
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'HelloPrint-Schlüssel fehlt in config.local.php.']; }
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        if (!$b || $b['status'] !== 'bezahlt') { return ['ok' => false, 'grund' => 'Nur bezahlte Bestellungen gehen an den Drucker.']; }
        $ad = (array) json_decode((string) $b['adresse'], true);
        if (strtoupper((string) ($ad['land'] ?? '')) !== self::land()) {
            return ['ok' => false, 'grund' => 'HelloPrint liefert mit diesem Konto nur nach ' . self::land() . '.'];
        }
        $items = [];
        foreach (Db::all('SELECT * FROM wm_positionen WHERE bestellung_id = ? ORDER BY id', [$bestellungId]) as $i => $x) {
            $a = Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'helloprint'", [(int) $x['variante_id']]);
            if (!$a) { return ['ok' => false, 'grund' => 'Für „' . $x['variante'] . '“ ist kein HelloPrint-Artikel (variantKey) eingetragen.']; }
            $items[] = [
                'itemReferenceId' => $b['nummer'] . '-' . ($i + 1),
                'variantKey' => (string) $a['artikel'],
                'quantity' => (int) $a['menge'] * (int) $x['menge'],
                'serviceLevel' => 'saver',
                'fileUrl' => Druckerei::dateiLink((int) $x['entwurf_id'], 'frei'),
            ];
        }
        if (!$items) { return ['ok' => false, 'grund' => 'Die Bestellung hat keine Position.']; }
        require_once __DIR__ . '/Gelato.php';
        [$vor, $nach] = Gelato::namen((string) ($ad['name'] ?? ''));
        $k = static fn(string $t, int $n): string => mb_substr(trim($t), 0, $n);
        $koerper = [
            'mode' => self::modus(),
            'orderReferenceId' => (string) $b['nummer'],
            'shipping' => array_filter([
                'companyName' => $k((string) ($ad['firma'] ?? ''), 64),
                'firstName' => $k($vor, 32), 'lastName' => $k($nach, 32),
                'addressLine1' => $k((string) ($ad['strasse'] ?? ''), 128),
                'postcode' => $k((string) preg_replace('~[^A-Za-z0-9 \-]~', '', (string) ($ad['plz'] ?? '')), 12),
                'city' => $k((string) ($ad['ort'] ?? ''), 64), 'country' => self::land(),
                'phone' => $k((string) ($ad['telefon'] ?? ''), 32),
            ], static fn($v) => $v !== ''),
            'orderItems' => $items,
        ];
        if (!Druckerei::sperren($bestellungId, self::NAME)) { return ['ok' => false, 'grund' => 'Diese Bestellung wurde schon gesendet (oder es läuft gerade).']; }
        try {
            $r = self::rufen('POST', '/orders', $koerper);
        } catch (Throwable $e) {
            Druckerei::fehler($bestellungId, 'Keine Antwort von HelloPrint: ' . $e->getMessage() . ' — im HelloPrint-Konto nachsehen, ob ' . $b['nummer'] . ' trotzdem angelegt wurde.');
            return ['ok' => false, 'grund' => 'Keine Antwort von HelloPrint. Nichts wird wiederholt.'];
        }
        $d = json_decode($r['body'], true);
        $fehler = !is_array($d) || $r['code'] < 200 || $r['code'] >= 300 || strtoupper((string) ($d['status'] ?? '')) === 'ERROR' || (isset($d['success']) && $d['success'] === false);
        if ($fehler) {
            $grund = is_array($d) ? (string) ($d['message'] ?? json_encode($d)) : mb_substr($r['body'], 0, 300);
            Druckerei::fehler($bestellungId, 'HelloPrint lehnte ab (HTTP ' . $r['code'] . '): ' . mb_substr($grund, 0, 400));
            return ['ok' => false, 'grund' => 'HelloPrint lehnte ab: ' . mb_substr($grund, 0, 300)];
        }
        // Antwort ist nicht dokumentiert: requestId (aus dem Callback-Beispiel) oder unsere Nummer als Bezug.
        $ref = (string) ($d['requestId'] ?? $d['data']['orderId'] ?? $b['nummer']);
        Druckerei::erledigt($bestellungId, $ref);
        return ['ok' => true, 'grund' => '', 'id' => $ref];
    }

    /** Cron: Stand nachlesen (nur lesen). Versendet → WmBestellung::versendet (Mail an den Partner). */
    public static function nachsehen(): int
    {
        if (!self::bereit()) { return 0; }
        require_once __DIR__ . '/WmBestellung.php';
        $n = 0;
        foreach (Db::all("SELECT id, nummer, status FROM wm_bestellungen WHERE anbieter = ? AND status = 'beim_drucker' AND anbieter_status = 'auftrag' LIMIT 30", [self::NAME]) as $b) {
            try {
                $r = self::rufen('GET', '/orders/orderReferenceId=' . rawurlencode((string) $b['nummer']), null);
                if ($r['code'] !== 200) { continue; }
                $d = json_decode($r['body'], true);
                if (!is_array($d)) { continue; }
                $text = strtoupper((string) json_encode($d));
                $versendet = str_contains($text, '"SHIPPED"') || str_contains($text, '"DELIVERED"') || str_contains($text, '"OUT_FOR_DELIVERY"');
                if ($versendet) {
                    $spur = self::spurLink($d);
                    WmBestellung::versendet((int) $b['id'], 'HelloPrint ' . $b['nummer'], str_starts_with($spur, 'https://') ? mb_substr($spur, 0, 390) : '');
                    $n++;
                }
            } catch (Throwable $e) { error_log('HelloPrint::nachsehen ' . $b['id'] . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    /** Erste https-Adresse unter einem Schlüssel „trackingUrls“ (wie im Callback-Beispiel der Doku), irgendwo in der Antwort. */
    public static function spurLink(array $d): string
    {
        foreach ($d as $k => $v) {
            if ($k === 'trackingUrls' && is_array($v)) {
                foreach ($v as $u) { if (is_string($u) && str_starts_with($u, 'https://')) { return $u; } }
            }
            if (is_array($v)) { $x = self::spurLink($v); if ($x !== '') { return $x; } }
        }
        return '';
    }

    /** @return array{code:int, body:string} */
    private static function rufen(string $methode, string $weg, ?array $koerper): array
    {
        $url = self::BASIS . $weg;
        $kopf = ['x-api-key: ' . trim((string) (self::cfg()['api'] ?? '')), 'Content-Type: application/json', 'Accept: application/json'];
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
