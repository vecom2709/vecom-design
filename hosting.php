<?php
declare(strict_types=1);
/* ==========================================================================
   hosting.php — Domain & Hosting als DIREKTKAUF, ohne Website-Auftrag.

   DER WEG IN DREI SCHRITTEN

   1. Der Besucher nennt Name, E-Mail und seine Wunschdomain.
   2. Die Domain wird sofort geprueft. Ist sie frei, sieht er sie mit Preis
      und den Bedingungen — und schliesst mit einem Klick verbindlich ab
      ("zahlungspflichtig bestellen", Widerruf und AGB bestaetigt).
   3. Vertrag und erste Rate entstehen sofort; die Domain steht ab da direkt
      in der Verwaltung. Uwe muss nichts anbieten. Angelegt (KAS-Account,
      Domain, Postfach) wird, sobald die erste Monatsrate bezahlt ist.

   WARUM DIE PRUEFUNG ERST BEIM ABSENDEN

   Die Domainpruefung fragt fremde Dienste (RDAP, Whois) auf unsere Rechnung
   und IP. Sie laeuft deshalb NICHT bei jedem Tastendruck, sondern einmal je
   Absenden — gebremst durch eine IP-Sperre und die Sitzungsgrenze der
   Domainpruefung. Beim verbindlichen Kauf wird ein zweites Mal geprueft,
   damit kein manipuliertes Formular eine vergebene Domain durchdrueckt.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Domainpruefung.php';
require_once __DIR__ . '/app/src/Widerruf.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomhosting');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($_COOKIE['vecomlang'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

/* Alle Saetze der Seite — dreisprachig, an einem Ort. */
$W = [
  'it' => [
    'titel'   => 'Dominio & Hosting',
    'lead'    => 'Il tuo dominio con spazio web, certificato SSL e casella e-mail — su un account tuo, con i tuoi dati di accesso. Senza sito, se non ti serve.',
    'preis'   => '{preis} al mese · 12 mesi di durata minima, poi disdici a fine mese',
    'drin'    => 'Che cosa è compreso',
    'punkte'  => ['Il tuo dominio (.it, .de, .com, .eu …) — registrato e gestito da noi',
                  '10 GB di spazio web con accesso FTP',
                  'Certificato SSL incluso — si rinnova da solo',
                  'Casella e-mail info@tuodominio — altre le crei tu',
                  'Account proprio con i TUOI dati di accesso'],
    'name'    => 'Nome e cognome', 'email' => 'E-mail',
    'domain'  => 'Il dominio che vorresti', 'domainBsp' => 'es. trattoria-rossi.it',
    'pruefen' => 'Verifica disponibilità',
    'ablauf'  => 'Controlliamo subito se il dominio è libero. Se lo è, lo attivi con un clic — nessun vincolo prima della tua conferma.',
    'frei'    => '{domain} è libero!',
    'freiSub' => 'Ottimo — puoi attivarlo adesso.',
    'vergeben'=> '{domain} è già occupato. Prova con un altro nome qui sotto.',
    'unklar'  => 'Non sono riuscito a verificare {domain} con certezza. Riprova tra poco o scegli un altro nome.',
    'zuSchnell'=> 'Un attimo — riprova tra qualche secondo.',
    'ungueltig'=> 'Questo non sembra un nome di dominio valido. Esempio: trattoria-rossi.it',
    'kaufTitel'=> 'Attiva {domain}',
    'kaufKnopf'=> 'Ordino con obbligo di pagare — {preis} al mese',
    'nochmal' => 'Prova un altro nome',
    'fehlerFelder' => 'Controlla nome e indirizzo e-mail.',
    'fehlerZust'   => 'Per attivare servono entrambe le conferme.',
    'dankeT'  => 'Fatto! {domain} è tuo.',
    'danke'   => 'Grazie! Ti abbiamo mandato a {email} la conferma del contratto e la prima fattura ({preis}). Appena arriva il pagamento attiviamo tutto e i tuoi dati di accesso compaiono sulla tua pagina personale.',
    'zurSeite'=> 'Vai alla tua pagina',
    'schon'   => 'Hai già un dominio in corso con noi. Ti abbiamo scritto a {email}; tutto il resto è sulla tua pagina personale.',
    'zurueck' => 'Torna al sito',
  ],
  'de' => [
    'titel'   => 'Domain & Hosting',
    'lead'    => 'Deine Wunschdomain mit Speicherplatz, SSL-Zertifikat und E-Mail-Postfach — auf einem eigenen Account, mit deinen eigenen Zugangsdaten. Ganz ohne Website, wenn du keine brauchst.',
    'preis'   => '{preis} im Monat · 12 Monate Mindestlaufzeit, danach zum Monatsende kündbar',
    'drin'    => 'Was drinsteckt',
    'punkte'  => ['Deine Wunschdomain (.it, .de, .com, .eu …) — von uns registriert und betreut',
                  '10 GB Speicherplatz mit FTP-Zugang',
                  'SSL-Zertifikat inklusive — verlängert sich von selbst',
                  'E-Mail-Postfach info@deine-domain — weitere legst du selbst an',
                  'Eigener Account mit DEINEN Zugangsdaten'],
    'name'    => 'Vor- und Nachname', 'email' => 'E-Mail',
    'domain'  => 'Deine Wunschdomain', 'domainBsp' => 'z. B. trattoria-rossi.it',
    'pruefen' => 'Verfügbarkeit prüfen',
    'ablauf'  => 'Wir prüfen sofort, ob die Domain frei ist. Ist sie frei, schaltest du sie mit einem Klick — vor deiner Bestätigung bindet dich nichts.',
    'frei'    => '{domain} ist frei!',
    'freiSub' => 'Sehr gut — du kannst sie jetzt aktivieren.',
    'vergeben'=> '{domain} ist schon vergeben. Versuch unten einen anderen Namen.',
    'unklar'  => 'Ich konnte {domain} nicht sicher prüfen. Versuch es gleich noch einmal oder wähle einen anderen Namen.',
    'zuSchnell'=> 'Einen Moment — bitte in ein paar Sekunden noch einmal versuchen.',
    'ungueltig'=> 'Das sieht nicht nach einer gültigen Domain aus. Beispiel: trattoria-rossi.it',
    'kaufTitel'=> '{domain} aktivieren',
    'kaufKnopf'=> 'Zahlungspflichtig bestellen — {preis} im Monat',
    'nochmal' => 'Anderen Namen versuchen',
    'fehlerFelder' => 'Bitte Name und E-Mail-Adresse prüfen.',
    'fehlerZust'   => 'Zum Aktivieren werden beide Bestätigungen gebraucht.',
    'dankeT'  => 'Geschafft! {domain} gehört dir.',
    'danke'   => 'Danke! Wir haben dir an {email} die Vertragsbestätigung und die erste Rechnung ({preis}) geschickt. Sobald die Zahlung da ist, schalten wir alles, und deine Zugangsdaten erscheinen auf deiner persönlichen Seite.',
    'zurSeite'=> 'Zu deiner Seite',
    'schon'   => 'Du hast schon eine Domain bei uns in Arbeit. Wir haben dir an {email} geschrieben; alles Weitere steht auf deiner persönlichen Seite.',
    'zurueck' => 'Zurück zur Website',
  ],
  'en' => [
    'titel'   => 'Domain & hosting',
    'lead'    => 'Your domain with web space, SSL certificate and email mailbox — on your own account, with your own access details. No website needed.',
    'preis'   => '{preis} per month · 12-month minimum term, then cancel at month’s end',
    'drin'    => 'What’s included',
    'punkte'  => ['Your domain (.it, .de, .com, .eu …) — registered and managed by us',
                  '10 GB of web space with FTP access',
                  'SSL certificate included — renews itself',
                  'Email mailbox info@yourdomain — create more yourself',
                  'Your own account with YOUR access details'],
    'name'    => 'Full name', 'email' => 'Email',
    'domain'  => 'The domain you’d like', 'domainBsp' => 'e.g. trattoria-rossi.com',
    'pruefen' => 'Check availability',
    'ablauf'  => 'We check right away whether the domain is free. If it is, you activate it with one click — nothing binds you before you confirm.',
    'frei'    => '{domain} is available!',
    'freiSub' => 'Great — you can activate it now.',
    'vergeben'=> '{domain} is already taken. Try another name below.',
    'unklar'  => 'I couldn’t verify {domain} for certain. Try again shortly or pick another name.',
    'zuSchnell'=> 'One moment — please try again in a few seconds.',
    'ungueltig'=> 'That doesn’t look like a valid domain. Example: trattoria-rossi.com',
    'kaufTitel'=> 'Activate {domain}',
    'kaufKnopf'=> 'Order with obligation to pay — {preis} per month',
    'nochmal' => 'Try another name',
    'fehlerFelder' => 'Please check your name and email address.',
    'fehlerZust'   => 'Both confirmations are needed to activate.',
    'dankeT'  => 'Done! {domain} is yours.',
    'danke'   => 'Thank you! We’ve sent your contract confirmation and first invoice ({preis}) to {email}. As soon as the payment arrives we set everything up, and your access details appear on your personal page.',
    'zurSeite'=> 'Go to your page',
    'schon'   => 'You already have a domain in progress with us. We’ve written to {email}; everything else is on your personal page.',
    'zurueck' => 'Back to the website',
  ],
][$sprache];

