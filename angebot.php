<?php
declare(strict_types=1);
/* ==========================================================================
   angebot.php — Das Angebot, so wie der Kunde es sieht und annimmt.

   HIER WIRD AUS EINEM GESPRAECH GELD

   Deshalb steht auf dieser Seite nichts, was ablenkt: die Positionen, die
   Summe, wie bezahlt wird, und zwei Knoepfe. Kein Konto, kein Passwort — der
   Schluessel in der Adresse ist der Zugang, wie beim Fragebogen und bei der
   Projektseite.

   ZWEI KNOEPFE, NICHT EINER

   "Passt so nicht" gehoert genauso sichtbar hierher wie "Annehmen". Wer nur
   zusagen kann, sagt gar nichts und man wartet zwei Wochen auf ein Schweigen,
   aus dem sich nichts lernen laesst. Mit einer abgelehnten Zeile weiss man
   wenigstens, woran es lag.

   Das Annehmen ist ein POST mit Bestaetigung, nie ein Link. Ein Angebot per
   Vorschau eines Mailprogramms anzunehmen waere ein teurer Unfall.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Das Angebot ist derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Angebot.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomangebot');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

$token = trim((string) ($_REQUEST['t'] ?? ''));
$a = null; $panne = false;
try {
    $a = Angebot::laden($token);
} catch (Throwable $e) {
    $panne = true;
    try { Events::melden('angebot_fehler', 'Angebotsseite nicht erreichbar', 'schlecht', $e->getMessage(), '/angebote'); }
    catch (Throwable $e2) { /* dann eben nicht */ }
}

$sprache = $a ? (string) $a['sprache'] : 'it';
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T = static fn(string $s): string => Texte::h(Texte::ANGEBOT[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$adresse = static fn(string $meldung = ''): string =>
    '/angebot.php?t=' . rawurlencode($token) . ($meldung !== '' ? '&m=' . rawurlencode($meldung) : '');

/* ---------- Antworten, dann umleiten ---------- */
if ($a && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        header('Location: ' . $adresse('panne')); exit;
    }
    $tat = (string) ($_POST['tat'] ?? '');
    try {
        if ($tat === 'annehmen') {
            $bestellId = Angebot::annehmen($token);
            header('Location: ' . $adresse($bestellId !== null ? 'danke' : 'panne')); exit;
        }
        if ($tat === 'ablehnen') {
            Angebot::ablehnen($token, (string) ($_POST['grund'] ?? ''));
            header('Location: ' . $adresse('abgelehnt')); exit;
        }
        if ($tat === 'wunsch') {
            /* Nur Kreuze und Mengen kommen mit, keine Betraege -- die Preise
               holt Angebot::wunschSpeichern aus dem Angebot und dem Katalog.
               Ein Preis, den der Kunde mitschicken darf, waere ein Preis, den
               er bestimmen darf. */
            $mengen = [];
            foreach ((array) ($_POST['pos'] ?? []) as $slug => $menge) {
                if (!preg_match('/^[a-z0-9_]{1,60}$/', (string) $slug)) { continue; }
                $mengen[(string) $slug] = (int) $menge;
            }
            $ok = Angebot::wunschSpeichern((int) $a['id'], $mengen);
            header('Location: ' . $adresse($ok ? 'wunsch' : 'panne')); exit;
        }
    } catch (Throwable $e) {
        try { Events::melden('angebot_fehler', 'Antwort auf ein Angebot ging schief', 'schlecht', $e->getMessage(), '/angebote'); }
        catch (Throwable $e2) { /* dann eben nicht */ }
        header('Location: ' . $adresse('panne')); exit;
    }
    header('Location: ' . $adresse()); exit;
}

/* ---------- Als PDF ausliefern ----------
   Vor jeder Ausgabe von HTML, sonst stehen die Kopfzeilen schon fest. Der
   Kunde bekommt es als Anhang mit sprechendem Dateinamen, nicht als
   Bildschirmansicht in einem fremden Betrachter. */
if ($a && isset($_GET['pdf'])) {
    try {
        $inhalt = Angebot::pdf((int) $a['id']);
        if ($inhalt !== '') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . Angebot::dateiname($a) . '"');
            header('Content-Length: ' . strlen($inhalt));
            header('X-Content-Type-Options: nosniff');
            echo $inhalt;
            exit;
        }
    } catch (Throwable $e) {
        try { Events::melden('angebot_fehler', 'Angebots-PDF liess sich nicht bauen', 'schlecht', $e->getMessage(), '/angebote'); }
        catch (Throwable $e2) { /* dann eben nicht */ }
    }
    header('Location: ' . $adresse('panne')); exit;
}

