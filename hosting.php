<?php
declare(strict_types=1);
/* ==========================================================================
   hosting.php — Domain & Hosting solo bestellen, ohne Website-Auftrag.

   DER WEG IN DREI SAETZEN

   Der Besucher nennt Name, E-Mail und seine Wunschdomain. Daraus entsteht
   eine ANFRAGE (kein Vertrag): Uwe prueft, ob die Domain wirklich frei ist,
   und bietet sie dann ueber die Kundenseite an — dort stehen Preis und
   Ja-Knopf. Erst dieser Klick ist die Zustimmung, und angelegt wird erst,
   wenn die erste Monatsrate bezahlt ist.

   WARUM KEINE LIVE-DOMAINPRUEFUNG IM FORMULAR

   Die Pruefung fragt fremde Dienste (RDAP, Whois) auf unsere Rechnung und
   IP. Auf einer offenen Seite ohne Schluessel waere das ein kostenloser
   Whois-Dienst fuer jedermann — beim Fragebogen haengt sie deshalb am
   Token. Hier prueft Uwe im Admin, bevor ein Angebot rausgeht: Der Kunde
   bekommt nie ein Versprechen, das sich als vergeben herausstellt.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$sprache = strtolower((string) ($_REQUEST['lang'] ?? 'it'));
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
    'name'    => 'Nome e cognome', 'email' => 'E-mail', 'tel' => 'Telefono (facoltativo)',
    'domain'  => 'Il dominio che vorresti', 'domainBsp' => 'es. trattoria-rossi.it',
    'senden'  => 'Richiedi il dominio',
    'ablauf'  => 'Come funziona: controlliamo se il dominio è libero e ti mandiamo l’offerta via e-mail — decidi tu con un clic sulla tua pagina personale. Attiviamo tutto dopo il primo pagamento mensile. Nessun vincolo prima della tua conferma.',
    'dankeT'  => 'Richiesta arrivata!',
    'danke'   => 'Controlliamo subito se {domain} è libero e ti scriviamo a {email} — di solito entro un giorno lavorativo. Nella tua casella trovi già una conferma.',
    'fehler'  => 'Controlla nome, e-mail e dominio e riprova.',
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
    'name'    => 'Vor- und Nachname', 'email' => 'E-Mail', 'tel' => 'Telefon (freiwillig)',
    'domain'  => 'Deine Wunschdomain', 'domainBsp' => 'z. B. trattoria-rossi.it',
    'senden'  => 'Domain anfragen',
    'ablauf'  => 'So läuft es: Wir prüfen, ob die Domain frei ist, und schicken dir das Angebot per E-Mail — entscheiden tust du mit einem Klick auf deiner persönlichen Seite. Geschaltet wird alles nach der ersten Monatszahlung. Vor deiner Zusage bindet dich nichts.',
    'dankeT'  => 'Anfrage ist da!',
    'danke'   => 'Wir prüfen gleich, ob {domain} frei ist, und schreiben dir an {email} — meist innerhalb eines Werktags. Eine Eingangsbestätigung liegt schon in deinem Postfach.',
    'fehler'  => 'Bitte Name, E-Mail und Domain prüfen und noch einmal senden.',
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
    'name'    => 'Full name', 'email' => 'Email', 'tel' => 'Phone (optional)',
    'domain'  => 'The domain you’d like', 'domainBsp' => 'e.g. trattoria-rossi.com',
    'senden'  => 'Request this domain',
    'ablauf'  => 'How it works: we check whether the domain is available and email you the offer — you decide with one click on your personal page. Everything is set up after your first monthly payment. Nothing binds you before you say yes.',
    'dankeT'  => 'Request received!',
    'danke'   => 'We’ll check right away whether {domain} is available and write to you at {email} — usually within one working day. A confirmation is already in your inbox.',
    'fehler'  => 'Please check name, email and domain and send again.',
    'zurueck' => 'Back to the website',
  ],
][$sprache];

$preisCents = 990;
try {
    foreach (['Config', 'Db'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
    require_once __DIR__ . '/app/src/Status.php';
    $preisCents = (int) (Db::wert("SELECT monthly_cents FROM packages WHERE slug = 'hosting'", [], 990) ?: 990);
} catch (Throwable $e) { /* die Seite zeigt dann den festen Preis */ }
$preisText = number_format($preisCents / 100, 2, ',', '.') . ' €';