$W = Widerruf::texte($sprache) + $W;   // 'agb'/'wid'/'widText' — echte Werte gewinnen; derselbe Wortlaut wie im Vertragsblatt

$preisCents = (int) (Db::wert("SELECT monthly_cents FROM packages WHERE slug = 'hosting'", [], 990) ?: 990);
$preisText  = Fmt::geld($preisCents);
$basis      = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

/* KANN DER KUNDE ÜBERHAUPT BEZAHLEN?
   ----------------------------------------------------------------------
   Ein Direktkauf ohne Zahlungsweg ist ein Schein-Kauf: Der Kunde schließt
   einen Vertrag ab, kann aber nicht zahlen, und bekommt trotzdem Zugang.
   Genau das soll NICHT passieren, solange nichts eingerichtet ist. Also:
   Der verbindliche Kauf ist nur möglich, wenn wenigstens ein Zahlungsweg
   steht — Stripe live ODER eine IBAN für die Überweisung. Fehlt beides,
   nimmt die Seite die Wunschdomain nur als VORMERKUNG entgegen, ohne
   Vertrag und ohne Kundenseite. Sobald ein Weg da ist, wird von selbst
   wieder verkauft. */
$zahlungMoeglich = false;
try {
    require_once __DIR__ . '/app/src/Zahlung/Anbieter.php';
    require_once __DIR__ . '/app/src/Zahlung/Stripe.php';
    require_once __DIR__ . '/app/src/Firma.php';
    $stAnb = new StripeAnbieter();
    // Live zählt immer; der Testmodus nur, wenn der Test-Schalter an ist —
    // dieselbe Regel wie bei der Website-Direktbuchung (buchen.php).
    $testSichtbar = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'direktkauf_test'", [], '0') === '1';
    $stripeKassiert = $stAnb->bereit() && $stAnb->webhookBereit()
        && ($stAnb->modus() === 'live' || $testSichtbar);
    $ueberweisung = Firma::get('iban') !== '';
    $zahlungMoeglich = $stripeKassiert || $ueberweisung;
} catch (Throwable $e) { $zahlungMoeglich = false; }

