<div class="kopf"><h1>Benachrichtigungen</h1><div class="rechts">
<form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
<input type="hidden" name="tat" value="meldungen_gelesen"><input type="hidden" name="zurueck" value="benachrichtigungen">
<button class="knopf">Alle als gelesen markieren</button></form></div></div>
<div class="block"><?php if (!$liste): ?><div class="leer">Nichts vorhanden.</div><?php else: ?><ul class="verlauf">
<?php foreach ($liste as $m): ?><li style="<?= $m['read_at'] ? 'opacity:.5' : '' ?>">
<span class="punkt" style="background:var(--<?= $m['level']==='schlecht'?'rot':($m['level']==='gut'?'gruen':'cyan') ?>)"></span>
<span><?= $m['link'] ? '<a href="' . Fmt::h(url(ltrim((string) $m['link'], '/'))) . '">' . Fmt::h($m['title']) . '</a>' : Fmt::h($m['title']) ?>
<br><small><?= Fmt::h($m['body']) ?></small></span>
<span class="wann"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
