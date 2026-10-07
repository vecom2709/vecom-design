<?php
declare(strict_types=1);
/* ==========================================================================
   claude-mcp.php — Claudes Lesezugang zur Verwaltung (AI Office Stufe 2, 07.10.2026).

   WARUM NICHT mcp.php (07.10.2026, gemessen): Der Webspace hat MultiViews an.
   Liegt eine mcp.php im Wurzelordner, beantwortet Apache /mcp selbst, noch vor
   der .htaccess — und weil Claude „Accept: application/json“ schickt, der
   Variante aber der Typ application/x-httpd-php anhängt, kam 406 Not Acceptable.
   Mit curl (das jede Antwortart nimmt) fiel es nicht auf. Ein Name, der nicht mit „mcp.“
   beginnt, lässt /mcp bis zur RewriteRule durch. Die alte mcp.php nimmt die
   Abrissliste im Deploy vom Webspace — solange sie dort liegt, bleibt der Fehler.

   Die Adresse, die Uwe bei Claude als Connector einträgt:
       https://vecom-design.it/mcp
   Ein MCP-Server über Streamable HTTP, nur POST, Antwort immer als ein
   JSON-Objekt (keine Ströme — nichts hier dauert so lange, dass es sich lohnt).

   ZWEI ZEITALTER AUF EINER ADRESSE
   Seit der Fassung 2026-07-28 trägt jede Anfrage ihre Version selbst
   (_meta und Kopfzeilen), es gibt kein „initialize“ mehr. Ältere Clients
   (bis 2025-11-25) begrüßen sich erst mit „initialize“. Welche Fassung
   Claude gerade spricht, ist nicht in unserer Hand — also beide: Eine
   Anfrage mit _meta wird nach 2026-07-28 bedient, „initialize“ nach der
   alten Weise. Sitzungen vergeben wir in keinem Fall; es gibt nichts, was
   zwischen zwei Anfragen gemerkt werden müsste.

   Ohne gültigen Schlüssel: 401 mit dem Hinweis, wo es den gibt (RFC 9728) —
   daran erkennt Claude, dass es um Erlaubnis fragen muss. Wie: claude-oauth.php.
   Was gelesen werden darf: app/src/ClaudeWerkzeuge.php.
   ========================================================================== */

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

const MCP_MODERN = '2026-07-28';
const MCP_ALT = ['2025-11-25', '2025-06-18', '2025-03-26'];

