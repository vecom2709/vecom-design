<?php
/**
 * Der ausführliche Website-Bericht als Baustein (28.09.2026, A1–A10).
 * Eingebunden von analisi.php, website-check.php, bericht.php, analyse.php
 * und kunde.php. Bringt sein eigenes Aussehen mit (alles unter .wb).
 *
 * @var array $wb [
 *   'kc' => Kurz-Check {host,url,punkte,meta,geprueft?}, 'sprache' => it|de|en,
 *   'token' => ?string (Bericht mit fester Adresse: PDF, Teilen),
 *   'firma' => ?array (Name, Branche, Stadt für den Google-Vorschlag),
 *   'bild' => ?string (Adresse des Handyfotos), 'marken' => list (A2),
 *   'vergleich' => ?array (A5), 'preise' => bool (A7, nur im persönlichen Bereich),
 *   'seite' => string (Adresse dieser Seite ohne Rechner-Werte, für das Formular ohne Skript),
 * ]
 */
$wbH = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$wbSp = in_array($wb['sprache'] ?? 'it', ['it', 'de', 'en'], true) ? (string) $wb['sprache'] : 'it';
$W = WebBericht::WORTE[$wbSp];
$wbKc = (array) $wb['kc'];
$wbP = array_values(array_filter((array) ($wbKc['punkte'] ?? []), static fn($p) => isset(Texte::PARTNER_CHECK['punkte'][$p['was'] ?? ''])));
$wbNote = WebBericht::note($wbP);
$wbFarbe = WebBericht::farbe($wbNote);
$wbSek = WebBericht::sekunden($wbKc);
$wbG = WebBericht::google($wbKc, $wbSp, $wb['firma'] ?? null);
$wbDatum = date('d.m.Y', strtotime((string) ($wbKc['geprueft'] ?? 'now')));
$wbBogen = 251.33;
$wbRW = max(0, min(100000, (int) ($_GET['wbw'] ?? 0)));
$wbRM = max(1, min(30, (int) ($_GET['wbm'] ?? 1)));
$wbR = $wbRW > 0 ? WebBericht::rechnen($wbRW, $wbRM) : null;
$wbPlan = !empty($wb['preise']) ? WebBericht::plan($wbP, $wbSp) : null;
$wbSeite = (string) ($wb['seite'] ?? '');
$wbStandFarbe = ['gut' => '#3fb56b', 'hinweis' => '#e0b341', 'schlecht' => '#e5534b'];
?>
<style>
  .wb{--wb-f:#15120e;--wb-f2:#1c1813;--wb-t:#f7f3ea;--wb-d:#b8b0a4;--wb-l:#8b847a;--wb-a:#f1d38b;--wb-li:rgba(255,255,255,.09);--wb-li2:rgba(255,255,255,.16);color:var(--wb-t);font:16px/1.6 'Inter',system-ui,sans-serif}
  .wb *{box-sizing:border-box}
  .wb [hidden]{display:none!important}
  .wb h2{font:700 20px/1.25 'Archivo',sans-serif;margin:0 0 12px;color:var(--wb-t);text-transform:none;letter-spacing:normal}
  .wb .wb-k{background:var(--wb-f);border:1px solid var(--wb-li);border-radius:18px;padding:20px;margin:16px 0 0}
  .wb .wb-leise{color:var(--wb-d);font-size:14.5px;margin:0}
  .wb .wb-kopf{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:center}
  .wb .wb-tacho{position:relative;width:200px;height:118px}
  .wb .wb-tacho svg{display:block}
  .wb .wb-tacho b{position:absolute;left:0;right:0;top:52px;text-align:center;font:800 40px/1 'Archivo',sans-serif}
  .wb .wb-tacho small{position:absolute;left:0;right:0;top:96px;text-align:center;font-size:12px;color:var(--wb-l);letter-spacing:.08em;text-transform:uppercase}
  .wb .wb-stufe{font:700 22px/1.2 'Archivo',sans-serif;margin:0 0 6px}
  .wb .wb-satz{font-size:17px;margin:0 0 6px}
  @media (max-width:560px){.wb .wb-kopf{grid-template-columns:1fr;justify-items:center;text-align:center}}
  /* A4 */
  .wb .wb-lade{position:relative}
  .wb .wb-re{position:absolute;opacity:0;pointer-events:none}
  .wb .wb-bahn{margin:10px 0 0}
  .wb .wb-bahn span{display:flex;justify-content:space-between;font-size:14px;color:var(--wb-d);margin-bottom:4px}
  .wb .wb-bahn i{display:block;height:14px;border-radius:999px;background:var(--wb-f2);overflow:hidden;border:1px solid var(--wb-li)}
  .wb .wb-bahn i::after{content:"";display:block;height:100%;width:100%;border-radius:999px;background:var(--wb-c,#e0b341);transform-origin:left;animation:wbLaden var(--wb-s,2s) linear both}
  .wb .wb-bahn.ziel i::after{--wb-c:#3fb56b}
  @keyframes wbLaden{from{transform:scaleX(0)}to{transform:scaleX(1)}}
  .wb .wb-b2{display:none}
  .wb .wb-re:checked ~ .wb-b1{display:none}.wb .wb-re:checked ~ .wb-b2{display:block}
  .wb .wb-nochmal{display:inline-flex;margin-top:12px;min-height:40px;align-items:center;padding:0 14px;border-radius:10px;border:1px solid var(--wb-li2);color:var(--wb-t);cursor:pointer;font-size:14px}
  @media (prefers-reduced-motion:reduce){.wb .wb-bahn i::after{animation:none}.wb .wb-nochmal{display:none}}
  /* A2 */
  .wb .wb-foto{display:grid;grid-template-columns:minmax(0,300px) 1fr;gap:18px;align-items:start}
  .wb .wb-bild{position:relative;border-radius:18px;overflow:hidden;border:6px solid #0b0a09;box-shadow:0 0 0 1px var(--wb-li2);aspect-ratio:390/844;background:#fff}
  .wb .wb-bild img{display:block;width:100%;height:100%;object-fit:cover;object-position:top}
  .wb .wb-m{position:absolute;border:2px solid #e5534b;border-radius:6px;box-shadow:0 0 0 2px rgba(0,0,0,.35)}
  .wb .wb-m em{position:absolute;left:-12px;top:-12px;width:24px;height:24px;border-radius:50%;background:#e5534b;color:#fff;font:700 13px/24px 'Inter',sans-serif;text-align:center;font-style:normal}
  .wb ol.wb-legende{margin:0;padding-left:22px}.wb ol.wb-legende li{margin:6px 0}
  @media (max-width:640px){.wb .wb-foto{grid-template-columns:1fr}.wb .wb-bild{max-width:280px;margin:0 auto}}
  /* A8 / A10 */
  .wb ul.wb-punkte{list-style:none;margin:0;padding:0;display:grid;gap:10px}
  .wb .wb-p{display:grid;grid-template-columns:44px 1fr;gap:12px;padding:14px;border:1px solid var(--wb-li);border-radius:14px;background:var(--wb-f2)}
  .wb .wb-p .wb-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.04);color:var(--wb-c)}
  .wb .wb-p .wb-sym{width:24px;height:24px}
  .wb .wb-p h3{margin:0;font:700 16px/1.3 'Inter',sans-serif;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .wb .wb-chip{font-size:12px;font-weight:600;padding:2px 9px;border-radius:999px;border:1px solid var(--wb-c);color:var(--wb-c)}
  .wb .wb-p p{margin:4px 0 0;color:var(--wb-d);font-size:15px}
  .wb .wb-p .wb-heisst{color:var(--wb-t)}
  .wb .wb-p details{margin-top:6px}.wb .wb-p summary{cursor:pointer;color:var(--wb-a);font-size:14px;min-height:32px;display:flex;align-items:center}
  /* A3 */
  .wb .wb-zwei{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:640px){.wb .wb-zwei{grid-template-columns:1fr}}
  .wb .wb-etikett{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--wb-l);margin:0 0 6px}
  .wb .wb-serp{background:#fff;color:#202124;border-radius:12px;padding:14px 16px;font-family:arial,sans-serif}
  .wb .wb-serp .u{font-size:12.5px;color:#4d5156;margin:0 0 2px}
  .wb .wb-serp .t{font-size:18px;line-height:1.3;color:#1a0dab;margin:0 0 4px}
  .wb .wb-serp .d{font-size:14px;line-height:1.5;color:#4d5156;margin:0}
  .wb .wb-serp .d.leer{color:#b3261e;font-style:italic}
  .wb .wb-chat{background:#0b141a;border-radius:12px;padding:14px}
  .wb .wb-blase{background:#005c4b;color:#e9edef;border-radius:10px;padding:6px;max-width:280px;margin-left:auto;font-family:system-ui,sans-serif}
  .wb .wb-blase .img{height:110px;border-radius:7px;background:linear-gradient(135deg,#b98a31,#f7e6ae 50%,#c49438);display:grid;place-items:center;color:#16120b;font:800 17px/1.2 'Archivo',sans-serif;text-align:center;padding:10px}
  .wb .wb-blase .kt{background:rgba(0,0,0,.18);border-radius:7px;padding:6px 8px;margin-top:4px;font-size:13px}
  .wb .wb-blase .kt b{display:block;font-size:13.5px;color:#fff}.wb .wb-blase .kt span{color:#aebac1;font-size:12px}
  .wb .wb-blase .link{color:#53bdeb;font-size:13.5px;padding:4px 6px;word-break:break-all}
  /* A5 */
  .wb .wb-vgl{display:grid;gap:6px;margin-top:10px}
  .wb .wb-vgl div{display:grid;grid-template-columns:90px 1fr 38px;gap:10px;align-items:center;font-size:14px;color:var(--wb-d)}
  .wb .wb-vgl i{display:block;height:12px;border-radius:999px;background:rgba(255,255,255,.14)}
  .wb .wb-vgl .sie{color:var(--wb-t);font-weight:700}.wb .wb-vgl .sie i{background:var(--wb-a)}
  .wb .wb-platz{font:800 26px/1.2 'Archivo',sans-serif;margin:0 0 4px}
  /* A6 / A7 */
  .wb form.wb-rech{display:grid;gap:12px}
  .wb form.wb-rech label{font-size:14px;color:var(--wb-d);display:block;margin-bottom:4px}
  .wb form.wb-rech input[type=number]{width:100%;max-width:220px;font-size:18px;padding:12px 14px;border-radius:12px;border:1px solid var(--wb-li2);background:var(--wb-f2);color:var(--wb-t)}
  .wb form.wb-rech input[type=range]{width:100%;max-width:420px;accent-color:#d9b25e}
  .wb .wb-ergebnis{font:800 22px/1.3 'Archivo',sans-serif;color:var(--wb-a);margin:4px 0 0}
  .wb .wb-knopf{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 20px;border-radius:12px;border:0;cursor:pointer;font:700 15.5px/1 'Inter',sans-serif;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;text-decoration:none}
  .wb .wb-knopf.leise{background:transparent;color:var(--wb-t);border:1px solid var(--wb-li2)}
  .wb table.wb-plan{width:100%;border-collapse:collapse;font-size:15px}
  .wb table.wb-plan td{padding:10px 6px;border-top:1px solid var(--wb-li);vertical-align:top}
  .wb table.wb-plan td:last-child{text-align:right;white-space:nowrap;color:var(--wb-a)}
  .wb table.wb-plan td.enth{color:var(--wb-l);font-size:13.5px}
  .wb table.wb-plan tr.summe td{font-weight:700;border-top:2px solid var(--wb-li2)}
  .wb .wb-fuss{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
</style>
<div class="wb" id="wb">
  <section class="wb-k" aria-labelledby="wb-note">
    <div class="wb-kopf">
      <div class="wb-tacho" role="img" aria-label="<?= $wbH($W['note'] . ': ' . $wbNote . ' / 100') ?>">
        <svg viewBox="0 0 200 118" width="200" height="118" aria-hidden="true">
          <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="16" stroke-linecap="round"/>
          <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="<?= $wbH($wbFarbe) ?>" stroke-width="16" stroke-linecap="round"
                stroke-dasharray="<?= number_format($wbBogen * $wbNote / 100, 2, '.', '') ?> <?= $wbBogen ?>"/>
        </svg>
        <b style="color:<?= $wbH($wbFarbe) ?>"><?= $wbNote ?></b><small><?= $wbH($W['note']) ?></small>
      </div>
      <div>
        <p class="wb-stufe" id="wb-note" style="color:<?= $wbH($wbFarbe) ?>"><?= $wbH(WebBericht::stufe($wbNote, $wbSp)) ?></p>
        <p class="wb-satz"><?= $wbH(WebBericht::satz($wbP, $wbSp)) ?></p>
        <p class="wb-leise"><?= $wbH((string) ($wbKc['host'] ?? '')) ?> · <?= $wbH(strtr($W['geprueft'], ['{datum}' => $wbDatum])) ?></p>
      </div>
    </div>
  </section>

  <?php if ($wbSek !== null): $wbDauer = max(0.3, min(20.0, $wbSek)); ?>
  <section class="wb-k wb-lade" aria-labelledby="wb-lade">
    <h2 id="wb-lade"><?= $wbH($W['tempo_t']) ?></h2>
    <input type="checkbox" id="wb-re" class="wb-re" aria-hidden="true" tabindex="-1">
    <?php foreach (['wb-b1', 'wb-b2'] as $wbKopie): ?>
      <div class="<?= $wbKopie ?>">
        <div class="wb-bahn" style="--wb-s:<?= number_format($wbDauer, 1, '.', '') ?>s;--wb-c:<?= $wbSek <= WebBericht::TEMPO_GUT_S ? '#3fb56b' : ($wbSek < 3.5 ? '#e0b341' : '#e5534b') ?>">
          <span><b><?= $wbH($W['ihre']) ?></b><span><?= $wbH(str_replace('.', $wbSp === 'en' ? '.' : ',', (string) $wbSek)) ?> <?= $wbH($W['sek']) ?></span></span><i></i></div>
        <div class="wb-bahn ziel" style="--wb-s:<?= WebBericht::TEMPO_GUT_S ?>s"><span><b><?= $wbH($W['ziel']) ?></b><span><?= $wbSp === 'en' ? '1.5' : '1,5' ?> <?= $wbH($W['sek']) ?></span></span><i></i></div>
      </div>
    <?php endforeach; ?>
    <label for="wb-re" class="wb-nochmal">↻ <?= $wbH($W['nochmal']) ?></label>
  </section>
  <?php endif; ?>

  <?php if (!empty($wb['bild'])): $wbM = (array) ($wb['marken'] ?? []); ?>
  <section class="wb-k" aria-labelledby="wb-foto">
    <h2 id="wb-foto"><?= $wbH($W['foto_t']) ?></h2>
    <div class="wb-foto">
      <div class="wb-bild"><img src="<?= $wbH((string) $wb['bild']) ?>" alt="<?= $wbH($W['foto_t']) ?>" width="390" height="844" loading="lazy">
        <?php foreach ($wbM as $i => $m): ?>
          <span class="wb-m" style="left:<?= round($m['x'] / 3.9, 2) ?>%;top:<?= round($m['y'] / 8.44, 2) ?>%;width:<?= round($m['b'] / 3.9, 2) ?>%;height:<?= round($m['h'] / 8.44, 2) ?>%"><em><?= $i + 1 ?></em></span>
        <?php endforeach; ?>
      </div>
      <div>
        <p class="wb-leise" style="margin-bottom:10px"><?= $wbH($W['foto_d']) ?></p>
        <?php if ($wbM): ?><ol class="wb-legende"><?php foreach ($wbM as $m): ?><li><?= $wbH($W['mark'][$m['art']]) ?></li><?php endforeach; ?></ol><?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="wb-k" aria-labelledby="wb-punkte">
    <h2 id="wb-punkte"><?= $wbH($W['punkte']) ?></h2>
    <ul class="wb-punkte">
      <?php foreach (WebBericht::sortiert($wbP) as $p): $wbC = $wbStandFarbe[$p['stand']] ?? '#e0b341'; ?>
        <li class="wb-p" style="--wb-c:<?= $wbC ?>">
          <span class="wb-ic"><?= WebBericht::symbol($p['was']) ?></span>
          <div>
            <h3><?= $wbH(Texte::h(Texte::PARTNER_CHECK['punkte'][$p['was']]['titel'], $wbSp)) ?> <span class="wb-chip"><?= $wbH($W['stand'][$p['stand']] ?? '') ?></span></h3>
            <p><?= $wbH(WebBericht::punktSatz($p, $wbSp)) ?></p>
            <?php if ($p['stand'] !== 'gut'): ?>
              <p class="wb-heisst"><b><?= $wbH($W['heisst']) ?></b> <?= $wbH(WebBericht::HEISST[$p['was']][$wbSp] ?? '') ?></p>
            <?php endif; ?>
            <details><summary><?= $wbH($W['warum']) ?></summary><p><?= $wbH(WebBericht::WARUM[$p['was']][$wbSp] ?? '') ?></p></details>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php $wbGoogleGut = in_array('google', array_column(array_filter($wbP, static fn($p) => $p['stand'] === 'gut'), 'was'), true);
        if ($wbP && ($wbP[0]['was'] ?? '') !== 'erreichbar' && (!$wbGoogleGut || !$wbG['og'])): ?>
  <section class="wb-k" aria-labelledby="wb-google">
    <?php if (!$wbGoogleGut): ?>
    <h2 id="wb-google"><?= $wbH($W['google_t']) ?></h2>
    <div class="wb-zwei">
      <div><p class="wb-etikett"><?= $wbH($W['heute']) ?></p>
        <div class="wb-serp"><p class="u"><?= $wbH($wbG['jetzt']['url']) ?></p><p class="t"><?= $wbH($wbG['jetzt']['titel']) ?></p>
          <?php if ($wbG['jetzt']['beschreibung'] !== ''): ?><p class="d"><?= $wbH($wbG['jetzt']['beschreibung']) ?></p><?php else: ?><p class="d leer"><?= $wbH($W['ohne_b']) ?></p><?php endif; ?></div></div>
      <div><p class="wb-etikett"><?= $wbH($W['vorschlag']) ?> · <?= $wbH($W['beispiel']) ?></p>
        <div class="wb-serp"><p class="u"><?= $wbH($wbG['vorschlag']['url']) ?></p><p class="t"><?= $wbH($wbG['vorschlag']['titel']) ?></p><p class="d"><?= $wbH($wbG['vorschlag']['beschreibung']) ?></p></div></div>
    </div>
    <?php endif; ?>
    <?php if (!$wbG['og']): ?>
    <h2 style="margin-top:<?= $wbGoogleGut ? '0' : '22px' ?>"<?= $wbGoogleGut ? ' id="wb-google"' : '' ?>><?= $wbH($W['wa_t']) ?></h2>
    <div class="wb-zwei">
      <div><p class="wb-etikett"><?= $wbH($W['heute']) ?> · <?= $wbH($wbG['og'] ? $W['wa_mit'] : $W['wa_ohne']) ?></p>
        <div class="wb-chat"><div class="wb-blase">
          <?php if ($wbG['og']): ?><div class="img"><?= $wbH($wbG['name']) ?></div><div class="kt"><b><?= $wbH($wbG['jetzt']['titel']) ?></b><span><?= $wbH((string) ($wbKc['host'] ?? '')) ?></span></div><?php endif; ?>
          <div class="link">https://<?= $wbH((string) ($wbKc['host'] ?? '')) ?></div></div></div></div>
      <div><p class="wb-etikett"><?= $wbH($W['vorschlag']) ?> · <?= $wbH($W['wa_mit']) ?></p>
        <div class="wb-chat"><div class="wb-blase"><div class="img"><?= $wbH($wbG['name']) ?></div>
          <div class="kt"><b><?= $wbH($wbG['vorschlag']['titel']) ?></b><span><?= $wbH(mb_substr($wbG['vorschlag']['beschreibung'], 0, 80)) ?>…</span></div>
          <div class="link">https://<?= $wbH((string) ($wbKc['host'] ?? '')) ?></div></div></div></div>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($wb['vergleich'])): $wbV = $wb['vergleich']; $wbX = 0; ?>
  <section class="wb-k" aria-labelledby="wb-vgl">
    <h2 id="wb-vgl"><?= $wbH($W['vgl_t']) ?></h2>
    <p class="wb-platz"><?= $wbH(strtr($W['vgl_platz'], ['{platz}' => (string) $wbV['platz'], '{von}' => (string) $wbV['von']])) ?></p>
    <p class="wb-leise"><?= $wbH(strtr($W['vgl_d'], ['{branche}' => (string) $wbV['branche'], '{ort}' => (string) $wbV['ort']])) ?></p>
    <div class="wb-vgl">
      <?php foreach ($wbV['liste'] as $z): $wbName = $z['selbst'] ? $W['vgl_sie'] : strtr($W['vgl_betrieb'], ['{x}' => chr(65 + ($wbX++ % 26))]); ?>
        <div class="<?= $z['selbst'] ? 'sie' : '' ?>"><span><?= $wbH($wbName) ?></span><i style="width:<?= max(4, (int) $z['wert']) ?>%"></i><span><?= (int) $z['wert'] ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="wb-k" id="wb-rechner" aria-labelledby="wb-rech">
    <h2 id="wb-rech"><?= $wbH($W['rech_t']) ?></h2>
    <form class="wb-rech" method="get" action="<?= $wbH($wbSeite) ?>#wb-rechner" data-sprache="<?= $wbH($wbSp) ?>"
          data-text="<?= $wbH($W['rech_ergebnis']) ?>" data-bezahlt="<?= $wbH($W['rech_bezahlt']) ?>" data-preis="<?= $wbPlan ? (int) round(($wbPlan['von'] + $wbPlan['bis']) / 2) : 0 ?>">
      <?php $wbQuery = []; parse_str((string) parse_url($wbSeite, PHP_URL_QUERY), $wbQuery); foreach ($wbQuery as $k => $v): if (is_string($v) && !in_array($k, ['wbw', 'wbm'], true)): ?>
        <input type="hidden" name="<?= $wbH($k) ?>" value="<?= $wbH($v) ?>">
      <?php endif; endforeach; ?>
      <div><label for="wb-w"><?= $wbH($W['rech_wert']) ?></label><input id="wb-w" type="number" name="wbw" min="0" max="100000" step="10" inputmode="numeric" value="<?= $wbRW > 0 ? $wbRW : '' ?>" placeholder="<?= $wbSp === 'en' ? '150' : '150' ?>"></div>
      <div><label for="wb-m"><?= $wbH($W['rech_mehr']) ?>: <b data-wb-m><?= $wbRM ?></b></label><input id="wb-m" type="range" name="wbm" min="1" max="10" value="<?= $wbRM ?>"></div>
      <p class="wb-ergebnis" data-wb-ergebnis aria-live="polite"><?= $wbR ? $wbH(strtr($W['rech_ergebnis'], ['{n}' => (string) $wbR['jahr'], '{summe}' => WebBericht::euro($wbR['summe'] * 100, $wbSp)])) : '' ?></p>
      <?php if ($wbPlan && $wbR && $wbRW > 0): ?><p class="wb-leise" data-wb-bezahlt><?= $wbH(strtr($W['rech_bezahlt'], ['{n}' => (string) max(1, (int) ceil((($wbPlan['von'] + $wbPlan['bis']) / 2) / 100 / $wbRW))])) ?></p><?php else: ?><p class="wb-leise" data-wb-bezahlt></p><?php endif; ?>
      <p class="wb-leise"><?= $wbH($W['rech_hinweis']) ?></p>
      <p style="margin:0"><button class="wb-knopf leise" type="submit" data-wb-senden><?= $wbH($W['rech_knopf']) ?></button></p>
    </form>
  </section>

  <?php if ($wbPlan): ?>
  <section class="wb-k" aria-labelledby="wb-plan">
    <h2 id="wb-plan"><?= $wbH($W['plan_t']) ?></h2>
    <p class="wb-leise" style="margin-bottom:10px"><?= $wbH($W['plan_d']) ?></p>
    <table class="wb-plan"><tbody>
      <?php foreach ($wbPlan['zeilen'] as $z): ?>
        <tr><td><b><?= $wbH($z['titel']) ?></b><br><span class="wb-leise"><?= $wbH($z['loesung']) ?></span></td><td<?= str_contains($z['preis'], '€') ? '' : ' class="enth"' ?>><?= $wbH($z['preis']) ?></td></tr>
      <?php endforeach; ?>
      <tr class="summe"><td><?= $wbH($W['plan_gesamt']) ?></td><td><?= $wbH($wbPlan['gesamt']) ?></td></tr>
    </tbody></table>
    <?php if (!empty($wb['preisLink'])): ?><p style="margin:14px 0 0"><a class="wb-knopf" href="<?= $wbH((string) $wb['preisLink']) ?>"><?= $wbH($W['plan_knopf']) ?></a></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($wb['token'])): $wbUrl = WebBericht::adresse((string) $wb['token'], $wbSp); ?>
  <div class="wb-fuss">
    <a class="wb-knopf" href="/bericht.php?b=<?= $wbH((string) $wb['token']) ?>&amp;lang=<?= $wbH($wbSp) ?>&amp;pdf=1"><?= $wbH($W['pdf']) ?></a>
    <a class="wb-knopf leise" href="https://wa.me/?text=<?= $wbH(rawurlencode($wbUrl)) ?>" target="_blank" rel="noopener"><?= $wbH($W['teilen']) ?> · WhatsApp</a>
    <a class="wb-knopf leise" href="mailto:?subject=<?= $wbH(rawurlencode($W['note'] . ' — ' . (string) ($wbKc['host'] ?? ''))) ?>&amp;body=<?= $wbH(rawurlencode($wbUrl)) ?>"><?= $wbH($W['teilen']) ?> · E-Mail</a>
  </div>
  <?php endif; ?>
</div>
<script src="/assets/js/web-bericht.js" defer></script>
