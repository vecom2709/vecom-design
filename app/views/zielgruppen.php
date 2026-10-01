<?php
/**
 * Marketing · Zielgruppen & Recherche (Marketing-Studio 1 und 5 — 01.10.2026).
 *
 * Uwe: „Zielgruppe und Recherche sollen eins werden … für die Verwaltung auf
 * Deutsch anzeigen, dass wir es lesen können … wichtig, dass Deutsch und
 * Italien klar getrennt sind.“ Deshalb eine Seite je Land: oben der Schalter,
 * darunter der Weg in drei Schritten, die Zielgruppen, der Knopf für Claude
 * und die Funde mit Quellen.
 *
 * Erwartet: $land, $liste (MkZielgruppe::alle($land)), $fehlend, $f (Filter), $funde,
 *           $offen (MkLand::offen), $auftraege, $ohneDeutsch, $pc.
 */
require_once dirname(__DIR__) . '/src/MkAuftrag.php';
require_once dirname(__DIR__) . '/src/MkLand.php';
$branchen = MkKampagne::branchen();
$land = $land ?? 'IT';
$f = $f ?? ['art' => '', 'branche' => '', 'status' => ''];
$funde = $funde ?? [];
$auftraege = $auftraege ?? [];
$offen = $offen ?? MkLand::offen();
$ohneDeutsch = (int) ($ohneDeutsch ?? 0);
$pc = $pc ?? ['pc_wach' => false, 'pc_alter' => null];
$name = MkLand::name($land);
$andere = MkLand::andere($land);
$datum = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '—';
$zurueck = http_build_query(array_filter($f + ['land' => $land]));

$zahlEntwurf = count(array_filter($liste, static fn($z) => $z['status'] !== 'freigegeben' || (int) $z['ueberarbeitung'] === 1));
$zahlFrei = count(array_filter($liste, static fn($z) => $z['status'] === 'freigegeben' || (int) $z['ueberarbeitung'] === 1));
$laeuft = (bool) array_filter($auftraege, static fn($a) => ($a['art'] ?? 'recherche') === 'recherche' && in_array($a['status'], ['wartet', 'laeuft'], true));
$dran = $liste === [] ? 1 : ($zahlEntwurf > 0 ? 2 : 3);
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Zielgruppen &amp; Recherche</h1>
    <div class="weg">Claude recherchiert im Netz und baut je Branche eine Zielgruppe mit Quellen · du prüfst und gibst frei · danach schreibt Claude Inhalte und Anzeigen dafür</div>
  </div>
</div>

<?php $mkLand = $land; $mkLandSeite = 'zielgruppen'; $mkLandOffen = $offen; require __DIR__ . '/mk_land.php'; ?>

<ol class="mk-schritte" aria-label="So läuft es in <?= Fmt::h($name) ?>">
  <li class="<?= $dran === 1 ? 'dran' : '' ?>"><b>Recherchieren</b>
    <?= $laeuft ? 'Claude recherchiert gerade.' : ($liste === [] ? 'Noch keine Zielgruppe in ' . Fmt::h($name) . '.' : count($liste) . ' ' . (count($liste) === 1 ? 'Zielgruppe' : 'Zielgruppen') . ' in ' . Fmt::h($name) . '.') ?>
    <a href="#auftraege">Recherche starten</a></li>
  <li class="<?= $dran === 2 ? 'dran' : '' ?>"><b>Prüfen und freigeben</b>
    <?= $zahlEntwurf > 0 ? $zahlEntwurf . ' ' . ($zahlEntwurf === 1 ? 'Entwurf wartet' : 'Entwürfe warten') . ' auf dich. <a href="#zielgruppen">Ansehen</a>' : 'Nichts offen.' ?></li>
  <li class="<?= $dran === 3 ? 'dran' : '' ?>"><b>Inhalte schreiben lassen</b>
    <?= $zahlFrei > 0 ? $zahlFrei . ' freigegeben — daraus schreibt Claude Beiträge und Anzeigen. <a href="' . Fmt::h(url('inhalte') . '?land=' . $land) . '#auftraege">Zu den Inhalten</a>' : 'Geht, sobald eine Zielgruppe freigegeben ist.' ?></li>
</ol>

<section class="block" id="zielgruppen" aria-labelledby="mk-zg-titel">
  <h2 id="mk-zg-titel">Zielgruppen in <?= Fmt::h($name) ?> <span class="mehr"><?= count($liste) ?></span></h2>
  <?php if ($liste): ?>
  <div class="mk-zg-karten">
    <?php foreach ($liste as $z): $zp = json_decode((string) ($z['profil'] ?? ''), true) ?: []; ?>
      <a class="mk-best" href="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="text-decoration:none;color:inherit">
        <h3><?= Fmt::h($branchen[$z['branche']] ?? $z['branche']) ?></h3>
        <b class="mk-best__name"><?= Fmt::h($z['titel']) ?></b>
        <span class="mk-best__zahl">Stand <?= Fmt::h($datum($z['updated_at'])) ?></span>
        <?php if ($land === 'IT' && $zp && MkZielgruppe::ohneDeutsch($zp + ['land' => 'IT'])): ?><span class="mk-best__zweit">ohne deutsche Übersetzung</span><?php endif; ?>
        <?php if ($z['status'] === 'freigegeben'): ?><span class="marke2 gut mk-best__marke">freigegeben</span>
        <?php elseif ((int) $z['ueberarbeitung'] === 1): ?><span class="marke2 warnung mk-best__marke">Überarbeitung prüfen</span>
        <?php else: ?><span class="marke2 warnung mk-best__marke">Entwurf prüfen</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p style="margin:0;max-width:70ch;line-height:1.6">Noch keine Zielgruppe in <?= Fmt::h($name) ?>. Unten „Recherche starten“ drücken: Dein PC lässt Claude über dein Claude-Abo die Zahlen deiner geprüften Betriebe lesen, im Netz nach Quellen suchen und je Branche einen Entwurf hierher liefern. Nichts gilt, bevor du es freigibst.</p>
  <?php endif; ?>
