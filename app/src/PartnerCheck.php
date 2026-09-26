<?php
declare(strict_types=1);

require_once __DIR__ . '/Domainpruefung.php';

/**
 * Website-Schnellcheck für Partner (26.09.2026).
 *
 * Der Partner tippt die Adresse eines Betriebs ein und bekommt einen
 * einseitigen Bericht zum Weiterschicken -- mit seiner Empfehlung und
 * seinem Link darunter. Sechs Punkte, die ein Laie versteht und die sich
 * eindeutig beantworten lassen (wie bei Abnahme: nichts, was Augen braucht).
 *
 * SICHERHEIT: Hier ruft unser Server eine Adresse ab, die ein Fremder
 * eingetippt hat. Also: nur http/https, Name muss auf eine öffentliche
 * Adresse zeigen, die geprüfte IP wird für den Abruf fest verdrahtet
 * (CURLOPT_RESOLVE -- sonst könnte der Name zwischen Prüfung und Abruf auf
 * 127.0.0.1 umspringen), Weiterleitungen werden einzeln nachgeprüft, Größe
 * und Zeit sind begrenzt. Ohne das wäre der Schnellcheck ein Tor ins
 * Netz des Webhosters.
 */
final class PartnerCheck
{
    public const JE_TAG = 20;
    private const ZEIT = 8;
    private const MAX_BYTE = 3 * 1024 * 1024;
    private const KENNUNG = 'Mozilla/5.0 (compatible; Vecom-Design-Schnellcheck/1.0; +https://vecom-design.it)';

    /** @var null|callable(string):array Austauschbar für die Kette. */
    public static $holer = null;
    /** @var null|callable(string):list<string> */
    public static $aufloeser = null;

