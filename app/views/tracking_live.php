<?php
/* Aktuelle Partner-Besucher (letzte 5 Minuten) — auch als Teil für das Nachladen. Erwartet $live. */
$lDauer = static function (string $a, string $b): string { $s = max(0, strtotime($b) - strtotime($a)); return sprintf('%02d:%02d', intdiv($s, 60), $s % 60); };
?>
<h2>Aktuelle Partner-Besucher <span class="mehr"><?= count($live) ?> in den letzten 5 Minuten · <?= date('H:i:s') ?></span></h2>
<?php if (!$live): ?>
  <p class="leise" style="margin:0">Gerade ist niemand über einen Partnerlink auf der Seite.</p>
<?php else: ?>
  <div class="st-live">
    <?php foreach ($live as $v): $aktiv = strtotime((string) $v['zuletzt_am']) >= time() - 120; ?>
      <article>
        <b><?= $aktiv ? '<span class="st-punkt" aria-hidden="true"></span>' : '' ?><?= Fmt::h($v['visitor_id']) ?></b>
        <dl>
          <dt>Partner</dt><dd><?= Fmt::h($v['partner']) ?></dd>
          <dt>Land</dt><dd><?= Fmt::h(Geo::landName((string) $v['land'])) ?><?= $v['region'] !== '' ? ' · ' . Fmt::h($v['region']) : '' ?><?= ($v['stadt'] ?? '') !== '' ? ' · ' . Fmt::h((string) $v['stadt']) : '' ?></dd>
          <dt>Gerät</dt><dd><?= Fmt::h(Spur::geraetName((string) $v['geraet'])) ?></dd>
          <dt>Quelle</dt><dd><?= Fmt::h(Spur::quelleName((string) $v['quelle'])) ?></dd>
          <dt>Einstieg</dt><dd><?= Fmt::h($v['einstieg']) ?></dd>
          <dt>Aktuell</dt><dd><?= Fmt::h($v['aktuell']) ?></dd>
          <dt>Dauer</dt><dd><?= Fmt::h($lDauer((string) $v['start_am'], (string) $v['zuletzt_am'])) ?></dd>
          <dt>Status</dt><dd><?= $aktiv ? 'aktiv' : 'zuletzt vor ' . max(1, intdiv(time() - strtotime((string) $v['zuletzt_am']), 60)) . ' Min.' ?> · <?= Fmt::h(Spur::STATUS[$v['status']] ?? $v['status']) ?></dd>
        </dl>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
