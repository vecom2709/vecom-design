<?php
/**
 * Marketing · Inhalte (Content-Studio, Marketing-Studio Schritt 2 — 01.10.2026).
 * Erwartet: $f (Filter), $liste (MkInhalt::liste), $zahl (je Status), $zielgruppen (freigegebene),
 *           $alleZg, $kampagnen, $auftraege (MkAuftrag::liste(…, 'inhalte')), $pc (AkquiseSteuerung::stand).
 * Marketing-Studio 5: $land, $offen (MkLand::offen), $ohneDeutsch — je Land getrennt, Italienisches mit
 * deutscher Fassung zum Lesen.
 */
require_once dirname(__DIR__) . '/src/MkAuftrag.php';
require_once dirname(__DIR__) . '/src/MkLand.php';
$land = $land ?? 'IT';
$offen = $offen ?? MkLand::offen();
$ohneDeutsch = (int) ($ohneDeutsch ?? 0);
$branchen = MkKampagne::branchen();
$auftraege = $auftraege ?? [];
$pc = $pc ?? ['pc_wach' => false, 'pc_alter' => null];
$kurz = static function (array $x): string {
    $f = $x['f'];
    $t = match ($x['format']) {
        'meta_anzeige' => (string) ($f['primaertexte'][0] ?? ''),
        'google_anzeige' => implode(' · ', array_slice((array) ($f['ueberschriften'] ?? []), 0, 3)),
        'karussell', 'story' => implode(' · ', array_map(static fn($fo) => (string) $fo['titel'], array_slice((array) ($f['folien'] ?? []), 0, 3))),
        default => (string) (($f['hook'] ?? '') !== '' ? $f['hook'] : ($f['text'] ?? '')),
    };
    return mb_strlen($t) > 170 ? mb_substr($t, 0, 168) . '…' : $t;
};
$filterLink = static fn(array $mehr) => url('inhalte') . '?' . http_build_query(array_filter($mehr + $f));
$deKurz = static function (array $x): string {
    $t = trim(preg_replace('/\s+/u', ' ', (string) ($x['uebersetzung'] ?? '')) ?? '');
    return mb_strlen($t) > 140 ? mb_substr($t, 0, 138) . '…' : $t;
};
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Inhalte</h1>
    <div class="weg">organisch und bezahlt · von Claude aus einer freigegebenen Zielgruppe geschrieben · du prüfst, änderst und gibst frei — jedes Stück bekommt seinen eigenen Link</div>
  </div>
</div>

<?php $mkLand = $land; $mkLandSeite = 'inhalte'; $mkLandOffen = $offen; require __DIR__ . '/mk_land.php'; ?>

