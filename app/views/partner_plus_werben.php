<?php
/* Reiter „Werben“ (28.09.2026, Uwe: Ja): Branchen-Pakete, Gutschein zum
   Verschenken, Kundenstimmen zum Teilen. Eingebunden aus partner.php nach
   dem Medien-Block. Gutschein-, Stimmen- und Meilenstein-Bilder zeichnet
   partner-medien.js aus #plus_daten -- im Browser, nichts geht zu uns.
   Gesetzt: $p, $sprache, $h, $T. */
$PP = static fn(string $k): string => Texte::h(Texte::PARTNER_PLUS[$k] ?? [], $sprache);
$brPakete = [];
foreach (PartnerMarketing::BRANCHEN as $bk) { $brPakete[$bk] = PartnerMarketing::branche($p, $bk, $sprache); }
$stListe = PartnerSeite::stimmen($p, $sprache, 6);
$stLink = PartnerWerbung::link($p, 'stimme');
$stWer = static fn(array $s): string => $s['name'] . ($s['firma'] !== '' ? ', ' . $s['firma'] : '') . ($s['ort'] !== '' ? ' · ' . $s['ort'] : '');
$gsKarte = Texte::PARTNER_PLUS['gs_karte'];
$plusDaten = [
    'gutschein' => [
        'link' => PartnerWerbung::link($p, 'gutschein'),
        'name' => Partner::anzeigeName($p),
        'texte' => array_combine(['it', 'de', 'en'], array_map(static fn(string $l) => array_map(static fn(array $t) => Texte::h($t, $l), $gsKarte), ['it', 'de', 'en'])),
    ],
    'stimmen' => array_map(static fn(array $s) => ['text' => $s['text'], 'wer' => $stWer($s), 'sterne' => $s['sterne']], $stListe),
    'stimme_link' => $stLink,
    'meilensteine' => array_map(static fn(array $t) => Texte::h($t, $sprache), Texte::PARTNER_PLUS['meilensteine']),
    'ms_karte' => $PP('ms_karte'), 'ms_unter' => $PP('ms_karte_unter'), 'ms_link' => PartnerWerbung::link($p, 'meilenstein'),
];
?>
<script type="application/json" id="plus_daten"><?= json_encode($plusDaten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<div class="block pt" id="branchen" data-reiter="werben">
  <h2><?= $h($PP('br_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('br_text')) ?></p>
  <div class="chips" role="group" aria-label="<?= $h($PP('br_titel')) ?>">
    <?php $brErst = true; foreach ($brPakete as $bk => $bp): ?>
      <button type="button" data-br="<?= $h($bk) ?>" aria-pressed="<?= $brErst ? 'true' : 'false' ?>"><?= $h($bp['name']) ?></button>
    <?php $brErst = false; endforeach; ?>
  </div>
  <?php $brErst = true; foreach ($brPakete as $bk => $bp): ?>
    <div class="pp-br" data-br-feld="<?= $h($bk) ?>"<?= $brErst ? '' : ' hidden' ?>>
      <p class="md-l"><?= $h($PP('br_warum')) ?></p>
      <p class="pp-warum"><?= $h($bp['warum']) ?></p>
      <p class="md-l"><?= $h($PP('br_args')) ?></p>
      <ul class="pp-args"><?php foreach ($bp['args'] as $ba): ?><li><?= $h($ba) ?></li><?php endforeach; ?></ul>
      <p class="md-l"><?= $h($PP('br_satz')) ?></p>
      <p class="pp-satz">„<?= $h($bp['satz']) ?>“</p>
      <p class="md-l"><?= $h($PP('br_wa')) ?></p>
      <textarea id="br_wa_<?= $h($bk) ?>" readonly rows="6"><?= $h($bp['wa']) ?></textarea>
      <div class="knoepfe">
        <a class="knopf haupt" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($bp['wa']) ?>">WhatsApp</a>
        <button class="knopf" type="button" data-kopie="br_wa_<?= $h($bk) ?>"><?= $h($PP('ak_kopieren')) ?></button>
      </div>
      <p class="md-l"><?= $h($PP('br_post')) ?></p>
      <textarea id="br_post_<?= $h($bk) ?>" readonly rows="4"><?= $h($bp['post']) ?></textarea>
      <div class="knoepfe">
        <button class="knopf" type="button" data-kopie="br_post_<?= $h($bk) ?>"><?= $h($PP('ak_kopieren')) ?></button>
        <button class="knopf" type="button" data-teilen-text="<?= $h($bp['post']) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
        <a class="knopf" href="#medien" data-br-bild="<?= $h($bk) ?>"><?= $h($PP('br_bild')) ?></a>
      </div>
    </div>
  <?php $brErst = false; endforeach; ?>
</div>

<div class="block pt" id="gutschein" data-reiter="werben">
  <h2><?= $h($PP('gs_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('gs_text')) ?></p>
  <label for="gs_firma"><?= $h($PP('gs_fuer')) ?></label>
  <input id="gs_firma" type="text" maxlength="60" autocomplete="off" placeholder="<?= $h(['it' => 'Pizzeria Da Mario', 'de' => 'Bäckerei Müller', 'en' => 'Mario’s Pizzeria'][$sprache] ?? 'Pizzeria Da Mario') ?>">
  <p class="md-l"><?= $h($PP('gs_sprache')) ?></p>
  <div class="chips" role="group" aria-label="<?= $h($PP('gs_sprache')) ?>">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $gl => $gw): ?>
      <button type="button" data-gs-sprache="<?= $gl ?>" aria-pressed="<?= $gl === $sprache ? 'true' : 'false' ?>"><?= $gw ?></button>
    <?php endforeach; ?>
  </div>
  <div class="md-buehne"><canvas id="gs_vorschau" width="1080" height="1350" role="img" aria-label="<?= $h($PP('gs_titel')) ?>"></canvas></div>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" id="gs_laden"><?= $h($PP('gs_laden')) ?></button>
    <button class="knopf" type="button" id="gs_teilen" hidden><?= $h($PP('gs_teilen')) ?></button>
  </div>
  <p class="md-l"><?= $h($PP('gs_begleit')) ?></p>
  <textarea id="gs_text" readonly rows="4"></textarea>
  <div class="knoepfe">
    <button class="knopf" type="button" data-kopie="gs_text"><?= $h($PP('ak_kopieren')) ?></button>
    <a class="knopf" target="_blank" rel="noopener" id="gs_wa" href="https://wa.me/" data-basis="https://wa.me/?text=">WhatsApp</a>
  </div>
</div>

<div class="block pt" id="arbeiten-teilen" data-reiter="werben">
  <h2><?= $h($PP('bw_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('bw_text')) ?></p>
  <ul class="pp-bw">
    <?php foreach (PartnerSeite::ARBEITEN_STANDARD as $bwA): $bwT = Texte::PARTNER_SEITE['arbeiten'][$bwA]; $bwU = (string) PartnerSeite::arbeitUrl($bwA); ?>
      <li><a href="<?= $h($bwU) ?>" target="_blank" rel="noopener"><img src="/assets/img/arbeiten/<?= $h($bwA) ?>/an.webp" alt="" width="2400" height="1350" loading="lazy" decoding="async">
        <b><?= $h($bwT['name']) ?></b><small><?= $h(preg_replace('~^www\.~', '', (string) parse_url($bwU, PHP_URL_HOST))) ?></small></a></li>
    <?php endforeach; ?>
  </ul>
  <p class="md-l"><?= $h($PP('bw_sprache')) ?></p>
  <div class="chips" role="group" aria-label="<?= $h($PP('bw_sprache')) ?>">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $bl => $bn): ?>
      <button type="button" data-bw-sprache="<?= $bl ?>" aria-pressed="<?= $bl === $sprache ? 'true' : 'false' ?>"><?= $bn ?></button>
    <?php endforeach; ?>
  </div>
  <?php foreach (['it', 'de', 'en'] as $bl): $bwB = PartnerMarketing::arbeitenBeitrag($p, $bl); ?>
    <div data-bw-feld="<?= $bl ?>"<?= $bl === $sprache ? '' : ' hidden' ?>>
      <textarea id="bw_<?= $bl ?>" readonly rows="10" lang="<?= $bl ?>"><?= $h($bwB) ?></textarea>
      <div class="knoepfe">
        <a class="knopf haupt" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($bwB) ?>">WhatsApp</a>
        <button class="knopf" type="button" data-kopie="bw_<?= $bl ?>"><?= $h($PP('ak_kopieren')) ?></button>
        <button class="knopf" type="button" data-teilen-text="<?= $h($bwB) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($stListe): ?>
<div class="block pt" id="stimmen-teilen" data-reiter="werben">
  <h2><?= $h($PP('st_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('st_text')) ?></p>
  <?php foreach ($stListe as $si => $st): $stB = strtr($PP('st_beitrag'), ['{text}' => $st['text'], '{wer}' => $stWer($st), '{link}' => $stLink]); ?>
    <figure class="pp-stimme">
      <?php if ($st['sterne']): ?><div class="pp-sterne" aria-label="<?= (int) $st['sterne'] ?>/5"><?= str_repeat('★', (int) $st['sterne']) ?></div><?php endif; ?>
      <blockquote><?= $h($st['text']) ?></blockquote>
      <figcaption>— <?= $h($stWer($st)) ?></figcaption>
      <textarea id="st_<?= $si ?>" readonly rows="4" hidden><?= $h($stB) ?></textarea>
      <div class="knoepfe">
        <button class="knopf" type="button" data-kopie="st_<?= $si ?>"><?= $h($PP('ak_kopieren')) ?></button>
        <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($stB) ?>">WhatsApp</a>
        <button class="knopf" type="button" data-stimme-bild="<?= $si ?>"><?= $h($PP('st_bild')) ?></button>
      </div>
    </figure>
  <?php endforeach; ?>
  <canvas id="st_vorschau" width="1080" height="1080" hidden></canvas>
</div>
<?php endif; ?>
