<?php
/**
 * Marketing · ein Inhalt (Content-Studio — 01.10.2026): Vorschau wie auf der
 * Plattform, Zeichenzähler, Freigabe mit eigenem Link, Bearbeiten.
 * Erwartet: $x (MkInhalt::laden), $zg (MkZielgruppe::laden oder null), $kampagnen, $funde.
 */
$branchen = MkKampagne::branchen();
$f = $x['f'];
$G = MkInhalt::G;
$link = MkInhalt::link($x);
[$kamp, $cr] = MkInhalt::kampagne($x);
$istSozial = in_array($x['plattform'], ['instagram', 'facebook', 'linkedin', 'threads', 'tiktok', 'youtube'], true);
$zaehler = static function (string $t, int $grenze, ?int $empf = null): string {
    $n = mb_strlen($t);
    $zu = $n > $grenze || ($empf !== null && $n > $empf);
    return '<span class="mk-zaehler' . ($zu ? ' zu' : '') . '">' . $n . '/' . ($empf ?? $grenze) . ($empf !== null && $n > $empf ? ' — wird gekürzt angezeigt' : '') . '</span>';
};
$textVoll = trim(((string) ($f['hook'] ?? '') !== '' && !str_starts_with((string) ($f['text'] ?? ''), (string) $f['hook']) ? $f['hook'] . "\n\n" : '') . (string) ($f['text'] ?? ''));
$posten = static fn(string $tat, string $wort, string $klasse = 'knopf', string $mehr = '') => '<form method="post" action="' . Fmt::h(url('inhalte/' . (int) $x['id'])) . '" style="margin:0;display:inline-flex;gap:8px;flex-wrap:wrap;align-items:center;max-width:100%;min-width:0">'
    . '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '"><input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $x['id'] . '">' . $mehr
    . '<button class="' . $klasse . '">' . Fmt::h($wort) . '</button></form>';
require_once dirname(__DIR__) . '/src/MkMedium.php';
$medien = $medien ?? [];
$medienAuftraege = $medienAuftraege ?? [];
$pc = $pc ?? ['pc_wach' => false, 'pc_alter' => null];
$bildGew = null; $videoGew = null;
foreach ($medien as $m) { if ($m['status'] === 'gewaehlt' && $m['art'] === 'bild' && !$bildGew) { $bildGew = $m; } if ($m['status'] === 'gewaehlt' && $m['art'] === 'video' && !$videoGew) { $videoGew = $m; } }
$medienBild = static fn(?array $m, string $ersatz): string => $m
    ? ($m['art'] === 'video' ? '<video class="mk-medium" src="' . Fmt::h(url('medien/' . (int) $m['id'])) . '" controls preload="metadata" playsinline></video>'
                             : '<img class="mk-medium" src="' . Fmt::h(url('medien/' . (int) $m['id'])) . '" alt="' . Fmt::h('Bild zu „' . $x['titel'] . '“') . '" loading="lazy">')
    : '<div class="mk-vorschau__bild">' . Fmt::h($ersatz) . '</div>';
