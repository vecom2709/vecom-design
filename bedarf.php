<?php
declare(strict_types=1);
/* ==========================================================================
   bedarf.php — Der Konfigurator auf der Website.

   WAS HIER AN DIE STELLE VON DREI PREISKARTEN GETRETEN IST

   Feste Pakete zwingen den Kunden, sich selbst einzusortieren. Wer mehr
   braucht als das teuerste, zahlt trotzdem nur das teuerste; wer weniger
   braucht, nimmt das billigste und ist unzufrieden. Hier beschreibt er
   stattdessen, was er braucht — und der Preis entsteht daraus.

   WARUM AM ENDE TROTZDEM EINE ZAHL STEHT

   Die Zielgruppe sind kleine Betriebe in und um Agrigent. Wer dort gar
   keine Zahl sieht, denkt "das kann ich mir nicht leisten" und fragt erst
   gar nicht. Deshalb steht am Ende eine Spanne — nicht auf der Startseite,
   sondern erst, wenn der Kunde gesagt hat, was er will. Dann ist sie keine
   Werbung mehr, sondern eine Auskunft. Abschaltbar ueber die Einstellung
   bedarf_spanne_zeigen, falls sich das als falsch erweist.

   OHNE KONTO, MIT SCHLUESSEL IN DER ADRESSE

   Wie beim Fragebogen und bei der Projektseite. Wer zumacht, kommt mit
   demselben Link an dieselbe Stelle zurueck. Der Schluessel steht in der
   Adresse des Formulars, nicht in seinem Rumpf: Verwirft der Server eine zu
   grosse Eingabe, sind $_POST und $_FILES leer — dann waere der Schluessel
   weg und der Kunde ausgesperrt. Dieser Fehler ist hier schon einmal
   passiert und soll sich nicht wiederholen.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Konfigurator ist derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Baukasten.php';
require_once __DIR__ . '/app/src/Bedarf.php';
require_once __DIR__ . '/app/src/Einfuehrung.php';
require_once __DIR__ . '/app/src/Empfehlung.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

/* ---------- Als Telegram-Mini-App (30.09.2026) ----------
   ?tg=QUELLE heißt: Diese Seite läuft im Fenster über dem Telegram-Kanal
   (telegram-app.php). Drei Unterschiede, sonst ist alles dieselbe Seite:
   Telegram Web darf sie einbetten; das Sitzungs-Cookie muss dann auch im
   fremden Rahmen mitkommen (SameSite=None, sonst scheitert das CSRF-Feld
   bei jedem Klick); und die Anfrage trägt die Herkunft „telegram“. */
