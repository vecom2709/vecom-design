<?php
/* ==========================================================================
   Werbe-Paket auf der Partnerseite (26.09.2026). Eingebunden aus partner.php,
   dort sind $p, $link, $sprache, $T, $h, $selbst, $meldung gesetzt.

   Aufbau: ein Reiter je Kanal (ohne Skript stehen alle Tafeln untereinander),
   darin fertige Texte mit Kopieren und Teilen, danach Werbemittel, Signatur
   und Website-Knopf, dann „Was wirkt“. Jeder Text trägt den Link seines
   Kanals -- der Partner setzt nie selbst etwas ein.
   ========================================================================== */
$vorlagen = PartnerWerbung::vorlagen($p, $sprache);
$KN = static fn(string $k): string => PartnerWerbung::name($k, $sprache);
$tipp = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBUNG['tipps'][$k] ?? [], $sprache);
$aw = PartnerWerbung::auswertung((int) $p['id']);
$sig = PartnerWerbung::signatur($p, $sprache);
$knopf = PartnerWerbung::websiteKnopf($p, $sprache);
?>
<style>
  .reiter{display:flex;gap:6px;overflow-x:auto;padding:2px 0 8px;margin:4px 0 6px;scrollbar-width:thin}
  .reiter button{flex:0 0 auto;min-height:38px;padding:7px 14px;border-radius:999px;border:1px solid var(--linie2);background:transparent;
                 color:var(--dim);font:inherit;font-size:14px;cursor:pointer}
  .reiter button[aria-selected=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.09)}
  .reiter button:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .tafel{display:grid;gap:12px;margin-top:4px}
  .tafel > h3{font-size:15px;margin:10px 0 0}
  .vorlage{border:1px solid var(--linie);border-radius:12px;padding:12px}
  .vorlage h4{font-size:14px;margin:0 0 8px;color:var(--text)}
  .vorlage textarea{width:100%;box-sizing:border-box;font-size:14px;line-height:1.55;padding:10px 12px;resize:vertical;min-height:90px}
  .vorlage .betreff{font-size:13px;color:var(--dim);margin:0 0 6px}
  .vorlage .betreff b{color:var(--text);font-weight:600}
  .vorlage .knoepfe{margin-top:8px}
  .vorlage .knopf{min-height:40px}
  .kanallink{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--leise);min-width:0;margin-top:2px}
  .kanallink code{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--dim)}
  .kanallink .knopf{min-height:32px;padding:5px 11px;font-size:12.5px}
  .sig-vorschau{background:#fff;border-radius:10px;padding:14px 16px;margin:8px 0;overflow-x:auto}
  .pt .sig-vorschau td{border:0}
  .web-vorschau{padding:12px 0}
  .aw{font-size:13.5px}
  .aw td:first-child{font-weight:600;word-break:break-word}
  .pt .aw th.r,.pt .aw td.r{white-space:normal;padding-left:4px;padding-right:4px}
  .aw td small{display:block;color:var(--cyan);font-size:12px}
  .aw .bester{border:1px solid rgba(241,211,139,.45);border-radius:10px;padding:9px 12px;margin:10px 0 0;font-size:14px}
  .profil-kopf{display:flex;gap:14px;align-items:center;margin:4px 0 8px}
  .profil-kopf img,.profil-kopf .leer{width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid var(--linie2);flex:0 0 72px}
  .profil-kopf .leer{display:grid;place-items:center;color:var(--leise);font-size:26px}
  .pt input[type=file]{font-size:14px;color:var(--dim)}
  .md-h{font-size:15px;margin:14px 0 6px}
  .md-l{font-size:12.5px;color:var(--leise);margin:10px 0 4px;text-transform:uppercase;letter-spacing:.05em}
  .chips{display:flex;gap:6px;flex-wrap:wrap}
  .chips button{min-height:36px;padding:6px 12px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13.5px;cursor:pointer}
  .chips button[aria-pressed=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.09)}
  .chips button:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .md-buehne{margin:14px 0 4px;display:flex;justify-content:center;background:rgba(255,255,255,.02);border:1px solid var(--linie);border-radius:12px;padding:12px}
  .md-buehne canvas{max-width:100%;max-height:440px;width:auto;height:auto;border-radius:6px;box-shadow:0 8px 30px rgba(0,0,0,.4)}
  .md-video{display:block;max-width:100%;max-height:440px;margin:10px auto;border-radius:10px;background:#000}
  .druckliste{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:8px}
  .druckliste .knopf{justify-content:flex-start;text-align:left}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>

<div class="block pt" id="werbung">
  <h2><?= $h($T('pk_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($T('pk_text')) ?></p>

  <div class="reiter" role="tablist" aria-label="<?= $h($T('pk_titel')) ?>" hidden>
    <?php foreach (PartnerWerbung::KANAELE as $i => $k): ?>
      <button type="button" role="tab" id="r_<?= $h($k) ?>" aria-controls="t_<?= $h($k) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><?= $h($KN($k)) ?></button>
    <?php endforeach; ?>
  </div>

  <?php foreach (PartnerWerbung::KANAELE as $k): $kl = PartnerWerbung::link($p, $k); ?>
  <section class="tafel" id="t_<?= $h($k) ?>" role="tabpanel" aria-labelledby="r_<?= $h($k) ?>">
    <h3 class="nur-ohne-js"><?= $h($KN($k)) ?></h3>
    <?php if ($tipp($k) !== ''): ?><p class="klein" style="margin:0"><?= $h($tipp($k)) ?></p><?php endif; ?>
    <div class="kanallink"><span><?= $h(strtr($T('pk_link'), ['{kanal}' => $KN($k)])) ?>:</span><code><?= $h($kl) ?></code>
      <button class="knopf" type="button" data-kopie-text="<?= $h($kl) ?>"><?= $h($T('kopieren')) ?></button></div>
    <?php foreach ($vorlagen[$k] ?? [] as $v): ?>
      <article class="vorlage">
        <h4><?= $h($v['titel']) ?></h4>
        <?php if ($v['betreff'] !== ''): ?><p class="betreff"><?= $h($T('pk_betreff')) ?>: <b><?= $h($v['betreff']) ?></b></p><?php endif; ?>
        <label class="sr" for="v_<?= $h($v['id']) ?>"><?= $h($v['titel']) ?></label>
        <textarea id="v_<?= $h($v['id']) ?>" readonly rows="6" data-wachsen><?= $h($v['text']) ?></textarea>
        <div class="knoepfe">
          <button class="knopf" type="button" data-kopie="v_<?= $h($v['id']) ?>"><?= $h($T('kopieren')) ?></button>
          <?php if ($v['teilen'] !== null): ?>
            <a class="knopf haupt" href="<?= $h($v['teilen']) ?>" <?= str_starts_with($v['teilen'], 'http') ? 'target="_blank" rel="noopener"' : '' ?>><?= $h($T($k === 'email' ? 'pk_senden' : 'pk_teilen')) ?></a>
          <?php else: ?>
            <button class="knopf haupt" type="button" data-teilen="v_<?= $h($v['id']) ?>" data-url="<?= $h($kl) ?>" hidden><?= $h($T('pk_teilen')) ?></button>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

  <details style="margin-top:18px">
    <summary style="cursor:pointer;color:var(--cyan);font-size:14.5px"><?= $h($T('pk_werkzeuge')) ?></summary>
    <h3 style="font-size:15px;margin:14px 0 4px"><?= $h($T('sig_titel')) ?></h3>
    <p class="klein" style="margin:0"><?= $h($T('sig_text')) ?></p>
    <div class="sig-vorschau" id="sig_vorschau"><?= $sig ?></div>
    <textarea id="sig_html" hidden><?= $h($sig) ?></textarea>
    <button class="knopf" type="button" id="sig_kopieren"><?= $h($T('sig_kopieren')) ?></button>

    <h3 style="font-size:15px;margin:20px 0 4px"><?= $h($T('web_titel')) ?></h3>
    <p class="klein" style="margin:0"><?= $h($T('web_text')) ?></p>
    <div class="web-vorschau"><?= $knopf ?></div>
    <div class="vorlage" style="padding:10px">
      <label class="sr" for="web_code"><?= $h($T('web_titel')) ?></label>
      <textarea id="web_code" readonly rows="4" style="font-family:ui-monospace,monospace;font-size:12.5px"><?= $h($knopf) ?></textarea>
      <div class="knoepfe"><button class="knopf" type="button" data-kopie="web_code"><?= $h($T('code_kopieren')) ?></button></div>
    </div>
  </details>

  <h2 style="margin-top:22px"><?= $h($T('aw_titel')) ?></h2>
  <?php if (!$aw['zeilen']): ?>
    <p class="klein" style="margin-top:0"><?= $h($T('aw_leer')) ?></p>
  <?php else: ?>
    <table class="aw"><thead><tr><th><?= $h($T('aw_kanal')) ?></th><th class="r"><?= $h($T('aw_klicks')) ?></th>
      <th class="r"><?= $h($T('aw_kunden')) ?></th><th class="r"><?= $h($T('aw_verkaeufe')) ?></th></tr></thead><tbody>
      <?php foreach ($aw['zeilen'] as $z): ?>
        <tr><td><?= $h($KN($z['kanal'])) ?></td><td class="r"><?= (int) $z['klicks'] ?></td><td class="r"><?= (int) $z['kunden'] ?></td>
            <td class="r"><?= (int) $z['verkaeufe'] ?><?= $z['provision'] > 0 ? '<small>' . $h(Fmt::geld($z['provision'])) . '</small>' : '' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php if ($aw['bester'] !== null): ?><p class="bester"><?= $h(strtr($T('aw_bester'), ['{kanal}' => $KN($aw['bester'])])) ?></p><?php endif; ?>
  <?php endif; ?>
</div>

<?php
  $MT = static fn(string $k): string => strtr(Texte::h(Texte::PARTNER_MEDIEN[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
  $medienDaten = [
      'code' => (string) $p['code'], 'kurz' => preg_replace('~^https?://~', '', Partner::link($p)),
      'links' => ['bild' => PartnerWerbung::link($p, 'bild'), 'video' => PartnerWerbung::link($p, 'video'), 'karte' => PartnerWerbung::link($p, 'karte')],
      'foto' => PartnerWerbung::fotoAdresse($p),
      'motive' => array_map(static fn(array $m): array => ['titel' => Texte::h($m['titel'], $sprache), 'unter' => Texte::h($m['unter'], $sprache)], Texte::PARTNER_MEDIEN['motive']),
      'punkte' => array_map(static fn(array $t): string => Texte::h($t, $sprache), Texte::PARTNER_MEDIEN['punkte']),
      'empf' => $MT('empf'), 'scan' => $MT('scan'), 'hook' => $MT('hook'), 'bio' => $MT('bio'), 'werbung' => $MT('werbung'),
      't' => ['video_laeuft' => $T('video_laeuft'), 'video_fertig' => $T('video_fertig'), 'video_nein' => $T('video_nein'), 'video_webm' => $T('video_webm')],
  ];
?>
<div class="block pt" id="medien">
  <h2><?= $h($T('md_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($T('md_text')) ?></p>
  <script type="application/json" id="medien_daten"><?= json_encode($medienDaten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

  <h3 class="md-h"><?= $h($T('bild_titel')) ?></h3>
  <p class="md-l"><?= $h($T('bild_motiv')) ?></p>
  <div class="chips">
    <?php $ersteM = true; foreach (Texte::PARTNER_MEDIEN['motive'] as $mk => $mv): ?>
      <button type="button" data-motiv="<?= $h($mk) ?>" aria-pressed="<?= $ersteM ? 'true' : 'false' ?>"><?= $h(Texte::h($mv['name'], $sprache)) ?></button>
    <?php $ersteM = false; endforeach; ?>
  </div>
  <p class="md-l"><?= $h($T('bild_format')) ?></p>
  <div class="chips">
    <?php foreach (['quadrat', 'hoch', 'story', 'quer', 'banner', 'qr', 'qrfoto'] as $i => $fk): ?>
      <button type="button" data-format="<?= $fk ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= $h($T('bf_' . $fk)) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="md-buehne"><canvas id="bild_vorschau" width="1080" height="1080" role="img" aria-label="<?= $h($T('bild_titel')) ?>"></canvas></div>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" id="bild_laden"><?= $h($T('bild_laden')) ?></button>
    <button class="knopf" type="button" id="bild_teilen" hidden><?= $h($T('bild_teilen')) ?></button>
  </div>

  <h3 class="md-h" style="margin-top:22px"><?= $h($T('video_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('video_text')) ?></p>
  <div class="knoepfe"><button class="knopf" type="button" id="video_erzeugen"><?= $h($T('video_erzeugen')) ?></button></div>
  <p class="klein" id="video_stand" role="status" aria-live="polite"></p>
  <video id="video_vorschau" class="md-video" controls playsinline muted loop hidden></video>
  <div class="knoepfe">
    <button class="knopf" type="button" id="video_laden" hidden><?= $h($T('video_laden')) ?></button>
    <button class="knopf" type="button" id="video_teilen" hidden><?= $h($T('video_teilen')) ?></button>
  </div>

  <h3 class="md-h" style="margin-top:22px"><?= $h($T('druck_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('druck_text')) ?></p>
  <div class="druckliste">
    <?php foreach (['visitenkarten', 'flyer', 'aufsteller', 'aufkleber'] as $dk): ?>
      <a class="knopf" href="<?= $h($selbst(['druck' => $dk])) ?>" target="_blank" rel="noopener"><?= $h($T('dr_' . $dk)) ?></a>
    <?php endforeach; ?>
    <a class="knopf" href="<?= $h($selbst(['karte' => 1])) ?>" target="_blank" rel="noopener"><?= $h($T('dr_karte')) ?></a>
  </div>
</div>

<?php $foto = PartnerWerbung::fotoAdresse($p); $pfFehler = in_array($meldung, ['satz_link', 'satz_lang', 'foto_gross', 'foto_art'], true); ?>
<div class="block pt" id="profil">
  <h2><?= $h($T('pf_titel')) ?></h2>
  <?php if (($_GET['m'] ?? '') === 'pf_gut'): ?><div class="hinweis gut" role="status"><?= $h($T('pf_gut')) ?></div><?php endif; ?>
  <?php if ($pfFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div><?php endif; ?>
  <p class="klein" style="margin-top:0"><?= $h($T('pf_text')) ?></p>
  <div class="profil-kopf">
    <?php if ($foto): ?><img src="<?= $h($foto) ?>" alt="" width="72" height="72"><?php else: ?><span class="leer" aria-hidden="true">★</span><?php endif; ?>
    <a href="<?= $h('/p.php?' . http_build_query(['c' => $p['code'], 'lang' => $sprache, 'n' => 1])) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-size:14.5px"><?= $h($T('pf_vorschau')) ?></a>
  </div>
  <form method="post" action="<?= $h($selbst()) ?>#profil" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="profil">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PartnerWerbung::FOTO_MAX_BYTE ?>">
    <label for="pf_foto"><?= $h($T('pf_foto')) ?></label>
    <input id="pf_foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
    <label for="pf_satz"><?= $h($T('pf_satz')) ?></label>
    <textarea id="pf_satz" name="satz" rows="2" maxlength="<?= PartnerWerbung::SATZ_MAX ?>" placeholder="<?= $h($T('pf_satz_ph')) ?>"><?= $h((string) ($_POST['satz'] ?? $p['profil_satz'] ?? '')) ?></textarea>
    <button class="knopf haupt" type="submit"><?= $h($T('pf_speichern')) ?></button>
  </form>
  <?php if ($foto): ?>
    <form method="post" action="<?= $h($selbst()) ?>#profil" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="tat" value="foto_weg">
      <button class="knopf" type="submit" style="align-self:flex-start"><?= $h($T('pf_foto_weg')) ?></button>
    </form>
  <?php endif; ?>
</div>

<script>
/* Reiter, Kopieren, Teilen. Ohne Skript bleiben alle Tafeln sichtbar. */
(function () {
  var reiter = document.querySelector('#werbung .reiter'), tabs = reiter ? [].slice.call(reiter.querySelectorAll('[role=tab]')) : [];
  function zeige(t, fokus) {
    tabs.forEach(function (b) {
      var an = b === t; b.setAttribute('aria-selected', an ? 'true' : 'false'); b.tabIndex = an ? 0 : -1;
      document.getElementById(b.getAttribute('aria-controls')).hidden = !an;
    });
    if (fokus) { t.focus(); }
    try { localStorage.setItem('vecom_pk_reiter', t.id); } catch (e) {}
    wachsen();
  }
  if (reiter) {
    reiter.hidden = false;
    [].forEach.call(document.querySelectorAll('#werbung .nur-ohne-js'), function (e) { e.hidden = true; });
    var start = tabs[0]; try { var m = localStorage.getItem('vecom_pk_reiter'); if (m && document.getElementById(m)) { start = document.getElementById(m); } } catch (e) {}
    tabs.forEach(function (b, i) {
      b.addEventListener('click', function () { zeige(b, false); });
      b.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : null;
        if (n === null) { return; } e.preventDefault(); zeige(tabs[(n + tabs.length) % tabs.length], true);
      });
    });
    zeige(start, false);
  }
  function wachsen() {
    [].forEach.call(document.querySelectorAll('textarea[data-wachsen]'), function (t) {
      if (t.offsetParent === null) { return; } t.style.height = 'auto'; t.style.height = (t.scrollHeight + 4) + 'px';
    });
  }
  window.addEventListener('resize', wachsen);
  function geklappt(b) { var alt = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = alt; }, 1600); }
  function kopiere(text, b) {
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(function () { geklappt(b); }, function () {}); return; }
    var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); geklappt(b); } catch (e) {} t.remove();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-kopie],[data-kopie-text]'); if (!b) { return; }
    kopiere(b.dataset.kopieText || document.getElementById(b.dataset.kopie).value, b);
  });
  /* Instagram und TikTok: Das Handy teilt selbst (Teilen-Menü mit allen Apps). */
  if (navigator.share) {
    [].forEach.call(document.querySelectorAll('[data-teilen]'), function (b) {
      b.hidden = false;
      b.addEventListener('click', function () {
        navigator.share({ text: document.getElementById(b.dataset.teilen).value }).catch(function () {});
      });
    });
  }
  /* Signatur als formatierter Text -- eingefügt in Gmail/Outlook bleibt der Knopf ein Knopf. */
  var sk = document.getElementById('sig_kopieren');
  if (sk) sk.addEventListener('click', function () {
    var html = document.getElementById('sig_html').value, vor = document.getElementById('sig_vorschau');
    if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
      navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([html], { type: 'text/html' }), 'text/plain': new Blob([vor.innerText], { type: 'text/plain' }) })])
        .then(function () { geklappt(sk); }, function () { markiere(); });
    } else { markiere(); }
    function markiere() { var r = document.createRange(); r.selectNodeContents(vor); var s = getSelection(); s.removeAllRanges(); s.addRange(r); try { document.execCommand('copy'); geklappt(sk); } catch (e) {} }
  });
})();
</script>
