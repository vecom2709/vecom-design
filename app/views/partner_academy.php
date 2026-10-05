<?php
/* ==========================================================================
   Partner Academy (Etappe 1, 05.10.2026, Academy; Etappe 2: Bedarfsassistent,
   Kundenfinder, „Was mache ich jetzt?“, PDF-Bibliothek). Eine eigene Seite neben dem
   Command Center: Start mit Fortschritt, Module mit Lektionen und kurzem
   Wissenstest, Einwand-Schnellhilfe, Kontaktwege, Leistungen in drei Ebenen,
   Suche, Merkliste und eigene Notizen.

   Erwartet aus partner.php: $p, $sprache, $h, $start, $selbst, $akSeite.
   Gelesen wird nur über $p['id']. Alle Inhalte kommen aus Academy::inhalte().
   ========================================================================== */
$A = Texte::ACADEMY;
$a = static fn(string $k, array $r = []): string => strtr(Texte::h($A[$k] ?? [], $sprache), $r);
$D = Academy::inhalte($sprache);
$akPid = (int) $p['id'];
$platz = Academy::platzhalter($p, $sprache);
$t = static fn($s): string => strtr((string) $s, $platz);
$L = static fn(array $q): string => $start(['ak' => '1'] + $q);
$ziel = static fn(string $seite, array $q = []): string => $start(['ak' => $seite] + $q);
$merk = Academy::merkliste($akPid);
$stand = Academy::stand($akPid, $sprache);
$fortschritt = Academy::fortschritt($akPid);
$name = trim((string) preg_split('~\s+~u', trim((string) $p['name']))[0]) ?: Partner::anzeigeName($p);
$csrf = (string) $_SESSION['csrf'];