</section>

<section class="block mk-auftrag" id="auftraege" aria-labelledby="mk-auftrag-titel">
  <div class="mk-auftrag__kopf">
    <h2 id="mk-auftrag-titel">Claude recherchieren lassen</h2>
    <span class="mk-ampel <?= $pc['pc_wach'] ? 'gruen' : '' ?>"><i></i><?= $pc['pc_wach'] ? 'Dein PC ist an — holt Aufträge alle 5 Minuten ab' : ($pc['pc_alter'] === null ? 'Dein PC hat sich noch nie gemeldet' : 'Dein PC ist aus oder schläft — der Auftrag wartet, bis er wieder an ist') ?></span>
  </div>
  <form class="mk-filter" method="post" action="<?= Fmt::h(url('zielgruppen')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_starten"><input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
    <select name="branche" aria-label="Was recherchieren" style="width:auto"><option value="">Die wichtigsten Branchen ohne Zielgruppe (<?= MkAuftrag::FEHLENDE_JE_LAUF ?> je Lauf) und neue Funde</option><?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?> — Zielgruppe und Funde</option><?php endforeach; ?></select>
    <label class="mk-haken"><input type="checkbox" name="beide" value="1" checked> auch in <?= Fmt::h(MkLand::name($andere)) ?></label>
    <button class="knopf haupt">Recherche starten</button>
  </form>
  <?php if ($fehlend): ?>
    <div class="mk-fein">Noch ohne Zielgruppe in <?= Fmt::h($name) ?> — ein Klick startet die Recherche für genau diese Branche:</div>
    <div class="mk-fehlt">
      <?php foreach ($fehlend as $fz): ?>
        <form method="post" action="<?= Fmt::h(url('zielgruppen')) ?>"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_starten"><input type="hidden" name="branche" value="<?= Fmt::h($fz['branche']) ?>"><input type="hidden" name="land" value="<?= Fmt::h($fz['land']) ?>"><button class="knopf klein" title="<?= (int) $fz['firmen'] > 0 ? Fmt::h(number_format((int) $fz['firmen'], 0, ',', '.') . ' Betriebe in der Akquise') : 'eigene Seite vorhanden, noch keine Betriebe in der Akquise' ?>"><?= Fmt::h($branchen[$fz['branche']] ?? $fz['branche']) ?> recherchieren</button></form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="mk-fein" style="margin:10px 0 0;max-width:90ch;line-height:1.55">Dein PC holt den Auftrag ab und lässt Claude über dein Claude-Abo im Netz suchen (nur Websuche und Webseiten lesen, kein API-Schlüssel). Dauer etwa 10–20 Minuten je Land. Alles kommt als Entwurf mit Quellen<?= $land === 'IT' ? ', Italienisches mit deutscher Übersetzung' : '' ?> — nichts gilt, bevor du es freigibst. Höchstens <?= MkAuftrag::PRO_TAG ?> Recherchen am Tag.</p>
  <?php if ($ohneDeutsch > 0): ?>
    <div class="mk-hinweis-zeile" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--linie)">
      <span style="font-size:14px"><?= $ohneDeutsch ?> italienische <?= $ohneDeutsch === 1 ? 'Text hat' : 'Texte haben' ?> noch keine deutsche Übersetzung (aus der Zeit vor dem Umbau).</span>
      <form method="post" action="<?= Fmt::h(url('zielgruppen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="uebersetzen_starten"><button class="knopf klein">Übersetzen lassen</button></form>
    </div>
  <?php endif; ?>
  <?php $mkSeite = 'zielgruppen'; require __DIR__ . '/mk_auftraege.php'; ?>
</section>

<section id="funde" aria-labelledby="mk-funde-titel">
  <div class="mk-hinweis-zeile" style="margin:6px 0 10px">
    <h2 id="mk-funde-titel" style="margin:0;font-size:17px">Recherche-Funde für <?= Fmt::h($name) ?> <span class="mehr" style="font-weight:400;color:var(--leise);font-size:13px"><?= count($funde) ?> · Themen, Trends, Fragen, Wettbewerb — jeweils mit Quelle</span></h2>
  </div>
  <form class="mk-filter" method="get" action="<?= Fmt::h(url('zielgruppen')) ?>#funde" aria-label="Funde filtern">
    <input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
    <select name="art" aria-label="Art" style="width:auto"><option value="">Alle Arten</option><?php foreach (MkZielgruppe::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"<?= $f['art'] === $ak ? ' selected' : '' ?>><?= Fmt::h($aw) ?></option><?php endforeach; ?></select>
    <select name="branche" aria-label="Branche" style="width:auto"><option value="">Alle Branchen</option><?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?></option><?php endforeach; ?></select>
    <select name="status" aria-label="Status" style="width:auto"><option value="">Offen und gemerkt</option><?php foreach (MkZielgruppe::STATUS_RECHERCHE as $sk => $sw): ?><option value="<?= $sk ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select>
    <button class="knopf">Filtern</button>
  </form>
  <?php if (!$funde): ?>
    <div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Funde für <?= Fmt::h($name) ?><?= array_filter($f) ? ' mit diesem Filter' : '' ?>. Oben „Recherche starten“ drücken — Claude sucht im Netz, prüft die Quellen und legt die Funde hier ab.</p></div>
  <?php else: ?>
    <?php $fundeZurueck = $zurueck; require __DIR__ . '/mk_funde.php'; ?>
  <?php endif; ?>
</section>
