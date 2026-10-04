<?php
/* ==========================================================================
   Werbe-Paket auf der Partnerseite (26.09.2026). Eingebunden aus partner.php,
   dort sind $p, $link, $sprache, $T, $h, $selbst, $meldung gesetzt.

   Aufbau: ein Reiter je Kanal (ohne Skript stehen alle Tafeln untereinander),
   darin fertige Texte mit Kopieren und Teilen, danach Werbemittel, Signatur
   und Website-Knopf, dann „Was wirkt“. Jeder Text trägt den Link seines
   Kanals -- der Partner setzt nie selbst etwas ein.
   ========================================================================== */
$vorlagen = PartnerWerbung::vorlagen($p, $sprache);
$KN = static fn(string $k): string => PartnerWerbung::name($k, $sprache);
$tipp = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBUNG['tipps'][$k] ?? [], $sprache);
$aw = PartnerWerbung::auswertung((int) $p['id']);
$sig = PartnerWerbung::signatur($p, $sprache);
$knopf = PartnerWerbung::websiteKnopf($p, $sprache);
?>
<style>
  .reiter{display:flex;gap:6px;overflow-x:auto;padding:2px 0 8px;margin:4px 0 6px;scrollbar-width:thin}
  .reiter button{flex:0 0 auto;min-height:38px;padding:7px 14px;border-radius:999px;border:1px solid var(--linie2);background:transparent;
                 color:var(--dim);font:inherit;font-size:14px;cursor:pointer}
  .reiter button[aria-selected=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.09)}
  .reiter button:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .tafel{display:grid;gap:12px;margin-top:4px}
  .tafel > h3{font-size:15px;margin:10px 0 0}
  .vorlage{border:1px solid var(--linie);border-radius:12px;padding:12px}
  .vorlage h4{font-size:14px;margin:0 0 8px;color:var(--text)}
  .vorlage textarea{width:100%;box-sizing:border-box;font-size:14px;line-height:1.55;padding:10px 12px;resize:vertical;min-height:90px}
  .vorlage .betreff{font-size:13px;color:var(--dim);margin:0 0 6px}
  .vorlage .betreff b{color:var(--text);font-weight:600}
  .vorlage .knoepfe{margin-top:8px}
  .vorlage .knopf{min-height:40px}
  .kanallink{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--leise);min-width:0;margin-top:2px}
  .kanallink code{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--dim)}
  .kanallink .knopf{min-height:32px;padding:5px 11px;font-size:12.5px}
  .sig-vorschau{background:#fff;border-radius:10px;padding:14px 16px;margin:8px 0;overflow-x:auto}
  .pt .sig-vorschau td{border:0}
  .web-vorschau{padding:12px 0}
  .aw{font-size:13.5px}
  .aw td:first-child{font-weight:600;word-break:break-word}
  .pt .aw th.r,.pt .aw td.r{white-space:normal;padding-left:4px;padding-right:4px}
  .aw td small{display:block;color:var(--cyan);font-size:12px}
  .aw .bester{border:1px solid rgba(241,211,139,.45);border-radius:10px;padding:9px 12px;margin:10px 0 0;font-size:14px}
  .profil-kopf{display:flex;gap:14px;align-items:center;margin:4px 0 8px}
  .profil-kopf img,.profil-kopf .leer{width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid var(--linie2);flex:0 0 72px}
  .profil-kopf .leer{display:grid;place-items:center;color:var(--leise);font-size:26px}
  .pt input[type=file]{font-size:14px;color:var(--dim)}
  .md-h{font-size:15px;margin:14px 0 6px}
  .md-l{font-size:12.5px;color:var(--leise);margin:10px 0 4px;text-transform:uppercase;letter-spacing:.05em}
  .chips{display:flex;gap:6px;flex-wrap:wrap}
  .chips button{min-height:36px;padding:6px 12px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13.5px;cursor:pointer}
  .chips button[aria-pressed=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.09)}
  .chips button:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .md-buehne{margin:14px 0 4px;display:flex;justify-content:center;background:rgba(255,255,255,.02);border:1px solid var(--linie);border-radius:12px;padding:12px}
  .md-buehne canvas{max-width:100%;max-height:440px;width:auto;height:auto;border-radius:6px;box-shadow:0 8px 30px rgba(0,0,0,.4)}
  .md-video{display:block;max-width:100%;max-height:440px;margin:10px auto;border-radius:10px;background:#000}
  .druckliste{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:8px}
  .druckliste .knopf{justify-content:flex-start;text-align:left}
  .vk-raster{list-style:none;margin:8px 0 6px;padding:0;display:grid;gap:14px}
  .vk-karte{display:grid;gap:8px;border:1px solid var(--linie);border-radius:14px;padding:10px}
  .vk-karte img{width:100%;height:auto;border-radius:8px;display:block;background:#0d0b08}
  .vk-karte b{font-size:14.5px;color:var(--text);font-weight:600}
  .vk-knoepfe{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
  .vk-knoepfe .knopf{min-height:42px;justify-content:center;font-size:13.5px;padding:6px 10px}
  .fl-chips{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 4px}
  .fl-chips button{min-height:38px;padding:6px 12px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13.5px;cursor:pointer}
  .fl-chips button span{color:var(--leise);font-size:12px;margin-left:2px}
  .fl-chips button[aria-pressed=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.09)}
  .fl-chips button:focus-visible,.fl-karte .knopf:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .fl-gruppe[hidden]{display:none}
  .fl-sp{display:flex;gap:4px;align-items:center;flex-wrap:wrap}
  .fl-sp button{min-height:32px;min-width:38px;padding:3px 8px;border-radius:8px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
  .fl-sp button[aria-pressed=true]{border-color:rgba(241,211,139,.7);color:var(--text);background:rgba(241,211,139,.12)}
  .fl-sp button:focus-visible{outline:2px solid var(--cyan);outline-offset:2px}
  .fl-neu{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#f1d38b;font-weight:600}
  .fl-raster{list-style:none;margin:0 0 6px;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .fl-karte{display:flex;flex-direction:column;gap:7px;border:1px solid var(--linie);border-radius:12px;padding:8px;min-width:0}
  .fl-karte img{width:100%;height:auto;aspect-ratio:5/8;object-fit:contain;border-radius:7px;background:#0d0b08;display:block}
  .fl-karte b{font-size:14px;line-height:1.3;color:var(--text);font-weight:600}
  .fl-knoepfe{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:auto}
  .fl-knoepfe .knopf{min-height:40px;padding:6px 8px;font-size:13.5px;justify-content:center}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>

<div class="block pt" id="werbung" data-reiter="werben">
  <h2><?= $h($T('pk_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($T('pk_text')) ?></p>

  <div class="reiter" role="tablist" aria-label="<?= $h($T('pk_titel')) ?>" hidden>
    <?php foreach (PartnerWerbung::KANAELE as $i => $k): ?>
      <button type="button" role="tab" id="r_<?= $h($k) ?>" aria-controls="t_<?= $h($k) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><?= $h($KN($k)) ?></button>
    <?php endforeach; ?>
  </div>

  <?php foreach (PartnerWerbung::KANAELE as $k): $kl = PartnerWerbung::link($p, $k); ?>
  <section class="tafel" id="t_<?= $h($k) ?>" role="tabpanel" aria-labelledby="r_<?= $h($k) ?>">
    <h3 class="nur-ohne-js"><?= $h($KN($k)) ?></h3>
    <?php if ($tipp($k) !== ''): ?><p class="klein" style="margin:0"><?= $h($tipp($k)) ?></p><?php endif; ?>
    <div class="kanallink"><span><?= $h(strtr($T('pk_link'), ['{kanal}' => $KN($k)])) ?>:</span><code><?= $h($kl) ?></code>
      <button class="knopf" type="button" data-kopie-text="<?= $h($kl) ?>"><?= $h($T('kopieren')) ?></button></div>
    <?php foreach ($vorlagen[$k] ?? [] as $v): ?>
      <article class="vorlage">
        <h4><?= $h($v['titel']) ?></h4>
        <?php if ($v['betreff'] !== ''): ?><p class="betreff"><?= $h($T('pk_betreff')) ?>: <b><?= $h($v['betreff']) ?></b></p><?php endif; ?>
        <label class="sr" for="v_<?= $h($v['id']) ?>"><?= $h($v['titel']) ?></label>
        <textarea id="v_<?= $h($v['id']) ?>" readonly rows="6" data-wachsen><?= $h($v['text']) ?></textarea>
        <div class="knoepfe">
          <button class="knopf" type="button" data-kopie="v_<?= $h($v['id']) ?>"><?= $h($T('kopieren')) ?></button>
          <?php if ($v['teilen'] !== null): ?>
            <a class="knopf haupt" href="<?= $h($v['teilen']) ?>" <?= str_starts_with($v['teilen'], 'http') ? 'target="_blank" rel="noopener"' : '' ?>><?= $h($T($k === 'email' ? 'pk_senden' : 'pk_teilen')) ?></a>
          <?php else: ?>
            <button class="knopf haupt" type="button" data-teilen="v_<?= $h($v['id']) ?>" data-url="<?= $h($kl) ?>" hidden><?= $h($T('pk_teilen')) ?></button>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

  <details style="margin-top:18px">
    <summary style="cursor:pointer;color:var(--cyan);font-size:14.5px"><?= $h($T('pk_werkzeuge')) ?></summary>
    <h3 style="font-size:15px;margin:14px 0 4px"><?= $h($T('sig_titel')) ?></h3>
    <p class="klein" style="margin:0"><?= $h($T('sig_text')) ?></p>
    <div class="sig-vorschau" id="sig_vorschau"><?= $sig ?></div>
    <textarea id="sig_html" hidden><?= $h($sig) ?></textarea>
    <button class="knopf" type="button" id="sig_kopieren"><?= $h($T('sig_kopieren')) ?></button>

    <h3 style="font-size:15px;margin:20px 0 4px"><?= $h($T('web_titel')) ?></h3>
    <p class="klein" style="margin:0"><?= $h($T('web_text')) ?></p>
    <div class="web-vorschau"><?= $knopf ?></div>
    <div class="vorlage" style="padding:10px">
      <label class="sr" for="web_code"><?= $h($T('web_titel')) ?></label>
      <textarea id="web_code" readonly rows="4" style="font-family:ui-monospace,monospace;font-size:12.5px"><?= $h($knopf) ?></textarea>
      <div class="knoepfe"><button class="knopf" type="button" data-kopie="web_code"><?= $h($T('code_kopieren')) ?></button></div>
    </div>

    <?php /* Check-Knopf (28.09.2026, W2): die kostenlose Analyse auf der Website des Partners. */
          $akW = ['it' => ['Analisi gratuita sul suo sito', 'Inserisca questo pulsante o riquadro sul suo sito (associazione, studio, negozio). Chi lo usa vede subito l’analisi del proprio sito — ed è registrato a suo nome.', 'Pulsante', 'Riquadro'],
                  'de' => ['Kostenlose Analyse auf Ihrer Website', 'Bauen Sie diesen Knopf oder Kasten auf Ihrer Website ein (Verein, Büro, Geschäft). Wer ihn nutzt, sieht sofort die Analyse seiner eigenen Website — und wird Ihnen zugeordnet.', 'Knopf', 'Kasten'],
                  'en' => ['Free analysis on your website', 'Put this button or box on your website (club, office, shop). Anyone using it sees the analysis of their own website right away — and is credited to you.', 'Button', 'Box']][$sprache] ?? null;
          if ($akW): foreach (['knopf' => $akW[2], 'kasten' => $akW[3]] as $akArt => $akName): $akCode = PartnerWerbung::analyseKnopf($p, $sprache, $akArt); ?>
      <?php if ($akArt === 'knopf'): ?><h3 style="font-size:15px;margin:20px 0 4px"><?= $h($akW[0]) ?></h3><p class="klein" style="margin:0"><?= $h($akW[1]) ?></p><?php endif; ?>
      <p class="klein" style="margin:12px 0 0;font-weight:600"><?= $h($akName) ?></p>
      <div class="web-vorschau"><?= $akCode ?></div>
      <div class="vorlage" style="padding:10px">
        <label class="sr" for="ak_<?= $akArt ?>"><?= $h($akW[0] . ' · ' . $akName) ?></label>
        <textarea id="ak_<?= $akArt ?>" readonly rows="4" style="font-family:ui-monospace,monospace;font-size:12.5px"><?= $h($akCode) ?></textarea>
        <div class="knoepfe"><button class="knopf" type="button" data-kopie="ak_<?= $akArt ?>"><?= $h($T('code_kopieren')) ?></button></div>
      </div>
    <?php endforeach; endif; ?>
  </details>

  <h2 style="margin-top:22px"><?= $h($T('aw_titel')) ?></h2>
  <?php if (!$aw['zeilen']): ?>
    <p class="klein" style="margin-top:0"><?= $h($T('aw_leer')) ?></p>
  <?php else: ?>
    <table class="aw"><thead><tr><th><?= $h($T('aw_kanal')) ?></th><th class="r"><?= $h($T('aw_klicks')) ?></th>
      <th class="r"><?= $h($T('aw_kunden')) ?></th><th class="r"><?= $h($T('aw_verkaeufe')) ?></th></tr></thead><tbody>
      <?php foreach ($aw['zeilen'] as $z): ?>
        <tr><td><?= $h($KN($z['kanal'])) ?></td><td class="r"><?= (int) $z['klicks'] ?></td><td class="r"><?= (int) $z['kunden'] ?></td>
            <td class="r"><?= (int) $z['verkaeufe'] ?><?= $z['provision'] > 0 ? '<small>' . $h(Fmt::geld($z['provision'])) . '</small>' : '' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php if ($aw['bester'] !== null): ?><p class="bester"><?= $h(strtr($T('aw_bester'), ['{kanal}' => $KN($aw['bester'])])) ?></p><?php endif; ?>
  <?php endif; ?>
</div>

<?php
  $MT = static fn(string $k): string => strtr(Texte::h(Texte::PARTNER_MEDIEN[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
  /* Erfolge als Kacheln (27.09.2026): fertige Seiten, deren Kunde das Zeigen
     erlaubt hat, und veröffentlichte Kundenstimmen mit Erlaubnis. Nichts
     anderes -- eine Kachel ist öffentlich. */
  $MKw = static fn(string $k): string => Texte::h(Texte::PARTNER_MARKETING[$k] ?? [], $sprache);
  $kacheln = [];
  foreach (PartnerErfolg::liste((int) $p['id']) as $kE) {
      if (!$kE['zeigen']) { continue; }
      $kacheln[] = ['art' => 'erfolg', 'name' => (string) $kE['firma'], 'titel' => strtr($MKw('kc_online'), ['{firma}' => (string) $kE['firma']]),
                    'unter' => $MKw('kc_neu'), 'host' => (string) (parse_url((string) $kE['url'], PHP_URL_HOST) ?: '')];
  }
  foreach (PartnerSeite::stimmen($p, $sprache, 3) as $kS) {
      $kacheln[] = ['art' => 'stimme', 'name' => (string) $kS['name'], 'text' => (string) $kS['text'],
                    'wer' => trim($kS['name'] . ($kS['firma'] !== '' ? ', ' . $kS['firma'] : '') . ($kS['ort'] !== '' ? ' · ' . $kS['ort'] : '')),
                    'sterne' => $kS['sterne'], 'marke' => $MKw('kc_stimme')];
  }
  $medienDaten = [
      'code' => (string) $p['code'], 'kurz' => preg_replace('~^https?://~', '', Partner::link($p)),
      'links' => ['bild' => PartnerWerbung::link($p, 'bild'), 'video' => PartnerWerbung::link($p, 'video'), 'karte' => PartnerWerbung::link($p, 'karte'), 'kachel' => PartnerWerbung::link($p, 'kachel')],
      'kacheln' => $kacheln,
      'foto' => PartnerWerbung::fotoAdresse($p),
      'motive' => array_map(static fn(array $m): array => ['titel' => Texte::h($m['titel'], $sprache), 'unter' => Texte::h($m['unter'], $sprache)], Texte::PARTNER_MEDIEN['motive']),
      'punkte' => array_map(static fn(array $t): string => Texte::h($t, $sprache), Texte::PARTNER_MEDIEN['punkte']),
      'empf' => $MT('empf'), 'scan' => $MT('scan'), 'hook' => $MT('hook'), 'bio' => $MT('bio'), 'werbung' => $MT('werbung'),
      't' => ['video_laeuft' => $T('video_laeuft'), 'video_fertig' => $T('video_fertig'), 'video_nein' => $T('video_nein'), 'video_webm' => $T('video_webm')],
  ];
?>
<?php /* Fertige Beiträge aus dem Marketing-Studio (Marketing-Studio 8, 01.10.2026) — mit dem Link des Partners. */
  require_once __DIR__ . '/../src/MkPartnerBeitraege.php';
  $pbListe = (array) (static function () use ($p, $sprache): array { try { return MkPartnerBeitraege::fuerPartner($p, $sprache); } catch (Throwable $e) { return []; } })();
  if ($pbListe): ?>
<div class="block pt" id="beitraege" data-reiter="werben">
  <h2><?= $h(MkPartnerBeitraege::t('titel', $sprache)) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h(MkPartnerBeitraege::t('text', $sprache)) ?></p>
  <div class="tafel">
    <?php /* Zwei Beiträge stehen offen, der Rest klappt (03.10.2026, „alle langen Listen einklappbar“) */
          $pbNr = 0; foreach ($pbListe as $pb): $pbNr++; if ($pbNr === 3): ?>
  </div>
  <details class="weitere"><summary><?= $h(strtr($T('kl_weitere'), ['{n}' => (string) (count($pbListe) - 2)])) ?></summary>
  <div class="tafel">
    <?php endif; ?>
      <article class="vorlage">
        <h4><?= $pb['land'] === 'DE' ? '🇩🇪' : '🇮🇹' ?> <?= $h($pb['titel']) ?></h4>
        <?php if ($pb['bild']): ?><img src="<?= $h($pb['bild']) ?>" alt="<?= $h($pb['titel']) ?>" loading="lazy" style="display:block;width:100%;max-width:420px;border-radius:10px;margin:0 0 8px"><?php endif; ?>
        <label class="sr" for="pb_<?= (int) $pb['id'] ?>"><?= $h($pb['titel']) ?></label>
        <textarea id="pb_<?= (int) $pb['id'] ?>" readonly rows="6" data-wachsen><?= $h($pb['text']) ?></textarea>
        <div class="knoepfe">
          <button class="knopf" type="button" data-kopie="pb_<?= (int) $pb['id'] ?>"><?= $h($T('kopieren')) ?></button>
          <?php if ($pb['bild']): ?><a class="knopf" href="<?= $h($pb['bild']) ?>" download="vecom-<?= (int) $pb['id'] ?>.jpg"><?= $h(MkPartnerBeitraege::t('bild', $sprache)) ?></a><?php endif; ?>
          <a class="knopf haupt" href="<?= $h($pb['whatsapp']) ?>" target="_blank" rel="noopener"><?= $h(MkPartnerBeitraege::t('whatsapp', $sprache)) ?></a>
          <a class="knopf" href="<?= $h($pb['facebook']) ?>" target="_blank" rel="noopener"><?= $h(MkPartnerBeitraege::t('facebook', $sprache)) ?></a>
          <button class="knopf" type="button" data-handy="pb_<?= (int) $pb['id'] ?>" data-handy-bild="<?= $h((string) ($pb['bild'] ?? '')) ?>" data-handy-wer="<?= $h((string) $p['name']) ?>"
                  data-handy-titel="<?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_titel'], $sprache)) ?>" data-handy-zu="<?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_schliessen'], $sprache)) ?>"><?= $h(Texte::h(Texte::PARTNER_MARKETING['hv_knopf'], $sprache)) ?></button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($pbNr > 2): ?></details><?php endif; ?>
</div>
<?php endif; ?>
<?php /* 3D-Galerie (Marketing-Studio 11, 01.10.2026, Uwe: Ja zu P1–P3): fotoreal von Vecoms PC — Text, Link und QR legt partner-3d.js im Browser drauf. */
  require_once __DIR__ . '/../src/MkMedium.php';
  $g3Liste = MkMedium::galerieFuerPartner($p);
  $g3Bestellt = MkMedium::bestellungenVon($p);
  $g3Motive = [];
  foreach (MkMedium::STUDIO_MOTIV as $g3St => $g3Mo) {
      $g3M = Texte::PARTNER_MEDIEN['motive'][$g3Mo] ?? Texte::PARTNER_MEDIEN['motive']['allgemein'];
      $g3Motive[$g3St] = ['titel' => Texte::h($g3M['titel'], $sprache), 'unter' => Texte::h($g3M['unter'], $sprache)];
  }
  $g3Daten = ['items' => $g3Liste, 'motive' => $g3Motive, 'allgemein' => ['titel' => Texte::h(Texte::PARTNER_MEDIEN['motive']['allgemein']['titel'], $sprache), 'unter' => Texte::h(Texte::PARTNER_MEDIEN['motive']['allgemein']['unter'], $sprache)],
              'code' => (string) $p['code'], 'kurz' => preg_replace('~^https?://~', '', Partner::link($p)), 'link' => PartnerWerbung::link($p, 'video3d'), 'linkBild' => PartnerWerbung::link($p, 'bild3d'),
              'empf' => strtr(Texte::h(Texte::PARTNER_MEDIEN['empf'], $sprache), ['{name}' => (string) $p['name']]), 'scan' => Texte::h(Texte::PARTNER_MEDIEN['scan'], $sprache),
              't' => ['v_laeuft' => MkMedium::gt('v_laeuft', $sprache), 'v_fertig' => MkMedium::gt('v_fertig', $sprache), 'v_nein' => MkMedium::gt('v_nein', $sprache)]]; ?>
<div class="block pt" id="galerie3d" data-reiter="werben">
  <h2><?= $h(MkMedium::gt('titel', $sprache)) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h(MkMedium::gt('text', $sprache)) ?></p>
  <?php if (!empty($g3Meldung)): ?><div class="hinweis <?= in_array($g3Meldung, ['b_ok', 'b_ok_pruefen'], true) ? 'gut' : 'schlecht' ?>" role="status"><?= $h(MkMedium::gt($g3Meldung, $sprache)) ?></div><?php endif; ?>
  <script type="application/json" id="g3_daten"><?= json_encode($g3Daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php if ($g3Liste): ?>
    <div class="g3-raster" role="group" aria-label="<?= $h(MkMedium::gt('titel', $sprache)) ?>">
      <?php foreach ($g3Liste as $i => $g3): ?>
        <button type="button" class="g3-stueck" data-g3="<?= $i ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
          <?php if ($g3['art'] === 'video'): ?><video src="<?= $h($g3['url']) ?>#t=1" muted playsinline preload="metadata"></video><span class="g3-marke"><?= $h(MkMedium::gt('video', $sprache)) ?></span>
          <?php else: ?><img src="<?= $h($g3['url']) ?>" alt="<?= $h((string) (MkMedium::GALERIE_SZENEN[$g3['studio']][$sprache] ?? '3D')) ?>" loading="lazy"><?php endif; ?>
          <?php if ($g3['eigen']): ?><span class="g3-marke g3-eigen"><?= $h(MkMedium::gt('eigen', $sprache)) ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
    <p class="md-l"><?= $h(MkMedium::gt('format', $sprache)) ?></p>
    <div class="chips">
      <?php foreach (['quadrat', 'hoch', 'story'] as $i => $fk): ?>
        <button type="button" data-g3format="<?= $fk ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= $h(MkMedium::gt($fk, $sprache)) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="md-buehne"><canvas id="g3_vorschau" width="1080" height="1350" role="img" aria-label="<?= $h(MkMedium::gt('titel', $sprache)) ?>"></canvas></div>
    <div class="knoepfe">
      <button class="knopf haupt" type="button" id="g3_laden"><?= $h(MkMedium::gt('laden', $sprache)) ?></button>
      <button class="knopf" type="button" id="g3_teilen" hidden><?= $h(MkMedium::gt('teilen', $sprache)) ?></button>
      <button class="knopf haupt" type="button" id="g3_video" hidden><?= $h(MkMedium::gt('v_machen', $sprache)) ?></button>
    </div>
    <p class="klein" id="g3_stand" role="status" aria-live="polite"></p>
    <video id="g3_ergebnis" class="md-video" controls playsinline muted hidden></video>
    <div class="knoepfe">
      <button class="knopf" type="button" id="g3_vladen" hidden><?= $h(MkMedium::gt('v_laden', $sprache)) ?></button>
      <button class="knopf" type="button" id="g3_vteilen" hidden><?= $h(MkMedium::gt('teilen', $sprache)) ?></button>
    </div>
  <?php else: ?>
    <p class="klein"><?= $h(MkMedium::gt('leer', $sprache)) ?></p>
  <?php endif; ?>

  <?php /* Der Wunsch an Vecom ist der seltenere Weg — zugeklappt, damit die fertigen Motive oben stehen (03.10.2026) */ ?>
  <details class="klapp" data-klapp="g3_wunsch" style="margin-top:14px"<?= $g3Liste ? '' : ' open' ?>>
  <summary><h3 class="md-h"><?= $h(MkMedium::gt('b_titel', $sprache)) ?></h3></summary>
  <p class="klein" style="margin-top:0"><?= $h(MkMedium::gt('b_text', $sprache)) ?></p>
  <form method="post" action="<?= $h($selbst()) ?>#galerie3d" class="g3-bestellen" id="g3_form">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="g3_bestellen">
    <label><span class="g3-l"><?= $h(MkMedium::gt('b_szene', $sprache)) ?></span>
      <select name="studio" id="g3_studio"><?php foreach (MkMedium::GALERIE_SZENEN as $g3k => $g3n): ?><option value="<?= $h($g3k) ?>"><?= $h($g3n[$sprache] ?? $g3n['it']) ?></option><?php endforeach; ?>
        <option value="eigen"><?= $h(MkMedium::gt('w_eigen', $sprache)) ?></option></select></label>
    <label><span class="g3-l"><?= $h(MkMedium::gt('b_art', $sprache)) ?></span>
      <select name="art"><option value="bild"><?= $h(MkMedium::gt('b_bild', $sprache)) ?> 4:5</option><option value="video"><?= $h(MkMedium::gt('video', $sprache)) ?> 9:16</option></select></label>
    <label class="g3-breit" id="g3_wunsch_feld"><span class="g3-l"><?= $h(MkMedium::gt('w_text', $sprache)) ?></span>
      <textarea name="wunsch" rows="3" maxlength="600"></textarea>
      <small class="klein"><?= $h(MkMedium::gt('w_hinweis', $sprache)) ?></small></label>
    <div class="g3-fein" id="g3_fein">
      <?php foreach (['blick' => [MkMedium::WUNSCH_BLICK, 'bl_'], 'naehe' => [MkMedium::WUNSCH_NAEHE, 'na_'], 'stimmung' => [MkMedium::WUNSCH_STIMMUNG, 'st_']] as $g3f => [$g3w, $g3p]): ?>
        <label><span class="g3-l"><?= $h(MkMedium::gt('w_' . $g3f, $sprache)) ?></span>
          <select name="<?= $g3f ?>"><?php foreach ($g3w as $g3o): ?><option value="<?= $h($g3o) ?>"><?= $h(MkMedium::gt($g3p . $g3o, $sprache)) ?></option><?php endforeach; ?></select></label>
      <?php endforeach; ?>
    </div>
    <label class="g3-breit"><span class="g3-l"><?= $h(MkMedium::gt('w_titel', $sprache)) ?></span><input name="titel" maxlength="60" autocomplete="off"></label>
    <button class="knopf"><?= $h(MkMedium::gt('b_knopf', $sprache)) ?></button>
  </form>
  </details>
  <?php if ($g3Bestellt): ?>
    <ul class="klein" style="margin:10px 0 0;padding-left:18px">
      <?php foreach ($g3Bestellt as $g3b): ?><li><?= $h($g3b['studio'] !== '' ? (string) (MkMedium::GALERIE_SZENEN[$g3b['studio']][$sprache] ?? $g3b['studio']) : '„' . mb_strimwidth($g3b['text'], 0, 60, '…') . '“') ?> · <?= $h($g3b['art'] === 'video' ? MkMedium::gt('video', $sprache) : MkMedium::gt('b_bild', $sprache)) ?> · <?= $h(MkMedium::gt(['fehler' => 'b_fehler', 'pruefen' => 'b_pruefen', 'abgelehnt' => 'b_abgelehnt'][$g3b['status']] ?? 'b_offen', $sprache)) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="block pt" id="medien" data-reiter="werben">
  <h2><?= $h($T('md_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($T('md_text')) ?></p>
  <script type="application/json" id="medien_daten"><?= json_encode($medienDaten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

  <h3 class="md-h"><?= $h($T('bild_titel')) ?></h3>
  <p class="md-l"><?= $h($T('bild_motiv')) ?></p>
  <div class="chips">
    <?php $ersteM = true; foreach (Texte::PARTNER_MEDIEN['motive'] as $mk => $mv): ?>
      <button type="button" data-motiv="<?= $h($mk) ?>" aria-pressed="<?= $ersteM ? 'true' : 'false' ?>"><?= $h(Texte::h($mv['name'], $sprache)) ?></button>
    <?php $ersteM = false; endforeach; ?>
  </div>
  <p class="md-l"><?= $h($T('bild_format')) ?></p>
  <div class="chips">
    <?php foreach (['quadrat', 'hoch', 'story', 'quer', 'banner', 'qr', 'qrfoto'] as $i => $fk): ?>
      <button type="button" data-format="<?= $fk ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= $h($T('bf_' . $fk)) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="md-buehne"><canvas id="bild_vorschau" width="1080" height="1080" role="img" aria-label="<?= $h($T('bild_titel')) ?>"></canvas></div>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" id="bild_laden"><?= $h($T('bild_laden')) ?></button>
    <button class="knopf" type="button" id="bild_teilen" hidden><?= $h($T('bild_teilen')) ?></button>
  </div>

  <h3 class="md-h" style="margin-top:22px"><?= $h($T('video_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('video_text')) ?></p>
  <div class="knoepfe"><button class="knopf" type="button" id="video_erzeugen"><?= $h($T('video_erzeugen')) ?></button></div>
  <p class="klein" id="video_stand" role="status" aria-live="polite"></p>
  <video id="video_vorschau" class="md-video" controls playsinline muted loop hidden></video>
  <div class="knoepfe">
    <button class="knopf" type="button" id="video_laden" hidden><?= $h($T('video_laden')) ?></button>
    <button class="knopf" type="button" id="video_teilen" hidden><?= $h($T('video_teilen')) ?></button>
  </div>

  <h3 class="md-h" style="margin-top:22px"><?= $h($T('druck_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('druck_text')) ?></p>
  <div class="druckliste">
    <?php foreach (['visitenkarten', 'flyer', 'aufsteller', 'aufkleber'] as $dk): ?>
      <a class="knopf" href="<?= $h($selbst(['druck' => $dk])) ?>" target="_blank" rel="noopener"><?= $h($T('dr_' . $dk)) ?></a>
    <?php endforeach; ?>
    <a class="knopf" href="<?= $h($selbst(['karte' => 1])) ?>" target="_blank" rel="noopener"><?= $h($T('dr_karte')) ?></a>
  </div>

  <?php require_once dirname(__DIR__) . '/src/PartnerKarten.php';
        $vkK = in_array((string) ($_GET['ks'] ?? ''), PartnerKarten::KONTAKTE, true) ? (string) $_GET['ks'] : 'email';
        $vkS = in_array((string) ($_GET['vks'] ?? ''), ['it', 'de', 'en'], true) ? (string) $_GET['vks'] : $sprache;
        $vkL = static fn(array $x): string => $selbst(array_merge(['ks' => $vkK, 'vks' => $vkS], $x)); ?>
  <section class="vk" id="visitenkarten" aria-labelledby="vk_titel" style="margin-top:22px">
   <?php /* Große Bildraster klappen zu (03.10.2026): sie machten den Reiter „Werben“ am Handy 16.000 px lang. */ ?>
   <details class="klapp" data-klapp="visitenkarten"<?= isset($_GET['ks']) || isset($_GET['vks']) ? ' open' : '' ?>>
    <summary><h3 class="md-h" id="vk_titel"><?= $h($T('vk_titel')) ?></h3></summary>
    <p class="klein" style="margin-top:0"><?= $h(strtr($T('vk_text'), ['{link}' => PartnerKarten::kurz($p), '{name}' => PartnerKarten::name($p)])) ?></p>
    <div class="fl-chips" role="group" aria-label="<?= $h($T('vk_kontakt')) ?>">
      <span class="klein" style="align-self:center"><?= $h($T('vk_kontakt')) ?>:</span>
      <?php foreach (['email' => PartnerKarten::kontakt($p, 'email'), 'vecom' => PartnerKarten::VECOM_MAIL] as $kk => $kt): ?>
        <a class="knopf" style="min-height:38px;border-radius:999px;<?= $kk === $vkK ? 'border-color:rgba(241,211,139,.7);color:var(--text)' : '' ?>" aria-current="<?= $kk === $vkK ? 'true' : 'false' ?>"
           href="<?= $h($selbst(['ks' => $kk, 'vks' => $vkS]) . '#visitenkarten') ?>"><?= $h($kt) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="fl-chips" role="group" aria-label="<?= $h($T('vk_sprache')) ?>">
      <span class="klein" style="align-self:center"><?= $h($T('vk_sprache')) ?>:</span>
      <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $ls => $lw): ?>
        <a class="knopf" style="min-height:38px;border-radius:999px;<?= $ls === $vkS ? 'border-color:rgba(241,211,139,.7);color:var(--text)' : '' ?>" aria-current="<?= $ls === $vkS ? 'true' : 'false' ?>"
           href="<?= $h($selbst(['ks' => $vkK, 'vks' => $ls]) . '#visitenkarten') ?>"><?= $h($lw) ?></a>
      <?php endforeach; ?>
    </div>
    <ul class="vk-raster">
      <?php foreach (PartnerKarten::STILE as $vs => $vn): if (!PartnerKarten::gibt($vs)) { continue; } $vName = $vn[$sprache] ?? $vn['de']; ?>
        <li class="vk-karte">
          <img src="<?= $h($vkL(['vk' => $vs, 'f' => 'vorschau'])) ?>" alt="<?= $h(strtr($T('vk_alt'), ['{name}' => $vName])) ?>" width="720" height="231" loading="lazy" decoding="async">
          <b><?= $h($vName) ?></b>
          <span class="vk-knoepfe">
            <a class="knopf haupt" href="<?= $h($vkL(['vk' => $vs, 'f' => 'pdf'])) ?>" download><?= $h($T('vk_pdf')) ?></a>
            <a class="knopf" href="<?= $h($vkL(['vk' => $vs, 'f' => 'bogen'])) ?>" download><?= $h($T('vk_bogen')) ?></a>
            <a class="knopf" href="<?= $h($vkL(['vk' => $vs, 'f' => 'vorn'])) ?>" download><?= $h($T('vk_vorn')) ?></a>
            <a class="knopf" href="<?= $h($vkL(['vk' => $vs, 'f' => 'hinten'])) ?>" download><?= $h($T('vk_hinten')) ?></a>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="klein"><?= $h($T('vk_hinweis')) ?></p>
   </details>
  </section>

  <?php require_once dirname(__DIR__) . '/src/PartnerFlyer.php'; $flGruppen = PartnerFlyer::gruppiert(); ?>
  <?php if ($flGruppen): ?>
  <section class="fl" id="flyer" aria-labelledby="fl_titel">
   <details class="klapp" data-klapp="flyer">
    <summary><h3 class="md-h" id="fl_titel"><?= $h($T('fl_titel')) ?></h3></summary>
    <p class="klein" style="margin-top:0"><?= $h(strtr($T('fl_text'), ['{link}' => PartnerFlyer::kurz($p)])) ?></p>
    <div class="fl-chips" role="group" aria-label="<?= $h($T('fl_filter')) ?>">
      <button type="button" data-flg="" aria-pressed="true"><?= $h($T('fl_alle')) ?> <span><?= count(PartnerFlyer::liste()) ?></span></button>
      <?php foreach ($flGruppen as $gk => $gl): ?>
        <button type="button" data-flg="<?= $h($gk) ?>" aria-pressed="false"><?= $h(PartnerFlyer::GRUPPEN[$gk][$sprache] ?? $gk) ?> <span><?= count($gl) ?></span></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($flGruppen as $gk => $gl): ?>
      <div class="fl-gruppe" data-flgruppe="<?= $h($gk) ?>">
        <p class="md-l"><?= $h(PartnerFlyer::GRUPPEN[$gk][$sprache] ?? $gk) ?></p>
        <ul class="fl-raster">
          <?php foreach ($gl as $fs => $ff):
              $fn = PartnerFlyer::name($fs, $sprache); $fm = PartnerFlyer::mass($fs); $fk = PartnerFlyer::vorschauFaktor($fs);
              $fSps = PartnerFlyer::sprachen($fs); $fSp = PartnerFlyer::sprache($fs, $sprache);
              $fA = $fSp !== '' ? ['fsp' => $fSp] : []; ?>
            <li class="fl-karte"<?= $fSps ? ' data-flsp' : '' ?>>
              <img src="<?= $h($selbst(['fl' => $fs, 'f' => 'vorschau'] + $fA)) ?>" alt="<?= $h(strtr($T('fl_alt'), ['{name}' => $fn])) ?>"
                   width="<?= (int) round($fm['b'] * $fk) ?>" height="<?= (int) round($fm['h'] * $fk) ?>" loading="lazy" decoding="async">
              <?php if ($fSps): ?><span class="fl-neu"><?= $h($T('fl_neu')) ?></span><?php endif; ?>
              <b><?= $h($fn) ?></b>
              <?php if ($fSps): ?>
              <span class="fl-sp" role="group" aria-label="<?= $h($T('fl_sprache')) ?>">
                <?php foreach ($fSps as $fx): ?>
                  <button type="button" data-sp="<?= $h($fx) ?>" aria-pressed="<?= $fx === $fSp ? 'true' : 'false' ?>"
                          data-v="<?= $h($selbst(['fl' => $fs, 'f' => 'vorschau', 'fsp' => $fx])) ?>"
                          data-j="<?= $h($selbst(['fl' => $fs, 'f' => 'jpg', 'fsp' => $fx])) ?>"
                          data-p="<?= $h($selbst(['fl' => $fs, 'f' => 'pdf', 'fsp' => $fx])) ?>"><?= $h(strtoupper($fx)) ?></button>
                <?php endforeach; ?>
              </span>
              <?php endif; ?>
              <span class="fl-knoepfe">
                <a class="knopf" data-fla="j" href="<?= $h($selbst(['fl' => $fs, 'f' => 'jpg'] + $fA)) ?>" download aria-label="<?= $h($fn . ': ' . $T('fl_jpg')) ?>"><?= $h($T('fl_jpg')) ?></a>
                <a class="knopf" data-fla="p" href="<?= $h($selbst(['fl' => $fs, 'f' => 'pdf'] + $fA)) ?>" download aria-label="<?= $h($fn . ': ' . $T('fl_pdf')) ?>"><?= $h($T('fl_pdf')) ?></a>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
    <p class="klein"><?= $h($T('fl_hinweis')) ?></p>
   </details>
  </section>
  <script>
  (function () {
    var s = document.getElementById('flyer'); if (!s) { return; }
    var k = s.querySelectorAll('.fl-chips button'), g = s.querySelectorAll('.fl-gruppe');
    k.forEach(function (b) {
      b.addEventListener('click', function () {
        var w = b.getAttribute('data-flg');
        k.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        g.forEach(function (x) { x.hidden = w !== '' && x.getAttribute('data-flgruppe') !== w; });
      });
    });
    // Sprache je Flyer: Vorschau und beide Downloads wechseln mit (04.10.2026).
    s.querySelectorAll('[data-flsp]').forEach(function (karte) {
      var bs = karte.querySelectorAll('.fl-sp button');
      bs.forEach(function (b) {
        b.addEventListener('click', function () {
          bs.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
          karte.querySelector('img').src = b.getAttribute('data-v');
          karte.querySelector('[data-fla=j]').href = b.getAttribute('data-j');
          karte.querySelector('[data-fla=p]').href = b.getAttribute('data-p');
        });
      });
    });
  })();
  </script>
  <?php endif; ?>
</div>

<?php $foto = PartnerWerbung::fotoAdresse($p); $pfFehler = in_array($meldung, ['satz_link', 'satz_lang', 'foto_gross', 'foto_art'], true); ?>
<div class="block pt" id="profil" data-reiter="profil">
  <h2><?= $h($T('pf_titel')) ?></h2>
  <?php if (($_GET['m'] ?? '') === 'pf_gut'): ?><div class="hinweis gut" role="status"><?= $h($T('pf_gut')) ?></div><?php endif; ?>
  <?php if ($pfFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div><?php endif; ?>
  <p class="klein" style="margin-top:0"><?= $h($T('pf_text')) ?></p>
  <div class="profil-kopf">
    <?php if ($foto): ?><img src="<?= $h($foto) ?>" alt="" width="72" height="72"><?php else: ?><span class="leer" aria-hidden="true">★</span><?php endif; ?>
    <a href="<?= $h('/p.php?' . http_build_query(['c' => $p['code'], 'lang' => $sprache, 'n' => 1])) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-size:14.5px"><?= $h($T('pf_vorschau')) ?></a>
  </div>
  <form method="post" action="<?= $h($selbst()) ?>#profil" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="profil">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PartnerWerbung::FOTO_MAX_BYTE ?>">
    <label for="pf_foto"><?= $h($T('pf_foto')) ?></label>
    <input id="pf_foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
    <label for="pf_satz"><?= $h($T('pf_satz')) ?></label>
    <textarea id="pf_satz" name="satz" rows="2" maxlength="<?= PartnerWerbung::SATZ_MAX ?>" placeholder="<?= $h($T('pf_satz_ph')) ?>"><?= $h((string) ($_POST['satz'] ?? $p['profil_satz'] ?? '')) ?></textarea>
    <button class="knopf haupt" type="submit"><?= $h($T('pf_speichern')) ?></button>
  </form>
  <?php if ($foto): ?>
    <form method="post" action="<?= $h($selbst()) ?>#profil" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="tat" value="foto_weg">
      <button class="knopf" type="submit" style="align-self:flex-start"><?= $h($T('pf_foto_weg')) ?></button>
    </form>
  <?php endif; ?>
</div>

<script>
/* Reiter, Kopieren, Teilen. Ohne Skript bleiben alle Tafeln sichtbar. */
(function () {
  var reiter = document.querySelector('#werbung .reiter'), tabs = reiter ? [].slice.call(reiter.querySelectorAll('[role=tab]')) : [];
  function zeige(t, fokus) {
    tabs.forEach(function (b) {
      var an = b === t; b.setAttribute('aria-selected', an ? 'true' : 'false'); b.tabIndex = an ? 0 : -1;
      document.getElementById(b.getAttribute('aria-controls')).hidden = !an;
    });
    if (fokus) { t.focus(); }
    try { localStorage.setItem('vecom_pk_reiter', t.id); } catch (e) {}
    wachsen();
  }
  if (reiter) {
    reiter.hidden = false;
    [].forEach.call(document.querySelectorAll('#werbung .nur-ohne-js'), function (e) { e.hidden = true; });
    var start = tabs[0]; try { var m = localStorage.getItem('vecom_pk_reiter'); if (m && document.getElementById(m)) { start = document.getElementById(m); } } catch (e) {}
    tabs.forEach(function (b, i) {
      b.addEventListener('click', function () { zeige(b, false); });
      b.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : null;
        if (n === null) { return; } e.preventDefault(); zeige(tabs[(n + tabs.length) % tabs.length], true);
      });
    });
    zeige(start, false);
  }
  function wachsen() {
    [].forEach.call(document.querySelectorAll('textarea[data-wachsen]'), function (t) {
      if (t.offsetParent === null) { return; } t.style.height = 'auto'; t.style.height = (t.scrollHeight + 4) + 'px';
    });
  }
  window.addEventListener('resize', wachsen);
  function geklappt(b) { var alt = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = alt; }, 1600); }
  function kopiere(text, b) {
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(function () { geklappt(b); }, function () {}); return; }
    var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); geklappt(b); } catch (e) {} t.remove();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-kopie],[data-kopie-text]'); if (!b) { return; }
    kopiere(b.dataset.kopieText || document.getElementById(b.dataset.kopie).value, b);
  });
  /* Instagram und TikTok: Das Handy teilt selbst (Teilen-Menü mit allen Apps). */
  if (navigator.share) {
    [].forEach.call(document.querySelectorAll('[data-teilen]'), function (b) {
      b.hidden = false;
      b.addEventListener('click', function () {
        navigator.share({ text: document.getElementById(b.dataset.teilen).value }).catch(function () {});
      });
    });
  }
  /* Signatur als formatierter Text -- eingefügt in Gmail/Outlook bleibt der Knopf ein Knopf. */
  var sk = document.getElementById('sig_kopieren');
  if (sk) sk.addEventListener('click', function () {
    var html = document.getElementById('sig_html').value, vor = document.getElementById('sig_vorschau');
    if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
      navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([html], { type: 'text/html' }), 'text/plain': new Blob([vor.innerText], { type: 'text/plain' }) })])
        .then(function () { geklappt(sk); }, function () { markiere(); });
    } else { markiere(); }
    function markiere() { var r = document.createRange(); r.selectNodeContents(vor); var s = getSelection(); s.removeAllRanges(); s.addRange(r); try { document.execCommand('copy'); geklappt(sk); } catch (e) {} }
  });
})();
</script>
