<?php /* Phase 4 (25.09.2026): aus "Betreuung" wird "Vertraege" -- Geld, das
         monatlich kommt, das Hosting und was davon auf Uwe wartet, auf einer
         Seite. Hier wird nur gelesen; die Taten stehen in der Kundenakte. */ ?>
<div class="kopf"><div><h1>Verträge</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Was jeden Monat läuft: Betreuung und Hosting. Website und Betreuung sind zwei getrennte
    Verträge — die Website steht bei den Bestellungen.</p></div>
</div>

<?php $zz = $zahlen ?? []; ?>
<div class="karten">
  <div class="karte"><h3>Monatlich</h3><div class="wert"><?= Fmt::geld((int) ($zz['monatlich'] ?? $monatlich)) ?></div>
    <div class="neben"><?= (int) ($zz['vertraege'] ?? count($liste)) ?> laufende Verträge</div></div>
  <div class="karte"><h3>Automatisch abgebucht</h3><div class="wert"><?= Fmt::geld((int) ($zz['auto_summe'] ?? 0)) ?></div>
    <div class="neben"><?= (int) ($zz['automatisch'] ?? 0) ?> von <?= (int) ($zz['vertraege'] ?? 0) ?> Verträgen — der Rest per Link</div></div>
  <div class="karte"><h3>Überfällig</h3><div class="wert"><?= Fmt::geld((int) ($zz['offen_summe'] ?? 0)) ?></div>
    <div class="neben"><?= (int) ($zz['ueberfaellig'] ?? 0) ?> Rate(n) über dem Zahlungsziel</div></div>
  <div class="karte"><h3>Hosting</h3><div class="wert"><?= (int) ($zz['hosting'] ?? 0) ?></div>
    <div class="neben"><?= (int) ($zz['hosting_arbeit'] ?? 0) > 0 ? (int) $zz['hosting_arbeit'] . ' werden gerade eingerichtet' : 'Domains in Betrieb' ?></div></div>
</div>

