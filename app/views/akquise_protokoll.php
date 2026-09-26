<?php
/** @var array $eintraege */
$akqTeil = 'protokoll';
?>
<div class="kopf"><div><h1>Akquise-Protokoll</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Jeder automatische Schritt: gefunden, Website erkannt, Audit, Befunde, Score,
    Text, Compliance, Freigabe, Versand, Antwort. Die letzten 300 Einträge.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<div class="block">
  <?php if (!$eintraege): ?><div class="leer">Noch nichts passiert.</div><?php else: ?>
  <div class="tabellenrahmen"><table><thead><tr><th>Zeit</th><th>Schritt</th><th>Firma</th><th>Was</th><th>Wer</th></tr></thead><tbody>
    <?php foreach ($eintraege as $p): ?>
      <tr><td class="akq-klein" style="white-space:nowrap"><?= Fmt::h(Fmt::zeit((string) $p['created_at'])) ?></td>
        <td><span class="marke2"><?= Fmt::h((string) $p['schritt']) ?></span></td>
        <td><?php if ($p['firma_id']): ?><a href="<?= Fmt::h(url('akquise/' . (int) $p['firma_id'])) ?>" style="text-decoration:underline"><?= Fmt::h((string) $p['firma']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= Fmt::h((string) $p['text']) ?></td><td class="akq-klein"><?= Fmt::h((string) $p['actor']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
