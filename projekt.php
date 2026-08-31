<?php
declare(strict_types=1);
/* ==========================================================================
   Die Seite, auf der der Kunde seinem Projekt zusieht.

   Derselbe Schluessel wie beim Fragebogen — es ist derselbe Mensch, und ein
   zweiter Schluessel waere ein zweiter, der verloren gehen kann. Kein Konto,
   kein Passwort, nichts zu merken.

   Er sieht, wo das Projekt steht, kann uns schreiben und Dateien schicken.
   Alles Uebrige — Betraege, andere Kunden, die Verwaltung — sieht er nicht.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Onboarding.php';
require_once __DIR__ . '/app/src/Nachricht.php';
require_once __DIR__ . '/app/src/Ablage.php';
require_once __DIR__ . '/app/src/Rechnung.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomprojekt');
session_start();

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$token = trim((string) ($_REQUEST['t'] ?? ''));
$f = null;
$panne = false;
try {
    $f = Onboarding::laden($token);
} catch (Throwable $e) {
    $panne = true;
}

$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($f['kunde_sprache'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T = static fn(string $s): string => Texte::h(Texte::PROJEKT[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

/* ---------- Eine Datei herunterladen ---------- */
if ($f && isset($_GET['datei'])) {
    $d = Db::one('SELECT * FROM files WHERE id = ? AND project_id = ?',
        [(int) $_GET['datei'], (int) $f['projekt_id']]);
    if (!$d) { http_response_code(404); exit('Nicht gefunden.'); }
    Ablage::ausliefern($d);
}

/* ---------- Einen eigenen Beleg herunterladen ---------- */
if ($f && isset($_GET['beleg'])) {
    // Ueber die Bestellung geprueft, nicht ueber die Belegnummer: So kann
    // niemand mit einer geratenen Nummer einen fremden Beleg ziehen.
    $r = Db::one('SELECT * FROM invoices WHERE id = ? AND customer_id = ? AND project_id = ?',
        [(int) $_GET['beleg'], (int) $f['customer_id'], (int) $f['projekt_id']]);
    if (!$r) { http_response_code(404); exit('Nicht gefunden.'); }
    $daten = Rechnung::pdf($r);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($daten));
    header('Content-Disposition: attachment; filename="' . Rechnung::dateiname($r) . '"');
    echo $daten;
    exit;
}

/* ---------- Schreiben und Hochladen ---------- */
$meldung = null;
$fehler  = [];

if ($f && Ablage::zuGrossFuerDenServer()) {
    // Der Server hat die Anfrage verworfen, bevor PHP sie zu sehen bekam.
    // Ohne diesen Fall stuende hier eine Meldung ueber ein abgelaufenes
    // Formular — und der Kunde suchte den Fehler an der falschen Stelle.
    $fehler[] = 'Die Datei ist größer als ' . Fmt::bytes(Ablage::grenze()) . '.';
} elseif ($f && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $fehler[] = Texte::h(Texte::SEITE['panne'], $sprache);
    } elseif (!empty($_POST['website'])) {
        // Honigtopf: Menschen fuellen dieses Feld nie aus.
        header('Location: projekt.php?t=' . rawurlencode($token)); exit;
    } else {
        $tat = (string) ($_POST['tat'] ?? '');
        try {
            if ($tat === 'nachricht') {
                $text = trim((string) ($_POST['text'] ?? ''));
                if ($text === '') {
                    $fehler[] = $T('leer');
                } else {
                    Nachricht::schreiben((int) $f['projekt_id'], $text, 'kunde');
                    $meldung = $T('gesendet');
                }
            } elseif ($tat === 'freigabe') {
                // Der Kunde gibt frei. Damit rueckt das Projekt auf die finale
                // Freigabe — und genau daran haengt die Restzahlungs-Anfrage.
                if (in_array((string) $f['projekt_status'], ['vorschau', 'kundenfeedback', 'aenderungen'], true)) {
                    Events::protokoll('freigabe', 'Der Kunde hat die Vorschau freigegeben',
                        (int) $f['customer_id'], null, (int) $f['projekt_id']);
                    Events::melden('freigabe', 'Vorschau freigegeben', 'gut',
                        ($f['kunde_firma'] ?: $f['kunde']) . ' — ' . $f['projekt'],
                        '/projekte/' . (int) $f['projekt_id']);
                    Events::projektStatus((int) $f['projekt_id'], 'finale_freigabe');
                }
                $meldung = $T('freigegeben');
            } elseif ($tat === 'aenderung') {
                $text = trim((string) ($_POST['text'] ?? ''));
                if ($text === '') {
                    $fehler[] = $T('aendernWie');
                } else {
                    Nachricht::schreiben((int) $f['projekt_id'], $text, 'kunde');
                    if ((string) $f['projekt_status'] !== 'aenderungen') {
                        Events::projektStatus((int) $f['projekt_id'], 'aenderungen');
                    }
                    $meldung = $T('aenderungOk');
                }
            } elseif ($tat === 'datei') {
                Ablage::annehmen($_FILES['datei'] ?? [], (int) $f['projekt_id'], (int) $f['customer_id'], 'kunde');
                Events::melden('datei_neu', 'Neue Datei vom Kunden', 'info',
                    ($f['kunde_firma'] ?: $f['kunde']) . ' — ' . ($_FILES['datei']['name'] ?? ''),
                    '/projekte/' . (int) $f['projekt_id']);
                $meldung = $T('dateiOk');
            }
        } catch (Throwable $e) {
            // Die Meldung darf der Kunde sehen: Sie sagt ihm, was zu tun ist
            // ("zu groß", "Format nicht angenommen") — keine Serverinterna.
            $fehler[] = $e->getMessage();
        }
        // Frisch lesen: Eine Freigabe aendert den Projektstand, und die Seite
        // soll den neuen zeigen — nicht den von vor einer Zehntelsekunde.
        try { $f = Onboarding::laden($token) ?? $f; } catch (Throwable $e) { /* Anzeige reicht */ }
    }
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

