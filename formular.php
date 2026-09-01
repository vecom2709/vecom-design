<?php
/* ==========================================================================
   formular.php — nimmt das Anfrageformular entgegen und verschickt es
   über Brevo. Läuft auf dem eigenen Webspace bei All-Inkl.

   Warum über den eigenen Server und nicht direkt aus dem Browser:
   Der Brevo-Schlüssel darf niemals im Quelltext der Seite stehen — dort
   könnte ihn jeder auslesen und in deinem Namen E-Mails verschicken.
   Deshalb liegt er in config.local.php, die nie ins Repository kommt.

   EINRICHTUNG (einmalig, zwei Minuten):
   1. Bei Brevo einloggen → oben rechts auf den Namen → "SMTP & API"
      → Reiter "API-Schlüssel" → "Neuen API-Schlüssel erstellen".
   2. Neben dieser Datei eine Datei config.local.php anlegen mit:

        <?php
        return [
          'key'  => 'xkeysib-hier-der-schluessel',
          'to'   => 'kontakt@vecom-design.it',
          'from' => 'kontakt@vecom-design.it',
          'name' => 'Vecom Design Website',
        ];

   3. In Brevo unter "Absender & IP" die Adresse kontakt@vecom-design.it
      als Absender bestätigen — sonst lehnt Brevo den Versand ab.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'method']));
}

$cfgFile = __DIR__ . '/config.local.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'config']));
}
$cfg = require $cfgFile;

/* Honigtopf: Ein Feld, das Menschen nie ausfüllen, Bots aber schon. */
if (!empty($_POST['website'])) {
    exit(json_encode(['ok' => true]));      // still verwerfen
}

$clean = static function ($v, $max = 4000) {
    $v = is_string($v) ? trim($v) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
};

$name    = $clean($_POST['name'] ?? '', 120);
$email   = $clean($_POST['email'] ?? '', 160);
$telefon = $clean($_POST['telefon'] ?? '', 60);
$text    = $clean($_POST['nachricht'] ?? '');
$paket     = $clean($_POST['paket'] ?? '', 60);
$paketName = $clean($_POST['paket_name'] ?? '', 120);
$seite     = $clean($_POST['seite'] ?? '', 190);
$sprache   = $clean($_POST['sprache'] ?? 'it', 2);

/* --------------------------------------------------------------------------
   Die Anfrage zusaetzlich in der Verwaltung festhalten: Kunde anlegen oder
   finden, Anfrage daranhaengen. Bewusst NACH dem Versand und in einem
   try/catch — die E-Mail hat Vorrang. Steht die Datenbank still, soll die
   Anfrage trotzdem ankommen; sie ist dann eben nur im Postfach.
   -------------------------------------------------------------------------- */
$merken = static function () use ($name, $email, $telefon, $text, $paket, $paketName, $seite, $sprache): void {
    $konfig = __DIR__ . '/app/config.local.php';
    if (!is_file($konfig)) { return; }
    try {
        foreach (['Config', 'Db', 'Status', 'Auth', 'Events', 'Anfrage'] as $k) {
            require_once __DIR__ . "/app/src/$k.php";
        }
        Anfrage::annehmen([
            'name' => $name, 'email' => $email, 'telefon' => $telefon,
            'nachricht' => $text, 'paket' => $paket, 'paket_name' => $paketName,
            'website_url' => $seite, 'sprache' => $sprache,
        ]);
    } catch (Throwable $e) {
        // Bewusst still: Der Besucher hat abgeschickt, die Mail ist unterwegs.
        error_log('Anfrage konnte nicht gespeichert werden: ' . $e->getMessage());
    }
};

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $text === '') {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'error' => 'fields']));
}

/* Einfache Bremse: höchstens eine Anfrage alle 20 Sekunden je Adresse. */
$lock = sys_get_temp_dir() . '/vecom_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
if (is_file($lock) && (time() - filemtime($lock)) < 20) {
    http_response_code(429);
    exit(json_encode(['ok' => false, 'error' => 'slow down']));
}
touch($lock);

$body = "Neue Projektanfrage über vecom-design.it\n\n"
      . "Name:    $name\n"
      . "E-Mail:  $email\n"
      . ($telefon !== '' ? "Telefon: $telefon\n" : '')
      . ($paketName !== '' ? "Paket:   $paketName\n" : '')
      . "\n$text\n";

$payload = [
    'sender'      => ['email' => $cfg['from'], 'name' => $cfg['name'] ?? 'Website'],
    'to'          => [['email' => $cfg['to']]],
    'replyTo'     => ['email' => $email, 'name' => $name],
    'subject'     => 'Projektanfrage — ' . $name,
    'textContent' => $body,
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $cfg['key'],
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    $merken();
    exit(json_encode(['ok' => true]));
}

/* Fällt Brevo aus, geht die Anfrage trotzdem nicht verloren:
   dann übernimmt der Mailserver des Webspace. */
$sent = @mail($cfg['to'], 'Projektanfrage — ' . $name, $body,
    "From: {$cfg['from']}\r\nReply-To: $email\r\nContent-Type: text/plain; charset=utf-8");

$merken();
http_response_code($sent ? 200 : 502);
echo json_encode(['ok' => (bool) $sent, 'error' => $sent ? null : 'send']);
