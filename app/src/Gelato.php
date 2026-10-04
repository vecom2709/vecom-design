<?php
declare(strict_types=1);

require_once __DIR__ . '/DruckereiSchnittstelle.php';


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
final class Gelato implements DruckereiAnbieter, DruckereiPreise
{
    public const BASIS = 'https://order.gelatoapis.com';
    public const NAME = 'Gelato';

    /** Prüfnaht für die Kette: fn(string $methode, string $url, array $kopf, ?string $rumpf): array{code:int, body:string} */
    public static $netz = null;

    /** Gelato liefert in alle Länder unserer Partner; $land spielt hier (noch) keine Rolle. */
    public static function bereit(?string $land = null): bool
    {
        return self::schluessel() !== '';
    }

    /** Echter Auftrag (DruckereiAnbieter) — derselbe Weg wie der Entwurf, nur mit Freigabe an Gelato. */
    public static function auftragSenden(int $bestellungId): array
    {
        return self::entwurfSenden($bestellungId, true);
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
        require_once __DIR__ . '/Druckerei.php';
        $geheim = Druckerei::linkGeheim();
        if (strlen($geheim) < 16) { throw new RuntimeException('app_geheim (oder hosting_geheim) fehlt in config.local.php — ohne ihn kein Link für die Druckdatei.'); }
        $bis = time() + $tage * 86400;
        $sig = hash_hmac('sha256', 'wm-druck|' . $entwurfId . '|' . $bis, $geheim);
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/druckdatei.php?' . http_build_query(['e' => $entwurfId, 'x' => $bis, 's' => $sig]);
    }

    /** Prüft einen Link aus dateiLink(). Gibt die Entwurfs-id oder 0. */
    public static function linkPruefen(string $e, string $x, string $s): int
    {
        require_once __DIR__ . '/Druckerei.php';
        $geheim = Druckerei::linkGeheim();
        if (strlen($geheim) < 16 || !ctype_digit($e) || !ctype_digit($x) || (int) $x < time()) { return 0; }
        return hash_equals(hash_hmac('sha256', 'wm-druck|' . $e . '|' . $x, $geheim), $s) ? (int) $e : 0;
    }