$m         = (string) ($_GET['m'] ?? '');
$positionen = $a ? Angebot::positionen((int) $a['id']) : [];

/* Fuer den Rechner: der Katalog, und daraus die Posten, die noch nicht im
   Angebot stehen. Betreuungspakete bleiben draussen -- die sind ein eigener
   Vertrag und gehoeren nicht in eine Angebotssumme. */
require_once __DIR__ . '/app/src/Baukasten.php';
$katalogW = [];
$dazu     = [];
if ($a) {
    try {
        $katalogW = Baukasten::katalog();
        $drin = [];
        foreach ($positionen as $p) { $drin[(string) $p['baustein_slug']] = true; }
        foreach ($katalogW as $slug => $bs) {
            if (isset($drin[$slug]) || (int) $bs['monatlich']) { continue; }
            $dazu[$slug] = $bs;
        }
    } catch (Throwable $e) { $katalogW = []; $dazu = []; }
}
$abgelaufen = $a && Angebot::abgelaufen($a);
$offen      = $a && $a['status'] === 'gesendet' && !$abgelaufen;

$basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/");

$anzahlungCents = $a ? (int) round((int) $a['summe_cents'] * (int) $a['anzahlung_prozent'] / 100) : 0;
$datum = static function (?string $d): string {
    return $d ? date('d.m.Y', (int) strtotime($d)) : '';
};
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css">
<style>
  .lead{color:var(--dim);font-size:15px;line-height:1.65}
  .akopf{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--linie)}
  .eck{display:flex;gap:14px;flex-wrap:wrap;font-size:12.5px;color:var(--leise);margin-top:10px}
  .eck b{color:var(--dim);font-weight:600}
  .pos{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--linie);align-items:flex-start}
  .pos:last-child{border-bottom:0}
  .pos__wort{flex:1 1 auto;min-width:0}
  .pos__wort strong{font-size:14.5px}
  .pos__wort p{margin:3px 0 0;color:var(--leise);font-size:12.5px;line-height:1.55}
  .pos__geld{flex:0 0 auto;text-align:right;font-size:14.5px;white-space:nowrap}
  .pos__menge{color:var(--leise);font-size:12.5px}
  .summe{display:flex;justify-content:space-between;align-items:baseline;gap:12px;
    padding-top:14px;margin-top:4px;border-top:2px solid var(--linie)}
  .summe .wort{font-size:14px;color:var(--dim)}
  .summe .zahl{font-size:clamp(22px,5.5vw,30px);font-weight:600}
  .mtl{display:flex;justify-content:space-between;gap:12px;margin-top:10px;font-size:14px;color:var(--dim)}
  .zahlung{color:var(--leise);font-size:13px;line-height:1.65;margin:14px 0 0}
  .tun{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
  .tun .knopf{flex:1 1 auto}
  .neinbox{margin-top:14px;padding-top:14px;border-top:1px solid var(--linie)}
  .neinbox summary{cursor:pointer;color:var(--leise);font-size:13px}
  .neinbox textarea{margin-top:10px}

  /* ---- Der Rechner ----------------------------------------------------
     Bewusst dieselbe Zeilenform wie die Posten darueber: Der Kunde soll
     sehen, dass er an derselben Liste arbeitet, nicht an einem Formular
     daneben. */
  .wpos{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--linie);align-items:center}
  .wpos:last-of-type{border-bottom:0}
  .wpos input[type=checkbox]{width:20px;height:20px;flex:0 0 auto;accent-color:var(--cyan)}
  .wpos__wort{flex:1 1 auto;min-width:0;font-size:14px}
  .wpos__wort small{display:block;color:var(--leise);font-size:12px;margin-top:2px}
  .wpos__geld{flex:0 0 auto;text-align:right;font-size:14px;white-space:nowrap;font-variant-numeric:tabular-nums}
  .wmenge{display:inline-flex;align-items:center;gap:6px;margin-left:8px}
  .wmenge button{width:30px;height:30px;border-radius:8px;border:1px solid var(--linie);
    background:transparent;color:inherit;font-size:16px;line-height:1;cursor:pointer}
  .wmenge button:hover{border-color:var(--cyan)}
  .wmenge span{min-width:1.6em;text-align:center;font-variant-numeric:tabular-nums}
  .wsumme{display:flex;justify-content:space-between;align-items:baseline;gap:12px;
    padding-top:14px;margin-top:8px;border-top:2px solid var(--linie)}
  .wsumme .zahl{font-size:clamp(20px,5vw,26px);font-weight:600}
  .waus{opacity:.45}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($panne): ?>
  <div class="block"><div class="hinweis schlecht"><?= $h($T('panne')) ?></div></div>