$nachrichten = [];
$dateien = [];
$belege = [];
if ($f) {
    try {
        $nachrichten = Db::all('SELECT * FROM messages WHERE project_id = ? ORDER BY created_at, id', [(int) $f['projekt_id']]);
        $dateien = Db::all('SELECT * FROM files WHERE project_id = ? ORDER BY id DESC', [(int) $f['projekt_id']]);
        $belege = Db::all('SELECT * FROM invoices WHERE project_id = ? ORDER BY id DESC', [(int) $f['projekt_id']]);
    } catch (Throwable $e) { /* Anzeige reicht auch ohne */ }
}

$stufen = array_keys(Texte::PROJEKT_STAND);
$jetzt  = $f ? array_search((string) $f['projekt_status'], $stufen, true) : false;
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/app/assets/admin.css">
<style>
  body{padding:26px 18px 60px}
  .seite{max-width:680px;margin:0 auto}
  .kopfzeile{text-align:center;margin-bottom:20px}
  .wortmarke{display:flex;justify-content:center;align-items:center;gap:2px;
    font-weight:700;letter-spacing:.02em;font-size:18px;padding-bottom:16px}
  .wortmarke b{background:linear-gradient(135deg,var(--blau),var(--cyan));
    -webkit-background-clip:text;background-clip:text;color:transparent}
  .block h2{font-size:15px;margin-bottom:14px}
  textarea{min-height:96px;resize:vertical;line-height:1.5}
  .stufen{list-style:none;padding:0;margin:0}
  .stufen li{display:flex;align-items:center;gap:11px;padding:6px 0;font-size:14px;color:var(--leise)}
  .stufen li .kreis{width:11px;height:11px;border-radius:50%;background:var(--linie);flex:none}
  .stufen li.durch{color:var(--dim)} .stufen li.durch .kreis{background:var(--gruen)}
  .stufen li.jetzt{color:var(--text);font-weight:650} .stufen li.jetzt .kreis{background:var(--cyan);box-shadow:0 0 0 4px rgba(31,232,255,.18)}
  .nachricht{padding:11px 13px;border-radius:11px;margin-bottom:9px;border:1px solid var(--linie)}
  .nachricht.wir{background:var(--flaeche2)}
  .nachricht .wer{font-size:12.5px;font-weight:650;margin-bottom:5px;display:flex;justify-content:space-between;gap:10px}
  .nachricht .text{white-space:pre-wrap;font-size:14px;line-height:1.55}
  .datei{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--linie);font-size:14px}
  .datei:first-of-type{border-top:0}
  .sprachen{text-align:center;margin-top:22px;font-size:13px;color:var(--leise)}
  .sprachen a{color:var(--leise);margin:0 6px}
  .sprachen a.jetzt{color:var(--cyan)}
</style>
</head>
<body>
<div class="seite">
  <div class="kopfzeile"><div class="wortmarke"><b>VECOM</b>&nbsp;DESIGN</div></div>

<?php if ($panne || !$f): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h(Texte::h(Texte::SEITE[$panne ? 'panne' : 'weg'], $sprache)) ?></div>
    <a class="knopf haupt" href="<?= $h($basis) ?>">Vecom Design</a>
  </div>