$tg = preg_match('/^[a-z]{2,12}$/', (string) ($_GET['tg'] ?? '')) ? (string) $_GET['tg'] : '';
if ($tg !== '') {
    require_once __DIR__ . '/app/src/TelegramApp.php';
    header('Content-Security-Policy: ' . TelegramApp::EINBETTEN);
    session_set_cookie_params(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
}
$tgZusatz = $tg !== '' ? '&tg=' . rawurlencode($tg) : '';
session_name('vecombedarf');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* ---------- Sprache ---------- */
/* Eine Stelle entscheidet ueber die Sprache, und sie merkt sie fuer die
   naechste Seite -- auch fuer die statischen (23.09.2026). */
require_once __DIR__ . '/app/src/Sprache.php';
$sprache = Sprache::ausAnfrage();
Sprache::merken($sprache);

$T = static fn(string $s): string => Texte::h(Texte::BEDARF[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/");

/* Welche Frage auf welcher Seite steht, entscheidet Baukasten::SCHRITTE —
   dort, weil der Datensatz dieselbe Zahl zum Begrenzen braucht. Der letzte
   Schritt traegt keine Fragen mehr, sondern das Ergebnis. */
$anzahl = Baukasten::schrittZahl();

/* Absolut, nicht relativ — und das ist kein Schoenheitsfehler.
   Der Empfehlungslink /e/ANNA3CU wird serverseitig auf diese Datei
   umgeschrieben, die Adresse im Browser bleibt aber /e/ANNA3CU. Eine
   relative Weiterleitung landet dann unter /e/bedarf.php, und der Kunde
   steht vor einer toten Seite — ausgerechnet der, den jemand empfohlen hat. */
$adresse = static function (int $schritt, string $token, string $meldung = '') use ($sprache, $tgZusatz): string {
    $u = '/bedarf.php?t=' . rawurlencode($token) . '&lang=' . rawurlencode($sprache) . '&schritt=' . $schritt . $tgZusatz;
    return $meldung !== '' ? $u . '&m=' . rawurlencode($meldung) : $u;
};

/* ---------- Empfehlungscode aus der Adresse ----------
   Er kommt am Anfang herein (/e/CODE) und wird erst ganz am Ende gebraucht.
   Deshalb in die Sitzung: Vier Schritte spaeter steht er sonst nicht mehr in
   der Adresse, und die Empfehlung waere verloren. */
$codeRoh = strtoupper(trim((string) ($_REQUEST['e'] ?? '')));
if ($codeRoh !== '' && preg_match('/^[A-Z0-9]{5,16}$/', $codeRoh)) {
    $_SESSION['empfehl_code'] = $codeRoh;
}
$empfehlCode = (string) ($_SESSION['empfehl_code'] ?? '');
$empfehlName = '';
if ($empfehlCode !== '') {
    try {
        $eid = Empfehlung::kundeZuCode($empfehlCode);
        if ($eid) { $empfehlName = (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$eid], ''); }
        else { $empfehlCode = ''; unset($_SESSION['empfehl_code']); }
    } catch (Throwable $e) { $empfehlCode = ''; }
}

/* ---------- Auswahl aus einer Branchen-Demo ----------
   Wie der Empfehlungscode: Sie kommt ueber die Adresse herein, muss aber
   das Sprachtor und alle Schritte ueberleben -- also in die Sitzung.
   Bedarf::demoPruefen laesst nur bekannte Schluessel durch. */
$demoRoh = Bedarf::demoPruefen(strtolower(trim((string) ($_GET['demo'] ?? ''))));
if ($demoRoh !== '') { $_SESSION['bedarf_demo'] = $demoRoh; }
$demo     = (string) ($_SESSION['bedarf_demo'] ?? '');
$demoText = Bedarf::demoText($demo, $sprache);
// Kuechenplan aus dem Planer: gleiche Behandlung, eigener Schluessel
$planRoh = Bedarf::planPruefen((string) ($_GET['plan'] ?? ''));
if ($planRoh !== '') { $_SESSION['bedarf_plan'] = $planRoh; }
$plan = (string) ($_SESSION['bedarf_plan'] ?? '');
if ($plan !== '') { $demoText = ($demoText !== '' ? $demoText . ': ' : '') . Bedarf::planText($plan, $sprache); }

/* ---------- Laden oder anfangen ---------- */
$token = trim((string) ($_REQUEST['t'] ?? ''));
$b = null;
$panne = false;

try {
    Baukasten::sicherstellen();
    if ($token !== '') {
        $b = Bedarf::laden($token);
    }
    if (!$b && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        /* Das Sprachtor, das hier bis zum 24.09.2026 stand, ist mit dem
           E-Mail-Einstieg weggefallen: Die Sprache kommt jetzt aus der
           Fassung der Seite, auf der er die Adresse eintraegt, und gefragt
           wird er im Vorhaben (Feld „In welcher Sprache soll ich Ihnen
           schreiben“). Ohne Schluessel entsteht hier nichts mehr. */
        /* SEIT DEM 24.09.2026 BEGINNT ES MIT DER ADRESSE (S2)
           ------------------------------------------------------------------
           Wer ohne Schluessel kommt -- ueber einen alten Knopf, ein
           Lesezeichen, den Showroom oder einen Empfehlungslink /e/CODE --,
           landet beim E-Mail-Einstieg. Die acht Fragen beantwortet er
           danach in seinem Dashboard. Ein Link mit Schluessel (etwa aus
           einer Mail des Telefonassistenten) oeffnet weiter genau diesen
           Bedarf -- kein bereits verschickter Link stirbt. */
        header('Location: /zugang.php?lang=' . rawurlencode($sprache)
            . ($empfehlCode !== '' ? '&e=' . rawurlencode($empfehlCode) : ''), true, 302);
        exit;
    }
} catch (Throwable $e) {
    $panne = true;
    try {
        Events::melden('bedarf_fehler', 'Konfigurator nicht erreichbar', 'schlecht', $e->getMessage(), '/anfragen');
    } catch (Throwable $e2) { /* dann eben nicht */ }
}

/* ---------- Im Dashboard (24.09.2026, D1/D2) ----------
   Gehoert der Bedarf schon einem Kunden, kommt er aus seinem Dashboard: Die
   Adresse ist bekannt und wird nicht noch einmal gefragt, der Weg zurueck
   steht oben, und nach dem Absenden geht es dorthin zurueck statt auf eine
   Dankeseite. */
$imDashboard = false; $dashKunde = null; $dashLink = '';
if ($b && $b['customer_id'] !== null) {
    try {
        require_once __DIR__ . '/app/src/Kundenzugang.php';
        $dashKunde = Db::one('SELECT id, name, email, phone, company, sprache FROM customers WHERE id = ?', [(int) $b['customer_id']]);
        if ($dashKunde) {
            $imDashboard = true;
            $dashLink = Kundenzugang::linkFuer((int) $dashKunde['id'], $sprache);
        }
    } catch (Throwable $e) { $imDashboard = false; }
    if ($imDashboard && $empfehlCode === '' && trim((string) ($b['empfehl_code'] ?? '')) !== '') {
        $empfehlCode = strtoupper(trim((string) $b['empfehl_code']));
        try {
            $eid = Empfehlung::kundeZuCode($empfehlCode);
            if ($eid) { $empfehlName = (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$eid], ''); }
            else { $empfehlCode = ''; }
        } catch (Throwable $e) { $empfehlCode = ''; }
    }
    // Schon abgesendet? Dann gehoert er ins Dashboard, nicht auf eine Dankeseite
    // In der Mini-App nicht: Dort zeigt die Dankeseite „Zurück zu Telegram“ (Telegram Web
    // dürfte das Dashboard ohnehin nicht einbetten).
    if ($imDashboard && $tg === '' && $b['status'] !== 'offen' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . $dashLink, true, 303); exit;
    }
}

