<?php
/* DEIN PC: Starten/Stoppen und Fortschritt (29.09.2026, Uwe: Ja zu F1–F4).
   Wird in „Neue Kunden finden“ eingebunden und alle 30 Sekunden über
   /app/akquise/steuerung neu geholt (nur dieser Teil, ohne Seite). */
require_once dirname(__DIR__) . '/src/AkquiseSteuerung.php';
require_once dirname(__DIR__) . '/src/BranchenStatistik.php';
$st = AkquiseSteuerung::stand();
$fs = AkquiseSteuerung::fortschritt();
$zl = AkquiseSteuerung::zuletzt(5);
$stPost = static fn(string $tat, string $wort, string $klasse = 'knopf') => '<form method="post" action="' . Fmt::h(url('akquise')) . '" style="display:inline;margin:0">' . Csrf::feld()
    . '<input type="hidden" name="tat" value="' . $tat . '"><button class="' . $klasse . '">' . Fmt::h($wort) . '</button></form>';
$stPrueft = $st['art'] === 'audit';
$stSucht = $st['art'] === 'recherche';
$pz = static fn(int $a, int $b): int => $b > 0 ? (int) min(100, floor($a / $b * 100)) : 0;
$zahl = static fn(int $x): string => number_format($x, 0, ',', '.');
$dauer = static function (?int $min): string {
    if ($min === null) { return ''; }
    if ($min < 60) { return 'noch etwa ' . max(1, $min) . ' Min.'; }
    return 'noch etwa ' . intdiv($min, 60) . ' Std.' . ($min % 60 >= 5 ? ' ' . ($min % 60) . ' Min.' : '');
};
$landName = static fn(string $l): string => ['IT' => 'Italien', 'DE' => 'Deutschland'][$l] ?? $l;
?>
<div class="akq-st-kopf"><h2>Dein PC</h2>
  <span class="akq-ampel <?= $st['pc_wach'] ? 'gruen' : 'grau' ?>"><i></i><?= $st['pc_wach'] ? 'an — meldet sich alle 5 Minuten' : ($st['pc_alter'] === null ? 'hat sich noch nie gemeldet' : 'aus oder im Schlafmodus (zuletzt vor ' . Fmt::h($st['pc_alter'] >= 120 ? round($st['pc_alter'] / 60) . ' Std.' : $st['pc_alter'] . ' Min.') . ')') ?></span></div>
<?php if ($st['stop']): ?><div class="hinweis schlecht" style="margin:8px 0 0">Die Notbremse ist gezogen — der PC tut nichts, bis du sie oben löst.</div><?php endif; ?>

<div class="akq-st-reihe">
  <div class="akq-st-teil">
    <h3>Websites prüfen</h3>
    <?php if ($stPrueft): $p = $pz($st['stand'], $st['ziel']); ?>
      <div class="akq-st-gross"><b><?= $p ?> %</b><span><?= (int) $st['stand'] ?> von <?= (int) $st['ziel'] ?> geprüft<?= $st['rest_min'] !== null ? ' · ' . Fmt::h($dauer($st['rest_min'])) : '' ?></span></div>
      <div class="akq-st-balken gross" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $p ?>" aria-label="Prüflauf"><span style="width:<?= $p ?>%"></span></div>
      <?php if ($st['text'] !== ''): ?><p class="akq-st-jetzt">gerade: <?= Fmt::h($st['text']) ?></p><?php endif; ?>
    <?php elseif (!$st['audit_an']): ?>
      <p><b>Aus.</b> Es wird nichts geprüft — auch nachts nicht.</p>
    <?php elseif ($st['jetzt']): ?>
      <p><b>Startet gleich:</b> in den nächsten fünf Minuten<?= $st['pc_wach'] ? '' : ', sobald dein PC an ist' ?>.</p>
    <?php else: ?>
      <p><b>An.</b> Nächster Lauf heute Nacht um 02:30 (bis zu 300 Websites).</p>
    <?php endif; ?>
    <div class="akq-st-knoepfe">
      <?php if ($st['audit_an'] && !$stPrueft && !$st['jetzt']): ?><?= $stPost('akq_pruefung_start', 'Jetzt starten', 'knopf haupt') ?><?php endif; ?>
      <?php if (!$st['audit_an']): ?><?= $stPost('akq_pruefung_start', 'Starten', 'knopf haupt') ?><?php endif; ?>
      <?php if ($st['audit_an']): ?><?= $stPost('akq_pruefung_stop', $stPrueft ? 'Stoppen' : 'Ausschalten (auch nachts nicht)') ?><?php endif; ?>
    </div>
  </div>
  <div class="akq-st-teil">
    <h3>Betriebe suchen</h3>
    <?php if ($stSucht || ($st['suche'] && $st['suche']['status'] === 'laeuft')): ?>
      <p><b>Sucht gerade:</b> <?= Fmt::h((string) ($st['suche']['gebiet'] ?? $st['text'])) ?><?= $st['suche'] ? ' (' . Fmt::h((string) $st['suche']['land']) . ')' : '' ?> — bisher <?= $zahl((int) ($st['suche']['neu'] ?? 0)) ?> neue Betriebe</p>
    <?php elseif (!$st['recherche_an']): ?>
      <p><b>Aus.</b> Suchaufträge bleiben liegen.</p>
    <?php elseif ($st['suche_wartend'] > 0): ?>
      <p><b>Wartet:</b> <?= Fmt::h((string) $st['suche']['gebiet']) ?><?= $st['suche_wartend'] > 1 ? ' und ' . ($st['suche_wartend'] - 1) . ' weitere' : '' ?> — startet in den nächsten fünf Minuten<?= $st['pc_wach'] ? '' : ', sobald dein PC an ist' ?>.</p>
    <?php else: ?>
      <p><b>Nichts zu tun.</b> Unten einen Ort eintragen und „Jetzt suchen“.</p>
    <?php endif; ?>
    <div class="akq-st-knoepfe">
      <?php if ($st['suche']): ?><?= $stPost('akq_suche_stop', 'Suche stoppen') ?><?php endif; ?>
      <?php if (!$st['recherche_an']): ?><?= $stPost('akq_suche_an', 'Einschalten', 'knopf haupt') ?><?php endif; ?>
    </div>
  </div>
