<?php
/* ==========================================================================
   Command Center des Partners (Etappe 1b, 05.10.2026, PartnerCommand).
   Eine eigene, schnelle Seite und seit 05.10.2026 die START-Seite des Partners
   (Spezifikation Punkt 4): „Guten Morgen, Name.“, ein Satz, vier Kennzahlen,
   „Heute wichtig“ und der große Knopf „Was soll ich jetzt tun?“. Ziele, Weg zur
   Provision, Kampagnen und Marketingprofil stehen zugeklappt darunter. Alles
   Weitere liegt in den Bereichen KUNDEN · MARKETING · ERGEBNISSE · SHOP · MEIN KONTO.

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
$ccKundenSeite = in_array($ccSeite, ['kunden', 'lead'], true);
/* Sprungziele: Anker im Partnerbereich — oder, mit „cc:“, Stellen im Command Center selbst (Kampagnen). */
$ccBereich = static fn(string $anker): string => match ($anker) {
    'cc:kampagne-neu' => $selbst(['cc' => 1, 'kampagne' => 'neu']),
    'cc:kampagnen'    => $selbst(['cc' => 1]) . '#kampagnen',
    'cc:qr'           => $selbst(['cc' => 1, 'qr' => 1]),
    'cc:kunden'       => $selbst(['cc' => 1, 'kunden' => 1]),
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
    'finden'      => ['<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>', Texte::PARTNER_REITER['reiter']['finden']['kurz']],
    'werben'      => ['<path d="M3 10.5v3a1 1 0 0 0 1 1h2.5L12 18.5v-13L6.5 9.5H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>', Texte::PARTNER_REITER['reiter']['werben']['kurz']],
    'geld'        => ['<path d="M4 19.5h16"/><path d="M6.5 16v-4"/><path d="M11 16V8"/><path d="M15.5 16v-6"/><path d="M20 16V5"/>', Texte::PARTNER_REITER['reiter']['geld']['kurz']],
    'werbemittel' => ['<path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>', Texte::PARTNER_REITER['reiter']['werbemittel']['kurz']],
    'academy'     => ['<path d="M2.5 9.5L12 5l9.5 4.5L12 14z"/><path d="M6.5 11.5v4.2c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3v-4.2"/><path d="M21.5 9.5v5"/>', Texte::PARTNER_REITER['reiter']['academy']['kurz']],
    'profil'      => ['<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>', Texte::PARTNER_REITER['reiter']['profil']['kurz']],
];
if (!$ccMc) { unset($ccLeiste['werbemittel']); }
/* Am Handy höchstens fünf: START · KUNDEN · MARKETING · ERGEBNISSE · MEHR (Punkt 3). */
$ccMehr = ['werbemittel', 'academy', 'profil'];
/* Die vier Kennzahlen (Punkt 4) — jede führt zu ihren Einzelheiten. */
$ccKacheln = [
    'leads'     => [$ccZ['leads_neu'], 'cc:kunden', ''],
    'kunden'    => [$ccZ['kunden'], 'zahlen', ''],
    'provision' => [$ccZ['provision'], 'provisionen', $ccZ['provision_wartet'] > 0 ? $c($C['k_wartet'], ['{betrag}' => Fmt::geld($ccZ['provision_wartet'])]) : ''],
    'klicks'    => [$ccZ['klicks'], 'zahlen', ''],
];
$ccWichtig = PartnerCommand::wichtig($p, $sprache, $ccZ, $ccMc);
$ccIst = static fn(string $liste, string $wert): bool => in_array($wert, (array) $ccPf[$liste], true);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($c($C['titel'])) ?> — Vecom Design</title>
<meta name="theme-color" content="#060a16">
<link rel="manifest" href="<?= $h($selbst(['manifest' => 1])) ?>">
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="apple-touch-icon" href="/assets/img/app-icon-192.png">
<link rel="preload" href="/assets/img/vecom-v.svg" as="image" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/kunde.css') ?>">
<link rel="stylesheet" href="/assets/css/partner-cc.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/partner-cc.css') ?>">
</head>
<body class="cc" data-cc-voll="<?= $h($selbst()) ?>">
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
  </header>
  <nav class="cc-leiste" aria-label="<?= $h(Texte::h(Texte::PARTNER_REITER['aria'], $sprache)) ?>">
    <?php foreach ($ccLeiste as $lk => [$svg, $wort]): ?>
      <?php $ccHier = $lk === ($ccKundenSeite ? 'finden' : 'cc'); /* KUNDEN ist seit Phase 2 eine eigene Seite im Command Center */ ?>
      <a href="<?= $h($lk === 'cc' ? $selbst(['cc' => 1]) : ($lk === 'finden' ? $selbst(['cc' => 1, 'kunden' => 1]) : ($lk === 'academy' ? $start(['ak' => '1']) : $selbst() . '#r-' . $lk))) ?>"<?= $ccHier ? ' aria-current="page"' : '' ?><?= in_array($lk, $ccMehr, true) ? ' class="cc-gross"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $svg ?></svg><span><?= $h(Texte::h($wort, $sprache)) ?></span></a>
    <?php endforeach; ?>
    <?php /* MEHR am Handy: ohne Skript, als aufklappbare Liste über der Leiste. */ ?>
    <details class="cc-mehr">
      <summary aria-label="<?= $h(Texte::h(Texte::PARTNER_REITER['mehr_aria'], $sprache)) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="5.5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="18.5" cy="12" r="1.6"/></svg><span><?= $h(Texte::h(Texte::PARTNER_REITER['mehr'], $sprache)) ?></span></summary>
      <div class="cc-mehr__liste">
        <?php foreach ($ccMehr as $lk): if (!isset($ccLeiste[$lk])) { continue; } [$svg, $wort] = $ccLeiste[$lk]; ?>
          <a href="<?= $h($lk === 'academy' ? $start(['ak' => '1']) : $selbst() . '#r-' . $lk) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $svg ?></svg><span><?= $h(Texte::h($wort, $sprache)) ?></span></a>
        <?php endforeach; ?>
      </div>
    </details>
  </nav>

  <?php if ($ccSeite !== 'start'): require __DIR__ . ($ccKundenSeite ? '/partner_cc_kunden.php' : '/partner_cc_kampagne.php'); else: ?>
  <main id="cc-start" tabindex="-1">
    <div class="cc-hallo cc-auf">
      <?php [$vor, $nach] = array_pad(explode('{name}', $c($C['gruss'][PartnerCommand::gruss()]), 2), 2, ''); /* morgen | tag | abend; Name in Gold */ ?>
      <h1><?= $h($vor) ?><span class="name"><?= $h($ccName) ?></span><?= $h($nach) ?></h1>
      <p class="cc-unter"><?= $h($c($C['ueberblick'])) ?></p>
    </div>

    <?php if ($ccMeldung === 'pf_gut'): ?><div class="hinweis gut cc-auf" role="status" style="margin:0 0 16px"><?= $h($c($C['pf_gut'])) ?></div><?php endif; ?>

    <section class="cc-kz4 cc-auf z2" aria-label="<?= $h($c($C['kz4_aria'])) ?>">
      <?php foreach ($ccKacheln as $kk => [$wert, $anker, $zusatz]):
        $txt = $kk === 'provision' ? Fmt::geld($wert) : $ccZahl($wert); ?>
        <a class="cc-zahl<?= $kk === 'provision' && $wert > 0 ? ' gold' : '' ?><?= $wert === 0 ? ' null' : '' ?>" href="<?= $h($ccBereich($anker)) ?>" data-cc-zahl="<?= $h($kk) ?>">
          <span class="l"><?= $h($c($C['kz4'][$kk][0])) ?></span>
          <b><?= $h($txt) ?></b>
          <small><?= $h($zusatz !== '' ? $zusatz : $c($C['kz4'][$kk][1])) ?></small>
        </a>
      <?php endforeach; ?>
    </section>

    <section class="cc-wichtig cc-auf z2" aria-labelledby="cc-wichtig-t">
      <h2 class="cc-titel" id="cc-wichtig-t"><?= $h($c($C['wichtig_titel'])) ?></h2>
      <?php if (!$ccWichtig): ?>
        <p class="cc-ruhig"><i class="cc-punkt gruen" aria-hidden="true"></i><?= $h($c($C['wichtig_leer'])) ?></p>
      <?php else: ?>
        <ul class="cc-wliste">
          <?php foreach ($ccWichtig as $w): ?>
            <li><a href="<?= $h($ccBereich($w['anker'])) ?>" data-cc-wichtig="<?= $h($w['k']) ?>">
              <i class="cc-punkt <?= $h($w['stufe']) ?>" aria-hidden="true"></i>
              <span><?= $h($w['text']) ?><small class="sr-nur"> — <?= $h($c($C['wichtig_stufe'][$w['stufe']])) ?></small></span>
              <span class="cc-pfeil" aria-hidden="true">→</span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php /* Der große Knopf (Punkt 4): Erst auf Tippen zeigt er die eine beste nächste Aktion — ohne Skript, als <details>. */ ?>
    <details class="cc-jetzt cc-auf z3" id="jetzt">
      <summary class="cc-jetzt__knopf"><span><?= $h($c($C['jetzt'])) ?></span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg></summary>
      <section class="cc-heute" aria-labelledby="cc-heute-t">
        <?php if ($ccE['wenig']): ?>
          <p class="cc-wenig"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6v.2"/></svg><span><?= $h($c($C['wenig'])) ?></span></p>
        <?php endif; ?>
        <h2 id="cc-heute-t"><?= $h($ccE['titel']) ?></h2>
        <p class="cc-warum"><b><?= $h($c($C['warum'])) ?>:</b> <?= $h($ccE['warum']) ?></p>
        <a class="knopf haupt" href="<?= $h($ccBereich($ccE['anker'])) ?>" data-cc-empfehlung="<?= $h($ccE['k']) ?>"><?= $h($ccE['knopf']) ?> <span aria-hidden="true">→</span></a>
      </section>
    </details>

    <nav class="cc-schnell cc-auf z3" aria-label="<?= $h($c($C['schnell_aria'])) ?>">
      <a href="<?= $h($selbst(['cc' => 1, 'kampagne' => 'neu'])) ?>">+ <?= $h($c($C['schnell']['kampagne_neu'])) ?></a>
      <a href="<?= $h($selbst(['cc' => 1]) . '#kampagnen') ?>"><?= $h($c($C['schnell']['kampagnen'], ['{n}' => (string) $ccZ['kampagnen']])) ?></a>
      <a href="<?= $h($selbst(['cc' => 1, 'qr' => 1])) ?>"><?= $h($c($C['schnell']['qr'])) ?></a>
      <a href="<?= $h($selbst(['cc' => 1]) . '#profil') ?>"><?= $h($c($C['schnell']['profil'])) ?></a>
    </nav>

      <?php /* Partner Academy (05.10.2026): Training fortsetzen und die Einwand-Hilfe — zwei Tipps vom Start entfernt. */
        require_once dirname(__DIR__) . '/src/Academy.php'; $ccAk = Texte::ACADEMY; $ccA = static fn(string $k, array $r = []): string => strtr(Texte::h($ccAk[$k], $sprache), $r);
        try { $ccAs = Academy::stand((int) $p['id'], $sprache); } catch (Throwable $e) { $ccAs = null; } ?>
      <?php if ($ccAs): ?>
      <section class="cc-karte ak-cc cc-auf z3" aria-labelledby="cc-ak-t">
        <div class="ak-cc__text">
          <h2 class="cc-titel" id="cc-ak-t"><?= $h($ccA('titel')) ?></h2>
          <p><b><?= (int) $ccAs['prozent'] ?> %</b> · <?= $h($ccA('module_fertig', ['{n}' => (string) $ccAs['fertig'], '{gesamt}' => (string) $ccAs['gesamt']])) ?><?php if ($ccAs['naechstes']): ?> · <?= $h($ccA('naechstes')) ?>: <?= $h($ccAs['naechstes']['titel']) ?><?php endif; ?></p>
          <div class="ak-cc__balken" aria-hidden="true"><i style="width:<?= (int) $ccAs['prozent'] ?>%"></i></div>
        </div>
        <div class="ak-cc__knoepfe">
          <a class="knopf haupt" href="<?= $h($start(['ak' => '1'])) ?>"><?= $h($ccA($ccAs['begonnen'] ? 'fortsetzen' : 'beginnen')) ?> →</a>
          <a class="knopf" href="<?= $h($start(['ak' => 'einwaende'])) ?>"><?= $h($ccA('s_einwand')) ?></a>
        </div>
      </section>
      <?php endif; ?>

    <?php /* Darunter, zugeklappt: was es vorher auf dieser Seite gab. Offen, wenn das Profil gerade bearbeitet wird. */
          $ccOffen = $ccMeldung === 'pf_fehler' || $ccMeldung === 'pf_gut' || isset($_GET['profil']); ?>
    <details class="cc-mehrblick cc-auf z4" id="mehr"<?= $ccOffen ? ' open' : '' ?>>
      <summary><?= $h($c($C['mehr_ueberblick'])) ?></summary>
    <div class="cc-raster">
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

      <?php $ccTr = PartnerCommand::trichter($p); ?>
      <section class="cc-breit cc-auf z3" aria-labelledby="cc-weg-t">
        <h2 class="cc-titel" id="cc-weg-t"><?= $h($c($C['weg_titel'])) ?></h2>
        <ol class="cc-trichter">
          <?php foreach ($C['weg'] as $tk => $tn): $tw = (int) $ccTr[$tk]; ?>
            <li class="<?= $tw === 0 ? 'null' : '' ?><?= $tk === 'provision' && $tw > 0 ? ' gold' : '' ?>"><b><?= $h($tk === 'provision' ? Fmt::geld($tw) : $ccZahl($tw)) ?></b><span><?= $h($c($tn)) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <p class="hilfe"><?= $h($c($C['weg_satz'])) ?></p>
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
    </details>
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
