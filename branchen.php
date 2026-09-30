<?php
declare(strict_types=1);
/* ==========================================================================
   branchen.php — öffentliche Branchen-Stadt-Seiten (28.09.2026, Uwe: Ja zu W1).

     /siti-web/                 Übersicht aller Seiten
     /siti-web/restaurant-agrigento[?lang=de|en]
                                „Websites der Restaurants in Agrigent“

   Nur anonyme Anteile aus den Website-Prüfungen, ab BranchenStatistik::MIN
   geprüften Betrieben. Kein Betrieb wird genannt. Ziel: Wer sich darin
   wiedererkennt, prüft seine eigene Seite (analisi.php) -- und sagt dort
   selbst Ja. Niemand wird angeschrieben.
   ========================================================================== */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Il servizio non è disponibile.'); }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Akquise', 'BranchenStatistik'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$slug = strtolower((string) ($_GET['s'] ?? ''));
$s = null; $alle = [];
try {
    $s = $slug !== '' ? BranchenStatistik::laden($slug) : null;
    $alle = BranchenStatistik::liste(null, 500);
} catch (Throwable $e) { $s = null; }
$sprache = isset($_GET['lang']) ? Sprache::ausAnfrage() : ($s && $s['land'] === 'DE' ? 'de' : 'it');
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

