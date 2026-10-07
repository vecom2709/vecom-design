<?php
/** @var array $trichter @var string $nach @var int $tage @var array $woche
 *  Trichter & Wochenziel (26.09.2026): Was wirkt -- nicht, wie viel rausging. */
$akqTeil = 'auswertung';
$namen = ['gefunden' => 'Gefunden', 'geprueft' => 'Geprüft', 'angesprochen' => 'Angesprochen', 'geoeffnet' => 'Analyse geöffnet',
          'antwort' => 'Antwort', 'interesse' => 'Interesse', 'kunde' => 'Kunde'];
$gruppeName = static function (string $g) use ($nach): string {
    if ($nach === 'kanal') { return AkquiseGate::KANAELE[$g] ?? ($g !== '' ? $g : '—'); }
    if ($nach === 'variante') { return $g === 'A' ? 'A — Befunde zuerst' : ($g === 'B' ? 'B — Skizze zuerst' : 'ohne Variante (älter / von Hand)'); }
    return Akquise::branchenName($g !== '' ? $g : null);
};
$summe = AkquiseAuswertung::summe($trichter);
$anteil = static fn(int $a, int $b): string => $b > 0 ? round($a / $b * 100) . ' %' : '—';
$stufen = AkquiseAuswertung::STUFEN;
$prozent = $woche['ziel'] > 0 ? min(100, (int) round($woche['erreicht'] / $woche['ziel'] * 100)) : 0;
$wt = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
?>
<div class="kopf"><div><h1>Auswertung</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Wie viele Betriebe von Stufe zu Stufe kommen — gezählt je Betrieb, nicht je Nachricht.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<style>
  .akq-ziel{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center}
  .akq-ziel .balken{height:14px;border-radius:999px;background:var(--flaeche2);border:1px solid var(--linie);overflow:hidden}
  .akq-ziel .balken i{display:block;height:100%;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .akq-tage{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:12px}
  .akq-tage div{text-align:center;border:1px solid var(--linie);border-radius:10px;padding:6px 0;font-size:var(--fs-klein);color:var(--leise)}
  .akq-tage b{display:block;font-size:17px;color:var(--text)}
  .akq-tage .heute{border-color:var(--linie2)}
  .akq-tr td,.akq-tr th{text-align:right;white-space:nowrap}
  .akq-tr td:first-child,.akq-tr th:first-child{text-align:left;white-space:normal}
  .akq-tr small{display:block;color:var(--leise);font-size:var(--fs-klein)}
  .akq-tr tfoot td{font-weight:650;border-top:1px solid var(--linie2)}
  .akq-wahl{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
  .akq-wahl a{padding:6px 12px;border-radius:999px;border:1px solid var(--linie);color:var(--dim);font-size:var(--fs-klein)}
  .akq-wahl a.an{background:var(--flaeche2);color:var(--text);border-color:var(--linie2)}
</style>

<?php if (!empty($wegDash)): $wd = $wegDash['stufen']; $wdMax = max(1, (int) $wd['verwaltung']);
      $wegName = ['check' => 'Website-Check', 'kurzcheck' => 'Kurz-Check (Analyse)', 'partner' => 'Partner', 'anzeige' => 'Facebook/Instagram-Anzeige',
                  'google' => 'Google-Anzeige', 'whatsapp' => 'WhatsApp', 'telefon' => 'Telefonassistent', 'seite' => 'Website (E-Mail-Einstieg)',
                  'akquise' => 'Akquise (nach Ja)', 'tipp' => 'Website-Tipp', 'vorort' => 'Vor Ort', 'wa' => 'WhatsApp', 'kurz' => 'Kurz-Check (Analyse)',
                  'analisi' => 'Kurz-Check (Analyse)', 'link' => 'Einwilligungs-Link', 'vorschau' => 'Website-Vorschau', 'analyse' => 'Analyse-Seite']; ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 4px">Weg zum Dashboard <span class="akq-klein">(letzte <?= (int) $tage ?> Tage; Verwaltung und Prüfung insgesamt)</span></h2>
  <div class="tabellenrahmen"><table class="akq-tr"><tbody>
    <?php $wv = null; foreach (AkquiseAuswertung::WEG_STUFEN as $wk => $wn): $n = (int) $wd[$wk]; ?>
      <tr><td><?= Fmt::h($wn) ?></td>
        <td style="width:45%"><span style="display:block;height:10px;border-radius:999px;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);width:<?= max(1, (int) round(sqrt($n / $wdMax) * 100)) ?>%"></span></td>
        <td><?= number_format($n, 0, ',', '.') ?><?php if ($wv !== null && in_array($wk, ['offen', 'preis', 'angebot'], true)): ?><small><?= $anteil($n, (int) $wd[$wv]) ?></small><?php endif; ?></td></tr>
    <?php $wv = $wk; endforeach; ?>
  </tbody></table></div>
  <?php if ($wegDash['wege']): ?>
    <h3 style="font-size:14px;margin:14px 0 6px">Je Weg: wo abgesprungen wird</h3>
    <div class="tabellenrahmen"><table class="akq-tr"><thead><tr><th>Weg</th><th>Link bekommen</th><th>Geöffnet</th><th>Preis gesehen</th><th>Angebot</th></tr></thead><tbody>
      <?php foreach ($wegDash['wege'] as $w): ?>
        <tr><td><?= Fmt::h($wegName[$w['quelle']] ?? $w['quelle']) ?></td><td><?= $w['link'] ?></td>
          <td><?= $w['offen'] ?><small><?= $anteil($w['offen'], $w['link']) ?></small></td>
          <td><?= $w['preis'] ?><small><?= $anteil($w['preis'], $w['offen']) ?></small></td>
          <td><?= $w['angebot'] ?><small><?= $anteil($w['angebot'], $w['preis']) ?></small></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <p class="akq-klein" style="margin-top:8px">Der Balken ist wurzelskaliert, damit auch kleine Stufen sichtbar bleiben. Die kleine Zahl ist der Anteil an der Stufe davor. Der schwächste Übergang ist die Stelle, an der ein Weg nachgebessert werden sollte.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Wochenziel: Betriebe ansprechen</h2>
  <div class="akq-ziel">
    <div><div class="balken" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $woche['ziel'] ?>" aria-valuenow="<?= (int) $woche['erreicht'] ?>"><i style="width:<?= $prozent ?>%"></i></div>
      <p class="akq-klein" style="margin:6px 0 0"><?= (int) $woche['erreicht'] ?> von <?= (int) $woche['ziel'] ?> diese Woche (ab Montag, <?= Fmt::h(date('d.m.', strtotime($woche['montag']))) ?>)<?= $woche['erreicht'] >= $woche['ziel'] ? ' — geschafft ✓' : '' ?></p></div>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:flex;gap:6px;align-items:center">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_wochenziel">
      <input type="number" name="ziel" min="1" max="500" value="<?= (int) $woche['ziel'] ?>" style="width:80px" aria-label="Wochenziel">
      <button class="knopf">Ziel setzen</button></form>
  </div>
  <div class="akq-tage">
    <?php $i = 0; foreach ($woche['tage'] as $tag => $n): ?>
      <div class="<?= $tag === date('Y-m-d') ? 'heute' : '' ?>"><b><?= (int) $n ?></b><?= $wt[$i++] ?></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Trichter</h2>
  <div class="akq-wahl">
    <?php foreach (['branche' => 'nach Branche', 'kanal' => 'nach Kanal', 'variante' => 'nach Text A/B'] as $k => $w): ?>
      <a class="<?= $nach === $k ? 'an' : '' ?>" href="<?= Fmt::h(url('akquise/auswertung') . '?' . http_build_query(['nach' => $k, 'tage' => $tage])) ?>"><?= Fmt::h($w) ?></a>
    <?php endforeach; ?>
    <span style="width:12px"></span>
    <?php foreach ([30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $k => $w): ?>
      <a class="<?= $tage === $k ? 'an' : '' ?>" href="<?= Fmt::h(url('akquise/auswertung') . '?' . http_build_query(['nach' => $nach, 'tage' => $k])) ?>"><?= Fmt::h($w) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$trichter): ?>
    <div class="leer">Im Zeitraum noch nichts <?= $nach === 'branche' ? 'gefunden' : 'angesprochen' ?>.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table class="akq-tr"><thead><tr><th><?= $nach === 'kanal' ? 'Kanal' : ($nach === 'variante' ? 'Text' : 'Branche') ?></th>
      <?php foreach ($stufen as $st): if ($nach !== 'branche' && in_array($st, ['gefunden', 'geprueft'], true)) { continue; } ?><th><?= Fmt::h($namen[$st]) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($trichter as $z): $w = $z['werte']; ?>
        <tr><td><?= Fmt::h($gruppeName($z['gruppe'])) ?></td>
          <?php $vorher = null; foreach ($stufen as $st): if ($nach !== 'branche' && in_array($st, ['gefunden', 'geprueft'], true)) { continue; } ?>
            <td><?= (int) $w[$st] ?><?php if ($vorher !== null): ?><small><?= $anteil((int) $w[$st], (int) $w[$vorher]) ?></small><?php endif; ?></td>
          <?php $vorher = $st; endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody><tfoot><tr><td>Zusammen</td>
        <?php $vorher = null; foreach ($stufen as $st): if ($nach !== 'branche' && in_array($st, ['gefunden', 'geprueft'], true)) { continue; } ?>
          <td><?= (int) $summe[$st] ?><?php if ($vorher !== null): ?><small><?= $anteil((int) $summe[$st], (int) $summe[$vorher]) ?></small><?php endif; ?></td>
        <?php $vorher = $st; endforeach; ?></tr></tfoot></table></div>
    <p class="akq-klein" style="margin-top:8px">Die kleine Zahl ist der Anteil an der Stufe davor. „Analyse geöffnet“ zählt, wer die persönliche Seite (QR-Code im Brief) mindestens einmal aufgerufen hat.
      <?= $nach === 'variante' ? ' A/B: Variante B (Skizze zuerst) gibt es nur für Briefe an Branchen mit passendem Bild; verteilt wird hälftig.' : '' ?></p>
  <?php endif; ?>
</div>