/* Absätze: Leerzeile trennt, Zeilen „1. …“ werden eine nummerierte Liste. */
$absaetze = static function (string $text) use ($h, $t): string {
    $o = '';
    foreach (preg_split("~\n\s*\n~", trim($t($text))) as $teil) {
        $zeilen = array_values(array_filter(array_map('trim', explode("\n", $teil))));
        if ($zeilen && preg_match('~^\d+\.\s~', $zeilen[0])) {
            $o .= '<ol class="ak-schritte">';
            foreach ($zeilen as $z) { $o .= '<li>' . $h((string) preg_replace('~^\d+\.\s*~', '', $z)) . '</li>'; }
            $o .= '</ol>';
        } else {
            $o .= '<p>' . nl2br($h(implode("\n", $zeilen))) . '</p>';
        }
    }
    return $o;
};
$liste = static function (array $punkte, string $klasse = '') use ($h, $t): string {
    if (!$punkte) { return ''; }
    $o = '<ul class="ak-liste ' . $klasse . '">';
    foreach ($punkte as $pk) { $o .= '<li>' . $h($t($pk)) . '</li>'; }
    return $o . '</ul>';
};
/* Merken-Knopf: kleines Formular, kommt auf dieselbe Stelle zurück. */
$merkKnopf = static function (string $art, string $slug, array $zurueck, string $anker = '') use ($merk, $h, $a, $csrf): string {
    $an = Academy::gemerkt($merk, $art, $slug);
    $o = '<form method="post" class="ak-merk-form"><input type="hidden" name="_csrf" value="' . $h($csrf) . '">'
       . '<input type="hidden" name="tat" value="ak_merken"><input type="hidden" name="art" value="' . $h($art) . '">'
       . '<input type="hidden" name="ziel" value="' . $h($slug) . '"><input type="hidden" name="an" value="' . ($an ? '' : '1') . '">'
       . '<input type="hidden" name="anker" value="' . $h($anker) . '">';
    foreach ($zurueck as $k => $v) { $o .= '<input type="hidden" name="' . $h((string) $k) . '" value="' . $h((string) $v) . '">'; }
    return $o . '<button type="submit" class="ak-merk' . ($an ? ' an' : '') . '" aria-pressed="' . ($an ? 'true' : 'false') . '">'
        . '<span aria-hidden="true">' . ($an ? '★' : '☆') . '</span> ' . $h($a($an ? 'gemerkt' : 'merken')) . '</button></form>';
};
$modulStand = static function (array $m) use ($fortschritt): array {
    $z = $fortschritt[$m['slug']] ?? null;
    $n = Academy::schritte($m);
    if (!$z) { return ['s' => 'neu', 'p' => 0]; }
    if ($z['fertig_am'] !== null) { return ['s' => 'fertig', 'p' => 100]; }
    return ['s' => 'begonnen', 'p' => $n > 0 ? (int) round(100 * min(count($m['lektionen'] ?? []), count(Academy::gelesen($z))) / $n) : 0];
};
/* Partnerlink zum Kopieren (Bedarfsassistent, „Was mache ich jetzt?“): derselbe Link wie überall. */
$linkBox = static function () use ($p, $h, $a): string {
    $url = Partner::link($p);
    return '<section class="ak-link cc-karte" aria-labelledby="ak-link-t"><h2 class="cc-titel" id="ak-link-t">' . $h($a('bedarf_link')) . '</h2>'
        . '<p class="hilfe">' . $h($a('bedarf_link_satz')) . '</p><div class="ak-link__zeile">'
        . '<label class="sr-only" for="ak-link-feld">' . $h($a('bedarf_link')) . '</label>'
        . '<input id="ak-link-feld" type="text" readonly value="' . $h($url) . '">'
        . '<button class="knopf" type="button" data-ak-kopie="#ak-link-feld" data-fertig="' . $h($a('kopiert')) . '" hidden>' . $h($a('kopieren')) . '</button></div></section>';
};
/* Erste ungelesene Lektion — dort setzt „Training fortsetzen“ an. */
$weiterBei = static function (array $m) use ($fortschritt): string {
    $g = Academy::gelesen($fortschritt[$m['slug']] ?? []);
    foreach (array_keys($m['lektionen'] ?? []) as $i) { if (!in_array($i, $g, true)) { return (string) $i; } }
    return !empty($m['fragen']) && (($fortschritt[$m['slug']]['fertig_am'] ?? null) === null) ? 'test' : '0';
};
$icon = [
    'einwand'  => '<path d="M12 3l8 3.5v5.2c0 4.6-3.3 8.2-8 9.3-4.7-1.1-8-4.7-8-9.3V6.5z"/><path d="M9 12l2 2 4-4"/>',
    'kontakt'  => '<path d="M5 5h14a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 19 16h-6l-5 4v-4H5a1.5 1.5 0 0 1-1.5-1.5v-8A1.5 1.5 0 0 1 5 5z"/>',
    'leistung' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9z"/>',
    'kunden'   => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
    'meine'    => '<path d="M7 3.5h10a1 1 0 0 1 1 1V21l-6-3.6L6 21V4.5a1 1 0 0 1 1-1z"/>',
    'bedarf'   => '<path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><path d="M8 5.5H6a1 1 0 0 0-1 1V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V6.5a1 1 0 0 0-1-1h-2"/><path d="M8.5 12l2 2 4.5-4.5"/><path d="M8.5 17.5h7"/>',
    'finder'   => '<path d="M4 6.5l1.6 1.6L8.5 5"/><path d="M4 12.5l1.6 1.6 2.9-3.1"/><path d="M4 18.5l1.6 1.6 2.9-3.1"/><path d="M11.5 6.5h8.5"/><path d="M11.5 12.5h8.5"/><path d="M11.5 18.5h8.5"/>',
    'jetzt'    => '<circle cx="12" cy="12" r="8.5"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
    'pdf'      => '<path d="M6.5 3h7.5l4.5 4.5V20a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4.5h4.5"/><path d="M8.5 13h7"/><path d="M8.5 16.5h5"/>',
    'academy'  => '<path d="M2.5 9.5L12 5l9.5 4.5L12 14z"/><path d="M6.5 11.5v4.2c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3v-4.2"/><path d="M21.5 9.5v5"/>',
];
$leiste = [   /* dieselbe Reihe wie im Command Center (Spezifikation Punkt 3), dazu die Academy */
    'cc'      => ['<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>', 'start', $selbst(['cc' => 1])],
    'finden'  => ['<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>', 'finden', $selbst() . '#r-finden'],
    'werben'  => ['<path d="M3 10.5v3a1 1 0 0 0 1 1h2.5L12 18.5v-13L6.5 9.5H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>', 'werben', $selbst() . '#r-werben'],
    'geld'    => ['<path d="M4 19.5h16"/><path d="M6.5 16v-4"/><path d="M11 16V8"/><path d="M15.5 16v-6"/><path d="M20 16V5"/>', 'geld', $selbst() . '#r-geld'],
    'academy' => [$icon['academy'], 'academy', $L([])],
    'profil'  => ['<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>', 'profil', $selbst() . '#r-profil'],
];
$akMehr = ['werbemittel', 'profil'];   // am Handy unter MEHR, wie im Command Center
$akTitel = $a('titel');
[$aufQ, $zuQ] = ['it' => ['«', '»'], 'en' => ['“', '”']][$sprache] ?? ['„', '“'];
/* Marketing Center nur, wenn es einen Katalog gibt — wie im Command Center. */
try { require_once dirname(__DIR__) . '/src/Werbemittel.php'; $akMc = (bool) Werbemittel::katalog($sprache, false, Werbemittel::anzeigeLand($p)); } catch (Throwable $e) { $akMc = false; }
if ($akMc) {
    $leiste = array_slice($leiste, 0, 4, true)
        + ['werbemittel' => ['<path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>', 'werbemittel', $selbst() . '#r-werbemittel']]
        + array_slice($leiste, 4, null, true);
}
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($akTitel) ?> — Vecom Design</title>
<meta name="theme-color" content="#0a0908">
<link rel="manifest" href="<?= $h($selbst(['manifest' => 1])) ?>">
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="apple-touch-icon" href="/assets/img/app-icon-192.png">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/kunde.css') ?>">
<link rel="stylesheet" href="/assets/css/partner-cc.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/partner-cc.css') ?>">
<link rel="stylesheet" href="/assets/css/partner-academy.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/partner-academy.css') ?>">
<script src="/assets/js/partner-academy.js?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/partner-academy.js') ?>" defer></script>
</head>
<body class="cc ak">
<div class="seite">
  <header class="cc-kopf">
    <a class="cc-marke" href="<?= $h($L([])) ?>" aria-label="Vecom Design — <?= $h($akTitel) ?>">
      <img src="/assets/img/vecom-v.svg" alt="" width="42" height="34">
      <span><b>Vecom</b><small>Partner Academy</small></span>
    </a>
    <a class="cc-alles" href="<?= $h($selbst()) ?>"><?= $h($a('bereich')) ?> <span aria-hidden="true">→</span></a>
  </header>
  <nav class="cc-leiste" aria-label="<?= $h(Texte::h(Texte::PARTNER_REITER['aria'], $sprache)) ?>">
    <?php foreach ($leiste as $lk => [$svg, $reiter, $url]): ?>
      <a href="<?= $h($url) ?>"<?= $lk === 'academy' ? ' aria-current="page"' : '' ?><?= in_array($lk, $akMehr, true) ? ' class="cc-gross"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $svg ?></svg><span><?= $h(Texte::h(Texte::PARTNER_REITER['reiter'][$reiter]['kurz'], $sprache)) ?></span></a>
    <?php endforeach; ?>
    <details class="cc-mehr">
      <summary aria-label="<?= $h(Texte::h(Texte::PARTNER_REITER['mehr_aria'], $sprache)) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="5.5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="18.5" cy="12" r="1.6"/></svg><span><?= $h(Texte::h(Texte::PARTNER_REITER['mehr'], $sprache)) ?></span></summary>
      <div class="cc-mehr__liste">
        <?php foreach ($akMehr as $lk): if (!isset($leiste[$lk])) { continue; } [$svg, $reiter, $url] = $leiste[$lk]; ?>
          <a href="<?= $h($url) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $svg ?></svg><span><?= $h(Texte::h(Texte::PARTNER_REITER['reiter'][$reiter]['kurz'], $sprache)) ?></span></a>
        <?php endforeach; ?>
      </div>
    </details>
  </nav>

  <form class="ak-suche" method="get" action="/partner.php" role="search">
    <input type="hidden" name="t" value="<?= $h((string) $p['token']) ?>"><input type="hidden" name="ak" value="suche">
    <label class="sr-only" for="ak-q"><?= $h($a('suche')) ?></label>
    <input id="ak-q" type="search" name="q" value="<?= $h($akSeite === 'suche' ? (string) ($_GET['q'] ?? '') : '') ?>" placeholder="<?= $h($a('suche_ph')) ?>" autocomplete="off" maxlength="60">
    <button class="knopf" type="submit"><?= $h($a('suche_los')) ?></button>
  </form>

  <main id="ak-inhalt" tabindex="-1">
