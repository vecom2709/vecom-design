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

  <div class="block"><h2>Aufgaben</h2>
    <?php if (!$aufgaben): ?><div class="leer">Noch keine Aufgaben.</div><?php else: ?>
      <?php $offen = 0; foreach ($aufgaben as $a) { if (!(int) $a['done']) { $offen++; } } ?>
      <p style="color:var(--leise);font-size:12.5px;margin-bottom:10px">
        <?= count($aufgaben) - $offen ?> von <?= count($aufgaben) ?> erledigt</p>
      <table><tbody>
      <?php foreach ($aufgaben as $a): ?>
        <?php $fertig = (int) $a['done'] === 1; ?>
        <tr>
          <td style="width:34px">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="aufgabe_umschalten">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="knopf" style="padding:2px 9px;min-width:0"
                title="<?= $fertig ? 'Wieder offen' : 'Erledigt' ?>"><?= $fertig ? '✓' : '&nbsp;&nbsp;' ?></button></form>
          </td>
          <td style="<?= $fertig ? 'color:var(--leise);text-decoration:line-through' : '' ?>"><?= Fmt::h($a['title']) ?></td>
          <td style="text-align:right;white-space:nowrap;color:var(--leise);font-size:12.5px">
            <?= Fmt::h($a['due_date'] ? Fmt::datum($a['due_date']) : '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?></div>

  <div class="block"><h2>Nachrichten</h2>
    <?php if (!$nachrichten): ?><div class="leer">Noch keine Nachrichten.</div><?php else: ?>
      <?php $ungelesen = 0; foreach ($nachrichten as $n) { if ($n['sender'] === 'kunde' && $n['read_at'] === null) { $ungelesen++; } } ?>
      <?php foreach ($nachrichten as $n): ?>
        <?php $vomKunden = $n['sender'] === 'kunde'; ?>
        <div style="padding:11px 13px;border-radius:11px;margin-bottom:9px;border:1px solid var(--linie);
                    background:<?= $vomKunden ? 'var(--flaeche2)' : 'transparent' ?>">
          <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:5px">
            <b style="font-size:12.5px;color:<?= $vomKunden ? 'var(--cyan)' : 'var(--leise)' ?>">
              <?= $vomKunden ? Fmt::h((string) $p['kunde']) : 'Du' ?>
              <?php if ($vomKunden && $n['read_at'] === null): ?><span class="marke2 warnung" style="margin-left:6px">neu</span><?php endif; ?>
            </b>
            <small style="color:var(--leise)"><?= Fmt::h(Fmt::seit($n['created_at'])) ?></small>
          </div>
          <div style="white-space:pre-wrap;font-size:14px;line-height:1.55"><?= Fmt::h($n['body']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if ($ungelesen > 0): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachrichten_gelesen">
          <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="knopf">Als gelesen markieren</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachricht_senden">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Antworten</label>
        <textarea name="text" rows="4" maxlength="5000" style="min-height:90px"
                  placeholder="Der Kunde bekommt den Text auch per E-Mail."></textarea></div>
      <button class="knopf haupt">Absenden</button></form>
    <?php if ($kundenlink): ?>
      <div class="feld" style="margin-top:14px"><label>Seine Projektseite</label>
        <input readonly onclick="this.select()" value="<?= Fmt::h($kundenlink) ?>"></div>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Dateien</h2>
    <?php if (!$dateien): ?><div class="leer">Noch keine Dateien.</div><?php else: ?>
      <table><tbody>
      <?php foreach ($dateien as $d): ?>
        <tr>
          <td><a href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>"><?= Fmt::h($d['orig_name']) ?></a>
            <br><small style="color:var(--leise)"><?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?> ·
              <?= $d['uploaded_by'] === 'kunde' ? 'vom Kunden' : 'von dir' ?> ·
              <?= Fmt::h(Fmt::seit($d['created_at'])) ?></small></td>
          <td style="text-align:right;width:90px">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_weg">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="knopf">Löschen</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_hoch">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Datei hinterlegen</label><input type="file" name="datei" required></div>
      <button class="knopf">Hochladen</button></form>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Der Kunde sieht diese Dateien auf seiner Projektseite und kann selbst welche schicken.</p>
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

  <div class="block"><h2>Website</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_speichern">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="project_id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Domain</label>
        <input name="domain" placeholder="beispiel.it" value="<?= Fmt::h((string) ($website['domain'] ?? '')) ?>"></div>
      <div class="feld"><label>Adresse</label>
        <input name="url" placeholder="https://beispiel.it" value="<?= Fmt::h((string) ($website['url'] ?? '')) ?>"></div>
      <div class="feld" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="monitoring" id="mon" style="width:auto"
               <?= (int) ($website['monitoring'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="mon" style="margin:0">Regelmäßig überwachen</label></div>
      <button class="knopf">Speichern</button></form>

    <?php if ($website): ?>
      <table style="margin-top:14px"><tbody>
        <tr><td>Zustand</td><td><span class="marke2 <?= Status::ton((string) $website['status']) ?>">
          <?= Fmt::h(Status::label(Status::WEBSITE, (string) $website['status'])) ?></span></td></tr>
        <tr><td>Letzte Antwort</td><td><?= $website['last_status']
            ? (int) $website['last_status'] . ($website['last_ms'] !== null ? ' · ' . (int) $website['last_ms'] . ' ms' : '')
            : 'noch nicht geprüft' ?></td></tr>
        <tr><td>Zuletzt erreichbar</td><td><?= Fmt::h($website['last_ok_at'] ? Fmt::seit($website['last_ok_at']) : '—') ?></td></tr>
        <tr><td>Zertifikat bis</td><td><?= Fmt::h($website['ssl_expires_at'] ? Fmt::datum((string) $website['ssl_expires_at']) : '—') ?></td></tr>
      </tbody></table>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_pruefen">
        <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $website['id'] ?>">
        <button class="knopf">Jetzt prüfen</button></form>

      <?php if ($pruefungen): ?>
        <h3 style="font-size:12.5px;color:var(--leise);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.06em">Letzte Prüfungen</h3>
        <table><tbody>
        <?php foreach ($pruefungen as $k): ?>
          <tr>
            <td style="width:22px"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= (int) $k['ok'] ? 'var(--gruen)' : 'var(--rot)' ?>"></span></td>
            <td style="color:var(--dim);font-size:13px"><?= Fmt::h(Fmt::seit($k['checked_at'])) ?>
              <?php if ($k['error']): ?><br><small style="color:var(--rot)"><?= Fmt::h($k['error']) ?></small><?php endif; ?></td>
            <td style="text-align:right;color:var(--leise);font-size:12.5px;white-space:nowrap">
              <?= $k['http_status'] ? (int) $k['http_status'] : '—' ?><?= $k['response_ms'] !== null ? ' · ' . (int) $k['response_ms'] . ' ms' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    <?php endif; ?>
  </div>

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
