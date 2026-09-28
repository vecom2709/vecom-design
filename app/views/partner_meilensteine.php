<?php
/* Meilensteine (28.09.2026, Uwe: Ja). Reiter „Start“. Erreichte kann der
   Partner als Bild teilen (Zeichnung in partner-medien.js, Daten in
   #plus_daten). Kein Geld, keine Kundennamen -- nur Anerkennung.
   Gesetzt: $p, $sprache, $h. */
$PP = static fn(string $k): string => Texte::h(Texte::PARTNER_PLUS[$k] ?? [], $sprache);
$msStand = PartnerMarketing::meilensteine($p);
$msSymbol = [
    'profil' => '<circle cx="12" cy="9" r="4"/><path d="M4.5 20c1.4-3.6 4.2-5.4 7.5-5.4s6.1 1.8 7.5 5.4"/>',
    'klick1' => '<path d="M8 3v10l3-2.4 2.2 4.8 2-1-2.2-4.6 3.8-.4z"/>',
    'klick10' => '<path d="M3 17l5-5 4 4 8-8"/><path d="M15 8h5v5"/>',
    'klick100' => '<path d="M3 20h18"/><path d="M6 20V11M11 20V6M16 20v-9M21 20V3"/>',
    'check5' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 9l2 2 4-4M8 15h8"/>',
    'anfrage1' => '<path d="M4 5h16v11H8l-4 4z"/>',
    'kunde1' => '<path d="M12 3l2.6 5.6 6 .6-4.6 4 1.4 6-5.4-3.2-5.4 3.2 1.4-6-4.6-4 6-.6z"/>',
    'kunde5' => '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>',
    'geld1' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/>',
    'kurs' => '<path d="M3 8l9-4 9 4-9 4z"/><path d="M7 10v5c0 1.6 2.3 3 5 3s5-1.4 5-3v-5"/>',
];
?>
<div class="block pt" id="meilensteine" data-reiter="start">
  <div class="pp-kopf"><h2><?= $h($PP('ms_titel')) ?></h2><span class="klein"><?= count(array_filter($msStand)) ?>/<?= count($msStand) ?></span></div>
  <p class="klein" style="margin-top:0"><?= $h($PP('ms_text')) ?></p>
  <ul class="pp-ms">
    <?php foreach ($msStand as $mk => $ja): ?>
      <li class="<?= $ja ? 'ja' : 'nein' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $msSymbol[$mk] ?></svg>
        <span><?= $h(Texte::h(Texte::PARTNER_PLUS['meilensteine'][$mk], $sprache)) ?></span>
        <?php if ($ja && $mk !== 'geld1'): ?><button type="button" class="pp-ms-teilen" data-meilenstein="<?= $h($mk) ?>"><?= $h($PP('ms_teilen')) ?></button><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <canvas id="ms_vorschau" width="1080" height="1080" hidden></canvas>
</div>
