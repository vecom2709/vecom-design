<?php
/* ==========================================================================
   formular.php — nimmt das Anfrageformular der Website entgegen.

   WAS HIER PASSIERT, IN DER REIHENFOLGE DER WICHTIGKEIT:

   1. Die Anfrage wird in der Verwaltung festgehalten (Kunde + Anfrage +
      Zugangslink + Eingangsbestaetigung an den Kunden). Das ist der
      dauerhafte Nachweis — er ueberlebt jeden Mailausfall.
   2. Uwe bekommt eine E-Mail darueber.
   3. Geht beides schief, landet die Anfrage als Zeile in einer geschuetzten
      Datei. Verloren gehen darf sie nicht.

   Und ganz gleich, was passiert: Die Antwort sagt die Wahrheit. Genau das
   war frueher das Problem — die Seite meldete "unterwegs", waehrend hier
   ein Fehler 500 herauskam und die Anfrage im Nichts verschwand.

   ZUGANGSDATEN: Der Brevo-Schluessel steht in der Verwaltung unter
   Einstellungen → E-Mail-Versand. Als Rueckfall gelten weiterhin
   app/config.local.php und config.local.php — beide liegen nur auf dem
   Webspace und nie im Repository.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

/** Eine Antwort, ein Ende. Nie zwei Wege offen lassen. */
function antwort(int $code, array $daten): never
{
    http_response_code($code);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(405, ['ok' => false, 'error' => 'method']);
}

/* Honigtopf: ein Feld, das Menschen nie ausfuellen, Bots aber schon.
   Der Bot bekommt ein freundliches Ja und merkt nichts. */
if (!empty($_POST['website'])) {
    antwort(200, ['ok' => true]);
}

$sauber = static function ($v, int $max = 4000): string {
    $v = is_string($v) ? trim($v) : '';
    // \r und \0 raus: damit niemand ueber ein Eingabefeld eigene
    // Kopfzeilen in eine E-Mail schmuggeln kann.
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
};

$name      = $sauber($_POST['name'] ?? '', 120);
$email     = mb_strtolower($sauber($_POST['email'] ?? '', 160));
$telefon   = $sauber($_POST['telefon'] ?? '', 60);
$text      = $sauber($_POST['nachricht'] ?? '');
$paket     = $sauber($_POST['paket'] ?? '', 60);
$paketName = $sauber($_POST['paket_name'] ?? '', 120);
$seite     = $sauber($_POST['seite'] ?? '', 190);
$sprache   = $sauber($_POST['sprache'] ?? 'it', 2);
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $text === '') {
    antwort(422, ['ok' => false, 'error' => 'fields']);
}

/* Einfache Bremse: hoechstens eine Anfrage alle 20 Sekunden je Adresse. */
$sperre = sys_get_temp_dir() . '/vecom_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
if (is_file($sperre) && (time() - (int) filemtime($sperre)) < 20) {
    antwort(429, ['ok' => false, 'error' => 'slow down']);
}
@touch($sperre);

$betreff = 'Projektanfrage — ' . $name;
$body = "Neue Projektanfrage über vecom-design.it\n\n"
      . "Name:    $name\n"
      . "E-Mail:  $email\n"
      . ($telefon   !== '' ? "Telefon: $telefon\n" : '')
      . ($paketName !== '' ? "Paket:   $paketName\n" : '')
      . ($seite     !== '' ? "Seite:   $seite\n" : '')
      . "Sprache: $sprache\n"
      . "\n$text\n";

$gespeichert = false;   // steht die Anfrage in der Verwaltung?
$verschickt  = false;   // ist die Meldung an Uwe raus?
$anfrageId   = null;
$kundeId     = null;
$pannen      = [];

/* --------------------------------------------------------------------------
   1. In der Verwaltung festhalten. Das ist der eigentliche Zweck: Danach
      steht die Anfrage in der Datenbank, der Kunde ist angelegt, der
      Zugangslink existiert und der Kunde hat seine Eingangsbestaetigung.

      Config::all() beendet das Skript mit einer HTML-Meldung, wenn die
      Konfiguration fehlt. Deshalb wird vorher nachgesehen — sonst kaeme
      hier statt JSON eine Textseite heraus, und genau daran ist das
      Formular schon einmal gescheitert.
   -------------------------------------------------------------------------- */
