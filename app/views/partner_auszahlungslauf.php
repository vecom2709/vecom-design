<?php
/* Auszahlungslauf (Phase 5, 05.10.2026; Spezifikation 30: „Auszahlungsläufe mit Sammelfreigabe“).
   Zwei Schritte, jeder mit eigener Rückfrage (Ablauf::TRAGWEITE): erst freigeben, was nach der Wartezeit
   auf Sie wartet, dann auszahlen, was bereit ist. Ausgezahlt wird je Partner über PartnerWege::auszahlen —
   derselbe Weg wie der Knopf in der Akte, mit Vereinbarung, Mindestbetrag und Zahlungsprüfung.
   Die automatische Auszahlung (Uwe: „Beides“, Tageslimit) läuft daneben unverändert weiter. */
$lvl = ['starter' => 'Starter', 'silber' => 'Silber', 'gold' => 'Gold', 'platin' => 'Platin'];
$frei = array_values(array_filter($lauf, static fn($z) => $z['freigabe_n'] > 0));
$bereit = array_values(array_filter($lauf, static fn($z) => $z['bereit_n'] > 0));
$summe = static fn(array $l, string $k): int => array_sum(array_column($l, $k));
$wegName = static fn(?string $w): string => $w === null ? '— kein Weg' : (PartnerWege::WEGE[$w] ?? $w);
$auto = Partner::einstellung('partner_auto_auszahlen') === '1';
?>
<div class="kopf"><h1>Auszahlungslauf</h1><a class="knopf" href="<?= Fmt::h(url('partner')) ?>">← Partner</a></div>
<div class="block" style="padding:14px 18px">
  <p style="font-size:13.5px;line-height:1.7;margin:0;color:var(--dim)">
    <b style="color:var(--text)">Zur Freigabe</b> stehen Provisionen, deren Wartezeit vorbei ist und die du selbst freigeben wolltest.
    <b style="color:var(--text)">Auszahlungsbereit</b> geht über den Weg des Partners raus — Stripe, PayPal und Wise sofort, SEPA über die Datei, Verrechnung in der Akte.
    Die automatische Auszahlung ist <b style="color:var(--text)"><?= $auto ? 'an' : 'aus' ?></b>
    (Tageslimit <?= Fmt::h(Fmt::geld(Partner::zahl('partner_auto_tageslimit_cents'))) ?>, Mindestbetrag <?= Fmt::h(Fmt::geld(Partner::zahl('partner_mindest_cents'))) ?>).</p>
</div>

