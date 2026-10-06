<?php
/* ==========================================================================
   ERGEBNISSE im Command Center (Phase 5, 05.10.2026; Spezifikation 28–31, 55).
   Oben die vier Kennzahlen wie auf der Startseite, darunter das Geld in vier
   Stufen (PartnerGeld), das Level mit Fortschritt, was aus offenen Angeboten
   erwartet wird, die Provisionen und die Auszahlungen — nie ein Kundenname.
   Was der alte Bereich darüber hinaus kann (Zahlen und Vergleich, Jahres-
   übersicht, Auszahlungsweg), bleibt dort und ist unten verlinkt.
   Erwartet aus partner_cc.php: $p, $sprache, $h, $selbst, $c, $C, $ccKacheln, $ccZahl, $ccBereich.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerGeld.php';
require_once dirname(__DIR__) . '/src/PartnerWege.php';
$G = Texte::PARTNER_GELD;
$g = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sprache);
$gU = PartnerGeld::uebersicht($p);
$gE = PartnerGeld::erwartet($p);
$gL = PartnerGeld::liste((int) $p['id'], 200);
$gA = Db::all('SELECT id, nummer, betrag_cents, created_at FROM partner_auszahlungen WHERE partner_id = ? ORDER BY id DESC LIMIT 24', [(int) $p['id']]);
$gS = Partner::satzFuer($p);
$gSt = Partner::stufeStand($p);
$gWeg = $gU['weg'] !== null ? (PartnerWege::WEGE[$gU['weg']] ?? $gU['weg']) : null;
$gProz = static fn(int $bp): string => Partner::satzWort(['art' => 'prozent', 'wert' => $bp], true);
$gHinweis = [
    'erwartet'   => $gU['stufen']['erwartet']['n'] > 0 ? $g($G['h_erwartet'], ['{n}' => (string) $gU['stufen']['erwartet']['n']]) : $g($G['h_erwartet0']),
    'bestaetigt' => $gU['stufen']['bestaetigt']['n'] === 0 ? $g($G['h_bestaetigt0'])
                  : ($gU['naechste_frei'] !== null ? $g($G['h_bestaetigt'], ['{datum}' => Fmt::datum($gU['naechste_frei'])]) : $g($G['h_bestaetigt_f'])),
    'auszahlbar' => $gWeg === null ? $g($G['h_keinweg'])
                  : ($gU['automatisch'] ? $g($G['h_auto'], ['{weg}' => $gWeg]) . ' · ' . $g($G['h_mindest'], ['{min}' => Fmt::geld($gU['mindest_cents'])])
                  : $g($G['h_hand'], ['{weg}' => $gWeg]) . ' · ' . $g($G['h_mindest'], ['{min}' => Fmt::geld($gU['mindest_cents'])])),
    'ausgezahlt' => $g($G['h_ausgezahlt']),
];
?>
<main id="cc-start" tabindex="-1">
  <div class="cc-hallo cc-auf">
    <h1><?= $h($g($G['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($g($G['satz'])) ?></p>
  </div>

  <section class="cc-kz4 cc-auf z2" aria-label="<?= $h($c($C['kz4_aria'])) ?>" data-tour="eg-zahlen">
    <?php foreach ($ccKacheln as $kk => [$wert, $anker, $zusatz]):
      $txt = $kk === 'provision' ? Fmt::geld($wert) : $ccZahl($wert); ?>
      <a class="cc-zahl<?= $kk === 'provision' && $wert > 0 ? ' gold' : '' ?><?= $wert === 0 ? ' null' : '' ?>" href="<?= $h($kk === 'provision' ? '#geld' : $ccBereich($anker)) ?>" data-cc-zahl="<?= $h($kk) ?>">
        <span class="l"><?= $h($c($C['kz4'][$kk][0])) ?></span>
        <b><?= $h($txt) ?></b>
        <small><?= $h($zusatz !== '' ? $zusatz : $c($C['kz4'][$kk][1])) ?></small>
      </a>
    <?php endforeach; ?>
  </section>

  <?php /* Vier Stufen, in dieser Reihenfolge — eine Liste, damit Vorleser sie als Weg lesen. */ ?>
  <ol class="cc-geld cc-auf z2" id="geld" aria-label="<?= $h($g($G['geld_aria'])) ?>">
    <?php foreach (PartnerGeld::STUFEN as $gi => $gk): $gw = $gU['stufen'][$gk]; ?>
      <li class="cc-geld-<?= $h($gk) ?><?= $gw['cents'] === 0 ? ' null' : '' ?>">
        <span class="nr" aria-hidden="true"><?= $gi + 1 ?></span>
        <span class="l"><?= $h($g($G['stufen'][$gk])) ?></span>
        <b><?= $h(Fmt::geld($gw['cents'])) ?></b>
        <small><?= $h($gHinweis[$gk]) ?></small>
        <?php if ($gk === 'auszahlbar' && $gw['cents'] > 0 && $gU['netto_auszahlbar'] !== $gw['cents']): ?>
          <small><?= $h($g($G['netto'], ['{betrag}' => Fmt::geld($gU['netto_auszahlbar'])])) ?></small>
        <?php endif; ?>
        <?php if ($gk === 'auszahlbar' && $gWeg === null): ?><a class="knopf" href="<?= $h($selbst() . '#wege') ?>"><?= $h($g($G['weg_setzen'])) ?> →</a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <div class="cc-raster">
    <section class="cc-karte cc-level cc-auf z3<?= $gSt['stufe'] !== null ? ' lv-' . $h($gSt['stufe']) : '' ?>" aria-labelledby="cc-lv-t" data-tour="eg-level">
      <p class="cc-auge" id="cc-lv-t"><?= $h($g($G['lv_titel'])) ?></p>
      <?php if ($gSt['stufe'] === null): ?>
        <p class="cc-lv-satz"><?= $h($g($G['lv_aus'], ['{satz}' => Partner::satzWort($gS)])) ?></p>
      <?php else: ?>
        <p class="cc-lv-name"><?= $h($T('st_' . $gSt['stufe'])) ?></p>
        <p class="cc-lv-satz"><?= $h($g($G['lv_satz'], ['{satz}' => Partner::satzWort($gS, true), '{n}' => (string) $gSt['verkaeufe']])) ?></p>
        <?php if ($gSt['naechste'] !== null): ?>
          <div class="cc-lv-balken" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $gSt['anteil'] ?>"
               aria-label="<?= $h($T('st_' . $gSt['naechste'])) ?>"><i style="width:<?= max(3, (int) $gSt['anteil']) ?>%"></i></div>
          <p class="hilfe"><?= $h($gSt['naechste'] === 'platin'
              ? $g($G['lv_naechst_platin'], ['{fehlen}' => (string) $gSt['fehlen']])
              : $g($G['lv_naechst_satz'], ['{fehlen}' => (string) $gSt['fehlen'], '{naechste}' => $T('st_' . $gSt['naechste']),
                   '{satz}' => Partner::satzWort((array) $gSt['naechster_satz'] + ['art' => 'prozent', 'wert' => 0], true)])) ?></p>
        <?php else: ?>
          <p class="hilfe"><?= $h($g($G['lv_oben'])) ?></p>
        <?php endif; ?>
        <ol class="cc-lv-leiter">
          <?php foreach (['starter' => [0, Partner::zahl('partner_standard_wert')], 'silber' => [Partner::zahl('partner_silber_ab'), Partner::zahl('partner_silber_bp')],
                          'gold' => [Partner::zahl('partner_gold_ab'), Partner::zahl('partner_gold_bp')], 'platin' => [Partner::zahl('partner_platin_ab'), null]] as $lk => [$lab, $lbp]): ?>
            <li<?= $lk === $gSt['stufe'] ? ' aria-current="true"' : '' ?>><b><?= $h($T('st_' . $lk)) ?></b>
              <span><?= $h($lab === 0 ? $g($G['lv_start']) : $g($G['lv_ab'], ['{n}' => (string) $lab])) ?></span>
              <span><?= $h($lbp === null ? $g($G['lv_platin_satz']) : $gProz((int) $lbp)) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <div class="cc-lv-vorteile<?= $gSt['stufe'] === 'platin' ? ' an' : '' ?>">
          <p><b><?= $h($g($G['lv_vorteile'])) ?></b></p>
          <ul><?php foreach ($G['lv_v'] as $lv): ?><li><?= $h($g($lv)) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
    </section>

    <section class="cc-auf z3" aria-labelledby="cc-erw-t">
      <h2 class="cc-titel" id="cc-erw-t"><?= $h($g($G['erw_titel'])) ?></h2>
      <?php if (!$gE): ?>
        <p class="cc-leer"><?= $h($g($G['h_erwartet0'])) ?></p>
      <?php else: ?>
        <ul class="cc-mat">
          <?php foreach ($gE as $e): ?>
            <li><span><?= $h($g($G[$e['status'] === 'angenommen' ? 'erw_angenommen' : 'erw_gesendet'], ['{datum}' => Fmt::datum($e['datum'])])) ?></span>
              <b><?= $h(Fmt::geld($e['cents'])) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="hilfe" style="margin-top:10px"><?= $h($g($G['erw_hilfe'])) ?></p>
    </section>
  </div>

  <section class="cc-auf z3" aria-labelledby="cc-pv-t" style="margin-top:22px" data-tour="eg-provisionen">
    <h2 class="cc-titel" id="cc-pv-t"><?= $h($g($G['liste'])) ?></h2>
    <?php if (!$gL): ?>
      <p class="cc-leer"><?= $h($g($G['leer'])) ?></p>
    <?php else:
      $gZeile = static function (array $z) use ($h, $g, $G, $T): string {
          $stufe = $z['stufe'] === '' ? $g($G['storniert']) : $g($G['stufen'][$z['stufe']]);
          $frei = $z['status'] === 'wartet' && $z['frei_ab'] !== null ? '<small>' . $h($g($G['frei_ab'], ['{datum}' => Fmt::datum($z['frei_ab'])])) . '</small>' : '';
          return '<tr class="' . ($z['stufe'] === '' ? 'weg' : 'st-' . $h($z['stufe'])) . '"><th scope="row">' . $h($z['nr']) . '<small>' . $h(Fmt::datum($z['datum'])) . '</small></th>'
              . '<td data-l="' . $h($g($G['sp']['leistung'])) . '">' . $h($T('a_' . $z['art']) ?: $z['art']) . '<small>' . ($z['satz'] !== '' ? $h($z['satz']) . ' · ' : '') . $h(Fmt::geld($z['basis_cents'])) . '</small></td>'
              . '<td data-l="' . $h($g($G['sp']['betrag'])) . '" class="r"><b>' . $h(Fmt::geld($z['cents'])) . '</b></td>'
              . '<td data-l="' . $h($g($G['sp']['stufe'])) . '"><span class="cc-stufe">' . $h($stufe) . '</span>' . $frei . '</td></tr>';
      }; ?>
      <div class="cc-ktab-rahmen">
        <table class="cc-ktab cc-pvtab">
          <thead><tr><th scope="col"><?= $h($g($G['sp']['nr'])) ?></th><th scope="col"><?= $h($g($G['sp']['leistung'])) ?></th>
            <th scope="col"><?= $h($g($G['sp']['betrag'])) ?></th><th scope="col"><?= $h($g($G['sp']['stufe'])) ?></th></tr></thead>
          <tbody><?php foreach (array_slice($gL, 0, 8) as $z) { echo $gZeile($z); } ?></tbody>
        </table>
      </div>
      <?php if (count($gL) > 8): ?>
        <details class="cc-kl-alle"><summary><?= $h($g($G['mehr'], ['{n}' => (string) count($gL)])) ?></summary>
          <div class="cc-ktab-rahmen"><table class="cc-ktab cc-pvtab"><tbody><?php foreach (array_slice($gL, 8) as $z) { echo $gZeile($z); } ?></tbody></table></div>
        </details>
      <?php endif; ?>
    <?php endif; ?>
    <p class="hilfe" style="margin-top:8px"><?= $h($T('privat')) ?></p>
  </section>

  <?php if ($gA): ?>
    <section class="cc-auf z3" aria-labelledby="cc-az-t" style="margin-top:22px" data-tour="eg-auszahlung">
      <h2 class="cc-titel" id="cc-az-t"><?= $h($g($G['ausz_titel'])) ?></h2>
      <ul class="cc-mat">
        <?php foreach ($gA as $a): ?>
          <li><span><?= $h(Fmt::datum((string) $a['created_at'])) ?> · <?= $h((string) $a['nummer']) ?></span>
            <span><b><?= $h(Fmt::geld((int) $a['betrag_cents'])) ?></b> · <a href="<?= $h($selbst(['beleg' => (int) $a['id']])) ?>" style="color:var(--gold)"><?= $h($g($G['beleg'])) ?></a></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <nav class="cc-schnell cc-auf z3" style="margin-top:22px" aria-label="<?= $h($g($G['weiter_titel'])) ?>">
    <a href="<?= $h($selbst() . '#zahlen') ?>"><?= $h($g($G['w_zahlen'])) ?> →</a>
    <a href="<?= $h($selbst() . '#wege') ?>"><?= $h($g($G['w_weg'])) ?> →</a>
    <a href="<?= $h($selbst() . '#sofort') ?>"><?= $h($g($G['w_jahr'])) ?> →</a>
  </nav>
</main>