/* ---------- Schreiben, dann umleiten ---------- */
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $jetzt = max(1, min($anzahl, (int) ($_POST['schritt'] ?? 1)));

    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        header('Location: ' . $adresse($jetzt, (string) $b['token'], 'panne')); exit;
    }

    $tat = (string) ($_POST['tat'] ?? 'weiter');
    try {
        if ($tat === 'absenden') {
            $ok = Bedarf::absenden((int) $b['id'], [
                'name'    => (string) ($_POST['name'] ?? ''),
                // Im Dashboard steht die Adresse fest: die, deren Link er geoeffnet hat
                'email'   => $imDashboard ? (string) $dashKunde['email'] : (string) ($_POST['email'] ?? ''),
                'telefon' => (string) ($_POST['telefon'] ?? ''),
                'firma'   => (string) ($_POST['firma'] ?? ''),
                'empfehl_code' => $empfehlCode,
                'empfehl_wer'  => (string) ($_POST['empfehl_wer'] ?? ''),
                // Seine Antwort auf die Sprachfrage — nicht die Fassung, in
                // der er gerade zufaellig liest.
                'sprache'      => (string) ($_POST['sprache_wahl'] ?? ''),
                'demo'         => $demo,
                'plan'         => $plan,
                // Aus der Mini-App im Kanal: dieselbe Herkunft wie der Bot
                'herkunft'     => $tg !== '' ? 'telegram' : '',
            ]);
            if ($ok && $tg !== '') {
                try {
                    $tgKid = (int) Db::wert('SELECT customer_id FROM bedarf WHERE id = ?', [(int) $b['id']], 0);
                    if ($tgKid > 0) { Events::protokoll('anfrage_quelle', 'Anfrage kam über die Telegram-Mini-App (' . $tg . ')', $tgKid); }
                } catch (Throwable $e) { /* ein fehlender Vermerk kostet nichts */ }
            }
            if ($ok) { unset($_SESSION['bedarf_demo'], $_SESSION['bedarf_plan']); }
            /* Kam er über einen Partnerlink, oder hat er in „Wer hat uns
               empfohlen?“ einen Partnercode eingetippt? Dann gehört er ab
               jetzt zu diesem Partner. Wirft nie -- ein fehlender Vermerk
               ist nachtragbar, ein verlorener Bedarf nicht. */
            if ($ok && !$demo) {
                require_once __DIR__ . '/app/src/Partner.php';
                $kid = (int) Db::wert('SELECT customer_id FROM bedarf WHERE id = ?', [(int) $b['id']], 0);
                if ($kid > 0) { Partner::ausBesuch($kid, (int) $b['id'], (string) ($_POST['empfehl_wer'] ?? '')); }
            }
            /* Die Dankeseite in SEINER Sprache, nicht in der, in der er
               gelesen hat. Wer gerade "Deutsch" angegeben hat und dann eine
               italienische Bestaetigung sieht, glaubt zu Recht, die Angabe
               sei untergegangen — und die Mail, die gleich kommt, ist ja
               schon deutsch. */
            $zielSprache = strtolower(trim((string) ($_POST['sprache_wahl'] ?? '')));
            if (!in_array($zielSprache, ['it', 'de', 'en'], true)) { $zielSprache = $sprache; }
            /* EIN FRAGEBOGEN (26.09.2026, Uwe: „Die 8 Fragen sollen in den
               großen Fragebogen zusammenlaufen“): Nach dem Richtpreis geht es
               ohne Umweg über das Dashboard im selben Fragebogen weiter, mit
               den Antworten von eben schon eingetragen. Klappt das nicht,
               landet er wie bisher auf seiner Seite -- dort steht derselbe
               Fragebogen als erster Knopf. */
            if ($ok && $imDashboard) {
                try {
                    require_once __DIR__ . '/app/src/Onboarding.php';
                    $fbId = Onboarding::vorab((int) $dashKunde['id']);
                    header('Location: /fragebogen.php?t=' . rawurlencode(Onboarding::token($fbId))
                        . '&lang=' . rawurlencode($zielSprache) . '&m=vorhaben', true, 303);
                    exit;
                } catch (Throwable $e) {
                    try { Events::melden('fragebogen_fehler', 'Fragebogen nach den acht Fragen nicht angelegt', 'schlecht',
                        $e->getMessage(), '/kunden/' . (int) $dashKunde['id']); } catch (Throwable $e2) { /* egal */ }
                }
                header('Location: ' . Kundenzugang::linkFuer((int) $dashKunde['id'], $zielSprache) . '&m=vorhaben', true, 303);
                exit;
            }
            header('Location: /bedarf.php?t=' . rawurlencode((string) $b['token'])
                . '&lang=' . rawurlencode($zielSprache)
                . '&schritt=' . $anzahl
                . '&m=' . ($ok ? 'danke' : 'pflicht') . $tgZusatz); exit;
        }

        // Die Antworten dieses Schritts einsammeln. Ein nicht angekreuztes
        // Mehrfachfeld schickt gar nichts mit — deshalb wird es ausdruecklich
        // als leer uebergeben, sonst bliebe die alte Antwort stehen.
        $neu = [];
        foreach (Baukasten::SCHRITTE[$jetzt - 1] ?? [] as $frage) {
            $art = Baukasten::FRAGEN[$frage]['art'] ?? 'einfach';
            $neu[$frage] = $art === 'mehrfach' ? (array) ($_POST[$frage] ?? []) : (string) ($_POST[$frage] ?? '');
        }
        Bedarf::speichern((int) $b['id'], $neu, $jetzt);

        if ($tat === 'zurueck') { header('Location: ' . $adresse(max(1, $jetzt - 1), (string) $b['token'])); exit; }
        header('Location: ' . $adresse(min($anzahl, $jetzt + 1), (string) $b['token'])); exit;

    } catch (Throwable $e) {
        try {
            Events::melden('bedarf_fehler', 'Bedarf konnte nicht gespeichert werden', 'schlecht', $e->getMessage(), '/anfragen');
        } catch (Throwable $e2) { /* dann eben nicht */ }
        header('Location: ' . $adresse($jetzt, (string) $b['token'], 'panne')); exit;
    }
}