<?php
/* =============================== START =============================== */
if ($akSeite === 'start'):
  $nx = $stand['naechstes']; ?>
    <div class="cc-hallo cc-auf">
      <h1><?= $h($a('hallo', ['{name}' => $name])) ?><span class="cc-frage"><?= $h($a('lead')) ?></span></h1>
    </div>
    <?php $akNeu = Academy::neu(); $akNeuZ = $akNeu ? Academy::meldeZiel($akNeu['ziel'], $sprache) : null; if ($akNeuZ): ?>
      <a class="ak-neu-hinweis cc-auf" href="<?= $h($start($akNeuZ['ak'])) ?>"><span class="ak-neu"><?= $h($a('neu_hinweis')) ?></span>
        <span><small><?= $h($a('neu_titel')) ?></small><b><?= $h($akNeuZ['titel']) ?></b></span><i aria-hidden="true">→</i></a>
    <?php endif; ?>
    <div class="cc-raster">
      <section class="cc-heute cc-auf z2" aria-labelledby="ak-stand-t">
        <p class="cc-auge" id="ak-stand-t"><?= $h($a('fortschritt')) ?></p>
        <div class="ak-prozent"><b><?= (int) $stand['prozent'] ?> %</b><span><?= $h($a('module_fertig', ['{n}' => (string) $stand['fertig'], '{gesamt}' => (string) $stand['gesamt']])) ?></span></div>
        <div class="ak-balken" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $stand['prozent'] ?>"><i style="width:<?= (int) $stand['prozent'] ?>%"></i></div>
        <?php if ($nx): ?>
          <p class="ak-naechst"><span><?= $h($a('naechstes')) ?></span><b><?= (int) $nx['nr'] ?> · <?= $h($nx['titel']) ?></b></p>
          <a class="knopf haupt" href="<?= $h($ziel('modul', ['m' => $nx['slug'], 'l' => $weiterBei($nx)])) ?>"><?= $h($a($stand['begonnen'] ? 'fortsetzen' : 'beginnen')) ?> <span aria-hidden="true">→</span></a>
        <?php else: ?>
          <p class="ak-naechst"><b><?= $h($a('alle_fertig')) ?></b></p>
        <?php endif; ?>
        <?php if ($stand['zuletzt'] && (!$nx || $stand['zuletzt']['slug'] !== $nx['slug'])): ?>
          <p class="ak-zuletzt"><?= $h($a('zuletzt')) ?>: <a href="<?= $h($ziel('modul', ['m' => $stand['zuletzt']['slug'], 'l' => $weiterBei($stand['zuletzt'])])) ?>"><?= $h($stand['zuletzt']['titel']) ?></a></p>
        <?php endif; ?>
      </section>

      <section class="cc-auf z3" aria-labelledby="ak-schnell-t">
        <h2 class="cc-titel" id="ak-schnell-t"><?= $h($a('schnell')) ?></h2>
        <ul class="cc-ziele">
          <?php foreach ([
              'einwand'  => [$ziel('einwaende'), 's_einwand', 's_einwand_satz'],
              'kontakt'  => [$ziel('kontakt'), 's_kontakt', 's_kontakt_satz'],
              'leistung' => [$ziel('leistungen'), 's_leistung', 's_leistung_satz'],
              'jetzt'    => [$ziel('jetzt'), 's_jetzt', 's_jetzt_satz'],
              'bedarf'   => [$ziel('bedarf'), 's_bedarf', 's_bedarf_satz'],
              'finder'   => [$ziel('finder'), 's_finder', 's_finder_satz'],
              'pdf'      => [$ziel('bibliothek'), 's_bibliothek', 's_bibliothek_satz'],
              'kunden'   => [$selbst() . '#r-finden', 's_kunden', 's_kunden_satz'],
              'meine'    => [$ziel('meine'), 'merkliste', 'notizen'],
            ] as $sk => [$url, $t1, $t2]): ?>
            <li><a class="cc-ziel<?= $sk === 'einwand' ? ' ak-ziel-gold' : '' ?>" href="<?= $h($url) ?>">
              <i aria-hidden="true"><svg viewBox="0 0 24 24"><?= $icon[$sk] ?></svg></i>
              <span><b><?= $h($a($t1)) ?></b><small><?= $h($a($t2)) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="cc-breit cc-auf z3" aria-labelledby="ak-module-t">
        <h2 class="cc-titel" id="ak-module-t"><?= $h($a('module')) ?></h2>
        <ol class="ak-module">
          <?php foreach ($D['module'] as $m): $ms = $modulStand($m); ?>
            <li><a class="ak-modul s-<?= $h($ms['s']) ?>" href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => $weiterBei($m)])) ?>">
              <span class="ak-nr"><?= $ms['s'] === 'fertig' ? '✓' : (int) $m['nr'] ?></span>
              <span class="ak-modul__text"><b><?= $h($m['titel']) ?></b>
                <small><?= $h($a('min', ['{n}' => (string) (int) $m['minuten']])) ?><?= !empty($m['pflicht']) ? ' · ' . $h($a('pflicht')) : '' ?>
                  <?= $ms['s'] !== 'neu' ? ' · ' . $h($a($ms['s'])) : '' ?></small></span>
              <?php if ($ms['s'] === 'begonnen'): ?><span class="ak-mini" aria-hidden="true"><i style="width:<?= (int) $ms['p'] ?>%"></i></span><?php endif; ?>
            </a></li>
          <?php endforeach; ?>
        </ol>
      </section>

      <?php $weg = []; foreach ($D['module'] as $m) { foreach (($m['lektionen'] ?? []) as $l) { if (!empty($l['weg'])) { $weg = $l['weg']; } } } ?>
      <?php if ($weg): ?>
      <section class="cc-breit cc-auf z4" aria-labelledby="ak-weg-t">
        <h2 class="cc-titel" id="ak-weg-t"><?= $h($a('weg')) ?></h2>
        <ol class="ak-weg"><?php foreach ($weg as $i => $w): ?><li><span><?= $i + 1 ?></span><?= $h($w) ?></li><?php endforeach; ?></ol>
      </section>
      <?php endif; ?>
    </div>

