<?php
/**
 * Marketing · eine Zielgruppe (Marketing-Studio Schritt 1 — 01.10.2026).
 * Erwartet: $z (MkZielgruppe::laden), $daten (Datengrundlage), $funde (Recherche dieser Branche).
 */
$p = $z['p'];
$branchen = MkKampagne::branchen();
$n = static fn(int $x): string => number_format($x, 0, ',', '.');
$datum = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '—';
$liste = static function (array $eintraege): void {
    if (!$eintraege) { echo '<p class="leise" style="margin:0">—</p>'; return; }
    echo '<ul class="mk-liste">';
    foreach ($eintraege as $e) { echo '<li>' . Fmt::h((string) $e) . '</li>'; }
    echo '</ul>';
};
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1><?= Fmt::h($p['titel'] ?? $z['titel']) ?>
      <?php if ($z['status'] === 'freigegeben'): ?><span class="marke2 gut" style="vertical-align:4px">freigegeben</span><?php else: ?><span class="marke2 warnung" style="vertical-align:4px"><?= $z['v'] ? 'Überarbeitung' : 'Entwurf' ?></span><?php endif; ?></h1>
    <div class="weg"><?= Fmt::h(($branchen[$z['branche']] ?? $z['branche']) . ' · ' . (MkZielgruppe::LAENDER[$z['land']] ?? $z['land'])) ?> · Stand <?= Fmt::h($datum($z['updated_at'])) ?><?= $z['freigegeben_am'] ? ' · freigegeben am ' . Fmt::h($datum($z['freigegeben_am'])) : '' ?> · von Claude recherchiert</div>
  </div>
  <a class="knopf" href="<?= Fmt::h(url('zielgruppen')) ?>">‹ Alle Zielgruppen</a>
</div>

<?php if ($z['status'] !== 'freigegeben'): ?>
<div class="block" style="border-color:var(--linie2)">
  <h2>Prüfen und freigeben</h2>
  <p style="margin:0 0 12px;max-width:70ch;line-height:1.6"><?= $z['v'] ? 'Claude hat die freigegebene Fassung überarbeitet. Bis du freigibst, gilt die alte weiter.' : 'Ein Entwurf. Content und Kampagnen stützen sich erst darauf, wenn du ihn freigibst.' ?> Prüf vor allem, ob die Probleme zu dem passen, was du von Kunden hörst, und ob die Quellen tragen.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="zielgruppe_freigeben"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><button class="knopf haupt">Zielgruppe freigeben</button></form>
    <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="zielgruppe_verwerfen"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><button class="knopf"><?= $z['v'] ? 'Überarbeitung verwerfen, alte Fassung behalten' : 'Entwurf verwerfen' ?></button></form>
  </div>
</div>
<?php endif; ?>

<div class="block">
  <h2>Kurz gesagt</h2>
  <p style="margin:0;max-width:75ch;line-height:1.65"><?= Fmt::h((string) ($p['kurz'] ?? '')) ?></p>
  <?php if (!empty($p['ansprache'])): ?><p style="margin:12px 0 0;max-width:75ch;line-height:1.65"><b>Ansprache:</b> <?= Fmt::h((string) $p['ansprache']) ?></p><?php endif; ?>
</div>

<div class="block">
  <h2>Datengrundlage <span class="mehr">selbst gemessen, nicht geschätzt</span></h2>
  <div class="kacheln" style="margin:0 0 12px">
    <div class="kachel"><span>Betriebe in der Akquise</span><b><?= $n($daten['firmen']) ?></b></div>
    <div class="kachel"><span>Websites geprüft</span><b><?= $n($daten['geprueft']) ?></b></div>
    <div class="kachel"><span>ohne Website</span><b><?= $n($daten['ohne_website']) ?></b></div>
    <div class="kachel"><span>Ø Handlungsbedarf (Score)</span><b><?= $daten['score'] !== null ? (int) $daten['score'] : '—' ?></b><span class="leise">0 gut · 100 dringend</span></div>
    <div class="kachel"><span>Kampagnen (12 Monate)</span><b><?= $n((int) $daten['kampagnen']['anzahl']) ?></b><span class="leise"><?= $n((int) $daten['kampagnen']['leads']) ?> Leads · <?= $n((int) $daten['kampagnen']['kunden']) ?> Kunden</span></div>
  </div>
  <?php if ($daten['befunde']): ?>
    <h3 class="leise" style="margin:6px 0 8px;font-size:12px">Häufigste belegte Befunde auf ihren Websites</h3>
    <div class="balkenliste">
      <?php foreach ($daten['befunde'] as $b): ?>
        <div class="bl__zeile" title="<?= Fmt::h($b['titel'] . ': ' . $b['n'] . ' Betriebe') ?>"><span class="bl__wort"><?= Fmt::h($b['titel']) ?></span><span class="bl__spur"><i style="width:<?= min(100, round($b['anteil'])) ?>%"></i></span><b class="bl__zahl"><?= number_format($b['anteil'], 0, ',', '.') ?> %</b></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="leise" style="margin:0">Noch keine geprüften Websites dieser Branche.</p><?php endif; ?>
</div>

<div class="mk-zwei">
  <?php foreach (MkZielgruppe::LISTEN as $k => [$ueberschrift]): ?>
    <div class="block"><h2><?= Fmt::h($ueberschrift) ?></h2><?php $liste((array) ($p[$k] ?? [])); ?></div>
  <?php endforeach; ?>
</div>

<div class="block">
  <h2>Bezahlte Werbung</h2>
  <?php $bz = (array) ($p['bezahlt'] ?? []); ?>
  <?php foreach (MkZielgruppe::BEZAHLT as $bk => $bw): if (empty($bz[$bk])) { continue; } ?>
    <p style="margin:0 0 10px;max-width:80ch;line-height:1.6"><b><?= Fmt::h($bw) ?>:</b> <?= Fmt::h(is_array($bz[$bk]) ? implode(' · ', $bz[$bk]) : (string) $bz[$bk]) ?></p>
  <?php endforeach; ?>
</div>

<div class="block">
  <h2>Quellen <span class="mehr"><?= count((array) ($p['quellen'] ?? [])) ?></span></h2>
  <ol style="margin:0;padding-left:20px;line-height:1.7;font-size:14px">
    <?php foreach ((array) ($p['quellen'] ?? []) as $q): ?>
      <li><a href="<?= Fmt::h((string) $q['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Fmt::h((string) $q['titel']) ?></a><?= !empty($q['datum']) ? ' <span class="leise" style="display:inline">· ' . Fmt::h((string) $q['datum']) . '</span>' : '' ?></li>
    <?php endforeach; ?>
  </ol>
</div>

<?php if ($funde): ?>
<div class="block">
  <h2>Recherche zu dieser Branche <a class="mehr" href="<?= Fmt::h(url('recherche') . '?branche=' . rawurlencode((string) $z['branche'])) ?>">alle →</a></h2>
  <ul class="mk-liste">
    <?php foreach ($funde as $fu): ?><li><b><?= Fmt::h(MkZielgruppe::ARTEN[$fu['art']] ?? $fu['art']) ?>:</b> <?= Fmt::h($fu['titel']) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
