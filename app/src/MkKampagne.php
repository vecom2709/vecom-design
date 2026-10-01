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
        'qr' => 'QR-Code', 'verzeichnis' => 'Verzeichnis / Eintrag', 'sonstige' => 'Sonstiges',
    ];
    /** utm_medium je Plattform — damit auch fremde Werkzeuge den Link richtig einordnen. */
    private const MEDIUM = ['email' => 'email', 'sms' => 'sms', 'flyer' => 'print', 'qr' => 'print', 'google' => 'cpc', 'verzeichnis' => 'referral', 'sonstige' => 'link'];

    public const ARTEN = ['beitrag' => 'Beitrag', 'reel' => 'Reel / Kurzvideo', 'story' => 'Story', 'karussell' => 'Karussell', 'video' => 'Video',
        'anzeige' => 'Anzeige', 'newsletter' => 'Newsletter', 'flyer' => 'Flyer / Druck', 'sonstiges' => 'Sonstiges'];

    public const STATUS = ['aktiv' => 'Aktiv', 'pausiert' => 'Pausiert', 'beendet' => 'Beendet'];

    /* Phase 4 (Kampagnen-Manager): woran der Erfolg gemessen wird — jeweils ein
       echtes Ereignis aus der Spur, nie ein Klick. [Wort, Feld in zahlen()] */
    public const ZIEL_ARTEN = [
        'leads'         => ['Leads (E-Mail oder Anfrage)', 'leads'],
        'website_check' => ['Website-Checks', 'checks'],
        'rechner'       => ['Preisrechner abgeschlossen', 'rechner'],
        'termin'        => ['Termine gebucht', 'termine'],
        'kunden'        => ['Neue Kunden', 'kunden'],
        'besuche'       => ['Besuche (Bekanntheit)', 'besuche'],
    ];
    /** Der Handlungsaufruf im Beitrag oder in der Anzeige. */
    public const CTA = [
        'website_check' => 'Kostenloser Website-Check', 'preis' => 'Preis berechnen', 'termin' => 'Termin buchen',
        'whatsapp' => 'WhatsApp schreiben', 'anruf' => 'Anrufen', 'angebot' => 'Angebot anfordern',
        'mehr' => 'Mehr erfahren', 'eigen' => 'Eigener Text',
    ];
    /** Alle Zahlen einer Kampagne, leer. */
    public const LEER = ['klicks' => 0, 'besuche' => 0, 'checks' => 0, 'rechner' => 0, 'termine' => 0, 'leads' => 0, 'angebote' => 0, 'kunden' => 0,
        'auftraege' => 0, 'zahlungen' => 0, 'umsatz' => 0];
    public const BUDGET_ARTEN = ['gesamt' => 'für die ganze Kampagne', 'monat' => 'je Kalendermonat'];
    /** Ab diesem Anteil warnt die Budgetgrenze (Meldung und Farbe). */
    public const BUDGET_WARNUNG = 80;

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

    /** Branchen wie in der Akquise (akquise_branchen.json) — ein Wortschatz für alles. @return array<string,string> */
    public static function branchen(): array
    {
        static $b = null;
        if ($b !== null) { return $b; }
        $b = [];
        $j = json_decode((string) @file_get_contents(__DIR__ . '/akquise_branchen.json'), true);
        foreach (is_array($j) ? $j : [] as $k => $v) {
            if ($k[0] !== '_' && is_array($v)) { $b[(string) $k] = (string) ($v['de'] ?? $k); }
        }
        return $b;
    }

    /**
     * Ziel, Branche, CTA, Budget und Laufzeit aus einem Formular prüfen.
     * @return array<string,mixed>|string die Felder oder ein Fehlertext
     */
    public static function felder(array $d, ?array $alt = null): array|string
    {
        $wert = static fn(string $k, $vor) => array_key_exists($k, $d) ? $d[$k] : $vor;
        $ziel = (string) $wert('ziel_art', $alt['ziel_art'] ?? 'leads');
        $branche = (string) $wert('branche', $alt['branche'] ?? '');
        $cta = (string) $wert('cta', $alt['cta'] ?? '');
        $ctaText = mb_substr(trim((string) $wert('cta_text', $alt['cta_text'] ?? '')), 0, 120);
        $budgetRoh = trim((string) $wert('budget', isset($alt['budget_cents']) && $alt['budget_cents'] !== null ? number_format((int) $alt['budget_cents'] / 100, 2, '.', '') : ''));
        $budgetArt = (string) $wert('budget_art', $alt['budget_art'] ?? 'gesamt');
        $start = trim((string) $wert('start_am', $alt['start_am'] ?? ''));
        $ende = trim((string) $wert('ende_am', $alt['ende_am'] ?? ''));
        if (!isset(self::ZIEL_ARTEN[$ziel])) { return 'Bitte ein Ziel wählen.'; }
        if ($branche !== '' && !isset(self::branchen()[$branche])) { return 'Unbekannte Branche.'; }
        if ($cta !== '' && !isset(self::CTA[$cta])) { return 'Unbekannter Handlungsaufruf.'; }
        if ($cta === 'eigen' && $ctaText === '') { return 'Bitte den eigenen Handlungsaufruf ausschreiben.'; }
        if ($cta !== 'eigen') { $ctaText = ''; }
        $budget = null;
        if ($budgetRoh !== '') {
            if (!preg_match('/^\d{1,7}([.,]\d{1,2})?$/', $budgetRoh)) { return 'Das Budget bitte als Betrag in Euro, z. B. 150 oder 150,00.'; }
            $budget = (int) round((float) str_replace(',', '.', $budgetRoh) * 100);
            if ($budget <= 0) { $budget = null; }
        }
        if (!isset(self::BUDGET_ARTEN[$budgetArt])) { $budgetArt = 'gesamt'; }
        $datum = static fn(string $t): bool => $t === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1 && strtotime($t) !== false);
        if (!$datum($start) || !$datum($ende)) { return 'Bitte gültige Daten für die Laufzeit.'; }
        if ($start !== '' && $ende !== '' && $ende < $start) { return 'Das Ende der Laufzeit liegt vor dem Start.'; }
        return ['ziel_art' => $ziel, 'branche' => $branche, 'cta' => $cta, 'cta_text' => $ctaText, 'budget_cents' => $budget, 'budget_art' => $budgetArt,
                'start_am' => $start !== '' ? $start : null, 'ende_am' => $ende !== '' ? $ende : null];
    }

    /** @return int|string neue ID oder Fehlertext */
    public static function anlegen(array $d): int|string
    {
        $name = trim((string) ($d['name'] ?? ''));
        $plattform = (string) ($d['plattform'] ?? '');
        $ziel = trim((string) ($d['ziel'] ?? '/')) ?: '/';
        if ($name === '' || mb_strlen($name) > 120) { return 'Bitte einen Namen (bis 120 Zeichen) eingeben.'; }
        if (!isset(self::PLATTFORMEN[$plattform])) { return 'Bitte eine Plattform wählen.'; }
        if (!self::zielOk($ziel)) { return 'Die Zielseite muss eine Seite von vecom-design.it sein, z. B. /de/ oder /analisi.php.'; }
        $mehr = self::felder($d);
        if (is_string($mehr)) { return $mehr; }
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
        /* Marketing-Studio 5: Land aus der Zielseite (oder ausdrücklich), dazu die Zielgruppe, aus der die Kampagne entstand. */
        require_once __DIR__ . '/MkLand.php';
        $land = strtoupper((string) ($d['land'] ?? ''));
        $mehr['land'] = isset(MkLand::NAMEN[$land]) ? $land : MkLand::ausZiel($ziel);
        if ((int) ($d['zielgruppe_id'] ?? 0) > 0 && Db::wert('SELECT id FROM mk_zielgruppen WHERE id = ?', [(int) $d['zielgruppe_id']], null) !== null) { $mehr['zielgruppe_id'] = (int) $d['zielgruppe_id']; }
        $id = (int) Db::insert('mk_kampagnen', ['code' => $code, 'name' => $name, 'plattform' => $plattform, 'ziel' => $ziel,
            'notiz' => mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 500)] + $mehr);
        Events::pruefspur('kampagne_angelegt', 'mk_kampagnen', $id, [], ['code' => $code, 'name' => $name, 'plattform' => $plattform, 'ziel' => $ziel] + $mehr);
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
        $mehr = self::felder($d, $k);
        if (is_string($mehr)) { return $mehr; }
        $neu += $mehr;
        if ($neu['ziel'] !== $k['ziel']) { require_once __DIR__ . '/MkLand.php'; $neu['land'] = MkLand::ausZiel($neu['ziel']); }
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
        $vorher = self::budget(self::laden($kampagneId) ?? [], $datum);
        $id = (int) Db::insert('mk_kosten', ['kampagne_id' => $kampagneId, 'datum' => $datum, 'betrag_cents' => $betrag,
            'notiz' => mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 200), 'ausgabe_id' => $ausgabe > 0 ? $ausgabe : null]);
        Events::pruefspur('kampagne_kosten', 'mk_kosten', $id, [], ['kampagne_id' => $kampagneId, 'betrag_cents' => $betrag, 'ausgabe_id' => $ausgabe ?: null]);
        self::budgetMelden(self::laden($kampagneId) ?? [], $vorher, self::budget(self::laden($kampagneId) ?? [], $datum));
        return null;
    }

    /**
     * Stand der Budgetgrenze: ausgegeben (ganze Kampagne oder der Monat des
     * Stichtags) gegen die Grenze. Null ohne Grenze.
     * @return array{grenze:int, ausgegeben:int, anteil:float, stufe:string, art:string}|null  stufe: ok | knapp | erreicht
     */
    public static function budget(array $k, ?string $stichtag = null): ?array
    {
        if (empty($k['id']) || empty($k['budget_cents'])) { return null; }
        $grenze = (int) $k['budget_cents'];
        $monat = ($k['budget_art'] ?? 'gesamt') === 'monat';
        $tag = $stichtag ?? date('Y-m-d');
        $aus = $monat
            ? (int) Db::wert('SELECT COALESCE(SUM(betrag_cents),0) FROM mk_kosten WHERE kampagne_id = ? AND datum BETWEEN ? AND ?', [(int) $k['id'], date('Y-m-01', strtotime($tag)), date('Y-m-t', strtotime($tag))], 0)
            : (int) Db::wert('SELECT COALESCE(SUM(betrag_cents),0) FROM mk_kosten WHERE kampagne_id = ?', [(int) $k['id']], 0);
        $anteil = round($aus / $grenze * 100, 1);
        return ['grenze' => $grenze, 'ausgegeben' => $aus, 'anteil' => $anteil, 'art' => $monat ? 'monat' : 'gesamt',
                'stufe' => $anteil >= 100 ? 'erreicht' : ($anteil >= self::BUDGET_WARNUNG ? 'knapp' : 'ok')];
    }

    /** Meldung, wenn neue Kosten die Warnschwelle oder die Grenze überschreiten — einmal je Übergang. */
    private static function budgetMelden(array $k, ?array $vorher, ?array $nachher): void
    {
        if ($nachher === null || empty($k['id'])) { return; }
        $rang = ['ok' => 0, 'knapp' => 1, 'erreicht' => 2];
        if ($rang[$nachher['stufe']] <= $rang[$vorher['stufe'] ?? 'ok']) { return; }
        $erreicht = $nachher['stufe'] === 'erreicht';
        try {
            Events::melden('kampagne_budget', 'Kampagne „' . $k['name'] . '“: Budget ' . ($erreicht ? 'erreicht' : 'zu ' . number_format($nachher['anteil'], 0, ',', '.') . ' % verbraucht'),
                $erreicht ? 'schlecht' : 'warnung',
                Fmt::geld($nachher['ausgegeben']) . ' von ' . Fmt::geld($nachher['grenze']) . ($nachher['art'] === 'monat' ? ' in diesem Monat' : '')
                . ' — die Anzeige läuft bei der Plattform weiter, bis du sie dort pausierst.', '/kampagnen/' . (int) $k['id']);
        } catch (Throwable $e) { }
    }

    /** Läuft die Kampagne laut Laufzeit? vor | laeuft | vorbei | offen (ohne Laufzeit) */
    public static function laufzeit(array $k, ?string $heute = null): string
    {
        $h = $heute ?? date('Y-m-d');
        if (empty($k['start_am']) && empty($k['ende_am'])) { return 'offen'; }
        if (!empty($k['start_am']) && $h < $k['start_am']) { return 'vor'; }
        if (!empty($k['ende_am']) && $h > $k['ende_am']) { return 'vorbei'; }
        return 'laeuft';
    }

    /** Die Zahl, an der die Kampagne gemessen wird. */
    public static function zielWert(array $k, array $z): int
    {
        return (int) ($z[self::ZIEL_ARTEN[$k['ziel_art'] ?? 'leads'][1] ?? 'leads'] ?? 0);
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
        $q = self::utm($k, $cr);
        /* Marketing-Studio 6: Eine PHP-Seite für alle Sprachen (z. B. der Website-Check) erfährt das Land der Kampagne. */
        if (str_ends_with($ziel, '.php') && in_array($k['land'] ?? '', ['IT', 'DE'], true)) { $q = ['lang' => strtolower((string) $k['land'])] + $q; }
        return $ziel . '?' . http_build_query($q);
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
        $leer = self::LEER;
        $aus = [];
        foreach (Db::all("SELECT $je AS gid, COUNT(*) AS n FROM spur_besuche b WHERE b.kampagne_id IS NOT NULL AND b.verdacht = 0 AND b.start_am BETWEEN ? AND ?$w GROUP BY gid", $zeit) as $r) {
            $aus[(int) $r['gid']] = ['besuche' => (int) $r['n']] + $leer;
        }
        $feld = ['campaign_visit' => 'klicks', 'price_calculator_completed' => 'rechner', 'lead_created' => 'leads', 'offer_created' => 'angebote',
                 'customer_created' => 'kunden', 'order_created' => 'auftraege', 'payment_completed' => 'zahlungen',
                 'website_check_completed' => 'checks', 'appointment_requested' => 'termine'];
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
        if (isset(self::branchen()[(string) ($f['branche'] ?? '')])) { $w[] = 'k.branche = ?'; $a[] = (string) $f['branche']; }
        /* Ein Land zeigt seine Kampagnen und die ohne Land (z. B. englische Seite) — nie die des anderen. */
        if (in_array((string) ($f['land'] ?? ''), ['IT', 'DE'], true)) { $w[] = "k.land IN (?, '')"; $a[] = (string) $f['land']; }
        $kamp = Db::all('SELECT k.*, (SELECT COUNT(*) FROM mk_creatives c WHERE c.kampagne_id = k.id) AS werbemittel FROM mk_kampagnen k'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY FIELD(k.status, 'aktiv', 'pausiert', 'beendet'), k.created_at DESC", $a);
        $zahlen = self::zahlen($von, $bis);
        $kosten = self::kostenJe($von, $bis);
        $summe = self::LEER + ['kosten' => 0];
        /* Vergleich nach Branche, Handlungsaufruf und Plattform (Phase 4): dieselben Zahlen, anders gebündelt. */
        $gruppen = ['branche' => [], 'cta' => [], 'plattform' => []];
        foreach ($kamp as $i => $k) {
            $z = ($zahlen[(int) $k['id']] ?? []) + self::LEER;
            $z['kosten'] = $kosten[(int) $k['id']] ?? 0;
            $kamp[$i] += $z;
            $kamp[$i]['zielwert'] = self::zielWert($k, $z);
            $kamp[$i]['budget'] = self::budget($k);
            $kamp[$i]['laufzeit'] = self::laufzeit($k);
            foreach ($summe as $s => $_) { $summe[$s] += (int) $z[$s]; }
            foreach (['branche' => (string) $k['branche'], 'cta' => (string) $k['cta'], 'plattform' => (string) $k['plattform']] as $g => $schl) {
                $schl = $schl !== '' ? $schl : '—';
                $gruppen[$g][$schl] ??= ['kampagnen' => 0] + self::LEER + ['kosten' => 0];
                $gruppen[$g][$schl]['kampagnen']++;
                foreach (self::LEER + ['kosten' => 0] as $s => $_) { $gruppen[$g][$schl][$s] += (int) $z[$s]; }
            }
        }
        foreach ($gruppen as $g => $l) { uasort($l, static fn($x, $y) => [$y['leads'], $y['umsatz'], $y['klicks']] <=> [$x['leads'], $x['umsatz'], $x['klicks']]); $gruppen[$g] = $l; }
        return ['kampagnen' => $kamp, 'summe' => $summe, 'gruppen' => $gruppen];
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