<?php elseif (!$a): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($T('weg')) ?></div>
    <a class="knopf haupt" style="margin-top:12px" href="<?= $h($zurueck) ?>">Vecom Design</a>
  </div>

<?php else: ?>
  <?php if ($m === 'wunsch'): ?><div class="hinweis gut"><?= $h($T('aendernDanke')) ?></div><?php endif; ?>
  <?php if ($m === 'danke'): ?><div class="hinweis gut"><?= $h($T('dankeAn')) ?></div><?php endif; ?>
  <?php if ($m === 'abgelehnt'): ?><div class="hinweis"><?= $h($T('dankeAb')) ?></div><?php endif; ?>
  <?php if ($m === 'panne'): ?><div class="hinweis schlecht"><?= $h($T('panne')) ?></div><?php endif; ?>

  <div class="akopf">
    <h1 style="font-size:21px;margin:0 0 6px"><?= $h($T('titel')) ?></h1>
    <p class="lead" style="margin:0"><?= $h($T('lead')) ?></p>
    <div class="eck">
      <span><b><?= $h($T('nummer')) ?></b> <?= $h((string) $a['nummer']) ?></span>
      <?php if ($a['gueltig_bis']): ?>
        <span><?= $h(strtr($T('gueltig'), ['{datum}' => $datum((string) $a['gueltig_bis'])])) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($a['status'] === 'zurueckgezogen'): ?>
    <?php /* Nicht verstecken: Der Kunde hat dieses Blatt gelesen und darf es
             wiederfinden. Nur zusagen kann er darauf nicht mehr -- das haengt
             an $offen, das nur fuer "gesendet" gilt. */ ?>
    <div class="hinweis warnung"><?= $h($T('ersetzt')) ?></div>
  <?php elseif ($a['status'] === 'angenommen'): ?>
    <div class="hinweis gut"><?= $h($T('schonAn')) ?></div>
  <?php elseif ($a['status'] === 'abgelehnt'): ?>
    <div class="hinweis"><?= $h($T('schonAb')) ?></div>
  <?php elseif ($abgelaufen || $a['status'] === 'abgelaufen'): ?>
    <div class="hinweis warnung"><?= $h($T('abgelaufen')) ?></div>
  <?php endif; ?>

  <?php if (trim((string) ($a['einleitung'] ?? '')) !== ''): ?>
    <div class="block"><p style="margin:0;color:var(--dim);font-size:14.5px;line-height:1.7"><?=
      nl2br($h((string) $a['einleitung'])) ?></p></div>
  <?php endif; ?>

  <div class="block">
    <h2 style="font-size:15px;margin:0 0 4px"><?= $h($T('posten')) ?></h2>
    <?php foreach ($positionen as $p): ?>
      <?php if ((int) $p['monatlich']) { continue; } ?>
      <div class="pos">
        <div class="pos__wort">
          <strong><?= $h((string) $p['bezeichnung']) ?></strong>
          <?php if ((int) $p['menge'] > 1): ?>
            <span class="pos__menge">× <?= (int) $p['menge'] ?></span>
          <?php endif; ?>
          <?php if (trim((string) $p['beschreibung']) !== ''): ?>
            <p><?= $h((string) $p['beschreibung']) ?></p>
          <?php endif; ?>
        </div>
        <div class="pos__geld"><?= $h(Fmt::geld((int) $p['summe_cents'], (string) $a['currency'])) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="summe">
      <span class="wort"><?= $h($T('summe')) ?></span>
      <span class="zahl"><?= $h(Fmt::geld((int) $a['summe_cents'], (string) $a['currency'])) ?></span>
    </div>

    <?php foreach ($positionen as $p): ?>
      <?php if (!(int) $p['monatlich']) { continue; } ?>
      <div class="mtl">
        <span><?= $h((string) $p['bezeichnung']) ?></span>
        <span><?= $h(Fmt::geld((int) $p['summe_cents'], (string) $a['currency'])) ?> <?= $h($T('proMonat')) ?></span>
      </div>
    <?php endforeach; ?>

    <?php if ((int) $a['summe_cents'] > 0): ?>
      <p class="zahlung"><?= $h(strtr($T('zahlung'), [
        '{anzahlung}' => Fmt::geld($anzahlungCents, (string) $a['currency'])
      ])) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($offen): ?>
    <div class="block">
      <form method="post" action="/angebot.php?t=<?= $h(rawurlencode($token)) ?>"
            onsubmit="return confirm('<?= $h($T('annehmen')) ?>?')">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="t" value="<?= $h($token) ?>">
        <div class="tun">
          <button class="knopf haupt" name="tat" value="annehmen"><?= $h($T('annehmen')) ?></button>
        </div>
      </form>

      <details class="neinbox">
        <summary><?= $h($T('aendernKopf')) ?></summary>
        <p class="lead" style="font-size:13.5px;margin:10px 0 0"><?= $h($T('aendernLead')) ?></p>

        <?php if ((int) ($a['wunsch_runden'] ?? 0) >= 2): ?>
          <div class="hinweis warnung" style="margin-top:10px"><?= $h($T('aendernGenug')) ?></div>
        <?php endif; ?>

        <form method="post" action="/angebot.php?t=<?= $h(rawurlencode($token)) ?>" id="rechner">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
          <input type="hidden" name="t" value="<?= $h($token) ?>">

          <?php foreach ($positionen as $p): ?>
            <?php
              $slug = (string) $p['baustein_slug'];
              $fest = in_array($slug, Baukasten::FEST, true);
              $bs   = $katalogW[$slug] ?? null;
              $proStueck = $bs && (int) $bs['je_einheit'];
            ?>
            <label class="wpos" data-einzel="<?= (int) $p['einzel_cents'] ?>"
                   data-mtl="<?= (int) $p['monatlich'] ?>">
              <input type="checkbox" name="pos[<?= $h($slug) ?>]" value="<?= (int) $p['menge'] ?>"
                     checked <?= $fest ? 'onclick="return false"' : '' ?>>
              <span class="wpos__wort">
                <?= $h((string) $p['bezeichnung']) ?>
                <?php if ($proStueck): ?>
                  <span class="wmenge" data-menge>
                    <button type="button" data-schritt="-1" aria-label="−">−</button>
                    <span data-zahl><?= (int) $p['menge'] ?></span>
                    <button type="button" data-schritt="1" aria-label="+">+</button>
                  </span>
                <?php endif; ?>
                <?php if ($fest): ?><small><?= $h($T('aendernFest')) ?></small><?php endif; ?>
              </span>
              <span class="wpos__geld" data-geld><?= $h(Fmt::geld((int) $p['summe_cents'], (string) $a['currency'])) ?></span>
            </label>
          <?php endforeach; ?>

          <?php if ($dazu): ?>
            <p style="margin:16px 0 2px;font-size:13px;color:var(--leise)"><?= $h($T('aendernDazu')) ?></p>
            <?php foreach ($dazu as $slug => $bs): ?>
              <?php
                $anfrage = in_array($slug, Baukasten::NUR_AUF_ANFRAGE, true);
                $einzel  = Baukasten::mitte((int) $bs['preis_cents'],
                                            (int) $bs['preis_bis_cents'] ?: (int) $bs['preis_cents']);
              ?>
              <label class="wpos waus" data-einzel="<?= $anfrage ? 0 : $einzel ?>"
                     data-mtl="<?= (int) $bs['monatlich'] ?>">
                <input type="checkbox" name="pos[<?= $h((string) $slug) ?>]" value="1">
                <span class="wpos__wort">
                  <?= $h(Baukasten::name($bs, $sprache)) ?>
                  <?php if (!$anfrage && (int) $bs['je_einheit']): ?>
                    <span class="wmenge" data-menge>
                      <button type="button" data-schritt="-1" aria-label="−">−</button>
                      <span data-zahl>1</span>
                      <button type="button" data-schritt="1" aria-label="+">+</button>
                    </span>
                  <?php endif; ?>
                </span>
                <span class="wpos__geld" data-geld><?= $anfrage
                    ? $h($T('aendernAnfrage'))
                    : $h(Fmt::geld($einzel, (string) $a['currency'])) ?></span>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="wsumme">
            <span class="wort"><?= $h($T('aendernNeu')) ?></span>
            <span class="zahl" id="wsumme"><?= $h(Fmt::geld((int) $a['summe_cents'], (string) $a['currency'])) ?></span>
          </div>
          <div class="mtl" id="wmtlzeile" hidden>
            <span><?= $h($T('proMonat')) ?></span><span id="wmtl"></span>
          </div>
          <p class="zahlung" style="margin-top:8px"><?= $h($T('aendernKeinAngebot')) ?></p>

          <button class="knopf" style="margin-top:12px" name="tat" value="wunsch"><?= $h($T('aendernSenden')) ?></button>
        </form>
      </details>

      <details class="neinbox">
        <summary><?= $h($T('ablehnen')) ?></summary>
        <form method="post" action="/angebot.php?t=<?= $h(rawurlencode($token)) ?>">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
          <input type="hidden" name="t" value="<?= $h($token) ?>">
          <div class="feld">
            <label for="f_grund"><?= $h($T('grundFrage')) ?></label>
            <textarea id="f_grund" name="grund" rows="2"></textarea>
          </div>
          <button class="knopf" name="tat" value="ablehnen"><?= $h($T('ablehnen')) ?></button>
        </form>
      </details>
    </div>
  <?php endif; ?>

  <div class="block" style="text-align:center">
    <a class="knopf" href="/angebot.php?t=<?= $h(rawurlencode($token)) ?>&amp;pdf=1"><?= $h($T('pdf')) ?></a>
  </div>