    /** Aus einer Eingabe wie „trattoria-rossi.it“ eine abrufbare Adresse. */
    public static function adresse(string $roh): ?string
    {
        $roh = trim($roh);
        if ($roh === '') { return null; }
        if (!preg_match('~^https?://~i', $roh)) { $roh = 'https://' . $roh; }
        $t = parse_url($roh);
        if (!$t || empty($t['host']) || !in_array(strtolower((string) ($t['scheme'] ?? '')), ['http', 'https'], true)) { return null; }
        if (isset($t['port']) && !in_array((int) $t['port'], [80, 443], true)) { return null; }
        if (isset($t['user']) || isset($t['pass'])) { return null; }
        $host = mb_strtolower((string) $t['host']);
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) || !str_contains($host, '.')) { return null; }
        if (Domainpruefung::normalisieren($host) === null) { return null; }
        return strtolower((string) $t['scheme']) . '://' . $host . ($t['path'] ?? '/') . (isset($t['query']) ? '?' . $t['query'] : '');
    }

    /** @return list<string> öffentliche IPs des Namens, sonst leer */
    public static function oeffentlicheIps(string $host): array
    {
        $ips = self::$aufloeser ? (self::$aufloeser)($host) : array_values(array_filter((array) (@gethostbynamel($host) ?: [])));
        if (!$ips) { return []; }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return []; }
        }
        return $ips;
    }

    /** @return array{ok:bool,status:int,ms:int,inhalt:string,url:string,ssl_tage:?int,fehler:string} */
    public static function holen(string $url): array
    {
        if (self::$holer) { return (self::$holer)($url); }
        $leer = ['ok' => false, 'status' => 0, 'ms' => 0, 'inhalt' => '', 'url' => $url, 'ssl_tage' => null, 'fehler' => ''];
        $gesamt = microtime(true);
        for ($sprung = 0; $sprung <= 4; $sprung++) {
            $t = parse_url($url); $host = mb_strtolower((string) ($t['host'] ?? ''));
            $port = (int) ($t['port'] ?? (strtolower((string) ($t['scheme'] ?? '')) === 'https' ? 443 : 80));
            $ips = $host !== '' && in_array($port, [80, 443], true) ? self::oeffentlicheIps($host) : [];
            if (!$ips) { return ['fehler' => 'nicht_oeffentlich'] + $leer; }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => self::ZEIT, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => self::KENNUNG, CURLOPT_CERTINFO => true,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => static fn($r, $g, $jetzt): int => $jetzt > self::MAX_BYTE ? 1 : 0,
            ]);
            $inhalt = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $weiter = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $netz = curl_errno($ch);
            $info = (array) curl_getinfo($ch);
            curl_close($ch);
            if ($netz !== 0) {
                return ['fehler' => in_array($netz, [35, 51, 58, 60], true) ? 'zertifikat' : 'netz', 'url' => $url] + $leer;
            }
            if ($status >= 300 && $status < 400 && $weiter !== '') { $url = $weiter; continue; }
            $tage = null;
            foreach (($info['certinfo'] ?? []) as $z) {
                if (!empty($z['Expire date']) && ($ts = strtotime((string) $z['Expire date'])) !== false) { $tage = (int) floor(($ts - time()) / 86400); break; }
            }
            return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'ms' => (int) round((microtime(true) - $gesamt) * 1000),
                    'inhalt' => is_string($inhalt) ? $inhalt : '', 'url' => $url, 'ssl_tage' => $tage, 'fehler' => $status >= 400 ? 'status' : ''];
        }
        return ['fehler' => 'weiterleitungen'] + $leer;
    }

    /**
     * Die sechs Punkte. stand: gut | hinweis | schlecht; schluessel für die
     * dreisprachigen Texte in Texte::PARTNER_CHECK.
     * @return array{ok:bool, url:string, host:string, fehler:string, punkte:list<array{was:string,stand:string,wert:string}>}
     */
    public static function pruefen(string $adresse): array
    {
        $url = self::adresse($adresse);
        if ($url === null) { return ['ok' => false, 'url' => $adresse, 'host' => '', 'fehler' => 'adresse', 'punkte' => []]; }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $a = self::holen($url);
        // Ohne https versucht: Viele alte Seiten haben kein Zertifikat.
        if (!$a['ok'] && str_starts_with($url, 'https://') && in_array($a['fehler'], ['netz', 'zertifikat'], true)) {
            $b = self::holen('http://' . substr($url, 8));
            if ($b['ok']) { $b['fehler_https'] = $a['fehler']; $a = $b; }
        }
        if (!$a['ok']) {
            return ['ok' => false, 'url' => $url, 'host' => $host, 'fehler' => $a['fehler'] === 'nicht_oeffentlich' ? 'adresse' : 'erreichbar',
                    'punkte' => [['was' => 'erreichbar', 'stand' => 'schlecht', 'wert' => '']]];
        }
        $html = mb_substr($a['inhalt'], 0, 600000);
        $p = [];
        $ms = (int) $a['ms'];
        $p[] = ['was' => 'tempo', 'stand' => $ms < 1500 ? 'gut' : ($ms < 3500 ? 'hinweis' : 'schlecht'), 'wert' => number_format($ms / 1000, 1, ',', '') . ' s'];
        $https = str_starts_with((string) $a['url'], 'https://');
        $p[] = ['was' => 'sicher', 'stand' => !$https ? 'schlecht' : (($a['ssl_tage'] ?? 99) < 14 ? 'hinweis' : 'gut'),
                'wert' => $https && $a['ssl_tage'] !== null ? (string) $a['ssl_tage'] : ''];
        $viewport = (bool) preg_match('~<meta[^>]+name=["\']viewport["\']~i', $html);
        $p[] = ['was' => 'handy', 'stand' => $viewport ? 'gut' : 'schlecht', 'wert' => ''];
        $titel = preg_match('~<title[^>]*>(.*?)</title>~is', $html, $t) ? trim(html_entity_decode(strip_tags($t[1]), ENT_QUOTES, 'UTF-8')) : '';
        $besch = (bool) preg_match('~<meta[^>]+(name=["\']description["\'][^>]*content=["\'][^"\']{20,}|content=["\'][^"\']{20,}["\'][^>]*name=["\']description)~i', $html);
        $p[] = ['was' => 'google', 'stand' => ($titel !== '' && $besch) ? 'gut' : ($titel !== '' ? 'hinweis' : 'schlecht'), 'wert' => mb_substr($titel, 0, 70)];
        /* Aktualität: das jüngste Jahr neben einem ©. Alte Jahre im Fuß sind
           der häufigste Satz, den ein Kunde über seine Seite hört: „Die ist
           ja von 2016.“ Kein Jahr = keine Aussage, nur ein Hinweis. */
        $jahr = 0;
        if (preg_match_all('~(?:©|&copy;|&#169;|copyright)[^0-9<]{0,40}((?:19|20)\d\d)(?:\s*[-–]\s*((?:19|20)\d\d))?~iu', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $treffer) { $jahr = max($jahr, (int) $treffer[1], (int) ($treffer[2] ?? 0)); }
        }
        $jetzt = (int) date('Y');
        $p[] = ['was' => 'aktuell', 'stand' => $jahr === 0 ? 'hinweis' : ($jahr >= $jetzt - 1 ? 'gut' : ($jahr >= $jetzt - 3 ? 'hinweis' : 'schlecht')), 'wert' => $jahr ? (string) $jahr : ''];
        $og = (bool) preg_match('~<meta[^>]+property=["\']og:image["\']~i', $html);
        $p[] = ['was' => 'teilen', 'stand' => $og ? 'gut' : 'hinweis', 'wert' => ''];
        return ['ok' => true, 'url' => (string) $a['url'], 'host' => $host, 'fehler' => '', 'punkte' => $p];
    }

    /**
     * Prüfen und als Bericht ablegen.
     * @return array{ok:bool, grund?:string, token?:string, ergebnis?:array}
     */
    public static function anlegen(int $partnerId, string $adresse): array
    {
        require_once __DIR__ . '/PartnerRecherche.php';
        if (self::adresse($adresse) === null) { return ['ok' => false, 'grund' => 'ck_adresse']; }
        if (!PartnerRecherche::zaehlen($partnerId, 'check', self::JE_TAG)) { return ['ok' => false, 'grund' => 'ck_genug']; }
        $e = self::pruefen($adresse);
        if ($e['fehler'] === 'adresse') { return ['ok' => false, 'grund' => 'ck_adresse']; }
        $token = bin2hex(random_bytes(16));
        Db::insert('partner_checks', ['partner_id' => $partnerId, 'token' => $token, 'url' => mb_substr($e['url'], 0, 500),
            'host' => mb_substr($e['host'] ?: (string) parse_url((string) self::adresse($adresse), PHP_URL_HOST), 0, 190),
            'ergebnis' => json_encode($e + ['geprueft' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'token' => $token, 'ergebnis' => $e];
    }

    public static function link(string $token): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/check.php?t=' . $token;
    }

    /** @return list<array{token:string,host:string,created_at:string,aufrufe:int,schlecht:int}> */
    public static function letzte(int $partnerId, int $n = 5): array
    {
        return array_map(static function (array $z): array {
            $e = json_decode((string) $z['ergebnis'], true) ?: [];
            return ['token' => (string) $z['token'], 'host' => (string) $z['host'], 'created_at' => (string) $z['created_at'], 'aufrufe' => (int) $z['aufrufe'],
                    'schlecht' => count(array_filter($e['punkte'] ?? [], static fn($p) => $p['stand'] === 'schlecht'))];
        }, Db::all('SELECT token, host, ergebnis, aufrufe, created_at FROM partner_checks WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, $n), [$partnerId]));
    }
}
