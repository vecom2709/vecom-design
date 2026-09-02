<?php
declare(strict_types=1);
/* ==========================================================================
   kunde.php — die eine Seite des Kunden.

   Vom ersten Kontakt bis Jahre nach dem Onlinegang: dieselbe Adresse. Sie
   zeigt immer den Schritt, der gerade dran ist, und darunter alles, was
   schon war. Kein Konto, kein Passwort, ein Link.

   Sie ersetzt drei Seiten, die es weiterhin gibt: vorgang.php (Anfrage),
   projekt.php (Projekt) und den Fragebogen. Links, die schon in Postfaechern
   liegen, fuehren von dort hierher — kein bereits verschickter Link stirbt.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
foreach (['Texte', 'Kundenzugang', 'Vorgang', 'Nachricht', 'Ablage', 'Onboarding', 'Mail', 'Abo', 'Stimme'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomkunde');
session_start();

// Der Schluessel steht in der Adresse — er soll nicht ueber den Verweis-Kopf
// an fremde Server gehen und in keinen Zwischenspeicher.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$token = trim((string) ($_REQUEST['t'] ?? ''));
$kunde = null; $panne = false;
try { $kunde = Kundenzugang::ausToken($token); } catch (Throwable $e) { $panne = true; }

$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$h     = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$hier  = 'kunde.php?t=' . rawurlencode($token);

$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($kunde['sprache'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T  = static fn(string $s): string => Texte::h(Texte::KUNDE[$s] ?? [], $sprache);
$TS = static fn(string $stufe, string $feld = ''): string => $feld === ''
    ? Texte::h(Texte::KUNDE_STUFEN[$stufe] ?? [], $sprache)
    : Texte::h(Texte::KUNDE_STUFEN[$stufe][$feld] ?? [], $sprache);

$fehler = []; $meldung = null;

/* -------------------------------------------------------------------------
   Herunterladen: eine Datei oder einen eigenen Beleg.

   Beides wird ueber die Kundennummer geprueft, nie ueber die Nummer im Link
   allein — sonst zoege jemand mit einer geratenen Zahl fremde Unterlagen.
   ------------------------------------------------------------------------- */
if ($kunde && isset($_GET['datei'])) {
    $d = sicherLesen(fn() => Db::one('SELECT * FROM files WHERE id = ? AND customer_id = ?',
        [(int) $_GET['datei'], (int) $kunde['id']]), null);
    if (!$d) { http_response_code(404); exit('Nicht gefunden.'); }
    Ablage::ausliefern($d);
}

if ($kunde && isset($_GET['beleg'])) {
    require_once __DIR__ . '/app/src/Rechnung.php';
    $r = sicherLesen(fn() => Db::one(
        "SELECT * FROM invoices WHERE id = ? AND customer_id = ?
           AND (issued_at IS NOT NULL OR status <> 'entwurf')",
        [(int) $_GET['beleg'], (int) $kunde['id']]), null);
    if (!$r) { http_response_code(404); exit('Nicht gefunden.'); }
    $daten = Rechnung::pdf($r);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($daten));
    header('Content-Disposition: attachment; filename="' . Rechnung::dateiname($r) . '"');
    echo $daten;
    exit;
}

/* -------------------------------------------------------------------------
   Was der Kunde tun kann. Vier Dinge, mehr braucht es nicht.
   ------------------------------------------------------------------------- */
