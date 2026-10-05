<?php
/* ==========================================================================
   SHOP im Command Center (Phase 6a, 05.10.2026; Spezifikation 32–38).
   Jedes Produkt mit zwei Wegen, wie im Konzept „SHOP mit zwei Knöpfen am
   selben Produkt“: SELBST DRUCKEN (das PDF aus ?druck=, kostenlos — nur wo es
   eine Selbstdruck-Fassung gibt) und DRUCKEN LASSEN (der vorhandene Ablauf
   Gestalten → Freigeben → Bestellen im Marketing Center). Darunter die eigenen
   Bestellungen mit „Ist angekommen“ und „Problem melden“ (zugestellt und
   Reklamation, Uwe: „Partner bestätigt, sonst nach 14 Tagen“).
   Erwartet aus partner_cc.php: $p, $sprache, $h, $selbst, $ccKatalog, $ccMeldung.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/WmBestellung.php';
require_once dirname(__DIR__) . '/src/Werbemittel.php';
require_once dirname(__DIR__) . '/src/Marketingcenter.php';
$SH = Texte::PARTNER_SHOP;
$sh = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$W = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBEMITTEL[$k] ?? [], $sprache);
/* Welche Vorlage eine Selbstdruck-Fassung hat (partner_druck.php) — die übrigen gibt es nur aus der Druckerei. */
$shSelbst = ['visitenkarte' => 'visitenkarten', 'flyer_a6' => 'flyer', 'flyer_a5' => 'flyer', 'flyer_branche' => 'flyer', 'aufkleber_50' => 'aufkleber'];
$shB = Marketingcenter::nachBereich($ccKatalog);
$shBest = WmBestellung::fuerPartner((int) $p['id']);
$shM = (string) ($_GET['wm'] ?? '');
?>
<main id="cc-start" tabindex="-1">
  <div class="cc-hallo cc-auf">
    <h1><?= $h($sh($SH['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($sh($SH['satz'])) ?></p>
  </div>
  <p class="cc-shop-wege cc-auf z2"><?= $h($sh($SH['wege'])) ?></p>

  <?php if (!$ccKatalog): ?>
    <p class="cc-leer"><?= $h($sh($SH['leer'])) ?></p>
  <?php endif; ?>
  <?php foreach ($shB as $shX => $shL): if (!$shL) { continue; } ?>
    <section class="cc-auf z2" aria-labelledby="sh-<?= $h($shX) ?>" style="margin-top:20px">
      <h2 class="cc-titel" id="sh-<?= $h($shX) ?>"><?= $h(Texte::h(Texte::MARKETINGCENTER['b'][$shX] ?? ['it' => $shX], $sprache)) ?></h2>
      <ul class="cc-shop">
        <?php foreach ($shL as $pr): $shDruck = $shSelbst[(string) $pr['vorlage']] ?? null; ?>
          <li class="cc-teil">
            <div class="cc-teil-kopf"><b><?= $h((string) $pr['name']) ?></b>
              <small><?= $h(trim((string) $pr['format'] . ((string) $pr['format'] !== '' ? ' · ' : '') . $sh($SH['ab'], ['{preis}' => Werbemittel::euro((int) $pr['ab_cent'])]))) ?></small></div>
            <div class="knoepfe">
              <?php if ($shDruck !== null): ?>
                <a class="knopf" href="<?= $h($selbst(['druck' => $shDruck, 'hell' => 1])) ?>" target="_blank" rel="noopener"><?= $h($sh($SH['selbst'])) ?><small><?= $h($sh($SH['selbst_klein'])) ?></small></a>
              <?php endif; ?>
              <a class="knopf haupt" href="<?= $h($selbst() . '#wm-p' . (int) $pr['id']) ?>"><?= $h($sh($SH['lassen'])) ?> →</a>
            </div>
            <?php if ($shDruck === null): ?><small class="cc-shop-nur"><?= $h($sh($SH['nur_lassen'])) ?></small><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
  <?php if ($ccKatalog): ?>
    <nav class="cc-schnell cc-auf z3" style="margin-top:14px"><a href="<?= $h($selbst() . '#werbemittel') ?>"><?= $h($sh($SH['alle'])) ?> →</a></nav>
  <?php endif; ?>

  <section class="cc-auf z3" id="bestellungen" aria-labelledby="sh-best-t" style="margin-top:26px">
    <h2 class="cc-titel" id="sh-best-t"><?= $h($sh($SH['best'])) ?></h2>
    <?php if (in_array($shM, ['angekommen', 'problem', 'problem_grund', 'problem_foto'], true)): ?>
      <div class="hinweis <?= in_array($shM, ['angekommen', 'problem'], true) ? 'gut' : 'schlecht' ?>" role="<?= in_array($shM, ['angekommen', 'problem'], true) ? 'status' : 'alert' ?>" style="margin:0 0 14px"><?= $h($W('m_' . $shM)) ?></div>
    <?php endif; ?>
    <?php if (!$shBest): ?><p class="cc-leer"><?= $h($sh($SH['best_leer'])) ?></p><?php endif; ?>
    <ul class="cc-best">
      <?php foreach ($shBest as $b): $bid = (int) $b['id']; $pos = $b['positionen'];
        $frist = $b['status'] === 'zugestellt' && !empty($b['zugestellt_am']) && empty($b['reklamation_am'])
            ? date('Y-m-d H:i:s', strtotime((string) $b['zugestellt_am'] . ' +' . WmBestellung::REKLAMATION_TAGE . ' days')) : null; ?>
        <li class="cc-teil" id="b-<?= $bid ?>">
          <div class="cc-best-kopf"><b><?= $h((string) $b['nummer']) ?></b>
            <span class="cc-stufe st-<?= $h((string) $b['status']) ?>"><?= $h($W('s_' . $b['status'])) ?></span></div>
          <small><?= $h(Fmt::datum((string) $b['created_at'])) ?> · <?= $h(implode(', ', array_map(static fn($x) => trim($x['name'] . ' · ' . $x['variante'], ' ·'), $pos))) ?>
            · <b><?= $h(Werbemittel::euro((int) $b['summe_cent'])) ?></b></small>
          <?php if (in_array($b['status'], ['versendet', 'zugestellt', 'reklamation'], true) && !empty($b['tracking'])): ?>
            <small><?php if (!empty($b['tracking_url'])): ?><a href="<?= $h((string) $b['tracking_url']) ?>" target="_blank" rel="noopener" style="color:var(--gold)"><?= $h($W('sendung')) ?> · <?= $h((string) $b['tracking']) ?></a>
              <?php else: ?><?= $h($W('sendung')) ?>: <?= $h((string) $b['tracking']) ?><?php endif; ?></small>
          <?php endif; ?>
          <?php if (!empty($b['reklamation_entscheid'])): ?>
            <p class="cc-best-entscheid"><b><?= $h(Texte::h(Texte::PARTNER_WERBEMITTEL['rek_entschieden'][$b['reklamation_entscheid']] ?? [], $sprache)) ?></b>
              <?php if (!empty($b['reklamation_antwort'])): ?><br><?= $h($sh($SH['entscheid_antwort'])) ?>: <?= $h((string) $b['reklamation_antwort']) ?><?php endif; ?></p>
          <?php elseif ($b['status'] === 'reklamation'): ?>
            <p class="cc-best-entscheid"><?= $h((string) $b['reklamation_grund']) ?></p>
          <?php endif; ?>
          <?php if ($frist !== null): ?><small><?= $h($sh($SH['frist'], ['{datum}' => Fmt::datum($frist)])) ?></small><?php endif; ?>
          <?php if ($b['status'] === 'versendet' || WmBestellung::reklamierbar($b)): ?>
            <div class="knoepfe">
              <?php if ($b['status'] === 'versendet'): ?>
                <form method="post" action="<?= $h($selbst(['cc' => 1, 'shop' => 1])) ?>" style="margin:0">
                  <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="wm_angekommen"><input type="hidden" name="bestellung" value="<?= $bid ?>">
                  <button class="knopf haupt"><?= $h($W('angekommen')) ?></button></form>
              <?php endif; ?>
              <details class="cc-problem">
                <summary class="knopf stumm"><?= $h($W('problem')) ?></summary>
                <form method="post" action="<?= $h($selbst(['cc' => 1, 'shop' => 1])) ?>" enctype="multipart/form-data">
                  <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="wm_problem"><input type="hidden" name="bestellung" value="<?= $bid ?>">
                  <p class="hilfe"><?= $h(strtr($W('problem_satz'), ['{tage}' => (string) WmBestellung::REKLAMATION_TAGE])) ?></p>
                  <label class="cc-feldname" for="pr-g-<?= $bid ?>"><?= $h($W('problem_grund')) ?></label>
                  <textarea id="pr-g-<?= $bid ?>" name="grund" rows="3" maxlength="600" required minlength="10"></textarea>
                  <label class="cc-feldname" for="pr-f-<?= $bid ?>"><?= $h($W('problem_foto')) ?></label>
                  <input id="pr-f-<?= $bid ?>" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                  <button class="knopf haupt" style="margin-top:10px"><?= $h($W('problem_senden')) ?></button>
                </form>
              </details>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>