if (!is_file(__DIR__ . '/app/config.local.php')) {
    $pannen[] = 'verwaltung: app/config.local.php fehlt';
} else {
    try {
        foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Mail', 'Anfrage'] as $klasse) {
            require_once __DIR__ . "/app/src/$klasse.php";
        }
        date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

        $anfrageId = Anfrage::annehmen([
            'name'        => $name,
            'email'       => $email,
            'telefon'     => $telefon,
            'nachricht'   => $text,
            'paket'       => $paket,
            'paket_name'  => $paketName,
            'website_url' => $seite,
            'sprache'     => $sprache,
        ]);
        $gespeichert = $anfrageId !== null;
        if ($gespeichert) {
            $kundeId = Db::wert('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrageId], null);
            $kundeId = $kundeId !== null ? (int) $kundeId : null;
        }
    } catch (Throwable $e) {
        $pannen[] = 'verwaltung: ' . $e->getMessage();
        error_log('formular.php — Verwaltung: ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------------
   2. Die Meldung an Uwe. Ueber Mail::senden, weil das den Versand im
      Nachrichtenprotokoll der Verwaltung festhaelt — eine E-Mail, die
      niemand nachsehen kann, ist eine halbe E-Mail.
   -------------------------------------------------------------------------- */
if (class_exists('Mail')) {
    try {
        $verschickt = Mail::senden(
            'anfrage_intern',
            Mail::eigeneAdresse(),
            $betreff,
            $body . ($anfrageId ? "\n— In der Verwaltung: /app/anfragen/$anfrageId\n" : ''),
            ['antwortAn' => $email, 'customer_id' => $kundeId]
        );
        if (!$verschickt) {
            // Mail::senden schreibt den Grund in die Tabelle mails. Steht die
            // Datenbank still, waere er sonst nirgends — deshalb hier noch
            // einmal fuer die Notfalldatei.
            $pannen[] = 'mail: Brevo hat die Meldung nicht angenommen';
        }
    } catch (Throwable $e) {
        $pannen[] = 'mail: ' . $e->getMessage();
        error_log('formular.php — Mail: ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------------
   3. Rueckfall auf den alten Weg: config.local.php im Stammverzeichnis,
      direkt an Brevo. Bleibt, damit ein Webspace ohne Verwaltung weiter
      funktioniert.
   -------------------------------------------------------------------------- */
if (!$verschickt) {
    $datei = __DIR__ . '/config.local.php';
    $cfg = is_file($datei) ? require $datei : null;
    if (is_array($cfg) && !empty($cfg['key']) && !empty($cfg['to']) && !empty($cfg['from'])) {
        $inhalt = [
            'sender'      => ['email' => $cfg['from'], 'name' => $cfg['name'] ?? 'Website'],
            'to'          => [['email' => $cfg['to']]],
            'replyTo'     => ['email' => $email, 'name' => $name],
            'subject'     => $betreff,
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
            CURLOPT_POSTFIELDS => json_encode($inhalt, JSON_UNESCAPED_UNICODE),
        ]);
        $antwort = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $verschickt = true;
        } else {
            $pannen[] = 'brevo-datei: HTTP ' . $code . ' ' . mb_substr((string) $antwort, 0, 160);
        }
    }
}

/* --------------------------------------------------------------------------
   4. Letzter Rueckfall: der Mailserver des Webspace.
   -------------------------------------------------------------------------- */
if (!$verschickt) {
    $an = 'kontakt@vecom-design.it';
    if (class_exists('Mail')) { $an = Mail::eigeneAdresse(); }
    $verschickt = @mail($an, $betreff, $body,
        "From: kontakt@vecom-design.it\r\nReply-To: $email\r\n"
        . "Content-Type: text/plain; charset=utf-8");
    if (!$verschickt) { $pannen[] = 'mail(): abgelehnt'; }
}

/* --------------------------------------------------------------------------
   5. Netz unter dem Netz: Ist die Anfrage weder gespeichert noch verschickt,
      wird sie in eine gesperrte Datei geschrieben. Lieber eine Zeile in
      einer Datei als eine Anfrage, die es nie gegeben hat.
   -------------------------------------------------------------------------- */
if (!$gespeichert && !$verschickt) {
    $ordner = __DIR__ . '/app/notfall';
    if (is_dir($ordner) || @mkdir($ordner, 0755, true)) {
        if (!is_file($ordner . '/.htaccess')) {
            @file_put_contents($ordner . '/.htaccess',
                "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        @file_put_contents($ordner . '/anfragen.jsonl',
            json_encode([
                'zeit' => date('c'), 'name' => $name, 'email' => $email,
                'telefon' => $telefon, 'paket' => $paketName ?: $paket,
                'seite' => $seite, 'sprache' => $sprache, 'nachricht' => $text,
                'pannen' => $pannen,
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX);
    }
    error_log('formular.php — Anfrage weder gespeichert noch verschickt: ' . implode(' | ', $pannen));
    antwort(502, ['ok' => false, 'error' => 'send']);
}

/* Angekommen ist sie, sobald einer der beiden Wege getragen hat. */
antwort(200, [
    'ok'          => true,
    'gespeichert' => $gespeichert,
    'gemeldet'    => $verschickt,
]);