<?php if (!empty($ergebnis)): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Ergebnis des letzten Laufs</h2>
  <ul style="margin:0;padding-left:18px;font-size:13.5px;line-height:1.7">
    <?php foreach ($ergebnis as $r): $rn = array_values(array_filter($lauf, static fn($z) => $z['id'] === (int) $r['id']))[0]['name'] ?? ('Partner ' . (int) $r['id']); ?>
      <li><span class="marke2 <?= $r['ok'] ? 'gut' : '' ?>"><?= $r['ok'] ? 'ok' : 'nicht' ?></span> <a href="<?= Fmt::h(url('partner/' . (int) $r['id'])) ?>"><?= Fmt::h($rn) ?></a> — <?= Fmt::h($r['text']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Zur Freigabe <span style="font-weight:400;color:var(--leise);font-size:12.5px"><?= count($frei) ?> Partner · <?= Fmt::h(Fmt::geld($summe($frei, 'freigabe_cents'))) ?></span></h2>
  <?php if (!$frei): ?><p style="color:var(--leise);font-size:13px;margin:0">Nichts wartet auf deine Freigabe.</p><?php else: ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_lauf_freigeben">
    <div class="tabellenrahmen"><table>
      <thead><tr><th style="width:34px"><input type="checkbox" checked aria-label="Alle" data-alle="lf"></th><th>Partner</th><th>Level</th><th style="text-align:right">Provisionen</th><th style="text-align:right">Betrag</th></tr></thead><tbody>
      <?php foreach ($frei as $z): ?>
        <tr><td><input type="checkbox" name="partner[]" value="<?= $z['id'] ?>" checked data-gruppe="lf" aria-label="<?= Fmt::h($z['name']) ?>"></td>
          <td><a href="<?= Fmt::h(url('partner/' . $z['id'])) ?>"><?= Fmt::h($z['name']) ?></a> <span style="color:var(--leise);font-size:12px"><?= Fmt::h($z['code']) ?></span></td>
          <td><?= Fmt::h($lvl[$z['stufe'] ?? ''] ?? 'eigener Satz') ?></td>
          <td style="text-align:right"><?= $z['freigabe_n'] ?></td><td style="text-align:right"><?= Fmt::h(Fmt::geld($z['freigabe_cents'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <button class="knopf haupt" style="margin-top:10px">Gewählte freigeben</button>
  </form>
  <?php endif; ?>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Auszahlungsbereit <span style="font-weight:400;color:var(--leise);font-size:12.5px"><?= count($bereit) ?> Partner · <?= Fmt::h(Fmt::geld($summe($bereit, 'netto_cents'))) ?> nach Einbehalt</span></h2>
  <?php if (!$bereit): ?><p style="color:var(--leise);font-size:13px;margin:0">Nichts ist auszahlungsbereit.</p><?php else: ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_lauf_auszahlen">
    <div class="tabellenrahmen"><table>
      <thead><tr><th style="width:34px"><input type="checkbox" aria-label="Alle" data-alle="la"></th><th>Partner</th><th>Weg</th><th style="text-align:right">Provisionen</th><th style="text-align:right">Auszahlung</th><th>Hinweis</th></tr></thead><tbody>
      <?php foreach ($bereit as $z): $geht = $z['weg'] !== null && in_array($z['weg'], PartnerWege::AUTOMATISCH, true) && $z['mindest_ok']; ?>
        <tr><td><input type="checkbox" name="partner[]" value="<?= $z['id'] ?>" data-gruppe="la" aria-label="<?= Fmt::h($z['name']) ?>"<?= $geht ? '' : ' disabled' ?>></td>
          <td><a href="<?= Fmt::h(url('partner/' . $z['id'])) ?>"><?= Fmt::h($z['name']) ?></a> <span style="color:var(--leise);font-size:12px"><?= Fmt::h($lvl[$z['stufe'] ?? ''] ?? '') ?></span></td>
          <td><?= Fmt::h($wegName($z['weg'])) ?></td>
          <td style="text-align:right"><?= $z['bereit_n'] ?></td><td style="text-align:right"><b><?= Fmt::h(Fmt::geld($z['netto_cents'])) ?></b></td>
          <td style="font-size:12.5px;color:var(--leise)"><?= !$z['mindest_ok'] ? 'unter dem Mindestbetrag' : ($z['weg'] === null ? 'Partner hat keinen Weg' : (!$geht ? 'in der Akte auszahlen (' . Fmt::h($wegName($z['weg'])) . ')' : '')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <button class="knopf haupt" style="margin-top:10px">Gewählte jetzt auszahlen</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!empty($unterwegs)): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Unterwegs, noch nicht bestätigt</h2>
  <div class="tabellenrahmen"><table><tbody>
    <?php foreach ($unterwegs as $a): ?>
      <tr><td><?= Fmt::h(Fmt::datum((string) $a['created_at'])) ?></td><td><?= Fmt::h((string) $a['nummer']) ?></td>
        <td><a href="<?= Fmt::h(url('partner/' . (int) $a['partner_id'])) ?>"><?= Fmt::h((string) $a['name']) ?></a></td>
        <td><?= Fmt::h($wegName((string) $a['weg'])) ?></td><td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $a['betrag_cents'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <p style="color:var(--leise);font-size:12.5px;margin:8px 0 0">Bestätigen oder abbrechen in der Akte des Partners.</p>
</div>
<?php endif; ?>
<script>
/* „Alle“ wählt die Zeilen seiner Tabelle — gesperrte (ohne Weg, unter dem Mindestbetrag) bleiben aus. */
document.querySelectorAll('[data-alle]').forEach(function (a) {
  a.addEventListener('change', function () {
    document.querySelectorAll('[data-gruppe="' + a.dataset.alle + '"]').forEach(function (c) { if (!c.disabled) { c.checked = a.checked; } });
  });
});
</script>
