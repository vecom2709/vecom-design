<?php
declare(strict_types=1);

/* ==========================================================================
   Gelato.php — erster Druckanbieter des Marketing Centers (03.10.2026,
   Phase 4, Uwe: „mache alles automatisch soweit wie es geht“).

   QUELLE: nur die offizielle Doku, gelesen am 03.10.2026
     Bestellung anlegen  POST https://order.gelatoapis.com/v4/orders
                         Kopfzeile X-API-KEY, JSON; orderType "order"|"draft"
                         Pflicht: orderReferenceId, customerReferenceId,
                         currency, items[{itemReferenceId, productUid,
                         quantity, files[{type,url}]}], shippingAddress
                         {firstName, lastName, addressLine1, city, postCode,
                         country, email}
     Bestellung lesen    GET  https://order.gelatoapis.com/v4/orders/{id}
                         shipment.packages[].trackingCode / trackingUrl
     Druckdaten          PDF, 4 mm Beschnitt, ohne Schnittmarken, ≤ 300 dpi
   Nicht verwendet: die Webhooks. Gelato dokumentiert für sie keine
   Unterschrift — jeder könnte „versendet“ melden. Stattdessen fragt der
   Cron mit dem eigenen Schlüssel nach (lesen()).

   NUR ENTWÜRFE
   Gesendet wird ausschließlich orderType "draft". Ein Entwurf geht laut Doku
   nicht in Produktion, bis er im Dashboard (oder per API) umgewandelt wird.
   Das Umwandeln macht Uwe im Gelato-Dashboard — dort wird auch bezahlt.
   Geld fließt also nie von selbst.

   NIE DOPPELT
   Vor dem Senden wird die Bestellung in EINER Anweisung als „wird gesendet“
   markiert (nur wenn noch nichts gesendet wurde). Scheitert der Aufruf,
   bleibt „fehler“ mit dem Grund stehen, und erst Uwes Klick „zurücksetzen“
   gibt sie wieder frei. Kein automatischer zweiter Versuch: Ein Zeitüberlauf
   kann heißen, dass Gelato den Entwurf doch angelegt hat.
   ========================================================================== */
final class Gelato
{
    public const BASIS = 'https://order.gelatoapis.com';
    public const NAME = 'Gelato';

    /** Prüfnaht für die Kette: fn(string $methode, string $url, array $kopf, ?string $rumpf): array{code:int, body:string} */
    public static $netz = null;

    public static function bereit(): bool
    {
        return self::schluessel() !== '';
    }

    private static function schluessel(): string
    {
        $g = Config::get('gelato', []);
        return is_array($g) ? trim((string) ($g['api'] ?? '')) : '';
    }

    /** Anbieter-Artikel einer Variante (productUid, Menge) — oder null. */
    public static function artikel(int $varianteId): ?array
    {
        return Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'gelato'", [$varianteId]) ?: null;
    }