if ($kunde && Ablage::zuGrossFuerDenServer()) {
    // Ohne diesen Fall stuende hier eine Meldung ueber ein abgelaufenes
    // Formular — und der Kunde suchte den Fehler an der falschen Stelle.
    $fehler[] = 'Die Datei ist größer als ' . Fmt::bytes(Ablage::grenze()) . '.';
} elseif ($kunde && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $fehler[] = Texte::h(Texte::SEITE['panne'] ?? [], $sprache, 'Bitte noch einmal versuchen.');
    } elseif (!empty($_POST['website'])) {
        header('Location: ' . $hier); exit;   // Honigtopf
    } else {
        $seite = Kundenzugang::seite($kunde);
        $pid   = $seite['vorgang']['projekt_id'] ?? null;
        $tat   = (string) ($_POST['tat'] ?? '');
        try {
            if ($tat === 'nachricht' || $tat === 'aenderung') {
                $text = trim((string) ($_POST['text'] ?? ''));
                if ($text === '') {
                    $fehler[] = Texte::h(Texte::PROJEKT['leer'] ?? [], $sprache, 'Bitte etwas hineinschreiben.');
                } else {
                    // Vor dem Auftrag gibt es kein Projekt — dann haengt die
                    // Nachricht am Kunden. Danach am Projekt. Fuer ihn ist es
                    // dasselbe Feld.
                    if ($pid) { Nachricht::schreiben((int) $pid, $text, 'kunde'); }
                    else       { Nachricht::vorab((int) $kunde['id'], $text, 'kunde'); }

                    // Ein Aenderungswunsch am fertigen Entwurf schiebt das
                    // Projekt zurueck auf "Aenderungen" — bei einer Seite, die
                    // schon online ist, ausdruecklich nicht: Da ist es ein
                    // Auftrag, ueber den erst gesprochen wird.
                    if ($tat === 'aenderung' && $pid
                        && in_array((string) ($seite['vorgang']['projekt']['status'] ?? ''),
                                    ['vorschau', 'kundenfeedback'], true)) {
                        Events::projektStatus((int) $pid, 'aenderungen');
                    }
                    $meldung = Texte::h(Texte::PROJEKT['gesendet'] ?? [], $sprache, 'Ist raus.');
                }

            } elseif ($tat === 'freigabe' && $pid) {
                $st = (string) ($seite['vorgang']['projekt']['status'] ?? '');
                if (in_array($st, ['vorschau', 'kundenfeedback', 'aenderungen'], true)) {
                    Events::protokoll('freigabe', 'Der Kunde hat die Vorschau freigegeben',
                        (int) $kunde['id'], null, (int) $pid);
                    Events::melden('freigabe', 'Vorschau freigegeben', 'gut',
                        (string) ($kunde['company'] ?: $kunde['name']), '/vorgaenge/b'
                            . (int) ($seite['vorgang']['bestell_id'] ?? 0));
                    Events::projektStatus((int) $pid, 'finale_freigabe');
                }
                $meldung = Texte::h(Texte::PROJEKT['freigegeben'] ?? [], $sprache, 'Danke für die Freigabe.');

            } elseif ($tat === 'stimme') {
                $wort = trim((string) ($_POST['text'] ?? ''));
                if ($wort === '') {
                    $fehler[] = Texte::h(Texte::PROJEKT['leer'] ?? [], $sprache, 'Bitte etwas hineinschreiben.');
                } elseif (!Stimme::vonKunde((int) $kunde['id'])) {
                    Stimme::abgeben((int) $kunde['id'], $wort,
                        !empty($_POST['erlaubnis']),
                        isset($_POST['sterne']) ? (int) $_POST['sterne'] : null);
                    $meldung = Texte::h(Texte::KUNDE['stimmeDanke'] ?? [], $sprache, 'Danke dir!');
                }

            } elseif ($tat === 'kuendigen') {
                // Der Kunde kuendigt selbst. Das Enddatum rechnet Abo aus, der
                // Kunde hat es vor dem Klick gesehen, und die Bestaetigung geht
                // von dort aus raus — hier steht keine Logik doppelt.
                $abo = sicherLesen(fn() => Abo::fuerKunde((int) $kunde['id']), null);
                if ($abo && in_array((string) $abo['status'], ['aktiv', 'angelegt'], true)) {
                    $e = Abo::kuendigen((int) $abo['id'], 'kunde');
                    $meldung = str_replace('{datum}', Fmt::datum($e['ende']),
                        Texte::h(Texte::KUNDE['gekuendigt'] ?? [], $sprache, 'Kündigung ist angekommen.'));
                }

            } elseif ($tat === 'datei') {
                Ablage::annehmen($_FILES['datei'] ?? [], $pid ? (int) $pid : null, (int) $kunde['id'], 'kunde');
                Events::melden('datei_neu', 'Neue Datei vom Kunden', 'info',
                    (string) ($kunde['company'] ?: $kunde['name']) . ' — '
                        . (string) ($_FILES['datei']['name'] ?? ''),
                    '/kunden/' . (int) $kunde['id']);
                $meldung = Texte::h(Texte::PROJEKT['dateiOk'] ?? [], $sprache, 'Danke, ist angekommen.');
            }
        } catch (Throwable $e) {
            // Die Meldung darf der Kunde sehen: Sie sagt ihm, was zu tun ist
            // ("zu groß", "Format nicht angenommen") — keine Serverinterna.
            $fehler[] = $e->getMessage();
        }
    }
}