/* Die Vormerk-Texte — nur gebraucht, solange noch nicht bezahlt werden kann. */
$W += [
  'it' => [
    'vormerkHinweis' => 'Il pagamento online lo stiamo attivando in questi giorni. Intanto puoi prenotare il tuo dominio: te lo teniamo da parte e ti scriviamo appena si può pagare.',
    'vormerkKnopf'   => 'Prenota questo dominio',
    'vormerkDankeT'  => '{domain} è prenotato per te!',
    'vormerkDanke'   => 'Grazie! Ti abbiamo scritto a {email}. Ti teniamo da parte {domain} e ti contattiamo appena il pagamento è attivo — senza alcun impegno per te.',
  ],
  'de' => [
    'vormerkHinweis' => 'Das Bezahlen online richten wir gerade ein. Du kannst dir deine Domain schon vormerken lassen: Wir halten sie für dich frei und melden uns, sobald du bezahlen kannst.',
    'vormerkKnopf'   => 'Diese Domain vormerken',
    'vormerkDankeT'  => '{domain} ist für dich vorgemerkt!',
    'vormerkDanke'   => 'Danke! Wir haben dir an {email} geschrieben. Wir halten {domain} für dich frei und melden uns, sobald das Bezahlen bereitsteht — ganz unverbindlich für dich.',
  ],
  'en' => [
    'vormerkHinweis' => 'We’re just setting up online payment. In the meantime you can reserve your domain: we’ll hold it for you and write as soon as you can pay.',
    'vormerkKnopf'   => 'Reserve this domain',
    'vormerkDankeT'  => '{domain} is reserved for you!',
    'vormerkDanke'   => 'Thank you! We’ve written to {email}. We’ll hold {domain} for you and get in touch as soon as payment is ready — with no obligation on your side.',
  ],
][$sprache];