    public static function artikelSetzen(int $varianteId, string $artikel, int $menge): void
    {
        $artikel = trim($artikel);
        if ($artikel === '') {
            Db::run("DELETE FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = 'gelato'", [$varianteId]);
            return;
        }
        if (!preg_match('~^[a-z0-9_\-]{3,200}$~i', $artikel)) { throw new InvalidArgumentException('Gelato-Artikel (productUid) ungültig.'); }
        if ($menge < 1 || $menge > 100000) { throw new InvalidArgumentException('Menge ungültig.'); }
        Db::run("INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge) VALUES (?, 'gelato', ?, ?)
                 ON DUPLICATE KEY UPDATE artikel = VALUES(artikel), menge = VALUES(menge)", [$varianteId, $artikel, $menge]);
    }

    /**
     * Unterschriebener, befristeter Link, unter dem Gelato die Druckdatei
     * abholt. Ohne app_geheim gibt es keinen Link — ein erratbarer Schlüssel
     * wäre schlimmer als keiner.
     */
    public static function dateiLink(int $entwurfId, int $tage = 14): string
    {
        $geheim = (string) Config::get('app_geheim', '');
        if (strlen($geheim) < 16) { throw new RuntimeException('app_geheim fehlt in config.local.php — ohne ihn kein Link für die Druckdatei.'); }
        $bis = time() + $tage * 86400;
        $sig = hash_hmac('sha256', 'wm-druck|' . $entwurfId . '|' . $bis, $geheim);
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/druckdatei.php?' . http_build_query(['e' => $entwurfId, 'x' => $bis, 's' => $sig]);
    }

    /** Prüft einen Link aus dateiLink(). Gibt die Entwurfs-id oder 0. */
    public static function linkPruefen(string $e, string $x, string $s): int
    {
        $geheim = (string) Config::get('app_geheim', '');
        if (strlen($geheim) < 16 || !ctype_digit($e) || !ctype_digit($x) || (int) $x < time()) { return 0; }
        return hash_equals(hash_hmac('sha256', 'wm-druck|' . $e . '|' . $x, $geheim), $s) ? (int) $e : 0;
    }

    /**
     * Schickt eine bezahlte Bestellung als ENTWURF an Gelato.
     * @return array{ok:bool, grund:string, id?:string}
     */
    public static function entwurfSenden(int $bestellungId): array
    {
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'Gelato-Schlüssel fehlt in config.local.php.']; }
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        if (!$b || $b['status'] !== 'bezahlt') { return ['ok' => false, 'grund' => 'Nur bezahlte Bestellungen gehen an den Drucker.']; }
        $pos = Db::all('SELECT x.*, e.datei_druck_hash FROM wm_positionen x JOIN wm_entwuerfe e ON e.id = x.entwurf_id WHERE x.bestellung_id = ?', [$bestellungId]);
        $p = Db::one('SELECT email FROM partner WHERE id = ?', [(int) $b['partner_id']]);
        $items = [];
        foreach ($pos as $i => $x) {
            $a = self::artikel((int) $x['variante_id']);
            if (!$a) { return ['ok' => false, 'grund' => 'Für „' . $x['variante'] . '“ ist kein Gelato-Artikel eingetragen.']; }
            if (empty($x['datei_druck_hash'])) { return ['ok' => false, 'grund' => 'Zur Freigabe fehlt die Gelato-Druckdatei — der Partner muss die Datei neu erstellen und freigeben.']; }
            $items[] = [
                'itemReferenceId' => $b['nummer'] . '-' . ($i + 1),
                'productUid' => (string) $a['artikel'],
                'quantity' => (int) $a['menge'] * (int) $x['menge'],
                'files' => [['type' => 'default', 'url' => self::dateiLink((int) $x['entwurf_id'])]],
            ];
        }
        if (!$items) { return ['ok' => false, 'grund' => 'Die Bestellung hat keine Position.']; }
        $ad = (array) json_decode((string) $b['adresse'], true);
        [$vor, $nach] = self::namen((string) ($ad['name'] ?? ''));
        $koerper = [
            'orderType' => 'draft',
            'orderReferenceId' => (string) $b['nummer'],
            'customerReferenceId' => 'partner-' . (int) $b['partner_id'],
            'currency' => 'EUR',
            'items' => $items,
            'shippingAddress' => array_filter([
                'firstName' => $vor, 'lastName' => $nach, 'companyName' => (string) ($ad['firma'] ?? ''),
                'addressLine1' => (string) ($ad['strasse'] ?? ''), 'city' => (string) ($ad['ort'] ?? ''),
                'postCode' => (string) ($ad['plz'] ?? ''), 'country' => (string) ($ad['land'] ?? 'IT'),
                'email' => (string) ($p['email'] ?? ''), 'phone' => (string) ($ad['telefon'] ?? ''),
            ], static fn($v) => $v !== ''),
        ];

        // Sperre: nur wenn noch nichts gesendet wurde (anbieter_status leer).
        $gesperrt = Db::run("UPDATE wm_bestellungen SET anbieter = ?, anbieter_status = 'wird_gesendet', anbieter_fehler = NULL, anbieter_am = NOW()
                              WHERE id = ? AND status = 'bezahlt' AND anbieter_status IS NULL", [self::NAME, $bestellungId])->rowCount() === 1;
        if (!$gesperrt) { return ['ok' => false, 'grund' => 'Diese Bestellung wurde schon an Gelato gesendet (oder es läuft gerade).']; }

        try {
            $r = self::rufen('POST', '/v4/orders', $koerper);
        } catch (Throwable $e) {
            self::fehler($bestellungId, 'Keine Antwort von Gelato: ' . $e->getMessage() . ' — im Gelato-Dashboard nachsehen, ob der Entwurf ' . $b['nummer'] . ' trotzdem angelegt wurde.');
            return ['ok' => false, 'grund' => 'Keine Antwort von Gelato. Nichts wird wiederholt — bitte im Dashboard nachsehen.'];
        }
        $d = json_decode($r['body'], true);
        if ($r['code'] < 200 || $r['code'] >= 300 || !is_array($d) || empty($d['id'])) {
            $grund = is_array($d) ? (string) ($d['message'] ?? $d['error'] ?? json_encode($d)) : mb_substr($r['body'], 0, 300);
            self::fehler($bestellungId, 'Gelato lehnte ab (HTTP ' . $r['code'] . '): ' . mb_substr($grund, 0, 400));
            return ['ok' => false, 'grund' => 'Gelato lehnte ab: ' . mb_substr($grund, 0, 300)];
        }
        Db::run("UPDATE wm_bestellungen SET anbieter_ref = ?, anbieter_status = 'entwurf' WHERE id = ?", [mb_substr((string) $d['id'], 0, 120), $bestellungId]);
        return ['ok' => true, 'grund' => '', 'id' => (string) $d['id']];
    }