<section class="block mk-auftrag" id="auftraege" aria-labelledby="mk-schreib-titel">
  <div class="mk-auftrag__kopf">
    <h2 id="mk-schreib-titel">Claude Inhalte schreiben lassen</h2>
    <span class="mk-ampel <?= $pc['pc_wach'] ? 'gruen' : '' ?>"><i></i><?= $pc['pc_wach'] ? 'Dein PC ist an — holt Aufträge alle 5 Minuten ab' : ($pc['pc_alter'] === null ? 'Dein PC hat sich noch nie gemeldet' : 'Dein PC ist aus oder schläft — der Auftrag wartet, bis er wieder an ist') ?></span>
  </div>
  <?php if (!$zielgruppen): ?>
    <p style="margin:0;max-width:75ch;line-height:1.6">Noch keine freigegebene Zielgruppe in <?= Fmt::h(MkLand::name($land)) ?>. Claude schreibt nur für Zielgruppen, die du geprüft hast: unter <a href="<?= Fmt::h(url('zielgruppen') . '?land=' . $land) ?>">Zielgruppen &amp; Recherche</a> eine recherchieren lassen, öffnen und „Zielgruppe freigeben“ drücken.</p>
  <?php else: ?>
  <form class="mk-formular mk-schreiben" method="post" action="<?= Fmt::h(url('inhalte')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="inhalte_erstellen">
    <div class="feld"><label for="mi_zg">Für wen</label>
      <select id="mi_zg" name="zielgruppe"><?php foreach ($zielgruppen as $z): ?><option value="<?= (int) $z['id'] ?>"<?= (int) $f['zielgruppe'] === (int) $z['id'] ? ' selected' : '' ?>><?= Fmt::h(($branchen[$z['branche']] ?? $z['branche']) . ' · ' . $z['land'] . ' — ' . $z['titel']) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="mi_umfang">Was</label>
      <select id="mi_umfang" name="umfang"><option value="beides">Organisch und Anzeigen</option><option value="organisch">Nur organische Beiträge</option><option value="bezahlt">Nur Anzeigen</option></select></div>
    <div class="feld"><label for="mi_anzahl">Wie viele</label>
      <select id="mi_anzahl" name="anzahl"><?php foreach ([4, 6, 8, 12] as $n): ?><option value="<?= $n ?>"<?= $n === 6 ? ' selected' : '' ?>><?= $n ?> Stück</option><?php endforeach; ?></select></div>
    <div class="feld"><label for="mi_kampagne">In welche Kampagne</label>
      <select id="mi_kampagne" name="kampagne"><option value="0">je Plattform eine eigene (automatisch)</option><?php foreach ($kampagnen as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h($k['name']) ?></option><?php endforeach; ?></select></div>
    <fieldset class="feld breit mk-plattformen"><legend>Wo</legend>
      <?php foreach (MkInhalt::PLATTFORMEN as $pk): ?><label class="mk-haken"><input type="checkbox" name="plattformen[]" value="<?= $pk ?>"<?= in_array($pk, ['instagram', 'facebook', 'google'], true) ? ' checked' : '' ?>> <?= Fmt::h(MkKampagne::PLATTFORMEN[$pk] ?? $pk) ?></label><?php endforeach; ?>
    </fieldset>
    <div class="feld breit"><label for="mi_thema">Thema <span class="mk-fein">(freiwillig)</span></label>
      <input id="mi_thema" name="thema" maxlength="200" placeholder="z. B. Airbnb-Gebühr ab Mitte Oktober · Nebensaison · kostenloser Website-Check"></div>
    <div class="breit"><button class="knopf haupt">Inhalte schreiben lassen</button></div>
  </form>
  <p class="mk-fein" style="margin:8px 0 0;max-width:90ch;line-height:1.55">Claude nutzt das freigegebene Profil, deine Recherche-Funde (gemerkte zuerst) und die Grenzen jeder Plattform. Dauer etwa 5–15 Minuten, über dein Claude-Abo. Alles kommt als Entwurf; erst bei der Freigabe entsteht der eigene Link. Höchstens <?= MkAuftrag::PRO_TAG ?> Schreibaufträge am Tag.</p>
  <?php endif; ?>
  <?php if ($ohneDeutsch > 0): ?>
    <div class="mk-hinweis-zeile" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--linie)">
      <span style="font-size:14px"><?= $ohneDeutsch ?> italienische <?= $ohneDeutsch === 1 ? 'Inhalt hat' : 'Inhalte haben' ?> noch keine deutsche Fassung zum Lesen (aus der Zeit vor dem Umbau). Neue bringen sie gleich mit.</span>
      <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="uebersetzen_starten"><input type="hidden" name="zurueck" value="inhalte"><button class="knopf klein">Übersetzen lassen</button></form>
    </div>
  <?php endif; ?>
  <?php $mkSeite = 'inhalte'; require __DIR__ . '/mk_auftraege.php'; ?>
</section>

<?php if (!empty($vorherNachher)): /* Marketing-Studio 9 */ ?>
<section class="block mk-start" id="vorher-nachher" aria-labelledby="mk-vn-titel">
  <h2 id="mk-vn-titel">Vorher/Nachher aus fertigen Projekten <span class="mehr">der Kunde hat zugestimmt · ein Klick: drei Entwürfe mit Bild</span></h2>
  <ul class="mk-zeilen">
    <?php foreach ($vorherNachher as $vn): ?>
      <li><span><b><?= Fmt::h((string) ($vn['company'] ?: $vn['name'])) ?></b> <span class="mk-fein">· <?= Fmt::h((string) $vn['domain']) ?> · zugestimmt am <?= Fmt::h(date('d.m.Y', strtotime((string) $vn['referenz_am']))) ?></span></span>
        <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="vorher_nachher_erstellen"><input type="hidden" name="kunde" value="<?= (int) $vn['id'] ?>"><button class="knopf klein haupt">Beitrag erstellen</button></form></li>
    <?php endforeach; ?>
  </ul>
  <p class="mk-fein" style="margin:8px 0 0">Instagram, Facebook und Telegram in der Sprache des Kunden (mit deutscher Fassung). Dein PC fotografiert die neue Website und nimmt als „Vorher“ das Bild aus dem Website-Check der Akquise, falls es eins gibt. Freigeben wie immer.</p>
