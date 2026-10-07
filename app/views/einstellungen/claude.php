<?php
/* CLAUDE-ZUGANG (AI Office Stufe 2, 07.10.2026)
   ==========================================================================
   Drei Dinge, die zusammengehören, weil sie alle „Claude weiß Bescheid“ heißen:
   der Lesezugang (MCP mit Erlaubnis), das Morgenbriefing um 07:30 und das
   Wissen aus PROJEKT.md. Daten: $claude, $wissen, $briefing, $briefingZuletzt,
   $telegramVerbunden. Warum es so gebaut ist: app/src/ClaudeZugang.php. */
$c = $claude ?? ['an' => false, 'adresse' => '', 'verbindungen' => [], 'griffe' => []];
$aktiv = array_values(array_filter($c['verbindungen'], static fn($v) => $v['entzogen_am'] === null && strtotime((string) $v['bis']) > time()));
?>
<div class="block" id="zugang"><h2>Lesezugang für Claude <span class="mehr"><span class="marke2 <?= $c['an'] ? ($aktiv ? 'gut' : '') : 'schlecht' ?>"><?=
    !$c['an'] ? 'aus' : ($aktiv ? 'verbunden' : 'bereit, nicht verbunden') ?></span></span></h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Claude kann deine Verwaltung <b>lesen</b> — Kunden, Projekte, Geld, Umsatz-Chancen, Meldungen, Akquise, Überwachung,
    AI Freigaben, Prüfspur und das Wissen unten. Mit der Erlaubnis <b>Eintragen</b> auch Notizen, Aufgaben, Wiedervorlagen,
    Meldungen als gelesen — und Vorschläge in AI Freigaben, die erst mit deinem Ja rausgehen. Senden, freigeben, löschen
    oder Geld bewegen kann Claude darüber nie. Passwörter, Schlüssel, Kundenlinks und Zahlmittel gibt die Verwaltung nie heraus.
  </p>
  <div class="feld" style="max-width:520px"><label for="claude-adresse">Adresse für Claude</label>
    <input id="claude-adresse" value="<?= Fmt::h((string) $c['adresse']) ?>" readonly onclick="this.select()"></div>
  <ol style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:4px 0 14px 18px;padding:0">
    <li>In Claude unter Einstellungen → Connectors einen eigenen Connector hinzufügen.</li>
    <li>Name „Vecom Verwaltung“, als Adresse die Zeile oben.</li>
    <li>„Verbinden“ — Claude öffnet diese Verwaltung, du siehst, was erlaubt wird, und klickst „Erlauben“.</li>
  </ol>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin:0 0 12px">Eine Verbindung hält <?= (int) ClaudeZugang::TAGE ?> Tage. Danach fragt Claude neu.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="claude_zugang_schalten"><input type="hidden" name="an" value="<?= $c['an'] ? '0' : '1' ?>">
    <button class="knopf"><?= $c['an'] ? 'Claude-Zugang ausschalten (entzieht alle Verbindungen)' : 'Claude-Zugang einschalten' ?></button>
  </form>
</div>

<div class="block" id="verbindungen"><h2>Verbindungen</h2>
  <?php if (!$c['verbindungen']): ?>
    <p style="margin:0;color:var(--dim)">Noch keine. Sobald du in Claude verbindest und hier erlaubst, steht sie da.</p>
  <?php else: ?>
    <table class="schlicht cz-stapel"><thead><tr><th>Programm</th><th>Darf</th><th>Erlaubt</th><th>Gilt bis</th><th>Zuletzt</th><th>Abfragen</th><th></th></tr></thead><tbody>
      <?php foreach ($c['verbindungen'] as $v):
        $gilt = $v['entzogen_am'] === null && strtotime((string) $v['bis']) > time(); ?>
        <tr style="<?= $gilt ? '' : 'opacity:.55' ?>">
          <td><?= Fmt::h((string) ($v['programm'] ?? 'Claude')) ?></td>
          <td><?= str_contains((string) $v['scope'], ClaudeZugang::EINTRAGEN) ? 'Lesen + Eintragen' : 'Lesen' ?></td>
          <td><?= Fmt::h(Fmt::zeit((string) $v['erlaubt_am'])) ?><?= !empty($v['erlaubt_von']) ? ' · ' . Fmt::h((string) $v['erlaubt_von']) : '' ?></td>
          <td><?= $gilt ? '<span class="cz-nur-schmal">Gilt bis </span>' : '' ?><?= $gilt ? Fmt::h(date('d.m.Y', strtotime((string) $v['bis']))) : '<span class="marke2">' . ($v['entzogen_am'] !== null ? 'entzogen' : 'abgelaufen') . '</span>'
                . ($v['entzogen_grund'] ? ' <small style="color:var(--leise)">' . Fmt::h((string) $v['entzogen_grund']) . '</small>' : '') ?></td>
          <td><span class="cz-nur-schmal">Zuletzt: </span><?= $v['zuletzt_am'] ? Fmt::h(Fmt::seit((string) $v['zuletzt_am'])) : '–' ?></td>
          <td><?= (int) $v['aufrufe'] ?><span class="cz-nur-schmal"> Abfragen</span></td>
          <td class="cz-knopf"><?php if ($gilt): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="claude_entziehen"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
              <button class="knopf">Entziehen</button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<?php if ($c['griffe']): ?>
