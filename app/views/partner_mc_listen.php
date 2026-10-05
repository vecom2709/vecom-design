<?php
/* ==========================================================================
   Marketing Center: Meine Designs, Favoriten, Marketing-Erfolge (04.10.2026,
   Partner-Marketingcenter Schritt 2). Nur im Partnerbereich, nie in der
   Verwaltungsvorschau. Gesetzt sein müssen: $p, $sprache, $h, $selbst,
   $mcDesigns, $mcErfolge, $mcFav, $mcProdukte (id => Produkt), $wmBestellungen.
   Alles hier gehört dem angemeldeten Partner (Marketingcenter::designs u. a.).
   ========================================================================== */
$MC = static fn(string $k): string => Texte::h(Texte::MARKETINGCENTER[$k] ?? [], $sprache);
$MCb = static fn(string $b): string => Texte::h(Texte::MARKETINGCENTER['b'][$b] ?? [], $sprache);
$mcSt = static fn(string $s): string => Texte::h(Texte::MARKETINGCENTER['d_status'][$s] ?? [], $sprache);
?>
<style>
  .mc-liste{display:grid;gap:8px}
  .mc-zeile{display:grid;grid-template-columns:1fr auto;gap:6px 14px;align-items:center;padding:12px 14px;border:1px solid var(--linie);border-radius:12px;font-size:14px}
  .mc-zeile b{font-size:14.5px}
  .mc-zeile .wm-meta{margin:2px 0 0}
  .mc-zeile .mc-tun{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
  .mc-zeile .mc-tun .knopf{min-height:36px;padding:6px 12px;font-size:13.5px}
  .mc-mid{font-variant-numeric:tabular-nums;letter-spacing:.02em;color:#e3c27a}
  .mc-s{font-size:12px;padding:2px 9px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim);white-space:nowrap}
  .mc-s-freigegeben{border-color:rgba(120,200,140,.55);color:#9fe0b0}
  .mc-s-entwurf{border-color:rgba(241,211,139,.55);color:#f1d38b}
  .mc-zahlen{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:6px 0 14px}
  .mc-zahlen div{border:1px solid rgba(227,194,122,.18);border-radius:12px;padding:12px;background:linear-gradient(165deg,#0d162c,#0a1021)}
  .mc-zahlen b{display:block;font-size:24px;color:#f3ede2;font-variant-numeric:tabular-nums}
  .mc-zahlen span{font-size:12.5px;color:var(--leise)}
  @media (max-width:520px){ .mc-zahlen{grid-template-columns:1fr 1fr} .mc-zeile{grid-template-columns:1fr} .mc-zeile .mc-tun{justify-content:flex-start} }
</style>

<div class="block pt" id="mc-designs" data-reiter="werbemittel" data-mc-teil="uebersicht designs">
  <h2><?= $h($MCb('designs')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($MC('d_satz')) ?></p>
  <?php if (!$mcDesigns): ?>
    <p class="wm-meta"><?= $h($MC('d_leer')) ?></p>
  <?php else: ?>
    <div class="mc-liste">
      <?php foreach ($mcDesigns as $mcD): ?>
        <div class="mc-zeile">
          <div>
            <b><?= $h($mcD['produkt']) ?></b> <span class="mc-s mc-s-<?= $h($mcD['status']) ?>"><?= $h($mcSt($mcD['status'])) ?></span>
            <p class="wm-meta"><span class="mc-mid"><?= $h($mcD['marketing_id']) ?></span> · <?= $h(Fmt::datum($mcD['created_at'])) ?> ·
              <?= $h(strtr($MC('d_zahlen'), ['{scans}' => (string) $mcD['erfolg']['scans'], '{anfragen}' => (string) $mcD['erfolg']['anfragen']])) ?></p>
          </div>
          <div class="mc-tun">
            <a class="knopf" href="<?= $h($selbst(['wmpdf' => $mcD['id']])) ?>" target="_blank" rel="noopener"><?= $h($MC('d_pdf')) ?></a>
            <?php if (isset($mcProdukte[$mcD['produkt_id']])): ?><a class="knopf" href="#wm-p<?= $mcD['produkt_id'] ?>"><?= $h($MC('zum_produkt')) ?></a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$wmBestellungen): ?>
<div class="block pt" id="mc-bestellungen" data-reiter="werbemittel" data-mc-teil="bestellungen">
  <h2><?= $h($MCb('bestellungen')) ?></h2>
  <p class="wm-meta"><?= $h($MC('b_leer')) ?></p>
</div>
<?php endif; ?>

<div class="block pt" id="mc-favoriten" data-reiter="werbemittel" data-mc-teil="favoriten<?= $mcFav ? ' uebersicht' : '' ?>">
  <h2><?= $h($MCb('favoriten')) ?></h2>
  <?php $mcFavDa = array_values(array_filter($mcFav, static fn($i) => isset($mcProdukte[$i]))); ?>
  <?php if (!$mcFavDa): ?>
    <p class="wm-meta"><?= $h($MC('fav_leer')) ?></p>
  <?php else: ?>
    <div class="mc-liste">
      <?php foreach ($mcFavDa as $mcI): $mcPr = $mcProdukte[$mcI]; ?>
        <div class="mc-zeile">
          <div><b><?= $h($mcPr['name']) ?></b>
            <p class="wm-meta"><?= $h(Texte::h(Texte::MARKETINGCENTER['b'][$mcPr['mc_bereich']] ?? [], $sprache)) ?> · <?= $h(strtr(Texte::h(Texte::PARTNER_WERBEMITTEL['ab'], $sprache), ['{preis}' => Werbemittel::euro((int) $mcPr['ab_cent'])])) ?></p></div>
          <div class="mc-tun">
            <a class="knopf haupt" href="#wm-p<?= $mcI ?>"><?= $h($MC('zum_produkt')) ?></a>
            <form method="post" action="<?= $h($selbst()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
              <input type="hidden" name="tat" value="wm_favorit"><input type="hidden" name="produkt" value="<?= $mcI ?>"><input type="hidden" name="zurueck" value="favoriten">
              <button class="knopf"><?= $h($MC('fav_weg')) ?></button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="block pt" id="mc-erfolge" data-reiter="werbemittel" data-mc-teil="uebersicht erfolge">
  <h2><?= $h($MCb('erfolge')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($MC('e_satz')) ?></p>
  <?php $mcS = $mcErfolge['summe']; ?>
  <div class="mc-zahlen">
    <?php foreach (['scans' => 'e_scans', 'besucher' => 'e_besucher', 'anfragen' => 'e_anfragen', 'abschluesse' => 'e_kunden'] as $mcK => $mcTk): ?>
      <div><b><?= (int) $mcS[$mcK] ?></b><span><?= $h($MC($mcTk)) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($mcS['scans'] === 0 && $mcS['anfragen'] === 0): ?>
    <p class="wm-meta"><?= $h($MC('e_leer')) ?></p>
  <?php else: ?>
    <?php if ($mcErfolge['quote'] !== null): ?><p class="wm-meta" style="margin:0 0 10px"><?= $h(strtr($MC('e_quote'), ['{q}' => str_replace('.', $sprache === 'en' ? '.' : ',', (string) $mcErfolge['quote'])])) ?></p><?php endif; ?>
    <h3 style="font-size:15px;margin:6px 0 8px"><?= $h($MC('e_beste')) ?></h3>
    <div class="mc-liste">
      <?php foreach ($mcErfolge['beste'] as $mcD): ?>
        <div class="mc-zeile">
          <div><b><?= $h($mcD['produkt']) ?></b>
            <p class="wm-meta"><span class="mc-mid"><?= $h($mcD['marketing_id']) ?></span> · <?= $h(strtr(Texte::h(Texte::PARTNER_WERBEMITTEL['erfolg'], $sprache),
              ['{scans}' => (string) $mcD['erfolg']['scans'], '{besucher}' => (string) $mcD['erfolg']['besucher'], '{anfragen}' => (string) $mcD['erfolg']['anfragen'], '{abschluesse}' => (string) $mcD['erfolg']['abschluesse']])) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="wm-meta" style="margin-top:12px"><?= $h($MC('e_hinweis')) ?></p>
</div>