</section>
<?php endif; ?>

<nav class="mk-chips" aria-label="Nach Stand" style="margin:0 0 12px">
  <a href="<?= Fmt::h($filterLink(['status' => ''])) ?>"<?= $f['status'] === '' ? ' aria-current="page"' : '' ?>>Offen (<?= (int) $zahl['entwurf'] + (int) $zahl['freigegeben'] ?>)</a>
  <?php foreach (MkInhalt::STATUS as $sk => $sw): ?><a href="<?= Fmt::h($filterLink(['status' => $sk])) ?>"<?= $f['status'] === $sk ? ' aria-current="page"' : '' ?>><?= Fmt::h($sw) ?> (<?= (int) ($zahl[$sk] ?? 0) ?>)</a><?php endforeach; ?>
</nav>
<form class="mk-filter" method="get" action="<?= Fmt::h(url('inhalte')) ?>" aria-label="Inhalte filtern">
  <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= Fmt::h($f['status']) ?>"><?php endif; ?>
  <select name="plattform" aria-label="Plattform" style="width:auto"><option value="">Alle Plattformen</option><?php foreach (MkInhalt::PLATTFORMEN as $pk): ?><option value="<?= $pk ?>"<?= $f['plattform'] === $pk ? ' selected' : '' ?>><?= Fmt::h(MkKampagne::PLATTFORMEN[$pk] ?? $pk) ?></option><?php endforeach; ?></select>
  <select name="art" aria-label="Organisch oder bezahlt" style="width:auto"><option value="">Organisch und bezahlt</option><?php foreach (MkInhalt::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"<?= $f['art'] === $ak ? ' selected' : '' ?>><?= Fmt::h($aw) ?></option><?php endforeach; ?></select>
  <select name="zielgruppe" aria-label="Zielgruppe" style="width:auto;max-width:100%"><option value="0">Alle Zielgruppen</option><?php foreach ($alleZg as $z): ?><option value="<?= (int) $z['id'] ?>"<?= (int) $f['zielgruppe'] === (int) $z['id'] ? ' selected' : '' ?>><?= Fmt::h($z['titel']) ?></option><?php endforeach; ?></select>
  <button class="knopf">Filtern</button>
</form>

<?php if (!$liste): ?>
  <div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Inhalte in <?= Fmt::h(MkLand::name($land)) ?><?= array_filter(array_diff_key($f, ['land' => 1])) ? ' für diesen Filter' : '' ?>. Oben eine freigegebene Zielgruppe wählen und „Inhalte schreiben lassen“ drücken.</p></div>
<?php else: ?>
  <div class="mk-inhalte">
    <?php foreach ($liste as $x): ?>
      <a class="mk-inhalt-karte" href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>">
        <span class="mk-inhalt-karte__marken">
          <span class="marke2"><?= Fmt::h(MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']) ?></span>
          <span class="marke2 <?= $x['art'] === 'bezahlt' ? 'warnung' : '' ?>"><?= Fmt::h(MkInhalt::ARTEN[$x['art']] ?? $x['art']) ?></span>
          <span class="mk-fein"><?= Fmt::h(MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) ?> · <?= Fmt::h(strtoupper((string) $x['sprache'])) ?></span>
        </span>
        <b class="mk-inhalt-karte__titel"><?= Fmt::h($x['titel']) ?></b>
        <span class="mk-inhalt-karte__text"><?= Fmt::h($kurz($x)) ?></span>
        <?php if ($x['sprache'] !== 'de' && $deKurz($x) !== ''): ?><span class="mk-de"><?= Fmt::h($deKurz($x)) ?></span><?php endif; ?>
        <span class="mk-inhalt-karte__fuss">
          <span class="marke2 <?= ['entwurf' => 'warnung', 'freigegeben' => 'gut', 'veroeffentlicht' => 'gut', 'verworfen' => ''][$x['status']] ?? '' ?>"><?= Fmt::h(MkInhalt::STATUS[$x['status']] ?? $x['status']) ?></span>
          <span class="mk-fein"><?= Fmt::h(date('d.m.Y', strtotime((string) $x['created_at']))) ?><?= $x['creative_id'] ? ' · eigener Link' : '' ?><?= !empty($x['geplant_am']) && $x['status'] === 'freigegeben' ? ' · geplant ' . Fmt::h(date('d.m. H:i', strtotime((string) $x['geplant_am']))) : '' ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