$fertig = false; $panne = false;
$name = ''; $email = ''; $telefon = ''; $wunsch = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sauber = static function ($v, int $max): string {
        $v = is_string($v) ? trim($v) : '';
        return mb_substr(str_replace(["\r", "\0"], '', $v), 0, $max);
    };
    $name    = $sauber($_POST['name'] ?? '', 120);
    $email   = mb_strtolower($sauber($_POST['email'] ?? '', 160));
    $telefon = $sauber($_POST['telefon'] ?? '', 60);
    $wunsch  = mb_strtolower($sauber($_POST['wunschdomain'] ?? '', 190));

    if (!empty($_POST['website'])) {                       // Honigtopf
        header('Location: /'); exit;
    }
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $wunsch === '') {
        $panne = true;
    } else {
        /* Einfache Bremse gegen Skripte: eine Anfrage je Adresse alle 20 s. */
        $sperre = sys_get_temp_dir() . '/vecomhost_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (is_file($sperre) && (time() - (int) filemtime($sperre)) < 20) {
            $panne = true;
        } else {
            @touch($sperre);
            try {
                foreach (['Csrf', 'Auth', 'Fmt', 'Events', 'Mail', 'Anfrage'] as $k) {
                    require_once __DIR__ . "/app/src/$k.php";
                }
                date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
                Anfrage::annehmen([
                    'name' => $name, 'email' => $email, 'telefon' => $telefon,
                    'paket' => 'hosting', 'sprache' => $sprache, 'sprache_gefragt' => true,
                    'nachricht' => "Solo Domain & Hosting.\nWunschdomain: " . $wunsch,
                ]);
                $fertig = true;
            } catch (Throwable $e) {
                error_log('hosting.php — ' . $e->getMessage());
                $panne = true;
            }
        }
    }
}
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
</style>
</head>
<body>
<div class="karte">
  <div class="wortmarke"><b>VECOM</b>&nbsp;DESIGN</div>

  <?php if ($fertig): ?>
    <h1 style="font-size:22px;margin:0 0 8px"><?= $h($W['dankeT']) ?></h1>
    <p style="color:var(--dim);line-height:1.6"><?= $h(strtr($W['danke'],
        ['{domain}' => $wunsch, '{email}' => $email])) ?></p>
    <p style="margin-top:18px"><a class="knopf" href="/<?= $sprache === 'it' ? '' : $sprache . '/' ?>"><?= $h($W['zurueck']) ?></a></p>

  <?php else: ?>
    <h1 style="font-size:22px;margin:0 0 6px"><?= $h($W['titel']) ?></h1>
    <p style="color:var(--dim);line-height:1.6;margin:0 0 10px"><?= $h($W['lead']) ?></p>
    <p class="preiszeile"><b><?= $h(strtr($W['preis'], ['{preis}' => $preisText])) ?></b></p>

    <div style="font-size:13px;font-weight:650;margin-bottom:8px"><?= $h($W['drin']) ?></div>
    <ul class="drin">
      <?php foreach ($W['punkte'] as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?>
    </ul>

    <?php if ($panne): ?><div class="hinweis schlecht"><?= $h($W['fehler']) ?></div><?php endif; ?>

    <form method="post" action="/hosting.php?lang=<?= $h($sprache) ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
             style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="feld"><label for="hname"><?= $h($W['name']) ?></label>
        <input id="hname" name="name" required value="<?= $h($name) ?>"></div>
      <div class="feld"><label for="hemail"><?= $h($W['email']) ?></label>
        <input id="hemail" type="email" name="email" required value="<?= $h($email) ?>"></div>
      <div class="feld"><label for="htel"><?= $h($W['tel']) ?></label>
        <input id="htel" name="telefon" value="<?= $h($telefon) ?>"></div>
      <div class="feld"><label for="hdomain"><?= $h($W['domain']) ?></label>
        <input id="hdomain" name="wunschdomain" required
               placeholder="<?= $h($W['domainBsp']) ?>" value="<?= $h($wunsch) ?>"></div>
      <button class="knopf haupt" style="width:100%;margin-top:6px"><?= $h($W['senden']) ?></button>
    </form>
    <p class="hin"><?= $h($W['ablauf']) ?></p>
  <?php endif; ?>
</div>
</body>
</html>
