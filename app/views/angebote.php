<?php
/* Alle Angebote. Entwuerfe zuerst — die sind Arbeit, die noch aussteht. */
$stufen = [
    'entwurf'    => ['',        'Entwurf'],
    'gesendet'   => ['warnung', 'beim Kunden'],
    'angenommen' => ['gut',     'angenommen'],
    'abgelehnt'  => ['',        'abgelehnt'],
    'abgelaufen' => ['',        'abgelaufen'],
];
?>
<div class="kopf"><h1>Angebote</h1></div>

<div class="block"><div class="tabellenrahmen"><table>
<thead><tr>
  <th>Nummer</th><th>Kunde</th><th class="num">Summe</th><th class="num">Monatlich</th>
  <th>Stand</th><th>Gültig bis</th>
</tr></thead>
<tbody>
<?php if (!$liste): ?>
  <tr><td colspan="6"><div class="leer">Noch kein Angebot. Sie entstehen aus einem eingegangenen Bedarf.</div></td></tr>
<?php endif; ?>
<?php foreach ($liste as $a): ?>
  <?php
    [$farbe, $wort] = $stufen[(string) $a['status']] ?? ['', (string) $a['status']];
    $laeuftAus = $a['status'] === 'gesendet' && $a['gueltig_bis'] !== null
                 && (string) $a['gueltig_bis'] < date('Y-m-d', strtotime('+3 days'));
  ?>
  <tr>
    <td><a href="<?= Fmt::h(url('angebote/' . $a['id'])) ?>"><strong><?= Fmt::h((string) $a['nummer']) ?></strong></a>
      <?php if (trim((string) $a['titel']) !== ''): ?>
        <div style="color:var(--leise);font-size:12.5px"><?= Fmt::h((string) $a['titel']) ?></div>
      <?php endif; ?>
    </td>
    <td><?= Fmt::h((string) ($a['kunde'] ?? '—')) ?></td>
    <td class="num"><?= Fmt::geld((int) $a['summe_cents'], (string) $a['currency']) ?></td>
    <td class="num"><?= (int) $a['monatlich_cents'] ? Fmt::geld((int) $a['monatlich_cents']) . '/Mon.' : '—' ?></td>
    <td><span class="marke2 <?= Fmt::h($farbe) ?>"><?= Fmt::h($wort) ?></span></td>
    <td style="font-size:12.5px;<?= $laeuftAus ? 'color:var(--warn,#d19a2a)' : 'color:var(--leise)' ?>">
      <?= $a['gueltig_bis'] ? Fmt::h(Fmt::datum((string) $a['gueltig_bis'])) : '—' ?>
      <?php if ($laeuftAus): ?><br>läuft bald aus<?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div></div>