<?php else: ?>

  <div class="kopfzeile">
    <h1 style="font-size:21px"><?= $h((string) $f['projekt']) ?></h1>
  </div>

  <?php foreach ($fehler as $x): ?><div class="hinweis schlecht"><?= $h($x) ?></div><?php endforeach; ?>
  <?php if ($meldung): ?><div class="hinweis gut"><?= $h($meldung) ?></div><?php endif; ?>

  <div class="block">
    <h2><?= $h($T('stand')) ?></h2>
    <ul class="stufen">
      <?php foreach ($stufen as $i => $stufe): ?>
        <li class="<?= $jetzt !== false && $i < $jetzt ? 'durch' : ($i === $jetzt ? 'jetzt' : '') ?>">
          <span class="kreis"></span><span><?= $h(Texte::h(Texte::PROJEKT_STAND[$stufe], $sprache)) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($f['projekt_vorschau'])): ?>
      <a class="knopf haupt" style="margin-top:14px" href="<?= $h((string) $f['projekt_vorschau']) ?>"
         target="_blank" rel="noopener"><?= $h($T('vorschau')) ?></a>
    <?php endif; ?>

    <?php if ($f['status'] !== 'abgeschlossen'): ?>
      <div class="hinweis" style="margin-top:14px;background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
        <?= $h($T('fragebogenOffen')) ?>
      </div>
      <a class="knopf" href="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>"><?= $h($T('fragebogen')) ?></a>
    <?php endif; ?>
  </div>

  <?php if (in_array((string) $f['projekt_status'], ['vorschau', 'kundenfeedback', 'aenderungen'], true)): ?>
    <div class="block">
      <h2><?= $h($T('freigabe')) ?></h2>
      <p style="color:var(--dim);font-size:14px;line-height:1.6;margin-bottom:14px"><?= $h($T('freigabeText')) ?></p>
      <form method="post" action="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="t" value="<?= $h($token) ?>">
        <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
        <input type="hidden" name="tat" value="freigabe">
        <button class="knopf haupt" style="width:100%;justify-content:center"><?= $h($T('freigeben')) ?></button>
      </form>
      <form method="post" action="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="t" value="<?= $h($token) ?>">
        <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
        <input type="hidden" name="tat" value="aenderung">
        <div class="feld"><textarea name="text" rows="3" maxlength="5000"
          placeholder="<?= $h($T('aendern')) ?>"></textarea></div>
        <button class="knopf" style="width:100%;justify-content:center"><?= $h($T('aendern')) ?></button>
      </form>
    </div>
  <?php endif; ?>

  <div class="block">
    <h2><?= $h($T('nachrichten')) ?></h2>
    <?php if (!$nachrichten): ?>
      <div class="leer"><?= $h($T('nochNichts')) ?></div>
    <?php else: ?>
      <?php foreach ($nachrichten as $n): ?>
        <?php $vonUns = $n['sender'] !== 'kunde'; ?>
        <div class="nachricht <?= $vonUns ? 'wir' : '' ?>">
          <div class="wer">
            <span style="color:<?= $vonUns ? 'var(--cyan)' : 'var(--leise)' ?>">
              <?= $h($vonUns ? $T('wir') : $T('du')) ?></span>
            <span style="color:var(--leise);font-weight:400"><?= $h(Fmt::zeit($n['created_at'])) ?></span>
          </div>
          <div class="text"><?= $h($n['body']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" action="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>" style="margin-top:14px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="t" value="<?= $h($token) ?>">
      <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
      <input type="hidden" name="tat" value="nachricht">
      <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="feld"><label><?= $h($T('schreiben')) ?></label>
        <textarea name="text" rows="4" maxlength="5000"></textarea></div>
      <button class="knopf haupt"><?= $h($T('senden')) ?></button>
    </form>
  </div>

  <div class="block">
    <h2><?= $h($T('dateien')) ?></h2>
    <?php if (!$dateien): ?>
      <div class="leer"><?= $h($T('keineDateien')) ?></div>
    <?php else: ?>
      <?php foreach ($dateien as $d): ?>
        <div class="datei">
          <span><a href="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;datei=<?= (int) $d['id'] ?>"><?= $h($d['orig_name']) ?></a>
            <br><small style="color:var(--leise)"><?= $h(Fmt::bytes((int) $d['size_bytes'])) ?> ·
              <?= $h($d['uploaded_by'] === 'kunde' ? $T('vonDir') : $T('vonUns')) ?> ·
              <?= $h(Fmt::datum($d['created_at'])) ?></small></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" action="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>" enctype="multipart/form-data" style="margin-top:14px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="t" value="<?= $h($token) ?>">
      <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
      <input type="hidden" name="tat" value="datei">
      <div class="feld"><label><?= $h($T('hochladen')) ?></label>
        <input type="file" name="datei" accept="<?= $h(Ablage::endungen()) ?>" required></div>
      <p style="color:var(--leise);font-size:12.5px;margin:-6px 0 12px">
        <?= $h(str_replace('{max}', Fmt::bytes(Ablage::grenze()), $T('dateiHinweis'))) ?></p>
      <button class="knopf"><?= $h($T('senden')) ?></button>
    </form>
  </div>

  <?php if ($belege): ?>
    <div class="block">
      <h2><?= $h($T('belege')) ?></h2>
      <?php foreach ($belege as $r): ?>
        <div class="datei">
          <span><a href="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;beleg=<?= (int) $r['id'] ?>"><?= $h((string) $r['invoice_no']) ?></a>
            <br><small style="color:var(--leise)"><?= $h(Fmt::datum((string) $r['issued_at'])) ?></small></span>
          <b style="white-space:nowrap"><?= $h(Fmt::geld((int) $r['total_cents'], (string) $r['currency'])) ?></b>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="projekt.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $l ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