/* ---------- Zustand: Formular · Bestätigung · Danke ---------- */
$ansicht = 'formular';                 // formular | bestaetigen | danke
$fehler  = [];
$name = ''; $email = ''; $wunsch = '';
$dankeDomain = ''; $dankeSeite = ''; $dankeSchon = false; $dankeVormerk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sauber = static function ($v, int $max): string {
        $v = is_string($v) ? trim($v) : '';
        return mb_substr(str_replace(["\r", "\0"], '', $v), 0, $max);
    };
    $name   = $sauber($_POST['name'] ?? '', 120);
    $email  = mb_strtolower($sauber($_POST['email'] ?? '', 160));
    $wunsch = mb_strtolower($sauber($_POST['wunschdomain'] ?? '', 190));
    $tat    = (string) ($_POST['tat'] ?? 'pruefen');

    if (!empty($_POST['website'])) { header('Location: /'); exit; }   // Honigtopf

    $csrfOk = !empty($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''));
    // Eine Anfrage je IP alle 15 Sekunden — die Domainpruefung fragt fremde Dienste.
    $sperre = sys_get_temp_dir() . '/vecomhost_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $zuSchnell = is_file($sperre) && (time() - (int) filemtime($sperre)) < 15;

    if (!$csrfOk) {
        $fehler[] = $W['fehlerFelder'];
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler[] = $W['fehlerFelder'];
    } elseif ($zuSchnell) {
        $fehler[] = $W['zuSchnell'];   // die IP-Bremse — noch keine Domain geprueft
    } else {
        @touch($sperre);
        require_once __DIR__ . '/app/src/Hosting.php';

        if ($tat === 'vormerken' || ($tat === 'kaufen' && !$zahlungMoeglich)) {
            /* VORMERKUNG — solange noch nicht bezahlt werden kann.
               Kein Vertrag, keine Kundenseite: nur eine Anfrage, die Uwe
               weiterführt, sobald ein Zahlungsweg steht. Die Domain wird
               nochmal geprüft, damit keine vergebene vorgemerkt wird. */
            $domain = Domainpruefung::normalisieren($wunsch);
            $stand  = $domain !== null ? (string) (Domainpruefung::pruefen($domain)['stand'] ?? '') : 'ungueltig';
            if ($domain === null) {
                $fehler[] = $W['ungueltig'];
            } elseif ($stand !== 'frei') {
                $fehler[] = strtr($W[$stand === 'vergeben' ? 'vergeben' : 'unklar'], ['{domain}' => $domain]);
            } else {
                try {
                    require_once __DIR__ . '/app/src/Anfrage.php';
                    Anfrage::annehmen([
                        'name' => $name, 'email' => $email, 'paket' => 'hosting',
                        'sprache' => $sprache, 'sprache_gefragt' => true,
                        'nachricht' => "Domain & Hosting vorgemerkt (Zahlung noch nicht möglich).\nWunschdomain: " . $domain,
                    ]);
                } catch (Throwable $e) { /* der Kontakt zählt, der Rest steht in der Verwaltung */ }
                $ansicht      = 'danke';
                $dankeVormerk = true;
                $dankeDomain  = $domain;
            }

        } elseif ($tat === 'kaufen') {
            /* Der verbindliche Abschluss. Beide Bestaetigungen sind Pflicht,
               und die Domain wird im Kauf ein zweites Mal geprueft. */
            $agbOk = !empty($_POST['agb']);
            $widOk = !empty($_POST['widerruf']);
            if (!$agbOk || !$widOk) {
                $fehler[] = $W['fehlerZust'];
                $ansicht  = 'bestaetigen';
                $wunsch   = (string) Domainpruefung::normalisieren($wunsch);
            } else {
                $erg = Hosting::direktKauf($name, $email, $wunsch, $sprache);
                if (!empty($erg['ok'])) {
                    $ansicht     = 'danke';
                    $dankeDomain = (string) ($erg['domain'] ?? $wunsch);
                    $dankeSchon  = ($erg['grund'] ?? '') === 'schon';
                    try {
                        require_once __DIR__ . '/app/src/Kundenzugang.php';
                        $dankeSeite = Kundenzugang::linkFuer((int) ($erg['kunde_id'] ?? 0));
                    } catch (Throwable $e) { $dankeSeite = ''; }
                } else {
                    // Zwischen Prüfen und Kaufen vergriffen, oder Manipulation.
                    $grund = (string) ($erg['grund'] ?? 'unklar');
                    $key = in_array($grund, ['vergeben', 'unklar', 'ungueltig', 'daten'], true)
                        ? ($grund === 'daten' ? 'fehlerFelder' : $grund) : 'unklar';
                    $fehler[] = strtr($W[$key], ['{domain}' => (string) ($erg['domain'] ?? $wunsch)]);
                    $ansicht  = 'formular';
                }
            }
        } else {
            /* Nur pruefen — noch kein Kauf. */
            $domain = Domainpruefung::normalisieren($wunsch);
            if ($domain === null) {
                $fehler[] = $W['ungueltig'];
            } else {
                $erg   = Domainpruefung::pruefen($domain);
                $stand = (string) ($erg['stand'] ?? '');
                if ($stand === 'frei') {
                    $ansicht = 'bestaetigen';
                    $wunsch  = $domain;
                } elseif ($stand === 'vergeben') {
                    $fehler[] = strtr($W['vergeben'], ['{domain}' => $domain]);
                } else {
                    $fehler[] = strtr($W['unklar'], ['{domain}' => $domain]);
                }
            }
        }
    }
}

