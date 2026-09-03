<?php
/* Empfehlungen: wer wen gebracht hat, und was daraus geworden ist.

   Oben steht die Arbeitsliste — genannte Namen, die noch niemandem gehoeren.
   Das ist bewusst die erste Zeile der Seite: Eine unzugeordnete Empfehlung ist
   ein Kunde, der auf seinen Rabatt wartet und nichts davon weiss. */
$stufen = [
    'offen'     => ['', 'wartet auf Zahlung'],
    'verdient'  => ['gut', 'verdient'],
    'verfallen' => ['warnung', 'verfallen'],
];
?>
<div class="kopf"><h1>Empfehlungen</h1></div>

<?php if ($offen): ?>
  <div class="block">
    <h2 style="font-size:15px;margin:0 0 6px">Diese Namen warten auf eine Zuordnung</h2>
    <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 14px">
      Jemand hat einen Namen genannt, statt über einen Empfehlungslink zu kommen.
      Wer gemeint ist, entscheidest du — raten wäre hier der teuerste Fehler.
      Hat die Bestellung schon bezahlt, wird die Gutschrift beim Zuordnen nachgeholt.
    </p>
    <div class="tabellenrahmen"><table>
      <thead><tr><th>Genannt</th><th>Von wem</th><th>Wann</th><th style="width:290px">Zuordnen</th></tr></thead>
      <tbody>
      <?php foreach ($offen as $o): ?>
        <tr>
          <td><strong><?= Fmt::h((string) $o['genannt_als']) ?></strong></td>
          <td><?= Fmt::h((string) ($o['geworbener'] ?? '—')) ?>
            <?php if (($o['geworbener_firma'] ?? '') !== ''): ?>
              <div style="color:var(--leise);font-size:12px"><?= Fmt::h((string) $o['geworbener_firma']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12.5px;color:var(--leise)"><?= Fmt::h(Fmt::datum((string) $o['created_at'])) ?></td>
          <td>
            <form method="post" action="<?= Fmt::h(url('')) ?>" class="reihe" style="gap:8px;margin:0">
              <?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="empfehlung_zuordnen">
              <input type="hidden" name="zurueck" value="empfehlungen">
              <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
              <select name="kunde" style="flex:1 1 auto">
                <option value="">— wer war das? —</option>
                <?php foreach ($kunden as $k): ?>
                  <?php if ((int) $k['id'] === (int) ($o['geworbener_id'] ?? 0)) { continue; } ?>
                  <option value="<?= (int) $k['id'] ?>"><?= Fmt::h((string) $k['name']) ?><?php
                    if (($k['company'] ?? '') !== '') { echo ' — ' . Fmt::h((string) $k['company']); } ?></option>
                <?php endforeach; ?>
              </select>
              <button class="knopf">Gutschreiben</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 12px">Alle Empfehlungen</h2>
  <div class="tabellenrahmen"><table>
    <thead><tr>
      <th>Empfehler</th><th>Rabatt läuft</th><th>Geworben</th>
      <th>Weg</th><th>Stand</th><th>Wann</th>
    </tr></thead>
    <tbody>
    <?php if (!$liste): ?>
      <tr><td colspan="6"><div class="leer">Noch keine Empfehlung eingegangen.</div></td></tr>
    <?php endif; ?>
    <?php foreach ($liste as $z): ?>
      <?php [$farbe, $wort] = $stufen[(string) $z['status']] ?? ['', (string) $z['status']]; ?>
      <tr>
        <td>
          <?php if ($z['empfehler_id']): ?>
            <a href="<?= Fmt::h(url('kunden/' . $z['empfehler_id'])) ?>"><strong><?= Fmt::h((string) $z['empfehler']) ?></strong></a>
            <?php if (($z['code'] ?? '') !== ''): ?>
              <div style="color:var(--leise);font-size:12px;font-family:ui-monospace,monospace"><?= Fmt::h((string) $z['code']) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <span style="color:var(--leise)">„<?= Fmt::h((string) $z['genannt_als']) ?>" — noch niemand</span>
          <?php endif; ?>
        </td>
        <td style="font-size:13px">
          <?php if ($z['empfehler_id'] && ($z['rabatt_bis'] ?? null) && (int) $z['rabatt_prozent'] > 0): ?>
            <?= (int) $z['rabatt_prozent'] ?> % bis <?= Fmt::h(Fmt::datum((string) $z['rabatt_bis'])) ?>
          <?php else: ?><span style="color:var(--leise)">—</span><?php endif; ?>
        </td>
        <td><?= Fmt::h((string) ($z['geworbener'] ?? '—')) ?></td>
        <td style="font-size:12.5px;color:var(--leise)"><?= $z['quelle'] === 'link' ? 'Link' : 'genannt' ?></td>
        <td><span class="marke2 <?= Fmt::h($farbe) ?>"><?= Fmt::h($wort) ?></span>
          <?php if (($z['grund'] ?? '') !== ''): ?>
            <div style="color:var(--leise);font-size:12px"><?= Fmt::h((string) $z['grund']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:12.5px;color:var(--leise)"><?= Fmt::h(Fmt::seit((string) $z['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">So funktioniert es</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.75;margin:0">
    Jeder Kunde hat einen Empfehlungscode; sein Link steht in seiner Kundenakte
    zum Kopieren. Wer darüber kommt, ist eindeutig zugeordnet. Wer stattdessen
    einen Namen in den Konfigurator tippt, landet oben in der Arbeitsliste.
    Verdient wird, sobald der Geworbene bezahlt hat — <?= (int) $prozent ?> %
    auf die Betreuung für <?= (int) $monate ?> Monate. Mehrere Empfehlungen
    verlängern die Laufzeit, statt den Satz zu erhöhen. Wird zurückerstattet,
    verfällt die Empfehlung und der Rabatt wird neu gerechnet.
  </p>
</div>
