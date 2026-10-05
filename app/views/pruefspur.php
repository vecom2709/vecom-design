<?php
/* Prüfspur (Phase 9, 06.10.2026): wer hat wann was geändert — nur lesen, nur Admin.
   Daten: $spur (zeilen, weiter), $auswahl (wer, objekt), $f (Filter). Geheimes ist in Pruefspur geschwärzt. */
$q = static fn(array $mehr = []): string => url('pruefspur') . '?' . http_build_query(array_filter($mehr + $f, static fn($v) => $v !== '' && $v !== null && $v !== 0));
?>
<style>
  .ps-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
  .ps-filter .feld{margin:0}
  .ps-z td{vertical-align:top}
  .ps-aend{margin:0;padding:0;list-style:none;font-size:12.5px;line-height:1.5}
  .ps-aend li{word-break:break-word}
  .ps-aend s{color:var(--rot);text-decoration-color:rgba(255,138,138,.6)}
  .ps-aend ins{color:var(--gruen);text-decoration:none}
  .ps-aend b{color:var(--dim);font-weight:500}
</style>
<div class="kopf"><div><h1>Prüfspur</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Wer hat wann was getan — aus der Verwaltung, dem Partnerbereich und den Automationen. Nur lesen. Passwörter, Schlüssel und Tokens sind geschwärzt.</p></div></div>

<div class="block">
  <form class="ps-filter" method="get" action="<?= Fmt::h(url('pruefspur')) ?>">
    <div class="feld" style="flex:0 0 180px"><label for="ps-wer">Wer</label><select id="ps-wer" name="wer"><option value="">Alle</option>
      <?php foreach ($auswahl['wer'] as $w): ?><option<?= $f['wer'] === $w ? ' selected' : '' ?>><?= Fmt::h((string) $w) ?></option><?php endforeach; ?></select></div>
    <div class="feld" style="flex:0 0 200px"><label for="ps-tat">Tat (Anfang)</label><input id="ps-tat" name="tat" value="<?= Fmt::h($f['tat']) ?>" placeholder="z. B. partner_"></div>
    <div class="feld" style="flex:0 0 180px"><label for="ps-obj">Objekt</label><select id="ps-obj" name="objekt"><option value="">Alle</option>
      <?php foreach ($auswahl['objekt'] as $o): ?><option<?= $f['objekt'] === $o ? ' selected' : '' ?>><?= Fmt::h((string) $o) ?></option><?php endforeach; ?></select></div>
    <div class="feld" style="flex:0 0 100px"><label for="ps-id">Nr.</label><input id="ps-id" name="objekt_id" inputmode="numeric" value="<?= $f['objekt_id'] ? (int) $f['objekt_id'] : '' ?>"></div>
    <div class="feld" style="flex:0 0 150px"><label for="ps-von">Von</label><input id="ps-von" type="date" name="von" value="<?= Fmt::h($f['von']) ?>"></div>
    <div class="feld" style="flex:0 0 150px"><label for="ps-bis">Bis</label><input id="ps-bis" type="date" name="bis" value="<?= Fmt::h($f['bis']) ?>"></div>
    <button class="knopf">Filtern</button>
    <?php if (array_filter($f)): ?><a class="knopf" href="<?= Fmt::h(url('pruefspur')) ?>">Zurücksetzen</a><?php endif; ?>
  </form>
</div>

<div class="block">
  <?php if (!$spur['zeilen']): ?>
    <div class="leer">Keine Einträge für diese Auswahl.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table id="pruefspur-liste">
      <thead><tr><th>Wann</th><th>Wer</th><th>Tat</th><th>Objekt</th><th>Was sich änderte</th></tr></thead><tbody>
      <?php foreach ($spur['zeilen'] as $z): ?>
        <tr class="ps-z">
          <td style="white-space:nowrap;font-size:12.5px"><?= Fmt::h(date('d.m.Y H:i:s', strtotime((string) $z['created_at']))) ?><?php if ($z['ip']): ?><div style="color:var(--leise);font-size:11.5px"><?= Fmt::h((string) $z['ip']) ?></div><?php endif; ?></td>
          <td style="font-size:13px"><a href="<?= Fmt::h($q(['wer' => (string) $z['actor'], 'vor' => null])) ?>"><?= Fmt::h((string) $z['actor']) ?></a></td>
          <td style="font-size:13px"><code><?= Fmt::h((string) $z['action']) ?></code></td>
          <td style="font-size:13px;white-space:nowrap"><a href="<?= Fmt::h($q(['objekt' => (string) $z['entity'], 'objekt_id' => $z['entity_id'] !== null ? (int) $z['entity_id'] : '', 'vor' => null])) ?>"><?= Fmt::h((string) $z['entity']) ?><?= $z['entity_id'] !== null ? ' ' . (int) $z['entity_id'] : '' ?></a></td>
          <td><?php if (!$z['aenderung']): ?><span style="color:var(--leise);font-size:12.5px">—</span><?php else: ?>
            <ul class="ps-aend"><?php foreach (array_slice($z['aenderung'], 0, 8) as $a): ?>
              <li><b><?= Fmt::h($a['feld']) ?>:</b> <?php if ($a['vorher'] !== null): ?><s><?= Fmt::h($a['vorher']) ?></s> → <?php endif; ?><ins><?= $a['nachher'] !== null ? Fmt::h($a['nachher']) : '(leer)' ?></ins></li>
            <?php endforeach; ?><?php if (count($z['aenderung']) > 8): ?><li style="color:var(--leise)">… und <?= count($z['aenderung']) - 8 ?> weitere Felder</li><?php endif; ?></ul>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($spur['weiter'] !== null): ?>
      <p style="margin:12px 0 0"><a class="knopf" href="<?= Fmt::h($q(['vor' => $spur['weiter']])) ?>">Ältere Einträge</a></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