<div class="block" id="griffe"><h2>Was Claude zuletzt gelesen hat</h2>
  <table class="schlicht"><tbody>
    <?php foreach ($c['griffe'] as $g): ?>
      <tr><td style="width:1%;white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::zeit((string) $g['created_at'])) ?></td>
          <td><code><?= Fmt::h((string) $g['werkzeug']) ?></code><?= $g['argumente'] ? ' <small style="color:var(--leise)">' . Fmt::h(mb_substr((string) $g['argumente'], 0, 120)) . '</small>' : '' ?></td>
          <td style="width:1%;white-space:nowrap"><?= (int) $g['ok'] ? '' : '<span class="marke2 warnung">nicht gelesen</span>' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="block" id="briefing"><h2>Morgenbriefing <span class="mehr"><span class="marke2 <?= $telegramVerbunden ? 'gut' : 'warnung' ?>"><?= $telegramVerbunden ? '07:30 an dein Telegram' : 'Telegram nicht verbunden' ?></span></span></h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Jeden Morgen um 07:30: was auf dich wartet, Geld, Technik, Akquise und Termine.
    <?= $briefingZuletzt !== '' ? 'Zuletzt geschickt am ' . Fmt::h(date('d.m.Y', strtotime($briefingZuletzt))) . '.' : 'Noch nie geschickt.' ?>
    <?php if (!$telegramVerbunden): ?>Verbinden unter <a href="<?= Fmt::h(url('einstellungen?b=telegram')) ?>">Einstellungen → Telegram</a> — bis dahin geht es über den Ersatzweg.<?php endif; ?>
  </p>
  <?php if ($briefing !== ''): ?>
    <p style="color:var(--leise);font-size:var(--fs-klein);margin:0 0 6px">So sähe es jetzt aus:</p>
    <div class="mb-vorschau"><?= nl2br($briefing) /* in Morgenbriefing::text() schon maskiert, nur <b> ist echtes HTML */ ?></div>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"><?= Csrf::feld() ?>
    <input type="hidden" name="tat" value="morgenbriefing_jetzt"><button class="knopf">Jetzt an mein Telegram schicken</button></form>
</div>

<div class="block" id="wissen"><h2>Wissen für Claude</h2>
  <?php if ($wissen === null): ?>
    <p style="margin:0;color:var(--dim)">Noch nicht gebaut — es entsteht beim nächsten Deploy aus PROJEKT.md, CLAUDE.md, VECOM-STANDARD.md und AKQUISE.md.</p>
  <?php else: ?>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:10px">
      <?= (int) $wissen['kapitel'] ?> Kapitel, Stand <?= Fmt::h(Fmt::zeit(date('Y-m-d H:i:s', strtotime((string) $wissen['stand'])))) ?>.
      Claude sucht darin, wenn die Frage „warum ist das so?“ lautet, und bekommt das Kapitel im Wortlaut.
    </p>
    <table class="schlicht cz-stapel"><tbody>
      <?php foreach ($wissen['quellen'] as $q): ?>
        <tr><td style="width:30%"><code><?= Fmt::h((string) $q['datei']) ?></code></td><td><?= Fmt::h((string) $q['wozu']) ?></td>
            <td style="width:1%;white-space:nowrap;color:var(--leise)"><?= (int) $q['kapitel'] ?> Kapitel</td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<style>
  .cz-nur-schmal{display:none}
  .cz-knopf{text-align:right}
  /* Auf dem Telefon (gemessen 390 px): Tabellen als Stapel, sonst verschwand „Entziehen“ rechts aus dem Bild. */
  @media (max-width:640px){
    .cz-stapel thead{display:none}
    .cz-stapel tr{display:block;padding:10px 0;border-top:1px solid var(--linie,rgba(255,255,255,.08))}
    .cz-stapel td{display:block;border:0;padding:2px 0;width:auto!important}
    .cz-stapel .cz-knopf{text-align:left;padding-top:8px}
    .cz-nur-schmal{display:inline;color:var(--leise)}
  }
  .mb-vorschau{white-space:normal;font-size:var(--fs-klein);line-height:1.6;padding:12px 14px;border:1px solid var(--linie,rgba(255,255,255,.08));border-radius:10px;max-width:640px}
</style>
