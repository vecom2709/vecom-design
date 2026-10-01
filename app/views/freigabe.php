<?php
/**
 * Marketing · Freigeben (Marketing-Studio 6 — 01.10.2026, Uwe: „ja“ zu U3:
 * „da, wo es mehrere Schritte braucht, sollen es weniger sein“).
 *
 * Ein Stück nach dem anderen, je Land: Vorschau, deutsche Fassung, ein Satz,
 * was „Ja“ tut — dann Ja (freigeben, Bild wählen, auf den nächsten freien
 * Sendeplatz legen), Nein (verwerfen) oder Später. Tasten J, N, S.
 *
 * Erwartet: $land, $x (nächster Entwurf oder null), $rest, $offen, $zg, $medien, $bildLaeuft, $geplant.
 */
require_once dirname(__DIR__) . '/src/MkLand.php';
require_once dirname(__DIR__) . '/src/MkVeroeffentlichen.php';
$land = $land ?? 'IT';
$offen = $offen ?? MkLand::offen();
$medien = $medien ?? [];
$geplant = $geplant ?? [];
$name = MkLand::name($land);
$andere = MkLand::andere($land);
$plName = static fn(string $p): string => trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$p] ?? $p)) ?? '');
$tag = static fn(string $t): string => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', strtotime($t))] . ' ' . date('d.m. H:i', strtotime($t));
$knopf = static fn(string $tat, string $wort, string $klasse, string $taste, int $id): string => '<form method="post" action="' . Fmt::h(url('freigabe')) . '" style="margin:0">'
    . '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '"><input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . $id . '">'
    . '<button class="' . $klasse . '" data-taste="' . $taste . '">' . Fmt::h($wort) . ' <kbd>' . strtoupper($taste) . '</kbd></button></form>';
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Freigeben</h1>
    <div class="weg">ein Stück nach dem anderen · Ja gibt frei und plant es auf den nächsten freien Abend (<?= MkVeroeffentlichen::SENDEZEIT ?>) · Nein verwirft · Später legt es nach hinten</div>
  </div>
</div>

<?php $mkLand = $land; $mkLandSeite = 'freigabe'; $mkLandOffen = $offen; require __DIR__ . '/mk_land.php'; ?>

<?php if ($x === null): ?>
  <div class="block">
    <h2>Alles durchgesehen in <?= Fmt::h($name) ?></h2>
    <p style="margin:0 0 12px;max-width:70ch;line-height:1.6">Kein Entwurf wartet hier. Neue entstehen, wenn du bei einer freigegebenen Zielgruppe „Kampagne starten“ drückst.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ((int) ($offen[$andere] ?? 0) > 0): ?><a class="knopf haupt" href="<?= Fmt::h(url('freigabe') . '?land=' . $andere) ?>">In <?= Fmt::h(MkLand::name($andere)) ?> warten Entwürfe</a><?php endif; ?>
      <a class="knopf" href="<?= Fmt::h(url('zielgruppen') . '?land=' . $land) ?>">Zu den Zielgruppen</a>
    </div>
  </div>
<?php else:
  $f = $x['f'];
  $bild = null;
  foreach ($medien as $m) { if ($m['status'] === 'gewaehlt') { $bild = $m; break; } }
  if ($bild === null) { foreach ($medien as $m) { if ($m['status'] === 'neu') { $bild = $m; break; } } }
