<?php
/**
 * Recherche-Funde als Liste mit Knöpfen (Marketing-Studio 1 und 5).
 * Erwartet: $funde (MkZielgruppe::recherche), $fundeZurueck (Query oder „zielgruppen/ID“), $branchen.
 * Steht auf „Zielgruppen & Recherche“ und auf jeder Zielgruppe — merken und
 * verwerfen geht dort, wo man liest.
 */
$fundeZurueck = $fundeZurueck ?? '';
?>
<div class="mk-recherche">
  <?php foreach ($funde as $fu): ?>
    <article class="block" style="margin-bottom:10px">
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
        <span class="marke2"><?= Fmt::h(MkZielgruppe::ARTEN[$fu['art']] ?? $fu['art']) ?></span>
        <?php if ($fu['branche'] !== ''): ?><span class="marke2"><?= Fmt::h($branchen[$fu['branche']] ?? $fu['branche']) ?></span><?php endif; ?>
        <span class="mk-fein"><?= $fu['land'] !== '' ? MkLand::marke((string) $fu['land']) : 'für beide Länder' ?> · Relevanz <?= str_repeat('●', (int) $fu['relevanz']) . str_repeat('○', 5 - (int) $fu['relevanz']) ?> · <?= Fmt::h(date('d.m.Y', strtotime((string) $fu['created_at']))) ?></span>
        <?php if ($fu['status'] !== 'neu'): ?><span class="marke2 <?= $fu['status'] === 'gemerkt' ? 'gut' : '' ?>"><?= Fmt::h(MkZielgruppe::STATUS_RECHERCHE[$fu['status']] ?? $fu['status']) ?></span><?php endif; ?>
      </div>
      <h3 style="margin:0 0 6px;font-size:15px"><?= Fmt::h($fu['titel']) ?></h3>
      <p style="margin:0 0 8px;max-width:85ch;line-height:1.6;white-space:pre-line"><?= Fmt::h($fu['text']) ?></p>
      <p class="mk-fein" style="margin:0 0 10px">Quelle<?= count($fu['q']) > 1 ? 'n' : '' ?>:
        <?php foreach ($fu['q'] as $i => $q): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= Fmt::h((string) $q['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Fmt::h((string) $q['titel']) ?></a><?php endforeach; ?></p>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['gemerkt' => 'Merken', 'verwendet' => 'Als verwendet markieren', 'verworfen' => 'Verwerfen'] as $st => $wort): if ($fu['status'] === $st) { continue; } ?>
          <form method="post" action="<?= Fmt::h(url('zielgruppen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_status"><input type="hidden" name="id" value="<?= (int) $fu['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>"><input type="hidden" name="zurueck" value="<?= Fmt::h($fundeZurueck) ?>"><button class="knopf klein"><?= Fmt::h($wort) ?></button></form>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
