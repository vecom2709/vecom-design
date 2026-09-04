<div class="kopf"><div><h1>Fragebögen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Nach der Anzahlung geht der Fragebogen von allein raus.
  Wer nach <?= (int) Onboarding::ERINNERUNG_NACH_TAGEN ?> Tagen nicht geantwortet hat, bekommt einmal eine Erinnerung.</p></div>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_erinnern">
    <input type="hidden" name="zurueck" value="onboarding">
    <button class="knopf">Fällige Erinnerungen verschicken</button></form>
</div>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Fragebögen. Der erste entsteht mit der ersten bezahlten Bestellung.</div>
  <?php else: ?>
    <table><thead><tr><th>Kunde</th><th>Projekt</th><th>Stand</th><th>Eingeladen</th><th>Erinnert</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $q): ?>
      <?php $fertig = $q['status'] === 'abgeschlossen'; ?>
      <tr>
        <td><a href="<?= Fmt::h(url('kunden/' . (int) $q['customer_id'])) ?>"><?= Fmt::h($q['firma'] ?: $q['kunde']) ?></a>
            <br><small style="color:var(--leise)"><?= Fmt::h($q['kunde_email']) ?></small></td>
        <td><a href="<?= Fmt::h(url('projekte/' . (int) $q['project_id'])) ?>"><?= Fmt::h($q['projekt']) ?></a>
            <br><small style="color:var(--leise)"><?= Fmt::h(Status::label(Status::PROJEKT, (string) $q['projekt_status'])) ?></small></td>
        <td><span class="marke2 <?= $fertig ? 'gut' : ($q['eingeladen_am'] ? 'warnung' : '') ?>">
            <?= $fertig ? 'Abgeschlossen' : ($q['eingeladen_am'] ? 'Wartet auf Antwort' : 'Noch nicht verschickt') ?></span></td>
        <td><?= Fmt::h($q['eingeladen_am'] ? Fmt::datum($q['eingeladen_am']) : '—') ?></td>
        <td><?= Fmt::h($q['erinnert_am'] ? Fmt::datum($q['erinnert_am']) : '—') ?></td>
        <td style="text-align:right"><?php if (!$fertig): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_einladen">
            <input type="hidden" name="zurueck" value="onboarding">
            <input type="hidden" name="id" value="<?= (int) $q['project_id'] ?>">
            <button class="knopf"><?= $q['eingeladen_am'] ? 'Noch einmal' : 'Verschicken' ?></button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<?php /* ======================================================================
     DIE LISTE MUSS SICH AUCH LEEREN LASSEN

     Sie waechst mit jeder Mail und mit jedem Fehlversuch. Nach einer kaputten
     Woche stehen dort dreissig Zeilen "Kein Brevo-Schluessel hinterlegt", und
     was wirklich rausging, sucht man dazwischen.

     Ein Fehlversuch ist folgenlos: Die Verwaltung zaehlt nur GESENDETE Mails,
     wenn sie wissen will, ob der Kunde etwas schon hat. Deshalb geht der weg
     wie ein Krümel vom Tisch. Eine gesendete Zeile dagegen traegt Wissen --
     an ihr haengt die Mahnstufe und die Frage, ob der Zahlungslink drausen
     ist. Loeschen darf man sie trotzdem, aber nicht versehentlich.
     ================================================================== */ ?>
<?php $fehlversuche = 0; foreach ($mails as $m) { if ($m['status'] !== 'gesendet') { $fehlversuche++; } } ?>
<div class="block"><h2>Verschickte E-Mails<?php if ($fehlversuche): ?>
    <span class="mehr"><?= $fehlversuche ?> Fehlversuch<?= $fehlversuche === 1 ? '' : 'e' ?></span><?php endif; ?>
    <?php if ($fehlversuche): ?>
      <span style="float:right">
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
              data-frage="Alle <?= (int) $fehlversuche ?> Fehlversuche aus der Liste nehmen? Verschickt wurde dabei nichts — es geht nur um die Einträge."
              data-ja="Ja, aufräumen">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="mails_fehler_loeschen">
          <input type="hidden" name="zurueck" value="onboarding">
          <button class="knopf">Fehlversuche aufräumen</button></form></span>
    <?php endif; ?></h2>
  <?php if (!$mails): ?>
    <div class="leer">Noch nichts verschickt.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table><thead><tr><th>Wann</th><th>Anlass</th><th>An</th><th>Betreff</th><th>Stand</th><th></th></tr></thead><tbody>
    <?php foreach ($mails as $m): ?>
      <?php $raus = (string) $m['status'] === 'gesendet'; ?>
      <tr><td><?= Fmt::h(Fmt::seit($m['created_at'])) ?></td>
          <td><?= Fmt::h($m['anlass']) ?></td>
          <td><?= Fmt::h($m['empfaenger']) ?></td>
          <td><?= Fmt::h($m['betreff']) ?></td>
          <td><span class="marke2 <?= $raus ? 'gut' : 'schlecht' ?>"><?= Fmt::h($m['status']) ?></span>
          <?php if ($m['fehler']): ?><br><small style="color:var(--rot)"><?= Fmt::h(mb_substr((string) $m['fehler'], 0, 140)) ?></small><?php endif; ?></td>
          <td style="text-align:right">
            <form method="post" action="<?= Fmt::h(url('')) ?>"
              <?php if ($raus): ?>data-frage="Diese Zeile löschen? Die Verwaltung weiß danach nicht mehr, dass „<?= Fmt::h(mb_substr((string) $m['betreff'], 0, 60)) ?>&#8220; draußen war — der Schritt kann wieder als offen auftauchen."
                  data-ja="Ja, löschen"<?php endif; ?>>
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="mail_loeschen">
              <input type="hidden" name="zurueck" value="onboarding">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button class="knopf" title="Diesen Eintrag löschen">Löschen</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