</div>

<?php $gs = $fs['gesamt']; $gp = $pz($gs['geprueft'], $gs['n']); ?>
<div class="akq-st-reihe">
  <div class="akq-st-teil">
    <h3>Gesamtstand</h3>
    <div class="akq-st-gross"><b><?= $gs['n'] > 0 && $gp === 0 && $gs['geprueft'] > 0 ? '&lt; 1' : $gp ?> %</b><span><?= $zahl($gs['geprueft']) ?> von <?= $zahl($gs['n']) ?> Websites geprüft</span></div>
    <div class="akq-st-balken gross" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $gp ?>" aria-label="Gesamtstand"><span style="width:<?= max($gs['geprueft'] > 0 ? 1 : 0, $gp) ?>%"></span></div>
    <ul class="akq-st-liste">
      <?php foreach ($fs['gebiete'] as $gb): $x = $pz($gb['geprueft'], $gb['n']); ?>
        <li><span class="akq-st-name"><?= Fmt::h($gb['gebiet']) ?> <small><?= Fmt::h($landName($gb['land'])) ?></small></span>
          <span class="akq-st-mini"><span style="width:<?= max($gb['geprueft'] > 0 ? 2 : 0, $x) ?>%"></span></span>
          <span class="akq-st-zahl"><?= $zahl($gb['geprueft']) ?> / <?= $zahl($gb['n']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($gs['nicht'] > 0): ?><p class="akq-klein" style="margin-top:6px"><?= $zahl($gs['nicht']) ?> Websites waren nicht prüfbar (nicht erreichbar oder robots.txt).</p><?php endif; ?>
  </div>
  <div class="akq-st-teil">
    <h3>Weg zur Branchen-Seite <small class="akq-klein" style="font-weight:400">· ab <?= BranchenStatistik::MIN ?> geprüften Websites · <?= (int) $fs['seiten'] ?> fertig</small></h3>
    <?php $ng = AkquiseSteuerung::zuletztGerechnet(); ?>
    <p class="akq-klein" style="margin:0 0 4px">Wird nach jedem Prüflauf automatisch neu gerechnet<?= $ng ? ' · zuletzt ' . Fmt::h(date('d.m. H:i', strtotime((string) $ng['zeit']))) . ' (' . (int) $ng['seiten'] . ' Seiten)' : '' ?>.</p>
    <?php if (!$fs['gruppen']): ?><p>Noch keine Branche mit <?= BranchenStatistik::MIN ?> Websites in einem Ort.</p><?php endif; ?>
    <ul class="akq-st-liste">
      <?php foreach ($fs['gruppen'] as $gr): $x = $pz(min($gr['geprueft'], BranchenStatistik::MIN), BranchenStatistik::MIN); $ok = $gr['geprueft'] >= BranchenStatistik::MIN; ?>
        <li class="<?= $ok ? 'fertig' : '' ?>"><span class="akq-st-name"><?= Fmt::h(Akquise::branchenName($gr['branche'])) ?> <small><?= Fmt::h(mb_convert_case(mb_strtolower($gr['stadt']), MB_CASE_TITLE)) ?></small></span>
          <span class="akq-st-mini"><span style="width:<?= $x ?>%"></span></span>
          <span class="akq-st-zahl"><?php if ($gr['seite']): ?><a href="<?= Fmt::h('/siti-web/' . $gr['seite'] . '/') ?>" target="_blank" rel="noopener">✓ Seite</a><?php elseif ($ok): ?>✓ nachts fertig<?php else: ?><?= min($gr['geprueft'], BranchenStatistik::MIN) ?> / <?= BranchenStatistik::MIN ?><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="akq-st-teil" style="margin-top:12px">
  <h3>Zuletzt geprüft</h3>
  <?php if (!$zl): ?><p>Noch keine Website geprüft.</p><?php else: ?>
  <ul class="akq-st-zuletzt">
    <?php foreach ($zl as $z): ?>
      <li><span class="akq-klein"><?= Fmt::h(date('d.m. H:i', strtotime((string) $z['beendet_am']))) ?></span>
        <a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>"><?= Fmt::h((string) $z['name']) ?></a> <span class="akq-klein"><?= Fmt::h((string) ($z['domain'] ?? '')) ?></span>
        <span class="akq-st-zz"><?= $z['status'] === 'fertig' ? (int) $z['befunde'] . ' Befunde' . ($z['score'] !== null ? ' · Chance ' . (int) $z['score'] : '') : Fmt::h(Akquise::AUDIT_STATUS[(string) $z['status']] ?? (string) $z['status']) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <p class="akq-klein" style="margin-top:6px">Aktualisiert sich alle 30 Sekunden von selbst · Stand <?= date('H:i:s') ?></p>
</div>