$T = [
    'it' => ['ueber' => 'Com\'è la situazione dei siti web? Per settore e città', 'ueberText' => 'Dati anonimi dalle nostre analisi automatiche. Scelga il suo settore e la sua città.',
             'titel' => '{Mz} {in}: com\'è il loro sito web?', 'kern' => 'Il {p} % {wort}.', 'grund' => 'Abbiamo analizzato {g} siti ({n} attività in totale) — dati anonimi, aggiornati il {datum}.',
             'balken' => 'Che cosa abbiamo trovato', 'ohneAnteil' => 'di tutte le attività',
             'cta' => 'E il suo sito? Lo scopra in 30 secondi.', 'ctaText' => 'Analisi gratuita dello stesso tipo, con un rapporto chiaro su cosa migliorare. Senza impegno.',
             'ohneWeb' => 'Non ha ancora un sito? Veda subito una bozza con il suo nome →', 'knopf' => 'Analisi gratuita', 'preis' => 'Prezzo indicativo in 90 secondi', 'weitere' => 'Altre città', 'andere' => 'Altri settori {in}',
             'methode' => 'Come contiamo: un programma controlla ogni sito come lo vede un cliente al telefono (velocità, leggibilità, sicurezza, contatto, Google). Mostriamo solo percentuali e solo quando i siti analizzati sono almeno {min}. Nessuna attività viene nominata.',
             'bedeutet' => 'Che cosa significa per i clienti', 'preisTitel' => 'Un sito per {mz}: prezzo indicativo', 'preisAb' => 'da {ab}', 'preisText' => 'Con le funzioni che servono di solito a {mz}. Il suo prezzo esatto in 90 secondi, senza impegno.', 'faq' => 'Domande frequenti', 'start' => 'Inizio',
             'nichts' => 'Questa pagina non è (più) disponibile.', 'uebersicht' => 'Tutti i settori e le città', 'leer' => 'Le prime pagine arrivano appena le analisi bastano.'],
    'de' => ['ueber' => 'Wie steht es um die Websites? Nach Branche und Ort', 'ueberText' => 'Anonyme Werte aus unseren automatischen Prüfungen. Wählen Sie Ihre Branche und Ihren Ort.',
             'titel' => '{Mz} {in}: Wie gut sind die Websites?', 'kern' => '{p} % {wort}.', 'grund' => 'Wir haben {g} Websites geprüft ({n} Betriebe insgesamt) — anonym, Stand {datum}.',
             'balken' => 'Was wir gefunden haben', 'ohneAnteil' => 'aller Betriebe',
             'cta' => 'Und Ihre Website? In 30 Sekunden wissen Sie es.', 'ctaText' => 'Dieselbe Analyse kostenlos, mit einem klaren Bericht, was sich verbessern lässt. Unverbindlich.',
             'ohneWeb' => 'Noch keine Website? Sehen Sie sofort eine Skizze mit Ihrem Namen →', 'knopf' => 'Kostenlose Analyse', 'preis' => 'Richtpreis in 90 Sekunden', 'weitere' => 'Weitere Orte', 'andere' => 'Andere Branchen {in}',
             'methode' => 'So zählen wir: Ein Programm prüft jede Website so, wie ein Kunde sie am Handy sieht (Tempo, Lesbarkeit, Sicherheit, Kontakt, Google). Wir zeigen nur Anteile und nur, wenn mindestens {min} Websites geprüft sind. Kein Betrieb wird genannt.',
             'bedeutet' => 'Was das für die Kunden bedeutet', 'preisTitel' => 'Website für {mz}: Richtpreis', 'preisAb' => 'ab {ab}', 'preisText' => 'Mit den Funktionen, die {mz} üblicherweise brauchen. Ihren genauen Preis sehen Sie in 90 Sekunden, unverbindlich.', 'faq' => 'Häufige Fragen', 'start' => 'Start',
             'nichts' => 'Diese Seite ist nicht (mehr) verfügbar.', 'uebersicht' => 'Alle Branchen und Orte', 'leer' => 'Die ersten Seiten erscheinen, sobald genug Prüfungen vorliegen.'],
    'en' => ['ueber' => 'How are local websites doing? By industry and town', 'ueberText' => 'Anonymous figures from our automated checks. Pick your industry and town.',
             'titel' => '{Mz} {in}: how good are their websites?', 'kern' => '{p} % {wort}.', 'grund' => 'We checked {g} websites ({n} businesses in total) — anonymous, as of {datum}.',
             'balken' => 'What we found', 'ohneAnteil' => 'of all businesses',
             'cta' => 'And your website? Find out in 30 seconds.', 'ctaText' => 'The same analysis for free, with a clear report on what to improve. No obligation.',
             'ohneWeb' => 'No website yet? See a sketch with your name right away →', 'knopf' => 'Free analysis', 'preis' => 'Guide price in 90 seconds', 'weitere' => 'Other towns', 'andere' => 'Other industries {in}',
             'methode' => 'How we count: a program checks each website the way a customer sees it on a phone (speed, readability, security, contact, Google). We only show percentages, and only when at least {min} websites were checked. No business is named.',
             'bedeutet' => 'What this means for customers', 'preisTitel' => 'A website for {mz}: guide price', 'preisAb' => 'from {ab}', 'preisText' => 'With the features {mz} usually need. See your exact price in 90 seconds, no obligation.', 'faq' => 'Frequently asked questions', 'start' => 'Home',
             'nichts' => 'This page is not (or no longer) available.', 'uebersicht' => 'All industries and towns', 'leer' => 'The first pages appear as soon as there are enough checks.'],
][$sprache];
$W = static fn(string $k): string => BranchenStatistik::WORTE[$k][$sprache];
/* Ohne ?lang= gilt die Sprache des Landes (IT italienisch, DE deutsch) -- nur Abweichungen tragen den Parameter. */
$adr = static fn(?array $x, string $l): string => $basis . '/siti-web/' . ($x ? $x['slug'] : '')
    . ($l === (($x['land'] ?? 'IT') === 'DE' ? 'de' : 'it') ? '' : '?lang=' . $l);

