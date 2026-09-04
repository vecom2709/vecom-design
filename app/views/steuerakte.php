<div class="kopf"><div><h1>Fürs Finanzamt</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Ein Jahr, eine Datei: jeder Beleg als PDF, jede Eingangsrechnung, die Liste der
    Zahlungseingänge und die Tabellen dazu. Das Paket entsteht jede Nacht von selbst — du musst
    es nur herunterladen. Was es <b>nicht</b> ist, steht unten.</p></div>
</div>

<?php if ($fristen): ?>
<div class="block">
  <h2>Was ansteht</h2>
  <div class="tabellenrahmen"><table><tbody>
  <?php foreach (array_slice($fristen, 0, 4) as $f): ?>
    <tr>
      <td style="width:120px;white-space:nowrap"><?= Fmt::h(Fmt::datum($f['datum'])) ?>
        <br><small style="color:var(--leise)">in <?= (int) $f['tage'] ?> Tagen</small></td>
      <td><b><?= Fmt::h($f['titel']) ?></b>
        <div style="color:var(--leise);font-size:12.5px;margin-top:3px"><?= Fmt::h($f['text']) ?></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!$jahre): ?>
  <div class="block"><div class="leer">Noch keine Belege. Sobald der erste ausgestellt ist, steht hier ein Jahr.</div></div>