<?php endif; ?>
</div>
<?php if ($offen): ?>
<script>
/* Rechnet mit, waehrend der Kunde klickt.
   Die Zahlen hier sind Anzeige, nichts weiter: Abgeschickt werden nur die
   Kreuze und die Mengen, den Preis setzt der Server aus Angebot und Katalog.
   Waere es anders, koennte sich der Kunde seinen Preis selbst schreiben. */
(function () {
  var form = document.getElementById('rechner');
  if (!form) { return; }
  var waehrung = <?= json_encode((string) $a['currency']) ?>;
  var summeAus = document.getElementById('wsumme');
  var mtlAus   = document.getElementById('wmtl');
  var mtlZeile = document.getElementById('wmtlzeile');
  var aufAnfrage = <?= json_encode($T('aendernAnfrage'), JSON_UNESCAPED_UNICODE) ?>;

  function geld(cents) {
    return (cents / 100).toLocaleString(<?= json_encode($sprache === 'de' ? 'de-DE' : ($sprache === 'en' ? 'en-GB' : 'it-IT')) ?>,
      { style: 'currency', currency: waehrung || 'EUR', minimumFractionDigits: 2 });
  }

  function menge(zeile) {
    var z = zeile.querySelector('[data-zahl]');
    return z ? parseInt(z.textContent, 10) || 1 : 1;
  }

  function rechnen() {
    var einmal = 0, monat = 0;
    form.querySelectorAll('.wpos').forEach(function (zeile) {
      var kreuz  = zeile.querySelector('input[type=checkbox]');
      var einzel = parseInt(zeile.dataset.einzel, 10) || 0;
      var an     = kreuz && kreuz.checked;
      zeile.classList.toggle('waus', !an);
      var m = menge(zeile);
      if (kreuz) { kreuz.value = String(m); }
      var zeilensumme = einzel * m;
      var geldFeld = zeile.querySelector('[data-geld]');
      if (geldFeld && einzel > 0) { geldFeld.textContent = geld(zeilensumme); }
      else if (geldFeld && einzel === 0) { geldFeld.textContent = aufAnfrage; }
      if (!an) { return; }
      if (zeile.dataset.mtl === '1') { monat += zeilensumme; } else { einmal += zeilensumme; }
    });
    summeAus.textContent = geld(einmal);
    if (monat > 0) { mtlZeile.hidden = false; mtlAus.textContent = geld(monat); }
    else { mtlZeile.hidden = true; }
  }

  form.addEventListener('change', rechnen);
  form.addEventListener('click', function (e) {
    var knopf = e.target.closest('[data-schritt]');
    if (!knopf) { return; }
    e.preventDefault();
    var zeile = knopf.closest('.wpos');
    var zahl  = zeile.querySelector('[data-zahl]');
    var neu   = Math.max(1, Math.min(99, (parseInt(zahl.textContent, 10) || 1) + parseInt(knopf.dataset.schritt, 10)));
    zahl.textContent = String(neu);
    var kreuz = zeile.querySelector('input[type=checkbox]');
    if (kreuz && !kreuz.checked) { kreuz.checked = true; }   // wer die Menge aendert, will es haben
    rechnen();
  });
  rechnen();
})();
</script>
<?php endif; ?>
</body>
</html>
