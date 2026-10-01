<?php
/**
 * Länderschalter Italien | Deutschland (Marketing-Studio 5 — 01.10.2026,
 * Uwe: „Wichtig, dass Deutsch und Italien klar getrennt sind“).
 * Erwartet: $mkLand ('IT'|'DE'), $mkLandSeite (Route), $mkLandOffen (MkLand::offen).
 * Die Zahl am Land sind die Entwürfe, die dort auf Prüfung warten.
 */
require_once dirname(__DIR__) . '/src/MkLand.php';
$mkLandOffen = $mkLandOffen ?? ['IT' => 0, 'DE' => 0];
?>
<nav class="mk-laender" aria-label="Land wählen">
  <?php foreach (MkLand::NAMEN as $mkL => $mkLName): $mkN = (int) ($mkLandOffen[$mkL] ?? 0); ?>
    <a href="<?= Fmt::h(url($mkLandSeite) . '?land=' . $mkL) ?>"<?= $mkLand === $mkL ? ' aria-current="page"' : '' ?>>
      <i class="mk-flagge mk-flagge--<?= strtolower($mkL) ?>" aria-hidden="true"></i><?= Fmt::h($mkLName) ?>
      <?php if ($mkN > 0): ?><b title="<?= $mkN ?> Entwürfe warten auf deine Prüfung"><?= $mkN ?><span class="mk-sr"> zu prüfen</span></b><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
