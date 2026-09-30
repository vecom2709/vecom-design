<?php
/**
 * Marketing · Recherche (Marketing-Studio Schritt 1 — 01.10.2026).
 * Erwartet: $f (Filter art/branche/status), $funde (MkZielgruppe::recherche).
 */
$branchen = MkKampagne::branchen();
$zurueck = http_build_query(array_filter($f));
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Recherche</h1>
    <div class="weg">Themen, Trends, häufige Fragen, Wettbewerb und Plattformen — von Claude recherchiert, jeweils mit Quelle · Wettbewerb nur als Anregung, nie zum Abschreiben</div>
  </div>
</div>

<form class="mk-filter" method="get" action="<?= Fmt::h(url('recherche')) ?>">
  <select name="art" aria-label="Art" style="width:auto"><option value="">Alle Arten</option><?php foreach (MkZielgruppe::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"<?= $f['art'] === $ak ? ' selected' : '' ?>><?= Fmt::h($aw) ?></option><?php endforeach; ?></select>
  <select name="branche" aria-label="Branche" style="width:auto"><option value="">Alle Branchen</option><?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?></option><?php endforeach; ?></select>
  <select name="status" aria-label="Status" style="width:auto"><option value="">Offen und gemerkt</option><?php foreach (MkZielgruppe::STATUS_RECHERCHE as $sk => $sw): ?><option value="<?= $sk ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select>
  <button class="knopf">Anzeigen</button>
</form>

<?php if (!$funde): ?>
  <div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Funde<?= array_filter($f) ? ' für diesen Filter' : '' ?>. Sag im Chat „Recherchiere Themen für Restaurants“ (oder eine andere Branche) — Claude sucht im Netz, prüft die Quellen und legt die Funde hier ab.</p></div>
<?php else: ?>
  <div class="mk-recherche">
    <?php foreach ($funde as $fu): ?>
      <article class="block" style="margin-bottom:10px">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
          <span class="marke2"><?= Fmt::h(MkZielgruppe::ARTEN[$fu['art']] ?? $fu['art']) ?></span>
          <?php if ($fu['branche'] !== ''): ?><span class="marke2"><?= Fmt::h($branchen[$fu['branche']] ?? $fu['branche']) ?><?= $fu['land'] !== '' ? ' · ' . Fmt::h($fu['land']) : '' ?></span><?php endif; ?>
          <span class="mk-fein">Relevanz <?= str_repeat('●', (int) $fu['relevanz']) . str_repeat('○', 5 - (int) $fu['relevanz']) ?> · <?= Fmt::h(date('d.m.Y', strtotime((string) $fu['created_at']))) ?></span>
          <?php if ($fu['status'] !== 'neu'): ?><span class="marke2 <?= $fu['status'] === 'gemerkt' ? 'gut' : '' ?>"><?= Fmt::h(MkZielgruppe::STATUS_RECHERCHE[$fu['status']] ?? $fu['status']) ?></span><?php endif; ?>
        </div>
        <h2 style="margin:0 0 6px;font-size:15px"><?= Fmt::h($fu['titel']) ?></h2>
        <p style="margin:0 0 8px;max-width:85ch;line-height:1.6;white-space:pre-line"><?= Fmt::h($fu['text']) ?></p>
        <p class="mk-fein" style="margin:0 0 10px">Quelle<?= count($fu['q']) > 1 ? 'n' : '' ?>:
          <?php foreach ($fu['q'] as $i => $q): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= Fmt::h((string) $q['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Fmt::h((string) $q['titel']) ?></a><?php endforeach; ?></p>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach (['gemerkt' => 'Merken', 'verwendet' => 'Als verwendet markieren', 'verworfen' => 'Verwerfen'] as $st => $wort): if ($fu['status'] === $st) { continue; } ?>
            <form method="post" action="<?= Fmt::h(url('recherche')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_status"><input type="hidden" name="id" value="<?= (int) $fu['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>"><input type="hidden" name="zurueck" value="<?= Fmt::h($zurueck) ?>"><button class="knopf klein"><?= Fmt::h($wort) ?></button></form>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
