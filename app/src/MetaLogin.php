<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/MetaSeite.php';

/* ==========================================================================
   MetaLogin.php — „Mit Meta verbinden“ (01.10.2026, Uwe: „mach soweit
   automatisch wie du kannst“).

   Statt im Graph API Explorer einen Schlüssel zu erzeugen, zu verlängern und
   abzuschreiben: ein Knopf in der Verwaltung. Meta fragt Uwe, welche Seite
   und welches Instagram-Konto die App nutzen darf; der Server tauscht den
   Rückruf-Code gegen einen langen Nutzer-Schlüssel, trägt Seite, Instagram
   und WhatsApp selbst ein (MetaSeite::selbstEinrichten) und legt danach den
   Seiten-Schlüssel ab — der läuft nicht ab. Kein Schlüssel wird angezeigt.

   Nötig sind nur App-ID (nicht geheim, vorbelegt mit „Vecom Design
   Marketing“) und App-Geheimnis (dasselbe wie bei WhatsApp — wird
   mitbenutzt, wenn dort schon hinterlegt). In der Meta-App muss die
   Rückruf-Adresse unter „Gültige OAuth-Redirect-URIs“ stehen.
   ========================================================================== */
final class MetaLogin
{
    public const VERSION = 'v21.0';
    public const APP_VORGABE = '1096956489415912';   // „Vecom Design Marketing“ (öffentliche App-ID)
    public const RECHTE = [
        'pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'pages_manage_metadata',
        'leads_retrieval', 'business_management',
        'instagram_basic', 'instagram_content_publish', 'instagram_manage_comments', 'instagram_manage_messages',
        'whatsapp_business_management', 'whatsapp_business_messaging',
    ];

    /** Test-Haken: fn(string $url): array{status:int, json:?array} */
    public static $netz = null;

    public static function einstellungen(): array
    {
        return [
            'app_id' => AkquiseGate::einstellung('meta_app_id', self::APP_VORGABE),
            'config_id' => AkquiseGate::einstellung('meta_login_config', ''),
            'geheim' => self::geheim() !== '',
            'rueckruf' => self::rueckrufAdresse(),
        ];
    }

    public static function rueckrufAdresse(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis() . '/meta-rueckruf';
    }

    /** App-ID, Konfiguration (Login for Business) und optional App-Geheimnis. @return ?string Fehler */
    public static function speichern(array $d): ?string
    {
        $app = preg_replace('~\D~', '', (string) ($d['app_id'] ?? '')) ?? '';
        if ($app !== '') { AkquiseGate::setzen('meta_app_id', mb_substr($app, 0, 30)); }
        if (array_key_exists('config_id', $d)) { AkquiseGate::setzen('meta_login_config', mb_substr(preg_replace('~\D~', '', (string) $d['config_id']) ?? '', 0, 30)); }
        $g = trim((string) ($d['app_geheim'] ?? ''));
        if ($g !== '') {
            if (!preg_match('~^[a-f0-9]{16,64}$~i', $g)) { return 'Das App-Geheimnis sieht nicht richtig aus (32 Zeichen aus 0–9 und a–f).'; }
            require_once __DIR__ . '/Hosting.php';
            $blob = (string) Hosting::versiegeln(['wert' => $g]);
            if ($blob === '') { return 'Das App-Geheimnis ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'; }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['meta_app_geheim', $blob]);
        }
        return null;
    }

    /** Eigenes App-Geheimnis, sonst das von WhatsApp (dieselbe Meta-App). */
    private static function geheim(): string
    {
        require_once __DIR__ . '/Hosting.php';
        foreach (['meta_app_geheim', 'wa_app_geheim'] as $k) {
            $blob = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], '');
            if ($blob === '') { continue; }
            $w = (string) (Hosting::entsiegeln($blob)['wert'] ?? '');
            if ($w !== '') { return $w; }
        }
        return '';
    }

    public static function bereit(): bool
    {
        $e = self::einstellungen();
        return $e['app_id'] !== '' && $e['geheim'];
    }

    /** Wohin der Knopf führt — null, solange App-ID oder Geheimnis fehlen. */
    public static function adresse(string $zustand): ?string
    {
        if (!self::bereit()) { return null; }
        $e = self::einstellungen();
        $q = ['client_id' => $e['app_id'], 'redirect_uri' => $e['rueckruf'], 'state' => $zustand, 'response_type' => 'code'];
        /* App vom Typ Business mit „Facebook Login for Business“: die Rechte stehen in der Konfiguration. */
        if ($e['config_id'] !== '') { $q['config_id'] = $e['config_id']; } else { $q['scope'] = implode(',', self::RECHTE); }
        return 'https://www.facebook.com/' . self::VERSION . '/dialog/oauth?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{status:int, json:?array} */
    private static function holen(string $url): array
    {
        if (self::$netz) { return (self::$netz)($url); }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'json' => is_string($roh) ? (json_decode($roh, true) ?: null) : null];
    }

    private static function fehler(array $r): string
    {
        return mb_substr((string) ($r['json']['error']['message'] ?? ('HTTP ' . $r['status'])), 0, 200);
    }

    /**
     * Code → kurzer Nutzer-Schlüssel → langer Nutzer-Schlüssel → Einrichten
     * → Seiten-Schlüssel ablegen. Gibt nur Sätze ohne Schlüssel zurück.
     * @return array{ok:bool, text:string}
     */
    public static function rueckruf(string $code): array
    {
        if ($code === '' || !self::bereit()) { return ['ok' => false, 'text' => 'Meta hat keinen Code geschickt oder App-ID/App-Geheimnis fehlen.']; }
        $e = self::einstellungen();
        $basis = 'https://graph.facebook.com/' . self::VERSION . '/oauth/access_token?';
        $kurz = self::holen($basis . http_build_query(['client_id' => $e['app_id'], 'redirect_uri' => $e['rueckruf'], 'client_secret' => self::geheim(), 'code' => $code], '', '&', PHP_QUERY_RFC3986));
        $kTok = (string) ($kurz['json']['access_token'] ?? '');
        if ($kTok === '') { return ['ok' => false, 'text' => 'Meta hat den Code nicht angenommen: ' . self::fehler($kurz)]; }
        $lang = self::holen($basis . http_build_query(['grant_type' => 'fb_exchange_token', 'client_id' => $e['app_id'], 'client_secret' => self::geheim(), 'fb_exchange_token' => $kTok], '', '&', PHP_QUERY_RFC3986));
        $nutzer = (string) ($lang['json']['access_token'] ?? '') ?: $kTok;

        /* Mit dem Nutzer-Schlüssel einrichten: Seite, Instagram, WhatsApp. */
        MetaSeite::speichern(['token' => $nutzer]);
        $se = MetaSeite::selbstEinrichten();
        $seite = MetaSeite::einstellungen()['seite_id'];
        /* Danach den Seiten-Schlüssel ablegen — aus einem langen Nutzer-Schlüssel läuft er nicht ab. */
        $seitenTok = $seite !== '' ? MetaSeite::seitenSchluessel($nutzer, $seite) : '';
        if ($seitenTok !== '') { MetaSeite::speichern(['token' => $seitenTok]); }
        require_once __DIR__ . '/MkKanaele.php';
        $p = MkKanaele::pruefen('facebook');
        $text = implode(' · ', $se['zeilen']);
        return ['ok' => $se['ok'] && $p['ok'], 'text' => 'Mit Meta verbunden. ' . ($text !== '' ? $text . '.' : $p['text'])];
    }
}
