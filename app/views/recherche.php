<?php
/**
 * Marketing · Recherche (Marketing-Studio Schritt 1 — 01.10.2026).
 * Erwartet: $f (Filter art/branche/status), $funde (MkZielgruppe::recherche),
 *           $auftraege (MkAuftrag::liste), $pc (AkquiseSteuerung::stand).
 * Recherche per Knopf (01.10.2026): „Recherche starten“ legt einen Auftrag an,
 * den der PC abholt — kein Umweg mehr über den Chat.
 */
require_once dirname(__DIR__) . '/src/MkAuftrag.php';
$branchen = MkKampagne::branchen();
$auftraege = $auftraege ?? [];
$pc = $pc ?? ['pc_wach' => false, 'pc_alter' => null];
$offen = false;
foreach ($auftraege as $a) { if (in_array($a['status'], ['wartet', 'laeuft'], true)) { $offen = true; } }
$uhr = static fn(?string $t): string => $t ? (date('Y-m-d', strtotime($t)) === date('Y-m-d') ? 'heute ' : date('d.m. ', strtotime($t))) . date('H:i', strtotime($t)) : '';
$minuten = static fn(?string $von, ?string $bis = null): int => $von ? max(0, (int) round(((($bis ? strtotime($bis) : time())) - strtotime($von)) / 60)) : 0;
$zurueck = http_build_query(array_filter($f));
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Recherche</h1>
    <div class="weg">Themen, Trends, häufige Fragen, Wettbewerb und Plattformen — von Claude recherchiert, jeweils mit Quelle · Wettbewerb nur als Anregung, nie zum Abschreiben</div>
  </div>
</div>

<section class="block mk-auftrag" id="auftraege" aria-labelledby="mk-auftrag-titel">
  <div class="mk-auftrag__kopf">
    <h2 id="mk-auftrag-titel">Claude recherchieren lassen</h2>
    <span class="mk-ampel <?= $pc['pc_wach'] ? 'gruen' : '' ?>"><i></i><?= $pc['pc_wach'] ? 'Dein PC ist an — holt Aufträge alle 5 Minuten ab' : ($pc['pc_alter'] === null ? 'Dein PC hat sich noch nie gemeldet' : 'Dein PC ist aus oder schläft — der Auftrag wartet, bis er wieder an ist') ?></span>
  </div>
  <form class="mk-filter" method="post" action="<?= Fmt::h(url('recherche')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_starten">
    <select name="branche" aria-label="Was recherchieren" style="width:auto"><option value="">Alle Branchen — neue Funde und fehlende Zielgruppen</option><?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?> — Zielgruppe und Funde</option><?php endforeach; ?></select>
    <select name="land" aria-label="Land" style="width:auto"><?php foreach (MkZielgruppe::LAENDER as $lk => $lw): ?><option value="<?= $lk ?>"><?= Fmt::h($lw) ?></option><?php endforeach; ?></select>
    <button class="knopf haupt">Recherche starten</button>
  </form>
  <p class="mk-fein" style="margin:0;max-width:90ch;line-height:1.55">Dein PC holt den Auftrag ab und lässt Claude über dein Claude-Abo im Netz suchen (nur Websuche und Webseiten lesen, kein API-Schlüssel). Dauer etwa 10–20 Minuten. Alles kommt als Entwurf mit Quellen — nichts gilt, bevor du es freigibst. Höchstens <?= MkAuftrag::PRO_TAG ?> Recherchen am Tag.</p>
  <?php if ($auftraege): ?>
  <div class="tabellenrahmen"><table class="mk-tab">
    <thead><tr><th>Auftrag</th><th>Stand</th><th>Ergebnis</th></tr></thead>
    <tbody>
    <?php foreach ($auftraege as $a): ?>
      <tr>
        <td class="mk-name"><?= Fmt::h(MkAuftrag::beschreibung($a)) ?><div class="mk-fein">angestoßen <?= Fmt::h($uhr($a['created_at'])) ?></div></td>
        <td style="white-space:nowrap">
          <?php if ($a['status'] === 'laeuft'): ?><span class="marke2 warnung mk-laeuft">Claude recherchiert</span><div class="mk-fein">seit <?= $minuten($a['gestartet_am']) ?> Min.</div>
          <?php elseif ($a['status'] === 'wartet'): ?><span class="marke2">wartet auf deinen PC</span>
            <form method="post" action="<?= Fmt::h(url('recherche')) ?>" style="margin:6px 0 0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_abbrechen"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="knopf klein">Abbrechen</button></form>
          <?php elseif ($a['status'] === 'fertig'): ?><span class="marke2 gut">fertig</span><div class="mk-fein"><?= Fmt::h($uhr($a['fertig_am'])) ?> · <?= $minuten($a['gestartet_am'], $a['fertig_am']) ?> Min.</div>
          <?php else: ?><span class="marke2 <?= $a['status'] === 'fehler' ? 'schlecht' : '' ?>"><?= Fmt::h(MkAuftrag::STATUS[$a['status']] ?? $a['status']) ?></span><?php endif; ?>
        </td>
        <td><?php if ($a['status'] === 'fertig'): ?><?= (int) $a['zielgruppen'] ?> <?= (int) $a['zielgruppen'] === 1 ? 'Zielgruppe' : 'Zielgruppen' ?><?= (int) $a['zielgruppen'] > 0 ? ' (<a href="' . Fmt::h(url('zielgruppen')) . '">prüfen</a>)' : '' ?> · <?= (int) $a['funde'] ?> <?= (int) $a['funde'] === 1 ? 'neuer Fund' : 'neue Funde' ?><?php endif; ?>
          <?php if (!empty($a['ergebnis'])): ?><div class="mk-fein" style="max-width:60ch;white-space:pre-line"><?= Fmt::h((string) $a['ergebnis']) ?></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if ($offen): ?>