    /**
     * Schickt eine bezahlte Bestellung an Gelato — als ENTWURF (Uwe bestätigt
     * im Dashboard) oder, im Automatikbetrieb, als echten AUFTRAG
     * (orderType "order": Gelato druckt und berechnet sofort). Beides nur
     * einmal; ein Fehler bleibt stehen und wird nicht wiederholt.
     * @return array{ok:bool, grund:string, id?:string}
     */
    public static function entwurfSenden(int $bestellungId, bool $auftrag = false): array
    {
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'Gelato-Schlüssel fehlt in config.local.php.']; }
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        if (!$b || $b['status'] !== 'bezahlt') { return ['ok' => false, 'grund' => 'Nur bezahlte Bestellungen gehen an den Drucker.']; }
        $pos = Db::all('SELECT x.*, e.datei_druck_hash, w.vorlage FROM wm_positionen x JOIN wm_entwuerfe e ON e.id = x.entwurf_id
                         JOIN wm_produkte w ON w.id = e.produkt_id WHERE x.bestellung_id = ?', [$bestellungId]);
        $p = Db::one('SELECT email FROM partner WHERE id = ?', [(int) $b['partner_id']]);
        $items = [];
        foreach ($pos as $i => $x) {
            $a = self::artikel((int) $x['variante_id']);
            if (!$a) { return ['ok' => false, 'grund' => 'Für „' . $x['variante'] . '“ ist kein Gelato-Artikel eingetragen.']; }
            if (empty($x['datei_druck_hash'])) { return ['ok' => false, 'grund' => 'Zur Freigabe fehlt die Gelato-Druckdatei — der Partner muss die Datei neu erstellen und freigeben.']; }
            $item = [
                'itemReferenceId' => $b['nummer'] . '-' . ($i + 1),
                'productUid' => (string) $a['artikel'],
                'quantity' => (int) $a['menge'] * (int) $x['menge'],
                'files' => [['type' => 'default', 'url' => self::dateiLink((int) $x['entwurf_id'])]],
            ];
            // Mehrseitige Produkte (Wandkalender): pageCount laut Doku „alle Seiten inklusive Vorder- und Rückseite“.
            if ((string) $x['vorlage'] === 'kalender_a3') { require_once __DIR__ . '/WmKalender.php'; $item['pageCount'] = WmKalender::SEITEN; }
            $items[] = $item;
        }
        if (!$items) { return ['ok' => false, 'grund' => 'Die Bestellung hat keine Position.']; }
        $ad = (array) json_decode((string) $b['adresse'], true);
        [$vor, $nach] = self::namen((string) ($ad['name'] ?? ''));
        // Feldlängen laut Doku (Quote/Order, 04.10.2026): Name je 25, Straße 35, Ort 30, PLZ 15, Telefon 25.
        $k = static fn(string $t, int $n): string => mb_substr(trim($t), 0, $n);
        $koerper = [
            'orderType' => $auftrag ? 'order' : 'draft',
            'orderReferenceId' => (string) $b['nummer'],
            'customerReferenceId' => 'partner-' . (int) $b['partner_id'],
            'currency' => 'EUR',
            'items' => $items,
            'shippingAddress' => array_filter([
                'firstName' => $k($vor, 25), 'lastName' => $k($nach, 25), 'companyName' => $k((string) ($ad['firma'] ?? ''), 60),
                'addressLine1' => $k((string) ($ad['strasse'] ?? ''), 35), 'city' => $k((string) ($ad['ort'] ?? ''), 30),
                'postCode' => $k((string) ($ad['plz'] ?? ''), 15), 'country' => (string) ($ad['land'] ?? 'IT'),
                'email' => (string) ($p['email'] ?? ''), 'phone' => $k((string) ($ad['telefon'] ?? ''), 25),
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
        if ($auftrag) {
            Db::run("UPDATE wm_bestellungen SET anbieter_ref = ?, anbieter_status = 'auftrag', status = 'beim_drucker', beim_drucker_am = NOW() WHERE id = ? AND status = 'bezahlt'",
                [mb_substr((string) $d['id'], 0, 120), $bestellungId]);
        } else {
            Db::run("UPDATE wm_bestellungen SET anbieter_ref = ?, anbieter_status = 'entwurf' WHERE id = ?", [mb_substr((string) $d['id'], 0, 120), $bestellungId]);
        }
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
        require_once __DIR__ . '/WmBestellung.php';
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
                        $link = str_starts_with($url, 'https://') ? mb_substr($url, 0, 390) : '';
                        if (WmBestellung::automatik()) {
                            // Automatikbetrieb (Uwe, 04.10.2026: „nichts von Hand“): versendet, Partner bekommt die Mail.
                            Db::run("UPDATE wm_bestellungen SET status = 'beim_drucker', beim_drucker_am = COALESCE(beim_drucker_am, NOW()) WHERE id = ? AND status = 'bezahlt'", [(int) $b['id']]);
                            WmBestellung::versendet((int) $b['id'], mb_substr($code, 0, 120), $link);
                        } else {
                            Db::run('UPDATE wm_bestellungen SET tracking = ?, tracking_url = ? WHERE id = ? AND tracking IS NULL',
                                [mb_substr($code, 0, 120), $link !== '' ? $link : null, (int) $b['id']]);
                            Events::melden('wm_sendung', 'Werbemittel unterwegs: Sendungsnummer von Gelato', 'hinweis',
                                'Gelato meldet ' . $code . '. In der Verwaltung „Versendet“ klicken — dann bekommt der Partner die Mail.', '/werbemittel/bestellungen');
                        }
                        $n++;
                        break;
                    }
                }
            } catch (Throwable $e) { error_log('Gelato::nachsehen ' . $b['id'] . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    /**
     * Preis bei Gelato erfragen (Quote-API, Doku gelesen 04.10.2026:
     * POST https://order.gelatoapis.com/v4/orders:quote). Nur lesen — es
     * entsteht keine Bestellung. Zurück: Produkt + günstigster normaler
     * Versand, netto in Cent, oder null.
     * @return ?array{netto:int, versand:string, min:int, max:int}
     */
    /** Grund der letzten Absage beim Preisholen (HTTP-Status und Gelatos eigener Text), für die Meldung in der Verwaltung. */
    public static string $letzterGrund = '';

    public static function angebotHolen(string $artikel, int $menge, string $land): ?array
    {
        $empf = ['IT' => ['firstName' => 'Vecom', 'lastName' => 'Design', 'addressLine1' => 'Via Atenea 1', 'city' => 'Agrigento', 'postCode' => '92100'],
                 'DE' => ['firstName' => 'Vecom', 'lastName' => 'Design', 'addressLine1' => 'Unter den Linden 1', 'city' => 'Berlin', 'postCode' => '10117']][$land] ?? null;
        if ($empf === null) { return null; }
        $empf += ['country' => $land, 'email' => (string) Config::get('email', 'kontakt@vecom-design.it')];
        $r = self::rufen('POST', '/v4/orders:quote', [
            'orderReferenceId' => 'preis-' . $land . '-' . $menge, 'customerReferenceId' => 'vecom-preis', 'currency' => 'EUR',
            'allowMultipleQuotes' => false, 'recipient' => $empf,
            'products' => [['itemReferenceId' => 'p1', 'productUid' => $artikel, 'quantity' => $menge]],
        ]);
        $d = json_decode($r['body'], true);
        $q = is_array($d) ? ($d['quotes'][0] ?? null) : null;
        if ($r['code'] !== 200 || !is_array($q)) {
            // Was Gelato sagt, damit die Verwaltung den Grund zeigen kann (nie der Schlüssel — der steht nur in der Kopfzeile).
            $text = is_array($d) ? (string) ($d['message'] ?? json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : (string) $r['body'];
            self::$letzterGrund = 'HTTP ' . $r['code'] . ($text !== '' ? ': ' . mb_substr(trim($text), 0, 300) : '');
            return null;
        }
        $produkt = 0.0;
        foreach ((array) ($q['products'] ?? []) as $x) { if (strtoupper((string) ($x['currency'] ?? '')) !== 'EUR') { self::$letzterGrund = 'Preis nicht in Euro'; return null; } $produkt += (float) ($x['price'] ?? 0); }
        $versand = null;
        foreach ((array) ($q['shipmentMethods'] ?? []) as $m) {
            if (strtoupper((string) ($m['currency'] ?? '')) !== 'EUR' || !in_array((string) ($m['type'] ?? ''), ['normal', 'standard'], true)) { continue; }
            if ($versand === null || (float) $m['price'] < (float) $versand['price']) { $versand = $m; }
        }
        if ($produkt <= 0 || $versand === null) { self::$letzterGrund = $produkt <= 0 ? 'kein Produktpreis in der Antwort' : 'keine Standard-Versandart in Euro'; return null; }
        return ['netto' => (int) round(($produkt + (float) $versand['price']) * 100), 'versand' => (string) ($versand['name'] ?? ''),
                'min' => (int) ($versand['minDeliveryDays'] ?? 0), 'max' => (int) ($versand['maxDeliveryDays'] ?? 0)];
    }

    /**
     * Gelato-Preise aller zugeordneten Auflagen für Italien und Deutschland
     * holen und als Angebote eintragen (brutto mit der Mehrwertsteuer des
     * Lieferlands, weil Vecom ohne Partita IVA sie zahlt). Cron, wöchentlich.
     */
    public static function preiseAktualisieren(): int
    {
        if (!self::bereit()) { return 0; }
        require_once __DIR__ . '/Werbemittel.php';
        $n = 0;
        foreach (Db::all("SELECT variante_id, artikel, menge FROM wm_anbieter_produkte WHERE anbieter = 'gelato'") as $z) {
            foreach (array_keys(Werbemittel::LIEFERLAENDER) as $land) {
                try {
                    $a = self::angebotHolen((string) $z['artikel'], (int) $z['menge'], $land);
                    if (!$a) { continue; }
                    $brutto = (int) round($a['netto'] * (100 + Werbemittel::MWST[$land]) / 100);
                    Werbemittel::angebotSpeichern((int) $z['variante_id'], [
                        'anbieter' => self::NAME, 'land' => $land, 'preis_eur' => number_format($brutto / 100, 2, ',', ''),
                        'netto_eur' => number_format($a['netto'] / 100, 2, ',', ''), 'papier' => 'laut Gelato-Artikel ' . $z['artikel'],
                        'lieferung' => trim($a['versand'] . ($a['max'] > 0 ? ', ' . $a['min'] . '–' . $a['max'] . ' Tage' : '')) . ' · automatisch',
                        'link' => 'https://dashboard.gelato.com', 'geprueft_am' => date('Y-m-d'),
                    ]);
                    $n++;
                } catch (Throwable $e) { self::$letzterGrund = 'keine Antwort: ' . mb_substr($e->getMessage(), 0, 200); error_log('Gelato::preiseAktualisieren: ' . $e->getMessage()); }
            }
        }
        return $n;
    }

    // ---- intern -----------------------------------------------------------------

    private static function fehler(int $id, string $text): void
    {
        Db::run("UPDATE wm_bestellungen SET anbieter_status = 'fehler', anbieter_fehler = ? WHERE id = ?", [mb_substr($text, 0, 500), $id]);
        Events::melden('wm_gelato_fehler', 'Gelato: Entwurf nicht angelegt', 'schlecht', mb_substr($text, 0, 480), '/werbemittel/bestellungen');
    }

    /** Was die Bestellung bei Gelato JETZT kostet (brutto mit MwSt des Lieferlands, wie die Angebote) — null, wenn nicht abrufbar. */
    public static function preisJetzt(int $bestellungId): ?int
    {
        require_once __DIR__ . '/Werbemittel.php';
        $b = Db::one('SELECT adresse FROM wm_bestellungen WHERE id = ?', [$bestellungId]);
        $ad = (array) json_decode((string) ($b['adresse'] ?? ''), true);
        $land = strtoupper((string) ($ad['land'] ?? ''));
        if (!isset(Werbemittel::MWST[$land])) { return null; }
        $summe = 0;
        foreach (Db::all('SELECT variante_id, menge FROM wm_positionen WHERE bestellung_id = ?', [$bestellungId]) as $x) {
            $a = self::artikel((int) $x['variante_id']);
            if (!$a) { return null; }
            try { $q = self::angebotHolen((string) $a['artikel'], (int) $a['menge'] * (int) $x['menge'], $land); } catch (Throwable $e) { return null; }
            if (!$q) { return null; }
            $summe += (int) round($q['netto'] * (100 + Werbemittel::MWST[$land]) / 100);
        }
        return $summe > 0 ? $summe : null;
    }

    /** Probe-Entwurf mit der Musterkarte (siehe Druckerei::probeSenden) — kleinste zugeordnete Auflage, immer „draft“. */
    public static function probeSenden(): array
    {
        require_once __DIR__ . '/Druckerei.php';
        $a = Db::one("SELECT artikel, menge FROM wm_anbieter_produkte WHERE anbieter = 'gelato' ORDER BY menge, id LIMIT 1");
        if (!$a) { return ['ok' => false, 'grund' => 'Kein Gelato-Artikel zugeordnet.']; }
        $ad = Druckerei::MUSTER_ADRESSE;
        [$vor, $nach] = self::namen($ad['name']);
        $ref = 'PROBE-' . date('Ymd-His');
        try {
            $r = self::rufen('POST', '/v4/orders', [
                'orderType' => 'draft', 'orderReferenceId' => $ref, 'customerReferenceId' => 'vecom-probe', 'currency' => 'EUR',
                'items' => [['itemReferenceId' => $ref . '-1', 'productUid' => (string) $a['artikel'], 'quantity' => (int) $a['menge'],
                             'files' => [['type' => 'default', 'url' => Druckerei::dateiLink(0, 'probe_druck', 2)]]]],
                'shippingAddress' => ['firstName' => $vor, 'lastName' => $nach, 'companyName' => $ad['firma'], 'addressLine1' => $ad['strasse'],
                                      'city' => $ad['ort'], 'postCode' => $ad['plz'], 'country' => $ad['land'], 'email' => (string) Config::get('email', 'kontakt@vecom-design.it')],
            ]);
        } catch (Throwable $e) { return ['ok' => false, 'grund' => 'Keine Antwort von Gelato: ' . $e->getMessage()]; }
        $d = json_decode($r['body'], true);
        if ($r['code'] < 200 || $r['code'] >= 300 || !is_array($d) || empty($d['id'])) {
            $grund = is_array($d) ? (string) ($d['message'] ?? json_encode($d, JSON_UNESCAPED_UNICODE)) : mb_substr($r['body'], 0, 300);
            return ['ok' => false, 'grund' => 'Gelato lehnte ab (HTTP ' . $r['code'] . '): ' . mb_substr($grund, 0, 300)];
        }
        return ['ok' => true, 'grund' => '', 'id' => (string) $d['id'], 'ref' => $ref];
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
