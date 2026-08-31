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

<div class="block"><h2>Verschickte E-Mails</h2>
  <?php if (!$mails): ?>
    <div class="leer">Noch nichts verschickt.</div>
  <?php else: ?>
    <table><thead><tr><th>Wann</th><th>Anlass</th><th>An</th><th>Betreff</th><th>Stand</th></tr></thead><tbody>
    <?php foreach ($mails as $m): ?>
      <tr><td><?= Fmt::h(Fmt::seit($m['created_at'])) ?></td>
          <td><?= Fmt::h($m['anlass']) ?></td>
          <td><?= Fmt::h($m['empfaenger']) ?></td>
          <td><?= Fmt::h($m['betreff']) ?></td>
          <td><span class="marke2 <?= $m['status'] === 'gesendet' ? 'gut' : 'schlecht' ?>"><?= Fmt::h($m['status']) ?></span>
          <?php if ($m['fehler']): ?><br><small style="color:var(--rot)"><?= Fmt::h(mb_substr((string) $m['fehler'], 0, 140)) ?></small><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