<?php else: ?>
  <?php foreach ($jahre as $j): ?>
    <?php
      $z  = $uebersicht[$j] ?? null;
      $a  = $ausgaben[$j] ?? ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0];
      $g  = $grenzen[$j]  ?? ['summe' => 0, 'waehrung' => 'EUR', 'anteil' => 0.0, 'warnung' => null];
      $ar = $archiv[$j]   ?? ['stand' => null, 'bytes' => 0];
      $w  = (string) ($z['waehrung'] ?? 'EUR');
    ?>
    <div class="block">
      <h2><?= (int) $j ?><span class="mehr"><?= (int) ($z['anzahl'] ?? 0) ?> Belege ·
        <?= Fmt::h(Fmt::geld((int) ($z['brutto'] ?? 0), $w)) ?></span></h2>

      <?php if ($g['warnung'] !== null): ?>
        <div class="hinweis schlecht" style="margin-bottom:12px"><?= Fmt::h((string) $g['warnung']) ?></div>
      <?php endif; ?>

      <div class="tabellenrahmen"><table><tbody>
        <tr><td style="width:38%">Eingegangen <?= (int) $j ?></td>
          <td><b><?= Fmt::h(Fmt::geld((int) $g['summe'], (string) $g['waehrung'])) ?></b>
            <div style="color:var(--leise);font-size:12.5px;margin-top:4px">Das ist die Zahl, nach der
              in Italien besteuert wird — Geld, das angekommen ist, nicht Rechnungen, die geschrieben
              wurden. Rechnung im Dezember, Zahlung im Januar: zählt zum nächsten Jahr.</div></td></tr>
        <tr><td>Ausgestellt <?= (int) $j ?></td>
          <td><?= Fmt::h(Fmt::geld((int) ($z['brutto'] ?? 0), $w)) ?>
            <?php if ((int) ($z['steuer'] ?? 0) > 0): ?>
              <small style="color:var(--leise)"> · davon Steuer <?= Fmt::h(Fmt::geld((int) $z['steuer'], $w)) ?></small>
            <?php endif; ?></td></tr>
        <?php if ((int) $a['anzahl'] > 0): ?>
        <tr><td>Ausgaben</td>
          <td><?= (int) $a['anzahl'] ?> Belege · <?= Fmt::h(Fmt::geld((int) $a['brutto'])) ?>
            <?php if ((int) $a['rc_netto'] > 0): ?>
              <div style="color:var(--leise);font-size:12.5px;margin-top:4px">Davon
                <?= Fmt::h(Fmt::geld((int) $a['rc_netto'])) ?> aus dem Ausland — Reverse Charge,
                rechnerisch <?= Fmt::h(Fmt::geld((int) $a['rc_iva'])) ?> IVA.</div>
            <?php endif; ?></td></tr>
        <?php endif; ?>
        <tr><td>Nummernreihe</td><td>
          <?php if (!empty($z['luecken'])): ?>
            <span class="marke2 schlecht">Lücken</span>
            <div style="color:var(--rot);font-size:12.5px;margin-top:5px">
              Es fehlen: <?= Fmt::h(implode(', ', $z['luecken'])) ?>.
              Eine Nummerierung muss im Jahr lückenlos sein — bitte klären, bevor das rausgeht.</div>
          <?php else: ?>
            <span class="marke2 gut">lückenlos</span>
          <?php endif; ?></td></tr>
        <?php if ((int) ($z['offen'] ?? 0) > 0): ?>
          <tr><td>Nicht bezahlt</td><td><span class="marke2 warnung"><?= (int) $z['offen'] ?></span></td></tr>
        <?php endif; ?>
        <?php $fo = $z['forderungen'] ?? ['anzahl' => 0, 'summe' => 0]; ?>
        <?php if ((int) $fo['anzahl'] > 0): ?>
          <tr><td>Offene Forderungen</td><td>
            <span class="marke2 warnung"><?= (int) $fo['anzahl'] ?></span>
            <?= Fmt::h(Fmt::geld((int) $fo['summe'])) ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:5px">
              Bis zum 31.12. fällig und nicht bezahlt. Zählen steuerlich nicht zu
              <?= (int) $j ?> — besteuert wird, was eingegangen ist. Sie stehen hier,
              damit eine Zahlung im Januar dem richtigen Jahr zugeordnet wird.</div></td></tr>
        <?php endif; ?>
        <?php if ((int) ($z['entwuerfe'] ?? 0) > 0): ?>
          <tr><td>Entwürfe</td><td><?= (int) $z['entwuerfe'] ?>
            <small style="color:var(--leise)">— ohne Nummer, nicht im Paket: das sind keine Belege</small></td></tr>
        <?php endif; ?>
        <tr><td>Paket</td><td>
          <?php if ($ar['stand'] !== null): ?>
            <span class="marke2 gut">liegt bereit</span>
            <small style="color:var(--leise)"> · <?= Fmt::h(Fmt::seit((string) $ar['stand'])) ?>
              · <?= Fmt::h(Fmt::bytes((int) $ar['bytes'])) ?></small>
          <?php else: ?>
            <span class="marke2 warnung">wird beim ersten Abruf gebaut</span>
          <?php endif; ?></td></tr>
      </tbody></table></div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
        <a class="knopf haupt" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/paket')) ?>">Alles als ZIP</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/einnahmen')) ?>">Zahlungseingänge</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/abgrenzung')) ?>">Jahreswechsel</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/forderungen')) ?>"
           title="Was am 31.12. noch aussteht — steuerlich nicht zu zählen, aber der Commercialista fragt danach">Offene Forderungen</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/verzeichnis')) ?>">Belegverzeichnis</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/ausgaben')) ?>">Ausgaben</a>
        <?php if ((int) $a['rc_netto'] > 0): ?>
          <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/reversecharge')) ?>">Reverse Charge</a>
        <?php endif; ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline;margin:0">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="steuerakte_bauen">
          <input type="hidden" name="zurueck" value="steuerakte">
          <input type="hidden" name="jahr" value="<?= (int) $j ?>">
          <button class="knopf stumm">Jetzt neu bauen</button></form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="block">
  <h2>Was das Paket ist — und was nicht</h2>
  <p style="color:var(--dim);font-size:13.5px;margin-bottom:10px">
    Es ist die vollständige Grundlage für deinen Commercialista: alle Belege, alle Zahlungseingänge,
    alle Eingangsrechnungen, mit Prüfsummen und einer Liste dessen, was fehlt. Drei Dinge kann es
    aber nicht sein, und das ist kein Versäumnis, sondern gesetzlich so gebaut:</p>
  <div class="tabellenrahmen"><table><tbody>
    <tr><td style="width:34%">Keine <i>conservazione a norma</i></td>
      <td>Die verlangt Zeitstempel und Signatur auf dem Archivpaket, einen Index nach UNI 11386,
        Pflichtmetadaten, einen benannten Verantwortlichen und ein Handbuch. Die Agenzia sagt es
        selbst: das ist nicht „einfach auf dem Rechner speichern“. Dafür gibt es ihren
        <b>kostenlosen Dienst im Portal „Fatture e Corrispettivi“</b> — er muss einmal eingeschaltet
        und der Accordo di servizio angenommen werden, sonst passiert nichts. Zweiter, getrennter
        Klick: „Consultazione e acquisizione“, sonst behält die Agenzia nur die Kopfdaten.</td></tr>
    <tr><td>Keine elektronische Rechnung</td>
      <td>Eine Rechnung gilt erst als ausgestellt, wenn sie über das SdI gelaufen ist. Das setzt
        eine Partita IVA voraus und läuft über das Portal, eine PEC oder deinen Commercialista.
        Ein PDF von hier ist steuerlich keine Rechnung.</td></tr>
    <tr><td>Kein Weg zum Finanzamt</td>
      <td>Es gibt keinen Kanal, über den man Kontoauszüge, Verträge oder Quittungen bei der Agenzia
        hochlädt. Belege werden aufbewahrt und <b>auf Anforderung vorgelegt</b> — mehr verlangt
        niemand. Nur Rechnungen haben mit dem SdI einen eigenen Weg.</td></tr>
    <tr><td>Wie lange aufheben</td>
      <td>Zehn Jahre ab der letzten Eintragung (Art. 2220 Codice civile), länger solange eine
        Prüfung läuft (Art. 22 DPR 600/1973). Zwölf Jahre sind die sichere Antwort.</td></tr>
  </tbody></table></div>
</div>