/* ---------- Anzeigen ---------- */
$schritt   = max(1, min($anzahl, (int) ($_GET['schritt'] ?? 1)));
$m         = (string) ($_GET['m'] ?? '');
$antworten = $b ? Bedarf::antworten($b) : [];
$fertig    = $b && $b['status'] !== 'offen';

/* Die Spanne wird erst im letzten Schritt gerechnet — vorher waere sie eine
   Zahl, die sich bei jeder Antwort aendert, und das verunsichert mehr als
   es hilft. */
$spanne = null; $monatlich = 0; $zeigen = true; $genug = true;
if ($b && $schritt === $anzahl) {
    try {
        $genug  = Baukasten::genugGesagt($antworten);
        $zeigen = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'bedarf_spanne_zeigen'", [], '1') === '1'
                  && $genug;
        $r = Baukasten::rechnen($antworten);
        $spanne = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
        $monatlich = (int) $r['monatlich_cents'];
    } catch (Throwable $e) { $spanne = null; }
}

/* Der Live-Richtpreis (28.09.2026, Uwe: Ja zu R1–R3). Vorher stand hier
   bewusst keine Zahl vor dem Ende; Uwe will jetzt, dass jede Antwort den
   Preis sofort zeigt. Ohne Skript rechnet der Server ihn bei jedem Schritt,
   mit Skript bei jedem Klick (richtpreis.php) -- beide über Baukasten::live,
   also dieselbe Rechnung wie Ergebnis und Angebot. Abschaltbar mit derselben
   Einstellung wie die Spanne. */
$live = null; $liveAb = 0;
if ($b && !$fertig && $schritt < $anzahl) {
    try {
        if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'bedarf_spanne_zeigen'", [], '1') === '1') {
            $liveKat = Baukasten::katalog();
            $live = Baukasten::live($antworten, $schritt - 1, $liveKat);
            $liveAb = Baukasten::ab($liveKat);
        }
    } catch (Throwable $e) { $live = null; }
}

/* Wie viele Plaetze zum Einfuehrungspreis noch offen sind. Echte Zahl aus
   voll bezahlten Bestellungen — kein erfundener Countdown. Faellt sie aus,
   steht dort einfach nichts. */
$rest = null; $ziel = 0;
if ($b && $schritt === $anzahl) {
    try {
        if (Einfuehrung::laeuft()) { $rest = Einfuehrung::restplaetze(); $ziel = Einfuehrung::ziel(); }
    } catch (Throwable $e) { $rest = null; }
}

