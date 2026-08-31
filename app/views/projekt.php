<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('projekte')) ?>">Projekte</a></div>
<h1><?= Fmt::h($p['name']) ?></h1></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Ablauf</h2>
    <div class="balken" style="height:8px;margin-bottom:14px"><i style="width:<?= (int) $p['progress'] ?>%"></i></div>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_status">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <select name="status"><?php foreach (Status::PROJEKT as $w => $t): ?>
        <option value="<?= $w ?>" <?= $p['status'] === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?></select>
      <button class="knopf haupt">Status setzen</button></form>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Der Projektstatus zieht die Bestellung sinngemäß mit.
    Der technische Website-Status bleibt davon unberührt — er wird nur vom Monitoring gesetzt.</p></div>

  <div class="block"><h2>Fragebogen</h2>
    <?php if (!$fragebogen): ?>
      <div class="leer">Zu diesem Projekt gibt es keinen Fragebogen.</div>
    <?php else: ?>
      <?php
        $fbDaten = $fragebogen['data'] ? (json_decode((string) $fragebogen['data'], true) ?: []) : [];
        $fbToken = (string) ($fragebogen['token'] ?? '');
        $fbFertig = $fragebogen['status'] === 'abgeschlossen';
      ?>
      <table style="margin-bottom:14px"><tbody>
        <tr><td style="width:38%">Stand</td><td><span class="marke2 <?= $fbFertig ? 'gut' : '' ?>">
          <?= $fbFertig ? 'Abgeschlossen' : 'Offen' ?></span></td></tr>
        <tr><td>Eingeladen</td><td><?= Fmt::h($fragebogen['eingeladen_am'] ? Fmt::datum($fragebogen['eingeladen_am']) : 'noch nicht') ?></td></tr>
        <tr><td>Erinnert</td><td><?= Fmt::h($fragebogen['erinnert_am'] ? Fmt::datum($fragebogen['erinnert_am']) : '—') ?></td></tr>
        <?php if ($fbFertig): ?>
          <tr><td>Abgeschickt</td><td><?= Fmt::h(Fmt::datum($fragebogen['submitted_at'])) ?></td></tr>
        <?php endif; ?>
      </tbody></table>

      <?php if ($fbToken !== ''): ?>
        <div class="feld"><label>Zugangslink für den Kunden</label>
          <input readonly onclick="this.select()" value="<?= Fmt::h(Onboarding::link($fbToken)) ?>"></div>
      <?php endif; ?>

      <?php if (!$fbFertig): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_einladen">
            <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="knopf haupt"><?= $fragebogen['eingeladen_am'] ? 'Noch einmal verschicken' : 'Fragebogen verschicken' ?></button></form>
          <?php if ($fbToken === ''): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_link">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="knopf">Nur Link erzeugen</button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($fbDaten): ?>
        <?php foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt): ?>
          <?php $hat = array_filter($inhalt['felder'], static fn($_, $n) => trim((string) ($fbDaten[$n] ?? '')) !== '', ARRAY_FILTER_USE_BOTH); ?>
          <?php if ($hat): ?>
            <h3 style="font-size:13px;color:var(--leise);margin:18px 0 6px;text-transform:uppercase;letter-spacing:.06em"><?= Fmt::h(Texte::h($inhalt, 'de')) ?></h3>
            <table><tbody>
            <?php foreach ($hat as $name => $feld): ?>
              <tr><td style="width:38%"><?= Fmt::h(Texte::h($feld, 'de')) ?></td>
                  <td style="white-space:pre-wrap"><?= Fmt::h((string) $fbDaten[$name]) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php elseif ($fragebogen['eingeladen_am']): ?>
        <div class="leer">Der Kunde hat noch nichts eingetragen.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?><br><small><?= Fmt::h($a['actor']) ?></small></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . $p['customer_id'])) ?>"><?= Fmt::h($p['kunde']) ?></a></td></tr>
    <tr><td>Bestellung</td><td><?= $p['order_id'] ? '<a href="' . Fmt::h(url('bestellungen/' . $p['order_id'])) . '">' . Fmt::h((string) $p['order_no']) . '</a>' : '—' ?></td></tr>
    <tr><td>Projektstatus</td><td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td></tr>
    <tr><td>Website-Status</td><td><span class="marke2 <?= Status::ton((string) ($website['status'] ?? '')) ?>"><?= Fmt::h($website ? Status::label(Status::WEBSITE, $website['status']) : 'keine hinterlegt') ?></span></td></tr>
    <tr><td>Fragebogen</td><td><?= Fmt::h($fragebogen ? ucfirst((string) $fragebogen['status']) : '—') ?></td></tr>
    <tr><td>Start</td><td><?= Fmt::h(Fmt::datum($p['start_date'])) ?></td></tr>
  </tbody></table></div>
  <div class="block"><h2>Eckdaten</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_felder">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Deadline</label><input type="date" name="deadline" value="<?= Fmt::h($p['deadline'] ?? '') ?>"></div>
      <div class="feld"><label>Priorität</label><select name="priority">
        <?php foreach (['niedrig','normal','hoch'] as $pr): ?><option <?= $p['priority'] === $pr ? 'selected' : '' ?>><?= $pr ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label>Vorschau-Link</label><input name="preview_url" value="<?= Fmt::h($p['preview_url'] ?? '') ?>"></div>
      <button class="knopf">Speichern</button></form></div>

  <div class="block"><h2>E-Mails</h2>
    <?php if (!$mails): ?><div class="leer">Noch keine verschickt.</div><?php else: ?>
      <table><tbody>
      <?php foreach ($mails as $m): ?>
        <tr><td><?= Fmt::h($m['betreff']) ?><br><small style="color:var(--leise)"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></small></td>
            <td style="text-align:right"><span class="marke2 <?= $m['status'] === 'gesendet' ? 'gut' : 'schlecht' ?>"><?= Fmt::h($m['status']) ?></span>
            <?php if ($m['fehler']): ?><br><small style="color:var(--rot)"><?= Fmt::h(mb_substr((string) $m['fehler'], 0, 120)) ?></small><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?></div>
</div></div>