<?php if ($warten ?? []): ?>
  <div class="block"><h2>Wartet auf dich</h2><div class="tabellenrahmen"><table><tbody>
    <?php foreach ($warten as $w): ?>
      <tr><td style="width:1%;white-space:nowrap"><span class="marke2 <?= Fmt::h($w['ton']) ?>"><?= Fmt::h($w['marke']) ?></span></td>
        <td style="width:34%"><a href="<?= Fmt::h(url($w['link'])) ?>"><?= Fmt::h(Fmt::name($w['titel'])) ?></a></td>
        <td style="color:var(--dim)"><?= Fmt::h($w['text']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>

<h2 style="margin:22px 0 10px">Monatliche Verträge</h2>
<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch kein Betreuungsvertrag. Anlegen kannst du einen in der Kundenakte.</div></div>
<?php else: ?>
<div class="block"><div class="tabellenrahmen"><table>
  <thead><tr><th>Kunde</th><th>Paket</th><th>Monatlich</th><th>Zahlart</th>
    <th>Läuft seit</th><th>Mindestens bis</th><th>Nächste Rate</th><th>Zustand</th></tr></thead><tbody>
  <?php foreach ($liste as $a): ?>
    <?php
      $ton = ['aktiv' => 'gut', 'gekuendigt' => 'warnung', 'beendet' => '', 'angelegt' => ''][$a['status']] ?? '';
      $wort = ['aktiv' => 'läuft', 'gekuendigt' => 'gekündigt', 'beendet' => 'beendet',
               'angelegt' => 'angelegt'][$a['status']] ?? $a['status'];
    ?>
    <tr>
      <td><a href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'])) ?>"><?= Fmt::h(Fmt::name($a['firma'], $a['kunde'])) ?></a></td>
      <td><?= Fmt::h((string) $a['paket_name']) ?></td>
      <td><?= Fmt::h(Fmt::geld((int) $a['betrag_cents'], (string) $a['currency'])) ?></td>
      <?php /* Phase 2: Was hinterlegt ist, zaehlt mehr als die Zahlart beim Anlegen. */ ?>
      <td><?= (string) ($a['zahlmittel_text'] ?? '') !== ''
            ? '<span title="Automatische Abbuchung seit ' . Fmt::h(Fmt::datum((string) $a['zahlmittel_am'])) . '">↻ ' . Fmt::h((string) $a['zahlmittel_text']) . '</span>'
            : Fmt::h(Abo::ZAHLARTEN[$a['zahlart']] ?? (string) $a['zahlart']) ?></td>
      <td><?= Fmt::h(Fmt::datum((string) $a['beginn'])) ?></td>
      <td><?= Fmt::h(Fmt::datum((string) $a['mindestlaufzeit_bis'])) ?></td>
      <td><?= in_array((string) $a['status'], ['aktiv', 'gekuendigt'], true) && $a['naechste_abrechnung']
            ? Fmt::h(Fmt::datum((string) $a['naechste_abrechnung'])) : '—' ?></td>
      <td><span class="marke2 <?= Fmt::h($ton) ?>"><?= Fmt::h($wort) ?></span>
        <?php if ($a['laeuft_bis']): ?>
          <div style="color:var(--leise);font-size:12px">bis <?= Fmt::h(Fmt::datum((string) $a['laeuft_bis'])) ?></div>
        <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php /* Das Hosting: Wer, welche Domain, wie weit. Einzelheiten und der Knopf
         zum Wiederholen stehen in der Kundenakte. */ ?>
<h2 style="margin:22px 0 10px">Domain &amp; Hosting</h2>
<?php if (!($hosting ?? [])): ?>
  <div class="block"><div class="leer">Noch kein Hosting-Auftrag.</div></div>
<?php else: ?>
<div class="block"><div class="tabellenrahmen"><table>
  <thead><tr><th>Kunde</th><th>Domain</th><th>Domain soll</th><th>E-Mail</th><th>Monatlich</th><th>Stand</th></tr></thead><tbody>
  <?php foreach ($hosting as $h): ?>
    <?php
      $hTon = ['angelegt' => 'gut', 'aktiv' => 'gut', 'in_arbeit' => 'warnung', 'zugestimmt' => '', 'vorgeschlagen' => ''][$h['status']] ?? '';
      $hWort = ['vorgeschlagen' => 'angeboten', 'zugestimmt' => 'wartet auf Zahlung', 'in_arbeit' => 'wird eingerichtet',
                'angelegt' => 'läuft', 'aktiv' => 'läuft'][$h['status']] ?? (string) $h['status'];
    ?>
    <tr>
      <td><a href="<?= Fmt::h(url('kunden/' . (int) $h['customer_id'])) ?>"><?= Fmt::h(Fmt::name($h['wer'])) ?></a></td>
      <td><b><?= Fmt::h((string) $h['domain']) ?></b></td>
      <td><?= Fmt::h(['neu' => 'neu registrieren', 'transfer' => 'umziehen', 'behalten' => 'bleibt beim Anbieter',
                      'offen' => 'noch offen'][(string) ($h['domain_aktion'] ?? 'neu')] ?? (string) $h['domain_aktion']) ?></td>
      <td><?= Fmt::h(['vecom' => 'Postfach bei uns', 'bisher' => 'bleibt, wo sie ist', 'keine' => 'keine',
                      'offen' => 'noch offen'][(string) ($h['mail'] ?? 'vecom')] ?? (string) $h['mail']) ?></td>
      <td><?= $h['inklusive'] ? '<span style="color:var(--leise)">in Betreuung</span>' : Fmt::h(Fmt::geld((int) $h['preis_cents'])) ?></td>
      <td><span class="marke2 <?= Fmt::h($hTon) ?>"><?= Fmt::h($hWort) ?></span>
        <?php if ((int) $h['schritte'] > 0 && !in_array((string) $h['status'], ['vorgeschlagen', 'zugestimmt'], true)): ?>
          <div style="color:var(--leise);font-size:12px"><?= (int) $h['erledigt'] ?>/<?= (int) $h['schritte'] ?> Schritte<?=
            (int) $h['hand'] > 0 ? ' · ' . (int) $h['hand'] . ' von Hand' : '' ?></div>
        <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
