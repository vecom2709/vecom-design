<div class="kopf"><h1>Projekte</h1><div class="rechts">
<form class="leiste"><select name="status"><option value="">alle Status</option>
<?php foreach (Status::PROJEKT as $w => $t): ?><option value="<?= $w ?>" <?= $st === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?>
</select><button class="knopf">Filtern</button></form></div></div>
<?php require_once dirname(__DIR__) . '/src/Bausperre.php'; $pjAlle = Bausperre::alleGestoppt(); /* AutoBuild Phase 4: globaler Not-Aus */ ?>
<div class="block" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between<?= $pjAlle ? ';border-color:var(--rot, #c0392b)' : '' ?>">
  <span style="font-size:14px"><?= $pjAlle ? '<b>⛔ Alle automatischen Builds sind gestoppt.</b>' : 'Automatische Builds: erlaubt (je Projekt nur nach Angebotsannahme + Anzahlung).' ?></span>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $pjAlle ? 'bau_weiter_alle' : 'bau_stopp_alle' ?>">
    <?php if (!$pjAlle || Auth::istAdmin()): ?><button class="knopf klein"><?= $pjAlle ? 'Alle Builds wieder erlauben' : '⛔ Alle automatischen Builds stoppen' ?></button><?php endif; ?></form>
</div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Projekt</th><th>Kunde</th><th>Projektstatus</th><th>Website</th><th>Fortschritt</th><th>Deadline</th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="6"><div class="leer">Noch keine Projekte. Sie entstehen automatisch, sobald eine Zahlung bestätigt wird.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $p): ?><tr>
<td><a href="<?= Fmt::h(url('projekte/' . $p['id'])) ?>"><strong><?= Fmt::h($p['name']) ?></strong></a></td>
<td><?= Fmt::h($p['kunde']) ?></td>
<td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td>
<td><span class="marke2 <?= Status::ton((string) $p['website_status']) ?>"><?= Fmt::h($p['website_status'] ? Status::label(Status::WEBSITE, $p['website_status']) : 'keine') ?></span></td>
<td><div class="balken"><i style="width:<?= (int) $p['progress'] ?>%"></i></div></td>
<td><?= Fmt::h(Fmt::datum($p['deadline'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
