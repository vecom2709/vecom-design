<?php
/* ==========================================================================
   Command Center des Partners (Etappe 1b, 05.10.2026, PartnerCommand).
   Eine eigene, schnelle Seite: Begrüßung, genau eine Empfehlung für heute,
   Schnellwege nach Ziel, sechs echte Kennzahlen, Marketingprofil. Alles
   Weitere bleibt im Partnerbereich — jede Kachel führt mit einem Tipp dorthin.

   Erwartet aus partner.php: $p, $sprache, $h, $selbst, $ccMeldung ('' | pf_gut | pf_fehler),
   $ccMc (Marketing Center vorhanden), $ccPost (bei Fehler: was eingegeben war).
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerCommand.php';
require_once dirname(__DIR__) . '/src/PartnerKampagne.php';
$C = Texte::PARTNER_CC;
$c = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$ccZ = PartnerCommand::zahlen($p);
$ccE = PartnerCommand::empfehlung($p, $sprache, $ccZ, $ccMc);
$ccPf = PartnerCommand::profil($p);
if ($ccMeldung === 'pf_fehler' && is_array($ccPost ?? null)) {   // Eingaben behalten, damit nichts neu getippt werden muss
    $ccPf = ['branchen' => array_map('strval', (array) ($ccPost['branchen'] ?? [])), 'wege' => array_map('strval', (array) ($ccPost['wege'] ?? [])),
             'ziel' => (string) ($ccPost['ziel'] ?? ''), 'ort' => (string) ($ccPost['ort'] ?? ''), 'fertig' => false];
}
$ccSeite ??= 'start';
/* Sprungziele: Anker im Partnerbereich — oder, mit „cc:“, Stellen im Command Center selbst (Kampagnen). */
$ccBereich = static fn(string $anker): string => match ($anker) {
    'cc:kampagne-neu' => $selbst(['cc' => 1, 'kampagne' => 'neu']),
    'cc:kampagnen'    => $selbst(['cc' => 1]) . '#kampagnen',
    default           => $selbst() . '#' . $anker,
};
$ccName = trim((string) preg_split('~\s+~u', trim((string) $p['name']))[0]) ?: Partner::anzeigeName($p);
$ccZahl = static fn(int $n): string => number_format($n, 0, ',', $sprache === 'en' ? ',' : '.');
$ccIcon = [
    'kunden'   => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
    'anfragen' => '<path d="M9.5 14.5l5-5"/><path d="M11 6.5l1.2-1.2a4 4 0 0 1 5.6 5.6L16.5 12"/><path d="M13 17.5l-1.2 1.2a4 4 0 0 1-5.6-5.6L7.5 12"/>',
    'lokal'    => '<path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
    'social'   => '<path d="M3 10.5v3a1 1 0 0 0 1 1h2.5L12 18.5v-13L6.5 9.5H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>',
    'check'    => '<rect x="4" y="3.5" width="16" height="17" rx="2.5"/><path d="M8 9l1.6 1.6L12.5 7.7"/><path d="M8 15l1.6 1.6 2.9-2.9"/><path d="M15 9.5h2M15 15.5h2"/>',
];
$ccLeiste = [
    'cc'          => ['<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>', Texte::PARTNER_REITER['reiter']['start']['kurz']],
    'werben'      => ['<path d="M3 10.5v3a1 1 0 0 0 1 1h2.5L12 18.5v-13L6.5 9.5H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>', Texte::PARTNER_REITER['reiter']['werben']['kurz']],
    'werbemittel' => ['<path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>', Texte::PARTNER_REITER['reiter']['werbemittel']['kurz']],
    'finden'      => ['<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>', Texte::PARTNER_REITER['reiter']['finden']['kurz']],
    'geld'        => ['<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/><path d="M15.5 14.5h2.5"/>', Texte::PARTNER_REITER['reiter']['geld']['kurz']],
    'profil'      => ['<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>', Texte::PARTNER_REITER['reiter']['profil']['kurz']],
];
if (!$ccMc) { unset($ccLeiste['werbemittel']); }
$ccKacheln = [
    'kampagnen'    => [$ccZ['kampagnen'], 'cc:kampagnen', ''],
    'scans'        => [$ccZ['scans'], $ccMc ? 'mc-erfolge' : 'werben', ''],
    'leads'        => [$ccZ['leads'], 'besuche', ''],
    'kunden'       => [$ccZ['kunden'], 'empfehlungen', ''],
    'provision'    => [$ccZ['provision'], 'provisionen', $ccZ['provision_wartet'] > 0 ? $c($C['k_wartet'], ['{betrag}' => Fmt::geld($ccZ['provision_wartet'])]) : ''],
    'bestellungen' => [$ccZ['bestellungen'], $ccMc ? 'mc-bestellungen' : 'werben', $ccZ['bestellungen_zahlung'] > 0 ? $c($C['k_zahlung'], ['{n}' => (string) $ccZ['bestellungen_zahlung']]) : ''],
];
if (!$ccMc && $ccZ['bestellungen'] === 0) { unset($ccKacheln['bestellungen']); }
$ccIst = static fn(string $liste, string $wert): bool => in_array($wert, (array) $ccPf[$liste], true);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($c($C['titel'])) ?> — Vecom Design</title>
<meta name="theme-color" content="#0a0908">
<link rel="manifest" href="<?= $h($selbst(['manifest' => 1])) ?>">
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="apple-touch-icon" href="/assets/img/app-icon-192.png">
<link rel="preload" href="/assets/img/vecom-v.svg" as="image" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/kunde.css') ?>">
<link rel="stylesheet" href="/assets/css/partner-cc.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/partner-cc.css') ?>">
</head>
<body class="cc">
<?php if ($ccSeite === 'start'): ?>
<div class="cc-intro" id="cc-intro" hidden aria-hidden="true">
  <div class="cc-intro__schein"></div>
  <div class="cc-intro__v"><img src="/assets/img/vecom-v.svg" alt="" width="289" height="235"></div>
  <button class="cc-intro__weg" type="button" tabindex="-1"><?= $h($c($C['intro_weg'])) ?></button>