<?php
/* =============================== MODUL =============================== */
elseif ($akSeite === 'modul'):
  $m = Academy::modul((string) ($_GET['m'] ?? ''), $sprache);
  if (!$m): ?>
    <p class="hinweis"><a href="<?= $h($L([])) ?>"><?= $h($a('zur_academy')) ?></a></p>
  <?php else:
    $lek = $m['lektionen'] ?? [];
    $lRoh = (string) ($_GET['l'] ?? '0');
    $l = in_array($lRoh, ['test', 'fertig'], true) ? $lRoh : max(0, min(count($lek) - 1, (int) $lRoh));
    if (is_int($l)) { Academy::lektionGelesen($akPid, $m['slug'], $l); $fortschritt = Academy::fortschritt($akPid); }
    $gelesen = Academy::gelesen($fortschritt[$m['slug']] ?? []);
    $hatTest = !empty($m['fragen']);
    $zurueck = ['ak' => 'modul', 'm' => $m['slug'], 'l' => (string) $l]; ?>
    <nav class="ak-pfad" aria-label="Academy"><a href="<?= $h($L([])) ?>">Academy</a> <span aria-hidden="true">/</span> <span><?= $h($a('art_modul')) ?> <?= (int) $m['nr'] ?></span></nav>
    <header class="ak-kopf cc-auf">
      <div>
        <h1><?= $h($m['titel']) ?></h1>
        <p class="ak-ziel"><b><?= $h($a('ziel')) ?>:</b> <?= $h($t($m['ziel'])) ?></p>
      </div>
      <?= $merkKnopf('modul', $m['slug'], $zurueck) ?>
    </header>
    <ol class="ak-punkte" aria-label="<?= $h($a('lektion', ['{n}' => '', '{gesamt}' => (string) count($lek)])) ?>">
      <?php foreach ($lek as $i => $x): ?>
        <li class="<?= in_array($i, $gelesen, true) ? 'ok' : '' ?><?= $l === $i ? ' jetzt' : '' ?>"><a href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => (string) $i])) ?>" title="<?= $h($x['titel']) ?>"><span class="sr-only"><?= $h($x['titel']) ?></span></a></li>
      <?php endforeach; ?>
      <?php if ($hatTest): ?><li class="test<?= in_array($l, ['test', 'fertig'], true) ? ' jetzt' : '' ?><?= ($fortschritt[$m['slug']]['fertig_am'] ?? null) !== null ? ' ok' : '' ?>"><a href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => 'test'])) ?>" title="<?= $h($a('test')) ?>"><span class="sr-only"><?= $h($a('test')) ?></span></a></li><?php endif; ?>
    </ol>

    <?php if (is_int($l)): $x = $lek[$l]; ?>
      <article class="ak-lektion cc-karte cc-auf">
        <p class="ak-auge"><?= $h($a('lektion', ['{n}' => (string) ($l + 1), '{gesamt}' => (string) count($lek)])) ?></p>
        <h2><?= $h($x['titel']) ?></h2>
        <?= !empty($x['text']) ? $absaetze((string) $x['text']) : '' ?>
        <?= $liste($x['punkte'] ?? []) ?>
        <?php if (!empty($x['fragenliste'])): ?><h3><?= $h($a('fragenliste')) ?></h3><?= $liste($x['fragenliste'], 'ak-fragen') ?><?php endif; ?>
        <?php if (!empty($x['darf']) || !empty($x['darf_nicht'])): ?>
          <div class="ak-zwei">
            <div class="ak-gut"><h3><?= $h($a('darf')) ?></h3><?= $liste($x['darf'] ?? []) ?></div>
            <div class="ak-schlecht"><h3><?= $h($a('darf_nicht')) ?></h3><?= $liste($x['darf_nicht'] ?? []) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($x['gut']) || !empty($x['schlecht'])): ?>
          <div class="ak-zwei">
            <?php if (!empty($x['schlecht'])): ?><div class="ak-schlecht"><h3><?= $h($a('schlecht')) ?></h3><?= $liste($x['schlecht']) ?></div><?php endif; ?>
            <?php if (!empty($x['gut'])): ?><div class="ak-gut"><h3><?= $h($a('gut')) ?></h3><?= $liste($x['gut']) ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($x['weg'])): ?><ol class="ak-weg"><?php foreach ($x['weg'] as $i => $w): ?><li><span><?= $i + 1 ?></span><?= $h($w) ?></li><?php endforeach; ?></ol><?php endif; ?>
        <?php if (!empty($x['merke'])): ?><p class="ak-merke"><b><?= $h($a('merke')) ?>:</b> <?= $h($t($x['merke'])) ?></p><?php endif; ?>
        <?php $bezug = ['erstkontakt' => ['kontakt', 's_kontakt', 's_kontakt_satz'], 'vecom-praesentieren' => ['leistungen', 's_leistung', 's_leistung_satz'], 'einwaende' => ['einwaende', 's_einwand', 's_einwand_satz']][$m['slug']] ?? null;
          if ($bezug && $l === count($lek) - 1): ?>
          <a class="cc-ziel ak-bezug" href="<?= $h($ziel($bezug[0])) ?>"><i aria-hidden="true"><svg viewBox="0 0 24 24"><?= $icon[$bezug[0] === 'einwaende' ? 'einwand' : ($bezug[0] === 'leistungen' ? 'leistung' : 'kontakt')] ?></svg></i>
            <span><b><?= $h($a($bezug[1])) ?></b><small><?= $h($a($bezug[2])) ?></small></span></a>
        <?php endif; ?>
      </article>
      <div class="ak-nav">
        <?php if ($l > 0): ?><a class="knopf stumm" href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => (string) ($l - 1)])) ?>">← <?= $h($a('zurueck')) ?></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($l < count($lek) - 1): ?>
          <a class="knopf haupt" href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => (string) ($l + 1)])) ?>"><?= $h($a('weiter')) ?> →</a>
        <?php elseif ($hatTest): ?>
          <a class="knopf haupt" href="<?= $h($ziel('modul', ['m' => $m['slug'], 'l' => 'test'])) ?>"><?= $h($a('zum_test')) ?> →</a>
        <?php else: ?>
          <form method="post"><input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_test"><input type="hidden" name="m" value="<?= $h($m['slug']) ?>">
            <button class="knopf haupt" type="submit"><?= $h($a('abschliessen')) ?> ✓</button></form>
        <?php endif; ?>
      </div>

    <?php elseif ($l === 'test'): ?>
      <?php if (($_GET['e'] ?? '') === 'lesen'): ?><p class="hinweis schlecht" role="alert"><?= $h($a('zuerst_lesen')) ?></p><?php endif; ?>
      <form class="ak-test cc-karte cc-auf" method="post">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_test"><input type="hidden" name="m" value="<?= $h($m['slug']) ?>">
        <p class="ak-auge"><?= $h($a('test')) ?></p>
        <p class="hilfe"><?= $h($a('test_satz')) ?></p>
        <?php foreach (($m['fragen'] ?? []) as $fi => $fr): ?>
          <fieldset><legend><?= $h($t($fr['frage'])) ?></legend>
            <?php foreach ($fr['antworten'] as $ai => $an): ?>
              <label class="ak-wahl"><input type="radio" name="a[<?= (int) $fi ?>]" value="<?= (int) $ai ?>" required> <span><?= $h($t($an)) ?></span></label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
        <button class="knopf haupt" type="submit"><?= $h($a('abschliessen')) ?> ✓</button>
      </form>

    <?php else: /* fertig */
      $erg = $_SESSION['ak_ergebnis'] ?? null;
      if (!is_array($erg) || ($erg['m'] ?? '') !== $m['slug']) { $erg = null; }
      $nx = Academy::stand($akPid, $sprache)['naechstes']; ?>
      <section class="ak-fertig cc-heute cc-auf">
        <p class="cc-auge"><?= $h($a('modul_fertig_t')) ?></p>
        <h2><?= $h($m['titel']) ?> ✓</h2>
        <?php if ($erg && (int) $erg['fragen'] > 0): ?>
          <p class="ak-prozent"><b><?= $h($a('ergebnis', ['{r}' => (string) (int) $erg['richtig'], '{n}' => (string) (int) $erg['fragen']])) ?></b></p>
          <ol class="ak-auswertung">
            <?php foreach ($m['fragen'] as $fi => $fr): $ok = !empty($erg['auswertung'][$fi]['ok']); ?>
              <li class="<?= $ok ? 'ok' : 'nein' ?>"><b><?= $h($t($fr['frage'])) ?></b>
                <span><?= $ok ? '✓' : '✗' ?> <?= $h($a('richtig_war')) ?> <?= $h($t($fr['antworten'][(int) $fr['richtig']])) ?></span>
                <small><?= $h($t($fr['warum'])) ?></small></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <div class="ak-nav">
          <a class="knopf stumm" href="<?= $h($L([])) ?>"><?= $h($a('zur_academy')) ?></a>
          <?php if ($nx): ?><a class="knopf haupt" href="<?= $h($ziel('modul', ['m' => $nx['slug'], 'l' => $weiterBei($nx)])) ?>"><?= $h($a('naechstes_modul')) ?>: <?= $h($nx['titel']) ?> →</a><?php endif; ?>
        </div>
      </section>
      <?php unset($_SESSION['ak_ergebnis']); ?>
    <?php endif; ?>

    <?php $notizen = Academy::notizen($akPid, $m['slug']); ?>
    <section class="ak-notizen cc-karte" id="notizen" aria-labelledby="ak-notizen-t">
      <h2 class="cc-titel" id="ak-notizen-t"><?= $h($a('notizen')) ?></h2>
      <?php if (($_GET['e'] ?? '') === 'notiz'): ?><p class="hinweis gut" role="status"><?= $h($a('notiz_gut')) ?></p><?php endif; ?>
      <form method="post" class="ak-notiz-form">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_notiz">
        <input type="hidden" name="ak" value="modul"><input type="hidden" name="m" value="<?= $h($m['slug']) ?>"><input type="hidden" name="l" value="<?= $h((string) $l) ?>">
        <label class="sr-only" for="ak-notiz"><?= $h($a('notiz_ph')) ?></label>
        <textarea id="ak-notiz" name="text" rows="2" maxlength="<?= Academy::NOTIZ_MAX ?>" placeholder="<?= $h($a('notiz_ph')) ?>"></textarea>
        <button class="knopf" type="submit"><?= $h($a('notiz_speichern')) ?></button>
      </form>
      <?php foreach ($notizen as $nz): ?>
        <div class="ak-notiz"><p><?= nl2br($h((string) $nz['text'])) ?></p>
          <form method="post"><input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_notiz_weg"><input type="hidden" name="id" value="<?= (int) $nz['id'] ?>">
            <input type="hidden" name="ak" value="modul"><input type="hidden" name="m" value="<?= $h($m['slug']) ?>"><input type="hidden" name="l" value="<?= $h((string) $l) ?>">
            <button class="ak-leise" type="submit"><?= $h($a('notiz_weg')) ?></button></form></div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

