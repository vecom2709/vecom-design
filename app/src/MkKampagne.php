<?php
declare(strict_types=1);

/* ==========================================================================
   MkKampagne.php — Kampagnen-Links und was aus ihnen wird
   (Growth Engine Phase 3, 30.09.2026, Uwe: „ja“).

   WOZU

   Der Überblick (Phase 2) konnte nur sagen, welche Quelle die meisten
   Aufrufe bringt — nicht, welcher Beitrag Kunden bringt. 1.888 von 1.900
   Aufrufen kamen ohne erkennbare Herkunft. Ein Kampagnenlink schließt das:

     /k/CODE              eine Kampagne (z. B. „restaurants-herbst“)
     /k/CODE/WERBEMITTEL  ein Werbemittel darin (z. B. „reel3“)

   Der Klick legt einen Besuch in derselben Spur an wie ein Partnerlink
   (Spur::kampagnenBesuch) — dieselben Ereignisse (Preisrechner, Lead,
   Angebot, Auftrag, Zahlung), dieselben Datenschutzregeln. Hier wird nur
   verwaltet und ausgewertet.

   WAS NICHT GEHT, UND WARUM

   - Kein fremdes Ziel: Die Zielseite ist immer ein Pfad auf vecom-design.it.
     Ein Kampagnenlink darf nie zur offenen Weiterleitung werden.
   - Keine erfundene Zuordnung: Ein Lead gehört zur Kampagne nur, wenn sein
     Besuch über den Link begann (oder mit Einwilligung wiederkam) und er
     selbst seine Daten eingetragen hat.
   ========================================================================== */

final class MkKampagne
{
    public const PLATTFORMEN = [
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
        'pinterest' => 'Pinterest', 'threads' => 'Threads', 'x' => 'X', 'telegram' => 'Telegram', 'whatsapp' => 'WhatsApp',
        'google' => 'Google (Anzeige, Profil)', 'email' => 'E-Mail / Newsletter', 'sms' => 'SMS', 'flyer' => 'Flyer / Druck',
        'qr' => 'QR-Code', 'sonstige' => 'Sonstiges',
    ];
    /** utm_medium je Plattform — damit auch fremde Werkzeuge den Link richtig einordnen. */
    private const MEDIUM = ['email' => 'email', 'sms' => 'sms', 'flyer' => 'print', 'qr' => 'print', 'google' => 'cpc', 'sonstige' => 'link'];

    public const ARTEN = ['beitrag' => 'Beitrag', 'reel' => 'Reel / Kurzvideo', 'story' => 'Story', 'karussell' => 'Karussell', 'video' => 'Video',
        'anzeige' => 'Anzeige', 'newsletter' => 'Newsletter', 'flyer' => 'Flyer / Druck', 'sonstiges' => 'Sonstiges'];

    public const STATUS = ['aktiv' => 'Aktiv', 'pausiert' => 'Pausiert', 'beendet' => 'Beendet'];

    /** Häufige Ziele zur Auswahl — frei eintippen geht auch, solange es ein eigener Pfad ist. */
    public const ZIELE = [
        '/' => 'Startseite (IT)', '/de/' => 'Startseite (DE)', '/en/' => 'Startseite (EN)',
        '/analisi.php' => 'Website-Check (Ampel)', '/prezzi.html' => 'Preise (IT)', '/de/preise.html' => 'Preise (DE)',
        '/siti-web-ristoranti.html' => 'Ristoranti', '/siti-web-bed-and-breakfast.html' => 'B&B', '/siti-web-parrucchieri.html' => 'Parrucchieri',
        '/siti-web-artigiani.html' => 'Artigiani', '/siti-web-trasporti.html' => 'Trasporti', '/siti-web-agrigento.html' => 'Provincia di Agrigento',
        '/de/website-restaurant-cafe.html' => 'Restaurant/Café (DE)', '/de/website-friseur.html' => 'Friseur (DE)', '/de/website-handwerker.html' => 'Handwerker (DE)',
        '/de/website-kfz-werkstatt.html' => 'Kfz-Werkstatt (DE)', '/de/website-pension-ferienwohnung.html' => 'Pension/Ferienwohnung (DE)',
    ];

    /* ------------------------------------------------------------------ */
    /* Prüfen                                                              */
    /* ------------------------------------------------------------------ */

