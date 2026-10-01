<?php
/**
 * Das kleine Fenster des Ausfüll-Knopfs (Verzeichnisse, 01.10.2026).
 *
 * Geöffnet vom Lesezeichen „Vecom eintragen“ auf der Eintragsseite eines
 * Verzeichnisses. Sucht die Stelle zur Adresse, legt bei Bedarf ihre eigenen
 * Links an (ein Formular, das sich selbst abschickt — dieselbe Tat wie der
 * Knopf „Eintrag vorbereiten“) und schickt die Angaben per postMessage an
 * genau die Seite, die es geöffnet hat. Danach: „Eingereicht“ von hier aus.
 *
 * Erwartet: $afHost, $afOrigin, $afOk, $afE (Stelle oder null), $afSp.
 */
$e = $afE;
/* Nach dem Anlegen der Links zurück und ausfüllen; nach dem Speichern eines Stands (s=1) nicht noch einmal. */
$zurueckPfad = static fn(bool $fuellen): string => 'ausfuellen?' . http_build_query(['h' => $afHost, 'o' => $afOrigin, 'sp' => $afSp] + ($fuellen ? [] : ['s' => 1]), '', '&', PHP_QUERY_RFC3986);
$brauchtLinks = $afOk && $e !== null && empty($e['kampagne_id']) && !in_array($e['status'], ['spaeter', 'nein', 'abgelehnt'], true);
$gesperrt = $e !== null && in_array($e['status'], ['spaeter', 'nein'], true);
$kostet = $e !== null && !(int) $e['kostenlos'];
$daten = $afOk && !$brauchtLinks ? Verzeichnisse::ausfuellDaten($e, $afSp) : null;
/* Von selbst ausfüllen nur, wenn nichts dagegen spricht — und nicht nach dem Speichern eines Stands (s=1). */
$auto = $daten !== null && $e !== null && !$gesperrt && !$kostet && empty($_GET['s']);
$gut = (string) ($_SESSION['gut'] ?? ''); $fehler = (string) ($_SESSION['fehler'] ?? ''); unset($_SESSION['gut'], $_SESSION['fehler']);
$json = static fn($w): string => (string) json_encode($w, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$formKopf = static function (string $tat, int $id) use ($zurueckPfad): string {
    return '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '"><input type="hidden" name="tat" value="' . Fmt::h($tat) . '">'
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="zurueck" value="' . Fmt::h($zurueckPfad($tat === 'verzeichnis_vorbereiten')) . '">';
};
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vecom eintragen</title>
<link rel="stylesheet" href="<?= Fmt::h(url('assets/admin.css')) ?>">
<style>
  body{margin:0;padding:18px 18px 24px;min-height:100vh;box-sizing:border-box}
  h1{font-size:18px;margin:0 0 4px} h1 span{color:var(--gold,#d9b46a)}
  .af-weg{color:var(--leise);font-size:13px;margin:0 0 14px;word-break:break-all}
  .af-box{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin:0 0 12px;line-height:1.5;font-size:14px}
  .af-box b{color:var(--text)}
  .af-knoepfe{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0}
  .af-fein{font-size:12.5px;color:var(--leise);margin:8px 0 0}
  form{margin:0} input[type=url]{width:100%;box-sizing:border-box;margin:8px 0 0}
</style>
</head>
<body>
<h1>Vecom <span>eintragen</span></h1>
<p class="af-weg"><?= Fmt::h($afHost !== '' ? $afHost : '—') ?></p>
<?php if ($gut !== ''): ?><div class="hinweis gut"><?= Fmt::h($gut) ?></div><?php endif; ?>
<?php if ($fehler !== ''): ?><div class="hinweis schlecht"><?= Fmt::h($fehler) ?></div><?php endif; ?>

<?php if (!$afOk): ?>
  <div class="af-box">Diese Seite kann ich nicht ausfüllen. Der Knopf arbeitet nur auf https-Seiten und nur, wenn er dort über das Lesezeichen gedrückt wird.</div>

<?php elseif ($brauchtLinks): ?>
  <div class="af-box"><b><?= Fmt::h((string) $e['name']) ?></b><br>Lege eigene Links für diese Stelle an — dann füllt der Knopf aus.</div>
  <form id="af-vorbereiten" method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>">
    <?= $formKopf('verzeichnis_vorbereiten', (int) $e['id']) ?>
    <noscript><button class="knopf haupt">Links anlegen und ausfüllen</button></noscript>
  </form>
  <script>document.getElementById('af-vorbereiten').submit();</script>

<?php else: ?>
  <?php if ($e !== null): ?>
    <div class="af-box">
      <b><?= Fmt::h((string) $e['name']) ?></b> · <?= Fmt::h(Verzeichnisse::STATUS[(string) $e['status']] ?? (string) $e['status']) ?> · <?= Fmt::h(Verzeichnisse::SPRACHEN[(string) $e['sprache']] ?? '') ?>
      <?php if ((string) $e['regeln'] !== ''): ?><div class="af-fein"><?= Fmt::h((string) $e['regeln']) ?></div><?php endif; ?>
    </div>
    <?php if ($gesperrt): ?>
      <div class="hinweis schlecht">Diese Stelle steht auf „<?= Fmt::h(Verzeichnisse::STATUS[(string) $e['status']]) ?>“. Ausgefüllt wird nur, wenn du es trotzdem willst.</div>
    <?php elseif ($kostet): ?>
      <div class="hinweis schlecht">Achtung: <?= Fmt::h((string) $e['kosten']) ?>. Ausgefüllt wird nur auf deinen Klick — und nichts buchen, was Geld kostet.</div>
    <?php endif; ?>
    <div class="af-box" id="af-stand"><?= $auto ? 'Ausgefüllt — die Felder sind gold umrandet. Bitte prüfen, ein Captcha lösen und auf der Seite absenden.' : 'Auf „Ausfüllen“ drücken, dann prüfen und auf der Seite absenden.' ?>
      <div class="af-fein">Mehrstufiges Formular? Auf jeder Stufe hier „Nochmal ausfüllen“ oder das Lesezeichen noch einmal drücken.</div>
      <div class="af-knoepfe"><button class="knopf<?= $auto ? '' : ' haupt' ?>" type="button" id="af-senden"><?= $auto ? 'Nochmal ausfüllen' : 'Ausfüllen' ?></button></div>
    </div>
    <?php if (in_array($e['status'], ['offen', 'eingereicht'], true)): ?>
    <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" class="af-box">
      <?= $formKopf('verzeichnis_stand', (int) $e['id']) ?>
      <?php if ($e['status'] === 'offen'): ?>
        Abgeschickt? Dann hier vermerken — die Verwaltung erinnert in einer Woche, nachzusehen, ob der Eintrag online ist.
        <div class="af-knoepfe"><button class="knopf haupt" name="status" value="eingereicht">Eingereicht</button></div>
      <?php else: ?>
        Eingereicht am <?= Fmt::h(date('d.m.Y', strtotime((string) ($e['eingereicht_am'] ?? $e['status_am'])))) ?>. Ist der Eintrag zu sehen?
        <input type="url" name="eintrag_url" placeholder="Adresse des Eintrags (freiwillig)" aria-label="Adresse des Eintrags">
        <div class="af-knoepfe"><button class="knopf haupt" name="status" value="online">Ist online</button><button class="knopf" name="status" value="abgelehnt">Abgelehnt</button></div>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  <?php else: ?>
    <div class="af-box">Für diese Seite steht keine Stelle in der Liste. Mit den allgemeinen Angaben ausfüllen (Website ohne eigenen Link)?
      <form method="get" action="<?= Fmt::h(url('ausfuellen')) ?>" class="af-knoepfe">
        <input type="hidden" name="h" value="<?= Fmt::h($afHost) ?>"><input type="hidden" name="o" value="<?= Fmt::h($afOrigin) ?>">
        <select name="sp" aria-label="Sprache" onchange="this.form.submit()"><?php foreach (Verzeichnisse::SPRACHEN as $s => $w): ?><option value="<?= Fmt::h($s) ?>"<?= $s === $afSp ? ' selected' : '' ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select>
        <button class="knopf haupt" type="button" id="af-senden">Ausfüllen</button>
      </form>
      <div class="af-fein">Damit Besuche dieser Stelle zählen: in der Verwaltung unter Verzeichnisse aufnehmen, dann hier neu öffnen. <a href="<?= Fmt::h(url('verzeichnisse') . '#neu') ?>" target="_blank" rel="noopener">Aufnehmen ↗</a></div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<p class="af-fein"><a href="<?= Fmt::h(url('verzeichnisse')) ?>" target="_blank" rel="noopener">Verzeichnisse öffnen ↗</a> · <a href="#" onclick="window.close();return false">Fenster schließen</a></p>

<?php if ($daten !== null): ?>
<script>
(function () {
  var DATEN = <?= $json($daten) ?>, ZIEL = <?= $json($afOrigin) ?>, AUTO = <?= $auto ? 'true' : 'false' ?>;
  var stand = document.getElementById('af-stand');
  function senden() {
    /* Nur an genau die Seite, die das Fenster geöffnet hat (ZIEL ist geprüft). */
    if (!window.opener) {
      if (stand) { stand.firstChild.textContent = 'Die Eintragsseite ist nicht mehr verbunden — dort das Lesezeichen noch einmal drücken.'; }
      return;
    }
    window.opener.postMessage({ vecomAusfuellen: DATEN }, ZIEL);
  }
  var k = document.getElementById('af-senden');
  if (k) { k.addEventListener('click', senden); }
  if (AUTO) { senden(); }
})();
</script>
<?php endif; ?>
</body>
</html>