$preisZeile = strtr($W['preis'], ['{preis}' => $preisText]);
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($W['titel']) ?> — Vecom Design</title>
<meta name="description" content="<?= $h($W['lead']) ?>">
<link rel="stylesheet" href="/app/assets/admin.css">
<style>
  body{display:flex;align-items:center;justify-content:center;padding:24px}
  .karte{width:100%;max-width:480px}
  .wortmarke{display:flex;justify-content:center;align-items:center;gap:2px;
    font-weight:700;letter-spacing:.02em;font-size:18px;margin-bottom:14px}
  .wortmarke b{background:linear-gradient(135deg,var(--blau),var(--cyan));
    -webkit-background-clip:text;background-clip:text;color:transparent}
  .preiszeile{margin:2px 0 14px;font-size:14px;color:var(--dim)}
  .drin{list-style:none;margin:0 0 18px;padding:0;display:grid;gap:7px}
  .drin li{display:grid;grid-template-columns:18px 1fr;gap:8px;font-size:13.5px;
    color:var(--dim);line-height:1.5}
  .drin li::before{content:'✓';color:var(--cyan);font-weight:700}
  .hin{font-size:12.5px;color:var(--leise);line-height:1.55;margin:14px 0 0}
  .freimeld{border:1px solid var(--cyan);border-radius:12px;padding:14px 16px;margin:0 0 16px;
    background:rgba(31,232,255,.06)}
  .freimeld b{font-size:17px}
  .zust{display:grid;grid-template-columns:22px 1fr;gap:10px;align-items:start;
    margin:12px 0;font-size:12.5px;line-height:1.5;color:var(--dim);cursor:pointer}
  .zust input{width:20px;height:20px;margin:1px 0 0;accent-color:var(--blau);cursor:pointer}
  .zust a{color:var(--cyan)}
  .widerruf{margin:0 0 16px;font-size:12.5px;color:var(--leise)}
  .widerruf summary{cursor:pointer;min-height:40px;display:flex;align-items:center;color:var(--dim)}
  .widerruf p{margin:6px 0 0;line-height:1.55}