    public static function codeOk(string $c): bool { return preg_match('/^[a-z0-9][a-z0-9-]{2,23}$/', $c) === 1; }
    public static function werbemittelCodeOk(string $c): bool { return preg_match('/^[a-z0-9][a-z0-9-]{0,11}$/', $c) === 1; }

    /** Nur ein Pfad auf der eigenen Seite — kein Schema, kein //, keine Rückreise auf /k/ oder /p/. */
    public static function zielOk(string $z): bool
    {
        return preg_match('~^/(?!/)[A-Za-z0-9/_.\-]{0,180}$~', $z) === 1 && !preg_match('~^/(k|p|app)(/|$)~', $z) && !str_contains($z, '..');
    }

    /** Aus einem Namen ein lesbarer Code: „Restaurants Herbst 2026“ → restaurants-herbst-2026. */
    public static function slug(string $name, int $max = 24): string
    {
        $t = strtr(mb_strtolower(trim($name)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', '&' => '-']);
        $t = trim((string) preg_replace('~[^a-z0-9]+~', '-', $t), '-');
        if (strlen($t) <= $max) { return $t; }
        /* An einer Wortgrenze kürzen („instagram-restaurants“ statt „instagram-restaurant“); nur ein überlanges erstes Wort wird hart geschnitten. */
        $aus = '';
        foreach (explode('-', $t) as $wort) {
            $neu = $aus === '' ? $wort : $aus . '-' . $wort;
            if (strlen($neu) > $max) { break; }
            $aus = $neu;
        }
        return $aus !== '' ? $aus : substr($t, 0, $max);
    }

    /* ------------------------------------------------------------------ */
    /* Anlegen und ändern                                                  */
    /* ------------------------------------------------------------------ */

    /** @return int|string neue ID oder Fehlertext */
    public static function anlegen(array $d): int|string
    {
        $name = trim((string) ($d['name'] ?? ''));
        $plattform = (string) ($d['plattform'] ?? '');
        $ziel = trim((string) ($d['ziel'] ?? '/')) ?: '/';
        if ($name === '' || mb_strlen($name) > 120) { return 'Bitte einen Namen (bis 120 Zeichen) eingeben.'; }
        if (!isset(self::PLATTFORMEN[$plattform])) { return 'Bitte eine Plattform wählen.'; }
        if (!self::zielOk($ziel)) { return 'Die Zielseite muss eine Seite von vecom-design.it sein, z. B. /de/ oder /analisi.php.'; }
        $code = strtolower(trim((string) ($d['code'] ?? '')));
        if ($code !== '' && !self::codeOk($code)) { return 'Der Kurz-Code darf nur a–z, 0–9 und Bindestrich enthalten (3 bis 24 Zeichen).'; }
        if ($code === '') {
            $basis = self::slug($name, 21);
            if (strlen($basis) < 3) { $basis = 'kampagne'; }
            $code = $basis; $i = 2;
            while ((int) Db::wert('SELECT COUNT(*) FROM mk_kampagnen WHERE code = ?', [$code], 0) > 0) { $code = $basis . '-' . $i++; }
        } elseif ((int) Db::wert('SELECT COUNT(*) FROM mk_kampagnen WHERE code = ?', [$code], 0) > 0) {
            return 'Den Kurz-Code „' . $code . '“ gibt es schon.';
        }
        $id = (int) Db::insert('mk_kampagnen', ['code' => $code, 'name' => $name, 'plattform' => $plattform, 'ziel' => $ziel,
            'notiz' => mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 500)]);
        Events::pruefspur('kampagne_angelegt', 'mk_kampagnen', $id, [], ['code' => $code, 'name' => $name, 'plattform' => $plattform, 'ziel' => $ziel]);
        return $id;
    }

    /** @return ?string Fehler */
    public static function aendern(int $id, array $d): ?string
    {
        $k = self::laden($id);
        if ($k === null) { return 'Kampagne nicht gefunden.'; }
        $neu = [
            'name' => trim((string) ($d['name'] ?? $k['name'])),
            'ziel' => trim((string) ($d['ziel'] ?? $k['ziel'])) ?: '/',
            'status' => (string) ($d['status'] ?? $k['status']),
            'notiz' => mb_substr(trim((string) ($d['notiz'] ?? $k['notiz'])), 0, 500),
        ];
        if ($neu['name'] === '' || mb_strlen($neu['name']) > 120) { return 'Bitte einen Namen (bis 120 Zeichen) eingeben.'; }
        if (!self::zielOk($neu['ziel'])) { return 'Die Zielseite muss eine Seite von vecom-design.it sein.'; }
        if (!isset(self::STATUS[$neu['status']])) { return 'Unbekannter Status.'; }
        Db::update('mk_kampagnen', $id, $neu);
        Events::pruefspur('kampagne_geaendert', 'mk_kampagnen', $id, array_intersect_key($k, $neu), $neu);
        return null;
    }

    /** @return int|string neue ID oder Fehlertext */
    public static function werbemittelAnlegen(int $kampagneId, array $d): int|string
    {
        if (self::laden($kampagneId) === null) { return 'Kampagne nicht gefunden.'; }
        $name = trim((string) ($d['name'] ?? ''));
        $art = (string) ($d['art'] ?? 'beitrag');
        if ($name === '' || mb_strlen($name) > 120) { return 'Bitte einen Namen für das Werbemittel eingeben.'; }
        if (!isset(self::ARTEN[$art])) { $art = 'sonstiges'; }
        $code = strtolower(trim((string) ($d['code'] ?? '')));
        if ($code !== '' && !self::werbemittelCodeOk($code)) { return 'Der Code des Werbemittels: a–z, 0–9, Bindestrich, bis 12 Zeichen.'; }
        if ($code === '') {
            $basis = self::slug($name, 11) ?: 'w';
            $code = $basis; $i = 2;
            while ((int) Db::wert('SELECT COUNT(*) FROM mk_creatives WHERE kampagne_id = ? AND code = ?', [$kampagneId, $code], 0) > 0) { $code = substr($basis, 0, 9) . $i++; }
        } elseif ((int) Db::wert('SELECT COUNT(*) FROM mk_creatives WHERE kampagne_id = ? AND code = ?', [$kampagneId, $code], 0) > 0) {
            return 'Diesen Code gibt es in der Kampagne schon.';
        }
        $id = (int) Db::insert('mk_creatives', ['kampagne_id' => $kampagneId, 'code' => $code, 'name' => $name, 'art' => $art]);
        Events::pruefspur('werbemittel_angelegt', 'mk_creatives', $id, [], ['kampagne_id' => $kampagneId, 'code' => $code, 'name' => $name]);
        return $id;
    }

    /**
     * Kosten eintragen. Mit Beleg (Ausgabe „Werbung“): Betrag aus dem Beleg,
     * wenn keiner angegeben ist — und derselbe Beleg nur einmal.
     * @return ?string Fehler
     */
    public static function kostenAnlegen(int $kampagneId, array $d): ?string
    {
        if (self::laden($kampagneId) === null) { return 'Kampagne nicht gefunden.'; }
        $datum = (string) ($d['datum'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || strtotime($datum) === false) { return 'Bitte ein Datum wählen.'; }
        $betrag = (int) round((float) str_replace(',', '.', trim((string) ($d['betrag'] ?? '0'))) * 100);
        $ausgabe = (int) ($d['ausgabe_id'] ?? 0);
        if ($ausgabe > 0) {
            $a = Db::one("SELECT id, netto_cents, brutto_cents, kategorie FROM ausgaben WHERE id = ?", [$ausgabe]);
            if (!$a || $a['kategorie'] !== 'werbung') { return 'Nur Belege der Kategorie „Werbung“ lassen sich verbinden.'; }
            if ((int) Db::wert('SELECT COUNT(*) FROM mk_kosten WHERE ausgabe_id = ?', [$ausgabe], 0) > 0) { return 'Dieser Beleg hängt schon an einer Kampagne.'; }
            if ($betrag <= 0) { $betrag = (int) ($a['netto_cents'] > 0 ? $a['netto_cents'] : $a['brutto_cents']); }
        }
        if ($betrag <= 0 || $betrag > 100000000) { return 'Bitte einen Betrag über 0 eingeben.'; }
        $id = (int) Db::insert('mk_kosten', ['kampagne_id' => $kampagneId, 'datum' => $datum, 'betrag_cents' => $betrag,
            'notiz' => mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 200), 'ausgabe_id' => $ausgabe > 0 ? $ausgabe : null]);
        Events::pruefspur('kampagne_kosten', 'mk_kosten', $id, [], ['kampagne_id' => $kampagneId, 'betrag_cents' => $betrag, 'ausgabe_id' => $ausgabe ?: null]);
        return null;
    }

    public static function kostenLoeschen(int $id): ?int
    {
        $k = Db::one('SELECT * FROM mk_kosten WHERE id = ?', [$id]);
        if (!$k) { return null; }
        Db::run('DELETE FROM mk_kosten WHERE id = ?', [$id]);
        Events::pruefspur('kampagne_kosten_geloescht', 'mk_kosten', $id, $k, []);
        return (int) $k['kampagne_id'];
    }

    /* ------------------------------------------------------------------ */
    /* Lesen                                                               */
    /* ------------------------------------------------------------------ */

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM mk_kampagnen WHERE id = ?', [$id]) ?: null;
    }

