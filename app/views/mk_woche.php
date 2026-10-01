<?php
/**
 * „Diese Woche werben“ — ein Knopf je Land (01.10.2026, Uwe: Ja zu M2).
 * Steht oben auf „Zahlen“ und „Freigeben“. Gilt für das oben gewählte Land.
 * Erwartet: optional $wwZurueck ('marketing'|'freigabe').
 */
require_once dirname(__DIR__) . '/src/MkLand.php';
require_once dirname(__DIR__) . '/src/MkAutopilot.php';
$wwLand = MkLand::wahl();
$wwZ = MkAutopilot::naechsteZielgruppe($wwLand);
$wwE = MkAutopilot::einstellung($wwLand);
$wwLaeuft = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'inhalte' AND land = ? AND status IN ('wartet','laeuft')", [$wwLand], 0) > 0;
$wwDieseWoche = (string) Db::wert("SELECT svalue FROM settings WHERE skey = ?", ['mk_autopilot_woche_' . $wwLand], '') === date('o-\WW'); ?>
<section class="block mk-woche" aria-labelledby="mk-ww-titel" style="margin:0 0 16px">
  <div style="display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div style="min-width:0;flex:1 1 320px">
      <h2 id="mk-ww-titel" style="margin:0 0 4px"><?= MkLand::marke($wwLand) ?> Diese Woche werben</h2>
      <p class="mk-fein" style="margin:0;line-height:1.55">
        <?php if ($wwZ === null): ?>Noch keine freigegebene Zielgruppe in <?= Fmt::h(MkLand::name($wwLand)) ?> — erst unter „Zielgruppen“ recherchieren lassen und freigeben.
        <?php elseif ($wwLaeuft): ?>Claude schreibt gerade für <?= Fmt::h(MkLand::name($wwLand)) ?>. Sind Texte und Bilder fertig, kommt die Telegram-Nachricht zum Absegnen.
        <?php else: ?>Ein Klick: Claude schreibt für „<?= Fmt::h((string) $wwZ['titel']) ?>“ Beiträge<?= $wwE['anzeigen'] ? ' und Anzeigen' : '' ?><?= $wwE['bilder'] ? ', die Bilder entstehen dazu' : '' ?>, dann kommt alles per Telegram — Stück für Stück Ja oder Nein. Freigegebenes geht abends automatisch raus.<?= $wwDieseWoche ? ' Diese Woche lief schon eine Runde.' : '' ?>
        <?php endif; ?></p>
    </div>
    <?php if ($wwZ !== null && !$wwLaeuft): ?>
      <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="woche_werben">
        <input type="hidden" name="land" value="<?= Fmt::h($wwLand) ?>"><input type="hidden" name="zurueck" value="<?= Fmt::h($wwZurueck ?? 'freigabe') ?>">
        <button class="knopf haupt" style="min-height:48px;padding:0 22px;font-size:15.5px"><?= $wwDieseWoche ? 'Noch eine Runde' : 'Diese Woche werben' ?> — <?= Fmt::h(MkLand::name($wwLand)) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>