</div>
<?php endif; ?>

<div class="seite">
  <header class="cc-kopf">
    <a class="cc-marke" href="<?= $h($selbst(['cc' => 1])) ?>" aria-label="Vecom Design — <?= $h($c($C['titel'])) ?>">
      <img src="/assets/img/vecom-v.svg" alt="" width="42" height="34">
      <span><b>Vecom</b><small><?= $h($c($C['titel'])) ?></small></span>
    </a>
    <a class="cc-alles" href="<?= $h($selbst()) ?>"><?= $h($c($C['bereich'])) ?> <span aria-hidden="true">→</span></a>
  </header>
  <nav class="cc-leiste" aria-label="<?= $h(Texte::h(Texte::PARTNER_REITER['aria'], $sprache)) ?>">
    <?php foreach ($ccLeiste as $lk => [$svg, $wort]): ?>
      <a href="<?= $h($lk === 'cc' ? $selbst(['cc' => 1]) : $selbst() . '#r-' . $lk) ?>"<?= $lk === 'cc' ? ' aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $svg ?></svg><span><?= $h(Texte::h($wort, $sprache)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($ccSeite !== 'start'): require __DIR__ . '/partner_cc_kampagne.php'; else: ?>
  <main id="cc-start" tabindex="-1">
    <div class="cc-hallo cc-auf">
      <?php [$vor, $nach] = array_pad(explode('{name}', $c($C['hallo']), 2), 2, ''); /* Name in Gold, der Gruß drumherum aus Texte */ ?>
      <h1><?= $h($vor) ?><span class="name"><?= $h($ccName) ?></span><?= $h($nach) ?>
        <span class="cc-frage"><?= $h($c($C['frage'])) ?></span></h1>
    </div>

    <?php if ($ccMeldung === 'pf_gut'): ?><div class="hinweis gut cc-auf" role="status" style="margin:0 0 16px"><?= $h($c($C['pf_gut'])) ?></div><?php endif; ?>

    <div class="cc-raster">
      <section class="cc-heute cc-auf z2" aria-labelledby="cc-heute-t">
        <p class="cc-auge"><?= $h($c($C['heute'])) ?></p>
        <?php if ($ccE['wenig']): ?>
          <p class="cc-wenig"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6v.2"/></svg><span><?= $h($c($C['wenig'])) ?></span></p>
        <?php endif; ?>
        <h2 id="cc-heute-t"><?= $h($ccE['titel']) ?></h2>
        <p class="cc-warum"><b><?= $h($c($C['warum'])) ?>:</b> <?= $h($ccE['warum']) ?></p>
        <a class="knopf haupt" href="<?= $h($ccBereich($ccE['anker'])) ?>" data-cc-empfehlung="<?= $h($ccE['k']) ?>"><?= $h($ccE['knopf']) ?> <span aria-hidden="true">→</span></a>
      </section>

      <section class="cc-auf z3" aria-labelledby="cc-ziele-t">
        <h2 class="cc-titel" id="cc-ziele-t"><?= $h($c($C['ziele_titel'])) ?></h2>
        <ul class="cc-ziele">
          <?php foreach (PartnerCommand::SCHNELLWEGE as $zk => $anker): if ($zk === 'lokal' && !$ccMc) { $anker = 'medien'; } ?>
            <li><a class="cc-ziel" href="<?= $h($ccBereich($anker)) ?>" data-cc-ziel="<?= $h($zk) ?>">
              <i aria-hidden="true"><svg viewBox="0 0 24 24"><?= $ccIcon[$zk] ?></svg></i>
              <span><b><?= $h($c($C['ziele'][$zk][0])) ?></b><small><?= $h($c($C['ziele'][$zk][1])) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="cc-breit cc-auf z3" aria-labelledby="cc-zahlen-t">
        <h2 class="cc-titel" id="cc-zahlen-t"><?= $h($c($C['zahlen_titel'])) ?></h2>
        <div class="cc-zahlen">
          <?php foreach ($ccKacheln as $kk => [$wert, $anker, $zusatz]):
            $txt = $kk === 'provision' ? Fmt::geld($wert) : $ccZahl($wert); ?>
            <a class="cc-zahl<?= $kk === 'provision' && $wert > 0 ? ' gold' : '' ?><?= $wert === 0 ? ' null' : '' ?>" href="<?= $h($ccBereich($anker)) ?>" data-cc-zahl="<?= $h($kk) ?>">
              <span class="l"><?= $h($c($C['k'][$kk][0])) ?></span>
              <b><?= $h($txt) ?></b>
              <small><?= $h($zusatz !== '' ? $zusatz : ($kk === 'kampagnen' && $wert > 0 ? '' : $c($C['k'][$kk][1]))) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <?php $ccKListe = array_slice(PartnerKampagne::liste((int) $p['id']), 0, 6); $KK = Texte::PARTNER_KAMPAGNE; ?>
      <section class="cc-breit cc-auf z4" id="kampagnen" aria-labelledby="cc-kamp-t">
        <div class="cc-titelzeile"><h2 class="cc-titel" id="cc-kamp-t"><?= $h($c($KK['liste'])) ?></h2>
          <a class="knopf haupt" href="<?= $h($selbst(['cc' => 1, 'kampagne' => 'neu'])) ?>">+ <?= $h($c($KK['neu'])) ?></a></div>
        <?php if (!$ccKListe): ?>
          <p class="cc-leer"><?= $h($c($KK['leer'])) ?></p>
        <?php else: ?>
          <ul class="cc-kliste">
            <?php foreach ($ccKListe as $kk): $kz = PartnerKampagne::zahlen((int) $p['id'], (int) $kk['id']) ?? []; ?>
              <li><a href="<?= $h($selbst(['cc' => 1, 'kampagne' => (int) $kk['id']])) ?>">
                <span><b><?= $h((string) $kk['name']) ?></b><small><?= $h(PartnerKampagne::nummer($kk)) ?> · <span class="cc-status s-<?= $h((string) $kk['status']) ?>"><?= $h($c($KK['status'][(string) $kk['status']])) ?></span></small></span>
                <span class="cc-kz"><b><?= (int) ($kz['scans'] ?? 0) + (int) ($kz['klicks'] ?? 0) ?></b><small><?= $h($c($KK['kz'])) ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="cc-breit cc-karte cc-profil cc-auf z4" id="profil" aria-labelledby="cc-profil-t">
        <h2 id="cc-profil-t"><?= $h($c($C['pf_titel'])) ?></h2>
        <?php if ($ccPf['fertig'] && $ccMeldung !== 'pf_fehler' && !isset($_GET['profil'])): ?>
          <p class="cc-kurz">
            <span><b><?= $h(implode(', ', array_map(static fn($b) => $c(Texte::KAMPAGNE_BRANCHEN[$b]), $ccPf['branchen']))) ?></b></span>
            <span><?= $h($ccPf['ort']) ?></span>
            <span><?= $h(implode(', ', array_map(static fn($w) => $c($C['wege'][$w]), $ccPf['wege']))) ?></span>
            <span><?= $h($c(Texte::KAMPAGNE_ZIELE[$ccPf['ziel']])) ?></span>
            <a href="<?= $h($selbst(['cc' => 1, 'profil' => 1])) ?>#profil"><?= $h($c($C['pf_aendern'])) ?></a>
          </p>
        <?php else: ?>
          <p class="lead"><?= $h($c($C['pf_text'])) ?></p>
          <?php if ($ccMeldung === 'pf_fehler'): ?><div class="hinweis schlecht" role="alert" style="margin:0 0 14px"><?= $h($c($C['pf_fehler'])) ?></div><?php endif; ?>
          <form method="post" action="<?= $h($selbst(['cc' => 1])) ?>#profil" id="cc-profil-form" class="cc-assistent" data-max-branchen="<?= PartnerCommand::HOECHSTENS_BRANCHEN ?>"
                data-stand="<?= $h($c($C['pf_schritt'])) ?>">
            <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="cc_profil">
            <fieldset class="cc-schritt">
              <legend><?= $h($c($C['pf_branchen'])) ?></legend>
              <div class="cc-chips">
                <?php foreach (PartnerKampagne::BRANCHEN as $b): ?>
                  <label class="cc-chip"><input type="checkbox" name="branchen[]" value="<?= $h($b) ?>"<?= $ccIst('branchen', $b) ? ' checked' : '' ?>><span><?= $h($c(Texte::KAMPAGNE_BRANCHEN[$b])) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <fieldset class="cc-schritt">
              <legend><label for="cc-ort"><?= $h($c($C['pf_region'])) ?></label></legend>
              <input type="text" id="cc-ort" name="ort" data-pflicht maxlength="80" autocomplete="address-level2" value="<?= $h($ccPf['ort']) ?>" placeholder="<?= $h($c($C['pf_region_ph'])) ?>">
              <p class="hilfe"><?= $h($c($C['pf_region_hilfe'])) ?></p>
            </fieldset>
            <fieldset class="cc-schritt">
              <legend><?= $h($c($C['pf_wege'])) ?></legend>
              <div class="cc-chips">
                <?php foreach (PartnerCommand::WEGE as $w): ?>
                  <label class="cc-chip"><input type="checkbox" name="wege[]" value="<?= $h($w) ?>"<?= $ccIst('wege', $w) ? ' checked' : '' ?>><span><?= $h($c($C['wege'][$w])) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <fieldset class="cc-schritt">
              <legend><?= $h($c($C['pf_ziel'])) ?></legend>
              <div class="cc-chips">
                <?php foreach (PartnerCommand::PROFIL_ZIELE as $z): ?>
                  <label class="cc-chip"><input type="radio" name="ziel" value="<?= $h($z) ?>"<?= $ccPf['ziel'] === $z ? ' checked' : '' ?>><span><?= $h($c(Texte::KAMPAGNE_ZIELE[$z])) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <div class="cc-fuss">
              <button class="knopf" type="button" data-cc-zurueck hidden><?= $h($c($C['pf_zurueck'])) ?></button>
              <button class="knopf haupt" type="button" data-cc-weiter hidden><?= $h($c($C['pf_weiter'])) ?></button>
              <button class="knopf haupt" type="submit" data-cc-speichern><?= $h($c($C['pf_speichern'])) ?></button>
              <span class="stand" data-cc-stand aria-live="polite"></span>
            </div>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </main>
  <?php endif; ?>


  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h($selbst(['cc' => 1, 'lang' => $l])) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/src/Fuss.php'; echo Fuss::html($sprache); ?>
<script src="/assets/js/partner-cc.js?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/partner-cc.js') ?>" defer></script>
</body>
</html>
