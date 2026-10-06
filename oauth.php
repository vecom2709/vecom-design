<?php
declare(strict_types=1);
/* ==========================================================================
   oauth.php — die Tür, durch die Claude um Erlaubnis fragt (AI Office Stufe 2, 07.10.2026).

   Aufgerufen von Claude (claude.ai), nicht von Besuchern. Die .htaccess
   leitet hierher:
     /.well-known/oauth-protected-resource[/mcp]   Steckbrief der Schnittstelle (RFC 9728)
     /.well-known/oauth-authorization-server       Steckbrief dieser Tür (RFC 8414)
     /oauth/register     Programm anmelden (RFC 7591)
     /oauth/authorize    um Erlaubnis fragen → weiter in die Verwaltung, wo Uwe „Erlauben“ klickt
     /oauth/token        Code oder Erneuerung gegen Schlüssel tauschen
     /oauth/revoke       Schlüssel zurückgeben (RFC 7009)

   Was hier passiert und warum so: app/src/ClaudeZugang.php.
   ========================================================================== */

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

function oauthJson(array $d, int $code = 200, bool $offen = false): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    // Die Steckbriefe sind öffentlich (wie bei jedem OAuth-Server); alles andere nicht.
    if ($offen) { header('Access-Control-Allow-Origin: *'); }
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function oauthSeite(string $text, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
    $h = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Claude-Zugang · Vecom Design</title><body style="font:16px/1.5 system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 16px;color:#222">'
        . '<h1 style="font-size:20px">Claude-Zugang</h1><p>' . $h . '</p></body></html>';
    exit;
}

if (!is_file(__DIR__ . '/app/config.local.php')) { oauthJson(['error' => 'temporarily_unavailable'], 503); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'ClaudeZugang'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

/* Welcher Schritt? Zuerst aus der aufgerufenen Adresse (REQUEST_URI ist auch nach der
   Umleitung die ursprüngliche) — ein angehängtes ?schritt=… soll nichts umlenken können.
   Nur wenn der Pfad nichts hergibt, gilt das ?schritt der .htaccess. */
$schritt = '';
$pfad = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (preg_match('~^/\.well-known/(oauth-protected-resource|oauth-authorization-server)(/mcp)?/?$~', $pfad, $m)) { $schritt = $m[1]; }
elseif (preg_match('~^/oauth/(register|authorize|token|revoke)/?$~', $pfad, $m)) { $schritt = $m[1]; }
elseif ($pfad === '/oauth.php') { $schritt = (string) ($_GET['schritt'] ?? ''); }
$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$kopf = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

try {
    switch ($schritt) {
        case 'oauth-protected-resource':
            oauthJson(ClaudeZugang::steckbriefRessource(), 200, true);

        case 'oauth-authorization-server':
            oauthJson(ClaudeZugang::steckbriefServer(), 200, true);

        case 'register':
            if ($methode !== 'POST') { header('Allow: POST'); oauthJson(['error' => 'invalid_request'], 405); }
            $d = json_decode((string) file_get_contents('php://input', false, null, 0, 65536), true);
            if (!is_array($d)) { oauthJson(['error' => 'invalid_client_metadata', 'error_description' => 'JSON erwartet.'], 400); }
            [$code, $antwort] = ClaudeZugang::registrieren($d, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            oauthJson($antwort, $code);

        case 'authorize':
            $q = $methode === 'POST' ? $_POST : $_GET;
            $e = ClaudeZugang::anfrageAnnehmen(is_array($q) ? $q : []);
            if ($e['art'] === 'fehler') { oauthSeite($e['text']); }
            header('Cache-Control: no-store');
            header('Location: ' . $e['ziel'], true, 302);
            exit;

        case 'token':
            if ($methode !== 'POST') { header('Allow: POST'); oauthJson(['error' => 'invalid_request'], 405); }
            [$code, $antwort] = ClaudeZugang::einloesen($_POST, $kopf);
            if ($code === 401) { header('WWW-Authenticate: Basic realm="Vecom"'); }
            oauthJson($antwort, $code);

        case 'revoke':
            if ($methode !== 'POST') { header('Allow: POST'); oauthJson(['error' => 'invalid_request'], 405); }
            ClaudeZugang::zurueckgeben((string) ($_POST['token'] ?? ''));
            oauthJson([], 200);
    }
} catch (Throwable $e) {
    try { Events::protokoll('claude_oauth_fehler', $schritt . ': ' . mb_substr($e->getMessage(), 0, 300)); } catch (Throwable $e2) { }
    oauthJson(['error' => 'server_error'], 500);
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
exit("Nicht gefunden.\n");