</style>
</head>
<body>
<div class="karte">
  <div class="wortmarke"><b>VECOM</b>&nbsp;DESIGN</div>

  <?php /* ---------- DANKE: Kauf abgeschlossen ODER vorgemerkt ---------- */ ?>
  <?php if ($ansicht === 'danke'): ?>
    <?php if ($dankeVormerk): ?>
      <div class="freimeld"><b>✓ <?= $h(strtr($W['vormerkDankeT'], ['{domain}' => $dankeDomain])) ?></b></div>
      <p style="color:var(--dim);line-height:1.6"><?= $h(strtr($W['vormerkDanke'],
          ['{domain}' => $dankeDomain, '{email}' => $email])) ?></p>
      <p style="margin-top:18px">
        <a class="knopf" href="/<?= $sprache === 'it' ? '' : $sprache . '/' ?>"><?= $h($W['zurueck']) ?></a></p>
    <?php else: ?>
      <div class="freimeld"><b>✓ <?= $h(strtr($W['dankeT'], ['{domain}' => $dankeDomain])) ?></b></div>
      <p style="color:var(--dim);line-height:1.6"><?= $h(strtr($dankeSchon ? $W['schon'] : $W['danke'],
          ['{email}' => $email, '{preis}' => $preisText])) ?></p>
      <p style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($dankeSeite !== ''): ?>
          <a class="knopf haupt" href="<?= $h($dankeSeite) ?>"><?= $h($W['zurSeite']) ?></a>
        <?php endif; ?>
        <a class="knopf" href="/<?= $sprache === 'it' ? '' : $sprache . '/' ?>"><?= $h($W['zurueck']) ?></a>
      </p>
    <?php endif; ?>

  <?php /* ---------- BESTÄTIGEN: Domain frei, jetzt verbindlich aktivieren ---------- */ ?>
  <?php elseif ($ansicht === 'bestaetigen'): ?>
    <div class="freimeld">
      <b>✓ <?= $h(strtr($W['frei'], ['{domain}' => $wunsch])) ?></b><br>
      <span style="color:var(--dim);font-size:13.5px"><?= $h($W['freiSub']) ?></span>
    </div>
    <h1 style="font-size:20px;margin:0 0 6px"><?= $h(strtr($W['kaufTitel'], ['{domain}' => $wunsch])) ?></h1>
    <p class="preiszeile"><b><?= $h($preisZeile) ?></b></p>

    <?php foreach ($fehler as $x): ?><div class="hinweis schlecht"><?= $h($x) ?></div><?php endforeach; ?>

    <?php if ($zahlungMoeglich): ?>
    <?php /* Bezahlweg steht — verbindlicher Kauf mit Widerruf und AGB. */ ?>
    <form method="post" action="/hosting.php?lang=<?= $h($sprache) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="kaufen">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
             style="position:absolute;left:-9999px" aria-hidden="true">
      <input type="hidden" name="name" value="<?= $h($name) ?>">
      <input type="hidden" name="email" value="<?= $h($email) ?>">
      <input type="hidden" name="wunschdomain" value="<?= $h($wunsch) ?>">

      <label class="zust">
        <input type="checkbox" name="widerruf" value="1" required <?= !empty($_POST['widerruf']) ? 'checked' : '' ?>>
        <span><?= $h($W['wid']) ?></span>
      </label>
      <label class="zust">
        <input type="checkbox" name="agb" value="1" required <?= !empty($_POST['agb']) ? 'checked' : '' ?>>
        <span><?= $W['agb'] /* enthaelt bewusst zwei Links */ ?></span>
      </label>

      <details class="widerruf">
        <summary><?= $h($W['widTitel'] ?? 'Widerrufsrecht') ?></summary>
        <p><?= $h($W['widText'] ?? '') ?></p>
      </details>

      <button class="knopf haupt" style="width:100%;margin-top:6px"><?= $h(strtr($W['kaufKnopf'], ['{preis}' => $preisText])) ?></button>
    </form>
    <?php else: ?>
    <?php /* Noch kein Bezahlweg (weder Stripe noch IBAN): kein verbindlicher
             Kauf, nur eine Vormerkung — kein Vertrag, keine Kundenseite. */ ?>
      <p class="hin" style="margin:0 0 14px"><?= $h($W['vormerkHinweis']) ?></p>
      <form method="post" action="/hosting.php?lang=<?= $h($sprache) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="vormerken">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
               style="position:absolute;left:-9999px" aria-hidden="true">
        <input type="hidden" name="name" value="<?= $h($name) ?>">
        <input type="hidden" name="email" value="<?= $h($email) ?>">
        <input type="hidden" name="wunschdomain" value="<?= $h($wunsch) ?>">
        <button class="knopf haupt" style="width:100%"><?= $h($W['vormerkKnopf']) ?></button>
      </form>
    <?php endif; ?>
    <p style="margin-top:12px;text-align:center">
      <a href="/hosting.php?lang=<?= $h($sprache) ?>" style="color:var(--leise);font-size:12.5px"><?= $h($W['nochmal']) ?></a></p>

  <?php /* ---------- FORMULAR: Domain eingeben und prüfen ---------- */ ?>
  <?php else: ?>
    <h1 style="font-size:22px;margin:0 0 6px"><?= $h($W['titel']) ?></h1>
    <p style="color:var(--dim);line-height:1.6;margin:0 0 10px"><?= $h($W['lead']) ?></p>
    <p class="preiszeile"><b><?= $h($preisZeile) ?></b></p>

    <div style="font-size:13px;font-weight:650;margin-bottom:8px"><?= $h($W['drin']) ?></div>
    <ul class="drin">
      <?php foreach ($W['punkte'] as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?>
    </ul>

    <?php foreach ($fehler as $x): ?><div class="hinweis schlecht"><?= $h($x) ?></div><?php endforeach; ?>

    <form method="post" action="/hosting.php?lang=<?= $h($sprache) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="pruefen">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
             style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="feld"><label for="hname"><?= $h($W['name']) ?></label>
        <input id="hname" name="name" required value="<?= $h($name) ?>"></div>
      <div class="feld"><label for="hemail"><?= $h($W['email']) ?></label>
        <input id="hemail" type="email" name="email" required value="<?= $h($email) ?>"></div>
      <div class="feld"><label for="hdomain"><?= $h($W['domain']) ?></label>
        <input id="hdomain" name="wunschdomain" required
               placeholder="<?= $h($W['domainBsp']) ?>" value="<?= $h($wunsch) ?>"></div>
      <button class="knopf haupt" style="width:100%;margin-top:6px"><?= $h($W['pruefen']) ?></button>
    </form>
    <p class="hin"><?= $h($W['ablauf']) ?></p>
  <?php endif; ?>
</div>
</body>
</html>