    /** Uwe hat nachgesehen: Fehlerstand aufheben, damit erneut gesendet werden kann. */
    public static function zuruecksetzen(int $bestellungId): bool
    {
        return Db::run("UPDATE wm_bestellungen SET anbieter_status = NULL, anbieter_fehler = NULL, anbieter = NULL, anbieter_ref = NULL
                         WHERE id = ? AND status = 'bezahlt' AND anbieter_status = 'fehler'", [$bestellungId])->rowCount() === 1;
    }

    /**
     * Cron: Stand der Bestellungen bei Gelato lesen. Nur lesen — gesetzt wird
     * höchstens „beim Drucker“ (Entwurf in Auftrag umgewandelt) und die
     * Sendungsnummer; „versendet“ samt Mail an den Partner bleibt Uwes Klick.
     */
    public static function nachsehen(): int
    {
        if (!self::bereit()) { return 0; }
        $n = 0;
        foreach (Db::all("SELECT id, status, anbieter_ref, tracking FROM wm_bestellungen WHERE anbieter = ? AND anbieter_ref IS NOT NULL
                           AND status IN ('bezahlt', 'beim_drucker') AND anbieter_status IN ('entwurf', 'auftrag') LIMIT 30", [self::NAME]) as $b) {
            try {
                $r = self::rufen('GET', '/v4/orders/' . rawurlencode((string) $b['anbieter_ref']), null);
                if ($r['code'] !== 200) { continue; }
                $d = (array) json_decode($r['body'], true);
                $typ = (string) ($d['orderType'] ?? '');
                $stand = (string) ($d['fulfillmentStatus'] ?? '');
                if ($typ === 'order' && $b['status'] === 'bezahlt') {
                    Db::run("UPDATE wm_bestellungen SET status = 'beim_drucker', beim_drucker_am = NOW(), anbieter_status = 'auftrag' WHERE id = ? AND status = 'bezahlt'", [(int) $b['id']]);
                    $n++;
                }
                if ($stand !== '') { Db::run('UPDATE wm_bestellungen SET anbieter_fehler = NULL, anbieter_am = NOW() WHERE id = ?', [(int) $b['id']]); }
                foreach ((array) ($d['shipment']['packages'] ?? []) as $pk) {
                    $code = trim((string) ($pk['trackingCode'] ?? ''));
                    $url = trim((string) ($pk['trackingUrl'] ?? ''));
                    if ($code !== '' && (string) $b['tracking'] === '') {
                        Db::run('UPDATE wm_bestellungen SET tracking = ?, tracking_url = ? WHERE id = ? AND tracking IS NULL',
                            [mb_substr($code, 0, 120), str_starts_with($url, 'https://') ? mb_substr($url, 0, 400) : null, (int) $b['id']]);
                        Events::melden('wm_sendung', 'Werbemittel unterwegs: Sendungsnummer von Gelato', 'hinweis',
                            'Gelato meldet ' . $code . '. In der Verwaltung „Versendet“ klicken — dann bekommt der Partner die Mail.', '/werbemittel/bestellungen');
                        $n++;
                        break;
                    }
                }
            } catch (Throwable $e) { error_log('Gelato::nachsehen ' . $b['id'] . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    // ---- intern -----------------------------------------------------------------

    private static function fehler(int $id, string $text): void
    {
        Db::run("UPDATE wm_bestellungen SET anbieter_status = 'fehler', anbieter_fehler = ? WHERE id = ?", [mb_substr($text, 0, 500), $id]);
        Events::melden('wm_gelato_fehler', 'Gelato: Entwurf nicht angelegt', 'schlecht', mb_substr($text, 0, 480), '/werbemittel/bestellungen');
    }

    /** „Maria Rossi“ → [Maria, Rossi]; ein Wort → [Wort, Wort] (beide Felder sind Pflicht). */
    public static function namen(string $name): array
    {
        $teile = preg_split('~\s+~u', trim($name)) ?: [];
        if (count($teile) < 2) { $w = $teile[0] ?? '-'; return [$w, $w]; }
        $nach = array_pop($teile);
        return [implode(' ', $teile), $nach];
    }

    /** @return array{code:int, body:string} */
    private static function rufen(string $methode, string $weg, ?array $koerper): array
    {
        $url = self::BASIS . $weg;
        // Den Schlüssel zeigt keine Meldung und kein Protokoll — er steht nur in dieser Kopfzeile.
        $kopf = ['X-API-KEY: ' . self::schluessel(), 'Content-Type: application/json', 'Accept: application/json'];
        $rumpf = $koerper === null ? null : (string) json_encode($koerper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (self::$netz !== null) { return (self::$netz)($methode, $url, $kopf, $rumpf); }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_HTTPHEADER => $kopf, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        if ($rumpf !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $rumpf); }
        $roh = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($roh === false) { throw new RuntimeException($fehler !== '' ? $fehler : 'Verbindung fehlgeschlagen'); }
        return ['code' => $code, 'body' => (string) $roh];
    }
}
