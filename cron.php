<?php
declare(strict_types=1);
/* ==========================================================================
   Der regelmaessige Lauf, angestossen vom Cronjob im KAS.

   Er prueft die ueberwachten Websites, erinnert an offene Fragebogen,
   warnt vor ablaufenden Zertifikaten und raeumt abgelaufene Zahlungslinks
   weg. Alles Weitere steht in app/src/Cron.php.

   Aufgerufen wird die Adresse aus der Verwaltung unter Website-Monitoring —
   sie traegt einen Schluessel. Ohne den passiert hier nichts.
   ========================================================================== */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit("Noch nicht eingerichtet.\n"); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
// Ein Eingang ohne Menschen: Während des Not-Aus geht von hier nichts an Kunden,
// Partner oder die Öffentlichkeit (AI Office Stufe 0, 06.10.2026, Automation::ausgangGesperrt).
require_once __DIR__ . '/app/src/Automation.php';
Automation::automatischAb('cron');
require_once __DIR__ . '/app/src/Cron.php';
require_once __DIR__ . '/app/src/Einrichtung.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

// Der Schluessel darf in der Adresse stehen (so kann der KAS ihn aufrufen)
// oder im Kopf mitkommen. Verglichen wird zeitkonstant.
//
// SEIT 06.10.2026 (AI Office Stufe 0) AUCH ALS HTTP-PASSWORT: Ein Schluessel in
// der Adresse landet in jedem Serverprotokoll. Der KAS-Cronjob kann unter
// „Erweiterte Einstellungen“ einen HTTP-Benutzer und ein HTTP-Passwort
// mitschicken (all-inkl.com, Anleitung „Cronjobs: Einrichtung“) — dann steht
// der Schluessel im Kopf, nicht in der Adresse. Welcher Weg zuletzt kam, merkt
// sich cron_weg; die Verwaltung zeigt es, damit der Umstieg messbar ist.
$cronBasis = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
if ($cronBasis === '') {
    $cronKopf = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos($cronKopf, 'basic ') === 0) {
        $cronTeile = explode(':', (string) base64_decode(substr($cronKopf, 6), true), 2);
        $cronBasis = (string) ($cronTeile[1] ?? '');
    }
}
$cronWeg = $cronBasis !== '' ? 'passwort' : (isset($_SERVER['HTTP_X_VECOM_CRON']) ? 'kopf' : 'adresse');
$schluessel = $cronBasis !== '' ? $cronBasis : (string) ($_SERVER['HTTP_X_VECOM_CRON'] ?? $_GET['schluessel'] ?? '');

try {
    if (!Cron::schluesselStimmt($schluessel)) {
        // Absichtlich wortkarg: Wer den Schluessel nicht hat, erfaehrt auch
        // nicht, ob es hier ueberhaupt etwas zu holen gibt.
        http_response_code(404);
        exit("Nicht gefunden.\n");
    }
} catch (Throwable $e) {
    http_response_code(503);
    exit("Noch nicht bereit.\n");
}
try {
    Db::run("INSERT INTO settings (skey, svalue) VALUES ('cron_weg', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$cronWeg]);
} catch (Throwable $e) { /* nur Anzeige */ }
/* Prüfung 07.10.2026 (Vorschlag 8): Kommt der Schlüssel noch über die Adresse, steht er in jedem Zugriffsprotokoll.
   Abschalten würde den Cronjob anhalten (KAS ruft genau diese Adresse) — deshalb einmal pro Woche eine Meldung
   mit dem Handgriff im KAS, bis der Weg „passwort“ ist. Danach wird die Adresse nicht mehr angenommen. */
if ($cronWeg === 'adresse') {
    try {
        if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_passwort_seit'", [], '') !== '') {
            http_response_code(404); exit("Nicht gefunden.\n");   // einmal umgestellt: die Adresse gilt nicht mehr
        }
        $cwSchl = 'cron_weg_hinweis_' . date('o-W');
        if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$cwSchl], '') === '') {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$cwSchl, date('Y-m-d H:i:s')]);
            require_once __DIR__ . '/app/src/Events.php';
            Events::melden('cron_weg', 'Cronjob-Schlüssel steht noch in der Adresse', 'info',
                'Im KAS unter Tools → Cronjobs beim Vecom-Cronjob „Benutzer“ (z. B. cron) und „Passwort“ = den bisherigen Schlüssel eintragen und ?schluessel=… aus der Adresse nehmen. Danach steht der Schlüssel in keinem Protokoll mehr, und die Adresse wird nicht mehr angenommen.', '/monitoring');
        }
    } catch (Throwable $e) { /* nur Hinweis */ }
} elseif ($cronWeg === 'passwort') {
    try { Db::run("INSERT IGNORE INTO settings (skey, svalue) VALUES ('cron_passwort_seit', ?)", [date('Y-m-d H:i:s')]); } catch (Throwable $e) { }
}

