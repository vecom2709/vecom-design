<?php
/* ==========================================================================
   Ein Produkt im Marketing Center — in drei Schritten (04.10.2026, Uwe:
   „der Partner findet die Auswahl schwer … direkt auswählen, ohne viel zu
   suchen … leicht verständlicher und schöner“).

     ① Aussehen wählen   Bildkacheln statt Listen; Flyer mit Branchenmotiv
                         (Gruppen-Knöpfe) und allgemeiner Gestaltung. Immer
                         offen — kein zugeklapptes „Gestalten und freigeben“.
     ② Prüfen/freigeben  wie bisher: fertige Datei ansehen, Haken, Freigabe.
     ③ Bestellen         Auflagen mit Preis; gesperrt, bis freigegeben ist.

   Am Computer stehen Vorschau und Schritte nebeneinander (Vorschau bleibt
   beim Scrollen stehen), am Handy untereinander. Eingebunden aus
   partner_werbemittel.php in der Schleife über $wmP — alle Variablen von dort.
   ========================================================================== */
$wmSt = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? Werbemittel::stand((int) $p['id'], (int) $wmP['id']) : ['entwurf' => null, 'freigegeben' => null];
$wmJetzt = $wmSt['entwurf']['wahl'] ?? $wmSt['freigegeben']['wahl'] ?? ['stil' => '', 'sprache' => $sprache, 'kontakt' => 'email'];
$wmVl = (string) $wmP['vorlage'];
$wmGest = Werbemittel::gestaltbar($wmVl);
/* Was zur Wahl steht: Stile a, b, c … (nach Designlinie) und — bei Flyern — die Branchenmotive (nach Gruppe). */
$mcAlle = $wmGest ? Designlinie::stileDa($wmVl) : [];
$mcBr = array_values(array_filter($mcAlle, static fn($s) => strlen((string) $s) > 1));
$mcSt = array_values(array_filter($mcAlle, static fn($s) => strlen((string) $s) === 1));
if (!in_array($wmJetzt['stil'], $mcAlle, true)) { $wmJetzt['stil'] = (string) ($mcBr[0] ?? $mcSt[0] ?? 'a'); }
$wmBranchen = [];
if ($mcBr) {
    require_once dirname(__DIR__) . '/src/PartnerFlyer.php';
    foreach ($mcBr as $mcS) { $wmBranchen[$mcS] = PartnerFlyer::name($mcS, $sprache); }
}
$mcGrp = [];
foreach ($mcBr as $mcS) { $mcGrp[(string) (PartnerFlyer::liste()[$mcS]['g'] ?? 'allgemein')][] = $mcS; }
$mcGrpJetzt = isset($wmBranchen[$wmJetzt['stil']]) ? (string) (PartnerFlyer::liste()[$wmJetzt['stil']]['g'] ?? '') : '';
$mcQuer = $wmVl === 'visitenkarte';
$mcMini = static fn(string $s): string => $wmNurLesen ? '' : $selbst(['wmmini' => $wmVl, 'st' => $s, 'vks' => $sprache]);
$mcStilName = static fn(string $s): string => $wmBranchen[$s] ?? (PartnerKarten::STILE[$s][$sprache] ?? PartnerKarten::STILE[$s]['de'] ?? $s);
/* Überschrift (Schritt 4): nur bei Gestaltungen, die eine haben — Branchenmotive bringen ihre eigene mit. */
$mcTitelDa = $wmGest && $wmVl !== 'visitenkarte' && array_filter($mcSt, static fn($s) => WmDruck::hatTitel($wmVl, $s));
$mcTitelText = static fn(string $t, string $l): string => implode(' ', WmDruck::titelZeilen($t, $l));
$wmWahlText = static fn(array $w): string => $mcStilName((string) ($w['stil'] ?? ''))
    . (isset($w['titel']) ? ' · „' . $mcTitelText((string) $w['titel'], (string) ($w['sprache'] ?? $sprache)) . '“' : '')
    . ' · ' . (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'][$w['sprache'] ?? ''] ?? '')
    . ' · ' . PartnerKarten::kontakt($p, (string) ($w['kontakt'] ?? 'email'));
$mcNoch = $wmNoch && in_array($wmNochVar, array_column($wmP['varianten'], 'id'), true);
/* Produktfoto der Druckerei — nur, wenn genau diese Druckerei das Produkt im Land des Partners herstellt
   (sonst wäre es ein anderes Produkt als das, was er kauft). */
$mcFotoDa = !$wmNurLesen && Werbemittel::hersteller($wmP, (string) $wmP['land']) === 'Printful';
$mcFoto = static function (array $e) use ($h, $selbst, $mcT, $mcFotoDa, $p): void {
    if (!$mcFotoDa || empty($e['mockup_status']) || $e['mockup_status'] === 'fehler') { return; } ?>
    <div class="mc-foto"><span class="wm-meta" style="margin:0"><?= $h($mcT('foto_titel')) ?></span>
      <?php if ($e['mockup_status'] === 'fertig'): $mcNrn = Werbemittel::produktfotos((int) $e['id'], (int) $p['id']); ?>
        <img src="<?= $h($selbst(['wmfoto' => (int) $e['id']])) ?>" alt="" loading="lazy" width="1000" height="1000">
        <?php if (count($mcNrn) > 1): ?><span class="mc-fotos"><?php foreach (array_slice($mcNrn, 1) as $mcN): ?><a href="<?= $h($selbst(['wmfoto' => (int) $e['id'], 'n' => $mcN])) ?>" target="_blank" rel="noopener"><img src="<?= $h($selbst(['wmfoto' => (int) $e['id'], 'n' => $mcN])) ?>" alt="" loading="lazy" width="160" height="160"></a><?php endforeach; ?></span><?php endif; ?>
        <span class="mc-fotolinks"><?php foreach ($mcNrn as $mcN): ?><a class="knopf" href="<?= $h($selbst(['wmfoto' => (int) $e['id'], 'n' => $mcN, 'dl' => 1])) ?>" download><?= $h($mcT('foto_laden', ['{n}' => (string) ($mcN + 1)])) ?></a><?php endforeach; ?></span>
        <span class="wm-meta" style="margin:0"><?= $h($mcT('foto_nutzen')) ?></span>
      <?php else: ?><span class="wm-meta" style="margin:0"><?= $h($mcT('foto_wartet')) ?></span><?php endif; ?></div>
<?php };
$mcKachel = static function (string $s, string $linie, string $gruppe = '', string $linienName = '') use ($h, $wmJetzt, $mcMini, $mcStilName, $mcQuer, $wmNurLesen): void { ?>
  <label class="mc-kachel<?= $mcQuer ? ' mc-quer' : '' ?>" data-linie="<?= $linie ?>"<?= $gruppe !== '' ? ' data-flg="' . $h($gruppe) . '"' : '' ?>>
    <input type="radio" name="stil" value="<?= $h($s) ?>"<?= $s === $wmJetzt['stil'] ? ' checked' : '' ?>>
    <?php if (!$wmNurLesen): ?><img src="<?= $h($mcMini($s)) ?>" alt="" loading="lazy" decoding="async" width="260" height="<?= $mcQuer ? 168 : 368 ?>"><?php endif; ?>
    <span><?= $h($mcStilName($s)) ?><?php if ($linienName !== ''): ?><small class="mc-linienname"><?= $h($linienName) ?></small><?php endif; ?></span>
  </label>
<?php };
?>
<article class="wm-produkt mc-produkt" id="wm-p<?= (int) $wmP['id'] ?>"<?= isset($mcLinienJe[(int) $wmP['id']]) ? ' data-linien="' . $h(implode(' ', array_keys($mcLinienJe[(int) $wmP['id']]))) . '"' : '' ?>>
  <header class="mc-produktkopf">
    <div>
      <h3><?= $h($wmP['name']) ?><?php if (isset($wmP['sichtbar']) && !$wmP['sichtbar']): ?> <span class="marke2 warnung" style="font-size:12px">für Partner noch aus</span><?php endif; ?></h3>
      <?php if ($wmP['text'] !== ''): ?><p class="wm-text"><?= $h($wmP['text']) ?></p><?php endif; ?>
      <p class="wm-meta"><?= $wmP['format'] !== '' ? $h($W('format') . ' ' . $wmP['format']) . ' · ' : '' ?><?= $h($wmP['nummer']) ?> · <b class="mc-ab"><?= $h(strtr($W('ab'), ['{preis}' => Werbemittel::euro((int) $wmP['ab_cent'])])) ?></b></p>
      <?php if (($wmP['material'] ?? '') !== '' || ($wmP['lieferung'] ?? '') !== ''): ?>
        <dl class="mc-fakten">
          <?php if ($wmP['material'] !== ''): ?><dt><?= $h($mcT('material')) ?></dt><dd><?= $h($wmP['material']) ?></dd><?php endif; ?>
          <?php if ($wmP['lieferung'] !== ''): ?><dt><?= $h($mcT('lieferung')) ?></dt><dd><?= $h($wmP['lieferung']) ?></dd><?php endif; ?>
        </dl>
      <?php endif; ?>
    </div>
    <?php if ($mcEigen): $mcIst = in_array((int) $wmP['id'], $mcFav, true); ?>
      <form method="post" action="<?= $h($selbst()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="tat" value="wm_favorit"><input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>">
        <button class="mc-stern" aria-pressed="<?= $mcIst ? 'true' : 'false' ?>" aria-label="<?= $h($mcT($mcIst ? 'fav_aria_aus' : 'fav_aria_an', ['{name}' => $wmP['name']])) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $mcIcon['favoriten'] ?></svg><?= $h($mcT($mcIst ? 'fav_gemerkt' : 'fav_merken')) ?></button>
      </form>
    <?php endif; ?>
  </header>

  <div class="mc-raster">
    <?php if ($wmGest):
      $wmBild = $wmNurLesen
        ? 'data:image/jpeg;base64,' . base64_encode(Werbemittel::vorschauBild($p, $wmVl, $wmJetzt['stil'], $sprache, 'email'))
        : $selbst(['wmv' => $wmVl, 'st' => $wmJetzt['stil'], 'vks' => $wmJetzt['sprache'], 'ks' => $wmJetzt['kontakt'], 'tt' => (string) ($wmJetzt['titel'] ?? '')]); ?>
      <figure class="mc-vorschau">
        <img src="<?= $h($wmBild) ?>" <?= $mcQuer ? 'width="720" height="231"' : (str_starts_with($wmVl, 'aufkleber') ? 'width="360" height="360"' : (str_starts_with($wmVl, 'rollup') ? 'width="176" height="420"' : (str_starts_with($wmVl, 'tasse') ? 'width="926" height="360"' : 'width="528" height="360"'))) ?> loading="lazy" decoding="async" id="wm-bild-<?= (int) $wmP['id'] ?>"
             <?php if (!$wmNurLesen): ?>data-muster="<?= $h($selbst(['wmv' => $wmVl, 'st' => '_S_', 'vks' => '_L_', 'ks' => '_K_', 'tt' => '_T_'])) ?>"<?php endif; ?>
             alt="<?= $h(strtr($W('vorschau_alt'), ['{name}' => $wmP['name']])) ?>">
        <figcaption><?= $h($mcT('vorschau')) ?></figcaption>
      </figure>
    <?php endif; ?>

    <div class="mc-schritte">
      <?php if (!$wmNurLesen && $wmGest): $wmMeldung = (string) ($_GET['wm'] ?? ''); ?>
      <?php if (in_array($wmMeldung, ['entwurf', 'frei', 'veraltet', 'zuviel', 'fehler', 'verworfen', 'qr'], true)): ?>
        <p class="wm-meldung<?= in_array($wmMeldung, ['entwurf', 'frei', 'verworfen'], true) ? ' gut' : '' ?>" role="status"><?= $h($W('m_' . $wmMeldung)) ?></p>
      <?php endif; ?>

      <section class="mc-schritt">
        <h4><span>1</span><?= $h($mcT('schritt1')) ?></h4>
        <p class="wm-meta" style="margin:0 0 10px"><?= $h($mcT('s1_satz')) ?></p>
        <form method="post" action="<?= $h($selbst()) ?>#wm-p<?= (int) $wmP['id'] ?>" class="wm-gestalter wm-gestalten" data-bild="wm-bild-<?= (int) $wmP['id'] ?>">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_entwurf">
          <input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>">
          <p class="wm-meta wm-wahl-hinweis" hidden style="margin:0"><?= $h($W('vorschau_wahl')) ?> ↑</p>
          <?php if ($mcBr): ?>
            <fieldset class="mc-motive">
              <legend><?= $h($mcT('motiv_branche')) ?></legend>
              <div class="mc-gruppen" role="group">
                <button type="button" data-flg=""<?= $mcGrpJetzt === '' ? ' aria-pressed="true"' : ' aria-pressed="false"' ?>><?= $h($mcT('gruppe_alle')) ?></button>
                <?php foreach (PartnerFlyer::GRUPPEN as $mcG => $mcGn): if (empty($mcGrp[$mcG])) { continue; } ?>
                  <button type="button" data-flg="<?= $h($mcG) ?>" aria-pressed="<?= $mcG === $mcGrpJetzt ? 'true' : 'false' ?>"><?= $h(explode(' — ', Texte::h($mcGn, $sprache))[0]) ?></button>
                <?php endforeach; ?>
              </div>
              <div class="mc-kacheln mc-branchen">
                <?php foreach ($mcGrp as $mcG => $mcL2) { foreach ($mcL2 as $mcS) { $mcKachel($mcS, Designlinie::von($wmVl, $mcS), $mcG); } } ?>
              </div>
            </fieldset>
          <?php endif; ?>
          <?php if ($mcSt): ?>
            <fieldset class="mc-motive">
              <legend><?= $h($mcBr ? $mcT('motiv_allgemein') : $W('stil')) ?></legend>
              <?php /* Ein Raster für alle Stile, in der Reihenfolge der Linien; die Linie steht klein in der Kachel. */ ?>
              <div class="mc-kacheln mc-stilraster">
                <?php foreach (Designlinie::gruppiert($wmVl, $mcSt) as $mcLi => $mcLs) { foreach ($mcLs as $mcS) { $mcKachel($mcS, $mcLi, '', Texte::h(Texte::MARKETINGCENTER['linien'][$mcLi], $sprache)); } } ?>
              </div>
            </fieldset>
          <?php endif; ?>
          <?php if ($mcTitelDa): $mcTJetzt = WmDruck::titel((string) ($wmJetzt['titel'] ?? '')); ?>
            <fieldset class="mc-titel"<?= WmDruck::hatTitel($wmVl, $wmJetzt['stil']) ? '' : ' hidden' ?> data-titel-stile="<?= $h(implode(' ', array_filter($mcSt, static fn($s) => WmDruck::hatTitel($wmVl, $s)))) ?>">
              <legend><?= $h($mcT('titel')) ?></legend>
              <p class="wm-meta" style="margin:0 0 8px"><?= $h($mcT('titel_satz')) ?></p>
              <div class="mc-titelliste">
                <?php foreach (array_keys(Texte::WM_TITEL) as $mcTk): [$mcT1, $mcT2] = WmDruck::titelZeilen($mcTk, $sprache); ?>
                  <label><input type="radio" name="titel" value="<?= $h($mcTk) ?>"<?= $mcTk === $mcTJetzt ? ' checked' : '' ?>>
                    <span><?= $h($mcT1) ?> <b><?= $h($mcT2) ?></b></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endif; ?>
          <div class="mc-zeile2">
            <fieldset><legend><?= $h($mcQuer ? Texte::h(Texte::PARTNER['vk_sprache'] ?? [], $sprache) : $mcT('sprache')) ?></legend>
              <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $wmL => $wmLn): ?>
                <label><input type="radio" name="sprache" value="<?= $wmL ?>"<?= $wmL === $wmJetzt['sprache'] ? ' checked' : '' ?>> <?= $wmLn ?></label>
              <?php endforeach; ?>
            </fieldset>
            <fieldset><legend><?= $h($mcQuer ? Texte::h(Texte::PARTNER['vk_kontakt'] ?? [], $sprache) : $mcT('kontakt')) ?></legend>
              <?php foreach (PartnerKarten::KONTAKTE as $wmKo): ?>
                <label><input type="radio" name="kontakt" value="<?= $wmKo ?>"<?= $wmKo === $wmJetzt['kontakt'] ? ' checked' : '' ?>> <?= $h(PartnerKarten::kontakt($p, $wmKo)) ?></label>
              <?php endforeach; ?>
            </fieldset>
          </div>
          <button class="knopf<?= $wmSt['entwurf'] || $wmSt['freigegeben'] ? '' : ' haupt' ?>"><?= $h($W('erzeugen')) ?></button>
        </form>
      </section>

      <section class="mc-schritt">
        <h4><span>2</span><?= $h($mcT('schritt2')) ?></h4>
        <?php if (!$wmSt['entwurf'] && !$wmSt['freigegeben']): ?><p class="wm-meta" style="margin:0"><?= $h($mcT('s2_leer')) ?></p><?php endif; ?>
        <?php if ($wmSt['entwurf']): $wmE = $wmSt['entwurf']; ?>
          <form class="wm-entwurf" method="post" action="<?= $h($selbst()) ?>#wm-p<?= (int) $wmP['id'] ?>">
            <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_freigeben">
            <input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>"><input type="hidden" name="entwurf" value="<?= (int) $wmE['id'] ?>">
            <input type="hidden" name="hash" value="<?= $h((string) $wmE['datei_hash']) ?>">
            <b><?= $h($W('entwurf_titel')) ?></b>
            <span><?= $h($wmWahlText($wmE['wahl'])) ?></span>
            <?php $mcFoto($wmE); ?>
            <span class="wm-meta" style="margin:0"><?= $h(strtr($W('mid'), ['{id}' => Werbemittel::marketingId($wmE)])) ?></span>
            <a class="knopf" href="<?= $h($selbst(['wmpdf' => (int) $wmE['id']])) ?>" target="_blank" rel="noopener"><?= $h($W('pdf_ansehen')) ?></a>
            <?php if (!empty($wmE['hat_pf'])): ?>
              <span class="wm-meta" style="margin:0"><?= $h($W('pf_titel')) ?></span>
              <span class="wm-pf"><?php foreach (['vorn', 'hinten'] as $wmS): ?><a href="<?= $h($selbst(['wmpf' => (int) $wmE['id'], 's' => $wmS])) ?>" target="_blank" rel="noopener"><img src="<?= $h($selbst(['wmpf' => (int) $wmE['id'], 's' => $wmS])) ?>" alt="<?= $h($W('pf_' . $wmS)) ?>" width="180" height="108" loading="lazy"></a><?php endforeach; ?></span>
            <?php endif; ?>
            <?php /* QR-Prüfung vor der Produktion (Schritt 8): bestanden → grüner Hinweis; durchgefallen → keine Freigabe. */
              $mcQr = $wmE['qr_ok'] === null ? null : (array) json_decode((string) $wmE['qr_pruefung'], true);
              $mcQrOk = $wmE['qr_ok'] === null || (int) $wmE['qr_ok'] === 1; ?>
            <?php if ($mcQr && $mcQrOk): ?>
              <span class="mc-qr-ok">✓ <?= $h($mcT('qr_ok', ['{mm}' => str_replace('.', $sprache === 'en' ? '.' : ',', (string) round(((float) ($mcQr['mm'] ?? 0)) / 10, 1)), '{link}' => preg_replace('~^https?://~', '', (string) ($mcQr['link'] ?? ''))])) ?></span>
            <?php elseif (!$mcQrOk): ?>
              <span class="mc-qr-fehler" role="alert"><?= $h($mcT('qr_fehler')) ?></span>
            <?php endif; ?>
            <?php if ($mcQrOk): ?>
            <label class="wm-haken"><input type="checkbox" name="geprueft" value="1" required> <span><?= $h($W(!empty($wmE['hat_pf']) ? 'pruef_haken_pf' : 'pruef_haken')) ?></span></label>
            <?php endif; ?>
            <span style="display:flex;gap:8px;flex-wrap:wrap"><?php if ($mcQrOk): ?><button class="knopf haupt"><?= $h($W('freigeben')) ?></button><?php endif; ?>
              <button class="knopf" form="wm-weg-<?= (int) $wmE['id'] ?>"><?= $h($W('verwerfen')) ?></button></span>
          </form>
          <form id="wm-weg-<?= (int) $wmE['id'] ?>" method="post" action="<?= $h($selbst()) ?>#wm-p<?= (int) $wmP['id'] ?>" data-frage="<?= $h($W('verwerfen_frage')) ?>" class="wm-frage" hidden>
            <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_verwerfen">
            <input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>"><input type="hidden" name="entwurf" value="<?= (int) $wmE['id'] ?>">
          </form>
        <?php endif; ?>
        <?php if ($wmSt['freigegeben']): $wmF = $wmSt['freigegeben']; ?>
          <div class="wm-frei">
            <b>✓ <?= $h(strtr($W('frei_titel'), ['{datum}' => Fmt::datum((string) $wmF['freigegeben_am'])])) ?></b>
            <span><?= $h($wmWahlText($wmF['wahl'])) ?></span>
            <a href="<?= $h($selbst(['wmpdf' => (int) $wmF['id']])) ?>" target="_blank" rel="noopener"><?= $h($W('pdf_ansehen')) ?></a>
            <?php $mcFoto($wmF); ?>
            <span class="wm-meta" style="margin:0"><?= $h($W('frei_satz')) ?></span>
            <?php $wmEr = Werbemittel::erfolg((int) $p['id'], (int) $wmF['id']); ?>
            <span class="wm-meta" style="margin:0"><?= $h(strtr($W('mid'), ['{id}' => Werbemittel::marketingId($wmF)])) ?><br>
              <?= $h(strtr($W('erfolg'), ['{scans}' => (string) $wmEr['scans'], '{besucher}' => (string) $wmEr['besucher'], '{anfragen}' => (string) $wmEr['anfragen'], '{abschluesse}' => (string) $wmEr['abschluesse']])) ?></span>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php /* Bestellen — nur mit freigegebener Druckdatei. In der Verwaltungsvorschau gesperrt. */
        $wmDarf = $wmNurLesen || !empty($wmSt['freigegeben']);
        $wmM = (string) ($_GET['wm'] ?? ''); ?>
      <section class="mc-schritt wm-bestellen"<?= $mcNoch ? ' id="wm-bestellen"' : '' ?>>
        <h4><span>3</span><?= $h($mcT('schritt3')) ?></h4>
        <?php if ($mcNoch): ?><p class="wm-meldung" role="status"><?= $h($W('nochmal_satz')) ?></p><?php endif; ?>
        <?php if (!$wmNurLesen && in_array($wmM, ['adresse', 'freigabe_fehlt', 'zuviel_offen', 'nicht_verfuegbar', 'nicht_lieferbar'], true)): ?>
          <p class="wm-meldung" role="alert"><?= $h($W('m_' . $wmM)) ?></p>
        <?php endif; ?>
        <?php if (!$wmDarf): ?>
          <table class="wm-varianten" aria-label="<?= $h($W('je_auflage')) ?>">
            <?php foreach ($wmP['varianten'] as $wmV): ?>
              <tr><td><?= $h($wmV['name']) ?></td><td><?php foreach ($wmV['preise'] as $wmPl => $wmPc): ?><span class="wm-landpreis<?= $wmPl === $wmP['land'] ? '' : ' wm-anderes' ?>"><?= $h(Partner::flagge($wmPl)) ?> <?= $h(Werbemittel::euro((int) $wmPc)) ?></span><?php endforeach; ?></td></tr>
            <?php endforeach; ?>
          </table>
          <p class="wm-meta" style="margin:6px 0 0"><?= $h($W('preis_land')) ?></p>
          <p class="wm-meta mc-gesperrt"><?= $h($W('erst_freigeben')) ?></p>
        <?php else: ?>
        <form<?= $wmNurLesen ? '' : ' method="post" action="' . $h($selbst()) . '"' ?>>
          <fieldset class="wm-bfeld"<?= $wmNurLesen ? ' disabled' : '' ?>>
            <?php if (!$wmNurLesen): ?><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_bestellen"><?php endif; ?>
            <div class="wm-wahl" role="radiogroup" aria-label="<?= $h($W('auflage')) ?>">
              <?php foreach ($wmP['varianten'] as $wmI => $wmV): ?>
                <label><input type="radio" name="variante" value="<?= (int) $wmV['id'] ?>"<?= ($mcNoch ? (int) $wmV['id'] === $wmNochVar : $wmI === 0) ? ' checked' : '' ?> required>
                  <span><?= $h($wmV['name']) ?></span><b><?php foreach ($wmV['preise'] as $wmPl => $wmPc): ?><span class="wm-landpreis<?= $wmPl === $wmP['land'] ? '' : ' wm-anderes' ?>"><?= $h(Partner::flagge($wmPl)) ?> <?= $h(Werbemittel::euro((int) $wmPc)) ?></span><?php endforeach; ?></b></label>
              <?php endforeach; ?>
            </div>
            <p class="wm-unter"><?= $h($W('preis_land')) ?></p>
            <p class="wm-unter"><?= $h($W('lieferadresse')) ?></p>
            <?php foreach ($wmAdressen as $wmAi => $wmA): ?>
              <label class="wm-adr"><input type="radio" name="adresse" value="<?= (int) $wmA['id'] ?>"<?= ($mcNoch ? (int) $wmA['id'] === $wmNochAdr : $wmAi === 0) ? ' checked' : '' ?>>
                <span><?= $h($wmA['name'] . ($wmA['firma'] !== '' ? ' · ' . $wmA['firma'] : '') . ', ' . $wmA['strasse'] . ', ' . $wmA['plz'] . ' ' . $wmA['ort'] . ' (' . $wmA['land'] . ')') ?></span></label>
            <?php endforeach; ?>
            <?php if ($wmAdressen): ?><label class="wm-adr"><input type="radio" name="adresse" value="neu"<?= $mcNoch && $wmNochAdr === 0 ? ' checked' : '' ?>> <span><?= $h($W('neue_adresse')) ?></span></label><?php else: ?><input type="hidden" name="adresse" value="neu"><?php endif; ?>
            <div class="wm-neu"<?= $wmAdressen ? ' data-nur-neu="1"' : '' ?>>
              <label><?= $h($W('a_name')) ?><input name="name" autocomplete="name" value="<?= $h((string) ($wmNochNeu['name'] ?? ($wmAdressen ? '' : (string) ($p['name'] ?? '')))) ?>"></label>
              <label><?= $h($W('a_firma')) ?><input name="firma" autocomplete="organization" value="<?= $h((string) ($wmNochNeu['firma'] ?? '')) ?>"></label>
              <label class="wm-breit"><?= $h($W('a_strasse')) ?><input name="strasse" autocomplete="street-address" value="<?= $h((string) ($wmNochNeu['strasse'] ?? '')) ?>"></label>
              <label><?= $h($W('a_plz')) ?><input name="plz" autocomplete="postal-code" inputmode="numeric" value="<?= $h((string) ($wmNochNeu['plz'] ?? '')) ?>"></label>
              <label><?= $h($W('a_ort')) ?><input name="ort" autocomplete="address-level2" value="<?= $h((string) ($wmNochNeu['ort'] ?? '')) ?>"></label>
              <label><?= $h($W('a_land')) ?><select name="land" autocomplete="country">
                <?php foreach ($wmLaender as $wmLc => $wmLn): ?><option value="<?= $wmLc ?>"<?= $wmLc === (string) ($wmNochNeu['land'] ?? $wmP['land']) ? ' selected' : '' ?>><?= $h($wmLn) ?></option><?php endforeach; ?>
              </select></label>
              <label><?= $h($W('a_telefon')) ?><input name="telefon" type="tel" autocomplete="tel" value="<?= $h((string) ($wmNochNeu['telefon'] ?? '')) ?>"></label>
            </div>
            <label class="wm-haken"><input type="checkbox" name="verbindlich" value="1" required> <span><?= $h($W('verbindlich')) ?></span></label>
            <button class="knopf haupt"><?= $h($W($wmZahlweg === 'stripe' ? 'knopf_stripe' : 'knopf_anfrage')) ?></button>
            <p class="wm-meta" style="margin:0"><?= $h($W($wmZahlweg === 'stripe' ? 'stripe_satz' : 'anfrage_satz')) ?></p>
          </fieldset>
        </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</article>
