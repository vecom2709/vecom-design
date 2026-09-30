<?php
declare(strict_types=1);

require_once __DIR__ . '/Partner.php';

/**
 * Partner-Tracking (30.09.2026, Uwe: „Alles“).
 *
 * WAS AUFGEZEICHNET WIRD: nur Besuche, die über einen Partnerlink begannen
 * (/p/CODE) — und seit dem 30.09.2026 (Growth Engine Phase 3) über einen
 * eigenen Kampagnenlink (/k/CODE, k.php) —, und mit Einwilligung ihre
 * späteren Wiederkehrer. Ein Besuch trägt dann partner_id ODER kampagne_id
 * (selten beides, wenn ein Partnerkunde über eine Kampagne wiederkommt). Alle anderen
 * Besucher bleiben in der anonymen Zählung (z.php) — hier entsteht kein
 * zweites Statistiksystem, sondern die Ebene zwischen „Klick“ (partner_klicks)
 * und „Kunde“ (partner_zuordnungen).
 *
 * WER WER IST: Ein Besuch trägt eine zufällige Besucher-ID (VIS-8F31A92C)
 * und eine Sitzung. Ohne Einwilligung gilt beides nur, bis der Browser zugeht
 * (Sitzungs-Cookie) — jeder Besuch ist dann „neu“. Mit Einwilligung merkt sich
 * der Browser Besucher-ID und Partner für spur_zuordnung_tage (einstellbar).
 * Einen Namen gibt es erst, wenn jemand freiwillig seine E-Mail einträgt;
 * dann hängt der Besuch am Kunden (verknuepfen()).
 *
 * WER DEN PARTNER BESTIMMT: immer der Server (Cookie, das p.php gesetzt hat,
 * oder partner_zuordnungen) — nie ein Wert, den der Browser mitschickt.
 * Eine Conversion ist nie ein Klick: Anfrage, Angebot, Auftrag und Zahlung
 * kommen nur aus den Stellen im Code, an denen sie wirklich entstehen.
 *
 * DATENSCHUTZ: keine IP im Klartext (nur ein täglich wechselnder Hash gegen
 * Mehrfachklicks), kein Fingerprint, keine Stadt; Land/Region lokal (Geo).
 * Rohdaten nach spur_rohdaten_tage → Tageszahlen (aufraeumen()).
 */
final class Spur
{
    public const KEKS = 'vecomspur';          // Sitzung (httponly, bis der Browser zugeht)
    public const KEKS_BESUCHER = 'vecomspurv'; // Besucher-ID, nur mit Einwilligung, befristet
    public const KEKS_WAHL = 'vecomspurok';    // die Entscheidung ja/nein (technisch notwendig)
    public const KEKS_JS = 'vdsp';             // für das Skript lesbar: „dies ist ein Partner-/Kampagnen-Besuch“ (nur 1)
    public const KEKS_KAMPAGNE = 'vecomkamp';  // Kampagne (CODE oder CODE:WERBEMITTEL), wie das Partner-Cookie

    public const EREIGNISSE = [
        'partner_visit', 'campaign_visit', 'page_view',
        'price_calculator_opened', 'price_calculator_started', 'price_calculator_completed',
        'questionnaire_opened', 'questionnaire_started', 'questionnaire_completed',
        'contact_form_opened', 'lead_created', 'offer_created', 'customer_created', 'order_created', 'payment_completed',
        'website_check_completed', 'appointment_requested',   // Growth Engine Phase 4: Website-Check und Termin
    ];
    /** Was das Skript im Browser melden darf — alles andere entsteht nur auf dem Server. */
    public const AUS_DEM_BROWSER = ['page_view', 'price_calculator_opened', 'contact_form_opened'];
    /** Nur einmal je Besuch (bzw. je Kunde bei Ereignissen ohne Besuch). */
    public const EINMALIG = ['price_calculator_opened', 'price_calculator_started', 'price_calculator_completed', 'questionnaire_opened',
        'questionnaire_started', 'questionnaire_completed', 'contact_form_opened', 'lead_created', 'customer_created',
        'website_check_completed', 'appointment_requested'];

    public const NAMEN = [
        'partner_visit' => 'Partnerlink geöffnet', 'campaign_visit' => 'Kampagnenlink geöffnet', 'page_view' => 'Seite', 'price_calculator_opened' => 'Preisrechner geöffnet',
        'price_calculator_started' => 'Preisrechner gestartet', 'price_calculator_completed' => 'Preisrechner abgeschlossen',
        'questionnaire_opened' => 'Fragebogen geöffnet', 'questionnaire_started' => 'Fragebogen gestartet',
        'questionnaire_completed' => 'Fragebogen abgeschlossen', 'contact_form_opened' => 'Kontaktformular geöffnet',
        'lead_created' => 'Anfrage / Lead erstellt', 'offer_created' => 'Angebot erstellt', 'customer_created' => 'Kunde geworden',
        'order_created' => 'Auftrag angenommen', 'payment_completed' => 'Zahlung eingegangen',
        'website_check_completed' => 'Website-Check gemacht', 'appointment_requested' => 'Termin gebucht',
    ];

    /** Status eines Besuchs, aufsteigend. */
    public const STATUS = ['besucher' => 'Besucher', 'interessent' => 'Interessent', 'rechner' => 'Preisrechner abgeschlossen',
        'anfrage' => 'Anfrage', 'angebot' => 'Angebot', 'kunde' => 'Kunde', 'abgeschlossen' => 'Abgeschlossen'];
    private const STATUS_AUS = [
        'price_calculator_opened' => 'interessent', 'price_calculator_started' => 'interessent', 'questionnaire_opened' => 'interessent',
        'contact_form_opened' => 'interessent', 'price_calculator_completed' => 'rechner', 'questionnaire_started' => 'rechner',
        'questionnaire_completed' => 'anfrage', 'lead_created' => 'anfrage', 'offer_created' => 'angebot',
        'customer_created' => 'kunde', 'order_created' => 'kunde', 'payment_completed' => 'abgeschlossen',
        'website_check_completed' => 'interessent', 'appointment_requested' => 'anfrage',
    ];