$autoName = (MkKampagne::branchen()[$x['branche']] ?? $x['branche']) . ' ' . $x['land'] . ' · ' . trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? '');
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1><?= Fmt::h($x['titel']) ?>
      <span class="marke2 <?= ['entwurf' => 'warnung', 'freigegeben' => 'gut', 'veroeffentlicht' => 'gut'][$x['status']] ?? '' ?>" style="vertical-align:4px"><?= Fmt::h(MkInhalt::STATUS[$x['status']] ?? $x['status']) ?></span></h1>
    <div class="weg"><?= Fmt::h((MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']) . ' · ' . (MkInhalt::ARTEN[$x['art']] ?? $x['art']) . ' · ' . (MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . ' · ' . (MkInhalt::SPRACHEN[$x['sprache']] ?? $x['sprache'])) ?>
      <?php if ($zg): ?> · für <a href="<?= Fmt::h(url('zielgruppen/' . (int) $zg['id'])) ?>"><?= Fmt::h($zg['titel']) ?></a><?php endif; ?> · von Claude geschrieben am <?= Fmt::h(date('d.m.Y', strtotime((string) $x['created_at']))) ?></div>
  </div>
  <a class="knopf" href="<?= Fmt::h(url('inhalte')) ?>">‹ Alle Inhalte</a>
</div>

<?php if ($x['status'] === 'entwurf'): ?>
<div class="block" style="border-color:var(--linie2)">
  <h2>Prüfen und freigeben</h2>
  <p style="margin:0 0 12px;max-width:72ch;line-height:1.6">Lies es so, wie es deine Zielgruppe liest: Stimmt jede Aussage? Passt der Ton? Ändern kannst du unten. Mit der Freigabe bekommt das Stück ein eigenes Werbemittel und damit einen eigenen Link — jeder Klick darauf zählt bis zum Kunden.</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <?= $posten('inhalt_freigeben', 'Freigeben — eigenen Link erzeugen', 'knopf haupt',
        '<label class="mk-sr" for="mi_fk">Kampagne</label><select id="mi_fk" name="kampagne" style="width:auto;max-width:100%;min-width:0;flex:1 1 200px"><option value="0">' . Fmt::h($x['kampagne_id'] ? 'die beim Auftrag gewählte Kampagne' : 'eigene Kampagne: ' . $autoName . ' · ' . date('m/Y')) . '</option>'
        . implode('', array_map(static fn($k) => '<option value="' . (int) $k['id'] . '"' . ((int) $x['kampagne_id'] === (int) $k['id'] ? ' selected' : '') . '>' . Fmt::h($k['name']) . '</option>', $kampagnen)) . '</select>') ?>
    <?= $posten('inhalt_verwerfen', 'Verwerfen') ?>
  </div>
</div>
<?php elseif ($link): ?>
<?php require_once dirname(__DIR__) . '/src/MkVeroeffentlichen.php';
  $mv = $x['status'] === 'freigegeben' ? MkVeroeffentlichen::moeglich($x) : ['auto' => false, 'grund' => '', 'medium' => null];
  $mvIds = json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
  $mvPl = MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']; ?>
<div class="block" id="posten" style="border-color:var(--linie2)">
  <h2><?= $x['status'] === 'veroeffentlicht' ? 'Veröffentlicht' : 'Freigegeben — jetzt posten' ?> <span class="mehr">eigener Link, zählt bis zum Kunden</span></h2>
  <?php if (!empty($x['post_fehler'])): ?><div class="hinweis schlecht" style="margin:0 0 12px">Zuletzt nicht geklappt: <?= Fmt::h((string) $x['post_fehler']) ?></div><?php endif; ?>
  <?php if ($x['status'] === 'freigegeben'): ?>
  <div class="mk-posten">
    <?php if ($mv['auto']): ?>
      <?= $posten('inhalt_posten', 'Jetzt auf ' . $mvPl . ' veröffentlichen', 'knopf haupt') ?>
      <form method="post" action="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>" class="mk-filter" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="inhalt_planen"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
        <label class="mk-sr" for="mv_wann">Zeitpunkt</label>
        <input id="mv_wann" type="datetime-local" name="wann" style="width:auto" min="<?= date('Y-m-d\TH:i', time() + 300) ?>" value="<?= $x['geplant_am'] ? Fmt::h(date('Y-m-d\TH:i', strtotime((string) $x['geplant_am']))) : '' ?>">
        <button class="knopf"><?= $x['geplant_am'] ? 'Neu planen' : 'Planen' ?></button>
      </form>
      <?php if ($x['geplant_am']): ?><?= $posten('inhalt_planen', 'Planung aufheben', 'knopf klein') ?><span class="marke2 warnung">geplant für <?= Fmt::h(date('d.m. H:i', strtotime((string) $x['geplant_am']))) ?></span><?php endif; ?>
    <?php else: ?>
      <p class="mk-fein" style="margin:0;max-width:70ch"><?= Fmt::h($mv['grund']) ?></p>
    <?php endif; ?>
    <a class="knopf" href="<?= Fmt::h(url('inhalte/' . (int) $x['id']) . '?paket=1') ?>">Paket herunterladen (ZIP)</a>
  </div>
  <?php elseif ($mvIds): ?>
  <p class="mk-fein" style="margin:0 0 10px">Automatisch veröffentlicht<?= !empty($mvIds['fb']) ? ' · <a href="https://www.facebook.com/' . Fmt::h(rawurlencode((string) $mvIds['fb'])) . '" target="_blank" rel="noopener noreferrer">auf Facebook ansehen</a>' : '' ?><?= !empty($mvIds['ig']) ? ' · Instagram-Beitrag ' . Fmt::h((string) $mvIds['ig']) : '' ?><?= !empty($mvIds['tg']) ? ' · Telegram-Nachricht ' . Fmt::h((string) $mvIds['tg']) : '' ?></p>
  <?php endif; ?>
  <div class="mk-teilen">
    <div>
      <div class="mk-link"><input id="mi_link" readonly value="<?= Fmt::h($link) ?>" aria-label="Eigener Link"><button class="knopf" type="button" data-kopieren="mi_link">Link kopieren</button></div>
      <?php if (in_array($x['plattform'], ['instagram', 'tiktok'], true) && !in_array($x['format'], ['meta_anzeige'], true)): ?>
        <p class="mk-fein" style="margin:0">Auf <?= Fmt::h(MkKampagne::PLATTFORMEN[$x['plattform']]) ?> ist ein Link im Text nicht klickbar: in die Bio, als Link-Sticker in der Story oder in den Kommentar „Link in Bio“.</p>
      <?php endif; ?>
      <label class="mk-fein" for="mi_text">Fertiger Text<?= in_array($x['plattform'], MkInhalt::LINK_IM_TEXT, true) || in_array($x['format'], ['meta_anzeige', 'google_anzeige', 'telegram', 'profil'], true) ? ' mit Link' : '' ?></label>
      <textarea id="mi_text" readonly rows="8" style="font-size:13.5px;line-height:1.5"><?= Fmt::h(MkInhalt::kopiertext($x)) ?></textarea>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button class="knopf haupt" type="button" data-kopieren="mi_text">Text kopieren</button>
        <?php if ($x['status'] === 'freigegeben'): ?><?= $posten('inhalt_veroeffentlicht', 'Selbst gepostet — als veröffentlicht markieren') ?><?php endif; ?>
        <?php if ($x['status'] === 'veroeffentlicht'): ?><a class="knopf" href="<?= Fmt::h(url('inhalte/' . (int) $x['id']) . '?paket=1') ?>">Paket (ZIP)</a><?php endif; ?>
        <?php if ($kamp): ?><a class="knopf" href="<?= Fmt::h(url('kampagnen/' . (int) $kamp['id'])) ?>">Kampagne „<?= Fmt::h($kamp['name']) ?>“ · Zahlen</a><?php endif; ?>
      </div>
      <p class="mk-fein" style="margin:0">Werbemittel <span class="mk-code"><?= Fmt::h(($kamp['code'] ?? '') . '/' . ($cr['code'] ?? '')) ?></span><?= $x['veroeffentlicht_am'] ? ' · veröffentlicht am ' . Fmt::h(date('d.m.Y', strtotime((string) $x['veroeffentlicht_am']))) : '' ?></p>
    </div>
    <div class="mk-qr" aria-label="QR-Code zum Link"><?= MkKampagne::qr($link) ?></div>
  </div>
</div>
<?php endif; ?>

<div class="mk-zwei">
  <div class="block">
    <h2>Vorschau <span class="mehr">ungefähr so auf <?= Fmt::h(MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']) ?></span></h2>
    <?php if ($x['format'] === 'google_anzeige'): ?>
      <div class="mk-vorschau google">
        <div class="g-url"><span class="g-anz">Gesponsert</span> vecom-design.it<?= ($f['pfad1'] ?? '') !== '' ? ' › ' . Fmt::h($f['pfad1']) : '' ?><?= ($f['pfad2'] ?? '') !== '' ? ' › ' . Fmt::h($f['pfad2']) : '' ?></div>
        <div class="g-titel"><?= Fmt::h(implode(' | ', array_slice((array) $f['ueberschriften'], 0, 3))) ?></div>
        <div class="g-text"><?= Fmt::h(implode(' ', array_slice((array) $f['beschreibungen'], 0, 2))) ?></div>
      </div>
      <p class="mk-fein" style="margin:10px 0 6px">Google mischt Überschriften und Beschreibungen selbst — jede muss für sich stehen.</p>
      <ul class="mk-zeilen"><?php foreach ((array) $f['ueberschriften'] as $u): ?><li><span><?= Fmt::h($u) ?></span><?= $zaehler($u, $G['g_ueberschrift']) ?></li><?php endforeach; ?></ul>
      <ul class="mk-zeilen" style="margin-top:10px"><?php foreach ((array) $f['beschreibungen'] as $b): ?><li><span><?= Fmt::h($b) ?></span><?= $zaehler($b, $G['g_beschreibung']) ?></li><?php endforeach; ?></ul>
      <?php if (!empty($f['keywords'])): ?><p class="mk-fein" style="margin:10px 0 0">Keywords: <?= Fmt::h(implode(' · ', $f['keywords'])) ?></p><?php endif; ?>
    <?php elseif ($x['format'] === 'telegram' || $x['format'] === 'profil'): ?>
      <div class="mk-vorschau">
        <div class="mk-vorschau__kopf"><span class="mk-vorschau__logo"></span><div><div class="mk-vorschau__name">Vecom Design</div><div class="mk-vorschau__zweit"><?= $x['format'] === 'telegram' ? 'Kanal' : 'Google-Unternehmensprofil' ?></div></div></div>
        <?= $medienBild($bildGew, $x['bildidee'] ? 'Bildidee: ' . $x['bildidee'] : 'Bild') ?>
        <div class="mk-vorschau__text"><?= Fmt::h((string) $f['text']) ?></div>
        <?php $knopf = (string) ($f['knopf'] ?? $f['cta'] ?? ''); if ($knopf !== ''): ?><div class="mk-vorschau__leiste"><small><?= $link ? Fmt::h(preg_replace('~^https?://~', '', $link)) : 'Link folgt mit der Freigabe' ?></small><span class="mk-vorschau__knopf"><?= Fmt::h($knopf) ?></span></div><?php endif; ?>
      </div>
      <p style="margin:8px 0 0"><?= $zaehler((string) $f['text'], $x['format'] === 'telegram' ? $G['telegram'] : $G['profil']) ?></p>
    <?php else: ?>
      <?php $istAnzeige = $x['format'] === 'meta_anzeige'; $haupt = $istAnzeige ? (string) ($f['primaertexte'][0] ?? '') : $textVoll; ?>
      <div class="mk-vorschau">
        <div class="mk-vorschau__kopf"><span class="mk-vorschau__logo"></span><div><div class="mk-vorschau__name">Vecom Design</div><div class="mk-vorschau__zweit"><?= $istAnzeige ? 'Gesponsert' : 'Jetzt' ?></div></div></div>
        <?php if ($x['format'] !== 'story'): ?><div class="mk-vorschau__text"><?= Fmt::h($istAnzeige && mb_strlen($haupt) > $G['primaertext_sichtbar'] ? mb_substr($haupt, 0, $G['primaertext_sichtbar']) . '… Mehr' : $haupt) ?></div><?php endif; ?>
        <?php if (!empty($f['hashtags'])): ?><div class="mk-vorschau__tags"><?= Fmt::h(implode(' ', $f['hashtags'])) ?></div><?php endif; ?>
        <?php if (!empty($f['folien'])): ?>
          <div class="mk-folien"><?php foreach ($f['folien'] as $i => $fo): ?><div class="mk-folie"><i><?= $i + 1 ?>/<?= count($f['folien']) ?></i><b><?= Fmt::h($fo['titel']) ?></b><span><?= Fmt::h($fo['text']) ?></span></div><?php endforeach; ?></div>
        <?php else: ?>
          <?= $medienBild($x['format'] === 'reel' ? ($videoGew ?? $bildGew) : ($bildGew ?? $videoGew), $x['bildidee'] ? 'Bildidee: ' . $x['bildidee'] : ($x['format'] === 'reel' ? 'Kurzvideo — Skript unten' : 'Bild')) ?>
        <?php endif; ?>
        <?php if ($istAnzeige): ?>
          <div class="mk-vorschau__leiste"><div><small>VECOM-DESIGN.IT</small><b><?= Fmt::h((string) ($f['ueberschriften'][0] ?? '')) ?></b><?php if (($f['beschreibung'] ?? '') !== ''): ?><small><?= Fmt::h($f['beschreibung']) ?></small><?php endif; ?></div><span class="mk-vorschau__knopf"><?= Fmt::h(MkInhalt::META_CTA[$f['cta'] ?? ''] ?? 'Mehr dazu') ?></span></div>
        <?php endif; ?>
      </div>
      <?php if ($istAnzeige): ?>
        <p class="mk-fein" style="margin:10px 0 6px">Varianten — Meta testet sie selbst gegeneinander:</p>
        <ul class="mk-zeilen"><?php foreach ((array) $f['primaertexte'] as $t): ?><li><span style="white-space:pre-line"><?= Fmt::h($t) ?></span><?= $zaehler($t, $G['primaertext'], $G['primaertext_sichtbar']) ?></li><?php endforeach; ?>
          <?php foreach ((array) $f['ueberschriften'] as $t): ?><li><b><?= Fmt::h($t) ?></b><?= $zaehler($t, $G['meta_ueberschrift'], $G['meta_ueberschrift_empf']) ?></li><?php endforeach; ?></ul>
      <?php elseif ($x['format'] !== 'story'): ?>
        <p style="margin:8px 0 0"><?= $zaehler($textVoll, $G['text']) ?> · <?= count((array) ($f['hashtags'] ?? [])) ?>/<?= $G['hashtags'] ?> Hashtags<?= ($f['cta'] ?? '') !== '' ? ' · Aufruf: ' . Fmt::h($f['cta']) : '' ?></p>
      <?php else: ?>
        <p class="mk-fein" style="margin:8px 0 0">Story-Folien nacheinander<?= ($f['cta'] ?? '') !== '' ? ' · Sticker/Aufruf: ' . Fmt::h($f['cta']) : '' ?></p>
      <?php endif; ?>
      <?php if (!empty($f['szenen'])): ?>
        <h3 style="margin:14px 0 6px;font-size:14px">Drehbuch <span class="mk-fein">· Einstieg: <?= Fmt::h((string) $f['hook']) ?> · etwa <?= array_sum(array_map(static fn($s) => (int) $s['sekunden'], $f['szenen'])) ?> Sekunden</span></h3>
        <div class="tabellenrahmen"><table class="mk-tab mk-szenen"><thead><tr><th>Sek.</th><th>Bild</th><th>Einblendung</th><th>Sprecher</th></tr></thead><tbody>
          <?php foreach ($f['szenen'] as $s): ?><tr><td class="num"><?= (int) $s['sekunden'] ?></td><td><?= Fmt::h($s['bild']) ?></td><td><?= Fmt::h($s['einblendung']) ?></td><td><?= Fmt::h($s['sprecher']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block">
    <h2>Warum das trägt</h2>
    <p style="margin:0 0 12px;line-height:1.6"><?= Fmt::h((string) ($x['begruendung'] ?: '—')) ?></p>
    <?php if ($x['bildidee']): ?><h3 style="margin:0 0 4px;font-size:14px">Bild- bzw. Videoidee</h3><p style="margin:0 0 12px;line-height:1.6"><?= Fmt::h((string) $x['bildidee']) ?></p><?php endif; ?>
    <?php if ($funde): ?><h3 style="margin:0 0 4px;font-size:14px">Benutzte Recherche</h3>
      <ul class="mk-liste"><?php foreach ($funde as $fu): ?><li><?= Fmt::h(MkZielgruppe::ARTEN[$fu['art']] ?? $fu['art']) ?>: <?= Fmt::h($fu['titel']) ?></li><?php endforeach; ?></ul>
      <p class="mk-fein" style="margin:6px 0 0">Quellen dazu unter <a href="<?= Fmt::h(url('recherche')) ?>">Recherche</a>.</p><?php endif; ?>
  </div>
</div>

<section class="block mk-auftrag" id="medien" aria-labelledby="mk-medien-titel">
  <div class="mk-auftrag__kopf">
    <h2 id="mk-medien-titel">Bild und Video <span class="mehr">über Kie.ai auf deinem PC</span></h2>
    <span class="mk-ampel <?= $pc['pc_wach'] ? 'gruen' : '' ?>"><i></i><?= $pc['pc_wach'] ? 'Dein PC ist an' : 'Dein PC ist aus — der Auftrag wartet' ?></span>
  </div>
  <?php if ($x['status'] !== 'verworfen'): ?>
  <div class="mk-medien-knoepfe">
    <form class="mk-filter" method="post" action="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>" style="margin:0">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="medium_erzeugen"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="medium" value="bild">
      <label class="mk-sr" for="mm_bf">Format des Bildes</label>
      <select id="mm_bf" name="format" style="width:auto"><?php foreach (MkMedium::FORMATE['bild'] as $fm): ?><option value="<?= $fm ?>"<?= MkMedium::formatFuer($x) === $fm ? ' selected' : '' ?>><?= $fm ?></option><?php endforeach; ?></select>
      <button class="knopf haupt">Bild erzeugen · ca. <?= (int) MkMedium::MODELLE['bild']['nano-banana-pro'][1] ?> Credits</button>
    </form>
    <form class="mk-filter" method="post" action="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>" style="margin:0">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="medium_erzeugen"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="medium" value="video">
      <label class="mk-sr" for="mm_vm">Videomodell</label>
      <select id="mm_vm" name="modell" style="width:auto"><?php foreach (MkMedium::MODELLE['video'] as $mk => [$mw, $mc]): ?><option value="<?= $mk ?>"><?= Fmt::h($mw) ?> · ca. <?= (int) $mc ?> Credits</option><?php endforeach; ?></select>
      <label class="mk-sr" for="mm_vf">Format des Videos</label>
      <select id="mm_vf" name="format" style="width:auto"><?php foreach (MkMedium::FORMATE['video'] as $fm): ?><option value="<?= $fm ?>"<?= MkMedium::formatFuer($x, 'video') === $fm ? ' selected' : '' ?>><?= $fm ?></option><?php endforeach; ?></select>
      <button class="knopf">Video erzeugen</button>
    </form>
  </div>
  <p class="mk-fein" style="margin:8px 0 0;max-width:95ch;line-height:1.55">Vor jedem Lauf prüft dein PC das Kie-Guthaben; der Schlüssel bleibt auf dem PC. Preise laut Kie.ai (Stand 10/2026, 1 Credit ≈ 0,005 $): Bild etwa 24 Credits, Video Fast 80, Quality 400 — der echte Verbrauch steht danach beim Bild. <?= $bildGew ? 'Ein Video nimmt das gewählte Bild als ersten Frame, solange es jünger als zwei Tage ist.' : 'Wählst du vorher ein Bild, wird es zum ersten Frame des Videos.' ?></p>
  <?php endif; ?>
  <?php $auftraege = $medienAuftraege; $mkSeite = 'inhalte/' . (int) $x['id']; require __DIR__ . '/mk_auftraege.php'; ?>
  <?php if ($medien): ?>
  <div class="mk-galerie">
    <?php foreach ($medien as $m): ?>
      <figure class="mk-galerie__stueck<?= $m['status'] === 'gewaehlt' ? ' gewaehlt' : '' ?>">
        <?= $medienBild($m, '') ?>
        <figcaption>
          <span><?php if ($m['status'] === 'gewaehlt'): ?><span class="marke2 gut">gewählt</span> <?php endif; ?><?= Fmt::h(MkMedium::ARTEN[$m['art']] ?? $m['art']) ?> · <?= Fmt::h($m['format']) ?> · <?= Fmt::h((int) $m['bytes'] >= 1048576 ? number_format((int) $m['bytes'] / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round((int) $m['bytes'] / 1024)) . ' KB') ?><?= $m['credits'] !== null ? ' · ' . Fmt::h(rtrim(rtrim(number_format((float) $m['credits'], 2, ',', '.'), '0'), ',')) . ' Credits' : '' ?></span>
          <span class="mk-galerie__knoepfe">
            <?php if ($m['status'] !== 'gewaehlt'): ?><?= $posten('medium_status', 'Wählen', 'knopf klein', '<input type="hidden" name="medium_id" value="' . (int) $m['id'] . '"><input type="hidden" name="status" value="gewaehlt">') ?><?php endif; ?>
            <a class="knopf klein" href="<?= Fmt::h(url('medien/' . (int) $m['id']) . '?laden=1') ?>">Herunterladen</a>
            <?= $posten('medium_status', 'Verwerfen', 'knopf klein', '<input type="hidden" name="medium_id" value="' . (int) $m['id'] . '"><input type="hidden" name="status" value="verworfen">') ?>
          </span>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if (in_array($x['status'], ['entwurf', 'freigegeben'], true)): ?>
<details class="block mk-bearbeiten"<?= $x['status'] === 'entwurf' ? ' open' : '' ?>>
  <summary style="cursor:pointer"><h2 style="display:inline">Bearbeiten</h2></summary>
  <form method="post" action="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="inhalt_speichern"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
    <div class="feld"><label for="e_titel">Titel <span class="mk-fein">(nur intern, auch Name des Werbemittels)</span></label><input id="e_titel" name="titel" maxlength="160" value="<?= Fmt::h($x['titel']) ?>"></div>
    <?php if (in_array($x['format'], ['beitrag', 'karussell', 'reel'], true)): ?>
      <div class="feld"><label for="e_hook">Einstieg (erste Zeile)</label><input id="e_hook" name="f_hook" maxlength="<?= $G['hook'] ?>" value="<?= Fmt::h((string) ($f['hook'] ?? '')) ?>"></div>
    <?php endif; ?>
    <?php if (in_array($x['format'], ['karussell', 'story'], true)): ?>
      <div class="feld"><label for="e_folien">Folien <span class="mk-fein">(eine je Zeile: Titel | Text)</span></label>
        <textarea id="e_folien" name="f_folien" rows="6"><?= Fmt::h(implode("\n", array_map(static fn($fo) => $fo['titel'] . ' | ' . $fo['text'], (array) ($f['folien'] ?? [])))) ?></textarea></div>
    <?php endif; ?>
    <?php if ($x['format'] === 'reel'): ?>
      <div class="feld"><label for="e_szenen">Szenen <span class="mk-fein">(eine je Zeile: Sekunden | Bild | Einblendung | Sprecher)</span></label>
        <textarea id="e_szenen" name="f_szenen" rows="7"><?= Fmt::h(implode("\n", array_map(static fn($s) => $s['sekunden'] . ' | ' . $s['bild'] . ' | ' . $s['einblendung'] . ' | ' . $s['sprecher'], (array) ($f['szenen'] ?? [])))) ?></textarea></div>
    <?php endif; ?>
    <?php if (in_array($x['format'], ['beitrag', 'karussell', 'reel', 'telegram', 'profil'], true)): ?>
      <div class="feld"><label for="e_text">Text</label><textarea id="e_text" name="f_text" rows="8" maxlength="<?= $x['format'] === 'telegram' ? $G['telegram'] : ($x['format'] === 'profil' ? $G['profil'] : $G['text']) ?>"><?= Fmt::h((string) ($f['text'] ?? '')) ?></textarea></div>
    <?php endif; ?>
    <?php if (in_array($x['format'], ['beitrag', 'karussell', 'reel'], true)): ?>
      <div class="feld"><label for="e_tags">Hashtags <span class="mk-fein">(mit Leerzeichen getrennt, höchstens <?= $G['hashtags'] ?>)</span></label><input id="e_tags" name="f_hashtags" value="<?= Fmt::h(implode(' ', (array) ($f['hashtags'] ?? []))) ?>"></div>
    <?php endif; ?>
    <?php if (in_array($x['format'], ['beitrag', 'karussell', 'reel', 'story', 'profil'], true)): ?>
      <div class="feld"><label for="e_cta">Handlungsaufruf</label><input id="e_cta" name="f_cta" maxlength="<?= $G['cta'] ?>" value="<?= Fmt::h((string) ($f['cta'] ?? '')) ?>"></div>
    <?php endif; ?>
    <?php if ($x['format'] === 'telegram'): ?>
      <div class="feld"><label for="e_knopf">Text auf dem Knopf</label><input id="e_knopf" name="f_knopf" maxlength="<?= $G['knopf'] ?>" value="<?= Fmt::h((string) ($f['knopf'] ?? '')) ?>"></div>
    <?php endif; ?>
    <?php if ($x['format'] === 'meta_anzeige'): ?>
      <?php for ($i = 0; $i < $G['meta_varianten']; $i++): ?>
        <div class="feld"><label for="e_pt<?= $i ?>">Primärtext <?= $i + 1 ?> <span class="mk-fein">(die ersten <?= $G['primaertext_sichtbar'] ?> Zeichen sind sichtbar)</span></label><textarea id="e_pt<?= $i ?>" name="f_primaertexte[]" rows="3" maxlength="<?= $G['primaertext'] ?>"><?= Fmt::h((string) ($f['primaertexte'][$i] ?? '')) ?></textarea></div>
      <?php endfor; ?>
      <?php for ($i = 0; $i < $G['meta_varianten']; $i++): ?>
        <div class="feld"><label for="e_ue<?= $i ?>">Überschrift <?= $i + 1 ?> <span class="mk-fein">(bis <?= $G['meta_ueberschrift_empf'] ?> empfohlen)</span></label><input id="e_ue<?= $i ?>" name="f_ueberschriften[]" maxlength="<?= $G['meta_ueberschrift'] ?>" value="<?= Fmt::h((string) ($f['ueberschriften'][$i] ?? '')) ?>"></div>
      <?php endfor; ?>
      <div class="feld"><label for="e_be">Beschreibung</label><input id="e_be" name="f_beschreibung" maxlength="<?= $G['meta_beschreibung'] ?>" value="<?= Fmt::h((string) ($f['beschreibung'] ?? '')) ?>"></div>
      <div class="feld"><label for="e_mcta">Knopf</label><select id="e_mcta" name="f_cta"><?php foreach (MkInhalt::META_CTA as $ck => $cw): ?><option value="<?= $ck ?>"<?= ($f['cta'] ?? '') === $ck ? ' selected' : '' ?>><?= Fmt::h($cw) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <?php if ($x['format'] === 'google_anzeige'): ?>
      <div class="feld"><label for="e_gue">Überschriften <span class="mk-fein">(eine je Zeile, 3–15, je höchstens 30 Zeichen — längere fallen weg)</span></label><textarea id="e_gue" name="f_ueberschriften" rows="8"><?= Fmt::h(implode("\n", (array) $f['ueberschriften'])) ?></textarea></div>
      <div class="feld"><label for="e_gbe">Beschreibungen <span class="mk-fein">(eine je Zeile, 2–4, je höchstens 90 Zeichen)</span></label><textarea id="e_gbe" name="f_beschreibungen" rows="4"><?= Fmt::h(implode("\n", (array) $f['beschreibungen'])) ?></textarea></div>
      <div class="feld"><label for="e_p1">Pfad 1 / Pfad 2 <span class="mk-fein">(je bis 15 Zeichen)</span></label>
        <div style="display:flex;gap:8px"><input id="e_p1" name="f_pfad1" maxlength="15" value="<?= Fmt::h((string) ($f['pfad1'] ?? '')) ?>"><input aria-label="Pfad 2" name="f_pfad2" maxlength="15" value="<?= Fmt::h((string) ($f['pfad2'] ?? '')) ?>"></div></div>
      <div class="feld"><label for="e_kw">Keywords <span class="mk-fein">(eines je Zeile)</span></label><textarea id="e_kw" name="f_keywords" rows="4"><?= Fmt::h(implode("\n", (array) ($f['keywords'] ?? []))) ?></textarea></div>
    <?php endif; ?>
    <div class="feld"><label for="e_bild">Bild- bzw. Videoidee</label><textarea id="e_bild" name="bildidee" rows="3"><?= Fmt::h((string) $x['bildidee']) ?></textarea></div>
    <div class="feld"><label for="e_bp">Bild-Prompt für Kie.ai <span class="mk-fein">(englisch; leer = Bildidee wird genommen)</span></label><textarea id="e_bp" name="bild_prompt" rows="3"><?= Fmt::h((string) ($x['bild_prompt'] ?? '')) ?></textarea></div>
    <button class="knopf haupt">Speichern</button>
  </form>
</details>
<?php endif; ?>