<?php
/* =============================== EINWÄNDE =============================== */
elseif ($akSeite === 'einwaende'):
  $eSlug = (string) ($_GET['e'] ?? '');
  $e = $eSlug !== '' ? Academy::eintrag('einwand', $eSlug, $sprache) : null;
  if ($e): Academy::zaehlen('einwand', $e['slug']); ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a> <span aria-hidden="true">/</span> <a href="<?= $h($ziel('einwaende')) ?>"><?= $h($a('einwaende')) ?></a></nav>
    <article class="ak-einwand cc-auf">
      <header class="ak-kopf"><h1><?= $h($aufQ . $e['satz'] . $zuQ) ?></h1><?= $merkKnopf('einwand', $e['slug'], ['ak' => 'einwaende', 'e' => $e['slug']]) ?></header>
      <div class="ak-antwort cc-heute">
        <p class="cc-auge"><?= $h($a('e_antwort')) ?></p>
        <p class="ak-gross"><?= $h($t($e['antwort'])) ?></p>
      </div>
      <dl class="ak-felder">
        <div><dt><?= $h($a('e_frage')) ?></dt><dd class="ak-zitat"><?= $h($t($e['frage'])) ?></dd></div>
        <div><dt><?= $h($a('e_dahinter')) ?></dt><dd><?= $h($t($e['dahinter'])) ?></dd></div>
        <div><dt><?= $h($a('e_ziel')) ?></dt><dd><?= $h($t($e['ziel'])) ?></dd></div>
        <div><dt><?= $h($a('e_weiter')) ?></dt><dd><?= $h($t($e['weiter'])) ?></dd></div>
        <div class="schlecht"><dt><?= $h($a('e_vermeiden')) ?></dt><dd><?= $h($t($e['vermeiden'])) ?></dd></div>
      </dl>
      <p class="ak-warn" role="note"><?= $h($a('e_warn')) ?></p>
      <a class="knopf stumm ak-alle" href="<?= $h($ziel('einwaende')) ?>">← <?= $h($a('e_alle')) ?></a>
    </article>
  <?php else: ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('einwaende')) ?></h1><p class="ak-ziel"><?= $h($a('einwaende_satz')) ?></p></div></header>
    <label class="sr-only" for="ak-filter"><?= $h($a('filter')) ?></label>
    <input class="ak-filter" id="ak-filter" type="search" placeholder="<?= $h($a('filter')) ?>" data-ak-filter="#ak-einwaende" hidden>
    <ul class="ak-einwaende" id="ak-einwaende">
      <?php foreach ($D['einwaende'] as $e): ?>
        <li data-such="<?= $h(mb_strtolower($e['satz'] . ' ' . ($e['suche'] ?? ''))) ?>"><a href="<?= $h($ziel('einwaende', ['e' => $e['slug']])) ?>"><span><?= $h($aufQ . $e['satz'] . $zuQ) ?></span><?= Academy::gemerkt($merk, 'einwand', $e['slug']) ? '<i aria-label="' . $h($a('gemerkt')) . '">★</i>' : '' ?><b aria-hidden="true">→</b></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="ak-warn" role="note"><?= $h($a('e_warn')) ?></p>
  <?php endif; ?>

<?php
/* =============================== KONTAKTWEGE =============================== */
elseif ($akSeite === 'kontakt'):
  $offen = (string) ($_GET['k'] ?? ''); ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('kontakt')) ?></h1><p class="ak-ziel"><?= $h($a('kontakt_satz')) ?></p></div></header>
    <div class="ak-klapp">
      <?php foreach ($D['kontakt'] as $k): ?>
        <details id="k-<?= $h($k['slug']) ?>"<?= $offen === $k['slug'] ? ' open' : '' ?>>
          <summary><b><?= $h($k['name']) ?></b><small><?= $h($t($k['ziel'])) ?></small></summary>
          <div class="ak-klapp__inhalt">
            <h3><?= $h($a('k_eroeffnung')) ?></h3><p><?= $h($t($k['eroeffnung'])) ?></p>
            <h3><?= $h($a('k_beispiel')) ?></h3><p class="ak-zitat"><?= $h($t($k['beispiel'])) ?></p>
            <div class="ak-zwei">
              <div class="ak-gut"><h3><?= $h($a('gut')) ?></h3><?= $liste($k['gut'] ?? []) ?></div>
              <div class="ak-schlecht"><h3><?= $h($a('schlecht')) ?></h3><?= $liste($k['schlecht'] ?? []) ?></div>
            </div>
            <h3><?= $h($a('k_fehler')) ?></h3><?= $liste($k['fehler'] ?? []) ?>
            <p class="ak-merke"><b><?= $h($a('k_weiter')) ?>:</b> <?= $h($t($k['weiter'])) ?></p>
            <?= $merkKnopf('kontakt', $k['slug'], ['ak' => 'kontakt', 'k' => $k['slug']], 'k-' . $k['slug']) ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

