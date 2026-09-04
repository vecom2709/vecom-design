<div class="kopf"><div><h1>Kundenstimmen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Kunden schreiben sie auf ihrer eigenen Seite, sobald die Website online ist. Was du hier
    freigibst, steht danach von allein auf vecom-design.it — und was du versteckst, verschwindet
    wieder.</p></div>
</div>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch keine Stimme. Die Vorlage „Um Bewertung bitten“ in der
    Kundenakte fragt danach — am besten ein paar Wochen nach dem Onlinegang.</div></div>
<?php else: ?>
  <?php foreach ($liste as $s): ?>
    <div class="block">
      <h2><?= Fmt::h($s['firma'] ?: $s['name']) ?>
        <span class="mehr">
          <?php if ($s['status'] === 'veroeffentlicht'): ?><span class="marke2 gut">auf der Website</span>
          <?php elseif ($s['status'] === 'versteckt'): ?><span class="marke2">versteckt</span>
          <?php else: ?><span class="marke2 warnung">neu</span><?php endif; ?>
          <?php if (!$s['erlaubnis']): ?><span class="marke2 schlecht">keine Erlaubnis</span><?php endif; ?>
        </span></h2>

      <?php if ($s['sterne']): ?>
        <div style="color:var(--cyan);letter-spacing:2px;margin-bottom:6px"><?= str_repeat('★', (int) $s['sterne']) ?><span
          style="color:var(--linie2)"><?= str_repeat('★', 5 - (int) $s['sterne']) ?></span></div>
      <?php endif; ?>

      <p style="white-space:pre-wrap;font-size:15px;line-height:1.65;color:var(--dim)"><?= Fmt::h((string) $s['text']) ?></p>
      <p style="color:var(--leise);font-size:12.5px">
        <?= Fmt::h($s['name']) ?><?= $s['firma'] ? ' · ' . Fmt::h((string) $s['firma']) : '' ?><?= $s['ort'] ? ' · ' . Fmt::h((string) $s['ort']) : '' ?>
        · <?= Fmt::h(strtoupper((string) $s['sprache'])) ?>
        · <?= Fmt::h(Fmt::datum((string) $s['created_at'])) ?></p>

      <?php if (!$s['erlaubnis']): ?>
        <div class="hinweis schlecht" style="margin:10px 0">Der Kunde hat der Veröffentlichung mit
          Namen nicht zugestimmt. Ohne seine Zustimmung darf sie nicht auf die Website. Frag ihn —
          und setz das Häkchen erst, wenn er zugestimmt hat.</div>
      <?php endif; ?>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <?php if ($s['status'] !== 'veroeffentlicht' && $s['erlaubnis']): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="stimme_frei">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="knopf haupt">Auf die Website</button></form>
        <?php endif; ?>
        <?php if ($s['status'] === 'veroeffentlicht'): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="stimme_weg">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="knopf">Von der Website nehmen</button></form>
        <?php endif; ?>
        <?php if (!$s['erlaubnis']): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
                data-frage="Hat der Kunde der Veröffentlichung mit seinem Namen wirklich zugestimmt?" data-ja="Ja, er hat zugestimmt">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="stimme_erlaubnis">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="knopf">Er hat zugestimmt</button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