/* --------------------------------------------------------------------------
   Zuerst: die Datenbank nachziehen.

   WARUM DAS HIER STEHT UND NICHT NUR IN DER VERWALTUNG

   Bisher hat ausschliesslich app/index.php offene Migrationen eingespielt —
   also erst dann, wenn Uwe die Verwaltung aufmachte. Nach jedem Deploy, der
   eine Spalte hinzufuegt, lief der neue Code bis dahin auf der alten
   Datenbank. Und zwar nicht nur fuer ihn: fragebogen.php, projekt.php,
   vorgang.php, buchen.php, formular.php und stripe-webhook.php gehoeren dem
   Kunden, und die fragen niemanden.

   Am 31.08. ist genau das passiert. In den Meldungen steht
   "Fragebogen nicht erreichbar — Unknown column 'c.sprache'": Ein Kunde hat
   seinen Link angeklickt und eine Fehlerseite bekommen, weil die Spalte im
   Code schon da war und in der Datenbank noch nicht.

   Der Cronjob laeuft alle zehn Minuten. Damit ist das Fenster nie groesser
   als zehn Minuten, und niemand muss daran denken. Beispieldaten legt er
   ausdruecklich nicht an — deshalb der Schalter.

   Erst nach der Schluesselpruefung: Sonst koennte ein Fremder den Aufruf
   nutzen, um Schreibvorgaenge an der Datenbank anzustossen.
   -------------------------------------------------------------------------- */
try {
    $stand = Einrichtung::selbsttaetig(false);
    if ($stand['migrationen']) {
        Events::protokoll('system_migration', 'Datenbank vom Cronjob nachgezogen: '
            . implode(', ', $stand['migrationen']));
    }
    if ($stand['fehler'] !== null) {
        Events::melden('system_migration', 'Die Datenbank liess sich nicht aktualisieren', 'schlecht',
            mb_substr((string) $stand['fehler'], 0, 400)
                . ' — solange das so bleibt, koennen Kundenseiten auf Fehler laufen.',
            '/einstellungen');
    }
} catch (Throwable $e) {
    // Das Nachziehen ist Vorsorge. Scheitert es, soll der eigentliche Lauf
    // trotzdem stattfinden — Monitoring und Erinnerungen sind wichtiger.
    $stand = ['migrationen' => [], 'fehler' => $e->getMessage()];
}

try {
    $bilanz = Cron::laufen(isset($_GET['sofort']));
} catch (Throwable $e) {
    http_response_code(500);
    // In der Antwort steht nur, dass es schiefging. Der Grund landet dort,
    // wo Uwe ihn sieht — nicht in einer oeffentlich abrufbaren Zeile.
    try {
        Events::melden('cron_fehler', 'Der regelmäßige Lauf ist gescheitert', 'schlecht',
            mb_substr($e->getMessage(), 0, 400), '/monitoring');
    } catch (Throwable $e2) { /* dann eben nicht */ }
    exit("Fehler.\n");
}

if (!empty($bilanz['uebersprungen'])) {
    echo "uebersprungen: " . $bilanz['grund'] . "\n";
    exit;
}

echo "ok " . date('d.m.Y H:i:s') . "\n";
if (!empty($stand['migrationen'])) {
    echo str_pad('migrationen', 16) . implode(', ', $stand['migrationen']) . "\n";
}
foreach ($bilanz as $name => $wert) {
    if ($name === 'zeit') { continue; }
    echo str_pad((string) $name, 16) . (is_array($wert) ? json_encode($wert, JSON_UNESCAPED_UNICODE) : (string) $wert) . "\n";
}