    /** Für k.php: aktive Kampagne und (falls bekannt) ihr Werbemittel. @return array{0:?array,1:?array} */
    public static function ausCode(string $code, string $werbemittel = ''): array
    {
        $code = strtolower($code); $werbemittel = strtolower($werbemittel);
        if (!self::codeOk($code)) { return [null, null]; }
        $k = Db::one("SELECT * FROM mk_kampagnen WHERE code = ? AND status = 'aktiv'", [$code]) ?: null;
        if ($k === null || $werbemittel === '' || !self::werbemittelCodeOk($werbemittel)) { return [$k, null]; }
        return [$k, Db::one('SELECT * FROM mk_creatives WHERE kampagne_id = ? AND code = ?', [(int) $k['id'], $werbemittel]) ?: null];
    }

    public static function werbemittel(int $kampagneId): array
    {
        return Db::all('SELECT * FROM mk_creatives WHERE kampagne_id = ? ORDER BY id', [$kampagneId]);
    }

    public static function kosten(int $kampagneId): array
    {
        return Db::all('SELECT k.*, a.lieferant, a.beleg_nr FROM mk_kosten k LEFT JOIN ausgaben a ON a.id = k.ausgabe_id WHERE k.kampagne_id = ? ORDER BY k.datum DESC, k.id DESC', [$kampagneId]);
    }