?>
  <section class="mk-stapel" aria-labelledby="mk-stapel-titel">
    <div class="mk-stapel__kopf">
      <span class="mk-fein">Noch <b><?= (int) $rest ?></b> <?= (int) $rest === 1 ? 'Entwurf' : 'Entwürfe' ?> in <?= Fmt::h($name) ?></span>
      <span class="marke2"><?= Fmt::h($plName((string) $x['plattform'])) ?></span>
      <span class="marke2 <?= $x['art'] === 'bezahlt' ? 'warnung' : '' ?>"><?= Fmt::h(MkInhalt::ARTEN[$x['art']] ?? $x['art']) ?></span>
      <span class="mk-fein"><?= Fmt::h(MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) ?><?= $zg ? ' · für ' . Fmt::h((string) $zg['titel']) : '' ?></span>
    </div>
    <h2 id="mk-stapel-titel" class="mk-stapel__titel"><?= Fmt::h((string) $x['titel']) ?></h2>
    <div class="mk-zwei">
      <div>
        <div class="mk-vorschau">
          <div class="mk-vorschau__kopf"><span class="mk-vorschau__logo"></span><div><div class="mk-vorschau__name">Vecom Design</div><div class="mk-vorschau__zweit"><?= Fmt::h($plName((string) $x['plattform'])) ?><?= $x['art'] === 'bezahlt' ? ' · Gesponsert' : '' ?></div></div></div>
          <?php if ($bild): ?>
            <?php if ($bild['art'] === 'video'): ?><video class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $bild['id'])) ?>" controls preload="metadata" playsinline></video>
            <?php else: ?><img class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $bild['id'])) ?>" alt="<?= Fmt::h('Bild zu „' . $x['titel'] . '“') ?>"><?php endif; ?>
          <?php elseif ($x['format'] !== 'google_anzeige'): ?>
            <div class="mk-vorschau__bild"><?= $bildLaeuft ? 'Das Bild entsteht gerade über Kie.ai …' : Fmt::h($x['bildidee'] ? 'Bildidee: ' . $x['bildidee'] : 'noch kein Bild') ?></div>
          <?php endif; ?>
          <div class="mk-vorschau__text"><?= Fmt::h(MkInhalt::kopiertext($x)) ?></div>
          <?php if (!empty($f['folien'])): ?>
            <div class="mk-folien"><?php foreach ($f['folien'] as $i => $fo): ?><div class="mk-folie"><i><?= $i + 1 ?>/<?= count($f['folien']) ?></i><b><?= Fmt::h((string) $fo['titel']) ?></b><span><?= Fmt::h((string) $fo['text']) ?></span></div><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (!empty($f['szenen'])): ?>
            <ol class="mk-fein" style="margin:10px 0 0;padding-left:18px;line-height:1.5"><?php foreach ($f['szenen'] as $sz): ?><li><?= (int) $sz['sekunden'] ?> s · <?= Fmt::h((string) $sz['bild']) ?><?= $sz['einblendung'] !== '' ? ' · „' . Fmt::h((string) $sz['einblendung']) . '“' : '' ?></li><?php endforeach; ?></ol>
          <?php endif; ?>
        </div>
      </div>
      <div class="mk-stapel__rechts">
        <?php if ($x['sprache'] !== 'de' && !empty($x['uebersetzung'])): ?>
          <div class="mk-uebersetzung" style="margin-top:0"><h3>Auf Deutsch — nur zum Lesen</h3><?= Fmt::h((string) $x['uebersetzung']) ?></div>
        <?php endif; ?>
        <?php if ($x['begruendung']): ?><p class="mk-fein" style="margin:0;line-height:1.55"><b>Warum das trägt:</b> <?= Fmt::h((string) $x['begruendung']) ?></p><?php endif; ?>
        <div class="mk-stapel__entscheid">
          <p style="margin:0;line-height:1.5"><?= Fmt::h(MkVeroeffentlichen::wasPassiert($x)) ?></p>
          <div class="mk-stapel__knoepfe">
            <?= $knopf('stapel_ja', 'Ja — freigeben', 'knopf haupt', 'j', (int) $x['id']) ?>
            <?= $knopf('stapel_nein', 'Nein — verwerfen', 'knopf', 'n', (int) $x['id']) ?>
            <?= $knopf('stapel_spaeter', 'Später', 'knopf', 's', (int) $x['id']) ?>
          </div>
          <a class="mk-fein" href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>#bearbeiten">Erst etwas ändern? Stück öffnen →</a>
        </div>
      </div>
    </div>
  </section>
  <script>
  /* J = Ja, N = Nein, S = Später — nicht, während jemand tippt. */
  document.addEventListener('keydown', function (e) {
    var a = document.activeElement; if (e.ctrlKey || e.metaKey || e.altKey || (a && /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName))) { return; }
    var k = document.querySelector('[data-taste="' + e.key.toLowerCase() + '"]'); if (k) { e.preventDefault(); k.click(); }
  });
  </script>
<?php endif; ?>

<?php if ($geplant): ?>
<div class="block" style="margin-top:16px">
  <h2>Eingeplant <span class="mehr">geht von selbst raus · ändern oder aufheben am Stück</span></h2>
  <ul class="mk-zeilen">
    <?php foreach ($geplant as $g): ?><li><span><?= MkLand::marke((string) $g['land'], false) ?> <a href="<?= Fmt::h(url('inhalte/' . (int) $g['id'])) ?>#posten"><?= Fmt::h((string) $g['titel']) ?></a></span><span class="mk-fein" style="white-space:nowrap"><?= Fmt::h($plName((string) $g['plattform'])) ?> · <?= Fmt::h($tag((string) $g['geplant_am'])) ?></span></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
