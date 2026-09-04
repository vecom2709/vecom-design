<?php
declare(strict_types=1);
/* ==========================================================================
   Direktbuchung eines Pakets von der Website aus.

   Ablauf: Besucher klickt auf der Paketkarte "Jetzt buchen"
        →  diese Seite fragt Name, E-Mail und Firma ab
        →  Kunde und Bestellung entstehen, die Anzahlung wird angelegt
        →  weiter zur Bezahlseite von Stripe

   Bewusst mit drei Feldern statt direkt zu Stripe: Springt jemand auf der
   Bezahlseite ab, bleibt wenigstens der Kontakt — dann ist es eben eine
   Anfrage statt einer Bestellung.

   Der Weg ist nur offen, wenn das Paket dafuer freigeschaltet ist, Stripe
   eingerichtet ist und der Livemodus laeuft. Zum Ausprobieren laesst sich das
   in der Verwaltung voruebergehend auch fuer den Testmodus oeffnen.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Buchung ist derzeit nicht möglich.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Zahlung/Anbieter.php';
require_once __DIR__ . '/app/src/Zahlung/Stripe.php';
require_once __DIR__ . '/app/src/Onboarding.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecombuchung');
session_start();

/* ---------- Sprache ---------- */
$sprache = strtolower((string) ($_REQUEST['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

$T = [
  'it' => [
    'titel' => 'Prenota il pacchetto', 'name' => 'Nome e cognome', 'email' => 'E-mail',
    'firma' => 'Azienda (facoltativo)', 'weiter' => 'Continua al pagamento',
    'anzahlung' => 'Acconto ora', 'rest' => 'Saldo alla consegna', 'gesamt' => 'Totale',
    'hinweis' => 'Ora paghi solo l’acconto. Il saldo è dovuto alla consegna del sito.',
    'zurueck' => 'Torna al sito', 'fehlerFelder' => 'Inserisci nome e un indirizzo e-mail valido.',
    'zu' => 'Questo pacchetto al momento non è prenotabile online. Scrivici — ti rispondiamo entro un giorno lavorativo.',
    'testmodus' => 'Modalità di prova — nessun pagamento reale.',
    'monat' => 'poi al mese',
    'ablauf' => 'Come funziona',
    'a1' => 'Paghi l’acconto', 'a1d' => 'Con carta o altri metodi, tramite un fornitore certificato.',
    'a2' => 'Rispondi a sei domande', 'a2d' => 'Un breve questionario: obiettivo, contenuti, gusto. Bastano pochi minuti.',
    'a3' => 'Ricevi la bozza', 'a3d' => 'La costruisco e ti mando un indirizzo privato dove seguirla.',
    'a4' => 'Approvi e saldi', 'a4d' => 'Il sito va online solo dopo la tua approvazione.',
    'fehlerZust' => 'Per proseguire servono entrambe le conferme.',
  ],
  'de' => [
    'titel' => 'Paket buchen', 'name' => 'Name', 'email' => 'E-Mail',
    'firma' => 'Firma (freiwillig)', 'weiter' => 'Weiter zur Zahlung',
    'anzahlung' => 'Anzahlung jetzt', 'rest' => 'Rest bei Übergabe', 'gesamt' => 'Gesamt',
    'hinweis' => 'Jetzt wird nur die Anzahlung fällig. Der Rest kommt bei Übergabe der Website.',
    'zurueck' => 'Zurück zur Website', 'fehlerFelder' => 'Bitte Name und eine gültige E-Mail-Adresse eintragen.',
    'zu' => 'Dieses Paket ist gerade nicht direkt buchbar. Schreib uns — wir antworten innerhalb eines Werktags.',
    'testmodus' => 'Testmodus — es fließt kein echtes Geld.',
    'monat' => 'danach monatlich',
    'ablauf' => 'So läuft es',
    'a1' => 'Anzahlung zahlen', 'a1d' => 'Mit Karte oder anderen Wegen, über einen geprüften Anbieter.',
    'a2' => 'Sechs Fragen beantworten', 'a2d' => 'Ein kurzer Fragebogen: Ziel, Inhalte, Geschmack. Dauert wenige Minuten.',
    'a3' => 'Entwurf bekommen', 'a3d' => 'Ich baue ihn und schicke dir eine eigene Adresse, unter der du ihn verfolgst.',
    'a4' => 'Freigeben und Rest zahlen', 'a4d' => 'Online geht die Seite erst nach deiner Freigabe.',
    'fehlerZust' => 'Zum Fortfahren werden beide Bestätigungen gebraucht.',
  ],
  'en' => [
    'titel' => 'Book this package', 'name' => 'Name', 'email' => 'Email',
    'firma' => 'Company (optional)', 'weiter' => 'Continue to payment',
    'anzahlung' => 'Deposit now', 'rest' => 'Balance on handover', 'gesamt' => 'Total',
    'hinweis' => 'Only the deposit is due now. The balance follows when the site is handed over.',
    'zurueck' => 'Back to the site', 'fehlerFelder' => 'Please enter a name and a valid email address.',
    'zu' => 'This package can’t be booked online right now. Write to us — we answer within one working day.',
    'testmodus' => 'Test mode — no real money is charged.',
    'monat' => 'then per month',
    'ablauf' => 'How it works',
    'a1' => 'Pay the deposit', 'a1d' => 'By card or other methods, through a certified provider.',
    'a2' => 'Answer six questions', 'a2d' => 'A short questionnaire: goal, content, taste. It takes a few minutes.',
    'a3' => 'Receive the draft', 'a3d' => 'I build it and send you a private address where you can follow it.',
    'a4' => 'Approve and pay the balance', 'a4d' => 'The site goes live only after your approval.',
    'fehlerZust' => 'Both confirmations are needed to continue.',
  ],
][$sprache];

/* Die Widerrufstexte kommen aus Widerruf.php und nicht aus dieser Datei.
   Der Grund ist nicht Ordnungsliebe: Was hier angezeigt wird, wird spaeter
   in der Auftragsbestaetigung woertlich bestaetigt. Zwei Stellen waeren zwei
   Wortlaute, sobald einer davon einmal geaendert wird. */
require_once __DIR__ . '/app/src/Widerruf.php';
$T += Widerruf::texte($sprache);

$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/") . '#plans';

/* ---------- Darf hier ueberhaupt gebucht werden? ---------- */
/* Alles hier in einen Rettungsring: Fehlt eine Aktualisierung der Datenbank
   oder hakt etwas anderes, soll die Seite eine Meldung zeigen — niemals eine
   leere Seite. Das ist eine oeffentliche Adresse. */
$stripe = new StripeAnbieter();
$stripeOffen = false;
$paket = null;
$slug = preg_replace('~[^a-z0-9\-]~i', '', (string) ($_REQUEST['paket'] ?? ''));
try {
    $testSichtbar = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'direktkauf_test'", [], '0') === '1';
    $stripeOffen  = $stripe->bereit() && $stripe->webhookBereit() && ($stripe->modus() === 'live' || $testSichtbar);
    if ($slug !== '') {
        $paket = Db::one('SELECT * FROM packages WHERE slug = ? AND active = 1 AND oeffentlich = 1 AND direktkauf = 1', [$slug]);
    }
} catch (Throwable $e) {
    $stripeOffen = false;
    $paket = null;
}

$fehler = [];
$eingabe = ['name' => '', 'email' => '', 'firma' => ''];

if ($paket && $stripeOffen && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($eingabe as $k => $_) { $eingabe[$k] = trim((string) ($_POST[$k] ?? '')); }

    // Honigtopf — Menschen fuellen dieses Feld nie aus, Bots schon.
    if (!empty($_POST['website'])) { header('Location: ' . $zurueck); exit; }

    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $fehler[] = $T['fehlerFelder'];
    }
    if ($eingabe['name'] === '' || !filter_var($eingabe['email'], FILTER_VALIDATE_EMAIL)) {
        $fehler[] = $T['fehlerFelder'];
    }
    // Beide Zustimmungen sind Pflicht. Ohne die zweite laeuft die Widerrufsfrist
    // von vierzehn Tagen weiter, auch wenn laengst gearbeitet wird.
    $agbOk = !empty($_POST['agb']);
    $widOk = !empty($_POST['widerruf']);
    if (!$agbOk || !$widOk) { $fehler[] = $T['fehlerZust']; }
    // Einfache Bremse gegen wiederholtes Abschicken.
    $sperre = sys_get_temp_dir() . '/vecombuchung_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    if (is_file($sperre) && (time() - filemtime($sperre)) < 20) { $fehler[] = $T['fehlerFelder']; }

    if (!$fehler) {
        touch($sperre);
        try {
            $kundeId = Events::kundeFinden([
                'name' => mb_substr($eingabe['name'], 0, 120),
                'email' => mb_strtolower($eingabe['email']),
                'company' => mb_substr($eingabe['firma'], 0, 120) ?: null,
            ]);
            // In welcher Sprache er gebucht hat, in der schreiben wir ihm auch.
            Onboarding::spracheMerken($kundeId, $sprache);
            $bestellId = Events::bestellungAnlegen($kundeId, (int) $paket['id'],
                'Direkt auf der Website gebucht (' . strtoupper($sprache) . ')');

            // Nicht nur der Haken wird festgehalten, sondern der Wortlaut, den er
            // vor sich hatte: Was heute auf der Seite steht, kann morgen anders
            // lauten — belegbar ist nur das Gezeigte.
            $jetzt = date('Y-m-d H:i:s');
            Db::update('orders', $bestellId, [
                'agb_ok_am'       => $jetzt,
                'widerruf_ok_am'  => $jetzt,
                'zustimmung_lang' => $sprache,
                'zustimmung_text' => trim(strip_tags((string) $T['agb'])) . "\n" . trim((string) $T['wid']),
            ]);

            $zahlung = Db::one("SELECT * FROM payments WHERE order_id = ? AND art = 'anzahlung'", [$bestellId]);
            $bestell = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
            $kunde   = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);

            $url = $stripe->bezahlseite($zahlung, $bestell, $kunde);
            Db::update('payments', (int) $zahlung['id'], [
                'provider' => 'stripe', 'status' => 'in_bearbeitung',
                'link_url' => $url, 'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
            ]);
            Events::melden('bestellung_neu', 'Direktbuchung auf der Website', 'info',
                $paket['name'] . ' — ' . $eingabe['name'], '/bestellungen/' . $bestellId);

            header('Location: ' . $url);
            exit;
        } catch (Throwable $e) {
            // Der Kontakt ist trotzdem da — das ist der Sinn der drei Felder.
            Events::melden('integration_fehler', 'Direktbuchung fehlgeschlagen', 'schlecht',
                mb_substr($e->getMessage(), 0, 200), '/integrationen');
            $fehler[] = $T['zu'];
        }
    }
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$texte = $paket && $paket['texte'] ? (json_decode((string) $paket['texte'], true)[$sprache] ?? []) : [];
$name  = trim((string) ($texte['name'] ?? '')) !== '' ? $texte['name'] : (string) ($paket['name'] ?? '');
$preis = (int) ($paket['price_cents'] ?? 0);
$anz   = (int) round($preis * 50 / 100);
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($T['titel']) ?> — Vecom Design</title>
<link rel="stylesheet" href="/app/assets/admin.css">
<style>
  body{display:flex;align-items:center;justify-content:center;padding:24px}
  .karte{width:100%;max-width:480px}
  /* Eigene Wortmarke: die aus admin.css verschwindet unter 900 Pixeln,
     und genau dort — auf dem Handy — wird am meisten gebucht. */
  .ablauf{list-style:none;counter-reset:s;margin:0 0 20px;padding:0;display:grid;gap:10px}
  .ablauf li{counter-increment:s;display:grid;grid-template-columns:26px 1fr;gap:2px 10px;align-items:start}
  /* Beide Textzeilen gehoeren in die zweite Spalte — sonst faellt die
     Beschreibung in die 26px schmale Ziffernspalte und bricht Wort fuer Wort. */
  .ablauf li>*{grid-column:2}
  .ablauf li::before{grid-column:1;grid-row:1/span 2}
  .ablauf li::before{content:counter(s);width:26px;height:26px;border-radius:50%;
    display:grid;place-items:center;font-size:12px;font-weight:700;
    background:rgba(31,232,255,.12);color:var(--cyan);box-shadow:inset 0 0 0 1px rgba(31,232,255,.3)}
  .ablauf b{display:block;font-size:14px}
  .ablauf span{display:block;font-size:12.5px;color:var(--dim);line-height:1.45}
  .zustimmung{display:grid;grid-template-columns:22px 1fr;gap:10px;align-items:start;
    margin:14px 0;font-size:12.5px;line-height:1.5;color:var(--dim);cursor:pointer}
  .zustimmung input{width:20px;height:20px;margin:1px 0 0;accent-color:var(--blau);cursor:pointer}
  .zustimmung a{color:var(--cyan)}
  .widerruf{margin:0 0 18px;font-size:12.5px;color:var(--leise)}
  .widerruf summary{cursor:pointer;min-height:44px;display:flex;align-items:center;color:var(--dim)}
  .widerruf p{margin:0 0 4px;line-height:1.55}
  .wortmarke{display:flex;justify-content:center;align-items:center;gap:2px;
    font-weight:700;letter-spacing:.02em;font-size:18px;margin-bottom:14px}
  .wortmarke b{background:linear-gradient(135deg,var(--blau),var(--cyan));
    -webkit-background-clip:text;background-clip:text;color:transparent}
  .zeile{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-top:1px solid var(--linie);font-size:14px}
  .zeile:first-of-type{border-top:0}
  .zeile b{font-variant-numeric:tabular-nums}
  .gross{font-size:19px;font-weight:650}
</style>
</head>
<body>
<div class="karte">
  <div class="wortmarke"><b>VECOM</b>&nbsp;DESIGN</div>

  <?php if (!$paket || !$stripeOffen): ?>
    <div class="block">
      <div class="hinweis schlecht"><?= $h($T['zu']) ?></div>
      <a class="knopf haupt" href="<?= $h($zurueck) ?>"><?= $h($T['zurueck']) ?></a>
    </div>
  <?php else: ?>
    <div class="block">
      <h2 style="font-size:17px;margin-bottom:14px"><?= $h($T['titel']) ?>: <?= $h($name) ?></h2>

      <?php if ($stripe->modus() !== 'live'): ?>
        <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
          <?= $h($T['testmodus']) ?>
        </div>
      <?php endif; ?>
      <?php foreach ($fehler as $f): ?><div class="hinweis schlecht"><?= $h($f) ?></div><?php endforeach; ?>

      <div class="zeile"><span><?= $h($T['gesamt']) ?></span><b><?= Fmt::geld($preis, (string) $paket['currency']) ?></b></div>
      <div class="zeile gross"><span><?= $h($T['anzahlung']) ?></span><b><?= Fmt::geld($anz, (string) $paket['currency']) ?></b></div>
      <div class="zeile" style="color:var(--dim)"><span><?= $h($T['rest']) ?></span><b><?= Fmt::geld($preis - $anz, (string) $paket['currency']) ?></b></div>
      <?php if ((int) $paket['monthly_cents'] > 0): ?>
        <div class="zeile" style="color:var(--leise)"><span><?= $h($T['monat']) ?></span><b><?= Fmt::geld((int) $paket['monthly_cents'], (string) $paket['currency']) ?></b></div>
      <?php endif; ?>

      <p style="color:var(--dim);font-size:13px;margin:12px 0 16px"><?= $h($T['hinweis']) ?></p>

      <!-- Was nach dem Klick kommt. Alles davon laeuft ohnehin schon; sichtbar
           war es bisher nur denen, die bereits bezahlt hatten. -->
      <ol class="ablauf" aria-label="<?= $h($T['ablauf']) ?>">
        <?php foreach ([1, 2, 3, 4] as $i): ?>
          <li><b><?= $h($T['a' . $i]) ?></b><span><?= $h($T['a' . $i . 'd']) ?></span></li>
        <?php endforeach; ?>
      </ol>

      <form method="post">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="paket" value="<?= $h((string) $paket['slug']) ?>">
        <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
        <div style="position:absolute;left:-9999px" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="feld"><label><?= $h($T['name']) ?> *</label>
          <input name="name" required value="<?= $h($eingabe['name']) ?>" autocomplete="name"></div>
        <div class="feld"><label><?= $h($T['email']) ?> *</label>
          <input type="email" name="email" required value="<?= $h($eingabe['email']) ?>" autocomplete="email"></div>
        <div class="feld"><label><?= $h($T['firma']) ?></label>
          <input name="firma" value="<?= $h($eingabe['firma']) ?>" autocomplete="organization"></div>
        <label class="zustimmung">
          <input type="checkbox" name="agb" value="1" required <?= !empty($_POST['agb']) ? 'checked' : '' ?>>
          <span><?= $T['agb'] /* enthaelt bewusst zwei Links */ ?></span>
        </label>
        <label class="zustimmung">
          <input type="checkbox" name="widerruf" value="1" required <?= !empty($_POST['widerruf']) ? 'checked' : '' ?>>
          <span><?= $h($T['wid']) ?></span>
        </label>
        <details class="widerruf">
          <summary><?= $h($T['widTitel']) ?></summary>
          <p><?= $h($T['widText']) ?></p>
        </details>
        <button class="knopf haupt" style="width:100%;justify-content:center"><?= $h($T['weiter']) ?></button>
      </form>
      <p style="text-align:center;margin-top:14px">
        <a href="<?= $h($zurueck) ?>" style="color:var(--leise);font-size:13px"><?= $h($T['zurueck']) ?></a></p>
    </div>
  <?php endif; ?>
</div>
<?php /* Impressum, Datenschutz und AGB — auch unter den Seiten, die man nur
         mit Schluessel erreicht. Sie waren bisher nur auf den oeffentlichen
         Seiten zu finden, obwohl der Kunde hier entscheidet. */ ?>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
