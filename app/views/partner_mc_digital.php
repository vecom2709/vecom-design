<?php
/* ==========================================================================
   Digitale Visitenkarte (04.10.2026, Partner-Marketingcenter Schritt 5).
   Was es im Digitalbereich noch nicht gab: die Karte als Bild zum Verschicken
   und als vCard fürs Adressbuch. Alles Übrige (Beiträge, Bilder-Baukasten,
   Texte je Kanal, Signatur) gibt es schon und wird hier nur verlinkt.
   Gesetzt sein müssen: $p, $sprache, $h, $selbst, $mcT, $mcMini? (nicht nötig).
   ========================================================================== */
$dvkStile = array_values(array_filter(array_keys(PartnerKarten::STILE), static fn($s) => PartnerKarten::gibt((string) $s)));
$dvkStart = (string) (Db::wert("SELECT JSON_UNQUOTE(JSON_EXTRACT(wahl, '$.stil')) FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id
                                  WHERE e.partner_id = ? AND w.vorlage = 'visitenkarte' AND e.status = 'freigegeben' ORDER BY e.id DESC LIMIT 1", [(int) $p['id']], '') ?: 'a');
if (!in_array($dvkStart, $dvkStile, true)) { $dvkStart = $dvkStile[0] ?? 'a'; }
$dvkD = PartnerDaten::fuer($p);
?>
<div class="block pt" id="mc-dvk" data-reiter="werbemittel" data-mc-teil="uebersicht digital">
  <h2><?= $h($mcT('dvk_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($mcT('dvk_satz')) ?></p>
  <div class="mc-dvk">
    <img id="dvk_bild" src="<?= $h($selbst(['wmdvk' => $dvkStart, 'vks' => $sprache])) ?>" width="1080" height="1350" alt="<?= $h($mcT('dvk_titel')) ?>" loading="lazy" decoding="async">
    <div class="mc-dvk-wahl wm-gestalten">
      <div class="mc-kacheln mc-dvk-kacheln" role="radiogroup" aria-label="<?= $h(Texte::h(Texte::PARTNER_WERBEMITTEL['stil'], $sprache)) ?>">
        <?php foreach ($dvkStile as $dvkS): ?>
          <label class="mc-kachel mc-quer"><input type="radio" name="dvkstil" value="<?= $h($dvkS) ?>"<?= $dvkS === $dvkStart ? ' checked' : '' ?>>
            <img src="<?= $h($selbst(['wmmini' => 'visitenkarte', 'st' => $dvkS, 'vks' => $sprache])) ?>" alt="" loading="lazy" width="420" height="272">
            <span><?= $h(PartnerKarten::STILE[$dvkS][$sprache] ?? PartnerKarten::STILE[$dvkS]['de']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="wm-knoepfe">
        <a class="knopf haupt" id="dvk_laden" href="<?= $h($selbst(['wmdvk' => $dvkStart, 'vks' => $sprache, 'dl' => 1])) ?>" download><?= $h($mcT('dvk_bild')) ?></a>
        <button class="knopf" type="button" id="dvk_teilen" hidden><?= $h($mcT('dvk_teilen')) ?></button>
        <a class="knopf" href="<?= $h($selbst(['wmvcf' => 1])) ?>" download><?= $h($mcT('dvk_vcf')) ?></a>
      </div>
      <?php if ($dvkD['telefon'] === '' && $dvkD['whatsapp'] === ''): ?><p class="wm-meta"><a href="#kontaktdaten"><?= $h($mcT('dvk_tipp')) ?></a></p><?php endif; ?>
    </div>
  </div>
</div>
<script>
(function () {
  var box = document.getElementById('mc-dvk'); if (!box) { return; }
  var bild = document.getElementById('dvk_bild'), laden = document.getElementById('dvk_laden'), teilen = document.getElementById('dvk_teilen');
  function adr(dl) { var s = box.querySelector('input[name=dvkstil]:checked').value; var u = new URL(bild.src, location.href); u.searchParams.set('wmdvk', s); if (dl) { u.searchParams.set('dl', '1'); } else { u.searchParams.delete('dl'); } return u.toString(); }
  box.addEventListener('change', function () { bild.src = adr(false); laden.href = adr(true); });
  if (navigator.canShare && window.File) {
    teilen.hidden = false;
    teilen.addEventListener('click', function () {
      fetch(adr(false), { credentials: 'same-origin' }).then(function (r) { return r.blob(); }).then(function (b) {
        var f = new File([b], 'vecom-visitenkarte.jpg', { type: 'image/jpeg' });
        if (navigator.canShare({ files: [f] })) { return navigator.share({ files: [f] }); }
        location.href = adr(true);
      }).catch(function () {});
    });
  }
})();
</script>