    public const QUELLEN = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram', 'google' => 'Google', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'threads' => 'Threads',
        'x' => 'X', 'email' => 'E-Mail', 'sms' => 'SMS',
        'qr' => 'QR-Code', 'visitenkarte' => 'Partner-Visitenkarte', 'flyer' => 'Flyer (QR)', 'wiederkehr' => 'Wiederkehr (gemerkt)',
        'andere' => 'Andere Website', 'direkt' => 'Direktlink'];

    public const STANDARD = ['spur_an' => '1', 'spur_zuordnung_tage' => '30', 'spur_rohdaten_tage' => '90', 'spur_frage_an' => '1',
        'spur_geo_an' => '1', 'spur_klick_grenze' => '5'];

    private static ?array $besuch = null;
    private static bool $gesucht = false;

    /* ================================================================== */
    /*  Einstellungen                                                     */
    /* ================================================================== */

    public static function einstellung(string $k): string
    {
        try { $v = Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], null); } catch (Throwable $e) { $v = null; }
        return $v === null || $v === '' ? (self::STANDARD[$k] ?? '') : (string) $v;
    }

    public static function an(): bool { return self::einstellung('spur_an') === '1'; }
    public static function zuordnungTage(): int { return max(1, min(365, (int) self::einstellung('spur_zuordnung_tage'))); }
    public static function rohdatenTage(): int { return max(7, min(730, (int) self::einstellung('spur_rohdaten_tage'))); }

    /** @return ?string Fehler */
    public static function einstellungenSetzen(array $d): ?string
    {
        $tage = (int) ($d['spur_zuordnung_tage'] ?? 0);
        $roh = (int) ($d['spur_rohdaten_tage'] ?? 0);
        $grenze = (int) ($d['spur_klick_grenze'] ?? 0);
        if ($tage < 1 || $tage > 365) { return 'Partnerzuordnung zwischen 1 und 365 Tagen.'; }
        if ($roh < 7 || $roh > 730) { return 'Aufbewahrung der Einzeldaten zwischen 7 und 730 Tagen.'; }
        if ($grenze < 2 || $grenze > 100) { return 'Grenze für Mehrfachklicks zwischen 2 und 100 je Stunde.'; }
        $neu = ['spur_an' => !empty($d['spur_an']) ? '1' : '0', 'spur_zuordnung_tage' => (string) $tage, 'spur_rohdaten_tage' => (string) $roh,
                'spur_frage_an' => !empty($d['spur_frage_an']) ? '1' : '0', 'spur_geo_an' => !empty($d['spur_geo_an']) ? '1' : '0',
                'spur_klick_grenze' => (string) $grenze];
        $vorher = [];
        foreach ($neu as $k => $v) {
            $vorher[$k] = self::einstellung($k);
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
        Events::pruefspur('spur_einstellungen', 'settings', null, $vorher, $neu);
        return null;
    }

    /* ================================================================== */
    /*  Technik: Gerät, Quelle, IP-Hash, Cookies                          */
    /* ================================================================== */

    /** @return array{geraet:string, browser:string, system:string} Nur grob, aus dem User-Agent — kein Fingerprint. */
    public static function ua(string $ua): array
    {
        $system = match (true) {
            (bool) preg_match('~iPad|Macintosh.*Mobile~i', $ua) => 'iPadOS',
            (bool) preg_match('~iPhone|iPod~i', $ua) => 'iOS',
            (bool) preg_match('~Android~i', $ua) => 'Android',
            (bool) preg_match('~Windows~i', $ua) => 'Windows',
            (bool) preg_match('~CrOS~i', $ua) => 'ChromeOS',
            (bool) preg_match('~Mac OS X|Macintosh~i', $ua) => 'macOS',
            (bool) preg_match('~Linux~i', $ua) => 'Linux',
            default => 'Andere',
        };
        $browser = match (true) {
            (bool) preg_match('~Instagram~i', $ua) => 'Instagram (App)',
            (bool) preg_match('~FBAN|FBAV|FB_IAB~i', $ua) => 'Facebook (App)',
            (bool) preg_match('~TikTok|musical_ly|BytedanceWebview~i', $ua) => 'TikTok (App)',
            (bool) preg_match('~Telegram~i', $ua) => 'Telegram (App)',
            (bool) preg_match('~Edg/|EdgA/|EdgiOS~', $ua) => 'Edge',
            (bool) preg_match('~OPR/|Opera~', $ua) => 'Opera',
            (bool) preg_match('~SamsungBrowser~', $ua) => 'Samsung Internet',
            (bool) preg_match('~Firefox/|FxiOS~', $ua) => 'Firefox',
            (bool) preg_match('~Chrome/|CriOS~', $ua) => 'Chrome',
            (bool) preg_match('~Safari/~', $ua) => 'Safari',
            default => 'Andere',
        };
        $geraet = match (true) {
            $system === 'iPadOS' || (bool) preg_match('~Tablet|Android(?!.*Mobile)~i', $ua) => 'tablet',
            (bool) preg_match('~Mobile|iPhone|iPod|Android~i', $ua) => 'smartphone',
            default => 'desktop',
        };
        return ['geraet' => $geraet, 'browser' => $browser, 'system' => $system];
    }

    public static function geraetName(string $g): string
    {
        return ['smartphone' => 'Smartphone', 'tablet' => 'Tablet', 'desktop' => 'Desktop'][$g] ?? '—';
    }

    /** Die Quelle eines Besuchs: UTM vor Kanal vor Herkunfts-Domain. */
    public static function quelle(string $utmSource, ?string $kanal, string $refHost): string
    {
        $abbild = static function (string $w): ?string {
            $w = mb_strtolower(trim($w));
            if ($w === '') { return null; }
            foreach (['facebook' => '~^(fb|facebook|meta)|facebook\.|fb\.me|fbclid~', 'instagram' => '~^(ig|insta)|instagram~', 'tiktok' => '~tiktok~',
                      'whatsapp' => '~^wa$|whatsapp|wa\.me~', 'telegram' => '~^tg$|telegram|t\.me~', 'google' => '~google|^gclid~', 'linkedin' => '~linkedin|lnkd~',
                      'youtube' => '~youtube|youtu\.be~', 'pinterest' => '~pinterest|^pin\.it$~', 'threads' => '~^threads$|threads\.(net|com)~',
                      'x' => '~^x$|^(twitter|t\.co|x\.com)$~', 'email' => '~^(e-?mail|mail|newsletter)$~', 'sms' => '~^sms$~', 'qr' => '~^qr~',
                      'visitenkarte' => '~^(karte|visitenkarte|biglietto)$~', 'flyer' => '~^(flyer|volantino)$~'] as $q => $re) {
                if (preg_match($re, $w)) { return $q; }
            }
            return null;
        };
        return $abbild($utmSource) ?? $abbild((string) $kanal) ?? $abbild($refHost) ?? ($refHost !== '' ? 'andere' : 'direkt');
    }

    public static function quelleName(string $q): string { return self::QUELLEN[$q] ?? ucfirst($q); }

    /** Täglich wechselnder Hash — reicht gegen Mehrfachklicks, taugt nicht zum Wiedererkennen über Tage. */
    public static function ipHash(string $ip): string
    {
        return $ip === '' ? '' : substr(hash('sha256', $ip . '|' . date('Y-m-d') . '|' . Config::get('app_geheim', 'vecom') . '|spur'), 0, 16);
    }

    private static function sicher(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    }

    private static function keks(string $name, string $wert, int $sekunden = 0, bool $nurServer = true): void
    {
        if (headers_sent()) { if ($sekunden < 0) { unset($_COOKIE[$name]); } else { $_COOKIE[$name] = $wert; } return; }
        @setcookie($name, $wert, ['expires' => $sekunden > 0 ? time() + $sekunden : ($sekunden < 0 ? time() - 3600 : 0), 'path' => '/',
            'secure' => self::sicher(), 'httponly' => $nurServer, 'samesite' => 'Lax']);
        if ($sekunden < 0) { unset($_COOKIE[$name]); } else { $_COOKIE[$name] = $wert; }
    }

    private static function neueId(string $vor, int $laenge): string
    {
        return $vor . strtoupper(bin2hex(random_bytes(intdiv($laenge, 2))));
    }

    /** Hat dieser Browser zugestimmt, sich Partner und Besucher-ID zu merken? */
    public static function eingewilligt(): bool
    {
        return (string) ($_COOKIE[self::KEKS_WAHL] ?? '') === '1';
    }

    /* ================================================================== */
    /*  Besuch                                                            */
    /* ================================================================== */

    /** Der laufende Besuch aus dem Sitzungs-Cookie — oder null. */
    public static function aktuellerBesuch(): ?array
    {
        if (self::$gesucht) { return self::$besuch; }
        self::$gesucht = true;
        /* In der Verwaltung nie: Uwe testet seine Partnerlinks im selben Browser --
           sonst hinge eine Anfrage, die er für jemand anderen anlegt, an SEINEM Testbesuch. */
        if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/app/')) { return self::$besuch = null; }
        $sid = (string) ($_COOKIE[self::KEKS] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $sid)) { return self::$besuch = null; }
        try { self::$besuch = Db::one('SELECT * FROM spur_besuche WHERE session_id = ?', [$sid]) ?: null; } catch (Throwable $e) { self::$besuch = null; }
        return self::$besuch;
    }

    /**
     * Telegram (01.10.2026, T1): Im Webhook gibt es kein Cookie. Der Bot sagt
     * hier, welcher Besuch zu diesem Chat gehört — dann hängen sich alle
     * Ereignisse dieser Anfrage (Preisrechner in Bedarf, Lead in Anfrage …)
     * an ihn, ohne dass eine dieser Stellen Telegram kennen muss.
     */
    public static function besuchVorgeben(?array $b): void { self::$besuch = $b; self::$gesucht = true; }

    /**
     * Ein Bot-Start über einen Kampagnenlink (t.me/BOT?start=m_CODE): derselbe
     * Besuch wie ein Klick auf /k/CODE, nur ohne Browser — kein Cookie, keine
     * IP, kein Gerät, keine Region. Quelle „telegram“. Gemerkt wird er nicht
     * im Browser, sondern am Chat (telegram_chats.spur_besuch_id), und nur so
     * lange, wie der Chat besteht.
     *
     * @param array{id:int,code:string} $k
     * @param array{id:int,code:string}|null $cr
     */
    public static function telegramBesuch(array $k, ?array $cr, string $sprache = ''): ?array
    {
        if (!self::an()) { return null; }
        try {
            $id = Db::insert('spur_besuche', [
                'visitor_id' => self::neueId('VIS-', 8), 'session_id' => bin2hex(random_bytes(16)),
                'partner_id' => null, 'kampagne_id' => (int) $k['id'], 'creative_id' => $cr !== null ? (int) $cr['id'] : null,
                'kanal' => 'telegram', 'neu' => 1, 'einwilligung' => 0, 'einstieg' => 'telegram:start', 'aktuell' => 'telegram:start',
                'quelle' => 'telegram', 'utm_source' => 'telegram', 'utm_medium' => 'bot', 'utm_campaign' => mb_substr((string) $k['code'], 0, 80),
                'utm_content' => mb_substr((string) ($cr['code'] ?? ''), 0, 80), 'geraet' => 'telegram', 'sprache' => mb_substr($sprache, 0, 5),
            ]);
            $b = Db::one('SELECT * FROM spur_besuche WHERE id = ?', [$id]);
            if (!$b) { return null; }
            self::ereignis('campaign_visit', ['besuch' => $b, 'seite' => 'telegram:start', 'meta' => array_filter(['kanal' => 'telegram', 'werbemittel' => $cr['code'] ?? null])]);
            return $b;
        } catch (Throwable $e) { error_log('Spur::telegramBesuch: ' . $e->getMessage()); return null; }
    }

    /** Für die Kette: Zwischenspeicher leeren, wenn sich Cookies ändern. */
    public static function vergessen(): void { self::$besuch = null; self::$gesucht = false; }

    /**
     * Ein echter Klick auf einen Partnerlink (p.php). Zählt jeden Klick als
     * partner_visit; eine neue Sitzung entsteht nur, wenn dieser Browser nicht
     * schon eine für denselben Partner hat. Viele neue Sitzungen aus derselben
     * Quelle in einer Stunde → „Verdacht“: zählt nicht als Besucher.
     *
     * @param array{ua?:string, ip?:string, referrer?:string, sprache?:string, einstieg?:string, get?:array} $s
     */
    public static function partnerBesuch(array $p, ?string $kanal, array $s): ?array
    {
        if (!self::an()) { return null; }
        try {
            $vorher = self::aktuellerBesuch();
            $get = (array) ($s['get'] ?? []);
            $wiederholt = $vorher !== null && (int) $vorher['partner_id'] === (int) $p['id'];
            $besuch = $wiederholt ? $vorher : self::besuchAnlegen((int) $p['id'], null, null, $kanal, $s, $get, false);
            if ($besuch === null) { return null; }
            self::ereignis('partner_visit', ['besuch' => $besuch, 'seite' => mb_substr((string) ($s['einstieg'] ?? '/p/' . $p['code']), 0, 190),
                'meta' => array_filter(['kanal' => $kanal, 'wiederholt' => $wiederholt ? 1 : null, 'verdacht' => (int) $besuch['verdacht'] === 1 ? 1 : null])]);
            /* Das Skript auf den Seiten weiß so, dass es Seitenwechsel melden darf (und ob es fragen soll). */
            self::keks(self::KEKS_JS, '1', 0, false);
            /* Mit Einwilligung: Partner und Besucher-ID bleiben für die eingestellte Dauer. */
            if (self::eingewilligt()) { self::merken($besuch, $p, $kanal); }
            return $besuch;
        } catch (Throwable $e) { error_log('Spur::partnerBesuch: ' . $e->getMessage()); return null; }
    }

    /**
     * Ein echter Klick auf einen Kampagnenlink (k.php). Wie partnerBesuch:
     * jeder Klick ist ein campaign_visit; eine neue Sitzung nur, wenn dieser
     * Browser nicht schon eine für dieselbe Kampagne und dasselbe Werbemittel hat.
     *
     * @param array{id:int,code:string,plattform:string} $k
     * @param array{id:int,code:string}|null $cr
     */
    public static function kampagnenBesuch(array $k, ?array $cr, array $s): ?array
    {
        if (!self::an()) { return null; }
        try {
            $vorher = self::aktuellerBesuch();
            $wiederholt = $vorher !== null && (int) ($vorher['kampagne_id'] ?? 0) === (int) $k['id'] && (int) ($vorher['creative_id'] ?? 0) === (int) ($cr['id'] ?? 0);
            $besuch = $wiederholt ? $vorher : self::besuchAnlegen(null, (int) $k['id'], $cr !== null ? (int) $cr['id'] : null, null, $s, (array) ($s['get'] ?? []), false);
            if ($besuch === null) { return null; }
            self::ereignis('campaign_visit', ['besuch' => $besuch, 'seite' => mb_substr((string) ($s['einstieg'] ?? '/k/' . $k['code']), 0, 190),
                'meta' => array_filter(['werbemittel' => $cr['code'] ?? null, 'wiederholt' => $wiederholt ? 1 : null, 'verdacht' => (int) $besuch['verdacht'] === 1 ? 1 : null])]);
            self::keks(self::KEKS_JS, '1', 0, false);
            /* Ohne Einwilligung nur für diesen Besuch; mit Einwilligung wie beim Partner befristet. */
            self::keks(self::KEKS_KAMPAGNE, (string) $k['code'] . ($cr !== null ? ':' . $cr['code'] : ''), self::eingewilligt() ? self::zuordnungTage() * 86400 : 0, true);
            if (self::eingewilligt()) { self::merken($besuch); }
            return $besuch;
        } catch (Throwable $e) { error_log('Spur::kampagnenBesuch: ' . $e->getMessage()); return null; }
    }

    private static function besuchAnlegen(?int $partnerId, ?int $kampagneId, ?int $creativeId, ?string $kanal, array $s, array $get, bool $wiederkehr): ?array
    {
        $ua = (string) ($s['ua'] ?? '');
        if (Partner::istRoboter($ua)) { return null; }
        $ip = (string) ($s['ip'] ?? '');
        $ipHash = self::ipHash($ip);
        $u = self::ua($ua);
        $refHost = mb_strtolower((string) (parse_url((string) ($s['referrer'] ?? ''), PHP_URL_HOST) ?? ''));
        $eigene = mb_strtolower((string) (parse_url((string) Config::get('website', 'https://vecom-design.it'), PHP_URL_HOST) ?? ''));
        if ($refHost !== '' && ($refHost === $eigene || str_ends_with($refHost, '.' . ltrim(preg_replace('~^www\.~', '', $eigene) ?? '', '.')) || $refHost === preg_replace('~^www\.~', '', $eigene))) { $refHost = ''; }
        $utm = static fn(string $k, int $max): string => mb_substr(preg_replace('~[^\p{L}\p{N} ._\-/+]~u', '', (string) ($get[$k] ?? '')) ?? '', 0, $max);
        $geo = ['land' => '', 'region' => ''];
        if (self::einstellung('spur_geo_an') === '1' && $ip !== '') {
            try { require_once __DIR__ . '/Geo.php'; $geo = Geo::suchen($ip); } catch (Throwable $e) { }
        }
        /* Besucher-ID: mit Einwilligung die gemerkte, sonst eine neue für diese Sitzung. */
        $vid = (string) ($_COOKIE[self::KEKS_BESUCHER] ?? '');
        $neu = 1;
        if (self::eingewilligt() && preg_match('/^VIS-[A-F0-9]{8}$/', $vid)) {
            $neu = (int) Db::wert('SELECT COUNT(*) FROM spur_besuche WHERE visitor_id = ?', [$vid], 0) > 0 ? 0 : 1;
        } else { $vid = self::neueId('VIS-', 8); }
        /* Mehrfachklicks: viele neue Sitzungen aus derselben Quelle für denselben Partner (bzw. dieselbe Kampagne) in einer Stunde. */
        $verdacht = 0;
        if ($ipHash !== '') {
            $n = (int) Db::wert('SELECT COUNT(*) FROM spur_besuche WHERE ip_hash = ? AND ' . ($partnerId !== null ? 'partner_id' : 'kampagne_id') . ' = ? AND start_am >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [$ipHash, $partnerId ?? (int) $kampagneId], 0);
            $verdacht = $n >= (int) self::einstellung('spur_klick_grenze') ? 1 : 0;
        }
        $sid = bin2hex(random_bytes(16));
        $einstieg = mb_substr((string) ($s['einstieg'] ?? ''), 0, 190);
        $quelle = $wiederkehr ? 'wiederkehr' : self::quelle((string) ($get['utm_source'] ?? ''), $kanal, $refHost);
        $id = Db::insert('spur_besuche', [
            'visitor_id' => $vid, 'session_id' => $sid, 'partner_id' => $partnerId, 'kampagne_id' => $kampagneId, 'creative_id' => $creativeId,
            'kanal' => $kanal !== null ? Partner::kanal($kanal) : null,
            'neu' => $neu, 'einwilligung' => self::eingewilligt() ? 1 : 0, 'einstieg' => $einstieg, 'aktuell' => $einstieg,
            'ref_link' => mb_substr((string) ($s['ref_link'] ?? ''), 0, 190), 'referrer' => mb_substr($refHost, 0, 120), 'quelle' => $quelle,
            'utm_source' => $utm('utm_source', 60), 'utm_medium' => $utm('utm_medium', 60), 'utm_campaign' => $utm('utm_campaign', 80), 'utm_content' => $utm('utm_content', 80),
            'geraet' => $u['geraet'], 'browser' => $u['browser'], 'system' => $u['system'],
            'sprache' => mb_substr((string) ($s['sprache'] ?? ''), 0, 5), 'land' => $geo['land'], 'region' => mb_substr($geo['region'], 0, 80),
            'verdacht' => $verdacht, 'ip_hash' => $ipHash,
        ]);
        self::keks(self::KEKS, $sid, 0, true);
        self::vergessen();
        return self::$besuch = Db::one('SELECT * FROM spur_besuche WHERE id = ?', [$id]);
    }

    /**
     * Kein Besuch in dieser Sitzung, aber mit Einwilligung gemerkter Partner:
     * Der Besucher kommt Tage später ohne Link wieder — neuer Besuch, derselbe
     * Besucher, Quelle „Wiederkehr“. Ohne Einwilligung: nichts.
     */
    public static function wiederkehr(array $s): ?array
    {
        if (!self::an() || !self::eingewilligt() || self::aktuellerBesuch() !== null) { return self::aktuellerBesuch(); }
        [$c, $kanal] = Partner::teilen((string) ($_COOKIE[Partner::KEKS] ?? ''));
        $p = $c !== '' ? Partner::ausCode($c) : null;
        try {
            if ($p !== null) {
                $b = self::besuchAnlegen((int) $p['id'], null, null, $kanal, $s, (array) ($s['get'] ?? []), true);
            } else {
                /* Kein Partner gemerkt, aber eine Kampagne (mit Einwilligung). */
                [$kc, $crc] = array_pad(explode(':', (string) ($_COOKIE[self::KEKS_KAMPAGNE] ?? ''), 2), 2, '');
                $k = preg_match('/^[a-z0-9][a-z0-9-]{2,23}$/', $kc) ? Db::one("SELECT id FROM mk_kampagnen WHERE code = ? AND status = 'aktiv'", [$kc]) : null;
                if (!$k) { return null; }
                $cr = $crc !== '' ? Db::one('SELECT id FROM mk_creatives WHERE kampagne_id = ? AND code = ?', [(int) $k['id'], $crc]) : null;
                $b = self::besuchAnlegen(null, (int) $k['id'], $cr ? (int) $cr['id'] : null, null, $s, (array) ($s['get'] ?? []), true);
            }
            if ($b) { self::keks(self::KEKS_JS, '1', 0, false); }
            return $b;
        } catch (Throwable $e) { return null; }
    }

    /** Einwilligung merken: Partner-Cookie und Besucher-ID bleiben für die eingestellte Dauer. */
    private static function merken(array $besuch, ?array $p = null, ?string $kanal = null): void
    {
        $dauer = self::zuordnungTage() * 86400;
        self::keks(self::KEKS_BESUCHER, (string) $besuch['visitor_id'], $dauer, true);
        self::keks(self::KEKS_JS, '1', $dauer, false);   // damit das Skript beim Wiederkommen meldet
        if ($p === null && !empty($besuch['partner_id'])) { $p = Partner::laden((int) $besuch['partner_id']); $kanal = $besuch['kanal'] ?? null; }
        if ($p) { self::keks(Partner::KEKS, (string) $p['code'] . ($kanal ? ':' . $kanal : ''), $dauer, true); }
        if (!empty($besuch['kampagne_id'])) {
            try {
                $k = Db::one('SELECT k.code, c.code AS werbemittel FROM mk_kampagnen k LEFT JOIN mk_creatives c ON c.id = ? WHERE k.id = ?', [(int) ($besuch['creative_id'] ?? 0), (int) $besuch['kampagne_id']]);
                if ($k) { self::keks(self::KEKS_KAMPAGNE, (string) $k['code'] . (!empty($k['werbemittel']) ? ':' . $k['werbemittel'] : ''), $dauer, true); }
            } catch (Throwable $e) { }
        }
    }

    /** Die Antwort auf die Frage im Fenster (t.php). */
    public static function einwilligen(bool $ja): void
    {
        self::keks(self::KEKS_WAHL, $ja ? '1' : '0', 180 * 86400, true);
        $b = self::aktuellerBesuch();
        if ($ja && $b) {
            Db::run('UPDATE spur_besuche SET einwilligung = 1, einwilligung_am = NOW() WHERE id = ?', [(int) $b['id']]);
            self::merken($b);
        }
        if (!$ja) {
            self::keks(self::KEKS_BESUCHER, '', -1);
            if ($b) { Db::run('UPDATE spur_besuche SET einwilligung = 0 WHERE id = ?', [(int) $b['id']]); }
            // Das Partner-Cookie wird wieder zum Sitzungs-Cookie (wie vor der Frage).
            if (isset($_COOKIE[Partner::KEKS])) { self::keks(Partner::KEKS, (string) $_COOKIE[Partner::KEKS], 0, true); }
            if (isset($_COOKIE[self::KEKS_KAMPAGNE])) { self::keks(self::KEKS_KAMPAGNE, (string) $_COOKIE[self::KEKS_KAMPAGNE], 0, true); }
            self::keks(self::KEKS_JS, '1', 0, false);
        }
    }

    /** Widerruf (Link in der Datenschutzerklärung): alles Gemerkte weg. */
    public static function widerrufen(): void
    {
        $b = self::aktuellerBesuch();
        if ($b) { Db::run('UPDATE spur_besuche SET einwilligung = 0 WHERE id = ?', [(int) $b['id']]); }
        foreach ([self::KEKS_BESUCHER, Partner::KEKS, self::KEKS_KAMPAGNE, self::KEKS_JS, self::KEKS] as $k) { self::keks($k, '', -1); }
        self::keks(self::KEKS_WAHL, '0', 180 * 86400, true);
    }

    /** Soll das Fenster fragen? Nur in einem Partner- oder Kampagnen-Besuch, nur wenn noch nicht entschieden. */
    public static function sollFragen(): bool
    {
        return self::an() && self::einstellung('spur_frage_an') === '1' && self::aktuellerBesuch() !== null && !isset($_COOKIE[self::KEKS_WAHL]);
    }

    /* ================================================================== */
    /*  Ereignisse — die eine zentrale Stelle                             */
    /* ================================================================== */

    /**
     * Ein Ereignis festhalten. Wirft nie.
     *
     * @param array{besuch?:array, customer_id?:?int, seite?:string, meta?:array, betrag_cents?:?int, anfrage_id?:?int} $o
     *   Ohne 'besuch' gilt der laufende Besuch (Cookie); ohne den der letzte
     *   Besuch des Kunden; ohne den der zugeordnete Partner des Kunden
     *   (Ereignis ohne Besuch). Ohne Partner: nichts.
     */
    public static function ereignis(string $typ, array $o = []): void
    {
        if (!in_array($typ, self::EREIGNISSE, true)) { return; }
        try {
            if (!self::an()) { return; }
            $kunde = isset($o['customer_id']) && (int) $o['customer_id'] > 0 ? (int) $o['customer_id'] : null;
            $b = $o['besuch'] ?? self::aktuellerBesuch();
            /* Der Besuch gehört schon einem anderen Kunden: dann zählt der Kunde, nicht das Cookie. */
            if ($b !== null && $kunde !== null && !empty($b['customer_id']) && (int) $b['customer_id'] !== $kunde) { $b = null; }
            if ($b === null && $kunde !== null) {
                $b = Db::one('SELECT * FROM spur_besuche WHERE customer_id = ? ORDER BY zuletzt_am DESC LIMIT 1', [$kunde]) ?: null;
            }
            /* Partner: aus dem Besuch; hat der keinen (Kampagnen-Besuch), der dem Kunden zugeordnete Partner —
               so bleibt ein Partnerkunde im Partner-Tracking, auch wenn er später über eine Kampagne wiederkommt. */
            $partner = $b !== null && !empty($b['partner_id']) ? (int) $b['partner_id']
                : ($kunde !== null ? (int) Db::wert('SELECT partner_id FROM partner_zuordnungen WHERE customer_id = ?', [$kunde], 0) : 0);
            $kampagne = $b !== null && !empty($b['kampagne_id']) ? (int) $b['kampagne_id'] : null;
            if ($partner <= 0 && $kampagne === null) { return; }
            if ($b !== null && $kunde !== null && empty($b['customer_id'])) { self::verknuepfen($kunde, isset($o['anfrage_id']) ? (int) $o['anfrage_id'] : null, $b); }
            if (in_array($typ, self::EINMALIG, true)) {
                $schon = $b !== null
                    ? (int) Db::wert('SELECT COUNT(*) FROM spur_ereignisse WHERE besuch_id = ? AND event_type = ?', [(int) $b['id'], $typ], 0)
                    : (int) Db::wert('SELECT COUNT(*) FROM spur_ereignisse WHERE customer_id = ? AND partner_id = ? AND event_type = ?', [$kunde, $partner, $typ], 0);
                /* Lead/Kunde je Kunde nur einmal — auch über mehrere Besuche. */
                if ($schon === 0 && $kunde !== null && in_array($typ, ['lead_created', 'customer_created'], true)) {
                    $schon = (int) Db::wert('SELECT COUNT(*) FROM spur_ereignisse WHERE customer_id = ? AND event_type = ?', [$kunde, $typ], 0);
                }
                if ($schon > 0) { return; }
            }
            $meta = $o['meta'] ?? [];
            Db::insert('spur_ereignisse', [
                'besuch_id' => $b['id'] ?? null, 'visitor_id' => (string) ($b['visitor_id'] ?? ''), 'session_id' => (string) ($b['session_id'] ?? ''),
                'partner_id' => $partner > 0 ? $partner : null, 'kampagne_id' => $kampagne,
                'creative_id' => $kampagne !== null && !empty($b['creative_id']) ? (int) $b['creative_id'] : null, 'event_type' => $typ, 'seite' => mb_substr((string) ($o['seite'] ?? ''), 0, 190),
                'meta' => $meta ? mb_substr((string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500) : '',
                'customer_id' => $kunde ?? ($b['customer_id'] ?? null), 'betrag_cents' => isset($o['betrag_cents']) ? (int) $o['betrag_cents'] : null,
            ]);
            if ($b !== null) {
                $status = self::STATUS_AUS[$typ] ?? null;
                $rang = array_flip(array_keys(self::STATUS));
                $setz = ['zuletzt_am = NOW()'];
                $werte = [];
                if ($status !== null && ($rang[$status] ?? 0) > ($rang[$b['status']] ?? 0)) { $setz[] = 'status = ?'; $werte[] = $status; }
                if ($typ === 'page_view' && ($o['seite'] ?? '') !== '') { $setz[] = 'aktuell = ?'; $setz[] = 'seiten = seiten + 1'; $werte[] = mb_substr((string) $o['seite'], 0, 190); }
                $werte[] = (int) $b['id'];
                Db::run('UPDATE spur_besuche SET ' . implode(', ', $setz) . ' WHERE id = ?', $werte);
            }
        } catch (Throwable $e) { error_log('Spur::ereignis(' . $typ . '): ' . $e->getMessage()); }
    }

    /** Besuch ↔ Kunde, sobald jemand freiwillig seine Daten eingetragen hat. Nur wenn der Besuch noch keinem Kunden gehört. */
    public static function verknuepfen(int $kundeId, ?int $anfrageId = null, ?array $b = null): void
    {
        try {
            $b ??= self::aktuellerBesuch();
            if ($b === null || $kundeId <= 0) { return; }
            Db::run('UPDATE spur_besuche SET customer_id = ?, anfrage_id = COALESCE(anfrage_id, ?) WHERE id = ? AND customer_id IS NULL', [$kundeId, $anfrageId, (int) $b['id']]);
            /* Frühere Ereignisse dieses Besuchers ohne Kunde gleich mit (derselbe Besucher — die Kennung ist zufällig je Browser). */
            Db::run('UPDATE spur_ereignisse SET customer_id = ? WHERE customer_id IS NULL AND besuch_id IN (SELECT id FROM spur_besuche WHERE visitor_id = ?)',
                [$kundeId, (string) $b['visitor_id']]);
            if (self::$besuch !== null && (int) self::$besuch['id'] === (int) $b['id']) { self::$besuch['customer_id'] = $kundeId; }
        } catch (Throwable $e) { }
    }

    /** Lebenszeichen der offenen Seite (für „Live“): nur zuletzt_am, kein Ereignis. */
    public static function ping(): void
    {
        $b = self::aktuellerBesuch();
        if ($b) { try { Db::run('UPDATE spur_besuche SET zuletzt_am = NOW() WHERE id = ?', [(int) $b['id']]); } catch (Throwable $e) { } }
    }

    /** Eine Seitenadresse säubern: nur Pfad, ohne Schlüssel (?t=…) und ohne Fragment. */
    public static function pfad(string $roh): string
    {
        $p = (string) (parse_url($roh, PHP_URL_PATH) ?? '/');
        $p = preg_replace('~[^A-Za-z0-9/._\-]~', '', $p) ?? '/';
        return mb_substr($p !== '' ? $p : '/', 0, 190);
    }

    /* ================================================================== */
    /*  Aufräumen                                                         */
    /* ================================================================== */

    /**
     * Einzeldaten älter als spur_rohdaten_tage → Tageszahlen je Partner und
     * Ereignis, dann löschen. Ganze Tage, damit sich Roh- und Tageszahlen nie
     * überschneiden. Gibt die Zahl gelöschter Ereignisse zurück.
     */
    public static function aufraeumen(): int
    {
        $grenze = date('Y-m-d', strtotime('-' . self::rohdatenTage() . ' days'));
        Db::run("INSERT INTO spur_tage (partner_id, tag, event_type, anzahl, besucher, betrag_cents)
                 SELECT e.partner_id, DATE(e.created_at), e.event_type, COUNT(*), COUNT(DISTINCT NULLIF(e.visitor_id, '')), COALESCE(SUM(e.betrag_cents), 0)
                   FROM spur_ereignisse e LEFT JOIN spur_besuche b ON b.id = e.besuch_id
                  WHERE e.created_at < ? AND e.partner_id IS NOT NULL AND (b.id IS NULL OR b.verdacht = 0 OR e.event_type = 'partner_visit')
               GROUP BY e.partner_id, DATE(e.created_at), e.event_type
                 ON DUPLICATE KEY UPDATE anzahl = anzahl + VALUES(anzahl), besucher = besucher + VALUES(besucher), betrag_cents = betrag_cents + VALUES(betrag_cents)", [$grenze]);
        /* Dasselbe je Kampagne und Werbemittel (Growth Engine). */
        Db::run("INSERT INTO mk_tage (kampagne_id, creative_id, tag, event_type, anzahl, besucher, betrag_cents)
                 SELECT e.kampagne_id, COALESCE(e.creative_id, 0), DATE(e.created_at), e.event_type, COUNT(*), COUNT(DISTINCT NULLIF(e.visitor_id, '')), COALESCE(SUM(e.betrag_cents), 0)
                   FROM spur_ereignisse e LEFT JOIN spur_besuche b ON b.id = e.besuch_id
                  WHERE e.created_at < ? AND e.kampagne_id IS NOT NULL AND (b.id IS NULL OR b.verdacht = 0 OR e.event_type = 'campaign_visit')
               GROUP BY e.kampagne_id, COALESCE(e.creative_id, 0), DATE(e.created_at), e.event_type
                 ON DUPLICATE KEY UPDATE anzahl = anzahl + VALUES(anzahl), besucher = besucher + VALUES(besucher), betrag_cents = betrag_cents + VALUES(betrag_cents)", [$grenze]);
        $n = Db::run('DELETE FROM spur_ereignisse WHERE created_at < ?', [$grenze])->rowCount();
        Db::run('DELETE FROM spur_besuche WHERE zuletzt_am < ?', [$grenze]);
        return $n;
    }

    /**
     * Die anonymen Zähldateien (besuche.csv, demo.csv) wuchsen bisher ewig.
     * 400 Tage bleiben: Die Seite „Besucher“ zeigt 26 Wochen, dazu der
     * Vergleich mit dem Vorjahr. Zeilen tragen vorn das Datum (JJJJ-MM-TT).
     * @return array<string,int> gelöschte Zeilen je Datei
     */
    public static function zaehldateienKuerzen(int $tage = 400, ?string $wurzel = null): array
    {
        $wurzel ??= dirname(__DIR__, 2);
        $grenze = date('Y-m-d', strtotime('-' . max(30, $tage) . ' days'));
        $aus = [];
        foreach (['besuche.csv', 'demo.csv'] as $name) {
            $datei = $wurzel . '/' . $name;
            if (!is_file($datei) || !($fh = @fopen($datei, 'r+'))) { continue; }
            try {
                if (!flock($fh, LOCK_EX)) { continue; }
                $behalten = []; $weg = 0;
                while (($z = fgets($fh)) !== false) {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $z, $m) && $m[1] < $grenze) { $weg++; continue; }
                    $behalten[] = $z;
                }
                if ($weg > 0) { ftruncate($fh, 0); rewind($fh); fwrite($fh, implode('', $behalten)); fflush($fh); }
                $aus[$name] = $weg;
            } finally { flock($fh, LOCK_UN); fclose($fh); }
        }
        return $aus;
    }

    /* ================================================================== */
    /*  Auswertung                                                        */
    /* ================================================================== */

    public const ZEITRAEUME = ['heute' => 'Heute', 'gestern' => 'Gestern', '7' => '7 Tage', '30' => '30 Tage', 'monat' => 'Dieser Monat', 'vormonat' => 'Letzter Monat', 'frei' => 'Zeitraum'];

    /** @return array{0:string,1:string,2:string} von, bis (Datum, einschließlich), Schlüssel */
    public static function zeitraum(string $k, string $von = '', string $bis = ''): array
    {
        $heute = date('Y-m-d');
        return match ($k) {
            'heute' => [$heute, $heute, 'heute'],
            'gestern' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), 'gestern'],
            '7' => [date('Y-m-d', strtotime('-6 days')), $heute, '7'],
            'monat' => [date('Y-m-01'), $heute, 'monat'],
            'vormonat' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), 'vormonat'],
            'frei' => (static function () use ($von, $bis, $heute): array {
                $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', $von) && strtotime($von) ? $von : date('Y-m-d', strtotime('-29 days'));
                $b = preg_match('/^\d{4}-\d{2}-\d{2}$/', $bis) && strtotime($bis) ? $bis : $heute;
                return $v <= $b ? [$v, $b, 'frei'] : [$b, $v, 'frei'];
            })(),
            default => [date('Y-m-d', strtotime('-29 days')), $heute, '30'],
        };
    }

    /**
     * Filter → SQL auf spur_besuche (Alias b). Nur bekannte Felder, Werte als Parameter.
     * @return array{0:string,1:array}
     */
    public static function filterSql(array $f): array
    {
        $w = []; $a = [];
        if ((int) ($f['partner'] ?? 0) > 0) { $w[] = 'b.partner_id = ?'; $a[] = (int) $f['partner']; }
        foreach (['land' => 'b.land', 'geraet' => 'b.geraet', 'quelle' => 'b.quelle', 'status' => 'b.status'] as $k => $sp) {
            $v = (string) ($f[$k] ?? '');
            if ($v !== '' && preg_match('/^[A-Za-z_-]{1,30}$/', $v)) { $w[] = "$sp = ?"; $a[] = $v; }
        }
        return [$w ? ' AND ' . implode(' AND ', $w) : '', $a];
    }

    /** Kennzahlen oben. */
    public static function kennzahlen(string $von, string $bis, array $f = []): array
    {
        [$fw, $fa] = self::filterSql($f);
        $zeit = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $besuche = Db::one("SELECT COUNT(*) AS sitzungen, COUNT(DISTINCT b.visitor_id) AS besucher, SUM(b.neu = 1) AS neu, SUM(b.neu = 0) AS wieder, SUM(b.verdacht) AS verdacht
                              FROM spur_besuche b WHERE b.partner_id IS NOT NULL AND b.verdacht = 0 AND b.start_am BETWEEN ? AND ?$fw", array_merge($zeit, $fa)) ?: [];
        $verd = (int) Db::wert("SELECT COUNT(*) FROM spur_besuche b WHERE b.partner_id IS NOT NULL AND b.verdacht = 1 AND b.start_am BETWEEN ? AND ?$fw", array_merge($zeit, $fa), 0);
        $ev = [];
        foreach (Db::all("SELECT e.event_type, COUNT(*) AS n, COUNT(DISTINCT COALESCE(e.besuch_id, -e.customer_id)) AS eindeutig, COALESCE(SUM(e.betrag_cents), 0) AS summe
                            FROM spur_ereignisse e LEFT JOIN spur_besuche b ON b.id = e.besuch_id
                           WHERE e.created_at BETWEEN ? AND ? AND e.partner_id IS NOT NULL AND (b.id IS NULL OR b.verdacht = 0 OR e.event_type = 'partner_visit')" . self::filterEreignis($f) . "
                        GROUP BY e.event_type", array_merge($zeit, self::filterEreignisArgs($f))) as $z) {
            $ev[(string) $z['event_type']] = $z;
        }
        /* Ältere Tage stehen nur noch als Tageszahlen da. */
        foreach (self::tageszahlen($von, $bis, $f) as $t => $z) {
            $ev[$t] = ['n' => (int) ($ev[$t]['n'] ?? 0) + (int) $z['anzahl'], 'eindeutig' => (int) ($ev[$t]['eindeutig'] ?? 0) + (int) $z['anzahl'],
                       'summe' => (int) ($ev[$t]['summe'] ?? 0) + (int) $z['betrag']];
        }
        $n = static fn(string $t, string $k = 'eindeutig'): int => (int) ($ev[$t][$k] ?? 0);
        $sitzungen = (int) ($besuche['sitzungen'] ?? 0);
        $kunden = $n('customer_created');
        return [
            'klicks' => $n('partner_visit', 'n'), 'sitzungen' => $sitzungen, 'besucher' => (int) ($besuche['besucher'] ?? 0),
            'neu' => (int) ($besuche['neu'] ?? 0), 'wieder' => (int) ($besuche['wieder'] ?? 0), 'verdacht' => $verd,
            'rechner_geoeffnet' => $n('price_calculator_opened'), 'rechner_gestartet' => $n('price_calculator_started'), 'rechner_fertig' => $n('price_calculator_completed'),
            'fragebogen_geoeffnet' => $n('questionnaire_opened'), 'fragebogen' => $n('questionnaire_completed'),
            'anfragen' => $n('lead_created'), 'angebote' => $n('offer_created'), 'kunden' => $kunden, 'auftraege' => $n('order_created', 'n'),
            'zahlungen' => $n('payment_completed', 'n'), 'umsatz' => (int) ($ev['payment_completed']['summe'] ?? 0), 'auftragswert' => (int) ($ev['order_created']['summe'] ?? 0),
            'conversion' => $sitzungen > 0 ? round($kunden / $sitzungen * 100, 2) : 0.0,
        ];
    }

    private static function filterEreignis(array $f): string
    {
        $s = '';
        if ((int) ($f['partner'] ?? 0) > 0) { $s .= ' AND e.partner_id = ?'; }
        foreach (['land' => 'b.land', 'geraet' => 'b.geraet', 'quelle' => 'b.quelle', 'status' => 'b.status'] as $k => $sp) {
            $v = (string) ($f[$k] ?? '');
            if ($v !== '' && preg_match('/^[A-Za-z_-]{1,30}$/', $v)) { $s .= " AND $sp = ?"; }
        }
        return $s;
    }

    private static function filterEreignisArgs(array $f): array
    {
        $a = [];
        if ((int) ($f['partner'] ?? 0) > 0) { $a[] = (int) $f['partner']; }
        foreach (['land', 'geraet', 'quelle', 'status'] as $k) {
            $v = (string) ($f[$k] ?? '');
            if ($v !== '' && preg_match('/^[A-Za-z_-]{1,30}$/', $v)) { $a[] = $v; }
        }
        return $a;
    }

    /** Tageszahlen (nur Partner-Filter wirkt — Land/Gerät kennen sie nicht). @return array<string,array{anzahl:int,betrag:int}> */
    private static function tageszahlen(string $von, string $bis, array $f): array
    {
        $aus = [];
        $pw = (int) ($f['partner'] ?? 0) > 0 ? ' AND partner_id = ' . (int) $f['partner'] : '';
        foreach (Db::all("SELECT event_type, SUM(anzahl) AS anzahl, SUM(betrag_cents) AS betrag FROM spur_tage WHERE tag BETWEEN ? AND ?$pw GROUP BY event_type", [$von, $bis]) as $z) {
            $aus[(string) $z['event_type']] = ['anzahl' => (int) $z['anzahl'], 'betrag' => (int) $z['betrag']];
        }
        return $aus;
    }

    /** Die Partnertabelle. @return list<array<string,mixed>> */
    public static function partnerTabelle(string $von, string $bis, array $f = []): array
    {
        $zeilen = [];
        foreach (Db::all("SELECT id, name, code FROM partner WHERE status IN ('aktiv','pausiert') ORDER BY name") as $p) {
            if ((int) ($f['partner'] ?? 0) > 0 && (int) $f['partner'] !== (int) $p['id']) { continue; }
            $k = self::kennzahlen($von, $bis, ['partner' => (int) $p['id']] + $f);
            $zeilen[] = ['id' => (int) $p['id'], 'name' => (string) $p['name'], 'code' => (string) $p['code']] + $k;
        }
        usort($zeilen, static fn($a, $b) => [$b['umsatz'], $b['kunden'], $b['klicks']] <=> [$a['umsatz'], $a['kunden'], $a['klicks']]);
        return $zeilen;
    }

    /** Aktive Partner-Besucher (letzte 5 Minuten). */
    public static function live(int $minuten = 5): array
    {
        return Db::all("SELECT b.*, p.name AS partner FROM spur_besuche b JOIN partner p ON p.id = b.partner_id
                         WHERE b.zuletzt_am >= DATE_SUB(NOW(), INTERVAL ? MINUTE) ORDER BY b.zuletzt_am DESC LIMIT 50", [max(1, min(60, $minuten))]);
    }

    /** Letzte Besuche (für die Liste unter dem Dashboard). */
    public static function besuche(string $von, string $bis, array $f = [], int $anzahl = 50): array
    {
        [$fw, $fa] = self::filterSql($f);
        return Db::all("SELECT b.*, p.name AS partner FROM spur_besuche b JOIN partner p ON p.id = b.partner_id
                         WHERE b.start_am BETWEEN ? AND ?$fw ORDER BY b.start_am DESC LIMIT " . max(1, min(500, $anzahl)),
            array_merge([$von . ' 00:00:00', $bis . ' 23:59:59'], $fa));
    }

    /** Ein Besuch mit seiner Journey (nur echte Ereignisse), und was danach am Kunden geschah. */
    public static function journey(int $besuchId): ?array
    {
        $b = Db::one('SELECT b.*, p.name AS partner, p.code FROM spur_besuche b JOIN partner p ON p.id = b.partner_id WHERE b.id = ?', [$besuchId]);
        if (!$b) { return null; }
        $schritte = Db::all('SELECT * FROM spur_ereignisse WHERE besuch_id = ? ORDER BY id', [$besuchId]);
        $andere = Db::all("SELECT id, start_am, quelle, status FROM spur_besuche WHERE visitor_id = ? AND id <> ? ORDER BY start_am", [(string) $b['visitor_id'], $besuchId]);
        $danach = [];
        if (!empty($b['customer_id'])) {
            $danach = Db::all("SELECT * FROM spur_ereignisse WHERE customer_id = ? AND (besuch_id IS NULL OR besuch_id <> ?) AND event_type IN ('lead_created','offer_created','customer_created','order_created','payment_completed')
                                ORDER BY id", [(int) $b['customer_id'], $besuchId]);
        }
        return ['besuch' => $b, 'schritte' => $schritte, 'andere' => $andere, 'danach' => $danach];
    }

    /** Funnel: Partnerlink → Besucher → Preisrechner → Fragebogen → Anfrage → Angebot → Kunde → Zahlung. */
    public static function funnel(string $von, string $bis, array $f = []): array
    {
        $k = self::kennzahlen($von, $bis, $f);
        $stufen = [
            ['Partnerlink (Klicks)', $k['klicks']], ['Webseitenbesucher', $k['sitzungen']], ['Preisrechner gestartet', $k['rechner_gestartet']],
            ['Fragebogen', max($k['fragebogen'], $k['fragebogen_geoeffnet'])], ['Anfrage', $k['anfragen']], ['Angebot', $k['angebote']],
            ['Kunde', $k['kunden']], ['Zahlung', $k['zahlungen']],
        ];
        $aus = []; $vorher = null;
        foreach ($stufen as [$name, $n]) {
            $quote = $vorher === null ? 100.0 : ($vorher > 0 ? round($n / $vorher * 100, 1) : 0.0);
            $aus[] = ['name' => $name, 'n' => (int) $n, 'quote' => $quote, 'abbruch' => $vorher === null ? 0.0 : round(100 - $quote, 1),
                      'anteil' => $stufen[0][1] > 0 ? round($n / $stufen[0][1] * 100, 1) : 0.0];
            $vorher = (int) $n;
        }
        return $aus;
    }

    /** Herkunft: Top-Listen. @return array<string, list<array{wert:string,n:int}>> */
    public static function herkunft(string $von, string $bis, array $f = [], int $top = 8): array
    {
        [$fw, $fa] = self::filterSql($f);
        $aus = [];
        foreach (['land' => 'b.land', 'region' => "CONCAT(b.land, ' · ', b.region)", 'geraet' => 'b.geraet', 'browser' => 'b.browser', 'einstieg' => 'b.einstieg',
                  'kampagne' => 'b.utm_campaign', 'quelle' => 'b.quelle'] as $k => $sp) {
            $extra = $k === 'region' ? " AND b.region <> ''" : ($k === 'kampagne' ? " AND b.utm_campaign <> ''" : '');
            $aus[$k] = Db::all("SELECT $sp AS wert, COUNT(*) AS n FROM spur_besuche b WHERE b.partner_id IS NOT NULL AND b.verdacht = 0 AND b.start_am BETWEEN ? AND ?$fw$extra
                                GROUP BY wert ORDER BY n DESC LIMIT " . max(1, min(30, $top)), array_merge([$von . ' 00:00:00', $bis . ' 23:59:59'], $fa));
        }
        return $aus;
    }

    /** Heute je Partner in einem Satz: „Laura hat heute 18 Besucher gebracht …“. */
    public static function heuteSaetze(): array
    {
        $h = date('Y-m-d');
        $aus = [];
        foreach (self::partnerTabelle($h, $h) as $z) {
            if ($z['sitzungen'] === 0 && $z['klicks'] === 0) { continue; }
            $aus[] = $z;
        }
        return $aus;
    }
}
