<?php
/**
 * Meldungen.
 *
 * Eine Meldung ist ein Zuruf, kein Beleg: Was tatsächlich passiert ist,
 * steht im Verlauf und in der Prüfspur, und die bleiben unangetastet.
 * Deshalb darf hier gelöscht werden, sobald etwas erledigt ist — sonst
 * wird die Liste nur länger, und wo hundert alte Zeilen stehen, sieht
 * niemand mehr die eine neue.
 */
$knopf = static function (string $tat, int $id, string $text, string $stil = '') {
    ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= Fmt::h($tat) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="zurueck" value="benachrichtigungen">
      <button class="knopf" style="padding:3px 9px;min-width:0;font-size:12px;<?= $stil ?>"><?= $text ?></button>
    </form>
    <?php
};
?>

<div class="kopf">
  <div><h1>Benachrichtigungen</h1>
    <div class="weg"><?= (int) $offen ?> ungelesen · <?= (int) $gelesen ?> gelesen</div></div>
  <div class="rechts">
    <?php if ($offen > 0): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="meldungen_gelesen">
        <input type="hidden" name="zurueck" value="benachrichtigungen">
        <button class="knopf">Alle als gelesen markieren</button></form>
    <?php endif; ?>
    <?php if ($gelesen > 0): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>"
            data-frage="<?= (int) $gelesen ?> gelesene Meldungen löschen? Der Verlauf bleibt davon unberührt." data-ja="Ja, löschen">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="meldungen_weg">
        <input type="hidden" name="zurueck" value="benachrichtigungen">
        <button class="knopf" style="border-color:rgba(255,138,138,.4);color:var(--rot)">
          Gelesene löschen (<?= (int) $gelesen ?>)</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Nichts vorhanden.</div>
  <?php else: ?>
    <ul class="verlauf">
      <?php foreach ($liste as $m): ?>
        <?php $farbe = ['schlecht' => 'rot', 'warnung' => 'gelb', 'gut' => 'gruen'][$m['level']] ?? 'cyan'; ?>
        <li style="<?= $m['read_at'] ? 'opacity:.45' : '' ?>">
          <span class="punkt" style="background:var(--<?= $farbe ?>)"></span>
          <span style="min-width:0">
            <?= $m['link']
                ? '<a href="' . Fmt::h(url(ltrim((string) $m['link'], '/'))) . '">' . Fmt::h((string) $m['title']) . '</a>'
                : Fmt::h((string) $m['title']) ?>
            <?php if ($m['body']): ?><br><small><?= Fmt::h((string) $m['body']) ?></small><?php endif; ?>
          </span>
          <span class="wann" style="display:flex;gap:6px;align-items:center">
            <?= Fmt::h(Fmt::seit($m['created_at'])) ?>
            <?php if (!$m['read_at']) { $knopf('meldung_gelesen', (int) $m['id'], 'Gelesen'); } ?>
            <?php $knopf('meldung_weg', (int) $m['id'], '&times;', 'color:var(--leise)'); ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p style="color:var(--leise);font-size:12.5px;margin-top:14px;padding-top:12px;border-top:1px solid var(--linie)">
    Gelesene Meldungen älter als 30 Tage räumt der Cronjob von selbst weg — ungelesene bleiben stehen,
    egal wie alt. Was tatsächlich passiert ist, steht unabhängig davon unter
    <a href="<?= Fmt::h(url('aktivitaeten')) ?>">Aktivitäten</a>.</p>
</div>
