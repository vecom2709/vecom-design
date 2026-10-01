<?php
/**
 * Marketing · Zielgruppen (Marketing-Studio Schritt 1 — 01.10.2026).
 * Erwartet: $liste (MkZielgruppe::alle), $fehlend (Branchen ohne Profil, nach geprüften Betrieben).
 */
$branchen = MkKampagne::branchen();
$datum = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '—';
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Zielgruppen</h1>
    <div class="weg">je Branche und Land · Datengrundlage aus deinen geprüften Betrieben, Recherche mit Quellen von Claude · gilt erst nach deiner Freigabe</div>
  </div>
</div>

<?php if ($liste): ?>
<div class="mk-besten" style="margin-bottom:16px">
  <?php foreach ($liste as $z): ?>
    <a class="mk-best" href="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="text-decoration:none;color:inherit">
      <h3><?= Fmt::h(($branchen[$z['branche']] ?? $z['branche']) . ' · ' . (MkZielgruppe::LAENDER[$z['land']] ?? $z['land'])) ?></h3>
      <b class="mk-best__name"><?= Fmt::h($z['titel']) ?></b>
      <span class="mk-best__zahl">Stand <?= Fmt::h($datum($z['updated_at'])) ?></span>
      <?php if ($z['status'] === 'freigegeben'): ?><span class="marke2 gut mk-best__marke">freigegeben</span>
      <?php elseif ((int) $z['ueberarbeitung'] === 1): ?><span class="marke2 warnung mk-best__marke">Überarbeitung prüfen</span>
      <?php else: ?><span class="marke2 warnung mk-best__marke">Entwurf prüfen</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Zielgruppe. Unter <a href="<?= Fmt::h(url('recherche#auftraege')) ?>">Recherche</a> „Recherche starten“ drücken oder unten bei einer Branche auf „Recherchieren“: Dein PC lässt Claude über dein Claude-Abo die Zahlen deiner geprüften Betriebe lesen, im Netz nach Quellen suchen und je Branche einen Entwurf hierher liefern. Nichts gilt, bevor du es freigibst.</p></div>
<?php endif; ?>

<?php if ($fehlend): ?>
<div class="block">
  <h2>Noch ohne Profil <span class="mehr">nach Zahl der Betriebe in der Akquise</span></h2>
  <div class="tabellenrahmen"><table class="mk-tab">
    <thead><tr><th>Branche</th><th>Land</th><th class="num">Betriebe</th><th><span class="mk-sr">Aktion</span></th></tr></thead>
    <tbody>
      <?php foreach ($fehlend as $fz): ?>
        <tr><td class="mk-name"><?= Fmt::h($branchen[$fz['branche']] ?? $fz['branche']) ?></td><td><?= Fmt::h(MkZielgruppe::LAENDER[$fz['land']] ?? $fz['land']) ?></td><td class="num"><?= number_format((int) $fz['firmen'], 0, ',', '.') ?></td>
          <td style="text-align:right"><form method="post" action="<?= Fmt::h(url('recherche')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_starten"><input type="hidden" name="branche" value="<?= Fmt::h($fz['branche']) ?>"><input type="hidden" name="land" value="<?= Fmt::h($fz['land']) ?>"><button class="knopf klein">Recherchieren</button></form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