<?php
/* =============================== LEISTUNGEN =============================== */
elseif ($akSeite === 'leistungen'):
  $offen = (string) ($_GET['s'] ?? ''); ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('leistungen')) ?></h1><p class="ak-ziel"><?= $h($a('leistungen_satz')) ?></p></div></header>
    <div class="ak-klapp">
      <?php foreach ($D['leistungen'] as $s): ?>
        <details id="s-<?= $h($s['slug']) ?>"<?= $offen === $s['slug'] ? ' open' : '' ?>>
          <summary><b><?= $h($s['name']) ?></b><small><?= $h($t($s['kurz'])) ?></small></summary>
          <div class="ak-klapp__inhalt">
            <div class="ak-30"><p class="cc-auge"><?= $h($a('l_30')) ?></p><p class="ak-gross"><?= $h($t($s['s30'])) ?></p></div>
            <div class="ak-ebenen" data-ak-ebenen>
              <section><h3><?= $h($a('l_kurz')) ?></h3><p><?= $h($t($s['kurz'])) ?></p></section>
              <section><h3><?= $h($a('l_normal')) ?></h3><p><?= $h($t($s['normal'])) ?></p></section>
              <section><h3><?= $h($a('l_aus')) ?></h3><p><?= $h($t($s['ausfuehrlich'])) ?></p></section>
            </div>
            <?= $merkKnopf('leistung', $s['slug'], ['ak' => 'leistungen', 's' => $s['slug']], 's-' . $s['slug']) ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

<?php
/* =============================== SUCHE =============================== */
elseif ($akSeite === 'suche'):
  $q = trim((string) ($_GET['q'] ?? ''));
  $treffer = Academy::suche($q, $sprache);
  $hin = static fn(array $tr): string => match ($tr['art']) {
      'einwand'  => $ziel('einwaende', ['e' => $tr['slug']]),
      'leistung' => $ziel('leistungen', ['s' => $tr['slug']]) . '#s-' . $tr['slug'],
      'kontakt'  => $ziel('kontakt', ['k' => $tr['slug']]) . '#k-' . $tr['slug'],
      default    => $ziel('modul', ['m' => $tr['slug'], 'l' => (string) (int) ($tr['lektion'] ?? 0)]),
  }; ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <h1 class="ak-h1"><?= $h($a('suche_treffer', ['{n}' => (string) count($treffer), '{q}' => $q])) ?></h1>
    <?php if (!$treffer): ?><p class="cc-leer"><?= $h($a('suche_leer')) ?></p><?php endif; ?>
    <ul class="ak-treffer">
      <?php foreach ($treffer as $tr): ?>
        <li><a href="<?= $h($hin($tr)) ?>"><small><?= $h($a('art_' . $tr['art'])) ?></small><b><?= $h($t($tr['titel'])) ?></b><span><?= $h($t($tr['auszug'])) ?></span></a></li>
      <?php endforeach; ?>
    </ul>

<?php
/* =============================== BEDARFSASSISTENT (Etappe 2) =============================== */
elseif ($akSeite === 'bedarf'):
  $bA = is_array($_GET['b'] ?? null) ? $_GET['b'] : [];
  $bFertig = isset($_GET['aus']);
  $bErg = $bFertig ? Academy::bedarfAuswerten($bA) : [];
  if ($bFertig) { Academy::zaehlen('werkzeug', 'bedarf'); } ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('bedarf')) ?></h1><p class="ak-ziel"><?= $h($a('bedarf_satz')) ?></p></div></header>
    <?php if ($bFertig): ?>
      <section class="cc-heute ak-ergebnis cc-auf" aria-labelledby="ak-bedarf-erg">
        <p class="cc-auge" id="ak-bedarf-erg"><?= $h($a('bedarf_ergebnis')) ?></p>
        <ol class="ak-empf">
          <?php foreach ($bErg as $sl): $ls = Academy::eintrag('leistung', $sl, $sprache); if (!$ls) { continue; } ?>
            <li><details><summary><b><?= $h($ls['name']) ?></b><small><?= $h($t((string) ($D['bedarf_grund'][$sl] ?? $ls['kurz']))) ?></small></summary>
              <div class="ak-30"><p class="cc-auge"><?= $h($a('bedarf_spiegel')) ?></p><p class="ak-gross"><?= $h($t($ls['s30'])) ?></p>
                <a href="<?= $h($ziel('leistungen', ['s' => $sl]) . '#s-' . $sl) ?>"><?= $h($a('l_aus')) ?> →</a></div></details></li>
          <?php endforeach; ?>
        </ol>
        <p class="ak-warn" role="note"><?= $h($a('bedarf_hinweis')) ?></p>
      </section>
      <?= $linkBox() ?>
      <p class="ak-nav"><a class="knopf stumm" href="<?= $h($ziel('bedarf')) ?>"><?= $h($a('bedarf_neu')) ?></a></p>
    <?php else: ?>
      <form class="ak-test ak-fragebogen cc-karte cc-auf" method="get" action="/partner.php">
        <input type="hidden" name="t" value="<?= $h((string) $p['token']) ?>"><input type="hidden" name="ak" value="bedarf"><input type="hidden" name="aus" value="1">
        <?php foreach ($D['bedarf'] as $q): $mehr = ($q['typ'] ?? '') === 'mehr'; ?>
          <fieldset><legend><?= $h($q['frage']) ?><?php if ($mehr): ?> <small>(<?= $h($a('mehrfach')) ?>)</small><?php endif; ?></legend>
            <div class="ak-chips">
            <?php foreach ($q['optionen'] as $ow => $ot): ?>
              <label class="ak-chip"><input type="<?= $mehr ? 'checkbox' : 'radio' ?>" name="b[<?= $h($q['k']) ?>]<?= $mehr ? '[]' : '' ?>" value="<?= $h((string) $ow) ?>"> <span><?= $h($ot) ?></span></label>
            <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
        <p class="hilfe"><?= $h($a('bedarf_privat')) ?></p>
        <button class="knopf haupt" type="submit"><?= $h($a('bedarf_los')) ?> →</button>
      </form>
    <?php endif; ?>