/* ---------- Was auf der Seite steht ---------- */
$seite = $kunde ? Kundenzugang::seite($kunde) : null;
$v     = $seite['vorgang'] ?? null;
$stufe = $seite['stufe'] ?? 'anfrage';
$pid   = $v['projekt_id'] ?? null;

$nachrichten = $kunde ? (array) sicherLesen(fn() => Db::all(
    'SELECT * FROM messages WHERE customer_id = ? ORDER BY created_at, id LIMIT 100', [(int) $kunde['id']])) : [];
$dateien = $kunde ? (array) sicherLesen(fn() => Db::all(
    'SELECT * FROM files WHERE customer_id = ? ORDER BY id DESC LIMIT 30', [(int) $kunde['id']])) : [];
$belege = $kunde ? (array) sicherLesen(fn() => Db::all(
    "SELECT * FROM invoices WHERE customer_id = ? AND (issued_at IS NOT NULL OR status <> 'entwurf')
      ORDER BY id DESC", [(int) $kunde['id']])) : [];
$fragebogen = $pid ? sicherLesen(fn() => Db::one(
    'SELECT * FROM questionnaires WHERE project_id = ?', [(int) $pid]), null) : null;

/* Der Schluessel des Fragebogens. Er kann fehlen — etwa direkt nachdem der
   Zugang zurueckgezogen wurde. Dann entsteht hier ein frischer, statt dass
   der Knopf ohne Erklaerung verschwindet. */
$fbToken = '';
if ($fragebogen && ($fragebogen['status'] ?? '') === 'offen') {
    $fbToken = (string) sicherLesen(fn() => Onboarding::token((int) $fragebogen['id']), '');
}

function sicherLesen(callable $fn, mixed $ersatz = []): mixed {
    try { return $fn(); } catch (Throwable $e) { return $ersatz; }
}

/** Die offene Zahlung, auf die der Kunde gerade schaut. */
$offen = null;
foreach ((array) ($v['zahlungen'] ?? []) as $z) {
    if ($z['status'] !== 'bezahlt' && !empty($z['link_url'])) { $offen = $z; break; }
}

Csrf::feld();   // erzeugt das Sitzungsgeheimnis, falls noch keines da ist
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
  /* Die Fortschrittsleiste: waagerecht, damit sie auf dem Handy nicht
     die halbe Seite frisst. Sieben Punkte, der aktuelle traegt die Farbe. */
  .weg{display:flex;gap:5px;margin:0 0 22px;list-style:none;padding:0}
  .weg li{flex:1 1 0;min-width:0;font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;
    color:var(--leise);padding-top:8px;border-top:3px solid var(--linie);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .weg li.durch{border-top-color:var(--blau);color:var(--dim)}
  .weg li.jetzt{border-top-color:var(--cyan);color:var(--text);font-weight:650}
  .wegzahl{font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise);
    margin:0 0 20px}
  /* Auf dem Handy sind sieben Beschriftungen sieben Wortanfaenge mit
     Auslassungspunkten — also weg damit. Die Balken bleiben, und darunter
     steht in Worten, der wievielte Schritt es ist. Die Ueberschrift im
     Kasten sagt ohnehin, welcher. */
  @media (max-width:640px){
    .weg{margin-bottom:9px}
    .weg li{font-size:0;padding-top:0;height:3px;border-top-width:3px}
  }
  @media (min-width:641px){ .wegzahl{display:none} }
  /* Der eine Kasten, der sagt, was dran ist. */
  .dran{border:1px solid var(--linie2);border-radius:16px;padding:20px 22px;margin-bottom:20px;
    background:linear-gradient(135deg,rgba(6,72,232,.16),rgba(31,232,255,.05))}
  .dran.warten{background:none}
  .dran .wer{font-size:11.5px;letter-spacing:.09em;text-transform:uppercase;color:var(--cyan);margin-bottom:8px}
  .dran.warten .wer{color:var(--leise)}
  .dran h2{font-size:19px;margin:0 0 8px;font-stretch:100%}
  .dran p{color:var(--dim);font-size:14.5px;line-height:1.6;margin:0 0 16px}
  .dran .tun{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  details.klapp{border:1px solid var(--linie);border-radius:14px;padding:13px 16px;margin-bottom:12px}
  details.klapp>summary{cursor:pointer;font-weight:650;font-size:15px;list-style:none}
  details.klapp>summary::-webkit-details-marker{display:none}
  details.klapp>summary::before{content:"+ ";color:var(--leise)}
  details.klapp[open]>summary::before{content:"– "}
  .mini{color:var(--leise);font-size:12.5px;line-height:1.55}
  /* Der Kasten fuer etwas, das es noch nicht gibt: dieselbe Form wie die
     anderen, nur ohne Griff daran. Er soll erwartet aussehen, nicht defekt. */
  .klapp.ruht{border:1px dashed var(--linie);border-radius:14px;padding:13px 16px;margin-bottom:12px;opacity:.72}
  .klapp.ruht .summe{font-weight:650;font-size:15px;color:var(--leise)}
  .klapp.ruht .summe::before{content:"○ ";color:var(--linie2)}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($panne || !$kunde): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($T('nichtGefunden')) ?></div>
    <a class="knopf haupt" href="<?= $h($basis) ?>">Vecom Design</a>
  </div>
<?php else: ?>

  <div class="kopfzeile">
    <h1 style="font-size:21px"><?= $h(str_replace('{name}',
        explode(' ', (string) $kunde['name'])[0], $T('hallo'))) ?></h1>
  </div>

  <?php foreach ($fehler as $x): ?><div class="hinweis schlecht"><?= $h($x) ?></div><?php endforeach; ?>
  <?php if ($meldung): ?><div class="hinweis gut"><?= $h($meldung) ?></div><?php endif; ?>

  <?php /* ---------- Wo er steht ---------- */ ?>
  <ul class="weg">
    <?php foreach (Kundenzugang::REIHE as $i => $s): ?>
      <li class="<?= $i < $seite['stufe_nr'] ? 'durch' : ($i === $seite['stufe_nr'] ? 'jetzt' : '') ?>"><?= $h($TS($s, 'kurz')) ?></li>
    <?php endforeach; ?>
  </ul>
  <div class="wegzahl"><?= $h(strtr(Texte::h(Texte::SEITE['schritt'] ?? [], $sprache, 'Schritt {n} von {g}'),
      ['{n}' => (string) ($seite['stufe_nr'] + 1), '{g}' => (string) count(Kundenzugang::REIHE)])) ?></div>

  <?php /* ---------- Der eine Schritt ---------- */ ?>
  <div class="dran <?= $seite['dran'] === 'kunde' ? '' : 'warten' ?>">
    <div class="wer"><?= $h($seite['dran'] === 'kunde' ? $T('duBistDran')
        : ($seite['dran'] === 'niemand' ? $T('nichtsOffen') : $T('wirSindDran'))) ?></div>
    <h2><?= $h($TS($stufe)) ?></h2>
    <p><?= $h($TS($stufe, 'text')) ?></p>

    <div class="tun">
      <?php if ($stufe === 'angebot' && $offen): ?>
        <a class="knopf haupt" href="<?= $h((string) $offen['link_url']) ?>">
          <?= $h((string) ($offen['bezeichnung'] ?: 'Zahlung')) ?> ·
          <?= Fmt::geld((int) $offen['amount_cents'], (string) $offen['currency']) ?></a>

      <?php elseif ($stufe === 'angaben' && $fragebogen && $fbToken !== ''): ?>
        <?php
          // Steht schon etwas drin, heisst der Knopf "weiter ausfuellen" — und
          // daneben, wie weit es ist. Das ist der Unterschied zwischen "noch
          // so eine Aufgabe" und "gleich geschafft".
          $fbDaten = [];
          if (!empty($fragebogen['data'])) { $fbDaten = json_decode((string) $fragebogen['data'], true) ?: []; }
          $fbVoll  = count(array_filter($fbDaten, static fn($w) => trim((string) $w) !== ''));
          $fbAlle  = count(Onboarding::felder());
        ?>
        <a class="knopf haupt" href="/fragebogen.php?t=<?= $h($fbToken) ?>&amp;lang=<?= $h($sprache) ?>">
          <?= $h($fbVoll > 0
              ? Texte::h(Texte::SEITE['weiterMachen'] ?? [], $sprache, 'Fragebogen weiter ausfüllen')
              : Texte::h(Texte::PROJEKT['fragebogen'] ?? [], $sprache, 'Zum Fragebogen')) ?></a>
        <?php if ($fbVoll > 0 && $fbAlle > 0): ?>
          <span class="mini" style="flex-basis:100%"><?= (int) $fbVoll ?> / <?= (int) $fbAlle ?>
            <?= $h(Texte::h(['it' => 'campi compilati', 'de' => 'Felder ausgefüllt', 'en' => 'fields filled in'], $sprache)) ?></span>
        <?php endif; ?>

      <?php elseif ($stufe === 'entwurf'): ?>
        <?php if ($seite['vorschau'] !== ''): ?>
          <a class="knopf haupt" href="<?= $h($seite['vorschau']) ?>" target="_blank" rel="noopener">
            <?= $h($T('entwurfAnsehen')) ?></a>
        <?php endif; ?>
        <form method="post" action="<?= $h($hier) ?>" style="display:inline">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="freigabe">
          <button class="knopf"><?= $h(Texte::h(Texte::PROJEKT['freigeben'] ?? [], $sprache, 'Passt so')) ?></button>
        </form>

      <?php elseif ($stufe === 'freigabe' && $offen): ?>
        <a class="knopf haupt" href="<?= $h((string) $offen['link_url']) ?>">
          <?= $h((string) ($offen['bezeichnung'] ?: 'Restzahlung')) ?> ·
          <?= Fmt::geld((int) $offen['amount_cents'], (string) $offen['currency']) ?></a>

      <?php elseif (($stufe === 'online' || $stufe === 'fertig') && $seite['live'] !== ''): ?>
        <a class="knopf haupt" href="<?= $h($seite['live']) ?>" target="_blank" rel="noopener">
          <?= $h($T('seiteAnsehen')) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ---------- Wie war es? Erst wenn die Seite steht. ---------- */ ?>
  <?php $stimme = $kunde ? sicherLesen(fn() => Stimme::vonKunde((int) $kunde['id']), null) : null; ?>
  <?php if ($kunde && in_array($stufe, ['online', 'fertig'], true)): ?>
    <details class="klapp" <?= $stimme ? '' : 'open' ?>>
      <summary><?= $h($T('stimme')) ?></summary>
      <?php if ($stimme): ?>
        <p class="mini" style="margin-top:10px"><?= $h($T('stimmeSchon')) ?></p>
        <div style="padding:11px 13px;border:1px solid var(--linie);border-radius:12px;margin-top:9px;
                    white-space:pre-wrap;font-size:14.5px;line-height:1.6;color:var(--dim)"><?= $h((string) $stimme['text']) ?></div>
      <?php else: ?>
        <p class="mini" style="margin-top:10px"><?= $h($T('stimmeHilfe')) ?></p>
        <form method="post" action="<?= $h($hier) ?>" style="margin-top:12px">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="stimme">
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                 style="position:absolute;left:-9999px" aria-hidden="true">
          <div class="feld">
            <label for="sterne"><?= $h($T('stimmeSterne')) ?></label>
            <select id="sterne" name="sterne" style="width:auto;min-width:200px">
              <option value="5">★★★★★</option>
              <option value="4">★★★★</option>
              <option value="3">★★★</option>
              <option value="2">★★</option>
              <option value="1">★</option>
            </select>
          </div>
          <div class="feld"><textarea name="text" rows="5" required
            placeholder="<?= $h($T('stimmeFeld')) ?>"></textarea></div>
          <label style="display:flex;gap:9px;align-items:flex-start;margin-bottom:12px;cursor:pointer">
            <input type="checkbox" name="erlaubnis" value="1" style="width:auto;margin-top:3px">
            <span style="font-size:14px;line-height:1.5"><?= $h($T('stimmeErlaubnis')) ?><br>
              <span class="mini"><?= $h($T('stimmeErlaubnisNein')) ?></span></span>
          </label>
          <button class="knopf haupt"><?= $h($T('stimmeSenden')) ?></button>
        </form>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <?php /* ---------- Deine Betreuung: der zweite Vertrag ---------- */ ?>
  <?php $abo = $kunde ? sicherLesen(fn() => Abo::fuerKunde((int) $kunde['id']), null) : null; ?>
  <?php if ($abo && (string) $abo['status'] !== 'angelegt'): ?>
    <?php $vor = sicherLesen(fn() => Abo::kuendigungsvorschau($abo), ['moeglich' => false, 'ende' => '']); ?>
    <details class="klapp">
      <summary><?= $h($T('betreuung')) ?><?php if ($abo['laeuft_bis']): ?>
        <span class="mini"> · <?= $h(str_replace('{datum}', Fmt::datum((string) $abo['laeuft_bis']), $T('laeuftBis'))) ?></span>
      <?php endif; ?></summary>

      <div style="margin-top:12px">
        <div style="font-size:17px;font-weight:650"><?= $h((string) $abo['paket_name']) ?></div>
        <div style="color:var(--dim);margin-top:4px">
          <?= Fmt::geld((int) $abo['betrag_cents'], (string) $abo['currency']) ?> <?= $h($T('betreuungMtl')) ?></div>
        <p class="mini" style="margin-top:10px">
          <?= $h(str_replace('{datum}', Fmt::datum((string) $abo['beginn']), $T('betreuungSeit'))) ?><br>
          <?= $h(str_replace('{datum}', Fmt::datum((string) $abo['mindestlaufzeit_bis']), $T('betreuungMind'))) ?>
        </p>

        <?php if ((string) $abo['status'] === 'beendet'): ?>
          <p class="mini"><?= $h(str_replace('{datum}', Fmt::datum((string) $abo['laeuft_bis']), $T('betreuungWeg'))) ?></p>

        <?php elseif ((string) $abo['status'] === 'gekuendigt'): ?>
          <div class="hinweis gut" style="margin-top:12px">
            <?= $h(str_replace('{datum}', Fmt::datum((string) $abo['laeuft_bis']), $T('gekuendigt'))) ?></div>

        <?php elseif (!empty($vor['moeglich'])): ?>
          <?php /* Das Datum steht DA, bevor er klickt. Eine Kuendigung, deren
                   Wirkung man erst hinterher erfaehrt, ist eine Zumutung. */ ?>
          <p class="mini" style="margin-top:12px">
            <?= $h(str_replace('{datum}', Fmt::datum((string) $vor['ende']), $T('kuendigenWann'))) ?></p>
          <form method="post" action="<?= $h($hier) ?>" style="margin-top:10px"
                onsubmit="return confirm('<?= $h($T('kuendigenSicher')) ?>')">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="kuendigen">
            <button class="knopf"><?= $h($T('kuendigen')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </details>
  <?php endif; ?>

  <?php /* ---------- Deine Website: Entwurf und, sobald da, die echte ---------- */ ?>
  <?php /* Noch nichts freigeschaltet: Der Kasten steht trotzdem da, nur grau.
           Ab der Stufe, auf der wir bauen — vorher waere er verfrueht. */ ?>
  <?php if ($seite['vorschau'] === '' && $seite['live'] === ''
            && in_array($stufe, ['arbeit', 'entwurf'], true)): ?>
    <div class="klapp ruht">
      <div class="summe"><?= $h($T('deineSeite')) ?></div>
      <p class="mini" style="margin:8px 0 0"><?= $h($T('nochNichts')) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($seite['vorschau'] !== '' || $seite['live'] !== ''): ?>
    <details class="klapp" <?= $stufe === 'online' || $stufe === 'fertig' ? 'open' : '' ?>>
      <summary><?= $h($T('deineSeite')) ?></summary>
      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($seite['live'] !== ''): ?>
          <a class="knopf haupt" href="<?= $h($seite['live']) ?>" target="_blank" rel="noopener"><?= $h($T('seiteAnsehen')) ?></a>
        <?php endif; ?>
        <?php if ($seite['vorschau'] !== ''): ?>
          <a class="knopf" href="<?= $h($seite['vorschau']) ?>" target="_blank" rel="noopener"><?= $h($T('entwurfAnsehen')) ?></a>
        <?php endif; ?>
      </div>
      <p class="mini" style="margin-top:12px"><?= $h($T('aenderungHilfe')) ?></p>
      <form method="post" action="<?= $h($hier) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="aenderung">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
               style="position:absolute;left:-9999px" aria-hidden="true">
        <div class="feld"><textarea name="text" rows="3" placeholder="<?= $h($T('aenderung')) ?>"></textarea></div>
        <button class="knopf haupt"><?= $h(Texte::h(Texte::PROJEKT['senden'] ?? [], $sprache, 'Absenden')) ?></button>
      </form>
    </details>
  <?php endif; ?>

  <?php /* ---------- Gespräch ---------- */ ?>
  <details class="klapp" <?= $nachrichten ? '' : 'open' ?>>
    <summary><?= $h($T('gespraech')) ?><?= $nachrichten ? ' (' . count($nachrichten) . ')' : '' ?></summary>
    <p class="mini" style="margin-top:10px"><?= $h($T('gespraechHilfe')) ?></p>
    <?php foreach ($nachrichten as $m): ?>
      <div style="padding:11px 13px;border:1px solid var(--linie);border-radius:12px;margin:9px 0;
                  <?= $m['sender'] === 'kunde' ? '' : 'background:var(--flaeche2)' ?>">
        <div style="font-size:12.5px;font-weight:650;display:flex;justify-content:space-between;gap:10px;margin-bottom:5px">
          <span><?= $m['sender'] === 'kunde' ? $h(explode(' ', (string) $kunde['name'])[0]) : 'Vecom Design' ?></span>
          <span style="color:var(--leise);font-weight:400"><?= $h(Fmt::datum($m['created_at'])) ?></span></div>
        <?php if (!empty($m['betreff'])): ?>
          <div style="font-size:13px;color:var(--cyan);margin-bottom:5px"><?= $h((string) $m['betreff']) ?></div>
        <?php endif; ?>
        <div style="white-space:pre-wrap;font-size:14.5px;line-height:1.6;color:var(--dim)"><?= $h((string) $m['body']) ?></div>
      </div>
    <?php endforeach; ?>
    <form method="post" action="<?= $h($hier) ?>" style="margin-top:12px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachricht">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
             style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="feld"><textarea name="text" rows="4" required
        placeholder="<?= $h(Texte::h(Texte::PROJEKT['schreiben'] ?? [], $sprache, 'Nachricht schreiben')) ?>"></textarea></div>
      <button class="knopf haupt"><?= $h(Texte::h(Texte::PROJEKT['senden'] ?? [], $sprache, 'Absenden')) ?></button>
    </form>
  </details>

  <?php /* ---------- Unterlagen ---------- */ ?>
  <?php if ($belege): ?>
    <details class="klapp">
      <summary><?= $h($T('unterlagen')) ?> (<?= count($belege) ?>)</summary>
      <?php foreach ($belege as $r): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;
                    padding:11px 0;border-top:1px solid var(--linie)">
          <span><?= $h((string) $r['invoice_no']) ?><br>
            <small style="color:var(--leise)"><?= Fmt::geld((int) $r['total_cents'], (string) $r['currency']) ?>
              · <?= $h(Fmt::datum($r['issued_at'])) ?></small></span>
          <a class="knopf" href="<?= $h($hier) ?>&amp;beleg=<?= (int) $r['id'] ?>">PDF</a>
        </div>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>

  <?php /* ---------- Dateien ---------- */ ?>
  <details class="klapp">
    <summary><?= $h($T('dateien')) ?><?= $dateien ? ' (' . count($dateien) . ')' : '' ?></summary>
    <p class="mini" style="margin-top:10px"><?= $h($T('dateienHilfe')) ?></p>
    <?php foreach ($dateien as $d): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;
                  padding:10px 0;border-top:1px solid var(--linie)">
        <span><?= $h((string) $d['orig_name']) ?><br>
          <small style="color:var(--leise)"><?= $h(Fmt::bytes((int) $d['size_bytes'])) ?>
            · <?= $h(Fmt::datum($d['created_at'])) ?></small></span>
        <?php if ($d['uploaded_by'] !== 'kunde'): ?>
          <a class="knopf" href="<?= $h($hier) ?>&amp;datei=<?= (int) $d['id'] ?>">↓</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <form method="post" action="<?= $h($hier) ?>" enctype="multipart/form-data"
          style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei">
      <input type="file" name="datei" required style="max-width:230px">
      <button class="knopf"><?= $h($T('hochladen')) ?></button>
    </form>
  </details>

  <p class="mini" style="margin-top:26px;text-align:center"><?= $h($T('lesenswert')) ?></p>

<?php endif; ?>
</div>
</body>
</html>