<script>
/* Solange ein Auftrag wartet oder läuft: alle 30 Sekunden neu laden — aber nicht, während jemand tippt oder auswählt. */
setTimeout(function () { var a = document.activeElement; if (!a || !/^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName)) { location.reload(); } }, 30000);
</script>
<?php endif; ?>

<form class="mk-filter" method="get" action="<?= Fmt::h(url('recherche')) ?>" aria-label="Funde filtern">
  <select name="art" aria-label="Art" style="width:auto"><option value="">Alle Arten</option><?php foreach (MkZielgruppe::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"<?= $f['art'] === $ak ? ' selected' : '' ?>><?= Fmt::h($aw) ?></option><?php endforeach; ?></select>
  <select name="branche" aria-label="Branche" style="width:auto"><option value="">Alle Branchen</option><?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?></option><?php endforeach; ?></select>
  <select name="status" aria-label="Status" style="width:auto"><option value="">Offen und gemerkt</option><?php foreach (MkZielgruppe::STATUS_RECHERCHE as $sk => $sw): ?><option value="<?= $sk ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select>
  <button class="knopf">Filtern</button>
</form>

<?php if (!$funde): ?>
  <div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Funde<?= array_filter($f) ? ' für diesen Filter' : '' ?>. Oben „Recherche starten“ drücken — Claude sucht im Netz, prüft die Quellen und legt die Funde hier ab.</p></div>
<?php else: ?>
  <div class="mk-recherche">
    <?php foreach ($funde as $fu): ?>
      <article class="block" style="margin-bottom:10px">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
          <span class="marke2"><?= Fmt::h(MkZielgruppe::ARTEN[$fu['art']] ?? $fu['art']) ?></span>
          <?php if ($fu['branche'] !== ''): ?><span class="marke2"><?= Fmt::h($branchen[$fu['branche']] ?? $fu['branche']) ?><?= $fu['land'] !== '' ? ' · ' . Fmt::h($fu['land']) : '' ?></span><?php endif; ?>
          <span class="mk-fein">Relevanz <?= str_repeat('●', (int) $fu['relevanz']) . str_repeat('○', 5 - (int) $fu['relevanz']) ?> · <?= Fmt::h(date('d.m.Y', strtotime((string) $fu['created_at']))) ?></span>
          <?php if ($fu['status'] !== 'neu'): ?><span class="marke2 <?= $fu['status'] === 'gemerkt' ? 'gut' : '' ?>"><?= Fmt::h(MkZielgruppe::STATUS_RECHERCHE[$fu['status']] ?? $fu['status']) ?></span><?php endif; ?>
        </div>
        <h2 style="margin:0 0 6px;font-size:15px"><?= Fmt::h($fu['titel']) ?></h2>
        <p style="margin:0 0 8px;max-width:85ch;line-height:1.6;white-space:pre-line"><?= Fmt::h($fu['text']) ?></p>
        <p class="mk-fein" style="margin:0 0 10px">Quelle<?= count($fu['q']) > 1 ? 'n' : '' ?>:
          <?php foreach ($fu['q'] as $i => $q): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= Fmt::h((string) $q['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Fmt::h((string) $q['titel']) ?></a><?php endforeach; ?></p>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach (['gemerkt' => 'Merken', 'verwendet' => 'Als verwendet markieren', 'verworfen' => 'Verwerfen'] as $st => $wort): if ($fu['status'] === $st) { continue; } ?>
            <form method="post" action="<?= Fmt::h(url('recherche')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_status"><input type="hidden" name="id" value="<?= (int) $fu['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>"><input type="hidden" name="zurueck" value="<?= Fmt::h($zurueck) ?>"><button class="knopf klein"><?= Fmt::h($wort) ?></button></form>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
