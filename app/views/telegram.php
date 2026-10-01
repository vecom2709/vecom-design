<?php
/**
 * Marketing · Telegram (Telegram Growth Engine T2 — 01.10.2026).
 *
 * Was Telegram bringt, nur aus Gemessenem (TelegramZahlen): neue Nutzer,
 * Kanal-Mitglieder, der Weg bis zum Kunden, Quellen mit Growth Score.
 * Was Telegram nicht hergibt, steht als „noch nicht messbar“ da, nie als 0.
 *
 * Erwartet: $z (MkKennzahlen::zeitraum), $d (TelegramZahlen::dashboard).
 */
[$von, $bis, $zk, $vv, $vb] = $z;
$s = $d['summe']; $sv = $d['vorher']; $k = $d['kanal']; $b = $d['beste']; $hz = $d['heute'];
$geld = static fn(int $c): string => Fmt::geld($c);
$zahl = static fn(?int $x): string => $x === null ? '–' : number_format($x, 0, ',', '.');
$pz = static fn(?float $x, int $st = 1): string => $x === null ? '—' : number_format($x, $st, ',', '.') . ' %';
$datum = static fn(string $t): string => date('d.m.Y', strtotime($t));
$n = static fn(string $art) => (int) ($s[$art] ?? 0);
$hier = static fn(array $mehr = []): string => url('telegram') . '?' . http_build_query(array_filter(array_merge(
    ['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null], $mehr), static fn($v) => $v !== null && $v !== ''));
$trend = static function (int $jetzt, int $vorher): string {
    $t = MkKennzahlen::trend($jetzt, $vorher);
    if ($t === null) { return $jetzt > 0 ? '<span class="mk-trend">neu im Zeitraum</span>' : ''; }
    if (abs($t) < 0.5) { return '<span class="mk-trend">wie im Vorzeitraum</span>'; }
    return '<span class="mk-trend ' . ($t > 0 ? 'auf' : 'ab') . '">' . ($t > 0 ? '▲' : '▼') . ' ' . number_format(abs($t), 0, ',', '.') . ' % zum Vorzeitraum</span>';
};
$beste = static function (string $titel, ?array $b, string $einheit, string $leer) use ($zahl): void { ?>
    <div class="mk-best">
      <h3><?= Fmt::h($titel) ?></h3>
      <?php if ($b === null): ?>
        <p class="mk-best__leer"><?= Fmt::h($leer) ?></p>
      <?php else: ?>
        <b class="mk-best__name" title="<?= Fmt::h($b['name']) ?>"><?= Fmt::h($b['name']) ?></b>
        <span class="mk-best__zahl"><?= Fmt::h($zahl($b['zahl'])) ?> <?= Fmt::h($b['zahl'] === 1 && $einheit === 'Leads' ? 'Lead' : $einheit) ?></span>
        <?php if ($b['zweiter'] !== null): ?><span class="mk-best__zweit">danach: <?= Fmt::h($b['zweiter']) ?> (<?= Fmt::h($zahl($b['zweiter_zahl'])) ?>)</span><?php endif; ?>
        <?php if ($b['wenig']): ?><span class="marke2 warnung mk-best__marke">wenige Daten</span><?php endif; ?>
      <?php endif; ?>
    </div>
<?php };
$tage = $d['tage'];
$schnitt = static fn(?int $summe): string => number_format((int) $summe / $tage, 1, ',', '.');
require __DIR__ . '/mk_stil.php';
?>
<style>.tg-tab td.mk-name{min-width:200px}.tg-tab th,.tg-tab td{padding-left:8px;padding-right:8px}</style>
<div class="mk-kopf">
  <div>
    <h1>Telegram <span class="leise" style="font-weight:400;font-size:15px">· Growth Engine</span></h1>
    <div class="weg"><?= Fmt::h($datum($von)) ?><?= $von !== $bis ? ' – ' . Fmt::h($datum($bis)) : '' ?> · verglichen mit <?= Fmt::h($datum($vv)) ?><?= $vv !== $vb ? ' – ' . Fmt::h($datum($vb)) : '' ?></div>
  </div>
  <nav class="mk-chips" aria-label="Weiter">
    <a href="<?= Fmt::h(url('kampagnen')) ?>">Kampagnen</a>
    <a href="<?= Fmt::h(url('einstellungen') . '?b=telegram') ?>">Bot &amp; Kanal</a>
  </nav>
</div>

<form class="mk-filter" method="get" action="<?= Fmt::h(url('telegram')) ?>">
  <nav class="mk-chips" aria-label="Zeitraum">
    <?php foreach (MkKennzahlen::ZEITRAEUME as $zs => $zw): if ((string) $zs === 'frei') { continue; } ?>
      <a href="<?= Fmt::h($hier(['z' => (string) $zs, 'von' => null, 'bis' => null])) ?>"<?= $zk === (string) $zs ? ' aria-current="page"' : '' ?>><?= Fmt::h($zw) ?></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="z" value="frei">
  <div class="feld"><label for="tg_von" class="leise" style="margin:0">von</label><input id="tg_von" type="date" name="von" value="<?= Fmt::h($von) ?>"></div>
  <div class="feld"><label for="tg_bis" class="leise" style="margin:0">bis</label><input id="tg_bis" type="date" name="bis" value="<?= Fmt::h($bis) ?>"></div>
  <button class="knopf">Zeitraum anzeigen</button>
</form>

<div class="karten mk-karten">
  <div class="karte"><h3>Neue Bot-Nutzer</h3><div class="wert"><?= $zahl($n('bot_neu')) ?></div>
    <div class="neben">heute <?= $zahl((int) $d['neu']['heute']) ?> · 7 Tage <?= $zahl((int) $d['neu']['7']) ?> · 30 Tage <?= $zahl((int) $d['neu']['30']) ?></div><?= $trend($n('bot_neu'), (int) ($sv['bot_neu'] ?? 0)) ?></div>
  <div class="karte"><h3>Kanal-Mitglieder</h3><div class="wert"><?= $zahl($k['stand']) ?></div>
    <div class="neben"><?php if ($k['stand'] === null): ?>noch nicht gemessen — der tägliche Lauf trägt die Zahl ein<?php else: ?>Stand <?= Fmt::h($datum((string) $k['stand_tag'])) ?><?php if ($k['wachstum'] !== null): ?> · <?= $k['wachstum'] >= 0 ? '+' : '' ?><?= $zahl($k['wachstum']) ?> seit <?= Fmt::h($datum((string) $k['anfang_tag'])) ?> (<?= Fmt::h($pz($k['wachstum_pct'])) ?>)<?php endif; ?><?php endif; ?></div>
    <span class="mk-trend">im Zeitraum: +<?= $zahl($k['bei']) ?> beigetreten · −<?= $zahl($k['aus']) ?> ausgetreten</span></div>
  <div class="karte"><h3>Aktive Nutzer</h3><div class="wert"><?= $zahl((int) ($hz['bot_aktiv'] ?? 0)) ?></div>
    <div class="neben">heute · Ø <?= Fmt::h($schnitt($s['bot_aktiv'])) ?> pro Tag im Zeitraum</div></div>
  <div class="karte"><h3>Wiederkehrend</h3><div class="wert"><?= $zahl((int) ($hz['bot_wieder'] ?? 0)) ?></div>
    <div class="neben">heute · Ø <?= Fmt::h($schnitt($s['bot_wieder'])) ?> pro Tag — kamen nach mindestens einem Tag wieder</div></div>
  <div class="karte"><h3>Preisrechner</h3><div class="wert"><?= $zahl($n('rechner')) ?></div>
    <div class="neben"><?= $zahl($n('rechner_fertig')) ?> abgeschlossen · <?= $zahl($n('app_start')) ?> davon als Mini-App im Kanal</div></div>
  <div class="karte"><h3>Leads</h3><div class="wert"><?= $zahl($n('lead')) ?></div>
    <div class="neben"><?= $zahl($n('interesse')) ?> neue Interessenten · <?= $zahl($n('beratung')) ?> Beratungen · <?= $zahl($d['partner_leads']) ?> über Partner</div><?= $trend($n('lead'), (int) ($sv['lead'] ?? 0)) ?></div>
  <div class="karte"><h3>Kunden aus Telegram</h3><div class="wert"><?= $zahl($d['kunden']) ?></div>
    <div class="neben">erste Zahlung im Zeitraum</div><?= $trend($d['kunden'], $d['kunden_vorher']) ?></div>
  <div class="karte"><h3>Umsatz aus Telegram</h3><div class="wert"><?= Fmt::h($geld($d['umsatz'])) ?></div>
    <div class="neben"><?= $d['kosten'] > 0 ? 'Kampagnenkosten ' . Fmt::h($geld($d['kosten'])) : 'von Kunden, die über Telegram kamen' ?></div><?= $trend($d['umsatz'], $d['umsatz_vorher']) ?></div>
</div>

<?php if ($d['hinweise']): ?>
<div class="block">
  <h2>Was auffällt</h2>
  <ul class="mk-liste probleme"><?php foreach ($d['hinweise'] as $t): ?><li><?= Fmt::h($t) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="block">
  <h2>Das Beste im Zeitraum</h2>
  <div class="mk-besten">
    <?php $beste('Beste Quelle', $b['quelle'], 'Punkte (Growth Score)', 'Noch keine Quelle mit Nutzern oder Anfragen im Zeitraum.'); ?>
    <?php $beste('Beste Kampagne', $b['kampagne'], $b['kampagne_nach'], 'Noch kein Start über den Telegram-Link einer Kampagne.'); ?>
    <?php $beste('Bester Partner', $b['partner'], $b['partner_nach'], 'Noch niemand über einen Partner-Link im Bot.'); ?>
    <div class="mk-best"><h3>Beste Telegram-Gruppe</h3><p class="mk-best__leer">Noch nicht messbar — kommt mit den Verzeichnissen und Kooperationen (T5): jede Gruppe bekommt dort ihren eigenen Link.</p></div>
    <div class="mk-best"><h3>Beste Inhalte</h3><p class="mk-best__leer">Noch nicht messbar — kommt mit dem Redaktionsplan (T6): jeder Beitrag bekommt seinen eigenen Link.</p></div>
  </div>
</div>

<?php $f = $d['funnel']; $max = max(1, ...array_map(static fn($x) => (int) $x[1], $f)); ?>
<div class="block">
  <h2>Weg zum Kunden <span class="mehr">im Zeitraum</span></h2>
  <div class="trichter mk-trichter">
    <?php foreach ($f as $i => [$wort, $wert, $erkl]): $vor = $i > 0 ? (int) $f[$i - 1][1] : 0; $q = $i > 0 ? MkKennzahlen::quote((int) $wert, $vor) : null; ?>
      <div class="trichter__stufe">
        <span class="trichter__zahl"><?= $zahl((int) $wert) ?></span>
        <span class="trichter__balken" aria-hidden="true"><span style="width:<?= max(1, round($wert / $max * 100)) ?>%"></span></span>
        <span class="trichter__wort"><?= Fmt::h($wort) ?><?php if ($i > 0 && $q !== null && $q <= 100): ?> <i>· <?= Fmt::h($pz($q)) ?> der Stufe davor</i><?php endif; ?><span class="mk-zweit"><?= Fmt::h($erkl) ?></span></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="leise" style="margin:12px 0 0">Die Stufen sind Wege, keine Pflicht-Reihenfolge: Wer über den Kanal-Knopf kommt, springt direkt in den Preisrechner. Jede Stufe zählt je Chat einmal.</p>
</div>

<div class="block" id="quellen">
  <h2>Quellen <span class="mehr">nach Growth Score</span></h2>
  <?php if (!$d['quellen']): ?>
    <p class="leise">Im Zeitraum kam noch niemand über Telegram. Sobald Kampagnen-, Partner- oder Kanal-Links genutzt werden, steht hier jede Quelle mit ihrem Weg bis zum Umsatz.</p>
  <?php else: ?>
  <div class="tabellenrahmen">
    <table class="mk-tab tg-tab">
      <thead><tr><th>Quelle</th><th class="num" title="Starts über den Bot-Link">Starts</th><th class="num" title="neue Bot-Nutzer">Neu</th><th class="num" title="Kanal-Beitritte über den Kanal-Link">Kanal</th><th class="num" title="Preisrechner abgeschlossen">Rechner</th><th class="num" title="Beratung gestartet">Beratung</th><th class="num">Leads</th><th class="num">Kunden</th><th class="num">Umsatz</th><th class="num">Kosten</th><th class="num" title="Growth Score">Score</th></tr></thead>
      <tbody>
        <?php foreach ($d['quellen'] as $q): ?>
          <tr>
            <td class="mk-name"><?php if ($q['art'] === 'kampagne' && $q['id']): ?><a href="<?= Fmt::h(url('kampagnen/' . $q['id'])) ?>"><?= Fmt::h($q['name']) ?></a><?php elseif ($q['art'] === 'partner' && $q['id']): ?><a href="<?= Fmt::h(url('partner/' . $q['id'])) ?>"><?= Fmt::h($q['name']) ?></a><?php else: ?><?= Fmt::h($q['name']) ?><?php endif; ?>
              <br><span class="mk-code"><?= Fmt::h($q['quelle'] !== '' ? $q['quelle'] : '—') ?></span></td>
            <td class="num"><?= $zahl($q['bot_start']) ?></td><td class="num"><?= $zahl($q['bot_neu']) ?></td><td class="num"><?= $zahl($q['kanal_bei']) ?></td>
            <td class="num"><?= $zahl($q['rechner_fertig']) ?></td><td class="num"><?= $zahl($q['beratung']) ?></td><td class="num"><b><?= $zahl($q['lead']) ?></b></td>
            <td class="num"><?= $zahl($q['kunden']) ?></td><td class="num"><?= Fmt::h($geld($q['umsatz'])) ?></td>
            <td class="num"><?= $q['kosten'] > 0 ? Fmt::h($geld($q['kosten'])) : '—' ?></td>
            <td class="num"><b><?= $zahl($q['score']) ?></b><?php if ($q['je10'] !== null): ?><br><span class="mk-code"><?= number_format($q['je10'], 1, ',', '.') ?> je 10 €</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="mk-fein" style="margin:10px 0 0">Growth Score: neuer Nutzer <?= TelegramZahlen::GEWICHTE['bot_neu'] ?> · Kanal-Beitritt <?= TelegramZahlen::GEWICHTE['kanal_bei'] ?> · Preisrechner abgeschlossen <?= TelegramZahlen::GEWICHTE['rechner_fertig'] ?> · Beratung <?= TelegramZahlen::GEWICHTE['beratung'] ?> · Lead <?= TelegramZahlen::GEWICHTE['lead'] ?> · Kunde <?= TelegramZahlen::GEWICHTE['kunden'] ?> · je 100 € Umsatz 1 Punkt. Mit Kosten zusätzlich die Punkte je 10 €. Werbemittel zählen zu ihrer Kampagne.</p>
</div>

<details class="block mk-quelle">
  <summary>Woher diese Zahlen kommen — und was Telegram nicht hergibt</summary>
  <p style="margin:10px 0 0">Gezählt wird ohne Personenbezug, als Tageszahl je Quelle: neue Nutzer, Starts über Links, Kanal-Beitritte und -Austritte über eigene Einladungslinks, und die Stufen im Bot (je Chat einmal). Die Quelle ist die erste, über die jemand kam: Kampagnen-Link (m_…), Partner-Link (p_…), Empfehlung (e_…), Kanal-Knöpfe oder Website. Kunden und Umsatz stammen von Kunden, deren Anfrage aus dem Bot oder der Mini-App kam; Umsatz sind bezahlte Zahlungen ohne Beispieldaten, Kunden die erste bezahlte Zahlung im Zeitraum. Mitgliederstand: einmal täglich bei Telegram abgefragt. Gemessen wird seit dem 01.10.2026 — längere Zeiträume enthalten davor nichts.</p>
  <p style="margin:8px 0 0">Nicht messbar für einen Bot: Aufrufe und Weiterleitungen einzelner Kanalbeiträge (stehen in Telegrams eigener Kanalstatistik) und wer ohne eigenen Link beigetreten ist. „Aktiv“ und „wiederkehrend“ sind je Tag gezählt — über mehrere Tage ist es ein Durchschnitt, keine Zahl verschiedener Menschen.</p>
</details>