<?php
/* =============================== KUNDENFINDER-CHECKLISTE (Etappe 2) =============================== */
elseif ($akSeite === 'finder'):
  $fA = is_array($_GET['f'] ?? null) ? array_map('strval', array_filter($_GET['f'], 'is_scalar')) : [];
  $fFertig = isset($_GET['aus']);
  $fErg = $fFertig ? Academy::finderAuswerten($fA) : null;
  if ($fFertig) { Academy::zaehlen('werkzeug', 'finder'); } ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('finder')) ?></h1><p class="ak-ziel"><?= $h($a('finder_satz')) ?></p></div></header>
    <?php if ($fErg): ?>
      <section class="cc-heute ak-ergebnis ak-stufe-<?= $h($fErg['stufe']) ?> cc-auf" aria-labelledby="ak-finder-erg">
        <p class="cc-auge" id="ak-finder-erg"><?= $h($a('finder_ergebnis')) ?></p>
        <p class="ak-stufe"><b><?= $h($a('finder_stufe_' . $fErg['stufe'])) ?></b></p>
        <p class="ak-gross"><?= $h((string) preg_replace('~^[^:]{3,40}:\s*~u', '', $t((string) ($D['finder_ergebnis'][$fErg['stufe']] ?? '')))) ?></p>
        <?php if ($fErg['beantwortet'] < 6 && ($fA['website'] ?? '') !== 'nein'): ?><p class="ak-warn" role="note"><?= $h($a('finder_wenig')) ?></p><?php endif; ?>
        <div class="ak-knoepfe">
          <a class="knopf haupt" href="<?= $h($selbst() . '#schnellcheck') ?>"><?= $h($a('finder_check')) ?> →</a>
          <a class="knopf" href="<?= $h($ziel('jetzt', ['lage' => 'reserviert'])) ?>"><?= $h($a('jetzt')) ?></a>
        </div>
      </section>
      <p class="ak-nav"><a class="knopf stumm" href="<?= $h($ziel('finder')) ?>"><?= $h($a('finder_neu')) ?></a></p>
    <?php else: ?>
      <form class="ak-test ak-fragebogen cc-karte cc-auf" method="get" action="/partner.php">
        <input type="hidden" name="t" value="<?= $h((string) $p['token']) ?>"><input type="hidden" name="ak" value="finder"><input type="hidden" name="aus" value="1">
        <?php foreach ($D['finder'] as $q): ?>
          <fieldset><legend><?= $h($q['frage']) ?></legend>
            <div class="ak-chips">
            <?php foreach ($q['optionen'] as $ow): ?>
              <label class="ak-chip"><input type="radio" name="f[<?= $h($q['k']) ?>]" value="<?= $h((string) $ow) ?>"> <span><?= $h($a('opt_' . $ow)) ?></span></label>
            <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
        <p class="hilfe"><?= $h($a('finder_privat')) ?></p>
        <button class="knopf haupt" type="submit"><?= $h($a('finder_los')) ?> →</button>
      </form>
    <?php endif; ?>

<?php
/* =============================== WAS MACHE ICH JETZT? (Etappe 2) =============================== */
elseif ($akSeite === 'jetzt'):
  $lg = Academy::eintrag('lage', (string) ($_GET['lage'] ?? ''), $sprache);
  if ($lg) { Academy::zaehlen('lage', $lg['slug']); } ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('jetzt')) ?></h1><p class="ak-ziel"><?= $h($a('jetzt_satz')) ?></p></div></header>
    <?php if ($lg): ?>
      <section class="cc-heute ak-ergebnis cc-auf" aria-labelledby="ak-lage-t">
        <p class="cc-auge"><?= $h($a('jetzt_wahl')) ?></p>
        <h2 id="ak-lage-t"><?= $h($lg['titel']) ?></h2>
        <p class="ak-gross"><?= $h($t($lg['empfehlung'])) ?></p>
        <?php if (!empty($lg['schritte'])): ?>
          <h3><?= $h($a('jetzt_schritte')) ?></h3>
          <ol class="ak-weg"><?php foreach ($lg['schritte'] as $i => $w): ?><li><span><?= $i + 1 ?></span><?= $h($t($w)) ?></li><?php endforeach; ?></ol>
        <?php endif; ?>
      </section>
      <?php $kn = []; $mitLink = false;
        foreach (($lg['knoepfe'] ?? []) as $code) {
          [$art, $was] = array_pad(explode(':', (string) $code, 2), 2, '');
          if ($art === 'link') { $mitLink = true; continue; }
          $e = null;
          $eintrag = match ($art) {
            'finder' => [$ziel('finder'), $a('kn_finder'), 'kunden'],
            'bedarf' => [$ziel('bedarf'), $a('kn_bedarf'), 'bedarf'],
            'notiz' => [$ziel('meine') . '#notizen', $a('kn_notiz'), 'meine'],
            'einwaende' => [$ziel('einwaende'), $a('kn_einwaende'), 'einwand'],
            'check' => [$selbst() . '#schnellcheck', $a('kn_check'), 'kunden'],
            'kontakt' => ($e = Academy::eintrag('kontakt', $was, $sprache)) ? [$ziel('kontakt', ['k' => $was]) . '#k-' . $was, $a('kn_kontakt', ['{name}' => $e['name']]), 'kontakt'] : null,
            'modul' => ($e = Academy::modul($was, $sprache)) ? [$ziel('modul', ['m' => $was, 'l' => $weiterBei($e)]), $a('kn_modul', ['{name}' => $e['titel']]), 'academy'] : null,
            'einwand' => ($e = Academy::eintrag('einwand', $was, $sprache)) ? [$ziel('einwaende', ['e' => $was]), $a('kn_einwand', ['{name}' => $aufQ . $e['satz'] . $zuQ]), 'einwand'] : null,
            default => null,
          };
          if ($eintrag) { $kn[] = $eintrag; }
        } ?>
      <?php if ($kn): ?>
      <section class="cc-auf z2" aria-labelledby="ak-hilfe-t">
        <h2 class="cc-titel" id="ak-hilfe-t"><?= $h($a('jetzt_hilfe')) ?></h2>
        <ul class="cc-ziele">
          <?php foreach ($kn as [$url, $txt, $ic]): ?>
            <li><a class="cc-ziel" href="<?= $h($url) ?>"><i aria-hidden="true"><svg viewBox="0 0 24 24"><?= $icon[$ic] ?? $icon['academy'] ?></svg></i><span><b><?= $h($txt) ?></b></span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
      <?php if ($mitLink) { echo $linkBox(); } ?>
      <h2 class="cc-titel ak-abstand"><?= $h($a('jetzt_andere')) ?></h2>
    <?php else: ?>
      <h2 class="cc-titel"><?= $h($a('jetzt_wahl')) ?></h2>
    <?php endif; ?>
    <ul class="ak-lagen">
      <?php foreach ($D['lagen'] as $i => $x): ?>
        <li><a href="<?= $h($ziel('jetzt', ['lage' => $x['slug']])) ?>"<?= $lg && $lg['slug'] === $x['slug'] ? ' aria-current="true"' : '' ?>><span><?= $i + 1 ?></span><?= $h($x['titel']) ?></a></li>
      <?php endforeach; ?>
    </ul>

