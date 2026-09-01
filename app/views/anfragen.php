<div class="kopf"><div><h1>Anfragen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Was über das Formular auf der Website hereinkommt.
  Der Kunde ist ab dem Absenden angelegt — aus einer Anfrage wird mit einem Knopf eine Bestellung.</p></div>
</div>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Anfrage. Die erste entsteht, sobald jemand das Formular abschickt.</div>
  <?php else: ?>
    <table><thead><tr><th>Eingegangen</th><th>Wer</th><th>Paket</th><th>Stand</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $a): ?>
      <?php
        $marke = ['neu' => 'gut', 'in_arbeit' => 'warnung', 'bestellung' => '', 'erledigt' => ''][$a['status']] ?? '';
        $wort  = ['neu' => 'Neu', 'in_arbeit' => 'In Arbeit', 'bestellung' => 'Bestellung', 'erledigt' => 'Erledigt'][$a['status']] ?? $a['status'];
      ?>
      <tr>
        <td><?= Fmt::h(Fmt::datum($a['created_at'])) ?><br>
            <small style="color:var(--leise)"><?= Fmt::h(strtoupper((string) $a['sprache'])) ?></small></td>
        <td><a href="<?= Fmt::h(url('anfragen/' . (int) $a['id'])) ?>"><?= Fmt::h($a['name']) ?></a><br>
            <small style="color:var(--leise)"><?= Fmt::h($a['email']) ?></small></td>
        <td><?= $a['paket_name'] ? Fmt::h($a['paket_name']) : '<span style="color:var(--leise)">—</span>' ?></td>
        <td><span class="marke2 <?= $marke ?>"><?= Fmt::h($wort) ?></span>
            <?php if ($a['order_id']): ?><br><small><a href="<?= Fmt::h(url('bestellungen/' . (int) $a['order_id'])) ?>">zur Bestellung</a></small><?php endif; ?></td>
        <td style="text-align:right"><a class="knopf" href="<?= Fmt::h(url('anfragen/' . (int) $a['id'])) ?>">Ansehen</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