function mcpAntwort(?array $d, int $code = 200): never
{
    http_response_code($code);
    if ($d === null) { exit; }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function mcpFehler(mixed $id, int $fehler, string $text, int $http = 200, ?array $daten = null): never
{
    $e = ['code' => $fehler, 'message' => $text];
    if ($daten !== null) { $e['data'] = $daten; }
    $d = ['jsonrpc' => '2.0', 'error' => $e];
    if ($id !== null) { $d = ['jsonrpc' => '2.0', 'id' => $id, 'error' => $e]; }
    mcpAntwort($d, $http);
}

if (!is_file(__DIR__ . '/app/config.local.php')) { mcpAntwort(null, 503); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'ClaudeZugang', 'ClaudeWerkzeuge', 'ClaudeEintragen'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

/* DNS-Rebinding-Schutz (MCP-Transport, „Security“): Kommt ein Origin mit, muss es Claude oder wir selbst sein. */
$herkunft = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($herkunft !== '' && !in_array(rtrim($herkunft, '/'), array_merge(ClaudeZugang::HERKUENFTE, [ClaudeZugang::basis()]), true)) {
    mcpFehler(null, -32600, 'Herkunft nicht erlaubt.', 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // Kein Strom per GET, keine Sitzung zum Beenden per DELETE.
    header('Allow: POST');
    mcpAntwort(null, 405);
}

try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

/* ---------- Wer fragt? ---------- */
$kopf = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$token = preg_match('/^Bearer\s+(\S+)$/i', trim($kopf), $m) ? $m[1] : '';
$verbindung = null;
try { $verbindung = ClaudeZugang::pruefen($token); } catch (Throwable $e) { mcpAntwort(null, 503); }
if ($verbindung === null) {
    $steckbrief = ClaudeZugang::basis() . '/.well-known/oauth-protected-resource/mcp';
    header('WWW-Authenticate: Bearer resource_metadata="' . $steckbrief . '", scope="' . implode(' ', ClaudeZugang::UMFAENGE) . '"'
        . ($token !== '' ? ', error="invalid_token"' : ''));
    mcpFehler(null, -32001, $token === '' ? 'Anmeldung nötig.' : 'Schlüssel ungültig oder abgelaufen.', 401);
}

/* ---------- Was wird gefragt? ---------- */
$roh = (string) file_get_contents('php://input', false, null, 0, 1048576);
$nachricht = json_decode($roh, true);
if (!is_array($nachricht) || array_is_list($nachricht)) { mcpFehler(null, -32700, 'Eine einzelne JSON-RPC-Nachricht erwartet.', 400); }
$id = $nachricht['id'] ?? null;
$art = (string) ($nachricht['method'] ?? '');
$param = is_array($nachricht['params'] ?? null) ? $nachricht['params'] : [];
if (($nachricht['jsonrpc'] ?? '') !== '2.0' || $art === '') { mcpFehler($id, -32600, 'Ungültige Anfrage.', 400); }

/* Benachrichtigungen (ohne id): annehmen, nichts tun. */
if (!array_key_exists('id', $nachricht)) { mcpAntwort(null, 202); }

$meta = is_array($param['_meta'] ?? null) ? $param['_meta'] : [];
$modern = isset($meta['io.modelcontextprotocol/protocolVersion']);
$kopfWert = static function (string $name): ?string {
    $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$k]) ? (string) $_SERVER[$k] : null;
};
$entschluesseln = static function (?string $w): ?string {
    if ($w !== null && preg_match('/^=\?base64\?([A-Za-z0-9+\/=]*)\?=$/', $w, $m)) { $d = base64_decode($m[1], true); return $d === false ? null : $d; }
    return $w;
};

if ($modern) {
    $version = (string) $meta['io.modelcontextprotocol/protocolVersion'];
    if ($version !== MCP_MODERN) {
        mcpFehler($id, -32022, 'Unsupported protocol version', 400, ['supported' => array_merge([MCP_MODERN], MCP_ALT), 'requested' => $version]);
    }
    // Kopf und Rumpf müssen dasselbe sagen (Streamable HTTP, „Server Validation“).
    $abweichung = null;
    if ($kopfWert('MCP-Protocol-Version') !== $version) { $abweichung = 'MCP-Protocol-Version'; }
    elseif ($kopfWert('Mcp-Method') !== $art) { $abweichung = 'Mcp-Method'; }
    elseif (in_array($art, ['tools/call', 'resources/read', 'prompts/get'], true)
        && $entschluesseln($kopfWert('Mcp-Name')) !== (string) ($param['name'] ?? $param['uri'] ?? '')) { $abweichung = 'Mcp-Name'; }
    if ($abweichung !== null) { mcpFehler($id, -32020, 'Header mismatch: ' . $abweichung, 400); }
}

$ergebnis = static function (array $r) use ($id, $modern): never {
    if ($modern) { $r = ['resultType' => 'complete'] + $r; }
    // Ein leeres Ergebnis (ping) ist ein JSON-Objekt {}, keine Liste [] — so verlangt es JSON-RPC.
    mcpAntwort(['jsonrpc' => '2.0', 'id' => $id, 'result' => $r === [] ? new stdClass() : $r]);
};
$anleitung = 'Lesezugang zur Verwaltung von Vecom Design (vecom-design.it), der Webdesign-Agentur von Uwe auf Sizilien. '
    . 'Lesen immer; mit der Erlaubnis „Eintragen“ auch Notizen, Aufgaben, Wiedervorlagen, Meldungen als gelesen und Vorschläge in AI Freigaben. '
    . 'Nach draußen geht nie etwas direkt: Nachrichten und Angebote nur als Vorschlag, Uwe genehmigt. Nichts erfinden. '
    . 'Mit Uwe Deutsch sprechen und ihn siezen. Beträge kommen in Cent; null heißt „ließ sich nicht lesen“, nicht „keine“. '
    . 'Für einen Überblick zuerst lage_heute; für „warum ist das so?“ wissen_suchen.';
$server = ['name' => 'vecom-verwaltung', 'title' => 'Vecom Verwaltung', 'version' => '2.0'];

switch ($art) {
    case 'initialize':
        // Alte Weise (bis 2025-11-25): Version aushandeln, mehr nicht.
        $wunsch = (string) ($param['protocolVersion'] ?? '');
        $ergebnis(['protocolVersion' => in_array($wunsch, MCP_ALT, true) ? $wunsch : MCP_ALT[0],
                   'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => $server, 'instructions' => $anleitung]);

    case 'server/discover':
        $ergebnis(['supportedVersions' => array_merge([MCP_MODERN], MCP_ALT), 'capabilities' => ['tools' => ['listChanged' => false]],
                   '_meta' => ['io.modelcontextprotocol/serverInfo' => $server], 'instructions' => $anleitung]);

    case 'ping':
        $ergebnis([]);

    case 'tools/list':
        // Was Claude sieht, hängt am erlaubten Umfang (MCP „Tools“: darf je nach Berechtigung verschieden sein).
        $eintragen = ClaudeZugang::darf($verbindung, ClaudeZugang::EINTRAGEN);
        $ergebnis(['tools' => array_merge(ClaudeWerkzeuge::liste(), $eintragen ? ClaudeEintragen::liste() : [])]);

    case 'tools/call':
        $name = (string) ($param['name'] ?? '');
        $argumente = is_array($param['arguments'] ?? null) ? $param['arguments'] : [];
        $schreibt = in_array($name, ClaudeEintragen::namen(), true);
        if (!$schreibt && !in_array($name, ClaudeWerkzeuge::namen(), true)) { mcpFehler($id, -32602, 'Unbekanntes Werkzeug: ' . mb_substr($name, 0, 60)); }
        $vid = (int) $verbindung['id'];
        if ($schreibt && !ClaudeZugang::darf($verbindung, ClaudeZugang::EINTRAGEN)) {
            // Fehlt der Umfang: 403 mit dem, was gebraucht wird — Claude kann dann neu um Erlaubnis fragen (MCP „Scope Challenge“).
            ClaudeZugang::spur($vid, $name, [], false, 0, 0);
            header('WWW-Authenticate: Bearer error="insufficient_scope", scope="' . implode(' ', ClaudeZugang::UMFAENGE) . '", resource_metadata="'
                . ClaudeZugang::basis() . '/.well-known/oauth-protected-resource/mcp", error_description="Eintragen ist nicht erlaubt"');
            mcpFehler($id, -32001, 'Für Eintragen fehlt die Erlaubnis — bitte in Claude neu verbinden.', 403);
        }
        if (ClaudeZugang::zuViel($vid)) {
            ClaudeZugang::spur($vid, $name, $argumente, false, 0, 0);
            $ergebnis(['content' => [['type' => 'text', 'text' => 'Zu viele Abfragen in kurzer Zeit. In zehn Minuten wieder.']], 'isError' => true]);
        }
        $t0 = microtime(true);
        $r = $schreibt ? ClaudeEintragen::rufen($name, $argumente, $vid) : ClaudeWerkzeuge::rufen($name, $argumente);
        // In der Spur steht bei Texten nur die Länge — eine Kundennachricht gehört nicht ins Protokoll der Griffe.
        $spurArg = array_map(static fn($w) => is_string($w) && mb_strlen($w) > 80 ? '(' . mb_strlen($w) . ' Zeichen)' : $w, $argumente);
        ClaudeZugang::spur($vid, $name, $spurArg, $r['ok'], mb_strlen($r['text']), (int) round((microtime(true) - $t0) * 1000));
        $aus = ['content' => [['type' => 'text', 'text' => $r['text']]], 'isError' => !$r['ok']];
        if ($r['ok'] && is_array($r['daten']) && !array_is_list($r['daten'])) { $aus['structuredContent'] = $r['daten']; }
        $ergebnis($aus);
}
// Unbekannte Methode: in der neuen Fassung mit 404 (Streamable HTTP, „Protocol Version Header“).
mcpFehler($id, -32601, 'Method not found', $modern ? 404 : 200);
