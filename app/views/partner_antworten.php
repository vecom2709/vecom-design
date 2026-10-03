<?php
/* Antwort-Helfer (03.10.2026, Uwe: Ja zu K4 — „wenn er kommentiert hatte oder geschrieben
   oder geliked … Ziel ihn auf die Partner-Webseite zu leiten“). Reiter „Werben“.
   Alles läuft im Browser: Nichts wird gespeichert, nichts geht von selbst raus.
   Der Link trägt den Kanal „antwort“ — so zeigt die Besucherliste, wer darüber kam.
   Gesetzt: $p, $sprache, $h. */
require_once dirname(__DIR__) . '/src/PartnerWerbung.php';
$PA = Texte::PARTNER_ANTWORTEN;
$paW = static fn(array $t): string => Texte::h($t, $sprache);
$paDaten = ['link' => PartnerWerbung::link($p, 'antwort'), 'lagen' => array_map(static fn(array $l) => $l['text'], $PA['lagen']), 'kopiert' => $paW($PA['kopiert']),
            'zitat' => ['it' => ' «{z}»', 'de' => ' „{z}“', 'en' => ' “{z}”']];
?>
<div class="block pt" id="antworten" data-reiter="werben">
  <h2><?= $h($paW($PA['titel'])) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($paW($PA['text'])) ?></p>
  <script type="application/json" id="antworten_daten"><?= json_encode($paDaten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <p class="md-l"><?= $h($paW($PA['was'])) ?></p>
  <div class="chips" role="group" aria-label="<?= $h($paW($PA['was'])) ?>">
    <?php $paErst = true; foreach ($PA['lagen'] as $paK => $paL): ?>
      <button type="button" data-lage="<?= $h($paK) ?>" aria-pressed="<?= $paErst ? 'true' : 'false' ?>"><?= $h($paW($paL['name'])) ?></button>
    <?php $paErst = false; endforeach; ?>
  </div>
  <div class="reihe" style="margin-top:8px">
    <div><label for="aw_name"><?= $h($paW($PA['vorname'])) ?></label><input id="aw_name" type="text" maxlength="40" autocomplete="off"></div>
    <div style="flex:0 1 150px"><label for="aw_sprache"><?= $h($paW($PA['sprache'])) ?></label>
      <select id="aw_sprache"><?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $paS => $paN): ?><option value="<?= $paS ?>"<?= $paS === $sprache ? ' selected' : '' ?>><?= $paN ?></option><?php endforeach; ?></select></div>
  </div>
  <label for="aw_zitat"><?= $h($paW($PA['zitat_feld'])) ?></label>
  <input id="aw_zitat" type="text" maxlength="140" autocomplete="off">
  <label for="aw_text" class="mk-sr"><?= $h($paW($PA['titel'])) ?></label>
  <textarea id="aw_text" rows="5" data-wachsen style="width:100%;box-sizing:border-box;margin-top:10px;font-size:15px;line-height:1.55;padding:10px 12px"></textarea>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" id="aw_kopieren"><?= $h($paW($PA['kopieren'])) ?></button>
    <a class="knopf" id="aw_wa" href="https://wa.me/" target="_blank" rel="noopener"><?= $h($paW($PA['wa'])) ?></a>
    <button class="knopf" type="button" data-handy="aw_text" data-handy-wer="<?= $h((string) $p['name']) ?>" data-handy-titel="<?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_titel'], $sprache)) ?>" data-handy-zu="<?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_schliessen'], $sprache)) ?>"><?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_knopf'], $sprache)) ?></button>
  </div>
  <p class="klein"><?= $h($paW($PA['hinweis'])) ?></p>
</div>
<script>
(function () {
  var el = document.getElementById('antworten_daten'); if (!el) { return; }
  var D = JSON.parse(el.textContent), lage = Object.keys(D.lagen)[0];
  var name = document.getElementById('aw_name'), sp = document.getElementById('aw_sprache'), zitat = document.getElementById('aw_zitat');
  var feld = document.getElementById('aw_text'), wa = document.getElementById('aw_wa'), kopie = document.getElementById('aw_kopieren');
  var knoepfe = [].slice.call(document.querySelectorAll('#antworten [data-lage]'));
  function bauen() {
    var s = sp.value, t = D.lagen[lage][s] || D.lagen[lage].it, n = name.value.trim(), z = zitat.value.trim();
    // Ohne Namen: „Hallo {vorname},“ wird „Hallo,“ — „Danke …, {vorname}!“ wird „Danke …!“
    t = n ? t.split('{vorname}').join(n) : t.replace(/,? \{vorname\}([,!])/g, '$1').split('{vorname}').join('');
    t = t.split('{zitat}').join(z ? D.zitat[s].replace('{z}', z.length > 80 ? z.slice(0, 78) + '…' : z) : '');
    feld.value = t.split('{link}').join(D.link);
    wa.href = 'https://wa.me/?text=' + encodeURIComponent(feld.value);
  }
  knoepfe.forEach(function (k) {
    k.addEventListener('click', function () { lage = k.dataset.lage; knoepfe.forEach(function (a) { a.setAttribute('aria-pressed', a === k ? 'true' : 'false'); }); bauen(); });
  });
  [name, sp, zitat].forEach(function (f) { f.addEventListener('input', bauen); });
  feld.addEventListener('input', function () { wa.href = 'https://wa.me/?text=' + encodeURIComponent(feld.value); });
  kopie.addEventListener('click', function () {
    var alt = kopie.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(feld.value) : Promise.reject()).catch(function () { feld.select(); document.execCommand('copy'); });
    kopie.textContent = D.kopiert; setTimeout(function () { kopie.textContent = alt; }, 1600);
  });
  bauen();
})();
</script>
