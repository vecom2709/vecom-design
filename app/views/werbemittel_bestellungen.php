<?php
/* Marketing Center › Bestellungen (03.10.2026, Phase 3).
   Der Weg einer Bestellung: angefragt/offen → bezahlt → beim Drucker →
   versendet. Je Zeile nur der Knopf für den nächsten Schritt. Die
   Druckdatei ist genau die, die der Partner freigegeben hat. */
$S = [
    'angefragt' => ['Angefragt — Zahlung klären', 'warnung'], 'offen' => ['Bezahlseite offen', 'warnung'],
    'bezahlt' => ['Bezahlt — jetzt drucken lassen', 'gut'], 'beim_drucker' => ['Beim Drucker', ''],
    'versendet' => ['Versendet', 'gut'], 'storniert' => ['Storniert', ''],
];
?>
<div class="kopf"><div><h1>Werbemittel-Bestellungen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px;max-width:760px">
    Zahlweg gerade: <strong><?= $zahlweg === 'stripe' ? 'Stripe (Partner zahlen direkt)' : 'Anfrage (du klärst die Zahlung)' ?></strong>.
    Bezahlt gilt eine Bestellung nur, wenn Stripe es meldet oder du es hier bestätigst. Den Druck beauftragst du
    beim Anbieter selbst — mit der Druckdatei aus der Zeile.</p></div>
  <div class="rechts"><a class="knopf stumm" href="<?= Fmt::h(url('werbemittel')) ?>">Katalog & Preise</a></div>
</div>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch keine Bestellung.</div></div>
<?php endif; ?>

<?php foreach ($liste as $b): $pos = $b['positionen'][0] ?? null; $a = $b['adresse'];
  $ek = array_sum(array_map(static fn($x) => (int) $x['einkauf_cent'] * (int) $x['menge'], $b['positionen'])); ?>
  <div class="block" id="b<?= (int) $b['id'] ?>">
    <h2><?= Fmt::h($b['nummer']) ?>
      <span class="mehr"><span class="marke2 <?= Fmt::h($S[$b['status']][1] ?? '') ?>"><?= Fmt::h($S[$b['status']][0] ?? $b['status']) ?></span></span></h2>
    <div class="reihe" style="gap:18px;align-items:flex-start">
      <div style="flex:1;min-width:220px;font-size:14px;line-height:1.6">
        <strong><?= Fmt::h($b['partner']) ?></strong> · <?= Fmt::h($b['code']) ?><br>
        <?php if ($pos): ?><?= Fmt::h($pos['produkt_nummer'] . ' ' . $pos['name']) ?> · <?= Fmt::h($pos['variante']) ?><?= str_contains((string) $pos['variante'], (string) $pos['auflage']) ? '' : ' (' . (int) $pos['auflage'] . ' Stück)' ?><br><?php endif; ?>
        Bestellt <?= Fmt::h(Fmt::datum((string) $b['created_at'])) ?>
        <?php if ($b['bezahlt_am']): ?> · bezahlt <?= Fmt::h(Fmt::datum((string) $b['bezahlt_am'])) ?> (<?= Fmt::h((string) $b['bezahlt_wie']) ?>)<?php endif; ?>
        <?php if ($b['anbieter']): ?><br>Drucker: <?= Fmt::h((string) $b['anbieter']) ?><?= $b['anbieter_ref'] ? ' · Auftrag ' . Fmt::h((string) $b['anbieter_ref']) : '' ?><?php endif; ?>
        <?php if ($b['tracking']): ?><br>Sendung: <?php if ($b['tracking_url']): ?><a href="<?= Fmt::h((string) $b['tracking_url']) ?>" target="_blank" rel="noopener"><?= Fmt::h((string) $b['tracking']) ?></a><?php else: ?><?= Fmt::h((string) $b['tracking']) ?><?php endif; ?><?php endif; ?>
      </div>
      <div style="min-width:200px;font-size:14px;line-height:1.6">
        <span style="color:var(--leise)">Lieferadresse</span><br>
        <?= Fmt::h((string) ($a['name'] ?? '')) ?><?= !empty($a['firma']) ? '<br>' . Fmt::h((string) $a['firma']) : '' ?><br>
        <?= Fmt::h((string) ($a['strasse'] ?? '')) ?><br><?= Fmt::h(trim(($a['plz'] ?? '') . ' ' . ($a['ort'] ?? ''))) ?> · <?= Fmt::h((string) ($a['land'] ?? '')) ?>
        <?= !empty($a['telefon']) ? '<br>Tel. ' . Fmt::h((string) $a['telefon']) : '' ?>
      </div>
      <div style="min-width:160px;font-size:14px;line-height:1.6;text-align:right">
        Partner zahlt <strong><?= Fmt::h(Werbemittel::euro((int) $b['summe_cent'])) ?></strong><br>
        <span style="color:var(--leise)">Einkauf <?= Fmt::h(Werbemittel::euro($ek)) ?> · Marge <?= Fmt::h(Werbemittel::euro((int) $b['summe_cent'] - $ek)) ?></span><br>
        <?php if ($pos): ?><a class="knopf stumm" style="margin-top:6px" href="<?= Fmt::h(url('werbemittel/pdf/' . (int) $pos['entwurf_id'])) ?>" target="_blank" rel="noopener">Druckdatei (PDF)</a><?php endif; ?>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:flex-end">
      <?php if (in_array($b['status'], ['angefragt', 'offen'], true)): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0;display:flex;gap:6px;align-items:flex-end">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_b_bezahlt"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <div class="feld" style="margin:0"><label>Bezahlt per</label><select name="wie"><option value="ueberweisung">Überweisung</option><option value="bar">bar</option><option value="stripe">Stripe (von Hand geprüft)</option></select></div>
          <button class="knopf haupt">Zahlung ist da</button></form>
      <?php elseif ($b['status'] === 'bezahlt'): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_b_drucker"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <div class="feld" style="margin:0"><label>Drucker</label><input name="anbieter" required placeholder="z. B. HelloPrint" style="width:160px"></div>
          <div class="feld" style="margin:0"><label>Auftragsnummer</label><input name="ref" style="width:160px"></div>
          <button class="knopf haupt">Beim Drucker beauftragt</button></form>
      <?php elseif ($b['status'] === 'beim_drucker'): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_b_versendet"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <div class="feld" style="margin:0"><label>Sendungsnummer</label><input name="tracking" required style="width:170px"></div>
          <div class="feld" style="margin:0"><label>Link zur Verfolgung (https://…)</label><input name="url" type="url" placeholder="https://" style="width:260px"></div>
          <button class="knopf haupt">Versendet</button></form>
      <?php endif; ?>
      <?php if (in_array($b['status'], ['angefragt', 'offen', 'bezahlt'], true)): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 0 auto">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_b_storno"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <button class="knopf stumm">Stornieren</button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
