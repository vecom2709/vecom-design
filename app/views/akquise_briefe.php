<?php
/** @var list<array> $kandidaten @var array $offen @var bool $bereit
 *  Brief-Serie (27.09.2026): Vorschauen erzeugen → Gesamtpreis → eine Rückfrage → alle raus. */
$akqTeil = 'briefe';
$euro = static fn(int $c): string => number_format($c / 100, 2, ',', '.') . ' €';
?>
<div class="kopf"><div><h1>Brief-Serie</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Bis zu <?= AkquiseBriefserie::HOECHSTENS ?> freigegebene Briefe auf einmal — jeder mit Vorschau und Preis, dann eine Rückfrage für alle.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>

<?php if (!$bereit): ?>
  <div class="block"><p class="akq-klein" style="margin:0">Für den Briefdienst fehlt noch der Schlüssel — unter <a href="<?= Fmt::h(url('akquise/regeln#briefdienst')) ?>">Regeln &amp; Versand → Briefdienst</a> eintragen.</p></div>
<?php endif; ?>

<?php if ($offen['briefe']): $ids = array_map(static fn($b) => (int) $b['id'], $offen['briefe']); ?>
<div class="block" style="border-color:rgba(241,211,139,.45)">
  <h2 style="font-size:15px;margin:0 0 8px">Bereit zum Verschicken <span class="akq-klein" style="font-weight:400">· <?= count($ids) ?> Briefe · zusammen <b style="color:var(--text)"><?= Fmt::h($euro($offen['summe'])) ?></b><?= $offen['test'] ? ' · TEST (Sandbox)' : '' ?></span></h2>
  <div class="tabellenrahmen"><table><thead><tr><th>Betrieb</th><th>Ort</th><th style="text-align:right">Seiten</th><th style="text-align:right">Preis</th><th>Blatt</th></tr></thead><tbody>
    <?php foreach ($offen['briefe'] as $b): ?>
      <tr><td><a href="<?= Fmt::h(url('akquise/' . (int) $b['firma_id'])) ?>"><?= Fmt::h((string) $b['name']) ?></a></td><td class="akq-klein"><?= Fmt::h((string) ($b['stadt'] ?? '')) ?></td>
        <td style="text-align:right"><?= (int) ($b['seiten'] ?? 0) ?: '—' ?></td><td style="text-align:right"><?= Fmt::h($euro((int) $b['kosten_cents'])) ?></td>
        <td><?php if (!empty($b['pdf_url'])): ?><a href="<?= Fmt::h((string) $b['pdf_url']) ?>" target="_blank" rel="noopener">ansehen</a><?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>"
          data-frage="<?= Fmt::h(count($ids) . ' Briefe für zusammen ' . $euro($offen['summe']) . ($offen['test'] ? ' im TESTBETRIEB bestätigen? Nichts wird gedruckt.' : ' jetzt an Poste Italiane schicken? Gedruckt, frankiert, verschickt — und bezahlt. Eine zweite Ansprache ist danach bei jedem gesperrt.')) ?>"
          data-ja="<?= Fmt::h('Ja, alle ' . count($ids) . ' verschicken') ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_briefserie_senden">
      <?php foreach ($ids as $bid): ?><input type="hidden" name="briefe[]" value="<?= $bid ?>"><?php endforeach; ?>
      <button class="knopf haupt">Alle <?= count($ids) ?> verschicken · <?= Fmt::h($euro($offen['summe'])) ?></button></form>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_briefserie_verwerfen">
      <?php foreach ($ids as $bid): ?><input type="hidden" name="briefe[]" value="<?= $bid ?>"><?php endforeach; ?>
      <button class="knopf">Alle verwerfen</button></form>
  </div>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Freigegebene Briefe ohne Versand <span class="akq-klein" style="font-weight:400">· <?= count($kandidaten) ?></span></h2>
  <?php if (!$kandidaten): ?>
    <p class="akq-klein" style="margin:0">Keine. Briefe entstehen in der Firmenakte (Brieftext freigeben); hier landen alle, die noch nicht verschickt sind.</p>
  <?php else: ?>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_briefserie_vorbereiten">
    <div class="tabellenrahmen"><table><thead><tr><th style="width:34px"></th><th>Betrieb</th><th>Ort</th><th>Hinweis</th></tr></thead><tbody>
      <?php foreach ($kandidaten as $k): ?>
        <tr><td><input type="checkbox" name="firmen[]" value="<?= (int) $k['id'] ?>" <?= $k['grund'] === null ? 'checked' : 'disabled' ?> style="width:auto" aria-label="<?= Fmt::h($k['name']) ?>"></td>
          <td><a href="<?= Fmt::h(url('akquise/' . (int) $k['id'])) ?>"><?= Fmt::h($k['name']) ?></a></td><td class="akq-klein"><?= Fmt::h($k['stadt']) ?></td>
          <td class="akq-klein"><?= $k['grund'] !== null ? Fmt::h($k['grund']) : '—' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <label class="akq-haken" style="margin-top:10px"><input type="checkbox" name="bestaetigt" value="1" required> Bei keinem dieser Betriebe ist ein Werbewiderspruch bekannt (Brief mit Widerspruchshinweis).</label>
    <button class="knopf" style="margin-top:10px"<?= $bereit ? '' : ' disabled' ?>>Vorschauen erzeugen (Preis kommt vom Briefdienst, nichts wird verschickt)</button>
  </form>
  <?php endif; ?>
</div>
