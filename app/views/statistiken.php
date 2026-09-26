<?php
/**
 * Besucher — was die Website zählt (26.09.2026).
 *
 * Nur Summen, nie ein einzelner Besuch: Die Zähldateien kennen keine IP und
 * keinen Keks. Umsatz steht nebenan unter „Zahlen“ -- hier nicht noch einmal.
 * Eine Farbe für alle Balken: Es ist immer EINE Reihe; die Zahl steht in
 * Schriftfarbe daneben, nie in der Balkenfarbe.
 */
$b = $besuche; $d = $demos;
$balken = static function (array $werte, string $leer) {
    if (!$werte) { echo '<p class="leise">' . Fmt::h($leer) . '</p>'; return; }
    $max = max($werte) ?: 1; ?>
    <div class="balkenliste">
      <?php foreach ($werte as $wort => $n): $pz = round($n / $max * 100); ?>
        <div class="bl__zeile" title="<?= Fmt::h($wort . ': ' . $n) ?>">
          <span class="bl__wort"><?= Fmt::h((string) $wort) ?></span>
          <span class="bl__spur"><i style="width:<?= $pz ?>%"></i></span>
          <b class="bl__zahl"><?= (int) $n ?></b>
        </div>
      <?php endforeach; ?>
    </div>
<?php };
$wege = [];
foreach ($d['wege'] as $w) { $wege[$w['wort']] = $w['zahl']; }
$anrufe = $wege['Anruf-Knopf gedrückt'] ?? 0;
$whatsapp = $wege['WhatsApp-Knopf gedrückt'] ?? 0;
?>
<div class="kopf"><div><h1>Besucher</h1>
  <div class="weg">Letzte <?= (int) $b['tage'] ?> Tage · ohne IP, ohne Cookie</div></div></div>

<?php if (!$b['da']): ?>
  <div class="hinweis">Noch keine Zähldatei — sie entsteht mit dem ersten Besuch auf der Website.</div>
<?php endif; ?>

<div class="kacheln">
  <div class="kachel"><span>Heute</span><b><?= (int) $b['heute'] ?></b></div>
  <div class="kachel"><span>Besuche, <?= (int) $b['tage'] ?> Tage</span><b><?= (int) $b['summe'] ?></b></div>
  <div class="kachel"><span>Anruf-Knopf</span><b><?= (int) $anrufe ?></b></div>
  <div class="kachel"><span>WhatsApp-Knopf</span><b><?= (int) $whatsapp ?></b></div>
</div>

<div class="block">
  <h2>Besuche je Woche</h2>
  <?php $wMax = max(array_column($b['wochen'], 'zahl') ?: [0]) ?: 1; ?>
  <div class="saeulen" role="img" aria-label="Besuche je Woche, <?= count($b['wochen']) ?> Wochen">
    <?php /* Dieselbe Säulenform wie unter „Zahlen“ (.saeulen), keine zweite. */
    foreach ($b['wochen'] as $w): ?>
      <div style="height:<?= round($w['zahl'] / $wMax * 100) ?>%" title="Woche ab <?= Fmt::h(date('d.m.', strtotime($w['ab']))) ?>: <?= (int) $w['zahl'] ?> Besuche"></div>
    <?php endforeach; ?>
  </div>
  <div class="saeulen__achse"><span><?= Fmt::h(date('d.m.', strtotime($b['wochen'][0]['ab'] ?? 'now'))) ?></span><span>diese Woche</span></div>
  <details class="klapp spaeter"><summary>Als Tabelle</summary>
    <table><tr><th>Woche ab</th><th style="text-align:right">Besuche</th></tr>
      <?php foreach (array_reverse($b['wochen']) as $w): ?><tr><td><?= Fmt::h(date('d.m.Y', strtotime($w['ab']))) ?></td><td style="text-align:right"><?= (int) $w['zahl'] ?></td></tr><?php endforeach; ?>
    </table></details>
</div>

<div class="zwei">
  <div class="block"><h2>Woher sie kommen</h2>
    <p class="leise">Seit 26.09.2026 zuverlässig — vorher stand hier fast alles unter „direkt“, weil die Herkunft nie ankam.</p>
    <?php $balken($b['quellen'], 'Noch keine Besuche.'); ?></div>
  <div class="block"><h2>Welche Seite</h2>
    <?php $balken($b['seiten'], 'Wird seit dem 26.09.2026 gezählt.'); ?></div>
</div>

<div class="zwei">
  <div class="block"><h2>Kampagnen</h2>
    <p class="leise">Links mit <code>?utm_source=instagram</code> (oder <code>facebook</code>, <code>flyer</code> …) erscheinen hier mit ihrem Namen.</p>
    <?php $balken($b['kampagnen'], 'Noch kein Besuch über einen markierten Link.'); ?></div>
  <div class="block"><h2>Gerät</h2>
    <?php $balken($b['geraete'], 'Noch keine Besuche.'); ?></div>
</div>

<div class="block"><h2>Was sie anklicken</h2>
  <?php $balken(array_filter($wege), 'Noch nichts angeklickt.'); ?>
  <?php if ($d['demos']): ?>
    <h2 style="margin-top:18px">Demos</h2>
    <table><tr><th>Demo</th><th style="text-align:right">geöffnet</th><th style="text-align:right">benutzt</th><th style="text-align:right">„So etwas für mich“</th></tr>
      <?php foreach ($d['demos'] as $x): ?><tr><td><?= Fmt::h($x['name']) ?></td><td style="text-align:right"><?= (int) $x['geoeffnet'] ?></td><td style="text-align:right"><?= (int) $x['benutzt'] ?></td><td style="text-align:right"><?= (int) $x['anfrage'] ?></td></tr><?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($trichter['neu'])): $t = $trichter['neu']; ?>
<div class="block"><h2>Vom Eintragen bis zum Bezahlen</h2>
  <p class="leise">Letzte <?= (int) $trichter['tage'] ?> Tage, über den E-Mail-Einstieg. Mehr dazu unter <a href="<?= Fmt::h(url('bedarf')) ?>">Posteingang → Bedarf</a>.</p>
  <?php $balken(['E-Mail eingetragen' => (int) $t['eingetragen'], 'Link geöffnet' => (int) $t['geoeffnet'], 'Vorhaben beschrieben' => (int) $t['vorhaben'],
                 'Angebot bekommen' => (int) $t['angebot'], 'Bezahlt' => (int) $t['bezahlt']], ''); ?>
</div>
<?php endif; ?>
