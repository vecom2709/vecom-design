<?php
/* ==========================================================================
   Kunden finden (26.09.2026): Firmen-Finder, Website-Schnellcheck,
   Gesprächsleitfaden. Eingebunden aus partner.php ($p, $sprache, $T, $h,
   $selbst, $meldung, $checkNeu).
   ========================================================================== */
$fiOrt = trim((string) ($_GET['fi_ort'] ?? ''));
$fiBranche = (string) ($_GET['fi_branche'] ?? '');
$fiErg = $fiOrt !== '' ? PartnerRecherche::suchen((int) $p['id'], $fiOrt, $fiBranche, $sprache, !isset($_GET['fi_nz']), true) : null;
$fiMeine = PartnerRecherche::meine((int) $p['id'], $sprache);
$branchenListe = Akquise::branchen();
$fiMeldung = in_array($meldung, ['fi_weg', 'fi_vecom', 'fi_voll'], true) ? $meldung : '';
$feFehler = Texte::PARTNER['fe_fehler'][$meldung] ?? null;
$feWahl = $feFehler ? $_POST : [];
$ckMeldung = in_array($meldung, ['ck_adresse', 'ck_genug'], true) ? $meldung : '';
$ckLetzte = PartnerCheck::letzte((int) $p['id']);
$datum = static fn(string $d): string => date('d.m.Y', strtotime($d));
$firmaZeile = static function (array $f, bool $meine) use ($h, $T, $selbst, $datum, $fiOrt, $fiBranche): string {
    $o = '<li class="firma"><div class="firma__kopf"><b>' . $h($f['name']) . '</b><span class="chance ' . $h($f['chance']) . '">' . $h($T('fi_chance_' . $f['chance'])) . '</span></div>'
       . '<small>' . $h($f['branche']) . ' · ' . $h(trim($f['adresse'] !== '' ? $f['adresse'] . ', ' . $f['ort'] : $f['ort'], ', ')) . ($f['domain'] !== '' ? ' · ' . $h($f['domain']) : '') . '</small>';
    $o .= '<div class="firma__tat">';
    if ($meine || ($f['stand'] ?? '') === 'meine') {
        $o .= '<span class="klein" style="margin:0">' . $h(strtr($T('fi_bis'), ['{datum}' => $datum((string) $f['bis'])])) . '</span>'
            . '<form method="post" action="' . $h($selbst(['fi_ort' => $fiOrt, 'fi_branche' => $fiBranche, 'fi_nz' => 1])) . '#recherche">'
            . '<input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tat" value="fi_frei">'
            . '<input type="hidden" name="firma" value="' . (int) $f['id'] . '"><button class="knopf klein-knopf" type="submit">' . $h($T('fi_frei')) . '</button></form>';
    } elseif (($f['stand'] ?? '') === 'vecom') {
        $o .= '<span class="klein" style="margin:0">' . $h($T('fi_vecom')) . '</span>';
    } else {
        $o .= '<form method="post" action="' . $h($selbst(['fi_ort' => $fiOrt, 'fi_branche' => $fiBranche, 'fi_nz' => 1])) . '#recherche">'
            . '<input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tat" value="fi_reserv">'
            . '<input type="hidden" name="firma" value="' . (int) $f['id'] . '"><button class="knopf klein-knopf" type="submit">' . $h($T('fi_reserv')) . '</button></form>';
    }
    return $o . '</div></li>';
};
?>
<style>
  .firmen{list-style:none;padding:0;margin:10px 0 0;display:grid;gap:8px}
  .firma{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;display:grid;gap:4px}
  .firma__kopf{display:flex;gap:8px;align-items:baseline;justify-content:space-between;flex-wrap:wrap}
  .firma small{color:var(--leise);font-size:12.5px;line-height:1.45}
  .firma__tat{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:4px}
  .firma__tat form{display:block}
  .chance{font-size:11.5px;padding:2px 9px;border-radius:999px;border:1px solid var(--linie);white-space:nowrap;color:var(--dim)}
  .chance.hoch{border-color:rgba(241,211,139,.6);color:var(--cyan)}
  .ck-knoepfe{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .ck-knoepfe form{display:inline}
  .ck-weg{color:var(--dim) !important}
  .klein-knopf{min-height:34px !important;padding:6px 12px !important;font-size:13px !important}
  .reihe{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
  .reihe > div{flex:1 1 160px;display:flex;flex-direction:column;gap:4px}
  .reihe select{font-size:16px;padding:11px 12px;border-radius:10px}
  .ck-ergebnis{border:1px solid rgba(241,211,139,.45);border-radius:12px;padding:12px;margin-top:10px}
  .ck-ergebnis ul{list-style:none;padding:0;margin:6px 0 10px;display:grid;gap:4px;font-size:14px}
  .ampel{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle}
  .ampel.gut{background:#34d39b}.ampel.hinweis{background:#e8b64c}.ampel.schlecht{background:#ef6b5b}
  .leitfaden details{border-top:1px solid var(--linie);padding:10px 0}
  .leitfaden summary{cursor:pointer;font-size:14.5px;color:var(--text)}
  .leitfaden pre{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.65;color:var(--dim);margin:8px 0 0}
</style>

<div class="block pt" id="recherche">
  <h2><?= $h($T('re_titel')) ?></h2>

  <h3 class="md-h" style="margin-top:4px"><?= $h($T('ck_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('ck_text')) ?></p>
  <?php if (($_GET['m'] ?? '') === 'ck_weg_gut'): ?><div class="hinweis gut" role="status"><?= $h($T('ck_weg_gut')) ?></div><?php endif; ?>
  <?php if ($ckMeldung): ?><div class="hinweis schlecht" role="alert"><?= $h($T($ckMeldung)) ?></div><?php endif; ?>
  <form method="post" action="<?= $h($selbst()) ?>#recherche" data-warten="<?= $h($T('ck_laeuft')) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="check">
    <label for="ck_url"><?= $h($T('ck_feld')) ?></label>
    <div class="kopie"><input id="ck_url" type="text" name="url" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="300" required
        value="<?= $h((string) ($_POST['url'] ?? $_GET['ck_url'] ?? '')) ?>"><button class="knopf haupt" type="submit"><?= $h($T('ck_pruefen')) ?></button></div>
  </form>
  <?php if ($checkNeu): $ckE = $checkNeu['ergebnis']; $ckL = PartnerCheck::link($checkNeu['token']); ?>
    <div class="ck-ergebnis" role="status">
      <b><?= $h($T('ck_fertig')) ?> <?= $h($ckE['host']) ?></b>
      <ul>
        <?php foreach ($ckE['punkte'] as $pk): $K = Texte::PARTNER_CHECK['punkte'][$pk['was']]; ?>
          <li><span class="ampel <?= $h($pk['stand']) ?>" aria-hidden="true"></span><?= $h(Texte::h($K['titel'], $sprache)) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="knoepfe" style="margin-top:0">
        <a class="knopf haupt" href="https://wa.me/?text=<?= rawurlencode($T('ck_wa_text') . $ckL) ?>" target="_blank" rel="noopener"><?= $h($T('ck_wa')) ?></a>
        <a class="knopf" href="<?= $h($ckL) ?>" target="_blank" rel="noopener"><?= $h($T('ck_oeffnen')) ?></a>
        <button class="knopf" type="button" data-kopie-text="<?= $h($ckL) ?>"><?= $h($T('kopieren')) ?></button>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($ckLetzte && !$checkNeu): ?>
    <p class="md-l"><?= $h($T('ck_letzte')) ?></p>
    <ul class="firmen">
      <?php foreach ($ckLetzte as $c): ?>
        <li class="firma"><div class="firma__kopf"><b><?= $h($c['host']) ?></b><small><?= $h($datum($c['created_at'])) ?></small></div>
          <small><?= $h(strtr($T('ck_punkte'), ['{n}' => (string) $c['schlecht']])) ?> · <?= $h(strtr($T('ck_aufrufe'), ['{n}' => (string) $c['aufrufe']])) ?></small>
          <?php $ckL2 = PartnerCheck::link($c['token']); ?>
          <div class="firma__tat"><a href="<?= $h($ckL2) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-size:13.5px"><?= $h($T('ck_oeffnen')) ?> →</a>
            <span class="ck-knoepfe">
              <a class="knopf klein-knopf" href="https://wa.me/?text=<?= rawurlencode($T('ck_wa_text') . $ckL2) ?>" target="_blank" rel="noopener">WhatsApp</a>
              <button class="knopf klein-knopf" type="button" data-kopie-text="<?= $h($ckL2) ?>"><?= $h($T('kopieren')) ?></button>
              <button class="knopf klein-knopf" type="button" data-teilen-text="<?= $h($T('ck_wa_text') . $ckL2) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
              <form method="post" action="<?= $h($selbst()) ?>#recherche" onsubmit="return confirm(this.dataset.frage)" data-frage="<?= $h($T('ck_loeschen_frage')) ?>">
                <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ck_weg"><input type="hidden" name="token" value="<?= $h($c['token']) ?>">
                <button class="knopf klein-knopf ck-weg" type="submit"><?= $h($T('ck_loeschen')) ?></button></form>
            </span></div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3 class="md-h" style="margin-top:24px"><?= $h($T('fi_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('fi_text')) ?></p>
  <?php if ($fiMeldung): ?><div class="hinweis schlecht" role="alert"><?= $h($T($fiMeldung)) ?></div><?php endif; ?>
  <form method="get" action="/partner.php#recherche" data-warten="<?= $h($T('fi_laeuft')) ?>">
    <input type="hidden" name="t" value="<?= $h((string) $p['token']) ?>">
    <div class="reihe">
      <div><label for="fi_ort"><?= $h($T('fi_ort')) ?></label><input id="fi_ort" type="text" name="fi_ort" maxlength="80" required value="<?= $h($fiOrt) ?>" autocomplete="address-level2"></div>
      <div><label for="fi_branche"><?= $h($T('fi_branche')) ?></label>
        <select id="fi_branche" name="fi_branche"><option value=""><?= $h($T('fi_alle')) ?></option>
          <?php foreach ($branchenListe as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= $bk === $fiBranche ? ' selected' : '' ?>><?= $h(Akquise::branchenName($bk, $sprache)) ?></option><?php endforeach; ?>
        </select></div>
      <button class="knopf" type="submit"><?= $h($T('fi_suchen')) ?></button>
    </div>
  </form>
  <?php if ($fiErg !== null): ?>
    <?php $fiWeb = $fiErg['web'] ?? null; ?>
    <?php if ($fiWeb && $fiWeb['fehler'] !== null && $fiWeb['fehler'] !== 'läuft'): ?><div class="hinweis" style="margin-top:10px"><?= $h($T('fi_web_fehler')) ?></div>
    <?php elseif ($fiWeb && $fiWeb['gefragt'] && $fiWeb['neu'] > 0): ?><p class="klein" role="status"><?= $h(strtr($T('fi_web_neu'), ['{n}' => (string) $fiWeb['neu']])) ?></p><?php endif; ?>
    <?php if (!$fiErg['ok']): ?><div class="hinweis" style="margin-top:10px"><?= $h($T($fiErg['grund'] === 'fi_ort' ? 'fi_keine' : $fiErg['grund'])) ?></div>
    <?php elseif (!$fiErg['treffer']): ?><p class="klein"><?= $h($T('fi_keine')) ?></p>
    <?php else: ?>
      <ul class="firmen"><?php foreach ($fiErg['treffer'] as $f) { echo $firmaZeile($f, false); } ?></ul>
      <p class="klein"><?= $h($T('fi_hinweis')) ?></p>
      <p class="klein" style="margin-top:4px;font-size:11.5px"><?= $h($T('fi_osm')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($fiOrt !== ''): $fiLinks = PartnerRecherche::suchlinks($fiOrt, $fiBranche); if ($fiLinks): ?>
    <p class="md-l" style="margin-top:16px"><?= $h($T('fi_quellen')) ?></p>
    <p class="klein" style="margin-top:0"><?= $h($T('fi_quellen_text')) ?></p>
    <div class="ck-knoepfe" style="margin-top:6px">
      <?php foreach ($fiLinks as $fl): ?><a class="knopf klein-knopf" href="<?= $h($fl['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $h(Texte::h(Texte::PARTNER['fi_q'][$fl['art']], $sprache)) ?> ↗</a><?php endforeach; ?>
    </div>
  <?php endif; endif; ?>

  <details id="eintragen" style="margin-top:16px"<?= $feFehler || ($_GET['m'] ?? '') === 'fe_gut' ? ' open' : '' ?>>
    <summary style="cursor:pointer;color:var(--cyan);font-size:14.5px"><?= $h($T('fe_titel')) ?></summary>
    <?php if (($_GET['m'] ?? '') === 'fe_gut'): ?><div class="hinweis gut" role="status" style="margin-top:8px"><?= $h($T('fe_gut')) ?></div><?php endif; ?>
    <?php if ($feFehler): ?><div class="hinweis schlecht" role="alert" style="margin-top:8px"><?= $h(Texte::h($feFehler, $sprache)) ?></div><?php endif; ?>
    <form method="post" action="<?= $h($selbst()) ?>#eintragen" style="margin-top:10px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="fe_eintragen">
      <label for="fe_name"><?= $h($T('fe_name')) ?></label><input id="fe_name" type="text" name="name" required maxlength="160" value="<?= $h((string) ($feWahl['name'] ?? '')) ?>">
      <div class="reihe">
        <div><label for="fe_ort"><?= $h($T('fe_ort')) ?></label><input id="fe_ort" type="text" name="ort" required maxlength="80" value="<?= $h((string) ($feWahl['ort'] ?? $fiOrt)) ?>"></div>
        <div><label for="fe_branche"><?= $h($T('fe_branche')) ?></label>
          <select id="fe_branche" name="branche" required><option value=""></option>
            <?php foreach ($branchenListe as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= $bk === (string) ($feWahl['branche'] ?? $fiBranche) ? ' selected' : '' ?>><?= $h(Akquise::branchenName($bk, $sprache)) ?></option><?php endforeach; ?>
          </select></div>
        <div style="flex:0 1 90px"><label for="fe_land">Land</label><select id="fe_land" name="land"><option value="IT">IT</option><option value="DE"<?= ($feWahl['land'] ?? '') === 'DE' ? ' selected' : '' ?>>DE</option></select></div>
      </div>
      <label for="fe_adresse"><?= $h($T('fe_adresse')) ?></label><input id="fe_adresse" type="text" name="adresse" maxlength="200" value="<?= $h((string) ($feWahl['adresse'] ?? '')) ?>" autocomplete="off">
      <label for="fe_website"><?= $h($T('fe_website')) ?></label><input id="fe_website" type="text" name="website" maxlength="300" inputmode="url" autocapitalize="off" spellcheck="false" value="<?= $h((string) ($feWahl['website'] ?? '')) ?>" placeholder="www…">
      <button class="knopf" type="submit"><?= $h($T('fe_knopf')) ?></button>
    </form>
  </details>

  <?php if ($fiMeine): ?>
    <p class="md-l" style="margin-top:16px"><?= $h($T('fi_meine')) ?> (<?= count($fiMeine) ?>)</p>
    <ul class="firmen"><?php foreach ($fiMeine as $f) { echo $firmaZeile($f, true); } ?></ul>
  <?php endif; ?>

  <div class="leitfaden" style="margin-top:22px">
    <h3 class="md-h"><?= $h($T('lf_titel')) ?></h3>
    <?php foreach (Texte::PARTNER_LEITFADEN as $li => $abschnitt): ?>
      <details><summary><?= $h(PartnerVorlagen::text("leitfaden.$li.titel", $sprache, Texte::h($abschnitt['titel'], $sprache))) ?></summary><pre><?= $h(PartnerVorlagen::text("leitfaden.$li.text", $sprache, Texte::h($abschnitt['text'], $sprache))) ?></pre></details>
    <?php endforeach; ?>
  </div>
</div>
<script>
/* Schnellcheck und Websuche dauern ein paar Sekunden: Knopf sperren und sagen, was passiert. */
(function () {
  [].forEach.call(document.querySelectorAll('#recherche form[data-warten]'), function (f) {
    f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); b.disabled = true; b.textContent = f.dataset.warten; });
  });
})();
</script>