$geld = static function (int $cents) use ($sprache): string {
    // Englisch trennt Tausender mit Komma: €1,500 -- nicht €1.500 (gesehen im Dashboard-Einblick)
    return $sprache === 'en' ? '€' . number_format($cents / 100, 0, '.', ',') : number_format($cents / 100, 0, ',', '.') . ' €';
};
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<?php if ($tg !== ''): ?>
<script src="https://telegram.org/js/telegram-web-app.js?59"></script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .lead{color:var(--dim);font-size:15px;line-height:1.65}
  .bkopf{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--linie)}
  .punkte{display:flex;gap:6px;margin:14px 0 4px;list-style:none;padding:0}
  .punkte li{flex:1 1 0;height:4px;border-radius:2px;background:var(--linie)}
  .punkte li.durch{background:var(--blau)}
  .punkte li.jetzt{background:var(--cyan)}
  /* Die Angaben nach dem Richtpreis: ein Teil desselben Fragebogens */
  .punkte li.angaben{flex-grow:3}
  .zaehler{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .beiseite{color:var(--leise);font-size:12.5px;line-height:1.6;margin-top:10px}

  .frage{margin-bottom:22px}
  .frage:last-child{margin-bottom:0}
  .frage h2{font-size:18px;margin:0 0 4px}
  .frage .hilfe{color:var(--leise);font-size:13px;line-height:1.55;margin:0 0 12px}

  /* Ganze Flaechen statt kleiner Kreise: Auf dem Handy trifft der Daumen
     einen 20-Pixel-Radiobutton nicht zuverlaessig. Hier ist die ganze Zeile
     das Ziel, mindestens 52 Pixel hoch. */
  .wahl{display:grid;gap:8px}
  /* position:relative ist kein Beiwerk: Das Eingabefeld darunter liegt
     absolut. Ohne einen positionierten Elternteil verankert es sich am
     Seitenanfang — und der Browser springt beim Tabben nach oben, statt
     die gerade gewaehlte Zeile zu zeigen. */
  .wahl label{position:relative;display:flex;align-items:center;gap:12px;min-height:52px;
    padding:12px 14px;border:1px solid var(--linie);border-radius:10px;
    cursor:pointer;transition:border-color .15s,background .15s}
  .wahl label:hover{border-color:var(--blau)}
  .wahl input{position:absolute;opacity:0;width:0;height:0}
  .wahl .kaestchen{flex:0 0 20px;width:20px;height:20px;border:2px solid var(--linie);
    border-radius:50%;position:relative;transition:border-color .15s}
  .wahl .kaestchen.eckig{border-radius:5px}
  .wahl input:checked + .kaestchen{border-color:var(--cyan)}
  .wahl input:checked + .kaestchen::after{content:"";position:absolute;inset:3px;
    border-radius:50%;background:var(--cyan)}
  .wahl .kaestchen.eckig::after{border-radius:2px}
  .wahl label:has(input:checked){border-color:var(--cyan);background:rgba(192,136,24,.07)}
  .wahl input:focus-visible + .kaestchen{outline:2px solid var(--cyan);outline-offset:3px}
  .wahl .wort{font-size:15px;line-height:1.4}

  .ergebnis{text-align:center;padding:26px 18px}
  .ergebnis .klein{font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .ergebnis .zahl{font-size:clamp(28px,7vw,40px);font-weight:600;margin:10px 0 6px;line-height:1.15}
  .ergebnis .monat{color:var(--dim);font-size:14px;line-height:1.6;margin:0}
  .ergebnis .erklaerung{color:var(--leise);font-size:13px;line-height:1.65;margin:14px auto 0;max-width:44ch}

  .knapp{margin:16px auto 0;max-width:44ch;padding:10px 14px;border-radius:10px;
    background:rgba(192,136,24,.09);border:1px solid rgba(192,136,24,.28);
    font-size:13.5px;line-height:1.55;color:var(--dim)}
  .knapp span{display:block;margin-top:4px;color:var(--leise);font-size:12.5px}
  .erkannt{margin:4px 0 0;padding:10px 14px;border-radius:10px;
    background:rgba(192,136,24,.09);border:1px solid rgba(192,136,24,.28);
    font-size:13.5px;color:var(--dim)}
  /* Live-Richtpreis: unten angeheftet, damit er bei jedem Klick sichtbar bleibt */
  .livepreis{position:sticky;bottom:0;z-index:5;margin:14px -4px 0;padding:12px 16px calc(12px + env(safe-area-inset-bottom));
    border:1px solid rgba(192,136,24,.45);border-radius:14px 14px 0 0;background:rgba(14,12,9,.96);
    box-shadow:0 -10px 30px rgba(0,0,0,.45);display:grid;gap:2px}
  .livepreis .lp-t{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .livepreis .lp-z{font-size:clamp(22px,6vw,28px);font-weight:600;line-height:1.2;color:var(--text);transition:color .3s}
  .livepreis .lp-z.neu{color:var(--cyan)}
  .livepreis .lp-d{font-size:14px;color:var(--cyan);min-height:1.2em}
  .livepreis .lp-m,.livepreis .lp-h{font-size:12.5px;color:var(--leise);line-height:1.5}
  @media (prefers-reduced-motion:reduce){.livepreis .lp-z{transition:none}}
  .leiste2{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .leiste2 .rechts{margin-left:auto}
  .leiste2 .knopf{flex:0 1 auto;padding-left:26px;padding-right:26px}
  @media (max-width:520px){ .leiste2 .knopf{flex:1 1 auto} .leiste2 .rechts{display:none} }

</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($panne || !$b): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($T($panne ? 'panne' : 'weg')) ?></div>
    <a class="knopf haupt" href="<?= $tg !== '' ? '/telegram-app.php?neu=1&amp;lang=' . $h($sprache) . '&amp;s=' . $h($tg) : '/bedarf.php?lang=' . $h($sprache) ?>"><?= $h($T('neu')) ?></a>
  </div>

<?php elseif ($m === 'danke' || ($fertig && ($schritt === $anzahl || $tg !== ''))): ?>
  <div class="block">
    <div class="hinweis gut"><?= $h($T('danke')) ?></div>
    <?php if ($tg !== ''): ?>
      <button type="button" class="knopf haupt" style="margin-top:12px" data-tg-schliessen><?= $h($T('tgZurueck')) ?></button>
      <?php if ($dashLink !== ''): ?>
        <?php /* Der persönliche Bereich öffnet im Browser, nicht im Fenster: Er ist
                 länger als ein Rechner und gehört nicht in einen fremden Rahmen. */ ?>
        <a class="knopf" style="margin-top:10px" href="<?= $h($dashLink) ?>" target="_blank" rel="noopener" data-tg-extern><?= $h($T('zumDashboard')) ?></a>
      <?php endif; ?>
    <?php else: ?>
    <a class="knopf haupt" style="margin-top:12px" href="<?= $h($zurueck) ?>">Vecom Design</a>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="bkopf">
    <?php if ($imDashboard): ?>
      <a href="<?= $h($dashLink) ?>" style="display:inline-block;margin:0 0 10px;font-size:13.5px;color:var(--dim)"><?= $h($T('zumDashboard')) ?></a>
    <?php endif; ?>
    <?php /* Im Dashboard sind die acht Fragen der Anfang des einen
             Fragebogens (26.09.2026): gleiche Überschrift, ein letzter,
             breiter Balken für die Angaben danach, und gezählt wird in
             Minuten bis zum Ende -- wie im Fragebogen selbst (B6). Ohne
             Dashboard bleibt es beim alten „Schritt n von 8“. */
          $restMin = null;
          if ($imDashboard) {
              try {
                  require_once __DIR__ . '/app/src/Fragen.php';
                  $fbDaten = Bedarf::alsFragebogen((int) $dashKunde['id']);
                  $fbRoh = Db::wert('SELECT data FROM questionnaires WHERE customer_id = ? ORDER BY id DESC LIMIT 1',
                      [(int) $dashKunde['id']], null);
                  if ($fbRoh) { $fbDaten = array_merge($fbDaten, (array) (json_decode((string) $fbRoh, true) ?: [])); }
                  // Die acht Fragen sind Klicks: rund acht Sekunden je Frage
                  $sek = max(0, $anzahl - $schritt) * 8 + Fragen::restMinuten($fbDaten, 1) * 60;
                  $restMin = (int) ceil($sek / 60);
              } catch (Throwable $e) { $restMin = null; }
          } ?>
    <h1 style="font-size:21px;margin:0 0 6px"><?= $h($T($imDashboard ? 'titelEins' : 'titel')) ?></h1>
    <p class="lead" style="margin:0"><?= $h($T($imDashboard ? 'leadEins' : 'lead')) ?></p>
    <ul class="punkte">
      <?php for ($i = 1; $i <= $anzahl; $i++): ?>
        <li class="<?= $i < $schritt ? 'durch' : ($i === $schritt ? 'jetzt' : '') ?>"></li>
      <?php endfor; ?>
      <?php if ($imDashboard): ?><li class="angaben" title="<?= $h(Texte::h(Texte::SEITE['angabenTitel'] ?? [], $sprache)) ?>"></li><?php endif; ?>
    </ul>
    <div class="zaehler"><?= $h($restMin === null
        ? strtr($T('schritt'), ['{n}' => (string) $schritt, '{g}' => (string) $anzahl])
        : ($restMin <= 1 ? Texte::h(Texte::SEITE['nochEineMin'] ?? [], $sprache)
            : strtr(Texte::h(Texte::SEITE['nochMin'] ?? [], $sprache), ['{m}' => (string) $restMin]))) ?></div>
  </div>

  <?php if ($m === 'panne'): ?><div class="hinweis schlecht"><?= $h($T('panne')) ?></div><?php endif; ?>
  <?php if ($m === 'pflicht'): ?><div class="hinweis schlecht"><?= $h($T('pflicht')) ?></div><?php endif; ?>
  <?php if ($demoText !== '' && ($schritt === 1 || $schritt === $anzahl)): ?>
    <p class="erkannt"><?= $h(strtr($T('demoErkannt'), ['{wahl}' => $demoText])) ?></p>
  <?php endif; ?>

  <form method="post" action="/bedarf.php?t=<?= $h(rawurlencode((string) $b['token'])) ?>&amp;lang=<?= $h($sprache) ?><?= $h($tgZusatz) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
    <input type="hidden" name="schritt" value="<?= (int) $schritt ?>">

    <?php if ($schritt < $anzahl): ?>
      <div class="block">
        <?php foreach (Baukasten::SCHRITTE[$schritt - 1] as $name): ?>
          <?php $f = Baukasten::FRAGEN[$name]; $mehrfach = ($f['art'] ?? 'einfach') === 'mehrfach'; ?>
          <?php $gewaehlt = $antworten[$name] ?? ($mehrfach ? [] : ''); ?>
          <fieldset class="frage" style="border:0;padding:0;margin-inline:0">
            <legend style="padding:0"><h2><?= $h(Texte::h($f['frage'], $sprache)) ?></h2></legend>
            <?php if (!empty($f['hilfe'])): ?>
              <p class="hilfe"><?= $h(Texte::h($f['hilfe'], $sprache)) ?></p>
            <?php endif; ?>
            <div class="wahl">
              <?php foreach ($f['optionen'] as $wert => $text): ?>
                <?php
                  $wert = (string) $wert;
                  $an = $mehrfach ? in_array($wert, (array) $gewaehlt, true) : ((string) $gewaehlt === $wert);
                  $id = 'o_' . $h($name) . '_' . $h($wert);
                ?>
                <label for="<?= $id ?>">
                  <input type="<?= $mehrfach ? 'checkbox' : 'radio' ?>" id="<?= $id ?>"
                         name="<?= $h($name) ?><?= $mehrfach ? '[]' : '' ?>"
                         value="<?= $h($wert) ?>"<?= $an ? ' checked' : '' ?>>
                  <span class="kaestchen<?= $mehrfach ? ' eckig' : '' ?>"></span>
                  <span class="wort"><?= $h(Texte::h($text, $sprache)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <?php if (!$genug): ?>
        <div class="block">
          <div class="hinweis warnung"><?= $h($T('nichts')) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($spanne && $zeigen): ?>
        <div class="block ergebnis">
          <div class="klein"><?= $h($T('ergebnisTitel')) ?></div>
          <div class="zahl"><?= $h($geld($spanne['von_cents'])) ?> – <?= $h($geld($spanne['bis_cents'])) ?></div>
          <?php if ($monatlich > 0): ?>
            <p class="monat"><?= $h(strtr($T('ergebnisMonat'), ['{betrag}' => $geld($monatlich)])) ?></p>
          <?php endif; ?>
          <p class="erklaerung"><?= $h($T($imDashboard ? 'ergebnisTextEins' : 'ergebnisText')) ?></p>
          <?php if ($rest !== null && $rest > 0): ?>
            <p class="knapp">
              <?= $h(strtr($T('knappheit'), ['{n}' => (string) $rest, '{g}' => (string) $ziel])) ?>
              <span><?= $h(strtr($T('knappheitHilfe'), ['{g}' => (string) $ziel])) ?></span>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="block">
        <h2 style="font-size:18px;margin:0 0 14px"><?= $h($T($imDashboard ? 'kontaktTitelDashboard' : 'kontaktTitel')) ?></h2>
        <div class="feld">
          <label for="f_name"><?= $h($T('fName')) ?> *</label>
          <input id="f_name" name="name" autocomplete="name" required
                 value="<?= $h((string) (($b['name'] ?? '') !== '' ? $b['name'] : ($dashKunde['name'] ?? ''))) ?>">
        </div>
        <?php if ($imDashboard): ?>
          <?php /* D2: Die Adresse ist bewiesen -- er hat den Link aus diesem
                   Postfach geoeffnet. Sie steht da, zum Nachlesen, nicht zum
                   Aendern: Eine hier getippte andere Adresse fuehrte in eine
                   zweite Kundenakte. */ ?>
          <p class="erkannt" style="margin:0 0 14px"><?= $h($T('emailFest')) ?> <b><?= $h((string) $dashKunde['email']) ?></b></p>
        <?php else: ?>
        <div class="feld">
          <label for="f_email"><?= $h($T('fEmail')) ?> *</label>
          <input id="f_email" name="email" type="email" autocomplete="email" required
                 value="<?= $h((string) ($b['email'] ?? '')) ?>">
        </div>
        <?php endif; ?>
        <div class="feld">
          <label for="f_telefon"><?= $h($T('fTelefon')) ?></label>
          <input id="f_telefon" name="telefon" type="tel" autocomplete="tel"
                 value="<?= $h((string) ($b['telefon'] ?? '')) ?>">
        </div>
        <div class="feld">
          <label for="f_firma"><?= $h($T('fFirma')) ?></label>
          <input id="f_firma" name="firma" autocomplete="organization"
                 value="<?= $h((string) ($b['firma'] ?? '')) ?>">
        </div>
        <?php /* ----------------------------------------------------------
             Die Sprache als Antwort, nicht als Nebenwirkung.

             Unten steht ein Umschalter, aber der aendert nur die Ansicht --
             und weil jeder Verweis auf diese Seite fest "lang=it" trug, hat
             ihn kaum jemand je gebraucht. Was dabei herauskam, entschied
             danach ueber jede Mail, jeden Beleg und die ganze Kundenseite.

             Hier steht die Frage jetzt da, wo die Kontaktdaten stehen, mit
             der aktuellen Fassung als Vorauswahl. Wer sie stehen laesst, hat
             sie trotzdem gesehen -- und das ist der Unterschied zu vorher.
             ---------------------------------------------------------- */ ?>
        <div class="feld">
          <label for="f_sprache"><?= $h($T('fSprache')) ?> *</label>
          <select id="f_sprache" name="sprache_wahl" required>
            <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $sl => $wort): ?>
              <option value="<?= $sl ?>" <?= $sprache === $sl ? 'selected' : '' ?>><?= $h($wort) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="beiseite" style="margin-top:6px"><?= $h($T('fSpracheHilfe')) ?></p>
        </div>
        <?php if ($empfehlName !== ''): ?>
          <p class="erkannt"><?= $h(strtr($T('empfehlungErkannt'), ['{name}' => $empfehlName])) ?></p>
        <?php else: ?>
          <div class="feld">
            <label for="f_empf"><?= $h($T('fEmpfehlung')) ?></label>
            <input id="f_empf" name="empfehl_wer"
                   value="<?= $h((string) ($b['empfehl_wer'] ?? '')) ?>">
            <p class="beiseite" style="margin-top:6px"><?= $h($T('empfehlungHilfe')) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="block">
      <div class="leiste2">
        <?php if ($schritt > 1): ?>
          <button class="knopf" name="tat" value="zurueck"><?= $h($T('zurueck')) ?></button>
        <?php endif; ?>
        <span class="rechts"></span>
        <?php if ($schritt < $anzahl): ?>
          <button class="knopf haupt" name="tat" value="weiter"><?= $h($T('weiter')) ?></button>
        <?php else: ?>
          <button class="knopf haupt" name="tat" value="absenden"><?= $h($T($imDashboard ? 'absendenDashboard' : 'absenden')) ?></button>
        <?php endif; ?>
      </div>
      <p class="beiseite"><?= $h($T('autoOk')) ?></p>
    </div>
    <?php if ($live !== null): $leer = $schritt === 1 && empty($antworten['zweck']); ?>
      <div class="livepreis" id="livepreis" role="status" aria-live="polite">
        <span class="lp-t"><?= $h($T('liveTitel')) ?></span>
        <span class="lp-z" id="lp_zahl"><?= $h($leer ? strtr($T('liveAb'), ['{betrag}' => Baukasten::geldText($liveAb, $sprache)])
            : Baukasten::geldText($live['von_cents'], $sprache) . ' – ' . Baukasten::geldText($live['bis_cents'], $sprache)) ?></span>
        <span class="lp-d" id="lp_delta"><?= $leer ? $h($T('liveStart')) : '' ?></span>
        <span class="lp-m" id="lp_monat"><?= $live['monatlich_cents'] > 0 ? $h(strtr($T('liveMonat'), ['{betrag}' => Baukasten::geldText($live['monatlich_cents'], $sprache)])) : '' ?></span>
        <span class="lp-h"><?= $h($T('liveHinweis')) ?></span>
      </div>
      <script type="application/json" id="livepreis_daten"><?= json_encode([
          'antworten' => (object) $antworten, 'schritt' => $schritt, 'lang' => $sprache, 'von' => $leer ? $liveAb : $live['von_cents'], 'monat' => $live['monatlich_cents'],
          'fragen' => array_values(Baukasten::SCHRITTE[$schritt - 1] ?? []),
          't' => ['plus' => $T('livePlus'), 'minus' => $T('liveMinus'), 'gleich' => $T('liveGleich'), 'offen' => $T('liveOffen'), 'monat' => $T('liveMonatPlus'), 'monatWeg' => $T('liveMonatWeg')],
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      <script src="/assets/js/richtpreis-live.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/richtpreis-live.js') ?>" defer></script>
    <?php endif; ?>
  </form>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="/bedarf.php?t=<?= $h(rawurlencode((string) $b['token'])) ?>&amp;lang=<?= $l ?>&amp;schritt=<?= (int) $schritt ?><?= $h($tgZusatz) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<?php /* Impressum, Datenschutz und AGB — auch unter den Seiten, die man nur
         mit Schluessel erreicht. Sie waren bisher nur auf den oeffentlichen
         Seiten zu finden, obwohl der Kunde hier entscheidet. */ ?>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
<?php if ($tg !== ''): ?>
<script>
/* Mini-App: Telegram sagen, dass die Seite steht, das Fenster ganz öffnen,
   Farben passend zur Seite. Den Schlüssel im Gerät merken, solange der
   Rechner offen ist — beim nächsten Öffnen geht es hier weiter
   (telegram-app.php); nach dem Absenden vergessen. Die Startdaten von
   Telegram (hinter dem #) werden nicht gelesen und nicht verschickt. */
(function () {
  var w = window.Telegram && window.Telegram.WebApp;
  if (w) {
    try { w.ready(); w.expand(); w.setHeaderColor('#0e0c09'); w.setBackgroundColor('#0e0c09'); } catch (e) { }
  }
  try {
    <?php if ($b && !$fertig && $m !== 'danke'): ?>localStorage.setItem('vd_tg_bedarf', <?= json_encode((string) $b['token']) ?>);
    <?php else: ?>localStorage.removeItem('vd_tg_bedarf');
    <?php endif; ?>
  } catch (e) { }
  var k = document.querySelector('[data-tg-schliessen]');
  if (k) { k.addEventListener('click', function () { if (w) { w.close(); } else { history.back(); } }); }
  // Telegrams eigener Zurück-Pfeil oben im Fenster: ab Schritt 2 derselbe
  // Weg wie der Knopf „Zurück“ unten (speichert, dann ein Schritt zurück).
  var zur = document.querySelector('button[name=tat][value=zurueck]');
  if (w && w.BackButton) {
    try {
      if (zur) { w.BackButton.onClick(function () { zur.click(); }); w.BackButton.show(); }
      else { w.BackButton.hide(); }
    } catch (e) { }
  }
  var x = document.querySelector('[data-tg-extern]');
  if (x && w && w.openLink) { x.addEventListener('click', function (e) { e.preventDefault(); w.openLink(x.href); }); }
})();
</script>
<?php endif; ?>
</body>
</html>
