<div class="kopf"><div><h1>Website-Monitoring</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    <?php if ($lauf): ?>
      Letzter Lauf: <?= Fmt::h(Fmt::seit($lauf)) ?> (<?= Fmt::h(Fmt::zeit($lauf)) ?>)
      <?php if ($bilanz && isset($bilanz['websites']['geprueft'])): ?>
        <?php $wieViele = (int) $bilanz['websites']['geprueft']; ?>
        · <?= $wieViele ?> <?= $wieViele === 1 ? 'Seite' : 'Seiten' ?> geprüft, <?= (int) ($bilanz['websites']['gestoert'] ?? 0) ?> gestört
      <?php endif; ?>
    <?php else: ?>
      Noch kein Lauf. Trage unten die Adresse als Cronjob im KAS ein.
    <?php endif; ?>
  </p></div>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="cron_jetzt">
    <input type="hidden" name="zurueck" value="monitoring">
    <button class="knopf haupt">Jetzt prüfen</button></form>
</div>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Website hinterlegt. Trag sie im jeweiligen Projekt ein.</div>
  <?php else: ?>
    <table><thead><tr><th>Domain</th><th>Kunde</th><th>Zustand</th><th>Letzte Antwort</th>
      <th>Verfügbar (30 T.)</th><th>Zertifikat</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $w): ?>
      <?php
        $quote = (int) $w['pruefungen'] > 0 ? round((int) $w['gute'] / (int) $w['pruefungen'] * 100, 1) : null;
        $sslTage = $w['ssl_expires_at'] ? (int) floor((strtotime((string) $w['ssl_expires_at']) - time()) / 86400) : null;
      ?>
      <tr>
        <td><a href="<?= Fmt::h((string) $w['url']) ?>" target="_blank" rel="noopener"><?= Fmt::h($w['domain']) ?></a>
          <?php if (!(int) $w['monitoring']): ?><br><small style="color:var(--leise)">Überwachung aus</small><?php endif; ?>
          <?php if ($w['projekt']): ?><br><small style="color:var(--leise)"><a href="<?= Fmt::h(url('projekte/' . (int) $w['project_id'])) ?>"><?= Fmt::h($w['projekt']) ?></a></small><?php endif; ?></td>
        <td><a href="<?= Fmt::h(url('kunden/' . (int) $w['customer_id'])) ?>"><?= Fmt::h($w['firma'] ?: $w['kunde']) ?></a></td>
        <td><span class="marke2 <?= Status::ton((string) $w['status']) ?>"><?= Fmt::h(Status::label(Status::WEBSITE, (string) $w['status'])) ?></span></td>
        <td><?php if ($w['last_status']): ?>
              <?= (int) $w['last_status'] ?>
              <?php if ($w['last_ms'] !== null): ?>
                · <span style="color:<?= (int) $w['last_ms'] > 3000 ? 'var(--gelb)' : 'var(--dim)' ?>"><?= (int) $w['last_ms'] ?> ms</span>
              <?php endif; ?>
              <br><small style="color:var(--leise)"><?= Fmt::h($w['last_ok_at'] ? Fmt::seit($w['last_ok_at']) : '—') ?></small>
            <?php else: ?><span style="color:var(--leise)">noch nicht geprüft</span><?php endif; ?></td>
        <td><?= $quote === null ? '<span style="color:var(--leise)">—</span>'
              : '<b style="color:' . ($quote >= 99.5 ? 'var(--gruen)' : ($quote >= 98 ? 'var(--gelb)' : 'var(--rot)')) . '">'
                . number_format($quote, 1, ',', '.') . ' %</b><br><small style="color:var(--leise)">' . (int) $w['pruefungen'] . ' Prüfungen</small>' ?></td>
        <td><?php if ($sslTage === null): ?><span style="color:var(--leise)">—</span>
            <?php else: ?><span style="color:<?= $sslTage <= 14 ? 'var(--gelb)' : 'var(--dim)' ?>">noch <?= max(0, $sslTage) ?> Tage</span>
              <br><small style="color:var(--leise)"><?= Fmt::h(Fmt::datum((string) $w['ssl_expires_at'])) ?></small><?php endif; ?></td>
        <td style="text-align:right">
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_pruefen">
            <input type="hidden" name="zurueck" value="monitoring">
            <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <button class="knopf">Prüfen</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<div class="zwei">
  <div class="block"><h2>Letzte Prüfungen</h2>
    <?php if (!$letzte): ?><div class="leer">Noch nichts geprüft.</div><?php else: ?>
      <table><tbody>
      <?php foreach ($letzte as $k): ?>
        <tr>
          <td style="width:26px"><span class="punkt" style="background:<?= (int) $k['ok'] ? 'var(--gruen)' : 'var(--rot)' ?>;display:inline-block;width:8px;height:8px;border-radius:50%"></span></td>
          <td><?= Fmt::h($k['domain']) ?>
            <?php if ($k['error']): ?><br><small style="color:var(--rot)"><?= Fmt::h($k['error']) ?></small><?php endif; ?></td>
          <td style="text-align:right;white-space:nowrap;color:var(--dim)">
            <?= $k['http_status'] ? (int) $k['http_status'] : '—' ?><?= $k['response_ms'] !== null ? ' · ' . (int) $k['response_ms'] . ' ms' : '' ?>
            <br><small style="color:var(--leise)"><?= Fmt::h(Fmt::seit($k['checked_at'])) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?></div>

  <div class="block"><h2>Cronjob im KAS</h2>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
      Der Webspace hat keinen eigenen Dienst, der von allein läuft. Der Anstoß kommt vom
      KAS: Er ruft alle zehn Minuten diese Adresse auf. Ohne den Schlüssel darin passiert nichts.
    </p>
    <div class="feld"><label>Diese Adresse im KAS eintragen</label>
      <input readonly onclick="this.select()" value="<?= Fmt::h((string) $adresse) ?>"></div>
    <ol style="color:var(--dim);font-size:13.5px;line-height:1.9;padding-left:20px;margin:0">
      <li>Im KAS links auf <b>Tools</b> → <b>Cronjobs</b></li>
      <li><b>Neuen Cronjob anlegen</b></li>
      <li>Bei <b>URL</b> die Adresse oben einfügen</li>
      <li>Intervall: <b>alle 10 Minuten</b></li>
      <li>Speichern — fertig</li>
    </ol>
    <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
      Der Schlüssel gehört nicht in eine E-Mail und nicht in einen Chat. Wer ihn hat, kann den
      Lauf anstoßen — mehr nicht, aber das reicht als Grund, ihn für sich zu behalten.
    </p>
    <?php if ($bilanz): ?>
      <p style="color:var(--leise);font-size:12px;margin-top:14px;word-break:break-all">
        Letzte Bilanz: <?= Fmt::h(json_encode($bilanz, JSON_UNESCAPED_UNICODE)) ?></p>
    <?php endif; ?>
  </div>
</div>