if ($slug !== '' && $s === null) { http_response_code(404); }
if ($s) {
    [$kk, $kp] = BranchenStatistik::staerkste($s);
    $mz = BranchenStatistik::mehrzahl($s['branche'], $sprache);
    $titel = strtr($T['titel'], ['{Mz}' => mb_strtoupper(mb_substr($mz, 0, 1)) . mb_substr($mz, 1), '{in}' => BranchenStatistik::in($s['ort'], $sprache)]);
    $kern = strtr($T['kern'], ['{p}' => (string) $kp, '{wort}' => $W($kk)]);
    $beschreibung = $titel . ': ' . $kern . ' ' . strtr($T['grund'], ['{g}' => (string) $s['geprueft'], '{n}' => (string) $s['n'], '{datum}' => date('d.m.Y', strtotime($s['aktualisiert']))]);
    $kanon = $adr($s, $sprache);
} else {
    $titel = $T['ueber']; $beschreibung = $T['ueberText']; $kanon = $adr(null, $sprache);
}
$cta = '/analisi.php?lang=' . $sprache;
/* Kundenfinder (30.09.2026): Wer noch keine Website hat, sieht auf der Startseite sofort eine Skizze mit seinem Namen. */
$ohneWeb = ($sprache === 'it' ? '/' : '/' . $sprache . '/') . '#vorschau';
$preisLink = '/zugang.php?lang=' . $sprache;
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($titel) ?> — Vecom Design</title>
<meta name="description" content="<?= $h(mb_substr($beschreibung, 0, 158)) ?>">
<?php if ($slug !== '' && $s === null): ?><meta name="robots" content="noindex"><?php endif; ?>
<link rel="canonical" href="<?= $h($kanon) ?>">
<?php foreach (['it', 'de', 'en'] as $l): ?><link rel="alternate" hreflang="<?= $l ?>" href="<?= $h($adr($s, $l)) ?>">
<?php endforeach; ?>
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96"><link rel="icon" href="/assets/img/favicon-48.png" sizes="48x48">
<meta property="og:title" content="<?= $h($titel) ?>"><meta property="og:description" content="<?= $h(mb_substr($beschreibung, 0, 200)) ?>">
<meta property="og:image" content="<?= $h($basis) ?>/assets/img/og-image.jpg"><meta property="og:url" content="<?= $h($kanon) ?>">
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  html{background:#0a0908}
  body{margin:0;min-height:100vh;background:radial-gradient(1200px 600px at 50% -200px,rgba(241,211,139,.09),transparent 70%),#0a0908;color:#f7f3ea;font:17px/1.6 'Inter',system-ui,sans-serif}
  main{max-width:780px;margin:0 auto;padding:28px 16px 44px}
  a{color:#f1d38b}
  .marke{display:inline-flex;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;color:#f7f3ea;text-decoration:none;margin-bottom:26px}.marke b{color:#f1d38b}
  h1{font:800 clamp(28px,6.4vw,42px)/1.12 'Archivo',sans-serif;margin:0 0 10px}
  .kern{font:700 clamp(21px,4.6vw,27px)/1.3 'Archivo',sans-serif;color:#f1d38b;margin:0 0 8px}
  .grund{color:#c9c1b3;margin:0 0 26px;font-size:15.5px}
  h2{font:700 20px/1.3 'Archivo',sans-serif;margin:30px 0 12px}
  .zeilen{display:grid;gap:14px;margin:0;padding:0;list-style:none}
  .zeilen li{display:grid;gap:6px}
  .zeilen .z{display:flex;justify-content:space-between;gap:12px;font-size:16px}
  .zeilen .z b{font:800 20px/1 'Archivo',sans-serif;color:#f7e6ae;white-space:nowrap}
  .zeilen .bar{height:10px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
  .zeilen .bar i{display:block;height:100%;border-radius:999px;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .cta{margin-top:30px;padding:22px 20px;border:1px solid rgba(241,211,139,.4);border-radius:18px;background:rgba(241,211,139,.05)}
  .cta p{margin:6px 0 16px;color:#d8d0c1}
  .knoepfe{display:flex;gap:10px;flex-wrap:wrap}
  .haupt{display:inline-flex;align-items:center;min-height:52px;padding:0 24px;border-radius:12px;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;font-weight:700;text-decoration:none;font-size:17px}
  .neben{display:inline-flex;align-items:center;min-height:52px;padding:0 20px;border-radius:12px;border:1px solid rgba(241,211,139,.45);color:#f7f3ea;text-decoration:none}
  .haupt:focus-visible,.neben:focus-visible,.links a:focus-visible{outline:3px solid #f1d38b;outline-offset:3px}
  .links{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}
  .links a{display:inline-flex;min-height:44px;align-items:center;padding:0 14px;border:1px solid rgba(255,255,255,.14);border-radius:999px;color:#f7f3ea;text-decoration:none;font-size:15px}
  .methode{margin-top:34px;color:#a39b8d;font-size:14px;line-height:1.6}
  /* Fußbereich (29.09.2026): Marke, Rechtliches und Sprachen in einer ruhigen Leiste statt loser Links am Rand */
  .fussbereich{max-width:780px;margin:0 auto;padding:0 16px 36px}
  .fb-innen{border-top:1px solid rgba(241,211,139,.22);padding-top:22px;display:grid;gap:14px}
  .fb-kopf{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:6px 24px}
  .fb-marke{font:800 13px/1.4 'Archivo',sans-serif;letter-spacing:.08em;color:#f7f3ea;text-decoration:none}.fb-marke b{color:#f1d38b}
  .fb-marke span{display:block;font:400 13.5px/1.5 'Inter',system-ui,sans-serif;letter-spacing:0;color:#a39b8d;margin-top:3px}
  .rechtsfuss{display:flex;flex-wrap:wrap;gap:0 20px;margin:0;padding:0;border:0;font-size:14.5px}
  .rechtsfuss a{color:#c9c1b3;text-decoration:none;min-height:44px;display:inline-flex;align-items:center}
  .rechtsfuss a:hover,.rechtsfuss a:focus-visible,.sprachen a:hover{color:#f1d38b;text-decoration:underline;text-underline-offset:4px}
  .rechtsfuss span{display:none}
  .fb-fuss{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px 24px;font-size:13.5px;color:#8a8275}
  .sprachen{display:flex;gap:6px;margin:0 -12px}
  .sprachen a{display:inline-flex;align-items:center;min-height:36px;padding:0 12px;border-radius:999px;color:#a39b8d;text-decoration:none;border:1px solid transparent}
  .sprachen a[aria-current]{color:#f7f3ea;border-color:rgba(241,211,139,.35);background:rgba(241,211,139,.06)}
  .rechtsfuss a:focus-visible,.sprachen a:focus-visible,.fb-marke:focus-visible{outline:3px solid #f1d38b;outline-offset:3px;border-radius:6px}
</style>
</head>
<body>
<main>
  <a class="marke" href="/<?= $sprache === 'it' ? '' : $h($sprache) . '/' ?>"><b>VECOM</b>&nbsp;DESIGN</a>
<?php if ($s): ?>
  <h1><?= $h($titel) ?></h1>
  <p class="kern"><?= $h($kern) ?></p>
  <p class="grund"><?= $h(strtr($T['grund'], ['{g}' => (string) $s['geprueft'], '{n}' => (string) $s['n'], '{datum}' => date('d.m.Y', strtotime($s['aktualisiert']))])) ?></p>

  <div class="cta">
    <b style="font:800 21px/1.25 'Archivo',sans-serif"><?= $h($T['cta']) ?></b>
    <p><?= $h($T['ctaText']) ?></p>
    <div class="knoepfe"><a class="haupt" href="<?= $h($cta) ?>"><?= $h($T['knopf']) ?> →</a><a class="neben" href="<?= $h($preisLink) ?>"><?= $h($T['preis']) ?></a></div>
    <p style="margin:12px 0 0"><a href="<?= $h($ohneWeb) ?>" style="color:inherit"><?= $h($T['ohneWeb']) ?></a></p>
  </div>

  <h2><?= $h($T['balken']) ?></h2>
  <ul class="zeilen">
    <?php $zeilen = ['ohne' => (int) ($s['werte']['ohne'] ?? 0)];
          foreach (array_keys(BranchenStatistik::KENNZAHLEN) as $k) { $zeilen[$k] = (int) ($s['werte'][$k] ?? 0); }
          arsort($zeilen);
          foreach ($zeilen as $k => $p): if ($p <= 0) { continue; } ?>
      <li><span class="z"><span><?= $h(BranchenStatistik::LABEL[$k][$sprache]) ?><?php if ($k === 'ohne'): ?> <small style="color:#a39b8d">(<?= $h($T['ohneAnteil']) ?>)</small><?php endif; ?></span><b><?= $p ?> %</b></span>
        <span class="bar" aria-hidden="true"><i style="width:<?= $p ?>%"></i></span></li>
    <?php endforeach; ?>
  </ul>

  <?php /* Für Google, Maps und KI-Suche (29.09.2026): Folgen, typischer Preis, Fragen & Antworten -- aus echten Zahlen */
        $top = array_slice(array_keys(array_filter($zeilen, static fn($p) => $p > 0)), 0, 3);
        $abTyp = 0; try { $abTyp = BranchenStatistik::typischerPreis($s['branche']); } catch (Throwable $e) { $abTyp = 0; }
        $faq = $abTyp > 0 ? BranchenStatistik::faq($s, $sprache, $abTyp) : []; ?>
  <?php if ($top): ?>
    <h2><?= $h($T['bedeutet']) ?></h2>
    <ul style="margin:0;padding-left:20px;color:#d8d0c1;line-height:1.65">
      <?php foreach ($top as $k): ?><li style="margin-bottom:8px"><b style="color:#f7f3ea"><?= $h(BranchenStatistik::LABEL[$k][$sprache]) ?>:</b> <?= $h(BranchenStatistik::FOLGE[$k][$sprache]) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($abTyp > 0): require_once __DIR__ . '/app/src/Baukasten.php'; ?>
    <div class="cta" style="margin-top:26px">
      <b style="font:800 20px/1.25 'Archivo',sans-serif"><?= $h(strtr($T['preisTitel'], ['{mz}' => $mz])) ?></b>
      <p style="font:800 30px/1.2 'Archivo',sans-serif;color:#f7e6ae;margin:8px 0 4px"><?= $h(strtr($T['preisAb'], ['{ab}' => Baukasten::geldText($abTyp, $sprache)])) ?></p>
      <p><?= $h(strtr($T['preisText'], ['{mz}' => $mz])) ?></p>
      <div class="knoepfe"><a class="neben" href="<?= $h($preisLink) ?>"><?= $h($T['preis']) ?> →</a></div>
    </div>
  <?php endif; ?>
  <?php if ($faq): ?>
    <h2><?= $h($T['faq']) ?></h2>
    <?php foreach ($faq as [$fq, $fa]): ?>
      <details style="border-top:1px solid rgba(255,255,255,.1);padding:12px 0"><summary style="cursor:pointer;font-weight:600;font-size:16.5px"><?= $h($fq) ?></summary><p style="margin:8px 0 0;color:#d8d0c1"><?= $h($fa) ?></p></details>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php $gleich = array_values(array_filter($alle, static fn($x) => $x['branche'] === $s['branche'] && $x['slug'] !== $s['slug']));
        $ortAndere = array_values(array_filter($alle, static fn($x) => $x['ort'] === $s['ort'] && $x['slug'] !== $s['slug'])); ?>
  <?php if ($gleich): ?>
    <h2><?= $h($T['weitere']) ?></h2>
    <ul class="links"><?php foreach (array_slice($gleich, 0, 16) as $x): ?><li><a href="<?= $h($adr($x, $sprache)) ?>"><?= $h($x['ort']) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($ortAndere): ?>
    <h2><?= $h(strtr($T['andere'], ['{in}' => BranchenStatistik::in($s['ort'], $sprache)])) ?></h2>
    <ul class="links"><?php foreach (array_slice($ortAndere, 0, 16) as $x): ?><li><a href="<?= $h($adr($x, $sprache)) ?>"><?= $h(Akquise::branchenName($x['branche'], $sprache)) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <p class="methode"><?= $h(strtr($T['methode'], ['{min}' => (string) BranchenStatistik::MIN])) ?> <a href="<?= $h($adr(null, $sprache)) ?>"><?= $h($T['uebersicht']) ?></a></p>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $titel, 'description' => $beschreibung, 'url' => $kanon,
      'inLanguage' => $sprache, 'dateModified' => date('c', strtotime($s['aktualisiert'])), 'publisher' => ['@id' => 'https://vecom-design.it/#studio']],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <script type="application/ld+json"><?= json_encode(array_values(array_filter([
      $faq ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn($q) => ['@type' => 'Question', 'name' => $q[0],
          'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)] : null,
      ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
          ['@type' => 'ListItem', 'position' => 1, 'name' => $T['start'], 'item' => $basis . '/'],
          ['@type' => 'ListItem', 'position' => 2, 'name' => $T['uebersicht'], 'item' => $adr(null, $sprache)],
          ['@type' => 'ListItem', 'position' => 3, 'name' => $titel, 'item' => $kanon]]],
      $abTyp > 0 ? ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $titel, 'serviceType' => 'Web design',
          'provider' => ['@id' => 'https://vecom-design.it/#studio'], 'areaServed' => ['@type' => 'City', 'name' => $s['ort']],
          'offers' => ['@type' => 'Offer', 'priceCurrency' => 'EUR', 'price' => number_format($abTyp / 100, 2, '.', ''), 'url' => $basis . $preisLink]] : null,
  ])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php elseif ($slug !== ''): ?>
  <h1><?= $h($T['nichts']) ?></h1>
  <p><a href="<?= $h($adr(null, $sprache)) ?>"><?= $h($T['uebersicht']) ?></a></p>
<?php else: ?>
  <h1><?= $h($T['ueber']) ?></h1>
  <p class="grund"><?= $h($T['ueberText']) ?></p>
  <?php if (!$alle): ?><p><?= $h($T['leer']) ?></p><?php endif; ?>
  <?php $nachBranche = []; foreach ($alle as $x) { $nachBranche[$x['branche']][] = $x; } ksort($nachBranche);
        foreach ($nachBranche as $bk => $liste): ?>
    <h2><?= $h(Akquise::branchenName($bk, $sprache)) ?></h2>
    <ul class="links"><?php foreach ($liste as $x): ?><li><a href="<?= $h($adr($x, $sprache)) ?>"><?= $h($x['ort']) ?></a></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <div class="cta"><b style="font:800 21px/1.25 'Archivo',sans-serif"><?= $h($T['cta']) ?></b><p><?= $h($T['ctaText']) ?></p>
    <div class="knoepfe"><a class="haupt" href="<?= $h($cta) ?>"><?= $h($T['knopf']) ?> →</a></div></div>
<?php endif; ?>
</main>
<div class="fussbereich"><div class="fb-innen">
  <div class="fb-kopf">
    <a class="fb-marke" href="/<?= $sprache === 'it' ? '' : $h($sprache) . '/' ?>"><b>VECOM</b>&nbsp;DESIGN<span><?= $h(['it' => 'Siti web · Aragona (AG), Sicilia', 'de' => 'Websites · Aragona (AG), Sizilien', 'en' => 'Websites · Aragona (AG), Sicily'][$sprache] ?? 'Aragona (AG)') ?></span></a>
    <?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
  </div>
  <div class="fb-fuss">
    <span>© <?= date('Y') ?> Vecom Design</span>
    <nav class="sprachen" aria-label="<?= $h(['it' => 'Lingua', 'de' => 'Sprache', 'en' => 'Language'][$sprache] ?? 'Lingua') ?>"><?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $w): ?><a href="<?= $h($adr($s, $l)) ?>" hreflang="<?= $l ?>" lang="<?= $l ?>"<?= $l === $sprache ? ' aria-current="true"' : '' ?>><?= $h($w) ?></a><?php endforeach; ?></nav>
  </div>
</div></div>
</body>
</html>