    /** Belege „Werbung“, die noch an keiner Kampagne hängen (für die Auswahl). */
    public static function freieBelege(int $limit = 30): array
    {
        return Db::all("SELECT a.id, a.datum, a.lieferant, a.titel, a.netto_cents, a.brutto_cents FROM ausgaben a
                         WHERE a.kategorie = 'werbung' AND NOT EXISTS (SELECT 1 FROM mk_kosten k WHERE k.ausgabe_id = a.id)
                         ORDER BY a.datum DESC LIMIT " . max(1, min(200, $limit)));
    }

    public static function basis(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
    }

    /** Der Kurzlink zum Teilen. */
    public static function link(array $k, ?array $cr = null): string
    {
        return self::basis() . '/k/' . $k['code'] . ($cr !== null ? '/' . $cr['code'] : '');
    }

    /** Die UTM-Werte, die k.php an die Zielseite hängt (und die Anzeigen-Werkzeuge verstehen). */
    public static function utm(array $k, ?array $cr = null): array
    {
        $pl = (string) $k['plattform'];
        return array_filter(['utm_source' => $pl, 'utm_medium' => self::MEDIUM[$pl] ?? 'social', 'utm_campaign' => (string) $k['code'],
            'utm_content' => $cr['code'] ?? null], static fn($v) => $v !== null && $v !== '');
    }

    /** Wohin k.php weiterleitet: eigene Zielseite mit UTM. */
    public static function zielAdresse(array $k, ?array $cr = null): string
    {
        $ziel = self::zielOk((string) $k['ziel']) ? (string) $k['ziel'] : '/';
        return $ziel . '?' . http_build_query(self::utm($k, $cr));
    }

    /** QR-Code als SVG (lokal erzeugt, kein fremder Dienst). */
    public static function qr(string $url): string
    {
        require_once __DIR__ . '/../lib/qrcode.php';
        $qr = QRCode::getMinimumQRCode($url, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount(); $d = '';
        for ($y = 0; $y < $n; $y++) { for ($x = 0; $x < $n; $x++) { if ($qr->isDark($y, $x)) { $d .= "M{$x},{$y}h1v1h-1z"; } } }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 ' . ($n + 4) . ' ' . ($n + 4) . '" role="img" aria-label="QR-Code" shape-rendering="crispEdges"><rect x="-2" y="-2" width="' . ($n + 4) . '" height="' . ($n + 4) . '" fill="#fff"/><path fill="#000" d="' . $d . '"/></svg>';
    }

    /* ------------------------------------------------------------------ */
    /* Auswerten                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Zahlen je Kampagne (oder je Werbemittel einer Kampagne) im Zeitraum —
     * (Alias „gid“, nicht „id“: in GROUP BY gewinnt sonst die Spalte b.id / e.id
     * gegen den Alias, und jede Zeile bildet ihre eigene Gruppe — gemessen.)
     * aus den Einzeldaten, ältere Tage aus mk_tage. Verdächtige Mehrfachklicks
     * zählen als Klick, nicht als Besuch.
     * @return array<int, array<string,int>> Schlüssel: kampagne_id bzw. creative_id (0 = ohne Werbemittel)
     */
    public static function zahlen(string $von, string $bis, ?int $nurKampagne = null): array
    {
        $zeit = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $je = $nurKampagne !== null ? 'COALESCE(b.creative_id, 0)' : 'b.kampagne_id';
        $jeE = $nurKampagne !== null ? 'COALESCE(e.creative_id, 0)' : 'e.kampagne_id';
        $w = $nurKampagne !== null ? ' AND b.kampagne_id = ' . (int) $nurKampagne : '';
        $wE = $nurKampagne !== null ? ' AND e.kampagne_id = ' . (int) $nurKampagne : '';
        $leer = ['klicks' => 0, 'besuche' => 0, 'rechner' => 0, 'leads' => 0, 'angebote' => 0, 'kunden' => 0, 'auftraege' => 0, 'zahlungen' => 0, 'umsatz' => 0];
        $aus = [];
        foreach (Db::all("SELECT $je AS gid, COUNT(*) AS n FROM spur_besuche b WHERE b.kampagne_id IS NOT NULL AND b.verdacht = 0 AND b.start_am BETWEEN ? AND ?$w GROUP BY gid", $zeit) as $r) {
            $aus[(int) $r['gid']] = ['besuche' => (int) $r['n']] + $leer;
        }
        $feld = ['campaign_visit' => 'klicks', 'price_calculator_completed' => 'rechner', 'lead_created' => 'leads', 'offer_created' => 'angebote',
                 'customer_created' => 'kunden', 'order_created' => 'auftraege', 'payment_completed' => 'zahlungen'];
        foreach (Db::all("SELECT $jeE AS gid, e.event_type, COUNT(*) AS n, COUNT(DISTINCT COALESCE(e.besuch_id, -e.customer_id)) AS eindeutig, COALESCE(SUM(e.betrag_cents), 0) AS summe
                            FROM spur_ereignisse e LEFT JOIN spur_besuche b ON b.id = e.besuch_id
                           WHERE e.kampagne_id IS NOT NULL AND e.created_at BETWEEN ? AND ?$wE AND (b.id IS NULL OR b.verdacht = 0 OR e.event_type = 'campaign_visit')
                        GROUP BY gid, e.event_type", $zeit) as $r) {
            $id = (int) $r['gid']; $aus[$id] ??= $leer;
            if (!isset($feld[$r['event_type']])) { continue; }
            $f = $feld[$r['event_type']];
            $aus[$id][$f] += in_array($f, ['klicks', 'auftraege', 'zahlungen'], true) ? (int) $r['n'] : (int) $r['eindeutig'];
            if ($f === 'zahlungen') { $aus[$id]['umsatz'] += (int) $r['summe']; }
        }
        $jeT = $nurKampagne !== null ? 'creative_id' : 'kampagne_id';
        $wT = $nurKampagne !== null ? ' AND kampagne_id = ' . (int) $nurKampagne : '';
        foreach (Db::all("SELECT $jeT AS gid, event_type, SUM(anzahl) AS n, SUM(betrag_cents) AS summe FROM mk_tage WHERE tag BETWEEN ? AND ?$wT GROUP BY gid, event_type", [$von, $bis]) as $r) {
            $id = (int) $r['gid']; $aus[$id] ??= $leer;
            if (!isset($feld[$r['event_type']])) { continue; }
            $aus[$id][$feld[$r['event_type']]] += (int) $r['n'];
            if ($r['event_type'] === 'payment_completed') { $aus[$id]['umsatz'] += (int) $r['summe']; }
        }
        return $aus;
    }

    /** Kosten je Kampagne im Zeitraum. @return array<int,int> */
    public static function kostenJe(string $von, string $bis): array
    {
        $aus = [];
        foreach (Db::all('SELECT kampagne_id, SUM(betrag_cents) AS n FROM mk_kosten WHERE datum BETWEEN ? AND ? GROUP BY kampagne_id', [$von, $bis]) as $r) {
            $aus[(int) $r['kampagne_id']] = (int) $r['n'];
        }
        return $aus;
    }

    /**
     * Die Liste für die Seite „Kampagnen“: jede Kampagne mit ihren Zahlen und
     * Kosten, dazu die Summen. Filter: Plattform, Status.
     */
    public static function liste(string $von, string $bis, array $f = []): array
    {
        $w = []; $a = [];
        if (isset(self::PLATTFORMEN[(string) ($f['plattform'] ?? '')])) { $w[] = 'k.plattform = ?'; $a[] = (string) $f['plattform']; }
        if (isset(self::STATUS[(string) ($f['status'] ?? '')])) { $w[] = 'k.status = ?'; $a[] = (string) $f['status']; }
        $kamp = Db::all('SELECT k.*, (SELECT COUNT(*) FROM mk_creatives c WHERE c.kampagne_id = k.id) AS werbemittel FROM mk_kampagnen k'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY FIELD(k.status, 'aktiv', 'pausiert', 'beendet'), k.created_at DESC", $a);
        $zahlen = self::zahlen($von, $bis);
        $kosten = self::kostenJe($von, $bis);
        $summe = ['klicks' => 0, 'besuche' => 0, 'rechner' => 0, 'leads' => 0, 'angebote' => 0, 'kunden' => 0, 'auftraege' => 0, 'zahlungen' => 0, 'umsatz' => 0, 'kosten' => 0];
        foreach ($kamp as $i => $k) {
            $z = ($zahlen[(int) $k['id']] ?? []) + ['klicks' => 0, 'besuche' => 0, 'rechner' => 0, 'leads' => 0, 'angebote' => 0, 'kunden' => 0, 'auftraege' => 0, 'zahlungen' => 0, 'umsatz' => 0];
            $z['kosten'] = $kosten[(int) $k['id']] ?? 0;
            $kamp[$i] += $z;
            foreach ($summe as $s => $_) { $summe[$s] += (int) $z[$s]; }
        }
        return ['kampagnen' => $kamp, 'summe' => $summe];
    }

    /** Wer über die Kampagne kam und selbst seine Daten eingetragen hat. */
    public static function kontakte(int $kampagneId, int $limit = 50): array
    {
        return Db::all("SELECT c.id, c.name, c.company, MIN(b.start_am) AS erster_besuch, MAX(cr.name) AS werbemittel,
                               SUBSTRING_INDEX(GROUP_CONCAT(b.status ORDER BY FIELD(b.status, 'abgeschlossen','kunde','angebot','anfrage','rechner','interessent','besucher')), ',', 1) AS status
                          FROM spur_besuche b JOIN customers c ON c.id = b.customer_id LEFT JOIN mk_creatives cr ON cr.id = b.creative_id
                         WHERE b.kampagne_id = ? GROUP BY c.id, c.name, c.company ORDER BY erster_besuch DESC LIMIT " . max(1, min(500, $limit)), [$kampagneId]);
    }

    /**
     * Herkunft eines Kunden für seine Akte: der erste aufgezeichnete Besuch,
     * der zu ihm gehört — über welche Kampagne, welches Werbemittel, welchen
     * Partner, von wo. Null, wenn er ohne Link kam (dann weiß es niemand).
     */
    public static function herkunft(int $kundeId): ?array
    {
        try {
            $b = Db::one("SELECT b.*, k.name AS kampagne, k.id AS k_id, k.plattform, cr.name AS werbemittel, p.name AS partner
                            FROM spur_besuche b LEFT JOIN mk_kampagnen k ON k.id = b.kampagne_id LEFT JOIN mk_creatives cr ON cr.id = b.creative_id
                            LEFT JOIN partner p ON p.id = b.partner_id
                           WHERE b.customer_id = ? ORDER BY b.start_am ASC LIMIT 1", [$kundeId]);
            if (!$b) { return null; }
            $b['besuche'] = (int) Db::wert('SELECT COUNT(*) FROM spur_besuche WHERE customer_id = ?', [$kundeId], 0);
            return $b;
        } catch (Throwable $e) { return null; }
    }
}