<?php
/* =============================== PDF-BIBLIOTHEK (Etappe 2) =============================== */
elseif ($akSeite === 'bibliothek'):
  $docs = Academy::dokumente($sprache);
  $zuletzt = Academy::zuletzt($akPid, 3);
  $pdfUrl = static fn(string $d, bool $laden = false): string => $start(['ak' => 'pdf', 'd' => $d] + ($laden ? ['laden' => 1] : []));
  $neuBis = date('Y-m-d', time() - 14 * 86400); ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <header class="ak-kopf"><div><h1><?= $h($a('bibliothek')) ?></h1><p class="ak-ziel"><?= $h($a('bibliothek_satz')) ?></p></div></header>
    <?php if (($_GET['e'] ?? '') === 'kein'): ?><p class="hinweis schlecht" role="alert"><?= $h($a('b_kein')) ?></p><?php endif; ?>
    <?php $zl = array_values(array_filter(array_map(static fn($z) => Academy::dokument($z, $sprache), $zuletzt))); if ($zl): ?>
      <p class="ak-zuletzt"><?= $h($a('b_zuletzt')) ?>:
        <?php foreach ($zl as $i => $zd): ?><?= $i ? ' · ' : '' ?><a href="<?= $h($pdfUrl($zd['slug'])) ?>" target="_blank" rel="noopener"><?= $h($zd['titel']) ?></a><?php endforeach; ?></p>
    <?php endif; ?>
    <?php if (!$docs): ?><p class="cc-leer"><?= $h($a('b_leer')) ?></p><?php endif; ?>
    <ul class="ak-docs">
      <?php foreach ($docs as $dc): ?>
        <li class="ak-doc cc-karte" id="d-<?= $h($dc['slug']) ?>">
          <p class="ak-doc__kat"><?= $h($a('d_kat_' . $dc['kategorie']) ?: $dc['kategorie']) ?><?php if ($dc['eigen'] && $dc['stand'] >= $neuBis): ?> <span class="ak-neu"><?= $h($a('neu_hinweis')) ?></span><?php endif; ?></p>
          <h2><?= $h($dc['titel']) ?></h2>
          <p class="ak-doc__stand"><?= $dc['eigen'] ? $h($a('b_version') . ' ' . $dc['version']) . ' · ' : '' ?><?= $h($a('d_stand')) ?> <?= $h(Fmt::datum($dc['stand'])) ?> · PDF</p>
          <div class="ak-knoepfe">
            <a class="knopf haupt" href="<?= $h($pdfUrl($dc['slug'])) ?>" target="_blank" rel="noopener"><?= $h($a('b_ansehen')) ?></a>
            <a class="knopf" href="<?= $h($pdfUrl($dc['slug'], true)) ?>"><?= $h($a('b_laden')) ?></a>
            <a class="knopf stumm ak-nur-pc" href="<?= $h($pdfUrl($dc['slug'])) ?>" target="_blank" rel="noopener" data-ak-drucken><?= $h($a('b_drucken')) ?></a>
            <?= $merkKnopf('pdf', $dc['slug'], ['ak' => 'bibliothek'], 'd-' . $dc['slug']) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

<?php
/* =============================== MEINE ACADEMY =============================== */
else: /* meine */
  $alleNotizen = Academy::notizen($akPid); ?>
    <nav class="ak-pfad"><a href="<?= $h($L([])) ?>">Academy</a></nav>
    <h1 class="ak-h1"><?= $h($a('merkliste')) ?></h1>
    <?php if (!$merk): ?><p class="cc-leer"><?= $h($a('merkliste_leer')) ?></p><?php endif; ?>
    <ul class="ak-treffer">
      <?php foreach ($merk as $mz): $en = Academy::eintrag($mz['art'], $mz['ziel'], $sprache); if (!$en) { continue; }
        $url = match ($mz['art']) {
            'einwand' => $ziel('einwaende', ['e' => $mz['ziel']]), 'leistung' => $ziel('leistungen', ['s' => $mz['ziel']]) . '#s-' . $mz['ziel'],
            'kontakt' => $ziel('kontakt', ['k' => $mz['ziel']]) . '#k-' . $mz['ziel'], 'pdf' => $start(['ak' => 'pdf', 'd' => $mz['ziel']]), default => $ziel('modul', ['m' => $mz['ziel'], 'l' => $weiterBei($en)]) }; ?>
        <li><a href="<?= $h($url) ?>"><small><?= $h($a('art_' . $mz['art'])) ?></small><b><?= $h($en['satz'] ?? $en['name'] ?? $en['titel']) ?></b></a></li>
      <?php endforeach; ?>
    </ul>
    <h2 class="cc-titel ak-abstand" id="notizen"><?= $h($a('notizen')) ?></h2>
    <section class="ak-notizen cc-karte">
      <?php if (($_GET['e'] ?? '') === 'notiz'): ?><p class="hinweis gut" role="status"><?= $h($a('notiz_gut')) ?></p><?php endif; ?>
      <form method="post" class="ak-notiz-form">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_notiz"><input type="hidden" name="ak" value="meine">
        <label class="sr-only" for="ak-notiz2"><?= $h($a('notiz_ph')) ?></label>
        <textarea id="ak-notiz2" name="text" rows="2" maxlength="<?= Academy::NOTIZ_MAX ?>" placeholder="<?= $h($a('notiz_ph')) ?>"></textarea>
        <button class="knopf" type="submit"><?= $h($a('notiz_speichern')) ?></button>
      </form>
      <?php foreach ($alleNotizen as $nz): $nm = $nz['modul'] !== '' ? Academy::modul((string) $nz['modul'], $sprache) : null; ?>
        <div class="ak-notiz"><?php if ($nm): ?><small><?= $h($nm['titel']) ?></small><?php endif; ?><p><?= nl2br($h((string) $nz['text'])) ?></p>
          <form method="post"><input type="hidden" name="_csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tat" value="ak_notiz_weg"><input type="hidden" name="id" value="<?= (int) $nz['id'] ?>"><input type="hidden" name="ak" value="meine">
            <button class="ak-leise" type="submit"><?= $h($a('notiz_weg')) ?></button></form></div>
      <?php endforeach; ?>
    </section>
<?php endif; ?>
    <p class="ak-intern"><?= $h($a('intern')) ?></p>
  </main>
</div>
<?php if (!($akSeite === 'einwaende' && empty($_GET['e']))): ?>
<a class="ak-hilfe" href="<?= $h($ziel('einwaende')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon['einwand'] ?></svg><span><?= $h($a('s_einwand')) ?></span></a>
<?php endif; ?>
</body>
</html>
