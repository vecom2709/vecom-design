<?php
/**
 * Stand der Claude-Aufträge (Recherche per Knopf, Content-Studio — 01.10.2026).
 * Erwartet: $auftraege (MkAuftrag::liste), $mkSeite ('recherche' | 'inhalte').
 * Lädt die Seite alle 30 Sekunden neu, solange ein Auftrag wartet oder läuft.
 */
$mkSeite = $mkSeite ?? 'recherche';
$mkOffen = false;
foreach ($auftraege as $a) { if (in_array($a['status'], ['wartet', 'laeuft'], true)) { $mkOffen = true; } }
$mkUhr = static fn(?string $t): string => $t ? (date('Y-m-d', strtotime($t)) === date('Y-m-d') ? 'heute ' : date('d.m. ', strtotime($t))) . date('H:i', strtotime($t)) : '';
$mkMin = static fn(?string $von, ?string $bis = null): int => $von ? max(0, (int) round(((($bis ? strtotime($bis) : time())) - strtotime($von)) / 60)) : 0;
$mkN = static fn(int $n, string $eins, string $viele): string => $n . ' ' . ($n === 1 ? $eins : $viele);
?>
<?php if ($auftraege): ?>
<div class="tabellenrahmen"><table class="mk-tab">
  <thead><tr><th>Auftrag</th><th>Stand</th><th>Ergebnis</th></tr></thead>
  <tbody>
  <?php foreach ($auftraege as $a): $istInhalt = ($a['art'] ?? 'recherche') === 'inhalte'; ?>
    <tr>
      <td class="mk-name"><?= Fmt::h(MkAuftrag::beschreibung($a)) ?><div class="mk-fein">angestoßen <?= Fmt::h($mkUhr($a['created_at'])) ?></div></td>
      <td style="white-space:nowrap">
        <?php if ($a['status'] === 'laeuft'): ?><span class="marke2 warnung mk-laeuft"><?= $istInhalt ? 'Claude schreibt' : 'Claude recherchiert' ?></span><div class="mk-fein">seit <?= $mkMin($a['gestartet_am']) ?> Min.</div>
        <?php elseif ($a['status'] === 'wartet'): ?><span class="marke2">wartet auf deinen PC</span>
          <form method="post" action="<?= Fmt::h(url($mkSeite)) ?>" style="margin:6px 0 0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="<?= $istInhalt ? 'inhalte_abbrechen' : 'recherche_abbrechen' ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="knopf klein">Abbrechen</button></form>
        <?php elseif ($a['status'] === 'fertig'): ?><span class="marke2 gut">fertig</span><div class="mk-fein"><?= Fmt::h($mkUhr($a['fertig_am'])) ?> · <?= $mkMin($a['gestartet_am'], $a['fertig_am']) ?> Min.</div>
        <?php else: ?><span class="marke2 <?= $a['status'] === 'fehler' ? 'schlecht' : '' ?>"><?= Fmt::h(MkAuftrag::STATUS[$a['status']] ?? $a['status']) ?></span><?php endif; ?>
      </td>
      <td><?php if ($a['status'] === 'fertig'): ?>
          <?php if ($istInhalt): ?><?= Fmt::h($mkN((int) ($a['inhalte'] ?? 0), 'Entwurf', 'Entwürfe')) ?><?php else: ?><?= Fmt::h($mkN((int) $a['zielgruppen'], 'Zielgruppe', 'Zielgruppen')) ?><?= (int) $a['zielgruppen'] > 0 ? ' (<a href="' . Fmt::h(url('zielgruppen')) . '">prüfen</a>)' : '' ?> · <?= Fmt::h($mkN((int) $a['funde'], 'neuer Fund', 'neue Funde')) ?><?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($a['ergebnis'])): ?><div class="mk-fein" style="max-width:60ch;white-space:pre-line"><?= Fmt::h((string) $a['ergebnis']) ?></div><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php if ($mkOffen): ?>
<script>
/* Solange ein Auftrag wartet oder läuft: alle 30 Sekunden neu laden — aber nicht, während jemand tippt oder auswählt. */
setTimeout(function () { var a = document.activeElement; if (!a || !/^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName)) { location.reload(); } }, 30000);
</script>
<?php endif; ?>
