<?php
/* ==========================================================================
   MARKETING im Command Center (Phase 3, 05.10.2026; Spezifikation Punkt 11–14,
   24–26; Uwe: fünf Bereiche, zwölf Branchen, Academy als Quelle der Einwände,
   Mediathek mit Tabelle). Fünf Bereiche, einer je Bildschirm:
     assistent — vier Fragen, drei fertige Inhalte (PartnerMediathek::vorschlaege)
     mediathek — alle Karten, Filter nach Zweck, Branche, Kanal
     kampagnen — eigene Kampagnen, neue Kampagne, QR-Center
     verkauf   — Bedarf, Einwände, Gespräch, Anschreiben (Inhalte aus der Academy)
     check     — Website-Schnellcheck (PartnerCheck) mit Ergebnis und Weitergabe
   Erwartet aus partner_cc.php: $p, $sprache, $h, $selbst, $start, $c, $C, $ccPf (Profil),
   $ccMkMeldung (Fehler des Schnellchecks).
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerMediathek.php';
require_once dirname(__DIR__) . '/src/PartnerBranche.php';
require_once dirname(__DIR__) . '/src/PartnerKampagne.php';
$M = Texte::PARTNER_MKT;
$m = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$mTeile = array_keys($M['teile']);
$mTeil = in_array((string) ($_GET['teil'] ?? ''), $mTeile, true) ? (string) $_GET['teil'] : 'assistent';
$mUrl = static fn(array $x = []): string => $selbst(['cc' => 1, 'marketing' => 1] + $x);
/* Ein GET-Formular braucht die Parameter der Seite als versteckte Felder (Zugang, cc, marketing, teil). */
$mGetFelder = static function (array $x) use ($mUrl, $h): string {
    parse_str((string) parse_url($mUrl($x), PHP_URL_QUERY), $q);
    $aus = '';
    foreach ($q as $k => $v) { if (is_string($v)) { $aus .= '<input type="hidden" name="' . $h($k) . '" value="' . $h($v) . '">'; } }
    return $aus;
};
/* Vorbelegung aus dem Marketingprofil: Branche, Ort, bevorzugter Weg, Ziel. */
$mProfilKanal = ['persoenlich' => 'persoenlich', 'whatsapp' => 'whatsapp', 'social' => 'instagram', 'telefon' => 'persoenlich', 'druck' => 'druck', 'email' => 'email'];
$mProfilZweck = ['neue_kunden' => 'neukunden', 'anfragen' => 'neukunden', 'bekanntheit' => 'vorstellung', 'lokal' => 'neukunden', 'social' => 'vorstellung'];
$mKampZiel = ['neukunden' => 'neue_kunden', 'vertrauen' => 'bekanntheit', 'angebot' => 'anfragen', 'anlass' => 'social', 'vorstellung' => 'bekanntheit', 'referenzen' => 'bekanntheit'];
$mPf = $ccPf ?? PartnerCommand::profil($p);
$mKarte = static function (array $k, string $nr) use ($h, $m, $M, $selbst): string {
    $txtId = 'mt-' . preg_replace('~[^a-z0-9-]~', '', $nr);
    $wa = $k['text'] !== '' && $k['anker'] === '' && (!$k['kanaele'] || array_intersect(['whatsapp', 'persoenlich'], $k['kanaele']));
    $tg = $k['text'] !== '' && $k['anker'] === '' && $k['link'] !== '' && in_array('telegram', $k['kanaele'], true);
    // E-Mail nur mit Betreff (Pflichtregel: nie eine Mail aus dem Partnerbereich ohne Betreff).
    $mail = $k['text'] !== '' && $k['anker'] === '' && in_array('email', $k['kanaele'], true) && trim((string) $k['betreff']) !== '';
    ob_start(); ?>
    <li class="cc-mt-karte z-<?= $h($k['zweck']) ?>" data-mt="<?= $h($k['id']) ?>">
      <?php if (!empty($k['bild'])): ?><img class="cc-mt-bild" src="<?= $h($k['bild']) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
      <div class="cc-mt-kopf"><span class="cc-mt-zweck"><?= $h($m($M['zwecke'][$k['zweck']])) ?></span>
        <?php if ($k['quelle'] === 'eigen' && $k['anker'] === ''): ?><span class="cc-mt-neu"><?= $h($m($M['mt_vecom'])) ?></span><?php endif; ?>
        <?php if ($k['kanaele']): ?><span class="cc-mt-kanaele"><?= $h(implode(' · ', array_map(static fn($x) => $m($M['kanaele'][$x]), array_slice($k['kanaele'], 0, 3)))) ?></span><?php endif; ?></div>
      <h3><?= $h($k['titel']) ?></h3>
      <?php if ($k['anker'] !== ''): ?>
        <p class="cc-mt-satz"><?= $h($k['text']) ?></p>
      <?php elseif ($k['text'] !== ''): ?>
        <?php if (trim((string) $k['betreff']) !== ''): ?><p class="cc-mt-betreff"><b><?= $h($m($M['mt_betreff'])) ?>:</b> <?= $h($k['betreff']) ?></p><?php endif; ?>
        <textarea class="cc-mt-text" id="<?= $h($txtId) ?>" rows="5" readonly aria-label="<?= $h($m($M['mt_text_aria'])) ?>"><?= $h($k['text']) ?></textarea>
      <?php endif; ?>
      <div class="cc-mt-knoepfe">
        <?php if ($k['anker'] !== ''): ?>
          <a class="knopf" href="<?= $h($selbst() . '#' . $k['anker']) ?>"><?= $h($m($M['mt_oeffnen'])) ?> →</a>
        <?php else: ?>
          <?php if ($k['text'] !== ''): ?><button class="knopf" type="button" data-cc-kopie="<?= $h($txtId) ?>" data-fertig="<?= $h($m($M['mt_kopiert'])) ?>"><?= $h($m($M['mt_kopieren'])) ?></button><?php endif; ?>
          <?php if ($wa): ?><a class="knopf" href="https://wa.me/?text=<?= rawurlencode($k['text']) ?>" target="_blank" rel="noopener"><?= $h($m($M['mt_wa'])) ?></a><?php endif; ?>
          <?php if ($tg): ?><a class="knopf" href="https://t.me/share/url?url=<?= rawurlencode($k['link']) ?>&amp;text=<?= rawurlencode(trim(str_replace($k['link'], '', $k['text']))) ?>" target="_blank" rel="noopener"><?= $h($m($M['mt_tg'])) ?></a><?php endif; ?>
          <?php if ($mail): ?><a class="knopf" href="mailto:?subject=<?= rawurlencode((string) $k['betreff']) ?>&amp;body=<?= rawurlencode($k['text']) ?>"><?= $h($m($M['kanaele']['email'])) ?></a><?php endif; ?>
          <?php if (!empty($k['bild'])): ?><a class="knopf" href="<?= $h($k['bild'] . '&dl=1') ?>" download><?= $h($m($M['mt_bild'])) ?></a><?php endif; ?>
          <?php if (!empty($k['mehr'])): ?><a class="cc-mt-mehr" href="<?= $h($selbst() . '#' . $k['mehr']) ?>"><?= $h($m($M['sk_tag_mehr'])) ?> →</a><?php endif; ?>
        <?php endif; ?>
      </div>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<main id="cc-start" tabindex="-1" class="cc-marketing">
  <div class="cc-seitenkopf cc-auf">
    <h1><?= $h($m($M['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($m($M['satz'])) ?></p>
  </div>

  <nav class="cc-unterleiste cc-auf z2" aria-label="<?= $h($m($M['teile_aria'])) ?>">
    <?php foreach ($mTeile as $t): ?>
      <a href="<?= $h($mUrl(['teil' => $t])) ?>"<?= $mTeil === $t ? ' aria-current="page"' : '' ?>><?= $h($m($M['teile'][$t])) ?></a>
    <?php endforeach; ?>
  </nav>

<?php if ($mTeil === 'assistent'):
  $aB = PartnerBranche::von((string) ($_GET['branche'] ?? ($mPf['branchen'][0] ?? ''))) ?: 'andere';
  $aR = mb_substr(trim((string) ($_GET['region'] ?? $mPf['ort'])), 0, 60);
  // Kanal aus dem Profil: der erste digitale Weg, den der Partner angegeben hat (persönlich und Telefon zuletzt).
  $aPfK = 'whatsapp';
  foreach (['whatsapp', 'social', 'email', 'druck', 'persoenlich', 'telefon'] as $w) { if (in_array($w, $mPf['wege'], true)) { $aPfK = $mProfilKanal[$w]; break; } }
  $aK = in_array((string) ($_GET['kanal'] ?? ''), PartnerMediathek::KANAELE, true) ? (string) $_GET['kanal'] : $aPfK;
  $aZ = in_array((string) ($_GET['ziel'] ?? ''), PartnerMediathek::ZWECKE, true) ? (string) $_GET['ziel'] : ($mProfilZweck[$mPf['ziel']] ?? 'neukunden');
  $aV = PartnerMediathek::vorschlaege($p, $sprache, $aB, $aK, $aZ, 3);
  $aBn = PartnerBranche::name($aB, $sprache); $aKn = $m($M['kanaele'][$aK]); ?>
  <section class="cc-mt-assistent cc-auf z2" aria-labelledby="cc-as-t">
    <h2 id="cc-as-t" class="cc-as-titel"><?= $h($m($M['as_titel'])) ?></h2>
    <p class="hilfe" style="margin:0 0 12px"><?= $h($m($M['as_satz'])) ?></p>
    <?php /* Erst die drei Inhalte, die vier Fragen zugeklappt darüber: vorbelegt aus dem Profil, ein Tipp ändert sie.
             Offen nur, solange das Profil nicht fertig ist oder gerade gewählt wurde. */ ?>
    <details class="cc-karte cc-as-wahl"<?= !$mPf['fertig'] || isset($_GET['branche']) ? ' open' : '' ?>>
      <summary><span><?= $h($m($M[$aR !== '' ? 'as_ergebnis' : 'as_ergebnis_ohne'], ['{branche}' => $aBn, '{region}' => $aR, '{kanal}' => $aKn])) ?></span>
        <b><?= $h($m(Texte::PARTNER_CC['pf_aendern'])) ?></b></summary>
      <form method="get" action="/partner.php" class="cc-felder cc-as-form">
        <?= $mGetFelder(['teil' => 'assistent']) ?>
        <label class="cc-feld"><span><?= $h($m($M['as_branche'])) ?></span><select name="branche">
          <?php foreach (PartnerBranche::auswahl($sprache) as $bk => $bn): ?><option value="<?= $h($bk) ?>"<?= $aB === $bk ? ' selected' : '' ?>><?= $h($bn) ?></option><?php endforeach; ?></select></label>
        <label class="cc-feld"><span><?= $h($m($M['as_region'])) ?></span><input name="region" maxlength="60" value="<?= $h($aR) ?>" placeholder="<?= $h($m($M['as_region_ph'])) ?>" autocomplete="address-level2"></label>
        <label class="cc-feld"><span><?= $h($m($M['as_kanal'])) ?></span><select name="kanal">
          <?php foreach (PartnerMediathek::KANAELE as $kk): ?><option value="<?= $h($kk) ?>"<?= $aK === $kk ? ' selected' : '' ?>><?= $h($m($M['kanaele'][$kk])) ?></option><?php endforeach; ?></select></label>
        <label class="cc-feld"><span><?= $h($m($M['as_ziel'])) ?></span><select name="ziel">
          <?php foreach (PartnerMediathek::ZWECKE as $zz): ?><option value="<?= $h($zz) ?>"<?= $aZ === $zz ? ' selected' : '' ?>><?= $h($m($M['as_ziele'][$zz])) ?></option><?php endforeach; ?></select></label>
        <div class="cc-feld breit"><button class="knopf" type="submit"><?= $h($m($M['as_los'])) ?></button></div>
      </form>
    </details>
  </section>

  <section class="cc-auf z3" aria-label="<?= $h($m($M['as_titel'])) ?>">
    <?php if ($aR !== ''): ?><p class="cc-wenig" style="margin:0 0 12px"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6v.2"/></svg><span><?= $h($m($M['as_tipp_region'], ['{region}' => $aR])) ?></span></p><?php endif; ?>
    <ul class="cc-mt-liste">
      <?php foreach ($aV as $i => $k): ?><?= $mKarte($k, 'as' . $i) ?><?php endforeach; ?>
    </ul>
    <div class="cc-fuss" style="margin-top:14px">
      <a class="knopf haupt" href="<?= $h($selbst(['cc' => 1, 'kampagne' => 'neu', 'ziel' => $mKampZiel[$aZ], 'branche' => $aB, 'region' => $aR])) ?>"><?= $h($m($M['as_kampagne'])) ?> →</a>
      <a class="knopf" href="<?= $h($mUrl(['teil' => 'mediathek', 'zweck' => $aZ, 'branche' => $aB, 'kanal' => $aK])) ?>"><?= $h($m($M['as_mehr'])) ?></a>
    </div>
  </section>

<?php elseif ($mTeil === 'mediathek'):
  $fZ = in_array((string) ($_GET['zweck'] ?? ''), PartnerMediathek::ZWECKE, true) ? (string) $_GET['zweck'] : '';
  $fB = PartnerBranche::von((string) ($_GET['branche'] ?? ''));
  $fK = in_array((string) ($_GET['kanal'] ?? ''), PartnerMediathek::KANAELE, true) ? (string) $_GET['kanal'] : '';
  $fL = PartnerMediathek::karten($p, $sprache, ['zweck' => $fZ, 'branche' => $fB, 'kanal' => $fK]); ?>
  <form method="get" action="/partner.php" class="cc-mt-filter cc-auf z2" data-cc-auto>
    <?= $mGetFelder(['teil' => 'mediathek']) ?>
    <div class="cc-chips" role="radiogroup" aria-label="<?= $h($m($M['as_ziel'])) ?>">
      <label class="cc-chip"><input type="radio" name="zweck" value=""<?= $fZ === '' ? ' checked' : '' ?>><span><?= $h($m($M['mt_alle'])) ?></span></label>
      <?php foreach (PartnerMediathek::ZWECKE as $zz): ?><label class="cc-chip"><input type="radio" name="zweck" value="<?= $h($zz) ?>"<?= $fZ === $zz ? ' checked' : '' ?>><span><?= $h($m($M['zwecke'][$zz])) ?></span></label><?php endforeach; ?>
    </div>
    <div class="cc-zeile">
      <label class="cc-feld"><span><?= $h($m($M['mt_branche'])) ?></span><select name="branche"><option value=""><?= $h($m($M['mt_alle_branchen'])) ?></option>
        <?php foreach (PartnerBranche::auswahl($sprache) as $bk => $bn): ?><option value="<?= $h($bk) ?>"<?= $fB === $bk ? ' selected' : '' ?>><?= $h($bn) ?></option><?php endforeach; ?></select></label>
      <label class="cc-feld"><span><?= $h($m($M['mt_kanal'])) ?></span><select name="kanal"><option value=""><?= $h($m($M['mt_alle_kanaele'])) ?></option>
        <?php foreach (PartnerMediathek::KANAELE as $kk): ?><option value="<?= $h($kk) ?>"<?= $fK === $kk ? ' selected' : '' ?>><?= $h($m($M['kanaele'][$kk])) ?></option><?php endforeach; ?></select></label>
      <noscript><button class="knopf" type="submit"><?= $h($m($M['mt_filtern'])) ?></button></noscript>
    </div>
  </form>
  <p class="hilfe cc-auf z3" role="status" style="margin:0 0 10px"><?= $h($m($M['mt_n'][count($fL) === 1 ? 0 : 1], ['{n}' => (string) count($fL)])) ?></p>
  <?php if (!$fL): ?>
    <p class="cc-leer cc-auf z3"><?= $h($m($M['mt_leer'])) ?></p>
  <?php else: ?>
    <ul class="cc-mt-liste cc-auf z3">
      <?php foreach ($fL as $i => $k): ?><?= $mKarte($k, 'l' . $i) ?><?php endforeach; ?>
    </ul>
  <?php endif; ?>

<?php elseif ($mTeil === 'kampagnen'):
  $kL = PartnerKampagne::liste((int) $p['id']); $KK = Texte::PARTNER_KAMPAGNE; ?>
  <section class="cc-auf z2" aria-labelledby="cc-mk-t">
    <div class="cc-titelzeile"><h2 class="cc-titel" id="cc-mk-t"><?= $h($m($KK['liste'])) ?></h2>
      <a class="knopf haupt" href="<?= $h($selbst(['cc' => 1, 'kampagne' => 'neu'])) ?>">+ <?= $h($m($KK['neu'])) ?></a></div>
    <?php if (!$kL): ?>
      <p class="cc-leer"><?= $h($m($KK['leer'])) ?></p>
    <?php else: ?>
      <ul class="cc-kliste">
        <?php foreach ($kL as $kk): $kz = PartnerKampagne::zahlen((int) $p['id'], (int) $kk['id']) ?? []; ?>
          <li><a href="<?= $h($selbst(['cc' => 1, 'kampagne' => (int) $kk['id']])) ?>">
            <span><b><?= $h((string) $kk['name']) ?></b><small><?= $h(PartnerKampagne::nummer($kk)) ?> · <span class="cc-status s-<?= $h((string) $kk['status']) ?>"><?= $h($m($KK['status'][(string) $kk['status']])) ?></span></small></span>
            <span class="cc-kz"><b><?= (int) ($kz['scans'] ?? 0) + (int) ($kz['klicks'] ?? 0) ?></b><small><?= $h($m($KK['kz'])) ?></small></span></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <nav class="cc-schnell cc-auf z3" style="margin-top:18px">
    <a href="<?= $h($selbst(['cc' => 1, 'qr' => 1])) ?>"><?= $h($m($KK['qr_alle'])) ?> →</a>
  </nav>

<?php elseif ($mTeil === 'verkauf'):
  require_once dirname(__DIR__) . '/src/Academy.php';
  $vE = []; try { $vE = (array) (Academy::inhalte($sprache)['einwaende'] ?? []); } catch (Throwable $e) { $vE = []; }
  $vAuf = ['it' => '«', 'de' => '„', 'en' => '“'][$sprache] ?? '„'; $vZu = ['it' => '»', 'de' => '“', 'en' => '”'][$sprache] ?? '“'; ?>
  <p class="cc-lead cc-auf" style="margin:0 0 18px"><?= $h($m($M['vk_satz'])) ?></p>
  <div class="cc-raster">
    <section class="cc-auf z2" aria-labelledby="cc-vk-v">
      <h2 class="cc-titel" id="cc-vk-v"><?= $h($m($M['vk_vorher'])) ?></h2>
      <ul class="cc-ziele">
        <li><a class="cc-ziel" href="<?= $h($start(['ak' => 'bedarf'])) ?>"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 9a3 3 0 1 1 4.5 2.6c-.9.5-1.5 1.2-1.5 2.4"/><path d="M12 17.5v.2"/><circle cx="12" cy="12" r="9"/></svg></i>
          <span><b><?= $h($m($M['vk_bedarf'])) ?></b><small><?= $h($m($M['vk_bedarf_satz'])) ?></small></span></a></li>
        <li><a class="cc-ziel" href="<?= $h($start(['ak' => 'finder'])) ?>"><i aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3.5" width="16" height="17" rx="2.5"/><path d="M8 9l1.6 1.6L12.5 7.7"/><path d="M8 15l1.6 1.6 2.9-2.9"/><path d="M15 9.5h2M15 15.5h2"/></svg></i>
          <span><b><?= $h($m($M['vk_finder'])) ?></b><small><?= $h($m($M['vk_finder_satz'])) ?></small></span></a></li>
      </ul>
    </section>
    <section class="cc-auf z2" aria-labelledby="cc-vk-e">
      <h2 class="cc-titel" id="cc-vk-e"><?= $h($m($M['vk_einwaende'])) ?></h2>
      <?php if ($vE): ?>
        <ul class="cc-kliste cc-einwaende">
          <?php foreach (array_slice($vE, 0, 6) as $e): ?>
            <li><a href="<?= $h($start(['ak' => 'einwaende', 'e' => (string) $e['slug']])) ?>"><span><b><?= $h($vAuf . (string) $e['satz'] . $vZu) ?></b></span><span aria-hidden="true">→</span></a></li>
          <?php endforeach; ?>
        </ul>
        <p style="margin:10px 0 0"><a class="cc-mt-mehr" href="<?= $h($start(['ak' => 'einwaende'])) ?>"><?= $h($m($M['vk_alle'], ['{n}' => (string) count($vE)])) ?> →</a></p>
      <?php endif; ?>
    </section>
    <section class="cc-auf z3" aria-labelledby="cc-vk-g">
      <h2 class="cc-titel" id="cc-vk-g"><?= $h($m($M['vk_gespraech'])) ?></h2>
      <nav class="cc-schnell">
        <a href="<?= $h($selbst() . '#leitfaden') ?>"><?= $h($m($M['vk_leitfaden'])) ?></a>
        <a href="<?= $h($selbst() . '#anrufliste') ?>"><?= $h($m($M['vk_anrufliste'])) ?></a>
        <a href="<?= $h($start(['ak' => 'jetzt'])) ?>"><?= $h($m($M['vk_jetzt'])) ?></a>
      </nav>
    </section>
    <section class="cc-auf z3" aria-labelledby="cc-vk-s">
      <h2 class="cc-titel" id="cc-vk-s"><?= $h($m($M['vk_schreiben'])) ?></h2>
      <nav class="cc-schnell">
        <a href="<?= $h($selbst(['cc' => 1, 'kunden' => 1])) ?>"><?= $h($m($M['vk_akte'])) ?></a>
        <a href="<?= $h($start(['ak' => 'kontakt'])) ?>"><?= $h($m($M['vk_kontaktwege'])) ?></a>
      </nav>
    </section>
  </div>

<?php elseif ($mTeil === 'check'):
  require_once dirname(__DIR__) . '/src/PartnerCheck.php';
  $TP = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sprache);
  $ckNeu = null;
  if (preg_match('/^[0-9a-f]{32}$/', (string) ($_GET['ck'] ?? ''))) {
      $ckZ = Db::one('SELECT token, ergebnis FROM partner_checks WHERE token = ? AND partner_id = ?', [(string) $_GET['ck'], (int) $p['id']]);
      if ($ckZ) { $ckNeu = ['token' => (string) $ckZ['token'], 'ergebnis' => json_decode((string) $ckZ['ergebnis'], true) ?: ['host' => '', 'punkte' => []]]; }
  }
  $ckL = PartnerCheck::letzte((int) $p['id']); ?>
  <section class="cc-karte cc-auf z2" aria-labelledby="cc-ck-t">
    <h2 class="cc-as-titel" id="cc-ck-t"><?= $h($TP('ck_titel')) ?></h2>
    <p class="hilfe" style="margin:0 0 12px"><?= $h($m($M['ck_satz'])) ?></p>
    <?php if (($ccMkMeldung ?? '') !== ''): ?><div class="hinweis schlecht" role="alert" style="margin:0 0 12px"><?= $h($TP($ccMkMeldung)) ?></div><?php endif; ?>
    <form method="post" action="<?= $h($mUrl(['teil' => 'check'])) ?>" class="cc-zeile" data-warten="<?= $h($TP('ck_laeuft')) ?>">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="mkt_check">
      <label class="cc-feld"><span><?= $h($TP('ck_feld')) ?></span><input type="text" name="url" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="300" required value="<?= $h((string) ($_POST['url'] ?? '')) ?>"></label>
      <button class="knopf haupt" type="submit"><?= $h($TP('ck_pruefen')) ?></button>
    </form>
  </section>
  <?php if ($ckNeu): $ckE = $ckNeu['ergebnis']; $ckLink = PartnerCheck::link($ckNeu['token']); ?>
    <section class="cc-karte cc-ck-ergebnis cc-auf z3" id="ck-ergebnis" role="status">
      <h2 class="cc-titel"><?= $h($TP('ck_fertig')) ?> <?= $h((string) $ckE['host']) ?></h2>
      <ul class="cc-ck-punkte">
        <?php foreach ((array) $ckE['punkte'] as $pk): $KP = Texte::PARTNER_CHECK['punkte'][$pk['was']] ?? null; if (!$KP) { continue; } ?>
          <li><span class="cc-ampel <?= $h((string) $pk['stand']) ?>" aria-hidden="true"></span><?= $h(Texte::h($KP['titel'], $sprache)) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="cc-mt-knoepfe">
        <a class="knopf" href="https://wa.me/?text=<?= rawurlencode($TP('ck_wa_text') . $ckLink) ?>" target="_blank" rel="noopener"><?= $h($TP('ck_wa')) ?></a>
        <a class="knopf" href="<?= $h($ckLink) ?>" target="_blank" rel="noopener"><?= $h($TP('ck_oeffnen')) ?></a>
        <input type="hidden" id="ck-link" value="<?= $h($ckLink) ?>"><button class="knopf" type="button" data-cc-kopie="ck-link" data-fertig="<?= $h($m($M['mt_kopiert'])) ?>"><?= $h($TP('kopieren')) ?></button>
      </div>
    </section>
  <?php endif; ?>
  <?php if ($ckL): ?>
    <section class="cc-auf z4" aria-labelledby="cc-ck-l" style="margin-top:18px">
      <h2 class="cc-titel" id="cc-ck-l"><?= $h($TP('ck_letzte')) ?></h2>
      <ul class="cc-kliste">
        <?php foreach ($ckL as $c0): ?>
          <li><a href="<?= $h(PartnerCheck::link($c0['token'])) ?>" target="_blank" rel="noopener"><span><b><?= $h($c0['host']) ?></b>
            <small><?= $h(strtr($TP('ck_punkte'), ['{n}' => (string) $c0['schlecht']])) ?> · <?= $h(strtr($TP('ck_aufrufe'), ['{n}' => (string) $c0['aufrufe']])) ?></small></span><span aria-hidden="true">↗</span></a></li>
        <?php endforeach; ?>
      </ul>
      <p style="margin:10px 0 0"><a class="cc-mt-mehr" href="<?= $h($selbst() . '#schnellcheck') ?>"><?= $h($m($M['ck_alle'])) ?> →</a></p>
    </section>
  <?php endif; ?>
<?php endif; ?>
</main>
