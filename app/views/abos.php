<div class="kopf"><div><h1>Betreuung</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Die monatlichen Verträge. Website und Betreuung sind zwei getrennte Verträge —
    hier stehen nur die monatlichen.
    <?php if ($monatlich > 0): ?>
      Zusammen <b><?= Fmt::h(Fmt::geld($monatlich)) ?></b> im Monat.
    <?php endif; ?></p></div>
</div>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch kein Betreuungsvertrag. Anlegen kannst du einen in der Kundenakte.</div></div>
<?php else: ?>
<div class="block"><div class="tabellenrahmen"><table>
  <thead><tr><th>Kunde</th><th>Paket</th><th>Monatlich</th><th>Zahlart</th>
    <th>Läuft seit</th><th>Mindestens bis</th><th>Zustand</th></tr></thead><tbody>
  <?php foreach ($liste as $a): ?>
    <?php
      $ton = ['aktiv' => 'gut', 'gekuendigt' => 'warnung', 'beendet' => '', 'angelegt' => ''][$a['status']] ?? '';
      $wort = ['aktiv' => 'läuft', 'gekuendigt' => 'gekündigt', 'beendet' => 'beendet',
               'angelegt' => 'angelegt'][$a['status']] ?? $a['status'];
    ?>
    <tr>
      <td><a href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'])) ?>"><?= Fmt::h($a['firma'] ?: $a['kunde']) ?></a></td>
      <td><?= Fmt::h((string) $a['paket_name']) ?></td>
      <td><?= Fmt::h(Fmt::geld((int) $a['betrag_cents'], (string) $a['currency'])) ?></td>
      <td><?= Fmt::h(Abo::ZAHLARTEN[$a['zahlart']] ?? (string) $a['zahlart']) ?></td>
      <td><?= Fmt::h(Fmt::datum((string) $a['beginn'])) ?></td>
      <td><?= Fmt::h(Fmt::datum((string) $a['mindestlaufzeit_bis'])) ?></td>
      <td><span class="marke2 <?= Fmt::h($ton) ?>"><?= Fmt::h($wort) ?></span>
        <?php if ($a['laeuft_bis']): ?>
          <div style="color:var(--leise);font-size:12px">bis <?= Fmt::h(Fmt::datum((string) $a['laeuft_bis'])) ?></div>
        <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
